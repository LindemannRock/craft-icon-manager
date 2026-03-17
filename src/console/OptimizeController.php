<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\iconmanager\console;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;
use craft\helpers\FileHelper;
use lindemannrock\iconmanager\IconManager;
use lindemannrock\iconmanager\services\SvgoService;
use MathiasReker\PhpSvgOptimizer\Service\Facade\SvgOptimizerFacade;
use yii\console\ExitCode;

/**
 * Optimize SVG icons using various optimization engines
 *
 * @since 1.10.0
 */
class OptimizeController extends Controller
{
    /**
     * @var string Path to an SVG file or directory for rule verification
     */
    public $path = '';

    /**
     * @var bool Include risky rules in verification runs
     */
    public $includeRisky = true;

    /**
     * @var bool Keep optimized verification outputs in runtime storage
     */
    public $keepOutputs = false;

    /**
     * @var array<int, string>
     */
    private const RISKY_RULES = [
        'removeDataAttributes',
        'removeEnableBackgroundAttribute',
        'removeWidthHeightAttributes',
        'scopeSvgStyles',
    ];

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
        $options[] = 'path';
        $options[] = 'includeRisky';
        $options[] = 'keepOutputs';
        return $options;
    }

    /**
     * Verify installed optimizer rules against one SVG file or a directory of SVG files.
     *
     * This does not prove visual equivalence, but it does prove that each rule can run,
     * produce parseable SVG output, and avoid obvious structural breakage.
     *
     * @param string|null $path Path to an SVG file or directory
     * @return int
     * @since 5.11.3
     */
    public function actionVerify(?string $path = null): int
    {
        $targetPath = $path ?: $this->path;

        if ($targetPath === '') {
            $this->stderr("Error: Provide a file or directory path.\n", Console::FG_RED);
            $this->stdout("Example: ./craft icon-manager/optimize/verify --path=/absolute/path/to/svgs\n");
            return ExitCode::USAGE;
        }

        $resolvedPath = Craft::getAlias($targetPath);
        $svgFiles = $this->resolveSvgFiles($resolvedPath);

        if ($svgFiles === []) {
            $this->stderr("No SVG files found at: {$resolvedPath}\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        if ($this->engine === 'svgo') {
            return $this->verifySvgoFiles($resolvedPath, $svgFiles);
        }

        if ($this->engine !== 'php') {
            $this->stderr("Error: Unknown engine '{$this->engine}'\n", Console::FG_RED);
            $this->stdout("Available engines: php, svgo\n");
            return ExitCode::USAGE;
        }

        return $this->verifyPhpRules($resolvedPath, $svgFiles);
    }

    /**
     * Run the full PHP optimizer rule surface against the provided SVG files.
     *
     * @param string $resolvedPath
     * @param array<int, string> $svgFiles
     * @return int
     */
    private function verifyPhpRules(string $resolvedPath, array $svgFiles): int
    {
        $supportedRules = $this->getSupportedRuleNames();
        $safeRules = array_values(array_diff($supportedRules, self::RISKY_RULES));
        $rulesToVerify = $this->includeRisky ? $supportedRules : $safeRules;

        $this->stdout("\n");
        $this->stdout("Icon Manager - PHP Optimizer Verification\n", Console::FG_CYAN);
        $this->stdout("========================================\n\n");
        $this->stdout("Path:         ", Console::FG_YELLOW);
        $this->stdout($resolvedPath . "\n");
        $this->stdout("SVG files:    ", Console::FG_YELLOW);
        $this->stdout(count($svgFiles) . "\n");
        $this->stdout("Rules tested: ", Console::FG_YELLOW);
        $this->stdout(count($rulesToVerify) . "\n");
        $this->stdout("Risky rules:  ", Console::FG_YELLOW);
        $this->stdout($this->includeRisky ? "included\n" : "skipped\n");
        $this->stdout("\n");

        $summary = [
            'files' => count($svgFiles),
            'ruleRuns' => 0,
            'comboRuns' => 0,
            'changedRuns' => 0,
            'failures' => [],
        ];

        foreach ($svgFiles as $filePath) {
            $content = file_get_contents($filePath);
            if ($content === false) {
                $summary['failures'][] = [
                    'file' => $filePath,
                    'rule' => 'read',
                    'message' => 'Could not read file contents',
                ];
                continue;
            }

            $relativeLabel = $this->getRelativeDisplayPath($resolvedPath, $filePath);
            $this->stdout("Verifying {$relativeLabel}\n", Console::FG_YELLOW);

            foreach ($rulesToVerify as $ruleName) {
                $summary['ruleRuns']++;
                $result = $this->verifyRulesForContent($content, [$ruleName => true], in_array($ruleName, self::RISKY_RULES, true));

                if (!$result['success']) {
                    $summary['failures'][] = [
                        'file' => $filePath,
                        'rule' => $ruleName,
                        'message' => $result['message'],
                    ];
                    $this->stdout("  [FAIL] {$ruleName}: {$result['message']}\n", Console::FG_RED);
                    continue;
                }

                if ($result['changed']) {
                    $summary['changedRuns']++;
                }

                if ($this->keepOutputs) {
                    $this->persistVerificationOutput($filePath, $ruleName, $result['content']);
                }
            }

            $summary['comboRuns']++;
            $safeCombo = $this->verifyRulesForContent($content, array_fill_keys($safeRules, true), false);
            if (!$safeCombo['success']) {
                $summary['failures'][] = [
                    'file' => $filePath,
                    'rule' => 'all-safe-rules',
                    'message' => $safeCombo['message'],
                ];
                $this->stdout("  [FAIL] all-safe-rules: {$safeCombo['message']}\n", Console::FG_RED);
            } elseif ($safeCombo['changed']) {
                $summary['changedRuns']++;
            }

            if ($this->keepOutputs && $safeCombo['success']) {
                $this->persistVerificationOutput($filePath, 'all-safe-rules', $safeCombo['content']);
            }

            if ($this->includeRisky) {
                $summary['comboRuns']++;
                $allRules = array_fill_keys($supportedRules, true);
                $allCombo = $this->verifyRulesForContent($content, $allRules, true);

                if (!$allCombo['success']) {
                    $summary['failures'][] = [
                        'file' => $filePath,
                        'rule' => 'all-rules',
                        'message' => $allCombo['message'],
                    ];
                    $this->stdout("  [FAIL] all-rules: {$allCombo['message']}\n", Console::FG_RED);
                } elseif ($allCombo['changed']) {
                    $summary['changedRuns']++;
                }

                if ($this->keepOutputs && $allCombo['success']) {
                    $this->persistVerificationOutput($filePath, 'all-rules', $allCombo['content']);
                }
            }
        }

        $this->stdout("\nSummary\n", Console::FG_CYAN);
        $this->stdout("-------\n");
        $this->stdout("Files checked:      {$summary['files']}\n");
        $this->stdout("Individual rules:   {$summary['ruleRuns']}\n");
        $this->stdout("Rule combinations:  {$summary['comboRuns']}\n");
        $this->stdout("Changed outputs:    {$summary['changedRuns']}\n");
        $this->stdout("Failures:           " . count($summary['failures']) . "\n");

        if ($this->keepOutputs) {
            $this->stdout("Saved outputs:      " . Craft::$app->path->getRuntimePath() . "/icon-manager/verify\n");
        }

        if ($summary['failures'] !== []) {
            $this->stdout("\nFailures\n", Console::FG_RED);
            $this->stdout("--------\n");

            foreach ($summary['failures'] as $failure) {
                $this->stdout($failure['file'] . " :: " . $failure['rule'] . " :: " . $failure['message'] . "\n", Console::FG_RED);
            }

            $this->stdout("\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("\nAll requested rule checks completed without parser or optimizer failures.\n", Console::FG_GREEN);
        $this->stdout("This verifies structural validity, not visual equivalence. Spot-check representative icons before release.\n\n", Console::FG_YELLOW);

        return ExitCode::OK;
    }

    /**
     * Run fixture or corpus verification through the configured SVGO engine.
     *
     * @param string $resolvedPath
     * @param array<int, string> $svgFiles
     * @return int
     */
    private function verifySvgoFiles(string $resolvedPath, array $svgFiles): int
    {
        $svgoService = new SvgoService();

        if (!$svgoService->isAvailable()) {
            $this->stderr("SVGO is not available.\n", Console::FG_RED);
            return ExitCode::UNAVAILABLE;
        }

        $this->stdout("\n");
        $this->stdout("Icon Manager - SVGO Verification\n", Console::FG_CYAN);
        $this->stdout("================================\n\n");
        $this->stdout("Path:         ", Console::FG_YELLOW);
        $this->stdout($resolvedPath . "\n");
        $this->stdout("SVG files:    ", Console::FG_YELLOW);
        $this->stdout(count($svgFiles) . "\n");
        $this->stdout("SVGO path:    ", Console::FG_YELLOW);
        $this->stdout($svgoService->getSvgoPath() . "\n");
        $this->stdout("Config:       ", Console::FG_YELLOW);
        $this->stdout(($this->config ?: $svgoService->getConfigPath() ?: 'default') . "\n\n");

        $failures = [];
        $changedRuns = 0;

        foreach ($svgFiles as $filePath) {
            $relativeLabel = $this->getRelativeDisplayPath($resolvedPath, $filePath);
            $this->stdout("Verifying {$relativeLabel}\n", Console::FG_YELLOW);

            $tempFile = $this->copyToVerificationTemp($filePath);
            $result = $svgoService->optimizeFile($tempFile, $this->config ?: null);

            if (($result['success'] ?? false) === false && (($result['changed'] ?? false) === false)) {
                if (($result['message'] ?? '') !== 'No optimization needed') {
                    $failures[] = [
                        'file' => $filePath,
                        'message' => $result['message'] ?? 'Unknown SVGO error',
                    ];
                    $this->stdout("  [FAIL] " . ($result['message'] ?? 'Unknown SVGO error') . "\n", Console::FG_RED);
                    @unlink($tempFile);
                    continue;
                }
            }

            $optimizedContent = file_get_contents($tempFile);
            if ($optimizedContent === false || !$this->isValidSvgContent($optimizedContent)) {
                $failures[] = [
                    'file' => $filePath,
                    'message' => 'Optimized output is not valid SVG XML',
                ];
                $this->stdout("  [FAIL] Optimized output is not valid SVG XML\n", Console::FG_RED);
                @unlink($tempFile);
                continue;
            }

            if (($result['changed'] ?? false) === true) {
                $changedRuns++;
            }

            if ($this->keepOutputs) {
                $this->persistVerificationOutput($filePath, 'svgo', $optimizedContent);
            }

            @unlink($tempFile);
        }

        $this->stdout("\nSummary\n", Console::FG_CYAN);
        $this->stdout("-------\n");
        $this->stdout("Files checked:   " . count($svgFiles) . "\n");
        $this->stdout("Changed outputs: {$changedRuns}\n");
        $this->stdout("Failures:        " . count($failures) . "\n");

        if ($this->keepOutputs) {
            $this->stdout("Saved outputs:   " . Craft::$app->path->getRuntimePath() . "/icon-manager/verify/svgo\n");
        }

        if ($failures !== []) {
            $this->stdout("\nFailures\n", Console::FG_RED);
            $this->stdout("--------\n");
            foreach ($failures as $failure) {
                $this->stdout($failure['file'] . " :: " . $failure['message'] . "\n", Console::FG_RED);
            }
            $this->stdout("\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("\nSVGO verification completed without parser or optimizer failures.\n", Console::FG_GREEN);
        $this->stdout("This verifies structural validity, not visual equivalence. Spot-check representative outputs before release.\n\n", Console::FG_YELLOW);

        return ExitCode::OK;
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

    /**
     * @return array<int, string>
     */
    private function resolveSvgFiles(string $path): array
    {
        if (is_file($path)) {
            return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'svg' ? [$path] : [];
        }

        if (!is_dir($path)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'svg') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @return array{success: bool, changed: bool, message: string, content: string}
     */
    private function verifyRulesForContent(string $content, array $rules, bool $allowRisky): array
    {
        try {
            $optimizer = SvgOptimizerFacade::fromString($content)
                ->withRules(...$rules);

            if ($allowRisky && method_exists($optimizer, 'allowRisky')) {
                $optimizer = $optimizer->allowRisky();
            }

            $optimized = $optimizer->optimize();
            $optimizedContent = $optimized->getContent();

            if (!$this->isValidSvgContent($optimizedContent)) {
                return [
                    'success' => false,
                    'changed' => false,
                    'message' => 'Optimized output is not valid SVG XML',
                    'content' => $optimizedContent,
                ];
            }

            return [
                'success' => true,
                'changed' => $optimizedContent !== $content,
                'message' => '',
                'content' => $optimizedContent,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'changed' => false,
                'message' => $e->getMessage(),
                'content' => '',
            ];
        }
    }

    private function isValidSvgContent(string $content): bool
    {
        if (trim($content) === '') {
            return false;
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $document = new \DOMDocument();
        $loaded = $document->loadXML($content, \LIBXML_NONET);
        $errors = libxml_get_errors();

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded || $errors !== []) {
            return false;
        }

        $root = $document->documentElement;

        return $root instanceof \DOMElement && strtolower($root->localName ?: $root->nodeName) === 'svg';
    }

    /**
     * @return array<int, string>
     */
    private function getSupportedRuleNames(): array
    {
        $method = new \ReflectionMethod(SvgOptimizerFacade::class, 'withRules');
        $rules = [];

        foreach ($method->getParameters() as $parameter) {
            $rules[] = $parameter->getName();
        }

        return $rules;
    }

    private function persistVerificationOutput(string $sourceFilePath, string $ruleName, string $content): void
    {
        $baseDir = Craft::$app->path->getRuntimePath() . '/icon-manager/verify/' . $ruleName;
        FileHelper::createDirectory($baseDir);
        file_put_contents($baseDir . '/' . basename($sourceFilePath), $content);
    }

    private function copyToVerificationTemp(string $sourceFilePath): string
    {
        $tempDir = Craft::$app->path->getRuntimePath() . '/icon-manager/verify-temp';
        FileHelper::createDirectory($tempDir);

        $tempFile = $tempDir . '/' . uniqid('svgo-', true) . '-' . basename($sourceFilePath);
        copy($sourceFilePath, $tempFile);

        return $tempFile;
    }

    private function getRelativeDisplayPath(string $basePath, string $filePath): string
    {
        if (is_dir($basePath)) {
            $prefix = rtrim($basePath, '/') . '/';
            if (str_starts_with($filePath, $prefix)) {
                return substr($filePath, strlen($prefix));
            }
        }

        return basename($filePath);
    }
}
