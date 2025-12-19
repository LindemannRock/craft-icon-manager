<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * Comprehensive icon management field supporting SVG libraries and icon fonts
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\iconmanager;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\console\Application as ConsoleApplication;
use craft\events\RegisterCacheOptionsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\Fields;
use craft\services\UserPermissions;
use craft\services\Utilities;
use craft\utilities\ClearCaches;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use craft\web\View;
use lindemannrock\iconmanager\fields\IconManagerField;
use lindemannrock\iconmanager\models\Settings;
use lindemannrock\iconmanager\services\IconSetsService;
use lindemannrock\iconmanager\services\IconsService;
use lindemannrock\iconmanager\services\SvgOptimizerService;
use lindemannrock\iconmanager\services\SvgoService;
use lindemannrock\iconmanager\utilities\ClearIconCache;
use lindemannrock\iconmanager\variables\IconManagerVariable;
use lindemannrock\logginglibrary\LoggingLibrary;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use yii\base\Event;

/**
 * Icon Manager Plugin
 *
 * @author    LindemannRock
 * @package   IconManager
 * @since     1.0.0
 *
 * @property-read IconsService $icons
 * @property-read IconSetsService $iconSets
 * @property-read SvgOptimizerService $svgOptimizer
 * @property-read SvgoService $svgo
 * @property-read Settings $settings
 * @method Settings getSettings()
 */
class IconManager extends Plugin
{
    use LoggingTrait;

    /**
     * @var IconManager|null Singleton plugin instance
     */
    public static ?IconManager $plugin = null;

    /**
     * @var string Plugin schema version for migrations
     */
    public string $schemaVersion = '1.0.0';

    /**
     * @var bool Whether the plugin exposes a control panel settings page
     */
    public bool $hasCpSettings = true;

    /**
     * @var bool Whether the plugin registers a control panel section
     */
    public bool $hasCpSection = true;

    /**
     * @inheritdoc
     */
    public static function config(): array
    {
        return [
            'components' => [
                'icons' => IconsService::class,
                'iconSets' => IconSetsService::class,
                'svgOptimizer' => SvgOptimizerService::class,
                'svgo' => \lindemannrock\iconmanager\services\SvgoService::class,
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        // Override plugin name from config if available, otherwise use from database settings
        $configFileSettings = Craft::$app->getConfig()->getConfigFromFile('icon-manager');
        if (isset($configFileSettings['pluginName'])) {
            $this->name = $configFileSettings['pluginName'];
        } else {
            // Get from database settings
            $settings = $this->getSettings();
            if (!empty($settings->pluginName)) {
                $this->name = $settings->pluginName;
            }
        }

        $this->_registerLogTarget();
        $this->_registerCpRoutes();
        $this->_registerFieldTypes();

        // Register Twig extension for plugin name helpers
        Craft::$app->view->registerTwigExtension(new \lindemannrock\iconmanager\twigextensions\PluginNameExtension());

        $this->_registerVariables();
        $this->_registerPermissions();
        $this->_registerTemplateRoots();
        $this->_registerUtilities();
        $this->_registerCacheOptions();

        // Register console controller
        if (Craft::$app instanceof ConsoleApplication) {
            $this->controllerNamespace = 'lindemannrock\iconmanager\console';
        }

        // Remove info logging to prevent log flooding
    }

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): ?Model
    {
        // Load settings from database using the new method
        try {
            return Settings::loadFromDatabase();
        } catch (\Exception $e) {
            // Database might not be ready during installation
            $this->logInfo('Could not load settings from database', ['error' => $e->getMessage()]);
            return new Settings();
        }
    }

    /**
     * Force reload settings from database
     * This is needed because Craft caches settings in a private property
     */
    public function reloadSettings(): void
    {
        // Use reflection to access and clear the private _settings property
        $reflection = new \ReflectionClass(\craft\base\Plugin::class);
        $property = $reflection->getProperty('_settings');
        $property->setAccessible(true);
        $property->setValue($this, null);
    }

    /**
     * @inheritdoc
     */
    public function getSettingsResponse(): mixed
    {
        return Craft::$app->controller->redirect('icon-manager/settings');
    }

    /**
     * @inheritdoc
     */
    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate(
            'icon-manager/settings',
            [
                'settings' => $this->getSettings(),
                'plugin' => $this,
            ]
        );
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();

        if ($item) {
            // Use Craft's built-in folder-grid icon for icon management
            $item['icon'] = '@appicons/folder-grid.svg';

            $item['subnav'] = [
                'icon-sets' => [
                    'label' => Craft::t('icon-manager', 'Icon Sets'),
                    'url' => 'icon-manager',
                ],
            ];

            // Add logs section using the logging library (only if installed)
            if (Craft::$app->getPlugins()->isPluginInstalled('logging-library') &&
                Craft::$app->getPlugins()->isPluginEnabled('logging-library')) {
                $item = LoggingLibrary::addLogsNav($item, $this->handle, [
                    'iconManager:viewLogs',
                ]);
            }

            if (Craft::$app->getUser()->checkPermission('iconManager:editSettings')) {
                $item['subnav']['settings'] = [
                    'label' => Craft::t('icon-manager', 'Settings'),
                    'url' => 'icon-manager/settings',
                ];
            }
        }

        return $item;
    }

