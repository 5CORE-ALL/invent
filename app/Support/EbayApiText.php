<?php

namespace App\Support;

/**
 * eBay Trading XML often repeats a tag. json_encode(SimpleXML) then
 * turns that tag into a list. Casting the list to string aborts the push.
 */
class EbayApiText
{
    public static function string(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }
        if (is_int($value) || is_float($value)) {
            return trim((string) $value);
        }
        if (! is_array($value)) {
            return '';
        }

        $parts = [];
        foreach ($value as $item) {
            $text = self::string($item);
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return implode(' ', $parts);
    }
}
