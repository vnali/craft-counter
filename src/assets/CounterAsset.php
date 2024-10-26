<?php
/**
 * @copyright Copyright (c) vnali
 */

namespace vnali\counter\assets;

use Craft;
use craft\helpers\UrlHelper;
use craft\web\AssetBundle;
use craft\web\View;
use vnali\counter\Counter;
use vnali\counter\models\Settings;
use yii\web\JqueryAsset;

/**
 * Asset Bundle used for counter.
 */
class CounterAsset extends AssetBundle
{
    /**
     * @inheritDoc
     */
    public function init()
    {
        $this->sourcePath = "@vnali/counter/resources";

        $pluginSettings = Counter::$plugin->getSettings();
        /** @var Settings $pluginSettings */
        $siteSettings = json_decode($pluginSettings->siteSettings, true);
        $sitesService = Craft::$app->getSites();
        $currentSite = $sitesService->getCurrentSite();
        $enabledCounter = false;
        $siteUnique = $currentSite->uid;

        if (isset($siteSettings[$siteUnique]['enabledCounter'])) {
            $enabledCounter = $siteSettings[$siteUnique]['enabledCounter'];
        }

        // Register only if site or page visitor is enabled
        if ($enabledCounter) {
            // If we should support outdated browsers, we include Jquery.
            if ($pluginSettings->supportOutdatedBrowsers) {
                $this->depends = [
                    JqueryAsset::class,
                ];
            }
            
            $this->js = [
                'js/counter.js',
            ];
        }

        parent::init();
    }

    /**
     * @inheritDoc
     */
    public function registerAssetFiles($view)
    {
        parent::registerAssetFiles($view);

        $pluginSettings = Counter::$plugin->getSettings();
        /** @var Settings $pluginSettings */
        $supportOutdatedBrowsers = $pluginSettings->supportOutdatedBrowsers;
        $sessionInfoUrl = UrlHelper::actionUrl('users/session-info');
        $counterUrl = UrlHelper::Url('counter/counter/count');
        $js = <<<JS
                window.supportOutdatedBrowsers = '$supportOutdatedBrowsers';
                window.sessionInfoUrl = '$sessionInfoUrl';
                window.counterUrl = '$counterUrl';
JS;
        $view->registerJs($js, View::POS_HEAD);
    }
}
