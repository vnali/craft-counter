<?php

namespace vnali\counter\events;

use craft\events\CancelableEvent;
use DateTime;

class CountEvent extends CancelableEvent
{
    public ?int $userId;
    public ?string $anonymizedIp;
    public ?string $ip;
    public ?string $userAgent;
    public DateTime $time;
    public int $siteId;
    public string $hashedIp;
    public string $page;
    public string $untrimmedPage;
}
