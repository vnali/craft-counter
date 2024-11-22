<?php

/**
 * @copyright Copyright (c) vnali
 */

namespace vnali\counter\controllers;

use Craft;
use craft\db\Query;
use craft\helpers\DateTimeHelper;
use craft\web\Controller;
use vnali\counter\Counter;
use vnali\counter\helpers\StringHelper;
use vnali\counter\models\Settings;
use vnali\counter\records\PageVisitsRecord;
use vnali\counter\stats\AverageVisitors;
use vnali\counter\stats\MaxOnline;
use vnali\counter\stats\NextVisitedPages;
use vnali\counter\stats\Visitors;
use vnali\counter\stats\Visits;
use vnali\counter\stats\VisitsRecent;
use vnali\counter\widgets\AverageVisitors as WidgetAverageVisitors;
use vnali\counter\widgets\DecliningPages as WidgetDecliningPages;
use vnali\counter\widgets\MaxOnline as WidgetMaxOnline;
use vnali\counter\widgets\NextVisitedPages as WidgetNextVisitedPages;
use vnali\counter\widgets\NotVisitedPages as WidgetNotVisitedPages;
use vnali\counter\widgets\Online as WidgetOnline;
use vnali\counter\widgets\PageStatistics as WidgetPageStatistics;
use vnali\counter\widgets\TopPages as WidgetTopPages;
use vnali\counter\widgets\TrendingPages as WidgetTrendingPages;
use vnali\counter\widgets\Visitors as WidgetVisitors;
use vnali\counter\widgets\Visits as WidgetVisits;
use vnali\counter\widgets\VisitsRecent as WidgetsVisitsRecent;
use yii\caching\ChainedDependency;
use yii\caching\DbDependency;
use yii\caching\ExpressionDependency;
use yii\caching\TagDependency;
use yii\web\BadRequestHttpException;
use yii\web\Response;

