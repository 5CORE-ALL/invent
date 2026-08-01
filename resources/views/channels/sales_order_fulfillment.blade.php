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
        }

        #sales-order-fulfillment-table.tabulator .tabulator-header .tabulator-col {
            height: 80px !important;
            overflow: visible;
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
            padding: 12px 14px;
        }
        #sof-filter-bar .sof-filter-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 4px;
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
        .sof-top-badge.veeqo { background: #6610f2; }
        .sof-top-badge.shopify { background: #198754; }
        .sof-top-badge.others { background: #6c757d; }

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
        #sof-invoiced-badge,
        #sof-delivered-badge,
        #sof-all-order-badge {
            cursor: pointer;
        }

        #sof-pending-table.tabulator .tabulator-header .tabulator-col,
        #sof-fulfilled-table.tabulator .tabulator-header .tabulator-col,
        #sof-scan-done-table.tabulator .tabulator-header .tabulator-col,
        #sof-invoiced-table.tabulator .tabulator-header .tabulator-col,
        #sof-delivered-table.tabulator .tabulator-header .tabulator-col,
        #sof-all-order-table.tabulator .tabulator-header .tabulator-col {
            background-color: #e6e6e6;
        }
        #sof-pending-table.tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title,
        #sof-fulfilled-table.tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title,
        #sof-scan-done-table.tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title,
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
        }
        #sof-pending-table.tabulator .tabulator-header .tabulator-col,
        #sof-fulfilled-table.tabulator .tabulator-header .tabulator-col,
        #sof-scan-done-table.tabulator .tabulator-header .tabulator-col,
        #sof-invoiced-table.tabulator .tabulator-header .tabulator-col,
        #sof-delivered-table.tabulator .tabulator-header .tabulator-col,
        #sof-all-order-table.tabulator .tabulator-header .tabulator-col {
            height: 80px !important;
            overflow: visible;
        }
        #sof-pending-table .tabulator-row .tabulator-cell,
        #sof-fulfilled-table .tabulator-row .tabulator-cell,
        #sof-scan-done-table .tabulator-row .tabulator-cell,
        #sof-invoiced-table .tabulator-row .tabulator-cell,
        #sof-delivered-table .tabulator-row .tabulator-cell,
        #sof-all-order-table .tabulator-row .tabulator-cell {
            vertical-align: middle;
        }
        #sof-pending-table .tabulator-row .tabulator-cell:has(.sof-order-id-wrap),
        #sof-fulfilled-table .tabulator-row .tabulator-cell:has(.sof-order-id-wrap),
        #sof-scan-done-table .tabulator-row .tabulator-cell:has(.sof-order-id-wrap),
        #sof-invoiced-table .tabulator-row .tabulator-cell:has(.sof-order-id-wrap),
        #sof-delivered-table .tabulator-row .tabulator-cell:has(.sof-order-id-wrap),
        #sof-all-order-table .tabulator-row .tabulator-cell:has(.sof-order-id-wrap),
        #sof-pending-table .tabulator-row .tabulator-cell:has(.sof-text-dot-wrap),
        #sof-fulfilled-table .tabulator-row .tabulator-cell:has(.sof-text-dot-wrap),
        #sof-scan-done-table .tabulator-row .tabulator-cell:has(.sof-text-dot-wrap),
        #sof-invoiced-table .tabulator-row .tabulator-cell:has(.sof-text-dot-wrap),
        #sof-delivered-table .tabulator-row .tabulator-cell:has(.sof-text-dot-wrap),
        #sof-all-order-table .tabulator-row .tabulator-cell:has(.sof-text-dot-wrap) {
            overflow: visible !important;
        }
        #sof-pending-table .tabulator-row:has(.sof-order-id-wrap:hover),
        #sof-fulfilled-table .tabulator-row:has(.sof-order-id-wrap:hover),
        #sof-scan-done-table .tabulator-row:has(.sof-order-id-wrap:hover),
        #sof-invoiced-table .tabulator-row:has(.sof-order-id-wrap:hover),
        #sof-delivered-table .tabulator-row:has(.sof-order-id-wrap:hover),
        #sof-all-order-table .tabulator-row:has(.sof-order-id-wrap:hover),
        #sof-pending-table .tabulator-row:has(.sof-text-dot-wrap:hover),
        #sof-fulfilled-table .tabulator-row:has(.sof-text-dot-wrap:hover),
        #sof-scan-done-table .tabulator-row:has(.sof-text-dot-wrap:hover),
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
            --bs-table-bg: #fff9c4;
            --bs-table-accent-bg: #fff9c4;
            background-color: #fff9c4 !important;
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
                <div class="card-body py-3">
                    <div id="sof-summary-stats" class="p-3 bg-light rounded">
                        <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
                            <div class="d-flex flex-wrap gap-2" role="group" aria-label="Summary metrics">
                                <span class="badge bg-primary fs-6 p-2" style="color: white; font-weight: bold;" title="Active channels from channel_master (same as /all-marketplace-master)">
                                    Channels: <span id="sof-channel-count">0</span>
                                </span>
                                <span class="badge fs-6 p-2" id="sof-pending-total-badge" style="background:#fff3cd; color:#856404; font-weight:600; border:1px solid #ffe69c;" title="Sum of Pending column (unfulfilled orders across Marketplace Manager channels)">
                                    Pending: <span id="sof-pending-total">0</span>
                                </span>
                                <span class="badge fs-6 p-2" id="sof-fulfilled-24h-badge" style="background:#d1e7dd; color:#0f5132; font-weight:600; border:1px solid #a3cfbb;" title="Label Created / shipped orders updated in the last 24 hours across Marketplace Manager channels">
                                    Label Created: <span id="sof-fulfilled-24h">0</span>
                                </span>
                                <span class="badge fs-6 p-2" id="sof-scan-done-24h-badge" style="background:#cfe2ff; color:#084298; font-weight:600; border:1px solid #9ec5fe;" title="Scan Done — status Received only, updated in the last 24 hours">
                                    Scan Done: <span id="sof-scan-done-24h">0</span>
                                </span>
                                <span class="badge fs-6 p-2" id="sof-invoiced-badge" style="background:#e2d9f3; color:#432874; font-weight:600; border:1px solid #c5b3e6;" title="All Invoiced status orders">
                                    Invoiced: <span id="sof-invoiced-total">0</span>
                                </span>
                                <span class="badge fs-6 p-2" id="sof-delivered-badge" style="background:#cff4fc; color:#055160; font-weight:600; border:1px solid #9eeaf9;" title="Delivered / Received across all marketplaces (Faire DELIVERED, Shein/Reverb Received, etc.)">
                                    Delivered: <span id="sof-delivered-total">0</span>
                                </span>
                                <span class="badge fs-6 p-2" id="sof-all-order-badge" style="background:#e9ecef; color:#343a40; font-weight:600; border:1px solid #ced4da;" title="All marketplace orders from the last 30 days (original status)">
                                    All Order: <span id="sof-all-order-total">0</span>
                                </span>
                            </div>
                            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-end ms-auto" role="group" aria-label="Carrier / platform badges">
                                @foreach(($topBadges ?? []) as $badge)
                                    @php
                                        $badgeKey = $badge['key'] ?? '';
                                        $badgeLabel = $badge['label'] ?? strtoupper($badgeKey);
                                        $badgeLink = $badge['link'] ?? null;
                                        $hasLink = !empty($badgeLink);
                                    @endphp
                                    <span class="sof-top-badge {{ $badgeKey }} {{ $hasLink ? '' : 'is-disabled' }}"
                                          data-badge-key="{{ $badgeKey }}"
                                          data-badge-label="{{ $badgeLabel }}"
                                          data-badge-link="{{ $badgeLink ?? '' }}"
                                          title="{{ $hasLink ? 'Click to open '.$badgeLabel : 'Add a link via the red dot' }}">
                                        <span class="sof-top-badge-label">{{ $badgeLabel }}</span>
                                        <span class="sof-ch-orders-dot {{ $hasLink ? 'green' : 'red' }} sof-top-badge-dot"
                                              title="{{ $hasLink ? 'Double-click to edit link' : 'Click to add link' }}"
                                              role="button"
                                              tabindex="0"></span>
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body pt-0">
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
                                Label Created <span class="badge ms-1" id="sof-fulfilled-tab-count" style="background:#d1e7dd;color:#0f5132;border:1px solid #a3cfbb;">0</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="sof-scan-done-tab" data-bs-toggle="tab"
                                    data-bs-target="#sof-scan-done-pane" type="button" role="tab"
                                    aria-controls="sof-scan-done-pane" aria-selected="false">
                                Scan Done <span class="badge ms-1" id="sof-scan-done-tab-count" style="background:#cfe2ff;color:#084298;border:1px solid #9ec5fe;">0</span>
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
                                Delivered <span class="badge ms-1" id="sof-delivered-tab-count" style="background:#cff4fc;color:#055160;border:1px solid #9eeaf9;">0</span>
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="sof-channels-pane" role="tabpanel" aria-labelledby="sof-channels-tab">
                            <div id="sof-filter-bar" class="mb-2">
                                <div class="d-flex flex-wrap align-items-end gap-3">
                                    <div class="flex-grow-1" style="min-width:200px;">
                                        <label class="sof-filter-label" for="sof-search">Search</label>
                                        <input type="text" id="sof-search" class="form-control form-control-sm" placeholder="Search by Channel or Alias...">
                                    </div>
                                    <div class="d-flex align-items-end gap-2">
                                        <button type="button" class="btn btn-sm btn-primary" id="sof-filter-apply">Apply</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="sof-filter-clear">Clear</button>
                                    </div>
                                </div>
                            </div>
                            <div id="sales-order-fulfillment-table" style="height: calc(100vh - 380px);"></div>
                        </div>

                        <div class="tab-pane fade" id="sof-all-order-pane" role="tabpanel" aria-labelledby="sof-all-order-tab">
                            <div id="sof-all-order-filter-bar" class="mb-2" style="background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;padding:12px 14px;">
                                <div class="d-flex flex-wrap align-items-end gap-3">
                                    <div class="flex-grow-1" style="min-width:200px;">
                                        <label class="sof-filter-label" for="sof-all-order-search">Search</label>
                                        <input type="text" id="sof-all-order-search" class="form-control form-control-sm" placeholder="Search by Channel, Order ID, SKU, Status...">
                                    </div>
                                    <div class="d-flex align-items-end gap-2">
                                        <button type="button" class="btn btn-sm btn-primary" id="sof-all-order-filter-apply">Apply</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="sof-all-order-filter-clear">Clear</button>
                                    </div>
                                </div>
                            </div>
                            <p class="small text-muted mb-2">Marketplace orders from the last 30 days, with original status values.</p>
                            <div id="sof-all-order-table" style="height: calc(100vh - 400px);"></div>
                        </div>

                        <div class="tab-pane fade" id="sof-pending-pane" role="tabpanel" aria-labelledby="sof-pending-tab">
                            <div id="sof-pending-filter-bar" class="mb-2" style="background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;padding:12px 14px;">
                                <div class="d-flex flex-wrap align-items-end gap-3">
                                    <div class="flex-grow-1" style="min-width:200px;">
                                        <label class="sof-filter-label" for="sof-pending-search">Search</label>
                                        <input type="text" id="sof-pending-search" class="form-control form-control-sm" placeholder="Search by Channel, Order ID, SKU, Status...">
                                    </div>
                                    <div class="d-flex align-items-end gap-2">
                                        <button type="button" class="btn btn-sm btn-primary" id="sof-pending-filter-apply">Apply</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="sof-pending-filter-clear">Clear</button>
                                    </div>
                                </div>
                            </div>
                            <div id="sof-pending-table" style="height: calc(100vh - 380px);"></div>
                        </div>

                        <div class="tab-pane fade" id="sof-fulfilled-pane" role="tabpanel" aria-labelledby="sof-fulfilled-tab">
                            <div id="sof-fulfilled-filter-bar" class="mb-2" style="background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;padding:12px 14px;">
                                <div class="d-flex flex-wrap align-items-end gap-3">
                                    <div class="flex-grow-1" style="min-width:200px;">
                                        <label class="sof-filter-label" for="sof-fulfilled-search">Search</label>
                                        <input type="text" id="sof-fulfilled-search" class="form-control form-control-sm" placeholder="Search by Channel, Order ID, SKU, Status...">
                                    </div>
                                    <div class="d-flex align-items-end gap-2">
                                        <button type="button" class="btn btn-sm btn-primary" id="sof-fulfilled-filter-apply">Apply</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="sof-fulfilled-filter-clear">Clear</button>
                                    </div>
                                </div>
                            </div>
                            <p class="small text-muted mb-2">Label Created / shipped orders updated in the last 24 hours.</p>
                            <div id="sof-fulfilled-table" style="height: calc(100vh - 400px);"></div>
                        </div>

                        <div class="tab-pane fade" id="sof-scan-done-pane" role="tabpanel" aria-labelledby="sof-scan-done-tab">
                            <div id="sof-scan-done-filter-bar" class="mb-2" style="background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;padding:12px 14px;">
                                <div class="d-flex flex-wrap align-items-end gap-3">
                                    <div class="flex-grow-1" style="min-width:200px;">
                                        <label class="sof-filter-label" for="sof-scan-done-search">Search</label>
                                        <input type="text" id="sof-scan-done-search" class="form-control form-control-sm" placeholder="Search by Channel, Order ID, SKU, Status...">
                                    </div>
                                    <div class="d-flex align-items-end gap-2">
                                        <button type="button" class="btn btn-sm btn-primary" id="sof-scan-done-filter-apply">Apply</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="sof-scan-done-filter-clear">Clear</button>
                                    </div>
                                </div>
                            </div>
                            <p class="small text-muted mb-2">Scan Done — status Received only, updated in the last 24 hours.</p>
                            <div id="sof-scan-done-table" style="height: calc(100vh - 400px);"></div>
                        </div>

                        <div class="tab-pane fade" id="sof-invoiced-pane" role="tabpanel" aria-labelledby="sof-invoiced-tab">
                            <div id="sof-invoiced-filter-bar" class="mb-2" style="background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;padding:12px 14px;">
                                <div class="d-flex flex-wrap align-items-end gap-3">
                                    <div class="flex-grow-1" style="min-width:200px;">
                                        <label class="sof-filter-label" for="sof-invoiced-search">Search</label>
                                        <input type="text" id="sof-invoiced-search" class="form-control form-control-sm" placeholder="Search by Channel, Order ID, SKU, Status...">
                                    </div>
                                    <div class="d-flex align-items-end gap-2">
                                        <button type="button" class="btn btn-sm btn-primary" id="sof-invoiced-filter-apply">Apply</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="sof-invoiced-filter-clear">Clear</button>
                                    </div>
                                </div>
                            </div>
                            <p class="small text-muted mb-2">All Invoiced status orders.</p>
                            <div id="sof-invoiced-table" style="height: calc(100vh - 400px);"></div>
                        </div>

                        <div class="tab-pane fade" id="sof-delivered-pane" role="tabpanel" aria-labelledby="sof-delivered-tab">
                            <div id="sof-delivered-filter-bar" class="mb-2" style="background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;padding:12px 14px;">
                                <div class="d-flex flex-wrap align-items-end gap-3">
                                    <div class="flex-grow-1" style="min-width:200px;">
                                        <label class="sof-filter-label" for="sof-delivered-search">Search</label>
                                        <input type="text" id="sof-delivered-search" class="form-control form-control-sm" placeholder="Search by Channel, Order ID, SKU, Status...">
                                    </div>
                                    <div class="d-flex align-items-end gap-2">
                                        <button type="button" class="btn btn-sm btn-primary" id="sof-delivered-filter-apply">Apply</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="sof-delivered-filter-clear">Clear</button>
                                    </div>
                                </div>
                            </div>
                            <p class="small text-muted mb-2">Delivered / Received across all marketplaces (Faire DELIVERED, Shein &amp; Reverb Received, etc.).</p>
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
@endsection

