@extends('layouts.vertical', ['title' => 'Compliance Masters', 'mode' => $mode ?? '', 'demo' => $demo ?? '', 'sidenav' => 'condensed', 'skipHighcharts' => true])

@section('css')
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">

    <style>
        /* Aliexpress-tabulator style: full-height table area */
        #compliance-table-wrapper {
            height: calc(100vh - 128px);
            min-height: 280px;
            display: flex;
            flex-direction: column;
            background: #fff;
        }

        #compliance-tabulator.cm-tabulator-host {
            flex: 1;
            min-height: 0;
            width: 100%;
            border-top: 1px solid #dee2e6;
        }

        #compliance-tabulator .tabulator {
            font-size: 11px;
            width: 100% !important;
            max-width: 100%;
            border: 1px solid #d1d5db;
        }

        #compliance-tabulator .tabulator-tableholder {
            overflow-x: auto;
        }

        #compliance-tabulator .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }

        /* AliExpress tabulator: dense neutral header + vertical titles */
        #compliance-tabulator .tabulator-header {
            background: #e8ecf1;
            color: #1e293b;
            font-weight: 600;
            border-bottom: 1px solid #cbd5e1;
        }

        /* Horizontal titles with the filter control above the name */
        #compliance-tabulator .tabulator-header .tabulator-col {
            border-right: 1px solid #cbd5e1;
            height: auto !important;
            min-height: 72px !important;
        }

        #compliance-tabulator .tabulator-header .tabulator-col-content {
            padding: 4px 3px 6px;
            height: 100%;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            justify-content: flex-start;
            gap: 4px;
        }

        #compliance-tabulator .tabulator-header .tabulator-col .tabulator-col-title {
            writing-mode: horizontal-tb !important;
            text-orientation: mixed;
            white-space: normal;
            transform: none !important;
            height: auto !important;
            display: block;
            text-align: center;
            font-size: 11px;
            font-weight: 600;
            line-height: 1.2;
            color: #334155;
            padding: 0;
            margin: 0;
            order: 2;
        }

        #compliance-tabulator .tabulator-header,
        #compliance-tabulator .tabulator-headers,
        #compliance-tabulator .tabulator-header .tabulator-col,
        #compliance-tabulator .tabulator-header .tabulator-col-content,
        #compliance-tabulator .tabulator-header-filter {
            overflow: visible !important;
        }

        #compliance-tabulator .tabulator-header .tabulator-header-filter {
            order: -1;
            width: 100%;
            padding: 0 0 4px;
            position: relative;
            overflow: visible !important;
            display: block !important;
            visibility: visible !important;
            height: auto !important;
            min-height: 24px;
        }

        #compliance-tabulator .tabulator-header .tabulator-col-title-holder {
            order: 2;
            width: 100%;
        }

        #compliance-tabulator .tabulator-header-filter .cm-filter-cell {
            width: 100%;
            min-width: 0;
            padding: 0;
        }

        #compliance-tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0 !important;
        }

        #compliance-tabulator .tabulator-cell.cm-compliance-field-col {
            min-width: 0;
            padding-left: 2px;
            padding-right: 2px;
        }

        #compliance-tabulator .compliance-thumb-wrap img,
        #compliance-tabulator .compliance-thumb-img {
            max-width: 32px;
            max-height: 32px;
            width: auto;
            height: auto;
            object-fit: cover;
        }

        #compliance-tabulator .tabulator-row .tabulator-cell {
            padding: 3px 5px;
            vertical-align: middle;
            border-bottom: 1px solid #e5e7eb;
            border-right: 1px solid #f1f5f9;
        }

        #compliance-tabulator .tabulator-row .tabulator-cell:last-child {
            border-right: none;
        }

        #compliance-tabulator .compliance-na-badge,
        #compliance-tabulator .compliance-req-badge {
            font-size: 11px;
            padding: 0.1rem 0.35rem;
            font-weight: 600;
            line-height: 1.2;
        }

        #compliance-tabulator .tabulator-row .tabulator-cell {
            font-size: 11px;
            line-height: 1.25;
        }

        #compliance-tabulator .compliance-pdf-icon-bg {
            width: 22px;
            height: 22px;
            font-size: 11px;
            border-radius: 4px;
        }

        #compliance-tabulator .edit-btn {
            padding: 1px 5px;
            border-radius: 3px;
            line-height: 1.2;
        }

        #compliance-tabulator .edit-btn:hover {
            transform: none;
            box-shadow: none;
        }

        #compliance-tabulator .tabulator-row.tabulator-row-even .tabulator-cell {
            background-color: #fafafa;
        }

        #compliance-tabulator .tabulator-row:hover .tabulator-cell {
            background-color: #f1f5f9;
        }

        .cm-filter-cell .form-label {
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 10px;
            line-height: 1.2;
        }

        .cm-filter-cell .form-control-sm,
        .cm-filter-cell .form-select-sm {
            font-size: 10px;
            padding-top: 0.15rem;
            padding-bottom: 0.15rem;
            padding-left: 0.25rem;
            padding-right: 0.25rem;
            width: 100%;
            min-width: 0;
        }

        .cm-filter-cell .cm-field-filter {
            font-weight: 600;
            transition: background-color 0.12s ease, color 0.12s ease, border-color 0.12s ease;
        }

        .cm-filter-cell .cm-field-filter.cm-filter-req {
            background-color: #dc3545 !important;
            border-color: #dc3545 !important;
            color: #fff !important;
        }

        .cm-filter-cell .cm-field-filter.cm-filter-na {
            background-color: #0d6efd !important;
            border-color: #0d6efd !important;
            color: #fff !important;
        }

        .cm-filter-cell .cm-field-filter option {
            background-color: #fff;
            color: #212529;
            font-weight: 600;
        }

        .cm-filter-cell .cm-field-filter option.cm-opt-req,
        .cm-filter-cell .cm-field-filter option[value="req"] {
            background-color: #dc3545 !important;
            color: #fff !important;
        }

        .cm-filter-cell .cm-field-filter option.cm-opt-na,
        .cm-filter-cell .cm-field-filter option[value="na"] {
            background-color: #0d6efd !important;
            color: #fff !important;
        }

        .cm-filter-cell .cm-field-filter option[value="all"] {
            background-color: #fff !important;
            color: #212529 !important;
        }

        .cm-toolbar-row {
            display: flex;
            flex-wrap: nowrap;
            align-items: stretch;
            gap: 8px;
            width: 100%;
            min-width: 0;
        }

        .cm-toolbar-actions {
            display: flex;
            flex: 0 0 auto;
            align-items: stretch;
            gap: 6px;
        }

        .cm-toolbar-actions .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
        }

        #cm-summary-stats {
            flex: 1 1 auto;
            min-width: 0;
            display: flex;
            flex-wrap: nowrap;
            align-items: stretch;
            gap: 4px;
            padding: 0 !important;
            margin: 0;
            background: transparent;
            border: none;
        }

        #cm-summary-stats .cm-summary-badge {
            flex: 1 1 0;
            min-width: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 3.25px;
            font-size: clamp(8.94px, 0.934vw, 17.87px);
            font-weight: 600;
            padding: 0.228rem 0.325rem;
            line-height: 1.2;
            color: #fff !important;
            border: 0;
            cursor: pointer;
            user-select: none;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        #cm-summary-stats .cm-summary-badge .summary-trend-dot {
            width: 8.12px !important;
            height: 8.12px !important;
            min-width: 8.12px !important;
            min-height: 8.12px !important;
            margin-right: 0 !important;
        }

        #cm-summary-stats .cm-summary-badge:hover {
            filter: brightness(0.92);
        }

        #cm-summary-stats .cm-summary-badge.cm-summary-badge-active {
            box-shadow: 0 0 0 2px #fff, 0 0 0 4px #111827;
        }

        #cmKpiChartModal.modal {
            --tz-modal-width: 100%;
            padding-left: 0 !important;
            padding-right: 0 !important;
            z-index: 1080;
        }
        #cmKpiChartModal .modal-dialog {
            width: 100% !important;
            max-width: none !important;
            margin: 0.5rem 0 0 0 !important;
        }

        #compliance-table-wrapper .rainbow-loader {
            flex-shrink: 0;
            padding: 16px;
            background: #fafbfc;
            border-top: 1px solid #dee2e6;
        }

        /* Parent summary rows (SKU or Parent contains PARENT) */
        #compliance-tabulator .tabulator-row.tabulator-com-parent-keyword .tabulator-cell {
            background-color: #fffef2 !important;
        }

        #compliance-tabulator .tabulator-row.tabulator-com-parent-keyword:hover .tabulator-cell {
            background-color: #fefce8 !important;
        }

        #compliance-tabulator .tabulator-row.tabulator-com-parent-keyword:hover {
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.06);
        }

        #compliance-tabulator .tabulator-row.tabulator-com-parent-keyword .tabulator-cell.compliance-parent-col:hover {
            background-color: #fffde7 !important;
        }

        #compliance-tabulator .tabulator-row.tabulator-com-parent-keyword:hover .tabulator-cell.compliance-parent-col:hover {
            background-color: #fffef2 !important;
        }

        .rainbow-loader {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .loading-text {
            margin-top: 10px;
            font-weight: bold;
        }

        .custom-toast {
            z-index: 2000;
            max-width: 400px;
            width: auto;
            min-width: 300px;
            font-size: 16px;
        }
        
        .toast-body {
            padding: 12px 15px;
            word-wrap: break-word;
            white-space: normal;
        }

        .compliance-thumb-wrap {
            display: inline-block;
            line-height: 0;
            cursor: zoom-in;
        }

        .compliance-thumb-wrap img {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 4px;
            vertical-align: middle;
            transition: box-shadow 0.2s ease, transform 0.2s ease;
        }

        .compliance-thumb-wrap:hover img {
            box-shadow: 0 4px 14px rgba(26, 86, 183, 0.35);
            transform: scale(1.05);
        }

        #compliance-img-hover-preview {
            position: fixed;
            z-index: 10050;
            display: none;
            pointer-events: none;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.22);
            border-radius: 8px;
            background: #fff;
            padding: 6px;
            line-height: 0;
        }

        #compliance-img-hover-preview img {
            max-width: min(92vw, 380px);
            max-height: min(85vh, 380px);
            width: auto;
            height: auto;
            object-fit: contain;
            display: block;
            border-radius: 4px;
        }

        /* Parent: shrink with fitColumns; ellipsis until hover expand */
        #compliance-tabulator .tabulator-cell.compliance-parent-col {
            min-width: 0;
            box-sizing: border-box;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap !important;
            text-align: left;
        }

        #compliance-tabulator .tabulator-cell.compliance-parent-col:hover {
            white-space: normal !important;
            word-break: break-word;
            overflow: visible;
            max-width: min(28rem, 78vw);
            width: max-content;
            min-width: 3.5rem;
            position: relative;
            z-index: 25;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.12);
            background-color: #fff;
        }

        #compliance-tabulator .tabulator-row.tabulator-row-even .tabulator-cell.compliance-parent-col:hover {
            background-color: #fafafa;
        }

        #compliance-tabulator .tabulator-row:hover .tabulator-cell.compliance-parent-col:hover {
            background-color: #f1f5f9;
        }

        #compliance-tabulator .tabulator-cell.cm-supplier-col {
            text-align: center;
            font-weight: 700;
        }

        /* Same email presence dots as /supplier.list */
        #compliance-tabulator .cm-supplier-data-dot {
            display: inline-block;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            vertical-align: middle;
            flex-shrink: 0;
        }

        #cm-email-hover-tip {
            position: fixed;
            z-index: 10850;
            display: none;
            align-items: center;
            gap: 8px;
            max-width: min(420px, calc(100vw - 16px));
            padding: 6px 8px;
            background: #0f172a;
            color: #fff;
            border-radius: 6px;
            font-size: 12px;
            line-height: 1.3;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.28);
        }

        #cm-email-hover-tip .cm-email-hover-text {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        #cm-email-hover-tip .cm-copy-supplier-email {
            border: 0;
            background: transparent;
            padding: 0;
            line-height: 1;
            color: #fff;
            cursor: pointer;
            flex-shrink: 0;
        }

        #cm-email-hover-tip .cm-copy-supplier-email:hover {
            color: #86efac;
        }

        #cm-email-hover-tip .cm-copy-supplier-email.is-copied {
            color: #86efac;
        }

        #compliance-tabulator .cm-supplier-data-dot--ok {
            background-color: #198754;
            cursor: pointer;
        }

        #compliance-tabulator .cm-supplier-data-dot--missing {
            background-color: #dc3545;
        }

        #compliance-tabulator .cm-supplier-email-cell {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        #compliance-tabulator .cm-copy-supplier-email {
            border: 0;
            background: transparent;
            padding: 0;
            line-height: 1;
            color: #64748b;
            cursor: pointer;
        }

        #compliance-tabulator .cm-copy-supplier-email:hover {
            color: #0f172a;
        }

        #compliance-tabulator .cm-copy-supplier-email.is-copied {
            color: #16a34a;
        }

        .compliance-field-block .btn-check:checked + .btn-outline-secondary {
            background-color: #6c757d;
            color: #fff;
        }

        .compliance-field-block .btn-check:checked + .btn-outline-primary {
            background-color: #0d6efd;
            color: #fff;
        }

        .compliance-field-thumb {
            width: 36px;
            height: 36px;
            object-fit: cover;
            border-radius: 4px;
            vertical-align: middle;
        }

        /* Right-side compliance editor panel — dense, one-screen */
        #addComplianceModal.offcanvas-end {
            width: min(360px, 100vw);
            border-left: 1px solid rgba(15, 23, 42, 0.08);
        }

        #addComplianceModal .offcanvas-header {
            background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%);
            color: #fff;
            padding: 0.45rem 0.75rem;
        }

        #addComplianceModal .offcanvas-title {
            font-size: 0.85rem;
            font-weight: 600;
        }

        #addComplianceModal .btn-close {
            transform: scale(0.85);
        }

        #addComplianceModal .cm-panel-body {
            display: flex;
            flex-direction: column;
            height: 100%;
            min-height: 0;
            padding: 0;
            overflow: hidden;
        }

        #addComplianceModal .cm-panel-scroll {
            flex: 1 1 auto;
            overflow: hidden;
            padding: 0.45rem 0.55rem 0.25rem;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        #addComplianceModal #addComplianceForm {
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            min-height: 0;
        }

        #addComplianceModal .cm-panel-footer {
            flex: 0 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.4rem;
            padding: 0.4rem 0.55rem;
            border-top: 1px solid #e5e7eb;
            background: #f8fafc;
        }

        #addComplianceModal .cm-sku-block {
            flex: 0 0 auto;
            margin-bottom: 0.35rem;
            padding-bottom: 0.35rem;
            border-bottom: 1px solid #eef2f7;
        }

        #addComplianceModal .cm-sku-block .form-label {
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #64748b;
            margin-bottom: 0.1rem;
        }

        #addComplianceModal .cm-meta-row {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            gap: 0.35rem 0.5rem;
            margin-top: 0.25rem;
            font-size: 0.72rem;
        }

        #addComplianceModal .cm-meta-row .small {
            font-size: 0.68rem;
        }

        #addComplianceModal .cm-fields-list {
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
            flex: 1 1 auto;
            min-height: 0;
            justify-content: space-evenly;
        }

        #addComplianceModal .compliance-field-block {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            background: #fff;
            padding: 0.2rem 0.35rem;
            transition: border-color 0.15s ease, background-color 0.15s ease;
        }

        #addComplianceModal .compliance-field-block.is-req {
            border-color: #fca5a5;
            background: #fef2f2;
        }

        #addComplianceModal .compliance-field-block.is-req.is-complete {
            border-color: #86efac;
            background: #f0fdf4;
        }

        #addComplianceModal .cm-field-row {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        #addComplianceModal .cm-field-label {
            font-size: 0.72rem;
            font-weight: 600;
            color: #0f172a;
            margin: 0;
            min-width: 3.6rem;
            flex: 0 0 3.6rem;
            line-height: 1.1;
        }

        #addComplianceModal .cm-field-row .btn-group {
            flex: 1 1 auto;
        }

        #addComplianceModal .cm-field-row .btn {
            padding: 0.12rem 0.35rem;
            font-size: 0.65rem;
            font-weight: 600;
            line-height: 1.2;
        }

        #addComplianceModal .cm-file-actions {
            display: inline-flex;
            align-items: center;
            gap: 0.2rem;
            flex: 0 0 auto;
        }

        #addComplianceModal .cm-file-btn {
            position: relative;
            width: 26px;
            height: 26px;
            padding: 0;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            background: #fff;
            color: #475569;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            line-height: 1;
            cursor: pointer;
        }

        #addComplianceModal .cm-file-btn:hover {
            border-color: #0d9488;
            color: #0f766e;
            background: #f0fdfa;
        }

        #addComplianceModal .cm-file-btn.has-file {
            border-color: #16a34a;
            color: #15803d;
            background: #dcfce7;
        }

        #addComplianceModal .cm-file-btn.missing-file {
            border-color: #ef4444;
            color: #b91c1c;
            background: #fee2e2;
        }

        #addComplianceModal .cm-file-dot {
            position: absolute;
            top: -2px;
            right: -2px;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #ef4444;
            border: 1px solid #fff;
        }

        #addComplianceModal .cm-file-btn.has-file .cm-file-dot {
            background: #22c55e;
        }

        #addComplianceModal .cm-file-preview-link {
            font-size: 0.62rem;
            max-width: 28px;
            overflow: hidden;
            white-space: nowrap;
        }

        #addComplianceModal .cm-file-preview-link img {
            width: 22px;
            height: 22px;
            object-fit: cover;
            border-radius: 3px;
            display: block;
        }

        #addComplianceModal #cm_autosave_status {
            font-size: 0.65rem;
            white-space: nowrap;
        }

        @media (max-height: 720px) {
            #addComplianceModal .cm-field-label {
                min-width: 3.2rem;
                flex-basis: 3.2rem;
                font-size: 0.68rem;
            }
            #addComplianceModal .cm-field-row .btn {
                padding: 0.08rem 0.28rem;
                font-size: 0.6rem;
            }
            #addComplianceModal .cm-file-btn {
                width: 22px;
                height: 22px;
                font-size: 0.62rem;
            }
            #addComplianceModal .compliance-field-block {
                padding: 0.12rem 0.28rem;
            }
            #addComplianceModal .cm-fields-list {
                gap: 0.12rem;
            }
        }

        .compliance-na-badge {
            background-color: #0d6efd !important;
            color: #fff !important;
            font-weight: 600;
            border: none;
        }

        .compliance-nrq-badge {
            background-color: #0d6efd !important;
            color: #fff !important;
            font-weight: 600;
            border: none;
        }

        .compliance-req-badge {
            background-color: #dc3545 !important;
            color: #fff !important;
            font-weight: 600;
        }

        .compliance-req-badge.compliance-req-ok {
            background-color: #198754 !important;
            color: #fff !important;
        }

        .compliance-pdf-link {
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            vertical-align: middle;
        }

        .compliance-pdf-icon-bg {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 6px;
            background-color: #22c55e;
            color: #fff;
            font-size: 15px;
            line-height: 1;
            transition: background-color 0.15s ease;
        }

        .compliance-pdf-link:hover .compliance-pdf-icon-bg,
        .cm-file-chip:hover .compliance-pdf-icon-bg,
        .cm-file-chip.is-menu-open .compliance-pdf-icon-bg {
            background-color: #16a34a;
            color: #fff;
        }

        .cm-file-chip {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            vertical-align: middle;
            cursor: pointer;
        }

        .cm-file-chip .compliance-field-thumb {
            width: 28px;
            height: 28px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
        }

        #cm-file-action-menu {
            position: fixed;
            z-index: 10060;
            display: none;
            min-width: 118px;
            padding: 4px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.18);
        }

        #cm-file-action-menu.is-open {
            display: block;
        }

        #cm-file-action-menu button {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            border: 0;
            background: transparent;
            color: #0f172a;
            font-size: 11px;
            font-weight: 600;
            padding: 6px 8px;
            border-radius: 6px;
            cursor: pointer;
            text-align: left;
        }

        #cm-file-action-menu button:hover {
            background: #f1f5f9;
            color: #0f766e;
        }

        #cm-file-action-menu button i {
            width: 14px;
            text-align: center;
            color: #64748b;
        }

        #cm-file-action-menu button:hover i {
            color: #0f766e;
        }

        #compliance-tabulator .tabulator-cell.cm-compliance-field-col {
            overflow: visible;
        }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $__cmFields = [
            'battery' => 'Battery',
            'wireless' => 'Wireless',
            'electric' => 'Electric',
            'gcc' => 'GCC',
            'rohs' => 'RoHs',
            'blanket' => 'Blanket',
            'bluetooth' => 'Bluetooth',
            'logo' => 'Logo',
            'graph' => 'Graph',
        ];
        $__cmFilterIds = [
            'battery' => 'filterBattery',
            'wireless' => 'filterWireless',
            'electric' => 'filterElectric',
            'gcc' => 'filterGcc',
            'rohs' => 'filterRohs',
            'blanket' => 'filterBlanket',
            'bluetooth' => 'filterBluetooth',
            'logo' => 'filterLogo',
            'graph' => 'filterGraph',
        ];
    @endphp

    @include('layouts.shared.page-title', [
        'page_title' => 'Compliance Masters',
        'sub_title' => 'Compliance Masters Analysis',
    ])

    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 11000;"></div>

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-2">
                    <div class="cm-toolbar-row">
                        <div class="cm-toolbar-actions">
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#importModal" title="Import Excel" aria-label="Import Excel">
                                <i class="bi bi-upload"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-success" id="downloadExcel" title="Download Excel" aria-label="Download Excel">
                                <i class="bi bi-download"></i>
                            </button>
                        </div>
                        <div id="cm-summary-stats">
                            <button type="button" class="badge bg-success cm-summary-badge" data-cm-filter="any" data-kpi-key="badge:compliance-master|missing_any" data-kpi-label="Compliance-M" data-kpi-value="0" title="Show SKUs with any REQ compliance field"><span class="summary-trend-dot none" role="button" tabindex="0" title="Click for rolling history"></span>Compliance-M <span id="cm-summary-any">(0)</span></button>
                            <button type="button" class="badge bg-success cm-summary-badge" data-cm-filter="battery" data-kpi-key="badge:compliance-master|missing_battery" data-kpi-label="Battery" data-kpi-value="0" title="Show rows with Battery REQ"><span class="summary-trend-dot none" role="button" tabindex="0" title="Click for rolling history"></span>Battery <span id="cm-summary-battery">(0)</span></button>
                            <button type="button" class="badge bg-success cm-summary-badge" data-cm-filter="wireless" data-kpi-key="badge:compliance-master|missing_wireless" data-kpi-label="Wireless" data-kpi-value="0" title="Show rows with Wireless REQ"><span class="summary-trend-dot none" role="button" tabindex="0" title="Click for rolling history"></span>Wireless <span id="cm-summary-wireless">(0)</span></button>
                            <button type="button" class="badge bg-success cm-summary-badge" data-cm-filter="electric" data-kpi-key="badge:compliance-master|missing_electric" data-kpi-label="Electric" data-kpi-value="0" title="Show rows with Electric REQ"><span class="summary-trend-dot none" role="button" tabindex="0" title="Click for rolling history"></span>Electric <span id="cm-summary-electric">(0)</span></button>
                            <button type="button" class="badge bg-success cm-summary-badge" data-cm-filter="gcc" data-kpi-key="badge:compliance-master|missing_gcc" data-kpi-label="GCC" data-kpi-value="0" title="Show rows with GCC REQ"><span class="summary-trend-dot none" role="button" tabindex="0" title="Click for rolling history"></span>GCC <span id="cm-summary-gcc">(0)</span></button>
                            <button type="button" class="badge bg-success cm-summary-badge" data-cm-filter="rohs" data-kpi-key="badge:compliance-master|missing_rohs" data-kpi-label="RoHs" data-kpi-value="0" title="Show rows with RoHs REQ"><span class="summary-trend-dot none" role="button" tabindex="0" title="Click for rolling history"></span>RoHs <span id="cm-summary-rohs">(0)</span></button>
                            <button type="button" class="badge bg-success cm-summary-badge" data-cm-filter="blanket" data-kpi-key="badge:compliance-master|missing_blanket" data-kpi-label="Blanket" data-kpi-value="0" title="Show rows with Blanket REQ"><span class="summary-trend-dot none" role="button" tabindex="0" title="Click for rolling history"></span>Blanket <span id="cm-summary-blanket">(0)</span></button>
                            <button type="button" class="badge bg-success cm-summary-badge" data-cm-filter="bluetooth" data-kpi-key="badge:compliance-master|missing_bluetooth" data-kpi-label="Bluetooth" data-kpi-value="0" title="Show rows with Bluetooth REQ"><span class="summary-trend-dot none" role="button" tabindex="0" title="Click for rolling history"></span>Bluetooth <span id="cm-summary-bluetooth">(0)</span></button>
                            <button type="button" class="badge bg-success cm-summary-badge" data-cm-filter="logo" data-kpi-key="badge:compliance-master|missing_logo" data-kpi-label="Logo" data-kpi-value="0" title="Show rows with Logo REQ"><span class="summary-trend-dot none" role="button" tabindex="0" title="Click for rolling history"></span>Logo <span id="cm-summary-logo">(0)</span></button>
                            <button type="button" class="badge bg-success cm-summary-badge" data-cm-filter="graph" data-kpi-key="badge:compliance-master|missing_graph" data-kpi-label="Graph" data-kpi-value="0" title="Show rows with Graph REQ"><span class="summary-trend-dot none" role="button" tabindex="0" title="Click for rolling history"></span>Graph <span id="cm-summary-graph">(0)</span></button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div id="compliance-table-wrapper">
                        <div id="compliance-tabulator" class="cm-tabulator-host" aria-label="Compliance data grid"></div>

                        <div id="rainbow-loader" class="rainbow-loader">
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="loading-text">Loading Compliance Masters Data...</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade p-0" id="cmKpiChartModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog shadow-none m-0 mx-0">
                    <div class="modal-content" style="overflow:hidden;">
                        <div class="modal-header bg-dark text-white py-1 px-3">
                            <h6 class="modal-title mb-0" style="font-size:13px;">
                                <i class="bi bi-graph-up me-1"></i>
                                <span id="cmKpiChartTitle">KPI — Rolling history</span>
                            </h6>
                            <div class="d-flex align-items-center gap-2">
                                <select id="cmKpiChartRange" class="form-select form-select-sm" style="width:auto;font-size:11px;">
                                    <option value="7">L7</option>
                                    <option value="14">L14</option>
                                    <option value="30" selected>L30</option>
                                    <option value="60">L60</option>
                                    <option value="90">L90</option>
                                </select>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                        </div>
                        <div class="modal-body py-2 px-3">
                            <div class="d-flex justify-content-between align-items-center mb-2 small">
                                <span id="cmKpiChartSub" class="text-muted"></span>
                                <span id="cmKpiChartTone" class="badge bg-secondary">—</span>
                            </div>
                            <div id="cmKpiChartLoading" class="text-center py-3" style="display:none;">
                                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                            </div>
                            <div id="cmKpiChartNoData" class="text-center py-3 text-muted small" style="display:none;">
                                No history yet — dots will color after a few daily snapshots.
                            </div>
                            <div id="cmKpiChartWrap" style="display:none;height:280px;">
                                <canvas id="cmKpiChartCanvas"></canvas>
                            </div>
                            <div class="d-flex justify-content-around small mt-2" id="cmKpiChartStats" style="display:none;">
                                <div class="text-center"><div class="text-muted" style="font-size:10px;">Highest</div><div id="cmKpiHi" class="fw-bold">—</div></div>
                                <div class="text-center"><div class="text-muted" style="font-size:10px;">Median</div><div id="cmKpiMed" class="fw-bold">—</div></div>
                                <div class="text-center"><div class="text-muted" style="font-size:10px;">Lowest</div><div id="cmKpiLo" class="fw-bold">—</div></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Import Modal -->
            <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header" style="background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%); color: white;">
                            <h5 class="modal-title" id="importModalLabel">
                                <i class="bi bi-upload me-2"></i>Import Compliance Data
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Instructions:</strong>
                                <ol class="mb-0 mt-2">
                                    <li>Download the sample file below</li>
                                    <li>Use <strong>N/A</strong> or <strong>REQ</strong> per column. After import, open each SKU to attach the <strong>image</strong> and <strong>PDF</strong> required for REQ fields.</li>
                                    <li>Upload the completed file</li>
                                </ol>
                            </div>

                            <div class="mb-3">
                                <button type="button" class="btn btn-outline-primary w-100" id="downloadSampleBtn">
                                    <i class="bi bi-download me-2"></i>Download Sample File
                                </button>
                            </div>

                            <div class="mb-3">
                                <label for="importFile" class="form-label fw-bold">Select Excel File</label>
                                <input type="file" class="form-control" id="importFile" accept=".xlsx,.xls,.csv">
                                <div class="form-text">Supported formats: .xlsx, .xls, .csv</div>
                                <div id="fileError" class="text-danger mt-2" style="display: none;"></div>
                            </div>

                            <div id="importProgress" class="progress mb-3" style="display: none;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                            </div>

                            <div id="importResult" class="alert" style="display: none;"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" id="importBtn" disabled>
                                <i class="bi bi-upload me-2"></i>Import
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add / Edit Compliance Master — right-side panel -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="addComplianceModal" aria-labelledby="addComplianceModalLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title mb-0" id="addComplianceModalLabel">Edit Compliance Data</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body cm-panel-body">
            <div class="cm-panel-scroll">
                <form id="addComplianceForm">
                    <div class="cm-sku-block">
                        <label for="addComplianceSku" class="form-label">SKU <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="addComplianceSku" name="sku" readonly required>
                        <div class="cm-meta-row">
                            <label class="d-inline-flex align-items-center gap-2 mb-0 small"
                                title="When checked, the same compliance values are saved to all child SKUs under the same parent"
                                style="cursor:pointer;user-select:none;">
                                <input type="checkbox" class="form-check-input m-0" id="cm_apply_siblings" autocomplete="off">
                                <span class="fw-semibold">Siblings</span>
                            </label>
                            <span class="small text-muted" id="cm_siblings_hint"></span>
                            <span class="small text-success d-none ms-auto" id="cm_autosave_status"></span>
                        </div>
                    </div>

                    <div class="cm-fields-list">
                        @foreach ($__cmFields as $fkey => $flabel)
                            <div class="compliance-field-block" data-add-field="{{ $fkey }}">
                                <div class="cm-field-row">
                                    <span class="cm-field-label">{{ $flabel }}</span>
                                    <div class="btn-group btn-group-sm" role="group" aria-label="{{ $flabel }} mode">
                                        <input type="radio" class="btn-check" name="add_mode_{{ $fkey }}" id="add_na_{{ $fkey }}" value="na" checked autocomplete="off">
                                        <label class="btn btn-outline-secondary" for="add_na_{{ $fkey }}">N/A</label>
                                        <input type="radio" class="btn-check" name="add_mode_{{ $fkey }}" id="add_req_{{ $fkey }}" value="req" autocomplete="off">
                                        <label class="btn btn-outline-primary" for="add_req_{{ $fkey }}">REQ</label>
                                    </div>
                                    <div class="cm-file-actions d-none" id="add_{{ $fkey }}_req_wrap">
                                        <input type="file" class="d-none add-compliance-img-input" id="add_{{ $fkey }}_img_file" accept="image/*" data-field="{{ $fkey }}" data-path-input="add_{{ $fkey }}_img_path">
                                        <button type="button" class="cm-file-btn cm-img-btn missing-file" data-trigger-file="add_{{ $fkey }}_img_file" title="Upload image" aria-label="Upload {{ $flabel }} image">
                                            <i class="bi bi-image" aria-hidden="true"></i>
                                            <span class="cm-file-dot" aria-hidden="true"></span>
                                        </button>
                                        <span class="cm-file-preview-link" id="add_{{ $fkey }}_img_preview"></span>

                                        <input type="file" class="d-none add-compliance-pdf-input" id="add_{{ $fkey }}_pdf_file" accept=".pdf,application/pdf" data-field="{{ $fkey }}" data-path-input="add_{{ $fkey }}_pdf_path">
                                        <button type="button" class="cm-file-btn cm-pdf-btn missing-file" data-trigger-file="add_{{ $fkey }}_pdf_file" title="Upload PDF" aria-label="Upload {{ $flabel }} PDF">
                                            <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
                                            <span class="cm-file-dot" aria-hidden="true"></span>
                                        </button>
                                        <span class="cm-file-preview-link" id="add_{{ $fkey }}_pdf_link"></span>

                                        <span class="visually-hidden" id="add_{{ $fkey }}_img_status"></span>
                                        <span class="visually-hidden" id="add_{{ $fkey }}_pdf_status"></span>
                                    </div>
                                </div>
                                <input type="hidden" id="add_{{ $fkey }}_img_path" value="">
                                <input type="hidden" id="add_{{ $fkey }}_pdf_path" value="">
                            </div>
                        @endforeach
                    </div>
                </form>
            </div>
            <div class="cm-panel-footer">
                <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="offcanvas">Close</button>
                <button type="button" class="btn btn-sm btn-primary" id="saveAddComplianceBtn">
                    <i class="bi bi-save me-1"></i> Save
                </button>
            </div>
        </div>
    </div>
