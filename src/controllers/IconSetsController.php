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

use lindemannrock\iconmanager\models\IconSet;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Icon Sets Controller
 *
 * @since 1.0.0
 */
class IconSetsController extends Controller
{
    use LoggingTrait;

    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        $user = Craft::$app->getUser();

        // Check permissions based on action
        switch ($action->id) {
            case 'index':
                // View icon sets list requires view permission (or any related permission for implicit access)
                $hasViewAccess = $user->checkPermission('iconManager:viewIconSets') ||
                    $user->checkPermission('iconManager:createIconSets') ||
                    $user->checkPermission('iconManager:editIconSets') ||
                    $user->checkPermission('iconManager:deleteIconSets') ||
                    $user->checkPermission('iconManager:manageOptimization');
                if (!$hasViewAccess) {
                    throw new ForbiddenHttpException('User does not have permission to view icon sets');
                }
                break;

            case 'edit':
                // Edit page requires create (for new) or edit (for existing)
                $iconSetId = Craft::$app->getRequest()->getParam('iconSetId');
                if ($iconSetId) {
                    // Viewing/editing existing icon set
                    if (!$user->checkPermission('iconManager:editIconSets')) {
                        throw new ForbiddenHttpException('User does not have permission to edit icon sets');
                    }
                } else {
                    // Creating new icon set
                    if (!$user->checkPermission('iconManager:createIconSets')) {
                        throw new ForbiddenHttpException('User does not have permission to create icon sets');
                    }
                }
                break;

            case 'save':
                // Save requires create (for new) or edit (for existing)
                $iconSetId = Craft::$app->getRequest()->getBodyParam('iconSetId');
                if ($iconSetId) {
                    // Editing existing
                    if (!$user->checkPermission('iconManager:editIconSets')) {
                        throw new ForbiddenHttpException('User does not have permission to edit icon sets');
                    }
                } else {
                    // Creating new
                    if (!$user->checkPermission('iconManager:createIconSets')) {
                        throw new ForbiddenHttpException('User does not have permission to create icon sets');
                    }
                }
                break;

            case 'delete':
            case 'bulk-delete':
                if (!$user->checkPermission('iconManager:deleteIconSets')) {
                    throw new ForbiddenHttpException('User does not have permission to delete icon sets');
                }
                break;

            case 'bulk-enable':
            case 'bulk-disable':
            case 'refresh-icons':
                if (!$user->checkPermission('iconManager:editIconSets')) {
                    throw new ForbiddenHttpException('User does not have permission to edit icon sets');
                }
                break;

            case 'optimize':
            case 'apply-optimizations':
            case 'restore-backup':
            case 'get-svg-files':
            case 'save-optimized-svgs':
            case 'delete-backup':
                if (!$user->checkPermission('iconManager:manageOptimization')) {
                    throw new ForbiddenHttpException('User does not have permission to manage SVG optimization');
                }
                break;
        }

