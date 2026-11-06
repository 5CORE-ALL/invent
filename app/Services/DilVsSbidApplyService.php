<?php

namespace App\Services;

use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\EbayMetric;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Support\CpMasterDil;
use App\Support\DilVsSbidListingRollup;
use App\Support\DilVsSbidRule;
use App\Support\EbayBidPercentage;
use App\Support\EbayCampaignAdLiveBid;
use App\Support\EbayGpftLookup;
use App\Support\EbayMarketingPushRetry;
use App\Support\EbayStdNpftLookup;
use App\Support\PmtCategoryBidRule;
use App\Support\Marketplace\EbayCampaignEndedListingRemap;
use App\Support\Marketplace\EbayListingEnded;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Push Dil vs SBid for one eBay account: ES Bid or the dynamic % as the
 * promoted-listing bid. When the switch is off, nothing is pushed.
 * A mismatch pulls live C Bid, pushes S Bid, and pulls again until they
 * match or the verify rounds run out.
 */
class DilVsSbidApplyService
{
    /** Ads Rule PMT: per eBay account, its ads table, metrics model, API service and Dil rule key. */
    private const PMT_ACCOUNTS = [
        'ebay1' => ['ads' => 'ebay_campaign_ads', 'metric' => EbayMetric::class, 'api' => EbayApiService::class, 'rule' => DilVsSbidRule::KEY_EBAY1],
        'ebay2' => ['ads' => 'ebay2_campaign_ads', 'metric' => Ebay2Metric::class, 'api' => Ebay2ApiService::class, 'rule' => DilVsSbidRule::KEY_EBAY2],
        'ebay3' => ['ads' => 'ebay3_campaign_ads', 'metric' => Ebay3Metric::class, 'api' => EbayThreeApiService::class, 'rule' => DilVsSbidRule::KEY_EBAY3],
    ];

    public const VERIFY_ROUNDS = 5;

    /** @var list<int> */
    public const VERIFY_DELAYS_MS = [500, 1000, 2000, 4000, 8000];

    /** @var callable(int): void|null */
    public $sleeper = null;

    /** @var class-string|null Metrics model of the account being pushed (for relist lookups). */
    private ?string $metricClass = null;

    /** Rule key of the run in progress (used to re-check the On / Off switch mid-run). */
    private ?string $ruleKey = null;

    /** Listings left unpushed because the switch was turned Off during the run. */
    private int $switchOffSkipped = 0;

    private bool $switchedOffMidRun = false;

    /** @var (callable(): bool)|null Live On / Off check used instead of the Dil vs SBid switch (Ads Rule PMT). */
    private $switchCheck = null;

    /** True once the switch is Off: no more bids or pauses are sent for the rest of the run. */
    private function switchIsOff(): bool
    {
        if ($this->switchedOffMidRun) {
            return true;
        }
        if ($this->switchCheck !== null) {
            if (! ($this->switchCheck)()) {
                $this->switchedOffMidRun = true;
            }
        } elseif ($this->ruleKey !== null && ! DilVsSbidRule::isOn($this->ruleKey)) {
            $this->switchedOffMidRun = true;
        }

        return $this->switchedOffMidRun;
    }

    /**
     * @param  class-string  $metricClass
     * @param  class-string  $apiServiceClass
     * @param  array<int, mixed>  $listingIds
     * @return array{success:int,failed:int,skipped:int,unchanged?:int,results:array<int,array<string,mixed>>,error?:string}
     */
    public function apply(string $ruleKey, string $adsTable, string $metricClass, string $apiServiceClass, array $listingIds, bool $onlyChanged = false, ?callable $decider = null, ?callable $switchOn = null): array
    {
        $listingIds = array_values(array_unique(array_map('strval', $listingIds)));
        if ($listingIds === []) {
            return ['success' => 0, 'failed' => 0, 'skipped' => 0, 'results' => [], 'error' => 'No listings to apply'];
        }

        $this->metricClass = $metricClass;
        $this->ruleKey = $ruleKey;
        $this->switchOffSkipped = 0;
        $this->switchedOffMidRun = false;
        $this->switchCheck = $switchOn;
        $custom = $decider !== null; // Ads Rule PMT supplies its own decision per listing
        $stored = DilVsSbidRule::load($ruleKey);
        $useDil = $custom || ! empty($stored['enabled']);
        $slabs = $stored['slabs'];
        $cvr = $stored['cvr'];
        $tables = $stored['tables'];
        $cap = $stored['cap'] ?? DilVsSbidRule::defaultCap();
        $viewsOver = $stored['views_over'] ?? DilVsSbidRule::defaultViewOver();
        $metricsByListing = $metricClass::whereIn('item_id', $listingIds)->get()->groupBy(fn ($m) => (string) $m->item_id);
        $ads = DB::table($adsTable)
            ->whereIn('listing_id', $listingIds)
            ->whereNotNull('campaign_id')
            ->where('campaign_id', '!=', '')
            ->where('funding_strategy', 'COST_PER_SALE')
            ->get()
            ->groupBy(fn ($ad) => (string) $ad->listing_id);

        $skus = $this->skusForListings($listingIds, $metricsByListing, $ads);
        $shopifyMap = $this->shopifyBySku($skus);
        $npftMap = (! $custom && $useDil && DilVsSbidRule::usesNpft($tables))
            ? EbayStdNpftLookup::forSkus($skus)
            : [];
        $gpftMap = (! $custom && $useDil && DilVsSbidRule::usesGpft($tables))
            ? $this->gpftBySku($metricsByListing)
            : [];

        $service = new $apiServiceClass();
        $http = new EbayMarketingPushRetry($service);
        try {
            $http->acquireToken();
        } catch (\Exception $e) {
            return ['success' => 0, 'failed' => 0, 'skipped' => 0, 'results' => [], 'error' => 'Token error: '.$e->getMessage()];
        }

        $results = [];
        $success = 0;
        $failed = 0;
        $skipped = 0;
        $unchanged = 0;
        $bidsByCampaign = [];
        $offsByCampaign = [];

        foreach ($listingIds as $lid) {
            $campaignAds = $ads->get($lid);
            if ($campaignAds === null || $campaignAds->isEmpty()) {
                $results[] = ['listing_id' => $lid, 'status' => 'skipped', 'reason' => 'Not in a COST_PER_SALE campaign'];
                $skipped++;
                continue;
            }

            foreach ($campaignAds as $ad) {
                if (! $ad->campaign_id) {
                    $results[] = ['listing_id' => $lid, 'status' => 'skipped', 'reason' => 'Not in a COST_PER_SALE campaign'];
                    $skipped++;
                    continue;
                }

                $rolled = $this->rolledListingInputs($lid, $ad, $metricsByListing, $shopifyMap, $npftMap, $gpftMap);
                $wanted = $custom ? $decider((string) $lid, $ad, $rolled) : $this->listingDecision(
                    $useDil,
                    $ad,
                    $rolled['metric'],
                    $rolled['shopify'],
                    $slabs,
                    $cvr,
                    $tables,
                    $cap,
                    $viewsOver,
                    $rolled['npftMap'],
                    DilVsSbidRule::usesEsBidCeiling($ruleKey),
                    $rolled['gpftMap']
                );

                if ($wanted['skip'] !== null) {
                    $results[] = ['listing_id' => $lid, 'status' => 'skipped', 'reason' => $wanted['skip']];
                    $skipped++;
                    continue;
                }

                if ($wanted['off']) {
                    if (empty($ad->ad_id)) {
                        $results[] = ['listing_id' => $lid, 'status' => 'skipped', 'reason' => 'Pause but no ad id'];
                        $skipped++;
                        continue;
                    }
                    $offsByCampaign[(string) $ad->campaign_id][] = [
                        'listingId' => $lid,
                        'adId' => (string) $ad->ad_id,
                    ];
                    continue;
                }

                $bidsByCampaign[(string) $ad->campaign_id][] = [
                    'listingId' => $lid,
                    'adId' => $ad->ad_id ? (string) $ad->ad_id : null,
                    'bidPercentage' => $wanted['formatted'],
                    'sku' => trim((string) (($ad->sku ?? '') !== '' ? $ad->sku : ($rolled['metric']->sku ?? ''))),
                ];
            }
        }

        $this->pushUntilMatched(
            $http,
            $adsTable,
            $bidsByCampaign,
            $results,
            $success,
            $failed,
            $unchanged,
            ! $onlyChanged
        );

        foreach ($offsByCampaign as $campaignId => $requests) {
            foreach (array_chunk($requests, 200) as $chunk) {
                if ($this->switchIsOff()) {
                    foreach ($chunk as $r) {
                        $results[] = ['listing_id' => $r['listingId'], 'status' => 'skipped', 'reason' => 'Dil vs SBid switched off'];
                        $this->switchOffSkipped++;
                    }

                    continue;
                }
                $this->pauseAds($http, $adsTable, $campaignId, $chunk, $results, $success, $failed);
            }
        }

        return [
            'success' => $success,
            'failed' => $failed,
            'skipped' => $skipped + $this->switchOffSkipped,
            'unchanged' => $unchanged,
            'results' => $results,
            'stopped' => $this->switchedOffMidRun,
        ];
    }

