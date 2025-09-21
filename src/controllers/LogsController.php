<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\iconmanager\controllers;

use lindemannrock\iconmanager\IconManager;
use Craft;
use craft\web\Controller;
use craft\web\Response;
use yii\web\NotFoundHttpException;

/**
 * Logs Controller
 *
 * Handles log viewing and management
 */
class LogsController extends Controller
{
    /**
     * View logs
     */
    public function actionIndex(): Response
    {
        $this->requirePermission('accessPlugin-icon-manager');

        $request = Craft::$app->getRequest();

        // Get filter parameters
        $level = $request->getParam('level', 'all');
        $date = $request->getParam('date', (new \DateTime())->format('Y-m-d'));
        $search = $request->getParam('search', '');
        $page = (int) $request->getParam('page', 1);
        $limit = 50; // Entries per page

        // Get available log files
        $logFiles = $this->_getAvailableLogFiles();

        // Read and parse log entries
        $logEntries = $this->_getLogEntries($date, $level, $search, $page, $limit);

        // Get total count for pagination
        $totalEntries = $this->_getLogEntriesCount($date, $level, $search);

        // Calculate pagination info
        $totalPages = ceil($totalEntries / $limit);

        return $this->renderTemplate('icon-manager/logs/index', [
            'pluginName' => IconManager::getInstance()->getSettings()->pluginName ?? 'Icon Manager',
            'logFiles' => $logFiles,
            'logEntries' => $logEntries,
            'filters' => [
                'level' => $level,
                'date' => $date,
                'search' => $search,
                'page' => $page,
            ],
            'pagination' => [
                'total' => $totalEntries,
                'perPage' => $limit,
                'currentPage' => $page,
                'totalPages' => $totalPages,
            ],
            'levels' => [
                'all' => 'All Levels',
                'error' => 'Error',
                'warning' => 'Warning',
                'info' => 'Info',
                'trace' => 'Trace',
            ],
        ]);
    }

    /**
     * Download a log file
     */
    public function actionDownload(): Response
    {
        $this->requirePermission('accessPlugin-icon-manager');

        $date = Craft::$app->getRequest()->getRequiredParam('date');

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new \InvalidArgumentException('Invalid date format');
        }

        $logPath = Craft::$app->getPath()->getLogPath() . "/icon-manager-{$date}.log";

        if (!file_exists($logPath)) {
            throw new NotFoundHttpException('Log file not found');
        }

        return Craft::$app->getResponse()->sendFile($logPath, "icon-manager-{$date}.log", [
            'mimeType' => 'text/plain',
        ]);
    }

    /**
     * Get available log files
     */
    private function _getAvailableLogFiles(): array
    {
        $logPath = Craft::$app->getPath()->getLogPath();
        $files = [];

        if (is_dir($logPath)) {
            $pattern = $logPath . '/icon-manager-*.log';
            $logFiles = glob($pattern);

            if ($logFiles) {
                // Sort by date (newest first)
                rsort($logFiles);

                foreach ($logFiles as $file) {
                    if (preg_match('/icon-manager-(\d{4}-\d{2}-\d{2})\.log$/', basename($file), $matches)) {
                        $date = $matches[1];
                        $files[] = [
                            'value' => $date,
                            'label' => date('Y-m-d (D)', strtotime($date))
                        ];
                    }
                }
            }
        }

        return $files;
    }

    /**
     * Get log entries for a specific date
     */
    private function _getLogEntries(string $date, string $level, string $search, int $page, int $limit): array
    {
        $logPath = Craft::$app->getPath()->getLogPath() . "/icon-manager-{$date}.log";

        if (!file_exists($logPath)) {
            return [];
        }

        $entries = [];
        $file = fopen($logPath, 'r');

        if ($file) {
            while (($line = fgets($file)) !== false) {
                $entry = $this->_parseLogLine($line);
                if ($entry && $this->_matchesFilters($entry, $level, $search)) {
                    $entries[] = $entry;
                }
            }
            fclose($file);
        }

        // Reverse to show newest first
        $entries = array_reverse($entries);

        // Apply pagination
        $offset = ($page - 1) * $limit;
        return array_slice($entries, $offset, $limit);
    }

    /**
     * Get total count of log entries for pagination
     */
    private function _getLogEntriesCount(string $date, string $level, string $search): int
    {
        $logPath = Craft::$app->getPath()->getLogPath() . "/icon-manager-{$date}.log";

        if (!file_exists($logPath)) {
            return 0;
        }

        $count = 0;
        $file = fopen($logPath, 'r');

        if ($file) {
            while (($line = fgets($file)) !== false) {
                $entry = $this->_parseLogLine($line);
                if ($entry && $this->_matchesFilters($entry, $level, $search)) {
                    $count++;
                }
            }
            fclose($file);
        }

        return $count;
    }

    /**
     * Parse a log line into components
     */
    private function _parseLogLine(string $line): ?array
    {
        // Skip empty lines
        if (empty(trim($line))) {
            return null;
        }

        // Match old Yii2 format: timestamp [ip][user][session][level][category] message
        if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\s+\[([^\]]*)\]\[([^\]]*)\]\[([^\]]*)\]\[([^\]]*)\]\[([^\]]*)\]\s+(.*)$/', $line, $matches)) {
            return [
                'timestamp' => $matches[1],
                'ip' => $matches[2],
                'user' => $matches[3] ?: 'guest',
                'session' => $matches[4],
                'level' => strtolower($matches[5]),
                'category' => $matches[6],
                'message' => trim($matches[7]),
                'raw' => $line
            ];
        }

        // Match new format: timestamp [user:id][level][category] message
        if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\s+\[([^\]]+)\]\[([^\]]+)\]\[([^\]]+)\]\s+(.*)$/', $line, $matches)) {
            return [
                'timestamp' => $matches[1],
                'ip' => '',
                'user' => $matches[2],
                'session' => '',
                'level' => strtolower($matches[3]),
                'category' => $matches[4],
                'message' => trim($matches[5]),
                'raw' => $line
            ];
        }

        return null;
    }

    /**
     * Check if entry matches filters
     */
    private function _matchesFilters(array $entry, string $level, string $search): bool
    {
        // Level filter
        if ($level !== 'all' && $entry['level'] !== $level) {
            return false;
        }

        // Search filter
        if (!empty($search)) {
            $searchLower = strtolower($search);
            if (stripos($entry['message'], $searchLower) === false) {
                return false;
            }
        }

        return true;
    }
}