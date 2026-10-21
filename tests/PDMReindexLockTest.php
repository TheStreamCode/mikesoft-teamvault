<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMReindexLockTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['pdm_test_options'] = [];
    }

    public function test_first_claim_wins_and_a_second_request_is_rejected(): void
    {
        $first = new MSTV_Reindex_Lock(1);
        $second = new MSTV_Reindex_Lock(1);

        self::assertTrue($first->acquire());
        self::assertFalse($second->acquire());
        self::assertSame('mstv_auto_reindex_claim_1', $first->option_name());
        self::assertNotFalse(get_option('mstv_auto_reindex_claim_1'));
    }

    public function test_released_claim_can_be_acquired_again(): void
    {
        $lock = new MSTV_Reindex_Lock(1);

        self::assertTrue($lock->acquire());
        $lock->release();

        self::assertFalse(get_option('mstv_auto_reindex_claim_1'));
        self::assertTrue((new MSTV_Reindex_Lock(1))->acquire());
    }

    public function test_fresh_claim_blocks_concurrent_requests(): void
    {
        $GLOBALS['pdm_test_options']['mstv_auto_reindex_claim_1'] = (string) time();

        self::assertFalse((new MSTV_Reindex_Lock(1))->acquire());
    }

    public function test_stale_claim_is_taken_over(): void
    {
        $GLOBALS['pdm_test_options']['mstv_auto_reindex_claim_1'] = (string) (time() - MSTV_Reindex_Lock::TTL - 1);

        self::assertTrue((new MSTV_Reindex_Lock(1))->acquire());
    }

    public function test_claims_are_scoped_per_site(): void
    {
        self::assertTrue((new MSTV_Reindex_Lock(1))->acquire());
        self::assertTrue((new MSTV_Reindex_Lock(2))->acquire());
    }
}
