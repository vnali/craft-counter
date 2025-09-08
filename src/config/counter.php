<?php

/**
 * Counter config.php
 *
 * This file exists only as a template for the Counter plugin settings.
 * It does nothing on its own.
 *
 * Don't edit this file, instead copy it to 'craft/config' as 'counter.php'
 * and make your changes there to override default settings.
 *
 * Once copied to 'craft/config', this file will be multi-environment aware as
 * well, so you can have different settings groups for each environment, just as
 * you do for 'general.php'
 */

return [
    '*' => [
        'anonymizedIpInEvent' => false, // send anonymized IP in event, default is false
        'anonymizeIp' => true, // before hashing the IP, anonymize IP too, default is true
        'ipInEvent' => false, // send IP in event, default is false
        'cacheWidgetsSeconds' => 0, // default is 0. when this is not set or is 0, widgets use default caching system so cached results are used as long as the cached data is valid
        'headlessToken' => 'ComplexToken', // Token to send from frontend for counting in headless mode
        'autoImportJquery' => true, // When the 'Support outdated browsers' setting is enabled, the Counter plugin imports jQuery. Set this to false if your frontend already includes jQuery
        'removeDomainFromResult' => false, // Remove domain part of url from statistics results if true
        'widgetTitleTruncateLength' => 50, // Maximum number of characters for widget titles. A value of 0 disables truncation; any other value truncates the title to the specified length. The default is 50.
    ],
];
