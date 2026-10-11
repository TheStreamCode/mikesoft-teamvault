<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMI18nRuntimeTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['pdm_test_options'] = [
            'mstv_interface_language' => 'it',
        ];
        $GLOBALS['pdm_test_actions'] = [];
    }

    protected function tearDown(): void
    {
        $GLOBALS['pdm_test_options'] = [];
        $GLOBALS['pdm_test_actions'] = [];
    }

    public function test_gettext_filter_maps_string_for_configured_language(): void
    {
        $i18n = new MSTV_I18n();
        $i18n->init();

        self::assertSame(
            'Notifiche',
            apply_filters('gettext', 'Notifications', 'Notifications', 'mikesoft-teamvault')
        );
    }

    public function test_gettext_filter_leaves_english_unchanged(): void
    {
        $GLOBALS['pdm_test_options']['mstv_interface_language'] = 'en';

        $i18n = new MSTV_I18n();
        $i18n->init();

        self::assertSame(
            'Notifications',
            apply_filters('gettext', 'Notifications', 'Notifications', 'mikesoft-teamvault')
        );
    }

    public function test_gettext_filter_ignores_other_domains(): void
    {
        $i18n = new MSTV_I18n();
        $i18n->init();

        self::assertSame(
            'Notifications',
            apply_filters('gettext', 'Notifications', 'Notifications', 'other-domain')
        );
    }

    public function test_gettext_filter_respects_installed_language_packs(): void
    {
        $i18n = new MSTV_I18n();
        $i18n->init();

        // A GlotPress translation must win over the bundled fallback map.
        self::assertSame(
            'Pacchetti di lingua',
            apply_filters('gettext', 'Pacchetti di lingua', 'Notifications', 'mikesoft-teamvault')
        );
    }

    public function test_auto_language_falls_back_to_english_for_unsupported_locale(): void
    {
        $GLOBALS['pdm_test_options']['mstv_interface_language'] = 'auto';

        self::assertSame('en', MSTV_I18n::get_language());
    }

    public function test_sanitize_language_rejects_unsupported_values(): void
    {
        self::assertSame('auto', MSTV_I18n::sanitize_language('auto'));
        self::assertSame('it', MSTV_I18n::sanitize_language('it'));
        self::assertSame('en', MSTV_I18n::sanitize_language('xx'));
    }
}
