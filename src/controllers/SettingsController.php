<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\iconmanager\controllers;

use Craft;
use craft\web\Controller;

use lindemannrock\iconmanager\IconManager;
use lindemannrock\iconmanager\models\Settings;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use yii\web\Response;

/**
 * Settings Controller
 *
 * @since 1.0.0
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
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        return $this->actionGeneral();
    }

    /**
     * General settings
     *
     * @return Response
     */
    public function actionGeneral(): Response
    {
        $this->requirePermission('iconManager:editSettings');

        $settings = IconManager::$plugin->getSettings();

        return $this->renderTemplate('icon-manager/settings/general', [
            'settings' => $settings,
        ]);
    }

    /**
     * Icon Types settings
     *
     * @return Response
     * @since 1.10.0
     */
    public function actionIconTypes(): Response
    {
        $this->requirePermission('iconManager:editSettings');

        $settings = IconManager::$plugin->getSettings();

        return $this->renderTemplate('icon-manager/settings/icon-types', [
            'settings' => $settings,
        ]);
    }

    /**
     * SVG Optimization settings
     *
     * @return Response
     * @since 1.10.0
     */
    public function actionSvgOptimization(): Response
    {
        $this->requirePermission('iconManager:editSettings');

        $settings = IconManager::$plugin->getSettings();

        return $this->renderTemplate('icon-manager/settings/svg-optimization/index', [
            'settings' => $settings,
        ]);
    }

    /**
     * Interface settings
     *
     * @return Response
     */
    public function actionInterface(): Response
    {
        $this->requirePermission('iconManager:editSettings');

        $settings = IconManager::$plugin->getSettings();

        return $this->renderTemplate('icon-manager/settings/interface', [
            'settings' => $settings,
        ]);
    }

    /**
     * Cache settings
     *
     * @return Response
     * @since 5.6.0
     */
    public function actionCache(): Response
    {
        $this->requirePermission('iconManager:editSettings');

        $settings = IconManager::$plugin->getSettings();

        return $this->renderTemplate('icon-manager/settings/cache', [
            'settings' => $settings,
        ]);
    }

    /**
     * Save settings
     *
     * @return Response|null
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission('iconManager:editSettings');

        // Load current settings from database
        $settings = Settings::loadFromDatabase();

        // Get only the posted settings (fields from the current page)
        $settingsData = Craft::$app->getRequest()->getBodyParam('settings', []);

        // Only update fields that were posted and are not overridden by config
        foreach ($settingsData as $key => $value) {
            if (!$settings->isOverriddenByConfig($key) && property_exists($settings, $key)) {
                // Check for setter method first (handles array conversions, etc.)
                $setterMethod = 'set' . ucfirst($key);
                if (method_exists($settings, $setterMethod)) {
                    $settings->$setterMethod($value);
                } else {
                    $settings->$key = $value;
                }
            }
        }

        // Validate
        if (!$settings->validate()) {
            Craft::$app->getSession()->setError(Craft::t('icon-manager', 'Could not save settings.'));

            // Get the section to re-render the correct template with errors
            $section = $this->_validSettingsSection(
                $this->request->getBodyParam('section', 'general'),
            );
            $template = "icon-manager/settings/{$section}";

            return $this->renderTemplate($template, [
                'settings' => $settings,
            ]);
        }

        // Save settings to database
        if ($settings->saveToDatabase()) {
            // Reload settings so Craft picks up the new values
            IconManager::$plugin->reloadSettings();

            Craft::$app->getSession()->setNotice(Craft::t('icon-manager', 'Settings saved.'));
        } else {
            Craft::$app->getSession()->setError(Craft::t('icon-manager', 'Could not save settings'));
            return null;
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * Validate and sanitize the settings section parameter
     *
     * @param string $section The section from POST data
     * @return string A validated section name
     */
    private function _validSettingsSection(string $section): string
    {
        $allowed = ['general', 'icon-types', 'svg-optimization', 'interface', 'cache'];

        return in_array($section, $allowed, true) ? $section : 'general';
    }
}
