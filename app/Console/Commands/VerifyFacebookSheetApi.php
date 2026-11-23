<?php

namespace App\Console\Commands;

use App\Http\Controllers\FacebookAllAdsSheetController;
use App\Models\FacebookAllAdsSheet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only health report for /facebook-all-ads-sheet: is every column being
 * fetched from an API and saved, and how fresh is it? Changes nothing.
 */
class VerifyFacebookSheetApi extends Command
{
    protected $signature = 'facebook:verify-sheet-api';

    protected $description = 'Report whether the Facebook all-ads sheet data is fetched from the APIs (read-only)';

    public function handle(): int
    {
        $bad = 0;
        $ok = function (bool $pass, string $label, string $detail) use (&$bad) {
            if (! $pass) {
                $bad++;
            }
            $this->line(($pass ? '  [ OK ] ' : '  [FAIL] ') . str_pad($label, 34) . $detail);
        };

        $this->info('1. API tables (saved from Meta)');
        foreach (['meta_campaigns', 'meta_insights_daily', 'facebook_campaign_lifetime_stats', 'facebook_all_ads_sheet'] as $t) {
            if (! Schema::hasTable($t)) {
                $ok(false, $t, 'table missing');
            }
        }
        if ($bad) {
            return 1;
        }

        $camps = DB::table('meta_campaigns')->count();
        $campSync = DB::table('meta_campaigns')->max('synced_at');
        $ok($camps > 0, 'meta_campaigns', "{$camps} rows, last synced {$campSync}");

        $latest = DB::table('meta_insights_daily')->where('entity_type', 'campaign')->max('date_start');
        $age = $latest ? (int) now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($latest)->startOfDay(), true) : null;
        $ok($latest !== null && $age <= 3, 'meta_insights_daily (campaign)', $latest ? "latest {$latest} ({$age} day(s) old; must be <= 3)" : 'no data');

        $lt = DB::table('facebook_campaign_lifetime_stats')->count();
        $ltAt = DB::table('facebook_campaign_lifetime_stats')->max('updated_at');
        $ok($lt > 0, 'facebook_campaign_lifetime_stats', "{$lt} rows, last updated {$ltAt}");

        $this->info('2. Sheet rows (where the Campaign list comes from)');
        $bySource = FacebookAllAdsSheet::query()
            ->select('source_filename', DB::raw('COUNT(*) AS c'))
            ->groupBy('source_filename')->pluck('c', 'source_filename')->all();
        foreach ($bySource as $src => $c) {
            $this->line("         {$c} row(s) from: {$src}");
        }
        $apiRows = (int) ($bySource['Meta API sync'] ?? 0);
        $ok($apiRows > 0, 'Campaign list built from API', "{$apiRows} of " . array_sum($bySource) . ' rows');

        $this->info('3. Merged sheet, as the page shows it');
        $merged = app(FacebookAllAdsSheetController::class)->getMergedView(null);
        $data = $merged['data'] ?? [];
        $n = count($data);
        $ok($n > 0, 'rows on the page', (string) $n);
        if ($n === 0) {
            return 1;
        }

        $filled = fn (string $k) => count(array_filter($data, fn ($r) => isset($r[$k]) && $r[$k] !== null && $r[$k] !== ''));
        $groups = [
            'Campaign name / CAMPAIGN ID (Meta)' => ['Campaign name', 'CAMPAIGN ID'],
            'SPEND / IMPR / CLK (Meta insights)' => ['SPEND', 'IMPR', 'CLK'],
            'Status (Meta)'                      => ['Status'],
            'SALES / SOLD / Sessions (Shopify)'  => ['SALES', 'SOLD', 'Sessions'],
            'Lifetime (Meta lifetime sync)'      => ['Lifetime Spend', 'Lifetime Sales', 'Lifetime Acos'],
        ];
        foreach ($groups as $label => $cols) {
            $parts = [];
            $minPct = 100;
            foreach ($cols as $c) {
                $f = $filled($c);
                $pct = (int) round($f / $n * 100);
                $minPct = min($minPct, $pct);
                $parts[] = "{$c} {$f}/{$n}";
            }
            $ok($minPct >= 50, $label, implode(', ', $parts));
        }

        $this->info('4. Not from an API (expected to be manual / internal)');
        foreach (['ad_type', 'ch', 'b2b_b2c', 'Price', 'GPFT$', 'Audit Req'] as $c) {
            $this->line('         ' . str_pad($c, 10) . $filled($c) . "/{$n} filled");
        }

        $this->line('');
        $bad === 0
            ? $this->info('All API-backed columns look healthy.')
            : $this->warn("{$bad} check(s) failed. Failing groups show blank cells on the page.");

        return $bad === 0 ? 0 : 1;
    }
}
