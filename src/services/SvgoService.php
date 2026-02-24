<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\iconmanager\services;

use Craft;
use craft\base\Component;
use craft\helpers\FileHelper;
use lindemannrock\iconmanager\IconManager;
use lindemannrock\iconmanager\models\IconSet;
use lindemannrock\logginglibrary\traits\LoggingTrait;

/**
 * SVGO Service
 *
 * Handles SVGO detection, configuration, and optimization
 *
 * @since 1.10.0
 */
class SvgoService extends Component
{
    use LoggingTrait;

    private ?string $_svgoPath = null;
    private ?string $_configPath = null;
    private ?bool $_isAvailable = null;

    /**
     * Check if SVGO is available in the project
     */
    public function isAvailable(): bool
    {
        if ($this->_isAvailable !== null) {
            return $this->_isAvailable;
        }

        $svgoPath = $this->getSvgoPath();
        $this->_isAvailable = $svgoPath !== null && file_exists($svgoPath);

        return $this->_isAvailable;
    }

    /**
     * Get the path to SVGO executable
     */
    public function getSvgoPath(): ?string
    {
        if ($this->_svgoPath !== null) {
            return $this->_svgoPath;
        }

        $projectRoot = Craft::getAlias('@root');

        // Check common locations
        $possiblePaths = [
            $projectRoot . '/node_modules/.bin/svgo',
            $projectRoot . '/node_modules/svgo/bin/svgo',
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $this->_svgoPath = $path;
                return $this->_svgoPath;
            }
        }

        // Try global install
        $globalPath = $this->findGlobalSvgo();
        if ($globalPath) {
            $this->_svgoPath = $globalPath;
            return $this->_svgoPath;
        }

