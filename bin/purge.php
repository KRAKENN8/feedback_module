<?php

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
        $answers = $d->exec('DELETE FROM limis_tagasiside WHERE created_at<DATE_SUB(NOW(),INTERVAL 180 DAY)');
        $guards = $d->exec('DELETE FROM limis_tagasiside_guard WHERE expires_at<NOW()');
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
