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
 * Pins the optimization backup-location contract. getBackupPath() is the single
 * source of truth for where backups are written, listed, and restored from — a
 * regression here either scatters backups or lets the restore/delete containment
 * guard point at the wrong root.
 */
final class SettingsBackupPathTest extends TestCase
{
    public function testDefaultBackupPathResolvesToStorage(): void
    {
        $settings = new Settings();

        self::assertSame(
            Craft::getAlias('@storage/icon-manager/backups'),
            $settings->getBackupPath()
        );
    }

    public function testCustomBackupPathResolvesFromAlias(): void
    {
        $settings = new Settings();
        $settings->backupPath = '@storage/custom-icon-backups';

        self::assertSame(
            Craft::getAlias('@storage/custom-icon-backups'),
            $settings->getBackupPath()
        );
    }

    public function testStorageAliasIsAllowedForBackupPath(): void
    {
        $settings = new Settings();
        $settings->backupPath = '@storage/icon-manager/backups';

        self::assertTrue($settings->validate(['backupPath']));
    }

    public function testPathOutsideAllowedRootsFailsForBackupPath(): void
    {
        $settings = new Settings();
        $settings->backupPath = '/tmp/icon-manager-backups';

        self::assertFalse($settings->validate(['backupPath']));
    }

    public function testWebrootIsRejectedForBackupPath(): void
    {
        // Backups must never be web-accessible — @webroot is not an allowed root.
        $settings = new Settings();
        $settings->backupPath = '@webroot/icon-manager/backups';

        self::assertFalse($settings->validate(['backupPath']));
    }

    public function testBareRootRequiresSubfolderForBackupPath(): void
    {
        $settings = new Settings();
        $settings->backupPath = '@root';

        self::assertFalse($settings->validate(['backupPath']));
        self::assertStringContainsString('subfolder', implode(' ', $settings->getErrors('backupPath')));
    }

    public function testInvalidBackupPathFallsBackToStorageDefault(): void
    {
        $settings = new Settings();
        $settings->backupPath = '/tmp/icon-manager-backups';

        // An unresolvable/disallowed path must never escape the safe default.
        self::assertSame(
            Craft::getAlias('@storage/icon-manager/backups'),
            $settings->getBackupPath()
        );
    }

    public function testBackupLocationLabelMatchesPathWhenNoVolume(): void
    {
        $settings = new Settings();
        $settings->backupVolumeUid = null;

        self::assertSame($settings->getBackupPath(), $settings->getBackupLocationLabel());
    }
}
