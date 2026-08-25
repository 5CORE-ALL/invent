<?php

namespace App\Support\Badges;

use App\Contracts\PageBadgeCalculator;
use App\Models\ProductMaster;
use Illuminate\Support\Facades\Cache;

class ComplianceMasterBadgeCalculator implements PageBadgeCalculator
{
    public const PAGE_NAME = 'compliance-master';

    public const CACHE_KEY = 'compliance_master_missing_sidebar_count';

    /** @var list<string> */
    public const FIELD_KEYS = [
        'battery',
        'wireless',
        'electric',
        'gcc',
        'rohs',
        'blanket',
        'bluetooth',
        'logo',
        'graph',
    ];

    public static function pageName(): string
    {
        return self::PAGE_NAME;
    }

    public static function syncBeforeCalculate(): void
    {
        //
    }

    /**
     * @return array<string, int>
     */
    public static function calculate(): array
    {
        $data = self::emptyCounts();
        ProductMaster::query()
            ->select(['id', 'sku', 'parent', 'Values'])
            ->orderBy('id')
            ->chunkById(400, function ($chunk) use (&$data) {
                foreach ($chunk as $product) {
                    $values = is_array($product->Values) ? $product->Values : [];
                    self::accumulateRow(
                        $data,
                        (string) ($product->sku ?? ''),
                        (string) ($product->parent ?? ''),
                        $values
                    );
                }
            });

        return $data;
    }

    /**
     * @return array<string, int>
     */
    public static function emptyCounts(): array
    {
        $data = ['missing_any' => 0];
        foreach (self::FIELD_KEYS as $key) {
            $data['missing_'.$key] = 0;
        }

        return $data;
    }

    /**
     * @param  array<string, int>  $data
     * @param  array<string, mixed>  $values
     */
    public static function accumulateRow(array &$data, string $sku, string $parent, array $values): void
    {
        $isParent = str_contains(strtoupper($sku), 'PARENT')
            || str_contains(strtoupper($parent), 'PARENT');
        $any = false;
        foreach (self::FIELD_KEYS as $key) {
            if (self::isFieldReq($values, $key)) {
                $data['missing_'.$key]++;
                $any = true;
            }
        }
        if ($any && ! $isParent) {
            $data['missing_any']++;
        }
    }

    public static function cachedMissingSkuCount(): int
    {
        try {
            return (int) Cache::remember(self::CACHE_KEY, 300, fn () => (int) (self::calculate()['missing_any'] ?? 0));
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function missingSkuCount(): int
    {
        return (int) (self::calculate()['missing_any'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function rowIsMissingAny(array $values): bool
    {
        foreach (self::FIELD_KEYS as $key) {
            if (self::isFieldReq($values, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Same rules as isReqFilterMatchForItem() on compliance-master.
     *
     * @param  array<string, mixed>  $values
     */
    public static function isFieldReq(array $values, string $key): bool
    {
        return strtoupper(trim((string) ($values[$key] ?? ''))) === 'REQ';
    }
}
