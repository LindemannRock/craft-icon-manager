<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\iconmanager\console;

use Craft;
use craft\console\Controller;
use lindemannrock\iconmanager\IconManager;

/**
 * Test Controller
 */
class TestController extends Controller
{
    /**
     * Test saving icon set settings
     */
    public function actionTestSave(): int
    {
        $iconSet = IconManager::getInstance()->iconSets->getIconSetByHandle('testIcons');
        
        if (!$iconSet) {
            $this->stdout("Icon set 'testIcons' not found\n");
            return 1;
        }
        
        $this->stdout("Current settings:\n");
        $this->stdout(json_encode($iconSet->settings, JSON_PRETTY_PRINT) . "\n\n");
        
        // Update settings
        $iconSet->settings = [
            'folder' => '/alhatab',
            'includeSubfolders' => false,
        ];
        
        $this->stdout("Saving with new settings:\n");
        $this->stdout(json_encode($iconSet->settings, JSON_PRETTY_PRINT) . "\n\n");
        
        if (IconManager::getInstance()->iconSets->saveIconSet($iconSet)) {
            $this->stdout("Save successful!\n\n");
            
            // Reload and verify
            $reloaded = IconManager::getInstance()->iconSets->getIconSetById($iconSet->id, true);
            $this->stdout("Reloaded settings:\n");
            $this->stdout(json_encode($reloaded->settings, JSON_PRETTY_PRINT) . "\n");
        } else {
            $this->stdout("Save failed!\n");
            $this->stdout("Errors: " . json_encode($iconSet->getErrors()) . "\n");
        }
        
        return 0;
    }
    
    /**
     * Test icon loading
     */
    public function actionTestIcon(): int
    {
        $iconSet = IconManager::getInstance()->iconSets->getIconSetByHandle('testIcons');
        
        if (!$iconSet) {
            $this->stdout("Icon set 'testIcons' not found\n");
            return 1;
        }
        
        $icons = IconManager::getInstance()->icons->getIconsBySetId($iconSet->id);
        $this->stdout("Found " . count($icons) . " icons\n\n");
        
        if (count($icons) > 0) {
            $icon = $icons[0];
            $this->stdout("Testing first icon:\n");
            $this->stdout("Name: " . $icon->name . "\n");
            $this->stdout("Path: " . $icon->path . "\n");
            $this->stdout("Resolved path: " . Craft::getAlias($icon->path) . "\n");
            $this->stdout("File exists: " . (file_exists(Craft::getAlias($icon->path)) ? "Yes" : "No") . "\n");
            
            $content = $icon->getContent();
            $this->stdout("Content length: " . ($content ? strlen($content) : 0) . " bytes\n");
            
            if ($content) {
                $this->stdout("First 100 chars: " . substr($content, 0, 100) . "...\n");
            }
        }
        
        return 0;
    }
    
    /**
     * Test settings storage
     */
    public function actionTestSettings(): int
    {
        $this->stdout("Testing settings storage...\n\n");
        
        // Get current settings
        $settings = IconManager::getInstance()->getSettings();
        $this->stdout("Current settings from model:\n");
        $this->stdout("iconSetsPath: " . $settings->iconSetsPath . "\n");
        $this->stdout("enableCache: " . ($settings->enableCache ? 'true' : 'false') . "\n");
        $this->stdout("cacheDuration: " . $settings->cacheDuration . "\n");
        // $this->stdout("iconsPerPage: " . $settings->iconsPerPage . "\n");
        // $this->stdout("showLabels: " . ($settings->showLabels ? 'true' : 'false') . "\n");
        // $this->stdout("iconSize: " . $settings->iconSize . "\n");
        $this->stdout("enabledIconTypes: " . json_encode($settings->enabledIconTypes) . "\n\n");
        
        // Test saving
        $this->stdout("Testing save...\n");
        $settings->cacheDuration = 12345;
        if ($settings->saveToDatabase()) {
            $this->stdout("Save successful!\n\n");
            
            // Reload and verify
            $reloaded = \lindemannrock\iconmanager\models\Settings::loadFromDatabase();
            $this->stdout("Reloaded cacheDuration: " . $reloaded->cacheDuration . "\n");
        } else {
            $this->stdout("Save failed!\n");
            $this->stdout("Errors: " . json_encode($settings->getErrors()) . "\n");
        }
        
        return 0;
    }
    
    /**
     * Check config override functionality
     */
    public function actionCheckConfig(): int
    {
        $this->stdout("Testing config override functionality...\n\n");
        
        // Get settings
        $settings = IconManager::getInstance()->getSettings();
        
        // Show current settings
        $this->stdout("Current settings:\n");
        $this->stdout("- iconSetsPath: " . $settings->iconSetsPath . "\n");
        $this->stdout("  Resolved: " . $settings->getResolvedIconSetsPath() . "\n");
        $this->stdout("- enableCache: " . ($settings->enableCache ? 'true' : 'false') . "\n");
        $this->stdout("- cacheDuration: " . $settings->cacheDuration . "\n");
        // $this->stdout("- iconsPerPage: " . $settings->iconsPerPage . "\n");
        // $this->stdout("- showLabels: " . ($settings->showLabels ? 'true' : 'false') . "\n");
        // $this->stdout("- iconSize: " . $settings->iconSize . "\n");
        $this->stdout("- enabledIconTypes: " . json_encode($settings->enabledIconTypes) . "\n\n");
        
        // Check overrides
        $this->stdout("Overridden settings:\n");
        $overriddenSettings = $settings->getOverriddenSettings();
        if (empty($overriddenSettings)) {
            $this->stdout("None\n");
        } else {
            foreach ($overriddenSettings as $setting) {
                $this->stdout("- " . $setting . " (from config file)\n");
            }
        }
        
        // Check icon type overrides
        $this->stdout("\nOverridden icon types:\n");
        $overriddenIconTypes = $settings->getOverriddenIconTypes();
        if (empty($overriddenIconTypes)) {
            $this->stdout("None\n");
        } else {
            foreach ($overriddenIconTypes as $iconType) {
                $this->stdout("- " . $iconType . " (from config file)\n");
            }
        }
        
        // Check config file
        $this->stdout("\nConfig file settings:\n");
        $configSettings = Craft::$app->getConfig()->getConfigFromFile('icon-manager');
        if ($configSettings) {
            foreach ($configSettings as $key => $value) {
                $this->stdout("- " . $key . ": " . json_encode($value) . "\n");
            }
        } else {
            $this->stdout("No config file found or empty\n");
        }
        
        // Check environment
        $this->stdout("\nEnvironment: " . Craft::$app->env . "\n");
        
        return 0;
    }
}
