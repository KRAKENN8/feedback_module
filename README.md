# LIMIS feedback module — автономный прототип (LIMIS-FEEDBACK-01)
Не интегрирован с LIMIS и не подтверждает совместимость. Только синтетические данные.
## Требования
PHP 8.5 (pdo_mysql), MariaDB 11.
## Запуск
1. `CREATE DATABASE limis_feedback_proto CHARACTER SET utf8mb4;` и отдельный пользователь только на эту БД.
2. `mariadb limis_feedback_proto < migrations/001_install.sql`
3. `cp config/config.example.php config/config.php`; задать пароль БД, `secret`, `admin_hash`.
4. `php bin/seed.php` (синтетика, 8 записей старше 180 дней)
5. `php -S localhost:8080 -t public` → http://localhost:8080/ (демо), /admin/tagasiside.php (админ).
## Очистка данных
`php bin/purge.php` (cron раз в сутки): ответы старше 180 дней + просроченные guard/nonce.
## Откат и удаление модуля
`mariadb limis_feedback_proto < migrations/001_rollback.sql`, затем удалить папку проекта.
## Структура
`public/` web-root · `src/` общий код · `config/` секреты (вне web-root) · `migrations/` · `bin/` CLI.
## Ручные проверки (результаты НЕ зафиксированы, тесты не запускались)
Автоприглашение: открыть две разные страницы демо и подождать 45 с активного просмотра. Пороги можно изменить в начале `public/index.php`: `$inviteAfterSeconds` и `$inviteMinPages`. Важная операция: страница `apply`. Подавление на 30 дней: закрыть опрос, кнопка остаётся. Валидация: curl с некорректным JSON на /tagasiside/submit.php.
