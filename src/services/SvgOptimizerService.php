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
use lindemannrock\iconmanager\IconManager;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use MathiasReker\PhpSvgOptimizer\Service\Facade\SvgOptimizerFacade;

/**
 * SVG Optimizer Service
 *
 * Scans and optimizes SVG files in icon sets
 */
class SvgOptimizerService extends Component
{
    use LoggingTrait;

    /**
     * Scan all SVG icon sets for optimization opportunities
     *
     * @return array Report with issues found
     */
    public function scanAllIconSets(): array
    {
        $iconSets = IconManager::getInstance()->iconSets->getAllIconSets();
        $results = [];

        foreach ($iconSets as $iconSet) {
            // Only scan SVG-based icon sets
            if ($iconSet->type !== 'folder' && $iconSet->type !== 'svg-folder') {
                continue;
            }

            $scanResult = $this->scanIconSet($iconSet);
            if ($scanResult['totalIcons'] > 0) {
                $results[] = $scanResult;
            }
        }

        return $results;
    }

    /**
     * Scan a specific icon set for issues
     *
     * @param object $iconSet The icon set to scan
     * @return array Scan results
     */
    public function scanIconSet($iconSet): array
    {
        $basePath = IconManager::getInstance()->getSettings()->iconSetsPath;
        $folder = $iconSet->settings['folder'] ?? '';
        $folderPath = Craft::getAlias($basePath . '/' . $folder);
        $includeSubfolders = $iconSet->settings['includeSubfolders'] ?? false;

        $result = [
            'iconSetId' => $iconSet->id,
            'iconSetName' => $iconSet->name,
            'folder' => $folder,
            'totalIcons' => 0,
            'totalSize' => 0,
            'issues' => [
                'clipPaths' => 0,
                'masks' => 0,
                'filters' => 0,
                'comments' => 0,
                'inlineStyles' => 0,
                'largeFiles' => 0,
                'widthHeight' => 0,
            ],
            'icons' => [],
        ];

        if (!is_dir($folderPath)) {
            $this->logWarning("Icon set folder not found", ['folderPath' => $folderPath]);
            return $result;
        }

        // Get all SVG files (respecting includeSubfolders setting)
        $svgFiles = $this->getSvgFiles($folderPath, $includeSubfolders);

        foreach ($svgFiles as $filePath) {
            $iconResult = $this->scanSvgFile($filePath);

            $result['totalIcons']++;
            $result['totalSize'] += $iconResult['fileSize'];

            // Count issues
            foreach ($iconResult['issues'] as $issueType => $count) {
                if ($count > 0) {
                    $result['issues'][$issueType]++;
                }
            }

            // Store icon details if it has issues
            if ($this->hasIssues($iconResult['issues'])) {
                $result['icons'][] = [
                    'path' => str_replace($folderPath . '/', '', $filePath),
                    'fileSize' => $iconResult['fileSize'],
                    'issues' => $iconResult['issues'],
                ];
            }
        }

        return $result;
    }

