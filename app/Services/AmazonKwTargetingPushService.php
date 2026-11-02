<?php

namespace App\Services;

use App\Models\AmazonAdsMissingLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Pushes the keywords stored in the app (amazon_kw_targeting_keywords) to Amazon:
 *  - every KW campaign (Sponsored Products) gets the keywords it is missing;
 *  - every parent's HL campaign (Sponsored Brands, linked in amazon_ads_missing_links as type HL)
 *    gets the union of the keywords of that parent's KW campaigns.
 * Keywords already live on the campaign (same text + match type) are never sent again.
 * Only BROAD / PHRASE / EXACT keywords are pushed. Negative keywords are not touched.
 */
class AmazonKwTargetingPushService
{
    private const STORE_TABLE = 'amazon_kw_targeting_keywords';

    private const MATCH_TYPES = ['BROAD', 'PHRASE', 'EXACT'];

    public function __construct(private AmazonAdsService $ads)
    {
    }

    /**
     * @param  list<array{parent: string, campaign_id: string, campaign_name: string}>  $kwCampaigns  non-PT SP campaigns
     * @return array{rows: list<array<string, mixed>>, totals: array<string, int>, errors: list<string>}
     */
    /** Most hand-picked campaigns that are evened out against each other. */
    private const MAX_SHARE_CAMPAIGNS = 40;

