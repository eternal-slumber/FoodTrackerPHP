<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\ValidationException;
use App\Services\UploadedFileStorage;
use App\Uploads\UploadedImagePolicy;
use PHPUnit\Framework\TestCase;

class UploadedFileStorageTest extends TestCase
{
    private string $uploadPath;

    protected function setUp(): void
    {
        $this->uploadPath = sys_get_temp_dir() . '/foodtracker_uploads_' . bin2hex(random_bytes(6)) . '/';
        mkdir($this->uploadPath, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->uploadPath);
    }

    public function testDeleteUserFolderDeletesOnlyTargetUserUploads(): void
    {
        $this->createUpload('user_100001/aaaaaaaa.jpg');
        $this->createUpload('user_100001/nested/bbbbbbbb.png');
        $this->createUpload('user_100002/cccccccc.jpg');

        $storage = new UploadedFileStorage($this->uploadPath);
        $storage->deleteUserFolder(100001);

        $this->assertDirectoryDoesNotExist($this->uploadPath . 'user_100001');
        $this->assertFileExists($this->uploadPath . 'user_100002/cccccccc.jpg');
    }

    public function testDeleteOldOrphanFilesKeepsReferencedAndFreshUploads(): void
    {
        $oldOrphan = $this->createUpload('user_100001/aaaaaaaa.jpg');
        $freshOrphan = $this->createUpload('user_100001/bbbbbbbb.jpg');
        $usedOldUpload = $this->createUpload('user_100001/cccccccc.webp');
        $unmanagedFile = $this->createUpload('misc/dddddddd.jpg');

        touch($oldOrphan, time() - 172800);
        touch($usedOldUpload, time() - 172800);
        touch($unmanagedFile, time() - 172800);

        $storage = new UploadedFileStorage($this->uploadPath);

        $deleted = $storage->deleteOldOrphanFiles(
            ['user_100001/cccccccc.webp'],
            86400
        );

        $this->assertSame(1, $deleted);
        $this->assertFileDoesNotExist($oldOrphan);
        $this->assertFileExists($freshOrphan);
        $this->assertFileExists($usedOldUpload);
        $this->assertFileExists($unmanagedFile);
    }

    public function testThumbnailRelativePathUsesThumbPrefixAndJpegExtension(): void
    {
        $storage = new UploadedFileStorage($this->uploadPath);

        $this->assertSame(
            'user_100001/thumb_aaaaaaaa.jpg',
            $storage->thumbnailRelativePath('user_100001/aaaaaaaa.png')
        );
    }

    public function testDeleteImageSetDeletesOriginalAndThumbnail(): void
    {
        $original = $this->createUpload('user_100001/aaaaaaaa.jpg');
        $thumbnail = $this->createUpload('user_100001/thumb_aaaaaaaa.jpg');

        $storage = new UploadedFileStorage($this->uploadPath);
        $storage->deleteImageSet('user_100001/aaaaaaaa.jpg');

        $this->assertFileDoesNotExist($original);
        $this->assertFileDoesNotExist($thumbnail);
    }

    public function testDeleteOldOrphanFilesKeepsThumbnailForReferencedUpload(): void
    {
        $original = $this->createUpload('user_100001/aaaaaaaa.jpg');
        $thumbnail = $this->createUpload('user_100001/thumb_aaaaaaaa.jpg');
        $orphanThumbnail = $this->createUpload('user_100001/thumb_bbbbbbbb.jpg');

        touch($original, time() - 172800);
        touch($thumbnail, time() - 172800);
        touch($orphanThumbnail, time() - 172800);

        $storage = new UploadedFileStorage($this->uploadPath);
        $deleted = $storage->deleteOldOrphanFiles(
            ['user_100001/aaaaaaaa.jpg'],
            86400
        );

        $this->assertSame(1, $deleted);
        $this->assertFileExists($original);
        $this->assertFileExists($thumbnail);
        $this->assertFileDoesNotExist($orphanThumbnail);
    }

    public function testSaveUploadedFileValidatesContentAndChoosesExtensionFromDetectedMime(): void
    {
        $sourcePath = $this->createTemporaryPng();
        $storage = new UploadedFileStorage($this->uploadPath);

        $relativePath = $storage->saveUploadedFile($sourcePath, 100001);

        $this->assertMatchesRegularExpression('/^user_100001\/[a-f0-9_]+\.png$/', $relativePath);
        $this->assertFileExists($storage->fullPath($relativePath));
        $this->assertFileDoesNotExist($sourcePath);
        $this->assertSame('image/png', $storage->mimeType($relativePath));
    }

    public function testSaveUploadedFileRejectsMissingSource(): void
    {
        $storage = new UploadedFileStorage($this->uploadPath);

        $this->expectException(ValidationException::class);
        $storage->saveUploadedFile(sys_get_temp_dir() . '/missing_' . bin2hex(random_bytes(6)), 100001);
    }

    public function testSaveUploadedFileRejectsNonImageContent(): void
    {
        $sourcePath = tempnam(sys_get_temp_dir(), 'foodtracker_invalid_');
        $this->assertIsString($sourcePath);
        file_put_contents($sourcePath, 'not an image');

        try {
            $storage = new UploadedFileStorage($this->uploadPath);

            $this->expectException(ValidationException::class);
            $storage->saveUploadedFile($sourcePath, 100001);
        } finally {
            if (is_file($sourcePath)) {
                unlink($sourcePath);
            }
        }
    }

