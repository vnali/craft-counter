<?php

namespace vnali\counter\stats;

use Craft;
use DateTime;
use DateTimeZone;
use vnali\counter\base\Date;
use vnali\counter\helpers\DateTimeHelper as CounterDateTimeHelper;

class AverageVisitors extends Date
{
    public ?string $siteId;

    /**
     * @inheritDoc
     */
    public function __construct(string $dateRange = null, DateTime|false|null $startDate = null, DateTime|false|null $endDate = null, ?string $calendar = 'gregorian', ?string $siteId = null)
    {
        $this->siteId = $siteId;

        parent::__construct($dateRange, $startDate, $endDate, $calendar);
    }

    /**
     * @inheritDoc
     */
    public function getData(): string|int|bool|array|null
    {
        list($query, $startDate, $endDate) = $this->_baseData();

        // The only possible interval is daily
        $interval = 'Y-m-d';

        if ($this->siteId == '*') {
            $query->andWhere('siteId is null');
        } else {
            $query->andWhere(['siteId' => $this->siteId]);
        }

        $query->select(["*"]);

        $rows = $query->orderBy('dateCreated asc')->all();

        $data = [];
        $sumVisitors = 0;
        $tz = Craft::$app->getTimeZone();
        $tzTime = new DateTimeZone($tz);

        foreach ($rows as $row) {
            $datetime = new DateTime($row['dateCreated'], new DateTimeZone('UTC'));
            $datetime->setTimezone($tzTime);

            $dateKey = CounterDateTimeHelper::intlDate($datetime, $this->calendar, 'yyyy-MM-dd');

            // Currently we use newVisitors to calculate daily visitors. why we decide to keep visitors forever, we should switch to query visitors table
            // newVisitors has some problems with changing site timezone
            $sumVisitors = $sumVisitors + $row['newVisitors'];

            if (!isset($data[$dateKey])) {
                $visitors = 0;
                $data[$dateKey]['dateCreated'] = $datetime;
            } else {
                $visitors = $data[$dateKey]['visitors'];
            }

            $data[$dateKey]['visitors'] = $visitors + $row['newVisitors'];
        }

        uksort($data, function($key1, $key2) use ($data) {
            return $data[$key1]['dateCreated'] <=> $data[$key2]['dateCreated'];
        });

        $data = CounterDateTimeHelper::fillTimes($data, ['visitors', 'dateCreated'], $interval, $startDate, $endDate, $this->dateRange, $this->calendar);

        $collection = collect($data);
        $visitors = $collection->pluck('visitors');
        $visitors = $visitors->toArray();

        $labels = array_keys($data);

        if (count($labels) == 0) {
            $averageVisitors = null;
        } else {
            $averageVisitors = round($sumVisitors / count($labels));
        }

        return [$averageVisitors, json_encode($labels), json_encode(array_values($visitors))];
    }
}
