<?php

namespace vnali\counter\helpers;

use Craft;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use IntlCalendar;
use vnali\counter\Counter;
use vnali\counter\models\Settings;

class DateTimeHelper
{
    /**
     * Fill gaps between two dates or times
     *
     * @param array $times
     * @param array $items
     * @param string $interval
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param string $dateRange
     * @param string|null $calendar
     * @param string|null $preferredInterval
     * @return array
     */
    public static function fillTimes(array $times, array $items, string $interval, DateTime $startDate, DateTime $endDate, string $dateRange, ?string $calendar = null, ?string $preferredInterval = null): array
    {
        if (!$calendar) {
            $calendar = 'gregorian';
        }
        if (!$preferredInterval) {
            $preferredInterval = 'hourly';
        }

        $now = new DateTime();
        $minuteTz = $now->format('i');
        $all_times = [];

        if (($dateRange == 'thisHour' || $dateRange == 'previousHour') && $interval == 'H:i') {
            $hour = $startDate->format('H');
            $hours[] = $hour . ':00';
            if ($dateRange == 'thisHour') {
                if ($preferredInterval == '15minutes') {
                    if ($minuteTz >= 15) {
                        $hours[] = $hour . ':15';
                    }
                    if ($minuteTz >= 30) {
                        $hours[] = $hour . ':30';
                    }
                    if ($minuteTz >= 45) {
                        $hours[] = $hour . ':45';
                    }
                } elseif ($preferredInterval == '30minutes') {
                    if ($minuteTz >= 30) {
                        $hours[] = $hour . ':30';
                    }
                }
            } elseif ($dateRange == 'previousHour') {
                if ($preferredInterval == '15minutes') {
                    $hours[] = $hour . ':15';
                    $hours[] = $hour . ':30';
                    $hours[] = $hour . ':45';
                } elseif ($preferredInterval == '30minutes') {
                    $hours[] = $hour . ':30';
                }
            }
            foreach ($hours as $hour) {
                foreach ($items as $item) {
                    $all_times[$hour][$item] = $times[$hour][$item] ?? 0;
                }
            }
        } elseif ($interval == 'H:i') {
            $first = 0;
            $lastHour = $endDate->format('H');
            for ($i = $first; $i <= $lastHour; $i++) {
                // Format the number to two digits
                $hour = str_pad((string)$i, 2, "0", STR_PAD_LEFT);
                if ($preferredInterval == '15minutes') {
                    foreach ($items as $item) {
                        $all_times[$hour . ':00'][$item] = $times[$hour . ':00'][$item] ?? 0;
                    }
                    if ($dateRange != 'today' || $hour < $lastHour || $minuteTz >= 15) {
                        foreach ($items as $item) {
                            $all_times[$hour . ':15'][$item] = $times[$hour . ':15'][$item] ?? 0;
                        }
                    }
                    if ($dateRange != 'today' || $hour < $lastHour || $minuteTz >= 30) {
                        foreach ($items as $item) {
                            $all_times[$hour . ':30'][$item] = $times[$hour . ':30'][$item] ?? 0;
                        }
                    }
                    if ($dateRange != 'today' || $hour < $lastHour || $minuteTz >= 45) {
                        foreach ($items as $item) {
                            $all_times[$hour . ':45'][$item] = $times[$hour . ':45'][$item] ?? 0;
                        }
                    }
                } elseif ($preferredInterval == '30minutes') {
                    foreach ($items as $item) {
                        $all_times[$hour . ':00'][$item] = $times[$hour . ':00'][$item] ?? 0;
                    }
                    if ($dateRange != 'today' || $hour < $lastHour || $minuteTz >= 30) {
                        foreach ($items as $item) {
                            $all_times[$hour . ':30'][$item] = $times[$hour . ':30'][$item] ?? 0;
                        }
                    }
                }
            }
        } elseif (($dateRange != 'thisHour' && $dateRange != 'previousHour') && $interval == 'H') {
            $first = 0;
            $last = $endDate->format('H');
            for ($i = $first; $i <= $last; $i++) {
                // Format the number to two digits
                $hour = str_pad((string)$i, 2, "0", STR_PAD_LEFT);
                foreach ($items as $item) {
                    $all_times[$hour][$item] = $times[$hour][$item] ?? 0;
                }
            }
        } elseif ($interval == 'Y') {
            $first = self::intlDate($startDate, $calendar, self::intlFormat($interval), 'en_US');
            $last = self::intlDate($endDate, $calendar, self::intlFormat($interval), 'en_US');

            // fill years from first available year to last available year
            for ($i = $first; $i <= $last; $i++) {
                foreach ($items as $item) {
                    $all_times[$i][$item] = $times[$i][$item] ?? 0;
                }
            }
        } elseif ($interval == 'Y-m-d') {
            if ($calendar == 'gregorian') {
                $endDayFormatted = $endDate->format($interval);
            } else {
                $endDayFormatted = self::intlDate($endDate, $calendar, self::intlFormat($interval));
            }

            $end = true;
            do {
                if ($calendar == 'gregorian') {
                    $startDayFormatted = $startDate->format($interval);
                } else {
                    $startDayFormatted = self::intlDate($startDate, $calendar, self::intlFormat($interval));
                }
                if ($startDayFormatted <= $endDayFormatted) {
                    foreach ($items as $item) {
                        $all_times[$startDayFormatted][$item] = $times[$startDayFormatted][$item] ?? 0;
                    }
                    $startDate->modify('+1 day');
                } else {
                    $end = false;
                }
            } while ($end);
        } elseif ($interval == 'Y-m') {
            $endDateDayFormatted = self::intlDate($endDate, $calendar, self::intlFormat($interval));
            $end = true;
            do {
                $startDayFormatted = self::intlDate($startDate, $calendar, self::intlFormat($interval));
                if ($startDayFormatted <= $endDateDayFormatted) {
                    foreach ($items as $item) {
                        $all_times[$startDayFormatted][$item] = $times[$startDayFormatted][$item] ?? 0;
                    }
                    $intlCalendar = IntlCalendar::createInstance();
                    $intlCalendar->set(
                        (int)$startDate->format('Y'),
                        $startDate->format('n') - 1,
                        (int)$startDate->format('j'),
                    );
                    $intlCalendar->add(IntlCalendar::FIELD_MONTH, 1);
                    $intlCalendar->set(IntlCalendar::FIELD_DAY_OF_MONTH, 1);
                    $startDate = $intlCalendar->toDateTime();
                } else {
                    $end = false;
                }
            } while ($end);
        } else {
            $all_times = $times;
        }

        return $all_times;
    }

