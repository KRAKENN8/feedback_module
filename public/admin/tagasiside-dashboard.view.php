<?php
/**
 * Шаблон панели мониторинга отзывов администратора (роль Peakasutaja).
 *
 * @var string   $ui          Язык интерфейса ('et' или 'ru')
 * @var array    $T           Словарь переводов
 * @var string   $csrf        CSRF-токен текущей сессии
 * @var int      $days        Выбранный период в днях (7, 30, 90, 180)
 * @var string   $lang        Выбранный фильтр языка отзыва ('', 'et', 'ru')
 * @var int      $tot         Общее количество ответов за период
 * @var float|null $avg       Средняя оценка удобства использования
 * @var callable $bars        Генератор горизонтальных графиков распределения
 * @var callable $dist        Выборка распределения по столбцу
 * @var array    $comments    Список последних текстовых отзывов
 * @var callable $h           Функция экранирования HTML
 */
?>
<!doctype html>
<html lang="<?= $h($ui) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#005aa3">
    <title><?= $h($T['dashboard_title']) ?> · LIMIS</title>
    <link rel="icon" type="image/x-icon" href="https://limis.narva.ee/failid/png/Vapp_0_2.ico">
    <link rel="stylesheet" href="/admin/tagasiside.css">
    <script src="/admin/tagasiside.js" defer></script>
