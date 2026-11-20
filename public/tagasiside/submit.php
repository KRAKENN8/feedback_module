<?php

declare(strict_types=1);

/**
 * Публичный эндпоинт приёма и сохранения ответов формы отзыва.
 *
 * Пайплайн обработки запроса:
 * 1. Проверка метода HTTP: только POST (иначе 405 Method Not Allowed с заголовком Allow: POST).
 * 2. Чтение и валидация JSON-тела (максимум 8 КБ, иначе 400 Bad Request).
 * 3. Ловушка для ботов (honeypot): при заполнении поля 'website' отдается фиктивный 200 OK.
 * 4. Проверка подписи HMAC-токена и временного окна 2..7200 сек (иначе 403 Forbidden).
 * 5. Валидация полей через FeedbackValidator (иначе 422 Unprocessable Entity).
 * 6. Ограничение частоты (Rate Limiting) — максимум 3 отправки в час с одного IP:
 *    - По умолчанию через атомарный Lua-скрипт в Redis (reserveRateLimit/releaseRateLimit).
 *    - При отключённом Redis — через блокировку GET_LOCK и таблицу limis_tagasiside_guard в MariaDB.
 *    - При отказе включённого Redis — fail-closed (503 Service Unavailable).
 * 7. Атомарная транзакция MariaDB:
 *    - Проверка уникальности nonce (защита от дубликатов / double-submit).
 *    - Сохранение отзыва в таблицу limis_tagasiside.
 *    - При ошибке транзакции — автоматический откат резервирования в Redis.
 */

require __DIR__ . '/../../src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

/**
 * Вспомогательная функция отправки JSON-ответа и завершения работы скрипта.
 *
 * @param int                  $code HTTP статус-код
 * @param array<string, mixed> $data Тело ответа
 * @return never
 */
function out(int $code, array $data): never
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// 1. Проверка метода запроса
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    out(405, ['error' => 'method']);
}

// 2. Чтение и декодирование JSON (ограничение размера до 8192 байт)
$in = json_decode(file_get_contents('php://input', false, null, 0, 8192) ?: '', true);
if (!is_array($in)) {
    out(400, ['error' => 'invalid']);
}

// 3. Honeypot: скрытое поле для спам-ботов
if (!empty($in['website'])) {
    out(200, ['ok' => true]);
}

// 4. Проверка HMAC-токена и извлечение уникального nonce
$nonce = verify_token((string)($in['token'] ?? ''));
if (!$nonce) {
    out(403, ['error' => 'csrf']);
}

// 5. Валидация типов, диапазонов и допустимых значений полей
$validation = FeedbackValidator::validate($in);
if (!$validation['ok']) {
    out(422, ['error' => $validation['error']]);
}

$data = $validation['data'];
$lang = $data['lang'];
$purpose = $data['purpose'];
$completed = $data['completed'];
$rating = $data['ease_rating'];
$diff = $data['difficulty'];
$pg = $data['page_group'];
$com = $data['comment'];

// Ключ rate limit на основе HMAC от IP-адреса клиента
$rk = 'r:' . hash_hmac('sha256', client_ip(), cfg()['secret']);
$redisEnabled = !empty(cfg()['redis_enabled']);
$redisKey = 'lf:rate:v2:' . $rk;
$redisReservation = null;

// 6. Ограничение частоты отправок через Redis (при включённом бэкенде)
// При включённом Redis отказ backend не должен переключать запросы на отдельный счётчик.
if ($redisEnabled) {
    try {
        $redis = redis();
        if ($redis === null) {
            throw new RuntimeException('Redis rate limiter is enabled but unavailable');
        }

        $rate = $redis->reserveRateLimit($redisKey, $nonce, 3, 3600);
        if (!$rate['allowed']) {
            out(429, ['error' => 'rate']);
        }

        if ($rate['reserved']) {
            $redisReservation = [$redisKey, $nonce];
        }
    } catch (Throwable $e) {
        error_log('Redis rate limit unavailable: ' . $e->getMessage());
        out(503, ['error' => 'rate_unavailable']);
    }
}

/**
 * Замыкание для отката зарезервированного счётчика в Redis при сбое сохранения в БД.
 */
$releaseRedisReservation = static function () use (&$redisReservation): void {
    if ($redisReservation === null) {
        return;
    }

    [$key, $member] = $redisReservation;
    $redisReservation = null;
    try {
        $client = redis();
        if ($client !== null) {
            $client->releaseRateLimit($key, $member);
        }
    } catch (Throwable $e) {
        error_log('Redis rate limit reservation release failed: ' . $e->getMessage());
    }
};

// 7. Сохранение в базу данных MariaDB
try {
    $db = db();

    // Если Redis явно отключён, ограничиваем частоту через MariaDB guard и именованную блокировку.
    $lockName = null;
    if (!$redisEnabled) {
        $lockName = 'lf:' . substr($rk, 2, 61);
        $lock = $db->prepare('SELECT GET_LOCK(?, 5)');
        $lock->execute([$lockName]);
        if ((int)$lock->fetchColumn() !== 1) {
            out(503, ['error' => 'busy']);
        }
    }

    $response = null;
    try {
        $db->beginTransaction();

        // Проверка уникальности nonce (защита от повторной отправки одной и той же формы)
        try {
            $db->prepare('INSERT INTO limis_tagasiside_nonce(nonce,expires_at) VALUES(?,DATE_ADD(NOW(),INTERVAL 2 HOUR))')
                ->execute([$nonce]);
        } catch (PDOException $e) {
            // Ошибка 1062 — дубликат первичного ключа
            if ((int)($e->errorInfo[1] ?? 0) !== 1062) {
                throw $e;
            }
            $db->rollBack();
            $response = [409, ['error' => 'duplicate']];
        }

        if ($response === null) {
            // Резервный rate-limit через MariaDB guard (только если Redis выключен)
            if (!$redisEnabled) {
                $c = $db->prepare('SELECT COUNT(*) FROM limis_tagasiside_guard WHERE k=? AND expires_at>NOW()');
                $c->execute([$rk]);
                if ((int)$c->fetchColumn() >= 3) {
                    $db->rollBack();
                    $response = [429, ['error' => 'rate']];
                } else {
                    $db->prepare('INSERT INTO limis_tagasiside_guard(k,expires_at) VALUES(?,DATE_ADD(NOW(),INTERVAL 1 HOUR))')
                        ->execute([$rk]);
                }
            }

            // Запись отзыва в постоянную таблицу
            if ($response === null) {
                $db->prepare('INSERT INTO limis_tagasiside(lang,purpose,completed,ease_rating,difficulty,comment,page_group) VALUES(?,?,?,?,?,?,?)')
                    ->execute([$lang, $purpose, $completed, $rating, $diff, $com, $pg]);

                $db->commit();
                $response = [200, ['ok' => true]];
            }
        }
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    } finally {
        if ($lockName !== null) {
            $release = $db->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lockName]);
        }
    }

    // Если ответ не успешный (дубликат или лимит), освобождаем попытку в Redis
    if ($response[0] !== 200) {
        $releaseRedisReservation();
    } else {
        $redisReservation = null;
    }

    out($response[0], $response[1]);
} catch (Throwable $e) {
    $releaseRedisReservation();
    // Логирование внутренней ошибки сервера без раскрытия деталей клиенту
    error_log('tagasiside: ' . get_class($e));
    out(500, ['error' => 'server']);
}
