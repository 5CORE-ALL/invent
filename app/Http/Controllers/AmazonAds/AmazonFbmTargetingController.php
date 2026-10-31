<?php

namespace App\Http\Controllers\AmazonAds;

use App\Http\Controllers\Controller;
use App\Models\AmazonKwTargetingKeyword;
use App\Services\AmazonAdsService;
use App\Services\AmazonKwCousinLinkService;
use App\Services\AmazonKwTargetingPushService;
use App\Support\AmazonAdsCampaignSkuMetrics;
use App\Support\Marketplace\AmazonAdsMissingLinks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    private const STORE_TABLE = 'amazon_kw_targeting_keywords';

    private const NEG_TABLE = 'amazon_kw_targeting_negatives';

    private const SPECIFIC_TABLE = 'amazon_kw_targeting_specifics';

    private const SPECIFIC_NEG_TABLE = 'amazon_kw_targeting_specific_negatives';

    /** Per-target flags (deleted / ignored) set from the targets modal. */
    private const FLAG_TABLE = 'amazon_kw_targeting_flags';

    private const COUSIN_TABLE = 'amazon_kw_cousin_links';

    /** Set while a background pull of daily campaign reports (for the LT columns) is running. */
    private const LT_SYNC_LOCK = 'amz_fbm_lt_sync_lock';

    /** "Low keyword count" badge thresholds: campaigns with fewer than this many of each kind. */
    private const LOW_LIMITS = ['kw' => 50, 'neg' => 50, 'spec' => 10, 'sneg' => 10];

    private const COUSIN_NAME_TABLE = 'amazon_kw_cousin_names';

    /** @var array<string, array<string, mixed>>|null */
    private ?array $campaignIndexMemo = null;

    /** @var array<string, list<string>>|null parent key => campaign names */
    private ?array $siblingsMemo = null;

    public function __construct(private AmazonAdsService $ads, private AmazonKwCousinLinkService $cousins)
    {
    }

    public function index()
    {
        return view('amazon_ads.fbm-targeting');
    }

    public function data(Request $request): JsonResponse
    {
        // Give a heavy production load room, and make any failure visible: caught errors come back as JSON with the
        // reason, and fatals (memory / time limit) are written to storage/logs/fbm-targeting-error.log.
        $limit = (string) ini_get('memory_limit');
        if ($limit !== '-1' && $this->iniBytes($limit) < 512 * 1024 * 1024) {
            @ini_set('memory_limit', '512M');
        }
        @set_time_limit(90);
        register_shutdown_function(static function () {
            $e = error_get_last();
            if ($e && in_array($e['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
                @file_put_contents(
                    storage_path('logs/fbm-targeting-error.log'),
                    '['.date('Y-m-d H:i:s').'] FATAL '.$e['message'].' @ '.$e['file'].':'.$e['line']."\n",
                    FILE_APPEND
                );
            }
        });

        try {
            return $this->loadData($request);
        } catch (\Throwable $e) {
            report($e);
            @file_put_contents(
                storage_path('logs/fbm-targeting-error.log'),
                '['.date('Y-m-d H:i:s').'] '.get_class($e).': '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine()."\n",
                FILE_APPEND
            );

            return response()->json([
                'success' => false,
                'message' => mb_substr(class_basename($e).': '.$e->getMessage(), 0, 400).' ('.basename($e->getFile()).':'.$e->getLine().')',
            ], 500);
        }
    }

    private function iniBytes(string $v): int
    {
        $v = trim($v);
        $n = (int) $v;
        $u = strtolower(substr($v, -1));

        return match ($u) {
            'g' => $n * 1024 * 1024 * 1024,
            'm' => $n * 1024 * 1024,
            'k' => $n * 1024,
            default => $n,
        };
    }

    /** @var array<string, mixed> */
    private static array $schemaMemo = [];

    // Schema lookups hit information_schema (slow on a big database) and the answers rarely change, so they are
    // remembered for an hour. Only "yes" answers are cached, so a table created by a migration shows up straight away.
    protected function hasTable(string $table): bool
    {
        $k = 't:'.$table;
        if (self::$schemaMemo[$k] ?? false) {
            return true;
        }
        $yes = Cache::get('amz_kw_schema:'.$k) === 1 || Schema::hasTable($table);
        if ($yes) {
            self::$schemaMemo[$k] = true;
            Cache::put('amz_kw_schema:'.$k, 1, now()->addHour());
        }

        return $yes;
    }

    protected function hasColumn(string $table, string $column): bool
    {
        return in_array(strtolower($column), array_map('strtolower', $this->columnsOf($table)), true);
    }

    /** @return list<string> */
    protected function columnsOf(string $table): array
    {
        $k = 'c:'.$table;
        if (isset(self::$schemaMemo[$k])) {
            return self::$schemaMemo[$k];
        }
        $cols = Cache::get('amz_kw_schema:'.$k);
        if (! is_array($cols) || $cols === []) {
            $cols = Schema::getColumnListing($table);
            if ($cols !== []) {
                Cache::put('amz_kw_schema:'.$k, $cols, now()->addHour());
            }
        }

        return self::$schemaMemo[$k] = array_values($cols);
    }

    protected function loadData(Request $request): JsonResponse
    {
        $t0 = $tLast = microtime(true);
        $timing = [];
        $mark = function (string $name) use (&$timing, &$tLast): void {
            $now = microtime(true);
            $timing[$name] = (int) round(($now - $tLast) * 1000);
            $tLast = $now;
        };
        if (! $this->hasTable(self::SP_TABLE) || ! $this->hasColumn(self::SP_TABLE, 'campaignName')) {
            return response()->json(['success' => true, 'data' => [], 'last_page' => 1, 'total' => 0]);
        }

        $page = max(1, (int) $request->query('page', 1));
        $size = min(100, max(1, (int) $request->query('size', 50)));
        $search = trim((string) $request->query('campaign', ''));

        $all = $this->campaignsGroupedByParent();
        $mark('campaigns');
        // Badge figures are cached briefly and can never break the grid (they just show 0 if something fails).
        $badges = $this->badgeFigures($all);
        $missingTitles = $badges['missing_titles'];
        $missingCousins = $badges['missing_cousins'];
        $mark('badges');

        // "Low" badges: campaigns under the keyword-count thresholds (counted over every campaign, ignoring filters).
        $lowCounts = $badges['counts'];
        $lowTotals = $this->lowTotals($all, $lowCounts);
        $low = strtolower(trim((string) $request->query('low', '')));

        // Parent = "PARENT …" campaigns, SKU = single-SKU campaigns, all = both.
        $scope = strtolower(trim((string) $request->query('scope', 'all')));
        if ($scope === 'parent' || $scope === 'sku') {
            $all = array_values(array_filter($all, static fn (array $c) => $scope === 'parent' ? $c['is_parent'] : ! $c['is_parent']));
        }

        // Separate filters: campaign name, parent, and the parent's Product Master title (every word must match).
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $all = array_values(array_filter($all, static fn (array $c) => str_contains(mb_strtolower($c['campaign_name']), $needle)));
        }
        $parentQ = mb_strtolower(trim((string) $request->query('parent', '')));
        if ($parentQ !== '') {
            $all = array_values(array_filter($all, static fn (array $c) => str_contains(mb_strtolower($c['parent']), $parentQ)));
        }
        $titleTerms = array_values(array_filter(preg_split('/\s+/', mb_strtolower(trim((string) $request->query('title', '')))) ?: []));
        if ($titleTerms !== []) {
            $titleMap = $this->parentTitles(array_map(static fn (array $c) => $c['parent'], $all));
            $all = array_values(array_filter($all, function (array $c) use ($titleMap, $titleTerms) {
                $title = mb_strtolower($titleMap[$this->parentKey($c['parent'])] ?? '');
                foreach ($titleTerms as $t) {
                    if (! str_contains($title, $t)) {
                        return false;
                    }
                }

                return $title !== '';
            }));
        }

        if (isset(self::LOW_LIMITS[$low])) {
            $limit = self::LOW_LIMITS[$low];
            $all = array_values(array_filter($all, static fn (array $c) => ($lowCounts[$c['campaign_id']][$low] ?? 0) < $limit));
        }

        // KW cousin filter: matches the cousin group name, or any cousin campaign / cousin parent.
        $cousinSearch = mb_strtolower(trim((string) $request->query('cousin', '')));
        if ($cousinSearch !== '') {
            $groupsAll = $this->hasTable(self::COUSIN_TABLE) ? $this->cousins->groupsMap() : [];
            $namesAll = $this->cousinNameMap();
            $index = $this->campaignIndex();
            $terms = preg_split('/\s+/', $cousinSearch) ?: [];
            $all = array_values(array_filter($all, function (array $c) use ($groupsAll, $namesAll, $index, $terms) {
                $group = $this->effectiveGroup($c['campaign_name'], $groupsAll);
                $hay = mb_strtolower($this->groupNameFor($group, $c['campaign_name'], $namesAll));
                foreach ($this->cousinCampaignNames($group, $c['campaign_name']) as $member) {
                    $hay .= ' '.mb_strtolower($member).' '.mb_strtolower($index[$this->cousins->normalize($member)]['parent'] ?? '');
                }
                foreach ($terms as $t) {
                    if ($t !== '' && ! str_contains($hay, $t)) {
                        return false;
                    }
                }

                return true;
            }));
        }

        $total = count($all);
        $rows = array_slice($all, ($page - 1) * $size, $size);

        $mark('filters');

        // Keep keywords / negatives in step across KW cousins before they are read below.
        $this->autoSyncCousins($rows);
        $mark('cousin_sync');

        $campaignIds = array_values(array_filter(array_map(
            static fn (array $r) => $r['campaign_id'],
            $rows
        ), static fn (string $id) => $id !== ''));

        $targetsByCampaign = $this->mergeStoredKeywords(
            $this->targetsForCampaignIds($campaignIds),
            $campaignIds
        );

        $negativesByCampaign = $this->mergeStoredKeywords([], $campaignIds, self::NEG_TABLE);
        // Targets deleted in the modal are hidden; ignored ones stay listed (flagged for the modal).
        [$targetsByCampaign, $ignoredTargetsByCampaign] = $this->applyFlags($targetsByCampaign, $campaignIds, 'kw');
        [$negativesByCampaign, $ignoredNegativesByCampaign] = $this->applyFlags($negativesByCampaign, $campaignIds, 'neg');
        $specificByCampaign = $this->specificByCampaign($campaignIds);
        $specificNegByCampaign = $this->specificByCampaign($campaignIds, self::SPECIFIC_NEG_TABLE);

        $mark('keywords');
        $titlesByParent = $this->parentTitles(array_map(static fn (array $r) => $r['parent'], $rows));
        $mark('titles');

        $groups = $this->hasTable(self::COUSIN_TABLE) ? $this->cousins->groupsMap() : [];
        $nameMap = $this->cousinNameMap();

        // Lifetime clicks / sold of every campaign in each row's KW cousin group (own parent + cousin parents).
        $groupCampaignIds = [];
        $allGroupIds = [];
        try {
            $index = $this->campaignIndex();
            foreach ($rows as $row) {
                $ids = $row['campaign_id'] !== '' ? [$row['campaign_id'] => true] : [];
                foreach ($this->effectiveGroup($row['campaign_name'], $groups) as $member) {
                    $cid = $index[$this->cousins->normalize((string) $member)]['campaign_id'] ?? '';
                    if ($cid !== '') {
                        $ids[$cid] = true; // keyed by id: a campaign id shared by renamed campaigns counts once
                    }
                }
                $groupCampaignIds[$row['campaign_name']] = array_keys($ids);
                foreach ($ids as $cid => $_) {
                    $allGroupIds[(string) $cid] = true;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('AmazonFbmTargetingController: cousin group lookup for LT failed', ['error' => $e->getMessage()]);
        }
        $ltTotals = $this->lifetimeCampaignTotals(array_map('strval', array_keys($allGroupIds)));
        $mark('lifetime');

        $data = [];
        $prevParent = null;
        foreach ($rows as $row) {
            $id = $row['campaign_id'];
            $name = $row['campaign_name'];
            $targets = $targetsByCampaign[$id] ?? ($targetsByCampaign[strtolower($name)] ?? []);
            $negatives = $negativesByCampaign[$id] ?? [];
            $ignoredTargets = $ignoredTargetsByCampaign[$id] ?? [];
            $ignoredNegatives = $ignoredNegativesByCampaign[$id] ?? [];
            $specific = $specificByCampaign[$id] ?? [];
            $specificNeg = $specificNegByCampaign[$id] ?? [];

            // Campaigns under the same parent share the same KW cousins.
            $group = $this->effectiveGroup($name, $groups);
            $cousinNames = $this->cousinCampaignNames($group, $name);

            $cousinParents = $cousinNames === [] ? [] : $this->cousinParents($cousinNames);

            $ltClicks = 0;
            $ltSold = 0;
            $ltWithData = 0;
            $memberIds = $groupCampaignIds[$name] ?? [];
            foreach ($memberIds as $mid) {
                $t = $ltTotals[(string) $mid] ?? null;
                if ($t !== null) {
                    $ltClicks += $t['clicks'];
                    $ltSold += $t['sold'];
                    $ltWithData++;
                }
            }
            $data[] = [
                'lt_clicks' => $ltClicks,
                'lt_sold' => $ltSold,
                'lt_campaigns' => count($memberIds),
                'lt_campaigns_with_data' => $ltWithData,
                // id / name give the Auto Task Events (ATE) rules one trackable key + label per campaign row.
                'id' => $name !== '' ? $name : $id, // campaign names are unique per row (a campaign id can repeat across renamed campaigns)
                'name' => $name,
                'cousin_name' => $this->groupNameFor($group, $name, $nameMap),
                'cousins' => $cousinNames,
                'cousin_parents' => $cousinParents,
                'cousin_count' => count($cousinParents),
                'parent' => $row['parent'],
                'title' => $titlesByParent[$this->parentKey($row['parent'])] ?? '',
                // Parent is printed once per group (first row of the group on this page).
                'parent_first' => $prevParent === null || $prevParent !== $row['parent'],
                'campaign_id' => $id,
                'campaign_name' => $name,
                'status' => $row['status'],
                'targets' => $targets,
                'target_count' => count($targets),
                'ignored_targets' => $ignoredTargets,
                'negatives' => $negatives,
                'negative_count' => count($negatives),
                'ignored_negatives' => $ignoredNegatives,
                'specific' => $specific,
                'specific_count' => count($specific),
                'specific_neg' => $specificNeg,
                'specific_neg_count' => count($specificNeg),
            ];
            $prevParent = $row['parent'];
        }

        $mark('rows');
        $timing['total'] = (int) round((microtime(true) - $t0) * 1000);
        if ($timing['total'] > 3000) {
            Log::warning('AmazonFbmTargetingController: slow grid load', $timing + ['query' => $request->query()]);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
            'last_page' => max(1, (int) ceil($total / $size)),
            'total' => $total,
            'missing_titles' => count($missingTitles),
            'missing_title_parents' => array_slice($missingTitles, 0, 40),
            'low_counts' => $lowTotals,
            'low_limits' => self::LOW_LIMITS,
            'missing_cousins' => count($missingCousins),
            'missing_cousin_parents' => array_slice($missingCousins, 0, 40),
            'lt_coverage' => $this->lifetimeCoverage(),
            'timing_ms' => $timing,
        ])->header('Server-Timing', implode(', ', array_map(static fn ($k, $v) => $k.';dur='.$v, array_keys($timing), $timing)));
    }

    /**
     * @param  list<array{campaign_id: string}>  $campaigns
     * @param  array<string, array<string, int>>  $counts
     * @return array<string, int>
     */
    protected function lowTotals(array $campaigns, array $counts): array
    {
        $totals = array_fill_keys(array_keys(self::LOW_LIMITS), 0);
        foreach ($campaigns as $c) {
            foreach (self::LOW_LIMITS as $k => $limit) {
                if (($counts[$c['campaign_id']][$k] ?? 0) < $limit) {
                    $totals[$k]++;
                }
            }
        }

        return $totals;
    }

    /**
     * The toolbar badge numbers for Auto Task Events (the server-side daily check). Keys / labels match the page's badges.
     *
     * @return list<array{key: string, label: string, value: int}>
     */
    public function aceBadgeValues(): array
    {
        $all = $this->campaignsGroupedByParent();
        $b = $this->badgeFigures($all, true); // the daily Auto Task Event check always reads fresh numbers
        $low = $this->lowTotals($all, $b['counts']);

        return [
            ['key' => 'aft-missing-titles', 'label' => 'Missing Titles', 'value' => count($b['missing_titles'])],
            ['key' => 'aft-missing-cousins', 'label' => 'Missing KW cousins', 'value' => count($b['missing_cousins'])],
            ['key' => 'aft-low-kw', 'label' => 'KW < '.self::LOW_LIMITS['kw'], 'value' => $low['kw']],
            ['key' => 'aft-low-neg', 'label' => 'Neg KW < '.self::LOW_LIMITS['neg'], 'value' => $low['neg']],
            ['key' => 'aft-low-spec', 'label' => 'Specific KW < '.self::LOW_LIMITS['spec'], 'value' => $low['spec']],
            ['key' => 'aft-low-sneg', 'label' => 'Specific neg KW < '.self::LOW_LIMITS['sneg'], 'value' => $low['sneg']],
        ];
    }

    private const BADGE_CACHE_KEY = 'amz_kw_targeting_badges_v2';

    protected function forgetBadges(): void
    {
        Cache::forget(self::BADGE_CACHE_KEY);
    }

    /**
     * Figures behind the toolbar badges (missing titles / cousins, keyword counts per campaign). Cached for a minute
     * and each part is isolated so a failure only blanks that badge instead of failing the whole grid.
     *
     * @param  list<array{parent: string, campaign_name: string, campaign_id: string}>  $campaigns
     * @return array{missing_titles: list<string>, missing_cousins: list<string>, counts: array<string, array{kw: int, neg: int, spec: int, sneg: int}>}
     */
    protected function badgeFigures(array $campaigns, bool $fresh = false): array
    {
        $empty = ['missing_titles' => [], 'missing_cousins' => [], 'counts' => []];
        if ($fresh) {
            $this->forgetBadges();
        }
        try {
            // Cached for 15 minutes and dropped as soon as keywords / cousins change (forgetBadges), so a search never pays for them.
            return Cache::remember(self::BADGE_CACHE_KEY, now()->addMinutes(15), function () use ($campaigns, $empty) {
                $out = $empty;
                foreach ([
                    'missing_titles' => fn () => $this->missingTitleParents($campaigns),
                    'missing_cousins' => fn () => $this->missingCousinParents($campaigns),
                    'counts' => fn () => $this->keywordCountsByCampaign(),
                ] as $key => $fn) {
                    try {
                        $out[$key] = $fn();
                    } catch (\Throwable $e) {
                        Log::warning('AmazonFbmTargetingController: badge figure failed', ['badge' => $key, 'error' => $e->getMessage()]);
                    }
                }

                return $out;
            });
        } catch (\Throwable $e) {
            Log::warning('AmazonFbmTargetingController: badge figures failed', ['error' => $e->getMessage()]);

            return $empty;
        }
    }

    /**
     * Keyword counts per campaign id for the "low" badges / filters: kw (reports + app store, distinct keyword + match),
     * neg (app negatives), spec (Specific KW), sneg (S Negative KW).
     *
     * @return array<string, array{kw: int, neg: int, spec: int, sneg: int}>
     */
    protected function keywordCountsByCampaign(): array
    {
        $out = [];
        $bump = static function (string $cid, string $k, int $n) use (&$out): void {
            $out[$cid] ??= ['kw' => 0, 'neg' => 0, 'spec' => 0, 'sneg' => 0];
            $out[$cid][$k] = $n;
        };

        // kw: union of the keyword reports and the app store, distinct by lower-cased "keyword (MATCH)" - aggregated in SQL
        // (the reports table can be large, so nothing is loaded into PHP).
        $parts = [];
        if ($this->hasTable(self::KW_TABLE)) {
            $cols = $this->columnsOf(self::KW_TABLE);
            $labelCol = in_array('keyword', $cols, true) ? 'keyword' : (in_array('targeting', $cols, true) ? 'targeting' : null);
            if ($labelCol !== null && in_array('campaign_id', $cols, true)) {
                $matchExpr = in_array('matchType', $cols, true) ? "TRIM(COALESCE(`matchType`, ''))" : "''";
                $parts[] = 'SELECT CONVERT(CAST(`campaign_id` AS CHAR) USING utf8mb4) AS cid, '
                    ."LOWER(CONVERT(CONCAT(TRIM(`{$labelCol}`), IF({$matchExpr} = '', '', CONCAT(' (', {$matchExpr}, ')'))) USING utf8mb4)) AS lbl "
                    .'FROM `'.self::KW_TABLE."` WHERE `{$labelCol}` IS NOT NULL AND TRIM(`{$labelCol}`) <> '' AND `campaign_id` IS NOT NULL";
            }
        }
        if ($this->hasTable(self::STORE_TABLE)) {
            $parts[] = 'SELECT CONVERT(CAST(`campaign_id` AS CHAR) USING utf8mb4) AS cid, '
                ."LOWER(CONVERT(CONCAT(`keyword`, IF(`match_type` = '', '', CONCAT(' (', `match_type`, ')'))) USING utf8mb4)) AS lbl "
                .'FROM `'.self::STORE_TABLE.'`';
        }
        if ($parts !== []) {
            // UNION (not UNION ALL) removes the duplicates between the two sources.
            // Targets deleted in the targets modal are left out.
            $skipDeleted = $this->hasTable(self::FLAG_TABLE)
                ? " LEFT JOIN `".self::FLAG_TABLE."` f ON f.kind = 'kw' AND f.status = 'deleted'"
                    .' AND f.campaign_id COLLATE utf8mb4_unicode_ci = u.cid COLLATE utf8mb4_unicode_ci'
                    .' AND f.label_lc COLLATE utf8mb4_unicode_ci = u.lbl COLLATE utf8mb4_unicode_ci WHERE f.id IS NULL'
                : '';
            foreach (DB::select('SELECT u.cid AS cid, COUNT(*) AS c FROM ('.implode(' UNION ', $parts).') u'.$skipDeleted.' GROUP BY u.cid') as $r) {
                $bump(trim((string) $r->cid), 'kw', (int) $r->c);
            }
        }

        if ($this->hasTable(self::NEG_TABLE)) {
            foreach (DB::table(self::NEG_TABLE)->selectRaw("campaign_id, COUNT(DISTINCT LOWER(CONCAT(keyword, '|', match_type))) as c")->groupBy('campaign_id')->get() as $r) {
                $bump((string) $r->campaign_id, 'neg', (int) $r->c);
            }
        }
        foreach (['spec' => self::SPECIFIC_TABLE, 'sneg' => self::SPECIFIC_NEG_TABLE] as $k => $table) {
            if ($this->hasTable($table)) {
                foreach (DB::table($table)->selectRaw('campaign_id, COUNT(*) as c')->groupBy('campaign_id')->get() as $r) {
                    $bump((string) $r->campaign_id, $k, (int) $r->c);
                }
            }
        }

        return $out;
    }

    /**
     * Parents (of every campaign on this page, ignoring filters) that have no KW cousins on any other parent.
     * Campaigns of one parent share cousins, so one campaign per parent is enough to decide.
     *
     * @param  list<array{parent: string, campaign_name: string}>  $campaigns
     * @return list<string>
     */
    protected function missingCousinParents(array $campaigns): array
    {
        $groups = $this->hasTable(self::COUSIN_TABLE) ? $this->cousins->groupsMap() : [];
        $missing = [];
        $seen = [];
        foreach ($campaigns as $c) {
            $p = trim((string) $c['parent']);
            if ($p === '') {
                continue;
            }
            $key = $this->parentKey($p);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $group = $this->effectiveGroup($c['campaign_name'], $groups);
            if ($this->cousinCampaignNames($group, $c['campaign_name']) === []) {
                $missing[] = $p;
            }
        }
        sort($missing, SORT_NATURAL | SORT_FLAG_CASE);

        return $missing;
    }

    /**
     * Parents (of every campaign on this page, ignoring filters) that have no title in the Product Master.
     *
     * @param  list<array{parent: string}>  $campaigns
     * @return list<string>
     */
    protected function missingTitleParents(array $campaigns): array
    {
        $parents = [];
        foreach ($campaigns as $c) {
            $p = trim((string) $c['parent']);
            if ($p !== '') {
                $parents[$this->parentKey($p)] = $p;
            }
        }
        if ($parents === []) {
            return [];
        }

        $titles = $this->parentTitles(array_values($parents));
        $missing = [];
        foreach ($parents as $key => $label) {
            if (($titles[$key] ?? '') === '') {
                $missing[] = $label;
            }
        }
        sort($missing, SORT_NATURAL | SORT_FLAG_CASE);

        return $missing;
    }

    /**
     * Every distinct SP campaign (PT and archived campaigns excluded) with its parent family, ordered so all campaigns of one parent
     * sit together (parents A-Z, campaigns without a known parent last).
     *
     * @return list<array{parent: string, status: string, is_parent: bool, campaign_id: string, campaign_name: string}>
     */
    protected function campaignsGroupedByParent(): array
    {
        return Cache::remember('amz_kw_targeting_campaigns_by_parent_v5', now()->addMinutes(5), function () {
            // Latest known status per campaign; archived campaigns are left out everywhere.
            $status = AmazonAdsMissingLinks::campaignStatusMap();

            // This page is for KW / HL work only: product-targeting (PT) campaigns are left out everywhere.
            $rows = DB::table(self::SP_TABLE)
                ->whereNotNull('campaignName')
                ->where('campaignName', '!=', '')
                ->selectRaw('campaignName AS campaign_name, MAX(campaign_id) AS campaign_id')
                ->groupBy('campaignName')
                ->get()
                ->reject(fn ($r) => $this->isProductTargetingCampaign((string) $r->campaign_name)
                    || ($status[AmazonAdsMissingLinks::normalizeCampaignName((string) $r->campaign_name)] ?? '') === 'ARCHIVED')
                ->values();

            $names = $rows->map(static fn ($r) => trim((string) $r->campaign_name))->all();
            $parents = AmazonAdsCampaignSkuMetrics::parentFamiliesForCampaignNames($names);

            $list = [];
            foreach ($rows as $r) {
                $name = trim((string) $r->campaign_name);
                $list[] = [
                    'parent' => (string) ($parents[$name] ?? ''),
                    'status' => (string) ($status[AmazonAdsMissingLinks::normalizeCampaignName($name)] ?? ''),
                    // PARENT … campaigns vs single-SKU campaigns.
                    'is_parent' => AmazonAdsCampaignSkuMetrics::campaignKeyIsParent(
                        AmazonAdsCampaignSkuMetrics::skuKeyFromCampaignName($name)
                    ),
                    'campaign_id' => trim((string) ($r->campaign_id ?? '')),
                    'campaign_name' => $name,
                ];
            }

            usort($list, static function (array $a, array $b) {
                if (($a['parent'] === '') !== ($b['parent'] === '')) {
                    return $a['parent'] === '' ? 1 : -1;
                }

                return strnatcasecmp($a['parent'], $b['parent'])
                    ?: strnatcasecmp($a['campaign_name'], $b['campaign_name']);
            });

            return $list;
        });
    }

    /**
     * Push the app's keywords to Amazon: missing keywords go to every KW campaign (SP) and, per parent,
     * to its linked HL campaign (SB). dry_run=1 only reports what would be sent.
     */
    public function pushKeywords(Request $request, AmazonKwTargetingPushService $push): JsonResponse
    {
        $dryRun = filter_var($request->input('dry_run', true), FILTER_VALIDATE_BOOLEAN);

        @set_time_limit(900);

        $kw = array_values(array_filter(
            $this->campaignsGroupedByParent(),
            fn (array $c) => $c['campaign_id'] !== '' && ! $this->isProductTargetingCampaign($c['campaign_name'])
        ));

        $r = $push->run($kw, $dryRun);
        $t = $r['totals'];

        if ($r['errors'] !== [] && $r['rows'] === []) {
            return response()->json(['success' => false, 'message' => implode(' ', $r['errors']), 'dry_run' => $dryRun], 502);
        }

        $message = $dryRun
            ? 'Ready to push '.$t['kw_to_add'].' keyword(s) to '.$t['kw_campaigns'].' KW campaign(s) and '
                .$t['hl_to_add'].' to '.$t['hl_campaigns'].' HL campaign(s).'
            : 'Pushed '.$t['added'].' keyword(s)'.($t['failed'] > 0 ? ', '.$t['failed'].' rejected/failed' : '').'.';

        return response()->json([
            'success' => true,
            'dry_run' => $dryRun,
            'message' => $message,
            'totals' => $t,
            'rows' => $r['rows'],
            'errors' => $r['errors'],
        ]);
    }

    /**
     * Manually added keywords per campaign (independent of imports and KW cousins).
     *
     * @param  list<string>  $campaignIds
     * @return array<string, list<array{id: int, keyword: string, match_type: string}>>
     */
    protected function specificByCampaign(array $campaignIds, string $table = self::SPECIFIC_TABLE): array
    {
        $campaignIds = array_values(array_unique(array_filter($campaignIds)));
        if ($campaignIds === [] || ! $this->hasTable($table)) {
            return [];
        }

        $out = [];
        $rows = DB::table($table)
            ->whereIn('campaign_id', $campaignIds)
            ->orderByRaw('LOWER(keyword)')
            ->get(['id', 'campaign_id', 'keyword', 'match_type']);
        foreach ($rows as $r) {
            $out[(string) $r->campaign_id][] = [
                'id' => (int) $r->id,
                'keyword' => (string) $r->keyword,
                'match_type' => (string) $r->match_type,
            ];
        }

        return $out;
    }

    /**
     * Add Specific KW by hand to one campaign. One keyword per line (or comma separated).
     * Existing keywords (same text + match type) are skipped.
     */
    public function specificAdd(Request $request): JsonResponse
    {
        $v = $request->validate([
            'campaign_id' => ['required', 'string', 'max:64'],
            'keywords' => ['required', 'string', 'max:20000'],
            'match_type' => ['nullable', 'string', 'max:32'],
            'kind' => ['nullable', 'string', 'in:kw,neg'],
        ]);

        $neg = ($v['kind'] ?? 'kw') === 'neg';
        $table = $neg ? self::SPECIFIC_NEG_TABLE : self::SPECIFIC_TABLE;
        $match = strtoupper(trim((string) ($v['match_type'] ?? '')));
        $allowed = $neg ? ['', 'NEGATIVE_EXACT', 'NEGATIVE_PHRASE'] : ['', 'BROAD', 'PHRASE', 'EXACT'];
        if (! in_array($match, $allowed, true)) {
            return response()->json(['success' => false, 'message' => 'Invalid match type.'], 422);
        }

        if (! $this->hasTable($table)) {
            return response()->json(['success' => false, 'message' => 'Table is missing. Run php artisan migrate.'], 500);
        }

        $cid = trim($v['campaign_id']);
        $campaign = null;
        foreach ($this->campaignsGroupedByParent() as $c) {
            if ($c['campaign_id'] === $cid) {
                $campaign = $c;
                break;
            }
        }
        if ($campaign === null) {
            return response()->json(['success' => false, 'message' => 'Unknown campaign.'], 422);
        }

        $now = now();
        $user = Auth::user()?->name;

        $seen = [];
        $rows = [];
        foreach (preg_split('/[\r\n,]+/', $v['keywords']) ?: [] as $raw) {
            $text = trim((string) preg_replace('/\s+/u', ' ', (string) $raw));
            if ($text === '') {
                continue;
            }
            $key = AmazonKwTargetingKeyword::normalizeKey($text);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $rows[] = [
                'campaign_id' => $cid,
                'campaign_name' => $campaign['campaign_name'],
                'keyword' => mb_substr($text, 0, 512),
                'keyword_key' => $key,
                'match_type' => $match,
                'added_by' => $user,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if ($rows === []) {
            return response()->json(['success' => false, 'message' => 'Enter at least one keyword.'], 422);
        }

        $added = DB::table($table)->insertOrIgnore($rows);
        $skipped = count($rows) - $added;
        $this->forgetBadges();

        return response()->json([
            'success' => true,
            'message' => $added.' keyword'.($added === 1 ? '' : 's').' added'.($skipped > 0 ? ', '.$skipped.' already existed.' : '.'),
            'added' => $added,
            'skipped' => $skipped,
            'specific' => $this->specificByCampaign([$cid], $table)[$cid] ?? [],
        ]);
    }

    /**
     * Remove one Specific KW.
     */
    public function specificRemove(Request $request): JsonResponse
    {
        $v = $request->validate([
            'campaign_id' => ['required', 'string', 'max:64'],
            'id' => ['required', 'integer'],
            'kind' => ['nullable', 'string', 'in:kw,neg'],
        ]);

        $table = ($v['kind'] ?? 'kw') === 'neg' ? self::SPECIFIC_NEG_TABLE : self::SPECIFIC_TABLE;
        if (! $this->hasTable($table)) {
            return response()->json(['success' => false, 'message' => 'Table is missing. Run php artisan migrate.'], 500);
        }

        $cid = trim($v['campaign_id']);
        DB::table($table)->where('id', (int) $v['id'])->where('campaign_id', $cid)->delete();
        $this->forgetBadges();

        return response()->json([
            'success' => true,
            'message' => 'Keyword removed.',
            'specific' => $this->specificByCampaign([$cid], $table)[$cid] ?? [],
        ]);
    }

    /**
     * Split a displayed target label ("keyword text (MATCH)") into text + match type.
     *
     * @return array{text: string, match: string}
     */
    protected function parseTargetLabel(string $label): array
    {
        $raw = trim($label);
        if (preg_match('/^(.*)\s+\(([A-Za-z][A-Za-z0-9_\/ -]{1,20})\)$/u', $raw, $m)) {
            return ['text' => trim($m[1]), 'match' => strtoupper(trim($m[2]))];
        }

        return ['text' => $raw, 'match' => ''];
    }

    protected function flagLabel(string $key, string $match): string
    {
        return $match !== '' ? $key.' ('.strtolower($match).')' : $key;
    }

    /**
     * Flags (deleted / ignored) of a kind for campaigns: campaign_id => ['keyword_key|MATCH' => status].
     *
     * @param  list<string>  $campaignIds
     * @return array<string, array<string, string>>
     */
    protected function flagMap(string $kind, array $campaignIds): array
    {
        $campaignIds = array_values(array_unique(array_filter(array_map('strval', $campaignIds))));
        if ($campaignIds === [] || ! $this->hasTable(self::FLAG_TABLE)) {
            return [];
        }
        $out = [];
        foreach (array_chunk($campaignIds, 1000) as $chunk) {
            foreach (DB::table(self::FLAG_TABLE)->where('kind', $kind)->whereIn('campaign_id', $chunk)
                ->get(['campaign_id', 'keyword_key', 'match_type', 'status']) as $r) {
                $out[(string) $r->campaign_id][$r->keyword_key.'|'.strtoupper((string) $r->match_type)] = (string) $r->status;
            }
        }

        return $out;
    }

    /**
     * Set of "campaign_id|keyword_key|MATCH" for targets deleted in the modal.
     *
     * @param  list<string|int>  $campaignIds
     * @return array<string, true>
     */
    protected function deletedKeys(string $kind, array $campaignIds): array
    {
        $set = [];
        foreach ($this->flagMap($kind, array_map('strval', $campaignIds)) as $cid => $flags) {
            foreach ($flags as $k => $status) {
                if ($status === 'deleted') {
                    $set[$cid.'|'.$k] = true;
                }
            }
        }

        return $set;
    }

    /**
     * Drop deleted targets from per-campaign label lists and collect the ignored ones.
     *
     * @param  array<string, list<string>>  $map
     * @param  list<string>  $campaignIds
     * @return array{0: array<string, list<string>>, 1: array<string, list<string>>} [lists without deleted, ignored labels]
     */
    protected function applyFlags(array $map, array $campaignIds, string $kind): array
    {
        $flags = $this->flagMap($kind, $campaignIds);
        $ignored = [];
        if ($flags === []) {
            return [$map, $ignored];
        }
        foreach ($map as $cid => $labels) {
            $f = $flags[(string) $cid] ?? null;
            if ($f === null) {
                continue;
            }
            $keep = [];
            foreach ($labels as $label) {
                $p = $this->parseTargetLabel((string) $label);
                $status = $f[AmazonKwTargetingKeyword::normalizeKey($p['text']).'|'.$p['match']] ?? null;
                if ($status === 'deleted') {
                    continue;
                }
                if ($status === 'ignored') {
                    $ignored[$cid][] = $label;
                }
                $keep[] = $label;
            }
            $map[$cid] = $keep;
        }

        return [$map, $ignored];
    }

    /**
     * Campaigns that share keywords with the given one (its KW-cousin group + same-parent siblings), including itself.
     *
     * @return array<string, string> campaign_id => campaign_name
     */
    protected function groupMembers(string $campaignId, string $campaignName): array
    {
        $members = [$campaignId => $campaignName];
        try {
            $index = $this->campaignIndex();
            foreach ($this->effectiveGroup($campaignName) as $name) {
                $hit = $index[$this->cousins->normalize((string) $name)] ?? null;
                if ($hit && $hit['campaign_id'] !== '') {
                    $members[$hit['campaign_id']] = $hit['campaign_name'];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('AmazonFbmTargetingController: group members lookup failed', ['error' => $e->getMessage()]);
        }

        return $members;
    }

    /**
     * Current target / negative lists of one campaign (what the grid row would show).
     *
     * @return array<string, mixed>
     */
    protected function targetLists(string $campaignId): array
    {
        $targets = $this->mergeStoredKeywords($this->targetsForCampaignIds([$campaignId]), [$campaignId]);
        $negatives = $this->mergeStoredKeywords([], [$campaignId], self::NEG_TABLE);
        [$targets, $ignoredT] = $this->applyFlags($targets, [$campaignId], 'kw');
        [$negatives, $ignoredN] = $this->applyFlags($negatives, [$campaignId], 'neg');

        return [
            'targets' => $targets[$campaignId] ?? [],
            'ignored_targets' => $ignoredT[$campaignId] ?? [],
            'negatives' => $negatives[$campaignId] ?? [],
            'ignored_negatives' => $ignoredN[$campaignId] ?? [],
        ];
    }

    /**
     * Lifetime clicks and units sold per campaign, summed from the stored DAILY Amazon Ads SP campaign reports
     * (report_date_range = YYYY-MM-DD; the L1 / L7 / L15 / L30 summary rows overlap them and are left out).
     * Same rows and fields as the LT Sold / LT CVR columns on /amazon-ads/all (AmazonAdsController::
     * prefetchLifetimeSpendSalesForPageRows): clicks = SUM(clicks), sold = SUM(purchases30d).
     * Campaigns with no daily rows are absent from the result.
     *
     * @param  list<string>  $campaignIds
     * @return array<string, array{clicks: int, sold: int}>
     */
    protected function lifetimeCampaignTotals(array $campaignIds): array
    {
        $campaignIds = array_values(array_unique(array_filter($campaignIds, static fn ($id) => (string) $id !== '')));
        if ($campaignIds === [] || ! $this->hasTable(self::SP_TABLE)) {
            return [];
        }
        $cols = $this->columnsOf(self::SP_TABLE);
        if (! in_array('campaign_id', $cols, true) || ! in_array('report_date_range', $cols, true) || ! in_array('clicks', $cols, true)) {
            return [];
        }
        $soldCol = in_array('purchases30d', $cols, true) ? 'purchases30d' : (in_array('unitsSoldClicks30d', $cols, true) ? 'unitsSoldClicks30d' : null);

        sort($campaignIds);
        $key = 'amz_fbm_lt_totals_v2:'.md5(implode(',', $campaignIds));

        try {
            return Cache::remember($key, now()->addMinutes(5), function () use ($campaignIds, $soldCol) {
                $out = [];
                foreach (array_chunk($campaignIds, 1000) as $chunk) {
                    $rows = DB::table(self::SP_TABLE)
                        ->whereIn('campaign_id', $chunk)
                        ->whereRaw('CHAR_LENGTH(report_date_range) = 10')
                        ->selectRaw(
                            'campaign_id, SUM(COALESCE(`clicks`, 0)) AS clicks, '
                            .($soldCol !== null ? 'SUM(COALESCE(`'.$soldCol.'`, 0))' : '0').' AS sold'
                        )
                        ->groupBy('campaign_id')
                        ->get();
                    foreach ($rows as $r) {
                        $cid = trim((string) $r->campaign_id);
                        $out[$cid] ??= ['clicks' => 0, 'sold' => 0];
                        $out[$cid]['clicks'] += (int) $r->clicks;
                        $out[$cid]['sold'] += (int) $r->sold;
                    }
                }

                return $out;
            });
        } catch (\Throwable $e) {
            Log::warning('AmazonFbmTargetingController: lifetime totals failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Which days the stored daily SP campaign reports cover (what "lifetime" means here), plus whether a pull
     * from Amazon is already running.
     *
     * @return array{days: int, from: ?string, to: ?string, syncing: bool}
     */
    protected function lifetimeCoverage(): array
    {
        $out = ['days' => 0, 'from' => null, 'to' => null, 'syncing' => Cache::has(self::LT_SYNC_LOCK)];
        try {
            if ($this->hasTable(self::SP_TABLE) && $this->hasColumn(self::SP_TABLE, 'report_date_range')) {
                $c = Cache::remember('amz_fbm_lt_coverage', now()->addMinutes(5), function () {
                    $r = DB::table(self::SP_TABLE)
                        ->whereRaw('CHAR_LENGTH(report_date_range) = 10')
                        ->selectRaw('COUNT(DISTINCT report_date_range) AS d, MIN(report_date_range) AS mn, MAX(report_date_range) AS mx')
                        ->first();

                    return ['days' => (int) ($r->d ?? 0), 'from' => $r->mn ?? null, 'to' => $r->mx ?? null];
                });
                $out = $c + $out;
            }
        } catch (\Throwable $e) {
            Log::warning('AmazonFbmTargetingController: lifetime coverage failed', ['error' => $e->getMessage()]);
        }

        return $out;
    }

    /**
     * Pull the missing daily SP campaign report days from the Amazon Ads Reporting API (the most Amazon serves is
     * about 95 days) in a background process, so the LT clicks / sold columns fill in. Existing days are skipped.
     */
    public function syncLifetime(): JsonResponse
    {
        if (! Cache::add(self::LT_SYNC_LOCK, now()->toDateTimeString(), now()->addMinutes(60))) {
            return response()->json(['success' => true, 'message' => 'A pull from Amazon is already running (started within the last hour). Reload the page in a few minutes.', 'already' => true]);
        }

        try {
            $php = PHP_BINARY ?: 'php';
            if (stripos($php, 'fpm') !== false || stripos($php, 'cgi') !== false) {
                $cli = trim((string) shell_exec('command -v php 2>/dev/null'));
                if ($cli !== '') {
                    $php = $cli;
                }
            }
            $artisan = base_path('artisan');
            $log = storage_path('logs/fbm-targeting-lt-sync.log');
            if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
                pclose(popen('start /B '.escapeshellarg($php).' '.escapeshellarg($artisan).' app:amazon-ads-backfill-daily --type=sp', 'r'));
            } else {
                exec('nohup '.escapeshellarg($php).' '.escapeshellarg($artisan)
                    .' app:amazon-ads-backfill-daily --type=sp >> '.escapeshellarg($log).' 2>&1 &');
            }
        } catch (\Throwable $e) {
            Cache::forget(self::LT_SYNC_LOCK);
            Log::warning('AmazonFbmTargetingController: LT sync spawn failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Could not start the pull: '.$e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pulling daily campaign reports from Amazon in the background (this can take several minutes). Reload the page when it finishes.',
        ]);
    }

    /**
     * Lifetime (LT) clicks and units sold per keyword of one campaign, accumulated from the stored DAILY Amazon Ads
     * keyword reports (rows whose report_date_range is a date). The L1 / L7 / L15 / L30 windows are left out because
     * they overlap the daily rows. Units sold = unitsSoldClicks30d, the same field the rest of the ads pages use.
     * Keyed "keyword_key|MATCH" (keyword lower-cased, spaces collapsed) so the page can match its target labels.
     */
    public function targetMetrics(Request $request): JsonResponse
    {
        $cid = trim((string) $request->query('campaign_id', ''));
        $metrics = [];
        if ($cid !== '' && $this->hasTable(self::KW_TABLE)) {
            $cols = $this->columnsOf(self::KW_TABLE);
            $labelCol = in_array('keyword', $cols, true) ? 'keyword' : (in_array('targeting', $cols, true) ? 'targeting' : null);
            $soldCol = in_array('unitsSoldClicks30d', $cols, true) ? 'unitsSoldClicks30d' : (in_array('purchases30d', $cols, true) ? 'purchases30d' : null);
            if ($labelCol !== null && in_array('campaign_id', $cols, true) && in_array('report_date_range', $cols, true)) {
                $matchCol = in_array('matchType', $cols, true) ? 'matchType' : null;
                $clicks = in_array('clicks', $cols, true) ? 'SUM(COALESCE(`clicks`, 0))' : '0';
                $sold = $soldCol !== null ? 'SUM(COALESCE(`'.$soldCol.'`, 0))' : '0';
                $rows = DB::table(self::KW_TABLE)
                    ->where('campaign_id', $cid)
                    ->where('report_date_range', 'like', '____-__-__')
                    ->whereNotNull($labelCol)
                    ->selectRaw("`{$labelCol}` as label, ".($matchCol ? "`{$matchCol}`" : "''")." as mt, {$clicks} as clicks, {$sold} as sold")
                    ->groupBy($labelCol)
                    ->when($matchCol, fn ($q) => $q->groupBy($matchCol))
                    ->get();
                foreach ($rows as $r) {
                    $text = trim((string) $r->label);
                    if ($text === '') {
                        continue;
                    }
                    $key = AmazonKwTargetingKeyword::normalizeKey($text).'|'.strtoupper(trim((string) $r->mt));
                    $metrics[$key] ??= ['clicks' => 0, 'sold' => 0];
                    $metrics[$key]['clicks'] += (int) $r->clicks;
                    $metrics[$key]['sold'] += (int) $r->sold;
                }
            }
        }

        return response()->json(['success' => true, 'metrics' => (object) $metrics]);
    }

    /**
     * Bulk edit / delete / ignore on targets (or negative keywords) from the targets modal. The action is applied to
     * the campaign and to every campaign sharing its keywords (KW cousins + same-parent siblings), because the
     * KW-cousin sync would otherwise copy the keywords straight back.
     *  - delete: removed from the app and never re-imported
     *  - ignore / unignore: kept, but skipped when pushing keywords to Amazon
     *  - edit: new text and/or match type (find & replace for several at once)
     * Only the app's data is changed; nothing is sent to Amazon.
     */
    public function bulkTargets(Request $request): JsonResponse
    {
        $v = $request->validate([
            'kind' => ['required', 'string', 'in:kw,neg'],
            'action' => ['required', 'string', 'in:edit,delete,ignore,unignore'],
            'campaign_id' => ['required', 'string', 'max:64'],
            'labels' => ['required', 'array', 'min:1', 'max:5000'],
            'labels.*' => ['required', 'string', 'max:700'],
            'text' => ['nullable', 'string', 'max:512'],
            'find' => ['nullable', 'string', 'max:512'],
            'replace' => ['nullable', 'string', 'max:512'],
            'match_type' => ['nullable', 'string', 'max:32'],
        ]);

        if (! $this->hasTable(self::FLAG_TABLE)) {
            return response()->json(['success' => false, 'message' => 'Table is missing. Run php artisan migrate.'], 500);
        }

        $kind = $v['kind'];
        $action = $v['action'];
        $table = $this->storeTable($kind);
        if (! $this->hasTable($table)) {
            return response()->json(['success' => false, 'message' => 'Table is missing. Run php artisan migrate.'], 500);
        }

        $cid = trim($v['campaign_id']);
        $campaign = null;
        foreach ($this->campaignsGroupedByParent() as $c) {
            if ($c['campaign_id'] === $cid) {
                $campaign = $c;
                break;
            }
        }
        if ($campaign === null) {
            return response()->json(['success' => false, 'message' => 'Unknown campaign.'], 422);
        }

        $newMatch = null;
        if ($action === 'edit' && trim((string) ($v['match_type'] ?? '')) !== '') {
            $newMatch = strtoupper(trim((string) $v['match_type']));
            $allowed = $kind === 'neg' ? ['NEGATIVE_EXACT', 'NEGATIVE_PHRASE'] : ['BROAD', 'PHRASE', 'EXACT'];
            if (! in_array($newMatch, $allowed, true)) {
                return response()->json(['success' => false, 'message' => 'Invalid match type.'], 422);
            }
        }

        // Unique targets (text key + match type) out of the selected labels.
        $items = [];
        foreach ($v['labels'] as $label) {
            $p = $this->parseTargetLabel((string) $label);
            if ($p['text'] === '') {
                continue;
            }
            $items[AmazonKwTargetingKeyword::normalizeKey($p['text']).'|'.$p['match']] = $p;
        }
        if ($items === []) {
            return response()->json(['success' => false, 'message' => 'Nothing selected.'], 422);
        }

        // Edit: work out the new text / match for every item first.
        $edits = [];
        if ($action === 'edit') {
            $single = count($items) === 1;
            $text = trim((string) preg_replace('/\s+/u', ' ', (string) ($v['text'] ?? '')));
            $find = (string) ($v['find'] ?? '');
            $replace = (string) ($v['replace'] ?? '');
            if ($single && $text === '' && $find === '' && $newMatch === null) {
                return response()->json(['success' => false, 'message' => 'Enter a new keyword or choose a match type.'], 422);
            }
            if (! $single && $find === '' && $newMatch === null) {
                return response()->json(['success' => false, 'message' => 'Enter text to find and replace, or choose a match type.'], 422);
            }
            foreach ($items as $oldK => $p) {
                $nt = $p['text'];
                if ($single && $text !== '') {
                    $nt = $text;
                } elseif ($find !== '') {
                    $nt = trim((string) preg_replace('/\s+/u', ' ', str_ireplace($find, $replace, $p['text'])));
                }
                if ($nt === '') {
                    return response()->json(['success' => false, 'message' => 'The edit would leave an empty keyword.'], 422);
                }
                $nm = $newMatch ?? $p['match'];
                $newK = AmazonKwTargetingKeyword::normalizeKey($nt).'|'.$nm;
                if ($newK === $oldK) {
                    continue; // unchanged
                }
                $edits[$oldK] = ['old' => $p, 'text' => $nt, 'match' => $nm];
            }
            if ($edits === []) {
                return response()->json(['success' => true, 'message' => 'Nothing changed.', 'changed' => 0] + $this->targetLists($cid));
            }
        }

        $members = $this->groupMembers($cid, $campaign['campaign_name']);
        $memberIds = array_map('strval', array_keys($members));
        $now = now();
        $user = Auth::user()?->name;

        $flagRow = function (string $campaignId, string $text, string $match, string $status) use ($kind, $now, $user): array {
            $key = AmazonKwTargetingKeyword::normalizeKey($text);

            return [
                'kind' => $kind,
                'campaign_id' => $campaignId,
                'keyword_key' => $key,
                'match_type' => $match,
                'label_lc' => mb_substr($this->flagLabel($key, $match), 0, 320),
                'status' => $status,
                'flagged_by' => $user,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        };
        $upsert = function (array $rows): void {
            foreach (array_chunk($rows, 500) as $batch) {
                DB::table(self::FLAG_TABLE)->upsert(
                    $batch,
                    ['kind', 'campaign_id', 'keyword_key', 'match_type'],
                    ['label_lc', 'status', 'flagged_by', 'updated_at']
                );
            }
        };
        $clearFlags = function (array $keys, ?string $onlyStatus = null) use ($kind, $memberIds): void {
            // $keys: list of [keyword_key, match]
            foreach ($keys as [$k, $m]) {
                $q = DB::table(self::FLAG_TABLE)->where('kind', $kind)->whereIn('campaign_id', $memberIds)
                    ->where('keyword_key', $k)->where('match_type', $m);
                if ($onlyStatus !== null) {
                    $q->where('status', $onlyStatus);
                }
                $q->delete();
            }
        };

        $changed = 0;
        DB::transaction(function () use ($action, $items, $edits, $members, $memberIds, $table, $kind, $now, $flagRow, $upsert, $clearFlags, &$changed) {
            if ($action === 'ignore') {
                $rows = [];
                foreach ($items as $p) {
                    foreach ($memberIds as $m) {
                        $rows[] = $flagRow($m, $p['text'], $p['match'], 'ignored');
                    }
                }
                $upsert($rows);
                $changed = count($items);

                return;
            }

            if ($action === 'unignore') {
                $clearFlags(array_map(static fn ($p) => [AmazonKwTargetingKeyword::normalizeKey($p['text']), $p['match']], array_values($items)), 'ignored');
                $changed = count($items);

                return;
            }

            // delete + edit both remove the old target from the store and flag it deleted (so reports / pulls don't revive it)
            $targets = $action === 'edit' ? array_map(static fn ($e) => $e['old'], array_values($edits)) : array_values($items);
            $flags = [];
            foreach ($targets as $p) {
                $key = AmazonKwTargetingKeyword::normalizeKey($p['text']);
                DB::table($table)->whereIn('campaign_id', $memberIds)->where('keyword_key', $key)->where('match_type', $p['match'])->delete();
                foreach ($memberIds as $m) {
                    $flags[] = $flagRow($m, $p['text'], $p['match'], 'deleted');
                }
            }
            $upsert($flags);
            $changed = count($targets);

            if ($action === 'edit') {
                $inserts = [];
                $revive = [];
                foreach ($edits as $e) {
                    $key = AmazonKwTargetingKeyword::normalizeKey($e['text']);
                    $revive[] = [$key, $e['match']];
                    foreach ($members as $mid => $mname) {
                        $row = [
                            'campaign_id' => (string) $mid,
                            'campaign_name' => $mname,
                            'ad_group_id' => null,
                            'keyword_id' => null,
                            'keyword' => mb_substr($e['text'], 0, 512),
                            'keyword_key' => $key,
                            'match_type' => $e['match'],
                            'state' => null,
                            'source' => 'manual',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                        if ($kind === 'neg') {
                            $row['level'] = 'CAMPAIGN';
                        }
                        $inserts[] = $row;
                    }
                }
                // A previously deleted/ignored flag on the new text must not hide the edited target.
                $clearFlags($revive);
                foreach (array_chunk($inserts, 500) as $batch) {
                    DB::table($table)->insertOrIgnore($batch);
                }
            }
        });

        $this->forgetBadges();

        $n = $changed;
        $noun = ($kind === 'neg' ? 'negative keyword' : 'target').($n === 1 ? '' : 's');
        $verb = ['edit' => 'updated', 'delete' => 'deleted', 'ignore' => 'ignored', 'unignore' => 'un-ignored'][$action];
        $shared = count($members) > 1 ? ' (applied to '.count($members).' campaigns sharing these keywords)' : '';

        return response()->json([
            'success' => true,
            'message' => $n.' '.$noun.' '.$verb.'.'.$shared,
            'changed' => $n,
            'campaigns' => count($members),
        ] + $this->targetLists($cid));
    }

    private function storeTable(string $kind): string
    {
        return $kind === 'neg' ? self::NEG_TABLE : self::STORE_TABLE;
    }

    /**
     * Pull keywords from Amazon KW campaigns (SP campaigns that are not PT) and store the
     * ones the app does not have yet. Keywords already stored are left untouched and never duplicated.
     * Afterwards every KW-cousin group is synced so cousins share the same keywords.
     */
    public function pullKeywords(): JsonResponse
    {
        return $this->pullKind('kw');
    }

    /**
     * Same as pullKeywords() but for negative keywords.
     */
    public function pullNegatives(): JsonResponse
    {
        return $this->pullKind('neg');
    }

    private function pullKind(string $kind): JsonResponse
    {
        $label = $kind === 'neg' ? 'negative keyword' : 'keyword';
        $table = $this->storeTable($kind);

        if (! $this->hasTable(self::SP_TABLE) || ! $this->hasTable($table)) {
            return response()->json([
                'success' => false,
                'message' => 'Required tables are missing. Run php artisan migrate.',
            ], 500);
        }

        @set_time_limit(600);

        $names = [];
        foreach ($this->campaignsGroupedByParent() as $c) {
            if ($c['campaign_id'] !== '' && ! $this->isProductTargetingCampaign($c['campaign_name'])) {
                $names[$c['campaign_id']] = $c['campaign_name'];
            }
        }
        if ($names === []) {
            return response()->json([
                'success' => true,
                'message' => 'No KW campaigns found.',
                'campaigns' => 0, 'fetched' => 0, 'added' => 0, 'skipped' => 0,
            ]);
        }

        try {
            $r = $this->ingestKeywords($names, $kind);
        } catch (\Throwable $e) {
            Log::warning('AmazonFbmTargetingController: pull '.$label.'s failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Could not fetch '.$label.'s from Amazon: '.$e->getMessage(),
            ], 502);
        }

        $cousinAdded = 0;
        if ($this->hasTable(self::COUSIN_TABLE)) {
            $done = [];
            foreach ($this->cousins->groupsMap() as $group) {
                $key = implode('|', $group);
                if (isset($done[$key])) {
                    continue;
                }
                $done[$key] = true;
                $cousinAdded += $this->syncCousinKeywords($group, false, $kind);
            }
        }

        $message = $r['added'].' new '.$label.($r['added'] === 1 ? '' : 's').' added. '.$r['skipped'].' already in the app.';
        if ($cousinAdded > 0) {
            $message .= ' '.$cousinAdded.' copied between KW cousins.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'campaigns' => count($names),
            'fetched' => $r['fetched'],
            'added' => $r['added'],
            'skipped' => $r['skipped'],
            'cousin_added' => $cousinAdded,
        ]);
    }

    /**
     * Fetch live keywords for the given campaigns from Amazon and insert the ones missing in the app.
     * Existing rows are never touched.
     *
     * @param  array<string, string>  $names  campaign_id => campaign name
     * @return array{fetched: int, added: int, skipped: int}
     */
    protected function ingestKeywords(array $names, string $kind = 'kw'): array
    {
        $table = $this->storeTable($kind);
        if ($kind === 'neg') {
            $ids = array_keys($names);
            $remote = array_merge(
                array_map(static fn (array $k) => $k + ['_level' => 'CAMPAIGN'], $this->ads->listSpCampaignNegativeKeywordsByCampaignIds($ids)),
                array_map(static fn (array $k) => $k + ['_level' => 'AD_GROUP'], $this->ads->listSpAdGroupNegativeKeywordsByCampaignIds($ids))
            );
        } else {
            $remote = $this->ads->listKeywordsByCampaignIdsBulk(array_keys($names));
        }

        // Keys already in the app, so they are retained as-is.
        $existing = [];
        foreach (array_chunk(array_keys($names), 1000) as $chunk) {
            foreach (DB::table($table)->whereIn('campaign_id', $chunk)->get(['campaign_id', 'keyword_key', 'match_type']) as $row) {
                $existing[$row->campaign_id.'|'.$row->keyword_key.'|'.$row->match_type] = true;
            }
        }

        // Targets deleted in the targets modal are not brought back.
        foreach ($this->deletedKeys($kind, array_keys($names)) as $k => $_) {
            $existing[$k] = true;
        }

        $now = now();
        $toInsert = [];
        $skipped = 0;
        foreach ($remote as $kw) {
            $cid = trim((string) ($kw['campaignId'] ?? ''));
            $text = trim((string) ($kw['keywordText'] ?? $kw['keyword'] ?? ''));
            if ($cid === '' || $text === '' || ! isset($names[$cid])) {
                continue;
            }
            $match = strtoupper(trim((string) ($kw['matchType'] ?? '')));
            $key = AmazonKwTargetingKeyword::normalizeKey($text);
            $dedupe = $cid.'|'.$key.'|'.$match;
            if (isset($existing[$dedupe])) {
                $skipped++;

                continue;
            }
            $existing[$dedupe] = true;
            $row = [
                'campaign_id' => $cid,
                'campaign_name' => $names[$cid],
                'ad_group_id' => ($kw['adGroupId'] ?? null) !== null ? (string) $kw['adGroupId'] : null,
                'keyword_id' => ($kw['keywordId'] ?? null) !== null ? (string) $kw['keywordId'] : null,
                'keyword' => mb_substr($text, 0, 512),
                'keyword_key' => $key,
                'match_type' => $match,
                'state' => isset($kw['state']) ? strtoupper((string) $kw['state']) : null,
                'source' => 'amazon',
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if ($kind === 'neg') {
                $row['level'] = (string) ($kw['_level'] ?? 'CAMPAIGN');
            }
            $toInsert[] = $row;
        }

        $added = 0;
        foreach (array_chunk($toInsert, 500) as $batch) {
            $added += DB::table($table)->insertOrIgnore($batch);
        }
        if ($added > 0) {
            $this->forgetBadges();
        }

        return ['fetched' => count($remote), 'added' => $added, 'skipped' => $skipped];
    }

    /**
     * Copy keywords between the campaigns of one cousin group so every member ends up with the
     * union (keyword text + match type, no duplicates). Works on the app's keyword store.
     * Optionally pulls the members' live Amazon keywords first so nothing is missed.
     *
     * @param  list<string>  $memberNames
     * @return int number of keyword rows added across the group
     */
    protected function syncCousinKeywords(array $memberNames, bool $ingestLive = true, string $kind = 'kw'): int
    {
        $table = $this->storeTable($kind);
        if (! $this->hasTable($table)) {
            return 0;
        }

        $index = $this->campaignIndex();
        $members = [];
        foreach ($memberNames as $name) {
            $hit = $index[$this->cousins->normalize((string) $name)] ?? null;
            if ($hit && $hit['campaign_id'] !== '') {
                $members[$hit['campaign_id']] = $hit['campaign_name'];
            }
        }
        if (count($members) < 2) {
            return 0;
        }

        if ($ingestLive) {
            try {
                $this->ingestKeywords($members, $kind);
            } catch (\Throwable $e) {
                Log::warning('AmazonFbmTargetingController: cousin live pull failed', ['error' => $e->getMessage()]);
            }
        }

        $cols = ['campaign_id', 'keyword', 'keyword_key', 'match_type', 'state'];
        if ($kind === 'neg') {
            $cols[] = 'level';
        }
        $rows = DB::table($table)
            ->whereIn('campaign_id', array_keys($members))
            ->get($cols);

        $union = [];
        $have = [];
        foreach ($rows as $row) {
            $uk = $row->keyword_key.'|'.$row->match_type;
            $union[$uk] ??= $row;
            $have[$row->campaign_id][$uk] = true;
        }

        $deleted = $this->deletedKeys($kind, array_keys($members));
        $now = now();
        $toInsert = [];
        foreach ($members as $cid => $cname) {
            foreach ($union as $uk => $row) {
                if (isset($have[$cid][$uk]) || isset($deleted[$cid.'|'.$uk])) {
                    continue;
                }
                $new = [
                    'campaign_id' => (string) $cid,
                    'campaign_name' => $cname,
                    'ad_group_id' => null,
                    'keyword_id' => null,
                    'keyword' => $row->keyword,
                    'keyword_key' => $row->keyword_key,
                    'match_type' => $row->match_type,
                    'state' => $row->state,
                    'source' => 'cousin',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                if ($kind === 'neg') {
                    // Copied negatives are app-level; they sit on the campaign (no ad group on the target).
                    $new['level'] = 'CAMPAIGN';
                }
                $toInsert[] = $new;
            }
        }

        $added = 0;
        foreach (array_chunk($toInsert, 500) as $batch) {
            $added += DB::table($table)->insertOrIgnore($batch);
        }
        if ($added > 0) {
            $this->forgetBadges();
        }

        return $added;
    }

    /**
     * Automatic KW-cousin sync for the campaigns on the page being loaded: one live pull per kind for all
     * members of the touched groups (so nothing on Amazon is missed), then a local union per group
     * (keyword + match type, no duplicates). Each group is re-synced at most every 5 minutes.
     * Only groups that have cousins on other parents are synced.
     *
     * @param  list<array{parent: string, campaign_name: string}>  $rows
     */
    protected function autoSyncCousins(array $rows): void
    {
        if (! $this->hasTable(self::COUSIN_TABLE) || ! $this->hasTable(self::STORE_TABLE)) {
            return;
        }

        try {
            $groupsMap = $this->cousins->groupsMap();
            $index = $this->campaignIndex();

            $todo = [];
            foreach ($rows as $row) {
                $group = $this->effectiveGroup($row['campaign_name'], $groupsMap);
                if ($this->cousinCampaignNames($group, $row['campaign_name']) === []) {
                    continue;
                }
                $norms = array_map(fn ($n) => $this->cousins->normalize((string) $n), $group);
                sort($norms);
                $key = 'amz_kw_cousin_autosync:'.md5(implode('|', $norms));
                if (isset($todo[$key]) || Cache::has($key)) {
                    continue;
                }
                $todo[$key] = $group;
            }
            if ($todo === []) {
                return;
            }
            $todo = array_slice($todo, 0, 12, true); // keep one page load light; the rest sync on later loads

            $members = [];
            foreach ($todo as $group) {
                foreach ($group as $name) {
                    $hit = $index[$this->cousins->normalize((string) $name)] ?? null;
                    if ($hit && $hit['campaign_id'] !== '') {
                        $members[$hit['campaign_id']] = $hit['campaign_name'];
                    }
                }
            }

            // Keywords already shown in the grid come from the synced keyword reports, so bring those into the store too.
            $this->ingestFromReports($members);

            // Local data only here (no live Amazon calls while the grid loads); live keywords come in with the
            // Pull buttons and "Sync keywords now".
            foreach (['kw', 'neg'] as $kind) {
                if ($kind === 'neg' && ! $this->hasTable(self::NEG_TABLE)) {
                    continue;
                }
                foreach ($todo as $group) {
                    $this->syncCousinKeywords($group, false, $kind);
                }
            }

            foreach (array_keys($todo) as $key) {
                Cache::put($key, 1, now()->addMinutes(5));
            }
        } catch (\Throwable $e) {
            Log::warning('AmazonFbmTargetingController: auto cousin sync failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Copy the keywords of the synced SP keyword reports (what the Targets column already shows) into the keyword
     * store for the given campaigns, retaining what is there (dedupe by campaign + keyword + match type).
     *
     * @param  array<string, string>  $members  campaign_id => campaign_name
     */
    protected function ingestFromReports(array $members): void
    {
        if ($members === [] || ! $this->hasTable(self::KW_TABLE)) {
            return;
        }
        $cols = $this->columnsOf(self::KW_TABLE);
        $labelCol = in_array('keyword', $cols, true) ? 'keyword' : (in_array('targeting', $cols, true) ? 'targeting' : null);
        if ($labelCol === null || ! in_array('campaign_id', $cols, true)) {
            return;
        }
        $hasMatch = in_array('matchType', $cols, true);
        $select = ['campaign_id', $labelCol.' as label'];
        if ($hasMatch) {
            $select[] = 'matchType';
        }

        $now = now();
        $seen = $this->deletedKeys('kw', array_keys($members));
        $toInsert = [];
        foreach (array_chunk(array_keys($members), 1000) as $chunk) {
            $q = DB::table(self::KW_TABLE)->whereIn('campaign_id', $chunk)->whereNotNull($labelCol)->where($labelCol, '!=', '');
            foreach ($q->get($select) as $row) {
                $cid = trim((string) $row->campaign_id);
                $text = trim((string) $row->label);
                if ($cid === '' || $text === '' || ! isset($members[$cid])) {
                    continue;
                }
                $match = $hasMatch ? strtoupper(trim((string) ($row->matchType ?? ''))) : '';
                $key = AmazonKwTargetingKeyword::normalizeKey($text);
                $dedupe = $cid.'|'.$key.'|'.$match;
                if (isset($seen[$dedupe])) {
                    continue;
                }
                $seen[$dedupe] = true;
                $toInsert[] = [
                    'campaign_id' => $cid,
                    'campaign_name' => $members[$cid],
                    'ad_group_id' => null,
                    'keyword_id' => null,
                    'keyword' => mb_substr($text, 0, 512),
                    'keyword_key' => $key,
                    'match_type' => $match,
                    'state' => null,
                    'source' => 'report',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        $inserted = 0;
        foreach (array_chunk($toInsert, 500) as $batch) {
            $inserted += DB::table(self::STORE_TABLE)->insertOrIgnore($batch);
        }
        if ($inserted > 0) {
            $this->forgetBadges();
        }
    }

    /**
     * Normalized campaign name => campaign row, for resolving cousin names to campaign ids.
     *
     * @return array<string, array{parent: string, campaign_id: string, campaign_name: string}>
     */
    protected function campaignIndex(): array
    {
        if ($this->campaignIndexMemo !== null) {
            return $this->campaignIndexMemo;
        }
        $index = [];
        foreach ($this->campaignsGroupedByParent() as $c) {
            $index[$this->cousins->normalize($c['campaign_name'])] = $c;
        }

        return $this->campaignIndexMemo = $index;
    }

    /**
     * Parents for the cousin picker. Picking a parent brings in all of its campaigns automatically.
     * The campaign's own parent and parents that are already cousins are left out.
     */
    public function cousinCampaigns(Request $request): JsonResponse
    {
        $q = mb_strtolower(trim((string) $request->query('q', '')));
        $campaign = (string) $request->query('exclude', '');
        $index = $this->campaignIndex();
        $ownParent = strtoupper($index[$this->cousins->normalize($campaign)]['parent'] ?? '');

        $inGroup = [];
        foreach ($this->effectiveGroup($campaign) as $name) {
            $inGroup[$this->cousins->normalize($name)] = true;
        }

        // parent (upper) => [display, campaigns total, campaigns already cousins]
        $parents = [];
        foreach ($this->campaignsGroupedByParent() as $c) {
            if ($c['parent'] === '') {
                continue;
            }
            $key = strtoupper($c['parent']);
            $parents[$key] ??= ['parent' => $c['parent'], 'campaigns' => 0, 'linked' => 0];
            $parents[$key]['campaigns']++;
            if (isset($inGroup[$this->cousins->normalize($c['campaign_name'])])) {
                $parents[$key]['linked']++;
            }
        }

        $out = [];
        foreach ($parents as $key => $p) {
            if ($key === $ownParent || $p['linked'] >= $p['campaigns']) {
                continue;
            }
            if ($q !== '' && ! str_contains(mb_strtolower($p['parent']), $q)) {
                continue;
            }
            $out[] = ['parent' => $p['parent'], 'campaigns' => $p['campaigns']];
        }

        return response()->json(['success' => true, 'parents' => $out]);
    }

    /**
     * Add parents as KW cousins of a campaign: every campaign under each chosen parent becomes a cousin,
     * then keywords are synced across the whole cousin group.
     */
    public function addCousins(Request $request): JsonResponse
    {
        $v = $request->validate([
            'campaign' => ['required', 'string', 'max:512'],
            'parents' => ['required', 'array', 'min:1'],
            'parents.*' => ['string', 'max:512'],
            'name' => ['nullable', 'string', 'max:191'],
        ]);

        if (! $this->hasTable(self::COUSIN_TABLE)) {
            return response()->json(['success' => false, 'message' => 'Cousin table is missing. Run php artisan migrate.'], 500);
        }

        $index = $this->campaignIndex();
        $campaign = trim($v['campaign']);
        $self = $index[$this->cousins->normalize($campaign)] ?? null;
        if (! $self) {
            return response()->json(['success' => false, 'message' => 'Unknown campaign.'], 422);
        }

        $wanted = [];
        foreach ($v['parents'] as $p) {
            $wanted[strtoupper(trim($p))] = true;
        }

        // The campaign's siblings (same parent) take part too, so the whole parent gets the same cousins.
        $names = [$self['campaign_name']];
        foreach ($this->siblingsByParent()[strtoupper($self['parent'])] ?? [] as $sib) {
            if ($self['parent'] !== '') {
                $names[] = $sib;
            }
        }
        $picked = 0;
        foreach ($this->campaignsGroupedByParent() as $c) {
            if ($c['parent'] !== '' && isset($wanted[strtoupper($c['parent'])])
                && strtoupper($c['parent']) !== strtoupper($self['parent'])) {
                $names[] = $c['campaign_name'];
                $picked++;
            }
        }
        if ($picked === 0) {
            return response()->json(['success' => false, 'message' => 'Pick at least one other parent.'], 422);
        }
        $names = array_values(array_unique($names));

        // Join with the existing group so everyone stays one connected set.
        $group = $this->uniqueByNorm(array_merge($this->effectiveGroup($campaign), $names));
        $this->cousins->syncFullyConnectedGroup($group, Auth::user()?->name ?? 'N/A');

        // Name: the typed one wins, else the campaign's own / an existing member's name; shared by the whole group.
        $typed = trim((string) ($v['name'] ?? ''));
        $nameMap = $this->cousinNameMap();
        $groupName = $typed !== '' ? $typed : $this->groupNameFor($group, $self['campaign_name'], $nameMap);
        $this->setGroupName($group, $groupName);

        @set_time_limit(300);
        $added = $this->syncCousinKeywords($group);
        $addedNeg = $this->syncCousinKeywords($group, true, 'neg');

        $this->forgetBadges(); // missing-cousin / keyword-count badges changed
        return response()->json([
            'success' => true,
            'message' => 'Cousins saved ('.$picked.' campaigns from '.count($wanted).' parent'.(count($wanted) === 1 ? '' : 's').'). Synced across '.count($group).' campaigns: '
                .$added.' keyword'.($added === 1 ? '' : 's').', '
                .$addedNeg.' negative keyword'.($addedNeg === 1 ? '' : 's').'.',
            'added' => $added,
            'added_negatives' => $addedNeg,
            'affected' => $this->cousinRows($group),
        ]);
    }

    /**
     * Remove a cousin parent (all of its campaigns) from the cousin group of a campaign.
     * Keywords already synced stay.
     */
    public function removeCousin(Request $request): JsonResponse
    {
        $v = $request->validate([
            'campaign' => ['required', 'string', 'max:512'],
            'parent' => ['nullable', 'string', 'max:512'],
            'cousin' => ['nullable', 'string', 'max:512'],
        ]);

        $campaign = $v['campaign'];
        $before = $this->effectiveGroup($campaign);

        $toRemove = [];
        $parent = strtoupper(trim((string) ($v['parent'] ?? '')));
        if ($parent !== '') {
            $index = $this->campaignIndex();
            foreach ($before as $name) {
                $hit = $index[$this->cousins->normalize($name)] ?? null;
                if ($hit && strtoupper($hit['parent']) === $parent
                    && $this->cousins->normalize($name) !== $this->cousins->normalize($campaign)) {
                    $toRemove[] = $name;
                }
            }
        } elseif (! empty($v['cousin'])) {
            $toRemove[] = $v['cousin'];
        }

        foreach ($toRemove as $name) {
            $this->cousins->unlinkFromGroup($name, $this->effectiveGroup($campaign), Auth::user()?->name ?? 'N/A');
        }
        // Campaigns that left the group no longer carry its name.
        $this->clearGroupName($toRemove);
        $this->forgetBadges();

        return response()->json([
            'success' => true,
            'message' => 'Cousin removed.',
            'affected' => $this->cousinRows($before),
        ]);
    }

    /**
     * Re-run the keyword sync for the cousin group of a campaign.
     */
    public function syncCousins(Request $request): JsonResponse
    {
        $v = $request->validate(['campaign' => ['required', 'string', 'max:512']]);

        $group = $this->effectiveGroup($v['campaign']);
        if ($this->cousinCampaignNames($group, $v['campaign']) === []) {
            return response()->json(['success' => false, 'message' => 'This campaign has no cousins yet.'], 422);
        }

        // Apply: make the parent-level cousins permanent for every campaign under the same parent.
        $this->cousins->syncFullyConnectedGroup($group, Auth::user()?->name ?? 'N/A');

        @set_time_limit(300);
        $added = $this->syncCousinKeywords($group);
        $addedNeg = $this->syncCousinKeywords($group, true, 'neg');

        return response()->json([
            'success' => true,
            'message' => 'Synced across '.count($group).' campaigns: '
                .$added.' keyword'.($added === 1 ? '' : 's').', '
                .$addedNeg.' negative keyword'.($addedNeg === 1 ? '' : 's').'.',
            'added' => $added,
            'added_negatives' => $addedNeg,
            'affected' => $this->cousinRows($group),
        ]);
    }

    /**
     * Campaign names per parent (PT / archived already excluded), keyed by upper-cased parent.
     *
     * @return array<string, list<string>>
     */
    protected function siblingsByParent(): array
    {
        if ($this->siblingsMemo !== null) {
            return $this->siblingsMemo;
        }
        $out = [];
        foreach ($this->campaignsGroupedByParent() as $c) {
            if ($c['parent'] !== '') {
                $out[strtoupper($c['parent'])][] = $c['campaign_name'];
            }
        }

        return $this->siblingsMemo = $out;
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    protected function uniqueByNorm(array $names): array
    {
        $seen = [];
        foreach ($names as $n) {
            $seen[$this->cousins->normalize((string) $n)] ??= trim((string) $n);
        }
        unset($seen['']);

        return array_values($seen);
    }

    /**
     * Cousin group of a campaign. Campaigns under the same parent share one group: it is the union of the
     * stored links of all campaigns of that parent (plus the siblings themselves).
     *
     * @param  array<string, list<string>>|null  $groupsMap  pre-loaded groupsMap()
     * @return list<string>
     */
    protected function effectiveGroup(string $campaign, ?array $groupsMap = null): array
    {
        $groupsMap ??= $this->hasTable(self::COUSIN_TABLE) ? $this->cousins->groupsMap() : [];
        $norm = $this->cousins->normalize($campaign);
        $members = $groupsMap[$norm] ?? [$campaign];

        $self = $this->campaignIndex()[$norm] ?? null;
        if ($self && $self['parent'] !== '') {
            foreach ($this->siblingsByParent()[strtoupper($self['parent'])] ?? [] as $sib) {
                foreach ($groupsMap[$this->cousins->normalize($sib)] ?? [$sib] as $m) {
                    $members[] = $m;
                }
            }
        }

        return $this->uniqueByNorm($members);
    }

    /**
     * The cousins of a campaign: its group minus itself and minus its own siblings (same parent).
     *
     * @param  list<string>  $group
     * @return list<string>
     */
    protected function cousinCampaignNames(array $group, string $campaign): array
    {
        $index = $this->campaignIndex();
        $selfNorm = $this->cousins->normalize($campaign);
        $ownParent = strtoupper($index[$selfNorm]['parent'] ?? '');

        return array_values(array_filter($group, function ($m) use ($index, $selfNorm, $ownParent) {
            $norm = $this->cousins->normalize((string) $m);
            if ($norm === $selfNorm || ! isset($index[$norm])) {
                return false; // itself, or hidden on this page (PT / archived)
            }

            return $ownParent === '' || strtoupper($index[$norm]['parent']) !== $ownParent;
        }));
    }

    private function parentKey(string $parent): string
    {
        return strtoupper(trim((string) preg_replace('/\s+/u', ' ', str_replace("\xC2\xA0", ' ', $parent))));
    }

    /**
     * Product Master title for each parent (autofill). The parent's own "PARENT …" row wins when it has a
     * title; otherwise the first child SKU of that parent that has one. Prefers the 150-char title.
     *
     * @param  list<string>  $parents
     * @return array<string, string> normalized parent => title
     */
    protected function parentTitles(array $parents): array
    {
        $keys = [];
        foreach ($parents as $p) {
            $k = $this->parentKey($p);
            if ($k !== '') {
                $keys[$k] = true;
            }
        }
        if ($keys === [] || ! $this->hasTable('product_master')) {
            return [];
        }

        $cols = array_values(array_filter(
            ['title150', 'title100', 'title80', 'title60'],
            fn (string $c) => $this->hasColumn('product_master', $c)
        ));
        if ($cols === []) {
            return [];
        }

        $out = [];
        $rank = [];
        foreach (array_chunk(array_keys($keys), 500) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '?'));
            $rows = DB::table('product_master')
                ->whereNull('deleted_at')
                // A plain bound string (U+00A0) takes the column's collation; CHAR(160) / UNHEX() would not convert or would clash.
                ->whereRaw('UPPER(TRIM(REPLACE(parent, ?, " "))) IN ('.$ph.')', array_merge(["\u{00A0}"], $chunk))
                ->orderBy('sku')
                ->get(array_merge(['parent', 'sku'], $cols));

            foreach ($rows as $r) {
                $title = '';
                foreach ($cols as $c) {
                    $t = trim((string) ($r->{$c} ?? ''));
                    if ($t !== '') {
                        $title = $t;
                        break;
                    }
                }
                if ($title === '') {
                    continue;
                }
                $key = $this->parentKey((string) $r->parent);
                $isParentRow = str_starts_with(strtoupper(trim((string) $r->sku)), 'PARENT');
                // 2 = the PARENT row itself, 1 = first child with a title.
                $score = $isParentRow ? 2 : 1;
                if (! isset($out[$key]) || $score > $rank[$key]) {
                    $out[$key] = $title;
                    $rank[$key] = $score;
                }
            }
        }

        return $out;
    }

    /**
     * Saved cousin names: normalized campaign name => name.
     *
     * @return array<string, string>
     */
    protected function cousinNameMap(): array
    {
        if (! $this->hasTable(self::COUSIN_NAME_TABLE)) {
            return [];
        }

        return DB::table(self::COUSIN_NAME_TABLE)->pluck('name', 'campaign_norm')->all();
    }

    /**
     * Name of the cousin group a campaign belongs to: its own saved name, else the first member's.
     *
     * @param  list<string>  $group
     * @param  array<string, string>  $nameMap
     */
    protected function groupNameFor(array $group, string $campaign, array $nameMap): string
    {
        $own = $nameMap[$this->cousins->normalize($campaign)] ?? '';
        if ($own !== '') {
            return $own;
        }
        foreach ($group as $member) {
            $n = $nameMap[$this->cousins->normalize($member)] ?? '';
            if ($n !== '') {
                return $n;
            }
        }

        return '';
    }

    /**
     * Save one name on every campaign of a cousin group (empty name removes it).
     *
     * @param  list<string>  $group
     */
    protected function setGroupName(array $group, string $name): void
    {
        if (! $this->hasTable(self::COUSIN_NAME_TABLE)) {
            return;
        }
        $name = mb_substr(trim($name), 0, 191);
        if ($name === '') {
            $this->clearGroupName($group);

            return;
        }

        $now = now();
        $user = Auth::user()?->name;
        foreach ($group as $member) {
            $norm = $this->cousins->normalize($member);
            if ($norm === '') {
                continue;
            }
            DB::table(self::COUSIN_NAME_TABLE)->updateOrInsert(
                ['campaign_norm' => $norm],
                ['campaign' => mb_substr(trim($member), 0, 512), 'name' => $name, 'updated_by' => $user, 'updated_at' => $now, 'created_at' => $now]
            );
        }
    }

    /**
     * @param  list<string>  $campaigns
     */
    protected function clearGroupName(array $campaigns): void
    {
        if (! $this->hasTable(self::COUSIN_NAME_TABLE) || $campaigns === []) {
            return;
        }
        $norms = array_values(array_filter(array_map(fn ($c) => $this->cousins->normalize((string) $c), $campaigns)));
        if ($norms !== []) {
            DB::table(self::COUSIN_NAME_TABLE)->whereIn('campaign_norm', $norms)->delete();
        }
    }

    /**
     * Name (or rename) the cousin group of a campaign. The name is stored on every campaign in the group.
     */
    public function nameCousins(Request $request): JsonResponse
    {
        $v = $request->validate([
            'campaign' => ['required', 'string', 'max:512'],
            'name' => ['nullable', 'string', 'max:191'],
        ]);

        if (! $this->hasTable(self::COUSIN_NAME_TABLE)) {
            return response()->json(['success' => false, 'message' => 'Table is missing. Run php artisan migrate.'], 500);
        }

        $campaign = trim($v['campaign']);
        if (! isset($this->campaignIndex()[$this->cousins->normalize($campaign)])) {
            return response()->json(['success' => false, 'message' => 'Unknown campaign.'], 422);
        }

        $group = $this->effectiveGroup($campaign);
        $name = trim((string) ($v['name'] ?? ''));
        $this->setGroupName($group, $name);

        return response()->json([
            'success' => true,
            'message' => $name === '' ? 'Name removed.' : 'Name saved for '.count($group).' campaign'.(count($group) === 1 ? '' : 's').'.',
            'affected' => $this->cousinRows($group),
        ]);
    }

    /**
     * Cousin campaigns of one campaign, grouped by parent for display.
     *
     * @param  list<string>  $cousinNames
     * @return list<array{parent: string, campaigns: list<string>}>
     */
    protected function cousinParents(array $cousinNames): array
    {
        $index = $this->campaignIndex();
        $byParent = [];
        $loose = [];
        foreach ($cousinNames as $name) {
            $hit = $index[$this->cousins->normalize($name)] ?? null;
            if ($hit === null) {
                continue; // PT / archived campaigns are hidden on this page
            }
            $parent = $hit['parent'];
            if ($parent === '') {
                $loose[] = ['parent' => '', 'campaigns' => [$name]];

                continue;
            }
            $key = strtoupper($parent);
            $byParent[$key] ??= ['parent' => $parent, 'campaigns' => []];
            $byParent[$key]['campaigns'][] = $name;
        }
        ksort($byParent, SORT_NATURAL);

        return array_merge(array_values($byParent), $loose);
    }

    /**
     * @param  list<string>  $group
     * @return list<array{campaign: string, cousins: list<string>, cousin_parents: list<array>, cousin_count: int}>
     */
    protected function cousinRows(array $group): array
    {
        $nameMap = $this->cousinNameMap();
        $map = $this->hasTable(self::COUSIN_TABLE) ? $this->cousins->groupsMap() : [];
        $rows = [];
        foreach ($group as $member) {
            $current = $this->effectiveGroup($member, $map);
            $others = $this->cousinCampaignNames($current, $member);
            $parents = $this->cousinParents($others);
            $rows[] = [
                'campaign' => $member,
                'cousin_name' => $this->groupNameFor($current, $member, $nameMap),
                'cousins' => $others,
                'cousin_parents' => $parents,
                'cousin_count' => count($parents),
            ];
        }

        return $rows;
    }

    /**
     * KW campaigns are every campaign that is not product targeting (name contains " PT" / ends with PT).
     */
    protected function isProductTargetingCampaign(string $campaignName): bool
    {
        $n = preg_replace('/\s+/u', ' ', trim(str_replace("\xC2\xA0", ' ', $campaignName)));
        if ($n === '') {
            return false;
        }
        $u = strtoupper($n);

        return str_contains($u, ' PT') || preg_match('/PT\.?\s*$/u', $u) === 1;
    }

    /**
     * Add keywords stored in the app to the per-campaign target lists (no duplicates).
     *
     * @param  array<string, list<string>>  $map
     * @param  list<string>  $campaignIds
     * @return array<string, list<string>>
     */
    protected function mergeStoredKeywords(array $map, array $campaignIds, string $table = self::STORE_TABLE): array
    {
        $campaignIds = array_values(array_unique(array_filter($campaignIds)));
        if ($campaignIds === [] || ! $this->hasTable($table)) {
            return $map;
        }

        $seen = [];
        foreach ($map as $cid => $labels) {
            foreach ($labels as $label) {
                $seen[$cid][strtolower($label)] = true;
            }
        }

        $touched = [];
        foreach (DB::table($table)->whereIn('campaign_id', $campaignIds)->get(['campaign_id', 'keyword', 'match_type']) as $row) {
            $cid = (string) $row->campaign_id;
            $label = $row->match_type !== '' ? $row->keyword.' ('.$row->match_type.')' : $row->keyword;
            if (isset($seen[$cid][strtolower($label)])) {
                continue;
            }
            $seen[$cid][strtolower($label)] = true;
            $map[$cid][] = $label;
            $touched[$cid] = true;
        }

        foreach (array_keys($touched) as $cid) {
            sort($map[$cid], SORT_NATURAL | SORT_FLAG_CASE);
            $map[$cid] = array_values($map[$cid]);
        }

        return $map;
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

        // Local data only (synced keyword reports; the keyword store is merged in by the caller). Live Amazon calls
        // made every search / page a multi-second wait - live keywords now come in through the Pull buttons.
        $map = $this->targetsFromKeywordReports($campaignIds);

        foreach ($map as $cid => $labels) {
            sort($labels, SORT_NATURAL | SORT_FLAG_CASE);
            $map[$cid] = array_values($labels);
        }

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
        if (! $this->hasTable(self::KW_TABLE)) {
            return [];
        }

        $cols = $this->columnsOf(self::KW_TABLE);
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
