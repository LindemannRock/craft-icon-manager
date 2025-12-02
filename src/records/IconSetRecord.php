<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\iconmanager\records;

use craft\db\ActiveRecord;

/**
 * Icon Set Record
 *
 * @property int $id
 * @property string $name
 * @property string $handle
 * @property string $type
 * @property string $settings
 * @property bool $enabled
 * @property int $sortOrder
 * @property \DateTime $dateCreated
 * @property \DateTime $dateUpdated
 * @property string $uid
 */
class IconSetRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%iconmanager_iconsets}}';
    }
}
