<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\iconmanager\services;

use Craft;
use craft\base\Component;
use craft\helpers\App;
use craft\helpers\FileHelper;
use lindemannrock\base\cache\DisposableCacheStorageDecision;
use lindemannrock\base\cache\DisposableCacheStorageResolver;
use lindemannrock\base\cache\ScopedCache;
use lindemannrock\base\cache\ScopedCacheResult;
use lindemannrock\base\helpers\CacheHelper;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\iconmanager\IconManager;
use lindemannrock\iconmanager\models\Icon;
use lindemannrock\iconmanager\models\IconSet;

/**
 * Owns Icon Manager's disposable cache storage and invalidation boundaries.
 *
 * @since 5.18.0
 */
class IconCacheService extends Component
{
    public const FAMILY = 'icons';

    /** @var list<string> */
    private const FILE_TYPES = [
        'svg-folder',
        'svg-sprite',
        'material-icons',
        'font-awesome',
        'web-font',
        'other',
    ];

    /** @var array<string, string> */
    private const TYPE_FOLDERS = [
        'svg-folder' => 'svg-folder',
        'svg-sprite' => 'svg-sprite',
        'material-icons' => 'material-icons',
        'font-awesome' => 'font-awesome',
        'web-font' => 'web-font',
    ];

    /** @var array<string, true> */
    private static array $loggedFailures = [];

    public function getStorageDecision(
        ?string $configuredStorage = null,
        ?bool $ephemeralHost = null,
    ): DisposableCacheStorageDecision {
        $configuredStorage ??= IconManager::$plugin->getSettings()->cacheStorageMethod;

        return (new DisposableCacheStorageResolver())->resolve(
            configuredStorageToken: $configuredStorage,
            diagnosticContext: IconManager::$plugin->id . ':disposable-cache',
            ephemeralHost: $ephemeralHost ?? $this->isEphemeralHost(),
        );
    }

    public function getDisplayFilePath(?DisposableCacheStorageDecision $decision = null): ?string
    {
        $decision ??= $this->getStorageDecision();
        if (!$decision->usesFileCache() || !$decision->filePathEligible) {
            return null;
        }

        return $this->getCacheBasePath();
    }

    public function readIconCollection(int $iconSetId, IconSet $iconSet): ScopedCacheResult
    {
        if (!$this->persistentCacheEnabled()) {
            return ScopedCacheResult::miss();
        }

        $decision = $this->getStorageDecision();
        if ($decision->usesApplicationCache()) {
            return $this->readApplicationValue(
                $decision,
                ['kind' => 'collection'],
                $this->setScope($iconSetId),
                fn(mixed $value): bool => $this->isIconCollection($value),
                'collection-read',
            );
        }
        if ($decision->usesFileCache()) {
            return $this->readSerializedCollectionFile(
                $this->getIconCollectionFile($iconSetId, $iconSet->type),
                IconManager::$plugin->getSettings()->cacheDuration,
            );
        }

        return ScopedCacheResult::miss();
    }

    /** @param list<Icon> $icons */
    public function writeIconCollection(int $iconSetId, IconSet $iconSet, array $icons): bool
    {
        if (!$this->persistentCacheEnabled()) {
            return false;
        }

        $ttl = IconManager::$plugin->getSettings()->cacheDuration;
        $decision = $this->getStorageDecision();
        if ($decision->usesApplicationCache()) {
            return $this->writeApplicationValue(
                $decision,
                ['kind' => 'collection'],
                $icons,
                $ttl,
                $this->setScope($iconSetId),
                'collection-write',
            );
        }
        if ($decision->usesFileCache()) {
            return $this->writeFile(
                $this->getIconCollectionFile($iconSetId, $iconSet->type),
                serialize($icons),
                'collection-file-write',
            );
        }

        return false;
    }

