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
use lindemannrock\iconmanager\iconsets\FontAwesome;
use lindemannrock\iconmanager\iconsets\MaterialIcons;
use lindemannrock\iconmanager\IconManager;
use lindemannrock\iconmanager\models\Icon;
use lindemannrock\iconmanager\models\IconSet;
use lindemannrock\iconmanager\services\IconCacheService;
use lindemannrock\iconmanager\tests\TestCase;

/**
 * Covers durable collection and decoded-definition cache compatibility.
 */
final class IconDefinitionCacheTest extends TestCase
{
    private string $originalRuntimePath;
    private string $originalStorage;
    private bool $originalEnabled;
    private int $originalDuration;
    private string $runtimePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalRuntimePath = Craft::$app->getRuntimePath();
        $this->runtimePath = $this->createTrackedTempDirectory(self::MARKER_PREFIX . 'cache_');
        Craft::$app->setRuntimePath($this->runtimePath);

        $settings = IconManager::$plugin->getSettings();
        $this->originalStorage = $settings->cacheStorageMethod;
        $this->originalEnabled = $settings->enableCache;
        $this->originalDuration = $settings->cacheDuration;
        $settings->cacheStorageMethod = 'file';
        $settings->enableCache = true;
        $settings->cacheDuration = 600;
    }

    protected function cleanupExternalState(): void
    {
        Craft::$app->setRuntimePath($this->originalRuntimePath);
        $settings = IconManager::$plugin->getSettings();
        $settings->cacheStorageMethod = $this->originalStorage;
        $settings->enableCache = $this->originalEnabled;
        $settings->cacheDuration = $this->originalDuration;

        parent::cleanupExternalState();
    }

    public function testCollectionFileKeepsItsPathSerializationAndAllowedIconClassContract(): void
    {
        $service = new IconCacheService();
        $iconSet = $this->iconSet(71, 'svg-sprite');
        $icon = new Icon();
        $icon->name = 'durable';

        self::assertTrue($service->writeIconCollection(71, $iconSet, [$icon]));
        $file = $this->cacheFile('svg-sprite', 'set_71.cache');
        self::assertFileExists($file);

        $serialized = file_get_contents($file);
        self::assertIsString($serialized);
        $decoded = unserialize($serialized, ['allowed_classes' => [Icon::class]]);
        self::assertIsArray($decoded);
        self::assertContainsOnlyInstancesOf(Icon::class, $decoded);

        $hit = $service->readIconCollection(71, $iconSet);
        self::assertTrue($hit->isHit());
        self::assertCount(1, $hit->value);
    }

    public function testCorruptExpiredAndDisallowedCollectionFilesFailSafely(): void
    {
        $service = new IconCacheService();
        $iconSet = $this->iconSet(72, 'svg-folder');
        $file = $this->cacheFile('svg-folder', 'set_72.cache');
        $this->ensureDirectory(dirname($file));

        file_put_contents($file, serialize([new \stdClass()]));
        self::assertTrue($service->readIconCollection(72, $iconSet)->isFailure());
        self::assertFileDoesNotExist($file);

        file_put_contents($file, 'not-serialized-data');
        self::assertTrue($service->readIconCollection(72, $iconSet)->isFailure());
        self::assertFileDoesNotExist($file);

        $icon = new Icon();
        file_put_contents($file, serialize([$icon]));
        touch($file, time() - 601);
        self::assertTrue($service->readIconCollection(72, $iconSet)->isMiss());
        self::assertFileDoesNotExist($file);
    }

    public function testDefinitionFilesKeepJsonPathsAndRecoverFromCorruptionAndExpiry(): void
    {
        $service = new IconCacheService();
        $fontAwesome = ['address-book' => ['styles' => ['solid']]];
        $material = ['home' => ['styles' => ['filled']]];

        self::assertTrue($service->writeFontAwesomeDefinitions('7.0.0', 'free', $fontAwesome));
        self::assertTrue($service->writeMaterialDefinitions('icons', $material));
        $fontFile = $this->cacheFile('font-awesome', '7.0.0_free.cache');
        $materialFile = $this->cacheFile('material-icons', 'icons.cache');
        self::assertSame($fontAwesome, json_decode((string)file_get_contents($fontFile), true));
        self::assertSame($material, json_decode((string)file_get_contents($materialFile), true));

        file_put_contents($fontFile, 'invalid-json');
        self::assertTrue($service->readFontAwesomeDefinitions('7.0.0', 'free')->isFailure());
        self::assertFileDoesNotExist($fontFile);

        touch($materialFile, time() - 601);
        self::assertTrue($service->readMaterialDefinitions('icons')->isMiss());
        self::assertFileDoesNotExist($materialFile);
    }

    public function testHandlersRecomputePackagedDefinitionsAndRepairCorruptFiles(): void
    {
        $fontFile = $this->cacheFile('font-awesome', '7.0.0_free.cache');
        $materialFile = $this->cacheFile('material-icons', 'icons.cache');
        $this->ensureDirectory(dirname($fontFile));
        $this->ensureDirectory(dirname($materialFile));
        file_put_contents($fontFile, 'corrupt');
        file_put_contents($materialFile, 'corrupt');

        $fontSet = $this->iconSet(73, 'font-awesome', [
            'type' => FontAwesome::TYPE_CDN,
            'version' => '7.0.0',
            'license' => FontAwesome::LICENSE_FREE,
            'styles' => ['solid'],
        ]);
        $materialSet = $this->iconSet(74, 'material-icons', [
            'materialType' => MaterialIcons::TYPE_ICONS,
            'styles' => [MaterialIcons::STYLE_FILLED],
        ]);

        self::assertNotEmpty((new FontAwesome($fontSet))->getIcons());
        self::assertNotEmpty((new MaterialIcons($materialSet))->getIcons());
        self::assertIsArray(json_decode((string)file_get_contents($fontFile), true));
        self::assertIsArray(json_decode((string)file_get_contents($materialFile), true));
    }

    public function testSetInvalidationIsTypeAwareOnlyForFilesAndPreservesDefinitions(): void
    {
        $service = new IconCacheService();
        $icon = new Icon();
        self::assertTrue($service->writeIconCollection(75, $this->iconSet(75, 'svg-folder'), [$icon]));
        self::assertTrue($service->writeIconCollection(75, $this->iconSet(75, 'web-font'), [$icon]));
        self::assertTrue($service->writeIconCollection(76, $this->iconSet(76, 'svg-folder'), [$icon]));
        self::assertTrue($service->writeMaterialDefinitions('icons', ['home' => []]));

        self::assertTrue($service->invalidateSet(75, ['svg-folder', 'web-font']));
        self::assertFileDoesNotExist($this->cacheFile('svg-folder', 'set_75.cache'));
        self::assertFileDoesNotExist($this->cacheFile('web-font', 'set_75.cache'));
        self::assertFileExists($this->cacheFile('svg-folder', 'set_76.cache'));
        self::assertFileExists($this->cacheFile('material-icons', 'icons.cache'));
    }

    public function testFamilyInvalidationOwnsCollectionsDefinitionsAndOtherTypeFiles(): void
    {
        $service = new IconCacheService();
        $icon = new Icon();
        self::assertTrue($service->writeIconCollection(77, $this->iconSet(77, 'custom-type'), [$icon]));
        self::assertTrue($service->writeFontAwesomeDefinitions('7.0.0', 'free', ['home' => []]));
        self::assertTrue($service->writeMaterialDefinitions('icons', ['home' => []]));

        $counts = $service->countFileCaches();
        self::assertIsArray($counts);
        self::assertSame(1, $counts['other']);
        self::assertSame(1, $counts['font-awesome']);
        self::assertSame(1, $counts['material-icons']);

        self::assertTrue($service->invalidateFamily());
        foreach (['other', 'font-awesome', 'material-icons'] as $type) {
            self::assertSame([], glob($this->cacheFile($type, '*.cache')));
        }
    }

    private function iconSet(int $id, string $type, array $settings = []): IconSet
    {
        $iconSet = new IconSet();
        $iconSet->id = $id;
        $iconSet->handle = 'test-' . $id;
        $iconSet->type = $type;
        $iconSet->settings = $settings;

        return $iconSet;
    }

    private function cacheFile(string $type, string $filename): string
    {
        return $this->runtimePath . '/icon-manager/cache/' . $type . '/' . $filename;
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }
}
