<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\iconmanager\controllers;

use lindemannrock\iconmanager\IconManager;
use lindemannrock\logginglibrary\traits\LoggingTrait;

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
    protected array|bool|int $allowAnonymous = ['render', 'serve-sprite'];

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
            $this->logWarning("Icon render failed - icon set not found or disabled", ['iconSetHandle' => $iconSetHandle]);
            throw new \yii\web\NotFoundHttpException('Icon set not found');
        }

        $icon = IconManager::getInstance()->icons->getIcon($iconSetHandle, $iconName);
        if (!$icon) {
            $this->logWarning("Icon render failed - icon not found", [
                'iconSetHandle' => $iconSetHandle,
                'iconName' => $iconName
            ]);
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

        $iconSet = IconManager::getInstance()->iconSets->getIconSetByHandle($iconSetHandle);
        if (!$iconSet || !$iconSet->enabled) {
            $this->logWarning("Icon data request failed - icon set not found", ['iconSetHandle' => $iconSetHandle]);
            return $this->asJson(['error' => 'Icon set not found']);
        }

        $icon = IconManager::getInstance()->icons->getIcon($iconSetHandle, $iconName);
        if (!$icon) {
            $this->logWarning("Icon data request failed - icon not found", [
                'iconSetHandle' => $iconSetHandle,
                'iconName' => $iconName
            ]);
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
     * Get fonts/sprites needed for selected icons (lightweight endpoint for page init)
     */
    public function actionGetAssetsForSelectedIcons(): Response
    {
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $selectedIcons = $request->getParam('selectedIcons', []);

        $fonts = [];
        $sprites = [];

        // Get unique icon set handles from selected icons
        $iconSetHandles = array_unique(array_column($selectedIcons, 'iconSetHandle'));

        foreach ($iconSetHandles as $handle) {
            $iconSet = IconManager::getInstance()->iconSets->getIconSetByHandle($handle);
            if (!$iconSet) {
                continue;
            }

            // Get required assets for this icon set
            if ($iconSet->type === 'material-icons') {
                $handler = new \lindemannrock\iconmanager\iconsets\MaterialIcons($iconSet);
                $assets = $handler->getAssets();

                foreach ($assets as $asset) {
                    if ($asset['type'] === 'css' && isset($asset['url'])) {
                        $fonts[] = [
                            'type' => 'remote',
                            'url' => $asset['url'],
                        ];
                    } elseif ($asset['type'] === 'css' && isset($asset['inline'])) {
                        $fonts[] = [
                            'type' => 'inline',
                            'css' => $asset['inline'],
                        ];
                    }
                }
            } elseif ($iconSet->type === 'web-font') {
                $assets = \lindemannrock\iconmanager\iconsets\WebFont::getAssets($iconSet);

                foreach ($assets as $asset) {
                    if ($asset['type'] === 'css' && isset($asset['inline'])) {
                        $fonts[] = [
                            'type' => 'inline',
                            'css' => $asset['inline'],
                        ];
                    }
                }
            } elseif ($iconSet->type === 'svg-sprite') {
                $spriteUrl = \lindemannrock\iconmanager\iconsets\SvgSprite::getSpriteUrl($iconSet);
                if ($spriteUrl) {
                    $sprites[] = [
                        'name' => $iconSet->handle,
                        'url' => $spriteUrl,
                    ];
                }
            }
        }

        return $this->asJson([
            'success' => true,
            'fonts' => array_values(array_unique($fonts, SORT_REGULAR)),
            'sprites' => array_values(array_unique($sprites, SORT_REGULAR)),
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

        // Get the field
        $field = Craft::$app->getFields()->getFieldById($fieldId);
        if (!$field) {
            $this->logWarning("Field not found", ['fieldId' => $fieldId]);
            return $this->asJson(['error' => 'Field not found']);
        }

        // Get allowed icon sets for this field (only icon sets with enabled types)
        $iconSets = [];
        if ($field->allowedIconSets === '*') {
            $iconSets = IconManager::getInstance()->iconSets->getAllEnabledIconSetsWithAllowedTypes();
        } elseif (!empty($field->allowedIconSets) && is_array($field->allowedIconSets)) {
            $iconSets = IconManager::getInstance()->iconSets->getIconSetsByHandles($field->allowedIconSets);
            // Filter to only include icon sets with enabled types
            $settings = IconManager::getInstance()->getSettings();
            $enabledTypes = $settings->enabledIconTypes ?? [];
            $iconSets = array_filter($iconSets, function($iconSet) use ($enabledTypes) {
                return $iconSet->enabled && (($enabledTypes[$iconSet->type] ?? false) === true);
            });
        } else {
            $iconSets = IconManager::getInstance()->iconSets->getAllEnabledIconSetsWithAllowedTypes();
        }

        // Collect all icons with their content and required assets (fonts/CSS/sprites)
        $iconsData = [];
        $fonts = [];
        $sprites = [];
        $iconCount = 0;

        foreach ($iconSets as $iconSet) {
            // Skip Font Awesome Kits (they use manual input)
            if ($iconSet->type === 'font-awesome' && isset($iconSet->settings['type']) && $iconSet->settings['type'] === 'kit') {
                continue;
            }

            // Get required assets for this icon set (Material Icons, WebFont, etc.)
            if ($iconSet->type === 'material-icons') {
                $handler = new \lindemannrock\iconmanager\iconsets\MaterialIcons($iconSet);
                $assets = $handler->getAssets();

                foreach ($assets as $asset) {
                    if ($asset['type'] === 'css' && isset($asset['url'])) {
                        $fonts[] = [
                            'type' => 'remote',
                            'url' => $asset['url'],
                        ];
                    } elseif ($asset['type'] === 'css' && isset($asset['inline'])) {
                        // Add inline CSS as a data attribute for JS to inject
                        $fonts[] = [
                            'type' => 'inline',
                            'css' => $asset['inline'],
                        ];
                    }
                }
            } elseif ($iconSet->type === 'web-font') {
                $assets = \lindemannrock\iconmanager\iconsets\WebFont::getAssets($iconSet);

                foreach ($assets as $asset) {
                    if ($asset['type'] === 'css' && isset($asset['inline'])) {
                        $fonts[] = [
                            'type' => 'inline',
                            'css' => $asset['inline'],
                        ];
                    }
                }
            } elseif ($iconSet->type === 'svg-sprite') {
                // Get sprite URL for this icon set
                $spriteUrl = \lindemannrock\iconmanager\iconsets\SvgSprite::getSpriteUrl($iconSet);
                if ($spriteUrl) {
                    $sprites[] = [
                        'name' => $iconSet->handle,
                        'url' => $spriteUrl,
                    ];
                }
            }

            $icons = IconManager::getInstance()->icons->getIconsBySetId($iconSet->id);
            foreach ($icons as $icon) {
                $iconArray = $icon->toPickerArray();

                // Add the icon content (SVG markup or font HTML)
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

        return $this->asJson([
            'success' => true,
            'icons' => $iconsData,
            'fonts' => array_values(array_unique($fonts, SORT_REGULAR)),
            'sprites' => array_values(array_unique($sprites, SORT_REGULAR)),
        ]);
    }

    /**
     * Serve a web font file
     */
    public function actionServeFont(): Response
    {
        $iconSetHandle = Craft::$app->getRequest()->getQueryParam('iconSet');
        $fileName = Craft::$app->getRequest()->getQueryParam('file');

        if (!$iconSetHandle || !$fileName) {
            throw new \yii\web\NotFoundHttpException('Invalid parameters');
        }

        // Get icon set
        $iconSet = IconManager::getInstance()->iconSets->getIconSetByHandle($iconSetHandle);
        if (!$iconSet || $iconSet->type !== 'web-font') {
            throw new \yii\web\NotFoundHttpException('Icon set not found');
        }

        // Get font file path
        $settings = $iconSet->settings ?? [];
        $fontFile = $settings['fontFile'] ?? null;

        if (!$fontFile || basename($fontFile) !== $fileName) {
            throw new \yii\web\NotFoundHttpException('Font file not found');
        }

        $fontPath = IconManager::getInstance()->getSettings()->getResolvedIconSetsPath() . DIRECTORY_SEPARATOR . $fontFile;

        if (!file_exists($fontPath)) {
            throw new \yii\web\NotFoundHttpException('Font file does not exist');
        }

        // Determine MIME type
        $ext = pathinfo($fontFile, PATHINFO_EXTENSION);
        $mimeType = match(strtolower($ext)) {
            'woff2' => 'font/woff2',
            'woff' => 'font/woff',
            'ttf' => 'font/ttf',
            'otf' => 'font/otf',
            default => 'application/octet-stream'
        };

        // Return font file
        return Craft::$app->getResponse()->sendFile($fontPath, $fileName, [
            'mimeType' => $mimeType,
            'inline' => true
        ]);
    }

    /**
     * Serve an SVG sprite file
     */
    public function actionServeSprite(): Response
    {
        $iconSetHandle = Craft::$app->getRequest()->getQueryParam('iconSet');
        $fileName = Craft::$app->getRequest()->getQueryParam('file');

        if (!$iconSetHandle || !$fileName) {
            throw new \yii\web\NotFoundHttpException('Invalid parameters');
        }

        // Get icon set
        $iconSet = IconManager::getInstance()->iconSets->getIconSetByHandle($iconSetHandle);
        if (!$iconSet || $iconSet->type !== 'svg-sprite') {
            throw new \yii\web\NotFoundHttpException('Icon set not found');
        }

        // Get sprite file path
        $settings = $iconSet->settings ?? [];
        $spriteFile = $settings['spriteFile'] ?? null;

        if (!$spriteFile || basename($spriteFile) !== $fileName) {
            throw new \yii\web\NotFoundHttpException('Sprite file not found');
        }

        $spritePath = IconManager::getInstance()->getSettings()->getResolvedIconSetsPath() . DIRECTORY_SEPARATOR . $spriteFile;

        if (!file_exists($spritePath)) {
            throw new \yii\web\NotFoundHttpException('Sprite file does not exist');
        }

        // Return sprite file
        return Craft::$app->getResponse()->sendFile($spritePath, $fileName, [
            'mimeType' => 'image/svg+xml',
            'inline' => true
        ]);
    }
}