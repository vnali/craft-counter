<?php

namespace vnali\counter\base;

use Craft;
use craft\db\Query;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\i18n\Locale;
use DateInterval;
use DateTime;
use DateTimeZone;
use IntlCalendar;
use vnali\counter\records\CounterRecord;
use yii\base\Exception;

abstract class Date implements DateInterface
{
    use DateTrait;

    public $calendar;

    public $locale;

    public function __construct(string $dateRange = null, mixed $startDate = null, mixed $endDate = null, ?string $calendar = 'gregorian')
    {
        $user = Craft::$app->getUser()->getIdentity();
        if ($user) {
            $this->weekStartDay = $user->getPreference('weekStartDay') ?? $this->weekStartDay;
            $this->locale = $user->getPreference('locale') ?? $user->getPreference('language') ?? 'en_US';
        }

        $this->calendar = $calendar;
        $this->dateRange = $dateRange ?? $this->dateRange;

        if ($this->dateRange && $this->dateRange != self::DATE_RANGE_CUSTOM) {
            $this->_setDates();
        } else {
            $this->setStartDate($startDate);
            $this->setEndDate($endDate);
        }
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function get(): mixed
    {
        $this->_setDates();
        $data = $this->getData();
        return $this->prepareData($data);
    }

    /**
     * @inheritdoc
     */
    public function prepareData($data): mixed
    {
        return $data;
    }

    /**
     * @inheritdoc
     */
    public function setStartDate(?DateTime $date): void
    {
        if (!$date) {
            $this->_startDate = new DateTime();
        } else {
            $this->_startDate = $date;
        }
    }

    /**
     * @inheritdoc
     */
    public function setEndDate(?DateTime $date): void
    {
        if (!$date) {
            $this->_endDate = new DateTime();
        } else {
            $this->_endDate = $date;
        }
    }

    /**
     * @inheritdoc
     */
    public function getStartDate(): mixed
    {
        return $this->_startDate;
    }

    /**
     * @inheritdoc
     */
    public function getEndDate(): mixed
    {
        return $this->_endDate;
    }

    /**
     * @inheritdoc
     */
    public function getDateRangeWording(): string
    {
        switch ($this->dateRange) {
            case self::DATE_RANGE_THISHOUR: {
                    return Craft::t('counter', 'This hour');
                }
            case self::DATE_RANGE_PREVIOUSHOUR: {
                    return Craft::t('counter', 'Previous hour');
                }
            case self::DATE_RANGE_YESTERDAY: {
                    return Craft::t('counter', 'Yesterday');
                }
            case self::DATE_RANGE_ALL: {
                    return Craft::t('counter', 'All');
                }
            case self::DATE_RANGE_TODAY: {
                    return Craft::t('counter', 'Today');
                }
            case self::DATE_RANGE_THISWEEK: {
                    return Craft::t('counter', 'This week');
                }
            case self::DATE_RANGE_THISMONTH: {
                    return Craft::t('counter', 'This month');
                }
            case self::DATE_RANGE_THISYEAR: {
                    return Craft::t('counter', 'This year');
                }
            case self::DATE_RANGE_PAST7DAYS: {
                    return Craft::t('counter', 'Past {num} days', ['num' => 7]);
                }
            case self::DATE_RANGE_PAST30DAYS: {
                    return Craft::t('counter', 'Past {num} days', ['num' => 30]);
                }
            case self::DATE_RANGE_PAST90DAYS: {
                    return Craft::t('counter', 'Past {num} days', ['num' => 90]);
                }
            case self::DATE_RANGE_PASTYEAR: {
                    return Craft::t('counter', 'Past year');
                }
            case self::DATE_RANGE_CUSTOM: {
                    if (!$this->_startDate || !$this->_endDate) {
                        return '';
                    }

                    $startDate = Craft::$app->getFormatter()->asDate($this->_startDate, Locale::LENGTH_SHORT);
                    $endDate = Craft::$app->getFormatter()->asDate($this->_endDate, Locale::LENGTH_SHORT);

                    if (Craft::$app->getLocale()->getOrientation() == 'rtl') {
                        return $endDate . ' - ' . $startDate;
                    }

                    return $startDate . ' - ' . $endDate;
                }
            default: {
                    return '';
                }
        }
    }

    /**
     * @throws Exception
     */
    private function _setDates(): void
    {
        if (!$this->dateRange) {
            throw new Exception('A date range string must be specified to set stat dates.');
        }

        if ($this->_startDate && $this->_endDate) {
            return;
        }

        if ($this->dateRange != self::DATE_RANGE_CUSTOM) {
            $this->setStartDate($this->_getStartDate($this->dateRange));
            $this->setEndDate($this->_getEndDate($this->dateRange));
        }
    }

    /**
     * Based on the date range return the start date.
     *
     * @throws \Exception
     */
    private function _getStartDate(string $dateRange): bool|DateTime
    {
        if ($dateRange == self::DATE_RANGE_CUSTOM) {
            return false;
        }

        $date = new DateTime();
        switch ($dateRange) {
            case self::DATE_RANGE_ALL: {
                    $firstRecord = CounterRecord::find()->select('dateCreated')->orderBy('dateCreated asc')->scalar();
                    $date = new DateTime($firstRecord, new DateTimeZone('UTC'));
                    break;
                }
            case self::DATE_RANGE_YESTERDAY: {
                    $date = DateTimeHelper::toDateTime(strtotime('yesterday'));
                    break;
                }
            case self::DATE_RANGE_TODAY: {
                    $date = new DateTime();
                    break;
                }
            case self::DATE_RANGE_THISMONTH: {
                    $calendar = IntlCalendar::createInstance(null, $this->locale . '@calendar=' . $this->calendar);
                    $currentYear = $calendar->get(IntlCalendar::FIELD_YEAR);
                    $currentMonth = $calendar->get(IntlCalendar::FIELD_MONTH);
                    $calendar->set($currentYear, $currentMonth, 1);
                    $date = $calendar->toDateTime();
                    break;
                }
            case self::DATE_RANGE_THISWEEK: {
                    if (date('l') != self::START_DAY_INT_TO_DAY[$this->weekStartDay]) {
                        $date = DateTimeHelper::toDateTime(strtotime('last ' . self::START_DAY_INT_TO_DAY[$this->weekStartDay]));
                    }
                    break;
                }
            case self::DATE_RANGE_THISYEAR: {
                    $calendar = IntlCalendar::createInstance(null, $this->locale . '@calendar=' . $this->calendar);
                    $currentYear = $calendar->get(IntlCalendar::FIELD_YEAR);
                    $calendar->set($currentYear, 0, 1);
                    $date = $calendar->toDateTime();
                    break;
                }
            case self::DATE_RANGE_PAST7DAYS:
            case self::DATE_RANGE_PAST30DAYS:
            case self::DATE_RANGE_PAST90DAYS: {
                    $number = str_replace(['past', 'Days'], '', $dateRange);
                    // Minus one so we include today as a "past day"
                    $number--;
                    $date = $this->_getEndDate($dateRange);
                    $interval = new DateInterval('P' . $number . 'D');
                    $date->sub($interval);
                    break;
                }
            case self::DATE_RANGE_PASTYEAR: {
                    $date = $this->_getEndDate($dateRange);
                    $interval = new DateInterval('P1Y');
                    $date->sub($interval);
                    $date->modify('first day of next month');
                    break;
                }
        }

        if ($dateRange == self::DATE_RANGE_THISHOUR) {
            $hour = $date->format('H');
            $date->setTime((int)$hour, 0);
        } elseif ($dateRange == self::DATE_RANGE_PREVIOUSHOUR) {
            $date->modify('-1 hour');
            $hour = $date->format('H');
            $date->setTime((int)$hour, 0);
        } else {
            $date->setTime(0, 0);
        }
        return $date;
    }


    /**
     * Based on the date range return the end date.
     *
     * @throws \Exception
     */
    private function _getEndDate(string $dateRange): bool|DateTime
    {
        if ($dateRange == self::DATE_RANGE_CUSTOM) {
            return false;
        }

        $date = new DateTime();
        switch ($dateRange) {
            case self::DATE_RANGE_YESTERDAY: {
                    $date = DateTimeHelper::toDateTime(strtotime('yesterday'));
                    break;
                }
            case self::DATE_RANGE_THISWEEK: {
                    $endDayOfWeek = self::START_DAY_INT_TO_END_DAY[$this->weekStartDay];
                    if (date('l') != $endDayOfWeek) {
                        $date = DateTimeHelper::toDateTime(strtotime('next ' . $endDayOfWeek));
                    }
                    break;
                }
        }

        if ($dateRange == self::DATE_RANGE_THISHOUR || $dateRange == self::DATE_RANGE_TODAY) {
            $hour = $date->format('H');
            $date->setTime((int)$hour, 59, 59);
        } elseif ($dateRange == self::DATE_RANGE_PREVIOUSHOUR) {
            $date->modify('-1 hour');
            $hour = $date->format('H');
            $date->setTime((int)$hour, 59, 59);
        } else {
            $date->setTime(23, 59, 59);
        }
        return $date;
    }

    /**
     * Generate base data
     */
    protected function _baseData(): array
    {
        // Make sure the end time is always the last point on that day.
        if ($this->_endDate instanceof DateTime && $this->dateRange != self::DATE_RANGE_THISHOUR && $this->dateRange != self::DATE_RANGE_PREVIOUSHOUR && $this->dateRange != self::DATE_RANGE_TODAY) {
            $this->_endDate->setTime(23, 59, 59);
        }

        $startDate = Db::prepareDateForDb($this->_startDate);
        $endDate = Db::prepareDateForDb($this->_endDate);
        $query = (new Query())
            ->from('{{%counter_counter}}' . ' counter')
            ->andWhere(['>=', 'dateUpdated', $startDate])
            ->andWhere(['<=', 'dateUpdated', $endDate]);
        $query->select(["*"]);

        return array($query, $this->_startDate, $this->_endDate);
    }
}
