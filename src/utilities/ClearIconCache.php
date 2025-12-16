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
use lindemannrock\iconmanager\IconManager;

/**
 * Clear Icon Cache utility
 *
 * @since 1.7.0
 */
class ClearIconCache extends Utility
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        $pluginName = IconManager::getInstance()->getSettings()->pluginName;
        return $pluginName;
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
        $iconSets = IconManager::getInstance()->iconSets->getAllIconSets();
        $totalIcons = 0;

        foreach ($iconSets as $iconSet) {
            $icons = IconManager::getInstance()->icons->getIconsBySetId($iconSet->id);
            $totalIcons += count($icons);
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

        // Count cache files (only for file storage)
        $cacheStats = [
            'svg-folder' => 0,
            'svg-sprite' => 0,
            'material-icons' => 0,
            'font-awesome' => 0,
            'web-font' => 0,
        ];

        // Only count files when using file storage (Redis counts are not displayed)
        if ($settings->cacheStorageMethod === 'file') {
            $runtimePath = Craft::$app->path->getRuntimePath();
            $cacheBasePath = $runtimePath . '/icon-manager/cache/';
            foreach (array_keys($cacheStats) as $type) {
                $cachePath = $cacheBasePath . $type . '/';
                if (is_dir($cachePath)) {
                    $cacheStats[$type] = count(glob($cachePath . '*.cache'));
                }
            }
        }

        return Craft::$app->getView()->renderTemplate('icon-manager/utilities/index', [
            'iconSetCount' => count($iconSets),
            'totalIcons' => $totalIcons,
            'cacheEnabled' => $settings->enableCache,
            'cacheDuration' => $duration,
            'cacheStats' => $cacheStats,
            'storageMethod' => $settings->cacheStorageMethod,
        ]);
    }
}
