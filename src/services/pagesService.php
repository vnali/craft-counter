<?php

/**
 * @copyright Copyright (c) vnali
 */

namespace vnali\counter\services;

use Craft;
use craft\db\Query;
use craft\helpers\DateTimeHelper as HelpersDateTimeHelper;
use DateInterval;
use DateTime;
use DateTimeZone;
use IntlCalendar;
use IntlDateFormatter;
use vnali\counter\Counter;
use vnali\counter\helpers\DateTimeHelper;
use vnali\counter\models\Settings;
use vnali\counter\records\PageVisitsRecord;
use yii\base\Component;
use yii\db\Expression;

/**
 * Page visits Service class
 */
class pagesService extends Component
{
    /**
     * Returns page visits
     *
     * @param string $page
     * @param string|null $siteId
     * @param array|null $dateRanges
     * @return array
     */
    public function visits(string $page, ?string $siteId, ?array $dateRanges = []): array
    {
        if (!$siteId) {
            $site = craft::$app->getSites()->getPrimarySite();
            $siteId = $site->id;
        }

        if (!$dateRanges) {
            $dateRanges = ['all', 'allIgnoreInterval', 'today', 'yesterday', 'thisWeek', 'previousWeek', 'thisMonth', 'previousMonth', 'thisYear', 'previousYear', 'lastVisit'];
        }

        $query = PageVisitsRecord::find()->where(['page' => $page]);
        if ($siteId != '*') {
            $query = $query->andWhere(['siteId' => $siteId]);
        }
        /** @var PageVisitsRecord|null $pageVisit */
        $pageVisit = $query->one();

        $result = [];
        if ($pageVisit) {
            $siteId = $pageVisit->siteId;
            $settings = Counter::$plugin->getSettings();
            /** @var Settings $settings */
            $siteSettings = json_decode($settings->siteSettings, true);
            $siteService = Craft::$app->sites;
            $site = $siteService->getSiteById($siteId);
            $siteUnique = $site->uid;
            if (isset($site)) {
                if ($siteSettings[$siteUnique]['calendar']) {
                    $calendarSystem = $siteSettings[$siteUnique]['calendar'];
                } else {
                    $calendarSystem = 'gregorian';
                }

                $tz = Craft::$app->getTimeZone();
                $tzTime = new DateTimeZone($tz);
                $now = new DateTime('now', $tzTime);
                $format = null;

                foreach ($dateRanges as $field) {
                    switch ($field) {
                        case 'all':
                            break;
                        case 'allIgnoreInterval':
                            break;
                        case 'lastVisit':
                            break;
                        case 'today':
                            $format = 'yyyy/MM/dd';
                            break;
                        case 'thisMonth':
                            $format = 'yyyy/MM';
                            break;
                        case 'thisYear':
                            $format = 'yyyy';
                            break;
                        case 'yesterday':
                            $format = 'yyyy/MM/dd';
                            break;
                        case 'thisWeek':
                        case 'previousWeek':
                            break;
                        case 'previousMonth':
                            $format = 'yyyy/MM';
                            break;
                        case 'previousYear':
                            $format = 'yyyy';
                            break;
                        default:
                            return ['debugMessage' => 'not accepted ' . $field];
                    }

                    /** @var PageVisitsRecord|null $pageVisit */
                    $dateUpdated = new DateTime($pageVisit->dateUpdated, new \DateTimeZone("UTC"));
                    $dateUpdated->setTimezone($tzTime);
                    if ($field == 'all') {
                        if (isset($pageVisit['allTime'])) {
                            $result['all'] = $pageVisit['allTime'];
                        }
                    } elseif ($field == 'allIgnoreInterval') {
                        if (isset($pageVisit['allTimeIgnoreInterval'])) {
                            $result['allIgnoreInterval'] = $pageVisit['allTimeIgnoreInterval'];
                        }
                    } elseif ($field == 'lastVisit') {
                        if (isset($pageVisit['lastVisit'])) {
                            $result['lastVisit'] = $pageVisit['lastVisit'];
                        }
                    } elseif ($field == 'today') {
                        $dateUpdated1 = DateTimeHelper::intlDate($dateUpdated, $calendarSystem, $format);
                        $now1 = DateTimeHelper::intlDate($now, $calendarSystem, $format);
                        if ($now1 == $dateUpdated1) {
                            if (isset($pageVisit['today'])) {
                                $result['today'] = $pageVisit['today'];
                            }
                        } else {
                            $result['today'] = 0;
                        }
                    } elseif ($field == 'yesterday') {
                        $dateUpdated1 = DateTimeHelper::intlDate($dateUpdated, $calendarSystem, $format);
                        $now1 = DateTimeHelper::intlDate($now, $calendarSystem, $format);
                        if ($now1 == $dateUpdated1) {
                            if (isset($pageVisit['yesterday'])) {
                                $result['yesterday'] = $pageVisit['yesterday'];
                            } else {
                                $result['yesterday'] = 0;
                            }
                        } else {
                            $now1 = clone $now;
                            $dateUpdated1 = clone $dateUpdated;
                            $dateUpdated1->modify('+1 day');
                            if ($dateUpdated1->format('Y-m-d') === $now1->format('Y-m-d')) {
                                if (isset($pageVisit['today'])) {
                                    $result['yesterday'] = $pageVisit['today'];
                                }
                            } else {
                                $result['yesterday'] = 0;
                            }
                        }
                    } elseif ($field == 'thisWeek') {
                        $nowTz1 = clone $now;
                        $dateUpdatedTz1 = clone $dateUpdated;
                        // Check if value for this week is valid
                        if ($dateUpdatedTz1 >= DateTimeHelper::thisWeek($siteId, $nowTz1, $tzTime)) {
                            if (isset($pageVisit['thisWeek'])) {
                                $result['thisWeek'] = $pageVisit['thisWeek'];
                            } else {
                                $result['thisWeek'] = 0;
                            }
                        } else {
                            // there is no visit within this week so value for this week is not valid, use 0
                            $result['thisWeek'] = 0;
                        }
                    } elseif ($field == 'previousWeek') {
                        $nowTz1 = clone $now;
                        $dateUpdatedTz1 = clone $dateUpdated;
                        // if the latest update is within this week, value for previous week is valid too
                        if ($dateUpdatedTz1 >= DateTimeHelper::thisWeek($siteId, $nowTz1, $tzTime)) {
                            if (isset($pageVisit['previousWeek'])) {
                                $result['previousWeek'] = $pageVisit['previousWeek'];
                            } else {
                                $result['previousWeek'] = 0;
                            }
                        } elseif ($dateUpdatedTz1 >= DateTimeHelper::lastWeek($siteId, $nowTz1, $tzTime)) {
                            // if the last update is within the previous week, use value of this week for previous week
                            if (isset($pageVisit['thisWeek'])) {
                                $result['previousWeek'] = $pageVisit['thisWeek'];
                            }
                        } else {
                            $result['previousWeek'] = 0;
                        }
                    } elseif ($field == 'thisMonth') {
                        $dateUpdated1 = DateTimeHelper::intlDate($dateUpdated, $calendarSystem, $format);
                        $now1 = DateTimeHelper::intlDate($now, $calendarSystem, $format);
                        if ($now1 == $dateUpdated1) {
                            if (isset($pageVisit['thisMonth'])) {
                                $result['thisMonth'] = $pageVisit['thisMonth'];
                            } else {
                                $result['thisMonth'] = 0;
                            }
                        } else {
                            $result['thisMonth'] = 0;
                        }
                    } elseif ($field == 'previousMonth') {
                        $dateUpdated1 = clone $dateUpdated;
                        $dateUpdated12 = DateTimeHelper::intlDate($dateUpdated1, $calendarSystem, $format);
                        $now1 = clone $now;
                        $now12 = DateTimeHelper::intlDate($now1, $calendarSystem, $format);
                        if ($now12 == $dateUpdated12) {
                            if (isset($pageVisit['previousMonth'])) {
                                $result['previousMonth'] = $pageVisit['previousMonth'];
                            } else {
                                $result['previousMonth'] = 0;
                            }
                        } else {
                            $calendar = IntlCalendar::createInstance();
                            $calendar->set(
                                (int)$dateUpdated1->format('Y'),
                                $dateUpdated1->format('n') - 1,
                                (int)$dateUpdated1->format('j'),
                            );
                            $calendar->add(IntlCalendar::FIELD_MONTH, 1);
                            $calendar->set(IntlCalendar::FIELD_DAY_OF_MONTH, 1);
                            $formatter = new IntlDateFormatter('en_us@calendar=' . $calendarSystem, \IntlDateFormatter::FULL, \IntlDateFormatter::FULL, $tz, \IntlDateFormatter::TRADITIONAL, 'yyyy/MM');
                            $nextMonth = $formatter->format($calendar);
                            $monthNow = DateTimeHelper::intlDate($now1, $calendarSystem, 'yyyy/MM');
                            if ($nextMonth === $monthNow) {
                                if (isset($pageVisit['thisMonth'])) {
                                    $result['previousMonth'] = $pageVisit['thisMonth'];
                                }
                            } else {
                                $result['previousMonth'] = 0;
                            }
                        }
                    } elseif ($field == 'thisYear') {
                        $dateUpdated1 = DateTimeHelper::intlDate($dateUpdated, $calendarSystem, $format);
                        $now1 = DateTimeHelper::intlDate($now, $calendarSystem, $format);
                        if ($now1 == $dateUpdated1) {
                            if (isset($pageVisit['thisYear'])) {
                                $result['thisYear'] = $pageVisit['thisYear'];
                            }
                        } else {
                            $result['thisYear'] = 0;
                        }
                    } elseif ($field == 'previousYear') {
                        $dateUpdated1 = clone $dateUpdated;
                        $dateUpdated12 = DateTimeHelper::intlDate($dateUpdated1, $calendarSystem, $format);
                        $now1 = clone $now;
                        $now12 = DateTimeHelper::intlDate($now1, $calendarSystem, $format);
                        if ($now12 == $dateUpdated12) {
                            if (isset($pageVisit['previousYear'])) {
                                $result['previousYear'] = $pageVisit['previousYear'];
                            } else {
                                $result['previousYear'] = 0;
                            }
                        } else {
                            $calendar = IntlCalendar::createInstance();
                            $calendar->set(
                                (int)$dateUpdated1->format('Y'),
                                $dateUpdated1->format('n') - 1,
                                (int)$dateUpdated1->format('j'),
                            );
                            $calendar->roll(IntlCalendar::FIELD_YEAR, 1);
                            $calendar->set(IntlCalendar::FIELD_MONTH, 1);
                            $calendar->set(IntlCalendar::FIELD_DAY_OF_MONTH, 1);
                            $formatter = new IntlDateFormatter('en_us@calendar=' . $calendarSystem, \IntlDateFormatter::FULL, \IntlDateFormatter::FULL, $tz, \IntlDateFormatter::TRADITIONAL, 'yyyy');
                            $nextYear = $formatter->format($calendar);
                            $yearNow = DateTimeHelper::intlDate($now1, $calendarSystem, 'yyyy');
                            if ($nextYear === $yearNow) {
                                if (isset($pageVisit['thisYear'])) {
                                    $result['previousYear'] = $pageVisit['thisYear'];
                                }
                            } else {
                                $result['previousYear'] = 0;
                            }
                        }
                    }
                    if ($field == 'lastVisit') {
                        $lastVisit = new DateTime($pageVisit['lastVisit'], new \DateTimeZone("UTC"));
                        $tz = Craft::$app->getTimeZone();
                        $tzTime = new DateTimeZone($tz);
                        $lastVisit->setTimezone($tzTime);
                        $result['lastVisit'] = $lastVisit->format('Y-m-d H:i:s');
                    }
                }
                $result['debugMessage'] = 'ok';
            } else {
                $result['debugMessage'] = 'The site is not valid';
            }
        } else {
            return ['debugMessage' => 'the page is not found'];
        }
        return $result;
    }


