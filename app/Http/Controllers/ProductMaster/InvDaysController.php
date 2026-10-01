<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProductMaster\ProductMasterController as PMController;
use App\Models\AmazonDatasheet;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * "Inv Days": every CP Master SKU from the same product table as Inv<30 Days,
 * with Days Exp = (INV / OVL30) * 30, sorted highest to lowest.
 */
class InvDaysController extends Controller
{
    public function index(Request $request)
    {
        $mode = $request->query('mode', '');
        $demo = $request->query('demo', '');

        return view('inv-days', compact('mode', 'demo'));
    }

    public function getData(Request $request)
    {
        try {
            $baseResponse = app(PMController::class)->getViewProductData($request);
            $baseData = $baseResponse->getData(true);
            $products = $baseData['data'] ?? [];

            $ageBySku = $this->incomingAgeDaysBySku();
            $amazonSheets = $this->amazonSheetsByLookupKey();
            $clearanceBySku = $this->clearanceBySku();
            $nrpBySku = $this->forecastNrpBySku();

            $rows = [];
            foreach ($products as $product) {
                $sku = trim((string) ($product['SKU'] ?? ''));
                if ($sku === '' || stripos($sku, 'PARENT') === 0) {
                    continue;
                }

                $inv = (float) ($product['shopify_inv'] ?? 0);
                $ovl30 = (float) ($product['shopify_quantity'] ?? 0);
                $salePrice = $this->amazonSalePrice($sku, $amazonSheets);
                $clearance = $clearanceBySku[$this->skuKey($sku)] ?? null;

                $rows[] = [
                    'id' => $product['id'] ?? null,
                    'image' => $product['image_path'] ?? null,
                    'parent' => $product['Parent'] ?? '',
                    'sku' => $sku,
                    'inv' => $inv,
                    'inv_value' => $salePrice !== null ? (int) round($salePrice * $inv) : null,
                    'ovl30' => $ovl30,
                    'dil' => InvUnder30DaysController::dilPercent($inv, $ovl30),
                    'age_days' => $ageBySku[$this->skuKey($sku)] ?? null,
                    'days_exp' => InvUnder30DaysController::daysExp($inv, $ovl30) ?? 99999,
                    'clearance' => $clearance['value'] ?? 'NO',
                    'clearance_has_history' => $clearance !== null,
                    'nr' => $nrpBySku[$this->forecastSkuKey($sku)] ?? 'REQ',
                ];
            }

            usort($rows, static function (array $a, array $b): int {
                $ad = $a['days_exp'];
                $bd = $b['days_exp'];
                // Missing Days Exp is stored as 99999 and sorts as that number.
                if ($ad === null && $bd === null) {
                    return 0;
                }
                if ($ad === null) {
                    return -1;
                }
                if ($bd === null) {
                    return 1;
                }

                return $bd <=> $ad;
            });

            return response()->json([
                'status' => 200,
                'data' => array_values($rows),
            ]);
        } catch (\Throwable $e) {
            Log::error('Inv Days data failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'status' => 500,
                'message' => 'Unable to load Inv Days data.',
                'data' => [],
            ], 500);
        }
    }

    public function toggleClearance(Request $request)
    {
        $sku = trim((string) $request->input('sku', ''));
        if ($sku === '' || stripos($sku, 'PARENT') === 0) {
            return response()->json(['message' => 'SKU is required.'], 422);
        }

        $key = $this->skuKey($sku);
        $user = $request->user();
        $name = trim((string) ($user->name ?? ''));
        if ($name === '') {
            $name = trim((string) ($user->email ?? 'Unknown'));
        }

        try {
            $saved = DB::transaction(function () use ($sku, $key, $user, $name) {
                $current = DB::table('inv_days_clearances')->where('sku_key', $key)->lockForUpdate()->first();
                $from = ($current && strtoupper((string) $current->value) === 'YES') ? 'YES' : 'NO';
                $to = $from === 'YES' ? 'NO' : 'YES';
                $now = now();

                if ($current) {
                    DB::table('inv_days_clearances')->where('id', $current->id)->update([
                        'sku' => $sku,
                        'value' => $to,
                        'updated_at' => $now,
                    ]);
                } else {
                    DB::table('inv_days_clearances')->insert([
                        'sku_key' => $key,
                        'sku' => $sku,
                        'value' => $to,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                DB::table('inv_days_clearance_logs')->insert([
                    'sku_key' => $key,
                    'sku' => $sku,
                    'from_value' => $from,
                    'to_value' => $to,
                    'changed_by' => $name,
                    'user_id' => $user?->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                return $to;
            });
        } catch (\Throwable $e) {
            Log::error('Inv Days clearance toggle failed: '.$e->getMessage());

            return response()->json(['message' => 'Unable to save clearance.'], 500);
        }

        return response()->json([
            'status' => 200,
            'clearance' => $saved,
            'clearance_has_history' => true,
        ]);
    }

    public function clearanceHistory(Request $request)
    {
        $sku = trim((string) $request->query('sku', ''));
        if ($sku === '') {
            return response()->json(['message' => 'SKU is required.', 'data' => []], 422);
        }

        if (! Schema::hasTable('inv_days_clearance_logs')) {
            return response()->json(['status' => 200, 'sku' => $sku, 'data' => []]);
        }

        $rows = DB::table('inv_days_clearance_logs')
            ->where('sku_key', $this->skuKey($sku))
            ->orderByDesc('id')
            ->limit(100)
            ->get(['from_value', 'to_value', 'changed_by', 'created_at']);

        $data = $rows->map(function ($row) {
            $at = $row->created_at;
            try {
                $at = $at ? Carbon::parse($at)->timezone('America/New_York')->format('j M Y, g:i A') : null;
            } catch (\Throwable $e) {
                $at = (string) $row->created_at;
            }

            return [
                'from_value' => $row->from_value,
                'to_value' => $row->to_value,
                'changed_by' => $row->changed_by,
                'created_at' => $at,
            ];
        })->values();

        return response()->json([
            'status' => 200,
            'sku' => $sku,
            'data' => $data,
        ]);
    }

    /**
     * NRP from forecast_analysis.nr, matched the same way as /forecast.analysis.
     * REQ = green, NR (2BDC) = red, LATER = yellow. Missing rows display as REQ.
     *
     * @return array<string, string>
     */
    private function forecastNrpBySku(): array
    {
        if (! Schema::hasTable('forecast_analysis')) {
            return [];
        }

        $query = DB::table('forecast_analysis')->whereNotNull('sku');
        if (Schema::hasColumn('forecast_analysis', 'archived_at')) {
            $query->whereNull('archived_at');
        }

        $grouped = [];
        foreach ($query->get(['sku', 'nr', 'stage']) as $row) {
            $key = $this->forecastSkuKey((string) $row->sku);
            if ($key === '') {
                continue;
            }
            $grouped[$key][] = $row;
        }

        $map = [];
        foreach ($grouped as $key => $group) {
            $picked = null;
            foreach ($group as $row) {
                if (trim((string) ($row->stage ?? '')) !== '') {
                    $picked = $row;
                    break;
                }
            }
            if (! $picked) {
                foreach ($group as $row) {
                    if (trim((string) ($row->nr ?? '')) !== '') {
                        $picked = $row;
                        break;
                    }
                }
            }
            $picked = $picked ?? $group[0];
            $nr = strtoupper(trim((string) ($picked->nr ?? '')));
            if (! in_array($nr, ['REQ', 'NR', 'LATER'], true)) {
                $nr = 'REQ';
            }
            $map[$key] = $nr;
        }

        return $map;
    }

    private function forecastSkuKey(string $sku): string
    {
        $sku = strtoupper(trim($sku));
        $sku = preg_replace('/\s+/u', ' ', $sku) ?? $sku;
        $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku) ?? $sku;

        return trim($sku);
    }

    /**
     * @return array<string, array{value: string}>
     */
    private function clearanceBySku(): array
    {
        if (! Schema::hasTable('inv_days_clearances')) {
            return [];
        }

        $map = [];
        foreach (DB::table('inv_days_clearances')->get(['sku_key', 'value']) as $row) {
            $key = (string) $row->sku_key;
            if ($key === '') {
                continue;
            }
            $map[$key] = [
                'value' => strtoupper((string) $row->value) === 'YES' ? 'YES' : 'NO',
            ];
        }

        return $map;
    }

    /**
     * Amazon listing sale price from amazon_datsheets.price, matched the same way as the Amazon page.
     *
     * @param  \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, AmazonDatasheet>>  $amazonSheets
     */
    private function amazonSalePrice(string $sku, $amazonSheets): ?float
    {
        $key = AmazonDatasheet::normalizeSkuForLookup($sku);
        if ($key === '') {
            return null;
        }

        $sheet = AmazonDatasheet::pickBestForProductSku($sku, $amazonSheets->get($key));
        if (! $sheet || ! is_numeric($sheet->price)) {
            return null;
        }

        $price = (float) $sheet->price;

        return $price > 0 ? $price : null;
    }

    /**
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, AmazonDatasheet>>
     */
    private function amazonSheetsByLookupKey()
    {
        return AmazonDatasheet::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->get(['id', 'sku', 'price', 'updated_at'])
            ->groupBy(fn ($row) => AmazonDatasheet::normalizeSkuForLookup((string) $row->sku));
    }

    /**
     * Whole days from the earliest incoming date to today.
     * Uses incoming and incoming-return receipts first, then the product created date
     * when a SKU was never received through Incoming.
     *
     * @return array<string, int>
     */
    private function incomingAgeDaysBySku(): array
    {
        $dates = [];

        $receipts = DB::table('inventories')
            ->whereIn('type', ['incoming', 'incoming_return'])
            ->whereNotNull('sku')
            ->get(['sku', 'approved_at', 'created_at']);

        foreach ($receipts as $row) {
            $at = $row->approved_at ?: $row->created_at;
            $this->keepEarliestDate($dates, (string) $row->sku, $at);
        }

        $products = DB::table('product_master')
            ->whereNull('deleted_at')
            ->whereNotNull('sku')
            ->get(['sku', 'created_at']);

        foreach ($products as $row) {
            $key = $this->skuKey((string) $row->sku);
            if ($key === '' || isset($dates[$key])) {
                continue;
            }
            $this->keepEarliestDate($dates, (string) $row->sku, $row->created_at);
        }

        $today = Carbon::now('America/New_York')->startOfDay();
        $map = [];

        foreach ($dates as $key => $incomingAt) {
            try {
                $start = Carbon::parse($incomingAt)->timezone('America/New_York')->startOfDay();
            } catch (\Throwable $e) {
                continue;
            }

            $map[$key] = $start->greaterThan($today)
                ? 0
                : (int) abs($start->diffInDays($today));
        }

        return $map;
    }

    private function skuKey(string $sku): string
    {
        $sku = str_replace("\u{00a0}", ' ', trim($sku));
        $sku = preg_replace('/\s+/u', ' ', $sku) ?? $sku;

        return strtolower($sku);
    }

    /**
     * @param  array<string, string>  $dates
     */
    private function keepEarliestDate(array &$dates, string $sku, mixed $at): void
    {
        $key = $this->skuKey($sku);
        if ($key === '' || $at === null || $at === '') {
            return;
        }

        $at = (string) $at;
        if (! isset($dates[$key]) || $at < $dates[$key]) {
            $dates[$key] = $at;
        }
    }
}
