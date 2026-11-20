<?php

declare(strict_types=1);

/**
 * Панель администрирования отзывов LIMIS.
 *
 * Обеспечивает:
 * - Проверку прав доступа: исключительно для роли Peakasutaja.
 * - Поддержку режимов авторизации: штатный LIMIS ($_SESSION['active_role']) и автономный (standalone по паролю).
 * - Фильтрацию данных по временным интервалам (7, 30, 90, 180 дней) и языку отзыва.
 * - Расчёт ключевых метрик: общее число ответов, средняя оценка, распределения по категориям.
 * - Защищённый экспорт в CSV (экранирование формул, разделитель ';', UTF-8 с BOM).
 */

require __DIR__ . '/../../src/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

$ui = $_GET['ui'] ?? ($_SESSION['ui'] ?? 'et');
$ui = in_array($ui, ['et', 'ru'], true) ? $ui : 'et';
$_SESSION['ui'] = $ui;

$translations = require __DIR__ . '/../../src/i18n/admin.php';
$T = $translations[$ui];

/**
 * Безопасное экранирование строк для вывода в HTML.
 */
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$csrf = $_SESSION['csrf'] ??= bin2hex(random_bytes(16));

// Обработка выхода из сессии
if (isset($_POST['logout']) && hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
    $logoutUi = $ui;
    AdminAuth::logout();
    session_destroy();
    header('Location: tagasiside.php?ui=' . $logoutUi);
    exit;
}

// Авторизация администратора по паролю (в автономном режиме / standalone)
if (AdminAuth::getAuthMode() === 'standalone' && isset($_POST['password'])) {
    if (
        hash_equals($csrf, (string)($_POST['csrf'] ?? ''))
        && cfg()['admin_hash']
        && password_verify((string)$_POST['password'], cfg()['admin_hash'])
    ) {
        session_regenerate_id(true);
        AdminAuth::setSessionRole(AdminAuth::ROLE_PEAKASUTAJA);
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
        header('Location: tagasiside.php');
        exit;
    }
    $err = true;
}

// Проверка прав на сервере: доступ разрешён исключительно роли Peakasutaja
if (!AdminAuth::isPeakasutaja()) {
    if (isset($_GET['csv'])) {
        http_response_code(403);
        exit('Forbidden');
    }

    // Если пользователь уже авторизован под другой ролью (напр. kasutaja/teenistuja или kodanik_er)
    // или если включён штатный режим LIMIS, отдаём 403 Forbidden с пояснением
    if (AdminAuth::isAuthenticated() || AdminAuth::getAuthMode() === 'limis') {
        http_response_code(403);
        $activeRole = AdminAuth::getActiveRole() ?? 'puudub';
        require __DIR__ . '/tagasiside-forbidden.view.php';
        exit;
    }

    $loginError = isset($err);
    require __DIR__ . '/tagasiside-login.view.php';
    exit;
}

// Параметры фильтрации: период в днях
$days = (int)($_GET['days'] ?? 30);
if (!in_array($days, [7, 30, 90, 180], true)) {
    $days = 30;
}

// Параметры фильтрации: язык ответа
$lang = in_array($_GET['lang'] ?? '', ['et', 'ru'], true) ? $_GET['lang'] : '';
$w = 'created_at>=DATE_SUB(NOW(),INTERVAL ' . $days . ' DAY)' . ($lang ? ' AND lang=?' : '');
$a = $lang ? [$lang] : [];

/**
 * Подготовленный SQL-запрос с автоматическим выполнением.
 */
$q = function (string $sql, array $p = []) {
    $s = db()->prepare($sql);
    $s->execute($p);
    return $s;
};

// Безопасные заголовки для защиты от MIME-sniffing и Clickjacking
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

// Экспорт отзывов в формате CSV
if (isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=tagasiside.csv');

    $o = fopen('php://output', 'w');
    // Добавление UTF-8 BOM для корректного открытия в Excel
    fwrite($o, "\xEF\xBB\xBF");

    // Защита от CSV Formula Injection: экранирование символов '=', '+', '-', '@', '\t', '\r'
    $safe = fn($v) => ($v !== null && preg_match('/^[=+\-@\t\r]/', (string)$v)) ? "'" . $v : $v;

    $cols = ['id', 'created_at', 'lang', 'purpose', 'completed', 'ease_rating', 'difficulty', 'comment', 'page_group'];
    fputcsv($o, $cols, ';', '"', "\\");

    $stmt = db()->prepare("SELECT " . implode(',', $cols) . " FROM limis_tagasiside WHERE $w ORDER BY id DESC");
    $stmt->execute($a);
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        fputcsv($o, array_map($safe, $row), ';', '"', "\\");
    }
    exit;
}

// Агрегированные метрики для дашборда
$tot = (int)$q("SELECT COUNT(*) FROM limis_tagasiside WHERE $w", $a)->fetchColumn();
$avg = $q("SELECT ROUND(AVG(ease_rating),2) FROM limis_tagasiside WHERE $w", $a)->fetchColumn();

// Распределение ответов по категориальным столбцам
$dist = fn($col) => $q("SELECT $col k,COUNT(*) n FROM limis_tagasiside WHERE $w AND $col IS NOT NULL GROUP BY $col ORDER BY n DESC", $a)->fetchAll(PDO::FETCH_KEY_PAIR);

/**
 * Генератор горизонтальных полос статистики для дашборда.
 */
$bars = function (array $d, int $t, string $type) use ($h, $T) {
    $o = '';
    foreach ($d as $k => $n) {
        $p = $t ? round($n * 100 / $t) : 0;
        $label = $T[$type . '_values'][$k] ?? $k;
        $o .= '<div class="bar-row"><span class="bar-label">' . $h($label) . '</span>'
            . '<span class="bar-track"><i data-percent="' . $p . '" aria-hidden="true"></i></span>'
            . '<b class="bar-value">' . (int)$n . ' (' . $p . '%)</b></div>';
    }
    return $o ?: '<p class="empty-state">' . $h($T['no_responses']) . '</p>';
};

// Выборка последних текстовых комментариев
$comments = $q("SELECT created_at,lang,ease_rating,comment FROM limis_tagasiside WHERE $w AND comment IS NOT NULL ORDER BY id DESC LIMIT 100", $a)->fetchAll(PDO::FETCH_ASSOC);
require __DIR__ . '/tagasiside-dashboard.view.php';
