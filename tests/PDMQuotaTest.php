<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FakeQuotaFilesRepo extends MSTV_Repository_Files
{
    public array $byUser = [];
    public int $usersTotal = 0;

    public function __construct(array $byUser = [], int $usersTotal = 0)
    {
        $this->byUser = $byUser;
        $this->usersTotal = $usersTotal;
    }

    public function get_total_size_by_user(int $userId): int
    {
        return $this->byUser[$userId] ?? 0;
    }

    public function get_total_size_by_users(array $userIds): int
    {
        return $this->usersTotal;
    }
}

final class FakeQuotaGroupsRepo extends MSTV_Repository_Groups
{
    public array $groupsByUser = [];
    public array $membersByGroup = [];

    public function __construct(array $groupsByUser = [], array $membersByGroup = [])
    {
        $this->groupsByUser = $groupsByUser;
        $this->membersByGroup = $membersByGroup;
    }

    public function find_groups_for_user(int $userId): array
    {
        return $this->groupsByUser[$userId] ?? [];
    }

    public function find_members(int $groupId): array
    {
        return $this->membersByGroup[$groupId] ?? [];
    }
}

final class PDMQuotaTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['pdm_test_options'] = ['mstv_quotas_enabled' => true, 'mstv_quotas' => []];
        $GLOBALS['pdm_test_users'] = [];
    }

    private function quota(array $byUser = [], int $usersTotal = 0, array $groupsByUser = [], array $membersByGroup = []): MSTV_Quota
    {
        return new MSTV_Quota(
            new MSTV_Settings(),
            new FakeQuotaFilesRepo($byUser, $usersTotal),
            new FakeQuotaGroupsRepo($groupsByUser, $membersByGroup)
        );
    }

    public function test_disabled_quotas_allow_any_upload(): void
    {
        $GLOBALS['pdm_test_options']['mstv_quotas_enabled'] = false;
        $GLOBALS['pdm_test_options']['mstv_quotas'] = ['user:5' => 10];

        self::assertNull($this->quota(['5' => 9])->check_upload(5, 1000));
    }

    public function test_no_quota_row_means_unlimited(): void
    {
        $GLOBALS['pdm_test_options']['mstv_quotas'] = [];

        self::assertNull($this->quota(['5' => 999999])->check_upload(5, 999999));
    }

    public function test_upload_below_user_quota_is_allowed(): void
    {
        $GLOBALS['pdm_test_options']['mstv_quotas'] = ['user:5' => 1000];

        self::assertNull($this->quota([5 => 500])->check_upload(5, 400));
    }

    public function test_upload_exceeding_user_quota_is_blocked(): void
    {
        $GLOBALS['pdm_test_options']['mstv_quotas'] = ['user:5' => 1000];

        $result = $this->quota([5 => 900])->check_upload(5, 200);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('quota_exceeded', $result->get_error_code());
        self::assertSame(413, $result->data['status']);
    }

    public function test_group_quota_is_enforced_across_members(): void
    {
        $GLOBALS['pdm_test_options']['mstv_quotas'] = ['group:3' => 1000];

        // User 5 belongs to group 3; group members already used 950 bytes.
        $quota = $this->quota([], 950, [5 => [3]], [3 => [5, 6]]);

        self::assertInstanceOf(WP_Error::class, $quota->check_upload(5, 100));
    }

    public function test_administrator_is_never_blocked(): void
    {
        $GLOBALS['pdm_test_options']['mstv_quotas'] = ['user:1' => 10];
        $GLOBALS['pdm_test_users'][1] = new FakePDMUser(1, ['manage_options' => true]);

        self::assertNull($this->quota([1 => 9999])->check_upload(1, 9999));
    }
}
