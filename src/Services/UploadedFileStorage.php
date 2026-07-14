<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Uploads\UploadedImagePolicy;

class UploadedFileStorage
{
    private readonly string $uploadPath;

    public function __construct(string $uploadPath)
    {
        $this->uploadPath = rtrim($uploadPath, '/') . '/';
    }

    public function saveUploadedFile(string $tmpPath, int $tgId): string
    {
        [$sourcePath, $mimeType] = $this->validateSourceImage($tmpPath);
        $userFolder = $this->trustedUserFolder($tgId);

        $extension = UploadedImagePolicy::MIME_TO_EXTENSION[$mimeType];

        $fileName = time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $targetPath = $userFolder . '/' . $fileName;

        $moved = is_uploaded_file($sourcePath)
            ? move_uploaded_file($sourcePath, $targetPath)
            : @rename($sourcePath, $targetPath);
        if (!$moved) {
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
        if (is_file($path) && !@unlink($path)) {
            throw new \RuntimeException('Could not delete upload file');
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
        $this->validateImageDimensions($sourcePath, $mimeType);
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

        imagecopyresampled(
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
        );

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

    /** @param list<string> $usedRelativePaths */
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

    /** @return list<string> */
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
        if (!$this->isManagedUploadPath($relativePath)) {
            throw new \InvalidArgumentException('Invalid managed upload path');
        }

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

    /** @return array{string, string} */
    private function validateSourceImage(string $tmpPath): array
    {
        if ($tmpPath === '' || str_contains($tmpPath, "\0") || str_contains($tmpPath, '://')) {
            throw new ValidationException('Invalid upload source');
        }

        if (is_link($tmpPath) || !is_file($tmpPath) || !is_readable($tmpPath)) {
            throw new ValidationException('Uploaded image is unavailable');
        }

        $sourcePath = realpath($tmpPath);
        if ($sourcePath === false || !$this->isTrustedTemporaryPath($sourcePath)) {
            throw new ValidationException('Upload source is outside the trusted temporary directory');
        }

        $size = filesize($sourcePath);
        if ($size === false || $size < 1 || $size > UploadedImagePolicy::MAX_BYTES) {
            throw new ValidationException('Uploaded image size is invalid');
        }

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($sourcePath);
        if (!is_string($mimeType) || !isset(UploadedImagePolicy::MIME_TO_EXTENSION[$mimeType])) {
            throw new ValidationException('Uploaded file is not a supported image');
        }

        $this->validateImageDimensions($sourcePath, $mimeType);

        return [$sourcePath, $mimeType];
    }

    private function validateImageDimensions(string $sourcePath, string $expectedMimeType): void
    {
        $imageInfo = @getimagesize($sourcePath);
        if (!is_array($imageInfo) || $imageInfo['mime'] !== $expectedMimeType) {
            throw new ValidationException('Uploaded image content is invalid');
        }

        $width = $imageInfo[0];
        $height = $imageInfo[1];
        if (
            $width < 1
            || $height < 1
            || $width > UploadedImagePolicy::MAX_WIDTH
            || $height > UploadedImagePolicy::MAX_HEIGHT
            || $width * $height > UploadedImagePolicy::MAX_PIXELS
        ) {
            throw new ValidationException('Uploaded image dimensions are too large');
        }
    }

    private function isTrustedTemporaryPath(string $sourcePath): bool
    {
        if (is_uploaded_file($sourcePath)) {
            return true;
        }

        $temporaryRoots = [sys_get_temp_dir(), (string)ini_get('upload_tmp_dir')];
        foreach ($temporaryRoots as $temporaryRoot) {
            if ($temporaryRoot === '') {
                continue;
            }

            $resolvedRoot = realpath($temporaryRoot);
            if ($resolvedRoot !== false && $this->isPathInside($sourcePath, $resolvedRoot)) {
                return true;
            }
        }

        return false;
    }

    private function trustedUserFolder(int $tgId): string
    {
        if ($tgId < 1 || $tgId > 10000000000) {
            throw new ValidationException('Invalid upload owner');
        }

        if (!is_dir($this->uploadPath) && !mkdir($this->uploadPath, 0755, true)) {
            throw new \RuntimeException('Failed to create upload folder');
        }

        $uploadRoot = realpath($this->uploadPath);
        if ($uploadRoot === false || is_link(rtrim($this->uploadPath, '/'))) {
            throw new \RuntimeException('Invalid upload folder');
        }

        $userFolder = $this->userFolder($tgId);
        if (is_link($userFolder)) {
            throw new \RuntimeException('Invalid user upload folder');
        }

        if (!is_dir($userFolder) && !mkdir($userFolder, 0755, true)) {
            throw new \RuntimeException('Failed to create user folder');
        }

        $resolvedUserFolder = realpath($userFolder);
        if ($resolvedUserFolder === false || !$this->isPathInside($resolvedUserFolder, $uploadRoot)) {
            throw new \RuntimeException('User upload folder escapes upload root');
        }

        return $resolvedUserFolder;
    }

    private function isPathInside(string $path, string $root): bool
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR);

        return $path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR);
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
