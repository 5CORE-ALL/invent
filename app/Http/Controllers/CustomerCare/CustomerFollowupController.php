<?php

namespace App\Http\Controllers\CustomerCare;

use App\Http\Controllers\Controller;
use App\Models\ChannelMaster;
use App\Models\CustomerFollowup;
use App\Models\ProductMaster;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerFollowupController extends Controller
{
    /** Shown in filters and as suggestions; users may still assign any name via free text. */
    private const DEFAULT_EXECUTIVES = ['Hritiksha', 'Jasmine', 'Suman'];

    /** Drop accidental notes pasted into `assigned_executive` so they don’t appear as filter options. */
    private static function executiveLabelIsFilterEligible(string $raw): bool
    {
        $t = trim($raw);

        return $t !== '' && mb_strlen($t) <= 48;
    }

    /** Mean seconds from ticket `created_at` to `resolved_at` (Resolved / green rows only). */
    private static function averageResolvedTatSeconds(): ?float
    {
        $rows = CustomerFollowup::query()
            ->where('status', 'Resolved')
            ->whereNotNull('resolved_at')
            ->get(['created_at', 'resolved_at']);

        if ($rows->isEmpty()) {
            return null;
        }

        return (float) $rows->avg(static function (CustomerFollowup $r): float {
            return (float) max(
                0,
                $r->created_at->diffInSeconds($r->resolved_at)
            );
        });
    }

    /** Short label for avg TAT badge (e.g. "2d 5h", "45m"). */
    private static function formatAverageTatLabel(?float $avgSeconds): string
    {
        if ($avgSeconds === null || $avgSeconds <= 0) {
            return '—';
        }

        if ($avgSeconds < 60) {
            return sprintf('%ds', max(1, (int) round($avgSeconds)));
        }

        $secs = (int) round($avgSeconds);
        $days = intdiv($secs, 86400);
        $secs %= 86400;
        $hours = intdiv($secs, 3600);
        $secs %= 3600;
        $minutes = intdiv($secs, 60);

        if ($days > 0) {
            return sprintf('%dd %dh', $days, $hours);
        }
        if ($hours > 0) {
            return sprintf('%dh %dm', $hours, $minutes);
        }

        return sprintf('%dm', max(1, $minutes));
    }

    /** Active marketplaces — same filter as /all-marketplace-master (getViewChannelData). */
    private static function activeChannelsQuery()
    {
        return ChannelMaster::query()
            ->whereRaw('LOWER(TRIM(status)) = ?', ['active'])
            ->orderBy('type')
            ->orderBy('id');
    }

    /** `datetime-local` value (app timezone), no seconds. */
    private static function toDatetimeLocalString(?CarbonInterface $dt, string $tz): string
    {
        if (!$dt) {
            return '';
        }

        return Carbon::parse($dt)->timezone($tz)->format('Y-m-d\TH:i');
    }

    /**
     * Channels for dropdown: channel_master.id + display name = channel column.
     * // Future: Replace with API-based channel fetch
     */
    public function index()
    {
        $channels = self::activeChannelsQuery()
            ->get(['id', 'channel'])
            ->map(fn ($r) => (object) ['id' => $r->id, 'name' => $r->channel]);

        if ($channels->isEmpty()) {
            $channels = collect([
                (object) ['id' => 0, 'name' => 'Amazon'],
                (object) ['id' => 0, 'name' => 'Flipkart'],
                (object) ['id' => 0, 'name' => 'Shopify'],
                (object) ['id' => 0, 'name' => 'Website'],
                (object) ['id' => 0, 'name' => 'WhatsApp'],
            ]);
        }

        $canDeleteFollowups = self::userMayDeleteFollowups();

        return view('customer-care.customer_followups', compact('channels', 'canDeleteFollowups'));
    }

    /** Only this account may delete follow-ups (UI + API). */
    private static function userMayDeleteFollowups(): bool
    {
        $email = strtolower(trim((string) auth()->user()?->email ?? ''));

        return $email === 'president@5core.com';
    }

    /**
     * SKU suggestions from product_master (same source as Product Master table), for follow-up form autocomplete.
     *
     * Note: many SKUs share a broad substring (e.g. "GS" → 100+ matches). A flat ORDER BY sku + small limit
     * hides valid rows (e.g. "GSTOOL RND YLW REST" was past row 25 for "GSTOOL"). Prefer prefix / exact matches.
     */
    public function searchProductSkus(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        // "GS" can match 100+ SKUs; cap high enough that alphabetically late SKUs (e.g. "GSTOOL RND YLW REST") still return.
        $limit = min(200, max(1, (int) $request->query('limit', 80)));

        if ($q === '') {
            return response()->json(['skus' => []]);
        }

        $esc = addcslashes($q, '%_\\');
        $like = '%' . $esc . '%';
        $prefix = $esc . '%';
        $upperQ = strtoupper($q);

        $rows = ProductMaster::query()
            ->select(['sku', 'parent'])
            ->where(function ($qq) use ($like) {
                $qq->where('sku', 'like', $like)
                    ->orWhere('parent', 'like', $like);
            })
            ->orderByRaw(
                'CASE
                    WHEN UPPER(TRIM(sku)) = ? THEN 0
                    WHEN sku LIKE ? THEN 1
                    WHEN UPPER(TRIM(parent)) = ? THEN 2
                    WHEN parent LIKE ? THEN 3
                    ELSE 4
                END',
                [$upperQ, $prefix, $upperQ, $prefix]
            )
            ->orderBy('sku')
            ->limit($limit)
            ->get();

        return response()->json([
            'skus' => $rows->map(fn ($r) => [
                'sku' => $r->sku,
                'parent' => $r->parent,
            ])->values(),
        ]);
    }

    /** // TODO: Replace static data with API integration */
    public function data(Request $request)
    {
        $q = CustomerFollowup::query()->with('channelMaster');

        if ($request->filled('search')) {
            $s = '%' . addcslashes(trim($request->search), '%_\\') . '%';
            $q->where(function ($qq) use ($s) {
                $qq->where('ticket_id', 'like', $s)
                    ->orWhere('order_id', 'like', $s)
                    ->orWhere('sku', 'like', $s)
                    ->orWhere('customer_name', 'like', $s)
                    ->orWhere('comments', 'like', $s)
                    ->orWhere('internal_remarks', 'like', $s)
                    ->orWhere('status', 'like', $s)
                    ->orWhere('assigned_executive', 'like', $s)
                    ->orWhereHas('channelMaster', function ($ch) use ($s) {
                        $ch->where('channel', 'like', $s);
                    });
            });
        }
        if ($request->filled('channel_id')) {
            $q->where('channel_master_id', $request->channel_id);
        }
        if ($request->filled('status')) {
            if ($request->status === 'all') {
                // Match default list: hide Resolved (green status dot) until user picks "Resolved".
                $q->where('status', '!=', 'Resolved');
            } else {
                $q->where('status', $request->status);
            }
        } else {
            // Default list: hide Resolved (green) unless a status filter is chosen.
            $q->where('status', '!=', 'Resolved');
        }
        if ($request->filled('executive')) {
            $q->where('assigned_executive', 'like', '%' . addcslashes(trim($request->executive), '%_\\') . '%');
        }
        if ($request->filled('date_from')) {
            $q->whereDate('followup_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $q->whereDate('followup_date', '<=', $request->date_to);
        }

        $rows = $q->orderBy('followup_date')->orderBy('id')->get();

        $tz = config('app.timezone');
        $now = Carbon::now($tz);
        $today = $now->toDateString();

        $total = CustomerFollowup::count();
        $pending = CustomerFollowup::where('status', 'Pending')->count();
        $resolvedToday = CustomerFollowup::where('status', 'Resolved')
            ->whereDate('updated_at', $today)
            ->count();
        $escalations = CustomerFollowup::where('status', 'Escalated')->count();

        $tatAvgSeconds = self::averageResolvedTatSeconds();

        $data = $rows->map(function (CustomerFollowup $f) use ($tz) {
            $time = $f->followup_time
                ? (is_string($f->followup_time) ? substr($f->followup_time, 0, 5) : $f->followup_time->format('H:i'))
                : '';
            $next = $f->next_followup_at
                ? strtolower(Carbon::parse($f->next_followup_at)->timezone($tz)->format('d M Y H:i'))
                : '—';
            $followupDate = $f->followup_date ? $f->followup_date->format('d M') : '';
            $followupDt = $followupDate !== '' ? $followupDate . ($time ? ' ' . $time : '') : '—';

            $notes = $f->comments;
            $notesStr = $notes !== null && $notes !== '' ? (string) $notes : '';

            return [
                'id' => $f->id,
                'ticket_id' => $f->ticket_id,
                'order_id' => $f->order_id !== null && $f->order_id !== '' ? $f->order_id : '—',
                'sku' => $f->sku !== null && $f->sku !== '' ? $f->sku : '—',
                'notes' => $notesStr,
                'channel_name' => $f->channelMaster?->channel ?? '—',
                'customer_name' => $f->customer_name,
                'status' => $f->status,
                'followup_display' => $followupDt,
                'next_followup' => $next,
                'next_followup_at' => self::toDatetimeLocalString($f->next_followup_at, $tz),
                'executive' => $f->assigned_executive ?? '—',
                'reference_link' => $f->reference_link,
                'overdue' => $f->isOverdue(),
            ];
        });

        $fromDb = CustomerFollowup::query()
            ->whereNotNull('assigned_executive')
            ->where('assigned_executive', '!=', '')
            ->distinct()
            ->pluck('assigned_executive')
            ->filter()
            ->filter(fn ($e) => self::executiveLabelIsFilterEligible((string) $e))
            ->values();

        $executives = collect(self::DEFAULT_EXECUTIVES)
            ->merge($fromDb)
            ->map(fn ($e) => is_string($e) ? trim($e) : $e)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return response()->json([
            'data' => $data,
            'executives' => $executives,
            'stats' => [
                'total' => $total,
                'pending' => $pending,
                'resolved_today' => $resolvedToday,
                'escalations' => $escalations,
                'tat_avg_seconds' => $tatAvgSeconds,
                'tat_avg_label' => self::formatAverageTatLabel($tatAvgSeconds),
            ],
        ]);
    }

    private function validateChannelMasterId(?int $id): ?int
    {
        if (empty($id)) {
            return null;
        }
        $ok = self::activeChannelsQuery()->where('id', $id)->exists();

        return $ok ? $id : null;
    }

    /** Stored on each save; taken from the authenticated user (name, or email if name empty). */
    private function executiveNameFromAuth(): string
    {
        $user = auth()->user();
        if (!$user) {
            return '';
        }
        $name = trim((string) $user->name);
        if ($name !== '') {
            return $name;
        }

        return trim((string) ($user->email ?? ''));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'nullable|string|max:64',
            'sku' => 'nullable|string|max:128',
            'channel_master_id' => 'nullable|integer',
            'customer_name' => 'nullable|string|max:255',
            'issue_type' => ['nullable', Rule::in(['Payment', 'Delivery', 'Return', 'Refund', 'Other'])],
            'status' => ['required', Rule::in(['Pending', 'Resolved', 'Escalated'])],
            'followup_date' => 'nullable|date',
            'followup_time' => 'nullable|date_format:H:i',
            'next_followup_at' => 'nullable|date',
            'comments' => 'nullable|string',
            'reference_link' => 'nullable|string|max:512',
        ]);

        if (!empty($validated['reference_link']) && !filter_var($validated['reference_link'], FILTER_VALIDATE_URL)) {
            return response()->json(['success' => false, 'message' => 'Reference link must be a valid URL.', 'errors' => ['reference_link' => ['Invalid URL']]], 422);
        }

        $validated['customer_name'] = isset($validated['customer_name']) && trim((string) $validated['customer_name']) !== ''
            ? trim($validated['customer_name'])
            : '';

        $validated['issue_type'] = isset($validated['issue_type']) && $validated['issue_type'] !== ''
            ? $validated['issue_type']
            : 'Other';

        $validated['channel_master_id'] = $this->validateChannelMasterId(
            isset($validated['channel_master_id']) ? (int) $validated['channel_master_id'] : null
        );
        $tz = config('app.timezone');
        if (empty($validated['followup_date'])) {
            $validated['followup_date'] = Carbon::today($tz)->toDateString();
        }
        if (!isset($validated['followup_time']) || $validated['followup_time'] === null || $validated['followup_time'] === '') {
            $validated['followup_time'] = Carbon::now($tz)->format('H:i:s');
        }

        $validated['priority'] = 'Medium';
        $validated['assigned_executive'] = $this->executiveNameFromAuth();

        $validated['resolved_at'] = $validated['status'] === 'Resolved'
            ? Carbon::now(config('app.timezone'))
            : null;

        $validated['ticket_id'] = CustomerFollowup::temporaryTicketId();
        $followup = CustomerFollowup::create($validated);
        $ticketId = CustomerFollowup::assignTicketIdFromPrimaryKey($followup);

        return response()->json([
            'success' => true,
            'message' => 'Follow-up created.',
            'ticket_id' => $ticketId,
            'id' => $followup->id,
        ]);
    }

    public function show(CustomerFollowup $customer_followup)
    {
        $customer_followup->load('channelMaster');
        $f = $customer_followup;
        $time = $f->followup_time
            ? (is_string($f->followup_time) ? substr($f->followup_time, 0, 5) : $f->followup_time->format('H:i'))
            : '';

        return response()->json([
            'id' => $f->id,
            'ticket_id' => $f->ticket_id,
            'order_id' => $f->order_id,
            'sku' => $f->sku,
            'channel_master_id' => $f->channel_master_id,
            'customer_name' => $f->customer_name,
            'status' => $f->status,
            'followup_date' => $f->followup_date ? $f->followup_date->format('Y-m-d') : '',
            'followup_time' => $time,
            'next_followup_at' => $f->next_followup_at ? $f->next_followup_at->format('Y-m-d\TH:i') : '',
            'comments' => $f->comments,
            'reference_link' => $f->reference_link,
        ]);
    }

    public function update(Request $request, CustomerFollowup $customer_followup)
    {
        $validated = $request->validate([
            'ticket_id' => ['required', 'string', 'max:64', Rule::unique('customer_followups', 'ticket_id')->ignore($customer_followup->id)],
            'order_id' => 'nullable|string|max:64',
            'sku' => 'nullable|string|max:128',
            'channel_master_id' => 'nullable|integer',
            'customer_name' => 'nullable|string|max:255',
            'issue_type' => ['nullable', Rule::in(['Payment', 'Delivery', 'Return', 'Refund', 'Other'])],
            'status' => ['required', Rule::in(['Pending', 'Resolved', 'Escalated'])],
            'followup_date' => 'nullable|date',
            'followup_time' => 'nullable|date_format:H:i',
            'next_followup_at' => 'nullable|date',
            'comments' => 'nullable|string',
            'reference_link' => 'nullable|string|max:512',
        ]);

        if (!empty($validated['reference_link']) && !filter_var($validated['reference_link'], FILTER_VALIDATE_URL)) {
            return response()->json(['success' => false, 'message' => 'Reference link must be a valid URL.'], 422);
        }

        $validated['customer_name'] = isset($validated['customer_name']) && trim((string) $validated['customer_name']) !== ''
            ? trim($validated['customer_name'])
            : '';

        if (array_key_exists('issue_type', $validated) && $validated['issue_type'] === null) {
            unset($validated['issue_type']);
        }

        $validated['channel_master_id'] = $this->validateChannelMasterId(
            isset($validated['channel_master_id']) ? (int) $validated['channel_master_id'] : null
        );
        if (empty($validated['followup_date'])) {
            $validated['followup_date'] = $customer_followup->followup_date?->toDateString()
                ?? Carbon::today(config('app.timezone'))->toDateString();
        }

        $validated['assigned_executive'] = $this->executiveNameFromAuth();

        if ($validated['status'] === 'Resolved') {
            if ($customer_followup->status !== 'Resolved') {
                $validated['resolved_at'] = Carbon::now(config('app.timezone'));
            }
        } else {
            $validated['resolved_at'] = null;
        }

        unset($validated['ticket_id']);

        $customer_followup->update($validated);

        return response()->json(['success' => true, 'message' => 'Updated.']);
    }

    public function destroy(CustomerFollowup $customer_followup)
    {
        if (!self::userMayDeleteFollowups()) {
            return response()->json(['success' => false, 'message' => 'You are not allowed to delete follow-ups.'], 403);
        }

        $customer_followup->delete();

        return response()->json(['success' => true, 'message' => 'Deleted.']);
    }

    /** Inline edit for the Next column only (`datetime-local`). Date column is read-only in the grid. */
    public function patchInlineDates(Request $request, CustomerFollowup $customer_followup): JsonResponse
    {
        if (!$request->exists('next_followup_at')) {
            return response()->json(['success' => false, 'message' => 'Nothing to update.'], 422);
        }

        if ($request->input('next_followup_at') === '') {
            $request->merge(['next_followup_at' => null]);
        }

        $validated = $request->validate([
            'next_followup_at' => 'nullable|date',
        ]);

        $tz = config('app.timezone');
        $v = $validated['next_followup_at'] ?? null;
        $customer_followup->next_followup_at = $v === null ? null : Carbon::parse($v, $tz);
        $customer_followup->save();

        return response()->json(['success' => true, 'message' => 'Saved.']);
    }

    /** Inline edit for status column (Pending / Resolved / Escalated). */
    public function patchInlineStatus(Request $request, CustomerFollowup $customer_followup): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['Pending', 'Resolved', 'Escalated'])],
        ]);

        $newStatus = $validated['status'];
        if ($newStatus === 'Resolved') {
            if ($customer_followup->status !== 'Resolved') {
                $customer_followup->resolved_at = Carbon::now(config('app.timezone'));
            }
        } else {
            $customer_followup->resolved_at = null;
        }

        $customer_followup->status = $newStatus;
        $customer_followup->assigned_executive = $this->executiveNameFromAuth();
        $customer_followup->save();

        return response()->json(['success' => true, 'message' => 'Saved.']);
    }
}
