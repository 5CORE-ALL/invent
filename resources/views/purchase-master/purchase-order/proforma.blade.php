<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Proforma Invoice</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-size: 15px;
            color: #222;
            background: #f6f8fa;
            padding: 30px 0;
        }

        .invoice-box {
            background: #fff;
            border: 1px solid #e3e6ea;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
            padding: 25px 25px;
            border-radius: 14px;
            max-width: 100%;
            width: 100%;
            margin: 0 auto;
        }

        .invoice-box .table {
            width: 100%;
            table-layout: auto;
        }

        .heading {
            text-align: center;
            margin-bottom: 28px;
            font-weight: 700;
            font-size: 28px;
            letter-spacing: 2px;
            color: #1a237e;
        }

        .invoice-header {
            border-bottom: 1.5px solid #e3e6ea;
            padding-bottom: 18px;
            margin-bottom: 24px;
        }

        .invoice-header h6 {
            font-weight: 600;
            color: #3949ab;
        }

        .invoice-header p {
            margin-bottom: 2px;
        }

        .table {
            margin-bottom: 0;
        }

        .table th,
        .table td {
            vertical-align: middle;
            text-align: center;
        }

        .table thead th {
            background: #e8eaf6;
            color: #1a237e;
            font-weight: 700;
            border-bottom: 2px solid #c5cae9;
        }

        .table tfoot td {
            background: #f5f5f5;
            font-weight: 600;
        }

        .note-section {
            background: #f1f8e9;
            padding: 15px 15px;
            border-radius: 8px;
            margin-top: 18px;
        }

        .note-section h6 {
            color: #388e3c;
            font-weight: 700;
        }

        .terms-section {
            font-size: 14px;
            line-height: 1.7;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 16px 20px;
            margin-top: 28px;
        }

        .totals-box {
            background: #f3e5f5;
            border-radius: 8px;
            padding: 18px 22px;
            margin-top: 18px;
            color: #6a1b9a;
            font-weight: 600;
        }

        .footer {
            margin-top: 40px;
            text-align: center;
            color: #888;
            font-size: 13px;
        }

        @media print {
            body {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                margin: 0;
                padding: 0;
                zoom: 70%;
            }
            button,
            .btn,
            [onclick*="add"],
            [onclick*="edit"],
            svg {
                display: none !important;
            }

            [type="button"],
            .no-print,
            .col-edit,
            .po-edit-btn,
            .po-line-actions,
            .po-supplier-sku-edit,
            .po-copy-col-btn,
            .po-copy-toolbar {
                display: none !important;
            }

            @page {
                size: A4;
                margin: 0;
            }

            body {
                background: #fff !important;
                padding: 0;
            }

            .invoice-box {
                box-shadow: none;
                border: none;
                max-width: 100%;
                width: 100%;
            }
        }
        .wrap-text {
            word-wrap: break-word;
            white-space: normal;
            font-size: 12px;
        }

        .col-product {
            width: 160px;
            min-width: 150px;
            max-width: 180px;
            font-size: 11px;
            vertical-align: middle !important;
            text-align: left;
        }

        .po-product-cell {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
        }

        .po-product-photo {
            width: 130px;
            height: 130px;
            object-fit: contain;
            display: block;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #fff;
            box-sizing: border-box;
            padding: 4px;
        }

        .po-product-meta {
            width: 100%;
            text-align: left;
            line-height: 1.25;
        }

        .po-product-label {
            display: block;
            font-size: 9px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            margin-top: 4px;
        }

        .po-product-label:first-child {
            margin-top: 0;
        }

        .po-product-sku {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: #1a3d7c;
            word-break: break-word;
            white-space: normal;
        }

        .po-product-supplier {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: #374151;
            word-break: break-word;
            white-space: normal;
            min-height: 1.2em;
        }

        .col-short-name {
            min-width: 100px;
            max-width: 140px;
            white-space: normal;
            text-align: left;
            font-size: 12px;
        }

        /* Same size as product photo */
        .po-barcode-wrap {
            width: 130px;
            height: 130px;
            margin: 0 auto;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 6px 4px;
            box-sizing: border-box;
            gap: 3px;
        }

        .po-barcode-sku {
            font-size: 10px;
            font-weight: 700;
            color: #1a3d7c;
            line-height: 1.2;
            text-align: center;
            width: 100%;
            word-break: break-word;
            max-height: 2.4em;
            overflow: hidden;
            flex-shrink: 0;
        }

        .po-barcode-img {
            max-width: 110px;
            max-height: 48px;
            width: auto;
            height: auto;
            object-fit: contain;
            display: block;
            margin: 0 auto;
            background: #fff;
            flex-shrink: 0;
        }

        .po-barcode-code {
            font-size: 9px;
            font-weight: 600;
            color: #374151;
            line-height: 1.2;
            word-break: break-all;
            text-align: center;
            width: 100%;
            flex-shrink: 0;
        }

        .col-edit {
            width: 70px;
            min-width: 70px;
        }

        .po-edit-btn {
            border: 1px solid #3949ab;
            color: #3949ab;
            background: #fff;
            border-radius: 6px;
            padding: 4px 10px;
            font-size: 12px;
            cursor: pointer;
        }

        .po-edit-btn:hover {
            background: #3949ab;
            color: #fff;
        }

        .po-copy-toolbar {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }

        .po-copy-all-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid #3949ab;
            color: #3949ab;
            background: #fff;
            border-radius: 6px;
            padding: 4px 10px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            line-height: 1.2;
        }

        .po-copy-all-btn:hover,
        .po-copy-all-btn.is-copied {
            background: #3949ab;
            color: #fff;
        }

        .table thead th .po-th-wrap {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            flex-wrap: wrap;
        }

        .po-copy-col-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            padding: 0;
            border: 1px solid #9fa8da;
            border-radius: 4px;
            background: #fff;
            color: #3949ab;
            cursor: pointer;
            flex-shrink: 0;
            vertical-align: middle;
        }

        .po-copy-col-btn:hover,
        .po-copy-col-btn.is-copied {
            background: #3949ab;
            color: #fff;
            border-color: #3949ab;
        }

        .po-copy-col-btn svg {
            width: 12px;
            height: 12px;
            display: block;
        }

        .po-line-input,
        .po-supplier-sku-input {
            width: 100%;
            min-width: 70px;
            font-size: 12px;
            padding: 4px 6px;
            border: 1px solid #3949ab;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .po-line-input.po-line-tech {
            min-width: 140px;
            min-height: 64px;
            resize: vertical;
        }
        .po-line-input.po-line-pkg {
            min-width: 110px;
            min-height: 72px;
            font-size: 11px;
        }
        .po-line-actions {
            display: flex;
            flex-direction: column;
            gap: 4px;
            align-items: stretch;
        }
        .po-line-actions .btn {
            font-size: 11px;
            padding: 3px 8px;
        }
        .po-supplier-sku-input-legacy {
            width: 100%;
            min-width: 110px;
            font-size: 12px;
            padding: 4px 6px;
            border: 1px solid #3949ab;
            border-radius: 4px;
        }

        .po-supplier-sku-actions {
            display: flex;
            gap: 4px;
            justify-content: center;
            margin-top: 4px;
        }

        .po-supplier-sku-actions .btn {
            font-size: 11px;
            padding: 2px 8px;
        }

        .col-tech {
            min-width: 280px;
            width: 32%;
            word-wrap: break-word;
            white-space: normal;
            font-size: 12px;
            text-align: left;
        }

        .col-pkg {
            min-width: 140px;
            max-width: 200px;
            font-size: 10px;
            vertical-align: top !important;
            text-align: left;
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: anywhere;
        }

        .po-pkg-combined {
            cursor: pointer;
            padding: 2px 0;
            border-radius: 3px;
            transition: background 0.15s ease;
        }

        .po-pkg-combined:hover {
            background: #eef4ff;
        }

        .po-pkg-combined-row {
            margin: 0 0 2px;
            line-height: 1.2;
        }

        .po-pkg-combined-row:last-child {
            margin-bottom: 0;
        }

        .po-pkg-combined-label {
            font-size: 10px;
            font-weight: 700;
            color: #000;
            margin-right: 4px;
        }

        .po-pkg-combined-label::after {
            content: ':';
        }

        .po-pkg-combined-value {
            font-size: 10px;
            font-weight: 400;
            color: #000;
            word-break: break-word;
            white-space: pre-wrap;
        }

        .po-pkg-combined-thumb {
            width: 28px;
            height: 28px;
            object-fit: contain;
            border: 1px solid #d1d5db;
            border-radius: 3px;
            background: #fff;
            display: inline-block;
            vertical-align: middle;
        }

        .po-pkg-combined-link,
        .po-pkg-combined a,
        .po-pkg-combined a:link,
        .po-pkg-combined a:visited,
        .po-pkg-combined a:hover,
        .po-pkg-combined a:active {
            font-size: 10px;
            font-weight: 400;
            color: #000 !important;
            text-decoration: none;
            word-break: break-all;
        }

        .po-pkg-combined .text-muted,
        .po-pkg-combined .text-primary {
            color: #000 !important;
        }

        @media print {
            .po-pkg-combined {
                cursor: default;
            }
            .po-pkg-combined:hover {
                background: transparent;
            }
        }

        .col-special-qc {
            min-width: 160px;
            max-width: 240px;
            font-size: 11px;
            vertical-align: top !important;
            text-align: left;
        }

        .po-special-qc-cell {
            cursor: pointer;
            min-height: 48px;
            padding: 4px 2px;
            border-radius: 4px;
            color: #000;
            transition: background 0.15s ease;
        }

        .po-special-qc-cell:hover {
            background: #eef4ff;
        }

        .po-special-qc-list {
            margin: 0;
            padding-left: 1.25rem;
            font-size: 10px;
            line-height: 1.3;
            color: #000;
        }

        .po-special-qc-list li {
            margin-bottom: 2px;
        }

        .po-special-qc-empty {
            color: #000;
            font-size: 10px;
        }

        .po-special-qc-point-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }

        .po-special-qc-point-num {
            flex: 0 0 24px;
            font-weight: 700;
            color: #000;
        }

        .po-special-qc-point-row .form-control {
            flex: 1 1 auto;
        }

        @media print {
            .po-special-qc-cell {
                cursor: default;
            }
            .po-special-qc-cell:hover {
                background: transparent;
            }
        }

        .col-dims {
            width: 130px;
            min-width: 120px;
            font-size: 11px;
            vertical-align: middle !important;
            text-align: left;
            white-space: nowrap;
        }

        .po-dims-cell {
            display: flex;
            flex-direction: column;
            gap: 4px;
            line-height: 1.25;
        }

        .po-dims-row {
            display: flex;
            align-items: baseline;
            gap: 4px;
        }

        .po-dims-label {
            font-size: 9px;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            min-width: 28px;
        }

        .po-dims-value {
            font-size: 12px;
            font-weight: 600;
            color: #1a237e;
            min-width: 40px;
        }
    </style>
