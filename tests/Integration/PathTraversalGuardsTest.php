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
use craft\helpers\Db;
use craft\helpers\FileHelper;
use craft\helpers\StringHelper;
use lindemannrock\iconmanager\IconManager;
use lindemannrock\iconmanager\models\IconSet;
use lindemannrock\iconmanager\tests\TestCase;
use lindemannrock\iconmanager\variables\IconManagerVariable;

/**
 * Pins the path-traversal guards added to the optimizer and the icon scanner.
 *
 * The threat actor here is an admin with `iconManager:editIconSets` or
 * `iconManager:manageOptimization` who supplies a crafted icon-set `folder`
 * setting or a crafted `backupPath` POST body. Pre-fix, the scan/restore/
 * delete paths honored `../` and could escape into the rest of the
 * filesystem; the guards reject anything that resolves outside the expected
 * base.
 */
final class PathTraversalGuardsTest extends TestCase
{
    public function testRefreshIconsForSetDropsIconsWhenFolderEscapesIconsBase(): void
    {
        // seedTempIconRoot points iconSetsPath at a fresh temp directory.
        // A `folder` setting of `../escape-target` resolves outside it.
        $this->seedTempIconRoot();

        [$setId, $iconSet] = $this->seedIconSet('svg-folder', 3, [
            'folder' => '../escape-target',
            'includeSubfolders' => true,
        ]);

        // Pre-condition: stale seeded rows exist.
        $this->assertSame(3, $this->countRows('{{%iconmanager_icons}}', ['iconSetId' => $setId]));

        IconManager::getInstance()->icons->refreshIconsForSet($iconSet);

        // Post-condition: scan rejected the folder, so the refresh wiped the
        // stale rows and inserted nothing. Zero rows == the guard fired.
        $this->assertSame(
            0,
            $this->countRows('{{%iconmanager_icons}}', ['iconSetId' => $setId]),
            'IconsService::_scanSvgFolder must reject folders that escape the icons base.'
        );
    }

    public function testOptimizerScanIconSetReturnsEmptyWhenFolderEscapesIconsBase(): void
    {
        $this->seedTempIconRoot();

        [, $iconSet] = $this->seedIconSet('svg-folder', 0, [
            'folder' => '../escape-target',
            'includeSubfolders' => true,
        ]);

        $result = IconManager::getInstance()->svgOptimizer->scanIconSet($iconSet);

        $this->assertSame(0, $result['totalIcons']);
        $this->assertSame([], $result['icons']);
    }

    public function testRestoreFromBackupRejectsPathOutsideBackupRoot(): void
    {
        // Source directory outside runtime/icon-manager/backups — typical
        // attacker input would be a system path like /etc, but a temp dir
        // we control is enough to prove the guard fires.
        $maliciousSource = sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::MARKER_PREFIX . 'restore_src_' . bin2hex(random_bytes(3));
        FileHelper::createDirectory($maliciousSource);
        file_put_contents($maliciousSource . '/dummy.svg', '<svg></svg>');

        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::MARKER_PREFIX . 'restore_target_' . bin2hex(random_bytes(3));
        FileHelper::createDirectory($target);
        file_put_contents($target . '/keep-me.txt', 'untouched');

        $result = IconManager::getInstance()->svgOptimizer->restoreFromBackup($maliciousSource, $target);

        $this->assertFalse($result, 'restoreFromBackup must reject sources outside the backup root.');
        $this->assertFileExists(
            $target . '/keep-me.txt',
            'Target must not be wiped — the guard must fire before deleteDirectory().'
        );

        FileHelper::removeDirectory($maliciousSource);
        FileHelper::removeDirectory($target);
    }

    public function testInjectSpriteRejectsSpriteFileTraversal(): void
    {
        $this->seedTempIconRoot();

        [, $iconSet] = $this->seedIconSet('svg-sprite', 0, [
            'spriteFile' => '../escape-target.svg',
        ]);

        $variable = new IconManagerVariable();
        $output = $variable->injectSprite($iconSet->handle);

        $this->assertSame(
            '',
            $output,
            'injectSprite() must return empty string when spriteFile escapes the icons base.',
        );
    }

