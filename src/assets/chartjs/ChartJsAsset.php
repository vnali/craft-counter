<?php

namespace vnali\counter\assets\chartjs;

use craft\web\AssetBundle;

/**
 * ChartJs asset bundle
 */
class ChartJsAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/dist';

        $this->js = [
            'chart.umd.min.js',
        ];

        parent::init();
    }
}
