<?php

/**
 * @copyright Copyright (c) vnali
 */

namespace vnali\counter\controllers;

use Craft;
use craft\helpers\StringHelper;
use craft\web\Controller;
use craft\web\UrlManager;
use vnali\counter\base\DateInterface;
use vnali\counter\Counter;
use vnali\counter\models\Settings;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Set settings for the plugin
 */
class SettingsController extends Controller
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
        if (!Craft::$app->getConfig()->getGeneral()->allowAdminChanges) {
            throw new ForbiddenHttpException(Craft::t('counter', 'Administrative changes are disallowed in this environment.'));
        }

        // Require permission
        $this->requirePermission('counter-manageSettings');

        return parent::beforeAction($action);
    }

    /**
     * Return general settings template
     *
     * @param Settings|null $settings
     * @return Response
     */
    public function actionGeneral(?Settings $settings = null): Response
    {
        if ($settings === null) {
            $settings = Counter::$plugin->getSettings();
        }

        /** @var Settings $settings */
        $variables['siteSettings'] = json_decode($settings->siteSettings, true);
        $variables['calendars'] = ['gregorian' => 'Gregorian'];
        $variables['days'] = DateInterface::START_DAY_INT_TO_DAY;

        if ($settings->ignoreUserIds) {
            $users = [];
            foreach ($settings->ignoreUserIds as $user) {
                if (Craft::$app->users->getUserById($user)) {
                    $users[] = Craft::$app->users->getUserById($user);
                }
            }
            $settings->ignoreUserIds = $users;
        }
        $userGroups = Craft::$app->userGroups->getAllGroups();
        $groups = [];
        foreach ($userGroups as $userGroup) {
            $group = [];
            $group['value'] = $userGroup->handle;
            $group['label'] = $userGroup->name;
            $groups[] = $group;
        }
        $variables['groups'] = $groups;
        $variables['settings'] = $settings;

        return $this->renderTemplate(
            'counter/settings/_general',
            $variables
        );
    }

    /**
     * Save general settings
     *
     * @param Settings|null $settings
     * @return Response|null
     */
    public function actionGeneralSave(Settings $settings = null): ?Response
    {
        $this->requirePostRequest();

        $validate = true;
        $errorMessage = Craft::t('counter', 'Couldn’t save settings.');
        /** @var Settings $settings */
        $settings = Counter::$plugin->getSettings();
        $currentSiteSettings = json_decode($settings->siteSettings, true);
        if (!$settings->salt) {
            $settings->salt = StringHelper::randomString();
        }
        $settings->onlineThreshold = $this->request->getBodyParam('onlineThreshold', $settings->onlineThreshold);
        $settings->visitsInterval = $this->request->getBodyParam('visitsInterval', $settings->visitsInterval);
        $settings->registerCounter = $this->request->getBodyParam('registerCounter', $settings->registerCounter);
        $settings->removeAllQueryParams = $this->request->getBodyParam('removeAllQueryParams', $settings->removeAllQueryParams);
        $settings->removeQueryParams = $this->request->getBodyParam('removeQueryParams', $settings->removeQueryParams);
        $settings->removeUrlFragment = $this->request->getBodyParam('removeUrlFragment', $settings->removeUrlFragment);
        $settings->ignoreAllUsers = $this->request->getBodyParam('ignoreAllUsers', $settings->ignoreAllUsers);
        $settings->ignoreBots = $this->request->getBodyParam('ignoreBots', $settings->ignoreBots);
        $settings->disableCountController = $this->request->getBodyParam('disableCountController', $settings->disableCountController);
        $settings->supportOutdatedBrowsers = $this->request->getBodyParam('supportOutdatedBrowsers', $settings->supportOutdatedBrowsers);
        
        if (!isset($settings->keepVisitorsInSeconds)) {
            $settings->keepVisitorsInSeconds = -1;
        }
        
        if (($settings->keepVisitorsInSeconds != -1)) {
            $validate = false;
            $errorMessage = Craft::t('counter', 'The keepVisitorsInSeconds value should be -1');
        }

        $user = Craft::$app->getUser()->getIdentity();
        if ($user->can('editUsers')) {
            $settings->ignoreUserIds = Craft::$app->getRequest()->getBodyParam('ignoreUserIds');
            $settings->ignoreGroups = Craft::$app->getRequest()->getBodyParam('ignoreGroups');
        }

        // Episode mapping fields
        $siteSettings = [];
        $siteFields = $this->request->getBodyParam('siteSettingFields');

        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $siteHandle = $site->handle;
            $siteId = $site->id;
            $siteUnique = $siteHandle . $siteId;
            $mapping = [];

            if (isset($siteFields[$siteUnique])) {
                if (isset($siteFields[$siteUnique]['calendar']) && !in_array($siteFields[$siteUnique]['calendar'], ['gregorian'])) {
                    $settings->addError('siteSettings', 'Selected calendar is not valid');
                    $validate = false;
                }
                if (!in_array($siteFields[$siteUnique]['weekStartDay'], [0, 1, 2, 3, 4, 5, 6])) {
                    $settings->addError('siteSettings', 'Selected week start day is not valid');
                    $validate = false;
                }
                if (isset($siteFields[$siteUnique]['calendar'])) {
                    $mapping['calendar'] = $siteFields[$siteUnique]['calendar'];
                } elseif (isset($currentSiteSettings[$siteUnique]['calendar'])) {
                    $mapping['calendar'] = $currentSiteSettings[$siteUnique]['calendar'];
                } else {
                    $mapping['calendar'] = 'gregorian';
                }
                $mapping['weekStartDay'] = $siteFields[$siteUnique]['weekStartDay'];
                $mapping['enabledCounter'] = $siteFields[$siteUnique]['enabledCounter'];
                /*
                $mapping['enabledPageVisits'] = $siteFields[$siteUnique]['enabledPageVisits'];
                if (!$mapping['enabledCounter'] && $mapping['enabledPageVisits']) {
                    $settings->addError('siteSettings', 'To enable page counter, site counter must be enabled too.');
                    $validate = false;
                }
                */
            }

            $siteSettings[$siteUnique] = $mapping;
        }
        $settings->siteSettings = json_encode($siteSettings);

        if (!$validate || !$settings->validate()) {
            Craft::$app->getSession()->setError($errorMessage);

            /** @var UrlManager $urlManager */
            $urlManager = Craft::$app->getUrlManager();
            $urlManager->setRouteParams([
                'settings' => $settings,
            ]);

            return null;
        }

        // Save it
        if (!Craft::$app->getPlugins()->savePluginSettings(Counter::$plugin, $settings->getAttributes())) {
            return $this->asModelFailure($settings, Craft::t('counter', 'Couldn’t save general settings.'), 'settings');
        }

        return $this->asSuccess(Craft::t('counter', 'General settings saved.'));
    }
}
