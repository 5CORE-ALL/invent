<?php

namespace App\Console\Commands;

use App\Http\Controllers\Sales\AmazonSalesController;
use App\Models\AmazonOrder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only: show why /all-marketplace-master Amazon Y Sales / L30 differ from Seller Central.
 */
class DiagnoseAmazonSellerCentralSales extends Command
{
    protected $signature = 'amazon:diagnose-seller-central
        {--date= : Pacific Y-m-d to treat as Y Sales (default: yesterday PT)}';

    protected $description = 'Compare Amazon Y Sales / L30 (stored price vs Ordered product sales) to Seller Central';

    public function handle(): int
    {
        $tz = 'America/Los_Angeles';
        $nowPt = Carbon::now($tz);
        $day = $this->option('date')
            ? Carbon::parse((string) $this->option('date'), $tz)->startOfDay()
            : Carbon::yesterday($tz);

        $yStart = $day->copy()->startOfDay();
        $yEnd = $day->copy()->endOfDay();
        $l30Start = $day->copy()->subDays(AmazonSalesController::DAILY_SALES_WINDOW_DAYS - 1)->startOfDay();
        $l30End = $day->copy()->endOfDay();

        $this->info(str_repeat('=', 76));
        $this->info('Amazon vs Seller Central — Y Sales / L30');
        $this->line('APP_TZ='.config('app.timezone').' MODE='.AmazonOrder::salesTotalMode());
        $this->line('Now PT: '.$nowPt->toDateTimeString());
        $this->line('Y Sales day (PT): '.$day->toDateString());
        $this->line('Y window UTC: '.AmazonOrder::storedOrderDateBound($yStart).' → '.AmazonOrder::storedOrderDateBound($yEnd));
        $this->line('L30 window UTC: '.AmazonOrder::storedOrderDateBound($l30Start).' → '.AmazonOrder::storedOrderDateBound($l30End));
        $this->newLine();

        $this->info('Sample order_date vs PurchaseDate (are we storing UTC clock?)');
        $samples = DB::table('amazon_orders')->whereNotNull('order_date')->orderByDesc('id')->limit(6)->get(['id', 'order_date', 'status', 'total_amount', 'currency', 'raw_data']);
        foreach ($samples as $s) {
            $raw = is_string($s->raw_data) ? json_decode($s->raw_data, true) : (array) $s->raw_data;
            $pd = $raw['PurchaseDate'] ?? $raw['purchaseDate'] ?? '?';
            $mp = $raw['MarketplaceId'] ?? $raw['marketplaceId'] ?? '?';
            $this->line("  id={$s->id} order_date={$s->order_date} PurchaseDate={$pd} mp={$mp} {$s->status} {$s->currency} {$s->total_amount}");
        }
        $this->newLine();

        $yStored = AmazonOrder::storedLinePriceSumByOrderDate($yStart, $yEnd);
        $yProduct = AmazonOrder::productSalesByOrderDate($yStart, $yEnd);
        $l30Stored = AmazonOrder::storedLinePriceSumByOrderDate($l30Start, $l30End);
        $l30Product = AmazonOrder::productSalesByOrderDate($l30Start, $l30End);

        $yShip = $this->sumJsonMoney($yStart, $yEnd, 'ShippingPrice');
        $yGift = $this->sumJsonMoney($yStart, $yEnd, 'GiftWrapPrice');
        $yItem = $this->sumJsonMoney($yStart, $yEnd, 'ItemPrice');
        $yPromo = $this->sumJsonMoney($yStart, $yEnd, 'PromotionDiscount');
        $yCurrencies = $this->currencyBreakdown($yStart, $yEnd);

        $this->table(
            ['Metric', 'Stored i.price (OLD software)', 'Ordered product sales (FIX)', 'Delta'],
            [
                ['Y Sales '.$day->toDateString(), $this->money($yStored), $this->money($yProduct), $this->money($yStored - $yProduct)],
                ['L30 '.$l30Start->toDateString().'–'.$l30End->toDateString(), $this->money($l30Stored), $this->money($l30Product), $this->money($l30Stored - $l30Product)],
            ]
        );

        $this->info('Y Sales raw_data breakdown (same Pacific day, UTC bounds)');
        $this->line('  ItemPrice='.$this->money($yItem).' PromotionDiscount='.$this->money($yPromo));
        $this->line('  ShippingPrice='.$this->money($yShip).' GiftWrap='.$this->money($yGift));
        $this->line('  ItemPrice − promo = '.$this->money($yItem - $yPromo).'  ← Seller Central "Ordered product sales"');
        $this->line('  Currencies: '.json_encode($yCurrencies));
        $this->newLine();

        $this->comment('Seller Central screenshots you compared:');
        $this->comment('  Aug 26 Sales tile: $2,743.61');
        $this->comment('  Jul 27–Aug 25 custom 30d (MX/BR/CA/US): $87,270.14');
        $this->comment('Software L30 is 30 complete PT days through yesterday — not that custom range.');
        $this->newLine();
        $this->info('Loophole: FetchAmazonOrders adds ShippingPrice + GiftWrapPrice into amazon_order_items.price,');
        $this->info('and /all-marketplace-master summed i.price. Amazon Sales = product only.');

        return self::SUCCESS;
    }

    private function sumJsonMoney(Carbon $start, Carbon $end, string $pascal): float
    {
        $rows = AmazonOrder::constrainOrderDate(
            DB::table('amazon_orders as o')
                ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
                ->where(function ($q) {
                    $q->whereNull('o.status')->orWhereNotIn('o.status', ['Canceled', 'Cancelled']);
                })
                ->select('i.raw_data'),
            $start,
            $end
        )->get();

        $sum = 0.0;
        foreach ($rows as $row) {
            $raw = AmazonOrder::decodeRawPayload($row->raw_data);
            $camel = lcfirst($pascal);
            $sum += (float) (data_get($raw, $pascal.'.Amount') ?? data_get($raw, $camel.'.amount') ?? 0);
        }

        return $sum;
    }

    /** @return array<string, float> */
    private function currencyBreakdown(Carbon $start, Carbon $end): array
    {
        $rows = AmazonOrder::constrainOrderDate(
            DB::table('amazon_orders as o')
                ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
                ->where(function ($q) {
                    $q->whereNull('o.status')->orWhereNotIn('o.status', ['Canceled', 'Cancelled']);
                })
                ->select(['o.currency', 'i.price']),
            $start,
            $end
        )->get();

        $out = [];
        foreach ($rows as $row) {
            $cur = $row->currency ?: 'USD';
            $out[$cur] = ($out[$cur] ?? 0) + (float) $row->price;
        }

        return $out;
    }

    private function money(float $n): string
    {
        return '$'.number_format($n, 2);
    }
}
