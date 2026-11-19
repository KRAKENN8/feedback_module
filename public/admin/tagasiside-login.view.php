<!doctype html>
<html lang="<?= $h($ui) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#123c70">
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
                    <p class="alert" id="login-error" role="alert"><?= $h($T['login_error']) ?></p>
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

        <p class="login-footer"><?= $h($T['demo_footer']) ?></p>
    </main>
</body>
</html>
