<?php

namespace vnali\counter\base;

use DateTime;

interface DateInterface
{
    public const DATE_RANGE_ALL = 'all';
    public const DATE_RANGE_THISHOUR = 'thisHour';
    public const DATE_RANGE_PREVIOUSHOUR = 'previousHour';
    public const DATE_RANGE_TODAY = 'today';
    public const DATE_RANGE_YESTERDAY = 'yesterday';
    public const DATE_RANGE_THISWEEK = 'thisWeek';
    public const DATE_RANGE_THISMONTH = 'thisMonth';
    public const DATE_RANGE_THISYEAR = 'thisYear';
    public const DATE_RANGE_PAST7DAYS = 'past7Days';
    public const DATE_RANGE_PAST30DAYS = 'past30Days';
    public const DATE_RANGE_PAST90DAYS = 'past90Days';
    public const DATE_RANGE_PASTYEAR = 'pastYear';
    public const DATE_RANGE_CUSTOM = 'custom';

    public const START_DAY_INT_TO_DAY = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    public const START_DAY_INT_TO_END_DAY = [
        0 => 'Saturday',
        1 => 'Sunday',
        2 => 'Monday',
        3 => 'Tuesday',
        4 => 'Wednesday',
        5 => 'Thursday',
        6 => 'Friday',
    ];

    /**
     * @return mixed
     */
    public function get(): mixed;

    /**
     * @return mixed
     */
    public function getData(): mixed;

    /**
     * @return mixed
     */
    public function getStartDate(): mixed;

    /**
     * @return mixed
     */
    public function getEndDate(): mixed;

    public function setStartDate(?DateTime $date): void;

    public function setEndDate(?DateTime $date): void;

    /**
     * @param $data
     * @return mixed
     */
    public function prepareData($data): mixed;

    public function getDateRangeWording(): string;
}
