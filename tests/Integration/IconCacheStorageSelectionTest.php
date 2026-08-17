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
use craft\cachecascade\CascadeCache;
use craft\helpers\App;
use lindemannrock\base\cache\CacheBackendStatus;
use lindemannrock\base\cache\DisposableCacheStorageDecision;
use lindemannrock\base\cache\DisposableCacheStoragePresenter;
use lindemannrock\base\cache\DisposableCacheStorageResolver;
use lindemannrock\base\cache\ScopedCache;
use lindemannrock\base\cache\ScopedCacheResult;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\iconmanager\IconManager;
use lindemannrock\iconmanager\models\Icon;
use lindemannrock\iconmanager\models\IconSet;
use lindemannrock\iconmanager\services\IconCacheService;
use lindemannrock\iconmanager\tests\TestCase;
use ReflectionClass;
use ReflectionProperty;
use yii\caching\ArrayCache;
use yii\caching\Cache;
use yii\caching\CacheInterface;
use yii\caching\DbCache;
use yii\caching\FileCache;
use yii\redis\Cache as RedisCache;

require_once dirname(__DIR__) . '/Fixtures/CascadeCache.php';

/**
 * Covers portable storage selection and Base API provenance.
 */
final class IconCacheStorageSelectionTest extends TestCase
{
    private CacheInterface $originalCache;
    private string $originalStorage;
    private bool $originalEnabled;
    private int $originalDuration;
    private string $originalApplicationId;
    private array $originalDiagnostics;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalCache = Craft::$app->getCache();
        $settings = IconManager::$plugin->getSettings();
        $this->originalStorage = $settings->cacheStorageMethod;
        $this->originalEnabled = $settings->enableCache;
        $this->originalDuration = $settings->cacheDuration;
        $this->originalApplicationId = Craft::$app->id;
        $this->originalDiagnostics = $this->diagnosticsProperty()->getValue();

