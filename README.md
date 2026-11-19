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
## Проверки
В локальном окружении: синтаксис всех PHP-файлов проверен; `/tagasiside/token.php` вернул JSON-токен; GET на `/tagasiside/submit.php` вернул 405; MariaDB advisory lock доступен; на ширине 320 px модальное окно не переполняет viewport.

Полный функциональный чек-лист ещё не выполнен: не проверены запись валидного ответа, лимит отправок, восстановление после ошибок БД, удаление по расписанию, реальная авторизация LIMIS, production-сессии/CSP и скринридер.

Автоприглашение: открыть две разные страницы демо и подождать 45 с активного просмотра. Пороги можно изменить в начале `public/index.php`: `$inviteAfterSeconds` и `$inviteMinPages`. Важная операция: страница `apply`. Подавление на 30 дней: закрыть опрос, кнопка остаётся.
