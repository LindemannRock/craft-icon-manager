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
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\iconmanager\IconManager;
use lindemannrock\iconmanager\models\IconSet;
use lindemannrock\iconmanager\tests\TestCase;

/**
 * Pins IconsService::getIconsBySetId() cache contract + refreshIconsForSet()
 * invalidation. Stale cache here serves outdated icons to every CP field +
 * frontend render — the failure mode is "I changed my icon set but the
 * picker still shows the old list."
 */
final class IconsServiceCacheTest extends TestCase
{
    private bool $savedEnableCache;
    private string $savedStorageMethod;

    protected function setUp(): void
    {
        parent::setUp();

        // The DDEV install may have cacheStorageMethod=redis; these tests pin
        // the file-cache lifecycle specifically. Save + force, restore in
        // tearDown.
        $settings = IconManager::getInstance()->getSettings();
        $this->savedEnableCache = $settings->enableCache;
        $this->savedStorageMethod = $settings->cacheStorageMethod;
        $settings->enableCache = true;
        $settings->cacheStorageMethod = 'file';
    }

    protected function tearDown(): void
    {
        $settings = IconManager::getInstance()->getSettings();
        $settings->enableCache = $this->savedEnableCache;
        $settings->cacheStorageMethod = $this->savedStorageMethod;

        parent::tearDown();
    }

    /**
     * First call is a cache miss: rows come from the DB, get serialized into
     * the per-set file cache, and populate the in-memory map. The cache file
     * lives under storage/runtime/icon-manager/cache/{typeFolder}/set_{id}.cache
     * — typeFolder mirrors IconSet::$type with a stable allowlist.
     */
    public function testFirstCallSeedsFileCacheAndReturnsRowsFromDatabase(): void
    {
        [$setId, $iconSet] = $this->seedIconSet('svg-sprite', 3);

        $cacheFile = $this->cacheFilePath($iconSet);
        $this->assertFileDoesNotExist($cacheFile);

        $icons = IconManager::getInstance()->icons->getIconsBySetId($setId);

        $this->assertCount(3, $icons);
        $this->assertFileExists($cacheFile);

        $cached = unserialize((string) file_get_contents($cacheFile));
        $this->assertIsArray($cached);
        $this->assertCount(3, $cached);
        $this->assertContainsOnlyInstancesOf(\lindemannrock\iconmanager\models\Icon::class, $cached);
    }

    /**
     * After the first call, subsequent calls hit the in-memory `_iconsBySetId`
     * map without re-reading the DB. The proof: nuke the underlying rows AND
     * the file cache between calls — if the service re-queried, the second
     * call would return []. It must still return the original 3 icons.
     */
    public function testSecondCallShortCircuitsViaInMemoryCache(): void
    {
        [$setId, $iconSet] = $this->seedIconSet('svg-sprite', 3);

        $first = IconManager::getInstance()->icons->getIconsBySetId($setId);
        $this->assertCount(3, $first);

        // Yank the DB rows + file cache out from under the service.
        Craft::$app->getDb()->createCommand()
            ->delete('{{%iconmanager_icons}}', ['iconSetId' => $setId])
            ->execute();
        @unlink($this->cacheFilePath($iconSet));

        $second = IconManager::getInstance()->icons->getIconsBySetId($setId);

        $this->assertCount(3, $second, 'In-memory cache should still satisfy the second call.');
    }

    /**
     * refreshIconsForSet() must drop BOTH the file cache and the in-memory
     * map, otherwise a CP "rescan" leaves consumers reading stale icons.
     * Verified by pre-warming both caches with synthetic rows, then calling
     * refresh against a real sprite — the post-refresh DB rows must match
     * the sprite (not the seeded stale rows), the cache file is gone, and
     * the next read returns the fresh set.
     */
    public function testRefreshIconsForSetClearsFileAndMemoryCacheAndRescans(): void
    {
        $root = $this->seedTempIconRoot();
        file_put_contents($root . '/post-refresh.svg', <<<'SVG'
            <?xml version="1.0" encoding="UTF-8"?>
            <svg xmlns="http://www.w3.org/2000/svg">
                <symbol id="fresh-a" viewBox="0 0 24 24"><path d="M0"/></symbol>
                <symbol id="fresh-b" viewBox="0 0 24 24"><path d="M0"/></symbol>
            </svg>
            SVG);

        // Insert iconset row pointing at the temp sprite, plus stale icon rows
        // that don't match its contents.
        [$setId, $iconSet] = $this->seedIconSet('svg-sprite', 5, [
            'spriteFile' => 'post-refresh.svg',
        ]);

        // Warm both caches.
        IconManager::getInstance()->icons->getIconsBySetId($setId);
        $cacheFile = $this->cacheFilePath($iconSet);
        $this->assertFileExists($cacheFile);

        IconManager::getInstance()->icons->refreshIconsForSet($iconSet);

        $this->assertFileDoesNotExist($cacheFile, 'refresh should drop the file cache');

        // Post-refresh DB rows must match the sprite (2 fresh symbols), not
        // the 5 stale rows we seeded.
        $rowCount = $this->countRows('{{%iconmanager_icons}}', ['iconSetId' => $setId]);
        $this->assertSame(2, $rowCount);

        $fresh = IconManager::getInstance()->icons->getIconsBySetId($setId);
        $this->assertCount(2, $fresh);
        $names = array_map(fn($icon) => $icon->name, $fresh);
        sort($names);
        $this->assertSame(['fresh-a', 'fresh-b'], $names);
    }