    public function run(array $kwCampaigns, bool $dryRun, ?callable $progress = null, ?array $only = null, ?array $sharePool = null): array
    {
        $errors = [];
        $rows = [];
        // Every real push gets a token; the keywords it sends are logged so the run can be undone later.
        $runToken = $dryRun ? '' : (date('YmdHis').'-'.bin2hex(random_bytes(3)));
        // $only = ["KW|<campaign id>", "HL|<campaign id>", ...]: push just those campaigns (null = every campaign).
        $onlySet = $only === null ? null : array_fill_keys(array_map('strval', $only), true);
        $picked = static fn (string $type, string $id): bool => $onlySet === null || isset($onlySet[$type.'|'.$id]);
        // One access token for the whole run (hundreds of calls) instead of a token refresh per call.
        $this->ads->reuseAccessToken();
        $report = function (string $note) use (&$rows, $progress): void {
            if ($progress !== null) {
                $progress($rows, $this->totals($rows), $note);
            }
        };

        if (! Schema::hasTable(self::STORE_TABLE)) {
            return ['rows' => [], 'totals' => $this->totals([]), 'errors' => ['Keyword table is missing. Run php artisan migrate.']];
        }

        $kwCampaigns = array_values(array_filter($kwCampaigns, static fn (array $c) => $c['campaign_id'] !== ''));
        // The HL campaign of each parent (one per parent).
        $hlByParent = $this->hlCampaignsByParent();

        // Only some campaigns picked: load just those (and, when an HL campaign is picked, the campaigns of its parent, whose
        // keywords it receives). Pushing one campaign must not read the keywords of all ~800 campaigns first.
        if ($onlySet !== null) {
            $pickedParents = [];
            foreach ($hlByParent as $parentKey => $hl) {
                if ($picked('HL', (string) $hl['id'])) {
                    $pickedParents[$parentKey] = true;
                }
            }
            $kwCampaigns = array_values(array_filter($kwCampaigns, static fn (array $c) => $picked('KW', (string) $c['campaign_id'])
                || ($c['parent'] !== '' && isset($pickedParents[strtoupper($c['parent'])]))));
        }
        $kwIds = array_map(static fn (array $c) => $c['campaign_id'], $kwCampaigns);

        // App keywords per KW campaign, unique by text + match type. A campaign gets its OWN keywords. When the user picked
        // campaigns by hand ($shareAcrossPicked) it also gets the real keywords of the OTHER PICKED campaigns - whatever their
        // parent or KW cousin group - because picking them together is the instruction to even them out. A whole-account run
        // (nothing picked) never shares between campaigns. Keywords the app merely copied in from KW cousins ("cousin"
        // source) are never pushed.
        // $sharePool = ids of every campaign picked by hand (null = whole-account run, no sharing). It may be larger than
        // $kwCampaigns because the sync pushes in chunks.
        $shareAcrossPicked = $sharePool !== null;
        $pickedIds = [];
        foreach ((array) $sharePool as $sid) {
            if ($picked('KW', (string) $sid)) {
                $pickedIds[(string) $sid] = true;
            }
        }
        if (count($pickedIds) > self::MAX_SHARE_CAMPAIGNS) {
            // evening out a big hand-picked list would send every campaign thousands of unrelated keywords
            $errors[] = 'More than '.self::MAX_SHARE_CAMPAIGNS.' campaigns are ticked, so keywords are NOT shared between them: each campaign only gets its own keywords. Tick fewer campaigns to even them out.';
            $pickedIds = [];
            $shareAcrossPicked = false;
        }
        $storeIds = array_values(array_unique(array_merge($kwIds, array_keys($pickedIds))));
        $ownByCampaign = [];
        foreach (array_chunk($storeIds, 1000) as $chunk) {
            $q = DB::table(self::STORE_TABLE)->whereIn('campaign_id', $chunk)->whereIn('match_type', self::MATCH_TYPES)
                ->where('source', '!=', 'cousin')
                ->get(['campaign_id', 'keyword', 'keyword_key', 'match_type']);
            foreach ($q as $r) {
                $ownByCampaign[(string) $r->campaign_id][$r->keyword_key.'|'.$r->match_type] = [
                    'text' => (string) $r->keyword,
                    'match' => (string) $r->match_type,
                    'key' => (string) $r->keyword_key,
                ];
            }
        }
        // Targets ignored (or deleted) in the targets modal are not pushed (per receiving campaign).
        $skip = [];
        if (Schema::hasTable('amazon_kw_targeting_flags')) {
            foreach (array_chunk($kwIds, 1000) as $chunk) {
                foreach (DB::table('amazon_kw_targeting_flags')->where('kind', 'kw')->whereIn('campaign_id', $chunk)
                    ->get(['campaign_id', 'keyword_key', 'match_type']) as $f) {
                    $skip[$f->campaign_id.'|'.$f->keyword_key.'|'.strtoupper((string) $f->match_type)] = true;
                }
            }
        }
        $appByCampaign = [];
        foreach ($kwCampaigns as $c) {
            $cid = (string) $c['campaign_id'];
            $merged = $ownByCampaign[$cid] ?? [];
            if ($shareAcrossPicked && isset($pickedIds[$cid])) {
                foreach ($pickedIds as $sid => $_) {
                    if ($sid === $cid) {
                        continue;
                    }
                    foreach ($ownByCampaign[$sid] ?? [] as $k => $kw) {
                        $merged[$k] ??= $kw;
                    }
                }
            }
            foreach ($merged as $k => $kw) {
                if (isset($skip[$cid.'|'.$kw['key'].'|'.strtoupper($kw['match'])])) {
                    continue;
                }
                $appByCampaign[$cid][$k] = $kw;
            }
        }

        // ---- KW campaigns (SP) ----
        $liveSp = [];
        $adGroupSp = [];
        try {
            // Only campaigns that have app keywords can be missing something, so only those are read from Amazon
            // (and in parallel, with one access token) - this is what made the check so slow.
            $liveIds = array_values(array_filter($kwIds, static fn (string $id) => ! empty($appByCampaign[$id]) && $picked('KW', $id)));
            foreach ($this->ads->listKeywordsByCampaignIdsConcurrent($liveIds) as $kw) {
                $cid = trim((string) ($kw['campaignId'] ?? ''));
                $text = trim((string) ($kw['keywordText'] ?? ''));
                if ($cid === '' || $text === '') {
                    continue;
                }
                $liveSp[$cid][$this->key($text, (string) ($kw['matchType'] ?? ''))] = true;
                if (! isset($adGroupSp[$cid]) && ! empty($kw['adGroupId'])) {
                    $adGroupSp[$cid] = (string) $kw['adGroupId'];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('KW targeting push: SP keyword list failed', ['error' => $e->getMessage()]);

            return ['rows' => [], 'totals' => $this->totals([]), 'errors' => ['Could not read live KW campaigns from Amazon: '.$e->getMessage()]];
        }

        $byParent = [];
        $upToDate = [];
        $seenKw = [];
        foreach ($kwCampaigns as $c) {
            $cid = $c['campaign_id'];
            $missing = [];
            foreach ($appByCampaign[$cid] ?? [] as $k => $kw) {
                if (! isset($liveSp[$cid][$this->key($kw['text'], $kw['match'])])) {
                    $missing[] = $kw;
                }
            }
            if ($c['parent'] !== '') {
                foreach ($appByCampaign[$cid] ?? [] as $k => $kw) {
                    $byParent[strtoupper($c['parent'])][$k] = $kw;
                }
            }
            if ($missing === [] && $picked('KW', $cid) && ! isset($upToDate[$cid])) {
                // nothing missing: reported so the check can show the campaign as "up to date" instead of dropping it silently
                $upToDate[$cid] = ['campaign_id' => $cid, 'campaign_name' => $c['campaign_name'], 'parent' => $c['parent'],
                    'keywords' => count($appByCampaign[$cid] ?? [])];
            }
            if ($missing === [] || ! $picked('KW', $cid)) {
                continue;
            }
            // Renamed campaigns can share one Amazon campaign id: push / preview it once only.
            if (isset($seenKw[$cid])) {
                continue;
            }
            $seenKw[$cid] = true;

            $row = ['type' => 'KW', 'campaign_id' => $cid, 'campaign_name' => $c['campaign_name'], 'parent' => $c['parent'],
                'to_add' => count($missing), 'added' => 0, 'failed' => 0, 'message' => ''];

            if (! $dryRun) {
                $adGroupId = $adGroupSp[$cid] ?? $this->ads->resolvePrimaryAdGroupId($cid);
                if ($adGroupId === '') {
                    $row['message'] = 'No ad group found.';
                    $row['failed'] = count($missing);
                } else {
                    $bid = (float) config('services.amazon_ads.default_keyword_bid', 1.0);
                    $byMatch = [];
                    $keysByMatch = [];
                    foreach ($missing as $kw) {
                        $byMatch[$kw['match']][] = $kw['text'];
                        $keysByMatch[$kw['match']][] = (string) ($kw['key'] ?? '');
                    }
                    $msgs = [];
                    foreach ($byMatch as $match => $texts) {
                        $r = $this->ads->pushAdGroupPositiveKeywords($cid, $adGroupId, $texts, $match, $bid);
                        $row['added'] += (int) $r['added'];
                        $row['failed'] += (int) $r['failed'];
                        // Everything of this match type landed on Amazon: those stored keywords are Amazon keywords now.
                        if ((int) $r['failed'] === 0 && (int) $r['added'] > 0) {
                            $this->markOnAmazon($cid, (string) $match, $keysByMatch[$match] ?? []);
                        }
                        if ((int) $r['added'] > 0) {
                            $this->logPush($runToken, $cid, (string) $c['campaign_name'], (string) $match, $texts, $keysByMatch[$match] ?? []);
                        }
                        if ($r['failed'] > 0 && $r['errors'] !== []) {
                            $msgs[] = implode(' | ', array_slice($r['errors'], 0, 2));
                        }
                    }
                    $row['message'] = implode(' ', $msgs);
                    if ($row['added'] > 0) {
                        \App\Support\AmazonAdsTargetCounts::increment(\App\Support\AmazonAdsTargetCounts::SP_KEYWORDS, $cid, (int) $row['added']);
                    }
                }
            }
            $rows[] = $row;
            if (! $dryRun) {
                $report('Pushed to KW campaign '.$row['campaign_name']);
            }
        }

        // ---- HL campaigns (SB), one per parent ----
        $hlIds = [];
        foreach ($byParent as $parentKey => $_) {
            if (isset($hlByParent[$parentKey]) && $picked('HL', (string) $hlByParent[$parentKey]['id'])) {
                $hlIds[$hlByParent[$parentKey]['id']] = true;
            }
        }

        $liveSb = [];
        $adGroupSb = [];
        if ($hlIds !== []) {
            try {
                foreach ($this->ads->listSbKeywordsByCampaignIdsConcurrent(array_map('strval', array_keys($hlIds))) as $kw) {
                    $cid = trim((string) ($kw['campaignId'] ?? ''));
                    $text = trim((string) ($kw['keywordText'] ?? ''));
                    if ($cid === '' || $text === '') {
                        continue;
                    }
                    $liveSb[$cid][$this->key($text, (string) ($kw['matchType'] ?? ''))] = true;
                    if (! isset($adGroupSb[$cid]) && ! empty($kw['adGroupId'])) {
                        $adGroupSb[$cid] = (string) $kw['adGroupId'];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('KW targeting push: SB keyword list failed', ['error' => $e->getMessage()]);
                $errors[] = 'Could not read live HL campaigns from Amazon: '.$e->getMessage();
                $hlIds = [];
            }
        }

        // Parents of the picked campaigns that have no HL (HEAD) campaign linked: said so, never silently skipped.
        foreach ($byParent as $parentKey => $_) {
            if (! isset($hlByParent[$parentKey])) {
                $upToDate['NOHL|'.$parentKey] = ['type' => 'HL', 'campaign_id' => '', 'campaign_name' => $parentKey, 'parent' => $parentKey,
                    'keywords' => 0, 'note' => 'no HL campaign linked'];
            }
        }

        // Several parents can be linked to the same HL campaign: merge their keywords into one row per campaign.
        $byHl = [];
        foreach ($byParent as $parentKey => $keywords) {
            $hl = $hlByParent[$parentKey] ?? null;
            if (! $hl || ! isset($hlIds[$hl['id']])) {
                continue;
            }
            $hid = $hl['id'];
            if (! isset($byHl[$hid])) {
                $byHl[$hid] = ['hl' => $hl, 'parents' => [], 'keywords' => []];
            }
            $byHl[$hid]['parents'][] = $parentKey;
            foreach ($keywords as $k => $kw) {
                $byHl[$hid]['keywords'][$k] = $kw;
            }
        }

        foreach ($byHl as $hid => $entry) {
            $hid = (string) $hid;
            $hl = $entry['hl'];
            $keywords = $entry['keywords'];
            $parentKey = implode(', ', $entry['parents']);
            $missing = [];
            foreach ($keywords as $kw) {
                if (! isset($liveSb[$hid][$this->key($kw['text'], $kw['match'])])) {
                    $missing[] = $kw;
                }
            }
            if ($missing === []) {
                // reported (like the KW campaigns) so the check shows the HEAD campaign as up to date instead of ignoring it
                $upToDate['HL|'.$hid] = ['type' => 'HL', 'campaign_id' => $hid, 'campaign_name' => $hl['name'], 'parent' => $parentKey,
                    'keywords' => count($keywords)];

                continue;
            }

            $row = ['type' => 'HL', 'campaign_id' => $hid, 'campaign_name' => $hl['name'], 'parent' => $parentKey,
                'to_add' => count($missing), 'added' => 0, 'failed' => 0, 'message' => ''];

            if (! $dryRun) {
                $adGroupId = $adGroupSb[$hid] ?? $this->sbAdGroupId($hid);
                if ($adGroupId === '') {
                    $row['message'] = 'No HL ad group found.';
                    $row['failed'] = count($missing);
                } else {
                    $bid = round(max(0.1, (float) config('services.amazon_ads.default_keyword_bid', 1.0)), 2);
                    foreach (array_chunk($missing, 100) as $chunk) {
                        $payload = array_map(static fn (array $kw) => [
                            'campaignId' => $hid,
                            'adGroupId' => $adGroupId,
                            'keywordText' => $kw['text'],
                            'matchType' => strtolower($kw['match']),
                            'bid' => $bid,
                        ], $chunk);
                        try {
                            $resp = $this->ads->createSbKeywords($payload);
                            [$ok, $bad, $msg] = $this->parseSbResult($resp, count($chunk));
                            $row['added'] += $ok;
                            $row['failed'] += $bad;
                            if ($msg !== '' && $row['message'] === '') {
                                $row['message'] = $msg;
                            }
                        } catch (\Throwable $e) {
                            $row['failed'] += count($chunk);
                            $row['message'] = $this->ads->amazonErrorMessage($e);
                        }
                    }
                }
            }
            $rows[] = $row;
            if (! $dryRun) {
                $report('Pushed to HL campaign '.$row['campaign_name']);
            }
        }

        return ['rows' => $rows, 'totals' => $this->totals($rows), 'errors' => $errors, 'up_to_date' => array_slice(array_values($upToDate), 0, 500), 'run_token' => $runToken];
    }

    /**
     * Parent (upper-cased family, "PARENT " prefix stripped) => HL campaign linked on the Ads Missing page.
     *
     * @return array<string, array{id: string, name: string}>
     */
    private function hlCampaignsByParent(): array
    {
        if (! Schema::hasTable('amazon_ads_missing_links')) {
            return [];
        }
        $out = [];
        foreach (AmazonAdsMissingLink::query()->where('type', 'HL')->get(['sku', 'campaign_id', 'campaign_name']) as $l) {
            $id = preg_replace('/\D+/', '', (string) $l->campaign_id) ?: '';
            if ($id === '') {
                continue;
            }
            $sku = strtoupper(trim(preg_replace('/\s+/u', ' ', str_replace("\xC2\xA0", ' ', (string) $l->sku))));
            $parent = str_starts_with($sku, 'PARENT ') ? trim(substr($sku, 7)) : $sku;
            if ($parent !== '') {
                $out[$parent] = ['id' => $id, 'name' => trim((string) $l->campaign_name)];
            }
        }

        return $out;
    }

    /**
     * Remember what a push sent (per run and campaign) so the run can be undone.
     *
     * @param  list<string>  $texts
     * @param  list<string>  $keys
     */
    private function logPush(string $runToken, string $campaignId, string $campaignName, string $match, array $texts, array $keys): void
    {
        if ($runToken === '' || ! Schema::hasTable('amazon_kw_push_log')) {
            return;
        }
        try {
            $now = now();
            $by = \Illuminate\Support\Facades\Auth::user()?->name;
            $rows = [];
            foreach ($texts as $i => $text) {
                $rows[] = [
                    'run_token' => $runToken, 'campaign_id' => $campaignId, 'campaign_name' => mb_substr($campaignName, 0, 255),
                    'keyword_text' => mb_substr((string) $text, 0, 512), 'keyword_key' => (string) ($keys[$i] ?? mb_strtolower(trim((string) $text))),
                    'match_type' => $match, 'pushed_at' => $now, 'pushed_by' => $by,
                ];
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('amazon_kw_push_log')->insert($chunk);
            }
        } catch (\Throwable $e) {
            Log::info('KW targeting push: could not log the pushed keywords', ['error' => $e->getMessage()]);
        }
    }

    /** Stored keywords that were copied from a cousin and have now been pushed count as Amazon keywords. */
    private function markOnAmazon(string $campaignId, string $match, array $keys): void
    {
        $keys = array_values(array_filter(array_unique($keys), static fn (string $k) => $k !== ''));
        try {
            foreach (array_chunk($keys, 500) as $chunk) {
                DB::table(self::STORE_TABLE)->where('campaign_id', $campaignId)->where('match_type', $match)
                    ->whereIn('keyword_key', $chunk)->where('source', 'cousin')->update(['source' => 'amazon']);
            }
        } catch (\Throwable $e) {
            Log::info('KW targeting push: could not mark pushed keywords', ['error' => $e->getMessage()]);
        }
    }

    private function sbAdGroupId(string $campaignId): string
    {
        try {
            $resp = $this->ads->listSbAdsPage([$campaignId]);
            foreach ((array) ($resp['ads'] ?? []) as $ad) {
                if (! empty($ad['adGroupId'])) {
                    return (string) $ad['adGroupId'];
                }
            }
        } catch (\Throwable $e) {
            Log::info('KW targeting push: SB ad group lookup failed', ['error' => $e->getMessage()]);
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $resp
     * @return array{0: int, 1: int, 2: string}
     */
    private function parseSbResult(array $resp, int $sent): array
    {
        $items = array_is_list($resp) ? $resp : (array) ($resp['keywords'] ?? []);
        if ($items === []) {
            return [$sent, 0, ''];
        }
        $ok = 0;
        $bad = 0;
        $msg = '';
        foreach ($items as $it) {
            if (! is_array($it)) {
                continue;
            }
            $code = strtoupper((string) ($it['code'] ?? ''));
            if (isset($it['keywordId']) && ($code === '' || $code === 'SUCCESS')) {
                $ok++;
            } else {
                $detail = (string) ($it['details'] ?? $it['description'] ?? $code);
                $blob = strtolower((string) json_encode($it));
                if (str_contains($blob, 'duplicate') || str_contains($blob, 'already exists')) {
                    continue; // already on the campaign
                }
                $bad++;
                $msg = $msg !== '' ? $msg : $detail;
            }
        }

        return [$ok, $bad, $msg];
    }

    private function key(string $text, string $match): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($text))).'|'.strtoupper(trim($match));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function totals(array $rows): array
    {
        $t = ['kw_campaigns' => 0, 'hl_campaigns' => 0, 'kw_to_add' => 0, 'hl_to_add' => 0, 'added' => 0, 'failed' => 0];
        foreach ($rows as $r) {
            $isKw = $r['type'] === 'KW';
            $t[$isKw ? 'kw_campaigns' : 'hl_campaigns']++;
            $t[$isKw ? 'kw_to_add' : 'hl_to_add'] += (int) $r['to_add'];
            $t['added'] += (int) $r['added'];
            $t['failed'] += (int) $r['failed'];
        }

        return $t;
    }
}
