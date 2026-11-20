<?php

declare(strict_types=1);

$lang = ($_GET['lang'] ?? 'et') === 'ru' ? 'ru' : 'et';
$pages = ['home', 'eservices', 'procurement', 'property', 'account', 'appointment', 'apply'];
$page = in_array($_GET['p'] ?? '', $pages, true) ? $_GET['p'] : 'home';
$critical = $page === 'apply';
$pageGroup = $critical ? 'eservices' : $page;
$inviteAfterSeconds = 45;
$inviteMinPages = 2;

$copy = [
    'et' => [
        'title' => 'Narva linna teenused',
        'brand' => 'NARVA LINN',
        'eyebrow' => 'LINNA DIGITEENUSED',
        'description' => 'Leia vajalik teave ja kasuta Narva linna digiteenuseid ühest kohast.',
        'language' => 'Keelevalik',
        'nav' => [
            'home' => 'Avaleht',
            'eservices' => 'E-teenused',
            'procurement' => 'Hanked',
            'property' => 'Linnavara',
            'account' => 'Isiklik konto',
            'appointment' => 'Vastuvõtule registreerimine',
            'apply' => 'Esita taotlus',
        ],
        'headings' => [
            'home' => 'Narva linna teenused',
            'eservices' => 'E-teenused',
            'procurement' => 'Hanked ja enampakkumised',
            'property' => 'Linnavara',
            'account' => 'Isiklik konto',
            'appointment' => 'Vastuvõtule registreerimine',
            'apply' => 'Taotluse esitamine',
        ],
        'content' => 'Tutvu teenusega ning leia juhised ja teave, mida vajad järgmise sammu tegemiseks.',
    ],
    'ru' => [
        'title' => 'Услуги города Нарвы',
        'brand' => 'ГОРОД НАРВА',
        'eyebrow' => 'ГОРОДСКИЕ ЭЛЕКТРОННЫЕ УСЛУГИ',
        'description' => 'Находите информацию и пользуйтесь электронными услугами Нарвы в одном месте.',
        'language' => 'Выбор языка',
        'nav' => [
            'home' => 'Главная',
            'eservices' => 'Электронные услуги',
            'procurement' => 'Закупки',
            'property' => 'Городское имущество',
            'account' => 'Личный кабинет',
            'appointment' => 'Запись на приём',
            'apply' => 'Подать заявление',
        ],
        'headings' => [
            'home' => 'Услуги города Нарвы',
            'eservices' => 'Электронные услуги',
            'procurement' => 'Закупки и аукционы',
            'property' => 'Городское имущество',
            'account' => 'Личный кабинет',
            'appointment' => 'Запись на приём',
            'apply' => 'Подача заявления',
        ],
        'content' => 'Ознакомьтесь с услугой и найдите информацию, которая поможет вам сделать следующий шаг.',
    ],
];

$text = $copy[$lang];
$h = fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="<?= $h($lang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#123c70">
    <title><?= $h($text['headings'][$page]) ?> · LIMIS</title>
    <link rel="stylesheet" href="/site.css">
    <link rel="stylesheet" href="/tagasiside/widget.css">
</head>
<body class="service-site">
    <header class="site-header">
        <div class="site-header-inner">
            <a class="site-brand" href="?p=home&amp;lang=<?= $h($lang) ?>">
                <span class="site-brand-name">LIMIS</span>
                <span class="site-brand-location"><?= $h($text['brand']) ?></span>
            </a>
            <nav class="site-language" aria-label="<?= $h($text['language']) ?>">
                <a href="?p=<?= $h($page) ?>&amp;lang=et" lang="et" <?= $lang === 'et' ? 'aria-current="page"' : '' ?>>ET</a>
                <a href="?p=<?= $h($page) ?>&amp;lang=ru" lang="ru" <?= $lang === 'ru' ? 'aria-current="page"' : '' ?>>RU</a>
            </nav>
        </div>
    </header>

    <main class="site-main">
        <p class="site-eyebrow"><?= $h($text['eyebrow']) ?></p>
        <h1><?= $h($text['headings'][$page]) ?></h1>
        <p class="site-intro"><?= $h($page === 'home' ? $text['description'] : $text['content']) ?></p>

        <nav class="service-nav" aria-label="<?= $h($text['title']) ?>">
            <?php foreach ($pages as $navPage): ?>
                <a href="?p=<?= $h($navPage) ?>&amp;lang=<?= $h($lang) ?>" <?= $navPage === $page ? 'aria-current="page"' : '' ?>>
                    <?= $h($text['nav'][$navPage]) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if ($page !== 'home'): ?>
            <section class="service-card">
                <h2><?= $h($text['headings'][$page]) ?></h2>
                <p><?= $h($text['content']) ?></p>
            </section>
        <?php endif; ?>
    </main>

    <script
        src="/tagasiside/widget.js?v=<?= filemtime(__DIR__ . '/tagasiside/widget.js') ?>"
        data-lang="<?= $h($lang) ?>"
        data-page="<?= $h($pageGroup) ?>"
        data-page-key="<?= $h($page) ?>"
        data-critical="<?= $critical ? '1' : '0' ?>"
        data-invite-after-seconds="<?= $inviteAfterSeconds ?>"
        data-invite-min-pages="<?= $inviteMinPages ?>"></script>
</body>
</html>
