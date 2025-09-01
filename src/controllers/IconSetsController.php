<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\iconmanager\controllers;

use lindemannrock\iconmanager\IconManager;
use lindemannrock\iconmanager\models\IconSet;

use Craft;
use craft\web\Controller;
use yii\web\Response;

/**
 * Icon Sets Controller
 */
class IconSetsController extends Controller
{
    /**
     * Icon sets index
     */
    public function actionIndex(): Response
    {
        $iconSets = IconManager::getInstance()->iconSets->getAllIconSets();

        return $this->renderTemplate('icon-manager/icon-sets/index', [
            'iconSets' => $iconSets,
        ]);
    }

    /**
     * Edit an icon set
     */
    public function actionEdit(?int $iconSetId = null, ?IconSet $iconSet = null): Response
    {
        // If we have an icon set passed (from save redirect), use it
        if ($iconSet === null) {
            if ($iconSetId) {
                // Force fresh load
                $iconSet = IconManager::getInstance()->iconSets->getIconSetById($iconSetId, true);
                if (!$iconSet) {
                    throw new \yii\web\NotFoundHttpException('Icon set not found');
                }
            } else {
                $iconSet = new IconSet();
            }
        }

        return $this->renderTemplate('icon-manager/icon-sets/edit', [
            'iconSet' => $iconSet,
            'isNew' => !$iconSet->id,
            'availableFolders' => $iconSet->getAvailableFolders(),
        ]);
    }

    /**
     * Save an icon set
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $iconSetId = $request->getBodyParam('iconSetId');

        if ($iconSetId) {
            $iconSet = IconManager::getInstance()->iconSets->getIconSetById($iconSetId);
            if (!$iconSet) {
                throw new \yii\web\NotFoundHttpException('Icon set not found');
            }
        } else {
            $iconSet = new IconSet();
        }

        // Populate model
        $iconSet->name = $request->getBodyParam('name');
        $iconSet->handle = $request->getBodyParam('handle');
        $iconSet->type = $request->getBodyParam('type');
        $iconSet->enabled = (bool)$request->getBodyParam('enabled');
        
        // Get settings and ensure proper types
        $settings = $request->getBodyParam('settings', []);
        
        // Log settings for debugging if needed
        // Craft::info("Icon set save - Settings: " . json_encode($settings), __METHOD__);
        
        // Convert includeSubfolders to boolean
        if (isset($settings['includeSubfolders'])) {
            $settings['includeSubfolders'] = (bool)$settings['includeSubfolders'];
        }
        
        // Normalize folder path - ensure it has a leading slash if not empty
        if (isset($settings['folder']) && $settings['folder'] !== '') {
            $settings['folder'] = '/' . ltrim($settings['folder'], '/');
        }
        
        $iconSet->settings = $settings;

        // Save
        if (!IconManager::getInstance()->iconSets->saveIconSet($iconSet)) {
            Craft::$app->getSession()->setError(Craft::t('icon-manager', 'Couldn\'t save icon set.'));

            Craft::$app->getUrlManager()->setRouteParams([
                'iconSet' => $iconSet,
            ]);

            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('icon-manager', 'Icon set saved.'));

        // Always redirect to the edit page after saving
        return $this->redirect('icon-manager/icon-sets/' . $iconSet->id);
    }

    /**
     * Delete an icon set
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();

        $iconSetId = Craft::$app->getRequest()->getRequiredBodyParam('iconSetId');
        $iconSet = IconManager::getInstance()->iconSets->getIconSetById($iconSetId);

        if (!$iconSet) {
            throw new \yii\web\NotFoundHttpException('Icon set not found');
        }

        if (!IconManager::getInstance()->iconSets->deleteIconSet($iconSet)) {
            if ($this->request->getAcceptsJson()) {
                return $this->asJson(['success' => false, 'error' => Craft::t('icon-manager', 'Couldn\'t delete icon set.')]);
            }
            Craft::$app->getSession()->setError(Craft::t('icon-manager', 'Couldn\'t delete icon set.'));
            return $this->redirectToPostedUrl();
        }

        if ($this->request->getAcceptsJson()) {
            return $this->asJson(['success' => true]);
        }
        
        Craft::$app->getSession()->setNotice(Craft::t('icon-manager', 'Icon set deleted.'));
        return $this->redirectToPostedUrl();
    }

    /**
     * Refresh icons for an icon set
     */
    public function actionRefreshIcons(): Response
    {
        $this->requirePostRequest();
        
        $iconSetId = Craft::$app->getRequest()->getRequiredBodyParam('iconSetId');
        $iconSet = IconManager::getInstance()->iconSets->getIconSetById($iconSetId);
        
        if (!$iconSet) {
            throw new \yii\web\NotFoundHttpException('Icon set not found');
        }
        
        try {
            IconManager::getInstance()->icons->refreshIconsForSet($iconSet);
            Craft::$app->getSession()->setNotice(Craft::t('icon-manager', 'Icons refreshed.'));
        } catch (\Exception $e) {
            Craft::$app->getSession()->setError(Craft::t('icon-manager', 'Could not refresh icons: {error}', ['error' => $e->getMessage()]));
        }
        
        return $this->redirectToPostedUrl();
    }
}