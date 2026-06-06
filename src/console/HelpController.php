<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\iconmanager\console;

use lindemannrock\base\console\controllers\AbstractHelpController;

/**
 * Console help for Icon Manager commands.
 *
 * @since 5.15.0
 */
final class HelpController extends AbstractHelpController
{
    /**
     * @inheritdoc
     */
    protected function helpManifest(): array
    {
        return [
            'title' => 'Icon Manager',
            'pluginHandle' => 'icon-manager',
            'commandPrefixes' => [
                'php craft',
                'ddev craft',
            ],
            'summary' => 'Use these commands to optimize SVG folder icon sets, check SVGO availability, and verify optimizer rules against fixture SVG files.',
            'common' => [
                'optimize',
                'optimize/check',
                'optimize/verify',
            ],
            'groups' => [
                [
                    'name' => 'optimize',
                    'label' => 'SVG Optimization',
                    'description' => 'Optimize SVG folder icon sets and verify optimizer tooling.',
                    'commands' => [
                        [
                            'path' => 'optimize',
                            'summary' => 'Optimize one SVG folder icon set.',
                            'description' => 'Run SVG optimization for an SVG folder icon set. Omit --set to use the interactive picker for the icon set, engine, preset, and backup choice.',
                            'usageOptions' => '[--set=<id>] [--engine=<php|svgo>] [--config=<path>] [--dry-run] [--no-backup]',
                            'options' => [
                                [
                                    'name' => '--set',
                                    'description' => 'Icon set ID. Omit for interactive mode.',
                                ],
                                [
                                    'name' => '--engine',
                                    'description' => 'php or svgo. Default: php.',
                                ],
                                [
                                    'name' => '--config',
                                    'description' => 'SVGO config file path when using --engine=svgo.',
                                ],
                                [
                                    'name' => '--dry-run',
                                    'description' => 'Show what would be optimized without writing changes.',
                                ],
                                [
                                    'name' => '--no-backup',
                                    'description' => 'Skip automatic backup creation before writing optimized SVGs.',
                                ],
                            ],
                            'examples' => [
                                'icon-manager/optimize',
                                'icon-manager/optimize --set=3 --engine=php --dry-run',
                                'icon-manager/optimize --set=3 --engine=svgo --config=svgo.config.mjs',
                            ],
                            'notes' => [
                                'Only SVG folder icon sets can be optimized.',
                                'Automatic backups are stored under Craft runtime storage unless --no-backup is used.',
                                'Use --dry-run first when optimizing a production icon library or unfamiliar SVG set.',
                            ],
                        ],
                        [
                            'path' => 'optimize/check',
                            'summary' => 'Check whether SVGO is available.',
                            'description' => 'Detect the SVGO executable and the active SVGO config file so you can confirm the Node-based optimizer is ready before using --engine=svgo.',
                            'examples' => [
                                'icon-manager/optimize/check',
                            ],
                            'notes' => [
                                'SVGO is only needed for the svgo engine. The default php engine does not require Node.js.',
                            ],
                        ],
                        [
                            'path' => 'optimize/verify',
                            'summary' => 'Verify optimizer rules against SVG fixtures.',
                            'description' => 'Run PHP optimizer rules or SVGO against one SVG file or a directory of SVG files. This validates parser and structural output, not pixel-perfect visual equivalence.',
                            'usageOptions' => '--path=<file-or-directory> [--engine=<php|svgo>] [--config=<path>] [--include-risky=<0|1>] [--keep-outputs]',
                            'options' => [
                                [
                                    'name' => '--path',
                                    'description' => 'SVG file or directory to verify.',
                                    'required' => true,
                                ],
                                [
                                    'name' => '--engine',
                                    'description' => 'php or svgo. Default: php.',
                                ],
                                [
                                    'name' => '--config',
                                    'description' => 'SVGO config file path when using --engine=svgo.',
                                ],
                                [
                                    'name' => '--include-risky',
                                    'description' => 'Include risky PHP optimizer rules. Default: 1.',
                                ],
                                [
                                    'name' => '--keep-outputs',
                                    'description' => 'Write optimized verification outputs to runtime storage for inspection.',
                                ],
                            ],
                            'examples' => [
                                'icon-manager/optimize/verify --path=path/to/svg-fixtures',
                                'icon-manager/optimize/verify --engine=php --path=path/to/svg-fixtures --include-risky=0',
                                'icon-manager/optimize/verify --engine=svgo --path=path/to/svg-fixtures --config=svgo.config.mjs --keep-outputs=1',
                            ],
                            'notes' => [
                                'Verification outputs are written to storage/runtime/icon-manager/verify/ when --keep-outputs is enabled.',
                                'After dependency upgrades, run representative fixtures and spot-check important icons visually.',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
