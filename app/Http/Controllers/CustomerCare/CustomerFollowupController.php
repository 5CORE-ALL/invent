<?php

namespace App\Http\Controllers\CustomerCare;

use App\Http\Controllers\Controller;
use App\Models\ChannelMaster;
use App\Models\CustomerFollowup;
use App\Models\ProductMaster;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerFollowupController extends Controller
{
    /** Shown in filters and as suggestions; users may still assign any name via free text. */
    private const DEFAULT_EXECUTIVES = ['Hritiksha', 'Jasmine', 'Suman'];

    /** Active marketplaces — same filter as /all-marketplace-master (getViewChannelData). */
    private static function activeChannelsQuery()
    {
        return ChannelMaster::query()
            ->whereRaw('LOWER(TRIM(status)) = ?', ['active'])
            ->orderBy('type')
            ->orderBy('id');
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

        $defaultExecutives = self::DEFAULT_EXECUTIVES;

        return view('customer-care.customer_followups', compact('channels', 'defaultExecutives'));
    }

    /**
     * SKU suggestions from product_master (same source as Product Master table), for follow-up form autocomplete.
     */
    public function searchProductSkus(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $limit = min(40, max(1, (int) $request->query('limit', 25)));

        if ($q === '') {
            return response()->json(['skus' => []]);
        }

        $like = '%' . addcslashes($q, '%_\\') . '%';

        $rows = ProductMaster::query()
            ->select(['sku', 'parent'])
            ->where(function ($qq) use ($like) {
                $qq->where('sku', 'like', $like)
                    ->orWhere('parent', 'like', $like);
            })
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
                    ->orWhere('comments', 'like', $s);
            });
        }
        if ($request->filled('channel_id')) {
            $q->where('channel_master_id', $request->channel_id);
        }
        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }
        if ($request->filled('priority')) {
            $q->where('priority', $request->priority);
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

        $rows = $q->orderByDesc('followup_date')->orderByDesc('id')->get();

        $tz = config('app.timezone');
        $now = Carbon::now($tz);
        $today = $now->toDateString();

        $total = CustomerFollowup::count();
        $pending = CustomerFollowup::where('status', 'Pending')->count();
        $resolvedToday = CustomerFollowup::where('status', 'Resolved')
            ->whereDate('updated_at', $today)
            ->count();
        $escalations = CustomerFollowup::where('status', 'Escalated')->count();

        $data = $rows->map(function (CustomerFollowup $f) use ($tz) {
            $time = $f->followup_time
                ? (is_string($f->followup_time) ? substr($f->followup_time, 0, 5) : $f->followup_time->format('H:i'))
                : '';
            $next = $f->next_followup_at
                ? Carbon::parse($f->next_followup_at)->timezone($tz)->format('m-d-Y H:i')
                : '—';
            $followupDt = $f->followup_date->format('m-d-Y') . ($time ? ' ' . $time : '');

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
                'issue_type' => $f->issue_type,
                'status' => $f->status,
                'priority' => $f->priority,
                'followup_display' => $followupDt,
                'next_followup' => $next,
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
                'avg_response' => '—',
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'nullable|string|max:64',
            'sku' => 'nullable|string|max:128',
            'channel_master_id' => 'nullable|integer',
            'customer_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:32',
            'issue_type' => ['required', Rule::in(['Payment', 'Delivery', 'Return', 'Refund', 'Other'])],
            'status' => ['required', Rule::in(['Pending', 'In Progress', 'Resolved', 'Escalated'])],
            'priority' => ['required', Rule::in(['Low', 'Medium', 'High', 'Urgent'])],
            'followup_date' => 'required|date',
            'followup_time' => 'nullable|date_format:H:i',
            'next_followup_at' => 'nullable|date',
            'assigned_executive' => 'nullable|string|max:255',
            'comments' => 'nullable|string',
            'internal_remarks' => 'nullable|string',
            'reference_link' => 'nullable|string|max:512',
        ]);

        if (!empty($validated['reference_link']) && !filter_var($validated['reference_link'], FILTER_VALIDATE_URL)) {
            return response()->json(['success' => false, 'message' => 'Reference link must be a valid URL.', 'errors' => ['reference_link' => ['Invalid URL']]], 422);
        }

        $validated['channel_master_id'] = $this->validateChannelMasterId(
            isset($validated['channel_master_id']) ? (int) $validated['channel_master_id'] : null
        );

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
            'email' => $f->email,
            'phone' => $f->phone,
            'issue_type' => $f->issue_type,
            'status' => $f->status,
            'priority' => $f->priority,
            'followup_date' => $f->followup_date->format('Y-m-d'),
            'followup_time' => $time,
            'next_followup_at' => $f->next_followup_at ? $f->next_followup_at->format('Y-m-d\TH:i') : '',
            'assigned_executive' => $f->assigned_executive,
            'comments' => $f->comments,
            'internal_remarks' => $f->internal_remarks,
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
            'customer_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:32',
            'issue_type' => ['required', Rule::in(['Payment', 'Delivery', 'Return', 'Refund', 'Other'])],
            'status' => ['required', Rule::in(['Pending', 'In Progress', 'Resolved', 'Escalated'])],
            'priority' => ['required', Rule::in(['Low', 'Medium', 'High', 'Urgent'])],
            'followup_date' => 'required|date',
            'followup_time' => 'nullable|date_format:H:i',
            'next_followup_at' => 'nullable|date',
            'assigned_executive' => 'nullable|string|max:255',
            'comments' => 'nullable|string',
            'internal_remarks' => 'nullable|string',
            'reference_link' => 'nullable|string|max:512',
        ]);

        if (!empty($validated['reference_link']) && !filter_var($validated['reference_link'], FILTER_VALIDATE_URL)) {
            return response()->json(['success' => false, 'message' => 'Reference link must be a valid URL.'], 422);
        }

        $validated['channel_master_id'] = $this->validateChannelMasterId(
            isset($validated['channel_master_id']) ? (int) $validated['channel_master_id'] : null
        );

        unset($validated['ticket_id']);

        $customer_followup->update($validated);

        return response()->json(['success' => true, 'message' => 'Updated.']);
    }

    public function destroy(CustomerFollowup $customer_followup)
    {
        $customer_followup->delete();

        return response()->json(['success' => true, 'message' => 'Deleted.']);
    }
}