    /**
     * Scan a single SVG file for issues
     *
     * @param string $filePath Path to SVG file
     * @return array Issues found
     */
    private function scanSvgFile(string $filePath): array
    {
        $result = [
            'fileSize' => filesize($filePath),
            'issues' => [
                'clipPaths' => 0,
                'masks' => 0,
                'filters' => 0,
                'comments' => 0,
                'inlineStyles' => 0,
                'largeFiles' => 0,
                'widthHeight' => 0,
            ],
        ];

        $content = file_get_contents($filePath);
        if ($content === false) {
            return $result;
        }

        // Get scan settings
        $settings = IconManager::getInstance()->getSettings();

        // Check for problematic clip-paths (empty or unused)
        if ($settings->scanClipPaths) {
            $clipPathIssues = 0;
            if (preg_match_all('/<clipPath[^>]*id\s*=\s*["\']([^"\']+)["\'][^>]*>(.*?)<\/clipPath>/is', $content, $clipMatches, PREG_SET_ORDER)) {
                foreach ($clipMatches as $match) {
                    $clipId = $match[1];
                    $clipContent = trim($match[2]);

                    // Empty clip-path
                    if (empty($clipContent)) {
                        $clipPathIssues++;
                        continue;
                    }

                    // Unused clip-path (not referenced anywhere)
                    if (!preg_match('/clip-path\s*[:=]\s*["\']?\s*url\s*\(\s*#' . preg_quote($clipId, '/') . '\s*\)/i', $content)) {
                        $clipPathIssues++;
                    }
                }
            }
            $result['issues']['clipPaths'] = $clipPathIssues;
        }

        // Check for problematic masks (empty or unused)
        if ($settings->scanMasks) {
            $maskIssues = 0;
            if (preg_match_all('/<mask[^>]*id\s*=\s*["\']([^"\']+)["\'][^>]*>(.*?)<\/mask>/is', $content, $maskMatches, PREG_SET_ORDER)) {
                foreach ($maskMatches as $match) {
                    $maskId = $match[1];
                    $maskContent = trim($match[2]);

                    // Empty mask
                    if (empty($maskContent)) {
                        $maskIssues++;
                        continue;
                    }

                    // Unused mask (not referenced anywhere)
                    if (!preg_match('/mask\s*[:=]\s*["\']?\s*url\s*\(\s*#' . preg_quote($maskId, '/') . '\s*\)/i', $content)) {
                        $maskIssues++;
                    }
                }
            }
            $result['issues']['masks'] = $maskIssues;
        }

        // Check for filters
        if ($settings->scanFilters) {
            if (preg_match_all('/<filter/i', $content, $matches)) {
                $result['issues']['filters'] = count($matches[0]);
            }
        }

        // Check for comments (exclude legal/license comments with <!--! ... -->)
        if ($settings->scanComments) {
            if (preg_match_all('/<!--(?!!).*?-->/s', $content, $matches)) {
                $result['issues']['comments'] = count($matches[0]);
            }
        }

        // Check for inline styles (but exclude CSS-only properties we want to keep)
        if ($settings->scanInlineStyles) {
            if (preg_match_all('/style\s*=\s*["\']([^"\']+)["\']/i', $content, $matches)) {
                $styleCount = 0;
                foreach ($matches[1] as $styleContent) {
                    // Parse the style content
                    $hasConvertibleStyles = false;
                    foreach (explode(';', $styleContent) as $style) {
                        if (strpos($style, ':') !== false) {
                            list($prop, $value) = array_map('trim', explode(':', $style, 2));
                            $propLower = strtolower($prop);

                            // Only count if it's NOT a CSS-only property
                            if (!in_array($propLower, ['mix-blend-mode', 'opacity', 'filter', 'transform', 'clip-path', 'isolation'])) {
                                $hasConvertibleStyles = true;
                                break;
                            }
                        }
                    }
                    if ($hasConvertibleStyles) {
                        $styleCount++;
                    }
                }
                $result['issues']['inlineStyles'] = $styleCount;
            }
        }

        // Check if file is large (> 10KB)
        if ($settings->scanLargeFiles) {
            if ($result['fileSize'] > 10240) {
                $result['issues']['largeFiles'] = 1;
            }
        }

        // Check for width/height attributes on <svg> tag
        if ($settings->scanWidthHeight || $settings->scanWidthHeightWithViewBox) {
            // Match only when preceded by space or quote to avoid stroke-width, line-width, etc.
            if (preg_match('/<svg[^>]*[\s"\'](width|height)\s*=/i', $content)) {
                $hasViewBox = preg_match('/<svg[^>]*viewBox\s*=/i', $content);

                if (!$hasViewBox && $settings->scanWidthHeight) {
                    // Critical: width/height without viewBox
                    $result['issues']['widthHeight'] = 1;
                } elseif ($hasViewBox && $settings->scanWidthHeightWithViewBox) {
                    // Optional: width/height even with viewBox
                    $result['issues']['widthHeight'] = 1;
                }
            }
        }

        return $result;
    }

