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
    // The path to icon sets folder
    'iconSetsPath' => '@root/icons',

    // Whether to enable icon caching
    'enableCache' => true,

    // Cache duration in seconds
    'cacheDuration' => 86400,

    // Default icon set types to enable
    'enabledIconTypes' => [
        'svg-folder' => true,
        'svg-sprite' => true,
        'font-awesome' => true,
        'material-icons' => false,
    ],

    // Maximum icons to display per page in picker
    'iconsPerPage' => 100,

    // Show icon labels in picker
    'showLabels' => true,

    // Icon preview size in picker (small, medium, large)
    'iconSize' => 'medium',
];