</head>

<body>
    <div class="invoice-box" id="invoice-box">
        <div class="d-flex justify-content-end">
            <button type="button" class="btn btn-success" onclick="printAsPdfStyle()">Download PDF</button>
        </div>
        <div class="row mb-4 align-items-center">
            <div class="col-md-6">
                <div class="heading mb-0 text-start" style="font-size: 1.5rem;">
                    Proforma Invoice / Contract
                </div>
                <div class="mt-2">
                    <img src="{{ asset('assets/5core.png') }}" alt="Company Logo" style="height: 60px;">
                </div>
            </div>
            <div class="col-md-6 text-end">
                <div>
                    <span class="fw-bold text-secondary">PO Number:</span>
                    <span class="ms-1">{{ $order->po_number }}</span>
                </div>
                <div>
                    <span class="fw-bold text-secondary">PO Date:</span>
                    <span class="ms-1">{{ $order->po_date ?? \Carbon\Carbon::now()->format('d-m-Y') }}</span>
                </div>
            </div>
        </div>

        {{-- Invoice Header --}}
        <div class="row invoice-header">
            <div class="col-md-6">
                <h6>From:</h6>
                <p>
                    {{ $from['name'] ?? '5 CORE INC' }}<br>
                    {!! $from['address'] ?? '1221 W.SANDUSKY AVE,<br>BELLEFONTAINE OH43311, USA' !!}<br>
                    {{-- Email: {{ $from['email'] ?? 'president@5core.com' }}<br>
                    Phone: {{ $from['phone'] ?? '+1(714)249-0848' }} --}}
                </p>
            </div>
            <div class="col-md-6 text-end">
                <h6>To:</h6>
                <p>
                    {{ $supplier->name ?? 'John Doe' }}<br>
                    {{ $supplier->company ?? 'ABC Imports Ltd.' }}<br>
                    {{ $supplier->country ?? 'China' }}<br>
                    Email: {{ $supplier->email ?? 'john@abcimports.com' }}
                </p>
            </div>
        </div>
        @php
            $grandTotals = [];
            // Show ¥ columns only when at least one line was entered in RMB.
            // USD-only POs show US$ columns only.
            $showRmbColumns = false;
            foreach ($items as $__item) {
                if (strtoupper((string) ($__item->currency ?? 'USD')) === 'RMB') {
                    $showRmbColumns = true;
                    break;
                }
            }
        @endphp
        @php
            $poCopyIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M4 1.5H3a2 2 0 0 0-2 2V14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3.5a2 2 0 0 0-2-2h-1v1h1a1 1 0 0 1 1 1V14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V3.5a1 1 0 0 1 1-1h1v-1z"/><path d="M9.5 1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5h3zm-3-1A1.5 1.5 0 0 0 5 1.5v1A1.5 1.5 0 0 0 6.5 4h3A1.5 1.5 0 0 0 11 2.5v-1A1.5 1.5 0 0 0 9.5 0h-3z"/></svg>';
        @endphp
        <div class="po-copy-toolbar no-print">
            <button type="button" class="po-copy-all-btn" id="poCopyAllBtn" title="Copy all columns">
                {!! $poCopyIcon !!}
                <span>For all</span>
            </button>
        </div>
        {{-- SKU Table --}}
        <table class="table table-bordered table-responsive" id="poItemsTable" style="padding:0%;">
            <thead>
                <tr>
                    <th class="col-product" data-copy-key="product">
                        <span class="po-th-wrap">
                            <span>Product</span>
                            <button type="button" class="po-copy-col-btn no-print" data-copy-col="product" title="Copy Product column">{!! $poCopyIcon !!}</button>
                        </span>
                    </th>
                    <th class="col-short-name" data-copy-key="short_name">
                        <span class="po-th-wrap">
                            <span>Short Name</span>
                            <button type="button" class="po-copy-col-btn no-print" data-copy-col="short_name" title="Copy Short Name column">{!! $poCopyIcon !!}</button>
                        </span>
                    </th>
                    <th class="col-tech" data-copy-key="tech">
                        <span class="po-th-wrap">
                            <span>Tech</span>
                            <button type="button" class="po-copy-col-btn no-print" data-copy-col="tech" title="Copy Tech column">{!! $poCopyIcon !!}</button>
                        </span>
                    </th>
                    <th class="col-pkg" data-copy-key="packaging">
                        <span class="po-th-wrap">
                            <span>Packaging</span>
                            <button type="button" class="po-copy-col-btn no-print" data-copy-col="packaging" title="Copy Packaging column">{!! $poCopyIcon !!}</button>
                        </span>
                    </th>
                    <th class="col-dims" data-copy-key="dims">
                        <span class="po-th-wrap">
                            <span>NW (kg) / GW (kg) / CBM</span>
                            <button type="button" class="po-copy-col-btn no-print" data-copy-col="dims" title="Copy NW/GW/CBM column">{!! $poCopyIcon !!}</button>
                        </span>
                    </th>
                    <th class="col-special-qc" data-copy-key="special_qc">
                        <span class="po-th-wrap">
                            <span>Special Instruction QC</span>
                            <button type="button" class="po-copy-col-btn no-print" data-copy-col="special_qc" title="Copy Special Instruction QC column">{!! $poCopyIcon !!}</button>
                        </span>
                    </th>
                    <th data-copy-key="qty">
                        <span class="po-th-wrap">
                            <span>QTY</span>
                            <button type="button" class="po-copy-col-btn no-print" data-copy-col="qty" title="Copy QTY column">{!! $poCopyIcon !!}</button>
                        </span>
                    </th>
                    <th data-copy-key="price_usd">
                        <span class="po-th-wrap">
                            <span>Rate $</span>
                            <button type="button" class="po-copy-col-btn no-print" data-copy-col="price_usd" title="Copy Rate $ column">{!! $poCopyIcon !!}</button>
                        </span>
                    </th>
                    @if($showRmbColumns)
                        <th data-copy-key="price_rmb">
                            <span class="po-th-wrap">
                                <span>Rate ¥</span>
                                <button type="button" class="po-copy-col-btn no-print" data-copy-col="price_rmb" title="Copy Rate ¥ column">{!! $poCopyIcon !!}</button>
                            </span>
                        </th>
                    @endif
                    <th data-copy-key="total_usd">
                        <span class="po-th-wrap">
                            <span>Total ($)</span>
                            <button type="button" class="po-copy-col-btn no-print" data-copy-col="total_usd" title="Copy Total ($) column">{!! $poCopyIcon !!}</button>
                        </span>
                    </th>
                    @if($showRmbColumns)
                        <th data-copy-key="total_rmb">
                            <span class="po-th-wrap">
                                <span>Total (¥)</span>
                                <button type="button" class="po-copy-col-btn no-print" data-copy-col="total_rmb" title="Copy Total (¥) column">{!! $poCopyIcon !!}</button>
                            </span>
                        </th>
                    @endif
                    <th class="col-edit no-print">Edit</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $subtotal = 0;
                    $cbmTotal = 0;
                    $usdToCny = $usdToCny ?? null;
                    $subtotalUsd = 0.0;
                    $subtotalRmb = 0.0;
                    $hasUsdTotal = false;
                    $hasRmbTotal = false;
                @endphp
                @foreach ($items as $i => $item)
                    @php
                        $lineTotal = $item->qty * $item->price;
                        $subtotal += $lineTotal;
                        $cbmTotal += ($item->qty ?? 0) * (float)($item->cbm ?? 0);

                        $curr = strtoupper($item->currency ?? 'USD');
                        $currencySymbol = ($curr === 'RMB') ? '¥' : '$';
                        $price = (float) ($item->price ?? 0);
                        $qty = (float) ($item->qty ?? 0);

                        // Stored currency is the source of truth:
                        // - USD entry → show $ only (no ¥ conversion)
                        // - RMB entry → show ¥ and converted $
                        if ($curr === 'RMB') {
                            $priceRmb = $price;
                            $priceUsd = ($usdToCny && $usdToCny > 0) ? round($price / $usdToCny, 2) : null;
                        } else {
                            $priceUsd = $price;
                            $priceRmb = null;
                        }

                        $totalUsd = $priceUsd !== null ? round($priceUsd * $qty, 2) : null;
                        $totalRmb = $priceRmb !== null ? round($priceRmb * $qty, 2) : null;
                        if ($totalUsd !== null) {
                            $subtotalUsd += $totalUsd;
                            $hasUsdTotal = true;
                        }
                        if ($totalRmb !== null) {
                            $subtotalRmb += $totalRmb;
                            $hasRmbTotal = true;
                        }

                        // Grand total per currency
                        if (!isset($grandTotals[$curr])) $grandTotals[$curr] = 0;
                        $grandTotals[$curr] += $lineTotal;

                    @endphp
                    <tr>
                        <td class="col-product">
                            <div class="po-product-cell">
                                @if(!empty($item->photo_url))
                                    <img src="{{ $item->photo_url }}"
                                         alt="{{ $item->sku ?? '' }}"
                                         class="po-product-photo" />
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                                @if(!empty($item->barcode_url) || !empty($item->barcode_code))
                                    <div class="po-barcode-wrap">
                                        <div class="po-barcode-sku">{{ $item->sku ?? '—' }}</div>
                                        @if(!empty($item->barcode_url))
                                            <img src="{{ $item->barcode_url }}"
                                                 alt="Barcode {{ $item->barcode_code ?? $item->sku ?? '' }}"
                                                 class="po-barcode-img" />
                                        @endif
                                        <div class="po-barcode-code">{{ $item->barcode_code ?? '—' }}</div>
                                    </div>
                                @endif
                                <div class="po-product-meta">
                                    <span class="po-product-label">Supplier SKU</span>
                                    <span class="po-product-supplier po-editable"
                                          data-field="supplier_sku"
                                          data-item-index="{{ $i }}">
                                        <span class="po-field-text">{{ $item->supplier_sku ?? '' }}</span>
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td class="col-short-name po-editable" data-field="short_name">
                            <span class="po-field-text">{{ $item->short_name ?? '' }}</span>
                        </td>
                        <td class="wrap-text col-tech po-editable" data-field="tech" data-raw="{{ base64_encode((string) ($item->tech ?? '')) }}">
                            <span class="po-field-text">{!! nl2br(e($item->tech ?? '')) !!}</span>
                        </td>
                        @php
                            $itemPkg = trim((string) ($item->item_pkg ?? ''));
                            $ctnPkg = trim((string) ($item->ctn_pkg ?? ''));
                            $itemPkgCover = trim((string) ($item->item_pkg_cover ?? ''));
                            $designFile = trim((string) ($item->design_file ?? ''));
                            $ctnQty = $item->ctn_qty ?? '';
                            $ctnPrintFile = trim((string) ($item->ctn_print_file ?? ''));
                            $designFileName = $designFile !== '' ? basename(parse_url($designFile, PHP_URL_PATH) ?: $designFile) : '';
                            $designIsImage = $designFile !== '' && preg_match('/\.(jpe?g|png|gif|webp|bmp|svg)(\?|$)/i', $designFile);
                            $coverIsImage = $itemPkgCover !== '' && preg_match('/\.(jpe?g|png|gif|webp|bmp|svg)(\?|$)/i', $itemPkgCover);
                            $pkgProductId = $item->product_master_id ?? null;
                            $pkgSku = $item->product_master_sku ?? ($item->sku ?? '');
                        @endphp
                        <td class="wrap-text col-pkg">
                            <div class="po-pkg-combined"
                                 role="button"
                                 tabindex="0"
                                 title="Edit Packaging"
                                 data-product-id="{{ $pkgProductId }}"
                                 data-sku="{{ $pkgSku }}"
                                 data-item-pkg="{{ $itemPkg }}"
                                 data-ctn-pkg="{{ $ctnPkg }}"
                                 data-cover-url="{{ $itemPkgCover }}"
                                 data-design-file="{{ $designFile }}"
                                 data-ctn-qty="{{ $ctnQty }}"
                                 data-ctn-print-file="{{ $ctnPrintFile }}">
                                <div class="po-pkg-combined-row">
                                    <span class="po-pkg-combined-label">Item Pkg</span>
                                    <span class="po-pkg-combined-value po-item-pkg-text">
                                        @if($itemPkg !== '')
                                            {!! nl2br(e($itemPkg)) !!}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </span>
                                </div>
                                <div class="po-pkg-combined-row">
                                    <span class="po-pkg-combined-label">Itm pkg Cover</span>
                                    <span class="po-pkg-combined-value po-cover-text">
                                        @if($itemPkgCover !== '')
                                            @if($coverIsImage)
                                                <img src="{{ $itemPkgCover }}" alt="Itm pkg Cover" class="po-pkg-combined-thumb">
                                            @else
                                                <span class="po-pkg-combined-link">{{ basename(parse_url($itemPkgCover, PHP_URL_PATH) ?: $itemPkgCover) }}</span>
                                            @endif
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </span>
                                </div>
                                <div class="po-pkg-combined-row">
                                    <span class="po-pkg-combined-label">Design File</span>
                                    <span class="po-pkg-combined-value po-design-text">
                                        @if($designFile !== '')
                                            @if($designIsImage)
                                                <img src="{{ $designFile }}" alt="Design File" class="po-pkg-combined-thumb">
                                            @else
                                                <span class="po-pkg-combined-link">{{ $designFileName !== '' ? $designFileName : 'File' }}</span>
                                            @endif
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </span>
                                </div>
                                <div class="po-pkg-combined-row">
                                    <span class="po-pkg-combined-label">Ctn Pkg</span>
                                    <span class="po-pkg-combined-value po-ctn-pkg-text">
                                        @if($ctnPkg !== '')
                                            {!! nl2br(e($ctnPkg)) !!}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </span>
                                </div>
                                <div class="po-pkg-combined-row">
                                    <span class="po-pkg-combined-label">Ctn Qty</span>
                                    <span class="po-pkg-combined-value po-ctn-qty-text">
                                        @if($ctnQty !== '' && $ctnQty !== null)
                                            {{ $ctnQty }}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </span>
                                </div>
                                <div class="po-pkg-combined-row">
                                    <span class="po-pkg-combined-label">Ctn Print File</span>
                                    <span class="po-pkg-combined-value po-ctn-print-text">
                                        @if($ctnPrintFile !== '')
                                            <span class="po-pkg-combined-link">{{ basename(parse_url($ctnPrintFile, PHP_URL_PATH) ?: $ctnPrintFile) }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td class="col-dims">
                            <div class="po-dims-cell">
                                <div class="po-dims-row">
                                    <span class="po-dims-label">NW (kg)</span>
                                    <span class="po-dims-value po-editable" data-field="nw">
                                        <span class="po-field-text">{{ $item->nw ?? '' }}</span>
                                    </span>
                                </div>
                                <div class="po-dims-row">
                                    <span class="po-dims-label">GW (kg)</span>
                                    <span class="po-dims-value po-editable" data-field="gw">
                                        <span class="po-field-text">{{ $item->gw ?? '' }}</span>
                                    </span>
                                </div>
                                <div class="po-dims-row">
                                    <span class="po-dims-label">CBM</span>
                                    <span class="po-dims-value po-editable" data-field="cbm">
                                        <span class="po-field-text">{{ $item->cbm ?? '' }}</span>
                                    </span>
                                </div>
                            </div>
                        </td>
                        @php
                            $specialQcText = trim((string) ($item->special_instruction_qc ?? ''));
                            $specialQcPoints = preg_split('/\r\n|\r|\n/', $specialQcText) ?: [];
                            $specialQcPoints = array_values(array_filter(array_map(function ($line) {
                                $line = trim((string) $line);
                                $line = preg_replace('/^\s*(?:\d+[\.\)]\s*|[-•]\s+)/u', '', $line) ?? $line;
                                return trim($line);
                            }, $specialQcPoints), fn ($line) => $line !== ''));
                        @endphp
                        <td class="col-special-qc">
                            <div class="po-special-qc-cell"
                                 role="button"
                                 tabindex="0"
                                 title="Edit Special Instruction QC"
                                 data-product-id="{{ $item->product_master_id ?? '' }}"
                                 data-sku="{{ $item->product_master_sku ?? ($item->sku ?? '') }}"
                                 data-special-qc="{{ $specialQcText }}">
                                @if(count($specialQcPoints) > 0)
                                    <ol class="po-special-qc-list">
                                        @foreach($specialQcPoints as $point)
                                            <li>{{ $point }}</li>
                                        @endforeach
                                    </ol>
                                @else
                                    <span class="po-special-qc-empty">—</span>
                                @endif
                            </div>
                        </td>
                        <td class="col-qty po-editable" data-field="qty">
                            <span class="po-field-text">{{ $item->qty }}</span>
                        </td>
                        <td class="col-price-usd po-editable"
                            data-field="price_usd"
                            data-currency-source="{{ $curr }}"
                            data-raw="{{ $curr === 'USD' ? $price : ($priceUsd !== null ? $priceUsd : '') }}">
                            <span class="po-field-text">{{ $priceUsd !== null ? rtrim(rtrim(number_format($priceUsd, 2, '.', ''), '0'), '.') . '$' : '—' }}</span>
                        </td>
                        @if($showRmbColumns)
                            <td class="col-price-rmb po-editable"
                                data-field="price_rmb"
                                data-currency-source="{{ $curr }}"
                                data-raw="{{ $curr === 'RMB' ? $price : '' }}">
                                <span class="po-field-text">{{ $priceRmb !== null ? rtrim(rtrim(number_format($priceRmb, 2, '.', ''), '0'), '.') . '¥' : '—' }}</span>
                            </td>
                        @endif
                        <td class="col-total-usd">{{ $totalUsd !== null ? number_format($totalUsd, 2) . '$' : '—' }}</td>
                        @if($showRmbColumns)
                            <td class="col-total-rmb">{{ $totalRmb !== null ? number_format($totalRmb, 2) . '¥' : '—' }}</td>
                        @endif
                        <td class="col-edit no-print">
                            <button type="button"
                                    class="po-edit-btn"
                                    data-item-index="{{ $i }}"
                                    data-currency="{{ strtoupper($item->currency ?? 'USD') }}"
                                    title="Edit line item">
                                Edit
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="{{ $showRmbColumns ? 10 : 9 }}" class="text-end">Grand Total</td>
                    <td>{{ $hasUsdTotal ? number_format($subtotalUsd, 2) . '$' : '—' }}</td>
                    @if($showRmbColumns)
                        <td>{{ $hasRmbTotal ? number_format($subtotalRmb, 2) . '¥' : '—' }}</td>
                    @endif
                    <td class="col-edit no-print"></td>
                </tr>
            </tfoot>
        </table>

        <div class="row mt-4">
            <div class="col-md-6">
                <div class="note-section">
                    <h6>Important Points
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor"
                            class="bi bi-plus" viewBox="0 0 16 16" onclick="addNote()"
                            style="cursor: pointer; color: #6a1b9a; border-radius: 50%; padding: 2px; background: #f3e5f5; height: 25px; width: 25px; display: inline-block; vertical-align: middle;
                            margin-left: 8px;">
                            <path
                                d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z" />
                        </svg>
                    </h6>
                    <ul class="mb-0">
                        <li>Delivery: 25 days within advance payment.</li>
                        <li>Product quality as per approved samples.</li>
                    </ul>
                </div>

                <script>
                    function addNote() {
                        const ul = document.querySelector('.note-section ul');
                        const point = prompt('Enter a new important point:');
                        if (point && point.trim() !== '') {
                            const li = document.createElement('li');
                            li.textContent = point;
                            ul.appendChild(li);
                        }
                    }
                </script>
            </div>
            <div class="col-md-6">
                <div class="totals-box">
                    @foreach($grandTotals as $curr => $total)
                        @php
                            $currencySymbol = $curr === 'RMB' ? '¥' : '$';
                        @endphp
                        <div>Subtotal: <span class="float-end">{{ $currencySymbol }}{{ number_format($total, 2) }}</span></div>
                        <div>Advance: <span class="float-end">{{ $currencySymbol }}{{ number_format($order->advance_amount ?? 0, 2) }}</span></div>
                        <div>Balance Due: <span class="float-end">{{ $currencySymbol }}{{ number_format(round($total - ($order->advance_amount ?? 0), 0), 0) }}</span></div>
                    @endforeach
                    <div class="mt-2 pt-2" style="border-top: 1px solid rgba(106,27,154,0.3);">CBM Total: <span class="float-end">{{ number_format($cbmTotal, 2) }}</span></div>
                </div>
            </div>

        </div>

        @php
            $terms = [
                'Shipping Port' => ['Tianjin', 'Guangzhou', 'Ningbo'],
                'Quality' => [
                    '• We want to have repeat order if all quality and packaging is 100% okay.',
                ],
                'Time' => [
                    '• Delivery within 25 days of deposit',
                ],
                'Packaging' => [
                    '• No printing any Chinese letters. Only "made in China" on outer box.',
                    '• Customized packing - 2 color logo on product, customized color gift box, customized manual book / inner box 3ply & outer box 5ply.',
                    '• Inner Box - Print logo, www.5CORE.com, certification, logo, barcode, SKU on GIFT boxes + inner box.',
                    '• Need to put a sticker/print with Barcode and sku on top of polymailer bag/brown inner box.',
                    '• Master carton should weigh within (15 KG to 22 kg maximum) and within size of 18x18x18 to max 25x25x25 inch.',
                    '• Master carton must contain 5 Core Logo, SKU, QTY, GW (Lbs), Size (in Inch), Box No. xx.',
                    '• Provide extra color and brown gift boxes for repackaging damaged items.',
                    '• Add color stickers on each gift and outer carton for color variants.',
                    '• Apply cello tape on corners of inner/outer box for secure packaging.',
                    '• Use standard pallet size for small loose items that are very heavy.',
                ],
                'Payment Terms' => [
                    '• 20% deposit, balance before shipping.',
                    '• 20% deposit, balance before Release of BL.',
                    '• 10% deposit, balance before Release of BL.',
                    '• 30% deposit, balance before Release of BL.',
                    '• Each item includes 2% additional free goods for damages.',
                ],
                'Replacements' => [
                    '• High-quality (8 pics) HD pictures + 1 video + description + specifications with client logo for marketing.',
                ],
                'Others' => [
                    '• User Manual /Assembly book required in English and Spanish with 5CORE logo printed on it.',
                ],
            ];
        @endphp

        <form id="termsForm" style="background: #e6e9e94d; padding: 15px 15px; margin-top:20px;border-radius: 8px;">
            <h5 class="fw-bold text-primary mt-3">Terms & Conditions:</h5>
            @foreach ($terms as $heading => $points)
                <div class="mb-1">
                    <h6>{{ $heading }}</h6>
                    @if ($heading === 'Shipping Port')
                        <select name="Shipping Port" class="form-select form-select-sm mb-2" required>
                            @foreach ($points as $port)
                                <option value="{{ $port }}">{{ $port }}</option>
                            @endforeach
                        </select>
                    @else
                        <ul class="list-unstyled">
                            @foreach ($points as $key => $point)
                                <li class="mb-0">
                                    <label>
                                        <input type="checkbox" name="terms[{{ $heading }}][]"
                                            value="{{ $point }}" checked>
                                        {{ $point }}
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach

            <div class="mb-3">
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addCustomPoint()">+ Add Custom Point</button>
            </div>

            <div id="customPoints"></div>

        </form>
    </div>

    {{-- Item Pkg / Itm pkg Cover / Ctn Pkg edit modal (Dim Wt Master data source) --}}
    <div class="modal fade" id="poPkgModal" tabindex="-1" aria-labelledby="poPkgModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="poPkgModalLabel">Edit Packaging</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2 small text-muted">
                        SKU: <strong id="poPkgModalSku">—</strong>
                    </div>
                    <div class="mb-3">
                        <label for="poPkgItemInput" class="form-label fw-semibold">Item Pkg</label>
                        <input type="text" class="form-control" id="poPkgItemInput"
                               placeholder="Item packaging instructions (from Dim Wt Master)"
                               autocomplete="off">
                        <div class="form-text">Saved to Dim Wt Master → Instructions item PKG</div>
                    </div>
                    <div class="mb-3">
                        <label for="poPkgCoverInput" class="form-label fw-semibold">Itm pkg Cover</label>
                        <input type="text" id="poPkgCoverInput" class="form-control"
                               placeholder="Image URL or path"
                               autocomplete="off">
                        <div class="form-text">Saved to product master (Values.item_pkg_cover). Leave blank to clear.</div>
                    </div>
                    <div class="mb-3">
                        <label for="poDesignFileInput" class="form-label fw-semibold">Design File</label>
                        <div class="input-group">
                            <input type="text" id="poDesignFileInput" class="form-control"
                                   placeholder="File URL or path"
                                   autocomplete="off">
                            <button type="button" class="btn btn-outline-secondary" id="poDesignFilePickBtn" title="Upload design file">
                                Add file
                            </button>
                        </div>
                        <input type="file" id="poDesignFilePicker" class="d-none"
                               accept=".cdr,.zip,.pdf,.ai,image/*,application/octet-stream">
                        <div class="form-text" id="poDesignFileHint">
                            Saved to product master (Values.packing_cdr_path). Use <strong>Add file</strong> to upload, or paste a path. Leave blank to clear.
                        </div>
                        <a href="#" id="poDesignFileOpenLink" class="small d-none" target="_blank" rel="noopener">Open current file</a>
                    </div>
                    <div class="mb-3">
                        <label for="poPkgCtnInput" class="form-label fw-semibold">Ctn Pkg</label>
                        <input type="text" class="form-control" id="poPkgCtnInput" maxlength="100"
                               placeholder="Carton packaging instructions (max 100 characters)" autocomplete="off">
                        <div class="form-text">Saved to Dim Wt Master → Ctn pkg (max 100)</div>
                    </div>
                    <div class="mb-3">
                        <label for="poCtnQtyInput" class="form-label fw-semibold">Ctn Qty</label>
                        <input type="text" class="form-control" id="poCtnQtyInput"
                               placeholder="Carton quantity" autocomplete="off">
                        <div class="form-text">Saved to Dim Wt Master → CTN (QTY) (Values.ctn_qty)</div>
                    </div>
                    <div class="mb-1">
                        <label for="poCtnPrintFileInput" class="form-label fw-semibold">Ctn Print File</label>
                        <input type="text" id="poCtnPrintFileInput" class="form-control"
                               placeholder="File URL or path"
                               autocomplete="off">
                        <div class="form-text">Saved to product master (Values.ctn_print_file). Leave blank to clear.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="poPkgSaveBtn">Save</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Special Instruction QC — numbered points modal --}}
    <div class="modal fade" id="poSpecialQcModal" tabindex="-1" aria-labelledby="poSpecialQcModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="poSpecialQcModalLabel">Special Instruction QC</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2 small text-muted">
                        SKU: <strong id="poSpecialQcModalSku">—</strong>
                    </div>
                    <div id="poSpecialQcPoints"></div>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="poSpecialQcAddPointBtn">+ Add point</button>
                    <div class="form-text mt-2">Saved as numbered points to QC Improvement Req (before item pkg).</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="poSpecialQcSaveBtn">Save</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
        function addCustomPoint() {
            const container = document.getElementById('customPoints');
            const newDiv = document.createElement('div');
            newDiv.className = "mb-0";
            newDiv.innerHTML = `
                <input type="text" name="custom_terms[]" class="form-control form-control-sm" placeholder="Enter custom point" required>
            `;
            container.appendChild(newDiv);
        }
        
        window.onbeforeprint = () => {
            // Remove all unchecked checkboxes
            const allCheckboxes = document.querySelectorAll('input[type="checkbox"]');
            allCheckboxes.forEach(checkbox => {
                if (!checkbox.checked) {
                    const li = checkbox.closest('li');
                    if (li) li.remove();
                } else {
                    checkbox.style.display = 'none'; // Hide checkbox for clean print
                }
            });

            // Remove empty custom input points
            const customInputs = document.querySelectorAll('input[name="custom_terms[]"]');
            customInputs.forEach(input => {
                if (!input.value.trim()) {
                    input.remove();
                } else {
                    const textNode = document.createElement('p');
                    textNode.textContent = input.value.trim();
                    input.parentNode.replaceChild(textNode, input);
                }
            });

            // Convert Shipping Port dropdown to plain text
            const portSelect = document.querySelector('select[name="Shipping Port"]');
            if (portSelect) {
                const selectedOption = portSelect.options[portSelect.selectedIndex];
                const selectedText = selectedOption ? selectedOption.textContent.trim() : 'N/A';

                // Create a text element only for printing
                const printSpan = document.createElement('p');
                printSpan.textContent = `Shipping Port: ${selectedText}`;
                printSpan.classList.add('print-only');
                printSpan.style.margin = '0';

                portSelect.style.display = 'none'; // hide original select
                portSelect.parentNode.appendChild(printSpan);
            }


            // Remove all buttons inside the form
            document.querySelectorAll('form#termsForm button').forEach(btn => btn.remove());

            // ✅ Remove empty heading blocks
            document.querySelectorAll('#termsForm .mb-1').forEach(section => {
                const listItems = section.querySelectorAll('li');
                const hasTextInputs = section.querySelectorAll('input[type="text"], select, textarea').length;
                const hasRemainingContent = listItems.length > 0 || hasTextInputs > 0;

                if (!hasRemainingContent) {
                    section.remove();
                }
            });

        };

        function printAsPdfStyle() {
            window.print();
        }

        (function () {
            const saveUrl = @json(!empty($order->id) ? route('purchase-order.update-item-supplier-sku', $order->id) : '');
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const usdToCny = @json($usdToCny ?? null);
            const showRmbColumns = @json($showRmbColumns ?? false);
            let editingRow = null;

            function escapeHtml(str) {
                return String(str || '')
                    .replace(/&/g, '&amp;')
                    .replace(/"/g, '&quot;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');
            }

            function fieldValue(cell) {
                if (!cell) return '';
                if (cell.hasAttribute('data-raw')) {
                    const raw = cell.getAttribute('data-raw') || '';
                    if (!raw) return '';
                    try {
                        const bin = atob(raw);
                        if (typeof TextDecoder !== 'undefined') {
                            const bytes = Uint8Array.from(bin, function (c) { return c.charCodeAt(0); });
                            return new TextDecoder('utf-8').decode(bytes);
                        }
                        return decodeURIComponent(escape(bin));
                    } catch (e) {
                        // Legacy: previously used JSON-encoded attribute values.
                        if (raw.startsWith('"') && raw.endsWith('"')) {
                            try { return JSON.parse(raw); } catch (e2) {}
                        }
                        return raw;
                    }
                }
                const textEl = cell.querySelector('.po-field-text');
                return (textEl?.textContent || '').trim();
            }

            function setEditStatus(editCell, text, kind) {
                const el = editCell?.querySelector('.po-autosave-status');
                if (!el) return;
                el.textContent = text;
                el.classList.remove('text-muted', 'text-success', 'text-danger', 'text-primary');
                el.classList.add(kind === 'ok' ? 'text-success' : (kind === 'err' ? 'text-danger' : (kind === 'busy' ? 'text-primary' : 'text-muted')));
            }

            function formatMoneyDisplay(n, suffix) {
                if (!isFinite(n)) return '—';
                return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + suffix;
            }

            function updateRowTotalsFromInputs(row) {
                const qty = parseFloat(row.querySelector('.po-line-input[data-field="qty"]')?.value) || 0;
                const usd = parseFloat(row.querySelector('.po-line-input[data-field="price_usd"]')?.value);
                const rmb = parseFloat(row.querySelector('.po-line-input[data-field="price_rmb"]')?.value);
                const totalUsdCell = row.querySelector('.col-total-usd');
                const totalRmbCell = row.querySelector('.col-total-rmb');
                if (totalUsdCell && isFinite(usd)) {
                    totalUsdCell.textContent = formatMoneyDisplay(qty * usd, '$');
                }
                if (totalRmbCell && isFinite(rmb)) {
                    totalRmbCell.textContent = formatMoneyDisplay(qty * rmb, '¥');
                }
            }

            function finishRowEdit(row, editBtnHtml, itemIndex, currency) {
                if (!row) return;
                const getVal = (field) => {
                    const input = row.querySelector('.po-line-input[data-field="' + field + '"]');
                    return input ? String(input.value || '').trim() : '';
                };
                const setText = (field, text, asHtml) => {
                    const cell = row.querySelector('.po-editable[data-field="' + field + '"]');
                    if (!cell) return;
                    if (asHtml) {
                        cell.innerHTML = '<span class="po-field-text">' + (text ? escapeHtml(text).replace(/\n/g, '<br>') : '') + '</span>';
                    } else {
                        cell.innerHTML = '<span class="po-field-text">' + escapeHtml(text) + '</span>';
                    }
                };

                const qtyVal = getVal('qty');
                const usdVal = getVal('price_usd');
                const rmbVal = getVal('price_rmb');
                const qtyN = parseFloat(qtyVal) || 0;
                const usdN = parseFloat(usdVal);
                const rmbN = parseFloat(rmbVal);

                setText('supplier_sku', getVal('supplier_sku'), false);
                setText('short_name', getVal('short_name'), false);
                setText('tech', getVal('tech'), true);
                setText('nw', getVal('nw'), false);
                setText('gw', getVal('gw'), false);
                setText('cbm', getVal('cbm'), false);
                setText('qty', qtyVal, false);

                const usdCell = row.querySelector('.po-editable[data-field="price_usd"]');
                if (usdCell) {
                    usdCell.setAttribute('data-raw', usdVal);
                    usdCell.setAttribute('data-currency-source', currency || 'USD');
                    usdCell.innerHTML = '<span class="po-field-text">'
                        + (isFinite(usdN) ? (usdN.toFixed(2).replace(/\.?0+$/, '') + '$') : '—')
                        + '</span>';
                }
                const rmbCell = row.querySelector('.po-editable[data-field="price_rmb"]');
                if (rmbCell) {
                    rmbCell.setAttribute('data-raw', rmbVal);
                    rmbCell.setAttribute('data-currency-source', currency || 'USD');
                    rmbCell.innerHTML = '<span class="po-field-text">'
                        + (isFinite(rmbN) ? (rmbN.toFixed(2).replace(/\.?0+$/, '') + '¥') : '—')
                        + '</span>';
                }

                const totalUsdCell = row.querySelector('.col-total-usd');
                const totalRmbCell = row.querySelector('.col-total-rmb');
                if (totalUsdCell) {
                    totalUsdCell.textContent = isFinite(usdN) ? formatMoneyDisplay(qtyN * usdN, '$') : '—';
                }
                if (totalRmbCell) {
                    totalRmbCell.textContent = isFinite(rmbN) ? formatMoneyDisplay(qtyN * rmbN, '¥') : '—';
                }

                const editCell = row.querySelector('.col-edit');
                if (editCell) {
                    editCell.innerHTML = editBtnHtml;
                    const newBtn = editCell.querySelector('.po-edit-btn');
                    if (newBtn) {
                        newBtn.setAttribute('data-item-index', String(itemIndex));
                        newBtn.setAttribute('data-currency', currency || 'USD');
                        delete newBtn.dataset.bound;
                        bindEditButton(newBtn);
                    }
                }
                editingRow = null;
            }

            function startEdit(btn) {
                if (!saveUrl) {
                    alert('Cannot save: purchase order id missing.');
                    return;
                }
                const row = btn.closest('tr');
                if (!row || row.querySelector('.po-line-input')) return;
                if (editingRow && editingRow !== row) {
                    alert('Finish editing the other row first (click Done).');
                    return;
                }

                const index = btn.getAttribute('data-item-index');
                const currency = (btn.getAttribute('data-currency') || 'USD').toUpperCase();
                const editBtnHtml = row.querySelector('.col-edit')?.innerHTML || '';

                const fields = [
                    { field: 'supplier_sku', type: 'text' },
                    { field: 'short_name', type: 'text', max: 40 },
                    { field: 'tech', type: 'textarea' },
                    { field: 'nw', type: 'number' },
                    { field: 'gw', type: 'number' },
                    { field: 'cbm', type: 'number' },
                    { field: 'qty', type: 'number' },
                    { field: 'price_usd', type: 'number' },
                ];
                if (showRmbColumns) {
                    fields.push({ field: 'price_rmb', type: 'number' });
                }

                fields.forEach((cfg) => {
                    const cell = row.querySelector('.po-editable[data-field="' + cfg.field + '"]');
                    if (!cell) return;
                    const val = fieldValue(cell);
                    if (cfg.type === 'textarea') {
                        cell.innerHTML = `<textarea class="po-line-input po-line-tech" data-field="${cfg.field}">${escapeHtml(val)}</textarea>`;
                    } else {
                        const maxAttr = cfg.max ? ` maxlength="${cfg.max}"` : '';
                        const step = cfg.type === 'number' ? ' step="any"' : '';
                        let readonly = '';
                        if (cfg.field === 'price_usd' && currency === 'RMB' && showRmbColumns) readonly = ' readonly';
                        if (cfg.field === 'price_rmb' && currency === 'USD') readonly = ' readonly';
                        cell.innerHTML = `<input type="${cfg.type}" class="po-line-input" data-field="${cfg.field}" value="${escapeHtml(val)}"${maxAttr}${step}${readonly}>`;
                    }
                });

                const priceUsdInput = row.querySelector('.po-line-input[data-field="price_usd"]');
                const priceRmbInput = row.querySelector('.po-line-input[data-field="price_rmb"]');
                let priceSource = currency; // 'USD' | 'RMB'
                let autosaveTimer = null;
                let saveInFlight = false;
                let saveQueued = false;
                let lastSaveOk = true;

                function syncPricesFromSource() {
                    if (!showRmbColumns || !usdToCny || !priceUsdInput || !priceRmbInput) return;
                    if (priceSource === 'RMB') {
                        const rmb = parseFloat(priceRmbInput.value);
                        priceRmbInput.readOnly = false;
                        priceUsdInput.readOnly = true;
                        priceUsdInput.value = (isFinite(rmb) && rmb > 0)
                            ? (rmb / usdToCny).toFixed(2)
                            : '';
                    } else {
                        priceUsdInput.readOnly = false;
                        priceRmbInput.readOnly = true;
                        priceRmbInput.value = '';
                    }
                }

                function buildPayload() {
                    const rmbVal = priceRmbInput ? priceRmbInput.value.trim() : '';
                    const usdVal = priceUsdInput ? priceUsdInput.value.trim() : '';
                    const payload = {
                        item_index: parseInt(index, 10),
                        currency: priceSource === 'RMB' ? 'RMB' : 'USD',
                        price_rmb: priceSource === 'RMB' ? rmbVal : '',
                        price_usd: priceSource === 'RMB' ? '' : usdVal,
                    };
                    fields.forEach((cfg) => {
                        if (cfg.field === 'price_usd' || cfg.field === 'price_rmb') return;
                        const input = row.querySelector('.po-line-input[data-field="' + cfg.field + '"]');
                        payload[cfg.field] = input ? input.value.trim() : '';
                    });
                    return payload;
                }

                async function autosaveLine() {
                    if (!row.querySelector('.po-line-input')) return;
                    if (saveInFlight) {
                        saveQueued = true;
                        return;
                    }
                    saveInFlight = true;
                    const editCell = row.querySelector('.col-edit');
                    setEditStatus(editCell, 'Saving…', 'busy');
                    try {
                        const res = await fetch(saveUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                            },
                            body: JSON.stringify(buildPayload()),
                        });
                        const resp = await res.json().catch(() => ({}));
                        if (!res.ok || !resp || resp.success === false) {
                            throw new Error(resp?.message || 'Failed to autosave');
                        }
                        updateRowTotalsFromInputs(row);
                        lastSaveOk = true;
                        setEditStatus(editCell, 'Saved', 'ok');
                    } catch (err) {
                        lastSaveOk = false;
                        setEditStatus(editCell, 'Save failed', 'err');
                    } finally {
                        saveInFlight = false;
                        if (saveQueued) {
                            saveQueued = false;
                            autosaveLine();
                        }
                    }
                }

                function scheduleAutosave() {
                    updateRowTotalsFromInputs(row);
                    if (autosaveTimer) clearTimeout(autosaveTimer);
                    setEditStatus(row.querySelector('.col-edit'), 'Editing…', 'muted');
                    autosaveTimer = setTimeout(() => {
                        autosaveTimer = null;
                        autosaveLine();
                    }, 700);
                }

                if (priceUsdInput) {
                    priceUsdInput.addEventListener('focus', function () { priceSource = 'USD'; });
                    priceUsdInput.addEventListener('input', function () {
                        priceSource = 'USD';
                        syncPricesFromSource();
                        scheduleAutosave();
                    });
                }
                if (priceRmbInput) {
                    priceRmbInput.addEventListener('focus', function () {
                        priceSource = 'RMB';
                        priceRmbInput.readOnly = false;
                        if (priceUsdInput) priceUsdInput.readOnly = true;
                    });
                    priceRmbInput.addEventListener('input', function () {
                        priceSource = 'RMB';
                        syncPricesFromSource();
                        scheduleAutosave();
                    });
                }
                syncPricesFromSource();

                row.querySelectorAll('.po-line-input').forEach((input) => {
                    if (input === priceUsdInput || input === priceRmbInput) return;
                    input.addEventListener('input', scheduleAutosave);
                    input.addEventListener('change', scheduleAutosave);
                });

                const editCell = row.querySelector('.col-edit');
                editCell.innerHTML = `
                    <div class="po-line-actions">
                        <div class="po-autosave-status text-muted small mb-1">Autosave on</div>
                        <button type="button" class="btn btn-sm btn-outline-secondary po-done-line">Done</button>
                    </div>
                `;
                editingRow = row;

                editCell.querySelector('.po-done-line').addEventListener('click', async () => {
                    if (autosaveTimer) {
                        clearTimeout(autosaveTimer);
                        autosaveTimer = null;
                        await autosaveLine();
                    } else if (!saveInFlight) {
                        await autosaveLine();
                    }
                    let wait = 0;
                    while (saveInFlight && wait < 30) {
                        await new Promise((r) => setTimeout(r, 100));
                        wait++;
                    }
                    if (!lastSaveOk) {
                        alert('Last save failed. Fix the values and wait for Saved, then click Done.');
                        return;
                    }
                    finishRowEdit(row, editBtnHtml, index, priceSource === 'RMB' ? 'RMB' : 'USD');
                });

                row.querySelector('.po-line-input')?.focus();
            }

            function bindEditButton(btn) {
                if (!btn || btn.dataset.bound === '1') return;
                btn.dataset.bound = '1';
                btn.addEventListener('click', () => startEdit(btn));
            }

            document.querySelectorAll('.po-edit-btn').forEach(bindEditButton);

            // Special Instruction QC — numbered points modal
            const specialQcUrl = @json(route('qc.improvement.req.before.item.pkg.update'));
            const specialQcModalEl = document.getElementById('poSpecialQcModal');
            const specialQcModal = specialQcModalEl ? bootstrap.Modal.getOrCreateInstance(specialQcModalEl) : null;
            let specialQcTargetCell = null;

            function parseSpecialQcPoints(text) {
                return String(text || '')
                    .split(/\r\n|\r|\n/)
                    .map((line) => line.trim().replace(/^\s*(?:\d+[\.\)]\s*|[-•]\s+)/u, '').trim())
                    .filter((line) => line !== '');
            }

            function formatSpecialQcPoints(points) {
                return (points || [])
                    .map((p) => String(p || '').trim())
                    .filter((p) => p !== '')
                    .map((p, i) => (i + 1) + '. ' + p)
                    .join('\n');
            }

            function renderSpecialQcCell(cell, text) {
                if (!cell) return;
                const points = parseSpecialQcPoints(text);
                cell.setAttribute('data-special-qc', text || '');
                if (!points.length) {
                    cell.innerHTML = '<span class="po-special-qc-empty">—</span>';
                    return;
                }
                cell.innerHTML = '<ol class="po-special-qc-list">'
                    + points.map((p) => '<li>' + escapeHtml(p) + '</li>').join('')
                    + '</ol>';
            }

            function renumberSpecialQcRows() {
                document.querySelectorAll('#poSpecialQcPoints .po-special-qc-point-num').forEach((el, i) => {
                    el.textContent = (i + 1) + '.';
                });
            }

            function addSpecialQcPointRow(value) {
                const wrap = document.getElementById('poSpecialQcPoints');
                if (!wrap) return;
                const row = document.createElement('div');
                row.className = 'po-special-qc-point-row';
                row.innerHTML = `
                    <span class="po-special-qc-point-num">1.</span>
                    <input type="text" class="form-control po-special-qc-point-input" placeholder="Enter point" autocomplete="off">
                    <button type="button" class="btn btn-outline-danger btn-sm po-special-qc-remove" title="Remove">×</button>
                `;
                const input = row.querySelector('.po-special-qc-point-input');
                if (input) input.value = value || '';
                row.querySelector('.po-special-qc-remove')?.addEventListener('click', () => {
                    row.remove();
                    renumberSpecialQcRows();
                    if (!document.querySelectorAll('#poSpecialQcPoints .po-special-qc-point-row').length) {
                        addSpecialQcPointRow('');
                    }
                });
                wrap.appendChild(row);
                renumberSpecialQcRows();
            }

            function openSpecialQcModal(cell) {
                if (!specialQcModal || !cell) return;
                const productId = cell.getAttribute('data-product-id') || '';
                const sku = decodeHtmlEntities(cell.getAttribute('data-sku') || '');
                if (!productId) {
                    alert('Product not found in Dim Wt Master for this SKU.');
                    return;
                }
                specialQcTargetCell = cell;
                document.getElementById('poSpecialQcModalSku').textContent = sku || '—';
                const pointsWrap = document.getElementById('poSpecialQcPoints');
                if (pointsWrap) pointsWrap.innerHTML = '';
                const points = parseSpecialQcPoints(cell.getAttribute('data-special-qc') || '');
                if (points.length) {
                    points.forEach((p) => addSpecialQcPointRow(p));
                } else {
                    addSpecialQcPointRow('');
                }
                specialQcModal.show();
                specialQcModalEl.addEventListener('shown.bs.modal', function onShown() {
                    specialQcModalEl.removeEventListener('shown.bs.modal', onShown);
                    document.querySelector('#poSpecialQcPoints .po-special-qc-point-input')?.focus();
                }, { once: true });
            }

            document.querySelectorAll('.po-special-qc-cell').forEach((cell) => {
                cell.addEventListener('click', () => openSpecialQcModal(cell));
                cell.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        openSpecialQcModal(cell);
                    }
                });
            });

            document.getElementById('poSpecialQcAddPointBtn')?.addEventListener('click', () => {
                addSpecialQcPointRow('');
                const inputs = document.querySelectorAll('#poSpecialQcPoints .po-special-qc-point-input');
                inputs[inputs.length - 1]?.focus();
            });

            document.getElementById('poSpecialQcSaveBtn')?.addEventListener('click', async () => {
                if (!specialQcTargetCell) return;
                const productId = parseInt(specialQcTargetCell.getAttribute('data-product-id') || '0', 10);
                if (!productId) {
                    alert('Product not found in Dim Wt Master for this SKU.');
                    return;
                }
                const points = Array.from(document.querySelectorAll('#poSpecialQcPoints .po-special-qc-point-input'))
                    .map((el) => (el.value || '').trim())
                    .filter((v) => v !== '');
                const text = formatSpecialQcPoints(points);
                const saveBtn = document.getElementById('poSpecialQcSaveBtn');
                saveBtn.disabled = true;
                saveBtn.textContent = 'Saving…';
                try {
                    const res = await fetch(specialQcUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({
                            product_id: productId,
                            qc_improvement_req: text,
                        }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || data.success === false) {
                        throw new Error(data.message || 'Failed to save Special Instruction QC');
                    }
                    const saved = data.qc_improvement_req != null ? String(data.qc_improvement_req) : text;
                    renderSpecialQcCell(specialQcTargetCell, saved);
                    specialQcModal.hide();
                } catch (err) {
                    alert(err.message || 'Failed to save Special Instruction QC');
                } finally {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save';
                }
            });

            // Item Pkg / Cover / Design File / Ctn Pkg modal
            const coverUploadUrl = @json(route('purchase-order.item-pkg-cover'));
            const designFileUrl = @json(route('purchase-order.design-file'));
            const designFileUploadUrl = @json(route('packing.instructions.master.upload.cdr'));
            const ctnPrintFileUrl = @json(route('purchase-order.ctn-print-file'));
            const itemPkgUrl = @json(route('instructions.item.pkg.update'));
            const ctnPkgUrl = @json(route('dim.wt.master.update'));
            let pkgInitialCtnQty = '';
            let pkgInitialCtnPrint = '';
            const pkgModalEl = document.getElementById('poPkgModal');
            const pkgModal = pkgModalEl ? bootstrap.Modal.getOrCreateInstance(pkgModalEl) : null;
            let pkgTargetCell = null;

            function decodeHtmlEntities(str) {
                if (!str) return '';
                const el = document.createElement('textarea');
                el.innerHTML = str;
                return el.value;
            }

            function isImagePath(url) {
                return /\.(jpe?g|png|gif|webp|bmp|svg)(\?|$)/i.test(String(url || ''));
            }

            function fileBasename(url) {
                const u = String(url || '').trim();
                if (!u) return '';
                try {
                    const path = u.split('?')[0];
                    const parts = path.split('/');
                    return parts[parts.length - 1] || u;
                } catch (e) {
                    return u;
                }
            }

            function updateFileOpenLink(linkId, url) {
                const link = document.getElementById(linkId);
                if (!link) return;
                const u = (url || '').trim();
                if (u) {
                    link.href = u.startsWith('http') || u.startsWith('/') ? u : ('/' + u.replace(/^\/+/, ''));
                    link.classList.remove('d-none');
                } else {
                    link.href = '#';
                    link.classList.add('d-none');
                }
            }

            function updateDesignFileOpenLink(url) {
                updateFileOpenLink('poDesignFileOpenLink', url);
            }

            function renderPkgText(el, text) {
                const t = (text || '').trim();
                if (!el) return;
                el.innerHTML = t ? escapeHtml(t).replace(/\n/g, '<br>') : '<span class="text-muted">—</span>';
            }

            function renderFileValueHtml(url) {
                const u = (url || '').trim();
                if (!u) return '<span class="text-muted">—</span>';
                if (isImagePath(u)) {
                    return `<img src="${escapeHtml(u)}" alt="" class="po-pkg-combined-thumb">`;
                }
                return `<span class="po-pkg-combined-link">${escapeHtml(fileBasename(u) || 'File')}</span>`;
            }

            function syncPkgCellData(row, itemPkg, ctnPkg, coverUrl, designUrl, ctnQty, ctnPrintUrl) {
                if (!row) return;
                const cell = row.querySelector('.po-pkg-combined');
                if (!cell) return;
                cell.setAttribute('data-item-pkg', itemPkg);
                cell.setAttribute('data-ctn-pkg', ctnPkg);
                if (coverUrl !== undefined) cell.setAttribute('data-cover-url', (coverUrl || '').trim());
                if (designUrl !== undefined) cell.setAttribute('data-design-file', (designUrl || '').trim());
                if (ctnQty !== undefined) cell.setAttribute('data-ctn-qty', ctnQty == null ? '' : String(ctnQty));
                if (ctnPrintUrl !== undefined) cell.setAttribute('data-ctn-print-file', (ctnPrintUrl || '').trim());

                renderPkgText(cell.querySelector('.po-item-pkg-text'), itemPkg);
                renderPkgText(cell.querySelector('.po-ctn-pkg-text'), ctnPkg);

                const coverEl = cell.querySelector('.po-cover-text');
                if (coverEl && coverUrl !== undefined) coverEl.innerHTML = renderFileValueHtml(coverUrl);

                const designEl = cell.querySelector('.po-design-text');
                if (designEl && designUrl !== undefined) designEl.innerHTML = renderFileValueHtml(designUrl);

                const qtyEl = cell.querySelector('.po-ctn-qty-text');
                if (qtyEl && ctnQty !== undefined) {
                    const q = ctnQty == null ? '' : String(ctnQty).trim();
                    qtyEl.innerHTML = q !== '' ? escapeHtml(q) : '<span class="text-muted">—</span>';
                }

                const printEl = cell.querySelector('.po-ctn-print-text');
                if (printEl && ctnPrintUrl !== undefined) {
                    printEl.innerHTML = renderFileValueHtml(ctnPrintUrl);
                }
            }

            function openPkgModal(cell) {
                if (!pkgModal || !cell) return;
                const source = cell.classList.contains('po-pkg-combined')
                    ? cell
                    : (cell.closest('tr')?.querySelector('.po-pkg-combined') || cell);
                const productId = source.getAttribute('data-product-id') || '';
                const sku = decodeHtmlEntities(source.getAttribute('data-sku') || '');
                if (!productId) {
                    alert('Product not found in Dim Wt Master for this SKU.');
                    return;
                }
                pkgTargetCell = source;
                document.getElementById('poPkgModalSku').textContent = sku || '—';
                document.getElementById('poPkgItemInput').value = decodeHtmlEntities(source.getAttribute('data-item-pkg') || '');
                document.getElementById('poPkgCtnInput').value = decodeHtmlEntities(source.getAttribute('data-ctn-pkg') || '');

                const coverInputEl = document.getElementById('poPkgCoverInput');
                const currentCover = (source.getAttribute('data-cover-url') || '').trim();
                if (coverInputEl) coverInputEl.value = currentCover;

                const designInputEl = document.getElementById('poDesignFileInput');
                const designPickerEl = document.getElementById('poDesignFilePicker');
                const currentDesign = (source.getAttribute('data-design-file') || '').trim();
                if (designInputEl) designInputEl.value = currentDesign;
                if (designPickerEl) designPickerEl.value = '';
                updateDesignFileOpenLink(currentDesign);

                const currentCtnQty = String(source.getAttribute('data-ctn-qty') || '').trim();
                const currentCtnPrint = String(source.getAttribute('data-ctn-print-file') || '').trim();
                pkgInitialCtnQty = currentCtnQty;
                pkgInitialCtnPrint = currentCtnPrint;
                const ctnQtyEl = document.getElementById('poCtnQtyInput');
                const ctnPrintEl = document.getElementById('poCtnPrintFileInput');
                if (ctnQtyEl) ctnQtyEl.value = currentCtnQty;
                if (ctnPrintEl) ctnPrintEl.value = currentCtnPrint;

                pkgModal.show();
                pkgModalEl.addEventListener('shown.bs.modal', function onShown() {
                    pkgModalEl.removeEventListener('shown.bs.modal', onShown);
                    document.getElementById('poPkgItemInput')?.focus();
                }, { once: true });
            }

            document.querySelectorAll('.po-pkg-combined').forEach((cell) => {
                cell.addEventListener('click', () => openPkgModal(cell));
                cell.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        openPkgModal(cell);
                    }
                });
            });

            document.getElementById('poDesignFileInput')?.addEventListener('input', function () {
                updateDesignFileOpenLink(this.value || '');
            });

            document.getElementById('poDesignFilePickBtn')?.addEventListener('click', () => {
                document.getElementById('poDesignFilePicker')?.click();
            });

            document.getElementById('poDesignFilePicker')?.addEventListener('change', async function () {
                const file = this.files && this.files[0];
                if (!file || !pkgTargetCell) return;
                const sku = decodeHtmlEntities(pkgTargetCell.getAttribute('data-sku') || '');
                if (!sku) {
                    alert('SKU is required to upload a Design File.');
                    this.value = '';
                    return;
                }
                const pickBtn = document.getElementById('poDesignFilePickBtn');
                const hint = document.getElementById('poDesignFileHint');
                if (pickBtn) {
                    pickBtn.disabled = true;
                    pickBtn.textContent = 'Uploading…';
                }
                if (hint) hint.textContent = 'Uploading design file…';
                try {
                    const fd = new FormData();
                    fd.append('sku', sku);
                    fd.append('cdr', file);
                    const res = await fetch(designFileUploadUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: fd,
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || data.success === false) {
                        throw new Error(data.message || 'Failed to upload Design File');
                    }
                    const savedPath = (data.url || data.path || '').trim();
                    const input = document.getElementById('poDesignFileInput');
                    if (input) input.value = savedPath;
                    updateDesignFileOpenLink(savedPath);
                    const row = pkgTargetCell.closest('tr');
                    syncPkgCellData(
                        row,
                        (document.getElementById('poPkgItemInput')?.value || '').trim(),
                        (document.getElementById('poPkgCtnInput')?.value || '').trim().slice(0, 100),
                        undefined,
                        savedPath
                    );
                    if (hint) {
                        hint.innerHTML = 'Saved to product master (Values.packing_cdr_path). Use <strong>Add file</strong> to upload, or paste a path. Leave blank to clear.';
                    }
                } catch (err) {
                    alert(err.message || 'Failed to upload Design File');
                    if (hint) {
                        hint.innerHTML = 'Saved to product master (Values.packing_cdr_path). Use <strong>Add file</strong> to upload, or paste a path. Leave blank to clear.';
                    }
                } finally {
                    this.value = '';
                    if (pickBtn) {
                        pickBtn.disabled = false;
                        pickBtn.textContent = 'Add file';
                    }
                }
            });

            document.getElementById('poPkgSaveBtn')?.addEventListener('click', async () => {
                if (!pkgTargetCell) return;
                const productId = parseInt(pkgTargetCell.getAttribute('data-product-id') || '0', 10);
                const sku = decodeHtmlEntities(pkgTargetCell.getAttribute('data-sku') || '');
                if (!productId) {
                    alert('Product not found in Dim Wt Master for this SKU.');
                    return;
                }

                const itemPkg = (document.getElementById('poPkgItemInput').value || '').trim();
                const ctnPkg = (document.getElementById('poPkgCtnInput').value || '').trim().slice(0, 100);
                const ctnQtyRaw = (document.getElementById('poCtnQtyInput')?.value || '').trim();
                const ctnPrintPath = (document.getElementById('poCtnPrintFileInput')?.value || '').trim();
                const row = pkgTargetCell.closest('tr');
                const previousCover = (pkgTargetCell.getAttribute('data-cover-url') || '').trim();
                const previousDesign = (pkgTargetCell.getAttribute('data-design-file') || '').trim();
                const coverPath = (document.getElementById('poPkgCoverInput')?.value || '').trim();
                const designPath = (document.getElementById('poDesignFileInput')?.value || '').trim();
                const coverChanged = coverPath !== previousCover;
                const designChanged = designPath !== previousDesign;
                const ctnQtyChanged = ctnQtyRaw !== String(pkgInitialCtnQty || '').trim();
                const ctnPrintChanged = ctnPrintPath !== String(pkgInitialCtnPrint || '').trim();
                const saveBtn = document.getElementById('poPkgSaveBtn');
                saveBtn.disabled = true;
                saveBtn.textContent = 'Saving…';

                try {
                    // product_id is the source of truth; omit sku on item-pkg to avoid quote/entity mismatches
                    const itemRes = await fetch(itemPkgUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({
                            product_id: productId,
                            instructions: itemPkg,
                        }),
                    });
                    const itemData = await itemRes.json().catch(() => ({}));
                    if (!itemRes.ok || itemData.success === false) {
                        throw new Error(itemData.message || 'Failed to save Item Pkg');
                    }

                    const ctnPayload = {
                        product_id: productId,
                        sku: sku,
                        ctn_instructions: ctnPkg || null,
                    };
                    if (ctnQtyChanged) {
                        ctnPayload.ctn_qty = ctnQtyRaw === '' ? null : ctnQtyRaw;
                    }
                    const ctnRes = await fetch(ctnPkgUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify(ctnPayload),
                    });
                    const ctnData = await ctnRes.json().catch(() => ({}));
                    if (!ctnRes.ok || ctnData.success === false) {
                        throw new Error(ctnData.message || 'Failed to save Ctn Pkg / Ctn Qty');
                    }

                    let savedCoverUrl;
                    if (coverChanged) {
                        const coverRes = await fetch(coverUploadUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                            },
                            body: JSON.stringify({
                                product_id: productId,
                                sku: sku,
                                path: coverPath,
                            }),
                        });
                        const coverData = await coverRes.json().catch(() => ({}));
                        if (!coverRes.ok || coverData.success === false) {
                            throw new Error(coverData.message || 'Failed to save Itm pkg Cover');
                        }
                        savedCoverUrl = coverData.url != null ? String(coverData.url) : coverPath;
                    }

                    let savedDesignUrl;
                    if (designChanged) {
                        const designRes = await fetch(designFileUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                            },
                            body: JSON.stringify({
                                product_id: productId,
                                sku: sku,
                                path: designPath,
                            }),
                        });
                        const designData = await designRes.json().catch(() => ({}));
                        if (!designRes.ok || designData.success === false) {
                            throw new Error(designData.message || 'Failed to save Design File');
                        }
                        savedDesignUrl = designData.url != null ? String(designData.url) : designPath;
                    }

                    let savedCtnPrintUrl;
                    if (ctnPrintChanged) {
                        const printRes = await fetch(ctnPrintFileUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                            },
                            body: JSON.stringify({
                                product_id: productId,
                                sku: sku,
                                path: ctnPrintPath,
                            }),
                        });
                        const printData = await printRes.json().catch(() => ({}));
                        if (!printRes.ok || printData.success === false) {
                            throw new Error(printData.message || 'Failed to save Ctn Print File');
                        }
                        savedCtnPrintUrl = printData.url != null ? String(printData.url) : ctnPrintPath;
                        pkgInitialCtnPrint = savedCtnPrintUrl;
                    }

                    const savedItem = (itemData.instructions != null ? String(itemData.instructions) : itemPkg).trim();
                    const savedCtn = ctnPkg;
                    const savedCtnQty = ctnQtyChanged ? ctnQtyRaw : undefined;
                    if (ctnQtyChanged) pkgInitialCtnQty = ctnQtyRaw;
                    syncPkgCellData(row, savedItem, savedCtn, savedCoverUrl, savedDesignUrl, savedCtnQty, savedCtnPrintUrl);
                    pkgModal.hide();
                } catch (err) {
                    alert(err.message || 'Failed to save packaging');
                } finally {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save';
                }
            });

            // Column / For-all clipboard copy
            const copyColMap = {
                product: '.col-product',
                short_name: '.col-short-name',
                tech: '.col-tech',
                packaging: '.col-pkg',
                dims: '.col-dims',
                special_qc: '.col-special-qc',
                qty: '.col-qty',
                price_usd: '.col-price-usd',
                price_rmb: '.col-price-rmb',
                total_usd: '.col-total-usd',
                total_rmb: '.col-total-rmb',
            };
            const copyColLabels = {
                product: 'Product',
                short_name: 'Short Name',
                tech: 'Tech',
                packaging: 'Packaging',
                dims: 'NW (kg) / GW (kg) / CBM',
                special_qc: 'Special Instruction QC',
                qty: 'QTY',
                price_usd: 'Rate $',
                price_rmb: 'Rate ¥',
                total_usd: 'Total ($)',
                total_rmb: 'Total (¥)',
            };

            function normalizeCopyText(text) {
                return String(text || '')
                    .replace(/\u00a0/g, ' ')
                    .replace(/[ \t]+\n/g, '\n')
                    .replace(/\n{3,}/g, '\n\n')
                    .replace(/[ \t]{2,}/g, ' ')
                    .trim();
            }

            function cellCopyText(cell) {
                if (!cell) return '';
                const clone = cell.cloneNode(true);
                clone.querySelectorAll('button, .po-copy-col-btn, .po-edit-btn, .po-line-actions, input, textarea, img').forEach((el) => el.remove());
                const input = cell.querySelector('.po-line-input, .po-supplier-sku-input');
                if (input) {
                    return normalizeCopyText(input.value);
                }
                const pkg = cell.querySelector('.po-pkg-combined');
                if (pkg) {
                    const parts = [];
                    pkg.querySelectorAll('.po-pkg-combined-row').forEach((rowEl) => {
                        const label = normalizeCopyText(rowEl.querySelector('.po-pkg-combined-label')?.textContent || '');
                        const valueEl = rowEl.querySelector('.po-pkg-combined-value');
                        let value = '';
                        if (valueEl) {
                            const link = valueEl.querySelector('.po-pkg-combined-link');
                            const thumb = valueEl.querySelector('img');
                            if (link) value = normalizeCopyText(link.textContent);
                            else if (thumb) value = normalizeCopyText(thumb.getAttribute('src') || thumb.getAttribute('alt') || '');
                            else value = normalizeCopyText(valueEl.textContent);
                        }
                        if (value === '—' || value === '-') value = '';
                        parts.push(label ? (label + ': ' + value) : value);
                    });
                    return parts.filter(Boolean).join('\n');
                }
                const dims = cell.querySelector('.po-dims-cell');
                if (dims) {
                    const parts = [];
                    dims.querySelectorAll('.po-dims-row').forEach((rowEl) => {
                        const label = normalizeCopyText(rowEl.querySelector('.po-dims-label')?.textContent || '');
                        const value = normalizeCopyText(rowEl.querySelector('.po-dims-value')?.textContent || '');
                        parts.push(label ? (label + ': ' + value) : value);
                    });
                    return parts.filter(Boolean).join('\n');
                }
                const product = cell.querySelector('.po-product-cell');
                if (product) {
                    const sku = normalizeCopyText(product.querySelector('.po-barcode-sku')?.textContent || '');
                    const code = normalizeCopyText(product.querySelector('.po-barcode-code')?.textContent || '');
                    const supplier = normalizeCopyText(product.querySelector('.po-product-supplier')?.textContent || '');
                    return [sku, code ? ('UPC: ' + code) : '', supplier ? ('Supplier SKU: ' + supplier) : '']
                        .filter(Boolean)
                        .join('\n');
                }
                const qcList = cell.querySelectorAll('.po-special-qc-list li');
                if (qcList.length) {
                    return Array.from(qcList)
                        .map((li, idx) => (idx + 1) + '. ' + normalizeCopyText(li.textContent))
                        .join('\n');
                }
                return normalizeCopyText(clone.textContent);
            }

            async function writeClipboard(text) {
                const value = String(text || '');
                if (!value) throw new Error('Nothing to copy');
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(value);
                    return;
                }
                const ta = document.createElement('textarea');
                ta.value = value;
                ta.setAttribute('readonly', '');
                ta.style.position = 'fixed';
                ta.style.left = '-9999px';
                document.body.appendChild(ta);
                ta.select();
                const ok = document.execCommand('copy');
                document.body.removeChild(ta);
                if (!ok) throw new Error('Copy failed');
            }

            function flashCopyBtn(btn, okLabel) {
                if (!btn) return;
                btn.classList.add('is-copied');
                const label = btn.querySelector('span');
                const prev = label ? label.textContent : null;
                if (label && okLabel) label.textContent = okLabel;
                setTimeout(() => {
                    btn.classList.remove('is-copied');
                    if (label && okLabel) label.textContent = prev;
                }, 1200);
            }

            function getBodyRows() {
                return Array.from(document.querySelectorAll('#poItemsTable tbody tr'));
            }

            function getVisibleCopyKeys() {
                return Array.from(document.querySelectorAll('#poItemsTable thead th[data-copy-key]'))
                    .map((th) => th.getAttribute('data-copy-key'))
                    .filter((key) => key && copyColMap[key]);
            }

            function columnValues(key) {
                const selector = copyColMap[key];
                if (!selector) return [];
                return getBodyRows().map((row) => cellCopyText(row.querySelector(selector)));
            }

            function buildColumnCopyText(key) {
                const label = copyColLabels[key] || key;
                const values = columnValues(key);
                return [label].concat(values).join('\n');
            }

            function buildAllCopyText() {
                const keys = getVisibleCopyKeys();
                const headers = keys.map((k) => copyColLabels[k] || k);
                const rows = getBodyRows().map((row) =>
                    keys.map((key) => cellCopyText(row.querySelector(copyColMap[key])).replace(/\t/g, ' ').replace(/\n/g, ' | '))
                );
                return [headers.join('\t')]
                    .concat(rows.map((cols) => cols.join('\t')))
                    .join('\n');
            }

            document.querySelectorAll('.po-copy-col-btn').forEach((btn) => {
                btn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const key = btn.getAttribute('data-copy-col');
                    try {
                        await writeClipboard(buildColumnCopyText(key));
                        flashCopyBtn(btn);
                    } catch (err) {
                        alert(err.message || 'Failed to copy column');
                    }
                });
            });

            document.getElementById('poCopyAllBtn')?.addEventListener('click', async (e) => {
                e.preventDefault();
                try {
                    await writeClipboard(buildAllCopyText());
                    flashCopyBtn(e.currentTarget, 'Copied');
                } catch (err) {
                    alert(err.message || 'Failed to copy table');
                }
            });
        })();

    </script>
</body>

</html>
