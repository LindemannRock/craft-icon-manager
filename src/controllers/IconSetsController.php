<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\iconmanager\controllers;

use Craft;
use craft\helpers\App;
use craft\helpers\FileHelper;
use craft\web\Controller;
use lindemannrock\base\helpers\CpNavHelper;
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
                // View icon sets list requires manageIconSets permission
                if (!$user->checkPermission('iconManager:manageIconSets')) {
                    // Redirect to first accessible section
                    $settings = IconManager::getInstance()->getSettings();
                    $sections = IconManager::getInstance()->getCpSections($settings, false, true);
                    $route = CpNavHelper::firstAccessibleRoute($user, $settings, $sections);
                    if ($route) {
                        Craft::$app->getResponse()->redirect($route)->send();
                        return false;
                    }
                    // No access at all
                    throw new ForbiddenHttpException(Craft::t('icon-manager', 'User does not have permission to access Icon Manager.'));
                }
                break;

            case 'edit':
                // Edit page requires create (for new) or edit (for existing)
                $iconSetId = Craft::$app->getRequest()->getParam('iconSetId');
                if ($iconSetId) {
                    // Viewing/editing existing icon set
                    if (!$user->checkPermission('iconManager:editIconSets')) {
                        throw new ForbiddenHttpException(Craft::t('icon-manager', 'User does not have permission to edit icon sets.'));
                    }
                } else {
                    // Creating new icon set
                    if (!$user->checkPermission('iconManager:createIconSets')) {
                        throw new ForbiddenHttpException(Craft::t('icon-manager', 'User does not have permission to create icon sets.'));
                    }
                }
                break;

            case 'save':
                // Save requires create (for new) or edit (for existing)
                $iconSetId = Craft::$app->getRequest()->getBodyParam('iconSetId');
                if ($iconSetId) {
                    // Editing existing
                    if (!$user->checkPermission('iconManager:editIconSets')) {
                        throw new ForbiddenHttpException(Craft::t('icon-manager', 'User does not have permission to edit icon sets.'));
                    }
                } else {
                    // Creating new
                    if (!$user->checkPermission('iconManager:createIconSets')) {
                        throw new ForbiddenHttpException(Craft::t('icon-manager', 'User does not have permission to create icon sets.'));
                    }
                }
                break;

            case 'delete':
            case 'bulk-delete':
                if (!$user->checkPermission('iconManager:deleteIconSets')) {
                    throw new ForbiddenHttpException(Craft::t('icon-manager', 'User does not have permission to delete icon sets.'));
                }
                break;

            case 'bulk-enable':
            case 'bulk-disable':
            case 'refresh-icons':
                if (!$user->checkPermission('iconManager:editIconSets')) {
                    throw new ForbiddenHttpException(Craft::t('icon-manager', 'User does not have permission to edit icon sets.'));
                }
                break;

            case 'optimize':
            case 'apply-optimizations':
            case 'restore-backup':
            case 'get-svg-files':
            case 'save-optimized-svgs':
            case 'delete-backup':
                if (!$user->checkPermission('iconManager:manageOptimization')) {
                    throw new ForbiddenHttpException(Craft::t('icon-manager', 'User does not have permission to manage SVG optimization.'));
                }
                break;
        }

        return parent::beforeAction($action);
    }
    /**
     * Icon sets index
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        $request = Craft::$app->getRequest();
        $settings = IconManager::getInstance()->getSettings();
        $user = Craft::$app->getUser();

        // ---- Param parsing + allowlist validation -------------------------
        // Every parameter that controls filtering or sorting goes through an
        // explicit allowlist. Off-list values snap back to the default.

        $statusFilter = (string) $request->getQueryParam('status', 'all');
        $validStatuses = ['all', 'enabled', 'disabled'];
        if (!in_array($statusFilter, $validStatuses, true)) {
            $statusFilter = 'all';
        }

        $typeFilter = (string) $request->getQueryParam('type', 'all');
        $validTypes = ['all', 'svg-folder', 'svg-sprite', 'font-awesome', 'material-icons', 'web-font'];
        if (!in_array($typeFilter, $validTypes, true)) {
            $typeFilter = 'all';
        }

        // 64-char defensive clamp on free-text search. Keeps a runaway payload
        // (URL of any length) from reaching the filter loop.
        $search = trim((string) $request->getQueryParam('search', ''));
        if (mb_strlen($search) > 64) {
            $search = mb_substr($search, 0, 64);
        }

        $validSortFields = ['name', 'type', 'iconCount', 'optimizationIssueCount', 'enabled'];
        $sort = (string) $request->getQueryParam('sort', 'name');
        if (!in_array($sort, $validSortFields, true)) {
            $sort = 'name';
        }
        $dir = strtolower((string) $request->getQueryParam('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $page = max(1, (int) $request->getQueryParam('page', 1));
        $limit = max(1, (int) $settings->itemsPerPage);

        // ---- Load + filter ------------------------------------------------
        $iconSets = IconManager::getInstance()->iconSets->getAllIconSets();
        $enabledTypes = $settings->enabledIconTypes ?? [];

        // Filter by allowed types (from settings)
        $iconSets = array_values(array_filter($iconSets, fn($iconSet): bool =>
            ($enabledTypes[$iconSet->type] ?? false) === true
        ));

        if ($statusFilter === 'enabled') {
            $iconSets = array_values(array_filter($iconSets, fn($iconSet): bool => $iconSet->enabled));
        } elseif ($statusFilter === 'disabled') {
            $iconSets = array_values(array_filter($iconSets, fn($iconSet): bool => !$iconSet->enabled));
        }

        if ($typeFilter !== 'all') {
            $iconSets = array_values(array_filter($iconSets, fn($iconSet): bool => $iconSet->type === $typeFilter));
        }

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $iconSets = array_values(array_filter($iconSets, fn($iconSet): bool =>
                stripos((string) $iconSet->name, $needle) !== false ||
                stripos((string) $iconSet->handle, $needle) !== false
            ));
        }

        // ---- Sort + paginate ----------------------------------------------
        $iconSets = $this->sortIconSets($iconSets, $sort, $dir);

        // totalCount is computed *after* filtering so the pager reflects what
        // the user can actually see, not the underlying set size.
        $totalCount = count($iconSets);
        $offset = ($page - 1) * $limit;
        $iconSets = array_slice($iconSets, $offset, $limit);

        return $this->renderTemplate('icon-manager/icon-sets/index', [
            'iconSets' => $iconSets,
            'statusFilter' => $statusFilter,
            'typeFilter' => $typeFilter,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir,
            'page' => $page,
            'limit' => $limit,
            'totalCount' => $totalCount,
            'canCreate' => $user->checkPermission('iconManager:createIconSets'),
            'canEdit' => $user->checkPermission('iconManager:editIconSets'),
            'canDelete' => $user->checkPermission('iconManager:deleteIconSets'),
        ]);
    }

    /**
     * @param IconSet[] $iconSets
     * @return IconSet[]
     */
    private function sortIconSets(array $iconSets, string $sort, string $dir): array
    {
        // Pre-compute expensive sort values once per icon set. usort can call
        // the comparator O(N log N) times — without this, getIconCount() runs
        // a DB lookup and getOptimizationIssueCount() runs a full disk scan
        // every single comparison.
        $sortValues = [];
        if (in_array($sort, ['iconCount', 'optimizationIssueCount'], true)) {
            foreach ($iconSets as $iconSet) {
                $sortValues[$iconSet->id] = $sort === 'iconCount'
                    ? $iconSet->getIconCount()
                    : $iconSet->getOptimizationIssueCount();
            }
        }

        $multiplier = $dir === 'desc' ? -1 : 1;

        usort($iconSets, function(IconSet $a, IconSet $b) use ($sort, $multiplier, $sortValues): int {
            $cmp = match ($sort) {
                'type' => strcasecmp((string) $a->type, (string) $b->type),
                'iconCount', 'optimizationIssueCount' => $sortValues[$a->id] <=> $sortValues[$b->id],
                'enabled' => ((int) $a->enabled) <=> ((int) $b->enabled),
                default => strcasecmp((string) $a->name, (string) $b->name),
            };

            // Stable tie-break by name so equal primary keys don't shuffle
            // between requests — keeps pagination predictable.
            if ($cmp === 0 && $sort !== 'name') {
                $cmp = strcasecmp((string) $a->name, (string) $b->name);
            }

            return $cmp * $multiplier;
        });

        return $iconSets;
    }

    /**
     * Edit an icon set
     *
     * @param int|null $iconSetId
     * @param IconSet|null $iconSet
     * @return Response
     */
    public function actionEdit(?int $iconSetId = null, ?IconSet $iconSet = null): Response
    {
        // If we have an icon set passed (from save redirect), use it
        if ($iconSet === null) {
            if ($iconSetId) {
                // Force fresh load
                $iconSet = IconManager::getInstance()->iconSets->getIconSetById($iconSetId, true);
                if (!$iconSet) {
                    throw new \yii\web\NotFoundHttpException(Craft::t('icon-manager', 'Icon set not found'));
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
     *
     * @return Response|null
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $iconSetId = $request->getBodyParam('iconSetId');

        if ($iconSetId) {
            $iconSet = IconManager::getInstance()->iconSets->getIconSetById($iconSetId);
            if (!$iconSet) {
                throw new \yii\web\NotFoundHttpException(Craft::t('icon-manager', 'Icon set not found'));
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

        return $this->redirectToPostedUrl($iconSet);
    }

    /**
     * Delete an icon set
     *
     * @return Response
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();

        $iconSetId = Craft::$app->getRequest()->getRequiredBodyParam('iconSetId');
        $iconSet = IconManager::getInstance()->iconSets->getIconSetById($iconSetId);

        if (!$iconSet) {
            throw new \yii\web\NotFoundHttpException(Craft::t('icon-manager', 'Icon set not found'));
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
     *
     * @return Response
     * @since 5.10.0
     */
    public function actionBulkEnable(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $iconSetIds = Craft::$app->getRequest()->getRequiredBodyParam('iconSetIds');
        $count = 0;

        foreach ($iconSetIds as $iconSetId) {
            $iconSet = IconManager::getInstance()->iconSets->getIconSetById($iconSetId);
            if ($iconSet) {
                $iconSet->enabled = true;
                if (IconManager::getInstance()->iconSets->saveIconSet($iconSet)) {
                    $count++;
                }
            }
        }

        return $this->asJson([
            'success' => true,
            'count' => $count,
        ]);
    }

    /**
     * Bulk disable icon sets
     *
     * @return Response
     * @since 5.10.0
     */
    public function actionBulkDisable(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $iconSetIds = Craft::$app->getRequest()->getRequiredBodyParam('iconSetIds');
        $count = 0;

        foreach ($iconSetIds as $iconSetId) {
            $iconSet = IconManager::getInstance()->iconSets->getIconSetById($iconSetId);
            if ($iconSet) {
                $iconSet->enabled = false;
                if (IconManager::getInstance()->iconSets->saveIconSet($iconSet)) {
                    $count++;
                }
            }
        }

        return $this->asJson([
            'success' => true,
            'count' => $count,
        ]);
    }

    /**
     * Bulk delete icon sets
     *
     * @return Response
     * @since 5.10.0
     */
    public function actionBulkDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $iconSetIds = Craft::$app->getRequest()->getRequiredBodyParam('iconSetIds');
        $count = 0;

        foreach ($iconSetIds as $iconSetId) {
            $iconSet = IconManager::getInstance()->iconSets->getIconSetById($iconSetId);
            if ($iconSet && IconManager::getInstance()->iconSets->deleteIconSet($iconSet)) {
                $count++;
            }
        }

        return $this->asJson([
            'success' => true,
            'count' => $count,
        ]);
    }

    /**
     * Refresh icons for an icon set
     *
     * @return Response
     * @since 1.9.0
     */
    public function actionRefreshIcons(): Response
    {
        $this->requirePostRequest();

        $iconSetId = Craft::$app->getRequest()->getRequiredBodyParam('iconSetId');
        $iconSet = IconManager::getInstance()->iconSets->getIconSetById($iconSetId);

        if (!$iconSet) {
            throw new \yii\web\NotFoundHttpException(Craft::t('icon-manager', 'Icon set not found'));
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
     *
     * @param int $iconSetId
     * @return Response
     * @since 1.14.0
     */
    public function actionOptimize(int $iconSetId): Response
    {
        // Redirect to edit page with optimization tab
        // The standalone optimize page is deprecated - using integrated tab instead
        return $this->redirect('icon-manager/icon-sets/' . $iconSetId . '#optimization');
    }

    /**
     * Apply optimizations to SVG files
     *
     * @return Response
     * @since 1.14.0
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
            throw new \yii\web\NotFoundHttpException(Craft::t('icon-manager', 'Icon set not found'));
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
        $environment = App::env('CRAFT_ENVIRONMENT') ?: App::env('ENVIRONMENT');
        if ($environment) {
            $allowedEnvironments = ['local', 'dev', 'development'];
            return in_array(strtolower($environment), $allowedEnvironments);
        }

        // Default to not allowed
        return false;
    }

    /**
     * Restore icon set from backup
     *
     * @return Response
     * @since 5.3.0
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
            throw new \yii\web\NotFoundHttpException(Craft::t('icon-manager', 'Icon set not found'));
        }

        try {
            $basePath = FileHelper::normalizePath(IconManager::getInstance()->getSettings()->getResolvedIconSetsPath());
            $folder = $iconSet->settings['folder'] ?? '';
            $targetPath = FileHelper::normalizePath($basePath . DIRECTORY_SEPARATOR . $folder);

            // Containment guard — the iconSet `folder` is admin-controlled and the restore
            // path determines where the backup contents land. A traversal here lets a
            // dev-environment admin write backup contents to arbitrary directories.
            if (!str_starts_with($targetPath . DIRECTORY_SEPARATOR, $basePath . DIRECTORY_SEPARATOR)) {
                Craft::$app->getSession()->setError(
                    Craft::t('icon-manager', 'Failed to restore from backup.')
                );
                return $this->redirectToPostedUrl();
            }

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
     *
     * @return Response
     * @since 1.14.0
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

        $basePath = FileHelper::normalizePath(IconManager::getInstance()->getSettings()->getResolvedIconSetsPath());
        $folder = $iconSet->settings['folder'] ?? '';
        $folderPath = FileHelper::normalizePath($basePath . DIRECTORY_SEPARATOR . $folder);

        // Containment guard — admin-supplied `folder` could escape the icons base
        // and dump arbitrary SVG file contents into the JSON response.
        if (!str_starts_with($folderPath . DIRECTORY_SEPARATOR, $basePath . DIRECTORY_SEPARATOR)) {
            return $this->asJson(['success' => false, 'error' => 'Folder not found']);
        }

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
     *
     * @return Response
     * @since 1.14.0
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
            $basePath = FileHelper::normalizePath(IconManager::getInstance()->getSettings()->getResolvedIconSetsPath());
            $folder = $iconSet->settings['folder'] ?? '';
            $folderPath = FileHelper::normalizePath($basePath . DIRECTORY_SEPARATOR . $folder);
            $includeSubfolders = $iconSet->settings['includeSubfolders'] ?? false;

            // The icon-set folder is admin-controlled. It must stay inside
            // the configured icons base before backup creation or writes.
            if (!str_starts_with($folderPath . DIRECTORY_SEPARATOR, $basePath . DIRECTORY_SEPARATOR)) {
                return $this->asJson(['success' => false, 'error' => 'Folder not found']);
            }

            // Create backup first
            $backupPath = IconManager::getInstance()->svgOptimizer->createBackupPublic($folderPath, $iconSet->name, $includeSubfolders);
            if (!$backupPath) {
                return $this->asJson(['success' => false, 'error' => 'Failed to create backup']);
            }

            // Save optimized files — every `file['path']` is admin-supplied via
            // POST, so each one must be confined to the icon set's folder and
            // must be an .svg. Without this guard `file_put_contents` would
            // honor a crafted `../../../something` and overwrite arbitrary
            // files within the web server's permissions.
            $savedCount = 0;
            $normalizedFolderPath = FileHelper::normalizePath($folderPath);
            foreach ($files as $file) {
                if (!isset($file['path']) || !isset($file['content'])) {
                    continue;
                }

                $resolvedPath = FileHelper::normalizePath($file['path']);
                if (!str_starts_with($resolvedPath . DIRECTORY_SEPARATOR, $normalizedFolderPath . DIRECTORY_SEPARATOR)) {
                    $this->logWarning('Rejected optimized SVG write: path escapes icon set folder', [
                        'iconSetId' => $iconSetId,
                        'requestedPath' => $file['path'],
                        'folderPath' => $normalizedFolderPath,
                    ]);
                    continue;
                }

                if (strtolower(pathinfo($resolvedPath, PATHINFO_EXTENSION)) !== 'svg') {
                    $this->logWarning('Rejected optimized SVG write: not a .svg path', [
                        'iconSetId' => $iconSetId,
                        'requestedPath' => $file['path'],
                    ]);
                    continue;
                }

                if (file_put_contents($resolvedPath, $file['content']) !== false) {
                    $savedCount++;
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
     *
     * @return Response
     * @since 5.3.0
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
