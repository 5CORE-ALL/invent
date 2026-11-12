<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\AmazonAds\AmazonFbmTargetingController;
use App\Http\Controllers\Controller;
use App\Models\GoogleAdsNegativeKeyword;
use App\Services\AmazonKwCousinLinkService;
use App\Services\GoogleAdsSbidService;
use App\Support\AmazonAdsCampaignSkuMetrics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Google "N Targeting" (negative keywords) with KW cousins and push - the Google twin of /amazon-ads/fbm-targeting.
 *
 * No tables of its own. It only reads / writes what already exists:
 *  - negative keywords: google_ads_negative_keywords (synced by app:fetch-google-ads-negative-keywords; pushed
 *    keywords are mirrored back into it, exactly like the N Target modal on Google Shopping does);
 *  - campaigns: latest row per campaign in google_ads_campaigns (Shopping scope, like the Shopping grid);
 *  - cousins: the KW cousin groups of the Keyword Targeting page (amazon_kw_cousin_links / _names). A group is a set
 *    of parents, so it applies to Google campaigns of those parents as well. Adding / removing / naming cousins here
 *    writes to those same tables (through the same link service), so both pages always show the same groups.
 */
class GoogleNegTargetingController extends Controller
{
    /** N Target "low" threshold (same as the Google Shopping badge). */
    private const LOW = 50;

    /** Campaigns pushed per request; the page loops so no request runs long. */
    private const PUSH_BATCH = 3;

    private const MATCHES = ['PHRASE', 'EXACT', 'BROAD'];

    private const CACHE_CAMPAIGNS = 'gads_neg_tgt_campaigns_v1';

    private const CACHE_COUSINS = 'gads_neg_tgt_cousins_v1';

    public function index()
    {
        return view('campaign.google-neg-targeting');
    }

    public function data(): JsonResponse
    {
        $cousins = $this->cousins();
        $counts = $this->negativeCounts();

        $rows = [];
        $used = [];
        foreach ($this->campaigns() as $c) {
            $gid = $cousins['parent_group'][strtoupper($c['parent'])] ?? null;
            $group = $gid !== null ? $cousins['groups'][$gid] : null;
            if ($gid !== null) {
                $used[$gid] = $group;
            }
            $rows[] = [
                'campaign_id' => $c['campaign_id'],
                'campaign_name' => $c['campaign_name'],
                'status' => $c['status'],
                'parent' => $c['parent'],
                'is_parent' => str_starts_with(strtoupper($c['campaign_name']), 'PARENT'),
                'gid' => $gid,
                'cousin_name' => $group['name'] ?? '',
                'cousin_count' => $group ? count($group['parents']) - 1 : 0,
                'neg_count' => (int) ($counts[$c['campaign_id']] ?? 0),
            ];
        }

        return response()->json([
            'data' => $rows,
            'groups' => (object) $used,
            'low' => self::LOW,
            'last_sync' => $this->lastSync(),
            'pulling' => $this->isPulling(),
        ]);
    }

    protected function lastSync(): ?string
    {
        return Schema::hasTable('google_ads_negative_keywords')
            ? (DB::table('google_ads_negative_keywords')->max('updated_at') ?: null)
            : null;
    }

    // ---------- Pull (refresh the stored negatives from Google Ads) ----------

    protected function pullFlag(): string
    {
        return storage_path('app/gads_neg_pull.running');
    }

    protected function isPulling(): bool
    {
        $f = $this->pullFlag();

        return is_file($f) && (time() - (int) filemtime($f)) < 1800; // a flag older than 30 min is a crashed run
    }

