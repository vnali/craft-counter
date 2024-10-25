<?php


namespace vnali\counter\validators;

use Craft;
use craft\db\Table;
use craft\helpers\Db;
use yii\validators\Validator;

/**
 * Site validator class
 */
class SiteValidator extends Validator
{
    public function validateAttribute($model, $attribute)
    {
        if (Craft::$app->getIsMultiSite()) {
            $siteId = $model->$attribute;
            $currentUser = Craft::$app->getUser()->getIdentity();

            if ($siteId && $siteId != '*') {
                $siteUid = Db::uidById(Table::SITES, $siteId);
                if (!$siteUid || !$currentUser->can('editSite:' . $siteUid)) {
                    $this->addError($model, $attribute, 'The user can not access site');
                }
            } elseif ($siteId && $siteId == '*') {
                $sites = Craft::$app->getSites()->getAllSites();
                foreach ($sites as $site) {
                    if (!$currentUser->can('editSite:' . $site->uid)) {
                        $this->addError($model, $attribute, 'The user can not access site');
                    }
                }
            } else {
                $this->addError($model, $attribute, 'The site should be specified');
            }
        }
    }
}
