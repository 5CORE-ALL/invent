@extends('layouts.vertical', ['title' => 'Salary Payslip — ' . ($data['employee'] ?? 'Employee')])

@section('css')
@include('payroll.partials.payslip-styles')
@endsection

@section('content')
@php
    $format = $data['format'] ?? $payslip->format ?? 'standard';
@endphp

<div class="payslip-toolbar d-flex flex-wrap gap-2 justify-content-between align-items-center">
    <a href="{{ route('payroll.index') }}" class="btn btn-light btn-sm"><i class="ri-arrow-left-line me-1"></i> Payroll</a>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="openPayslipPrint()"><i class="ri-printer-line me-1"></i> Print</button>
        <button type="button" class="btn btn-primary btn-sm" onclick="openPayslipPrint()"><i class="ri-file-pdf-line me-1"></i> Save as PDF</button>
        <span class="badge bg-light text-dark border align-self-center">{{ ucfirst($format) }} format</span>
    </div>
</div>

@include('payroll.partials.payslip-body')

<script>
function openPayslipPrint() {
    var url = @json(route('payroll.payslip.print', $payslip));
    var w = window.open(url, 'payslipPrint', 'noopener,noreferrer');
    if (!w) {
        window.location.href = url;
    }
}
</script>
@endsection
