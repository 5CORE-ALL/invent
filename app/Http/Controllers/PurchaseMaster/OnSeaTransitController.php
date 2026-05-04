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

        $onSeaTransitData = OnSeaTransit::all()->map(function ($item) use ($chinaLoads) {
            $chinaLoad = $chinaLoads->firstWhere('container_sl_no', $item->container_sl_no);
            $invoiceValue = $item->invoice_value ?? 0;
            $paid = $item->paid ?? 0;
            $balance = $invoiceValue - $paid;
            
            return [
                'id' => $item->id,
                'container_sl_no' => $item->container_sl_no,
                'mbl' => $chinaLoad->mbl ?? null,
                'obl' => $chinaLoad->obl ?? null,
                'container_no' => $chinaLoad->container_no ?? null,
                'item' => $chinaLoad->item ?? null,
                'bl_check' => $item->bl_check,
                'bl_link' => $item->bl_link,
                'isf' => $item->isf,
                'etd' => $item->etd,
                'port_arrival' => $item->port_arrival,
                'eta_date_ohio' => $item->eta_date_ohio,
                'duty_calcu' => $item->duty_calcu,
                'invoice_send_to_dominic' => $item->invoice_send_to_dominic,
                'arrival_notice_email' => $item->arrival_notice_email,
                'remarks' => $item->remarks,
                'status' => $item->status,
                'invoice_value' => $item->invoice_value,
                'paid' => $item->paid,
                'balance' => $balance,
            ];
        });
        
        $totalCount = OnSeaTransit::count();
        $arrivedCount = OnSeaTransit::where('status', 'Arrived')->count();
        $planningCount = OnSeaTransit::where('status', 'Planning')->count();
        $remainingCount = $totalCount - ($arrivedCount + $planningCount);
        
        // Calculate total invoice value for filtered containers (all except Arrived and Planning)
        $totalInvoiceValue = OnSeaTransit::where(function($query) {
            $query->whereNull('status')
                  ->orWhereNotIn('status', ['Arrived', 'Planning']);
        })->sum('invoice_value');
        
        // Calculate total pending amount (balance) for filtered containers
        $totalPendingAmount = OnSeaTransit::where(function($query) {
            $query->whereNull('status')
                  ->orWhereNotIn('status', ['Arrived', 'Planning']);
        })->sum('balance');

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
            'totalCount' => $totalCount,
            'arrivedCount' => $arrivedCount,
            'planningCount' => $planningCount,
            'remainingCount' => $remainingCount,
            'totalInvoiceValue' => $totalInvoiceValue,
            'totalPendingAmount' => $totalPendingAmount,
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
        
        // Auto-calculate balance when invoice_value or paid changes
        if ($data['column'] === 'invoice_value' || $data['column'] === 'paid') {
            $invoiceValue = $data['column'] === 'invoice_value' ? $data['value'] : $record->invoice_value;
            $paid = $data['column'] === 'paid' ? $data['value'] : $record->paid;
            $record->balance = ($invoiceValue ?? 0) - ($paid ?? 0);
        }
        
        $record->save();

        return response()->json([
            'success' => true,
            'balance' => $record->balance
        ]);
    }
}