    /**
     * Get all SVG files in a directory
     *
     * @param string $directory Directory to scan
     * @param bool $includeSubfolders Whether to scan subdirectories recursively
     * @return array Array of file paths
     */
    private function getSvgFiles(string $directory, bool $includeSubfolders = true): array
    {
        $files = [];

        if (!is_dir($directory)) {
            return $files;
        }

        if ($includeSubfolders) {
            // Recursive scan of all subdirectories
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && strtolower($file->getExtension()) === 'svg') {
                    $files[] = $file->getPathname();
                }
            }
        } else {
            // Non-recursive scan - only root directory
            $iterator = new \DirectoryIterator($directory);

            foreach ($iterator as $file) {
                if ($file->isFile() && strtolower($file->getExtension()) === 'svg') {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    /**
     * Check if an issue array has any issues
     *
     * @param array $issues Issues array
     * @return bool True if has issues
     */
    private function hasIssues(array $issues): bool
    {
        return array_sum($issues) > 0;
    }

    /**
     * Format file size for display
     *
     * @param int $bytes File size in bytes
     * @return string Formatted size
     */
    public function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }

    /**
     * Get SVG content for preview (for optimization table)
     *
     * @param object $iconSet The icon set
     * @param string $relativePath Relative path to the SVG file
     * @return string|null SVG content or null if not found
     */
    public function getSvgPreview($iconSet, string $relativePath): ?string
    {
        $basePath = IconManager::getInstance()->getSettings()->iconSetsPath;
        $folder = $iconSet->settings['folder'] ?? '';
        $fullPath = Craft::getAlias($basePath . '/' . $folder . '/' . $relativePath);

        if (file_exists($fullPath)) {
            return file_get_contents($fullPath);
        }

        return null;
    }

    /**
     * Optimize all SVG files in an icon set
     *
     * @param object $iconSet The icon set to optimize
     * @param bool $createBackup Whether to create a backup before optimization
     * @return array Result with success status, count, and backup path
     */
    public function optimizeIconSet($iconSet, bool $createBackup = true): array
    {
        // Check if optimization is enabled in settings
        $settings = IconManager::getInstance()->getSettings();
        if (!$settings->enableOptimization) {
            return [
                'success' => false,
                'error' => 'SVG optimization is disabled in plugin settings.',
            ];
        }

        $basePath = $settings->iconSetsPath;
        $folder = $iconSet->settings['folder'] ?? '';
        $folderPath = Craft::getAlias($basePath . '/' . $folder);
        $includeSubfolders = $iconSet->settings['includeSubfolders'] ?? false;

        if (!is_dir($folderPath)) {
            return [
                'success' => false,
                'error' => 'Icon set folder not found',
            ];
        }

        // Get all SVG files (respecting includeSubfolders setting)
        $svgFiles = $this->getSvgFiles($folderPath, $includeSubfolders);

        // Create backup before optimization if requested and files exist
        $backupPath = null;
        if ($createBackup && count($svgFiles) > 0) {
            $backupPath = $this->createBackup($folderPath, $iconSet->name, $includeSubfolders);
            if (!$backupPath) {
                return [
                    'success' => false,
                    'error' => 'Failed to create backup',
                ];
            }
        }

        $optimizedCount = 0;
        $skippedCount = 0;
        foreach ($svgFiles as $filePath) {
            if ($this->optimizeSvgFile($filePath)) {
                $optimizedCount++;
            } else {
                $skippedCount++;
            }
        }

        // If no files were optimized, delete the backup (no changes made)
        if ($optimizedCount === 0 && $backupPath && is_dir($backupPath)) {
            $this->deleteDirectory($backupPath);
            $backupPath = null;
            $this->logInfo("Deleted unnecessary backup (no files were optimized)");
        }

        return [
            'success' => true,
            'total' => count($svgFiles),
            'optimized' => $optimizedCount,
            'skipped' => $skippedCount,
            'filesOptimized' => $optimizedCount, // Keep for backward compatibility
            'backupPath' => $backupPath,
        ];
    }

    /**
     * Create a timestamped backup of the icon set folder (public method for controller)
     *
     * @param string $folderPath Path to folder to backup
     * @param string $iconSetName Name of icon set for backup folder
     * @param bool $includeSubfolders Whether to include subdirectories in backup
     * @return string|false Backup path on success, false on failure
     */
    public function createBackupPublic(string $folderPath, string $iconSetName, bool $includeSubfolders = true)
    {
        return $this->createBackup($folderPath, $iconSetName, $includeSubfolders);
    }

    /**
     * Create a timestamped backup of the icon set folder
     *
     * @param string $folderPath Path to folder to backup
     * @param string $iconSetName Name of icon set for backup folder
     * @param bool $includeSubfolders Whether to include subdirectories in backup
     * @return string|false Backup path on success, false on failure
     */
    private function createBackup(string $folderPath, string $iconSetName, bool $includeSubfolders = true)
    {
        $runtimePath = Craft::$app->path->getRuntimePath();
        $backupBasePath = $runtimePath . '/icon-manager/backups';

        // Create backups directory if it doesn't exist
        if (!is_dir($backupBasePath)) {
            mkdir($backupBasePath, 0755, true);
        }

        // Create timestamped backup folder
        $timestamp = date('Y-m-d_H-i-s');
        $safeName = preg_replace('/[^a-z0-9_-]/i', '_', $iconSetName);
        $backupPath = $backupBasePath . '/' . $safeName . '_' . $timestamp;

        // Copy folder (respecting includeSubfolders setting)
        if (!$this->copyDirectory($folderPath, $backupPath, $includeSubfolders)) {
            return false;
        }

        $this->logInfo("Created backup", ['backupPath' => $backupPath, 'includeSubfolders' => $includeSubfolders]);
        return $backupPath;
    }

    /**
     * Copy a directory
     *
     * @param string $source Source directory
     * @param string $dest Destination directory
     * @param bool $includeSubfolders Whether to copy subdirectories recursively
     * @return bool Success
     */
    private function copyDirectory(string $source, string $dest, bool $includeSubfolders = true): bool
    {
        if (!is_dir($source)) {
            return false;
        }

        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }

        if ($includeSubfolders) {
            // Recursive copy - all subdirectories
            /** @var \RecursiveIteratorIterator|\RecursiveDirectoryIterator $iterator */
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                $destPath = $dest . '/' . $iterator->getSubPathName();

                if ($item->isDir()) {
                    if (!is_dir($destPath)) {
                        mkdir($destPath, 0755, true);
                    }
                } else {
                    copy($item->getPathname(), $destPath);
                }
            }
        } else {
            // Non-recursive copy - only root directory files
            $iterator = new \DirectoryIterator($source);

            foreach ($iterator as $item) {
                if ($item->isDot()) {
                    continue;
                }

                $destPath = $dest . '/' . $item->getFilename();

                if ($item->isFile()) {
                    copy($item->getPathname(), $destPath);
                }
                // Skip subdirectories when includeSubfolders is false
            }
        }

        return true;
    }

    /**
     * Optimize a single SVG file
     *
     * @param string $filePath Path to SVG file
     * @return bool Success
     */
    private function optimizeSvgFile(string $filePath): bool
    {
        try {
            // Get user's optimization settings - ALL rules are user-controlled
            $settings = IconManager::getInstance()->getSettings();

            // Build rules array based on user settings (all 21 rules)
            $rules = [];

            // Conversion rules
            if ($settings->optimizeConvertColorsToHex) {
                $rules['convertColorsToHex'] = true;
            }
            if ($settings->optimizeConvertCssClasses) {
                $rules['convertCssClassesToAttributes'] = true;
            }
            if ($settings->optimizeConvertEmptyTags) {
                $rules['convertEmptyTagsToSelfClosing'] = true;
            }
            if ($settings->optimizeConvertInlineStyles) {
                $rules['convertInlineStylesToAttributes'] = true;
            }

            // Minification rules
            if ($settings->optimizeMinifyCoordinates) {
                $rules['minifySvgCoordinates'] = true;
            }
            if ($settings->optimizeMinifyTransformations) {
                $rules['minifyTransformations'] = true;
            }

            // Removal rules
            if ($settings->optimizeRemoveComments) {
                $rules['removeComments'] = true;  // Auto-preserves legal comments (<!--! -->)
            }
            if ($settings->optimizeRemoveDefaultAttributes) {
                $rules['removeDefaultAttributes'] = true;
            }
            if ($settings->optimizeRemoveDeprecatedAttributes) {
                $rules['removeDeprecatedAttributes'] = true;
            }
            if ($settings->optimizeRemoveDoctype) {
                $rules['removeDoctype'] = true;
            }
            if ($settings->optimizeRemoveEnableBackground) {
                $rules['removeEnableBackgroundAttribute'] = true;
            }
            if ($settings->optimizeRemoveEmptyAttributes) {
                $rules['removeEmptyAttributes'] = true;
            }
            if ($settings->optimizeRemoveInkscapeFootprints) {
                $rules['removeInkscapeFootprints'] = true;
            }
            if ($settings->optimizeRemoveInvisibleCharacters) {
                $rules['removeInvisibleCharacters'] = true;
            }
            if ($settings->optimizeRemoveMetadata) {
                $rules['removeMetadata'] = true;
            }
            if ($settings->optimizeRemoveWhitespace) {
                $rules['removeUnnecessaryWhitespace'] = true;
            }
            if ($settings->optimizeRemoveUnusedNamespaces) {
                $rules['removeUnusedNamespaces'] = true;
            }
            if ($settings->optimizeRemoveUnusedMasks) {
                $rules['removeUnusedMasks'] = true;
            }
            if ($settings->optimizeRemoveWidthHeight) {
                $rules['removeWidthHeightAttributes'] = true;
            }

            // Structure rules
            if ($settings->optimizeFlattenGroups) {
                $rules['flattenGroups'] = true;
            }
            if ($settings->optimizeSortAttributes) {
                $rules['sortAttributes'] = true;
            }

            // Apply optimization with user-controlled rules
            $svgOptimizer = SvgOptimizerFacade::fromFile($filePath)
                ->withRules(...$rules)
                ->optimize()
                ->saveToFile($filePath);

            // Return true if any bytes were saved
            return $svgOptimizer->getMetaData()->getSavedBytes() > 0;
        } catch (\Exception $e) {
            $this->logError("Failed to optimize SVG file", [
                'filePath' => $filePath,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * List all backups for an icon set
     *
     * @param string $iconSetName Name of icon set
     * @return array List of backups with metadata
     */
    public function listBackups(string $iconSetName): array
    {
        $runtimePath = Craft::$app->path->getRuntimePath();
        $backupBasePath = $runtimePath . '/icon-manager/backups';

        if (!is_dir($backupBasePath)) {
            return [];
        }

        $safeName = preg_replace('/[^a-z0-9_-]/i', '_', $iconSetName);
        $backups = [];

        foreach (glob($backupBasePath . '/' . $safeName . '_*') as $backupPath) {
            if (!is_dir($backupPath)) {
                continue;
            }

            $backupName = basename($backupPath);
            $size = $this->getDirectorySize($backupPath);
            $timestamp = filemtime($backupPath);

            $backups[] = [
                'name' => $backupName,
                'path' => $backupPath,
                'size' => $size,
                'date' => $timestamp,
                'formattedDate' => date('Y-m-d H:i:s', $timestamp),
            ];
        }

        // Sort by date descending (newest first)
        usort($backups, function($a, $b) {
            return $b['date'] - $a['date'];
        });

        return $backups;
    }

    /**
     * Get total size of a directory
     *
     * @param string $path Directory path
     * @return int Size in bytes
     */
    private function getDirectorySize(string $path): int
    {
        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    /**
     * Restore an icon set from backup
     *
     * @param string $backupPath Path to backup
     * @param string $targetPath Path to restore to
     * @return bool Success
     */
    public function restoreFromBackup(string $backupPath, string $targetPath): bool
    {
        if (!is_dir($backupPath)) {
            return false;
        }

        // Detect if backup contains subdirectories (indicates it was created with includeSubfolders=true)
        $backupHasSubdirs = $this->hasSubdirectories($backupPath);

        if ($backupHasSubdirs) {
            // Full restore - delete everything and restore complete structure
            if (is_dir($targetPath)) {
                $this->deleteDirectory($targetPath);
            }
            return $this->copyDirectory($backupPath, $targetPath, true);
        } else {
            // Root-only restore - only replace root files, preserve existing subdirectories
            if (!is_dir($targetPath)) {
                mkdir($targetPath, 0755, true);
            }

            // Delete only root-level files before restoring
            $iterator = new \DirectoryIterator($targetPath);
            foreach ($iterator as $item) {
                if ($item->isDot()) {
                    continue;
                }
                if ($item->isFile()) {
                    unlink($item->getPathname());
                }
                // Leave subdirectories untouched
            }

            // Copy root-level files from backup
            return $this->copyDirectory($backupPath, $targetPath, false);
        }
    }

    /**
     * Check if a directory contains any subdirectories
     *
     * @param string $path Directory path to check
     * @return bool True if has subdirectories
     */
    private function hasSubdirectories(string $path): bool
    {
        if (!is_dir($path)) {
            return false;
        }

        $iterator = new \DirectoryIterator($path);
        foreach ($iterator as $item) {
            if ($item->isDot()) {
                continue;
            }
            if ($item->isDir()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Delete a backup
     *
     * @param string $backupPath Path to backup
     * @return bool Success
     */
    public function deleteBackup(string $backupPath): bool
    {
        if (!is_dir($backupPath)) {
            return false;
        }

        return $this->deleteDirectory($backupPath);
    }

    /**
     * Recursively delete a directory
     *
     * @param string $path Directory path
     * @return bool Success
     */
    private function deleteDirectory(string $path): bool
    {
        if (!is_dir($path)) {
            return false;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        return rmdir($path);
    }
}
