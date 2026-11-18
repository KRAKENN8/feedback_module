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
    $loginError = isset($err);
    require __DIR__ . '/tagasiside-login.view.php';
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
        $o .= '<div class="bar-row"><span class="bar-label">' . $h($k) . '</span>'
            . '<span class="bar-track"><i data-percent="' . $p . '" aria-hidden="true"></i></span>'
            . '<b class="bar-value">' . (int)$n . ' (' . $p . '%)</b></div>';
    }
    return $o ?: '<p class="empty-state">No responses for this period.</p>';
};

$comments = $q("SELECT created_at,lang,ease_rating,comment FROM limis_tagasiside WHERE $w AND comment IS NOT NULL ORDER BY id DESC LIMIT 100", $a)->fetchAll(PDO::FETCH_ASSOC);
require __DIR__ . '/tagasiside-dashboard.view.php';
