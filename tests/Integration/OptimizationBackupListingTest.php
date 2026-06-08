<?php
/**
 * LindemannRock Icon Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\iconmanager\tests\Integration;

use craft\helpers\FileHelper;
use lindemannrock\iconmanager\IconManager;
use lindemannrock\iconmanager\tests\TestCase;

/**
 * Pins the backup-listing date contract. Backup dates must come from the
 * timestamp baked into the folder name, not filemtime — otherwise relocating
 * backup storage (a manual move/copy) resets every backup's displayed date to
 * the move time.
 */
final class OptimizationBackupListingTest extends TestCase
{
    private string $backupBase;

    /** @var string[] */
    private array $createdFolders = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->backupBase = IconManager::getInstance()->getSettings()->getBackupPath();
    }

    protected function tearDown(): void
    {
        foreach ($this->createdFolders as $folder) {
            if (is_dir($folder)) {
                FileHelper::removeDirectory($folder);
            }
        }
        $this->createdFolders = [];

        parent::tearDown();
    }

    public function testListBackupsDerivesDateFromFolderNameNotMtime(): void
    {
        $setName = '__iconmanager_test_backup_date';
        $folder = $this->makeBackupFolder($setName . '_2020-01-02_03-04-05');
        // Simulate a manual move: mtime is "now", but the name says 2020.
        touch($folder, time());

        $backups = IconManager::getInstance()->svgOptimizer->listBackups($setName);

        self::assertCount(1, $backups);
        self::assertSame('2020-01-02 03:04:05', $backups[0]['dateTime']->format('Y-m-d H:i:s'));
    }

    public function testListBackupsFallsBackToMtimeForUnparseableName(): void
    {
        $setName = '__iconmanager_test_backup_legacy';
        $folder = $this->makeBackupFolder($setName . '_legacy');
        $mtime = 1559894950; // 2019-06-07 08:09:10 UTC
        touch($folder, $mtime);

        $backups = IconManager::getInstance()->svgOptimizer->listBackups($setName);

        self::assertCount(1, $backups);
        self::assertSame($mtime, $backups[0]['dateTime']->getTimestamp());
    }

    private function makeBackupFolder(string $name): string
    {
        $folder = $this->backupBase . '/' . $name;
        FileHelper::createDirectory($folder);
        file_put_contents($folder . '/icon.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
        $this->createdFolders[] = $folder;

        return $folder;
    }
}
