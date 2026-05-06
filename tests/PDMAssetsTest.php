<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMAssetsTest extends TestCase
{
    public function test_effective_client_upload_limit_uses_lowest_positive_limit(): void
    {
        $assets = new MSTV_Assets(new MSTV_Settings());
        $method = new ReflectionMethod(MSTV_Assets::class, 'get_effective_client_upload_limit');
        $method->setAccessible(true);

        self::assertSame(5 * 1024 * 1024, $method->invoke($assets, 50 * 1024 * 1024, '8M', '5M'));
    }
}
