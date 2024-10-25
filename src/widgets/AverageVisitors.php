<?php

namespace vnali\counter\widgets;

use Craft;
use craft\base\Widget;
use craft\helpers\DateTimeHelper;
use craft\helpers\StringHelper;
use Exception;
use vnali\counter\assets\CounterWidgetChartAsset;
use vnali\counter\base\DateWidgetTrait;
use vnali\counter\Counter;
use vnali\counter\helpers\StringHelper as CounterStringHelper;
use vnali\counter\models\Settings;
use vnali\counter\stats\AverageVisitors as AverageVisitorsStat;
use vnali\counter\validators\SiteValidator;

class AverageVisitors extends Widget
{
    use DateWidgetTrait;

    private ?AverageVisitorsStat $_stat = null;

    public ?bool $showChart = null;

    public ?string $siteId = null;

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

        $this->_stat = new AverageVisitorsStat(
            $this->dateRange,
            DateTimeHelper::toDateTime($this->startDate, true),
            DateTimeHelper::toDateTime($this->endDate, true),
            $this->calendar,
            $this->siteId,
        );
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
        return Craft::t('counter', 'Average Daily Visitors');
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
        return Craft::t('counter', 'Average Daily Visitors');
    }

    /**
     * @inheritDoc
     */
    public function getSubtitle(): ?string
    {
        $site = null;
        // if it is multi-site, mention the site
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

        // Convert dateRanges like past30Days to Past 30 Days
        $dateRange = preg_replace('/([A-Z])|(\d+)/', ' $0', $this->dateRange);
        $dateRange = CounterStringHelper::toSentenceCase($dateRange);

        // If date range is custom, mention start date and end date.
        if ($this->dateRange == 'custom') {
            $dateRange = '(' . $this->startDate . ' ' . craft::t('counter', 'to') . ' ' . $this->endDate . ')';
        }

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

        list($number, $label, $visitorsData) = $this->_stat->get();
        $timeFrame = $this->_stat->getDateRangeWording();
        $widget = $this;

        $view = Craft::$app->getView();
        $id = 'average-visitors' . StringHelper::randomString();
        $namespaceId = $view->namespaceInputId($id);
        $view->registerAssetBundle(CounterWidgetChartAsset::class);

        return $view->renderTemplate('counter/_components/widgets/average-visitors/body', compact('widget', 'number', 'label', 'visitorsData', 'timeFrame', 'namespaceId'));
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
        $id = 'average-visitors' . StringHelper::randomString();
        $namespaceId = Craft::$app->getView()->namespaceInputId($id);

        /** @var Settings $settings */
        $settings = Counter::$plugin->getSettings();
        $showAllCalendars = $settings->showAllCalendars;

        return Craft::$app->getView()->renderTemplate('counter/_components/widgets/average-visitors/settings', [
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
        $rules[] = [['calendar'], 'in', 'range' => ['gregorian']];
        $rules[] = [['showChart'], 'in', 'range' => ['0', '1']];
        $rules[] = [['siteId'], SiteValidator::class, 'skipOnEmpty' => false];

        return $rules;
    }
}
