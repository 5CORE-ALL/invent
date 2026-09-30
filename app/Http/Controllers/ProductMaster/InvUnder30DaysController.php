<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProductMaster\ProductMasterController as PMController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * "Inv<30 Days": CP Master rows with INV > 0 and DIL > 100% (OVL30 exceeds INV,
 * i.e. inventory will not last the next 30 days at the current sales rate).
 */
class InvUnder30DaysController extends Controller
{
    public const DIL_THRESHOLD = 100;

    public function index(Request $request)
    {
        $mode = $request->query('mode', '');
        $demo = $request->query('demo', '');

        return view('inv-under-30-days', compact('mode', 'demo'));
    }

    public function getData(Request $request)
    {
        try {
            $baseResponse = app(PMController::class)->getViewProductData($request);
            $baseData = $baseResponse->getData(true);
            $products = $baseData['data'] ?? [];

            $rows = [];
            foreach ($products as $product) {
                $sku = trim((string) ($product['SKU'] ?? ''));
                if ($sku === '' || stripos($sku, 'PARENT') === 0) {
                    continue;
                }

                $inv = (float) ($product['shopify_inv'] ?? 0);
                $ovl30 = (float) ($product['shopify_quantity'] ?? 0);
                if ($inv <= 0) {
                    continue;
                }

                $dil = self::dilPercent($inv, $ovl30);
                if ($dil === null || $dil <= self::DIL_THRESHOLD) {
                    continue;
                }

                $rows[] = [
                    'id' => $product['id'] ?? null,
                    'image' => $product['image_path'] ?? null,
                    'parent' => $product['Parent'] ?? '',
                    'sku' => $sku,
                    'inv' => $inv,
                    'ovl30' => $ovl30,
                    'dil' => $dil,
                    'days_exp' => self::daysExp($inv, $ovl30),
                ];
            }

            usort($rows, static fn (array $a, array $b) => $b['dil'] <=> $a['dil']);

            return response()->json([
                'status' => 200,
                'dil_threshold' => self::DIL_THRESHOLD,
                'data' => array_values($rows),
            ]);
        } catch (\Throwable $e) {
            Log::error('Inv<30 Days data failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'status' => 500,
                'message' => 'Unable to load Inv<30 Days data.',
                'data' => [],
            ], 500);
        }
    }

    /**
     * Same DIL formula as CP Master: round(OVL30 / INV * 100). Null when INV is 0.
     */
    public static function dilPercent(float $inv, float $ovl30): ?int
    {
        if ($inv <= 0) {
            return null;
        }

        return (int) round(($ovl30 / $inv) * 100);
    }

    /**
     * Days Exp = (INV / OVL30) * 30 — days the current inventory lasts at the L30 sales rate.
     */
    public static function daysExp(float $inv, float $ovl30): ?int
    {
        if ($ovl30 <= 0) {
            return null;
        }

        return (int) round(($inv / $ovl30) * 30);
    }
}
