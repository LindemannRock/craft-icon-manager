<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\iconmanager\web\assets\field;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * Icon Manager Field Asset Bundle
 */
class IconManagerFieldAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/dist';

        $this->depends = [
            CpAsset::class,
        ];

        // Use minified JS in production
        $this->js = [
            Craft::$app->getConfig()->getGeneral()->devMode ? 'IconManagerField.js' : 'IconManagerField.min.js',
        ];

        parent::init();
    }
}
