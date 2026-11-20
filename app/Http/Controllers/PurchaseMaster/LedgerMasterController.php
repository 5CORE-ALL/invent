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
        $purchaseOrders = PurchaseOrder::select('id', 'po_number', 'supplier_id', 'total_amount', 'advance_amount')->get();
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
            'balance_image' => 'nullable|image|mimes:jpg,jpeg,png',
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
        if ($request->hasFile('balance_image')) {
            $data['balance_image'] = $request->file('balance_image')->store('advance_payments', 'public');
        }
        AdvancePayment::create($data);

        return back()->with('flash_message', 'Advance and Payment saved successfully.');
    }

    public function getAdvancePaymentsData(Request $request)
    {
        // active (default) | archived | all. A row counts as archived when either the payment
        // row or its purchase contract (PO) is archived, so both pages always agree.
        $filter = $request->query('filter', 'active');

        $query = AdvancePayment::select(
            'id',
            'vo_number',
            'supplier_id',
            'purchase_contract_id',
            'amount',
            'advance_amount',
            'image',
            'balance_image',
            'remarks',
            'is_archived'
        )
            ->with([
                'supplier' => fn($q) => $q->select('id', 'name')->withCount('bankAccounts'),
                'purchaseContract:' . implode(',', array_filter([
                    'id', 'po_number', 'po_date', 'items', 'is_archived',
                    \Schema::hasColumn('purchase_orders', 'po_stage') ? 'po_stage' : null,
                    \Schema::hasColumn('purchase_orders', 'currency') ? 'currency' : null,
                ])),
            ]);

        if ($filter === 'active') {
            $query->where('is_archived', false)
                ->whereDoesntHave('purchaseContract', fn($q) => $q->where('is_archived', true));
        } elseif ($filter === 'archived') {
            $query->where(function ($q) {
                $q->where('is_archived', true)
                    ->orWhereHas('purchaseContract', fn($p) => $p->where('is_archived', true));
            });
        }

        $payments = $query->get();

        $payments = $payments->map(function ($payment) {
            $amount = $payment->amount ?? 0;
            $advance = $payment->advance_amount ?? 0;

            $currency = 'USD';
            $currencySeen = false;
            // Currency chosen on the PO itself (Yuan contract) wins over the line-item guess.
            if (strtoupper((string) ($payment->purchaseContract->currency ?? '')) === 'RMB') {
                $currency = 'RMB';
                $currencySeen = true;
            }
            $items = $payment->purchaseContract->items ?? null;
            $items = is_array($items) ? $items : json_decode((string) $items, true);
            // SKUs on the PO (shown as the Summary chips, same as /list-all-purchase-orders)
            $skus = [];
            foreach ((array) $items as $item) {
                $sku = trim((string) (is_array($item) ? ($item['sku'] ?? '') : ''));
                if ($sku !== '' && !in_array($sku, $skus, true)) {
                    $skus[] = $sku;
                }
            }

            foreach ((array) $items as $item) {
                // PO currency = currency of its items (USD or RMB); first item wins on a mixed PO.
                if (!$currencySeen && !empty($item['currency'])) {
                    $cur = strtoupper(trim((string) $item['currency']));
                    $currency = in_array($cur, ['RMB', 'CNY', 'CNH', 'YUAN'], true) ? 'RMB' : 'USD';
                    $currencySeen = true;
                }
            }

            return [
                'id' => $payment->id,
                'vo_number' => $payment->vo_number,
                'supplier_id' => $payment->supplier_id,
                'supplier_name' => $payment->supplier->name ?? '',
                'bank_count' => (int) ($payment->supplier->bank_accounts_count ?? 0),
                'purchase_contract_id' => $payment->purchase_contract_id,
                'purchase_contract' => $payment->purchaseContract->po_number ?? '',
                'po_date' => $payment->purchaseContract && $payment->purchaseContract->po_date
                    ? Carbon::parse($payment->purchaseContract->po_date)->format('j M y')
                    : '',
                'currency' => $currency,
                'skus' => $skus,
                'po_stage' => $payment->purchaseContract->po_stage ?? '',
                'amount' => $amount ?? '',
                'advance_amount' => $advance ?? '',
                'balance' => $amount - $advance,
                'image' => $payment->image ? asset('storage/' . $payment->image) : '',
                'balance_image' => $payment->balance_image ? asset('storage/' . $payment->balance_image) : '',
                'remarks' => $payment->remarks ?? '',
                'is_archived' => (bool) ($payment->is_archived || ($payment->purchaseContract->is_archived ?? false)),
            ];
        });

        return response()->json($payments);
    }

    public function updateAdvancePayments(Request $request)
    {
        $request->validate([
            'payment_id' => 'required|exists:advance_payments,id',
            'vo_number' => 'required|string',
            'supplier_id' => 'required|exists:suppliers,id',
            'purchase_contract_id' => 'required|exists:purchase_orders,id',
            'amount' => 'nullable|numeric',
            'advance_amount' => 'nullable|numeric',
            'image' => 'nullable|image|mimes:jpg,jpeg,png',
            'balance_image' => 'nullable|image|mimes:jpg,jpeg,png',
            'remarks' => 'nullable|string',
        ]);

        $payment = AdvancePayment::findOrFail($request->payment_id);

        $payment->vo_number = $request->vo_number;
        $payment->supplier_id = $request->supplier_id;
        $payment->purchase_contract_id = $request->purchase_contract_id;
        $payment->amount = $request->amount;
        $payment->advance_amount = $request->advance_amount;
        $payment->remarks = $request->remarks;

        // Image upload - delete old image if new one is uploaded
        if ($request->hasFile('image')) {
            // Delete old image
            if ($payment->image && Storage::disk('public')->exists($payment->image)) {
                Storage::disk('public')->delete($payment->image);
            }
            $payment->image = $request->file('image')->store('advance_payments', 'public');
        }

        // Balance image - same handling as the advance image
        if ($request->hasFile('balance_image')) {
            if ($payment->balance_image && Storage::disk('public')->exists($payment->balance_image)) {
                Storage::disk('public')->delete($payment->balance_image);
            }
            $payment->balance_image = $request->file('balance_image')->store('advance_payments', 'public');
        }

        $payment->save();

        return back()->with('flash_message', 'Advance and Payment updated successfully.');
    }

    public function deleteAdvancePayments(Request $request)
    {
        $ids = $request->ids;
        AdvancePayment::whereIn('id', $ids)->delete();

        return response()->json(['success' => true, 'message' => 'Deleted successfully.']);
    }

    public function archiveAdvancePayment(Request $request)
    {
        $request->validate(['id' => 'required|exists:advance_payments,id']);

        $payment = AdvancePayment::findOrFail($request->id);
        $payment->update(['is_archived' => true, 'archived_at' => now()]);

        // Archived here => archived on the Purchase Contracts page too (the PO listener archives
        // every payment row of that PO).
        $po = $payment->purchaseContract;
        if ($po && ! $po->is_archived) {
            $po->update(['is_archived' => true]);
        }

        return response()->json(['success' => true, 'message' => 'Archived successfully.']);
    }

    public function restoreAdvancePayment(Request $request)
    {
        $request->validate(['id' => 'required|exists:advance_payments,id']);

        $payment = AdvancePayment::findOrFail($request->id);
        $payment->update(['is_archived' => false, 'archived_at' => null]);

        // Restored here => restored on the Purchase Contracts page too.
        $po = $payment->purchaseContract;
        if ($po && $po->is_archived) {
            $po->update(['is_archived' => false]);
        }

        return response()->json(['success' => true, 'message' => 'Restored successfully.']);
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
            $parts = explode('-', $latestOrder->vo_number);
            $lastSerial = intval(end($parts));
            $newSerial = str_pad($lastSerial + 1, 2, '0', STR_PAD_LEFT);
        } else {
            $newSerial = '01';
        }
        return "$prefix-$newSerial";
    }
}
