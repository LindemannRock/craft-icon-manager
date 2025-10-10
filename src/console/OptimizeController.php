<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\iconmanager\console;

use lindemannrock\iconmanager\IconManager;
use lindemannrock\iconmanager\services\SvgoService;
use craft\console\Controller;
use craft\helpers\Console;
use yii\console\ExitCode;

/**
 * Optimize SVG icons using various optimization engines
 */
class OptimizeController extends Controller
{
    /**
     * @var string The optimization engine to use (php or svgo)
     */
    public $engine = 'php';

    /**
     * @var int The icon set ID to optimize
     */
    public $set;

    /**
     * @var string Path to SVGO config file
     */
    public $config;

    /**
     * @var bool Dry run - show what would be optimized without making changes
     */
    public $dryRun = false;

    /**
     * @var bool Skip backup creation
     */
    public $noBackup = false;

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);
        $options[] = 'engine';
        $options[] = 'set';
        $options[] = 'config';
        $options[] = 'dryRun';
        $options[] = 'noBackup';
        return $options;
    }

    /**
     * Optimize SVG icons in an icon set
     *
     * @return int
     */
    public function actionIndex(): int
    {
        // Check if optimization is enabled
        if (!IconManager::getInstance()->getSettings()->enableOptimization) {
            $this->stdout("\n");
            $this->stderr("✗ SVG Optimization is disabled\n\n", Console::FG_RED);
            $this->stdout("To enable optimization:\n\n");
            $this->stdout("1. Via Settings:\n");
            $this->stdout("   Go to Icon Manager → Settings → SVG Optimization\n");
            $this->stdout("   Enable 'Enable Optimization'\n\n");
            $this->stdout("2. Via Config File:\n");
            $this->stdout("   Edit config/icon-manager.php and set:\n");
            $this->stdout("   'enableOptimization' => true\n\n", Console::FG_GREEN);
            return ExitCode::CONFIG;
        }

        // Interactive mode if no --set provided
        if (!$this->set) {
            return $this->interactiveMode();
        }

        // Get icon set
        $iconSet = IconManager::getInstance()->iconSets->getIconSetById($this->set);
        if (!$iconSet) {
            $this->stderr("Error: Icon set #{$this->set} not found\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        // Only SVG folder icon sets can be optimized
        if ($iconSet->type !== 'svg-folder') {
            $this->stderr("Error: Only SVG folder icon sets can be optimized\n", Console::FG_RED);
            $this->stdout("Icon set '{$iconSet->name}' is type: {$iconSet->type}\n");
            return ExitCode::DATAERR;
        }

        $this->stdout("\n");
        $this->stdout("Icon Manager - SVG Optimization\n", Console::FG_CYAN);
        $this->stdout("================================\n\n");
        $this->stdout("Icon Set: ", Console::FG_YELLOW);
        $this->stdout("{$iconSet->name} (#{$iconSet->id})\n");
        $this->stdout("Engine:   ", Console::FG_YELLOW);
        $this->stdout("{$this->engine}\n");

        if ($this->dryRun) {
            $this->stdout("Mode:     ", Console::FG_YELLOW);
            $this->stdout("Dry run (no changes will be made)\n", Console::FG_GREY);
        }

        $this->stdout("\n");

        // Handle based on engine
        if ($this->engine === 'svgo') {
            return $this->optimizeWithSvgo($iconSet);
        } elseif ($this->engine === 'php') {
            return $this->optimizeWithPhp($iconSet);
        } else {
            $this->stderr("Error: Unknown engine '{$this->engine}'\n", Console::FG_RED);
            $this->stdout("Available engines: php, svgo\n");
            return ExitCode::USAGE;
        }
    }

    /**
     * Interactive mode - prompts for icon set and engine selection
     */
    private function interactiveMode(): int
    {
        $this->stdout("\n");
        $this->stdout("Icon Manager - SVG Optimization\n", Console::FG_CYAN);
        $this->stdout("================================\n\n");

        // Get all SVG folder icon sets
        $allIconSets = IconManager::getInstance()->iconSets->getAllIconSets();
        $svgFolderSets = array_filter($allIconSets, function($set) {
            return $set->type === 'svg-folder' && $set->enabled;
        });

        if (empty($svgFolderSets)) {
            $this->stderr("No SVG folder icon sets found.\n", Console::FG_RED);
            $this->stdout("\nCreate an SVG folder icon set first in the Control Panel.\n");
            return ExitCode::DATAERR;
        }

        // Show available icon sets
        $this->stdout("Available Icon Sets:\n\n", Console::FG_YELLOW);

        $choices = [];
        $index = 1;
        foreach ($svgFolderSets as $set) {
            $this->stdout("  [{$index}] ", Console::FG_GREY);
            $this->stdout("{$set->name}", Console::FG_GREEN);
            $this->stdout(" (ID: {$set->id})\n");
            $choices[$index] = $set;
            $index++;
        }

        $this->stdout("\n");

        // Prompt for icon set
        $choice = $this->prompt("Select an icon set [1-" . count($choices) . "]:", [
            'required' => true,
            'validator' => function($input) use ($choices) {
                return isset($choices[(int)$input]) ? true : 'Invalid choice';
            },
        ]);

        $selectedSet = $choices[(int)$choice];
        $this->set = $selectedSet->id;

        $this->stdout("\n");
        $this->stdout("Selected: ", Console::FG_YELLOW);
        $this->stdout("{$selectedSet->name}\n\n");

        // Check SVGO availability
        $svgoService = new SvgoService();
        $svgoAvailable = $svgoService->isAvailable();

        // Show engine options
        $this->stdout("Available Engines:\n\n", Console::FG_YELLOW);
        $this->stdout("  [1] ", Console::FG_GREY);
        $this->stdout("PHP Optimizer", Console::FG_GREEN);
        $this->stdout(" (Default, always available)\n");

        if ($svgoAvailable) {
            $this->stdout("  [2] ", Console::FG_GREY);
            $this->stdout("SVGO", Console::FG_GREEN);
            $this->stdout(" (Advanced, Node.js)\n");
        } else {
            $this->stdout("  [2] ", Console::FG_GREY);
            $this->stdout("SVGO", Console::FG_RED);
            $this->stdout(" (Not installed)\n");
        }

        $this->stdout("\n");

        // Prompt for engine
        $engineChoice = $this->prompt("Select optimization engine [1-2]:", [
            'required' => true,
            'default' => '1',
            'validator' => function($input) use ($svgoAvailable) {
                if (!in_array($input, ['1', '2'])) {
                    return 'Invalid choice';
                }
                if ($input === '2' && !$svgoAvailable) {
                    return 'SVGO is not installed. Choose [1] for PHP optimizer.';
                }
                return true;
            },
        ]);

        $this->engine = $engineChoice === '2' ? 'svgo' : 'php';

        $this->stdout("\n");

        // If SVGO selected, ask about configuration
        if ($this->engine === 'svgo' && !$this->config) {
            $svgoService = new SvgoService();
            $existingConfig = $svgoService->getConfigPath();

            if ($existingConfig) {
                $this->stdout("✓ Using project config: ", Console::FG_GREEN);
                $this->stdout($existingConfig . "\n\n");
            } else {
                $this->stdout("No SVGO config found in project root.\n\n", Console::FG_YELLOW);
                $this->stdout("Optimization Presets:\n\n", Console::FG_YELLOW);
                $this->stdout("  [1] ", Console::FG_GREY);
                $this->stdout("Safe", Console::FG_GREEN);
                $this->stdout(" - Remove metadata, comments (preserves visual elements)\n");
                $this->stdout("  [2] ", Console::FG_GREY);
                $this->stdout("Balanced", Console::FG_GREEN);
                $this->stdout(" - Safe + cleanup IDs, remove hidden elements\n");
                $this->stdout("  [3] ", Console::FG_GREY);
                $this->stdout("Aggressive", Console::FG_GREEN);
                $this->stdout(" - Balanced + merge paths, convert colors (may affect styling)\n");
                $this->stdout("  [4] ", Console::FG_GREY);
                $this->stdout("Default", Console::FG_GREEN);
                $this->stdout(" - Use SVGO defaults (no custom config)\n");

                $this->stdout("\n");

                $preset = $this->prompt("Select optimization preset [1-4]:", [
                    'required' => true,
                    'default' => '1',
                    'validator' => function($input) {
                        return in_array($input, ['1', '2', '3', '4']) ? true : 'Invalid choice';
                    },
                ]);

                // Create temporary config based on preset
                if ($preset !== '4') {
                    $this->config = $this->createTempConfig($preset);
                    $this->stdout("\n");
                    $this->stdout("✓ Using temporary config (", Console::FG_GREEN);
                    $this->stdout(["Safe", "Balanced", "Aggressive"][(int)$preset - 1]);
                    $this->stdout(" preset)\n");
                }

                $this->stdout("\n");
            }
        }

        // Ask about backup (unless --noBackup flag is set or dry run)
        if (!$this->noBackup && !$this->dryRun) {
            $backupInput = $this->prompt("Create backup before optimization? (yes|no) [yes]:", [
                'default' => 'yes',
                'validator' => function($input) {
                    $input = strtolower(trim($input));
                    // Accept: yes, y, 1, no, n, 0
                    if (in_array($input, ['yes', 'y', '1', 'no', 'n', '0', ''])) {
                        return true;
                    }
                    return 'Please enter yes, y, 1, no, n, or 0';
                },
            ]);

            $input = strtolower(trim($backupInput));
            $createBackup = in_array($input, ['yes', 'y', '1', '']);

            if (!$createBackup) {
                $this->noBackup = true;
            }
            $this->stdout("\n");
        }

        // Run optimization
        return $this->actionIndex();
    }

    /**
     * Optimize using SVGO
     */
    private function optimizeWithSvgo($iconSet): int
    {
        // Check if SVGO is available
        $svgoService = new SvgoService();

        if (!$svgoService->isAvailable()) {
            $this->stderr("✗ SVGO not found in project\n\n", Console::FG_RED);
            $this->stdout("To use SVGO optimization, install it first:\n\n");

            // Detect package manager
            $projectRoot = \Craft::getAlias('@root');
            $packageManager = $this->detectPackageManager($projectRoot);

            $this->stdout("  ", Console::FG_GREY);

            if ($packageManager === 'pnpm') {
                $this->stdout("pnpm add -D svgo\n", Console::FG_GREEN);
            } elseif ($packageManager === 'yarn') {
                $this->stdout("yarn add --dev svgo\n", Console::FG_GREEN);
            } else {
                $this->stdout("npm install --save-dev svgo\n", Console::FG_GREEN);
            }

            $this->stdout("\n");
            $this->stdout("Then run the command again.\n");
            $this->stdout("\n");
            $this->stdout("Optional: Create a custom svgo.config.js in your project root.\n");
            $this->stdout("See documentation for example configuration.\n\n");

            return ExitCode::UNAVAILABLE;
        }

        $this->stdout("✓ SVGO found: ", Console::FG_GREEN);
        $this->stdout($svgoService->getSvgoPath() . "\n");

        // Determine which config to use
        $configToUse = null;
        if ($this->config) {
            // User provided --config flag
            $configToUse = $this->config;
            $this->stdout("✓ Config:     ", Console::FG_GREEN);
            $this->stdout($configToUse . " (user provided)\n");
        } elseif ($svgoService->getConfigPath()) {
            // Project has svgo.config.js
            $configToUse = $svgoService->getConfigPath();
            $this->stdout("✓ Config:     ", Console::FG_GREEN);
            $this->stdout($configToUse . " (project config)\n");
        } else {
            // No config found - default to Safe preset
            $configToUse = $this->createTempConfig('1');
            $this->stdout("✓ Config:     ", Console::FG_YELLOW);
            $this->stdout("Using Safe preset (no project config found)\n");
        }

        $this->stdout("\n");

        // Run optimization
        if ($this->dryRun) {
            $this->stdout("Dry run mode - no files will be modified\n\n", Console::FG_GREY);
            return ExitCode::OK;
        }

        try {
            $result = $svgoService->optimizeIconSet($iconSet, $configToUse, !$this->noBackup);

            $this->stdout("\n");

            // Check if there were any files to process
            if ($result['total'] === 0) {
                $this->stdout("No SVG files found in this icon set.\n", Console::FG_YELLOW);
                $this->stdout("\nCheck that the icon set path is correct and contains SVG files.\n");
                $this->stdout("\n");
                return ExitCode::OK;
            }

            // Check if everything was already optimized (no changes made)
            if ($result['optimized'] === 0 && $result['errors'] === 0) {
                $this->stdout("✓ All files are already optimized!\n", Console::FG_GREEN);
                $this->stdout("\nNo changes needed.\n");
                $this->stdout("\n");
                return ExitCode::OK;
            }

            $this->stdout("Optimization Complete!\n", Console::FG_GREEN);
            $this->stdout("=====================\n\n");
            $this->stdout("Files processed: ", Console::FG_YELLOW);
            $this->stdout("{$result['total']}\n");
            $this->stdout("Optimized:       ", Console::FG_GREEN);
            $this->stdout("{$result['optimized']}\n");
            $this->stdout("Skipped:         ", Console::FG_GREY);
            $this->stdout("{$result['skipped']}\n");
            $this->stdout("Errors:          ", Console::FG_RED);
            $this->stdout("{$result['errors']}\n");

            if (!empty($result['backupPath'])) {
                $this->stdout("\n");
                $this->stdout("Backup created:  ", Console::FG_CYAN);
                $this->stdout("{$result['backupPath']}\n");
            }

            if ($result['errors'] > 0) {
                $this->stdout("\nSome files had errors. Check the logs for details.\n", Console::FG_YELLOW);
            }

            $this->stdout("\n");

            return ExitCode::OK;
        } catch (\Exception $e) {
            $this->stderr("\n✗ Optimization failed: " . $e->getMessage() . "\n\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Optimize using PHP optimizer
     */
    private function optimizeWithPhp($iconSet): int
    {
        $this->stdout("Using PHP optimizer (mathiasreker/php-svg-optimizer)\n\n");

        if ($this->dryRun) {
            $this->stdout("Dry run mode - no files will be modified\n\n", Console::FG_GREY);
            return ExitCode::OK;
        }

        try {
            $optimizer = IconManager::getInstance()->svgOptimizer;
            $result = $optimizer->optimizeIconSet($iconSet, !$this->noBackup);

            $this->stdout("\n");

            // Check if there were any files to process
            if ($result['total'] === 0) {
                $this->stdout("No SVG files found in this icon set.\n", Console::FG_YELLOW);
                $this->stdout("\nCheck that the icon set path is correct and contains SVG files.\n");
                $this->stdout("\n");
                return ExitCode::OK;
            }

            // Check if everything was already optimized
            if ($result['optimized'] === 0 && $result['skipped'] === 0) {
                $this->stdout("✓ All files are already optimized!\n", Console::FG_GREEN);
                $this->stdout("\nNo changes needed.\n");
                $this->stdout("\n");
                return ExitCode::OK;
            }

            $this->stdout("Optimization Complete!\n", Console::FG_GREEN);
            $this->stdout("=====================\n\n");
            $this->stdout("Files processed: ", Console::FG_YELLOW);
            $this->stdout("{$result['total']}\n");
            $this->stdout("Optimized:       ", Console::FG_GREEN);
            $this->stdout("{$result['optimized']}\n");
            $this->stdout("Skipped:         ", Console::FG_GREY);
            $this->stdout("{$result['skipped']}\n");

            if (!empty($result['backupPath'])) {
                $this->stdout("\n");
                $this->stdout("Backup created:  ", Console::FG_CYAN);
                $this->stdout("{$result['backupPath']}\n");
            }

            // If no files were optimized, suggest SVGO for advanced optimization
            if ($result['optimized'] === 0 && $result['skipped'] > 0) {
                $this->stdout("\n");
                $this->stdout("ℹ Note: PHP optimizer has limited capabilities.\n", Console::FG_YELLOW);
                $this->stdout("\n");
                $this->stdout("For advanced optimization (clip-paths, masks, file size reduction),\n");
                $this->stdout("consider using SVGO:\n\n");

                $svgoService = new SvgoService();
                if ($svgoService->isAvailable()) {
                    $this->stdout("  ", Console::FG_GREY);
                    $this->stdout("./craft icon-manager/optimize --set={$iconSet->id} --engine=svgo\n", Console::FG_GREEN);
                } else {
                    $this->stdout("  1. Install SVGO: ", Console::FG_GREY);
                    $projectRoot = \Craft::getAlias('@root');
                    $packageManager = $this->detectPackageManager($projectRoot);
                    if ($packageManager === 'pnpm') {
                        $this->stdout("pnpm add -D svgo\n", Console::FG_GREEN);
                    } elseif ($packageManager === 'yarn') {
                        $this->stdout("yarn add --dev svgo\n", Console::FG_GREEN);
                    } else {
                        $this->stdout("npm install --save-dev svgo\n", Console::FG_GREEN);
                    }
                    $this->stdout("  2. Run: ", Console::FG_GREY);
                    $this->stdout("./craft icon-manager/optimize --set={$iconSet->id} --engine=svgo\n", Console::FG_GREEN);
                }
            }

            $this->stdout("\n");

            return ExitCode::OK;
        } catch (\Exception $e) {
            $this->stderr("\n✗ Optimization failed: " . $e->getMessage() . "\n\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Detect which package manager is being used
     */
    private function detectPackageManager(string $projectRoot): string
    {
        if (file_exists($projectRoot . '/pnpm-lock.yaml')) {
            return 'pnpm';
        }
        if (file_exists($projectRoot . '/yarn.lock')) {
            return 'yarn';
        }
        return 'npm';
    }

    /**
     * Create temporary SVGO config file based on preset
     */
    private function createTempConfig(string $preset): string
    {
        $runtimePath = \Craft::$app->path->getRuntimePath();
        $tempPath = $runtimePath . '/icon-manager/svgo-preset-' . $preset . '.js';

        // Ensure directory exists
        $dir = dirname($tempPath);
        if (!is_dir($dir)) {
            \craft\helpers\FileHelper::createDirectory($dir);
        }

        $configs = [
            '1' => "export default {
    plugins: [
        'removeDoctype',
        'removeXMLProcInst',
        'removeComments',
        'removeMetadata',
        'removeEditorsNSData',
        'cleanupAttrs',
        'removeEmptyAttrs',
        'removeEmptyContainers',
    ],
};",
            '2' => "export default {
    plugins: [
        'removeDoctype',
        'removeXMLProcInst',
        'removeComments',
        'removeMetadata',
        'removeEditorsNSData',
        'cleanupAttrs',
        'removeEmptyAttrs',
        'removeEmptyContainers',
        'removeHiddenElems',
        'removeEmptyText',
        'cleanupIds',
        'removeUselessDefs',
    ],
};",
            '3' => "export default {
    plugins: [
        {
            name: 'preset-default',
            params: {
                overrides: {
                    convertColors: true,
                    mergePaths: true,
                },
            },
        },
        'cleanupIds',
    ],
};",
        ];

        file_put_contents($tempPath, $configs[$preset]);
        return $tempPath;
    }

    /**
     * Check SVGO availability
     *
     * @return int
     */
    public function actionCheck(): int
    {
        $this->stdout("\n");
        $this->stdout("Checking SVGO availability...\n", Console::FG_CYAN);
        $this->stdout("============================\n\n");

        $svgoService = new SvgoService();

        if ($svgoService->isAvailable()) {
            $this->stdout("✓ SVGO is installed\n\n", Console::FG_GREEN);
            $this->stdout("Path:    ", Console::FG_YELLOW);
            $this->stdout($svgoService->getSvgoPath() . "\n");

            if ($svgoService->getConfigPath()) {
                $this->stdout("Config:  ", Console::FG_YELLOW);
                $this->stdout($svgoService->getConfigPath() . "\n");
            } else {
                $this->stdout("Config:  ", Console::FG_YELLOW);
                $this->stdout("Not found (using defaults)\n", Console::FG_GREY);
            }

            $this->stdout("\n");
            $this->stdout("You can now use SVGO for optimization:\n");
            $this->stdout("  ./craft icon-manager/optimize --set=ID --engine=svgo\n\n");

            return ExitCode::OK;
        } else {
            $this->stderr("✗ SVGO is not installed\n\n", Console::FG_RED);

            $projectRoot = \Craft::getAlias('@root');
            $packageManager = $this->detectPackageManager($projectRoot);

            $this->stdout("Install with:\n  ", Console::FG_GREY);

            if ($packageManager === 'pnpm') {
                $this->stdout("pnpm add -D svgo\n\n", Console::FG_GREEN);
            } elseif ($packageManager === 'yarn') {
                $this->stdout("yarn add --dev svgo\n\n", Console::FG_GREEN);
            } else {
                $this->stdout("npm install --save-dev svgo\n\n", Console::FG_GREEN);
            }

            return ExitCode::UNAVAILABLE;
        }
    }
}
