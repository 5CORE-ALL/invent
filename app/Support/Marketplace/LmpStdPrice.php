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

    /** @var array<string, string> */
    private static array $preparedSkus = [];

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
        self::$preparedSkus = [];
    }

    /**
     * Load Std Prc only for these SKUs and their LMP link siblings.
     * A full amazon_data_view scan made listing publish exceed the gateway timeout.
     *
     * @param  list<string>  $skus
     */
    private static function warm(array $skus): void
    {
        if (self::$bySku === null) {
            self::$bySku = [];
        }

        $pending = [];
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            $key = self::key($sku);
            if ($key === '' || isset(self::$preparedSkus[$key])) {
                continue;
            }
            $pending[] = $sku;
            self::$preparedSkus[$key] = $sku;
        }
        if ($pending === []) {
            return;
        }

        if (self::$groups === null) {
            self::$groups = app(LmpSkuGroupService::class);
        }
        try {
            self::$groups->prepareForSkus(array_values(self::$preparedSkus));
        } catch (\Throwable $e) {
            Log::warning('LmpStdPrice: SKU link groups failed', ['error' => $e->getMessage()]);
            self::$groups = null;
        }

        $toLoad = $pending;
        if (self::$groups !== null) {
            foreach ($pending as $sku) {
                try {
                    $members = self::$groups->groupContaining($sku);
                } catch (\Throwable $e) {
                    $members = [];
                }
                foreach ($members as $member) {
                    $member = trim((string) $member);
                    $memberKey = self::key($member);
                    if ($member === '' || $memberKey === '') {
                        continue;
                    }
                    self::$preparedSkus[$memberKey] = $member;
                    $toLoad[] = $member;
                }
            }
        }

        self::loadPrices($toLoad);
    }

    /**
     * @param  list<string>  $skus
     */
    private static function loadPrices(array $skus): void
    {
        if (! Schema::hasTable('amazon_data_view')) {
            return;
        }

        $exact = [];
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '') {
                continue;
            }
            $exact[$sku] = true;
            $exact[self::key($sku)] = true;
        }
        $exact = array_values(array_filter(array_keys($exact)));
        if ($exact === []) {
            return;
        }

        try {
            foreach (array_chunk($exact, 200) as $chunk) {
                self::storePrices(AmazonDataView::query()
                    ->select(['id', 'sku', 'value'])
                    ->whereIn('sku', $chunk)
                    ->get());
            }
        } catch (\Throwable $e) {
            Log::warning('LmpStdPrice: Std Prc lookup failed', ['error' => $e->getMessage()]);
        }
    }

    private static function storePrices(Collection $rows): void
    {
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
    }
}
