<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMRepositoryLogsSecurityTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_CLIENT_IP'], $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
    }

    public function test_create_prefers_remote_addr_over_spoofable_proxy_headers(): void
    {
        $_SERVER['HTTP_CLIENT_IP'] = '198.51.100.10';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.11';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.20';

        $wpdb = new class {
            public int $insert_id = 123;
            public array $inserted = [];

            public function get_blog_prefix($blogId): string
            {
                return 'wp_';
            }

            public function insert($table, $data, $formats): bool
            {
                $this->inserted = [
                    'table' => $table,
                    'data' => $data,
                    'formats' => $formats,
                ];

                return true;
            }
        };

        $GLOBALS['wpdb'] = $wpdb;

        $repo = new MSTV_Repository_Logs();
        $repo->create([
            'user_id' => 7,
            'action' => 'download',
            'target_type' => 'file',
            'target_id' => 44,
        ]);

        self::assertSame('203.0.113.20', $wpdb->inserted['data']['ip_address']);
    }
}
