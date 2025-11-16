<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\Sourcing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SourcingController extends Controller
{
    public function index(){
        return view('purchase-master.sourcing.index');
    }

    public function getSourcingData()
    {
        $sourcings = Sourcing::all();
        return response()->json($sourcings);
    }

    public function storeSourcing(Request $request)
    {
        $request->validate([
            'target_item' => 'required|string|max:255',
            'target_link1' => 'nullable|url',
            'product_description' => 'nullable|string',
            'rfq_form' => 'nullable|url',
            'rfq_report' => 'nullable|url',
            'status' => 'required|in:hold,working,done',
        ]);

        Sourcing::create([
            'target_item' => $request->target_item,
            'target_link1' => $request->target_link1,
            'target_link2' => $request->target_link2,
            'product_description' => $request->product_description,
            'rfq_form' => $request->rfq_form,
            'rfq_report' => $request->rfq_report,
            'status' => $request->status,
        ]);

        return redirect()->back()->with('flash_message', 'Sourcing record saved successfully.');
    }

    public function updateSourcing(Request $request, $id)
    {
        $request->validate([
            'field' => 'required|string',
            'value' => 'nullable|string',
        ]);

        $sourcing = Sourcing::findOrFail($id);

        $allowedSourcing = [
            'target_item',
            'target_link1',
            'target_link2',
            'product_description',
            'rfq_form',
            'rfq_report',
            'status',
        ];

        if (in_array($request->field, $allowedSourcing)) {
            $sourcing->update([
                $request->field => $request->value,
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function deleteSourcing(Request $request)
    {
        $ids = $request->input('ids', []);
        if(!empty($ids)){
            Sourcing::whereIn('id', $ids)->delete();
            return response()->json(['success' => true]);
        }
        return response()->json(['success' => false], 400);
    }


    public function getParentBySku($sku)
    {
        $product = ProductMaster::where('sku', $sku)->first();

        if ($product) {
            return response()->json(['parent' => $product->parent]);
        }

        return response()->json(['parent' => null], 404);
    }
}
