import assert from 'node:assert/strict';
import test from 'node:test';

const baseUrl = (process.env.LIMIS_TEST_BASE_URL || 'http://localhost:8080').replace(/\/+$/, '');
const adminUrl = `${baseUrl}/admin/tagasiside.php`;

function extractCsrfAndCookie(html, headers) {
    const match = html.match(/name="csrf"\s+value="([^"]+)"/i) || html.match(/value="([^"]+)"\s+name="csrf"/i);
    const csrf = match ? match[1] : '';
    const cookieHeader = headers.get('set-cookie') || '';
    const sessionMatch = cookieHeader.match(/PHPSESSID=([^;]+)/);
    const cookie = sessionMatch ? `PHPSESSID=${sessionMatch[1]}` : '';
    return { csrf, cookie };
}

// Проверка правила санитизации формул из public/admin/tagasiside.php:
// $safe = fn($v) => ($v !== null && preg_match('/^[=+\-@\t\r]/', (string)$v)) ? "'" . $v : $v;
function sanitizeCsvFormula(value) {
    if (value !== null && /^[=+\-@\t\r]/.test(String(value))) {
        return "'" + value;
    }
    return value;
}

test('CSV formula injection sanitizer escapes dangerous leading characters', () => {
    assert.equal(sanitizeCsvFormula('=SUM(1+2)'), "'=SUM(1+2)");
    assert.equal(sanitizeCsvFormula('+12345'), "'+12345");
    assert.equal(sanitizeCsvFormula('-5+2'), "'-5+2");
    assert.equal(sanitizeCsvFormula('@SUM'), "'@SUM");
    assert.equal(sanitizeCsvFormula('\tcmd'), "'\tcmd");
    assert.equal(sanitizeCsvFormula('\rcmd'), "'\rcmd");

    // Обычные и безопасные строки не должны искажаться
    assert.equal(sanitizeCsvFormula('Hea teenus'), 'Hea teenus');
    assert.equal(sanitizeCsvFormula('Отличный сервис!'), 'Отличный сервис!');
    assert.equal(sanitizeCsvFormula('12345'), '12345');
    assert.equal(sanitizeCsvFormula(null), null);
});

test('unauthenticated request to CSV export is rejected with 403 Forbidden', async () => {
    const response = await fetch(`${adminUrl}?csv=1`);

    assert.equal(response.status, 403);
    const body = await response.text();
    assert.equal(body.trim(), 'Forbidden');
});

test('unauthenticated access to admin dashboard shows the login form and hides metrics', async () => {
    const response = await fetch(adminUrl);

    assert.equal(response.status, 200);
    assert.match(response.headers.get('content-type') || '', /text\/html/i);

    const html = await response.text();
    assert.match(html, /<form[^>]+method=["']post["']/i);
    assert.match(html, /name=["']password["']/i);
    assert.match(html, /name=["']csrf["']/i);

    // Панель статистики и комментарии должны быть скрыты от неавторизованного пользователя
    assert.doesNotMatch(html, /class=["']summary-grid["']/i);
    assert.doesNotMatch(html, /class=["']insights-grid["']/i);
    assert.doesNotMatch(html, /class=["']comment-list["']/i);
});

test('admin login page supports language switching via ui query parameter', async () => {
    const etResponse = await fetch(`${adminUrl}?ui=et`);
    const etHtml = await etResponse.text();
    assert.match(etHtml, /Tere tulemast tagasi/i);
    assert.match(etHtml, /Logi sisse/i);

    const ruResponse = await fetch(`${adminUrl}?ui=ru`);
    const ruHtml = await ruResponse.text();
    assert.match(ruHtml, /С возвращением/i);
    assert.match(ruHtml, /Войти/i);
});

test('login attempt without CSRF token is rejected', async () => {
    const response = await fetch(adminUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'password=wrong-password'
    });

    assert.equal(response.status, 200);
    const html = await response.text();
    // Сессия не должна выдаваться, отображается форма логина
    assert.match(html, /name=["']password["']/i);
    assert.doesNotMatch(html, /class=["']summary-grid["']/i);
});

test('login attempt with invalid password displays an error notification', async () => {
    const initial = await fetch(`${adminUrl}?ui=ru`);
    const initialHtml = await initial.text();
    const { csrf, cookie } = extractCsrfAndCookie(initialHtml, initial.headers);

    assert.ok(csrf, 'CSRF token should be present in login form');

    const response = await fetch(adminUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            ...(cookie ? { Cookie: cookie } : {})
        },
        body: new URLSearchParams({
            csrf,
            password: 'completely-wrong-password-12345'
        }).toString()
    });

    assert.equal(response.status, 200);
    const html = await response.text();
    assert.match(html, /Неверный пароль|Parool ei sobinud/i);
    assert.doesNotMatch(html, /class=["']summary-grid["']/i);
});
