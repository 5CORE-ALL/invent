<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\ChinaLoad;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChinaLoadController extends Controller
{
    public function index()
    {
        $chinaLoads = ChinaLoad::all();
        $forwarders = Supplier::where('type', 'Forwarders')->orderBy('name')->get();
        return view('purchase-master.china_load.index', compact('chinaLoads', 'forwarders'));
    }

    public function inlineUpdateBySl(Request $request)
    {
        $container_sl_no = $request->input('container_sl_no');

        if (!$container_sl_no) {
            return response()->json(['success' => false, 'message' => 'Missing sl_no.']);
        }

        $record = ChinaLoad::firstOrNew(['container_sl_no' => $container_sl_no]);

        if ($request->has('column') && $request->has('value')) {
            $column = $request->input('column');
            $value = $request->input('value');

            if (!in_array($column, [
                'load', 'list_of_goods', 'shut_out', 'obl', 'mbl', 'container_no', 'item', 'cha_china', 'consignee', 'status'
            ])) {
                return response()->json(['success' => false, 'message' => 'Invalid column.']);
            }

            $record->$column = $value;
        } else {
            $allowed = ['load', 'list_of_goods', 'shut_out', 'obl', 'mbl', 'container_no', 'item', 'cha_china', 'consignee', 'status'];
            foreach ($allowed as $field) {
                if ($request->has($field)) {
                    $record->$field = $request->input($field);
                }
            }
        }

        $record->save();

        return response()->json(['success' => true]);
    }


}
