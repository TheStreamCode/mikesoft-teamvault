<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMAdminAppTest extends TestCase
{
    /**
     * The admin app is split across admin-app-core.js, admin-app-governance.js and
     * admin-app.js; assert against their concatenation so behavior checks are
     * independent of which module a helper currently lives in.
     */
    private function adminAppSource(): string
    {
        $files = glob(dirname(__DIR__) . '/assets/js/admin-app*.js') ?: [];
        sort($files);

        return implode("\n", array_map(
            static fn (string $file): string => (string) file_get_contents($file),
            $files
        ));
    }

    public function testApiUrlBuilderSupportsPlainPermalinkRestUrls(): void
    {
        $source = $this->adminAppSource();

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

    public function testResponsiveViewportHelpersUseSeparateSidebarAndDetailsBreakpoints(): void
    {
        $source = $this->adminAppSource();

        self::assertStringContainsString(
            'isSidebarDrawerViewport()',
            $source,
            'The folder sidebar drawer must keep its own 992px breakpoint helper.'
        );
        self::assertStringContainsString(
            'return window.innerWidth <= 992;',
            $source,
            'The folder sidebar drawer must remain limited to 992px and below.'
        );
        self::assertStringContainsString(
            'isDetailsDrawerViewport()',
            $source,
            'The details drawer must use its own wider breakpoint helper.'
        );
        self::assertStringContainsString(
            'return window.innerWidth <= 1200;',
            $source,
            'The details drawer must open at 1200px and below.'
        );
        self::assertStringNotContainsString(
            'isMobileViewport()',
            $source,
            'A single mobile viewport helper would couple the sidebar and details drawer breakpoints again.'
        );
    }

    public function testDetailsDrawerCssStartsAtTwelveHundredPixelsAndRespectsAdminBarOffsets(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__) . '/assets/css/admin.css');

        self::assertStringContainsString(
            '--pdm-admin-bar-offset: 32px;',
            $source,
            'Fixed admin drawers must start below the desktop WordPress admin bar.'
        );
        self::assertStringContainsString(
            '--pdm-admin-bar-offset: 46px;',
            $source,
            'Fixed admin drawers must use the taller mobile WordPress admin bar offset.'
        );
        self::assertStringContainsString(
            '@media (max-width: 1200px)',
            $source,
            'The details panel responsive threshold must remain at 1200px.'
        );
        self::assertStringContainsString(
            'top: var(--pdm-admin-bar-offset);',
            $source,
            'Off-canvas drawers must not cover the WordPress admin bar.'
        );
        self::assertStringContainsString(
            'max-height: calc(100vh - var(--pdm-admin-bar-offset));',
            $source,
            'Off-canvas drawers must size to the viewport below the WordPress admin bar.'
        );
        self::assertStringContainsString(
            'transform: translateX(100%);',
            $source,
            'The details panel must move off-canvas instead of shrinking at 1200px.'
        );
        self::assertStringNotContainsString(
            'width: 280px;',
            $source,
            'The 1200px details behavior must not leave a cramped inline details panel.'
        );
    }
}