    /**
     * Run the existing app:fetch-google-ads-negative-keywords command in the background (campaign + ad group levels,
     * which is what N Target counts). The page polls pullStatus().
     */
    public function pull(): JsonResponse
    {
        if ($this->isPulling()) {
            return response()->json(['success' => true, 'running' => true, 'message' => 'A pull is already running.']);
        }

        $php = PHP_BINARY ?: 'php';
        if (stripos($php, 'fpm') !== false || stripos($php, 'cgi') !== false) {
            $cli = trim((string) shell_exec('command -v php 2>/dev/null'));
            if ($cli !== '') {
                $php = $cli;
            }
        }
        $flag = $this->pullFlag();
        @file_put_contents($flag, (string) time());
        $log = storage_path('logs/gads_neg_pull.log');
        $cmd = escapeshellarg($php).' '.escapeshellarg(base_path('artisan')).' app:fetch-google-ads-negative-keywords --level=campaign'
            .' && '.escapeshellarg($php).' '.escapeshellarg(base_path('artisan')).' app:fetch-google-ads-negative-keywords --level=ad_group';
        exec('nohup sh -c '.escapeshellarg($cmd.'; rm -f '.escapeshellarg($flag)).' >> '.escapeshellarg($log).' 2>&1 &');

        return response()->json(['success' => true, 'running' => true, 'message' => 'Pulling negative keywords from Google Ads in the background.']);
    }

    public function pullStatus(): JsonResponse
    {
        return response()->json(['running' => $this->isPulling(), 'last_sync' => $this->lastSync()]);
    }

    // ---------- KW cousins (stored in the Keyword Targeting tables, so both pages always agree) ----------

    /**
     * Amazon KW campaign names per parent (upper-cased): the units the cousin links are made of.
     *
     * @return array<string, list<string>>
     */
    protected function amazonNamesByParent(): array
    {
        $out = [];
        foreach (app(AmazonFbmTargetingController::class)->campaignsGroupedByParent() as $c) {
            $p = strtoupper(trim((string) $c['parent']));
            if ($p !== '') {
                $out[$p][] = (string) $c['campaign_name'];
            }
        }

        return $out;
    }

    /** @return list<string> every linked Amazon campaign name of the parent's cousin group (incl. its own) */
    protected function groupNamesOf(string $parent, array $byParent): array
    {
        $svc = app(AmazonKwCousinLinkService::class);
        $map = $svc->groupsMap();
        $names = $byParent[$parent] ?? [];
        foreach ($byParent[$parent] ?? [] as $n) {
            foreach ($map[$svc->normalize($n)] ?? [] as $m) {
                $names[] = $m;
            }
        }
        $seen = [];
        foreach ($names as $n) {
            $seen[$svc->normalize((string) $n)] ??= trim((string) $n);
        }
        unset($seen['']);

        return array_values($seen);
    }

    protected function setGroupName(array $names, string $name): void
    {
        if (! Schema::hasTable('amazon_kw_cousin_names')) {
            return;
        }
        $svc = app(AmazonKwCousinLinkService::class);
        $norms = array_values(array_filter(array_map(fn ($n) => $svc->normalize((string) $n), $names)));
        $name = mb_substr(trim($name), 0, 191);
        if ($name === '') {
            if ($norms !== []) {
                DB::table('amazon_kw_cousin_names')->whereIn('campaign_norm', $norms)->delete();
            }

            return;
        }
        $now = now();
        $user = Auth::user()?->name;
        foreach ($names as $n) {
            $norm = $svc->normalize((string) $n);
            if ($norm !== '') {
                DB::table('amazon_kw_cousin_names')->updateOrInsert(
                    ['campaign_norm' => $norm],
                    ['campaign' => mb_substr(trim((string) $n), 0, 512), 'name' => $name, 'updated_by' => $user, 'updated_at' => $now, 'created_at' => $now]
                );
            }
        }
    }

    protected function forgetCousinCaches(): void
    {
        Cache::forget(self::CACHE_COUSINS);
        Cache::forget('amz_kw_targeting_badges_v3'); // the Keyword Targeting badges count cousins too
    }

