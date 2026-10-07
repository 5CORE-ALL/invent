<?php

namespace App\Support\Marketplace;

use App\Models\AmazonDataView;
use App\Services\LmpSkuGroupService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Std Prc shown on LMP Overall: amazon_data_view STANDARD_PRICE,
 * shared across Sku Link LMP siblings.
 */
class LmpStdPrice
{
    /** @var array<string, float>|null */
    private static ?array $bySku = null;

    private static ?LmpSkuGroupService $groups = null;

    /**
     * @param  Collection<int, mixed>  $rows
     * @return Collection<int, mixed>
     */
    public static function attach(Collection $rows): Collection
    {
        if ($rows->isEmpty()) {
            return $rows;
        }

        $skus = [];
        foreach ($rows as $row) {
            $sku = trim((string) ($row->sku ?? ''));
            if ($sku !== '') {
                $skus[] = $sku;
            }
        }
        self::warm($skus);

        return $rows->each(function ($row) {
            $row->std_price = self::forSku((string) ($row->sku ?? ''));
        });
    }

    public static function forSku(string $sku): ?float
    {
        self::warm([$sku]);
        $members = [];
        if (self::$groups !== null) {
            try {
                $members = self::$groups->groupContaining($sku);
            } catch (\Throwable $e) {
                $members = [];
            }
        }

        return self::priceFromMap($sku, self::$bySku ?? [], $members);
    }

    /**
     * @param  array<string, float>  $bySku
     * @param  list<string>  $members
     */
    public static function priceFromMap(string $sku, array $bySku, array $members = []): ?float
    {
        $list = $members !== [] ? $members : [$sku];
        foreach ($list as $member) {
            $key = self::key((string) $member);
            if ($key !== '' && isset($bySku[$key])) {
                return $bySku[$key];
            }
        }

        return null;
    }

    public static function key(string $sku): string
    {
        return strtoupper(preg_replace('/\s+/', ' ', trim($sku)) ?? '');
    }

    public static function reset(): void
    {
        self::$bySku = null;
        self::$groups = null;
    }

    /**
     * @param  list<string>  $skus
     */
    private static function warm(array $skus): void
    {
        if (self::$bySku !== null) {
            return;
        }

        self::$bySku = [];
        if (Schema::hasTable('amazon_data_view')) {
            try {
                AmazonDataView::query()
                    ->select(['id', 'sku', 'value'])
                    ->orderBy('id')
                    ->chunkById(1000, function ($rows): void {
                        foreach ($rows as $row) {
                            $key = self::key((string) $row->sku);
                            if ($key === '') {
                                continue;
                            }
                            $val = is_array($row->value) ? $row->value : [];
                            $std = $val['STANDARD_PRICE'] ?? null;
                            if (is_numeric($std) && (float) $std > 0) {
                                self::$bySku[$key] = round((float) $std, 2);
                            }
                        }
                    });
            } catch (\Throwable $e) {
                Log::warning('LmpStdPrice: Std Prc lookup failed', ['error' => $e->getMessage()]);
            }
        }

        self::$groups = app(LmpSkuGroupService::class);
        try {
            self::$groups->prepareForSkus($skus);
        } catch (\Throwable $e) {
            Log::warning('LmpStdPrice: SKU link groups failed', ['error' => $e->getMessage()]);
            self::$groups = null;
        }
    }
}
