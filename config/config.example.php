<?php
// Скопируйте в config/config.php. Папка config/ НЕ должна быть в web-root.

return [
    'dsn'        => 'mysql:host=127.0.0.1;dbname=limis_feedback;charset=utf8mb4',
    'db_user'    => 'limis_feedback_admin',
    'db_pass'    => 'change-me',
    'secret'     => 'замените-на-случайные-64-символа', // HMAC для токенов и ключей лимита
    'admin_hash' => '', // php -r "echo password_hash('пароль', PASSWORD_DEFAULT);"

    // Режим аутентификации панели администратора:
    // 'standalone' - локальный режим разработки с отдельным паролем (admin_hash)
    // 'limis'      - штатный режим LIMIS: проверяет роль $_SESSION['active_role'] или $_SESSION['role'] === 'peakasutaja'
    'auth_mode' => 'standalone',

    // Redis (Rate Limiting)
    'redis_enabled' => true,
    'redis_host'    => '127.0.0.1',
    'redis_port'    => 6379,
    'redis_pass'    => null,
    'redis_timeout' => 2.0,

    // Доверенные обратные прокси (Reverse Proxy) для корректного client IP
    'trusted_proxies' => ['127.0.0.1', '::1'],
];