    /** Parents that can be added as cousins of ?parent= (they need an Amazon KW campaign to link to). */
    public function cousinParents(Request $request): JsonResponse
    {
        $own = strtoupper(trim((string) $request->query('parent', '')));
        $q = mb_strtolower(trim((string) $request->query('q', '')));
        $by = $this->amazonNamesByParent();
        $cousins = $this->cousins();

        $inGroup = [$own => true];
        $gid = $cousins['parent_group'][$own] ?? null;
        foreach ($gid !== null ? $cousins['groups'][$gid]['parents'] : [] as $p) {
            $inGroup[$p] = true;
        }
        $google = [];
        foreach ($this->campaigns() as $c) {
            if ($c['parent'] !== '') {
                $google[$c['parent']] = ($google[$c['parent']] ?? 0) + 1;
            }
        }

        $out = [];
        foreach ($by as $p => $names) {
            $p = (string) $p;
            if (isset($inGroup[$p]) || ($q !== '' && ! str_contains(mb_strtolower($p), $q))) {
                continue;
            }
            $out[] = ['parent' => $p, 'campaigns' => count($names), 'google' => (int) ($google[$p] ?? 0)];
        }
        usort($out, static fn (array $a, array $b) => strnatcasecmp($a['parent'], $b['parent']));

        return response()->json(['success' => true, 'parents' => array_slice($out, 0, 300), 'has_amazon' => isset($by[$own])]);
    }

    /** Add parents as cousins of a parent: all their campaigns join, the group stays one connected set. */
    public function cousinAdd(Request $request): JsonResponse
    {
        $v = $request->validate([
            'parent' => ['required', 'string', 'max:255'],
            'parents' => ['required', 'array', 'min:1', 'max:100'],
            'parents.*' => ['string', 'max:255'],
            'name' => ['nullable', 'string', 'max:191'],
        ]);
        if (! Schema::hasTable('amazon_kw_cousin_links')) {
            return response()->json(['success' => false, 'message' => 'Cousin table is missing. Run php artisan migrate.'], 500);
        }

        $own = strtoupper(trim($v['parent']));
        $by = $this->amazonNamesByParent();
        if (empty($by[$own])) {
            return response()->json(['success' => false, 'message' => 'This parent has no Amazon KW campaign yet, so it cannot be linked. Create it on Keyword Targeting first.'], 422);
        }

        $names = $this->groupNamesOf($own, $by);
        $picked = 0;
        $skipped = 0;
        foreach ($v['parents'] as $p) {
            $p = strtoupper(trim($p));
            if ($p === '' || $p === $own) {
                continue;
            }
            if (isset($by[$p])) {
                $names = array_merge($names, $by[$p], $this->groupNamesOf($p, $by));
                $picked++;
            } else {
                $skipped++;
            }
        }
        if ($picked === 0) {
            return response()->json(['success' => false, 'message' => 'Pick at least one other parent that has an Amazon KW campaign.'], 422);
        }

        $svc = app(AmazonKwCousinLinkService::class);
        $seen = [];
        foreach ($names as $n) {
            $seen[$svc->normalize((string) $n)] ??= trim((string) $n);
        }
        unset($seen['']);
        $group = array_values($seen);

        @set_time_limit(300);
        $svc->syncFullyConnectedGroup($group, Auth::user()?->name ?? 'N/A');

        $typed = trim((string) ($v['name'] ?? ''));
        if ($typed === '' && Schema::hasTable('amazon_kw_cousin_names')) {
            $typed = (string) (DB::table('amazon_kw_cousin_names')->whereIn('campaign_norm', array_keys($seen))->orderBy('id')->value('name') ?? '');
        }
        $this->setGroupName($group, $typed);
        $this->forgetCousinCaches();

        return response()->json([
            'success' => true,
            'message' => 'Cousins saved ('.$picked.' parent'.($picked === 1 ? '' : 's').')'.($skipped ? ', '.$skipped.' skipped (no Amazon KW campaign)' : '').'.',
        ]);
    }