    /**
     * Pull live C Bid for RUNNING ads, then push Dil vs SBid until live
     * matches. Ads that already match are left alone.
     *
     * @param  class-string  $metricClass
     * @param  class-string  $apiServiceClass
     * @return array{success:int,failed:int,skipped:int,unchanged?:int,results:array<int,array<string,mixed>>,error?:string}
     */
    public function applyChanged(string $ruleKey, string $adsTable, string $metricClass, string $apiServiceClass, bool $onlyLocalMismatch = false): array
    {
        $stored = DilVsSbidRule::load($ruleKey);
        if (empty($stored['enabled'])) {
            return ['success' => 0, 'failed' => 0, 'skipped' => 0, 'unchanged' => 0, 'results' => [], 'error' => 'Dil vs SBid is off'];
        }
        if ($ruleKey === DilVsSbidRule::KEY_EBAY1 && PmtCategoryBidRule::isOn()) {
            // Ads Rule PMT owns the eBay 1 bids while it is on; two rules would keep overwriting each other.
            return ['success' => 0, 'failed' => 0, 'skipped' => 0, 'unchanged' => 0, 'results' => [], 'error' => 'Ads Rule PMT is on'];
        }

        $listingIds = DB::table($adsTable)
            ->whereNotNull('campaign_id')
            ->where('campaign_id', '!=', '')
            ->where('funding_strategy', 'COST_PER_SALE')
            ->whereRaw("UPPER(TRIM(COALESCE(campaign_status, ''))) = 'RUNNING'")
            ->pluck('listing_id')
            ->all();

        if ($onlyLocalMismatch) {
            $listingIds = $this->filterLocalMismatches($ruleKey, $adsTable, $metricClass, $listingIds);
            if ($listingIds === []) {
                return ['success' => 0, 'failed' => 0, 'skipped' => 0, 'unchanged' => 0, 'results' => []];
            }
        }

        return $this->apply($ruleKey, $adsTable, $metricClass, $apiServiceClass, $listingIds, true);
    }

    /**
     * Ads Rule PMT (eBay 1): push the per-category rule bid, or pause the ad (INV 0), for every RUNNING
     * COST_PER_SALE ad. With $onlyLocalMismatch only ads whose stored C Bid differs are touched.
     *
     * @return array{success:int,failed:int,skipped:int,unchanged?:int,results:array<int,array<string,mixed>>,error?:string,stopped?:bool}
     */
    public function applyPmtChanged(string $account = 'ebay1', bool $onlyLocalMismatch = false, array $onlyListingIds = []): array
    {
        $cfg = self::PMT_ACCOUNTS[$account] ?? null;
        if ($cfg === null) {
            return ['success' => 0, 'failed' => 0, 'skipped' => 0, 'unchanged' => 0, 'results' => [], 'error' => 'Unknown eBay account'];
        }
        if (! PmtCategoryBidRule::isOn($account)) {
            return ['success' => 0, 'failed' => 0, 'skipped' => 0, 'unchanged' => 0, 'results' => [], 'error' => 'Ads Rule PMT is off'];
        }

        $adsTable = $cfg['ads'];
        $listingIds = DB::table($adsTable)
            ->whereNotNull('campaign_id')
            ->where('campaign_id', '!=', '')
            ->where('funding_strategy', 'COST_PER_SALE')
            ->whereRaw("UPPER(TRIM(COALESCE(campaign_status, ''))) = 'RUNNING'")
            ->pluck('listing_id')
            ->all();

        if ($onlyListingIds !== []) { // a manual run for specific listings (ebay:pmt-check --push)
            $only = array_map('strval', $onlyListingIds);
            $listingIds = array_values(array_filter($listingIds, fn ($id) => in_array((string) $id, $only, true)));
            if ($listingIds === []) {
                return ['success' => 0, 'failed' => 0, 'skipped' => 0, 'unchanged' => 0, 'results' => [], 'error' => 'Listing is not in a RUNNING COST_PER_SALE campaign'];
            }
        }

        $bids = PmtCategoryBidRule::bids($account);
        $categories = app(ExcelCategoryLookupService::class)->map($account);
        // eBay 2: the rule is decided once per PARENT (INV / OV L30 / eBay sold of the whole family),
        // and every ad listing of that parent gets the same bid. Other accounts decide per listing.
        $families = $account === 'ebay2'
            ? $this->pmtParentFamilies($listingIds, $cfg['metric'], $categories)
            : [];
        $decider = fn (string $lid, object $ad, array $rolled): array => isset($families[(string) $lid])
            ? $this->pmtDecisionFromInputs($families[(string) $lid], $bids)
            : $this->pmtDecision($lid, $rolled, $bids, $categories);

        if ($onlyLocalMismatch) {
            $listingIds = $this->pmtLocalMismatches($listingIds, $adsTable, $cfg['metric'], $decider);
            if ($listingIds === []) {
                return ['success' => 0, 'failed' => 0, 'skipped' => 0, 'unchanged' => 0, 'results' => []];
            }
        }

        return $this->apply(
            $cfg['rule'],
            $adsTable,
            $cfg['metric'],
            $cfg['api'],
            $listingIds,
            true,
            $decider,
            fn (): bool => PmtCategoryBidRule::isOn($account)
        );
    }

