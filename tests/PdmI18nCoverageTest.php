<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PdmI18nCoverageTest extends TestCase
{
    /** Language maps that must each fully cover the plugin's translatable strings. */
    private const LANGUAGE_CONSTANTS = ['ITALIAN_MAP', 'FRENCH_MAP', 'SPANISH_MAP', 'GERMAN_MAP'];

    public function testEveryLanguageMapCoversEveryPluginTranslationString(): void
    {
        $usedStrings = $this->collectUsedTranslationStrings();

        foreach (self::LANGUAGE_CONSTANTS as $const) {
            $translatedStrings = $this->getMapKeys($const);
            $missingStrings = array_values(array_diff($usedStrings, $translatedStrings));
            sort($missingStrings);

            self::assertSame(
                [],
                $missingStrings,
                "Missing {$const} translations:\n" . implode("\n", $missingStrings)
            );
        }
    }

    public function testNoLanguageMapContainsUnusedTranslationStrings(): void
    {
        $usedStrings = $this->collectUsedTranslationStrings();

        foreach (self::LANGUAGE_CONSTANTS as $const) {
            $translatedStrings = $this->getMapKeys($const);
            $unusedStrings = array_values(array_diff($translatedStrings, $usedStrings));
            sort($unusedStrings);

            self::assertSame(
                [],
                $unusedStrings,
                "Unused {$const} translations:\n" . implode("\n", $unusedStrings)
            );
        }
    }

    public function testAllLanguageMapsShareTheSameKeySet(): void
    {
        $reference = $this->getMapKeys('ITALIAN_MAP');
        sort($reference);

        foreach (['FRENCH_MAP', 'SPANISH_MAP', 'GERMAN_MAP'] as $const) {
            $keys = $this->getMapKeys($const);
            sort($keys);

            self::assertSame(
                $reference,
                $keys,
                "{$const} does not cover the same source strings as ITALIAN_MAP."
            );
        }
    }

    public function testNoLanguageMapContainsDuplicateKeys(): void
    {
        foreach (self::LANGUAGE_CONSTANTS as $const) {
            $keys = $this->getMapKeysFromSource($const);
            $duplicates = array_values(array_unique(array_diff_assoc($keys, array_unique($keys))));
            sort($duplicates);

            self::assertSame(
                [],
                $duplicates,
                "Duplicate {$const} keys:\n" . implode("\n", $duplicates)
            );
        }
    }

    /**
     * @return string[]
     */
    private function collectUsedTranslationStrings(): array
    {
        $root = dirname(__DIR__);
        $files = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                static function (SplFileInfo $current): bool {
                    if (! $current->isDir()) {
                        return true;
                    }

                    return ! in_array($current->getFilename(), ['.git', '.phpunit.cache', 'tests', 'vendor'], true);
                }
            )
        );

        $strings = [];
        $pattern = "/(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\\s*\\(\\s*'((?:\\\\'|[^'])*)'\\s*,\\s*'mikesoft-teamvault'/s";

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || 'php' !== strtolower($file->getExtension())) {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());
            preg_match_all($pattern, $contents, $matches);

            foreach ($matches[1] as $match) {
                $strings[] = str_replace("\\'", "'", $match);
            }
        }

        $strings = array_values(array_unique($strings));
        sort($strings);

        return $strings;
    }

    /**
     * @return string[]
     */
    private function getMapKeys(string $constant): array
    {
        $reflectionConstant = (new ReflectionClass(MSTV_I18n::class))->getReflectionConstant($constant);

        self::assertNotFalse($reflectionConstant, "Missing constant {$constant} on MSTV_I18n.");

        $keys = array_keys($reflectionConstant->getValue());
        sort($keys);

        return $keys;
    }

    /**
     * @return string[]
     */
    private function getMapKeysFromSource(string $constant): array
    {
        $contents = (string) file_get_contents(dirname(__DIR__) . '/includes/class-mstv-i18n.php');

        if (! preg_match('/private const ' . preg_quote($constant, '/') . ' = \[(.*?)\n    \];/s', $contents, $block)) {
            self::fail("Could not locate the {$constant} block in class-mstv-i18n.php.");
        }

        preg_match_all("/^\\s*'((?:\\\\'|[^'])*)'\\s*=>/m", $block[1], $matches);

        return array_map(
            static fn (string $key): string => str_replace("\\'", "'", $key),
            $matches[1]
        );
    }
}
