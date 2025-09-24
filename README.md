# Icon Manager for Craft CMS

A comprehensive icon management field supporting SVG libraries and icon fonts for Craft CMS 5.x.

## Features

- **Multiple Icon Formats**: Support for SVG files, SVG sprites, Font Awesome, and Material Icons
- **Intuitive Interface**: Searchable icon picker with preview, tabs/dropdown for multiple sets
- **Flexible Configuration**: Configure icon sets and field settings (size, labels, search)
- **Performance Optimized**: Built-in caching for fast icon loading
- **Security**: SVG sanitization to prevent XSS attacks
- **Twig Integration**: Easy icon rendering in templates with automatic HTML safety

## Development Status

⚠️ **Beta Features** - The following features are currently in testing and finalization:
- **SVG Sprite support** - Basic functionality implemented, advanced features pending
- **Material Icons** - Core integration complete, variable fonts being refined  
- **Font Awesome** - Free icons working, Pro/Kit support in development

SVG folder icons are fully stable and production-ready.

## Requirements

- Craft CMS 5.0 or greater
- PHP 8.2 or greater

## Installation

### Via Composer (Development)

Until published on Packagist, install directly from the repository:

```bash
cd /path/to/project
composer config repositories.icon-manager vcs https://github.com/LindemannRock/craft-icon-manager
composer require lindemannrock/icon-manager:dev-main
```

Then install the plugin:
```bash
./craft plugin/install icon-manager
```

### Via Composer (Production - Coming Soon)

Once published on Packagist:

```bash
cd /path/to/project
composer require lindemannrock/icon-manager
./craft plugin/install icon-manager
```

### Via Control Panel

In the Control Panel, go to Settings → Plugins and click "Install" for Icon Manager.

## Configuration

### Plugin Settings

Settings can be configured in the Control Panel at Settings → Icon Manager, or via a config file.

1. **Icon Sets Path**: Set the path to your icon files (default: `@root/icons`)
2. **Cache Settings**: Configure caching for better performance
3. **Display Settings**: Set default display options
4. **Icon Types**: Enable/disable different icon set types

### Config File

Create a `config/icon-manager.php` file to override default settings:

```php
<?php

use craft\helpers\App;

return [
    // Global settings
    '*' => [
        // Plugin display name
        'pluginName' => 'Icon Manager',
        
        // Default icons path
        'iconSetsPath' => '@root/src/icons',
        
        // Icon types to enable
        'enabledIconTypes' => [
            'svg-folder' => true,
            'svg-sprite' => true,
            'font-awesome' => false,
            'material-icons' => false,
        ],
    ],
    
    // Dev environment settings
    'dev' => [
        // Use source icons in dev
        'iconSetsPath' => '@root/src/icons',

        // Enable caching in dev for performance
        'enableCache' => true,
        'cacheDuration' => 3600, // 1 hour

        // Detailed logging for development
        'logLevel' => 'trace',

        // Allow all icon types for testing
        'enabledIconTypes' => [
            'svg-folder' => true,
            'svg-sprite' => true,
            'font-awesome' => true,
            'material-icons' => true,
        ],
    ],
    
    // Staging environment settings  
    'staging' => [
        // Production-ready icons path
        'iconSetsPath' => '@webroot/dist/assets/icons',
        
        // Optimize for staging
        'enableCache' => true,
        'cacheDuration' => 86400, // 1 day
    ],
    
    // Production environment settings
    'production' => [
        // Production icons path
        'iconSetsPath' => '@webroot/dist/assets/icons',

        // Optimize for production
        'enableCache' => true,
        'cacheDuration' => 2592000, // 30 days

        // Minimal logging for production
        'logLevel' => 'warning',

        // Only stable icon types in production
        'enabledIconTypes' => [
            'svg-folder' => true,
            'svg-sprite' => false, // Beta
            'font-awesome' => false, // Beta
            'material-icons' => false, // Beta
        ],
    ],
];
```

Settings defined in the config file will override CP settings and show a warning message in the settings UI.

See [Configuration Documentation](docs/CONFIGURATION.md) for all available options.

#### Available Configuration Options

- **pluginName** - Customize the plugin display name in navigation and settings
- **iconSetsPath** - Path to icon files (supports aliases like `@root`, `@webroot`)
- **enableCache** - Whether to cache icon data for better performance
- **cacheDuration** - How long to cache icon data, in seconds
- **enabledIconTypes** - Enable/disable specific icon set types
- **logLevel** - Logging verbosity: error, warning, info, or trace

### Creating Icon Sets

1. Navigate to Icon Manager → Icon Sets in the CP
2. Click "New Icon Set"
3. Choose the type and configure settings:

