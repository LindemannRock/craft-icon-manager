<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\iconmanager\migrations;

use craft\db\Migration;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\helpers\StringHelper;

/**
 * Install migration.
 *
 * @since 1.0.0
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->createTables();
        $this->createIndexes();
        $this->addForeignKeys();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropForeignKeys();
        $this->dropTables();

        return true;
    }

    /**
     * Creates the tables.
     */
    protected function createTables(): void
    {
        // Icon Sets table
        $this->createTable('{{%iconmanager_iconsets}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'type' => $this->string()->notNull(),
            'settings' => $this->text(),
            'enabled' => $this->boolean()->defaultValue(true)->notNull(),
            'sortOrder' => $this->smallInteger()->unsigned(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Settings table (single row like Smart Links)
        $this->createTable('{{%iconmanager_settings}}', [
            'id' => $this->primaryKey(),
            // Plugin settings
            'pluginName' => $this->string()->null(),
            // General settings
            'iconSetsPath' => $this->string()->notNull()->defaultValue('@root/icons'),
            'enableCache' => $this->boolean()->notNull()->defaultValue(true),
            'cacheDuration' => $this->integer()->notNull()->defaultValue(86400),
            'cacheStorageMethod' => $this->string(10)->notNull()->defaultValue('file')->comment('Cache storage method: file or redis'),
            // Icon types (stored as JSON)
            'enabledIconTypes' => $this->text(),
            // Optimization settings
            'enableOptimization' => $this->boolean()->notNull()->defaultValue(true),
            'enableOptimizationBackup' => $this->boolean()->notNull()->defaultValue(true),
            // Scan control settings
            'scanClipPaths' => $this->boolean()->notNull()->defaultValue(true),
            'scanMasks' => $this->boolean()->notNull()->defaultValue(true),
            'scanFilters' => $this->boolean()->notNull()->defaultValue(true),
            'scanComments' => $this->boolean()->notNull()->defaultValue(true),
            'scanInlineStyles' => $this->boolean()->notNull()->defaultValue(true),
            'scanLargeFiles' => $this->boolean()->notNull()->defaultValue(true),
            'scanWidthHeight' => $this->boolean()->notNull()->defaultValue(true),
            'scanWidthHeightWithViewBox' => $this->boolean()->notNull()->defaultValue(false),
            // PHP SVG Optimizer settings (21 rules)
            'optimizeConvertColorsToHex' => $this->boolean()->notNull()->defaultValue(true),
            'optimizeConvertCssClasses' => $this->boolean()->notNull()->defaultValue(true),
            'optimizeConvertEmptyTags' => $this->boolean()->notNull()->defaultValue(true),
            'optimizeConvertInlineStyles' => $this->boolean()->notNull()->defaultValue(true),
            'optimizeFlattenGroups' => $this->boolean()->notNull()->defaultValue(true),
            'optimizeMinifyCoordinates' => $this->boolean()->notNull()->defaultValue(true),
            'optimizeMinifyTransformations' => $this->boolean()->notNull()->defaultValue(true),
            'optimizeRemoveComments' => $this->boolean()->notNull()->defaultValue(true),
            'optimizeRemoveDefaultAttributes' => $this->boolean()->notNull()->defaultValue(true),
            'optimizeRemoveDeprecatedAttributes' => $this->boolean()->notNull()->defaultValue(true),
            'optimizeRemoveDoctype' => $this->boolean()->notNull()->defaultValue(true),
            'optimizeRemoveEnableBackground' => $this->boolean()->notNull()->defaultValue(true),
            'optimizeRemoveEmptyAttributes' => $this->boolean()->notNull()->defaultValue(true),
            'optimizeRemoveInkscapeFootprints' => $this->boolean()->notNull()->defaultValue(true),
            'optimizeRemoveInvisibleCharacters' => $this->boolean()->notNull()->defaultValue(true),
            'optimizeRemoveMetadata' => $this->boolean()->notNull()->defaultValue(true),
            'optimizeRemoveWhitespace' => $this->boolean()->notNull()->defaultValue(true),
            'optimizeRemoveUnusedNamespaces' => $this->boolean()->notNull()->defaultValue(true),
            'optimizeRemoveUnusedMasks' => $this->boolean()->notNull()->defaultValue(true),
            'optimizeRemoveWidthHeight' => $this->boolean()->notNull()->defaultValue(true),
            'optimizeSortAttributes' => $this->boolean()->notNull()->defaultValue(true),
            // Logging settings
            'logLevel' => $this->string(20)->notNull()->defaultValue('error'),
            // UI settings
            'itemsPerPage' => $this->integer()->notNull()->defaultValue(100),
            // System fields
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Insert default settings row
        $this->insert('{{%iconmanager_settings}}', [
            'id' => 1,
            'pluginName' => 'Icon Manager',
            'iconSetsPath' => '@root/icons',
            'enableCache' => true,
            'cacheDuration' => 86400,
            'enabledIconTypes' => Json::encode([
                'svg-folder' => true,
                'svg-sprite' => true,
                'font-awesome' => false,
                'material-icons' => false,
                'web-font' => false,
            ]),
            'enableOptimization' => true,
            'enableOptimizationBackup' => true,
            'scanClipPaths' => true,
            'scanMasks' => true,
            'scanFilters' => true,
            'scanComments' => true,
            'scanInlineStyles' => true,
            'scanLargeFiles' => true,
            'scanWidthHeight' => true,
            'scanWidthHeightWithViewBox' => false,
            // PHP SVG Optimizer defaults (all enabled)
            'optimizeConvertColorsToHex' => true,
            'optimizeConvertCssClasses' => true,
            'optimizeConvertEmptyTags' => true,
            'optimizeConvertInlineStyles' => true,
            'optimizeFlattenGroups' => true,
            'optimizeMinifyCoordinates' => true,
            'optimizeMinifyTransformations' => true,
            'optimizeRemoveComments' => true,
            'optimizeRemoveDefaultAttributes' => true,
            'optimizeRemoveDeprecatedAttributes' => true,
            'optimizeRemoveDoctype' => true,
            'optimizeRemoveEnableBackground' => true,
            'optimizeRemoveEmptyAttributes' => true,
            'optimizeRemoveInkscapeFootprints' => true,
            'optimizeRemoveInvisibleCharacters' => true,
            'optimizeRemoveMetadata' => true,
            'optimizeRemoveWhitespace' => true,
            'optimizeRemoveUnusedNamespaces' => true,
            'optimizeRemoveUnusedMasks' => true,
            'optimizeRemoveWidthHeight' => true,
            'optimizeSortAttributes' => true,
            'logLevel' => 'error',
            'itemsPerPage' => 100,
            'dateCreated' => Db::prepareDateForDb(new \DateTime()),
            'dateUpdated' => Db::prepareDateForDb(new \DateTime()),
            'uid' => StringHelper::UUID(),
        ]);

        // Icon cache table
        $this->createTable('{{%iconmanager_icons}}', [
            'id' => $this->primaryKey(),
            'iconSetId' => $this->integer()->notNull(),
            'name' => $this->string()->notNull(),
            'label' => $this->string(),
            'path' => $this->string()->notNull(), // Relative path only (e.g., 'brand/logo.svg')
            'keywords' => $this->text(),
            'metadata' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
    }

    /**
     * Creates the indexes.
     */
    protected function createIndexes(): void
    {
        $this->createIndex(null, '{{%iconmanager_iconsets}}', 'handle', true);
        $this->createIndex(null, '{{%iconmanager_iconsets}}', 'sortOrder');
        $this->createIndex(null, '{{%iconmanager_iconsets}}', 'enabled');

        // No index needed for settings table (only one row)

        $this->createIndex(null, '{{%iconmanager_icons}}', 'iconSetId');
        $this->createIndex(null, '{{%iconmanager_icons}}', 'name');
    }

    /**
     * Adds the foreign keys.
     */
    protected function addForeignKeys(): void
    {
        $this->addForeignKey(null, '{{%iconmanager_icons}}', 'iconSetId', '{{%iconmanager_iconsets}}', 'id', 'CASCADE', 'CASCADE');
    }

    /**
     * Drops the foreign keys.
     */
    protected function dropForeignKeys(): void
    {
        if ($this->db->tableExists('{{%iconmanager_icons}}')) {
            $this->dropForeignKeyIfExists('{{%iconmanager_icons}}', ['iconSetId']);
        }
    }

    /**
     * Drops the tables.
     */
    protected function dropTables(): void
    {
        $this->dropTableIfExists('{{%iconmanager_icons}}');
        $this->dropTableIfExists('{{%iconmanager_settings}}');
        $this->dropTableIfExists('{{%iconmanager_iconsets}}');
    }
}
