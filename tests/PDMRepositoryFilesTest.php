<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMRepositoryFilesTest extends TestCase
{
    public function test_update_relative_paths_for_folder_rename_updates_descendant_files(): void
    {
        $wpdb = new class {
            public array $updates = [];

            public function get_blog_prefix($blogId): string
            {
                return 'wp_';
            }

            public function prepare($query, ...$args): array
            {
                return ['query' => $query, 'args' => $args];
            }

            public function get_results($prepared): array
            {
                return [
                    (object) ['id' => 10, 'relative_path' => 'clients/acme/quote.pdf'],
                    (object) ['id' => 11, 'relative_path' => 'clients/acme/contracts/master.pdf'],
                ];
            }

            public function update($table, $data, $where): int
            {
                $this->updates[] = [
                    'table' => $table,
                    'data' => $data,
                    'where' => $where,
                ];

                return 1;
            }
        };

        $GLOBALS['wpdb'] = $wpdb;

        $repo = new PDM_Repository_Files();
        $updated = $repo->update_relative_paths_for_folder_rename('clients/acme', 'clients/team-acme');

        self::assertSame(2, $updated);
        self::assertSame([
            [
                'table' => 'wp_pdm_files',
                'data' => ['relative_path' => 'clients/team-acme/quote.pdf'],
                'where' => ['id' => 10],
            ],
            [
                'table' => 'wp_pdm_files',
                'data' => ['relative_path' => 'clients/team-acme/contracts/master.pdf'],
                'where' => ['id' => 11],
            ],
        ], $wpdb->updates);
    }
}
