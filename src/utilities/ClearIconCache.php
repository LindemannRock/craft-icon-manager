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

        // Count cache files
        $runtimePath = Craft::$app->path->getRuntimePath();
        $cacheStats = [
            'icons' => 0,
            'fontawesome' => 0,
            'material' => 0,
        ];

        $iconsCachePath = $runtimePath . '/icon-manager/icons/';
        if (is_dir($iconsCachePath)) {
            $cacheStats['icons'] = count(glob($iconsCachePath . '*.cache'));
        }

        $faCachePath = $runtimePath . '/icon-manager/fontawesome/';
        if (is_dir($faCachePath)) {
            $cacheStats['fontawesome'] = count(glob($faCachePath . '*.cache'));
        }

        $materialCachePath = $runtimePath . '/icon-manager/material/';
        if (is_dir($materialCachePath)) {
            $cacheStats['material'] = count(glob($materialCachePath . '*.cache'));
        }

        return Craft::$app->getView()->renderTemplate('icon-manager/utilities/index', [
            'iconSetCount' => count($iconSets),
            'totalIcons' => $totalIcons,
            'cacheEnabled' => $settings->enableCache,
            'cacheDuration' => $duration,
            'cacheStats' => $cacheStats,
        ]);
    }
}