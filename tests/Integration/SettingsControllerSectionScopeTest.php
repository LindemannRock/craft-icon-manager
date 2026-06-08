<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\iconmanager\tests\Integration;

use lindemannrock\iconmanager\controllers\SettingsController;
use lindemannrock\iconmanager\IconManager;
use lindemannrock\iconmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @since 5.15.0
 */
#[CoversClass(SettingsController::class)]
final class SettingsControllerSectionScopeTest extends TestCase
{
    public function testSettingsSectionsMatchRenderedFormScopes(): void
    {
        $controller = new SettingsController('settings', IconManager::$plugin);
        $method = new \ReflectionMethod($controller, '_validationAttributesForSection');

        $expected = [
            'general' => [
                'pluginName',
                'iconSetsPath',
                'logLevel',
            ],
            'icon-types' => [
                'enabledIconTypes',
            ],
            'svg-optimization' => [
                'enableOptimization',
                'enableOptimizationBackup',
                'backupVolumeUid',
                'backupPath',
                'scanClipPaths',
                'scanMasks',
                'scanFilters',
                'scanComments',
                'scanInlineStyles',
                'scanLargeFiles',
                'scanWidthHeight',
                'scanWidthHeightWithViewBox',
                'optimizeConvertColorsToHex',
                'optimizeConvertCssClasses',
                'optimizeConvertEmptyTags',
                'optimizeConvertInlineStyles',
                'optimizeFlattenGroups',
                'optimizeMinifyCoordinates',
                'optimizeMinifyTransformations',
                'optimizeRemoveComments',
                'optimizeRemoveDefaultAttributes',
                'optimizeRemoveDeprecatedAttributes',
                'optimizeRemoveDoctype',
                'optimizeRemoveEnableBackground',
                'optimizeRemoveEmptyAttributes',
                'optimizeRemoveInkscapeFootprints',
                'optimizeRemoveInvisibleCharacters',
                'optimizeRemoveMetadata',
                'optimizeRemoveWhitespace',
                'optimizeRemoveUnusedNamespaces',
                'optimizeRemoveUnusedMasks',
                'optimizeRemoveWidthHeight',
                'optimizeSortAttributes',
                'optimizeFixAttributeNames',
                'optimizeRemoveAriaAndRole',
                'optimizeRemoveDataAttributes',
                'optimizeRemoveDuplicateElements',
                'optimizeRemoveEmptyGroups',
                'optimizeRemoveEmptyTextElements',
                'optimizeRemoveNonStandardAttributes',
                'optimizeRemoveNonStandardTags',
                'optimizeRemoveTitleAndDesc',
                'optimizeRemoveUnsafeElements',
                'optimizeScopeSvgStyles',
                'optimizeAllowRiskyRules',
            ],
            'interface' => [
                'itemsPerPage',
                'timeFormat',
                'monthFormat',
                'dateOrder',
                'dateSeparator',
                'showSeconds',
            ],
            'cache' => [
                'cacheStorageMethod',
                'enableCache',
                'cacheDuration',
            ],
        ];

        foreach ($expected as $section => $attributes) {
            self::assertSame($attributes, $method->invoke($controller, $section), "Unexpected {$section} settings scope.");
        }
    }
}
