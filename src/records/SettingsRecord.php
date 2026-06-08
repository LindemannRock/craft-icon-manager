<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\iconmanager\records;

use craft\db\ActiveRecord;

/**
 * Settings Record
 *
 * @property int $id
 * @property string|null $pluginName
 * @property string $iconSetsPath
 * @property string|null $iconSetsVolumeUid
 * @property bool $enableCache
 * @property int $cacheDuration
 * @property string $cacheStorageMethod
 * @property string|null $enabledIconTypes
 * @property bool $enableOptimization
 * @property bool $enableOptimizationBackup
 * @property string $backupPath
 * @property string|null $backupVolumeUid
 * @property bool $scanClipPaths
 * @property bool $scanMasks
 * @property bool $scanFilters
 * @property bool $scanComments
 * @property bool $scanInlineStyles
 * @property bool $scanLargeFiles
 * @property bool $scanWidthHeight
 * @property bool $scanWidthHeightWithViewBox
 * @property bool $optimizeAllowRiskyRules
 * @property bool $optimizeConvertColorsToHex
 * @property bool $optimizeConvertCssClasses
 * @property bool $optimizeConvertEmptyTags
 * @property bool $optimizeConvertInlineStyles
 * @property bool $optimizeFixAttributeNames
 * @property bool $optimizeFlattenGroups
 * @property bool $optimizeMinifyCoordinates
 * @property bool $optimizeMinifyTransformations
 * @property bool $optimizeRemoveAriaAndRole
 * @property bool $optimizeRemoveComments
 * @property bool $optimizeRemoveDataAttributes
 * @property bool $optimizeRemoveDefaultAttributes
 * @property bool $optimizeRemoveDeprecatedAttributes
 * @property bool $optimizeRemoveDoctype
 * @property bool $optimizeRemoveDuplicateElements
 * @property bool $optimizeRemoveEmptyGroups
 * @property bool $optimizeRemoveEnableBackground
 * @property bool $optimizeRemoveEmptyAttributes
 * @property bool $optimizeRemoveEmptyTextElements
 * @property bool $optimizeRemoveInkscapeFootprints
 * @property bool $optimizeRemoveInvisibleCharacters
 * @property bool $optimizeRemoveMetadata
 * @property bool $optimizeRemoveNonStandardAttributes
 * @property bool $optimizeRemoveNonStandardTags
 * @property bool $optimizeRemoveTitleAndDesc
 * @property bool $optimizeRemoveUnsafeElements
 * @property bool $optimizeRemoveWhitespace
 * @property bool $optimizeRemoveUnusedNamespaces
 * @property bool $optimizeRemoveUnusedMasks
 * @property bool $optimizeRemoveWidthHeight
 * @property bool $optimizeScopeSvgStyles
 * @property bool $optimizeSortAttributes
 * @property string $logLevel
 * @property int $itemsPerPage
 * @property \DateTime $dateCreated
 * @property \DateTime $dateUpdated
 * @property string $uid
 * @since 1.0.0
 */
class SettingsRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%iconmanager_settings}}';
    }
}
