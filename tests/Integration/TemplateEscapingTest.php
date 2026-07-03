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

    /**
     * @return array<string, array{string, string}>
     */
    public static function pathInfoBoxTemplates(): array
    {
        $templateRoot = dirname(__DIR__, 2) . '/src/templates';

        return [
            'cache base path' => [$templateRoot . '/settings/cache.twig', 'iconHelper.cacheBasePath'],
            'icon sets path' => [$templateRoot . '/settings/general.twig', 'resolvedIconSetsPath'],
        ];
    }
}
