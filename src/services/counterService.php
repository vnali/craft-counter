<?php

/**
 * @copyright Copyright (c) vnali
 */

namespace vnali\counter\services;

use Craft;
use craft\helpers\DateTimeHelper as CraftDateTimeHelper;
use DateTime;
use DateTimeZone;
use IntlCalendar;
use IntlDateFormatter;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use vnali\counter\Counter;
use vnali\counter\events\CountEvent;
use vnali\counter\helpers\DateTimeHelper;
use vnali\counter\helpers\DateTimeHelper as CounterDateTimeHelper;
use vnali\counter\helpers\IpHelper;
use vnali\counter\helpers\UrlHelper as CounterUrlHelper;
use vnali\counter\models\Settings;
use vnali\counter\records\CounterRecord;
use vnali\counter\records\PageVisitsRecord;
use vnali\counter\records\VisitorsRecord;
use vnali\counter\stats\AverageVisitors;
use vnali\counter\stats\MaxOnline;
use vnali\counter\stats\Visitors;
use vnali\counter\stats\Visits;
use yii\base\Component;

/**
 * Counter Service class
 */
class counterService extends Component
{
    /**
     * @event CountEvent
     */
    public const EVENT_BEFORE_COUNT = 'beforeCount';

    /**
     * @event CountEvent
     */
    public const EVENT_AFTER_COUNT = 'afterCount';

    /**
     * Returns max online
     *
     * @param string $dateRange
     * @param string|null $startDate
     * @param string|null $endDate
     * @param string|null $siteId
     * @param string|null $calendar
     * @return array
     */
    public function maxOnline(string $dateRange, ?string $startDate, ?string $endDate, ?string $siteId, ?string $calendar): array
    {
        $stat = new MaxOnline(
            $dateRange,
            isset($startDate) ? CraftDateTimeHelper::toDateTime($startDate, true) : null,
            isset($endDate) ? CraftDateTimeHelper::toDateTime($endDate, true) : null,
            $calendar,
            $siteId,
        );
        list($maxOnline, $maxOnlineDate) = $stat->get();
        return array($maxOnline, $maxOnlineDate);
    }

    /**
     * Returns visits
     *
     * @param string $dateRange
     * @param string|null $startDate
     * @param string|null $endDate
     * @param string|null $siteId
     * @param boolean|null $ignoreVisitsInterval
     * @param string|null $calendar
     * @param string|null $preferredInterval
     * @return int
     */
    public function visits(string $dateRange, ?string $startDate, ?string $endDate, ?string $siteId, ?bool $ignoreVisitsInterval, ?string $calendar, ?string $preferredInterval = null): int
    {
        $stat = new Visits(
            $dateRange,
            isset($startDate) ? CraftDateTimeHelper::toDateTime($startDate, true) : null,
            isset($endDate) ? CraftDateTimeHelper::toDateTime($endDate, true) : null,
            $calendar,
            $siteId,
            $ignoreVisitsInterval,
            $preferredInterval
        );
        list($visits) = $stat->get();
        return $visits;
    }

    /**
     * Returns visitors
     *
     * @param string $dateRange
     * @param string|null $startDate
     * @param string|null $endDate
     * @param string|null $siteId
     * @param string|null $calendar
     * @return int|null
     */
    public function visitors(string $dateRange, ?string $startDate, ?string $endDate, ?string $siteId, ?string $calendar): ?int
    {
        $stat = new Visitors(
            $dateRange,
            isset($startDate) ? CraftDateTimeHelper::toDateTime($startDate, true) : null,
            isset($endDate) ? CraftDateTimeHelper::toDateTime($endDate, true) : null,
            $calendar,
            $siteId,
        );
        list($visitors) = $stat->get();
        return $visitors;
    }

