/**
 * Тесты интеграции с Redis для подсистемы Rate Limiting (ТЗ № LIMIS-FEEDBACK-01).
 *
 * Проверяют:
 * - Атомарность и уникальность резерваций rate limit через Lua-скрипт.
 * - Возможность отката (release) резервации.
 * - Поведение fail-closed при недоступности настроенного Redis-сервера (503 rate_unavailable).
 */

import assert from 'node:assert/strict';
import { createServer } from 'node:net';
import { execFileSync, spawn } from 'node:child_process';
import { mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { createHmac, randomBytes } from 'node:crypto';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import test from 'node:test';

const phpBinary = process.env.PHP_BINARY || 'php';
const redisHost = process.env.LIMIS_REDIS_HOST || '127.0.0.1';
const redisPort = process.env.LIMIS_REDIS_PORT || '6379';
const redisPassword = process.env.LIMIS_REDIS_PASSWORD || '';

async function unusedPort() {
    const server = createServer();
    await new Promise((resolveListen, reject) => {
        server.once('error', reject);
        server.listen(0, '127.0.0.1', resolveListen);
    });
    const { port } = server.address();
    await new Promise((resolveClose, reject) => server.close(error => error ? reject(error) : resolveClose()));
    return port;
}

test('Redis rate reservations are atomic, unique, and releasable', (t) => {
    const phpScript = `
        require_once __DIR__ . '/src/RedisClient.php';

        try {
            $r = new RedisClient(
                getenv('LIMIS_REDIS_HOST') ?: '127.0.0.1',
                (int)(getenv('LIMIS_REDIS_PORT') ?: 6379),
                1.0,
                getenv('LIMIS_REDIS_PASSWORD') ?: null
            );
        } catch (Throwable $e) {
            echo json_encode(['stage' => 'connect', 'error' => $e->getMessage()]);
            exit;
        }

        $key = 'lf:test:rate:v2:' . bin2hex(random_bytes(8));
        $result = null;
        $failure = null;
        try {
            $first = $r->reserveRateLimit($key, 'nonce-1', 3, 60);
            $duplicate = $r->reserveRateLimit($key, 'nonce-1', 3, 60);
            $second = $r->reserveRateLimit($key, 'nonce-2', 3, 60);
            $third = $r->reserveRateLimit($key, 'nonce-3', 3, 60);
            $denied = $r->reserveRateLimit($key, 'nonce-4', 3, 60);
            $released = $r->releaseRateLimit($key, 'nonce-2');
            $afterRelease = $r->reserveRateLimit($key, 'nonce-4', 3, 60);
            $result = compact('first', 'duplicate', 'second', 'third', 'denied', 'released', 'afterRelease');
        } catch (Throwable $e) {
            $failure = $e->getMessage();
        }

        try {
            $r->cmd('DEL', $key);
        } catch (Throwable $e) {
            $failure = $failure ?: 'Redis test key cleanup failed: ' . $e->getMessage();
        }
        $r->close();

        echo json_encode($failure === null
            ? ['stage' => 'complete', 'result' => $result]
            : ['stage' => 'operation', 'error' => $failure]);
    `;

    let stdout;
    try {
        stdout = execFileSync(phpBinary, ['-n', '-r', phpScript], {
            cwd: process.cwd(),
            encoding: 'utf8',
            timeout: 10000,
            env: {
                ...process.env,
                LIMIS_REDIS_HOST: redisHost,
                LIMIS_REDIS_PORT: redisPort,
                LIMIS_REDIS_PASSWORD: redisPassword
            }
        });
    } catch (error) {
        if (error.code === 'ENOENT') {
            t.skip(`PHP executable not found: ${phpBinary}`);
            return;
        }
        throw error;
    }

    const response = JSON.parse(stdout.trim());
    if (response.stage === 'connect') {
        t.skip(`Redis is unavailable at ${redisHost}:${redisPort}: ${response.error}`);
        return;
    }
    assert.equal(response.stage, 'complete', response.error);

    const { first, duplicate, second, third, denied, released, afterRelease } = response.result;
    assert.equal(first.allowed, true);
    assert.equal(first.count, 1);
    assert.equal(first.reserved, true);
    assert.ok(first.ttl > 0 && first.ttl <= 60);
    assert.equal(duplicate.allowed, true);
    assert.equal(duplicate.count, 1);
    assert.equal(duplicate.reserved, false);
    assert.equal(second.count, 2);
    assert.equal(third.count, 3);
    assert.equal(denied.allowed, false);
    assert.equal(denied.count, 3);
    assert.equal(denied.reserved, false);
    assert.equal(released, true);
    assert.equal(afterRelease.allowed, true);
    assert.equal(afterRelease.count, 3);
    assert.equal(afterRelease.reserved, true);
});

test('submit endpoint fails closed when configured Redis is unreachable', async (t) => {
    const phpBinary = process.env.PHP_BINARY || 'php';
    try {
        execFileSync(phpBinary, ['-v'], { stdio: 'ignore' });
    } catch (error) {
        if (error.code === 'ENOENT') {
            t.skip(`PHP executable not found: ${phpBinary}`);
            return;
        }
        throw error;
    }

    const tempDirectory = mkdtempSync(join(tmpdir(), 'limis-redis-test-'));
    const configPath = join(tempDirectory, 'config.php');
    const secret = randomBytes(32).toString('hex');
    const serverPort = await unusedPort();
    const closedRedisPort = await unusedPort();

    const config = Buffer.from(JSON.stringify({
        dsn: 'mysql:host=127.0.0.1;dbname=unavailable_for_test;charset=utf8mb4',
        db_user: 'test',
        db_pass: '',
        secret,
        admin_hash: '',
        redis_enabled: true,
        redis_host: '127.0.0.1',
        redis_port: closedRedisPort,
        redis_timeout: 0.1
    })).toString('base64');
    writeFileSync(configPath, `<?php return json_decode(base64_decode('${config}'), true);`);

    let server;
    try {
        server = spawn(phpBinary, [
            '-n',
            '-S',
            `127.0.0.1:${serverPort}`,
            '-t',
            resolve('public')
        ], {
            cwd: process.cwd(),
            env: { ...process.env, LF_CONFIG: configPath },
            stdio: 'ignore'
        });
    } catch (error) {
        rmSync(tempDirectory, { recursive: true, force: true });
        if (error.code === 'ENOENT') {
            t.skip(`PHP executable not found: ${phpBinary}`);
            return;
        }
        throw error;
    }

    t.after(() => {
        server.kill();
        rmSync(tempDirectory, { recursive: true, force: true });
    });

    const baseUrl = `http://127.0.0.1:${serverPort}`;
    let tokenResponse;
    for (let attempt = 0; attempt < 50; attempt++) {
        try {
            tokenResponse = await fetch(`${baseUrl}/tagasiside/token.php`);
            break;
        } catch {
            if (server.exitCode !== null) {
                assert.fail(`PHP test server exited with code ${server.exitCode}`);
            }
            await new Promise(resolveDelay => setTimeout(resolveDelay, 100));
        }
    }
    assert.ok(tokenResponse, 'PHP test server did not become ready');
    assert.equal(tokenResponse.status, 200);
    const { token } = await tokenResponse.json();
    await new Promise(resolveDelay => setTimeout(resolveDelay, 2100));

    const response = await fetch(`${baseUrl}/tagasiside/submit.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            token,
            lang: 'et',
            purpose: 'eservices',
            completed: 'yes',
            ease_rating: 4
        })
    });

    assert.equal(response.status, 503);
    assert.deepEqual(await response.json(), { error: 'rate_unavailable' });
});