        $settings->cacheStorageMethod = 'craft';
        $settings->enableCache = true;
        $settings->cacheDuration = 600;
    }

    protected function cleanupExternalState(): void
    {
        Craft::$app->set('cache', $this->originalCache);
        Craft::$app->id = $this->originalApplicationId;

        $settings = IconManager::$plugin->getSettings();
        $settings->cacheStorageMethod = $this->originalStorage;
        $settings->enableCache = $this->originalEnabled;
        $settings->cacheDuration = $this->originalDuration;
        $this->diagnosticsProperty()->setValue(null, $this->originalDiagnostics);

        parent::cleanupExternalState();
    }

    public function testApprovedBaseCacheApisLoadFromTheLocalCandidate(): void
    {
        $expectedBasePath = realpath(dirname(__DIR__, 3) . '/base/src');
        self::assertIsString($expectedBasePath);

        foreach ([
            CacheBackendStatus::class,
            DisposableCacheStorageDecision::class,
            DisposableCacheStoragePresenter::class,
            DisposableCacheStorageResolver::class,
            ScopedCache::class,
            ScopedCacheResult::class,
        ] as $class) {
            $filename = (new ReflectionClass($class))->getFileName();
            self::assertIsString($filename);
            self::assertStringStartsWith($expectedBasePath . DIRECTORY_SEPARATOR, (string)realpath($filename));
        }
    }

    public function testStorageMatrixUsesOnlyDurableFilesOrSuitableApplicationCaches(): void
    {
        $service = new IconCacheService();
        $redis = (new ReflectionClass(RedisCache::class))->newInstanceWithoutConstructor();
        self::assertInstanceOf(RedisCache::class, $redis);

        $cases = [
            [new ArrayCache(), 'file', false, DisposableCacheStorageDecision::EFFECTIVE_FILE],
            [new CascadeCache(), 'file', true, DisposableCacheStorageDecision::EFFECTIVE_APPLICATION],
            [new ArrayCache(), 'file', true, DisposableCacheStorageDecision::EFFECTIVE_DISABLED],
            [$redis, 'redis', false, DisposableCacheStorageDecision::EFFECTIVE_APPLICATION],
            [new CascadeCache(), 'craft', false, DisposableCacheStorageDecision::EFFECTIVE_APPLICATION],
            [new DbCache(), 'craft', true, DisposableCacheStorageDecision::EFFECTIVE_APPLICATION],
            [new FileCache(), 'craft', false, DisposableCacheStorageDecision::EFFECTIVE_APPLICATION],
            [new FileCache(), 'craft', true, DisposableCacheStorageDecision::EFFECTIVE_DISABLED],
            [new ArrayCache(), 'redis', false, DisposableCacheStorageDecision::EFFECTIVE_DISABLED],
            [new PortableUnknownCache(), 'craft', true, DisposableCacheStorageDecision::EFFECTIVE_APPLICATION],
        ];

        foreach ($cases as [$cache, $token, $ephemeral, $expected]) {
            self::assertInstanceOf(CacheInterface::class, $cache);
            Craft::$app->set('cache', $cache);
            $decision = $service->getStorageDecision($token, $ephemeral);
            self::assertSame($expected, $decision->effectiveStorage);
            if ($decision->usesApplicationCache()) {
                self::assertSame($cache, $decision->applicationCache);
            }
        }
    }

    public function testUnknownTokenAndUnavailableCacheDisablePersistenceWithoutGuessing(): void
    {
        Craft::$app->set('cache', static function(): never {
            throw new \RuntimeException('Cache resolution should not run for an unknown token.');
        });

        $service = new IconCacheService();
        $unknown = $service->getStorageDecision('unsupported', false);
        self::assertTrue($unknown->isDisabled());
        self::assertSame(
            DisposableCacheStorageDecision::REASON_UNKNOWN_CONFIGURED_TOKEN,
            $unknown->reasonCode,
        );

        $unavailable = $service->getStorageDecision('craft', false);
        self::assertTrue($unavailable->isDisabled());
        self::assertSame(CacheBackendStatus::BACKEND_UNAVAILABLE, $unavailable->backendStatus->backend);
    }

    public function testDisabledCacheRetainsRequestPolicyButPerformsNoPersistentOperations(): void
    {
        $cache = new PortableUnknownCache();
        Craft::$app->set('cache', $cache);
        $settings = IconManager::$plugin->getSettings();
        $settings->enableCache = false;

        $service = new IconCacheService();
        self::assertTrue($service->readMaterialDefinitions('icons')->isMiss());
        self::assertFalse($service->writeMaterialDefinitions('icons', []));
        self::assertSame(0, $cache->reads);
        self::assertSame(0, $cache->writes);

        $settings->enableCache = true;
        $settings->cacheDuration = 0;
        self::assertTrue($service->readMaterialDefinitions('icons')->isMiss());
        self::assertFalse($service->writeMaterialDefinitions('icons', []));
        self::assertSame(0, $cache->reads);
        self::assertSame(0, $cache->writes);
    }

    public function testEmptyValuesFiniteTtlAndFailuresRemainDistinct(): void
    {
        $cache = new PortableUnknownCache();
        Craft::$app->set('cache', $cache);
        $service = new IconCacheService();

        self::assertTrue($service->readMaterialDefinitions('icons')->isMiss());
        self::assertTrue($service->writeMaterialDefinitions('icons', []));
        $hit = $service->readMaterialDefinitions('icons');
        self::assertTrue($hit->isHit());
        self::assertSame([], $hit->value);
        self::assertContains(600, $cache->durations);

        self::assertTrue($service->writeMaterialDefinitions('symbols', ['symbol' => []]));
        self::assertTrue($service->writeFontAwesomeDefinitions('7.0.0', 'free', ['free' => []]));
        self::assertTrue($service->writeFontAwesomeDefinitions('7.0.0', 'pro', ['pro' => []]));
        self::assertSame([], $service->readMaterialDefinitions('icons')->value);
        self::assertSame(['symbol' => []], $service->readMaterialDefinitions('symbols')->value);
        self::assertSame(['free' => []], $service->readFontAwesomeDefinitions('7.0.0', 'free')->value);
        self::assertSame(['pro' => []], $service->readFontAwesomeDefinitions('7.0.0', 'pro')->value);

        Craft::$app->set('cache', new ThrowingPortableCache());
        $failure = $service->readMaterialDefinitions('icons');
        self::assertTrue($failure->isFailure());
        self::assertFalse($service->writeMaterialDefinitions('icons', []));
        self::assertFalse($service->invalidateFamily());
    }

    public function testMalformedApplicationCollectionIsRejectedAndRecomputed(): void
    {
        $cache = new PortableUnknownCache();
        Craft::$app->set('cache', $cache);
        $scoped = new ScopedCache($cache, 'icon-manager', IconCacheService::FAMILY);
        self::assertTrue($scoped->set(
            ['kind' => 'collection'],
            [new \stdClass()],
            600,
            ['kind' => 'set', 'id' => 902],
        ));

        $iconSet = new IconSet();
        $iconSet->id = 902;
        $iconSet->type = 'svg-folder';
        $result = (new IconCacheService())->readIconCollection(902, $iconSet);
        self::assertTrue($result->isFailure());
        self::assertTrue($scoped->get(
            ['kind' => 'collection'],
            ['kind' => 'set', 'id' => 902],
        )->isMiss());
    }

    public function testManagedDatabaseAndFilesystemApplicationCachesPersistValues(): void
    {
        $service = new IconCacheService();

        Craft::$app->set('cache', new CascadeCache());
        self::assertTrue($service->writeMaterialDefinitions('icons', []));
        self::assertTrue($service->readMaterialDefinitions('icons')->isHit());

        $databaseCache = new MemoryDatabaseCache();
        Craft::$app->set('cache', $databaseCache);
        self::assertTrue($service->writeMaterialDefinitions('symbols', []));
        self::assertTrue($service->readMaterialDefinitions('symbols')->isHit());

        $fileCache = new FileCache([
            'cachePath' => $this->createTrackedTempDirectory(self::MARKER_PREFIX . 'application_file_'),
            'keyPrefix' => self::MARKER_PREFIX . bin2hex(random_bytes(4)),
        ]);
        Craft::$app->set('cache', $fileCache);
        self::assertTrue($service->writeFontAwesomeDefinitions('test', 'free', []));
        self::assertTrue($service->readFontAwesomeDefinitions('test', 'free')->isHit());
    }

    public function testDirectRedisApplicationCachePersistsWithoutTrackingSets(): void
    {
        $redisHost = App::env('REDIS_HOST');
        if (!$redisHost) {
            self::markTestSkipped('Redis is not configured for this environment.');
        }

        Craft::$app->id = 'icon-manager-cache-test-' . bin2hex(random_bytes(5));
        $cache = new TrackingRedisApplicationCache([
            'keyPrefix' => Craft::$app->id,
            'defaultDuration' => 600,
            'redis' => [
                'class' => \yii\redis\Connection::class,
                'hostname' => $redisHost,
                'port' => App::env('REDIS_PORT') ?: 6379,
                'password' => App::env('REDIS_PASSWORD') ?: null,
                'database' => (int)(App::env('REDIS_DATABASE') ?: 0),
            ],
        ]);
        Craft::$app->set('cache', $cache);
        $service = new IconCacheService();

        try {
            self::assertTrue($service->writeMaterialDefinitions('icons', []));
            self::assertTrue($service->readMaterialDefinitions('icons')->isHit());

            $iconSet = new IconSet();
            $iconSet->id = 901;
            $iconSet->handle = 'redis-serialization';
            $iconSet->type = 'svg-folder';
            $icon = new Icon();
            $icon->name = 'serialized';
            self::assertTrue($service->writeIconCollection(901, $iconSet, [$icon]));
            $collection = $service->readIconCollection(901, $iconSet);
            self::assertTrue($collection->isHit());
            self::assertContainsOnlyInstancesOf(Icon::class, $collection->value);
            self::assertNotEmpty($cache->logicalKeys);
        } finally {
            foreach (array_keys($cache->logicalKeys) as $key) {
                $cache->delete($key);
            }
            $cache->redis->close();
        }
    }

    public function testEphemeralDisabledStorageNeverTouchesRuntimeCachePaths(): void
    {
        Craft::$app->set('cache', new ArrayCache());
        IconManager::$plugin->getSettings()->cacheStorageMethod = 'file';
        $service = new PathObservingIconCacheService();

        self::assertTrue($service->readMaterialDefinitions('icons')->isMiss());
        self::assertFalse($service->writeMaterialDefinitions('icons', []));
        self::assertTrue($service->invalidateSet(42, ['svg-folder']));
        self::assertTrue($service->invalidateFamily());
        self::assertTrue($service->invalidateStorageTransition('file', 'craft'));
        self::assertNull($service->countFileCaches());
        self::assertNull($service->getDisplayFilePath());
        self::assertSame(0, $service->pathAccesses);
    }

    public function testPresentationAndTemplatesUseSharedBackendNeutralContracts(): void
    {
        Craft::$app->set('cache', new CascadeCache());
        $service = new IconCacheService();
        $presentation = (new DisposableCacheStoragePresenter())->present(
            $service->getStorageDecision('file', true),
        );

        self::assertSame('Using managed cache', $presentation->headingKey);
        self::assertSame(
            ['This host has an ephemeral filesystem, so the application cache is used automatically.'],
            $presentation->explanationKeys,
        );
        self::assertFalse($presentation->filePathEligible);

        $settingsTemplate = file_get_contents(dirname(__DIR__, 2) . '/src/templates/settings/cache.twig');
        $utilityTemplate = file_get_contents(dirname(__DIR__, 2) . '/src/templates/utilities/index.twig');
        self::assertIsString($settingsTemplate);
        self::assertIsString($utilityTemplate);
        self::assertStringContainsString("'lindemannrock-base/_partials/field-cache-storage'", $settingsTemplate);
        self::assertStringContainsString('cacheStorage.utilityValueKey', $utilityTemplate);
        self::assertStringContainsString("|t('lindemannrock-base')", $utilityTemplate);
        self::assertStringNotContainsString('yii\\redis\\Cache', $settingsTemplate);
        self::assertStringNotContainsString('className', $settingsTemplate);
        self::assertStringNotContainsString('Redis Not Configured', $settingsTemplate);
    }

    public function testProductionCacheCodeContainsNoRedisEnumerationOrTrackingOperations(): void
    {
        $sourceFiles = [
            dirname(__DIR__, 2) . '/src/IconManager.php',
            dirname(__DIR__, 2) . '/src/controllers/CacheController.php',
            dirname(__DIR__, 2) . '/src/services/IconCacheService.php',
            dirname(__DIR__, 2) . '/src/services/IconsService.php',
        ];
        $source = '';
        foreach ($sourceFiles as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);
            $source .= $contents;
        }

        foreach (['executeCommand', 'getCacheKeySet', 'iconmanager-icons-keys', 'SMEMBERS', 'SADD', 'SREM', 'SCAN', 'KEYS', 'flush'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $source);
        }
    }

    private function diagnosticsProperty(): ReflectionProperty
    {
        return new ReflectionProperty(PluginHelper::class, 'applicationCacheDiagnostics');
    }
}

