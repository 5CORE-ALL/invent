<?php

namespace App\Console\Commands;

use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\EbayMetric;
use App\Models\ShopifySku;
use App\Services\EbayRuleSpriceApplyService;
use App\Support\Marketplace\EbayListingEnded;
use App\Support\NegativeSnroiPushGuard;
use Illuminate\Console\Command;

/**
 * READ-ONLY. Explains the eBay blue-triangle count: which SKUs the server can push,
 * which it can not, and why. Writes nothing and pushes nothing.
 *
 * Blue triangle on the page = INV > 0, listing not ended, S PRC ≠ eBay Price.
 */
class EbayBlueAuditCommand extends Command
{
    protected $signature = 'ebay:blue-audit
        {channel=ebay1 : ebay1, ebay2 or ebay3}
        {--sample=10 : SKUs to list per reason}';

    protected $description = 'Read-only: break the eBay blue-triangle count down by reason (pushable, blocked, unpushable).';

    public function handle(): int
    {
        $channels = EbayRuleSpriceApplyService::channelsFromArg((string) $this->argument('channel'));
        if ($channels === []) {
            $this->error('Use ebay1, ebay2 or ebay3.');

            return self::FAILURE;
        }
        $sample = max(1, (int) $this->option('sample'));

        foreach ($channels as $channel) {
            $this->report($channel, $sample);
        }

        return self::SUCCESS;
    }

    private function report(string $channel, int $sample): void
    {
        $svc = EbayRuleSpriceApplyService::for($channel);
        $buckets = [
            'pushable_now' => [],            // saved S PRC ≠ live, push queue will take it
            'needs_save_then_push' => [],    // live rule target ≠ saved S PRC and ≠ live
            'blocked_negative_snroi' => [],  // accepted exception: min-profit / negative SNROI
            'no_target' => [],               // rule gives no S PRC (cannot be blue on the page either, unless saved)
            'matches_live' => [],
        ];

        // run() sets this flag; computeTarget() reads it. Set it the same way without running a save pass.
        $flag = new \ReflectionProperty($svc, 'applyStdPrcVsDil');
        $flag->setAccessible(true);
        $flag->setValue($svc, \App\Support\SpriceActiveRule::usesStdPrc($channel));

        $dilStore = $svc->loadDilGroiStore();
        $margin = $this->margin($svc);
        $ads = $svc->channelAdsPercent();

        foreach ($svc->hydrateAll(null) as $row) {
            if (! ((float) ($row['inv'] ?? 0) > 0)) {
                continue;
            }
            $sku = (string) $row['sku'];
            $live = round((float) ($row['live'] ?? 0), 2);
            $saved = round((float) ($row['saved_sprice'] ?? 0), 2);
            $target = $svc->computeTarget($row, $dilStore['rules'], $dilStore['cvr_adj'], $margin, $ads);
            $next = $target !== null ? round((float) $target['sprice'], 2) : 0.0;
            $page = $next > 0 ? $next : $saved; // page shows the live rule result, else the saved value

            if (! ($page > 0)) {
                $buckets['no_target'][] = $sku;
                continue;
            }
            if (EbayRuleSpriceApplyService::sameCents($page, $live)) {
                $buckets['matches_live'][] = $sku;
                continue;
            }
            if (NegativeSnroiPushGuard::shouldSkip($channel, $sku, $page)) {
                $buckets['blocked_negative_snroi'][] = $sku.' ('.$page.' vs '.$live.')';
                continue;
            }
            if ($saved > 0 && EbayRuleSpriceApplyService::sameCents($saved, $page)) {
                $buckets['pushable_now'][] = $sku.' ('.$page.' vs '.$live.')';
            } else {
                $buckets['needs_save_then_push'][] = $sku.' ('.$page.' vs '.$live.')';
            }
        }

        // INV > 0 listings the server never sees (the page can still show them as blue).
        $unpushable = $this->unpushable($channel);

        $this->info(strtoupper($channel));
        foreach ($buckets as $name => $list) {
            $this->line(sprintf('  %-26s %d', $name, count($list)));
            if (in_array($name, ['matches_live', 'no_target'], true)) {
                continue;
            }
            foreach (array_slice($list, 0, $sample) as $s) {
                $this->line('      '.$s);
            }
        }
        foreach ($unpushable as $name => $list) {
            $this->line(sprintf('  %-26s %d   (server cannot push these)', $name, count($list)));
            foreach (array_slice($list, 0, $sample) as $s) {
                $this->line('      '.$s);
            }
        }
        $blue = count($buckets['pushable_now']) + count($buckets['needs_save_then_push'])
            + count($buckets['blocked_negative_snroi']);
        $this->info(sprintf(
            '  expected page badge ≈ %d (+ unpushable listings the page may also count); server clears %d, %d stay blue by the negative-SNROI rule',
            $blue,
            count($buckets['pushable_now']) + count($buckets['needs_save_then_push']),
            count($buckets['blocked_negative_snroi'])
        ));
        $this->newLine();
    }

    /**
     * @return array<string, list<string>>
     */
    private function unpushable(string $channel): array
    {
        $metric = match ($channel) {
            'ebay2' => Ebay2Metric::class,
            'ebay3' => Ebay3Metric::class,
            default => EbayMetric::class,
        };
        $out = ['no_item_id' => [], 'ended_listing' => [], 'live_price_missing' => []];
        $inv = ShopifySku::query()->where('inv', '>', 0)->pluck('inv', 'sku')
            ->mapWithKeys(static fn ($v, $k) => [strtoupper(trim((string) $k)) => (float) $v]);

        $metric::query()->whereNotNull('sku')->where('sku', '!=', '')->orderBy('id')
            ->chunkById(500, function ($rows) use (&$out, $inv) {
                foreach ($rows as $m) {
                    $sku = strtoupper(trim((string) $m->sku));
                    if ($sku === '' || str_contains($sku, 'PARENT') || ! isset($inv[$sku])) {
                        continue;
                    }
                    if (EbayListingEnded::isEnded($m->listing_status ?? null)) {
                        $out['ended_listing'][] = $sku;
                    } elseif (trim((string) ($m->item_id ?? '')) === '') {
                        $out['no_item_id'][] = $sku;
                    } elseif (! ((float) ($m->ebay_price ?? 0) > 0)) {
                        $out['live_price_missing'][] = $sku;
                    }
                }
            });

        return $out;
    }

    private function margin(EbayRuleSpriceApplyService $svc): float
    {
        $m = new \ReflectionMethod($svc, 'takeHome');
        $m->setAccessible(true);

        return (float) $m->invoke($svc);
    }
}