    /**
     * Returns average visitors
     *
     * @param string $dateRange
     * @param string|null $startDate
     * @param string|null $endDate
     * @param string|null $siteId
     * @param string|null $calendar
     * @return int
     */
    public function averageVisitors(string $dateRange, ?string $startDate, ?string $endDate, ?string $siteId, ?string $calendar): int
    {
        $stat = new AverageVisitors(
            $dateRange,
            isset($startDate) ? CraftDateTimeHelper::toDateTime($startDate, true) : null,
            isset($endDate) ? CraftDateTimeHelper::toDateTime($endDate, true) : null,
            $calendar,
            $siteId,
        );
        list($averageVisitors) = $stat->get();
        return $averageVisitors;
    }

    /**
     * Returns online visitors
     *
     * @param string|null $siteId
     * @param int|null $onlineThreshold
     * @return int
     */
    public function onlineVisitors(?string $siteId = null, ?int $onlineThreshold = null): int
    {
        $now = new DateTime('now', new DateTimeZone('UTC'));
        $pluginSettings = Counter::$plugin->getSettings();

        /** @var Settings $pluginSettings */
        if (!$onlineThreshold) {
            $onlineThreshold = $pluginSettings->onlineThreshold;
        }
        if (!$siteId) {
            $site = Craft::$app->sites->getPrimarySite();
            $siteId = $site->id;
        }

        // Get online users for the site
        if (Craft::$app->getDb()->getIsPgsql()) {
            $where = new \yii\db\Expression('EXTRACT(EPOCH FROM (:now - "dateCreated")) <= :difference', [
                ':now' => $now->format('Y-m-d H:i:s'),
                ':difference' => $onlineThreshold,
            ]);
        } else {
            $where = ['>=', 'dateCreated', new \yii\db\Expression(':now - INTERVAL :difference SECOND', [
                ':now' => $now->format('Y-m-d H:i:s'),
                ':difference' => $onlineThreshold,
            ])];
        }

        $query = VisitorsRecord::find()
            ->where($where)
            ->select('visitor');

        if ($siteId != '*') {
            $query->andWhere(['siteId' => $siteId]);
        }

        $query->andWhere(['skip' => false]);

        $column = $query->column();
        $onlineCounter = (int)count(array_unique($column));

        return $onlineCounter;
    }

