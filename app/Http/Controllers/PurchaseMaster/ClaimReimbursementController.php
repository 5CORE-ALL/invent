<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Support\SuperAdminAccess;
use Illuminate\Http\Request;
use App\Models\ClaimReimbursement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ClaimReimbursementController extends Controller
{
    /**
     * Emails allowed to archive / resolve claims.
     */
    private const ARCHIVE_ALLOWED_EMAILS = [
        'president@5core.com',
        'inventory@5core.com',
    ];

    public function index()
    {
        $suppliers = Supplier::where('type', '=', 'Supplier')->get();

        // Generate next claim number
        $lastClaim = ClaimReimbursement::latest()->first();
        $nextNumber = $lastClaim ? ((int) str_replace('CLM-', '', $lastClaim->claim_number)) + 1 : 1;
        $claimNumber = 'CLM-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        $canArchive = SuperAdminAccess::allows(Auth::user(), self::ARCHIVE_ALLOWED_EMAILS);

        return view('purchase-master.claim-reimbursement', compact('suppliers', 'claimNumber', 'canArchive'));
    }

    public function getViewClaimReimbursementData(Request $request)
    {
        $claimsQuery = ClaimReimbursement::with('supplier:id,name,website,email,whatsapp,wechat,alibaba');

        if ($request->filled('supplier_id')) {
            $claimsQuery->where('supplier_id', $request->supplier_id);
        }

        // By default hide archived (resolved) claims unless explicitly requested.
        if ($request->boolean('show_archived')) {
            $claimsQuery->where('is_archived', true);
        } else {
            $claimsQuery->where('is_archived', false);
        }

        $claims = $claimsQuery->get();

        $formatted = $claims->map(function ($claim) {
            // Legacy rows (added before creator tracking) default to 1 Apr.
            $createdDate = $claim->created_at
                ? $claim->created_at->format('j M')
                : '1 Apr';

            return [
                'id' => $claim->id,
                'claim_number' => $claim->claim_number,
                'supplier_name' => $claim->supplier->name ?? 'N/A',
                'claim_date' => $claim->claim_date,
                'details' => $claim->items,
                'total_amount' => $claim->total_amount,
                'created_by' => $claim->created_by ?: 'System',
                'created_date' => $createdDate,
                'action_history' => $claim->action_history ?: [],
                'received_amount' => $claim->received_amount,
                'details_note' => $claim->details_note,
                'follow_up_date' => $claim->follow_up_date ? \Carbon\Carbon::parse($claim->follow_up_date)->format('Y-m-d') : null,
                'is_archived' => (bool) $claim->is_archived,
                'archived_by' => $claim->archived_by,
                'archived_date' => $claim->archived_at ? $claim->archived_at->format('j M') : null,
                'communication' => $this->buildSupplierPlatformLinks($claim->supplier),
            ];
        });

        return response()->json($formatted);
    }

    /**
     * Build supplier communication/platform links (Website, Email, WhatsApp, WeChat, Alibaba).
     * Mirrors the MFRG In-Progress communication column.
     */
    private function buildSupplierPlatformLinks($supplier): array
    {
        if (!$supplier) {
            return [];
        }

        $links = [];

        $website = trim((string) ($supplier->website ?? ''));
        if ($website !== '') {
            $url = preg_match('#^https?://#i', $website) ? $website : ('https://' . ltrim($website, '/'));
            $links[] = ['label' => 'Website', 'url' => $url, 'external' => true];
        }

        $email = trim((string) ($supplier->email ?? ''));
        if ($email !== '') {
            $links[] = ['label' => 'Email', 'url' => 'mailto:' . $email, 'external' => false];
        }

        $whatsapp = trim((string) ($supplier->whatsapp ?? ''));
        if ($whatsapp !== '') {
            $digits = preg_replace('/\D/', '', $whatsapp);
            if ($digits !== '') {
                $links[] = ['label' => 'WhatsApp', 'url' => 'https://wa.me/' . $digits, 'external' => true];
            }
        }

        $wechat = trim((string) ($supplier->wechat ?? ''));
        if ($wechat !== '') {
            $links[] = ['label' => 'WeChat', 'url' => null, 'display' => $wechat];
        }

        $alibaba = trim((string) ($supplier->alibaba ?? ''));
        if ($alibaba !== '') {
            $url = preg_match('#^https?://#i', $alibaba) ? $alibaba : ('https://' . ltrim($alibaba, '/'));
            $links[] = ['label' => 'Alibaba', 'url' => $url, 'external' => true];
        }

        return $links;
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

        $user = Auth::user()->name ?? 'System';

        ClaimReimbursement::create([
            'supplier_id' => $request->supplier,
            'claim_number' => $request->claim_number,
            'claim_date' => $request->claim_date,
            'items' => json_encode($items),
            'total_amount' => $totalAmount,
            'created_by' => $user,
            'action_history' => [
                [
                    'action' => 'Added',
                    'user' => $user,
                    'date' => now()->format('j M'),
                    'datetime' => now()->format('j M Y, g:i A'),
                ],
            ],
        ]);

        return redirect()->back()->with('flash_message', 'Claim submitted successfully.');
    }

    public function updateReceivedAmount(Request $request, $id)
    {
        $request->validate([
            'received_amount' => 'nullable|string',
        ]);

        $claim = ClaimReimbursement::findOrFail($id);

        $value = $request->input('received_amount');

        $history = $claim->action_history ?: [];
        $history[] = [
            'action' => 'Recd Amt/Goods updated',
            'user' => Auth::user()->name ?? 'System',
            'date' => now()->format('j M'),
            'datetime' => now()->format('j M Y, g:i A'),
        ];

        $claim->update([
            'received_amount' => $value,
            'action_history' => $history,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Received amount updated successfully.',
            'received_amount' => $claim->received_amount,
            'action_history' => $claim->action_history,
        ]);
    }

    public function addAction(Request $request, $id)
    {
        $request->validate([
            'note' => 'required|string',
        ]);

        $claim = ClaimReimbursement::findOrFail($id);

        $history = $claim->action_history ?: [];
        $history[] = [
            'action' => 'Action taken',
            'note' => trim($request->input('note')),
            'user' => Auth::user()->name ?? 'System',
            'date' => now()->format('j M'),
            'datetime' => now()->format('j M Y, g:i A'),
        ];

        $claim->update([
            'action_history' => $history,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Action added successfully.',
            'action_history' => $claim->action_history,
        ]);
    }

    public function updateDetailsNote(Request $request, $id)
    {
        $request->validate([
            'details_note' => 'nullable|string',
        ]);

        $claim = ClaimReimbursement::findOrFail($id);

        $claim->update([
            'details_note' => $request->input('details_note'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Details updated successfully.',
            'details_note' => $claim->details_note,
        ]);
    }

    public function updateFollowUpDate(Request $request, $id)
    {
        $request->validate([
            'follow_up_date' => 'nullable|date',
        ]);

        $claim = ClaimReimbursement::findOrFail($id);

        $claim->update([
            'follow_up_date' => $request->input('follow_up_date') ?: null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Follow up date updated successfully.',
            'follow_up_date' => $claim->follow_up_date ? \Carbon\Carbon::parse($claim->follow_up_date)->format('Y-m-d') : null,
        ]);
    }

    public function toggleArchive(Request $request, $id)
    {
        if (! SuperAdminAccess::allows(Auth::user(), self::ARCHIVE_ALLOWED_EMAILS)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to archive / resolve claims.',
            ], 403);
        }

        $claim = ClaimReimbursement::findOrFail($id);

        $archive = $request->boolean('archive', true);

        $claim->update([
            'is_archived' => $archive,
            'archived_by' => $archive ? (Auth::user()->name ?? 'System') : null,
            'archived_at' => $archive ? now() : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => $archive ? 'Claim archived (resolved).' : 'Claim restored.',
            'is_archived' => (bool) $claim->is_archived,
        ]);
    }
}
