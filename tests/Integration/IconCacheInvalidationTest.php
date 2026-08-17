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
use lindemannrock\iconmanager\IconManager;
use lindemannrock\iconmanager\models\Icon;
use lindemannrock\iconmanager\models\IconSet;
use lindemannrock\iconmanager\services\IconCacheService;
use lindemannrock\iconmanager\services\IconsService;
use lindemannrock\iconmanager\tests\TestCase;
use yii\caching\Cache;
use yii\caching\CacheInterface;

/**
 * Covers scoped invalidation and mutation lifecycle behavior.
 */
final class IconCacheInvalidationTest extends TestCase
{
    private CacheInterface $originalCache;
    private string $originalStorage;
    private bool $originalEnabled;
    private int $originalDuration;

    /** @var list<int> */
    private array $createdIconSetIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalCache = Craft::$app->getCache();
        $settings = IconManager::$plugin->getSettings();
        $this->originalStorage = $settings->cacheStorageMethod;
        $this->originalEnabled = $settings->enableCache;
        $this->originalDuration = $settings->cacheDuration;
        $settings->cacheStorageMethod = 'craft';
        $settings->enableCache = true;
        $settings->cacheDuration = 600;
        Craft::$app->set('cache', new InvalidationApplicationCache());
    }

    protected function tearDown(): void
    {
        foreach ($this->createdIconSetIds as $iconSetId) {
            Craft::$app->getDb()->createCommand()
                ->delete('{{%iconmanager_icons}}', ['iconSetId' => $iconSetId])
                ->execute();
            Craft::$app->getDb()->createCommand()
                ->delete('{{%iconmanager_iconsets}}', ['id' => $iconSetId])
                ->execute();
        }
        $this->createdIconSetIds = [];

        parent::tearDown();
    }

    protected function cleanupExternalState(): void
    {
        Craft::$app->set('cache', $this->originalCache);
        $settings = IconManager::$plugin->getSettings();
        $settings->cacheStorageMethod = $this->originalStorage;
        $settings->enableCache = $this->originalEnabled;
        $settings->cacheDuration = $this->originalDuration;

        parent::cleanupExternalState();
    }

    public function testSetInvalidationIsIsolatedAndDoesNotInvalidateDefinitions(): void
    {
        $service = new IconCacheService();
        $icon = new Icon();
        $one = $this->iconSet(101, 'svg-folder');
        $two = $this->iconSet(102, 'material-icons');

        self::assertTrue($service->writeIconCollection(101, $one, [$icon]));
        self::assertTrue($service->writeIconCollection(102, $two, [$icon]));
        self::assertTrue($service->writeMaterialDefinitions('icons', []));
        self::assertTrue($service->invalidateSet(101, ['svg-folder']));

        self::assertTrue($service->readIconCollection(101, $one)->isMiss());
        self::assertTrue($service->readIconCollection(102, $two)->isHit());
        self::assertTrue($service->readMaterialDefinitions('icons')->isHit());
    }

    public function testApplicationIdentityUsesStableSetIdAndNotIconSetType(): void
    {
        $service = new IconCacheService();
        $icon = new Icon();
        $oldType = $this->iconSet(103, 'svg-sprite');
        $newType = $this->iconSet(103, 'web-font');

        self::assertTrue($service->writeIconCollection(103, $oldType, [$icon]));
        self::assertTrue($service->readIconCollection(103, $newType)->isHit());
        self::assertTrue($service->invalidateSet(103, ['svg-sprite', 'web-font']));
        self::assertTrue($service->readIconCollection(103, $oldType)->isMiss());
        self::assertTrue($service->readIconCollection(103, $newType)->isMiss());
    }

    public function testFamilyInvalidationClearsCollectionsAndDefinitionsButPreservesUnrelatedEntries(): void
    {
        $cache = Craft::$app->getCache();
        self::assertInstanceOf(InvalidationApplicationCache::class, $cache);
        self::assertTrue($cache->set('unrelated-sentinel', 'preserved', 600));

        $service = new IconCacheService();
        $iconSet = $this->iconSet(104, 'svg-folder');
        self::assertTrue($service->writeIconCollection(104, $iconSet, [new Icon()]));
        self::assertTrue($service->writeFontAwesomeDefinitions('7.0.0', 'free', []));
        self::assertTrue($service->writeMaterialDefinitions('icons', []));

        self::assertTrue($service->invalidateFamily());
        self::assertTrue($service->readIconCollection(104, $iconSet)->isMiss());
        self::assertTrue($service->readFontAwesomeDefinitions('7.0.0', 'free')->isMiss());
        self::assertTrue($service->readMaterialDefinitions('icons')->isMiss());
        self::assertSame('preserved', $cache->get('unrelated-sentinel'));
    }

    public function testStorageTransitionInvalidatesBothOldAndNewEffectiveStores(): void
    {
        $service = new IconCacheService();
        self::assertTrue($service->writeMaterialDefinitions('icons', []));

        $originalRuntimePath = Craft::$app->getRuntimePath();
        $runtimePath = $this->createTrackedTempDirectory(self::MARKER_PREFIX . 'transition_');
        Craft::$app->setRuntimePath($runtimePath);
        $settings = IconManager::$plugin->getSettings();

        try {
            $settings->cacheStorageMethod = 'file';
            $iconSet = $this->iconSet(105, 'svg-folder');
            self::assertTrue($service->writeIconCollection(105, $iconSet, [new Icon()]));
            $file = $runtimePath . '/icon-manager/cache/svg-folder/set_105.cache';
            self::assertFileExists($file);

            self::assertTrue($service->invalidateStorageTransition('file', 'craft'));
            self::assertFileDoesNotExist($file);

            $settings->cacheStorageMethod = 'craft';
            self::assertTrue($service->readMaterialDefinitions('icons')->isMiss());
        } finally {
            Craft::$app->setRuntimePath($originalRuntimePath);
            $settings->cacheStorageMethod = 'craft';
        }
    }

    public function testTypeChangeRefreshesTheStableSetScope(): void
    {
        $root = $this->seedTempIconRoot();
        file_put_contents($root . '/before.svg', <<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg">
                <symbol id="before" viewBox="0 0 24 24"><path d="M0"/></symbol>
            </svg>
            SVG);
        file_put_contents($root . '/after.svg', <<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0"/></svg>
            SVG);

        $iconSet = new IconSet();
        $iconSet->name = 'Cache type lifecycle';
        $iconSet->handle = 'im-test-type-lifecycle';
        $iconSet->type = 'svg-sprite';
        $iconSet->settings = ['spriteFile' => 'before.svg'];
        $iconSet->enabled = true;
        self::assertTrue(IconManager::$plugin->iconSets->saveIconSet($iconSet));
        $this->createdIconSetIds[] = $iconSet->id;
        $this->trackSeededSetId($iconSet->id);

        $before = (new IconsService())->getIconsBySetId($iconSet->id);
        self::assertCount(1, $before);
        self::assertSame('before', $before[0]->name);

        $iconSet->type = 'svg-folder';
        $iconSet->settings = ['folder' => '', 'includeSubfolders' => false];
        self::assertTrue(IconManager::$plugin->iconSets->saveIconSet($iconSet));

        $after = (new IconsService())->getIconsBySetId($iconSet->id);
        $names = array_map(static fn(Icon $icon): string => $icon->name, $after);
        sort($names);
        self::assertSame(['after', 'before'], $names);
    }

    public function testCommittedSaveAndDeletionSucceedWhenPostCommitInvalidationFails(): void
    {
        Craft::$app->set('cache', new InvalidationThrowingCache());
        $root = $this->seedTempIconRoot();
        file_put_contents($root . '/committed.svg', <<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg">
                <symbol id="committed" viewBox="0 0 24 24"><path d="M0"/></symbol>
            </svg>
            SVG);

        $iconSet = new IconSet();
        $iconSet->name = 'Committed cache failure';
        $iconSet->handle = 'im-test-committed-failure';
        $iconSet->type = 'svg-sprite';
        $iconSet->settings = ['spriteFile' => 'committed.svg'];
        $iconSet->enabled = true;

        self::assertTrue(IconManager::$plugin->iconSets->saveIconSet($iconSet));
        $this->createdIconSetIds[] = $iconSet->id;
        $this->trackSeededSetId($iconSet->id);
        self::assertSame(1, $this->countRows('{{%iconmanager_iconsets}}', ['id' => $iconSet->id]));
        self::assertSame(1, $this->countRows('{{%iconmanager_icons}}', ['iconSetId' => $iconSet->id]));

        self::assertTrue(IconManager::$plugin->iconSets->deleteIconSet($iconSet));
        self::assertSame(0, $this->countRows('{{%iconmanager_iconsets}}', ['id' => $iconSet->id]));
    }

    private function iconSet(int $id, string $type): IconSet
    {
        $iconSet = new IconSet();
        $iconSet->id = $id;
        $iconSet->handle = 'set-' . $id;
        $iconSet->type = $type;

        return $iconSet;
    }
}

/**
 * Persistent application-cache double used for invalidation behavior.
 *
 * @since 5.18.0
 */
class InvalidationApplicationCache extends Cache
{
    /** @var array<string, mixed> */
    private array $values = [];

    protected function getValue($key)
    {
        return $this->values[$key] ?? false;
    }

    protected function setValue($key, $value, $duration)
    {
        $this->values[$key] = $value;
        return true;
    }

    protected function addValue($key, $value, $duration)
    {
        if (array_key_exists($key, $this->values)) {
            return false;
        }

        return $this->setValue($key, $value, $duration);
    }

    protected function deleteValue($key)
    {
        unset($this->values[$key]);
        return true;
    }

    protected function flushValues()
    {
        $this->values = [];
        return true;
    }
}

/**
 * Persistent application cache whose operations fail.
 *
 * @since 5.18.0
 */
final class InvalidationThrowingCache extends InvalidationApplicationCache
{
    protected function getValue($key)
    {
        throw new \RuntimeException('Injected cache read failure.');
    }

    protected function setValue($key, $value, $duration)
    {
        throw new \RuntimeException('Injected cache write failure.');
    }

    protected function addValue($key, $value, $duration)
    {
        throw new \RuntimeException('Injected cache add failure.');
    }
}
