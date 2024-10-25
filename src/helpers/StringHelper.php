<?php

namespace vnali\counter\helpers;

class StringHelper
{
    /**
     * convert a word to sentence case for example past 7 Days -> Past 7 days
     *
     * @param string $string
     * @return string
     */
    public static function toSentenceCase(string $string): string
    {
        $string = trim($string);
        return ucfirst(strtolower($string));
    }
}
