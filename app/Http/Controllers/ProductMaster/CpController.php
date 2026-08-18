<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Models\CpHistory;
use App\Models\ProductMaster;
use App\Models\User;
use App\Support\SuperAdminAccess;
use Carbon\Carbon;
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
        'mgr-content@5core.com',
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
     * Daily CP series for the Old CP / CP Master history graph.
     * Built from cp_histories (step changes) plus today's live Values.cp.
     * Default window is 365 days; leading empty days are dropped when we
     * only have a shorter stretch of data.
     */
    public function chart(Request $request)
    {
        $request->headers->set('Accept', 'application/json');

        $validated = $request->validate([
            'sku' => 'required|string',
            'days' => 'nullable|integer|min:0|max:730',
            'current_cp' => 'nullable|numeric',
        ]);

        $sku = trim($validated['sku']);
        $days = (int) ($validated['days'] ?? 365);
        if ($days <= 0) {
            $days = 365;
        }
        if ($days > 0 && $days < 7) {
            $days = 7;
        }

        $product = ProductMaster::query()
            ->whereRaw('TRIM(UPPER(sku)) = ?', [strtoupper($sku)])
            ->first();

        $currentCp = $product ? $this->currentCp($product) : null;
        if ($currentCp === null && isset($validated['current_cp']) && is_numeric($validated['current_cp'])) {
            $currentCp = (float) $validated['current_cp'];
        }

        $tz = 'America/Los_Angeles';
        $windowStart = now($tz)->startOfDay()->subDays($days)->toDateString();

        $skuKeys = array_values(array_unique(array_filter([
            $sku,
            $product?->sku,
        ])));

        $records = CpHistory::query()
            ->where(function ($q) use ($skuKeys, $sku) {
                $q->whereIn('sku', $skuKeys)
                    ->orWhereRaw('TRIM(UPPER(sku)) = ?', [strtoupper($sku)]);
            })
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['old_cp', 'new_cp', 'created_at']);

        $byDate = [];
        $priorCp = null;
        foreach ($records as $row) {
            if (! is_numeric($row->new_cp)) {
                continue;
            }
            $created = $row->created_at instanceof Carbon
                ? $row->created_at->copy()->timezone($tz)
                : Carbon::parse((string) $row->created_at, $tz);
            $date = $created->toDateString();
            $value = (float) $row->new_cp;
            if ($date < $windowStart) {
                $priorCp = $value;
                continue;
            }
            if ($priorCp === null && is_numeric($row->old_cp)) {
                $priorCp = (float) $row->old_cp;
            }
            $byDate[$date] = $value;
        }

        if ($currentCp !== null) {
            $byDate[now($tz)->toDateString()] = round((float) $currentCp, 2);
        }

        if ($byDate === [] && $priorCp === null) {
            return response()->json([
                'success' => true,
                'sku' => $product?->sku ?? $sku,
                'current_cp' => $currentCp,
                'data' => [],
            ]);
        }

        $series = $this->fillDailyCpChartSeries($byDate, $days, $priorCp);
        if ($priorCp === null) {
            $first = null;
            foreach ($series as $i => $point) {
                if ($point['value'] !== null) {
                    $first = $i;
                    break;
                }
            }
            $series = $first === null ? [] : array_values(array_slice($series, $first));
        }

        return response()->json([
            'success' => true,
            'sku' => $product?->sku ?? $sku,
            'current_cp' => $currentCp,
            'data' => $series,
        ]);
    }

    /**
     * @param  array<string, float|int>  $byDateKey
     * @return list<array{date: string, value: float|null}>
     */
    private function fillDailyCpChartSeries(array $byDateKey, int $days, ?float $priorCp): array
    {
        $tz = 'America/Los_Angeles';
        $end = now($tz)->startOfDay();
        $start = $end->copy()->subDays($days);
        $last = $priorCp;
        $hasAny = $priorCp !== null;
        $out = [];

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $key = $d->toDateString();
            if (array_key_exists($key, $byDateKey) && is_numeric($byDateKey[$key])) {
                $last = (float) $byDateKey[$key];
                $hasAny = true;
                $out[] = [
                    'date' => $d->format('M j'),
                    'value' => $last,
                ];
                continue;
            }
            $out[] = [
                'date' => $d->format('M j'),
                'value' => $hasAny ? (float) $last : null,
            ];
        }

        return $out;
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
                'message' => 'Only inventory@5core.com, president@5core.com or mgr-content@5core.com can approve a CP change.',
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
     * Unarchive a previously approved/archived CP history entry so it returns
     * to the active (pending) history. Restricted to the configured approvers.
     */
    public function unarchive(Request $request)
    {
        $request->headers->set('Accept', 'application/json');

        $validated = $request->validate([
            'id' => 'required|integer',
        ]);

        if (! $this->userCanApprove()) {
            return response()->json([
                'success' => false,
                'message' => 'Only inventory@5core.com, president@5core.com or mgr-content@5core.com can unarchive a CP change.',
            ], 403);
        }

        $entry = CpHistory::find($validated['id']);
        if (! $entry) {
            return response()->json([
                'success' => false,
                'message' => 'History entry not found.',
            ], 404);
        }

        // Reverting the archive also reverts the approval that caused it,
        // so the entry becomes pending again in the active history.
        $entry->archived = false;
        $entry->archived_at = null;
        $entry->approved = false;
        $entry->approved_by = null;
        $entry->approved_at = null;
        $entry->save();

        return response()->json([
            'success' => true,
            'message' => 'CP change unarchived and moved back to pending.',
        ]);
    }

    /**
     * Whether the currently authenticated user may approve CP changes.
     */
    private function userCanApprove(): bool
    {
        return SuperAdminAccess::allows(auth()->user(), self::APPROVER_EMAILS);
    }
}
