<?php
// Синтетические данные (в т.ч. старше 180 дней для проверки purge.php)

require __DIR__ . '/../src/bootstrap.php';

$s = db()->prepare('INSERT INTO limis_tagasiside(created_at,lang,purpose,completed,ease_rating,difficulty,comment,page_group) VALUES(DATE_SUB(NOW(),INTERVAL ? DAY),?,?,?,?,?,?,?)');

$pk = fn($a) => $a[array_rand($a)];

for ($i = 0; $i < 60; $i++) {
    $s->execute([
        $i < 8 ? 200 : random_int(0, 170),
        $pk(ENUMS['lang']),
        $pk(ENUMS['purpose']),
        $pk(ENUMS['completed']),
        random_int(1, 5),
        $pk(ENUMS['difficulty']),
        $i % 7 ? null : ($i % 14 ? '=HYPERLINK("x")' : '<script>alert(1)</script> test'),
        $pk(ENUMS['page_group'])
    ]);
}

echo "Seeded 60 synthetic rows\n";
