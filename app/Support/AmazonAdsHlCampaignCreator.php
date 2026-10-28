<?php

namespace App\Support;

use App\Services\AmazonAdsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Create one PAUSED Sponsored Brands (HL / Headline) campaign for a parent.
 *
 * Brand assets (brand entity, logo, headline, landing page) cannot be invented, so they are cloned from an
 * existing HL campaign of the same account (the "template"). Only the name, budget and ASINs change.
 * Every step is PAUSED. A failure after the campaign exists is reported with the campaign id so the caller
 * can still link it.
 */
final class AmazonAdsHlCampaignCreator
{
    public function __construct(private AmazonAdsService $ads) {}

    /**
     * @param  list<string>  $asins
     * @param  list<array{text: string, match_type: string, bid: float}>  $keywords
     * @param  list<string>  $templateCandidateIds  SB campaign ids to try as template, best first
     * @return array{ok: bool, message: string, campaign_id: string, ad_group_id: string, ad_id: string, steps: list<string>}
     */
    public function create(string $name, float $dailyBudget, array $asins, array $keywords, array $templateCandidateIds, ?string $headline = null): array
    {
        $out = ['ok' => false, 'message' => '', 'campaign_id' => '', 'ad_group_id' => '', 'ad_id' => '', 'steps' => []];

        $asins = AmazonAdsSbAdEditor::mergeAsins([], $asins);
        if ($asins === []) {
            $out['message'] = 'Select at least one child SKU with a valid ASIN.';

            return $out;
        }

        $tpl = $this->loadTemplate($templateCandidateIds);
        if ($tpl === null) {
            $out['message'] = 'No existing HL (Sponsored Brands) campaign with a creative was found to copy the brand logo / headline from. '
                .'Create the first HL campaign in Amazon Ads, link it here, then use CT again.';

            return $out;
        }
        $out['steps'][] = 'Template: campaign '.$tpl['campaign_id'];

        // 1) Campaign (cloned settings, PAUSED).
        $campaign = [
            'name' => $name,
            'state' => 'PAUSED',
            'budget' => round(max(1.0, $dailyBudget), 2),
            'budgetType' => 'DAILY',
            'startDate' => now()->format('Y-m-d'),
        ];
        foreach (['brandEntityId', 'costType', 'goal', 'productLocation', 'bidding', 'isMultiAdGroupsEnabled'] as $k) {
            if (isset($tpl['campaign'][$k]) && $tpl['campaign'][$k] !== '' && $tpl['campaign'][$k] !== []) {
                $campaign[$k] = $tpl['campaign'][$k];
            }
        }
        try {
            $resp = $this->ads->createSbCampaigns([$campaign]);
        } catch (\Throwable $e) {
            $out['message'] = 'Amazon campaign create failed: '.$this->ads->amazonErrorMessage($e);

            return $out;
        }
        $campaignId = $this->firstId($resp, 'campaigns', 'campaignId');
        if ($campaignId === '') {
            $out['message'] = 'Amazon rejected the campaign: '.($this->firstError($resp, 'campaigns') ?: 'no campaign id returned.');

            return $out;
        }
        $out['campaign_id'] = $campaignId;
        $out['steps'][] = 'Campaign '.$campaignId.' created (PAUSED)';

        // 2) Ad group.
        try {
            $resp = $this->ads->createSbAdGroups([['campaignId' => $campaignId, 'name' => $name]]);
            $adGroupId = $this->firstId($resp, 'adGroups', 'adGroupId');
        } catch (\Throwable $e) {
            $out['message'] = 'Campaign created, but the ad group failed: '.$this->ads->amazonErrorMessage($e);

            return $out;
        }
        if ($adGroupId === '') {
            $out['message'] = 'Campaign created, but Amazon rejected the ad group: '.($this->firstError($resp, 'adGroups') ?: 'no ad group id returned.');

            return $out;
        }
        $out['ad_group_id'] = $adGroupId;
        $out['steps'][] = 'Ad group '.$adGroupId.' created';

        // 3) Ad (creative cloned from the template, ASINs replaced).
        $creative = AmazonAdsSbAdEditor::copyCreativeForAsins($tpl['creative'], $tpl['ad'], $asins);
        if (isset($creative['brandLogoAssetId'])) {
            $creative['brandLogoAssetID'] = $creative['brandLogoAssetID'] ?? $creative['brandLogoAssetId'];
            unset($creative['brandLogoAssetId']);
        }
        if ($headline !== null && trim($headline) !== '') {
            $creative['headline'] = trim($headline);
        }
        $landing = is_array($tpl['ad']['landingPage'] ?? null) ? $tpl['ad']['landingPage'] : [];
        $pageType = strtoupper((string) ($landing['pageType'] ?? ''));
        if ($pageType === '' || $pageType === 'PRODUCT_LIST' || $pageType === 'DETAIL_PAGE') {
            $landing = ['pageType' => 'PRODUCT_LIST', 'asins' => $asins];
        }
        $ad = [
            'adGroupId' => $adGroupId,
            'name' => $name,
            'state' => 'PAUSED',
            'creative' => $creative,
            'landingPage' => $landing,
        ];
        try {
            $resp = $this->ads->createSbAds([$ad]);
            $adId = $this->firstId($resp, 'ads', 'adId');
        } catch (\Throwable $e) {
            $out['message'] = 'Campaign + ad group created, but the ad (creative) failed: '.$this->ads->amazonErrorMessage($e);

            return $out;
        }
        if ($adId === '') {
            $out['message'] = 'Campaign + ad group created, but Amazon rejected the ad: '.($this->firstError($resp, 'ads') ?: 'no ad id returned.');

            return $out;
        }
        $out['ad_id'] = $adId;
        $out['steps'][] = 'Ad '.$adId.' created (PAUSED)';

        // 4) Keywords (optional).
        $kwErr = '';
        if ($keywords !== []) {
            $rows = [];
            foreach ($keywords as $k) {
                $text = trim((string) ($k['text'] ?? ''));
                if ($text === '') {
                    continue;
                }
                $match = strtolower((string) ($k['match_type'] ?? 'phrase'));
                $rows[] = [
                    'campaignId' => $campaignId,
                    'adGroupId' => $adGroupId,
                    'keywordText' => $text,
                    'matchType' => in_array($match, ['broad', 'phrase', 'exact'], true) ? $match : 'phrase',
                    'bid' => round(max(0.1, (float) ($k['bid'] ?? 0.6)), 2),
                    'state' => 'enabled',
                ];
            }
            if ($rows !== []) {
                try {
                    $this->ads->createSbKeywords($rows);
                    $out['steps'][] = count($rows).' keyword(s) sent';
                } catch (\Throwable $e) {
                    $kwErr = ' Keywords failed: '.$this->ads->amazonErrorMessage($e);
                }
            }
        }

        $this->persistAd($campaignId, $adGroupId, $adId, $name, $asins);

        $out['ok'] = true;
        $out['message'] = 'HL campaign created (PAUSED): '.$name.'.'.$kwErr;

        return $out;
    }

