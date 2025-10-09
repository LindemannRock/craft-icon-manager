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
use FontLib\Font;

/**
 * WebFont Icon Set Handler
 *
 * Supports custom icon fonts (TTF, WOFF, WOFF2, OTF)
 * Automatically extracts glyph information from font files
 */
class WebFont
{
    /**
     * Get icons from a custom web font file
     */
    public static function getIcons(IconSet $iconSet): array
    {
        $icons = [];
        $settings = $iconSet->settings ?? [];

        if (empty($settings['fontFile'])) {
            Craft::warning('WebFont icon set has no font file configured: ' . $iconSet->handle, 'icon-manager');
            return [];
        }

        $fontPath = self::getFontPath($settings['fontFile']);

        if (!file_exists($fontPath)) {
            Craft::error('Font file not found: ' . $fontPath . ' for icon set: ' . $iconSet->handle, 'icon-manager');
            return [];
        }

        // Extract glyphs from font file
        $glyphs = self::extractGlyphsFromFont($fontPath);

        // Load optional metadata file for keywords
        $metadata = self::loadMetadata($fontPath);

        // Create icon objects for each glyph
        foreach ($glyphs as $glyph) {
            $iconName = $glyph['name'];
            $codepoint = dechex($glyph['unicode']);

            $icon = new Icon();
            $icon->name = $iconName;
            $icon->label = self::formatLabel($iconName);
            $icon->type = Icon::TYPE_FONT;
            $icon->value = $settings['cssPrefix'] ?? 'icon';
            $icon->iconSetId = $iconSet->id;
            $icon->iconSetHandle = $iconSet->handle;
            $icon->keywords = $metadata[$iconName] ?? [];
            $icon->metadata = [
                'type' => Icon::TYPE_FONT,
                'className' => ($settings['cssPrefix'] ?? 'icon') . '-' . $iconName,
                'iconName' => $iconName,
                'codepoint' => $codepoint,
                'unicode' => $glyph['unicode'],
            ];

            // Store the icon name for rendering
            $icon->path = $iconName;

            $icons[] = $icon;
        }

        Craft::info('Loaded ' . count($icons) . ' icons from web font: ' . $iconSet->handle, 'icon-manager');

        return $icons;
    }

    /**
     * Extract glyph information from font file
     */
    protected static function extractGlyphsFromFont(string $fontPath): array
    {
        $glyphs = [];

        try {
            $font = Font::load($fontPath);
            $font->parse();

            // Get unicode character map
            $charMap = $font->getUnicodeCharMap();

            // Get glyph names from font
            $names = $font->getData('post', 'names');

            if (!$charMap) {
                return [];
            }

            foreach ($charMap as $unicode => $glyphId) {
                // Skip control characters and empty glyphs
                if ($unicode < 33) {
                    continue;
                }

                // Try to get a meaningful name
                $name = $names[$glyphId] ?? null;

                // Fallback to unicode hex if no name
                if (!$name || strpos($name, 'uni') === 0) {
                    $name = 'glyph-' . dechex($unicode);
                }

                $glyphs[] = [
                    'name' => $name,
                    'unicode' => $unicode,
                    'glyphId' => $glyphId,
                ];
            }

        } catch (\Exception $e) {
            Craft::error('Error parsing font file ' . $fontPath . ': ' . $e->getMessage(), 'icon-manager');
        }

        return $glyphs;
    }

    /**
     * Load metadata JSON file if it exists
     * Format: { "icon-name": ["keyword1", "keyword2"], ... }
     */
    protected static function loadMetadata(string $fontPath): array
    {
        $metadataPath = preg_replace('/\.(ttf|woff|woff2|otf)$/i', '-metadata.json', $fontPath);

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
     * Get full path to font file
     */
    protected static function getFontPath(string $fontFile): string
    {
        $settings = IconManager::getInstance()->getSettings();
        return $settings->getResolvedIconSetsPath() . DIRECTORY_SEPARATOR . $fontFile;
    }

    /**
     * Format icon name for display
     */
    protected static function formatLabel(string $name): string
    {
        // Remove common prefixes
        $name = preg_replace('/^(icon-|glyph-)/', '', $name);

        // Convert to title case
        return ucwords(str_replace(['-', '_'], ' ', $name));
    }

    /**
     * Get @font-face CSS for this font
     */
    public static function getFontFaceCss(IconSet $iconSet): string
    {
        $settings = $iconSet->settings ?? [];

        if (empty($settings['fontFile'])) {
            return '';
        }

        $fontPath = self::getFontPath($settings['fontFile']);
        $fontName = pathinfo($settings['fontFile'], PATHINFO_FILENAME);
        $cssPrefix = $settings['cssPrefix'] ?? 'icon';

        // Get font URL - use controller action to serve the font file
        $fontUrl = \craft\helpers\UrlHelper::cpUrl('icon-manager/icons/serve-font', [
            'iconSet' => $iconSet->handle,
            'file' => basename($settings['fontFile'])
        ]);

        // Determine font format from extension
        $ext = pathinfo($settings['fontFile'], PATHINFO_EXTENSION);
        $format = match(strtolower($ext)) {
            'woff2' => 'woff2',
            'woff' => 'woff',
            'ttf' => 'truetype',
            'otf' => 'opentype',
            default => 'truetype'
        };

        $css = <<<CSS
@font-face {
    font-family: '{$fontName}';
    src: url('{$fontUrl}') format('{$format}');
    font-weight: normal;
    font-style: normal;
    font-display: block;
}

.{$cssPrefix} {
    font-family: '{$fontName}' !important;
    speak: never;
    font-style: normal;
    font-weight: normal;
    font-variant: normal;
    text-transform: none;
    line-height: 1;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}
CSS;

        return $css;
    }

    /**
     * Get available font files from icons directory
     */
    public static function getAvailableFonts(): array
    {
        $settings = IconManager::getInstance()->getSettings();
        $iconsPath = $settings->getResolvedIconSetsPath();

        if (!is_dir($iconsPath)) {
            return [];
        }

        $fonts = FileHelper::findFiles($iconsPath, [
            'only' => ['*.ttf', '*.woff', '*.woff2', '*.otf'],
            'recursive' => true,
        ]);

        $options = [];
        foreach ($fonts as $font) {
            $relativePath = str_replace($iconsPath . DIRECTORY_SEPARATOR, '', $font);
            $options[] = ['label' => $relativePath, 'value' => $relativePath];
        }

        return $options;
    }

    /**
     * Get assets (fonts) needed for this icon set
     */
    public static function getAssets(IconSet $iconSet): array
    {
        $assets = [];
        $settings = $iconSet->settings ?? [];

        if (!empty($settings['fontFile'])) {
            $fontName = pathinfo($settings['fontFile'], PATHINFO_FILENAME);

            $assets[] = [
                'type' => 'css',
                'inline' => self::getFontFaceCss($iconSet),
            ];
        }

        return $assets;
    }
}
