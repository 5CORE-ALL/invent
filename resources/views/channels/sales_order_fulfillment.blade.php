@extends('layouts.vertical', ['title' => 'Sales Order Fulfillment', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .sof-channel-logo {
            width: 36px;
            height: 36px;
            object-fit: contain;
            border-radius: 6px;
            background: #fff;
            border: 1px solid #e9ecef;
            padding: 2px;
            display: inline-block;
            vertical-align: middle;
        }
        .sof-channel-logo-placeholder {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 6px;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            color: #adb5bd;
            font-size: 14px;
            vertical-align: middle;
        }
        .sof-channel-logo-link {
            display: inline-flex;
            line-height: 1;
        }
        .sof-channel-name {
            font-weight: 600;
            color: #1e293b;
            text-decoration: none;
        }
        .sof-channel-name.has-link {
            color: #0d6efd;
        }
        .sof-channel-name.has-link:hover {
            text-decoration: underline;
        }

        /* Hide default sorter caret (vertical headers); header click still sorts */
        #sales-order-fulfillment-table.tabulator .tabulator-col .tabulator-col-sorter,
        #sof-pending-table.tabulator .tabulator-col .tabulator-col-sorter,
        #sof-fulfilled-table.tabulator .tabulator-col .tabulator-col-sorter,
        #sof-scan-done-table.tabulator .tabulator-col .tabulator-col-sorter,
        #sof-in-transit-table.tabulator .tabulator-col .tabulator-col-sorter,
        #sof-in-received-table.tabulator .tabulator-col .tabulator-col-sorter,
        #sof-invoiced-table.tabulator .tabulator-col .tabulator-col-sorter,
        #sof-delivered-table.tabulator .tabulator-col .tabulator-col-sorter,
        #sof-all-order-table.tabulator .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }

        #sales-order-fulfillment-table.tabulator .tabulator-header .tabulator-col {
            background-color: #e6e6e6;
        }

        /* Vertical column headers — same as /all-marketplace-master */
        #sales-order-fulfillment-table.tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            white-space: nowrap;
            transform: rotate(180deg);
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
            color: black !important;
            overflow: visible;
            text-overflow: clip;
            pointer-events: none; /* clicks pass through to sortable column */
        }

        #sales-order-fulfillment-table.tabulator .tabulator-header .tabulator-col {
            height: 80px !important;
            overflow: visible;
        }

        #sales-order-fulfillment-table.tabulator .tabulator-header .tabulator-col.tabulator-sortable {
            cursor: pointer;
        }

        #sales-order-fulfillment-table.tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0 !important;
        }

        #sales-order-fulfillment-table .tabulator-row .tabulator-cell {
            vertical-align: middle;
        }

        #sof-filter-bar {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 6px 10px;
        }
        #sof-filter-bar .sof-filter-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 2px;
            line-height: 1.1;
        }
        /* Compact 2-row toolbar: badges/actions + filters */
        #sof-toolbar {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 6px 8px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        #sof-toolbar-row1,
        #sof-toolbar-row2 {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            gap: 6px;
            min-width: 0;
            overflow-x: auto;
        }
        #sof-toolbar .sof-summary-badge {
            font-size: 0.72rem !important;
            padding: 0.2rem 0.45rem !important;
            line-height: 1.15;
            font-weight: 600 !important;
            white-space: nowrap;
            flex-shrink: 0;
        }
        #sof-toolbar .sof-top-badge {
            font-size: 0.72rem;
            padding: 0.2rem 0.45rem;
            gap: 5px;
            flex-shrink: 0;
        }
        #sof-toolbar .btn-sm {
            padding: 0.2rem 0.5rem;
            font-size: 0.75rem;
            line-height: 1.2;
            white-space: nowrap;
            flex-shrink: 0;
        }
        #sof-toolbar .form-control-sm,
        #sof-toolbar .form-select-sm,
        #sof-toolbar .input-group-sm > .form-control,
        #sof-toolbar .input-group-sm > .input-group-text {
            min-height: 28px;
            height: 28px;
            padding-top: 0.15rem;
            padding-bottom: 0.15rem;
            font-size: 0.78rem;
        }
        #sof-toolbar .sof-filter-field {
            flex-shrink: 0;
        }
        #sof-toolbar #sof-order-search {
            min-width: 180px;
            flex: 1 1 180px;
        }
        #sof-toolbar #sof-channel-filter {
            min-width: 130px;
        }
        #sof-date-filter-hint {
            font-size: 0.7rem;
            white-space: nowrap;
            color: #64748b;
            margin-left: auto;
            flex-shrink: 0;
        }
        #sof-tabs {
            margin-bottom: 0.5rem !important;
        }
        .sof-date-scope-hint {
            margin-bottom: 0.35rem !important;
        }

        .sof-oc-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            vertical-align: middle;
            margin-right: 6px;
        }
        .sof-oc-dot.connected { background: #0ab39c; }
        .sof-oc-dot.disconnected { background: #f06548; }
        .sof-oc-missing { color: #adb5bd; }

        .sof-hist-dot {
            display: inline-block;
            width: 9px;
            height: 9px;
            border-radius: 50%;
            margin-left: 6px;
            vertical-align: middle;
            cursor: pointer;
            box-shadow: 0 0 0 1px rgba(0,0,0,0.08);
        }
        .sof-hist-dot:hover {
            transform: scale(1.25);
        }
        .sof-summary-badge {
            cursor: pointer;
            user-select: none;
        }
        .sof-summary-badge:hover {
            filter: brightness(0.97);
        }
        #sofHistoryChartModal .modal-dialog {
            max-width: 920px;
        }
        #sofHistoryChartContainer {
            height: 28vh;
            min-height: 180px;
            display: flex;
            align-items: stretch;
        }

        .sof-carrier-badge {
            display: inline-block;
            font-size: 0.72rem;
            font-weight: 600;
            line-height: 1.2;
            padding: 0.2rem 0.45rem;
            border-radius: 0.35rem;
            border: 1px solid transparent;
            white-space: nowrap;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            vertical-align: middle;
        }
        .sof-carrier-usps { background: #e0f2fe; color: #0369a1; border-color: #bae6fd; }
        .sof-carrier-ups { background: #fef3c7; color: #92400e; border-color: #fde68a; }
        .sof-carrier-fedex { background: #ede9fe; color: #5b21b6; border-color: #ddd6fe; }
        .sof-carrier-dhl { background: #ffedd5; color: #c2410c; border-color: #fed7aa; }
        .sof-carrier-amazon { background: #fffef2; color: #854d0e; border-color: #fde047; }
        .sof-carrier-gofo { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
        .sof-carrier-ontrac { background: #e0e7ff; color: #3730a3; border-color: #c7d2fe; }
        .sof-carrier-veeqo { background: #fce7f3; color: #9d174d; border-color: #fbcfe8; }
        .sof-carrier-other { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }

        .sof-pending-badge {
            display: inline-block;
            background: #fff3cd;
            color: #856404;
            font-weight: 500;
            font-size: 0.8rem;
            padding: 0.35rem 0.7rem;
            border-radius: 50rem;
            border: 1px solid #ffe69c;
            text-decoration: none;
            line-height: 1.2;
            white-space: nowrap;
            box-shadow: none;
        }
        .sof-pending-badge:hover {
            background: #ffecb5;
            color: #664d03;
            text-decoration: none;
        }
        .sof-pending-badge.is-zero {
            background: #f1f5f9;
            color: #94a3b8;
            border-color: #e2e8f0;
            font-weight: 500;
        }

        .sof-ch-orders-dot {
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            vertical-align: middle;
            cursor: pointer;
        }
        .sof-ch-orders-dot.red {
            background: #f06548;
            box-shadow: 0 0 0 2px rgba(240, 101, 72, 0.2);
        }
        .sof-ch-orders-dot.green {
            background: #0ab39c;
            box-shadow: 0 0 0 2px rgba(10, 179, 156, 0.2);
            text-decoration: none;
        }
        .sof-ch-orders-dot.green:hover {
            box-shadow: 0 0 0 3px rgba(10, 179, 156, 0.35);
        }

        .sof-order-id-wrap {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .sof-order-id-dot {
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #0ab39c;
            box-shadow: 0 0 0 2px rgba(10, 179, 156, 0.2);
            cursor: pointer;
            vertical-align: middle;
        }
        .sof-order-id-wrap:hover .sof-order-id-dot {
            box-shadow: 0 0 0 3px rgba(10, 179, 156, 0.35);
        }
        .sof-order-id-popover {
            display: none;
            position: absolute;
            left: 50%;
            bottom: calc(100% + 8px);
            transform: translateX(-50%);
            z-index: 40;
            min-width: 180px;
            max-width: 320px;
            background: #fff;
            color: #1e293b;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
            padding: 8px 10px;
            white-space: nowrap;
        }
        .sof-order-id-wrap:hover .sof-order-id-popover {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sof-order-id-popover::after {
            content: '';
            position: absolute;
            left: 50%;
            top: 100%;
            transform: translateX(-50%);
            border: 6px solid transparent;
            border-top-color: #fff;
            filter: drop-shadow(0 1px 0 #e2e8f0);
        }
        .sof-order-id-text {
            font-size: 0.8rem;
            font-weight: 600;
            color: #0f172a;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sof-order-id-text a {
            color: #0d6efd;
            text-decoration: none;
        }
        .sof-order-id-text a:hover {
            text-decoration: underline;
        }
        .sof-order-id-copy {
            border: none;
            background: #f1f5f9;
            color: #475569;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
            padding: 0;
        }
        .sof-order-id-copy:hover {
            background: #e2e8f0;
            color: #0f172a;
        }
        .sof-order-id-copy.copied {
            background: #d1e7dd;
            color: #0f5132;
        }

        .sof-text-dot-wrap {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .sof-text-dot {
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #6c8cff;
            box-shadow: 0 0 0 2px rgba(108, 140, 255, 0.22);
            cursor: default;
            vertical-align: middle;
        }
        .sof-text-dot-wrap:hover .sof-text-dot {
            box-shadow: 0 0 0 3px rgba(108, 140, 255, 0.35);
        }
        .sof-text-dot-box {
            display: none;
            position: absolute;
            right: calc(100% + 10px);
            top: 50%;
            left: auto;
            bottom: auto;
            transform: translateY(-50%);
            z-index: 40;
            min-width: 160px;
            max-width: 360px;
            background: #fff;
            color: #334155;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 8px 10px;
            font-size: 0.8rem;
            font-weight: 500;
            line-height: 1.35;
            white-space: normal;
            text-align: left;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
        }
        .sof-text-dot-wrap:hover .sof-text-dot-box {
            display: block;
        }
        .sof-text-dot-box::after {
            content: '';
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            border: 6px solid transparent;
            border-left-color: #fff;
            filter: drop-shadow(1px 0 0 #cbd5e1);
        }

        .sof-top-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #405189;
            color: #fff;
            font-weight: 700;
            font-size: 0.95rem;
            padding: 0.5rem 0.85rem;
            border-radius: 0.5rem;
            text-decoration: none;
            cursor: pointer;
            user-select: none;
            border: none;
        }
        .sof-top-badge:hover {
            color: #fff;
            text-decoration: none;
            filter: brightness(1.08);
        }
        .sof-top-badge.is-disabled {
            opacity: 0.85;
            cursor: default;
        }
        .sof-top-badge .sof-ch-orders-dot {
            flex-shrink: 0;
        }
        .sof-top-badge.gofo { background: #0d6efd; }
        .sof-top-badge.gofo.is-api-ready { box-shadow: inset 0 0 0 2px rgba(255,255,255,0.55); cursor: pointer; }
        .sof-top-badge.veeqo { background: #6610f2; }
        .sof-top-badge.veeqo.is-api-ready { box-shadow: inset 0 0 0 2px rgba(255,255,255,0.55); cursor: default; }
        .sof-top-badge.shopify { background: #198754; }
        .sof-top-badge.others { background: #6c757d; }
        #sofGofoToolsModal .sof-gofo-result {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 0.65rem 0.75rem;
            font-size: 0.82rem;
            white-space: pre-wrap;
            max-height: 260px;
            overflow: auto;
        }

        #sof-tabs .nav-link {
            font-weight: 600;
            color: #475569;
        }
        #sof-tabs .nav-link.active {
            color: #0d6efd;
            border-bottom-color: #0d6efd;
        }
        #sof-pending-total-badge,
        #sof-fulfilled-24h-badge,
        #sof-scan-done-24h-badge,
        #sof-in-transit-badge,
        #sof-in-received-badge,
        #sof-invoiced-badge,
        #sof-delivered-badge,
        #sof-all-order-badge {
            cursor: pointer;
        }

        #sof-pending-table.tabulator .tabulator-header .tabulator-col,
        #sof-fulfilled-table.tabulator .tabulator-header .tabulator-col,
        #sof-scan-done-table.tabulator .tabulator-header .tabulator-col,
        #sof-in-transit-table.tabulator .tabulator-header .tabulator-col,
        #sof-in-received-table.tabulator .tabulator-header .tabulator-col,
        #sof-invoiced-table.tabulator .tabulator-header .tabulator-col,
        #sof-delivered-table.tabulator .tabulator-header .tabulator-col,
        #sof-all-order-table.tabulator .tabulator-header .tabulator-col {
            background-color: #e6e6e6;
        }
        #sof-pending-table.tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title,
        #sof-fulfilled-table.tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title,
        #sof-scan-done-table.tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title,
        #sof-in-transit-table.tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title,
        #sof-in-received-table.tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title,
        #sof-invoiced-table.tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title,
        #sof-delivered-table.tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title,
        #sof-all-order-table.tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            white-space: nowrap;
            transform: rotate(180deg);
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
            color: black !important;
            overflow: visible;
            text-overflow: clip;
            pointer-events: none; /* clicks pass through to sortable column */
        }
        /* Select-all checkbox: keep horizontal + clickable (vertical headers break header tickbox) */
        #sof-pending-table.tabulator .tabulator-header .tabulator-col.tabulator-row-header .tabulator-col-title,
        #sof-fulfilled-table.tabulator .tabulator-header .tabulator-col.tabulator-row-header .tabulator-col-title,
        #sof-scan-done-table.tabulator .tabulator-header .tabulator-col.tabulator-row-header .tabulator-col-title,
        #sof-in-transit-table.tabulator .tabulator-header .tabulator-col.tabulator-row-header .tabulator-col-title,
        #sof-in-received-table.tabulator .tabulator-header .tabulator-col.tabulator-row-header .tabulator-col-title,
        #sof-invoiced-table.tabulator .tabulator-header .tabulator-col.tabulator-row-header .tabulator-col-title,
        #sof-delivered-table.tabulator .tabulator-header .tabulator-col.tabulator-row-header .tabulator-col-title,
        #sof-all-order-table.tabulator .tabulator-header .tabulator-col.tabulator-row-header .tabulator-col-title {
            writing-mode: horizontal-tb;
            text-orientation: mixed;
            transform: none;
            height: auto;
            min-height: 0;
            pointer-events: auto;
            cursor: pointer;
        }
        #sof-pending-table.tabulator .tabulator-header .tabulator-col.tabulator-row-header,
        #sof-fulfilled-table.tabulator .tabulator-header .tabulator-col.tabulator-row-header,
        #sof-scan-done-table.tabulator .tabulator-header .tabulator-col.tabulator-row-header,
        #sof-in-transit-table.tabulator .tabulator-header .tabulator-col.tabulator-row-header,
        #sof-in-received-table.tabulator .tabulator-header .tabulator-col.tabulator-row-header,
        #sof-invoiced-table.tabulator .tabulator-header .tabulator-col.tabulator-row-header,
        #sof-delivered-table.tabulator .tabulator-header .tabulator-col.tabulator-row-header,
        #sof-all-order-table.tabulator .tabulator-header .tabulator-col.tabulator-row-header {
            cursor: pointer;
        }
        /* Ensure header/row tickboxes always receive clicks despite vertical-header CSS */
        #sof-pending-table.tabulator .tabulator-header input[type="checkbox"],
        #sof-fulfilled-table.tabulator .tabulator-header input[type="checkbox"],
        #sof-scan-done-table.tabulator .tabulator-header input[type="checkbox"],
        #sof-in-transit-table.tabulator .tabulator-header input[type="checkbox"],
        #sof-in-received-table.tabulator .tabulator-header input[type="checkbox"],
        #sof-invoiced-table.tabulator .tabulator-header input[type="checkbox"],
        #sof-delivered-table.tabulator .tabulator-header input[type="checkbox"],
        #sof-all-order-table.tabulator .tabulator-header input[type="checkbox"],
        #sof-pending-table.tabulator .tabulator-cell input[type="checkbox"],
        #sof-fulfilled-table.tabulator .tabulator-cell input[type="checkbox"],
        #sof-scan-done-table.tabulator .tabulator-cell input[type="checkbox"],
        #sof-in-transit-table.tabulator .tabulator-cell input[type="checkbox"],
        #sof-in-received-table.tabulator .tabulator-cell input[type="checkbox"],
        #sof-invoiced-table.tabulator .tabulator-cell input[type="checkbox"],
        #sof-delivered-table.tabulator .tabulator-cell input[type="checkbox"],
        #sof-all-order-table.tabulator .tabulator-cell input[type="checkbox"] {
            pointer-events: auto !important;
            transform: none !important;
            writing-mode: horizontal-tb !important;
            cursor: pointer;
        }
        #sof-pending-table.tabulator .tabulator-header .tabulator-col,
        #sof-fulfilled-table.tabulator .tabulator-header .tabulator-col,
        #sof-scan-done-table.tabulator .tabulator-header .tabulator-col,
        #sof-in-transit-table.tabulator .tabulator-header .tabulator-col,
        #sof-in-received-table.tabulator .tabulator-header .tabulator-col,
        #sof-invoiced-table.tabulator .tabulator-header .tabulator-col,
        #sof-delivered-table.tabulator .tabulator-header .tabulator-col,
        #sof-all-order-table.tabulator .tabulator-header .tabulator-col {
            height: 80px !important;
            overflow: visible;
        }
        #sof-pending-table.tabulator .tabulator-header .tabulator-col.tabulator-sortable,
        #sof-fulfilled-table.tabulator .tabulator-header .tabulator-col.tabulator-sortable,
        #sof-scan-done-table.tabulator .tabulator-header .tabulator-col.tabulator-sortable,
        #sof-in-transit-table.tabulator .tabulator-header .tabulator-col.tabulator-sortable,
        #sof-in-received-table.tabulator .tabulator-header .tabulator-col.tabulator-sortable,
        #sof-invoiced-table.tabulator .tabulator-header .tabulator-col.tabulator-sortable,
        #sof-delivered-table.tabulator .tabulator-header .tabulator-col.tabulator-sortable,
        #sof-all-order-table.tabulator .tabulator-header .tabulator-col.tabulator-sortable {
            cursor: pointer;
        }
        #sof-pending-table .tabulator-row .tabulator-cell,
        #sof-fulfilled-table .tabulator-row .tabulator-cell,
        #sof-scan-done-table .tabulator-row .tabulator-cell,
        #sof-in-transit-table .tabulator-row .tabulator-cell,
        #sof-in-received-table .tabulator-row .tabulator-cell,
        #sof-invoiced-table .tabulator-row .tabulator-cell,
        #sof-delivered-table .tabulator-row .tabulator-cell,
        #sof-all-order-table .tabulator-row .tabulator-cell {
            vertical-align: middle;
        }
        #sof-pending-table .tabulator-row .tabulator-cell:has(.sof-order-id-wrap),
        #sof-fulfilled-table .tabulator-row .tabulator-cell:has(.sof-order-id-wrap),
        #sof-scan-done-table .tabulator-row .tabulator-cell:has(.sof-order-id-wrap),
        #sof-in-transit-table .tabulator-row .tabulator-cell:has(.sof-order-id-wrap),
        #sof-in-received-table .tabulator-row .tabulator-cell:has(.sof-order-id-wrap),
        #sof-invoiced-table .tabulator-row .tabulator-cell:has(.sof-order-id-wrap),
        #sof-delivered-table .tabulator-row .tabulator-cell:has(.sof-order-id-wrap),
        #sof-all-order-table .tabulator-row .tabulator-cell:has(.sof-order-id-wrap),
        #sof-pending-table .tabulator-row .tabulator-cell:has(.sof-text-dot-wrap),
        #sof-fulfilled-table .tabulator-row .tabulator-cell:has(.sof-text-dot-wrap),
        #sof-scan-done-table .tabulator-row .tabulator-cell:has(.sof-text-dot-wrap),
        #sof-in-transit-table .tabulator-row .tabulator-cell:has(.sof-text-dot-wrap),
        #sof-in-received-table .tabulator-row .tabulator-cell:has(.sof-text-dot-wrap),
        #sof-invoiced-table .tabulator-row .tabulator-cell:has(.sof-text-dot-wrap),
        #sof-delivered-table .tabulator-row .tabulator-cell:has(.sof-text-dot-wrap),
        #sof-all-order-table .tabulator-row .tabulator-cell:has(.sof-text-dot-wrap) {
            overflow: visible !important;
        }
        #sof-pending-table .tabulator-row:has(.sof-order-id-wrap:hover),
        #sof-fulfilled-table .tabulator-row:has(.sof-order-id-wrap:hover),
        #sof-scan-done-table .tabulator-row:has(.sof-order-id-wrap:hover),
        #sof-in-transit-table .tabulator-row:has(.sof-order-id-wrap:hover),
        #sof-in-received-table .tabulator-row:has(.sof-order-id-wrap:hover),
        #sof-invoiced-table .tabulator-row:has(.sof-order-id-wrap:hover),
        #sof-delivered-table .tabulator-row:has(.sof-order-id-wrap:hover),
        #sof-all-order-table .tabulator-row:has(.sof-order-id-wrap:hover),
        #sof-pending-table .tabulator-row:has(.sof-text-dot-wrap:hover),
        #sof-fulfilled-table .tabulator-row:has(.sof-text-dot-wrap:hover),
        #sof-scan-done-table .tabulator-row:has(.sof-text-dot-wrap:hover),
        #sof-in-transit-table .tabulator-row:has(.sof-text-dot-wrap:hover),
        #sof-in-received-table .tabulator-row:has(.sof-text-dot-wrap:hover),
        #sof-invoiced-table .tabulator-row:has(.sof-text-dot-wrap:hover),
        #sof-delivered-table .tabulator-row:has(.sof-text-dot-wrap:hover),
        #sof-all-order-table .tabulator-row:has(.sof-text-dot-wrap:hover) {
            z-index: 5;
            position: relative;
        }

        .sof-fulfilled-badge {
            display: inline-block;
            background: #d1e7dd;
            color: #0f5132;
            font-weight: 500;
            font-size: 0.8rem;
            padding: 0.35rem 0.7rem;
            border-radius: 50rem;
            border: 1px solid #a3cfbb;
            line-height: 1.2;
            white-space: nowrap;
        }
        .sof-scan-done-badge {
            display: inline-block;
            background: #cfe2ff;
            color: #084298;
            font-weight: 500;
            font-size: 0.8rem;
            padding: 0.35rem 0.7rem;
            border-radius: 50rem;
            border: 1px solid #9ec5fe;
            line-height: 1.2;
            white-space: nowrap;
        }
        .sof-in-transit-badge {
            display: inline-block;
            background: #ffe5d0;
            color: #9a3412;
            font-weight: 500;
            font-size: 0.8rem;
            padding: 0.35rem 0.7rem;
            border-radius: 50rem;
            border: 1px solid #fdba74;
            line-height: 1.2;
            white-space: nowrap;
        }
        .sof-in-received-badge {
            display: inline-block;
            background: #d1fae5;
            color: #065f46;
            font-weight: 500;
            font-size: 0.8rem;
            padding: 0.35rem 0.7rem;
            border-radius: 50rem;
            border: 1px solid #6ee7b7;
            line-height: 1.2;
            white-space: nowrap;
        }
        .sof-invoiced-badge {
            display: inline-block;
            background: #e2d9f3;
            color: #432874;
            font-weight: 500;
            font-size: 0.8rem;
            padding: 0.35rem 0.7rem;
            border-radius: 50rem;
            border: 1px solid #c5b3e6;
            line-height: 1.2;
            white-space: nowrap;
        }
        .sof-delivered-badge {
            display: inline-block;
            background: #cff4fc;
            color: #055160;
            font-weight: 500;
            font-size: 0.8rem;
            padding: 0.35rem 0.7rem;
            border-radius: 50rem;
            border: 1px solid #9eeaf9;
            line-height: 1.2;
            white-space: nowrap;
        }
        .sof-all-order-status-badge {
            display: inline-block;
            background: #e9ecef;
            color: #343a40;
            font-weight: 500;
            font-size: 0.8rem;
            padding: 0.35rem 0.7rem;
            border-radius: 50rem;
            border: 1px solid #ced4da;
            line-height: 1.2;
            white-space: nowrap;
        }
        .sof-inv-cell {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }
        .sof-inv-zero-alert {
            color: #dc3545;
            font-size: 0.75rem;
            line-height: 1;
            cursor: help;
        }
        .sof-inv-zero-alert i {
            color: #dc3545;
        }
        .sof-label-badge {
            display: inline-block;
            font-weight: 600;
            font-size: 0.75rem;
            padding: 0.2rem 0.5rem;
            border-radius: 0.35rem;
            line-height: 1.2;
            white-space: nowrap;
        }
        .sof-label-env { background: #cfe2ff; color: #084298; border: 1px solid #9ec5fe; } /* Envelope — blue */
        .sof-label-std { background: #d1e7dd; color: #0f5132; border: 1px solid #a3cfbb; } /* Standard — green */
        .sof-label-osize { background: #f8d7da; color: #842029; border: 1px solid #f1aeb5; } /* O-Size — red */
        .sof-label-pallet { background: #e2d9f3; color: #432874; border: 1px solid #c5b3e6; }
        .sof-label-other { background: #e9ecef; color: #343a40; border: 1px solid #ced4da; }
        .sof-label-cell {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            justify-content: center;
        }
        .sof-label-dims-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #6c8cff;
            box-shadow: 0 0 0 2px rgba(108, 140, 255, 0.22);
            cursor: pointer;
            flex-shrink: 0;
            vertical-align: middle;
            border: none;
            padding: 0;
        }
        .sof-label-dims-dot:hover {
            box-shadow: 0 0 0 3px rgba(108, 140, 255, 0.4);
        }
        #sofLabelDimsModal .modal-dialog {
            max-width: min(1600px, 96vw);
            width: 96vw;
            margin: 1rem auto;
        }
        #sofLabelDimsModal .modal-header {
            padding: 1rem 1.5rem;
            background: #cfe2ff;
            border-bottom-color: #9ec5fe;
        }
        #sofLabelDimsModal .modal-title {
            font-size: 1.5rem;
            color: #084298;
        }
        #sofLabelDimsModal .modal-body {
            padding: 1.5rem 1.75rem;
            font-size: 1.15rem;
        }
        #sofLabelDimsModal .modal-footer {
            padding: 1rem 1.5rem;
        }
        #sofLabelDimsModal .modal-footer .btn {
            font-size: 1rem;
            padding: 0.5rem 1.25rem;
        }
        #sofLabelDimsModal .sof-label-sku-row {
            display: flex;
            align-items: flex-start;
            gap: 1.25rem;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
        }
        #sofLabelDimsModal .sof-label-type-right {
            margin-left: auto;
            text-align: right;
            font-size: 2rem;
            line-height: 1.3;
            white-space: nowrap;
            padding-top: 0.15rem;
        }
        #sofLabelDimsModal .sof-label-type-right .text-muted {
            font-size: 2rem;
            color: #64748b !important;
        }
        #sofLabelDimsModal #sof-label-dims-type {
            font-size: 2rem;
            font-weight: 700;
        }
        #sofLabelDimsModal .sof-label-sku-img {
            width: 120px;
            height: 120px;
            object-fit: contain;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            background: #f8f9fa;
            flex-shrink: 0;
        }
        #sofLabelDimsModal .sof-label-sku-img.is-missing {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #adb5bd;
            font-size: 0.85rem;
        }
        #sofLabelDimsModal .sof-label-sku-meta {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            min-width: 0;
            flex: 1;
        }
        #sofLabelDimsModal .sof-label-sku-line {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            flex-wrap: wrap;
        }
        #sofLabelDimsModal .sof-label-sku-label {
            color: #64748b;
            font-size: 1rem;
        }
        #sofLabelDimsModal #sof-label-dims-sku {
            font-size: 2.55rem;
            font-weight: 700;
            color: #0f766e;
            word-break: break-word;
            line-height: 1.2;
        }
        .sof-sku-cell {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            max-width: 100%;
        }
        .sof-sku-cell code {
            white-space: nowrap;
        }
        .sof-sku-copy {
            border: none;
            background: #f1f5f9;
            color: #475569;
            width: 26px;
            height: 26px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
            padding: 0;
            font-size: 0.75rem;
        }
        .sof-sku-copy:hover {
            background: #e2e8f0;
            color: #0f172a;
        }
        .sof-sku-copy.copied {
            background: #d1e7dd;
            color: #0f5132;
        }
        #sofLabelDimsModal .sof-sku-copy {
            width: 36px;
            height: 36px;
            font-size: 1rem;
        }
        #sofLabelDimsModal .sof-label-dims-table {
            font-size: 1.15rem;
            margin-bottom: 0;
        }
        #sofLabelDimsModal .sof-label-dims-table th,
        #sofLabelDimsModal .sof-label-dims-table thead th,
        #sofLabelDimsModal .sof-label-dims-table > :not(caption) > * > th {
            font-weight: 700;
            text-align: center;
            white-space: nowrap;
            font-size: 1rem;
            text-transform: lowercase;
            color: #000;
            vertical-align: middle;
            padding: 0.85rem 0.75rem;
            --bs-table-bg: #fffef2;
            --bs-table-accent-bg: #fffef2;
            background-color: #fffef2 !important;
        }
        #sofLabelDimsModal .sof-label-dims-table th.sof-dim-decl {
            --bs-table-bg: #f8d7da;
            --bs-table-accent-bg: #f8d7da;
            background-color: #f8d7da !important; /* pink — Decl columns only */
        }
        #sofLabelDimsModal .sof-label-dims-table td {
            text-align: center;
            vertical-align: middle;
            padding: 1rem 0.75rem;
            font-size: 1.25rem;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Sales Order Fulfillment',
        'sub_title'  => 'Active Channels',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-2">
                    <div id="sof-toolbar" class="mb-2">
                        {{-- Row 1: status badges + actions + platform badges --}}
                        <div id="sof-toolbar-row1" role="group" aria-label="Summary metrics and actions">
                            <span class="badge bg-primary sof-summary-badge" data-sof-metric="channel_count" style="color: white;" title="Active channels — click for history graph">
                                Channels: <span id="sof-channel-count">0</span><i class="sof-hist-dot" data-sof-metric="channel_count" style="background:#6c757d;" title="History trend"></i>
                            </span>
                            <span class="badge sof-summary-badge" id="sof-pending-total-badge" data-sof-metric="pending_total" style="background:#fff3cd; color:#856404; border:1px solid #ffe69c;" title="Pending — click for history graph">
                                Pending: <span id="sof-pending-total">0</span><i class="sof-hist-dot" data-sof-metric="pending_total" style="background:#6c757d;" title="History trend"></i>
                            </span>
                            <span class="badge sof-summary-badge" id="sof-fulfilled-24h-badge" data-sof-metric="fulfilled_24h" style="background:#d1e7dd; color:#0f5132; border:1px solid #a3cfbb;" title="Label Created / No Scan — click for history graph">
                                Label Created / No Scan: <span id="sof-fulfilled-24h">0</span><i class="sof-hist-dot" data-sof-metric="fulfilled_24h" style="background:#6c757d;" title="History trend"></i>
                            </span>
                            <span class="badge sof-summary-badge" id="sof-scan-done-24h-badge" data-sof-metric="scan_done_24h" style="background:#cfe2ff; color:#084298; border:1px solid #9ec5fe;" title="Shipped/Received — click for history graph">
                                Shipped/Received: <span id="sof-scan-done-24h">0</span><i class="sof-hist-dot" data-sof-metric="scan_done_24h" style="background:#6c757d;" title="History trend"></i>
                            </span>
                            <span class="badge sof-summary-badge" id="sof-in-transit-badge" data-sof-metric="in_transit_total" style="background:#ffe5d0; color:#9a3412; border:1px solid #fdba74;" title="In Transit — click for history graph">
                                In Transit: <span id="sof-in-transit-total">0</span><i class="sof-hist-dot" data-sof-metric="in_transit_total" style="background:#6c757d;" title="History trend"></i>
                            </span>
                            <span class="badge sof-summary-badge" id="sof-in-received-badge" data-sof-metric="in_received_total" style="background:#d1fae5; color:#065f46; border:1px solid #6ee7b7;" title="In Received — click for history graph">
                                In Received: <span id="sof-in-received-total">0</span><i class="sof-hist-dot" data-sof-metric="in_received_total" style="background:#6c757d;" title="History trend"></i>
                            </span>
                            <span class="badge sof-summary-badge" id="sof-invoiced-badge" data-sof-metric="invoiced_total" style="background:#e2d9f3; color:#432874; border:1px solid #c5b3e6;" title="Invoiced — click for history graph">
                                Invoiced: <span id="sof-invoiced-total">0</span><i class="sof-hist-dot" data-sof-metric="invoiced_total" style="background:#6c757d;" title="History trend"></i>
                            </span>
                            <span class="badge sof-summary-badge" id="sof-delivered-badge" data-sof-metric="delivered_total" style="background:#cff4fc; color:#055160; border:1px solid #9eeaf9;" title="Delivered — click for history graph">
                                Delivered: <span id="sof-delivered-total">0</span><i class="sof-hist-dot" data-sof-metric="delivered_total" style="background:#6c757d;" title="History trend"></i>
                            </span>
                            <span class="badge sof-summary-badge" id="sof-all-order-badge" data-sof-metric="all_order_total" style="background:#e9ecef; color:#343a40; border:1px solid #ced4da;" title="All Order — click for history graph">
                                All Order: <span id="sof-all-order-total">0</span><i class="sof-hist-dot" data-sof-metric="all_order_total" style="background:#6c757d;" title="History trend"></i>
                            </span>
                            <button type="button"
                                    id="sof-pull-tracking-btn"
                                    class="btn btn-sm btn-outline-secondary ms-1"
                                    title="Pull tracking for selected rows only (checkbox). If none selected, pulls a batch. Temu/Temu2 → Temu API; others → channel API (no Shopify).">
                                <i class="mdi mdi-barcode-scan me-1"></i>
                                <span class="sof-pull-tracking-label">Pull Tracking</span>
                            </button>
                            <button type="button"
                                    id="sof-refresh-shipment-btn"
                                    class="btn btn-sm btn-outline-primary"
                                    title="Refresh open shipment statuses via USPS / UPS APIs">
                                <i class="mdi mdi-truck-fast-outline me-1"></i>
                                <span class="sof-refresh-shipment-label">Update Status</span>
                            </button>
                            @foreach(($topBadges ?? []) as $badge)
                                @php
                                    $badgeKey = $badge['key'] ?? '';
                                    $badgeLabel = $badge['label'] ?? strtoupper($badgeKey);
                                    $badgeLink = $badge['link'] ?? null;
                                    $hasLink = !empty($badgeLink);
                                    $gofoApiReady = $badgeKey === 'gofo' && !empty($gofoApiConfigured);
                                    $veeqoApiReady = $badgeKey === 'veeqo' && !empty($veeqoApiConfigured);
                                    $apiReady = $gofoApiReady || $veeqoApiReady;
                                @endphp
                                <span class="sof-top-badge {{ $badgeKey }} {{ ($hasLink || $apiReady) ? '' : 'is-disabled' }} {{ $apiReady ? 'is-api-ready' : '' }}"
                                      data-badge-key="{{ $badgeKey }}"
                                      data-badge-label="{{ $badgeLabel }}"
                                      data-badge-link="{{ $badgeLink ?? '' }}"
                                      data-gofo-api="{{ $gofoApiReady ? '1' : '0' }}"
                                      data-veeqo-api="{{ $veeqoApiReady ? '1' : '0' }}"
                                      title="{{ $gofoApiReady ? 'Click to open GOFO API tools' : ($veeqoApiReady ? 'Veeqo API connected' : ($hasLink ? 'Click to open '.$badgeLabel : 'Add a link via the red dot')) }}">
                                    <span class="sof-top-badge-label">{{ $badgeLabel }}</span>
                                    <span class="sof-ch-orders-dot {{ ($hasLink || $apiReady) ? 'green' : 'red' }} sof-top-badge-dot"
                                          title="{{ $hasLink ? 'Double-click to edit link' : ($apiReady ? 'API connected' : 'Click to add link') }}"
                                          role="button"
                                          tabindex="0"></span>
                                </span>
                            @endforeach
                        </div>

                        {{-- Row 2: date / carrier / tracking / channels / SKU search / edit --}}
                        <div id="sof-toolbar-row2">
                            <div class="sof-filter-field">
                                <label class="visually-hidden" for="sof-date-from">From</label>
                                <input type="date" id="sof-date-from" class="form-control form-control-sm" style="width:140px;" title="From date (California)">
                            </div>
                            <div class="sof-filter-field">
                                <label class="visually-hidden" for="sof-date-to">To</label>
                                <input type="date" id="sof-date-to" class="form-control form-control-sm" style="width:140px;" title="To date (California)">
                            </div>
                            <div class="sof-filter-field">
                                <label class="visually-hidden" for="sof-carrier-filter">Carrier</label>
                                <select id="sof-carrier-filter" class="form-select form-select-sm" style="width:140px;" title="{{ !empty($veeqoApiConfigured) ? 'Carriers from Veeqo API + GOFO/Veeqo' : 'Carrier filter' }}">
                                    <option value="">All carriers</option>
                                    <option value="gofo">GOFO</option>
                                    @php
                                        $sofVeeqoCarriers = collect($veeqoCarriers ?? []);
                                        $sofDefaultCarriers = [
                                            ['key' => 'usps', 'label' => 'USPS'],
                                            ['key' => 'ups', 'label' => 'UPS'],
                                            ['key' => 'fedex', 'label' => 'FedEx'],
                                            ['key' => 'dhl', 'label' => 'DHL'],
                                            ['key' => 'amazon', 'label' => 'Amz'],
                                            ['key' => 'ontrac', 'label' => 'OnTrac'],
                                            ['key' => 'other', 'label' => 'Other'],
                                        ];
                                        $sofCarrierOptions = $sofVeeqoCarriers->isNotEmpty()
                                            ? $sofVeeqoCarriers->map(fn ($c) => [
                                                'key' => $c['key'] ?? '',
                                                'label' => $c['label'] ?? ($c['name'] ?? ''),
                                            ])->filter(fn ($c) => ($c['key'] ?? '') !== '' && ($c['key'] ?? '') !== 'gofo' && ($c['key'] ?? '') !== 'veeqo')->values()
                                            : collect($sofDefaultCarriers);
                                    @endphp
                                    @foreach($sofCarrierOptions as $carrierOpt)
                                        <option value="{{ $carrierOpt['key'] }}">{{ $carrierOpt['label'] }}</option>
                                    @endforeach
                                    <option value="veeqo">Veeqo{{ !empty($veeqoApiConfigured) ? ' ✓' : '' }}</option>
                                    @if($sofVeeqoCarriers->isNotEmpty() && ! $sofCarrierOptions->contains(fn ($c) => ($c['key'] ?? '') === 'other'))
                                        <option value="other">Other</option>
                                    @endif
                                    <option value="none">No carrier</option>
                                </select>
                            </div>
                            <div class="sof-filter-field">
                                <label class="visually-hidden" for="sof-tracking-filter">Tracking</label>
                                <select id="sof-tracking-filter" class="form-select form-select-sm" style="width:170px;" title="Filter by tracking number presence">
                                    <option value="">Tracking (0)</option>
                                    <option value="updated">Tracking Updated (0)</option>
                                    <option value="pending">Tracking Pending (0)</option>
                                </select>
                            </div>
                            <div class="sof-filter-field">
                                <label class="visually-hidden" for="sof-channel-filter">Channels</label>
                                <div class="input-group input-group-sm" style="width:150px;">
                                    <span class="input-group-text" title="Quick Search Channels"><i class="fas fa-search"></i></span>
                                    <input type="text" id="sof-channel-filter" class="form-control"
                                           list="sof-channel-datalist"
                                           placeholder="Channels…"
                                           autocomplete="off"
                                           title="Quick Search filter by channel">
                                </div>
                                <datalist id="sof-channel-datalist">
                                    @foreach(($sofChannels ?? []) as $chOpt)
                                        @if(($chOpt['slug'] ?? '') !== '')
                                            <option value="{{ $chOpt['label'] }}" data-slug="{{ $chOpt['slug'] }}"></option>
                                        @endif
                                    @endforeach
                                </datalist>
                            </div>
                            <div class="sof-filter-field flex-grow-1">
                                <label class="visually-hidden" for="sof-order-search">Search</label>
                                <input type="text" id="sof-order-search" class="form-control form-control-sm"
                                       placeholder="Search Channel, Order ID, SKU, Status…"
                                       autocomplete="off"
                                       title="Filter orders by Channel, Order ID, SKU, Status">
                            </div>
                            <button type="button" class="btn btn-sm btn-success" id="sof-bulk-edit-btn" disabled title="Edit selected rows (only changed fields are saved)">
                                <i class="mdi mdi-pencil-box-multiple-outline me-1"></i>
                                Edit (<span id="sof-bulk-edit-count">0</span>)
                            </button>
                            <div id="sof-date-filter-hint" title="Active filters">California dates (default: last 30 days PT)</div>
                        </div>
                    </div>
                    <ul class="nav nav-tabs mb-3" id="sof-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="sof-channels-tab" data-bs-toggle="tab"
                                    data-bs-target="#sof-channels-pane" type="button" role="tab"
                                    aria-controls="sof-channels-pane" aria-selected="true">
                                Channels
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="sof-all-order-tab" data-bs-toggle="tab"
                                    data-bs-target="#sof-all-order-pane" type="button" role="tab"
                                    aria-controls="sof-all-order-pane" aria-selected="false">
                                All Order <span class="badge ms-1" id="sof-all-order-tab-count" style="background:#e9ecef;color:#343a40;border:1px solid #ced4da;">0</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="sof-pending-tab" data-bs-toggle="tab"
                                    data-bs-target="#sof-pending-pane" type="button" role="tab"
                                    aria-controls="sof-pending-pane" aria-selected="false">
                                Pending <span class="badge ms-1" id="sof-pending-tab-count" style="background:#fff3cd;color:#856404;border:1px solid #ffe69c;">0</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="sof-fulfilled-tab" data-bs-toggle="tab"
                                    data-bs-target="#sof-fulfilled-pane" type="button" role="tab"
                                    aria-controls="sof-fulfilled-pane" aria-selected="false">
                                Label Created / No Scan <span class="badge ms-1" id="sof-fulfilled-tab-count" style="background:#d1e7dd;color:#0f5132;border:1px solid #a3cfbb;">0</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="sof-scan-done-tab" data-bs-toggle="tab"
                                    data-bs-target="#sof-scan-done-pane" type="button" role="tab"
                                    aria-controls="sof-scan-done-pane" aria-selected="false">
                                Shipped/Received <span class="badge ms-1" id="sof-scan-done-tab-count" style="background:#cfe2ff;color:#084298;border:1px solid #9ec5fe;">0</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="sof-in-transit-tab" data-bs-toggle="tab"
                                    data-bs-target="#sof-in-transit-pane" type="button" role="tab"
                                    aria-controls="sof-in-transit-pane" aria-selected="false">
                                In Transit <span class="badge ms-1" id="sof-in-transit-tab-count" style="background:#ffe5d0;color:#9a3412;border:1px solid #fdba74;">0</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="sof-in-received-tab" data-bs-toggle="tab"
                                    data-bs-target="#sof-in-received-pane" type="button" role="tab"
                                    aria-controls="sof-in-received-pane" aria-selected="false">
                                In Received <span class="badge ms-1" id="sof-in-received-tab-count" style="background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;">0</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="sof-invoiced-tab" data-bs-toggle="tab"
                                    data-bs-target="#sof-invoiced-pane" type="button" role="tab"
                                    aria-controls="sof-invoiced-pane" aria-selected="false">
                                Invoiced <span class="badge ms-1" id="sof-invoiced-tab-count" style="background:#e2d9f3;color:#432874;border:1px solid #c5b3e6;">0</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="sof-delivered-tab" data-bs-toggle="tab"
                                    data-bs-target="#sof-delivered-pane" type="button" role="tab"
                                    aria-controls="sof-delivered-pane" aria-selected="false">
                                Delivered <span class="badge ms-1" id="sof-delivered-tab-count" style="background:#cff4fc;color:#055160;border:1px solid #9eeaf9;" title="Selected date range">0</span>
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="sof-channels-pane" role="tabpanel" aria-labelledby="sof-channels-tab">
                            <div id="sof-filter-bar" class="mb-2">
                                <div class="d-flex flex-wrap align-items-end gap-2">
                                    <div class="flex-grow-1" style="min-width:200px;">
                                        <label class="sof-filter-label" for="sof-search">Search</label>
                                        <input type="text" id="sof-search" class="form-control form-control-sm" placeholder="Search by Channel or Alias...">
                                    </div>
                                </div>
                            </div>
                            <div id="sales-order-fulfillment-table" style="height: calc(100vh - 380px);"></div>
                        </div>

                        <div class="tab-pane fade" id="sof-all-order-pane" role="tabpanel" aria-labelledby="sof-all-order-tab">
                            <p class="small text-muted mb-2 sof-date-scope-hint">Marketplace orders in the selected date range, with original status values.</p>
                            <div id="sof-all-order-table" style="height: calc(100vh - 400px);"></div>
                        </div>

                        <div class="tab-pane fade" id="sof-pending-pane" role="tabpanel" aria-labelledby="sof-pending-tab">
                            <p class="small text-muted mb-2 sof-date-scope-hint">Pending / unfulfilled orders in the selected date range.</p>
                            <div id="sof-pending-table" style="height: calc(100vh - 400px);"></div>
                        </div>

                        <div class="tab-pane fade" id="sof-fulfilled-pane" role="tabpanel" aria-labelledby="sof-fulfilled-tab">
                            <p class="small text-muted mb-2 sof-date-scope-hint">Label Created / No Scan orders in the selected date range.</p>
                            <div id="sof-fulfilled-table" style="height: calc(100vh - 400px);"></div>
                        </div>

                        <div class="tab-pane fade" id="sof-scan-done-pane" role="tabpanel" aria-labelledby="sof-scan-done-tab">
                            <p class="small text-muted mb-2 sof-date-scope-hint">Shipped/Received orders in the selected date range.</p>
                            <div id="sof-scan-done-table" style="height: calc(100vh - 400px);"></div>
                        </div>

                        <div class="tab-pane fade" id="sof-in-transit-pane" role="tabpanel" aria-labelledby="sof-in-transit-tab">
                            <p class="small text-muted mb-2 sof-date-scope-hint">In Transit orders in the selected date range.</p>
                            <div id="sof-in-transit-table" style="height: calc(100vh - 400px);"></div>
                        </div>

                        <div class="tab-pane fade" id="sof-in-received-pane" role="tabpanel" aria-labelledby="sof-in-received-tab">
                            <p class="small text-muted mb-2 sof-date-scope-hint">In Received orders in the selected date range.</p>
                            <div id="sof-in-received-table" style="height: calc(100vh - 400px);"></div>
                        </div>

                        <div class="tab-pane fade" id="sof-invoiced-pane" role="tabpanel" aria-labelledby="sof-invoiced-tab">
                            <p class="small text-muted mb-2 sof-date-scope-hint">Invoiced orders in the selected date range.</p>
                            <div id="sof-invoiced-table" style="height: calc(100vh - 400px);"></div>
                        </div>

                        <div class="tab-pane fade" id="sof-delivered-pane" role="tabpanel" aria-labelledby="sof-delivered-tab">
                            <p class="small text-muted mb-2 sof-date-scope-hint">Delivered / Received in the selected date range (Faire DELIVERED, Shein &amp; Reverb Received, etc.).</p>
                            <div id="sof-delivered-table" style="height: calc(100vh - 400px);"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="sofChOrdersLinkModal" tabindex="-1" aria-labelledby="sofChOrdersLinkModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title fw-semibold mb-0" id="sofChOrdersLinkModalLabel">Add Ch Orders link</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="small text-muted mb-2">
                        <span id="sof-ch-orders-modal-channel">—</span>
                    </div>
                    <label class="form-label small mb-1" for="sof-ch-orders-modal-input">URL</label>
                    <input type="url" class="form-control form-control-sm" id="sof-ch-orders-modal-input"
                           placeholder="https://… or /path" autocomplete="off">
                    <div class="small text-muted mt-1">Leave blank and save to clear the link.</div>
                    <div class="small text-danger mt-2 d-none" id="sof-ch-orders-modal-error"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" id="sof-ch-orders-modal-save">Save</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="sofTopBadgeLinkModal" tabindex="-1" aria-labelledby="sofTopBadgeLinkModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title fw-semibold mb-0" id="sofTopBadgeLinkModalLabel">Add badge link</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="small text-muted mb-2">
                        <span id="sof-top-badge-modal-name">—</span>
                    </div>
                    <label class="form-label small mb-1" for="sof-top-badge-modal-input">URL</label>
                    <input type="url" class="form-control form-control-sm" id="sof-top-badge-modal-input"
                           placeholder="https://… or /path" autocomplete="off">
                    <div class="small text-muted mt-1">Leave blank and save to clear the link.</div>
                    <div class="small text-danger mt-2 d-none" id="sof-top-badge-modal-error"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" id="sof-top-badge-modal-save">Save</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="sofGofoToolsModal" tabindex="-1" aria-labelledby="sofGofoToolsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title fw-semibold mb-0" id="sofGofoToolsModalLabel">GOFO Express API</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <span class="badge" id="sof-gofo-conn-badge" style="background:#e9ecef;color:#495057;">Checking…</span>
                        <span class="small text-muted" id="sof-gofo-api-base">{{ $gofoApiBase ?? '' }}</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" id="sof-gofo-ping-btn">Test connection</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="sof-gofo-refresh-btn" title="Refresh open GOFO shipment statuses">Sync GOFO statuses</button>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="border rounded p-2 h-100">
                                <div class="fw-semibold small mb-2">Verify delivery ZIP</div>
                                <div class="row g-2">
                                    <div class="col-4">
                                        <input type="text" class="form-control form-control-sm" id="sof-gofo-zip-country" value="US" placeholder="Country">
                                    </div>
                                    <div class="col-8">
                                        <input type="text" class="form-control form-control-sm" id="sof-gofo-zip-code" placeholder="ZIP / postal code" value="90210">
                                    </div>
                                    <div class="col-6">
                                        <input type="text" class="form-control form-control-sm" id="sof-gofo-zip-state" placeholder="State" value="California">
                                    </div>
                                    <div class="col-6">
                                        <input type="text" class="form-control form-control-sm" id="sof-gofo-zip-city" placeholder="City" value="Los Angeles">
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-primary mt-2" id="sof-gofo-verify-btn">Check ZIP</button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-2 h-100">
                                <div class="fw-semibold small mb-2">Track waybill / order</div>
                                <input type="text" class="form-control form-control-sm" id="sof-gofo-track-no" placeholder="GOFO waybill or customer order no.">
                                <button type="button" class="btn btn-sm btn-primary mt-2" id="sof-gofo-track-btn">Track</button>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <div class="small text-muted mb-1">Result</div>
                        <div class="sof-gofo-result" id="sof-gofo-result">Open tools above to query GOFO.</div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="sofShipmentEditModal" tabindex="-1" aria-labelledby="sofShipmentEditModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title fw-semibold mb-0" id="sofShipmentEditModalLabel">Edit shipment</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="small text-muted mb-2" id="sof-ship-edit-target-hint">Editing 1 row</div>
                    <div class="alert alert-light border small py-2 mb-3" id="sof-ship-edit-dirty-hint">
                        Only fields you change are saved. Unchanged fields stay as-is on every selected row.
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1" for="sof-ship-edit-tracking">Tracking number</label>
                        <input type="text" class="form-control form-control-sm sof-ship-edit-field" id="sof-ship-edit-tracking" data-field="tracking_number" autocomplete="off">
                        <div class="form-text sof-ship-mixed-hint d-none" data-for="tracking_number">Mixed values — leave blank to keep each row’s current value.</div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1" for="sof-ship-edit-carrier">Carrier</label>
                        <input type="text" class="form-control form-control-sm sof-ship-edit-field" id="sof-ship-edit-carrier" data-field="tracking_company" list="sof-ship-edit-carrier-list" autocomplete="off" placeholder="GOFO, USPS, UPS, FedEx…">
                        <datalist id="sof-ship-edit-carrier-list">
                            <option value="GOFO"></option>
                            @forelse(($veeqoCarriers ?? []) as $vc)
                                <option value="{{ $vc['name'] ?? $vc['label'] ?? '' }}"></option>
                            @empty
                                <option value="USPS"></option>
                                <option value="UPS"></option>
                                <option value="FedEx"></option>
                                <option value="DHL"></option>
                                <option value="Amz"></option>
                                <option value="OnTrac"></option>
                            @endforelse
                            <option value="Veeqo"></option>
                        </datalist>
                        <div class="form-text sof-ship-mixed-hint d-none" data-for="tracking_company">Mixed values — leave blank to keep each row’s current value.</div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1" for="sof-ship-edit-status">Shipment status</label>
                        <select class="form-select form-select-sm sof-ship-edit-field" id="sof-ship-edit-status" data-field="shipment_status">
                            <option value="">— keep / choose —</option>
                            <option value="Pending">Pending</option>
                            <option value="InfoReceived">InfoReceived</option>
                            <option value="InTransit">InTransit</option>
                            <option value="OutForDelivery">OutForDelivery</option>
                            <option value="AvailableForPickup">AvailableForPickup</option>
                            <option value="Delivered">Delivered</option>
                            <option value="Exception">Exception</option>
                            <option value="DeliveryFailure">DeliveryFailure</option>
                            <option value="Expired">Expired</option>
                            <option value="NotFound">NotFound</option>
                        </select>
                        <div class="form-text sof-ship-mixed-hint d-none" data-for="shipment_status">Mixed values — leave as “keep” to skip this field.</div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small mb-1" for="sof-ship-edit-detail">Status detail</label>
                        <input type="text" class="form-control form-control-sm sof-ship-edit-field" id="sof-ship-edit-detail" data-field="shipment_status_detail" autocomplete="off">
                        <div class="form-text sof-ship-mixed-hint d-none" data-for="shipment_status_detail">Mixed values — leave blank to keep each row’s current value.</div>
                    </div>
                    <div class="small text-danger mt-2 d-none" id="sof-ship-edit-error"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" id="sof-ship-edit-save">Save changes</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="sofLabelDimsModal" tabindex="-1" aria-labelledby="sofLabelDimsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-semibold mb-0" id="sofLabelDimsModalLabel">Label details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="sof-label-sku-row">
                        <img id="sof-label-dims-img" class="sof-label-sku-img d-none" alt="SKU image" src="">
                        <div id="sof-label-dims-img-missing" class="sof-label-sku-img is-missing">No image</div>
                        <div class="sof-label-sku-meta">
                            <div class="sof-label-sku-line">
                                <span class="sof-label-sku-label">SKU:</span>
                                <code id="sof-label-dims-sku">—</code>
                                <button type="button" class="sof-sku-copy" id="sof-label-dims-sku-copy" title="Copy SKU" aria-label="Copy SKU">
                                    <i class="fas fa-copy" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                        <div class="sof-label-type-right">
                            <span class="text-muted">Type:</span>
                            <span id="sof-label-dims-type" class="ms-1">—</span>
                        </div>
                    </div>
                    <div class="mb-4">
                        <span class="fw-semibold">Label Qty:</span>
                        <span id="sof-label-dims-qty" class="ms-1">—</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered sof-label-dims-table">
                            <thead>
                                <tr>
                                    <th class="sof-dim-act">itm wt gw</th>
                                    <th class="sof-dim-act">item l in</th>
                                    <th class="sof-dim-act">item w in</th>
                                    <th class="sof-dim-act">item h in</th>
                                    <th class="sof-dim-decl">itm wt gw<br>decl</th>
                                    <th class="sof-dim-decl">item l in<br>decl</th>
                                    <th class="sof-dim-decl">item w in<br>decl</th>
                                    <th class="sof-dim-decl">item h in<br>decl</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td id="sof-label-dims-wt-act">—</td>
                                    <td id="sof-label-dims-l">—</td>
                                    <td id="sof-label-dims-w">—</td>
                                    <td id="sof-label-dims-h">—</td>
                                    <td id="sof-label-dims-wt-decl">—</td>
                                    <td id="sof-label-dims-l-decl">—</td>
                                    <td id="sof-label-dims-w-decl">—</td>
                                    <td id="sof-label-dims-h-decl">—</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- History graph (same idea as Active Channel Master) --}}
    <div class="modal fade" id="sofHistoryChartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="overflow: hidden;">
                <div class="modal-header bg-info text-white py-2 px-3">
                    <h6 class="modal-title mb-0" style="font-size: 13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span id="sofHistoryChartTitle">SOF History</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="sofHistoryChartRange" class="form-select form-select-sm bg-white" style="width: 110px; height: 26px; font-size: 11px; padding: 1px 8px;">
                            <option value="7">7 Days</option>
                            <option value="30" selected>30 Days</option>
                            <option value="60">60 Days</option>
                            <option value="90">90 Days</option>
                            <option value="0">All</option>
                        </select>
                        <button type="button" class="btn-close btn-close-white" style="font-size: 10px;" data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <div class="modal-body p-2">
                    <div id="sofHistoryChartContainer">
                        <div style="flex: 1; min-width: 0; position: relative;">
                            <canvas id="sofHistoryChart"></canvas>
                        </div>
                        <div style="width: 100px; display: flex; flex-direction: column; justify-content: center; gap: 8px; padding: 6px 8px; border-left: 1px solid #e9ecef; background: #f8f9fa;">
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; color: #dc3545;">Highest</div>
                                <div id="sofHistoryHighest" style="font-size: 13px; font-weight: 700; color: #dc3545;">-</div>
                            </div>
                            <div style="text-align: center; border-top: 1px dashed #adb5bd; border-bottom: 1px dashed #adb5bd; padding: 4px 0;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; color: #6c757d;">Median</div>
                                <div id="sofHistoryMedian" style="font-size: 13px; font-weight: 700; color: #6c757d;">-</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; color: #198754;">Lowest</div>
                                <div id="sofHistoryLowest" style="font-size: 13px; font-weight: 700; color: #198754;">-</div>
                            </div>
                        </div>
                    </div>
                    <div id="sofHistoryChartLoading" class="text-center py-3" style="display: none;">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <p class="mt-1 text-muted small mb-0">Loading history…</p>
                    </div>
                    <div id="sofHistoryChartNoData" class="text-center py-3" style="display: none;">
                        <p class="text-muted small mb-0">No daily history yet. Snapshots save automatically at 00:00 PST.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    let table = null;
    let allRows = [];
    let pendingTable = null;
    let pendingRows = [];
    let pendingTableLoaded = false;
    let pendingTableLoading = false;
    let fulfilledTable = null;
    let fulfilledRows = [];
    let fulfilledTableLoaded = false;
    let fulfilledTableLoading = false;
    let scanDoneTable = null;
    let scanDoneRows = [];
    let scanDoneTableLoaded = false;
    let scanDoneTableLoading = false;
    let inTransitTable = null;
    let inTransitRows = [];
    let inTransitTableLoaded = false;
    let inTransitTableLoading = false;
    let inReceivedTable = null;
    let inReceivedRows = [];
    let inReceivedTableLoaded = false;
    let inReceivedTableLoading = false;
    let invoicedTable = null;
    let invoicedRows = [];
    let invoicedTableLoaded = false;
    let invoicedTableLoading = false;
    let deliveredTable = null;
    let deliveredRows = [];
    let deliveredTableLoaded = false;
    let deliveredTableLoading = false;
    let allOrderTable = null;
    let allOrderRows = [];
    let allOrderTableLoaded = false;
    let allOrderTableLoading = false;

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /** Ajax returns full arrays — keep sort/filter/page client-side (server ignores sort params). */
    const sofLocalTableOpts = {
        pagination: true,
        paginationMode: 'local',
        sortMode: 'local',
        filterMode: 'local',
        paginationSize: 50,
        paginationSizeSelector: [25, 50, 100, true],
        movableColumns: false,
        headerSortClickElement: 'header',
    };

    /** Explicit select-all (built-in header tick is broken by vertical-header CSS). */
    function sofSelectAllTitleFormatter(cell) {
        const table = cell.getTable();
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.className = 'sof-select-all-cb';
        checkbox.title = 'Select all filtered rows';
        checkbox.setAttribute('aria-label', 'Select all filtered rows');

        const syncHeader = function () {
            try {
                const active = table.getRows('active') || [];
                let selectedActive = 0;
                active.forEach(function (r) {
                    try {
                        if (r.isSelected()) selectedActive += 1;
                    } catch (e) {}
                });
                checkbox.checked = active.length > 0 && selectedActive === active.length;
                checkbox.indeterminate = selectedActive > 0 && selectedActive < active.length;
            } catch (e) {
                checkbox.checked = false;
                checkbox.indeterminate = false;
            }
        };

        checkbox.addEventListener('click', function (e) {
            e.stopPropagation();
        });
        checkbox.addEventListener('change', function (e) {
            e.stopPropagation();
            try {
                if (checkbox.checked) {
                    // Filtered/active rows only (respects search + Tracking/Carrier filters).
                    table.selectRow('active');
                } else {
                    table.deselectRow();
                }
            } catch (err) {
                try {
                    const rows = table.getRows('active') || [];
                    if (checkbox.checked) {
                        rows.forEach(function (r) { try { r.select(); } catch (e2) {} });
                    } else {
                        table.deselectRow();
                    }
                } catch (err2) {}
            }
            syncHeader();
            sofUpdateBulkEditButton();
        });

        // Keep header tick in sync with manual row clicks / filters.
        if (!table._sofSelectAllSync) {
            table._sofSelectAllSync = true;
            table.on('rowSelectionChanged', syncHeader);
            table.on('dataFiltered', syncHeader);
            table.on('pageLoaded', syncHeader);
        }
        setTimeout(syncHeader, 0);
        return checkbox;
    }

    /** Order tabs: row checkboxes + Edit / bulk partial update. */
    const sofOrderTableOpts = Object.assign({}, sofLocalTableOpts, {
        selectableRows: true,
        // Tabulator 6: header tickbox belongs on rowHeader (not a normal column).
        rowHeader: {
            formatter: 'rowSelection',
            titleFormatter: sofSelectAllTitleFormatter,
            headerSort: false,
            resizable: false,
            frozen: true,
            headerHozAlign: 'center',
            hozAlign: 'center',
            width: 44,
            minWidth: 44,
        },
    });

    /** California (America/Los_Angeles) calendar helpers — never use browser local TZ. */
    const SOF_TZ = 'America/Los_Angeles';

    function sofCaliforniaTodayYmd() {
        try {
            return new Intl.DateTimeFormat('en-CA', {
                timeZone: SOF_TZ,
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
            }).format(new Date()); // YYYY-MM-DD
        } catch (e) {
            const d = new Date();
            return d.toISOString().slice(0, 10);
        }
    }

    function sofShiftYmd(ymd, deltaDays) {
        const parts = String(ymd || '').split('-').map(Number);
        if (parts.length !== 3 || parts.some(function (n) { return !Number.isFinite(n); })) {
            return sofCaliforniaTodayYmd();
        }
        const dt = new Date(Date.UTC(parts[0], parts[1] - 1, parts[2]));
        dt.setUTCDate(dt.getUTCDate() + deltaDays);
        return dt.toISOString().slice(0, 10);
    }

    function sofDefaultDateFrom() {
        return sofShiftYmd(sofCaliforniaTodayYmd(), -30);
    }

    function sofDefaultDateTo() {
        return sofCaliforniaTodayYmd();
    }

    function sofInitDateFilterDefaults() {
        const fromEl = document.getElementById('sof-date-from');
        const toEl = document.getElementById('sof-date-to');
        if (fromEl && !fromEl.value) fromEl.value = sofDefaultDateFrom();
        if (toEl && !toEl.value) toEl.value = sofDefaultDateTo();
        sofUpdateDateFilterHint();
    }

    function sofDateParams() {
        const fromEl = document.getElementById('sof-date-from');
        const toEl = document.getElementById('sof-date-to');
        let from = fromEl ? (fromEl.value || '') : '';
        let to = toEl ? (toEl.value || '') : '';
        if (!from) from = sofDefaultDateFrom();
        if (!to) to = sofDefaultDateTo();
        return {
            date_from: from,
            date_to: to,
            tz: SOF_TZ,
        };
    }

    function sofCarrierFilterValue() {
        const el = document.getElementById('sof-carrier-filter');
        return el ? String(el.value || '').trim().toLowerCase() : '';
    }

    function sofTrackingFilterValue() {
        const el = document.getElementById('sof-tracking-filter');
        return el ? String(el.value || '').trim().toLowerCase() : '';
    }

    const SOF_CHANNEL_OPTIONS = @json($sofChannels ?? []);

    function sofChannelFilterValue() {
        const el = document.getElementById('sof-channel-filter');
        return el ? String(el.value || '').trim() : '';
    }

    /** Resolve Quick Search Channels input to mm_slug when it matches a known label/slug. */
    function sofResolvedChannelSlug() {
        const raw = sofChannelFilterValue();
        if (!raw) return '';
        const q = raw.toLowerCase();
        for (let i = 0; i < SOF_CHANNEL_OPTIONS.length; i++) {
            const slug = String(SOF_CHANNEL_OPTIONS[i].slug || '').toLowerCase();
            const label = String(SOF_CHANNEL_OPTIONS[i].label || '').toLowerCase();
            if (q === slug || q === label) {
                return slug;
            }
        }
        return '';
    }

    function sofRowMatchesChannel(data) {
        const raw = sofChannelFilterValue();
        if (!raw) return true;
        const slug = sofResolvedChannelSlug();
        if (slug) {
            return String(data && data.mm_slug || '').toLowerCase() === slug;
        }
        const q = raw.toLowerCase();
        return String(data && data.channel_label || '').toLowerCase().includes(q)
            || String(data && data.mm_slug || '').toLowerCase().includes(q)
            || String(data && data.channel || '').toLowerCase().includes(q)
            || String(data && data.alias || '').toLowerCase().includes(q);
    }

    /** Same rule as Tracking column dots: green = has number, red = missing. */
    function sofRowHasTracking(data) {
        if (!data || typeof data !== 'object') return false;
        return String(data.tracking_number || '').trim() !== '';
    }

    function sofCarrierKeyFromName(name) {
        const n = String(name || '').trim().toLowerCase();
        if (!n) return 'none';
        if (n.includes('gofo')) return 'gofo';
        if (n.includes('usps') || n.includes('united states postal')) return 'usps';
        if (n.includes('ups') && !n.includes('usps')) return 'ups';
        if (n.includes('fedex') || n.includes('federal express')) return 'fedex';
        if (n.includes('dhl')) return 'dhl';
        if (n.includes('amazon') || n.includes('amzl')) return 'amazon';
        if (n.includes('ontrac') || n.includes('on trac')) return 'ontrac';
        if (n.includes('uniuni')) return 'uniuni';
        if (n.includes('veeqo')) return 'veeqo';
        return 'other';
    }

    function guessCarrierFromTrackingNumber(tn) {
        const n = String(tn || '').replace(/\s+/g, '').toUpperCase();
        if (!n) return '';
        if (n.indexOf('1Z') === 0) return 'UPS';
        if (n.indexOf('TBA') === 0) return 'Amazon';
        if (/^(UN|UU|UNI)[A-Z0-9]{6,}$/.test(n)) return 'UniUni';
        if (/^GF[A-Z0-9]{6,}$/.test(n)) return 'GOFO';
        if (/^(JD|GM)\d{10,}$/.test(n) || /^3S[A-Z0-9]{8,}$/.test(n)) return 'DHL';
        if (/^[CD]\d{14,15}$/.test(n)) return 'OnTrac';
        if (/^(94|93|92|95|96|91)\d{18,22}$/.test(n)) return 'USPS';
        if (/^[A-Z]{2}\d{9}[A-Z]{2}$/.test(n)) return 'USPS';
        if (/^\d{12}$/.test(n) || /^96\d{13}$/.test(n)) return 'FedEx';
        if (/^\d{10}$/.test(n)) return 'DHL';
        return '';
    }

    function sofRowMatchesCarrier(data) {
        const selected = sofCarrierFilterValue();
        if (!selected) return true;
        return sofCarrierKeyFromName(data && data.tracking_company) === selected;
    }

    function sofRowMatchesTracking(data) {
        const selected = sofTrackingFilterValue();
        if (!selected) return true; // Tracking = ALL
        if (selected === 'updated') return sofRowHasTracking(data);
        if (selected === 'pending') return !sofRowHasTracking(data);
        return true;
    }

    function sofOrderSearchMatches(data, q) {
        if (!q) return true;
        return String(data.channel_label || '').toLowerCase().includes(q)
            || String(data.order_id || '').toLowerCase().includes(q)
            || String(data.sku || '').toLowerCase().includes(q)
            || String(data.status_label || data.status || '').toLowerCase().includes(q)
            || String(data.tracking_number || '').toLowerCase().includes(q)
            || String(data.tracking_company || '').toLowerCase().includes(q)
            || String(data.display_title || '').toLowerCase().includes(q);
    }

    function sofApplyOrderTableFilter(tbl, searchSelector) {
        if (!tbl) return;
        const q = ($(searchSelector).val() || '').trim().toLowerCase();
        const carrier = sofCarrierFilterValue();
        const tracking = sofTrackingFilterValue();
        const channel = sofChannelFilterValue();
        if (!q && !carrier && !tracking && !channel) {
            tbl.clearFilter(true);
        } else {
            tbl.setFilter(function (data) {
                return sofOrderSearchMatches(data, q)
                    && sofRowMatchesCarrier(data)
                    && sofRowMatchesTracking(data)
                    && sofRowMatchesChannel(data);
            });
        }
        // Refresh Tracking badge counts from unfiltered cache for the visible table.
        if (sofActiveOrderTable() === tbl) {
            const cached = sofRowsForTable(tbl);
            sofUpdateTrackingFilterCounts(Array.isArray(cached) ? cached : sofCachedRowsForActiveOrderTab());
        }
    }

    function sofApplyAllCarrierFilters() {
        applyPendingFilters();
        applyFulfilledFilters();
        applyScanDoneFilters();
        applyInTransitFilters();
        applyInReceivedFilters();
        applyInvoicedFilters();
        applyDeliveredFilters();
        applyAllOrderFilters();
        applyFilters();
        sofUpdateTrackingFilterCounts();
    }

    function sofOrderTabIsActive(tabSelector, paneSelector) {
        const tab = document.querySelector(tabSelector);
        const pane = paneSelector ? document.querySelector(paneSelector) : null;
        // Prefer visible pane — tab buttons can keep stale aria-selected.
        if (pane && pane.classList.contains('active') && pane.classList.contains('show')) {
            return true;
        }
        if (pane && pane.classList.contains('active') && !pane.classList.contains('fade')) {
            return true;
        }
        if (tab && tab.classList.contains('active') && tab.getAttribute('aria-selected') === 'true') {
            return true;
        }
        return false;
    }

    /** Unfiltered row cache for the visible order tab (Tracking column source). */
    function sofCachedRowsForActiveOrderTab() {
        const pairs = [
            ['#sof-pending-tab', '#sof-pending-pane', function () { return pendingRows; }],
            ['#sof-fulfilled-tab', '#sof-fulfilled-pane', function () { return fulfilledRows; }],
            ['#sof-scan-done-tab', '#sof-scan-done-pane', function () { return scanDoneRows; }],
            ['#sof-in-transit-tab', '#sof-in-transit-pane', function () { return inTransitRows; }],
            ['#sof-in-received-tab', '#sof-in-received-pane', function () { return inReceivedRows; }],
            ['#sof-invoiced-tab', '#sof-invoiced-pane', function () { return invoicedRows; }],
            ['#sof-delivered-tab', '#sof-delivered-pane', function () { return deliveredRows; }],
            ['#sof-all-order-tab', '#sof-all-order-pane', function () { return allOrderRows; }],
        ];
        for (let i = 0; i < pairs.length; i++) {
            if (sofOrderTabIsActive(pairs[i][0], pairs[i][1])) {
                const rows = pairs[i][2]();
                return Array.isArray(rows) ? rows : [];
            }
        }
        return [];
    }

    function sofRowsForTable(tbl) {
        if (!tbl) return null;
        if (tbl === pendingTable) return pendingRows;
        if (tbl === fulfilledTable) return fulfilledRows;
        if (tbl === scanDoneTable) return scanDoneRows;
        if (tbl === inTransitTable) return inTransitRows;
        if (tbl === inReceivedTable) return inReceivedRows;
        if (tbl === invoicedTable) return invoicedRows;
        if (tbl === deliveredTable) return deliveredRows;
        if (tbl === allOrderTable) return allOrderRows;
        return null;
    }

    function sofAnyLoadedOrderRows() {
        const caches = [
            pendingRows, fulfilledRows, scanDoneRows, inTransitRows,
            inReceivedRows, invoicedRows, deliveredRows, allOrderRows,
        ];
        let best = [];
        for (let i = 0; i < caches.length; i++) {
            if (Array.isArray(caches[i]) && caches[i].length > best.length) {
                best = caches[i];
            }
        }
        return best;
    }

    function sofUpdateTrackingFilterCounts(rows) {
        const el = document.getElementById('sof-tracking-filter');
        if (!el || !el.options || el.options.length < 3) return;
        // Prefer explicit rows, then active-tab cache, then any loaded order rows.
        let list = Array.isArray(rows) ? rows : sofCachedRowsForActiveOrderTab();
        if (!Array.isArray(list) || !list.length) {
            list = sofAnyLoadedOrderRows();
        }
        if (!Array.isArray(list)) list = [];
        let updated = 0;
        let pending = 0;
        for (let i = 0; i < list.length; i++) {
            if (sofRowHasTracking(list[i])) updated += 1;
            else pending += 1;
        }
        const total = list.length;
        const fmt = function (n) { return Number(n || 0).toLocaleString(); };
        el.options[0].text = 'Tracking (' + fmt(total) + ')';
        el.options[1].text = 'Tracking Updated (' + fmt(updated) + ')';
        el.options[2].text = 'Tracking Pending (' + fmt(pending) + ')';
    }

    function sofUpdateDateFilterHint() {
        const p = sofDateParams();
        const carrier = sofCarrierFilterValue();
        const carrierLabel = carrier
            ? (($('#sof-carrier-filter option:selected').text() || carrier))
            : 'All carriers';
        const tracking = sofTrackingFilterValue();
        const trackingLabel = tracking
            ? (($('#sof-tracking-filter option:selected').text() || tracking))
            : 'Tracking (all)';
        const channel = sofChannelFilterValue();
        const channelLabel = channel ? ('Channels: ' + channel) : 'All channels';
        const hint = document.getElementById('sof-date-filter-hint');
        if (!hint) return;
        let text = (p.date_from && p.date_to)
            ? ('California dates ' + p.date_from + ' → ' + p.date_to)
            : 'Order date range (California, default: last 30 days)';
        text += ' · ' + carrierLabel + ' · ' + trackingLabel + ' · ' + channelLabel;
        hint.textContent = text;
    }

    function sofReloadAjaxTable(t) {
        if (!t) return;
        try {
            // Prefer setData() so Tabulator re-hits ajaxURL with current sofDateParams().
            if (typeof t.setData === 'function') {
                t.setData();
                return;
            }
        } catch (e) {}
        try {
            if (typeof t.replaceData === 'function') {
                t.replaceData();
            }
        } catch (e2) {}
    }

    function sofReloadAllTablesForDateRange() {
        sofUpdateDateFilterHint();
        [table, pendingTable, fulfilledTable, scanDoneTable, inTransitTable, inReceivedTable, invoicedTable, deliveredTable, allOrderTable]
            .forEach(sofReloadAjaxTable);
        // Carrier is client-side; re-apply after reload starts completing via dataLoaded.
        sofApplyAllCarrierFilters();
    }

    sofInitDateFilterDefaults();

    function sofApplyDateFilterFromInputs() {
        const from = ($('#sof-date-from').val() || '').trim() || sofDefaultDateFrom();
        const to = ($('#sof-date-to').val() || '').trim() || sofDefaultDateTo();
        $('#sof-date-from').val(from);
        $('#sof-date-to').val(to);
        if (from > to) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'warning', title: 'Invalid dates', text: 'From date must be on or before To date (California).' });
            } else {
                alert('From date must be on or before To date (California).');
            }
            return;
        }
        sofReloadAllTablesForDateRange();
    }

    $('#sof-date-from, #sof-date-to').on('change', sofApplyDateFilterFromInputs);

    $('#sof-carrier-filter').on('change', function () {
        sofUpdateDateFilterHint();
        sofApplyAllCarrierFilters();
    });

    $('#sof-tracking-filter').on('change', function () {
        sofApplyAllCarrierFilters();
        sofUpdateTrackingFilterCounts();
        sofUpdateDateFilterHint();
    });

    let sofChannelFilterTimer = null;
    $('#sof-channel-filter').on('input change', function () {
        sofUpdateDateFilterHint();
        if (sofChannelFilterTimer) clearTimeout(sofChannelFilterTimer);
        sofChannelFilterTimer = setTimeout(function () {
            sofApplyAllCarrierFilters();
        }, 150);
    });

    // ── Row select + Edit / bulk partial update ─────────────────────────────
    let sofShipEditCtx = null; // { table, rows, baseline, mixed }

    function sofOrderTablePairs() {
        return [
            ['#sof-pending-tab', '#sof-pending-pane', pendingTable],
            ['#sof-fulfilled-tab', '#sof-fulfilled-pane', fulfilledTable],
            ['#sof-scan-done-tab', '#sof-scan-done-pane', scanDoneTable],
            ['#sof-in-transit-tab', '#sof-in-transit-pane', inTransitTable],
            ['#sof-in-received-tab', '#sof-in-received-pane', inReceivedTable],
            ['#sof-invoiced-tab', '#sof-invoiced-pane', invoicedTable],
            ['#sof-delivered-tab', '#sof-delivered-pane', deliveredTable],
            ['#sof-all-order-tab', '#sof-all-order-pane', allOrderTable],
        ];
    }

    function sofActiveOrderTable() {
        const pairs = sofOrderTablePairs();
        for (let i = 0; i < pairs.length; i++) {
            if (sofOrderTabIsActive(pairs[i][0], pairs[i][1])) {
                return pairs[i][2];
            }
        }
        // Fallback: any order pane that is currently displayed.
        for (let i = 0; i < pairs.length; i++) {
            const pane = document.querySelector(pairs[i][1]);
            if (pane && pane.offsetParent !== null && pairs[i][2]) {
                return pairs[i][2];
            }
        }
        return null;
    }

    function sofTableSelectedData(tbl) {
        if (!tbl) return [];
        try {
            if (typeof tbl.getSelectedData === 'function') {
                const data = tbl.getSelectedData() || [];
                if (data.length) return data;
            }
        } catch (e) {}
        try {
            if (typeof tbl.getSelectedRows === 'function') {
                return (tbl.getSelectedRows() || []).map(function (r) {
                    try { return r.getData(); } catch (e2) { return null; }
                }).filter(Boolean);
            }
        } catch (e) {}
        return [];
    }

    function sofUpdateBulkEditButton() {
        const n = sofSelectedPullTargets().length;
        const btn = document.getElementById('sof-bulk-edit-btn');
        const countEl = document.getElementById('sof-bulk-edit-count');
        if (countEl) countEl.textContent = String(n);
        if (btn) btn.disabled = n < 1;
        const pullBtn = document.getElementById('sof-pull-tracking-btn');
        if (pullBtn) {
            pullBtn.title = n > 0
                ? ('Pull tracking for ' + n + ' selected row(s) only')
                : 'Check row checkbox(es) first to pull only those orders. With none checked, pulls a batch of 40.';
        }
    }

    function sofWireOrderTable(tbl) {
        if (!tbl || tbl._sofSelWired) return;
        tbl._sofSelWired = true;
        tbl.on('rowSelectionChanged', function () {
            sofUpdateBulkEditButton();
        });
    }

    function sofFieldValuesAcrossRows(rows, field) {
        const vals = rows.map(function (r) {
            const v = r && r[field];
            return v == null ? '' : String(v).trim();
        });
        const uniq = Array.from(new Set(vals));
        return {
            mixed: uniq.length > 1,
            value: uniq.length === 1 ? uniq[0] : '',
        };
    }

    function openSofShipmentEdit(table, clickedRow) {
        if (!table || !clickedRow) return;
        const selected = sofTableSelectedData(table);
        const clickedId = clickedRow.id;
        const selectedIncludes = selected.some(function (r) { return r && r.id === clickedId; });
        let rows;
        if (selected.length > 1 && selectedIncludes) {
            rows = selected;
        } else if (selected.length === 1 && selectedIncludes) {
            rows = selected;
        } else {
            rows = [clickedRow];
        }

        const fields = ['tracking_number', 'tracking_company', 'shipment_status', 'shipment_status_detail'];
        const baseline = {};
        const mixed = {};
        fields.forEach(function (f) {
            const info = sofFieldValuesAcrossRows(rows, f);
            baseline[f] = info.value;
            mixed[f] = info.mixed;
        });

        sofShipEditCtx = { table: table, rows: rows, baseline: baseline, mixed: mixed };

        const title = document.getElementById('sofShipmentEditModalLabel');
        const hint = document.getElementById('sof-ship-edit-target-hint');
        if (title) title.textContent = rows.length > 1 ? ('Bulk edit (' + rows.length + ' rows)') : 'Edit shipment';
        if (hint) {
            hint.textContent = rows.length > 1
                ? ('Editing ' + rows.length + ' selected rows. Only fields you change are written.')
                : ('Editing order ' + (clickedRow.order_id || clickedRow.id || ''));
        }

        const err = document.getElementById('sof-ship-edit-error');
        if (err) {
            err.textContent = '';
            err.classList.add('d-none');
        }

        document.querySelectorAll('.sof-ship-edit-field').forEach(function (el) {
            const field = el.getAttribute('data-field');
            const isMixed = !!mixed[field];
            const hintEl = document.querySelector('.sof-ship-mixed-hint[data-for="' + field + '"]');
            if (hintEl) hintEl.classList.toggle('d-none', !isMixed);

            if (el.tagName === 'SELECT') {
                if (isMixed) {
                    el.value = '';
                } else {
                    el.value = baseline[field] || '';
                }
            } else {
                el.value = isMixed ? '' : (baseline[field] || '');
                el.placeholder = isMixed ? 'Keep existing (mixed)' : '';
            }
            el.dataset.sofBaseline = isMixed ? '__mixed__' : (baseline[field] || '');
        });

        const modalEl = document.getElementById('sofShipmentEditModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    }

    function sofCollectDirtyShipmentFields() {
        if (!sofShipEditCtx) return {};
        const dirty = {};
        document.querySelectorAll('.sof-ship-edit-field').forEach(function (el) {
            const field = el.getAttribute('data-field');
            const baseline = el.dataset.sofBaseline || '';
            let current = (el.value || '').trim();
            if (el.tagName === 'SELECT' && current === '') {
                // "keep" for status when blank
                return;
            }
            if (baseline === '__mixed__') {
                // Mixed: only apply if user entered a value
                if (current !== '') {
                    dirty[field] = current;
                }
                return;
            }
            if (current !== baseline) {
                dirty[field] = current;
            }
        });
        return dirty;
    }

    function sofSaveShipmentEdit() {
        if (!sofShipEditCtx || !sofShipEditCtx.rows || !sofShipEditCtx.rows.length) return;
        const fields = sofCollectDirtyShipmentFields();
        const err = document.getElementById('sof-ship-edit-error');
        if (Object.keys(fields).length === 0) {
            if (err) {
                err.textContent = 'Change at least one field before saving. Unchanged fields are skipped.';
                err.classList.remove('d-none');
            }
            return;
        }

        const rows = sofShipEditCtx.rows.map(function (r) {
            return {
                shopify_order_id: r.shopify_order_id || '',
                order_number: r.order_number || '',
                order_id: r.order_id || '',
                order_id_api: r.order_id_api || '',
                tracking_number: r.tracking_number || '',
            };
        });

        const saveBtn = document.getElementById('sof-ship-edit-save');
        if (saveBtn) saveBtn.disabled = true;
        if (err) {
            err.textContent = '';
            err.classList.add('d-none');
        }

        fetch('{{ route("sales.order.fulfillment.bulk.update.shipment") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': sofCsrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ rows: rows, fields: fields }),
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); })
            .then(function (res) {
                if (!res.ok || !res.json || !res.json.success) {
                    throw new Error((res.json && res.json.message) || 'Update failed.');
                }
                // Patch local table rows for immediate UI feedback
                const tbl = sofShipEditCtx.table;
                if (tbl) {
                    sofShipEditCtx.rows.forEach(function (r) {
                        const patch = Object.assign({ id: r.id }, fields);
                        try { tbl.updateData([patch]); } catch (e) {}
                    });
                }
                const modalEl = document.getElementById('sofShipmentEditModal');
                if (modalEl && typeof bootstrap !== 'undefined') {
                    bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                }
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Updated',
                        text: res.json.message || 'Saved.',
                        timer: 2200,
                        showConfirmButton: false,
                    });
                }
            })
            .catch(function (e) {
                if (err) {
                    err.textContent = e.message || 'Update failed.';
                    err.classList.remove('d-none');
                }
            })
            .finally(function () {
                if (saveBtn) saveBtn.disabled = false;
            });
    }

    $('#sof-ship-edit-save').on('click', sofSaveShipmentEdit);
    $('#sof-bulk-edit-btn').on('click', function () {
        const tbl = sofActiveOrderTable();
        if (!tbl) return;
        const selected = tbl.getSelectedData() || [];
        if (!selected.length) return;
        openSofShipmentEdit(tbl, selected[0]);
    });
    $('#sof-tabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function () {
        sofUpdateBulkEditButton();
        sofUpdateTrackingFilterCounts();
    });

    function sofIsEmptySortValue(v) {
        return v === null || v === undefined || String(v).trim() === '';
    }

    function sofStringSorter(a, b) {
        const aEmpty = sofIsEmptySortValue(a);
        const bEmpty = sofIsEmptySortValue(b);
        if (aEmpty && bEmpty) return 0;
        if (aEmpty) return -1;
        if (bEmpty) return 1;
        return String(a).toLowerCase().localeCompare(String(b).toLowerCase(), undefined, {
            numeric: true,
            sensitivity: 'base',
        });
    }

    function sofDateSorter(a, b) {
        const aEmpty = sofIsEmptySortValue(a);
        const bEmpty = sofIsEmptySortValue(b);
        if (aEmpty && bEmpty) return 0;
        if (aEmpty) return -1;
        if (bEmpty) return 1;
        const at = Date.parse(String(a).replace(' ', 'T'));
        const bt = Date.parse(String(b).replace(' ', 'T'));
        const aOk = !isNaN(at);
        const bOk = !isNaN(bt);
        if (!aOk && !bOk) return sofStringSorter(a, b);
        if (!aOk) return -1;
        if (!bOk) return 1;
        return at - bt;
    }

    function sofNormalizeOrderRows(rows) {
        if (!Array.isArray(rows)) return [];
        rows.forEach(function (row) {
            if (!row || typeof row !== 'object') return;
            if (row.tracking_number == null) row.tracking_number = '';
            else row.tracking_number = String(row.tracking_number).trim();
            if (row.tracking_company == null) row.tracking_company = '';
            else row.tracking_company = String(row.tracking_company).trim();
        });
        return rows;
    }

    function sofFormatDateCell(cell) {
        const v = cell.getValue();
        if (!v) return '—';
        try {
            const d = new Date(String(v).replace(' ', 'T'));
            if (!isNaN(d.getTime())) {
                return d.toLocaleString(undefined, {
                    month: 'short', day: '2-digit',
                    hour: '2-digit', minute: '2-digit',
                });
            }
        } catch (e) {}
        return escapeHtml(v);
    }

    /** Updated + Tracking + Carrier columns inserted after Date on order tabs. */
    function sofTrackingColumns() {
        return [
            {
                title: 'Updated',
                field: 'updated_at',
                minWidth: 140,
                headerHozAlign: 'center',
                headerSort: true,
                sorter: sofDateSorter,
                formatter: sofFormatDateCell,
            },
            {
                title: 'Tracking',
                field: 'tracking_number',
                minWidth: 70,
                width: 78,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: true,
                sorter: sofStringSorter,
                headerTooltip: 'Green = tracking number available · Red = missing',
                formatter: formatTrackingCell,
            },
            {
                title: 'Carrier',
                field: 'tracking_company',
                minWidth: 110,
                headerHozAlign: 'center',
                headerSort: true,
                sorter: sofStringSorter,
                headerTooltip: 'Carrier / tracking company for this tracking number',
                formatter: formatCarrierCell,
            },
        ];
    }

    function formatTrackingCell(cell) {
        const row = cell.getRow().getData() || {};
        const tracking = (cell.getValue() || row.tracking_number || '').toString().trim();
        const shipStatus = (row.shipment_status || '').toString().trim();
        const detail = (row.shipment_status_detail || '').toString().trim();
        const hasTracking = tracking !== '';
        const tipParts = [];
        if (hasTracking) tipParts.push(tracking);
        if (shipStatus) tipParts.push(shipStatus);
        if (detail) tipParts.push(detail);
        const tip = tipParts.length ? tipParts.join(' · ') : 'No tracking number';
        const color = hasTracking ? 'green' : 'red';
        const label = hasTracking ? 'Tracking available' : 'Tracking missing';
        return '<span class="sof-ch-orders-dot ' + color + '" title="' + escapeHtml(tip) + '" aria-label="' + escapeHtml(label) + '" style="cursor:default;"></span>';
    }

    function carrierBadgeClass(name) {
        const n = String(name || '').toLowerCase();
        if (!n) return 'sof-carrier-other';
        if (n.includes('usps') || n.includes('united states postal')) return 'sof-carrier-usps';
        if (n.includes('ups') || n.includes('united parcel')) return 'sof-carrier-ups';
        if (n.includes('fedex') || n.includes('federal express')) return 'sof-carrier-fedex';
        if (n.includes('dhl')) return 'sof-carrier-dhl';
        if (n.includes('amazon') || n.includes('amzl')) return 'sof-carrier-amazon';
        if (n.includes('gofo')) return 'sof-carrier-gofo';
        if (n.includes('ontrac') || n.includes('on trac')) return 'sof-carrier-ontrac';
        if (n.includes('uniuni')) return 'sof-carrier-other';
        if (n.includes('veeqo')) return 'sof-carrier-veeqo';
        return 'sof-carrier-other';
    }

    function formatCarrierBadgeHtml(name) {
        const v = String(name || '').trim();
        if (!v) return '<span class="sof-oc-missing">—</span>';
        const cls = carrierBadgeClass(v);
        return `<span class="sof-carrier-badge ${cls}" title="${escapeHtml(v)}">${escapeHtml(v)}</span>`;
    }

    function formatCarrierCell(cell) {
        let v = String(cell.getValue() || '').trim();
        if (!v) {
            const row = cell.getRow && cell.getRow() ? cell.getRow().getData() : null;
            v = guessCarrierFromTrackingNumber(row && row.tracking_number);
            if (v && row && typeof row === 'object') {
                row.tracking_company = v;
            }
        }
        return formatCarrierBadgeHtml(v);
    }

    function buildPulledTrackingTableHtml(rows) {
        const list = Array.isArray(rows) ? rows : [];
        if (!list.length) {
            return '<p class="text-muted mb-0" style="font-size:0.9rem;">No orders with tracking were returned.</p>';
        }
        let body = '';
        list.forEach(function (r) {
            body += '<tr>'
                + '<td style="padding:4px 6px;border-bottom:1px solid #e5e7eb;white-space:nowrap;">' + escapeHtml(r.order_number || r.shopify_order_id || '—') + '</td>'
                + '<td style="padding:4px 6px;border-bottom:1px solid #e5e7eb;"><code style="font-size:0.75rem;">' + escapeHtml(r.tracking_number || '—') + '</code></td>'
                + '<td style="padding:4px 6px;border-bottom:1px solid #e5e7eb;">' + formatCarrierBadgeHtml(r.tracking_company) + '</td>'
                + '<td style="padding:4px 6px;border-bottom:1px solid #e5e7eb;">' + escapeHtml(r.fulfillment_status || '—') + '</td>'
                + '<td style="padding:4px 6px;border-bottom:1px solid #e5e7eb;">' + escapeHtml(r.shipment_status || '—') + '</td>'
                + '<td style="padding:4px 6px;border-bottom:1px solid #e5e7eb;font-size:0.75rem;color:#64748b;">' + escapeHtml(r.note || '') + '</td>'
                + '</tr>';
        });
        return '<div style="max-height:360px;overflow:auto;border:1px solid #e5e7eb;border-radius:6px;">'
            + '<table style="width:100%;border-collapse:collapse;font-size:0.8rem;text-align:left;">'
            + '<thead><tr style="background:#f8fafc;position:sticky;top:0;">'
            + '<th style="padding:6px;">Order</th><th style="padding:6px;">Tracking</th><th style="padding:6px;">Carrier</th>'
            + '<th style="padding:6px;">Fulfillment</th><th style="padding:6px;">Ship status</th><th style="padding:6px;">Note</th>'
            + '</tr></thead><tbody>' + body + '</tbody></table></div>';
    }

    function reloadSofTrackingTables() {
        [
            fulfilledTable, inTransitTable, deliveredTable, scanDoneTable,
            pendingTable, inReceivedTable, invoicedTable, allOrderTable, table,
        ].forEach(function (t) {
            if (!t) return;
            try {
                if (typeof t.replaceData === 'function') t.replaceData();
                else if (typeof t.setData === 'function') t.setData();
            } catch (e) {}
        });
        sofApplyAllCarrierFilters();
    }

    /**
     * Patch only rows that received tracking from Pull Tracking — no full table reload.
     * Matches by order_number / order_id / order_id_api / shopify_order_id.
     * @returns {number} count of table cells updated
     */
    function sofApplyPulledTrackingToTables(pulledRows) {
        const list = Array.isArray(pulledRows) ? pulledRows : [];
        const byKey = {};
        list.forEach(function (r) {
            if (!r || typeof r !== 'object') return;
            const tn = String(r.tracking_number || '').trim();
            if (!tn) return;
            const carrier = String(r.tracking_company || '').trim() || guessCarrierFromTrackingNumber(tn);
            const patch = {
                tracking_number: tn,
                tracking_company: carrier,
            };
            ['order_number', 'shopify_order_id', 'order_id', 'order_id_api'].forEach(function (k) {
                const v = String(r[k] || '').trim().toLowerCase();
                if (v) byKey[v] = patch;
            });
        });
        if (!Object.keys(byKey).length) return 0;

        function lookupPatch(row) {
            if (!row || typeof row !== 'object') return null;
            const candidates = [
                row.order_number, row.order_id, row.order_id_api, row.shopify_order_id,
            ];
            for (let i = 0; i < candidates.length; i++) {
                const key = String(candidates[i] || '').trim().toLowerCase();
                if (key && byKey[key]) return byKey[key];
            }
            return null;
        }

        function patchCache(arr) {
            if (!Array.isArray(arr) || !arr.length) return arr;
            return arr.map(function (row) {
                const hit = lookupPatch(row);
                if (!hit) return row;
                return Object.assign({}, row, {
                    tracking_number: hit.tracking_number,
                    tracking_company: hit.tracking_company || row.tracking_company || '',
                });
            });
        }

        pendingRows = patchCache(pendingRows);
        fulfilledRows = patchCache(fulfilledRows);
        scanDoneRows = patchCache(scanDoneRows);
        inTransitRows = patchCache(inTransitRows);
        inReceivedRows = patchCache(inReceivedRows);
        invoicedRows = patchCache(invoicedRows);
        deliveredRows = patchCache(deliveredRows);
        allOrderRows = patchCache(allOrderRows);

        let updated = 0;
        [
            pendingTable, fulfilledTable, scanDoneTable, inTransitTable,
            inReceivedTable, invoicedTable, deliveredTable, allOrderTable,
        ].forEach(function (tbl) {
            if (!tbl || typeof tbl.getRows !== 'function') return;
            try {
                tbl.getRows().forEach(function (tabRow) {
                    const data = tabRow.getData() || {};
                    const hit = lookupPatch(data);
                    if (!hit) return;
                    try {
                        tabRow.update({
                            tracking_number: hit.tracking_number,
                            tracking_company: hit.tracking_company || data.tracking_company || '',
                        });
                        updated += 1;
                    } catch (e) {
                        try {
                            tbl.updateData([Object.assign({ id: data.id }, {
                                tracking_number: hit.tracking_number,
                                tracking_company: hit.tracking_company || data.tracking_company || '',
                            })]);
                            updated += 1;
                        } catch (e2) {}
                    }
                });
            } catch (e) {}
        });

        // Refresh filters / Tracking dropdown counts for the active tab only.
        const active = sofActiveOrderTable();
        if (active) {
            const searchMap = [
                [pendingTable, '#sof-order-search'],
                [fulfilledTable, '#sof-order-search'],
                [scanDoneTable, '#sof-order-search'],
                [inTransitTable, '#sof-order-search'],
                [inReceivedTable, '#sof-order-search'],
                [invoicedTable, '#sof-order-search'],
                [deliveredTable, '#sof-order-search'],
                [allOrderTable, '#sof-order-search'],
            ];
            for (let i = 0; i < searchMap.length; i++) {
                if (searchMap[i][0] === active) {
                    sofApplyOrderTableFilter(active, searchMap[i][1]);
                    break;
                }
            }
        }
        sofUpdateTrackingFilterCounts();
        sofUpdateDateFilterHint();
        return updated;
    }

    function sumPending(rows) {
        let pendingTotal = 0;
        (rows || []).forEach(function (row) {
            if (row && row.pending_count !== null && row.pending_count !== undefined) {
                pendingTotal += Number(row.pending_count) || 0;
            }
        });
        return pendingTotal;
    }

    function getVisibleRows() {
        if (!table) {
            return allRows;
        }
        try {
            // Table may not have ingested ajax data yet — don't treat [] as final.
            const tableData = table.getData();
            if (!Array.isArray(tableData) || tableData.length === 0) {
                return allRows;
            }
            const visible = table.getData('active');
            if (Array.isArray(visible)) {
                return visible;
            }
        } catch (e) {
            // fall back to allRows
        }
        return allRows;
    }

    function updateSummaryStats(rows) {
        const list = Array.isArray(rows) ? rows : getVisibleRows();
        const channelCount = list.length;
        const pendingTotal = sumPending(list);
        const channelEl = document.getElementById('sof-channel-count');
        const pendingEl = document.getElementById('sof-pending-total');
        const pendingTabCount = document.getElementById('sof-pending-tab-count');
        if (channelEl) {
            channelEl.textContent = Number(channelCount || 0).toLocaleString();
        }
        if (pendingEl) {
            pendingEl.textContent = Number(pendingTotal || 0).toLocaleString();
        }
        if (pendingTabCount && !pendingTableLoaded) {
            pendingTabCount.textContent = Number(pendingTotal || 0).toLocaleString();
        }
    }

    function switchToPendingTab() {
        const tabBtn = document.getElementById('sof-pending-tab');
        if (tabBtn && typeof bootstrap !== 'undefined') {
            bootstrap.Tab.getOrCreateInstance(tabBtn).show();
        } else if (tabBtn) {
            tabBtn.click();
        }
        ensurePendingTable();
    }

    function switchToFulfilledTab() {
        const tabBtn = document.getElementById('sof-fulfilled-tab');
        if (tabBtn && typeof bootstrap !== 'undefined') {
            bootstrap.Tab.getOrCreateInstance(tabBtn).show();
        } else if (tabBtn) {
            tabBtn.click();
        }
        ensureFulfilledTable();
    }

    function switchToScanDoneTab() {
        const tabBtn = document.getElementById('sof-scan-done-tab');
        if (tabBtn && typeof bootstrap !== 'undefined') {
            bootstrap.Tab.getOrCreateInstance(tabBtn).show();
        } else if (tabBtn) {
            tabBtn.click();
        }
        ensureScanDoneTable();
    }

    function switchToInTransitTab() {
        const tabBtn = document.getElementById('sof-in-transit-tab');
        if (tabBtn && typeof bootstrap !== 'undefined') {
            bootstrap.Tab.getOrCreateInstance(tabBtn).show();
        } else if (tabBtn) {
            tabBtn.click();
        }
        ensureInTransitTable();
    }

    function switchToInReceivedTab() {
        const tabBtn = document.getElementById('sof-in-received-tab');
        if (tabBtn && typeof bootstrap !== 'undefined') {
            bootstrap.Tab.getOrCreateInstance(tabBtn).show();
        } else if (tabBtn) {
            tabBtn.click();
        }
        ensureInReceivedTable();
    }

    function switchToInvoicedTab() {
        const tabBtn = document.getElementById('sof-invoiced-tab');
        if (tabBtn && typeof bootstrap !== 'undefined') {
            bootstrap.Tab.getOrCreateInstance(tabBtn).show();
        } else if (tabBtn) {
            tabBtn.click();
        }
        ensureInvoicedTable();
    }

    function switchToDeliveredTab() {
        const tabBtn = document.getElementById('sof-delivered-tab');
        if (tabBtn && typeof bootstrap !== 'undefined') {
            bootstrap.Tab.getOrCreateInstance(tabBtn).show();
        } else if (tabBtn) {
            tabBtn.click();
        }
        ensureDeliveredTable();
    }

    function switchToAllOrderTab() {
        const tabBtn = document.getElementById('sof-all-order-tab');
        if (tabBtn && typeof bootstrap !== 'undefined') {
            bootstrap.Tab.getOrCreateInstance(tabBtn).show();
        } else if (tabBtn) {
            tabBtn.click();
        }
        ensureAllOrderTable();
    }

    function formatChOrdersDot(cell) {
        const row = cell.getRow().getData();
        const link = (row.ch_orders_link || '').trim();
        // Channels tab uses row.id as channel_master id; order tabs use channel_id.
        const channelId = row.channel_id != null ? row.channel_id : row.id;
        const channelName = (row.channel_label || row.alias || row.channel || '').toString().trim();

        if (!channelId) {
            return '<span class="sof-oc-missing" title="No channel_master match">—</span>';
        }

        if (link) {
            const a = document.createElement('a');
            a.href = link;
            a.target = '_blank';
            a.rel = 'noopener noreferrer';
            a.className = 'sof-ch-orders-dot green';
            a.title = link + ' — double-click to edit';
            a.setAttribute('aria-label', 'Open Ch Orders link');
            a.addEventListener('click', function (ev) {
                ev.stopPropagation();
            });
            a.addEventListener('dblclick', function (ev) {
                ev.preventDefault();
                ev.stopPropagation();
                openChOrdersLinkModal(channelId, channelName, link);
            });
            return a;
        }

        const dot = document.createElement('span');
        dot.className = 'sof-ch-orders-dot red';
        dot.title = 'Double-click to add Ch Orders link';
        dot.setAttribute('aria-label', 'Add Ch Orders link');
        dot.addEventListener('dblclick', function (ev) {
            ev.preventDefault();
            ev.stopPropagation();
            openChOrdersLinkModal(channelId, channelName, '');
        });
        return dot;
    }

    function sofLabelDimsDisplay(v) {
        if (v === null || v === undefined || v === '') {
            return '—';
        }
        return String(v);
    }

    function copyTextToClipboard(text, btn) {
        const value = (text || '').toString();
        if (!value) {
            return;
        }
        const done = function () {
            if (!btn) return;
            btn.classList.add('copied');
            btn.innerHTML = '<i class="fas fa-check" aria-hidden="true"></i>';
            setTimeout(function () {
                btn.classList.remove('copied');
                btn.innerHTML = '<i class="fas fa-copy" aria-hidden="true"></i>';
            }, 1200);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(value).then(done).catch(function () {
                window.prompt('Copy:', value);
            });
        } else {
            window.prompt('Copy:', value);
            done();
        }
    }

    function openLabelDimsModal(row) {
        const modalEl = document.getElementById('sofLabelDimsModal');
        if (!modalEl || typeof bootstrap === 'undefined') {
            return;
        }
        const data = row || {};
        const sku = (data.sku || '').toString().trim();
        const skuEl = document.getElementById('sof-label-dims-sku');
        const typeEl = document.getElementById('sof-label-dims-type');
        const qtyEl = document.getElementById('sof-label-dims-qty');
        const imgEl = document.getElementById('sof-label-dims-img');
        const imgMissingEl = document.getElementById('sof-label-dims-img-missing');
        if (skuEl) skuEl.textContent = sku || '—';
        if (typeEl) typeEl.textContent = (data.label || '').toString().trim() || '—';
        if (qtyEl) qtyEl.textContent = sofLabelDimsDisplay(data.label_qty);

        const imageUrl = (data.sku_image || '').toString().trim();
        if (imgEl && imgMissingEl) {
            if (imageUrl) {
                imgEl.src = imageUrl;
                imgEl.alt = sku ? ('Image for ' + sku) : 'SKU image';
                imgEl.classList.remove('d-none');
                imgMissingEl.classList.add('d-none');
                imgEl.onerror = function () {
                    imgEl.classList.add('d-none');
                    imgEl.removeAttribute('src');
                    imgMissingEl.classList.remove('d-none');
                };
            } else {
                imgEl.classList.add('d-none');
                imgEl.removeAttribute('src');
                imgMissingEl.classList.remove('d-none');
            }
        }

        const map = {
            'sof-label-dims-wt-act': data.wt_act,
            'sof-label-dims-l': data.l,
            'sof-label-dims-w': data.w,
            'sof-label-dims-h': data.h,
            'sof-label-dims-wt-decl': data.wt_decl,
            'sof-label-dims-l-decl': data.l_decl,
            'sof-label-dims-w-decl': data.w_decl,
            'sof-label-dims-h-decl': data.h_decl,
        };
        Object.keys(map).forEach(function (id) {
            const el = document.getElementById(id);
            if (el) el.textContent = sofLabelDimsDisplay(map[id]);
        });

        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    document.getElementById('sof-label-dims-sku-copy')?.addEventListener('click', function (ev) {
        ev.preventDefault();
        const skuEl = document.getElementById('sof-label-dims-sku');
        const sku = (skuEl?.textContent || '').trim();
        if (!sku || sku === '—') {
            return;
        }
        copyTextToClipboard(sku, ev.currentTarget);
    });

    function syncChOrdersLinkAcrossTables(channelId, savedLink) {
        const id = Number(channelId);
        if (table) {
            table.getRows().forEach(function (r) {
                if (Number(r.getData().id) === id) {
                    r.update({ ch_orders_link: savedLink });
                }
            });
        }
        allRows = allRows.map(function (r) {
            if (Number(r.id) === id) {
                return Object.assign({}, r, { ch_orders_link: savedLink });
            }
            return r;
        });

        [pendingTable, fulfilledTable, scanDoneTable, inTransitTable, inReceivedTable, invoicedTable, deliveredTable, allOrderTable].forEach(function (t) {
            if (!t) return;
            t.getRows().forEach(function (r) {
                if (Number(r.getData().channel_id) === id) {
                    r.update({ ch_orders_link: savedLink });
                }
            });
        });
        pendingRows = pendingRows.map(function (r) {
            if (Number(r.channel_id) === id) {
                return Object.assign({}, r, { ch_orders_link: savedLink });
            }
            return r;
        });
        fulfilledRows = fulfilledRows.map(function (r) {
            if (Number(r.channel_id) === id) {
                return Object.assign({}, r, { ch_orders_link: savedLink });
            }
            return r;
        });
        scanDoneRows = scanDoneRows.map(function (r) {
            if (Number(r.channel_id) === id) {
                return Object.assign({}, r, { ch_orders_link: savedLink });
            }
            return r;
        });
        inTransitRows = inTransitRows.map(function (r) {
            if (Number(r.channel_id) === id) {
                return Object.assign({}, r, { ch_orders_link: savedLink });
            }
            return r;
        });
        inReceivedRows = inReceivedRows.map(function (r) {
            if (Number(r.channel_id) === id) {
                return Object.assign({}, r, { ch_orders_link: savedLink });
            }
            return r;
        });
        invoicedRows = invoicedRows.map(function (r) {
            if (Number(r.channel_id) === id) {
                return Object.assign({}, r, { ch_orders_link: savedLink });
            }
            return r;
        });
        deliveredRows = deliveredRows.map(function (r) {
            if (Number(r.channel_id) === id) {
                return Object.assign({}, r, { ch_orders_link: savedLink });
            }
            return r;
        });
        allOrderRows = allOrderRows.map(function (r) {
            if (Number(r.channel_id) === id) {
                return Object.assign({}, r, { ch_orders_link: savedLink });
            }
            return r;
        });
    }

    function formatChannelPctAlert(value, threshold, metricLabel) {
        if (value === null || value === undefined || value === '') {
            return '<span class="sof-oc-missing">—</span>';
        }
        const n = Number(value);
        if (!Number.isFinite(n)) {
            return '<span class="sof-oc-missing">—</span>';
        }
        const label = Math.round(n).toLocaleString(undefined, {
            maximumFractionDigits: 0,
        }) + '%';
        if (n < threshold) {
            return `<span class="sof-inv-cell">`
                + `<span>${escapeHtml(label)}</span>`
                + `<span class="sof-inv-zero-alert" title="Alert: Channel ${escapeHtml(metricLabel)} is below ${threshold}%">`
                + `<i class="fas fa-exclamation-triangle" aria-hidden="true"></i>`
                + `</span></span>`;
        }
        return `<span class="sof-inv-cell">${escapeHtml(label)}</span>`;
    }

    function orderListColumns(statusBadgeClass) {
        return [
            {
                title: 'Edit',
                field: '__sof_edit',
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: false,
                width: 70,
                minWidth: 70,
                frozen: true,
                formatter: function (cell) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'btn btn-sm btn-outline-primary py-0 px-2';
                    btn.textContent = 'Edit';
                    btn.title = 'Edit this row (or all selected rows)';
                    btn.addEventListener('click', function (ev) {
                        ev.preventDefault();
                        ev.stopPropagation();
                        openSofShipmentEdit(cell.getTable(), cell.getRow().getData());
                    });
                    return btn;
                },
            },
            {
                title: 'Channel',
                field: 'channel_label',
                minWidth: 100,
                headerHozAlign: 'center',
                headerSort: true,
                sorter: sofStringSorter,
                formatter: function (cell) {
                    const row = cell.getRow().getData();
                    const label = escapeHtml(cell.getValue() || '');
                    const url = (row.orders_url || '').trim();
                    if (url) {
                        return `<a href="${escapeHtml(url)}" target="_blank" class="sof-channel-name has-link">${label}</a>`;
                    }
                    return `<span class="sof-channel-name">${label}</span>`;
                },
            },
            {
                title: 'Ch Orders',
                field: 'ch_orders_link',
                minWidth: 70,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: false,
                headerTooltip: 'Same Ch Orders link as Channels tab. Double-click red dot to add/edit.',
                formatter: formatChOrdersDot,
            },
            {
                title: 'Order ID',
                field: 'order_id',
                minWidth: 70,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: true,
                sorter: sofStringSorter,
                headerTooltip: 'Hover green dot to see full Order ID and copy',
                formatter: function (cell) {
                    const row = cell.getRow().getData();
                    const orderId = (cell.getValue() || '').toString().trim();
                    if (!orderId) {
                        return '<span class="sof-oc-missing">—</span>';
                    }

                    const url = (row.order_url || '').trim();
                    const wrap = document.createElement('span');
                    wrap.className = 'sof-order-id-wrap';

                    const dot = document.createElement('span');
                    dot.className = 'sof-order-id-dot';
                    dot.setAttribute('aria-label', 'Order ID');
                    wrap.appendChild(dot);

                    const pop = document.createElement('span');
                    pop.className = 'sof-order-id-popover';

                    const text = document.createElement('span');
                    text.className = 'sof-order-id-text';
                    if (url) {
                        const a = document.createElement('a');
                        a.href = url;
                        a.target = '_blank';
                        a.rel = 'noopener noreferrer';
                        a.textContent = orderId;
                        a.addEventListener('click', function (ev) { ev.stopPropagation(); });
                        text.appendChild(a);
                    } else {
                        text.textContent = orderId;
                    }
                    pop.appendChild(text);

                    const copyBtn = document.createElement('button');
                    copyBtn.type = 'button';
                    copyBtn.className = 'sof-order-id-copy';
                    copyBtn.title = 'Copy Order ID';
                    copyBtn.innerHTML = '<i class="fas fa-copy" aria-hidden="true"></i>';
                    copyBtn.addEventListener('click', function (ev) {
                        ev.preventDefault();
                        ev.stopPropagation();
                        const done = function () {
                            copyBtn.classList.add('copied');
                            copyBtn.innerHTML = '<i class="fas fa-check" aria-hidden="true"></i>';
                            setTimeout(function () {
                                copyBtn.classList.remove('copied');
                                copyBtn.innerHTML = '<i class="fas fa-copy" aria-hidden="true"></i>';
                            }, 1200);
                        };
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(orderId).then(done).catch(function () {
                                window.prompt('Copy Order ID:', orderId);
                            });
                        } else {
                            window.prompt('Copy Order ID:', orderId);
                            done();
                        }
                    });
                    pop.appendChild(copyBtn);
                    wrap.appendChild(pop);

                    return wrap;
                },
            },
            {
                title: 'Date',
                field: 'order_date',
                minWidth: 110,
                headerHozAlign: 'center',
                headerSort: true,
                sorter: sofDateSorter,
                formatter: sofFormatDateCell,
            },
            {
                title: 'Status',
                field: 'status_label',
                minWidth: 90,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: true,
                sorter: sofStringSorter,
                formatter: function (cell) {
                    const label = escapeHtml(cell.getValue() || cell.getRow().getData().status || '—');
                    return `<span class="${statusBadgeClass}">${label}</span>`;
                },
            },
            {
                title: 'SKU',
                field: 'sku',
                minWidth: 280,
                width: 280,
                headerHozAlign: 'center',
                headerSort: true,
                sorter: sofStringSorter,
                formatter: function (cell) {
                    const sku = (cell.getValue() || '').toString().trim();
                    if (!sku) {
                        return '—';
                    }
                    const wrap = document.createElement('span');
                    wrap.className = 'sof-sku-cell';
                    const code = document.createElement('code');
                    code.textContent = sku;
                    wrap.appendChild(code);
                    const copyBtn = document.createElement('button');
                    copyBtn.type = 'button';
                    copyBtn.className = 'sof-sku-copy';
                    copyBtn.title = 'Copy SKU';
                    copyBtn.setAttribute('aria-label', 'Copy SKU');
                    copyBtn.innerHTML = '<i class="fas fa-copy" aria-hidden="true"></i>';
                    copyBtn.addEventListener('click', function (ev) {
                        ev.preventDefault();
                        ev.stopPropagation();
                        copyTextToClipboard(sku, copyBtn);
                    });
                    wrap.appendChild(copyBtn);
                    return wrap;
                },
            },
            {
                title: 'Product',
                field: 'display_title',
                minWidth: 70,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerTooltip: 'Hover dot to see full product name',
                formatter: function (cell) {
                    const title = (cell.getValue() || '').toString().trim();
                    if (!title) {
                        return '<span class="sof-oc-missing">—</span>';
                    }
                    const wrap = document.createElement('span');
                    wrap.className = 'sof-text-dot-wrap';

                    const dot = document.createElement('span');
                    dot.className = 'sof-text-dot';
                    dot.setAttribute('aria-label', 'Product');
                    wrap.appendChild(dot);

                    const box = document.createElement('span');
                    box.className = 'sof-text-dot-box';
                    box.textContent = title;
                    wrap.appendChild(box);

                    return wrap;
                },
            },
            {
                title: 'INV',
                field: 'INV',
                minWidth: 70,
                hozAlign: 'center',
                headerHozAlign: 'center',
                sorter: 'number',
                headerTooltip: 'Shopify on-hand inventory (shopify_skus.inv). Red triangle = zero stock alert.',
                formatter: function (cell) {
                    const raw = cell.getValue();
                    const n = (raw === null || raw === undefined || raw === '') ? 0 : Number(raw);
                    const label = Number.isFinite(n) ? n.toLocaleString() : '0';
                    if (!Number.isFinite(n) || n === 0) {
                        return `<span class="sof-inv-cell">`
                            + `<span>${label}</span>`
                            + `<span class="sof-inv-zero-alert" title="Alert: INV is 0 — out of stock">`
                            + `<i class="fas fa-exclamation-triangle" aria-hidden="true"></i>`
                            + `</span></span>`;
                    }
                    return `<span class="sof-inv-cell">${label}</span>`;
                },
            },
            {
                title: 'Label',
                field: 'label',
                minWidth: 90,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerTooltip: 'Shipping Master Label Type. Click the blue dot for Label Qty & dimensions.',
                formatter: function (cell) {
                    const data = cell.getRow().getData() || {};
                    const v = (cell.getValue() || '').toString().trim();
                    const wrap = document.createElement('span');
                    wrap.className = 'sof-label-cell';

                    if (v) {
                        const cls = ({
                            'ENV': 'sof-label-env',
                            'STD': 'sof-label-std',
                            'O-Size': 'sof-label-osize',
                            'Pallet': 'sof-label-pallet',
                        })[v] || 'sof-label-other';
                        const badge = document.createElement('span');
                        badge.className = 'sof-label-badge ' + cls;
                        badge.textContent = v;
                        wrap.appendChild(badge);
                    } else {
                        const missing = document.createElement('span');
                        missing.className = 'sof-oc-missing';
                        missing.textContent = '—';
                        wrap.appendChild(missing);
                    }

                    const dot = document.createElement('button');
                    dot.type = 'button';
                    dot.className = 'sof-label-dims-dot';
                    dot.title = 'View Label Qty & dimensions';
                    dot.setAttribute('aria-label', 'View Label Qty and dimensions');
                    dot.addEventListener('click', function (ev) {
                        ev.preventDefault();
                        ev.stopPropagation();
                        openLabelDimsModal(data);
                    });
                    wrap.appendChild(dot);

                    return wrap;
                },
            },
            {
                title: 'Qty',
                field: 'quantity',
                minWidth: 60,
                hozAlign: 'center',
                headerHozAlign: 'center',
                sorter: 'number',
            },
            {
                title: 'Amount',
                field: 'amount',
                minWidth: 80,
                hozAlign: 'right',
                headerHozAlign: 'center',
                sorter: 'number',
                formatter: function (cell) {
                    const v = cell.getValue();
                    if (v === null || v === undefined || v === '') return '—';
                    return Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },
            },
            {
                title: 'Prft alert',
                field: 'groi_pct',
                minWidth: 80,
                hozAlign: 'center',
                headerHozAlign: 'center',
                sorter: 'number',
                headerTooltip: 'Channel GROI% from Channel Master. Red triangle if below 40%.',
                formatter: function (cell) {
                    return formatChannelPctAlert(cell.getValue(), 40, 'GROI%');
                },
            },
            {
                title: 'GPFT%',
                field: 'gpft_pct',
                minWidth: 70,
                hozAlign: 'center',
                headerHozAlign: 'center',
                sorter: 'number',
                headerTooltip: 'Channel GPFT% from Channel Master. Red triangle if below 15%.',
                formatter: function (cell) {
                    return formatChannelPctAlert(cell.getValue(), 15, 'GPFT%');
                },
            },
            {
                title: 'Shopify',
                field: 'shopify_order_id',
                minWidth: 90,
                hozAlign: 'center',
                headerHozAlign: 'center',
                formatter: function (cell) {
                    const row = cell.getRow().getData();
                    if (row.shopify_order_id) {
                        return '<span class="badge bg-success">Imported</span>';
                    }
                    if ((row.import_status || '') === 'queued') {
                        return '<span class="badge bg-info">Queued</span>';
                    }
                    if ((row.import_status || '') === 'import_failed') {
                        return '<span class="badge bg-danger">Failed</span>';
                    }
                    return '<span class="badge bg-light text-muted">Pending</span>';
                },
            },
        ];
    }

    function applyFilters() {
        if (!table) return;
        const q = ($('#sof-search').val() || '').trim().toLowerCase();
        const channel = sofChannelFilterValue();

        if (!q && !channel) {
            table.clearFilter(true);
        } else {
            table.setFilter(function (data) {
                const searchOk = !q || (
                    String(data.channel || '').toLowerCase().includes(q)
                    || String(data.alias || '').toLowerCase().includes(q)
                );
                if (!searchOk) return false;
                if (!channel) return true;
                const slug = sofResolvedChannelSlug();
                if (slug) {
                    return String(data.mm_slug || '').toLowerCase() === slug;
                }
                const cq = channel.toLowerCase();
                return String(data.channel || '').toLowerCase().includes(cq)
                    || String(data.alias || '').toLowerCase().includes(cq)
                    || String(data.mm_slug || '').toLowerCase().includes(cq);
            });
        }
        updateSummaryStats();
    }

    table = new Tabulator('#sales-order-fulfillment-table', Object.assign({}, sofLocalTableOpts, {
        layout: 'fitColumns',
        placeholder: 'Loading channels…',
        initialSort: [
            { column: 'pending_count', dir: 'desc' },
        ],
        ajaxURL: '{{ route("sales.order.fulfillment.data") }}',
        ajaxConfig: 'GET',
        ajaxParams: sofDateParams,
        ajaxRequestFunc: function (url, config, params) {
            return new Promise(function (resolve, reject) {
                $.ajax({
                    url: url,
                    type: 'GET',
                    data: Object.assign({}, params || {}, sofDateParams()),
                    timeout: 0,
                    success: resolve,
                    error: reject,
                });
            });
        },
        ajaxResponse: function (url, params, response) {
            allRows = (response && response.success && Array.isArray(response.data))
                ? response.data
                : [];

            // Set badges immediately from API totals (table may still be empty here).
            const channelCount = (response && response.channel_count != null)
                ? Number(response.channel_count)
                : allRows.length;
            const pendingTotal = (response && response.pending_total != null)
                ? Number(response.pending_total)
                : sumPending(allRows);
            const fulfilled24h = (response && response.fulfilled_24h != null)
                ? Number(response.fulfilled_24h)
                : 0;
            const scanDone24h = (response && response.scan_done_24h != null)
                ? Number(response.scan_done_24h)
                : 0;
            const inTransitTotal = (response && response.in_transit_total != null)
                ? Number(response.in_transit_total)
                : 0;
            const inReceivedTotal = (response && response.in_received_total != null)
                ? Number(response.in_received_total)
                : 0;
            const invoicedTotal = (response && response.invoiced_total != null)
                ? Number(response.invoiced_total)
                : 0;
            const deliveredTotal = (response && response.delivered_total != null)
                ? Number(response.delivered_total)
                : 0;
            const allOrderTotal = (response && response.all_order_total != null)
                ? Number(response.all_order_total)
                : 0;

            const channelEl = document.getElementById('sof-channel-count');
            const pendingEl = document.getElementById('sof-pending-total');
            const fulfilledEl = document.getElementById('sof-fulfilled-24h');
            const scanDoneEl = document.getElementById('sof-scan-done-24h');
            const inTransitEl = document.getElementById('sof-in-transit-total');
            const inReceivedEl = document.getElementById('sof-in-received-total');
            const invoicedEl = document.getElementById('sof-invoiced-total');
            const deliveredEl = document.getElementById('sof-delivered-total');
            const allOrderEl = document.getElementById('sof-all-order-total');
            if (channelEl) channelEl.textContent = channelCount.toLocaleString();
            if (pendingEl) pendingEl.textContent = pendingTotal.toLocaleString();
            if (fulfilledEl) fulfilledEl.textContent = fulfilled24h.toLocaleString();
            if (scanDoneEl) scanDoneEl.textContent = scanDone24h.toLocaleString();
            if (inTransitEl) inTransitEl.textContent = inTransitTotal.toLocaleString();
            if (inReceivedEl) inReceivedEl.textContent = inReceivedTotal.toLocaleString();
            if (invoicedEl) invoicedEl.textContent = invoicedTotal.toLocaleString();
            if (deliveredEl) deliveredEl.textContent = deliveredTotal.toLocaleString();
            if (allOrderEl) allOrderEl.textContent = allOrderTotal.toLocaleString();
            loadSofHistoryDots();
            const fulfilledTabCount = document.getElementById('sof-fulfilled-tab-count');
            if (fulfilledTabCount && !fulfilledTableLoaded) {
                fulfilledTabCount.textContent = fulfilled24h.toLocaleString();
            }
            const scanDoneTabCount = document.getElementById('sof-scan-done-tab-count');
            if (scanDoneTabCount && !scanDoneTableLoaded) {
                scanDoneTabCount.textContent = scanDone24h.toLocaleString();
            }
            const inTransitTabCount = document.getElementById('sof-in-transit-tab-count');
            if (inTransitTabCount && !inTransitTableLoaded) {
                inTransitTabCount.textContent = inTransitTotal.toLocaleString();
            }
            const inReceivedTabCount = document.getElementById('sof-in-received-tab-count');
            if (inReceivedTabCount && !inReceivedTableLoaded) {
                inReceivedTabCount.textContent = inReceivedTotal.toLocaleString();
            }
            const invoicedTabCount = document.getElementById('sof-invoiced-tab-count');
            if (invoicedTabCount && !invoicedTableLoaded) {
                invoicedTabCount.textContent = invoicedTotal.toLocaleString();
            }
            const deliveredTabCount = document.getElementById('sof-delivered-tab-count');
            if (deliveredTabCount && !deliveredTableLoaded) {
                deliveredTabCount.textContent = deliveredTotal.toLocaleString();
            }
            const allOrderTabCount = document.getElementById('sof-all-order-tab-count');
            if (allOrderTabCount && !allOrderTableLoaded) {
                allOrderTabCount.textContent = allOrderTotal.toLocaleString();
            }

            return allRows;
        },
        dataLoaded: function () {
            applyFilters();
            updateSummaryStats(allRows);
        },
        dataFiltered: function () {
            updateSummaryStats();
        },
        columns: [
            {
                title: 'Img',
                field: 'logo',
                frozen: true,
                width: 70,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: false,
                formatter: function (cell) {
                    const row = cell.getRow().getData();
                    const logo = cell.getValue();
                    const channel = escapeHtml(row.channel || '');
                    const sellerLink = (row.seller_link || '').trim();

                    const imgHtml = logo
                        ? `<img src="/storage/${escapeHtml(logo)}" alt="${channel}" class="sof-channel-logo" onerror="this.style.display='none'"/>`
                        : `<span class="sof-channel-logo-placeholder" title="No logo"><i class="fas fa-image"></i></span>`;

                    if (sellerLink) {
                        return `<a href="${escapeHtml(sellerLink)}" target="_blank" rel="noopener noreferrer" class="sof-channel-logo-link" title="Open seller page">${imgHtml}</a>`;
                    }
                    return imgHtml;
                },
            },
            {
                title: 'MP',
                field: 'channel',
                frozen: true,
                visible: false,
                minWidth: 180,
                headerHozAlign: 'center',
                formatter: function (cell) {
                    const row = cell.getRow().getData();
                    const channel = escapeHtml(cell.getValue() || '');
                    const missingLink = (row.missing_link || '').trim();
                    if (missingLink) {
                        return `<a href="${escapeHtml(missingLink)}" target="_blank" class="sof-channel-name has-link" title="Open channel view">${channel}</a>`;
                    }
                    return `<span class="sof-channel-name">${channel}</span>`;
                },
            },
            {
                title: 'Channel',
                field: 'alias',
                minWidth: 140,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerTooltip: 'Channel alias — click to open this channel\'s view.',
                formatter: function (cell) {
                    const alias = (cell.getValue() || '').toString().trim();
                    if (!alias) {
                        return '<span style="color:#adb5bd;">-</span>';
                    }
                    const row = cell.getRow().getData();
                    const viewLink = (row.missing_link || '').trim();
                    const safeAlias = escapeHtml(alias);
                    if (viewLink) {
                        return `<a href="${escapeHtml(viewLink)}" target="_blank" class="sof-channel-name has-link" title="Open ${safeAlias} view">${safeAlias}</a>`;
                    }
                    return `<span class="sof-channel-name">${safeAlias}</span>`;
                },
            },
            {
                title: 'OC',
                field: 'oc_connected',
                width: 130,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerTooltip: 'Marketplace Manager connection status (same as /marketplace).',
                sorter: function (a, b, aRow, bRow) {
                    const rank = function (row) {
                        const d = row.getData();
                        if (!d.has_manager) return 0;
                        return d.oc_connected ? 2 : 1;
                    };
                    return rank(aRow) - rank(bRow);
                },
                formatter: function (cell) {
                    const row = cell.getRow().getData();
                    if (!row.has_manager) {
                        return '<span class="sof-oc-missing" title="No Marketplace Manager integration">—</span>';
                    }
                    if (row.oc_connected) {
                        return '<span class="sof-oc-dot connected"></span>Connected';
                    }
                    return '<span class="sof-oc-dot disconnected"></span>Not connected';
                },
            },
            {
                title: 'Pending',
                field: 'pending_count',
                width: 140,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerTooltip: 'Unfulfilled / pending orders (same Pending badge as each marketplace Orders page).',
                sorter: function (a, b) {
                    const av = (a === null || a === undefined || a === '') ? -1 : Number(a);
                    const bv = (b === null || b === undefined || b === '') ? -1 : Number(b);
                    return av - bv;
                },
                formatter: function (cell) {
                    const row = cell.getRow().getData();
                    if (!row.has_manager || row.pending_count === null || row.pending_count === undefined) {
                        return '<span class="sof-oc-missing" title="No Marketplace Manager orders">—</span>';
                    }
                    const count = Number(row.pending_count || 0);
                    const label = 'Pending: ' + count.toLocaleString();
                    const cls = count > 0 ? 'sof-pending-badge' : 'sof-pending-badge is-zero';
                    const ordersUrl = (row.orders_url || '').trim();
                    if (ordersUrl) {
                        return `<a href="${escapeHtml(ordersUrl)}" class="${cls}" title="Open marketplace orders">${escapeHtml(label)}</a>`;
                    }
                    return `<span class="${cls}">${escapeHtml(label)}</span>`;
                },
            },
            {
                title: 'Ch Orders',
                field: 'ch_orders_link',
                width: 90,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: false,
                headerTooltip: 'Double-click red dot to add a channel orders link. Green dot opens the saved link.',
                formatter: formatChOrdersDot,
            },
        ],
    }));

    const chOrdersModal = document.getElementById('sofChOrdersLinkModal');
    const chOrdersInput = document.getElementById('sof-ch-orders-modal-input');
    const chOrdersLabel = document.getElementById('sofChOrdersLinkModalLabel');
    const chOrdersChannelEl = document.getElementById('sof-ch-orders-modal-channel');
    const chOrdersSaveBtn = document.getElementById('sof-ch-orders-modal-save');
    const chOrdersErrorEl = document.getElementById('sof-ch-orders-modal-error');
    let chOrdersCtx = null;
    let chOrdersInFlight = false;

    function openChOrdersLinkModal(channelId, channelName, currentValue) {
        if (!chOrdersModal || typeof bootstrap === 'undefined') {
            return;
        }
        chOrdersCtx = { channelId: channelId };
        if (chOrdersLabel) {
            chOrdersLabel.textContent = currentValue ? 'Edit Ch Orders link' : 'Add Ch Orders link';
        }
        if (chOrdersChannelEl) {
            chOrdersChannelEl.textContent = channelName || '';
        }
        if (chOrdersInput) {
            chOrdersInput.value = currentValue || '';
        }
        if (chOrdersErrorEl) {
            chOrdersErrorEl.textContent = '';
            chOrdersErrorEl.classList.add('d-none');
        }
        bootstrap.Modal.getOrCreateInstance(chOrdersModal).show();
        setTimeout(function () {
            if (chOrdersInput) {
                chOrdersInput.focus();
                chOrdersInput.select();
            }
        }, 200);
    }

    function commitChOrdersLinkFromModal() {
        if (chOrdersInFlight || !chOrdersCtx) {
            return;
        }
        const val = chOrdersInput ? (chOrdersInput.value || '').trim() : '';
        chOrdersInFlight = true;
        if (chOrdersSaveBtn) chOrdersSaveBtn.disabled = true;
        if (chOrdersErrorEl) {
            chOrdersErrorEl.textContent = '';
            chOrdersErrorEl.classList.add('d-none');
        }

        fetch('{{ route("sales.order.fulfillment.ch.orders.link") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                channel_id: chOrdersCtx.channelId,
                ch_orders_link: val,
            }),
        })
        .then(function (r) { return r.json().then(function (body) { return { ok: r.ok, body: body }; }); })
        .then(function (res) {
            if (!res.ok || !res.body || !res.body.success) {
                throw new Error((res.body && res.body.message) || 'Could not save link.');
            }
            if (chOrdersModal && typeof bootstrap !== 'undefined') {
                bootstrap.Modal.getOrCreateInstance(chOrdersModal).hide();
            }
            const saved = res.body.ch_orders_link || null;
            syncChOrdersLinkAcrossTables(chOrdersCtx.channelId, saved);
        })
        .catch(function (e) {
            if (chOrdersErrorEl) {
                chOrdersErrorEl.textContent = e.message || 'Could not save link.';
                chOrdersErrorEl.classList.remove('d-none');
            }
        })
        .finally(function () {
            chOrdersInFlight = false;
            if (chOrdersSaveBtn) chOrdersSaveBtn.disabled = false;
        });
    }

    if (chOrdersSaveBtn) {
        chOrdersSaveBtn.addEventListener('click', commitChOrdersLinkFromModal);
    }
    if (chOrdersInput) {
        chOrdersInput.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') {
                ev.preventDefault();
                commitChOrdersLinkFromModal();
            }
        });
    }

    // Top badges: GOFO / VEEQO / Shopify / Others
    const topBadgeModal = document.getElementById('sofTopBadgeLinkModal');
    const topBadgeInput = document.getElementById('sof-top-badge-modal-input');
    const topBadgeLabel = document.getElementById('sofTopBadgeLinkModalLabel');
    const topBadgeNameEl = document.getElementById('sof-top-badge-modal-name');
    const topBadgeSaveBtn = document.getElementById('sof-top-badge-modal-save');
    const topBadgeErrorEl = document.getElementById('sof-top-badge-modal-error');
    let topBadgeCtx = null;
    let topBadgeInFlight = false;

    function openTopBadgeLinkModal(badgeKey, badgeLabel, currentValue) {
        if (!topBadgeModal || typeof bootstrap === 'undefined') {
            return;
        }
        topBadgeCtx = { badgeKey: badgeKey, badgeLabel: badgeLabel };
        if (topBadgeLabel) {
            topBadgeLabel.textContent = currentValue ? ('Edit ' + badgeLabel + ' link') : ('Add ' + badgeLabel + ' link');
        }
        if (topBadgeNameEl) {
            topBadgeNameEl.textContent = badgeLabel || '';
        }
        if (topBadgeInput) {
            topBadgeInput.value = currentValue || '';
        }
        if (topBadgeErrorEl) {
            topBadgeErrorEl.textContent = '';
            topBadgeErrorEl.classList.add('d-none');
        }
        bootstrap.Modal.getOrCreateInstance(topBadgeModal).show();
        setTimeout(function () {
            if (topBadgeInput) {
                topBadgeInput.focus();
                topBadgeInput.select();
            }
        }, 200);
    }

    function applyTopBadgeLinkUi(badgeEl, link) {
        const hasLink = !!(link && String(link).trim());
        const label = badgeEl.getAttribute('data-badge-label') || '';
        badgeEl.setAttribute('data-badge-link', hasLink ? String(link).trim() : '');
        badgeEl.classList.toggle('is-disabled', !hasLink);
        badgeEl.title = hasLink ? ('Click to open ' + label) : ('Add a link via the red dot');

        const dot = badgeEl.querySelector('.sof-top-badge-dot');
        if (dot) {
            dot.classList.toggle('green', hasLink);
            dot.classList.toggle('red', !hasLink);
            dot.title = hasLink ? 'Double-click to edit link' : 'Click to add link';
        }
    }

    function commitTopBadgeLinkFromModal() {
        if (topBadgeInFlight || !topBadgeCtx) {
            return;
        }
        const val = topBadgeInput ? (topBadgeInput.value || '').trim() : '';
        topBadgeInFlight = true;
        if (topBadgeSaveBtn) topBadgeSaveBtn.disabled = true;
        if (topBadgeErrorEl) {
            topBadgeErrorEl.textContent = '';
            topBadgeErrorEl.classList.add('d-none');
        }

        fetch('{{ route("sales.order.fulfillment.badge.link") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                badge_key: topBadgeCtx.badgeKey,
                link: val,
            }),
        })
        .then(function (r) { return r.json().then(function (body) { return { ok: r.ok, body: body }; }); })
        .then(function (res) {
            if (!res.ok || !res.body || !res.body.success) {
                throw new Error((res.body && res.body.message) || 'Could not save link.');
            }
            if (topBadgeModal && typeof bootstrap !== 'undefined') {
                bootstrap.Modal.getOrCreateInstance(topBadgeModal).hide();
            }
            const badgeEl = document.querySelector('.sof-top-badge[data-badge-key="' + topBadgeCtx.badgeKey + '"]');
            if (badgeEl) {
                applyTopBadgeLinkUi(badgeEl, res.body.link || '');
            }
        })
        .catch(function (e) {
            if (topBadgeErrorEl) {
                topBadgeErrorEl.textContent = e.message || 'Could not save link.';
                topBadgeErrorEl.classList.remove('d-none');
            }
        })
        .finally(function () {
            topBadgeInFlight = false;
            if (topBadgeSaveBtn) topBadgeSaveBtn.disabled = false;
        });
    }

    document.querySelectorAll('.sof-top-badge').forEach(function (badgeEl) {
        const dot = badgeEl.querySelector('.sof-top-badge-dot');

        badgeEl.addEventListener('click', function (ev) {
            if (ev.target.closest('.sof-top-badge-dot')) {
                return;
            }
            const key = badgeEl.getAttribute('data-badge-key') || '';
            const gofoApi = badgeEl.getAttribute('data-gofo-api') === '1';
            if (key === 'gofo' && gofoApi) {
                openGofoToolsModal();
                return;
            }
            const link = (badgeEl.getAttribute('data-badge-link') || '').trim();
            if (!link) {
                return;
            }
            window.open(link, '_blank', 'noopener,noreferrer');
        });

        if (dot) {
            dot.addEventListener('click', function (ev) {
                ev.preventDefault();
                ev.stopPropagation();
                const key = badgeEl.getAttribute('data-badge-key') || '';
                const label = badgeEl.getAttribute('data-badge-label') || key;
                const link = (badgeEl.getAttribute('data-badge-link') || '').trim();
                // Red dot: open modal. Green: click alone shouldn't steal badge open — use dblclick to edit.
                if (!link) {
                    openTopBadgeLinkModal(key, label, '');
                }
            });
            dot.addEventListener('dblclick', function (ev) {
                ev.preventDefault();
                ev.stopPropagation();
                const key = badgeEl.getAttribute('data-badge-key') || '';
                const label = badgeEl.getAttribute('data-badge-label') || key;
                const link = (badgeEl.getAttribute('data-badge-link') || '').trim();
                openTopBadgeLinkModal(key, label, link);
            });
        }
    });

    if (topBadgeSaveBtn) {
        topBadgeSaveBtn.addEventListener('click', commitTopBadgeLinkFromModal);
    }
    if (topBadgeInput) {
        topBadgeInput.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') {
                ev.preventDefault();
                commitTopBadgeLinkFromModal();
            }
        });
    }

    function applyPendingFilters() {
        sofApplyOrderTableFilter(pendingTable, '#sof-order-search');
    }

    function ensurePendingTable() {
        if (pendingTable || pendingTableLoading) {
            if (pendingTable) {
                setTimeout(function () { pendingTable.redraw(true); }, 50);
            }
            return;
        }
        pendingTableLoading = true;

        pendingTable = new Tabulator('#sof-pending-table', Object.assign({}, sofOrderTableOpts, {
            layout: 'fitColumns',
            placeholder: 'Loading pending orders…',
            initialSort: [
                { column: 'order_date', dir: 'desc' },
            ],
            ajaxURL: '{{ route("sales.order.fulfillment.pending.data") }}',
            ajaxConfig: 'GET',
            ajaxParams: sofDateParams,
            ajaxRequestFunc: function (url, config, params) {
                return new Promise(function (resolve, reject) {
                    $.ajax({
                        url: url,
                        type: 'GET',
                        data: Object.assign({}, params || {}, sofDateParams()),
                        timeout: 0,
                        success: resolve,
                        error: reject,
                    });
                });
            },
            ajaxResponse: function (url, params, response) {
                pendingRows = sofNormalizeOrderRows((response && response.success && Array.isArray(response.data))
                    ? response.data
                    : []);
                pendingTableLoaded = true;
                pendingTableLoading = false;
                const count = (response && response.count != null)
                    ? Number(response.count)
                    : pendingRows.length;
                const tabCount = document.getElementById('sof-pending-tab-count');
                if (tabCount) tabCount.textContent = count.toLocaleString();
                const pendingEl = document.getElementById('sof-pending-total');
                if (pendingEl) pendingEl.textContent = count.toLocaleString();
                sofUpdateTrackingFilterCounts(pendingRows);
                return pendingRows;
            },
            dataLoaded: function () {
                sofUpdateTrackingFilterCounts(pendingRows);
                applyPendingFilters();
            },
            columns: orderListColumns('sof-pending-badge'),
        }));
        sofWireOrderTable(pendingTable);
    }

    function applyFulfilledFilters() {
        sofApplyOrderTableFilter(fulfilledTable, '#sof-order-search');
    }

    function ensureFulfilledTable() {
        if (fulfilledTable || fulfilledTableLoading) {
            if (fulfilledTable) {
                setTimeout(function () { fulfilledTable.redraw(true); }, 50);
            }
            return;
        }
        fulfilledTableLoading = true;

        fulfilledTable = new Tabulator('#sof-fulfilled-table', Object.assign({}, sofOrderTableOpts, {
            layout: 'fitColumns',
            placeholder: 'Loading Label Created / No Scan orders…',
            initialSort: [
                { column: 'updated_at', dir: 'desc' },
            ],
            ajaxURL: '{{ route("sales.order.fulfillment.fulfilled.data") }}',
            ajaxConfig: 'GET',
            ajaxParams: sofDateParams,
            ajaxRequestFunc: function (url, config, params) {
                return new Promise(function (resolve, reject) {
                    $.ajax({
                        url: url,
                        type: 'GET',
                        data: Object.assign({}, params || {}, sofDateParams()),
                        timeout: 0,
                        success: resolve,
                        error: reject,
                    });
                });
            },
            ajaxResponse: function (url, params, response) {
                fulfilledRows = sofNormalizeOrderRows((response && response.success && Array.isArray(response.data))
                    ? response.data
                    : []);
                fulfilledTableLoaded = true;
                fulfilledTableLoading = false;
                const count = (response && response.count != null)
                    ? Number(response.count)
                    : fulfilledRows.length;
                const tabCount = document.getElementById('sof-fulfilled-tab-count');
                if (tabCount) tabCount.textContent = count.toLocaleString();
                const fulfilledEl = document.getElementById('sof-fulfilled-24h');
                if (fulfilledEl) fulfilledEl.textContent = count.toLocaleString();
                sofUpdateTrackingFilterCounts(fulfilledRows);
                return fulfilledRows;
            },
            dataLoaded: function () {
                sofUpdateTrackingFilterCounts(fulfilledRows);
                applyFulfilledFilters();
            },
            columns: (function () {
                const cols = orderListColumns('sof-fulfilled-badge');
                cols.forEach(function (c) {
                    if (c.field === 'status_label') {
                        c.title = 'Label Created / No Scan';
                        c.headerTooltip = 'Label Created / No Scan status';
                    }
                });
                // After Date (index 3 after Channel, Ch Orders, Order ID, Date) insert Updated + Tracking
                const dateIdx = cols.findIndex(function (c) { return c.field === 'order_date'; });
                const insertAt = dateIdx >= 0 ? dateIdx + 1 : 3;
                cols.splice(insertAt, 0, ...sofTrackingColumns());
                return cols;
            })(),
        }));
        sofWireOrderTable(fulfilledTable);
    }

    function applyScanDoneFilters() {
        sofApplyOrderTableFilter(scanDoneTable, '#sof-order-search');
    }

    function ensureScanDoneTable() {
        if (scanDoneTable || scanDoneTableLoading) {
            if (scanDoneTable) {
                setTimeout(function () { scanDoneTable.redraw(true); }, 50);
            }
            return;
        }
        scanDoneTableLoading = true;

        scanDoneTable = new Tabulator('#sof-scan-done-table', Object.assign({}, sofOrderTableOpts, {
            layout: 'fitColumns',
            placeholder: 'Loading Shipped/Received orders…',
            initialSort: [
                { column: 'updated_at', dir: 'desc' },
            ],
            ajaxURL: '{{ route("sales.order.fulfillment.scan.done.data") }}',
            ajaxConfig: 'GET',
            ajaxParams: sofDateParams,
            ajaxRequestFunc: function (url, config, params) {
                return new Promise(function (resolve, reject) {
                    $.ajax({
                        url: url,
                        type: 'GET',
                        data: Object.assign({}, params || {}, sofDateParams()),
                        timeout: 0,
                        success: resolve,
                        error: reject,
                    });
                });
            },
            ajaxResponse: function (url, params, response) {
                scanDoneRows = sofNormalizeOrderRows((response && response.success && Array.isArray(response.data))
                    ? response.data
                    : []);
                scanDoneTableLoaded = true;
                scanDoneTableLoading = false;
                const count = (response && response.count != null)
                    ? Number(response.count)
                    : scanDoneRows.length;
                const tabCount = document.getElementById('sof-scan-done-tab-count');
                if (tabCount) tabCount.textContent = count.toLocaleString();
                const scanDoneEl = document.getElementById('sof-scan-done-24h');
                if (scanDoneEl) scanDoneEl.textContent = count.toLocaleString();
                sofUpdateTrackingFilterCounts(scanDoneRows);
                return scanDoneRows;
            },
            dataLoaded: function () {
                sofUpdateTrackingFilterCounts(scanDoneRows);
                applyScanDoneFilters();
            },
            columns: (function () {
                const cols = orderListColumns('sof-scan-done-badge');
                cols.forEach(function (c) {
                    if (c.field === 'status_label') {
                        c.title = 'Shipped/Received';
                        c.headerTooltip = 'Shipped / Received status';
                    }
                });
                const dateIdx = cols.findIndex(function (c) { return c.field === 'order_date'; });
                const insertAt = dateIdx >= 0 ? dateIdx + 1 : 3;
                cols.splice(insertAt, 0, ...sofTrackingColumns());
                return cols;
            })(),
        }));
        sofWireOrderTable(scanDoneTable);
    }

    function applyInTransitFilters() {
        sofApplyOrderTableFilter(inTransitTable, '#sof-order-search');
    }

    function ensureInTransitTable() {
        if (inTransitTable || inTransitTableLoading) {
            if (inTransitTable) {
                setTimeout(function () { inTransitTable.redraw(true); }, 50);
            }
            return;
        }
        inTransitTableLoading = true;

        inTransitTable = new Tabulator('#sof-in-transit-table', Object.assign({}, sofOrderTableOpts, {
            layout: 'fitColumns',
            placeholder: 'Loading In Transit orders…',
            initialSort: [
                { column: 'updated_at', dir: 'desc' },
            ],
            ajaxURL: '{{ route("sales.order.fulfillment.in.transit.data") }}',
            ajaxConfig: 'GET',
            ajaxParams: sofDateParams,
            ajaxRequestFunc: function (url, config, params) {
                return new Promise(function (resolve, reject) {
                    $.ajax({
                        url: url,
                        type: 'GET',
                        data: Object.assign({}, params || {}, sofDateParams()),
                        timeout: 0,
                        success: resolve,
                        error: reject,
                    });
                });
            },
            ajaxResponse: function (url, params, response) {
                inTransitRows = sofNormalizeOrderRows((response && response.success && Array.isArray(response.data))
                    ? response.data
                    : []);
                inTransitTableLoaded = true;
                inTransitTableLoading = false;
                const count = (response && response.count != null)
                    ? Number(response.count)
                    : inTransitRows.length;
                const tabCount = document.getElementById('sof-in-transit-tab-count');
                if (tabCount) tabCount.textContent = count.toLocaleString();
                const inTransitEl = document.getElementById('sof-in-transit-total');
                if (inTransitEl) inTransitEl.textContent = count.toLocaleString();
                sofUpdateTrackingFilterCounts(inTransitRows);
                return inTransitRows;
            },
            dataLoaded: function () {
                sofUpdateTrackingFilterCounts(inTransitRows);
                applyInTransitFilters();
            },
            columns: (function () {
                const cols = orderListColumns('sof-in-transit-badge');
                cols.forEach(function (c) {
                    if (c.field === 'status_label') {
                        c.title = 'In Transit';
                        c.headerTooltip = 'In Transit status';
                    }
                });
                const dateIdx = cols.findIndex(function (c) { return c.field === 'order_date'; });
                const insertAt = dateIdx >= 0 ? dateIdx + 1 : 3;
                cols.splice(insertAt, 0, ...sofTrackingColumns());
                return cols;
            })(),
        }));
        sofWireOrderTable(inTransitTable);
    }

    function applyInReceivedFilters() {
        sofApplyOrderTableFilter(inReceivedTable, '#sof-order-search');
    }

    function ensureInReceivedTable() {
        if (inReceivedTable || inReceivedTableLoading) {
            if (inReceivedTable) {
                setTimeout(function () { inReceivedTable.redraw(true); }, 50);
            }
            return;
        }
        inReceivedTableLoading = true;

        inReceivedTable = new Tabulator('#sof-in-received-table', Object.assign({}, sofOrderTableOpts, {
            layout: 'fitColumns',
            placeholder: 'Loading In Received orders…',
            initialSort: [
                { column: 'updated_at', dir: 'desc' },
            ],
            ajaxURL: '{{ route("sales.order.fulfillment.in.received.data") }}',
            ajaxConfig: 'GET',
            ajaxParams: sofDateParams,
            ajaxRequestFunc: function (url, config, params) {
                return new Promise(function (resolve, reject) {
                    $.ajax({
                        url: url,
                        type: 'GET',
                        data: Object.assign({}, params || {}, sofDateParams()),
                        timeout: 0,
                        success: resolve,
                        error: reject,
                    });
                });
            },
            ajaxResponse: function (url, params, response) {
                inReceivedRows = sofNormalizeOrderRows((response && response.success && Array.isArray(response.data))
                    ? response.data
                    : []);
                inReceivedTableLoaded = true;
                inReceivedTableLoading = false;
                const count = (response && response.count != null)
                    ? Number(response.count)
                    : inReceivedRows.length;
                const tabCount = document.getElementById('sof-in-received-tab-count');
                if (tabCount) tabCount.textContent = count.toLocaleString();
                const inReceivedEl = document.getElementById('sof-in-received-total');
                if (inReceivedEl) inReceivedEl.textContent = count.toLocaleString();
                sofUpdateTrackingFilterCounts(inReceivedRows);
                return inReceivedRows;
            },
            dataLoaded: function () {
                sofUpdateTrackingFilterCounts(inReceivedRows);
                applyInReceivedFilters();
            },
            columns: (function () {
                const cols = orderListColumns('sof-in-received-badge');
                cols.forEach(function (c) {
                    if (c.field === 'status_label') {
                        c.title = 'In Received';
                        c.headerTooltip = 'Received status';
                    }
                });
                const dateIdx = cols.findIndex(function (c) { return c.field === 'order_date'; });
                const insertAt = dateIdx >= 0 ? dateIdx + 1 : 3;
                cols.splice(insertAt, 0, ...sofTrackingColumns());
                return cols;
            })(),
        }));
        sofWireOrderTable(inReceivedTable);
    }

    function applyInvoicedFilters() {
        sofApplyOrderTableFilter(invoicedTable, '#sof-order-search');
    }

    function ensureInvoicedTable() {
        if (invoicedTable || invoicedTableLoading) {
            if (invoicedTable) {
                setTimeout(function () { invoicedTable.redraw(true); }, 50);
            }
            return;
        }
        invoicedTableLoading = true;

        invoicedTable = new Tabulator('#sof-invoiced-table', Object.assign({}, sofOrderTableOpts, {
            layout: 'fitColumns',
            placeholder: 'Loading Invoiced orders…',
            initialSort: [
                { column: 'updated_at', dir: 'desc' },
            ],
            ajaxURL: '{{ route("sales.order.fulfillment.invoiced.data") }}',
            ajaxConfig: 'GET',
            ajaxParams: sofDateParams,
            ajaxRequestFunc: function (url, config, params) {
                return new Promise(function (resolve, reject) {
                    $.ajax({
                        url: url,
                        type: 'GET',
                        data: Object.assign({}, params || {}, sofDateParams()),
                        timeout: 0,
                        success: resolve,
                        error: reject,
                    });
                });
            },
            ajaxResponse: function (url, params, response) {
                invoicedRows = sofNormalizeOrderRows((response && response.success && Array.isArray(response.data))
                    ? response.data
                    : []);
                invoicedTableLoaded = true;
                invoicedTableLoading = false;
                const count = (response && response.count != null)
                    ? Number(response.count)
                    : invoicedRows.length;
                const tabCount = document.getElementById('sof-invoiced-tab-count');
                if (tabCount) tabCount.textContent = count.toLocaleString();
                const invoicedEl = document.getElementById('sof-invoiced-total');
                if (invoicedEl) invoicedEl.textContent = count.toLocaleString();
                sofUpdateTrackingFilterCounts(invoicedRows);
                return invoicedRows;
            },
            dataLoaded: function () {
                sofUpdateTrackingFilterCounts(invoicedRows);
                applyInvoicedFilters();
            },
            columns: (function () {
                const cols = orderListColumns('sof-invoiced-badge');
                cols.forEach(function (c) {
                    if (c.field === 'status_label') {
                        c.title = 'Invoiced';
                        c.headerTooltip = 'Invoiced status';
                    }
                });
                const dateIdx = cols.findIndex(function (c) { return c.field === 'order_date'; });
                const insertAt = dateIdx >= 0 ? dateIdx + 1 : 3;
                cols.splice(insertAt, 0, ...sofTrackingColumns());
                return cols;
            })(),
        }));
        sofWireOrderTable(invoicedTable);
    }

    function applyDeliveredFilters() {
        sofApplyOrderTableFilter(deliveredTable, '#sof-order-search');
    }

    function ensureDeliveredTable() {
        if (deliveredTableLoading) {
            return;
        }
        if (deliveredTable) {
            if (deliveredTableLoaded) {
                setTimeout(function () { deliveredTable.redraw(true); }, 50);
                return;
            }
            // Previous load failed — rebuild so user can retry by re-opening the tab.
            try { deliveredTable.destroy(); } catch (e) {}
            deliveredTable = null;
        }
        deliveredTableLoading = true;

        deliveredTable = new Tabulator('#sof-delivered-table', Object.assign({}, sofOrderTableOpts, {
            layout: 'fitColumns',
            placeholder: 'Loading Delivered orders (last 30 days)…',
            initialSort: [
                { column: 'updated_at', dir: 'desc' },
            ],
            ajaxURL: '{{ route("sales.order.fulfillment.delivered.data") }}',
            ajaxConfig: 'GET',
            ajaxParams: sofDateParams,
            ajaxRequestFunc: function (url, config, params) {
                return new Promise(function (resolve, reject) {
                    $.ajax({
                        url: url,
                        type: 'GET',
                        data: Object.assign({}, params || {}, sofDateParams()),
                        timeout: 0,
                        success: resolve,
                        error: reject,
                    });
                });
            },
            ajaxResponse: function (url, params, response) {
                if (!response || response.success === false) {
                    deliveredTableLoading = false;
                    deliveredTableLoaded = false;
                    deliveredRows = [];
                    return [];
                }
                deliveredRows = sofNormalizeOrderRows(Array.isArray(response.data) ? response.data : []);
                deliveredTableLoaded = true;
                deliveredTableLoading = false;
                const count = (response.count != null)
                    ? Number(response.count)
                    : deliveredRows.length;
                const tabCount = document.getElementById('sof-delivered-tab-count');
                if (tabCount) tabCount.textContent = count.toLocaleString();
                const deliveredEl = document.getElementById('sof-delivered-total');
                if (deliveredEl) deliveredEl.textContent = count.toLocaleString();
                sofUpdateTrackingFilterCounts(deliveredRows);
                return deliveredRows;
            },
            ajaxError: function () {
                deliveredTableLoading = false;
                deliveredTableLoaded = false;
                deliveredRows = [];
                if (deliveredTable) {
                    deliveredTable.setPlaceholder('Failed to load Delivered orders. Switch tabs and open Delivered again to retry.');
                }
            },
            dataLoaded: function () {
                sofUpdateTrackingFilterCounts(deliveredRows);
                applyDeliveredFilters();
            },
            columns: (function () {
                const cols = orderListColumns('sof-delivered-badge');
                cols.forEach(function (c) {
                    if (c.field === 'status_label') {
                        c.title = 'Delivered';
                        c.headerTooltip = 'Delivered status (last 30 days)';
                    }
                });
                const dateIdx = cols.findIndex(function (c) { return c.field === 'order_date'; });
                const insertAt = dateIdx >= 0 ? dateIdx + 1 : 3;
                cols.splice(insertAt, 0, ...sofTrackingColumns());
                return cols;
            })(),
        }));
        sofWireOrderTable(deliveredTable);
    }

    function applyAllOrderFilters() {
        sofApplyOrderTableFilter(allOrderTable, '#sof-order-search');
    }

    function ensureAllOrderTable() {
        if (allOrderTable || allOrderTableLoading) {
            if (allOrderTable) {
                setTimeout(function () { allOrderTable.redraw(true); }, 50);
            }
            return;
        }
        allOrderTableLoading = true;

        allOrderTable = new Tabulator('#sof-all-order-table', Object.assign({}, sofOrderTableOpts, {
            layout: 'fitColumns',
            placeholder: 'Loading all orders…',
            initialSort: [
                { column: 'updated_at', dir: 'desc' },
            ],
            ajaxURL: '{{ route("sales.order.fulfillment.all.order.data") }}',
            ajaxConfig: 'GET',
            ajaxParams: sofDateParams,
            ajaxRequestFunc: function (url, config, params) {
                return new Promise(function (resolve, reject) {
                    $.ajax({
                        url: url,
                        type: 'GET',
                        data: Object.assign({}, params || {}, sofDateParams()),
                        timeout: 0,
                        success: resolve,
                        error: reject,
                    });
                });
            },
            ajaxResponse: function (url, params, response) {
                allOrderRows = sofNormalizeOrderRows((response && response.success && Array.isArray(response.data))
                    ? response.data
                    : []);
                allOrderTableLoaded = true;
                allOrderTableLoading = false;
                const count = (response && response.count != null)
                    ? Number(response.count)
                    : allOrderRows.length;
                const tabCount = document.getElementById('sof-all-order-tab-count');
                if (tabCount) tabCount.textContent = count.toLocaleString();
                const allOrderEl = document.getElementById('sof-all-order-total');
                if (allOrderEl) allOrderEl.textContent = count.toLocaleString();
                sofUpdateTrackingFilterCounts(allOrderRows);
                return allOrderRows;
            },
            dataLoaded: function () {
                sofUpdateTrackingFilterCounts(allOrderRows);
                applyAllOrderFilters();
            },
            columns: (function () {
                const cols = orderListColumns('sof-all-order-status-badge');
                cols.forEach(function (c) {
                    if (c.field === 'status_label') {
                        c.title = 'Status';
                        c.headerTooltip = 'Original marketplace status';
                    }
                });
                const dateIdx = cols.findIndex(function (c) { return c.field === 'order_date'; });
                const insertAt = dateIdx >= 0 ? dateIdx + 1 : 3;
                cols.splice(insertAt, 0, ...sofTrackingColumns());
                return cols;
            })(),
        }));
        sofWireOrderTable(allOrderTable);
    }

    document.getElementById('sof-all-order-tab')?.addEventListener('shown.bs.tab', function () {
        ensureAllOrderTable();
    });
    document.getElementById('sof-pending-tab')?.addEventListener('shown.bs.tab', function () {
        ensurePendingTable();
    });
    document.getElementById('sof-fulfilled-tab')?.addEventListener('shown.bs.tab', function () {
        ensureFulfilledTable();
    });
    document.getElementById('sof-scan-done-tab')?.addEventListener('shown.bs.tab', function () {
        ensureScanDoneTable();
    });
    document.getElementById('sof-in-transit-tab')?.addEventListener('shown.bs.tab', function () {
        ensureInTransitTable();
    });
    document.getElementById('sof-in-received-tab')?.addEventListener('shown.bs.tab', function () {
        ensureInReceivedTable();
    });
    document.getElementById('sof-invoiced-tab')?.addEventListener('shown.bs.tab', function () {
        ensureInvoicedTable();
    });
    document.getElementById('sof-delivered-tab')?.addEventListener('shown.bs.tab', function () {
        ensureDeliveredTable();
    });

    document.getElementById('sof-pending-total-badge')?.addEventListener('click', function () {
        switchToPendingTab();
    });
    document.getElementById('sof-fulfilled-24h-badge')?.addEventListener('click', function () {
        switchToFulfilledTab();
    });
    document.getElementById('sof-scan-done-24h-badge')?.addEventListener('click', function () {
        switchToScanDoneTab();
    });
    document.getElementById('sof-in-transit-badge')?.addEventListener('click', function () {
        switchToInTransitTab();
    });
    document.getElementById('sof-in-received-badge')?.addEventListener('click', function () {
        switchToInReceivedTab();
    });
    document.getElementById('sof-invoiced-badge')?.addEventListener('click', function () {
        switchToInvoicedTab();
    });
    document.getElementById('sof-delivered-badge')?.addEventListener('click', function () {
        switchToDeliveredTab();
    });
    document.getElementById('sof-all-order-badge')?.addEventListener('click', function () {
        switchToAllOrderTab();
    });
    $('#sof-search').on('keyup', function (e) {
        if (e.key === 'Enter') {
            applyFilters();
            return;
        }
        applyFilters();
    });
    let sofOrderSearchTimer = null;
    $('#sof-order-search').on('input keyup', function () {
        if (sofOrderSearchTimer) clearTimeout(sofOrderSearchTimer);
        sofOrderSearchTimer = setTimeout(function () {
            sofApplyAllCarrierFilters();
        }, 150);
    });

    function sofMapPullTarget(r) {
        if (!r || typeof r !== 'object') return null;
        const mapped = {
            id: String(r.id || '').trim(),
            mm_slug: String(r.mm_slug || '').trim(),
            order_id: String(r.order_id || '').trim(),
            order_id_api: String(r.order_id_api || '').trim(),
            order_number: String(r.order_number || '').trim(),
            shopify_order_id: String(r.shopify_order_id || '').trim(),
        };
        if (!(mapped.mm_slug || mapped.order_id || mapped.order_id_api || mapped.order_number || mapped.shopify_order_id)) {
            return null;
        }
        return mapped;
    }

    function sofSelectedPullTargets() {
        const seen = {};
        const out = [];
        function addRows(list) {
            (list || []).forEach(function (r) {
                const mapped = sofMapPullTarget(r);
                if (!mapped) return;
                const key = mapped.id
                    || (mapped.mm_slug + '|' + (mapped.order_id_api || mapped.order_id || mapped.order_number || mapped.shopify_order_id));
                if (seen[key]) return;
                seen[key] = true;
                out.push(mapped);
            });
        }

        // 1) Active order tab first
        addRows(sofTableSelectedData(sofActiveOrderTable()));

        // 2) Any other order table that still has checked rows
        sofOrderTablePairs().forEach(function (pair) {
            addRows(sofTableSelectedData(pair[2]));
        });

        return out;
    }

    function sofRunPullTracking(selected) {
        const $btn = $('#sof-pull-tracking-btn');
        if ($btn.prop('disabled')) return;
        const $label = $btn.find('.sof-pull-tracking-label');
        const prev = $label.text();
        let targets = Array.isArray(selected) ? selected.slice() : [];
        if (targets.length > 100) {
            const proceed = window.confirm(
                'You have ' + targets.length + ' rows selected. Pull will process the first 100 only. Continue?'
            );
            if (!proceed) return;
            targets = targets.slice(0, 100);
        }
        $btn.prop('disabled', true);
        $label.text(targets.length ? ('Pulling ' + targets.length + '…') : 'Pulling…');

        const channelFilter = sofChannelFilterValue();
        const payload = {
            limit: targets.length ? Math.min(100, targets.length) : 40,
            channel: channelFilter || '',
            selected: targets,
            selected_only: targets.length > 0,
        };
        fetch('{{ route("sales.order.fulfillment.pull.tracking.numbers") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        })
            .then(function (r) {
                return r.text().then(function (text) {
                    let j = null;
                    try { j = text ? JSON.parse(text) : null; } catch (e) { j = null; }
                    return { ok: r.ok, status: r.status, json: j, raw: text };
                });
            })
            .then(function (res) {
                const j = res.json || {};
                const ok = !!(res.ok && j.success !== false);
                const msg = j.message
                    || (ok ? 'Done.' : ('Pull failed' + (res.status ? (' (HTTP ' + res.status + ')') : '') + '.'));
                const rows = Array.isArray(j.data) ? j.data : [];
                const summary = j.summary || {};
                const summaryLine = (summary.selected ? ('Selected: ' + summary.selected + ' · ') : '')
                    + 'Checked: ' + (summary.checked || 0)
                    + ' · With tracking: ' + (summary.with_tracking || 0)
                    + ' · Saved: ' + (summary.updated || 0)
                    + ' · Empty: ' + (summary.empty || 0);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: ok ? 'success' : 'error',
                        title: ok
                            ? (targets.length ? ('Pulled ' + targets.length + ' selected') : 'Pulled tracking numbers')
                            : 'Pull failed',
                        width: Math.min(920, window.innerWidth - 40),
                        html: '<div style="text-align:left;font-size:0.9rem;">'
                            + '<p class="mb-1">' + escapeHtml(msg) + '</p>'
                            + '<p class="text-muted mb-2" style="font-size:0.8rem;">' + escapeHtml(summaryLine) + '</p>'
                            + buildPulledTrackingTableHtml(rows)
                            + '</div>',
                        showConfirmButton: true,
                        confirmButtonText: 'Close',
                    });
                } else {
                    alert(msg + '\n' + summaryLine);
                }
                if (ok) {
                    sofApplyPulledTrackingToTables(rows);
                }
            })
            .catch(function (err) {
                const msg = err && err.message ? err.message : 'Network error';
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Pull failed', text: msg });
                } else {
                    alert(msg);
                }
            })
            .finally(function () {
                $btn.prop('disabled', false);
                $label.text(prev);
            });
    }

    $('#sof-pull-tracking-btn').on('click', function () {
        const selected = sofSelectedPullTargets();
        if (!selected.length) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'No rows checked',
                    html: 'Use the <b>header checkbox</b> (or row checkboxes) to select orders, then click <b>Pull Tracking Number</b>.<br><br>'
                        + 'Or continue to pull a batch of 40 orders that are still missing tracking.',
                    showCancelButton: true,
                    confirmButtonText: 'Pull batch of 40',
                    cancelButtonText: 'Cancel',
                }).then(function (result) {
                    if (result.isConfirmed) sofRunPullTracking([]);
                });
            } else if (confirm('No rows checked. Pull a batch of 40?')) {
                sofRunPullTracking([]);
            }
            return;
        }
        sofRunPullTracking(selected);
    });

    const sofHistoryLabels = {
        channel_count: 'Channels',
        pending_total: 'Pending',
        fulfilled_24h: 'Label Created / No Scan',
        scan_done_24h: 'Shipped/Received',
        in_transit_total: 'In Transit',
        in_received_total: 'In Received',
        invoiced_total: 'Invoiced',
        delivered_total: 'Delivered',
        all_order_total: 'All Order',
    };
    const sofDotTrendsUrl = @json(route('sales.order.fulfillment.history.dot.trends'));
    const sofChartDataUrl = @json(route('sales.order.fulfillment.history.chart.data'));
    let sofHistoryChartInstance = null;
    let sofHistoryMetric = 'pending_total';
    let sofHistoryDays = 30;

    function loadSofHistoryDots() {
        fetch(sofDotTrendsUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                const metrics = (res && res.success && res.metrics) ? res.metrics : {};
                Object.keys(sofHistoryLabels).forEach(function (key) {
                    const pair = metrics[key] || [null, null];
                    const v1 = pair[0] != null ? Number(pair[0]) : null;
                    const v2 = pair[1] != null ? Number(pair[1]) : null;
                    let color = '#6c757d';
                    if (v1 != null && v2 != null && !isNaN(v1) && !isNaN(v2)) {
                        if (v2 > v1) color = '#28a745';
                        else if (v2 < v1) color = '#dc3545';
                    }
                    document.querySelectorAll('.sof-hist-dot[data-sof-metric="' + key + '"]').forEach(function (el) {
                        el.style.background = color;
                        el.title = (v1 != null && v2 != null)
                            ? ('Was ' + Number(v1).toLocaleString() + ' → ' + Number(v2).toLocaleString() + ' (click for graph)')
                            : 'History trend (click for graph)';
                    });
                });
            })
            .catch(function () { /* keep gray dots */ });
    }

    function showSofHistoryChart(metric) {
        sofHistoryMetric = metric || 'pending_total';
        const label = sofHistoryLabels[sofHistoryMetric] || sofHistoryMetric;
        const rangeLabel = sofHistoryDays > 0 ? (sofHistoryDays + ' Days') : 'All';
        $('#sofHistoryChartTitle').text('SOF — ' + label + ' (Rolling ' + rangeLabel + ', Pacific day)');
        $('#sofHistoryChartRange').val(String(sofHistoryDays));
        const modalEl = document.getElementById('sofHistoryChartModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
        loadSofHistoryChart();
    }

    function loadSofHistoryChart() {
        $('#sofHistoryChartContainer').hide();
        $('#sofHistoryChartNoData').hide();
        $('#sofHistoryChartLoading').show();
        const qs = new URLSearchParams({ metric: sofHistoryMetric, days: String(sofHistoryDays) });
        fetch(sofChartDataUrl + '?' + qs.toString(), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                $('#sofHistoryChartLoading').hide();
                const data = (res && res.success && Array.isArray(res.data)) ? res.data : [];
                if (!data.length) {
                    $('#sofHistoryChartNoData').show();
                    if (sofHistoryChartInstance) {
                        sofHistoryChartInstance.destroy();
                        sofHistoryChartInstance = null;
                    }
                    return;
                }
                $('#sofHistoryChartContainer').show();
                const labels = data.map(function (d) { return d.date; });
                const values = data.map(function (d) { return Number(d.value) || 0; });
                const dataMin = Math.min.apply(null, values);
                const dataMax = Math.max.apply(null, values);
                const sorted = values.slice().sort(function (a, b) { return a - b; });
                const mid = Math.floor(sorted.length / 2);
                const median = sorted.length % 2
                    ? sorted[mid]
                    : (sorted[mid - 1] + sorted[mid]) / 2;
                const fmt = function (v) { return Math.round(v).toLocaleString('en-US'); };
                $('#sofHistoryHighest').text(fmt(dataMax));
                $('#sofHistoryMedian').text(fmt(median));
                $('#sofHistoryLowest').text(fmt(dataMin));
                const ctx = document.getElementById('sofHistoryChart').getContext('2d');
                if (sofHistoryChartInstance) sofHistoryChartInstance.destroy();
                const range = (dataMax - dataMin) || 1;
                sofHistoryChartInstance = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: sofHistoryLabels[sofHistoryMetric] || sofHistoryMetric,
                            data: values,
                            borderColor: '#0d6efd',
                            backgroundColor: 'rgba(13,110,253,0.12)',
                            fill: true,
                            tension: 0.25,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) { return fmt(ctx.parsed.y); },
                                },
                            },
                        },
                        scales: {
                            y: {
                                beginAtZero: dataMin === 0,
                                suggestedMin: Math.max(0, dataMin - range * 0.1),
                                suggestedMax: dataMax + range * 0.1,
                                ticks: { callback: function (v) { return fmt(v); } },
                            },
                        },
                    },
                });
            })
            .catch(function () {
                $('#sofHistoryChartLoading').hide();
                $('#sofHistoryChartNoData').show();
            });
    }

    $(document).on('click', '.sof-summary-badge, .sof-hist-dot', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const metric = $(this).data('sof-metric') || $(this).closest('[data-sof-metric]').data('sof-metric');
        if (metric) showSofHistoryChart(metric);
    });

    $('#sofHistoryChartRange').on('change', function () {
        sofHistoryDays = parseInt($(this).val(), 10);
        if (isNaN(sofHistoryDays)) sofHistoryDays = 30;
        const label = sofHistoryLabels[sofHistoryMetric] || sofHistoryMetric;
        const rangeLabel = sofHistoryDays > 0 ? (sofHistoryDays + ' Days') : 'All';
        $('#sofHistoryChartTitle').text('SOF — ' + label + ' (Rolling ' + rangeLabel + ', Pacific day)');
        loadSofHistoryChart();
    });

    // ── GOFO Express tools (top badge) ──────────────────────────────────────
    const gofoToolsModal = document.getElementById('sofGofoToolsModal');
    const sofGofoResult = document.getElementById('sof-gofo-result');
    const sofGofoConnBadge = document.getElementById('sof-gofo-conn-badge');
    const sofCsrf = function () {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    };

    function setGofoResult(text, isError) {
        if (!sofGofoResult) return;
        sofGofoResult.textContent = text || '';
        sofGofoResult.style.borderColor = isError ? '#f1aeb5' : '#e2e8f0';
        sofGofoResult.style.background = isError ? '#fff5f5' : '#f8fafc';
    }

    function setGofoConnBadge(ok, message) {
        if (!sofGofoConnBadge) return;
        if (ok) {
            sofGofoConnBadge.textContent = 'API connected';
            sofGofoConnBadge.style.background = '#d1e7dd';
            sofGofoConnBadge.style.color = '#0f5132';
            sofGofoConnBadge.title = message || '';
        } else {
            sofGofoConnBadge.textContent = 'API error';
            sofGofoConnBadge.style.background = '#f8d7da';
            sofGofoConnBadge.style.color = '#842029';
            sofGofoConnBadge.title = message || '';
        }
    }

    function openGofoToolsModal() {
        if (!gofoToolsModal || typeof bootstrap === 'undefined') {
            return;
        }
        bootstrap.Modal.getOrCreateInstance(gofoToolsModal).show();
        pingGofoStatus();
    }

    function pingGofoStatus() {
        if (sofGofoConnBadge) {
            sofGofoConnBadge.textContent = 'Checking…';
            sofGofoConnBadge.style.background = '#e9ecef';
            sofGofoConnBadge.style.color = '#495057';
        }
        fetch('{{ route("sales.order.fulfillment.gofo.status") }}', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); })
            .then(function (res) {
                const msg = (res.json && res.json.message) ? res.json.message : '';
                setGofoConnBadge(!!(res.ok && res.json && res.json.success), msg);
                if (res.json && res.json.data) {
                    setGofoResult(JSON.stringify(res.json, null, 2), !(res.json.success));
                }
            })
            .catch(function (err) {
                setGofoConnBadge(false, err.message || 'Network error');
                setGofoResult(err.message || 'Network error', true);
            });
    }

    $('#sof-gofo-ping-btn').on('click', function () { pingGofoStatus(); });

    $('#sof-gofo-verify-btn').on('click', function () {
        const $btn = $(this);
        if ($btn.prop('disabled')) return;
        $btn.prop('disabled', true).text('Checking…');
        fetch('{{ route("sales.order.fulfillment.gofo.verify") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': sofCsrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                consigneeCountry: ($('#sof-gofo-zip-country').val() || 'US').trim(),
                consigneeCode: ($('#sof-gofo-zip-code').val() || '').trim(),
                consigneeState: ($('#sof-gofo-zip-state').val() || '').trim(),
                consigneeCity: ($('#sof-gofo-zip-city').val() || '').trim(),
            }),
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); })
            .then(function (res) {
                setGofoResult(JSON.stringify(res.json || {}, null, 2), !(res.json && res.json.success));
            })
            .catch(function (err) {
                setGofoResult(err.message || 'Network error', true);
            })
            .finally(function () {
                $btn.prop('disabled', false).text('Check ZIP');
            });
    });

    $('#sof-gofo-track-btn').on('click', function () {
        const $btn = $(this);
        if ($btn.prop('disabled')) return;
        const orderNo = ($('#sof-gofo-track-no').val() || '').trim();
        if (!orderNo) {
            setGofoResult('Enter a GOFO waybill / order number.', true);
            return;
        }
        $btn.prop('disabled', true).text('Tracking…');
        fetch('{{ route("sales.order.fulfillment.gofo.track") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': sofCsrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ orderNo: orderNo }),
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); })
            .then(function (res) {
                setGofoResult(JSON.stringify(res.json || {}, null, 2), !(res.json && res.json.success));
            })
            .catch(function (err) {
                setGofoResult(err.message || 'Network error', true);
            })
            .finally(function () {
                $btn.prop('disabled', false).text('Track');
            });
    });

    $('#sof-gofo-refresh-btn').on('click', function () {
        const $btn = $(this);
        if ($btn.prop('disabled')) return;
        const prev = $btn.text();
        $btn.prop('disabled', true).text('Syncing…');
        fetch('{{ route("sales.order.fulfillment.gofo.refresh") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': sofCsrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ limit: 40 }),
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); })
            .then(function (res) {
                const msg = (res.json && res.json.message) ? res.json.message : (res.ok ? 'Done.' : 'Failed.');
                const detail = (res.json && res.json.output) ? String(res.json.output) : '';
                setGofoResult(msg + (detail ? '\n\n' + detail : ''), !res.ok);
                if (res.ok) {
                    reloadSofTrackingTables();
                }
            })
            .catch(function (err) {
                setGofoResult(err.message || 'Network error', true);
            })
            .finally(function () {
                $btn.prop('disabled', false).text(prev);
            });
    });

    function sofShowShipmentSyncNotice(ok, msg) {
        let $banner = $('#sof-shipment-sync-banner');
        if (!$banner.length) {
            $banner = $('<div id="sof-shipment-sync-banner" class="alert py-2 px-3 mb-2 small" role="status" style="display:none;"></div>');
            const $btn = $('#sof-refresh-shipment-btn');
            if ($btn.length) {
                $btn.closest('.d-flex').before($banner);
            } else {
                $('body').prepend($banner);
            }
        }
        $banner
            .removeClass('alert-success alert-danger alert-warning')
            .addClass(ok ? 'alert-success' : 'alert-danger')
            .html(escapeHtml(msg))
            .stop(true, true)
            .fadeIn(150);
        if (ok) {
            clearTimeout(window.__sofShipmentSyncBannerTimer);
            window.__sofShipmentSyncBannerTimer = setTimeout(function () {
                $banner.fadeOut(300);
            }, 8000);
        }
    }

    $('#sof-refresh-shipment-btn').on('click', function () {
        const $btn = $(this);
        if ($btn.prop('disabled')) return;
        const $label = $btn.find('.sof-refresh-shipment-label');
        const prev = $label.text();
        $btn.prop('disabled', true);
        $label.text('Queuing…');

        fetch('{{ route("sales.order.fulfillment.refresh.shipment.status") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            // Background catch-up for large backlogs (~thousands). Does not block the page.
            body: JSON.stringify({ limit: 2000, stale: 0, repair_quota: true, catch_up: true }),
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); })
            .then(function (res) {
                const msg = (res.json && res.json.message) ? res.json.message : (res.ok ? 'Queued.' : 'Update failed.');
                // Inline banner only — avoid native alert() (blocks the page every click).
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: res.ok ? 'success' : 'error',
                        title: res.ok ? 'Sync queued' : 'Update failed',
                        html: '<div style="text-align:left;font-size:0.9rem;">'
                            + '<p class="mb-0">' + escapeHtml(msg) + '</p>'
                            + '</div>',
                        timer: res.ok ? 5500 : undefined,
                        showConfirmButton: true,
                        confirmButtonText: 'OK',
                    });
                } else {
                    sofShowShipmentSyncNotice(!!res.ok, msg);
                }
                // Soft refresh active tab shortly — full multi-tab reload is too slow with ~5k rows.
                if (res.ok) {
                    setTimeout(function () {
                        try {
                            if (fulfilledTable && typeof fulfilledTable.replaceData === 'function') {
                                fulfilledTable.replaceData();
                            }
                        } catch (e) {}
                    }, 45000);
                }
            })
            .catch(function (err) {
                const msg = err && err.message ? err.message : 'Network error';
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Update failed', text: msg });
                } else {
                    sofShowShipmentSyncNotice(false, msg);
                }
            })
            .finally(function () {
                $btn.prop('disabled', false);
                $label.text(prev);
            });
    });
})();
</script>
@endsection