    /**
     * Candidate SB campaign ids to copy the brand creative from: the given ids first, then recent SB report campaigns.
     *
     * @param  list<string>  $preferred
     * @return list<string>
     */
    public static function templateCandidates(array $preferred): array
    {
        $ids = [];
        foreach ($preferred as $id) {
            $id = preg_replace('/\D+/', '', (string) $id) ?: '';
            if ($id !== '') {
                $ids[$id] = true;
            }
        }
        if (Schema::hasTable('amazon_sb_campaign_reports')) {
            $rows = DB::table('amazon_sb_campaign_reports')
                ->whereNotNull('campaign_id')
                ->where('campaign_id', '!=', '')
                ->orderByDesc('id')
                ->limit(300)
                ->pluck('campaign_id');
            foreach ($rows as $id) {
                $id = preg_replace('/\D+/', '', (string) $id) ?: '';
                if ($id !== '') {
                    $ids[$id] = true;
                }
                if (count($ids) >= 12) {
                    break;
                }
            }
        }

        return array_slice(array_keys($ids), 0, 12);
    }

    /**
     * @param  list<string>  $candidateIds
     * @return array{campaign_id: string, campaign: array<string, mixed>, ad: array<string, mixed>, creative: array<string, mixed>}|null
     */
    private function loadTemplate(array $candidateIds): ?array
    {
        foreach ($candidateIds as $cid) {
            try {
                $listed = $this->ads->fetchAllSbAds(['ENABLED', 'PAUSED'], [$cid]);
                $ads = array_values(array_filter((array) ($listed['ads'] ?? []), 'is_array'));
                if (empty($listed['success']) || $ads === []) {
                    continue;
                }
                $ad = $ads[0];
                foreach ($ads as $cand) {
                    if (strtoupper((string) ($cand['state'] ?? '')) === 'ENABLED') {
                        $ad = $cand;
                        break;
                    }
                }
                $adId = preg_replace('/\D+/', '', (string) ($ad['adId'] ?? '')) ?: '';
                $creativeRow = [];
                if ($adId !== '') {
                    $resp = $this->ads->listSbAdCreatives($adId);
                    foreach ((array) ($resp['creatives'] ?? []) as $row) {
                        if (is_array($row)) {
                            $creativeRow = $row;
                            break;
                        }
                    }
                }
                $props = AmazonAdsSbAdEditor::creativeProperties($creativeRow);
                if ($props === []) {
                    $props = is_array($ad['creative'] ?? null) ? $ad['creative'] : [];
                }
                $logo = $props['brandLogoAssetId'] ?? $props['brandLogoAssetID'] ?? '';
                if (trim((string) ($props['brandName'] ?? '')) === '' || trim((string) $logo) === '') {
                    continue;
                }
                $camps = $this->ads->listSbCampaignsByIds([$cid]);
                $camp = is_array($camps[0] ?? null) ? $camps[0] : [];
                if (trim((string) ($camp['brandEntityId'] ?? '')) === '') {
                    continue;
                }

                return ['campaign_id' => $cid, 'campaign' => $camp, 'ad' => $ad, 'creative' => $creativeRow];
            } catch (\Throwable $e) {
                Log::warning('HL template load failed', ['campaign_id' => $cid, 'error' => $e->getMessage()]);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $resp
     */
    private function firstId(array $resp, string $key, string $idField): string
    {
        $success = data_get($resp, $key.'.success');
        if (is_array($success)) {
            foreach ($success as $row) {
                $id = is_array($row) ? (data_get($row, $idField) ?? data_get($row, substr($key, 0, -1).'.'.$idField)) : null;
                $id = preg_replace('/\D+/', '', (string) $id) ?: '';
                if ($id !== '') {
                    return $id;
                }
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $resp
     */
    private function firstError(array $resp, string $key): string
    {
        $errors = data_get($resp, $key.'.error');
        if (is_array($errors)) {
            foreach ($errors as $row) {
                $msg = trim((string) (data_get($row, 'errors.0.errorValue.message')
                    ?? data_get($row, 'errors.0.message')
                    ?? data_get($row, 'errorValue.message')
                    ?? data_get($row, 'message')
                    ?? ''));
                if ($msg !== '') {
                    return $msg;
                }
            }
        }

        return '';
    }

    /**
     * @param  list<string>  $asins
     */
    private function persistAd(string $campaignId, string $adGroupId, string $adId, string $name, array $asins): void
    {
        try {
            AmazonAdsCampaignSkuSync::persistSbAds($this->ads->resolvedProfileId() ?: 'default', [[
                'adId' => $adId,
                'campaignId' => $campaignId,
                'adGroupId' => $adGroupId,
                'state' => 'PAUSED',
                'name' => $name,
                'creative' => ['asins' => $asins],
            ]]);
        } catch (\Throwable $e) {
            Log::warning('HL created ad persist failed', ['error' => $e->getMessage()]);
        }
    }
}
