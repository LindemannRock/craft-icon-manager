<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\iconmanager\models;

use Craft;
use craft\base\Model;
use craft\behaviors\EnvAttributeParserBehavior;
use lindemannrock\base\helpers\StoragePathHelper;
use lindemannrock\base\traits\DateFormatSettingsTrait;
use lindemannrock\base\traits\ItemsPerPageSettingsTrait;
use lindemannrock\base\traits\LogLevelSettingsTrait;
use lindemannrock\base\traits\PluginNameSettingsTrait;
use lindemannrock\base\traits\SettingsConfigTrait;
use lindemannrock\base\traits\SettingsDisplayNameTrait;
use lindemannrock\base\traits\SettingsPersistenceTrait;
use lindemannrock\base\validators\StoragePathValidator;
use lindemannrock\logginglibrary\traits\LoggingTrait;

/**
 * Icon Manager Settings Model
 *
 * @since 1.0.0
 */
class Settings extends Model
{
    use DateFormatSettingsTrait;
    use ItemsPerPageSettingsTrait;
    use LogLevelSettingsTrait;
    use LoggingTrait;
    use PluginNameSettingsTrait;
    use SettingsConfigTrait;
    use SettingsDisplayNameTrait;
    use SettingsPersistenceTrait;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle('icon-manager');
    }

    /**
     * @var string The name of the plugin as it appears in the Control Panel menu
     */
    public string $pluginName = 'Icon Manager';
    
    /**
     * @var string The path to icon sets folder
     */
    public string $iconSetsPath = '@root/icons';

    /**
     * @var bool Whether to enable icon caching
     */
    public bool $enableCache = true;

    /**
     * @var int Cache duration in seconds (24 hours)
     */
    public int $cacheDuration = 86400;

    /**
     * @var string Cache storage method (file or redis)
     */
    public string $cacheStorageMethod = 'file';

    /**
     * @var array Default icon set types to enable
     */
    public array $enabledIconTypes = [
        'svg-folder' => true,
        'svg-sprite' => true,
        'font-awesome' => false,
        'material-icons' => false,
        'web-font' => false,
    ];

    /**
     * @var bool Enable SVG optimization features
     */
    public bool $enableOptimization = true;

    /**
     * @var bool Enable automatic backups before optimization
     */
    public bool $enableOptimizationBackup = true;

    /**
     * @var bool Scan for unused clip-paths
     */
    public bool $scanClipPaths = true;

    /**
     * @var bool Scan for unused masks
     */
    public bool $scanMasks = true;

    /**
     * @var bool Scan for filters
     */
    public bool $scanFilters = true;

    /**
     * @var bool Scan for comments
     */
    public bool $scanComments = true;

    /**
     * @var bool Scan for inline styles
     */
    public bool $scanInlineStyles = true;

    /**
     * @var bool Scan for large files (>10KB)
     */
    public bool $scanLargeFiles = true;

    /**
     * @var bool Scan for width/height without viewBox (critical issue)
     */
    public bool $scanWidthHeight = true;

    /**
     * @var bool Scan for width/height even with viewBox (optional optimization)
     */
    public bool $scanWidthHeightWithViewBox = false;

    // PHP SVG Optimizer Settings (control what optimizations to apply)
    // Exposes the plugin-supported v8 rule set plus risky-rule opt-in.

    /**
     * @var bool Convert colors to hex format
     */
    public bool $optimizeConvertColorsToHex = true;

    /**
     * @var bool Convert CSS classes to inline SVG attributes
     */
    public bool $optimizeConvertCssClasses = true;

    /**
     * @var bool Convert empty tags to self-closing format
     */
    public bool $optimizeConvertEmptyTags = true;

    /**
     * @var bool Convert inline styles to SVG attributes
     */
    public bool $optimizeConvertInlineStyles = true;

    /**
     * @var bool Flatten unnecessary groups
     */
    public bool $optimizeFlattenGroups = true;

    /**
     * @var bool Minify SVG coordinates
     */
    public bool $optimizeMinifyCoordinates = true;

    /**
     * @var bool Minify transformation matrices
     */
    public bool $optimizeMinifyTransformations = true;

    /**
     * @var bool Remove comments (preserves legal comments <!--! -->)
     */
    public bool $optimizeRemoveComments = true;

    /**
     * @var bool Remove default attributes
     */
    public bool $optimizeRemoveDefaultAttributes = true;

    /**
     * @var bool Remove deprecated attributes
     */
    public bool $optimizeRemoveDeprecatedAttributes = true;

    /**
     * @var bool Remove DOCTYPE declarations
     */
    public bool $optimizeRemoveDoctype = true;

    /**
     * @var bool Remove enable-background attribute (deprecated)
     */
    public bool $optimizeRemoveEnableBackground = true;

    /**
     * @var bool Remove empty attributes
     */
    public bool $optimizeRemoveEmptyAttributes = true;

    /**
     * @var bool Remove Inkscape-specific elements and attributes
     */
    public bool $optimizeRemoveInkscapeFootprints = true;

    /**
     * @var bool Remove invisible characters
     */
    public bool $optimizeRemoveInvisibleCharacters = true;

    /**
     * @var bool Remove metadata elements
     */
    public bool $optimizeRemoveMetadata = true;

    /**
     * @var bool Remove unnecessary whitespace
     */
    public bool $optimizeRemoveWhitespace = true;

    /**
     * @var bool Remove unused XML namespaces
     */
    public bool $optimizeRemoveUnusedNamespaces = true;

    /**
     * @var bool Remove unused mask definitions
     */
    public bool $optimizeRemoveUnusedMasks = true;

    /**
     * @var bool Remove width/height attributes (keeps viewBox for responsive sizing)
     */
    public bool $optimizeRemoveWidthHeight = true;

    /**
     * @var bool Sort attributes alphabetically
     */
    public bool $optimizeSortAttributes = true;

    /**
     * @var bool Fix invalid or legacy attribute names
     */
    public bool $optimizeFixAttributeNames = true;

    /**
     * @var bool Remove aria-* and role attributes
     */
    public bool $optimizeRemoveAriaAndRole = false;

    /**
     * @var bool Remove data-* attributes
     */
    public bool $optimizeRemoveDataAttributes = false;

    /**
     * @var bool Remove duplicate SVG elements
     */
    public bool $optimizeRemoveDuplicateElements = true;

    /**
     * @var bool Remove empty groups
     */
    public bool $optimizeRemoveEmptyGroups = true;

    /**
     * @var bool Remove empty text elements
     */
    public bool $optimizeRemoveEmptyTextElements = true;

    /**
     * @var bool Remove non-standard SVG attributes
     */
    public bool $optimizeRemoveNonStandardAttributes = false;

    /**
     * @var bool Remove non-standard SVG tags
     */
    public bool $optimizeRemoveNonStandardTags = false;

    /**
     * @var bool Remove <title> and <desc> elements
     */
    public bool $optimizeRemoveTitleAndDesc = false;

    /**
     * @var bool Remove unsafe SVG elements
     */
    public bool $optimizeRemoveUnsafeElements = true;

    /**
     * @var bool Scope SVG styles
     */
    public bool $optimizeScopeSvgStyles = false;

    /**
     * @var bool Allow risky optimizer rules
     */
    public bool $optimizeAllowRiskyRules = true;


    /**
     * @inheritdoc
     */
    protected function defineBehaviors(): array
    {
        return [
            'parser' => [
                'class' => EnvAttributeParserBehavior::class,
                'attributes' => ['iconSetsPath'],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return array_merge([
            [['iconSetsPath'], 'required'],
            [['iconSetsPath'], 'string'],
            [
                'iconSetsPath',
                StoragePathValidator::class,
                'translationCategory' => static::pluginHandle(),
                'allowedAliases' => ['@root', '@storage', '@webroot'],
                'preventWebroot' => false,
                'requireAlias' => true,
            ],
            [['enableCache', 'enableOptimization', 'enableOptimizationBackup', 'scanClipPaths', 'scanMasks', 'scanFilters', 'scanComments', 'scanInlineStyles', 'scanLargeFiles', 'scanWidthHeight', 'scanWidthHeightWithViewBox', 'optimizeConvertColorsToHex', 'optimizeConvertCssClasses', 'optimizeConvertEmptyTags', 'optimizeConvertInlineStyles', 'optimizeFlattenGroups', 'optimizeMinifyCoordinates', 'optimizeMinifyTransformations', 'optimizeRemoveComments', 'optimizeRemoveDefaultAttributes', 'optimizeRemoveDeprecatedAttributes', 'optimizeRemoveDoctype', 'optimizeRemoveEnableBackground', 'optimizeRemoveEmptyAttributes', 'optimizeRemoveInkscapeFootprints', 'optimizeRemoveInvisibleCharacters', 'optimizeRemoveMetadata', 'optimizeRemoveWhitespace', 'optimizeRemoveUnusedNamespaces', 'optimizeRemoveUnusedMasks', 'optimizeRemoveWidthHeight', 'optimizeSortAttributes', 'optimizeFixAttributeNames', 'optimizeRemoveAriaAndRole', 'optimizeRemoveDataAttributes', 'optimizeRemoveDuplicateElements', 'optimizeRemoveEmptyGroups', 'optimizeRemoveEmptyTextElements', 'optimizeRemoveNonStandardAttributes', 'optimizeRemoveNonStandardTags', 'optimizeRemoveTitleAndDesc', 'optimizeRemoveUnsafeElements', 'optimizeScopeSvgStyles', 'optimizeAllowRiskyRules'], 'boolean'],
            [['cacheDuration'], 'integer', 'min' => 60, 'max' => 604800],
            [['cacheStorageMethod'], 'in', 'range' => ['file', 'redis']],
            [['enabledIconTypes'], 'safe'],
        ], $this->pluginNameSettingsRules(), $this->logLevelSettingsRules(), $this->dateFormatSettingsRules(), $this->itemsPerPageSettingsRules());
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return array_merge([
            'iconSetsPath' => Craft::t('icon-manager', 'Icon Sets Path'),
            'enabledIconTypes' => Craft::t('icon-manager', 'Icon Types'),
            'enableCache' => Craft::t('icon-manager', 'Enable Cache'),
            'cacheDuration' => Craft::t('icon-manager', 'Cache Duration'),
            'cacheStorageMethod' => Craft::t('icon-manager', 'Cache Storage Method'),
            'enableOptimization' => Craft::t('icon-manager', 'Enable Optimization'),
            'enableOptimizationBackup' => Craft::t('icon-manager', 'Enable Automatic Backups'),
            'optimizeAllowRiskyRules' => Craft::t('icon-manager', 'Allow Risky Rules'),
            'optimizeConvertColorsToHex' => Craft::t('icon-manager', 'Convert Colors to Hex'),
            'optimizeConvertCssClasses' => Craft::t('icon-manager', 'Convert CSS Classes to Attributes'),
            'optimizeConvertEmptyTags' => Craft::t('icon-manager', 'Convert Empty Tags to Self-Closing'),
            'optimizeConvertInlineStyles' => Craft::t('icon-manager', 'Convert Inline Styles to Attributes'),
            'optimizeFixAttributeNames' => Craft::t('icon-manager', 'Fix Attribute Names'),
            'optimizeFlattenGroups' => Craft::t('icon-manager', 'Flatten Groups'),
            'optimizeMinifyCoordinates' => Craft::t('icon-manager', 'Minify SVG Coordinates'),
            'optimizeMinifyTransformations' => Craft::t('icon-manager', 'Minify Transformations'),
            'optimizeRemoveAriaAndRole' => Craft::t('icon-manager', 'Remove Aria and Role Attributes'),
            'optimizeRemoveComments' => Craft::t('icon-manager', 'Remove Comments'),
            'optimizeRemoveDataAttributes' => Craft::t('icon-manager', 'Remove Data Attributes'),
            'optimizeRemoveDefaultAttributes' => Craft::t('icon-manager', 'Remove Default Attributes'),
            'optimizeRemoveDeprecatedAttributes' => Craft::t('icon-manager', 'Remove Deprecated Attributes'),
            'optimizeRemoveDoctype' => Craft::t('icon-manager', 'Remove DOCTYPE'),
            'optimizeRemoveDuplicateElements' => Craft::t('icon-manager', 'Remove Duplicate Elements'),
            'optimizeRemoveEmptyAttributes' => Craft::t('icon-manager', 'Remove Empty Attributes'),
            'optimizeRemoveEmptyGroups' => Craft::t('icon-manager', 'Remove Empty Groups'),
            'optimizeRemoveEmptyTextElements' => Craft::t('icon-manager', 'Remove Empty Text Elements'),
            'optimizeRemoveEnableBackground' => Craft::t('icon-manager', 'Remove Enable-Background'),
            'optimizeRemoveInkscapeFootprints' => Craft::t('icon-manager', 'Remove Inkscape Footprints'),
            'optimizeRemoveInvisibleCharacters' => Craft::t('icon-manager', 'Remove Invisible Characters'),
            'optimizeRemoveMetadata' => Craft::t('icon-manager', 'Remove Metadata'),
            'optimizeRemoveNonStandardAttributes' => Craft::t('icon-manager', 'Remove Non-Standard Attributes'),
            'optimizeRemoveNonStandardTags' => Craft::t('icon-manager', 'Remove Non-Standard Tags'),
            'optimizeRemoveTitleAndDesc' => Craft::t('icon-manager', 'Remove Title and Description'),
            'optimizeRemoveUnsafeElements' => Craft::t('icon-manager', 'Remove Unsafe Elements'),
            'optimizeRemoveUnusedMasks' => Craft::t('icon-manager', 'Remove Unused Masks'),
            'optimizeRemoveUnusedNamespaces' => Craft::t('icon-manager', 'Remove Unused Namespaces'),
            'optimizeRemoveWhitespace' => Craft::t('icon-manager', 'Remove Whitespace'),
            'optimizeRemoveWidthHeight' => Craft::t('icon-manager', 'Remove Width/Height Attributes'),
            'optimizeScopeSvgStyles' => Craft::t('icon-manager', 'Scope SVG Styles'),
            'optimizeSortAttributes' => Craft::t('icon-manager', 'Sort Attributes'),
            'scanClipPaths' => Craft::t('icon-manager', 'Scan Clip-Paths'),
            'scanComments' => Craft::t('icon-manager', 'Scan Comments'),
            'scanFilters' => Craft::t('icon-manager', 'Scan Filters'),
            'scanInlineStyles' => Craft::t('icon-manager', 'Scan Inline Styles'),
            'scanLargeFiles' => Craft::t('icon-manager', 'Scan Large Files'),
            'scanMasks' => Craft::t('icon-manager', 'Scan Masks'),
            'scanWidthHeight' => Craft::t('icon-manager', 'Scan Width/Height (Critical)'),
            'scanWidthHeightWithViewBox' => Craft::t('icon-manager', 'Scan Width/Height with ViewBox'),
        ], $this->pluginNameSettingsLabel(), $this->logLevelSettingsLabel(), $this->dateFormatSettingsLabels(), $this->itemsPerPageSettingsLabel());
    }

    /**
     * Custom setter for enabledIconTypes to normalize boolean values
     * Converts string values ("1", "") to proper booleans (true, false)
     *
     * @param array $value
     * @since 1.10.0
     */
    public function setEnabledIconTypes(array $value): void
    {
        // Normalize all values to booleans
        foreach ($value as $type => $enabled) {
            $value[$type] = filter_var($enabled, FILTER_VALIDATE_BOOLEAN);
        }

        $this->enabledIconTypes = $value;
    }

    /**
     * Get the resolved icon sets path
     *
     * @return string
     */
    public function getResolvedIconSetsPath(): string
    {
        return StoragePathHelper::resolve($this->iconSetsPath);
    }
    
    // =========================================================================
    // Trait Configuration Methods
    // =========================================================================

    /**
     * Database table name for settings storage
     */
    protected static function tableName(): string
    {
        return 'iconmanager_settings';
    }

    /**
     * Plugin handle for config file resolution
     */
    protected static function pluginHandle(): string
    {
        return 'icon-manager';
    }

    /**
     * Fields that should be cast to boolean
     */
    protected static function booleanFields(): array
    {
        return [
            'enableCache',
            'enableOptimization',
            'enableOptimizationBackup',
            'scanClipPaths',
            'scanMasks',
            'scanFilters',
            'scanComments',
            'scanInlineStyles',
            'scanLargeFiles',
            'scanWidthHeight',
            'scanWidthHeightWithViewBox',
            'optimizeConvertColorsToHex',
            'optimizeConvertCssClasses',
            'optimizeConvertEmptyTags',
            'optimizeConvertInlineStyles',
            'optimizeFlattenGroups',
            'optimizeMinifyCoordinates',
            'optimizeMinifyTransformations',
            'optimizeRemoveComments',
            'optimizeRemoveDefaultAttributes',
            'optimizeRemoveDeprecatedAttributes',
            'optimizeRemoveDoctype',
            'optimizeRemoveEnableBackground',
            'optimizeRemoveEmptyAttributes',
            'optimizeRemoveInkscapeFootprints',
            'optimizeRemoveInvisibleCharacters',
            'optimizeRemoveMetadata',
            'optimizeRemoveWhitespace',
            'optimizeRemoveUnusedNamespaces',
            'optimizeRemoveUnusedMasks',
            'optimizeRemoveWidthHeight',
            'optimizeSortAttributes',
            'optimizeFixAttributeNames',
            'optimizeRemoveAriaAndRole',
            'optimizeRemoveDataAttributes',
            'optimizeRemoveDuplicateElements',
            'optimizeRemoveEmptyGroups',
            'optimizeRemoveEmptyTextElements',
            'optimizeRemoveNonStandardAttributes',
            'optimizeRemoveNonStandardTags',
            'optimizeRemoveTitleAndDesc',
            'optimizeRemoveUnsafeElements',
            'optimizeScopeSvgStyles',
            'optimizeAllowRiskyRules',
            'showSeconds',
        ];
    }

    /**
     * Fields that should be cast to integer
     */
    protected static function integerFields(): array
    {
        return [
            'cacheDuration',
            'itemsPerPage',
        ];
    }

    /**
     * Fields that should be JSON encoded/decoded
     */
    protected static function jsonFields(): array
    {
        return [
            'enabledIconTypes',
        ];
    }

    /**
     * Fields to exclude from database save
     */
    protected static function excludeFromSave(): array
    {
        return [];
    }
}
