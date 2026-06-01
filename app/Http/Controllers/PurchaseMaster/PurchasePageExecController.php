<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Services\PurchasePageExecService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchasePageExecController extends Controller
{
    public function show(string $pageKey, PurchasePageExecService $service): JsonResponse
    {
        try {
            return response()->json($service->pagePayload($pageKey));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function updateAssignment(Request $request, string $pageKey, PurchasePageExecService $service): JsonResponse
    {
        if (! PurchasePageExecService::userCanEdit()) {
            return response()->json(['message' => 'You are not allowed to edit page executive assignments.'], 403);
        }

        $validated = $request->validate([
            'assigned_exec' => 'nullable|string|max:64',
        ]);

        try {
            $service->setAssignment($pageKey, $validated['assigned_exec'] ?? null);

            return response()->json([
                'success' => true,
                'message' => 'Executive assignment saved.',
                'data' => $service->pagePayload($pageKey),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function storeOption(Request $request, PurchasePageExecService $service): JsonResponse
    {
        if (! PurchasePageExecService::userCanEdit()) {
            return response()->json(['message' => 'You are not allowed to edit executive options.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:64',
        ]);

        try {
            $service->addOption($validated['name']);

            return response()->json([
                'success' => true,
                'message' => 'Executive option added.',
                'options' => $service->getOptions(),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
