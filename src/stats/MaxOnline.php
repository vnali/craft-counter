<?php

namespace vnali\counter\stats;

use Craft;
use craft\db\Query;
use craft\helpers\Db;
use DateTime;
use DateTimeZone;
use vnali\counter\base\Date;
use vnali\counter\helpers\DateTimeHelper as CounterDateTimeHelper;

class MaxOnline extends Date
{
    public ?string $siteId;

    public $calendar;

    public ?bool $showChart = false;

    public ?bool $showVisitor = false;

    /**
     * @inheritDoc
     */
    public function __construct(string $dateRange = null, DateTime|false|null $startDate = null, DateTime|false|null $endDate = null, ?string $calendar = 'gregorian', ?string $siteId = null,  ?bool $showChart = false, ?bool $showVisitor = false)
    {
        $this->siteId = $siteId;

        $this->showChart = $showChart;

        $this->showVisitor = $showVisitor;

        parent::__construct($dateRange, $startDate, $endDate, $calendar);
    }


    /**
     * @inheritDoc
     */
    public function getData(): array|null
    {
        $labels = [];
        $visitors = [];
        $showVisitorOnChart = false;
        $maxOnline = 0;
        $maxOnlineDate = null;
        $maxOnlineArray = [];

        list($query, $startDate, $endDate) = $this->_baseData();
        $interval = CounterDateTimeHelper::Interval($this->dateRange, $startDate, $endDate, 'hourly');

        if ($this->siteId == '*') {
            $query->andWhere('siteId is null');
        } else {
            $query->andWhere(['siteId' => $this->siteId]);
        }

        $query->select(["*"]);
        $rows = $query->orderBy('dateCreated asc')->all();

        $data = [];
        $tz = Craft::$app->getTimeZone();
        $tzTime = new DateTimeZone($tz);

        foreach ($rows as $row) {
            $dateCreated = new DateTime($row['dateCreated'], new DateTimeZone('UTC'));
            $dateCreated->setTimezone($tzTime);

            $dateKey = CounterDateTimeHelper::intlDate($dateCreated, $this->calendar, CounterDateTimeHelper::intlFormat($interval));

            if ($this->showVisitor) {
                if (!isset($data[$dateKey]['visitors'])) {
                    $visitors = 0;
                    $data[$dateKey]['dateCreated'] = $dateCreated;
                } else {
                    $visitors = $data[$dateKey]['visitors'];
                }

                $data[$dateKey]['visitors'] = $visitors + $row['newVisitors'];
            /*
            if ($interval == 'H') {
                if (!isset($data[$dateKey]['visitors'])) {
                    $date1 = clone $startDate;
                    $date1->setTime((int) $dateKey, 0, 0);
                    $date2 = clone $startDate;
                    $date2->setTime((int) $dateKey, 59, 59);
                    $query = (new Query())
                        ->from('{{%counter_visitors}}' . ' visitors')
                        ->andWhere(['>=', 'dateCreated',  Db::prepareDateForDb($date1)])
                        ->andWhere(['<=', 'dateCreated', Db::prepareDateForDb($date2)])
                        ->andWhere(['skip' => false]);
                    $query->select(['visitor']);

                    if ($this->siteId && $this->siteId != '*') {
                        $query->andWhere(['siteId' => $this->siteId]);
                    }
                    $column = $query->column();
                    $count = (int)count(array_unique($column));
                    $data[$dateKey]['visitors'] = (int) $count;
                }
            } else {
                $data[$dateKey]['visitors'] = $visitors + $row['newVisitors'];
            }
            */
            } else {
                // we do not need visitors data but set a null value for later process
                $data[$dateKey]['visitors'] = 0;
                $data[$dateKey]['dateCreated'] = $dateCreated;
            }

            // maxOnline is max online in whole range
            if ($row['maxOnline'] >= $maxOnline) {
                $maxOnline = $row['maxOnline'];
                $maxOnlineDate = $row['maxOnlineDate'];
            }

            // maxOnlineKeyDate keeps max online in each key date
            if (!isset($data[$dateKey]['maxOnline'])) {
                $maxOnlineKeyDate = 0;
            } else {
                $maxOnlineKeyDate = $data[$dateKey]['maxOnline'];
            }
            if ($row['maxOnline'] >= $maxOnlineKeyDate) {
                $data[$dateKey]['maxOnline'] = $row['maxOnline'];
                $data[$dateKey]['maxOnlineDate'] = $row['maxOnlineDate'];
            }
        }

        // Sort based dateCreated value
        uksort($data, function($key1, $key2) use ($data) {
            return $data[$key1]['dateCreated'] <=> $data[$key2]['dateCreated'];
        });

        // Fill times with no value
        $data = CounterDateTimeHelper::fillTimes($data, ['visitors', 'maxOnline', 'maxOnlineDate', 'dateCreated'], $interval, $startDate, $endDate, $this->dateRange, $this->calendar);
        // visitors only works for half an hour, hour and day based range
        if ($interval == 'H' || $interval == 'H:i' || $interval == 'Y-m-d') {
            $showVisitorOnChart = true;
        }

        $labels = array_keys($data);

        $collection = collect($data);
        $visitors = $collection->pluck('visitors');
        $visitors = $visitors->toArray();

        $maxOnlineArray = array_map(function($item, $label) use ($interval) {
            $date = null;
            if ($item['maxOnlineDate']) {
                $date = new DateTime($item['maxOnlineDate'], new DateTimeZone('UTC'));
                $tz = Craft::$app->getTimeZone();
                $tzTime = new DateTimeZone($tz);
                $date->setTimezone($tzTime);
            }
            // If day is not on x-axis, mentioned it on tooltip
            $maxDate = null;
            if ($date) {
                if ($interval == 'Y' || $interval == 'Y-m') {
                    $maxDate = $date->format('Y-m-d H:i:s');
                } else {
                    $maxDate = $date->format('H:i:s');
                }
            }

            return [
                'x' => $label,
                'y' => (int)$item['maxOnline'],
                'time' => $maxDate,
            ];
        }, $data, $labels);

        // Max online date in selected calendar system
        if ($maxOnlineDate) {
            $maxOnlineDate = new DateTime($maxOnlineDate, new DateTimeZone('UTC'));
            $maxOnlineDate->setTimezone($tzTime);
            if ($this->calendar != 'gregorian') {
                $maxOnlineDate = CounterDateTimeHelper::intlDate($maxOnlineDate, $this->calendar, 'EEEE, yyyy-MM-dd HH:mm:ss');
            } else {
                $maxOnlineDate = $maxOnlineDate->format('l, Y-m-d H:i:s');
            }
        } else {
            $maxOnlineDate = null;
        }

        return [$maxOnline, $maxOnlineDate, json_encode($labels), json_encode($maxOnlineArray), json_encode(array_values($visitors)), $showVisitorOnChart];
    }
}
