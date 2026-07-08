<?php

namespace App\Http\Controllers;

use App\Models\AmazonAdsMissingLink;
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
     * Parent + SKU rows from product_master, each flagged as a parent (SKU starting with "PARENT"),
     * plus its linked PT / KW campaigns so the grid can render them.
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

        // All links grouped by sku.
        $linksBySku = AmazonAdsMissingLink::orderBy('id')
            ->get()
            ->groupBy(fn ($l) => (string) $l->sku);

        $data = $rows->map(function ($r) use ($linksBySku) {
            $sku = (string) ($r->sku ?? '');
            $links = $linksBySku->get($sku, collect());

            return [
                'parent' => $r->parent,
                'sku' => $r->sku,
                'is_parent' => Str::startsWith(strtoupper(trim($sku)), 'PARENT'),
                'campaign_pick' => '',
                'pt' => $this->linkListForType($links, 'PT'),
                'kw' => $this->linkListForType($links, 'KW'),
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
     * @return array{ok: bool, sku: string, pt: array, kw: array}
     */
    private function linksResponseForSku(string $sku): array
    {
        $links = AmazonAdsMissingLink::where('sku', $sku)->orderBy('id')->get();

        return [
            'ok' => true,
            'sku' => $sku,
            'pt' => $this->linkListForType($links, 'PT'),
            'kw' => $this->linkListForType($links, 'KW'),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection  $links
     * @return array<int, array{id: int, campaign_id: ?string, campaign_name: string}>
     */
    private function linkListForType($links, string $type): array
    {
        return $links
            ->filter(fn ($l) => (string) $l->type === $type)
            ->map(fn ($l) => [
                'id' => (int) $l->id,
                'campaign_id' => $l->campaign_id,
                'campaign_name' => (string) $l->campaign_name,
            ])
            ->values()
            ->all();
    }
}
