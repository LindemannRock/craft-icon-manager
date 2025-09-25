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
        return $pluginName . ' Cache';
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
            $duration = round($cacheDuration / 86400) . ' ' . Craft::t('icon-manager', 'days');
        } elseif ($cacheDuration >= 3600) {
            $duration = round($cacheDuration / 3600) . ' ' . Craft::t('icon-manager', 'hours');
        } else {
            $duration = round($cacheDuration / 60) . ' ' . Craft::t('icon-manager', 'minutes');
        }
        
        return Craft::$app->getView()->renderTemplate('icon-manager/utilities/index', [
            'iconSetCount' => count($iconSets),
            'totalIcons' => $totalIcons,
            'cacheEnabled' => $settings->enableCache,
            'cacheDuration' => $duration,
        ]);
    }
}