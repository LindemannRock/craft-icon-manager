<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\iconmanager\variables;

use lindemannrock\iconmanager\IconManager;
use lindemannrock\iconmanager\models\Icon;
use lindemannrock\iconmanager\models\IconSet;

use Craft;

/**
 * Icon Manager Variable
 */
class IconManagerVariable
{
    /**
     * Get an icon by set handle and name
     *
     * @param string $iconSetHandle
     * @param string $iconName
     * @return Icon|null
     */
    public function getIcon(string $iconSetHandle, string $iconName): ?Icon
    {
        return IconManager::getInstance()->icons->getIcon($iconSetHandle, $iconName);
    }

    /**
     * Get all icon sets
     *
     * @return IconSet[]
     */
    public function getIconSets(): array
    {
        return IconManager::getInstance()->iconSets->getAllIconSets();
    }

    /**
     * Get enabled icon sets
     *
     * @return IconSet[]
     */
    public function getEnabledIconSets(): array
    {
        return IconManager::getInstance()->iconSets->getAllEnabledIconSets();
    }

    /**
     * Get an icon set by handle
     *
     * @param string $handle
     * @return IconSet|null
     */
    public function getIconSet(string $handle): ?IconSet
    {
        return IconManager::getInstance()->iconSets->getIconSetByHandle($handle);
    }

    /**
     * Get icons for a specific set
     *
     * @param string $iconSetHandle
     * @return Icon[]
     */
    public function getIcons(string $iconSetHandle): array
    {
        $iconSet = IconManager::getInstance()->iconSets->getIconSetByHandle($iconSetHandle);
        
        if (!$iconSet) {
            return [];
        }

        return IconManager::getInstance()->icons->getIconsBySetId($iconSet->id);
    }

    /**
     * Search for icons
     *
     * @param string $query
     * @param string[] $iconSetHandles
     * @return Icon[]
     */
    public function searchIcons(string $query, array $iconSetHandles = []): array
    {
        return IconManager::getInstance()->icons->searchIcons($query, $iconSetHandles);
    }

    /**
     * Render an icon
     *
     * @param string $iconSetHandle
     * @param string $iconName
     * @param array $options
     * @return string
     */
    public function renderIcon(string $iconSetHandle, string $iconName, array $options = []): string
    {
        $icon = $this->getIcon($iconSetHandle, $iconName);

        if (!$icon) {
            return '';
        }

        return $icon->render($options);
    }

    /**
     * Get plugin settings
     *
     * @return \lindemannrock\iconmanager\models\Settings
     */
    public function getSettings()
    {
        return IconManager::getInstance()->getSettings();
    }
}