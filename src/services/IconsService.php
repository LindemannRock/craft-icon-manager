<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\iconmanager\services;

use lindemannrock\iconmanager\IconManager;
use lindemannrock\iconmanager\models\Icon;
use lindemannrock\iconmanager\models\IconSet;
use lindemannrock\iconmanager\records\IconRecord;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\FileHelper;
use craft\helpers\StringHelper;
use craft\helpers\Db;

/**
 * Icons Service
 */
class IconsService extends Component
{
    /**
     * @var array Cache of icons by set ID
     */
    private array $_iconsBySetId = [];

    /**
     * Get all icons for an icon set
     * 
     * @param int $iconSetId
     * @return Icon[]
     */
    public function getIconsBySetId(int $iconSetId): array
    {
        if (isset($this->_iconsBySetId[$iconSetId])) {
            return $this->_iconsBySetId[$iconSetId];
        }
        
        $settings = IconManager::getInstance()->getSettings();
        
        // Use cache if enabled
        if ($settings->enableCache) {
            $cacheKey = "icon-manager:icons-by-set:{$iconSetId}";
            $cacheDuration = $settings->cacheDuration;
            
            $icons = Craft::$app->getCache()->getOrSet($cacheKey, function() use ($iconSetId) {
                return $this->_loadIconsFromDatabase($iconSetId);
            }, $cacheDuration);
            
            $this->_iconsBySetId[$iconSetId] = $icons;
            return $icons;
        }
        
        // No cache - load directly
        $icons = $this->_loadIconsFromDatabase($iconSetId);
        $this->_iconsBySetId[$iconSetId] = $icons;
        
        return $icons;
    }
    
    /**
     * Load icons from database
     */
    private function _loadIconsFromDatabase(int $iconSetId): array
    {
        $results = (new Query())
            ->select([
                'id',
                'iconSetId',
                'name',
                'label',
                'path',
                'keywords',
                'metadata',
            ])
            ->from(['{{%iconmanager_icons}}'])
            ->where(['iconSetId' => $iconSetId])
            ->orderBy(['name' => SORT_ASC])
            ->all();

        $icons = [];
        $iconSet = IconManager::getInstance()->iconSets->getIconSetById($iconSetId);

        if ($iconSet) {
            foreach ($results as $result) {
                $icons[] = $this->_createIconFromRecord($result, $iconSet);
            }
        }

        return $icons;
    }

    /**
     * Get a specific icon
     */
    public function getIcon(string $iconSetHandle, string $iconName): ?Icon
    {
        $iconSet = IconManager::getInstance()->iconSets->getIconSetByHandle($iconSetHandle);
        if (!$iconSet) {
            return null;
        }

        $icons = $this->getIconsBySetId($iconSet->id);
        
        foreach ($icons as $icon) {
            if ($icon->name === $iconName) {
                return $icon;
            }
        }

        return null;
    }

    /**
     * Refresh icons for an icon set
     */
    public function refreshIconsForSet(IconSet $iconSet): void
    {
        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            // Clear cache for this icon set
            $cacheKey = "icon-manager:icons-by-set:{$iconSet->id}";
            Craft::$app->getCache()->delete($cacheKey);
            
            // Clear memory cache
            unset($this->_iconsBySetId[$iconSet->id]);
            
            // Delete existing icons
            $db->createCommand()
                ->delete('{{%iconmanager_icons}}', ['iconSetId' => $iconSet->id])
                ->execute();

            // Scan for new icons based on type
            $icons = $this->_scanIconsForSet($iconSet);

            // Insert new icons
            foreach ($icons as $icon) {
                $db->createCommand()
                    ->insert('{{%iconmanager_icons}}', [
                        'iconSetId' => $iconSet->id,
                        'name' => $icon['name'],
                        'label' => $icon['label'] ?? '',
                        'path' => $icon['path'] ?? '',
                        'keywords' => json_encode($icon['keywords'] ?? []),
                        'metadata' => json_encode($icon['metadata'] ?? []),
                        'dateCreated' => Db::prepareDateForDb(new \DateTime()),
                        'dateUpdated' => Db::prepareDateForDb(new \DateTime()),
                        'uid' => StringHelper::UUID(),
                    ])
                    ->execute();
            }

            // Clear cache
            unset($this->_iconsBySetId[$iconSet->id]);

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Search icons across all sets
     */
    public function searchIcons(string $query, array $iconSetHandles = []): array
    {
        $results = [];
        $iconSets = [];

        if (empty($iconSetHandles)) {
            $iconSets = IconManager::getInstance()->iconSets->getAllEnabledIconSets();
        } else {
            $iconSets = IconManager::getInstance()->iconSets->getIconSetsByHandles($iconSetHandles);
        }

        foreach ($iconSets as $iconSet) {
            $icons = $this->getIconsBySetId($iconSet->id);
            
            foreach ($icons as $icon) {
                if ($icon->matchesKeywords($query)) {
                    $results[] = $icon;
                }
            }
        }

        return $results;
    }

    /**
     * Scan icons for a set based on its type
     */
    private function _scanIconsForSet(IconSet $iconSet): array
    {
        switch ($iconSet->type) {
            case 'svg-folder':
                return $this->_scanSvgFolder($iconSet);
            case 'svg-sprite':
                return $this->_scanSvgSprite($iconSet);
            case 'font-awesome':
                return $this->_getFontAwesomeIcons($iconSet);
            case 'material-icons':
                return $this->_getMaterialIcons($iconSet);
            default:
                return [];
        }
    }

    /**
     * Scan SVG folder for icons
     */
    private function _scanSvgFolder(IconSet $iconSet): array
    {
        $icons = [];
        $settings = $iconSet->getTypeSettings();
        $folder = $settings['folder'] ?? '';
        $includeSubfolders = $settings['includeSubfolders'] ?? false;

        $basePath = IconManager::getInstance()->getSettings()->getResolvedIconSetsPath();
        
        // If folder is empty, use the base path itself
        if (empty($folder)) {
            $folderPath = $basePath;
        } else {
            $folderPath = FileHelper::normalizePath($basePath . DIRECTORY_SEPARATOR . $folder);
        }

        if (!is_dir($folderPath)) {
            Craft::info('Folder path does not exist: ' . $folderPath, __METHOD__);
            return $icons;
        }

        Craft::info('Scanning folder: ' . $folderPath, __METHOD__);

        $files = FileHelper::findFiles($folderPath, [
            'only' => ['*.svg'],
            'except' => ['_*'],
            'recursive' => $includeSubfolders,
        ]);
        
        Craft::info('Found ' . count($files) . ' SVG files', __METHOD__);

        foreach ($files as $file) {
            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file);
            $name = pathinfo($file, PATHINFO_FILENAME);
            
            // Create label from filename
            $label = StringHelper::titleize($name);

            // Try to extract keywords from SVG metadata
            $keywords = $this->_extractSvgKeywords($file);

            $icons[] = [
                'name' => $name,
                'label' => $label,
                'path' => $relativePath, // Store ONLY the relative path
                'keywords' => $keywords,
                'metadata' => [
                    'type' => Icon::TYPE_SVG,
                    'relativePath' => $relativePath,
                ],
            ];
        }

        return $icons;
    }

