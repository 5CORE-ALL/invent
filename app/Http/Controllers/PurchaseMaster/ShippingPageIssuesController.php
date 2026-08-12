<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\ChannelMaster;
use App\Models\ProductMaster;
use App\Models\ShippingPageIssue;
use App\Models\ShopifySku;
use App\Services\ShippingPinZoneService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ShippingPageIssuesController extends Controller
{
    public function __construct(private readonly ShippingPinZoneService $pinZoneService)
    {
    }

    public function index()
    {
        $channels = collect();
        if (Schema::hasTable('channel_master')) {
            $channels = ChannelMaster::query()
                ->whereNotNull('channel')
                ->where('channel', '!=', '')
                ->when(
                    Schema::hasColumn('channel_master', 'status'),
                    fn ($q) => $q->whereRaw('LOWER(TRIM(status)) = ?', ['active'])
                )
                ->orderBy('channel')
                ->get(['id', 'channel', 'alias']);
        }

        $skus = ProductMaster::query()
            ->whereNotNull('sku')
            ->where('sku', 'not like', 'PARENT %')
            ->orderBy('sku')
            ->pluck('sku');

        return view('shipping-page-issues', compact('channels', 'skus'));
    }

    public function data()
    {
        $rows = ShippingPageIssue::query()
            ->orderByDesc('o_date')
            ->orderByDesc('id')
            ->get();

        $metaBySku = $this->productMetaLookupForSkus($rows->pluck('sku')->filter()->unique()->values()->all());

        $data = $rows->map(function (ShippingPageIssue $row) use ($metaBySku) {
            $skuKey = strtoupper(trim((string) $row->sku));

            return $this->formatRow($row, $metaBySku[$skuKey] ?? []);
        });

        return response()->json(['data' => $data]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatedPayload($request);
        $validated = $this->withPinDerivedFields($validated);
        $row = ShippingPageIssue::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Shipping cost issue created.',
            'data' => $this->formatRow($row, $this->productMetaForSku($row->sku)),
        ]);
    }

    public function update(Request $request, ShippingPageIssue $shippingPageIssue)
    {
        $validated = $this->validatedPayload($request);
        $validated = $this->withPinDerivedFields($validated);
        $shippingPageIssue->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Shipping cost issue updated.',
            'data' => $this->formatRow($shippingPageIssue, $this->productMetaForSku($shippingPageIssue->sku)),
        ]);
    }

    public function destroy(ShippingPageIssue $shippingPageIssue)
    {
        $shippingPageIssue->delete();

        return response()->json([
            'success' => true,
            'message' => 'Shipping cost issue deleted.',
        ]);
    }

    public function shipForSkuLookup(Request $request)
    {
        $sku = trim((string) $request->query('sku', ''));
        $meta = $this->productMetaForSku($sku);

        return response()->json([
            'success' => true,
            'sku' => $sku,
            'ship' => $meta['ship'] ?? null,
            'image' => $meta['image'] ?? null,
            'product_id' => $meta['product_id'] ?? null,
            'parent' => $meta['parent'] ?? null,
            'label_type' => $meta['label_type'] ?? 'STD',
            'dim_wt' => $meta['dim_wt'] ?? [],
        ]);
    }

    public function pinLookup(Request $request)
    {
        $pin = trim((string) $request->query('pin_code', $request->query('pin', '')));
        $result = $this->pinZoneService->lookup($pin);

        return response()->json([
            'success' => $result['zone'] !== null || $result['state'] !== null,
            'origin_zip' => ShippingPinZoneService::ORIGIN_ZIP,
            'data' => $result,
        ]);
    }

    public function summary()
    {
        $metrics = $this->l30Metrics();

        return response()->json([
            'success' => true,
            'issues_l30' => $metrics['issues_count'],
            'loss_gain_l30' => $metrics['loss_gain_sum'],
            'from' => $metrics['from'],
            'to' => $metrics['to'],
        ]);
    }

    public function history(Request $request)
    {
        $metric = (string) $request->query('metric', 'issues');
        if (! in_array($metric, ['issues', 'loss_gain'], true)) {
            $metric = 'issues';
        }

        $days = (int) $request->query('days', 30);
        if ($days <= 0) {
            $days = 365;
        }
        $days = min(max($days, 7), 365);

        $tz = 'America/Los_Angeles';
        $end = Carbon::now($tz)->startOfDay();
        $start = $end->copy()->subDays($days - 1);
        // Need 29 days before start for rolling L30 windows.
        $fetchFrom = $start->copy()->subDays(29);

        $enriched = $this->enrichedIssuesFrom($fetchFrom->toDateString());

        $labels = [];
        $values = [];
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $windowStart = $cursor->copy()->subDays(29)->toDateString();
            $windowEnd = $cursor->toDateString();
            $inWindow = $enriched->filter(function (array $row) use ($windowStart, $windowEnd) {
                return $row['issue_date'] >= $windowStart && $row['issue_date'] <= $windowEnd;
            });

            $labels[] = $cursor->format('M d');
            if ($metric === 'issues') {
                $values[] = $inWindow->count();
            } else {
                $values[] = round((float) $inWindow->sum('loss_gain'), 2);
            }
        }

        $numeric = array_values(array_filter($values, fn ($v) => $v !== null));
        $highest = $numeric === [] ? null : max($numeric);
        $lowest = $numeric === [] ? null : min($numeric);
        $median = null;
        if ($numeric !== []) {
            sort($numeric);
            $mid = intdiv(count($numeric), 2);
            $median = count($numeric) % 2
                ? $numeric[$mid]
                : round(($numeric[$mid - 1] + $numeric[$mid]) / 2, 2);
        }

        return response()->json([
            'success' => true,
            'metric' => $metric,
            'days' => $days,
            'labels' => $labels,
            'values' => $values,
            'highest' => $highest,
            'median' => $median,
            'lowest' => $lowest,
        ]);
    }

    /**
     * @return array{issues_count: int, loss_gain_sum: float, from: string, to: string}
     */
    private function l30Metrics(): array
    {
        $tz = 'America/Los_Angeles';
        $to = Carbon::now($tz)->startOfDay();
        $from = $to->copy()->subDays(29);

        $enriched = $this->enrichedIssuesFrom($from->toDateString())
            ->filter(function (array $row) use ($from, $to) {
                return $row['issue_date'] >= $from->toDateString()
                    && $row['issue_date'] <= $to->toDateString();
            });

        return [
            'issues_count' => $enriched->count(),
            'loss_gain_sum' => round((float) $enriched->sum('loss_gain'), 2),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ];
    }

    /**
     * @return Collection<int, array{issue_date: string, loss_gain: float}>
     */
    private function enrichedIssuesFrom(string $fromDate): Collection
    {
        $rows = ShippingPageIssue::query()
            ->where(function ($q) use ($fromDate) {
                $q->whereDate('o_date', '>=', $fromDate)
                    ->orWhere(function ($q2) use ($fromDate) {
                        $q2->whereNull('o_date')->whereDate('created_at', '>=', $fromDate);
                    });
            })
            ->get();

        $metaBySku = $this->productMetaLookupForSkus($rows->pluck('sku')->filter()->unique()->values()->all());

        return $rows->map(function (ShippingPageIssue $row) use ($metaBySku) {
            $skuKey = strtoupper(trim((string) $row->sku));
            $ship = $metaBySku[$skuKey]['ship'] ?? null;
            $issueDate = $row->o_date?->format('Y-m-d')
                ?? ($row->created_at ? Carbon::parse($row->created_at)->timezone('America/Los_Angeles')->toDateString() : null);

            return [
                'issue_date' => $issueDate ?? '',
                'loss_gain' => $this->calcLossGainBeforeAction(
                    $row->amount_received,
                    $ship,
                    $row->amount_paid
                ) ?? 0.0,
            ];
        })->filter(fn (array $row) => $row['issue_date'] !== '');
    }

    private function validatedPayload(Request $request): array
    {
        return $request->validate([
            'o_date' => 'nullable|date',
            'o_number' => 'nullable|string|max:100',
            'channel' => 'nullable|string|max:255',
            'sku' => 'nullable|string|max:255',
            'pin_code' => 'nullable|string|max:20',
            'amount_received' => 'nullable|string|max:100',
            'amount_paid' => 'nullable|string|max:100',
            'action_taken' => 'nullable|string|max:5000',
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function withPinDerivedFields(array $validated): array
    {
        $pin = trim((string) ($validated['pin_code'] ?? ''));
        if ($pin === '') {
            $validated['pin_code'] = null;
            $validated['zone'] = null;
            $validated['state'] = null;

            return $validated;
        }

        $lookup = $this->pinZoneService->lookup($pin);
        $validated['pin_code'] = $lookup['pin_code'] ?: $pin;
        $validated['zone'] = $lookup['zone'];
        $validated['state'] = $lookup['state_abbr'] ?: $lookup['state'];

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function formatRow(ShippingPageIssue $row, array $meta): array
    {
        $ship = $meta['ship'] ?? null;

        return [
            'id' => $row->id,
            'o_date' => $row->o_date?->format('Y-m-d'),
            'o_date_display' => $row->o_date?->format('j M'),
            'o_number' => $row->o_number,
            'channel' => $row->channel,
            'sku' => $row->sku,
            'pin_code' => $row->pin_code,
            'zone' => $row->zone,
            'state' => $row->state,
            'ship' => $ship,
            'image' => $meta['image'] ?? null,
            'product_id' => $meta['product_id'] ?? null,
            'parent' => $meta['parent'] ?? null,
            'label_type' => $meta['label_type'] ?? 'STD',
            'dim_wt' => $meta['dim_wt'] ?? [],
            'amount_received' => $row->amount_received,
            'amount_paid' => $row->amount_paid,
            'loss_gain_before_action' => $this->calcLossGainBeforeAction(
                $row->amount_received,
                $ship,
                $row->amount_paid
            ),
            'action_taken' => $row->action_taken,
        ];
    }

    /**
     * Amount Received + Ship - Amount Paid
     */
    private function calcLossGainBeforeAction(mixed $amountReceived, mixed $ship, mixed $amountPaid): ?float
    {
        $received = $this->toNumber($amountReceived);
        $shipVal = $this->toNumber($ship);
        $paid = $this->toNumber($amountPaid);

        if ($received === null && $shipVal === null && $paid === null) {
            return null;
        }

        return round(($received ?? 0) + ($shipVal ?? 0) - ($paid ?? 0), 2);
    }

    private function toNumber(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $normalized = preg_replace('/[^0-9.\-]/', '', $raw);
        if ($normalized === null || $normalized === '' || $normalized === '-' || $normalized === '.') {
            return null;
        }

        return (float) $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function productMetaForSku(?string $sku): array
    {
        if ($sku === null || trim($sku) === '') {
            return [];
        }

        $lookup = $this->productMetaLookupForSkus([trim($sku)]);
        $key = strtoupper(trim($sku));

        return $lookup[$key] ?? [];
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<string, array<string, mixed>>
     */
    private function productMetaLookupForSkus(array $skus): array
    {
        if ($skus === []) {
            return [];
        }

        $allowedLabelTypes = ['ENV', 'STD', 'O-Size', 'Pallet'];
        $dimKeys = [
            'wt_act_kg', 'wt_act', 'wt_decl',
            'l', 'w', 'h', 'l_decl', 'w_decl', 'h_decl',
            'l_cm', 'w_cm', 'h_cm',
            'ctn_l', 'ctn_w', 'ctn_h', 'ctn_qty', 'ctn_cbm', 'ctn_cbm_each',
            'ctn_instructions', 'instructions_item_pkg', 'item_pkg_cover',
            'verified_data',
        ];

        $products = ProductMaster::query()
            ->whereIn('sku', $skus)
            ->get(['id', 'sku', 'parent', 'Values', 'main_image', 'image1']);

        $shopifyBySku = [];
        if (Schema::hasTable('shopify_skus')) {
            $shopifyBySku = ShopifySku::query()
                ->whereIn('sku', $skus)
                ->get(['sku', 'image_src'])
                ->keyBy(function ($item) {
                    return strtoupper(trim(str_replace("\u{00a0}", ' ', (string) $item->sku)));
                });
        }

        $result = [];
        foreach ($products as $product) {
            $values = $product->Values;
            if (is_string($values)) {
                $values = json_decode($values, true);
            }
            if (! is_array($values)) {
                $values = [];
            }

            $labelType = isset($values['label_type']) ? trim((string) $values['label_type']) : '';
            if (! in_array($labelType, $allowedLabelTypes, true)) {
                $labelType = 'STD';
            }

            $dimWt = [];
            foreach ($dimKeys as $key) {
                if (array_key_exists($key, $values)) {
                    $dimWt[$key] = $values[$key];
                }
            }

            $skuKey = strtoupper(trim(str_replace("\u{00a0}", ' ', (string) $product->sku)));
            $shopifyImage = $shopifyBySku[$skuKey]->image_src ?? null;

            $result[$skuKey] = [
                'product_id' => $product->id,
                'parent' => $product->parent,
                'ship' => $values['ship'] ?? null,
                'image' => $this->normalizeImageUrl($shopifyImage)
                    ?? $this->normalizeImageUrl($values['image_path'] ?? null)
                    ?? $this->normalizeImageUrl($product->main_image ?? null)
                    ?? $this->normalizeImageUrl($product->image1 ?? null),
                'label_type' => $labelType,
                'dim_wt' => $dimWt,
            ];
        }

        return $result;
    }

    private function normalizeImageUrl(mixed $path): ?string
    {
        $p = trim((string) ($path ?? ''));
        if ($p === '') {
            return null;
        }
        if (preg_match('/^(https?:)?\/\//i', $p) || str_starts_with($p, 'data:')) {
            return $p;
        }

        return '/'.ltrim($p, '/');
    }
}