    /**
     * Read-only: what Ads Rule PMT wants for one listing, what is stored locally and what eBay
     * says is live right now. Pushes nothing.
     *
     * @return array<string, mixed>
     */
    public function explainPmt(string $account, string $listingId): array
    {
        $cfg = self::PMT_ACCOUNTS[$account] ?? null;
        if ($cfg === null) {
            return ['error' => 'Unknown eBay account'];
        }
        $listingId = trim($listingId);
        $bids = PmtCategoryBidRule::bids($account);
        $categories = app(ExcelCategoryLookupService::class)->map($account);

        $ads = DB::table($cfg['ads'])->where('listing_id', $listingId)->get();
        $out = [
            'account' => $account,
            'listing_id' => $listingId,
            'switch_on' => PmtCategoryBidRule::isOn($account),
            'ads_rows' => $ads->map(fn ($a) => [
                'campaign_id' => $a->campaign_id ?? null,
                'ad_id' => $a->ad_id ?? null,
                'funding' => $a->funding_strategy ?? null,
                'status' => $a->campaign_status ?? null,
                'stored_c_bid' => $a->bid_percentage ?? null,
                'updated_at' => (string) ($a->updated_at ?? ''),
            ])->all(),
        ];

        // What the rule wants.
        if ($account === 'ebay2') {
            $in = $this->pmtParentFamilies([$listingId], $cfg['metric'], $categories)[$listingId] ?? null;
            $out['level'] = 'parent family';
        } else {
            $metrics = $cfg['metric']::where('item_id', $listingId)->get()->groupBy(fn ($m) => (string) $m->item_id);
            $first = $ads->first() ?? (object) [];
            $shopify = $this->shopifyBySku($this->skusForListings([$listingId], $metrics, $ads->groupBy(fn ($a) => (string) $a->listing_id)));
            $rolled = $this->rolledListingInputs($listingId, $first, $metrics, $shopify, [], []);
            $in = [
                'inv' => $rolled['shopify']->inv,
                'ovL30' => $rolled['shopify']->quantity,
                'sold' => $rolled['metric']->ebay_l30,
                'category' => $categories[ExcelCategoryLookupService::normalizeItemId($listingId)] ?? '',
            ];
            $out['level'] = 'listing';
        }
        if ($in === null) {
            $out['wanted'] = ['skip' => 'listing not found in metrics', 'off' => false, 'formatted' => null, 'label' => ''];

            return $out;
        }
        $out['inputs'] = $in + ['dil_pct' => ((float) ($in['inv'] ?? 0)) > 0 ? round(((float) $in['ovL30'] / (float) $in['inv']) * 100, 1) : 0];
        $out['category_range'] = $bids[$in['category']] ?? null;
        $out['wanted'] = $this->pmtDecisionFromInputs($in, $bids);

        // What eBay says is live.
        $out['live'] = [];
        try {
            $http = new EbayMarketingPushRetry(new $cfg['api']());
            $http->acquireToken();
            foreach ($ads as $ad) {
                if (empty($ad->campaign_id)) {
                    continue;
                }
                $res = $http->get('https://api.ebay.com/sell/marketing/v1/ad_campaign/'.$ad->campaign_id.'/ad', ['listing_ids' => $listingId]);
                $body = ($res['ok'] && $res['response'] !== null) ? $res['response']->json() : null;
                $found = is_array($body) ? ($body['ads'] ?? []) : [];
                $out['live'][] = [
                    'campaign_id' => (string) $ad->campaign_id,
                    'ok' => $res['ok'],
                    'error' => $res['error'],
                    'ads' => array_map(fn ($x) => [
                        'adId' => $x['adId'] ?? null,
                        'listingId' => $x['listingId'] ?? null,
                        'status' => $x['adStatus'] ?? null,
                        'bidPercentage' => $x['bidPercentage'] ?? null,
                    ], is_array($found) ? $found : []),
                ];
            }
        } catch (\Throwable $e) {
            $out['live_error'] = $e->getMessage();
        }

        return $out;
    }

    /**
     * Ads Rule PMT decision for one listing (family INV / OV L30 / eBay sold already rolled up).
     *
     * @param  array{metric:object,shopify:object}  $rolled
     * @param  array<string, array{highest:?float,lowest:?float}>  $bids
     * @param  array<string, string>  $categories
     * @return array{skip:?string, off:bool, formatted:?string, label:string}
     */
    private function pmtDecision(string $listingId, array $rolled, array $bids, array $categories): array
    {
        return $this->pmtDecisionFromInputs([
            'inv' => $rolled['shopify']->inv ?? null,
            'ovL30' => $rolled['shopify']->quantity ?? 0,
            'sold' => $rolled['metric']->ebay_l30 ?? 0,
            'category' => $categories[ExcelCategoryLookupService::normalizeItemId($listingId)] ?? '',
        ], $bids);
    }

    /**
     * Ads Rule PMT decision from already rolled-up numbers (one listing, or one whole parent family).
     *
     * @param  array{inv:mixed,ovL30:mixed,sold:mixed,category:string}  $in
     * @param  array<string, array{highest:?float,lowest:?float}>  $bids
     * @return array{skip:?string, off:bool, formatted:?string, label:string}
     */
    private function pmtDecisionFromInputs(array $in, array $bids): array
    {
        $inv = $in['inv'] ?? null;
        if ($inv === null || ! is_numeric($inv)) {
            // No Shopify match is not the same as INV 0 — never pause on missing data.
            return ['skip' => 'PMT rule: no inventory data', 'off' => false, 'formatted' => null, 'label' => ''];
        }
        $inv = (float) $inv;
        $ovL30 = (float) ($in['ovL30'] ?? 0);
        $sold = (float) ($in['sold'] ?? 0);
        $dil = $inv > 0 ? ($ovL30 / $inv) * 100 : 0.0;

        $category = (string) ($in['category'] ?? '');
        $range = $bids[$category] ?? ['highest' => null, 'lowest' => null];
        $d = PmtCategoryBidRule::decide($inv, $dil, $sold, $range['highest'], $range['lowest']);

        if ($d['pause']) {
            return ['skip' => null, 'off' => true, 'formatted' => null, 'label' => $d['label']];
        }
        $formatted = $d['bid'] !== null ? EbayBidPercentage::forPush($d['bid']) : null;
        if ($formatted === null) {
            return ['skip' => $d['label'], 'off' => false, 'formatted' => null, 'label' => $d['label']];
        }

        return ['skip' => null, 'off' => false, 'formatted' => $formatted, 'label' => $d['label']];
    }

