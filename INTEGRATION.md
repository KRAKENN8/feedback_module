# Руководство по установке и технической интеграции

В данном документе приведена пошаговая техническая инструкция по развёртыванию, настройке веб-сервера, баз данных, подсистемы кэширования и встраиванию модуля обратной связи в портал электронных услуг LIMIS.

> [!NOTE]
> Смежные аспекты вынесены в специализированные документы:
> - **[COMPLIANCE.md](./COMPLIANCE.md)** — требования нормативных стандартов: защита персональных данных (GDPR), информационная безопасность (E-ITS) и доступность (EN 301 549 / WCAG 2.1 AA).
> - **[ASSUMPTIONS.md](./ASSUMPTIONS.md)** — архитектурные компромиссы, допущения, технические ограничения и матрица разграничения ответственности.

---

## 1. Архитектура и схема работы

Модуль спроектирован как полностью автономный встраиваемый сервис без внешних облачных зависимостей.

```
[ Браузер жителя (LIMIS) ]
       │
       ├─ 1. Загрузка widget.css и widget.js (статика)
       ├─ 2. GET /tagasiside/token.php (получение одноразового HMAC-токена)
       ├─ 3. POST /tagasiside/submit.php (отправка ответа в формате JSON)
       │
[ Бэкенд модуля обратной связи ]
       │
       ├─ Проверка rate limiting (Redis 127.0.0.1:6379, атомарный Lua-скрипт)
       ├─ Валидация полей и одноразового nonce (MariaDB limis_tagasiside_nonce)
       ├─ Сохранение ответа (MariaDB limis_tagasiside)
       │
[ Панель администратора ] ─── GET /admin/tagasiside.php
       │
       └─ Проверка роли Peakasutaja в сессии LIMIS ($_SESSION['active_role'])
          и отображение агрегированной аналитики / экспорт CSV
```

1. **Клиентская часть:** скрипт `widget.js` отслеживает навигацию пользователя (по порогам времени и числу страниц), отображает модальный диалог опроса на эстонском или русском языках и синхронизирует состояние с `localStorage`.
2. **Публичный API:** выдаёт одноразовые токены защиты и принимает ответы без создания сессий PHP.
3. **Хранилище:** MariaDB 11 хранит только обезличенные агрегируемые данные; Redis ограничивает частоту отправок (не более 3 ответов в час с источника).
4. **Административная панель:** доступна только уполномоченным сотрудникам с ролью `Peakasutaja`.
5. **Регламентная очистка:** фоновый CLI-скрипт `bin/purge.php` удаляет устаревшие записи по расписанию.

---

## 2. Состав репозитория

| Путь | Назначение |
|---|---|
| `public/tagasiside/widget.js` | Клиентский скрипт опроса (ET/RU), модальное окно, управление фокусом, отправка данных, синхронизация с `localStorage`. |
| `public/tagasiside/widget.css` | Стили виджета в дизайн-системе LIMIS (токены `--ee-*`), фокусные контуры, адаптивность. |
| `public/tagasiside/token.php` | Выдача одноразового HMAC-токена (`Cache-Control: no-store`). |
| `public/tagasiside/submit.php` | Приём и валидация отзыва, проверка honeypot, токена, rate limiting в Redis и запись в БД. |
| `public/admin/tagasiside.php` | Контроллер панели администратора, фильтры по периодам/языкам, экспорт в CSV. |
| `public/admin/tagasiside-dashboard.view.php` | Шаблон дашборда аналитики и таблицы отзывов. |
| `public/admin/tagasiside-login.view.php` | Экран автономного входа по паролю для локальной разработки (`auth_mode => 'standalone'`). |
| `public/admin/tagasiside-forbidden.view.php` | Экран HTTP 403 Forbidden при нехватке роли `Peakasutaja`. |
| `public/admin/tagasiside.css`, `tagasiside.js` | Стили административной панели и клиентские обработчики фильтрации. |
| `src/AdminAuth.php` | Модуль аутентификации: сквозная сессия LIMIS (`auth_mode => 'limis'`) и автономный режим. |
| `src/bootstrap.php` | Инициализация окружения, подключение к БД, Redis-клиент, извлечение IP с учётом прокси. |
| `src/RedisClient.php` | Легковесный сокетный RESP-клиент Redis для атомарного rate limiting без сторонних расширений. |
| `src/FeedbackValidator.php` | Валидатор и нормализатор полей входящего отзыва. |
| `src/i18n/admin.php` | Словари локализации административной панели (ET / RU). |
| `config/config.example.php` | Пример рабочей конфигурации модуля. |
| `migrations/001_install.sql` | Создание таблиц `limis_tagasiside` и `limis_tagasiside_nonce`. |
| `migrations/001_rollback.sql` | Скрипт отката и полного удаления таблиц модуля. |
| `bin/purge.php` | CLI-скрипт регламентной очистки данных (180 дней). |
| `bin/seed.php` | Генератор синтетических тестовых отзывов для локальной разработки. |
| `bin/clean-tests.php` | Скрипт очистки БД от тестовых и сгенерированных записей. |
| `bin/test.ps1` | Скрипт автоматизированного прогона всех unit- и integration-тестов. |
| `public/index.php`, `site.css` | Локальная тестовая страница электронных услуг в стиле LIMIS для отладки виджета. |