    /**
     * Returns top pages
     *
     * @param string $dateRange
     * @param string|null $siteId
     * @param int|null $limit
     * @return array|null
     */
    public function top(?string $dateRange, ?string $siteId = null, ?int $limit = null): ?array
    {
        if (!$limit) {
            $limit = 20;
        }

        switch ($dateRange) {
            case 'all':
                $format = 'yyyy/MM/dd';
                $dateRange = 'allTime';
                break;
            case 'allIgnoreInterval':
                $format = 'yyyy/MM/dd';
                $dateRange = 'allTimeIgnoreInterval';
                break;
            case 'today':
                $format = 'yyyy/MM/dd';
                break;
            case 'thisWeek':
                $format = 'w';
                break;
            case 'thisMonth':
                $format = 'yyyy/MM';
                break;
            case 'thisYear':
                $format = 'yyyy';
                break;
            case 'yesterday':
                $format = 'yyyy/MM/dd';
                break;
                // Todo: implement this for other date ranges
                /*
            case 'previousWeek':
                $format = 'w';
                break;
            case 'previousMonth':
                $format = 'yyyy/MM';
                break;
            case 'previousYear':
                $format = 'yyyy';
                break;
            */
            default:
                return null;
        }

        $settings = Counter::$plugin->getSettings();
        /** @var Settings $settings */
        $siteSettings = json_decode($settings->siteSettings, true);

        $tz = Craft::$app->getTimeZone();
        $tzTime = new DateTimeZone($tz);
        $now = new DateTime('now', $tzTime);

        $siteService = Craft::$app->sites;
        if (!$siteId) {
            $site = $siteService->getPrimarySite();
            $siteId = $site->id;
        }

        $visits = [];
        $nowDates = [];
        $calendarSystems = [];
        $sites = $siteService->getAllSites();
        $pageVisitsQuery = PageVisitsRecord::find();
        $notAllowedSiteIds = [];
        foreach ($sites as $key => $site) {
            $siteUnique = $site->uid;
            if (isset($siteSettings[$siteUnique]['calendar']) && $siteSettings[$siteUnique]['calendar']) {
                $calendarSystem = $siteSettings[$siteUnique]['calendar'];
                $calendarSystems[$site->id] = $calendarSystem;
                $nowDates[$site->id] = DateTimeHelper::intlDate($now, $calendarSystem, $format);
            } else {
                craft::warning('calendar is not specified for ' . $site->name);
                $notAllowedSiteIds[] = $site->id;
            }
        }

        if ($siteId != '*') {
            $pageVisitsQuery->where(['siteId' => $siteId]);
        }

        if (count($notAllowedSiteIds) > 0) {
            $pageVisitsQuery->andWhere(['not in', 'siteId', $notAllowedSiteIds]);
        }

        $pageVisitsQuery->andWhere(['not', ["lastVisit" => null]]);
        $pageVisitsRecords = $pageVisitsQuery->orderBy("$dateRange desc")->all();

        $count = 0;
        $low = 0;
        foreach ($pageVisitsRecords as $pageVisitRecord) {
            $valid = false;
            /** @var PageVisitsRecord  $pageVisitRecord */
            $recordSiteId = $pageVisitRecord->siteId;
            // if site is not available
            if (!$siteService->getSiteById($recordSiteId)) {
                continue;
            }

            $dateVisited = new DateTime($pageVisitRecord->lastVisit, new \DateTimeZone("UTC"));
            $dateVisited->setTimezone($tzTime);

            if ($dateRange == 'allTime' || $dateRange == 'allTimeIgnoreInterval') {
                $valid = true;
            } elseif ($dateRange != 'thisWeek' && $dateRange != 'previousWeek') {
                $dateVisited = DateTimeHelper::intlDate($dateVisited, $calendarSystems[$recordSiteId], $format);
                if ($nowDates[$recordSiteId] == $dateVisited) {
                    $valid = true;
                }
            } else {
                // if dateUpdated is in the the range of this week, this week and previous week data is valid
                $nowTz1 = clone $now;
                $dateVisitedTz1 = clone $dateVisited;
                if ($dateVisitedTz1 >= DateTimeHelper::thisWeek($recordSiteId, $nowTz1, $tzTime)) {
                    $valid = true;
                }
            }

            if ($valid && ($pageVisitRecord->{$dateRange} > 0) && $count < $limit) {
                $count++;
                $visit = [];
                $visit['page'] = $pageVisitRecord->page;
                $visit['visits'] = $pageVisitRecord->{$dateRange};
                $visit['debugMessage'] = 'ok';
                $visits[] = $visit;
            }

            if (isset($visit['visits']) && $count >= $limit) {
                $low = $visit['visits'];
                break;
            }
        }

        // if date range is yesterday, we also should calculate pages that visited yesterday but not visited today
        if ($dateRange == 'yesterday') {
            $startDate = HelpersDateTimeHelper::toDateTime(strtotime('yesterday'));
            $startDate->setTime(0, 0);
            $startDate = $startDate->format('Y-m-d H:i:s');
            $endDate = HelpersDateTimeHelper::toDateTime(strtotime('yesterday'));
            $endDate->setTime(23, 59);
            $endDate = $endDate->format('Y-m-d H:i:s');
            $pageVisitsQuery->andWhere(['>=', 'lastVisit', $startDate]);
            $pageVisitsQuery->andWhere(['<=', 'lastVisit', $endDate]);
            $pageVisitsQuery->andWhere(['>=', 'today', $low]); // Don't allow the top of this results to be smaller the bottom of previous
            $pageVisitsQuery->orderBy("today desc");
            $pageVisitsQuery->limit($limit);
            $pageVisitsRecords = $pageVisitsQuery->all();

            foreach ($pageVisitsRecords as $pageVisitRecord) {
                /** @var PageVisitsRecord  $pageVisitRecord */
                $recordSiteId = $pageVisitRecord->siteId;
                // if site is not available
                if (!$siteService->getSiteById($recordSiteId)) {
                    continue;
                }

                if (((int)$pageVisitRecord->today > 0)) {
                    $visit = [];
                    $visit['page'] = $pageVisitRecord->page;
                    $visit['visits'] = $pageVisitRecord->today;
                    $visit['debugMessage'] = 'ok';
                    $visits[] = $visit;
                }
            }

            usort($visits, function($a, $b) {
                return $b['visits'] <=> $a['visits'];
            });

            $visits = array_slice($visits, 0, $limit);
        }

        return $visits;
    }

