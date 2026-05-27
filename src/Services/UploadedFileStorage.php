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

        $relativePath = 'user_' . $tgId . '/' . $fileName;

        try {
            $this->createThumbnail($relativePath);
        } catch (\Throwable $e) {
            error_log('Thumbnail generation failed: ' . $e->getMessage());
        }

        return $relativePath;
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

    public function deleteImageSet(?string $relativePath): void
    {
        if (!$relativePath) {
            return;
        }

        $this->deleteIfExists($relativePath);
        $this->deleteIfExists($this->thumbnailRelativePath($relativePath));
    }

    public function thumbnailRelativePath(string $relativePath): string
    {
        $relativePath = ltrim($relativePath, '/');
        $directory = dirname($relativePath);
        $fileName = pathinfo($relativePath, PATHINFO_FILENAME);

        if (str_starts_with($fileName, 'thumb_')) {
            $thumbnailName = $fileName . '.jpg';
        } else {
            $thumbnailName = 'thumb_' . $fileName . '.jpg';
        }

        return ($directory === '.' ? '' : $directory . '/') . $thumbnailName;
    }

    public function hasThumbnail(string $relativePath): bool
    {
        return is_file($this->fullPath($this->thumbnailRelativePath($relativePath)));
    }

    public function createThumbnail(string $relativePath): void
    {
        $sourcePath = $this->fullPath($relativePath);
        if (!is_file($sourcePath)) {
            throw new \RuntimeException('Source image not found');
        }

        if (!function_exists('imagecreatetruecolor')) {
            throw new \RuntimeException('GD extension is not available');
        }

        $mimeType = $this->mimeType($relativePath);
        $sourceImage = match ($mimeType) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? imagecreatefromjpeg($sourcePath) : false,
            'image/png' => function_exists('imagecreatefrompng') ? imagecreatefrompng($sourcePath) : false,
            'image/webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($sourcePath) : false,
            default => false,
        };

        if (!$sourceImage instanceof \GdImage) {
            throw new \RuntimeException('Unsupported or broken source image');
        }

        $sourceImage = $this->applyExifOrientation($sourceImage, $sourcePath, $mimeType);

        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);
        $cropSize = min($sourceWidth, $sourceHeight);

        if ($cropSize < 1) {
            throw new \RuntimeException('Invalid source image size');
        }

        $thumbnailSize = min(320, $cropSize);
        $sourceX = (int)(($sourceWidth - $cropSize) / 2);
        $sourceY = (int)(($sourceHeight - $cropSize) / 2);

        $thumbnail = imagecreatetruecolor($thumbnailSize, $thumbnailSize);
        if (!$thumbnail instanceof \GdImage) {
            throw new \RuntimeException('Failed to create thumbnail canvas');
        }

        $white = imagecolorallocate($thumbnail, 255, 255, 255);
        if ($white !== false) {
            imagefill($thumbnail, 0, 0, $white);
        }

        if (!imagecopyresampled(
            $thumbnail,
            $sourceImage,
            0,
            0,
            $sourceX,
            $sourceY,
            $thumbnailSize,
            $thumbnailSize,
            $cropSize,
            $cropSize
        )) {
            throw new \RuntimeException('Failed to resample thumbnail');
        }

        $thumbnailPath = $this->fullPath($this->thumbnailRelativePath($relativePath));
        $thumbnailFolder = dirname($thumbnailPath);

        if (!is_dir($thumbnailFolder) && !mkdir($thumbnailFolder, 0755, true)) {
            throw new \RuntimeException('Failed to create thumbnail folder');
        }

        if (!imagejpeg($thumbnail, $thumbnailPath, 80)) {
            throw new \RuntimeException('Failed to save thumbnail');
        }

        chmod($thumbnailPath, 0644);
    }

    private function applyExifOrientation(\GdImage $image, string $sourcePath, string $mimeType): \GdImage
    {
        if ($mimeType !== 'image/jpeg' || !function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($sourcePath);
        $orientation = is_array($exif) ? (int)($exif['Orientation'] ?? 1) : 1;

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => false,
        };

        if (!$rotated instanceof \GdImage) {
            return $image;
        }

        return $rotated;
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
        $used = [];
        foreach (array_filter($usedRelativePaths, 'is_string') as $relativePath) {
            $relativePath = ltrim($relativePath, '/');
            $used[$relativePath] = true;

            if (!$this->isThumbnailPath($relativePath)) {
                $used[$this->thumbnailRelativePath($relativePath)] = true;
            }
        }

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
        return (bool)preg_match('/^user_\d+\/(?:thumb_)?[a-f0-9_]+\.(jpg|png|webp)$/', $relativePath);
    }

    private function isThumbnailPath(string $relativePath): bool
    {
        return (bool)preg_match('/^user_\d+\/thumb_[a-f0-9_]+\.jpg$/', $relativePath);
    }
}