---

## 3. Системные требования

- **PHP:** версия 8.5+ с включёнными расширениями `pdo_mysql` и `mbstring`.
- **СУБД:** MariaDB 11+ (выделенная база данных с кодировкой `utf8mb4`).
- **Кэширование:** Redis 6+ или 7+ (порт 6379, поддержка команд `EVAL` и `AUTH`).
- **Сетевой протокол:** HTTPS на всех публичных и административных страницах.
- **Веб-сервер:** Apache 2.4+ с поддержкой `.htaccess` (или Nginx с аналогичными правилами маршрутизации).
- **Планировщик задач:** Windows Task Scheduler или Linux cron для вызова `bin/purge.php`.

---

## 4. Развёртывание бэкенда и базы данных

### 4.1. Размещение файлов вне публичного корня (Web Root)

Для исключения утечки служебных файлов размещайте проект так, чтобы корень веб-сервера указывал строго на каталог `public/`:

```text
/srv/limis-feedback/              <-- Вне DocumentRoot
  ├── config/
  │   └── config.php              <-- Конфиденциальный файл конфигурации
  ├── src/
  ├── migrations/
  ├── bin/
  └── public/                     <-- DocumentRoot веб-сервера
      ├── admin/
      ├── tagasiside/
      ├── index.php
      ├── site.css
      └── robots.txt
```

Папки `config/`, `src/`, `migrations/`, `bin/` и `tests/` категорически запрещено отдавать веб-сервером напрямую.

### 4.2. Создание базы данных и применение миграций

1. Создайте отдельную базу данных:
   ```sql
   CREATE DATABASE limis_feedback CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. Примените установочную миграцию:
   ```sh
   mariadb limis_feedback < migrations/001_install.sql
   ```
   Скрипт создаст две таблицы:
   - `limis_tagasiside` — записи отзывов жителей;
   - `limis_tagasiside_nonce` — реестр одноразовых токенов для исключения повторной отправки.

### 4.3. Настройка пользователя MariaDB (принцип Least Privilege)

Для работы приложения создайте отдельного пользователя базы данных без административных и DDL-привилегий:

```sql
-- Отзыв всех глобальных прав
REVOKE ALL PRIVILEGES ON limis_feedback.* FROM 'limis_feedback_app'@'localhost';

-- Выдача только необходимых прав на чтение, запись и консультативные блокировки
GRANT SELECT, INSERT, UPDATE, DELETE, LOCK TABLES ON limis_feedback.* TO 'limis_feedback_app'@'localhost';

