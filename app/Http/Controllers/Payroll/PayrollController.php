<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\PayrollArrear;
use App\Models\PayrollEmployeeSalary;
use App\Models\PayrollMonth;
use App\Models\PayrollPaymentDeduction;
use App\Models\PayrollPayslip;
use App\Models\PayrollPreviousRecord;
use App\Models\PayrollSalaryComponent;
use App\Models\PayrollSettlement;
use App\Models\User;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PayrollController extends Controller
{
    public function __construct(
        protected PayrollService $payroll
    ) {}

    protected function authorizeManage(): void
    {
        abort_unless(Gate::allows('payroll.manage'), 403, 'You do not have permission to manage payroll.');
    }

    protected function ensureUnlocked(?PayrollMonth $month): void
    {
        if ($month && $month->is_locked) {
            abort(422, 'Payroll month is locked. Unlock it to make changes.');
        }
    }

    protected function arrearMonthLabel(PayrollArrear $arrear): string
    {
        if ($arrear->period_from) {
            $from = $arrear->period_from->format('F Y');
            if ($arrear->period_to && ! $arrear->period_from->isSameMonth($arrear->period_to)) {
                return $from.' – '.$arrear->period_to->format('F Y');
            }

            return $from;
        }

        return $arrear->payrollMonth?->month_label ?? '—';
    }

    public function index(): View
    {
        $canManage = Gate::allows('payroll.manage');
        $months = PayrollMonth::orderByDesc('id')->get();
        $activeMonth = $months->first();
        $users = User::query()
            ->where('is_active', true)
            ->where('show_in_salary', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('payroll.index', [
            'canManage' => $canManage,
            'months' => $months,
            'activeMonth' => $activeMonth,
            'users' => $users,
            'payslipFormats' => config('payroll.payslip_formats', []),
            'monthStatuses' => config('payroll.month_statuses', []),
            'defaultMonthLabel' => $this->payroll->defaultMonthLabel(),
        ]);
    }

    public function monthData(PayrollMonth $payrollMonth): JsonResponse
    {
        // Draft / unlocked months reflect the latest TeamLogger hours; locked months keep their snapshot.
        if (! $payrollMonth->is_locked) {
            $this->payroll->refreshLiveHours($payrollMonth);
        }

        $payrollMonth->loadCount([
            'employeeSalaries',
            'salaryComponents',
            'paymentDeductions',
            'payslips',
        ]);

        $employees = PayrollEmployeeSalary::with('user')
            ->where('payroll_month_id', $payrollMonth->id)
            ->orderBy('id')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'user_id' => $r->user_id,
                'name' => $r->user?->name,
                'email' => $r->user?->email,
                'salary_pp' => $r->salary_pp,
                'increment' => $r->increment,
                'salary_lm' => (float) $r->salary_pp + (float) $r->increment,
                'hours_worked' => $r->hours_worked,
                'amount_lm' => $r->gross_amount,
                'amount_p' => $r->net_amount,
                'gross_amount' => $r->gross_amount,
                'net_amount' => $r->net_amount,
                'is_new_hire' => $r->is_new_hire,
                'bank_1' => $r->bank_1,
                'bank_2' => $r->bank_2,
                'upi_id' => $r->upi_id,
            ]);

        $salaryByUser = PayrollEmployeeSalary::where('payroll_month_id', $payrollMonth->id)
            ->get()
            ->keyBy('user_id');

        return response()->json([
            'month' => $payrollMonth,
            'employees' => $employees,
            'components' => PayrollSalaryComponent::with('user')->where('payroll_month_id', $payrollMonth->id)->get(),
            'payments' => PayrollPaymentDeduction::with('user')->where('payroll_month_id', $payrollMonth->id)->get(),
            'payslips' => PayrollPayslip::with('user')
                ->where('payroll_month_id', $payrollMonth->id)
                ->orderBy('id')
                ->get()
                ->map(function (PayrollPayslip $p) use ($salaryByUser) {
                    $row = $salaryByUser->get($p->user_id);

                    return [
                        'id' => $p->id,
                        'user_id' => $p->user_id,
                        'format' => $p->format,
                        'released_at' => $p->released_at,
                        'user' => $p->user,
                        'net' => $row ? (float) $row->net_amount : (float) ($p->data['net'] ?? 0),
                        'amount_lm' => $row ? (float) $row->gross_amount : null,
                        'hours_worked' => $row ? (float) $row->hours_worked : null,
                    ];
                }),
            'arrears' => PayrollArrear::with(['user', 'payrollMonth'])
                ->where('payroll_month_id', $payrollMonth->id)
                ->orderByDesc('id')
                ->get()
                ->map(fn (PayrollArrear $a) => [
                    'id' => $a->id,
                    'user_id' => $a->user_id,
                    'amount' => $a->amount,
                    'adjustment_type' => $a->adjustment_type ?? 'add',
                    'signed_amount' => $a->signedAmount(),
                    'description' => $a->description,
                    'status' => $a->status,
                    'applied_at' => $a->applied_at,
                    'period_from' => $a->period_from?->format('Y-m-d'),
                    'period_to' => $a->period_to?->format('Y-m-d'),
                    'month_label' => $a->payrollMonth?->month_label,
                    'arrear_for' => $this->arrearMonthLabel($a),
                    'user' => $a->user,
                    'payroll_month' => $a->payrollMonth,
                ]),
        ]);
    }

    public function storeMonth(Request $request): JsonResponse
    {
        $this->authorizeManage();

        $validated = $request->validate([
            'month_label' => 'required|string|max:32|unique:payroll_months,month_label',
            'notes' => 'nullable|string|max:2000',
            'payslip_format' => 'nullable|string|in:standard,detailed,compact',
        ]);

        [$start, $end] = $this->payroll->periodDatesFromLabel($validated['month_label']);

        $month = PayrollMonth::create([
            'month_label' => $validated['month_label'],
            'period_start' => $start,
            'period_end' => $end,
            'status' => 'draft',
            'payslip_format' => $validated['payslip_format'] ?? 'standard',
            'notes' => $validated['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        $synced = $this->payroll->syncEmployeesFromUsers($month);
        $this->payroll->recalculateMonth($month);

        return response()->json([
            'success' => true,
            'message' => "Payroll month created. {$synced} employee(s) added.",
            'month' => $month->fresh(),
        ]);
    }

    public function syncEmployees(Request $request, PayrollMonth $payrollMonth): JsonResponse
    {
        $this->authorizeManage();
        $this->ensureUnlocked($payrollMonth);

        $userIds = $request->input('user_ids', []);
        $newOnly = (bool) $request->input('new_hires_only', false);

        $count = $this->payroll->syncEmployeesFromUsers(
            $payrollMonth,
            is_array($userIds) ? $userIds : [],
            $newOnly
        );
        $this->payroll->recalculateMonth($payrollMonth);

        return response()->json([
            'success' => true,
            'message' => "{$count} employee salary record(s) synced.",
        ]);
    }

    public function updateEmployeeSalary(Request $request, PayrollEmployeeSalary $payrollEmployeeSalary): JsonResponse
    {
        $this->authorizeManage();
        $month = $payrollEmployeeSalary->payrollMonth;
        $this->ensureUnlocked($month);

        $validated = $request->validate([
            'salary_pp' => 'nullable|numeric|min:0',
            'increment' => 'nullable|numeric|min:0',
            'other' => 'nullable|numeric|min:0',
            'adv_inc_other' => 'nullable|numeric|min:0',
            'hours_worked' => 'nullable|numeric|min:0',
            'bank_1' => 'nullable|string|max:255',
            'bank_2' => 'nullable|string|max:255',
            'upi_id' => 'nullable|string|max:255',
            'is_new_hire' => 'nullable|boolean',
        ]);

        $payrollEmployeeSalary->update($validated);
        $this->payroll->recalculateMonth($month);

        return response()->json(['success' => true, 'row' => $payrollEmployeeSalary->fresh()->load('user')]);
    }

    public function storeComponent(Request $request, PayrollMonth $payrollMonth): JsonResponse
    {
        $this->authorizeManage();
        $this->ensureUnlocked($payrollMonth);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'type' => 'required|in:earning,deduction',
            'label' => 'required|string|max:120',
            'amount' => 'required|numeric|min:0',
            'is_one_time' => 'nullable|boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        $component = PayrollSalaryComponent::create(array_merge($validated, [
            'payroll_month_id' => $payrollMonth->id,
            'is_one_time' => $request->boolean('is_one_time', true),
        ]));

        $this->payroll->recalculateMonth($payrollMonth);

        return response()->json(['success' => true, 'component' => $component->load('user')]);
    }

    public function updateComponent(Request $request, PayrollSalaryComponent $payrollSalaryComponent): JsonResponse
    {
        $this->authorizeManage();
        $this->ensureUnlocked($payrollSalaryComponent->payrollMonth);

        $validated = $request->validate([
            'label' => 'sometimes|string|max:120',
            'amount' => 'sometimes|numeric|min:0',
            'type' => 'sometimes|in:earning,deduction',
            'notes' => 'nullable|string|max:500',
        ]);

        $payrollSalaryComponent->update($validated);
        $this->payroll->recalculateMonth($payrollSalaryComponent->payrollMonth);

        return response()->json(['success' => true, 'component' => $payrollSalaryComponent->fresh()]);
    }

    public function destroyComponent(PayrollSalaryComponent $payrollSalaryComponent): JsonResponse
    {
        $this->authorizeManage();
        $month = $payrollSalaryComponent->payrollMonth;
        $this->ensureUnlocked($month);
        $payrollSalaryComponent->delete();
        $this->payroll->recalculateMonth($month);

        return response()->json(['success' => true]);
    }

    public function storePaymentDeduction(Request $request, PayrollMonth $payrollMonth): JsonResponse
    {
        $this->authorizeManage();
        $this->ensureUnlocked($payrollMonth);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'entry_type' => 'required|in:payment,deduction',
            'label' => 'required|string|max:120',
            'amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $row = PayrollPaymentDeduction::create(array_merge($validated, [
            'payroll_month_id' => $payrollMonth->id,
        ]));

        $this->payroll->recalculateMonth($payrollMonth);

        return response()->json(['success' => true, 'row' => $row->load('user')]);
    }

    public function destroyPaymentDeduction(PayrollPaymentDeduction $payrollPaymentDeduction): JsonResponse
    {
        $this->authorizeManage();
        $month = $payrollPaymentDeduction->payrollMonth;
        $this->ensureUnlocked($month);
        $payrollPaymentDeduction->delete();
        $this->payroll->recalculateMonth($month);

        return response()->json(['success' => true]);
    }

    public function toggleLock(PayrollMonth $payrollMonth): JsonResponse
    {
        $this->authorizeManage();
        $payrollMonth->update(['is_locked' => ! $payrollMonth->is_locked]);

        return response()->json([
            'success' => true,
            'is_locked' => $payrollMonth->is_locked,
            'message' => $payrollMonth->is_locked ? 'Payroll inputs locked.' : 'Payroll inputs unlocked.',
        ]);
    }

    public function updateMonth(Request $request, PayrollMonth $payrollMonth): JsonResponse
    {
        $this->authorizeManage();

        $validated = $request->validate([
            'status' => 'sometimes|string|in:draft,processing,processed,released',
            'payslip_format' => 'sometimes|string|in:standard,detailed,compact',
            'notes' => 'nullable|string|max:2000',
        ]);

        if (! $payrollMonth->is_locked) {
            $payrollMonth->update($validated);
        } else {
            $payrollMonth->update(array_intersect_key($validated, array_flip(['payslip_format', 'notes'])));
        }

        return response()->json(['success' => true, 'month' => $payrollMonth->fresh()]);
    }

    public function recalculate(PayrollMonth $payrollMonth): JsonResponse
    {
        $this->authorizeManage();
        $this->payroll->recalculateMonth($payrollMonth);
        $payrollMonth->update(['status' => 'processed']);

        return response()->json(['success' => true, 'message' => 'Payroll recalculated for all employees.']);
    }

    public function generatePayslips(PayrollMonth $payrollMonth): JsonResponse
    {
        $this->authorizeManage();
        $this->payroll->recalculateMonth($payrollMonth);
        $count = $this->payroll->generatePayslips($payrollMonth);

        return response()->json([
            'success' => true,
            'message' => "{$count} payslip(s) generated.",
        ]);
    }

    public function releasePayslips(PayrollMonth $payrollMonth): JsonResponse
    {
        $this->authorizeManage();

        $this->payroll->generatePayslips($payrollMonth);
        PayrollPayslip::where('payroll_month_id', $payrollMonth->id)
            ->update(['released_at' => now()]);

        $payrollMonth->update([
            'payslips_released_at' => now(),
            'status' => 'released',
        ]);

        return response()->json(['success' => true, 'message' => 'Payslips released to employees.']);
    }

    public function releaseItStatements(PayrollMonth $payrollMonth): JsonResponse
    {
        $this->authorizeManage();
        $payrollMonth->update(['it_statements_released_at' => now()]);

        return response()->json(['success' => true, 'message' => 'IT statements marked as released.']);
    }

    /**
     * @return array{payslip: PayrollPayslip, data: array<string, mixed>, company: array<string, mixed>}
     */
    protected function payslipViewPayload(PayrollPayslip $payrollPayslip): array
    {
        $payrollPayslip->load(['user', 'payrollMonth']);

        $data = $payrollPayslip->data ?? [];
        $user = $payrollPayslip->user;
        $month = $payrollPayslip->payrollMonth;

        if ($user) {
            $data['employee'] = $data['employee'] ?? $user->name;
            $data['email'] = $data['email'] ?? $user->email;
            $data['designation'] = $data['designation'] ?? $user->designation;
            $data['employee_id'] = $data['employee_id'] ?? 'EMP-'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT);
        }
        if ($month) {
            $row = PayrollEmployeeSalary::where('payroll_month_id', $month->id)
                ->where('user_id', $payrollPayslip->user_id)
                ->first();

            if ($row) {
                $data = $this->payroll->buildPayslipData($month, $row, $payrollPayslip->format);
                $data['payslip_no'] = $data['payslip_no'] ?? ('PS-'.$payrollPayslip->id);
            } else {
                $data['month'] = $data['month'] ?? $month->month_label;
                $data['period_start'] = $data['period_start'] ?? $month->period_start?->format('d M Y');
                $data['period_end'] = $data['period_end'] ?? $month->period_end?->format('d M Y');
                $data['salary_lm'] = $data['salary_lm'] ?? ((float) ($data['salary_pp'] ?? 0) + (float) ($data['increment'] ?? 0));
            }
        }

        return [
            'payslip' => $payrollPayslip,
            'data' => $data,
            'company' => config('payroll.company', []),
        ];
    }

    public function viewPayslip(PayrollPayslip $payrollPayslip): View
    {
        return view('payroll.payslip', $this->payslipViewPayload($payrollPayslip));
    }

    public function viewPayslipPrint(Request $request, PayrollPayslip $payrollPayslip): View
    {
        return view('payroll.payslip-print', array_merge(
            $this->payslipViewPayload($payrollPayslip),
            ['autoPrint' => $request->boolean('print', true)]
        ));
    }

    public function storeArrear(Request $request): JsonResponse
    {
        $this->authorizeManage();

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'payroll_month_id' => 'nullable|exists:payroll_months,id',
            'amount' => 'required|numeric|min:0',
            'adjustment_type' => 'nullable|in:add,deduct',
            'arrear_for_month' => 'nullable|string|max:32',
            'period_from' => 'nullable|date',
            'period_to' => 'nullable|date',
            'description' => 'nullable|string|max:500',
        ]);

        if (! empty($validated['arrear_for_month']) && empty($validated['period_from'])) {
            try {
                $validated['period_from'] = Carbon::parse('first day of '.$validated['arrear_for_month'])->toDateString();
            } catch (\Throwable) {
                // ignore invalid month text
            }
        }
        unset($validated['arrear_for_month']);

        $arrear = PayrollArrear::create(array_merge($validated, [
            'status' => 'pending',
            'adjustment_type' => $validated['adjustment_type'] ?? 'add',
        ]));

        return response()->json([
            'success' => true,
            'arrear' => $arrear->load(['user', 'payrollMonth']),
        ]);
    }

    public function applyArrear(PayrollArrear $payrollArrear): JsonResponse
    {
        $this->authorizeManage();

        if ($payrollArrear->payroll_month_id) {
            $month = PayrollMonth::find($payrollArrear->payroll_month_id);
            $this->ensureUnlocked($month);
        }

        $payrollArrear->update(['status' => 'applied', 'applied_at' => now()]);

        if ($payrollArrear->payroll_month_id) {
            $this->payroll->recalculateMonth(PayrollMonth::find($payrollArrear->payroll_month_id));
        }

        return response()->json(['success' => true, 'arrear' => $payrollArrear->fresh()]);
    }

    public function destroyArrear(PayrollArrear $payrollArrear): JsonResponse
    {
        $this->authorizeManage();

        $monthId = $payrollArrear->payroll_month_id;
        if ($monthId) {
            $month = PayrollMonth::find($monthId);
            $this->ensureUnlocked($month);
        }

        $payrollArrear->delete();

        if ($monthId) {
            $this->payroll->recalculateMonth(PayrollMonth::find($monthId));
        }

        return response()->json([
            'success' => true,
            'message' => 'Arrear removed. Final salary recalculated.',
        ]);
    }

    public function arrearsList(): JsonResponse
    {
        return response()->json([
            'arrears' => PayrollArrear::with(['user', 'payrollMonth'])->orderByDesc('id')->get(),
        ]);
    }

    public function storeSettlement(Request $request): JsonResponse
    {
        $this->authorizeManage();

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'last_working_date' => 'nullable|date',
            'settlement_date' => 'nullable|date',
            'earnings' => 'nullable|array',
            'deductions' => 'nullable|array',
            'notes' => 'nullable|string|max:2000',
        ]);

        $earnings = collect($validated['earnings'] ?? [])->sum(fn ($v) => (float) ($v['amount'] ?? $v ?? 0));
        $deductions = collect($validated['deductions'] ?? [])->sum(fn ($v) => (float) ($v['amount'] ?? $v ?? 0));
        $net = $earnings - $deductions;

        $settlement = PayrollSettlement::create([
            'user_id' => $validated['user_id'],
            'last_working_date' => $validated['last_working_date'] ?? null,
            'settlement_date' => $validated['settlement_date'] ?? now()->toDateString(),
            'earnings' => $validated['earnings'] ?? [],
            'deductions' => $validated['deductions'] ?? [],
            'net_settlement' => $net,
            'notes' => $validated['notes'] ?? null,
            'status' => 'draft',
        ]);

        return response()->json(['success' => true, 'settlement' => $settlement->load('user')]);
    }

    public function processSettlement(PayrollSettlement $payrollSettlement): JsonResponse
    {
        $this->authorizeManage();
        $payrollSettlement->update([
            'status' => 'processed',
            'processed_by' => auth()->id(),
        ]);

        return response()->json(['success' => true, 'settlement' => $payrollSettlement->fresh()->load('user')]);
    }

    public function settlementsList(): JsonResponse
    {
        return response()->json([
            'settlements' => PayrollSettlement::with('user')->orderByDesc('id')->get(),
        ]);
    }

    public function previousRecords(): JsonResponse
    {
        return response()->json([
            'records' => PayrollPreviousRecord::with(['user', 'importer'])
                ->orderByDesc('month_label')
                ->get(),
        ]);
    }

    public function storePreviousRecord(Request $request): JsonResponse
    {
        $this->authorizeManage();

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'month_label' => 'required|string|max:32',
            'gross_amount' => 'nullable|numeric|min:0',
            'deductions_total' => 'nullable|numeric|min:0',
            'net_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
        ]);

        $record = PayrollPreviousRecord::updateOrCreate(
            ['user_id' => $validated['user_id'], 'month_label' => $validated['month_label']],
            array_merge($validated, [
                'imported_by' => auth()->id(),
                'imported_at' => now(),
            ])
        );

        return response()->json(['success' => true, 'record' => $record->load('user')]);
    }

    public function importPreviousCsv(Request $request): JsonResponse
    {
        $this->authorizeManage();

        $request->validate(['file' => 'required|file|mimes:csv,txt|max:5120']);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = fgetcsv($handle);
        $imported = 0;
        $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 3) {
                continue;
            }
            $nameOrEmail = trim($row[0] ?? '');
            $monthLabel = trim($row[1] ?? '');
            $net = (float) ($row[2] ?? 0);
            $gross = (float) ($row[3] ?? $net);
            $deductions = (float) ($row[4] ?? 0);

            $user = User::where('email', $nameOrEmail)
                ->orWhere('name', $nameOrEmail)
                ->first();

            if (! $user) {
                $errors[] = $nameOrEmail;

                continue;
            }

            PayrollPreviousRecord::updateOrCreate(
                ['user_id' => $user->id, 'month_label' => $monthLabel],
                [
                    'gross_amount' => $gross,
                    'deductions_total' => $deductions,
                    'net_amount' => $net,
                    'imported_by' => auth()->id(),
                    'imported_at' => now(),
                    'imported_data' => ['source' => 'csv', 'row' => $row],
                ]
            );
            $imported++;
        }
        fclose($handle);

        return response()->json([
            'success' => true,
            'message' => "Imported {$imported} previous payroll record(s).",
            'not_found' => array_slice($errors, 0, 10),
        ]);
    }

    public function exportMonth(PayrollMonth $payrollMonth)
    {
        $this->authorizeManage();

        $rows = PayrollEmployeeSalary::with('user')
            ->where('payroll_month_id', $payrollMonth->id)
            ->get();

        $csv = '"Name","Hours","Gross","Net","B1","B2","UPI"'."\n";
        foreach ($rows as $r) {
            $csv .= '"'.str_replace('"', '""', $r->user?->name ?? '').'"';
            $csv .= ',"'.($r->hours_worked).'h"';
            $csv .= ',"'.$r->gross_amount.'"';
            $csv .= ',"'.$r->net_amount.'"';
            $csv .= ',"'.($r->bank_1 ?? '').'"';
            $csv .= ',"'.($r->bank_2 ?? '').'"';
            $csv .= ',"'.($r->upi_id ?? '').'"'."\n";
        }

        $filename = 'payroll_'.$payrollMonth->month_label.'_'.now()->format('Y-m-d').'.csv';

        return response($csv, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }
}
