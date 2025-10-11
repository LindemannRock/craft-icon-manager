<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\iconmanager\fields;

use lindemannrock\iconmanager\IconManager;
use lindemannrock\iconmanager\models\Icon;

use lindemannrock\iconmanager\web\assets\field\IconManagerFieldAsset;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\base\PreviewableFieldInterface;
use craft\base\SortableFieldInterface;
use craft\base\EagerLoadingFieldInterface;
use craft\helpers\Html;
use craft\helpers\Json;

/**
 * Icon Manager Field
 */
class IconManagerField extends Field implements PreviewableFieldInterface, SortableFieldInterface, EagerLoadingFieldInterface
{
    /**
     * @var array|string Allowed icon sets for this field ('*' for all, array for specific sets)
     */
    public array|string $allowedIconSets = '*';

    /**
     * @var bool Show search box
     */
    public bool $showSearch = true;

    /**
     * @var bool Show labels with icons
     */
    public bool $showLabels = true;

    /**
     * @var string Icon size in picker (small, medium, large)
     */
    public string $iconSize = 'medium';

    /**
     * @var int Icons per page in picker
     */
    public int $iconsPerPage = 100;

    /**
     * @var bool Allow multiple icon selection
     */
    public bool $allowMultiple = false;

    /**
     * @var bool Allow custom labels for icons
     */
    public bool $allowCustomLabels = false;
    

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('icon-manager', 'Icon Manager');
    }

    /**
     * @inheritdoc
     */
    public static function supportedTranslationMethods(): array
    {
        return [
            self::TRANSLATION_METHOD_NONE,
            self::TRANSLATION_METHOD_SITE,
            self::TRANSLATION_METHOD_SITE_GROUP,
            self::TRANSLATION_METHOD_LANGUAGE,
        ];
    }

    /**
     * @inheritdoc
     */
    public static function icon(): string
    {
        return '@appicons/folder-grid.svg';
    }

    /**
     * @inheritdoc
     */
    public static function hasContentColumn(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function isRequiredInSettingsForm(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        
        $rules[] = [['allowedIconSets'], 'safe'];
        $rules[] = [['showSearch', 'showLabels', 'allowMultiple', 'allowCustomLabels'], 'boolean'];
        $rules[] = [['iconSize'], 'in', 'range' => ['small', 'medium', 'large']];
        $rules[] = [['iconsPerPage'], 'integer', 'min' => 10, 'max' => 500];

        return $rules;
    }
    
    /**
     * @inheritdoc
     */
    public function setAttributes($values, $safeOnly = true): void
    {
        // Handle the "All" option for allowed icon sets
        if (isset($values['allowedIconSets'])) {
            // Convert ['*'] array to '*' string for consistency with Verbb
            if (is_array($values['allowedIconSets']) && count($values['allowedIconSets']) === 1 && $values['allowedIconSets'][0] === '*') {
                $values['allowedIconSets'] = '*';
            }
            // If empty or null, default to '*' (All)
            elseif (empty($values['allowedIconSets'])) {
                $values['allowedIconSets'] = '*';
            }
        } else {
            // No allowedIconSets sent - default to "All"
            $values['allowedIconSets'] = '*';
        }
        
        parent::setAttributes($values, $safeOnly);
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        // Get all enabled icon sets with allowed types (filters by both enabled flag AND enabled icon types)
        $iconSets = IconManager::getInstance()->iconSets->getAllEnabledIconSetsWithAllowedTypes();
        $iconSetOptions = [];

        foreach ($iconSets as $iconSet) {
            $iconSetOptions[] = [
                'label' => $iconSet->name,
                'value' => $iconSet->handle,
            ];
        }

        return Craft::$app->getView()->renderTemplate('icon-manager/_components/fields/IconManagerField/settings', [
            'field' => $this,
            'iconSetOptions' => $iconSetOptions,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function normalizeValue(mixed $value, ?ElementInterface $element = null): mixed
    {
        // Handle multiple icons
        if ($this->allowMultiple) {
            if (is_array($value)) {
                $icons = [];
                foreach ($value as $item) {
                    if ($item instanceof Icon) {
                        $icons[] = $item;
                    } elseif (is_array($item)) {
                        $icon = $this->_createIconFromArray($item, $element);
                        if ($icon) {
                            $icons[] = $icon;
                        }
                    }
                }
                return $icons;
            }

            if (is_string($value) && !empty($value)) {
                $decoded = Json::decodeIfJson($value);
                if (is_array($decoded)) {
                    $icons = [];
                    // Check if it's an array of icon objects
                    foreach ($decoded as $item) {
                        if (is_array($item)) {
                            $icon = $this->_createIconFromArray($item, $element);
                            if ($icon) {
                                $icons[] = $icon;
                            }
                        }
                    }
                    return $icons;
                }
            }

            return [];
        }

        // Handle single icon (existing logic)
        if ($value instanceof Icon) {
            return $value;
        }

        if (is_string($value) && !empty($value)) {
            // Try to decode JSON
            $decoded = Json::decodeIfJson($value);
            if (is_array($decoded)) {
                $icon = $this->_createIconFromArray($decoded, $element);
                if ($icon) {
                }
                return $icon;
            }
        }

        if (is_array($value) && !empty($value)) {
            $icon = $this->_createIconFromArray($value, $element);
            if ($icon) {
                // Restore full customLabels array from database if available
                $this->_restoreCustomLabelsFromDatabase($icon, $element);
            }
            return $icon;
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function serializeValue(mixed $value, ?ElementInterface $element = null): mixed
    {
        // Handle multiple icons
        if ($this->allowMultiple) {
            if (is_array($value)) {
                $serializedIcons = [];
                foreach ($value as $icon) {
                    if ($icon instanceof Icon) {
                        $iconData = [
                            'iconSetHandle' => $icon->iconSetHandle,
                            'name' => $icon->name,
                            'type' => $icon->type,
                            'value' => $icon->value,
                        ];
                        
                        // Include custom labels (site-specific) if set
                        if ($icon->customLabel) {
                            $iconData['customLabel'] = $icon->customLabel;
                        }
                        
                        // Save site-specific custom labels array
                        if (!empty($icon->customLabels)) {
                            $iconData['customLabels'] = $icon->customLabels;
                        }
                        
                        $serializedIcons[] = $iconData;
                    }
                }
                return Json::encode($serializedIcons);
            }
            return Json::encode([]);
        }

        // Handle single icon (existing logic)
        if ($value instanceof Icon) {
            $iconData = [
                'iconSetHandle' => $value->iconSetHandle,
                'name' => $value->name,
                'type' => $value->type,
                'value' => $value->value,
            ];
            
            // Include custom labels (site-specific) if set
            if ($value->customLabel) {
                $iconData['customLabel'] = $value->customLabel;
            }
            
            // Save site-specific custom labels array
            if (!empty($value->customLabels)) {
                $iconData['customLabels'] = $value->customLabels;
            }
            
            return Json::encode($iconData);
        }

        return null;
    }


    /**
     * @inheritdoc
     */
    protected function inputHtml(mixed $value, ?ElementInterface $element = null, bool $inline = false): string
    {
        $id = Html::id($this->handle);
        $namespacedId = Craft::$app->getView()->namespaceInputId($id);

        // Get allowed icon sets - handle like Verbb does
        $iconSets = [];
        if ($this->allowedIconSets === '*') {
            // "All" selected - show all enabled icon sets
            $iconSets = IconManager::getInstance()->iconSets->getAllEnabledIconSets();
        } elseif (!empty($this->allowedIconSets) && is_array($this->allowedIconSets)) {
            // Specific icon sets selected
            $iconSets = IconManager::getInstance()->iconSets->getIconSetsByHandles($this->allowedIconSets);
        } else {
            // Fallback to all enabled icon sets
            $iconSets = IconManager::getInstance()->iconSets->getAllEnabledIconSets();
        }

        // Don't pass icon data - let JavaScript fetch it via AJAX in a single batch request
        $showSearchJson = $this->showSearch ? 'true' : 'false';
        $showLabelsJson = $this->showLabels ? 'true' : 'false';
        $allowMultipleJson = $this->allowMultiple ? 'true' : 'false';
        $allowCustomLabelsJson = $this->allowCustomLabels ? 'true' : 'false';
        $currentSiteId = Craft::$app->getSites()->getCurrentSite()->id;

        $js = <<<JS
new IconManager.IconPicker('$namespacedId', {
    fieldId: {$this->id},
    siteId: {$currentSiteId},
    showSearch: $showSearchJson,
    showLabels: $showLabelsJson,
    iconSize: '{$this->iconSize}',
    iconsPerPage: {$this->iconsPerPage},
    allowMultiple: $allowMultipleJson,
    allowCustomLabels: $allowCustomLabelsJson
});
JS;

        // Register asset bundle
        Craft::$app->getView()->registerAssetBundle(IconManagerFieldAsset::class);

        // Font Awesome Kit support temporarily disabled

        // Register field-specific JavaScript
        Craft::$app->getView()->registerJs($js);

        return Craft::$app->getView()->renderTemplate('icon-manager/_components/fields/IconManagerField/input', [
            'field' => $this,
            'id' => $id,
            'name' => $this->handle,
            'value' => $value,
            'iconSets' => $iconSets,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getElementValidationRules(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getSearchKeywords(mixed $value, ElementInterface $element): string
    {
        if ($value instanceof Icon) {
            $keywords = [$value->name, $value->label];
            $keywords = array_merge($keywords, $value->keywords);
            return implode(' ', $keywords);
        }

        return '';
    }

    /**
     * @inheritdoc
     */
    public function getTableAttributeHtml(mixed $value, ElementInterface $element): string
    {
        // Handle multiple icons
        if ($this->allowMultiple) {
            if (is_array($value) && !empty($value)) {
                $html = '';
                $maxDisplay = 3; // Limit displayed icons in table view
                $count = 0;
                
                foreach ($value as $icon) {
                    if ($icon instanceof Icon && $count < $maxDisplay) {
                        $html .= $icon->render(['size' => 24, 'class' => 'icon-manager-table-icon']);
                        $count++;
                    }
                }
                
                // Show count if there are more icons
                if (count($value) > $maxDisplay) {
                    $html .= '<span class="icon-manager-table-count">+' . (count($value) - $maxDisplay) . '</span>';
                }
                
                return $html;
            }
            return '';
        }

        // Handle single icon (existing logic)
        if (!$value instanceof Icon) {
            return '';
        }

        return $value->render(['size' => 24, 'class' => 'icon-manager-table-icon']);
    }

    /**
     * @inheritdoc
     */
    public function getSortOption(): array
    {
        return [
            'label' => $this->name,
            'orderBy' => $this->handle,
            'attribute' => 'field:' . $this->uid,
        ];
    }

    /**
     * Create an Icon model from array data
     */
    private function _createIconFromArray(array $data, ?ElementInterface $element = null): ?Icon
    {
        if (empty($data['iconSetHandle']) || empty($data['name'])) {
            return null;
        }

        // Get the full icon data from the service
        $icon = IconManager::getInstance()->icons->getIcon($data['iconSetHandle'], $data['name']);

        if (!$icon) {
            // Create a basic icon if not found in cache
            $icon = new Icon([
                'iconSetHandle' => $data['iconSetHandle'],
                'name' => $data['name'],
                'type' => $data['type'] ?? Icon::TYPE_SVG,
                'value' => $data['value'] ?? $data['name'],
            ]);
        }

        // Set custom label if provided
        if (isset($data['customLabel'])) {
            $icon->customLabel = $data['customLabel'];
        }
        
        // Restore site-specific custom labels array
        if (isset($data['customLabels']) && is_array($data['customLabels'])) {
            $icon->customLabels = $data['customLabels'];
        }

        return $icon;
    }

    /**
     * @inheritdoc
     */
    public function getEagerLoadingMap(array $sourceElements): array|null|false
    {
        // For non-element fields, we just return null to indicate no eager loading needed
        // Craft will handle the field values normally through normalizeValue()
        return null;
    }
    
    /**
     * @inheritdoc
     */
    public function getEagerLoadingGqlConditions(): ?array
    {
        // Allow all conditions for GraphQL
        return null;
    }

}