    /** Remove one cousin parent (all of its campaigns) from the group of ?parent=. */
    public function cousinRemove(Request $request): JsonResponse
    {
        $v = $request->validate(['parent' => ['required', 'string', 'max:255'], 'cousin_parent' => ['required', 'string', 'max:255']]);
        if (! Schema::hasTable('amazon_kw_cousin_links')) {
            return response()->json(['success' => false, 'message' => 'Cousin table is missing.'], 500);
        }
        $own = strtoupper(trim($v['parent']));
        $gone = strtoupper(trim($v['cousin_parent']));
        $by = $this->amazonNamesByParent();
        if ($own === $gone || empty($by[$gone])) {
            return response()->json(['success' => false, 'message' => 'Nothing to remove.'], 422);
        }

        @set_time_limit(300);
        $svc = app(AmazonKwCousinLinkService::class);
        $user = Auth::user()?->name ?? 'N/A';
        foreach ($by[$gone] as $name) {
            $svc->unlinkFromGroup($name, $this->groupNamesOf($own, $by), $user);
        }
        $this->setGroupName($by[$gone], ''); // campaigns that left no longer carry the group's name
        $this->forgetCousinCaches();

        return response()->json(['success' => true, 'message' => 'Cousin removed.']);
    }

    /** Name (or rename) the cousin group of ?parent=. */
    public function cousinName(Request $request): JsonResponse
    {
        $v = $request->validate(['parent' => ['required', 'string', 'max:255'], 'name' => ['nullable', 'string', 'max:191']]);
        $own = strtoupper(trim($v['parent']));
        $group = $this->groupNamesOf($own, $this->amazonNamesByParent());
        if ($group === []) {
            return response()->json(['success' => false, 'message' => 'This parent has no Amazon KW campaign to name a group on.'], 422);
        }
        $name = trim((string) ($v['name'] ?? ''));
        $this->setGroupName($group, $name);
        $this->forgetCousinCaches();

        return response()->json(['success' => true, 'message' => $name === '' ? 'Name removed.' : 'Name saved.']);
    }

    /**
     * What each campaign is missing: the union of the campaign-level negatives of its cousins, minus its own.
     * Read-only. ids = ticked campaigns; none = every campaign that has cousins.
     */
    public function pushCheck(Request $request): JsonResponse
    {
        $v = $request->validate(['ids' => ['nullable', 'array', 'max:500'], 'ids.*' => ['string', 'max:32']]);
        $byId = $this->campaignsById();
        $ids = $this->cleanIds($v['ids'] ?? []);
        if ($ids === []) {
            $ids = array_keys($byId);
        }

        $missing = $this->missingFor($ids);
        $rows = [];
        $upToDate = 0;
        foreach ($missing['eligible'] as $id) {
            $list = $missing['missing'][$id] ?? [];
            if ($list === []) {
                $upToDate++;

                continue;
            }
            $rows[] = [
                'campaign_id' => $id,
                'campaign_name' => $byId[$id]['campaign_name'],
                'parent' => $byId[$id]['parent'],
                'to_add' => count($list),
                'sample' => array_map(static fn (array $k) => $k['text'].' ['.$k['match'].']', array_slice($list, 0, 6)),
            ];
        }
        usort($rows, static fn (array $a, array $b) => $b['to_add'] <=> $a['to_add'] ?: strnatcasecmp($a['campaign_name'], $b['campaign_name']));

        return response()->json([
            'success' => true,
            'rows' => $rows,
            'up_to_date' => $upToDate,
            'no_cousins' => count($ids) - count($missing['eligible']),
            'batch' => self::PUSH_BATCH,
        ]);
    }

