<?php

namespace App\Console\Commands;

use App\Services\ChannelPushedPricePullService;
use App\Services\ChannelLivePriceSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Same idea as amazon:pull-pushed-prices / alibaba:pull-pushed-prices.
 *
 * A Shein push does not touch the Price column (Shein reviews price changes, so the product list keeps the old
 * price for a while). Without a read-back the page keeps the old Price + blue triangle until the next
 * 10-minute catch-up or daily sync. This reads the live price of every recently pushed SKU whose Price still differs
 * from the pushed value, and keeps doing so every run until it matches (or the window ends).
 */
class PullSheinPushedPricesCommand extends Command
{
    protected $signature = 'shein:pull-pushed-prices
        {--limit=40 : Max SKUs read per run}
        {--window-hours=12 : Only SKUs pushed within this many hours}
        {--delay-ms=250 : Pause between Shein lookups}';

    protected $description = 'Read the live Shein price of recently pushed SKUs into the Price column until it matches the push';

    public function handle(ChannelPushedPricePullService $pull): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $since = now()->subHours(max(1, (int) $this->option('window-hours')))->format('Y-m-d H:i:s');

        $rows = DB::table('shein_data_views')
            ->where('value', 'like', '%"SPRICE_STATUS":"pushed"%')
            ->get(['sku', 'value']);

        $pricesByKey = [];
        foreach (DB::table('shein_pricing_prices')->get(['sku', 'special_offer_price']) as $p) {
            foreach (ChannelLivePriceSync::skuLookupKeys((string) $p->sku) as $k) {
                $pricesByKey[$k] = (float) $p->special_offer_price;
            }
        }

        $due = [];
        $expected = [];
        foreach ($rows as $r) {
            $v = json_decode((string) $r->value, true);
            if (! is_array($v)) {
                continue;
            }
            $pushedAt = (string) ($v['SPRICE_PUSHED_AT'] ?? '');
            $pushed = (float) ($v['SPRICE_PUSHED_VALUE'] ?? 0);
            if ($pushed <= 0 || $pushedAt === '' || $pushedAt < $since) {
                continue;
            }
            $key = strtoupper(trim((string) $r->sku));
            $current = $pricesByKey[$key] ?? null;
            if ($current !== null && abs($current - $pushed) < 0.01) {
                continue;
            }
            $due[$pushedAt.'|'.$r->sku] = (string) $r->sku;
            $expected[$key] = $pushed;
        }
        ksort($due);
        $skus = array_slice(array_values($due), 0, $limit);

        $matched = $pulled = $failed = 0;
        foreach ($skus as $i => $sku) {
            $res = $pull->pullSkus('shein', [$sku], [strtoupper(trim($sku)) => $expected[strtoupper(trim($sku))] ?? 0.0])[0] ?? null;
            if ($res && ! empty($res['success'])) {
                $pulled++;
                if (abs((float) ($res['price'] ?? 0) - ($expected[strtoupper(trim($sku))] ?? -1)) < 0.01) {
                    $matched++;
                }
            } else {
                $failed++;
            }
            if ($i < count($skus) - 1) {
                usleep(max(0, (int) $this->option('delay-ms')) * 1000);
            }
        }

        $this->info(sprintf('Shein pushed-price pull: due=%d pulled=%d matched=%d failed=%d', count($due), $pulled, $matched, $failed));

        return self::SUCCESS;
    }
}
