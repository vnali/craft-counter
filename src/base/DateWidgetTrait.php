<?php

namespace vnali\counter\base;

use DateTime;

trait DateWidgetTrait
{
    /**
     * @var int|DateTime|null
     */
    public mixed $startDate = null;

    /**
     * @var int|DateTime|null
     */
    public mixed $endDate = null;

    /**
     * @var string|null
     */
    public ?string $dateRange = DateInterface::DATE_RANGE_TODAY; // set widget date range to Today in settings page

    /**
     * @var string|null
     */
    public ?string $calendar = null;
}
