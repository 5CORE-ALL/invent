<?php

namespace App\Http\Controllers\InventoryManagement;

use App\Http\Controllers\Controller;
use App\Services\Inventory\Inv5coreHubService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InvManagement5CoreController extends Controller
{
    public function __construct(private Inv5coreHubService $hub) {}

    public function index(Request $request)
    {
        $mode = $request->query('mode', '');
        $demo = $request->query('demo', '');

        return view('inventory-management.inv-management-5core', compact('mode', 'demo'));
    }

    public function data()
    {
        try {
            $payload = $this->hub->rows();

            return response()->json([
                'status' => 200,
                'data' => $payload['rows'],
                'meta' => $payload['meta'],
            ]);
        } catch (\Throwable $e) {
            Log::error('INV Management 5Core data failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'status' => 500,
                'message' => 'Unable to load INV Management 5Core.',
                'data' => [],
            ], 500);
        }
    }

    public function seed(Request $request)
    {
        @set_time_limit(0);

        try {
            $result = $this->hub->seedOpening($request->user()?->id);

            return response()->json([
                'status' => 200,
                'message' => 'Opening inventory saved for '.$result['seeded'].' SKU'
                    .($result['seeded'] === 1 ? '' : 's')
                    .'. '.$result['skipped'].' already seeded and left unchanged.',
                'seeded' => $result['seeded'],
                'skipped' => $result['skipped'],
            ]);
        } catch (\Throwable $e) {
            Log::error('INV Management 5Core seed failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'status' => 500,
                'message' => 'Unable to seed opening inventory.',
            ], 500);
        }
    }

    public function recordSales(Request $request)
    {
        @set_time_limit(0);

        try {
            $result = $this->hub->recordAppSales($request->user()?->id);
            $message = 'Recorded '.$result['posted'].' app sale'
                .($result['posted'] === 1 ? '' : 's');
            if ($result['reversed'] > 0) {
                $message .= ' and reversed '.$result['reversed'].' cancelled or refunded line'
                    .($result['reversed'] === 1 ? '' : 's');
            }
            $message .= '.';

            return response()->json([
                'status' => 200,
                'message' => $message,
                'posted' => $result['posted'],
                'reversed' => $result['reversed'],
            ]);
        } catch (\Throwable $e) {
            Log::error('INV Management 5Core sales post failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'status' => 500,
                'message' => 'Unable to record app sales.',
            ], 500);
        }
    }

    public function adjust(Request $request)
    {
        $data = $request->validate([
            'sku' => 'required|string|max:191',
            'txn_type' => 'required|in:adjustment,incoming,return,write_off',
            'mode' => 'required|in:set,add,subtract',
            'qty' => 'required|numeric|min:0',
            'detail' => 'required|string|max:2000',
        ]);

        try {
            $result = $this->hub->adjust(
                $data['sku'],
                $data['txn_type'],
                $data['mode'],
                (float) $data['qty'],
                $data['detail'],
                $request->user()?->id
            );

            return response()->json([
                'status' => 200,
                'message' => 'Adjustment saved.',
                'sku' => trim($data['sku']),
                'inv_app' => $result['inv_app'],
                'qty_delta' => $result['qty_delta'],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('INV Management 5Core adjust failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json(['message' => 'Unable to save the adjustment.'], 500);
        }
    }

    public function history(Request $request)
    {
        $sku = trim((string) $request->query('sku', ''));
        if ($sku === '') {
            return response()->json(['message' => 'SKU is required.'], 422);
        }

        try {
            $payload = $this->hub->history($sku);
            $balance = $payload['balance'];

            return response()->json([
                'status' => 200,
                'sku' => $sku,
                'seeded' => (bool) ($balance?->shopify_locked),
                'opening_qty' => $balance?->opening_qty,
                'opening_seeded_at' => $balance?->opening_seeded_at?->format('Y-m-d H:i'),
                'inv_app' => ($balance && $balance->shopify_locked) ? $balance->qty_on_hand : null,
                'rows' => $payload['rows'],
            ]);
        } catch (\Throwable $e) {
            Log::error('INV Management 5Core history failed: '.$e->getMessage());

            return response()->json(['message' => 'Unable to load adjustment history.'], 500);
        }
    }
}
