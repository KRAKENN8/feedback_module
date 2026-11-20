<?php

declare(strict_types=1);

/**
 * Публичный эндпоинт выдачи одноразового stateless HMAC-токена для формы отзыва.
 *
 * Токен не создаёт сессию на сервере, предотвращает CSRF и обеспечивает
 * защиту от роботов благодаря проверке минимальной задержки перед отправкой (2 секунды).
 *
 * Заголовки ответа:
 * - Content-Type: application/json; charset=utf-8
 * - Cache-Control: no-store (запрет кэширования браузером и промежуточными прокси)
 */

require __DIR__ . '/../../src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

echo json_encode(['token' => make_token()]);
