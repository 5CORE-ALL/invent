<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Models\CpHistory;
use App\Models\ProductMaster;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CpController extends Controller
{
    /**
     * Emails allowed to approve a CP change.
     */
    public const APPROVER_EMAILS = [
        'inventory@5core.com',
        'president@5core.com',
    ];

    /**
     * Read the current numeric CP from a product's Values JSON.
     */
    private function currentCp(ProductMaster $product): ?float
    {
        $values = is_array($product->Values) ? $product->Values : json_decode((string) $product->Values, true);
        if (! is_array($values)) {
            return null;
        }

        $cp = $values['cp'] ?? null;

        return is_numeric($cp) ? (float) $cp : null;
    }

    /**
     * Return the current CP and full change history (newest first) for a SKU.
     */
    public function history(Request $request)
    {
        $request->headers->set('Accept', 'application/json');

        $validated = $request->validate([
            'sku' => 'required|string',
        ]);

        $product = ProductMaster::where('sku', $validated['sku'])->first();

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        $records = CpHistory::where('sku', $validated['sku'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        // Resolve emails -> display names from the users table.
        $emails = $records->pluck('changed_by')
            ->merge($records->pluck('approved_by'))
            ->filter()
            ->unique()
            ->values();
        $namesByEmail = User::whereIn('email', $emails)->pluck('name', 'email');

        $resolveName = function (?string $email) use ($namesByEmail) {
            if (! $email) {
                return null;
            }

            return $namesByEmail[$email] ?? $email;
        };

        $history = $records->map(function (CpHistory $h) use ($resolveName) {
            return [
                'id' => $h->id,
                'old_cp' => $h->old_cp,
                'new_cp' => $h->new_cp,
                'is_increase' => (bool) $h->is_increase,
                'reason' => $h->reason,
                'changed_by' => $resolveName($h->changed_by),
                'approved' => (bool) $h->approved,
                'approved_by' => $resolveName($h->approved_by),
                'approved_at' => optional($h->approved_at)->format('j M'),
                'created_at' => optional($h->created_at)->format('j M'),
            ];
        });

        return response()->json([
            'success' => true,
            'sku' => $product->sku,
            'current_cp' => $this->currentCp($product),
            'can_approve' => $this->userCanApprove(),
            'history' => $history,
        ]);
    }

    /**
     * Return the CP change history for every SKU on one page (newest first).
     * Approved/archived entries are hidden unless ?include_archived=1 is passed.
     */
    public function allHistory(Request $request)
    {
        $request->headers->set('Accept', 'application/json');

        $includeArchived = $request->boolean('include_archived');

        $records = CpHistory::query()
            ->when(! $includeArchived, fn ($q) => $q->where('archived', false))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(2000)
            ->get();

        $emails = $records->pluck('changed_by')
            ->merge($records->pluck('approved_by'))
            ->filter()
            ->unique()
            ->values();
        $namesByEmail = User::whereIn('email', $emails)->pluck('name', 'email');

        $resolveName = function (?string $email) use ($namesByEmail) {
            if (! $email) {
                return null;
            }

            return $namesByEmail[$email] ?? $email;
        };

        $history = $records->map(function (CpHistory $h) use ($resolveName) {
            return [
                'id' => $h->id,
                'sku' => $h->sku,
                'old_cp' => $h->old_cp,
                'new_cp' => $h->new_cp,
                'is_increase' => (bool) $h->is_increase,
                'reason' => $h->reason,
                'changed_by' => $resolveName($h->changed_by),
                'approved' => (bool) $h->approved,
                'approved_by' => $resolveName($h->approved_by),
                'approved_at' => optional($h->approved_at)->format('j M'),
                'archived' => (bool) $h->archived,
                'created_at' => optional($h->created_at)->format('j M'),
            ];
        });

        return response()->json([
            'success' => true,
            'can_approve' => $this->userCanApprove(),
            'history' => $history,
        ]);
    }

    /**
     * Update the CP for a SKU.
     *
     * Rules:
     *  - First time (no existing CP): accept any value.
     *  - New value lower than or equal to current: accept.
     *  - New value higher than current: a mandatory reason is required.
     */
    public function update(Request $request)
    {
        $request->headers->set('Accept', 'application/json');

        $validated = $request->validate([
            'sku' => 'required|string',
            'cp' => 'required|numeric|min:0',
            'reason' => 'nullable|string|max:2000',
        ]);

        try {
            $product = ProductMaster::where('sku', $validated['sku'])->first();

            if (! $product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                ], 404);
            }

            $values = is_array($product->Values) ? $product->Values : json_decode((string) $product->Values, true);
            if (! is_array($values)) {
                $values = [];
            }

            $oldCp = $this->currentCp($product);
            $newCp = round((float) $validated['cp'], 2);
            $reason = trim((string) ($validated['reason'] ?? ''));

            $isIncrease = $oldCp !== null && $newCp > $oldCp;

            // A reason is mandatory whenever the CP increases.
            if ($isIncrease && $reason === '') {
                return response()->json([
                    'success' => false,
                    'requires_reason' => true,
                    'message' => 'A reason is required when the CP is increased.',
                ], 422);
            }

            $values['cp'] = $newCp;
            $product->Values = $values;
            $product->save();

            CpHistory::create([
                'sku' => $product->sku,
                'old_cp' => $oldCp,
                'new_cp' => $newCp,
                'is_increase' => $isIncrease,
                'reason' => $isIncrease ? $reason : ($reason !== '' ? $reason : null),
                'changed_by' => auth()->user()->email ?? null,
                'approved' => false,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'CP updated successfully.',
                'current_cp' => $newCp,
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating CP: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error updating CP: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Approve a CP history entry. Restricted to the configured approver emails.
     */
    public function approve(Request $request)
    {
        $request->headers->set('Accept', 'application/json');

        $validated = $request->validate([
            'id' => 'required|integer',
        ]);

        if (! $this->userCanApprove()) {
            return response()->json([
                'success' => false,
                'message' => 'Only inventory@5core.com or president@5core.com can approve a CP change.',
            ], 403);
        }

        $entry = CpHistory::find($validated['id']);
        if (! $entry) {
            return response()->json([
                'success' => false,
                'message' => 'History entry not found.',
            ], 404);
        }

        $entry->approved = true;
        $entry->approved_by = auth()->user()->email ?? null;
        $entry->approved_at = now();
        // Approving moves the entry out of the active history into the archive.
        $entry->archived = true;
        $entry->archived_at = now();
        $entry->save();

        return response()->json([
            'success' => true,
            'message' => 'CP change approved and archived.',
            'approved_by' => $entry->approved_by,
            'approved_at' => optional($entry->approved_at)->format('j M'),
        ]);
    }

    /**
     * Whether the currently authenticated user may approve CP changes.
     */
    private function userCanApprove(): bool
    {
        $email = strtolower((string) (auth()->user()->email ?? ''));

        return in_array($email, self::APPROVER_EMAILS, true);
    }
}
