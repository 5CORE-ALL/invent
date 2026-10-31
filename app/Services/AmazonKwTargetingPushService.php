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
    public function run(array $kwCampaigns, bool $dryRun, ?callable $progress = null): array
    {
        $errors = [];
        $rows = [];
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
        $kwIds = array_map(static fn (array $c) => $c['campaign_id'], $kwCampaigns);

        // App keywords per KW campaign, unique by text + match type.
        $appByCampaign = [];
        foreach (array_chunk($kwIds, 1000) as $chunk) {
            $q = DB::table(self::STORE_TABLE)->whereIn('campaign_id', $chunk)->whereIn('match_type', self::MATCH_TYPES)
                ->get(['campaign_id', 'keyword', 'keyword_key', 'match_type']);
            // Targets ignored (or deleted) in the targets modal are not pushed.
            $skip = [];
            if (Schema::hasTable('amazon_kw_targeting_flags')) {
                foreach (DB::table('amazon_kw_targeting_flags')->where('kind', 'kw')->whereIn('campaign_id', $chunk)
                    ->get(['campaign_id', 'keyword_key', 'match_type']) as $f) {
                    $skip[$f->campaign_id.'|'.$f->keyword_key.'|'.strtoupper((string) $f->match_type)] = true;
                }
            }
            foreach ($q as $r) {
                if (isset($skip[$r->campaign_id.'|'.$r->keyword_key.'|'.strtoupper((string) $r->match_type)])) {
                    continue;
                }
                $appByCampaign[(string) $r->campaign_id][$r->keyword_key.'|'.$r->match_type] = [
                    'text' => (string) $r->keyword,
                    'match' => (string) $r->match_type,
                ];
            }
        }

        // ---- KW campaigns (SP) ----
        $liveSp = [];
        $adGroupSp = [];
        try {
            // Only campaigns that have app keywords can be missing something, so only those are read from Amazon
            // (and in parallel, with one access token) - this is what made the check so slow.
            $liveIds = array_values(array_filter($kwIds, static fn (string $id) => ! empty($appByCampaign[$id])));
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
            if ($missing === []) {
                continue;
            }

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
                    foreach ($missing as $kw) {
                        $byMatch[$kw['match']][] = $kw['text'];
                    }
                    $msgs = [];
                    foreach ($byMatch as $match => $texts) {
                        $r = $this->ads->pushAdGroupPositiveKeywords($cid, $adGroupId, $texts, $match, $bid);
                        $row['added'] += (int) $r['added'];
                        $row['failed'] += (int) $r['failed'];
                        if ($r['failed'] > 0 && $r['errors'] !== []) {
                            $msgs[] = implode(' | ', array_slice($r['errors'], 0, 2));
                        }
                    }
                    $row['message'] = implode(' ', $msgs);
                }
            }
            $rows[] = $row;
            if (! $dryRun) {
                $report('Pushed to KW campaign '.$row['campaign_name']);
            }
        }

        // ---- HL campaigns (SB), one per parent ----
        $hlByParent = $this->hlCampaignsByParent();
        $hlIds = [];
        foreach ($byParent as $parentKey => $_) {
            if (isset($hlByParent[$parentKey])) {
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

        foreach ($byParent as $parentKey => $keywords) {
            $hl = $hlByParent[$parentKey] ?? null;
            if (! $hl || ! isset($hlIds[$hl['id']])) {
                continue;
            }
            $hid = $hl['id'];
            $missing = [];
            foreach ($keywords as $kw) {
                if (! isset($liveSb[$hid][$this->key($kw['text'], $kw['match'])])) {
                    $missing[] = $kw;
                }
            }
            if ($missing === []) {
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
                            'state' => 'enabled',
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

        return ['rows' => $rows, 'totals' => $this->totals($rows), 'errors' => $errors];
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
                if (stripos($detail, 'duplicate') !== false) {
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
