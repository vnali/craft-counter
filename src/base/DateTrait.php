<?php

namespace vnali\counter\base;

use DateTime;

trait DateTrait
{
    /**
     * @var string|null
     */
    public ?string $dateRange = DateInterface::DATE_RANGE_TODAY;

    /**
     * @var int
     */
    public int $weekStartDay = 1; // Monday

    /**
     * @var null|DateTime
     */
    private ?DateTime $_startDate = null;

    /**
     * @var null|DateTime
     */
    private ?DateTime $_endDate = null;
}