    /**
     * Returns trending page
     *
     * @param string $dateRange
     * @param string|null $siteId
     * @param string|null $growthType
     * @param bool|null $ignoreNewPages
     * @param int|null $limit
     * @return array|null
     */
    public function trending(string $dateRange, ?string $siteId = null, ?string $growthType = null, ?bool $ignoreNewPages = null, ?int $limit = null): ?array
    {
        $siteService = Craft::$app->sites;

        if (!$siteId) {
            $site = $siteService->getPrimarySite();
            $siteId = $site->id;
        }

        if (!$ignoreNewPages) {
            $ignoreNewPages = false;
        }

        if (!$limit) {
            $limit = 20;
        }

        if (!$growthType) {
            $growthType = 'count';
        }

        return $this->_analyzePages('trending', $dateRange, $siteId, $growthType, $ignoreNewPages, $limit);
    }

    /**
     * Returns declining pages
     *
     * @param string $dateRange
     * @param string|null $siteId
     * @param string|null $declineType
     * @param int|null $limit
     * @return array|null
     */
    public function declining(string $dateRange, ?string $siteId = null, ?string $declineType = null, ?int $limit = null): ?array
    {
        $siteService = Craft::$app->sites;
        if (!$siteId) {
            $site = $siteService->getPrimarySite();
            $siteId = $site->id;
        }

        if (!$limit) {
            $limit = 20;
        }

        if (!$declineType) {
            $declineType = 'count';
        }

        return $this->_analyzePages('declining', $dateRange, $siteId, $declineType, true, $limit);
    }

