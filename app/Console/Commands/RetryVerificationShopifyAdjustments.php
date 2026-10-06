<?php

namespace App\Console\Commands;

use App\Http\Controllers\InventoryManagement\VerificationAdjustmentController;
use App\Models\Inventory;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class RetryVerificationShopifyAdjustments extends Command
{
    protected $signature = 'verification:retry-shopify-adjustments
                            {--limit=25 : Max verification rows to retry per run}';

    protected $description = 'Retry verification-adjustment Shopify pushes that are pending or failed with HTTP 429';

    public function handle(VerificationAdjustmentController $controller): int
    {
        if (! Schema::hasTable('inventories') || ! Schema::hasColumn('inventories', 'shopify_adjustment_status')) {
            $this->info('inventories.shopify_adjustment_status is not available.');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $rows = self::retryableQuery()->orderBy('id')->limit($limit)->get();

        if ($rows->isEmpty()) {
            $this->info('No verification Shopify adjustments waiting to retry.');

            return self::SUCCESS;
        }

        $this->info('Retrying '.$rows->count().' verification Shopify adjustment(s).');

        $ok = 0;
        $pending = 0;
        $failed = 0;

        foreach ($rows as $record) {
            $result = $controller->finishShopifyAdjustment($record);
            $status = (string) ($result['shopify_adjustment_status'] ?? 'failed');

            if (($result['success'] ?? false) && in_array($status, ['success', 'na'], true)) {
                $ok++;
                $this->line("  #{$record->id} {$record->sku}: {$status}");
                continue;
            }

            if ($status === 'pending' || ($result['queued'] ?? false)) {
                $pending++;
                $this->line("  #{$record->id} {$record->sku}: still pending");
                continue;
            }

            $failed++;
            $this->warn("  #{$record->id} {$record->sku}: failed");
            Log::warning('verification:retry-shopify-adjustments failed', [
                'inventory_id' => $record->id,
                'sku' => $record->sku,
                'error' => $result['message'] ?? $result['shopify_adjustment_error'] ?? null,
            ]);
        }

        $this->info("Done. success={$ok} pending={$pending} failed={$failed}");

        return self::SUCCESS;
    }

    /**
     * Pending rows and older 429 failures that still have retries left.
     */
    public static function retryableQuery(): Builder
    {
        return Inventory::query()
            ->where('to_adjust', '!=', 0)
            ->where(function (Builder $query) {
                $query->where('shopify_adjustment_status', 'pending')
                    ->orWhere(function (Builder $failed) {
                        $failed->where('shopify_adjustment_status', 'failed')
                            ->where(function (Builder $error) {
                                $error->where('shopify_adjustment_error', 'like', '%429%')
                                    ->orWhere('shopify_adjustment_error', 'like', '%calls per second%')
                                    ->orWhere('shopify_adjustment_error', 'like', '%rate limited%')
                                    ->orWhere('shopify_adjustment_error', 'like', '%Exceeded 2%');
                            });
                    });
            })
            ->where(function (Builder $query) {
                $query->whereNull('shopify_retry_count')
                    ->orWhere('shopify_retry_count', '<', 10);
            });
    }
}
