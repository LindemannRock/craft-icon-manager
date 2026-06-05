<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\iconmanager\tests\Integration;

use lindemannrock\iconmanager\models\Settings;
use lindemannrock\iconmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @since 5.15.0
 */
#[CoversClass(Settings::class)]
final class SettingsCacheDurationValidationTest extends TestCase
{
    public function testCacheDurationMatchesRenderedFieldBounds(): void
    {
        $settings = new Settings();

        $settings->cacheDuration = 59;
        self::assertFalse($settings->validate(['cacheDuration']));
        self::assertNotEmpty($settings->getErrors('cacheDuration'));

        $settings->clearErrors();
        $settings->cacheDuration = 60;
        self::assertTrue($settings->validate(['cacheDuration']));

        $settings->cacheDuration = 604800;
        self::assertTrue($settings->validate(['cacheDuration']));

        $settings->cacheDuration = 604801;
        self::assertFalse($settings->validate(['cacheDuration']));
        self::assertNotEmpty($settings->getErrors('cacheDuration'));
    }
}
