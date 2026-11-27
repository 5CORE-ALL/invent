@extends('layouts.vertical', ['title' => 'Sales Loss Order', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .slo-channel-name {
            font-weight: 600;
            color: #1e293b;
            text-decoration: none;
        }
        .slo-channel-name.has-link { color: #0d6efd; }
        .slo-channel-name.has-link:hover { text-decoration: underline; }

        #sales-loss-order-table.tabulator .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }
        #sales-loss-order-table.tabulator .tabulator-header .tabulator-col {
            background-color: #e6e6e6;
            height: 80px !important;
            overflow: visible;
        }
        #sales-loss-order-table.tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
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
            pointer-events: none;
        }
        #sales-loss-order-table.tabulator .tabulator-header .tabulator-col.tabulator-sortable {
            cursor: pointer;
        }
        #sales-loss-order-table .tabulator-row .tabulator-cell {
            vertical-align: middle;
        }
        #sales-loss-order-table .tabulator-row .tabulator-cell:has(.slo-order-id-wrap),
        #sales-loss-order-table .tabulator-row .tabulator-cell:has(.slo-text-dot-wrap),
        #sales-loss-order-table .tabulator-row .tabulator-cell:has(.slo-tracking-cell) {
            overflow: visible !important;
        }
        #sales-loss-order-table .tabulator-row:has(.slo-tracking-cell:hover) {
            z-index: 8;
        }

        #slo-toolbar {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 6px 8px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        #slo-toolbar-row1,
        #slo-toolbar-row2 {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            gap: 6px;
            min-width: 0;
            overflow-x: auto;
        }
        #slo-toolbar .slo-summary-badge {
            font-size: 0.72rem !important;
            padding: 0.2rem 0.45rem !important;
            line-height: 1.15;
            font-weight: 600 !important;
            white-space: nowrap;
            flex-shrink: 0;
        }
        #slo-gpft-low-badge,
        #slo-groi-low-badge,
        #slo-npft-alert-badge,
        #slo-nroi-alert-badge {
            cursor: pointer;
            user-select: none;
        }
        .slo-alert-badge .fa-triangle-exclamation {
            color: #dc3545;
            margin-right: 2px;
        }
        #slo-gpft-low-badge:hover,
        #slo-groi-low-badge:hover,
        #slo-npft-alert-badge:hover,
        #slo-nroi-alert-badge:hover {
            filter: brightness(0.97);
        }
        #slo-gpft-low-badge.is-active,
        #slo-groi-low-badge.is-active,
        #slo-npft-alert-badge.is-active,
        #slo-nroi-alert-badge.is-active {
            box-shadow: inset 0 0 0 2px #842029;
        }
        #slo-toolbar .form-control-sm,
        #slo-toolbar .form-select-sm,
        #slo-toolbar .input-group-sm > .form-control,
        #slo-toolbar .input-group-sm > .input-group-text {
            min-height: 28px;
            height: 28px;
            padding-top: 0.15rem;
            padding-bottom: 0.15rem;
            font-size: 0.78rem;
        }
        #slo-toolbar .slo-filter-field { flex-shrink: 0; }
        #slo-toolbar #slo-order-search {
            min-width: 180px;
            flex: 1 1 180px;
        }
        #slo-date-filter-hint {
            font-size: 0.7rem;
            white-space: nowrap;
            color: #64748b;
            margin-left: auto;
            flex-shrink: 0;
        }
        .slo-oc-missing { color: #adb5bd; }
        .slo-status-badge {
            display: inline-block;
            background: #f8d7da;
            color: #842029;
            font-weight: 500;
            font-size: 0.8rem;
            padding: 0.35rem 0.7rem;
            border-radius: 50rem;
            border: 1px solid #f5c2c7;
            line-height: 1.2;
            white-space: nowrap;
        }
        .slo-order-id-wrap {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .slo-order-id-dot {
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #0ab39c;
            box-shadow: 0 0 0 2px rgba(10, 179, 156, 0.22);
            cursor: default;
        }
        .slo-order-id-popover {
            display: none;
            position: absolute;
            left: calc(100% + 10px);
            top: 50%;
            transform: translateY(-50%);
            z-index: 40;
            min-width: 160px;
            max-width: 320px;
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 8px 10px;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
            align-items: center;
            gap: 8px;
        }
        .slo-order-id-wrap:hover .slo-order-id-popover { display: flex; }
        .slo-order-id-text {
            font-size: 0.8rem;
            font-weight: 600;
            color: #0f172a;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .slo-order-id-text a { color: #0d6efd; text-decoration: none; }
        .slo-order-id-text a:hover { text-decoration: underline; }
        .slo-order-id-copy {
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
        .slo-order-id-copy:hover { background: #e2e8f0; color: #0f172a; }
        .slo-order-id-copy.copied { background: #d1e7dd; color: #0f5132; }
        .slo-text-dot-wrap {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .slo-text-dot {
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #6c8cff;
            box-shadow: 0 0 0 2px rgba(108, 140, 255, 0.22);
        }
        .slo-text-dot-box {
            display: none;
            position: absolute;
            right: calc(100% + 10px);
            top: 50%;
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
        .slo-text-dot-wrap:hover .slo-text-dot-box { display: block; }
        .slo-sku-cell {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            max-width: 100%;
        }
        .slo-sku-cell code {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .slo-sku-copy {
            border: none;
            background: #f1f5f9;
            color: #475569;
            width: 22px;
            height: 22px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            padding: 0;
            font-size: 11px;
        }
        .slo-tracking-cell {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .slo-ch-orders-dot {
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            flex-shrink: 0;
            cursor: default;
        }
        .slo-ch-orders-dot.red { background: #f06548; box-shadow: 0 0 0 2px rgba(240, 101, 72, 0.2); }
        .slo-ch-orders-dot.green { background: #0ab39c; box-shadow: 0 0 0 2px rgba(10, 179, 156, 0.2); }
        .slo-tracking-cell:hover .slo-ch-orders-dot.green {
            box-shadow: 0 0 0 3px rgba(10, 179, 156, 0.35);
        }
        .slo-tracking-popover {
            display: none;
            position: absolute;
            left: calc(100% + 10px);
            top: 50%;
            transform: translateY(-50%);
            z-index: 50;
            min-width: 180px;
            max-width: 360px;
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 8px 10px;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
            align-items: center;
            gap: 8px;
        }
        .slo-tracking-cell:hover .slo-tracking-popover { display: flex; }
        .slo-tracking-popover::before {
            content: '';
            position: absolute;
            right: 100%;
            top: 0;
            bottom: 0;
            width: 12px;
        }
        .slo-tracking-num {
            font-size: 0.8rem;
            font-weight: 600;
            color: #0f172a;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .slo-tracking-copy {
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
            font-size: 0.75rem;
        }
        .slo-tracking-copy:hover { background: #e2e8f0; color: #0f172a; }
        .slo-tracking-copy.copied { background: #d1e7dd; color: #0f5132; }
        .slo-carrier-badge {
            display: inline-block;
            font-size: 0.72rem;
            font-weight: 600;
            line-height: 1.2;
            padding: 0.2rem 0.45rem;
            border-radius: 0.35rem;
            border: 1px solid #e2e8f0;
            background: #f1f5f9;
            color: #475569;
            white-space: nowrap;
        }
        .slo-dt-cell {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            line-height: 1.15;
            font-weight: 700;
            color: #0f172a;
        }
        .slo-dt-date {
            white-space: nowrap;
        }
        .slo-dt-time {
            font-size: 0.7rem;
            font-weight: 600;
            color: #64748b;
            white-space: nowrap;
        }
        .slo-dil { font-weight: 600; }
        .slo-dil-red { color: #a00211; }
        .slo-dil-yellow { color: #ffc107; }
        .slo-dil-green { color: #28a745; }
        .slo-dil-pink { color: #e83e8c; }
        .slo-dil-zero { color: #6c757d; }

        #sales-loss-order-table.tabulator .tabulator-header .tabulator-col[tabulator-field="cost_ship"] .tabulator-col-title {
            writing-mode: horizontal-tb;
            transform: none;
            height: auto;
            font-size: 14px;
        }
        .slo-cs-btn {
            width: 28px;
            height: 28px;
            border: none;
            border-radius: 8px;
            background: linear-gradient(180deg, #eef4ff 0%, #dbe7ff 100%);
            color: #3b6fd6;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 1px 0 rgba(59, 111, 214, 0.15);
        }
        .slo-cs-btn:hover {
            background: linear-gradient(180deg, #dbe7ff 0%, #c7d8ff 100%);
            color: #1d4ed8;
        }
        .slo-cs-modal .modal-content {
            border: 0;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.18);
        }
        .slo-cs-modal .modal-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 55%, #2563eb 140%);
            color: #fff;
            border: 0;
            padding: 1rem 1.25rem;
        }
        .slo-cs-modal .modal-title {
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: 0.01em;
        }
        .slo-cs-modal .btn-close {
            filter: invert(1) grayscale(1) brightness(2);
        }
        .slo-cs-modal .modal-body {
            padding: 1.15rem 1.2rem 1.25rem;
            background: #f8fafc;
        }
        .slo-cs-hero {
            display: flex;
            align-items: center;
            gap: 14px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 14px;
        }
        .slo-cs-hero img,
        .slo-cs-hero .slo-cs-hero-img {
            width: 64px;
            height: 64px;
            object-fit: contain;
            border-radius: 10px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            flex-shrink: 0;
        }
        .slo-cs-hero .slo-cs-hero-img.is-missing {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            font-size: 20px;
        }
        .slo-cs-hero-meta { min-width: 0; }
        .slo-cs-hero-sku {
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f766e;
            word-break: break-word;
        }
        .slo-cs-hero-title {
            font-size: 0.82rem;
            color: #64748b;
            line-height: 1.35;
            margin-top: 2px;
        }
        .slo-cs-hero-sub {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 6px;
        }
        .slo-cs-chip {
            font-size: 0.7rem;
            font-weight: 600;
            padding: 2px 7px;
            border-radius: 999px;
            background: #eef2ff;
            color: #3730a3;
        }
        .slo-cs-section-label {
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #64748b;
            margin: 0 0 8px 2px;
        }
        .slo-cs-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            margin-bottom: 12px;
        }
        .slo-cs-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 10px 11px 11px;
            min-height: 78px;
        }
        .slo-cs-card .slo-cs-kicker {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.72rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .slo-cs-card .slo-cs-kicker i {
            width: 18px;
            height: 18px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: #fff;
        }
        .slo-cs-card .slo-cs-value {
            margin-top: 6px;
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.25;
            word-break: break-word;
        }
        .slo-cs-card .slo-cs-value.is-empty { color: #94a3b8; font-weight: 600; }
        .slo-cs-ico-cp { background: #0d9488; }
        .slo-cs-ico-frg { background: #d97706; }
        .slo-cs-ico-lp { background: #4f46e5; }
        .slo-cs-ico-dim { background: #334155; }
        .slo-cs-ico-wta { background: #16a34a; }
        .slo-cs-ico-wtd { background: #e11d48; }
        .slo-cs-ico-ship { background: #2563eb; }
        .slo-cs-ico-temu { background: #ea580c; }
        .slo-cs-ico-bb { background: #7c3aed; }
        @media (max-width: 576px) {
            .slo-cs-grid { grid-template-columns: 1fr 1fr; }
        }

        /* ---------- Loss summary badges + modal (lossx) ---------- */
        .lossx-strip {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 14px;
        }
        .lossx-card {
            --g1: #7c3aed; --g2: #ec4899;
            position: relative;
            overflow: hidden;
            text-align: left;
            border: 0;
            border-radius: 20px;
            padding: 14px 16px 12px;
            color: #fff;
            cursor: pointer;
            background: linear-gradient(135deg, var(--g1), var(--g2));
            box-shadow: 0 10px 24px -10px var(--g1);
            transition: transform .18s ease, box-shadow .18s ease, filter .18s ease;
        }
        .lossx-card::after {
            content: '';
            position: absolute; right: -30px; top: -30px;
            width: 110px; height: 110px; border-radius: 50%;
            background: rgba(255,255,255,.16);
            filter: blur(2px);
        }
        .lossx-card:hover { transform: translateY(-3px) scale(1.01); box-shadow: 0 16px 30px -10px var(--g1); filter: saturate(1.15); }
        .lossx-card:active { transform: scale(.98); }
        .lossx-card:focus-visible { outline: 3px solid rgba(255,255,255,.8); outline-offset: 2px; }
        .lossx-card[data-p="l30"] { --g1: #7c3aed; --g2: #ec4899; }
        .lossx-card[data-p="l7"] { --g1: #f97316; --g2: #e11d48; }
        .lossx-card[data-p="yesterday"] { --g1: #0ea5e9; --g2: #6366f1; }
        .lossx-card[data-p="today"] { --g1: #10b981; --g2: #0ea5e9; }
        .lossx-pill {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: .72rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase;
            padding: 3px 10px; border-radius: 999px;
            background: rgba(255,255,255,.22); backdrop-filter: blur(6px);
        }
        .lossx-big { font-size: 2.1rem; font-weight: 800; line-height: 1.05; margin: 8px 0 2px; letter-spacing: -.02em; font-variant-numeric: tabular-nums; }
        .lossx-big small { font-size: .8rem; font-weight: 700; opacity: .85; margin-left: 4px; letter-spacing: 0; }
        .lossx-sub { font-size: .76rem; opacity: .92; }
        .lossx-bar { height: 6px; border-radius: 99px; background: rgba(255,255,255,.25); margin: 10px 0 8px; overflow: hidden; }
        .lossx-bar > i { display: block; height: 100%; width: 0; border-radius: 99px; background: #fff; transition: width .7s cubic-bezier(.2,.8,.2,1); }
        .lossx-chip { display: inline-block; font-size: .74rem; font-weight: 700; padding: 2px 9px; border-radius: 99px; background: rgba(0,0,0,.22); }
        .lossx-mini { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
        .lossx-mini .lossx-chip b { font-weight: 800; margin-left: 3px; font-variant-numeric: tabular-nums; }
        .lossx-card.is-loading .lossx-big, .lossx-card.is-loading .lossx-sub, .lossx-card.is-loading .lossx-chip {
            color: transparent; background: linear-gradient(90deg, rgba(255,255,255,.12), rgba(255,255,255,.32), rgba(255,255,255,.12));
            background-size: 200% 100%; animation: lossx-shimmer 1.2s infinite; border-radius: 8px;
        }
        .lossx-card.is-loading .lossx-big { width: 70px; }
        @keyframes lossx-shimmer { to { background-position: -200% 0; } }
        @media (max-width: 992px) { .lossx-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

        .lossx-modal .modal-content {
            --g1: #7c3aed; --g2: #ec4899;
            background: #0d0e1c; color: #e9eaff; border: 1px solid rgba(255,255,255,.08);
            border-radius: 28px; overflow: hidden; box-shadow: 0 30px 80px rgba(0,0,0,.5);
        }
        .lossx-head {
            position: relative; padding: 14px 20px 12px; color: #fff;
            background: linear-gradient(135deg, var(--g1), var(--g2));
        }
        .lossx-head::after { content: ''; position: absolute; right: -50px; top: -60px; width: 220px; height: 220px; border-radius: 50%; background: rgba(255,255,255,.14); }
        .lossx-head h5 { margin: 6px 0 2px; font-weight: 800; font-size: 1.35rem; letter-spacing: -.01em; position: relative; z-index: 1; }
        .lossx-head .lossx-sub { position: relative; z-index: 1; }
        .lossx-head .btn-close { position: absolute; right: 18px; top: 18px; z-index: 2; filter: invert(1) grayscale(1) brightness(2); opacity: .9; }
        .lossx-stats { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; position: relative; z-index: 1; }
        .lossx-stat { background: rgba(255,255,255,.18); backdrop-filter: blur(8px); border-radius: 16px; padding: 8px 14px; min-width: 110px; }
        .lossx-stat b { display: block; font-size: 1.25rem; font-weight: 800; line-height: 1.1; font-variant-numeric: tabular-nums; }
        .lossx-stat span { font-size: .68rem; text-transform: uppercase; letter-spacing: .08em; opacity: .85; }
        .lossx-body { padding: 16px 20px 20px; max-height: 62vh; overflow-y: auto; }
        .lossx-tools { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 14px; }
        .lossx-search {
            flex: 1 1 200px; border-radius: 99px; border: 1px solid rgba(255,255,255,.12);
            background: rgba(255,255,255,.06); color: #fff; padding: 8px 16px; font-size: .86rem; outline: none;
        }
        .lossx-search::placeholder { color: rgba(255,255,255,.45); }
        .lossx-search:focus { border-color: var(--g2); box-shadow: 0 0 0 3px rgba(236,72,153,.25); }
        .lossx-sort { border: 1px solid rgba(255,255,255,.14); background: transparent; color: #cfd1ff; border-radius: 99px; padding: 6px 13px; font-size: .78rem; font-weight: 700; cursor: pointer; transition: all .15s; }
        .lossx-sort:hover { background: rgba(255,255,255,.08); }
        .lossx-sort.is-on { background: linear-gradient(135deg, var(--g1), var(--g2)); color: #fff; border-color: transparent; }
        .lossx-item { background: rgba(255,255,255,.045); border: 1px solid rgba(255,255,255,.07); border-radius: 18px; margin-bottom: 10px; overflow: hidden; transition: border-color .15s, transform .15s; }
        .lossx-item:hover { border-color: rgba(255,255,255,.2); }
        .lossx-row { display: grid; grid-template-columns: var(--lossx-cols); gap: 8px; align-items: center; padding: 8px 12px; cursor: pointer; }
        .lossx-rank { width: 38px; height: 38px; border-radius: 12px; display: grid; place-items: center; font-weight: 800; background: linear-gradient(135deg, var(--g1), var(--g2)); color: #fff; font-size: .9rem; }
        .lossx-sku { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-weight: 700; font-size: .92rem; color: #fff; }
        .lossx-title { font-size: .76rem; color: rgba(233,234,255,.6); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
        .lossx-tags { margin-top: 5px; display: flex; flex-wrap: wrap; gap: 5px; }
        .lossx-tag { font-size: .68rem; font-weight: 700; padding: 2px 8px; border-radius: 99px; background: rgba(255,255,255,.09); color: #d7d9ff; }
        .lossx-nums { display: flex; gap: 8px; align-items: center; }
        .lossx-num { text-align: center; min-width: 58px; padding: 6px 10px; border-radius: 14px; background: rgba(255,255,255,.06); }
        .lossx-num b { display: block; font-size: 1.05rem; font-weight: 800; font-variant-numeric: tabular-nums; }
        .lossx-num span { font-size: .62rem; text-transform: uppercase; letter-spacing: .06em; color: rgba(233,234,255,.55); }
        .lossx-num.is-loss b { color: #ff7a93; }
        .lossx-chev { transition: transform .2s; opacity: .6; }
        .lossx-item.is-open .lossx-chev { transform: rotate(180deg); }
        .lossx-orders { display: none; padding: 0 14px 12px; }
        .lossx-item.is-open .lossx-orders { display: block; }
        .lossx-orders table { width: 100%; font-size: .76rem; border-collapse: separate; border-spacing: 0 4px; }
        .lossx-orders th { color: rgba(233,234,255,.5); font-weight: 700; text-transform: uppercase; font-size: .62rem; letter-spacing: .06em; padding: 0 8px; }
        .lossx-orders td { background: rgba(255,255,255,.05); padding: 6px 8px; color: #e9eaff; }
        .lossx-orders td:first-child { border-radius: 10px 0 0 10px; }
        .lossx-orders td:last-child { border-radius: 0 10px 10px 0; }
        .lossx-neg { color: #ff7a93; font-weight: 800; }
        .lossx-empty { text-align: center; padding: 40px 10px; color: rgba(233,234,255,.6); }
        .lossx-empty big { display: block; font-size: 2.4rem; margin-bottom: 6px; }

        /* ---------- Loss modal as a table: image first, no title, INV / L30 / Dil%, LMP by Channel, SPRICE $ (from /price-increase) ---------- */
        .lossx-modal .modal-dialog { max-width: min(1800px, 99vw); margin: .5rem auto; }
        .lossx-body { max-height: calc(100vh - 215px); overflow-y: auto; overflow-x: hidden; padding: 12px 14px 14px; }
        .lossx-grid { --lossx-cols: 46px minmax(80px, 1fr) repeat(6, minmax(36px, 48px)) repeat(3, minmax(34px, 44px)) minmax(96px, 1.1fr) minmax(138px, 1.5fr) minmax(124px, 1fr); min-width: 0; }
        .lossx-grid .lossx-row, .lossx-grid .lossx-colhead { gap: 6px; padding-left: 8px; padding-right: 8px; }
        .lossx-colhead { display: grid; grid-template-columns: var(--lossx-cols); gap: 8px; align-items: end; padding: 6px 12px; margin-bottom: 6px; position: sticky; top: 0; z-index: 3; background: #0d0e1c; border-bottom: 1px solid rgba(255,255,255,.12); }
        .lossx-colhead span { font-size: .6rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: rgba(233,234,255,.55); text-align: center; line-height: 1.15; }
        .lossx-colhead span.is-left { text-align: left; }
        .lossx-c { text-align: center; font-weight: 800; font-size: .8rem; font-variant-numeric: tabular-nums; min-width: 0; }
        .lossx-c.is-loss { color: #ff7a93; }
        .lossx-dim { color: rgba(233,234,255,.35); font-weight: 600; }
        .lossx-imgbox { position: relative; width: 44px; height: 44px; }
        .lossx-img { width: 44px; height: 44px; object-fit: cover; border-radius: 12px; background: #fff; display: block; }
        .lossx-img.is-missing { display: grid; place-items: center; background: rgba(255,255,255,.08); color: rgba(255,255,255,.4); }
        .lossx-imgbox .lossx-rank { position: absolute; left: -5px; top: -5px; width: 20px; height: 20px; border-radius: 7px; font-size: .62rem; border: 2px solid #0d0e1c; }
        .lossx-item.is-sib { background: rgba(255,255,255,.02); border-style: dashed; border-left: 3px dashed rgba(125,211,252,.55); }
        .lossx-item.is-sib .lossx-row { cursor: default; padding-top: 5px; padding-bottom: 5px; }
        .lossx-item.is-sib .lossx-img, .lossx-item.is-sib .lossx-imgbox { width: 36px; height: 36px; }
        .lossx-sibtag { font-size: .58rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; padding: 1px 6px; border-radius: 99px; background: rgba(125,211,252,.16); color: #7dd3fc; margin-left: 6px; vertical-align: middle; }
        .lossx-fam-gap { height: 6px; }
        .lossx-lmp-wrap { display: flex; flex-wrap: wrap; gap: 3px; align-items: center; }
        .lossx-lmp { font-size: .7rem; font-weight: 700; padding: 2px 6px; border-radius: 6px; white-space: nowrap; }
        .lossx-sp { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; }
        .lossx-sp-in { width: 70px; min-width: 0; height: 28px; font-size: .82rem; font-weight: 700; text-align: right; padding: 2px 6px; border-radius: 8px; border: 1px solid rgba(255,255,255,.2); background: rgba(255,255,255,.07); color: #fff; outline: none; }
        .lossx-sp-in:focus { border-color: var(--g2); box-shadow: 0 0 0 3px rgba(236,72,153,.25); }
        .lossx-sp-in.is-below { color: #ff7a93; }
        .lossx-sp-go { height: 28px; width: 30px; padding: 0; line-height: 1; border-radius: 8px; border: 1px solid #22c55e; background: rgba(34,197,94,.16); color: #4ade80; cursor: pointer; flex: 0 0 auto; }
        .lossx-sp-go:hover:not(:disabled) { background: #22c55e; color: #fff; }
        .lossx-sp-go:disabled { opacity: .7; cursor: progress; }
        .lossx-chips { display: flex; flex-wrap: wrap; align-items: center; gap: 3px; flex: 1 1 100%; margin: 1px 0 0; padding: 0; border: 0; }
        .lossx-chip2 { display: inline-flex; align-items: center; gap: 3px; height: 20px; padding: 0 4px; background: #fff; border: 1px solid #ced4da; border-radius: 6px; font-size: .68rem; font-weight: 700; color: #111; }
        .lossx-chip2 i { font-style: normal; font-size: .54rem; font-weight: 700; color: #64748b; }
        .lossx-more { display: block; margin: 8px auto 0; }
        .lossx-abadges { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; position: relative; z-index: 1; }
        .lossx-abadge { border: 2px solid transparent; border-radius: 99px; padding: 5px 14px; font-size: .78rem; font-weight: 800; color: #fff; cursor: pointer; display: inline-flex; align-items: center; gap: 7px; transition: transform .12s, box-shadow .12s; }
        .lossx-abadge b { background: rgba(0,0,0,.28); border-radius: 99px; padding: 0 8px; font-variant-numeric: tabular-nums; }
        .lossx-abadge:hover { transform: translateY(-1px); }
        .lossx-abadge.is-open { background: #d97706; }
        .lossx-abadge.is-fixed { background: #16a34a; }
        .lossx-abadge.is-ignored { background: #64748b; }
        .lossx-abadge.is-on { border-color: #fff; box-shadow: 0 0 0 3px rgba(255,255,255,.28); }
        .lossx-sibrow { flex: 1 1 100%; display: flex; align-items: center; gap: 4px; font-size: .64rem; font-weight: 700; color: #cfd1ff; }
        .lossx-sibrow label { display: inline-flex; align-items: center; gap: 3px; margin: 0; cursor: pointer; white-space: nowrap; }
        .lossx-sibrow input[type=checkbox] { width: 13px; height: 13px; margin: 0; accent-color: #22c55e; cursor: pointer; }
        .lossx-sib-add { width: 54px; height: 20px; padding: 0 4px; font-size: .66rem; font-weight: 700; text-align: right; border-radius: 6px; border: 1px solid rgba(255,255,255,.2); background: rgba(255,255,255,.07); color: #fff; outline: none; }
        .lossx-sib-add:disabled { opacity: .35; }
        .lossx-estrow { display: flex; align-items: center; gap: 6px; }
        .lossx-hbtn { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; background: rgba(0,0,0,.22); color: #fff; font-size: .68rem; cursor: pointer; transition: background .12s, transform .12s; }
        .lossx-hbtn:hover, .lossx-hbtn:focus-visible { background: rgba(0,0,0,.42); transform: scale(1.1); outline: none; }
        .lossx-player { display: inline-flex; align-items: center; gap: 6px; background: #f1f3f5; border-radius: 99px; padding: 4px 8px; box-shadow: 0 1px 4px rgba(0,0,0,.25); }
        .lossx-player button { border: 0; border-radius: 50%; width: 30px; height: 30px; padding: 0; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; background: #e4e7ea; color: #5b6168; font-size: .72rem; transition: transform .12s, background .12s; }
        .lossx-player button:hover:not(:disabled) { transform: scale(1.08); background: #d5d9dd; }
        .lossx-player button:disabled { opacity: .4; cursor: default; }
        .lossx-player .lossx-play { width: 38px; height: 38px; background: #19b5d8; color: #fff; font-size: .9rem; }
        .lossx-player .lossx-play:hover:not(:disabled) { background: #12a0c0; }
        .lossx-player .lossx-play.is-on { background: #f59e0b; }
        .lossx-player .lossx-pcount { font-size: .74rem; font-weight: 800; color: #3d434a; min-width: 48px; text-align: center; display: none; }
        .lossx-player.is-active .lossx-pcount { display: inline-block; }
        .lossx-player .lossx-pexit { display: none; }
        .lossx-player.is-active .lossx-pexit { display: inline-flex; }
        .lossx-chev { margin-left: 6px; font-size: .7rem; }
        .lossx-filter { border: 1px solid rgba(255,255,255,.14); background: #1a1b30; color: #cfd1ff; border-radius: 99px; padding: 6px 12px; font-size: .78rem; font-weight: 700; outline: none; }
        .lossx-act { display: flex; flex-wrap: wrap; align-items: center; gap: 4px; }
        .lossx-act select { height: 28px; flex: 1 1 70px; min-width: 0; max-width: 100%; font-size: .74rem; font-weight: 700; border-radius: 8px; border: 1px solid rgba(255,255,255,.2); background: #1a1b30; color: #fff; padding: 0 4px; outline: none; }
        .lossx-act .lossx-reason-sel { order: 3; flex: 1 1 100%; }
        .lossx-act .lossx-act-by { order: 4; }
        .lossx-act select:focus { border-color: var(--g2); }
        .lossx-act select.is-fixed { border-color: #22c55e; color: #4ade80; }
        .lossx-act select.is-ignored { border-color: #94a3b8; color: #cbd5e1; }
        .lossx-act select.is-need { border-color: #f59e0b; }
        .lossx-hist-btn { flex: 0 0 28px; height: 28px; width: 28px; padding: 0; border-radius: 8px; border: 1px solid rgba(255,255,255,.2); background: transparent; color: #cfd1ff; cursor: pointer; }
        .lossx-hist-btn:hover { background: rgba(255,255,255,.1); }
        .lossx-act-by { flex-basis: 100%; font-size: .62rem; color: rgba(233,234,255,.5); line-height: 1.1; }
        .lossx-item.is-fixed { border-left: 3px solid #22c55e; }
        .lossx-item.is-ignored { border-left: 3px solid #64748b; }
        .lossx-item.is-ignored .lossx-row > *:not(.lossx-act-cell) { opacity: .55; }
        .lossx-histbox { display: none; padding: 0 14px 12px; }
        .lossx-item.is-hist .lossx-histbox { display: block; }
        .lossx-histbox table { width: 100%; font-size: .74rem; border-collapse: separate; border-spacing: 0 3px; }
        .lossx-histbox th { color: rgba(233,234,255,.5); font-weight: 700; text-transform: uppercase; font-size: .6rem; letter-spacing: .06em; padding: 0 8px; text-align: left; }
        .lossx-histbox td { background: rgba(255,255,255,.05); padding: 5px 8px; }
        .lossx-status { font-size: .72rem; color: rgba(233,234,255,.6); margin: 0 0 8px; min-height: 1em; }
        .lossx-toast { position: fixed; right: 18px; bottom: 18px; z-index: 2000; max-width: 380px; padding: 10px 14px; border-radius: 12px; background: #16a34a; color: #fff; font-size: .82rem; font-weight: 600; box-shadow: 0 10px 30px rgba(0,0,0,.4); }
        .lossx-toast.is-err { background: #dc2626; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Sales Loss Order',
        'sub_title'  => 'All Data',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-2">
                    <div class="lossx-strip" id="lossx-strip" aria-label="Orders selling at a loss">
                        @foreach(['l30' => 'L30', 'l7' => 'L7', 'yesterday' => 'Yesterday', 'today' => 'Today'] as $lossKey => $lossLabel)
                            <button type="button" class="lossx-card is-loading" data-p="{{ $lossKey }}" title="Click to see which products sell at a loss">
                                <span class="lossx-pill"><i class="fas fa-fire" aria-hidden="true"></i> {{ $lossLabel }}</span>
                                <div class="lossx-big"><span data-f="loss_orders">0</span><small>loss orders</small></div>
                                <div class="lossx-sub" data-f="sub">&nbsp;</div>
                                <div class="lossx-bar"><i data-f="bar"></i></div>
                                <div class="lossx-estrow">
                                    <span class="lossx-chip" data-f="amount">&nbsp;</span>
                                    <span class="lossx-hbtn" role="button" tabindex="0" data-hist="{{ $lossKey }}" title="History: Fixed / Ignore actions by users ({{ $lossLabel }})" aria-label="Action history ({{ $lossLabel }})"><i class="fas fa-history" aria-hidden="true"></i></span>
                                </div>
                                <div class="lossx-mini">
                                    <span class="lossx-chip" title="Net ROI % for all orders in this period (amount-weighted)">NROI% <b data-f="nroi">—</b></span>
                                    <span class="lossx-chip" title="Net profit % for all orders in this period (amount-weighted)">NPRFT% <b data-f="npft">—</b></span>
                                </div>
                            </button>
                        @endforeach
                    </div>
                    <div id="slo-toolbar" class="mb-2">
                        <div id="slo-toolbar-row1" role="group" aria-label="Summary metrics">
                            <span class="badge slo-summary-badge" style="background:#f8d7da; color:#842029; border:1px solid #f5c2c7;" title="All marketplace orders">
                                Orders: <span id="slo-order-count">0</span>
                            </span>
                            <span class="badge bg-primary slo-summary-badge" style="color: white;" title="Channels with at least one order">
                                Channels: <span id="slo-channel-count">0</span>
                            </span>
                            <span class="badge slo-summary-badge" style="background:#fff3cd; color:#856404; border:1px solid #ffe69c;" title="Sum of order amounts">
                                Amount: <span id="slo-amount-total">0.00</span>
                            </span>
                            <span class="badge slo-summary-badge" id="slo-gpft-low-badge" style="background:#f8d7da; color:#842029; border:1px solid #f5c2c7;" title="Click to filter GPFT% under 10%.">
                                GPFT% &lt; 10: <span id="slo-gpft-low-count">0</span>
                            </span>
                            <span class="badge slo-summary-badge" id="slo-groi-low-badge" style="background:#f8d7da; color:#842029; border:1px solid #f5c2c7;" title="Click to filter GROI% under 50%.">
                                GROI % &lt; 50%: <span id="slo-groi-low-count">0</span>
                            </span>
                            <span class="badge slo-summary-badge slo-alert-badge" id="slo-npft-alert-badge" style="background:#f8d7da; color:#842029; border:1px solid #f5c2c7;" title="Price alert: click to show orders with NPRFT% under 10%. Counts distinct SKUs.">
                                <i class="fas fa-triangle-exclamation" aria-hidden="true"></i> Price Alert NPRFT% &lt; 10%: <span id="slo-npft-alert-count">0</span> SKUs
                            </span>
                            <span class="badge slo-summary-badge slo-alert-badge" id="slo-nroi-alert-badge" style="background:#f8d7da; color:#842029; border:1px solid #f5c2c7;" title="Price alert: click to show orders with NROI% under 30%. Counts distinct SKUs.">
                                <i class="fas fa-triangle-exclamation" aria-hidden="true"></i> Price Alert NROI% &lt; 30%: <span id="slo-nroi-alert-count">0</span> SKUs
                            </span>
                        </div>
                        <div id="slo-toolbar-row2">
                            <div class="slo-filter-field">
                                <label class="visually-hidden" for="slo-date-from">From</label>
                                <input type="date" id="slo-date-from" class="form-control form-control-sm" style="width:140px;" title="From date" value="{{ $sloDateFrom ?? '' }}">
                            </div>
                            <div class="slo-filter-field">
                                <label class="visually-hidden" for="slo-date-to">To</label>
                                <input type="date" id="slo-date-to" class="form-control form-control-sm" style="width:140px;" title="To date" value="{{ $sloDateTo ?? '' }}">
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="slo-apply-dates" title="Apply date filter">Apply</button>
                            <div class="slo-filter-field">
                                <label class="visually-hidden" for="slo-date-preset">Date range</label>
                                <select id="slo-date-preset" class="form-select form-select-sm" style="width:120px;" title="Quick date range (EDT)">
                                    <option value="l30" selected>L30</option>
                                    <option value="l7">L7</option>
                                    <option value="yesterday">Last Day</option>
                                    <option value="today">Today</option>
                                    <option value="custom" hidden>Custom</option>
                                </select>
                            </div>
                            <div class="slo-filter-field">
                                <label class="visually-hidden" for="slo-channel-filter">Channels</label>
                                <div class="input-group input-group-sm" style="width:150px;">
                                    <span class="input-group-text" title="Filter by channel"><i class="fas fa-search"></i></span>
                                    <input type="text" id="slo-channel-filter" class="form-control"
                                           list="slo-channel-datalist"
                                           placeholder="Channels…"
                                           autocomplete="off"
                                           title="Filter by channel">
                                </div>
                                <datalist id="slo-channel-datalist">
                                    @foreach(($sloChannels ?? []) as $chOpt)
                                        @if(($chOpt['slug'] ?? '') !== '')
                                            <option value="{{ $chOpt['label'] }}" data-slug="{{ $chOpt['slug'] }}"></option>
                                        @endif
                                    @endforeach
                                </datalist>
                            </div>
                            <div class="slo-filter-field">
                                <label class="visually-hidden" for="slo-status-filter">Status</label>
                                <select id="slo-status-filter" class="form-select form-select-sm" style="width:150px;" title="Filter by status">
                                    <option value="">All statuses</option>
                                </select>
                            </div>
                            <div class="slo-filter-field flex-grow-1">
                                <label class="visually-hidden" for="slo-order-search">Search</label>
                                <input type="text" id="slo-order-search" class="form-control form-control-sm"
                                       placeholder="Search Channel, Order ID, SKU, Status…"
                                       autocomplete="off"
                                       title="Filter orders by Channel, Order ID, SKU, Status">
                            </div>
                            <div id="slo-date-filter-hint">Dates shown as 1 Apr · time EDT only</div>
                        </div>
                    </div>
                    <p class="small text-muted mb-2">All marketplace orders — every status. Defaults to the last 30 days.</p>
                    <div id="sales-loss-order-table" style="height: calc(100vh - 320px);"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade lossx-modal" id="lossxModal" tabindex="-1" aria-labelledby="lossxTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
            <div class="modal-content" id="lossxContent">
                <div class="lossx-head">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    <span class="lossx-pill"><i class="fas fa-fire" aria-hidden="true"></i> <span id="lossxPeriod">L30</span></span>
                    <h5 id="lossxTitle">Products selling at a loss</h5>
                    <div class="lossx-sub" id="lossxRange"></div>
                    <div class="lossx-stats">
                        <div class="lossx-stat"><b id="lossxStatOrders">0</b><span>Loss orders</span></div>
                        <div class="lossx-stat"><b id="lossxStatSkus">0</b><span>Products</span></div>
                        <div class="lossx-stat"><b id="lossxStatLoss">$0</b><span>Est. loss</span></div>
                        <div class="lossx-stat"><b id="lossxStatRate">0%</b><span>Of all orders</span></div>
                        <div class="lossx-stat" title="All orders in this period, amount-weighted"><b id="lossxStatNroi">—</b><span>NROI%</span></div>
                        <div class="lossx-stat" title="All orders in this period, amount-weighted"><b id="lossxStatNpft">—</b><span>NPRFT%</span></div>
                    </div>
                    <div class="lossx-abadges" id="lossxBadges" aria-label="Products by action">
                        <button type="button" class="lossx-abadge is-open" data-af="open" title="Loss products with no action yet">Open <b data-c="open">0</b></button>
                        <button type="button" class="lossx-abadge is-fixed" data-af="fixed" title="Loss products marked Fixed">Fixed <b data-c="fixed">0</b></button>
                        <button type="button" class="lossx-abadge is-ignored" data-af="ignored" title="Loss products marked Ignore">Ignored <b data-c="ignored">0</b></button>
                    </div>
                </div>
                <div class="lossx-body">
                    <div class="lossx-tools">
                        <input type="search" class="lossx-search" id="lossxSearch" placeholder="Search SKU, product or channel…" autocomplete="off">
                        <button type="button" class="lossx-sort" data-sort="orders">Most orders</button>
                        <button type="button" class="lossx-sort" data-sort="loss">Biggest loss</button>
                        <button type="button" class="lossx-sort is-on" data-sort="pct">Worst %</button>
                        <select class="lossx-filter" id="lossxFilter" title="Show products by their action">
                            <option value="all">All actions</option>
                            <option value="open" selected>Open (no action)</option>
                            <option value="fixed">Fixed</option>
                            <option value="ignored">Ignored</option>
                        </select>
                        <div class="lossx-player" id="lossxPlayer" title="Show the loss products one by one, starting with #1">
                            <button type="button" id="lossxPrev" title="Previous product" aria-label="Previous product"><i class="fas fa-step-backward" aria-hidden="true"></i></button>
                            <button type="button" class="lossx-play" id="lossxPlay" title="Play — auto-advance every 5 seconds" aria-label="Play"><i class="fas fa-play" aria-hidden="true"></i></button>
                            <button type="button" id="lossxNext" title="Next product" aria-label="Next product"><i class="fas fa-step-forward" aria-hidden="true"></i></button>
                            <span class="lossx-pcount" id="lossxPcount">1 / 1</span>
                            <button type="button" class="lossx-pexit" id="lossxPexit" title="Back to the full list" aria-label="Back to the full list"><i class="fas fa-times" aria-hidden="true"></i></button>
                        </div>
                    </div>
                    <div class="lossx-status" id="lossxStatus"></div>
                    <div id="lossxList"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="lossxHistModal" tabindex="-1" aria-labelledby="lossxHistTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title" id="lossxHistTitle">Action history</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="lossxHistAll">
                        <label class="form-check-label small" for="lossxHistAll">Show every SKU (not only the loss products of this period)</label>
                    </div>
                    <div id="lossxHistBody" class="small"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade slo-cs-modal" id="sloCostShipModal" tabindex="-1" aria-labelledby="sloCostShipModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="sloCostShipModalLabel">
                        <i class="fas fa-magnifying-glass me-2"></i>Cost, freight &amp; shipping
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="slo-cs-hero">
                        <img id="slo-cs-img" class="d-none" alt="SKU">
                        <div id="slo-cs-img-missing" class="slo-cs-hero-img is-missing"><i class="fas fa-box-open"></i></div>
                        <div class="slo-cs-hero-meta">
                            <div class="slo-cs-hero-sku" id="slo-cs-sku">—</div>
                            <div class="slo-cs-hero-title" id="slo-cs-title">—</div>
                            <div class="slo-cs-hero-sub">
                                <span class="slo-cs-chip" id="slo-cs-channel">—</span>
                                <span class="slo-cs-chip" id="slo-cs-order">—</span>
                            </div>
                        </div>
                    </div>
                    <div class="slo-cs-section-label">Cost</div>
                    <div class="slo-cs-grid">
                        <div class="slo-cs-card">
                            <div class="slo-cs-kicker"><i class="fas fa-tag slo-cs-ico-cp"></i>CP</div>
                            <div class="slo-cs-value" id="slo-cs-cp">—</div>
                        </div>
                        <div class="slo-cs-card">
                            <div class="slo-cs-kicker"><i class="fas fa-truck-fast slo-cs-ico-frg"></i>Freight</div>
                            <div class="slo-cs-value" id="slo-cs-frght">—</div>
                        </div>
                        <div class="slo-cs-card">
                            <div class="slo-cs-kicker"><i class="fas fa-dollar-sign slo-cs-ico-lp"></i>LP</div>
                            <div class="slo-cs-value" id="slo-cs-lp">—</div>
                        </div>
                    </div>
                    <div class="slo-cs-section-label">Package</div>
                    <div class="slo-cs-grid">
                        <div class="slo-cs-card">
                            <div class="slo-cs-kicker"><i class="fas fa-cube slo-cs-ico-dim"></i>Dimensions</div>
                            <div class="slo-cs-value" id="slo-cs-dims">—</div>
                        </div>
                        <div class="slo-cs-card">
                            <div class="slo-cs-kicker"><i class="fas fa-weight-hanging slo-cs-ico-wta"></i>Weight Act</div>
                            <div class="slo-cs-value" id="slo-cs-wt-act">—</div>
                        </div>
                        <div class="slo-cs-card">
                            <div class="slo-cs-kicker"><i class="fas fa-scale-balanced slo-cs-ico-wtd"></i>Weight Decl</div>
                            <div class="slo-cs-value" id="slo-cs-wt-decl">—</div>
                        </div>
                    </div>
                    <div class="slo-cs-section-label">Shipping</div>
                    <div class="slo-cs-grid">
                        <div class="slo-cs-card">
                            <div class="slo-cs-kicker"><i class="fas fa-ship slo-cs-ico-ship"></i>Ship</div>
                            <div class="slo-cs-value" id="slo-cs-ship">—</div>
                        </div>
                        <div class="slo-cs-card">
                            <div class="slo-cs-kicker"><i class="fas fa-store slo-cs-ico-temu"></i>Ship Temu</div>
                            <div class="slo-cs-value" id="slo-cs-ship-temu">—</div>
                        </div>
                        <div class="slo-cs-card">
                            <div class="slo-cs-kicker"><i class="fas fa-basket-shopping slo-cs-ico-bb"></i>Ship BB</div>
                            <div class="slo-cs-value" id="slo-cs-ship-bb">—</div>
                        </div>
                    </div>
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
    const SLO_TZ = 'America/New_York';
    const sloChannelSlugByLabel = {};
    @foreach(($sloChannels ?? []) as $chOpt)
        @if(($chOpt['slug'] ?? '') !== '' && ($chOpt['label'] ?? '') !== '')
            sloChannelSlugByLabel[@json(strtolower($chOpt['label']))] = @json($chOpt['slug']);
        @endif
    @endforeach

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function copyTextToClipboard(text, btn) {
        const done = function () {
            if (!btn) return;
            const prev = btn.innerHTML;
            btn.classList.add('copied');
            btn.innerHTML = '<i class="fas fa-check" aria-hidden="true"></i>';
            setTimeout(function () {
                btn.classList.remove('copied');
                btn.innerHTML = prev;
            }, 1200);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done).catch(function () {
                window.prompt('Copy:', text);
            });
        } else {
            window.prompt('Copy:', text);
            done();
        }
    }

    function sloDateParams() {
        const fromEl = document.getElementById('slo-date-from');
        const toEl = document.getElementById('slo-date-to');
        return {
            date_from: fromEl ? (fromEl.value || '') : '',
            date_to: toEl ? (toEl.value || '') : '',
            tz: SLO_TZ,
        };
    }

    function sloUpdateDateHint() {
        const p = sloDateParams();
        const hint = document.getElementById('slo-date-filter-hint');
        if (!hint) return;
        if (!p.date_from && !p.date_to) {
            hint.textContent = 'Last 30 days · display EDT';
        } else {
            hint.textContent = 'Filter: ' + (p.date_from || '…') + ' → ' + (p.date_to || 'today') + ' · display EDT';
        }
    }

    function sloChannelFilterValue() {
        const el = document.getElementById('slo-channel-filter');
        return el ? String(el.value || '').trim() : '';
    }

    function sloResolvedChannelSlug() {
        const label = sloChannelFilterValue().toLowerCase();
        return sloChannelSlugByLabel[label] || '';
    }

    function sloStringSorter(a, b) {
        return String(a || '').localeCompare(String(b || ''), undefined, { numeric: true, sensitivity: 'base' });
    }

    function sloDateSorter(a, b) {
        const as = String(a || '').trim();
        const bs = String(b || '').trim();
        if (!as && !bs) return 0;
        if (!as) return -1;
        if (!bs) return 1;
        return as.localeCompare(bs);
    }

    function sloApplyLatestFirstSort(tbl) {
        if (!tbl || tbl._sloSorting) return;
        tbl._sloSorting = true;
        try {
            tbl.setSort([{ column: 'order_date', dir: 'desc' }]);
        } catch (e) {
            /* table not ready */
        } finally {
            tbl._sloSorting = false;
        }
    }

    /** Source values are naive America/Los_Angeles wall clocks (same as fulfillment). */
    const SLO_SOURCE_TZ = 'America/Los_Angeles';
    const SLO_DISPLAY_TZ = 'America/New_York';

    function sloWallClockToUtcMs(raw, timeZone) {
        const m = String(raw || '').trim().match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/);
        if (!m) {
            const d = new Date(String(raw || '').replace(' ', 'T'));
            return isNaN(d.getTime()) ? null : d.getTime();
        }
        const y = Number(m[1]);
        const mo = Number(m[2]);
        const d = Number(m[3]);
        const h = Number(m[4] || 0);
        const mi = Number(m[5] || 0);
        const s = Number(m[6] || 0);
        const desired = Date.UTC(y, mo - 1, d, h, mi, s);
        const dtf = new Intl.DateTimeFormat('en-US', {
            timeZone: timeZone,
            hourCycle: 'h23',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        });
        const wallAsUtc = function (instant) {
            const parts = dtf.formatToParts(new Date(instant));
            const get = function (type) {
                const p = parts.find(function (x) { return x.type === type; });
                return p ? Number(p.value) : 0;
            };
            return Date.UTC(get('year'), get('month') - 1, get('day'), get('hour'), get('minute'), get('second'));
        };
        let instant = desired;
        for (let i = 0; i < 3; i++) {
            instant = desired - (wallAsUtc(instant) - desired);
        }
        return instant;
    }

    function sloFormatEstParts(raw) {
        const s = String(raw || '').trim();
        if (!s) return null;
        const dateOnly = /^\d{4}-\d{2}-\d{2}$/.test(s);
        const ms = sloWallClockToUtcMs(s, dateOnly ? SLO_DISPLAY_TZ : SLO_SOURCE_TZ);
        if (ms == null) return null;
        const d = new Date(ms);
        const dateParts = new Intl.DateTimeFormat('en-US', {
            timeZone: SLO_DISPLAY_TZ,
            day: 'numeric',
            month: 'short',
        }).formatToParts(d);
        const day = (dateParts.find(function (p) { return p.type === 'day'; }) || {}).value || '';
        const month = (dateParts.find(function (p) { return p.type === 'month'; }) || {}).value || '';
        const dateLabel = (day + ' ' + month).trim();
        if (dateOnly) {
            return { date: dateLabel, time: '', title: dateLabel + ' EDT' };
        }
        const time = new Intl.DateTimeFormat('en-US', {
            timeZone: SLO_DISPLAY_TZ,
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        }).format(d);
        const title = new Intl.DateTimeFormat('en-US', {
            timeZone: SLO_DISPLAY_TZ,
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            second: '2-digit',
            hour12: true,
        }).format(d) + ' EDT';
        return { date: dateLabel, time: time + ' EDT', title: title };
    }

    function sloFormatEstDateCell(cell) {
        const raw = cell.getValue();
        if (!raw) return '—';
        const parts = sloFormatEstParts(raw);
        if (!parts) return escapeHtml(raw);
        const wrap = document.createElement('span');
        wrap.className = 'slo-dt-cell';
        wrap.title = parts.title;
        const dateEl = document.createElement('span');
        dateEl.className = 'slo-dt-date';
        dateEl.textContent = parts.date;
        wrap.appendChild(dateEl);
        if (parts.time) {
            const timeEl = document.createElement('span');
            timeEl.className = 'slo-dt-time';
            timeEl.textContent = parts.time;
            wrap.appendChild(timeEl);
        }
        return wrap;
    }

    function formatMoney(v) {
        if (v === null || v === undefined || v === '') return '—';
        return Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function sloFormatUsd(v) {
        if (v === null || v === undefined || v === '') return '';
        const n = Number(v);
        if (!Number.isFinite(n)) return '';
        return '$' + n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function sloFormatWt(v) {
        if (v === null || v === undefined || v === '') return '';
        const n = Number(v);
        if (!Number.isFinite(n)) return '';
        return n.toLocaleString(undefined, { maximumFractionDigits: 2 }) + ' lb';
    }

    function sloFormatDims(row) {
        const parts = [row.l, row.w, row.h].map(function (v) {
            if (v === null || v === undefined || v === '') return null;
            const n = Number(v);
            return Number.isFinite(n) ? n : null;
        });
        if (parts.every(function (p) { return p === null; })) return '';
        return parts.map(function (p) {
            if (p === null) return '—';
            return Number.isInteger(p) ? String(p) : String(p);
        }).join(' × ') + ' in';
    }

    function sloSetCsValue(id, text) {
        const el = document.getElementById(id);
        if (!el) return;
        const has = !!(text && String(text).trim());
        el.textContent = has ? text : '—';
        el.classList.toggle('is-empty', !has);
    }

    function sloOpenCostShipModal(row) {
        const sku = String(row.sku || '').trim();
        const title = String(row.display_title || '').trim();
        const channel = String(row.channel_label || row.mm_slug || '').trim();
        const orderId = String(row.order_id || row.show_id || '').trim();
        document.getElementById('slo-cs-sku').textContent = sku || '—';
        document.getElementById('slo-cs-title').textContent = title || 'No product title';
        document.getElementById('slo-cs-channel').textContent = channel || 'Channel';
        document.getElementById('slo-cs-order').textContent = orderId ? ('Order ' + orderId) : 'No order ID';

        const img = document.getElementById('slo-cs-img');
        const missing = document.getElementById('slo-cs-img-missing');
        const src = String(row.sku_image || '').trim();
        if (img && missing) {
            if (src) {
                img.src = src;
                img.classList.remove('d-none');
                missing.classList.add('d-none');
            } else {
                img.removeAttribute('src');
                img.classList.add('d-none');
                missing.classList.remove('d-none');
            }
        }

        sloSetCsValue('slo-cs-cp', sloFormatUsd(row.cp));
        sloSetCsValue('slo-cs-frght', sloFormatUsd(row.frght));
        sloSetCsValue('slo-cs-lp', sloFormatUsd(row.lp));
        sloSetCsValue('slo-cs-dims', sloFormatDims(row));
        sloSetCsValue('slo-cs-wt-act', sloFormatWt(row.wt_act));
        sloSetCsValue('slo-cs-wt-decl', sloFormatWt(row.wt_decl));
        sloSetCsValue('slo-cs-ship', sloFormatUsd(row.ship));
        sloSetCsValue('slo-cs-ship-temu', sloFormatUsd(row.ship_temu));
        sloSetCsValue('slo-cs-ship-bb', sloFormatUsd(row.ship_bb));

        const modalEl = document.getElementById('sloCostShipModal');
        if (!modalEl) return;
        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } else if (window.jQuery) {
            window.jQuery(modalEl).modal('show');
        }
    }

    const sloAnalyticsPctBands = {
        groi: { redBelow: 50, purpleAbove: 100 },
        nroi: { redBelow: 40, purpleAbove: 75 },
        gpft: { redBelow: 17, purpleAbove: 33 },
        npft: { redBelow: 10, purpleAbove: 27 },
    };

    function sloFormatPctCell(cell, metric) {
        const v = cell.getValue();
        if (v === null || v === undefined || v === '') return '—';
        const n = Number(v);
        if (!Number.isFinite(n)) return '—';
        const band = sloAnalyticsPctBands[metric] || sloAnalyticsPctBands.groi;
        let color = '#006400';
        if (n < band.redBelow) {
            color = '#c00000';
        } else if (n > band.purpleAbove) {
            color = '#7030a0';
        }
        const rounded = Math.round(n) || 0; // whole number; "|| 0" avoids "-0"
        return '<span style="color:' + color + ';font-weight:700">' + rounded + '%</span>';
    }

    function sloStatusFilterValue() {
        const el = document.getElementById('slo-status-filter');
        return el ? String(el.value || '').trim() : '';
    }

    function sloRebuildStatusOptions(rows) {
        const sel = document.getElementById('slo-status-filter');
        if (!sel) return;
        const prev = sel.value;
        const counts = {};
        (rows || []).forEach(function (row) {
            const label = String(row.status_label || row.status || '').trim();
            if (!label || label === '—') return;
            counts[label] = (counts[label] || 0) + 1;
        });
        const labels = Object.keys(counts).sort(function (a, b) {
            return a.localeCompare(b, undefined, { sensitivity: 'base' });
        });
        sel.innerHTML = '';
        const all = document.createElement('option');
        all.value = '';
        all.textContent = 'All statuses';
        sel.appendChild(all);
        labels.forEach(function (label) {
            const opt = document.createElement('option');
            opt.value = label;
            opt.textContent = label + ' (' + counts[label].toLocaleString() + ')';
            sel.appendChild(opt);
        });
        if (prev && counts[prev]) {
            sel.value = prev;
        }
    }

    let sloGpftLowFilter = false;
    let sloGroiLowFilter = false;

    function sloIsGpftLow(row) {
        const n = Number(row && row.gpft_pct);
        return Number.isFinite(n) && n < 10;
    }

    function sloIsGroiLow(row) {
        const n = Number(row && row.groi_pct);
        return Number.isFinite(n) && n < 50;
    }

    function sloUpdateGpftLowBadge(rows) {
        const count = (rows || []).filter(sloIsGpftLow).length;
        const countEl = document.getElementById('slo-gpft-low-count');
        if (countEl) countEl.textContent = count.toLocaleString();
        const badge = document.getElementById('slo-gpft-low-badge');
        if (badge) badge.classList.toggle('is-active', sloGpftLowFilter);
    }

    function sloUpdateGroiLowBadge(rows) {
        const count = (rows || []).filter(sloIsGroiLow).length;
        const countEl = document.getElementById('slo-groi-low-count');
        if (countEl) countEl.textContent = count.toLocaleString();
        const badge = document.getElementById('slo-groi-low-badge');
        if (badge) badge.classList.toggle('is-active', sloGroiLowFilter);
    }

    // Price alerts: NPRFT% under 10 and NROI% under 30. Badges count distinct SKUs.
    let sloNpftAlertFilter = false;
    let sloNroiAlertFilter = false;

    function sloIsNpftAlert(row) {
        const v = row && row.npft_pct;
        if (v === null || v === undefined || v === '') return false;
        const n = Number(v);
        return Number.isFinite(n) && n < 10;
    }

    function sloIsNroiAlert(row) {
        const v = row && row.nroi_pct;
        if (v === null || v === undefined || v === '') return false;
        const n = Number(v);
        return Number.isFinite(n) && n < 30;
    }

    function sloCountAlertSkus(rows, test) {
        const skus = new Set();
        (rows || []).forEach(function (row) {
            const sku = String((row && row.sku) || '').trim().toLowerCase();
            if (sku && test(row)) skus.add(sku);
        });
        return skus.size;
    }

    function sloUpdateAlertBadges(rows) {
        const npftEl = document.getElementById('slo-npft-alert-count');
        const nroiEl = document.getElementById('slo-nroi-alert-count');
        if (npftEl) npftEl.textContent = sloCountAlertSkus(rows, sloIsNpftAlert).toLocaleString();
        if (nroiEl) nroiEl.textContent = sloCountAlertSkus(rows, sloIsNroiAlert).toLocaleString();
        document.getElementById('slo-npft-alert-badge')?.classList.toggle('is-active', sloNpftAlertFilter);
        document.getElementById('slo-nroi-alert-badge')?.classList.toggle('is-active', sloNroiAlertFilter);
    }

    function applyClientFilters(table) {
        if (!table) return;
        const q = ($('#slo-order-search').val() || '').trim().toLowerCase();
        const channel = sloChannelFilterValue();
        const slug = sloResolvedChannelSlug();
        const status = sloStatusFilterValue();

        if (!q && !channel && !status && !sloGpftLowFilter && !sloGroiLowFilter && !sloNpftAlertFilter && !sloNroiAlertFilter) {
            table.clearFilter(true);
            sloApplyLatestFirstSort(table);
            return;
        }

        table.setFilter(function (data) {
            const searchOk = !q || (
                String(data.channel_label || '').toLowerCase().includes(q)
                || String(data.order_id || '').toLowerCase().includes(q)
                || String(data.sku || '').toLowerCase().includes(q)
                || String(data.status_label || data.status || '').toLowerCase().includes(q)
                || String(data.tracking_number || '').toLowerCase().includes(q)
            );
            if (!searchOk) return false;
            if (status) {
                const rowStatus = String(data.status_label || data.status || '').trim();
                if (rowStatus.toLowerCase() !== status.toLowerCase()) return false;
            }
            if (sloGpftLowFilter && !sloIsGpftLow(data)) return false;
            if (sloGroiLowFilter && !sloIsGroiLow(data)) return false;
            if (sloNpftAlertFilter && !sloIsNpftAlert(data)) return false;
            if (sloNroiAlertFilter && !sloIsNroiAlert(data)) return false;
            if (!channel) return true;
            if (slug) {
                return String(data.mm_slug || '').toLowerCase() === slug;
            }
            const cq = channel.toLowerCase();
            return String(data.channel_label || '').toLowerCase().includes(cq)
                || String(data.mm_slug || '').toLowerCase().includes(cq);
        });
        sloApplyLatestFirstSort(table);
    }

    const table = new Tabulator('#sales-loss-order-table', {
        pagination: true,
        paginationMode: 'local',
        sortMode: 'local',
        filterMode: 'local',
        paginationSize: 50,
        paginationSizeSelector: [25, 50, 100, 500, true],
        movableColumns: false,
        headerSortClickElement: 'header',
        layout: 'fitColumns',
        placeholder: 'Loading sales loss orders…',
        initialSort: [{ column: 'order_date', dir: 'desc' }],
        ajaxURL: '{{ route("sales.loss.order.data") }}',
        ajaxConfig: 'GET',
        ajaxParams: sloDateParams,
        ajaxRequestFunc: function (url, config, params) {
            return new Promise(function (resolve, reject) {
                $.ajax({
                    url: url,
                    type: 'GET',
                    data: Object.assign({}, params || {}, sloDateParams()),
                    timeout: 0,
                    success: resolve,
                    error: reject,
                });
            });
        },
        ajaxResponse: function (url, params, response) {
            const rows = (response && response.success && Array.isArray(response.data))
                ? response.data
                : [];
            const count = (response && response.count != null) ? Number(response.count) : rows.length;
            const channels = (response && response.channel_count != null) ? Number(response.channel_count) : 0;
            const amount = (response && response.amount_total != null) ? Number(response.amount_total) : 0;
            const countEl = document.getElementById('slo-order-count');
            const chEl = document.getElementById('slo-channel-count');
            const amtEl = document.getElementById('slo-amount-total');
            if (countEl) countEl.textContent = count.toLocaleString();
            if (chEl) chEl.textContent = channels.toLocaleString();
            if (amtEl) amtEl.textContent = amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            if (response && response.success === false) {
                this.options.placeholder = response.message || 'Failed to load sales loss orders.';
            }
            sloRebuildStatusOptions(rows);
            sloUpdateGpftLowBadge(rows);
            sloUpdateGroiLowBadge(rows);
            sloUpdateAlertBadges(rows);
            return rows;
        },
        dataLoaded: function () {
            applyClientFilters(table);
            sloApplyLatestFirstSort(table);
        },
        columns: [
            {
                title: 'Channel',
                field: 'channel_label',
                minWidth: 100,
                headerHozAlign: 'center',
                headerSort: true,
                sorter: sloStringSorter,
                formatter: function (cell) {
                    const row = cell.getRow().getData();
                    const label = escapeHtml(cell.getValue() || '');
                    const url = (row.orders_url || '').trim();
                    if (url) {
                        return `<a href="${escapeHtml(url)}" target="_blank" class="slo-channel-name has-link">${label}</a>`;
                    }
                    return `<span class="slo-channel-name">${label}</span>`;
                },
            },
            {
                title: 'Order ID',
                field: 'order_id',
                minWidth: 70,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: true,
                sorter: sloStringSorter,
                formatter: function (cell) {
                    const row = cell.getRow().getData();
                    const orderId = (cell.getValue() || '').toString().trim();
                    if (!orderId) {
                        return '<span class="slo-oc-missing">—</span>';
                    }
                    const url = (row.order_url || '').trim();
                    const wrap = document.createElement('span');
                    wrap.className = 'slo-order-id-wrap';
                    const dot = document.createElement('span');
                    dot.className = 'slo-order-id-dot';
                    wrap.appendChild(dot);
                    const pop = document.createElement('span');
                    pop.className = 'slo-order-id-popover';
                    const text = document.createElement('span');
                    text.className = 'slo-order-id-text';
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
                    copyBtn.className = 'slo-order-id-copy';
                    copyBtn.title = 'Copy Order ID';
                    copyBtn.innerHTML = '<i class="fas fa-copy" aria-hidden="true"></i>';
                    copyBtn.addEventListener('click', function (ev) {
                        ev.preventDefault();
                        ev.stopPropagation();
                        copyTextToClipboard(orderId, copyBtn);
                    });
                    pop.appendChild(copyBtn);
                    wrap.appendChild(pop);
                    return wrap;
                },
            },
            {
                title: 'Date',
                field: 'order_date',
                minWidth: 88,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: true,
                sorter: sloDateSorter,
                headerTooltip: 'Latest orders on top · 1 Apr · time EDT only',
                formatter: sloFormatEstDateCell,
            },
            {
                title: 'Tracking',
                field: 'tracking_number',
                minWidth: 70,
                width: 78,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: true,
                sorter: sloStringSorter,
                headerTooltip: 'Hover the dot to see the tracking number and copy it',
                formatter: function (cell) {
                    const tracking = (cell.getValue() || '').toString().trim();
                    const wrap = document.createElement('span');
                    wrap.className = 'slo-tracking-cell';
                    const dot = document.createElement('span');
                    dot.className = 'slo-ch-orders-dot ' + (tracking ? 'green' : 'red');
                    dot.setAttribute('aria-label', tracking ? 'Tracking available' : 'Tracking missing');
                    wrap.appendChild(dot);
                    if (!tracking) {
                        return wrap;
                    }
                    const pop = document.createElement('span');
                    pop.className = 'slo-tracking-popover';
                    const num = document.createElement('span');
                    num.className = 'slo-tracking-num';
                    num.textContent = tracking;
                    pop.appendChild(num);
                    const copyBtn = document.createElement('button');
                    copyBtn.type = 'button';
                    copyBtn.className = 'slo-tracking-copy';
                    copyBtn.title = 'Copy tracking number';
                    copyBtn.setAttribute('aria-label', 'Copy tracking number');
                    copyBtn.innerHTML = '<i class="fas fa-copy" aria-hidden="true"></i>';
                    copyBtn.addEventListener('click', function (ev) {
                        ev.preventDefault();
                        ev.stopPropagation();
                        copyTextToClipboard(tracking, copyBtn);
                    });
                    pop.appendChild(copyBtn);
                    wrap.appendChild(pop);
                    return wrap;
                },
            },
            {
                title: 'SKU',
                field: 'sku',
                minWidth: 220,
                headerHozAlign: 'center',
                headerSort: true,
                sorter: sloStringSorter,
                formatter: function (cell) {
                    const sku = (cell.getValue() || '').toString().trim();
                    if (!sku) return '—';
                    const wrap = document.createElement('span');
                    wrap.className = 'slo-sku-cell';
                    const code = document.createElement('code');
                    code.textContent = sku;
                    wrap.appendChild(code);
                    const copyBtn = document.createElement('button');
                    copyBtn.type = 'button';
                    copyBtn.className = 'slo-sku-copy';
                    copyBtn.title = 'Copy SKU';
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
                formatter: function (cell) {
                    const title = (cell.getValue() || '').toString().trim();
                    if (!title) return '<span class="slo-oc-missing">—</span>';
                    const wrap = document.createElement('span');
                    wrap.className = 'slo-text-dot-wrap';
                    const dot = document.createElement('span');
                    dot.className = 'slo-text-dot';
                    wrap.appendChild(dot);
                    const box = document.createElement('span');
                    box.className = 'slo-text-dot-box';
                    box.textContent = title;
                    wrap.appendChild(box);
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
                formatter: function (cell) { return formatMoney(cell.getValue()); },
            },
            {
                title: 'Info',
                field: 'cost_ship',
                minWidth: 48,
                width: 52,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: false,
                headerTooltip: 'CP, freight, dimensions, weights, LP & shipping',
                titleFormatter: function () {
                    const icon = document.createElement('i');
                    icon.className = 'fas fa-magnifying-glass';
                    icon.setAttribute('aria-hidden', 'true');
                    return icon;
                },
                formatter: function () {
                    return '<button type="button" class="slo-cs-btn" title="View cost & shipping"><i class="fas fa-magnifying-glass"></i></button>';
                },
                cellClick: function (e, cell) {
                    e.preventDefault();
                    e.stopPropagation();
                    sloOpenCostShipModal(cell.getRow().getData() || {});
                },
            },
            {
                title: 'Groi%',
                field: 'groi_pct',
                minWidth: 70,
                hozAlign: 'center',
                headerHozAlign: 'center',
                sorter: 'number',
                headerTooltip: 'SKU GROI% from this marketplace Analytics page. Red <50%, dark green 50–100%, purple >100%.',
                formatter: function (cell) { return sloFormatPctCell(cell, 'groi'); },
            },
            {
                title: 'Nroi%',
                field: 'nroi_pct',
                minWidth: 70,
                hozAlign: 'center',
                headerHozAlign: 'center',
                sorter: 'number',
                headerTooltip: 'SKU NROI% from this marketplace Analytics page. Red <40%, dark green 40–75%, purple >75%.',
                formatter: function (cell) { return sloFormatPctCell(cell, 'nroi'); },
            },
            {
                title: 'Gpft%',
                field: 'gpft_pct',
                minWidth: 70,
                hozAlign: 'center',
                headerHozAlign: 'center',
                sorter: 'number',
                headerTooltip: 'SKU GPFT% from this marketplace Analytics page. Red <17%, dark green 17–33%, purple >33%.',
                formatter: function (cell) { return sloFormatPctCell(cell, 'gpft'); },
            },
            {
                title: 'Npft%',
                field: 'npft_pct',
                minWidth: 70,
                hozAlign: 'center',
                headerHozAlign: 'center',
                sorter: 'number',
                headerTooltip: 'SKU NPFT% from this marketplace Analytics page. Red <10%, dark green 10–27%, purple >27%.',
                formatter: function (cell) { return sloFormatPctCell(cell, 'npft'); },
            },
            {
                title: 'INV',
                field: 'INV',
                minWidth: 70,
                hozAlign: 'center',
                headerHozAlign: 'center',
                sorter: 'number',
                formatter: function (cell) {
                    const raw = cell.getValue();
                    const n = (raw === null || raw === undefined || raw === '') ? 0 : Number(raw);
                    const label = Number.isFinite(n) ? n.toLocaleString() : '0';
                    if (!Number.isFinite(n) || n === 0) {
                        return '<span class="text-danger fw-semibold">' + label + '</span>';
                    }
                    return label;
                },
            },
            {
                title: 'ovl30',
                field: 'l30',
                minWidth: 70,
                hozAlign: 'center',
                headerHozAlign: 'center',
                sorter: 'number',
                headerTooltip: 'OV L30 — same as /amazon-tabulator-view (shopify quantity)',
                formatter: function (cell) {
                    const n = Math.round(parseFloat(cell.getValue()) || 0);
                    return String(n);
                },
            },
            {
                title: 'DIL',
                field: 'dil',
                minWidth: 70,
                hozAlign: 'center',
                headerHozAlign: 'center',
                sorter: 'number',
                headerTooltip: 'Dil% = OV L30 / INV × 100 — same slabs as /amazon-tabulator-view',
                formatter: function (cell) {
                    const row = cell.getRow().getData() || {};
                    const inv = parseFloat(row.INV) || 0;
                    const ovl30 = parseFloat(row.l30) || 0;
                    if (inv === 0) {
                        return '<span class="slo-dil slo-dil-zero">0%</span>';
                    }
                    const dil = (ovl30 / inv) * 100;
                    let cls = 'slo-dil-pink';
                    if (dil < 25) cls = 'slo-dil-red';
                    else if (dil < 50) cls = 'slo-dil-green';
                    return '<span class="slo-dil ' + cls + '">' + Math.round(dil) + '%</span>';
                },
            },
            {
                title: 'Status',
                field: 'status_label',
                minWidth: 90,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: true,
                sorter: sloStringSorter,
                headerTooltip: 'Original marketplace status',
                formatter: function (cell) {
                    const label = escapeHtml(cell.getValue() || cell.getRow().getData().status || '—');
                    return `<span class="slo-status-badge">${label}</span>`;
                },
            },
            {
                title: 'Label',
                field: 'label',
                minWidth: 90,
                hozAlign: 'center',
                headerHozAlign: 'center',
                formatter: function (cell) {
                    const v = (cell.getValue() || '').toString().trim();
                    return v ? escapeHtml(v) : '<span class="slo-oc-missing">—</span>';
                },
            },
        ],
    });

    table.on('dataSorted', function (sorters) {
        if (table._sloSorting) return;
        const first = sorters && sorters[0];
        const ok = first && (first.field === 'order_date' || first.column === 'order_date') && first.dir === 'desc';
        if (!ok) {
            sloApplyLatestFirstSort(table);
        }
    });

    let searchTimer = null;
    $('#slo-order-search, #slo-channel-filter').on('input keyup', function () {
        if (searchTimer) clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { applyClientFilters(table); }, 150);
    });
    $('#slo-status-filter').on('change', function () {
        applyClientFilters(table);
    });
    document.getElementById('slo-gpft-low-badge')?.addEventListener('click', function () {
        sloGpftLowFilter = !sloGpftLowFilter;
        this.classList.toggle('is-active', sloGpftLowFilter);
        this.title = sloGpftLowFilter
            ? 'Showing GPFT% under 10%. Click to show all.'
            : 'Showing all. Click to filter GPFT% under 10%.';
        applyClientFilters(table);
    });
    document.getElementById('slo-groi-low-badge')?.addEventListener('click', function () {
        sloGroiLowFilter = !sloGroiLowFilter;
        this.classList.toggle('is-active', sloGroiLowFilter);
        this.title = sloGroiLowFilter
            ? 'Showing GROI% under 50%. Click to show all.'
            : 'Click to filter GROI% under 50%.';
        applyClientFilters(table);
    });
    document.getElementById('slo-npft-alert-badge')?.addEventListener('click', function () {
        sloNpftAlertFilter = !sloNpftAlertFilter;
        this.classList.toggle('is-active', sloNpftAlertFilter);
        this.title = sloNpftAlertFilter
            ? 'Showing orders with NPRFT% under 10%. Click to show all.'
            : 'Price alert: click to show orders with NPRFT% under 10%. Counts distinct SKUs.';
        applyClientFilters(table);
    });
    document.getElementById('slo-nroi-alert-badge')?.addEventListener('click', function () {
        sloNroiAlertFilter = !sloNroiAlertFilter;
        this.classList.toggle('is-active', sloNroiAlertFilter);
        this.title = sloNroiAlertFilter
            ? 'Showing orders with NROI% under 30%. Click to show all.'
            : 'Price alert: click to show orders with NROI% under 30%. Counts distinct SKUs.';
        applyClientFilters(table);
    });

    document.getElementById('slo-apply-dates')?.addEventListener('click', function () {
        sloUpdateDateHint();
        table.setData();
    });
    // Quick range dropdown: dates are Eastern calendar days, like the server.
    function sloEasternDate(offsetDays) {
        const parts = new Intl.DateTimeFormat('en-CA', {
            timeZone: SLO_TZ, year: 'numeric', month: '2-digit', day: '2-digit',
        }).formatToParts(new Date()).reduce(function (acc, p) { acc[p.type] = p.value; return acc; }, {});
        const d = new Date(Date.UTC(Number(parts.year), Number(parts.month) - 1, Number(parts.day)));
        d.setUTCDate(d.getUTCDate() + offsetDays);
        return d.toISOString().slice(0, 10);
    }
    function sloPresetRange(preset) {
        switch (preset) {
            case 'l7': return [sloEasternDate(-7), sloEasternDate(0)];
            case 'yesterday': return [sloEasternDate(-1), sloEasternDate(-1)];
            case 'today': return [sloEasternDate(0), sloEasternDate(0)];
            default: return [@json($sloDateFrom ?? ''), @json($sloDateTo ?? '')];
        }
    }
    const sloPresetEl = document.getElementById('slo-date-preset');
    sloPresetEl?.addEventListener('change', function () {
        if (sloPresetEl.value === 'custom') return;
        const range = sloPresetRange(sloPresetEl.value);
        const fromEl = document.getElementById('slo-date-from');
        const toEl = document.getElementById('slo-date-to');
        if (fromEl) fromEl.value = range[0];
        if (toEl) toEl.value = range[1];
        sloUpdateDateHint();
        table.setData();
    });
    ['slo-date-from', 'slo-date-to'].forEach(function (id) {
        document.getElementById(id)?.addEventListener('change', function () {
            if (sloPresetEl) sloPresetEl.value = 'custom';
            sloUpdateDateHint();
        });
    });
    sloUpdateDateHint();
})();
</script>
<script>
(function () {
    const SUMMARY_URL = @json(route('sales.loss.order.summary'));
    const KEYS = ['l30', 'l7', 'yesterday', 'today'];
    let data = null;
    let current = 'l30';
    let sortBy = 'pct';
    let query = '';

    const esc = (v) => String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    const num = (n) => Number(n || 0).toLocaleString();
    // Rounded to whole dollars / whole percent (pass d = 2 for cents)
    const money = (n, d) => {
        d = d || 0;
        const v = Number(Number(n || 0).toFixed(d));
        return (v < 0 ? '-' : '') + '$' + Math.abs(v).toLocaleString(undefined, { minimumFractionDigits: d, maximumFractionDigits: d });
    };
    const pct = (n) => (n === null || n === undefined || isNaN(Number(n))) ? '—' : (Math.round(Number(n)) || 0) + '%';

    function countUp(el, to) {
        const start = performance.now();
        const dur = 650;
        (function step(now) {
            const t = Math.min(1, (now - start) / dur);
            el.textContent = num(Math.round(to * (1 - Math.pow(1 - t, 3))));
            if (t < 1) requestAnimationFrame(step);
        })(start);
    }

    function paintCards() {
        KEYS.forEach(function (k) {
            const card = document.querySelector('.lossx-card[data-p="' + k + '"]');
            const p = data && data.periods ? data.periods[k] : null;
            if (!card || !p) return;
            card.classList.remove('is-loading');
            countUp(card.querySelector('[data-f="loss_orders"]'), p.loss_orders);
            const rate = p.orders > 0 ? (p.loss_orders / p.orders) * 100 : 0;
            card.querySelector('[data-f="sub"]').textContent = p.orders > 0
                ? num(p.loss_orders) + ' of ' + num(p.orders) + ' orders (' + rate.toFixed(1) + '%) · ' + num(p.skus) + ' products'
                : 'No orders in this period';
            card.querySelector('[data-f="bar"]').style.width = Math.min(100, rate) + '%';
            card.querySelector('[data-f="amount"]').textContent = p.loss_amount < 0 ? 'Est. ' + money(p.loss_amount) : 'No loss amount';
            card.querySelector('[data-f="nroi"]').textContent = pct(p.nroi_pct);
            card.querySelector('[data-f="npft"]').textContent = pct(p.npft_pct);
        });
    }

    function load(refresh) {
        document.querySelectorAll('.lossx-card').forEach(function (c) { c.classList.add('is-loading'); });
        fetch(SUMMARY_URL + (refresh ? '?refresh=1' : ''), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (!json || !json.success) throw new Error((json && json.message) || 'failed');
                data = json;
                paintCards();
            })
            .catch(function () {
                document.querySelectorAll('.lossx-card').forEach(function (c) {
                    c.classList.remove('is-loading');
                    c.querySelector('[data-f="sub"]').textContent = 'Could not load. Tap to retry.';
                    c.querySelector('[data-f="amount"]').textContent = '';
                });
            });
    }

    function periodGroups(key) {
        const p = data.periods[key];
        const groups = new Map();
        data.rows.forEach(function (r) {
            const d = String(r.date || '').slice(0, 10);
            if (d < p.from || d > p.to) return;
            const gk = (r.sku || r.title || '(no sku)').trim() || '(no sku)';
            let g = groups.get(gk);
            if (!g) {
                g = { sku: r.sku || '(no sku)', title: r.title || '', orderKeys: new Set(), qty: 0, loss: 0, worst: null, channels: {}, orders: [], nroiSum: 0, nroiW: 0, npftSum: 0, npftW: 0 };
                groups.set(gk, g);
            }
            if (!g.title && r.title) g.title = r.title;
            g.orderKeys.add(r.channel + '|' + r.order_id);
            g.qty += Number(r.qty || 0);
            g.loss += Number(r.loss || 0);
            if (r.pct !== null && (g.worst === null || r.pct < g.worst)) g.worst = r.pct;
            g.channels[r.channel] = (g.channels[r.channel] || 0) + 1;
            g.orders.push(r);
            const w = Number(r.amount) > 0 ? Number(r.amount) : 1;
            if (r.nroi !== null && r.nroi !== undefined) { g.nroiSum += Number(r.nroi) * w; g.nroiW += w; }
            if (r.npft !== null && r.npft !== undefined) { g.npftSum += Number(r.npft) * w; g.npftW += w; }
        });
        groups.forEach(function (g) {
            g.nroi = g.nroiW > 0 ? g.nroiSum / g.nroiW : null;
            g.npft = g.npftW > 0 ? g.npftSum / g.npftW : null;
        });
        return Array.from(groups.values());
    }

    // ---------- extra columns taken from /price-increase: image, INV, L30, Dil%, LMP by Channel, SPRICE $ (+ siblings) ----------
    const PRODUCTS_URL = @json(route('sales.loss.order.products'));
    const PI_URL = @json(url('/price-increase'));
    const PUSH_STATUS_URL = @json(url('/price-push-status'));
    const ACTIONS_URL = @json(route('sales.loss.order.actions'));
    const ACTION_HISTORY_URL = @json(route('sales.loss.order.actions.history'));
    const REASONS_URL = @json(route('sales.loss.order.reasons'));
    const CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    const NPFT_BELOW = 10;   // NPRFT% under this = loss sell
    const NROI_BELOW = 30;   // NROI% under this = loss sell
    const FAMILY_PAGE = 40;  // families (loss SKU + its siblings) drawn at a time

    const piRows = new Map();     // SKU (UPPER) -> row from /price-increase (incl. siblings)
    const piByParent = new Map(); // PARENT (UPPER) -> [rows]
    const piAsked = new Set();    // SKUs already asked from the server
    const typed = {};             // SKU (UPPER) -> price typed in SPRICE $ and not pushed yet
    const pushing = {};           // SKU (UPPER) -> { startedAt, price } while its push is being prepared
    const openRows = new Set();   // SKUs whose orders are expanded
    let piLoading = false;
    let shownFamilies = FAMILY_PAGE;
    const skuKey = (s) => String(s === null || s === undefined ? '' : s).trim().toUpperCase();

    const LMP_CHANNELS = [
        ['amazon', 'Amazon', 'A', 'background:transparent;color:#ff9900;border:1px solid #ff9900;'],
        ['ebay', 'eBay', 'E', 'background:#0dcaf0;color:#000;'],
        ['google', 'Google', 'G', 'background:#198754;color:#fff;'],
        ['bestbuy', 'BestBuy', 'B', 'background:#0046be;color:#fff;'],
        ['macy', 'Macy', 'M', 'background:#e21a2c;color:#fff;'],
        ['reverb', 'Reverb', 'R', 'background:#d9281d;color:#fff;'],
        ['temu', 'Temu', 'T', 'background:#fb7701;color:#fff;'],
        ['aliexpress', 'AliExpress', 'AE', 'background:#ff4747;color:#fff;'],
        ['tiktok', 'TikTok', 'TK', 'background:#111;color:#fff;border:1px solid rgba(255,255,255,.35);']
    ];

    function toast(msg, isErr) {
        const el = document.createElement('div');
        el.className = 'lossx-toast' + (isErr ? ' is-err' : '');
        el.textContent = msg;
        document.body.appendChild(el);
        setTimeout(function () { el.remove(); }, 6000);
    }

    function setStatus(html) {
        const el = document.getElementById('lossxStatus');
        if (el) el.innerHTML = html || '';
    }

    function indexParents() {
        piByParent.clear();
        piRows.forEach(function (r) {
            const p = skuKey(r.parent);
            if (!p) return;
            if (!piByParent.has(p)) piByParent.set(p, []);
            piByParent.get(p).push(r);
        });
        piByParent.forEach(function (rows) { rows.sort(function (a, b) { return String(a.sku).localeCompare(String(b.sku)); }); });
    }

    /** Ask the server for the /price-increase data of every loss SKU of this period and all their siblings. */
    function loadProducts() {
        const need = [];
        periodGroups(current).forEach(function (g) {
            const k = skuKey(g.sku);
            if (k && k !== '(NO SKU)' && !piAsked.has(k)) need.push(g.sku);
        });
        if (!need.length || piLoading) return;
        piLoading = true;
        need.forEach(function (s) { piAsked.add(skuKey(s)); });
        setStatus('<i class="fas fa-spinner fa-spin"></i> Loading image, INV, L30, Dil%, LMP by Channel, SPRICE $ and siblings from /price-increase… (the first load can take a minute)');
        fetch(PRODUCTS_URL, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ skus: need })
        }).then(function (r) { return r.json(); }).then(function (json) {
            if (!json || !json.success) throw new Error((json && json.message) || 'failed');
            (json.rows || []).forEach(function (r) { piRows.set(skuKey(r.sku), r); });
            indexParents();
            setStatus('');
        }).catch(function (e) {
            need.forEach(function (s) { piAsked.delete(skuKey(s)); });
            setStatus('<span class="lossx-neg">Could not load the /price-increase data' + (e && e.message ? ' (' + esc(e.message) + ')' : '') + '.</span> <button type="button" class="lossx-sort" data-retry="1">Retry</button>');
        }).finally(function () {
            piLoading = false;
            if (data) renderModal();
        });
    }

    function lowestLmp(pi) {
        let low = 0;
        Object.keys((pi && pi.lmp) || {}).forEach(function (k) {
            const v = Number(pi.lmp[k]);
            if (v > 0 && (!low || v < low)) low = v;
        });
        return low;
    }

    /** LMP by Channel — every channel's marketplace price, lowest first (same badges as /price-increase). */
    function lmpHtml(pi) {
        if (!pi) return '<span class="lossx-dim">—</span>';
        const found = [];
        LMP_CHANNELS.forEach(function (c) {
            const v = Number(pi.lmp && pi.lmp[c[0]]);
            if (v > 0) found.push({ c: c, v: v });
        });
        if (!found.length) return '<span class="lossx-dim">—</span>';
        found.sort(function (a, b) { return a.v - b.v; });
        const tip = found.map(function (x) { return x.c[1] + ' $' + x.v.toFixed(2); }).join(' · ');
        return '<div class="lossx-lmp-wrap" title="LMP by channel, lowest first: ' + esc(tip) + '">' + found.map(function (x, i) {
            return '<span class="lossx-lmp" style="' + x.c[3] + (i === 0 ? '' : 'font-size:.6rem;padding:2px 4px;font-weight:600;opacity:.92;') + '" title="' + esc(x.c[1]) + '">' +
                x.c[2] + ' $' + x.v.toFixed(2) + '</span>';
        }).join('') + '</div>';
    }

    /** The price the SPRICE $ box shows: its own saved price, else the saved Amazon SPRICE (same as /price-increase). */
    function spriceSaved(pi) {
        const own = Number(pi && pi.box_sprice);
        if (own > 0) return own;
        const seed = Number(pi && pi.amazon_sprice);
        return seed > 0 ? seed : 0;
    }

    /** GPFT / GROI / NPFT / NROI of Amazon at this price — same formulas as the /price-increase SPRICE $ column. */
    function spriceMetrics(price, pi) {
        price = Number(price) || 0;
        const lp = Number(pi && pi.lp) || 0;
        const ship = Number(pi && pi.ship) || 0;
        const ads = Number(pi && pi.ads) || 0;
        const margin = Number(pi && pi.margin) > 0 ? Number(pi.margin) : 0.8;
        if (!(price > 0) || !(lp > 0)) return null;
        const gross = price * 0.80 - ship - lp;
        const gpft = gross / price * 100;
        return { gpft: gpft, groi: gross / lp * 100, npft: gpft - ads, nroi: (price * margin - ship - lp - price * (ads / 100)) / lp * 100 };
    }

    function chipsHtml(price, pi) {
        const m = spriceMetrics(price, pi);
        const defs = [['GPFT', m && m.gpft, 'gpft'], ['GROI', m && m.groi, 'groi'], ['NPFT', m && m.npft, 'npft'], ['NROI', m && m.nroi, 'nroi']];
        const title = m ? 'Amazon percentages at this price' : 'Enter a price (needs LP) to see GPFT / GROI / NPFT / NROI';
        return defs.map(function (v) {
            let val = '<span class="lossx-dim">-</span>';
            if (m) {
                val = window.MetricPctColors
                    ? window.MetricPctColors.htmlFor(v[2], v[1], { decimals: 0, empty: '0%' })
                    : '<span style="color:#dc3545;font-weight:700;">' + Math.round(v[1]) + '%</span>';
            }
            return '<span class="lossx-chip2" title="' + title + '"><i>' + v[0] + '</i>' + val + '</span>';
        }).join('');
    }

    // "Siblings" rule — the SAME per-SKU switch as the Siblings tick of the /price-increase SKU window (shared browser storage):
    // ticked → the SPRICE push is also saved + pushed on every sibling SKU under the parent (price + the "+$" addition).
    const SIB_PREF_KEY = 'cvr_master_siblings_apply_by_sku_v1';
    const SIB_ADD_KEY = 'cvr_master_siblings_add_by_sku_v1';
    function lsMap(k) {
        try { const m = JSON.parse(localStorage.getItem(k) || '{}'); return (m && typeof m === 'object') ? m : {}; } catch (e) { return {}; }
    }
    function sibOn(key) { return !!lsMap(SIB_PREF_KEY)[key]; }
    function sibAdd(key) { const v = lsMap(SIB_ADD_KEY)[key]; return (v === undefined || v === null) ? '' : String(v); }
    function sibSave(key, on, add) {
        const pm = lsMap(SIB_PREF_KEY);
        if (on) pm[key] = 1; else delete pm[key];
        const am = lsMap(SIB_ADD_KEY);
        const raw = String(add === undefined || add === null ? '' : add).trim();
        if (raw === '') delete am[key]; else am[key] = raw;
        try {
            localStorage.setItem(SIB_PREF_KEY, JSON.stringify(pm));
            localStorage.setItem(SIB_ADD_KEY, JSON.stringify(am));
        } catch (e) { /* ignore */ }
    }
    function siblingsOf(pi) {
        const p = pi && pi.parent ? skuKey(pi.parent) : '';
        if (!p) return [];
        return (piByParent.get(p) || []).filter(function (r) { return skuKey(r.sku) !== skuKey(pi.sku); });
    }
    function addNum(raw) {
        const v = parseFloat(String(raw === undefined || raw === null ? '' : raw).replace(/[$,\s]/g, '').replace(',', '.'));
        return isFinite(v) ? +Math.max(-10000, Math.min(10000, v)).toFixed(2) : 0;
    }

    /** SPRICE $ — editable price + ✓ (Push All, same as /price-increase) + profit chips. */
    function spHtml(pi) {
        if (!pi) return '<span class="lossx-dim" title="This SKU is not on /price-increase">—</span>';
        const key = skuKey(pi.sku);
        const saved = spriceSaved(pi);
        const t = Object.prototype.hasOwnProperty.call(typed, key) ? typed[key] : null;
        const shown = t !== null ? t : (saved > 0 ? saved.toFixed(2) : '');
        const p = parseFloat(shown);
        const low = lowestLmp(pi);
        const below = p > 0 && low > 0 && p < low - 0.004;
        const busy = !!pushing[key];
        return '<div class="lossx-sp" data-key="' + esc(key) + '">' +
            '<input type="number" class="lossx-sp-in' + (below ? ' is-below' : '') + '" step="0.01" min="0.01" placeholder="0.00" value="' + esc(shown) + '"' +
                ' title="SPRICE $ — press ✓ or Enter to fill every channel, save and push (Push All)' + (low > 0 ? '\nLowest LMP $' + low.toFixed(2) : '') + '">' +
            '<button type="button" class="lossx-sp-go"' + (busy ? ' disabled' : '') + ' title="Push All — fills every channel by its column rule, then saves and pushes them (same as the ✓ on /price-increase)" aria-label="Push all channels with this SPRICE">' +
                '<i class="fas ' + (busy ? 'fa-spinner fa-spin' : 'fa-check') + '"></i></button>' +
            sibHtml(pi, key) +
            '<span class="lossx-chips">' + chipsHtml(p > 0 ? p : saved, pi) + '</span></div>';
    }

    function sibHtml(pi, key) {
        const sibs = siblingsOf(pi);
        if (!sibs.length) return '';
        const on = sibOn(key);
        return '<div class="lossx-sibrow">' +
            '<label title="Same Siblings rule as /price-increase: when ticked, this SPRICE is also saved and pushed on all ' + sibs.length + ' sibling SKU(s) of this parent (' + esc(sibs.slice(0, 6).map(function (r) { return r.sku; }).join(', ')) + (sibs.length > 6 ? ', …' : '') + ')">' +
                '<input type="checkbox" class="lossx-sib-cb"' + (on ? ' checked' : '') + '> Siblings (' + sibs.length + ')</label>' +
            '<input type="number" class="lossx-sib-add" step="0.01" placeholder="+$" value="' + esc(sibAdd(key)) + '"' + (on ? '' : ' disabled') +
                ' title="Siblings addition ($): every sibling gets this SKU\'s price + this amount (e.g. 7.44 + 0.01 = 7.45). Saved per SKU, shared with /price-increase.">' +
            '</div>';
    }

    function refreshSp(key) {
        const pi = piRows.get(key);
        if (!pi) return;
        document.querySelectorAll('#lossxList .lossx-sp').forEach(function (el) {
            if (el.dataset.key !== key) return;
            const tmp = document.createElement('div');
            tmp.innerHTML = spHtml(pi);
            if (tmp.firstElementChild) el.replaceWith(tmp.firstElementChild);
        });
    }

    function imgHtml(pi, rank) {
        const src = pi && pi.image ? pi.image : '';
        const img = src
            ? '<img class="lossx-img" loading="lazy" src="' + esc(src) + '" alt="">'
            : '<div class="lossx-img is-missing"><i class="fas fa-image"></i></div>';
        return '<div class="lossx-imgbox">' + img + (rank ? '<div class="lossx-rank">' + rank + '</div>' : '') + '</div>';
    }

    function numCells(o) {
        const dash = '<span class="lossx-dim">—</span>';
        const bad = function (v, lim) { return v !== null && v !== undefined && !isNaN(Number(v)) && Number(v) < lim; };
        const has = function (v) { return v !== null && v !== undefined && !isNaN(Number(v)); };
        return '<div class="lossx-c">' + (o.orders ? num(o.orders) : dash) + '</div>' +
            '<div class="lossx-c">' + (o.units ? num(o.units) : dash) + '</div>' +
            '<div class="lossx-c is-loss">' + (has(o.worst) ? pct(o.worst) : dash) + '</div>' +
            '<div class="lossx-c' + (bad(o.nroi, NROI_BELOW) ? ' is-loss' : '') + '">' + (has(o.nroi) ? pct(o.nroi) : dash) + '</div>' +
            '<div class="lossx-c' + (bad(o.npft, NPFT_BELOW) ? ' is-loss' : '') + '">' + (has(o.npft) ? pct(o.npft) : dash) + '</div>' +
            '<div class="lossx-c is-loss">' + (o.loss ? money(o.loss) : dash) + '</div>';
    }

    /** DIL% coloured by our rule (red < 25 · green 25–50 · pink >= 50), same as every other page. */
    function dilHtml(v) {
        const n = Number(v) || 0;
        if (window.MetricPctColors) return window.MetricPctColors.htmlFor('dil', n, { decimals: 0, empty: '0%' });
        const r = Math.round(n);
        return '<span style="color:' + (r < 25 ? '#dc3545' : (r < 50 ? '#198754' : '#ff2db4')) + ';font-weight:700;">' + r + '%</span>';
    }

    function invCells(pi) {
        if (!pi) return '<div class="lossx-c lossx-dim">—</div><div class="lossx-c lossx-dim">—</div><div class="lossx-c lossx-dim">—</div>';
        return '<div class="lossx-c">' + num(pi.inv) + '</div><div class="lossx-c">' + num(pi.l30) + '</div><div class="lossx-c">' + dilHtml(pi.dil) + '</div>';
    }

    // ---------- Action column: Fixed / Ignore (+ reason) with a per-SKU history ----------
    let actionMap = {};                 // SKU (UPPER) -> { action: fixed|ignored, reason, user, at }
    let reasons = ['Clearance'];
    const needReason = new Set();       // SKUs where Ignore was chosen but no reason picked yet
    let actionFilter = 'open';   // default: products still waiting for an action

    /** Fixed / Open / Ignored counts of this period's loss products (badges on top; click = filter, click again = all). */
    function paintActionBadges() {
        const counts = { open: 0, fixed: 0, ignored: 0 };
        periodGroups(current).forEach(function (g) {
            const st = actionMap[skuKey(g.sku)];
            counts[st && counts.hasOwnProperty(st.action) ? st.action : 'open']++;
        });
        document.querySelectorAll('#lossxBadges .lossx-abadge').forEach(function (b) {
            b.querySelector('[data-c]').textContent = num(counts[b.dataset.af]);
            b.classList.toggle('is-on', actionFilter === b.dataset.af);
        });
        const sel = document.getElementById('lossxFilter');
        if (sel && sel.value !== actionFilter) sel.value = actionFilter;
    }

    function setActionFilter(f) {
        actionFilter = f || 'all';
        playerOff();
        shownFamilies = FAMILY_PAGE;
        renderModal();
    }

    function actState(key) {
        if (needReason.has(key)) return 'ignored-pending';
        const st = actionMap[key];
        return st ? st.action : '';
    }

    function actClass(key) {
        const s = actState(key);
        return s === 'fixed' ? ' is-fixed' : (s === 'ignored' ? ' is-ignored' : '');
    }

    function actCellHtml(key, sku) {
        const st = actionMap[key];
        const need = needReason.has(key);
        const sel = need ? 'ignored' : (st ? st.action : '');
        let h = '<div class="lossx-act lossx-act-cell" data-key="' + esc(key) + '" data-sku="' + esc(sku) + '">' +
            '<select class="lossx-act-sel' + (sel ? ' is-' + sel : '') + '" aria-label="Action">' +
                '<option value=""' + (sel === '' ? ' selected' : '') + '>Action…</option>' +
                '<option value="fixed"' + (sel === 'fixed' ? ' selected' : '') + '>Fixed</option>' +
                '<option value="ignored"' + (sel === 'ignored' ? ' selected' : '') + '>Ignore</option>' +
            '</select>';
        if (sel === 'ignored') {
            const cur = !need && st ? (st.reason || '') : '';
            h += '<select class="lossx-reason-sel' + (cur ? '' : ' is-need') + '" aria-label="Reason">' +
                '<option value=""' + (cur ? '' : ' selected') + '>Reason…</option>' +
                reasons.map(function (r) { return '<option value="' + esc(r) + '"' + (r === cur ? ' selected' : '') + '>' + esc(r) + '</option>'; }).join('') +
                '<option value="__new">＋ Add new reason…</option></select>';
        }
        h += '<button type="button" class="lossx-hist-btn" title="History of this SKU" aria-label="History"><i class="fas fa-history" aria-hidden="true"></i></button>';
        if (st && !need) h += '<div class="lossx-act-by">' + esc(st.user || '—') + ' · ' + esc(String(st.at || '').slice(0, 16)) + '</div>';
        return h + '</div>';
    }

    function refreshAct(key) {
        document.querySelectorAll('#lossxList .lossx-item').forEach(function (item) {
            if (item.dataset.key !== key) return;
            const cell = item.querySelector('.lossx-act-cell');
            if (cell) cell.outerHTML = actCellHtml(key, cell.dataset.sku);
            const s = actState(key);
            item.classList.toggle('is-fixed', s === 'fixed');
            item.classList.toggle('is-ignored', s === 'ignored');
        });
    }

    function postJson(url, body) {
        return fetch(url, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body)
        }).then(function (r) {
            return r.json().catch(function () { throw new Error('Request failed (' + r.status + ')'); }).then(function (j) {
                if (!r.ok || !j || !j.success) throw new Error((j && j.message) || ('Request failed (' + r.status + ')'));
                return j;
            });
        });
    }

    function loadHist(item) {
        const box = item.querySelector('.lossx-histbox');
        if (!box) return;
        box.innerHTML = '<div class="lossx-title"><i class="fas fa-spinner fa-spin"></i> Loading history…</div>';
        fetch(ACTION_HISTORY_URL + '?sku=' + encodeURIComponent(item.dataset.key), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                const rows = (j && j.history) || [];
                if (!rows.length) { box.innerHTML = '<div class="lossx-title">No actions recorded for this SKU yet.</div>'; return; }
                const label = { fixed: 'Fixed', ignored: 'Ignored', open: 'Cleared' };
                box.innerHTML = '<table><thead><tr><th>When</th><th>User</th><th>Action</th><th>Reason</th></tr></thead><tbody>' +
                    rows.map(function (r) {
                        return '<tr><td>' + esc(String(r.at || '').slice(0, 16)) + '</td><td>' + esc(r.user || '—') + '</td><td>' +
                            esc(label[r.action] || r.action) + '</td><td>' + esc(r.reason || '—') + '</td></tr>';
                    }).join('') + '</tbody></table>';
            })
            .catch(function () { box.innerHTML = '<div class="lossx-title">Could not load the history.</div>'; });
    }

    function saveAction(key, sku, action, reason) {
        postJson(ACTIONS_URL, { sku: sku, action: action, reason: reason || '' }).then(function (j) {
            needReason.delete(key);
            if (j.state) actionMap[key] = j.state; else delete actionMap[key];
            if (actionFilter !== 'all') renderModal(); else { refreshAct(key); paintActionBadges(); }
            document.querySelectorAll('#lossxList .lossx-item.is-hist').forEach(function (it) { if (it.dataset.key === key) loadHist(it); });
            toast(sku + ': ' + (action === 'open' ? 'action cleared' : (action === 'fixed' ? 'marked Fixed' : 'ignored — ' + reason)));
        }).catch(function (err) {
            toast((err && err.message) || 'Could not save the action', true);
            refreshAct(key);
        });
    }

    function loadActions() {
        fetch(ACTIONS_URL, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j) return;
                if (j.reasons && j.reasons.length) reasons = j.reasons;
                if (j.success) { actionMap = j.actions || {}; renderModal(); }
            }).catch(function () {});
    }
    function lossItemHtml(g, rank, pi) {
        const channels = Object.keys(g.channels).sort().map(function (c) {
            return '<span class="lossx-tag">' + esc(c) + ' · ' + g.channels[c] + '</span>';
        }).join('');
        const orders = g.orders.slice().sort(function (a, b) { return (a.pct ?? 0) - (b.pct ?? 0); }).slice(0, 60).map(function (o) {
            return '<tr><td>' + esc(o.channel) + '</td><td>' + esc(o.order_id) + '</td><td>' + esc(o.date) + '</td><td>' + esc(o.qty) +
                '</td><td>' + (o.amount === null ? '—' : money(o.amount, 2)) + '</td><td class="lossx-neg">' + pct(o.pct) +
                '</td><td>' + pct(o.nroi) + '</td><td>' + pct(o.npft) +
                '</td><td class="lossx-neg">' + (o.loss ? money(o.loss) : '—') + '</td></tr>';
        }).join('');
        const more = g.orders.length > 60 ? '<div class="lossx-title mt-1">Showing the 60 worst of ' + g.orders.length + ' lines.</div>' : '';
        const open = openRows.has(skuKey(g.sku));
        return '<div class="lossx-item' + (open ? ' is-open' : '') + actClass(skuKey(g.sku)) + '" data-key="' + esc(skuKey(g.sku)) + '">' +
            '<div class="lossx-row" role="button" tabindex="0">' +
                imgHtml(pi, rank) +
                '<div style="min-width:0"><div class="lossx-sku" title="' + esc(g.title || g.sku) + '">' + esc(g.sku) + '<i class="fas fa-chevron-down lossx-chev" aria-hidden="true"></i></div>' +
                    '<div class="lossx-tags">' + channels + '</div></div>' +
                numCells({ orders: g.orderKeys.size, units: g.qty, worst: g.worst, nroi: g.nroi, npft: g.npft, loss: g.loss }) +
                invCells(pi) +
                '<div>' + lmpHtml(pi) + '</div>' +
                '<div>' + spHtml(pi) + '</div>' +
                actCellHtml(skuKey(g.sku), g.sku) +
            '</div>' +
            '<div class="lossx-histbox"></div>' +
            '<div class="lossx-orders"><table><thead><tr><th>Channel</th><th>Order</th><th>Date</th><th>Qty</th><th>Amount</th><th>Profit %</th><th>NROI%</th><th>NPRFT%</th><th>Est. loss</th></tr></thead><tbody>' +
                orders + '</tbody></table>' + more + '</div></div>';
    }

    /** Sales of a sibling SKU in the period (all its orders, not only the loss ones). */
    function sibStats(pi, p) {
        const o = { orders: 0, units: 0, worst: null, nroi: null, npft: null, loss: 0 };
        let w = 0, nr = 0, np = 0;
        const days = pi.days || {};
        Object.keys(days).forEach(function (d) {
            if (d < p.from || d > p.to) return;
            const a = days[d];
            o.orders += a[0]; o.units += a[1]; w += a[2]; nr += a[3]; np += a[4]; o.loss += a[5];
        });
        if (w > 0) { o.nroi = nr / w; o.npft = np / w; }
        return o;
    }

    function sibItemHtml(pi, p) {
        return '<div class="lossx-item is-sib' + actClass(skuKey(pi.sku)) + '" data-key="' + esc(skuKey(pi.sku)) + '">' +
            '<div class="lossx-row">' +
                imgHtml(pi, 0) +
                '<div style="min-width:0"><div class="lossx-sku">' + esc(pi.sku) + '<span class="lossx-sibtag">sibling</span></div></div>' +
                numCells(sibStats(pi, p)) +
                invCells(pi) +
                '<div>' + lmpHtml(pi) + '</div>' +
                '<div>' + spHtml(pi) + '</div>' +
                actCellHtml(skuKey(pi.sku), pi.sku) +
            '</div><div class="lossx-histbox"></div></div>';
    }

    /** Loss SKUs grouped by parent: the family = its loss SKUs first, then every other SKU of that parent. */
    function buildFamilies(groups) {
        const fams = [];
        const byParent = new Map();
        const lossKeys = new Set(groups.map(function (g) { return skuKey(g.sku); }));
        groups.forEach(function (g) {
            const pi = piRows.get(skuKey(g.sku));
            const parent = pi && pi.parent ? skuKey(pi.parent) : '';
            let fam = parent ? byParent.get(parent) : null;
            if (!fam) {
                fam = { parent: parent, losses: [], sibs: [] };
                fams.push(fam);
                if (parent) byParent.set(parent, fam);
            }
            fam.losses.push(g);
        });
        fams.forEach(function (fam) {
            if (!fam.parent) return;
            (piByParent.get(fam.parent) || []).forEach(function (r) {
                if (!lossKeys.has(skuKey(r.sku))) fam.sibs.push(r);
            });
        });
        return fams;
    }

    const COLHEAD = '<div class="lossx-colhead"><span>Image</span><span class="is-left">SKU</span><span>Orders</span><span>Units</span><span>Worst</span>' +
        '<span>NROI%</span><span>NPRFT%</span><span>Est. loss</span><span>INV</span><span>L30</span><span>DIL%</span>' +
        '<span class="is-left">LMP by Channel</span><span class="is-left">SPRICE $</span><span class="is-left">Action</span></div>';

    function renderModal() {
        const p = data.periods[current];
        const content = document.getElementById('lossxContent');
        const gradients = { l30: ['#7c3aed', '#ec4899'], l7: ['#f97316', '#e11d48'], yesterday: ['#0ea5e9', '#6366f1'], today: ['#10b981', '#0ea5e9'] };
        content.style.setProperty('--g1', gradients[current][0]);
        content.style.setProperty('--g2', gradients[current][1]);
        document.getElementById('lossxPeriod').textContent = p.label;
        document.getElementById('lossxRange').textContent = (p.from === p.to ? p.from : p.from + ' → ' + p.to) + ' · EDT · loss sell = NPRFT% below ' + NPFT_BELOW + ' or NROI% below ' + NROI_BELOW + ' (gross profit when net is missing) · cancelled orders excluded';
        document.getElementById('lossxStatOrders').textContent = num(p.loss_orders);
        document.getElementById('lossxStatSkus').textContent = num(p.skus);
        document.getElementById('lossxStatLoss').textContent = money(p.loss_amount);
        document.getElementById('lossxStatRate').textContent = (p.orders > 0 ? (p.loss_orders / p.orders) * 100 : 0).toFixed(1) + '%';
        document.getElementById('lossxStatNroi').textContent = pct(p.nroi_pct);
        document.getElementById('lossxStatNpft').textContent = pct(p.npft_pct);
        paintActionBadges();

        let groups = periodGroups(current);
        const q = query.trim().toLowerCase();
        if (q) {
            groups = groups.filter(function (g) {
                return g.sku.toLowerCase().includes(q) || g.title.toLowerCase().includes(q)
                    || Object.keys(g.channels).some(function (c) { return c.toLowerCase().includes(q); });
            });
        }
        if (actionFilter !== 'all') {
            groups = groups.filter(function (g) {
                const st = actionMap[skuKey(g.sku)];
                return (st ? st.action : 'open') === actionFilter;
            });
        }
        groups.sort(function (a, b) {
            if (sortBy === 'loss') return a.loss - b.loss;
            if (sortBy === 'pct') return (a.worst === null ? 1e9 : a.worst) - (b.worst === null ? 1e9 : b.worst);
            return b.orderKeys.size - a.orderKeys.size || a.loss - b.loss;
        });

        const list = document.getElementById('lossxList');
        if (!groups.length) {
            player.total = 0;
            playerSync();
            list.innerHTML = '<div class="lossx-empty"><i class="fas fa-circle-check fa-2x mb-2" aria-hidden="true"></i><div><b>' +
                (q || actionFilter !== 'all' ? 'No products match that search / filter.' : 'No orders sold at a loss in this period.') + '</b></div></div>';
            return;
        }

        const fams = buildFamilies(groups);
        if (player.active) {
            // Player mode: one loss product at a time (with its siblings), numbered from #1
            const flat = [];
            fams.forEach(function (fam) { fam.losses.forEach(function (g) { flat.push({ fam: fam, g: g }); }); });
            player.total = flat.length;
            player.idx = Math.max(0, Math.min(player.idx, flat.length - 1));
            const cur = flat[player.idx];
            openRows.add(skuKey(cur.g.sku));
            let one = lossItemHtml(cur.g, player.idx + 1, piRows.get(skuKey(cur.g.sku)));
            cur.fam.sibs.forEach(function (r) { one += sibItemHtml(r, p); });
            list.innerHTML = '<div class="lossx-grid">' + COLHEAD + '<div class="lossx-fam">' + one + '</div></div>';
            playerSync();
            return;
        }
        player.total = fams.reduce(function (n, fam) { return n + fam.losses.length; }, 0);
        playerSync();
        const slice = fams.slice(0, shownFamilies);
        let rank = 0;
        const body = slice.map(function (fam) {
            let h = '';
            fam.losses.forEach(function (g) { h += lossItemHtml(g, ++rank, piRows.get(skuKey(g.sku))); });
            fam.sibs.forEach(function (r) { h += sibItemHtml(r, p); });
            return '<div class="lossx-fam">' + h + '</div><div class="lossx-fam-gap"></div>';
        }).join('');
        const left = fams.length - slice.length;
        list.innerHTML = '<div class="lossx-grid">' + COLHEAD + body +
            (left > 0 ? '<button type="button" class="lossx-sort lossx-more" id="lossxMore">Show more (' + num(left) + ' more product' + (left === 1 ? '' : 's') + ')</button>' : '') +
            '</div>';
    }

    // ---------- Player: ⏮ ▶ ⏭ — loss products one by one, starting with #1 ----------
    const PLAY_MS = 5000;
    const player = { active: false, idx: 0, total: 0, playing: false, timer: null };

    function playerSync() {
        const box = document.getElementById('lossxPlayer');
        if (!box) return;
        box.classList.toggle('is-active', player.active);
        document.getElementById('lossxPcount').textContent = (player.active ? player.idx + 1 : 0) + ' / ' + player.total;
        const pl = document.getElementById('lossxPlay');
        pl.classList.toggle('is-on', player.playing);
        pl.innerHTML = '<i class="fas fa-' + (player.playing ? 'pause' : 'play') + '" aria-hidden="true"></i>';
        pl.title = player.playing ? 'Pause' : 'Play — auto-advance every ' + (PLAY_MS / 1000) + ' seconds';
        pl.setAttribute('aria-label', player.playing ? 'Pause' : 'Play');
        document.getElementById('lossxPrev').disabled = player.active && player.idx <= 0;
        document.getElementById('lossxNext').disabled = player.active && player.idx >= player.total - 1;
        pl.disabled = player.total === 0;
    }

    function playerPause() {
        if (player.timer) { clearInterval(player.timer); player.timer = null; }
        player.playing = false;
    }

    /** Leave player mode (no re-render): used when search / sort / filter / modal change. */
    function playerOff() {
        playerPause();
        player.active = false;
        player.idx = 0;
    }

    function playerShow(idx) {
        player.active = true;
        player.idx = Math.max(0, Math.min(player.total - 1, idx));
        renderModal();
        const body = document.querySelector('#lossxModal .lossx-body');
        if (body) body.scrollTop = 0;
    }

    function playerStep(d) {
        if (!player.total) return;
        const wasPlaying = player.playing;
        playerPause();
        playerShow(player.active ? player.idx + d : 0);
        if (wasPlaying) playerPlay(true);
    }

    function playerPlay(keepIdx) {
        if (!player.total) return;
        if (!keepIdx && (!player.active || player.idx >= player.total - 1)) playerShow(0);
        player.playing = true;
        player.timer = setInterval(function () {
            if (player.idx >= player.total - 1) { playerPause(); playerSync(); return; }
            playerShow(player.idx + 1);
        }, PLAY_MS);
        playerSync();
    }

    document.getElementById('lossxPlay')?.addEventListener('click', function () {
        if (player.playing) { playerPause(); playerSync(); } else playerPlay(false);
    });
    document.getElementById('lossxPrev')?.addEventListener('click', function () { playerStep(-1); });
    document.getElementById('lossxNext')?.addEventListener('click', function () { playerStep(1); });
    document.getElementById('lossxPexit')?.addEventListener('click', function () { playerOff(); renderModal(); });
    document.getElementById('lossxModal')?.addEventListener('hidden.bs.modal', function () { playerOff(); });

    function openModal(key) {
        if (!data) { load(false); return; }
        current = key;
        actionFilter = 'open';
        playerOff();
        query = '';
        shownFamilies = FAMILY_PAGE;
        document.getElementById('lossxSearch').value = '';
        renderModal();
        loadProducts();
        loadActions();
        const el = document.getElementById('lossxModal');
        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(el).show();
        } else if (window.jQuery) {
            window.jQuery(el).modal('show');
        }
    }

    // ==================== SPRICE $ ✓ = Push All (same as /price-increase) ====================
    // The push engine IS the /price-increase page: it is loaded once in a hidden frame and does exactly what its own
    // SPRICE $ ✓ does — fills every channel by its column rule, saves each channel's SPRICE and the SPRICE $ box value,
    // and pushes in a tracked server background job (failed pushes are retried hourly + e-mailed).
    let bridgeFrame = null;
    let bridgeReady = false;
    let bridgeWaiters = [];

    function ensureBridge() {
        return new Promise(function (resolve, reject) {
            if (bridgeReady) { resolve(); return; }
            bridgeWaiters.push({ resolve: resolve, reject: reject });
            if (bridgeFrame) return;
            bridgeFrame = document.createElement('iframe');
            bridgeFrame.src = PI_URL;
            bridgeFrame.setAttribute('aria-hidden', 'true');
            bridgeFrame.tabIndex = -1;
            bridgeFrame.style.cssText = 'position:fixed;left:-10000px;top:0;width:1280px;height:800px;border:0;opacity:0;pointer-events:none;';
            document.body.appendChild(bridgeFrame);
            setTimeout(function () {
                if (bridgeReady) return;
                const w = bridgeWaiters; bridgeWaiters = [];
                w.forEach(function (x) { x.reject(new Error('The push engine (/price-increase) did not load — try again')); });
                if (bridgeFrame) { bridgeFrame.remove(); bridgeFrame = null; }
            }, 120000);
        });
    }

    /** After the job started: follow the server's tracked pushes of this SKU and report the outcome. */
    function followPush(sku, startedAt) {
        const url = PUSH_STATUS_URL + '?sku=' + encodeURIComponent(sku);
        let tries = 0;
        (function poll() {
            fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    const rows = Object.values((res && res.rows) || {}).filter(function (r) {
                        return r && r.at && (new Date(r.at).getTime() >= startedAt - 120000);
                    });
                    const waiting = rows.filter(function (r) { return r.status === 'pending' && /^Queued/i.test(String(r.error || '')); });
                    if (waiting.length && ++tries < 400) { setTimeout(poll, 3000); return; }
                    const pushed = rows.filter(function (r) { return r.status === 'pushed'; }).length;
                    const failed = rows.length - pushed - waiting.length;
                    toast(sku + ': pushed ' + pushed + ' channel(s)' + (failed > 0 ? (', ' + failed + ' failed (see Push Pending)') : ''), failed > 0);
                })
                .catch(function () { if (++tries < 400) setTimeout(poll, 5000); });
        })();
    }

    window.addEventListener('message', function (ev) {
        if (ev.origin !== window.location.origin) return;
        const m = ev.data;
        if (!m) return;
        if (m.type === 'pi-bridge-ready') {
            bridgeReady = true;
            const w = bridgeWaiters; bridgeWaiters = [];
            w.forEach(function (x) { x.resolve(); });
            return;
        }
        if (m.type !== 'pi-box-push-result') return;
        const key = skuKey(m.sku);
        const job = pushing[key];
        if (!job) return;
        delete pushing[key];
        if (!m.ok) {
            refreshSp(key);
            toast(m.message || ('Could not push ' + m.sku), true);
            return;
        }
        // The engine saved the SPRICE $ box value too (shared with /price-increase)
        delete typed[key];
        const pi = piRows.get(key);
        if (pi) pi.box_sprice = job.price;
        refreshSp(key);
        // Siblings rule on: they were saved + pushed with this price (+ the addition) — show it on their rows too
        (job.sibs || []).forEach(function (s) {
            const sk = skuKey(s.sku);
            s.box_sprice = Math.max(0.01, +(job.price + job.add).toFixed(2));
            delete typed[sk];
            refreshSp(sk);
        });
        toast(m.sku + ': SPRICE $' + job.price.toFixed(2) + ' saved, pushing to all channels' + ((job.sibs || []).length ? ' + ' + job.sibs.length + ' sibling SKU(s)' : '') + '…');
        followPush(m.sku, job.startedAt);
    });

    /** ✓ / Enter: Push All for this SKU. */
    function pushSku(box) {
        if (!box) return;
        const key = box.dataset.key;
        const pi = piRows.get(key);
        const input = box.querySelector('.lossx-sp-in');
        if (!pi || !input) return;
        const price = parseFloat(String(input.value === null ? '' : input.value).replace(/[$,\s]/g, '').replace(',', '.'));
        if (!isFinite(price) || !(price > 0)) {
            toast('Enter SPRICE $ greater than 0', true);
            input.focus();
            return;
        }
        if (pushing[key]) { toast('A push for ' + pi.sku + ' is still being prepared', true); return; }
        const value = Math.max(0.01, +price.toFixed(2));
        const sibs = siblingsOf(pi);
        const sibOnNow = sibs.length > 0 && sibOn(key);
        pushing[key] = { startedAt: Date.now(), price: value, sibs: sibOnNow ? sibs : [], add: addNum(sibAdd(key)) };
        refreshSp(key);
        if (!bridgeReady) toast('Loading the push engine (/price-increase) — the first push can take a moment…');
        ensureBridge().then(function () {
            bridgeFrame.contentWindow.postMessage({
                type: 'pi-box-push', sku: pi.sku, price: value, siblings: sibOnNow, siblingAdd: sibAdd(key),
                data: { inventory: Number(pi.inv) || 0, overall_l30: Number(pi.l30) || 0, dil_percent: Number(pi.dil) || 0, image_path: pi.image || '' }
            }, window.location.origin);
        }).catch(function (err) {
            delete pushing[key];
            refreshSp(key);
            toast((err && err.message) || 'Could not start the push', true);
        });
    }

    // ---------- History button beside "Est." on every period card: who marked what Fixed / Ignored ----------
    let histKey = 'l30';
    let histRows = null;

    function histRender() {
        const body = document.getElementById('lossxHistBody');
        if (histRows === null) { body.innerHTML = '<div class="text-muted"><i class="fas fa-spinner fa-spin"></i> Loading…</div>'; return; }
        let rows = histRows;
        if (!document.getElementById('lossxHistAll').checked) {
            const keys = new Set(periodGroups(histKey).map(function (g) { return skuKey(g.sku); }));
            rows = rows.filter(function (r) { return keys.has(skuKey(r.sku)); });
        }
        if (!rows.length) { body.innerHTML = '<div class="text-muted">No Fixed / Ignore actions recorded yet.</div>'; return; }
        const label = { fixed: 'Fixed', ignored: 'Ignored', open: 'Cleared' };
        body.innerHTML = '<table class="table table-sm align-middle mb-0"><thead><tr><th>When</th><th>User</th><th>SKU</th><th>Action</th><th>Reason</th></tr></thead><tbody>' +
            rows.map(function (r) {
                return '<tr><td class="text-nowrap">' + esc(String(r.at || '').slice(0, 16)) + '</td><td>' + esc(r.user || '—') + '</td><td>' + esc(r.sku || '') +
                    '</td><td><span class="badge ' + (r.action === 'fixed' ? 'bg-success' : (r.action === 'ignored' ? 'bg-secondary' : 'bg-light text-dark')) + '">' +
                    esc(label[r.action] || r.action) + '</span></td><td>' + esc(r.reason || '—') + '</td></tr>';
            }).join('') + '</tbody></table>';
    }

    function openHistory(key) {
        histKey = key;
        histRows = null;
        const name = { l30: 'L30', l7: 'L7', yesterday: 'Yesterday', today: 'Today' }[key] || key;
        document.getElementById('lossxHistTitle').textContent = 'Action history — ' + name;
        histRender();
        const el = document.getElementById('lossxHistModal');
        if (window.bootstrap && bootstrap.Modal) bootstrap.Modal.getOrCreateInstance(el).show();
        else if (window.jQuery) window.jQuery(el).modal('show');
        const go = function () {
            fetch(ACTION_HISTORY_URL, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (j) { histRows = (j && j.history) || []; histRender(); })
                .catch(function () { document.getElementById('lossxHistBody').innerHTML = '<div class="text-danger">Could not load the history.</div>'; });
        };
        // the period data (to know which SKUs belong to the period) may still be loading
        if (data) go(); else setTimeout(go, 600);
    }

    document.getElementById('lossxHistAll')?.addEventListener('change', histRender);

    document.getElementById('lossx-strip')?.addEventListener('click', function (e) {
        const hb = e.target.closest('.lossx-hbtn');
        if (hb) { e.stopPropagation(); openHistory(hb.dataset.hist); return; }
        const card = e.target.closest('.lossx-card');
        if (card) openModal(card.dataset.p);
    });
    document.getElementById('lossx-strip')?.addEventListener('keydown', function (e) {
        const hb = e.target.closest ? e.target.closest('.lossx-hbtn') : null;
        if (hb && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); e.stopPropagation(); openHistory(hb.dataset.hist); }
    });
    document.getElementById('lossxSearch')?.addEventListener('input', function (e) {
        query = e.target.value || '';
        playerOff();
        shownFamilies = FAMILY_PAGE;
        renderModal();
    });
    document.querySelectorAll('.lossx-sort').forEach(function (btn) {
        if (!btn.dataset.sort) return;
        btn.addEventListener('click', function () {
            sortBy = btn.dataset.sort;
            playerOff();
            shownFamilies = FAMILY_PAGE;
            document.querySelectorAll('.lossx-sort[data-sort]').forEach(function (b) { b.classList.toggle('is-on', b === btn); });
            renderModal();
        });
    });
    document.getElementById('lossxStatus')?.addEventListener('click', function (e) {
        if (e.target.closest('[data-retry]')) loadProducts();
    });

    const listEl = document.getElementById('lossxList');
    document.getElementById('lossxFilter')?.addEventListener('change', function (e) { setActionFilter(e.target.value); });
    document.getElementById('lossxBadges')?.addEventListener('click', function (e) {
        const b = e.target.closest('.lossx-abadge');
        if (b) setActionFilter(actionFilter === b.dataset.af ? 'all' : b.dataset.af);
    });
    listEl?.addEventListener('click', function (e) {
        const go = e.target.closest('.lossx-sp-go');
        if (go) { e.preventDefault(); pushSku(go.closest('.lossx-sp')); return; }
        if (e.target.closest('#lossxMore')) { shownFamilies += FAMILY_PAGE; renderModal(); return; }
        const hb = e.target.closest('.lossx-hist-btn');
        if (hb) {
            const it = hb.closest('.lossx-item');
            if (it && it.classList.toggle('is-hist')) loadHist(it);
            return;
        }
        if (e.target.closest('.lossx-sp, .lossx-act, a, input, button, select')) return;
        const row = e.target.closest('.lossx-row');
        const item = row && row.parentElement;
        if (!item || !item.querySelector('.lossx-orders')) return;
        const open = item.classList.toggle('is-open');
        const k = item.dataset.key;
        if (open) openRows.add(k); else openRows.delete(k);
    });
    // Using a control of the shown product pauses the auto-advance
    listEl?.addEventListener('focusin', function (e) {
        if (player.playing && e.target.closest && e.target.closest('select, input, button')) { playerPause(); playerSync(); }
    });
    listEl?.addEventListener('change', function (e) {
        const sb = e.target.closest ? e.target.closest('.lossx-sibrow') : null;
        if (sb) {
            const key = sb.closest('.lossx-sp').dataset.key;
            const cb = sb.querySelector('.lossx-sib-cb'), add = sb.querySelector('.lossx-sib-add');
            sibSave(key, cb.checked, add.value);
            add.disabled = !cb.checked;
            return;
        }
        const cell = e.target.closest ? e.target.closest('.lossx-act-cell') : null;
        if (!cell) return;
        const key = cell.dataset.key, sku = cell.dataset.sku, v = e.target.value;
        if (e.target.classList.contains('lossx-act-sel')) {
            if (v === 'ignored') {
                const st = actionMap[key];
                if (!(st && st.action === 'ignored')) needReason.add(key);
                refreshAct(key);
            } else if (v === 'fixed') {
                needReason.delete(key);
                saveAction(key, sku, 'fixed', '');
            } else {
                needReason.delete(key);
                if (actionMap[key]) saveAction(key, sku, 'open', ''); else refreshAct(key);
            }
            return;
        }
        if (!e.target.classList.contains('lossx-reason-sel')) return;
        if (v === '__new') {
            const name = (window.prompt('New reason for ignoring this product:') || '').trim();
            if (!name) { refreshAct(key); return; }
            postJson(REASONS_URL, { name: name }).then(function (j) {
                reasons = j.reasons || reasons;
                renderModal();   // every row's Reason dropdown now lists it
                saveAction(key, sku, 'ignored', j.reason);
            }).catch(function (err) { toast((err && err.message) || 'Could not add the reason', true); refreshAct(key); });
            return;
        }
        if (!v) { refreshAct(key); return; }
        saveAction(key, sku, 'ignored', v);
    });    listEl?.addEventListener('input', function (e) {
        const inp = e.target.closest('.lossx-sp-in');
        if (!inp) return;
        const box = inp.closest('.lossx-sp');
        const key = box.dataset.key;
        typed[key] = inp.value;
        const pi = piRows.get(key);
        if (!pi) return;
        const price = parseFloat(inp.value);
        box.querySelector('.lossx-chips').innerHTML = chipsHtml(price > 0 ? price : spriceSaved(pi), pi);
        const low = lowestLmp(pi);
        inp.classList.toggle('is-below', price > 0 && low > 0 && price < low - 0.004);
    });
    listEl?.addEventListener('keydown', function (e) {
        const inp = e.target.closest ? e.target.closest('.lossx-sp-in') : null;
        if (inp && e.key === 'Enter') { e.preventDefault(); pushSku(inp.closest('.lossx-sp')); }
    });
    // A product picture that does not load becomes the placeholder
    listEl?.addEventListener('error', function (e) {
        const img = e.target;
        if (img && img.tagName === 'IMG' && img.classList.contains('lossx-img')) {
            const ph = document.createElement('div');
            ph.className = 'lossx-img is-missing';
            ph.innerHTML = '<i class="fas fa-image"></i>';
            img.replaceWith(ph);
        }
    }, true);

    load(false);
})();
</script>
@endsection
