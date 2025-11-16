<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\OnRoadTransit;
use App\Models\OnSeaTransit;
use Illuminate\Http\Request;

class OnRoadTransitController extends Controller
{
    public function index(){

        $chinaLoads = OnSeaTransit::select('container_sl_no')->get();

        foreach ($chinaLoads as $load) {
            $exists = OnRoadTransit::where('container_sl_no', $load->container_sl_no)->exists();
            if (!$exists) {
                OnRoadTransit::create([
                    'container_sl_no' => $load->container_sl_no
                ]);
            }
        }
        
        $onRoadTransitData = OnRoadTransit::all();

        return view('purchase-master.on_road_transit.index', [
            'onRoadTransitData' => $onRoadTransitData
        ]);
    }

    public function inlineUpdateOrCreate(Request $request)
    {
        $data = $request->only(['container_sl_no', 'column', 'value']);

        if (!$data['container_sl_no'] || !$data['column']) {
            return response()->json(['success' => false, 'message' => 'Missing data']);
        }

        $record = OnRoadTransit::firstOrNew(['container_sl_no' => $data['container_sl_no']]);
        $record->{$data['column']} = $data['value'];
        $record->save();

        return response()->json(['success' => true]);
    }

}
