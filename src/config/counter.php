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
    ],
];
