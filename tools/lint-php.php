<?php
/**
 * Recursively lint repository PHP files while skipping generated dependencies
 * and local-only work directories.
 *
 * @package MikesoftTeamVault
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$skipDirectories = array(
    '.git',
    '.phpunit.cache',
    '.worktrees',
    'mikesoft-teamvault',
    'mikesoft-teamvault-svn',
    'node_modules',
    'vendor',
);

$files = array();
$iterator = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        static function (SplFileInfo $current) use ($skipDirectories): bool {
            if (! $current->isDir()) {
                return true;
            }

            return ! in_array($current->getFilename(), $skipDirectories, true);
        }
    )
);

foreach ($iterator as $file) {
    if ($file instanceof SplFileInfo && $file->isFile() && 'php' === strtolower($file->getExtension())) {
        $files[] = $file->getPathname();
    }
}

sort($files);

$failures = 0;

foreach ($files as $file) {
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file);
    $output = array();
    $exitCode = 0;

    exec($command, $output, $exitCode);

    if (0 !== $exitCode) {
        ++$failures;
        echo implode(PHP_EOL, $output) . PHP_EOL;
    }
}

if (0 !== $failures) {
    fwrite(STDERR, sprintf("PHP lint failed for %d file(s).%s", $failures, PHP_EOL));
    exit(1);
}

printf("PHP lint passed for %d file(s).%s", count($files), PHP_EOL);
