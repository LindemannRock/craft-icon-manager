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
        $this->requireAdmin();
        
        try {
            $this->logInfo("Starting icon cache clearing operation");

            $iconSets = IconManager::getInstance()->iconSets->getAllIconSets();
            $clearedCount = 0;

            // Clear icon set caches
            foreach ($iconSets as $iconSet) {
                $cacheKey = "icon-manager:icons-by-set:{$iconSet->id}";
                if (Craft::$app->getCache()->delete($cacheKey)) {
                    $clearedCount++;
                }
            }

            $this->logTrace("Cleared {$clearedCount} icon set caches");

            // Clear Font Awesome caches
            $faVersions = ['v7'];
            $faLicenses = ['free', 'pro'];
            $faCacheCount = 0;
            foreach ($faVersions as $version) {
                foreach ($faLicenses as $license) {
                    $cacheKey = "icon-manager:fa-definitions:{$version}:{$license}";
                    if (Craft::$app->getCache()->delete($cacheKey)) {
                        $faCacheCount++;
                    }
                }
            }

            $this->logTrace("Cleared {$faCacheCount} Font Awesome caches");

            // Clear Material Icons caches
            $materialTypes = ['icons', 'symbols'];
            $materialCacheCount = 0;
            foreach ($materialTypes as $type) {
                $cacheKey = "icon-manager:material-{$type}:definitions";
                if (Craft::$app->getCache()->delete($cacheKey)) {
                    $materialCacheCount++;
                }
            }

            $this->logTrace("Cleared {$materialCacheCount} Material Icons caches");

            // Clear memory caches
            IconManager::getInstance()->icons->clearMemoryCache();

            $totalCleared = $clearedCount + $faCacheCount + $materialCacheCount;
            $this->logInfo("Icon cache cleared successfully", [
                'totalCaches' => $totalCleared,
                'iconSets' => $clearedCount,
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