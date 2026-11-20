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

        if (@fwrite($this->fp, $payload) === false) {
            throw new RuntimeException('Failed to write to Redis socket');
        }

        return $this->parseResponse();
    }

    private function parseResponse(): mixed
    {
        $line = fgets($this->fp);
        if ($line === false) {
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
                $read = 0;
                $data = '';
                while ($read < $len) {
                    $chunk = fread($this->fp, $len - $read);
                    if ($chunk === false || $chunk === '') {
                        throw new RuntimeException('Failed reading bulk string from Redis');
                    }
                    $data .= $chunk;
                    $read += strlen($chunk);
                }
                fread($this->fp, 2); // Пропускаем завершающий \r\n
                return $data;
            case '*': // Array
                $count = (int)$content;
                if ($count === -1) {
                    return null;
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
     * Атомарный счётчик ограничения частоты (Rate Limiting) на Lua-скрипте.
     * При первом запросе создаётся счётчик и задаётся TTL.
     *
     * @return array{allowed: bool, count: int, ttl: int}
     */
    public function rateLimit(string $key, int $max = 3, int $ttl = 3600): array
    {
        $script = '
            local current = redis.call("INCR", KEYS[1])
            if current == 1 then
                redis.call("EXPIRE", KEYS[1], ARGV[1])
            end
            local ttl = redis.call("TTL", KEYS[1])
            return {current, ttl}
        ';

        $res = $this->cmd('EVAL', $script, '1', $key, (string)$ttl);
        $count = (int)($res[0] ?? 0);
        $remainingTtl = (int)($res[1] ?? 0);

        return [
            'allowed' => $count <= $max,
            'count'   => $count,
            'ttl'     => $remainingTtl,
        ];
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
