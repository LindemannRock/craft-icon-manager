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
use lindemannrock\iconmanager\models\Icon;
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
        $maliciousSource = $this->createTrackedTempDirectory(self::MARKER_PREFIX . 'restore-src-');
        file_put_contents($maliciousSource . '/dummy.svg', '<svg></svg>');

        $target = $this->createTrackedTempDirectory(self::MARKER_PREFIX . 'restore-target-');
        file_put_contents($target . '/keep-me.txt', 'untouched');

        $result = IconManager::getInstance()->svgOptimizer->restoreFromBackup($maliciousSource, $target);

        $this->assertFalse($result, 'restoreFromBackup must reject sources outside the backup root.');
        $this->assertFileExists(
            $target . '/keep-me.txt',
            'Target must not be wiped — the guard must fire before deleteDirectory().'
        );
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

    /**
     * Third copy of the sprite path-traversal pattern: `Icon::_registerSprite()`
     * is called from `Icon::render()` whenever a Twig template renders a
     * sprite-type icon. Pass #3 fixed `actionServeSprite()` and `injectSprite()`
     * but missed this internal render path. Tested via reflection because the
     * method is private.
     */
    public function testRegisterSpriteRejectsSpriteFileTraversal(): void
    {
        $this->seedTempIconRoot();

        [, $iconSet] = $this->seedIconSet('svg-sprite', 0, [
            'spriteFile' => '../escape-from-icon-render.svg',
        ]);

        $icon = new Icon();
        $icon->type = Icon::TYPE_SPRITE;
        $icon->iconSetHandle = $iconSet->handle;
        $icon->name = 'evil-symbol';
        $icon->value = 'evil-symbol';

        $registered = $this->captureRegisteredHtml(function () use ($icon) {
            $method = new \ReflectionMethod($icon, '_registerSprite');
            $method->setAccessible(true);
            $method->invoke($icon);
        });

        $this->assertSame(
            '',
            $registered,
            '_registerSprite() must abort silently when spriteFile escapes the icons base.',
        );
    }

    /**
     * Pre-fix, `_registerSprite()` only stripped `<style>` tags before
     * `registerHtml()`. Event handlers, `<script>`, and `<foreignObject>`
     * survived into the rendered page. This test pins the `Icon::sanitizeSvg()`
     * pipe added in pass #4 (batch 5) — including the survival of `<symbol>`
     * + `<defs>` which sprites depend on.
     */
    public function testRegisterSpriteSanitizesMaliciousContent(): void
    {
        $root = $this->seedTempIconRoot();

        $maliciousSprite = <<<'SVG'
            <?xml version="1.0" encoding="UTF-8"?>
            <svg xmlns="http://www.w3.org/2000/svg">
                <script>alert('render-sprite-script')</script>
                <foreignObject>
                    <body xmlns="http://www.w3.org/1999/xhtml"><script>alert('render-sprite-foreignObject')</script></body>
                </foreignObject>
                <defs>
                    <symbol id="render-sprite-icon" viewBox="0 0 24 24" onclick="alert('render-sprite-onclick')">
                        <path d="M12 0L0 12h24z"/>
                    </symbol>
                </defs>
            </svg>
            SVG;
        file_put_contents($root . '/render-sprite.svg', $maliciousSprite);

        [, $iconSet] = $this->seedIconSet('svg-sprite', 0, [
            'spriteFile' => 'render-sprite.svg',
        ]);

        $icon = new Icon();
        $icon->type = Icon::TYPE_SPRITE;
        $icon->iconSetHandle = $iconSet->handle;
        $icon->name = 'render-sprite-icon';
        $icon->value = 'render-sprite-icon';

        $registered = $this->captureRegisteredHtml(function () use ($icon) {
            $method = new \ReflectionMethod($icon, '_registerSprite');
            $method->setAccessible(true);
            $method->invoke($icon);
        });

        $this->assertNotSame('', $registered, 'Sprite registration should have produced output for a valid file.');
        $this->assertStringNotContainsStringIgnoringCase('<script', $registered);
        $this->assertStringNotContainsStringIgnoringCase('<foreignObject', $registered);
        $this->assertStringNotContainsStringIgnoringCase('onclick', $registered);

        // Sprite-essential elements must survive.
        $this->assertStringContainsStringIgnoringCase('<symbol', $registered);
        $this->assertStringContainsStringIgnoringCase('id="render-sprite-icon"', $registered);
        $this->assertStringContainsStringIgnoringCase('<path', $registered);
    }

    public function testDeleteBackupRejectsPathOutsideBackupRoot(): void
    {
        $maliciousTarget = $this->createTrackedTempDirectory(self::MARKER_PREFIX . 'delete-target-');
        file_put_contents($maliciousTarget . '/keep-me.txt', 'untouched');

        $result = IconManager::getInstance()->svgOptimizer->deleteBackup($maliciousTarget);

        $this->assertFalse($result, 'deleteBackup must reject paths outside the backup root.');
        $this->assertDirectoryExists(
            $maliciousTarget,
            'Target directory must survive — the guard must fire before deleteDirectory().'
        );
        $this->assertFileExists($maliciousTarget . '/keep-me.txt');
    }

    /**
     * Run a callable that may call `View::registerHtml(...)` and return the
     * concatenated HTML that was newly registered at POS_BEGIN during the
     * call. Reads the private `_html` property via reflection because
     * craft\web\View doesn't expose registered content directly.
     */
    private function captureRegisteredHtml(callable $fn): string
    {
        $view = Craft::$app->getView();
        $prop = new \ReflectionProperty($view, '_html');
        $prop->setAccessible(true);

        $before = $prop->getValue($view)[\yii\web\View::POS_BEGIN] ?? [];

        $fn();

        $after = $prop->getValue($view)[\yii\web\View::POS_BEGIN] ?? [];
        $newEntries = array_diff_key($after, $before);

        return implode('', $newEntries);
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
