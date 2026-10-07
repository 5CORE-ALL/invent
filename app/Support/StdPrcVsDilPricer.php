<?php

namespace App\Support;

use App\Models\ChannelTabulatorColumnSetting;

/**
 * S PRC from a channel's saved Std prc vs dil slabs.
 * S PRC = Std Prc × (1 − (Age + Dil + CVR up/down + Review) / 100).
 * Used by the unattended apply commands. The page does not have to be open.
 */
class StdPrcVsDilPricer
{
    /** @var array<string, self> */
    private static array $cache = [];

    /** @param array{dil:list<array<string,float>>,age:list<array<string,float>>,cvr:array<string,float>,reviews:list<array<string,float>>,review_max:int} $rules */
    public function __construct(private array $rules) {}

    public static function forChannel(string $channel): self
    {
        if (isset(self::$cache[$channel])) {
            return self::$cache[$channel];
        }
        $defaults = self::defaults();
        $row = ChannelTabulatorColumnSetting::query()
            ->where('channel_name', $channel.'_std_prc_vs_dil')
            ->first();
        $saved = is_array($row?->visibility) ? $row->visibility : [];
        $rules = [
            'dil' => self::ranges($saved['dil'] ?? null, $defaults['dil']),
            'age' => self::ranges($saved['age'] ?? null, $defaults['age']),
            'cvr' => self::cvr($saved['cvr'] ?? null, $defaults['cvr']),
            'reviews' => self::ranges($saved['reviews'] ?? null, $defaults['reviews']),
            'review_max' => is_numeric($saved['review_max'] ?? null) ? max(1, (int) $saved['review_max']) : $defaults['review_max'],
        ];

        return self::$cache[$channel] = new self($rules);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function priceFromRow(array $row): ?float
    {
        $inv = (float) ($row['inv'] ?? $row['INV'] ?? 0);
        if (! ($inv > 0)) {
            return null;
        }
        $std = (float) ($row['std_price'] ?? $row['std'] ?? $row['STANDARD_PRICE'] ?? $row['standard_price'] ?? 0);
        if (! ($std > 0)) {
            return null;
        }
        $dil = (float) ($row['dil'] ?? 0);
        $age = is_numeric($row['age_days'] ?? null) ? (float) $row['age_days'] : null;
        $cvr = (float) ($row['cvr'] ?? 0);
        $cvr60 = (float) ($row['cvr_60'] ?? 0);
        $reviews = (float) ($row['review_count'] ?? $row['reviews'] ?? $row['Reviews'] ?? 0);
        $sum = min(99.99, max(0, $this->ageDisc($age) + $this->rangeDisc($dil, $this->rules['dil']) + $this->cvrDisc($cvr, $cvr60) + $this->reviewDisc($reviews)));
        $price = round($std * (1 - $sum / 100), 2);

        return $price > 0 ? $price : null;
    }

    private function ageDisc(?float $age): float
    {
        if ($age === null) {
            return 0.0;
        }

        return $this->rangeDisc($age, $this->rules['age']);
    }

    /**
     * @param  list<array<string, float>>  $rules
     */
    private function rangeDisc(float $value, array $rules): float
    {
        if ($value < 0 || $rules === []) {
            return 0.0;
        }
        $last = count($rules) - 1;
        foreach ($rules as $i => $rule) {
            $min = (float) $rule['min'];
            $max = (float) $rule['max'];
            if ($max < $min) {
                [$min, $max] = [$max, $min];
            }
            $hit = abs($max - $min) < 0.00001
                ? abs($value - $min) < 0.00001
                : ($value >= $min && ($i === $last ? $value <= $max : $value < $max));
            if ($hit) {
                return max(0, (float) $rule['disc']);
            }
        }

        return 0.0;
    }

    private function cvrDisc(float $cvr, float $cvr60): float
    {
        $cfg = $this->rules['cvr'];
        $trend = 'flat';
        if ($cvr60 > 0) {
            if ($cvr === 0.0 || $cvr < $cvr60 - 0.1) {
                $trend = 'down';
            } elseif ($cvr > $cvr60 + 0.1) {
                $trend = 'up';
            }
        }
        if ($trend === 'down') {
            $slabs = [];
            if (($cfg['down2_lt'] ?? 0) > 0) {
                $slabs[] = ['lt' => (float) $cfg['down2_lt'], 'disc' => (float) $cfg['down2_disc']];
            }
            if (($cfg['down_lt'] ?? 0) > 0) {
                $slabs[] = ['lt' => (float) $cfg['down_lt'], 'disc' => (float) $cfg['down_disc']];
            }
            usort($slabs, fn ($a, $b) => $a['lt'] <=> $b['lt']);
            foreach ($slabs as $slab) {
                if ($cvr < $slab['lt']) {
                    return max(0, $slab['disc']);
                }
            }
        }
        if ($trend === 'up') {
            $slabs = [
                ['gt' => (float) ($cfg['up_gt'] ?? 0), 'disc' => (float) ($cfg['up_disc'] ?? 0)],
            ];
            if (is_numeric($cfg['up2_gt'] ?? null)) {
                $slabs[] = ['gt' => (float) $cfg['up2_gt'], 'disc' => (float) ($cfg['up2_disc'] ?? 0)];
            }
            usort($slabs, fn ($a, $b) => $b['gt'] <=> $a['gt']);
            foreach ($slabs as $slab) {
                if ($cvr > $slab['gt']) {
                    return max(0, $slab['disc']);
                }
            }
        }

        return max(0, (float) ($cfg['flat_disc'] ?? 0));
    }

    private function reviewDisc(float $reviews): float
    {
        $max = (int) $this->rules['review_max'];
        if (! ($reviews > 0) || $reviews >= $max) {
            return 0.0;
        }
        foreach ($this->rules['reviews'] as $rule) {
            if ($reviews >= (float) $rule['min'] && $reviews <= (float) $rule['max']) {
                return max(0, (float) $rule['disc']);
            }
        }

        return 0.0;
    }

    /**
     * @param  mixed  $incoming
     * @param  list<array<string, float>>  $fallback
     * @return list<array<string, float>>
     */
    private static function ranges($incoming, array $fallback): array
    {
        if (! is_array($incoming)) {
            return $fallback;
        }
        $rules = [];
        foreach ($incoming as $item) {
            if (! is_array($item) || ! is_numeric($item['min'] ?? null) || ! is_numeric($item['max'] ?? null)) {
                continue;
            }
            $min = (float) $item['min'];
            $max = (float) $item['max'];
            if ($max < $min) {
                [$min, $max] = [$max, $min];
            }
            $disc = is_numeric($item['disc'] ?? null) ? (float) $item['disc'] : 0.0;
            $rules[] = ['min' => $min, 'max' => $max, 'disc' => min(100, max(0, $disc))];
        }

        return $rules !== [] ? $rules : $fallback;
    }

    /**
     * @param  mixed  $incoming
     * @param  array<string, float>  $defaults
     * @return array<string, float>
     */
    private static function cvr($incoming, array $defaults): array
    {
        $out = $defaults;
        if (! is_array($incoming)) {
            return $out;
        }
        foreach (['down2_lt', 'down_lt', 'up_gt', 'up2_gt', 'down2_disc', 'down_disc', 'up_disc', 'up2_disc', 'flat_disc'] as $key) {
            if (is_numeric($incoming[$key] ?? null) && (float) $incoming[$key] >= 0) {
                $out[$key] = (float) $incoming[$key];
            }
        }
        if (! is_numeric($incoming['down2_disc'] ?? null)) {
            $out['down2_disc'] = $out['down_disc'];
        }
        if (! is_numeric($incoming['up2_disc'] ?? null)) {
            $out['up2_disc'] = $out['up_disc'];
        }

        return $out;
    }

    /**
     * @return array{dil:list<array<string,float>>,age:list<array<string,float>>,cvr:array<string,float>,reviews:list<array<string,float>>,review_max:int}
     */
    private static function defaults(): array
    {
        return [
            'dil' => [
                ['min' => 0, 'max' => 0, 'disc' => 0],
                ['min' => 0.1, 'max' => 10, 'disc' => 0],
                ['min' => 10, 'max' => 25, 'disc' => 0],
                ['min' => 25, 'max' => 50, 'disc' => 0],
                ['min' => 50, 'max' => 100, 'disc' => 0],
                ['min' => 100, 'max' => 9999, 'disc' => 0],
            ],
            'age' => [
                ['min' => 0, 'max' => 30, 'disc' => 0],
                ['min' => 31, 'max' => 60, 'disc' => 0],
                ['min' => 61, 'max' => 90, 'disc' => 0],
                ['min' => 91, 'max' => 180, 'disc' => 0],
                ['min' => 181, 'max' => 365, 'disc' => 0],
                ['min' => 366, 'max' => 9999, 'disc' => 0],
            ],
            'cvr' => [
                'down2_lt' => 4, 'down2_disc' => 0, 'down_lt' => 7, 'down_disc' => 0,
                'up_gt' => 10, 'up_disc' => 0, 'up2_gt' => 15, 'up2_disc' => 0, 'flat_disc' => 0,
            ],
            'reviews' => [
                ['min' => 1, 'max' => 2, 'disc' => 4],
                ['min' => 2, 'max' => 3, 'disc' => 4],
            ],
            'review_max' => 4,
        ];
    }
}
