<?php

declare(strict_types=1);

use App\Repositories\MealRepository;
use App\Services\UploadedFileStorage;

$container = require __DIR__ . '/../bootstrap/container.php';

$ttlSeconds = (int)($_ENV['UPLOAD_ORPHAN_TTL_SECONDS'] ?? 86400);
if ($ttlSeconds < 0) {
    $ttlSeconds = 86400;
}

if ($ttlSeconds === 0) {
    fwrite(
        STDERR,
        "UPLOAD_ORPHAN_TTL_SECONDS=0 would delete every orphan upload immediately. Set a positive TTL before running cleanup.\n"
    );
    exit(1);
}

$mealRepository = $container->get(MealRepository::class);
$storage = $container->get(UploadedFileStorage::class);

$deleted = $storage->deleteOldOrphanFiles(
    $mealRepository->findAllImagePaths(),
    $ttlSeconds
);

echo "Deleted orphan upload files: {$deleted}\n";
