# Интеграция с LIMIS (все пункты NOT VERIFIED)
- **Встраивание:** `<script src=".../widget.js" data-lang="et|ru" data-page="<page_group>" data-invite-after-seconds="45" data-invite-min-pages="2">`. `page_group` — только из списка в `src/bootstrap.php`.
- **Порог автоприглашения:** `data-invite-after-seconds` задаёт секунды активности (по умолчанию `45`), `data-invite-min-pages` — минимальное число разных страниц (по умолчанию `2`). Указывайте одинаковые значения на всех страницах; в демо их можно изменить в начале `public/index.php` (`$inviteAfterSeconds`, `$inviteMinPages`). Значения должны быть целыми неотрицательными секундами и целым количеством страниц не меньше `1`.
- **Язык:** LIMIS задаёт `data-lang`; фактический источник языка в LIMIS неизвестен.
- **Важные операции:** LIMIS вызывает `LimisFeedback.setCritical(true/false)` или `window.dispatchEvent(new CustomEvent('limis-feedback:critical',{detail:{active:true}}))` при заявлениях, авторизации, подписании, оплате, отправке данных.
- **Авторизация:** демо-логин в `admin/tagasiside.php` заменить проверкой реальной роли `Peakasutaja` на каждом admin-endpoint, включая CSV.
- **Сессии:** `token.php`/`submit.php` не используют PHP-сессию; на тестовой копии проверить, что они не продлевают сессию LIMIS.
- **БД/инфраструктура:** заменить DSN, применить миграции, настроить cron `bin/purge.php`; rate limit в БД (Redis не использовался).
- **Тесты разработчика LIMIS:** язык, встраивание, подавление на реальных операциях, роль, сессии, заголовки/CSP, UI-совместимость.
- **Демо-части для замены:** `public/index.php`, демо-логин, localStorage-логика приглашений (per-browser, пользователь может очистить).
