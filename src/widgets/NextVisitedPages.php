<?php

namespace vnali\counter\widgets;

use Craft;
use craft\base\Widget;
use craft\helpers\DateTimeHelper;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use Exception;
use vnali\counter\assets\CounterWidgetChartAsset;
use vnali\counter\base\DateWidgetTrait;
use vnali\counter\helpers\StringHelper as CounterStringHelper;
use vnali\counter\records\PageVisitsRecord;
use vnali\counter\stats\NextVisitedPages as NextVisitedPagesStats;

class NextVisitedPages extends Widget
{
    use DateWidgetTrait;

    private ?NextVisitedPagesStats $_stat = null;

    public ?int $pageId = null;

    public ?int $difference = 60;

    public ?int $nextPagesLimit = null;

    public ?string $type = 'count';

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function init(): void
    {
        parent::init();
        $this->_stat = new NextVisitedPagesStats(
            $this->dateRange,
            DateTimeHelper::toDateTime($this->startDate, true),
            DateTimeHelper::toDateTime($this->endDate, true),
            $this->pageId,
            $this->difference,
            $this->nextPagesLimit,
            $this->type,
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
        return Craft::t('counter', 'Next Visited Pages');
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
        return Craft::t('counter', craft::t('counter', 'Next Visited Pages within {difference} Seconds', ['difference' => $this->difference]));
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

        $dateRange = preg_replace('/([A-Z])|(\d+)/', ' $0', $this->dateRange);
        $dateRange = CounterStringHelper::toSentenceCase($dateRange);

        if ($this->dateRange == 'custom') {
            $dateRange = '(' . $this->startDate . ' ' . craft::t('counter', 'to') . ' ' . $this->endDate . ')';
        }

        return $page . ' - ' . $dateRange;
    }

    /**
     * @inheritdoc
     */
    public function getBodyHtml(): ?string
    {
        $pageRecord = PageVisitsRecord::find()->where(['id' => $this->pageId])->one();

        /** @var PageVisitsRecord|null  $pageRecord */
        if (!$pageRecord) {
            return '';
        } else {
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
        }

        $view = Craft::$app->getView();
        $id = 'next-visited-pages' . StringHelper::randomString();
        $namespaceId = $view->namespaceInputId($id);
        $view->registerAssetBundle(CounterWidgetChartAsset::class);

        $data = $this->_stat->get();
        $labels = json_encode(array_keys($data));
        $data = json_encode(array_values($data));
        $widget = $this;

        return $view->renderTemplate('counter/_components/widgets/next-visited-pages/body', compact('namespaceId', 'widget', 'labels', 'data'));
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
        $id = 'next-visited-pages' . StringHelper::randomString();
        $namespaceId = Craft::$app->getView()->namespaceInputId($id);

        $page = null;
        $pageRecord = PageVisitsRecord::find()->where(['id' => $this->pageId])->one();

        /** @var PageVisitsRecord|null  $pageRecord */
        if ($pageRecord) {
            $page = $pageRecord->page;
        }

        $pageListURL = UrlHelper::Url('counter/counter/pages');
        $sessionInfoUrl = UrlHelper::actionUrl('users/session-info');

        return Craft::$app->getView()->renderTemplate('counter/_components/widgets/next-visited-pages/settings', [
            'id' => $id,
            'namespaceId' => $namespaceId,
            'widget' => $this,
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
        $rules[] = [['type'], 'in', 'range' => ['percentage', 'count']];
        $rules[] = [['difference'], 'integer', 'min' => 1];
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

        return $rules;
    }
}
