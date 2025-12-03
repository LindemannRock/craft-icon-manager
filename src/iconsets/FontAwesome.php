<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\iconmanager\iconsets;

use Craft;
use craft\helpers\Json;
use lindemannrock\iconmanager\IconManager;

use lindemannrock\iconmanager\models\Icon;
use lindemannrock\iconmanager\models\IconSet;
use lindemannrock\logginglibrary\services\LoggingService;

/**
 * Font Awesome icon set handler
 *
 * Supports:
 * - Font Awesome Free (v5/v6)
 * - Font Awesome Pro (v5/v6) via API key
 * - Kit integration
 * - CDN integration
 * - Auto-detection of Pro license
 */
class FontAwesome
{
    /**
     * Static logging helper using LoggingService
     */
    protected static function log(string $level, string $message, array $context = []): void
    {
        LoggingService::log($message, $level, 'icon-manager', $context);
    }

    // Constants
    // =========================================================================

    public const TYPE_KIT = 'kit';
    public const TYPE_CDN = 'cdn';
    public const TYPE_LOCAL = 'local';
    
    public const VERSION_7 = 'v7';
    
    public const LICENSE_FREE = 'free';
    public const LICENSE_PRO = 'pro';

    // Properties
    // =========================================================================

    private IconSet $iconSet;

    // Public Methods
    // =========================================================================

    /**
     * Constructor
     */
    public function __construct(IconSet $iconSet)
    {
        $this->iconSet = $iconSet;
    }

    /**
     * Get Font Awesome icons
     */
    public function getIcons(): array
    {
        $icons = [];
        $settings = $this->iconSet->settings;

        $type = $settings['type'] ?? self::TYPE_CDN;

        self::log('debug', 'Loading Font Awesome icons', [
            'iconSetHandle' => $this->iconSet->handle,
            'type' => $type,
        ]);

        switch ($type) {
            case self::TYPE_KIT:
                $icons = $this->getKitIcons($settings);
                break;

            case self::TYPE_CDN:
                $icons = $this->getCdnIcons($settings);
                break;

            case self::TYPE_LOCAL:
                $icons = $this->getLocalIcons($settings);
                break;
        }

        self::log('info', 'Loaded icons from Font Awesome', [
            'count' => count($icons),
            'iconSetHandle' => $this->iconSet->handle,
            'type' => $type,
        ]);

        return $icons;
    }

    /**
     * Get required assets (CSS/JS)
     */
    public function getAssets(): array
    {
        $assets = [];
        $settings = $this->iconSet->settings;
        $type = $settings['type'] ?? self::TYPE_CDN;
        
        switch ($type) {
            case self::TYPE_KIT:
                $kitCode = $settings['kitCode'] ?? '';
                if ($kitCode) {
                    $assets[] = [
                        'type' => 'js',
                        'url' => "https://kit.fontawesome.com/{$kitCode}.js",
                        'attributes' => ['crossorigin' => 'anonymous'],
                    ];
                }
                break;
                
            case self::TYPE_CDN:
                $version = $settings['version'] ?? '7.0.0';
                $license = $settings['license'] ?? self::LICENSE_FREE;
                $styles = $settings['styles'] ?? ['solid'];
                
                // Font Awesome v7 CDN URLs
                $baseUrl = $license === self::LICENSE_PRO
                    ? "https://pro.fontawesome.com/v7.0.0/css"
                    : "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css";
                
                // Always include base fontawesome.css
                $assets[] = [
                    'type' => 'css',
                    'url' => "{$baseUrl}/fontawesome.min.css",
                    'attributes' => ['crossorigin' => 'anonymous'],
                ];
                
                // Include selected styles (v7 naming)
                foreach ($styles as $style) {
                    $cssFile = $this->getV7CssFileName($style);
                    $assets[] = [
                        'type' => 'css',
                        'url' => "{$baseUrl}/{$cssFile}",
                        'attributes' => ['crossorigin' => 'anonymous'],
                    ];
                }
                break;
                
            case self::TYPE_LOCAL:
                // Local files would be handled differently
                break;
        }
        
        return $assets;
    }


    // Private Methods
    // =========================================================================

    /**
     * Get icons from Font Awesome kit
     *
     * For kits, we return an empty array since the kit JavaScript
     * dynamically loads available icons. Users should reference
     * Font Awesome documentation for available icons.
     */
    private function getKitIcons(array $settings): array
    {
        // Kits handle icon loading dynamically via JavaScript
        // We can't predict which icons are available without the kit JS
        $kitCode = $settings['kitCode'] ?? '';
        self::log('info', 'Font Awesome Kit icons load dynamically via JavaScript', [
            'kitCode' => $kitCode ? substr($kitCode, 0, 8) . '...' : 'not configured',
        ]);
        return [];
    }
    
