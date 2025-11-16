<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\TransitContainerDetail;
use App\Models\UpcomingContainer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UpComingContainerController extends Controller
{
    public function index()
    {
        $containers = TransitContainerDetail::whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNull('status')
                ->orWhere('status', '');
            })
            ->select('tab_name')
            ->distinct()
            ->get();
        $suppliers = Supplier::all();
        return view('purchase-master.upcoming-container', compact('containers', 'suppliers' ));
    }

    public function saveUpComingContainer(Request $request)
    {
        $request->validate([
            'container_number'   => 'required|string',
            'supplier_id'        => 'required|exists:suppliers,id',
            'invoice_value'      => 'nullable|numeric',
            'paid'               => 'nullable|numeric',
            'payment_terms'      => 'nullable',
        ]);

        $invoiceValue = $request->invoice_value ?? 0;
        $paid         = $request->paid ?? 0;
        $balance      = $invoiceValue - $paid;

        if ($request->id) {
            // Update existing record
            $container = UpcomingContainer::findOrFail($request->id);
            $container->update([
                'container_number'   => $request->container_number,
                'supplier_id'        => $request->supplier_id,
                'area'               => $request->area,
                'order_link'         => $request->order_link,   
                'invoice_value'      => $invoiceValue,
                'paid'               => $paid,
                'balance'            => $balance,
                'payment_terms'      => $request->payment_terms,
            ]);

            $message = 'Upcoming Container updated successfully!';
        } else {
            // Create new record
            UpcomingContainer::create([
                'container_number'   => $request->container_number,
                'supplier_id'        => $request->supplier_id,
                'area'               => $request->area,
                'order_link'        => $request->order_link,  
                'invoice_value'      => $invoiceValue,
                'paid'               => $paid,
                'balance'            => $balance,
                'payment_terms'      => $request->payment_terms,
            ]);

            $message = 'Upcoming Container saved successfully!';
        }

        return redirect()->back()->with('flash_message', $message);
    }

    public function getUpComingContainer()
    {
        $upcomingContainers = UpcomingContainer::with('supplier')
            ->orderBy('id', 'desc')
            ->get();

        $data = $upcomingContainers->map(function ($upContainers) {
            return [
                'id'              => $upContainers->id,
                'container_number'=> $upContainers->container_number,
                'supplier_id'     => $upContainers->supplier_id,
                'supplier_name'   => $upContainers->supplier->name ?? '',
                'area'            => $upContainers->area,
                'order_link'      => $upContainers->order_link,
                'invoice_value'   => $upContainers->invoice_value,
                'paid'            => $upContainers->paid,
                'balance'         => $upContainers->balance,
                'payment_terms'        => $upContainers->payment_terms
            ];
        });

        return response()->json($data);
    }

    public function deleteUpcomingContainer(Request $request)
    {
        if (!$request->has('ids') || !is_array($request->ids)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request. IDs not provided.'
            ], 400);
        }

        UpcomingContainer::whereIn('id', $request->ids)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Deleted successfully.'
        ]);
    }

}
