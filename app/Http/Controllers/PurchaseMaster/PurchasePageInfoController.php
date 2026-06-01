<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Services\PurchasePageInfoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchasePageInfoController extends Controller
{
    public function show(string $pageKey, PurchasePageInfoService $service): JsonResponse
    {
        try {
            return response()->json($service->pagePayload($pageKey));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function update(Request $request, string $pageKey, PurchasePageInfoService $service): JsonResponse
    {
        if (! PurchasePageInfoService::userCanEdit()) {
            return response()->json(['message' => 'You are not allowed to edit page information.'], 403);
        }

        $validated = $request->validate([
            'html_content' => 'nullable|string',
        ]);

        try {
            $service->saveHtml($pageKey, $validated['html_content'] ?? null);

            return response()->json([
                'success' => true,
                'message' => 'Page information saved.',
                'data' => $service->pagePayload($pageKey),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
