<?php

declare(strict_types=1);

require_once __DIR__ . '/RedisClient.php';
require_once __DIR__ . '/FeedbackValidator.php';

function cfg(): array
{
    static $c;
    return $c ??= require (getenv('LF_CONFIG') ?: __DIR__ . '/../config/config.php');
}

function redis(): ?RedisClient
{
    static $r;
    if ($r !== null) {
        return $r;
    }

    $c = cfg();
    if (empty($c['redis_enabled'])) {
        return null;
    }

    $host = $c['redis_host'] ?? '127.0.0.1';
    $port = (int)($c['redis_port'] ?? 6379);
    $auth = !empty($c['redis_pass']) ? (string)$c['redis_pass'] : null;
    $timeout = (float)($c['redis_timeout'] ?? 2.0);

    return $r = new RedisClient($host, $port, $timeout, $auth);
}

function client_ip(): string
{
    $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $trusted = cfg()['trusted_proxies'] ?? [];
    if (in_array($remote, $trusted, true)) {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $first = trim($parts[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $real = trim((string)$_SERVER['HTTP_X_REAL_IP']);
            if (filter_var($real, FILTER_VALIDATE_IP)) {
                return $real;
            }
        }
    }
    return $remote;
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
