<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use App\Models\ClaimReimbursement;
use Illuminate\Support\Facades\Log;

class ClaimReimbursementController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::where('type', '=', 'Supplier')->get();

        // Generate next claim number
        $lastClaim = ClaimReimbursement::latest()->first();
        $nextNumber = $lastClaim ? ((int) str_replace('CLM-', '', $lastClaim->claim_number)) + 1 : 1;
        $claimNumber = 'CLM-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        return view('purchase-master.claim-reimbursement', compact('suppliers', 'claimNumber'));
    }

    public function getViewClaimReimbursementData()
    {
        $claims = ClaimReimbursement::with('supplier:id,name')->get();

        $formatted = $claims->map(function ($claim) {
            return [
                'claim_number' => $claim->claim_number,
                'supplier_name' => $claim->supplier->name ?? 'N/A',
                'claim_date' => $claim->claim_date,
                'details' => $claim->items,
                'total_amount' => $claim->total_amount,
                'communication' => '',
            ];
        });

        return response()->json($formatted);
    }


    public function saveClaimReimbursement(Request $request)
    {
        $request->validate([
            'supplier' => 'required|exists:suppliers,id',
            'claim_number' => 'required|string',
            'claim_date' => 'required|date',
            'item.*' => 'required|string',
            'qty.*' => 'required|numeric',
            'rate.*' => 'required|numeric',
            'amount.*' => 'required|numeric',
            'image.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf',
        ]);

        $items = [];
        $totalAmount = 0;

        foreach ($request->item as $index => $item) {
            $imagePath = null;

            if ($request->hasFile("image.$index")) {
                $uploadPath = 'uploads/claim_images';
                $image = $request->file("image.$index");
                $imageName = time() . '_' . $index . '.' . $image->getClientOriginalExtension();
                $image->move(public_path($uploadPath), $imageName);
                $imagePath = $uploadPath . '/' . $imageName;
            }

            $rowAmount = $request->amount[$index];
            $totalAmount += $rowAmount;

            $items[] = [
                'item' => $item,
                'qty' => $request->qty[$index],
                'rate' => $request->rate[$index],
                'amount' => $rowAmount,
                'reason' => $request->reason[$index] ?? '',
                'image' => $imagePath,
            ];
        }

        ClaimReimbursement::create([
            'supplier_id' => $request->supplier,
            'claim_number' => $request->claim_number,
            'claim_date' => $request->claim_date,
            'items' => json_encode($items),
            'total_amount' => $totalAmount,
        ]);

        return redirect()->back()->with('flash_message', 'Claim submitted successfully.');
    }
}
