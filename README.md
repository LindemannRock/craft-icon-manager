# Icon Manager for Craft CMS

[![Latest Version](https://img.shields.io/packagist/v/lindemannrock/craft-icon-manager.svg)](https://packagist.org/packages/lindemannrock/craft-icon-manager)
[![Craft CMS](https://img.shields.io/badge/Craft%20CMS-5.0+-orange.svg)](https://craftcms.com/)
[![PHP](https://img.shields.io/badge/PHP-8.3+-blue.svg)](https://php.net/)
[![Logging Library](https://img.shields.io/badge/Logging%20Library-5.0+-green.svg)](https://github.com/LindemannRock/craft-logging-library)
[![License](https://img.shields.io/packagist/l/lindemannrock/craft-icon-manager.svg)](LICENSE)

A comprehensive icon management field supporting SVG libraries and icon fonts for Craft CMS 5.x.

## Table of Contents

- [Icon Manager for Craft CMS](#icon-manager-for-craft-cms)
  - [Table of Contents](#table-of-contents)
  - [Quick Start](#quick-start)
  - [Features](#features)
  - [Development Status](#development-status)
  - [Requirements](#requirements)
  - [Installation](#installation)
    - [Via Composer](#via-composer)
    - [Using DDEV](#using-ddev)
    - [Via Control Panel](#via-control-panel)
  - [Configuration](#configuration)
    - [Plugin Settings](#plugin-settings)
    - [Config File](#config-file)
      - [Available Configuration Options](#available-configuration-options)
      - [Scan Control Settings](#scan-control-settings)
    - [Creating Icon Sets](#creating-icon-sets)
      - [SVG Folder](#svg-folder)
      - [SVG Sprite](#svg-sprite)
      - [Font Awesome](#font-awesome)
      - [Material Icons](#material-icons)
      - [Web Font](#web-font)
  - [Usage](#usage)
    - [Field Type](#field-type)
    - [Template Variables](#template-variables)
      - [Basic Icon Rendering](#basic-icon-rendering)
      - [Working with Icon Objects](#working-with-icon-objects)
      - [List Icons from a Set](#list-icons-from-a-set)
      - [Search Icons](#search-icons)
      - [Working with Icon Sets](#working-with-icon-sets)
    - [Rendering Options](#rendering-options)
  - [Icon Metadata](#icon-metadata)
    - [Basic Structure](#basic-structure)
    - [Advanced Structure with Multilingual Support](#advanced-structure-with-multilingual-support)
    - [Label Resolution Priority](#label-resolution-priority)
    - [Supported Metadata Properties](#supported-metadata-properties)
  - [Security](#security)
    - [Automatic Sanitization](#automatic-sanitization)
    - [How It Works](#how-it-works)
    - [What's Not Sanitized](#whats-not-sanitized)
    - [Template Safety](#template-safety)
  - [Field Settings](#field-settings)
    - [Icon Size](#icon-size)
    - [Show Labels](#show-labels)
    - [Enable Search](#enable-search)
  - [Caching](#caching)
    - [Configuration](#configuration-1)
    - [Cache Management](#cache-management)
  - [Logging](#logging)
  - [Environment-Specific Paths](#environment-specific-paths)
  - [SVG Optimization](#svg-optimization)
    - [Enabling/Disabling Optimization](#enablingdisabling-optimization)
    - [Optimization Features](#optimization-features)
    - [Issue Detection](#issue-detection)
    - [PHP Optimizer (Default)](#php-optimizer-default)
    - [SVGO (Advanced)](#svgo-advanced)
    - [Scan Controls vs SVGO Configuration](#scan-controls-vs-svgo-configuration)
  - [Missing Icon Handling](#missing-icon-handling)
  - [Performance Tips](#performance-tips)
    - [For Large Icon Sets (500+ icons)](#for-large-icon-sets-500-icons)
    - [For Material Icons](#for-material-icons)
    - [Cache Strategy by Environment](#cache-strategy-by-environment)
  - [Troubleshooting](#troubleshooting)
    - [Icons Not Showing in Picker](#icons-not-showing-in-picker)
    - [Icons Not Rendering in Templates](#icons-not-rendering-in-templates)
    - [Optimization Not Working](#optimization-not-working)
    - [Performance Issues with Large Icon Sets](#performance-issues-with-large-icon-sets)
    - [Config File Not Working](#config-file-not-working)
  - [Examples](#examples)
  - [Support](#support)
  - [License](#license)

## Quick Start

Get up and running with Icon Manager in 5 minutes:

**1. Install the plugin**
```bash
composer require lindemannrock/icon-manager
./craft plugin/install icon-manager
```

**2. Create your first icon set**
- Go to **Icon Manager → Icon Sets** in the Control Panel
- Click **"New Icon Set"**
- Choose **"SVG Folder"** as the type
- Select a folder containing SVG files (or use the default `/icons` folder)
- Save the icon set

**3. Add an icon field to your section**
- Go to **Settings → Fields**
- Create a new **"Icon Manager"** field
- Add it to your entry type
- Start selecting icons in your entries!

**That's it!** Icons are now available in your templates via `{{ entry.iconField.render() }}`.

See [Usage](#usage) below for template examples and advanced features.

## Features

- **Multiple Icon Formats**: Support for SVG files, SVG sprites, Font Awesome, Material Icons, and custom web fonts
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
- **Web Font** - Custom icon font support (TTF, WOFF, OTF) with glyph extraction

SVG folder icons are fully stable and production-ready.

## Requirements

- Craft CMS 5.0 or greater
- PHP 8.3 or greater
- [Logging Library](https://github.com/LindemannRock/craft-logging-library) 5.0 or greater (installed automatically as dependency)

## Installation

### Via Composer

```bash
cd /path/to/project
composer require lindemannrock/craft-icon-manager
./craft plugin/install icon-manager
```

### Using DDEV

```bash
cd /path/to/project
ddev composer require lindemannrock/craft-icon-manager
ddev craft plugin/install icon-manager
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

```bash
cp vendor/lindemannrock/craft-icon-manager/src/config.php config/icon-manager.php
```

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
            'web-font' => false,
        ],

        // SVG Optimization
        'enableOptimization' => true,
        'enableOptimizationBackup' => true,

        // Scan Controls (what scanner detects - PHP optimizer only, SVGO uses svgo.config.js)
        'scanClipPaths' => true,              // Detect empty/unused clip-paths (used ones preserved)
        'scanMasks' => true,                  // Detect empty/unused masks (used ones preserved)
        'scanFilters' => true,                // Detect filter effects
        'scanComments' => true,               // Detect regular comments (legal <!--! ... --> preserved)
        'scanInlineStyles' => true,           // Detect convertible styles (CSS-only properties preserved)
        'scanLargeFiles' => true,             // Detect files >10KB (warning only)
        'scanWidthHeight' => true,            // Detect width/height without viewBox (critical)
        'scanWidthHeightWithViewBox' => false, // Detect width/height with viewBox (optional)
    ],

    // Dev environment settings
    'dev' => [
        // Use source icons in dev
        'iconSetsPath' => '@root/src/icons',

        // Enable caching in dev for performance
        'enableCache' => true,
        'cacheDuration' => 3600, // 1 hour

        // Detailed logging for development
        'logLevel' => 'debug',

        // Allow all icon types for testing
        'enabledIconTypes' => [
            'svg-folder' => true,
            'svg-sprite' => true,
            'font-awesome' => true,
            'material-icons' => true,
            'web-font' => true,
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
            'web-font' => false, // Beta
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
- **itemsPerPage** - Number of icon sets per page in CP index (10-500, default: 100)
- **enabledIconTypes** - Enable/disable specific icon set types
- **enableOptimization** - Enable/disable SVG optimization features (default: true)
- **enableOptimizationBackup** - Automatically create backups before optimization (default: true)
- **Scan Controls** - Granular control over what the scanner detects (see Scan Control Settings below)
- **logLevel** - Logging verbosity: error, warning, info, or debug

#### Scan Control Settings

These settings control what the scanner detects during optimization scans. **Important:** These only affect the scanner and PHP optimizer - SVGO uses its own `svgo.config.js` configuration.

```php
// config/icon-manager.php
return [
    'scanClipPaths' => true,              // Detect empty/unused clip-paths
    'scanMasks' => true,                  // Detect empty/unused masks
    'scanFilters' => true,                // Detect filter effects
    'scanComments' => true,               // Detect comments (excludes legal <!--! ... -->)
    'scanInlineStyles' => true,           // Detect convertible inline styles
    'scanLargeFiles' => true,             // Detect files >10KB (warning)
    'scanWidthHeight' => true,            // Detect width/height without viewBox (critical)
    'scanWidthHeightWithViewBox' => false, // Detect width/height with viewBox (optional)
];
```

**What each scan detects:**

- **scanClipPaths**: Flags **empty or unreferenced** clip-paths only. Used clip-paths are not flagged.
- **scanMasks**: Flags **empty or unreferenced** masks only. Used masks are not flagged.
- **scanFilters**: Flags all `<filter>` elements (can slow rendering). Disable if filters are intentional.
- **scanComments**: Flags regular comments. **Preserves legal comments** (`<!--! ... -->`).
- **scanInlineStyles**: Flags convertible styles (fill, stroke). **Preserves CSS-only** (isolation, mix-blend-mode, transform, filter).
- **scanLargeFiles**: Warning for files >10KB. May be normal for complex icons.
- **scanWidthHeight**: Flags width/height **without viewBox** (responsive issue - default: true).
- **scanWidthHeightWithViewBox**: Flags width/height **even with viewBox** (optional optimization - default: false).

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
- Point to an SVG sprite file containing multiple `<symbol>` elements
- Set optional ID prefix for icon references
- **Performance**: Sprite file is loaded once and injected into the page DOM, allowing all icons to reference the same sprite
- **Best for**: Projects using many icons from the same set (e.g., 10+ icons)
- Supports standard SVG sprite format with `<symbol id="icon-name">` structure

#### Font Awesome
- Supports Font Awesome v7 (latest)
- Configure styles (solid, regular, brands)
- Icons loaded from predefined lists
- Kit support planned for future release

#### Material Icons
- Material Icons (classic) with all styles
- Material Symbols with variable font support
- Configurable axes (weight, fill, optical size)
- **Performance Note**: Automatically loads Google Fonts font file (~3.7 MB) containing all 3,800+ icons. The font file is cached by the browser after first load, but represents a significant initial download.
  - **Alternative**: For better performance, download SVG versions of only the icons you need from [Google Fonts](https://fonts.google.com/icons) and add them to an SVG folder or sprite icon set instead.

#### Web Font
- Custom icon fonts (TTF, WOFF, OTF supported)
- Automatic glyph extraction with unicode mapping
- @font-face CSS generation and serving
- Configurable CSS prefix for icon classes
- **Performance**: Font file is served through Craft and cached by the browser after first load. Font size depends on the number of glyphs in your custom font.
- **Note**: WOFF2 not currently supported - use TTF or WOFF formats

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
        <span>{{ icon.displayLabel }}</span>
    {% endfor %}
{% endif %}

{# displayLabel provides smart label resolution:
   1. Site-specific custom label (if set)
   2. General custom label (if set)
   3. JSON metadata label
   4. Database label
   5. Translation
   6. Formatted filename (fallback)
#}
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
        "label_en": "Freshly Baked",
        "label_ar": "مخبوز طازج",
        "label_fr": "Fraîchement Cuit",
        "label_de": "Frisch Gebacken",
        "label_es": "Recién Horneado",
        "label_it": "Appena Sfornato",
        "search": {
            "terms": ["fresh", "baked", "bread", "bakery", "طازج", "مخبوز", "خبز", "frais", "cuit", "frisch", "gebacken"]
        },
        "category": "product-features",
        "description": "Icon representing freshly baked products"
    }
}
```

**Language Code Format:**
- Use `label_{languageCode}` where `{languageCode}` is the site's language code
- **Use underscore `_` not hyphen `-`**: `label_en`, `label_ar`, `label_fr` (correct) vs `label-en` (wrong)
- Supports any language: `label_en`, `label_ar`, `label_fr`, `label_de`, `label_es`, `label_it`, etc.
- Legacy format (`labelEn`, `labelAr`) still supported for backward compatibility
- Primary language codes only (e.g., `en-US` → uses `label_en`, `ar-AE` → uses `label_ar`)

**Label Resolution in Control Panel:**
- Uses the `?site=` URL parameter to determine which language to show
- Example: When editing `?site=fr`, shows `label_fr` from JSON
- Falls back gracefully: `label_fr` → `labelFr` → `label` → filename

**Custom Labels (Per-Site):**
- Custom labels are stored per-site using `customLabels[siteId]` object
- Each site can have different custom labels for the same icon
- Translation Method "Translate for each site" recommended for independent icon selection per site
- Custom labels take priority over JSON labels when set

### Label Resolution Priority

The plugin resolves icon labels in this order:
1. **Site-specific custom label** (highest priority) - set via field's custom label input for current site
2. **JSON metadata label** - from `label_fr`, `label_ar`, `label_en`, or legacy `labelFr`, `labelAr` based on editing site
3. **Database label** - stored label from icon set
4. **Translation system** - Craft's translation files
5. **Propagated custom label** - custom label from another site (as last resort)
6. **Filename** (fallback) - formatted from the SVG filename

**Important Notes:**
- In Control Panel: Uses `?site=` URL parameter to determine language (not user's CP language)
- On Frontend: Uses the current site being viewed
- Custom labels with "Translate for each site" field setting work correctly per-site
- JSON labels always use the editing site's language, ensuring correct translations in CP

### Supported Metadata Properties

- **`label`** - Default display label (fallback)
- **`label_{languageCode}`** - Language-specific labels (e.g., `label_en`, `label_ar`, `label_fr`, `label_de`)
  - Supports any Craft site language dynamically
  - Use primary language code only (e.g., `en-US` → `label_en`)
- **`labelEn`, `labelAr`** - Legacy format (still supported for backward compatibility)
- **`search.terms`** - Additional search keywords (supports multilingual)
- **`category`** - Icon category for organization
- **`description`** - Icon description for accessibility
- **`keywords`** - Legacy search terms (still supported)

## Security

Icon Manager provides automatic SVG sanitization to protect against XSS (Cross-Site Scripting) attacks and malicious code injection.

### Automatic Sanitization

All SVG content is automatically sanitized at render time to remove potentially malicious code:

- **Script Tags**: All `<script>` tags are removed
- **Event Handlers**: JavaScript event handlers (onclick, onmouseover, onload, etc.) are stripped
- **JavaScript Protocols**: `javascript:` protocols in hrefs are blocked and replaced with `#`
- **Malicious Data URLs**: Data URLs containing script content are prevented
- **External Scripts**: Remote script references are removed

### How It Works

Sanitization occurs when SVG content is rendered via:
- `{{ icon.render() }}` - Full icon rendering with attributes
- `{{ icon.svg }}` - Raw SVG content
- `{{ icon.content }}` - Alias for SVG content

The original SVG files remain unchanged on disk. Sanitization is applied only at render time, ensuring your source files are preserved while output is safe.

### What's Not Sanitized

Icon Manager focuses on security sanitization. The following are **not** automatically removed as they don't pose security risks:

- Clip-paths and masks (visual elements)
- Filters and effects (visual styling)
- Embedded fonts (typography)
- Metadata and comments (documentation)

These elements are preserved because they're part of the SVG's visual design and don't execute code.

### Template Safety

Because sanitization is automatic, you **do not need** to use Twig's `|raw` filter:

```twig
{# ✓ Correct - automatically safe #}
{{ icon.render() }}

{# ✗ Not needed - redundant #}
{{ icon.render()|raw }}
```

The plugin automatically marks output as safe using Twig's `Template::raw()` after sanitization is complete.

## Field Settings

### Icon Size
Controls the display size of icons in both the field preview and picker:
- **Small**: 32x32 pixels
- **Medium**: 48x48 pixels (default)
- **Large**: 64x64 pixels

Note: Font-based icons (Material Icons, Font Awesome, Web Fonts) are rendered 10px smaller than SVG icons for visual balance, as they typically have less padding in their bounding boxes.

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

## SVG Optimization

Icon Manager supports two SVG optimization engines with intelligent issue detection.

### Enabling/Disabling Optimization

SVG optimization can be controlled globally via settings or config file:

**Via Control Panel:**
1. Go to Icon Manager → Settings → SVG Optimization
2. Toggle "Enable Optimization" and "Enable Automatic Backups"

**Via Config File:**
```php
// config/icon-manager.php
return [
    'enableOptimization' => true,          // Enable optimization features
    'enableOptimizationBackup' => true,    // Auto-backup before optimization
];
```

When disabled:
- Optimization tab hidden in Icon Sets
- Optimize column hidden in Icon Sets table
- CLI commands show instructions on how to enable
- Utilities page displays disabled message with settings link

### Optimization Features

### Issue Detection

The plugin scans SVG files and identifies:

**Real Issues (Red):**
- **Unused Clip-Paths**: Empty or unreferenced clip-path definitions that can be removed
- **Unused Masks**: Empty or unreferenced mask definitions that can be removed
- **Comments**: Metadata and comments that increase file size
- **Filters**: Complex effects that may slow rendering
- **Inline Styles**: Styles that are harder to override with CSS
- **Width/Height Attributes**: Should use viewBox for responsive SVGs

**Warnings (Yellow):**
- **Large Files**: Files over 10KB (may be normal for complex icons)

**Not Flagged as Issues:**
- Functional clip-paths and masks that are actually used in the SVG
- ViewBox attributes (these are good for responsiveness)

### PHP Optimizer (Default)

Uses [mathiasreker/php-svg-optimizer](https://github.com/mathiasreker/php-svg-optimizer) and works out of the box. Available in the Control Panel via Icon Manager → Icon Sets → [Set] → Optimize.

**Features:**
- No additional installation required
- Works in CP interface
- Safe for production use
- Limited optimization capabilities
- Best for basic cleanup (metadata, comments, whitespace)

**Limitations:**
- Cannot optimize clip-paths, masks, or complex SVG features
- Limited file size reduction on already-clean SVGs

### SVGO (Advanced)

Uses [SVGO](https://github.com/svg/svgo) for advanced optimization with full configuration control. Requires Node.js installation.

**Installation:**

```bash
# Using npm
npm install --save-dev svgo

# Using yarn
yarn add --dev svgo

# Using pnpm
pnpm add -D svgo
```

**Usage:**

```bash
# Interactive mode - prompts for icon set, engine, and optimization preset
./craft icon-manager/optimize

# Check if SVGO is available
./craft icon-manager/optimize/check

# Direct command with specific icon set
./craft icon-manager/optimize --set=3 --engine=svgo

# Use custom config file
./craft icon-manager/optimize --set=3 --engine=svgo --config=my-svgo.config.js

# Skip backup creation
./craft icon-manager/optimize --set=3 --engine=svgo --noBackup

# Dry run (see what would be optimized without making changes)
./craft icon-manager/optimize --set=3 --engine=svgo --dryRun
```

**Interactive Mode:**

When running `./craft icon-manager/optimize` without flags, the command will:
1. List available SVG folder icon sets
2. Let you choose an icon set
3. Show available engines (PHP or SVGO)
4. If SVGO is selected and no config file exists, offer optimization presets:
   - **Safe**: Remove metadata, comments (preserves visual elements)
   - **Balanced**: Safe + cleanup IDs, remove hidden elements
   - **Aggressive**: Balanced + merge paths, convert colors (may affect styling)
   - **Default**: Use SVGO defaults
5. Ask if you want to create a backup before optimization
6. Show real-time progress during optimization

**Configuration:**

Create a `svgo.config.js` file in your project root for custom optimization:

```javascript
export default {
    plugins: [
        {
            name: 'preset-default',
            params: {
                overrides: {
                    convertColors: false,  // Preserve colors
                    mergePaths: false,     // Don't merge paths
                    removeViewBox: false,  // Keep viewBox
                },
            },
        },
        'removeDimensions',        // Remove width/height
        'removeEmptyContainers',   // Clean up empty elements
        'removeEditorsNSData',     // Remove editor metadata
    ],
};
```

See `docs/svgo.config.example.js` for a complete configuration example.

**Auto-Configuration:**

If no `svgo.config.js` is found:
- **Interactive mode**: Prompts you to choose an optimization preset
- **Direct command mode**: Automatically uses the "Safe" preset to prevent breaking SVGs

**Progress Output:**

SVGO shows real-time progress during optimization:
```
Processing (1/123): icon-name.svg...
  ✓ Optimized
Processing (2/123): another-icon.svg...
  - Skipped (already optimized)
```

**Automatic Backups:**

Before optimization, a backup is automatically created (unless `--noBackup` is used):
- Stored in `storage/backups/icon-manager/`
- Named with timestamp: `icon-set-name-YYYY-MM-DD-HHMMSS.zip`
- Can be restored from Icon Manager → Icon Sets → [Set] → Optimize tab (dev mode only)

**When to Use SVGO:**
- Need to optimize clip-paths, masks, or file size
- Want custom configuration per project
- Running optimization in CI/CD pipelines
- Have complex SVGs that need specific handling
- PHP optimizer finds "nothing to optimize"

**When to Use PHP Optimizer:**
- Want to optimize directly in the CP
- Don't have Node.js in your environment
- Need simple, reliable optimization
- Only need basic cleanup (comments, metadata)

### Scan Controls vs SVGO Configuration

**Scan control settings** (in Icon Manager settings or config) control:
- What the scanner flags as issues in the UI
- What the PHP optimizer attempts to fix

**SVGO configuration** (`svgo.config.js`) controls:
- What SVGO actually optimizes when you run `./craft icon-manager/optimize --engine=svgo`
- Independent of scan control settings

**Example scenario:**
```php
// config/icon-manager.php
'scanComments' => false, // Don't show comments as issues in UI
```

Even with `scanComments => false`:
- Scanner won't flag comments
- PHP optimizer won't remove comments
- **But SVGO will still remove comments** if `removeComments` is in `svgo.config.js`

To fully disable comment removal, you must:
1. Set `scanComments => false` in config (hides from scanner/PHP optimizer)
2. Remove `removeComments` from `svgo.config.js` (prevents SVGO from removing)

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

## Performance Tips

### For Large Icon Sets (500+ icons)

**1. Enable Caching**
```php
'enableCache' => true,
'cacheDuration' => 86400, // 24 hours
```

**2. Optimize Icon Files**
- Run SVGO on your icon sets to reduce file sizes
- Remove unnecessary metadata and comments
- Use viewBox instead of width/height for responsive scaling

**3. Limit Field Icon Sets**
- Configure fields to show only relevant icon sets
- Reduces picker loading time and improves UX

**4. Use SVG Sprites for Repeated Icons**
- If using 10+ icons from the same set on a page
- Sprite loads once, all icons reference it
- Significant performance improvement over individual SVG files

### For Material Icons

Material Icons loads a ~3.7MB font file containing all 3,800+ icons. For better performance:
- Use only the icons you need as SVG files instead
- Download specific icons from [Google Fonts](https://fonts.google.com/icons)
- Add them to an SVG folder icon set

### Cache Strategy by Environment

```php
'dev' => [
    'cacheDuration' => 3600,     // 1 hour - quick iteration
],
'staging' => [
    'cacheDuration' => 86400,    // 1 day - balance updates/performance
],
'production' => [
    'cacheDuration' => 2592000,  // 30 days - maximum performance
],
```

## Logging

Icon Manager uses the [LindemannRock Logging Library](https://github.com/LindemannRock/craft-logging-library) for centralized logging.

### Log Levels
- **Error**: Critical errors only (default)
- **Warning**: Errors and warnings
- **Info**: General information
- **Debug**: Detailed debugging (includes performance metrics, requires devMode)

### Configuration
```php
// config/icon-manager.php
return [
    'logLevel' => 'error', // error, warning, info, or debug
];
```

**Note:** Debug level requires Craft's `devMode` to be enabled. If set to debug with devMode disabled, it automatically falls back to info level.

### Log Files
- **Location**: `storage/logs/icon-manager-YYYY-MM-DD.log`
- **Retention**: 30 days (automatic cleanup via Logging Library)
- **Format**: Structured JSON logs with context data
- **Web Interface**: View and filter logs in CP at Icon Manager → Logs

### Log Management
Access logs through the Control Panel:
1. Navigate to Icon Manager → Logs
2. Filter by date, level, or search terms
3. Download log files for external analysis
4. View file sizes and entry counts
5. Auto-cleanup after 30 days (configurable via Logging Library)

**Requires:** `lindemannrock/craft-logging-library` plugin (installed automatically as dependency)

See [docs/LOGGING.md](docs/LOGGING.md) for detailed logging documentation.

## Troubleshooting

### Icons Not Showing in Picker

**Check icon set is enabled:**
- Go to Icon Manager → Icon Sets
- Ensure the icon set has the green "enabled" status
- Click the set and verify it's enabled in settings

**Refresh icon cache:**
- Go to Utilities → Clear Icon Cache
- Click "Refresh All Icons"
- Or go to the icon set and click "Refresh Icons"

**Check file paths:**
```php
// In config/icon-manager.php
'iconSetsPath' => '@root/icons', // Make sure this path exists
```

Verify the path exists:
```bash
ls /path/to/your/project/icons
```

### Icons Not Rendering in Templates

**Check the icon exists:**
```twig
{% set icon = craft.iconManager.getIcon('mySet', 'iconName') %}
{% if icon %}
    {{ icon.render() }}
{% else %}
    Icon not found
{% endif %}
```

**Check icon set handle:**
- Icon set handles are case-sensitive
- Verify the handle in Icon Manager → Icon Sets

**Clear Craft cache:**
```bash
./craft clear-caches/all
```

### Optimization Not Working

**Check optimization is enabled:**
- Icon Manager → Settings → SVG Optimization
- Ensure "Enable Optimization" is toggled on

**For CLI optimization, check environment:**
- Apply Optimizations button only works in dev/local
- CLI commands work in all environments

**SVGO not found:**
```bash
# Install SVGO
npm install --save-dev svgo

# Or verify it's installed
./craft icon-manager/optimize/check
```

### Performance Issues with Large Icon Sets

**Enable caching:**
```php
'enableCache' => true,
'cacheDuration' => 86400,
```

**Limit icons per page in field:**
- Edit the Icon Manager field
- Set "Icons Per Page" to 50-100 instead of 500

**Optimize SVG files:**
- Large, unoptimized SVGs slow down the picker
- Run optimization on your icon sets

### Config File Not Working

**Check file location:**
```bash
# Should be at:
config/icon-manager.php

# NOT:
vendor/lindemannrock/icon-manager/src/config.php
```

**Verify environment:**
```php
// Check current environment
echo Craft::$app->env; // Should match your config keys
```

**Check for syntax errors:**
```bash
php -l config/icon-manager.php
```

## Examples

For more real-world template examples, see [docs/EXAMPLES.md](docs/EXAMPLES.md) including:

- Navigation menus with icons
- Feature cards and icon grids
- Social media links
- Button components with loading states
- Dynamic icon selection
- Multi-site and RTL support
- Tailwind CSS integration
- Performance optimization techniques
- Accessibility best practices

## Support

- **Documentation**: [https://github.com/LindemannRock/craft-icon-manager](https://github.com/LindemannRock/craft-icon-manager)
- **Issues**: [https://github.com/LindemannRock/craft-icon-manager/issues](https://github.com/LindemannRock/craft-icon-manager/issues)
- **Email**: [support@lindemannrock.com](mailto:support@lindemannrock.com)

## License

This plugin is licensed under the MIT License. See [LICENSE](LICENSE) for details.

---

Developed by [LindemannRock](https://lindemannrock.com)