class WidgetController extends Controller
{
    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        return parent::beforeAction($action);
    }

    /**
     * Returns the widget data
     *
     * @param int $widgetId
     * @return Response|null
     */
    public function actionData(int $widgetId): ?Response
    {
        $this->requirePermission('counter-accessWidgets');
        // Get the existing widget
        $dashboardService = Craft::$app->getDashboard();
        $widget = $dashboardService->getWidgetById($widgetId);

        if (!$widget) {
            throw new BadRequestHttpException();
        }

        if (isset($widget->siteId)) {
            // Check again if user still has access to the site
            if (Craft::$app->getIsMultiSite()) {
                $currentUser = Craft::$app->getUser()->getIdentity();
                if ($widget->siteId == '*') {
                    $sites = Craft::$app->getSites()->getAllSites();
                    foreach ($sites as $site) {
                        if (!$currentUser->can('editSite:' . $site->uid)) {
                            return null;
                        }
                    }
                } else {
                    $site = Craft::$app->sites->getSiteById((int) $widget->siteId);
                    if (!$site || !$currentUser->can('editSite:' . $site->uid)) {
                        return null;
                    }
                }
            } else {
                if ($widget->siteId == '*') {
                    return null;
                } else {
                    $site = Craft::$app->sites->getSiteById((int) $widget->siteId);
                    if (!$site) {
                        return null;
                    }
                }
            }
        }

        $response = [];
        $class = get_class($widget);
        $cache = Craft::$app->getCache();
        $settings = Counter::$plugin->getSettings();
        /** @var Settings $settings */
        $cacheWidgetsSeconds = $settings->cacheWidgetsSeconds;

        if ($class == WidgetsVisitsRecent::class) {
            /** @var WidgetsVisitsRecent $widget  */
            $cacheKey = 'counter-plugin-widget-' . $widget->id;
            $response = $cache->get($cacheKey);
            if ($response === false) {
                if (!$cacheWidgetsSeconds) {
                    if ($widget->dateRange == 'thisHour' || $widget->dateRange == 'today') {
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
                    }
                }
                //
                if ($widget->dateRange == 'today' || $widget->dateRange == 'yesterday') {
                    $expressionDependency = new ExpressionDependency([
                        'expression' => 'date("Y-m-d")',
                    ]);
                } else {
                    $expressionDependency = new ExpressionDependency([
                        'expression' => 'date("Y-m-d H")',
                    ]);
                }

                $dependencies = [];
                if (isset($dbDependency)) {
                    $dependencies[] = $dbDependency;
                }
                $dependencies[] = $expressionDependency;
                $dependencies[] = new TagDependency(['tags' => 'counter-plugin']);

                /** @var VisitsRecent $stat  */
                $stat = new VisitsRecent(
                    $widget->dateRange,
                    DateTimeHelper::toDateTime($widget->startDate, true),
                    DateTimeHelper::toDateTime($widget->endDate, true),
                    $widget->calendar,
                    $widget->siteId,
                    $widget->ignoreVisitsInterval,
                    $widget->preferredInterval,
                    $widget->showChart,
                    $widget->showVisitor,
                );
                $response = $stat->get();

                // force cacheWidgetsSeconds to 0 for past date ranges.
                if ($widget->dateRange == 'yesterday' || $widget->dateRange == 'previousHour') {
                    $cacheWidgetsSeconds = 0;
                }
                $cache->set($cacheKey, $response, $cacheWidgetsSeconds, new ChainedDependency([
                    'dependencies' => $dependencies,
                ]));
            }
        } elseif ($class == WidgetOnline::class) {
            /** @var WidgetOnline $widget  */
            $response = Counter::$plugin->counter->onlineVisitors($widget->siteId, $widget->onlineThreshold);
        } elseif ($class == WidgetNextVisitedPages::class) {
            /** @var WidgetNextVisitedPages $widget  */
            $pageRecord = PageVisitsRecord::find()->where(['id' => $widget->pageId])->one();
            /** @var PageVisitsRecord|null  $pageRecord */
            if (!$pageRecord) {
                return null;
            } else {
                $page = $pageRecord->page;
            }

            $cacheKey = 'counter-plugin-widget-' . $widget->id;
            $results = $cache->get($cacheKey);
            if ($results === false) {
                if ($widget->dateRange == 'yesterday') {
                    $expressionDependency = new ExpressionDependency([
                        'expression' => 'date("Y-m-d")',
                    ]);
                } else {
                    $expressionDependency = new ExpressionDependency([
                        'expression' => 'date("Y-m-d H")',
                    ]);
                }

                /** @var NextVisitedPages $stat  */
                $stat = new NextVisitedPages(
                    $widget->dateRange,
                    DateTimeHelper::toDateTime($widget->startDate, true),
                    DateTimeHelper::toDateTime($widget->endDate, true),
                    $widget->pageId,
                    $widget->difference,
                    $widget->nextPagesLimit,
                    $widget->type,
                );
                $results = $stat->get();

                $cache->set($cacheKey, $results, 0, new ChainedDependency([
                    'dependencies' => [
                        $expressionDependency,
                        new TagDependency(['tags' => 'counter-plugin']),
                    ],
                ]));
            }
            $labels = array_keys($results);
            $values = array_values($results);
            $response = array($labels, $values);
        } elseif ($class == WidgetAverageVisitors::class) {
            /** @var WidgetAverageVisitors $widget  */
            $cacheKey = 'counter-plugin-widget-' . $widget->id;
            $response = $cache->get($cacheKey);
            if ($response === false) {
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
                }
                //
                $expressionDependency = new ExpressionDependency([
                    'expression' => 'date("Y-m-d")',
                ]);

                $dependencies = [];
                if (isset($dbDependency)) {
                    $dependencies[] = $dbDependency;
                }
                $dependencies[] = $expressionDependency;
                $dependencies[] = new TagDependency(['tags' => 'counter-plugin']);

                $stat = new AverageVisitors(
                    $widget->dateRange,
                    DateTimeHelper::toDateTime($widget->startDate, true),
                    DateTimeHelper::toDateTime($widget->endDate, true),
                    $widget->calendar,
                    $widget->siteId,
                );
                $response = $stat->get();
                $cache->set($cacheKey, $response, $cacheWidgetsSeconds, new ChainedDependency([
                    'dependencies' => $dependencies,
                ]));
            }
        } elseif ($class == WidgetTopPages::class) {
            /** @var WidgetTopPages $widget  */
            $cacheKey = 'counter-plugin-widget-' . $widget->id;
            $response = $cache->get($cacheKey);
            if ($response === false) {
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
                }
                //
                $expressionDependency = new ExpressionDependency([
                    'expression' => 'date("Y-m-d")',
                ]);

                $dependencies = [];
                if (isset($dbDependency)) {
                    $dependencies[] = $dbDependency;
                }
                $dependencies[] = $expressionDependency;
                $dependencies[] = new TagDependency(['tags' => 'counter-plugin']);

                $topPages = Counter::$plugin->pages->top($widget->dateRange, $widget->siteId, $widget->limit);
                $response = [];
                foreach ($topPages as $topPage) {
                    $tableData = [];
                    $tableData['title'] = $topPage['page'] . '...';
                    $tableData['url'] = $topPage['page'];
                    $tableData['visits'] = $topPage['visits'];
                    $response[] = $tableData;
                }

                // force cacheWidgetsSeconds to 0 for past date ranges.
                if ($widget->dateRange == 'yesterday') {
                    $cacheWidgetsSeconds = 0;
                }
                $cache->set($cacheKey, $response, $cacheWidgetsSeconds, new ChainedDependency([
                    'dependencies' => $dependencies,
                ]));
            }
        } elseif ($class == WidgetDecliningPages::class) {
            /** @var WidgetDecliningPages $widget  */
            $cacheKey = 'counter-plugin-widget-' . $widget->id;
            $response = $cache->get($cacheKey);
            if ($response === false) {
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
                }
                //
                $expressionDependency = new ExpressionDependency([
                    'expression' => 'date("Y-m-d")',
                ]);

                $dependencies = [];
                if (isset($dbDependency)) {
                    $dependencies[] = $dbDependency;
                }
                $dependencies[] = $expressionDependency;
                $dependencies[] = new TagDependency(['tags' => 'counter-plugin']);

                $decliningPages = Counter::$plugin->pages->declining($widget->dateRange, $widget->siteId, $widget->declineType, $widget->limit);
                $response = [];
                foreach ($decliningPages as $decliningPage) {
                    $tableData = [];
                    $tableData['title'] = $decliningPage['page'] . '...';
                    $tableData['url'] = $decliningPage['page'];
                    $tableData['current'] = $decliningPage['current'];
                    $tableData['previous'] = $decliningPage['previous'];
                    $tableData['decline'] = $decliningPage['decline'];
                    $response[] = $tableData;
                }

                $cache->set($cacheKey, $response, $cacheWidgetsSeconds, new ChainedDependency([
                    'dependencies' => $dependencies,
                ]));
            }
        } elseif ($class == WidgetNotVisitedPages::class) {
            /** @var WidgetNotVisitedPages $widget  */
            $cacheKey = 'counter-plugin-widget-' . $widget->id;
            $response = $cache->get($cacheKey);
            if ($response === false) {
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
                }
                //
                $expressionDependency = new ExpressionDependency([
                    'expression' => 'date("Y-m-d")',
                ]);

                $dependencies = [];
                if (isset($dbDependency)) {
                    $dependencies[] = $dbDependency;
                }
                $dependencies[] = $expressionDependency;
                $dependencies[] = new TagDependency(['tags' => 'counter-plugin']);

                $notVisitedPages = Counter::$plugin->pages->notVisited($widget->dateRange, $widget->siteId, $widget->limit, $widget->sortAsc, $widget->calendar);
                $response = [];
                foreach ($notVisitedPages as $notVisitedPage) {
                    $tableData = [];
                    $tableData['title'] = $notVisitedPage['page'] . '...';
                    $tableData['url'] = $notVisitedPage['page'];
                    $tableData['lastVisit'] = $notVisitedPage['lastVisit'];
                    $response[] = $tableData;
                }

                $cache->set($cacheKey, $response, $cacheWidgetsSeconds, new ChainedDependency([
                    'dependencies' => $dependencies,
                ]));
            }
        } elseif ($class == WidgetTrendingPages::class) {
            /** @var WidgetTrendingPages $widget  */
            $cacheKey = 'counter-plugin-widget-' . $widget->id;
            $response = $cache->get($cacheKey);
            if ($response === false) {
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
                }
                //
                $expressionDependency = new ExpressionDependency([
                    'expression' => 'date("Y-m-d")',
                ]);

                $dependencies = [];
                if (isset($dbDependency)) {
                    $dependencies[] = $dbDependency;
                }
                $dependencies[] = $expressionDependency;
                $dependencies[] = new TagDependency(['tags' => 'counter-plugin']);

                $trendingPages = Counter::$plugin->pages->trending($widget->dateRange, $widget->siteId, $widget->growthType, $widget->ignoreNewPages, $widget->limit);
                $response = [];
                foreach ($trendingPages as $trendingPage) {
                    $tableData = [];
                    $tableData['title'] = $trendingPage['page'] . '...';
                    $tableData['url'] = $trendingPage['page'];
                    $tableData['current'] = $trendingPage['current'];
                    $tableData['previous'] = $trendingPage['previous'];
                    $tableData['growth'] = $trendingPage['growth'];
                    $response[] = $tableData;
                }

                $cache->set($cacheKey, $response, $cacheWidgetsSeconds, new ChainedDependency([
                    'dependencies' => $dependencies,
                ]));
            }
        } elseif ($class == WidgetPageStatistics::class) {
            /** @var WidgetPageStatistics $widget  */
            $pageRecord = PageVisitsRecord::find()->where(['id' => $widget->pageId])->one();
            /** @var PageVisitsRecord|null  $pageRecord */
            // Check if still the user has access to site
            if (!$pageRecord) {
                return null;
            } else {
                $siteId = $pageRecord->siteId;
                // If site is not available
                $site = Craft::$app->sites->getSiteById($siteId);
                if (!$site) {
                    return null;
                }
                if (Craft::$app->getIsMultiSite()) {
                    $currentUser = Craft::$app->getUser()->getIdentity();
                    if (!$currentUser->can('editSite:' . $site->uid)) {
                        return null;
                    }
                }
            }

            $cacheKey = 'counter-plugin-widget-' . $widget->id;
            $response = $cache->get($cacheKey);
            if ($response === false) {
                $page = $pageRecord->page;
                if (!$cacheWidgetsSeconds) {
                    $dbDependency = new DbDependency([
                        'sql' => 'SELECT dateUpdated FROM {{%counter_page_visits}} where page=:page', 'params' => [':page' => $page],
                    ]);
                }
                $expressionDependency = new ExpressionDependency([
                    'expression' => 'date("Y-m-d")',
                ]);
                $results = Counter::$plugin->pages->visits($page, '*', []);
                unset($results['debugMessage']);
                $items = $widget->items ?? [];
                $pageVisits = [];
                foreach ($results as $key => $result) {
                    if (in_array($key, $items)) {
                        if ($key == 'allIgnoreInterval') {
                            $key = Craft::t('counter', 'All (ignore interval)');
                        } else {
                            $key = preg_replace('/([A-Z])/', ' $1', $key);
                            $key = craft::t('counter', StringHelper::toSentenceCase($key));
                        }
                        $pageVisit['title'] = $key;
                        $pageVisit['value'] = $result;
                        $pageVisits[] = $pageVisit;
                    }
                }

                $dependencies = [];
                if (isset($dbDependency)) {
                    $dependencies[] = $dbDependency;
                }
                $dependencies[] = $expressionDependency;
                $dependencies[] = new TagDependency(['tags' => 'counter-plugin']);
                $cache->set($cacheKey, $pageVisits, $cacheWidgetsSeconds, new ChainedDependency([
                    'dependencies' => $dependencies,
                ]));
                $response = $pageVisits;
            }
        } elseif ($class == WidgetVisitors::class) {
            /** @var WidgetVisitors $widget  */
            $cacheKey = 'counter-plugin-widget-' . $widget->id;
            $response = $cache->get($cacheKey);
            if ($response === false) {
                if (!$cacheWidgetsSeconds) {
                    if ($widget->dateRange == 'thisHour' || $widget->dateRange == 'today') {
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
                    }
                }
                //
                if ($widget->dateRange == 'today' || $widget->dateRange == 'yesterday') {
                    $expressionDependency = new ExpressionDependency([
                        'expression' => 'date("Y-m-d")',
                    ]);
                } else {
                    $expressionDependency = new ExpressionDependency([
                        'expression' => 'date("Y-m-d H")',
                    ]);
                }

                $dependencies = [];
                if (isset($dbDependency)) {
                    $dependencies[] = $dbDependency;
                }
                $dependencies[] = $expressionDependency;
                $dependencies[] = new TagDependency(['tags' => 'counter-plugin']);

                /** @var Visitors $stat  */
                $stat = new Visitors(
                    $widget->dateRange,
                    DateTimeHelper::toDateTime($widget->startDate, true),
                    DateTimeHelper::toDateTime($widget->endDate, true),
                    $widget->calendar,
                    $widget->siteId,
                    $widget->visitorType,
                    $widget->preferredInterval,
                    $widget->showChart,
                );
                $response = $stat->get();

                // force cacheWidgetsSeconds to 0 for past date ranges.
                if ($widget->dateRange == 'yesterday' || $widget->dateRange == 'previousHour') {
                    $cacheWidgetsSeconds = 0;
                }
                $cache->set($cacheKey, $response, $cacheWidgetsSeconds, new ChainedDependency([
                    'dependencies' => $dependencies,
                ]));
            }
        } elseif ($class == WidgetVisits::class) {
            /** @var WidgetVisits $widget  */
            $cacheKey = 'counter-plugin-widget-' . $widget->id;
            $response = $cache->get($cacheKey);
            if ($response === false) {
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
                }
                $expressionDependency = new ExpressionDependency([
                    'expression' => 'date("Y-m-d")',
                ]);

                $dependencies = [];
                if (isset($dbDependency)) {
                    $dependencies[] = $dbDependency;
                }
                $dependencies[] = $expressionDependency;
                $dependencies[] = new TagDependency(['tags' => 'counter-plugin']);

                /** @var Visits $stat  */
                $stat = new Visits(
                    $widget->dateRange,
                    DateTimeHelper::toDateTime($widget->startDate, true),
                    DateTimeHelper::toDateTime($widget->endDate, true),
                    $widget->calendar,
                    $widget->siteId,
                    $widget->ignoreVisitsInterval,
                    $widget->preferredInterval,
                    $widget->showChart,
                    $widget->showVisitor,
                );
                $response = $stat->get();
                $cache->set($cacheKey, $response, $cacheWidgetsSeconds, new ChainedDependency([
                    'dependencies' => $dependencies,
                ]));
            }
        } elseif ($class == WidgetMaxOnline::class) {
            /** @var WidgetMaxOnline $widget  */
            $cacheKey = 'counter-plugin-widget-' . $widget->id;
            $response = $cache->get($cacheKey);
            if ($response === false) {
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
                }
                $expressionDependency = new ExpressionDependency([
                    'expression' => 'date("Y-m-d")',
                ]);

                $dependencies = [];
                if (isset($dbDependency)) {
                    $dependencies[] = $dbDependency;
                }
                $dependencies[] = $expressionDependency;
                $dependencies[] = new TagDependency(['tags' => 'counter-plugin']);

                $stat = new MaxOnline(
                    $widget->dateRange,
                    DateTimeHelper::toDateTime($widget->startDate, true),
                    DateTimeHelper::toDateTime($widget->endDate, true),
                    $widget->calendar,
                    $widget->siteId,
                    $widget->showChart,
                    $widget->showVisitor,
                );
                $response = $stat->get();
                $cache->set($cacheKey, $response, $cacheWidgetsSeconds, new ChainedDependency([
                    'dependencies' => $dependencies,
                ]));
            }
        }

        return $this->asJson($response);
    }
}
