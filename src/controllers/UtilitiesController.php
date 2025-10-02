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
use lindemannrock\iconmanager\traits\LoggingTrait;
use yii\web\Response;

/**
 * Utilities Controller
 */
class UtilitiesController extends Controller
{
    use LoggingTrait;

    /**
     * Refresh all icon sets
     */
    public function actionRefreshAllIcons(): Response
    {
        $this->requirePostRequest();

        try {
            $iconSets = IconManager::getInstance()->iconSets->getAllIconSets();
            $refreshed = 0;
            $failed = 0;

            foreach ($iconSets as $iconSet) {
                try {
                    IconManager::getInstance()->icons->refreshIconsForSet($iconSet);
                    $refreshed++;
                } catch (\Exception $e) {
                    $this->logError("Failed to refresh icon set '{$iconSet->name}': " . $e->getMessage());
                    $failed++;
                }
            }

            if ($failed > 0) {
                Craft::$app->getSession()->setNotice(
                    Craft::t('icon-manager', 'Refreshed {refreshed} icon sets. {failed} failed.', [
                        'refreshed' => $refreshed,
                        'failed' => $failed
                    ])
                );
            } else {
                Craft::$app->getSession()->setNotice(
                    Craft::t('icon-manager', 'Successfully refreshed {count} icon sets.', [
                        'count' => $refreshed
                    ])
                );
            }
        } catch (\Exception $e) {
            $this->logError("Failed to refresh all icons: " . $e->getMessage());
            Craft::$app->getSession()->setError(
                Craft::t('icon-manager', 'Could not refresh icons: {error}', ['error' => $e->getMessage()])
            );
        }

        return $this->redirectToPostedUrl();
    }
}
