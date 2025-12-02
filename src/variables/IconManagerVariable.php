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

    /**
     * Get SVG optimizer service
     *
     * @return \lindemannrock\iconmanager\services\SvgOptimizerService
     */
    public function getSvgOptimizer()
    {
        return IconManager::getInstance()->svgOptimizer;
    }

    /**
     * Get SVGO service
     *
     * @return \lindemannrock\iconmanager\services\SvgoService
     */
    public function getSvgo()
    {
        return IconManager::getInstance()->svgo;
    }

    /**
     * Inject a sprite SVG inline
     *
     * @param string $iconSetHandle
     * @return string
     */
    public function injectSprite(string $iconSetHandle): string
    {
        $iconSet = IconManager::getInstance()->iconSets->getIconSetByHandle($iconSetHandle);
        if (!$iconSet || $iconSet->type !== 'svg-sprite') {
            return '';
        }

        $settings = $iconSet->settings ?? [];
        $spriteFile = $settings['spriteFile'] ?? null;

        if (!$spriteFile) {
            return '';
        }

        $spritePath = IconManager::getInstance()->getSettings()->getResolvedIconSetsPath() . DIRECTORY_SEPARATOR . $spriteFile;

        if (!file_exists($spritePath)) {
            return '';
        }

        $spriteContent = @file_get_contents($spritePath);
        if (!$spriteContent) {
            return '';
        }

        // Strip any <style> tags to prevent CSS pollution
        $spriteContent = preg_replace('/<style[^>]*>[\s\S]*?<\/style>/i', '', $spriteContent);

        // Return the sprite wrapped in a hidden div
        return '<div style="display:none;">' . $spriteContent . '</div>';
    }
}
