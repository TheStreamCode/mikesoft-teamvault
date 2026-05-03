<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PdmI18nCoverageTest extends TestCase
{
    public function testItalianMapCoversEveryPluginTranslationString(): void
    {
        $usedStrings = $this->collectUsedTranslationStrings();
        $translatedStrings = $this->getItalianMapKeys();
        $missingStrings = array_values(array_diff($usedStrings, $translatedStrings));

        sort($missingStrings);

        self::assertSame(
            [],
            $missingStrings,
            "Missing Italian translations:\n" . implode("\n", $missingStrings)
        );
    }

    public function testItalianMapDoesNotContainUnusedTranslationStrings(): void
    {
        $usedStrings = $this->collectUsedTranslationStrings();
        $translatedStrings = $this->getItalianMapKeys();
        $unusedStrings = array_values(array_diff($translatedStrings, $usedStrings));

        sort($unusedStrings);

        self::assertSame(
            [],
            $unusedStrings,
            "Unused Italian translations:\n" . implode("\n", $unusedStrings)
        );
    }

    public function testItalianMapDoesNotContainDuplicateKeys(): void
    {
        $keys = $this->getItalianMapKeysFromSource();
        $duplicates = array_values(array_unique(array_diff_assoc($keys, array_unique($keys))));

        sort($duplicates);

        self::assertSame(
            [],
            $duplicates,
            "Duplicate Italian translation keys:\n" . implode("\n", $duplicates)
        );
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
    private function getItalianMapKeys(): array
    {
        $constant = (new ReflectionClass(MSTV_I18n::class))->getReflectionConstant('ITALIAN_MAP');

        self::assertNotFalse($constant);

        $keys = array_keys($constant->getValue());
        sort($keys);

        return $keys;
    }

    /**
     * @return string[]
     */
    private function getItalianMapKeysFromSource(): array
    {
        $contents = (string) file_get_contents(dirname(__DIR__) . '/includes/class-mstv-i18n.php');
        preg_match_all("/^\\s*'((?:\\\\'|[^'])*)'\\s*=>/m", $contents, $matches);

        return array_map(
            static fn (string $key): string => str_replace("\\'", "'", $key),
            $matches[1]
        );
    }
}
