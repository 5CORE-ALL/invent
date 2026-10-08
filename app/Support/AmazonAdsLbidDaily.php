<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * One Lbid (live Amazon bid) value per campaign per calendar day, for the Lbid history dot.
 *
 * `last_sbid` on the report tables is overwritten on each live sync, so the daily value is
 * copied here: right after a verified live pull and again by the end-of-day snapshot command.
 * A later write on the same day replaces that day's value, so the day ends on its last live bid.
 */
final class AmazonAdsLbidDaily
{
    public const TABLE = 'amazon_ads_lbid_daily';

    /** A Lbid day is a California (Amazon Ads) calendar day, whatever the server or app timezone is. */
    public const TIMEZONE = 'America/Los_Angeles';

    /**
     * @return 'sp'|'sb'|null
     */
    public static function channelForTable(string $table): ?string
    {
        return match ($table) {
            'amazon_sp_campaign_reports' => 'sp',
            'amazon_sb_campaign_reports' => 'sb',
            default => null,
        };
    }

    public static function today(): string
    {
        return Carbon::now(self::TIMEZONE)->toDateString();
    }

    public static function available(): bool
    {
        try {
            return Schema::hasTable(self::TABLE);
        } catch (Throwable) {
            return false;
        }
    }

    public static function record(string $channel, string $campaignId, float $lbid, ?string $date = null): void
    {
        self::recordMany($channel, [$campaignId => $lbid], $date);
    }

    /**
     * @param  array<string, float|int|string>  $lbidByCampaignId
     */
    public static function recordMany(string $channel, array $lbidByCampaignId, ?string $date = null): int
    {
        if (! in_array($channel, ['sp', 'sb'], true) || $lbidByCampaignId === [] || ! self::available()) {
            return 0;
        }
        $date = $date ?? self::today();
        $now = now();
        $rows = [];
        foreach ($lbidByCampaignId as $cid => $value) {
            $cid = trim((string) $cid);
            if ($cid === '' || AmazonAdsStoredLiveBid::isBlank($value)) {
                continue;
            }
            $rows[] = [
                'channel' => $channel,
                'campaign_id' => $cid,
                'report_date' => $date,
                'lbid' => round((float) $value, 2),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if ($rows === []) {
            return 0;
        }
        try {
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table(self::TABLE)->upsert($chunk, ['channel', 'campaign_id', 'report_date'], ['lbid', 'updated_at']);
            }
        } catch (Throwable $e) {
            Log::warning('amazon-ads lbid daily: save failed', ['channel' => $channel, 'error' => $e->getMessage()]);

            return 0;
        }

        return count($rows);
    }

    /**
     * Copy each campaign's current Lbid from the report table onto today's row.
     * Current Lbid = newest calendar day with a bid, then L1, L30, L7 (same pick as the grid).
     */
    public static function snapshotFromReports(string $channel, string $onlyCampaignId = ''): int
    {
        $table = $channel === 'sb' ? 'amazon_sb_campaign_reports' : 'amazon_sp_campaign_reports';
        if (! self::available() || ! Schema::hasTable($table)
            || ! Schema::hasColumn($table, 'last_sbid') || ! Schema::hasColumn($table, 'campaign_id')) {
            return 0;
        }
        $since = Carbon::now(self::TIMEZONE)->subDays(14)->toDateString();
        $query = DB::table($table)
            ->whereNotNull('last_sbid')
            ->where('last_sbid', '!=', '')
            ->whereRaw('(last_sbid + 0) > 0')
            ->where(function ($w) use ($since) {
                $w->whereIn('report_date_range', ['L1', 'L30', 'L7'])
                    ->orWhere(function ($d) use ($since) {
                        $d->whereRaw('CHAR_LENGTH(report_date_range) = 10')
                            ->where('report_date_range', '>=', $since);
                    });
            });
        if ($onlyCampaignId !== '') {
            $query->where('campaign_id', $onlyCampaignId);
        }

        /** @var array<string, list<array{report_date_range: string, last_sbid: mixed}>> $byCampaign */
        $byCampaign = [];
        $query->orderBy('id')->select(['id', 'campaign_id', 'report_date_range', 'last_sbid'])
            ->chunkById(5000, function ($rows) use (&$byCampaign) {
                foreach ($rows as $row) {
                    $cid = trim((string) $row->campaign_id);
                    if ($cid === '') {
                        continue;
                    }
                    $byCampaign[$cid][] = [
                        'report_date_range' => (string) $row->report_date_range,
                        'last_sbid' => $row->last_sbid,
                    ];
                }
            });

        $picked = [];
        foreach ($byCampaign as $cid => $candidates) {
            $lbid = AmazonAdsStoredLiveBid::pick($candidates);
            if ($lbid !== null) {
                $picked[$cid] = $lbid;
            }
        }

        return self::recordMany($channel, $picked);
    }

    /**
     * @return array<string, float> Y-m-d => Lbid
     */
    public static function historyByDate(string $channel, string $campaignId): array
    {
        if (! in_array($channel, ['sp', 'sb'], true) || ! self::available()) {
            return [];
        }
        $out = [];
        $rows = DB::table(self::TABLE)
            ->where('channel', $channel)
            ->where('campaign_id', $campaignId)
            ->orderBy('report_date')
            ->get(['report_date', 'lbid']);
        foreach ($rows as $row) {
            $day = substr((string) $row->report_date, 0, 10);
            if ($day !== '' && is_numeric($row->lbid)) {
                $out[$day] = round((float) $row->lbid, 2);
            }
        }

        return $out;
    }

    /**
     * Most recent saved Lbid strictly before today, per campaign.
     *
     * @param  list<string>  $campaignIds
     * @return array<string, float>
     */
    public static function previousByCampaign(string $channel, array $campaignIds): array
    {
        if (! in_array($channel, ['sp', 'sb'], true) || $campaignIds === [] || ! self::available()) {
            return [];
        }
        $today = self::today();
        $out = [];
        foreach (array_chunk($campaignIds, 200) as $chunk) {
            $maxes = DB::table(self::TABLE)
                ->select('campaign_id', DB::raw('MAX(report_date) as md'))
                ->where('channel', $channel)
                ->whereIn('campaign_id', $chunk)
                ->where('report_date', '<', $today)
                ->groupBy('campaign_id')
                ->get();
            if ($maxes->isEmpty()) {
                continue;
            }
            $found = DB::table(self::TABLE)
                ->where('channel', $channel)
                ->where(function ($q) use ($maxes) {
                    foreach ($maxes as $max) {
                        $q->orWhere(function ($w) use ($max) {
                            $w->where('campaign_id', $max->campaign_id)->where('report_date', $max->md);
                        });
                    }
                })
                ->get(['campaign_id', 'lbid']);
            foreach ($found as $row) {
                if (is_numeric($row->lbid)) {
                    $out[(string) $row->campaign_id] = round((float) $row->lbid, 2);
                }
            }
        }

        return $out;
    }
}