/**
 * Unknown cache implementation accepted on a best-effort basis.
 *
 * @since 5.18.0
 */
class PortableUnknownCache extends Cache
{
    /** @var array<string, mixed> */
    private array $values = [];

    public int $reads = 0;
    public int $writes = 0;

    /** @var list<int> */
    public array $durations = [];

    protected function getValue($key)
    {
        $this->reads++;
        return $this->values[$key] ?? false;
    }

    protected function setValue($key, $value, $duration)
    {
        $this->writes++;
        $this->durations[] = (int)$duration;
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
 * Application cache that fails every operation.
 *
 * @since 5.18.0
 */
final class ThrowingPortableCache extends PortableUnknownCache
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

/**
 * Ephemeral-host seam that fails if a runtime cache path is requested.
 *
 * @since 5.18.0
 */
final class PathObservingIconCacheService extends IconCacheService
{
    public int $pathAccesses = 0;

    protected function isEphemeralHost(): bool
    {
        return true;
    }

    protected function getCacheBasePath(): string
    {
        $this->pathAccesses++;
        throw new \LogicException('Runtime cache base path must not be accessed.');
    }

    protected function getCachePath(string $type): string
    {
        $this->pathAccesses++;
        throw new \LogicException('Runtime cache path must not be accessed.');
    }
}

/**
 * Database-cache capability fixture with isolated in-memory values.
 *
 * @since 5.18.0
 */
final class MemoryDatabaseCache extends DbCache
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
 * Redis cache that records every exact logical key for cleanup.
 *
 * @since 5.18.0
 */
final class TrackingRedisApplicationCache extends RedisCache
{
    /** @var array<string, true> */
    public array $logicalKeys = [];

    public function buildKey($key)
    {
        if (is_string($key)) {
            $this->logicalKeys[$key] = true;
        }

        return parent::buildKey($key);
    }
}
