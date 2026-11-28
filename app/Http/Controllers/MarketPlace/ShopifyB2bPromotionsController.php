<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Services\ShopifyB2bPromotionService;
use Illuminate\Http\Request;

/**
 * /promotions → Shopify B2B (business5core.com): percentage-off as a lower special price (editable %, optional end).
 * NOTHING IS PUSHED to Shopify / the store from here. preview() saves nothing; apply() and remove() (confirm=1) only change
 * the saved S PRC in the app.
 */
class ShopifyB2bPromotionsController extends Controller
{
    public function __construct(private ShopifyB2bPromotionService $service)
    {
    }

    private function bad(string $message, int $status = 422)
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }

    /** @return list<string> */
    private function skuList(Request $request): array
    {
        $raw = $request->input('skus', []);
        if (is_string($raw)) {
            $raw = preg_split('/[\r\n,;]+/', $raw) ?: [];
        }
        $out = [];
        foreach ((array) $raw as $s) {
            $s = trim((string) $s);
            if ($s !== '') {
                $out[strtoupper($s)] = $s;
            }
        }

        return array_values($out);
    }

    private function pct(Request $request): ?float
    {
        $pct = $request->input('pct');
        if (! is_numeric($pct)) {
            return null;
        }
        $pct = round((float) $pct, 2);

        return ($pct >= 1 && $pct <= 90) ? $pct : null;
    }

    public function active()
    {
        return response()->json(['success' => true, 'promotions' => $this->service->active()]);
    }

    public function preview(Request $request)
    {
        $pct = $this->pct($request);
        if ($pct === null) {
            return $this->bad('pct must be between 1 and 90');
        }
        $skus = $this->skuList($request);
        if ($skus === [] || count($skus) > ShopifyB2bPromotionService::MAX_SKUS) {
            return $this->bad('Enter 1 to '.ShopifyB2bPromotionService::MAX_SKUS.' SKUs');
        }
        $rows = $this->service->plan($skus, $pct, $request->boolean('allow_below_cost'));
        $ready = count(array_filter($rows, fn ($r) => $r['status'] === 'ready'));

        return response()->json([
            'success' => true,
            'pct' => $pct,
            'ready' => $ready,
            'skipped' => count($rows) - $ready,
            'rows' => $rows,
        ]);
    }

    public function apply(Request $request)
    {
        if (! $request->boolean('confirm')) {
            return $this->bad('Confirmation required.');
        }
        $pct = $this->pct($request);
        if ($pct === null) {
            return $this->bad('pct must be between 1 and 90');
        }
        $skus = $this->skuList($request);
        if ($skus === [] || count($skus) > ShopifyB2bPromotionService::MAX_SKUS) {
            return $this->bad('Enter 1 to '.ShopifyB2bPromotionService::MAX_SKUS.' SKUs');
        }
        $end = (int) $request->input('end_time', 0);
        if ($end > 0 && $end < time() + 600) {
            return $this->bad('End time must be at least 10 minutes from now (or leave it empty for no end).');
        }

        $res = $this->service->apply($skus, $pct, $end > 0 ? $end : null, $request->boolean('allow_below_cost'), optional($request->user())->email);

        return response()->json($res, $res['success'] ? 200 : 422);
    }

    public function remove(Request $request)
    {
        if (! $request->boolean('confirm')) {
            return $this->bad('Confirmation required.');
        }
        $skus = $this->skuList($request);
        if ($skus === [] || count($skus) > ShopifyB2bPromotionService::MAX_SKUS) {
            return $this->bad('Select 1 to '.ShopifyB2bPromotionService::MAX_SKUS.' SKUs');
        }
        $res = $this->service->remove($skus, optional($request->user())->email);

        return response()->json($res, $res['success'] ? 200 : 422);
    }
}
