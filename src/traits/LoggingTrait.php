<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * Trait for consistent logging across services
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\iconmanager\traits;

use Craft;

/**
 * Logging trait for Icon Manager
 * Provides consistent logging to the dedicated icon-manager.log file
 */
trait LoggingTrait
{
    /**
     * Log an info message
     */
    protected function logInfo(string $message, array $params = []): void
    {
        Craft::info($this->formatMessage($message, $params), 'icon-manager');
    }

    /**
     * Log a warning message
     */
    protected function logWarning(string $message, array $params = []): void
    {
        Craft::warning($this->formatMessage($message, $params), 'icon-manager');
    }

    /**
     * Log an error message
     */
    protected function logError(string $message, array $params = []): void
    {
        Craft::error($this->formatMessage($message, $params), 'icon-manager');
    }

    /**
     * Log a trace message (most verbose level for debugging internal operations)
     */
    protected function logTrace(string $message, array $params = []): void
    {
        Craft::trace($this->formatMessage($message, $params), 'icon-manager');
    }

    /**
     * Format message with parameters
     */
    private function formatMessage(string $message, array $params = []): string
    {
        if (empty($params)) {
            return $message;
        }

        // Substitute placeholders in the message with actual values
        $formattedMessage = $message;
        foreach ($params as $key => $value) {
            $placeholder = '{' . $key . '}';
            if (strpos($formattedMessage, $placeholder) !== false) {
                $formattedMessage = str_replace($placeholder, (string)$value, $formattedMessage);
                // Remove the substituted parameter from params so it's not shown in JSON context
                unset($params[$key]);
            }
        }

        // Add remaining parameters as JSON context if any
        if (!empty($params)) {
            return $formattedMessage . ' | ' . json_encode($params);
        }

        return $formattedMessage;
    }
}