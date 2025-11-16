<?php

namespace App\Http\Controllers\PurchaseMAster;

use App\Http\Controllers\Controller;
use App\Models\ContainerPlanning;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\TransitContainerDetail;
use Illuminate\Http\Request;

class ContainerPlanningController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::all();
        $containers = TransitContainerDetail::select('tab_name')->distinct()->get();
        $purchaseOrders = PurchaseOrder::all();
        return view('purchase-master.container_planning.index', compact('suppliers', 'containers', 'purchaseOrders'));
    }

    public function saveContainerPlanning(Request $request)
    {
        $request->validate([
            'container_number'   => 'required|string',
            'po_number'          => 'required|string',
            'supplier_id'        => 'required|exists:suppliers,id',
            'invoice_value'      => 'nullable|numeric',
            'paid'               => 'nullable|numeric',
            'pay_term'           => 'nullable|in:EXW,FOB',
        ]);

        $invoiceValue = $request->invoice_value ?? 0;
        $paid         = $request->paid ?? 0;
        $balance      = $invoiceValue - $paid;

        if ($request->id) {
            // Update existing record
            $container = ContainerPlanning::findOrFail($request->id);
            $container->update([
                'container_number'   => $request->container_number,
                'po_number'          => $request->po_number,
                'supplier_id'        => $request->supplier_id,
                'area'               => $request->area,
                'packing_list_link'  => $request->packing_list_link,
                'currency'           => 'USD',
                'invoice_value'      => $invoiceValue,
                'paid'               => $paid,
                'balance'            => $balance,
                'pay_term'           => $request->pay_term,
            ]);

            $message = 'Container Planning updated successfully!';
        } else {
            // Create new record
            ContainerPlanning::create([
                'container_number'   => $request->container_number,
                'po_number'          => $request->po_number,
                'supplier_id'        => $request->supplier_id,
                'area'               => $request->area,
                'packing_list_link'  => $request->packing_list_link,
                'currency'           => 'USD',
                'invoice_value'      => $invoiceValue,
                'paid'               => $paid,
                'balance'            => $balance,
                'pay_term'           => $request->pay_term,
            ]);

            $message = 'Container Planning saved successfully!';
        }

        return redirect()->back()->with('flash_message', $message);
    }


    public function getContainerPlannings()
    {
        $plannings = ContainerPlanning::with('supplier')
            ->orderBy('id', 'desc')
            ->get();

        $data = $plannings->map(function ($plan) {
            return [
                'id'              => $plan->id,
                'container_number'=> $plan->container_number,
                'po_number'       => $plan->po_number,
                'supplier_id'     => $plan->supplier_id,
                'supplier_name'   => $plan->supplier->name ?? '',
                'area'            => $plan->area,
                'packing_list_link'=> $plan->packing_list_link,
                'currency'        => $plan->currency,
                'invoice_value'   => $plan->invoice_value,
                'paid'            => $plan->paid,
                'balance'         => $plan->balance,
                'pay_term'        => $plan->pay_term,
                'created_at'      => $plan->created_at->format('Y-m-d'),
            ];
        });

        return response()->json($data);
    }

    public function deleteContainerPlanning(Request $request)
    {
        $ids = $request->ids;
        ContainerPlanning::whereIn('id', $ids)->delete();

        return response()->json(['success' => true, 'message' => 'Deleted successfully.']);
    }

    public function getPoDetails($poNumber)
    {
        $po = PurchaseOrder::with('supplier')
            ->where('po_number', $poNumber)
            ->firstOrFail();

        return response()->json([
            'supplier_id'    => $po->supplier_id,
            'supplier_name'  => $po->supplier->name ?? '',
            'total_amount'   => $po->total_amount,
            'advance_amount' => $po->advance_amount,
            'balance'        => $po->total_amount - $po->advance_amount,
        ]);
    }


}