    /**
     * Same contract as the file-cache test above, but for the Redis storage
     * method. Pre-fix, refreshIconsForSet() only invalidated the file path —
     * Redis kept the stale icon list and every "Refresh Icons" click was a
     * no-op for users on Redis (the reported "I deleted icons but the count
     * is still 123" symptom). This test pins both sides of the fix: the
     * cached value is deleted, AND the key is removed from the SADD tracking
     * set used by the Clear-Icon-Cache utility.
     */
    public function testRefreshIconsForSetClearsRedisCacheAndRescans(): void
    {
        if (!(Craft::$app->cache instanceof \yii\redis\Cache)) {
            $this->markTestSkipped('Redis cache not configured for this environment.');
        }

        $settings = IconManager::getInstance()->getSettings();
        $settings->cacheStorageMethod = 'redis';

        $cacheKey = null;
        $trackingSet = PluginHelper::getCacheKeySet(IconManager::$plugin->id, 'icons');

        try {
            $root = $this->seedTempIconRoot();
            file_put_contents($root . '/post-refresh.svg', <<<'SVG'
                <?xml version="1.0" encoding="UTF-8"?>
                <svg xmlns="http://www.w3.org/2000/svg">
                    <symbol id="redis-fresh-a" viewBox="0 0 24 24"><path d="M0"/></symbol>
                    <symbol id="redis-fresh-b" viewBox="0 0 24 24"><path d="M0"/></symbol>
                </svg>
                SVG);

            [$setId, $iconSet] = $this->seedIconSet('svg-sprite', 5, [
                'spriteFile' => 'post-refresh.svg',
            ]);
            $cacheKey = PluginHelper::getCacheKeyPrefix(IconManager::$plugin->id, 'icons') . $setId;

            // Warm Redis: SET via _cacheIcons + SADD into the tracking set.
            IconManager::getInstance()->icons->getIconsBySetId($setId);
            $this->assertNotFalse(
                Craft::$app->cache->get($cacheKey),
                'getIconsBySetId() should have populated the Redis cache entry.'
            );

            $redis = Craft::$app->cache->redis;
            $trackedKeys = $redis->executeCommand('SMEMBERS', [$trackingSet]) ?: [];
            $this->assertContains(
                $cacheKey,
                $trackedKeys,
                '_cacheIcons() should have added the cache key to the tracking set.'
            );

            IconManager::getInstance()->icons->refreshIconsForSet($iconSet);

            $this->assertFalse(
                Craft::$app->cache->get($cacheKey),
                'refreshIconsForSet() should drop the Redis cache entry.'
            );

            $trackedKeysAfter = $redis->executeCommand('SMEMBERS', [$trackingSet]) ?: [];
            $this->assertNotContains(
                $cacheKey,
                $trackedKeysAfter,
                'refreshIconsForSet() should SREM the cache key from the tracking set.'
            );

            $rowCount = $this->countRows('{{%iconmanager_icons}}', ['iconSetId' => $setId]);
            $this->assertSame(2, $rowCount);

            $fresh = IconManager::getInstance()->icons->getIconsBySetId($setId);
            $this->assertCount(2, $fresh);
            $names = array_map(fn($icon) => $icon->name, $fresh);
            sort($names);
            $this->assertSame(['redis-fresh-a', 'redis-fresh-b'], $names);
        } finally {
            // Redis is non-transactional and the test re-warms the cache after
            // refresh — clean up explicitly so we don't leak keys to later runs.
            if ($cacheKey !== null && Craft::$app->cache instanceof \yii\redis\Cache) {
                Craft::$app->cache->delete($cacheKey);
                Craft::$app->cache->redis->executeCommand('SREM', [$trackingSet, $cacheKey]);
            }
        }
    }

    /**
     * Insert an iconset row + N stale icon rows by hand (bypassing
     * saveIconSet so we don't trigger an immediate scan-and-replace). Returns
     * the new id and a populated IconSet model.
     *
     * @return array{0:int,1:IconSet}
     */
    private function seedIconSet(string $type, int $iconCount, array $extraSettings = []): array
    {
        $handle = self::MARKER_PREFIX . 'set_' . bin2hex(random_bytes(3));
        $now = Db::prepareDateForDb(new \DateTime());

        Craft::$app->getDb()->createCommand()
            ->insert('{{%iconmanager_iconsets}}', [
                'name' => 'Icon Manager Test Set',
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
                    'metadata' => json_encode(['type' => 'sprite']),
                    'dateCreated' => $now,
                    'dateUpdated' => $now,
                    'uid' => StringHelper::UUID(),
                ])
                ->execute();
        }

        // The service caches IconSet lookups by id, so any previous test's
        // mutation needs clearing for the new id to resolve.
        IconManager::getInstance()->iconSets->clearCache();

        $iconSet = IconManager::getInstance()->iconSets->getIconSetById($setId);
        $this->assertNotNull($iconSet);

        $this->trackSeededSetId($setId);

        return [$setId, $iconSet];
    }

    private function cacheFilePath(IconSet $iconSet): string
    {
        $typeMap = [
            'svg-folder' => 'svg-folder',
            'svg-sprite' => 'svg-sprite',
            'material-icons' => 'material-icons',
            'font-awesome' => 'font-awesome',
            'web-font' => 'web-font',
        ];
        $typeFolder = $typeMap[$iconSet->type] ?? 'other';
        $path = PluginHelper::getCachePath(IconManager::$plugin, $typeFolder);
        FileHelper::createDirectory($path);

        return $path . 'set_' . $iconSet->id . '.cache';
    }
}
