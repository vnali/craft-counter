<?php

namespace vnali\counter;

use Craft;
use craft\base\Element;
use craft\base\Plugin;
use craft\db\Query;
use craft\elements\Category;
use craft\elements\Entry;
use craft\events\DefineGqlTypeFieldsEvent;
use craft\events\ModelEvent;
use craft\events\RegisterCacheOptionsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterElementTableAttributesEvent;
use craft\events\RegisterGqlDirectivesEvent;
use craft\events\RegisterGqlQueriesEvent;
use craft\events\RegisterGqlSchemaComponentsEvent;
use craft\events\RegisterGqlTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\events\WidgetEvent;
use craft\gql\TypeManager;
use craft\helpers\ElementHelper;
use craft\helpers\UrlHelper;
use craft\services\Dashboard;
use craft\services\Gql;
use craft\services\UserPermissions;
use craft\services\Utilities;
use craft\utilities\ClearCaches;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use GraphQL\Type\Definition\Type;
use vnali\counter\assets\CounterAsset;
use vnali\counter\gql\directives\DateConvert;
use vnali\counter\gql\helpers\Gql as HelpersGql;
use vnali\counter\gql\queries\CounterQuery;
use vnali\counter\gql\queries\PageVisitsQuery;
use vnali\counter\gql\queries\TopPagesQuery;
use vnali\counter\gql\queries\TrendingPagesQuery;
use vnali\counter\gql\types\CounterType;
use vnali\counter\gql\types\PageVisitsType;
use vnali\counter\gql\types\TopPagesType;
use vnali\counter\gql\types\TrendingPagesType;
use vnali\counter\helpers\UrlHelper as HelpersUrlHelper;
use vnali\counter\models\Settings;
use vnali\counter\records\PageVisitsRecord;
use vnali\counter\services\counterService;
use vnali\counter\services\pagesService;
use vnali\counter\twig\CounterVariable;
use vnali\counter\utilities\ImportDataUtility;
use vnali\counter\utilities\ImportDataUtility4;
use vnali\counter\widgets\AverageVisitors;
use vnali\counter\widgets\DecliningPages;
use vnali\counter\widgets\MaxOnline;
use vnali\counter\widgets\NextVisitedPages;
use vnali\counter\widgets\NotVisitedPages;
use vnali\counter\widgets\Online;
use vnali\counter\widgets\PageStatistics;
use vnali\counter\widgets\TopPages;
use vnali\counter\widgets\TrendingPages;
use vnali\counter\widgets\Visitors;
use vnali\counter\widgets\Visits;
use vnali\counter\widgets\VisitsRecent;
use yii\base\Event;
use yii\caching\TagDependency;

/**
 * Counter plugin
 * @property-read counterService $counter
 * @property-read pagesService $pages
 * @author vnali <vnali.dev@gmail.com>
 * @copyright vnali
 * @license https://craftcms.github.io/license/ Craft License
 */
class Counter extends Plugin
{
    /**
     * @var Counter
     */
    public static Counter $plugin;

    public string $schemaVersion = '1.0.0-alpha.5';
    public bool $hasCpSettings = true;
    public bool $hasCpSection = true;

