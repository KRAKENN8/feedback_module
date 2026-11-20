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

    // 1. Удаление тестовых записей из limis_tagasiside
    $stmt = $pdo->prepare("
        DELETE FROM limis_tagasiside 
        WHERE comment LIKE 'LIMIS_TEST_%'
           OR comment = 'Väga mugav ja kiire teenus.'
           OR comment = '=HYPERLINK(\"x\")'
           OR comment = '<script>alert(1)</script> test'
    ");
    $stmt->execute();
    $deletedRows = $stmt->rowCount();

    // 2. Очистка истёкших токенов nonce
    $stmtNonce = $pdo->prepare('DELETE FROM limis_tagasiside_nonce WHERE expires_at < NOW()');
    $stmtNonce->execute();
    $deletedNonces = $stmtNonce->rowCount();

    echo "Успешно удалено тестовых записей: {$deletedRows}, очищено nonce-токенов: {$deletedNonces}.\n";
} catch (Throwable $e) {
    echo "Ошибка очистки тестовых записей: " . $e->getMessage() . "\n";
    exit(1);
}
