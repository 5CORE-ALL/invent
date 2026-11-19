<?php

namespace App\Services;

use App\Support\PmtCategoryBidRule;
use App\Support\PmtRuleAutoPush;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Server-side twin of the browser auto-sync in the Ads Rule PMT window.
 *
 * Highest = Top 10% and Lowest = Bottom 10% of the "Promoted listings ad rate" column in the
 * "Impressions in eBay search" section of the benchmarks (/benchmarks, from the /excel LQS upload).
 * Every category that has a benchmark gets those values saved into the Ads Rule PMT ranges, so the
 * ranges stay current without anybody opening the page. A category without a benchmark is untouched.
 * When something changed and the PMT switch is On, a background push to eBay is started.
 */
final class PmtBenchmarkSync
{
    /**
     * @return array{account:string,status:string,changed:int,categories:int,file:?string,pushed:bool,note:?string}
     */
    public function run(string $account = 'ebay1', bool $push = true): array
    {
        $out = ['account' => $account, 'status' => 'ok', 'changed' => 0, 'categories' => 0, 'file' => null, 'pushed' => false, 'note' => null];
        if (! in_array($account, PmtCategoryBidRule::ACCOUNTS, true)) {
            return ['status' => 'skipped', 'note' => 'unknown account'] + $out;
        }

        try {
            $data = app(ExcelBenchmarksService::class)->get($account);
        } catch (\Throwable $e) {
            Log::warning('PmtBenchmarkSync '.$account.': '.$e->getMessage());

            return ['status' => 'error', 'note' => $e->getMessage()] + $out;
        }
        $out['file'] = $data['source']['filename'] ?? null;

        $field = $this->rateField($data['columns'] ?? []);
        if ($field === null || ($data['rows'] ?? []) === []) {
            return ['status' => 'skipped', 'note' => 'no benchmarks / no "Promoted listings ad rate" column'] + $out;
        }

        // category (normalized) => ['highest' => float, 'lowest' => float]
        $bench = [];
        foreach ($data['rows'] as $row) {
            if (! str_contains($this->norm((string) ($row['section'] ?? '')), 'impressions in ebay search')) {
                continue;
            }
            $key = $this->norm((string) ($row['category'] ?? ''));
            $val = $this->pct($row[$field] ?? null);
            if ($key === '' || $val === null) {
                continue;
            }
            $type = $row['benchmark'] ?? '';
            if ($type === 'Top 10%') {
                $bench[$key]['highest'] = $val;
            } elseif ($type === 'Bottom 10%') {
                $bench[$key]['lowest'] = $val;
            }
        }
        if ($bench === []) {
            return ['status' => 'skipped', 'note' => 'no Top / Bottom 10% values'] + $out;
        }

        $bids = PmtCategoryBidRule::bids($account);

        // Categories the window lists: the saved ones + every category of the account.
        $categories = array_keys($bids);
        try {
            foreach (app(ExcelCategoryLookupService::class)->categoryNames($account) as $name) {
                $categories[] = $name;
            }
        } catch (\Throwable $e) {
            // saved ones only
        }

        $changed = 0;
        $matched = 0;
        foreach (array_unique($categories) as $category) {
            $hit = $this->benchFor($bench, (string) $category);
            if ($hit === null) {
                continue;
            }
            $matched++;
            $cur = $bids[$category] ?? ['highest' => null, 'lowest' => null, 'manual' => null];
            $new = $cur;
            foreach (['highest', 'lowest'] as $f) {
                if (isset($hit[$f])) {
                    $new[$f] = round($hit[$f], 2);
                }
            }
            if ($new['highest'] !== null && $new['lowest'] !== null && $new['lowest'] > $new['highest']) {
                continue; // invalid pair: leave the category as it was
            }
            if ($new['highest'] === $cur['highest'] && $new['lowest'] === $cur['lowest']) {
                continue;
            }
            $bids[$category] = $new;
            $changed++;
        }
        $out['categories'] = $matched;
        $out['changed'] = $changed;

        if ($changed === 0) {
            return $out;
        }

        DB::table('ebay_sbid_rules')->updateOrInsert(
            ['key' => PmtCategoryBidRule::keyBids($account)],
            ['rule' => json_encode($bids, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]
        );

        if ($push && PmtCategoryBidRule::isOn($account)) {
            PmtRuleAutoPush::spawn($account, true);
            $out['pushed'] = true;
        }

        return $out;
    }

    /** Same column pick as the browser: a title with promoted + ad(s) + rate. */
    private function rateField(array $columns): ?string
    {
        $best = null;
        $bestScore = 0;
        foreach ($columns as $c) {
            $t = strtolower((string) ($c['title'] ?? ''));
            $score = 0;
            if (str_contains($t, 'promoted')) {
                $score += 2;
            }
            if (preg_match('/\bads?\b/', $t)) {
                $score += 1;
            }
            if (str_contains($t, 'rate')) {
                $score += 2;
            }
            if ($score >= 4 && $score > $bestScore) {
                $best = (string) $c['field'];
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * Same rules as the browser: exact name; else a sheet whose name eBay cut short (25+ chars) that is the start of the
     * category (longest wins); else the same words in another order. A shorter sheet name never matches a longer category.
     */
    private function benchFor(array $bench, string $category): ?array
    {
        $c = $this->norm($category);
        if (isset($bench[$c])) {
            return $bench[$c];
        }
        $best = null;
        $bestLen = 0;
        foreach ($bench as $k => $v) {
            $k = (string) $k;
            if (strlen($k) >= 25 && strlen($c) > strlen($k) && str_starts_with($c, $k) && strlen($k) > $bestLen) {
                $best = $v;
                $bestLen = strlen($k);
            }
        }
        if ($best !== null) {
            return $best;
        }
        $t = $this->tokKey($c);
        foreach ($bench as $k => $v) {
            if ($this->tokKey((string) $k) === $t) {
                return $v;
            }
        }

        return null;
    }

    private function tokKey(string $v): string
    {
        $w = preg_split('/[^a-z0-9]+/', strtolower($v), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $w = array_map(static fn ($x) => rtrim($x, 's'), $w);
        sort($w);

        return implode(' ', $w);
    }

    private function norm(string $v): string
    {
        return trim((string) preg_replace('/\s+/', ' ', strtolower($v)));
    }

    private function pct($v): ?float
    {
        $s = preg_replace('/[%,$\s]/', '', (string) ($v ?? ''));

        return $s !== '' && is_numeric($s) ? (float) $s : null;
    }
}
