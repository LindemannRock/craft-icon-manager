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
use lindemannrock\logginglibrary\traits\LoggingTrait;

/**
 * Cache Controller
 *
 * @since 1.8.0
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
            $cacheStats = [];

            // Clear all cache folders organized by type
            $cacheBasePath = $runtimePath . '/icon-manager/cache/';
            $cacheTypes = ['svg-folder', 'svg-sprite', 'material-icons', 'font-awesome', 'web-font'];

            foreach ($cacheTypes as $type) {
                $cachePath = $cacheBasePath . $type . '/';
                $typeCount = 0;

                if (is_dir($cachePath)) {
                    $cacheFiles = glob($cachePath . '*.cache');
                    foreach ($cacheFiles as $file) {
                        if (@unlink($file)) {
                            $typeCount++;
                        }
                    }
                }

                $cacheStats[$type] = $typeCount;
                $totalCleared += $typeCount;
            }

            // Clear memory caches
            IconManager::getInstance()->icons->clearMemoryCache();

            $this->logInfo("Icon cache cleared successfully", [
                'totalCaches' => $totalCleared,
                'cacheStats' => $cacheStats,
            ]);

            Craft::$app->getSession()->setNotice(
                Craft::t('icon-manager', 'Icon cache cleared.')
            );

            return $this->redirectToPostedUrl();
        } catch (\Throwable $e) {
            $this->logError("Failed to clear icon cache", ['error' => $e->getMessage()]);
            Craft::$app->getSession()->setError(
                Craft::t('icon-manager', 'Failed to clear icon cache.')
            );

            return $this->redirectToPostedUrl();
        }
    }
}