    public function readFontAwesomeDefinitions(string $version, string $license): ScopedCacheResult
    {
        return $this->readDefinitions(
            ['kind' => 'definitions', 'type' => 'font-awesome'],
            ['version' => $version, 'license' => $license],
            'font-awesome',
            $version . '_' . $license,
            'font-awesome-definitions-read',
        );
    }

    public function writeFontAwesomeDefinitions(string $version, string $license, array $definitions): bool
    {
        return $this->writeDefinitions(
            ['kind' => 'definitions', 'type' => 'font-awesome'],
            ['version' => $version, 'license' => $license],
            'font-awesome',
            $version . '_' . $license,
            $definitions,
            'font-awesome-definitions-write',
        );
    }

    public function readMaterialDefinitions(string $materialType): ScopedCacheResult
    {
        return $this->readDefinitions(
            ['kind' => 'definitions', 'type' => 'material-icons'],
            ['materialType' => $materialType],
            'material-icons',
            $materialType,
            'material-definitions-read',
        );
    }

    public function writeMaterialDefinitions(string $materialType, array $definitions): bool
    {
        return $this->writeDefinitions(
            ['kind' => 'definitions', 'type' => 'material-icons'],
            ['materialType' => $materialType],
            'material-icons',
            $materialType,
            $definitions,
            'material-definitions-write',
        );
    }

    /** @param list<string> $iconSetTypes */
    public function invalidateSet(int $iconSetId, array $iconSetTypes): bool
    {
        try {
            $decision = $this->getStorageDecision();
            if ($decision->usesApplicationCache()) {
                $cache = $this->getScopedCache($decision);
                $invalidated = $cache?->invalidateScope($this->setScope($iconSetId)) === true;
                if (!$invalidated) {
                    $this->logFailure('invalidate-set');
                }
                return $invalidated;
            }
            if (!$decision->usesFileCache()) {
                return true;
            }

            $success = true;
            foreach (array_unique($iconSetTypes) as $type) {
                $file = $this->getIconCollectionFile($iconSetId, $type);
                if (@is_file($file) && !@unlink($file)) {
                    $success = false;
                }
            }
            if (!$success) {
                $this->logFailure('invalidate-set-files');
            }

            return $success;
        } catch (\Throwable $e) {
            $this->logFailure('invalidate-set', $e);
            return false;
        }
    }

    public function invalidateFamily(): bool
    {
        try {
            return $this->invalidateDecision($this->getStorageDecision());
        } catch (\Throwable $e) {
            $this->logFailure('invalidate-family', $e);
            return false;
        }
    }

    public function invalidateStorageTransition(string $oldStorage, string $newStorage): bool
    {
        $success = true;
        $seen = [];

        foreach (array_unique([$oldStorage, $newStorage]) as $storage) {
            try {
                $decision = $this->getStorageDecision($storage);
            } catch (\Throwable $e) {
                $this->logFailure('invalidate-storage-transition', $e);
                $success = false;
                continue;
            }
            $identity = $decision->effectiveStorage;
            if ($decision->usesApplicationCache() && $decision->applicationCache !== null) {
                $identity .= ':' . spl_object_id($decision->applicationCache);
            }
            if (isset($seen[$identity])) {
                continue;
            }
            $seen[$identity] = true;
            $success = $this->invalidateDecision($decision) && $success;
        }

        return $success;
    }

    /** @return array<string, int>|null */
    public function countFileCaches(?DisposableCacheStorageDecision $decision = null): ?array
    {
        $decision ??= $this->getStorageDecision();
        if (!$decision->usesFileCache()) {
            return null;
        }

        $counts = [];
        foreach (self::FILE_TYPES as $type) {
            try {
                $counts[$type] = CacheHelper::countCacheFiles($this->getCachePath($type));
            } catch (\Throwable $e) {
                $counts[$type] = 0;
                $this->logFailure('count-files-' . $type, $e);
            }
        }

        return $counts;
    }

