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
use lindemannrock\logginglibrary\traits\LoggingTrait;

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
            'label' => $this->getDisplayLabel(), // Use display label for current site language
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
            // In CP: Use the site being edited (from request param)
            // In frontend: Use the current site
            if (Craft::$app->getRequest()->getIsCpRequest()) {
                $siteHandle = Craft::$app->getRequest()->getParam('site');
                if ($siteHandle) {
                    $site = Craft::$app->sites->getSiteByHandle($siteHandle);
                    $language = $site ? $site->language : (Craft::$app->sites->getCurrentSite()->language ?? 'en');
                } else {
                    $language = Craft::$app->sites->getCurrentSite()->language ?? 'en';
                }
            } else {
                $currentSite = Craft::$app->sites->getCurrentSite();
                $language = $currentSite->language ?? 'en';
            }

            // Convert language code to clean format
            // Supports both hyphen and underscore: 'en-US' -> 'en', 'en_GB' -> 'en', 'ar-AE' -> 'ar'
            $languageCode = strtolower(preg_split('/[-_]/', $language)[0]);

            // Check for language-specific label using dynamic key: label_{language}
            $languageKey = 'label_' . $languageCode;
            if (isset($data[$languageKey])) {
                return $data[$languageKey];
            }

            // Fallback to legacy format for backward compatibility
            $legacyKey = 'label' . ucfirst($languageCode); // labelEn, labelAr, labelFr, etc.
            if (isset($data[$legacyKey])) {
                return $data[$legacyKey];
            }

            // Fallback to generic label
            if (isset($data['label'])) {
                return $data['label'];
            }

            return null;
        } catch (\Exception $e) {
            // Log error but don't break
            $this->logWarning("Failed to parse JSON label file", ['jsonPath' => $jsonPath]);
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
     * Check if the icon exists and can be rendered
     */
    public function exists(): bool
    {
        // Font icons (Material Icons, WebFont, Font Awesome) always exist if they have metadata
        if ($this->type === self::TYPE_FONT) {
            return !empty($this->metadata) || !empty($this->name);
        }

        // Sprite icons exist if they have a sprite ID
        if ($this->type === self::TYPE_SPRITE) {
            return !empty($this->value);
        }

        // SVG icons need to check if the file exists
        if ($this->type === self::TYPE_SVG && $this->path) {
            $basePath = IconManager::getInstance()->getSettings()->iconSetsPath;
            $fullPath = Craft::getAlias($basePath . '/' . $this->path);
            return file_exists($fullPath);
        }

        return false;
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
     * Get the icon's content for display
     * - For SVG icons: returns full SVG markup
     * - For font icons (Material Icons): returns HTML with icon name as ligature
     * - For sprite icons: returns use reference
     *
     * @return \Twig\Markup|null
     */
    public function getContent()
    {
        switch ($this->type) {
            case self::TYPE_SVG:
                $svg = $this->getSvg();
                return $svg ? Template::raw($svg) : null;

            case self::TYPE_FONT:
                // For font icons (Material Icons, WebFont), return the icon as a span
                $iconContent = $this->path ?? $this->name;
                $useRawHtml = false;

                // Material Symbols use the icon name directly (ligatures)
                if (isset($this->metadata['materialType']) && $this->metadata['materialType'] === 'symbols') {
                    $iconContent = $this->metadata['iconName'] ?? $iconContent;
                }
                // Material Icons classic also use the icon name (ligatures)
                elseif (isset($this->metadata['materialType']) && $this->metadata['materialType'] === 'icons') {
                    $iconContent = $this->metadata['iconName'] ?? $iconContent;
                }
                // WebFont icons use unicode characters - need to convert decimal to actual character
                elseif (isset($this->metadata['unicode'])) {
                    $unicode = $this->metadata['unicode'];
                    $iconContent = mb_chr($unicode, 'UTF-8');
                    $useRawHtml = true;

                    // Debug log with more detail
                    $this->logDebug("WebFont icon details", [
                        'iconName' => $this->name,
                        'unicode' => $unicode,
                        'unicodeHex' => '0x' . dechex($unicode),
                        'charBytes' => bin2hex($iconContent),
                        'value' => $this->value
                    ]);
                }

                // For WebFont icons, use the base CSS prefix from value (e.g., "icon")
                // The font-family is applied via the base class, not individual icon classes
                $className = $this->value ?? 'material-icons';

                if ($useRawHtml) {
                    // For unicode characters, build HTML manually to avoid escaping
                    $html = '<span class="' . Html::encode($className) . '">' . $iconContent . '</span>';
                } else {
                    $html = Html::tag('span', $iconContent, ['class' => $className]);
                }

                return Template::raw($html);

            case self::TYPE_SPRITE:
                // For sprite icons, return an SVG use reference
                $html = Html::beginTag('svg', ['class' => 'icon']) .
                        Html::tag('use', '', ['href' => '#' . $this->value]) .
                        Html::endTag('svg');
                return Template::raw($html);

            default:
                return null;
        }
    }

    /**
     * Render the icon as HTML
     *
     * @return \Twig\Markup
     */
    public function render(array $options = [])
    {
        // Register fonts for font-based icons before rendering
        if ($this->type === self::TYPE_FONT && $this->iconSetHandle) {
            $this->_registerFonts();
        }

        // Register sprites for sprite-based icons before rendering
        if ($this->type === self::TYPE_SPRITE && $this->iconSetHandle) {
            $this->_registerSprite();
        }

        return $this->_renderIcon($options);
    }

    /**
     * Internal render method
     *
     * @return \Twig\Markup
     */
    private function _renderIcon(array $options = [])
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

                // Apply font size if specified (font icons are 10px smaller than SVGs for visual balance)
                if ($size) {
                    $fontIconSize = $size - 10;
                    if ($fontIconSize < 12) {
                        $fontIconSize = $size; // Don't make it too small
                    }
                    $attributes['style'] = 'font-size: ' . $fontIconSize . 'px';
                }

                // Material Icons use ligatures (icon name as content) with <i> tag
                if (isset($this->metadata['materialType'])) {
                    $content = $this->metadata['iconName'] ?? '';
                    $html = Html::tag('i', $content, $attributes);
                    return Template::raw($html);
                }

                // WebFont icons use unicode characters with <span> tag
                if (isset($this->metadata['unicode'])) {
                    $content = mb_chr($this->metadata['unicode'], 'UTF-8');
                    $html = Html::tag('span', $content, $attributes);
                    return Template::raw($html);
                }

                // Font Awesome or other font icons with <i> tag
                $html = Html::tag('i', '', $attributes);
                return Template::raw($html);
        }

        return Template::raw('');
    }

    /**
     * Register fonts for this icon (called before rendering)
     */
    private function _registerFonts(): void
    {
        static $registeredFonts = [];

        // Only register once per icon set
        if (isset($registeredFonts[$this->iconSetHandle])) {
            return;
        }

        $iconSet = IconManager::getInstance()->iconSets->getIconSetByHandle($this->iconSetHandle);
        if (!$iconSet) {
            return;
        }

        $view = Craft::$app->getView();

        // Material Icons - register Google Fonts
        if ($iconSet->type === 'material-icons') {
            $view->registerCssFile('https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=block', [
                'crossorigin' => 'anonymous'
            ]);
            $view->registerCssFile('https://fonts.googleapis.com/icon?family=Material+Icons&display=block', [
                'crossorigin' => 'anonymous'
            ]);
        }
        // WebFont - register @font-face CSS
        elseif ($iconSet->type === 'web-font') {
            $fontCss = \lindemannrock\iconmanager\iconsets\WebFont::getFontFaceCss($iconSet);
            if ($fontCss) {
                $view->registerCss($fontCss);
            }
        }

        $registeredFonts[$this->iconSetHandle] = true;
    }

    /**
     * Register sprite for this icon (called before rendering)
     */
    private function _registerSprite(): void
    {
        static $registeredSprites = [];

        // Only register once per icon set
        if (isset($registeredSprites[$this->iconSetHandle])) {
            return;
        }

        $iconSet = IconManager::getInstance()->iconSets->getIconSetByHandle($this->iconSetHandle);
        if (!$iconSet || $iconSet->type !== 'svg-sprite') {
            return;
        }

        // Get sprite content and inject it
        $settings = $iconSet->settings ?? [];
        $spriteFile = $settings['spriteFile'] ?? null;

        if (!$spriteFile) {
            return;
        }

        $spritePath = IconManager::getInstance()->getSettings()->getResolvedIconSetsPath() . DIRECTORY_SEPARATOR . $spriteFile;

        if (!file_exists($spritePath)) {
            return;
        }

        $spriteContent = @file_get_contents($spritePath);
        if (!$spriteContent) {
            return;
        }

        // Strip any <style> tags to prevent CSS pollution
        $spriteContent = preg_replace('/<style[^>]*>[\s\S]*?<\/style>/i', '', $spriteContent);

        // Inject sprite into page
        $view = Craft::$app->getView();
        $view->registerHtml('<div style="display:none;">' . $spriteContent . '</div>', \yii\web\View::POS_BEGIN);

        $registeredSprites[$this->iconSetHandle] = true;
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
