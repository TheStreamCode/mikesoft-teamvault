<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMAdminAppTest extends TestCase
{
    public function testApiUrlBuilderSupportsPlainPermalinkRestUrls(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__) . '/assets/js/admin-app.js');

        self::assertStringContainsString(
            "base.includes('?')",
            $source,
            'The admin API URL builder must detect query-style REST bases generated when pretty permalinks are disabled.'
        );
        self::assertStringContainsString(
            'return `${base}/${path.substring(0, qIdx)}&${path.substring(qIdx + 1)}`;',
            $source,
            'Endpoint query arguments must be appended with & after the rest_route query variable.'
        );
    }
}
