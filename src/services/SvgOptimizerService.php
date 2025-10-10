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

        // Get all SVG files recursively
        $svgFiles = $this->getSvgFiles($folderPath);

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

        // Check for problematic clip-paths (empty or unused)
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

        // Check for problematic masks (empty or unused)
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

        // Check for filters
        if (preg_match_all('/<filter/i', $content, $matches)) {
            $result['issues']['filters'] = count($matches[0]);
        }

        // Check for comments (exclude legal/license comments with <!--! ... -->)
        if (preg_match_all('/<!--(?!!).*?-->/s', $content, $matches)) {
            $result['issues']['comments'] = count($matches[0]);
        }

        // Check for inline styles (but exclude CSS-only properties we want to keep)
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

        // Check if file is large (> 10KB)
        if ($result['fileSize'] > 10240) {
            $result['issues']['largeFiles'] = 1;
        }

        // Check for width/height attributes on <svg> tag
        if (preg_match('/<svg[^>]*(width|height)\s*=/i', $content)) {
            $result['issues']['widthHeight'] = 1;
        }

        return $result;
    }

    /**
     * Get all SVG files in a directory recursively
     *
     * @param string $directory Directory to scan
     * @return array Array of file paths
     */
    private function getSvgFiles(string $directory): array
    {
        $files = [];

        if (!is_dir($directory)) {
            return $files;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'svg') {
                $files[] = $file->getPathname();
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
        $basePath = IconManager::getInstance()->getSettings()->iconSetsPath;
        $folder = $iconSet->settings['folder'] ?? '';
        $folderPath = Craft::getAlias($basePath . '/' . $folder);

        if (!is_dir($folderPath)) {
            return [
                'success' => false,
                'error' => 'Icon set folder not found',
            ];
        }

        // Get all SVG files
        $svgFiles = $this->getSvgFiles($folderPath);

        // Create backup before optimization if requested and files exist
        $backupPath = null;
        if ($createBackup && count($svgFiles) > 0) {
            $backupPath = $this->createBackup($folderPath, $iconSet->name);
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
     * @return string|false Backup path on success, false on failure
     */
    public function createBackupPublic(string $folderPath, string $iconSetName)
    {
        return $this->createBackup($folderPath, $iconSetName);
    }

    /**
     * Create a timestamped backup of the icon set folder
     *
     * @param string $folderPath Path to folder to backup
     * @param string $iconSetName Name of icon set for backup folder
     * @return string|false Backup path on success, false on failure
     */
    private function createBackup(string $folderPath, string $iconSetName)
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

        // Copy entire folder
        if (!$this->copyDirectory($folderPath, $backupPath)) {
            return false;
        }

        $this->logInfo("Created backup", ['backupPath' => $backupPath]);
        return $backupPath;
    }

    /**
     * Recursively copy a directory
     *
     * @param string $source Source directory
     * @param string $dest Destination directory
     * @return bool Success
     */
    private function copyDirectory(string $source, string $dest): bool
    {
        if (!is_dir($source)) {
            return false;
        }

        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }

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
            $content = file_get_contents($filePath);
            if ($content === false) {
                return false;
            }

            // Normalize whitespace in original content for comparison
            $originalNormalized = preg_replace('/\s+/', ' ', trim($content));

            // Preserve legal/license comments (<!--! ... -->) before optimization
            $legalComments = [];
            if (preg_match_all('/<!--!.*?-->/s', $content, $matches)) {
                $legalComments = $matches[0];
                // Temporarily replace with placeholders
                foreach ($legalComments as $index => $comment) {
                    $content = str_replace($comment, "<!--LEGAL_COMMENT_PLACEHOLDER_{$index}-->", $content);
                }
            }

            // Step 1: Use php-svg-optimizer library for basic optimization
            $optimizedContent = SvgOptimizerFacade::fromString($content)
                ->withRules(
                    convertColorsToHex: true,
                    removeComments: true,
                    removeMetadata: true,
                    removeInkscapeFootprints: true,
                    removeDefaultAttributes: true,
                    removeDeprecatedAttributes: true,
                    removeDoctype: true,
                    removeEmptyAttributes: true,
                    removeInvisibleCharacters: true,
                    removeUnnecessaryWhitespace: true,
                    removeUnusedNamespaces: true,
                    convertEmptyTagsToSelfClosing: true,
                    minifySvgCoordinates: true,
                    sortAttributes: true
                )
                ->optimize()
                ->getContent();

            // Restore legal comments
            foreach ($legalComments as $index => $comment) {
                $optimizedContent = str_replace("<!--LEGAL_COMMENT_PLACEHOLDER_{$index}-->", $comment, $optimizedContent);
            }

            // Step 2: Convert CSS classes to inline attributes (not handled by library)
            $optimizedContent = $this->convertCssClassesToAttributes($optimizedContent);

            // Step 3: Convert inline styles to attributes (not handled by library)
            $optimizedContent = $this->convertInlineStylesToAttributes($optimizedContent);

            // Step 4: Remove unused masks
            $optimizedContent = $this->removeUnusedMasks($optimizedContent);

            // Step 5: Remove width/height from <svg> tag (keep viewBox)
            $optimizedContent = $this->removeWidthHeight($optimizedContent);

            // Normalize whitespace in optimized content for comparison
            $optimizedNormalized = preg_replace('/\s+/', ' ', trim($optimizedContent));

            // Only write if content actually changed
            if ($originalNormalized === $optimizedNormalized) {
                return false; // No changes needed
            }

            // Write optimized content back to file
            return file_put_contents($filePath, $optimizedContent) !== false;
        } catch (\Exception $e) {
            $this->logError("Failed to optimize SVG file", [
                'filePath' => $filePath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Convert CSS classes to inline SVG attributes
     *
     * @param string $svgContent SVG content
     * @return string SVG content with CSS converted to attributes
     */
    private function convertCssClassesToAttributes(string $svgContent): string
    {
        // Extract CSS rules from <style> blocks
        $cssClasses = [];
        if (preg_match('/<style[^>]*>(.*?)<\/style>/s', $svgContent, $matches)) {
            $cssContent = $matches[1];

            // Parse CSS rules
            preg_match_all('/\.([a-zA-Z0-9_-]+)\s*\{([^}]+)\}/', $cssContent, $cssMatches, PREG_SET_ORDER);
            foreach ($cssMatches as $match) {
                $className = $match[1];
                $cssProps = $match[2];

                // Parse CSS properties
                $props = [];
                foreach (explode(';', $cssProps) as $prop) {
                    $prop = trim($prop);
                    if (strpos($prop, ':') !== false) {
                        list($key, $value) = array_map('trim', explode(':', $prop, 2));
                        $key = strtolower($key);

                        // Only convert SVG-compatible properties
                        if (in_array($key, ['fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'opacity'])) {
                            $props[$key] = $value;
                        }
                    }
                }

                if (!empty($props)) {
                    $cssClasses[$className] = $props;
                }
            }

            // If we found CSS classes, apply them and remove style blocks
            if (!empty($cssClasses)) {
                libxml_use_internal_errors(true);
                $dom = new \DOMDocument();
                $dom->preserveWhiteSpace = false;
                $dom->formatOutput = false;

                if ($dom->loadXML($svgContent, LIBXML_NOERROR | LIBXML_NOWARNING)) {
                    $xpath = new \DOMXPath($dom);

                    // Process each CSS class
                    foreach ($cssClasses as $className => $props) {
                        $elements = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' {$className} ')]");

                        foreach ($elements as $element) {
                            // Add attributes from CSS
                            foreach ($props as $attrName => $attrValue) {
                                if (!$element->hasAttribute($attrName)) {
                                    $element->setAttribute($attrName, $attrValue);
                                }
                            }

                            // Remove the class from the class attribute
                            $currentClass = $element->getAttribute('class');
                            $newClass = trim(str_replace($className, '', $currentClass));
                            if (empty($newClass)) {
                                $element->removeAttribute('class');
                            } else {
                                $element->setAttribute('class', $newClass);
                            }
                        }
                    }

                    // Remove all <style> elements using getElementsByTagName (XPath has issues with SVG namespace)
                    $styleElements = $dom->getElementsByTagName('style');
                    $stylesToRemove = [];
                    foreach ($styleElements as $styleElement) {
                        $stylesToRemove[] = $styleElement;
                    }
                    foreach ($stylesToRemove as $styleElement) {
                        $styleElement->parentNode->removeChild($styleElement);
                    }

                    // Remove empty <defs> elements using getElementsByTagName
                    $defsElements = $dom->getElementsByTagName('defs');
                    $defsToRemove = [];
                    foreach ($defsElements as $defsElement) {
                        // Check if defs is empty or only contains whitespace
                        $hasContent = false;
                        foreach ($defsElement->childNodes as $child) {
                            if ($child->nodeType === XML_ELEMENT_NODE ||
                                ($child->nodeType === XML_TEXT_NODE && trim($child->textContent) !== '')) {
                                $hasContent = true;
                                break;
                            }
                        }
                        if (!$hasContent) {
                            $defsToRemove[] = $defsElement;
                        }
                    }
                    foreach ($defsToRemove as $defsElement) {
                        $defsElement->parentNode->removeChild($defsElement);
                    }

                    $svgContent = $dom->saveXML($dom->documentElement);
                }

                libxml_clear_errors();
            }
        }

        return $svgContent;
    }

    /**
     * Convert inline style attributes to SVG attributes
     *
     * @param string $svgContent SVG content
     * @return string SVG content with inline styles converted to attributes
     */
    private function convertInlineStylesToAttributes(string $svgContent): string
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        if ($dom->loadXML($svgContent, LIBXML_NOERROR | LIBXML_NOWARNING)) {
            $xpath = new \DOMXPath($dom);
            $elementsWithStyle = $xpath->query('//*[@style]');

            foreach ($elementsWithStyle as $element) {
                $styleAttr = $element->getAttribute('style');
                $convertedProps = [];
                $remainingProps = [];

                // Parse style attribute
                foreach (explode(';', $styleAttr) as $declaration) {
                    $declaration = trim($declaration);
                    if (strpos($declaration, ':') !== false) {
                        list($prop, $value) = array_map('trim', explode(':', $declaration, 2));
                        $propLower = strtolower($prop);

                        // SVG-compatible properties that can be converted to attributes
                        if (in_array($propLower, ['fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'stroke-dasharray', 'stroke-dashoffset', 'opacity', 'fill-opacity', 'stroke-opacity'])) {
                            // Only add if element doesn't already have this attribute
                            if (!$element->hasAttribute($propLower)) {
                                $element->setAttribute($propLower, $value);
                                $convertedProps[] = $propLower;
                            }
                        } else {
                            // CSS-only properties that must stay in style attribute (like isolation, mix-blend-mode, etc.)
                            $remainingProps[] = $declaration;
                        }
                    }
                }

                // Update or remove style attribute based on what's left
                if (empty($remainingProps)) {
                    $element->removeAttribute('style');
                } else {
                    $element->setAttribute('style', implode('; ', $remainingProps));
                }
            }

            $svgContent = $dom->saveXML($dom->documentElement);
        }

        libxml_clear_errors();
        return $svgContent;
    }

    /**
     * Remove unused mask elements
     *
     * @param string $svgContent SVG content
     * @return string SVG content without unused masks
     */
    private function removeUnusedMasks(string $svgContent): string
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        if ($dom->loadXML($svgContent, LIBXML_NOERROR | LIBXML_NOWARNING)) {
            $xpath = new \DOMXPath($dom);

            // Find all mask elements
            $maskElements = $dom->getElementsByTagName('mask');
            $masksToRemove = [];

            foreach ($maskElements as $maskElement) {
                $maskId = $maskElement->getAttribute('id');
                if ($maskId) {
                    // Check if this mask is referenced anywhere
                    $references = $xpath->query("//*[contains(@mask, 'url(#{$maskId})')]");
                    if ($references->length === 0) {
                        // Mask is not used, mark for removal
                        $masksToRemove[] = $maskElement;
                    }
                }
            }

            // Remove unused masks
            foreach ($masksToRemove as $maskElement) {
                if ($maskElement->parentNode) {
                    $maskElement->parentNode->removeChild($maskElement);
                }
            }

            // After removing masks, check for empty defs again
            $defsElements = $dom->getElementsByTagName('defs');
            $defsToRemove = [];
            foreach ($defsElements as $defsElement) {
                $hasContent = false;
                foreach ($defsElement->childNodes as $child) {
                    if ($child->nodeType === XML_ELEMENT_NODE ||
                        ($child->nodeType === XML_TEXT_NODE && trim($child->textContent) !== '')) {
                        $hasContent = true;
                        break;
                    }
                }
                if (!$hasContent) {
                    $defsToRemove[] = $defsElement;
                }
            }
            foreach ($defsToRemove as $defsElement) {
                $defsElement->parentNode->removeChild($defsElement);
            }

            $svgContent = $dom->saveXML($dom->documentElement);
        }

        libxml_clear_errors();
        return $svgContent;
    }

    /**
     * Remove width and height attributes from SVG root element
     *
     * @param string $svgContent SVG content
     * @return string SVG content without width/height
     */
    private function removeWidthHeight(string $svgContent): string
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadXML($svgContent, LIBXML_NOERROR | LIBXML_NOWARNING);

        $svgElement = $dom->documentElement;
        if ($svgElement && $svgElement->nodeName === 'svg') {
            $svgElement->removeAttribute('width');
            $svgElement->removeAttribute('height');
        }

        $result = $dom->saveXML($dom->documentElement);
        libxml_clear_errors();

        return $result;
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

        // Delete existing files first
        if (is_dir($targetPath)) {
            $this->deleteDirectory($targetPath);
        }

        // Restore from backup
        return $this->copyDirectory($backupPath, $targetPath);
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
