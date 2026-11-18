<?php
// cron: 0 3 * * * php /path/bin/purge.php   (ответы старше 180 дней + просроченные guard/nonce)

require __DIR__ . '/../src/bootstrap.php';

$d = db();

$n = $d->exec('DELETE FROM limis_tagasiside WHERE created_at<DATE_SUB(NOW(),INTERVAL 180 DAY)');
$d->exec('DELETE FROM limis_tagasiside_guard WHERE expires_at<NOW()');
$d->exec('DELETE FROM limis_tagasiside_nonce WHERE expires_at<NOW()');

echo "Deleted $n old answers\n";
