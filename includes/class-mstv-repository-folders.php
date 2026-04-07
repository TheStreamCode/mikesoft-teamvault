<?php

defined('ABSPATH') || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom plugin tables require direct queries.

class MSTV_Repository_Folders
{
    private string $table;
    private ?array $allFoldersCache = null;
    private ?array $hierarchyCache = null;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->get_blog_prefix(get_current_blog_id()) . 'mstv_folders';
    }

    public function find(int $id): ?object
    {
        global $wpdb;

        $result = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id)
        );

        return $result;
    }

    public function find_by_parent(?int $parentId): array
    {
        global $wpdb;

        if (null === $parentId) {
            return $wpdb->get_results(
                "SELECT * FROM {$this->table} WHERE parent_id IS NULL ORDER BY name ASC"
            );
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE parent_id = %d ORDER BY name ASC",
                $parentId
            )
        );
    }

    public function find_all(): array
    {
        global $wpdb;

        if ($this->allFoldersCache !== null) {
            return $this->allFoldersCache;
        }

        $this->allFoldersCache = $wpdb->get_results(
            "SELECT * FROM {$this->table} ORDER BY name ASC"
        );

        return $this->allFoldersCache;
    }

    public function find_all_with_hierarchy(): array
    {
        if ($this->hierarchyCache !== null) {
            return $this->hierarchyCache;
        }

        $folders = $this->find_all();
        $this->hierarchyCache = $this->build_tree($folders);

        return $this->hierarchyCache;
    }

    public function create(array $data): int
    {
        global $wpdb;

        $wpdb->insert($this->table, [
            'parent_id' => $data['parent_id'] ?? null,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'relative_path' => $data['relative_path'],
            'created_by' => $data['created_by'],
        ], ['%d', '%s', '%s', '%s', '%d']);

        $this->flush_cache();

        return (int) $wpdb->insert_id;
    }

    public function update(int $id, array $data): bool
    {
        global $wpdb;

        $result = $wpdb->update(
            $this->table,
            $data,
            ['id' => $id]
        );

        if ($result !== false) {
            $this->flush_cache();
        }

        return $result !== false;
    }

    public function delete(int $id): bool
    {
        global $wpdb;

        $result = $wpdb->delete($this->table, ['id' => $id], ['%d']);

        if ($result !== false) {
            $this->flush_cache();
        }

        return $result !== false;
    }

    public function exists_by_name_and_parent(string $name, ?int $parentId): bool
    {
        global $wpdb;

        if (null === $parentId) {
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table} WHERE name = %s AND parent_id IS NULL",
                    $name
                )
            );
        } else {
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table} WHERE name = %s AND parent_id = %d",
                    $name,
                    $parentId
                )
            );
        }

        return (int) $count > 0;
    }

    public function count_children(int $folderId): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE parent_id = %d",
                $folderId
            )
        );
    }

    public function get_breadcrumb_data(int $folderId): array
    {
        $folders = $this->find_all();
        return MSTV_Helpers::build_breadcrumb($folders, $folderId);
    }

    private function build_tree(array $folders, ?int $parentId = null): array
    {
        $tree = [];

        foreach ($folders as $folder) {
            $folderParentId = $folder->parent_id !== null ? (int) $folder->parent_id : null;

            if ($folderParentId === $parentId) {
                $children = $this->build_tree($folders, (int) $folder->id);
                $tree[] = [
                    'id' => (int) $folder->id,
                    'name' => $folder->name,
                    'slug' => $folder->slug,
                    'parent_id' => $folderParentId,
                    'children' => $children,
                    'has_children' => !empty($children),
                ];
            }
        }

        usort($tree, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        return $tree;
    }

    public function update_relative_paths(int $folderId, string $newRelativePath): void
    {
        $children = $this->find_by_parent($folderId);
        foreach ($children as $child) {
            $childNewPath = rtrim($newRelativePath, '/\\') . '/' . $child->slug;
            $this->update((int) $child->id, ['relative_path' => $childNewPath]);
            $this->update_relative_paths((int) $child->id, $childNewPath);
        }
    }

    private function flush_cache(): void
    {
        $this->allFoldersCache = null;
        $this->hierarchyCache = null;
    }
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
