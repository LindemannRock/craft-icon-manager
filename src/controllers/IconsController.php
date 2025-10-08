<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\iconmanager\controllers;

use lindemannrock\iconmanager\IconManager;
use lindemannrock\iconmanager\traits\LoggingTrait;

use Craft;
use craft\web\Controller;
use yii\web\Response;

/**
 * Icons Controller
 */
class IconsController extends Controller
{
    use LoggingTrait;

    /**
     * @var array|bool|int Allow anonymous access
     */
    protected array|bool|int $allowAnonymous = ['render'];

    /**
     * Render an icon
     */
    public function actionRender(): Response
    {
        $request = Craft::$app->getRequest();
        $iconSetHandle = $request->getRequiredParam('iconSet');
        $iconName = $request->getRequiredParam('icon');

        $this->logTrace("Icon render request: {$iconSetHandle}:{$iconName}", [
            'iconSet' => $iconSetHandle,
            'icon' => $iconName,
            'userAgent' => $request->getUserAgent(),
            'ip' => $request->getUserIP()
        ]);

        $iconSet = IconManager::getInstance()->iconSets->getIconSetByHandle($iconSetHandle);
        if (!$iconSet || !$iconSet->enabled) {
            $this->logWarning("Icon render failed - icon set not found or disabled: {$iconSetHandle}");
            throw new \yii\web\NotFoundHttpException('Icon set not found');
        }

        $icon = IconManager::getInstance()->icons->getIcon($iconSetHandle, $iconName);
        if (!$icon) {
            $this->logWarning("Icon render failed - icon not found: {$iconSetHandle}:{$iconName}");
            throw new \yii\web\NotFoundHttpException('Icon not found');
        }

        // Return SVG content
        return $this->asRaw($icon->getContent())->format(Response::FORMAT_RAW);
    }
    
    /**
     * Get icon data for JavaScript (single icon - kept for backwards compatibility)
     */
    public function actionGetData(): Response
    {
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $iconSetHandle = $request->getRequiredParam('iconSet');
        $iconName = $request->getRequiredParam('icon');

        $this->logTrace("Icon data request (AJAX): {$iconSetHandle}:{$iconName}", [
            'iconSet' => $iconSetHandle,
            'icon' => $iconName,
            'requestType' => 'ajax-data'
        ]);

        $iconSet = IconManager::getInstance()->iconSets->getIconSetByHandle($iconSetHandle);
        if (!$iconSet || !$iconSet->enabled) {
            $this->logWarning("Icon data request failed - icon set not found: {$iconSetHandle}");
            return $this->asJson(['error' => 'Icon set not found']);
        }

        $icon = IconManager::getInstance()->icons->getIcon($iconSetHandle, $iconName);
        if (!$icon) {
            $this->logWarning("Icon data request failed - icon not found: {$iconSetHandle}:{$iconName}");
            return $this->asJson(['error' => 'Icon not found']);
        }

        return $this->asJson([
            'success' => true,
            'icon' => [
                'name' => $icon->name,
                'label' => $icon->label,
                'content' => $icon->getContent(),
                'keywords' => $icon->keywords,
                'type' => $icon->type,
            ]
        ]);
    }

    /**
     * Get all icons for a field in a single batch request
     */
    public function actionGetIconsForField(): Response
    {
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $fieldId = $request->getRequiredParam('fieldId');

        $this->logTrace("Batch icons request for field: {$fieldId}");

        // Get the field
        $field = Craft::$app->getFields()->getFieldById($fieldId);
        if (!$field) {
            $this->logWarning("Field not found: {$fieldId}");
            return $this->asJson(['error' => 'Field not found']);
        }

        // Get allowed icon sets for this field
        $iconSets = [];
        if ($field->allowedIconSets === '*') {
            $iconSets = IconManager::getInstance()->iconSets->getAllEnabledIconSets();
        } elseif (!empty($field->allowedIconSets) && is_array($field->allowedIconSets)) {
            $iconSets = IconManager::getInstance()->iconSets->getIconSetsByHandles($field->allowedIconSets);
        } else {
            $iconSets = IconManager::getInstance()->iconSets->getAllEnabledIconSets();
        }

        // Collect all icons with their content
        $iconsData = [];
        $iconCount = 0;

        foreach ($iconSets as $iconSet) {
            // Skip Font Awesome Kits (they use manual input)
            if ($iconSet->type === 'font-awesome' && isset($iconSet->settings['type']) && $iconSet->settings['type'] === 'kit') {
                continue;
            }

            $icons = IconManager::getInstance()->icons->getIconsBySetId($iconSet->id);
            foreach ($icons as $icon) {
                $iconArray = $icon->toPickerArray();

                // Add the SVG content (what we removed from toPickerArray)
                // But now it's in a single batch request, not embedded in HTML
                try {
                    $iconArray['content'] = $icon->getContent();
                } catch (\Exception $e) {
                    $iconArray['content'] = null;
                }

                $iconsData[] = $iconArray;
                $iconCount++;
            }
        }

        $this->logTrace("Returning {$iconCount} icons for field {$fieldId}");

        return $this->asJson([
            'success' => true,
            'icons' => $iconsData,
        ]);
    }
}