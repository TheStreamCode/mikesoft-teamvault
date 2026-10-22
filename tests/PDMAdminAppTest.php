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

    public function testAdminAppRoutesSharedHelpersThroughThePureHelpersModule(): void
    {
        $source = $this->adminAppSource();

        // Behavioral coverage lives in tests/pdm-helpers.test.mjs; this guards
        // the wiring so the app cannot silently reimplement the helpers.
        self::assertStringContainsString(
            'window.PDMHelpers.buildApiUrl(endpoint, mstvConfig.restUrl)',
            $source
        );
        self::assertStringContainsString(
            'window.PDMHelpers.escapeHtml(text)',
            $source
        );
        self::assertStringContainsString(
            'window.PDMHelpers.normalizeSameOriginUrl(value, window.location.href)',
            $source
        );
    }

    public function testHelpersModuleEscapesBothQuoteTypesForAttributeContexts(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../assets/js/admin-app-helpers.js');

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

    public function testDownloadAndPreviewSinksGoThroughTheSameOriginNormalizer(): void
    {
        $source = $this->adminAppSource();

        // Normalizer behavior is covered by tests/pdm-helpers.test.mjs; this
        // guards that every browser destination still passes through it.
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

    public function testTeamVaultCssRemovesAdminChromeFromEveryPluginScreen(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__) . '/assets/css/admin.css');

        self::assertStringContainsString(
            'body.mstv-admin-page #wpfooter',
            $source,
            'Every TeamVault screen must hide the WordPress admin footer band.'
        );
        self::assertStringContainsString(
            'body.mstv-admin-page #wpbody-content',
            $source,
            'Every TeamVault screen must remove the admin bottom padding under its panels.'
        );
        self::assertStringContainsString(
            'body.mstv-admin-page .pdm-wrapper',
            $source,
            'Panel wrappers on TeamVault screens must drop the 20px gap above and below the panel.'
        );
        self::assertStringContainsString(
            'height: calc(100vh - var(--pdm-admin-bar-offset));',
            $source,
            'The File Manager wrapper must fill exactly the viewport below the admin bar.'
        );
        self::assertStringContainsString(
            'body.toplevel_page_mikesoft-teamvault .pdm-sidebar',
            $source,
            'The File Manager sidebar must stretch to the app height so the storage panel reaches the bottom edge.'
        );
    }

    public function testDesignSystemUnifiesEveryPluginScreen(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__) . '/assets/css/admin.css');

        self::assertStringContainsString(
            'body.mstv-admin-page .pdm-settings-wrapper,',
            $css,
            'Settings, Activity Log and governance screens must share the same page canvas.'
        );
        self::assertStringContainsString(
            'body.mstv-admin-page .pdm-logs-wrapper,',
            $css,
            'The Activity Log must use the shared page canvas.'
        );
        self::assertStringContainsString(
            'body.mstv-admin-page .pdm-governance-wrap > *',
            $css,
            'Governance screens must share the constrained content width.'
        );
        self::assertStringContainsString(
            'body.mstv-admin-page .mstv-governance-footer',
            $css,
            'Governance save bars must share the sticky action pattern.'
        );
        self::assertStringContainsString(
            'body.mstv-admin-page .pdm-settings-actions',
            $css,
            'Settings must keep its save action visible while scrolling.'
        );
        self::assertStringContainsString(
            "setAttribute('aria-expanded'",
            $this->adminAppSource(),
            'The filter popover toggle must expose its expanded state.'
        );
    }

    public function testDeleteAndMutationErrorsSurfaceServerMessages(): void
    {
        $source = $this->adminAppSource();

        self::assertStringContainsString(
            "apiErrorMessage(error, 'deleteError')",
            $source,
            'Delete failures must surface the server-provided message instead of a generic error.'
        );
        self::assertStringContainsString(
            'isApiError: true',
            $source,
            'API errors must be marked so network failures can fall back to the generic message.'
        );
    }

    public function testAuditCsvFilterTravelsUnderADedicatedKey(): void
    {
        $source = $this->adminAppSource();

        self::assertStringContainsString(
            "params.set('log_action', f.action);",
            $source,
            'The report action filter must not overwrite the admin-post routing parameter.'
        );
        self::assertStringNotContainsString(
            "params.set('action', f.action);",
            $source,
            'The audit CSV export must not reuse the routing parameter name for filters.'
        );
    }

    public function testModalConfirmButtonsGuardAgainstDoubleSubmits(): void
    {
        $source = $this->adminAppSource();

        self::assertStringContainsString(
            'bindModalConfirm(handler)',
            $source,
            'Modal confirm buttons must share a double-submit guard.'
        );
        self::assertSame(
            3,
            substr_count($source, 'this.bindModalConfirm(async'),
            'The text-prompt factory plus the two move modals must route their confirm handlers through the guard.'
        );
        self::assertStringContainsString(
            'showTextPromptModal({ title, initial',
            $source,
            'Create-folder and rename modals must share the text prompt factory.'
        );
    }

    public function testUserSearchesDiscardStaleResponses(): void
    {
        $source = $this->adminAppSource();

        // All user searches share one widget; the sequence guard must live in
        // the widget and every site must route through it.
        self::assertStringContainsString(
            'bindUserSearch({ input, results, render, renderEmpty = null, onPick, onShown = null })',
            $source,
            'The shared user-search widget must carry the stale-response guard.'
        );
        self::assertSame(
            5,
            substr_count($source, 'this.bindUserSearch({'),
            'All five user-search sites must route through the shared widget.'
        );
        self::assertSame(
            1,
            substr_count($source, 'users/search?q='),
            'Only the shared widget may query the users/search endpoint.'
        );
    }

    public function testExpiredSessionErrorsSuggestReloadingThePage(): void
    {
        $source = $this->adminAppSource();

        self::assertStringContainsString(
            "error.code === 'rest_cookie_invalid_nonce'",
            $source,
            'An expired REST nonce must surface the dedicated session-expired message.'
        );
    }
}
