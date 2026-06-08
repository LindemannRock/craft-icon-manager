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
 *
 * @since 1.0.0
 */


return [
    // Global settings
    '*' => [
        // ========================================
        // GENERAL SETTINGS
        // ========================================
        // Basic plugin configuration and paths

        'pluginName' => 'Icon Manager',
        'iconSetsPath' => '@root/icons',
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

        // Where optimization backups are stored. Use a Craft path alias
        // (@storage recommended — it survives cache clears). Leave 'backupVolumeUid'
        // empty to use 'backupPath'; set it to an asset volume UID to store
        // backups in that volume instead.
        'backupPath' => '@storage/icon-manager/backups',
        'backupVolumeUid' => null,

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
        // Risky rules are grouped in the CP and only applied when explicitly allowed.
        'optimizeAllowRiskyRules' => true,

        // Conversion rules
        'optimizeConvertColorsToHex' => true,
        'optimizeConvertCssClasses' => true,
        'optimizeConvertEmptyTags' => true,
        'optimizeConvertInlineStyles' => true,
        'optimizeFixAttributeNames' => true,

        // Minification rules
        'optimizeMinifyCoordinates' => true,
        'optimizeMinifyTransformations' => true,

        // Removal rules
        'optimizeRemoveAriaAndRole' => false,
        'optimizeRemoveComments' => true,
        'optimizeRemoveDataAttributes' => false,
        'optimizeRemoveDefaultAttributes' => true,
        'optimizeRemoveDeprecatedAttributes' => true,
        'optimizeRemoveDoctype' => true,
        'optimizeRemoveDuplicateElements' => true,
        'optimizeRemoveEmptyGroups' => true,
        'optimizeRemoveEnableBackground' => true,
        'optimizeRemoveEmptyAttributes' => true,
        'optimizeRemoveEmptyTextElements' => true,
        'optimizeRemoveInkscapeFootprints' => true,
        'optimizeRemoveInvisibleCharacters' => true,
        'optimizeRemoveMetadata' => true,
        'optimizeRemoveNonStandardAttributes' => false,
        'optimizeRemoveNonStandardTags' => false,
        'optimizeRemoveTitleAndDesc' => false,
        'optimizeRemoveUnsafeElements' => true,
        'optimizeRemoveWhitespace' => true,
        'optimizeRemoveUnusedNamespaces' => true,
        'optimizeRemoveUnusedMasks' => true,
        'optimizeRemoveWidthHeight' => true,

        // Structure rules
        'optimizeFlattenGroups' => true,
        'optimizeScopeSvgStyles' => false,
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

        // Cache Storage Method
        // 'file' = File system (default, single server)
        // 'redis' = Redis/Database (load-balanced, multi-server, cloud hosting)
        'cacheStorageMethod' => 'file',

        'enableCache' => true,
        'cacheDuration' => 86400,             // 24 hours


        // ========================================
        // BASE PLUGIN OVERRIDES
        // ========================================
        // These settings override lindemannrock-base defaults for this plugin only.
        // Global defaults: config/lindemannrock-base.php
        // To customize globally: copy to config/lindemannrock-base.php

        /**
         * Date/time formatting overrides
         * Override base plugin date/time display settings for this plugin
         * Defaults: from config/lindemannrock-base.php
         */
        // 'timeFormat' => '24',      // '12' (AM/PM) or '24' (military)
        // 'monthFormat' => 'short',  // 'numeric' (01), 'short' (Jan), 'long' (January)
        // 'dateOrder' => 'dmy',      // 'dmy', 'mdy', 'ymd'
        // 'dateSeparator' => '/',    // '/', '-', '.'
        // 'showSeconds' => false,    // Show seconds in time display
    ],

    // Dev environment settings
    'dev' => [
        'iconSetsPath' => '@root/icons',
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
        'cacheStorageMethod' => 'redis',      // Use Redis for production (Servd/AWS/Platform.sh)
        'enableCache' => true,
        'cacheDuration' => 2592000,           // 30 days
    ],
];
