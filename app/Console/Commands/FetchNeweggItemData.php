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
        {--sleep=200 : Milliseconds to wait between SKUs (rate limiting)}
        {--raw : Print raw JSON for each SKU}';

    protected $description = 'Fetch Newegg item price + inventory from the API into the newegg_pricing table';

    public function handle(NeweggApiService $newegg): int
    {
        $country = strtoupper((string) $this->option('country'));
        $skus = $this->resolveSkus();

        if (empty($skus)) {
            $this->warn('No SKUs to process. Pass --sku=, --skus=, build the catalog (newegg:items --save), or save Newegg orders first (newegg:orders --save).');
            return self::FAILURE;
        }

        $this->info('Fetching Newegg item data for ' . count($skus) . ' SKU(s)...');
        $this->line('  SellerID: ' . (config('services.newegg.seller_id') ?: '(not set)'));
        $this->line('  Country:  ' . $country);
        $this->newLine();

        $sleepMs   = max((int) $this->option('sleep'), 0);
        $rows      = [];
        $saved     = 0;
        $tally     = [];
        $withPrice = 0;
        $verbose   = !$this->option('save') || count($skus) <= 50;

        foreach ($skus as $sku) {
            $invRes   = $newegg->getItemInventory($sku);
            $priceRes = $newegg->getItemPrice($sku, [$country]);

            if ($invRes['blocked_by_cloudflare'] || $priceRes['blocked_by_cloudflare']) {
                $this->error('Blocked by Cloudflare. Run this from a Newegg-whitelisted server.');
                return self::FAILURE;
            }

            $inv      = $this->extractInventory($invRes['json']);
            $invErr   = $this->extractError($invRes['json']);
            $price    = $this->extractPriceForCountry($priceRes['json'], $country);
            $priceErr = $this->extractError($priceRes['json']);
            $priceParent = $this->unwrap($priceRes['json']);

            if ($this->option('raw')) {
                $this->line("SKU {$sku} inventory: " . json_encode($invRes['json'], JSON_UNESCAPED_SLASHES));
                $this->line("SKU {$sku} price:     " . json_encode($priceRes['json'], JSON_UNESCAPED_SLASHES));
            }

            $priceStatus = $price !== null ? 'ok' : ($priceErr ?: 'no data');
            $tally[$priceStatus] = ($tally[$priceStatus] ?? 0) + 1;
            if ($price !== null) {
                $withPrice++;
            }

            if ($verbose) {
                $rows[] = [
                    $sku,
                    $inv['AvailableQuantity'] ?? ($invErr ?: '—'),
                    isset($inv['Active']) ? (string) $inv['Active'] : '—',
                    $price['SellingPrice'] ?? '—',
                    $price['MAP'] ?? '—',
                    $price['Currency'] ?? '—',
                    $priceStatus,
                ];
            }

            // Persist price + inventory together (one row per SKU + country).
            if ($this->option('save') && ($price !== null || $inv !== null)) {
                $itemNumber = data_get($priceParent, 'ItemNumber') ?? ($inv['ItemNumber'] ?? null);

                NeweggPricing::updateOrCreate(
                    ['seller_part_number' => $sku, 'country_code' => $price['CountryCode'] ?? $country],
                    [
                        'newegg_item_number'   => $itemNumber,
                        // price
                        'currency'             => $price['Currency'] ?? null,
                        'active'               => $price['Active'] ?? null,
                        'msrp'                 => $this->num($price['MSRP'] ?? null),
                        'map'                  => $this->num($price['MAP'] ?? null),
                        'checkout_map'         => $price['CheckoutMAP'] ?? null,
                        'selling_price'        => $this->num($price['SellingPrice'] ?? null),
                        'enable_free_shipping' => $price['EnableFreeShipping'] ?? null,
                        'on_promotion'         => $price['OnPromotion'] ?? null,
                        'limit_quantity'       => $price['LimitQuantity'] ?? null,
                        // inventory
                        'available_quantity'   => $inv['AvailableQuantity'] ?? null,
                        'fulfillment_option'   => $inv['FulfillmentOption'] ?? null,
                        'inventory_active'     => $inv['Active'] ?? null,
                        'warehouse_allocation' => $inv['WarehouseAllocation'] ?? null,
                        // raw (store the full API responses for traceability/debugging)
                        'price_raw_json'       => $priceRes['json'],
                        'inventory_raw_json'   => $invRes['json'],
                    ]
                );
                $saved++;
            }

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        $this->newLine();
        if ($verbose && !empty($rows)) {
            $this->table(['SKU', 'Avail Qty', 'Active', 'Selling', 'MAP', 'Cur', 'Price Status'], $rows);
            $this->newLine();
        }

        // Price status breakdown so it's clear why some SKUs have no price.
        $this->info('Price status breakdown:');
        ksort($tally);
        foreach ($tally as $status => $n) {
            $this->line(sprintf('  %-40s %d', $status, $n));
        }
        $this->line(sprintf('  %-40s %d / %d', 'TOTAL with price', $withPrice, count($skus)));

        $this->newLine();
        if ($this->option('save')) {
            $this->info("Saved/updated {$saved} rows in newegg_pricing.");
        } else {
            $this->comment('Use --save to persist into newegg_pricing.');
        }

        return self::SUCCESS;
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

    /**
     * Pull the price row for a given country out of PriceList.Price. The price
     * endpoint may return the result as a top-level list, a single object, or
     * wrapped under NeweggAPIResponse / ResponseBody / PriceResult /
     * ResponseList / ItemPriceResultList — handle them all.
     *
     * @param  array<mixed>|null  $json
     * @return array<string,mixed>|null
     */
    private function extractPriceForCountry(?array $json, string $country): ?array
    {
        foreach ($this->priceResultCandidates($json) as $result) {
            if (!is_array($result)) {
                continue;
            }

            $priceList = data_get($result, 'PriceList');
            if (!is_array($priceList) || empty($priceList)) {
                continue;
            }

            // The live API returns PriceList as a direct array of price rows,
            // but the docs show PriceList.Price — support both.
            if (array_is_list($priceList)) {
                $prices = $priceList;
            } else {
                $prices = data_get($priceList, 'Price', $priceList);
            }

            // A single price comes back as an associative array, not a list.
            if (is_array($prices) && !array_is_list($prices)) {
                $prices = [$prices];
            }

            foreach ($prices as $p) {
                if (strtoupper((string) data_get($p, 'CountryCode')) === $country) {
                    return $p;
                }
            }

            return $prices[0] ?? null;
        }

        return null;
    }

    /**
     * Normalize the many possible price-response envelopes into a flat list of
     * candidate "price result" objects (each expected to carry PriceList.Price).
     *
     * @param  array<mixed>|null  $json
     * @return list<mixed>
     */
    private function priceResultCandidates(?array $json): array
    {
        if (!is_array($json)) {
            return [];
        }

        // Top-level list of results (or errors — those just won't have PriceList).
        if (array_is_list($json)) {
            return $json;
        }

        $obj = $this->unwrap($json) ?? $json;

        // Result(s) may sit under a list wrapper.
        $list = data_get($obj, 'ItemPriceResultList.ItemPriceResult')
            ?? data_get($obj, 'PriceResultList.PriceResult')
            ?? data_get($obj, 'ResponseList');

        if (is_array($list)) {
            return array_is_list($list) ? $list : [$list];
        }

        return [$obj];
    }

    private function num($value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
