<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMReleaseMetadataTest extends TestCase
{
    private const RELEASE_VERSION = '3.1.1';
    private const TESTED_UP_TO = '7.0';
    private const CONTACT_EMAIL = 'teamvault@mikesoft.it';

    public function test_release_metadata_matches_wordpress_7_release(): void
    {
        $pluginFile = (string) file_get_contents(__DIR__ . '/../mikesoft-teamvault.php');
        $readme = (string) file_get_contents(__DIR__ . '/../readme.txt');
        $githubReadme = (string) file_get_contents(__DIR__ . '/../README.md');

        self::assertMatchesRegularExpression('/^\s\* Version:\s*' . preg_quote(self::RELEASE_VERSION, '/') . '\s*$/m', $pluginFile);
        self::assertStringContainsString("define('MSTV_VERSION', '" . self::RELEASE_VERSION . "');", $pluginFile);
        self::assertMatchesRegularExpression('/^Stable tag:\s*' . preg_quote(self::RELEASE_VERSION, '/') . '\s*$/m', $readme);
        self::assertMatchesRegularExpression('/^Tested up to:\s*' . preg_quote(self::TESTED_UP_TO, '/') . '\s*$/m', $readme);
        self::assertStringContainsString('Current plugin version: `' . self::RELEASE_VERSION . '`.', $githubReadme);
    }

    public function test_public_contact_metadata_uses_teamvault_mailbox(): void
    {
        $readme = (string) file_get_contents(__DIR__ . '/../readme.txt');
        $githubReadme = (string) file_get_contents(__DIR__ . '/../README.md');
        $securityPolicy = (string) file_get_contents(__DIR__ . '/../SECURITY.md');

        self::assertStringContainsString(self::CONTACT_EMAIL, $readme);
        self::assertStringContainsString(self::CONTACT_EMAIL, $githubReadme);
        self::assertStringContainsString(self::CONTACT_EMAIL, $securityPolicy);
    }
}
