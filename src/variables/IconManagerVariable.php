<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\iconmanager\variables;

use craft\helpers\FileHelper;
use lindemannrock\iconmanager\IconManager;
use lindemannrock\iconmanager\models\Icon;
use lindemannrock\iconmanager\models\IconSet;

/**
 * Icon Manager Variable
 *
 * @since 1.0.0
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
     * @since 1.10.0
     */
    public function getSvgOptimizer()
    {
        return IconManager::getInstance()->svgOptimizer;
    }

    /**
     * Get SVGO service
     *
     * @return \lindemannrock\iconmanager\services\SvgoService
     * @since 1.10.0
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

        // Containment guard: this method's output is rendered into front-end
        // templates, so an admin-misconfigured `spriteFile = '../...'` would
        // leak file contents to public visitors.
        $basePath = FileHelper::normalizePath(IconManager::getInstance()->getSettings()->getResolvedIconSetsPath());
        $spritePath = FileHelper::normalizePath($basePath . DIRECTORY_SEPARATOR . $spriteFile);

        if (!str_starts_with($spritePath . DIRECTORY_SEPARATOR, $basePath . DIRECTORY_SEPARATOR)) {
            return '';
        }

        if (!file_exists($spritePath)) {
            return '';
        }

        $spriteContent = @file_get_contents($spritePath);
        if (!$spriteContent) {
            return '';
        }

        // Sanitize sprite content: <symbol> and <defs> survive sanitization,
        // but <script>, <foreignObject>, event handlers, and javascript: URIs
        // are stripped before the content reaches public visitors.
        $sanitized = Icon::sanitizeSvg($spriteContent);
        if ($sanitized === null) {
            return '';
        }

        return '<div style="display:none;">' . $sanitized . '</div>';
    }
}
