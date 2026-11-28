<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Services\ShopifyB2cPromotionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * /promotions → Shopify B2C: percentage-off as a real Shopify automatic discount (editable %, start/end).
 * preview() writes nothing; apply / update / deactivate need confirm=1.
 */
class ShopifyB2cPromotionsController extends Controller
{
    public function __construct(private ShopifyB2cPromotionService $service)
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

    private function discountId(Request $request): ?string
    {
        $id = trim((string) $request->input('discount_id', ''));

        return preg_match('#^gid://shopify/DiscountAutomaticNode/\d+$#', $id) ? $id : null;
    }

    public function activities()
    {
        try {
            return response()->json(['success' => true, 'discounts' => $this->service->list()]);
        } catch (\Throwable $e) {
            return $this->bad($e->getMessage(), 502);
        }
    }

    public function preview(Request $request)
    {
        $pct = $this->pct($request);
        if ($pct === null) {
            return $this->bad('pct must be between 1 and 90');
        }
        $skus = $this->skuList($request);
        if ($skus === [] || count($skus) > ShopifyB2cPromotionService::MAX_SKUS) {
            return $this->bad('Enter 1 to '.ShopifyB2cPromotionService::MAX_SKUS.' SKUs');
        }
        $rows = $this->service->plan($skus, $pct, $request->boolean('allow_below_cost'));
        $ready = count(array_filter($rows, fn ($r) => $r['status'] === 'ready'));

        return response()->json(['success' => true, 'pct' => $pct, 'ready' => $ready, 'skipped' => count($rows) - $ready, 'rows' => $rows]);
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
        if ($skus === [] || count($skus) > ShopifyB2cPromotionService::MAX_SKUS) {
            return $this->bad('Enter 1 to '.ShopifyB2cPromotionService::MAX_SKUS.' SKUs');
        }
        $begin = (int) $request->input('begin_time', 0) ?: time() + 60;
        $end = (int) $request->input('end_time', 0);
        if ($end > 0 && $end < $begin + 600) {
            return $this->bad('End time must be at least 10 minutes after the start (or leave it empty for no end).');
        }
        $rows = $this->service->plan($skus, $pct, $request->boolean('allow_below_cost'));
        $ready = array_values(array_filter($rows, fn ($r) => $r['status'] === 'ready'));
        if ($ready === []) {
            return response()->json(['success' => false, 'message' => 'No SKU is ready to discount.', 'rows' => $rows], 422);
        }
        $res = $this->service->create($ready, $pct, $begin, $end > 0 ? $end : null, (string) $request->input('title', ''));
        Log::info('Promotions Shopify B2C apply', [
            'pct' => $pct, 'skus' => count($ready), 'ok' => $res['success'], 'msg' => $res['message'],
            'discount_id' => $res['discount_id'] ?? null, 'user' => optional($request->user())->email,
        ]);
        if (! $res['success']) {
            return response()->json(['success' => false, 'message' => 'Shopify: '.$res['message'], 'rows' => $rows], 502);
        }

        return response()->json([
            'success' => true,
            'discount_id' => $res['discount_id'],
            'applied' => count($ready),
            'skipped' => count($rows) - count($ready),
            'rows' => $rows,
            'message' => count($ready).' SKU(s) at '.$pct.'% off created on Shopify.',
        ]);
    }

    public function update(Request $request)
    {
        if (! $request->boolean('confirm')) {
            return $this->bad('Confirmation required.');
        }
        $id = $this->discountId($request);
        $pct = $this->pct($request);
        if ($id === null) {
            return $this->bad('Select one of our discounts.');
        }
        if ($pct === null) {
            return $this->bad('pct must be between 1 and 90');
        }
        $res = $this->service->updatePercent($id, $pct);
        Log::info('Promotions Shopify B2C update %', ['discount_id' => $id, 'pct' => $pct, 'ok' => $res['success'], 'msg' => $res['message'], 'user' => optional($request->user())->email]);

        return response()->json($res + ['discount_id' => $id], $res['success'] ? 200 : 502);
    }

    public function deactivate(Request $request)
    {
        if (! $request->boolean('confirm')) {
            return $this->bad('Confirmation required.');
        }
        $id = $this->discountId($request);
        if ($id === null) {
            return $this->bad('Select one of our discounts.');
        }
        $res = $this->service->deactivate($id);
        Log::info('Promotions Shopify B2C deactivate', ['discount_id' => $id, 'ok' => $res['success'], 'msg' => $res['message'], 'user' => optional($request->user())->email]);

        return response()->json($res + ['discount_id' => $id], $res['success'] ? 200 : 502);
    }
}
