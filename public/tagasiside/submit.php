<?php

declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(int $c, array $d): never
{
    http_response_code($c);
    echo json_encode($d);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    out(405, ['error' => 'method']);
}

$in = json_decode(file_get_contents('php://input', false, null, 0, 8192) ?: '', true);
if (!is_array($in)) {
    out(400, ['error' => 'invalid']);
}

// honeypot
if (!empty($in['website'])) {
    out(200, ['ok' => true]);
}

$nonce = verify_token((string)($in['token'] ?? ''));
if (!$nonce) {
    out(403, ['error' => 'csrf']);
}

$lang = $in['lang'] ?? null;
$purpose = $in['purpose'] ?? null;
$completed = $in['completed'] ?? null;
$rating = $in['ease_rating'] ?? null;
$diff = $in['difficulty'] ?? null;
$pg = $in['page_group'] ?? null;
$com = $in['comment'] ?? null;

if (
    !in_array($lang, ENUMS['lang'], true)
    || !in_array($purpose, ENUMS['purpose'], true)
    || !in_array($completed, ENUMS['completed'], true)
    || !is_int($rating)
    || $rating < 1
    || $rating > 5
) {
    out(422, ['error' => 'validation']);
}

if ($diff !== null && $diff !== '' && !in_array($diff, ENUMS['difficulty'], true)) {
    out(422, ['error' => 'validation']);
}

if ($pg !== null && $pg !== '' && !in_array($pg, ENUMS['page_group'], true)) {
    out(422, ['error' => 'validation']);
}

if ($com !== null && !is_string($com)) {
    out(422, ['error' => 'validation']);
}

$com = $com === null ? null : trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $com));
if ($com !== null && mb_strlen($com) > 500) {
    out(422, ['error' => 'too_long']);
}

if ($com === '') {
    $com = null;
}
if ($diff === '') {
    $diff = null;
}
if ($pg === '') {
    $pg = null;
}

$rk = 'r:' . hash_hmac('sha256', $_SERVER['REMOTE_ADDR'] ?? '', cfg()['secret']);
$redisEnabled = !empty(cfg()['redis_enabled']);
$redisKey = 'lf:rate:v2:' . $rk;
$redisReservation = null;

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

try {
    $db = db();

    // Если Redis явно отключён, ограничиваем частоту через MariaDB guard.
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

        try {
            $db->prepare('INSERT INTO limis_tagasiside_nonce(nonce,expires_at) VALUES(?,DATE_ADD(NOW(),INTERVAL 2 HOUR))')
                ->execute([$nonce]);
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) !== 1062) {
                throw $e;
            }
            $db->rollBack();
            $response = [409, ['error' => 'duplicate']];
        }

        if ($response === null) {
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

    if ($response[0] !== 200) {
        $releaseRedisReservation();
    } else {
        $redisReservation = null;
    }

    out($response[0], $response[1]);
} catch (Throwable $e) {
    $releaseRedisReservation();
    // без деталей клиенту
    error_log('tagasiside: ' . get_class($e));
    out(500, ['error' => 'server']);
}