    public function testSaveUploadedFileUsesActualFileSizeInsteadOfRequestMetadata(): void
    {
        $sourcePath = tempnam(sys_get_temp_dir(), 'foodtracker_large_');
        $this->assertIsString($sourcePath);
        $handle = fopen($sourcePath, 'wb');
        $this->assertIsResource($handle);
        ftruncate($handle, UploadedImagePolicy::MAX_BYTES + 1);
        fclose($handle);

        try {
            $storage = new UploadedFileStorage($this->uploadPath);

            $this->expectException(ValidationException::class);
            $storage->saveUploadedFile($sourcePath, 100001);
        } finally {
            if (is_file($sourcePath)) {
                unlink($sourcePath);
            }
        }
    }

    public function testSaveUploadedFileRejectsImageWithExcessivePixelDimensions(): void
    {
        $sourcePath = tempnam(sys_get_temp_dir(), 'foodtracker_dimensions_');
        $this->assertIsString($sourcePath);
        file_put_contents($sourcePath, $this->pngWithDimensions(8001, 1));

        try {
            $storage = new UploadedFileStorage($this->uploadPath);

            $this->expectException(ValidationException::class);
            $storage->saveUploadedFile($sourcePath, 100001);
        } finally {
            if (is_file($sourcePath)) {
                unlink($sourcePath);
            }
        }
    }

    public function testSaveUploadedFileRejectsImageWithExcessiveTotalPixelCount(): void
    {
        $sourcePath = tempnam(sys_get_temp_dir(), 'foodtracker_pixels_');
        $this->assertIsString($sourcePath);
        file_put_contents($sourcePath, $this->pngWithDimensions(5001, 5000));

        try {
            $storage = new UploadedFileStorage($this->uploadPath);

            $this->expectException(ValidationException::class);
            $storage->saveUploadedFile($sourcePath, 100001);
        } finally {
            if (is_file($sourcePath)) {
                unlink($sourcePath);
            }
        }
    }

    public function testSaveUploadedFileRejectsSymlinkSource(): void
    {
        $sourcePath = $this->createTemporaryPng();
        $linkPath = sys_get_temp_dir() . '/foodtracker_link_' . bin2hex(random_bytes(6));
        symlink($sourcePath, $linkPath);

        try {
            $storage = new UploadedFileStorage($this->uploadPath);

            $this->expectException(ValidationException::class);
            $storage->saveUploadedFile($linkPath, 100001);
        } finally {
            if (is_link($linkPath)) {
                unlink($linkPath);
            }
            if (is_file($sourcePath)) {
                unlink($sourcePath);
            }
        }
    }

    public function testSaveUploadedFileRejectsSourceOutsideTrustedTemporaryDirectory(): void
    {
        $sourcePath = __DIR__ . '/untrusted_upload_' . bin2hex(random_bytes(6)) . '.png';
        $temporaryImage = $this->createTemporaryPng();
        rename($temporaryImage, $sourcePath);

        try {
            $storage = new UploadedFileStorage($this->uploadPath);

            $this->expectException(ValidationException::class);
            $storage->saveUploadedFile($sourcePath, 100001);
        } finally {
            if (is_file($sourcePath)) {
                unlink($sourcePath);
            }
        }
    }

    public function testFullPathRejectsPathTraversal(): void
    {
        $storage = new UploadedFileStorage($this->uploadPath);

        $this->expectException(\InvalidArgumentException::class);
        $storage->fullPath('../outside.jpg');
    }

    public function testSaveUploadedFileRejectsSymlinkedUserFolder(): void
    {
        $sourcePath = $this->createTemporaryPng();
        $outsidePath = sys_get_temp_dir() . '/foodtracker_outside_' . bin2hex(random_bytes(6));
        mkdir($outsidePath, 0777, true);
        symlink($outsidePath, $this->uploadPath . 'user_100001');

        try {
            $storage = new UploadedFileStorage($this->uploadPath);

            $this->expectException(\RuntimeException::class);
            $storage->saveUploadedFile($sourcePath, 100001);
        } finally {
            $userFolder = $this->uploadPath . 'user_100001';
            if (is_link($userFolder)) {
                unlink($userFolder);
            }
            if (is_file($sourcePath)) {
                unlink($sourcePath);
            }
            if (is_dir($outsidePath)) {
                rmdir($outsidePath);
            }
        }
    }

    private function createTemporaryPng(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'foodtracker_image_');
        $this->assertIsString($path);
        $contents = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true
        );
        $this->assertIsString($contents);
        file_put_contents($path, $contents);

        return $path;
    }

    private function pngWithDimensions(int $width, int $height): string
    {
        $ihdrData = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);
        $ihdr = pack('N', strlen($ihdrData))
            . 'IHDR'
            . $ihdrData
            . pack('N', crc32('IHDR' . $ihdrData));
        $iend = pack('N', 0) . 'IEND' . pack('N', crc32('IEND'));

        return "\x89PNG\r\n\x1a\n" . $ihdr . $iend;
    }

    private function createUpload(string $relativePath): string
    {
        $path = $this->uploadPath . $relativePath;
        $folder = dirname($path);

        if (!is_dir($folder)) {
            mkdir($folder, 0777, true);
        }

        file_put_contents($path, 'test');

        return $path;
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
                continue;
            }

            unlink($item->getPathname());
        }

        rmdir($path);
    }
}
