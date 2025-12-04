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
 * Icon Record
 *
 * @property int $id
 * @property int $iconSetId
 * @property string $name
 * @property string $label
 * @property string $path
 * @property string $keywords
 * @property string $metadata
 * @property \DateTime $dateCreated
 * @property \DateTime $dateUpdated
 * @property string $uid
 * @since 1.0.0
 */
class IconRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%iconmanager_icons}}';
    }
}
