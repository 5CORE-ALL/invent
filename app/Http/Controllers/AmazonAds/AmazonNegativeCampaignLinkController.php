<?php

namespace App\Http\Controllers\AmazonAds;

use App\Http\Controllers\Controller;
use App\Services\AmazonNegativeCampaignLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Campaign-name linking for Amazon SP campaigns based on NEGATIVE keywords
 * (source: amazon_sp_negative_keywords). Same UX as the keyword campaign-link page; linked
 * campaigns form a group so negative keywords can later be pushed across the whole group.
 */
class AmazonNegativeCampaignLinkController extends Controller
{
    private const SOURCE_TABLE = 'amazon_sp_negative_keywords';

    public function __construct(private AmazonNegativeCampaignLinkService $linkService)
    {
    }

    public function index()
    {
        return view('amazon_ads.negative-link.index');
    }

    /**
     * Paginated distinct SP campaigns with negative-keyword counts and their linked group.
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

            $countQuery = (clone $base)->select('campaignName')->groupBy('campaignName');
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
     * Negative keywords for one campaign (for the "click the number" modal).
     */
    public function getKeywords(Request $request): JsonResponse
    {
        try {
            $campaign = trim((string) $request->query('campaign', ''));
            if ($campaign === '' || ! Schema::hasTable(self::SOURCE_TABLE)) {
                return response()->json(['success' => true, 'campaign' => $campaign, 'keywords' => []]);
            }

            $rows = DB::table(self::SOURCE_TABLE)
                ->where('campaignName', $campaign)
                ->orderBy('keywordText')
                ->get(['keywordText', 'matchType', 'level', 'state', 'ad_group_id']);

            $keywords = $rows->map(fn ($r) => [
                'keyword' => (string) ($r->keywordText ?? ''),
                'match_type' => $r->matchType ?? null,
                'level' => $r->level ?? null,
                'state' => $r->state ?? null,
                'ad_group_id' => $r->ad_group_id ?? null,
            ])->filter(fn ($k) => $k['keyword'] !== '')->values()->all();

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
     * Push (import) all negative keywords from a campaign's linked group INTO that campaign.
     *
     * Campaign-level negatives are created via sp/campaignNegativeKeywords; ad-group-level via
     * sp/negativeKeywords (into the destination's primary ad group). Missing negatives (by
     * text + match type + level, deduped) only. Created rows are mirrored into
     * amazon_sp_negative_keywords so the grid count updates immediately.
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
     * Bulk push: run the negative-keyword import for every campaign that has a linked group.
     */
    public function pushAll(Request $request): JsonResponse
    {
        if (! Schema::hasTable(self::SOURCE_TABLE)) {
            return response()->json(['success' => false, 'message' => 'Negative keyword table missing.'], 422);
        }

        $campaigns = [];
        foreach ($this->linkService->groupsMap() as $members) {
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
        foreach ($campaigns as $campaign) {
            $r = $this->pushForCampaign($campaign);
            $processed++;
            $totalAdded += $r['added'];
            $totalFailed += $r['failed'];
        }

        $msg = "Bulk push complete: added {$totalAdded} negative keyword(s) across {$processed} campaign(s).";
        if ($totalFailed > 0) {
            $msg .= " ({$totalFailed} rejected/skipped.)";
        }

        return response()->json([
            'success' => true,
            'added' => $totalAdded,
            'failed' => $totalFailed,
            'processed' => $processed,
            'message' => $msg,
        ]);
    }

    /**
     * Core negative-keyword import for one destination campaign.
     *
     * @return array{ok: bool, added: int, failed: int, message: string, status?: int}
     */
    /**
     * Live comparison: negatives the linked group has that the destination is missing, checked
     * against the destination's LIVE Amazon negatives (campaign + ad-group level).
     */
    private function resolveMissing(string $campaign): array
    {
        $group = $this->linkService->groupContaining($campaign);
        $linked = array_values(array_filter($group, fn ($c) => $this->linkService->normalize($c) !== $this->linkService->normalize($campaign)));
        if ($linked === []) {
            return ['ok' => false, 'message' => 'This campaign has no linked campaign to push from.', 'status' => 422];
        }

        $destCampaignId = DB::table(self::SOURCE_TABLE)
            ->where('campaignName', $campaign)
            ->whereNotNull('campaign_id')
            ->value('campaign_id');
        if (! $destCampaignId) {
            return ['ok' => false, 'message' => 'Could not resolve the destination campaign id.', 'status' => 422];
        }
        $destCampaignId = (string) $destCampaignId;

        $destAdGroupId = DB::table(self::SOURCE_TABLE)
            ->where('campaignName', $campaign)
            ->where('level', 'AD_GROUP')
            ->whereNotNull('ad_group_id')
            ->selectRaw('ad_group_id, COUNT(*) c')
            ->groupBy('ad_group_id')
            ->orderByDesc('c')
            ->value('ad_group_id');
        if (! $destAdGroupId && Schema::hasTable('amazon_sp_keyword_reports')) {
            $destAdGroupId = DB::table('amazon_sp_keyword_reports')
                ->where('campaignName', $campaign)
                ->whereNotNull('ad_group_id')
                ->selectRaw('ad_group_id, COUNT(*) c')
                ->groupBy('ad_group_id')
                ->orderByDesc('c')
                ->value('ad_group_id');
        }
        $destAdGroupId = $destAdGroupId ? (string) $destAdGroupId : null;

        // Destination existing set (TEXT|MATCH|LEVEL) — report table…
        $existing = [];
        DB::table(self::SOURCE_TABLE)
            ->where('campaignName', $campaign)
            ->whereNotNull('keywordText')
            ->select('keywordText', 'matchType', 'level')
            ->distinct()
            ->get()
            ->each(function ($r) use (&$existing) {
                $existing[$this->negKey($r->keywordText, $r->matchType, $r->level)] = true;
            });

        // …plus the destination's LIVE negatives on Amazon (authoritative).
        $liveOk = false;
        $destLiveCount = 0;
        try {
            $live = $this->fetchLiveNegativeSet($destCampaignId);
            $liveOk = true;
            $destLiveCount = count($live);
            foreach ($live as $k => $_) {
                $existing[$k] = true;
            }
        } catch (\Throwable) {
        }

        // Linked group's negatives, split by level, minus what the destination has.
        $campaignNegs = [];
        $adGroupNegs = [];
        $groupSourceKeys = [];
        DB::table(self::SOURCE_TABLE)
            ->whereIn('campaignName', $linked)
            ->whereNotNull('keywordText')
            ->select('keywordText', 'matchType', 'level')
            ->distinct()
            ->get()
            ->each(function ($r) use (&$campaignNegs, &$adGroupNegs, &$groupSourceKeys, $existing) {
                $text = trim((string) $r->keywordText);
                $mt = strtoupper(trim((string) $r->matchType));
                $level = strtoupper(trim((string) $r->level));
                if ($text === '' || ! in_array($mt, ['NEGATIVE_EXACT', 'NEGATIVE_PHRASE'], true)) {
                    return;
                }
                $key = $this->negKey($text, $mt, $level);
                $groupSourceKeys[$key] = true;
                if (isset($existing[$key])) {
                    return;
                }
                if ($level === 'CAMPAIGN') {
                    $campaignNegs[$key] = ['keywordText' => $text, 'matchType' => $mt, 'level' => 'CAMPAIGN'];
                } else {
                    $adGroupNegs[$key] = ['keywordText' => $text, 'matchType' => $mt, 'level' => 'AD_GROUP'];
                }
            });

        return [
            'ok' => true,
            'destCampaignId' => $destCampaignId,
            'destAdGroupId' => $destAdGroupId,
            'linked' => $linked,
            'campaignNegs' => array_values($campaignNegs),
            'adGroupNegs' => array_values($adGroupNegs),
            'destLiveCount' => $destLiveCount,
            'liveOk' => $liveOk,
            'groupSourceCount' => count($groupSourceKeys),
        ];
    }

    /**
     * Live compare (no writes) for negatives.
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

        $missing = array_merge($r['campaignNegs'], $r['adGroupNegs']);

        return response()->json([
            'success' => true,
            'campaign' => $campaign,
            'linked_campaigns' => $r['linked'],
            'dest_live_count' => $r['destLiveCount'],
            'live_ok' => $r['liveOk'],
            'group_source_count' => $r['groupSourceCount'],
            'missing' => $missing,
            'missing_count' => count($missing),
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
        $campaignNegs = $resolved['campaignNegs'];
        $adGroupNegs = $resolved['adGroupNegs'];
        $destLiveCount = $resolved['destLiveCount'];

        if ($campaignNegs === [] && $adGroupNegs === []) {
            $where = $resolved['liveOk'] ? ' (verified live on Amazon)' : '';
            return ['ok' => true, 'added' => 0, 'failed' => 0, 'dest_live_count' => $destLiveCount,
                'message' => "\"{$campaign}\" already has all negative keywords from its linked campaign(s){$where} — nothing new to add."];
        }

        $created = [];
        $failed = 0;
        $duplicates = 0;
        $errors = [];
        try {
            if ($campaignNegs !== []) {
                $r = $this->createCampaignNegatives($destCampaignId, $campaignNegs);
                $created = array_merge($created, $r['created']);
                $failed += $r['failed'];
                $duplicates += $r['duplicates'] ?? 0;
                $errors = array_merge($errors, $r['errors'] ?? []);
            }
            if ($adGroupNegs !== []) {
                if ($destAdGroupId) {
                    $r = $this->createAdGroupNegatives($destCampaignId, $destAdGroupId, $adGroupNegs);
                    $created = array_merge($created, $r['created']);
                    $failed += $r['failed'];
                    $duplicates += $r['duplicates'] ?? 0;
                    $errors = array_merge($errors, $r['errors'] ?? []);
                } else {
                    // No ad group to target — count them as skipped/failed.
                    $failed += count($adGroupNegs);
                    $errors[] = 'No target ad group found for ad-group-level negatives.';
                }
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'added' => 0, 'failed' => 0, 'message' => 'Amazon push failed: '.$e->getMessage(), 'status' => 500];
        }

        $profileId = (string) config('services.amazon_ads.profile_ids');
        foreach ($created as $c) {
            \App\Models\AmazonSpNegativeKeyword::updateOrCreate(
                ['keyword_id' => (string) $c['keywordId']],
                [
                    'profile_id' => $profileId,
                    'level' => $c['level'],
                    'campaign_id' => $destCampaignId,
                    'campaignName' => $campaign,
                    'ad_group_id' => $c['level'] === 'AD_GROUP' ? $destAdGroupId : null,
                    'keywordText' => $c['keywordText'],
                    'matchType' => $c['matchType'],
                    'state' => 'ENABLED',
                ]
            );
        }

        $added = count($created);
        $newLiveCount = $destLiveCount + $added;
        $errText = ! empty($errors) ? ' Reason: '.implode(' | ', array_slice(array_values(array_unique(array_filter($errors))), 0, 3)) : '';
        if ($added === 0) {
            if ($failed > 0) {
                return ['ok' => false, 'added' => 0, 'failed' => $failed, 'dest_live_count' => $newLiveCount, 'message' => "No negative keywords were added ({$failed} rejected).".$errText];
            }
            if ($duplicates > 0) {
                return ['ok' => true, 'added' => 0, 'failed' => 0, 'dest_live_count' => $newLiveCount, 'message' => "All {$duplicates} negative keyword(s) already exist in \"{$campaign}\" — nothing new to add."];
            }

            return ['ok' => true, 'added' => 0, 'failed' => 0, 'dest_live_count' => $newLiveCount, 'message' => 'No new negative keywords to push.'];
        }

        $msg = "Added {$added} negative keyword(s) to \"{$campaign}\" from ".count($linked).' linked campaign(s).';
        if ($duplicates > 0) {
            $msg .= " ({$duplicates} already existed.)";
        }
        if ($failed > 0) {
            $msg .= " ({$failed} rejected.)".$errText;
        }

        return ['ok' => true, 'added' => $added, 'failed' => $failed, 'dest_live_count' => $newLiveCount, 'message' => $msg];
    }

    private function negKey(mixed $text, mixed $matchType, mixed $level): string
    {
        return strtoupper(trim((string) $text)).'|'.strtoupper(trim((string) $matchType)).'|'.strtoupper(trim((string) $level));
    }

    /**
     * @param  list<array{keywordText: string, matchType: string, level: string}>  $negs
     * @return array{created: list<array{keywordId: string, keywordText: string, matchType: string, level: string}>, failed: int}
     */
    private function createCampaignNegatives(string $campaignId, array $negs): array
    {
        return $this->createNegatives(
            'https://advertising-api.amazon.com/sp/campaignNegativeKeywords',
            'application/vnd.spCampaignNegativeKeyword.v3+json',
            'campaignNegativeKeywords',
            array_map(fn ($n) => [
                'campaignId' => $campaignId,
                'keywordText' => $n['keywordText'],
                'matchType' => $n['matchType'],
                'state' => 'ENABLED',
            ], $negs),
            $negs,
            'CAMPAIGN'
        );
    }

    /**
     * @param  list<array{keywordText: string, matchType: string, level: string}>  $negs
     * @return array{created: list<array{keywordId: string, keywordText: string, matchType: string, level: string}>, failed: int}
     */
    private function createAdGroupNegatives(string $campaignId, string $adGroupId, array $negs): array
    {
        return $this->createNegatives(
            'https://advertising-api.amazon.com/sp/negativeKeywords',
            'application/vnd.spNegativeKeyword.v3+json',
            'negativeKeywords',
            array_map(fn ($n) => [
                'campaignId' => $campaignId,
                'adGroupId' => $adGroupId,
                'keywordText' => $n['keywordText'],
                'matchType' => $n['matchType'],
                'state' => 'ENABLED',
            ], $negs),
            $negs,
            'AD_GROUP'
        );
    }

    /**
     * Shared v3 batch-create for negative keywords.
     *
     * @param  list<array<string, mixed>>  $items      request items (aligned with $meta by index)
     * @param  list<array{keywordText: string, matchType: string, level: string}>  $meta
     * @return array{created: list<array{keywordId: string, keywordText: string, matchType: string, level: string}>, failed: int}
     */
    private function createNegatives(string $url, string $contentType, string $bodyKey, array $items, array $meta, string $level): array
    {
        $created = [];
        $failed = 0;
        $duplicates = 0;
        $errors = [];
        $token = $this->amazonAccessToken();
        $clientId = config('services.amazon_ads.client_id');
        $profileId = config('services.amazon_ads.profile_ids');

        foreach (array_chunk($items, 100, true) as $chunk) {
            $indices = array_keys($chunk);
            $batch = array_values($chunk);

            $response = Http::timeout(60)
                ->withToken($token)
                ->withHeaders([
                    'Amazon-Advertising-API-ClientId' => $clientId,
                    'Amazon-Advertising-API-Scope' => $profileId,
                    'Content-Type' => $contentType,
                    'Accept' => $contentType,
                ])
                ->post($url, [$bodyKey => $batch]);

            $body = $response->json();
            $successList = data_get($body, $bodyKey.'.success', []);
            $errorList = data_get($body, $bodyKey.'.error', []);

            foreach ($successList as $s) {
                $localIdx = (int) ($s['index'] ?? -1);
                $origIdx = $indices[$localIdx] ?? null;
                $kwId = $s['keywordId'] ?? $s['campaignNegativeKeywordId'] ?? $s['negativeKeywordId'] ?? null;
                if ($kwId === null || $origIdx === null || ! isset($meta[$origIdx])) {
                    continue;
                }
                $created[] = [
                    'keywordId' => (string) $kwId,
                    'keywordText' => $meta[$origIdx]['keywordText'],
                    'matchType' => $meta[$origIdx]['matchType'],
                    'level' => $level,
                ];
            }

            if (is_array($errorList)) {
                foreach ($errorList as $e) {
                    $msg = $this->extractAmazonError($e);
                    if (stripos($msg, 'duplicate') !== false) {
                        $duplicates++;
                    } else {
                        $failed++;
                        $errors[] = $msg;
                    }
                }
            }
            if (! $response->successful() && empty($successList) && empty($errorList)) {
                $failed += count($batch);
                $errors[] = 'HTTP '.$response->status().': '.substr((string) $response->body(), 0, 300);
            }
        }

        return ['created' => $created, 'failed' => $failed, 'duplicates' => $duplicates, 'errors' => array_values(array_unique(array_filter($errors)))];
    }

    /**
     * Live negative-keyword set (TEXT|MATCH|LEVEL) for a campaign from Amazon — campaign-level
     * (sp/campaignNegativeKeywords/list) + ad-group-level (sp/negativeKeywords/list).
     *
     * @return array<string, true>
     */
    private function fetchLiveNegativeSet(string $campaignId): array
    {
        $set = [];
        $token = $this->amazonAccessToken();
        $clientId = config('services.amazon_ads.client_id');
        $profileId = config('services.amazon_ads.profile_ids');

        $endpoints = [
            ['url' => 'https://advertising-api.amazon.com/sp/campaignNegativeKeywords/list', 'ct' => 'application/vnd.spCampaignNegativeKeyword.v3+json', 'key' => 'campaignNegativeKeywords', 'level' => 'CAMPAIGN'],
            ['url' => 'https://advertising-api.amazon.com/sp/negativeKeywords/list', 'ct' => 'application/vnd.spNegativeKeyword.v3+json', 'key' => 'negativeKeywords', 'level' => 'AD_GROUP'],
        ];

        foreach ($endpoints as $ep) {
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
                        'Content-Type' => $ep['ct'],
                        'Accept' => $ep['ct'],
                    ])
                    ->post($ep['url'], $body);

                if (! $response->successful()) {
                    break;
                }

                foreach (($response->json($ep['key']) ?? []) as $n) {
                    $text = trim((string) ($n['keywordText'] ?? ''));
                    $mt = strtoupper(trim((string) ($n['matchType'] ?? '')));
                    if ($text !== '' && $mt !== '') {
                        $set[$this->negKey($text, $mt, $ep['level'])] = true;
                    }
                }

                $nextToken = $response->json('nextToken');
            } while (! empty($nextToken) && ++$guard < 50);
        }

        return $set;
    }

    private function extractAmazonError(mixed $e): string
    {
        if (! is_array($e)) {
            return trim((string) $e);
        }
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
