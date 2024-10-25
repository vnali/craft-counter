<?php

namespace vnali\counter\stats;

use Craft;
use craft\db\Query;
use craft\helpers\Db;
use DateTime;
use DateTimeZone;
use vnali\counter\base\Date;
use vnali\counter\helpers\DateTimeHelper as CounterDateTimeHelper;

class Visits extends Date
{
    public $ignoreVisitsInterval;
    public $preferredInterval;
    public ?string $siteId;
    public ?bool $showChart = false;
    public ?bool $showVisitor = false;

    /**
     * @inheritDoc
     */
    public function __construct(string $dateRange = null, DateTime|false|null $startDate = null, DateTime|false|null $endDate = null, ?string $calendar = 'gregorian', ?string $siteId = null, ?bool $ignoreVisitsInterval = false, ?string $preferredInterval = null, ?bool $showChart = false, ?bool $showVisitor = false)
    {
        $this->siteId = $siteId;
        $this->ignoreVisitsInterval = $ignoreVisitsInterval;
        $this->preferredInterval = $preferredInterval;
        $this->showChart = $showChart;
        $this->showVisitor = $showVisitor;
        parent::__construct($dateRange, $startDate, $endDate, $calendar);
    }

    /**
     * @inheritDoc
     */
    public function getData(): string|int|bool|array|null
    {
        $labels = [];
        $visitors = [];
        $visits = [];
        $showVisitorOnChart = false;

        list($query, $startDate, $endDate) = $this->_baseData();

        $interval = CounterDateTimeHelper::Interval($this->dateRange, $startDate, $endDate, $this->preferredInterval);

        if ($this->siteId == '*') {
            $query->andWhere('siteId is null');
        } else {
            $query->andWhere(['siteId' => $this->siteId]);
        }

        $query->select(["*"]);
        $rows = $query->orderBy('dateCreated asc')->all();

        $data = [];
        $sumVisits = 0;
        $tz = Craft::$app->getTimeZone();
        $tzTime = new DateTimeZone($tz);
        foreach ($rows as $row) {
            $sumVisits = $sumVisits + ($this->ignoreVisitsInterval ? $row['visitsIgnoreInterval'] : $row['visits']);

            if ($this->showChart) {
                $datetime = new DateTime($row['dateCreated'], new DateTimeZone('UTC'));
                $datetime->setTimezone($tzTime);

                $dateKey = CounterDateTimeHelper::intlDate($datetime, $this->calendar, CounterDateTimeHelper::intlFormat($interval));
                if (($this->dateRange == 'today' || $this->dateRange == 'yesterday' || $this->dateRange == 'thisHour' || $this->dateRange == 'previousHour') && $this->preferredInterval != 'hourly') {
                    $dateKeyParts = explode(':', $dateKey);
                    $hour = $dateKeyParts[0];
                    $minute = $dateKeyParts[1];
                    if ($this->preferredInterval == '15minutes') {
                        if ($minute < 15) {
                            $dateKey = $hour . ':00';
                        } elseif ($minute >= 15 && $minute < 30) {
                            $dateKey = $hour . ':15';
                        } elseif ($minute >= 30 && $minute < 45) {
                            $dateKey = $hour . ':30';
                        } elseif ($minute >= 45) {
                            $dateKey = $hour . ':45';
                        }
                    } elseif ($this->preferredInterval == '30minutes') {
                        if ($minute < 30) {
                            $dateKey = $hour . ':00';
                        } else {
                            $dateKey = $hour . ':30';
                        }
                    }
                }

                if (!isset($data[$dateKey]['visits'])) {
                    $visits = 0;
                } else {
                    $visits = $data[$dateKey]['visits'];
                }

                $data[$dateKey]['visits'] = $visits + ($this->ignoreVisitsInterval ? $row['visitsIgnoreInterval'] : $row['visits']);

                if ($this->showVisitor) {
                    if (!isset($data[$dateKey]['visitors'])) {
                        $visitors = 0;
                        $data[$dateKey]['dateCreated'] = $datetime;
                    } else {
                        $visitors = $data[$dateKey]['visitors'];
                    }
                    if (($this->dateRange == 'today' && $this->preferredInterval == 'hourly')) {
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
                            $count = count(array_unique($query->column()));
                            $data[$dateKey]['visitors'] = (int) $count;
                        }
                    } elseif ($this->dateRange == 'today' && $this->preferredInterval == '30minutes') {
                        $dateKeyParts = explode(':', $dateKey);
                        if ($dateKeyParts[1] == '0') {
                            $min1 = 0;
                            $min2 = 29;
                        } else {
                            $min1 = 30;
                            $min2 = 59;
                        }
                        if (!isset($data[$dateKey]['visitors'])) {
                            $date1 = clone $startDate;
                            $date1->setTime((int) $dateKey, $min1, 0);
                            $date2 = clone $startDate;
                            $date2->setTime((int) $dateKey, $min2, 59);
                            $query = (new Query())
                                ->from('{{%counter_visitors}}' . ' visitors')
                                ->andWhere(['>=', 'dateCreated',  Db::prepareDateForDb($date1)])
                                ->andWhere(['<=', 'dateCreated', Db::prepareDateForDb($date2)])
                                ->andWhere(['skip' => false]);
                            $query->select(['visitor']);

                            if ($this->siteId && $this->siteId != '*') {
                                $query->andWhere(['siteId' => $this->siteId]);
                            }
                            $count = (int)count(array_unique($query->column()));
                            $data[$dateKey]['visitors'] = (int) $count;
                        }
                    } elseif ($this->dateRange == 'today' && $this->preferredInterval == '15minutes') {
                        $data[$dateKey]['visitors'] = $row['visitors'];
                    } else {
                        // This is daily (probably custom, this week, ...), we can sum new visitors to get total visitors in a day
                        $data[$dateKey]['visitors'] = $visitors + $row['newVisitors'];
                    }
                } else {
                    // we do not need visitors data but set a 0 value for later process
                    $data[$dateKey]['visitors'] = 0;
                    $data[$dateKey]['dateCreated'] = $datetime;
                }
            }
        }

        if ($this->showChart) {
            uksort($data, function($key1, $key2) use ($data) {
                return $data[$key1]['dateCreated'] <=> $data[$key2]['dateCreated'];
            });

            $data = CounterDateTimeHelper::fillTimes($data, ['visits', 'visitors', 'dateCreated'], $interval, $startDate, $endDate, $this->dateRange, $this->calendar, $this->preferredInterval);
            
            if ($interval == 'H' || $interval == 'H:i' || $interval == 'Y-m-d') {
                $showVisitorOnChart = true;
            }

            if ($this->showVisitor) {
                $collection = collect($data);
                $visitors = $collection->pluck('visitors');
                $visitors = $visitors->toArray();
            }

            $collection = collect($data);
            $visits = $collection->pluck('visits');
            $visits = $visits->toArray();

            $labels = array_keys($data);
        }

        return [$sumVisits, json_encode($labels), json_encode(array_values($visits)), json_encode(array_values($visitors)), $showVisitorOnChart];
    }
}
