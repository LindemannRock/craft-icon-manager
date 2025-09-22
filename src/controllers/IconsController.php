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
     * Get icon data for JavaScript
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
}