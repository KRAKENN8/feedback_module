<?php

declare(strict_types=1);

function cfg(): array
{
    static $c;
    return $c ??= require (getenv('LF_CONFIG') ?: __DIR__ . '/../config/config.php');
}

function db(): PDO
{
    static $p;
    return $p ??= new PDO(
        cfg()['dsn'],
        cfg()['db_user'],
        cfg()['db_pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

// Stateless CSRF-токен для анонимов (без PHP-сессии): nonce.ts.hmac
function make_token(): string
{
    $n = bin2hex(random_bytes(16));
    $t = time();
    return "$n.$t." . hash_hmac('sha256', "$n.$t", cfg()['secret']);
}

function verify_token(string $tok): ?string
{
    $p = explode('.', $tok);
    if (count($p) !== 3) {
        return null;
    }

    [$n, $t, $h] = $p;
    if (!ctype_xdigit($n) || strlen($n) !== 32 || !ctype_digit($t)) {
        return null;
    }

    if (!hash_equals(hash_hmac('sha256', "$n.$t", cfg()['secret']), $h)) {
        return null;
    }

    $age = time() - (int)$t;
    return ($age >= 2 && $age <= 7200) ? $n : null;
}

const ENUMS = [
    'lang' => ['et', 'ru'],
    'purpose' => ['eservices', 'procurement', 'property', 'account', 'appointment', 'other'],
    'completed' => ['yes', 'partial', 'no', 'none'],
    'difficulty' => ['search', 'application', 'ui', 'mobile', 'speed', 'none', 'other'],
    'page_group' => ['home', 'eservices', 'procurement', 'property', 'account', 'appointment'],
];
