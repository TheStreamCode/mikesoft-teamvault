<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMReportsTest extends TestCase
{
    private function fakeWpdb(): object
    {
        return new class {
            public string $users = 'wp_users';
            public array $prepareCalls = [];

            public function get_blog_prefix($blogId): string
            {
                return 'wp_';
            }

            public function prepare($sql, ...$args)
            {
                if (count($args) === 1 && is_array($args[0])) {
                    $args = $args[0];
                }
                $this->prepareCalls[] = ['sql' => $sql, 'args' => $args];

                return $sql;
            }

            public function get_var($query)
            {
                return 0;
            }

            public function get_results($query)
            {
                return [];
            }
        };
    }

    public function test_find_filtered_uses_prepared_params_and_drops_invalid_action(): void
    {
        $wpdb = $this->fakeWpdb();
        $GLOBALS['wpdb'] = $wpdb;

        $repo = new MSTV_Repository_Logs();
        $repo->find_filtered([
            'user_id' => 7,
            'action' => 'DROP TABLE', // not in allowlist -> ignored
            'folder_id' => 3,
            'file_type' => 'PDF',
            'date_from' => '2026-01-01',
        ], 1, 50);

        $countCall = $wpdb->prepareCalls[0];
        self::assertStringContainsString('l.user_id = %d', $countCall['sql']);
        self::assertStringContainsString('f.folder_id = %d', $countCall['sql']);
        self::assertStringContainsString('f.extension = %s', $countCall['sql']);
        self::assertStringContainsString('l.created_at >= %s', $countCall['sql']);
        self::assertStringNotContainsString('DROP TABLE', $countCall['sql']);

        self::assertContains(7, $countCall['args']);
        self::assertContains(3, $countCall['args']);
        self::assertContains('pdf', $countCall['args']);          // lower-cased extension
        self::assertContains('2026-01-01 00:00:00', $countCall['args']); // expanded date bound
        self::assertNotContains('DROP TABLE', $countCall['args']);
    }

    public function test_aggregate_access_scopes_to_preview_and_download(): void
    {
        $wpdb = $this->fakeWpdb();
        $GLOBALS['wpdb'] = $wpdb;

        $repo = new MSTV_Repository_Logs();
        $repo->aggregate_access('user', []);

        $call = $wpdb->prepareCalls[0];
        self::assertStringContainsString('l.action IN', $call['sql']);
        self::assertContains('preview', $call['args']);
        self::assertContains('download', $call['args']);
    }
}
