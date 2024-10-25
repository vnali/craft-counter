<?php

/**
 * @copyright Copyright (c) vnali
 */

namespace vnali\counter\assets;

use craft\web\AssetBundle;
use yii\web\JqueryAsset;

/**
 * Asset Bundle used for counter widgets setting.
 */
class CounterWidgetSettingsAsset extends AssetBundle
{
    /**
     * @inheritDoc
     */
    public function init()
    {
        $this->sourcePath = "@vnali/counter/resources";

        $this->depends = [
            JqueryAsset::class,
        ];

        $this->js = [
            'js/counter-widget-settings.js',
        ];

        $this->css = [
            'css/counter-widget-settings.css',
        ];

        parent::init();
    }
}
