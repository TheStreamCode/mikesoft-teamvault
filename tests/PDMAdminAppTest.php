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

    public function testHtmlEscaperAlsoEncodesQuotedAttributeDelimiters(): void
    {
        $source = $this->adminAppSource();

        self::assertStringContainsString("'\"': '&quot;'", $source);
        self::assertStringContainsString("\"'\": '&#039;'", $source);
        self::assertStringContainsString('replace(/[&<>"\']/', $source);
    }

    public function testUserAvatarInitialIsEscapedBeforeHtmlInsertion(): void
    {
        $source = $this->adminAppSource();

        self::assertStringContainsString(
            '${this.escapeHtml(user.display_name.charAt(0).toUpperCase())}',
            $source
        );
        self::assertStringNotContainsString(
            '>${user.display_name.charAt(0).toUpperCase()}</div>',
            $source
        );
    }

    public function testFileExtensionIsEscapedBeforeHtmlInsertion(): void
    {
        $source = $this->adminAppSource();

        self::assertStringContainsString(
            "\${this.escapeHtml(String(files.extension || '').toUpperCase())}",
            $source,
            'The details panel must escape the stored file extension before HTML interpolation.'
        );
        self::assertStringNotContainsString(
            '${files.extension.toUpperCase()}',
            $source,
            'The stored file extension must never reach an HTML sink unescaped.'
        );
    }

    public function testDownloadAndPreviewUrlsAreRestrictedToSameOriginHttp(): void
    {
        $source = $this->adminAppSource();

        self::assertStringContainsString('normalizeSameOriginUrl(value)', $source);
        self::assertStringContainsString("!['http:', 'https:'].includes(url.protocol)", $source);
        self::assertStringContainsString('url.origin !== window.location.origin', $source);
        self::assertStringContainsString('link.href = downloadUrl;', $source);
        self::assertStringContainsString('iframe.src = previewUrl;', $source);
        self::assertStringContainsString('img.src = previewUrl;', $source);
        self::assertStringContainsString('form.action = actionUrl;', $source);
        self::assertStringNotContainsString('link.href = files.download_url;', $source);
        self::assertStringNotContainsString('iframe.src = files.preview_url;', $source);
        self::assertStringNotContainsString('form.action = mstvConfig.actionUrl;', $source);
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
            'return window.innerWidth <= 1360;',
            $source,
            'The details drawer must account for the WordPress desktop admin menu.'
        );
        self::assertStringNotContainsString(
            'isMobileViewport()',
            $source,
            'A single mobile viewport helper would couple the sidebar and details drawer breakpoints again.'
        );
    }

    public function testDetailsDrawerCssAccountsForTheAdminMenuAndRespectsAdminBarOffsets(): void
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
            '@media (max-width: 1360px)',
            $source,
            'The details panel threshold must preserve 1200px of app space beside the WordPress admin menu.'
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
            'The details panel must move off-canvas instead of covering toolbar actions.'
        );
        self::assertStringNotContainsString(
            'width: 280px;',
            $source,
            'The responsive details behavior must not leave a cramped inline details panel.'
        );
    }
}
