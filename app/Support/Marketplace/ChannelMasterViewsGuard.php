<?php

namespace App\Support\Marketplace;

use App\Models\ChannelMasterSummary;
use Illuminate\Support\Facades\Log;

/**
 * Rolling L30 listing CVR is Qty ÷ Total Views. A partial live tabulator
 * scan can drop views 40–60% in one day while units stay flat, which makes
 * CVR jump from ~4.4% to 7–11%. Carry forward the last trusted views instead.
 */
class ChannelMasterViewsGuard
{
    /** Treat a views drop larger than this (vs the last good day) as a scan glitch. */
    public const COLLAPSE_RATIO = 0.85;

    /** If qty also falls by more than this, the views drop may be real. */
    public const QTY_REAL_DROP_RATIO = 0.80;

    /**
     * @return array{views: float, qty: float, listing_cvr: ?float}
     */
    public static function metricsFromSummary(array $sd): array
    {
        $qty = (float) ($sd['total_quantity'] ?? 0);
        if ($qty <= 0) {
            $qty = (float) ($sd['l30_orders'] ?? 0);
        }

        $listingCvr = null;
        if (array_key_exists('listing_cvr', $sd) && $sd['listing_cvr'] !== null && $sd['listing_cvr'] !== '') {
            $listingCvr = (float) $sd['listing_cvr'];
        }

        return [
            'views' => (float) ($sd['total_views'] ?? 0),
            'qty' => $qty,
            'listing_cvr' => $listingCvr,
        ];
    }

    public static function isCollapsed(
        float $candidateViews,
        float $baselineViews,
        float $candidateQty = 0.0,
        float $baselineQty = 0.0
    ): bool {
        if ($baselineViews <= 0) {
            return false;
        }
        if ($candidateViews <= 0) {
            return true;
        }
        if ($candidateViews >= $baselineViews * self::COLLAPSE_RATIO) {
            return false;
        }
        if ($baselineQty > 0 && $candidateQty > 0 && $candidateQty < $baselineQty * self::QTY_REAL_DROP_RATIO) {
            return false;
        }

        return true;
    }

    /**
     * Last snapshot before $beforeDate whose views are not themselves collapsed
     * versus the day before that.
     *
     * @return array{views: float, qty: float}|null
     */
    public static function lastTrusted(string $channel, ?string $beforeDate = null): ?array
    {
        $channel = strtolower(str_replace([' ', '-', '&', '/'], '', trim($channel)));
        if ($channel === '') {
            return null;
        }

        $query = ChannelMasterSummary::query()
            ->where('channel', $channel)
            ->orderByDesc('snapshot_date')
            ->limit(21);
        if ($beforeDate) {
            $query->whereDate('snapshot_date', '<', $beforeDate);
        }

        $points = [];
        foreach ($query->get() as $row) {
            $m = self::metricsFromSummary(ChannelMasterSummary::decodeSummaryData($row->summary_data ?? []));
            if ($m['views'] > 0) {
                $points[] = $m;
            }
        }

        $count = count($points);
        for ($i = 0; $i < $count; $i++) {
            $older = $points[$i + 1] ?? null;
            if ($older === null) {
                return ['views' => $points[$i]['views'], 'qty' => $points[$i]['qty']];
            }
            if (! self::isCollapsed($points[$i]['views'], $older['views'], $points[$i]['qty'], $older['qty'])) {
                return ['views' => $points[$i]['views'], 'qty' => $points[$i]['qty']];
            }
        }

        return null;
    }

    public static function stabilize(
        string $channel,
        float $candidateViews,
        float $candidateQty = 0.0,
        ?string $beforeDate = null
    ): float {
        $trusted = self::lastTrusted($channel, $beforeDate);
        if ($trusted === null) {
            return $candidateViews;
        }
        if (! self::isCollapsed($candidateViews, $trusted['views'], $candidateQty, $trusted['qty'])) {
            return $candidateViews;
        }

        Log::info('ChannelMasterViewsGuard carried views forward', [
            'channel' => $channel,
            'before' => $beforeDate,
            'candidate' => $candidateViews,
            'trusted' => $trusted['views'],
        ]);

        return $trusted['views'];
    }

