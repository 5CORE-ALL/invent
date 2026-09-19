<?php

namespace App\Support\Marketplace;

use App\Models\AmazonAdsMissingLink;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shared amazon-ads/missing campaign-link helpers.
 * Same table (amazon_ads_missing_links) and status rules as AmazonAdsMissingController.
 */
class AmazonAdsMissingLinks
{
    public const SP_TABLE = 'amazon_sp_campaign_reports';

    public static function skuForParent(string $parent): string
    {
        $parent = preg_replace('/\s+/', ' ', trim($parent));

        return $parent === '' ? '' : 'PARENT '.$parent;
    }

    /**
     * @return Collection<string, Collection<int, AmazonAdsMissingLink>>
     */
    public static function groupedBySku(): Collection
    {
        if (! Schema::hasTable('amazon_ads_missing_links')) {
            return collect();
        }

        return AmazonAdsMissingLink::query()
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($l) => (string) $l->sku);
    }

    /**
     * @param  list<string>  $parents
     * @return array<string, array{kw: array<int, array<string, mixed>>, pt: array<int, array<string, mixed>>}>
     */
    public static function listsByParent(array $parents): array
    {
        $linksBySku = self::groupedBySku();
        $statusMap = self::campaignStatusMap();
        $out = [];

        foreach ($parents as $parent) {
            $parent = trim((string) $parent);
            if ($parent === '') {
                continue;
            }
            $sku = self::skuForParent($parent);
            $links = $sku !== '' ? $linksBySku->get($sku, collect()) : collect();
            $out[$parent] = [
                'kw' => self::linkListForType($links, 'KW', $statusMap),
                'pt' => self::linkListForType($links, 'PT', $statusMap),
            ];
        }

        return $out;
    }

    /**
     * @return array{ok: bool, sku: string, pt: array, kw: array}
     */
    public static function listsResponseForSku(string $sku): array
    {
        $links = Schema::hasTable('amazon_ads_missing_links')
            ? AmazonAdsMissingLink::query()->where('sku', $sku)->orderBy('id')->get()
            : collect();
        $statusMap = self::campaignStatusMap();

        return [
            'ok' => true,
            'sku' => $sku,
            'pt' => self::linkListForType($links, 'PT', $statusMap),
            'kw' => self::linkListForType($links, 'KW', $statusMap),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection|iterable  $links
     * @param  array<string, string>  $statusMap
     * @return array<int, array{id: int, campaign_id: mixed, campaign_name: string, status: string, dot: string, page_created: bool, created_at: string, created_by: string}>
     */
    public static function linkListForType($links, string $type, array $statusMap = []): array
    {
        $rows = collect($links)->filter(fn ($l) => (string) ($l->type ?? '') === $type)->values();
        $userIds = $rows
            ->map(fn ($l) => (int) ($l->user_id ?? 0))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        $names = self::namesForUserIds($userIds);

        return $rows
            ->map(function ($l) use ($statusMap, $names) {
                $name = (string) ($l->campaign_name ?? '');
                $status = $statusMap[self::normalizeCampaignName($name)] ?? '';
                $dot = $status === 'ENABLED' ? 'green' : ($status !== '' ? 'red' : '');
                $userId = (int) ($l->user_id ?? 0);
                $createdBy = $userId > 0 ? ($names[$userId] ?? ('User #'.$userId)) : '';

                return [
                    'id' => (int) ($l->id ?? 0),
                    'campaign_id' => $l->campaign_id ?? null,
                    'campaign_name' => $name,
                    'status' => $status,
                    'dot' => $dot,
                    'page_created' => (bool) ($l->page_created ?? false),
                    'created_at' => self::formatCreatedAt($l->created_at ?? null),
                    'created_by' => $createdBy,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private static function namesForUserIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }

        try {
            if (! Schema::hasTable('users')) {
                return [];
            }

            return User::query()
                ->whereIn('id', $ids)
                ->get(['id', 'name', 'email'])
                ->mapWithKeys(function ($user) {
                    $label = trim((string) ($user->name ?? ''));
                    if ($label === '') {
                        $label = trim((string) ($user->email ?? ''));
                    }
                    if ($label === '') {
                        $label = 'User #'.(int) $user->id;
                    }

                    return [(int) $user->id => $label];
                })
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function formatCreatedAt(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        try {
            $dt = $value instanceof \DateTimeInterface
                ? Carbon::instance(\DateTimeImmutable::createFromInterface($value))
                : Carbon::parse((string) $value);
            $tz = 'America/Los_Angeles';
            if (function_exists('config')) {
                $cfg = config('app.timezone');
                if (is_string($cfg) && $cfg !== '') {
                    $tz = $cfg;
                }
            }

            return $dt->timezone($tz)->format('M j, Y g:i A');
        } catch (\Throwable $e) {
            return trim((string) $value);
        }
    }

    /**
     * Latest campaignStatus (ENABLED / PAUSED / …) per SP campaign, keyed by normalized name.
     *
     * @return array<string, string>
     */
    public static function campaignStatusMap(): array
    {
        if (! Schema::hasTable(self::SP_TABLE)
            || ! Schema::hasColumn(self::SP_TABLE, 'campaignName')
            || ! Schema::hasColumn(self::SP_TABLE, 'campaignStatus')) {
            return [];
        }

        $latestIds = DB::table(self::SP_TABLE)
            ->whereNotNull('campaignName')
            ->where('campaignName', '!=', '')
            ->selectRaw('MAX(id) AS max_id')
            ->groupBy('campaignName')
            ->pluck('max_id')
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->all();

        if ($latestIds === []) {
            return [];
        }

        $map = [];
        DB::table(self::SP_TABLE)
            ->whereIn('id', $latestIds)
            ->get(['campaignName', 'campaignStatus'])
            ->each(function ($row) use (&$map) {
                $key = self::normalizeCampaignName((string) ($row->campaignName ?? ''));
                if ($key === '') {
                    return;
                }
                $map[$key] = strtoupper(trim((string) ($row->campaignStatus ?? '')));
            });

        return $map;
    }

    public static function normalizeCampaignName(string $name): string
    {
        return strtoupper(rtrim(preg_replace('/\s+/', ' ', trim($name)), '.'));
    }
}
