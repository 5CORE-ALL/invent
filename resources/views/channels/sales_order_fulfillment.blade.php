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
                <div class="card-body py-3">
                    <div id="sof-summary-stats" class="p-3 bg-light rounded">
                        <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
                            <div class="d-flex flex-wrap gap-2" role="group" aria-label="Summary metrics">
                                <span class="badge bg-primary fs-6 p-2 sof-summary-badge" data-sof-metric="channel_count" style="color: white; font-weight: bold;" title="Active channels — click for history graph">
                                    Channels: <span id="sof-channel-count">0</span><i class="sof-hist-dot" data-sof-metric="channel_count" style="background:#6c757d;" title="History trend"></i>
                                </span>
                                <span class="badge fs-6 p-2 sof-summary-badge" id="sof-pending-total-badge" data-sof-metric="pending_total" style="background:#fff3cd; color:#856404; font-weight:600; border:1px solid #ffe69c;" title="Pending — click for history graph">
                                    Pending: <span id="sof-pending-total">0</span><i class="sof-hist-dot" data-sof-metric="pending_total" style="background:#6c757d;" title="History trend"></i>
                                </span>
                                <span class="badge fs-6 p-2 sof-summary-badge" id="sof-fulfilled-24h-badge" data-sof-metric="fulfilled_24h" style="background:#d1e7dd; color:#0f5132; font-weight:600; border:1px solid #a3cfbb;" title="Label Created / No Scan — click for history graph">
                                    Label Created / No Scan: <span id="sof-fulfilled-24h">0</span><i class="sof-hist-dot" data-sof-metric="fulfilled_24h" style="background:#6c757d;" title="History trend"></i>
                                </span>
                                <span class="badge fs-6 p-2 sof-summary-badge" id="sof-scan-done-24h-badge" data-sof-metric="scan_done_24h" style="background:#cfe2ff; color:#084298; font-weight:600; border:1px solid #9ec5fe;" title="Shipped/Received — click for history graph">
                                    Shipped/Received: <span id="sof-scan-done-24h">0</span><i class="sof-hist-dot" data-sof-metric="scan_done_24h" style="background:#6c757d;" title="History trend"></i>
                                </span>
                                <span class="badge fs-6 p-2 sof-summary-badge" id="sof-in-transit-badge" data-sof-metric="in_transit_total" style="background:#ffe5d0; color:#9a3412; font-weight:600; border:1px solid #fdba74;" title="In Transit — click for history graph">
                                    In Transit: <span id="sof-in-transit-total">0</span><i class="sof-hist-dot" data-sof-metric="in_transit_total" style="background:#6c757d;" title="History trend"></i>
                                </span>
                                <span class="badge fs-6 p-2 sof-summary-badge" id="sof-in-received-badge" data-sof-metric="in_received_total" style="background:#d1fae5; color:#065f46; font-weight:600; border:1px solid #6ee7b7;" title="In Received — click for history graph">
                                    In Received: <span id="sof-in-received-total">0</span><i class="sof-hist-dot" data-sof-metric="in_received_total" style="background:#6c757d;" title="History trend"></i>
                                </span>
                                <span class="badge fs-6 p-2 sof-summary-badge" id="sof-invoiced-badge" data-sof-metric="invoiced_total" style="background:#e2d9f3; color:#432874; font-weight:600; border:1px solid #c5b3e6;" title="Invoiced — click for history graph">
                                    Invoiced: <span id="sof-invoiced-total">0</span><i class="sof-hist-dot" data-sof-metric="invoiced_total" style="background:#6c757d;" title="History trend"></i>
                                </span>
                                <span class="badge fs-6 p-2 sof-summary-badge" id="sof-delivered-badge" data-sof-metric="delivered_total" style="background:#cff4fc; color:#055160; font-weight:600; border:1px solid #9eeaf9;" title="Delivered — click for history graph">
                                    Delivered: <span id="sof-delivered-total">0</span><i class="sof-hist-dot" data-sof-metric="delivered_total" style="background:#6c757d;" title="History trend"></i>
                                </span>
                                <span class="badge fs-6 p-2 sof-summary-badge" id="sof-all-order-badge" data-sof-metric="all_order_total" style="background:#e9ecef; color:#343a40; font-weight:600; border:1px solid #ced4da;" title="All Order — click for history graph">
                                    All Order: <span id="sof-all-order-total">0</span><i class="sof-hist-dot" data-sof-metric="all_order_total" style="background:#6c757d;" title="History trend"></i>
                                </span>
                            </div>
                            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-end ms-auto" role="group" aria-label="Carrier / platform badges">
                                <button type="button"
                                        id="sof-pull-tracking-btn"
                                        class="btn btn-sm btn-outline-secondary"
                                        title="Pull tracking numbers from Shopify fulfillments into this page">
                                    <i class="mdi mdi-barcode-scan me-1"></i>
                                    <span class="sof-pull-tracking-label">Pull Tracking Number</span>
                                </button>
                                <button type="button"
                                        id="sof-refresh-shipment-btn"
                                        class="btn btn-sm btn-outline-primary"
                                        title="Refresh open shipment statuses via USPS / UPS APIs">
                                    <i class="mdi mdi-truck-fast-outline me-1"></i>
                                    <span class="sof-refresh-shipment-label">Update Shipment Status</span>
                                </button>
                                @foreach(($topBadges ?? []) as $badge)
                                    @php
                                        $badgeKey = $badge['key'] ?? '';
                                        $badgeLabel = $badge['label'] ?? strtoupper($badgeKey);
                                        $badgeLink = $badge['link'] ?? null;
                                        $hasLink = !empty($badgeLink);
                                        $gofoApiReady = $badgeKey === 'gofo' && !empty($gofoApiConfigured);
                                    @endphp
                                    <span class="sof-top-badge {{ $badgeKey }} {{ ($hasLink || $gofoApiReady) ? '' : 'is-disabled' }} {{ $gofoApiReady ? 'is-api-ready' : '' }}"
                                          data-badge-key="{{ $badgeKey }}"
                                          data-badge-label="{{ $badgeLabel }}"
                                          data-badge-link="{{ $badgeLink ?? '' }}"
                                          data-gofo-api="{{ $gofoApiReady ? '1' : '0' }}"
                                          title="{{ $gofoApiReady ? 'Click to open GOFO API tools' : ($hasLink ? 'Click to open '.$badgeLabel : 'Add a link via the red dot') }}">
                                        <span class="sof-top-badge-label">{{ $badgeLabel }}</span>
                                        <span class="sof-ch-orders-dot {{ ($hasLink || $gofoApiReady) ? 'green' : 'red' }} sof-top-badge-dot"
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
                    <div id="sof-date-filter-bar" class="mb-3" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px 14px;">
                        <div class="d-flex flex-wrap align-items-end gap-3">
                            <div>
                                <label class="sof-filter-label" for="sof-date-from">From</label>
                                <input type="date" id="sof-date-from" class="form-control form-control-sm" style="min-width:150px;">
                            </div>
                            <div>
                                <label class="sof-filter-label" for="sof-date-to">To</label>
                                <input type="date" id="sof-date-to" class="form-control form-control-sm" style="min-width:150px;">
                            </div>
                            <div class="d-flex align-items-end gap-2">
                                <button type="button" class="btn btn-sm btn-primary" id="sof-date-filter-apply">Apply</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="sof-date-filter-clear" title="Reset to last 30 days">Clear</button>
                            </div>
                            <div class="small text-muted ms-auto pb-1" id="sof-date-filter-hint">Order date range (default: last 30 days)</div>
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
                            <p class="small text-muted mb-2 sof-date-scope-hint">Marketplace orders in the selected date range, with original status values.</p>
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
                            <p class="small text-muted mb-2 sof-date-scope-hint">Pending / unfulfilled orders in the selected date range.</p>
                            <div id="sof-pending-table" style="height: calc(100vh - 400px);"></div>
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
                            <p class="small text-muted mb-2 sof-date-scope-hint">Label Created / No Scan orders in the selected date range.</p>
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
                            <p class="small text-muted mb-2 sof-date-scope-hint">Shipped/Received orders in the selected date range.</p>
                            <div id="sof-scan-done-table" style="height: calc(100vh - 400px);"></div>
                        </div>

                        <div class="tab-pane fade" id="sof-in-transit-pane" role="tabpanel" aria-labelledby="sof-in-transit-tab">
                            <div id="sof-in-transit-filter-bar" class="mb-2" style="background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;padding:12px 14px;">
                                <div class="d-flex flex-wrap align-items-end gap-3">
                                    <div class="flex-grow-1" style="min-width:200px;">
                                        <label class="sof-filter-label" for="sof-in-transit-search">Search</label>
                                        <input type="text" id="sof-in-transit-search" class="form-control form-control-sm" placeholder="Search by Channel, Order ID, SKU, Status...">
                                    </div>
                                    <div class="d-flex align-items-end gap-2">
                                        <button type="button" class="btn btn-sm btn-primary" id="sof-in-transit-filter-apply">Apply</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="sof-in-transit-filter-clear">Clear</button>
                                    </div>
                                </div>
                            </div>
                            <p class="small text-muted mb-2 sof-date-scope-hint">In Transit orders in the selected date range.</p>
                            <div id="sof-in-transit-table" style="height: calc(100vh - 400px);"></div>
                        </div>

                        <div class="tab-pane fade" id="sof-in-received-pane" role="tabpanel" aria-labelledby="sof-in-received-tab">
                            <div id="sof-in-received-filter-bar" class="mb-2" style="background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;padding:12px 14px;">
                                <div class="d-flex flex-wrap align-items-end gap-3">
                                    <div class="flex-grow-1" style="min-width:200px;">
                                        <label class="sof-filter-label" for="sof-in-received-search">Search</label>
                                        <input type="text" id="sof-in-received-search" class="form-control form-control-sm" placeholder="Search by Channel, Order ID, SKU, Status...">
                                    </div>
                                    <div class="d-flex align-items-end gap-2">
                                        <button type="button" class="btn btn-sm btn-primary" id="sof-in-received-filter-apply">Apply</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="sof-in-received-filter-clear">Clear</button>
                                    </div>
                                </div>
                            </div>
                            <p class="small text-muted mb-2 sof-date-scope-hint">In Received orders in the selected date range.</p>
                            <div id="sof-in-received-table" style="height: calc(100vh - 400px);"></div>
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
                            <p class="small text-muted mb-2 sof-date-scope-hint">Invoiced orders in the selected date range.</p>
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

    function sofFormatDateInput(d) {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + day;
    }

    function sofDefaultDateFrom() {
        const d = new Date();
        d.setDate(d.getDate() - 30);
        return sofFormatDateInput(d);
    }

    function sofDefaultDateTo() {
        return sofFormatDateInput(new Date());
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
        return {
            date_from: fromEl ? (fromEl.value || '') : '',
            date_to: toEl ? (toEl.value || '') : '',
        };
    }

    function sofUpdateDateFilterHint() {
        const p = sofDateParams();
        const hint = document.getElementById('sof-date-filter-hint');
        if (!hint) return;
        if (p.date_from && p.date_to) {
            hint.textContent = 'Showing order dates ' + p.date_from + ' → ' + p.date_to;
        } else {
            hint.textContent = 'Order date range (default: last 30 days)';
        }
    }

    function sofReloadAllTablesForDateRange() {
        sofUpdateDateFilterHint();
        [table, pendingTable, fulfilledTable, scanDoneTable, inTransitTable, inReceivedTable, invoicedTable, deliveredTable, allOrderTable]
            .forEach(function (t) {
                if (t && typeof t.replaceData === 'function') {
                    t.replaceData();
                }
            });
    }

    sofInitDateFilterDefaults();

    $('#sof-date-filter-apply').on('click', function () {
        const from = ($('#sof-date-from').val() || '').trim();
        const to = ($('#sof-date-to').val() || '').trim();
        if (from && to && from > to) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'warning', title: 'Invalid dates', text: 'From date must be on or before To date.' });
            } else {
                alert('From date must be on or before To date.');
            }
            return;
        }
        sofReloadAllTablesForDateRange();
    });

    $('#sof-date-filter-clear').on('click', function () {
        $('#sof-date-from').val(sofDefaultDateFrom());
        $('#sof-date-to').val(sofDefaultDateTo());
        sofReloadAllTablesForDateRange();
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
                minWidth: 170,
                headerHozAlign: 'center',
                headerSort: true,
                sorter: sofStringSorter,
                headerTooltip: 'Tracking number and shipment status from Shopify / marketplace',
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
        if (!tracking && !shipStatus) {
            return '<span class="sof-oc-missing">—</span>';
        }
        let html = '';
        if (tracking) {
            html += `<code style="font-size:0.78rem;color:#334155;word-break:break-all;">${escapeHtml(tracking)}</code>`;
        } else {
            html += '<span class="sof-oc-missing">No #</span>';
        }
        if (shipStatus) {
            html += `<div style="font-size:0.7rem;color:#64748b;line-height:1.2;margin-top:2px;">${escapeHtml(shipStatus)}</div>`;
        }
        if (detail && shipStatus) {
            html += `<div style="font-size:0.68rem;color:#94a3b8;line-height:1.2;" title="${escapeHtml(detail)}">${escapeHtml(detail.length > 42 ? detail.slice(0, 42) + '…' : detail)}</div>`;
        }
        return html;
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
        return formatCarrierBadgeHtml(cell.getValue());
    }

    function buildPulledTrackingTableHtml(rows) {
        const list = Array.isArray(rows) ? rows : [];
        if (!list.length) {
            return '<p class="text-muted mb-0" style="font-size:0.9rem;">No Shopify orders were checked.</p>';
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
        if (fulfilledTable) {
            fulfilledTableLoaded = false;
            fulfilledTable.replaceData();
        }
        if (inTransitTable) {
            inTransitTableLoaded = false;
            inTransitTable.replaceData();
        }
        if (deliveredTable) {
            deliveredTableLoaded = false;
            deliveredTable.replaceData();
        }
        if (scanDoneTable) {
            scanDoneTableLoaded = false;
            scanDoneTable.replaceData();
        }
        if (table) {
            table.replaceData();
        }
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

        pendingTable = new Tabulator('#sof-pending-table', Object.assign({}, sofLocalTableOpts, {
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
                return pendingRows;
            },
            dataLoaded: function () {
                applyPendingFilters();
            },
            columns: orderListColumns('sof-pending-badge'),
        }));
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
                || String(data.tracking_number || '').toLowerCase().includes(q)
                || String(data.tracking_company || '').toLowerCase().includes(q)
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

        fulfilledTable = new Tabulator('#sof-fulfilled-table', Object.assign({}, sofLocalTableOpts, {
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
                return fulfilledRows;
            },
            dataLoaded: function () {
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

        scanDoneTable = new Tabulator('#sof-scan-done-table', Object.assign({}, sofLocalTableOpts, {
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
                return scanDoneRows;
            },
            dataLoaded: function () {
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
    }

    function applyInTransitFilters() {
        if (!inTransitTable) return;
        const q = ($('#sof-in-transit-search').val() || '').trim().toLowerCase();
        if (!q) {
            inTransitTable.clearFilter(true);
            return;
        }
        inTransitTable.setFilter(function (data) {
            return String(data.channel_label || '').toLowerCase().includes(q)
                || String(data.order_id || '').toLowerCase().includes(q)
                || String(data.sku || '').toLowerCase().includes(q)
                || String(data.status_label || data.status || '').toLowerCase().includes(q)
                || String(data.display_title || '').toLowerCase().includes(q);
        });
    }

    function ensureInTransitTable() {
        if (inTransitTable || inTransitTableLoading) {
            if (inTransitTable) {
                setTimeout(function () { inTransitTable.redraw(true); }, 50);
            }
            return;
        }
        inTransitTableLoading = true;

        inTransitTable = new Tabulator('#sof-in-transit-table', Object.assign({}, sofLocalTableOpts, {
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
                return inTransitRows;
            },
            dataLoaded: function () {
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
    }

    function applyInReceivedFilters() {
        if (!inReceivedTable) return;
        const q = ($('#sof-in-received-search').val() || '').trim().toLowerCase();
        if (!q) {
            inReceivedTable.clearFilter(true);
            return;
        }
        inReceivedTable.setFilter(function (data) {
            return String(data.channel_label || '').toLowerCase().includes(q)
                || String(data.order_id || '').toLowerCase().includes(q)
                || String(data.sku || '').toLowerCase().includes(q)
                || String(data.status_label || data.status || '').toLowerCase().includes(q)
                || String(data.display_title || '').toLowerCase().includes(q);
        });
    }

    function ensureInReceivedTable() {
        if (inReceivedTable || inReceivedTableLoading) {
            if (inReceivedTable) {
                setTimeout(function () { inReceivedTable.redraw(true); }, 50);
            }
            return;
        }
        inReceivedTableLoading = true;

        inReceivedTable = new Tabulator('#sof-in-received-table', Object.assign({}, sofLocalTableOpts, {
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
                return inReceivedRows;
            },
            dataLoaded: function () {
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

        invoicedTable = new Tabulator('#sof-invoiced-table', Object.assign({}, sofLocalTableOpts, {
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
                cols.splice(insertAt, 0, ...sofTrackingColumns());
                return cols;
            })(),
        }));
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

        deliveredTable = new Tabulator('#sof-delivered-table', Object.assign({}, sofLocalTableOpts, {
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

        allOrderTable = new Tabulator('#sof-all-order-table', Object.assign({}, sofLocalTableOpts, {
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
                cols.splice(insertAt, 0, ...sofTrackingColumns());
                return cols;
            })(),
        }));
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

    $('#sof-in-transit-filter-apply').on('click', applyInTransitFilters);
    $('#sof-in-transit-search').on('keyup', function (e) {
        if (e.key === 'Enter') {
            applyInTransitFilters();
            return;
        }
        applyInTransitFilters();
    });
    $('#sof-in-transit-filter-clear').on('click', function () {
        $('#sof-in-transit-search').val('');
        applyInTransitFilters();
    });

    $('#sof-in-received-filter-apply').on('click', applyInReceivedFilters);
    $('#sof-in-received-search').on('keyup', function (e) {
        if (e.key === 'Enter') {
            applyInReceivedFilters();
            return;
        }
        applyInReceivedFilters();
    });
    $('#sof-in-received-filter-clear').on('click', function () {
        $('#sof-in-received-search').val('');
        applyInReceivedFilters();
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

    $('#sof-pull-tracking-btn').on('click', function () {
        const $btn = $(this);
        if ($btn.prop('disabled')) return;
        const $label = $btn.find('.sof-pull-tracking-label');
        const prev = $label.text();
        $btn.prop('disabled', true);
        $label.text('Pulling…');

        fetch('{{ route("sales.order.fulfillment.pull.tracking.numbers") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ limit: 40 }),
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); })
            .then(function (res) {
                const msg = (res.json && res.json.message) ? res.json.message : (res.ok ? 'Done.' : 'Pull failed.');
                const rows = (res.json && res.json.data) ? res.json.data : [];
                const summary = (res.json && res.json.summary) ? res.json.summary : {};
                const summaryLine = 'Checked: ' + (summary.checked || 0)
                    + ' · With tracking: ' + (summary.with_tracking || 0)
                    + ' · Saved: ' + (summary.updated || 0)
                    + ' · Empty: ' + (summary.empty || 0);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: res.ok ? 'success' : 'error',
                        title: res.ok ? 'Pulled tracking numbers' : 'Pull failed',
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
                if (res.ok) {
                    reloadSofTrackingTables();
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

    $('#sof-refresh-shipment-btn').on('click', function () {
        const $btn = $(this);
        if ($btn.prop('disabled')) return;
        const $label = $btn.find('.sof-refresh-shipment-label');
        const prev = $label.text();
        $btn.prop('disabled', true);
        $label.text('Updating…');

        fetch('{{ route("sales.order.fulfillment.refresh.shipment.status") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            // Modest batch + repair quota-poisoned rows; full open set is paced by cron (~1–2×/day).
            body: JSON.stringify({ limit: 80, stale: 30, repair_quota: true }),
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); })
            .then(function (res) {
                const msg = (res.json && res.json.message) ? res.json.message : (res.ok ? 'Updated.' : 'Update failed.');
                const detail = (res.json && res.json.output) ? String(res.json.output) : '';
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: res.ok ? 'success' : 'error',
                        title: res.ok ? 'Shipment status' : 'Update failed',
                        html: '<div style="text-align:left;white-space:pre-wrap;font-size:0.9rem;">'
                            + escapeHtml(msg)
                            + (detail ? '<hr><code style="font-size:0.8rem;">' + escapeHtml(detail) + '</code>' : '')
                            + '</div>',
                        timer: res.ok ? 4500 : undefined,
                        showConfirmButton: !res.ok,
                    });
                } else {
                    alert(msg + (detail ? '\n\n' + detail : ''));
                }
                if (res.ok) {
                    reloadSofTrackingTables();
                }
            })
            .catch(function (err) {
                const msg = err && err.message ? err.message : 'Network error';
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Update failed', text: msg });
                } else {
                    alert(msg);
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
