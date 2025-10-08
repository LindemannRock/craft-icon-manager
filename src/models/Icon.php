<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\iconmanager\models;

use Craft;
use craft\base\Model;
use craft\helpers\Html;
use craft\helpers\StringHelper;
use craft\helpers\Template;
use lindemannrock\iconmanager\IconManager;
use lindemannrock\iconmanager\traits\LoggingTrait;

/**
 * Icon Model
 */
class Icon extends Model implements \JsonSerializable
{
    use LoggingTrait;

    /**
     * Icon types
     */
    const TYPE_SVG = 'svg';
    const TYPE_SPRITE = 'sprite';
    const TYPE_FONT = 'font';

    /**
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var int Icon set ID
     */
    public int $iconSetId;

    /**
     * @var string Icon set handle
     */
    public string $iconSetHandle = '';

    /**
     * @var string Icon type
     */
    public string $type = self::TYPE_SVG;

    /**
     * @var string Icon name/identifier
     */
    public string $name = '';

    /**
     * @var string Icon label for display
     */
    public string $label = '';

    /**
     * @var string Icon value (path, class name, sprite ID)
     */
    public string $value = '';

    /**
     * @var string|null Icon path (for SVG files)
     */
    public ?string $path = null;

    /**
     * @var array Keywords for searching
     */
    public array $keywords = [];

    /**
     * @var array Additional metadata
     */
    public array $metadata = [];

    /**
     * @var string|null Custom label set by user
     */
    public ?string $customLabel = null;

    /**
     * @var array Custom labels per site
     */
    public array $customLabels = [];

