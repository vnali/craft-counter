<?php

/**
 * @copyright Copyright (c) vnali
 */

namespace vnali\counter\controllers;

use Craft;
use craft\db\Query;
use craft\web\Controller;
use vnali\counter\Counter;
use vnali\counter\models\Settings;
use vnali\counter\records\PageVisitsRecord;
use yii\web\Response;

class CounterController extends Controller
{
    protected int|bool|array $allowAnonymous = ['count'];

    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        if (Craft::$app->getConfig()->getGeneral()->headlessMode) {
            $this->enableCsrfValidation = false;
        }
        return parent::beforeAction($action);
    }

    /**
     * Counter increase function
     *
     * @return void
     */
    public function actionCount(): void
    {
        $request = Craft::$app->getRequest();

        header('Access-Control-Allow-Origin: ' . $request->getOrigin());
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Accept, Content-Type');
        // Handle preflight requests
        
        if (Craft::$app->request->isOptions) {
            http_response_code(200);
            exit; // Ensure no further processing occurs
        }
        
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $pluginSettings = Counter::$plugin->getSettings();
        // Disallow HTTP request if it is not necessary
        /** @var Settings $pluginSettings */
        if (!$pluginSettings->registerCounter && $pluginSettings->disableCountController) {
            craft::info('Counter request can not be sent via http request');
            return;
        }

        $pageUrl = $request->getRequiredBodyParam('pageUrl');

        // Check site base URL
        $valid = false;
        $sites = Craft::$app->getSites()->getAllSites();
        foreach ($sites as $site) {
            $baseUrl = $site->getBaseUrl();
            if (strpos($pageUrl, $baseUrl) === 0) {
                $valid = true;
                break;
            }
        }

        if (!$valid) {
            craft::warning('Requested page does not match site base URLs');
            return;
        }

        // Check site origin
        $valid = false;
        foreach ($sites as $site) {
            $baseUrl = $site->getBaseUrl();
            if (strpos($baseUrl, $request->getOrigin()) === 0) {
                $valid = true;
                break;
            }
        }

        if (!$valid) {
            craft::warning('Requested page origin does not match site base URLs');
            return;
        }

        if (Craft::$app->getConfig()->getGeneral()->headlessMode) {
            $headlessToken = $request->getBodyParam('headlessToken');
            if (!$pluginSettings->headlessToken || $pluginSettings->headlessToken != $headlessToken) {
                return;
            }
        }

        Counter::$plugin->counter->count($pageUrl, true);
    }

    /**
     * Return list of pages based on search
     *
     * @return Response
     */
    public function actionPages(): Response
    {
        $this->requirePermission('counter-accessWidgets');
        $this->requirePostRequest();
        $request = Craft::$app->getRequest();
        $search = $request->getRequiredBodyParam('search');

        $query = (new Query())
            ->from('{{%counter_page_visits}} pageVisits')
            ->select(["id", "page"])
            ->andWhere(['like', 'page', $search])
            ->orderBy('lastVisit desc');
        $rows = $query->all();

        $results = [];
        $limit = 100;
        $count = 0;
        $userService = Craft::$app->getUser();
        $siteService = Craft::$app->getSites();

        foreach ($rows as $row) {
            if ($count == $limit) {
                break;
            }

            $allow = true;
            /** @var PageVisitsRecord|null $pageRecord */
            $pageRecord = PageVisitsRecord::find()->where(['id' => $row['id']])->one();
            $siteId = $pageRecord->siteId;
            // Check if the site is not available now
            $site = $siteService->getSiteById($siteId);
            if (!$site) {
                $allow = false;
            }

            // If user has not access to the site
            if (Craft::$app->getIsMultiSite()) {
                $currentUser = $userService->getIdentity();
                if (!$currentUser->can('editSite:' . $site->uid)) {
                    $allow = false;
                }
            }

            if ($allow) {
                $result = [];
                $result['name'] = $row['page'];
                $result['id'] = $row['id'];
                $results[] = $result;
                $count++;
            }
        }

        return $this->asJson($results);
    }
}
