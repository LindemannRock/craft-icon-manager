<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\iconmanager\controllers;

use Craft;
use craft\web\Controller;
use lindemannrock\iconmanager\IconManager;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use yii\web\ForbiddenHttpException;

/**
 * Cache Controller
 *
 * @since 1.0.0
 */
class CacheController extends Controller
{
    use LoggingTrait;

    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        $user = Craft::$app->getUser();

        if (!$user->checkPermission('iconManager:clearCache')) {
            throw new ForbiddenHttpException(Craft::t('icon-manager', 'User does not have permission to clear icon cache.'));
        }

        return parent::beforeAction($action);
    }

    /**
     * Clear all icon caches
     *
     * @return mixed
     */
    public function actionClear(): mixed
    {
        $this->requirePostRequest();
        
        try {
            $this->logInfo("Starting icon cache clearing operation");

            $cacheStats = IconManager::getInstance()->iconCache->countFileCaches() ?? [];
            $totalCleared = array_sum($cacheStats);
            if (!IconManager::getInstance()->iconCache->invalidateFamily()) {
                throw new \RuntimeException('Icon cache invalidation failed.');
            }

            IconManager::getInstance()->icons->clearMemoryCache();

            $this->logInfo("Icon cache cleared successfully", [
                'totalCaches' => $totalCleared,
                'cacheStats' => $cacheStats,
            ]);

            Craft::$app->getSession()->setNotice(
                Craft::t('icon-manager', 'Icon cache cleared.')
            );

            return $this->redirectToPostedUrl();
        } catch (\Throwable $e) {
            $this->logError("Failed to clear icon cache", ['error' => $e->getMessage()]);
            Craft::$app->getSession()->setError(
                Craft::t('icon-manager', 'Failed to clear icon cache.')
            );

            return $this->redirectToPostedUrl();
        }
    }
}
