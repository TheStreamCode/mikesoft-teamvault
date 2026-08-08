<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMReleaseMetadataTest extends TestCase
{
    private const RELEASE_VERSION = '3.2.6';
    private const RELEASE_DATE = '2026-08-08';
    private const TESTED_UP_TO = '7.0';
    private const CONTACT_EMAIL = 'teamvault@mikesoft.it';
    private const PLUGIN_AUTHOR = 'Mikesoft';

    public function test_release_metadata_matches_wordpress_7_release(): void
    {
        $pluginFile = (string) file_get_contents(__DIR__ . '/../mikesoft-teamvault.php');
        $readme = (string) file_get_contents(__DIR__ . '/../readme.txt');
        $githubReadme = (string) file_get_contents(__DIR__ . '/../README.md');
        $changelog = (string) file_get_contents(__DIR__ . '/../changelog.txt');
        $securityReview = (string) file_get_contents(__DIR__ . '/../docs/maintainer/security-review.md');

        self::assertMatchesRegularExpression('/^\s\* Version:\s*' . preg_quote(self::RELEASE_VERSION, '/') . '\s*$/m', $pluginFile);
        self::assertMatchesRegularExpression('/^\s\* Author:\s*' . preg_quote(self::PLUGIN_AUTHOR, '/') . '\s*$/m', $pluginFile);
        self::assertStringContainsString("define('MSTV_VERSION', '" . self::RELEASE_VERSION . "');", $pluginFile);
        self::assertMatchesRegularExpression('/^Stable tag:\s*' . preg_quote(self::RELEASE_VERSION, '/') . '\s*$/m', $readme);
        self::assertMatchesRegularExpression('/^Tested up to:\s*' . preg_quote(self::TESTED_UP_TO, '/') . '\s*$/m', $readme);
        self::assertStringContainsString('Current plugin version: `' . self::RELEASE_VERSION . '`.', $githubReadme);
        self::assertMatchesRegularExpression('/^## ' . preg_quote(self::RELEASE_VERSION, '/') . ' - ' . preg_quote(self::RELEASE_DATE, '/') . '$/m', $changelog);
        self::assertStringContainsString('# Security Review — ' . self::RELEASE_DATE, $securityReview);
        self::assertMatchesRegularExpression(
            '/== Upgrade Notice ==\R\R= ' . preg_quote(self::RELEASE_VERSION, '/') . ' =\R/',
            $readme
        );

        $localizedVersions = [
            'README.it.md' => 'Versione attuale del plugin: `' . self::RELEASE_VERSION . '`.',
            'README.fr.md' => 'Version actuelle du plugin : `' . self::RELEASE_VERSION . '`.',
            'README.es.md' => 'Versión actual del plugin: `' . self::RELEASE_VERSION . '`.',
            'README.de.md' => 'Aktuelle Plugin-Version: `' . self::RELEASE_VERSION . '`.',
        ];

        foreach ($localizedVersions as $file => $expectedLine) {
            self::assertStringContainsString(
                $expectedLine,
                (string) file_get_contents(__DIR__ . '/../' . $file),
                $file . ' must expose the current release version.'
            );
        }
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

    public function test_repository_presentation_metadata_is_release_ready(): void
    {
        $root = dirname(__DIR__);
        $citation = (string) file_get_contents($root . '/CITATION.cff');
        $dependabot = (string) file_get_contents($root . '/.github/dependabot.yml');
        $securityPolicy = (string) file_get_contents($root . '/SECURITY.md');
        $socialPreview = $root . '/.github/social-preview.png';

        self::assertStringContainsString('version: "' . self::RELEASE_VERSION . '"', $citation);
        self::assertStringContainsString('date-released: "' . self::RELEASE_DATE . '"', $citation);
        self::assertStringContainsString('license: GPL-2.0-or-later', $citation);
        self::assertStringContainsString('package-ecosystem: composer', $dependabot);
        self::assertStringContainsString('package-ecosystem: github-actions', $dependabot);
        self::assertStringContainsString('/security/advisories/new', $securityPolicy);
        self::assertFileExists($socialPreview);

        $dimensions = getimagesize($socialPreview);

        self::assertIsArray($dimensions);
        self::assertSame(1280, $dimensions[0]);
        self::assertSame(640, $dimensions[1]);
    }
}
