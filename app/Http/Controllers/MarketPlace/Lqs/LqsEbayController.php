<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

use App\Services\Lqs\LqsListingAuditService;
use Illuminate\Http\Request;

class LqsEbayController extends LqsMarketplaceBaseController
{
    protected function slug(): string
    {
        return 'ebay';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.ebay';
    }

    public function auditPrompt(LqsListingAuditService $audit)
    {
        try {
            $saved = $audit->getOrCreatePrompt($this->slug());

            return response()->json([
                'success' => true,
                'prompt' => $saved['prompt'],
                'is_default' => $saved['is_default'],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Failed to load audit prompt.'], 500);
        }
    }

    public function saveAuditPrompt(Request $request, LqsListingAuditService $audit)
    {
        $request->validate([
            'prompt' => 'required|string|min:20',
        ]);

        try {
            $prompt = $audit->savePrompt($this->slug(), $request->input('prompt'));

            return response()->json([
                'success' => true,
                'prompt' => $prompt,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Failed to save prompt.'], 500);
        }
    }

    public function runAudit(Request $request, LqsListingAuditService $audit)
    {
        $request->validate([
            'sku' => 'required|string',
            'prompt' => 'nullable|string',
        ]);

        try {
            $result = $audit->run($this->slug(), $request->input('sku'), $request->input('prompt'));

            return response()->json(['success' => true] + $result);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Audit failed.',
            ], 500);
        }
    }
}
