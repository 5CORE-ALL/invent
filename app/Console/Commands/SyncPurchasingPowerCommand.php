<?php

namespace App\Console\Commands;

use App\Models\PurchasingPowerSale;
use App\Services\PurchasingPowerApiService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class SyncPurchasingPowerCommand extends Command
{
    protected $signature = 'purchasing-power:sync
        {--prices : Sync listed price + stock from MCM OF21 into purchasing_power_products}
        {--orders : Sync orders from MCM OR11 into purchasing_power_sales}
        {--days=60 : Days of orders to pull (OR11 start_date window)}';

    protected $description = 'Sync Purchasing Power MCM prices (OF21) and/or orders (OR11)';

    public function handle(PurchasingPowerApiService $ppApi): int
    {
        $doPrices = (bool) $this->option('prices');
        $doOrders = (bool) $this->option('orders');

        // Default: both
        if (! $doPrices && ! $doOrders) {
            $doPrices = true;
            $doOrders = true;
        }

        $ok = true;

        if ($doPrices) {
            $this->info('=== Purchasing Power prices/stock (OF21) ===');
            $exit = Artisan::call('app:fetch-macy-products', ['--pp-mcm-only' => true]);
            $this->output->write(Artisan::output());
            if ($exit !== self::SUCCESS) {
                $ok = false;
                $this->error('Price sync failed.');
            }
        }

        if ($doOrders) {
            $this->info('=== Purchasing Power orders (OR11) ===');
            try {
                $days = max(1, (int) $this->option('days'));
                $end = Carbon::now('America/Los_Angeles')->endOfDay();
                $start = Carbon::now('America/Los_Angeles')->subDays($days - 1)->startOfDay();

                $this->info("Fetching orders {$start->toDateString()} → {$end->toDateString()}...");
                $result = $ppApi->fetchOrders($start, $end);
                $orders = $result['orders'] ?? [];
                $lines = $ppApi->flattenOrdersToLineRows($orders);
                $this->info('API orders: '.count($orders).' | line rows: '.count($lines));

                $upserted = 0;
                foreach (array_chunk($lines, 100) as $chunk) {
                    foreach ($chunk as $line) {
                        $orderId = (string) ($line->order_id ?? $line->order_number ?? '');
                        $sku = trim((string) ($line->sku ?? ''));
                        if ($orderId === '' || $sku === '') {
                            continue;
                        }

                        $nameParts = preg_split('/\s+/', trim((string) ($line->customer ?? '')), 2) ?: [];
                        $firstName = $nameParts[0] ?? null;
                        $lastName = $nameParts[1] ?? null;

                        $dateCreated = null;
                        if (! empty($line->order_date)) {
                            try {
                                $dateCreated = Carbon::parse($line->order_date)->utc();
                            } catch (\Throwable $e) {
                                $dateCreated = null;
                            }
                        }

                        PurchasingPowerSale::updateOrCreate(
                            [
                                'order_id' => $orderId,
                                'offer_sku' => $sku,
                            ],
                            [
                                'order_number' => $line->order_number ?? $orderId,
                                'date_created' => $dateCreated,
                                'quantity' => (int) ($line->quantity ?? 0),
                                'product_name' => $line->product_name ?? null,
                                'status' => $line->status ?? null,
                                'amount' => $line->amount ?? null,
                                'currency' => 'USD',
                                'product_sku' => $line->mirakl_product_sku ?? null,
                                'unit_price' => $line->unit_price ?? null,
                                'shipping_price' => 0,
                                'commission_excl_tax' => $line->commission ?? 0,
                                'commission_incl_tax' => $line->commission ?? 0,
                                'amount_transferred' => $line->amount_transferred ?? 0,
                                'shipping_company' => $line->shipping_company ?? null,
                                'tracking_number' => $line->tracking_number ?? null,
                                'tracking_url' => $line->tracking_url ?? null,
                                'customer_first_name' => $firstName,
                                'customer_last_name' => $lastName,
                                'customer_city' => $line->city ?? null,
                                'customer_state' => $line->state ?? null,
                                'customer_country' => $line->country ?? null,
                                'category_label' => $line->category_label ?? null,
                            ]
                        );
                        $upserted++;
                    }
                }

                $this->info("Orders sync complete. Upserted: {$upserted}");
            } catch (\Throwable $e) {
                $ok = false;
                $this->error('Orders sync failed: '.$e->getMessage());
                Log::error('purchasing-power:sync orders failed', ['error' => $e->getMessage()]);
            }
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
