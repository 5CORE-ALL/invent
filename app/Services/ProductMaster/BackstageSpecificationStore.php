<?php

namespace App\Services\ProductMaster;

use App\Models\BackstageSpecification;
use App\Models\CategoryMaster;
use App\Models\ProductMaster;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Copies item specifics after a successful marketplace push.
 * Other marketplaces read the latest value for a SKU instead of asking again.
 */
class BackstageSpecificationStore
{
    /** @var array<string, string> */
    private const MARKETPLACE_SLUGS = [
        'ebay' => 'ebay1',
        'ebay1' => 'ebay1',
        'ebayone' => 'ebay1',
        'ebay2' => 'ebay2',
        'ebaytwo' => 'ebay2',
        'ebay3' => 'ebay3',
        'ebaythree' => 'ebay3',
        'temu' => 'temu1',
        'temu1' => 'temu1',
        'temuone' => 'temu1',
        'temutwo' => 'temu2',
        'temuthree' => 'temu3',
        'amz' => 'amazon',
        'amazonfba' => 'amazon',
        'amzfbm' => 'amazon',
    ];

    /**
     * Save non-blank specifics that were just pushed.
     * Accepts a list of {key, value} rows or a name => value map.
     *
     * @param  list<array{key?: string, value?: string}>|array<string, mixed>  $specs
     */
    public function recordPush(string $sku, string $marketplace, array $specs, ?string $category = null): int
    {
        try {
            return $this->write($sku, $marketplace, $specs, $category);
        } catch (\Throwable $e) {
            Log::warning('Backstage specification save failed', [
                'sku' => $sku,
                'marketplace' => $marketplace,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Snapshot the SKU's saved technical specifications onto a marketplace
     * after that marketplace accepted a push. Extra values override same keys.
     *
     * @param  list<string>  $skus
     * @param  array<string, mixed>  $extra
     */
    public function recordProducts(array $skus, string $marketplace, array $extra = []): void
    {
        try {
            if (! Schema::hasTable('backstage_specifications')) {
                return;
            }
            $wanted = [];
            foreach ($skus as $sku) {
                $sku = trim((string) $sku);
                if ($sku !== '') {
                    $wanted[$sku] = $sku;
                }
            }
            if ($wanted === []) {
                return;
            }

            $saved = [];
            if (Schema::hasColumn('product_master', 'description_v2_specifications')) {
                $products = ProductMaster::query()
                    ->whereIn('sku', array_values($wanted))
                    ->get(['sku', 'description_v2_specifications']);
                foreach ($products as $product) {
                    $saved[trim((string) $product->sku)] = $this->rows($product->description_v2_specifications ?? []);
                }
            }

            foreach ($wanted as $sku) {
                $specs = $saved[$sku] ?? [];
                foreach ($this->rows($extra) as $row) {
                    $specs[] = $row;
                }
                $this->write($sku, $marketplace, $specs, null);
            }
        } catch (\Throwable $e) {
            Log::warning('Backstage specification snapshot failed', [
                'marketplace' => $marketplace,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Newest value for each specification name. A value already pushed to
     * $preferMarketplace wins over an older value from another marketplace.
     *
     * @return list<array{key: string, value: string, marketplace: string, category: string, pushed_at: string}>
     */
    public function latestForSku(string $sku, ?string $preferMarketplace = null): array
    {
        $sku = trim($sku);
        if ($sku === '' || ! Schema::hasTable('backstage_specifications')) {
            return [];
        }

        $prefer = $preferMarketplace !== null ? $this->slug($preferMarketplace) : '';
        $rows = BackstageSpecification::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
            ->orderByDesc('pushed_at')
            ->orderByDesc('id')
            ->get(['spec_key', 'spec_key_norm', 'spec_value', 'marketplace', 'category', 'pushed_at']);

        $picked = [];
        foreach ($rows as $row) {
            $norm = (string) $row->spec_key_norm;
            if ($norm === '') {
                continue;
            }
            $candidate = [
                'key' => (string) $row->spec_key,
                'value' => (string) $row->spec_value,
                'marketplace' => (string) $row->marketplace,
                'category' => (string) ($row->category ?? ''),
                'pushed_at' => optional($row->pushed_at)->toDateTimeString() ?? '',
            ];
            if (! isset($picked[$norm])) {
                $picked[$norm] = $candidate;

                continue;
            }
            if ($prefer !== '' && $candidate['marketplace'] === $prefer && $picked[$norm]['marketplace'] !== $prefer) {
                $picked[$norm] = $candidate;
            }
        }

        return array_values($picked);
    }

    /**
     * Required names that have no stored value. These are the only ones to ask for.
     *
     * @param  list<string>  $requiredNames
     * @return list<string>
     */
    public function missingForSku(string $sku, array $requiredNames, ?string $preferMarketplace = null): array
    {
        $have = [];
        foreach ($this->latestForSku($sku, $preferMarketplace) as $row) {
            if (trim($row['value']) !== '') {
                $have[$this->norm($row['key'])] = true;
            }
        }

        $missing = [];
        foreach ($requiredNames as $name) {
            $name = trim((string) $name);
            if ($name === '' || isset($have[$this->norm($name)])) {
                continue;
            }
            $missing[] = $name;
        }

        return $missing;
    }

    /**
     * @param  list<array{key?: string, value?: string}>|array<string, mixed>  $specs
     */
    private function write(string $sku, string $marketplace, array $specs, ?string $category): int
    {
        if (! Schema::hasTable('backstage_specifications')) {
            return 0;
        }

        $sku = trim($sku);
        $marketplace = $this->slug($marketplace);
        if ($sku === '' || $marketplace === 'unknown') {
            return 0;
        }

        $rows = $this->rows($specs);
        if ($rows === []) {
            return 0;
        }

        $category = trim((string) ($category ?? $this->categoryFor($sku, $marketplace)));
        $now = now();
        $payload = [];
        foreach ($rows as $row) {
            $norm = $this->norm($row['key']);
            if ($norm === '' || $row['value'] === '') {
                continue;
            }
            $payload[$norm] = [
                'sku' => $sku,
                'marketplace' => $marketplace,
                'spec_key' => mb_substr($row['key'], 0, 255),
                'spec_key_norm' => mb_substr($norm, 0, 255),
                'spec_value' => $row['value'],
                'category' => $category !== '' ? mb_substr($category, 0, 255) : null,
                'pushed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if ($payload === []) {
            return 0;
        }

        BackstageSpecification::query()->upsert(
            array_values($payload),
            ['sku', 'marketplace', 'spec_key_norm'],
            ['spec_key', 'spec_value', 'category', 'pushed_at', 'updated_at']
        );

        return count($payload);
    }

    /**
     * @param  mixed  $specs
     * @return list<array{key: string, value: string}>
     */
    private function rows(mixed $specs): array
    {
        if (! is_array($specs)) {
            return [];
        }

        $out = [];
        $list = array_is_list($specs);
        foreach ($specs as $key => $row) {
            if ($list && is_array($row)) {
                $name = trim((string) ($row['key'] ?? $row['name'] ?? $row['Key'] ?? ''));
                $value = trim((string) ($row['value'] ?? $row['Value'] ?? ''));
            } elseif (is_string($key) && is_scalar($row)) {
                $name = trim($key);
                $value = trim((string) $row);
            } else {
                continue;
            }
            if ($name === '' || $value === '') {
                continue;
            }
            $out[] = ['key' => $name, 'value' => $value];
        }

        return $out;
    }

    private function categoryFor(string $sku, string $marketplace): string
    {
        if (! Schema::hasTable('category_masters')) {
            return '';
        }

        $column = str_starts_with($marketplace, 'temu')
            ? 'category_temu'
            : (str_starts_with($marketplace, 'amazon') ? 'category_amz' : (str_starts_with($marketplace, 'ebay') ? 'category_ebay' : ''));
        if ($column === '' || ! Schema::hasColumn('category_masters', $column)) {
            return '';
        }

        $value = CategoryMaster::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
            ->value($column);

        return trim((string) $value);
    }

    private function slug(string $marketplace): string
    {
        $compact = strtolower(preg_replace('/[^a-z0-9]+/i', '', $marketplace) ?? '');

        return self::MARKETPLACE_SLUGS[$compact] ?? ($compact !== '' ? $compact : 'unknown');
    }

    private function norm(string $name): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', $name) ?? '');
    }
}
