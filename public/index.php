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
        'apply_notice_title' => 'Tagasiside hüpikteavitus on sellel lehel välja lülitatud',
        'apply_notice_text' => 'Kuna sellel lehel toimub andmete sisestamine (kriitiline toiming ja taotluse täitmine), ei kuvata siin hüpikakent ega automaatset kutset, et vältida tähelepanu hajutamist ja poolelioleva sisestamise katkestamist.',
        'apply_notice_param_label' => 'Rakendatud parameeter:',
        'apply_notice_param_desc' => 'skriptile on määratud atribuut data-critical="1" (toetatud ka LimisFeedback.setCritical(true) ja sündmus limis-feedback:critical). Püsiv nupp „Hinda LIMIS-t” lehe allservas jääb soovi korral kättesaadavaks.',
        'apply_form_heading' => 'Taotluse andmete sisestamine',
        'apply_form_intro' => 'Täitke allolevad väljad taotluse esitamiseks. Sisestamise ajal ei segata teid hüpikteavitustega.',
        'apply_field_subject' => 'Taotluse teema või valdkond',
        'apply_field_subject_placeholder' => 'Nt elukoha registreerimine, toetuse taotlemine...',
        'apply_field_details' => 'Taotluse sisu või selgitus',
        'apply_field_details_placeholder' => 'Kirjeldage lühidalt oma taotluse sisu...',
        'apply_submit_btn' => 'Salvesta ja jätka',
        'apply_draft_btn' => 'Salvesta mustandina',
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
        'apply_notice_title' => 'Всплывающее уведомление опроса на этой странице отключено',
        'apply_notice_text' => 'Поскольку на этой странице выполняется ввод данных (критическая операция и заполнение заявления), всплывающее окно и автоприглашение намеренно заблокированы, чтобы не отвлекать пользователя и не прерывать процесс ввода.',
        'apply_notice_param_label' => 'Применённый параметр:',
        'apply_notice_param_desc' => 'в теге <script> виджета задан атрибут data-critical="1" (также поддерживается вызов LimisFeedback.setCritical(true) и событие limis-feedback:critical). Плавающая кнопка «Оценить LIMIS» внизу страницы остаётся доступной для добровольной отправки.',
        'apply_form_heading' => 'Ввод данных заявления',
        'apply_form_intro' => 'Заполните поля ниже для подачи заявления. Во время ввода вас не будут отвлекать всплывающие уведомления.',
        'apply_field_subject' => 'Тема или сфера заявления',
        'apply_field_subject_placeholder' => 'Например, регистрация места жительства, ходатайство о пособии...',
        'apply_field_details' => 'Содержание заявления или пояснение',
        'apply_field_details_placeholder' => 'Кратко опишите содержание заявления...',
        'apply_submit_btn' => 'Сохранить и продолжить',
        'apply_draft_btn' => 'Сохранить как черновик',
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
    <meta name="theme-color" content="#183F68">
    <meta name="description" content="<?= $h($page === 'home' ? $text['description'] : $text['content']) ?>">
    <title><?= $h($text['headings'][$page]) ?> · LIMIS</title>
    <link rel="stylesheet" href="/site.css">
    <link rel="stylesheet" href="/tagasiside/widget.css">
    <script>
        (function () {
            try {
                var params = new URLSearchParams(window.location.search);
                var current = <?= json_encode($lang) ?>;
                if (params.has('lang')) {
                    localStorage.setItem('language', current);
                } else {
                    var stored = localStorage.getItem('language');
                    if ((stored === 'ru' || stored === 'et') && stored !== current) {
                        params.set('lang', stored);
                        window.location.replace(window.location.pathname + '?' + params.toString());
                    } else {
                        localStorage.setItem('language', current);
                    }
                }
            } catch (e) {}
        })();
    </script>
</head>
<body class="service-site">
    <header class="site-header">
        <div class="site-header-inner">
            <a class="site-brand" href="?p=home&amp;lang=<?= $h($lang) ?>">
                <span class="site-brand-name">LIMIS</span>
                <span class="site-brand-location"><?= $h($text['brand']) ?></span>
            </a>
            <nav class="site-language" aria-label="<?= $h($text['language']) ?>">
                <a href="?p=<?= $h($page) ?>&amp;lang=et" lang="et" <?= $lang === 'et' ? 'aria-current="page"' : '' ?> onclick="try{localStorage.setItem('language','et');}catch(e){}">ET</a>
                <a href="?p=<?= $h($page) ?>&amp;lang=ru" lang="ru" <?= $lang === 'ru' ? 'aria-current="page"' : '' ?> onclick="try{localStorage.setItem('language','ru');}catch(e){}">RU</a>
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

        <?php if ($page === 'apply'): ?>
            <div class="service-notice service-notice--critical" role="region" aria-label="<?= $h($text['apply_notice_title']) ?>">
                <div class="service-notice-header">
                    <span class="service-notice-icon" aria-hidden="true">&#9432;</span>
                    <strong><?= $h($text['apply_notice_title']) ?></strong>
                </div>
                <p><?= $h($text['apply_notice_text']) ?></p>
                <p class="service-notice-param">
                    <strong><?= $h($text['apply_notice_param_label']) ?></strong>
                    <code>data-critical="1"</code>
                    <span>(<?= $h($text['apply_notice_param_desc']) ?>)</span>
                </p>
            </div>

            <section class="service-card service-card--form">
                <h2><?= $h($text['apply_form_heading']) ?></h2>
                <p class="form-intro"><?= $h($text['apply_form_intro']) ?></p>

                <form class="demo-apply-form" action="#" method="post" onsubmit="event.preventDefault();">
                    <div class="form-group">
                        <label for="apply_subject"><?= $h($text['apply_field_subject']) ?></label>
                        <input
                            type="text"
                            id="apply_subject"
                            name="subject"
                            class="form-control"
                            placeholder="<?= $h($text['apply_field_subject_placeholder']) ?>"
                            autocomplete="off">
                    </div>

                    <div class="form-group">
                        <label for="apply_details"><?= $h($text['apply_field_details']) ?></label>
                        <textarea
                            id="apply_details"
                            name="details"
                            rows="4"
                            class="form-control"
                            placeholder="<?= $h($text['apply_field_details_placeholder']) ?>"></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><?= $h($text['apply_submit_btn']) ?></button>
                        <button type="button" class="btn btn-secondary"><?= $h($text['apply_draft_btn']) ?></button>
                    </div>
                </form>
            </section>
        <?php elseif ($page !== 'home'): ?>
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
