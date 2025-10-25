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
use lindemannrock\logginglibrary\traits\LoggingTrait;
use yii\web\Response;

/**
 * Settings Controller
 */
class SettingsController extends Controller
{
    use LoggingTrait;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle('icon-manager');
    }

    /**
     * Settings index
     */
    public function actionIndex(): Response
    {
        $this->requirePermission('iconManager:editSettings');

        $plugin = IconManager::getInstance();

        // Force reload settings to ensure we have fresh data from database
        $plugin->reloadSettings();
        $settings = $plugin->getSettings();

        return $this->renderTemplate('icon-manager/settings', [
            'plugin' => $plugin,
            'settings' => $settings,
            'fullPageForm' => true,
        ]);
    }
    
    /**
     * Save settings
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission('iconManager:editSettings');
        
        $request = Craft::$app->getRequest();
        $plugin = IconManager::getInstance();
        $settings = $plugin->getSettings();
        
        // Get settings from request (nested under 'settings' key)
        $postedSettings = $request->getBodyParam('settings', []);
        
        
        // Only update non-overridden settings
        if (!$settings->isOverridden('pluginName')) {
            $settings->pluginName = $postedSettings['pluginName'] ?? $settings->pluginName;
        }
        
        if (!$settings->isOverridden('iconSetsPath')) {
            $settings->iconSetsPath = $postedSettings['iconSetsPath'] ?? $settings->iconSetsPath;
        }
        
        if (!$settings->isOverridden('enableCache')) {
            $settings->enableCache = isset($postedSettings['enableCache']) ? (bool)$postedSettings['enableCache'] : $settings->enableCache;
        }
        
        if (!$settings->isOverridden('cacheDuration')) {
            $settings->cacheDuration = isset($postedSettings['cacheDuration']) ? (int)$postedSettings['cacheDuration'] : $settings->cacheDuration;
        }

        if (!$settings->isOverridden('logLevel')) {
            $settings->logLevel = $postedSettings['logLevel'] ?? $settings->logLevel;
        }

        // Handle lightswitch fields for icon types
        // Lightswitches send "1" when on, nothing when off
        $enabledIconTypes = $postedSettings['enabledIconTypes'] ?? [];
        
        // Only update non-overridden icon types
        $currentIconTypes = $settings->enabledIconTypes;
        foreach (['svg-folder', 'svg-sprite', 'font-awesome', 'material-icons'] as $iconType) {
            // Only update if not overridden by config
            if (!$settings->isIconTypeOverridden($iconType)) {
                $currentIconTypes[$iconType] = !empty($enabledIconTypes[$iconType]);
            }
        }
        
        $settings->enabledIconTypes = $currentIconTypes;
        
        
        // Validate
        if (!$settings->validate()) {
            $errors = $settings->getErrors();
            $errorMessage = Craft::t('icon-manager', 'Couldn\'t save plugin settings.');
            
            // Add validation errors to the message
            if (!empty($errors)) {
                $errorDetails = [];
                foreach ($errors as $attribute => $attributeErrors) {
                    $errorDetails[] = $attribute . ': ' . implode(', ', $attributeErrors);
                }
                $errorMessage .= ' ' . implode(' ', $errorDetails);
            }
            
            Craft::$app->getSession()->setError($errorMessage);
            
            Craft::$app->getUrlManager()->setRouteParams([
                'settings' => $settings
            ]);
            
            return null;
        }
        
        // Save to database using the new method
        if (!$settings->saveToDatabase()) {
            Craft::$app->getSession()->setError(Craft::t('icon-manager', 'Couldn\'t save plugin settings.'));
            return null;
        }
        
        // Don't use Craft's savePluginSettings as it blocks saving when config file exists
        // We're using our own database table for settings instead
        
        // Force reload settings from database to clear Craft's internal cache
        $plugin->reloadSettings();
        
        Craft::$app->getSession()->setNotice(Craft::t('icon-manager', 'Plugin settings saved.'));
        
        return $this->redirectToPostedUrl();
    }
}