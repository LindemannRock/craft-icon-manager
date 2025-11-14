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

    // Scan Controls (what scanner detects and reports in UI)
    'scanClipPaths' => true,              // Detect empty/unused clip-paths
    'scanMasks' => true,                  // Detect empty/unused masks
    'scanFilters' => true,                // Detect filter effects
    'scanComments' => true,               // Detect comments (legal <!--! --> preserved)
    'scanInlineStyles' => true,           // Detect convertible inline styles
    'scanLargeFiles' => false,            // Detect files >10KB (informational)
    'scanWidthHeight' => true,            // Detect width/height without viewBox
    'scanWidthHeightWithViewBox' => false, // Detect width/height with viewBox

    // PHP Optimizer rules (what to apply during optimization)
    'optimizeConvertColorsToHex' => true,
    'optimizeConvertCssClasses' => true,
    'optimizeConvertEmptyTags' => true,
    'optimizeConvertInlineStyles' => true,
    'optimizeMinifyCoordinates' => true,
    'optimizeMinifyTransformations' => true,
    'optimizeRemoveComments' => true,
    'optimizeRemoveDefaultAttributes' => true,
    'optimizeRemoveDeprecatedAttributes' => true,
    'optimizeRemoveDoctype' => true,
    'optimizeRemoveEnableBackground' => true,
    'optimizeRemoveEmptyAttributes' => true,
    'optimizeRemoveInkscapeFootprints' => true,
    'optimizeRemoveInvisibleCharacters' => true,
    'optimizeRemoveMetadata' => true,
    'optimizeRemoveWhitespace' => true,
    'optimizeRemoveUnusedNamespaces' => true,
    'optimizeRemoveUnusedMasks' => true,
    'optimizeRemoveWidthHeight' => true,
    'optimizeFlattenGroups' => true,
    'optimizeSortAttributes' => true,

    // Logging settings
    'logLevel' => 'error', // error, warning, info, debug

    // UI settings
    'itemsPerPage' => 100, // Items per page in CP (10-500)

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

        // Scan Controls (what to detect and report in UI)
        'scanClipPaths' => true,
        'scanMasks' => true,
        'scanFilters' => true,
        'scanComments' => true,
        'scanInlineStyles' => true,
        'scanLargeFiles' => false,
        'scanWidthHeight' => true,
        'scanWidthHeightWithViewBox' => false,

        // PHP Optimizer rules (what to apply - all 21 rules)
        'optimizeConvertColorsToHex' => true,
        'optimizeConvertCssClasses' => true,
        'optimizeConvertEmptyTags' => true,
        'optimizeConvertInlineStyles' => true,
        'optimizeMinifyCoordinates' => true,
        'optimizeMinifyTransformations' => true,
        'optimizeRemoveComments' => true,
        'optimizeRemoveDefaultAttributes' => true,
        'optimizeRemoveDeprecatedAttributes' => true,
        'optimizeRemoveDoctype' => true,
        'optimizeRemoveEnableBackground' => true,
        'optimizeRemoveEmptyAttributes' => true,
        'optimizeRemoveInkscapeFootprints' => true,
        'optimizeRemoveInvisibleCharacters' => true,
        'optimizeRemoveMetadata' => true,
        'optimizeRemoveWhitespace' => true,
        'optimizeRemoveUnusedNamespaces' => true,
        'optimizeRemoveUnusedMasks' => true,
        'optimizeRemoveWidthHeight' => true,
        'optimizeFlattenGroups' => true,
        'optimizeSortAttributes' => true,
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
use craft\helpers\App;

