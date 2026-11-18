<?php
// Скопируйте в config/config.php. Папка config/ НЕ должна быть в web-root.

return [
    'dsn'        => 'mysql:host=127.0.0.1;dbname=limis_feedback_proto;charset=utf8mb4',
    'db_user'    => 'proto',
    'db_pass'    => 'change-me',
    'secret'     => 'замените-на-случайные-64-символа', // HMAC для токенов и ключей лимита
    'admin_hash' => '', // php -r "echo password_hash('пароль', PASSWORD_DEFAULT);"
];
