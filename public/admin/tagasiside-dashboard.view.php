<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Tagasiside stats (demo)</title>
    <link rel="stylesheet" href="/admin/tagasiside.css">
    <script src="/admin/tagasiside.js" defer></script>
</head>
<body>
    <h1>LIMIS feedback — DEMO admin</h1>

    <form method="get" id="tagasiside-filter">
        Period
        <select name="days">
            <?php foreach ([7, 30, 90, 180] as $d): ?>
                <option value="<?= $d ?>" <?= $d === $days ? 'selected' : '' ?>><?= $d ?></option>
            <?php endforeach; ?>
        </select>
        Lang
        <select name="lang">
            <option value="">all</option>
            <?php foreach (['et', 'ru'] as $l): ?>
                <option value="<?= $h($l) ?>" <?= $l === $lang ? 'selected' : '' ?>><?= $h($l) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Apply</button>
        <a href="?csv=1&amp;days=<?= $days ?>&amp;lang=<?= $h($lang) ?>">CSV</a>
    </form>

    <p>Total: <b><?= $tot ?></b> · Avg ease: <b><?= $h($avg ?? '—') ?></b></p>

    <h2>Result</h2>
    <?= $bars($dist('completed'), $tot) ?>

    <h2>Ratings 1–5</h2>
    <?= $bars($dist('ease_rating'), $tot) ?>

    <h2>Difficulties</h2>
    <?= $bars($dist('difficulty'), $tot) ?>

    <h2>Purpose</h2>
    <?= $bars($dist('purpose'), $tot) ?>

    <h2>Comments</h2>
    <ul>
        <?php foreach ($comments as $c): ?>
            <li><?= $h($c['created_at']) ?> [<?= $h($c['lang']) ?>] <?= (int)$c['ease_rating'] ?>/5 — <?= $h($c['comment']) ?></li>
        <?php endforeach; ?>
    </ul>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
        <button name="logout" value="1">Logout</button>
    </form>
</body>
</html>
