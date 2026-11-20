<?php

declare(strict_types=1);

/**
 * CLI-скрипт генерации синтетических данных для тестирования модуля и панели администратора.
 *
 * Генерирует 60 случайных ответов с различными оценками, целями и комментариями,
 * включая записи старше 180 дней для проверки работы purge.php.
 */

require __DIR__ . '/../src/bootstrap.php';

$s = db()->prepare('INSERT INTO limis_tagasiside(created_at,lang,purpose,completed,ease_rating,difficulty,comment,page_group) VALUES(DATE_SUB(NOW(),INTERVAL ? DAY),?,?,?,?,?,?,?)');

$pk = fn($a) => $a[array_rand($a)];

$seedComments = [
    '[SEED] Väga mugav ja arusaadav teenus.',
    '[SEED] Leidsin vajaliku teabe kiiresti üles.',
    '[SEED] Sooviksin rohkem selgitusi taotluse esitamisel.',
    '[SEED] Понятный интерфейс, спасибо за обновления.',
    '[SEED] Не сразу нашел нужный раздел, но в целом удобно.',
    '[SEED] Работает быстро и без сбоев.',
    '[SEED] =HYPERLINK("https://example.com")',
    '[SEED] <script>alert(1)</script> test XSS injection',
    '[SEED] Tagasiside ilma lisakommentaarita.'
];

for ($i = 0; $i < 60; $i++) {
    $s->execute([
        $i < 8 ? 200 : random_int(0, 170),
        $pk(ENUMS['lang']),
        $pk(ENUMS['purpose']),
        $pk(ENUMS['completed']),
        random_int(1, 5),
        $pk(ENUMS['difficulty']),
        $seedComments[$i % count($seedComments)],
        $pk(ENUMS['page_group'])
    ]);
}

echo "Seeded 60 synthetic rows with [SEED] markers\n";
