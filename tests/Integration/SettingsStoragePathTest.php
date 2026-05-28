<?php
/**
 * LindemannRock Icon Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\iconmanager\tests\Integration;

use Craft;
use lindemannrock\iconmanager\models\Settings;
use lindemannrock\iconmanager\tests\TestCase;

/**
 * Pins the Icon Sets Path contract.
 */
final class SettingsStoragePathTest extends TestCase
{
    private const ENV_NAME = 'LR_ICON_MANAGER_PATH_TEST';

    protected function tearDown(): void
    {
        putenv(self::ENV_NAME);
        unset($_ENV[self::ENV_NAME], $_SERVER[self::ENV_NAME]);

        parent::tearDown();
    }

    public function testWebrootAliasIsAllowedForIconSetsPath(): void
    {
        $settings = new Settings();
        $settings->iconSetsPath = '@webroot/dist/assets/icons';

        self::assertTrue($settings->validate(['iconSetsPath']));
    }

    public function testStorageAliasIsAllowedForIconSetsPath(): void
    {
        $settings = new Settings();
        $settings->iconSetsPath = '@storage/icon-manager/icons';

        self::assertTrue($settings->validate(['iconSetsPath']));
    }

    public function testAbsolutePathInsideAllowedRootIsAllowedForIconSetsPath(): void
    {
        $settings = new Settings();
        $settings->iconSetsPath = Craft::getAlias('@root/icons');

        self::assertTrue($settings->validate(['iconSetsPath']));
    }

    public function testEnvVarResolvingInsideAllowedRootIsAllowedForIconSetsPath(): void
    {
        $this->setEnvValue(Craft::getAlias('@webroot/dist/assets/icons'));

        $settings = new Settings();
        $settings->iconSetsPath = '$' . self::ENV_NAME;

        self::assertTrue($settings->validate(['iconSetsPath']));
    }

    public function testPathOutsideAllowedRootsFailsForIconSetsPath(): void
    {
        $settings = new Settings();
        $settings->iconSetsPath = '/tmp/icon-manager-icons';

        self::assertFalse($settings->validate(['iconSetsPath']));
        self::assertStringContainsString('@root', implode(' ', $settings->getErrors('iconSetsPath')));
    }

    public function testParentTraversalFailsForIconSetsPath(): void
    {
        $settings = new Settings();
        $settings->iconSetsPath = '@root/../config';

        self::assertFalse($settings->validate(['iconSetsPath']));
        self::assertStringContainsString('..', implode(' ', $settings->getErrors('iconSetsPath')));
    }

    private function setEnvValue(string $value): void
    {
        putenv(self::ENV_NAME . '=' . $value);
        $_ENV[self::ENV_NAME] = $value;
        $_SERVER[self::ENV_NAME] = $value;
    }
}
