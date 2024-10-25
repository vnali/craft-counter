<?php

namespace vnali\counter\widgets;

use Craft;
use craft\base\Widget;
use craft\helpers\StringHelper;
use Exception;
use vnali\counter\assets\CounterWidgetTableAsset;
use vnali\counter\Counter;
use vnali\counter\helpers\StringHelper as CounterStringHelper;
use vnali\counter\models\Settings;
use vnali\counter\validators\SiteValidator;

class NotVisitedPages extends Widget
{
    public ?string $siteId = null;

    public ?string $dateRange = 'today';

    public int $limit = 5;

    public ?bool $sortAsc = null;

    public ?string $calendar = null;

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
        return Craft::t('counter', 'Not Visited Pages');
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
        return Craft::t('counter', 'Not Visited Pages');
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

        $dateRange = preg_replace('/([A-Z])|(\d+)/', ' $0', $this->dateRange);
        $dateRange = CounterStringHelper::toSentenceCase($dateRange);

        return $dateRange . ($site ? (' - ' . $site) : '');
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
        $notVisitedPages = Counter::$plugin->pages->notVisited($this->dateRange, $this->siteId, $this->limit, $this->sortAsc, $this->calendar);

        $view = Craft::$app->getView();

        $id = 'not-visited-pages' . StringHelper::randomString();
        $namespaceId = $view->namespaceInputId($id);
        $view->registerAssetBundle(CounterWidgetTableAsset::class);

        $widget = $this;

        return $view->renderTemplate('counter/_components/widgets/not-visited-pages/body', compact('widget', 'id', 'notVisitedPages', 'namespaceId'));
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
        $id = 'not-visited-pages' . StringHelper::randomString();
        $namespaceId = Craft::$app->getView()->namespaceInputId($id);

        /** @var Settings $settings */
        $settings = Counter::$plugin->getSettings();
        $showAllCalendars = $settings->showAllCalendars;

        return Craft::$app->getView()->renderTemplate('counter/_components/widgets/not-visited-pages/settings', [
            'id' => $id,
            'namespaceId' => $namespaceId,
            'widget' => $this,
            'showAllCalendars' => $showAllCalendars,
        ]);
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['dateRange'], 'safe'];
        $rules[] = [['sortAsc'], 'in', 'range' => ['0', '1']];
        $rules[] = [['limit'], 'integer', 'min' => 1, 'max' => 20];
        $rules[] = [['siteId'], SiteValidator::class, 'skipOnEmpty' => false];

        return $rules;
    }
}
