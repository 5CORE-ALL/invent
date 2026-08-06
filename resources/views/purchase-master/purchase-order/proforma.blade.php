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

        .totals-box .po-advance-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
        }

        .totals-box .po-advance-label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .totals-box .po-advance-percent-input {
            width: 64px;
            border: 1px solid #ce93d8;
            border-radius: 4px;
            padding: 2px 6px;
            background: #fffde7;
            color: #6a1b9a;
            font-weight: 700;
            text-align: center;
        }

        .totals-box .po-advance-amount,
        .totals-box .po-balance-due {
            background: #fffde7;
            border-radius: 4px;
            padding: 2px 10px;
            min-width: 72px;
            text-align: right;
            display: inline-block;
        }

        .totals-box .po-advance-save-hint {
            font-size: 11px;
            font-weight: 500;
            color: #8e24aa;
            min-height: 14px;
        }

        .po-advance-percent-print {
            display: none;
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
            .col-qc,
            .po-edit-btn,
            .po-delete-btn,
            .po-qc-btn,
            .po-line-actions,
            .po-supplier-sku-edit,
            .po-copy-col-btn,
            .po-copy-toolbar,
            .po-approvals.no-print,
            .po-rate-cp-icon,
            .po-advance-percent-input,
            .po-advance-percent-suffix,
            .po-advance-save-hint,
            .po-missing-badge {
                display: none !important;
            }

            .po-missing-print-dash {
                display: inline !important;
            }

            .po-approvals-print.only-print {
                display: block !important;
            }

            .po-advance-percent-print {
                display: inline !important;
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

        .po-product-photo--empty {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            font-size: 11px;
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
            width: 40px;
            min-width: 40px;
        }

        .po-edit-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            padding: 0;
            border: 1px solid #3949ab;
            color: #3949ab;
            background: #fff;
            border-radius: 6px;
            font-size: 14px;
            line-height: 1;
            cursor: pointer;
        }

        .po-edit-btn:hover {
            background: #3949ab;
            color: #fff;
        }

        .po-rate-cell {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            flex-wrap: wrap;
        }

        .po-rate-cp-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 16px;
            height: 16px;
            flex-shrink: 0;
            vertical-align: middle;
        }

        /* Pure CSS icons — proforma page has no MDI stylesheet */
        .po-rate-cp-icon--high::before,
        .po-rate-cp-icon--same::before {
            content: '';
            width: 0;
            height: 0;
            border-left: 7px solid transparent;
            border-right: 7px solid transparent;
            border-bottom: 13px solid currentColor;
            display: block;
        }

        .po-rate-cp-icon--high {
            color: #dc2626;
        }

        .po-rate-cp-icon--same {
            color: #f59e0b;
        }

        .po-rate-cp-icon--low::before {
            content: '✓';
            display: block;
            font-size: 15px;
            font-weight: 800;
            line-height: 1;
            color: #16a34a;
        }

        .po-rate-cp-icon--low {
            color: #16a34a;
        }

        .col-price-usd.po-rate-not-lowest,
        .col-price-usd.po-rate-not-lowest .po-rate-cell,
        .col-price-usd.po-rate-not-lowest .po-field-text {
            background-color: #fecaca !important;
        }

        .col-price-usd.po-rate-not-lowest {
            box-shadow: inset 0 0 0 1px #ef4444;
        }

        .po-line-actions {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }

        .po-delete-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            padding: 0;
            border: 1px solid #c62828;
            border-radius: 6px;
            background: #fff;
            color: #c62828;
            font-size: 16px;
            font-weight: 700;
            line-height: 1;
            cursor: pointer;
        }

        .po-delete-btn:hover {
            background: #c62828;
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

        .po-add-row-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 26px;
            height: 28px;
            padding: 0 8px;
            border: 1px solid #3949ab;
            border-radius: 6px;
            background: #fff;
            color: #3949ab;
            font-size: 18px;
            font-weight: 700;
            line-height: 1;
            cursor: pointer;
        }

        .po-add-row-btn:hover {
            background: #3949ab;
            color: #fff;
        }

        #poAddRowModal .form-label {
            font-size: 12px;
            margin-bottom: 2px;
        }

        #poAddRowModal .modal-body {
            max-height: min(70vh, 640px);
            overflow-y: auto;
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
            vertical-align: top !important;
        }

        .po-tech-block {
            display: flex;
            flex-direction: column;
            gap: 8px;
            text-align: left;
        }

        .po-tech-text {
            word-wrap: break-word;
            white-space: normal;
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

        .col-claim {
            min-width: 150px;
            max-width: 220px;
            font-size: 10px;
            vertical-align: top !important;
            text-align: left;
            line-height: 1.3;
        }

        .po-claim-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .po-claim-card {
            border: 1px solid #fde68a;
            background: #fffbeb;
            border-radius: 6px;
            padding: 5px 7px;
        }

        .po-claim-num {
            font-weight: 700;
            color: #92400e;
            text-decoration: none;
        }

        .po-claim-num:hover {
            text-decoration: underline;
        }

        .po-claim-meta {
            color: #78350f;
            font-weight: 600;
        }

        .po-claim-reason {
            color: #44403c;
            margin-top: 2px;
            word-break: break-word;
        }

        .col-qc {
            width: 48px;
            min-width: 48px;
            text-align: center;
            vertical-align: middle !important;
        }

        .po-qc-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            padding: 0;
            border: 2px solid currentColor;
            border-radius: 8px;
            background: #fff;
            line-height: 1;
            cursor: pointer;
        }

        .po-qc-btn .po-qc-icon {
            width: 18px;
            height: 18px;
            display: block;
        }

        /* No QC data → red */
        .po-qc-btn.po-qc-btn--empty {
            color: #dc2626;
            border-color: #dc2626;
            background: #fff5f5;
        }

        .po-qc-btn.po-qc-btn--empty:hover {
            background: #dc2626;
            color: #fff;
        }

        /* Has QC data → dark yellow */
        .po-qc-btn.po-qc-btn--has-data {
            color: #a16207;
            border-color: #a16207;
            background: #fffbeb;
        }

        .po-qc-btn.po-qc-btn--has-data:hover {
            background: #a16207;
            color: #fff;
        }

        .po-approvals-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .po-approval-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-width: 140px;
            padding: 10px 14px;
            border: 1px solid #c7d2fe;
            border-radius: 10px;
            background: #fff;
            color: #1e293b;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s ease, border-color 0.15s ease;
        }

        .po-approval-btn.is-approved {
            border-color: #86efac;
            background: #f0fdf4;
        }

        .po-approval-btn.is-locked {
            opacity: 0.75;
            cursor: not-allowed;
        }

        .po-approval-btn:not(.is-locked):hover {
            border-color: #6366f1;
            background: #eef2ff;
        }

        .po-approval-status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            flex-shrink: 0;
        }

        .po-approval-dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #dc2626;
            display: inline-block;
            box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.15);
        }

        .po-approval-tick {
            color: #16a34a;
            font-size: 22px;
            line-height: 1;
        }

        .po-approval-label {
            font-size: 14px;
        }

        .po-approval-meta {
            font-size: 11px;
            font-weight: 500;
            color: #64748b;
        }

        .po-approvals-print.only-print {
            display: none;
        }

        .po-approvals-print-row {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
        }

        .po-approvals-print-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
        }

        .po-bank-block {
            border: 1px solid #e3e6ea;
            border-radius: 10px;
            padding: 12px 14px;
            background: #f8fafc;
        }

        .po-bank-empty {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #64748b;
            font-size: 13px;
        }

        .po-bank-accounts {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .po-bank-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 12px;
        }

        .po-bank-card-title {
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 6px;
            font-size: 13px;
        }

        .po-bank-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 4px 12px;
            font-size: 12px;
            color: #334155;
        }

        .po-bank-label {
            font-weight: 700;
            color: #64748b;
            margin-right: 4px;
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

        .po-missing-badge {
            display: inline-block;
            padding: 1px 7px;
            border-radius: 999px;
            background: #fde8e8;
            color: #b42318;
            border: 1px solid #f5c2c7;
            font-size: 10px;
            font-weight: 700;
            line-height: 1.35;
            white-space: nowrap;
            vertical-align: middle;
        }

        .po-missing-print-dash {
            display: none;
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

        .po-dims-cell {
            display: flex;
            flex-direction: column;
            gap: 4px;
            line-height: 1.25;
            border-top: 1px solid #e5e7eb;
            padding-top: 6px;
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
            min-width: 52px;
        }

        .po-dims-value {
            font-size: 12px;
            font-weight: 600;
            color: #1a237e;
            min-width: 40px;
        }

        .po-dims-value .po-line-input {
            min-width: 70px;
            width: 80px;
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
                    <span class="ms-1">{{ strtoupper(\Carbon\Carbon::parse($order->po_date ?? now())->format('j M y')) }}</span>
                </div>
            </div>
        </div>

        {{-- Invoice Header --}}
        <div class="row invoice-header">
            <div class="col-md-6">
                <p>
                    <strong>From:</strong> {{ $from['name'] ?? '5 CORE INC' }}<br>
                    {!! $from['address'] ?? '1221 W.SANDUSKY AVE,<br>BELLEFONTAINE OH43311, USA' !!}<br>
                    {{-- Email: {{ $from['email'] ?? 'president@5core.com' }}<br>
                    Phone: {{ $from['phone'] ?? '+1(714)249-0848' }} --}}
                </p>
            </div>
            <div class="col-md-6 text-end">
                <p>
                    <strong>To:</strong> {{ $supplier->name ?? 'John Doe' }}<br>
                    {{ $supplier->company ?? 'ABC Imports Ltd.' }}<br>
                    Email: {{ $supplier->email ?? 'john@abcimports.com' }}
                </p>
            </div>
        </div>

        @php
            $bankAccounts = $bankAccounts ?? collect();
            $canEditPoBank = (bool) ($canEditPoBank ?? false);
            $hasBankAccounts = $bankAccounts instanceof \Illuminate\Support\Collection
                ? $bankAccounts->isNotEmpty()
                : (is_array($bankAccounts) && count($bankAccounts) > 0);
            $poSupplierId = (int) ($order->supplier_id ?? ($supplier->id ?? 0));
            $poMissing = '<span class="po-missing-badge no-print">Missing</span><span class="po-missing-print-dash">—</span>';
            $poMissingEdit = '<span class="badge bg-danger-subtle text-danger po-missing-badge no-print">Missing — edit: purchase@5core.com</span>';
            $bankFieldLabels = [
                'Supplier', 'Beneficiary', 'Nick', 'SWIFT', 'Account',
                'Address', 'City', 'Province', 'Country',
            ];
            $bankHasBlankField = false;
            if ($hasBankAccounts) {
                foreach ($bankAccounts as $__acct) {
                    foreach ([
                        $__acct->supplier_name ?? null,
                        $__acct->company_name ?? null,
                        $__acct->nick_name ?? null,
                        $__acct->swift ?? null,
                        $__acct->account_number ?? null,
                        $__acct->address ?? null,
                        $__acct->city ?? null,
                        $__acct->province ?? null,
                        $__acct->country ?? null,
                    ] as $__val) {
                        if (trim((string) $__val) === '') {
                            $bankHasBlankField = true;
                            break 2;
                        }
                    }
                }
            }
        @endphp
        <div class="po-bank-block mb-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                <h6 class="mb-0 fw-bold text-primary">Bank Details</h6>
                @if(!$hasBankAccounts && $canEditPoBank && $poSupplierId > 0)
                    <button type="button" class="btn btn-sm btn-outline-primary no-print" id="poBankEditBtn">
                        Edit Bank Details
                    </button>
                @elseif(!$hasBankAccounts && !$canEditPoBank)
                    {!! $poMissingEdit !!}
                @elseif($hasBankAccounts && $bankHasBlankField && !$canEditPoBank)
                    {!! $poMissingEdit !!}
                @elseif($hasBankAccounts && $bankHasBlankField && $canEditPoBank && $poSupplierId > 0)
                    <button type="button" class="btn btn-sm btn-outline-primary no-print" id="poBankEditBtn">
                        Edit Bank Details
                    </button>
                @endif
            </div>
            @if($hasBankAccounts)
                <div class="po-bank-accounts">
                    @foreach($bankAccounts as $acct)
                        <div class="po-bank-card">
                            <div class="po-bank-card-title">
                                {{ $acct->nick_name ?: ($acct->company_name ?: ('Account #'.$acct->id)) }}
                            </div>
                            <div class="po-bank-grid">
                                @foreach([
                                    'Supplier' => $acct->supplier_name,
                                    'Beneficiary' => $acct->company_name,
                                    'Nick' => $acct->nick_name,
                                    'SWIFT' => $acct->swift,
                                    'Account' => $acct->account_number,
                                    'Address' => $acct->address,
                                    'City' => $acct->city,
                                    'Province' => $acct->province,
                                    'Country' => $acct->country,
                                ] as $label => $val)
                                    <div>
                                        <span class="po-bank-label">{{ $label }}:</span>
                                        @if(trim((string) $val) !== '')
                                            {{ $val }}
                                        @else
                                            {!! $poMissing !!}
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="po-bank-empty mb-2">
                    <span class="po-approval-dot" aria-hidden="true"></span>
                    <span>No bank details on supplier list for this supplier.</span>
                </div>
                <div class="po-bank-grid">
                    @foreach($bankFieldLabels as $label)
                        <div>
                            <span class="po-bank-label">{{ $label }}:</span>
                            {!! $poMissing !!}
                        </div>
                    @endforeach
                </div>
            @endif
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
            <button type="button" class="po-add-row-btn" id="poAddRowBtn" title="Add row">+</button>
            <button type="button" class="po-add-row-btn" id="poAddFromToOrderBtn" title="Add SKUs from Order page for this supplier (Order qty &gt; 0 only)">++</button>
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
                            <span>Name</span>
                            <button type="button" class="po-copy-col-btn no-print" data-copy-col="short_name" title="Copy Name column">{!! $poCopyIcon !!}</button>
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
                    <th class="col-special-qc" data-copy-key="special_qc">
                        <span class="po-th-wrap">
                            <span>Special Instruction QC</span>
                            <button type="button" class="po-copy-col-btn no-print" data-copy-col="special_qc" title="Copy Special Instruction QC column">{!! $poCopyIcon !!}</button>
                        </span>
                    </th>
                    <th class="col-claim" data-copy-key="claim" title="From /claim-reimbursement (this supplier)">
                        <span class="po-th-wrap">
                            <span>Claim &amp; Reimb.</span>
                            <button type="button" class="po-copy-col-btn no-print" data-copy-col="claim" title="Copy Claim column">{!! $poCopyIcon !!}</button>
                        </span>
                    </th>
                    <th class="col-qc no-print" title="QC &amp; Packing issues (SKU + siblings)">QC</th>
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
                    <th class="col-edit no-print" title="Edit"></th>
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
                            @php
                                $displaySku = $item->product_master_sku ?? $item->sku ?? '';
                            @endphp
                            <div class="po-product-cell">
                                @if(!empty($item->photo_url))
                                    <img src="{{ $item->photo_url }}"
                                         alt="{{ $displaySku }}"
                                         class="po-product-photo"
                                         loading="lazy"
                                         referrerpolicy="no-referrer"
                                         @if(!empty($item->photo_fallback_url)) data-fallback="{{ $item->photo_fallback_url }}" @endif
                                         onerror="if(this.dataset.fallback){this.src=this.dataset.fallback;delete this.dataset.fallback;}else{this.outerHTML='<div class=&quot;po-product-photo po-product-photo--empty&quot;><span class=&quot;po-missing-badge no-print&quot;>Missing</span><span class=&quot;po-missing-print-dash&quot;—</span></div>';}" />
                                @else
                                    <div class="po-product-photo po-product-photo--empty">{!! $poMissing !!}</div>
                                @endif
                                <div class="po-product-meta" style="text-align:center;">
                                    <span class="po-product-label">5Core SKU</span>
                                    <span class="po-product-sku po-barcode-sku">
                                        @if($displaySku !== '')
                                            {{ $displaySku }}
                                        @else
                                            {!! $poMissing !!}
                                        @endif
                                    </span>
                                </div>
                                <div class="po-barcode-wrap">
                                    @if(!empty($item->barcode_url))
                                        <img src="{{ $item->barcode_url }}"
                                             alt="Barcode {{ $item->barcode_code ?? $displaySku }}"
                                             class="po-barcode-img"
                                             loading="lazy"
                                             referrerpolicy="no-referrer" />
                                    @else
                                        <div class="no-print">{!! $poMissing !!}</div>
                                    @endif
                                    <div class="po-barcode-code">
                                        @if(trim((string) ($item->barcode_code ?? '')) !== '')
                                            {{ $item->barcode_code }}
                                        @else
                                            {!! $poMissing !!}
                                        @endif
                                    </div>
                                </div>
                                <div class="po-product-meta">
                                    <span class="po-product-label">Supplier SKU</span>
                                    <span class="po-product-supplier po-editable"
                                          data-field="supplier_sku"
                                          data-item-index="{{ $i }}">
                                        <span class="po-field-text">
                                            @if(trim((string) ($item->supplier_sku ?? '')) !== '')
                                                {{ $item->supplier_sku }}
                                            @else
                                                {!! $poMissing !!}
                                            @endif
                                        </span>
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td class="col-short-name po-editable" data-field="short_name">
                            <span class="po-field-text">
                                @if(trim((string) ($item->short_name ?? '')) !== '')
                                    {{ $item->short_name }}
                                @else
                                    {!! $poMissing !!}
                                @endif
                            </span>
                        </td>
                        <td class="wrap-text col-tech">
                            <div class="po-tech-block">
                                <div class="po-tech-text po-editable" data-field="tech" data-raw="{{ base64_encode((string) ($item->tech ?? '')) }}">
                                    <span class="po-field-text">
                                        @if(trim((string) ($item->tech ?? '')) !== '')
                                            {!! nl2br(e($item->tech)) !!}
                                        @else
                                            {!! $poMissing !!}
                                        @endif
                                    </span>
                                </div>
                                <div class="po-dims-cell">
                                    <div class="po-dims-row">
                                        <span class="po-dims-label">NW (kg)</span>
                                        <span class="po-dims-value po-editable" data-field="nw">
                                            <span class="po-field-text">
                                                @if(trim((string) ($item->nw ?? '')) !== '')
                                                    {{ $item->nw }}
                                                @else
                                                    {!! $poMissing !!}
                                                @endif
                                            </span>
                                        </span>
                                    </div>
                                    <div class="po-dims-row">
                                        <span class="po-dims-label">GW (kg)</span>
                                        <span class="po-dims-value po-editable" data-field="gw">
                                            <span class="po-field-text">
                                                @if(trim((string) ($item->gw ?? '')) !== '')
                                                    {{ $item->gw }}
                                                @else
                                                    {!! $poMissing !!}
                                                @endif
                                            </span>
                                        </span>
                                    </div>
                                    <div class="po-dims-row">
                                        <span class="po-dims-label">CBM</span>
                                        <span class="po-dims-value po-editable" data-field="cbm">
                                            <span class="po-field-text">
                                                @if(trim((string) ($item->cbm ?? '')) !== '')
                                                    {{ $item->cbm }}
                                                @else
                                                    {!! $poMissing !!}
                                                @endif
                                            </span>
                                        </span>
                                    </div>
                                </div>
                            </div>
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
                                            {!! $poMissing !!}
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
                                            {!! $poMissing !!}
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
                                            {!! $poMissing !!}
                                        @endif
                                    </span>
                                </div>
                                <div class="po-pkg-combined-row">
                                    <span class="po-pkg-combined-label">Ctn Pkg</span>
                                    <span class="po-pkg-combined-value po-ctn-pkg-text">
                                        @if($ctnPkg !== '')
                                            {!! nl2br(e($ctnPkg)) !!}
                                        @else
                                            {!! $poMissing !!}
                                        @endif
                                    </span>
                                </div>
                                <div class="po-pkg-combined-row">
                                    <span class="po-pkg-combined-label">Ctn Qty</span>
                                    <span class="po-pkg-combined-value po-ctn-qty-text">
                                        @if($ctnQty !== '' && $ctnQty !== null)
                                            {{ $ctnQty }}
                                        @else
                                            {!! $poMissing !!}
                                        @endif
                                    </span>
                                </div>
                                <div class="po-pkg-combined-row">
                                    <span class="po-pkg-combined-label">Ctn Print File</span>
                                    <span class="po-pkg-combined-value po-ctn-print-text">
                                        @if($ctnPrintFile !== '')
                                            <span class="po-pkg-combined-link">{{ basename(parse_url($ctnPrintFile, PHP_URL_PATH) ?: $ctnPrintFile) }}</span>
                                        @else
                                            {!! $poMissing !!}
                                        @endif
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
                                    <span class="po-special-qc-empty">{!! $poMissing !!}</span>
                                @endif
                            </div>
                        </td>
                        <td class="col-claim">
                            @php
                                $claimLines = is_array($item->claim_lines ?? null) ? $item->claim_lines : [];
                            @endphp
                            @if(count($claimLines) > 0)
                                <div class="po-claim-list">
                                    @foreach($claimLines as $cl)
                                        <div class="po-claim-card">
                                            <div>
                                                <a class="po-claim-num"
                                                   href="{{ url('/claim-reimbursement') }}"
                                                   target="_blank"
                                                   rel="noopener"
                                                   title="Open Claim &amp; Reimbursement">{{ $cl['claim_number'] ?? 'Claim' }}</a>
                                                @if(!empty($cl['claim_date']))
                                                    <span class="po-claim-meta"> · {{ $cl['claim_date'] }}</span>
                                                @endif
                                            </div>
                                            <div class="po-claim-meta">
                                                Qty {{ $cl['qty'] !== '' && $cl['qty'] !== null ? $cl['qty'] : '—' }}
                                                · Amt {{ $cl['amount'] !== '' && $cl['amount'] !== null ? $cl['amount'] : '—' }}
                                            </div>
                                            @if(trim((string) ($cl['reason'] ?? '')) !== '')
                                                <div class="po-claim-reason">{{ $cl['reason'] }}</div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                {!! $poMissing !!}
                            @endif
                        </td>
                        <td class="col-qc no-print">
                            @php
                                $qcSku = $item->product_master_sku ?? $item->sku ?? '';
                                $qcHasData = !empty($item->qc_has_issues);
                            @endphp
                            <button type="button"
                                    class="po-qc-btn {{ $qcHasData ? 'po-qc-btn--has-data' : 'po-qc-btn--empty' }}"
                                    data-sku="{{ $qcSku }}"
                                    data-has-qc="{{ $qcHasData ? '1' : '0' }}"
                                    title="{{ $qcHasData ? 'QC issues found — click to view' : 'No QC data — click to view' }}"
                                    aria-label="{{ $qcHasData ? 'QC issues found' : 'No QC data' }}">
                                <svg class="po-qc-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <circle cx="10.5" cy="10.5" r="6.5" fill="none" stroke="currentColor" stroke-width="2.4"/>
                                    <path d="M15.5 15.5 L21 21" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
                                </svg>
                            </button>
                        </td>
                        <td class="col-qty po-editable" data-field="qty">
                            <span class="po-field-text">
                                @if(trim((string) ($item->qty ?? '')) !== '')
                                    {{ $item->qty }}
                                @else
                                    {!! $poMissing !!}
                                @endif
                            </span>
                        </td>
                        @php
                            $rateNotLowest = !empty($item->rate_not_lowest);
                            $cmpSource = strtolower((string) ($item->comparison_price_source ?? ''));
                            $rateTitleParts = [];
                            if ($item->cp !== null) {
                                $rateTitleParts[] = 'CP$ (product-master): '.$item->cp;
                            }
                            if ($rateNotLowest) {
                                $lowestHint = $item->comparison_lowest_usd !== null
                                    ? ' (lowest ~$'.$item->comparison_lowest_usd.')'
                                    : '';
                                $rateTitleParts[] = 'Relevant supplier is not lowest on comparison sheet'.$lowestHint;
                            } elseif ($cmpSource !== '') {
                                $rateTitleParts[] = 'Rate from comparison ('.strtoupper($cmpSource).')';
                            }
                            $rateTitle = $rateTitleParts !== [] ? implode(' · ', $rateTitleParts) : '';
                        @endphp
                        <td class="col-price-usd po-editable{{ $rateNotLowest ? ' po-rate-not-lowest' : '' }}"
                            data-field="price_usd"
                            data-currency-source="{{ $curr }}"
                            data-raw="{{ $curr === 'USD' ? $price : ($priceUsd !== null ? $priceUsd : '') }}"
                            data-cp="{{ $item->cp !== null ? $item->cp : '' }}"
                            data-rate-not-lowest="{{ $rateNotLowest ? '1' : '0' }}"
                            @if($rateTitle !== '') title="{{ $rateTitle }}" @endif>
                            <span class="po-rate-cell">
                                <span class="po-field-text">
                                    @if($priceUsd !== null)
                                        {{ rtrim(rtrim(number_format($priceUsd, 2, '.', ''), '0'), '.') . '$' }}
                                    @else
                                        {!! $poMissing !!}
                                    @endif
                                </span>
                                @if($priceUsd !== null && $item->cp !== null)
                                    @php
                                        $rateCmp = round((float) $priceUsd, 2);
                                        $cpCmp = round((float) $item->cp, 2);
                                    @endphp
                                    @if($rateCmp > $cpCmp)
                                        <span class="po-rate-cp-icon po-rate-cp-icon--high no-print"
                                              title="Rate {{ $rateCmp }} > CP {{ $cpCmp }}"
                                              aria-label="Rate higher than CP"></span>
                                    @elseif(abs($rateCmp - $cpCmp) < 0.005)
                                        <span class="po-rate-cp-icon po-rate-cp-icon--same no-print"
                                              title="Rate {{ $rateCmp }} = CP {{ $cpCmp }}"
                                              aria-label="Rate same as CP"></span>
                                    @else
                                        <span class="po-rate-cp-icon po-rate-cp-icon--low no-print"
                                              title="Rate {{ $rateCmp }} &lt; CP {{ $cpCmp }}"
                                              aria-label="Rate lower than CP"></span>
                                    @endif
                                @endif
                            </span>
                        </td>
                        @if($showRmbColumns)
                            <td class="col-price-rmb po-editable"
                                data-field="price_rmb"
                                data-currency-source="{{ $curr }}"
                                data-raw="{{ $curr === 'RMB' ? $price : '' }}">
                                <span class="po-field-text">
                                    @if($priceRmb !== null)
                                        {{ rtrim(rtrim(number_format($priceRmb, 2, '.', ''), '0'), '.') . '¥' }}
                                    @else
                                        {!! $poMissing !!}
                                    @endif
                                </span>
                            </td>
                        @endif
                        <td class="col-total-usd">
                            @if($totalUsd !== null)
                                {{ number_format(round($totalUsd), 0) . '$' }}
                            @else
                                {!! $poMissing !!}
                            @endif
                        </td>
                        @if($showRmbColumns)
                            <td class="col-total-rmb">
                                @if($totalRmb !== null)
                                    {{ number_format(round($totalRmb), 0) . '¥' }}
                                @else
                                    {!! $poMissing !!}
                                @endif
                            </td>
                        @endif
                        <td class="col-edit no-print">
                            <div class="po-line-actions">
                                <button type="button"
                                        class="po-edit-btn"
                                        data-item-index="{{ $i }}"
                                        data-currency="{{ strtoupper($item->currency ?? 'USD') }}"
                                        title="Edit line item"
                                        aria-label="Edit line item">
                                    <i class="mdi mdi-pencil" aria-hidden="true"></i>
                                </button>
                                <button type="button"
                                        class="po-delete-btn"
                                        data-item-index="{{ $i }}"
                                        title="Delete row"
                                        aria-label="Delete row">
                                    ×
                                </button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="{{ $showRmbColumns ? 10 : 9 }}" class="text-end">Grand Total</td>
                    <td>{{ $hasUsdTotal ? number_format(round($subtotalUsd), 0) . '$' : '—' }}</td>
                    @if($showRmbColumns)
                        <td>{{ $hasRmbTotal ? number_format(round($subtotalRmb), 0) . '¥' : '—' }}</td>
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
                <div class="totals-box" id="po-totals-box">
                    @php
                        $primaryCurr = isset($grandTotals['USD']) ? 'USD' : (array_key_first($grandTotals) ?: 'USD');
                        $primaryTotal = (float) ($grandTotals[$primaryCurr] ?? 0);
                        $poAdvancePercent = null;
                        if (isset($order->advance_percent) && $order->advance_percent !== null && $order->advance_percent !== '') {
                            $poAdvancePercent = (float) $order->advance_percent;
                        } elseif (isset($supplierDefaultAdvancePercent) && $supplierDefaultAdvancePercent !== null) {
                            $poAdvancePercent = (float) $supplierDefaultAdvancePercent;
                        }
                        $poAdvanceAmount = (float) ($order->advance_amount ?? 0);
                        if ($poAdvancePercent !== null && $primaryTotal > 0) {
                            $poAdvanceAmount = round($primaryTotal * ($poAdvancePercent / 100));
                        }
                        $primarySymbol = ($primaryCurr === 'RMB' || $primaryCurr === 'CNY') ? '¥' : '$';
                    @endphp
                    @foreach($grandTotals as $curr => $total)
                        @php
                            $currencySymbol = ($curr === 'RMB' || $curr === 'CNY') ? '¥' : '$';
                            $isPrimary = $curr === $primaryCurr;
                        @endphp
                        <div>Subtotal: <span class="float-end" @if($isPrimary) data-po-subtotal="1" data-currency="{{ $curr }}" data-amount="{{ round($total) }}" @endif>{{ $currencySymbol }}{{ number_format(round($total), 0) }}</span></div>
                        @if($isPrimary)
                            <div class="po-advance-row mt-1">
                                <span class="po-advance-label">
                                    Advance:
                                    <input type="number"
                                           id="po-advance-percent"
                                           class="po-advance-percent-input no-print"
                                           min="0"
                                           max="100"
                                           step="0.01"
                                           value="{{ $poAdvancePercent !== null ? rtrim(rtrim(number_format($poAdvancePercent, 2, '.', ''), '0'), '.') : '' }}"
                                           placeholder="%"
                                           title="Advance % of Grand Total"
                                           aria-label="Advance percent">
                                    <span class="po-advance-percent-suffix no-print">%</span>
                                    <span class="po-advance-percent-print" id="po-advance-percent-print">
                                        @if($poAdvancePercent !== null)
                                            {{ rtrim(rtrim(number_format($poAdvancePercent, 2, '.', ''), '0'), '.') }}%
                                        @else
                                            —
                                        @endif
                                    </span>
                                    <span class="no-print" id="po-advance-percent-missing" @if($poAdvancePercent !== null) style="display:none" @endif>{!! $poMissing !!}</span>
                                </span>
                                <span class="po-advance-amount float-end"
                                      id="po-advance-amount"
                                      data-currency-symbol="{{ $primarySymbol }}">{{ $primarySymbol }}{{ number_format(round($poAdvanceAmount), 0) }}</span>
                            </div>
                            <div class="po-advance-save-hint no-print" id="po-advance-save-hint"></div>
                            <div>Balance Due: <span class="float-end po-balance-due" id="po-balance-due">{{ $primarySymbol }}{{ number_format(round($total - $poAdvanceAmount), 0) }}</span></div>
                        @else
                            <div>Advance: <span class="float-end">{{ $currencySymbol }}{{ number_format(round($order->advance_amount ?? 0), 0) }}</span></div>
                            <div>Balance Due: <span class="float-end">{{ $currencySymbol }}{{ number_format(round($total - ($order->advance_amount ?? 0), 0), 0) }}</span></div>
                        @endif
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

        @php
            $approvalButtons = $approvalButtons ?? [];
        @endphp
        <div class="po-approvals no-print mt-4">
            <h5 class="fw-bold text-primary mb-3">Approved BY</h5>
            <div class="po-approvals-row">
                @foreach ($approvalButtons as $btn)
                    <button type="button"
                            class="po-approval-btn{{ !empty($btn['approved']) ? ' is-approved' : '' }}{{ empty($btn['can_toggle']) ? ' is-locked' : '' }}"
                            data-approval-key="{{ $btn['key'] }}"
                            data-can-toggle="{{ !empty($btn['can_toggle']) ? '1' : '0' }}"
                            data-approved="{{ !empty($btn['approved']) ? '1' : '0' }}"
                            title="{{ !empty($btn['can_toggle'])
                                ? (!empty($btn['approved']) ? 'Click to clear your approval' : 'Click to approve as '.$btn['label'])
                                : 'Only '.$btn['label'].' can approve this' }}">
                        <span class="po-approval-status" aria-hidden="true">
                            @if(!empty($btn['approved']))
                                <i class="mdi mdi-check-circle po-approval-tick"></i>
                            @else
                                <span class="po-approval-dot"></span>
                            @endif
                        </span>
                        <span class="po-approval-label">{{ $btn['label'] }}</span>
                        @if(!empty($btn['approved_at']))
                            <span class="po-approval-meta">{{ \Carbon\Carbon::parse($btn['approved_at'])->format('j M y H:i') }}</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>
        {{-- Print-visible approval summary --}}
        <div class="po-approvals-print only-print mt-4">
            <h5 class="fw-bold text-primary mb-2">Approved BY</h5>
            <div class="po-approvals-print-row">
                @foreach ($approvalButtons as $btn)
                    <div class="po-approvals-print-item">
                        @if(!empty($btn['approved']))
                            <i class="mdi mdi-check-circle po-approval-tick"></i>
                        @else
                            <span class="po-approval-dot"></span>
                        @endif
                        <span>{{ $btn['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
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

    {{-- Add Row modal — all proforma columns --}}
    <div class="modal fade" id="poAddRowModal" tabindex="-1" aria-labelledby="poAddRowModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="poAddRowModalLabel">Add Row</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label for="poAddSku" class="form-label fw-semibold">5Core SKU <span class="text-danger">*</span></label>
                            <select id="poAddSku" class="form-select" style="width:100%"></select>
                        </div>
                        <div class="col-md-6">
                            <label for="poAddSupplierSku" class="form-label fw-semibold">Supplier SKU</label>
                            <input type="text" class="form-control" id="poAddSupplierSku" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label for="poAddShortName" class="form-label fw-semibold">Name</label>
                            <input type="text" class="form-control" id="poAddShortName" maxlength="40" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label for="poAddQty" class="form-label fw-semibold">QTY</label>
                            <input type="number" step="any" min="0" class="form-control" id="poAddQty" autocomplete="off">
                        </div>
                        <div class="col-12">
                            <label for="poAddTech" class="form-label fw-semibold">Tech</label>
                            <textarea class="form-control" id="poAddTech" rows="3" autocomplete="off"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label for="poAddNw" class="form-label fw-semibold">NW (kg)</label>
                            <input type="number" step="any" class="form-control" id="poAddNw" autocomplete="off">
                        </div>
                        <div class="col-md-4">
                            <label for="poAddGw" class="form-label fw-semibold">GW (kg)</label>
                            <input type="number" step="any" class="form-control" id="poAddGw" autocomplete="off">
                        </div>
                        <div class="col-md-4">
                            <label for="poAddCbm" class="form-label fw-semibold">CBM</label>
                            <input type="number" step="any" class="form-control" id="poAddCbm" autocomplete="off">
                        </div>
                        <div class="col-12"><hr class="my-1"></div>
                        <div class="col-md-6">
                            <label for="poAddItemPkg" class="form-label fw-semibold">Item Pkg</label>
                            <input type="text" class="form-control" id="poAddItemPkg" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label for="poAddCover" class="form-label fw-semibold">Itm pkg Cover</label>
                            <input type="text" class="form-control" id="poAddCover" placeholder="Image URL or path" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label for="poAddDesign" class="form-label fw-semibold">Design File</label>
                            <input type="text" class="form-control" id="poAddDesign" placeholder="File URL or path" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label for="poAddCtnPkg" class="form-label fw-semibold">Ctn Pkg</label>
                            <input type="text" class="form-control" id="poAddCtnPkg" maxlength="100" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label for="poAddCtnQty" class="form-label fw-semibold">Ctn Qty</label>
                            <input type="text" class="form-control" id="poAddCtnQty" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label for="poAddCtnPrint" class="form-label fw-semibold">Ctn Print File</label>
                            <input type="text" class="form-control" id="poAddCtnPrint" placeholder="File URL or path" autocomplete="off">
                        </div>
                        <div class="col-12">
                            <label for="poAddSpecialQc" class="form-label fw-semibold">Special Instruction QC</label>
                            <textarea class="form-control" id="poAddSpecialQc" rows="3" placeholder="One point per line" autocomplete="off"></textarea>
                        </div>
                        <div class="col-12"><hr class="my-1"></div>
                        <div class="col-md-6">
                            <label for="poAddPriceUsd" class="form-label fw-semibold">Rate $</label>
                            <input type="number" step="any" min="0" class="form-control" id="poAddPriceUsd" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label for="poAddPriceRmb" class="form-label fw-semibold">Rate ¥</label>
                            <input type="number" step="any" min="0" class="form-control" id="poAddPriceRmb" autocomplete="off">
                            <div class="form-text">If Rate ¥ is entered, the line is stored as RMB.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="poAddRowSaveBtn">Add Row</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Bank details edit (saves to supplier.list / supplier_bank_accounts) --}}
    <div class="modal fade" id="poBankModal" tabindex="-1" aria-labelledby="poBankModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="poBankModalLabel">Add Bank Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-text mb-2">Saved to the same supplier bank data as /supplier.list (max 30 chars per field).</div>
                    <form id="poBankForm" class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold mb-0">Supplier name</label>
                            <input type="text" name="supplier_name" maxlength="30" class="form-control form-control-sm"
                                   value="{{ $supplier->name ?? '' }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold mb-0">Nick name</label>
                            <input type="text" name="nick_name" maxlength="30" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold mb-0">Beneficiary</label>
                            <input type="text" name="company_name" maxlength="30" class="form-control form-control-sm"
                                   value="{{ $supplier->company ?? '' }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold mb-0">Swift</label>
                            <input type="text" name="swift" maxlength="30" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold mb-0">Account number</label>
                            <input type="text" name="account_number" maxlength="30" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold mb-0">Address</label>
                            <input type="text" name="address" maxlength="30" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold mb-0">City</label>
                            <input type="text" name="city" maxlength="30" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold mb-0">Province</label>
                            <input type="text" name="province" maxlength="30" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold mb-0">Country</label>
                            <input type="text" name="country" maxlength="30" class="form-control form-control-sm">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="poBankSaveBtn">Save</button>
                </div>
            </div>
        </div>
    </div>

    {{-- QC & Packing issues (SKU + siblings) — from /customer-care/qc-and-packing --}}
    <div class="modal fade" id="poQcIssuesModal" tabindex="-1" aria-labelledby="poQcIssuesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="poQcIssuesModalLabel">
                        <i class="mdi mdi-magnify me-1"></i> QC &amp; Packing Issues
                        <span id="poQcIssuesModalSku" class="ms-2 fw-normal text-muted"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2 small">
                        <span class="text-muted">Parent:</span>
                        <strong id="poQcIssuesModalParent">—</strong>
                        <span class="text-muted ms-2">Siblings:</span>
                        <span id="poQcIssuesModalSiblings" class="text-break">—</span>
                        <a id="poQcIssuesPageLink" href="{{ url('/customer-care/qc-and-packing') }}" target="_blank" rel="noopener" class="ms-auto btn btn-sm btn-outline-primary">
                            Open QC &amp; Packing page
                        </a>
                    </div>
                    <div id="poQcIssuesModalBody">
                        <div class="text-center py-4 text-muted">Loading…</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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

    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
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
            const poMissingHtml = '<span class="po-missing-badge no-print">Missing</span><span class="po-missing-print-dash">—</span>';
            const displayOrMissing = (v) => {
                const s = String(v ?? '').trim();
                return s ? s : poMissingHtml;
            };
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
                if (textEl?.querySelector('.po-missing-badge, .po-missing-print-dash')) {
                    return '';
                }
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
                if (!isFinite(n)) return poMissingHtml;
                // Totals are rounded whole numbers on the proforma PDF.
                return Math.round(n).toLocaleString(undefined, { maximumFractionDigits: 0 }) + suffix;
            }

            function parseCpAttr(cell) {
                const raw = cell?.getAttribute('data-cp');
                if (raw === null || raw === undefined || String(raw).trim() === '') return null;
                const n = parseFloat(raw);
                return Number.isFinite(n) ? Math.round(n * 100) / 100 : null;
            }

            function rateCpIndicatorHtml(rate, cp) {
                if (!Number.isFinite(rate) || cp === null || !Number.isFinite(cp)) return '';
                const r = Math.round(rate * 100) / 100;
                const c = Math.round(cp * 100) / 100;
                let cls = 'po-rate-cp-icon--low';
                let label = 'Rate lower than CP';
                let tip = 'Rate ' + r + ' < CP ' + c;
                if (r > c) {
                    cls = 'po-rate-cp-icon--high';
                    label = 'Rate higher than CP';
                    tip = 'Rate ' + r + ' > CP ' + c;
                } else if (Math.abs(r - c) < 0.005) {
                    cls = 'po-rate-cp-icon--same';
                    label = 'Rate same as CP';
                    tip = 'Rate ' + r + ' = CP ' + c;
                }
                return '<span class="po-rate-cp-icon ' + cls + ' no-print" title="' + tip.replace(/"/g, '&quot;')
                    + '" aria-label="' + label + '"></span>';
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
                    const t = String(text ?? '').trim();
                    if (!t) {
                        cell.innerHTML = '<span class="po-field-text">' + poMissingHtml + '</span>';
                        return;
                    }
                    if (asHtml) {
                        cell.innerHTML = '<span class="po-field-text">' + escapeHtml(t).replace(/\n/g, '<br>') + '</span>';
                    } else {
                        cell.innerHTML = '<span class="po-field-text">' + escapeHtml(t) + '</span>';
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
                    const cp = parseCpAttr(usdCell);
                    const rateText = isFinite(usdN) ? (usdN.toFixed(2).replace(/\.?0+$/, '') + '$') : poMissingHtml;
                    usdCell.innerHTML = '<span class="po-rate-cell"><span class="po-field-text">'
                        + rateText
                        + '</span>'
                        + (isFinite(usdN) ? rateCpIndicatorHtml(usdN, cp) : '')
                        + '</span>';
                    if (usdCell.getAttribute('data-rate-not-lowest') === '1') {
                        usdCell.classList.add('po-rate-not-lowest');
                    } else {
                        usdCell.classList.remove('po-rate-not-lowest');
                    }
                }
                const rmbCell = row.querySelector('.po-editable[data-field="price_rmb"]');
                if (rmbCell) {
                    rmbCell.setAttribute('data-raw', rmbVal);
                    rmbCell.setAttribute('data-currency-source', currency || 'USD');
                    rmbCell.innerHTML = '<span class="po-field-text">'
                        + (isFinite(rmbN) ? (rmbN.toFixed(2).replace(/\.?0+$/, '') + '¥') : poMissingHtml)
                        + '</span>';
                }

                const totalUsdCell = row.querySelector('.col-total-usd');
                const totalRmbCell = row.querySelector('.col-total-rmb');
                if (totalUsdCell) {
                    totalUsdCell.innerHTML = isFinite(usdN) ? formatMoneyDisplay(qtyN * usdN, '$') : poMissingHtml;
                }
                if (totalRmbCell) {
                    totalRmbCell.innerHTML = isFinite(rmbN) ? formatMoneyDisplay(qtyN * rmbN, '¥') : poMissingHtml;
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
                    { field: 'short_name', type: 'text' },
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

            // Add Row (+) / ++ / delete
            const addItemUrl = @json(!empty($order->id) ? route('purchase-order.add-item', $order->id) : '');
            const addFromToOrderUrl = @json(!empty($order->id) ? route('purchase-order.add-from-to-order', $order->id) : '');
            const deleteItemUrl = @json(!empty($order->id) ? route('purchase-order.delete-item', $order->id) : '');
            const qcIssuesUrl = @json(route('purchase-order.qc-issues'));
            const toggleApprovalUrl = @json(!empty($order->id) ? route('purchase-order.toggle-approval', $order->id) : '');
            const updateAdvanceUrl = @json(!empty($order->id) ? route('purchase-order.update-advance', $order->id) : '');
            const shortNameBySkuUrl = @json(route('purchase-order.short-name-by-sku'));
            const skuSearchUrl = @json(url('/purchase/search-sku'));
            const poSupplierId = @json((int) ($order->supplier_id ?? ($supplier->id ?? 0)));
            const canEditPoBank = @json((bool) ($canEditPoBank ?? false));
            const poBankStoreUrl = poSupplierId > 0
                ? @json(url('/supplier')) + '/' + poSupplierId + '/bank-accounts'
                : '';

            document.getElementById('poBankEditBtn')?.addEventListener('click', () => {
                if (!canEditPoBank) {
                    alert('Only purchase@5core.com can add bank details.');
                    return;
                }
                if (!poBankStoreUrl) {
                    alert('Supplier missing on this purchase contract.');
                    return;
                }
                bootstrap.Modal.getOrCreateInstance(document.getElementById('poBankModal')).show();
            });

            document.getElementById('poBankSaveBtn')?.addEventListener('click', async () => {
                if (!canEditPoBank || !poBankStoreUrl) {
                    alert('Only purchase@5core.com can save bank details.');
                    return;
                }
                const form = document.getElementById('poBankForm');
                if (!form) return;
                const payload = {};
                form.querySelectorAll('input[name]').forEach((el) => {
                    payload[el.name] = String(el.value || '').trim();
                });
                const btn = document.getElementById('poBankSaveBtn');
                if (btn) {
                    btn.disabled = true;
                    btn.textContent = 'Saving…';
                }
                try {
                    const res = await fetch(poBankStoreUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify(payload),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || data.success === false) {
                        throw new Error(data.message || 'Failed to save bank details');
                    }
                    window.location.reload();
                } catch (err) {
                    alert(err.message || 'Failed to save bank details');
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = 'Save';
                    }
                }
            });

            // Advance % → amount = Grand Total * %; save to PO + supplier_advances
            (function initPoAdvance() {
                const percentInput = document.getElementById('po-advance-percent');
                const amountEl = document.getElementById('po-advance-amount');
                const balanceEl = document.getElementById('po-balance-due');
                const hintEl = document.getElementById('po-advance-save-hint');
                const printEl = document.getElementById('po-advance-percent-print');
                const missingEl = document.getElementById('po-advance-percent-missing');
                const subtotalEl = document.querySelector('[data-po-subtotal="1"]');
                if (!percentInput || !amountEl || !subtotalEl) return;

                const symbol = amountEl.getAttribute('data-currency-symbol') || '$';
                const currency = subtotalEl.getAttribute('data-currency') || 'USD';
                let saveTimer = null;
                let lastSavedKey = '';

                function formatMoney(n) {
                    return symbol + Math.round(Number(n) || 0).toLocaleString('en-US', { maximumFractionDigits: 0 });
                }

                function grandTotal() {
                    const raw = parseFloat(subtotalEl.getAttribute('data-amount') || '0');
                    return Number.isFinite(raw) ? raw : 0;
                }

                function recalc() {
                    const pctRaw = String(percentInput.value || '').trim();
                    const pct = pctRaw === '' ? null : parseFloat(pctRaw);
                    const total = grandTotal();
                    const amount = (pct !== null && Number.isFinite(pct))
                        ? Math.round(total * (pct / 100))
                        : 0;
                    amountEl.textContent = formatMoney(amount);
                    if (balanceEl) {
                        balanceEl.textContent = formatMoney(total - amount);
                    }
                    if (printEl) {
                        printEl.textContent = (pct !== null && Number.isFinite(pct))
                            ? (String(pct).replace(/\.0+$/, '').replace(/(\.\d*?)0+$/, '$1') + '%')
                            : '—';
                    }
                    if (missingEl) {
                        missingEl.style.display = (pct !== null && Number.isFinite(pct)) ? 'none' : '';
                    }
                    return { percent: (pct !== null && Number.isFinite(pct)) ? pct : null, amount, total };
                }

                async function saveAdvance() {
                    if (!updateAdvanceUrl) return;
                    const { percent, amount, total } = recalc();
                    const key = String(percent) + '|' + String(amount) + '|' + String(total);
                    if (key === lastSavedKey) return;
                    if (hintEl) hintEl.textContent = 'Saving…';
                    try {
                        const res = await fetch(updateAdvanceUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                            },
                            body: JSON.stringify({
                                advance_percent: percent,
                                grand_total: total,
                                currency: currency,
                            }),
                        });
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok || data.success === false) {
                            throw new Error(data.message || 'Failed to save advance');
                        }
                        lastSavedKey = key;
                        if (data.advance_amount != null) {
                            amountEl.textContent = formatMoney(data.advance_amount);
                        }
                        if (balanceEl && data.balance_due != null) {
                            balanceEl.textContent = formatMoney(data.balance_due);
                        }
                        if (hintEl) hintEl.textContent = 'Saved';
                        setTimeout(() => { if (hintEl) hintEl.textContent = ''; }, 1500);
                    } catch (err) {
                        if (hintEl) hintEl.textContent = err.message || 'Save failed';
                    }
                }

                function scheduleSave() {
                    recalc();
                    if (saveTimer) clearTimeout(saveTimer);
                    saveTimer = setTimeout(saveAdvance, 450);
                }

                percentInput.addEventListener('input', scheduleSave);
                percentInput.addEventListener('change', scheduleSave);
                recalc();
            })();

            document.querySelectorAll('.po-approval-btn').forEach((btn) => {
                if (!btn || btn.dataset.bound === '1') return;
                btn.dataset.bound = '1';
                btn.addEventListener('click', async () => {
                    if (!toggleApprovalUrl) {
                        alert('Purchase order id missing.');
                        return;
                    }
                    if (btn.getAttribute('data-can-toggle') !== '1') {
                        alert(btn.title || 'Only the named user can approve this button.');
                        return;
                    }
                    const key = btn.getAttribute('data-approval-key') || '';
                    if (!key) return;
                    btn.disabled = true;
                    try {
                        const res = await fetch(toggleApprovalUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                            },
                            body: JSON.stringify({ key }),
                        });
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok || data.success === false) {
                            throw new Error(data.message || 'Failed to update approval');
                        }
                        const approved = !!data.approved;
                        btn.dataset.approved = approved ? '1' : '0';
                        btn.classList.toggle('is-approved', approved);
                        const status = btn.querySelector('.po-approval-status');
                        if (status) {
                            status.innerHTML = approved
                                ? '<i class="mdi mdi-check-circle po-approval-tick"></i>'
                                : '<span class="po-approval-dot"></span>';
                        }
                        let meta = btn.querySelector('.po-approval-meta');
                        if (approved && data.approved_at) {
                            if (!meta) {
                                meta = document.createElement('span');
                                meta.className = 'po-approval-meta';
                                btn.appendChild(meta);
                            }
                            const d = new Date(String(data.approved_at).replace(' ', 'T'));
                            meta.textContent = Number.isNaN(d.getTime())
                                ? String(data.approved_at)
                                : (d.getDate() + ' ' + d.toLocaleString('en', { month: 'short' }).toUpperCase()
                                    + ' ' + String(d.getFullYear()).slice(-2)
                                    + ' ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0'));
                        } else if (meta) {
                            meta.remove();
                        }
                        btn.title = approved
                            ? 'Click to clear your approval'
                            : ('Click to approve as ' + (btn.querySelector('.po-approval-label')?.textContent || key));
                    } catch (err) {
                        alert(err.message || 'Failed to update approval');
                    } finally {
                        btn.disabled = false;
                    }
                });
            });

            function openPoQcIssuesModal(sku) {
                const skuEl = document.getElementById('poQcIssuesModalSku');
                const parentEl = document.getElementById('poQcIssuesModalParent');
                const sibsEl = document.getElementById('poQcIssuesModalSiblings');
                const bodyEl = document.getElementById('poQcIssuesModalBody');
                const linkEl = document.getElementById('poQcIssuesPageLink');
                const modalEl = document.getElementById('poQcIssuesModal');
                if (!modalEl || !bodyEl) return;

                sku = String(sku || '').trim();
                if (skuEl) skuEl.textContent = sku ? '( ' + sku + ' )' : '';
                if (parentEl) parentEl.textContent = '—';
                if (sibsEl) sibsEl.textContent = '—';
                bodyEl.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i></div>';
                if (linkEl) {
                    linkEl.href = @json(url('/customer-care/qc-and-packing'))
                        + (sku ? ('?sku=' + encodeURIComponent(sku)) : '');
                }
                bootstrap.Modal.getOrCreateInstance(modalEl).show();

                if (!sku) {
                    bodyEl.innerHTML = '<div class="text-muted p-2">SKU missing.</div>';
                    return;
                }

                const esc = (s) => String(s ?? '')
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
                const cell = (v) => {
                    const s = String(v ?? '').trim();
                    return s ? esc(s) : poMissingHtml;
                };

                fetch(qcIssuesUrl + '?sku=' + encodeURIComponent(sku), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                })
                    .then((res) => res.json())
                    .then((data) => {
                        if (parentEl) parentEl.textContent = data.parent || '—';
                        const siblings = Array.isArray(data.siblings) ? data.siblings : [];
                        if (sibsEl) {
                            sibsEl.textContent = siblings.length ? siblings.join(', ') : sku;
                        }
                        const issues = Array.isArray(data.issues) ? data.issues : [];
                        if (!issues.length) {
                            bodyEl.innerHTML = '<div class="text-muted fst-italic p-2">No QC / packing issues for this SKU or its siblings.</div>';
                            return;
                        }
                        const rows = issues.map((it) => {
                            const isFocus = String(it.sku || '').trim().toUpperCase() === sku.toUpperCase();
                            return '<tr' + (isFocus ? ' class="table-warning"' : '') + '>'
                                + '<td>' + cell(it.sku) + '</td>'
                                + '<td>' + cell(it.parent) + '</td>'
                                + '<td class="text-center">' + cell(it.qty) + '</td>'
                                + '<td class="text-center">' + cell(it.order_qty) + '</td>'
                                + '<td>' + cell(it.marketplace_1) + '</td>'
                                + '<td>' + cell(it.what_happened) + '</td>'
                                + '<td>' + cell(it.issue) + '</td>'
                                + '<td>' + cell(it.issue_remark) + '</td>'
                                + '<td>' + cell(it.action_1) + '</td>'
                                + '<td>' + cell(it.action_1_remark) + '</td>'
                                + '<td>' + cell(it.c_action_1) + '</td>'
                                + '<td>' + cell(it.c_action_1_remark) + '</td>'
                                + '<td>' + cell(it.replacement_tracking) + '</td>'
                                + '<td>' + cell(it.department) + '</td>'
                                + '<td>' + cell(it.created_by) + '</td>'
                                + '<td>' + cell(it.created_at_display || it.created_at) + '</td>'
                                + '</tr>';
                        }).join('');
                        bodyEl.innerHTML = '<div class="table-responsive"><table class="table table-sm table-bordered align-middle mb-0" style="font-size:12px;">'
                            + '<thead class="table-light"><tr>'
                            + '<th>SKU</th><th>Parent</th><th>Qty</th><th>Order Qty</th><th>Mkt</th>'
                            + '<th>What happened</th><th>Issue</th><th>Issue remark</th>'
                            + '<th>Action</th><th>Action remark</th>'
                            + '<th>RC Fixed</th><th>RC Fixed remark</th>'
                            + '<th>Replacement</th><th>Dept</th><th>By</th><th>Created</th>'
                            + '</tr></thead><tbody>' + rows + '</tbody></table></div>'
                            + '<div class="form-text mt-2">Highlighted row = current PO SKU. Data from /customer-care/qc-and-packing (SKU + siblings).</div>';
                    })
                    .catch(() => {
                        bodyEl.innerHTML = '<div class="text-danger p-2">Failed to load QC issues.</div>';
                    });
            }

            document.querySelectorAll('.po-qc-btn').forEach((btn) => {
                if (!btn || btn.dataset.bound === '1') return;
                btn.dataset.bound = '1';
                btn.addEventListener('click', () => openPoQcIssuesModal(btn.getAttribute('data-sku') || ''));
            });

            function bindDeleteButton(btn) {
                if (!btn || btn.dataset.bound === '1') return;
                btn.dataset.bound = '1';
                btn.addEventListener('click', async () => {
                    if (!deleteItemUrl) {
                        alert('Purchase order id missing.');
                        return;
                    }
                    const index = btn.getAttribute('data-item-index');
                    if (index === null || index === '') return;
                    if (!confirm('Delete this row?')) return;
                    btn.disabled = true;
                    try {
                        const res = await fetch(deleteItemUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                            },
                            body: JSON.stringify({ item_index: parseInt(index, 10) }),
                        });
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok || data.success === false) {
                            throw new Error(data.message || 'Failed to delete row');
                        }
                        window.location.reload();
                    } catch (err) {
                        alert(err.message || 'Failed to delete row');
                        btn.disabled = false;
                    }
                });
            }

            document.querySelectorAll('.po-delete-btn').forEach(bindDeleteButton);
            const addRowModalEl = document.getElementById('poAddRowModal');
            const addRowModal = addRowModalEl ? bootstrap.Modal.getOrCreateInstance(addRowModalEl) : null;
            let addSkuSelect2Ready = false;

            function resetAddRowForm() {
                const ids = [
                    'poAddSupplierSku', 'poAddShortName', 'poAddQty', 'poAddTech',
                    'poAddNw', 'poAddGw', 'poAddCbm', 'poAddItemPkg', 'poAddCover',
                    'poAddDesign', 'poAddCtnPkg', 'poAddCtnQty', 'poAddCtnPrint',
                    'poAddSpecialQc', 'poAddPriceUsd', 'poAddPriceRmb',
                ];
                ids.forEach((id) => {
                    const el = document.getElementById(id);
                    if (el) el.value = '';
                });
                if (window.jQuery && $('#poAddSku').length) {
                    $('#poAddSku').val(null).trigger('change');
                }
            }

            function initAddSkuSelect2() {
                if (!window.jQuery || addSkuSelect2Ready) return;
                const $sku = $('#poAddSku');
                if (!$sku.length) return;
                $sku.select2({
                    dropdownParent: $('#poAddRowModal'),
                    width: '100%',
                    placeholder: 'Search 5Core SKU…',
                    allowClear: true,
                    ajax: {
                        url: skuSearchUrl,
                        dataType: 'json',
                        delay: 250,
                        data: function (params) {
                            return { q: params.term || '', page: params.page || 1 };
                        },
                        processResults: function (data, params) {
                            params.page = params.page || 1;
                            const results = data.results || data.items || [];
                            return {
                                results: results,
                                pagination: { more: !!(data.pagination && data.pagination.more) },
                            };
                        },
                    },
                });
                $sku.off('select2:select.poShortName').on('select2:select.poShortName', async function (e) {
                    const selectedSku = (e.params?.data?.id || '').toString().trim();
                    const shortInput = document.getElementById('poAddShortName');
                    if (!selectedSku || !shortInput || !shortNameBySkuUrl) return;
                    // Only autofill when the field is empty so manual edits are kept.
                    if ((shortInput.value || '').trim() !== '') return;
                    try {
                        const res = await fetch(shortNameBySkuUrl + '?sku=' + encodeURIComponent(selectedSku), {
                            headers: { 'Accept': 'application/json' },
                        });
                        const data = await res.json().catch(() => ({}));
                        if (res.ok && data.short_name) {
                            shortInput.value = String(data.short_name);
                        }
                    } catch (err) {
                        // ignore autofill errors
                    }
                });
                addSkuSelect2Ready = true;
            }

            document.getElementById('poAddRowBtn')?.addEventListener('click', () => {
                if (!addItemUrl) {
                    alert('Purchase order id missing.');
                    return;
                }
                resetAddRowForm();
                initAddSkuSelect2();
                addRowModal?.show();
            });

            document.getElementById('poAddFromToOrderBtn')?.addEventListener('click', async () => {
                if (!addFromToOrderUrl) {
                    alert('Purchase order id missing.');
                    return;
                }
                if (!confirm('Add SKUs currently shown on the Order page for this contract’s supplier?\nOnly rows with Order qty > 0.\nQuantity = Order column qty.\nExisting SKUs on this PO are skipped.')) {
                    return;
                }
                const btn = document.getElementById('poAddFromToOrderBtn');
                const prev = btn ? btn.textContent : '++';
                if (btn) {
                    btn.disabled = true;
                    btn.textContent = '…';
                }
                try {
                    const res = await fetch(addFromToOrderUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({}),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || data.success === false) {
                        throw new Error(data.message || 'Failed to add SKUs from to-order-analysis');
                    }
                    alert(data.message || 'SKUs added.');
                    window.location.reload();
                } catch (err) {
                    alert(err.message || 'Failed to add SKUs from to-order-analysis');
                } finally {
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = prev || '++';
                    }
                }
            });

            document.getElementById('poAddRowSaveBtn')?.addEventListener('click', async () => {
                if (!addItemUrl) return;
                const sku = (window.jQuery ? ($('#poAddSku').val() || '') : '').toString().trim();
                if (!sku) {
                    alert('Please select a 5Core SKU.');
                    return;
                }

                const numOrNull = (id) => {
                    const v = (document.getElementById(id)?.value || '').trim();
                    return v === '' ? null : v;
                };
                const textVal = (id) => (document.getElementById(id)?.value || '').trim();

                const payload = {
                    sku: sku,
                    supplier_sku: textVal('poAddSupplierSku'),
                    short_name: textVal('poAddShortName'),
                    tech: textVal('poAddTech'),
                    nw: numOrNull('poAddNw'),
                    gw: numOrNull('poAddGw'),
                    cbm: numOrNull('poAddCbm'),
                    qty: numOrNull('poAddQty') ?? 0,
                    price_usd: numOrNull('poAddPriceUsd'),
                    price_rmb: numOrNull('poAddPriceRmb'),
                    item_pkg: textVal('poAddItemPkg'),
                    ctn_pkg: textVal('poAddCtnPkg').slice(0, 100),
                    item_pkg_cover: textVal('poAddCover'),
                    design_file: textVal('poAddDesign'),
                    ctn_qty: textVal('poAddCtnQty'),
                    ctn_print_file: textVal('poAddCtnPrint'),
                    special_instruction_qc: textVal('poAddSpecialQc'),
                };

                const saveBtn = document.getElementById('poAddRowSaveBtn');
                saveBtn.disabled = true;
                saveBtn.textContent = 'Adding…';
                try {
                    const res = await fetch(addItemUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify(payload),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || data.success === false) {
                        throw new Error(data.message || 'Failed to add row');
                    }
                    addRowModal?.hide();
                    window.location.reload();
                } catch (err) {
                    alert(err.message || 'Failed to add row');
                } finally {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Add Row';
                }
            });

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
                    cell.innerHTML = '<span class="po-special-qc-empty">' + poMissingHtml + '</span>';
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
                el.innerHTML = t ? escapeHtml(t).replace(/\n/g, '<br>') : poMissingHtml;
            }

            function renderFileValueHtml(url) {
                const u = (url || '').trim();
                if (!u) return poMissingHtml;
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
                    qtyEl.innerHTML = q !== '' ? escapeHtml(q) : poMissingHtml;
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
                special_qc: '.col-special-qc',
                claim: '.col-claim',
                qty: '.col-qty',
                price_usd: '.col-price-usd',
                price_rmb: '.col-price-rmb',
                total_usd: '.col-total-usd',
                total_rmb: '.col-total-rmb',
            };
            const copyColLabels = {
                product: 'Product',
                short_name: 'Name',
                tech: 'Tech',
                packaging: 'Packaging',
                special_qc: 'Special Instruction QC',
                claim: 'Claim & Reimb.',
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
                clone.querySelectorAll('button, .po-copy-col-btn, .po-edit-btn, .po-delete-btn, .po-line-actions, .po-rate-cp-icon, input, textarea, img').forEach((el) => el.remove());
                const input = cell.querySelector('.po-line-input, .po-supplier-sku-input');
                if (input) {
                    return normalizeCopyText(input.value);
                }
                const fieldText = cell.querySelector('.po-field-text');
                if (fieldText && cell.classList.contains('col-price-usd')) {
                    return normalizeCopyText(fieldText.textContent);
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
                if (cell.classList.contains('col-tech') || cell.querySelector('.po-tech-block')) {
                    const techEl = cell.querySelector('.po-editable[data-field="tech"]');
                    const techInput = techEl?.querySelector('.po-line-input');
                    const techText = techInput
                        ? normalizeCopyText(techInput.value)
                        : normalizeCopyText(techEl?.textContent || '');
                    const dimsParts = [];
                    cell.querySelectorAll('.po-dims-row').forEach((rowEl) => {
                        const label = normalizeCopyText(rowEl.querySelector('.po-dims-label')?.textContent || '');
                        const valueEl = rowEl.querySelector('.po-dims-value');
                        const valueInput = valueEl?.querySelector('.po-line-input');
                        const value = valueInput
                            ? normalizeCopyText(valueInput.value)
                            : normalizeCopyText(valueEl?.textContent || '');
                        dimsParts.push(label ? (label + ': ' + value) : value);
                    });
                    return [techText, dimsParts.filter(Boolean).join('\n')].filter(Boolean).join('\n');
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
