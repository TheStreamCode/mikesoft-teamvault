<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMGovernancePermissionsTest extends TestCase
{
    public function test_default_access_open_flag_is_true_when_rules_exist_but_root_has_none(): void
    {
        $this->set_current_user(admin: true);
        $controller = $this->buildController(hasAnyRules: true, hasRootRule: false);

        $response = $controller->get_folder_permissions(new WP_REST_Request(['id' => 0]));

        self::assertInstanceOf(WP_REST_Response::class, $response);
        self::assertTrue($response->data['data']['default_access_open']);
    }

    public function test_default_access_open_flag_is_false_when_a_root_rule_exists(): void
    {
        $this->set_current_user(admin: true);
        $controller = $this->buildController(hasAnyRules: true, hasRootRule: true);

        $response = $controller->get_folder_permissions(new WP_REST_Request(['id' => 0]));

        self::assertInstanceOf(WP_REST_Response::class, $response);
        self::assertFalse($response->data['data']['default_access_open']);
    }

    public function test_default_access_open_flag_is_false_when_no_rules_exist_at_all(): void
    {
        $this->set_current_user(admin: true);
        $controller = $this->buildController(hasAnyRules: false, hasRootRule: false);

        $response = $controller->get_folder_permissions(new WP_REST_Request(['id' => 0]));

        self::assertInstanceOf(WP_REST_Response::class, $response);
        self::assertFalse($response->data['data']['default_access_open']);
    }

    public function test_rule_management_is_denied_to_non_admins_without_an_explicit_manage_rule(): void
    {
        $this->set_current_user(admin: false);
        $controller = $this->buildRuleController(ruleManage: false);

        $result = $controller->get_folder_permissions(new WP_REST_Request(['id' => 0]));

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('mstv_forbidden', $result->get_error_code());
        self::assertSame(403, $result->data['status']);
    }

    public function test_rule_management_is_allowed_with_an_explicit_manage_rule(): void
    {
        $this->set_current_user(admin: false);
        $controller = $this->buildRuleController(ruleManage: true);

        $response = $controller->get_folder_permissions(new WP_REST_Request(['id' => 0]));

        self::assertInstanceOf(WP_REST_Response::class, $response);
        self::assertFalse($response->data['data']['default_access_open']);
    }

    public function test_rule_management_is_always_allowed_to_administrators(): void
    {
        $this->set_current_user(admin: true);
        $controller = $this->buildRuleController(ruleManage: false);

        $response = $controller->get_folder_permissions(new WP_REST_Request(['id' => 0]));

        self::assertInstanceOf(WP_REST_Response::class, $response);
    }

    private function set_current_user(bool $admin): void
    {
        $caps = $admin
            ? [MSTV_Capabilities::CAP_MANAGE => true, 'manage_options' => true]
            : [MSTV_Capabilities::CAP_MANAGE => true];

        $GLOBALS['pdm_test_users'] = [7 => new FakePDMUser(7, $caps)];
        $GLOBALS['pdm_test_current_user_id'] = 7;
    }

    private function buildRuleController(bool $ruleManage): MSTV_REST_Governance_Controller
    {
        $permissions = $this->getMockBuilder(MSTV_Permissions::class)->disableOriginalConstructor()->getMock();
        $permissions->method('current_user_can')->willReturn(true);
        $permissions->method('has_rule_based_manage')->willReturn($ruleManage);

        $permissionsRepo = $this->getMockBuilder(MSTV_Repository_Permissions::class)->disableOriginalConstructor()->getMock();
        $permissionsRepo->method('find_rules_for_folder')->willReturn([]);
        $permissionsRepo->method('has_any_rules')->willReturn(false);
        $permissionsRepo->method('has_any_rule')->willReturn(false);

        $groupsRepo = $this->getMockBuilder(MSTV_Repository_Groups::class)->disableOriginalConstructor()->getMock();
        $groupsRepo->method('find_all_groups')->willReturn([]);

        return new MSTV_REST_Governance_Controller(
            $this->createMock(MSTV_Auth::class),
            $groupsRepo,
            $permissionsRepo,
            $this->getMockBuilder(MSTV_Repository_Folders::class)->disableOriginalConstructor()->getMock(),
            $permissions
        );
    }

    private function buildController(bool $hasAnyRules, bool $hasRootRule): MSTV_REST_Governance_Controller
    {
        $permissions = $this->getMockBuilder(MSTV_Permissions::class)->disableOriginalConstructor()->getMock();
        $permissions->method('current_user_can')->willReturn(true);

        $permissionsRepo = $this->getMockBuilder(MSTV_Repository_Permissions::class)->disableOriginalConstructor()->getMock();
        $permissionsRepo->method('find_rules_for_folder')->willReturn([]);
        $permissionsRepo->method('has_any_rules')->willReturn($hasAnyRules);
        $permissionsRepo->method('has_any_rule')->willReturn($hasRootRule);

        $groupsRepo = $this->getMockBuilder(MSTV_Repository_Groups::class)->disableOriginalConstructor()->getMock();
        $groupsRepo->method('find_all_groups')->willReturn([]);

        return new MSTV_REST_Governance_Controller(
            $this->createMock(MSTV_Auth::class),
            $groupsRepo,
            $permissionsRepo,
            $this->getMockBuilder(MSTV_Repository_Folders::class)->disableOriginalConstructor()->getMock(),
            $permissions
        );
    }
}
