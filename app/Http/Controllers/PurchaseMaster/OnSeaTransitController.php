<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\ChinaLoad;
use App\Models\OnSeaTransit;
use Illuminate\Http\Request;

class OnSeaTransitController extends Controller
{
    public function index()
    {
        $chinaLoads = ChinaLoad::get(['container_sl_no', 'mbl', 'obl', 'container_no', 'item']);

        foreach ($chinaLoads as $load) {
            $exists = OnSeaTransit::where('container_sl_no', $load->container_sl_no)->exists();
            if (!$exists) {
                OnSeaTransit::create([
                    'container_sl_no' => $load->container_sl_no
                ]);
            }
        }

        $onSeaTransitData = OnSeaTransit::all();

        $chinaLoadMap = $chinaLoads->keyBy('container_sl_no')->map(function ($load) {
            return [
                'mbl' => $load->mbl,
                'obl' => $load->obl,
                'container_no' => $load->container_no,
                'item' => $load->item,
            ];
        });

        return view('purchase-master.on_sea_transit.index', [
            'onSeaTransitData' => $onSeaTransitData,
            'chinaLoadMap' => $chinaLoadMap,
        ]);
    }


    public function inlineUpdateOrCreate(Request $request)
    {
        $data = $request->only(['container_sl_no', 'column', 'value']);

        if (!$data['container_sl_no'] || !$data['column']) {
            return response()->json(['success' => false, 'message' => 'Missing data']);
        }

        $record = OnSeaTransit::firstOrNew(['container_sl_no' => $data['container_sl_no']]);
        $record->{$data['column']} = $data['value'];
        $record->save();

        return response()->json(['success' => true]);
    }
}
