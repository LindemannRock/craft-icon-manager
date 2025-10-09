<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\iconmanager\iconsets;

use lindemannrock\iconmanager\IconManager;
use lindemannrock\iconmanager\models\Icon;
use lindemannrock\iconmanager\models\IconSet;
use Craft;
use craft\helpers\FileHelper;
use craft\helpers\Json;

/**
 * SVG Sprite Icon Set Handler
 *
 * Supports SVG sprite files with <symbol> elements
 * Automatically extracts symbol IDs from sprite file
 */
class SvgSprite
{
    /**
     * Get icons from an SVG sprite file
     */
    public static function getIcons(IconSet $iconSet): array
    {
        $icons = [];
        $settings = $iconSet->settings ?? [];

        if (empty($settings['spriteFile'])) {
            Craft::warning('SVG Sprite icon set has no sprite file configured: ' . $iconSet->handle, 'icon-manager');
            return [];
        }

        $spritePath = self::getSpritePath($settings['spriteFile']);

        if (!file_exists($spritePath)) {
            Craft::error('Sprite file not found: ' . $spritePath . ' for icon set: ' . $iconSet->handle, 'icon-manager');
            return [];
        }

        // Extract symbols from sprite file
        $symbols = self::extractSymbolsFromSprite($spritePath);

        // Load optional metadata file for keywords
        $metadata = self::loadMetadata($spritePath);

        // Get optional prefix to remove from IDs
        $prefix = $settings['prefix'] ?? '';

        // Create icon objects for each symbol
        foreach ($symbols as $symbol) {
            $symbolId = $symbol['id'];
            $iconName = $symbolId;

            // Remove prefix if configured
            if ($prefix && str_starts_with($iconName, $prefix)) {
                $iconName = substr($iconName, strlen($prefix));
            }

            $icon = new Icon();
            $icon->name = $iconName;
            $icon->label = self::formatLabel($iconName);
            $icon->type = Icon::TYPE_SPRITE;
            $icon->value = $symbolId; // Store original ID for rendering
            $icon->iconSetId = $iconSet->id;
            $icon->iconSetHandle = $iconSet->handle;
            $icon->keywords = $metadata[$symbolId] ?? [];
            $icon->metadata = [
                'type' => Icon::TYPE_SPRITE,
                'symbolId' => $symbolId,
                'spriteFile' => $settings['spriteFile'],
                'viewBox' => $symbol['viewBox'] ?? null,
            ];

            $icons[] = $icon;
        }

        Craft::info('Loaded ' . count($icons) . ' icons from SVG sprite: ' . $iconSet->handle, 'icon-manager');

        return $icons;
    }

    /**
     * Extract symbol information from SVG sprite file
     */
    protected static function extractSymbolsFromSprite(string $spritePath): array
    {
        $symbols = [];

        try {
            $svgContent = file_get_contents($spritePath);

            if (!$svgContent) {
                Craft::error('Failed to read sprite file: ' . $spritePath, 'icon-manager');
                return [];
            }

            // Parse SVG with DOMDocument
            $dom = new \DOMDocument();

            // Suppress warnings for malformed HTML/SVG
            $previousValue = libxml_use_internal_errors(true);
            $dom->loadXML($svgContent);
            libxml_clear_errors();
            libxml_use_internal_errors($previousValue);

            // Find all <symbol> elements
            $symbolElements = $dom->getElementsByTagName('symbol');

            foreach ($symbolElements as $symbolElement) {
                $id = $symbolElement->getAttribute('id');

                if (!$id) {
                    continue; // Skip symbols without ID
                }

                $symbols[] = [
                    'id' => $id,
                    'viewBox' => $symbolElement->getAttribute('viewBox'),
                ];
            }

        } catch (\Exception $e) {
            Craft::error('Error parsing sprite file ' . $spritePath . ': ' . $e->getMessage(), 'icon-manager');
        }

        return $symbols;
    }

    /**
     * Load metadata JSON file if it exists
     * Format: { "symbol-id": ["keyword1", "keyword2"], ... }
     */
    protected static function loadMetadata(string $spritePath): array
    {
        $metadataPath = str_replace('.svg', '-metadata.json', $spritePath);

        if (!file_exists($metadataPath)) {
            return [];
        }

        try {
            $json = file_get_contents($metadataPath);
            return Json::decode($json);
        } catch (\Exception $e) {
            Craft::warning('Error loading metadata file ' . $metadataPath . ': ' . $e->getMessage(), 'icon-manager');
            return [];
        }
    }

    /**
     * Get full path to sprite file
     */
    protected static function getSpritePath(string $spriteFile): string
    {
        $settings = IconManager::getInstance()->getSettings();
        return $settings->getResolvedIconSetsPath() . DIRECTORY_SEPARATOR . $spriteFile;
    }

    /**
     * Format icon name for display
     */
    protected static function formatLabel(string $name): string
    {
        // Convert kebab-case or snake_case to title case
        return ucwords(str_replace(['-', '_'], ' ', $name));
    }

    /**
     * Get available sprite files from icons directory
     */
    public static function getAvailableSprites(): array
    {
        $settings = IconManager::getInstance()->getSettings();
        $iconsPath = $settings->getResolvedIconSetsPath();

        if (!is_dir($iconsPath)) {
            return [];
        }

        $sprites = FileHelper::findFiles($iconsPath, [
            'only' => ['*.svg'],
            'recursive' => true,
        ]);

        $options = [];
        foreach ($sprites as $sprite) {
            // Only include files that look like sprites (contain <symbol> tags)
            $content = @file_get_contents($sprite);
            if ($content && str_contains($content, '<symbol')) {
                $relativePath = str_replace($iconsPath . DIRECTORY_SEPARATOR, '', $sprite);
                $options[] = ['label' => $relativePath, 'value' => $relativePath];
            }
        }

        return $options;
    }

    /**
     * Get sprite file URL for serving
     */
    public static function getSpriteUrl(IconSet $iconSet): ?string
    {
        $settings = $iconSet->settings ?? [];

        if (empty($settings['spriteFile'])) {
            return null;
        }

        // Get the icon sets path and convert to web URL
        $iconManagerSettings = \lindemannrock\iconmanager\IconManager::getInstance()->getSettings();
        $iconSetsPath = $iconManagerSettings->getResolvedIconSetsPath();
        $spriteFilePath = $iconSetsPath . DIRECTORY_SEPARATOR . $settings['spriteFile'];

        // Convert filesystem path to web URL
        // In dev: @root/src/icons -> /src/icons
        // In prod: @webroot/dist/assets/icons -> /dist/assets/icons
        $webroot = \Craft::getAlias('@webroot');

        if (strpos($spriteFilePath, $webroot) === 0) {
            // File is within webroot - convert to relative URL
            $relativePath = str_replace($webroot, '', $spriteFilePath);
            $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
            return \craft\helpers\UrlHelper::siteUrl($relativePath);
        }

        // Fallback to controller action if file is outside webroot
        return \craft\helpers\UrlHelper::cpUrl('icon-manager/icons/serve-sprite', [
            'iconSet' => $iconSet->handle,
            'file' => basename($settings['spriteFile'])
        ]);
    }
}
