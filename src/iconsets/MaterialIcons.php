<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\iconmanager\iconsets;

use craft\helpers\Json;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\iconmanager\IconManager;

use lindemannrock\iconmanager\models\Icon;
use lindemannrock\iconmanager\models\IconSet;
use lindemannrock\logginglibrary\services\LoggingService;

/**
 * Material Icons handler
 *
 * Supports:
 * - Material Icons (classic)
 * - Material Symbols (variable font)
 * - Multiple styles (outlined, rounded, sharp, two-tone)
 * - Variable font axes (weight, grade, optical size)
 * @since 1.10.0
 */
class MaterialIcons
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

    public const TYPE_ICONS = 'icons';
    public const TYPE_SYMBOLS = 'symbols';
    
    public const STYLE_OUTLINED = 'outlined';
    public const STYLE_ROUNDED = 'rounded';
    public const STYLE_SHARP = 'sharp';
    public const STYLE_TWO_TONE = 'two-tone';
    public const STYLE_FILLED = 'filled';

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
     * Get Material Icons
     */
    public function getIcons(): array
    {
        $icons = [];
        $settings = $this->iconSet->settings;

        $materialType = $settings['materialType'] ?? self::TYPE_ICONS;

        // Load icon definitions
        $iconDefinitions = $this->loadIconDefinitions($materialType);

        self::log('debug', 'Loading Material Icons', [
            'iconSetHandle' => $this->iconSet->handle,
            'materialType' => $materialType,
            'definitionsCount' => count($iconDefinitions),
        ]);
        
        foreach ($iconDefinitions as $iconName => $iconData) {
            if ($materialType === self::TYPE_SYMBOLS) {
                // Material Symbols use a single font with variable axes
                $icon = new Icon();
                $icon->name = "material-symbols-{$iconName}";
                $icon->label = $this->formatLabel($iconName);
                $icon->type = Icon::TYPE_FONT;
                $icon->value = "material-symbols-outlined";
                $icon->iconSetId = $this->iconSet->id;
                $icon->iconSetHandle = $this->iconSet->handle;
                $icon->keywords = $iconData['keywords'] ?? [];
                $icon->metadata = [
                    'type' => Icon::TYPE_FONT,
                    'className' => 'material-symbols-outlined',
                    'iconName' => $iconName,
                    'codepoint' => $iconData['codepoint'] ?? '',
                    'materialType' => 'symbols',
                ];
                
                // For symbols, we add the icon name as content
                $icon->path = $iconName;
                
                $icons[] = $icon;
            } else {
                // Material Icons classic - create for each enabled style
                // Get user-selected styles from settings, or default to filled
                $selectedStyles = $settings['styles'] ?? [self::STYLE_FILLED];

                // Only include styles that are both available AND selected by user
                $availableStyles = $iconData['styles'] ?? [self::STYLE_FILLED];
                $styles = array_intersect($availableStyles, $selectedStyles);

                // If no intersection (shouldn't happen), fall back to filled
                if (empty($styles)) {
                    $styles = [self::STYLE_FILLED];
                }

                foreach ($styles as $style) {
                    $classPrefix = $this->getClassPrefix($style);
                    
                    $icon = new Icon();
                    $icon->name = "{$classPrefix}-{$iconName}";
                    $icon->label = $this->formatLabel($iconName);
                    $icon->type = Icon::TYPE_FONT;
                    $icon->value = $classPrefix;
                    $icon->iconSetId = $this->iconSet->id;
                    $icon->iconSetHandle = $this->iconSet->handle;
                    $icon->keywords = $iconData['keywords'] ?? [];
                    $icon->metadata = [
                        'type' => Icon::TYPE_FONT,
                        'className' => $classPrefix,
                        'iconName' => $iconName,
                        'style' => $style,
                        'codepoint' => $iconData['codepoint'] ?? '',
                        'materialType' => 'icons',
                    ];
                    
                    // For icons, we add the icon name as content
                    $icon->path = $iconName;
                    
                    $icons[] = $icon;
                }
            }
        }

        self::log('info', 'Loaded icons from Material Icons', [
            'count' => count($icons),
            'iconSetHandle' => $this->iconSet->handle,
            'materialType' => $materialType,
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
        $materialType = $settings['materialType'] ?? self::TYPE_ICONS;
        
        if ($materialType === self::TYPE_SYMBOLS) {
            // Material Symbols - Variable font
            $assets[] = [
                'type' => 'css',
                'url' => 'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200',
                'attributes' => ['crossorigin' => 'anonymous'],
            ];
            
            // Add CSS for default settings
            $assets[] = [
                'type' => 'css',
                'inline' => '
                    .material-symbols-outlined {
                        font-variation-settings:
                            "FILL" 0,
                            "wght" 400,
                            "GRAD" 0,
                            "opsz" 24;
                    }
                ',
            ];
        } else {
            // Material Icons classic
            $styles = $settings['styles'] ?? [self::STYLE_FILLED];
            
            // Always include base Material Icons font
            $assets[] = [
                'type' => 'css',
                'url' => 'https://fonts.googleapis.com/icon?family=Material+Icons',
                'attributes' => ['crossorigin' => 'anonymous'],
            ];
            
            // Include additional styles
            foreach ($styles as $style) {
                if ($style !== self::STYLE_FILLED) {
                    $fontFamily = $this->getFontFamily($style);
                    $assets[] = [
                        'type' => 'css',
                        'url' => "https://fonts.googleapis.com/icon?family={$fontFamily}",
                        'attributes' => ['crossorigin' => 'anonymous'],
                    ];
                }
            }
        }
        
        return $assets;
    }

    // Private Methods
    // =========================================================================

    /**
     * Load icon definitions from JSON files
     */
    private function loadIconDefinitions(string $type): array
    {
        $settings = IconManager::getInstance()->getSettings();

        // Skip cache if disabled
        if (!$settings->enableCache) {
            return $this->loadIconDefinitionsFromFile($type);
        }

        // Check custom file cache (organized by icon type)
        $cachePath = PluginHelper::getCachePath(IconManager::$plugin, 'material-icons');
        $cacheFile = $cachePath . "{$type}.cache";

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
        $definitions = $this->loadIconDefinitionsFromFile($type);

        // Create directory if it doesn't exist
        if (!is_dir($cachePath)) {
            \craft\helpers\FileHelper::createDirectory($cachePath);
        }

        file_put_contents($cacheFile, serialize($definitions));

        return $definitions;
    }
    
    private function loadIconDefinitionsFromFile(string $type): array
    {
        $definitions = [];

        // Path to bundled icon definitions
        $definitionsPath = __DIR__ . "/../json/material/{$type}/icons.json";

        if (file_exists($definitionsPath)) {
            $json = file_get_contents($definitionsPath);
            $definitions = Json::decode($json);
        } else {
            self::log('warning', 'Material Icons definition file not found', [
                'type' => $type,
                'path' => $definitionsPath,
            ]);
        }

        return $definitions;
    }

    /**
     * Format icon name to label
     */
    private function formatLabel(string $iconName): string
    {
        // Convert snake_case to Title Case
        return ucwords(str_replace('_', ' ', $iconName));
    }

    /**
     * Get class prefix for style
     */
    private function getClassPrefix(string $style): string
    {
        $prefixMap = [
            self::STYLE_FILLED => 'material-icons',
            self::STYLE_OUTLINED => 'material-icons-outlined',
            self::STYLE_ROUNDED => 'material-icons-round',
            self::STYLE_SHARP => 'material-icons-sharp',
            self::STYLE_TWO_TONE => 'material-icons-two-tone',
        ];
        
        return $prefixMap[$style] ?? 'material-icons';
    }

    /**
     * Get font family for style
     */
    private function getFontFamily(string $style): string
    {
        $familyMap = [
            self::STYLE_OUTLINED => 'Material+Icons+Outlined',
            self::STYLE_ROUNDED => 'Material+Icons+Round',
            self::STYLE_SHARP => 'Material+Icons+Sharp',
            self::STYLE_TWO_TONE => 'Material+Icons+Two+Tone',
        ];
        
        return $familyMap[$style] ?? 'Material+Icons';
    }
}
