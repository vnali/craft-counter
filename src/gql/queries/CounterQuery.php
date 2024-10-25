<?php

namespace vnali\counter\gql\queries;

use Craft;
use craft\gql\base\Query;
use vnali\counter\Counter;
use vnali\counter\gql\arguments\CounterArguments;
use vnali\counter\gql\helpers\Gql;
use vnali\counter\gql\types\CounterType;

class CounterQuery extends Query
{
    // Public Methods
    // =========================================================================
    public static function getQueries(bool $checkToken = true): array
    {
        if ($checkToken && (!Gql::canQueryItem('onlineVisitors') && !Gql::canQueryItem('maxOnline') && !Gql::canQueryItem('visits') && !Gql::canQueryItem('visitors') && !Gql::canQueryItem('averageVisitors'))) {
            return [];
        }
        return [
            'counter' => [
                'type' => CounterType::getType(),
                'args' => CounterArguments::getArguments(),
                'resolve' => function($root, $args, $context, $info) {
                    $result = [];
                    $fields = Gql::getTopLevelFieldNames($info);

                    $startDate = null;
                    $endDate = null;
                    if (isset($args['startDate'])) {
                        $startDate = $args['startDate'];
                    }
                    if (isset($args['endDate'])) {
                        $endDate = $args['endDate'];
                    }

                    if (isset($args['ignoreVisitsInterval'])) {
                        $ignoreVisitsInterval = $args['ignoreVisitsInterval'];
                    } else {
                        $ignoreVisitsInterval = false;
                    }

                    if ($ignoreVisitsInterval && !Gql::canQueryItem('visitsIgnoreInterval')) {
                        return ['debugMessage' => 'This schema has no access to query number of visits while ignoring interval'];
                    }

                    $dateRange = null;
                    if (isset($args['dateRange'])) {
                        $dateRange = $args['dateRange'];
                        switch ($dateRange) {
                            case 'thisHour':
                            case 'previousHour':
                                $allNotSupported = ['averageVisitors'];
                                foreach ($allNotSupported as $notSupported) {
                                    if (in_array($notSupported, $fields)) {
                                        return ['debugMessage' => $notSupported . ' is not supported for ' . $dateRange . ' date range'];
                                    }
                                }
                                break;
                            case 'yesterday':
                            case 'today':
                                // nothing to do
                                break;
                            case 'thisWeek':
                            case 'thisMonth':
                            case 'past7Days':
                            case 'past30Days':
                            case 'past90Days':
                            case 'all':
                            case 'pastYear':
                            case 'thisYear':
                                $allNotSupported = ['visitors'];
                                foreach ($allNotSupported as $notSupported) {
                                    if (in_array($notSupported, $fields)) {
                                        return ['debugMessage' => $notSupported . ' is not supported for ' . $dateRange . ' date range'];
                                    }
                                }
                                break;
                            case 'custom':
                                if (in_array('visitors', $fields)) {
                                    if ($startDate != $endDate) {
                                        return ['debugMessage' => 'Visitors can not be in fields list where start date and end date are not same'];
                                    }
                                }
                                if (!$startDate || !$endDate) {
                                    return ['debugMessage' => 'Start date and end date are required for custom date range.'];
                                }
                                break;
                            default:
                                return ['debugMessage' => 'Not supported date range'];
                        }

                        // Check access to dateRange
                        foreach ($fields as $field) {
                            if ($field != 'debugMessage' && $field != 'onlineVisitors' && $field != 'maxOnlineDate' && !Gql::canQueryItem($field . ucfirst($dateRange))) {
                                return ['debugMessage' => 'The selected dateRange is not allowed for ' . $field . ' via this schema'];
                            }
                        }
                    } else {
                        // date range is required if there is a field except debugMessage and onlineVisitors
                        foreach ($fields as $field) {
                            if ($field != 'debugMessage' && $field != 'onlineVisitors') {
                                return ['debugMessage' => 'Date range should be set'];
                            }
                        }
                    }

                    $onlineThreshold = null;
                    if (isset($args['onlineThreshold'])) {
                        $onlineThreshold = $args['onlineThreshold'];
                    }

                    if (isset($args['calendar'])) {
                        $calendar = $args['calendar'];
                    } else {
                        $calendar = 'gregorian';
                    }

                    $siteService = Craft::$app->sites;
                    if (isset($args['siteId'])) {
                        $siteId = $args['siteId'];
                        if ($siteId == "*") {
                            //$siteId = null;
                            $item = 'sitesAll';
                            if (!Gql::canQueryItem($item)) {
                                return ['debugMessage' => 'siteId * is not allowed'];
                            }
                        } elseif (is_numeric($siteId)) {
                            $site = $siteService->getSiteById($siteId);
                            if (!isset($site->uid) || !Gql::canQueryItem('sites' . $site->uid)) {
                                return ['debugMessage' => 'The siteId ' . $siteId . ' is not allowed for this schema'];
                            }
                        } else {
                            return ['debugMessage' => 'Invalid siteId is passed.'];
                        }
                    } else {
                        $site = $siteService->getPrimarySite();
                        $siteId = $site->id;
                        if (!isset($site->uid) || (!Gql::canQueryItem('sitesAll') && !Gql::canQueryItem('sites' . $site->uid))) {
                            return ['debugMessage' => 'The siteId ' . $site->name . ' is not allowed for this schema'];
                        }
                    }

                    if (in_array('maxOnline', $fields) || in_array('maxOnlineDate', $fields)) {
                        list($maxOnline, $maxOnlineDate) = Counter::$plugin->counter->maxOnline($dateRange, $startDate, $endDate, $siteId, $calendar);
                        if (in_array('maxOnline', $fields)) {
                            $result['maxOnline'] = $maxOnline ?? 0;
                        }
                        if (in_array('maxOnlineDate', $fields)) {
                            $result['maxOnlineDate'] = $maxOnlineDate;
                        }
                    }

                    if (in_array('onlineVisitors', $fields)) {
                        $onlineVisitors = Counter::$plugin->counter->onlineVisitors($siteId, $onlineThreshold);
                        $result['onlineVisitors'] = $onlineVisitors;
                    }

                    if (in_array('visitors', $fields)) {
                        $result['visitors'] = Counter::$plugin->counter->visitors($dateRange, $startDate, $endDate, $siteId, $calendar);
                    }

                    if (in_array('averageVisitors', $fields)) {
                        $result['averageVisitors'] = Counter::$plugin->counter->averageVisitors($dateRange, $startDate, $endDate, $siteId, $calendar);
                    }

                    if (in_array('visits', $fields)) {
                        $result['visits'] = Counter::$plugin->counter->visits($dateRange, $startDate, $endDate, $siteId, $ignoreVisitsInterval, $calendar);
                    }

                    $result['debugMessage'] = 'ok';
                    return $result;
                },
                'description' => 'This query is used to query visits, visitors, online visitors, average visitors and max online.',
            ],
        ];
    }
}
