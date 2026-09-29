<?php

namespace App\Support;

class DesignationKey
{
    /**
     * Decode HTML entities so "A &amp; B" and "A & B" are the same designation.
     */
    public static function canonical(string $value): string
    {
        $value = trim($value);
        $previous = null;
        while ($value !== $previous) {
            $previous = $value;
            $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return $value;
    }

    /**
     * Spellings that should match one designation, including the encoded form
     * already stored on some catalog rows.
     *
     * @return list<string>
     */
    public static function variants(string $value): array
    {
        $canonical = self::canonical($value);
        $encoded = htmlspecialchars($canonical, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return array_values(array_unique(array_filter(
            [$value, $canonical, $encoded, trim($value)],
            fn ($variant) => $variant !== ''
        )));
    }
}
