<?php

declare(strict_types=1);

use App\Services\NotificationDispatchService;

$container = require __DIR__ . '/../bootstrap/container.php';

$lockHandle = fopen(sys_get_temp_dir() . '/foodtracker-notification-dispatch.lock', 'c');
if ($lockHandle === false) {
    fwrite(STDERR, "Unable to open the notification dispatch lock file.\n");
    exit(1);
}

if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    fclose($lockHandle);
    echo "Notification dispatch is already running.\n";
    exit(0);
}

$exitCode = 0;

try {
    $batchSize = filter_var(
        $_ENV['NOTIFICATION_DISPATCH_BATCH_SIZE'] ?? 50,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 100]]
    );

    if ($batchSize === false) {
        throw new RuntimeException(
            'NOTIFICATION_DISPATCH_BATCH_SIZE must be an integer from 1 to 100.'
        );
    }

    $result = $container
        ->get(NotificationDispatchService::class)
        ->dispatchDue(limit: $batchSize);

    echo sprintf(
        "Notification dispatch complete: claimed=%d sent=%d skipped=%d failed=%d next_scheduled=%d\n",
        $result['claimed'],
        $result['sent'],
        $result['skipped'],
        $result['failed'],
        $result['next_scheduled']
    );
} catch (Throwable $error) {
    fwrite(STDERR, "Notification dispatch failed: {$error->getMessage()}\n");
    $exitCode = 1;
}

flock($lockHandle, LOCK_UN);
fclose($lockHandle);

exit($exitCode);
