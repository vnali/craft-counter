<?php

namespace vnali\counter\widgets;

use Craft;
use craft\base\Widget;
use craft\db\Query;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\Tag;
use craft\helpers\StringHelper;
use Exception;
use vnali\counter\assets\CounterWidgetTableAsset;
use vnali\counter\Counter;
use vnali\counter\helpers\StringHelper as CounterStringHelper;
use vnali\counter\models\Settings;
use vnali\counter\validators\SiteValidator;
use yii\caching\ChainedDependency;
use yii\caching\DbDependency;
use yii\caching\ExpressionDependency;
use yii\caching\TagDependency;

class NotVisitedPages extends Widget
{
    public ?string $siteId = null;

    public ?string $dateRange = 'today';

    public ?bool $showElementTitle = null;

    public ?array $sectionHandles = [];

    public ?array $items = [];

    public int $limit = 5;

    public ?bool $sortAsc = null;

    public ?string $calendar = null;

    public ?bool $useAjax = null;

    public ?int $autoRefreshWidget = null;

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
        return Craft::getAlias('@vnali/counter/icon-mask-widget.svg');
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

        $view = Craft::$app->getView();
        $id = 'not-visited-pages' . StringHelper::randomString();
        $namespaceId = $view->namespaceInputId($id);
        $view->registerAssetBundle(CounterWidgetTableAsset::class);
        $widget = $this;

        $settings = Counter::$plugin->getSettings();
        if (!$widget->useAjax) {
            $cache = Craft::$app->getCache();
            $cacheKey = 'counter-plugin-widget-' . $widget->id;
            $notVisitedPages = $cache->get($cacheKey);
            /** @var Settings $settings */
            $widgetTitleTruncateLength = $settings->widgetTitleTruncateLength;
            if ($notVisitedPages === false) {
                $cacheWidgetsSeconds = $settings->cacheWidgetsSeconds;
                if (!$cacheWidgetsSeconds) {
                    $query = (new Query())
                        ->select(['max(id)'])
                        ->from('{{%counter_visitors}}');
                    if ($widget->siteId != '*') {
                        $query->where(['siteId' => $widget->siteId]);
                    }
                    $rawQuery = $query->createCommand()->getRawSql();
                    $dbDependency = new DbDependency([
                        'sql' => $rawQuery,
                    ]);
                    // a dependency for elements changes
                    $query = (new Query());
                    if (Craft::$app->getDb()->getIsPgsql()) {
                        $query->select(['max("dateUpdated")']);
                    } else {
                        $query->select(['max(dateUpdated)']);
                    }
                    $query->from('{{%elements}}');
                    $rawQuery = $query->createCommand()->getRawSql();
                    $dbDependency2 = new DbDependency([
                        'sql' => $rawQuery,
                    ]);
                }
                $expressionDependency = new ExpressionDependency([
                    'expression' => 'date("Y-m-d")',
                ]);
                $dependencies = [];
                if (isset($dbDependency)) {
                    $dependencies[] = $dbDependency;
                }
                if (isset($dbDependency2)) {
                    $dependencies[] = $dbDependency2;
                }
                $dependencies[] = $expressionDependency;
                $dependencies[] = new TagDependency(['tags' => 'counter-plugin']);
                $filters = [];
                if ($this->sectionHandles) {
                    $filters['sectionHandles'] = $this->sectionHandles;
                }
                if ($this->items) {
                    $filters['items'] = $this->items;
                }
                $notVisitedPages = Counter::$plugin->pages->notVisited($this->dateRange, $this->siteId, $this->limit, $this->sortAsc, $this->calendar, $this->showElementTitle, $filters);
                $cache->set($cacheKey, $notVisitedPages, $cacheWidgetsSeconds, new ChainedDependency([
                    'dependencies' => $dependencies,
                ]));
            }
            return $view->renderTemplate('counter/_components/widgets/not-visited-pages/body', compact('widget', 'id', 'notVisitedPages', 'namespaceId', 'widgetTitleTruncateLength'));
        } else {
            return $view->renderTemplate('counter/_components/widgets/not-visited-pages/body-ajax', compact('widget', 'id', 'namespaceId'));
        }
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

        $currentVersion = Craft::$app->version;
        $targetVersion = '5.0.0';
        $sectionItems = [];
        if (version_compare($currentVersion, $targetVersion, '>=')) {
            $sections = Craft::$app->entries->getAllSections();
        } else {
            $sections = Craft::$app->sections->getAllSections();
        }
        foreach ($sections as $section) {
            $currentUser = Craft::$app->getUser()->getIdentity();
            if ($currentUser->can("viewEntries:$section->uid")) {
                $sectionItem = [];
                $sectionItem['value'] = $section->handle;
                $sectionItem['label'] = $section->name;
                $sectionItems[] = $sectionItem;
            }
        }

        $items = [];
        // short name for core element types
        $item['value'] = 'page';
        $item['label'] = 'Page';
        $items[] = $item;
        $item['value'] = 'entry';
        $item['label'] = 'Entry';
        $items[] = $item;
        $item['value'] = 'category';
        $item['label'] = 'Category';
        $items[] = $item;
        $item['value'] = 'tag';
        $item['label'] = 'Tag';
        $items[] = $item;
        $elementTypes = craft::$app->elements->getAllElementTypes();
        foreach ($elementTypes as $elementType) {
            if ($elementType::hasUris() && ($elementType != Entry::class) && ($elementType != Category::class) && ($elementType != Tag::class)) {
                $item = [];
                // prevent breaking selectize with encoding values
                $item['value'] = urlencode($elementType);
                $item['label'] = $elementType::displayName();
                $items[] = $item;
            }
        }

        /** @var Settings $settings */
        $settings = Counter::$plugin->getSettings();
        $showAllCalendars = $settings->showAllCalendars;

        return Craft::$app->getView()->renderTemplate('counter/_components/widgets/not-visited-pages/settings', [
            'id' => $id,
            'namespaceId' => $namespaceId,
            'widget' => $this,
            'showAllCalendars' => $showAllCalendars,
            'sections' => $sectionItems,
            'items' => $items,
        ]);
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['dateRange'], 'safe'];
        $rules[] = [['showElementTitle'], 'in', 'range' => ['0', '1']];
        $rules[] = [['sortAsc'], 'in', 'range' => ['0', '1']];
        $rules[] = [['limit'], 'integer', 'min' => 1, 'max' => 20];
        $rules[] = [['siteId'], SiteValidator::class, 'skipOnEmpty' => false];

        return $rules;
    }
}
