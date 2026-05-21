<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\UploadedFileStorage;
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
