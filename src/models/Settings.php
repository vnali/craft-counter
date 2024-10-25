<?php

namespace vnali\counter\models;

use craft\base\Model;

/**
 * Counter settings
 */
class Settings extends Model
{
    public ?bool $anonymizedIpInEvent = false;

    public ?bool $anonymizeIp = true;

    public ?bool $disableCountController = null;

    public ?bool $ignoreAllUsers = null;

    public ?bool $ignoreBots = null;

    public string|array|null $ignoreGroups = null;
    
    public string|array|null $ignoreUserIds = null;

    public ?bool $ipInEvent = false;

    public ?bool $showAllCalendars = false;

    public ?bool $supportOutdatedBrowsers = null;

    public ?int $keepVisitorsInSeconds = -1;

    public ?int $onlineThreshold = 60;

    public ?int $visitsInterval = 60;

    public ?string $removeQueryParams = null;
    
    public bool $registerCounter = false;

    public bool $removeAllQueryParams = false;

    public bool $removeUrlFragment = false;

    public ?string $salt = null;

    public ?string $siteSettings = null;

    public function rules(): array
    {
        $rules = parent::rules();
        $rules[] = [['removeQueryParams', 'siteSettings'], 'safe'];
        $rules[] = [['registerCounter', 'removeAllQueryParams', 'removeUrlFragment', 'ignoreAllUsers', 'ignoreBots', 'supportOutdatedBrowsers', 'disableCountController', 'anonymizeIp', 'ipInEvent', 'anonymizedIpInEvent'], 'in', 'range' => [0, 1]];
        $rules[] = [['onlineThreshold'], 'integer', 'min' => 1, 'max' => 600];
        $rules[] = [['visitsInterval'], 'integer', 'min' => 0, 'max' => 600];

        return $rules;
    }
}
