<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin</title>
    <link rel="stylesheet" href="/admin/tagasiside.css">
</head>
<body>
    <form method="post" class="login-form">
        <h1>Demo admin</h1>
        <?php if ($loginError): ?>
            <p role="alert">Invalid</p>
        <?php endif; ?>
        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
        <label>Password <input type="password" name="password" required></label>
        <button type="submit">OK</button>
    </form>
</body>
</html>
