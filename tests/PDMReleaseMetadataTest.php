<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMReleaseMetadataTest extends TestCase
{
    private const RELEASE_VERSION = '3.4.0';
    private const RELEASE_DATE = '2026-10-21';
    private const TESTED_UP_TO = '7.1';
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
        self::assertStringContainsString(
            'https://img.shields.io/wordpress/plugin/v/mikesoft-teamvault',
            $githubReadme
        );
        self::assertStringNotContainsString('Current plugin version:', $githubReadme);
        self::assertMatchesRegularExpression('/^## ' . preg_quote(self::RELEASE_VERSION, '/') . ' - ' . preg_quote(self::RELEASE_DATE, '/') . '$/m', $changelog);
        self::assertStringContainsString('# Security Review — ' . self::RELEASE_DATE, $securityReview);
        self::assertMatchesRegularExpression(
            '/== Upgrade Notice ==\R\R= ' . preg_quote(self::RELEASE_VERSION, '/') . ' =\R/',
            $readme
        );

        $localizedReadmes = [
            'README.it.md',
            'README.fr.md',
            'README.es.md',
            'README.de.md',
        ];

        foreach ($localizedReadmes as $file) {
            self::assertStringContainsString(
                '](README.md)',
                (string) file_get_contents(__DIR__ . '/../' . $file),
                $file . ' must link back to the English README in its language switcher.'
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
        $securityPolicy = (string) file_get_contents($root . '/SECURITY.md');
        $socialPreview = $root . '/.github/social-preview.png';

        self::assertStringContainsString('version: "' . self::RELEASE_VERSION . '"', $citation);
        self::assertStringContainsString('date-released: "' . self::RELEASE_DATE . '"', $citation);
        self::assertStringContainsString('license: GPL-2.0-or-later', $citation);
        self::assertFileExists($root . '/.github/dependabot.yml');
        self::assertFileExists($root . '/.github/CODEOWNERS');

        $dependabot = (string) file_get_contents($root . '/.github/dependabot.yml');

        self::assertStringContainsString('package-ecosystem: "composer"', $dependabot);
        self::assertStringContainsString('package-ecosystem: "github-actions"', $dependabot);
        self::assertStringContainsString('/security/advisories/new', $securityPolicy);
        self::assertFileExists($socialPreview);

        $dimensions = getimagesize($socialPreview);

        self::assertIsArray($dimensions);
        self::assertSame(1280, $dimensions[0]);
        self::assertSame(640, $dimensions[1]);
    }
}
