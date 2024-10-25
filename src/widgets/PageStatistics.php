<?php

namespace vnali\counter\widgets;

use Craft;
use craft\base\Widget;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use Exception;
use vnali\counter\assets\CounterWidgetTableAsset;
use vnali\counter\base\DateWidgetTrait;
use vnali\counter\Counter;
use vnali\counter\helpers\StringHelper as HelpersStringHelper;
use vnali\counter\records\PageVisitsRecord;

class PageStatistics extends Widget
{
    use DateWidgetTrait;

    public ?int $pageId = null;

    public string|array|null $items = null;

    public ?int $nextPagesLimit = null;

    public ?string $type = null;

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function init(): void
    {
        parent::init();
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
        return Craft::t('counter', 'Page Visits Statistics');
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
        return Craft::t('counter', craft::t('counter', 'Page Visits Statistics'));
    }

    /**
     * @inheritDoc
     */
    public function getSubtitle(): ?string
    {
        $page = null;
        $pageRecord = PageVisitsRecord::find()->where(['id' => $this->pageId])->one();

        /** @var PageVisitsRecord|null  $pageRecord */
        if ($pageRecord) {
            $page = $pageRecord->page;
        }

        return $page;
    }

    /**
     * @inheritdoc
     */
    public function getBodyHtml(): ?string
    {
        $pageVisits = [];
        $pageRecord = PageVisitsRecord::find()->where(['id' => $this->pageId])->one();

        /** @var PageVisitsRecord|null  $pageRecord */
        if ($pageRecord) {
            $siteId = $pageRecord->siteId;
            // If site is not available
            $site = Craft::$app->sites->getSiteById($siteId);
            if (!$site) {
                return '';
            }
            if (Craft::$app->getIsMultiSite()) {
                $currentUser = Craft::$app->getUser()->getIdentity();
                if (!$currentUser->can('editSite:' . $site->uid)) {
                    return '';
                }
            }
            if (!is_array($this->items)) {
                return '';
            }

            $results = Counter::$plugin->pages->visits($pageRecord->page, '*', []);
            unset($results['debugMessage']);
            $items = $this->items ?? [];
            foreach ($results as $key => $result) {
                if (in_array($key, $items)) {
                    if ($key == 'allIgnoreInterval') {
                        $key = Craft::t('counter', 'All (ignore interval)');
                    } else {
                        $key = preg_replace('/([A-Z])/', ' $1', $key);
                        $key = craft::t('counter', HelpersStringHelper::toSentenceCase($key));
                    }
                    $pageVisits[$key] = $result;
                }
            }
        }

        $view = Craft::$app->getView();
        $id = 'page-statistics' . StringHelper::randomString();
        $namespaceId = $view->namespaceInputId($id);
        $view->registerAssetBundle(CounterWidgetTableAsset::class);

        return $view->renderTemplate('counter/_components/widgets/page-statistics/body', compact('namespaceId', 'pageVisits'));
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
        $id = 'page-statistics' . StringHelper::randomString();
        $namespaceId = Craft::$app->getView()->namespaceInputId($id);

        $items = [
            'all' => craft::t('counter', 'All Time'),
            'allIgnoreInterval' => craft::t('counter', 'All time (ignore interval)'),
            'today' => craft::t('counter', 'Today'),
            'yesterday' => craft::t('counter', 'Yesterday'),
            'thisWeek' => craft::t('counter', 'This week'),
            'previousWeek' => craft::t('counter', 'Previous week'),
            'thisMonth' => craft::t('counter', 'This month'),
            'previousMonth' => craft::t('counter', 'Previous month'),
            'thisYear' => craft::t('counter', 'This year'),
            'previousYear' => craft::t('counter', 'Previous year'),
            'lastVisit' => craft::t('counter', 'Last visit'),
        ];

        $page = null;
        $pageRecord = PageVisitsRecord::find()->where(['id' => $this->pageId])->one();
        /** @var PageVisitsRecord|null  $pageRecord */
        if ($pageRecord) {
            $page = $pageRecord->page;
        }
        $pageListURL = UrlHelper::Url('counter/counter/pages');
        $sessionInfoUrl = UrlHelper::actionUrl('users/session-info');

        return Craft::$app->getView()->renderTemplate('counter/_components/widgets/page-statistics/settings', [
            'id' => $id,
            'namespaceId' => $namespaceId,
            'widget' => $this,
            'items' => $items,
            'selectedPage' => $page,
            'pageListURL' => $pageListURL,
            'sessionInfoUrl' => $sessionInfoUrl,
        ]);
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['pageId'], 'integer', 'skipOnEmpty' => false];
        $rules[] = [['pageId'], function($attribute, $params, $validator) {
            $pageRecord = PageVisitsRecord::find()->where(['id' => $this->pageId])->one();
            /** @var PageVisitsRecord|null  $pageRecord */
            if (!$pageRecord) {
                $this->addError($attribute, 'The selected page is not valid');
            } elseif (Craft::$app->getIsMultiSite()) {
                $siteId = $pageRecord->siteId;
                $site = Craft::$app->sites->getSiteById($siteId);
                $currentUser = Craft::$app->getUser()->getIdentity();
                if (!$currentUser->can('editSite:' . $site->uid)) {
                    $this->addError($attribute, 'The user has no access to the selected page');
                }
            }
        }, 'skipOnEmpty' => false];
        $items = [
            'all',
            'allIgnoreInterval',
            'today',
            'yesterday',
            'thisWeek',
            'previousWeek',
            'thisMonth',
            'previousMonth',
            'thisYear',
            'previousYear',
            'lastVisit',
        ];
        $rules[] = [['items'], 'each', 'rule' => ['in', 'range' => $items], 'skipOnEmpty' => false];

        return $rules;
    }
}
