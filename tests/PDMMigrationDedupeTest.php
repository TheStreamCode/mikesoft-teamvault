<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Minimal in-memory stand-in for $wpdb covering the queries used by the
 * uniqueness migration. The GROUP BY emulation mirrors the case-insensitive
 * collation used by WordPress tables.
 */
final class FakeMigrationWpdb
{
    /** @var array<int,object> */
    public array $folders = [];
    /** @var array<int,object> */
    public array $files = [];
    /** @var string[] */
    public array $queries = [];
    /** @var array<int,array{table: string, data: array, where: array}> */
    public array $updates = [];
    /** @var array<int,array{table: string, where: array}> */
    public array $deletes = [];
    public bool $hasParentKeyColumn = false;
    /** @var array<string,bool> */
    public array $indexes = [];

    public function prepare(string $query, ...$args): array
    {
        return ['query' => $query, 'args' => $args];
    }

    public function esc_like(string $text): string
    {
        return addcslashes($text, '_%\\');
    }

    public function get_results($query): array
    {
        [$sql, $args] = $this->split($query);

        if (str_starts_with($sql, 'DESCRIBE')) {
            return $this->hasParentKeyColumn ? [(object) ['Field' => 'parent_key']] : [];
        }

        if (str_contains($sql, 'GROUP BY parent_key, name')) {
            return $this->folderGroups();
        }

        if (str_contains($sql, 'WHERE parent_key = %d AND name = %s AND id <> %d')) {
            return $this->duplicateFolderRows((int) $args[0], (string) $args[1], (int) $args[2]);
        }

        if (str_contains($sql, 'WHERE relative_path = %s OR relative_path LIKE %s')) {
            return $this->rowsByPathPrefix($sql, (string) $args[0]);
        }

        if (str_contains($sql, 'FROM `wp_mstv_files`')) {
            return $this->fileRows();
        }

        if (str_contains($sql, 'FROM `wp_mstv_folders`')) {
            return $this->folderRows();
        }

        return [];
    }

    public function get_row($query)
    {
        [$sql, $args] = $this->split($query);

        if (str_contains($sql, 'WHERE id = %d')) {
            $id = (int) $args[0];
            $row = $this->folders[$id] ?? $this->files[$id] ?? null;

            return $row === null
                ? null
                : (object) ['id' => $id, 'relative_path' => (string) $row->relative_path];
        }

        return null;
    }

    public function get_var($query)
    {
        [$sql, $args] = $this->split($query);

        if (str_contains($sql, 'information_schema.statistics')) {
            return isset($this->indexes[(string) $args[1]]) ? 1 : 0;
        }

        if (str_contains($sql, 'LEFT(relative_path, 191)')) {
            return 0;
        }

        return 0;
    }

    public function query($query)
    {
        [$sql, $args] = $this->split($query);
        $this->queries[] = $sql;

        if (str_contains($sql, 'ADD COLUMN parent_key')) {
            $this->hasParentKeyColumn = true;

            return 1;
        }

        if (preg_match('/ADD UNIQUE KEY `([^`]+)`/', $sql, $matches) === 1) {
            $this->indexes[$matches[1]] = true;

            return 1;
        }

        if (str_starts_with($sql, 'UPDATE `wp_mstv_folders` SET parent_key')) {
            foreach ($this->folders as $row) {
                $row->parent_key = (int) ($row->parent_id ?? 0);
            }

            return count($this->folders);
        }

        return 1;
    }

    public function update($table, $data, $where, $format = null, $whereFormat = null): int
    {
        $this->updates[] = ['table' => $table, 'data' => $data, 'where' => $where];

        return $this->apply($table, $data, $where);
    }

    public function delete($table, $where, $format = null): int
    {
        $this->deletes[] = ['table' => $table, 'where' => $where];

        $isFolders = str_contains($table, 'folders');
        $removed = 0;

        foreach ($this->rowsFor($isFolders) as $id => $row) {
            if (!$this->matchesWhere($row, $where)) {
                continue;
            }

            if ($isFolders) {
                unset($this->folders[$id]);
            } else {
                unset($this->files[$id]);
            }

            $removed++;
        }

        return $removed;
    }

    private function apply(string $table, array $data, array $where): int
    {
        $changed = 0;

        foreach ($this->rowsFor(str_contains($table, 'folders')) as $row) {
            if (!$this->matchesWhere($row, $where)) {
                continue;
            }

            foreach ($data as $key => $value) {
                $row->{$key} = $value;
            }

            $changed++;
        }

        return $changed;
    }

    /**
     * @return array<int,object>
     */
    private function rowsFor(bool $isFolders): array
    {
        return $isFolders ? $this->folders : $this->files;
    }

    private function matchesWhere(object $row, array $where): bool
    {
        foreach ($where as $key => $value) {
            $current = $row->{$key} ?? null;

            if ($value === null) {
                if ($current !== null) {
                    return false;
                }

                continue;
            }

            if ((string) $current !== (string) $value) {
                return false;
            }
        }

        return true;
    }

