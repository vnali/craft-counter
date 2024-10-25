<?php

/**
 * @copyright Copyright (c) vnali
 */

namespace vnali\counter\assets;

use craft\web\AssetBundle;
use vnali\counter\assets\chartjs\ChartJsAsset;

/**
 * Asset Bundle used for counter widgets which have chart.
 */
class CounterWidgetChartAsset extends AssetBundle
{
    /**
     * @inheritDoc
     */
    public function init()
    {
        $this->sourcePath = "@vnali/counter/resources";

        $this->depends = [
            ChartJsAsset::class,
        ];

        $this->css = [
            'css/counter-widget.css',
        ];

        parent::init();
    }
}
