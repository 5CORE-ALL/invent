<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\AdvancePayment;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierLedger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder;

class LedgerMasterController extends Controller
{
    public function advanceAndPayments()
    {
        $voNumber = $this->generateVoucherNumber();
        $suppliers = Supplier::where('type', 'Supplier')->get();
        $purchaseOrders = PurchaseOrder::select('id', 'po_number', 'total_amount', 'advance_amount')->get();
        return view('purchase-master.ledger-master.advance-payments', compact('suppliers', 'purchaseOrders', 'voNumber'));
    }

    public function supplierLedger()
    {
        $suppliers = Supplier::where('type', 'Supplier')->get();
        return view('purchase-master.ledger-master.supplier-ledger', compact('suppliers'));
    }

    public function supplierStore(Request $request)
    {
        $validated = $request->validate([
            'supplier' => 'required|integer|exists:suppliers,id',
            'pm_image' => 'nullable|image|max:2048',
            'purchase_link' => 'nullable|url',
            'dr' => 'nullable|numeric',
            'cr' => 'nullable|numeric',
            'balance' => 'required|numeric',
        ]);

        $ledger = new SupplierLedger();
        $ledger->supplier_id = $validated['supplier'];

        if ($request->hasFile('pm_image')) {
            $path = $request->file('pm_image')->store('supplier_ledgers', 'public');
            $ledger->pm_image = $path;
        }

        $ledger->purchase_link = $validated['purchase_link'] ?? null;
        $ledger->dr = $validated['dr'] ?? 0;
        $ledger->cr = $validated['cr'] ?? 0;
        $ledger->balance = $validated['balance'];
        $ledger->save();

        return redirect()->back()->with('flash_message', 'Supplier Ledger entry created successfully.');
    }

    public function fetchSupplierLedgerData(Request $request)
    {
        $ledgers = SupplierLedger::orderBy('id', 'desc')->get();

        $data = $ledgers->map(function ($ledger) {

            $pmImageUrl = '';
            if (!empty($ledger->pm_image)) {
                $pmImageUrl = asset('storage/' . $ledger->pm_image);
            }

            return [
                'id'           => $ledger->id,
                'supplier_id'  => $ledger->supplier_id ?? '',
                'pm_image'     => $pmImageUrl,
                'purchase_link'=> $ledger->purchase_link,
                'dr'           => $ledger->dr,
                'cr'           => $ledger->cr,
                'balance'      => $ledger->balance,
            ];
        });

        $suppliers = Supplier::select('id', 'name')->orderBy('name')->get();

        return response()->json([
            'ledgers' => $data,
            'suppliers' => $suppliers
        ]);
    }

    public function updateSupplierLedger(Request $request)
    {
        $ledger = SupplierLedger::findOrFail($request->id);

        if ($request->hasFile('file')) {
            if ($ledger->{$request->field}) {
                $oldPath = $ledger->{$request->field};
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }

            $path = $request->file('file')->store('supplier_ledgers', 'public');

            $ledger->{$request->field} = $path;
            $ledger->save();

            return response()->json([
                'success' => true,
                'url' => asset('storage/' . $path)
            ]);
        }

        if ($request->filled('field')) {
            $ledger->{$request->field} = $request->value;
            $ledger->save();

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false]);
    }

    public function deleteSupplierLedger(Request $request)
    {
        $ids = $request->ids;
        SupplierLedger::whereIn('id', $ids)->delete();

        return response()->json(['success' => true, 'message' => 'Deleted successfully.']);
    }


    public function getSupplierBalance(Request $request)
    {
        $supplierId = $request->input('supplier_id');

        $balance = SupplierLedger::where('supplier_id', $supplierId)
            ->orderBy('id', 'desc')
            ->value('balance');

        return response()->json([
            'balance' => $balance ?? 0
        ]);
    }

    public function saveAdvancePayments(Request $request)
    {
        $request->validate([
            'vo_number' => 'required|string',
            'supplier_id' => 'required|exists:suppliers,id',
            'purchase_contract_id' => 'required|exists:purchase_orders,id',
            'amount' => 'nullable|numeric',
            'advance_amount' => 'nullable|numeric',
            'image' => 'nullable|image|mimes:jpg,jpeg,png',
            'remarks' => 'nullable|string',
        ]);

        $data = $request->only([
            'vo_number',
            'supplier_id',
            'purchase_contract_id',
            'amount',
            'advance_amount',
            'remarks',
        ]);

        // Image upload
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('advance_payments', 'public');
        }
        AdvancePayment::create($data);

        return back()->with('flash_message', 'Advance and Payment saved successfully.');
    }

    public function getAdvancePaymentsData()
    {
        $payments = AdvancePayment::select(
            'id',
            'vo_number',
            'supplier_id',
            'purchase_contract_id',
            'amount',
            'advance_amount',
            'image',
            'remarks'
        )
            ->with(['supplier:id,name', 'purchaseContract:id,po_number'])
            ->get();

        $payments = $payments->map(function ($payment) {
            $amount = $payment->amount ?? 0;
            $advance = $payment->advance_amount ?? 0;

            return [
                'id' => $payment->id,
                'vo_number' => $payment->vo_number,
                'supplier_name' => $payment->supplier->name ?? '',
                'purchase_contract' => $payment->purchaseContract->po_number ?? '',
                'amount' => $amount ?? '',
                'advance_amount' => $advance ?? '',
                'balance' => $amount - $advance,
                'image' => $payment->image ? asset('storage/' . $payment->image) : '',
                'remarks' => $payment->remarks ?? '',
            ];
        });

        return response()->json($payments);
    }

    public function deleteAdvancePayments(Request $request)
    {
        $ids = $request->ids;
        AdvancePayment::whereIn('id', $ids)->delete();

        return response()->json(['success' => true, 'message' => 'Deleted successfully.']);
    }

    function generateVoucherNumber()
    {
        $datePart = Carbon::now()->format('dmy');
        $prefix = 'VO-' . $datePart;

        $latestOrder = AdvancePayment::select('vo_number')
            ->where('vo_number', 'like', "$prefix-%")
            ->orderBy('vo_number', 'desc')
            ->first();

        if ($latestOrder) {
            $parts = explode('-', $latestOrder->po_number);
            $lastSerial = intval(end($parts));
            $newSerial = str_pad($lastSerial + 1, 2, '0', STR_PAD_LEFT);
        } else {
            $newSerial = '01';
        }
        return "$prefix-$newSerial";
    }
}
