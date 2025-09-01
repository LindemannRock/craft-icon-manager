<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\iconmanager\controllers;

use lindemannrock\iconmanager\IconManager;

use Craft;
use craft\web\Controller;
use yii\web\Response;

/**
 * Icons Controller
 */
class IconsController extends Controller
{
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
        
        $iconSet = IconManager::getInstance()->iconSets->getIconSetByHandle($iconSetHandle);
        if (!$iconSet || !$iconSet->enabled) {
            throw new \yii\web\NotFoundHttpException('Icon set not found');
        }
        
        $icon = IconManager::getInstance()->icons->getIcon($iconSetHandle, $iconName);
        if (!$icon) {
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
        
        $iconSet = IconManager::getInstance()->iconSets->getIconSetByHandle($iconSetHandle);
        if (!$iconSet || !$iconSet->enabled) {
            return $this->asJson(['error' => 'Icon set not found']);
        }
        
        $icon = IconManager::getInstance()->icons->getIcon($iconSetHandle, $iconName);
        if (!$icon) {
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