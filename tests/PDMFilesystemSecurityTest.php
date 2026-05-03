<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMFilesystemSecurityTest extends TestCase
{
    private string $basePath;
    private string $externalPath;

    protected function setUp(): void
    {
        $root = sys_get_temp_dir() . '/mstv-fs-security-test-' . bin2hex(random_bytes(6));
        $this->basePath = $root . '/storage';
        $this->externalPath = $root . '/outside.txt';

        mkdir($this->basePath, 0777, true);
        file_put_contents($this->basePath . '/inside.txt', 'inside');
        file_put_contents($this->externalPath, 'outside');

        $GLOBALS['wp_filesystem'] = new class {
            public function delete($path, $recursive = false, $type = false): bool
            {
                return false;
            }

            public function move($from, $to, $overwrite = false): bool
            {
                return false;
            }
        };
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wp_filesystem']);
        $this->deleteDirectory(dirname($this->basePath));
    }

    public function test_delete_file_rejects_traversal_outside_storage_base(): void
    {
        $filesystem = new MSTV_Filesystem($this->basePath);

        self::assertFalse($filesystem->delete_file('../outside.txt'));
        self::assertFileExists($this->externalPath);
    }

    public function test_move_file_rejects_source_traversal_outside_storage_base(): void
    {
        $filesystem = new MSTV_Filesystem($this->basePath);

        self::assertFalse($filesystem->move_file('../outside.txt', 'moved.txt'));
        self::assertFileExists($this->externalPath);
        self::assertFileDoesNotExist($this->basePath . '/moved.txt');
    }

    public function test_list_directory_omits_symlinks(): void
    {
        $linkPath = $this->basePath . '/external-link.txt';

        if (!function_exists('symlink') || !@symlink($this->externalPath, $linkPath)) {
            self::markTestSkipped('Symlinks are not available in this environment.');
        }

        $filesystem = new MSTV_Filesystem($this->basePath);

        self::assertNotContains('external-link.txt', $filesystem->list_directory(''));
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if (in_array($item, ['.', '..'], true)) {
                continue;
            }

            $itemPath = $path . DIRECTORY_SEPARATOR . $item;

            if (is_link($itemPath) || is_file($itemPath)) {
                unlink($itemPath);
                continue;
            }

            $this->deleteDirectory($itemPath);
        }

        rmdir($path);
    }
}