FLUSH PRIVILEGES;
```

> [!IMPORTANT]
> Пользователю приложения не требуются и не должны выдаваться права `DROP`, `ALTER`, `CREATE` или доступ к другим базам данных сервера LIMIS.

### 4.4. Создание рабочего файла конфигурации

1. Скопируйте `config/config.example.php` в `config/config.php`:
   ```sh
   cp config/config.example.php config/config.php
   ```
2. Сгенерируйте криптографически стойкий секрет (не менее 32 случайных байт / 64 hex-символов):
   ```sh
   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
   ```
3. Заполните параметры в `config/config.php`:
   ```php
   return [
       'dsn'            => 'mysql:host=127.0.0.1;dbname=limis_feedback;charset=utf8mb4',
       'db_user'        => 'limis_feedback_app',
       'db_pass'        => 'ВашНадежныйПарольБД',
       'secret'         => 'СгенерированнаяHexСтрока64Символа',
       'auth_mode'      => 'limis',       // 'limis' для интеграции с сессией LIMIS, 'standalone' для тестов
       'admin_hash'     => '',            // Используется только в режиме standalone
       'trusted_proxies'=> ['127.0.0.1'], // IP-адреса балансировщиков и reverse proxy
       'redis_enabled'  => true,
       'redis_host'     => '127.0.0.1',
       'redis_port'     => 6379,
       'redis_pass'     => null,          // Пароль Redis при наличии
       'redis_timeout'  => 1.5,
   ];
   ```

Если конфигурационный файл размещается по нестандартному пути, задайте переменную окружения `LF_CONFIG` для пула PHP-FPM и заданий планировщика.

### 4.5. Подсистема Rate Limiting в Redis

Ограничение частоты отправок обслуживается через Redis (не более 3 успешно принятых ответов в час с одного источника).
- Ключи формируются как `limis:rate:v2:<hmac_ip>`, где IP-адрес маскируется с солью из `secret` и имеет TTL 3600 секунд.
- При отказе или недоступности службы Redis публичный endpoint `submit.php` завершает запрос с **HTTP 503 Service Unavailable** (fail-closed защита от флуда в аварийном режиме).

### 4.6. Настройки веб-сервера и PHP для промышленного контура

В конфигурации PHP (`php.ini` / пул PHP-FPM):
- `display_errors = Off` — исключает утечку системной информации и SQL-ошибок посетителям;
- `log_errors = On` — запись ошибок в закрытый системный журнал;
- `error_log = /var/log/php/limis_feedback_error.log` (вне каталога сайта).

---

## 5. Встраивание виджета на страницы LIMIS

### 5.1. Подключение стилей и скрипта

Для наилучшей производительности подключайте CSS в `<head>`, а JavaScript с атрибутом `defer` перед закрывающим тегом `</body>`:

```html
<!-- В блоке <head> страницы -->
<link rel="stylesheet" href="/tagasiside/widget.css">

<!-- В нижней части страницы перед </body> -->
<script
  defer
  src="/tagasiside/widget.js"
  data-lang="et"
  data-page="eservices"
  data-page-key="service-catalog"
  data-invite-after-seconds="45"
  data-invite-min-pages="2"></script>
```

Файлы `widget.js`, `widget.css`, `token.php` и `submit.php` должны размещаться в одном каталоге (`/tagasiside/`), так как скрипт автоматически вычисляет адреса API относительно своего собственного URL.

### 5.2. Параметры конфигурации виджета (HTML data-атрибуты)

| Атрибут | Значение по умолчанию | Описание |
|---|---|---|
| `data-lang` | `'et'` | Начальный язык интерфейса виджета (`'et'` или `'ru'`). |
| `data-page` | `''` | Системная категория раздела для сохранения в БД (`page_group`). Допустимы только категории из белого списка `bootstrap.php`. |
| `data-page-key` | `''` | Уникальный статический идентификатор страницы. Используется **исключительно локально в браузере** для подсчёта числа посещённых страниц и никогда не отправляется на сервер. |
| `data-invite-after-seconds`| `45` | Время активного пребывания на сайте (в секундах) до показа автоматического приглашения. |
| `data-invite-min-pages` | `2` | Минимальное число уникальных страниц (`data-page-key`), которое должен посетить пользователь перед показом приглашения. |
| `data-critical` | `0` | Флаг `1` отключает показ автоматического приглашения (используется на формах ввода данных, подаче заявлений, оплате). |

### 5.3. Дизайн-система и переменные оформления

Виджет использует официальные токены дизайн-системы LIMIS (`:root`), объявленные в [widget.css](file:///c:/Users/kraken/Downloads/limis-feedback/public/tagasiside/widget.css):

```css
:root {
    --ee-blue: #005aa3;       /* Основной синий цвет кнопок и ссылок */
    --ee-blue-dark: #003c75;  /* Тёмно-синий цвет активных состояний и hover */
    --ee-blue-soft: #eaf4ff;  /* Светло-синий фон второстепенных кнопок */
    --ee-ink: #1b1f24;        /* Основной цвет текста */
    --ee-muted: #5b6673;      /* Вспомогательный текст и подсказки */
    --ee-border: #d8e0e8;     /* Рамки полей и разделители */
    --ee-bg: #f5f7fa;         /* Фоновый цвет */
    --ee-white: #ffffff;      /* Белый фон карточки диалога */
    --ee-focus: #ffcc00;      /* Высококонтрастный цвет рамки фокуса */
}
```

- **Шапка LIMIS:** цвет фона `.site-header` / `.limis-header` составляет `#24578A` с нижней рамкой `1px solid #1d4b77`.
- **Типографика:** шрифты `'Montserrat'`, `'Lato'`, Arial, sans-serif.
- **Фокус клавиатуры:** интерактивные элементы выделяются контуром `outline: 3px solid var(--ee-focus); outline-offset: 3px;`.

