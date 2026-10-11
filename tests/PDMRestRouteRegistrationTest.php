<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMRestRouteRegistrationTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['pdm_test_options'] = [
            'mstv_use_user_whitelist' => false,
            'mstv_allowed_users' => [],
        ];
    }

    protected function tearDown(): void
    {
        $GLOBALS['pdm_test_rest_routes'] = [];
        $GLOBALS['pdm_test_options'] = [];
    }

    public function test_every_registered_route_has_a_permission_callback(): void
    {
        $map = $this->permission_map_for($this->register_file_routes() + $this->register_governance_routes());

        self::assertNotEmpty($map);

        foreach ($map as $path => $byMethod) {
            foreach ($byMethod as $method => $callback) {
                self::assertIsArray(
                    $callback,
                    "Route {$path} [{$method}] has no permission callback."
                );
                self::assertInstanceOf(
                    MSTV_Auth::class,
                    $callback[0],
                    "Route {$path} [{$method}] permission callback is not an MSTV_Auth method."
                );
                self::assertIsString($callback[1]);
            }
        }
    }

    public function test_file_routes_match_the_expected_permission_map(): void
    {
        $expected = [
            'mstv/v1/browser' => ['GET' => 'verify_read_request'],
            'mstv/v1/folders' => ['POST' => 'verify_write_request'],
            'mstv/v1/folders/(?P<id>\d+)' => [
                'PATCH' => 'verify_write_request',
                'DELETE' => 'verify_delete_request',
            ],
            'mstv/v1/folders/(?P<id>\d+)/move' => ['POST' => 'verify_write_request'],
            'mstv/v1/files/upload' => ['POST' => 'verify_write_request'],
            'mstv/v1/files/(?P<id>\d+)' => [
                'PATCH' => 'verify_write_request',
                'DELETE' => 'verify_delete_request',
            ],
            'mstv/v1/files/(?P<id>\d+)/move' => ['POST' => 'verify_write_request'],
            'mstv/v1/files/(?P<id>\d+)/download' => ['GET' => 'verify_read_request'],
            'mstv/v1/files/(?P<id>\d+)/preview' => ['GET' => 'verify_read_request'],
            'mstv/v1/search' => ['GET' => 'verify_read_request'],
            'mstv/v1/settings' => [
                'GET' => 'verify_admin_request',
                'POST' => 'verify_admin_request',
            ],
            'mstv/v1/logs' => ['GET' => 'verify_admin_request'],
            'mstv/v1/users/search' => ['GET' => 'verify_admin_request'],
            'mstv/v1/export' => ['GET' => 'verify_read_request'],
            'mstv/v1/folders/(?P<id>\d+)/export' => ['GET' => 'verify_read_request'],
        ];

        $map = $this->permission_map_for($this->register_file_routes());

        self::assertSame($expected, $this->callback_methods($map));
    }

    public function test_governance_routes_match_the_expected_permission_map(): void
    {
        $expected = [
            'mstv/v1/groups' => [
                'GET' => 'verify_admin_request',
                'POST' => 'verify_admin_request',
            ],
            'mstv/v1/groups/(?P<id>\d+)' => [
                'PATCH' => 'verify_admin_request',
                'DELETE' => 'verify_admin_request',
            ],
            'mstv/v1/groups/(?P<id>\d+)/members' => ['POST' => 'verify_admin_request'],
            'mstv/v1/settings/notifications' => [
                'GET' => 'verify_admin_request',
                'POST' => 'verify_admin_request',
            ],
            'mstv/v1/reports/access' => ['GET' => 'verify_admin_request'],
            'mstv/v1/quotas' => [
                'GET' => 'verify_admin_request',
                'PUT' => 'verify_admin_request',
            ],
            'mstv/v1/folders/(?P<id>\d+)/permissions' => [
                'GET' => 'verify_write_request',
                'PUT' => 'verify_write_request',
                'DELETE' => 'verify_write_request',
            ],
        ];

        $map = $this->permission_map_for($this->register_governance_routes());

        self::assertSame($expected, $this->callback_methods($map));
    }

    private function register_file_routes(): array
    {
        $GLOBALS['pdm_test_rest_routes'] = [];

        $controller = new MSTV_REST_Controller(
            new MSTV_Settings(),
            $this->createMock(MSTV_Auth::class),
            $this->getMockBuilder(MSTV_Storage::class)->disableOriginalConstructor()->getMock(),
            $this->createMock(MSTV_Validator::class),
            $this->createMock(MSTV_Repository_Folders::class),
            $this->getMockBuilder(MSTV_Repository_Files::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(MSTV_Download::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(MSTV_Preview::class)->disableOriginalConstructor()->getMock(),
            $this->createMock(MSTV_Logger::class)
        );
        $controller->register_routes();

        return $GLOBALS['pdm_test_rest_routes'];
    }

    private function register_governance_routes(): array
    {
        $GLOBALS['pdm_test_rest_routes'] = [];

        $controller = new MSTV_REST_Governance_Controller(
            $this->createMock(MSTV_Auth::class),
            $this->getMockBuilder(MSTV_Repository_Groups::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(MSTV_Repository_Permissions::class)->disableOriginalConstructor()->getMock(),
            $this->createMock(MSTV_Repository_Folders::class),
            $this->createMock(MSTV_Permissions::class),
            null
        );
        $controller->register_routes();

        return $GLOBALS['pdm_test_rest_routes'];
    }

    /**
     * @param array<string, array<int, array|string>> $routes
     * @return array<string, array<string, array|int|string|null>>
     */
    private function permission_map_for(array $routes): array
    {
        $map = [];

        foreach ($routes as $path => $registrations) {
            foreach ($registrations as $args) {
                if (!is_array($args)) {
                    continue;
                }

                $entries = isset($args['methods']) ? [$args] : array_values(array_filter($args, 'is_array'));

                foreach ($entries as $entry) {
                    $methods = $entry['methods'] ?? [];
                    $methods = is_array($methods) ? $methods : [$methods];

                    foreach ($methods as $method) {
                        $map[$path][(string) $method] = $entry['permission_callback'] ?? null;
                    }
                }
            }
        }

        return $map;
    }

    /**
     * @param array<string, array<string, array<int, mixed>>> $map
     * @return array<string, array<string, string|null>>
     */
    private function callback_methods(array $map): array
    {
        $methods = [];

        foreach ($map as $path => $byMethod) {
            foreach ($byMethod as $method => $callback) {
                $methods[$path][$method] = is_array($callback) ? $callback[1] : null;
            }
        }

        return $methods;
    }
}
