<?php
/**
 * Шаблон экрана 403 Forbidden (недостаточно прав, требуется роль Peakasutaja).
 *
 * @var string   $ui          Язык интерфейса ('et' или 'ru')
 * @var array    $T           Словарь переводов
 * @var string   $csrf        CSRF-токен сессии
 * @var string   $activeRole  Текущая активная роль пользователя
 * @var callable $h           Функция экранирования HTML
 */
?>
<!doctype html>
<html lang="<?= $h($ui) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#183F68">
    <title><?= $h($T['forbidden_title']) ?></title>
    <link rel="icon" type="image/x-icon" href="https://limis.narva.ee/failid/png/Vapp_0_2.ico">
    <link rel="stylesheet" href="/admin/tagasiside.css">
</head>
<body class="login-page">
    <main class="login-shell">
        <section class="login-card" aria-labelledby="forbidden-title">
            <div class="login-card-top">
                <p class="eyebrow" style="color: #b91c1c;"><?= $h($T['forbidden_eyebrow']) ?></p>
                <nav class="language-switch login-language" aria-label="<?= $ui === 'et' ? 'Keelevalik' : 'Выбор языка' ?>">
                    <a href="?ui=et" lang="et" <?= $ui === 'et' ? 'aria-current="page"' : '' ?>>ET</a>
                    <a href="?ui=ru" lang="ru" <?= $ui === 'ru' ? 'aria-current="page"' : '' ?>>RU</a>
                </nav>
            </div>
            <h1 id="forbidden-title" style="color: #991b1b;"><?= $h($T['forbidden_heading']) ?></h1>
            <p class="login-intro"><?= $h($T['forbidden_message']) ?></p>

            <div class="alert" style="margin-bottom: 20px; font-size: 14px; background: #fff8f8; border-color: #fca5a5; color: #7f1d1d;">
                <strong><?= $h($T['forbidden_active_role']) ?></strong>
                <code style="padding: 2px 6px; background: #fee2e2; border-radius: 4px; font-weight: bold;"><?= $h($activeRole) ?></code>
                <p style="margin: 8px 0 0; color: #991b1b;"><?= $h($T['forbidden_contact_lead']) ?></p>
            </div>

            <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-top: 20px;">
                <form method="post" action="tagasiside.php" style="margin: 0;">
                    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                    <input type="hidden" name="logout" value="1">
                    <button type="submit" class="button button-primary">
                        <?= $h($T['switch_account']) ?>
                    </button>
                </form>
                <a href="/" class="button button-secondary" style="display: inline-flex; align-items: center; text-decoration: none;">
                    <?= $h($T['return_home']) ?>
                </a>
            </div>

            <p class="login-note"><?= $h($T['product_footer']) ?></p>
        </section>
        <footer class="login-footer">
            <p>© <?= date('Y') ?> Narva Linnavalitsus · LIMIS</p>
        </footer>
    </main>
</body>
</html>
