<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Http\Controllers\UpdatePriceApiController;
use App\Models\ShopifySku;
use App\Models\Shopifyb2cDataView;
use DB;

class PushShopifyB2CPriceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $sku;
    protected $price;
    protected $attempt;

    public $tries = 5;
    public $backoff = [60, 300, 900, 1800];
    public $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(string $sku, float $price, int $attempt = 1)
    {
        if ($price <= 0) {
            throw new \InvalidArgumentException('Price must be greater than 0');
        }

        $this->sku = strtoupper(trim($sku));
        $this->price = round($price, 2);
        $this->attempt = $attempt;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            Log::info('PushShopifyB2CPriceJob started', [
                'sku' => $this->sku,
                'price' => $this->price,
                'attempt' => $this->attempt,
                'job_attempt' => $this->attempts()
            ]);

            $byNorm = ShopifySku::buildShopifySkuLookupByNormalizedSku([$this->sku]);
            $k = ShopifySku::normalizeSkuForShopifyLookup($this->sku);
            $shopifyRecord = ($k !== '' && isset($byNorm[$k])) ? $byNorm[$k] : ShopifySku::where('sku', $this->sku)->first();

            if (!$shopifyRecord) {
                $this->savePricePushStatus($this->sku, 'shopifyb2c', 'error', $this->price);
                
                Log::error('PushShopifyB2CPriceJob - SKU not found in Shopify', [
                    'sku' => $this->sku
                ]);
                
                $this->fail(new \Exception("SKU: {$this->sku} not found in Shopify"));
                return;
            }

            $variantId = $shopifyRecord->variant_id;

            if (!$variantId) {
                $this->savePricePushStatus($this->sku, 'shopifyb2c', 'error', $this->price);
                
                Log::error('PushShopifyB2CPriceJob - Variant ID is null', [
                    'sku' => $this->sku,
                    'shopify_record' => $shopifyRecord
                ]);
                
                $this->fail(new \Exception("Variant ID not found for SKU: {$this->sku}"));
                return;
            }

            Log::info('PushShopifyB2CPriceJob - Calling Shopify API', [
                'sku' => $this->sku,
                'variant_id' => $variantId,
                'price' => $this->price
            ]);

            $result = UpdatePriceApiController::updateShopifyVariantPrice($variantId, $this->price);

            if ($result['status'] === 'success') {
                $verifiedPrice = $result['verified_price'] ?? $this->price;
                $this->savePricePushStatus($this->sku, 'shopifyb2c', 'pushed', $verifiedPrice);

                Log::info('PushShopifyB2CPriceJob - Success', [
                    'sku' => $this->sku,
                    'variant_id' => $variantId,
                    'price' => $verifiedPrice,
                    'attempt' => $this->attempts()
                ]);
            } else {
                $reason = $result['message'] ?? 'API error';
                
                Log::warning('PushShopifyB2CPriceJob - Failed, will retry', [
                    'sku' => $this->sku,
                    'variant_id' => $variantId,
                    'price' => $this->price,
                    'reason' => $reason,
                    'attempt' => $this->attempts(),
                    'max_tries' => $this->tries
                ]);

                if ($this->attempts() >= $this->tries) {
                    $this->savePricePushStatus($this->sku, 'shopifyb2c', 'error', $this->price);
                }

                throw new \Exception("Shopify B2C price push failed: $reason");
            }

        } catch (\Exception $e) {
            Log::error('PushShopifyB2CPriceJob - Exception', [
                'sku' => $this->sku,
                'price' => $this->price,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
                'max_tries' => $this->tries
            ]);

            if ($this->attempts() >= $this->tries) {
                $this->savePricePushStatus($this->sku, 'shopifyb2c', 'error', $this->price);
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception)
    {
        Log::error('PushShopifyB2CPriceJob - All retries exhausted', [
            'sku' => $this->sku,
            'price' => $this->price,
            'error' => $exception->getMessage()
        ]);

        $this->savePricePushStatus($this->sku, 'shopifyb2c', 'error', $this->price);
    }

    /**
     * Save price push status to shopifyb2c_data_view
     */
    private function savePricePushStatus(string $sku, string $marketplace, string $status, float $price): void
    {
        try {
            $existing = Shopifyb2cDataView::where('sku', $sku)->first();

            if ($existing) {
                DB::table('shopifyb2c_data_view')
                    ->where('sku', $sku)
                    ->update([
                        'value' => $status,
                        'price' => $price,
                        'updated_at' => now()
                    ]);
            } else {
                DB::table('shopifyb2c_data_view')->insert([
                    'sku' => $sku,
                    'value' => $status,
                    'price' => $price,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            Log::info("Shopify B2C push status saved", [
                'sku' => $sku,
                'status' => $status,
                'price' => $price
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to save Shopify B2C push status", [
                'sku' => $sku,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
        }
    }
}
