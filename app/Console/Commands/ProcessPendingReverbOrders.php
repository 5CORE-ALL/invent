<?php

namespace App\Console\Commands;

use App\Jobs\ImportReverbOrderToShopify;
use App\Models\ReverbOrderMetric;
use App\Models\ReverbSyncSettings;
use Illuminate\Console\Command;

class ProcessPendingReverbOrders extends Command
{
    protected $signature = 'reverb:process-pending
                            {--limit=500 : Max number of orders to dispatch}
                            {--force : Ignore import_orders_to_main_store setting}';

    protected $description = 'Dispatch ImportReverbOrderToShopify jobs for pending Reverb orders to the reverb queue';

    public function handle(): int
    {
        $settings = ReverbSyncSettings::getForReverb();
        if (!($this->option('force') || ($settings['order']['import_orders_to_main_store'] ?? false))) {
            $this->warn('Auto-import is disabled. Enable "Import orders to main store" in Reverb Settings, or use --force');
            return self::FAILURE;
        }

        $limit = (int) $this->option('limit');
        $skipShipped = $settings['order']['skip_shipped_orders'] ?? false;

        $query = ReverbOrderMetric::query()
            ->whereNull('shopify_order_id')
            ->whereNotNull('order_paid_at')
            ->orderBy('order_date')
            ->orderBy('id')
            ->limit($limit);

        if ($skipShipped) {
            $query->whereNotIn('status', ['shipped', 'delivered']);
        }

        $orders = $query->get();

        if ($orders->isEmpty()) {
            $this->info('No pending Reverb orders to process.');
            return self::SUCCESS;
        }

        $dispatched = 0;
        foreach ($orders as $order) {
            ImportReverbOrderToShopify::dispatch($order->id)->onQueue('reverb');
            $dispatched++;
        }

        $this->info("Dispatched {$dispatched} orders to reverb queue. Run: php artisan queue:work --queue=reverb");
        return self::SUCCESS;
    }
}