    protected function isEphemeralHost(): bool
    {
        return App::isEphemeral();
    }

    protected function getCacheBasePath(): string
    {
        return PluginHelper::getCacheBasePath(IconManager::$plugin);
    }

    protected function getCachePath(string $type): string
    {
        return PluginHelper::getCachePath(IconManager::$plugin, $type);
    }

    private function persistentCacheEnabled(): bool
    {
        $settings = IconManager::$plugin->getSettings();
        return $settings->enableCache && $settings->cacheDuration > 0;
    }

    private function readDefinitions(
        array $scope,
        array $item,
        string $fileType,
        string $fileIdentity,
        string $operation,
    ): ScopedCacheResult {
        if (!$this->persistentCacheEnabled()) {
            return ScopedCacheResult::miss();
        }

        $decision = $this->getStorageDecision();
        if ($decision->usesApplicationCache()) {
            return $this->readApplicationValue(
                $decision,
                $item,
                $scope,
                static fn(mixed $value): bool => is_array($value),
                $operation,
            );
        }
        if ($decision->usesFileCache()) {
            return $this->readJsonFile(
                $this->getDefinitionFile($fileType, $fileIdentity),
                IconManager::$plugin->getSettings()->cacheDuration,
                $operation,
            );
        }

        return ScopedCacheResult::miss();
    }

    private function writeDefinitions(
        array $scope,
        array $item,
        string $fileType,
        string $fileIdentity,
        array $definitions,
        string $operation,
    ): bool {
        if (!$this->persistentCacheEnabled()) {
            return false;
        }

        $ttl = IconManager::$plugin->getSettings()->cacheDuration;
        $decision = $this->getStorageDecision();
        if ($decision->usesApplicationCache()) {
            return $this->writeApplicationValue($decision, $item, $definitions, $ttl, $scope, $operation);
        }
        if ($decision->usesFileCache()) {
            $encoded = json_encode($definitions);
            if (!is_string($encoded)) {
                $this->logFailure($operation . '-encode');
                return false;
            }
            return $this->writeFile(
                $this->getDefinitionFile($fileType, $fileIdentity),
                $encoded,
                $operation . '-file',
            );
        }

        return false;
    }

    private function readApplicationValue(
        DisposableCacheStorageDecision $decision,
        array $item,
        array $scope,
        callable $validator,
        string $operation,
    ): ScopedCacheResult {
        $cache = $this->getScopedCache($decision);
        if ($cache === null) {
            return ScopedCacheResult::failure();
        }

        $result = $cache->get($item, $scope);
        if ($result->isFailure()) {
            $this->logFailure($operation);
            return $result;
        }
        if ($result->isHit() && !$validator($result->value)) {
            $cache->delete($item, $scope);
            $this->logFailure($operation . '-invalid-value');
            return ScopedCacheResult::failure();
        }

        return $result;
    }

    private function writeApplicationValue(
        DisposableCacheStorageDecision $decision,
        array $item,
        mixed $value,
        int $ttl,
        array $scope,
        string $operation,
    ): bool {
        $written = $this->getScopedCache($decision)?->set($item, $value, $ttl, $scope) === true;
        if (!$written) {
            $this->logFailure($operation);
        }

        return $written;
    }

    private function getScopedCache(DisposableCacheStorageDecision $decision): ?ScopedCache
    {
        if ($decision->applicationCache === null) {
            return null;
        }

        try {
            return new ScopedCache(
                $decision->applicationCache,
                IconManager::$plugin->id,
                self::FAMILY,
            );
        } catch (\Throwable $e) {
            $this->logFailure('initialize', $e);
            return null;
        }
    }

