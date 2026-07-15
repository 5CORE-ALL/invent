<?php

namespace App\Http\Controllers\AmazonAds;

use App\Http\Controllers\Controller;
use App\Services\AmazonCampaignLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Campaign-name linking for Amazon SP campaigns (source: amazon_sp_keyword_reports).
 * Mirrors /purchase-master/sku-link-lmp: a grid of campaigns where each can be linked into a
 * group. Linked campaigns will later share keywords when pushed together.
 */
class AmazonCampaignLinkController extends Controller
{
    private const SOURCE_TABLE = 'amazon_sp_keyword_reports';

    public function __construct(private AmazonCampaignLinkService $linkService)
    {
    }

    public function index()
    {
        return view('amazon_ads.campaign-link.index');
    }

    /**
     * Paginated distinct SP campaigns with keyword counts and their linked-campaign group.
     */
    public function getData(Request $request): JsonResponse
    {
        try {
            if (! Schema::hasTable(self::SOURCE_TABLE)) {
                return response()->json(['success' => true, 'data' => [], 'last_page' => 1, 'total' => 0]);
            }

            $page = max(1, (int) $request->query('page', 1));
            $size = min(200, max(1, (int) $request->query('size', 50)));
            $search = trim((string) $request->query('campaign', ''));

            $base = DB::table(self::SOURCE_TABLE)
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
                ->select('campaignName', DB::raw('COUNT(DISTINCT keyword_id) AS keyword_count'))
                ->groupBy('campaignName')
                ->orderBy('campaignName')
                ->forPage($page, $size)
                ->get();

            $groups = $this->linkService->groupsMap();

            $data = $rows->map(function ($row) use ($groups) {
                $name = (string) $row->campaignName;
                $group = $groups[$this->linkService->normalize($name)] ?? [$name];

                return [
                    'campaign' => $name,
                    'keyword_count' => (int) $row->keyword_count,
                    'linked_campaigns' => $group,
                    'linked_count' => max(0, count($group) - 1),
                ];
            })->values()->all();

            return response()->json([
                'success' => true,
                'data' => $data,
                'last_page' => max(1, (int) ceil($total / $size)),
                'total' => $total,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Distinct campaign names for the link modal search (excludes the source campaign).
     */
    public function getCampaigns(Request $request): JsonResponse
    {
        try {
            if (! Schema::hasTable(self::SOURCE_TABLE)) {
                return response()->json(['success' => true, 'campaigns' => []]);
            }

            $search = trim((string) $request->query('q', ''));
            $exclude = trim((string) $request->query('exclude', ''));

            $query = DB::table(self::SOURCE_TABLE)
                ->whereNotNull('campaignName')
                ->where('campaignName', '!=', '');

            if ($search !== '') {
                $query->whereRaw('LOWER(campaignName) LIKE ?', ['%'.strtolower($search).'%']);
            }
            if ($exclude !== '') {
                $query->whereRaw('LOWER(campaignName) <> ?', [strtolower($exclude)]);
            }

            $campaigns = $query->distinct()
                ->orderBy('campaignName')
                ->limit(50)
                ->pluck('campaignName')
                ->map(fn ($c) => trim((string) $c))
                ->filter()
                ->values()
                ->all();

            return response()->json(['success' => true, 'campaigns' => $campaigns]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Keywords for one campaign (for the "click the number" modal). Prefers the L30 summary
     * row per keyword, else the most recent row, so each keyword shows one line with metrics.
     */
    public function getKeywords(Request $request): JsonResponse
    {
        try {
            $campaign = trim((string) $request->query('campaign', ''));
            if ($campaign === '' || ! Schema::hasTable(self::SOURCE_TABLE)) {
                return response()->json(['success' => true, 'campaign' => $campaign, 'keywords' => []]);
            }

            $cols = Schema::getColumnListing(self::SOURCE_TABLE);
            $has = fn (string $c) => in_array($c, $cols, true);

            // One row per keyword: prefer its L30 summary row, else the latest (highest id).
            $pickIds = DB::table(self::SOURCE_TABLE)
                ->where('campaignName', $campaign)
                ->selectRaw("MAX(CASE WHEN report_date_range = 'L30' THEN id END) AS l30_id, MAX(id) AS latest_id")
                ->groupBy('keyword_id', 'targeting')
                ->get()
                ->map(fn ($r) => $r->l30_id ?? $r->latest_id)
                ->filter()
                ->values();

            if ($pickIds->isEmpty()) {
                return response()->json(['success' => true, 'campaign' => $campaign, 'keywords' => []]);
            }

            $select = ['keyword', 'targeting', 'matchType', 'keyword_id', 'report_date_range'];
            foreach (['impressions', 'clicks', 'cost', 'costPerClick', 'purchases30d', 'sales30d', 'acosClicks14d'] as $m) {
                if ($has($m)) {
                    $select[] = $m;
                }
            }

            $rows = DB::table(self::SOURCE_TABLE)
                ->whereIn('id', $pickIds)
                ->orderByRaw("CASE WHEN report_date_range = 'L30' THEN 0 ELSE 1 END")
                ->orderByDesc('id')
                ->get($select);

            // De-dupe by keyword text/targeting, preferring the L30 row already ordered first.
            $seen = [];
            $keywords = [];
            foreach ($rows as $r) {
                $label = trim((string) ($r->keyword ?? $r->targeting ?? ''));
                if ($label === '') {
                    continue;
                }
                $key = strtoupper($label).'|'.strtoupper((string) ($r->matchType ?? ''));
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $keywords[] = [
                    'keyword' => $label,
                    'match_type' => $r->matchType ?? null,
                    'impressions' => isset($r->impressions) ? (int) $r->impressions : null,
                    'clicks' => isset($r->clicks) ? (int) $r->clicks : null,
                    'cost' => isset($r->cost) ? round((float) $r->cost, 2) : null,
                    'cpc' => isset($r->costPerClick) ? round((float) $r->costPerClick, 2) : null,
                    'sold' => isset($r->purchases30d) ? (int) $r->purchases30d : null,
                    'sales' => isset($r->sales30d) ? round((float) $r->sales30d, 2) : null,
                    'acos' => isset($r->acosClicks14d) ? round((float) $r->acosClicks14d, 2) : null,
                ];
            }

            usort($keywords, fn ($a, $b) => strcasecmp($a['keyword'], $b['keyword']));

            return response()->json([
                'success' => true,
                'campaign' => $campaign,
                'count' => count($keywords),
                'keywords' => $keywords,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Push (import) all keywords from a campaign's linked group INTO that campaign.
     *
     * Destination = the clicked campaign; sources = its linked campaigns. Missing keywords
     * (by keyword text + match type, deduped) are created in the destination campaign's ad group
     * on Amazon via the SP keywords API, then mirrored into amazon_sp_keyword_reports so the grid
     * count updates immediately. Only real keywords (BROAD / PHRASE / EXACT) are copied.
     */
    public function pushLinked(Request $request): JsonResponse
    {
        $campaign = trim((string) $request->input('campaign', ''));
        if ($campaign === '' || ! Schema::hasTable(self::SOURCE_TABLE)) {
            return response()->json(['success' => false, 'message' => 'Campaign is required.'], 422);
        }

        $r = $this->pushForCampaign($campaign);

        return response()->json([
            'success' => $r['ok'],
            'added' => $r['added'],
            'failed' => $r['failed'],
            'dest_live_count' => $r['dest_live_count'] ?? null,
            'message' => $r['message'],
            'affected' => $this->affectedRows($campaign),
        ], $r['ok'] ? 200 : ($r['status'] ?? 422));
    }

    /**
     * Bulk push: run the import for every campaign that currently has a linked group.
     */
    public function pushAll(Request $request): JsonResponse
    {
        if (! Schema::hasTable(self::SOURCE_TABLE)) {
            return response()->json(['success' => false, 'message' => 'Keyword report table missing.'], 422);
        }

        $groups = $this->linkService->groupsMap();
        // One representative per group is enough? No — each linked campaign should receive the union.
        // Push into every campaign that has at least one linked partner.
        $campaigns = [];
        foreach ($groups as $members) {
            if (count($members) < 2) {
                continue;
            }
            foreach ($members as $m) {
                $campaigns[$m] = true;
            }
        }
        $campaigns = array_keys($campaigns);

        if ($campaigns === []) {
            return response()->json(['success' => true, 'added' => 0, 'processed' => 0, 'message' => 'No linked campaigns to push.']);
        }

        $totalAdded = 0;
        $totalFailed = 0;
        $processed = 0;
        $errors = [];
        foreach ($campaigns as $campaign) {
            $r = $this->pushForCampaign($campaign);
            $processed++;
            $totalAdded += $r['added'];
            $totalFailed += $r['failed'];
            if (! $r['ok'] && $r['added'] === 0 && $r['failed'] === 0 && ! str_contains($r['message'], 'No new')) {
                $errors[] = $campaign.': '.$r['message'];
            }
        }

        $msg = "Bulk push complete: added {$totalAdded} keyword(s) across {$processed} campaign(s).";
        if ($totalFailed > 0) {
            $msg .= " ({$totalFailed} rejected/skipped.)";
        }

        return response()->json([
            'success' => true,
            'added' => $totalAdded,
            'failed' => $totalFailed,
            'processed' => $processed,
            'message' => $msg,
            'errors' => array_slice($errors, 0, 10),
        ]);
    }

    /**
     * Merge keywords across an explicit list of campaigns: link them into one group, then
     * push the union into every campaign (duplicates by keyword text + match type are skipped).
     */
    public function mergeCampaigns(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'campaigns' => ['required', 'array', 'min:2'],
            'campaigns.*' => ['string', 'max:255'],
        ]);

        if (! Schema::hasTable(self::SOURCE_TABLE)) {
            return response()->json(['success' => false, 'message' => 'Keyword report table missing.'], 422);
        }

        $result = $this->runMerge($validated['campaigns']);

        return response()->json($result, ($result['success'] ?? false) ? 200 : ($result['status'] ?? 422));
    }

    /**
     * @param  list<string>  $campaigns
     * @return array{success: bool, added: int, failed: int, processed: int, campaigns: list<string>, message: string, errors?: list<string>, status?: int}
     */
    public function runMerge(array $campaigns): array
    {
        $names = [];
        $seen = [];
        foreach ($campaigns as $campaign) {
            $name = trim((string) $campaign);
            if ($name === '') {
                continue;
            }
            $norm = $this->linkService->normalize($name);
            if (isset($seen[$norm])) {
                continue;
            }
            $seen[$norm] = true;
            $names[] = $name;
        }

        if (count($names) < 2) {
            return [
                'success' => false,
                'added' => 0,
                'failed' => 0,
                'processed' => 0,
                'campaigns' => $names,
                'message' => 'At least two linked campaigns are required to merge keywords.',
                'status' => 422,
            ];
        }

        $this->linkService->syncFullyConnectedGroup($names, Auth::user()?->name);

        $totalAdded = 0;
        $totalFailed = 0;
        $processed = 0;
        $errors = [];
        $details = [];

        foreach ($names as $campaign) {
            $r = $this->pushForCampaign($campaign);
            $processed++;
            $totalAdded += (int) ($r['added'] ?? 0);
            $totalFailed += (int) ($r['failed'] ?? 0);
            $details[] = [
                'campaign' => $campaign,
                'added' => (int) ($r['added'] ?? 0),
                'failed' => (int) ($r['failed'] ?? 0),
                'message' => (string) ($r['message'] ?? ''),
            ];
            if (! ($r['ok'] ?? false) && (int) ($r['added'] ?? 0) === 0 && (int) ($r['failed'] ?? 0) === 0
                && ! str_contains((string) ($r['message'] ?? ''), 'No new')
                && ! str_contains((string) ($r['message'] ?? ''), 'already has all')) {
                $errors[] = $campaign.': '.($r['message'] ?? 'Failed');
            }
        }

        $msg = "Merge complete: added {$totalAdded} keyword(s) across {$processed} campaign(s). Duplicates were skipped.";
        if ($totalFailed > 0) {
            $msg .= " ({$totalFailed} rejected/skipped by Amazon.)";
        }

        return [
            'success' => true,
            'added' => $totalAdded,
            'failed' => $totalFailed,
            'processed' => $processed,
            'campaigns' => $names,
            'details' => $details,
            'message' => $msg,
            'errors' => array_slice($errors, 0, 10),
        ];
    }

    /**
     * Core import for one destination campaign. Returns a normalized result array.
     *
     * @return array{ok: bool, added: int, failed: int, message: string, status?: int}
     */
    /**
     * Live comparison between a destination campaign and its linked group. Determines the keywords
     * the linked campaigns have that the destination is missing — checked against the destination's
     * LIVE Amazon keyword list (not just our partial report data).
     *
     * @return array{
     *   ok: bool, message?: string, status?: int,
     *   destCampaignId?: string, destAdGroupId?: string, linked?: list<string>,
     *   missing?: list<array{keywordText: string, matchType: string}>,
     *   destLiveCount?: int, liveOk?: bool, groupSourceCount?: int
     * }
     */
    private function resolveMissing(string $campaign): array
    {
        $group = $this->linkService->groupContaining($campaign);
        $linked = array_values(array_filter($group, fn ($c) => $this->linkService->normalize($c) !== $this->linkService->normalize($campaign)));
        if ($linked === []) {
            return ['ok' => false, 'message' => 'This campaign has no linked campaign to push from.', 'status' => 422];
        }

        $destInfo = DB::table(self::SOURCE_TABLE)
            ->where('campaignName', $campaign)
            ->whereNotNull('campaign_id')
            ->whereNotNull('ad_group_id')
            ->whereNotNull('keyword_id')
            ->whereIn(DB::raw('UPPER(matchType)'), ['BROAD', 'PHRASE', 'EXACT'])
            ->selectRaw('campaign_id, ad_group_id, COUNT(*) AS c')
            ->groupBy('campaign_id', 'ad_group_id')
            ->orderByDesc('c')
            ->first();

        if (! $destInfo || ! $destInfo->campaign_id || ! $destInfo->ad_group_id) {
            return ['ok' => false, 'message' => 'Could not resolve the destination campaign\'s ad group from keyword data.', 'status' => 422];
        }
        $destCampaignId = (string) $destInfo->campaign_id;
        $destAdGroupId = (string) $destInfo->ad_group_id;

        // Destination existing set — report table first…
        $existing = [];
        DB::table(self::SOURCE_TABLE)
            ->where('campaignName', $campaign)
            ->whereNotNull('keyword')
            ->select('keyword', 'matchType')
            ->distinct()
            ->get()
            ->each(function ($r) use (&$existing) {
                $existing[$this->keywordKey($r->keyword, $r->matchType)] = true;
            });

        // …then the destination's LIVE Amazon keywords (authoritative).
        $liveOk = false;
        $destLiveCount = 0;
        try {
            $live = $this->fetchLiveCampaignKeywordSet($destCampaignId);
            $liveOk = true;
            $destLiveCount = count($live);
            foreach ($live as $k => $_) {
                $existing[$k] = true;
            }
        } catch (\Throwable) {
            // fall back to stored set
        }

        // Linked group's keywords (real keywords only), deduped, minus what the destination has.
        $candidates = [];
        $groupSourceKeys = [];
        DB::table(self::SOURCE_TABLE)
            ->whereIn('campaignName', $linked)
            ->whereNotNull('keyword_id')
            ->whereNotNull('keyword')
            ->whereIn(DB::raw('UPPER(matchType)'), ['BROAD', 'PHRASE', 'EXACT'])
            ->select('keyword', 'matchType')
            ->distinct()
            ->get()
            ->each(function ($r) use (&$candidates, &$groupSourceKeys, $existing) {
                $text = trim((string) $r->keyword);
                $mt = strtoupper(trim((string) $r->matchType));
                if ($text === '' || $mt === '') {
                    return;
                }
                $key = $this->keywordKey($text, $mt);
                $groupSourceKeys[$key] = true;
                if (isset($existing[$key]) || isset($candidates[$key])) {
                    return;
                }
                $candidates[$key] = ['keywordText' => $text, 'matchType' => $mt];
            });

        return [
            'ok' => true,
            'destCampaignId' => $destCampaignId,
            'destAdGroupId' => $destAdGroupId,
            'linked' => $linked,
            'missing' => array_values($candidates),
            'destLiveCount' => $destLiveCount,
            'liveOk' => $liveOk,
            'groupSourceCount' => count($groupSourceKeys),
        ];
    }

    /**
     * Live compare (no writes): returns the actual missing keywords + the destination's live count,
     * so the UI can show exactly why a push would/wouldn't add anything.
     */
    public function compare(Request $request): JsonResponse
    {
        $campaign = trim((string) $request->input('campaign', ''));
        if ($campaign === '' || ! Schema::hasTable(self::SOURCE_TABLE)) {
            return response()->json(['success' => false, 'message' => 'Campaign is required.'], 422);
        }

        $r = $this->resolveMissing($campaign);
        if (! $r['ok']) {
            return response()->json(['success' => false, 'message' => $r['message']], $r['status'] ?? 422);
        }

        return response()->json([
            'success' => true,
            'campaign' => $campaign,
            'linked_campaigns' => $r['linked'],
            'dest_live_count' => $r['destLiveCount'],
            'live_ok' => $r['liveOk'],
            'group_source_count' => $r['groupSourceCount'],
            'missing' => $r['missing'],
            'missing_count' => count($r['missing']),
        ]);
    }

    private function pushForCampaign(string $campaign): array
    {
        $resolved = $this->resolveMissing($campaign);
        if (! $resolved['ok']) {
            return ['ok' => false, 'added' => 0, 'failed' => 0, 'message' => $resolved['message'], 'status' => $resolved['status'] ?? 422];
        }

        $destCampaignId = $resolved['destCampaignId'];
        $destAdGroupId = $resolved['destAdGroupId'];
        $linked = $resolved['linked'];
        $toAdd = $resolved['missing'];
        $destLiveCount = $resolved['destLiveCount'];

        if ($toAdd === []) {
            $where = $resolved['liveOk'] ? ' (verified live on Amazon)' : '';
            return ['ok' => true, 'added' => 0, 'failed' => 0, 'dest_live_count' => $destLiveCount,
                'message' => "\"{$campaign}\" already has all keywords from its linked campaign(s){$where} — nothing new to add."];
        }

        try {
            $result = $this->createSpKeywords($destCampaignId, $destAdGroupId, $toAdd);
        } catch (\Throwable $e) {
            return ['ok' => false, 'added' => 0, 'failed' => 0, 'message' => 'Amazon push failed: '.$e->getMessage(), 'status' => 500];
        }

        // Mirror successfully-created keywords into the report table so the count reflects now.
        $profileId = (string) config('services.amazon_ads.profile_ids');
        foreach ($result['created'] as $c) {
            \App\Models\AmazonSpKeywordReport::updateOrCreate(
                ['profile_id' => $profileId, 'report_date_range' => 'L1', 'keyword_id' => (string) $c['keywordId'], 'targeting' => $c['keywordText']],
                [
                    'ad_type' => 'SPONSORED_PRODUCTS',
                    'campaign_id' => $destCampaignId,
                    'campaignName' => $campaign,
                    'ad_group_id' => $destAdGroupId,
                    'keyword' => $c['keywordText'],
                    'matchType' => $c['matchType'],
                    'adKeywordStatus' => 'ENABLED',
                    'campaignStatus' => 'ENABLED',
                ]
            );
        }

        $added = count($result['created']);
        $failed = $result['failed'];
        $duplicates = $result['duplicates'] ?? 0;
        $newLiveCount = $destLiveCount + $added;
        $errText = ! empty($result['errors']) ? ' Reason: '.implode(' | ', array_slice($result['errors'], 0, 3)) : '';

        if ($added === 0) {
            if ($failed > 0) {
                return ['ok' => false, 'added' => 0, 'failed' => $failed, 'dest_live_count' => $newLiveCount, 'message' => "No keywords were added ({$failed} rejected by Amazon).".$errText];
            }
            if ($duplicates > 0) {
                return ['ok' => true, 'added' => 0, 'failed' => 0, 'dest_live_count' => $newLiveCount, 'message' => "All {$duplicates} keyword(s) already exist in \"{$campaign}\" — nothing new to add."];
            }

            return ['ok' => true, 'added' => 0, 'failed' => 0, 'dest_live_count' => $newLiveCount, 'message' => 'No new keywords to push.'];
        }

        $msg = "Added {$added} keyword(s) to \"{$campaign}\" from ".count($linked).' linked campaign(s).';
        if ($duplicates > 0) {
            $msg .= " ({$duplicates} already existed.)";
        }
        if ($failed > 0) {
            $msg .= " ({$failed} rejected by Amazon.)".$errText;
        }

        return ['ok' => true, 'added' => $added, 'failed' => $failed, 'dest_live_count' => $newLiveCount, 'message' => $msg];
    }

    private function keywordKey(mixed $text, mixed $matchType): string
    {
        return strtoupper(trim((string) $text)).'|'.strtoupper(trim((string) $matchType));
    }

    /**
     * Create SP keywords in a destination ad group (v3 batch create).
     *
     * @param  list<array{keywordText: string, matchType: string}>  $keywords
     * @return array{created: list<array{keywordId: string, keywordText: string, matchType: string}>, failed: int}
     */
    private function createSpKeywords(string $campaignId, string $adGroupId, array $keywords): array
    {
        $created = [];
        $failed = 0;
        $duplicates = 0;
        $errors = [];
        $token = $this->amazonAccessToken();
        $clientId = config('services.amazon_ads.client_id');
        $profileId = config('services.amazon_ads.profile_ids');
        $bid = (float) config('services.amazon_ads.default_keyword_bid', 1.0);

        foreach (array_chunk($keywords, 100) as $chunk) {
            $payload = ['keywords' => array_map(fn ($k) => [
                'campaignId' => $campaignId,
                'adGroupId' => $adGroupId,
                'keywordText' => $k['keywordText'],
                'matchType' => $k['matchType'],
                'state' => 'ENABLED',
                'bid' => $bid,
            ], $chunk)];

            $response = Http::timeout(60)
                ->withToken($token)
                ->withHeaders([
                    'Amazon-Advertising-API-ClientId' => $clientId,
                    'Amazon-Advertising-API-Scope' => $profileId,
                    'Content-Type' => 'application/vnd.spKeyword.v3+json',
                    'Accept' => 'application/vnd.spKeyword.v3+json',
                ])
                ->post('https://advertising-api.amazon.com/sp/keywords', $payload);

            $body = $response->json();
            $successList = data_get($body, 'keywords.success', []);
            $errorList = data_get($body, 'keywords.error', []);

            foreach ($successList as $s) {
                $index = (int) ($s['index'] ?? -1);
                $kwId = $s['keywordId'] ?? data_get($s, 'keyword.keywordId');
                if ($kwId === null || ! isset($chunk[$index])) {
                    continue;
                }
                $created[] = [
                    'keywordId' => (string) $kwId,
                    'keywordText' => $chunk[$index]['keywordText'],
                    'matchType' => $chunk[$index]['matchType'],
                ];
            }

            if (is_array($errorList)) {
                foreach ($errorList as $e) {
                    $msg = $this->extractAmazonError($e);
                    if (stripos($msg, 'duplicate') !== false) {
                        $duplicates++;   // already exists on Amazon — not a real failure
                    } else {
                        $failed++;
                        $errors[] = $msg;
                    }
                }
            }

            // Whole request errored (non-2xx, no per-item breakdown).
            if (! $response->successful() && empty($successList) && empty($errorList)) {
                $failed += count($chunk);
                $errors[] = 'HTTP '.$response->status().': '.substr((string) $response->body(), 0, 300);
            }
        }

        return ['created' => $created, 'failed' => $failed, 'duplicates' => $duplicates, 'errors' => array_values(array_unique(array_filter($errors)))];
    }

    /**
     * Pull a readable message out of a v3 batch error item (shapes vary across endpoints).
     */
    private function extractAmazonError(mixed $e): string
    {
        if (! is_array($e)) {
            return trim((string) $e);
        }
        // Common shapes: {errors:[{errorType,message}]} or {code, details} or {message}
        $nested = $e['errors'][0] ?? null;
        $msg = $e['message']
            ?? $e['details']
            ?? $e['reason']
            ?? ($nested['message'] ?? $nested['errorType'] ?? null)
            ?? ($e['code'] ?? null);

        return $msg ? trim((string) $msg) : trim((string) json_encode($e));
    }

    private function amazonAccessToken(): string
    {
        $response = Http::asForm()->timeout(20)->post('https://api.amazon.com/auth/o2/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => config('services.amazon_ads.refresh_token'),
            'client_id' => config('services.amazon_ads.client_id'),
            'client_secret' => config('services.amazon_ads.client_secret'),
        ]);

        $token = $response->json('access_token');
        if (! $token) {
            throw new \RuntimeException('Could not obtain Amazon Ads access token.');
        }

        return (string) $token;
    }

    /**
     * Live keyword set (TEXT|MATCHTYPE) for a campaign, straight from Amazon (sp/keywords/list).
     * Used so dedup reflects what actually exists on Amazon — our report table only holds keywords
     * that had activity in the fetched windows, so it under-reports.
     *
     * @return array<string, true>
     */
    private function fetchLiveCampaignKeywordSet(string $campaignId): array
    {
        $set = [];
        $token = $this->amazonAccessToken();
        $clientId = config('services.amazon_ads.client_id');
        $profileId = config('services.amazon_ads.profile_ids');
        $nextToken = null;
        $guard = 0;

        do {
            $body = ['campaignIdFilter' => ['include' => [$campaignId]], 'maxResults' => 1000];
            if ($nextToken) {
                $body['nextToken'] = $nextToken;
            }

            $response = Http::timeout(60)
                ->withToken($token)
                ->withHeaders([
                    'Amazon-Advertising-API-ClientId' => $clientId,
                    'Amazon-Advertising-API-Scope' => $profileId,
                    'Content-Type' => 'application/vnd.spKeyword.v3+json',
                    'Accept' => 'application/vnd.spKeyword.v3+json',
                ])
                ->post('https://advertising-api.amazon.com/sp/keywords/list', $body);

            if (! $response->successful()) {
                break;
            }

            foreach (($response->json('keywords') ?? []) as $k) {
                $text = trim((string) ($k['keywordText'] ?? ''));
                $mt = strtoupper(trim((string) ($k['matchType'] ?? '')));
                if ($text !== '' && $mt !== '') {
                    $set[$this->keywordKey($text, $mt)] = true;
                }
            }

            $nextToken = $response->json('nextToken');
        } while (! empty($nextToken) && ++$guard < 50);

        return $set;
    }

    public function bulkLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'campaigns' => 'required|array|min:2',
            'campaigns.*' => 'required|string',
        ]);

        $campaigns = array_values(array_unique(array_filter(array_map('trim', $validated['campaigns']))));
        if (count($campaigns) < 2) {
            return response()->json(['success' => false, 'message' => 'Select at least two campaigns to link.'], 422);
        }

        $this->linkService->syncFullyConnectedGroup($campaigns, Auth::user()?->name ?? 'N/A');

        return response()->json([
            'success' => true,
            'message' => 'Campaigns linked.',
            'affected' => $this->affectedRows($campaigns[0]),
        ]);
    }

    public function removeLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'campaign' => 'required|string',
            'linked_campaign' => 'required|string',
        ]);

        $campaign = trim($validated['campaign']);
        $linkedCampaign = trim($validated['linked_campaign']);

        if ($campaign === '' || $linkedCampaign === '') {
            return response()->json(['success' => false, 'message' => 'Both campaigns are required.'], 422);
        }

        $beforeGroup = $this->linkService->groupContaining($campaign);
        $this->linkService->unlinkFromGroup($linkedCampaign, $beforeGroup, Auth::user()?->name ?? 'N/A');

        $affectedByName = [];
        foreach ($beforeGroup as $member) {
            foreach ($this->affectedRows($member) as $row) {
                $affectedByName[$row['campaign']] = $row;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Campaign unlinked.',
            'affected' => array_values($affectedByName),
        ]);
    }

    /**
     * @return list<array{campaign: string, linked_campaigns: list<string>, linked_count: int}>
     */
    private function affectedRows(string $campaign): array
    {
        $group = $this->linkService->groupContaining($campaign);
        $rows = [];
        foreach ($group as $member) {
            $rows[] = [
                'campaign' => $member,
                'linked_campaigns' => $group,
                'linked_count' => max(0, count($group) - 1),
            ];
        }

        return $rows;
    }
}
