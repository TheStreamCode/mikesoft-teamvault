<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Fake repositories that override only the data-access methods the permission engine
 * uses, so MSTV_Permissions can be exercised without a database.
 */
final class FakePermFolderRepo extends MSTV_Repository_Folders
{
    /** @var array<int,?int> id => parent_id */
    public array $parents = [];

    public function __construct(array $parents = [])
    {
        // Skip the parent constructor: it only computes a table name from $wpdb.
        $this->parents = $parents;
    }

    public function find(int $id): ?object
    {
        if (!array_key_exists($id, $this->parents)) {
            return null;
        }

        return (object) ['id' => $id, 'parent_id' => $this->parents[$id]];
    }
}

final class FakePermRulesRepo extends MSTV_Repository_Permissions
{
    /** @var array<int,array<array{principal_type:string,principal_id:int,action:string}>> */
    public array $rulesByFolder = [];

    public function __construct(array $rulesByFolder = [])
    {
        $this->rulesByFolder = $rulesByFolder;
    }

    public function find_rules_for_folder(int $folderId): array
    {
        return array_map(
            static fn($r) => (object) $r,
            $this->rulesByFolder[$folderId] ?? []
        );
    }
}

final class FakePermGroupsRepo extends MSTV_Repository_Groups
{
    /** @var array<int,int[]> userId => groupIds */
    public array $groupsByUser = [];

    public function __construct(array $groupsByUser = [])
    {
        $this->groupsByUser = $groupsByUser;
    }

    public function find_groups_for_user(int $userId): array
    {
        return $this->groupsByUser[$userId] ?? [];
    }
}