@endsection

@section('script')
    @include('partials.lazy-chart-js')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Store the loaded data globally
            let tableData = [];
            let filteredData = [];
            let complianceTable = null;
            let complianceFormMode = 'edit';
            let complianceEditSku = '';
            let complianceFormHydrating = false;
            let complianceAutoSaveTimer = null;
            let complianceAutoSaveInFlight = false;
            let complianceAutoSaveQueued = false;

            const COMPLIANCE_BULK_FIELD_KEYS = ['battery', 'wireless', 'electric', 'gcc', 'rohs', 'blanket', 'bluetooth', 'logo', 'graph'];
            let cmSummaryBadgeFilter = null;

            // Get CSRF token from meta tag
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

            function complianceImagePublicUrl(path) {
                if (path == null || path === '') return '';
                const s = String(path).trim();
                if (s.startsWith('http://') || s.startsWith('https://')) return s;
                return '/' + s.replace(/^\/+/, '');
            }

            function complianceFieldStoredValue(item, key) {
                return item[key] != null ? String(item[key]).trim() : '';
            }

            function complianceFieldImagePath(item, key) {
                const ik = key + '_img';
                return item[ik] != null ? String(item[ik]).trim() : '';
            }

            function complianceFieldPdfPath(item, key) {
                const pk = key + '_pdf';
                return item[pk] != null ? String(item[pk]).trim() : '';
            }

            function isComplianceNaValue(value) {
                const upper = String(value == null ? '' : value).trim().toUpperCase();
                return upper === '' || upper === 'N/A' || upper === 'NA' || upper === 'NRQ';
            }

            function isMissingComplianceFieldForItem(item, key) {
                const v = complianceFieldStoredValue(item, key);
                const img = complianceFieldImagePath(item, key);
                const pdf = complianceFieldPdfPath(item, key);
                if (isComplianceNaValue(v)) return true;
                if (v.toUpperCase() === 'REQ') return img === '' || pdf === '';
                return false;
            }

            function rowHasAnyReqCompliance(item) {
                if (complianceRowHasParentKeyword(item)) return false;
                return COMPLIANCE_BULK_FIELD_KEYS.some(function(k) {
                    return isReqFilterMatchForItem(item, k);
                });
            }

            /** REQ column filter: only rows where that field is REQ (excludes N/A, empty, and other values). */
            function isReqFilterMatchForItem(item, key) {
                return complianceFieldStoredValue(item, key).toUpperCase() === 'REQ';
            }

            function isReqOkFilterMatchForItem(item, key) {
                if (!isReqFilterMatchForItem(item, key)) return false;
                const img = complianceFieldImagePath(item, key);
                const pdf = complianceFieldPdfPath(item, key);
                return img !== '' && pdf !== '';
            }

            function isReqMissingFilterMatchForItem(item, key) {
                if (!isReqFilterMatchForItem(item, key)) return false;
                return !isReqOkFilterMatchForItem(item, key);
            }

            function isNaFilterMatchForItem(item, key) {
                return isComplianceNaValue(complianceFieldStoredValue(item, key));
            }

            function matchesComplianceFieldFilter(item, key, filterValue) {
                if (!filterValue || filterValue === 'all') return true;
                if (filterValue === 'req') return isReqMissingFilterMatchForItem(item, key);
                if (filterValue === 'req-ok') return isReqOkFilterMatchForItem(item, key);
                if (filterValue === 'na') return isNaFilterMatchForItem(item, key);
                return true;
            }

            function syncComplianceFieldFilterStyles() {
                document.querySelectorAll('.cm-field-filter').forEach(function(sel) {
                    sel.classList.remove('cm-filter-req', 'cm-filter-req-ok', 'cm-filter-na');
                    if (sel.value === 'req') sel.classList.add('cm-filter-req');
                    else if (sel.value === 'na') sel.classList.add('cm-filter-na');
                });
            }

            function complianceFieldCellHtml(item, key) {
                const v = complianceFieldStoredValue(item, key);
                const img = complianceFieldImagePath(item, key);
                const pdf = complianceFieldPdfPath(item, key);
                const upper = v.toUpperCase();
                const hasAnyFile = img !== '' || pdf !== '';

                let badge = '';
                if (upper === 'REQ') {
                    // Only show REQ when no files are attached yet
                    if (!hasAnyFile) {
                        badge = '<span class="badge rounded-pill compliance-req-badge" title="REQ — missing image or PDF">REQ</span>';
                    }
                } else if (isComplianceNaValue(v)) {
                    badge = '<span class="badge rounded-pill compliance-na-badge">N/A</span>';
                } else {
                    badge = `<span class="badge rounded-pill bg-info text-dark" title="Legacy value">${escapeHtml(v)}</span>`;
                }
                let thumb = '';
                if (img) {
                    const u = complianceImagePublicUrl(img);
                    thumb = ' ' + complianceFileChipHtml(u, 'image');
                }
                let pdfLink = '';
                if (pdf) {
                    const pu = complianceImagePublicUrl(pdf);
                    pdfLink = ' ' + complianceFileChipHtml(pu, 'pdf');
                }
                return `<span class="d-inline-flex align-items-center gap-1 flex-wrap justify-content-center">${badge}${thumb}${pdfLink}</span>`;
            }

            function complianceFileChipHtml(url, kind) {
                const safeUrl = escapeHtml(String(url || ''));
                const kindSafe = kind === 'pdf' ? 'pdf' : 'image';
                const icon = kindSafe === 'pdf'
                    ? '<span class="compliance-pdf-icon-bg" aria-hidden="true"><i class="bi bi-file-earmark-pdf"></i></span>'
                    : `<img class="compliance-field-thumb" src="${safeUrl}" alt="">`;
                return `<span class="cm-file-chip" data-file-url="${safeUrl}" data-file-kind="${kindSafe}" tabindex="0" title="Hover for file actions">${icon}</span>`;
            }

            async function uploadComplianceFieldImageToServer(field, file) {
                const fd = new FormData();
                fd.append('field', field);
                fd.append('image', file);
                fd.append('_token', csrfToken);
                const res = await fetch('/compliance-master/field-image', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: fd
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || data.success === false) {
                    throw new Error(data.message || 'Upload failed');
                }
                return data.path || '';
            }

            async function uploadComplianceFieldPdfToServer(field, file) {
                const fd = new FormData();
                fd.append('field', field);
                fd.append('pdf', file);
                fd.append('_token', csrfToken);
                const res = await fetch('/compliance-master/field-pdf', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: fd
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || data.success === false) {
                    throw new Error(data.message || 'PDF upload failed');
                }
                return data.path || '';
            }

            function resetComplianceAddFormFields() {
                const modal = document.getElementById('addComplianceModal');
                if (!modal) return;
                COMPLIANCE_BULK_FIELD_KEYS.forEach(k => {
                    const na = document.getElementById(`add_na_${k}`);
                    const req = document.getElementById(`add_req_${k}`);
                    if (na) na.checked = true;
                    if (req) req.checked = false;
                    const pathEl = document.getElementById(`add_${k}_img_path`);
                    if (pathEl) pathEl.value = '';
                    const pdfPathEl = document.getElementById(`add_${k}_pdf_path`);
                    if (pdfPathEl) pdfPathEl.value = '';
                    const st = document.getElementById(`add_${k}_img_status`);
                    if (st) st.textContent = '';
                    const pst = document.getElementById(`add_${k}_pdf_status`);
                    if (pst) pst.textContent = '';
                    const fi = modal.querySelector(`.add-compliance-img-input[data-field="${k}"]`);
                    if (fi) fi.value = '';
                    const fp = modal.querySelector(`.add-compliance-pdf-input[data-field="${k}"]`);
                    if (fp) fp.value = '';
                    refreshComplianceFieldFileUi(k);
                });
            }

            function setAddComplianceFormFromItem(item) {
                COMPLIANCE_BULK_FIELD_KEYS.forEach(k => {
                    const raw = complianceFieldStoredValue(item, k);
                    const upper = raw.toUpperCase();
                    const isReq = upper === 'REQ';
                    document.getElementById(`add_na_${k}`).checked = !isReq;
                    document.getElementById(`add_req_${k}`).checked = isReq;
                    const p = complianceFieldImagePath(item, k);
                    const pdfp = complianceFieldPdfPath(item, k);
                    const pathEl = document.getElementById(`add_${k}_img_path`);
                    if (pathEl) pathEl.value = p;
                    const pdfPathEl = document.getElementById(`add_${k}_pdf_path`);
                    if (pdfPathEl) pdfPathEl.value = pdfp;
                    const st = document.getElementById(`add_${k}_img_status`);
                    if (st) st.textContent = p ? 'Current image on file.' : '';
                    const pst = document.getElementById(`add_${k}_pdf_status`);
                    if (pst) pst.textContent = pdfp ? 'Current PDF on file.' : '';
                    refreshComplianceFieldFileUi(k);
                });
            }

            function refreshComplianceFieldFileUi(field) {
                const isReq = !!document.getElementById(`add_req_${field}`)?.checked;
                const pathEl = document.getElementById(`add_${field}_img_path`);
                const pdfPathEl = document.getElementById(`add_${field}_pdf_path`);
                const imgPath = pathEl ? pathEl.value.trim() : '';
                const pdfPath = pdfPathEl ? pdfPathEl.value.trim() : '';
                const block = document.querySelector(`#addComplianceModal .compliance-field-block[data-add-field="${field}"]`);
                const wrap = document.getElementById(`add_${field}_req_wrap`);
                const imgBtn = wrap ? wrap.querySelector('.cm-img-btn') : null;
                const pdfBtn = wrap ? wrap.querySelector('.cm-pdf-btn') : null;
                const prev = document.getElementById(`add_${field}_img_preview`);
                const plink = document.getElementById(`add_${field}_pdf_link`);

                if (wrap) wrap.classList.toggle('d-none', !isReq);
                if (block) {
                    block.classList.toggle('is-req', isReq);
                    block.classList.toggle('is-complete', isReq && imgPath !== '' && pdfPath !== '');
                }

                if (imgBtn) {
                    imgBtn.classList.toggle('has-file', imgPath !== '');
                    imgBtn.classList.toggle('missing-file', isReq && imgPath === '');
                    imgBtn.title = imgPath ? 'Replace image' : 'Upload image';
                }
                if (pdfBtn) {
                    pdfBtn.classList.toggle('has-file', pdfPath !== '');
                    pdfBtn.classList.toggle('missing-file', isReq && pdfPath === '');
                    pdfBtn.title = pdfPath ? 'Replace PDF' : 'Upload PDF';
                }

                if (prev) {
                    if (imgPath) {
                        const u = complianceImagePublicUrl(imgPath);
                        prev.innerHTML = `<a href="${escapeHtml(u)}" target="_blank" rel="noopener" title="Open image"><img src="${escapeHtml(u)}" alt=""></a>`;
                    } else {
                        prev.innerHTML = '';
                    }
                }
                if (plink) {
                    if (pdfPath) {
                        const pu = complianceImagePublicUrl(pdfPath);
                        plink.innerHTML = `<a href="${escapeHtml(pu)}" target="_blank" rel="noopener" title="Open PDF"><i class="bi bi-box-arrow-up-right"></i></a>`;
                    } else {
                        plink.innerHTML = '';
                    }
                }
            }

            function toggleAddComplianceReqWrap(field) {
                refreshComplianceFieldFileUi(field);
            }

            function collectComplianceFormPayload(sku) {
                const o = { sku: String(sku || '').trim() };
                COMPLIANCE_BULK_FIELD_KEYS.forEach(k => {
                    const req = document.querySelector(`#addComplianceModal input[name="add_mode_${k}"]:checked`)?.value === 'req';
                    const pathEl = document.getElementById(`add_${k}_img_path`);
                    const imgPath = pathEl ? pathEl.value.trim() : '';
                    const pdfPathEl = document.getElementById(`add_${k}_pdf_path`);
                    const pdfPath = pdfPathEl ? pdfPathEl.value.trim() : '';
                    if (req) {
                        o[k] = 'REQ';
                        o[k + '_img'] = imgPath;
                        o[k + '_pdf'] = pdfPath;
                    } else {
                        o[k] = 'N/A';
                        o[k + '_img'] = '';
                        o[k + '_pdf'] = '';
                    }
                });
                const siblingsCb = document.getElementById('cm_apply_siblings');
                o.apply_siblings = !!(siblingsCb && siblingsCb.checked);
                return o;
            }

            function getComplianceSiblingSkusForSku(sku) {
                const row = findComplianceRowBySku(sku);
                const parent = String(row?.Parent || '').trim();
                if (!parent) return [];
                const seen = new Set();
                const out = [];
                (Array.isArray(tableData) ? tableData : []).forEach(item => {
                    if (!item) return;
                    const p = String(item.Parent || '').trim();
                    const s = String(item.SKU || '').trim();
                    if (!s || p !== parent) return;
                    if (s.toUpperCase().includes('PARENT')) return;
                    const key = s.toUpperCase();
                    if (seen.has(key)) return;
                    seen.add(key);
                    out.push(s);
                });
                return out;
            }

            function updateComplianceSiblingsHint() {
                const hint = document.getElementById('cm_siblings_hint');
                const cb = document.getElementById('cm_apply_siblings');
                if (!hint) return;

                const skuInput = document.getElementById('addComplianceSku');
                let sku = (complianceEditSku || '').trim();
                if (!sku && skuInput) sku = String(skuInput.value || '').trim();

                if (!cb || !cb.checked) {
                    hint.textContent = '';
                    return;
                }
                if (!sku) {
                    hint.textContent = 'Select a SKU first';
                    return;
                }
                const siblings = getComplianceSiblingSkusForSku(sku);
                const otherCount = Math.max(0, siblings.length - 1);
                if (otherCount > 0) {
                    hint.textContent = 'Will also update ' + otherCount + ' sibling SKU(s)';
                } else {
                    hint.textContent = 'No sibling children found for this parent';
                }
            }

            function setComplianceAutosaveStatus(text, isError) {
                const el = document.getElementById('cm_autosave_status');
                if (!el) return;
                if (!text) {
                    el.classList.add('d-none');
                    el.textContent = '';
                    return;
                }
                el.classList.remove('d-none', 'text-success', 'text-danger', 'text-muted');
                el.classList.add(isError ? 'text-danger' : 'text-success');
                el.textContent = text;
            }

            function scheduleComplianceAutosave() {
                if (complianceFormHydrating) return;
                if (complianceAutoSaveTimer) clearTimeout(complianceAutoSaveTimer);
                complianceAutoSaveTimer = setTimeout(function() {
                    complianceAutoSaveTimer = null;
                    saveCompliance({ auto: true, keepOpen: true });
                }, 350);
            }

            function patchLocalComplianceRowsFromPayload(formData, siblingSkus) {
                const targets = new Set();
                const primary = String(formData.sku || '').trim().toUpperCase();
                if (primary) targets.add(primary);
                (Array.isArray(siblingSkus) ? siblingSkus : []).forEach(s => {
                    const k = String(s || '').trim().toUpperCase();
                    if (k) targets.add(k);
                });
                if (targets.size === 0) return;

                const applyTo = (rows) => {
                    if (!Array.isArray(rows)) return;
                    rows.forEach(item => {
                        const skuKey = String(item?.SKU || '').trim().toUpperCase();
                        if (!targets.has(skuKey)) return;
                        COMPLIANCE_BULK_FIELD_KEYS.forEach(k => {
                            if (Object.prototype.hasOwnProperty.call(formData, k)) {
                                item[k] = formData[k];
                            }
                            const ik = k + '_img';
                            const pk = k + '_pdf';
                            if (Object.prototype.hasOwnProperty.call(formData, ik)) item[ik] = formData[ik];
                            if (Object.prototype.hasOwnProperty.call(formData, pk)) item[pk] = formData[pk];
                        });
                    });
                };
                applyTo(tableData);
                applyTo(filteredData);
                if (complianceTable) {
                    renderTable(filteredData);
                    updateCounts();
                }
            }

            // Show loader immediately
            document.getElementById('rainbow-loader').style.display = 'block';

            // Centralized AJAX request function
            function makeRequest(url, method, data = {}) {
                const headers = {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                };

                if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method.toUpperCase())) {
                    data._token = csrfToken;
                }

                return fetch(url, {
                    method: method,
                    headers: headers,
                    body: method === 'GET' ? null : JSON.stringify(data)
                });
            }

            function loadXlsxLib() {
                if (window.XLSX) return Promise.resolve(window.XLSX);
                if (window._xlsxPromise) return window._xlsxPromise;
                window._xlsxPromise = new Promise(function(resolve, reject) {
                    const s = document.createElement('script');
                    s.src = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
                    s.async = true;
                    s.onload = function() { resolve(window.XLSX); };
                    s.onerror = function() {
                        window._xlsxPromise = null;
                        reject(new Error('Excel library failed to load'));
                    };
                    document.head.appendChild(s);
                });
                return window._xlsxPromise;
            }

            function escapeHtml(text) {
                if (text == null) return '';
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            function copyComplianceText(text, btn) {
                text = String(text || '').trim();
                if (!text) return;
                const done = function() {
                    showToast('success', 'Copied: ' + text);
                    if (!btn) return;
                    btn.classList.add('is-copied');
                    const icon = btn.querySelector('i');
                    if (icon) {
                        icon.classList.remove('bi-copy');
                        icon.classList.add('bi-check-lg');
                        setTimeout(function() {
                            btn.classList.remove('is-copied');
                            icon.classList.remove('bi-check-lg');
                            icon.classList.add('bi-copy');
                        }, 1200);
                    }
                };
                const fallback = function() {
                    const ta = document.createElement('textarea');
                    ta.value = text;
                    ta.setAttribute('readonly', '');
                    ta.style.position = 'fixed';
                    ta.style.top = '0';
                    ta.style.left = '0';
                    ta.style.opacity = '0';
                    document.body.appendChild(ta);
                    ta.focus();
                    ta.select();
                    try {
                        document.execCommand('copy');
                        done();
                    } catch (err) {
                        showToast('danger', 'Could not copy email');
                    }
                    document.body.removeChild(ta);
                };
                if (window.isSecureContext && navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(done).catch(fallback);
                } else {
                    fallback();
                }
            }

            document.addEventListener('click', function(e) {
                const btn = e.target.closest && e.target.closest('.cm-copy-supplier-email');
                if (!btn) return;
                e.preventDefault();
                e.stopPropagation();
                copyComplianceText(btn.getAttribute('data-email') || '', btn);
            });

            function setupComplianceEmailHover() {
                const host = document.getElementById('compliance-tabulator');
                if (!host) return;

                let tip = null;
                let hideTimer = null;

                function getTip() {
                    if (tip) return tip;
                    tip = document.createElement('div');
                    tip.id = 'cm-email-hover-tip';
                    tip.innerHTML = '<span class="cm-email-hover-text"></span>' +
                        '<button type="button" class="cm-copy-supplier-email" title="Copy email">' +
                        '<i class="bi bi-copy"></i></button>';
                    document.body.appendChild(tip);
                    tip.addEventListener('mouseenter', function() {
                        clearTimeout(hideTimer);
                    });
                    tip.addEventListener('mouseleave', scheduleHide);
                    return tip;
                }

                function hideTip() {
                    if (tip) tip.style.display = 'none';
                }

                function scheduleHide() {
                    clearTimeout(hideTimer);
                    hideTimer = setTimeout(hideTip, 180);
                }

                function positionTip(dot) {
                    const el = getTip();
                    const r = dot.getBoundingClientRect();
                    const pad = 8;
                    const gap = 8;
                    requestAnimationFrame(function() {
                        const w = el.offsetWidth || 180;
                        const h = el.offsetHeight || 32;
                        let left = r.left + (r.width / 2) - (w / 2);
                        let top = r.bottom + gap;
                        if (left + w > window.innerWidth - pad) left = Math.max(pad, window.innerWidth - w - pad);
                        if (left < pad) left = pad;
                        if (top + h > window.innerHeight - pad) top = Math.max(pad, r.top - h - gap);
                        el.style.left = left + 'px';
                        el.style.top = top + 'px';
                    });
                }

                function showTip(dot) {
                    const email = String(dot.getAttribute('data-email') || '').trim();
                    if (!email) return;
                    clearTimeout(hideTimer);
                    const el = getTip();
                    el.querySelector('.cm-email-hover-text').textContent = email;
                    const btn = el.querySelector('.cm-copy-supplier-email');
                    btn.setAttribute('data-email', email);
                    btn.setAttribute('title', 'Copy ' + email);
                    el.style.display = 'flex';
                    positionTip(dot);
                }

                host.addEventListener('mouseover', function(e) {
                    const dot = e.target.closest('.cm-supplier-data-dot--ok[data-email]');
                    if (dot && host.contains(dot)) showTip(dot);
                });

                host.addEventListener('mouseout', function(e) {
                    const dot = e.target.closest('.cm-supplier-data-dot--ok[data-email]');
                    if (!dot) return;
                    const next = e.relatedTarget;
                    if (next && (dot.contains(next) || (tip && tip.contains(next)))) return;
                    scheduleHide();
                });

                window.addEventListener('scroll', hideTip, true);
                window.addEventListener('resize', hideTip);
            }

            function setupComplianceImageHoverPreview() {
                const host = document.getElementById('compliance-tabulator');
                const tableScroll = host && host.querySelector('.tabulator-tableholder');
                let previewEl = null;
                let activeWrap = null;

                function getPreview() {
                    if (!previewEl) {
                        previewEl = document.createElement('div');
                        previewEl.id = 'compliance-img-hover-preview';
                        const img = document.createElement('img');
                        previewEl.appendChild(img);
                        document.body.appendChild(previewEl);
                    }
                    return previewEl;
                }

                function hidePreview() {
                    activeWrap = null;
                    if (previewEl) previewEl.style.display = 'none';
                }

                function positionPreview(clientX, clientY) {
                    const el = getPreview();
                    if (el.style.display !== 'block') return;
                    const margin = 14;
                    const pad = 8;
                    requestAnimationFrame(() => {
                        const w = el.offsetWidth || 200;
                        const h = el.offsetHeight || 200;
                        let left = clientX + margin;
                        let top = clientY + margin;
                        if (left + w > window.innerWidth - pad) left = Math.max(pad, window.innerWidth - w - pad);
                        if (top + h > window.innerHeight - pad) top = Math.max(pad, window.innerHeight - h - pad);
                        if (left < pad) left = pad;
                        if (top < pad) top = pad;
                        el.style.left = left + 'px';
                        el.style.top = top + 'px';
                    });
                }

                if (!host) return;

                host.addEventListener('mouseover', function(e) {
                    if (e.target.closest('.cm-file-chip')) {
                        hidePreview();
                        return;
                    }
                    const wrap = e.target.closest('.compliance-thumb-wrap');
                    if (wrap && host.contains(wrap)) {
                        const srcImg = wrap.querySelector('img');
                        if (!srcImg || !srcImg.getAttribute('src')) return;
                        activeWrap = wrap;
                        const el = getPreview();
                        const big = el.querySelector('img');
                        if (big.getAttribute('src') !== srcImg.src) big.src = srcImg.src;
                        el.style.display = 'block';
                        positionPreview(e.clientX, e.clientY);
                    } else {
                        hidePreview();
                    }
                });

                host.addEventListener('mousemove', function(e) {
                    if (!activeWrap) return;
                    if (!activeWrap.contains(e.target)) return;
                    positionPreview(e.clientX, e.clientY);
                });

                host.addEventListener('mouseleave', hidePreview);

                if (tableScroll) {
                    tableScroll.addEventListener('scroll', hidePreview, { passive: true });
                }
            }

            function setupComplianceFileHoverActions() {
                const host = document.getElementById('compliance-tabulator');
                if (!host) return;

                let menuEl = null;
                let activeChip = null;
                let hideTimer = null;

                function getMenu() {
                    if (menuEl) return menuEl;
                    menuEl = document.createElement('div');
                    menuEl.id = 'cm-file-action-menu';
                    menuEl.innerHTML = `
                        <button type="button" data-cm-file-action="copy"><i class="bi bi-copy"></i> Copy</button>
                        <button type="button" data-cm-file-action="download"><i class="bi bi-download"></i> Download</button>
                        <button type="button" data-cm-file-action="view"><i class="bi bi-eye"></i> View</button>
                    `;
                    document.body.appendChild(menuEl);

                    menuEl.addEventListener('mouseenter', function() {
                        if (hideTimer) {
                            clearTimeout(hideTimer);
                            hideTimer = null;
                        }
                    });
                    menuEl.addEventListener('mouseleave', scheduleHideMenu);
                    menuEl.addEventListener('click', async function(e) {
                        const btn = e.target.closest('[data-cm-file-action]');
                        if (!btn || !menuEl.contains(btn)) return;
                        e.preventDefault();
                        e.stopPropagation();
                        const action = btn.getAttribute('data-cm-file-action');
                        const url = menuEl.dataset.fileUrl || '';
                        if (!url) return;
                        await runComplianceFileAction(action, url, menuEl.dataset.fileKind || 'file');
                        hideMenu();
                    });
                    return menuEl;
                }

                function absoluteFileUrl(url) {
                    try {
                        return new URL(url, window.location.origin).href;
                    } catch (err) {
                        return String(url || '');
                    }
                }

                function guessFileName(url, kind) {
                    try {
                        const path = new URL(url, window.location.origin).pathname;
                        const base = path.split('/').filter(Boolean).pop() || '';
                        if (base) return decodeURIComponent(base);
                    } catch (err) {}
                    return kind === 'pdf' ? 'compliance.pdf' : 'compliance-file';
                }

                async function runComplianceFileAction(action, url, kind) {
                    const abs = absoluteFileUrl(url);
                    if (action === 'view') {
                        window.open(abs, '_blank', 'noopener');
                        return;
                    }
                    if (action === 'copy') {
                        try {
                            await navigator.clipboard.writeText(abs);
                            showToast('success', 'Link copied');
                        } catch (err) {
                            showToast('danger', 'Could not copy link');
                        }
                        return;
                    }
                    if (action === 'download') {
                        try {
                            const res = await fetch(abs, { credentials: 'same-origin' });
                            if (!res.ok) throw new Error('Download failed');
                            const blob = await res.blob();
                            const objectUrl = URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.href = objectUrl;
                            a.download = guessFileName(abs, kind);
                            document.body.appendChild(a);
                            a.click();
                            a.remove();
                            URL.revokeObjectURL(objectUrl);
                        } catch (err) {
                            const a = document.createElement('a');
                            a.href = abs;
                            a.target = '_blank';
                            a.rel = 'noopener';
                            a.download = guessFileName(abs, kind);
                            document.body.appendChild(a);
                            a.click();
                            a.remove();
                        }
                    }
                }

                function positionMenu(chip) {
                    const menu = getMenu();
                    const rect = chip.getBoundingClientRect();
                    const pad = 8;
                    menu.style.display = 'block';
                    menu.classList.add('is-open');
                    const mw = menu.offsetWidth || 120;
                    const mh = menu.offsetHeight || 110;
                    let left = rect.left + (rect.width / 2) - (mw / 2);
                    let top = rect.bottom + 6;
                    if (left + mw > window.innerWidth - pad) left = window.innerWidth - mw - pad;
                    if (left < pad) left = pad;
                    if (top + mh > window.innerHeight - pad) top = Math.max(pad, rect.top - mh - 6);
                    menu.style.left = left + 'px';
                    menu.style.top = top + 'px';
                }

                function showMenu(chip) {
                    if (hideTimer) {
                        clearTimeout(hideTimer);
                        hideTimer = null;
                    }
                    if (activeChip && activeChip !== chip) {
                        activeChip.classList.remove('is-menu-open');
                    }
                    activeChip = chip;
                    chip.classList.add('is-menu-open');
                    const menu = getMenu();
                    menu.dataset.fileUrl = chip.getAttribute('data-file-url') || '';
                    menu.dataset.fileKind = chip.getAttribute('data-file-kind') || 'file';
                    positionMenu(chip);
                }

                function hideMenu() {
                    if (hideTimer) {
                        clearTimeout(hideTimer);
                        hideTimer = null;
                    }
                    if (activeChip) activeChip.classList.remove('is-menu-open');
                    activeChip = null;
                    if (menuEl) {
                        menuEl.classList.remove('is-open');
                        menuEl.style.display = 'none';
                    }
                }

                function scheduleHideMenu() {
                    if (hideTimer) clearTimeout(hideTimer);
                    hideTimer = setTimeout(hideMenu, 160);
                }

                host.addEventListener('mouseover', function(e) {
                    const chip = e.target.closest('.cm-file-chip');
                    if (chip && host.contains(chip)) {
                        showMenu(chip);
                    }
                });

                host.addEventListener('mouseout', function(e) {
                    const chip = e.target.closest('.cm-file-chip');
                    if (!chip) return;
                    const to = e.relatedTarget;
                    if (to && (chip.contains(to) || (menuEl && menuEl.contains(to)))) return;
                    scheduleHideMenu();
                });

                host.addEventListener('click', function(e) {
                    const chip = e.target.closest('.cm-file-chip');
                    if (chip && host.contains(chip)) {
                        e.preventDefault();
                        showMenu(chip);
                    }
                });

                document.addEventListener('click', function(e) {
                    if (!menuEl || menuEl.style.display === 'none') return;
                    if (e.target.closest('.cm-file-chip') || e.target.closest('#cm-file-action-menu')) return;
                    hideMenu();
                });

                const tableScroll = host.querySelector('.tabulator-tableholder');
                if (tableScroll) {
                    tableScroll.addEventListener('scroll', hideMenu, { passive: true });
                }
                window.addEventListener('resize', hideMenu);
            }

            // Format number
            function formatNumber(value, decimals = 2) {
                if (value === null || value === undefined || value === '') return '-';
                const num = parseFloat(value);
                if (isNaN(num)) return '-';
                return num.toFixed(decimals);
            }

            // Load compliance data from server
            function loadData() {
                const cacheParam = '?ts=' + new Date().getTime();
                makeRequest('/compliance-master-data-view' + cacheParam, 'GET')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(response => {
                        if (response && response.data && Array.isArray(response.data)) {
                            tableData = response.data;
                            filteredData = [...tableData];
                            renderTable(filteredData);
                            updateCounts();
                        } else {
                            console.error('Invalid data format received from server');
                        }
                        document.getElementById('rainbow-loader').style.display = 'none';
                    })
                    .catch(error => {
                        console.error('Failed to load compliance data: ' + error.message);
                        document.getElementById('rainbow-loader').style.display = 'none';
                    });
            }

            function complianceRowHasParentKeyword(item) {
                const sku = String(item.SKU || '').toUpperCase();
                const par = String(item.Parent || '').toUpperCase();
                return sku.includes('PARENT') || par.includes('PARENT');
            }

            const CM_FIELD_LABELS = {
                battery: 'Battery',
                wireless: 'Wireless',
                electric: 'Electric',
                gcc: 'GCC',
                rohs: 'RoHs',
                blanket: 'Blanket',
                bluetooth: 'Bluetooth',
                logo: 'Logo',
                graph: 'Graph'
            };

            const CM_FILTER_IDS = {
                battery: 'filterBattery',
                wireless: 'filterWireless',
                electric: 'filterElectric',
                gcc: 'filterGcc',
                rohs: 'filterRohs',
                blanket: 'filterBlanket',
                bluetooth: 'filterBluetooth',
                logo: 'filterLogo',
                graph: 'filterGraph'
            };

            function cmPassAllHeaderFilter() {
                return true;
            }

            function cmTitleWithCount(label, countId) {
                return function() {
                    const el = document.createElement('span');
                    el.innerHTML = escapeHtml(label) + ' <span id="' + countId + '" class="text-danger fw-bold">(0)</span>';
                    return el;
                };
            }

            function cmWrapHeaderFilter(el, extraClass) {
                const wrap = document.createElement('div');
                wrap.className = extraClass ? 'cm-filter-cell ' + extraClass : 'cm-filter-cell';
                wrap.appendChild(el);
                return wrap;
            }

            function cmTextHeaderFilter(id, placeholder) {
                return function() {
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.id = id;
                    input.className = 'form-control form-control-sm';
                    input.placeholder = placeholder;
                    input.autocomplete = 'off';
                    input.addEventListener('input', function() {
                        applyFilters();
                    });
                    return cmWrapHeaderFilter(input);
                };
            }

            function cmEmailHeaderFilter() {
                const sel = document.createElement('select');
                sel.id = 'filterSupplierEmail';
                sel.className = 'form-select form-select-sm';
                sel.innerHTML = '<option value="all">All</option><option value="has">Has</option><option value="missing">Missing</option>';
                sel.addEventListener('change', function() {
                    applyFilters();
                });
                return cmWrapHeaderFilter(sel);
            }

            function cmSelectHeaderFilter(id, fieldKey) {
                return function() {
                    const sel = document.createElement('select');
                    sel.id = id;
                    sel.className = 'form-select form-select-sm cm-field-filter';
                    sel.setAttribute('data-field', fieldKey);
                    sel.innerHTML = '<option value="all">All Data</option><option value="req" class="cm-opt-req">REQ</option><option value="na" class="cm-opt-na">N/A</option>';
                    sel.addEventListener('change', function() {
                        syncComplianceFieldFilterStyles();
                        applyFilters();
                    });
                    return cmWrapHeaderFilter(sel);
                };
            }

            function bindComplianceHeaderFilters() {
                syncComplianceFieldFilterStyles();
            }

            function getComplianceTabulatorColumnDefinitions() {
                const cols = [
                    {
                        title: 'Image',
                        field: 'image_path',
                        headerSort: false,
                        width: 48,
                        widthShrink: 1,
                        hozAlign: 'center',
                        formatter: function(cell) {
                            const v = cell.getValue();
                            if (!v) return '-';
                            return '<span class="compliance-thumb-wrap"><img class="compliance-thumb-img" src="' + escapeHtml(String(v)) + '" alt=""></span>';
                        }
                    },
                    {
                        title: 'Parent',
                        field: 'Parent',
                        headerSort: false,
                        cssClass: 'compliance-parent-col',
                        minWidth: 45,
                        widthGrow: 1.6,
                        titleFormatter: cmTitleWithCount('Parent', 'parentCount'),
                        headerFilter: cmTextHeaderFilter('parentSearch', 'Parent'),
                        headerFilterFunc: cmPassAllHeaderFilter,
                        headerFilterLiveFilter: false,
                        formatter: function(cell) {
                            const item = cell.getRow().getData();
                            const raw = item.Parent != null && item.Parent !== '' ? String(item.Parent) : '';
                            if (!raw) return '-';
                            return '<span title="' + escapeHtml(raw) + '">' + escapeHtml(raw) + '</span>';
                        }
                    },
                    {
                        title: 'SKU',
                        field: 'SKU',
                        headerSort: false,
                        minWidth: 56,
                        widthGrow: 2,
                        titleFormatter: cmTitleWithCount('SKU', 'skuCount'),
                        headerFilter: cmTextHeaderFilter('skuSearch', 'SKU'),
                        headerFilterFunc: cmPassAllHeaderFilter,
                        headerFilterLiveFilter: false,
                        formatter: function(cell) {
                            const item = cell.getRow().getData();
                            const v = item.SKU != null && String(item.SKU) !== '' ? String(item.SKU) : '';
                            return v ? escapeHtml(v) : '-';
                        }
                    },
                    {
                        title: 'INV',
                        field: 'shopify_inv',
                        headerSort: false,
                        hozAlign: 'center',
                        width: 48,
                        widthShrink: 1,
                        formatter: function(cell) {
                            const item = cell.getRow().getData();
                            const v = item.shopify_inv;
                            if (v === 0 || v === '0') return '0';
                            if (v === null || v === undefined || v === '') return '-';
                            return escapeHtml(String(v));
                        }
                    },
                    {
                        title: 'Supplier',
                        field: 'supplier',
                        headerSort: false,
                        hozAlign: 'center',
                        cssClass: 'cm-supplier-col',
                        minWidth: 72,
                        widthGrow: 1.4,
                        headerFilter: cmTextHeaderFilter('supplierSearch', 'Supplier'),
                        headerFilterFunc: cmPassAllHeaderFilter,
                        headerFilterLiveFilter: false,
                        formatter: function(cell) {
                            const name = String(cell.getRow().getData().supplier || '').trim();
                            if (!name) return '<span class="text-muted">—</span>';
                            return '<span title="' + escapeHtml(name) + '">' + escapeHtml(name) + '</span>';
                        }
                    },
                    {
                        title: 'Email',
                        field: 'supplier_email',
                        headerSort: false,
                        hozAlign: 'center',
                        width: 64,
                        widthShrink: 1,
                        headerFilter: cmEmailHeaderFilter,
                        headerFilterFunc: cmPassAllHeaderFilter,
                        headerFilterLiveFilter: false,
                        formatter: function(cell) {
                            const email = String(cell.getRow().getData().supplier_email || '').trim();
                            if (!email) {
                                return '<span class="cm-supplier-email-cell"><span class="cm-supplier-data-dot cm-supplier-data-dot--missing" title="No email on file"></span></span>';
                            }
                            return '<span class="cm-supplier-email-cell">' +
                                '<span class="cm-supplier-data-dot cm-supplier-data-dot--ok" data-email="' + escapeHtml(email) + '" aria-label="' + escapeHtml(email) + '"></span>' +
                                '</span>';
                        }
                    }
                ];

                COMPLIANCE_BULK_FIELD_KEYS.forEach(function(fk) {
                    const label = CM_FIELD_LABELS[fk] || fk;
                    cols.push({
                        title: label,
                        field: fk,
                        headerSort: false,
                        hozAlign: 'center',
                        minWidth: 52,
                        widthGrow: 1,
                        cssClass: 'cm-compliance-field-col',
                        titleFormatter: cmTitleWithCount(label, fk + 'MissingCount'),
                        headerFilter: cmSelectHeaderFilter(CM_FILTER_IDS[fk], fk),
                        headerFilterFunc: cmPassAllHeaderFilter,
                        headerFilterLiveFilter: false,
                        formatter: function(cell) {
                            const item = cell.getRow().getData();
                            if (complianceRowHasParentKeyword(item)) {
                                return '<span class="text-muted user-select-none">—</span>';
                            }
                            return complianceFieldCellHtml(item, fk);
                        }
                    });
                });

                cols.push({
                    title: 'Action',
                    field: '_actions',
                    headerSort: false,
                    hozAlign: 'center',
                    width: 44,
                    widthShrink: 1,
                    formatter: function(cell) {
                        const item = cell.getRow().getData();
                        return '<div class="d-inline-flex">' +
                            '<button type="button" class="btn btn-sm btn-outline-warning edit-btn" data-sku="' + escapeHtml(String(item.SKU ?? '')) + '">' +
                            '<i class="bi bi-pencil-square"></i></button></div>';
                    }
                });

                return cols;
            }

            function renderTable(data) {
                const d = Array.isArray(data) ? data : [];
                if (typeof Tabulator === 'undefined') {
                    console.error('Tabulator is not loaded');
                    return;
                }
                if (!complianceTable) {
                    complianceTable = new Tabulator('#compliance-tabulator', {
                        data: d,
                        layout: 'fitColumns',
                        height: '100%',
                        placeholder: 'No compliance data found',
                        movableColumns: false,
                        columnDefaults: {
                            headerSort: false
                        },
                        columns: getComplianceTabulatorColumnDefinitions(),
                        rowFormatter: function(row) {
                            const el = row.getElement();
                            if (complianceRowHasParentKeyword(row.getData())) {
                                el.classList.add('tabulator-com-parent-keyword');
                            } else {
                                el.classList.remove('tabulator-com-parent-keyword');
                            }
                        },
                        tableBuilt: function() {
                            bindComplianceHeaderFilters();
                        }
                    });
                } else {
                    complianceTable.replaceData(d);
                }
            }

            // Update counts
            function updateCounts() {
                const parentSet = new Set();
                let skuCount = 0;
                let batteryMissingCount = 0;
                let wirelessMissingCount = 0;
                let electricMissingCount = 0;
                let gccMissingCount = 0;
                let rohsMissingCount = 0;
                let blanketMissingCount = 0;
                let bluetoothMissingCount = 0;
                let logoMissingCount = 0;
                let graphMissingCount = 0;

                filteredData.forEach(item => {
                    if (item.Parent) parentSet.add(item.Parent);
                    if (item.SKU && !String(item.SKU).toUpperCase().includes('PARENT'))
                        skuCount++;

                    if (isReqFilterMatchForItem(item, 'battery')) batteryMissingCount++;
                    if (isReqFilterMatchForItem(item, 'wireless')) wirelessMissingCount++;
                    if (isReqFilterMatchForItem(item, 'electric')) electricMissingCount++;
                    if (isReqFilterMatchForItem(item, 'gcc')) gccMissingCount++;
                    if (isReqFilterMatchForItem(item, 'rohs')) rohsMissingCount++;
                    if (isReqFilterMatchForItem(item, 'blanket')) blanketMissingCount++;
                    if (isReqFilterMatchForItem(item, 'bluetooth')) bluetoothMissingCount++;
                    if (isReqFilterMatchForItem(item, 'logo')) logoMissingCount++;
                    if (isReqFilterMatchForItem(item, 'graph')) graphMissingCount++;
                });

                const setText = function(id, text) {
                    const el = document.getElementById(id);
                    if (el) el.textContent = text;
                };
                setText('parentCount', `(${parentSet.size})`);
                setText('skuCount', `(${skuCount})`);
                setText('batteryMissingCount', `(${batteryMissingCount})`);
                setText('wirelessMissingCount', `(${wirelessMissingCount})`);
                setText('electricMissingCount', `(${electricMissingCount})`);
                setText('gccMissingCount', `(${gccMissingCount})`);
                setText('rohsMissingCount', `(${rohsMissingCount})`);
                setText('blanketMissingCount', `(${blanketMissingCount})`);
                setText('bluetoothMissingCount', `(${bluetoothMissingCount})`);
                setText('logoMissingCount', `(${logoMissingCount})`);
                setText('graphMissingCount', `(${graphMissingCount})`);

                let summaryAny = 0;
                let summaryBattery = 0;
                let summaryWireless = 0;
                let summaryElectric = 0;
                let summaryGcc = 0;
                let summaryRohs = 0;
                let summaryBlanket = 0;
                let summaryBluetooth = 0;
                let summaryLogo = 0;
                let summaryGraph = 0;
                (Array.isArray(tableData) ? tableData : []).forEach(item => {
                    if (rowHasAnyReqCompliance(item)) summaryAny++;
                    if (isReqFilterMatchForItem(item, 'battery')) summaryBattery++;
                    if (isReqFilterMatchForItem(item, 'wireless')) summaryWireless++;
                    if (isReqFilterMatchForItem(item, 'electric')) summaryElectric++;
                    if (isReqFilterMatchForItem(item, 'gcc')) summaryGcc++;
                    if (isReqFilterMatchForItem(item, 'rohs')) summaryRohs++;
                    if (isReqFilterMatchForItem(item, 'blanket')) summaryBlanket++;
                    if (isReqFilterMatchForItem(item, 'bluetooth')) summaryBluetooth++;
                    if (isReqFilterMatchForItem(item, 'logo')) summaryLogo++;
                    if (isReqFilterMatchForItem(item, 'graph')) summaryGraph++;
                });

                const sp = (id, val) => {
                    const el = document.getElementById(id);
                    if (!el) return;
                    el.textContent = `(${val})`;
                    const badge = el.closest('.cm-summary-badge') || el.closest('.badge');
                    if (!badge) return;
                    badge.setAttribute('data-kpi-value', String(Number(val) || 0));
                    badge.classList.remove(
                        'bg-danger', 'bg-success', 'bg-primary', 'bg-warning',
                        'bg-info', 'bg-secondary', 'bg-dark', 'text-dark'
                    );
                    badge.classList.add(Number(val) === 0 ? 'bg-success' : 'bg-danger');
                };
                sp('cm-summary-any', summaryAny);
                sp('cm-summary-battery', summaryBattery);
                sp('cm-summary-wireless', summaryWireless);
                sp('cm-summary-electric', summaryElectric);
                sp('cm-summary-gcc', summaryGcc);
                sp('cm-summary-rohs', summaryRohs);
                sp('cm-summary-blanket', summaryBlanket);
                sp('cm-summary-bluetooth', summaryBluetooth);
                sp('cm-summary-logo', summaryLogo);
                sp('cm-summary-graph', summaryGraph);
                syncComplianceSidebarBadge(summaryAny);
                persistComplianceKpiSnapshot();
            }

            function syncComplianceSidebarBadge(count) {
                const n = Number(count) || 0;
                document.querySelectorAll('.compliance-missing-sidebar-badge').forEach(function(el) {
                    el.textContent = n.toLocaleString();
                    el.style.display = n > 0 ? '' : 'none';
                });
            }

            function syncSummaryBadgeActiveState() {
                const titles = {
                    any: 'Show SKUs with any REQ compliance field',
                    battery: 'Show rows with Battery REQ',
                    wireless: 'Show rows with Wireless REQ',
                    electric: 'Show rows with Electric REQ',
                    gcc: 'Show rows with GCC REQ',
                    rohs: 'Show rows with RoHs REQ',
                    blanket: 'Show rows with Blanket REQ',
                    bluetooth: 'Show rows with Bluetooth REQ',
                    logo: 'Show rows with Logo REQ',
                    graph: 'Show rows with Graph REQ'
                };
                document.querySelectorAll('#cm-summary-stats .cm-summary-badge').forEach(function(badge) {
                    const key = badge.getAttribute('data-cm-filter');
                    const on = key && key === cmSummaryBadgeFilter;
                    badge.classList.toggle('cm-summary-badge-active', on);
                    badge.setAttribute('aria-pressed', on ? 'true' : 'false');
                    const base = titles[key] || 'Filter table';
                    badge.title = on ? base + ' (click again to clear)' : base;
                });
            }

            function setupSummaryBadgeFilters() {
                const host = document.getElementById('cm-summary-stats');
                if (!host || host.dataset.cmBadgeBound === '1') return;
                host.dataset.cmBadgeBound = '1';
                host.addEventListener('click', function(e) {
                    if (e.target.closest && e.target.closest('.summary-trend-dot')) return;
                    const badge = e.target.closest('.cm-summary-badge');
                    if (!badge || !host.contains(badge)) return;
                    const key = badge.getAttribute('data-cm-filter');
                    if (!key) return;
                    cmSummaryBadgeFilter = (cmSummaryBadgeFilter === key) ? null : key;
                    syncSummaryBadgeActiveState();
                    applyFilters();
                });
                syncSummaryBadgeActiveState();
            }

            const CM_KPI_HISTORY_URL = @json(route('dashboard.kpi.history'));
            const CM_KPI_TONES_URL = @json(route('dashboard.kpi.tones'));
            const CM_KPI_SNAPSHOT_URL = @json(route('compliance.master.badge.snapshot'));
            const CM_KPI_TONE_COLORS = { green: '#22c55e', red: '#ef4444', gray: '#9ca3af' };
            let cmKpiChartInstance = null;
            let cmKpiChartAjax = null;
            let cmKpiSnapshotTimer = null;
            let cmLastKpiSnapshot = '';
            const cmKpiActive = { key: '', label: '', value: null, days: 30 };

            function cmKpiFmt(v) {
                const n = Number(v || 0);
                if (!isFinite(n)) return '—';
                return Math.round(n).toLocaleString('en-US');
            }

            function persistComplianceKpiSnapshot() {
                const payload = {};
                document.querySelectorAll('#cm-summary-stats .cm-summary-badge[data-kpi-key]').forEach(function(badge) {
                    const key = badge.getAttribute('data-kpi-key') || '';
                    const field = key.indexOf('|') >= 0 ? key.split('|').pop() : '';
                    if (!field) return;
                    payload[field] = Number(badge.getAttribute('data-kpi-value') || 0) || 0;
                });
                if (Object.keys(payload).length === 0) return;
                const body = JSON.stringify(payload);
                if (body === cmLastKpiSnapshot) {
                    refreshComplianceKpiDotTones();
                    return;
                }
                clearTimeout(cmKpiSnapshotTimer);
                cmKpiSnapshotTimer = setTimeout(function() {
                    cmLastKpiSnapshot = body;
                    fetch(CM_KPI_SNAPSHOT_URL, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(payload)
                    }).then(function() {
                        refreshComplianceKpiDotTones();
                    }).catch(function() {});
                }, 400);
            }

            function setComplianceKpiDotTone(badge, tone) {
                const t = tone || 'gray';
                const cls = t === 'green' ? 'up' : (t === 'red' ? 'down' : 'none');
                const dot = badge.querySelector('.summary-trend-dot');
                if (!dot) return;
                dot.classList.remove('up', 'down', 'flat', 'none');
                dot.classList.add(cls);
                badge.setAttribute('data-kpi-tone', t);
            }

            function refreshComplianceKpiDotTones() {
                const keys = [];
                document.querySelectorAll('#cm-summary-stats .cm-summary-badge[data-kpi-key]').forEach(function(badge) {
                    const k = badge.getAttribute('data-kpi-key');
                    if (k) keys.push(k);
                });
                if (!keys.length) return;
                fetch(CM_KPI_TONES_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ keys: keys })
                }).then(function(r) { return r.json(); }).then(function(res) {
                    const tones = (res && res.tones) || {};
                    document.querySelectorAll('#cm-summary-stats .cm-summary-badge[data-kpi-key]').forEach(function(badge) {
                        const key = badge.getAttribute('data-kpi-key');
                        const tone = (tones[key] && tones[key].tone) || 'gray';
                        setComplianceKpiDotTone(badge, tone);
                    });
                }).catch(function() {});
            }

            function openComplianceKpiChart(badge) {
                cmKpiActive.key = badge.getAttribute('data-kpi-key') || '';
                cmKpiActive.label = badge.getAttribute('data-kpi-label') || (badge.textContent || '').replace(/\s+/g, ' ').trim();
                const raw = badge.getAttribute('data-kpi-value');
                cmKpiActive.value = (raw !== null && raw !== '' && isFinite(Number(raw))) ? Number(raw) : null;
                const rangeEl = document.getElementById('cmKpiChartRange');
                cmKpiActive.days = parseInt(rangeEl && rangeEl.value, 10) || 30;

                document.getElementById('cmKpiChartTitle').textContent = cmKpiActive.label + ' — Rolling L' + cmKpiActive.days;
                document.getElementById('cmKpiChartSub').textContent = cmKpiActive.key;
                const tone = badge.getAttribute('data-kpi-tone') || 'gray';
                const toneEl = document.getElementById('cmKpiChartTone');
                toneEl.textContent = String(tone).toUpperCase();
                toneEl.style.background = CM_KPI_TONE_COLORS[tone] || CM_KPI_TONE_COLORS.gray;
                toneEl.style.color = '#fff';

                const modalEl = document.getElementById('cmKpiChartModal');
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
                loadComplianceKpiChart();
            }

            function loadComplianceKpiChart() {
                if (!cmKpiActive.key) return;
                const start = function() {
                    if (cmKpiChartAjax) cmKpiChartAjax.abort();
                    document.getElementById('cmKpiChartLoading').style.display = 'block';
                    document.getElementById('cmKpiChartNoData').style.display = 'none';
                    document.getElementById('cmKpiChartWrap').style.display = 'none';
                    document.getElementById('cmKpiChartStats').style.display = 'none';

                    const params = new URLSearchParams({ key: cmKpiActive.key, days: String(cmKpiActive.days) });
                    if (cmKpiActive.value !== null) params.set('badge_value', String(cmKpiActive.value));

                    cmKpiChartAjax = fetch(CM_KPI_HISTORY_URL + '?' + params.toString(), {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin'
                    }).then(function(r) { return r.json(); }).then(function(payload) {
                        cmKpiChartAjax = null;
                        document.getElementById('cmKpiChartLoading').style.display = 'none';
                        if (!payload || !payload.success || !payload.data || !payload.data.length) {
                            document.getElementById('cmKpiChartNoData').style.display = 'block';
                            return;
                        }
                        if (payload.tone) {
                            const toneEl = document.getElementById('cmKpiChartTone');
                            toneEl.textContent = String(payload.tone).toUpperCase();
                            toneEl.style.background = CM_KPI_TONE_COLORS[payload.tone] || CM_KPI_TONE_COLORS.gray;
                        }
                        document.getElementById('cmKpiChartWrap').style.display = 'block';
                        document.getElementById('cmKpiChartStats').style.display = 'flex';
                        renderComplianceKpiChart(payload.data, payload.label || cmKpiActive.label);
                    }).catch(function() {
                        cmKpiChartAjax = null;
                        document.getElementById('cmKpiChartLoading').style.display = 'none';
                        document.getElementById('cmKpiChartNoData').style.display = 'block';
                    });
                };
                if (typeof loadChartJs === 'function') {
                    loadChartJs().then(start).catch(function() {
                        document.getElementById('cmKpiChartLoading').style.display = 'none';
                        document.getElementById('cmKpiChartNoData').style.display = 'block';
                    });
                    return;
                }
                start();
            }

            function renderComplianceKpiChart(data, label) {
                if (typeof Chart === 'undefined') return;
                const canvas = document.getElementById('cmKpiChartCanvas');
                if (!canvas) return;
                const ctx = canvas.getContext('2d');
                if (cmKpiChartInstance) cmKpiChartInstance.destroy();

                const labels = data.map(function(d) { return d.date; });
                const values = data.map(function(d) { return Number(d.value || 0); });
                const colors = data.map(function(d) { return CM_KPI_TONE_COLORS[d.tone] || CM_KPI_TONE_COLORS.gray; });
                const dataMin = Math.min.apply(null, values);
                const dataMax = Math.max.apply(null, values);
                const sorted = values.slice().sort(function(a, b) { return a - b; });
                const mid = Math.floor(sorted.length / 2);
                const median = sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
                const range = dataMax - dataMin || 1;

                document.getElementById('cmKpiHi').textContent = cmKpiFmt(dataMax);
                document.getElementById('cmKpiMed').textContent = cmKpiFmt(median);
                document.getElementById('cmKpiLo').textContent = cmKpiFmt(dataMin);

                cmKpiChartInstance = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: label,
                            data: values,
                            borderColor: '#94a3b8',
                            backgroundColor: 'rgba(148,163,184,0.12)',
                            fill: true,
                            tension: 0.3,
                            borderWidth: 1.5,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            pointBackgroundColor: colors,
                            pointBorderColor: colors
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: {
                                min: Math.min(0, dataMin - range * 0.08),
                                max: dataMax + range * 0.08,
                                ticks: { font: { size: 9 }, callback: function(v) { return cmKpiFmt(v); } }
                            },
                            x: { ticks: { maxRotation: 45, minRotation: 45, font: { size: 9 } } }
                        }
                    }
                });
            }

            function setupComplianceKpiHistory() {
                const host = document.getElementById('cm-summary-stats');
                if (!host || host.dataset.cmKpiBound === '1') return;
                host.dataset.cmKpiBound = '1';
                host.addEventListener('click', function(e) {
                    const dot = e.target.closest && e.target.closest('.summary-trend-dot');
                    if (!dot || !host.contains(dot)) return;
                    e.preventDefault();
                    e.stopPropagation();
                    if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
                    const badge = dot.closest('.cm-summary-badge');
                    if (badge) openComplianceKpiChart(badge);
                }, true);
                host.addEventListener('keydown', function(e) {
                    if (e.key !== 'Enter' && e.key !== ' ') return;
                    const dot = e.target.closest && e.target.closest('.summary-trend-dot');
                    if (!dot || !host.contains(dot)) return;
                    e.preventDefault();
                    e.stopPropagation();
                    const badge = dot.closest('.cm-summary-badge');
                    if (badge) openComplianceKpiChart(badge);
                });
                const rangeEl = document.getElementById('cmKpiChartRange');
                if (rangeEl) {
                    rangeEl.addEventListener('change', function() {
                        cmKpiActive.days = parseInt(this.value, 10) || 30;
                        document.getElementById('cmKpiChartTitle').textContent = cmKpiActive.label + ' — Rolling L' + cmKpiActive.days;
                        loadComplianceKpiChart();
                    });
                }
                refreshComplianceKpiDotTones();
            }

            // Apply all filters
            function applyFilters() {
                filteredData = tableData.filter(item => {
                    // Parent search filter
                    const parentSearchEl = document.getElementById('parentSearch');
                    const parentSearch = parentSearchEl ? parentSearchEl.value.toLowerCase() : '';
                    if (parentSearch && !(item.Parent || '').toLowerCase().includes(parentSearch)) {
                        return false;
                    }

                    // SKU search filter
                    const skuSearchEl = document.getElementById('skuSearch');
                    const skuSearch = skuSearchEl ? skuSearchEl.value.toLowerCase() : '';
                    if (skuSearch && !(item.SKU || '').toLowerCase().includes(skuSearch)) {
                        return false;
                    }

                    const supplierSearchEl = document.getElementById('supplierSearch');
                    const supplierSearch = supplierSearchEl ? supplierSearchEl.value.toLowerCase() : '';
                    if (supplierSearch && !(item.supplier || '').toLowerCase().includes(supplierSearch)) {
                        return false;
                    }

                    const filterSupplierEmailEl = document.getElementById('filterSupplierEmail');
                    const filterSupplierEmail = filterSupplierEmailEl ? filterSupplierEmailEl.value : 'all';
                    if (filterSupplierEmail === 'has' && !String(item.supplier_email || '').trim()) {
                        return false;
                    }
                    if (filterSupplierEmail === 'missing' && String(item.supplier_email || '').trim()) {
                        return false;
                    }

                    // Compliance field filters (Battery / Wireless / …)
                    const fieldFilterMap = {
                        battery: 'filterBattery',
                        wireless: 'filterWireless',
                        electric: 'filterElectric',
                        gcc: 'filterGcc',
                        rohs: 'filterRohs',
                        blanket: 'filterBlanket',
                        bluetooth: 'filterBluetooth',
                        logo: 'filterLogo',
                        graph: 'filterGraph'
                    };
                    for (const key of Object.keys(fieldFilterMap)) {
                        const el = document.getElementById(fieldFilterMap[key]);
                        const fv = el ? el.value : 'all';
                        if (!matchesComplianceFieldFilter(item, key, fv)) {
                            return false;
                        }
                    }

                    if (cmSummaryBadgeFilter === 'any') {
                        if (!rowHasAnyReqCompliance(item)) return false;
                    } else if (cmSummaryBadgeFilter) {
                        if (!isReqFilterMatchForItem(item, cmSummaryBadgeFilter)) return false;
                    }

                    return true;
                });
                renderTable(filteredData);
                updateCounts();
                syncComplianceFieldFilterStyles();
            }

            // Toast notification function
            function showToast(type, message) {
                document.querySelectorAll('.custom-toast').forEach(t => t.remove());

                const toast = document.createElement('div');
                const toastContainer = document.querySelector('.toast-container');
                const useContainer = !!toastContainer;
                toast.className = useContainer
                    ? `custom-toast toast align-items-center text-bg-${type} border-0 show mb-2`
                    : `custom-toast toast align-items-center text-bg-${type} border-0 show position-fixed top-0 end-0 m-4`;
                if (!useContainer) toast.style.zIndex = '2000';
                toast.setAttribute('role', 'alert');
                toast.setAttribute('aria-live', 'assertive');
                toast.setAttribute('aria-atomic', 'true');
                toast.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body">${message}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                `;
                (toastContainer || document.body).appendChild(toast);

                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 500);
                }, 3000);

                toast.querySelector('[data-bs-dismiss="toast"]').onclick = () => {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 500);
                };
            }

            // Setup Excel export function
            function setupExcelExport() {
                document.getElementById('downloadExcel').addEventListener('click', function() {
                    const btn = this;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
                    btn.disabled = true;
                    loadXlsxLib().then(function() {
                        runComplianceExcelExport(btn);
                    }).catch(function() {
                        showToast('danger', 'Failed to load Excel exporter.');
                        btn.innerHTML = '<i class="bi bi-download"></i>';
                        btn.disabled = false;
                    });
                });
            }

            function runComplianceExcelExport(btn) {
                    // Columns to export (excluding Image and Action)
                    const columns = ["Parent", "SKU", "INV", "Supplier", "Email", "Battery", "Wireless", "Electric", "GCC", "RoHs", "Blanket", "Bluetooth", "Logo", "Graph"];

                    // Column definitions with their data keys
                    const columnDefs = {
                        "Parent": {
                            key: "Parent"
                        },
                        "SKU": {
                            key: "SKU"
                        },
                        "INV": {
                            key: "shopify_inv"
                        },
                        "Supplier": {
                            key: "supplier"
                        },
                        "Email": {
                            key: "supplier_email"
                        },
                        "Battery": {
                            key: "battery"
                        },
                        "Wireless": {
                            key: "wireless"
                        },
                        "Electric": {
                            key: "electric"
                        },
                        "GCC": {
                            key: "gcc"
                        },
                        "RoHs": {
                            key: "rohs"
                        },
                        "Blanket": {
                            key: "blanket"
                        },
                        "Bluetooth": {
                            key: "bluetooth"
                        },
                        "Logo": {
                            key: "logo"
                        },
                        "Graph": {
                            key: "graph"
                        }
                    };

                    const complianceFieldKeysForExport = new Set([
                        'battery', 'wireless', 'electric', 'gcc', 'rohs', 'blanket', 'bluetooth', 'logo', 'graph'
                    ]);

                    // Mirror complianceFieldCellHtml() as plain text for Excel export so the
                    // file matches what the user sees on the page (e.g. empty DB value -> "N/A").
                    function complianceFieldCellText(item, key) {
                        const v = complianceFieldStoredValue(item, key);
                        const upper = v.toUpperCase();
                        if (upper === 'REQ') {
                            return 'REQ';
                        }
                        if (isComplianceNaValue(v)) {
                            return 'N/A';
                        }
                        return v;
                    }

                    // Use setTimeout to avoid UI freeze for large datasets
                    setTimeout(() => {
                        try {
                            // Always export the full dataset (tableData) so on-screen
                            // search/filter selections never reduce what gets downloaded.
                            // Falls back to filteredData only if tableData is somehow empty.
                            const sourceData = (Array.isArray(tableData) && tableData.length > 0)
                                ? tableData
                                : (Array.isArray(filteredData) ? filteredData : []);

                            const dataToExport = sourceData.filter(item => {
                                if (!item) return false;
                                const sku = String(item.SKU || '').trim();
                                const parent = String(item.Parent || '').trim();
                                if (sku === '' && parent === '') return false;
                                return true;
                            });

                            console.log('[Compliance Export] tableData:', tableData.length,
                                'filteredData:', filteredData.length,
                                'exporting:', dataToExport.length);

                            // Create worksheet data array
                            const wsData = [];

                            // Add header row
                            wsData.push(columns);

                            // Add data rows
                            dataToExport.forEach(item => {
                                // PARENT summary rows on the page leave compliance fields blank
                                // ("—"), so do the same in Excel and don't force N/A on them.
                                const isParentSummaryRow = complianceRowHasParentKeyword(item);

                                const row = [];
                                columns.forEach(col => {
                                    const colDef = columnDefs[col];
                                    if (colDef) {
                                        const key = colDef.key;
                                        let value = item[key] !== undefined && item[key] !== null ? item[key] : '';

                                        if (key === "shopify_inv") {
                                            if (value === 0 || value === "0") {
                                                value = 0;
                                            } else if (value === null || value === undefined || value === "") {
                                                value = '';
                                            } else {
                                                value = parseFloat(value) || 0;
                                            }
                                        } else if (complianceFieldKeysForExport.has(key)) {
                                            value = isParentSummaryRow ? '' : complianceFieldCellText(item, key);
                                        }

                                        row.push(value);
                                    } else {
                                        row.push('');
                                    }
                                });
                                wsData.push(row);
                            });

                            // Create workbook and worksheet
                            const wb = XLSX.utils.book_new();
                            const ws = XLSX.utils.aoa_to_sheet(wsData);

                            // Set column widths
                            const wscols = columns.map(col => {
                                // Adjust width based on column type
                                if (["Parent", "SKU"].includes(col)) {
                                    return { wch: 20 }; // Wider for text columns
                                } else if (["Supplier", "Email", "Battery", "Wireless", "Electric", "GCC", "RoHs", "Blanket", "Bluetooth", "Logo", "Graph"].includes(col)) {
                                    return { wch: 15 };
                                } else {
                                    return { wch: 10 }; // Default width for numeric columns
                                }
                            });
                            ws['!cols'] = wscols;

                            // Style the header row
                            const headerRange = XLSX.utils.decode_range(ws['!ref']);
                            for (let C = headerRange.s.c; C <= headerRange.e.c; ++C) {
                                const cell = XLSX.utils.encode_cell({
                                    r: 0,
                                    c: C
                                });
                                if (!ws[cell]) continue;

                                // Add header style
                                ws[cell].s = {
                                    fill: {
                                        fgColor: {
                                            rgb: "2C6ED5"
                                        }
                                    },
                                    font: {
                                        bold: true,
                                        color: {
                                            rgb: "FFFFFF"
                                        }
                                    },
                                    alignment: {
                                        horizontal: "center"
                                    }
                                };
                            }

                            // Add the worksheet to the workbook
                            XLSX.utils.book_append_sheet(wb, ws, "Compliance Masters");

                            // Generate Excel file and trigger download
                            XLSX.writeFile(wb, "compliance_master_export.xlsx");

                            // Show success toast (include row count so you can confirm full export)
                            showToast('success', `Excel file downloaded successfully! (${dataToExport.length} rows)`);
                        } catch (error) {
                            console.error("Excel export error:", error);
                            showToast('danger', 'Failed to export Excel file.');
                        } finally {
                            // Reset button state
                            document.getElementById('downloadExcel').innerHTML =
                                '<i class="bi bi-download"></i>';
                            document.getElementById('downloadExcel').disabled = false;
                        }
                    }, 100);
            }

            function findComplianceRowBySku(sku) {
                const s = String(sku || '');
                let row = tableData.find(i => String(i.SKU) === s);
                if (row) return row;
                return filteredData.find(i => String(i.SKU) === s);
            }

            function setupActionButtons() {
                const gridHost = document.getElementById('compliance-tabulator');
                if (!gridHost) return;
                gridHost.addEventListener('click', function(e) {
                    const editBtn = e.target.closest('.edit-btn');
                    if (editBtn && this.contains(editBtn)) {
                        e.preventDefault();
                        const sku = editBtn.getAttribute('data-sku');
                        if (sku) {
                            openComplianceModal('edit', sku);
                        }
                    }
                });
            }

            async function openComplianceModal(mode, editSku = null) {
                if (mode !== 'edit') {
                    return;
                }
                const modalElement = document.getElementById('addComplianceModal');
                const modalTitle = document.getElementById('addComplianceModalLabel');
                const skuSelect = document.getElementById('addComplianceSku');

                const skuStr = String(editSku || '').trim();
                if (!skuStr) {
                    showToast('warning', 'Could not determine SKU to edit.');
                    return;
                }
                if (skuStr.toUpperCase().includes('PARENT')) {
                    showToast('warning', 'Parent summary rows cannot be edited here.');
                    return;
                }
                const item = findComplianceRowBySku(skuStr);
                if (!item) {
                    showToast('warning', 'Row not found. Try refreshing the page.');
                    return;
                }
                complianceFormMode = 'edit';
                complianceEditSku = skuStr;
                modalTitle.textContent = 'Edit Compliance Data';

                complianceFormHydrating = true;
                document.getElementById('addComplianceForm').reset();
                resetComplianceAddFormFields();
                const siblingsCb = document.getElementById('cm_apply_siblings');
                if (siblingsCb) siblingsCb.checked = false;
                setComplianceAutosaveStatus('');
                if (skuSelect) skuSelect.value = skuStr;
                setAddComplianceFormFromItem(item);

                complianceFormHydrating = false;
                updateComplianceSiblingsHint();

                const saveBtn = document.getElementById('saveAddComplianceBtn');
                const newSaveBtn = saveBtn.cloneNode(true);
                saveBtn.parentNode.replaceChild(newSaveBtn, saveBtn);
                newSaveBtn.addEventListener('click', async function() {
                    await saveCompliance({ auto: false, keepOpen: false });
                });

                modalElement.addEventListener('hidden.bs.offcanvas', function complianceModalCleanup() {
                    if (complianceAutoSaveTimer) {
                        clearTimeout(complianceAutoSaveTimer);
                        complianceAutoSaveTimer = null;
                    }
                    complianceFormMode = 'edit';
                    complianceEditSku = '';
                    complianceFormHydrating = false;
                    setComplianceAutosaveStatus('');
                }, { once: true });

                const panel = bootstrap.Offcanvas.getOrCreateInstance(modalElement);
                panel.show();
            }

            async function saveCompliance(options = {}) {
                const opts = Object.assign({ auto: false, keepOpen: false }, options || {});
                const saveBtn = document.getElementById('saveAddComplianceBtn');
                const originalText = saveBtn ? saveBtn.innerHTML : '';
                const skuSelect = document.getElementById('addComplianceSku');
                const sku = (complianceEditSku || (skuSelect && skuSelect.value) || '').trim();

                if (!sku) {
                    if (!opts.auto) {
                        showToast('warning', 'Missing SKU for update.');
                    }
                    return;
                }

                if (opts.auto && complianceAutoSaveInFlight) {
                    complianceAutoSaveQueued = true;
                    return;
                }

                const url = '/compliance-master/update';

                try {
                    complianceAutoSaveInFlight = true;
                    if (opts.auto) {
                        setComplianceAutosaveStatus('Saving…', false);
                    } else if (saveBtn) {
                        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Saving...';
                        saveBtn.disabled = true;
                    }

                    const formData = collectComplianceFormPayload(sku);

                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify(formData)
                    });

                    const data = await response.json().catch(() => ({}));

                    if (!response.ok || data.success === false) {
                        throw new Error(data.message || 'Failed to save data');
                    }

                    const sibCount = Number(data.siblings_count) || (Array.isArray(data.siblings) ? data.siblings.length : 0);
                    patchLocalComplianceRowsFromPayload(formData, data.siblings || []);
                    updateComplianceSiblingsHint();

                    if (opts.auto || opts.keepOpen) {
                        const msg = sibCount > 0
                            ? ('Saved (+' + sibCount + ' siblings)')
                            : 'Saved';
                        setComplianceAutosaveStatus(msg, false);
                        if (!opts.auto) {
                            showToast('success', data.message || msg);
                        }
                    } else {
                        showToast('success', data.message || 'Compliance data updated successfully!');
                        const panel = bootstrap.Offcanvas.getInstance(document.getElementById('addComplianceModal'));
                        if (panel) panel.hide();
                    }
                } catch (error) {
                    console.error('Error saving:', error);
                    if (opts.auto) {
                        setComplianceAutosaveStatus(error.message || 'Save failed', true);
                    } else {
                        showToast('danger', error.message || 'Failed to save data');
                    }
                } finally {
                    complianceAutoSaveInFlight = false;
                    if (saveBtn && !opts.auto) {
                        saveBtn.innerHTML = originalText;
                        saveBtn.disabled = false;
                    }
                    if (complianceAutoSaveQueued) {
                        complianceAutoSaveQueued = false;
                        scheduleComplianceAutosave();
                    }
                }
            }

            // Setup import functionality
            function setupImport() {
                const importFile = document.getElementById('importFile');
                const importBtn = document.getElementById('importBtn');
                const downloadSampleBtn = document.getElementById('downloadSampleBtn');
                const importModal = document.getElementById('importModal');
                const fileError = document.getElementById('fileError');
                const importProgress = document.getElementById('importProgress');
                const importResult = document.getElementById('importResult');

                // Enable/disable import button based on file selection
                importFile.addEventListener('change', function() {
                    if (this.files && this.files.length > 0) {
                        const file = this.files[0];
                        const fileName = file.name.toLowerCase();
                        const validExtensions = ['.xlsx', '.xls', '.csv'];
                        const isValid = validExtensions.some(ext => fileName.endsWith(ext));

                        if (isValid) {
                            importBtn.disabled = false;
                            fileError.style.display = 'none';
                        } else {
                            importBtn.disabled = true;
                            fileError.textContent = 'Please select a valid Excel file (.xlsx, .xls, or .csv)';
                            fileError.style.display = 'block';
                        }
                    } else {
                        importBtn.disabled = true;
                    }
                });

                // Download sample file
                downloadSampleBtn.addEventListener('click', function() {
                    // Create sample data
                    const sampleData = [
                        ['SKU', 'Battery', 'Wireless', 'Electric', 'GCC', 'RoHs', 'Blanket', 'Bluetooth', 'Logo', 'Graph'],
                        ['SKU001', 'N/A', 'REQ', 'N/A', 'REQ', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A'],
                        ['SKU002', 'REQ', 'N/A', 'REQ', 'N/A', 'REQ', 'N/A', 'N/A', 'N/A', 'N/A'],
                        ['SKU003', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'REQ']
                    ];

                    // Create workbook
                    const wb = XLSX.utils.book_new();
                    const ws = XLSX.utils.aoa_to_sheet(sampleData);

                    // Set column widths
                    ws['!cols'] = [
                        { wch: 15 }, // SKU
                        { wch: 12 }, // Battery
                        { wch: 12 }, // Wireless
                        { wch: 12 }, // Electric
                        { wch: 12 }, // GCC
                        { wch: 12 }, // RoHs
                        { wch: 12 }, // Blanket
                        { wch: 12 }, // Bluetooth
                        { wch: 12 }, // Logo
                        { wch: 12 }  // Graph
                    ];

                    // Style header row
                    const headerRange = XLSX.utils.decode_range(ws['!ref']);
                    for (let C = headerRange.s.c; C <= headerRange.e.c; ++C) {
                        const cell = XLSX.utils.encode_cell({ r: 0, c: C });
                        if (!ws[cell]) continue;
                        ws[cell].s = {
                            fill: { fgColor: { rgb: "2C6ED5" } },
                            font: { bold: true, color: { rgb: "FFFFFF" } },
                            alignment: { horizontal: "center" }
                        };
                    }

                    XLSX.utils.book_append_sheet(wb, ws, "Compliance Data");
                    XLSX.writeFile(wb, "compliance_master_sample.xlsx");
                    
                    showToast('success', 'Sample file downloaded successfully!');
                });

                // Handle import
                importBtn.addEventListener('click', async function() {
                    const file = importFile.files[0];
                    if (!file) {
                        showToast('danger', 'Please select a file to import');
                        return;
                    }

                    // Disable button and show progress
                    importBtn.disabled = true;
                    importProgress.style.display = 'block';
                    importResult.style.display = 'none';
                    fileError.style.display = 'none';

                    const formData = new FormData();
                    formData.append('excel_file', file);
                    formData.append('_token', csrfToken);

                    try {
                        const response = await fetch('/compliance-master/import', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: formData
                        });

                        const result = await response.json();

                        // Update progress bar
                        const progressBar = importProgress.querySelector('.progress-bar');
                        progressBar.style.width = '100%';

                        if (response.ok && result.success) {
                            importResult.className = 'alert alert-success';
                            importResult.innerHTML = `
                                <i class="bi bi-check-circle me-2"></i>
                                <strong>Import Successful!</strong><br>
                                ${result.message || `Successfully imported ${result.imported || 0} records.`}
                                ${result.errors && result.errors.length > 0 ? `<br><small>Errors: ${result.errors.length}</small>` : ''}
                            `;
                            importResult.style.display = 'block';

                            // Reload data after successful import
                            setTimeout(() => {
                                loadData();
                                // Close modal after a delay
                                setTimeout(() => {
                                    const modal = bootstrap.Modal.getInstance(importModal);
                                    if (modal) modal.hide();
                                    // Reset form
                                    importFile.value = '';
                                    importBtn.disabled = true;
                                    importProgress.style.display = 'none';
                                    importResult.style.display = 'none';
                                    progressBar.style.width = '0%';
                                }, 2000);
                            }, 1000);
                        } else {
                            importResult.className = 'alert alert-danger';
                            importResult.innerHTML = `
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <strong>Import Failed!</strong><br>
                                ${result.message || 'An error occurred during import.'}
                            `;
                            importResult.style.display = 'block';
                            importBtn.disabled = false;
                        }
                    } catch (error) {
                        console.error('Import error:', error);
                        importResult.className = 'alert alert-danger';
                        importResult.innerHTML = `
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Import Failed!</strong><br>
                            ${error.message || 'An error occurred during import.'}
                        `;
                        importResult.style.display = 'block';
                        importBtn.disabled = false;
                    } finally {
                        // Reset progress bar after a delay
                        setTimeout(() => {
                            const progressBar = importProgress.querySelector('.progress-bar');
                            progressBar.style.width = '0%';
                        }, 2000);
                    }
                });

                // Reset form when modal is closed
                importModal.addEventListener('hidden.bs.modal', function() {
                    importFile.value = '';
                    importBtn.disabled = true;
                    importProgress.style.display = 'none';
                    importResult.style.display = 'none';
                    fileError.style.display = 'none';
                    const progressBar = importProgress.querySelector('.progress-bar');
                    if (progressBar) progressBar.style.width = '0%';
                });
            }

            function setupComplianceAddFormListeners() {
                const modal = document.getElementById('addComplianceModal');
                if (!modal) return;

                modal.addEventListener('click', function(e) {
                    const trigger = e.target.closest('[data-trigger-file]');
                    if (!trigger || !modal.contains(trigger)) return;
                    e.preventDefault();
                    const fileId = trigger.getAttribute('data-trigger-file');
                    const fileInput = fileId ? document.getElementById(fileId) : null;
                    if (fileInput) fileInput.click();
                });

                modal.addEventListener('change', function(e) {
                    if (e.target && e.target.id === 'cm_apply_siblings') {
                        updateComplianceSiblingsHint();
                        scheduleComplianceAutosave();
                        return;
                    }
                    const name = e.target.getAttribute('name');
                    if (name && name.startsWith('add_mode_')) {
                        const k = name.replace('add_mode_', '');
                        toggleAddComplianceReqWrap(k);
                        scheduleComplianceAutosave();
                    }
                });

                modal.addEventListener('change', async function(e) {
                    const inp = e.target.closest('.add-compliance-img-input');
                    if (!inp || !inp.files || !inp.files[0]) return;
                    const field = inp.dataset.field;
                    const pathInputId = inp.dataset.pathInput;
                    const pathEl = pathInputId ? document.getElementById(pathInputId) : null;
                    const st = document.getElementById(`add_${field}_img_status`);
                    try {
                        if (st) st.textContent = 'Uploading...';
                        const path = await uploadComplianceFieldImageToServer(field, inp.files[0]);
                        if (pathEl) pathEl.value = path;
                        if (st) st.textContent = 'Image saved.';
                        refreshComplianceFieldFileUi(field);
                        scheduleComplianceAutosave();
                    } catch (err) {
                        if (st) st.textContent = err.message || 'Upload failed';
                        showToast('danger', err.message || 'Upload failed');
                    }
                });

                modal.addEventListener('change', async function(e) {
                    const inp = e.target.closest('.add-compliance-pdf-input');
                    if (!inp || !inp.files || !inp.files[0]) return;
                    const field = inp.dataset.field;
                    const pathInputId = inp.dataset.pathInput;
                    const pathEl = pathInputId ? document.getElementById(pathInputId) : null;
                    const st = document.getElementById(`add_${field}_pdf_status`);
                    try {
                        if (st) st.textContent = 'Uploading...';
                        const path = await uploadComplianceFieldPdfToServer(field, inp.files[0]);
                        if (pathEl) pathEl.value = path;
                        if (st) st.textContent = 'PDF saved.';
                        refreshComplianceFieldFileUi(field);
                        scheduleComplianceAutosave();
                    } catch (err) {
                        if (st) st.textContent = err.message || 'Upload failed';
                        showToast('danger', err.message || 'Upload failed');
                    }
                });
            }

            // Initialize
            setupComplianceImageHoverPreview();
            setupComplianceEmailHover();
            setupComplianceFileHoverActions();
            setupSummaryBadgeFilters();
            setupComplianceKpiHistory();
            setupComplianceAddFormListeners();
            setupActionButtons();
            loadData();
            setupExcelExport();
            setupImport();
        });
    </script>
@endsection

