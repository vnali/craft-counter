<?php

namespace vnali\counter\widgets;

use Craft;
use craft\base\Widget;
use craft\helpers\StringHelper;
use Exception;
use vnali\counter\assets\CounterWidgetChartAsset;
use vnali\counter\Counter;
use vnali\counter\validators\SiteValidator;

class Online extends Widget
{
    public ?string $siteId = null;

    public int $onlineThreshold = 60;

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function init(): void
    {
        parent::init();
        if (!$this->siteId) {
            $site = Craft::$app->sites->getPrimarySite();
            $this->siteId = (string)$site->id;
        }
    }

    /**
     * @inheritdoc
     */
    public static function isSelectable(): bool
    {
        return Craft::$app->getUser()->checkPermission('counter-accessWidgets');
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('counter', 'Online Visitors');
    }

    /**
     * @inheritdoc
     */
    public static function icon(): ?string
    {
        return Craft::getAlias('@vnali/counter/icon-mask.svg');
    }


    /**
     * @inheritdoc
     */
    public function getTitle(): ?string
    {
        return Craft::t('counter', craft::t('counter', 'Online in {onlineThreshold} seconds', ['onlineThreshold' => $this->onlineThreshold]));
    }

    /**
     * @inheritDoc
     */
    public function getSubtitle(): ?string
    {
        $site = null;
        if (Craft::$app->getIsMultiSite()) {
            if ($this->siteId != '*') {
                $site = Craft::$app->getSites()->getSiteById((int) $this->siteId);
                if ($site) {
                    $site = craft::t('site', $site->name);
                }
            } else {
                $site = craft::t('counter', 'All sites');
            }
        }
        return $site;
    }

    /**
     * @inheritdoc
     */
    public function getBodyHtml(): ?string
    {
        // check again if user still has access to the site
        if (Craft::$app->getIsMultiSite()) {
            $currentUser = Craft::$app->getUser()->getIdentity();
            if ($this->siteId == '*') {
                $sites = Craft::$app->getSites()->getAllSites();
                foreach ($sites as $site) {
                    if (!$currentUser->can('editSite:' . $site->uid)) {
                        return '';
                    }
                }
            } else {
                $site = Craft::$app->sites->getSiteById((int) $this->siteId);
                if (!$site || !$currentUser->can('editSite:' . $site->uid)) {
                    return '';
                }
            }
        } else {
            if ($this->siteId == '*') {
                return '';
            } else {
                $site = Craft::$app->sites->getSiteById((int) $this->siteId);
                if (!$site) {
                    return '';
                }
            }
        }
        $onlineThreshold = $this->onlineThreshold;
        $number = Counter::$plugin->counter->onlineVisitors($this->siteId, $this->onlineThreshold);

        $view = Craft::$app->getView();
        $id = 'online' . StringHelper::randomString();
        $namespaceId = $view->namespaceInputId($id);
        $view->registerAssetBundle(CounterWidgetChartAsset::class);

        return $view->renderTemplate('counter/_components/widgets/online/body', compact('number', 'onlineThreshold', 'namespaceId'));
    }

    /**
     * @inheritDoc
     */
    public static function maxColspan(): ?int
    {
        return 3;
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        $id = 'online' . StringHelper::randomString();
        $namespaceId = Craft::$app->getView()->namespaceInputId($id);

        return Craft::$app->getView()->renderTemplate('counter/_components/widgets/online/settings', [
            'id' => $id,
            'namespaceId' => $namespaceId,
            'widget' => $this,
        ]);
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['onlineThreshold'], 'integer', 'min' => '1', 'max' => '3600'];
        $rules[] = [['siteId'], SiteValidator::class, 'skipOnEmpty' => false];

        return $rules;
    }
}
