<?php

declare(strict_types=1);

/**
 * Легковесный клиент Redis на PHP-сокетах (RESP протокол).
 * Не требует php_redis.dll или внешних библиотек.
 */
class RedisClient
{
    /** @var resource|null */
    private $fp = null;
    private string $host;
    private int $port;
    private float $timeout;
    private ?string $auth;

    public function __construct(
        string $host = '127.0.0.1',
        int $port = 6379,
        float $timeout = 2.0,
        ?string $auth = null
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->timeout = $timeout;
        $this->auth = $auth;
        $this->connect();
    }

    private function connect(): void
    {
        $errno = 0;
        $errstr = '';
        $this->fp = @stream_socket_client(
            "tcp://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            $this->timeout
        );

        if (!$this->fp) {
            throw new RuntimeException("Redis connection failed: {$errstr} ({$errno})");
        }

        $sec = (int)$this->timeout;
        $usec = (int)(($this->timeout - $sec) * 1e6);
        stream_set_timeout($this->fp, $sec, $usec);

        if ($this->auth !== null && $this->auth !== '') {
            $res = $this->cmd('AUTH', $this->auth);
            if ($res !== 'OK') {
                throw new RuntimeException("Redis auth failed: {$res}");
            }
        }
    }

    public function cmd(string ...$args): mixed
    {
        if (!$this->fp) {
            $this->connect();
        }

        $payload = '*' . count($args) . "\r\n";
        foreach ($args as $arg) {
            $payload .= '$' . strlen($arg) . "\r\n{$arg}\r\n";
        }

        $written = 0;
        $payloadLength = strlen($payload);
        while ($written < $payloadLength) {
            $count = @fwrite($this->fp, substr($payload, $written));
            if ($count === false || $count === 0) {
                $this->close();
                throw new RuntimeException('Failed to write to Redis socket');
            }
            $written += $count;
        }

        return $this->parseResponse();
    }

    private function readBytes(int $length): string
    {
        $data = '';
        while (strlen($data) < $length) {
            $chunk = @fread($this->fp, $length - strlen($data));
            if ($chunk === false || $chunk === '') {
                $this->close();
                throw new RuntimeException('Failed to read from Redis socket');
            }
            $data .= $chunk;
        }

        return $data;
    }

    private function parseResponse(): mixed
    {
        $line = @fgets($this->fp);
        if ($line === false) {
            $this->close();
            throw new RuntimeException('Failed to read from Redis socket');
        }

        $type = $line[0];
        $content = substr(rtrim($line, "\r\n"), 1);

        switch ($type) {
            case '+': // Simple string
                return $content;
            case ':': // Integer
                return (int)$content;
            case '$': // Bulk string
                $len = (int)$content;
                if ($len === -1) {
                    return null;
                }
                if ($len < 0 || $len > 16777216) {
                    throw new RuntimeException('Invalid Redis bulk string length');
                }
                $data = $this->readBytes($len);
                if ($this->readBytes(2) !== "\r\n") {
                    $this->close();
                    throw new RuntimeException('Invalid Redis bulk string terminator');
                }
                return $data;
            case '*': // Array
                $count = (int)$content;
                if ($count === -1) {
                    return null;
                }
                if ($count < 0 || $count > 1024) {
                    throw new RuntimeException('Invalid Redis array length');
                }
                $arr = [];
                for ($i = 0; $i < $count; $i++) {
                    $arr[] = $this->parseResponse();
                }
                return $arr;
            case '-': // Error
                throw new RuntimeException('Redis error: ' . $content);
            default:
                throw new RuntimeException('Unknown Redis response type: ' . $type);
        }
    }

    /**
     * Резервирует уникальную заявку в атомарном окне rate limit.
     *
     * @return array{allowed: bool, count: int, ttl: int, reserved: bool}
     */
    public function reserveRateLimit(string $key, string $member, int $max = 3, int $ttl = 3600): array
    {
        if ($key === '' || $member === '' || $max < 1 || $ttl < 1) {
            throw new InvalidArgumentException('Invalid rate limit reservation parameters');
        }

        $script = '
            local added = redis.call("SADD", KEYS[1], ARGV[1])
            if redis.call("TTL", KEYS[1]) == -1 then
                redis.call("EXPIRE", KEYS[1], ARGV[3])
            end
            local current = redis.call("SCARD", KEYS[1])
            if current > tonumber(ARGV[2]) then
                if added == 1 then
                    redis.call("SREM", KEYS[1], ARGV[1])
                    current = current - 1
                    added = 0
                end
                return {0, current, redis.call("TTL", KEYS[1]), added}
            end
            return {1, current, redis.call("TTL", KEYS[1]), added}
        ';

        $res = $this->cmd('EVAL', $script, '1', $key, $member, (string)$max, (string)$ttl);

        return [
            'allowed' => (int)($res[0] ?? 0) === 1,
            'count' => (int)($res[1] ?? 0),
            'ttl' => (int)($res[2] ?? 0),
            'reserved' => (int)($res[3] ?? 0) === 1,
        ];
    }

    public function releaseRateLimit(string $key, string $member): bool
    {
        $script = '
            local removed = redis.call("SREM", KEYS[1], ARGV[1])
            if redis.call("SCARD", KEYS[1]) == 0 then
                redis.call("DEL", KEYS[1])
            end
            return removed
        ';

        return (int)$this->cmd('EVAL', $script, '1', $key, $member) === 1;
    }

    public function ping(): string
    {
        return (string)$this->cmd('PING');
    }

    public function close(): void
    {
        if ($this->fp) {
            @fclose($this->fp);
            $this->fp = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
