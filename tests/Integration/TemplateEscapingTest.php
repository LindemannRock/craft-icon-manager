<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\iconmanager\tests\Integration;

use lindemannrock\iconmanager\tests\TestCase;

/**
 * @since 5.18.0
 */
final class TemplateEscapingTest extends TestCase
{
    /**
     * @dataProvider pathInfoBoxTemplates
     */
    public function testPathInfoBoxesEscapeDynamicPaths(string $templatePath, string $variable): void
    {
        $contents = (string)file_get_contents($templatePath);

        self::assertStringContainsString($variable . '|e', $contents);
        self::assertStringNotContainsString('~ ' . $variable . ' ~', $contents);
    }

    public function testSharedCacheStatusAutoEscapesTheConsumerFilePath(): void
    {
        $pluginTemplate = (string)file_get_contents(dirname(__DIR__, 2) . '/src/templates/settings/cache.twig');
        $baseTemplate = (string)file_get_contents(dirname(__DIR__, 3) . '/base/src/templates/_components/cache-storage-status.twig');

        self::assertStringContainsString("'lindemannrock-base/_partials/field-cache-storage'", $pluginTemplate);
        self::assertStringContainsString('filePath: cacheStorage.filePath', $pluginTemplate);
        self::assertStringContainsString('<code>{{ filePath }}</code>', $baseTemplate);
        self::assertStringNotContainsString('filePath|raw', $baseTemplate);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function pathInfoBoxTemplates(): array
    {
        $templateRoot = dirname(__DIR__, 2) . '/src/templates';

        return [
            'icon sets path' => [$templateRoot . '/settings/general.twig', 'resolvedIconSetsPath'],
        ];
    }
}
