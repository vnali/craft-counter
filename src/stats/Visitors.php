<?php

namespace vnali\counter\stats;

use Craft;
use craft\db\Query;
use craft\helpers\Db;
use DateTime;
use DateTimeZone;
use vnali\counter\base\Date;
use vnali\counter\helpers\DateTimeHelper as CounterDateTimeHelper;

class Visitors extends Date
{
    public ?string $siteId;

    public ?string $visitorType;

    public $preferredInterval;

    public ?bool $showChart = false;

    /**
     * @inheritDoc
     */
    public function __construct(string $dateRange = null, DateTime|false|null $startDate = null, DateTime|false|null $endDate = null, ?string $calendar = 'gregorian', ?string $siteId = null, ?string $visitorType = 'new', ?string $preferredInterval = null, ?bool $showChart = false)
    {
        $this->siteId = $siteId;
        $this->preferredInterval = $preferredInterval;
        $this->visitorType = $visitorType;
        $this->showChart = $showChart;

        parent::__construct($dateRange, $startDate, $endDate, $calendar);
    }

    /**
     * @inheritDoc
     */
    public function getData(): string|int|bool|array|null
    {
        $labels = [];
        $visitors = [];
        list($query, $startDate, $endDate) = $this->_baseData();

        // Validate t if invalid data is passed -from twig, ...-
        if (!in_array($this->dateRange, ['today', 'yesterday', 'thisHour', 'previousHour', 'custom'])) {
            return null;
        }

        if ($this->dateRange == 'custom') {
            if ($startDate->format('Y-m-d') != $endDate->format('Y-m-d')) {
                return null;
            }
            if (!$startDate || !$endDate) {
                return null;
            }
        }

        $sum = false;
        $sumVisitors = 0;
        if ($this->showChart || $this->siteId == '*') {
            $interval = CounterDateTimeHelper::Interval($this->dateRange, $startDate, $endDate, $this->preferredInterval);

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
            $visitorType = ($this->visitorType == 'new') ? 'newVisitors' : 'visitors';

            foreach ($rows as $key => $row) {
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

                // We only keep track of visitors day-based
                if ($this->dateRange == 'today' || $this->dateRange == 'yesterday' || $this->dateRange == 'custom') {
                    $sum = true;
                    $sumVisitors = $sumVisitors + $row['newVisitors'];
                }

                if (!isset($data[$dateKey])) {
                    $visitors = 0;
                    $data[$dateKey]['dateCreated'] = $datetime;
                } else {
                    $visitors = $data[$dateKey]['visitors'];
                }

                if ($visitorType == 'newVisitors') {
                    $data[$dateKey]['visitors'] = $visitors + $row['newVisitors'];
                } else {
                    if ($this->preferredInterval == 'hourly') {
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
                            $data[$dateKey]['visitors'] = (int)count(array_unique($column));
                        }
                    } elseif ($this->preferredInterval == '30minutes') {
                        if (!isset($data[$dateKey]['visitors'])) {
                            $dateKeyParts = explode(':', $dateKey);
                            if ($dateKeyParts[1] == '0') {
                                $min1 = 0;
                                $min2 = 29;
                            } else {
                                $min1 = 30;
                                $min2 = 59;
                            }
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
                            $column = $query->column();
                            $data[$dateKey]['visitors'] = (int) count(array_unique($column));
                        }
                    } elseif ($this->preferredInterval == '15minutes') {
                        $data[$dateKey]['visitors'] = $visitors + $row['visitors'];
                    }
                }
            }

            uksort($data, function($key1, $key2) use ($data) {
                return $data[$key1]['dateCreated'] <=> $data[$key2]['dateCreated'];
            });

            $data = CounterDateTimeHelper::fillTimes($data, ['visitors', 'dateCreated'], $interval, $startDate, $endDate, $this->dateRange, null, $this->preferredInterval);

            $collection = collect($data);
            $visitors = $collection->pluck('visitors');
            $visitors = $visitors->toArray();

            $labels = array_keys($data);
        }

        if (!$sum) {
            $query = (new Query())
                ->from('{{%counter_visitors}}' . ' visitors')
                ->andWhere(['>=', 'dateCreated',  Db::prepareDateForDb($startDate)])
                ->andWhere(['<=', 'dateCreated', Db::prepareDateForDb($endDate)])
                ->andWhere(['skip' => false]);
            $query->select(['visitor']);

            if ($this->siteId && $this->siteId != '*') {
                $query->andWhere(['siteId' => $this->siteId]);
            }

            $sumVisitors = $query->column();
            $sumVisitors = count(array_unique($sumVisitors));
        }

        return [$sumVisitors, json_encode($labels), json_encode(array_values($visitors))];
    }
}
