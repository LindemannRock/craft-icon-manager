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
 * Settings Controller
 */
class SettingsController extends Controller
{
    /**
     * Settings index
     */
    public function actionIndex(): Response
    {
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
        
        $request = Craft::$app->getRequest();
        $plugin = IconManager::getInstance();
        $settings = $plugin->getSettings();
        
        // Get settings from request (nested under 'settings' key)
        $postedSettings = $request->getBodyParam('settings', []);
        
        // Debug logging
        Craft::info('Posted settings: ' . json_encode($postedSettings), 'icon-manager');
        Craft::info('Current overridden settings: ' . json_encode($settings->getOverriddenSettings()), 'icon-manager');
        Craft::info('Current overridden icon types: ' . json_encode($settings->getOverriddenIconTypes()), 'icon-manager');
        
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
        
        if (!$settings->isOverridden('iconsPerPage')) {
            $settings->iconsPerPage = isset($postedSettings['iconsPerPage']) ? (int)$postedSettings['iconsPerPage'] : $settings->iconsPerPage;
        }
        
        if (!$settings->isOverridden('showLabels')) {
            $settings->showLabels = isset($postedSettings['showLabels']) ? (bool)$postedSettings['showLabels'] : $settings->showLabels;
        }
        
        if (!$settings->isOverridden('iconSize')) {
            $settings->iconSize = $postedSettings['iconSize'] ?? $settings->iconSize;
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
        
        // Before saving, log the current values
        Craft::info('Settings before save - showLabels: ' . ($settings->showLabels ? 'true' : 'false'), 'icon-manager');
        Craft::info('Settings before save - iconSize: ' . $settings->iconSize, 'icon-manager');
        Craft::info('Settings before save - material-icons: ' . ($settings->enabledIconTypes['material-icons'] ? 'true' : 'false'), 'icon-manager');
        
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