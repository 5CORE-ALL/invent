<?php

namespace App\Http\Controllers;

use App\Services\GoogleSheetsSyncService;
use Illuminate\Http\Request;

class GoogleSheetsController extends Controller
{
    protected $sheetService;

    public function __construct(GoogleSheetsSyncService $sheetService)
    {
        $this->sheetService = $sheetService;
    }

    public function syncAllSheets(Request $request)
    {
        try {
            $validated = $this->validateRequest($request);
            $results = $this->sheetService->processSequentially($validated);

            return response()->json([
                'success' => $this->allSheetsSuccessful($results),
                'results' => $results,
                'stats' => $this->calculateStats($results)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'operation' => 'required|string',
            'original_sku' => 'nullable|string',
            'original_parent' => 'nullable|string',
            'SKU' => 'required|string',
            'Parent' => 'nullable|string',
            'Label_QTY' => 'required|numeric',
            'CP' => 'required|numeric',
            'SHIP' => 'required|numeric',
            'WT_ACT' => 'required|numeric',
            'WT_DECL' => 'required|numeric',
            'W' => 'required|numeric',
            'L' => 'required|numeric',
            'H' => 'required|numeric',
            '5C' => 'nullable|string',
            'UPC' => 'nullable|string'
        ]);
    }

    private function allSheetsSuccessful(array $results): bool
    {
        foreach ($results as $result) {
            if (!$result['success']) {
                return false;
            }
        }
        return true;
    }

    private function calculateStats(array $results): array
    {
        $total = count($results);
        $success = count(array_filter($results, fn($r) => $r['success']));
        
        return [
            'total' => $total,
            'success' => $success,
            'failed' => $total - $success,
            'completion_rate' => $total > 0 ? round(($success / $total) * 100, 2) : 0
        ];
    }
}