</head>
<body class="dashboard-page">
    <header class="limis-header site-header topbar">
        <div class="limis-header__inner header-inner topbar-inner">
            <a class="limis-header__brand navbar-brand brand" href="/" title="Narva Linnavalitsuse Linnamajandusameti Infosüsteem (LIMIS)" aria-label="<?= $h($T['home_label']) ?>">
                Narva Linnamajandusamet · LIMIS
            </a>
            <div class="topbar-label"><?= $h($T['admin_label']) ?></div>
            <nav class="limis-header__nav" aria-label="<?= $ui === 'et' ? 'Keelevalik' : 'Выбор языка' ?>">
                <div class="limis-header__langs language-switcher language-switch" aria-label="<?= $ui === 'et' ? 'Keelevalik' : 'Выбор языка' ?>">
                    <a href="?days=<?= $days ?>&amp;lang=<?= $h($lang) ?>&amp;ui=et" id="et" lang="et" class="<?= $ui === 'et' ? 'is-active' : '' ?>" <?= $ui === 'et' ? 'aria-current="page"' : '' ?>>ET</a>
                    <a href="?days=<?= $days ?>&amp;lang=<?= $h($lang) ?>&amp;ui=ru" id="ru" lang="ru" class="<?= $ui === 'ru' ? 'is-active' : '' ?>" <?= $ui === 'ru' ? 'aria-current="page"' : '' ?>>RU</a>
                </div>
            </nav>
            <form method="post" class="logout-form">
                <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                <input type="hidden" name="ui" value="<?= $h($ui) ?>">
                <button class="button button-quiet" name="logout" value="1"><?= $h($T['sign_out']) ?></button>
            </form>
        </div>
    </header>

    <main class="dashboard">
        <section class="page-heading">
            <div>
                <p class="eyebrow"><?= $h($T['insights_eyebrow']) ?></p>
                <h1><?= $h($T['dashboard_title']) ?></h1>
                <p class="page-description"><?= $h($T['dashboard_intro']) ?></p>
            </div>
        </section>

        <section class="filter-card" aria-label="<?= $h($T['filters']) ?>">
            <form method="get" id="tagasiside-filter">
                <input type="hidden" name="ui" value="<?= $h($ui) ?>">
                <div class="filter-fields">
                    <label>
                        <span class="field-label"><?= $h($T['period']) ?></span>
                        <select name="days">
                            <?php foreach ([7, 30, 90, 180] as $d): ?>
                                <option value="<?= $d ?>" <?= $d === $days ? 'selected' : '' ?>><?= $h(sprintf($T['last_days'], $d)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span class="field-label"><?= $h($T['response_language']) ?></span>
                        <select name="lang">
                            <option value=""><?= $h($T['all_languages']) ?></option>
                            <?php foreach (['et' => $T['estonian'], 'ru' => $T['russian']] as $code => $name): ?>
                                <option value="<?= $h($code) ?>" <?= $code === $lang ? 'selected' : '' ?>><?= $h($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <div class="filter-actions">
                    <button class="button button-primary" type="submit"><?= $h($T['apply_filters']) ?></button>
                    <a class="button button-secondary" href="?csv=1&amp;days=<?= $days ?>&amp;lang=<?= $h($lang) ?>&amp;ui=<?= $h($ui) ?>">
                        <span aria-hidden="true">↓</span> <?= $h($T['export_csv']) ?>
                    </a>
                    <?php if ($lang !== '' || $days !== 30): ?>
                        <a class="clear-filters" href="?ui=<?= $h($ui) ?>"><?= $h($T['clear_filters']) ?></a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="summary-grid" aria-label="<?= $h($T['summary']) ?>">
            <article class="summary-card summary-primary">
                <span class="summary-label"><?= $h($T['responses']) ?></span>
                <strong class="summary-value"><?= $tot ?></strong>
                <span class="summary-caption"><?= $h($T['selected_period']) ?></span>
            </article>
            <article class="summary-card">
                <span class="summary-label"><?= $h($T['average_rating']) ?></span>
                <strong class="summary-value"><?= $h($avg ?? '—') ?><span class="summary-suffix">/ 5</span></strong>
                <span class="summary-caption"><?= $h($T['visitor_rating']) ?></span>
            </article>
        </section>

        <div class="insights-grid">
            <section class="panel" aria-labelledby="result-title">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow"><?= $h($T['outcome']) ?></p>
                        <h2 id="result-title"><?= $h($T['completion']) ?></h2>
                    </div>
                </div>
                <?= $bars($dist('completed'), $tot, 'completed') ?>
            </section>

            <section class="panel" aria-labelledby="rating-title">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow"><?= $h($T['usability']) ?></p>
                        <h2 id="rating-title"><?= $h($T['ease_ratings']) ?></h2>
                    </div>
                    <span class="panel-note"><?= $h($T['scale']) ?></span>
                </div>
                <?= $bars($dist('ease_rating'), $tot, 'ease') ?>
            </section>

            <section class="panel" aria-labelledby="difficulty-title">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow"><?= $h($T['friction']) ?></p>
                        <h2 id="difficulty-title"><?= $h($T['difficulties']) ?></h2>
                    </div>
                </div>
                <?= $bars($dist('difficulty'), $tot, 'difficulty') ?>
            </section>

            <section class="panel" aria-labelledby="purpose-title">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow"><?= $h($T['visitor_needs']) ?></p>
                        <h2 id="purpose-title"><?= $h($T['visit_purpose']) ?></h2>
                    </div>
                </div>
                <?= $bars($dist('purpose'), $tot, 'purpose') ?>
            </section>
        </div>

        <section class="panel comments-panel" aria-labelledby="comments-title">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow"><?= $h($T['in_their_words']) ?></p>
                    <h2 id="comments-title"><?= $h($T['comments']) ?></h2>
                </div>
                <span class="panel-note"><?= $h(sprintf($T['latest_comments'], count($comments))) ?></span>
            </div>
            <?php if ($comments): ?>
                <ul class="comment-list">
                    <?php foreach ($comments as $c): ?>
                        <?php $score = (int)$c['ease_rating']; ?>
                        <li class="comment">
                            <div class="comment-meta">
                                <time><?= $h($c['created_at']) ?></time>
                                <span class="language-tag"><?= $h(strtoupper($c['lang'])) ?></span>
                                <span class="rating-tag rating-tag--<?= $score ?>" data-rating="<?= $score ?>"><?= $score ?>/5 · <?= $h($T['ease_words'][(string)$score] ?? $T['ease_suffix']) ?></span>
                            </div>
                            <p><?= $h($c['comment']) ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="empty-state"><?= $h($T['no_comments']) ?></p>
            <?php endif; ?>
        </section>

        <footer class="dashboard-footer"><?= $h($T['product_footer']) ?></footer>
    </main>
</body>
</html>
