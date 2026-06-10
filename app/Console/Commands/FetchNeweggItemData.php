<?php

namespace App\Console\Commands;

use App\Models\NeweggItemInventory;
use App\Models\NeweggItemPrice;
use App\Models\NeweggOrderItem;
use App\Services\NeweggApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FetchNeweggItemData extends Command
{
    /**
     * Fetch Newegg item inventory + price and store them.
     * Price goes into its own table (newegg_item_prices); inventory into newegg_item_inventory.
     *
     *   php artisan newegg:item-data --sku=ABC123
     *   php artisan newegg:item-data --skus=ABC123,DEF456 --save
     *   php artisan newegg:item-data --save                 (all SKUs seen in newegg orders)
     */
    protected $signature = 'newegg:item-data
        {--sku= : A single seller part number to fetch}
        {--skus= : Comma-separated list of seller part numbers}
        {--country=USA : Destination country code for price (ISO 3-letter)}
        {--save : Persist results to the database}
        {--sleep=200 : Milliseconds to wait between SKUs (rate limiting)}
        {--raw : Print raw JSON for each SKU}';

    protected $description = 'Fetch Newegg item inventory and price from the API (price stored in its own table)';

    public function handle(NeweggApiService $newegg): int
    {
        $country = strtoupper((string) $this->option('country'));
        $skus = $this->resolveSkus();

        if (empty($skus)) {
            $this->warn('No SKUs to process. Pass --sku=, --skus=, or save Newegg orders first (newegg:orders --save).');
            return self::FAILURE;
        }

        $this->info('Fetching Newegg item data for ' . count($skus) . ' SKU(s)...');
        $this->line('  SellerID: ' . (config('services.newegg.seller_id') ?: '(not set)'));
        $this->line('  Country:  ' . $country);
        $this->newLine();

        $sleepMs   = max((int) $this->option('sleep'), 0);
        $rows      = [];
        $savedInv  = 0;
        $savedPrice = 0;

        foreach ($skus as $sku) {
            $invRes   = $newegg->getItemInventory($sku);
            $priceRes = $newegg->getItemPrice($sku, [$country]);

            if ($invRes['blocked_by_cloudflare'] || $priceRes['blocked_by_cloudflare']) {
                $this->error('Blocked by Cloudflare. Run this from a Newegg-whitelisted server.');
                return self::FAILURE;
            }

            $inv   = $this->extractObject($invRes['json']);
            $price = $this->extractPriceForCountry($priceRes['json'], $country);

            if ($this->option('raw')) {
                $this->line("SKU {$sku} inventory: " . json_encode($invRes['json'], JSON_UNESCAPED_SLASHES));
                $this->line("SKU {$sku} price:     " . json_encode($priceRes['json'], JSON_UNESCAPED_SLASHES));
            }

            $rows[] = [
                $sku,
                $inv['AvailableQuantity'] ?? '—',
                isset($inv['Active']) ? (string) $inv['Active'] : '—',
                $price['SellingPrice'] ?? '—',
                $price['MAP'] ?? '—',
                $price['Currency'] ?? '—',
            ];

            if ($this->option('save')) {
                if ($inv !== null) {
                    NeweggItemInventory::updateOrCreate(
                        ['seller_part_number' => $sku],
                        [
                            'newegg_item_number'   => $inv['ItemNumber'] ?? null,
                            'active'               => $inv['Active'] ?? null,
                            'fulfillment_option'   => $inv['FulfillmentOption'] ?? null,
                            'available_quantity'   => $inv['AvailableQuantity'] ?? null,
                            'warehouse_allocation' => $inv['WarehouseAllocation'] ?? null,
                            'raw_json'             => $inv,
                        ]
                    );
                    $savedInv++;
                }

                if ($price !== null) {
                    NeweggItemPrice::updateOrCreate(
                        ['seller_part_number' => $sku, 'country_code' => $price['CountryCode'] ?? $country],
                        [
                            'newegg_item_number'   => data_get($priceRes['json'], 'SellerPartNumber') ? data_get($priceRes['json'], 'ItemNumber') : null,
                            'currency'             => $price['Currency'] ?? null,
                            'active'               => $price['Active'] ?? null,
                            'msrp'                 => $this->num($price['MSRP'] ?? null),
                            'map'                  => $this->num($price['MAP'] ?? null),
                            'checkout_map'         => $price['CheckoutMAP'] ?? null,
                            'selling_price'        => $this->num($price['SellingPrice'] ?? null),
                            'enable_free_shipping' => $price['EnableFreeShipping'] ?? null,
                            'on_promotion'         => $price['OnPromotion'] ?? null,
                            'limit_quantity'       => $price['LimitQuantity'] ?? null,
                            'raw_json'             => $price,
                        ]
                    );
                    $savedPrice++;
                }
            }

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        $this->newLine();
        $this->table(['SKU', 'Avail Qty', 'Active', 'Selling', 'MAP', 'Cur'], $rows);

        if ($this->option('save')) {
            $this->newLine();
            $this->info("Saved {$savedInv} inventory rows and {$savedPrice} price rows.");
        } else {
            $this->newLine();
            $this->comment('Use --save to persist into newegg_item_inventory / newegg_item_prices.');
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

        // Default: every distinct seller part number we have seen in Newegg orders.
        return NeweggOrderItem::query()
            ->whereNotNull('seller_part_number')
            ->where('seller_part_number', '!=', '')
            ->distinct()
            ->pluck('seller_part_number')
            ->all();
    }

    /**
     * Newegg returns errors as a top-level list. A valid result is an object.
     *
     * @param  array<mixed>|null  $json
     * @return array<string,mixed>|null
     */
    private function extractObject(?array $json): ?array
    {
        if (!is_array($json) || array_is_list($json)) {
            return null;
        }

        return $json;
    }

    /**
     * Pull the price row for a given country out of the PriceResult.PriceList.Price array.
     *
     * @param  array<mixed>|null  $json
     * @return array<string,mixed>|null
     */
    private function extractPriceForCountry(?array $json, string $country): ?array
    {
        $obj = $this->extractObject($json);
        if ($obj === null) {
            return null;
        }

        $prices = data_get($obj, 'PriceList.Price', []);
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

    private function num($value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
