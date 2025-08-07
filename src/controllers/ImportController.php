<?php

/**
 * @copyright Copyright (c) vnali
 */

namespace vnali\counter\controllers;

use Craft;
use craft\db\Query;
use craft\helpers\Queue;
use craft\web\Controller;
use DateTime;
use vnali\counter\queue\jobs\ViewsWorkImport;
use vnali\counter\records\CounterRecord;
use vnali\counter\records\PageVisitsRecord;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Import data to counter plugin
 */
class ImportController extends Controller
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
    }

    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        if (Craft::$app->env === 'production') {
            throw new ForbiddenHttpException(Craft::t('counter', 'import is disallowed in this environment.'));
        }

        // Require permission
        $this->requirePermission('counter-importData');

        return parent::beforeAction($action);
    }


    public function actionViewsWork(): Response
    {
        if (Craft::$app->plugins->isPluginInstalled('views-work')) {
            // if there is already a record for Counter plugin, don't import data
            $pageVisitRecord = PageVisitsRecord::find()->one();
            if (!$pageVisitRecord) {
                $importSiteVisits = $this->request->getBodyParam('importSiteVisits');
                if ($importSiteVisits) {
                    $visits = (new Query())
                        ->select(['siteId', 'SUM(viewsTotal) AS viewsTotal'])
                        ->from('{{%viewswork_viewrecording}}')   // replace with your table
                        ->groupBy('siteId')
                        ->all();
                    $now = new DateTime('now', new \DateTimeZone("UTC"));
                    $formattedNow = $now->format('Y-m-d H:i:s');
                    $year = $now->format('Y');
                    $month = $now->format('m');
                    $day = $now->format('d');
                    $hour = $now->format('H');
                    $min = $now->format('i');
                    $quarter = 1;
                    if ($min >= 15 and $min < 30) {
                        $quarter = 2;
                    }
                    if ($min >= 30 and $min < 45) {
                        $quarter = 3;
                    }
                    if ($min >= 45) {
                        $quarter = 4;
                    }

                    $totalVisits = 0;
                    foreach ($visits as $visit) {
                        $siteId = $visit['siteId'];
                        $site = Craft::$app->sites->getSiteById($visit['siteId']);
                        if ($site !== null) {
                            $totalVisits = $totalVisits + $visit['viewsTotal'];
                            $counterRecord = new CounterRecord();
                            $counterRecord->year = (int)$year;
                            $counterRecord->month = (int)$month;
                            $counterRecord->day = (int)$day;
                            $counterRecord->hour = (int)$hour;
                            $counterRecord->quarter = $quarter;
                            $counterRecord->newVisitors = 1;
                            $counterRecord->visitors = 1;
                            $counterRecord->visits = $visit['viewsTotal'];
                            $counterRecord->maxOnline = 1;
                            $counterRecord->maxOnlineDate = $formattedNow;
                            $counterRecord->visitsIgnoreInterval = $visit['viewsTotal'];
                            $counterRecord->siteId = $visit['siteId'];
                            $counterRecord->dateCreated = $formattedNow;
                            $counterRecord->dateUpdated = $formattedNow;
                            $counterRecord->save();
                        } else {
                            craft::info("Site is not valid $siteId");
                        }
                    }

                    if ($totalVisits) {
                        $counterRecord = new CounterRecord();
                        $counterRecord->year = (int)$year;
                        $counterRecord->month = (int)$month;
                        $counterRecord->day = (int)$day;
                        $counterRecord->hour = (int)$hour;
                        $counterRecord->quarter = $quarter;
                        $counterRecord->newVisitors = 1;
                        $counterRecord->visitors = 1;
                        $counterRecord->visits = $totalVisits;
                        $counterRecord->maxOnline = 1;
                        $counterRecord->maxOnlineDate = $formattedNow;
                        $counterRecord->visitsIgnoreInterval = $totalVisits;
                        $counterRecord->siteId = null;
                        $counterRecord->dateCreated = $formattedNow;
                        $counterRecord->dateUpdated = $formattedNow;
                        $counterRecord->save();
                    }
                }
                $recordsQuery = \twentyfourhoursmedia\viewswork\records\ViewRecording::find()->orderBy('id ASC');
                Queue::push(new ViewsWorkImport([
                    'recordsQuery' => $recordsQuery,
                ]));
                Craft::$app->getSession()->setNotice('The import job has been added to the queue.');
            } else {
                Craft::$app->getSession()->setError('Counter plugin has already data');
            }
        } else {
            Craft::$app->getSession()->setError('Views work plugin is not installed');
        }
        return $this->redirect('utilities/counter-import-data');
    }
}
