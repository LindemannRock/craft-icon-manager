<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\iconmanager\migrations;

use Craft;
use craft\db\Migration;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\helpers\StringHelper;

/**
 * Install migration.
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
            // Icon types (stored as JSON)
            'enabledIconTypes' => $this->text(),
            // System fields
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Insert default settings row
        $this->insert('{{%iconmanager_settings}}', [
            'id' => 1,
            'pluginName' => null,
            'iconSetsPath' => '@root/icons',
            'enableCache' => true,
            'cacheDuration' => 86400,
            'enabledIconTypes' => Json::encode([
                'svg-folder' => true,
                'svg-sprite' => true,
                'font-awesome' => false,
                'material-icons' => false,
            ]),
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
            $this->dropForeignKey($this->db->getForeignKeyName('{{%iconmanager_icons}}', 'iconSetId'), '{{%iconmanager_icons}}');
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