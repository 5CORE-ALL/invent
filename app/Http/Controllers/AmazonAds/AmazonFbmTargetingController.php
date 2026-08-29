<?php

namespace App\Http\Controllers\AmazonAds;

use App\Http\Controllers\Controller;
use App\Services\AmazonAdsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Amz FBM Targeting grid: campaign names from the same SP reports table as /amazon-ads/all,
 * targets from the Amazon Ads SP keywords + targeting APIs.
 */
class AmazonFbmTargetingController extends Controller
{
    private const SP_TABLE = 'amazon_sp_campaign_reports';

    private const KW_TABLE = 'amazon_sp_keyword_reports';

    public function __construct(private AmazonAdsService $ads)
    {
    }

    public function index()
    {
        return view('amazon_ads.fbm-targeting');
    }

    public function data(Request $request): JsonResponse
    {
        if (! Schema::hasTable(self::SP_TABLE) || ! Schema::hasColumn(self::SP_TABLE, 'campaignName')) {
            return response()->json(['success' => true, 'data' => [], 'last_page' => 1, 'total' => 0]);
        }

        $page = max(1, (int) $request->query('page', 1));
        $size = min(100, max(1, (int) $request->query('size', 50)));
        $search = trim((string) $request->query('campaign', ''));

        $base = DB::table(self::SP_TABLE)
            ->whereNotNull('campaignName')
            ->where('campaignName', '!=', '');

        if ($search !== '') {
            $base->whereRaw('LOWER(campaignName) LIKE ?', ['%'.strtolower($search).'%']);
        }

        $countQuery = (clone $base)
            ->select('campaignName')
            ->groupBy('campaignName');
        $total = (int) DB::query()->fromSub($countQuery, 't')->count();

        $rows = (clone $base)
            ->selectRaw('campaignName AS campaign_name, MAX(campaign_id) AS campaign_id')
            ->groupBy('campaignName')
            ->orderBy('campaignName')
            ->forPage($page, $size)
            ->get();

        $campaignIds = $rows->map(static fn ($r) => trim((string) ($r->campaign_id ?? '')))
            ->filter(static fn (string $id) => $id !== '')
            ->values()
            ->all();

        $targetsByCampaign = $this->targetsForCampaignIds($campaignIds);

        $data = $rows->map(function ($row) use ($targetsByCampaign) {
            $id = trim((string) ($row->campaign_id ?? ''));
            $name = trim((string) ($row->campaign_name ?? ''));
            $targets = $targetsByCampaign[$id] ?? ($targetsByCampaign[strtolower($name)] ?? []);

            return [
                'campaign_id' => $id,
                'campaign_name' => $name,
                'targets' => $targets,
                'target_count' => count($targets),
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'data' => $data,
            'last_page' => max(1, (int) ceil($total / $size)),
            'total' => $total,
        ]);
    }

    /**
     * @param  list<string>  $campaignIds
     * @return array<string, list<string>>
     */
    protected function targetsForCampaignIds(array $campaignIds): array
    {
        $campaignIds = array_values(array_unique(array_filter($campaignIds)));
        if ($campaignIds === []) {
            return [];
        }

        $cacheKey = 'amz_fbm_targeting_v1:'.md5(implode(',', $campaignIds));
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $map = $this->targetsFromKeywordReports($campaignIds);

        try {
            foreach ($this->ads->listKeywordsByCampaignIds($campaignIds) as $kw) {
                $cid = trim((string) ($kw['campaignId'] ?? ''));
                $label = $this->formatKeywordTarget($kw);
                if ($cid === '' || $label === '') {
                    continue;
                }
                $map[$cid] = $map[$cid] ?? [];
                if (! in_array($label, $map[$cid], true)) {
                    $map[$cid][] = $label;
                }
            }
            foreach ($this->ads->listTargetsByCampaignIds($campaignIds) as $target) {
                $cid = trim((string) ($target['campaignId'] ?? ''));
                $label = $this->formatProductTarget($target);
                if ($cid === '' || $label === '') {
                    continue;
                }
                $map[$cid] = $map[$cid] ?? [];
                if (! in_array($label, $map[$cid], true)) {
                    $map[$cid][] = $label;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('AmazonFbmTargetingController: Amazon targeting API failed', [
                'error' => $e->getMessage(),
            ]);
        }

        foreach ($map as $cid => $labels) {
            sort($labels, SORT_NATURAL | SORT_FLAG_CASE);
            $map[$cid] = array_values($labels);
        }

        Cache::put($cacheKey, $map, now()->addMinutes(10));

        return $map;
    }

    /**
     * Fallback from the same keyword reports used on Ads All (SP keywords table).
     *
     * @param  list<string>  $campaignIds
     * @return array<string, list<string>>
     */
    protected function targetsFromKeywordReports(array $campaignIds): array
    {
        if (! Schema::hasTable(self::KW_TABLE)) {
            return [];
        }

        $cols = Schema::getColumnListing(self::KW_TABLE);
        $labelCol = in_array('keyword', $cols, true) ? 'keyword' : (in_array('targeting', $cols, true) ? 'targeting' : null);
        if ($labelCol === null || ! in_array('campaign_id', $cols, true)) {
            return [];
        }

        $q = DB::table(self::KW_TABLE)
            ->whereIn('campaign_id', $campaignIds)
            ->whereNotNull($labelCol)
            ->where($labelCol, '!=', '');

        $select = ['campaign_id', $labelCol.' as label'];
        if (in_array('matchType', $cols, true)) {
            $select[] = 'matchType';
        }

        $map = [];
        foreach ($q->get($select) as $row) {
            $cid = trim((string) ($row->campaign_id ?? ''));
            $label = trim((string) ($row->label ?? ''));
            $match = trim((string) ($row->matchType ?? ''));
            if ($cid === '' || $label === '') {
                continue;
            }
            if ($match !== '') {
                $label .= ' ('.$match.')';
            }
            $map[$cid] = $map[$cid] ?? [];
            if (! in_array($label, $map[$cid], true)) {
                $map[$cid][] = $label;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $kw
     */
    protected function formatKeywordTarget(array $kw): string
    {
        $text = trim((string) ($kw['keywordText'] ?? $kw['keyword'] ?? ''));
        if ($text === '') {
            return '';
        }
        $match = trim((string) ($kw['matchType'] ?? ''));

        return $match !== '' ? $text.' ('.$match.')' : $text;
    }

    /**
     * @param  array<string, mixed>  $target
     */
    protected function formatProductTarget(array $target): string
    {
        $expression = $target['expression'] ?? $target['resolvedExpression'] ?? [];
        if (is_string($expression) && trim($expression) !== '') {
            return trim($expression);
        }
        if (! is_array($expression)) {
            return trim((string) ($target['expressionType'] ?? ''));
        }

        $parts = [];
        foreach ($expression as $clause) {
            if (! is_array($clause)) {
                continue;
            }
            $type = trim((string) ($clause['type'] ?? ''));
            $value = trim((string) ($clause['value'] ?? ''));
            if ($type !== '' && $value !== '') {
                $parts[] = $type.': '.$value;
            } elseif ($type !== '') {
                $parts[] = $type;
            } elseif ($value !== '') {
                $parts[] = $value;
            }
        }

        if ($parts !== []) {
            return implode(' · ', $parts);
        }

        return trim((string) ($target['expressionType'] ?? ''));
    }
}
