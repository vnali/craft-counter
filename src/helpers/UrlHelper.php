<?php

namespace vnali\counter\helpers;

use vnali\counter\Counter;
use vnali\counter\models\Settings;

class UrlHelper
{
    /**
     * Remove query params from page URL
     *
     * @param string $url
     * @return string
     */
    public static function removeQueryParams(string $url): string
    {
        $pluginSettings = Counter::$plugin->getSettings();
        /** @var Settings $pluginSettings */
        $removeQueryParams = $pluginSettings->removeQueryParams;
        $removeQueryParams = explode(',', $removeQueryParams);
        $paramsToRemove = [];
        foreach ($removeQueryParams as $param) {
            $paramsToRemove[] = trim($param);
        }
        $parsedUrl = parse_url($url);
        parse_str($parsedUrl['query'] ?? '', $queryParams);
        $newQueryParams = [];
        foreach ($queryParams as $key => $value) {
            if (!in_array($key, $paramsToRemove)) {
                $newQueryParams[] = $key . '=' . $value;
            }
        }
        $newUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
        if (isset($parsedUrl['port'])) {
            $newUrl .= ':' . $parsedUrl['port'];
        }
        $newUrl .= $parsedUrl['path'];
        if (!empty($newQueryParams)) {
            $newUrl .= '?' . implode('&', $newQueryParams);
        }
        // Append the fragment if it exists
        /** @var Settings $settings */
        $settings = Counter::$plugin->getSettings();
        if (isset($parsedUrl['fragment']) && !$settings->removeUrlFragment) {
            $newUrl .= '#' . $parsedUrl['fragment'];
        }
        return $newUrl;
    }

    /**
     * Remove all query params from page URL
     *
     * @param string $url
     * @return string
     */
    public static function removeAllQueryParams(string $url): string
    {
        $parsedUrl = parse_url($url);
        $newUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
        if (isset($parsedUrl['port'])) {
            $newUrl .= ':' . $parsedUrl['port'];
        }
        $newUrl .= $parsedUrl['path'];
        /** @var Settings $settings */
        $settings = Counter::$plugin->getSettings();
        if (isset($parsedUrl['fragment']) && !$settings->removeUrlFragment) {
            $newUrl .= '#' . $parsedUrl['fragment'];
        }
        return $newUrl;
    }

    /**
     * Trim a URL to a specific length and bytes
     *
     * @param string $url
     * @param int $maxLength
     * @param int $maxBytes
     * @return string
     */
    public static function trimURL(string $url, ?int $maxLength = 2048, ?int $maxBytes = 3072): string
    {
        $url = mb_convert_encoding($url, 'UTF-8', 'auto');
        // maximum length is $maxLength, maximum url bytes is $maxBytes
        if (mb_strlen($url) > $maxLength || strlen($url) > $maxBytes) {
            $trimmedUrl = $url;
            // if it's length is bigger 2048, cut it
            if (mb_strlen($url) > $maxLength) {
                $trimmedUrl = mb_substr($url, 0, $maxLength, 'UTF-8');
            }

            // Double-check that the trimmed URL fits within the byte length limit
            while (mb_strlen($trimmedUrl) > $maxLength || strlen($trimmedUrl) > $maxBytes) {
                // Remove one character from the end if needed
                $trimmedUrl = mb_substr($trimmedUrl, 0, -1, 'UTF-8');
            }
        } else {
            $trimmedUrl = $url;
        }
        return $trimmedUrl;
    }
}