final class PDMPermissionsTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['pdm_test_options'] = [
            'mstv_use_user_whitelist' => false,
            'mstv_allowed_users' => [],
        ];
        $GLOBALS['pdm_test_users'] = [];
    }

    private function user(int $id, array $caps): void
    {
        $GLOBALS['pdm_test_users'][$id] = new FakePDMUser($id, $caps);
    }

    private function rule(string $type, int $id, string $action): array
    {
        return ['principal_type' => $type, 'principal_id' => $id, 'action' => $action];
    }

    private function engine(array $parents, array $rules, array $groups = []): MSTV_Permissions
    {
        return new MSTV_Permissions(
            new FakePermFolderRepo($parents),
            new FakePermGroupsRepo($groups),
            new FakePermRulesRepo($rules),
            new MSTV_Settings()
        );
    }

    public function test_no_rules_anywhere_grants_full_access_to_capable_user(): void
    {
        $this->user(5, [MSTV_Capabilities::CAP_MANAGE => true]);
        $engine = $this->engine([10 => null], []);

        foreach (MSTV_Permissions::ACTIONS as $action) {
            self::assertTrue($engine->user_can(5, 10, $action), "free fallback should grant {$action}");
        }
    }

    public function test_user_without_capability_is_denied(): void
    {
        $this->user(5, []);
        $engine = $this->engine([10 => null], []);

        self::assertFalse($engine->user_can(5, 10, MSTV_Permissions::ACTION_VIEW));
    }

    public function test_whitelist_floor_denies_non_whitelisted_user(): void
    {
        $GLOBALS['pdm_test_options']['mstv_use_user_whitelist'] = true;
        $GLOBALS['pdm_test_options']['mstv_allowed_users'] = [99];
        $this->user(5, [MSTV_Capabilities::CAP_MANAGE => true]);

        $engine = $this->engine([10 => null], []);

        self::assertFalse($engine->user_can(5, 10, MSTV_Permissions::ACTION_VIEW));
    }

    public function test_administrator_bypasses_restrictive_rules(): void
    {
        $this->user(1, [MSTV_Capabilities::CAP_MANAGE => true, 'manage_options' => true]);
        // A rule exists that grants nothing to user 1.
        $engine = $this->engine([10 => null], [10 => [$this->rule('user', 999, 'view')]]);

        foreach (MSTV_Permissions::ACTIONS as $action) {
            self::assertTrue($engine->user_can(1, 10, $action));
        }
    }

    public function test_explicit_rule_grants_only_listed_actions(): void
    {
        $this->user(5, [MSTV_Capabilities::CAP_MANAGE => true]);
        $engine = $this->engine([10 => null], [
            10 => [
                $this->rule('user', 5, 'view'),
                $this->rule('user', 5, 'download'),
            ],
        ]);

        self::assertTrue($engine->user_can(5, 10, MSTV_Permissions::ACTION_VIEW));
        self::assertTrue($engine->user_can(5, 10, MSTV_Permissions::ACTION_DOWNLOAD));
        self::assertFalse($engine->user_can(5, 10, MSTV_Permissions::ACTION_UPLOAD));
        self::assertFalse($engine->user_can(5, 10, MSTV_Permissions::ACTION_DELETE));
        self::assertFalse($engine->user_can(5, 10, MSTV_Permissions::ACTION_MANAGE));
    }

    public function test_preview_only_grants_view_without_download(): void
    {
        $this->user(5, [MSTV_Capabilities::CAP_MANAGE => true]);
        $engine = $this->engine([10 => null], [10 => [$this->rule('user', 5, 'view')]]);

        self::assertTrue($engine->user_can(5, 10, MSTV_Permissions::ACTION_VIEW));
        self::assertFalse($engine->user_can(5, 10, MSTV_Permissions::ACTION_DOWNLOAD));
    }

    public function test_child_inherits_nearest_ancestor_rules(): void
    {
        $this->user(5, [MSTV_Capabilities::CAP_MANAGE => true]);
        // folder 20 child of 10; rules only on 10.
        $engine = $this->engine([10 => null, 20 => 10], [10 => [$this->rule('user', 5, 'download')]]);

        self::assertTrue($engine->user_can(5, 20, MSTV_Permissions::ACTION_DOWNLOAD));
        self::assertFalse($engine->user_can(5, 20, MSTV_Permissions::ACTION_DELETE));
    }

    public function test_explicit_child_rule_overrides_inherited_parent(): void
    {
        $this->user(5, [MSTV_Capabilities::CAP_MANAGE => true]);
        // Parent grants download; child explicitly grants only view -> download must be denied on child.
        $engine = $this->engine(
            [10 => null, 20 => 10],
            [
                10 => [$this->rule('user', 5, 'download')],
                20 => [$this->rule('user', 5, 'view')],
            ]
        );

        self::assertTrue($engine->user_can(5, 20, MSTV_Permissions::ACTION_VIEW));
        self::assertFalse($engine->user_can(5, 20, MSTV_Permissions::ACTION_DOWNLOAD));
        // Parent itself keeps its own grant.
        self::assertTrue($engine->user_can(5, 10, MSTV_Permissions::ACTION_DOWNLOAD));
    }

    public function test_group_rule_grants_action_to_member(): void
    {
        $this->user(5, [MSTV_Capabilities::CAP_MANAGE => true]);
        $engine = $this->engine(
            [10 => null],
            [10 => [$this->rule('group', 3, 'upload')]],
            [5 => [3]]
        );

        self::assertTrue($engine->user_can(5, 10, MSTV_Permissions::ACTION_UPLOAD));
    }

    public function test_group_rule_does_not_grant_to_non_member(): void
    {
        $this->user(5, [MSTV_Capabilities::CAP_MANAGE => true]);
        $engine = $this->engine(
            [10 => null],
            [10 => [$this->rule('group', 3, 'upload')]],
            [5 => [7]]
        );

        self::assertFalse($engine->user_can(5, 10, MSTV_Permissions::ACTION_UPLOAD));
    }

    public function test_root_rule_applies_to_root_level_items(): void
    {
        $this->user(5, [MSTV_Capabilities::CAP_MANAGE => true]);
        // Rule on virtual root (folder 0) restricts root-level (folderId null) to view only.
        $engine = $this->engine([], [0 => [$this->rule('user', 5, 'view')]]);

        self::assertTrue($engine->user_can(5, null, MSTV_Permissions::ACTION_VIEW));
        self::assertFalse($engine->user_can(5, null, MSTV_Permissions::ACTION_UPLOAD));
    }
}