    /**
     * Push the missing negatives to Google Ads (campaign level) for a few campaigns. The missing list is worked out
     * again here from the stored data, so nothing the browser sends is trusted except the campaign ids.
     */
    public function push(Request $request): JsonResponse
    {
        $v = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:'.self::PUSH_BATCH], 'ids.*' => ['string', 'max:32']]);

        $customerId = str_replace('-', '', (string) config('services.google_ads.login_customer_id'));
        if ($customerId === '') {
            return response()->json(['success' => false, 'message' => 'Google Ads customer ID is not configured.'], 500);
        }

        @set_time_limit(180);
        $byId = $this->campaignsById();
        $ids = $this->cleanIds($v['ids']);
        $missing = $this->missingFor($ids)['missing'];

        $results = [];
        foreach ($ids as $id) {
            $name = $byId[$id]['campaign_name'] ?? $id;
            $list = $missing[$id] ?? [];
            if ($list === []) {
                $results[] = ['campaign_id' => $id, 'campaign_name' => $name, 'to_add' => 0, 'added' => 0, 'failed' => 0, 'message' => 'Nothing to add'];

                continue;
            }

            $byMatch = [];
            foreach ($list as $k) {
                $byMatch[$k['match']][] = $k['text'];
            }
            $added = 0;
            $failed = 0;
            $errors = [];
            $mirror = [];
            foreach ($byMatch as $match => $texts) {
                try {
                    $res = app(GoogleAdsSbidService::class)->pushCampaignNegativeKeywords($customerId, $id, $texts, $match);
                } catch (\Throwable $e) {
                    report($e);
                    $failed += count($texts);
                    $errors[] = GoogleAdsSbidService::briefGoogleError($e);

                    continue;
                }
                $added += (int) $res['added'];
                $failed += (int) $res['failed'];
                foreach ($res['errors'] ?? [] as $err) {
                    $errors[] = $err;
                }
                foreach ($res['created'] ?? [] as $c) {
                    $mirror[] = [
                        'level' => GoogleAdsNegativeKeyword::LEVEL_CAMPAIGN,
                        'customer_id' => $customerId,
                        'campaign_id' => $id,
                        'campaign_name' => $name,
                        'criterion_id' => str_contains($c['resource'], '~') ? substr($c['resource'], strrpos($c['resource'], '~') + 1) : '',
                        'criterion_resource_name' => $c['resource'],
                        'keyword_text' => $c['text'],
                        'match_type' => $match,
                        'criterion_type' => 'KEYWORD',
                        'status' => 'ENABLED',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
            if ($mirror !== []) {
                try {
                    GoogleAdsNegativeKeyword::upsert($mirror, ['criterion_resource_name'], ['keyword_text', 'match_type', 'status', 'updated_at']);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
            $results[] = [
                'campaign_id' => $id,
                'campaign_name' => $name,
                'to_add' => count($list),
                'added' => $added,
                'failed' => $failed,
                'message' => $errors === [] ? '' : mb_substr(implode(' | ', array_unique($errors)), 0, 240),
            ];
        }

        // The Google Shopping grid caches its rows (N Target included): let it see the new negatives.
        foreach (['shopping', 'serp', 'youtube'] as $channel) {
            $k = 'gads_raw_grid_ver:'.$channel;
            if (! Cache::add($k, 2, now()->addYear())) {
                Cache::increment($k);
            }
        }

        return response()->json(['success' => true, 'results' => $results]);
    }

    /**
     * @param  list<mixed>  $ids
     * @return list<string>
     */
    protected function cleanIds(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $s = preg_replace('/\D/', '', (string) $id);
            if ($s !== '') {
                $out[$s] = $s;
            }
        }

        return array_values($out);
    }

    /**
     * Shopping campaigns: latest row per campaign id, with the product_master parent resolved from the name
     * (same resolver as the Amazon page; Google names are SKUs / "PARENT …" too).
     *
     * @return list<array{campaign_id: string, campaign_name: string, status: string, parent: string}>
     */
    protected function campaigns(): array
    {
        return Cache::remember(self::CACHE_CAMPAIGNS, now()->addMinutes(5), function () {
            $latest = DB::table('google_ads_campaigns')
                ->whereNotNull('campaign_id')->where('campaign_id', '!=', '')
                ->selectRaw('campaign_id, MAX(`date`) as md')
                ->groupBy('campaign_id');

            $rows = DB::table('google_ads_campaigns as g')
                ->joinSub($latest, 'l', fn ($j) => $j->on('g.campaign_id', '=', 'l.campaign_id')->on('g.date', '=', 'l.md'))
                ->whereRaw('UPPER(g.campaign_name) NOT LIKE ?', ['% SEARCH%'])
                ->whereRaw('UPPER(g.campaign_name) NOT LIKE ?', ['% YT'])
                ->whereRaw("UPPER(COALESCE(g.campaign_status, '')) <> 'REMOVED'")
                ->get(['g.campaign_id', 'g.campaign_name', 'g.campaign_status']);

            $list = [];
            foreach ($rows as $r) {
                $id = trim((string) $r->campaign_id);
                $name = trim((string) $r->campaign_name);
                if ($id !== '' && $name !== '') {
                    $list[$id] = ['campaign_id' => $id, 'campaign_name' => $name, 'status' => strtoupper(trim((string) $r->campaign_status)), 'parent' => ''];
                }
            }
            $parents = AmazonAdsCampaignSkuMetrics::parentFamiliesForCampaignNames(array_column($list, 'campaign_name'));
            foreach ($list as &$c) {
                $c['parent'] = strtoupper(trim((string) ($parents[$c['campaign_name']] ?? '')));
            }
            unset($c);
            $list = array_values($list);
            usort($list, static function (array $a, array $b) {
                if (($a['parent'] === '') !== ($b['parent'] === '')) {
                    return $a['parent'] === '' ? 1 : -1;
                }

                return strnatcasecmp($a['parent'], $b['parent']) ?: strnatcasecmp($a['campaign_name'], $b['campaign_name']);
            });

            return $list;
        });
    }

    /** @return array<string, array{campaign_id: string, campaign_name: string, status: string, parent: string}> */
    protected function campaignsById(): array
    {
        $out = [];
        foreach ($this->campaigns() as $c) {
            $out[$c['campaign_id']] = $c;
        }

        return $out;
    }

    /**
     * Cousin groups, taken from the Keyword Targeting page's KW cousin links (no copy of that data): every stored
     * group of campaign names is turned into the set of parents it covers, and groups sharing a parent are merged.
     *
     * @return array{parent_group: array<string, string>, groups: array<string, array{name: string, parents: list<string>}>}
     */
    protected function cousins(): array
    {
        return Cache::remember(self::CACHE_COUSINS, now()->addMinutes(5), function () {
            $empty = ['parent_group' => [], 'groups' => []];
            if (! Schema::hasTable('amazon_kw_cousin_links')) {
                return $empty;
            }
            $svc = app(AmazonKwCousinLinkService::class);
            $map = $svc->groupsMap();
            if ($map === []) {
                return $empty;
            }

            $names = [];
            foreach ($map as $members) {
                foreach ($members as $m) {
                    $names[(string) $m] = true;
                }
            }
            $fam = AmazonAdsCampaignSkuMetrics::parentFamiliesForCampaignNames(array_map('strval', array_keys($names)));
            $nameMap = Schema::hasTable('amazon_kw_cousin_names')
                ? DB::table('amazon_kw_cousin_names')->pluck('name', 'campaign_norm')->all()
                : [];

            $uf = [];
            $find = function (string $x) use (&$uf): string {
                $uf[$x] ??= $x;
                while ($uf[$x] !== $x) {
                    $uf[$x] = $uf[$uf[$x]];
                    $x = $uf[$x];
                }

                return $x;
            };

            $seen = [];
            $groupNames = []; // first parent of a group => saved cousin name
            foreach ($map as $members) {
                $sig = md5(implode('|', $members));
                if (isset($seen[$sig])) {
                    continue;
                }
                $seen[$sig] = true;
                $parents = [];
                $label = '';
                foreach ($members as $m) {
                    $p = strtoupper(trim((string) ($fam[$m] ?? '')));
                    if ($p !== '') {
                        $parents[$p] = true;
                    }
                    $label = $label !== '' ? $label : trim((string) ($nameMap[$svc->normalize((string) $m)] ?? ''));
                }
                $parents = array_keys($parents);
                if (count($parents) < 2) {
                    continue; // one parent only: nobody to share with
                }
                $first = (string) $parents[0];
                foreach ($parents as $p) {
                    $ra = $find((string) $p);
                    $rb = $find($first);
                    if ($ra !== $rb) {
                        $uf[$ra] = $rb;
                    }
                }
                if ($label !== '') {
                    $groupNames[$first] = $groupNames[$first] ?? $label;
                }
            }

            $out = $empty;
            foreach (array_keys($uf) as $p) {
                $root = $find((string) $p);
                $out['parent_group'][(string) $p] = $root;
                $out['groups'][$root]['parents'][] = (string) $p;
                $out['groups'][$root]['name'] ??= '';
            }
            foreach ($groupNames as $first => $label) {
                $root = $find((string) $first);
                if (($out['groups'][$root]['name'] ?? '') === '') {
                    $out['groups'][$root]['name'] = $label;
                }
            }
            foreach ($out['groups'] as &$g) {
                sort($g['parents'], SORT_NATURAL | SORT_FLAG_CASE);
            }
            unset($g);

            return $out;
        });
    }

    /** Negative keywords per campaign as the N Target column counts them (campaign + ad group level, not removed). */
    protected function negativeCounts(): array
    {
        if (! Schema::hasTable('google_ads_negative_keywords')) {
            return [];
        }

        return $this->liveNegatives(DB::table('google_ads_negative_keywords')
            ->whereIn('level', [GoogleAdsNegativeKeyword::LEVEL_CAMPAIGN, GoogleAdsNegativeKeyword::LEVEL_AD_GROUP])
            ->whereNotNull('campaign_id')->where('campaign_id', '!=', ''))
            ->selectRaw('campaign_id, COUNT(*) AS c')->groupBy('campaign_id')
            ->pluck('c', 'campaign_id')->all();
    }

    protected function liveNegatives($query)
    {
        return $query->where(function ($w) {
            $w->whereNull('status')->orWhereRaw("UPPER(status) NOT IN ('REMOVED', 'ARCHIVED')");
        });
    }

    /**
     * Campaign-level negatives of the given campaigns: campaign_id => [dedupe key => ['text', 'match']].
     *
     * @param  list<string>  $campaignIds
     */
    protected function campaignNegatives(array $campaignIds): array
    {
        $out = [];
        if (! Schema::hasTable('google_ads_negative_keywords')) {
            return $out;
        }
        foreach (array_chunk($campaignIds, 1000) as $chunk) {
            $q = $this->liveNegatives(DB::table('google_ads_negative_keywords')
                ->where('level', GoogleAdsNegativeKeyword::LEVEL_CAMPAIGN)
                ->whereIn('campaign_id', $chunk)
                ->whereNotNull('keyword_text')->where('keyword_text', '!=', ''));
            foreach ($q->get(['campaign_id', 'keyword_text', 'match_type']) as $r) {
                $match = strtoupper(trim((string) $r->match_type));
                $text = trim((string) $r->keyword_text);
                if (! in_array($match, self::MATCHES, true) || $text === '') {
                    continue;
                }
                $out[trim((string) $r->campaign_id)][mb_strtolower($text).'|'.$match] = ['text' => $text, 'match' => $match];
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $ids
     * @return array{eligible: list<string>, missing: array<string, list<array{text: string, match: string}>>}
     */
    protected function missingFor(array $ids): array
    {
        $byId = $this->campaignsById();
        $cousins = $this->cousins();
        if ($cousins['groups'] === []) {
            return ['eligible' => [], 'missing' => []];
        }

        $members = []; // group => campaign ids (own parent + cousin parents)
        foreach ($byId as $c) {
            $g = $cousins['parent_group'][$c['parent']] ?? null;
            if ($g !== null && $c['parent'] !== '') {
                $members[$g][] = $c['campaign_id'];
            }
        }

        $targets = [];
        $need = [];
        foreach ($ids as $id) {
            $c = $byId[$id] ?? null;
            $g = $c ? ($cousins['parent_group'][$c['parent']] ?? null) : null;
            if ($g === null || $c['parent'] === '') {
                continue;
            }
            $targets[$id] = $g;
            foreach ($members[$g] as $m) {
                $need[$m] = true;
            }
        }

        $store = $this->campaignNegatives(array_keys($need));
        $missing = [];
        foreach ($targets as $id => $g) {
            $have = $store[$id] ?? [];
            $list = [];
            foreach ($members[$g] as $m) {
                if ((string) $m === (string) $id) {
                    continue;
                }
                foreach ($store[$m] ?? [] as $key => $kw) {
                    if (! isset($have[$key])) {
                        $list[$key] = $kw;
                    }
                }
            }
            $missing[(string) $id] = array_values($list);
        }

        return ['eligible' => array_map('strval', array_keys($targets)), 'missing' => $missing];
    }
}
