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

    // SVG Optimization settings
    'enableOptimization' => true,          // Enable optimization features
    'enableOptimizationBackup' => true,    // Auto-backup before optimization

    // Scan Controls (what scanner detects - PHP optimizer only, SVGO uses svgo.config.js)
    'scanClipPaths' => true,              // Detect empty/unused clip-paths (used ones preserved)
    'scanMasks' => true,                  // Detect empty/unused masks (used ones preserved)
    'scanFilters' => true,                // Detect filter effects
    'scanComments' => true,               // Detect regular comments (legal <!--! ... --> preserved)
    'scanInlineStyles' => true,           // Detect convertible styles (CSS-only properties preserved)
    'scanLargeFiles' => true,             // Detect files >10KB (warning only)
    'scanWidthHeight' => true,            // Detect width/height without viewBox (critical)
    'scanWidthHeightWithViewBox' => false, // Detect width/height with viewBox (optional)

    // Logging settings
    'logLevel' => 'error', // error, warning, info, debug

    // Icon types to enable
    'enabledIconTypes' => [
        'svg-folder' => true,
        'svg-sprite' => true,
        'font-awesome' => false,
        'material-icons' => false,
        'web-font' => false,
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
            'web-font' => false,
        ],

        // Scan Controls (PHP optimizer only, SVGO uses svgo.config.js)
        'scanClipPaths' => true,              // Detect empty/unused clip-paths (used ones preserved)
        'scanMasks' => true,                  // Detect empty/unused masks (used ones preserved)
        'scanFilters' => true,                // Detect filter effects
        'scanComments' => true,               // Detect regular comments (legal <!--! ... --> preserved)
        'scanInlineStyles' => true,           // Detect convertible styles (CSS-only properties preserved)
        'scanLargeFiles' => true,             // Detect files >10KB (warning only)
        'scanWidthHeight' => true,            // Detect width/height without viewBox (critical)
        'scanWidthHeightWithViewBox' => false, // Detect width/height with viewBox (optional)
    ],

    // Development environment
    'dev' => [
        'iconSetsPath' => '@root/src/icons',
        'enableCache' => true,
        'cacheDuration' => 3600, // 1 hour
        'logLevel' => 'debug', // Detailed logging for development
        'enabledIconTypes' => [
            'svg-folder' => true,
            'svg-sprite' => true,
            'font-awesome' => true,
            'material-icons' => true,
            'web-font' => true,
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
        'logLevel' => 'warning', // Minimal logging for production
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

### Using Environment Variables

All settings support environment variables:

```php
return [
    'enableCache' => getenv('ICON_MANAGER_CACHE') === 'true',
    'cacheDuration' => (int)getenv('ICON_CACHE_DURATION') ?: 86400,
    'iconSetsPath' => getenv('ICON_SETS_PATH') ?: '@root/icons',
    'logLevel' => getenv('ICON_LOG_LEVEL') ?: 'error',
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

#### SVG Optimization Settings

- **enableOptimization**: Enable/disable SVG optimization features globally (default: true)
  - When disabled, hides optimization UI, blocks optimization commands, and shows helpful messages
- **enableOptimizationBackup**: Automatically create backups before applying optimizations (default: true)
  - Backups are stored in `storage/backups/icon-manager/` with timestamps
  - Can be restored via Control Panel (dev mode only) or manually

#### Scan Control Settings

Control what the scanner detects as optimization opportunities. **Note:** These settings only affect the scanner and PHP optimizer. SVGO uses its own configuration file (`svgo.config.js`) and will optimize based on its settings regardless of these flags.

- **scanClipPaths** (default: true)
  - Detects **empty or unused** clip-path definitions
  - Only flags clip-paths that are not referenced anywhere in the SVG or have no content
  - Used clip-paths are preserved and not flagged as issues

- **scanMasks** (default: true)
  - Detects **empty or unused** mask definitions
  - Only flags masks that are not referenced anywhere in the SVG or have no content
  - Used masks are preserved and not flagged as issues

- **scanFilters** (default: true)
  - Detects filter effects (`<filter>` elements)
  - Flags all filters as they can slow down rendering
  - Some complex effects require filters - disable this if filters are intentional

- **scanComments** (default: true)
  - Detects regular comments `<!-- ... -->`
  - **Excludes legal/license comments** `<!--! ... -->` (e.g., Font Awesome licenses)
  - Legal comments are always preserved by both PHP and SVGO optimizers

- **scanInlineStyles** (default: true)
  - Detects inline `style` attributes with convertible properties
  - Only flags styles that can be converted to SVG attributes (fill, stroke, etc.)
  - **Preserves CSS-only properties** like `isolation`, `mix-blend-mode`, `transform`, `filter`
  - Example: `style="fill:#000"` → flagged (can be `fill="#000"`)
  - Example: `style="isolation:isolate"` → not flagged (CSS-only, must stay in style)

- **scanLargeFiles** (default: true)
  - Detects files over 10KB
  - **Warning only** - large file size may be normal for complex icons
  - Useful for identifying icons that might benefit from optimization

- **scanWidthHeight** (default: true)
  - Detects width/height attributes **without viewBox** on `<svg>` tag
  - **Critical issue** - SVG won't scale responsively without viewBox
  - Example: `<svg width="24" height="24">` → flagged (missing viewBox)
  - Example: `<svg width="24" height="24" viewBox="0 0 24 24">` → not flagged

- **scanWidthHeightWithViewBox** (default: false)
  - Detects width/height even when viewBox exists
  - **Optional optimization** - some prefer viewBox-only for maximum flexibility
  - When enabled: `<svg width="24" height="24" viewBox="0 0 24 24">` → flagged
  - When disabled: Only flags width/height **without** viewBox (critical issues only)

**Important:** SVGO ignores these settings. Configure SVGO separately via `svgo.config.js` to control what it optimizes.

#### Logging Settings

- **logLevel**: Logging verbosity level
  - `error` - Critical errors only (default, production recommended)
  - `warning` - Errors and warnings
  - `info` - General information
  - `debug` - Detailed debugging with performance metrics (development only, requires devMode)

#### Icon Type Settings

- **enabledIconTypes**: Enable/disable specific icon set types
  - `svg-folder` - SVG files in folders (stable, production-ready)
  - `svg-sprite` - SVG sprite files (beta)
  - `font-awesome` - Font Awesome icons (beta)
  - `material-icons` - Material Icons and Material Symbols (beta)
  - `web-font` - Custom web fonts with glyph extraction (beta)

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

## SVG Security

Icon Manager automatically sanitizes all SVG content at render time to prevent XSS attacks and malicious code execution.

### What Gets Sanitized

The following are automatically removed from SVG output:

- `<script>` tags
- Event handlers (onclick, onload, onmouseover, etc.)
- `javascript:` protocols in hrefs
- Data URLs with script content
- External script references

### Implementation Details

Sanitization is implemented in the `Icon` model and occurs when rendering:

```php
// In src/models/Icon.php
private function sanitizeSvg(?string $svg): ?string
{
    // Removes scripts, event handlers, and malicious protocols
    // Original files remain unchanged on disk
}
```

### Important Notes

1. **Runtime Sanitization**: Sanitization happens at render time, not when files are uploaded or saved
2. **Source Preservation**: Original SVG files on disk remain unchanged
3. **No Manual Filtering**: You don't need to use `|raw` filter in templates - output is automatically marked safe
4. **Visual Elements Preserved**: Clip-paths, masks, filters, and other visual elements are NOT removed as they don't pose security risks

### Security Best Practices

While Icon Manager sanitizes output, follow these practices for enhanced security:

1. **Trusted Sources**: Only use icons from trusted sources
2. **File Permissions**: Restrict write access to icon directories
3. **Regular Updates**: Keep the plugin updated for latest security fixes
4. **Content Auditing**: Periodically review icon sets for suspicious content

### Comparison with Other Solutions

Unlike some icon plugins that output SVG content raw without sanitization, Icon Manager provides:

- **Automatic Protection**: No manual sanitization required
- **Preserved Design**: Visual elements aren't stripped
- **Template Safety**: No need for `|raw` filter management
- **Source Integrity**: Original files remain untouched

## Logging Configuration

### Log Levels and Performance

Choose the appropriate log level for your environment:

- **Production**: Use `warning` or `error` to minimize log volume
- **Staging**: Use `info` for operational visibility
- **Development**: Use `debug` for debugging and performance analysis

### Log File Management

- **Location**: `storage/logs/icon-manager-YYYY-MM-DD.log`
- **Retention**: 30 days automatic cleanup
- **File Size**: Includes file size display in CP interface
- **Web Interface**: Access via Icon Manager → Logs in Control Panel

### Environment-Specific Logging

```php
'dev' => [
    'logLevel' => 'debug', // Full debugging
],
'staging' => [
    'logLevel' => 'info', // Operations monitoring
],
'production' => [
    'logLevel' => 'warning', // Errors and warnings only
],
```

### Performance Impact

- **debug**: High verbosity, includes cache operations and timing
- **info**: Moderate verbosity, normal operations
- **warning**: Low verbosity, problems only
- **error**: Minimal verbosity, critical issues only

## SVG Optimization Configuration

Icon Manager supports SVGO for advanced SVG optimization. See the [SVG Optimization section in README](../README.md#svg-optimization) for full details.

### SVGO Configuration File

Create a `svgo.config.js` file in your project root for custom optimization settings:

```javascript
export default {
    plugins: [
        {
            name: 'preset-default',
            params: {
                overrides: {
                    convertColors: false,  // Preserve colors for CSS control
                    mergePaths: false,     // Keep paths separate for animations
                    removeViewBox: false,  // Keep viewBox for responsive sizing
                },
            },
        },
        'removeDimensions',        // Remove fixed width/height
        'removeEmptyContainers',   // Clean up empty groups
        'removeEditorsNSData',     // Remove editor metadata (Figma, Sketch, etc.)
    ],
};
```

### Optimization Presets

If no `svgo.config.js` file exists, Icon Manager offers built-in presets:

1. **Safe** - Removes only metadata and comments, preserves all visual elements
2. **Balanced** - Safe + cleanup IDs and remove hidden elements
3. **Aggressive** - Balanced + merge paths and convert colors (may affect dynamic styling)
4. **Default** - Use SVGO's default optimization settings

In direct command mode (with `--set` flag), the Safe preset is automatically used if no config file is found.

### Issue Detection Configuration

The plugin automatically detects optimization opportunities:

**Flagged as Issues:**
- Empty or unused clip-paths and masks
- Comments and metadata
- Inline styles that prevent CSS overrides
- Width/height attributes (should use viewBox)

**Flagged as Warnings:**
- Large files (>10KB) - may be normal for complex icons

**Not Flagged:**
- Functional clip-paths and masks that are actually used
- ViewBox attributes (these are desirable)

No configuration needed - detection is automatic and intelligent.

## Icon Metadata Configuration

You can add `metadata.json` files to your icon folders to provide enhanced metadata like keywords, labels, and multilingual support.

See the [Icon Metadata section in the README](../README.md#icon-metadata) for:
- Basic metadata structure with keywords
- Advanced structure with multilingual labels
- Label resolution priority
- Supported metadata properties
- Complete examples