    /**
     * Get admin panel assets for preview
     */
    public function getAdminAssets(): array
    {
        $settings = $this->iconSet->settings;
        $type = $settings['type'] ?? self::TYPE_CDN;
        
        if ($type === self::TYPE_KIT) {
            $kitCode = $settings['kitCode'] ?? '';
            if ($kitCode) {
                return [[
                    'type' => 'js',
                    'url' => "https://kit.fontawesome.com/{$kitCode}.js",
                    'attributes' => ['crossorigin' => 'anonymous'],
                ]];
            }
        }
        
        return [];
    }

    /**
     * Get icons from CDN
     */
    private function getCdnIcons(array $settings): array
    {
        $icons = [];
        $version = $settings['version'] ?? '7.0.0';
        $license = $settings['license'] ?? self::LICENSE_FREE;
        $styles = $settings['styles'] ?? ['solid'];
        
        // Load icon definitions from bundled JSON files (v7 only)
        $iconDefinitions = $this->loadIconDefinitions($version, $license);
        
        foreach ($iconDefinitions as $iconId => $iconData) {
            // Check if icon has any of the selected styles
            $availableStyles = $iconData['styles'] ?? [];
            $matchingStyles = array_intersect($styles, $availableStyles);
            
            if (empty($matchingStyles)) {
                continue;
            }
            
            // Create an icon for each matching style
            foreach ($matchingStyles as $style) {
                $prefix = $this->getStylePrefix(['style' => $style]);
                
                $icon = new Icon();
                $icon->name = "{$prefix}-{$iconId}";
                $icon->label = $iconData['label'] ?? $iconId;
                $icon->type = Icon::TYPE_FONT;
                $icon->value = "{$prefix} fa-{$iconId}";
                $icon->iconSetId = $this->iconSet->id;
                $icon->iconSetHandle = $this->iconSet->handle;
                $icon->keywords = $iconData['search']['terms'] ?? [];
                $icon->metadata = [
                    'type' => Icon::TYPE_FONT,
                    'className' => "{$prefix} fa-{$iconId}",
                    'unicode' => $iconData['unicode'] ?? '',
                    'style' => $style,
                ];
                
                $icons[] = $icon;
            }
        }
        
        return $icons;
    }

    /**
     * Get icons from local files
     */
    private function getLocalIcons(array $settings): array
    {
        // This would scan local Font Awesome files
        // For now, return empty array
        return [];
    }

    /**
     * Load icon definitions from JSON files
     */
    private function loadIconDefinitions(string $version, string $license): array
    {
        $settings = IconManager::getInstance()->getSettings();

        // Skip cache if disabled
        if (!$settings->enableCache) {
            return $this->loadIconDefinitionsFromFile($version, $license);
        }

        // Check custom file cache (organized by icon type)
        $cachePath = Craft::$app->path->getRuntimePath() . '/icon-manager/cache/font-awesome/';
        $cacheFile = $cachePath . "{$version}_{$license}.cache";

        if (file_exists($cacheFile)) {
            // Check if cache is expired
            $mtime = filemtime($cacheFile);
            if (time() - $mtime <= $settings->cacheDuration) {
                $data = file_get_contents($cacheFile);
                return unserialize($data) ?? [];
            }
            // Cache expired, delete it
            @unlink($cacheFile);
        }

        // Load from file and cache it
        $definitions = $this->loadIconDefinitionsFromFile($version, $license);

        // Create directory if it doesn't exist
        if (!is_dir($cachePath)) {
            \craft\helpers\FileHelper::createDirectory($cachePath);
        }

        file_put_contents($cacheFile, serialize($definitions));

        return $definitions;
    }
    
    private function loadIconDefinitionsFromFile(string $version, string $license): array
    {
        $definitions = [];

        // Path to bundled icon definitions (v7 only)
        $definitionsPath = __DIR__ . "/../json/fontawesome/v7/{$license}/icons.json";

        if (file_exists($definitionsPath)) {
            $json = file_get_contents($definitionsPath);
            $definitions = Json::decode($json);
        } else {
            self::log('warning', 'Font Awesome definition file not found', [
                'version' => $version,
                'license' => $license,
                'path' => $definitionsPath,
            ]);
        }

        return $definitions;
    }
    
    /**
     * Get v7 CSS file name for style
     */
    private function getV7CssFileName(string $style): string
    {
        $fileMap = [
            'solid' => 'solid.min.css',
            'regular' => 'regular.min.css',
            'light' => 'light.min.css',
            'thin' => 'thin.min.css',
            'duotone' => 'duotone.min.css',
            'brands' => 'brands.min.css',
        ];
        
        return $fileMap[$style] ?? 'solid.min.css';
    }

    /**
     * Get style prefix for Font Awesome
     */
    private function getStylePrefix(array $style): string
    {
        $family = $style['family'] ?? '';
        $styleName = $style['style'] ?? '';
        
        // Handle different Font Awesome styles
        $prefixMap = [
            'solid' => 'fas',
            'regular' => 'far',
            'light' => 'fal',
            'thin' => 'fat',
            'duotone' => 'fad',
            'brands' => 'fab',
        ];
        
        // Special case for duotone family
        if ($family === 'duotone') {
            return 'fad';
        }
        
        return $prefixMap[$styleName] ?? 'fa';
    }
}