    /**
     * @inheritdoc
     */
    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->id,
            'iconSetHandle' => $this->iconSetHandle,
            'type' => $this->type,
            'name' => $this->name,
            'label' => $this->label,
            'value' => $this->value,
            'keywords' => $this->keywords,
        ];
    }
    
    /**
     * Get JSON data for picker without content (loaded via AJAX on-demand)
     */
    public function toPickerArray(): array
    {
        $data = $this->jsonSerialize();

        // Don't preload content - let the picker load it via AJAX on-demand
        // This prevents massive HTML payloads (was causing 12MB+ HTML files)
        $data['content'] = null;

        return $data;
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['iconSetId', 'type', 'name', 'value'], 'required'],
            [['iconSetId'], 'integer'],
            [['type'], 'in', 'range' => [self::TYPE_SVG, self::TYPE_SPRITE, self::TYPE_FONT]],
            [['name', 'label', 'value', 'path'], 'string'],
            [['keywords', 'metadata'], 'safe'],
        ];
    }

    /**
     * Get the display label with smart resolution
     * Priority: 1. Site-specific custom label 2. General custom label 3. JSON file 4. Database label 5. Translation 6. Filename
     */
    public function getDisplayLabel(): string
    {
        // 1. Check for site-specific custom field label (highest priority)
        $currentSiteId = Craft::$app->getSites()->getCurrentSite()->id;
        if (!empty($this->customLabels) && isset($this->customLabels[$currentSiteId])) {
            return $this->customLabels[$currentSiteId];
        }
        
        // 2. Check for general custom field label (fallback)
        if ($this->customLabel) {
            return $this->customLabel;
        }

        // 3. Check for JSON file label (high priority)
        if ($jsonLabel = $this->getJsonLabel()) {
            return $jsonLabel;
        }

        // 4. Check database field label (medium priority)
        if ($this->label) {
            return $this->label;
        }

        // 5. Check translation system (low priority)
        if ($translatedLabel = $this->getTranslatedLabel()) {
            return $translatedLabel;
        }

        // 6. Generate label from name (fallback)
        return StringHelper::titleize($this->name);
    }

    /**
     * Get site-specific custom label
     */
    public function getSiteSpecificCustomLabel(?int $siteId = null): ?string
    {
        // Simple: use current site if no site ID provided
        if (!$siteId) {
            $currentSite = Craft::$app->getSites()->getCurrentSite();
            $siteId = $currentSite->id;
        }
        
        // Return site-specific custom label
        if (!empty($this->customLabels) && isset($this->customLabels[$siteId])) {
            return $this->customLabels[$siteId];
        }
        return null;
    }

    /**
     * Get label from JSON file if it exists
     */
    private function getJsonLabel(): ?string
    {
        if (!$this->path) {
            return null;
        }

        // Build JSON file path based on SVG path
        $basePath = IconManager::getInstance()->getSettings()->iconSetsPath;
        $iconPath = Craft::getAlias($basePath . '/' . $this->path);
        $jsonPath = str_replace('.svg', '.json', $iconPath);

        if (!file_exists($jsonPath)) {
            return null;
        }

        try {
            $jsonContent = file_get_contents($jsonPath);
            $data = json_decode($jsonContent, true);

            if (!$data) {
                return null;
            }

            // Get current site language
            $currentSite = Craft::$app->sites->getCurrentSite();
            $language = $currentSite->language ?? 'en';

            // Check for language-specific label first
            if ($language === 'ar' && isset($data['labelAr'])) {
                return $data['labelAr'];
            } elseif ($language === 'en' && isset($data['labelEn'])) {
                return $data['labelEn'];
            } elseif (isset($data['label'])) {
                return $data['label'];
            }

            return null;
        } catch (\Exception $e) {
            // Log error but don't break
            Craft::warning("Failed to parse JSON label file: {$jsonPath}", 'icon-manager');
            return null;
        }
    }

    /**
     * Get label from translation system
     */
    private function getTranslatedLabel(): ?string
    {
        try {
            // Try to translate the icon name using your translation system
            $translated = Craft::t('alhatab', $this->name);
            
            // If translation exists (not the same as the original key), use it
            if ($translated !== $this->name) {
                return $translated;
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if the icon file exists
     */
    public function exists(): bool
    {
        if ($this->type !== self::TYPE_SVG || !$this->path) {
            return false;
        }
        
        $basePath = IconManager::getInstance()->getSettings()->iconSetsPath;
        $fullPath = Craft::getAlias($basePath . '/' . $this->path);
        
        return file_exists($fullPath);
    }

    /**
     * Get the icon's SVG content
     */
    public function getSvg(): ?string
    {
        if ($this->type !== self::TYPE_SVG || !$this->path) {
            return null;
        }

        // Build the full path using current config
        $basePath = IconManager::getInstance()->getSettings()->iconSetsPath;
        $fullPath = Craft::getAlias($basePath . '/' . $this->path);
        
        if (!file_exists($fullPath)) {
            $this->logWarning("Icon file not found: {$this->name}", [
                'iconId' => $this->id,
                'expectedPath' => $fullPath,
                'basePath' => $basePath,
                'relativePath' => $this->path
            ]);
            return null;
        }

        $svg = file_get_contents($fullPath);

        if ($svg === false) {
            $this->logError("Failed to read icon file: {$this->name}", [
                'iconId' => $this->id,
                'filePath' => $fullPath,
                'fileExists' => file_exists($fullPath),
                'fileSize' => filesize($fullPath)
            ]);
            return null;
        }

        if (empty($svg)) {
            $this->logWarning("Icon file is empty: {$this->name}", [
                'iconId' => $this->id,
                'filePath' => $fullPath,
                'fileSize' => filesize($fullPath)
            ]);
            return null;
        }

        // Sanitize SVG for security
        $sanitized = $this->sanitizeSvg($svg);

        if (empty($sanitized) && !empty($svg)) {
            $this->logWarning("SVG content removed during sanitization: {$this->name}", [
                'iconId' => $this->id,
                'originalLength' => strlen($svg),
                'filePath' => $fullPath
            ]);
        }

        return $sanitized;
    }
    
    /**
     * Sanitize SVG content to prevent XSS attacks
     */
    private function sanitizeSvg(?string $svg): ?string
    {
        if (!$svg) {
            return null;
        }
        
        // Remove any script tags
        $svg = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $svg);
        
        // Remove event handlers
        $svg = preg_replace('/\son\w+\s*=\s*["\'][^"\']*["\']/i', '', $svg);
        $svg = preg_replace('/\son\w+\s*=\s*[^\s>]*/i', '', $svg);
        
        // Remove javascript: protocol
        $svg = preg_replace('/href\s*=\s*["\']?\s*javascript:[^"\']*["\']?/i', 'href="#"', $svg);
        
        // Remove data: URLs with script content
        $svg = preg_replace('/src\s*=\s*["\']?\s*data:[^"\']*script[^"\']*["\']?/i', 'src=""', $svg);
        
        return $svg;
    }
    
    /**
     * Get the icon's content (alias for getSvg for now)
     * 
     * @return \Twig\Markup|null
     */
    public function getContent()
    {
        $svg = $this->getSvg();
        return $svg ? Template::raw($svg) : null;
    }

    /**
     * Render the icon as HTML
     * 
     * @return \Twig\Markup
     */
    public function render(array $options = [])
    {
        // Extract specific options
        $class = $options['class'] ?? '';
        $size = $options['size'] ?? null;
        $width = $options['width'] ?? null;
        $height = $options['height'] ?? null;
        
        // Remove these from options so they don't get added as attributes
        unset($options['class'], $options['size'], $options['width'], $options['height']);
        
        // Remaining options become attributes
        $attributes = $options;

        switch ($this->type) {
            case self::TYPE_SVG:
                $svg = $this->getSvg();
                if (!$svg) {
                    $this->logWarning("SVG content is empty for icon: {$this->name}", [
                        'iconId' => $this->id,
                        'iconSet' => $this->iconSetId,
                        'path' => $this->path
                    ]);
                    return '';
                }

                // Add classes and attributes to SVG
                $dom = new \DOMDocument();
                $loadResult = @$dom->loadHTML($svg, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

                if (!$loadResult) {
                    $this->logError("Failed to parse SVG content for icon: {$this->name}", [
                        'iconId' => $this->id,
                        'iconSet' => $this->iconSetId,
                        'svgLength' => strlen($svg)
                    ]);
                    return '';
                }

                $svgElement = $dom->getElementsByTagName('svg')->item(0);

                if ($svgElement) {
                    if ($class) {
                        $existingClass = $svgElement->getAttribute('class');
                        $svgElement->setAttribute('class', trim($existingClass . ' ' . $class));
                    }

                    // Handle dimensions with proportional scaling
                    if ($size) {
                        $svgElement->setAttribute('width', $size);
                        $svgElement->setAttribute('height', $size);
                    } else {
                        // Get original dimensions from SVG
                        $originalWidth = $svgElement->getAttribute('width') ?: null;
                        $originalHeight = $svgElement->getAttribute('height') ?: null;
                        
                        // Calculate proportional dimensions
                        if ($width && $height) {
                            // Both specified - use as-is
                            $svgElement->setAttribute('width', $width);
                            $svgElement->setAttribute('height', $height);
                        } elseif ($width && $originalWidth && $originalHeight) {
                            // Width specified, calculate proportional height
                            $aspectRatio = $originalHeight / $originalWidth;
                            $calculatedHeight = round($width * $aspectRatio, 1);
                            $svgElement->setAttribute('width', $width);
                            $svgElement->setAttribute('height', $calculatedHeight);
                        } elseif ($height && $originalWidth && $originalHeight) {
                            // Height specified, calculate proportional width  
                            $aspectRatio = $originalWidth / $originalHeight;
                            $calculatedWidth = round($height * $aspectRatio, 1);
                            $svgElement->setAttribute('width', $calculatedWidth);
                            $svgElement->setAttribute('height', $height);
                        } else {
                            // Fallback to original behavior
                            if ($width) {
                                $svgElement->setAttribute('width', $width);
                            }
                            if ($height) {
                                $svgElement->setAttribute('height', $height);
                            }
                        }
                    }

                    foreach ($attributes as $key => $value) {
                        $svgElement->setAttribute($key, $value);
                    }

                    return Template::raw($dom->saveHTML($svgElement));
                } else {
                    $this->logWarning("No SVG element found in parsed content for icon: {$this->name}", [
                        'iconId' => $this->id,
                        'iconSet' => $this->iconSetId,
                        'contentPreview' => substr($svg, 0, 100)
                    ]);
                }
                break;

            case self::TYPE_SPRITE:
                $attributes['class'] = $class;
                if ($size) {
                    $attributes['width'] = $size;
                    $attributes['height'] = $size;
                }

                $html = Html::tag('svg', 
                    Html::tag('use', '', ['href' => '#' . $this->value]),
                    $attributes
                );
                return Template::raw($html);

            case self::TYPE_FONT:
                $attributes['class'] = trim($this->value . ' ' . $class);
                
                // Material Icons use ligatures (icon name as content)
                $content = '';
                if (isset($this->metadata['materialType'])) {
                    $content = $this->metadata['iconName'] ?? '';
                }
                
                $html = Html::tag('i', $content, $attributes);
                return Template::raw($html);
        }

        return Template::raw('');
    }

    /**
     * Check if icon matches search keywords
     */
    public function matchesKeywords(string $search): bool
    {
        $search = strtolower($search);
        
        // Check name
        if (str_contains(strtolower($this->name), $search)) {
            return true;
        }

        // Check label
        if (str_contains(strtolower($this->label), $search)) {
            return true;
        }

        // Check keywords
        foreach ($this->keywords as $keyword) {
            if (str_contains(strtolower($keyword), $search)) {
                return true;
            }
        }

        return false;
    }
    
    /**
     * Get the icon set this icon belongs to
     */
    public function getIconSet(): ?IconSet
    {
        return \lindemannrock\iconmanager\IconManager::getInstance()->iconSets->getIconSetById($this->iconSetId);
    }
}