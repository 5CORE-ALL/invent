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
                <div class="ps-company-tag">{{ $company['tagline'] ?? 'Trusted Since 1984' }}</div>
            </div>
            <div class="ps-doc-badge">
                <p class="ps-doc-title">Salary Payslip</p>
                <div class="ps-doc-meta">
                    <div><strong>Pay period:</strong> {{ $data['month'] ?? '—' }}</div>
                    @if(!empty($data['period_start']))
                        <div>{{ $data['period_start'] }} – {{ $data['period_end'] ?? '' }}</div>
                    @endif
                    <div><strong>Payslip #:</strong> {{ $data['payslip_no'] ?? ('PS-'.$payslip->id) }}</div>
                    <div><strong>Generated:</strong> {{ $data['generated_at'] ?? now()->format('d M Y') }}</div>
                </div>
            </div>
        </header>

        <div class="ps-confidential">Confidential — For employee use only. Unauthorized distribution prohibited.</div>

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
                <label>Basic pay (Salary PP)</label>
                <span>₹{{ number_format($salaryPp, 0) }}</span>
            </div>
            <div class="ps-field">
                <label>Increment</label>
                <span>₹{{ number_format($increment, 0) }}</span>
            </div>
            <div class="ps-field">
                <label>Salary LM</label>
                <span>₹{{ number_format($salaryLm, 0) }}</span>
            </div>
            <div class="ps-field">
                <label>Hours worked (LM)</label>
                <span>{{ number_format($hours, 0) }} hours</span>
            </div>
            <div class="ps-field">
                <label>Amt LM</label>
                <span>₹{{ number_format($data['amount_lm_display'] ?? $data['gross'] ?? 0, 0) }}</span>
            </div>
            @endif
        </section>

        <section class="ps-tables">
            <div class="ps-table-wrap">
                <h6>Earnings</h6>
                <table class="ps-table">
                    <tbody>
                        @foreach($earnings as [$label, $amt])
                        @php $amt = (float) $amt; @endphp
                        <tr>
                            <td>{{ $label }}</td>
                            <td class="{{ $amt < 0 ? 'text-deduct' : '' }}">
                                @if($amt < 0)&minus;@endif₹{{ number_format(abs($amt), 2) }}
                            </td>
                        </tr>
                        @endforeach
                        <tr class="ps-total">
                            <td>Total Earnings</td>
                            <td>₹{{ number_format($totalEarnings, 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="ps-table-wrap">
                <h6>Deductions</h6>
                <table class="ps-table">
                    <tbody>
                        @if($hasDeductions)
                            @foreach($deductions as [$label, $amt])
                            <tr>
                                <td>{{ $label }}</td>
                                <td class="text-deduct">&minus;₹{{ number_format((float) $amt, 2) }}</td>
                            </tr>
                            @endforeach
                        @else
                            <tr>
                                <td>No deductions this month</td>
                                <td>₹0.00</td>
                            </tr>
                        @endif
                        <tr class="ps-total">
                            <td>Total Deductions</td>
                            <td class="{{ $hasDeductions ? 'text-deduct' : '' }}">
                                @if($hasDeductions)&minus;@endif₹{{ number_format($totalDeductions, 2) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
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
                    <div>No signature required unless stamped by HR.</div>
                </div>
            </div>
            <div class="ps-sign">
                <div class="ps-sign-line">Authorized Signatory — Human Resources</div>
            </div>
        </footer>
    </div>
</article>
