<?php

/**
 * @copyright Copyright (c) vnali
 */

namespace vnali\counter\assets;

use craft\web\AssetBundle;
use craft\web\assets\admintable\AdminTableAsset;

/**
 * Asset Bundle used for counter widgets which have table.
 */
class CounterWidgetTableAsset extends AssetBundle
{
    /**
     * @inheritDoc
     */
    public function init()
    {
        $this->sourcePath = "@vnali/counter/resources";

        $this->depends = [
            AdminTableAsset::class,
        ];

        $this->css = [
            'css/counter-widget.css',
        ];

        parent::init();
    }
}