    /**
     * Scan SVG sprite for icons
     */
    private function _scanSvgSprite(IconSet $iconSet): array
    {
        $icons = [];
        $settings = $iconSet->getTypeSettings();
        $spriteFile = $settings['spriteFile'] ?? '';
        $prefix = $settings['prefix'] ?? '';

        if (empty($spriteFile)) {
            return $icons;
        }

        $basePath = IconManager::getInstance()->getSettings()->getResolvedIconSetsPath();
        $spritePath = FileHelper::normalizePath($basePath . DIRECTORY_SEPARATOR . $spriteFile);

        if (!file_exists($spritePath)) {
            return $icons;
        }

        // Parse SVG sprite file
        $dom = new \DOMDocument();
        @$dom->load($spritePath);
        $symbols = $dom->getElementsByTagName('symbol');

        foreach ($symbols as $symbol) {
            $id = $symbol->getAttribute('id');
            if (empty($id)) {
                continue;
            }

            // Remove prefix if present
            $name = $prefix && str_starts_with($id, $prefix) 
                ? substr($id, strlen($prefix)) 
                : $id;

            $label = StringHelper::titleize($name);

            $icons[] = [
                'name' => $name,
                'label' => $label,
                'path' => '',
                'keywords' => [$name],
                'metadata' => [
                    'type' => Icon::TYPE_SPRITE,
                    'spriteId' => $id,
                    'spriteFile' => $spriteFile,
                ],
            ];
        }

        return $icons;
    }

    /**
     * Get Font Awesome icons
     */
    private function _getFontAwesomeIcons(IconSet $iconSet): array
    {
        // Use the dedicated Font Awesome handler
        $handler = new \lindemannrock\iconmanager\iconsets\FontAwesome($iconSet);
        return $handler->getIcons();
    }

    /**
     * Get Material Icons
     */
    private function _getMaterialIcons(IconSet $iconSet): array
    {
        // Use the dedicated Material Icons handler
        $handler = new \lindemannrock\iconmanager\iconsets\MaterialIcons($iconSet);
        return $handler->getIcons();
    }

    /**
     * Extract keywords from SVG file
     */
    private function _extractSvgKeywords(string $file): array
    {
        $keywords = [];
        
        // Check for metadata file
        $metadataFile = dirname($file) . '/metadata.json';
        if (file_exists($metadataFile)) {
            $metadata = json_decode(file_get_contents($metadataFile), true);
            $filename = pathinfo($file, PATHINFO_FILENAME);
            if (isset($metadata[$filename]['keywords'])) {
                $keywords = $metadata[$filename]['keywords'];
            }
        }

        // Also add parts of the filename as keywords
        $name = pathinfo($file, PATHINFO_FILENAME);
        $parts = preg_split('/[-_\s]+/', $name);
        $keywords = array_merge($keywords, $parts);

        return array_unique($keywords);
    }

    /**
     * Create icon from database record
     */
    private function _createIconFromRecord(array $record, IconSet $iconSet): Icon
    {
        $metadata = json_decode($record['metadata'], true) ?: [];
        
        $icon = new Icon();
        $icon->id = $record['id'];
        $icon->iconSetId = $record['iconSetId'];
        $icon->iconSetHandle = $iconSet->handle;
        $icon->name = $record['name'];
        $icon->label = $record['label'];
        $icon->path = $record['path'];
        $icon->keywords = json_decode($record['keywords'], true) ?: [];
        $icon->metadata = $metadata;
        
        // Set type from metadata
        $icon->type = $metadata['type'] ?? Icon::TYPE_SVG;
        
        // Set value based on type
        switch ($icon->type) {
            case Icon::TYPE_SPRITE:
                $icon->value = $metadata['spriteId'] ?? $icon->name;
                break;
            case Icon::TYPE_FONT:
                $icon->value = $metadata['className'] ?? $icon->name;
                break;
            default:
                $icon->value = $icon->name;
        }

        return $icon;
    }
    
    /**
     * Clear memory cache
     */
    public function clearMemoryCache(): void
    {
        $this->_iconsBySetId = [];
    }
}