        return parent::beforeAction($action);
    }
    /**
     * Icon sets index
     */
    public function actionIndex(): Response
    {
        $request = Craft::$app->getRequest();
        $settings = IconManager::getInstance()->getSettings();

        // Get query parameters
        $search = $request->getQueryParam('search', '');
        $statusFilter = $request->getQueryParam('status', 'all');
        $typeFilter = $request->getQueryParam('type', 'all');
        $sort = $request->getQueryParam('sort', 'name');
        $dir = $request->getQueryParam('dir', 'asc');
        $page = max(1, (int)$request->getQueryParam('page', 1));
        $limit = $settings->itemsPerPage ?? 100;

        // Get all icon sets whose types are enabled in settings
        $iconSets = IconManager::getInstance()->iconSets->getAllIconSets();
        $enabledTypes = $settings->enabledIconTypes ?? [];

        // Filter by allowed types (from settings)
        $iconSets = array_filter($iconSets, function($iconSet) use ($enabledTypes) {
            return ($enabledTypes[$iconSet->type] ?? false) === true;
        });

        // Apply status filter
        if ($statusFilter === 'enabled') {
            $iconSets = array_filter($iconSets, function($iconSet) {
                return $iconSet->enabled;
            });
        } elseif ($statusFilter === 'disabled') {
            $iconSets = array_filter($iconSets, function($iconSet) {
                return !$iconSet->enabled;
            });
        }

        // Apply type filter
        if ($typeFilter !== 'all') {
            $iconSets = array_filter($iconSets, function($iconSet) use ($typeFilter) {
                return $iconSet->type === $typeFilter;
            });
        }

        // Apply search filter
        if ($search !== '') {
            $searchLower = strtolower($search);
            $iconSets = array_filter($iconSets, function($iconSet) use ($searchLower) {
                return
                    stripos($iconSet->name, $searchLower) !== false ||
                    stripos($iconSet->handle, $searchLower) !== false;
            });
        }

        // Apply sorting
        usort($iconSets, function($a, $b) use ($sort, $dir) {
            $aValue = null;
            $bValue = null;

            switch ($sort) {
                case 'name':
                    $aValue = strtolower($a->name);
                    $bValue = strtolower($b->name);
                    break;
                case 'type':
                    $aValue = strtolower($a->type);
                    $bValue = strtolower($b->type);
                    break;
                case 'iconCount':
                    $aValue = $a->getIconCount();
                    $bValue = $b->getIconCount();
                    break;
                case 'optimizationIssueCount':
                    $aValue = $a->getOptimizationIssueCount();
                    $bValue = $b->getOptimizationIssueCount();
                    break;
                default:
                    $aValue = strtolower($a->name);
                    $bValue = strtolower($b->name);
            }

            if ($aValue === $bValue) {
                return 0;
            }

            $result = $aValue < $bValue ? -1 : 1;
            return $dir === 'desc' ? -$result : $result;
        });

        // Get total count
        $totalCount = count($iconSets);
        $totalPages = $totalCount > 0 ? (int)ceil($totalCount / $limit) : 1;

        // Apply pagination
        $offset = ($page - 1) * $limit;
        $iconSets = array_slice($iconSets, $offset, $limit);

        return $this->renderTemplate('icon-manager/icon-sets/index', [
            'iconSets' => $iconSets,
            'totalCount' => $totalCount,
            'totalPages' => $totalPages,
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset,
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
        $iconSet->handle = $this->_generateUniqueHandle($request->getBodyParam('handle'), $iconSetId);
        $iconSet->type = $request->getBodyParam('type');
        $iconSet->enabled = (bool)$request->getBodyParam('enabled');
        
        // Get settings and ensure proper types
        $settings = $request->getBodyParam('settings', []);

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
     * Bulk enable icon sets
     */
    public function actionBulkEnable(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $iconSetIds = Craft::$app->getRequest()->getRequiredBodyParam('iconSetIds');
        $enabled = 0;

        foreach ($iconSetIds as $iconSetId) {
            $iconSet = IconManager::getInstance()->iconSets->getIconSetById($iconSetId);
            if ($iconSet) {
                $iconSet->enabled = true;
                if (IconManager::getInstance()->iconSets->saveIconSet($iconSet)) {
                    $enabled++;
                }
            }
        }

        return $this->asJson([
            'success' => true,
            'enabled' => $enabled,
        ]);
    }

    /**
     * Bulk disable icon sets
     */
    public function actionBulkDisable(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $iconSetIds = Craft::$app->getRequest()->getRequiredBodyParam('iconSetIds');
        $disabled = 0;

        foreach ($iconSetIds as $iconSetId) {
            $iconSet = IconManager::getInstance()->iconSets->getIconSetById($iconSetId);
            if ($iconSet) {
                $iconSet->enabled = false;
                if (IconManager::getInstance()->iconSets->saveIconSet($iconSet)) {
                    $disabled++;
                }
            }
        }

        return $this->asJson([
            'success' => true,
            'disabled' => $disabled,
        ]);
    }

    /**
     * Bulk delete icon sets
     */
    public function actionBulkDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $iconSetIds = Craft::$app->getRequest()->getRequiredBodyParam('iconSetIds');
        $deleted = 0;

        foreach ($iconSetIds as $iconSetId) {
            $iconSet = IconManager::getInstance()->iconSets->getIconSetById($iconSetId);
            if ($iconSet && IconManager::getInstance()->iconSets->deleteIconSet($iconSet)) {
                $deleted++;
            }
        }

        return $this->asJson([
            'success' => true,
            'deleted' => $deleted,
        ]);
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

        // Check if optimization is enabled in settings
        if (!IconManager::getInstance()->getSettings()->enableOptimization) {
            Craft::$app->getSession()->setError(
                Craft::t('icon-manager', 'SVG optimization is disabled in plugin settings.')
            );
            return $this->redirectToPostedUrl();
        }

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
                if ($result['filesOptimized'] > 0) {
                    $message = Craft::t('icon-manager', 'Optimized {count} SVG file(s).', [
                        'count' => $result['filesOptimized'],
                    ]);
                    if ($result['backupPath']) {
                        $message .= ' ' . Craft::t('icon-manager', 'Backup created at: {backup}', [
                            'backup' => $result['backupPath'],
                        ]);
                    }
                } else {
                    $message = Craft::t('icon-manager', 'No files needed optimization. All files are already optimized.');
                }

                Craft::$app->getSession()->setNotice($message);
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
                    'content' => $content,
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
                'error' => 'SVG optimization is only available in local/development environments.',
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
            $includeSubfolders = $iconSet->settings['includeSubfolders'] ?? false;

            // Create backup first
            $backupPath = IconManager::getInstance()->svgOptimizer->createBackupPublic($folderPath, $iconSet->name, $includeSubfolders);
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
                'backupPath' => $backupPath,
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

    /**
     * Generate a unique handle by appending a number if needed
     *
     * @param string $handle The desired handle
     * @param int|null $currentIconSetId The current icon set ID (to exclude from duplicate check)
     * @return string A unique handle
     */
    private function _generateUniqueHandle(string $handle, ?int $currentIconSetId = null): string
    {
        // Check if handle already exists
        $query = (new \craft\db\Query())
            ->from('{{%iconmanager_iconsets}}')
            ->where(['handle' => $handle]);

        // Exclude current icon set if editing
        if ($currentIconSetId) {
            $query->andWhere(['not', ['id' => $currentIconSetId]]);
        }

        // If handle is unique, return as-is
        if (!$query->exists()) {
            return $handle;
        }

        // Handle is taken, generate unique one by appending number
        $baseHandle = $handle;
        $i = 1;

        // Keep incrementing until we find a unique handle
        while (true) {
            $newHandle = $baseHandle . $i;

            $query = (new \craft\db\Query())
                ->from('{{%iconmanager_iconsets}}')
                ->where(['handle' => $newHandle]);

            if ($currentIconSetId) {
                $query->andWhere(['not', ['id' => $currentIconSetId]]);
            }

            if (!$query->exists()) {
                return $newHandle;
            }

            $i++;
        }
    }
}
