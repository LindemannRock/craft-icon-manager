<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\iconmanager\models;

use Craft;
use craft\base\Model;
use craft\behaviors\EnvAttributeParserBehavior;
use lindemannrock\base\traits\SettingsConfigTrait;
use lindemannrock\base\traits\SettingsDisplayNameTrait;
use lindemannrock\base\traits\SettingsPersistenceTrait;
use lindemannrock\logginglibrary\traits\LoggingTrait;

/**
 * Icon Manager Settings Model
 *
 * @since 1.0.0
 */
class Settings extends Model
{
    use LoggingTrait;
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
     * @var string The public-facing name of the plugin
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
        'font-awesome' => true,
        'material-icons' => false,
        'web-font' => false,
    ];

    /**
     * @var string Log level for the plugin
     */
    public string $logLevel = 'error';

    /**
     * @var int Items per page in CP element index
     */
    public int $itemsPerPage = 100;

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
    // All 21 available rules from php-svg-optimizer v7.3

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
        return [
            [['iconSetsPath'], 'required'],
            [['iconSetsPath', 'pluginName', 'logLevel'], 'string'],
            [['enableCache', 'enableOptimization', 'enableOptimizationBackup', 'scanClipPaths', 'scanMasks', 'scanFilters', 'scanComments', 'scanInlineStyles', 'scanLargeFiles', 'scanWidthHeight', 'scanWidthHeightWithViewBox', 'optimizeConvertColorsToHex', 'optimizeConvertCssClasses', 'optimizeConvertEmptyTags', 'optimizeConvertInlineStyles', 'optimizeFlattenGroups', 'optimizeMinifyCoordinates', 'optimizeMinifyTransformations', 'optimizeRemoveComments', 'optimizeRemoveDefaultAttributes', 'optimizeRemoveDeprecatedAttributes', 'optimizeRemoveDoctype', 'optimizeRemoveEnableBackground', 'optimizeRemoveEmptyAttributes', 'optimizeRemoveInkscapeFootprints', 'optimizeRemoveInvisibleCharacters', 'optimizeRemoveMetadata', 'optimizeRemoveWhitespace', 'optimizeRemoveUnusedNamespaces', 'optimizeRemoveUnusedMasks', 'optimizeRemoveWidthHeight', 'optimizeSortAttributes'], 'boolean'],
            [['cacheDuration'], 'integer', 'min' => 1],
            [['cacheStorageMethod'], 'in', 'range' => ['file', 'redis']],
            [['itemsPerPage'], 'integer', 'min' => 10, 'max' => 500],
            [['itemsPerPage'], 'default', 'value' => 100],
            [['enabledIconTypes'], 'safe'],
            [['logLevel'], 'in', 'range' => ['debug', 'info', 'warning', 'error']],
            [['logLevel'], 'validateLogLevel'],
        ];
    }

    /**
     * Validate log level - debug requires devMode
     */
    public function validateLogLevel($attribute)
    {
        $logLevel = $this->$attribute;

        // Reset session warning when devMode is true - allows warning to show again if devMode changes
        if (Craft::$app->getConfig()->getGeneral()->devMode && !Craft::$app->getRequest()->getIsConsoleRequest()) {
            Craft::$app->getSession()->remove('im_debug_config_warning');
        }

        // Debug level is only allowed when devMode is enabled - auto-fallback to info
        if ($logLevel === 'debug' && !Craft::$app->getConfig()->getGeneral()->devMode) {
            $this->$attribute = 'info';

            // Only log warning once per session for config overrides
            if ($this->isOverriddenByConfig('logLevel')) {
                if (!Craft::$app->getRequest()->getIsConsoleRequest()) {
                    // Web request - use session to prevent duplicate warnings
                    if (Craft::$app->getSession()->get('im_debug_config_warning') === null) {
                        $this->logWarning('Log level "debug" from config file changed to "info" because devMode is disabled', [
                            'configFile' => 'config/icon-manager.php',
                        ]);
                        Craft::$app->getSession()->set('im_debug_config_warning', true);
                    }
                } else {
                    // Console request - just log without session
                    $this->logWarning('Log level "debug" from config file changed to "info" because devMode is disabled', [
                        'configFile' => 'config/icon-manager.php',
                    ]);
                }
            } else {
                // Database setting - save the correction
                $this->logWarning('Log level automatically changed from "debug" to "info" because devMode is disabled');
                $this->saveToDatabase();
            }
        }
    }

    /**
     * Custom setter for enabledIconTypes to normalize boolean values
     * Converts string values ("1", "") to proper booleans (true, false)
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
     */
    public function getResolvedIconSetsPath(): string
    {
        return Craft::getAlias($this->iconSetsPath);
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
