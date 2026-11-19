<?php
// ДЕМО-ОБОЛОЧКА (имитация, не LIMIS). ?p=home|eservices|procurement|property|apply&lang=et|ru

$L = ($_GET['lang'] ?? 'et') === 'ru' ? 'ru' : 'et';
$pages = ['home', 'eservices', 'procurement', 'property', 'apply'];
$p = in_array($_GET['p'] ?? '', $pages, true) ? $_GET['p'] : 'home';
$crit = $p === 'apply';
$pg = $crit ? 'eservices' : $p;
$inviteAfterSeconds = 45;
$inviteMinPages = 2;
?>
<!doctype html>
<html lang="<?= $L ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LIMIS demo shell</title>
    <link rel="stylesheet" href="/tagasiside/widget.css">
</head>
<body style="font: 16px system-ui; max-width: 800px; margin: 1rem auto; padding: 0 1rem;">
    <p>
        <b>DEMO SHELL — not LIMIS.</b> Lang:
        <a href="?p=<?= $p ?>&lang=et">ET</a> |
        <a href="?p=<?= $p ?>&lang=ru">RU</a>
    </p>

    <nav>
        <?php foreach ($pages as $x) echo '<a href="?p=' . $x . '&lang=' . $L . '">' . $x . '</a> | '; ?>
    </nav>

    <h1><?= htmlspecialchars($p) ?></h1>

    <?php if ($crit): ?>
        <p>[Test state: important operation in progress — auto-invite suppressed]</p>
        <form>
            <input aria-label="demo field">
            <button type="button">Submit</button>
        </form>
    <?php else: ?>
        <p>Demo content.</p>
    <?php endif; ?>

    <script src="/tagasiside/widget.js?v=<?= filemtime(__DIR__ . '/tagasiside/widget.js') ?>" data-lang="<?= $L ?>" data-page="<?= $pg ?>" data-critical="<?= $crit ? 1 : 0 ?>" data-invite-after-seconds="<?= $inviteAfterSeconds ?>" data-invite-min-pages="<?= $inviteMinPages ?>"></script>
</body>
</html>