return [
    'enableCache' => (bool)App::env('ICON_MANAGER_CACHE') ?: true,
    'cacheDuration' => (int)App::env('ICON_CACHE_DURATION') ?: 86400,
    'iconSetsPath' => App::env('ICON_SETS_PATH') ?: '@root/icons',
    'logLevel' => App::env('ICON_LOG_LEVEL') ?: 'error',
];
```

**Important:**
- ✅ Use `App::env('VAR_NAME')` - Craft 5 recommended approach
- ❌ Don't use `getenv('VAR_NAME')` - Not thread-safe
- ✅ Always import: `use craft\helpers\App;`

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

#### Scan Control Settings (Detection Only)

**Purpose:** Control what issues to detect and display in scan reports. These are for **UI reporting only** and do NOT control what gets optimized.

**Location:** Settings → SVG Optimization → Scan Controls tab

- **scanClipPaths** (default: true) - Detect empty or unused clip-path definitions
- **scanMasks** (default: true) - Detect empty or unused mask definitions
- **scanFilters** (default: true) - Detect filter effects (informational)
- **scanComments** (default: true) - Detect comments (legal `<!--! -->` preserved)
- **scanInlineStyles** (default: true) - Detect convertible inline styles
- **scanLargeFiles** (default: false) - Detect files >10KB (informational, use as file size guide)
- **scanWidthHeight** (default: true) - Detect width/height without viewBox (critical)
- **scanWidthHeightWithViewBox** (default: false) - Detect width/height with viewBox (optional)

**Note:** Scan results show in the "Issues Found" and "Issue Breakdown" sections of the Optimization tab.

#### PHP Optimizer Settings (What Gets Applied)

**Purpose:** Control which optimization rules are actually applied to files when using the PHP optimizer.

**Location:** Settings → SVG Optimization → PHP Optimizer tab

**All 21 available rules** (organized by category):

**Conversion Rules (4):**
- `optimizeConvertColorsToHex` - Convert colors to hexadecimal format
- `optimizeConvertCssClasses` - Convert CSS classes to SVG attributes (77 properties)
- `optimizeConvertEmptyTags` - Convert empty tags to self-closing format
- `optimizeConvertInlineStyles` - Convert inline styles to SVG attributes (77 properties)

**Minification Rules (2):**
- `optimizeMinifyCoordinates` - Reduce coordinate precision
- `optimizeMinifyTransformations` - Optimize transformation matrices

**Removal Rules (13):**
- `optimizeRemoveComments` - Remove comments (legal `<!--! -->` preserved)
- `optimizeRemoveDefaultAttributes` - Remove default-value attributes
- `optimizeRemoveDeprecatedAttributes` - Remove deprecated attributes
- `optimizeRemoveDoctype` - Remove DOCTYPE declarations
- `optimizeRemoveEnableBackground` - Remove deprecated enable-background
- `optimizeRemoveEmptyAttributes` - Remove empty attributes
- `optimizeRemoveInkscapeFootprints` - Remove Inkscape-specific elements
- `optimizeRemoveInvisibleCharacters` - Remove invisible/non-printable chars
- `optimizeRemoveMetadata` - Remove metadata elements
- `optimizeRemoveWhitespace` - Remove unnecessary whitespace
- `optimizeRemoveUnusedNamespaces` - Remove unused XML namespaces
- `optimizeRemoveUnusedMasks` - Remove unreferenced mask definitions
- `optimizeRemoveWidthHeight` - Remove width/height (keeps viewBox)

**Structure Rules (2):**
- `optimizeFlattenGroups` - Remove unnecessary `<g>` wrappers
- `optimizeSortAttributes` - Sort attributes alphabetically

**Default:** All rules enabled for comprehensive optimization. Toggle any off to skip that rule.

**Important:**
- Scan Controls and PHP Optimizer Settings are **separate and independent**
- Scan controls what you **see** in reports
- PHP Optimizer controls what **gets modified** in files
- Optimization may apply even when scan shows 0 issues (applies all enabled rules)
- SVGO ignores these settings - uses `svgo.config.js` instead

#### Logging Settings

- **logLevel**: Logging verbosity level
  - `error` - Critical errors only (default, production recommended)
  - `warning` - Errors and warnings
  - `info` - General information
  - `debug` - Detailed debugging with performance metrics (development only, requires devMode)

#### UI Settings

- **itemsPerPage** (default: 100)
  - Number of icon sets displayed per page in the Control Panel Icon Sets index
  - Minimum: 10, Maximum: 500
  - Configurable per environment for optimal performance
  - Can be overridden via config file to prevent CP modifications

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
use craft\helpers\App;

// Use environment variables for sensitive paths
'iconSetsPath' => App::env('ICON_SETS_PATH') ?: '@root/icons',

// Limit cache duration in shared environments
'cacheDuration' => min((int)App::env('CACHE_DURATION') ?: 86400, 604800), // Max 7 days
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