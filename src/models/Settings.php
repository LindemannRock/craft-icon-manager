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
            [['enableCache'], 'boolean'],
            [['cacheDuration'], 'integer', 'min' => 1],
            [['enabledIconTypes'], 'safe'],
            [['logLevel'], 'in', 'range' => ['trace', 'info', 'warning', 'error']],
        ];
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
            $booleanFields = ['enableCache'];
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
}