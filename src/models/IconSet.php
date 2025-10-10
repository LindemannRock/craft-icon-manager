<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\iconmanager\models;

use lindemannrock\iconmanager\IconManager;
use Craft;
use craft\base\Model;

/**
 * Icon Set Model
 */
class IconSet extends Model
{
    /**
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var string Name
     */
    public string $name = '';

    /**
     * @var string Handle
     */
    public string $handle = '';

    /**
     * @var string Type (svg-folder, svg-sprite, font-awesome, material-icons, custom)
     */
    public string $type = '';

    /**
     * @var array Settings specific to the icon set type
     */
    public array $settings = [];

    /**
     * @var bool Enabled
     */
    public bool $enabled = true;

    /**
     * @var int|null Sort order
     */
    public ?int $sortOrder = null;

    /**
     * @var \DateTime|null Date created
     */
    public ?\DateTime $dateCreated = null;

    /**
     * @var \DateTime|null Date updated
     */
    public ?\DateTime $dateUpdated = null;

    /**
     * @var string|null UID
     */
    public ?string $uid = null;

    /**
     * @var Icon[] Cached icons for this set
     */
    private array $_icons = [];

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['name', 'handle', 'type'], 'required'],
            [['name', 'handle'], 'string', 'max' => 255],
            [['handle'], 'match', 'pattern' => '/^[a-zA-Z][a-zA-Z0-9_]*$/'],
            [['type'], 'in', 'range' => ['svg-folder', 'svg-sprite', 'font-awesome', 'material-icons', 'web-font', 'custom']],
            [['enabled'], 'boolean'],
            [['sortOrder'], 'integer'],
            [['settings'], 'safe'],
        ];
    }

    /**
     * Get the display name for this icon set type
     */
    public function getTypeLabel(): string
    {
        return match($this->type) {
            'svg-folder' => Craft::t('icon-manager', 'SVG Folder'),
            'svg-sprite' => Craft::t('icon-manager', 'SVG Sprite'),
            'font-awesome' => Craft::t('icon-manager', 'Font Awesome'),
            'material-icons' => Craft::t('icon-manager', 'Material Icons'),
            'web-font' => Craft::t('icon-manager', 'Web Font'),
            'custom' => Craft::t('icon-manager', 'Custom'),
            default => $this->type,
        };
    }

    /**
     * Get type-specific settings
     */
    public function getTypeSettings(): array
    {
        return match($this->type) {
            'svg-folder' => [
                'folder' => $this->settings['folder'] ?? '',
                'includeSubfolders' => $this->settings['includeSubfolders'] ?? false,
            ],
            'svg-sprite' => [
                'spriteFile' => $this->settings['spriteFile'] ?? '',
                'prefix' => $this->settings['prefix'] ?? '',
            ],
            'font-awesome' => [
                'version' => $this->settings['version'] ?? '6',
                'license' => $this->settings['license'] ?? 'free',
                'styles' => $this->settings['styles'] ?? ['solid'],
            ],
            'material-icons' => [
                'style' => $this->settings['style'] ?? 'filled',
            ],
            default => $this->settings,
        };
    }
    
    /**
     * Get available folders from the icon sets path
     */
    public static function getAvailableFolders(): array
    {
        $options = [];
        $basePath = IconManager::getInstance()->getSettings()->getResolvedIconSetsPath();
        
        if (!is_dir($basePath)) {
            return $options;
        }
        
        // Add root folder option
        $options[] = ['label' => '/', 'value' => ''];
        
        // Scan for directories
        self::_scanDirectories($basePath, '', $options);
        
        return $options;
    }
    
    /**
     * Recursively scan directories
     */
    private static function _scanDirectories(string $basePath, string $relativePath, array &$options): void
    {
        $currentPath = $basePath . ($relativePath ? DIRECTORY_SEPARATOR . $relativePath : '');
        
        if (!is_dir($currentPath)) {
            return;
        }
        
        $items = scandir($currentPath);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || strpos($item, '.') === 0) {
                continue;
            }
            
            $itemPath = $currentPath . DIRECTORY_SEPARATOR . $item;
            $relativeItemPath = $relativePath ? $relativePath . '/' . $item : $item;
            
            if (is_dir($itemPath)) {
                $options[] = [
                    'label' => '/' . $relativeItemPath,
                    'value' => $relativeItemPath,
                ];
                
                // Recursively scan subdirectories
                self::_scanDirectories($basePath, $relativeItemPath, $options);
            }
        }
    }

    /**
     * Set the icons for this icon set
     */
    public function setIcons(array $icons): void
    {
        $this->_icons = $icons;
    }

    /**
     * Get the icons for this icon set
     */
    public function getIcons(): array
    {
        return $this->_icons;
    }

    /**
     * Get icon count
     */
    public function getIconCount(): int
    {
        if (!empty($this->_icons)) {
            return count($this->_icons);
        }
        
        // If icons aren't loaded, get count from service
        if ($this->id) {
            $icons = IconManager::getInstance()->icons->getIconsBySetId($this->id);
            return count($icons);
        }

        return 0;
    }

    /**
     * Get WebFont CSS for this icon set (if it's a web-font type)
     */
    public function getWebFontCss(): ?string
    {
        if ($this->type !== 'web-font') {
            return null;
        }

        return \lindemannrock\iconmanager\iconsets\WebFont::getFontFaceCss($this);
    }

    /**
     * Get optimization issue count for SVG folder icon sets
     * Includes all issues and warnings (including large files)
     */
    public function getOptimizationIssueCount(): int
    {
        // Only applicable for SVG folder icon sets
        if (!in_array($this->type, ['svg-folder', 'folder'])) {
            return 0;
        }

        // Scan this icon set for issues
        $scanResult = IconManager::getInstance()->svgOptimizer->scanIconSet($this);

        // Sum up all issues including warnings
        $totalIssues = 0;
        if (isset($scanResult['issues'])) {
            $totalIssues = ($scanResult['issues']['clipPaths'] ?? 0) +
                          ($scanResult['issues']['masks'] ?? 0) +
                          ($scanResult['issues']['filters'] ?? 0) +
                          ($scanResult['issues']['comments'] ?? 0) +
                          ($scanResult['issues']['inlineStyles'] ?? 0) +
                          ($scanResult['issues']['largeFiles'] ?? 0);
        }

        return $totalIssues;
    }
}