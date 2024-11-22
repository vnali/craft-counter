<?php

/**
 * @copyright Copyright (c) vnali
 */

namespace vnali\counter\assets;

use craft\helpers\UrlHelper;
use craft\web\AssetBundle;
use craft\web\View;

/**
 * Asset Bundle used for counter widgets which have table.
 */
class CounterWidgetAsset extends AssetBundle
{
    /**
     * @inheritDoc
     */
    public function init()
    {
        parent::init();

        $this->sourcePath = "@vnali/counter/resources";

        $this->css = [
            'css/counter-widget.css',
        ];
    }

    /**
     * @inheritDoc
     */
    public function registerAssetFiles($view)
    {
        parent::registerAssetFiles($view);
        $widgetDataUrl = UrlHelper::Url('counter/widget/data');
        $js = <<<JS
            window.counterCharts = {};
            window.counterChartsData = {};
            window.counterChartsOptions = {};
            window.counterIntervals = {};
            window.counterWidgetDataUrl = '$widgetDataUrl';
JS;
        $view->registerJs($js, View::POS_HEAD);
    }
}
