<?php

namespace App\Console\Commands;

use App\Models\NeweggItem;
use App\Models\NeweggOrderItem;
use App\Models\NeweggPricing;
use App\Services\NeweggApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FetchNeweggItemData extends Command
{
    /**
     * Fetch Newegg item inventory + price and store them.
     * Price + inventory are stored together in the newegg_pricing table.
     *
     *   php artisan newegg:item-data --sku=ABC123
     *   php artisan newegg:item-data --skus=ABC123,DEF456 --save
     *   php artisan newegg:item-data --save                 (all SKUs seen in newegg orders)
     */
    protected $signature = 'newegg:item-data
        {--sku= : A single seller part number to fetch}
        {--skus= : Comma-separated list of seller part numbers}
        {--source=auto : Where to pull the SKU list from: auto, catalog (newegg_items), or orders (newegg_order_items)}
        {--country=USA : Destination country code for price (ISO 3-letter)}
        {--save : Persist results to the database}
        {--only-missing : Only process SKUs that do not yet have a selling_price in newegg_pricing}
        {--chunk=100 : SKUs per Get Batch Price / Get Batch Inventory call (max 100)}
        {--sleep=0 : Milliseconds to wait between batches}
        {--raw : Print raw JSON for each batch}';

    protected $description = 'Fetch Newegg item price + inventory from the API into the newegg_pricing table';

    public function handle(NeweggApiService $newegg): int
    {
        $country = strtoupper((string) $this->option('country'));
        $skus = $this->resolveSkus();

        if (empty($skus)) {
            $this->warn('No SKUs to process. Pass --sku=, --skus=, build the catalog (newegg:items --save), or save Newegg orders first (newegg:orders --save).');
            return self::FAILURE;
        }

        $chunkSize = min(100, max((int) $this->option('chunk'), 1));
        $chunks    = array_chunk($skus, $chunkSize);
        $started   = microtime(true);

        $this->info('Fetching Newegg item data for ' . count($skus) . ' SKU(s)...');
        $this->line('  SellerID: ' . (config('services.newegg.seller_id') ?: '(not set)'));
        $this->line('  Country:  ' . $country);
        $this->line('  Mode:     batch (' . $chunkSize . ' SKUs/request, ' . count($chunks) . ' batch(es))');
        $this->newLine();

        $sleepMs   = max((int) $this->option('sleep'), 0);
        $rows      = [];
        $saved     = 0;
        $tally     = [];
        $withPrice = 0;
        $verbose   = count($skus) <= 50;

        foreach ($chunks as $batchIndex => $chunk) {
            $this->line(sprintf(
                '  Batch %d/%d (%d SKU%s)...',
                $batchIndex + 1,
                count($chunks),
                count($chunk),
                count($chunk) === 1 ? '' : 's'
            ));

            $priceRes = $this->callBatch(fn () => $newegg->getBatchPrice($chunk, [$country]));
            $invRes   = $this->callBatch(fn () => $newegg->getBatchInventory($chunk));

            if ($priceRes['blocked_by_cloudflare'] || $invRes['blocked_by_cloudflare']) {
                $this->error('Blocked by Cloudflare. Run this from a Newegg-whitelisted server.');
                return self::FAILURE;
            }

            if ($this->option('raw')) {
                $this->line('  price:     ' . json_encode($priceRes['json'], JSON_UNESCAPED_SLASHES));
                $this->line('  inventory: ' . json_encode($invRes['json'], JSON_UNESCAPED_SLASHES));
            }

            $priceBySku = $newegg->indexBatchItemsBySellerPartNumber(
                $newegg->extractBatchItemList($priceRes['json'])
            );
            $invBySku = $newegg->indexBatchItemsBySellerPartNumber(
                $newegg->extractBatchItemList($invRes['json'])
            );

            $priceBatchErr = ($priceRes['ok'] ?? false) ? null : ($priceRes['error'] ?: $this->extractError($priceRes['json']) ?: ('HTTP '.($priceRes['status'] ?? 0)));
            $invBatchErr   = ($invRes['ok'] ?? false) ? null : ($invRes['error'] ?: $this->extractError($invRes['json']) ?: ('HTTP '.($invRes['status'] ?? 0)));

            foreach ($chunk as $sku) {
                $key       = $newegg->batchSellerPartKey($sku);
                $priceItem = $priceBySku[$key] ?? null;
                $invItem   = $invBySku[$key] ?? null;

                $price    = $priceItem ? $newegg->extractPriceRowForCountry($priceItem, $country) : null;
                $inv      = $invItem ? $newegg->extractInventoryRowForCountry($invItem, $country) : null;
                $priceErr = $price !== null ? null : ($priceBatchErr ?: ($priceItem ? 'no country row' : 'no data'));

                $priceStatus = $price !== null ? 'ok' : ($priceErr ?: 'no data');
                $tally[$priceStatus] = ($tally[$priceStatus] ?? 0) + 1;
                if ($price !== null) {
                    $withPrice++;
                }

                if ($verbose) {
                    $rows[] = [
                        $sku,
                        $inv['AvailableQuantity'] ?? ($invBatchErr ?: '—'),
                        isset($inv['Active']) ? (string) $inv['Active'] : '—',
                        $price['SellingPrice'] ?? '—',
                        $price['MAP'] ?? '—',
                        $price['Currency'] ?? '—',
                        $priceStatus,
                    ];
                }

                if ($this->option('save') && ($price !== null || $inv !== null)) {
                    $itemNumber = $priceItem['ItemNumber'] ?? ($inv['ItemNumber'] ?? null);

                    NeweggPricing::updateOrCreate(
                        ['seller_part_number' => $sku, 'country_code' => $price['CountryCode'] ?? $country],
                        [
                            'newegg_item_number'   => $itemNumber,
                            'currency'             => $price['Currency'] ?? null,
                            'active'               => $price['Active'] ?? null,
                            'msrp'                 => $this->num($price['MSRP'] ?? null),
                            'map'                  => $this->num($price['MAP'] ?? null),
                            'checkout_map'         => $price['CheckoutMAP'] ?? null,
                            'selling_price'        => $this->num($price['SellingPrice'] ?? null),
                            'enable_free_shipping' => $price['EnableFreeShipping'] ?? null,
                            'on_promotion'         => $price['OnPromotion'] ?? null,
                            'limit_quantity'       => $price['LimitQuantity'] ?? null,
                            'available_quantity'   => $inv['AvailableQuantity'] ?? null,
                            'fulfillment_option'   => $inv['FulfillmentOption'] ?? null,
                            'inventory_active'     => $inv['Active'] ?? null,
                            'warehouse_allocation' => $inv['WarehouseAllocation'] ?? null,
                            'price_raw_json'       => $priceItem,
                            'inventory_raw_json'   => $invItem,
                        ]
                    );
                    $saved++;
                }
            }

            if ($sleepMs > 0 && $batchIndex < count($chunks) - 1) {
                usleep($sleepMs * 1000);
            }
        }

        $elapsed = microtime(true) - $started;

        $this->newLine();
        if ($verbose && !empty($rows)) {
            $this->table(['SKU', 'Avail Qty', 'Active', 'Selling', 'MAP', 'Cur', 'Price Status'], $rows);
            $this->newLine();
        }

        $this->info('Price status breakdown:');
        ksort($tally);
        foreach ($tally as $status => $n) {
            $this->line(sprintf('  %-40s %d', $status, $n));
        }
        $this->line(sprintf('  %-40s %d / %d', 'TOTAL with price', $withPrice, count($skus)));
        $this->line(sprintf('  %-40s %s', 'Elapsed', $this->formatDuration($elapsed)));

        $this->newLine();
        if ($this->option('save')) {
            $this->info("Saved/updated {$saved} rows in newegg_pricing.");
        } else {
            $this->comment('Use --save to persist into newegg_pricing.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  callable(): array{ok:bool,status:int,blocked_by_cloudflare:bool,json:?array,raw:string,error:?string}  $fn
     * @return array{ok:bool,status:int,blocked_by_cloudflare:bool,json:?array,raw:string,error:?string}
     */
    private function callBatch(callable $fn): array
    {
        $res = $fn();
        if (($res['ok'] ?? false) || ! empty($res['blocked_by_cloudflare'])) {
            return $res;
        }

        return $fn();
    }

    private function formatDuration(float $seconds): string
    {
        if ($seconds < 60) {
            return sprintf('%.1fs', $seconds);
        }

        $mins = (int) floor($seconds / 60);
        $secs = $seconds - ($mins * 60);

        return sprintf('%dm %.1fs', $mins, $secs);
    }

    /** @return list<string> */
    private function resolveSkus(): array
    {
        if ($this->option('sku')) {
            return [trim((string) $this->option('sku'))];
        }

        if ($this->option('skus')) {
            return collect(explode(',', (string) $this->option('skus')))
                ->map(fn ($s) => trim($s))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        $source = strtolower((string) $this->option('source'));
        $skus   = [];

        // catalog = all listed SKUs from the Item Basic Info report (newegg_items).
        if ($source === 'catalog' || $source === 'auto') {
            $skus = NeweggItem::query()
                ->whereNotNull('seller_part_number')
                ->where('seller_part_number', '!=', '')
                ->distinct()
                ->pluck('seller_part_number')
                ->all();
        }

        // Fallback (auto with empty catalog) / orders: SKUs seen in Newegg orders.
        if (empty($skus) && $source !== 'catalog') {
            $skus = NeweggOrderItem::query()
                ->whereNotNull('seller_part_number')
                ->where('seller_part_number', '!=', '')
                ->distinct()
                ->pluck('seller_part_number')
                ->all();
        }

        // Optionally skip SKUs that already have a price stored.
        if ($this->option('only-missing') && !empty($skus)) {
            $priced = NeweggPricing::whereNotNull('selling_price')
                ->pluck('seller_part_number')
                ->flip();

            $skus = array_values(array_filter($skus, fn ($s) => !isset($priced[$s])));
        }

        return $skus;
    }

    /**
     * Strip Newegg's response envelope(s). Depending on the endpoint the real
     * payload may sit at the top level, or be nested under NeweggAPIResponse /
     * ResponseBody / PriceResult / InventoryResult. Descend through whichever
     * wrappers are present and return the inner object.
     *
     * @param  array<mixed>|null  $json
     * @return array<string,mixed>|null
     */
    private function unwrap(?array $json): ?array
    {
        if (!is_array($json) || array_is_list($json)) {
            return null;
        }

        $wrappers = ['NeweggAPIResponse', 'ResponseBody', 'PriceResult', 'InventoryResult'];

        // Descend repeatedly while the only meaningful content is a known wrapper.
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($wrappers as $key) {
                if (isset($json[$key]) && is_array($json[$key]) && !array_is_list($json[$key])) {
                    $json = $json[$key];
                    $changed = true;
                    break;
                }
            }
        }

        return $json;
    }

    /**
     * Pull an error code/message if Newegg returned one (errors come back as a
     * top-level list, or sometimes nested under an Errors key).
     *
     * @param  array<mixed>|null  $json
     */
    private function extractError(?array $json): ?string
    {
        if (!is_array($json)) {
            return null;
        }

        $errors = $json;
        if (!array_is_list($json)) {
            $errors = data_get($json, 'Errors.Error') ?? data_get($json, 'Errors') ?? [];
            if (is_array($errors) && !array_is_list($errors)) {
                $errors = [$errors];
            }
        }

        $first = $errors[0] ?? null;
        if (!is_array($first)) {
            return null;
        }

        $code = data_get($first, 'Code');
        $msg  = data_get($first, 'Message');

        return trim(($code ? "{$code} " : '') . ($msg ?? '')) ?: null;
    }

    /**
     * Resolve the inventory payload regardless of envelope nesting.
     *
     * @param  array<mixed>|null  $json
     * @return array<string,mixed>|null
     */
    private function extractInventory(?array $json): ?array
    {
        $obj = $this->unwrap($json);
        if ($obj === null) {
            return null;
        }

        // Must look like an inventory result, not just an empty envelope.
        if (!array_key_exists('AvailableQuantity', $obj)
            && !array_key_exists('SellerPartNumber', $obj)
            && !array_key_exists('ItemNumber', $obj)) {
            return null;
        }

        return $obj;
    }

    private function num($value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
