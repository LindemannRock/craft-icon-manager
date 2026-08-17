<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\iconmanager\utilities;

use Craft;
use craft\base\Utility;
use lindemannrock\base\cache\DisposableCacheStoragePresenter;
use lindemannrock\iconmanager\IconManager;

/**
 * Clear Icon Cache utility
 *
 * @since 1.0.0
 */
class ClearIconCache extends Utility
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return IconManager::$plugin->getSettings()->getFullName();
    }

    /**
     * @inheritdoc
     */
    public static function id(): string
    {
        return 'clear-icon-cache';
    }

    /**
     * @inheritdoc
     */
    public static function icon(): ?string
    {
        return 'folder-grid';
    }

    /**
     * @inheritdoc
     */
    public static function contentHtml(): string
    {
        $user = Craft::$app->getUser();
        $iconSets = [];
        $totalIcons = 0;

        if ($user->getIdentity() && $user->checkPermission('iconManager:manageIconSets')) {
            $iconSets = IconManager::getInstance()->iconSets->getAllIconSets();

            foreach ($iconSets as $iconSet) {
                $icons = IconManager::getInstance()->icons->getIconsBySetId($iconSet->id);
                $totalIcons += count($icons);
            }
        }

        $settings = IconManager::getInstance()->getSettings();

        // Format cache duration for display
        $cacheDuration = $settings->cacheDuration;
        if ($cacheDuration >= 86400) {
            $days = round($cacheDuration / 86400);
            $duration = $days . ' ' . ($days == 1 ? Craft::t('icon-manager', 'day') : Craft::t('icon-manager', 'days'));
        } elseif ($cacheDuration >= 3600) {
            $hours = round($cacheDuration / 3600);
            $duration = $hours . ' ' . ($hours == 1 ? Craft::t('icon-manager', 'hour') : Craft::t('icon-manager', 'hours'));
        } else {
            $minutes = round($cacheDuration / 60);
            $duration = $minutes . ' ' . ($minutes == 1 ? Craft::t('icon-manager', 'minute') : Craft::t('icon-manager', 'minutes'));
        }

        $cacheDecision = IconManager::getInstance()->iconCache->getStorageDecision();
        $persistentCacheEnabled = $settings->enableCache && $settings->cacheDuration > 0;
        $cachePresentation = (new DisposableCacheStoragePresenter())->present(
            $cacheDecision,
            $persistentCacheEnabled,
        );
        $cacheStats = null;
        if ($user->getIdentity() && $user->checkPermission('iconManager:clearCache')) {
            $cacheStats = IconManager::getInstance()->iconCache->countFileCaches($cacheDecision);
        }

        return Craft::$app->getView()->renderTemplate('icon-manager/utilities/index', [
            'iconSetCount' => count($iconSets),
            'totalIcons' => $totalIcons,
            'cacheEnabled' => $persistentCacheEnabled,
            'cacheDuration' => $duration,
            'cacheStats' => $cacheStats,
            'cacheStorage' => $cachePresentation,
        ]);
    }
}