    /**
     * Return intl date for a given datetime
     *
     * @param DateTime|DateTimeImmutable $dateTime
     * @param string|null $calendar
     * @param string|null $format
     * @param string|null $locale
     * @param string|null $tz
     * @return string
     */
    public static function intlDate(DateTime|DateTimeImmutable $dateTime, ?string $calendar = null, ?string $format = null, ?string $locale = null, ?string $tz = null): string
    {
        if (!$tz) {
            $tz = Craft::$app->getTimeZone();
        }
        if (!$calendar) {
            $calendar = 'gregorian';
        }
        if (!$format) {
            $format = 'yyyy-MM-dd HH:mm:ss';
        }
        if (!$locale) {
            $locale = 'en_US';
            // TODO: more test for other locales
            /*
            $user = Craft::$app->getUser()->getIdentity();
            if ($user) {
                $locale = $user->getPreference('locale') ?? $user->getPreference('language') ?? 'en_US';
            } else {
                $locale = 'en_US';
            }
            */
        }

        $intl = new \IntlDateFormatter($locale . '@calendar=' . $calendar, \IntlDateFormatter::FULL, \IntlDateFormatter::FULL, $tz, \IntlDateFormatter::TRADITIONAL, $format);
        $date = $intl->format($dateTime);

        return $date;
    }

    /**
     * Returns interval for a date range
     *
     * @param string $dateRange
     * @param DateTime|null $start
     * @param DateTime|null $end
     * @return string
     */
    public static function Interval(string $dateRange, ?DateTime $start = null, ?DateTime $end = null, ?string $preferredInterval = null): string
    {
        if ($dateRange == 'custom') {
            $diff = $start->diff($end);
            if ($diff->y == 0 && $diff->m == 0 && $diff->d == 0 && $diff->h == 23) {
                $interval = 'H';
            } elseif ($diff->y > 0 || $diff->m > 0) {
                $interval = 'Y-m';
            } else {
                $interval = 'Y-m-d';
            }
        } elseif ($dateRange == 'all') {
            $interval = 'Y';
        } elseif ($dateRange == 'thisYear' || $dateRange == 'pastYear') {
            $interval = 'Y-m';
        } elseif ($dateRange == 'thisWeek' || $dateRange == 'thisMonth' || $dateRange == 'past90Days' || $dateRange == 'past7Days' || $dateRange == 'past30Days') {
            $interval = 'Y-m-d';
        } elseif ($dateRange == 'today' || $dateRange == 'yesterday') {
            if ($preferredInterval == 'hourly') {
                $interval = 'H';
            } else {
                $interval = 'H:i';
            }
        } elseif ($dateRange == 'thisHour' || $dateRange == 'previousHour') {
            if ($preferredInterval == 'hourly') {
                $interval = 'H';
            } else {
                $interval = 'H:i';
            }
        } else {
            $interval = 'd';
        }

        return $interval;
    }

