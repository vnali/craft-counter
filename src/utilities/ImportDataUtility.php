<?php

namespace vnali\counter\utilities;

use Craft;
use craft\base\Utility;

class ImportDataUtility extends Utility
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('counter', 'Counter');
    }

    /**
     * @inheritdoc
     */
    public static function id(): string
    {
        return 'counter-import-data';
    }

    /**
     * @inheritdoc
     */
    public static function iconPath(): ?string
    {
        return Craft::getAlias('@vnali/counter/icon-mask.svg');
    }

    /**
     * @inheritdoc
     */
    public static function contentHtml(): string
    {
        $view = Craft::$app->getView();
        return $view->renderTemplate('counter/_utilities/import-data.twig');
    }
}
