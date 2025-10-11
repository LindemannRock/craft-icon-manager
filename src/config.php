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

use craft\helpers\App;

return [
    // Global settings
    '*' => [
        // Plugin display name
        'pluginName' => 'Icon Manager',

        // Default icons path
        'iconSetsPath' => '@root/src/icons',

        // Whether to enable icon caching
        'enableCache' => true,

        // Cache duration in seconds
        'cacheDuration' => 86400, // 24 hours

        // Default icon set types to enable
        'enabledIconTypes' => [
            'svg-folder' => true,
            'svg-sprite' => true,
            'font-awesome' => false,
            'material-icons' => false,
            'web-font' => false,
        ],

        // SVG Optimization settings
        'enableOptimization' => true,
        'enableOptimizationBackup' => true,

        // Scan control settings (what to check for during optimization scans)
        'scanClipPaths' => true,              // Scan for unused clip-paths
        'scanMasks' => true,                  // Scan for unused masks
        'scanFilters' => true,                // Scan for filters
        'scanComments' => true,               // Scan for comments
        'scanInlineStyles' => true,           // Scan for inline styles
        'scanLargeFiles' => true,             // Scan for large files (>10KB)
        'scanWidthHeight' => true,            // Scan for width/height without viewBox
        'scanWidthHeightWithViewBox' => false, // Scan for width/height even with viewBox

        // Logging settings
        'logLevel' => 'error', // Options: 'debug', 'info', 'warning', 'error'
    ],

    // Dev environment settings
    'dev' => [
        // Use source icons in dev
        'iconSetsPath' => '@root/src/icons',

        // Enable caching in dev for performance
        'enableCache' => true,
        'cacheDuration' => 3600, // 1 hour

        // Allow all icon types for testing
        'enabledIconTypes' => [
            'svg-folder' => true,
            'svg-sprite' => true,
            'font-awesome' => true,
            'material-icons' => true,
            'web-font' => true,
        ],

        // More detailed logging in development
        'logLevel' => 'info',
    ],

    // Staging environment settings
    'staging' => [
        // Production-ready icons path
        'iconSetsPath' => '@webroot/dist/assets/icons',

        // Optimize for staging
        'enableCache' => true,
        'cacheDuration' => 86400, // 1 day

        // Moderate logging for staging
        'logLevel' => 'warning',
    ],

    // Production environment settings
    'production' => [
        // Production icons path
        'iconSetsPath' => '@webroot/dist/assets/icons',

        // Optimize for production
        'enableCache' => true,
        'cacheDuration' => 2592000, // 30 days

        // Only stable icon types in production
        'enabledIconTypes' => [
            'svg-folder' => true,
            'svg-sprite' => false, // Beta
            'font-awesome' => false, // Beta
            'material-icons' => false, // Beta
            'web-font' => false, // Beta
        ],

        // Minimal logging in production
        'logLevel' => 'error',
    ],
];
