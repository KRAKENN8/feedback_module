<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#123c70">
    <title>Feedback overview · LIMIS</title>
    <link rel="stylesheet" href="/admin/tagasiside.css">
    <script src="/admin/tagasiside.js" defer></script>
</head>
<body class="dashboard-page">
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="/" aria-label="LIMIS home">
                <span class="brand-mark" aria-hidden="true">L</span>
                <span class="brand-name">LIMIS <span>· NARVA</span></span>
            </a>
            <div class="topbar-label">Feedback administration</div>
            <form method="post" class="logout-form">
                <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                <button class="button button-quiet" name="logout" value="1">Sign out</button>
            </form>
        </div>
    </header>

    <main class="dashboard">
        <section class="page-heading">
            <div>
                <p class="eyebrow">SERVICE INSIGHTS</p>
                <h1>Feedback overview</h1>
                <p class="page-description">See what visitors think and where their experience can improve.</p>
            </div>
        </section>

        <section class="filter-card" aria-label="Feedback filters">
            <form method="get" id="tagasiside-filter">
                <div class="filter-fields">
                    <label>
                        <span class="field-label">Time period</span>
                        <select name="days">
                            <?php foreach ([7, 30, 90, 180] as $d): ?>
                                <option value="<?= $d ?>" <?= $d === $days ? 'selected' : '' ?>>Last <?= $d ?> days</option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span class="field-label">Response language</span>
                        <select name="lang">
                            <option value="">All languages</option>
                            <?php foreach (['et' => 'Estonian', 'ru' => 'Russian'] as $code => $name): ?>
                                <option value="<?= $h($code) ?>" <?= $code === $lang ? 'selected' : '' ?>><?= $h($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <div class="filter-actions">
                    <button class="button button-primary" type="submit">Apply filters</button>
                    <a class="button button-secondary" href="?csv=1&amp;days=<?= $days ?>&amp;lang=<?= $h($lang) ?>">
                        <span aria-hidden="true">↓</span> Export CSV
                    </a>
                    <?php if ($lang !== '' || $days !== 30): ?>
                        <a class="clear-filters" href="tagasiside.php">Clear filters</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="summary-grid" aria-label="Summary">
            <article class="summary-card summary-primary">
                <span class="summary-label">Responses</span>
                <strong class="summary-value"><?= $tot ?></strong>
                <span class="summary-caption">in selected period</span>
            </article>
            <article class="summary-card">
                <span class="summary-label">Average ease rating</span>
                <strong class="summary-value"><?= $h($avg ?? '—') ?><span class="summary-suffix">/ 5</span></strong>
                <span class="summary-caption">visitor-reported ease of use</span>
            </article>
        </section>

        <div class="insights-grid">
            <section class="panel" aria-labelledby="result-title">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">OUTCOME</p>
                        <h2 id="result-title">Task completion</h2>
                    </div>
                </div>
                <?= $bars($dist('completed'), $tot) ?>
            </section>

            <section class="panel" aria-labelledby="rating-title">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">USABILITY</p>
                        <h2 id="rating-title">Ease ratings</h2>
                    </div>
                    <span class="panel-note">Scale of 1–5</span>
                </div>
                <?= $bars($dist('ease_rating'), $tot) ?>
            </section>

            <section class="panel" aria-labelledby="difficulty-title">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">FRICTION</p>
                        <h2 id="difficulty-title">Reported difficulties</h2>
                    </div>
                </div>
                <?= $bars($dist('difficulty'), $tot) ?>
            </section>

            <section class="panel" aria-labelledby="purpose-title">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">VISITOR NEEDS</p>
                        <h2 id="purpose-title">Visit purpose</h2>
                    </div>
                </div>
                <?= $bars($dist('purpose'), $tot) ?>
            </section>
        </div>

        <section class="panel comments-panel" aria-labelledby="comments-title">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">IN THEIR WORDS</p>
                    <h2 id="comments-title">Visitor comments</h2>
                </div>
                <span class="panel-note">Latest <?= count($comments) ?> comments</span>
            </div>
            <?php if ($comments): ?>
                <ul class="comment-list">
                    <?php foreach ($comments as $c): ?>
                        <li class="comment">
                            <div class="comment-meta">
                                <time><?= $h($c['created_at']) ?></time>
                                <span class="language-tag"><?= $h(strtoupper($c['lang'])) ?></span>
                                <span class="rating-tag"><?= (int)$c['ease_rating'] ?>/5 ease</span>
                            </div>
                            <p><?= $h($c['comment']) ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="empty-state">No comments for this period. Try a longer time range.</p>
            <?php endif; ?>
        </section>

        <footer class="dashboard-footer">LIMIS feedback · Demonstration environment</footer>
    </main>
</body>
</html>
