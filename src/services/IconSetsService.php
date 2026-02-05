<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\iconmanager\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\DateTimeHelper;

use craft\helpers\StringHelper;
use lindemannrock\iconmanager\IconManager;
use lindemannrock\iconmanager\models\IconSet;
use lindemannrock\iconmanager\records\IconSetRecord;
use lindemannrock\logginglibrary\traits\LoggingTrait;

/**
 * Icon Sets Service
 *
 * @since 1.0.0
 */
class IconSetsService extends Component
{
    use LoggingTrait;

    /**
     * @var IconSet[]|null
     */
    private ?array $_iconSetsById = null;

    /**
     * @var IconSet[]|null
     */
    private ?array $_iconSetsByHandle = null;

    /**
     * Clear icon sets cache
     *
     * @since 1.0.0
     */
    public function clearCache(): void
    {
        $this->_iconSetsById = null;
        $this->_iconSetsByHandle = null;
    }
    
    /**
     * Get all icon sets
     *
     * @return IconSet[]
     * @since 1.0.0
     */
    public function getAllIconSets(): array
    {
        if ($this->_iconSetsById !== null) {
            return array_values($this->_iconSetsById);
        }

        $results = $this->_createIconSetQuery()
            ->orderBy(['sortOrder' => SORT_ASC, 'name' => SORT_ASC])
            ->all();

        $this->_iconSetsById = [];
        $this->_iconSetsByHandle = [];

        foreach ($results as $result) {
            $iconSet = $this->_createIconSetFromRecord($result);
            $this->_iconSetsById[$iconSet->id] = $iconSet;
            $this->_iconSetsByHandle[$iconSet->handle] = $iconSet;
        }

        return array_values($this->_iconSetsById);
    }

    /**
     * Get all enabled icon sets (only icon sets where enabled=true)
     *
     * @return IconSet[]
     * @since 1.0.0
     */
    public function getAllEnabledIconSets(): array
    {
        return array_filter($this->getAllIconSets(), fn($iconSet) => $iconSet->enabled);
    }

    /**
     * Get all enabled icon sets with allowed types
     * (filters by both enabled flag AND whether the icon type is enabled in settings)
     *
     * @return IconSet[]
     * @since 1.10.0
     */
    public function getAllEnabledIconSetsWithAllowedTypes(): array
    {
        $settings = IconManager::getInstance()->getSettings();
        $enabledTypes = $settings->enabledIconTypes ?? [];

        return array_filter($this->getAllEnabledIconSets(), function($iconSet) use ($enabledTypes) {
            // Check if this icon set's type is enabled in settings
            return ($enabledTypes[$iconSet->type] ?? false) === true;
        });
    }

    /**
     * Get an icon set by ID
     *
     * @param int $id
     * @param bool $fresh
     * @return IconSet|null
     * @since 1.0.0
     */
    public function getIconSetById(int $id, bool $fresh = false): ?IconSet
    {
        if ($fresh) {
            $this->clearCache();
        }
        
        if ($this->_iconSetsById === null) {
            $this->getAllIconSets();
        }

        return $this->_iconSetsById[$id] ?? null;
    }

    /**
     * Get an icon set by handle
     *
     * @param string $handle
     * @return IconSet|null
     * @since 1.0.0
     */
    public function getIconSetByHandle(string $handle): ?IconSet
    {
        if ($this->_iconSetsByHandle === null) {
            $this->getAllIconSets();
        }

        return $this->_iconSetsByHandle[$handle] ?? null;
    }

    /**
     * Get multiple icon sets by their handles
     *
     * @param string[] $handles
     * @return IconSet[]
     * @since 1.0.0
     */
    public function getIconSetsByHandles(array $handles): array
    {
        $iconSets = [];
        
        foreach ($handles as $handle) {
            $iconSet = $this->getIconSetByHandle($handle);
            if ($iconSet) {
                $iconSets[] = $iconSet;
            }
        }

        return $iconSets;
    }