    public static function config(): array
    {
        return [
            'components' => [
                'counter' => counterService::class,
                'pages' => pagesService::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        $currentVersion = Craft::$app->version;
        $targetVersion = '5.0.0';

        $this->_registerPermissions();
        $this->_registerRules();
        $this->_registerVariables();
        $this->_registerWidgets();
        $this->_registerGraphQl();

        $settings = Counter::$plugin->getSettings();
        /** @var Settings $settings */

        $view = Craft::$app->getView();
        if (!Craft::$app->getRequest()->getIsConsoleRequest() && !Craft::$app->getRequest()->getIsCpRequest() && $settings->registerCounter) {
            $view->registerAssetBundle(CounterAsset::class);
        }

        Event::on(
            ClearCaches::class,
            ClearCaches::EVENT_REGISTER_TAG_OPTIONS,
            function(RegisterCacheOptionsEvent $event) {
                $event->options = array_merge(
                    $event->options,
                    $this->_customAdminCpTagOptions()
                );
            }
        );

        Event::on(
            Dashboard::class,
            Dashboard::EVENT_AFTER_SAVE_WIDGET,
            function(WidgetEvent $event) {
                $cache = Craft::$app->getCache();
                $widgetId = $event->widget->id;
                $cache->delete('counter-plugin-widget-' . $widgetId);
            }
        );

        $user = Craft::$app->getUser();
        if (Craft::$app->plugins->isPluginInstalled('views-work') && $user->checkPermission('counter-importData')) {
            if (Craft::$app->env !== 'production') {
                if (version_compare($currentVersion, $targetVersion, '>=')) {
                    Event::on(
                        Utilities::class,
                        Utilities::EVENT_REGISTER_UTILITIES,
                        function(RegisterComponentTypesEvent $event) {
                            $event->types[] = ImportDataUtility::class;
                        }
                    );
                } else {
                    Event::on(
                        Utilities::class,
                        Utilities::EVENT_REGISTER_UTILITY_TYPES,
                        function(RegisterComponentTypesEvent $event) {
                            $event->types[] = ImportDataUtility4::class;
                        }
                    );
                }
            }
        }

        $keepElementStatsOnUrlChange = $settings->keepElementStatsOnUrlChange;
        if ($keepElementStatsOnUrlChange) {
            // because we can't catch element's old uri in after save
            Event::on(
                Element::class,
                Element::EVENT_BEFORE_SAVE,
                function(ModelEvent $event) {
                    $element = $event->sender;
                    if (!ElementHelper::isDraftOrRevision($element)) {
                        if (!$event->isNew && $element->hasUris()) {
                            $oldUri = (new Query())
                                ->select(['uri'])
                                ->from(['elements_sites'])
                                ->where([
                                    'elementId' => $element->id,
                                    'siteId' => $element->siteId,
                                ])
                                ->scalar();
                            $cache = Craft::$app->getCache();
                            $cache->set('counter-element-old-uri-' . $element->id . '-' . $element->siteId, $oldUri, 60, new TagDependency(['tags' => 'counter-plugin']));
                        }
                    }
                }
            );

            // we use after save because we can't catch element uri in before save if element save quickly before creating draft
            Event::on(
                Element::class,
                Element::EVENT_AFTER_SAVE,
                function(ModelEvent $event) {
                    $element = $event->sender;
                    if (!ElementHelper::isDraftOrRevision($element)) {
                        if (!$event->isNew && $element->hasUris()) {
                            // Query the old URI from elements_sites table
                            $cache = Craft::$app->getCache();
                            $oldUri = $cache->get('counter-element-old-uri-' . $element->id . '-' . $element->siteId);
                            if ($oldUri === false) {
                                $oldUri = (new Query())
                                    ->select(['uri'])
                                    ->from(['elements_sites'])
                                    ->where([
                                        'elementId' => $element->id,
                                        'siteId' => $element->siteId,
                                    ])
                                    ->scalar();
                            }
                            $newUri = $element->uri;
                            if ($oldUri !== null && $newUri !== null && $oldUri !== $newUri) {
                                $site = $element->getSite();
                                $oldUrl = rtrim($site->getBaseUrl(), '/') . '/' . ltrim($oldUri, '/');
                                $oldUrl = HelpersUrlHelper::trimURL($oldUrl, 2048, 3072);
                                $newUrl = $element->getUrl();
                                $newUrl = HelpersUrlHelper::trimURL($newUrl, 2048, 3072);
                                /** @var PageVisitsRecord|null $oldPageVisitsRecord */
                                $oldPageVisitsRecord = PageVisitsRecord::find()->where(['page' => $oldUrl, 'siteId' => $element->siteId])->one();
                                /** @var PageVisitsRecord|null $newPageVisitsRecord */
                                $newPageVisitsRecord = PageVisitsRecord::find()->where(['page' => $newUrl, 'siteId' => $element->siteId])->one();
                                if ($oldPageVisitsRecord) {
                                    if ($newPageVisitsRecord) {
                                        $newPageVisitsRecord->allTime = $newPageVisitsRecord->allTime + $oldPageVisitsRecord->allTime;
                                        $newPageVisitsRecord->allTimeIgnoreInterval = $newPageVisitsRecord->allTimeIgnoreInterval + $oldPageVisitsRecord->allTimeIgnoreInterval;
                                        $newPageVisitsRecord->today = $newPageVisitsRecord->today + $oldPageVisitsRecord->today;
                                        $newPageVisitsRecord->thisWeek = $newPageVisitsRecord->thisWeek + $oldPageVisitsRecord->thisWeek;
                                        $newPageVisitsRecord->thisMonth = $newPageVisitsRecord->thisMonth + $oldPageVisitsRecord->thisMonth;
                                        $newPageVisitsRecord->thisYear = $newPageVisitsRecord->thisYear + $oldPageVisitsRecord->thisYear;
                                        $newPageVisitsRecord->yesterday = $newPageVisitsRecord->yesterday + $oldPageVisitsRecord->yesterday;
                                        $newPageVisitsRecord->previousWeek = $newPageVisitsRecord->previousWeek + $oldPageVisitsRecord->previousWeek;
                                        $newPageVisitsRecord->previousMonth = $newPageVisitsRecord->previousMonth + $oldPageVisitsRecord->previousMonth;
                                        $newPageVisitsRecord->previousYear = $newPageVisitsRecord->previousYear + $oldPageVisitsRecord->previousYear;
                                        if ($newPageVisitsRecord->save()) {
                                            $oldPageVisitsRecord->delete();
                                        }
                                    } else {
                                        $oldPageVisitsRecord->page = $newUrl;
                                        // dont update dateUpdated. update this need check for yesterday and ...
                                        $oldPageVisitsRecord->markAttributeDirty('dateUpdated');
                                        $oldPageVisitsRecord->save();
                                    }
                                }
                            }
                        }
                    }
                }
            );
        }

        foreach ([Entry::class, Category::class] as $elementClass) {
            Event::on(
                $elementClass,
                Element::EVENT_REGISTER_TABLE_ATTRIBUTES,
                static function(RegisterElementTableAttributesEvent $event) {
                    $event->tableAttributes['counterAllViews'] = [
                        'label' => Craft::t('counter', 'All Time Views'),
                    ];
                    $event->tableAttributes['counterTodayViews'] = [
                        'label' => Craft::t('counter', 'Today Views'),
                    ];
                    $event->tableAttributes['counterThisWeekViews'] = [
                        'label' => Craft::t('counter', 'This Week Views'),
                    ];
                    $event->tableAttributes['counterThisMonthViews'] = [
                        'label' => Craft::t('counter', 'This Month Views'),
                    ];
                    $event->tableAttributes['counterThisYearViews'] = [
                        'label' => Craft::t('counter', 'This Year Views'),
                    ];
                    $event->tableAttributes['counterYesterdayViews'] = [
                        'label' => Craft::t('counter', 'Yesterday Views'),
                    ];
                    $event->tableAttributes['counterPreviousWeekViews'] = [
                        'label' => Craft::t('counter', 'Previous Week Views'),
                    ];
                    $event->tableAttributes['counterPreviousMonthViews'] = [
                        'label' => Craft::t('counter', 'Previous Month Views'),
                    ];
                    $event->tableAttributes['counterPreviousYearViews'] = [
                        'label' => Craft::t('counter', 'Previous Year Views'),
                    ];
                }
            );

            $columns = ['counterAllViews', 'counterTodayViews', 'counterThisWeekViews', 'counterThisMonthViews', 'counterThisYearViews', 'counterYesterdayViews', 'counterPreviousWeekViews', 'counterPreviousMonthViews', 'counterPreviousYearViews'];

            if (version_compare($currentVersion, $targetVersion, '>=')) {
                // Get the HTML for that attribute
                Event::on(
                    $elementClass,
                    Element::EVENT_DEFINE_ATTRIBUTE_HTML,
                    function(\craft\events\DefineAttributeHtmlEvent $event) use ($columns) {
                        if (!in_array($event->attribute, $columns)) {
                            return;
                        }
                        $html = $this->_renderCustomCounterAttributes($event);
                        $event->html = $html;
                    }
                );

                // Provide a fallback to prevent error on inline editing
                Event::on(
                    $elementClass,
                    Element::EVENT_DEFINE_INLINE_ATTRIBUTE_INPUT_HTML,
                    static function(\craft\events\DefineAttributeHtmlEvent $event) use ($columns) {
                        if (in_array($event->attribute, $columns)) {
                            $event->html = '';
                        }
                    }
                );
            } else {
                // Get the HTML for that attribute
                Event::on(
                    $elementClass,
                    Element::EVENT_SET_TABLE_ATTRIBUTE_HTML,
                    function(\craft\events\SetElementTableAttributeHtmlEvent $event) use ($columns) {
                        if (!in_array($event->attribute, $columns)) {
                            return;
                        }
                        $html = $this->_renderCustomCounterAttributes($event);
                        $event->html = $html;
                    }
                );
            }
        }
    }

    private function _renderCustomCounterAttributes($event)
    {
        $attribute = $event->attribute;
        $element = $event->sender;
        // if element does not have a url, set views to '', it it does, default is changed to '0'
        $html = '';
        try {
            $elementId = $element->id;
            $siteId = $element->siteId;
            $site = Craft::$app->sites->getSiteById($siteId);
            if ($site) {
                $element = Craft::$app->elements->getElementById($elementId, null, $siteId);
                if ($url = $element->getUrl()) {
                    $data = Counter::$plugin->pages->visits($url, $siteId);

                    if (str_starts_with($attribute, "counter")) {
                        $attribute = substr($attribute, 7);
                    }

                    if (str_ends_with($attribute, "Views")) {
                        $attribute = lcfirst(substr($attribute, 0, -5));
                    }
                    $html = $data[$attribute] ?? '0';
                }
            }
        } catch (\Throwable $e) {
            Craft::error($e, __METHOD__);
            $html = $e->getMessage();
        }
        return $html;
    }

    /**
     * @inheritDoc
     */
    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }

    public function getSettingsResponse(): mixed
    {
        $url = UrlHelper::cpUrl('counter/settings');
        return Craft::$app->getResponse()->redirect($url);
    }

    /**
     * @inheritDoc
     */
    public function getCpNavItem(): ?array
    {
        $nav = null;
        $allowAdminChanges = Craft::$app->getConfig()->getGeneral()->allowAdminChanges;
        $user = Craft::$app->getUser();
        // Settings
        if ($allowAdminChanges && $user->checkPermission('counter-manageSettings')) {
            $nav = parent::getCpNavItem();
            $nav['label'] = Craft::t('counter', 'Counter');

            $nav['subnav']['settings'] = [
                'label' => Craft::t('app', 'Settings'),
                'url' => 'counter/settings',
            ];
        }

        return $nav;
    }

    /**
     * Returns cache tag used by the plugin
     *
     * @return array
     */
    private function _customAdminCpTagOptions(): array
    {
        return [
            [
                'tag' => 'counter-plugin',
                'label' => Craft::t('counter', 'Counter plugin'),
            ],
        ];
    }

    /**
     * Register CP Url and site rules.
     *
     * @return void
     */
    private function _registerRules(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['counter/settings/general'] = 'counter/settings/general';
                $event->rules['counter/counter/pages'] = 'counter/counter/pages';
                $event->rules['counter/widget/data'] = 'counter/widget/data';
            }
        );

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['counter/counter/count'] = 'counter/counter/count';
            }
        );
    }

    private function _registerPermissions(): void
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {
                $permissions['counter-manageSettings'] = [
                    'label' => Craft::t('counter', 'Manage plugin settings'),
                ];
                $permissions['counter-accessWidgets'] = [
                    'label' => Craft::t('counter', 'Access plugin widgets'),
                ];
                $permissions['counter-importData'] = [
                    'label' => Craft::t('counter', 'Import data'),
                ];
                $event->permissions[] = [
                    'heading' => Craft::t('counter', 'Counter'),
                    'permissions' => $permissions,
                ];
            }
        );
    }

    /**
     * Register plugin services
     *
     * @return void
     */
    private function _registerVariables(): void
    {
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, function(Event $event) {
            /** @var CraftVariable $variable */
            $variable = $event->sender;
            $variable->set('counter', CounterVariable::class);
        });
    }

    private function _registerWidgets(): void
    {
        Event::on(Dashboard::class, Dashboard::EVENT_REGISTER_WIDGET_TYPES, static function(RegisterComponentTypesEvent $event) {
            $event->types[] = AverageVisitors::class;
            $event->types[] = DecliningPages::class;
            $event->types[] = MaxOnline::class;
            $event->types[] = NextVisitedPages::class;
            $event->types[] = NotVisitedPages::class;
            $event->types[] = Online::class;
            $event->types[] = PageStatistics::class;
            $event->types[] = TopPages::class;
            $event->types[] = TrendingPages::class;
            $event->types[] = Visits::class;
            $event->types[] = Visitors::class;
            $event->types[] = VisitsRecent::class;
        });
    }

    private function _registerGraphQl(): void
    {
        // Register the GraphQL directive
        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_DIRECTIVES, function(RegisterGqlDirectivesEvent $event) {
            $event->directives[] = DateConvert::class;
        });

        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_QUERIES, static function(RegisterGqlQueriesEvent $event) {
            // Add the plugin GraphQL queries
            $event->queries = array_merge(
                $event->queries,
                CounterQuery::getQueries(),
                PageVisitsQuery::getQueries(),
                TopPagesQuery::getQueries(),
                TrendingPagesQuery::getQueries(),
            );
        });

        Event::on(
            Gql::class,
            Gql::EVENT_REGISTER_GQL_TYPES,
            function(RegisterGqlTypesEvent $event) {
                $event->types[] = CounterType::class;
                $event->types[] = PageVisitsType::class;
                $event->types[] = TopPagesType::class;
                $event->types[] = TrendingPagesType::class;
            }
        );

        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_SCHEMA_COMPONENTS, function(RegisterGqlSchemaComponentsEvent $event) {
            $label = Craft::t('counter', 'Counter');
            $sites = Craft::$app->getSites()->getAllSites();

            $event->queries[$label]['counter.sitesAll:read'] = [
                'label' => Craft::t('counter', 'Query for all sites'),
            ];

            foreach ($sites as $site) {
                $event->queries[$label]["counter.sites{$site->uid}:read"] = [
                    'label' => Craft::t('counter', 'Query for the “{site}” site', [
                        'site' => $site->name,
                    ]),
                ];
            }

            $event->queries[$label]['counter.debugMessage:read'] = [
                'label' => Craft::t('counter', 'Query the debug message'),
            ];

            $event->queries[$label]['counter.onlineVisitors:read'] = ['label' => Craft::t('counter', 'Online Visitors')];

            $event->queries[$label]['counter.maxOnline:read'] = [
                'label' => Craft::t('counter', 'Query for site max online'),
                'nested' => [
                    'counter.maxOnlineThisHour:read' => [
                        'label' => Craft::t('counter', 'This hour'),
                    ],
                    'counter.maxOnlinePreviousHour:read' => [
                        'label' => Craft::t('counter', 'Previous hour'),
                    ],
                    'counter.maxOnlineToday:read' => [
                        'label' => Craft::t('counter', 'Today'),
                    ],
                    'counter.maxOnlineYesterday:read' => [
                        'label' => Craft::t('counter', 'Yesterday'),
                    ],
                    'counter.maxOnlineThisWeek:read' => [
                        'label' => Craft::t('counter', 'This week'),
                    ],
                    'counter.maxOnlineThisMonth:read' => [
                        'label' => Craft::t('counter', 'This month'),
                    ],
                    'counter.maxOnlineThisYear:read' => [
                        'label' => Craft::t('counter', 'This year'),
                    ],
                    'counter.maxOnlinePast7Days:read' => [
                        'label' => Craft::t('counter', 'Past 7 days'),
                    ],
                    'counter.maxOnlinePast30Days:read' => [
                        'label' => Craft::t('counter', 'Past 30 days'),
                    ],
                    'counter.maxOnlinePast90Days:read' => [
                        'label' => Craft::t('counter', 'Past 90 days'),
                    ],
                    'counter.maxOnlinePastYear:read' => [
                        'label' => Craft::t('counter', 'Past year'),
                    ],
                    'counter.maxOnlineAll:read' => [
                        'label' => Craft::t('counter', 'All Time'),
                    ],
                    'counter.maxOnlineCustom:read' => [
                        'label' => Craft::t('counter', 'Custom'),
                    ],
                ],
            ];
            $event->queries[$label]['counter.visits:read'] = [
                'label' => Craft::t('counter', 'Query for site visits'),
                'nested' => [
                    'counter.visitsThisHour:read' => [
                        'label' => Craft::t('counter', 'This hour'),
                    ],
                    'counter.visitsPreviousHour:read' => [
                        'label' => Craft::t('counter', 'Previous hour'),
                    ],
                    'counter.visitsToday:read' => [
                        'label' => Craft::t('counter', 'Today'),
                    ],
                    'counter.visitsYesterday:read' => [
                        'label' => Craft::t('counter', 'Yesterday'),
                    ],
                    'counter.visitsThisWeek:read' => [
                        'label' => Craft::t('counter', 'This week'),
                    ],
                    'counter.visitsThisMonth:read' => [
                        'label' => Craft::t('counter', 'This month'),
                    ],
                    'counter.visitsThisYear:read' => [
                        'label' => Craft::t('counter', 'This year'),
                    ],
                    'counter.visitsPast7Days:read' => [
                        'label' => Craft::t('counter', 'Past 7 days'),
                    ],
                    'counter.visitsPast30Days:read' => [
                        'label' => Craft::t('counter', 'Past 30 days'),
                    ],
                    'counter.visitsPast90Days:read' => [
                        'label' => Craft::t('counter', 'Past 90 days'),
                    ],
                    'counter.visitsPastYear:read' => [
                        'label' => Craft::t('counter', 'Past year'),
                    ],
                    'counter.visitsAll:read' => [
                        'label' => Craft::t('counter', 'All Time'),
                    ],
                    'counter.visitsCustom:read' => [
                        'label' => Craft::t('counter', 'Custom'),
                    ],
                    'counter.visitsIgnoreInterval:read' => [
                        'label' => Craft::t('counter', 'Can query the number of visits while ignoring the interval'),
                    ],
                ],
            ];
            $event->queries[$label]['counter.visitors:read'] = [
                'label' => Craft::t('counter', 'Query for site visitors'),
                'nested' => [
                    'counter.visitorsThisHour:read' => [
                        'label' => Craft::t('counter', 'This hour'),
                    ],
                    'counter.visitorsPreviousHour:read' => [
                        'label' => Craft::t('counter', 'Previous hour'),
                    ],
                    'counter.visitorsToday:read' => [
                        'label' => Craft::t('counter', 'Today'),
                    ],
                    'counter.visitorsYesterday:read' => [
                        'label' => Craft::t('counter', 'Yesterday'),
                    ],
                    'counter.visitorsCustom:read' => [
                        'label' => Craft::t('counter', 'Custom'),
                    ],
                ],
            ];
            $event->queries[$label]['counter.averageVisitors:read'] = [
                'label' => Craft::t('counter', 'Query for site average visitors'),
                'nested' => [
                    'counter.averageVisitorsToday:read' => [
                        'label' => Craft::t('counter', 'Today'),
                    ],
                    'counter.averageVisitorsYesterday:read' => [
                        'label' => Craft::t('counter', 'Yesterday'),
                    ],
                    'counter.averageVisitorsThisWeek:read' => [
                        'label' => Craft::t('counter', 'This week'),
                    ],
                    'counter.averageVisitorsThisMonth:read' => [
                        'label' => Craft::t('counter', 'This month'),
                    ],
                    'counter.averageVisitorsThisYear:read' => [
                        'label' => Craft::t('counter', 'This year'),
                    ],
                    'counter.averageVisitorsPast7Days:read' => [
                        'label' => Craft::t('counter', 'Past 7 days'),
                    ],
                    'counter.averageVisitorsPast30Days:read' => [
                        'label' => Craft::t('counter', 'Past 30 days'),
                    ],
                    'counter.averageVisitorsPast90Days:read' => [
                        'label' => Craft::t('counter', 'Past 90 days'),
                    ],
                    'counter.averageVisitorsPastYear:read' => [
                        'label' => Craft::t('counter', 'Past year'),
                    ],
                    'counter.averageVisitorsAll:read' => [
                        'label' => Craft::t('counter', 'All Time'),
                    ],
                    'counter.averageVisitorsCustom:read' => [
                        'label' => Craft::t('counter', 'Custom'),
                    ],
                ],
            ];
            $event->queries[$label]['counter.pageVisits:read'] = [
                'label' => Craft::t('counter', 'Query for page visits'),
                'nested' => [
                    'counter.pageVisitsToday:read' => [
                        'label' => Craft::t('counter', 'Today'),
                    ],
                    'counter.pageVisitsYesterday:read' => [
                        'label' => Craft::t('counter', 'Yesterday'),
                    ],
                    'counter.pageVisitsThisWeek:read' => [
                        'label' => Craft::t('counter', 'This week'),
                    ],
                    'counter.pageVisitsThisMonth:read' => [
                        'label' => Craft::t('counter', 'This month'),
                    ],
                    'counter.pageVisitsThisYear:read' => [
                        'label' => Craft::t('counter', 'This year'),
                    ],
                    'counter.pageVisitsPreviousWeek:read' => [
                        'label' => Craft::t('counter', 'Previous week'),
                    ],
                    'counter.pageVisitsPreviousMonth:read' => [
                        'label' => Craft::t('counter', 'Previous month'),
                    ],
                    'counter.pageVisitsPreviousYear:read' => [
                        'label' => Craft::t('counter', 'Previous year'),
                    ],
                    'counter.pageVisitsAll:read' => [
                        'label' => Craft::t('counter', 'All Time'),
                    ],
                    'counter.pageVisitsAllIgnoreInterval:read' => [
                        'label' => Craft::t('counter', 'All Time (Ignoring Interval)'),
                    ],
                    'counter.pageVisitsLastVisit:read' => [
                        'label' => Craft::t('counter', 'Last Visit'),
                    ],
                ],
            ];

            $event->queries[$label]['counter.topPages:read'] = [
                'label' => Craft::t('counter', 'Query for top pages'),
                'nested' => [
                    'counter.topPagesToday:read' => [
                        'label' => Craft::t('counter', 'Today'),
                    ],
                    'counter.topPagesYesterday:read' => [
                        'label' => Craft::t('counter', 'Yesterday'),
                    ],
                    'counter.topPagesThisWeek:read' => [
                        'label' => Craft::t('counter', 'This week'),
                    ],
                    'counter.topPagesThisMonth:read' => [
                        'label' => Craft::t('counter', 'This month'),
                    ],
                    'counter.topPagesThisYear:read' => [
                        'label' => Craft::t('counter', 'This year'),
                    ],
                    /*
                    'counter.topPagesPreviousWeek:read' => [
                        'label' => Craft::t('counter', 'Previous week'),
                    ],
                    'counter.topPagesPreviousMonth:read' => [
                        'label' => Craft::t('counter', 'Previous month'),
                    ],
                    'counter.topPagesPreviousYear:read' => [
                        'label' => Craft::t('counter', 'Previous year'),
                    ],
                    */
                    'counter.topPagesAll:read' => [
                        'label' => Craft::t('counter', 'All Time'),
                    ],
                    'counter.topPagesAllIgnoreInterval:read' => [
                        'label' => Craft::t('counter', 'All Time (Ignoring Interval)'),
                    ],
                ],
            ];

            $event->queries[$label]['counter.trendingPages:read'] = [
                'label' => Craft::t('counter', 'Query for trending pages'),
                'nested' => [
                    'counter.trendingPagesToday:read' => [
                        'label' => Craft::t('counter', 'Today'),
                    ],
                    'counter.trendingPagesThisWeek:read' => [
                        'label' => Craft::t('counter', 'This week'),
                    ],
                    'counter.trendingPagesThisMonth:read' => [
                        'label' => Craft::t('counter', 'This month'),
                    ],
                    'counter.trendingPagesThisYear:read' => [
                        'label' => Craft::t('counter', 'This year'),
                    ],
                ],
            ];
        });

        Event::on(
            TypeManager::class,
            TypeManager::EVENT_DEFINE_GQL_TYPE_FIELDS,
            function(DefineGqlTypeFieldsEvent $event) {
                // Add counter attributes to all entries and categories
                if ($event->typeName == 'EntryInterface' || $event->typeName == 'CategoryInterface') {
                    if (HelpersGql::canQueryItem('pageVisitsToday')) {
                        $event->fields['todayViews'] = [
                            'name' => 'todayViews',
                            'type' => Type::int(),
                            'resolve' => function($source, $arguments, $context, $resolveInfo) {
                                return $this->_counterGqlColumns($source, $resolveInfo->fieldName);
                            },
                        ];
                    }
                    if (HelpersGql::canQueryItem('pageVisitsThisWeek')) {
                        $event->fields['thisWeekViews'] = [
                            'name' => 'thisWeekViews',
                            'type' => Type::int(),
                            'resolve' => function($source, $arguments, $context, $resolveInfo) {
                                return $this->_counterGqlColumns($source, $resolveInfo->fieldName);
                            },
                        ];
                    }
                    if (HelpersGql::canQueryItem('pageVisitsThisMonth')) {
                        $event->fields['thisMonthViews'] = [
                            'name' => 'thisMonthViews',
                            'type' => Type::int(),
                            'resolve' => function($source, $arguments, $context, $resolveInfo) {
                                return $this->_counterGqlColumns($source, $resolveInfo->fieldName);
                            },
                        ];
                    }
                    if (HelpersGql::canQueryItem('pageVisitsThisYear')) {
                        $event->fields['thisYearViews'] = [
                            'name' => 'thisYearViews',
                            'type' => Type::int(),
                            'resolve' => function($source, $arguments, $context, $resolveInfo) {
                                return $this->_counterGqlColumns($source, $resolveInfo->fieldName);
                            },
                        ];
                    }
                    if (HelpersGql::canQueryItem('pageVisitsAll')) {
                        $event->fields['allViews'] = [
                            'name' => 'allViews',
                            'type' => Type::int(),
                            'resolve' => function($source, $arguments, $context, $resolveInfo) {
                                return $this->_counterGqlColumns($source, $resolveInfo->fieldName);
                            },
                        ];
                    }
                    if (HelpersGql::canQueryItem('pageVisitsYesterday')) {
                        $event->fields['yesterdayViews'] = [
                            'name' => 'yesterdayViews',
                            'type' => Type::int(),
                            'resolve' => function($source, $arguments, $context, $resolveInfo) {
                                return $this->_counterGqlColumns($source, $resolveInfo->fieldName);
                            },
                        ];
                    }
                    if (HelpersGql::canQueryItem('pageVisitsPreviousWeek')) {
                        $event->fields['previousWeekViews'] = [
                            'name' => 'previousWeekViews',
                            'type' => Type::int(),
                            'resolve' => function($source, $arguments, $context, $resolveInfo) {
                                return $this->_counterGqlColumns($source, $resolveInfo->fieldName);
                            },
                        ];
                    }
                    if (HelpersGql::canQueryItem('pageVisitsPreviousMonth')) {
                        $event->fields['previousMonthViews'] = [
                            'name' => 'previousMonthViews',
                            'type' => Type::int(),
                            'resolve' => function($source, $arguments, $context, $resolveInfo) {
                                return $this->_counterGqlColumns($source, $resolveInfo->fieldName);
                            },
                        ];
                    }
                    if (HelpersGql::canQueryItem('pageVisitsPreviousYear')) {
                        $event->fields['previousYearViews'] = [
                            'name' => 'previousYearViews',
                            'type' => Type::int(),
                            'resolve' => function($source, $arguments, $context, $resolveInfo) {
                                return $this->_counterGqlColumns($source, $resolveInfo->fieldName);
                            },
                        ];
                    }
                }
            }
        );
    }

    private function _counterGqlColumns($source, $attribute)
    {
        $elementId = $source->id;
        $siteId = $source->siteId;
        $site = Craft::$app->sites->getSiteById($siteId);
        $return = null;
        if ($site) {
            $element = Craft::$app->elements->getElementById($elementId, null, $siteId);
            if ($url = $element->getUrl()) {
                $data = Counter::$plugin->pages->visits($url, $siteId);
                if (str_ends_with($attribute, "Views")) {
                    $attribute = lcfirst(substr($attribute, 0, -5));
                }
                $return = $data[$attribute] ?? '0';
            }
        }
        return $return;
    }
}