    /**
     * Return format for intl formatter based on date format
     *
     * @param string $format
     * @return string
     */
    public static function intlFormat(string $format): string
    {
        switch ($format) {
            case 'Y':
                $intlFormat = 'yyyy';
                break;
            case 'Y-m':
                $intlFormat = 'yyyy-MM';
                break;
            case 'Y-m-d':
                $intlFormat = 'yyyy-MM-dd';
                break;
            case 'F':
                $intlFormat = 'MMMM';
                break;
            case 'm':
                $intlFormat = 'MM';
                break;
            case 'd':
                $intlFormat = 'dd';
                break;
            case 'H':
                $intlFormat = 'HH';
                break;
            case 'H:i':
                $intlFormat = 'HH:mm';
                break;
            case 'Y-m-d h:i:s':
                $intlFormat = 'yyyy-MM-dd HH:mm:ss';
                break;
            default:
                $intlFormat = 'dd';
                break;
        }

        return $intlFormat;
    }

    /**
     * Calculates time difference
     *
     * @param DateInterval $interval
     * @return int
     */
    public static function timeDifference(DateInterval $interval): int
    {
        $difference = $interval->s
            + $interval->i * 60
            + $interval->h * 3600
            + $interval->d * 86400
            + $interval->m * 2592000
            + $interval->y * 31536000;

        return $difference;
    }

    /**
     * Returns today 00:00 datetime
     *
     * @param DateTimeZone|null $timeZone
     * @return DateTime
     */
    public static function today(?DateTimeZone $timeZone = null): DateTime
    {
        return static::now($timeZone)->setTime(0, 0);
    }

    /**
     * Returns today
     *
     * @param DateTimeZone|null $timeZone
     * @return DateTime
     */
    public static function now(?DateTimeZone $timeZone = null): DateTime
    {
        return new DateTime('now', $timeZone);
    }

    /**
     * Returns first day of week that is set for a site in plugin setting. fallback is general defaultWeekStartDay
     *
     * @param int $siteId
     * @return int
     */
    public static function siteFirstWeekDay(int $siteId): int
    {
        $site = Craft::$app->sites->getSiteById($siteId);
        $pluginSettings = Counter::$plugin->getSettings();
        /** @var Settings $pluginSettings */
        $siteSettings = json_decode($pluginSettings->siteSettings, true);
        $siteUnique = $site->uid;

        if (isset($site) && isset($siteSettings[$siteUnique]['weekStartDay'])) {
            return (int)$siteSettings[$siteUnique]['weekStartDay'];
        } else {
            return (int)(Craft::$app->getConfig()->getGeneral()->defaultWeekStartDay);
        }
    }

    /**
     * Returns a [[DateTime]] object set to midnight of the first day of this week for a given date/today considering first day of week for a site
     *
     * @param int $siteId
     * @param DateTime|null $dateTime
     * @param DateTimeZone|null $timeZone
     * @return DateTime
     */
    public static function thisWeek(Int $siteId, ?DateTime $dateTime = null, ?DateTimeZone $timeZone = null): DateTime
    {
        $dateTime = $dateTime ? $dateTime->setTime(0, 0) : static::today($timeZone);
        $dayOfWeek = (int)$dateTime->format('N');
        if ($dayOfWeek === 7) {
            $dayOfWeek = 0;
        }
        $startDay = static::siteFirstWeekDay($siteId);
        // Is today the first day of the week?
        if ($dayOfWeek === $startDay) {
            return $dateTime;
        }

        if ($dayOfWeek > $startDay) {
            $diff = $dayOfWeek - $startDay;
        } else {
            $diff = ($dayOfWeek + 7) - $startDay;
        }

        return $dateTime->modify("-$diff days");
    }

    /**
     * Returns a [[DateTime]] object set to midnight of the first day of last week for a given date/today considering first day of week for a site
     *
     * @param int $siteId
     * @param DateTime|null $dateTime
     * @param DateTimeZone|null $timeZone
     * @return DateTime
     */
    public static function lastWeek(int $siteId, ?DateTime $dateTime = null, ?DateTimeZone $timeZone = null): DateTime
    {
        return static::thisWeek($siteId, $dateTime, $timeZone)->modify('-1 week');
    }
}