    /**
     * Analyze trending and declining pages
     *
     * @param string $analyze
     * @param string $dateRange
     * @param string $siteId
     * @param string $analyzeType
     * @param bool $ignoreNewPages
     * @param int $limit
     * @return array|null
     */
    private function _analyzePages(string $analyze, string $dateRange, string $siteId, string $analyzeType, bool $ignoreNewPages, int $limit): ?array
    {
        switch ($dateRange) {
            case 'today':
                $previous = 'yesterday';
                $format = 'yyyy/MM/dd';
                break;
            case 'thisWeek':
                $previous = 'previousWeek';
                $format = 'w';
                break;
            case 'thisMonth':
                $previous = 'previousMonth';
                $format = 'yyyy/MM';
                break;
            case 'thisYear':
                $previous = 'previousYear';
                $format = 'yyyy';
                break;
            default:
                return null;
        }

        $query = new Query();
        $query->from('{{%counter_page_visits}}' . ' page_visits');
        $siteService = Craft::$app->sites;

        if ($siteId != '*') {
            $query->andWhere(['siteId' => $siteId]);
        }

        $query->andWhere(['not', ["lastVisit" => null]]);

        $settings = Counter::$plugin->getSettings();
        /** @var Settings $settings */
        $siteSettings = json_decode($settings->siteSettings, true);

        $tz = Craft::$app->getTimeZone();
        $tzTime = new DateTimeZone($tz);
        $now = new DateTime('now', $tzTime);

        if (Craft::$app->getDb()->getIsPgsql()) {
            if ($analyzeType == 'percentage') {
                // use ::numeric to avoid 1/2 return to 0
                $query->select(['*', new Expression('ROUND((( "' . $dateRange . '"::numeric - "' . $previous . '"::numeric) / "' . $previous . '"::numeric) * 100, 2) AS result')]);
                $query->andWhere(['!=', '"' . $previous . '"', 0]);
            } else {
                $query->select(['*', new Expression('"' . $dateRange . '" - ' . '"' . $previous . '" AS result')]);
                if ($ignoreNewPages) {
                    $query->andWhere(['!=', '"' . $previous . '"', 0]);
                }
            }
        } else {
            if ($analyzeType == 'percentage') {
                $query->select(['*', new Expression("ROUND(($dateRange - $previous) / $previous * 100, 2) AS result")]);
                $query->andWhere(['!=', "$previous", 0]);
            } else {
                $query->select(['*', new Expression("$dateRange - $previous AS result")]);
                if ($ignoreNewPages) {
                    $query->andWhere(['!=', "$previous", 0]);
                }
            }
        }

        $sites = $siteService->getAllSites();
        $nowDates = [];
        $notAllowedSiteIds = [];
        $calendarSystems = [];
        foreach ($sites as $site) {
            $siteUnique = $site->uid;
            if (isset($siteSettings[$siteUnique]['calendar'])) {
                $calendarSystem = $siteSettings[$siteUnique]['calendar'];
                $calendarSystems[$site->id] = $calendarSystem;
                $nowDates[$site->id] = DateTimeHelper::intlDate($now, $calendarSystem, $format);
            } else {
                craft::warning('calendar is not specified for ' . $site->name);
                $notAllowedSiteIds[] = $site->id;
            }
        }

        if (count($notAllowedSiteIds) > 0) {
            $query->andWhere(['not in', 'siteId', $notAllowedSiteIds]);
        }

        if ($analyze == 'trending') {
            $index = 'growth';
            $query->orderBy('result desc');
            $query->addOrderBy("$dateRange desc");
        } else {
            $index = 'decline';
            $query->orderBy('result asc');
            $query->addOrderBy("$dateRange asc");
        }

        $rows = $query->all();
        $count = 0;
        $visits = [];

        foreach ($rows as $row) {
            if ($analyze == 'trending' && $row['result'] <= 0) {
                break;
            }
            if ($analyze == 'declining' && $row['result'] >= 0) {
                break;
            }

            $rowSiteId = $row['siteId'];
            $site = $siteService->getSiteById($rowSiteId);
            // If site is not available
            if ($site) {
                $valid = false;
                $dateVisited = new DateTime($row['lastVisit'], new \DateTimeZone("UTC"));
                $dateVisited->setTimezone($tzTime);

                if ($dateRange != 'thisWeek') {
                    $dateVisited = DateTimeHelper::intlDate($dateVisited, $calendarSystems[$rowSiteId], $format);
                    if ($nowDates[$rowSiteId] == $dateVisited) {
                        $valid = true;
                    }
                } else {
                    $nowTz1 = clone $now;
                    $dateVisitedTz1 = clone $dateVisited;
                    if ($dateVisitedTz1 >= DateTimeHelper::thisWeek($rowSiteId, $nowTz1, $tzTime)) {
                        $valid = true;
                    }
                }

                if ($valid && ($count < $limit)) {
                    $count++;
                    $visit = [];
                    $visit['page'] = $row['page'];
                    $visit['current'] = $row[$dateRange];
                    $visit['previous'] = $row[$previous];
                    $visit[$index] = $row['result'] . (($analyzeType == 'percentage') ? '%' : '');
                    $visit['debugMessage'] = 'ok';
                    $visits[] = $visit;
                }

                if ($count >= $limit) {
                    break;
                }
            }
        }

        return $visits;
    }

