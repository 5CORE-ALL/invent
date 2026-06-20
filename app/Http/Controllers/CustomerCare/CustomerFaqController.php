<?php

namespace App\Http\Controllers\CustomerCare;

use App\Http\Controllers\Controller;
use App\Models\CustomerFaq;
use App\Models\ResourceDepartment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class CustomerFaqController extends Controller
{
    private function userEmail(Request $request): string
    {
        return strtolower(trim((string) ($request->user()->email ?? '')));
    }

    /**
     * Append a row to the FAQ's edit / escalation history JSON.
     */
    private function appendHistory(?array $history, string $email, string $action, ?array $extra = null): array
    {
        $history = is_array($history) ? $history : [];
        $entry = [
            'email' => $email,
            'action' => $action,
            'at' => now()->toDateTimeString(),
        ];
        if (! empty($extra)) {
            $entry = array_merge($entry, $extra);
        }
        $history[] = $entry;

        return $history;
    }

    public function index(Request $request)
    {
        $departments = Schema::hasTable('resource_departments')
            ? ResourceDepartment::orderBy('name')->get()
            : collect();
        $deptNames = $departments->pluck('name', 'id');

        $perPage = (int) ($request->input('per_page', 50));
        if (! in_array($perPage, [25, 50, 100, 200, 500], true)) {
            $perPage = 50;
        }

        $query = Schema::hasTable('customer_faqs') ? CustomerFaq::query() : null;

        // Optional filters used by the page header.
        if ($query) {
            if ($status = trim((string) $request->input('status', ''))) {
                $query->where('status', $status);
            }
            if ($severity = trim((string) $request->input('severity', ''))) {
                $query->where('severity', $severity);
            }
            if ($customerType = trim((string) $request->input('customer_type', ''))) {
                $query->where('customer_type', $customerType);
            }
            if ($q = trim((string) $request->input('q', ''))) {
                $like = '%' . $q . '%';
                $query->where(function ($w) use ($like) {
                    $w->where('faq', 'like', $like)
                        ->orWhere('answers', 'like', $like)
                        ->orWhere('group_name', 'like', $like)
                        ->orWhere('action', 'like', $like)
                        ->orWhere('messages', 'like', $like);
                });
            }
            $faqs = $query->orderByDesc('id')->paginate($perPage)->withQueryString();
        } else {
            $faqs = collect();
        }

        $stats = [
            'total' => 0,
            'escalated' => 0,
            'open' => 0,
            'resolved' => 0,
            'critical' => 0,
        ];
        if (Schema::hasTable('customer_faqs')) {
            $stats['total'] = (int) CustomerFaq::count();
            $stats['escalated'] = (int) CustomerFaq::where('current_escalation_level', '>', 0)
                ->whereNotIn('status', ['resolved', 'closed'])
                ->count();
            $stats['open'] = (int) CustomerFaq::where('status', 'open')->count();
            $stats['resolved'] = (int) CustomerFaq::whereIn('status', ['resolved', 'closed'])->count();
            $stats['critical'] = (int) CustomerFaq::where('severity', 'critical')->count();
        }

        return view('customer-care.customer-faqs', [
            'faqs' => $faqs,
            'departments' => $departments,
            'deptNames' => $deptNames,
            'perPage' => $perPage,
            'stats' => $stats,
            'statusOptions' => CustomerFaq::STATUS_OPTIONS,
            'severityOptions' => CustomerFaq::SEVERITY_OPTIONS,
            'customerTypes' => CustomerFaq::CUSTOMER_TYPES,
            'filters' => [
                'status' => $request->input('status', ''),
                'severity' => $request->input('severity', ''),
                'customer_type' => $request->input('customer_type', ''),
                'q' => $request->input('q', ''),
            ],
        ]);
    }

    /**
     * JSON feed for the Tabulator table. Returns the full result set so the
     * client can search/sort/filter without round-trips. The Tabulator page
     * itself handles pagination via the `pagination: "local"` setting.
     */
    public function data(Request $request): JsonResponse
    {
        if (! Schema::hasTable('customer_faqs')) {
            return response()->json([]);
        }

        $rows = CustomerFaq::orderByDesc('id')->get();

        $payload = $rows->map(function (CustomerFaq $r) {
            return [
                'id' => $r->id,
                'group_name' => $r->group_name,
                'faq' => $r->faq,
                'answers' => $r->answers,
                'customer_type' => $r->customer_type,
                'severity' => $r->severity,
                'status' => $r->status,
                'type_variant' => $r->type_variant,
                'what' => $r->what,
                'link' => $r->link,
                'link2' => $r->link2,
                'sop' => $r->sop,
                'video' => $r->video,
                'action' => $r->action,
                'ca' => $r->ca,
                'plus_action' => $r->plus_action,
                'messages' => $r->messages,
                'escalation_l1_role' => $r->escalation_l1_role,
                'escalation_l1_name' => $r->escalation_l1_name,
                'escalation_l1_email' => $r->escalation_l1_email,
                'escalation_l1_sla' => $r->escalation_l1_sla,
                'escalation_l2_role' => $r->escalation_l2_role,
                'escalation_l2_name' => $r->escalation_l2_name,
                'escalation_l2_email' => $r->escalation_l2_email,
                'escalation_l2_sla' => $r->escalation_l2_sla,
                'escalation_l3_role' => $r->escalation_l3_role,
                'escalation_l3_name' => $r->escalation_l3_name,
                'escalation_l3_email' => $r->escalation_l3_email,
                'escalation_l3_sla' => $r->escalation_l3_sla,
                'current_escalation_level' => (int) $r->current_escalation_level,
                'escalated_at' => optional($r->escalated_at)->toDateTimeString(),
                'escalated_at_human' => optional($r->escalated_at)->diffForHumans(),
                'escalated_by_email' => $r->escalated_by_email,
                'escalated_to_email' => $r->escalated_to_email,
                'escalation_reason' => $r->escalation_reason,
                'resolved_at' => optional($r->resolved_at)->toDateTimeString(),
                'resolved_at_human' => optional($r->resolved_at)->diffForHumans(),
                'resolved_by_email' => $r->resolved_by_email,
                'resolution_note' => $r->resolution_note,
                'escalation_log' => $r->escalation_log ?? [],
                'created_by_email' => $r->created_by_email,
                'updated_by_email' => $r->updated_by_email,
                'created_at' => optional($r->created_at)->toDateTimeString(),
                'updated_at' => optional($r->updated_at)->toDateTimeString(),
                'updated_at_human' => optional($r->updated_at)->diffForHumans(),
            ];
        });

        return response()->json($payload);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $email = $this->userEmail($request);
        $data['created_by_email'] = $email;
        $data['updated_by_email'] = $email;
        $data['edit_history'] = $this->appendHistory([], $email, 'added');

        CustomerFaq::create($data);

        return redirect()->route('customer.care.faq.customers.index')
            ->with('success', 'Customer FAQ added successfully.');
    }

    public function update(Request $request, CustomerFaq $customer_faq)
    {
        $data = $this->validateData($request);
        $email = $this->userEmail($request);
        $data['updated_by_email'] = $email;
        $data['edit_history'] = $this->appendHistory(
            $customer_faq->edit_history,
            $email,
            'edited'
        );

        $customer_faq->update($data);

        return redirect()->route('customer.care.faq.customers.index')
            ->with('success', 'Customer FAQ updated.');
    }

    public function destroy(Request $request, CustomerFaq $customer_faq)
    {
        $customer_faq->delete(); // soft delete

        return redirect()->route('customer.care.faq.customers.index')
            ->with('success', 'Customer FAQ archived. It can be restored later.');
    }

    /**
     * Trigger or step-up an escalation for a row.
     * Bumps current_escalation_level, sets status = "escalated"
     * and records the action in escalation_log.
     */
    public function escalate(Request $request, CustomerFaq $customer_faq)
    {
        $validated = $request->validate([
            'level' => ['nullable', 'integer', 'min:1', 'max:3'],
            'reason' => ['nullable', 'string', 'max:5000'],
            'escalated_to_email' => ['nullable', 'email', 'max:255'],
        ]);

        $level = (int) ($validated['level'] ?? 0);
        if ($level <= 0) {
            $level = min(3, max(1, ((int) $customer_faq->current_escalation_level) + 1));
        }

        // Default to the matching escalation_lN_email if none was passed.
        $toEmail = trim((string) ($validated['escalated_to_email'] ?? ''));
        if ($toEmail === '') {
            $toEmail = (string) ($customer_faq->{'escalation_l' . $level . '_email'} ?? '');
        }

        $email = $this->userEmail($request);
        $now = now();

        $log = is_array($customer_faq->escalation_log) ? $customer_faq->escalation_log : [];
        $log[] = [
            'level' => $level,
            'at' => $now->toDateTimeString(),
            'by_email' => $email,
            'to_email' => $toEmail ?: null,
            'reason' => trim((string) ($validated['reason'] ?? '')) ?: null,
        ];

        $customer_faq->update([
            'current_escalation_level' => $level,
            'status' => 'escalated',
            'escalated_at' => $now,
            'escalated_by_email' => $email,
            'escalated_to_email' => $toEmail ?: null,
            'escalation_reason' => $validated['reason'] ?? $customer_faq->escalation_reason,
            'escalation_log' => $log,
            'updated_by_email' => $email,
            'edit_history' => $this->appendHistory(
                $customer_faq->edit_history,
                $email,
                'escalated to L' . $level
            ),
        ]);

        return redirect()->route('customer.care.faq.customers.index')
            ->with('success', "Escalated to Level {$level}.");
    }

    /**
     * Mark an escalated FAQ as resolved.
     */
    public function resolve(Request $request, CustomerFaq $customer_faq)
    {
        $validated = $request->validate([
            'resolution_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $email = $this->userEmail($request);
        $now = now();

        $log = is_array($customer_faq->escalation_log) ? $customer_faq->escalation_log : [];
        $log[] = [
            'level' => (int) $customer_faq->current_escalation_level,
            'at' => $now->toDateTimeString(),
            'by_email' => $email,
            'action' => 'resolved',
            'note' => trim((string) ($validated['resolution_note'] ?? '')) ?: null,
        ];

        $customer_faq->update([
            'status' => 'resolved',
            'resolved_at' => $now,
            'resolved_by_email' => $email,
            'resolution_note' => $validated['resolution_note'] ?? $customer_faq->resolution_note,
            'escalation_log' => $log,
            'updated_by_email' => $email,
            'edit_history' => $this->appendHistory(
                $customer_faq->edit_history,
                $email,
                'resolved'
            ),
        ]);

        return redirect()->route('customer.care.faq.customers.index')
            ->with('success', 'Marked as resolved.');
    }

    /**
     * Quick inline status change (e.g. open → in_progress).
     */
    public function updateStatus(Request $request, CustomerFaq $customer_faq)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(CustomerFaq::STATUS_OPTIONS))],
        ]);

        $email = $this->userEmail($request);

        $customer_faq->update([
            'status' => $validated['status'],
            'updated_by_email' => $email,
            'edit_history' => $this->appendHistory(
                $customer_faq->edit_history,
                $email,
                'status → ' . $validated['status']
            ),
        ]);

        return back()->with('success', 'Status updated.');
    }

    private function validateData(Request $request): array
    {
        $validated = $request->validate([
            'group_name' => ['nullable', 'string', 'max:255'],
            'faq' => ['required', 'string'],
            'answers' => ['nullable', 'string'],
            'customer_type' => ['nullable', 'string', 'max:120'],
            'dept' => ['nullable', 'array'],
            'dept.*' => ['string'],
            'severity' => ['nullable', Rule::in(array_keys(CustomerFaq::SEVERITY_OPTIONS))],
            'status' => ['nullable', Rule::in(array_keys(CustomerFaq::STATUS_OPTIONS))],
            'type_variant' => ['nullable', 'string'],
            'what' => ['nullable', 'string'],
            'link' => ['nullable', 'string', 'max:500'],
            'link2' => ['nullable', 'string', 'max:500'],
            'sop' => ['nullable', 'string', 'max:500'],
            'video' => ['nullable', 'string', 'max:500'],
            'action' => ['nullable', 'string'],
            'ca' => ['nullable', 'string'],
            'plus_action' => ['nullable', 'string'],
            'messages' => ['nullable', 'string'],

            'escalation_l1_role' => ['nullable', 'string', 'max:120'],
            'escalation_l1_name' => ['nullable', 'string', 'max:120'],
            'escalation_l1_email' => ['nullable', 'email', 'max:255'],
            'escalation_l1_sla' => ['nullable', 'string', 'max:60'],
            'escalation_l2_role' => ['nullable', 'string', 'max:120'],
            'escalation_l2_name' => ['nullable', 'string', 'max:120'],
            'escalation_l2_email' => ['nullable', 'email', 'max:255'],
            'escalation_l2_sla' => ['nullable', 'string', 'max:60'],
            'escalation_l3_role' => ['nullable', 'string', 'max:120'],
            'escalation_l3_name' => ['nullable', 'string', 'max:120'],
            'escalation_l3_email' => ['nullable', 'email', 'max:255'],
            'escalation_l3_sla' => ['nullable', 'string', 'max:60'],
        ]);

        $dept = $validated['dept'] ?? [];
        if (in_array('all', $dept, true)) {
            $dept = ['all'];
        }
        $validated['dept'] = array_values(array_unique($dept));

        $validated['severity'] = $validated['severity'] ?? 'medium';
        $validated['status'] = $validated['status'] ?? 'open';

        return $validated;
    }
}
