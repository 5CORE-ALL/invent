<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\ChinaLoad;
use App\Models\OnSeaTransit;
use App\Models\OnSeaTransitDetailsHistory;
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

        $onSeaTransitData = OnSeaTransit::whereNull('archived_at')->get()->map(function ($item) use ($chinaLoads) {
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
                'eta_port' => $item->eta_port,
                'port_arrival' => $item->port_arrival,
                'eta_date_ohio' => $item->eta_date_ohio,
                'duty_calcu' => $item->duty_calcu,
                'invoice_send_to_dominic' => $item->invoice_send_to_dominic,
                'arrival_notice_email' => $item->arrival_notice_email,
                'remarks' => $item->remarks,
                'status' => $item->status,
                'invoice_value' => $item->invoice_value,
                'freight' => $item->freight,
                'paid' => $item->paid,
                'balance' => $balance,
                'details' => $item->details,
            ];
        });
        
        // All count/sum aggregates must ignore archived rows so the badges
        // stay in sync with the visible table (whereNull('archived_at')).
        $activeBase = fn () => OnSeaTransit::whereNull('archived_at');

        $totalCount = $activeBase()->count();
        $arrivedCount = $activeBase()->where('status', 'Arrived')->count();
        $planningCount = $activeBase()->where('status', 'Planning')->count();
        $remainingCount = $totalCount - ($arrivedCount + $planningCount);

        // Calculate total invoice value for filtered containers (all except Arrived and Planning)
        $totalInvoiceValue = $activeBase()->where(function ($query) {
            $query->whereNull('status')
                  ->orWhereNotIn('status', ['Arrived', 'Planning']);
        })->sum('invoice_value');

        // Calculate total pending amount (balance) for filtered containers
        $totalPendingAmount = $activeBase()->where(function ($query) {
            $query->whereNull('status')
                  ->orWhereNotIn('status', ['Arrived', 'Planning']);
        })->sum('balance');

        // "Value" badge — sum of the table's Value column (invoice_value) for
        // every row the user actually sees. The Tabulator front-end filters
        // out only 'Arrived' rows (see updateBadgeCounts), so we mirror that
        // here for the initial paint; the JS recomputes after any inline edit.
        $totalColumnValue = $activeBase()->where(function ($query) {
            $query->whereNull('status')
                  ->orWhere('status', '!=', 'Arrived');
        })->sum('invoice_value');

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
            'totalColumnValue' => $totalColumnValue,
        ]);
    }


    public function inlineUpdateOrCreate(Request $request)
    {
        $data = $request->only(['container_sl_no', 'column', 'value']);

        if (!$data['container_sl_no'] || !$data['column']) {
            return response()->json(['success' => false, 'message' => 'Missing data']);
        }

        $record = OnSeaTransit::firstOrNew(['container_sl_no' => $data['container_sl_no']]);
        
        // Track details history if the column is 'details'
        if ($data['column'] === 'details') {
            $oldValue = $record->details;
            $newValue = $data['value'];
            
            // Only create history if value actually changed
            if ($oldValue !== $newValue) {
                $record->{$data['column']} = $data['value'];
                $record->save();
                
                // Get current user name
                $userName = auth()->check() ? auth()->user()->name : 'Unknown';
                
                OnSeaTransitDetailsHistory::create([
                    'on_sea_transit_id' => $record->id,
                    'container_sl_no' => $data['container_sl_no'],
                    'user_name' => $userName,
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                    'changed_at' => now(),
                ]);
            }
        } else {
            $record->{$data['column']} = $data['value'];
            
            // Auto-calculate balance when invoice_value or paid changes
            if ($data['column'] === 'invoice_value' || $data['column'] === 'paid') {
                $invoiceValue = $data['column'] === 'invoice_value' ? $data['value'] : $record->invoice_value;
                $paid = $data['column'] === 'paid' ? $data['value'] : $record->paid;
                $record->balance = ($invoiceValue ?? 0) - ($paid ?? 0);
            }
            
            $record->save();
        }

        return response()->json([
            'success' => true,
            'balance' => $record->balance
        ]);
    }
    
    public function getDetailsHistory($id)
    {
        $history = OnSeaTransitDetailsHistory::where('on_sea_transit_id', $id)
            ->orderBy('changed_at', 'desc')
            ->get();
            
        return response()->json([
            'success' => true,
            'history' => $history
        ]);
    }
    
    public function syncValue(Request $request)
    {
        $data = $request->only(['container_sl_no', 'invoice_value']);

        if (!$data['container_sl_no']) {
            return response()->json(['success' => false, 'message' => 'Container number is required']);
        }

        $record = OnSeaTransit::firstOrNew(['container_sl_no' => $data['container_sl_no']]);
        $record->invoice_value = $data['invoice_value'] ?? 0;
        
        // Auto-calculate balance
        $invoiceValue = $record->invoice_value ?? 0;
        $paid = $record->paid ?? 0;
        $record->balance = $invoiceValue - $paid;
        
        $record->save();

        return response()->json([
            'success' => true,
            'message' => 'Value synced successfully'
        ]);
    }

    /**
     * Archive a row from the On Sea Transit board.  Sets `archived_at = now()`
     * so the row stops appearing in the main view but stays in the DB.
     * Looked up by `id` (preferred) with a `container_sl_no` fallback.
     */
    public function archive(Request $request)
    {
        $id = $request->input('id');
        $containerSlNo = $request->input('container_sl_no');

        $record = $id
            ? OnSeaTransit::find($id)
            : ($containerSlNo ? OnSeaTransit::where('container_sl_no', $containerSlNo)->first() : null);

        if (!$record) {
            return response()->json(['success' => false, 'message' => 'Row not found'], 404);
        }

        $record->archived_at = now();
        $record->save();

        return response()->json(['success' => true]);
    }

    /**
     * Restore a previously archived row.  Symmetric to archive() — clears
     * archived_at so the row reappears on the board.
     */
    public function restore(Request $request)
    {
        $id = $request->input('id');
        $containerSlNo = $request->input('container_sl_no');

        $record = $id
            ? OnSeaTransit::find($id)
            : ($containerSlNo ? OnSeaTransit::where('container_sl_no', $containerSlNo)->first() : null);

        if (!$record) {
            return response()->json(['success' => false, 'message' => 'Row not found'], 404);
        }

        $record->archived_at = null;
        $record->save();

        return response()->json(['success' => true]);
    }

    /**
     * Bulk-update every editable field on a single row in one DB round-trip.
     * Powers the "Edit" pencil button in the Action column, which lets the
     * user fix every column at once instead of clicking each cell.
     *
     * Only fields present in the request body are touched, so partial edits
     * (e.g. just changing remarks + freight) leave the rest intact. Balance
     * is recomputed from invoice_value/paid the same way inlineUpdateOrCreate
     * does it, keeping the Due column honest.
     */
    public function updateRow(Request $request)
    {
        $containerSlNo = $request->input('container_sl_no');
        if (!$containerSlNo) {
            return response()->json(['success' => false, 'message' => 'container_sl_no is required'], 422);
        }

        $record = OnSeaTransit::firstOrNew(['container_sl_no' => $containerSlNo]);

        $editable = [
            'bl_check', 'bl_link', 'isf', 'isf_usa_agent', 'etd', 'eta_port',
            'port_arrival', 'eta_date_ohio', 'duty_calcu', 'invoice_send_to_dominic',
            'arrival_notice_email', 'remarks', 'invoice_value', 'freight', 'paid',
            'details', 'status',
        ];

        foreach ($editable as $field) {
            if ($request->has($field)) {
                $value = $request->input($field);
                // Coerce empty strings to null so DECIMAL/DATE columns stay clean.
                $record->{$field} = ($value === '' ? null : $value);
            }
        }

        // Keep balance derived (matches inlineUpdateOrCreate's behaviour).
        $invoiceValue = $record->invoice_value ?? 0;
        $paid = $record->paid ?? 0;
        $record->balance = $invoiceValue - $paid;

        $record->save();

        return response()->json([
            'success' => true,
            'balance' => $record->balance,
            'record'  => $record->only(array_merge($editable, ['id', 'container_sl_no', 'balance'])),
        ]);
    }
}