#### SVG Folder
- Point to a folder containing SVG files
- Optionally include subfolders
- Supports metadata.json for keywords
- Works great with icon libraries like:
  - [Lucide](https://lucide.dev/) - 1,600+ open source icons
  - [Heroicons](https://heroicons.com/) - Beautiful hand-crafted SVG icons
  - [Tabler Icons](https://tabler-icons.io/) - 3,000+ free SVG icons
  - Any other SVG icon library

#### SVG Sprite
- Point to an SVG sprite file
- Set optional ID prefix

#### Font Awesome
- Supports Font Awesome v7 (latest)
- Configure styles (solid, regular, brands)
- Icons loaded from predefined lists
- Kit support planned for future release

#### Material Icons
- Material Icons (classic) with all styles
- Material Symbols with variable font support
- Configurable axes (weight, fill, optical size)

## Usage

### Field Type

Add an Icon field to any element:

1. Create a new field of type "Icon Manager"
2. Configure field settings:
   - **Enable Search**: Show search box in picker
   - **Show Labels**: Display icon names in picker
   - **Icon Size**: Small (32px), Medium (48px), or Large (64px)
   - **Icon Sets**: Choose which sets are available
   - **Allow Multiple Selection**: Enable selecting multiple icons (returns array)
   - **Allow Custom Labels**: Let users add custom labels for icons
   - **Icons Per Page**: Number of icons displayed in picker (10-500)

3. Use in templates:

```twig
{# Single icon field #}
{% if entry.iconField %}
    {{ entry.iconField.render({ size: 32, class: 'my-icon' }) }}
{% endif %}

{# Multiple icons field #}
{% if entry.multipleIconsField %}
    {% for icon in entry.multipleIconsField %}
        {{ icon.render({ size: 24, class: 'icon-item' }) }}
        {% if icon.customLabel %}
            <span>{{ icon.customLabel }}</span>
        {% endif %}
    {% endfor %}
{% endif %}
```

Note: The `|raw` filter is not needed - icons are automatically rendered safely.

### Template Variables

#### Basic Icon Rendering

```twig
{# Render an icon directly #}
{{ craft.iconManager.renderIcon('myIconSet', 'arrow-right') }}

{# With options (size, class, etc.) #}
{{ craft.iconManager.renderIcon('myIconSet', 'arrow-right', {
    width: 24,
    height: 24,
    class: 'text-blue-500'
}) }}
```

#### Working with Icon Objects

```twig
{# Get icon object for more control #}
{% set icon = craft.iconManager.getIcon('myIconSet', 'star') %}

{% if icon %}
    {# Render with default settings #}
    {{ icon.render() }}
    
    {# Render with custom attributes #}
    {{ icon.render({
        width: 32,
        height: 32,
        class: 'icon-custom',
        'aria-label': 'Star rating'
    }) }}
    
    {# Get raw SVG content #}
    {{ icon.getContent() }}
    
    {# Access icon properties #}
    <div data-icon="{{ icon.name }}" data-set="{{ icon.iconSet.handle }}">
        {{ icon.render() }}
    </div>
{% endif %}
```

#### List Icons from a Set

```twig
{# Get all icons from a set #}
{% set icons = craft.iconManager.getIcons('myIconSet') %}

<div class="icon-grid">
    {% for icon in icons %}
        <div class="icon-item">
            {{ icon.render({ width: 24, height: 24 }) }}
            <span>{{ icon.name }}</span>
        </div>
    {% endfor %}
</div>
```

#### Search Icons

```twig
{# Search for icons across all sets #}
{% set searchResults = craft.iconManager.searchIcons('arrow') %}

{# Search within specific sets #}
{% set searchResults = craft.iconManager.searchIcons('arrow', ['myIconSet', 'fontAwesome']) %}

{% for icon in searchResults %}
    <div class="search-result">
        {{ icon.render({ width: 20, height: 20 }) }}
        <span>{{ icon.name }} ({{ icon.iconSet.name }})</span>
    </div>
{% endfor %}
```

#### Working with Icon Sets

```twig
{# Get all enabled icon sets #}
{% set iconSets = craft.iconManager.getEnabledIconSets() %}

{# Get a specific icon set #}
{% set iconSet = craft.iconManager.getIconSet('myIconSet') %}

{% if iconSet %}
    <h3>{{ iconSet.name }}</h3>
    <p>Type: {{ iconSet.type }}</p>
    <p>Icon count: {{ iconSet.icons|length }}</p>
{% endif %}
```

### Rendering Options

```twig
{{ icon.render({
    # Dimensions
    width: 24,
    height: 24,
    
    # CSS class
    class: 'custom-icon-class',
    
    # Additional attributes
    'data-icon': icon.name,
    'aria-label': 'Icon description',
    'role': 'img',
    
    # Style attribute
    style: 'fill: currentColor;'
}) }}
```

## Icon Metadata

Add a `metadata.json` file in your icon folders to provide enhanced metadata:

### Basic Structure

```json
{
    "star": {
        "keywords": ["favorite", "rating", "bookmark"]
    },
    "heart": {
        "keywords": ["love", "like", "favorite"]  
    }
}
```

### Advanced Structure with Multilingual Support

```json
{
    "freshly-baked": {
        "label": "Freshly Baked",
        "labelAr": "مخبوز طازج", 
        "labelEn": "Freshly Baked",
        "search": {
            "terms": ["fresh", "baked", "bread", "bakery", "طازج", "مخبوز", "خبز"]
        },
        "category": "product-features",
        "description": "Icon representing freshly baked products"
    }
}
```

### Label Resolution Priority

The plugin resolves icon labels in this order:
1. **Custom field label** (highest priority) - set in the field interface
2. **JSON metadata label** - from `label`, `labelAr`, `labelEn` based on site language
3. **Database label** - stored label from icon set
4. **Translation system** - Craft's translation files
5. **Filename** (fallback) - formatted from the SVG filename

### Supported Metadata Properties

- **`label`** - Default display label
- **`labelAr`** - Arabic language label  
- **`labelEn`** - English language label
- **`search.terms`** - Additional search keywords (supports multilingual)
- **`category`** - Icon category for organization
- **`description`** - Icon description for accessibility
- **`keywords`** - Legacy search terms (still supported)

## Security

All SVG content is automatically sanitized to remove potentially malicious code:
- Script tags are removed
- Event handlers (onclick, onmouseover, etc.) are stripped
- JavaScript protocols in hrefs are blocked
- Malicious data URLs are prevented

This ensures icons are safe to use without the `|raw` filter.

## Field Settings

### Icon Size
Controls the display size of icons in both the field preview and picker:
- **Small**: 32x32 pixels
- **Medium**: 48x48 pixels (default)
- **Large**: 64x64 pixels

### Show Labels
When enabled, displays icon names below icons in the picker and field preview.

### Enable Search
Shows a search box in the icon picker for filtering icons by name or keywords.

## Caching

Icon Manager includes a comprehensive caching system for better performance:

### Configuration
```php
// config/icon-manager.php
return [
    'enableCache' => true,        // Enable/disable caching
    'cacheDuration' => 86400,     // Cache duration in seconds (24 hours)
];
```

### Cache Management
- **Clear Icon Cache Utility**: Go to Utilities → Clear Icon Cache
- **Integrated with Craft**: Available in Utilities → Clear Caches
- **Automatic**: Cache clears when refreshing icon sets

## Logging

Icon Manager includes comprehensive logging with configurable levels:

### Log Levels
- **Error**: Critical errors only
- **Warning**: Errors and warnings
- **Info**: General information
- **Trace**: Detailed debugging (includes performance metrics)

### Configuration
```php
// config/icon-manager.php
return [
    'logLevel' => 'info', // error, warning, info, or trace
];
```

### Log Files
- **Location**: `storage/logs/icon-manager-YYYY-MM-DD.log`
- **Retention**: 30 days (automatic cleanup)
- **Format**: Structured logs with context data
- **Web Interface**: View and filter logs in CP at Icon Manager → Logs

### What's Logged
- **Error**: File system failures, SVG parsing errors, database errors
- **Warning**: Missing icons, empty content, slow operations (>1s)
- **Info**: Icon set operations, cache clears, major actions
- **Trace**: Cache hits/misses, performance timing, API requests

### Log Management
Access logs through the Control Panel:
1. Navigate to Icon Manager → Logs
2. Filter by date, level, or search terms
3. Download log files for external analysis
4. View file sizes and entry counts

## Environment-Specific Paths

Configure different icon paths per environment:

```php
// config/icon-manager.php
return [
    'dev' => [
        'iconSetsPath' => '@root/src/icons',     // Development
    ],
    'production' => [
        'iconSetsPath' => '@webroot/assets/icons', // Production
    ],
];
```

Icons are stored with relative paths in the database, so changing `iconSetsPath` instantly updates all icon locations without rescanning.

## Missing Icon Handling

When an icon file cannot be found:
- **In templates**: Returns `null` (no errors thrown)
- **In CP field**: Shows warning icon with tooltip
- **In logs**: Records warning with full path details

Check if an icon exists:
```twig
{% if icon.exists() %}
    {{ icon.render() }}
{% else %}
    {# Handle missing icon #}
{% endif %}
```

## Support

- **Documentation**: [https://github.com/LindemannRock/craft-icon-manager](https://github.com/LindemannRock/craft-icon-manager)
- **Issues**: [https://github.com/LindemannRock/craft-icon-manager/issues](https://github.com/LindemannRock/craft-icon-manager/issues)
- **Email**: [support@lindemannrock.com](mailto:support@lindemannrock.com)

## License

This plugin is licensed under the MIT License. See [LICENSE](LICENSE) for details.

---

Developed by [LindemannRock](https://lindemannrock.com)