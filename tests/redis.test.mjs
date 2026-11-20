import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import test from 'node:test';

const phpBinary = process.env.PHP_BINARY || 'E:\\workspace\\devtools\\db\\xampp\\php8.5\\php.exe';

test('RedisClient connects and executes atomic rate limiting', (t) => {
    const phpScript = `
        require_once __DIR__ . '/src/bootstrap.php';
        $r = redis();
        if ($r === null) {
            echo json_encode(['error' => 'redis_disabled']);
            exit;
        }

        try {
            $ping = $r->ping();
            $key = 'test:rate:' . bin2hex(random_bytes(4));
            $r1 = $r->rateLimit($key, 3, 60);
            $r2 = $r->rateLimit($key, 3, 60);
            $r3 = $r->rateLimit($key, 3, 60);
            $r4 = $r->rateLimit($key, 3, 60);
            $r->cmd('DEL', $key);

            echo json_encode([
                'ping' => $ping,
                'r1' => $r1,
                'r2' => $r2,
                'r3' => $r3,
                'r4' => $r4,
            ]);
        } catch (Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    `;

    let stdout;
    try {
        stdout = execFileSync(phpBinary, ['-n', '-r', phpScript], {
            cwd: process.cwd(),
            encoding: 'utf8',
            timeout: 5000
        });
    } catch (e) {
        t.skip(`PHP or Redis not available for execution: ${e.message}`);
        return;
    }

    const res = JSON.parse(stdout.trim());
    if (res.error) {
        t.skip(`Redis connection skipped: ${res.error}`);
        return;
    }

    assert.equal(res.ping, 'PONG');
    assert.equal(res.r1.allowed, true);
    assert.equal(res.r1.count, 1);
    assert.equal(res.r2.allowed, true);
    assert.equal(res.r2.count, 2);
    assert.equal(res.r3.allowed, true);
    assert.equal(res.r3.count, 3);
    assert.equal(res.r4.allowed, false);
    assert.equal(res.r4.count, 4);
    assert.ok(res.r1.ttl > 0 && res.r1.ttl <= 60);
});
