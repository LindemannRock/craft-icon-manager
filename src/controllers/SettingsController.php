<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\iconmanager\controllers;

use Craft;
use craft\helpers\FileHelper;
use craft\web\Controller;
use lindemannrock\base\cache\DisposableCacheStoragePresenter;
use lindemannrock\base\cache\DisposableCacheStorageResolver;
use lindemannrock\base\helpers\SettingsPostHelper;
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
        $this->requirePermission('iconManager:manageSettings');

        $settings = IconManager::$plugin->getSettings();
        $settings->validate(['iconSetsPath']);

        return $this->renderTemplate('icon-manager/settings/general', [
            'settings' => $settings,
            'resolvedIconSetsPath' => $this->resolvedIconSetsPath($settings),
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
        $this->requirePermission('iconManager:manageSettings');

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
        $this->requirePermission('iconManager:manageSettings');

        $settings = IconManager::$plugin->getSettings();

        return $this->renderTemplate('icon-manager/settings/svg-optimization/index', [
            'settings' => $settings,
            'optimizationBackupPath' => $this->optimizationBackupPath(),
        ]);
    }

    /**
     * Interface settings
     *
     * @return Response
     */
    public function actionInterface(): Response
    {
        $this->requirePermission('iconManager:manageSettings');

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
        $this->requirePermission('iconManager:manageSettings');

        $settings = IconManager::$plugin->getSettings();

        return $this->renderTemplate('icon-manager/settings/cache', [
            'settings' => $settings,
            'cacheStorage' => $this->cacheStorageTemplateVariables($settings),
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
        $this->requirePermission('iconManager:manageSettings');
        $section = $this->_validSettingsSection(
            $this->request->getBodyParam('section', 'general'),
        );

        // Load current settings from database
        $settings = Settings::loadFromDatabase();
        $oldCacheStorage = $settings->cacheStorageMethod;

        // Get only the posted settings (fields from the current page)
        $settingsData = Craft::$app->getRequest()->getBodyParam('settings', []);

        $result = SettingsPostHelper::apply(
            model: $settings,
            postedValues: is_array($settingsData) ? $settingsData : [],
            allowedAttributes: $this->_validationAttributesForSection($section),
            shouldSkipAttribute: fn(string $attribute): bool => $settings->isOverriddenByConfig($attribute),
        );

        $attributesToValidate = $result->attributesToValidate;

        // Validate only current section attributes
        if ($result->hasErrors || !$settings->validate($attributesToValidate)) {
            Craft::$app->getSession()->setError(Craft::t('icon-manager', 'Could not save settings.'));

            $template = $section === 'svg-optimization'
                ? 'icon-manager/settings/svg-optimization/index'
                : "icon-manager/settings/{$section}";

            $variables = [
                'settings' => $settings,
                'resolvedIconSetsPath' => $section === 'general' ? $this->resolvedIconSetsPath($settings) : null,
                'optimizationBackupPath' => $section === 'svg-optimization' ? $this->optimizationBackupPath() : null,
            ];
            if ($section === 'cache') {
                $variables['cacheStorage'] = $this->cacheStorageTemplateVariables($settings);
            }

            return $this->renderTemplate($template, $variables);
        }

        // Save only current section attributes
        if ($settings->saveToDatabase($attributesToValidate)) {
            // Reload settings so Craft picks up the new values
            IconManager::$plugin->reloadSettings();

            if ($section === 'cache') {
                $newSettings = IconManager::$plugin->getSettings();
                IconManager::$plugin->iconCache->invalidateStorageTransition(
                    $oldCacheStorage,
                    $newSettings->cacheStorageMethod,
                );
                IconManager::$plugin->icons->clearMemoryCache();
            }

            Craft::$app->getSession()->setNotice(Craft::t('icon-manager', 'Settings saved.'));
        } else {
            Craft::$app->getSession()->setError(Craft::t('icon-manager', 'Could not save settings.'));
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

    /**
     * Get settings attributes that belong to a section
     *
     * @param string $section
     * @return array<int, string>
     */
    private function _validationAttributesForSection(string $section): array
    {
        return match ($section) {
            'general' => ['pluginName', 'iconSetsPath', 'logLevel'],
            'icon-types' => ['enabledIconTypes'],
            'svg-optimization' => [
                'enableOptimization',
                'enableOptimizationBackup',
                'backupVolumeUid',
                'backupPath',
                'scanClipPaths',
                'scanMasks',
                'scanFilters',
                'scanComments',
                'scanInlineStyles',
                'scanLargeFiles',
                'scanWidthHeight',
                'scanWidthHeightWithViewBox',
                'optimizeConvertColorsToHex',
                'optimizeConvertCssClasses',
                'optimizeConvertEmptyTags',
                'optimizeConvertInlineStyles',
                'optimizeFlattenGroups',
                'optimizeMinifyCoordinates',
                'optimizeMinifyTransformations',
                'optimizeRemoveComments',
                'optimizeRemoveDefaultAttributes',
                'optimizeRemoveDeprecatedAttributes',
                'optimizeRemoveDoctype',
                'optimizeRemoveEnableBackground',
                'optimizeRemoveEmptyAttributes',
                'optimizeRemoveInkscapeFootprints',
                'optimizeRemoveInvisibleCharacters',
                'optimizeRemoveMetadata',
                'optimizeRemoveWhitespace',
                'optimizeRemoveUnusedNamespaces',
                'optimizeRemoveUnusedMasks',
                'optimizeRemoveWidthHeight',
                'optimizeSortAttributes',
                'optimizeFixAttributeNames',
                'optimizeRemoveAriaAndRole',
                'optimizeRemoveDataAttributes',
                'optimizeRemoveDuplicateElements',
                'optimizeRemoveEmptyGroups',
                'optimizeRemoveEmptyTextElements',
                'optimizeRemoveNonStandardAttributes',
                'optimizeRemoveNonStandardTags',
                'optimizeRemoveTitleAndDesc',
                'optimizeRemoveUnsafeElements',
                'optimizeScopeSvgStyles',
                'optimizeAllowRiskyRules',
            ],
            'interface' => [
                'itemsPerPage',
                'timeFormat',
                'monthFormat',
                'dateOrder',
                'dateSeparator',
                'showSeconds',
            ],
            'cache' => ['cacheStorageMethod', 'enableCache', 'cacheDuration'],
            default => [],
        };
    }

    private function resolvedIconSetsPath(Settings $settings): ?string
    {
        if ($settings->hasErrors('iconSetsPath')) {
            return null;
        }

        try {
            return FileHelper::normalizePath($settings->getResolvedIconSetsPath());
        } catch (\Throwable) {
            return null;
        }
    }

    private function optimizationBackupPath(): string
    {
        return IconManager::getInstance()->getSettings()->getBackupLocationLabel();
    }

    /**
     * @return array{
     *     applicationToken: string,
     *     filePresentation: \lindemannrock\base\cache\DisposableCacheStoragePresentation,
     *     applicationPresentation: \lindemannrock\base\cache\DisposableCacheStoragePresentation,
     *     filePath: string|null
     * }
     */
    private function cacheStorageTemplateVariables(Settings $settings): array
    {
        $storage = IconManager::$plugin->iconCache;
        $presenter = new DisposableCacheStoragePresenter();
        $applicationToken = DisposableCacheStorageResolver::applicationOptionToken($settings->cacheStorageMethod);
        $fileDecision = $storage->getStorageDecision('file');
        $applicationDecision = $storage->getStorageDecision($applicationToken);

        return [
            'applicationToken' => $applicationToken,
            'filePresentation' => $presenter->present($fileDecision),
            'applicationPresentation' => $presenter->present($applicationDecision),
            'filePath' => $storage->getDisplayFilePath($fileDecision),
        ];
    }
}