@section('script-bottom')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
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

        [pendingTable, fulfilledTable, scanDoneTable, invoicedTable, deliveredTable, allOrderTable].forEach(function (t) {
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
                title: 'Channel',
                field: 'channel_label',
                minWidth: 100,
                headerHozAlign: 'center',
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
                formatter: function (cell) {
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
                },
            },
            {
                title: 'Status',
                field: 'status_label',
                minWidth: 90,
                hozAlign: 'center',
                headerHozAlign: 'center',
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

        if (!q) {
            table.clearFilter(true);
        } else {
            table.setFilter(function (data) {
                return String(data.channel || '').toLowerCase().includes(q)
                    || String(data.alias || '').toLowerCase().includes(q);
            });
        }
        updateSummaryStats();
    }

    table = new Tabulator('#sales-order-fulfillment-table', {
        layout: 'fitColumns',
        placeholder: 'Loading channels…',
        pagination: true,
        paginationSize: 50,
        paginationSizeSelector: [25, 50, 100, true],
        movableColumns: false,
        initialSort: [
            { column: 'pending_count', dir: 'desc' },
        ],
        ajaxURL: '{{ route("sales.order.fulfillment.data") }}',
        ajaxConfig: 'GET',
        ajaxRequestFunc: function (url, config, params) {
            return new Promise(function (resolve, reject) {
                $.ajax({
                    url: url,
                    type: 'GET',
                    data: params,
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
            const invoicedEl = document.getElementById('sof-invoiced-total');
            const deliveredEl = document.getElementById('sof-delivered-total');
            const allOrderEl = document.getElementById('sof-all-order-total');
            if (channelEl) channelEl.textContent = channelCount.toLocaleString();
            if (pendingEl) pendingEl.textContent = pendingTotal.toLocaleString();
            if (fulfilledEl) fulfilledEl.textContent = fulfilled24h.toLocaleString();
            if (scanDoneEl) scanDoneEl.textContent = scanDone24h.toLocaleString();
            if (invoicedEl) invoicedEl.textContent = invoicedTotal.toLocaleString();
            if (deliveredEl) deliveredEl.textContent = deliveredTotal.toLocaleString();
            if (allOrderEl) allOrderEl.textContent = allOrderTotal.toLocaleString();
            const fulfilledTabCount = document.getElementById('sof-fulfilled-tab-count');
            if (fulfilledTabCount && !fulfilledTableLoaded) {
                fulfilledTabCount.textContent = fulfilled24h.toLocaleString();
            }
            const scanDoneTabCount = document.getElementById('sof-scan-done-tab-count');
            if (scanDoneTabCount && !scanDoneTableLoaded) {
                scanDoneTabCount.textContent = scanDone24h.toLocaleString();
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
    });

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
        if (!pendingTable) return;
        const q = ($('#sof-pending-search').val() || '').trim().toLowerCase();
        if (!q) {
            pendingTable.clearFilter(true);
            return;
        }
        pendingTable.setFilter(function (data) {
            return String(data.channel_label || '').toLowerCase().includes(q)
                || String(data.order_id || '').toLowerCase().includes(q)
                || String(data.sku || '').toLowerCase().includes(q)
                || String(data.status_label || data.status || '').toLowerCase().includes(q)
                || String(data.display_title || '').toLowerCase().includes(q);
        });
    }

    function ensurePendingTable() {
        if (pendingTable || pendingTableLoading) {
            if (pendingTable) {
                setTimeout(function () { pendingTable.redraw(true); }, 50);
            }
            return;
        }
        pendingTableLoading = true;

        pendingTable = new Tabulator('#sof-pending-table', {
            layout: 'fitDataFill',
            placeholder: 'Loading pending orders…',
            pagination: true,
            paginationSize: 50,
            paginationSizeSelector: [25, 50, 100, true],
            movableColumns: false,
            initialSort: [
                { column: 'order_date', dir: 'desc' },
            ],
            ajaxURL: '{{ route("sales.order.fulfillment.pending.data") }}',
            ajaxConfig: 'GET',
            ajaxRequestFunc: function (url, config, params) {
                return new Promise(function (resolve, reject) {
                    $.ajax({
                        url: url,
                        type: 'GET',
                        data: params,
                        timeout: 0,
                        success: resolve,
                        error: reject,
                    });
                });
            },
            ajaxResponse: function (url, params, response) {
                pendingRows = (response && response.success && Array.isArray(response.data))
                    ? response.data
                    : [];
                pendingTableLoaded = true;
                pendingTableLoading = false;
                const count = (response && response.count != null)
                    ? Number(response.count)
                    : pendingRows.length;
                const tabCount = document.getElementById('sof-pending-tab-count');
                if (tabCount) tabCount.textContent = count.toLocaleString();
                const pendingEl = document.getElementById('sof-pending-total');
                if (pendingEl) pendingEl.textContent = count.toLocaleString();
                return pendingRows;
            },
            dataLoaded: function () {
                applyPendingFilters();
            },
            columns: orderListColumns('sof-pending-badge'),
        });
    }

    function applyFulfilledFilters() {
        if (!fulfilledTable) return;
        const q = ($('#sof-fulfilled-search').val() || '').trim().toLowerCase();
        if (!q) {
            fulfilledTable.clearFilter(true);
            return;
        }
        fulfilledTable.setFilter(function (data) {
            return String(data.channel_label || '').toLowerCase().includes(q)
                || String(data.order_id || '').toLowerCase().includes(q)
                || String(data.sku || '').toLowerCase().includes(q)
                || String(data.status_label || data.status || '').toLowerCase().includes(q)
                || String(data.display_title || '').toLowerCase().includes(q);
        });
    }

    function ensureFulfilledTable() {
        if (fulfilledTable || fulfilledTableLoading) {
            if (fulfilledTable) {
                setTimeout(function () { fulfilledTable.redraw(true); }, 50);
            }
            return;
        }
        fulfilledTableLoading = true;

        fulfilledTable = new Tabulator('#sof-fulfilled-table', {
            layout: 'fitDataFill',
            placeholder: 'Loading Label Created orders…',
            pagination: true,
            paginationSize: 50,
            paginationSizeSelector: [25, 50, 100, true],
            movableColumns: false,
            initialSort: [
                { column: 'updated_at', dir: 'desc' },
            ],
            ajaxURL: '{{ route("sales.order.fulfillment.fulfilled.data") }}',
            ajaxConfig: 'GET',
            ajaxRequestFunc: function (url, config, params) {
                return new Promise(function (resolve, reject) {
                    $.ajax({
                        url: url,
                        type: 'GET',
                        data: params,
                        timeout: 0,
                        success: resolve,
                        error: reject,
                    });
                });
            },
            ajaxResponse: function (url, params, response) {
                fulfilledRows = (response && response.success && Array.isArray(response.data))
                    ? response.data
                    : [];
                fulfilledTableLoaded = true;
                fulfilledTableLoading = false;
                const count = (response && response.count != null)
                    ? Number(response.count)
                    : fulfilledRows.length;
                const tabCount = document.getElementById('sof-fulfilled-tab-count');
                if (tabCount) tabCount.textContent = count.toLocaleString();
                const fulfilledEl = document.getElementById('sof-fulfilled-24h');
                if (fulfilledEl) fulfilledEl.textContent = count.toLocaleString();
                return fulfilledRows;
            },
            dataLoaded: function () {
                applyFulfilledFilters();
            },
            columns: (function () {
                const cols = orderListColumns('sof-fulfilled-badge');
                cols.forEach(function (c) {
                    if (c.field === 'status_label') {
                        c.title = 'Label Created';
                        c.headerTooltip = 'Label Created status';
                    }
                });
                // After Date (index 3 after Channel, Ch Orders, Order ID, Date) insert Updated + Tracking
                const dateIdx = cols.findIndex(function (c) { return c.field === 'order_date'; });
                const insertAt = dateIdx >= 0 ? dateIdx + 1 : 3;
                cols.splice(insertAt, 0,
                    {
                        title: 'Updated',
                        field: 'updated_at',
                        minWidth: 140,
                        headerHozAlign: 'center',
                        formatter: function (cell) {
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
                        },
                    },
                    {
                        title: 'Tracking',
                        field: 'tracking_number',
                        minWidth: 150,
                        headerHozAlign: 'center',
                        headerTooltip: 'Tracking number from marketplace order payload',
                        formatter: function (cell) {
                            const tracking = (cell.getValue() || '').toString().trim();
                            if (!tracking) {
                                return '<span class="sof-oc-missing">—</span>';
                            }
                            return `<code style="font-size:0.8rem;color:#334155;">${escapeHtml(tracking)}</code>`;
                        },
                    }
                );
                return cols;
            })(),
        });
    }

    function applyScanDoneFilters() {
        if (!scanDoneTable) return;
        const q = ($('#sof-scan-done-search').val() || '').trim().toLowerCase();
        if (!q) {
            scanDoneTable.clearFilter(true);
            return;
        }
        scanDoneTable.setFilter(function (data) {
            return String(data.channel_label || '').toLowerCase().includes(q)
                || String(data.order_id || '').toLowerCase().includes(q)
                || String(data.sku || '').toLowerCase().includes(q)
                || String(data.status_label || data.status || '').toLowerCase().includes(q)
                || String(data.display_title || '').toLowerCase().includes(q);
        });
    }

    function ensureScanDoneTable() {
        if (scanDoneTable || scanDoneTableLoading) {
            if (scanDoneTable) {
                setTimeout(function () { scanDoneTable.redraw(true); }, 50);
            }
            return;
        }
        scanDoneTableLoading = true;

        scanDoneTable = new Tabulator('#sof-scan-done-table', {
            layout: 'fitDataFill',
            placeholder: 'Loading Scan Done orders…',
            pagination: true,
            paginationSize: 50,
            paginationSizeSelector: [25, 50, 100, true],
            movableColumns: false,
            initialSort: [
                { column: 'updated_at', dir: 'desc' },
            ],
            ajaxURL: '{{ route("sales.order.fulfillment.scan.done.data") }}',
            ajaxConfig: 'GET',
            ajaxRequestFunc: function (url, config, params) {
                return new Promise(function (resolve, reject) {
                    $.ajax({
                        url: url,
                        type: 'GET',
                        data: params,
                        timeout: 0,
                        success: resolve,
                        error: reject,
                    });
                });
            },
            ajaxResponse: function (url, params, response) {
                scanDoneRows = (response && response.success && Array.isArray(response.data))
                    ? response.data
                    : [];
                scanDoneTableLoaded = true;
                scanDoneTableLoading = false;
                const count = (response && response.count != null)
                    ? Number(response.count)
                    : scanDoneRows.length;
                const tabCount = document.getElementById('sof-scan-done-tab-count');
                if (tabCount) tabCount.textContent = count.toLocaleString();
                const scanDoneEl = document.getElementById('sof-scan-done-24h');
                if (scanDoneEl) scanDoneEl.textContent = count.toLocaleString();
                return scanDoneRows;
            },
            dataLoaded: function () {
                applyScanDoneFilters();
            },
            columns: (function () {
                const cols = orderListColumns('sof-scan-done-badge');
                cols.forEach(function (c) {
                    if (c.field === 'status_label') {
                        c.title = 'Scan Done';
                        c.headerTooltip = 'Status Received';
                    }
                });
                const dateIdx = cols.findIndex(function (c) { return c.field === 'order_date'; });
                const insertAt = dateIdx >= 0 ? dateIdx + 1 : 3;
                cols.splice(insertAt, 0,
                    {
                        title: 'Updated',
                        field: 'updated_at',
                        minWidth: 140,
                        headerHozAlign: 'center',
                        formatter: function (cell) {
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
                        },
                    },
                    {
                        title: 'Tracking',
                        field: 'tracking_number',
                        minWidth: 150,
                        headerHozAlign: 'center',
                        headerTooltip: 'Tracking number from marketplace order payload',
                        formatter: function (cell) {
                            const tracking = (cell.getValue() || '').toString().trim();
                            if (!tracking) {
                                return '<span class="sof-oc-missing">—</span>';
                            }
                            return `<code style="font-size:0.8rem;color:#334155;">${escapeHtml(tracking)}</code>`;
                        },
                    }
                );
                return cols;
            })(),
        });
    }

    function applyInvoicedFilters() {
        if (!invoicedTable) return;
        const q = ($('#sof-invoiced-search').val() || '').trim().toLowerCase();
        if (!q) {
            invoicedTable.clearFilter(true);
            return;
        }
        invoicedTable.setFilter(function (data) {
            return String(data.channel_label || '').toLowerCase().includes(q)
                || String(data.order_id || '').toLowerCase().includes(q)
                || String(data.sku || '').toLowerCase().includes(q)
                || String(data.status_label || data.status || '').toLowerCase().includes(q)
                || String(data.display_title || '').toLowerCase().includes(q);
        });
    }

    function ensureInvoicedTable() {
        if (invoicedTable || invoicedTableLoading) {
            if (invoicedTable) {
                setTimeout(function () { invoicedTable.redraw(true); }, 50);
            }
            return;
        }
        invoicedTableLoading = true;

        invoicedTable = new Tabulator('#sof-invoiced-table', {
            layout: 'fitDataFill',
            placeholder: 'Loading Invoiced orders…',
            pagination: true,
            paginationSize: 50,
            paginationSizeSelector: [25, 50, 100, true],
            movableColumns: false,
            initialSort: [
                { column: 'updated_at', dir: 'desc' },
            ],
            ajaxURL: '{{ route("sales.order.fulfillment.invoiced.data") }}',
            ajaxConfig: 'GET',
            ajaxRequestFunc: function (url, config, params) {
                return new Promise(function (resolve, reject) {
                    $.ajax({
                        url: url,
                        type: 'GET',
                        data: params,
                        timeout: 0,
                        success: resolve,
                        error: reject,
                    });
                });
            },
            ajaxResponse: function (url, params, response) {
                invoicedRows = (response && response.success && Array.isArray(response.data))
                    ? response.data
                    : [];
                invoicedTableLoaded = true;
                invoicedTableLoading = false;
                const count = (response && response.count != null)
                    ? Number(response.count)
                    : invoicedRows.length;
                const tabCount = document.getElementById('sof-invoiced-tab-count');
                if (tabCount) tabCount.textContent = count.toLocaleString();
                const invoicedEl = document.getElementById('sof-invoiced-total');
                if (invoicedEl) invoicedEl.textContent = count.toLocaleString();
                return invoicedRows;
            },
            dataLoaded: function () {
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
                cols.splice(insertAt, 0,
                    {
                        title: 'Updated',
                        field: 'updated_at',
                        minWidth: 140,
                        headerHozAlign: 'center',
                        formatter: function (cell) {
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
                        },
                    },
                    {
                        title: 'Tracking',
                        field: 'tracking_number',
                        minWidth: 150,
                        headerHozAlign: 'center',
                        headerTooltip: 'Tracking number from marketplace order payload',
                        formatter: function (cell) {
                            const tracking = (cell.getValue() || '').toString().trim();
                            if (!tracking) {
                                return '<span class="sof-oc-missing">—</span>';
                            }
                            return `<code style="font-size:0.8rem;color:#334155;">${escapeHtml(tracking)}</code>`;
                        },
                    }
                );
                return cols;
            })(),
        });
    }

    function applyDeliveredFilters() {
        if (!deliveredTable) return;
        const q = ($('#sof-delivered-search').val() || '').trim().toLowerCase();
        if (!q) {
            deliveredTable.clearFilter(true);
            return;
        }
        deliveredTable.setFilter(function (data) {
            return String(data.channel_label || '').toLowerCase().includes(q)
                || String(data.order_id || '').toLowerCase().includes(q)
                || String(data.sku || '').toLowerCase().includes(q)
                || String(data.status_label || data.status || '').toLowerCase().includes(q)
                || String(data.display_title || '').toLowerCase().includes(q);
        });
    }

    function ensureDeliveredTable() {
        if (deliveredTable || deliveredTableLoading) {
            if (deliveredTable) {
                setTimeout(function () { deliveredTable.redraw(true); }, 50);
            }
            return;
        }
        deliveredTableLoading = true;

        deliveredTable = new Tabulator('#sof-delivered-table', {
            layout: 'fitDataFill',
            placeholder: 'Loading Delivered orders…',
            pagination: true,
            paginationSize: 50,
            paginationSizeSelector: [25, 50, 100, true],
            movableColumns: false,
            initialSort: [
                { column: 'updated_at', dir: 'desc' },
            ],
            ajaxURL: '{{ route("sales.order.fulfillment.delivered.data") }}',
            ajaxConfig: 'GET',
            ajaxRequestFunc: function (url, config, params) {
                return new Promise(function (resolve, reject) {
                    $.ajax({
                        url: url,
                        type: 'GET',
                        data: params,
                        timeout: 0,
                        success: resolve,
                        error: reject,
                    });
                });
            },
            ajaxResponse: function (url, params, response) {
                deliveredRows = (response && response.success && Array.isArray(response.data))
                    ? response.data
                    : [];
                deliveredTableLoaded = true;
                deliveredTableLoading = false;
                const count = (response && response.count != null)
                    ? Number(response.count)
                    : deliveredRows.length;
                const tabCount = document.getElementById('sof-delivered-tab-count');
                if (tabCount) tabCount.textContent = count.toLocaleString();
                const deliveredEl = document.getElementById('sof-delivered-total');
                if (deliveredEl) deliveredEl.textContent = count.toLocaleString();
                return deliveredRows;
            },
            dataLoaded: function () {
                applyDeliveredFilters();
            },
            columns: (function () {
                const cols = orderListColumns('sof-delivered-badge');
                cols.forEach(function (c) {
                    if (c.field === 'status_label') {
                        c.title = 'Delivered';
                        c.headerTooltip = 'Delivered status';
                    }
                });
                const dateIdx = cols.findIndex(function (c) { return c.field === 'order_date'; });
                const insertAt = dateIdx >= 0 ? dateIdx + 1 : 3;
                cols.splice(insertAt, 0,
                    {
                        title: 'Updated',
                        field: 'updated_at',
                        minWidth: 140,
                        headerHozAlign: 'center',
                        formatter: function (cell) {
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
                        },
                    },
                    {
                        title: 'Tracking',
                        field: 'tracking_number',
                        minWidth: 150,
                        headerHozAlign: 'center',
                        headerTooltip: 'Tracking number from marketplace order payload',
                        formatter: function (cell) {
                            const tracking = (cell.getValue() || '').toString().trim();
                            if (!tracking) {
                                return '<span class="sof-oc-missing">—</span>';
                            }
                            return `<code style="font-size:0.8rem;color:#334155;">${escapeHtml(tracking)}</code>`;
                        },
                    }
                );
                return cols;
            })(),
        });
    }

    function applyAllOrderFilters() {
        if (!allOrderTable) return;
        const q = ($('#sof-all-order-search').val() || '').trim().toLowerCase();
        if (!q) {
            allOrderTable.clearFilter(true);
            return;
        }
        allOrderTable.setFilter(function (data) {
            return String(data.channel_label || '').toLowerCase().includes(q)
                || String(data.order_id || '').toLowerCase().includes(q)
                || String(data.sku || '').toLowerCase().includes(q)
                || String(data.status_label || data.status || '').toLowerCase().includes(q)
                || String(data.display_title || '').toLowerCase().includes(q);
        });
    }

    function ensureAllOrderTable() {
        if (allOrderTable || allOrderTableLoading) {
            if (allOrderTable) {
                setTimeout(function () { allOrderTable.redraw(true); }, 50);
            }
            return;
        }
        allOrderTableLoading = true;

        allOrderTable = new Tabulator('#sof-all-order-table', {
            layout: 'fitDataFill',
            placeholder: 'Loading all orders…',
            pagination: true,
            paginationSize: 50,
            paginationSizeSelector: [25, 50, 100, true],
            movableColumns: false,
            initialSort: [
                { column: 'updated_at', dir: 'desc' },
            ],
            ajaxURL: '{{ route("sales.order.fulfillment.all.order.data") }}',
            ajaxConfig: 'GET',
            ajaxRequestFunc: function (url, config, params) {
                return new Promise(function (resolve, reject) {
                    $.ajax({
                        url: url,
                        type: 'GET',
                        data: params,
                        timeout: 0,
                        success: resolve,
                        error: reject,
                    });
                });
            },
            ajaxResponse: function (url, params, response) {
                allOrderRows = (response && response.success && Array.isArray(response.data))
                    ? response.data
                    : [];
                allOrderTableLoaded = true;
                allOrderTableLoading = false;
                const count = (response && response.count != null)
                    ? Number(response.count)
                    : allOrderRows.length;
                const tabCount = document.getElementById('sof-all-order-tab-count');
                if (tabCount) tabCount.textContent = count.toLocaleString();
                const allOrderEl = document.getElementById('sof-all-order-total');
                if (allOrderEl) allOrderEl.textContent = count.toLocaleString();
                return allOrderRows;
            },
            dataLoaded: function () {
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
                cols.splice(insertAt, 0,
                    {
                        title: 'Updated',
                        field: 'updated_at',
                        minWidth: 140,
                        headerHozAlign: 'center',
                        formatter: function (cell) {
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
                        },
                    },
                    {
                        title: 'Tracking',
                        field: 'tracking_number',
                        minWidth: 150,
                        headerHozAlign: 'center',
                        headerTooltip: 'Tracking number from marketplace order payload',
                        formatter: function (cell) {
                            const tracking = (cell.getValue() || '').toString().trim();
                            if (!tracking) {
                                return '<span class="sof-oc-missing">—</span>';
                            }
                            return `<code style="font-size:0.8rem;color:#334155;">${escapeHtml(tracking)}</code>`;
                        },
                    }
                );
                return cols;
            })(),
        });
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
    document.getElementById('sof-invoiced-badge')?.addEventListener('click', function () {
        switchToInvoicedTab();
    });
    document.getElementById('sof-delivered-badge')?.addEventListener('click', function () {
        switchToDeliveredTab();
    });
    document.getElementById('sof-all-order-badge')?.addEventListener('click', function () {
        switchToAllOrderTab();
    });

    $('#sof-filter-apply').on('click', applyFilters);
    $('#sof-search').on('keyup', function (e) {
        if (e.key === 'Enter') {
            applyFilters();
            return;
        }
        applyFilters();
    });
    $('#sof-filter-clear').on('click', function () {
        $('#sof-search').val('');
        applyFilters();
    });

    $('#sof-pending-filter-apply').on('click', applyPendingFilters);
    $('#sof-pending-search').on('keyup', function (e) {
        if (e.key === 'Enter') {
            applyPendingFilters();
            return;
        }
        applyPendingFilters();
    });
    $('#sof-pending-filter-clear').on('click', function () {
        $('#sof-pending-search').val('');
        applyPendingFilters();
    });

    $('#sof-fulfilled-filter-apply').on('click', applyFulfilledFilters);
    $('#sof-fulfilled-search').on('keyup', function (e) {
        if (e.key === 'Enter') {
            applyFulfilledFilters();
            return;
        }
        applyFulfilledFilters();
    });
    $('#sof-fulfilled-filter-clear').on('click', function () {
        $('#sof-fulfilled-search').val('');
        applyFulfilledFilters();
    });

    $('#sof-scan-done-filter-apply').on('click', applyScanDoneFilters);
    $('#sof-scan-done-search').on('keyup', function (e) {
        if (e.key === 'Enter') {
            applyScanDoneFilters();
            return;
        }
        applyScanDoneFilters();
    });
    $('#sof-scan-done-filter-clear').on('click', function () {
        $('#sof-scan-done-search').val('');
        applyScanDoneFilters();
    });

    $('#sof-invoiced-filter-apply').on('click', applyInvoicedFilters);
    $('#sof-invoiced-search').on('keyup', function (e) {
        if (e.key === 'Enter') {
            applyInvoicedFilters();
            return;
        }
        applyInvoicedFilters();
    });
    $('#sof-invoiced-filter-clear').on('click', function () {
        $('#sof-invoiced-search').val('');
        applyInvoicedFilters();
    });

    $('#sof-delivered-filter-apply').on('click', applyDeliveredFilters);
    $('#sof-delivered-search').on('keyup', function (e) {
        if (e.key === 'Enter') {
            applyDeliveredFilters();
            return;
        }
        applyDeliveredFilters();
    });
    $('#sof-delivered-filter-clear').on('click', function () {
        $('#sof-delivered-search').val('');
        applyDeliveredFilters();
    });

    $('#sof-all-order-filter-apply').on('click', applyAllOrderFilters);
    $('#sof-all-order-search').on('keyup', function (e) {
        if (e.key === 'Enter') {
            applyAllOrderFilters();
            return;
        }
        applyAllOrderFilters();
    });
    $('#sof-all-order-filter-clear').on('click', function () {
        $('#sof-all-order-search').val('');
        applyAllOrderFilters();
    });
})();
</script>
@endsection