### 5.4. Определение и переключение языков

Виджет автоматически согласует язык интерфейса со схемой LIMIS:
1. **Приоритет выбора языка:**
   - Параметр в URL адресной строки: `?lang=et` или `?lang=ru`.
   - Значение в локальном хранилище: `localStorage.getItem('language')`.
   - Атрибут тега скрипта: `data-lang="et"` / `data-lang="ru"`.
   - Атрибут документа: `<html lang="ru">`.
2. **Синхронизация:** при переключении языка виджет автоматически обновляет `localStorage.setItem('language', lang)`.

### 5.5. Программный JavaScript API (`window.LimisFeedback`)

Для одностраничных приложений (SPA) и динамических сценариев виджет экспортирует глобальный объект:

```javascript
// Динамическая смена языка интерфейса
window.LimisFeedback?.setLanguage('ru');

// Регистрация виртуального перехода на новую страницу (для подсчёта порога страниц)
window.LimisFeedback?.setPage('application-step-2');

// Управление подавлением приглашения во время ответственных операций
window.LimisFeedback?.setCritical(true);  // Начало заполнения заявления / оплаты
window.LimisFeedback?.setCritical(false); // Завершение операции / отмена
```

Также поддерживаются эквивалентные события DOM:
- `window.dispatchEvent(new CustomEvent('limis-feedback:lang', { detail: 'ru' }));`
- `window.dispatchEvent(new CustomEvent('limis-feedback:page', { detail: 'step-2' }));`
- `window.dispatchEvent(new CustomEvent('limis-feedback:critical', { detail: true }));`

### 5.6. Настройка Reverse Proxy и заголовков CSP

При настройке Content Security Policy (CSP) принимающего сайта добавьте директивы:
- `script-src 'self'` (хост размещения `widget.js`);
- `style-src 'self'` (хост размещения `widget.css`);
- `connect-src 'self'` (эндпоинты `token.php` и `submit.php`).

