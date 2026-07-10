<?php

namespace App\Http\Controllers;

use App\Models\AmazonAdsMissingLink;
use App\Models\ShopifySku;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AmazonAdsMissingController extends Controller
{
    private const SP_TABLE = 'amazon_sp_campaign_reports';

    private const TYPES = ['PT', 'KW'];

    public function index()
    {
        return view('amazon_ads.amz_ads_missing');
    }

    /**
     * One synthetic parent row per distinct parent — same method as /amazon-tabulator-view:
     * raw "PARENT …" SKU rows are ignored, children are grouped by their (normalized) parent,
     * and each group yields a single parent row whose SKU is "PARENT {parent}". Inventory is the
     * SUM of the children's Shopify inv. This avoids the duplicate-parent rows that come from
     * multiple / whitespace-variant "PARENT …" SKUs in product_master.
     */
    public function data(): JsonResponse
    {
        if (! Schema::hasTable('product_master')) {
            return response()->json(['data' => []]);
        }

        $rows = DB::table('product_master')
            ->select('parent', 'sku')
            ->orderBy('parent')
            ->orderBy('sku')
            ->get();

        // Inventory per normalized parent = SUM(shopify_skus.inv) over child (non-PARENT) SKUs.
        $inventoryByParent = $this->buildInventorySumByParent($rows);

        // Distinct parents, derived from child rows only (mirrors the tabulator view's grouping).
        $parents = [];
        foreach ($rows as $r) {
            $sku = trim((string) ($r->sku ?? ''));
            if ($sku === '' || Str::startsWith(strtoupper($sku), 'PARENT')) {
                continue;
            }
            $parent = preg_replace('/\s+/', ' ', trim((string) ($r->parent ?? '')));
            if ($parent === '') {
                continue;
            }
            $parents[strtoupper($parent)] = $parent; // display value keyed by uppercase for dedupe
        }
        ksort($parents);

        // All links grouped by sku ("PARENT {parent}").
        $linksBySku = AmazonAdsMissingLink::orderBy('id')
            ->get()
            ->groupBy(fn ($l) => (string) $l->sku);

        $statusMap = $this->campaignStatusMap();

        $data = collect($parents)->map(function ($parent, $parentKey) use ($linksBySku, $inventoryByParent, $statusMap) {
            $sku = 'PARENT '.$parent;
            $links = $linksBySku->get($sku, collect());

            return [
                'parent' => $parent,
                'sku' => $sku,
                'is_parent' => true,
                'inventory' => (int) round($inventoryByParent[$parentKey] ?? 0),
                'campaign_pick' => '',
                'pt' => $this->linkListForType($links, 'PT', $statusMap),
                'kw' => $this->linkListForType($links, 'KW', $statusMap),
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    /**
     * Distinct SP campaigns (same source as /amazon-ads/all SP reports) for the Campaign picker.
     */
    public function campaigns(): JsonResponse
    {
        if (! Schema::hasTable(self::SP_TABLE) || ! Schema::hasColumn(self::SP_TABLE, 'campaignName')) {
            return response()->json(['data' => []]);
        }

        $rows = DB::table(self::SP_TABLE)
            ->selectRaw('campaignName AS campaign_name, MAX(campaign_id) AS campaign_id')
            ->whereNotNull('campaignName')
            ->where('campaignName', '!=', '')
            ->groupBy('campaignName')
            ->orderBy('campaignName')
            ->get();

        return response()->json(['data' => $rows]);
    }

    /**
     * Link a campaign to a SKU as PT or KW. Campaign id is resolved from the SP reports table by name.
     */
    public function link(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:PT,KW'],
            'campaign_name' => ['required', 'string', 'max:255'],
        ]);

        $campaignId = null;
        if (Schema::hasTable(self::SP_TABLE) && Schema::hasColumn(self::SP_TABLE, 'campaignName')) {
            $campaignId = DB::table(self::SP_TABLE)
                ->where('campaignName', $validated['campaign_name'])
                ->max('campaign_id');
        }

        AmazonAdsMissingLink::firstOrCreate(
            [
                'sku' => $validated['sku'],
                'type' => $validated['type'],
                'campaign_name' => $validated['campaign_name'],
            ],
            [
                'campaign_id' => $campaignId,
                'user_id' => Auth::id(),
                'created_at' => Carbon::now(),
            ]
        );

        return response()->json($this->linksResponseForSku($validated['sku']));
    }

    /**
     * Remove a linked campaign by its id.
     */
    public function unlink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $link = AmazonAdsMissingLink::find($validated['id']);
        $sku = $link?->sku;
        if ($link) {
            $link->delete();
        }

        return response()->json($this->linksResponseForSku((string) $sku));
    }

    /**
     * SUM(shopify_skus.inv) for child (non-PARENT) SKUs, keyed by normalized-uppercase parent name
     * (whitespace collapsed) so it lines up with the grouped parent rows.
     *
     * @param  \Illuminate\Support\Collection  $rows  product_master rows with parent + sku
     * @return array<string, float>
     */
    private function buildInventorySumByParent($rows): array
    {
        $childSkus = [];
        foreach ($rows as $r) {
            $s = trim((string) ($r->sku ?? ''));
            if ($s === '' || Str::startsWith(strtoupper($s), 'PARENT')) {
                continue;
            }
            $childSkus[] = $s;
        }

        $shopifyByPmSku = ShopifySku::mapByProductSkus(array_values(array_unique($childSkus)));

        $totals = [];
        foreach ($rows as $r) {
            $s = trim((string) ($r->sku ?? ''));
            if ($s === '' || Str::startsWith(strtoupper($s), 'PARENT')) {
                continue;
            }
            $pKey = strtoupper(preg_replace('/\s+/', ' ', trim((string) ($r->parent ?? ''))));
            if ($pKey === '') {
                continue;
            }
            $rec = $shopifyByPmSku->get($s);
            $totals[$pKey] = ($totals[$pKey] ?? 0) + (float) ($rec?->inv ?? 0);
        }

        return $totals;
    }

    /**
     * @return array{ok: bool, sku: string, pt: array, kw: array}
     */
    private function linksResponseForSku(string $sku): array
    {
        $links = AmazonAdsMissingLink::where('sku', $sku)->orderBy('id')->get();
        $statusMap = $this->campaignStatusMap();

        return [
            'ok' => true,
            'sku' => $sku,
            'pt' => $this->linkListForType($links, 'PT', $statusMap),
            'kw' => $this->linkListForType($links, 'KW', $statusMap),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection  $links
     * @param  array<string, string>  $statusMap  normalized campaign name => ENABLED/PAUSED
     * @return array<int, array{id: int, campaign_id: ?string, campaign_name: string, status: string, dot: string}>
     */
    private function linkListForType($links, string $type, array $statusMap = []): array
    {
        return $links
            ->filter(fn ($l) => (string) $l->type === $type)
            ->map(function ($l) use ($statusMap) {
                $name = (string) $l->campaign_name;
                $status = $statusMap[$this->normalizeCampaignName($name)] ?? '';
                $dot = $status === 'ENABLED' ? 'green' : ($status !== '' ? 'red' : '');

                return [
                    'id' => (int) $l->id,
                    'campaign_id' => $l->campaign_id,
                    'campaign_name' => $name,
                    'status' => $status,
                    'dot' => $dot,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Latest campaignStatus (ENABLED / PAUSED / …) per SP campaign, keyed by normalized campaign name.
     * Same source as /amazon-ads/all so the dot beside each linked campaign matches that page's status.
     *
     * @return array<string, string>
     */
    private function campaignStatusMap(): array
    {
        if (! Schema::hasTable(self::SP_TABLE)
            || ! Schema::hasColumn(self::SP_TABLE, 'campaignName')
            || ! Schema::hasColumn(self::SP_TABLE, 'campaignStatus')) {
            return [];
        }

        // Latest row per campaign name (highest id) carries the current status.
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
                $key = $this->normalizeCampaignName((string) ($row->campaignName ?? ''));
                if ($key === '') {
                    return;
                }
                $map[$key] = strtoupper(trim((string) ($row->campaignStatus ?? '')));
            });

        return $map;
    }

    /**
     * Normalize a campaign name for status lookups: collapse whitespace, drop a trailing period, upper-case.
     */
    private function normalizeCampaignName(string $name): string
    {
        return strtoupper(rtrim(preg_replace('/\s+/', ' ', trim($name)), '.'));
    }
}
