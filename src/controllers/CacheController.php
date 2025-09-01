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
use yii\web\Response;

/**
 * Cache Controller
 */
class CacheController extends Controller
{
    /**
     * Clear all icon caches
     */
    public function actionClear(): mixed
    {
        $this->requirePostRequest();
        $this->requireAdmin();
        
        try {
            $iconSets = IconManager::getInstance()->iconSets->getAllIconSets();
        $clearedCount = 0;
        
        foreach ($iconSets as $iconSet) {
            // Clear cache for each icon set
            $cacheKey = "icon-manager:icons-by-set:{$iconSet->id}";
            if (Craft::$app->getCache()->delete($cacheKey)) {
                $clearedCount++;
            }
        }
        
        // Clear Font Awesome caches
        $faVersions = ['v7'];
        $faLicenses = ['free', 'pro'];
        foreach ($faVersions as $version) {
            foreach ($faLicenses as $license) {
                $cacheKey = "icon-manager:fa-definitions:{$version}:{$license}";
                Craft::$app->getCache()->delete($cacheKey);
            }
        }
        
        // Clear Material Icons caches
        $materialTypes = ['icons', 'symbols'];
        foreach ($materialTypes as $type) {
            $cacheKey = "icon-manager:material-{$type}:definitions";
            Craft::$app->getCache()->delete($cacheKey);
        }
        
        // Clear memory caches
        IconManager::getInstance()->icons->clearMemoryCache();
        
            Craft::$app->getSession()->setNotice(
                Craft::t('icon-manager', 'Icon cache cleared.')
            );
            
            return $this->redirectToPostedUrl();
        } catch (\Throwable $e) {
            Craft::error('Error clearing icon cache: ' . $e->getMessage(), __METHOD__);
            Craft::$app->getSession()->setError(
                Craft::t('icon-manager', 'Failed to clear icon cache.')
            );
            
            return $this->redirectToPostedUrl();
        }
    }
}