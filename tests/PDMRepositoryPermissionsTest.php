<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMRepositoryPermissionsTest extends TestCase
{
    public function test_set_rules_rolls_back_when_an_insert_fails(): void
    {
        $wpdb = new class {
            public array $queries = [];
            public int $insertCalls = 0;

            public function get_blog_prefix($blogId): string
            {
                return 'wp_';
            }

            public function query(string $query): int
            {
                $this->queries[] = $query;
                return 0;
            }

            public function delete($table, $where, $format): int
            {
                return 1;
            }

            public function insert($table, $data, $format): int|false
            {
                $this->insertCalls++;
                return $this->insertCalls === 2 ? false : 1;
            }
        };
        $GLOBALS['wpdb'] = $wpdb;
        $repo = new MSTV_Repository_Permissions();

        $saved = $repo->set_rules(10, [[
            'principal_type' => 'user',
            'principal_id' => 7,
            'actions' => [MSTV_Permissions::ACTION_VIEW, MSTV_Permissions::ACTION_DOWNLOAD],
        ]], 1);

        self::assertFalse($saved);
        self::assertContains('START TRANSACTION', $wpdb->queries);
        self::assertContains('ROLLBACK', $wpdb->queries);
        self::assertNotContains('COMMIT', $wpdb->queries);
    }
}