    private function folderGroups(): array
    {
        $groups = [];

        foreach ($this->folders as $row) {
            $groups[((int) $row->parent_key) . '|' . mb_strtolower((string) $row->name)][] = $row;
        }

        $result = [];

        foreach ($groups as $rows) {
            if (count($rows) < 2) {
                continue;
            }

            $ids = array_map(static fn($row): int => (int) $row->id, $rows);
            $result[] = (object) [
                'parent_key' => (int) $rows[0]->parent_key,
                'name' => (string) $rows[0]->name,
                'keep_id' => min($ids),
                'total' => count($rows),
            ];
        }

        return $result;
    }

    private function duplicateFolderRows(int $parentKey, string $name, int $keepId): array
    {
        $rows = [];

        foreach ($this->folders as $row) {
            if ((int) $row->parent_key === $parentKey
                && mb_strtolower((string) $row->name) === mb_strtolower($name)
                && (int) $row->id !== $keepId
            ) {
                $rows[] = (object) ['id' => (int) $row->id, 'relative_path' => (string) $row->relative_path];
            }
        }

        usort($rows, static fn($a, $b): int => $a->id <=> $b->id);

        return $rows;
    }

    private function rowsByPathPrefix(string $sql, string $prefix): array
    {
        $isFolders = str_contains($sql, 'FROM `wp_mstv_folders`');
        $rows = [];

        foreach ($this->rowsFor($isFolders) as $row) {
            $path = (string) $row->relative_path;

            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                $rows[] = (object) ['id' => (int) $row->id, 'relative_path' => $path];
            }
        }

        return $rows;
    }

    private function folderRows(): array
    {
        $rows = [];

        foreach ($this->folders as $row) {
            $rows[] = (object) ['id' => (int) $row->id, 'relative_path' => (string) $row->relative_path];
        }

        return $rows;
    }

    private function fileRows(): array
    {
        $rows = [];

        foreach ($this->files as $row) {
            $rows[] = (object) ['id' => (int) $row->id, 'relative_path' => (string) $row->relative_path];
        }

        return $rows;
    }

    /**
     * @return array{0: string, 1: array<int,mixed>}
     */
    private function split($query): array
    {
        if (is_array($query)) {
            return [(string) $query['query'], $query['args']];
        }

        return [(string) $query, []];
    }
}