    /**
     * Ads Rule PMT on eBay 2 runs per PARENT, not per SKU / listing: INV and OV L30 are summed over
     * every child SKU of the parent (same as the PARENT row on /ebay2-tabulator-view), eBay sold is
     * summed over the parent's listings, and the category is the parent's listing category.
     * Every ad listing of the parent then gets the same decision. A listing whose SKUs have no
     * parent is its own family (same numbers as the old per-listing roll-up).
     *
     * @param  array<int, mixed>  $listingIds
     * @param  class-string  $metricClass
     * @param  array<string, string>  $categories  normalized item id => category
     * @return array<string, array{inv:?float,ovL30:float,sold:float,category:string}>  listing id => family inputs
     */
    private function pmtParentFamilies(array $listingIds, string $metricClass, array $categories): array
    {
        $listingIds = array_values(array_unique(array_map('strval', $listingIds)));
        if ($listingIds === []) {
            return [];
        }

        $normSku = static fn ($s): string => strtoupper(trim((string) $s));
        $isParentSku = static fn (string $s): bool => str_starts_with($s, 'PARENT');

        // 1. SKUs of the ad listings -> their parent value.
        $adMetrics = $metricClass::whereIn('item_id', $listingIds)->get(['item_id', 'sku', 'ebay_l30']);
        $adSkus = $adMetrics->pluck('sku')->map(fn ($s) => trim((string) $s))->filter()->unique()->values()->all();
        $parentBySku = $adSkus === []
            ? []
            : ProductMaster::query()->whereIn('sku', $adSkus)->pluck('parent', 'sku')
                ->mapWithKeys(fn ($p, $s) => [$normSku($s) => trim((string) $p)])->all();

        // 2. Every child SKU of those parents (also the ones with no ad / no listing).
        $parentValues = array_values(array_unique(array_filter($parentBySku, fn ($p) => $p !== '')));
        $familySkus = []; // family key => list of child SKUs
        $familyKeyBySku = [];
        if ($parentValues !== []) {
            foreach (ProductMaster::query()->whereIn('parent', $parentValues)->get(['sku', 'parent']) as $pm) {
                $sku = trim((string) $pm->sku);
                if ($sku === '' || $isParentSku($normSku($sku))) {
                    continue;
                }
                $key = 'P:'.$normSku($pm->parent);
                $familySkus[$key][$normSku($sku)] = $sku;
                $familyKeyBySku[$normSku($sku)] = $key;
            }
        }

        // 3. Family key per ad listing (a listing with no parent is its own family).
        // A real parent family always wins over the standalone fallback, whatever the metric row order is
        // (a PARENT ... row or an unmapped SKU can come first and must not hide the child SKUs' family).
        $familyByListing = [];
        foreach ($adMetrics as $m) {
            $sku = $normSku($m->sku);
            $key = $familyKeyBySku[$sku] ?? null;
            if ($key === null && $isParentSku($sku)) {
                // A PARENT row itself: its family is the parent value it belongs to (children carry the stock).
                $pv = $parentBySku[$sku] ?? '';
                $pk = 'P:'.$normSku($pv);
                $key = ($pv !== '' && isset($familySkus[$pk])) ? $pk : null;
            }
            if ($key !== null) {
                $familyByListing[(string) $m->item_id] ??= $key;
            }
        }
        foreach ($adMetrics as $m) {
            $lid = (string) $m->item_id;
            if (isset($familyByListing[$lid]) && $familyByListing[$lid] !== 'L:'.$lid) {
                continue;
            }
            $key = 'L:'.$lid;
            $familySkus[$key][$normSku($m->sku)] = trim((string) $m->sku);
            $familyByListing[$lid] = $key;
        }

        // 4. Inventory / sold for every child SKU of every family.
        $allSkus = [];
        foreach ($familySkus as $skus) {
            foreach ($skus as $sku) {
                if ($sku !== '' && ! $isParentSku($normSku($sku))) {
                    $allSkus[$sku] = true;
                }
            }
        }
        $allSkus = array_keys($allSkus);
        $shopifyMap = $this->shopifyBySku($allSkus);
        $soldByItem = []; // item id => listing-level eBay sold (the same number sits on every variation)
        $itemsByFamily = [];
        if ($allSkus !== []) {
            foreach ($metricClass::whereIn('sku', $allSkus)->get(['item_id', 'sku', 'ebay_l30']) as $m) {
                $item = trim((string) $m->item_id);
                $key = $familyKeyBySku[$normSku($m->sku)] ?? null;
                if ($item === '' || $key === null) {
                    continue;
                }
                $soldByItem[$item] = max($soldByItem[$item] ?? 0.0, (float) ($m->ebay_l30 ?? 0));
                $itemsByFamily[$key][$item] = true;
            }
        }
        foreach ($adMetrics as $m) { // listings of the no-parent families + any the SKU lookup missed
            $item = trim((string) $m->item_id);
            $key = $familyByListing[$item] ?? null;
            if ($item === '' || $key === null) {
                continue;
            }
            $soldByItem[$item] = max($soldByItem[$item] ?? 0.0, (float) ($m->ebay_l30 ?? 0));
            $itemsByFamily[$key][$item] = true;
        }

        // 5. Roll each family up once, then hand the same numbers to every ad listing in it.
        $rolledByFamily = [];
        foreach ($familySkus as $key => $skus) {
            $inv = 0.0;
            $ov = 0.0;
            $hasInv = false;
            foreach ($skus as $sku) {
                $shop = $shopifyMap[$sku] ?? null;
                if ($shop === null) {
                    continue;
                }
                if (isset($shop->inv) && is_numeric($shop->inv)) {
                    $inv += (float) $shop->inv;
                    $hasInv = true;
                }
                if (isset($shop->quantity) && is_numeric($shop->quantity)) {
                    $ov += (float) $shop->quantity;
                }
            }
            $sold = 0.0;
            $category = '';
            foreach (array_keys($itemsByFamily[$key] ?? []) as $item) {
                $sold += $soldByItem[$item] ?? 0.0;
                if ($category === '') {
                    $category = $categories[ExcelCategoryLookupService::normalizeItemId($item)] ?? '';
                }
            }
            $rolledByFamily[$key] = ['inv' => $hasInv ? $inv : null, 'ovL30' => $ov, 'sold' => $sold, 'category' => $category];
        }

        $out = [];
        foreach ($listingIds as $lid) {
            $key = $familyByListing[$lid] ?? null;
            if ($key !== null && isset($rolledByFamily[$key])) {
                $out[$lid] = $rolledByFamily[$key];
            }
        }

        return $out;
    }

