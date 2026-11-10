<?php

namespace App\Console\Commands;

use App\Services\ExcelBenchmarksService;
use App\Services\ExcelCategoryLookupService;
use App\Support\PmtCategoryBidRule;
use Illuminate\Console\Command;
/**
 * Checks that the "Fill from Benchmarks" values of the Ads Rule PMT modal line up with /benchmarks:
 * which modal categories find a benchmark sheet, which sheets no category uses, and the Highest / Lowest it fills
 * (Top 10% / Bottom 10% of "Promoted Listings ads rate" in the "Impressions in eBay search" section).
 * Same matching as the modal (resources/views/market-places/partials/ebay-pmt-multibuy.blade.php).
 */
class EbayPmtBenchmarkCheckCommand extends Command
{
    protected $signature = 'ebay:pmt-benchmark-check {account=ebay1} {--category= : only categories containing this text, e.g. "Stands & Hangers"}';

    protected $description = 'Compare the Ads Rule PMT categories with the /benchmarks sheets and show the Highest / Lowest each would get';

    public function handle(ExcelBenchmarksService $benchmarks, ExcelCategoryLookupService $categories): int
    {
        $account = (string) $this->argument('account');
        $data = $benchmarks->get($account);

        // Promoted Listings ads rate column (same scoring as the modal).
        $rate = null;
        $best = 0;
        foreach ($data['columns'] as $c) {
            $t = strtolower((string) $c['title']);
            $score = (str_contains($t, 'promoted') ? 2 : 0) + (preg_match('/\bads?\b/', $t) ? 1 : 0) + (str_contains($t, 'rate') ? 2 : 0);
            if ($score >= 4 && $score > $best) {
                $rate = $c;
                $best = $score;
            }
        }
        if ($rate === null) {
            $this->error('No "Promoted Listings ads rate" column in the benchmarks (upload the Listing Quality report in /excel).');

            return self::FAILURE;
        }
        $this->info('Benchmarks file: '.($data['source']['filename'] ?? '?').' · column: '.$rate['title']);

        // sheet => [highest, lowest]
        $bench = [];
        foreach ($data['rows'] as $row) {
            if (! str_contains($this->norm((string) $row['section']), 'impressions in ebay search')) {
                continue;
            }
            $val = $this->pct($row[$rate['field']] ?? null);
            if ($val === null) {
                continue;
            }
            $key = $this->norm((string) $row['category']);
            $bench[$key]['sheet'] = (string) $row['category'];
            if ($row['benchmark'] === 'Top 10%') {
                $bench[$key]['highest'] = $val;
            } elseif ($row['benchmark'] === 'Bottom 10%') {
                $bench[$key]['lowest'] = $val;
            } elseif ($row['benchmark'] === 'Yours') {
                $bench[$key]['yours'] = trim((string) $row[$rate['field']]);
            }
        }

        // SKUs per modal category.
        $skus = array_count_values(array_values($account === 'ebay1' ? $categories->categoryMasterBySku() : []));
        $saved = PmtCategoryBidRule::bids($account);
        $names = $categories->categoryNames($account);

        $used = [];
        $rows = [];
        $missing = 0;
        $only = mb_strtolower(trim((string) $this->option('category')));
        foreach ($names as $name) {
            if ($only !== '' && ! str_contains(mb_strtolower($name), $only)) {
                continue;
            }
            $hit = $this->find($bench, $name);
            if ($hit) {
                $used[$this->norm($hit['sheet'])] = true;
            } else {
                $missing++;
            }
            $s = $saved[$name] ?? null;
            $rows[] = [
                $name,
                $skus[$name] ?? 0,
                $hit['sheet'] ?? '— no benchmark —',
                $hit['yours'] ?? '',
                $hit['highest'] ?? '',
                $hit['lowest'] ?? '',
                $s ? (($s['highest'] ?? '').' / '.($s['lowest'] ?? '')) : '',
            ];
        }
        $this->table(['Modal category', 'SKUs', 'Benchmark sheet', 'Yours', 'Highest', 'Lowest', 'Saved H / L'], $rows);
        $this->line(count($names).' categories, '.($missing).' without a benchmark.');

        $unused = array_values(array_filter(array_map(fn ($b) => $b['sheet'], $bench), fn ($sheet) => ! isset($used[$this->norm($sheet)])));
        $this->line('Benchmark sheets no modal category uses ('.count($unused).'): '.($unused ? implode(' | ', $unused) : 'none'));

        // Saved ranges whose category has no SKU any more (e.g. an old truncated sheet name).
        $stale = array_values(array_filter(array_keys($saved), fn ($c) => ! in_array($c, $names, true) || ($skus[$c] ?? 0) === 0));
        if ($stale) {
            $this->warn('Saved ranges on categories with no SKUs: '.implode(' | ', $stale));
        }

        return self::SUCCESS;
    }

    private function norm(string $v): string
    {
        return trim((string) preg_replace('/\s+/', ' ', strtolower($v)));
    }

    private function pct($v): ?float
    {
        $n = preg_replace('/[%,$\s]/', '', (string) ($v ?? ''));

        return is_numeric($n) ? (float) $n : null;
    }

    /** Same as benchFor() in the modal: exact name, else a prefix match (sheet names are cut at 31 chars). */
    private function find(array $bench, string $category): ?array
    {
        $c = $this->norm($category);
        if (isset($bench[$c])) {
            return $bench[$c];
        }
        foreach ($bench as $k => $b) {
            if (min(strlen($k), strlen($c)) >= 10 && (str_starts_with($c, $k) || str_starts_with($k, $c))) {
                return $b;
            }
        }

        return null;
    }
}