    /**
     * Save an icon set
     *
     * @param IconSet $iconSet
     * @param bool $runValidation
     * @return bool
     * @since 1.0.0
     */
    public function saveIconSet(IconSet $iconSet, bool $runValidation = true): bool
    {
        $isNew = !$iconSet->id;

        if ($runValidation && !$iconSet->validate()) {
            // Format errors for readable message
            $errorMessages = [];
            foreach ($iconSet->getErrors() as $field => $fieldErrors) {
                $errorMessages[] = $field . ': ' . implode(', ', $fieldErrors);
            }
            $errorString = implode('; ', $errorMessages);

            $this->logWarning("Icon set validation failed", [
                'errorString' => $errorString,
                'errors' => $iconSet->getErrors(),
                'iconSetId' => $iconSet->id,
            ]);
            return false;
        }

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            // Get the record
            if ($iconSet->id) {
                $record = IconSetRecord::findOne($iconSet->id);
                if (!$record) {
                    throw new \Exception('Invalid icon set ID: ' . $iconSet->id);
                }
            } else {
                $record = new IconSetRecord();
                $iconSet->uid = StringHelper::UUID();
            }

            // Set attributes
            $record->name = $iconSet->name;
            $record->handle = $iconSet->handle;
            $record->type = $iconSet->type;
            $record->settings = json_encode($iconSet->settings);
            $record->enabled = $iconSet->enabled;
            $record->sortOrder = $iconSet->sortOrder ?? 0;
            $record->uid = $iconSet->uid;

            // Save record
            if (!$record->save()) {
                throw new \Exception('Could not save icon set record.');
            }

            if ($isNew) {
                $iconSet->id = $record->id;
            }

            // Clear caches
            $this->_iconSetsById = null;
            $this->_iconSetsByHandle = null;

            // Update icon cache for this set
            IconManager::getInstance()->icons->refreshIconsForSet($iconSet);

            $transaction->commit();

            // Log successful operation
            $action = $isNew ? 'created' : 'updated';
            $this->logInfo("Icon set operation successful", [
                'action' => $action,
                'iconSetId' => $iconSet->id,
                'name' => $iconSet->name,
                'type' => $iconSet->type,
            ]);
        } catch (\Throwable $e) {
            $transaction->rollBack();
            $this->logError("Failed to save icon set", [
                'error' => $e->getMessage(),
                'iconSetId' => $iconSet->id ?? 'new',
                'name' => $iconSet->name ?? 'unknown',
            ]);
            throw $e;
        }

        return true;
    }

    /**
     * Delete an icon set
     *
     * @param IconSet $iconSet
     * @return bool
     * @since 1.0.0
     */
    public function deleteIconSet(IconSet $iconSet): bool
    {
        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            // Delete all icons for this set (handled by foreign key cascade)
            
            // Delete the icon set record
            $db->createCommand()
                ->delete('{{%iconmanager_iconsets}}', ['id' => $iconSet->id])
                ->execute();

            // Clear caches
            $this->_iconSetsById = null;
            $this->_iconSetsByHandle = null;

            $transaction->commit();

            // Log successful deletion
            $this->logInfo("Icon set deleted successfully", [
                'iconSetId' => $iconSet->id,
                'name' => $iconSet->name,
                'type' => $iconSet->type,
            ]);
        } catch (\Throwable $e) {
            $transaction->rollBack();
            $this->logError("Failed to delete icon set", [
                'error' => $e->getMessage(),
                'iconSetId' => $iconSet->id,
                'name' => $iconSet->name,
            ]);
            throw $e;
        }

        return true;
    }

    /**
     * Reorder icon sets
     *
     * @param array $iconSetIds
     * @return bool
     * @since 1.0.0
     */
    public function reorderIconSets(array $iconSetIds): bool
    {
        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            foreach ($iconSetIds as $order => $id) {
                $db->createCommand()
                    ->update('{{%iconmanager_iconsets}}',
                        ['sortOrder' => $order + 1],
                        ['id' => $id]
                    )
                    ->execute();
            }

            // Clear caches
            $this->_iconSetsById = null;
            $this->_iconSetsByHandle = null;

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * Create icon set query
     */
    private function _createIconSetQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'name',
                'handle',
                'type',
                'settings',
                'enabled',
                'sortOrder',
                'dateCreated',
                'dateUpdated',
                'uid',
            ])
            ->from(['{{%iconmanager_iconsets}}']);
    }

    /**
     * Create icon set model from record
     */
    private function _createIconSetFromRecord($record): IconSet
    {
        $iconSet = new IconSet();
        $iconSet->id = $record['id'];
        $iconSet->name = $record['name'];
        $iconSet->handle = $record['handle'];
        $iconSet->type = $record['type'];
        $iconSet->settings = json_decode($record['settings'], true) ?: [];
        $iconSet->enabled = (bool)$record['enabled'];
        $iconSet->sortOrder = $record['sortOrder'];
        $iconSet->dateCreated = DateTimeHelper::toDateTime($record['dateCreated']);
        $iconSet->dateUpdated = DateTimeHelper::toDateTime($record['dateUpdated']);
        $iconSet->uid = $record['uid'];

        return $iconSet;
    }
}
