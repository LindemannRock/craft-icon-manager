<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\iconmanager\controllers;

use Craft;
use craft\web\Controller;
use lindemannrock\iconmanager\IconManager;
use lindemannrock\iconmanager\traits\LoggingTrait;
use yii\web\Response;

/**
 * Cache Controller
 */
class CacheController extends Controller
{
    use LoggingTrait;

    /**
     * Clear all icon caches
     */
    public function actionClear(): mixed
    {
        $this->requirePostRequest();
        
        try {
            $this->logInfo("Starting icon cache clearing operation");

            $runtimePath = Craft::$app->path->getRuntimePath();
            $totalCleared = 0;

            // Clear icon set caches from custom file storage
            $iconsCachePath = $runtimePath . '/icon-manager/icons/';
            $iconsCacheCount = 0;
            if (is_dir($iconsCachePath)) {
                $cacheFiles = glob($iconsCachePath . '*.cache');
                foreach ($cacheFiles as $file) {
                    if (@unlink($file)) {
                        $iconsCacheCount++;
                    }
                }
            }
            $this->logTrace("Cleared {$iconsCacheCount} icon set cache files");

            // Clear Font Awesome caches from custom file storage
            $faCachePath = $runtimePath . '/icon-manager/fontawesome/';
            $faCacheCount = 0;
            if (is_dir($faCachePath)) {
                $cacheFiles = glob($faCachePath . '*.cache');
                foreach ($cacheFiles as $file) {
                    if (@unlink($file)) {
                        $faCacheCount++;
                    }
                }
            }
            $this->logTrace("Cleared {$faCacheCount} Font Awesome cache files");

            // Clear Material Icons caches from custom file storage
            $materialCachePath = $runtimePath . '/icon-manager/material/';
            $materialCacheCount = 0;
            if (is_dir($materialCachePath)) {
                $cacheFiles = glob($materialCachePath . '*.cache');
                foreach ($cacheFiles as $file) {
                    if (@unlink($file)) {
                        $materialCacheCount++;
                    }
                }
            }
            $this->logTrace("Cleared {$materialCacheCount} Material Icons cache files");

            // Clear memory caches
            IconManager::getInstance()->icons->clearMemoryCache();

            $totalCleared = $iconsCacheCount + $faCacheCount + $materialCacheCount;
            $this->logInfo("Icon cache cleared successfully", [
                'totalCaches' => $totalCleared,
                'iconSets' => $iconsCacheCount,
                'fontAwesome' => $faCacheCount,
                'materialIcons' => $materialCacheCount
            ]);

            Craft::$app->getSession()->setNotice(
                Craft::t('icon-manager', 'Icon cache cleared.')
            );

            return $this->redirectToPostedUrl();
        } catch (\Throwable $e) {
            $this->logError("Failed to clear icon cache: " . $e->getMessage());
            Craft::$app->getSession()->setError(
                Craft::t('icon-manager', 'Failed to clear icon cache.')
            );

            return $this->redirectToPostedUrl();
        }
    }
}