    /**
     * Return list of not visited pages
     *
     * @param string $dateRange
     * @param string|null $siteId
     * @param int|null $limit
     * @param bool|null $sortAsc
     * @param string|null $calendar
     * @return array|null
     */
    public function notVisited(string $dateRange, ?string $siteId = null, ?int $limit = null, ?bool $sortAsc = false, ?string $calendar = null): ?array
    {
        $query = new Query();
        $query->from('{{%counter_page_visits}}' . ' page_visits');

        $siteService = Craft::$app->sites;
        if (!$siteId) {
            $site = $siteService->getPrimarySite();
            $siteId = $site->id;
        }

        if ($siteId != '*') {
            $query->andWhere(['siteId' => $siteId]);
        }

        if (!$limit) {
            $limit = 20;
        }

        if (!$calendar) {
            $calendar = 'gregorian';
        }

        $tz = Craft::$app->getTimeZone();
        $tzTime = new DateTimeZone($tz);
        $now = new DateTime('now', $tzTime);

        if ($sortAsc) {
            $query->orderBy('lastVisit asc');
        } else {
            $query->orderBy('lastVisit desc');
        }

        // The page has a record, because of a visit in range of ignore visits, so ignore it.
        // currently we only list pages that have at least one view before and not visited recently
        $query->andWhere(['not', ["lastVisit" => null]]);

        $rows = $query->all();

        $count = 0;
        $pages = [];
        foreach ($rows as $row) {
            // if site is not available
            if (!$siteService->getSiteById($row['siteId'])) {
                continue;
            }

            if ($count >= $limit) {
                break;
            }

            $valid = false;
            $dateVisited = new DateTime($row['lastVisit'], new \DateTimeZone("UTC"));
            $dateVisited->setTimezone($tzTime);

            if ($dateRange == 'today') {
                $now1 = clone $now;
                $now1->setTime(0, 0);
                if ($dateVisited->format('Y-m-d') != $now1->format('Y-m-d')) {
                    $valid = true;
                }
            } elseif ($dateRange == 'past2Days') {
                $now1 = clone $now;
                $now1->setTime(0, 0);
                $interval = new DateInterval('P1D');
                $now1->sub($interval);
                if ($dateVisited->format('Y-m-d H:i:s') < $now1->format('Y-m-d H:i:s')) {
                    $valid = true;
                }
            } elseif ($dateRange == 'past7Days') {
                $now1 = clone $now;
                $now1->setTime(0, 0);
                $interval = new DateInterval('P6D');
                $now1->sub($interval);
                if ($dateVisited->format('Y-m-d H:i:s') < $now1->format('Y-m-d H:i:s')) {
                    $valid = true;
                }
            } elseif ($dateRange == 'past30Days') {
                $now1 = clone $now;
                $now1->setTime(0, 0);
                $interval = new DateInterval('P29D');
                $now1->sub($interval);
                if ($dateVisited->format('Y-m-d H:i:s') < $now1->format('Y-m-d H:i:s')) {
                    $valid = true;
                }
            } elseif ($dateRange == 'past90Days') {
                $now1 = clone $now;
                $now1->setTime(0, 0);
                $interval = new DateInterval('P89D');
                $now1->sub($interval);
                if ($dateVisited->format('Y-m-d H:i:s') < $now1->format('Y-m-d H:i:s')) {
                    $valid = true;
                }
            }
            if ($valid) {
                $result = [];
                $result['lastVisit'] = (($calendar == 'gregorian') ? $dateVisited->format('Y-m-d H:i:s') : DateTimeHelper::intlDate($dateVisited, $calendar));
                $result['page'] = $row['page'];
                $pages[] = $result;
                $count++;
            }
        }

        return $pages;
    }
}
