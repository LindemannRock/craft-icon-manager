<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\iconmanager\models;

use lindemannrock\iconmanager\IconManager;
use Craft;
use craft\base\Model;
use craft\behaviors\EnvAttributeParserBehavior;
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\Json;

/**
 * Icon Manager Settings Model
 */
class Settings extends Model
{
    /**
     * @var array Track which settings are overridden by config
     */
    private array $_overriddenSettings = [];
    
    /**
     * @var array Track which specific icon types are overridden
     */
    private array $_overriddenIconTypes = [];
    
    /**
     * @var string|null The public-facing name of the plugin
     */
    public ?string $pluginName = 'Icon Manager';
    
    /**
     * @var string The path to icon sets folder
     */
    public string $iconSetsPath = '@root/icons';

    /**
     * @var bool Whether to enable icon caching
     */
    public bool $enableCache = true;

    /**
     * @var int Cache duration in seconds
     */
    public int $cacheDuration = 86400; // 24 hours

    /**
     * @var array Default icon set types to enable
     */
    public array $enabledIconTypes = [
        'svg-folder' => true,
        'svg-sprite' => true,
        'font-awesome' => true,
        'material-icons' => false,
    ];

    /**
     * @var string Log level for the plugin
     */
    public string $logLevel = 'error';

    /**
     * @var bool Enable SVG optimization features
     */
    public bool $enableOptimization = true;

