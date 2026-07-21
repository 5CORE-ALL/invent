<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QualityEnhanceController extends Controller
{
    public function index(){
        return view('purchase-master.quality-enhance');
    }

    public function getData(Request $request)
    {
        $data = DB::table('quality_enhance as qe')
            ->leftJoin('product_master as pm', 'qe.sku', '=', 'pm.sku')
            ->select('qe.id', 'qe.sku', 'qe.values', 'qe.created_at', 'pm.parent')
            ->get();

        $formattedData = $data->map(function($item) {
            $values = json_decode($item->values, true);
            return [
                'id' => $item->id,
                'sku' => $item->sku,
                'parent' => $item->parent ?? '',
                'issue' => $values['issue'] ?? '',
                'action_req' => $values['action_req'] ?? '',
                'status_remark' => $values['status_remark'] ?? '',
                'created_at' => $item->created_at,
            ];
        });

        return response()->json($formattedData);
    }

    public function update(Request $request)
    {
        $id = $request->input('id');
        $field = $request->input('field');
        $value = $request->input('value');

        $record = DB::table('quality_enhance')->where('id', $id)->first();

        if (!$record) {
            return response()->json(['error' => 'Record not found'], 404);
        }

        $values = json_decode($record->values, true);

        if (in_array($field, ['issue', 'action_req', 'status_remark'])) {
            $values[$field] = $value;
            DB::table('quality_enhance')->where('id', $id)->update([
                'values' => json_encode($values)
            ]);
        }

        return response()->json(['success' => true]);
    }


    public function saveQualityEnhance(Request $request)
    {
        $skuArray = $request->input('sku');
        $issueArray = $request->input('issue');
        $actionReqArray = $request->input('action_req');
        $statusRemarkArray = $request->input('status_remark');

        if (!$skuArray || !is_array($skuArray)) {
            return back()->with('error', 'No data to save.');
        }

        foreach ($skuArray as $index => $sku) {
            if (empty($sku)) continue; // Skip if SKU is empty

            $jsonData = [
                'issue' => $issueArray[$index] ?? '',
                'action_req' => $actionReqArray[$index] ?? '',
                'status_remark' => $statusRemarkArray[$index] ?? '',
            ];

            // Insert into DB
            DB::table('quality_enhance')->insert([
                'sku' => $sku,
                'values' => json_encode($jsonData),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return back()->with('flash_message', 'Data saved successfully!');
    }

    public function getParentFromSKU(Request $request)
    {
        $sku = $request->input('sku');
        if (!$sku) {
            return response()->json(['success' => false, 'message' => 'SKU is required']);
        }

        $parent = DB::table('product_master')->where('sku', $sku)->value('parent');

        if ($parent) {
            return response()->json(['success' => true, 'parent' => $parent]);
        } else {
            return response()->json(['success' => false, 'message' => 'Parent not found']);
        }
    }

    /**
     * Create or update quality_enhance values for one SKU (used by QC & Packing modal).
     */
    public function upsertBySku(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:255',
            'issue' => 'nullable|string|max:2000',
            'action_req' => 'nullable|string|max:2000',
            'status_remark' => 'nullable|string|max:2000',
        ]);

        $sku = trim((string) $validated['sku']);
        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU is required.'], 422);
        }

        $values = [
            'issue' => trim((string) ($validated['issue'] ?? '')),
            'action_req' => trim((string) ($validated['action_req'] ?? '')),
            'status_remark' => trim((string) ($validated['status_remark'] ?? '')),
        ];

        $existing = DB::table('quality_enhance')
            ->whereRaw('LOWER(TRIM(sku)) = ?', [strtolower($sku)])
            ->first();

        $allEmpty = $values['issue'] === '' && $values['action_req'] === '' && $values['status_remark'] === '';

        if ($allEmpty) {
            if ($existing) {
                DB::table('quality_enhance')->where('id', $existing->id)->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'Cleared.',
                'issue' => '',
                'action_req' => '',
                'status_remark' => '',
            ]);
        }

        if ($existing) {
            DB::table('quality_enhance')->where('id', $existing->id)->update([
                'values' => json_encode($values),
                'updated_at' => now(),
            ]);
            $id = (int) $existing->id;
        } else {
            $id = (int) DB::table('quality_enhance')->insertGetId([
                'sku' => $sku,
                'values' => json_encode($values),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Saved.',
            'id' => $id,
            'issue' => $values['issue'],
            'action_req' => $values['action_req'],
            'status_remark' => $values['status_remark'],
        ]);
    }
}
