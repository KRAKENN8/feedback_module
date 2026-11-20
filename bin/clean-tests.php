<?php

declare(strict_types=1);

/**
 * CLI-скрипт очистки базы данных от тестовых записей автотестов и seed.
 *
 * Удаляет записи с тестовыми маркерами автотестов и синтетических данных seed.php,
 * а также просроченные или тестовые токены nonce.
 */

require __DIR__ . '/../src/bootstrap.php';

try {
    $pdo = db();

    $cleanAll = in_array('--all', $argv ?? [], true) || in_array('-a', $argv ?? [], true);

    if ($cleanAll) {
        // Полная очистка таблицы отзывов (сброс для нового цикла или продакшена)
        $stmt = $pdo->prepare('DELETE FROM limis_tagasiside');
        $stmt->execute();
        $deletedRows = $stmt->rowCount();
    } else {
        // Выборочная очистка записей с тестовыми маркерами и старых бестекстовых записей seed
        $stmt = $pdo->prepare("
            DELETE FROM limis_tagasiside 
            WHERE comment LIKE 'LIMIS_TEST_%'
               OR comment LIKE '[SEED]%'
               OR comment LIKE '[TEST%'
               OR comment = 'Väga mugav ja kiire teenus.'
               OR comment = '=HYPERLINK(\"x\")'
               OR comment = '<script>alert(1)</script> test'
               OR (comment IS NULL AND created_at < DATE_SUB(NOW(), INTERVAL 1 DAY))
        ");
        $stmt->execute();
        $deletedRows = $stmt->rowCount();
    }

    // Очистка истёкших токенов nonce
    $stmtNonce = $pdo->prepare('DELETE FROM limis_tagasiside_nonce WHERE expires_at < NOW()');
    $stmtNonce->execute();
    $deletedNonces = $stmtNonce->rowCount();

    echo "Успешно удалено тестовых записей: {$deletedRows}, очищено nonce-токенов: {$deletedNonces}.\n";
} catch (Throwable $e) {
    echo "Ошибка очистки тестовых записей: " . $e->getMessage() . "\n";
    exit(1);
}
