<?php

namespace App\Console\Commands;

use App\Services\ReverbApiService;
use Illuminate\Console\Command;

class PushPriceToReverb extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reverb:push-price
                            {sku : SKU of the Reverb listing to update}
                            {price : Price amount (e.g. 99.99)}
                            {--dry-run : Resolve listing ID and show what would be updated without calling API}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Push price to a Reverb listing by SKU (uses ReverbApiService)';

    /**
     * Execute the console command.
     */
    public function handle(ReverbApiService $reverb): int
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

        $price = round((float) $priceInput, 2);
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info("Dry run: would update price to \${$price} for SKU: {$sku}");
            $listingId = $reverb->getListingIdBySku($sku);
            if ($listingId === null) {
                $this->warn('No Reverb listing found for this SKU.');
                return Command::SUCCESS;
            }
            $this->info("Found Reverb listing ID: {$listingId}. No API call made.");
            return Command::SUCCESS;
        }

        $this->info("Pushing price \${$price} to Reverb for SKU: {$sku}...");
        $result = $reverb->updatePrice($sku, $price);

        if ($result['success']) {
            $this->info($result['message']);
            return Command::SUCCESS;
        }

        $this->error($result['message']);
        return Command::FAILURE;
    }
}
