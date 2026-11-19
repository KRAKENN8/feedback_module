<?php

declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

$ui = $_GET['ui'] ?? ($_SESSION['ui'] ?? 'et');
$ui = in_array($ui, ['et', 'ru'], true) ? $ui : 'et';
$_SESSION['ui'] = $ui;

$translations = [
    'et' => [
        'login_title' => 'Tagasiside haldus · LIMIS',
        'home_label' => 'LIMIS avaleht',
        'login_eyebrow' => 'TAGASISIDE HALDUS',
        'login_heading' => 'Tere tulemast tagasi',
        'login_intro' => 'Logi sisse tagasiside ja teenuste hinnangute vaatamiseks.',
        'password' => 'Parool',
        'show_password' => 'Näita',
        'hide_password' => 'Peida',
        'login_error' => 'Parool ei sobinud. Proovi uuesti.',
        'sign_in' => 'Logi sisse',
        'authorized_only' => 'Ainult volitatud kasutajatele',
        'demo_footer' => 'LIMIS tagasiside · Demokeskkond',
        'admin_label' => 'Tagasiside haldus',
        'sign_out' => 'Logi välja',
        'insights_eyebrow' => 'ÜLEVAADE TEENUSEST',
        'dashboard_title' => 'Tagasiside ülevaade',
        'dashboard_intro' => 'Vaata külastajate arvamusi ja leia võimalusi teenuse parandamiseks.',
        'filters' => 'Tagasiside filtrid',
        'period' => 'Ajavahemik',
        'last_days' => 'Viimased %d päeva',
        'response_language' => 'Vastuse keel',
        'all_languages' => 'Kõik keeled',
        'estonian' => 'Eesti',
        'russian' => 'Vene',
        'apply_filters' => 'Rakenda filtrid',
        'export_csv' => 'Ekspordi CSV',
        'clear_filters' => 'Tühjenda filtrid',
        'summary' => 'Kokkuvõte',
        'responses' => 'Vastuseid',
        'selected_period' => 'valitud ajavahemikul',
        'average_rating' => 'Kasutusmugavuse keskmine hinnang',
        'visitor_rating' => 'külastajate hinnang',
        'outcome' => 'TULEMUS',
        'completion' => 'Ülesande täitmine',
        'usability' => 'KASUTUSMUGAVUS',
        'ease_ratings' => 'Kasutusmugavuse hinnangud',
        'scale' => 'Skaala 1–5',
        'friction' => 'TAKISTUSED',
        'difficulties' => 'Tekkinud raskused',
        'visitor_needs' => 'KÜLASTAJATE VAJADUSED',
        'visit_purpose' => 'Külastuse eesmärk',
        'in_their_words' => 'KÜLASTAJATE SÕNADEGA',
        'comments' => 'Külastajate kommentaarid',
        'latest_comments' => 'Viimased %d kommentaari',
        'no_comments' => 'Valitud ajavahemikul kommentaare pole. Proovi pikemat ajavahemikku.',
        'no_responses' => 'Valitud ajavahemikul vastuseid pole.',
        'purpose_values' => [
            'eservices' => 'E-taotlused',
            'procurement' => 'Hanked ja enampakkumised',
            'property' => 'Linnavara',
            'account' => 'Isiklik konto',
            'appointment' => 'Vastuvõtule registreerimine',
            'other' => 'Muu',
        ],
        'completed_values' => [
            'yes' => 'Jah',
            'partial' => 'Osaliselt',
            'no' => 'Ei',
            'none' => 'Konkreetne eesmärk puudus',
        ],
        'difficulty_values' => [
            'search' => 'Vajaliku teabe otsimine',
            'application' => 'Taotluse täitmine',
            'ui' => 'Arusaamatu liides',
            'mobile' => 'Sait mobiilseadmes',
            'speed' => 'Saidi kiirus',
            'none' => 'Raskusi ei olnud',
            'other' => 'Muu',
        ],
        'ease_values' => [
            '1' => 'Väga keeruline',
            '2' => 'Keeruline',
            '3' => 'Rahuldav',
            '4' => 'Lihtne',
            '5' => 'Väga lihtne',
        ],
        'ease_suffix' => 'mugavuse hinnang',
    ],
    'ru' => [
        'login_title' => 'Управление отзывами · LIMIS',
        'home_label' => 'Главная LIMIS',
        'login_eyebrow' => 'УПРАВЛЕНИЕ ОТЗЫВАМИ',
        'login_heading' => 'С возвращением',
        'login_intro' => 'Войдите, чтобы просматривать отзывы и оценки сервиса.',
        'password' => 'Пароль',
        'show_password' => 'Показать',
        'hide_password' => 'Скрыть',
        'login_error' => 'Неверный пароль. Попробуйте ещё раз.',
        'sign_in' => 'Войти',
        'authorized_only' => 'Только для авторизованных пользователей',
        'demo_footer' => 'Отзывы LIMIS · Демонстрационная среда',
        'admin_label' => 'Управление отзывами',
        'sign_out' => 'Выйти',
        'insights_eyebrow' => 'АНАЛИТИКА СЕРВИСА',
        'dashboard_title' => 'Обзор отзывов',
        'dashboard_intro' => 'Узнайте мнение посетителей и найдите возможности улучшить сервис.',
        'filters' => 'Фильтры отзывов',
        'period' => 'Период',
        'last_days' => 'Последние %d дн.',
        'response_language' => 'Язык отзыва',
        'all_languages' => 'Все языки',
        'estonian' => 'Эстонский',
        'russian' => 'Русский',
        'apply_filters' => 'Применить фильтры',
        'export_csv' => 'Экспорт CSV',
        'clear_filters' => 'Сбросить фильтры',
        'summary' => 'Сводка',
        'responses' => 'Ответов',
        'selected_period' => 'за выбранный период',
        'average_rating' => 'Средняя оценка удобства',
        'visitor_rating' => 'оценка посетителей',
        'outcome' => 'РЕЗУЛЬТАТ',
        'completion' => 'Выполнение задачи',
        'usability' => 'УДОБСТВО',
        'ease_ratings' => 'Оценки удобства',
        'scale' => 'Шкала 1–5',
        'friction' => 'СЛОЖНОСТИ',
        'difficulties' => 'Возникшие затруднения',
        'visitor_needs' => 'ПОТРЕБНОСТИ ПОСЕТИТЕЛЕЙ',
        'visit_purpose' => 'Цель визита',
        'in_their_words' => 'МНЕНИЯ ПОСЕТИТЕЛЕЙ',
        'comments' => 'Комментарии посетителей',
        'latest_comments' => 'Последние комментарии: %d',
        'no_comments' => 'За выбранный период комментариев нет. Попробуйте выбрать более длительный период.',
        'no_responses' => 'За выбранный период ответов нет.',
        'purpose_values' => [
            'eservices' => 'Электронные заявления',
            'procurement' => 'Закупки и аукционы',
            'property' => 'Городское имущество',
            'account' => 'Личный кабинет',
            'appointment' => 'Запись на приём',
            'other' => 'Другое',
        ],
        'completed_values' => [
            'yes' => 'Да',
            'partial' => 'Частично',
            'no' => 'Нет',
            'none' => 'Не было конкретной цели',
        ],
        'difficulty_values' => [
            'search' => 'Поиск нужной информации',
            'application' => 'Заполнение заявления',
            'ui' => 'Непонятный интерфейс',
            'mobile' => 'Сайт на мобильном устройстве',
            'speed' => 'Скорость работы сайта',
            'none' => 'Затруднений не было',
            'other' => 'Другое',
        ],
        'ease_values' => [
            '1' => 'Очень неудобно',
            '2' => 'Неудобно',
            '3' => 'Удовлетворительно',
            '4' => 'Удобно',
            '5' => 'Очень удобно',
        ],
        'ease_suffix' => 'оценка удобства',
    ],
];
$T = $translations[$ui];

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$csrf = $_SESSION['csrf'] ??= bin2hex(random_bytes(16));

if (isset($_POST['logout']) && hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
    $logoutUi = $ui;
    session_destroy();
    header('Location: tagasiside.php?ui=' . $logoutUi);
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

$bars = function (array $d, int $t, string $type) use ($h, $T) {
    $o = '';
    foreach ($d as $k => $n) {
        $p = $t ? round($n * 100 / $t) : 0;
        $label = $T[$type . '_values'][$k] ?? $k;
        $o .= '<div class="bar-row"><span class="bar-label">' . $h($label) . '</span>'
            . '<span class="bar-track"><i data-percent="' . $p . '" aria-hidden="true"></i></span>'
            . '<b class="bar-value">' . (int)$n . ' (' . $p . '%)</b></div>';
    }
    return $o ?: '<p class="empty-state">' . $h($T['no_responses']) . '</p>';
};

$comments = $q("SELECT created_at,lang,ease_rating,comment FROM limis_tagasiside WHERE $w AND comment IS NOT NULL ORDER BY id DESC LIMIT 100", $a)->fetchAll(PDO::FETCH_ASSOC);
require __DIR__ . '/tagasiside-dashboard.view.php';
