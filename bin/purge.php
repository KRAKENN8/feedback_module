<?php

declare(strict_types=1);

/**
 * CLI-скрипт регламентной очистки устаревших данных (ТЗ № LIMIS-FEEDBACK-01).
 *
 * Выполняет:
 * - Удаление ответов жителей старше 180 дней (требование минимизации и ограничения сроков хранения GDPR).
 * - Удаление истёкших записей rate-limit guard (MariaDB fallback).
 * - Удаление истёкших одноразовых токенов nonce.
 * - Защиту от параллельного запуска через консультативную блокировку GET_LOCK('limis-feedback-purge', 0).
 *
 * Рекомендуемый запуск: через cron раз в сутки (например, 03:00).
 */

require __DIR__ . '/../src/bootstrap.php';

try {
    $d = db();
    $lockName = 'limis-feedback-purge';
    $lock = $d->prepare('SELECT GET_LOCK(?, 0)');
    $lock->execute([$lockName]);

    if ((int)$lock->fetchColumn() !== 1) {
        fwrite(STDERR, "A cleanup process is already running.\n");
        exit(1);
    }

    try {
        // Очистка ответов старше 180 дней
        $answers = $d->exec('DELETE FROM limis_tagasiside WHERE created_at<DATE_SUB(NOW(),INTERVAL 180 DAY)');
        // Очистка истёкших записей лимитера MariaDB
        $guards = $d->exec('DELETE FROM limis_tagasiside_guard WHERE expires_at<NOW()');
        // Очистка истёкших одноразовых токенов
        $nonces = $d->exec('DELETE FROM limis_tagasiside_nonce WHERE expires_at<NOW()');

        printf(
            "Cleanup completed. Answers: %d; rate-limit records: %d; token records: %d.\n",
            $answers,
            $guards,
            $nonces
        );
    } finally {
        $release = $d->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    }
} catch (Throwable $e) {
    $message = 'Feedback cleanup failed: ' . get_class($e);
    error_log($message . ': ' . $e->getMessage());
    fwrite(STDERR, $message . "\n");
    exit(1);
}