    /**
     * Increase the counter
     *
     * @param string $pageUrl
     * @param boolean $viaController
     * @return void
     */
    public function count(string $pageUrl, bool $viaController = false): void
    {
        /** @var Settings $pluginSettings */
        $pluginSettings = Counter::$plugin->getSettings();

        if ($pluginSettings->registerCounter && !$viaController) {
            craft::warning('The plugin is set to count automatically but a direct call was detected');
            return;
        }

        $tz = Craft::$app->getTimeZone();
        $tzTime = new DateTimeZone($tz);
        $now = new DateTime('now', new \DateTimeZone("UTC"));

        // Currently we should keep visitors forever
        // TODO: allow to keep for less seconds?
        if (($pluginSettings->keepVisitorsInSeconds != -1)) {
            craft::warning('The keepVisitorsInSeconds value should be -1 or at least 86400');
            return;
        }

        $request = Craft::$app->getRequest();
        $userIp = $request->getUserIP();
        $userAgent = $request->getUserAgent();

        $userService = Craft::$app->getUser();
        $user = $userService->getIdentity();

        $sitesService = Craft::$app->getSites();
        $currentSite = $sitesService->getCurrentSite();
        $siteId = $currentSite->id;

        $salt = $pluginSettings->salt;
        $anonymizedIp = IpHelper::anonymizeIp($userIp);
        $saltedIp = $salt . ($pluginSettings->anonymizeIp ? $anonymizedIp : $userIp);
        $hashedIP = hash('sha256', $saltedIp);
        $pageUrl = urldecode($pageUrl);
        $unTrimmedPageUrl = $pageUrl;
        if ($pluginSettings->removeAllQueryParams) {
            $pageUrl = CounterUrlHelper::removeAllQueryParams($pageUrl);
        } elseif ($pluginSettings->removeQueryParams) {
            $pageUrl = CounterUrlHelper::removeQueryParams($pageUrl);
        }
        $pageUrl = CounterUrlHelper::trimURL($pageUrl, 2048, 3072);

        // Fire a before event
        $event = new CountEvent([
            'page' => $pageUrl,
            'untrimmedPage' => $unTrimmedPageUrl,
            'siteId' => $siteId,
            'userId' => $user?->id,
            'ip' => $pluginSettings->ipInEvent == true ? $userIp : null,
            'anonymizedIp' => $pluginSettings->anonymizedIpInEvent == true ? $anonymizedIp : null,
            'hashedIp' => $hashedIP,
            'userAgent' => $userAgent,
            'time' => $now,
        ]);
        if ($this->hasEventHandlers(self::EVENT_BEFORE_COUNT)) {
            $this->trigger(self::EVENT_BEFORE_COUNT, $event);

            if (!$event->isValid) {
                return;
            }
        }

        $siteSettings = json_decode($pluginSettings->siteSettings, true);
        $enabledCounter = false;
        $calendarSystem = null;
        $siteUnique = $currentSite->uid;

        if (isset($siteSettings[$siteUnique]['calendar'])) {
            $calendarSystem = $siteSettings[$siteUnique]['calendar'];
        }

        if (isset($siteSettings[$siteUnique]['enabledCounter'])) {
            $enabledCounter = $siteSettings[$siteUnique]['enabledCounter'];
        }

        if (!$enabledCounter) {
            return;
        }

        if ($pluginSettings->ignoreBots) {
            $CrawlerDetect = new CrawlerDetect();
            if ($CrawlerDetect->isCrawler($request->getUserAgent())) {
                craft::warning('a crawler is detected: ' . $request->getUserAgent() . ' from ' . $userIp);
                return;
            }
        }

        if ($user) {
            if ($pluginSettings->ignoreAllUsers) {
                return;
            } else {
                if (is_array($pluginSettings->ignoreUserIds) && in_array($user->id, $pluginSettings->ignoreUserIds)) {
                    return;
                }
                if (is_array($pluginSettings->ignoreGroups)) {
                    foreach ($user->groups as $group) {
                        if (in_array($group->handle, $pluginSettings->ignoreGroups)) {
                            return;
                        }
                    }
                }
            }
        }

        $nowTz = clone $now;
        $nowTz->setTimezone($tzTime);

        $onlineCounter = 0;
        $onlineCounterAllSites = 0;
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

        $yearTz = $nowTz->format('Y');
        $monthTz = $nowTz->format('m');
        $dayTz = $nowTz->format('d');
        $hourTz = $nowTz->format('H');
        $minTz = $nowTz->format('i');
        $quarterTz = 1;
        if ($minTz >= 15 and $minTz < 30) {
            $quarterTz = 2;
        }
        if ($minTz >= 30 and $minTz < 45) {
            $quarterTz = 3;
        }
        if ($minTz >= 45) {
            $quarterTz = 4;
        }

        $ignoreVisit = false;
        $ignoreVisitorInD = false;
        $ignoreVisitorInQ = false;
        // TODO: Delete all old records
        // this query can be limited to the first request of a day
        /*
        if (
            $pluginSettings->keepVisitorsInSeconds != -1
        ) {
            if (Craft::$app->getDb()->getIsPgsql()) {
                $where = new \yii\db\Expression('EXTRACT(EPOCH FROM (:now - "dateCreated")) > :difference', [
                    ':now' => $now->format('Y-m-d H:i:s'),
                    ':difference' => $pluginSettings->keepVisitorsInSeconds,
                ]);
            } else {
                $where = ['<', 'dateCreated', new \yii\db\Expression(':now - INTERVAL :difference SECOND', [
                    ':now' => $now->format('Y-m-d H:i:s'),
                    ':difference' => $pluginSettings->keepVisitorsInSeconds,
                ])];
            }
            VisitorsRecord::deleteAll($where);
        }
        */
        $query = VisitorsRecord::find()
            ->where(['visitor' => $hashedIP, 'siteId' => $siteId])
            ->andWhere(['skip' => false])
            ->orderBy('dateCreated desc');
        $visitorsRecord = $query->one();
        if ($visitorsRecord) {
            /** @var VisitorsRecord $visitorsRecord */
            $dateCreated = $visitorsRecord->dateCreated;
            $dateCreated = new DateTime($dateCreated, new \DateTimeZone("UTC"));
            $dateCreatedTz = clone $dateCreated;
            $dateCreatedTz->setTimezone($tzTime);

            $interval = $now->diff($dateCreated);
            $visitDifference = CounterDateTimeHelper::timeDifference($interval);

            $visitorYearTz = $dateCreatedTz->format('Y');
            $visitorMonthTz = $dateCreatedTz->format('m');
            $visitorDayTz = $dateCreatedTz->format('d');
            $visitorHourTz = $dateCreatedTz->format('H');
            $visitorMinTz = $dateCreatedTz->format('i');
            $visitorQuarterTz = 1;
            if ($visitorMinTz >= 15 and $visitorMinTz < 30) {
                $visitorQuarterTz = 2;
            }
            if ($visitorMinTz >= 30 and $visitorMinTz < 45) {
                $visitorQuarterTz = 3;
            }
            if ($visitorMinTz >= 45) {
                $visitorQuarterTz = 4;
            }

            if ($visitDifference < $pluginSettings->visitsInterval) {
                $ignoreVisit = true;
                $ignoreVisitorInD = true;
                $ignoreVisitorInQ = true;
            } else {
                if ($visitorYearTz == $yearTz && $visitorMonthTz == $monthTz && $visitorDayTz == $dayTz) {
                    $ignoreVisitorInD = true;
                }
                if ($visitorYearTz == $yearTz && $visitorMonthTz == $monthTz && $visitorDayTz == $dayTz && $visitorHourTz == $hourTz && $visitorQuarterTz == $quarterTz) {
                    $ignoreVisitorInQ = true;
                }
            }
        }
        $visitorRecord = null;
        if (!$ignoreVisit) {
            // Get online users for all sites
            if (Craft::$app->getDb()->getIsPgsql()) {
                $where = new \yii\db\Expression('EXTRACT(EPOCH FROM (:now - "dateCreated")) <= :difference', [
                    ':now' => $now->format('Y-m-d H:i:s'),
                    ':difference' => $pluginSettings->onlineThreshold,
                ]);
            } else {
                $where = ['>=', 'dateCreated', new \yii\db\Expression(':now - INTERVAL :difference SECOND', [
                    ':now' => $now->format('Y-m-d H:i:s'),
                    ':difference' => $pluginSettings->onlineThreshold,
                ])];
            }

            $query = VisitorsRecord::find()
                ->where($where)
                ->andWhere(['skip' => false])
                ->select('visitor');
            // use php to get unique for better performance
            $visitors = array_unique($query->column());
            $onlineCounterAllSites = count($visitors);
            if (!in_array($hashedIP, $visitors)) {
                $onlineCounterAllSites++;
            }
            // Get online users for the site
            if (Craft::$app->getDb()->getIsPgsql()) {
                $where = new \yii\db\Expression('EXTRACT(EPOCH FROM (:now - "dateCreated")) <= :difference', [
                    ':now' => $now->format('Y-m-d H:i:s'),
                    ':difference' => $pluginSettings->onlineThreshold,
                ]);
            } else {
                $where = ['>=', 'dateCreated', new \yii\db\Expression(':now - INTERVAL :difference SECOND', [
                    ':now' => $now->format('Y-m-d H:i:s'),
                    ':difference' => $pluginSettings->onlineThreshold,
                ])];
            }

            $query = VisitorsRecord::find()
                ->where($where)
                ->andWhere(['siteId' => $siteId])
                ->andWhere(['skip' => false])
                ->select('visitor');
            $visitors = array_unique($query->column());
            $onlineCounter = count($visitors);

            if (!in_array($hashedIP, $visitors)) {
                $onlineCounter++;
            }

            $visitorRecord = new VisitorsRecord();
            $visitorRecord->visitor = $hashedIP;
            $visitorRecord->siteId = $siteId;
            $visitorRecord->dateCreated = $now;
            $visitorRecord->page = $pageUrl;
            $visitorRecord->skip = false;
            $visitorRecord->save();
        } else {
            $visitorRecord = new VisitorsRecord();
            $visitorRecord->visitor = $hashedIP;
            $visitorRecord->siteId = $siteId;
            $visitorRecord->dateCreated = $now;
            $visitorRecord->page = $pageUrl;
            $visitorRecord->skip = true;
            $visitorRecord->save();
        }

        // Log visitor for all sites
        $counterRecord = CounterRecord::find()->where(['year' => $year, 'month' => $month, 'day' => $day, 'hour' => $hour, 'quarter' => $quarter, 'siteId' => null])->one();
        /** @var CounterRecord|null $counterRecord */
        if ($counterRecord) {
            if ($counterRecord->maxOnline < $onlineCounterAllSites) {
                $counterRecord->maxOnline = $onlineCounterAllSites;
                $counterRecord->maxOnlineDate = $now;
            }
            if (!$ignoreVisit) {
                $counterRecord->visits = new \yii\db\Expression('visits + 1');
            }
            if (!$ignoreVisitorInD) {
                $formattedDate = $now->format('Y-m-d H:i:s');
                $today = DateTimeHelper::today();
                $today->setTimezone(new DateTimeZone('UTC'));
                // check if visitor is new for all sites
                $visitorQuery = VisitorsRecord::find()
                    ->andWhere(['<=', 'dateCreated', $formattedDate])
                    ->andWhere(['>=', 'dateCreated', $today->format('Y-m-d H:i:s')])
                    ->andWhere(['visitor' => $hashedIP])
                    ->andWhere(['skip' => false]);
                if (isset($visitorRecord->id)) {
                    $visitorQuery->andWhere(['!=', 'id', $visitorRecord->id]);
                }
                if (!$visitorQuery->one()) {
                    if (Craft::$app->getDb()->getIsPgsql()) {
                        $counterRecord->newVisitors = new \yii\db\Expression('"newVisitors" + 1');
                    } else {
                        $counterRecord->newVisitors = new \yii\db\Expression('newVisitors + 1');
                    }
                }
            }
            if (!$ignoreVisitorInQ) {
                $formattedDate = $now->format('Y-m-d H:i:s');
                $formattedDate2 = $counterRecord->dateCreated;
                // check if visitor is new for all sites
                $visitorQuery = VisitorsRecord::find()
                    ->andWhere(['<=', 'dateCreated', $formattedDate])
                    ->andWhere(['>=', 'dateCreated', $formattedDate2])
                    ->andWhere(['visitor' => $hashedIP])
                    ->andWhere(['skip' => false]);
                if (isset($visitorRecord->id)) {
                    $visitorQuery->andWhere(['!=', 'id', $visitorRecord->id]);
                }
                if (!$visitorQuery->one()) {
                    $counterRecord->visitors = new \yii\db\Expression('visitors + 1');
                }
            }
            if (Craft::$app->getDb()->getIsPgsql()) {
                $counterRecord->visitsIgnoreInterval = new \yii\db\Expression('"visitsIgnoreInterval" + 1');
            } else {
                $counterRecord->visitsIgnoreInterval = new \yii\db\Expression('visitsIgnoreInterval + 1');
            }
            $counterRecord->dateUpdated = $now;
            $counterRecord->update();
        } else {
            $counterRecord = new CounterRecord();
            $counterRecord->year = (int)$year;
            $counterRecord->month = (int)$month;
            $counterRecord->day = (int)$day;
            $counterRecord->hour = (int)$hour;
            $counterRecord->quarter = $quarter;
            if (!$ignoreVisitorInD) {
                $formattedDate = $now->format('Y-m-d H:i:s');
                $today = DateTimeHelper::today();
                $today->setTimezone(new DateTimeZone('UTC'));
                // check if visitor is new for all sites
                $visitorQuery = VisitorsRecord::find()
                    ->andWhere(['<=', 'dateCreated', $formattedDate])
                    ->andWhere(['>=', 'dateCreated', $today->format('Y-m-d H:i:s')])
                    ->andWhere(['visitor' => $hashedIP])
                    ->andWhere(['skip' => false]);
                if (isset($visitorRecord->id)) {
                    $visitorQuery->andWhere(['!=', 'id', $visitorRecord->id]);
                }
                if (!$visitorQuery->one()) {
                    $counterRecord->newVisitors = 1;
                }
            }
            if (!$ignoreVisitorInQ) {
                $counterRecord->visitors = 1;
            }
            if (!$ignoreVisit) {
                $counterRecord->visits = 1;
                $counterRecord->maxOnline = 1;
                $counterRecord->maxOnlineDate = $now;
            }
            $counterRecord->visitsIgnoreInterval = 1;
            $counterRecord->siteId = null;
            $counterRecord->dateCreated = $now;
            $counterRecord->dateUpdated = $now;
            $counterRecord->save();
        }

        // Log visitor for visited site
        $counterRecord = CounterRecord::find()->where(['year' => $year, 'month' => $month, 'day' => $day, 'hour' => $hour, 'quarter' => $quarter, 'siteId' => $siteId])->one();
        /** @var CounterRecord|null $counterRecord */
        if ($counterRecord) {
            if ($counterRecord->maxOnline < $onlineCounter) {
                $counterRecord->maxOnline = $onlineCounter;
                $counterRecord->maxOnlineDate = $now;
            }
            if (!$ignoreVisit) {
                $counterRecord->visits = new \yii\db\Expression('visits + 1');
            }
            if (!$ignoreVisitorInD) {
                if (Craft::$app->getDb()->getIsPgsql()) {
                    $counterRecord->newVisitors = new \yii\db\Expression('"newVisitors" + 1');
                } else {
                    $counterRecord->newVisitors = new \yii\db\Expression('newVisitors + 1');
                }
            }
            if (!$ignoreVisitorInQ) {
                $counterRecord->visitors = new \yii\db\Expression('visitors + 1');
            }
            if (Craft::$app->getDb()->getIsPgsql()) {
                $counterRecord->visitsIgnoreInterval = new \yii\db\Expression('"visitsIgnoreInterval" + 1');
            } else {
                $counterRecord->visitsIgnoreInterval = new \yii\db\Expression('visitsIgnoreInterval + 1');
            }
            $counterRecord->dateUpdated = $now;
            $counterRecord->update();
        } else {
            $counterRecord = new CounterRecord();
            $counterRecord->year = (int)$year;
            $counterRecord->month = (int)$month;
            $counterRecord->day = (int)$day;
            $counterRecord->hour = (int)$hour;
            $counterRecord->quarter = $quarter;
            if (!$ignoreVisitorInD) {
                $counterRecord->newVisitors = 1;
            }
            if (!$ignoreVisitorInQ) {
                $counterRecord->visitors = 1;
            }
            if (!$ignoreVisit) {
                $counterRecord->visits = 1;
                $counterRecord->maxOnline = 1;
                $counterRecord->maxOnlineDate = $now;
            }
            $counterRecord->visitsIgnoreInterval = 1;
            $counterRecord->siteId = $siteId;
            $counterRecord->dateCreated = $now;
            $counterRecord->dateUpdated = $now;
            $counterRecord->save();
        }

        if ($calendarSystem) {
            // Log Page Visits
            /** @var PageVisitsRecord|null $pageVisitRecord */
            $pageVisitRecord = PageVisitsRecord::find()->where(['page' => $pageUrl, 'siteId' => $siteId])->one();
            if ($pageVisitRecord) {
                $dateUpdated = $pageVisitRecord->lastVisit;

                $dateUpdatedTz = null;
                $dateUpdatedTz1 = null;
                $pageYearIntlTz = null;
                $pageMonthIntlTz = null;
                $pageDayIntlTz = null;

                // If page visit record has a last visit -not a record that is created by a visit within ignore threshold-
                if ($dateUpdated) {
                    $firstVisit = false;
                    $dateUpdatedTz = new DateTime($dateUpdated, new \DateTimeZone("UTC"));
                    $dateUpdatedTz->setTimezone($tzTime);

                    $pageYearIntlTz = CounterDateTimeHelper::intlDate($dateUpdatedTz, $calendarSystem, 'yyyy', 'en_US');
                    $pageMonthIntlTz = CounterDateTimeHelper::intlDate($dateUpdatedTz, $calendarSystem, 'MM', 'en_US');
                    $pageDayIntlTz = CounterDateTimeHelper::intlDate($dateUpdatedTz, $calendarSystem, 'dd', 'en_US');
                } else {
                    $firstVisit = true;
                }

                $yearIntlTz = CounterDateTimeHelper::intlDate($nowTz, $calendarSystem, 'yyyy', 'en_US');
                $monthIntlTz = CounterDateTimeHelper::intlDate($nowTz, $calendarSystem, 'MM', 'en_US');
                $dayIntlTz = CounterDateTimeHelper::intlDate($nowTz, $calendarSystem, 'dd', 'en_US');

                if (Craft::$app->getDb()->getIsPgsql()) {
                    $pageVisitRecord->allTimeIgnoreInterval = new \yii\db\Expression('"allTimeIgnoreInterval" + 1');
                } else {
                    $pageVisitRecord->allTimeIgnoreInterval = new \yii\db\Expression('allTimeIgnoreInterval + 1');
                }

                if (!$ignoreVisit) {
                    if (Craft::$app->getDb()->getIsPgsql()) {
                        $pageVisitRecord->allTime = new \yii\db\Expression('"allTime"+1');
                    } else {
                        $pageVisitRecord->allTime = new \yii\db\Expression('allTime+1');
                    }
                }

                // are we in same year as page record?
                if ($firstVisit || $yearIntlTz == $pageYearIntlTz) {
                    if (!$ignoreVisit) {
                        if (Craft::$app->getDb()->getIsPgsql()) {
                            $pageVisitRecord->thisYear = new \yii\db\Expression('"thisYear" + 1');
                        } else {
                            $pageVisitRecord->thisYear = new \yii\db\Expression('thisYear + 1');
                        }
                    }
                } else {
                    // are we in next year of page record?
                    $calendar = IntlCalendar::createInstance();
                    $calendar->set(
                        (int)$dateUpdatedTz->format('Y'),
                        $dateUpdatedTz->format('n') - 1,
                        (int)$dateUpdatedTz->format('j'),
                    );
                    $calendar->roll(IntlCalendar::FIELD_YEAR, 1);
                    $calendar->set(IntlCalendar::FIELD_MONTH, 1);
                    $calendar->set(IntlCalendar::FIELD_DAY_OF_MONTH, 1);
                    $formatter = new IntlDateFormatter('en_us@calendar=' . $calendarSystem, \IntlDateFormatter::FULL, \IntlDateFormatter::FULL, $tz, \IntlDateFormatter::TRADITIONAL, 'yyyy');
                    $nextYear = $formatter->format($calendar);
                    $yearNow = CounterDateTimeHelper::intlDate($nowTz, $calendarSystem, 'yyyy');
                    if ($nextYear === $yearNow) {
                        $pageVisitRecord->previousYear = $pageVisitRecord->thisYear;
                    } else {
                        $pageVisitRecord->previousYear = 0;
                    }

                    if (!$ignoreVisit) {
                        $pageVisitRecord->thisYear = 1;
                    }
                }

                // are we in same month as page record?
                if ($firstVisit || ($yearIntlTz == $pageYearIntlTz && $monthIntlTz == $pageMonthIntlTz)) {
                    if (!$ignoreVisit) {
                        if (Craft::$app->getDb()->getIsPgsql()) {
                            $pageVisitRecord->thisMonth = new \yii\db\Expression('"thisMonth" + 1');
                        } else {
                            $pageVisitRecord->thisMonth = new \yii\db\Expression('thisMonth + 1');
                        }
                    }
                } else {
                    // are we in next month of page record?
                    $calendar = IntlCalendar::createInstance();
                    $calendar->set(
                        (int)$dateUpdatedTz->format('Y'),
                        $dateUpdatedTz->format('n') - 1,
                        (int)$dateUpdatedTz->format('j'),
                    );
                    $calendar->add(IntlCalendar::FIELD_MONTH, 1);
                    $calendar->set(IntlCalendar::FIELD_DAY_OF_MONTH, 1);
                    $formatter = new IntlDateFormatter('en_us@calendar=' . $calendarSystem, \IntlDateFormatter::FULL, \IntlDateFormatter::FULL, $tz, \IntlDateFormatter::TRADITIONAL, 'yyyy/MM');
                    $nextMonth = $formatter->format($calendar);

                    $monthNow = CounterDateTimeHelper::intlDate($nowTz, $calendarSystem, 'yyyy/MM');

                    if ($nextMonth === $monthNow) {
                        $pageVisitRecord->previousMonth = $pageVisitRecord->thisMonth;
                    } else {
                        $pageVisitRecord->previousMonth = 0;
                    }
                    if (!$ignoreVisit) {
                        $pageVisitRecord->thisMonth = 1;
                    }
                }

                // are we in same day as page record?
                if ($firstVisit || ($yearIntlTz == $pageYearIntlTz && $monthIntlTz == $pageMonthIntlTz && $dayIntlTz == $pageDayIntlTz)) {
                    if (!$ignoreVisit) {
                        $pageVisitRecord->today = new \yii\db\Expression('today + 1');
                    }
                } else {
                    $dateUpdatedTz1 = clone $dateUpdatedTz;
                    $dateUpdatedTz1->modify('+1 day');
                    if ($dateUpdatedTz1->format('Y-m-d') === $nowTz->format('Y-m-d')) {
                        $pageVisitRecord->yesterday = $pageVisitRecord->today;
                    } else {
                        $pageVisitRecord->yesterday = 0;
                    }

                    if (!$ignoreVisit) {
                        $pageVisitRecord->today = 1;
                    }
                }

                $nowTz1 = clone $nowTz;
                if ($dateUpdatedTz) {
                    $dateUpdatedTz1 = clone $dateUpdatedTz;
                }
                // Is data for this week visits is updated?
                if ($firstVisit || CounterDateTimeHelper::lastWeek($siteId, $dateUpdatedTz1, $tzTime) == CounterDateTimeHelper::lastWeek($siteId, $nowTz1, $tzTime)) {
                    if (!$ignoreVisit) {
                        if (Craft::$app->getDb()->getIsPgsql()) {
                            $pageVisitRecord->thisWeek = new \yii\db\Expression('"thisWeek" + 1');
                        } else {
                            $pageVisitRecord->thisWeek = new \yii\db\Expression('thisWeek + 1');
                        }
                    }
                } else {
                    $nowTz1 = clone $nowTz;
                    $dateUpdatedTz1 = clone $dateUpdatedTz;

                    if ($dateUpdatedTz1 < CounterDateTimeHelper::lastWeek($siteId, $nowTz1, $tzTime)) {
                        $pageVisitRecord->previousWeek = 0;
                    } else {
                        $pageVisitRecord->previousWeek = $pageVisitRecord->thisWeek;
                    }

                    if (!$ignoreVisit) {
                        $pageVisitRecord->thisWeek = 1;
                    }
                }

                if (!$ignoreVisit) {
                    $pageVisitRecord->lastVisit = $now;
                }
    
                $pageVisitRecord->dateUpdated = $now;
                $pageVisitRecord->update();
            } else {
                $pageVisitRecord = new PageVisitsRecord();
                $pageVisitRecord->allTimeIgnoreInterval = 1;
                if (!$ignoreVisit) {
                    $pageVisitRecord->allTime = 1;
                    $pageVisitRecord->thisYear = 1;
                    $pageVisitRecord->thisMonth = 1;
                    $pageVisitRecord->thisWeek = 1;
                    $pageVisitRecord->today = 1;
                    $pageVisitRecord->lastVisit = $now;
                }
                $pageVisitRecord->page = $pageUrl;
                $pageVisitRecord->siteId = $siteId;
                $pageVisitRecord->dateCreated = $now;
                $pageVisitRecord->dateUpdated = $now;
                $pageVisitRecord->save();
            }
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_COUNT)) {
            $this->trigger(self::EVENT_AFTER_COUNT, $event);
        }
    }
}
