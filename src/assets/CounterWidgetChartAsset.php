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
            CounterWidgetAsset::class,
            ChartJsAsset::class,
        ];

        parent::init();
    }
}
