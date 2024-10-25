<?php
/**
 * @copyright Copyright (c) vnali
 */

namespace vnali\counter\twig;

use Craft;
use vnali\counter\Counter;

class CounterVariable
{
    /**
     * Returns page visits
     *
     * @param string $page
     * @param string|null $siteId
     * @param array|null $dateRanges
     * @return array
     */
    public function pageVisits(string $page, ?string $siteId = null, ?array $dateRanges = []): array
    {
        return Counter::$plugin->pages->visits($page, $siteId, $dateRanges);
    }

    /**
     * Returns visits
     *
     * @param string $dateRange
     * @param string|null $startDate
     * @param string|null $endDate
     * @param string|null $siteId
     * @param bool|null $ignoreVisitsInterval
     * @param string|null $calendar
     * @return int
     */
    public function siteVisits(string $dateRange, ?string $startDate = null, ?string $endDate = null, ?string $siteId = null, ?bool $ignoreVisitsInterval = false, ?string $calendar = null): int
    {
        if (!$siteId) {
            $site = Craft::$app->sites->getPrimarySite();
            $siteId = (string)$site->id;
        }

        if (!$calendar) {
            $calendar = 'gregorian';
        }

        return Counter::$plugin->counter->visits($dateRange, $startDate, $endDate, $siteId, $ignoreVisitsInterval, $calendar);
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
    public function siteVisitors(string $dateRange, ?string $startDate = null, ?string $endDate = null, ?string $siteId = null, ?string $calendar = null): ?int
    {
        if (!$siteId) {
            $site = Craft::$app->sites->getPrimarySite();
            $siteId = (string)$site->id;
        }

        if (!$calendar) {
            $calendar = 'gregorian';
        }

        return Counter::$plugin->counter->visitors($dateRange, $startDate, $endDate, $siteId, $calendar);
    }

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
    public function maxOnline(string $dateRange, ?string $startDate = null, ?string $endDate = null, ?string $siteId = null, ?string $calendar = null): array
    {
        if (!$siteId) {
            $site = Craft::$app->sites->getPrimarySite();
            $siteId = (string)$site->id;
        }

        if (!$calendar) {
            $calendar = 'gregorian';
        }

        return Counter::$plugin->counter->maxOnline($dateRange, $startDate, $endDate, $siteId, $calendar);
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
        return Counter::$plugin->counter->onlineVisitors($siteId, $onlineThreshold);
    }

    /**
     * Increase the counter
     *
     * @param string $pageUrl
     * @return void
     */
    public function count(string $pageUrl): void
    {
        Counter::$plugin->counter->count($pageUrl, false);
    }

    /**
     * Returns top pages
     *
     * @param string|null $dateRange
     * @param string|null $siteId
     * @param int|null $limit
     * @return array|null
     */
    public function topPages(?string $dateRange, ?string $siteId = null, ?int $limit = null): ?array
    {
        return Counter::$plugin->pages->top($dateRange, $siteId, $limit);
    }

    /**
     * Return trending pages
     *
     * @param string $dateRange
     * @param string|null $siteId
     * @param string|null $growthType
     * @param bool|null $ignoreNewPages
     * @param int|null $limit
     * @return array|null
     */
    public function trendingPages(string $dateRange, ?string $siteId = null, ?string $growthType = null, ?bool $ignoreNewPages = null, ?int $limit = null): ?array
    {
        return Counter::$plugin->pages->trending($dateRange, $siteId, $growthType, $ignoreNewPages, $limit);
    }
}