        return null;
    }

    /**
     * Find global SVGO installation
     */
    private function findGlobalSvgo(): ?string
    {
        $output = [];
        $returnCode = 0;

        // Try 'which svgo' on Unix-like systems
        if (PHP_OS_FAMILY !== 'Windows') {
            exec('which svgo 2>/dev/null', $output, $returnCode);
            if ($returnCode === 0 && !empty($output[0])) {
                return trim($output[0]);
            }
        } else {
            // Try 'where svgo' on Windows
            exec('where svgo 2>nul', $output, $returnCode);
            if ($returnCode === 0 && !empty($output[0])) {
                return trim($output[0]);
            }
        }

        return null;
    }

    /**
     * Get the path to SVGO config file
     */
    public function getConfigPath(): ?string
    {
        if ($this->_configPath !== null) {
            return $this->_configPath;
        }

        $projectRoot = Craft::getAlias('@root');

        // Check for config files
        $possibleConfigs = [
            $projectRoot . '/svgo.config.js',
            $projectRoot . '/svgo.config.mjs',
            $projectRoot . '/.svgorc.js',
        ];

        foreach ($possibleConfigs as $config) {
            if (file_exists($config)) {
                $this->_configPath = $config;
                return $this->_configPath;
            }
        }

        return null;
    }

    /**
     * Optimize an entire icon set using SVGO
     */
    public function optimizeIconSet(IconSet $iconSet, ?string $customConfigPath = null, bool $createBackup = true): array
    {
        // Check if optimization is enabled in settings
        if (!IconManager::getInstance()->getSettings()->enableOptimization) {
            throw new \Exception('SVG optimization is disabled in plugin settings.');
        }

        if (!$this->isAvailable()) {
            throw new \Exception('SVGO is not available. Please install it first.');
        }

        $settings = $iconSet->getTypeSettings();
        $folder = $settings['folder'] ?? '';
        $includeSubfolders = $settings['includeSubfolders'] ?? false;

        $basePath = IconManager::getInstance()->getSettings()->getResolvedIconSetsPath();

        if (empty($folder)) {
            $folderPath = $basePath;
        } else {
            $folderPath = FileHelper::normalizePath($basePath . DIRECTORY_SEPARATOR . $folder);
        }

        if (!is_dir($folderPath)) {
            throw new \Exception("Folder path does not exist: {$folderPath}");
        }

        // Find all SVG files
        $files = FileHelper::findFiles($folderPath, [
            'only' => ['*.svg'],
            'except' => ['_*'],
            'recursive' => $includeSubfolders,
        ]);

        $total = count($files);
        $optimized = 0;
        $skipped = 0;
        $errors = 0;

        // Create backup before optimization if requested
        $backupPath = null;
        if ($createBackup && $total > 0) {
            $svgOptimizer = IconManager::getInstance()->svgOptimizer;
            $backupPath = $svgOptimizer->createBackupPublic($folderPath, $iconSet->name, $includeSubfolders);
            if (!$backupPath) {
                throw new \Exception('Failed to create backup');
            }
            $this->logInfo("Created backup before SVGO optimization", [
                'backupPath' => $backupPath,
                'includeSubfolders' => $includeSubfolders,
            ]);
        }

        $this->logInfo("Starting SVGO optimization for icon set", [
            'iconSetName' => $iconSet->name,
            'iconSetId' => $iconSet->id,
            'totalFiles' => $total,
        ]);

        // Process files with progress output
        $current = 0;
        foreach ($files as $file) {
            $current++;
            $filename = basename($file);

            // Output progress (will be visible in console)
            echo "Processing ({$current}/{$total}): {$filename}...\n";

            try {
                $result = $this->optimizeFile($file, $customConfigPath);

                if ($result['success']) {
                    $optimized++;
                    echo "  ✓ Optimized\n";
                    $this->logInfo("SVGO optimized file successfully", [
                        'file' => $filename,
                        'path' => $file,
                        'progress' => "{$current}/{$total}",
                    ]);
                } else {
                    $skipped++;
                    echo "  - Skipped\n";
                    $this->logWarning("Skipped file during SVGO optimization", [
                        'file' => $filename,
                        'path' => $file,
                        'reason' => $result['message'] ?? 'Unknown',
                        'output' => $result['output'] ?? '',
                    ]);
                }
            } catch (\Exception $e) {
                $errors++;
                echo "  ✗ Error\n";
                $this->logError("Error optimizing file with SVGO", [
                    'error' => $e->getMessage(),
                    'file' => $filename,
                    'path' => $file,
                ]);
                $this->logDebug("Exception trace", [
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->logInfo("SVGO optimization completed for icon set", [
            'iconSetName' => $iconSet->name,
            'iconSetId' => $iconSet->id,
            'total' => $total,
            'optimized' => $optimized,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);

        // If no files were optimized, delete the backup (no changes made)
        if ($optimized === 0 && $backupPath && is_dir($backupPath)) {
            $svgOptimizer = IconManager::getInstance()->svgOptimizer;
            $svgOptimizer->deleteBackup($backupPath);
            $backupPath = null;
            $this->logInfo("Deleted unnecessary backup (no files were optimized)");
        }

        return [
            'total' => $total,
            'optimized' => $optimized,
            'skipped' => $skipped,
            'errors' => $errors,
            'backupPath' => $backupPath,
        ];
    }

    /**
     * Optimize a single file using SVGO
     */
    public function optimizeFile(string $filePath, ?string $customConfigPath = null): array
    {
        if (!file_exists($filePath)) {
            return [
                'success' => false,
                'message' => 'File not found',
            ];
        }

        // Get original file size and content hash to detect changes
        $originalSize = filesize($filePath);
        $originalHash = md5_file($filePath);

        $svgoPath = $this->getSvgoPath();
        $configPath = $customConfigPath ?? $this->getConfigPath();

        // Build command
        $command = escapeshellarg($svgoPath) . ' ' . escapeshellarg($filePath);

        // Add config if available
        if ($configPath && file_exists($configPath)) {
            $command .= ' --config=' . escapeshellarg($configPath);
        }

        // Output to same file
        $command .= ' -o ' . escapeshellarg($filePath);

        // Redirect stderr to stdout to capture errors
        $command .= ' 2>&1';

        $output = [];
        $returnCode = 0;

        exec($command, $output, $returnCode);

        if ($returnCode === 0) {
            // Check if file actually changed
            $newSize = filesize($filePath);
            $newHash = md5_file($filePath);
            $wasOptimized = $originalHash !== $newHash;

            return [
                'success' => $wasOptimized,
                'message' => $wasOptimized ? 'File optimized successfully' : 'No optimization needed',
                'output' => implode("\n", $output),
                'originalSize' => $originalSize,
                'newSize' => $newSize,
                'changed' => $wasOptimized,
            ];
        } else {
            return [
                'success' => false,
                'message' => 'SVGO optimization failed',
                'output' => implode("\n", $output),
                'returnCode' => $returnCode,
            ];
        }
    }

    /**
     * Get SVGO version
     */
    public function getVersion(): ?string
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $svgoPath = $this->getSvgoPath();
        $command = escapeshellarg($svgoPath) . ' --version 2>&1';

        $output = [];
        $returnCode = 0;

        exec($command, $output, $returnCode);

        if ($returnCode === 0 && !empty($output[0])) {
            return trim($output[0]);
        }

        return null;
    }
}
