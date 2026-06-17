@php
    $logo = $company['logo'] ?? '/images/payroll/5core-logo.png';
    $watermark = $company['watermark'] ?? '/images/payroll/5core-stamp-red.png';
    if (! file_exists(public_path(ltrim($logo, '/')))) {
        $logo = '/images/5core_logo.png';
    }
    $format = $data['format'] ?? $payslip->format ?? 'standard';
    $isCompact = $format === 'compact';

    $payrollService = app(\App\Services\PayrollService::class);
    $hours = (float) ($data['hours_worked'] ?? 0);
    $salaryPp = (float) ($data['salary_pp'] ?? 0);
    $increment = (float) ($data['increment'] ?? 0);
    $salaryLm = (float) ($data['salary_lm'] ?? ($salaryPp + $increment));

    $earnings = $data['earning_lines'] ?? $payrollService->buildPayslipEarnings($data);
    $totalEarnings = (float) array_sum(array_column($earnings, 1));

    $deductions = [];
    if (($data['adv_inc_other'] ?? 0) > 0) {
        $deductions[] = ['Advance / Other Recovery', (float) $data['adv_inc_other']];
    }
    $baseDed = (float) ($data['deductions'] ?? 0);
    $advInDed = (float) ($data['adv_inc_other'] ?? 0);
    $otherDed = max(0, $baseDed - $advInDed);
    if ($otherDed > 0) {
        $deductions[] = ['Other Deductions', $otherDed];
    }
    if (! $isCompact && ! empty($data['components'])) {
        foreach ($data['components'] as $c) {
            if (($c['type'] ?? '') === 'deduction' && ($c['amount'] ?? 0) > 0) {
                $deductions[] = [(string) $c['label'], (float) $c['amount']];
            }
        }
    }
    $totalDeductions = array_sum(array_column($deductions, 1));
    $hasDeductions = $totalDeductions > 0.001;

    $net = (float) ($data['net'] ?? $data['amount_p_display'] ?? $data['amount_p_rounded'] ?? 0);
    try {
        $netWords = 'Rupees '.ucfirst(\Illuminate\Support\Number::spell((int) round($net))).' only';
    } catch (\Throwable $e) {
        $netWords = 'Rupees '.number_format($net, 2).' only';
    }
@endphp

<article class="payslip-page" id="payslipPrint">
    <div class="payslip-watermark" aria-hidden="true">
        <img src="{{ asset($watermark) }}" alt="">
    </div>

    <div class="payslip-inner">
        <header class="ps-header">
            <div class="ps-logo">
                <img src="{{ asset($logo) }}" alt="{{ $company['name'] ?? '5 CORE INC.' }}">
            </div>
            <div>
                <h1 class="ps-company-name">{{ $company['name'] ?? '5 CORE INC.' }}</h1>
                <div class="ps-company-tag">{{ $company['tagline'] ?? 'OHIO, USA.' }}</div>
            </div>
            <div class="ps-doc-badge">
                <p class="ps-doc-title">Salary Payslip</p>
                <div class="ps-doc-meta">
                    <div><strong>Pay period:</strong> {{ $data['month'] ?? '—' }}</div>
                    <div><strong>Payslip #:</strong> {{ $data['payslip_no'] ?? ('PS-'.$payslip->id) }}</div>
                </div>
            </div>
        </header>

        <section class="ps-employee-grid">
            <div class="ps-field">
                <label>Employee name</label>
                <span>{{ $data['employee'] ?? $payslip->user?->name }}</span>
            </div>
            <div class="ps-field">
                <label>Employee ID</label>
                <span>{{ $data['employee_id'] ?? '—' }}</span>
            </div>
            <div class="ps-field">
                <label>Designation</label>
                <span>{{ $data['designation'] ?? '—' }}</span>
            </div>
            <div class="ps-field">
                <label>Email</label>
                <span>{{ $data['email'] ?? $payslip->user?->email }}</span>
            </div>
            @if(!$isCompact)
            <div class="ps-field">
                <label>Salary Previous</label>
                <span>₹{{ number_format($salaryPp, 0) }}</span>
            </div>
            <div class="ps-field">
                <label>Increment</label>
                <span>₹{{ number_format($increment, 0) }}</span>
            </div>
            <div class="ps-field">
                <label>Salary</label>
                <span>₹{{ number_format($salaryLm, 0) }}</span>
            </div>
            <div class="ps-field">
                <label>Hours worked</label>
                <span>{{ number_format($hours, 0) }} hours</span>
            </div>
            <div class="ps-field">
                <label>Add Other</label>
                <span style="color: #1b5e20;">+₹{{ number_format($data['other'] ?? 0, 0) }}</span>
            </div>
            <div class="ps-field">
                <label>Add Incentive</label>
                <span style="color: #1b5e20;">+₹{{ number_format($data['incentive'] ?? 0, 0) }}</span>
            </div>
            <div class="ps-field">
                <label>Less Advance / Deductions</label>
                <span style="color: #c41e24;">&minus;₹{{ number_format($data['adv_inc_other'] ?? 0, 0) }}</span>
            </div>
            <div class="ps-field">
                <label>Amount</label>
                <span>₹{{ number_format($data['amount_p'] ?? $net, 0) }}</span>
            </div>
            <div class="ps-field">
                <label>Round Off</label>
                <span>₹{{ number_format($net, 0) }}</span>
            </div>
            @endif
        </section>

        <section class="ps-net-box">
            <div>
                <div class="ps-net-label">Net salary payable</div>
                <div class="ps-net-title">Amount credited to account</div>
                <div class="ps-net-words">{{ $netWords }}</div>
            </div>
            <div class="text-end">
                <div class="ps-net-amount">₹{{ number_format($net, 2) }}</div>
            </div>
        </section>

        <section class="ps-payment">
            <div class="ps-pay-card">
                <strong>Bank account 1</strong>
                <span class="{{ !empty($data['bank_1']) ? 'filled' : 'empty' }}">{{ $data['bank_1'] ?? 'Not provided' }}</span>
            </div>
            <div class="ps-pay-card">
                <strong>Bank account 2</strong>
                <span class="{{ !empty($data['bank_2']) ? 'filled' : 'empty' }}">{{ $data['bank_2'] ?? 'Not provided' }}</span>
            </div>
            <div class="ps-pay-card">
                <strong>UPI ID</strong>
                <span class="{{ !empty($data['upi_id']) ? 'filled' : 'empty' }}">{{ $data['upi_id'] ?? 'Not provided' }}</span>
            </div>
        </section>

        <footer class="ps-footer">
            <div class="ps-footer-line"></div>
            <div class="ps-footer-grid">
                <div>
                    <div class="ps-footer-contact"><i class="ri-map-pin-line"></i> {{ $company['address'] ?? '' }}</div>
                    <div class="ps-footer-contact mt-1"><i class="ri-mail-line"></i> {{ $company['email'] ?? '' }}</div>
                    <div class="ps-footer-contact mt-1"><i class="ri-global-line"></i> {{ $company['website'] ?? '' }}</div>
                </div>
                <div class="text-end">
                    <div>This is a computer-generated payslip.</div>
                    <div>No signature required.</div>
                </div>
            </div>
        </footer>
    </div>
</article>