    private function readSerializedCollectionFile(string $file, int $ttl): ScopedCacheResult
    {
        $content = $this->readFileContent($file, $ttl, 'collection-file-read');
        if (!$content->isHit()) {
            return $content;
        }

        $icons = @unserialize((string)$content->value, ['allowed_classes' => [Icon::class]]);
        if (!$this->isIconCollection($icons)) {
            @unlink($file);
            $this->logFailure('collection-file-invalid');
            return ScopedCacheResult::failure();
        }

        return ScopedCacheResult::hit($icons);
    }

    private function readJsonFile(string $file, int $ttl, string $operation): ScopedCacheResult
    {
        $content = $this->readFileContent($file, $ttl, $operation);
        if (!$content->isHit()) {
            return $content;
        }

        $decoded = json_decode((string)$content->value, true);
        if (!is_array($decoded)) {
            @unlink($file);
            $this->logFailure($operation . '-invalid-json');
            return ScopedCacheResult::failure();
        }

        return ScopedCacheResult::hit($decoded);
    }

    private function readFileContent(string $file, int $ttl, string $operation): ScopedCacheResult
    {
        try {
            if (!@is_file($file)) {
                return ScopedCacheResult::miss();
            }

            $mtime = @filemtime($file);
            if ($mtime === false) {
                $this->logFailure($operation . '-timestamp');
                return ScopedCacheResult::failure();
            }
            if (time() - $mtime > $ttl) {
                @unlink($file);
                return ScopedCacheResult::miss();
            }

            $content = @file_get_contents($file);
            if (!is_string($content)) {
                $this->logFailure($operation);
                return ScopedCacheResult::failure();
            }

            return ScopedCacheResult::hit($content);
        } catch (\Throwable $e) {
            $this->logFailure($operation, $e);
            return ScopedCacheResult::failure();
        }
    }

    private function writeFile(string $file, string $content, string $operation): bool
    {
        try {
            $directory = dirname($file);
            if (!@is_dir($directory)) {
                FileHelper::createDirectory($directory);
            }
            if (@file_put_contents($file, $content, LOCK_EX) === false) {
                throw new \RuntimeException('Cache file could not be written.');
            }
            return true;
        } catch (\Throwable $e) {
            $this->logFailure($operation, $e);
            return false;
        }
    }

    private function invalidateDecision(DisposableCacheStorageDecision $decision): bool
    {
        if ($decision->usesApplicationCache()) {
            $invalidated = $this->getScopedCache($decision)?->invalidateFamily() === true;
            if (!$invalidated) {
                $this->logFailure('invalidate-family');
            }
            return $invalidated;
        }
        if (!$decision->usesFileCache()) {
            return true;
        }

        $success = true;
        foreach (self::FILE_TYPES as $type) {
            try {
                CacheHelper::clearCacheFiles($this->getCachePath($type));
            } catch (\Throwable $e) {
                $success = false;
                $this->logFailure('clear-files-' . $type, $e);
            }
        }

        return $success;
    }

    /** @return array{kind: string, id: int} */
    private function setScope(int $iconSetId): array
    {
        return ['kind' => 'set', 'id' => $iconSetId];
    }

    private function getIconCollectionFile(int $iconSetId, string $iconSetType): string
    {
        $typeFolder = self::TYPE_FOLDERS[$iconSetType] ?? 'other';
        return $this->getCachePath($typeFolder) . 'set_' . $iconSetId . '.cache';
    }

    private function getDefinitionFile(string $type, string $identity): string
    {
        return $this->getCachePath($type) . $identity . '.cache';
    }

    private function isIconCollection(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $icon) {
            if (!$icon instanceof Icon) {
                return false;
            }
        }

        return true;
    }

    private function logFailure(string $operation, ?\Throwable $exception = null): void
    {
        if (isset(self::$loggedFailures[$operation])) {
            return;
        }
        self::$loggedFailures[$operation] = true;

        Craft::warning(sprintf(
            'Icon Manager cache %s failed%s; disposable data will be recomputed.',
            $operation,
            $exception === null ? '' : ' (' . $exception::class . ')',
        ), 'icon-manager');
    }
}
