<?php
/**
 * LindemannRock Icon Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\iconmanager\tests;

use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\base\testing\IntegrationTestCase;
use lindemannrock\iconmanager\IconManager;

/**
 * Base test case for icon-manager integration tests.
 *
 * Adds three pieces of plugin-specific shorthand on top of the shared base:
 *  - {@see seedTempIconRoot()} returns a per-test temp directory + points the
 *    live Settings model at it for the duration of the test (restored in
 *    tearDown). Sprite scanner + folder scanner both call
 *    Settings::getResolvedIconSetsPath(), so re-pointing the alias is the only
 *    fixture seam that avoids touching the real `@root/icons` tree.
 *  - {@see seededIconSetIds()} tracks IDs seeded directly into
 *    `iconmanager_iconsets` so tearDown can both purge the rows and drop the
 *    matching `set_{id}.cache` files written by IconsService.
 *  - {@see cleanupExternalState()} clears the in-memory icon cache (the
 *    IconsService is a Yii singleton with a per-set array that bleeds across
 *    tests) and purges any per-set file cache the test wrote.
 */
abstract class TestCase extends IntegrationTestCase
{
    public const MARKER_PREFIX = '__im_test_';

    private ?string $tempIconRoot = null;
    private ?string $savedIconSetsPath = null;

    /**
     * IDs of `iconmanager_iconsets` rows seeded directly by the test. Used by
     * cleanupExternalState() to drop their `set_{id}.cache` files in addition
     * to the marker-based row purge.
     *
     * @var list<int>
     */
    private array $seededSetIds = [];

    protected function tearDown(): void
    {
        // Purge seeded rows BEFORE base class restores stubbed components — the
        // memory cache reset in cleanupExternalState() also runs there.
        $this->purgeRowsByMarker('{{%iconmanager_icons}}', 'name', self::MARKER_PREFIX);
        $this->purgeRowsByMarker('{{%iconmanager_iconsets}}', 'handle', self::MARKER_PREFIX);

        try {
            parent::tearDown();
        } finally {
            if ($this->savedIconSetsPath !== null) {
                IconManager::getInstance()->getSettings()->iconSetsPath = $this->savedIconSetsPath;
                $this->savedIconSetsPath = null;
            }
            $this->tempIconRoot = null;
        }
    }

    protected function cleanupExternalState(): void
    {
        // Memory cache is a property on the singleton IconsService — leaks
        // across tests if not reset.
        IconManager::getInstance()->icons->clearMemoryCache();

        // Per-set file caches written under storage/runtime/icon-manager/cache/
        // need explicit cleanup because the cache filename encodes the set id.
        foreach ($this->seededSetIds as $setId) {
            foreach (['svg-folder', 'svg-sprite', 'material-icons', 'font-awesome', 'web-font', 'other'] as $typeFolder) {
                $cacheFile = PluginHelper::getCachePath(IconManager::$plugin, $typeFolder) . 'set_' . $setId . '.cache';
                if (file_exists($cacheFile)) {
                    @unlink($cacheFile);
                }
            }
        }
        $this->seededSetIds = [];
    }

    /**
     * Create a unique temp directory under the system temp root and re-point
     * the live Settings model's `iconSetsPath` at it. The original alias is
     * restored in tearDown.
     */
    protected function seedTempIconRoot(): string
    {
        if ($this->tempIconRoot !== null) {
            return $this->tempIconRoot;
        }

        $root = $this->createTrackedTempDirectory(self::MARKER_PREFIX);

        $settings = IconManager::getInstance()->getSettings();
        $this->savedIconSetsPath = $settings->iconSetsPath;
        $settings->iconSetsPath = $root;

        $this->tempIconRoot = $root;

        return $root;
    }

    /**
     * Register a seeded icon-set id so its file cache is dropped in tearDown.
     */
    protected function trackSeededSetId(int $id): void
    {
        $this->seededSetIds[] = $id;
    }
}
