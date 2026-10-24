<?php

namespace App\Services;

use App\Http\Controllers\MarketPlace\EbayController;
use App\Http\Controllers\MarketPlace\EbayThreeController;
use App\Http\Controllers\MarketPlace\EbayTwoController;
use App\Support\EbayRuleSprice;
use App\Support\NegativeSnroiPushGuard;
use App\Support\PushedListingPrice;
use App\Support\StdPrcVsDilPricer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Headless S PRC for /ebay-tabulator-view, /ebay2-tabulator-view and /ebay3-tabulator-view
 * (same job as Amazon's amazon:sprc-dil-auto-push, Shopify B2C's shopify-b2c:sprc-dil-auto-push).
 *
 * Re-prices every SKU with the Std prc vs dil chain (+ LMP cap + Std cap) from the same rows the page
 * loads, and saves S PRC wherever it changed. The push is queued separately by
 * ChannelPushSpriceDailyEnqueue (saved S PRC ≠ live Price, INV > 0).
 */
class EbaySprcDilAutoPushService
{
    /**
     * @param  list<string>|null  $onlySkus
     * @return array{stats: array<string, mixed>, changes: list<array{sku:string,sprice:float}>}
     */
    public function run(string $channel, bool $dryRun = false, ?array $onlySkus = null, ?callable $logger = null): array
    {
        $channel = strtolower(trim($channel));
        if (! in_array($channel, EbayRuleSprice::CHANNELS, true)) {
            throw new \InvalidArgumentException('channel must be one of: '.implode(', ', EbayRuleSprice::CHANNELS));
        }
        $log = $logger ?? static function (string $msg): void {};
        $stats = [
            'candidates' => 0,
            'applied' => 0,
            'skipped_unchanged' => 0,
            'skipped' => 0,
            'neg_snroi' => 0,
            'errors' => [],
        ];

        $lock = Cache::lock('ebay-sprc-dil-auto-push-run-'.$channel, 1800);
        if (! $lock->get()) {
            $log('eBay S PRC run already in progress for '.$channel.' — skipped.');

            return ['stats' => $stats, 'changes' => []];
        }

        try {
            $only = $onlySkus === null || $onlySkus === []
                ? []
                : array_map(static fn ($s) => strtoupper(trim((string) $s)), $onlySkus);
            $pricer = StdPrcVsDilPricer::forChannel($channel);

            $changes = [];
            $pageAds = EbayRuleSprice::pageAdsPercent($channel);
            // The page switch decides the rule: Std prc vs dil, or Sprc Dil (Target NROI by Dil, LMP × 0.98).
            $ruleContext = EbayRuleSprice::context($channel);
            $log($channel.': S PRC rule = '.($ruleContext['rule'] === 'dil' ? 'Sprc Dil' : 'Std prc vs dil'));
            foreach ($this->tabulatorRows($channel) as $row) {
                $input = EbayRuleSprice::inputFromTabulatorRow($row, $pageAds);
                if ($input === null) {
                    continue;
                }
                if ($only !== [] && ! in_array(strtoupper($input['sku']), $only, true)) {
                    continue;
                }
                $stats['candidates']++;
                $priced = EbayRuleSprice::price($pricer, $input, $ruleContext);
                if ($priced === null) {
                    $stats['skipped']++;

                    continue;
                }
                $sprice = (float) $priced['sprice'];
                // A negative-SNROI price is still saved, but the runner never pushes it: it stays blue.
                $live = (float) ($row['eBay Price'] ?? 0);
                if ($input['inv'] > 0 && ! PushedListingPrice::same($live, $sprice)
                    && NegativeSnroiPushGuard::shouldSkip($channel, $input['sku'], $sprice)) {
                    $stats['neg_snroi']++;
                }
                $saved = round((float) ($row['SPRICE'] ?? 0), 2);
                if (abs($saved - $sprice) <= 0.009) {
                    $stats['skipped_unchanged']++;

                    continue;
                }
                $changes[] = ['sku' => $input['sku'], 'sprice' => $sprice];
            }

            if (! $dryRun) {
                foreach (array_chunk($changes, 200) as $chunk) {
                    try {
                        $this->saveChunk($channel, $chunk);
                        $stats['applied'] += count($chunk);
                    } catch (\Throwable $e) {
                        $stats['errors'][] = $e->getMessage();
                        Log::warning('eBay S PRC auto save failed', ['channel' => $channel, 'error' => $e->getMessage()]);
                    }
                }
            }
            $log(sprintf('%s: %d rows, %d changed, %d unchanged, %d skipped.',
                $channel, $stats['candidates'], count($changes), $stats['skipped_unchanged'], $stats['skipped']));

            return ['stats' => $stats, 'changes' => $changes];
        } finally {
            $lock->release();
        }
    }

    /**
     * Row builders only: the *DataJson wrappers also write the daily summary.
     *
     * @return list<array<string, mixed>>
     */
    public function tabulatorRows(string $channel): array
    {
        $request = new Request();
        $request->attributes->set('skip_daily_summary', true);
        $level = ob_get_level();
        ob_start();
        try {
            $response = match ($channel) {
                'ebay1' => app(EbayController::class)->getViewEbayData($request),
                'ebay2' => app(EbayTwoController::class)->getViewEbayData($request),
                'ebay3' => app(EbayThreeController::class)->getViewEbay3DataTabulator($request),
            };
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
        $payload = json_decode((string) $response->getContent(), true);
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : (is_array($payload) ? $payload : []);

        return $this->flatten($data);
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return list<array<string, mixed>>
     */
    private function flatten(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $out[] = $row;
            if (is_array($row['_children'] ?? null)) {
                array_push($out, ...$this->flatten($row['_children']));
            }
        }

        return $out;
    }

    /**
     * @param  list<array{sku:string,sprice:float}>  $chunk
     */
    private function saveChunk(string $channel, array $chunk): void
    {
        $request = Request::create('/', 'POST', ['updates' => $chunk, 'skip_push' => 1]);
        match ($channel) {
            'ebay1' => app(EbayController::class)->saveSpriceToDatabase($request),
            'ebay2' => app(EbayTwoController::class)->saveSpriceToDatabase($request),
            'ebay3' => app(EbayThreeController::class)->saveSpriceToDatabase($request),
        };
    }
}