    /**
     * @var bool Enable automatic backups before optimization
     */
    public bool $enableOptimizationBackup = true;


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
            [['enableCache', 'enableOptimization', 'enableOptimizationBackup'], 'boolean'],
            [['cacheDuration'], 'integer', 'min' => 1],
            [['enabledIconTypes'], 'safe'],
            [['logLevel'], 'in', 'range' => ['debug', 'info', 'warning', 'error']],
            [['logLevel'], 'validateLogLevel'],
        ];
    }

    /**
     * Validate log level - debug requires devMode
     */
    public function validateLogLevel($attribute, $params, $validator)
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
                        Craft::warning('Log level "debug" from config file changed to "info" because devMode is disabled. Please update your config/icon-manager.php file.', 'icon-manager');
                        Craft::$app->getSession()->set('im_debug_config_warning', true);
                    }
                } else {
                    // Console request - just log without session
                    Craft::warning('Log level "debug" from config file changed to "info" because devMode is disabled. Please update your config/icon-manager.php file.', 'icon-manager');
                }
            } else {
                // Database setting - save the correction
                Craft::warning('Log level automatically changed from "debug" to "info" because devMode is disabled. This setting has been saved.', 'icon-manager');
                $this->saveToDatabase();
            }
        }
    }

    /**
     * Get the resolved icon sets path
     */
    public function getResolvedIconSetsPath(): string
    {
        return Craft::getAlias($this->iconSetsPath);
    }
    
    /**
     * @inheritdoc
     */
    public function __construct($config = [])
    {
        // Get config file overrides
        $configFileSettings = Craft::$app->getConfig()->getConfigFromFile('icon-manager');
        
        // Merge config file settings with defaults
        if ($configFileSettings) {
            $config = array_merge($configFileSettings, $config);
        }
        
        parent::__construct($config);
    }
    
    /**
     * Load settings from database
     *
     * @param Settings|null $settings Optional existing settings instance
     * @return self
     */
    public static function loadFromDatabase(?Settings $settings = null): self
    {
        if ($settings === null) {
            $settings = new self();
        }
        
        // Load from database
        try {
            $row = (new Query())
                ->from('{{%iconmanager_settings}}')
                ->where(['id' => 1])
                ->one();
        } catch (\Exception $e) {
            Craft::error('Failed to load settings from database: ' . $e->getMessage(), 'icon-manager');
            return $settings;
        }
        
        if ($row) {
            // Remove system fields that aren't attributes
            unset($row['id'], $row['dateCreated'], $row['dateUpdated'], $row['uid']);
            
            // Convert numeric boolean values to actual booleans
            $booleanFields = ['enableCache', 'enableOptimization', 'enableOptimizationBackup'];
            foreach ($booleanFields as $field) {
                if (isset($row[$field])) {
                    $row[$field] = (bool) $row[$field];
                }
            }
            
            // Convert numeric values to integers
            $integerFields = ['cacheDuration'];
            foreach ($integerFields as $field) {
                if (isset($row[$field])) {
                    $row[$field] = (int) $row[$field];
                }
            }
            
            // Decode JSON fields
            if (isset($row['enabledIconTypes'])) {
                $row['enabledIconTypes'] = Json::decode($row['enabledIconTypes']);
            }
            
            // Set attributes from database
            $settings->setAttributes($row, false);
        } else {
            Craft::warning('No settings found in database', 'icon-manager');
        }
        
        // Apply config file overrides
        $configFileSettings = Craft::$app->getConfig()->getConfigFromFile('icon-manager');
        if ($configFileSettings) {
            // Track which settings are overridden
            foreach ($configFileSettings as $setting => $value) {
                if (property_exists($settings, $setting)) {
                    // Special handling for enabledIconTypes - only override specified keys
                    if ($setting === 'enabledIconTypes' && is_array($value)) {
                        // Track which specific icon types are overridden
                        $settings->_overriddenIconTypes = array_keys($value);

                        // Only override the icon types that are in the config
                        // Keep database values for non-specified icon types
                        foreach ($value as $iconType => $enabled) {
                            $settings->enabledIconTypes[$iconType] = $enabled;
                        }
                    } else {
                        // For non-array settings, track as fully overridden
                        $settings->_overriddenSettings[] = $setting;
                        $settings->$setting = $value;
                    }
                }
            }
        }

        // IMPORTANT: Validate settings after config overrides are applied
        // This will trigger validateLogLevel and other validation methods
        if (!$settings->validate()) {
            Craft::error('Icon Manager settings validation failed: ' . print_r($settings->getErrors(), true), 'icon-manager');
        }

        return $settings;
    }
    
    /**
     * Save settings to database
     *
     * @return bool
     */
    public function saveToDatabase(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        $db = Craft::$app->getDb();
        
        // Build the attributes to save
        $attributes = [
            'pluginName' => $this->pluginName,
            'iconSetsPath' => $this->iconSetsPath,
            'enableCache' => $this->enableCache,
            'cacheDuration' => $this->cacheDuration,
            'enabledIconTypes' => Json::encode($this->enabledIconTypes),
            'logLevel' => $this->logLevel,
            'enableOptimization' => $this->enableOptimization,
            'enableOptimizationBackup' => $this->enableOptimizationBackup,
            'dateUpdated' => Db::prepareDateForDb(new \DateTime()),
        ];
        
        Craft::info('Attempting to save settings: ' . Json::encode($attributes), 'icon-manager');
        
        // Update existing settings (we know there's always one row from migration)
        try {
            $result = $db->createCommand()
                ->update('{{%iconmanager_settings}}', $attributes, ['id' => 1])
                ->execute();
            
            return $result !== false;
        } catch (\Exception $e) {
            Craft::error('Failed to save Icon Manager settings: ' . $e->getMessage(), 'icon-manager');
            return false;
        }
    }
    
    /**
     * Check if a setting is overridden by config file
     *
     * @param string $setting
     * @return bool
     */
    public function isOverridden(string $setting): bool
    {
        return in_array($setting, $this->_overriddenSettings, true);
    }
    
    /**
     * Get all overridden settings
     *
     * @return array
     */
    public function getOverriddenSettings(): array
    {
        return $this->_overriddenSettings;
    }
    
    /**
     * Check if a specific icon type is overridden
     *
     * @param string $iconType
     * @return bool
     */
    public function isIconTypeOverridden(string $iconType): bool
    {
        return in_array($iconType, $this->_overriddenIconTypes, true);
    }
    
    /**
     * Get all overridden icon types
     *
     * @return array
     */
    public function getOverriddenIconTypes(): array
    {
        return $this->_overriddenIconTypes;
    }

    /**
     * Check if a setting is being overridden by config file
     *
     * @param string $attribute The setting attribute name
     * @return bool
     */
    public function isOverriddenByConfig(string $attribute): bool
    {
        // Get the config file path
        $configPath = \Craft::$app->getPath()->getConfigPath() . '/icon-manager.php';

        if (!file_exists($configPath)) {
            return false;
        }

        // Load the raw config file
        $rawConfig = require $configPath;

        // Check if this attribute is set in the config file (root level or environment level)
        $hasRootConfig = array_key_exists($attribute, $rawConfig);
        $env = \Craft::$app->getConfig()->getGeneral()->env ?? '*';
        $hasEnvConfig = isset($rawConfig[$env]) && is_array($rawConfig[$env]) && array_key_exists($attribute, $rawConfig[$env]);

        if (!$hasRootConfig && !$hasEnvConfig) {
            return false;
        }


        return true;
    }

    /**
     * Get the raw config value (for display in settings form)
     *
     * @param string $attribute The setting attribute name
     * @return mixed|null
     */
    public function getRawConfigValue(string $attribute)
    {
        // Get the config file path
        $configPath = \Craft::$app->getPath()->getConfigPath() . '/icon-manager.php';

        if (!file_exists($configPath)) {
            return null;
        }

        // Load the raw config file
        $rawConfig = require $configPath;

        // Check environment-specific settings first (highest priority)
        $env = \Craft::$app->getConfig()->getGeneral()->env ?? '*';
        if (isset($rawConfig[$env]) && is_array($rawConfig[$env]) && array_key_exists($attribute, $rawConfig[$env])) {
            return $rawConfig[$env][$attribute];
        }

        // Check root level
        if (array_key_exists($attribute, $rawConfig)) {
            return $rawConfig[$attribute];
        }

        return null;
    }
}