Рекомендуется размещать модуль под общим доменом сайта через reverse proxy:
```nginx
location /tagasiside/ {
    proxy_pass http://127.0.0.1:8080/tagasiside/;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

---

## 6. Интеграция панели администратора с сессиями LIMIS

### 6.1. Принцип работы и права доступа

Доступ к странице `/admin/tagasiside.php` и выгрузке CSV разрешён только пользователям с ролью **`Peakasutaja`**.

Проверка прав централизована в компоненте `src/AdminAuth.php`:
- При значении `'auth_mode' => 'limis'` в `config.php` модуль не отображает форму логина, а инспектирует текущую сессию PHP, запущенную родительской системой LIMIS.
- Проверяются сессионные переменные:
  - `$_SESSION['active_role']` (штатный ключ LIMIS);
  - `$_SESSION['role']`, `$_SESSION['user']['role']`, `$_SESSION['user_role']`.
- Сопоставление роли выполняется регистронезависимо (`peakasutaja`, `Peakasutaja`), а также поддерживает префиксы (например, `peakasutaja:100`).

### 6.2. Поведение при отказе в доступе (HTTP 403 Forbidden)

Если пользователь авторизован в LIMIS под другой ролью (например, обычный служащий `teenistuja` или гражданин `kodanik_er`):
- Сервер отдаёт статус **HTTP 403 Forbidden**;
- Отображается информационный экран `tagasiside-forbidden.view.php` с сообщением о нехватке роли `Peakasutaja` и текущей активной ролью;
- Аналитические данные и форма экспорта CSV полностью скрыты.

### 6.3. Настройка междоменных cookie сессии

Если панель администратора размещается на отдельном поддомене (например, `tagasiside.narva.ee` при основном портале `limis.narva.ee`), в `php.ini` или перед стартом сессии LIMIS задайте общую область видимости:

```ini
session.cookie_domain = ".narva.ee"
session.cookie_path = "/"
session.cookie_secure = 1
session.cookie_httponly = 1
session.cookie_samesite = "Lax"
```

---

## 7. Регламентная очистка устаревших данных (`bin/purge.php`)

CLI-скрипт `bin/purge.php` предназначен для регулярного автоматического удаления:
- ответов старше 180 дней из таблицы `limis_tagasiside`;
- истёкших технических одноразовых токенов из таблицы `limis_tagasiside_nonce`.

Скрипт использует консультативную блокировку MariaDB (`GET_LOCK`), предотвращая одновременный запуск нескольких копий задачи. При успешном завершении возвращает код `$LASTEXITCODE == 0`.

### 7.1. Настройка в Windows Task Scheduler

1. Откройте **Task Scheduler → Create Task**.
2. **General:** укажите запуск от имени выделенной сервисной учётной записи (независимо от входа в систему).
3. **Triggers:** создайте ежедневное расписание (например, в 03:00 ночи).
4. **Actions:**
   - **Program/script:** `C:\Program Files\PHP\php.exe`
   - **Add arguments:** `C:\srv\limis-feedback\bin\purge.php`
   - **Start in:** `C:\srv\limis-feedback`
5. Для записи вывода в журнал настройте перенаправление в Arguments через `cmd.exe`:
   ```text
   /d /s /c ""C:\Program Files\PHP\php.exe" "C:\srv\limis-feedback\bin\purge.php" >> "C:\ProgramData\LimisFeedback\logs\purge.log" 2>&1"
   ```

### 7.2. Настройка в Linux cron

Добавьте задание в системный crontab пользователя приложения:

```cron
0 3 * * * /usr/bin/php /srv/limis-feedback/bin/purge.php >> /var/log/limis-feedback-purge.log 2>&1
```

---

## 8. Проверка работоспособности и тестирование

### 8.1. Автоматический запуск тестов (`bin/test.ps1`)

Под Windows запуск полного тестового набора выполняется одной командой:

```powershell
powershell -ExecutionPolicy Bypass -File bin/test.ps1
```

Скрипт автоматически выполняет:
1. Линтинг и проверку синтаксиса всех PHP-файлов (`php -l`).
2. Проверку доступности Redis.
3. 22 unit-теста виджета и Redis-клиента (`tests/widget.test.mjs`, `tests/redis.test.mjs`).
4. Подъём изолированного PHP-сервера и прогон 19 интеграционных тестов API и безопасности админки (`tests/api.test.mjs`, `tests/admin.test.mjs`).

### 8.2. Управление записью в тестовую базу (`LIMIS_TEST_ALLOW_WRITE`)

По умолчанию тесты не вносят изменений в MariaDB. Для тестирования сквозной вставки в изолированную тестовую базу:

```powershell
$env:LIMIS_TEST_ALLOW_WRITE = "1"
powershell -ExecutionPolicy Bypass -File bin/test.ps1
Remove-Item Env:\LIMIS_TEST_ALLOW_WRITE
```

Для ручной очистки тестовых данных в любой момент:
```powershell
powershell -ExecutionPolicy Bypass -File bin/test.ps1 -Clean
# или напрямую через PHP:
php bin/clean-tests.php
```

### 8.3. Пошаговый чек-лист технической приёмки

1. Запрос `GET /tagasiside/token.php` возвращает HTTP 200, JSON `{token: "..."}` и заголовок `Cache-Control: no-store`.
2. Запрос `GET /tagasiside/submit.php` отклоняется со статусом `405 Method Not Allowed`.
3. Отправка корректного отзыва через виджет возвращает `200 OK` и сохраняет запись в MariaDB.
4. Превышение лимита в 3 ответа за час с одного IP блокируется со статусом `429 Too Many Requests`.
5. При остановленной службе Redis эндпоинт отправки возвращает `503 Service Unavailable`.
6. Обращение к `/admin/tagasiside.php` без роли `Peakasutaja` блокируется с `HTTP 403 Forbidden`.
7. Запуск `php bin/purge.php` завершается с кодом `0` и выводит количество очищенных строк.

---

## 9. Процедура полного отката (Rollback)

В случае необходимости полного удаления таблиц модуля:

```sh
mariadb limis_feedback < migrations/001_rollback.sql
```

> [!CAUTION]
> Скрипт `001_rollback.sql` безвозвратно удаляет таблицы `limis_tagasiside` и `limis_tagasiside_nonce`. Перед запуском убедитесь в наличии резервной копии.
