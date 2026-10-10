<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Last BGT rule chart counts, stored in amazon_ads_rules under key counts.
 * The modal paints these immediately. A later recount replaces them.
 */
final class AmazonAdsBgtCountStore
{
    use StoresAmazonAdsRuleJson;

    private const KEY = 'counts';

    private const COLUMNS = ['acos', 'views', 'cvr', 'prc', 'reviews', 'dil', 'inv', 'spend'];

    /**
     * @return array<string, mixed>|null
     */
    public static function read(): ?array
    {
        $decoded = self::readStoredRule(self::KEY);
        if ($decoded === null || $decoded === []) {
            return null;
        }

        return self::normalize($decoded);
    }

    /**
     * @param  array<string, mixed>  $counts
     */
    public static function write(array $counts): void
    {
        self::storeRuleJson(self::KEY, self::normalize($counts));
    }

    public static function updatedAt(): ?string
    {
        self::ensureRulesTable();
        $value = DB::table('amazon_ads_rules')->where('key', self::KEY)->value('updated_at');

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input): array
    {
        $columns = [];
        $columnsIn = isset($input['columns']) && is_array($input['columns']) ? $input['columns'] : [];
        foreach (self::COLUMNS as $key) {
            $col = $columnsIn[$key] ?? null;
            if (! is_array($col)) {
                continue;
            }
            $list = [];
            foreach (array_values($col['counts'] ?? []) as $n) {
                if (count($list) >= 40) {
                    break;
                }
                $list[] = max(0, (int) $n);
            }
            if ($list === []) {
                continue;
            }
            $columns[$key] = [
                'counts' => $list,
                'unmatched' => max(0, (int) ($col['unmatched'] ?? 0)),
                'campaigns' => max(0, (int) ($col['campaigns'] ?? 0)),
            ];
        }

        $sum = null;
        if (isset($input['sum']) && is_array($input['sum'])) {
            $rows = [];
            foreach (array_values($input['sum']['rows'] ?? []) as $row) {
                if (! is_array($row) || count($rows) >= 80) {
                    break;
                }
                $color = isset($row['color']) ? (string) $row['color'] : '#64748b';
                if (! preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) {
                    $color = '#64748b';
                }
                $rows[] = [
                    'label' => mb_substr(trim((string) ($row['label'] ?? '')), 0, 40),
                    'n' => max(0, (int) ($row['n'] ?? 0)),
                    'color' => $color,
                ];
            }
            $parts = [];
            foreach (array_values($input['sum']['parts'] ?? []) as $part) {
                if (! is_array($part) || count($parts) >= 12) {
                    break;
                }
                $parts[] = [
                    'label' => mb_substr(trim((string) ($part['label'] ?? '')), 0, 40),
                    'count' => max(0, (int) ($part['count'] ?? 0)),
                    'sum' => self::finiteNumber($part['sum'] ?? 0),
                ];
            }
            $sum = [
                'rows' => $rows,
                'totalN' => max(0, (int) ($input['sum']['totalN'] ?? 0)),
                'sbgtTotal' => self::finiteNumber($input['sum']['sbgtTotal'] ?? 0),
                'campaigns' => max(0, (int) ($input['sum']['campaigns'] ?? 0)),
                'countTotal' => max(0, (int) ($input['sum']['countTotal'] ?? 0)),
                'bgtTotal' => self::finiteNumber($input['sum']['bgtTotal'] ?? 0),
                'parts' => $parts,
            ];
        }

        return [
            'filter' => mb_substr(trim((string) ($input['filter'] ?? '')), 0, 4000),
            'columns' => $columns,
            'sum' => $sum,
        ];
    }

    private static function finiteNumber(mixed $value): float
    {
        $n = is_numeric($value) ? (float) $value : 0.0;

        return is_finite($n) ? $n : 0.0;
    }
}