final class PDMMigrationDedupeTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['pdm_test_options'] = [];
    }

    public function test_uniqueness_migration_deduplicates_rows_and_adds_indexes(): void
    {
        $wpdb = $this->buildWpdb();

        $this->runMigration($wpdb);

        // Duplicate siblings merged onto the lowest id.
        self::assertArrayHasKey(1, $wpdb->folders);
        self::assertArrayNotHasKey(2, $wpdb->folders);
        self::assertArrayHasKey(3, $wpdb->folders);
        self::assertArrayNotHasKey(4, $wpdb->folders);
        self::assertSame(1, (int) $wpdb->folders[3]->parent_id);
        self::assertSame(1, (int) $wpdb->folders[3]->parent_key);

        // Files re-pointed and duplicate file rows removed.
        self::assertSame(1, (int) $wpdb->files[10]->folder_id);
        self::assertSame(3, (int) $wpdb->files[12]->folder_id);
        self::assertArrayNotHasKey(11, $wpdb->files);

        // Explicit ALTERs instead of relying on dbDelta for the unique keys.
        self::assertContains(
            'ALTER TABLE `wp_mstv_folders` ADD COLUMN parent_key BIGINT(20) UNSIGNED NOT NULL DEFAULT 0',
            $wpdb->queries
        );
        self::assertNotEmpty(array_filter(
            $wpdb->queries,
            static fn(string $sql): bool => str_contains($sql, 'ADD UNIQUE KEY `uq_mstv_folder_parent_name`')
        ));
        self::assertNotEmpty(array_filter(
            $wpdb->queries,
            static fn(string $sql): bool => str_contains($sql, 'ADD UNIQUE KEY `uq_mstv_file_relative_path`')
        ));

        // Per-folder rules of the duplicate folder are merged onto the survivor.
        self::assertNotEmpty(array_filter(
            $wpdb->queries,
            static fn(string $sql): bool => str_contains(
                $sql,
                'UPDATE IGNORE `wp_mstv_folder_permissions` SET folder_id = 1 WHERE folder_id = 2'
            )
        ));
        self::assertNotEmpty(array_filter(
            $wpdb->queries,
            static fn(string $sql): bool => str_contains(
                $sql,
                'DELETE FROM `wp_mstv_folder_permissions` WHERE folder_id = 2'
            )
        ));

        self::assertTrue(MSTV_Activator::schema_migration_applied('folder_parent_key'));
        self::assertTrue(MSTV_Activator::schema_migration_applied('folder_unique'));
        self::assertTrue(MSTV_Activator::schema_migration_applied('file_unique'));
    }

    public function test_uniqueness_migration_is_skipped_once_applied(): void
    {
        $wpdb = $this->buildWpdb();
        $this->runMigration($wpdb);

        $queriesBefore = count($wpdb->queries);
        $updatesBefore = count($wpdb->updates);
        $deletesBefore = count($wpdb->deletes);

        $this->runMigration($wpdb);

        self::assertSame($queriesBefore, count($wpdb->queries));
        self::assertSame($updatesBefore, count($wpdb->updates));
        self::assertSame($deletesBefore, count($wpdb->deletes));
    }

    public function test_duplicate_subtree_paths_are_rewritten_onto_the_surviving_path(): void
    {
        $wpdb = new FakeMigrationWpdb();
        $wpdb->hasParentKeyColumn = true;
        $wpdb->folders = [
            1 => (object) ['id' => 1, 'parent_id' => null, 'parent_key' => 0, 'name' => 'Docs', 'relative_path' => 'Docs'],
            2 => (object) ['id' => 2, 'parent_id' => null, 'parent_key' => 0, 'name' => 'docs', 'relative_path' => 'docs'],
            3 => (object) ['id' => 3, 'parent_id' => 2, 'parent_key' => 2, 'name' => 'Inner', 'relative_path' => 'docs/inner'],
        ];
        $wpdb->files = [
            10 => (object) ['id' => 10, 'folder_id' => 2, 'relative_path' => 'docs/inner/a.pdf'],
        ];

        $this->runMigration($wpdb);

        self::assertArrayNotHasKey(2, $wpdb->folders);
        self::assertSame('Docs/inner', (string) $wpdb->folders[3]->relative_path);
        self::assertSame(1, (int) $wpdb->folders[3]->parent_id);
        self::assertSame('Docs/inner/a.pdf', (string) $wpdb->files[10]->relative_path);
        self::assertSame(1, (int) $wpdb->files[10]->folder_id);
    }

    public function test_replace_path_prefix_rewrites_only_the_subtree(): void
    {
        self::assertSame('docs/inner/a.pdf', MSTV_Activator::replace_path_prefix('docs/inner/a.pdf', 'docs', 'docs'));
        self::assertSame('archive/a.pdf', MSTV_Activator::replace_path_prefix('docs/a.pdf', 'docs', 'archive'));
        self::assertSame('archive', MSTV_Activator::replace_path_prefix('docs', 'docs', 'archive'));
        self::assertSame('docs-other/a.pdf', MSTV_Activator::replace_path_prefix('docs-other/a.pdf', 'docs', 'archive'));
        self::assertSame('other/docs/a.pdf', MSTV_Activator::replace_path_prefix('other/docs/a.pdf', 'docs', 'archive'));
    }

    public function test_duplicate_file_groups_keep_the_lowest_id(): void
    {
        $groups = MSTV_Activator::duplicate_file_groups([
            (object) ['id' => 5, 'relative_path' => 'a.pdf'],
            (object) ['id' => 2, 'relative_path' => 'a.pdf'],
            (object) ['id' => 9, 'relative_path' => 'b.pdf'],
        ]);

        self::assertCount(1, $groups);
        self::assertSame(2, $groups[0]['keep_id']);
        self::assertSame([5], $groups[0]['duplicate_ids']);
    }

    private function runMigration(FakeMigrationWpdb $wpdb): void
    {
        $GLOBALS['wpdb'] = $wpdb;

        $method = new ReflectionMethod(MSTV_Activator::class, 'ensure_unique_indexes');
        $method->setAccessible(true);
        $method->invoke(null, 'wp_mstv_folders', 'wp_mstv_files', 'wp_mstv_folder_permissions');
    }

    private function buildWpdb(): FakeMigrationWpdb
    {
        $wpdb = new FakeMigrationWpdb();
        $wpdb->folders = [
            1 => (object) ['id' => 1, 'parent_id' => null, 'parent_key' => 0, 'name' => 'Docs', 'relative_path' => 'docs'],
            2 => (object) ['id' => 2, 'parent_id' => null, 'parent_key' => 0, 'name' => 'Docs', 'relative_path' => 'docs'],
            3 => (object) ['id' => 3, 'parent_id' => 2, 'parent_key' => 2, 'name' => 'Inner', 'relative_path' => 'docs/inner'],
            4 => (object) ['id' => 4, 'parent_id' => 2, 'parent_key' => 2, 'name' => 'Inner', 'relative_path' => 'docs/inner'],
        ];
        $wpdb->files = [
            10 => (object) ['id' => 10, 'folder_id' => 2, 'relative_path' => 'docs/inner/a.pdf'],
            11 => (object) ['id' => 11, 'folder_id' => 2, 'relative_path' => 'docs/inner/a.pdf'],
            12 => (object) ['id' => 12, 'folder_id' => 4, 'relative_path' => 'docs/inner/b.pdf'],
        ];

        return $wpdb;
    }
}
