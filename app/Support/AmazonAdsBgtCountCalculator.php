<?php

namespace App\Support;

use App\Http\Controllers\AmazonAdsController;
use Illuminate\Http\Request;
use ReflectionMethod;

/**
 * Same band math as the BGT Rules modal, saved into amazon_ads_rules.
 */
final class AmazonAdsBgtCountCalculator
{
    private const COLORS = ['#7c3aed', '#2563eb', '#16a34a', '#f59e0b', '#f97316', '#dc2626', '#64748b', '#0ea5e9'];

    /**
     * @return array<string, mixed>
     */
    public function calculate(): array
    {
        @set_time_limit(0);
        $campaigns = $this->campaigns();
        $acos = AmazonAcosSbgtRule::resolvedRule()['bands'] ?? [];
        $views = AmazonAdsBgtViewsRule::resolvedRule()['bands'] ?? [];
        $cvr = AmazonAdsBgtCvrRule::resolvedRule()['bands'] ?? [];
        $prc = AmazonAdsBgtPrcRule::resolvedRule()['bands'] ?? [];
        $reviews = AmazonAdsBgtReviewsRule::resolvedRule()['bands'] ?? [];
        $dil = AmazonAdsBgtDilRule::resolvedRule()['bands'] ?? [];
        $inv = AmazonAdsBgtInvRule::resolvedRule()['bands'] ?? [];
        $spend = AmazonAdsBgtSpendRule::resolvedRule()['bands'] ?? [];

        $columns = [
            'acos' => $this->tally($campaigns, $acos, 'acos_from', 'acos_to', fn (array $row): ?float => $this->num($row['ltAcos'] ?? null)),
            'views' => $this->tally($campaigns, $views, 'views_from', 'views_to', fn (array $row): ?float => $this->viewsOf($row)),
            'cvr' => $this->tally($campaigns, $cvr, 'cvr_from', 'cvr_to', fn (array $row): ?float => $this->cvrOf($row)),
            'prc' => $this->tally($campaigns, $prc, 'prc_from', 'prc_to', fn (array $row): ?float => $this->picked($row, 'bgt_prc_price', 'price')),
            'reviews' => $this->tallyReviews($campaigns, $reviews),
            'dil' => $this->tally($campaigns, $dil, 'dil_from', 'dil_to', fn (array $row): ?float => $this->dilOf($row)),
            'inv' => $this->tally($campaigns, $inv, 'inv_from', 'inv_to', fn (array $row): ?float => $this->picked($row, 'bgt_inv_value', 'Inv')),
            'spend' => $this->tally($campaigns, $spend, 'spend_from', 'spend_to', fn (array $row): ?float => $this->picked($row, 'cost', 'spend')),
        ];

        $saved = [
            'filter' => 'ENABLED,PAUSED',
            'columns' => $columns,
            'sum' => $this->sum($campaigns, $views, $cvr, $acos, $prc, $reviews, $dil, $inv),
        ];
        AmazonAdsBgtCountStore::write($saved);

        return AmazonAdsBgtCountStore::read() ?? $saved;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function campaigns(): array
    {
        $day = $this->defaultReportDay();
        $request = Request::create('/amazon-ads/raw-data/all_reports', 'POST', [
            'draw' => 1,
            'start' => 0,
            'length' => 20000,
            'bgt_universe' => 1,
            'search' => ['value' => '', 'regex' => 'false'],
            'date_from' => $day ?? '',
            'date_to' => $day ?? '',
            'summary_report_range' => '',
            'filter_campaign_status' => 'ENABLED,PAUSED',
        ]);
        $response = app(AmazonAdsController::class)->rawData($request, 'all_reports');
        $json = json_decode($response->getContent(), true);
        $rows = is_array($json) && isset($json['campaigns']) && is_array($json['campaigns'])
            ? $json['campaigns']
            : [];

        return array_values(array_filter($rows, 'is_array'));
    }

    private function defaultReportDay(): ?string
    {
        $method = new ReflectionMethod(AmazonAdsController::class, 'latestAvailableReportDayYmd');
        $method->setAccessible(true);
        $day = $method->invoke(null, 'amazon_sp_campaign_reports');

        return is_string($day) && $day !== '' ? $day : null;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $bands
     * @return array{counts: list<int>, unmatched: int, campaigns: int}
     */
    private function tally(array $rows, array $bands, string $fromKey, string $toKey, callable $valueOf): array
    {
        $counts = array_fill(0, count($bands), 0);
        $unmatched = 0;
        foreach ($rows as $row) {
            $idx = $this->bandIndex($valueOf($row), $bands, $fromKey, $toKey);
            if ($idx >= 0) {
                $counts[$idx]++;
            } else {
                $unmatched++;
            }
        }

        return [
            'counts' => $counts,
            'unmatched' => $unmatched,
            'campaigns' => count($rows),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $bands
     * @return array{counts: list<int>, unmatched: int, campaigns: int}
     */
    private function tallyReviews(array $rows, array $bands): array
    {
        $counts = array_fill(0, count($bands), 0);
        $unmatched = 0;
        foreach ($rows as $row) {
            $idx = $this->reviewsIndex($this->picked($row, 'bgt_reviews_rating', 'reviews'), $bands);
            if ($idx >= 0) {
                $counts[$idx]++;
            } else {
                $unmatched++;
            }
        }

        return [
            'counts' => $counts,
            'unmatched' => $unmatched,
            'campaigns' => count($rows),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $bands
     */
    private function bandIndex(?float $value, array $bands, string $fromKey, string $toKey): int
    {
        if ($value === null || ! is_finite($value)) {
            return -1;
        }
        foreach ($bands as $i => $band) {
            if (! is_array($band)) {
                continue;
            }
            $from = isset($band[$fromKey]) && is_numeric($band[$fromKey]) ? (float) $band[$fromKey] : NAN;
            $to = isset($band[$toKey]) && is_numeric($band[$toKey]) ? (float) $band[$toKey] : NAN;
            if (! is_finite($from) || ! is_finite($to)) {
                continue;
            }
            if ($value >= $from && $value <= $to) {
                return (int) $i;
            }
        }

        return -1;
    }

    /**
     * @param  list<array<string, mixed>>  $bands
     */
    private function reviewsIndex(?float $rating, array $bands): int
    {
        if ($rating === null || ! is_finite($rating)) {
            return -1;
        }
        $n = count($bands);
        foreach ($bands as $i => $band) {
            if (! is_array($band)) {
                continue;
            }
            $from = isset($band['rev_from']) && is_numeric($band['rev_from']) ? (float) $band['rev_from'] : NAN;
            $to = isset($band['rev_to']) && is_numeric($band['rev_to']) ? (float) $band['rev_to'] : NAN;
            if (! is_finite($from) || ! is_finite($to)) {
                continue;
            }
            $nextFrom = ($i < $n - 1 && isset($bands[$i + 1]['rev_from']) && is_numeric($bands[$i + 1]['rev_from']))
                ? (float) $bands[$i + 1]['rev_from']
                : null;
            $hit = ($rating >= $from && $rating <= $to)
                || ($nextFrom !== null && is_finite($nextFrom) && $rating > $to && $rating < $nextFrom);
            if ($hit) {
                return (int) $i;
            }
        }

        return -1;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $views
     * @param  list<array<string, mixed>>  $cvr
     * @param  list<array<string, mixed>>  $acos
     * @param  list<array<string, mixed>>  $prc
     * @param  list<array<string, mixed>>  $reviews
     * @param  list<array<string, mixed>>  $dil
     * @param  list<array<string, mixed>>  $inv
     * @return array<string, mixed>
     */
    private function sum(array $rows, array $views, array $cvr, array $acos, array $prc, array $reviews, array $dil, array $inv): array
    {
        $order = [
            ['key' => 'views', 'label' => 'Views'],
            ['key' => 'cvr', 'label' => 'CVR'],
            ['key' => 'acos', 'label' => 'ACOS'],
            ['key' => 'prc', 'label' => 'PRC'],
            ['key' => 'reviews', 'label' => 'Reviews'],
            ['key' => 'dil', 'label' => 'Dil'],
            ['key' => 'inv', 'label' => 'Inv'],
        ];
        $partCount = [];
        $partSum = [];
        foreach ($order as $part) {
            $partCount[$part['key']] = 0;
            $partSum[$part['key']] = 0.0;
        }
        $buckets = [];
        $campaigns = 0;
        $sbgtTotal = 0.0;
        foreach ($rows as $row) {
            $parts = [
                'views' => $this->amount($views, $this->bandIndex($this->viewsOf($row), $views, 'views_from', 'views_to'), 'bgt'),
                'cvr' => $this->amount($cvr, $this->bandIndex($this->cvrOf($row), $cvr, 'cvr_from', 'cvr_to'), 'bgt'),
                'acos' => $this->amount($acos, $this->bandIndex($this->num($row['ltAcos'] ?? null), $acos, 'acos_from', 'acos_to'), 'sbgt'),
                'prc' => $this->amount($prc, $this->bandIndex($this->picked($row, 'bgt_prc_price', 'price'), $prc, 'prc_from', 'prc_to'), 'bgt'),
                'reviews' => $this->amount($reviews, $this->reviewsIndex($this->picked($row, 'bgt_reviews_rating', 'reviews'), $reviews), 'bgt'),
                'dil' => $this->amount($dil, $this->bandIndex($this->dilOf($row), $dil, 'dil_from', 'dil_to'), 'bgt'),
                'inv' => $this->amount($inv, $this->bandIndex($this->picked($row, 'bgt_inv_value', 'Inv'), $inv, 'inv_from', 'inv_to'), 'bgt'),
            ];
            foreach ($order as $part) {
                $n = $parts[$part['key']];
                if ($n === null) {
                    continue;
                }
                $partCount[$part['key']]++;
                $partSum[$part['key']] += $n;
            }
            $total = AmazonAdsSbgt::sumFromParts(
                $parts['views'],
                $parts['cvr'],
                $parts['acos'],
                $parts['prc'],
                $parts['reviews'],
                $parts['dil'],
                $parts['inv']
            );
            if ($total === null) {
                continue;
            }
            $campaigns++;
            $sbgtTotal += $total;
            $buckets[$total] = ($buckets[$total] ?? 0) + 1;
        }
        ksort($buckets, SORT_NUMERIC);
        $chartRows = [];
        $i = 0;
        foreach ($buckets as $budget => $n) {
            $chartRows[] = [
                'label' => (string) $budget,
                'n' => $n,
                'color' => ((int) $budget) === 0 ? '#dc2626' : self::COLORS[$i % count(self::COLORS)],
            ];
            $i++;
        }
        $parts = [];
        $countTotal = 0;
        $bgtTotal = 0.0;
        foreach ($order as $part) {
            $countTotal += $partCount[$part['key']];
            $bgtTotal += $partSum[$part['key']];
            $parts[] = [
                'label' => $part['label'],
                'count' => $partCount[$part['key']],
                'sum' => $partSum[$part['key']],
            ];
        }

        return [
            'rows' => $chartRows,
            'totalN' => array_sum(array_column($chartRows, 'n')),
            'sbgtTotal' => $sbgtTotal,
            'campaigns' => $campaigns,
            'countTotal' => $countTotal,
            'bgtTotal' => $bgtTotal,
            'parts' => $parts,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $bands
     */
    private function amount(array $bands, int $idx, string $key): ?float
    {
        if ($idx < 0 || ! isset($bands[$idx]) || ! is_array($bands[$idx])) {
            return null;
        }
        $raw = $bands[$idx][$key] ?? null;
        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return null;
        }

        return (float) $raw;
    }

    private function viewsOf(array $row): float
    {
        $raw = array_key_exists('viewsL7', $row) && $row['viewsL7'] !== null
            ? $row['viewsL7']
            : ($row['page_cvr_sess7'] ?? null);

        return $this->num($raw) ?? 0.0;
    }

    private function cvrOf(array $row): float
    {
        $raw = array_key_exists('bgt_cvr_page_cvr', $row) && $row['bgt_cvr_page_cvr'] !== null
            ? $row['bgt_cvr_page_cvr']
            : ($row['pageCvr'] ?? null);

        return $this->num($raw) ?? 0.0;
    }

    private function dilOf(array $row): ?float
    {
        $raw = array_key_exists('bgt_dil_value', $row) && $row['bgt_dil_value'] !== null
            ? $row['bgt_dil_value']
            : ($row['dil'] ?? null);
        $n = $this->num($raw);
        if ($n !== null) {
            return $n;
        }
        $inv = $this->num($row['Inv'] ?? null);
        $ovl = $this->num($row['ovl30'] ?? null) ?? 0.0;
        if ($inv !== null && $inv > 0) {
            return ($ovl / $inv) * 100;
        }
        if ($inv !== null && $inv === 0.0) {
            return 0.0;
        }

        return null;
    }

    private function picked(array $row, string $primary, string $fallback): ?float
    {
        $raw = array_key_exists($primary, $row) && $row[$primary] !== null && $row[$primary] !== ''
            ? $row[$primary]
            : ($row[$fallback] ?? null);

        return $this->num($raw);
    }

    private function num(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }
        $n = (float) $value;

        return is_finite($n) ? $n : null;
    }
}