    /**
     * Pre-fix, injectSprite() rendered raw sprite content (minus <style>) into
     * front-end templates. <script>, <foreignObject>, and event handlers all
     * survived — public-facing XSS via an admin-uploaded sprite. This test
     * pins the Icon::sanitizeSvg() pipe added in pass #3, including the
     * survival of <symbol> + <defs> which sprites depend on.
     */
    public function testInjectSpriteSanitizesMaliciousContent(): void
    {
        $root = $this->seedTempIconRoot();

        $maliciousSprite = <<<'SVG'
            <?xml version="1.0" encoding="UTF-8"?>
            <svg xmlns="http://www.w3.org/2000/svg">
                <script>alert('sprite-script')</script>
                <foreignObject>
                    <body xmlns="http://www.w3.org/1999/xhtml"><script>alert('sprite-foreignObject')</script></body>
                </foreignObject>
                <defs>
                    <symbol id="legit-icon" viewBox="0 0 24 24" onclick="alert('sprite-onclick')">
                        <path d="M12 0L0 12h24z"/>
                    </symbol>
                </defs>
            </svg>
            SVG;
        file_put_contents($root . '/sprite.svg', $maliciousSprite);

        [, $iconSet] = $this->seedIconSet('svg-sprite', 0, [
            'spriteFile' => 'sprite.svg',
        ]);

        $variable = new IconManagerVariable();
        $output = $variable->injectSprite($iconSet->handle);

        $this->assertNotSame('', $output, 'Output should not be empty for a valid sprite file.');
        $this->assertStringNotContainsStringIgnoringCase('<script', $output);
        $this->assertStringNotContainsStringIgnoringCase('<foreignObject', $output);
        $this->assertStringNotContainsStringIgnoringCase('onclick', $output);

        // Sprite-essential elements must survive.
        $this->assertStringContainsStringIgnoringCase('<symbol', $output);
        $this->assertStringContainsStringIgnoringCase('id="legit-icon"', $output);
        $this->assertStringContainsStringIgnoringCase('<path', $output);
    }

    public function testDeleteBackupRejectsPathOutsideBackupRoot(): void
    {
        $maliciousTarget = sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::MARKER_PREFIX . 'delete_target_' . bin2hex(random_bytes(3));
        FileHelper::createDirectory($maliciousTarget);
        file_put_contents($maliciousTarget . '/keep-me.txt', 'untouched');

        $result = IconManager::getInstance()->svgOptimizer->deleteBackup($maliciousTarget);

        $this->assertFalse($result, 'deleteBackup must reject paths outside the backup root.');
        $this->assertDirectoryExists(
            $maliciousTarget,
            'Target directory must survive — the guard must fire before deleteDirectory().'
        );
        $this->assertFileExists($maliciousTarget . '/keep-me.txt');

        FileHelper::removeDirectory($maliciousTarget);
    }

    /**
     * Copied from IconsServiceCacheTest::seedIconSet — needed here because
     * private helpers don't cross test classes. Same contract: insert an
     * iconset row + N stale icon rows by hand, return id + populated model.
     *
     * @return array{0:int,1:IconSet}
     */
    private function seedIconSet(string $type, int $iconCount, array $extraSettings = []): array
    {
        $handle = self::MARKER_PREFIX . 'set_' . bin2hex(random_bytes(3));
        $now = Db::prepareDateForDb(new \DateTime());

        Craft::$app->getDb()->createCommand()
            ->insert('{{%iconmanager_iconsets}}', [
                'name' => 'Path Traversal Test Set',
                'handle' => $handle,
                'type' => $type,
                'settings' => json_encode($extraSettings),
                'enabled' => true,
                'sortOrder' => 999,
                'dateCreated' => $now,
                'dateUpdated' => $now,
                'uid' => StringHelper::UUID(),
            ])
            ->execute();
        $setId = (int) Craft::$app->getDb()->getLastInsertID('{{%iconmanager_iconsets}}_id_seq');

        for ($i = 0; $i < $iconCount; $i++) {
            Craft::$app->getDb()->createCommand()
                ->insert('{{%iconmanager_icons}}', [
                    'iconSetId' => $setId,
                    'name' => self::MARKER_PREFIX . 'icon_' . $i,
                    'label' => 'Test Icon ' . $i,
                    'path' => '',
                    'keywords' => json_encode([]),
                    'metadata' => json_encode(['type' => 'svg']),
                    'dateCreated' => $now,
                    'dateUpdated' => $now,
                    'uid' => StringHelper::UUID(),
                ])
                ->execute();
        }

        IconManager::getInstance()->iconSets->clearCache();

        $iconSet = IconManager::getInstance()->iconSets->getIconSetById($setId);
        $this->assertNotNull($iconSet);

        $this->trackSeededSetId($setId);

        return [$setId, $iconSet];
    }
}
