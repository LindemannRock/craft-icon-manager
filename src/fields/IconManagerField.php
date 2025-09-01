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
use craft\db\Query;
use craft\helpers\Html;
use craft\helpers\Json;

/**
 * Icon Manager Field
 */
class IconManagerField extends Field implements PreviewableFieldInterface, SortableFieldInterface
{
    /**
     * @var array Allowed icon sets for this field
     */
    public array $allowedIconSets = [];

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
    public function getSettingsHtml(): ?string
    {
        // Get all available icon sets
        $iconSets = IconManager::getInstance()->iconSets->getAllIconSets();
        $iconSetOptions = [];

        foreach ($iconSets as $iconSet) {
            if ($iconSet->enabled) {
                $iconSetOptions[] = [
                    'label' => $iconSet->name,
                    'value' => $iconSet->handle,
                ];
            }
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
                        
                        // Include custom label if set
                        if ($icon->customLabel) {
                            $iconData['customLabel'] = $icon->customLabel;
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
            
            // Include custom label if set
            if ($value->customLabel) {
                $iconData['customLabel'] = $value->customLabel;
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

        // Get allowed icon sets
        $iconSets = [];
        if (!empty($this->allowedIconSets)) {
            $iconSets = IconManager::getInstance()->iconSets->getIconSetsByHandles($this->allowedIconSets);
        } else {
            $iconSets = IconManager::getInstance()->iconSets->getAllEnabledIconSets();
        }

        // Prepare icon data for JavaScript
        $iconsData = [];
        foreach ($iconSets as $iconSet) {
            // Skip loading icons for Font Awesome Kits (they use manual input)
            if ($iconSet->type === 'font-awesome' && isset($iconSet->settings['type']) && $iconSet->settings['type'] === 'kit') {
                continue;
            }
            
            $icons = IconManager::getInstance()->icons->getIconsBySetId($iconSet->id);
            foreach ($icons as $icon) {
                $iconsData[] = $icon->toPickerArray();
            }
        }

        $iconsDataJson = Json::encode($iconsData);
        $showSearchJson = $this->showSearch ? 'true' : 'false';
        $showLabelsJson = $this->showLabels ? 'true' : 'false';
        $allowMultipleJson = $this->allowMultiple ? 'true' : 'false';
        
        $js = <<<JS
new IconManager.IconPicker('$namespacedId', {
    icons: $iconsDataJson,
    showSearch: $showSearchJson,
    showLabels: $showLabelsJson,
    iconSize: '{$this->iconSize}',
    iconsPerPage: {$this->iconsPerPage},
    allowMultiple: $allowMultipleJson
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

        return $icon;
    }

}