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
            5,
            substr_count($source, 'this.bindModalConfirm(async'),
            'All five plain modals must route their confirm handlers through the guard.'
        );
    }

    public function testUserSearchesDiscardStaleResponses(): void
    {
        $source = $this->adminAppSource();

        self::assertSame(
            5,
            substr_count($source, 'if (seq !== this._userSearchSeq) return;'),
            'Every debounced user search must drop responses that are no longer the latest request.'
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
