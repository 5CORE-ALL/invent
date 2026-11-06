<?php

namespace App\Console\Commands;

use App\Services\DilVsSbidApplyService;
use App\Support\PmtCategoryBidRule;
use Illuminate\Console\Command;

/**
 * Read-only: is Ads Rule PMT applied to one listing? Compares the bid the rule wants with the stored
 * C Bid and with the live bid eBay returns. Pushes nothing.
 *
 *   php artisan ebay:pmt-check 366565060273 ebay2
 */
class EbayPmtCheckCommand extends Command
{
    protected $signature = 'ebay:pmt-check {itemId : eBay item / listing id} {account=ebay2 : ebay1, ebay2 or ebay3}
        {--push : Also push the rule bid for this one listing now and print the result (changes the live bid)}';

    protected $description = 'Read-only: show the Ads Rule PMT bid wanted, the stored C Bid and the live eBay bid for one listing.';

    public function handle(DilVsSbidApplyService $apply): int
    {
        $account = strtolower(trim((string) $this->argument('account')));
        if (! in_array($account, PmtCategoryBidRule::ACCOUNTS, true)) {
            $this->error('Account must be ebay1, ebay2 or ebay3.');

            return self::FAILURE;
        }

        $r = $apply->explainPmt($account, (string) $this->argument('itemId'));
        if (! empty($r['error'])) {
            $this->error($r['error']);

            return self::FAILURE;
        }

        $this->line('Account / listing : '.$r['account'].' / '.$r['listing_id'].'  (rule decided per '.($r['level'] ?? '?').')');
        $this->line('Ads Rule PMT switch: '.($r['switch_on'] ? 'ON' : 'OFF'));
        $this->newLine();

        $this->line('Local ad rows ('.count($r['ads_rows']).'):');
        $this->table(['campaign_id', 'ad_id', 'funding', 'status', 'stored C Bid', 'row updated_at'], array_map(fn ($a) => [
            $a['campaign_id'], $a['ad_id'], $a['funding'], $a['status'], $a['stored_c_bid'], $a['updated_at'],
        ], $r['ads_rows']));

        if (isset($r['inputs'])) {
            $i = $r['inputs'];
            $this->line(sprintf(
                'Rule inputs: INV %s, OV L30 %s, Dil %s%%, eBay sold L30 %s, category "%s"',
                $i['inv'] ?? 'n/a', $i['ovL30'] ?? 0, $i['dil_pct'], $i['sold'] ?? 0, $i['category'] ?: '(none)'
            ));
            $range = $r['category_range'];
            $this->line('Category range: '.($range ? 'Highest '.($range['highest'] ?? '-').' / Lowest '.($range['lowest'] ?? '-') : 'NOT SET'));
        }

        $w = $r['wanted'];
        $this->newLine();
        $this->line('Rule wants: '.($w['off'] ? 'PAUSE the ad (INV 0)' : ($w['formatted'] !== null ? $w['formatted'].'%' : 'nothing')).'   '.($w['label'] ?: ($w['skip'] ?? '')));
        $this->newLine();

        if (isset($r['live_error'])) {
            $this->error('eBay API: '.$r['live_error']);
        }
        $wantedBid = $w['formatted'] !== null ? (float) $w['formatted'] : null;
        $applied = null;
        foreach ($r['live'] as $l) {
            $this->line('Live on eBay, campaign '.$l['campaign_id'].($l['ok'] ? '' : '  (API error: '.$l['error'].')').':');
            if ($l['ads'] === []) {
                $this->line('  no ad for this listing in that campaign');
                continue;
            }
            foreach ($l['ads'] as $ad) {
                $this->line(sprintf('  ad %s  listing %s  status %s  bid %s', $ad['adId'], $ad['listingId'], $ad['status'], $ad['bidPercentage'] ?? 'n/a'));
                if ($w['off']) {
                    $applied = ($applied ?? true) && strtoupper((string) $ad['status']) !== 'ACTIVE';
                } elseif ($wantedBid !== null && is_numeric($ad['bidPercentage'] ?? null)) {
                    $applied = ($applied ?? true) && abs(round((float) $ad['bidPercentage'], 1) - round($wantedBid, 1)) < 0.009;
                }
            }
        }

        $this->newLine();
        if (! $r['switch_on']) {
            $this->warn('RESULT: NOT APPLIED. The Ads Rule PMT switch is OFF for '.$account.', so nothing is pushed.');
        } elseif ($applied === null) {
            $this->warn('RESULT: cannot tell (no live ad / no rule bid). See the lines above.');
        } elseif ($applied) {
            $this->info('RESULT: APPLIED. The live eBay bid matches the rule.');
        } else {
            $this->warn('RESULT: NOT APPLIED. The live eBay bid differs from what the rule wants.');
        }

        if ($this->option('push')) {
            $this->newLine();
            $this->line('Pushing the rule bid for this listing only...');
            $res = $apply->applyPmtChanged($account, false, [(string) $this->argument('itemId')]);
            if (! empty($res['error'])) {
                $this->error('Push error: '.$res['error']);
            }
            $this->line(sprintf(
                'Pushed %d, unchanged %d, failed %d, skipped %d',
                (int) ($res['success'] ?? 0), (int) ($res['unchanged'] ?? 0), (int) ($res['failed'] ?? 0), (int) ($res['skipped'] ?? 0)
            ));
            foreach ($res['results'] ?? [] as $row) {
                $this->line('  '.json_encode($row, JSON_UNESCAPED_SLASHES));
            }
        }

        return self::SUCCESS;
    }
}
