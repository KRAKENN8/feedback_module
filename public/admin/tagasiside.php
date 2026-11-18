<?php

declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$csrf = $_SESSION['csrf'] ??= bin2hex(random_bytes(16));

if (isset($_POST['logout']) && hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
    session_destroy();
    header('Location: tagasiside.php');
    exit;
}

// DEMO-авторизация, НЕ LIMIS
if (isset($_POST['password'])) {
    if (
        hash_equals($csrf, (string)($_POST['csrf'] ?? ''))
        && cfg()['admin_hash']
        && password_verify((string)$_POST['password'], cfg()['admin_hash'])
    ) {
        session_regenerate_id(true);
        $_SESSION['role'] = 'Peakasutaja';
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
        header('Location: tagasiside.php');
        exit;
    }
    $err = true;
}

// проверка прав на сервере в этом же endpoint
if (($_SESSION['role'] ?? '') !== 'Peakasutaja') {
    if (isset($_GET['csv'])) {
        http_response_code(403);
        exit('Forbidden');
    }
    echo "<!doctype html>\n"
        . "<meta charset=utf-8>\n"
        . "<title>Admin</title>\n"
        . "<form method=post style='max-width:320px;margin:4rem auto;font:16px sans-serif'>\n"
        . "  <h1>Demo admin</h1>" . (isset($err) ? '<p role=alert>Invalid</p>' : '') . "\n"
        . "  <input type=hidden name=csrf value='{$h($csrf)}'>\n"
        . "  <label>Password <input type=password name=password required></label> <button>OK</button>\n"
        . "</form>";
    exit;
}

$days = (int)($_GET['days'] ?? 30);
if (!in_array($days, [7, 30, 90, 180], true)) {
    $days = 30;
}

$lang = in_array($_GET['lang'] ?? '', ['et', 'ru'], true) ? $_GET['lang'] : '';
$w = 'created_at>=DATE_SUB(NOW(),INTERVAL ' . $days . ' DAY)' . ($lang ? ' AND lang=?' : '');
$a = $lang ? [$lang] : [];

$q = function (string $sql, array $p = []) {
    $s = db()->prepare($sql);
    $s->execute($p);
    return $s;
};

if (isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=tagasiside.csv');

    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF");

    $safe = fn($v) => ($v !== null && preg_match('/^[=+\-@\t\r]/', (string)$v)) ? "'" . $v : $v;

    fputcsv($o, ['id', 'created_at', 'lang', 'purpose', 'completed', 'ease_rating', 'difficulty', 'comment', 'page_group'], ';', '"', '');
    foreach ($q("SELECT * FROM limis_tagasiside WHERE $w ORDER BY id DESC", $a) as $r) {
        fputcsv($o, array_map($safe, $r), ';', '"', '');
    }
    exit;
}

$tot = (int)$q("SELECT COUNT(*) FROM limis_tagasiside WHERE $w", $a)->fetchColumn();
$avg = $q("SELECT ROUND(AVG(ease_rating),2) FROM limis_tagasiside WHERE $w", $a)->fetchColumn();

$dist = fn($col) => $q("SELECT $col k,COUNT(*) n FROM limis_tagasiside WHERE $w AND $col IS NOT NULL GROUP BY $col ORDER BY n DESC", $a)->fetchAll(PDO::FETCH_KEY_PAIR);

$bars = function (array $d, int $t) use ($h) {
    $o = '';
    foreach ($d as $k => $n) {
        $p = $t ? round($n * 100 / $t) : 0;
        $o .= "<div class=row><span>{$h($k)}</span><i style='width:{$p}%'></i><b>$n ({$p}%)</b></div>";
    }
    return $o ?: '<p>—</p>';
};

$comments = $q("SELECT created_at,lang,ease_rating,comment FROM limis_tagasiside WHERE $w AND comment IS NOT NULL ORDER BY id DESC LIMIT 100", $a)->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang=en>
<head>
    <meta charset=utf-8>
    <meta name=viewport content="width=device-width,initial-scale=1">
    <title>Tagasiside stats (demo)</title>
    <style>
        body {
            font: 16px/1.5 system-ui;
            max-width: 900px;
            margin: 1rem auto;
            padding: 0 1rem;
            color: #1a1a1a;
        }
        .row {
            display: flex;
            gap: .5rem;
            align-items: center;
            margin: .2rem 0;
        }
        .row span {
            width: 9rem;
        }
        .row i {
            background: #1a56a0;
            height: 14px;
            display: block;
            min-width: 2px;
        }
        li {
            margin: .4rem 0;
        }
    </style>
</head>
<body>
    <h1>LIMIS feedback — DEMO admin</h1>

    <form method=get>
        Period
        <select name=days>
            <?php foreach ([7, 30, 90, 180] as $d) echo "<option " . ($d == $days ? 'selected' : '') . ">$d</option>"; ?>
        </select>
        Lang
        <select name=lang>
            <option value="">all</option>
            <?php foreach (['et', 'ru'] as $l) echo "<option " . ($l === $lang ? 'selected' : '') . ">$l</option>"; ?>
        </select>
        <button>Apply</button>
        <a href="?csv=1&days=<?= $days ?>&lang=<?= $h($lang) ?>">CSV</a>
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
        <?php foreach ($comments as $c) echo '<li>' . $h($c['created_at']) . ' [' . $h($c['lang']) . '] ' . (int)$c['ease_rating'] . '/5 — ' . $h($c['comment']) . '</li>'; ?>
    </ul>

    <form method=post>
        <input type=hidden name=csrf value="<?= $h($csrf) ?>">
        <button name=logout value=1>Logout</button>
    </form>
</body>
</html>
