<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Salary Payslip — {{ $data['employee'] ?? 'Employee' }}</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    @include('payroll.partials.payslip-styles')
</head>
<body class="payslip-print-body">
    @include('payroll.partials.payslip-body')

    @if($autoPrint ?? true)
    <script>
        window.addEventListener('load', function () {
            setTimeout(function () { window.print(); }, 400);
        });
    </script>
    @endif
</body>
</html>
