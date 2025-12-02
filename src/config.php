<?php
/**
 * Icon Manager config.php
 *
 * This file exists only as a template for the Icon Manager settings.
 * It does nothing on its own.
 *
 * Don't edit this file, instead copy it to 'craft/config' as 'icon-manager.php'
 * and make your changes there to override default settings.
 *
 * Once copied to 'craft/config', this file will be multi-environment aware as
 * well, so you can have different settings groups for each environment, just as
 * you do for 'general.php'
 */


return [
    // Global settings
    '*' => [
        // ========================================
        // GENERAL SETTINGS
        // ========================================
        // Basic plugin configuration and paths

        'pluginName' => 'Icon Manager',
        'iconSetsPath' => '@root/src/icons',
        'logLevel' => 'error',             // Options: 'debug', 'info', 'warning', 'error'


        // ========================================
        // ICON TYPES
        // ========================================
        // Enable/disable specific icon set types

        'enabledIconTypes' => [
            'svg-folder' => true,
            'svg-sprite' => true,
            'font-awesome' => false,
            'material-icons' => false,
            'web-font' => false,
        ],


        // ========================================
        // SVG OPTIMIZATION
        // ========================================
        // SVG optimization and scan control settings

        'enableOptimization' => true,
        'enableOptimizationBackup' => true,

        // Scan control settings (what to detect during optimization scans)
        'scanClipPaths' => true,              // Scan for unused clip-paths
        'scanMasks' => true,                  // Scan for unused masks
        'scanFilters' => true,                // Scan for filters
        'scanComments' => true,               // Scan for comments
        'scanInlineStyles' => true,           // Scan for inline styles
        'scanLargeFiles' => true,             // Scan for large files (>10KB)
        'scanWidthHeight' => true,            // Scan for width/height without viewBox
        'scanWidthHeightWithViewBox' => false, // Scan for width/height even with viewBox

        // PHP Optimizer rules (what to apply during optimization)
        // Conversion rules
        'optimizeConvertColorsToHex' => true,
        'optimizeConvertCssClasses' => true,
        'optimizeConvertEmptyTags' => true,
        'optimizeConvertInlineStyles' => true,

        // Minification rules
        'optimizeMinifyCoordinates' => true,
        'optimizeMinifyTransformations' => true,

        // Removal rules
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

        // Structure rules
        'optimizeFlattenGroups' => true,
        'optimizeSortAttributes' => true,


        // ========================================
        // INTERFACE SETTINGS
        // ========================================
        // Control panel interface options

        'itemsPerPage' => 100,                // Items per page in CP (10-500)


        // ========================================
        // CACHE SETTINGS
        // ========================================
        // Icon caching configuration

        'enableCache' => true,
        'cacheDuration' => 86400,             // 24 hours
    ],

    // Dev environment settings
    'dev' => [
        'iconSetsPath' => '@root/src/icons',
        'logLevel' => 'info',                 // More detailed logging in development
        'enabledIconTypes' => [
            'svg-folder' => true,
            'svg-sprite' => true,
            'font-awesome' => true,
            'material-icons' => true,
            'web-font' => true,
        ],
        'enableCache' => true,
        'cacheDuration' => 3600,              // 1 hour
    ],

    // Staging environment settings
    'staging' => [
        'iconSetsPath' => '@webroot/dist/assets/icons',
        'logLevel' => 'warning',              // Moderate logging for staging
        'enableCache' => true,
        'cacheDuration' => 86400,             // 1 day
    ],

    // Production environment settings
    'production' => [
        'iconSetsPath' => '@webroot/dist/assets/icons',
        'logLevel' => 'error',                // Minimal logging in production
        'enabledIconTypes' => [
            'svg-folder' => true,
            'svg-sprite' => false,            // Beta
            'font-awesome' => false,          // Beta
            'material-icons' => false,        // Beta
            'web-font' => false,              // Beta
        ],
        'enableCache' => true,
        'cacheDuration' => 2592000,           // 30 days
    ],
];
