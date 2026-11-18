<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#123c70">
    <title>Admin sign in · LIMIS feedback</title>
    <link rel="stylesheet" href="/admin/tagasiside.css">
</head>
<body class="login-page">
    <main class="login-shell">
        <a class="brand" href="/" aria-label="LIMIS home">
            <span class="brand-mark" aria-hidden="true">L</span>
            <span class="brand-name">LIMIS <span>· NARVA</span></span>
        </a>

        <section class="login-card" aria-labelledby="login-title">
            <p class="eyebrow">FEEDBACK ADMINISTRATION</p>
            <h1 id="login-title">Welcome back</h1>
            <p class="login-intro">Sign in to review feedback and service ratings.</p>

            <form method="post" class="login-form">
                <?php if ($loginError): ?>
                    <p class="alert" id="login-error" role="alert">That password did not match. Please try again.</p>
                <?php endif; ?>
                <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                <label for="admin-password">Password</label>
                <input
                    id="admin-password"
                    type="password"
                    name="password"
                    autocomplete="current-password"
                    <?= $loginError ? 'aria-describedby="login-error" aria-invalid="true"' : '' ?>
                    required
                >
                <button class="button button-primary login-submit" type="submit">
                    Sign in <span aria-hidden="true">→</span>
                </button>
            </form>
            <p class="login-note">Authorized administrators only</p>
        </section>

        <p class="login-footer">LIMIS feedback · Demonstration environment</p>
    </main>
</body>
</html>
