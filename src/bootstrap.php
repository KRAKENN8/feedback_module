<?php

declare(strict_types=1);

/**
 * Инициализация и системные функции модуля обратной связи LIMIS.
 *
 * Содержит:
 * - Загрузку конфигурации (`cfg()`).
 * - Управление подключением к Redis (`redis()`).
 * - Безопасное определение IP-клиента с учётом доверенных прокси (`client_ip()`).
 * - Подключение к базе данных MariaDB (`db()`).
 * - Генерацию и валидацию stateless HMAC-токенов (`make_token()`, `verify_token()`).
 * - Списки допустимых значений полей формы (`ENUMS`).
 */

require_once __DIR__ . '/RedisClient.php';
require_once __DIR__ . '/FeedbackValidator.php';
require_once __DIR__ . '/AdminAuth.php';

/**
 * Загрузка конфигурационного массива модуля.
 *
 * @return array<string, mixed>
 */
function cfg(): array
{
    static $c;
    return $c ??= require (getenv('LF_CONFIG') ?: __DIR__ . '/../config/config.php');
}

/**
 * Получение синглтон-клиента Redis при условии, что Redis включён в конфигурации.
 *
 * @return RedisClient|null Клиент Redis либо null, если Redis отключён
 */
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

/**
 * Определение IP-адреса клиента с поддержкой доверенных reverse-прокси.
 *
 * Заголовки X-Forwarded-For и X-Real-IP учитываются только в том случае,
 * если прямой адрес подключения ($_SERVER['REMOTE_ADDR']) входит в список 'trusted_proxies'.
 *
 * @return string IP-адрес клиента
 */
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

/**
 * Получение PDO-соединения с базой данных MariaDB.
 *
 * @return PDO
 */
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

/**
 * Генерация stateless HMAC-токена для анонимных пользователей (без создания PHP-сессии).
 * Формат токена: nonce.timestamp.hmac_sha256
 *
 * @return string
 */
function make_token(): string
{
    $n = bin2hex(random_bytes(16));
    $t = time();
    return "$n.$t." . hash_hmac('sha256', "$n.$t", cfg()['secret']);
}

/**
 * Проверка stateless HMAC-токена.
 *
 * Проверяет целостность подписи HMAC и временное окно:
 * - Минимальный возраст: 2 секунды (защита от ботов, отправляющих форму мгновенно).
 * - Максимальный возраст: 7200 секунд (2 часа).
 *
 * @param string $tok Строка токена
 * @return string|null 32-значный nonce токена при успешной проверке либо null
 */
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

/**
 * Списки допустимых значений полей формы.
 */
const ENUMS = [
    'lang' => ['et', 'ru'],
    'purpose' => ['eservices', 'procurement', 'property', 'account', 'appointment', 'other'],
    'completed' => ['yes', 'partial', 'no', 'none'],
    'difficulty' => ['search', 'application', 'ui', 'mobile', 'speed', 'none', 'other'],
    'page_group' => ['home', 'eservices', 'procurement', 'property', 'account', 'appointment'],
];
