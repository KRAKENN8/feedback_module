# PowerShell test runner for LIMIS Feedback Module
param(
    [string]$PhpPath = "",
    [string]$NodePath = "",
    [int]$TestPort = 8089,
    [switch]$Clean
)

$ErrorActionPreference = "Stop"

# 1. Поиск PHP
if (-not $PhpPath) {
    $candidates = @(
        $env:PHP_BINARY,
        "E:\workspace\devtools\db\xampp\php8.5\php.exe",
        "E:\workspace\devtools\db\xampp\php\php.exe",
        (Get-Command php -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Source)
    )
    foreach ($cand in $candidates) {
        if ($cand -and (Test-Path $cand)) {
            $PhpPath = $cand
            break
        }
    }
}

if (-not $PhpPath -or -not (Test-Path $PhpPath)) {
    Write-Error "PHP binary not found. Specify via -PhpPath 'C:\path\to\php.exe'"
    exit 1
}

# 1.1. Очистка тестовых записей по ключу -Clean
if ($Clean) {
    Write-Host "Running test data cleanup (bin/clean-tests.php)..." -ForegroundColor Yellow
    & $PhpPath bin/clean-tests.php
    exit $LASTEXITCODE
}

# 2. Поиск Node.js
if (-not $NodePath) {
    $candidates = @(
        (Get-Command node -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Source),
        "C:\Program Files\nodejs\node.exe"
    )
    foreach ($cand in $candidates) {
        if ($cand -and (Test-Path $cand)) {
            $NodePath = $cand
            break
        }
    }
}

if (-not $NodePath -or -not (Test-Path $NodePath)) {
    Write-Error "Node.js not found. Specify via -NodePath 'C:\path\to\node.exe'"
    exit 1
}

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host " LIMIS Feedback Test Runner" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "PHP:  $PhpPath"
Write-Host "Node: $NodePath"

# 3. Проверка синтаксиса PHP
Write-Host "`n[1/4] Checking PHP syntax (php -l)..." -ForegroundColor Yellow
$phpFiles = Get-ChildItem -Path @("src", "public", "bin") -Filter "*.php" -Recurse | Select-Object -ExpandProperty FullName
foreach ($file in $phpFiles) {
    & $PhpPath -l $file | Out-Null
    if ($LASTEXITCODE -ne 0) {
        Write-Error "Syntax error in $file"
        exit 1
    }
}
Write-Host "  -> All PHP files syntax OK" -ForegroundColor Green

# 4. Проверка Redis
Write-Host "`n[2/4] Checking Redis status on 127.0.0.1:6379..." -ForegroundColor Yellow
$redisAvailable = $false
try {
    $tcp = New-Object System.Net.Sockets.TcpClient
    $iar = $tcp.BeginConnect("127.0.0.1", 6379, $null, $null)
    $success = $iar.AsyncWaitHandle.WaitOne(800, $false)
    if ($success -and $tcp.Connected) {
        $tcp.EndConnect($iar)
        $tcp.Close()
        $redisAvailable = $true
        Write-Host "  -> Redis connection: ACTIVE" -ForegroundColor Green
    } else {
        Write-Host "  -> Redis connection: OFFLINE (Redis tests will be skipped)" -ForegroundColor DarkGray
    }
} catch {
    Write-Host "  -> Redis connection: OFFLINE ($($_.Exception.Message))" -ForegroundColor DarkGray
}

# 5. Запуск Unit-тестов (виджет и Redis)
Write-Host "`n[3/4] Running unit tests..." -ForegroundColor Yellow
$env:PHP_BINARY = $PhpPath
& $NodePath --test tests/widget.test.mjs tests/redis.test.mjs
if ($LASTEXITCODE -ne 0) {
    Write-Error "Unit tests failed"
    exit 1
}

# 6. Запуск сервера и интеграционных тестов
Write-Host "`n[4/4] Starting local PHP server on port $TestPort and running integration tests..." -ForegroundColor Yellow
$serverProcess = Start-Process -FilePath $PhpPath -ArgumentList "-n", "-d", "extension_dir=`"$(Split-Path $PhpPath)\ext`"", "-d", "extension=php_mbstring.dll", "-d", "extension=php_pdo_mysql.dll", "-S", "127.0.0.1:$TestPort", "-t", "public" -PassThru
Start-Sleep -Milliseconds 800

try {
    $env:LIMIS_TEST_BASE_URL = "http://127.0.0.1:$TestPort"
    & $NodePath --test tests/admin.test.mjs tests/api.test.mjs
    if ($LASTEXITCODE -ne 0) {
        Write-Error "Integration tests failed"
        exit 1
    }
} finally {
    if ($serverProcess -and -not $serverProcess.HasExited) {
        Stop-Process -Id $serverProcess.Id -Force
    }
    if ($env:LIMIS_TEST_ALLOW_WRITE -eq "1") {
        Write-Host "`nCleaning up test records from database..." -ForegroundColor Yellow
        & $PhpPath bin/clean-tests.php
    }
}

Write-Host "`n==========================================" -ForegroundColor Green
Write-Host " ALL TESTS PASSED SUCCESSFULLY!" -ForegroundColor Green
Write-Host "==========================================" -ForegroundColor Green
