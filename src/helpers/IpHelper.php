<?php

namespace vnali\counter\helpers;

class IpHelper
{
    /**
     * Anonymize IP
     *
     * @param string $ip
     * @return string
     */
    public static function anonymizeIp(string $ip): string
    {
        // Check if it's an IPv4 address
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $parts[3] = 'x';
            return implode('.', $parts);
        }

        // Check if it's an IPv6 address
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);
            for ($i = 5; $i < 8; $i++) {
                $parts[$i] = 'xxxx';
            }
            return implode(':', $parts);
        }

        return $ip;
    }
}
