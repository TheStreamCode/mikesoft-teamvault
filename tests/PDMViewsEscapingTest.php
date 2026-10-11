<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMViewsEscapingTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['pdm_test_options'] = [];
        $GLOBALS['pdm_test_transients'] = [];
        $GLOBALS['pdm_test_users'] = [];
        $GLOBALS['pdm_test_current_user_id'] = 5;
        $GLOBALS['pdm_test_current_user_caps'] = [];
        $GLOBALS['pdm_test_current_user_can'] = false;

        // Mirror WordPress core bootstrapping: the settings view reads the
        // global $wp_roles instance directly via MSTV_Capabilities.
        wp_roles();
    }

    protected function tearDown(): void
    {
        $GLOBALS['pdm_test_options'] = [];
        $GLOBALS['pdm_test_transients'] = [];
        $GLOBALS['pdm_test_users'] = [];
        $GLOBALS['pdm_test_current_user_id'] = 0;
    }

    private function render_view(string $file, array $variables = []): string
    {
        extract($variables, EXTR_OVERWRITE);

        ob_start();
        include MSTV_PLUGIN_DIR . 'admin/views/' . $file;

        return (string) ob_get_clean();
    }

    public function test_logs_page_escapes_log_row_values(): void
    {
        $log = (object) [
            'id' => 1,
            'user_id' => 5,
            'user_login' => '<script>alert(1)</script>',
            'action' => '"><script>alert(2)</script>',
            'target_type' => 'file',
            'target_id' => 7,
            'context' => '{"filename":"<b>Report</b>"}',
            'ip_address' => '1.2.3.4" onmouseover="alert(3)',
            'created_at' => '2026-01-01 10:00:00',
        ];

        $html = $this->render_view('logs-page.php', [
            'mstv_logs' => [$log],
            'mstv_pagination' => [
                'page' => 1,
                'per_page' => 50,
                'total_items' => 1,
                'total_pages' => 1,
                'has_prev' => false,
                'has_next' => false,
                'from_item' => 1,
                'to_item' => 1,
            ],
            'mstv_current_page' => 1,
            'mstv_selected_per_page' => 50,
            'mstv_allowed_per_page' => [25, 50, 100, 200],
        ]);

        self::assertStringNotContainsString('<script>alert(1)', $html);
        self::assertStringNotContainsString('<script>alert(2)', $html);
        self::assertStringNotContainsString('" onmouseover="', $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringContainsString('Showing 1-1 of 1 entries', $html);
    }

    public function test_logs_page_renders_empty_state_without_log_rows(): void
    {
        $html = $this->render_view('logs-page.php', [
            'mstv_logs' => [],
            'mstv_pagination' => [
                'page' => 1,
                'per_page' => 50,
                'total_items' => 0,
                'total_pages' => 0,
                'has_prev' => false,
                'has_next' => false,
                'from_item' => 0,
                'to_item' => 0,
            ],
            'mstv_current_page' => 1,
            'mstv_selected_per_page' => 50,
            'mstv_allowed_per_page' => [25, 50, 100, 200],
        ]);

        self::assertStringContainsString('pdm-logs-wrapper', $html);
        self::assertStringContainsString('No logs available', $html);
    }

    public function test_settings_page_escapes_error_notices_and_user_tags(): void
    {
        $GLOBALS['pdm_test_options'] = [
            'mstv_use_user_whitelist' => true,
            'mstv_allowed_users' => [7],
        ];
        $GLOBALS['pdm_test_transients'] = [
            'mstv_settings_error_5' => '<script>alert(1)</script>',
        ];
        $GLOBALS['pdm_test_users'] = [
            7 => new FakePDMViewUser(7, '<script>alert(2)</script>', '"><svg onload=alert(3)>'),
        ];

        $html = $this->render_view('settings-page.php', [
            'settings' => new MSTV_Settings(),
            'orphaned_files_count' => 0,
            'cleanup_result' => false,
            'reindex_result' => false,
        ]);

        self::assertStringNotContainsString('<script>alert(1)', $html);
        self::assertStringNotContainsString('<script>alert(2)', $html);
        self::assertStringNotContainsString('<svg onload=', $html);
        self::assertStringContainsString('&lt;script&gt;alert(2)&lt;/script&gt;', $html);
        self::assertSame('', (string) get_transient('mstv_settings_error_5'));
    }

    public function test_file_manager_page_escapes_extension_allowlist(): void
    {
        $GLOBALS['pdm_test_options'] = [
            'mstv_allowed_extensions' => 'pdf,"><script>alert(1)</script>',
        ];

        $html = $this->render_view('file-manager-page.php', [
            'settings' => new MSTV_Settings(),
        ]);

        self::assertStringNotContainsString('"><script>alert(1)', $html);
        self::assertStringContainsString('&quot;&gt;&lt;script&gt;', $html);
    }

    public function test_static_governance_views_render_without_variables(): void
    {
        foreach (['groups-page.php', 'quotas-page.php', 'notifications-page.php', 'reports-page.php'] as $file) {
            $html = $this->render_view($file);

            self::assertNotSame('', $html, "View {$file} rendered no output.");
        }
    }
}