    /**
     * Replace collapsed total_views on a summary. When listing_cvr was computed
     * from those views it is recomputed against the carried denominator.
     *
     * @param  array<string, mixed>  $sd
     * @return array<string, mixed>
     */
    public static function applyToSummary(array $sd, string $channel, ?string $beforeDate = null): array
    {
        $m = self::metricsFromSummary($sd);
        $stable = self::stabilize($channel, $m['views'], $m['qty'], $beforeDate);
        if ($stable <= 0 || abs($stable - $m['views']) < 0.5) {
            return $sd;
        }

        return self::rewriteViews($sd, $m, $stable);
    }

    /**
     * Same rewrite as applyToSummary, using an in-memory baseline (chart loops).
     *
     * @param  array<string, mixed>  $sd
     * @return array<string, mixed>
     */
    public static function carrySummary(array $sd, float $trustedViews): array
    {
        if ($trustedViews <= 0) {
            return $sd;
        }

        return self::rewriteViews($sd, self::metricsFromSummary($sd), $trustedViews);
    }

    /**
     * Walk rows oldest → newest and carry views across collapsed days (no extra queries).
     *
     * @param  iterable<int, ChannelMasterSummary>  $rows
     * @return array<int|string, array<string, mixed>>  keyed by model id
     */
    public static function stabilizeRowSummaries(iterable $rows): array
    {
        $chronological = [];
        foreach ($rows as $row) {
            $chronological[] = $row;
        }
        usort($chronological, function ($a, $b) {
            return strcmp((string) $a->snapshot_date, (string) $b->snapshot_date);
        });

        $out = [];
        $carryViews = null;
        $carryQty = null;
        foreach ($chronological as $row) {
            $sd = ChannelMasterSummary::decodeSummaryData($row->summary_data ?? []);
            $m = self::metricsFromSummary($sd);
            if ($carryViews !== null && self::isCollapsed($m['views'], $carryViews, $m['qty'], $carryQty ?? 0.0)) {
                $sd = self::rewriteViews($sd, $m, $carryViews);
            } elseif ($m['views'] > 0) {
                $carryViews = $m['views'];
                $carryQty = $m['qty'];
            }
            $out[$row->id] = $sd;
        }

        return $out;
    }

    /**
     * Persist carried views onto recent collapsed snapshots so charts, dots,
     * and rebuilds all read the same denominator.
     */
    public static function repairChannel(string $channel, int $days = 60): int
    {
        $channel = strtolower(str_replace([' ', '-', '&', '/'], '', trim($channel)));
        if ($channel === '') {
            return 0;
        }

        $from = now('America/Los_Angeles')->subDays($days)->toDateString();
        $rows = ChannelMasterSummary::query()
            ->where('channel', $channel)
            ->whereDate('snapshot_date', '>=', $from)
            ->orderBy('snapshot_date')
            ->get();

        $fixed = 0;
        $carryViews = null;
        $carryQty = null;
        foreach ($rows as $row) {
            $sd = ChannelMasterSummary::decodeSummaryData($row->summary_data ?? []);
            $m = self::metricsFromSummary($sd);
            if ($carryViews !== null && self::isCollapsed($m['views'], $carryViews, $m['qty'], $carryQty ?? 0.0)) {
                $sd = self::rewriteViews($sd, $m, $carryViews);
                $sd['views_carried_forward'] = true;
                $row->summary_data = $sd;
                $row->save();
                $fixed++;
                continue;
            }
            if ($m['views'] > 0) {
                $carryViews = $m['views'];
                $carryQty = $m['qty'];
            }
        }

        if ($fixed > 0) {
            Log::info('ChannelMasterViewsGuard repaired collapsed views', [
                'channel' => $channel,
                'days' => $fixed,
            ]);
        }

        return $fixed;
    }

    /**
     * @param  array<string, mixed>  $sd
     * @param  array{views: float, qty: float, listing_cvr: ?float}  $m
     * @return array<string, mixed>
     */
    private static function rewriteViews(array $sd, array $m, float $trustedViews): array
    {
        $sd['total_views'] = $trustedViews;
        if ($m['listing_cvr'] !== null && $m['views'] > 0 && $trustedViews > 0) {
            $impliedQty = ($m['listing_cvr'] / 100.0) * $m['views'];
            $recomputed = ($impliedQty / $trustedViews) * 100.0;
            if ($m['listing_cvr'] > $recomputed * 1.35) {
                $sd['listing_cvr'] = round($recomputed, 2);
            }
        }

        return $sd;
    }
}
