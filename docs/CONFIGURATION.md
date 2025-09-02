# Icon Manager Configuration

## Configuration File

You can override plugin settings by creating an `icon-manager.php` file in your `config/` directory.

### Basic Setup

1. Copy `vendor/lindemannrock/icon-manager/src/config.php` to `config/icon-manager.php`
2. Modify the settings as needed

### Available Settings

```php
<?php
return [
    // Plugin settings
    'pluginName' => 'Icon Manager',
    
    // Icon sets path
    'iconSetsPath' => '@root/src/icons',
    
    // Caching settings
    'enableCache' => true,
    'cacheDuration' => 86400, // 24 hours in seconds
    
    // Icon types to enable
    'enabledIconTypes' => [
        'svg-folder' => true,
        'svg-sprite' => true,
        'font-awesome' => false,
        'material-icons' => false,
    ],
];
```

### Multi-Environment Configuration

You can have different settings per environment:

```php
<?php
use craft\helpers\App;

return [
    // Global settings
    '*' => [
        'pluginName' => 'Icon Manager',
        'iconSetsPath' => '@root/src/icons',
        'enabledIconTypes' => [
            'svg-folder' => true,
            'svg-sprite' => true,
            'font-awesome' => false,
            'material-icons' => false,
        ],
    ],
    
    // Development environment
    'dev' => [
        'iconSetsPath' => '@root/src/icons',
        'enableCache' => true,
        'cacheDuration' => 3600, // 1 hour
        'enabledIconTypes' => [
            'svg-folder' => true,
            'svg-sprite' => true,
            'font-awesome' => true,
            'material-icons' => true,
        ],
    ],
    
    // Staging environment  
    'staging' => [
        'iconSetsPath' => '@webroot/dist/assets/icons',
        'enableCache' => true,
        'cacheDuration' => 86400, // 1 day
    ],
    
    // Production environment
    'production' => [
        'iconSetsPath' => '@webroot/dist/assets/icons',
        'enableCache' => true,
        'cacheDuration' => 2592000, // 30 days
        'enabledIconTypes' => [
            'svg-folder' => true,
            'svg-sprite' => false, // Beta
            'font-awesome' => false, // Beta  
            'material-icons' => false, // Beta
        ],
    ],
];
```

### Using Environment Variables

All settings support environment variables:

```php
return [
    'enableCache' => getenv('ICON_MANAGER_CACHE') === 'true',
    'cacheDuration' => (int)getenv('ICON_CACHE_DURATION') ?: 86400,
    'iconSetsPath' => getenv('ICON_SETS_PATH') ?: '@root/icons',
];
```

### Setting Descriptions

#### Plugin Settings

- **pluginName**: Display name for the plugin in Craft CP navigation

#### Icon Storage Settings

- **iconSetsPath**: Path to your icon files (supports Craft aliases like `@root`, `@webroot`)

#### Caching Settings

- **enableCache**: Enable/disable icon data caching for better performance
- **cacheDuration**: How long to cache icon data in seconds

#### Icon Type Settings

- **enabledIconTypes**: Enable/disable specific icon set types
  - `svg-folder` - SVG files in folders (stable, production-ready)
  - `svg-sprite` - SVG sprite files (beta)
  - `font-awesome` - Font Awesome icons (beta)
  - `material-icons` - Material Icons and Material Symbols (beta)

### Precedence

Settings are loaded in this order (later overrides earlier):

1. Default plugin settings
2. Database-stored settings (from CP)
3. Config file settings
4. Environment-specific config settings

### Performance Recommendations

For production environments:

```php
'production' => [
    'enableCache' => true,
    'cacheDuration' => 2592000, // 30 days for maximum performance
    'iconSetsPath' => '@webroot/dist/assets/icons', // Pre-built icons
    'enabledIconTypes' => [
        'svg-folder' => true, // Only stable features
    ],
],
```

### Security Recommendations

```php
// Use environment variables for sensitive paths
'iconSetsPath' => App::env('ICON_SETS_PATH') ?: '@root/icons',

// Limit cache duration in shared environments
'cacheDuration' => min((int)getenv('CACHE_DURATION') ?: 86400, 604800), // Max 7 days
```

## Icon Metadata Configuration

You can also configure icon metadata files alongside your icon configuration. See the main README for details on `metadata.json` structure and multilingual label support.