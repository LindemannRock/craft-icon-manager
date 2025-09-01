<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\iconmanager\web\assets\field;

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

        $this->js = [
            'IconManagerField.js',
        ];

        parent::init();
    }
}