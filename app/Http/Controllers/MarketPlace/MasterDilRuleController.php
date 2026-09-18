<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Support\AmazonDilGroiRule;
use App\Support\MasterDilGroiSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MasterDilRuleController extends Controller
{
    public function index(): View
    {
        return view('market-places.master_dil_rules');
    }

    public function data(): JsonResponse
    {
        $snap = MasterDilGroiSync::snapshot();

        return response()->json([
            'success' => true,
            'is_default' => $snap['is_default'],
            'target_metric' => 'nroi',
            'rules' => $snap['rules'],
            'channels' => $snap['channels'],
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        $incoming = $request->input('rules');
        if (! is_array($incoming) && is_string($request->input('rules'))) {
            $decoded = json_decode((string) $request->input('rules'), true);
            $incoming = is_array($decoded) ? $decoded : null;
        }
        if (! is_array($incoming)) {
            return response()->json(['success' => false, 'message' => 'rules array required'], 422);
        }

        $rules = AmazonDilGroiRule::normalizeList($incoming);
        if ($rules === []) {
            return response()->json(['success' => false, 'message' => 'At least one Dil slab is required'], 422);
        }

        $written = MasterDilGroiSync::writeFull($rules);
        $snap = MasterDilGroiSync::snapshot();

        return response()->json([
            'success' => true,
            'target_metric' => 'nroi',
            'rules' => $snap['rules'],
            'channels' => $snap['channels'],
            'updated' => $written['updated'],
        ]);
    }
}
