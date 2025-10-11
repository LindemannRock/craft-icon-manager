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
use lindemannrock\logginglibrary\traits\LoggingTrait;

use Craft;
use craft\web\Controller;
use yii\web\Response;

/**
 * Icon Sets Controller
 */
class IconSetsController extends Controller
{
    use LoggingTrait;
    /**
     * Icon sets index
     */
    public function actionIndex(): Response
    {
        // Only show icon sets whose types are enabled in settings
        $iconSets = IconManager::getInstance()->iconSets->getAllEnabledIconSetsWithAllowedTypes();

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

        $templateVars = [
            'iconSet' => $iconSet,
            'isNew' => !$iconSet->id,
            'availableFolders' => $iconSet->getAvailableFolders(),
            'availableFonts' => \lindemannrock\iconmanager\iconsets\WebFont::getAvailableFonts(),
            'availableSprites' => \lindemannrock\iconmanager\iconsets\SvgSprite::getAvailableSprites(),
        ];

        // Add icons for preview tab
        if ($iconSet->id) {
            $templateVars['icons'] = IconManager::getInstance()->icons->getIconsBySetId($iconSet->id);
        }

        // Add optimization data for existing SVG folder icon sets
        if ($iconSet->id && in_array($iconSet->type, ['svg-folder', 'folder'])) {
            $templateVars['scanResult'] = IconManager::getInstance()->svgOptimizer->scanIconSet($iconSet);
            $templateVars['backups'] = IconManager::getInstance()->svgOptimizer->listBackups($iconSet->name);
        }

        return $this->renderTemplate('icon-manager/icon-sets/edit', $templateVars);
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

    /**
     * Optimize SVG files for an icon set (redirects to edit page optimization tab)
     */
    public function actionOptimize(int $iconSetId): Response
    {
        // Redirect to edit page with optimization tab
        // The standalone optimize page is deprecated - using integrated tab instead
        return $this->redirect('icon-manager/icon-sets/' . $iconSetId . '#optimization');
    }

    /**
     * Apply optimizations to SVG files
     */
    public function actionApplyOptimizations(): Response
    {
        $this->requirePostRequest();

        // Check if optimization is allowed in this environment
        if (!$this->isOptimizationAllowed()) {
            Craft::$app->getSession()->setError(
                Craft::t('icon-manager', 'SVG optimization is only available in local/development environments.')
            );
            return $this->redirectToPostedUrl();
        }

        $iconSetId = Craft::$app->getRequest()->getRequiredBodyParam('iconSetId');
        $iconSet = IconManager::getInstance()->iconSets->getIconSetById($iconSetId);

        if (!$iconSet) {
            throw new \yii\web\NotFoundHttpException('Icon set not found');
        }

        try {
            $result = IconManager::getInstance()->svgOptimizer->optimizeIconSet($iconSet);

            if ($result['success']) {
                Craft::$app->getSession()->setNotice(
                    Craft::t('icon-manager', 'Optimized {count} SVG files. Backup created at: {backup}', [
                        'count' => $result['filesOptimized'],
                        'backup' => $result['backupPath']
                    ])
                );
            } else {
                Craft::$app->getSession()->setError(
                    Craft::t('icon-manager', 'Optimization failed: {error}', ['error' => $result['error']])
                );
            }
        } catch (\Exception $e) {
            Craft::$app->getSession()->setError(
                Craft::t('icon-manager', 'Could not optimize SVGs: {error}', ['error' => $e->getMessage()])
            );
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * Check if SVG optimization is allowed in current environment
     */
    private function isOptimizationAllowed(): bool
    {
        // Allow if devMode is enabled
        if (Craft::$app->config->general->devMode) {
            return true;
        }

        // Check CRAFT_ENVIRONMENT or ENVIRONMENT variables
        $environment = getenv('CRAFT_ENVIRONMENT') ?: getenv('ENVIRONMENT');
        if ($environment) {
            $allowedEnvironments = ['local', 'dev', 'development'];
            return in_array(strtolower($environment), $allowedEnvironments);
        }

        // Default to not allowed
        return false;
    }

    /**
     * Restore icon set from backup
     */
    public function actionRestoreBackup(): Response
    {
        $this->requirePostRequest();

        if (!$this->isOptimizationAllowed()) {
            Craft::$app->getSession()->setError(
                Craft::t('icon-manager', 'Backup restoration is only available in local/development environments.')
            );
            return $this->redirectToPostedUrl();
        }

        $iconSetId = Craft::$app->getRequest()->getRequiredBodyParam('iconSetId');
        $backupPath = Craft::$app->getRequest()->getRequiredBodyParam('backupPath');

        $iconSet = IconManager::getInstance()->iconSets->getIconSetById($iconSetId);
        if (!$iconSet) {
            throw new \yii\web\NotFoundHttpException('Icon set not found');
        }

        try {
            $basePath = IconManager::getInstance()->getSettings()->iconSetsPath;
            $folder = $iconSet->settings['folder'] ?? '';
            $targetPath = Craft::getAlias($basePath . '/' . $folder);

            if (IconManager::getInstance()->svgOptimizer->restoreFromBackup($backupPath, $targetPath)) {
                Craft::$app->getSession()->setNotice(
                    Craft::t('icon-manager', 'Icon set restored from backup successfully.')
                );
            } else {
                Craft::$app->getSession()->setError(
                    Craft::t('icon-manager', 'Failed to restore from backup.')
                );
            }
        } catch (\Exception $e) {
            Craft::$app->getSession()->setError(
                Craft::t('icon-manager', 'Could not restore backup: {error}', ['error' => $e->getMessage()])
            );
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * Get SVG files for client-side optimization
     */
    public function actionGetSvgFiles(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $iconSetId = $request->getBodyParam('iconSetId');
        $iconSet = IconManager::getInstance()->iconSets->getIconSetById($iconSetId);

        if (!$iconSet) {
            return $this->asJson(['success' => false, 'error' => 'Icon set not found']);
        }

        $basePath = IconManager::getInstance()->getSettings()->iconSetsPath;
        $folder = $iconSet->settings['folder'] ?? '';
        $folderPath = Craft::getAlias($basePath . '/' . $folder);

        if (!is_dir($folderPath)) {
            return $this->asJson(['success' => false, 'error' => 'Folder not found']);
        }

        // Get all SVG files
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($folderPath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'svg') {
                $relativePath = str_replace($folderPath . '/', '', $file->getPathname());
                $content = file_get_contents($file->getPathname());

                $files[] = [
                    'path' => $file->getPathname(),
                    'relativePath' => $relativePath,
                    'content' => $content
                ];
            }
        }

        return $this->asJson(['success' => true, 'files' => $files]);
    }

    /**
     * Save optimized SVG files
     */
    public function actionSaveOptimizedSvgs(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        if (!$this->isOptimizationAllowed()) {
            return $this->asJson([
                'success' => false,
                'error' => 'SVG optimization is only available in local/development environments.'
            ]);
        }

        $request = Craft::$app->getRequest();
        $iconSetId = $request->getBodyParam('iconSetId');
        $files = $request->getBodyParam('files', []);

        $iconSet = IconManager::getInstance()->iconSets->getIconSetById($iconSetId);
        if (!$iconSet) {
            return $this->asJson(['success' => false, 'error' => 'Icon set not found']);
        }

        try {
            $basePath = IconManager::getInstance()->getSettings()->iconSetsPath;
            $folder = $iconSet->settings['folder'] ?? '';
            $folderPath = Craft::getAlias($basePath . '/' . $folder);

            // Create backup first
            $backupPath = IconManager::getInstance()->svgOptimizer->createBackupPublic($folderPath, $iconSet->name);
            if (!$backupPath) {
                return $this->asJson(['success' => false, 'error' => 'Failed to create backup']);
            }

            // Save optimized files
            $savedCount = 0;
            foreach ($files as $file) {
                if (isset($file['path']) && isset($file['content'])) {
                    if (file_put_contents($file['path'], $file['content']) !== false) {
                        $savedCount++;
                    }
                }
            }

            return $this->asJson([
                'success' => true,
                'filesSaved' => $savedCount,
                'backupPath' => $backupPath
            ]);

        } catch (\Exception $e) {
            $this->logError('Could not save optimized SVGs', ['error' => $e->getMessage()]);
            return $this->asJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Delete a backup
     */
    public function actionDeleteBackup(): Response
    {
        $this->requirePostRequest();

        if (!$this->isOptimizationAllowed()) {
            Craft::$app->getSession()->setError(
                Craft::t('icon-manager', 'Backup deletion is only available in local/development environments.')
            );
            return $this->redirectToPostedUrl();
        }

        $backupPath = Craft::$app->getRequest()->getRequiredBodyParam('backupPath');

        try {
            if (IconManager::getInstance()->svgOptimizer->deleteBackup($backupPath)) {
                Craft::$app->getSession()->setNotice(
                    Craft::t('icon-manager', 'Backup deleted successfully.')
                );
            } else {
                Craft::$app->getSession()->setError(
                    Craft::t('icon-manager', 'Failed to delete backup.')
                );
            }
        } catch (\Exception $e) {
            Craft::$app->getSession()->setError(
                Craft::t('icon-manager', 'Could not delete backup: {error}', ['error' => $e->getMessage()])
            );
        }

        return $this->redirectToPostedUrl();
    }
}