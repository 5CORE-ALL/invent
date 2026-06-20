<?php

namespace App\Console\Commands;

use App\Models\NeweggPricing;
use App\Services\NeweggApiService;
use Illuminate\Console\Command;

class PushPriceToNewegg extends Command
{
    /**
     * Mirrors reverb:push-price. Pushes a single SKU price to the Newegg
     * Marketplace API via NeweggApiService::updateItemPrice().
     *
     * Examples:
     *   php artisan newegg:push-price "MS RBL HND CLCH" 31.99
     *   php artisan newegg:push-price "MS RBL HND CLCH" 31.99 --dry-run
     *
     * --as-spn forces the SKU argument to be treated as the Newegg
     * SellerPartNumber directly (skip the local newegg_pricing lookup).
     */
    protected $signature = 'newegg:push-price
                            {sku : Local SKU or Newegg SellerPartNumber}
                            {price : Price amount (e.g. 31.99)}
                            {--country=USA : ISO 3-letter country code (defaults to USA)}
                            {--currency=USD : Currency code (defaults to USD)}
                            {--as-spn : Treat the SKU argument as the Newegg SellerPartNumber as-is}
                            {--dry-run : Resolve SPN and show what would be sent without calling the API}';

    protected $description = 'Push price to a Newegg listing by SKU (uses NeweggApiService)';

    public function handle(NeweggApiService $newegg): int
    {
        $sku = trim((string) $this->argument('sku'));
        $priceInput = $this->argument('price');

        if ($sku === '') {
            $this->error('SKU is required.');
            return Command::FAILURE;
        }
        if (!is_numeric($priceInput) || (float) $priceInput <= 0) {
            $this->error('Price must be a positive number.');
            return Command::FAILURE;
        }

        $price    = round((float) $priceInput, 2);
        $country  = strtoupper((string) $this->option('country'));
        $currency = strtoupper((string) $this->option('currency'));
        $dryRun   = (bool) $this->option('dry-run');
        $asSpn    = (bool) $this->option('as-spn');

        $spn = $asSpn ? $sku : $this->resolveSpn($sku);
        if ($spn === null) {
            $this->error("No Newegg SellerPartNumber found for local SKU: {$sku}. (Pass --as-spn to push without lookup.)");
            return Command::FAILURE;
        }

        if ($dryRun) {
            $this->info("Dry run: would PUT price \${$price} {$currency} to Newegg ({$country}) for SPN: {$spn}");
            return Command::SUCCESS;
        }

        $this->info("Pushing price \${$price} {$currency} to Newegg ({$country}) for SPN: {$spn}...");
        $result = $newegg->updateItemPrice($spn, $price, $currency, $country);

        if ($result['success']) {
            $this->info($result['message']);
            return Command::SUCCESS;
        }

        $this->error($result['message']);
        if (!empty($result['raw'])) {
            $this->line('---- Newegg response ----');
            $this->line(substr((string) $result['raw'], 0, 1000));
        }
        return Command::FAILURE;
    }

    /**
     * Map a local SKU to the Newegg SellerPartNumber using the same
     * special-char-insensitive normalization the pricing controller uses.
     */
    private function resolveSpn(string $sku): ?string
    {
        $norm = $this->normalizeSkuKey($sku);
        if ($norm === '') {
            return null;
        }

        foreach (NeweggPricing::query()->select('seller_part_number')->get() as $row) {
            if ($this->normalizeSkuKey((string) $row->seller_part_number) === $norm) {
                return (string) $row->seller_part_number;
            }
        }

        return null;
    }

    private function normalizeSkuKey(?string $sku): string
    {
        if ($sku === null || $sku === '') {
            return '';
        }
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $sku));
    }
}
