<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\iconmanager\twigextensions;

use lindemannrock\iconmanager\IconManager;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Plugin Name Twig Extension
 *
 * Provides centralized access to plugin name variations in Twig templates.
 *
 * Usage in templates:
 * - {{ iconHelper.displayName }}             // "Icon" (singular, no Manager)
 * - {{ iconHelper.pluralDisplayName }}       // "Icons" (plural, no Manager)
 * - {{ iconHelper.fullName }}                // "Icon Manager" (as configured)
 * - {{ iconHelper.lowerDisplayName }}        // "icon" (lowercase singular)
 * - {{ iconHelper.pluralLowerDisplayName }}  // "icons" (lowercase plural)
 * @since 1.0.0
 */
class PluginNameExtension extends AbstractExtension implements GlobalsInterface
{
    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return 'Icon Manager - Plugin Name Helper';
    }

    /**
     * Make plugin name helper available as global Twig variable
     *
     * @return array
     */
    public function getGlobals(): array
    {
        return [
            'iconHelper' => new PluginNameHelper(),
        ];
    }
}

/**
 * Plugin Name Helper
 *
 * Helper class that exposes Settings methods as properties for clean Twig syntax.
 */
class PluginNameHelper
{
    /**
     * Get display name (singular, without "Manager")
     *
     * @return string
     */
    public function getDisplayName(): string
    {
        return IconManager::$plugin->getSettings()->getDisplayName();
    }

    /**
     * Get plural display name (without "Manager")
     *
     * @return string
     */
    public function getPluralDisplayName(): string
    {
        return IconManager::$plugin->getSettings()->getPluralDisplayName();
    }

    /**
     * Get full plugin name (as configured)
     *
     * @return string
     */
    public function getFullName(): string
    {
        return IconManager::$plugin->getSettings()->getFullName();
    }

    /**
     * Get lowercase display name (singular, without "Manager")
     *
     * @return string
     */
    public function getLowerDisplayName(): string
    {
        return IconManager::$plugin->getSettings()->getLowerDisplayName();
    }

    /**
     * Get lowercase plural display name (without "Manager")
     *
     * @return string
     */
    public function getPluralLowerDisplayName(): string
    {
        return IconManager::$plugin->getSettings()->getPluralLowerDisplayName();
    }

    /**
     * Magic getter to allow property-style access in Twig
     * Enables: {{ iconHelper.displayName }} instead of {{ iconHelper.getDisplayName() }}
     *
     * @param string $name
     * @return string|null
     */
    public function __get(string $name): ?string
    {
        $method = 'get' . ucfirst($name);
        if (method_exists($this, $method)) {
            return $this->$method();
        }
        return null;
    }
}
