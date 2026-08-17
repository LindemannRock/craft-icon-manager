<?php
/**
 * Icon Manager test fixture.
 *
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace craft\cachecascade;

use yii\caching\Cache;

/**
 * Minimal managed-cache fixture for Base capability classification.
 *
 * @since 5.18.0
 */
final class CascadeCache extends Cache
{
    /** @var array<string, mixed> */
    private array $values = [];

    protected function getValue($key)
    {
        return $this->values[$key] ?? false;
    }

    protected function setValue($key, $value, $duration)
    {
        $this->values[$key] = $value;
        return true;
    }

    protected function addValue($key, $value, $duration)
    {
        if (array_key_exists($key, $this->values)) {
            return false;
        }

        return $this->setValue($key, $value, $duration);
    }

    protected function deleteValue($key)
    {
        unset($this->values[$key]);
        return true;
    }

    protected function flushValues()
    {
        $this->values = [];
        return true;
    }
}
