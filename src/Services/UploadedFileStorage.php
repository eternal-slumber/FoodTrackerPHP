<?php

declare(strict_types=1);

namespace App\Services;

class UploadedFileStorage
{
    private readonly string $uploadPath;

    public function __construct(string $uploadPath)
    {
        $this->uploadPath = rtrim($uploadPath, '/') . '/';
    }

    public function saveUploadedFile(string $tmpPath, int $tgId, string $mimeType): string
    {
        $userFolder = $this->uploadPath . 'user_' . $tgId;

        if (!file_exists($userFolder) && !mkdir($userFolder, 0755, true)) {
            throw new \RuntimeException('Failed to create user folder');
        }

        $extension = match ($mimeType) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $fileName = time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $targetPath = $userFolder . '/' . $fileName;

        if (!move_uploaded_file($tmpPath, $targetPath)) {
            throw new \RuntimeException('Failed to save uploaded file');
        }

        chmod($targetPath, 0644);

        return 'user_' . $tgId . '/' . $fileName;
    }

    public function sanitizeDraftImagePath(?string $imagePath, int $tgId): ?string
    {
        if (!$imagePath) {
            return null;
        }

        $expectedPrefix = 'user_' . $tgId . '/';
        if (!str_starts_with($imagePath, $expectedPrefix)) {
            return null;
        }

        if (!preg_match('/^user_\d+\/[a-f0-9_]+\.(jpg|png|webp)$/', $imagePath)) {
            return null;
        }

        return is_file($this->fullPath($imagePath)) ? $imagePath : null;
    }

    public function deleteIfExists(?string $relativePath): void
    {
        if (!$relativePath) {
            return;
        }

        $path = $this->fullPath($relativePath);
        if (is_file($path)) {
            unlink($path);
        }
    }

    public function deleteUserFolder(int $tgId): void
    {
        $folder = $this->userFolder($tgId);

        if (!is_dir($folder)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
                continue;
            }

            unlink($item->getPathname());
        }

        rmdir($folder);
    }

    public function deleteOldOrphanFiles(array $usedRelativePaths, int $olderThanSeconds): int
    {
        $used = array_flip(array_filter($usedRelativePaths, 'is_string'));
        $cutoff = time() - max(0, $olderThanSeconds);
        $deleted = 0;

        foreach ($this->listUploadFiles() as $relativePath) {
            if (isset($used[$relativePath])) {
                continue;
            }

            $path = $this->fullPath($relativePath);
            $modifiedAt = filemtime($path);

            if ($modifiedAt === false || $modifiedAt > $cutoff) {
                continue;
            }

            unlink($path);
            $deleted++;
        }

        return $deleted;
    }

    public function listUploadFiles(): array
    {
        if (!is_dir($this->uploadPath)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->uploadPath, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $relativePath = ltrim(str_replace($this->uploadPath, '', $item->getPathname()), '/');
            if ($this->isManagedUploadPath($relativePath)) {
                $files[] = $relativePath;
            }
        }

        return $files;
    }

    public function fullPath(string $relativePath): string
    {
        return $this->uploadPath . ltrim($relativePath, '/');
    }

    public function mimeType(string $relativePath): string
    {
        return (new \finfo(FILEINFO_MIME_TYPE))->file($this->fullPath($relativePath)) ?: 'application/octet-stream';
    }

    private function userFolder(int $tgId): string
    {
        return $this->uploadPath . 'user_' . $tgId;
    }

    private function isManagedUploadPath(string $relativePath): bool
    {
        return (bool)preg_match('/^user_\d+\/[a-f0-9_]+\.(jpg|png|webp)$/', $relativePath);
    }
}
