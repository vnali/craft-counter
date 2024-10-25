<?php

namespace vnali\counter\widgets;

use Craft;
use craft\base\Widget;
use craft\helpers\StringHelper;
use Exception;
use vnali\counter\assets\CounterWidgetTableAsset;
use vnali\counter\Counter;
use vnali\counter\helpers\StringHelper as CounterStringHelper;
use vnali\counter\validators\SiteValidator;

class TrendingPages extends Widget
{
    public ?string $siteId = null;

    public ?string $dateRange = 'today';

    public ?string $growthType = 'count';

    public ?bool $ignoreNewPages = null;

    public int $limit = 5;

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
        return Craft::t('counter', 'Trending Pages');
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
        return Craft::t('counter', 'Trending Pages');
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
        // Check again if user still has access to the site
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

        $trendingPages = Counter::$plugin->pages->trending($this->dateRange, $this->siteId, $this->growthType, $this->ignoreNewPages, $this->limit);
        
        $now = null;
        $before = null;
        switch ($this->dateRange) {
            case 'today':
                $now = 'Today';
                $before = 'Yesterday';
                break;
            case 'thisWeek':
                $now = 'This week';
                $before = 'Previous week';
                break;
            case 'thisMonth':
                $now = 'This month';
                $before = 'Previous month';
                break;
            case 'thisYear':
                $now = 'This year';
                $before = 'Previous year';
                break;
            default:
                # code...
                break;
        }
        
        $view = Craft::$app->getView();
        $id = 'trending-pages' . StringHelper::randomString();
        $namespaceId = $view->namespaceInputId($id);
        $view->registerAssetBundle(CounterWidgetTableAsset::class);

        $widget = $this;

        return $view->renderTemplate('counter/_components/widgets/trending-pages/body', compact('widget', 'trendingPages', 'id', 'namespaceId', 'now', 'before'));
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
        $id = 'trending-pages' . StringHelper::randomString();
        $namespaceId = Craft::$app->getView()->namespaceInputId($id);

        return Craft::$app->getView()->renderTemplate('counter/_components/widgets/trending-pages/settings', [
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
        $rules[] = [['growthType'], 'in', 'range' => ['percentage', 'count']];
        $rules[] = [['dateRange'], 'safe'];
        $rules[] = [['ignoreNewPages'], 'in', 'range' => ['0', '1']];
        $rules[] = [['limit'], 'integer', 'min' => 1, 'max' => 20];
        $rules[] = [['siteId'], SiteValidator::class, 'skipOnEmpty' => false];

        return $rules;
    }
}
