<?php
/**
 * Шаблон экрана входа администратора (автономный режим / standalone).
 *
 * @var string $ui          Язык интерфейса ('et' или 'ru')
 * @var array  $T           Словарь локализации
 * @var string $csrf        CSRF-токен сессии
 * @var bool   $loginError  Флаг ошибки авторизации
 * @var callable $h         Функция экранирования HTML
 */
?>
<!doctype html>
<html lang="<?= $h($ui) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#183F68">
    <title><?= $h($T['login_title']) ?></title>
    <link rel="stylesheet" href="/admin/tagasiside.css">
    <script src="/admin/tagasiside.js" defer></script>
</head>
<body class="login-page">
    <main class="login-shell">
        <section class="login-card" aria-labelledby="login-title">
            <div class="login-card-top">
                <p class="eyebrow"><?= $h($T['login_eyebrow']) ?></p>
                <nav class="language-switch login-language" aria-label="<?= $ui === 'et' ? 'Keelevalik' : 'Выбор языка' ?>">
                    <a href="?ui=et" lang="et" <?= $ui === 'et' ? 'aria-current="page"' : '' ?>>ET</a>
                    <a href="?ui=ru" lang="ru" <?= $ui === 'ru' ? 'aria-current="page"' : '' ?>>RU</a>
                </nav>
            </div>
            <h1 id="login-title"><?= $h($T['login_heading']) ?></h1>
            <p class="login-intro"><?= $h($T['login_intro']) ?></p>

            <form method="post" class="login-form">
                <?php if ($loginError): ?>
                    <p class="alert" id="login-error" role="alert">
                        <svg class="alert-icon" width="18" height="18" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" clip-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9 9a1 1 0 012 0v4a1 1 0 11-2 0V9zm1-4a1.25 1.25 0 100 2.5 1.25 1.25 0 000-2.5z"/></svg>
                        <span><?= $h($T['login_error']) ?></span>
                    </p>
                <?php endif; ?>
                <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                <label for="admin-password"><?= $h($T['password']) ?></label>
                <div class="password-field">
                    <input
                        id="admin-password"
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        autofocus
                        <?= $loginError ? 'aria-describedby="login-error" aria-invalid="true"' : '' ?>
                        required
                    >
                    <button
                        class="password-toggle"
                        type="button"
                        aria-controls="admin-password"
                        aria-pressed="false"
                        aria-label="<?= $h($T['show_password']) ?>"
                        title="<?= $h($T['show_password']) ?>"
                        data-show-label="<?= $h($T['show_password']) ?>"
                        data-hide-label="<?= $h($T['hide_password']) ?>"
                    >
                        <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        <svg class="eye-off is-hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                            <path d="m3 3 18 18"></path>
                        </svg>
                    </button>
                </div>
                <button class="button button-primary login-submit" type="submit">
                    <?= $h($T['sign_in']) ?> <span aria-hidden="true">→</span>
                </button>
            </form>
            <p class="login-note"><?= $h($T['authorized_only']) ?></p>
        </section>

        <p class="login-footer"><?= $h($T['product_footer']) ?></p>
    </main>
</body>
</html>
