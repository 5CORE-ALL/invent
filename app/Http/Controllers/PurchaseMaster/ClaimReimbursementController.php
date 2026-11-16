<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\Purchase;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Support\AttendanceAccess;
use Illuminate\Http\Request;
use App\Models\ClaimReimbursement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ClaimReimbursementController extends Controller
{
    /**
     * Only directors may archive / resolve (remove) or edit claims.
     * Director = org level, role or designation, same rule as {@see AttendanceAccess::isDirector()}.
     */
    private function userCanManage(): bool
    {
        return AttendanceAccess::isDirector(Auth::user());
    }

    private function userCanArchive(): bool
    {
        return $this->userCanManage();
    }

    public function index()
    {
        $suppliers = Supplier::where('type', '=', 'Supplier')->get();

        // Generate next claim number
        $lastClaim = ClaimReimbursement::latest()->first();
        $nextNumber = $lastClaim ? ((int) str_replace('CLM-', '', $lastClaim->claim_number)) + 1 : 1;
        $claimNumber = 'CLM-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        $canArchive = $this->userCanArchive();

        return view('purchase-master.claim-reimbursement', compact('suppliers', 'claimNumber', 'canArchive'));
    }

    public function getViewClaimReimbursementData(Request $request)
    {
        $claimsQuery = ClaimReimbursement::with('supplier:id,name,website,email,whatsapp,wechat,alibaba');

        if ($request->filled('supplier_id')) {
            $claimsQuery->where('supplier_id', $request->supplier_id);
        }

        // Default view: active claims only. The "Show archived" view lists ALL claims
        // (active + archived) so every claim can be found from the archived section too.
        if (! $request->boolean('show_archived')) {
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
                'supplier_id' => $claim->supplier_id,
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
     * Badge numbers for Auto Task Events (ATE): keys are the badge element ids on the page, so rules made
     * in the browser and the server-side daily check use the same badges. Covers all suppliers.
     *
     * @return list<array{key: string, label: string, value: int|float}>
     */
    public function aceBadgeValues(): array
    {
        $query = ClaimReimbursement::where('is_archived', false);

        return [
            ['key' => 'claimsPendingCountBadge', 'label' => 'Claims Pending', 'value' => (clone $query)->count()],
            ['key' => 'claimsPendingAmountBadge', 'label' => 'Total Amt', 'value' => (int) round((float) (clone $query)->sum('total_amount'))],
        ];
    }

    /**
     * "Claims Pending" badge: number of active (not archived / resolved) claims and their total amount.
     */
    public function getPendingSummary(Request $request)
    {
        $query = ClaimReimbursement::where('is_archived', false);

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        return response()->json([
            'count' => (clone $query)->count(),
            'total_amount' => round((float) (clone $query)->sum('total_amount'), 2),
        ]);
    }

    /**
     * Resolve supplier(s) for a SKU using the data on /supplier.list:
     * the SKU's Parent (product master) is matched against each supplier's
     * "parent" list, and the SKU itself against the supplier's "sku" list.
     */
    public function getSkuSupplier(Request $request)
    {
        $sku = strtoupper(trim((string) $request->query('sku', '')));
        if ($sku === '') {
            return response()->json(['parent' => '', 'suppliers' => []]);
        }

        $parentRaw = trim((string) ProductMaster::where('sku', $request->query('sku'))->value('parent'));
        $parent = strtoupper($parentRaw);

        $split = static fn ($v) => array_filter(array_map('trim', explode(',', strtoupper((string) $v))), fn ($x) => $x !== '');

        $matches = [];
        foreach (Supplier::where('type', 'Supplier')->get(['id', 'name', 'sku', 'parent']) as $supplier) {
            $parents = $split($supplier->parent);
            $skus = $split($supplier->sku);

            if (($parent !== '' && in_array($parent, $parents, true)) || in_array($sku, $skus, true)) {
                $matches[] = ['id' => $supplier->id, 'name' => $supplier->name];
            }
        }

        $last = $this->findLastPurchaseRate((string) $request->query('sku'));

        return response()->json([
            'parent' => $parentRaw,
            'suppliers' => $matches,
            'last_rate' => $last['rate'] ?? null,
            'last_rate_source' => $last['source'] ?? null,
        ]);
    }

    /**
     * Latest USD rate paid for a SKU: newest Purchase voucher first,
     * then the newest USD-priced PO line as a fallback.
     *
     * @return array{rate: float, source: string}|null
     */
    private function findLastPurchaseRate(string $sku): ?array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        $needle = '%' . addcslashes(json_encode($sku), '\\%_') . '%';
        $matchSku = static fn ($row) => is_array($row)
            && strcasecmp(trim((string) ($row['sku'] ?? '')), $sku) === 0;

        $purchases = Purchase::where('items', 'like', $needle)
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->get(['id', 'vo_number', 'purchase_date', 'items']);

        foreach ($purchases as $purchase) {
            $items = is_array($purchase->items) ? $purchase->items : json_decode((string) $purchase->items, true);
            foreach ((array) $items as $row) {
                if ($matchSku($row) && (float) ($row['price'] ?? 0) > 0) {
                    return [
                        'rate' => round((float) $row['price'], 4),
                        'source' => 'Purchase ' . $purchase->vo_number,
                    ];
                }
            }
        }

        $orders = PurchaseOrder::where('items', 'like', $needle)
            ->orderByDesc('po_date')
            ->orderByDesc('id')
            ->get(['id', 'po_number', 'po_date', 'items']);

        foreach ($orders as $order) {
            $items = is_array($order->items) ? $order->items : json_decode((string) $order->items, true);
            foreach ((array) $items as $row) {
                if (! $matchSku($row) || strtoupper((string) ($row['currency'] ?? 'USD')) !== 'USD') {
                    continue;
                }
                if ((float) ($row['price'] ?? 0) > 0) {
                    return [
                        'rate' => round((float) $row['price'], 4),
                        'source' => 'PO ' . $order->po_number,
                    ];
                }
            }
        }

        return null;
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
            'supplier' => 'nullable|exists:suppliers,id',
            'row_supplier.*' => 'nullable|exists:suppliers,id',
            'claim_number' => 'required|string',
            'claim_date' => 'required|date',
            'item.*' => 'required|string',
            'qty.*' => 'required|numeric',
            'rate.*' => 'required|numeric',
            'amount.*' => 'required|numeric',
            'image.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf',
            'screenshot.*' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp',
            'video_link.*' => ['nullable', 'string', 'max:1000', 'regex:/^https?:\/\/\S+$/i'],
            'video_file.*' => 'nullable|file|mimes:mp4,mov,qt,m4v,webm,mkv,avi,mpeg,mpg,3gp|max:102400',
        ]);

        [$items, $totalAmount, $claimSupplierId] = $this->buildItemsFromForm($request);
        if (! $claimSupplierId) {
            return redirect()->back()->withInput()->withErrors(['row_supplier' => 'Please select a supplier.']);
        }

        $user = Auth::user()->name ?? 'System';

        ClaimReimbursement::create([
            'supplier_id' => $claimSupplierId,
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

        $redirectTo = trim((string) $request->input('redirect_to', ''));
        if ($redirectTo !== '' && str_starts_with($redirectTo, url('/'))) {
            return redirect()->to($redirectTo)->with('flash_message', 'Claim submitted successfully.');
        }

        return redirect()->back()->with('flash_message', 'Claim submitted successfully.');
    }

    /**
     * Turn the claim modal's parallel arrays into line items.
     * A row keeps its previous image (existing_image[]) unless a new file is uploaded.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: float|int, 2: int|string|null}
     */
    private function buildItemsFromForm(Request $request): array
    {
        $items = [];
        $totalAmount = 0;

        $rowSupplierIds = array_filter((array) $request->input('row_supplier', []));

        // The claim's main supplier: explicit one (PO proforma) or the first row's supplier.
        $claimSupplierId = $request->input('supplier') ?: (reset($rowSupplierIds) ?: null);

        $rowSupplierNames = $rowSupplierIds
            ? Supplier::whereIn('id', $rowSupplierIds)->pluck('name', 'id')->all()
            : [];

        foreach ($request->item as $index => $item) {
            $imagePath = trim((string) $request->input("existing_image.$index", '')) ?: null;

            if ($request->hasFile("image.$index")) {
                $uploadPath = 'uploads/claim_images';
                $image = $request->file("image.$index");
                $imageName = time() . '_' . $index . '.' . $image->getClientOriginalExtension();
                $image->move(public_path($uploadPath), $imageName);
                $imagePath = $uploadPath . '/' . $imageName;
            }

            $screenshotPath = trim((string) $request->input("existing_screenshot.$index", '')) ?: null;

            if ($request->hasFile("screenshot.$index")) {
                $uploadPath = 'uploads/claim_images';
                $shot = $request->file("screenshot.$index");
                $shotName = time() . '_' . $index . '_sc.' . $shot->getClientOriginalExtension();
                $shot->move(public_path($uploadPath), $shotName);
                $screenshotPath = $uploadPath . '/' . $shotName;
            }

            // Uploaded video: a new file replaces the saved one; otherwise the saved path is kept.
            $videoPath = trim((string) $request->input("existing_video_file.$index", ''));
            // Only ever keep a path inside our own upload folder (the field comes from the browser).
            $videoPath = str_starts_with($videoPath, 'uploads/claim_videos/') && ! str_contains($videoPath, '..') ? $videoPath : null;

            if ($request->hasFile("video_file.$index")) {
                $uploadPath = 'uploads/claim_videos';
                $video = $request->file("video_file.$index");
                $videoExt = $video->guessExtension() ?: $video->getClientOriginalExtension();
                $videoName = time() . '_' . $index . '_vid.' . $videoExt;
                $video->move(public_path($uploadPath), $videoName);
                $videoPath = $uploadPath . '/' . $videoName;
            }

            $rowAmount = $request->amount[$index];
            $totalAmount += $rowAmount;

            $rowSupplierId = $request->input("row_supplier.$index");

            $items[] = [
                'parent' => trim((string) $request->input("row_parent.$index", '')),
                'item' => $item,
                'supplier_id' => $rowSupplierId ?: null,
                'supplier' => $rowSupplierId ? ($rowSupplierNames[$rowSupplierId] ?? '') : '',
                'qty' => $request->qty[$index],
                'rate' => $request->rate[$index],
                'amount' => $rowAmount,
                'reason' => $request->reason[$index] ?? '',
                'image' => $imagePath,
                'screenshot' => $screenshotPath,
                'video_link' => trim((string) $request->input("video_link.$index", '')) ?: null,
                'video_file' => $videoPath,
            ];
        }

        return [$items, $totalAmount, $claimSupplierId];
    }

    /**
     * Edit a whole claim from the claim modal. Directors only.
     */
    public function updateClaim(Request $request, $id)
    {
        if (! $this->userCanManage()) {
            return redirect()->back()->with('flash_message', 'Only directors can edit claims.');
        }

        $request->validate([
            'row_supplier.*' => 'nullable|exists:suppliers,id',
            'claim_date' => 'required|date',
            'item.*' => 'required|string',
            'qty.*' => 'required|numeric',
            'rate.*' => 'required|numeric',
            'amount.*' => 'required|numeric',
            'image.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf',
            'existing_image.*' => 'nullable|string|max:500',
            'screenshot.*' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp',
            'existing_screenshot.*' => 'nullable|string|max:500',
            'video_link.*' => ['nullable', 'string', 'max:1000', 'regex:/^https?:\/\/\S+$/i'],
            'video_file.*' => 'nullable|file|mimes:mp4,mov,qt,m4v,webm,mkv,avi,mpeg,mpg,3gp|max:102400',
            'existing_video_file.*' => 'nullable|string|max:500',
        ]);

        $claim = ClaimReimbursement::findOrFail($id);

        [$items, $totalAmount, $claimSupplierId] = $this->buildItemsFromForm($request);
        if (! $claimSupplierId) {
            return redirect()->back()->withInput()->withErrors(['row_supplier' => 'Please select a supplier.']);
        }

        $user = Auth::user()->name ?? 'System';
        $history = $claim->action_history ?: [];
        $history[] = [
            'action' => 'Edited',
            'user' => $user,
            'date' => now()->format('j M'),
            'datetime' => now()->format('j M Y, g:i A'),
        ];

        $claim->update([
            'supplier_id' => $claimSupplierId,
            'claim_date' => $request->claim_date,
            'items' => $items,
            'total_amount' => $totalAmount,
            'action_history' => $history,
        ]);

        return redirect()->back()->with('flash_message', 'Claim ' . $claim->claim_number . ' updated successfully.');
    }

    /**
     * A claim's line items as a plain list. Claims created from the form were stored double-encoded
     * (a JSON string inside the JSON column), so `$claim->items` can come back as a string.
     *
     * @return array<int, array<string, mixed>>
     */
    private function claimItemsArray(ClaimReimbursement $claim): array
    {
        $items = $claim->items;
        for ($i = 0; $i < 2 && is_string($items); $i++) {
            $items = json_decode($items, true);
        }

        return is_array($items) ? array_values($items) : [];
    }

    /**
     * Set / replace / remove the video link and video file of ONE line item on an existing claim.
     * Open to everyone who can open the claim (like Actions and Recd Amt), so the claim can stay read-only
     * for non-directors while still letting them attach a video. Logged in the claim's action history.
     */
    public function updateVideo(Request $request, $id)
    {
        $request->validate([
            'row_index' => 'required|integer|min:0',
            'video_link' => ['nullable', 'string', 'max:1000', 'regex:/^https?:\/\/\S+$/i'],
            'video_file' => 'nullable|file|mimes:mp4,mov,qt,m4v,webm,mkv,avi,mpeg,mpg,3gp|max:102400',
            'remove_file' => 'nullable|boolean',
        ]);

        $claim = ClaimReimbursement::findOrFail($id);
        $items = $this->claimItemsArray($claim);
        $index = (int) $request->input('row_index');

        if (! isset($items[$index])) {
            return response()->json(['success' => false, 'message' => 'That claim line no longer exists. Reload the page.'], 422);
        }

        $items[$index]['video_link'] = trim((string) $request->input('video_link', '')) ?: null;

        if ($request->hasFile('video_file')) {
            $uploadPath = 'uploads/claim_videos';
            $video = $request->file('video_file');
            $videoExt = $video->guessExtension() ?: $video->getClientOriginalExtension();
            $videoName = time() . '_' . $index . '_vid.' . $videoExt;
            $video->move(public_path($uploadPath), $videoName);
            $items[$index]['video_file'] = $uploadPath . '/' . $videoName;
        } elseif ($request->boolean('remove_file')) {
            $items[$index]['video_file'] = null;
        }

        $history = $claim->action_history ?: [];
        $history[] = [
            'action' => 'Video updated (' . ($items[$index]['item'] ?? 'row ' . ($index + 1)) . ')',
            'user' => Auth::user()->name ?? 'System',
            'date' => now()->format('j M'),
            'datetime' => now()->format('j M Y, g:i A'),
        ];

        $claim->update([
            'items' => $items,
            'action_history' => $history,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Video saved.',
            'video_link' => $items[$index]['video_link'],
            'video_file' => $items[$index]['video_file'] ?? null,
            'items' => $items,
            'action_history' => $history,
        ]);
    }

    /**
     * Update claim line items (+ optional received_amount / details_note).
     * Used by PO proforma Claim & Reimb. section.
     */
    public function updateClaimItems(Request $request, $id)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.item' => 'required|string|max:255',
            'items.*.qty' => 'nullable|numeric',
            'items.*.rate' => 'nullable|numeric',
            'items.*.amount' => 'nullable|numeric',
            'items.*.reason' => 'nullable|string|max:2000',
            'items.*.image' => 'nullable|string|max:500',
            'received_amount' => 'nullable|string|max:255',
            'details_note' => 'nullable|string|max:5000',
        ]);

        $claim = ClaimReimbursement::findOrFail($id);
        $existingItems = $this->claimItemsArray($claim);
        $items = [];
        $totalAmount = 0.0;

        foreach ($request->input('items', []) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $sku = trim((string) ($row['item'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $qty = $row['qty'] ?? '';
            $rate = $row['rate'] ?? '';
            $amount = $row['amount'] ?? '';
            if ($amount === '' || $amount === null) {
                $q = is_numeric($qty) ? (float) $qty : 0.0;
                $r = is_numeric($rate) ? (float) $rate : 0.0;
                $amount = round($q * $r, 2);
            }
            if (is_numeric($amount)) {
                $totalAmount += (float) $amount;
            }

            $image = $row['image'] ?? null;
            if (($image === null || $image === '') && isset($existingItems[$index]['image'])) {
                $image = $existingItems[$index]['image'];
            }

            $items[] = [
                'parent' => $existingItems[$index]['parent'] ?? '',
                'item' => $sku,
                'supplier_id' => $existingItems[$index]['supplier_id'] ?? null,
                'supplier' => $existingItems[$index]['supplier'] ?? '',
                'qty' => $qty,
                'rate' => $rate,
                'amount' => $amount,
                'reason' => trim((string) ($row['reason'] ?? '')),
                'image' => $image,
                'screenshot' => $existingItems[$index]['screenshot'] ?? null,
                'video_link' => $existingItems[$index]['video_link'] ?? null,
                'video_file' => $existingItems[$index]['video_file'] ?? null,
            ];
        }

        if ($items === []) {
            return response()->json([
                'success' => false,
                'message' => 'At least one claim line with a SKU is required.',
            ], 422);
        }

        $history = $claim->action_history ?: [];
        $history[] = [
            'action' => 'Items updated (PO proforma)',
            'user' => Auth::user()->name ?? 'System',
            'date' => now()->format('j M'),
            'datetime' => now()->format('j M Y, g:i A'),
        ];

        $payload = [
            'items' => $items,
            'total_amount' => $totalAmount,
            'action_history' => $history,
        ];
        if ($request->exists('received_amount')) {
            $payload['received_amount'] = $request->input('received_amount');
        }
        if ($request->exists('details_note')) {
            $payload['details_note'] = $request->input('details_note');
        }

        $claim->update($payload);

        return response()->json([
            'success' => true,
            'message' => 'Claim updated successfully.',
            'claim' => [
                'id' => $claim->id,
                'claim_number' => $claim->claim_number,
                'total_amount' => $claim->total_amount,
                'received_amount' => $claim->received_amount,
                'details_note' => $claim->details_note,
                'items' => $claim->items,
            ],
        ]);
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
        if (! $this->userCanArchive()) {
            return response()->json([
                'success' => false,
                'message' => 'Only directors can archive / resolve claims.',
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