    /**
     * @param  array<int, mixed>  $listingIds
     * @param  callable(string, object, array): array  $decider
     * @return list<string>
     */
    private function pmtLocalMismatches(array $listingIds, string $adsTable, string $metricClass, callable $decider): array
    {
        $listingIds = array_values(array_unique(array_map('strval', $listingIds)));
        if ($listingIds === []) {
            return [];
        }

        $metricsByListing = $metricClass::whereIn('item_id', $listingIds)->get()->groupBy(fn ($m) => (string) $m->item_id);
        $ads = DB::table($adsTable)
            ->whereIn('listing_id', $listingIds)
            ->whereNotNull('campaign_id')
            ->where('campaign_id', '!=', '')
            ->where('funding_strategy', 'COST_PER_SALE')
            ->get()
            ->groupBy(fn ($ad) => (string) $ad->listing_id);
        $shopifyMap = $this->shopifyBySku($this->skusForListings($listingIds, $metricsByListing, $ads));

        $out = [];
        foreach ($listingIds as $lid) {
            foreach ($ads->get($lid) ?? [] as $ad) {
                if (! $ad->campaign_id) {
                    continue;
                }
                $rolled = $this->rolledListingInputs($lid, $ad, $metricsByListing, $shopifyMap, [], []);
                $wanted = $decider($lid, $ad, $rolled);
                if ($wanted['skip'] !== null) {
                    continue;
                }
                if ($wanted['off']) {
                    $out[] = $lid; // still RUNNING, so it needs pausing

                    continue;
                }
                $local = isset($ad->bid_percentage) && is_numeric($ad->bid_percentage) ? (float) $ad->bid_percentage : null;
                if (! EbayCampaignAdLiveBid::matches($local, (float) $wanted['formatted'])) {
                    $out[] = $lid;
                }
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Stored C Bid vs current Dil vs SBid. Used after Dil / views / CVR /
     * inventory change so the live pull only runs for yellow pending rows.
     *
     * @param  class-string  $metricClass
     * @param  array<int, mixed>  $listingIds
     * @return list<string>
     */
    public function filterLocalMismatches(string $ruleKey, string $adsTable, string $metricClass, array $listingIds): array
    {
        $listingIds = array_values(array_unique(array_map('strval', $listingIds)));
        if ($listingIds === []) {
            return [];
        }

        $stored = DilVsSbidRule::load($ruleKey);
        if (empty($stored['enabled'])) {
            return [];
        }

        $slabs = $stored['slabs'];
        $cvr = $stored['cvr'];
        $tables = $stored['tables'];
        $cap = $stored['cap'] ?? DilVsSbidRule::defaultCap();
        $viewsOver = $stored['views_over'] ?? DilVsSbidRule::defaultViewOver();
        $metricsByListing = $metricClass::whereIn('item_id', $listingIds)->get()->groupBy(fn ($m) => (string) $m->item_id);
        $ads = DB::table($adsTable)
            ->whereIn('listing_id', $listingIds)
            ->whereNotNull('campaign_id')
            ->where('campaign_id', '!=', '')
            ->where('funding_strategy', 'COST_PER_SALE')
            ->get()
            ->groupBy(fn ($ad) => (string) $ad->listing_id);

        $skus = $this->skusForListings($listingIds, $metricsByListing, $ads);
        $shopifyMap = $this->shopifyBySku($skus);
        $npftMap = DilVsSbidRule::usesNpft($tables) ? EbayStdNpftLookup::forSkus($skus) : [];
        $gpftMap = DilVsSbidRule::usesGpft($tables) ? $this->gpftBySku($metricsByListing) : [];

        $out = [];
        foreach ($listingIds as $lid) {
            $campaignAds = $ads->get($lid);
            if ($campaignAds === null || $campaignAds->isEmpty()) {
                continue;
            }
            foreach ($campaignAds as $ad) {
                if (! $ad->campaign_id) {
                    continue;
                }
                $rolled = $this->rolledListingInputs($lid, $ad, $metricsByListing, $shopifyMap, $npftMap, $gpftMap);
                $wanted = $this->listingDecision(
                    true,
                    $ad,
                    $rolled['metric'],
                    $rolled['shopify'],
                    $slabs,
                    $cvr,
                    $tables,
                    $cap,
                    $viewsOver,
                    $rolled['npftMap'],
                    DilVsSbidRule::usesEsBidCeiling($ruleKey),
                    $rolled['gpftMap']
                );
                if ($wanted['skip'] !== null || $wanted['off'] || $wanted['formatted'] === null) {
                    continue;
                }
                $local = isset($ad->bid_percentage) && is_numeric($ad->bid_percentage)
                    ? (float) $ad->bid_percentage
                    : null;
                if (! EbayCampaignAdLiveBid::matches($local, (float) $wanted['formatted'])) {
                    $out[] = $lid;
                }
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  \Illuminate\Support\Collection<string, mixed>  $metricsByListing
     * @param  \Illuminate\Support\Collection<string, mixed>  $ads
     * @param  array<int, mixed>  $listingIds
     * @return list<string>
     */
    private function skusForListings(array $listingIds, $metricsByListing, $ads): array
    {
        $skus = [];
        foreach ($listingIds as $lid) {
            $lid = (string) $lid;
            foreach ($metricsByListing->get($lid) ?? [] as $metric) {
                $sku = trim((string) ($metric->sku ?? ''));
                if ($sku !== '') {
                    $skus[] = $sku;
                }
            }
            $adSku = trim((string) ($ads->get($lid)?->first()->sku ?? ''));
            if ($adSku !== '') {
                $skus[] = $adSku;
            }
        }

        return array_values(array_unique($skus));
    }

    /**
     * Family Dil / views / sold for one listing. Several variation SKUs
     * on the same item_id become one S Bid.
     *
     * @param  \Illuminate\Support\Collection<string, mixed>  $metricsByListing
     * @param  array<string, mixed>  $shopifyMap
     * @param  array<string, mixed>  $npftMap
     * @param  array<string, ?float>  $gpftMap
     * @return array{metric:object,shopify:object,npftMap:array<string,?float>,gpftMap:array<string,?float>}
     */
    private function rolledListingInputs(string $listingId, object $ad, $metricsByListing, array $shopifyMap, array $npftMap, array $gpftMap = []): array
    {
        $members = [];
        foreach ($metricsByListing->get($listingId) ?? [] as $metric) {
            $sku = trim((string) ($metric->sku ?? ''));
            $shop = $sku !== '' ? ($shopifyMap[$sku] ?? null) : null;
            $members[] = [
                'sku' => $sku !== '' ? $sku : trim((string) ($ad->sku ?? '')),
                'quantity' => $shop?->quantity,
                'inv' => $shop?->inv,
                'views' => $metric->views ?? 0,
                'ebay_l30' => $metric->ebay_l30 ?? 0,
                'ebay_l60' => $metric->ebay_l60 ?? 0,
                'l7_views' => $metric->l7_views ?? 0,
                'npft' => $sku !== '' ? ($npftMap[EbayStdNpftLookup::key($sku)] ?? null) : null,
                'gpft' => $sku !== '' ? ($gpftMap[EbayStdNpftLookup::key($sku)] ?? null) : null,
            ];
        }
        if ($members === []) {
            $sku = trim((string) ($ad->sku ?? ''));
            $shop = $sku !== '' ? ($shopifyMap[$sku] ?? null) : null;
            $members[] = [
                'sku' => $sku,
                'quantity' => $shop?->quantity,
                'inv' => $shop?->inv,
                'views' => 0,
                'ebay_l30' => 0,
                'ebay_l60' => 0,
                'l7_views' => 0,
                'npft' => $sku !== '' ? ($npftMap[EbayStdNpftLookup::key($sku)] ?? null) : null,
                'gpft' => $sku !== '' ? ($gpftMap[EbayStdNpftLookup::key($sku)] ?? null) : null,
            ];
        }

        $rolled = DilVsSbidListingRollup::combine($members);
        $sku = $rolled['sku'];

        return [
            'metric' => (object) [
                'sku' => $sku,
                'views' => $rolled['views'],
                'ebay_l30' => $rolled['ebay_l30'],
                'ebay_l60' => $rolled['ebay_l60'],
                'l7_views' => $rolled['l7_views'],
            ],
            'shopify' => (object) [
                'quantity' => $rolled['quantity'],
                'inv' => $rolled['inv'],
            ],
            'npftMap' => $sku !== '' ? [EbayStdNpftLookup::key($sku) => $rolled['npft']] : [],
            'gpftMap' => $sku !== '' ? [EbayStdNpftLookup::key($sku) => $rolled['gpft']] : [],
        ];
    }

    /**
     * GPFT % per SKU from each listing's own price (ebay_metrics.ebay_price),
     * the same number the eBay 1 grid shows. Keyed by EbayStdNpftLookup::key(sku).
     *
     * @param  \Illuminate\Support\Collection<string, mixed>  $metricsByListing
     * @return array<string, ?float>
     */
    private function gpftBySku($metricsByListing): array
    {
        $prices = [];
        foreach ($metricsByListing as $metrics) {
            foreach ($metrics as $metric) {
                $sku = trim((string) ($metric->sku ?? ''));
                if ($sku !== '') {
                    $prices[$sku] = $metric->ebay_price ?? null;
                }
            }
        }

        return EbayGpftLookup::forPrices($prices);
    }

    /**
     * @param  object  $ad
     * @param  object|null  $metric
     * @param  object|null  $shopify
     * @return array{skip:?string, off:bool, formatted:?string, label:string}
     */
    private function listingDecision(
        bool $useDil,
        object $ad,
        $metric,
        $shopify,
        array $slabs,
        array $cvr,
        array $tables,
        array $cap,
        array $viewsOver,
        array $npftMap,
        bool $esCeiling = false,
        array $gpftMap = []
    ): array {
        if (! $useDil) {
            return ['skip' => 'Dil vs SBid is off', 'off' => false, 'formatted' => null, 'label' => ''];
        }

        $sku = (string) ($metric?->sku ?? $ad->sku ?? '');
        $dil = CpMasterDil::slabPercent($shopify?->quantity ?? null, $shopify?->inv ?? null);
        if ($dil === null) {
            return ['skip' => 'No CP Master Dil', 'off' => false, 'formatted' => null, 'label' => ''];
        }

        $esBid = (float) ($ad->suggested_bid ?? 0);
        $views = (float) ($metric?->views ?? 0);
        $sold = (float) ($metric?->ebay_l30 ?? 0);
        $decision = DilVsSbidRule::resolveTotal((float) $dil, $esBid, $slabs, $tables, [
            'views' => $views,
            'cvr' => $views > 0 ? ($sold / $views) * 100 : null,
            'sold' => $sold,
            'npft' => $npftMap[EbayStdNpftLookup::key($sku)] ?? null,
            'gpft' => $gpftMap[EbayStdNpftLookup::key($sku)] ?? null,
        ]);
        if ($decision['bid'] > 0) {
            $adjusted = DilVsSbidRule::applyCvr(
                (float) $decision['bid'],
                $views,
                $sold,
                (float) ($metric?->ebay_l60 ?? 0),
                $cvr
            );
            $viewAdj = DilVsSbidRule::applyViewOver(
                (float) $adjusted['bid'],
                $views,
                (float) ($metric?->l7_views ?? 0),
                $viewsOver
            );
            $decision['bid'] = DilVsSbidRule::clampBid((float) $viewAdj['bid'], $cap, $esBid, $esCeiling);
            $why = trim($adjusted['why'].' '.$viewAdj['why']);
            if ($why !== '') {
                $decision['label'] = trim($decision['label'].' '.$why);
            }
        }

        if ($decision['off']) {
            return ['skip' => null, 'off' => true, 'formatted' => null, 'label' => (string) ($decision['label'] ?? '')];
        }

        $formatted = EbayBidPercentage::forPush((float) $decision['bid']);
        if ($formatted === null) {
            return [
                'skip' => $decision['label'] !== '' ? $decision['label'] : 'No S Bid for this Dil',
                'off' => false,
                'formatted' => null,
                'label' => (string) ($decision['label'] ?? ''),
            ];
        }

        return ['skip' => null, 'off' => false, 'formatted' => $formatted, 'label' => (string) ($decision['label'] ?? '')];
    }

    /**
     * Pull live C Bid, push S Bid when they differ, pull again. Repeat until
     * they match or the verify rounds run out. Local C Bid is written only
     * from a listing-level live pull.
     *
     * Every campaign goes through each round together, so the verify wait
     * happens once per round for the whole run, not once per campaign.
     * A campaign drops out as soon as all its listings match.
     *
     * @param  array<string, list<array{listingId:string,adId:?string,bidPercentage:string}>>  $bidsByCampaign
     */
    private function pushUntilMatched(
        EbayMarketingPushRetry $http,
        string $adsTable,
        array $bidsByCampaign,
        array &$results,
        int &$success,
        int &$failed,
        int &$unchanged,
        bool $resumeFirst
    ): void {
        $pending = $bidsByCampaign;
        $first = true;
        for ($round = 1; $round <= self::VERIFY_ROUNDS && $pending !== []; $round++) {
            if (! $first) {
                $this->pauseVerify($round - 1);
            }
            $next = [];
            foreach ($pending as $campaignId => $requests) {
                $campaignId = (string) $campaignId;
                if ($this->switchIsOff()) {
                    // Switch turned Off while this run was going: stop pushing.
                    foreach ($requests as $r) {
                        $results[] = ['listing_id' => $r['listingId'], 'status' => 'skipped', 'reason' => 'Dil vs SBid switched off'];
                        $this->switchOffSkipped++;
                    }

                    continue;
                }
                $live = $this->pullListingBids($http, $campaignId, $requests);
                $needPush = [];
                foreach ($requests as $r) {
                    $want = (float) $r['bidPercentage'];
                    $got = $live[(string) $r['listingId']] ?? null;
                    if (EbayCampaignAdLiveBid::matches($got, $want)) {
                        $this->storeLiveBid($adsTable, $campaignId, $r, (float) $got);
                        $results[] = [
                            'listing_id' => $r['listingId'],
                            'status' => $first ? 'unchanged' : 'pushed',
                            'bid' => number_format((float) $got, 1, '.', '').'%',
                        ];
                        if ($first) {
                            $unchanged++;
                        } else {
                            $success++;
                        }
                        continue;
                    }
                    $needPush[] = $r;
                }
                if ($needPush === []) {
                    continue;
                }
                if ($first && $resumeFirst) {
                    $this->resumeAds($http, $adsTable, $campaignId, $needPush);
                }
                $sent = $this->sendBids($http, $adsTable, $campaignId, $needPush);
                $byId = [];
                foreach ($needPush as $np) {
                    $byId[(string) $np['listingId']] = $np;
                }
                foreach ($sent['failed'] as $fail) {
                    $req = $byId[(string) $fail['listingId']] ?? null;
                    if ($req !== null
                        && EbayCampaignEndedListingRemap::isEndedListingError((string) $fail['reason'])
                        && $this->recoverInvalidListing($http, $adsTable, $campaignId, $req, (string) $fail['reason'], $results, $success)) {
                        continue;
                    }
                    $results[] = ['listing_id' => $fail['listingId'], 'status' => 'failed', 'reason' => $fail['reason']];
                    $failed++;
                }
                if ($sent['accepted'] !== []) {
                    $next[$campaignId] = $sent['accepted'];
                }
            }
            $first = false;
            $pending = $this->switchIsOff() ? [] : $next;
        }

        if ($pending === []) {
            return;
        }

        $this->pauseVerify(self::VERIFY_ROUNDS);
        foreach ($pending as $campaignId => $requests) {
            $campaignId = (string) $campaignId;
            $live = $this->pullListingBids($http, $campaignId, $requests);
            foreach ($requests as $r) {
                $want = (float) $r['bidPercentage'];
                $got = $live[(string) $r['listingId']] ?? null;
                if (EbayCampaignAdLiveBid::matches($got, $want)) {
                    $this->storeLiveBid($adsTable, $campaignId, $r, (float) $got);
                    $results[] = ['listing_id' => $r['listingId'], 'status' => 'pushed', 'bid' => number_format($want, 1, '.', '').'%'];
                    $success++;
                    continue;
                }
                if ($got !== null) {
                    $this->storeLiveBid($adsTable, $campaignId, $r, (float) $got);
                } elseif ($this->listingIsDead((string) $r['listingId'])
                    && $this->recoverInvalidListing($http, $adsTable, $campaignId, $r, 'Ad not found on ended listing', $results, $success)) {
                    continue;
                }
                $liveText = $got !== null ? number_format((float) $got, 1, '.', '').'%' : 'empty';
                $results[] = [
                    'listing_id' => $r['listingId'],
                    'status' => 'failed',
                    'reason' => 'Live C Bid '.$liveText.' still does not match S Bid '.number_format($want, 1, '.', '').'%',
                ];
                $failed++;
            }
        }
    }

    /**
     * @param  list<array{listingId:string,adId:?string,bidPercentage:string}>  $requests
     * @return array<string, float>
     */
    private function pullListingBids(EbayMarketingPushRetry $http, string $campaignId, array $requests): array
    {
        $wanted = [];
        foreach ($requests as $r) {
            $wanted[(string) $r['listingId']] = $r;
        }
        $byListing = [];
        foreach ($this->fetchCampaignAds($http, $campaignId) as $ad) {
            if (! is_array($ad)) {
                continue;
            }
            $lid = (string) ($ad['listingId'] ?? '');
            if ($lid === '' || ! isset($wanted[$lid])) {
                continue;
            }
            $bid = EbayCampaignAdLiveBid::fromPayload($ad);
            if ($bid !== null) {
                $byListing[$lid] = $bid;
            }
        }
        foreach ($wanted as $lid => $r) {
            if (isset($byListing[$lid]) || empty($r['adId'])) {
                continue;
            }
            $out = $http->get('https://api.ebay.com/sell/marketing/v1/ad_campaign/'.$campaignId.'/ad/'.$r['adId']);
            $body = ($out['ok'] && $out['response'] !== null) ? $out['response']->json() : null;
            if (! is_array($body)) {
                continue;
            }
            $bid = EbayCampaignAdLiveBid::fromPayload($body);
            if ($bid !== null) {
                $byListing[$lid] = $bid;
            }
        }

        return $byListing;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchCampaignAds(EbayMarketingPushRetry $http, string $campaignId): array
    {
        $all = [];
        $offset = 0;
        $limit = 200;
        do {
            $out = $http->get('https://api.ebay.com/sell/marketing/v1/ad_campaign/'.$campaignId.'/ad', [
                'limit' => $limit,
                'offset' => $offset,
            ]);
            if (! $out['ok'] || $out['response'] === null) {
                break;
            }
            $data = $out['response']->json();
            $batch = is_array($data) ? ($data['ads'] ?? []) : [];
            if (! is_array($batch) || $batch === []) {
                break;
            }
            $all = array_merge($all, $batch);
            $total = (int) ($data['total'] ?? 0);
            $offset += $limit;
            if ($total > 0 && count($all) >= $total) {
                break;
            }
        } while (count($batch) >= $limit);

        return $all;
    }

    /**
     * @param  list<array{listingId:string,adId:?string,bidPercentage:string}>  $requests
     * @return array{accepted: list<array{listingId:string,adId:?string,bidPercentage:string}>, failed: list<array{listingId:string,reason:string}>}
     */
    private function sendBids(EbayMarketingPushRetry $http, string $adsTable, string $campaignId, array $requests, int $depth = 0): array
    {
        $accepted = [];
        $failed = [];
        $payload = array_map(fn ($r) => [
            'listingId' => $r['listingId'],
            'bidPercentage' => $r['bidPercentage'],
        ], $requests);

        $out = $http->post("https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/bulk_update_ads_bid_by_listing_id", [
            'requests' => $payload,
        ]);
        $response = $out['response'];
        $body = $response !== null ? $response->json() : null;

        if ($out['ok'] && is_array($body) && isset($body['responses']) && is_array($body['responses'])) {
            $byListing = [];
            foreach ($body['responses'] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $id = (string) ($row['listingId'] ?? '');
                if ($id !== '') {
                    $byListing[$id] = $row;
                }
            }
            $retryAtMax = [];
            foreach ($requests as $r) {
                $row = $byListing[(string) $r['listingId']] ?? null;
                $code = is_array($row) ? (int) ($row['statusCode'] ?? 200) : 0;
                $ok = is_array($row) && $code >= 200 && $code < 300 && empty($row['errors']);
                if ($ok) {
                    $accepted[] = $r;
                    continue;
                }
                $max = EbayBidPercentage::maxFromError($row);
                if ($max !== null && (float) $r['bidPercentage'] > $max && $depth < 2) {
                    $r['bidPercentage'] = number_format($max, 1, '.', '');
                    $retryAtMax[] = $r;
                    continue;
                }
                $reason = is_array($row) ? ($row['errors'][0]['message'] ?? 'Push failed') : 'Push failed';
                $failed[] = ['listingId' => (string) $r['listingId'], 'reason' => $reason];
            }
            if ($retryAtMax !== []) {
                $again = $this->sendBids($http, $adsTable, $campaignId, $retryAtMax, $depth + 1);
                $accepted = array_merge($accepted, $again['accepted']);
                $failed = array_merge($failed, $again['failed']);
            }

            return ['accepted' => $accepted, 'failed' => $failed];
        }

        if ($out['ok'] && $response !== null) {
            if (count($requests) === 1) {
                return ['accepted' => $requests, 'failed' => []];
            }
            if ($depth < 6) {
                $mid = intdiv(count($requests), 2);
                $left = $this->sendBids($http, $adsTable, $campaignId, array_slice($requests, 0, $mid), $depth + 1);
                $right = $this->sendBids($http, $adsTable, $campaignId, array_slice($requests, $mid), $depth + 1);

                return [
                    'accepted' => array_merge($left['accepted'], $right['accepted']),
                    'failed' => array_merge($left['failed'], $right['failed']),
                ];
            }
            foreach ($requests as $r) {
                $failed[] = ['listingId' => (string) $r['listingId'], 'reason' => 'No per-listing result'];
            }

            return ['accepted' => [], 'failed' => $failed];
        }

        $reason = (string) ($out['error'] ?? 'Push failed');
        $status = $response !== null ? $response->status() : 0;
        $max = EbayBidPercentage::maxFromError($body);
        if ($max !== null && $depth < 2) {
            $clamped = [];
            $changed = false;
            foreach ($requests as $r) {
                if ((float) $r['bidPercentage'] > $max) {
                    $r['bidPercentage'] = number_format($max, 1, '.', '');
                    $changed = true;
                }
                $clamped[] = $r;
            }
            if ($changed) {
                return $this->sendBids($http, $adsTable, $campaignId, $clamped, $depth + 1);
            }
        }

        // One invalid / ended listing can reject the whole batch; split until it is isolated.
        $bidRejected = $max !== null
            || str_contains(strtolower($reason), 'bidpercentage')
            || str_contains(strtolower($reason), 'bid percentage')
            || EbayCampaignEndedListingRemap::isEndedListingError($reason);
        if ($bidRejected && count($requests) > 1 && $depth < 6) {
            $mid = intdiv(count($requests), 2);
            $left = $this->sendBids($http, $adsTable, $campaignId, array_slice($requests, 0, $mid), $depth + 1);
            $right = $this->sendBids($http, $adsTable, $campaignId, array_slice($requests, $mid), $depth + 1);

            return [
                'accepted' => array_merge($left['accepted'], $right['accepted']),
                'failed' => array_merge($left['failed'], $right['failed']),
            ];
        }

        $this->noteSellerPause($adsTable, $campaignId, $status, $reason);
        foreach ($requests as $r) {
            $failed[] = ['listingId' => (string) $r['listingId'], 'reason' => $reason];
        }

        return ['accepted' => [], 'failed' => $failed];
    }

    /** Local metrics say this item_id is ended (or unknown), so an empty live pull means the listing is gone. */
    private function listingIsDead(string $listingId): bool
    {
        if ($this->metricClass === null) {
            return false;
        }
        $statuses = $this->metricClass::where('item_id', $listingId)->pluck('listing_status');
        if ($statuses->isEmpty()) {
            return true;
        }
        foreach ($statuses as $status) {
            if (! EbayListingEnded::isEnded($status)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The listing id on the ad is invalid / ended. Pull the SKU's current live
     * listing id, move the ad row to it, and put it in the same campaign with
     * the wanted bid (or just update the bid when it is already advertised).
     *
     * @param  array{listingId:string,adId:?string,bidPercentage:string,sku?:string}  $r
     * @param  array<int, array<string, mixed>>  $results
     * @return bool true when the SKU ended up pushed on the new listing id
     */
    private function recoverInvalidListing(EbayMarketingPushRetry $http, string $adsTable, string $campaignId, array $r, string $reason, array &$results, int &$success): bool
    {
        $oldId = (string) $r['listingId'];
        $sku = trim((string) ($r['sku'] ?? ''));
        if ($sku === '' || $this->metricClass === null) {
            return false;
        }

        try {
            $live = EbayCampaignEndedListingRemap::resolveLiveListing($sku, $oldId, $http->token(), $this->metricClass);
            $newId = trim((string) ($live['listing_id'] ?? ''));
            if ($newId === '' || $newId === $oldId) {
                return false;
            }
            $newSku = trim((string) ($live['sku'] ?? '')) !== '' ? trim((string) $live['sku']) : $sku;

            $newId = EbayCampaignEndedListingRemap::remapAdsRow($adsTable, $oldId, $newId, $newSku);
            // A listing can have several rows (unique per listing + campaign on ebay2/3);
            // prefer the one already live in a campaign, else the fresh remapped row.
            $rows = DB::table($adsTable)->where('listing_id', $newId)->get();
            $row = $rows->first(fn ($x) => trim((string) ($x->campaign_id ?? '')) !== ''
                    && in_array(strtoupper(trim((string) ($x->campaign_status ?? ''))), ['RUNNING', 'PAUSED', 'SYSTEM_PAUSED'], true))
                ?? $rows->first();
            $rowCampaign = trim((string) ($row->campaign_id ?? ''));
            $rowStatus = strtoupper(trim((string) ($row->campaign_status ?? '')));
            $targetCampaign = ($rowCampaign !== '' && in_array($rowStatus, ['RUNNING', 'PAUSED', 'SYSTEM_PAUSED'], true))
                ? $rowCampaign
                : $campaignId;
            $bid = (string) $r['bidPercentage'];

            $adId = $row->ad_id ?? null;
            if ($targetCampaign === $rowCampaign && $adId) {
                $sent = $this->sendBids($http, $adsTable, $targetCampaign, [[
                    'listingId' => $newId,
                    'adId' => (string) $adId,
                    'bidPercentage' => $bid,
                    'sku' => $newSku,
                ]]);
                if ($sent['accepted'] === []) {
                    Log::warning('Dil vs SBid relist bid update failed', ['old' => $oldId, 'new' => $newId, 'reason' => $sent['failed'][0]['reason'] ?? '']);

                    return false;
                }
            } else {
                $created = $http->post("https://api.ebay.com/sell/marketing/v1/ad_campaign/{$targetCampaign}/ad", [
                    'listingId' => $newId,
                    'bidPercentage' => $bid,
                ]);
                $createdBody = $created['response']?->json();
                $err = is_array($createdBody) ? (string) ($createdBody['errors'][0]['message'] ?? '') : '';
                if (! $created['ok']) {
                    if (stripos($err, 'already exists') === false) {
                        Log::warning('Dil vs SBid relist ad create failed', ['old' => $oldId, 'new' => $newId, 'reason' => $created['error'] ?? $err]);

                        return false;
                    }
                    $sent = $this->sendBids($http, $adsTable, $targetCampaign, [[
                        'listingId' => $newId,
                        'adId' => null,
                        'bidPercentage' => $bid,
                        'sku' => $newSku,
                    ]]);
                    if ($sent['accepted'] === []) {
                        return false;
                    }
                } else {
                    $adId = is_array($createdBody) ? ($createdBody['adId'] ?? null) : null;
                    if (! $adId) {
                        $location = (string) ($created['response']?->header('Location') ?? '');
                        $adId = $location !== '' ? basename(parse_url($location, PHP_URL_PATH) ?: $location) : null;
                    }
                }
            }

            $update = [
                'campaign_id' => $targetCampaign,
                'funding_strategy' => 'COST_PER_SALE',
                'campaign_status' => 'RUNNING',
                'bid_percentage' => round((float) $bid, 1),
                'promote_with_ad' => 'AD_ALREADY_CREATED',
                'updated_at' => now(),
            ];
            if ($adId) {
                $update['ad_id'] = (string) $adId;
            }
            // Drop the blank remapped row when this listing + campaign already has its own row
            // (unique key), then write onto the campaign row or the blank one.
            $hasCampaignRow = DB::table($adsTable)->where('listing_id', $newId)->where('campaign_id', $targetCampaign)->exists();
            $blank = fn () => DB::table($adsTable)->where('listing_id', $newId)
                ->where(fn ($q) => $q->whereNull('campaign_id')->orWhere('campaign_id', ''));
            if ($hasCampaignRow) {
                $blank()->delete();
                DB::table($adsTable)->where('listing_id', $newId)->where('campaign_id', $targetCampaign)->update($update);
            } elseif ($blank()->update($update) === 0) {
                DB::table($adsTable)->insert($update + [
                    'listing_id' => $newId,
                    'sku' => $newSku,
                    'created_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Dil vs SBid relist recovery failed', ['old' => $oldId, 'sku' => $sku, 'error' => $e->getMessage()]);

            return false;
        }

        $results[] = [
            'listing_id' => $newId,
            'status' => 'pushed',
            'bid' => number_format((float) $bid, 1, '.', '').'%',
            'reason' => 'Listing '.$oldId.' invalid ('.$reason.'); pulled new listing id '.$newId,
        ];
        $success++;

        return true;
    }

    private function storeLiveBid(string $adsTable, string $campaignId, array $r, float $live): void
    {
        DB::table($adsTable)
            ->where('listing_id', (string) $r['listingId'])
            ->where('campaign_id', $campaignId)
            ->update([
                'bid_percentage' => round($live, 1),
                'campaign_status' => 'RUNNING',
                'updated_at' => now(),
            ]);
    }

    private function pauseVerify(int $round): void
    {
        $ms = self::VERIFY_DELAYS_MS[min(max($round - 1, 0), count(self::VERIFY_DELAYS_MS) - 1)];
        if ($this->sleeper !== null) {
            ($this->sleeper)($ms);

            return;
        }
        usleep($ms * 1000);
    }

    private function pauseAds(EbayMarketingPushRetry $http, string $adsTable, string $campaignId, array $requests, array &$results, int &$success, int &$failed): void
    {
        $payload = array_map(fn ($r) => [
            'adId' => $r['adId'],
            'adStatus' => 'PAUSED',
        ], $requests);

        $out = $http->post("https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/bulk_update_ads_status", [
            'requests' => $payload,
        ]);
        $response = $out['response'];

        if ($out['ok'] && $response !== null && $this->pauseChunkOk($response->json())) {
            foreach ($requests as $r) {
                DB::table($adsTable)
                    ->where('listing_id', (string) $r['listingId'])
                    ->where('campaign_id', $campaignId)
                    ->update([
                        'campaign_status' => 'PAUSED',
                        'updated_at' => now(),
                    ]);
                $results[] = ['listing_id' => $r['listingId'], 'status' => 'pushed', 'bid' => 'OFF'];
                $success++;
            }

            return;
        }

        $reason = $out['ok'] && $response !== null
            ? $this->ebayReason($response->status(), $response->json())
            : (string) ($out['error'] ?? 'Pause failed');
        Log::warning('Dil vs SBid pause failed', [
            'campaign_id' => $campaignId,
            'http' => $response !== null ? $response->status() : 0,
            'reason' => $reason,
        ]);
        foreach ($requests as $r) {
            $results[] = ['listing_id' => $r['listingId'], 'status' => 'failed', 'reason' => 'Pause: '.$reason];
            $failed++;
        }
    }

    /** Turn a previously paused ad back on before writing the new bid. Failures are logged; the bid push still runs. */
    private function resumeAds(EbayMarketingPushRetry $http, string $adsTable, string $campaignId, array $requests): void
    {
        $payload = [];
        foreach ($requests as $r) {
            if (empty($r['adId'])) {
                continue;
            }
            $payload[] = [
                'adId' => $r['adId'],
                'adStatus' => 'ACTIVE',
            ];
        }
        if ($payload === []) {
            return;
        }

        $out = $http->post("https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/bulk_update_ads_status", [
            'requests' => $payload,
        ]);
        if (! $out['ok']) {
            $status = $out['response'] !== null ? $out['response']->status() : 0;
            $this->noteSellerPause($adsTable, $campaignId, $status, (string) ($out['error'] ?? 'Resume failed'));
            Log::warning('Dil vs SBid resume failed', [
                'campaign_id' => $campaignId,
                'error' => $out['error'] ?? 'Resume failed',
            ]);
        }
    }

    /** eBay can return HTTP 200 while a listing in the chunk failed. */
    private function pauseChunkOk($body): bool
    {
        if (! is_array($body)) {
            return true;
        }
        $responses = $body['responses'] ?? null;
        if (! is_array($responses) || $responses === []) {
            return empty($body['errors']);
        }
        foreach ($responses as $row) {
            $code = (int) ($row['statusCode'] ?? 200);
            if ($code >= 400) {
                return false;
            }
        }

        return true;
    }

    private function ebayReason(int $status, $body): string
    {
        if (! is_array($body)) {
            return (string) $status;
        }
        $msg = $body['errors'][0]['message']
            ?? $body['errors'][0]['longMessage']
            ?? $body['message']
            ?? null;

        return $msg ? ($status.': '.$msg) : (string) $status;
    }

    private function noteSellerPause(string $adsTable, string $campaignId, int $status, string $reason): void
    {
        if ($status === 409 && (str_contains(strtolower($reason), 'seller level') || str_contains(strtolower($reason), 'promoted listings'))) {
            DB::table($adsTable)
                ->where('campaign_id', $campaignId)
                ->update(['campaign_status' => 'SYSTEM_PAUSED', 'updated_at' => now()]);
        }
    }

    private function shopifyBySku(array $skus): array
    {
        $skus = array_values(array_unique(array_filter(array_map(
            fn ($s) => trim((string) $s),
            $skus
        ), fn ($s) => $s !== '' && ! str_starts_with(strtoupper($s), 'PARENT'))));
        if ($skus === []) {
            return [];
        }

        $mapped = ShopifySku::mapByProductSkus($skus);
        $out = [];
        foreach ($skus as $sku) {
            if (isset($mapped[$sku])) {
                $out[$sku] = $mapped[$sku];
            }
        }

        return $out;
    }
}