    /**
     * Register CP routes
     */
    private function _registerCpRoutes(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules = array_merge($event->rules, [
                    // Icon Sets routes
                    'icon-manager' => 'icon-manager/icon-sets/index',
                    'icon-manager/icon-sets' => 'icon-manager/icon-sets/index',
                    'icon-manager/icon-sets/new' => 'icon-manager/icon-sets/edit',
                    'icon-manager/icon-sets/<iconSetId:\d+>/optimize' => 'icon-manager/icon-sets/optimize',
                    'icon-manager/icon-sets/<iconSetId:\d+>' => 'icon-manager/icon-sets/edit',
                    'icon-manager/icon-sets/delete' => 'icon-manager/icon-sets/delete',
                    'icon-manager/icon-sets/refresh-icons' => 'icon-manager/icon-sets/refresh-icons',

                    // Settings routes
                    'icon-manager/settings' => 'icon-manager/settings/index',
                    'icon-manager/settings/icon-types' => 'icon-manager/settings/icon-types',
                    'icon-manager/settings/svg-optimization' => 'icon-manager/settings/svg-optimization',
                    'icon-manager/settings/<section:\w+>' => 'icon-manager/settings/<section>',

                    // Logging routes
                    'icon-manager/logs' => 'logging-library/logs/index',
                    'icon-manager/logs/download' => 'logging-library/logs/download',

                    // Icons API routes
                    'icon-manager/icons/render' => 'icon-manager/icons/render',
                    'icon-manager/icons/get-data' => 'icon-manager/icons/get-data',
                    'icon-manager/icons/get-icons-for-field' => 'icon-manager/icons/get-icons-for-field',
                    'icon-manager/icons/serve-font' => 'icon-manager/icons/serve-font',
                    'icon-manager/icons/serve-sprite' => 'icon-manager/icons/serve-sprite',

                    // Cache routes
                    'icon-manager/cache/clear' => 'icon-manager/cache/clear',
                ]);
            }
        );
    }

    /**
     * Register field types
     */
    private function _registerFieldTypes(): void
    {
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = IconManagerField::class;
            }
        );
    }

    /**
     * Register template variables
     */
    private function _registerVariables(): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $e) {
                /** @var CraftVariable $variable */
                $variable = $e->sender;
                $variable->set('iconManager', IconManagerVariable::class);
            }
        );
    }

    /**
     * Register user permissions
     */
    private function _registerPermissions(): void
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => Craft::t('icon-manager', 'Icon Manager'),
                    'permissions' => [
                        'iconManager:manageIconSets' => [
                            'label' => Craft::t('icon-manager', 'Manage icon sets'),
                        ],
                        'iconManager:viewLogs' => [
                            'label' => Craft::t('icon-manager', 'View logs'),
                        ],
                        'iconManager:editSettings' => [
                            'label' => Craft::t('icon-manager', 'Edit plugin settings'),
                        ],
                    ],
                ];
            }
        );
    }

    /**
     * Register template roots
     */
    private function _registerTemplateRoots(): void
    {
        Event::on(
            View::class,
            View::EVENT_REGISTER_CP_TEMPLATE_ROOTS,
            function(RegisterTemplateRootsEvent $event) {
                $event->roots['icon-manager'] = __DIR__ . '/templates';
            }
        );
    }

    /**
     * Register utilities
     */
    private function _registerUtilities(): void
    {
        Event::on(
            Utilities::class,
            Utilities::EVENT_REGISTER_UTILITIES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = ClearIconCache::class;
            }
        );
    }

    /**
     * Register cache options for Craft's Clear Caches utility
     */
    private function _registerCacheOptions(): void
    {
        Event::on(
            ClearCaches::class,
            ClearCaches::EVENT_REGISTER_CACHE_OPTIONS,
            function(RegisterCacheOptionsEvent $event) {
                $settings = $this->getSettings();
                $displayName = $settings->getDisplayName();

                $event->options[] = [
                    'key' => 'icon-manager-cache',
                    'label' => Craft::t('icon-manager', '{displayName} caches', ['displayName' => $displayName]),
                    'action' => function() {
                        $this->_clearIconCache();
                    },
                ];
            }
        );
    }

    /**
     * Clear all Icon Manager caches
     */
    private function _clearIconCache(): void
    {
        $settings = $this->getSettings();

        if ($settings->cacheStorageMethod === 'redis') {
            // Clear Redis cache
            $cache = Craft::$app->cache;
            if ($cache instanceof \yii\redis\Cache) {
                $redis = $cache->redis;

                // Get all icon cache keys from tracking set
                $keys = $redis->executeCommand('SMEMBERS', ['iconmanager-icons-keys']) ?: [];

                // Delete icon cache keys using Craft's cache component
                foreach ($keys as $key) {
                    $cache->delete($key);
                }

                // Clear the tracking set
                $redis->executeCommand('DEL', ['iconmanager-icons-keys']);
            }
        } else {
            // Clear file cache
            $runtimePath = Craft::$app->path->getRuntimePath();
            $cacheBasePath = $runtimePath . '/icon-manager/cache/';
            $cacheTypes = ['svg-folder', 'svg-sprite', 'material-icons', 'font-awesome', 'web-font'];

            foreach ($cacheTypes as $type) {
                $cachePath = $cacheBasePath . $type . '/';
                if (is_dir($cachePath)) {
                    $cacheFiles = glob($cachePath . '*.cache');
                    foreach ($cacheFiles as $file) {
                        @unlink($file);
                    }
                }
            }
        }

        // Clear memory caches
        $this->icons->clearMemoryCache();
    }

    /**
     * Register log target for dedicated log file
     */
    private function _registerLogTarget(): void
    {
        // Configure logging using the new logging library
        $settings = $this->getSettings();

        LoggingLibrary::configure([
            'pluginHandle' => $this->handle,
            'pluginName' => $settings->pluginName ?? $this->name,
            'logLevel' => $settings->logLevel ?? 'error',
            'itemsPerPage' => $settings->itemsPerPage ?? 50,
            'permissions' => ['iconManager:viewLogs'],
        ]);
    }
}
