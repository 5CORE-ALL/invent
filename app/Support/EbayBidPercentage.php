<?php

namespace App\Support;

/**
 * Promoted Listings CPS bidPercentage.
 * eBay accepts one decimal from 2.0 through 100.0. Anything else
 * (8.11, 10.75, 150) rejects the whole bulk update, so nothing pushes.
 */
final class EbayBidPercentage
{
    public const MIN = 2.0;

    public const MAX = 100.0;

    /**
     * ES Bid is the higher recommendation rate. ITEM alone misses TRENDING
     * when TRENDING is the maximum.
     *
     * @param  array<int, mixed>  $bidPercentages
     */
    public static function maximumSuggested(array $bidPercentages): ?float
    {
        $max = null;
        foreach ($bidPercentages as $row) {
            if (! is_array($row) || ! isset($row['value']) || ! is_numeric($row['value'])) {
                continue;
            }
            $value = (float) $row['value'];
            if ($max === null || $value > $max) {
                $max = $value;
            }
        }

        return $max;
    }

    /** String eBay will accept, or null when there is no bid to send. */
    public static function forPush(float $bid): ?string
    {
        if ($bid <= 0) {
            return null;
        }
        $n = round($bid, 1);
        if ($n < self::MIN) {
            $n = self::MIN;
        }
        if ($n > self::MAX) {
            $n = self::MAX;
        }

        return number_format($n, 1, '.', '');
    }

    /** Maximum from an eBay 35007 body, when the bid was above it. */
    public static function maxFromError($body): ?float
    {
        if (! is_array($body)) {
            return null;
        }
        $errors = [];
        if (isset($body['errors']) && is_array($body['errors'])) {
            $errors = $body['errors'];
        }
        foreach ($body['responses'] ?? [] as $row) {
            if (is_array($row) && isset($row['errors']) && is_array($row['errors'])) {
                $errors = array_merge($errors, $row['errors']);
            }
        }
        foreach ($errors as $err) {
            if (! is_array($err)) {
                continue;
            }
            foreach ($err['parameters'] ?? [] as $param) {
                if (is_array($param) && ($param['name'] ?? '') === 'maxBidPercent' && is_numeric($param['value'] ?? null)) {
                    return (float) $param['value'];
                }
            }
            $msg = (string) ($err['message'] ?? '');
            if (preg_match('/Maximum value:\s*([0-9]+(?:\.[0-9]+)?)/i', $msg, $match)) {
                return (float) $match[1];
            }
        }

        return null;
    }
}
