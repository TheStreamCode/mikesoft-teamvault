<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

if (!defined('WP_UNINSTALL_PLUGIN')) {
    define('WP_UNINSTALL_PLUGIN', true);
}

require_once dirname(__DIR__) . '/uninstall.php';

final class PDMUninstallSecurityTest extends TestCase
{
    private string $rootPath;

    protected function setUp(): void
    {
        $this->rootPath = sys_get_temp_dir() . '/mstv-uninstall-security-' . bin2hex(random_bytes(6));
        mkdir($this->rootPath, 0777, true);

        $GLOBALS['wp_filesystem'] = new class {
            public function rmdir($path, $recursive = false): bool
            {
                return @rmdir($path);
            }
        };
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wp_filesystem']);
        $this->deleteDirectory($this->rootPath);
    }

    public function test_recursive_delete_does_not_follow_directory_symlinks(): void
    {
        $storagePath = $this->rootPath . '/storage';
        $externalPath = $this->rootPath . '/external';
        mkdir($storagePath, 0777, true);
        mkdir($externalPath, 0777, true);
        file_put_contents($storagePath . '/inside.txt', 'inside');
        file_put_contents($externalPath . '/outside.txt', 'outside');

        $linkPath = $storagePath . '/external-link';
        if (!function_exists('symlink') || !@symlink($externalPath, $linkPath)) {
            self::markTestSkipped('Symlinks are not available in this environment.');
        }

        mstv_recursive_delete($storagePath);

        self::assertFileExists($externalPath . '/outside.txt');
        self::assertFileDoesNotExist($storagePath . '/inside.txt');
    }

    public function test_uninstall_path_boundary_rejects_paths_outside_root(): void
    {
        $storagePath = $this->rootPath . '/storage';
        $externalPath = $this->rootPath . '/external';
        mkdir($storagePath, 0777, true);
        mkdir($externalPath, 0777, true);
        file_put_contents($storagePath . '/inside.txt', 'inside');
        file_put_contents($externalPath . '/outside.txt', 'outside');

        self::assertTrue(mstv_uninstall_path_within_root($storagePath . '/inside.txt', $storagePath));
        self::assertFalse(mstv_uninstall_path_within_root($externalPath . '/outside.txt', $storagePath));
        self::assertTrue(mstv_uninstall_entry_within_root($storagePath . '/missing-link', $storagePath));
        self::assertFalse(mstv_uninstall_entry_within_root($externalPath . '/missing-link', $storagePath));
    }

    public function test_recursive_delete_removes_regular_storage_tree(): void
    {
        $storagePath = $this->rootPath . '/storage';
        mkdir($storagePath . '/nested', 0777, true);
        file_put_contents($storagePath . '/nested/file.txt', 'inside');

        mstv_recursive_delete($storagePath);

        self::assertDirectoryDoesNotExist($storagePath);
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path) || is_link($path)) {
            if (file_exists($path) || is_link($path)) {
                @unlink($path);
            }
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

            $this->deleteDirectory($path . DIRECTORY_SEPARATOR . $item);
        }

        @rmdir($path);
    }
}
