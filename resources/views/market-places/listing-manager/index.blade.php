@extends('layouts.vertical', ['title' => 'Listing Manager', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --lc-blue: #2563eb;
            --lc-blue-dark: #1d4ed8;
            --lc-bg: #f4f6fb;
            --lc-border: #e4e9f2;
            --lc-text: #0f172a;
            --lc-muted: #64748b;
            --lc-danger: #dc2626;
            --lc-warn: #d97706;
            --lc-card: #ffffff;
            --lc-soft: #f8fafc;
            --lc-ink: #111827;
        }
        .content-page:has(.lm-page) .content { padding-top: 18px !important; padding-bottom: 6px !important; }
        .content-page:has(.lm-page) .footer { padding-top: 8px; padding-bottom: 8px; }
        .lm-page {
            background:
                radial-gradient(900px 280px at 0% -8%, rgba(37,99,235,.10), transparent 55%),
                radial-gradient(700px 240px at 100% 0%, rgba(16,185,129,.08), transparent 50%),
                var(--lc-bg);
            margin: 0 -12px;
            padding: 10px 12px 8px;
            min-height: calc(100vh - 132px);
            color: var(--lc-text);
        }
        .lm-card {
            background: var(--lc-card); border: 1px solid rgba(15,23,42,.06);
            border-radius: 14px; box-shadow: 0 8px 24px rgba(15, 23, 42, .05);
            overflow: hidden;
        }
        .lm-header {
            display: grid; grid-template-columns: minmax(180px, 1fr) auto auto;
            gap: .75rem; margin: 0 0 .65rem; align-items: center;
            padding: 2px 2px 0;
        }
        @media (max-width: 900px) {
            .lm-header { grid-template-columns: 1fr; }
        }
        .lm-header h1 { margin: 0; font-size: 1.2rem; font-weight: 750; letter-spacing: -.02em; color: var(--lc-ink); line-height: 1.2; }
        .lm-actions { display: flex; gap: .4rem; align-items: center; flex-wrap: wrap; }
        .btn-lc { border-radius: 9px; font-weight: 650; font-size: .8rem; padding: .36rem .75rem; border: 1px solid transparent; }
        .btn-lc-primary { background: var(--lc-blue); border-color: var(--lc-blue-dark); color: #fff !important; }
        .btn-lc-primary:hover { background: var(--lc-blue-dark); color: #fff !important; }
        .btn-lc-primary:disabled, .btn-lc:disabled { opacity: .55; cursor: not-allowed; }
        .btn-lc-ghost { background: #fff; border-color: var(--lc-border); color: var(--lc-text); }
        .btn-lc-danger { background: #fff; border-color: #fecaca; color: #b91c1c; }
        .btn-lc-danger:hover { background: #fef2f2; }
        .lm-page-tabs { display: inline-flex; gap: .15rem; margin: 0; padding: .15rem; background: #fff; border: 1px solid var(--lc-border); border-radius: 10px; }
        .lm-page-tab {
            border: none; background: transparent; color: #475569;
            border-radius: 8px; padding: .32rem .8rem; font-size: .78rem; font-weight: 700; cursor: pointer;
        }
        .lm-page-tab.active { background: #0f172a; color: #fff; }
        .lm-panel { display: none; }
        .lm-panel.active { display: block; }
        .lm-filters {
            display: grid; grid-template-columns: 1.2fr 1fr .9fr auto auto auto auto; gap: .4rem;
            padding: .55rem .75rem; border-bottom: 1px solid var(--lc-border); background: #fbfcfe;
            align-items: center;
        }
        @media (max-width: 1100px) { .lm-filters { grid-template-columns: 1fr 1fr; } }
        .lm-filters .form-control, .lm-filters .form-select { min-height: 32px; font-size: .8rem; border-radius: 8px; border-color: var(--lc-border); padding: .28rem .6rem; }
        .lm-stats { display: flex; gap: .4rem; align-items: center; flex-wrap: wrap; }
        .lm-stat {
            background: #f1f5f9; color: #334155; border-radius: 999px; padding: .2rem .55rem;
            font-size: .72rem; font-weight: 700; white-space: nowrap;
        }
        .lm-stat strong { color: #0f172a; }
        .lm-stock-tabs { display: none; }
        .lm-channel-tabs { display: flex; gap: .45rem; padding: .45rem .75rem 0; flex-wrap: wrap; }
        .lm-stock-tab, .lm-channel-tab {
            border: none; background: transparent; color: #6b7280; border-bottom: 2px solid transparent;
            padding: .45rem .2rem; font-size: .92rem; font-weight: 700; cursor: pointer; margin-right: 1rem;
        }
        .lm-stock-tab.active, .lm-channel-tab.active { color: var(--lc-blue); border-bottom-color: var(--lc-blue); }
        .lm-channel-tab .badge { background: #e5e7eb; color: #374151; margin-left: .25rem; }
        .lm-channel-tab.active .badge { background: #dbeafe; color: #1d4ed8; }
        .lm-toolbar { display: flex; justify-content: space-between; gap: .5rem; flex-wrap: wrap; padding: .45rem .75rem; align-items: center; }
        .lm-info-box {
            background: #eff6ff; border: 1px solid #bfdbfe; color: #1e3a8a;
            border-radius: 8px; padding: .7rem .9rem; font-size: .8rem; margin: 0 1rem .75rem;
        }
        .lm-import-banner {
            background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af;
            border-radius: 8px; padding: .4rem .7rem; font-size: .75rem; margin-bottom: .5rem;
        }
        .lm-thumb { width: 32px; height: 32px; object-fit: contain; border: 1px solid var(--lc-border); border-radius: 7px; background: #fff; }
        .lm-thumb-empty {
            width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center;
            border: 1px solid var(--lc-border); border-radius: 7px; color: #94a3b8; background: #f8fafc; font-size: .7rem;
        }
        .lm-qty-pill {
            display: inline-flex; align-items: center; justify-content: center; min-width: 42px;
            border-radius: 999px; padding: .18rem .55rem; font-size: .75rem; font-weight: 750;
            background: #ecfdf5; color: #047857;
        }
        .lm-qty-pill.is-zero { background: #fff7ed; color: #c2410c; }
        .lm-name-link {
            color: var(--lc-blue); font-weight: 600; text-decoration: none; cursor: pointer;
            display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%;
        }
        .lm-hero-wrap { position: relative; display: inline-flex; cursor: pointer; }
        .lm-name-link:hover { text-decoration: underline; }
        .lm-sku-magnify {
            color: #2563eb; font-size: .95rem; line-height: 1; padding: 0;
            display: inline-flex; align-items: center; justify-content: center;
        }
        .lm-sku-magnify:hover { color: #1d4ed8; }
        .lm-status-pill {
            display: inline-block; border-radius: 999px; padding: .18rem .55rem;
            font-size: .72rem; font-weight: 700; white-space: nowrap;
        }
        .lm-status-missing { background: #ffedd5; color: #c2410c; }
        .lm-status-ready { background: #dbeafe; color: #1d4ed8; }
        .lm-status-active { background: #dcfce7; color: #166534; }
        .lm-status-failed { background: #fee2e2; color: #991b1b; }
        .tabulator.lm-tabulator { border: none; }
        .tabulator.lm-tabulator .tabulator-header { background: #f8fafc; border-color: var(--lc-border); }
        .tabulator.lm-tabulator .tabulator-header .tabulator-col-content {
            padding: 7px 10px; font-size: .68rem; font-weight: 750; text-transform: uppercase; letter-spacing: .04em; color: #64748b;
        }
        .tabulator.lm-tabulator .tabulator-row .tabulator-cell {
            padding: 6px 10px; font-size: .8rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .tabulator.lm-tabulator .tabulator-cell[tabulator-field="price"] {
            font-variant-numeric: tabular-nums;
            font-weight: 500;
            color: #334155;
            text-align: right !important;
        }
        .tabulator.lm-tabulator .tabulator-row { min-height: 38px; }
        .tabulator.lm-tabulator .tabulator-row:hover { background: #f8fafc !important; }
        .lm-toast-wrap { position: fixed; top: 1rem; right: 1rem; z-index: 3000; }
        .lm-channel-list { max-height: 320px; overflow: auto; }
        .lm-channel-row {
            display: flex; align-items: center; gap: .75rem; padding: .75rem .9rem;
            border: 1px solid var(--lc-border); border-radius: 12px; margin-bottom: .5rem; cursor: pointer;
            background: #fff; transition: border-color .15s ease, background .15s ease, box-shadow .15s ease;
        }
        .lm-channel-row.is-checked { border-color: #9bb8ee; background: #eef4ff; box-shadow: 0 0 0 3px rgba(61,111,216,.08); }
        .lm-channel-row img { width: 28px; height: 28px; object-fit: contain; border-radius: 4px; border: 1px solid var(--lc-border); }

        /* Full listing editor (LitCommerce-style) */
        .lc-editor-modal .modal-dialog { max-width: min(1180px, 96vw); margin: .75rem auto; }
        .lc-editor-modal .modal-content { border-radius: 16px; border: 1px solid var(--lc-border); height: calc(100vh - 1.5rem); display: flex; flex-direction: column; box-shadow: 0 18px 50px rgba(28,42,58,.12); }
        .lc-editor-modal .modal-header { border-bottom: 1px solid var(--lc-border); padding: .85rem 1rem; flex-shrink: 0; }
        .lc-editor-modal .modal-title { font-size: 1rem; font-weight: 700; max-width: 70%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .lc-editor-modal .modal-body { overflow: auto; flex: 1; padding: 0; }
        .lc-editor-modal .modal-footer {
            border-top: 1px solid var(--lc-border); padding: .75rem 1rem; display: flex; justify-content: space-between; gap: .5rem; flex-shrink: 0;
        }
        .lc-banner { padding: .55rem .85rem; font-size: .8rem; border-bottom: 1px solid transparent; }
        .lc-banner-danger { background: #fef2f2; color: #991b1b; border-color: #fecaca; }
        .lc-banner-info { background: #eff6ff; color: #1e40af; border-color: #bfdbfe; }
        .lc-banner-warn { background: #fffbeb; color: #92400e; border-color: #fde68a; }
        .lc-tabs {
            display: flex; gap: 0; border-bottom: 1px solid var(--lc-border); padding: 0 .5rem;
            overflow-x: auto; background: #fff; position: sticky; top: 0; z-index: 2;
        }
        .lc-tab {
            border: none; background: transparent; color: #4b5563; font-weight: 600; font-size: .84rem;
            padding: .85rem .9rem; border-bottom: 2px solid transparent; white-space: nowrap; position: relative;
        }
        .lc-tab.active { color: var(--lc-blue); border-bottom-color: var(--lc-blue); }
        .lc-tab .lc-err {
            position: absolute; top: 8px; right: 2px; width: 16px; height: 16px; border-radius: 50%;
            background: #ef4444; color: #fff; font-size: .65rem; display: inline-flex; align-items: center; justify-content: center;
        }
        .lc-tab[hidden] { display: none !important; }
        .lc-pane { display: none; padding: 1rem 1.25rem 1.5rem; }
        .lc-pane.active { display: block; }
        #lc-editor-channel-badge {
            display: none; font-size: .72rem; font-weight: 700; border-radius: 999px;
            padding: .12rem .5rem; background: #e0e7ff; color: #3730a3; margin-left: .5rem; vertical-align: middle;
        }
        #lc-editor-loading {
            display: none; padding: 2.5rem 1.25rem; text-align: center; color: #64748b; font-size: .9rem;
        }
        .lc-section-title { font-weight: 700; margin-bottom: .65rem; }
        .lc-help { font-size: .78rem; color: var(--lc-muted); margin-bottom: .85rem; }
        .lc-req { color: var(--lc-danger); }
        .lc-char-warn { color: var(--lc-danger); font-size: .78rem; }
        .lc-char-ok { color: var(--lc-muted); font-size: .78rem; }
        .lc-image-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: .65rem; }
        .lc-image-card {
            border: 1px solid var(--lc-border); border-radius: 8px; background: #fafafa; aspect-ratio: 1;
            display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden;
            cursor: grab;
        }
        .lc-image-card.sortable-ghost { opacity: .45; }
        .lc-image-card img { width: 100%; height: 100%; object-fit: contain; pointer-events: none; background: #fff; }
        .lc-image-card.is-broken { border-color: #fca5a5; background: #fef2f2; }
        .lc-image-card .lc-img-fail {
            position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
            font-size: .68rem; color: #b91c1c; padding: .35rem; text-align: center;
        }
        .lc-image-card .primary-tag {
            position: absolute; left: 0; bottom: 0; background: rgba(31,41,55,.85); color: #fff;
            font-size: .68rem; font-weight: 700; padding: .15rem .4rem;
        }
        .lc-image-card .lc-img-del {
            position: absolute; top: 4px; right: 4px; border: none; background: rgba(220,38,38,.9); color: #fff;
            width: 22px; height: 22px; border-radius: 50%; font-size: .7rem; line-height: 1; display: none;
        }
        .lc-image-card:hover .lc-img-del { display: inline-flex; align-items: center; justify-content: center; }
        .lc-suggested {
            display: inline-block; background: #dcfce7; color: #166534; font-size: .68rem; font-weight: 700;
            border-radius: 999px; padding: .1rem .45rem; margin-left: .35rem;
        }
        .lc-field-warn { color: var(--lc-danger); font-size: .75rem; margin-top: .2rem; }
        .lc-specific-row { display: grid; grid-template-columns: 160px 1fr; gap: .5rem; margin-bottom: .5rem; align-items: center; }
        @media (max-width: 700px) { .lc-specific-row { grid-template-columns: 1fr; } }
        .lc-cat-box { border: 1px solid var(--lc-border); border-radius: 8px; background: #fff; margin-top: .5rem; }
        .lc-cat-search { display: flex; align-items: center; gap: .5rem; padding: .55rem .75rem; border-bottom: 1px solid var(--lc-border); }
        .lc-cat-search i { color: #9ca3af; }
        .lc-cat-results { max-height: 240px; overflow: auto; }
        .lc-cat-item {
            display: block; width: 100%; text-align: left; border: none; background: #fff; padding: .55rem .75rem;
            font-size: .82rem; border-bottom: 1px solid #f3f4f6; color: #1f2937;
        }
        .lc-cat-item:hover { background: #eff6ff; color: var(--lc-blue); }
        .lc-primary-path { color: var(--lc-blue); font-weight: 600; font-size: .9rem; }
        .lc-policy-row { display: grid; grid-template-columns: 160px 1fr auto; gap: .65rem; align-items: center; margin-bottom: .75rem; }
        .lc-location-row { display: grid; grid-template-columns: 160px 1fr 1fr 1fr; gap: .65rem; align-items: center; }
        @media (max-width: 900px) {
            .lc-policy-row, .lc-location-row { grid-template-columns: 1fr; }
        }
        .lc-italic-note { font-style: italic; color: var(--lc-muted); font-size: .8rem; margin-bottom: .85rem; }
        .lc-desc-toolbar { display: flex; align-items: center; justify-content: space-between; gap: .5rem; flex-wrap: wrap; margin-bottom: .5rem; }
        .lc-desc-modes { display: inline-flex; border: 1px solid var(--lc-border); border-radius: 6px; overflow: hidden; }
        .lc-desc-modes button {
            border: none; background: #fff; color: #4b5563; padding: .35rem .65rem; font-size: .8rem; cursor: pointer;
        }
        .lc-desc-modes button.active { background: #eff6ff; color: var(--lc-blue); }
        .lc-desc-wrap { border: 1px solid var(--lc-border); border-radius: 8px; overflow: hidden; background: #fff; position: relative; }
        .lc-desc-wrap.is-fullscreen {
            position: fixed; inset: 12px; z-index: 2100; border-radius: 10px; box-shadow: 0 20px 50px rgba(0,0,0,.25);
            display: flex; flex-direction: column; background: #fff;
        }
        .lc-desc-wrap.is-fullscreen #lc-description,
        .lc-desc-wrap.is-fullscreen #lc-description-preview,
        .lc-desc-wrap.is-fullscreen #lc-description-rich { flex: 1; min-height: 0; height: auto !important; }
        .lc-desc-code-row { display: flex; min-height: 280px; }
        .lc-desc-gutter {
            width: 42px; background: #f8fafc; border-right: 1px solid var(--lc-border);
            color: #9ca3af; font-size: .72rem; line-height: 1.45; padding: .65rem .25rem; text-align: right;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; user-select: none; overflow: hidden;
        }
        #lc-description {
            border: none; border-radius: 0; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: .8rem; line-height: 1.45; resize: vertical; min-height: 280px;
        }
        #lc-description-preview {
            display: none; min-height: 280px; max-height: 520px; overflow: auto; padding: 1rem 1.1rem; background: #fff;
        }
        #lc-description-rich {
            display: none; min-height: 280px; max-height: 520px; overflow: auto; padding: .85rem 1rem;
            outline: none;
        }
        #lc-description-rich:focus { box-shadow: inset 0 0 0 2px #bfdbfe; }
        .lc-desc-side { display: flex; flex-direction: column; gap: .45rem; align-items: flex-start; margin-top: .65rem; }

        /* Product detail modal (LitCommerce All Products) */
        .lm-product-modal .modal-dialog { max-width: min(1100px, 96vw); margin: .6rem auto; }
        .lm-product-modal .modal-content { height: calc(100vh - 1.2rem); display: flex; flex-direction: column; border-radius: 16px; box-shadow: 0 18px 50px rgba(28,42,58,.12); }
        .lm-product-modal .modal-header { flex-shrink: 0; border-bottom: 1px solid var(--lc-border); gap: .75rem; }
        .lm-product-modal .modal-title { font-size: 1rem; font-weight: 700; max-width: 62%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .lm-product-modal .modal-body { flex: 1; overflow: auto; padding: 0; position: relative; }
        .lm-prod-tabs { display: flex; gap: 0; border-bottom: 1px solid var(--lc-border); padding: 0 .75rem; overflow-x: auto; position: sticky; top: 0; background: #fff; z-index: 2; }
        .lm-prod-tab {
            border: none; background: transparent; padding: .85rem .85rem; font-weight: 600; font-size: .84rem; color: #4b5563;
            border-bottom: 2px solid transparent; white-space: nowrap;
        }
        .lm-prod-tab.active { color: var(--lc-blue); border-bottom-color: var(--lc-blue); }
        .lm-prod-pane { display: none; padding: 1rem 1.25rem 1.5rem; }
        .lm-prod-pane.active { display: block; }
        .lm-prod-grid { display: grid; grid-template-columns: 180px 1fr; gap: .55rem 1rem; font-size: .875rem; }
        .lm-prod-grid .k { color: #6b7280; font-weight: 600; }
        .lm-prod-grid .v { color: #111827; word-break: break-word; }
        .lm-prod-edit-grid { display: grid; grid-template-columns: 180px 1fr; gap: .55rem 1rem; align-items: start; font-size: .875rem; }
        .lm-prod-edit-grid label { color: #6b7280; font-weight: 600; padding-top: .4rem; }
        .lm-prod-edit-grid .form-control { font-size: .86rem; }
        .lm-push-results { max-height: 260px; overflow: auto; font-size: .82rem; }
        .lm-push-results .ok { color: #166534; }
        .lm-push-results .fail { color: #b91c1c; }
        .lm-push-panel {
            border: 1px solid #bfdbfe; background: #f8fbff; border-radius: 12px;
            padding: 1rem 1.1rem; margin-bottom: 1rem;
        }
        .lm-push-badge {
            margin-left: auto; font-size: .7rem; font-weight: 700; border-radius: 999px;
            padding: .12rem .5rem; background: #f3f4f6; color: #4b5563; white-space: nowrap;
        }
        .lm-push-badge.listed { background: #dcfce7; color: #166534; }
        .lm-push-badge.draft { background: #dbeafe; color: #1d4ed8; }
        .lm-push-status-row {
            display: flex; align-items: flex-start; gap: .55rem; padding: .45rem .2rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .lm-push-status-row:last-child { border-bottom: 0; }
        .lm-push-status-row .ico { width: 1.1rem; text-align: center; margin-top: .1rem; }
        .lm-push-overlay {
            position: absolute; inset: 0; background: rgba(255,255,255,.72);
            display: flex; align-items: center; justify-content: center; z-index: 8;
        }
        .lm-push-overlay-card {
            background: #fff; border: 1px solid var(--lc-border); border-radius: 12px;
            box-shadow: 0 12px 30px rgba(28,42,58,.12); padding: 1.15rem 1.4rem;
            text-align: center; min-width: 260px;
        }
        .lm-push-overlay-card .spin { font-size: 1.4rem; color: var(--lc-blue); margin-bottom: .45rem; }
        .lm-product-modal .modal-content { position: relative; }
        .lm-status-active { display: inline-block; background: #dcfce7; color: #166534; font-size: .72rem; font-weight: 700; padding: .15rem .5rem; border-radius: 999px; }
        .lm-prod-desc { border: 1px solid var(--lc-border); border-radius: 8px; padding: 1rem; max-height: 420px; overflow: auto; background: #fff; }
        .lm-prod-desc img { max-width: 100%; height: auto; }
        .lm-masters-links { display: flex; flex-wrap: wrap; gap: .65rem 1rem; font-size: .82rem; }
        .lm-masters-links a { font-weight: 600; }
        .lm-masters-block { border: 1px solid var(--lc-border); border-radius: 10px; overflow: hidden; margin-bottom: 1rem; background: #fff; }
        .lm-masters-head {
            background: #f8fafc; color: #1e3a5f; font-weight: 700; font-size: .82rem;
            padding: .55rem .85rem; border-bottom: 1px solid var(--lc-border);
            display: flex; align-items: center; justify-content: space-between; gap: .5rem;
        }
        .lm-masters-body { padding: .75rem .85rem; }
        .lm-masters-title-row { display: grid; grid-template-columns: 110px 1fr auto auto; gap: .5rem .7rem; padding: .45rem 0; border-bottom: 1px solid #f3f4f6; align-items: start; }
        .lm-masters-title-row:last-child { border-bottom: 0; }
        .lm-masters-title-row .k { color: #6b7280; font-weight: 700; font-size: .8rem; }
        .lm-masters-title-row .v { color: #111827; font-size: .86rem; word-break: break-word; }
        .lm-bullet-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .lm-bullet-table th, .lm-bullet-table td {
            padding: .5rem .65rem; vertical-align: top; border-bottom: 1px solid #eef2f7; font-size: .86rem;
        }
        .lm-bullet-table thead th {
            color: #64748b; font-size: .72rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: .03em; background: #f8fafc;
        }
        .lm-bullet-table .num, .lm-bullet-table td.n {
            width: 48px; text-align: center; font-weight: 700; color: #1e3a5f;
            border-right: 1px solid #e5e7eb; background: #f8fafc;
        }
        .lm-bullet-table td.v { color: #111827; word-break: break-word; }
        .lm-bullet-table td.act, .lm-bullet-table th.act { width: 40px; text-align: center; }
        .lm-bullet-table td.cnt, .lm-bullet-table th.cnt { width: 52px; text-align: right; }
        .lm-bullet-table tbody tr:last-child td { border-bottom: 0; }
        .lm-masters-body.lm-masters-body--table { padding: 0; }
        .lm-char-count {
            font-weight: 700; font-size: .78rem; min-width: 2.4rem; text-align: right;
            white-space: nowrap; padding-top: .35rem; line-height: 1;
        }
        .lm-char-count.is-short { color: #dc2626; }
        .lm-char-count.is-ok { color: #16a34a; }
        .lm-char-count.is-over { color: #7c3aed; }
        .lm-masters-head-actions { display: inline-flex; align-items: center; gap: .55rem; }
        .lm-row-edit-btn {
            border: 1px solid #dbe3ef; background: #fff; color: #1d4ed8; border-radius: 6px;
            width: 28px; height: 28px; padding: 0; display: inline-flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .lm-row-edit-btn:hover { background: #eef4ff; }
        .lm-row-edit-actions { display: flex; gap: .35rem; flex-shrink: 0; }
        .lm-masters-title-row textarea,
        .lm-masters-bullet-row textarea,
        .lm-desc-edit-wrap textarea {
            width: 100%; font-size: .84rem; min-height: 64px;
        }
        .lm-desc-edit-wrap { padding: .75rem .85rem; }
        .lm-family-sync-checks { display: flex; flex-wrap: wrap; gap: .55rem 1.15rem; align-items: center; }
        .lm-family-sync-checks .form-check { display: flex; align-items: center; gap: .4rem; margin: 0; min-height: 0; }
        .lm-family-sync-checks .form-check-label { font-size: .82rem; font-weight: 600; color: #374151; }
        .lm-prod-images { display: grid; grid-template-columns: 280px 1fr; gap: 1rem; }
        @media (max-width: 800px) { .lm-prod-images { grid-template-columns: 1fr; } }
        .lm-prod-main-img { border: 1px solid var(--lc-border); border-radius: 8px; background: #fafafa; min-height: 280px; display: flex; align-items: center; justify-content: center; }
        .lm-prod-main-img img { max-width: 100%; max-height: 360px; object-fit: contain; }
        .lm-prod-thumbs { display: grid; grid-template-columns: repeat(auto-fill, minmax(88px, 1fr)); gap: .5rem; align-content: start; }
        .lm-prod-thumbs img {
            width: 100%; aspect-ratio: 1; object-fit: contain; border: 1px solid var(--lc-border); border-radius: 6px; background: #fff; cursor: pointer;
        }
        .lm-prod-thumbs img.active { border-color: var(--lc-blue); box-shadow: 0 0 0 2px #bfdbfe; }
        .lm-listed-head { background: #dcfce7; color: #166534; font-weight: 700; padding: .55rem .75rem; border-radius: 6px 6px 0 0; }
        .lm-unlist-head { background: #f3f4f6; color: #374151; font-weight: 700; padding: .55rem .75rem; border-radius: 6px 6px 0 0; margin-top: 1rem; }
        .lm-list-table { width: 100%; border-collapse: collapse; font-size: .84rem; }
        .lm-list-table th, .lm-list-table td { padding: .55rem .65rem; border-bottom: 1px solid #f3f4f6; text-align: left; }
        .lm-meta-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
        .lm-meta-table th, .lm-meta-table td { padding: .45rem .55rem; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        .lm-meta-table th { width: 220px; color: #6b7280; font-weight: 600; }
        .lm-var-current { background: #eef5ff; }
        .lm-var-label { display: inline-block; background: #e8f1ff; color: #2b56b3; border-radius: 999px; padding: .1rem .5rem; font-size: .72rem; font-weight: 700; }
        .lm-family-bar { display: flex; justify-content: space-between; gap: .75rem; align-items: center; flex-wrap: wrap; margin-bottom: .85rem; }
    </style>
@endsection

@section('content')
<div class="lm-page">
    <div class="lm-toast-wrap" id="lm-toast-wrap"></div>

    <div class="lm-header">
        <div>
            <h1 id="lm-page-title">Listing Manager</h1>
        </div>
        <div class="lm-page-tabs">
            <button type="button" class="lm-page-tab active" data-panel="products">All Products</button>
            <button type="button" class="lm-page-tab" data-panel="drafts">Channel Listings</button>
        </div>
        <div class="lm-actions" id="lm-header-actions-products">
            <button type="button" class="btn-lc btn-lc-ghost" id="lm-manage-channels-btn"><i class="fas fa-store me-1"></i>Add Marketplaces</button>
            <button type="button" class="btn-lc btn-lc-primary" id="lm-import-amazon-btn"><i class="fas fa-cloud-download-alt me-1"></i>Import from Amz</button>
        </div>
        <div class="lm-actions d-none" id="lm-header-actions-drafts">
            <button type="button" class="btn-lc btn-lc-primary" id="lm-quick-list-btn"><i class="fas fa-bolt me-1"></i><span id="lm-quick-list-label">Quick/Auto List</span></button>
            <button type="button" class="btn-lc btn-lc-ghost" id="lm-import-channel-btn" disabled title="Coming soon">Import</button>
            <div class="dropdown">
                <button class="btn-lc btn-lc-ghost dropdown-toggle" data-bs-toggle="dropdown">More Actions</button>
                <ul class="dropdown-menu">
                    <li><button type="button" class="dropdown-item" id="lm-draft-refresh-btn">Check Live Status</button></li>
                    <li><a class="dropdown-item" href="#" id="lm-open-listing-page" target="_blank">Open channel listing page</a></li>
                </ul>
            </div>
            <button type="button" class="btn-lc btn-lc-ghost" id="lm-channel-settings-btn"><i class="fas fa-cog"></i></button>
        </div>
    </div>

    {{-- Amazon catalog --}}
    <div class="lm-panel active" id="lm-panel-products">
        <div class="lm-card">
            <div class="lm-filters">
                <input type="text" id="lm-q-name" class="form-control" placeholder="Search products">
                <input type="text" id="lm-q-sku" class="form-control" placeholder="Search SKUs">
                <select id="lm-product-type" class="form-select"><option value="all">Collection / Type</option></select>
                <button type="button" class="btn-lc btn-lc-primary" id="lm-search-btn">Search</button>
                <button type="button" class="btn-lc btn-lc-ghost" id="lm-clear-btn">Clear</button>
                <div class="lm-stats">
                    <span class="lm-stat">All listings <strong id="lm-all-count">0</strong></span>
                    <span class="lm-stat">Shopify INV</span>
                </div>
                <div class="dropdown">
                    <button class="btn-lc btn-lc-primary dropdown-toggle" data-bs-toggle="dropdown">List on Channel</button>
                    <ul class="dropdown-menu">
                        <li><button type="button" class="dropdown-item" id="lm-add-selected-drafts">Add selected to Channel Drafts</button></li>
                        <li><button type="button" class="dropdown-item" id="lm-add-all-drafts">Add all visible to Channel Drafts</button></li>
                    </ul>
                </div>
            </div>
            <div class="lm-toolbar">
                <div class="text-muted small"><span id="lm-selected-count">0</span> products selected</div>
            </div>
            <div class="lm-stock-tabs" hidden>
                <button type="button" class="lm-stock-tab" data-stock="all">All (<span id="lm-in-stock-count">0</span>)</button>
                <button type="button" class="lm-stock-tab" data-stock="all">All (<span id="lm-out-stock-count">0</span>)</button>
            </div>
            <div id="lm-products-table" class="lm-tabulator"></div>
        </div>
    </div>

    {{-- LitCommerce channel drafts / active --}}
    <div class="lm-panel" id="lm-panel-drafts">
        <div class="lm-import-banner" id="lm-channel-banner">
            These listings are in <strong>Draft</strong> and not yet published. Complete all required fields for products marked
            <span class="lm-status-pill lm-status-missing">Missing Info</span> before publishing them to your store.
        </div>
        <div class="lm-card">
            <div class="lm-filters">
                <input type="text" id="lm-draft-q" class="form-control" placeholder="Search Products">
                <input type="text" id="lm-draft-q-sku" class="form-control" placeholder="Search SKU">
                <select id="lm-draft-channel" class="form-select"><option value="0">All channels</option></select>
                <button type="button" class="btn-lc btn-lc-primary" id="lm-draft-search-btn">Search</button>
                <button type="button" class="btn-lc btn-lc-ghost" id="lm-draft-clear-btn">Clear</button>
                <select id="lm-draft-sort" class="form-select">
                    <option value="updated">Sort by: Updated</option>
                    <option value="name">Sort by: Name</option>
                    <option value="sku">Sort by: SKU</option>
                </select>
            </div>

            <div class="lm-channel-tabs">
                <button type="button" class="lm-channel-tab active" data-tab="drafts">Drafts <span class="badge" id="lm-meta-drafts">0</span></button>
                <button type="button" class="lm-channel-tab" data-tab="active">Active <span class="badge" id="lm-meta-active">0</span></button>
            </div>

            <div class="lm-toolbar">
                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <select class="form-select form-select-sm" style="width:auto" disabled>
                        <option>Apply Template/Rules</option>
                    </select>
                    <div class="dropdown">
                        <button class="btn-lc btn-lc-primary dropdown-toggle" data-bs-toggle="dropdown">Select Action</button>
                        <ul class="dropdown-menu">
                            <li><button type="button" class="dropdown-item" id="lm-action-publish-selected">Publish selected (Ready only)</button></li>
                            <li><button type="button" class="dropdown-item" id="lm-action-delete-selected">Delete selected</button></li>
                        </ul>
                    </div>
                    <button type="button" class="btn-lc btn-lc-ghost" id="lm-multi-edit-btn">Multi Edit Mode</button>
                </div>
                <div class="text-muted small"><span id="lm-draft-selected-count">0</span> selected</div>
            </div>

            <div class="lm-info-box" id="lm-drafts-help">
                These listings are in <strong>Draft</strong> and not yet published. Review and <strong>complete all required fields</strong>
                for products marked with <strong>Missing Info</strong> before publishing them to your store.
            </div>

            <div id="lm-drafts-table" class="lm-tabulator"></div>
        </div>
    </div>
</div>

{{-- LitCommerce listing editor --}}
<div class="modal fade lc-editor-modal" id="lmListingEditorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="btn-close me-2" data-bs-dismiss="modal" aria-label="Close"></button>
                <h5 class="modal-title flex-grow-1">
                    <span id="lc-editor-title">Listing</span>
                    <span id="lc-editor-channel-badge"></span>
                </h5>
                <button type="button" class="btn-lc btn-lc-primary btn-sm" id="lc-reload-amazon-btn">Reload from Main Store</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="lc-draft-id">
                <div id="lc-editor-loading"><i class="fas fa-spinner fa-spin me-2"></i>Loading product details…</div>
                <div id="lc-editor-body">
                <div id="lc-banners"></div>
                <div class="lc-tabs" id="lc-tabs">
                    <button type="button" class="lc-tab active" data-pane="identifiers">Product Identifiers</button>
                    <button type="button" class="lc-tab" data-pane="variations">Variations</button>
                    <button type="button" class="lc-tab" data-pane="title">Title &amp; Description</button>
                    <button type="button" class="lc-tab" data-pane="images">Images</button>
                    <button type="button" class="lc-tab" data-pane="pricing">Pricing</button>
                    <button type="button" class="lc-tab" data-pane="category">Category</button>
                    <button type="button" class="lc-tab" data-pane="policies">Business Policies</button>
                    <button type="button" class="lc-tab" data-pane="relist">Auto Relist</button>
                </div>

                <div class="lc-pane active" data-pane="identifiers">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                        <div class="lc-section-title mb-0">Product Identifiers</div>
                        <button type="button" class="btn-lc btn-lc-ghost btn-sm lc-load-master-btn" data-master-source="identifiers">
                            <i class="fas fa-database me-1"></i>Load from Product Master
                        </button>
                    </div>
                    <p class="lc-help" id="lc-identifier-help">Product identifiers from Product Master.</p>
                    <div class="row g-3">
                        <div class="col-md-6" data-id-field="sku"><label class="form-label">SKU</label><input id="lc-sku" class="form-control" readonly></div>
                        <div class="col-md-6" data-id-field="asin"><label class="form-label" id="lc-asin-label">ASIN / Source</label><input id="lc-asin" class="form-control" readonly></div>
                        <div class="col-md-6" data-id-field="brand"><label class="form-label">Brand</label><input id="lc-brand-id" class="form-control" placeholder="5 Core Inc"></div>
                        <div class="col-md-6" data-id-field="manufacturer"><label class="form-label">Manufacturer</label><input id="lc-manufacturer" class="form-control" placeholder="5 Core Inc"></div>
                        <div class="col-md-6" data-id-field="upc"><label class="form-label">UPC</label><input id="lc-upc" class="form-control" placeholder="Optional"></div>
                        <div class="col-md-6" data-id-field="ean"><label class="form-label">EAN</label><input id="lc-ean" class="form-control" placeholder="Optional"></div>
                        <div class="col-md-6" data-id-field="isbn"><label class="form-label">ISBN</label><input id="lc-isbn" class="form-control" placeholder="Optional"></div>
                        <div class="col-md-6" data-id-field="epid"><label class="form-label">ePID</label><input id="lc-epid" class="form-control" placeholder="Optional"></div>
                    </div>
                </div>

                <div class="lc-pane" data-pane="variations">
                    <div class="lm-family-bar">
                        <div>
                            <div class="lc-section-title mb-0">Parent variations</div>
                            <p class="lc-help mb-0">Siblings share <code id="lc-family-parent">—</code> from Product Master. Publish lists them as one variation family.</p>
                        </div>
                        <button type="button" class="btn-lc btn-lc-ghost btn-sm" id="lc-copy-siblings-btn">
                            <i class="fas fa-copy me-1"></i>Copy listing details to siblings
                        </button>
                    </div>
                    <table class="lm-list-table">
                        <thead><tr><th>SKU</th><th>Pack</th><th>Title</th><th>ASIN</th><th>Qty</th><th>Price</th></tr></thead>
                        <tbody id="lc-family-rows"></tbody>
                    </table>
                </div>

                <div class="lc-pane" data-pane="title">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="lc-section-title mb-0" id="lc-title-heading">Title &amp; Description</div>
                        <button type="button" class="btn-lc btn-lc-ghost btn-sm lc-ebay-only" disabled>+ New Template</button>
                    </div>
                    <p class="lc-help mb-1 lc-ebay-only">Use template to quickly populate fields in this section.</p>
                    <select class="form-select mb-3 lc-ebay-only" disabled><option>-- Do Not Use Template --</option></select>
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <label class="form-label mb-0">Title <span class="lc-req">*</span></label>
                        <button type="button" class="btn-lc btn-lc-ghost btn-sm lc-load-master-btn" data-master-source="title">
                            <i class="fas fa-heading me-1"></i>Load Title from Title Master
                        </button>
                    </div>
                    <input id="lc-title" class="form-control mt-1">
                    <div id="lc-title-count" class="lc-char-ok mt-1">Characters: 0/80</div>
                    <div class="lc-desc-toolbar mt-3">
                        <div>
                            <label class="form-label mb-0">Description <span class="lc-req">*</span></label>
                            <div class="lc-help mb-0">Load description from Amazon, Description Master, or Shopify.</div>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button" class="btn-lc btn-lc-ghost btn-sm lc-load-desc-btn" data-desc-source="amazon">
                                <i class="fab fa-amazon me-1"></i>Load from Amazon
                            </button>
                            <button type="button" class="btn-lc btn-lc-ghost btn-sm lc-load-desc-btn" data-desc-source="description_master">
                                <i class="fas fa-book me-1"></i>Load from Description Master
                            </button>
                            <button type="button" class="btn-lc btn-lc-ghost btn-sm lc-load-desc-btn" data-desc-source="shopify">
                                <i class="fab fa-shopify me-1"></i>Load from Shopify
                            </button>
                            <button type="button" class="btn-lc btn-lc-primary btn-sm" id="lc-optimize-desc-btn">
                                <i class="fas fa-magic me-1"></i><span id="lc-optimize-desc-label">Optimize Description</span>
                            </button>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                        <div class="lc-desc-modes" id="lc-desc-modes">
                            <button type="button" class="active" data-desc-mode="code" title="HTML source"><i class="fas fa-code"></i></button>
                            <button type="button" data-desc-mode="preview" title="Preview"><i class="fas fa-eye"></i></button>
                            <button type="button" data-desc-mode="rich" title="Rich text"><i class="fas fa-font"></i></button>
                            <button type="button" data-desc-mode="fullscreen" title="Fullscreen"><i class="fas fa-expand"></i></button>
                        </div>
                        <button type="button" class="btn-lc btn-lc-ghost btn-sm" id="lc-switch-rich-btn">Switch to Rich Text Editor</button>
                    </div>
                    <div class="lc-desc-wrap" id="lc-desc-wrap">
                        <div class="lc-desc-code-row" id="lc-desc-code-row">
                            <div class="lc-desc-gutter" id="lc-desc-gutter">1</div>
                            <textarea id="lc-description" class="form-control" rows="14" spellcheck="false" placeholder="Add a marketplace description, or load it from Amazon, Description Master, or Shopify."></textarea>
                        </div>
                        <div id="lc-description-preview"></div>
                        <div id="lc-description-rich" contenteditable="true"></div>
                    </div>
                    <div class="lc-desc-side">
                        <div id="lc-desc-count" class="lc-char-ok">Characters: 0/500000</div>
                    </div>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-1">
                            <label class="form-label mb-0">Highlighted features / bullets</label>
                            <button type="button" class="btn-lc btn-lc-ghost btn-sm lc-load-master-btn" data-master-source="bullets">
                                <i class="fas fa-list me-1"></i>Load from Bullet Points
                            </button>
                        </div>
                        <textarea id="lc-bullets" class="form-control" rows="4" placeholder="One bullet per line. Loaded from /bullet-points."></textarea>
                    </div>
                </div>

                <div class="lc-pane" data-pane="images">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button" class="btn-lc btn-lc-primary btn-sm" id="lc-load-images-btn">Load Images From Image Master</button>
                            <button type="button" class="btn-lc btn-lc-ghost btn-sm lc-load-master-btn" data-master-source="videos">
                                <i class="fas fa-video me-1"></i>Load Videos from Video Master
                            </button>
                            <label class="btn-lc btn-lc-ghost btn-sm mb-0" style="cursor:pointer">
                                <i class="fas fa-upload me-1"></i>Upload Image
                                <input type="file" id="lc-upload-image" accept="image/*" hidden>
                            </label>
                        </div>
                        <div class="form-check lc-ebay-only">
                            <input class="form-check-input" type="checkbox" id="lc-gallery-plus">
                            <label class="form-check-label" for="lc-gallery-plus">Gallery Plus</label>
                        </div>
                    </div>
                    <p class="lc-help" id="lc-images-help">Drag images to reorder. First image is Primary.</p>
                    <input type="hidden" id="lc-images">
                    <div class="lc-image-grid" id="lc-image-preview"></div>
                    <div class="lc-reverb-only d-none mt-3">
                        <label class="form-label">Videos <span class="text-muted fw-normal">(Reverb, one URL per line)</span></label>
                        <textarea id="lc-reverb-videos" class="form-control" rows="3" placeholder="https://www.youtube.com/watch?v=…"></textarea>
                    </div>
                </div>

                <div class="lc-pane" data-pane="pricing">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                        <div class="lc-section-title mb-0" id="lc-pricing-heading">Pricing</div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn-lc btn-lc-ghost btn-sm lc-load-master-btn" data-master-source="pricing">
                                <i class="fas fa-database me-1"></i>Load from Product Master
                            </button>
                            <button type="button" class="btn-lc btn-lc-ghost btn-sm lc-ebay-only" disabled>+ New Template</button>
                        </div>
                    </div>
                    <select class="form-select mb-2 lc-ebay-only" disabled><option>-- Do Not Use Template --</option></select>
                    <p class="lc-help lc-ebay-only">When a template is applied, fields below use template values. Select “Do not use template” to edit directly.</p>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Price <span class="lc-req">*</span></label>
                            <input type="number" step="0.01" min="0" id="lc-price" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Quantity <span class="lc-req">*</span> <span class="text-muted fw-normal">(Shopify)</span></label>
                            <input type="number" min="0" id="lc-qty" class="form-control">
                        </div>
                        <div class="col-md-4 lc-reverb-only d-none">
                            <label class="form-label">Currency</label>
                            <input type="text" id="lc-price-currency" class="form-control" value="USD" maxlength="8">
                        </div>
                        <div class="col-md-4 d-flex align-items-end lc-ebay-only">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="lc-best-offer">
                                <label class="form-check-label" for="lc-best-offer">Best Offer</label>
                            </div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end lc-reverb-only d-none">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="lc-has-inventory" checked>
                                <label class="form-check-label" for="lc-has-inventory">Has inventory</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lc-pane" data-pane="category">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="lc-section-title mb-0" id="lc-category-heading">Primary Category</div>
                        <button type="button" class="btn-lc btn-lc-ghost btn-sm lc-ebay-only" disabled>+ New Template</button>
                    </div>
                    <select class="form-select mb-3 lc-ebay-only" disabled><option>-- Do Not Use Template --</option></select>
                    <p class="lc-help" id="lc-category-help">Search and select a marketplace category.</p>

                    <div class="mb-2 lc-mp-category-selected">
                        <label class="form-label mb-1">Category <span class="lc-req lc-tiktok-only d-none">*</span></label>
                        <span id="lc-category-path" class="lc-primary-path ms-2">Select a category</span>
                        <span class="text-muted small" id="lc-category-id-chip"></span>
                        <span class="lc-suggested" id="lc-category-suggested" style="display:none">Suggested</span>
                    </div>
                    <div class="mb-3 lc-mp-category-manual d-none">
                        <label class="form-label">Category ID <span class="lc-req">*</span></label>
                        <input type="text" id="lc-category-id-visible" class="form-control" placeholder="Marketplace category ID">
                        <label class="form-label mt-2">Category path / name</label>
                        <input type="text" id="lc-category-path-visible" class="form-control" placeholder="Optional category name">
                    </div>
                    <input type="hidden" id="lc-category-id">
                    <input type="hidden" id="lc-category-path-input">
                    <div class="lc-field-warn d-none mb-2" id="lc-category-id-warn">Required</div>

                    <div class="lc-ebay-only">
                    <label class="form-label">Secondary Category <i class="fas fa-exclamation-circle text-warning ms-1" title="Optional"></i></label>
                    <input id="lc-secondary-category-id" class="form-control mb-2" placeholder="Optional Category ID">
                    </div>

                    <div class="lc-cat-box mb-3 lc-mp-category-search">
                        <div class="lc-cat-search">
                            <i class="fas fa-search"></i>
                            <input type="text" id="lc-category-search" class="form-control form-control-sm border-0 shadow-none" placeholder="Search categories (e.g. speaker)">
                        </div>
                        <div class="lc-cat-results" id="lc-category-results">
                            <div class="text-muted small p-3">Type a keyword to search marketplace categories.</div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3 lc-ebay-only">
                        <div class="col-md-6">
                            <label class="form-label">Condition <span class="lc-req">*</span></label>
                            <select id="lc-condition" class="form-select">
                                <option value="">Please select</option>
                                <option value="New">New</option>
                                <option value="New other">New other</option>
                                <option value="Refurbished">Refurbished</option>
                                <option value="Used">Used</option>
                                <option value="For parts or not working">For parts or not working</option>
                            </select>
                            <div class="lc-field-warn d-none" id="lc-condition-warn">Required</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Listing Format</label>
                            <div class="mt-2">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="lc-format" id="lc-format-auction" value="Auction">
                                    <label class="form-check-label" for="lc-format-auction">Auction</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="lc-format" id="lc-format-fixed" value="FixedPriceItem" checked>
                                    <label class="form-check-label" for="lc-format-fixed">Fixed Price Item</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Duration</label>
                            <select id="lc-duration" class="form-select">
                                <option value="GTC">Good 'Til Cancelled</option>
                                <option value="Days_7">7 Days</option>
                                <option value="Days_10">10 Days</option>
                                <option value="Days_30">30 Days</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Condition Description</label>
                            <textarea id="lc-condition-desc" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="lc-private-listing">
                                <label class="form-check-label" for="lc-private-listing">Private Listing</label>
                            </div>
                        </div>
                    </div>

                    <div class="lc-reverb-only d-none">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                            <div class="lc-section-title mb-0">Reverb listing details</div>
                            <button type="button" class="btn-lc btn-lc-ghost btn-sm lc-load-master-btn" data-master-source="reverb">
                                <i class="fas fa-database me-1"></i>Load from Reverb Listing Master
                            </button>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Make <span class="lc-req">*</span></label>
                                <input type="text" id="lc-reverb-make" class="form-control" placeholder="e.g. 5 Core">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Model <span class="lc-req">*</span></label>
                                <input type="text" id="lc-reverb-model" class="form-control" placeholder="SKU or model name">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Finish</label>
                                <input type="text" id="lc-reverb-finish" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Year</label>
                                <input type="text" id="lc-reverb-year" class="form-control" placeholder="e.g. 2024">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Condition <span class="lc-req">*</span></label>
                                <select id="lc-reverb-condition" class="form-select">
                                    <option value="">Please select</option>
                                </select>
                                <div class="lc-field-warn d-none" id="lc-reverb-condition-warn">Required</div>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-3 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="lc-upc-does-not-apply">
                                <label class="form-check-label" for="lc-upc-does-not-apply">UPC does not apply</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="lc-reverb-handmade">
                                <label class="form-check-label" for="lc-reverb-handmade">Handmade</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="lc-reverb-offers" checked>
                                <label class="form-check-label" for="lc-reverb-offers">Offers enabled</label>
                            </div>
                        </div>
                    </div>

                    <div class="lc-section-title lc-ebay-only">Required Item Specifics</div>
                    <div class="lc-specific-row lc-ebay-only"><span>Brand</span><input id="lc-brand" class="form-control" placeholder="5 Core Inc"></div>
                    <div class="lc-specific-row lc-ebay-only"><span>Manufacturer</span><input id="lc-manufacturer-specific" class="form-control" placeholder="5 Core Inc"></div>
                    <div class="lc-specific-row lc-ebay-only"><span>MPN</span><input id="lc-mpn" class="form-control" placeholder="SKU"></div>
                    <div class="lc-specific-row lc-ebay-only"><span>UPC</span><input id="lc-upc-specific" class="form-control" placeholder="From CP Master"></div>
                    <div class="lc-section-title mt-3 lc-ebay-only">Recommended Item Specifics</div>
                    <div class="lc-specific-row lc-ebay-only"><span>Speaker Size</span><input id="lc-spec-speaker" class="form-control"></div>
                    <div class="lc-specific-row lc-ebay-only"><span>Voice Coil</span><input id="lc-spec-coil" class="form-control"></div>
                    <div class="lc-specific-row lc-ebay-only"><span>RMS Power</span><input id="lc-spec-rms" class="form-control"></div>
                </div>

                <div class="lc-pane" data-pane="policies">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="lc-section-title mb-0" id="lc-policies-title">Business Policies</div>
                        <button type="button" class="btn-lc btn-lc-ghost btn-sm lc-ebay-only" disabled>+ New Template</button>
                    </div>
                    <select class="form-select mb-2 lc-ebay-only" disabled><option>-- Do Not Use Template --</option></select>
                    <p class="lc-help" id="lc-policies-help"></p>
                    <div class="mb-3">
                        <button type="button" class="btn-lc btn-lc-ghost btn-sm lc-load-master-btn" data-master-source="package">
                            <i class="fas fa-box me-1"></i>Load Package from Product Master
                        </button>
                    </div>
                    <p class="lc-italic-note lc-ebay-only">If a listing includes Business Policies information, the Shipping and Payment &amp; Returns sections will be disabled.</p>

                    <div class="lc-tiktok-only d-none mb-3">
                        <label class="form-label">Warehouse ID</label>
                        <input id="lc-warehouse-id" class="form-control" placeholder="TikTok warehouse ID (optional)">
                        <p class="lc-help mb-0">Inventory publishes to this TikTok warehouse when set.</p>
                    </div>

                    <div class="lc-reverb-only d-none mb-3">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Shipping profile ID</label>
                                <input type="text" id="lc-reverb-shipping-profile" class="form-control" placeholder="From Reverb Listing Master">
                            </div>
                            <div class="col-md-6 d-flex align-items-end pb-1">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="lc-reverb-local-pickup">
                                    <label class="form-check-label" for="lc-reverb-local-pickup">Local pickup only</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Shipping rates (JSON)</label>
                                <textarea id="lc-reverb-shipping-rates" class="form-control font-monospace" rows="4" placeholder='[{"region_code":"US_CON","amount":"10.00","currency":"USD"}]'></textarea>
                                <p class="lc-help mb-0">Provide a profile ID and/or rates, or enable local pickup only.</p>
                            </div>
                        </div>
                    </div>

                    <div class="lc-location-row mb-3 lc-ebay-only">
                        <label class="form-label mb-0">Item Location <span class="lc-req">*</span></label>
                        <input id="lc-location-city" class="form-control" placeholder="City / Town" value="Bellefontaine">
                        <select id="lc-location-country" class="form-select">
                            <option value="">Please select</option>
                            <option value="US" selected>United States</option>
                            <option value="CA">Canada</option>
                            <option value="GB">United Kingdom</option>
                        </select>
                        <input id="lc-location-postal" class="form-control" placeholder="Zip / Postal" value="43311">
                    </div>

                    <div class="lc-section-title">Package Dimensions <span class="text-muted fw-normal small">(from Product Master)</span></div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-4"><input id="lc-pkg-l" class="form-control" placeholder="Length (in)"></div>
                        <div class="col-md-4"><input id="lc-pkg-w" class="form-control" placeholder="Width (in)"></div>
                        <div class="col-md-4"><input id="lc-pkg-h" class="form-control" placeholder="Height (in)"></div>
                    </div>
                    <div class="lc-section-title">Package Weight <span class="lc-req lc-weight-req" style="display:none">*</span></div>
                    <div class="d-flex gap-2 align-items-center mb-3" style="max-width:360px">
                        <input id="lc-pkg-lb" class="form-control" placeholder="0"><span class="text-muted">lbs</span>
                        <input id="lc-pkg-oz" class="form-control" placeholder="0"><span class="text-muted">oz</span>
                    </div>

                    <div class="lc-ebay-only">
                    <div class="lc-policy-row">
                        <label class="form-label mb-0">Shipping Policy <span class="lc-req">*</span></label>
                        <select id="lc-shipping-policy" class="form-select"><option value="">Loading…</option></select>
                        <a href="https://www.ebay.com/bp/manage" target="_blank" class="small text-nowrap">+ Create on eBay</a>
                    </div>
                    <div class="lc-field-warn ms-0 mb-2" id="lc-shipping-warn">Required</div>
                    <div class="lc-policy-row">
                        <label class="form-label mb-0">Payment Policy <span class="lc-req">*</span></label>
                        <select id="lc-payment-policy" class="form-select"><option value="">Loading…</option></select>
                        <a href="https://www.ebay.com/bp/manage" target="_blank" class="small text-nowrap">+ Create on eBay</a>
                    </div>
                    <div class="lc-field-warn mb-2" id="lc-payment-warn">Required</div>
                    <div class="lc-policy-row">
                        <label class="form-label mb-0">Return Policy <span class="lc-req">*</span></label>
                        <select id="lc-return-policy" class="form-select"><option value="">Loading…</option></select>
                        <a href="https://www.ebay.com/bp/manage" target="_blank" class="small text-nowrap">+ Create on eBay</a>
                    </div>
                    <div class="lc-field-warn mb-2" id="lc-return-warn">Required</div>
                    <button type="button" class="btn btn-link btn-sm p-0 mb-3" id="lc-refresh-policies">Refresh list of policies</button>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">VAT Percent</label>
                            <input id="lc-vat" class="form-control" type="number" step="0.01" min="0">
                        </div>
                    </div>
                    </div>
                </div>

                <div class="lc-pane" data-pane="relist">
                    <div class="lc-section-title">Auto Relist</div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="lc-auto-relist">
                        <label class="form-check-label" for="lc-auto-relist">Enable Auto Relist</label>
                    </div>
                    <p class="lc-help mt-2">Optional. Auto Relist settings are stored with the draft for future automation.</p>
                </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-lc btn-lc-danger" id="lc-delete-btn">Delete</button>
                <div class="d-flex gap-2 ms-auto align-items-center">
                    <button type="button" class="btn-lc btn-lc-primary" id="lc-publish-btn" disabled>
                        <i class="fas fa-cloud-upload-alt me-1"></i>Save &amp; Publish
                    </button>
                    <button type="button" class="btn-lc btn-lc-ghost" id="lc-save-close-btn" disabled>Save &amp; Close</button>
                    <button type="button" class="btn-lc btn-lc-ghost" id="lc-save-btn" disabled>Save Changes</button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- List on Channel modal --}}
<div class="modal fade" id="lmListChannelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Products to Channels Drafts</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="lm-select-all-channels">
                    <label class="form-check-label fw-semibold" for="lm-select-all-channels">Select All</label>
                </div>
                <div class="lm-channel-list" id="lm-channel-list"></div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="lm-include-siblings" checked>
                    <label class="form-check-label" for="lm-include-siblings">Include parent variations (sibling SKUs)</label>
                </div>
                <div class="lm-info-box mx-0">Select only the marketplace you want (for example Temu 2). Products go to <strong>Drafts</strong>. Then open Channel Listings, complete required fields, and Save &amp; Publish.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-lc btn-lc-ghost" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-lc btn-lc-primary" id="lm-add-draft-now-btn">Add As Draft Now</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="lmManageChannelsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Marketplaces</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Choose which marketplaces appear when listing products from Amz.</p>
                <div class="lm-channel-list" id="lm-manage-channel-list"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-lc btn-lc-ghost" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-lc btn-lc-primary" id="lm-save-channels-btn">Save Marketplaces</button>
            </div>
        </div>
    </div>
</div>

{{-- LitCommerce product detail (All Products click) --}}
<div class="modal fade lm-product-modal" id="lmProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                <h5 class="modal-title flex-grow-1" id="lm-prod-title">Product</h5>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn-lc btn-lc-ghost btn-sm" id="lm-prod-edit-btn">
                        <i class="fas fa-pen me-1"></i>Edit
                    </button>
                    <button type="button" class="btn-lc btn-lc-ghost btn-sm d-none" id="lm-prod-cancel-edit-btn">Cancel</button>
                    <button type="button" class="btn-lc btn-lc-ghost btn-sm d-none" id="lm-prod-save-btn">
                        <i class="fas fa-save me-1"></i>Save Changes
                    </button>
                    <button type="button" class="btn-lc btn-lc-primary btn-sm d-none" id="lm-prod-push-btn">
                        <i class="fas fa-cloud-upload-alt me-1"></i>Update on All Platforms
                    </button>
                    <button type="button" class="btn-lc btn-lc-primary btn-sm" id="lm-prod-update-btn">
                        <i class="fas fa-sync-alt me-1"></i>Update from Store
                    </button>
                </div>
            </div>
            <div class="modal-body">
                <div class="lm-prod-tabs" id="lm-prod-tabs">
                    <button type="button" class="lm-prod-tab active" data-pane="info">Product Info</button>
                    <button type="button" class="lm-prod-tab" data-pane="title">Title</button>
                    <button type="button" class="lm-prod-tab" data-pane="bullets">Bullet</button>
                    <button type="button" class="lm-prod-tab" data-pane="description">Description</button>
                    <button type="button" class="lm-prod-tab" data-pane="images">Images</button>
                    <button type="button" class="lm-prod-tab" data-pane="variations">Variations</button>
                    <button type="button" class="lm-prod-tab" data-pane="metafields">Main Store MetaFields</button>
                    <button type="button" class="lm-prod-tab" data-pane="listings">Listings</button>
                    <button type="button" class="lm-prod-tab" data-pane="orders">Orders</button>
                    <button type="button" class="lm-prod-tab" data-pane="changelog">Change Log</button>
                </div>
                <div id="lm-prod-push-overlay" class="lm-push-overlay d-none">
                    <div class="lm-push-overlay-card">
                        <div class="spin"><i class="fas fa-spinner fa-spin"></i></div>
                        <div class="fw-semibold" id="lm-prod-push-overlay-title">Updating marketplaces…</div>
                        <div class="text-muted small mt-1" id="lm-prod-push-overlay-sub">Please wait. This can take a minute.</div>
                    </div>
                </div>
                <div id="lm-prod-loading" class="p-4 text-muted">Loading product…</div>
                <div id="lm-prod-content" class="d-none">
                    <div class="lm-prod-pane active" data-pane="info">
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <button type="button" class="btn-lc btn-lc-ghost btn-sm lm-prod-master-btn" data-master-source="title">
                                <i class="fas fa-heading me-1"></i>Fetch from Title Master
                            </button>
                            <button type="button" class="btn-lc btn-lc-ghost btn-sm lm-prod-master-btn" data-master-source="description">
                                <i class="fas fa-book me-1"></i>Fetch from Description Master
                            </button>
                            <button type="button" class="btn-lc btn-lc-ghost btn-sm lm-prod-master-btn" data-master-source="identifiers">
                                <i class="fas fa-database me-1"></i>Fetch from Product Master
                            </button>
                            <button type="button" class="btn-lc btn-lc-ghost btn-sm lm-prod-master-btn" data-master-source="pricing">
                                <i class="fas fa-tag me-1"></i>Fetch Price from Product Master
                            </button>
                        </div>
                        <div id="lm-prod-view-wrap">
                            <div class="lm-prod-grid" id="lm-prod-info-grid"></div>
                            <div class="mt-3 fw-semibold mb-2">Description</div>
                            <div class="lm-prod-desc" id="lm-prod-description"></div>
                        </div>
                        <form id="lm-prod-edit-form" class="d-none" autocomplete="off" onsubmit="return false;">
                            <p class="lc-help">Edit the fields below, then Save Changes or Update on All Platforms.</p>
                            <div id="lm-prod-push-panel" class="lm-push-panel d-none">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                                    <div>
                                        <div class="lc-section-title mb-0">Choose marketplaces</div>
                                        <p class="lc-help mb-0">Listed channels update the live marketplace listing. Channels that are not live yet are saved as a draft in this app only.</p>
                                    </div>
                                    <button type="button" class="btn-lc btn-lc-ghost btn-sm" id="lm-prod-push-hide-btn">Hide</button>
                                </div>
                                <div class="d-flex flex-wrap gap-3 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="lm-push-part-title" checked>
                                        <label class="form-check-label" for="lm-push-part-title">Title</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="lm-push-part-description" checked>
                                        <label class="form-check-label" for="lm-push-part-description">Description</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="lm-push-part-price" checked>
                                        <label class="form-check-label" for="lm-push-part-price">Price</label>
                                    </div>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="lm-push-select-all">
                                    <label class="form-check-label fw-semibold" for="lm-push-select-all">Select All</label>
                                </div>
                                <div class="lm-channel-list" id="lm-prod-push-channels"></div>
                                <div class="lm-push-results mt-3 d-none" id="lm-push-results"></div>
                                <div class="d-flex justify-content-end gap-2 mt-3">
                                    <button type="button" class="btn-lc btn-lc-primary" id="lm-push-now-btn">
                                        <i class="fas fa-cloud-upload-alt me-1"></i>Push Updates
                                    </button>
                                </div>
                            </div>
                            <div class="lm-prod-edit-grid mb-3">
                                <label>Product Name</label>
                                <input type="text" class="form-control" id="lm-edit-title" maxlength="500">
                                <label>SKU</label>
                                <input type="text" class="form-control" id="lm-edit-sku" readonly>
                                <label>UPC</label>
                                <input type="text" class="form-control" id="lm-edit-upc">
                                <label>Vendor / Brand</label>
                                <input type="text" class="form-control" id="lm-edit-vendor">
                                <label>Manufacturer</label>
                                <input type="text" class="form-control" id="lm-edit-manufacturer">
                                <label>Product Type</label>
                                <input type="text" class="form-control" id="lm-edit-type">
                                <label>Tags</label>
                                <input type="text" class="form-control" id="lm-edit-tags">
                                <label>Price</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="lm-edit-price">
                                <label>Sale Price</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="lm-edit-sale-price">
                                <label>Condition</label>
                                <input type="text" class="form-control" id="lm-edit-condition">
                                <label>Short Description</label>
                                <textarea class="form-control" id="lm-edit-short-desc" rows="2"></textarea>
                                <label>Meta Title</label>
                                <input type="text" class="form-control" id="lm-edit-meta-title">
                                <label>SEO Description</label>
                                <textarea class="form-control" id="lm-edit-seo" rows="2"></textarea>
                                <label>Description</label>
                                <textarea class="form-control font-monospace" id="lm-edit-description" rows="10" placeholder="HTML or plain text"></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="lm-prod-pane" data-pane="title">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                            <button type="button" class="btn-lc btn-lc-primary btn-sm lm-master-sync-btn" data-master-source="content">
                                <i class="fas fa-sync-alt me-1"></i>Sync from Title Master
                            </button>
                            <button type="button" class="btn-lc btn-lc-ghost btn-sm lm-master-apply-btn" data-apply="title">
                                <i class="fas fa-file-import me-1"></i>Apply to Product Info
                            </button>
                            <a class="btn-lc btn-lc-ghost btn-sm" href="{{ route('title.master') }}" target="_blank" rel="noopener">Open Title Master</a>
                            <span class="text-muted small lm-master-status" data-for="title"></span>
                        </div>
                        <div class="lm-family-sync-checks mb-3">
                            <label class="form-check">
                                <input type="checkbox" class="form-check-input lm-sync-siblings">
                                <span class="form-check-label">Sync to siblings</span>
                            </label>
                            <label class="form-check">
                                <input type="checkbox" class="form-check-input lm-sync-parent">
                                <span class="form-check-label">Sync to parent and vice versa</span>
                            </label>
                        </div>
                        <section class="lm-masters-block">
                            <div class="lm-masters-head">Title Master</div>
                            <div class="lm-masters-body" id="lm-masters-titles"></div>
                        </section>
                    </div>
                    <div class="lm-prod-pane" data-pane="bullets">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                            <button type="button" class="btn-lc btn-lc-primary btn-sm lm-master-sync-btn" data-master-source="bullets">
                                <i class="fas fa-sync-alt me-1"></i>Sync from Bullet Points
                            </button>
                            <a class="btn-lc btn-lc-ghost btn-sm" href="{{ route('bullet.points') }}" target="_blank" rel="noopener">Open Bullet Points</a>
                            <span class="text-muted small lm-master-status" data-for="bullets"></span>
                        </div>
                        <div class="lm-family-sync-checks mb-3">
                            <label class="form-check">
                                <input type="checkbox" class="form-check-input lm-sync-siblings">
                                <span class="form-check-label">Sync to siblings</span>
                            </label>
                            <label class="form-check">
                                <input type="checkbox" class="form-check-input lm-sync-parent">
                                <span class="form-check-label">Sync to parent and vice versa</span>
                            </label>
                        </div>
                        <section class="lm-masters-block">
                            <div class="lm-masters-head">Bullet Points</div>
                            <div class="lm-masters-body lm-masters-body--table" id="lm-masters-bullets"></div>
                        </section>
                    </div>
                    <div class="lm-prod-pane" data-pane="description">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                            <button type="button" class="btn-lc btn-lc-primary btn-sm lm-master-sync-btn" data-master-source="description">
                                <i class="fas fa-sync-alt me-1"></i>Sync from Description Master
                            </button>
                            <button type="button" class="btn-lc btn-lc-ghost btn-sm lm-master-apply-btn" data-apply="description">
                                <i class="fas fa-file-import me-1"></i>Apply to Product Info
                            </button>
                            <a class="btn-lc btn-lc-ghost btn-sm" href="{{ route('product.description') }}" target="_blank" rel="noopener">Open Description Master</a>
                            <span class="text-muted small lm-master-status" data-for="description"></span>
                        </div>
                        <div class="lm-family-sync-checks mb-3">
                            <label class="form-check">
                                <input type="checkbox" class="form-check-input lm-sync-siblings">
                                <span class="form-check-label">Sync to siblings</span>
                            </label>
                            <label class="form-check">
                                <input type="checkbox" class="form-check-input lm-sync-parent">
                                <span class="form-check-label">Sync to parent and vice versa</span>
                            </label>
                        </div>
                        <section class="lm-masters-block">
                            <div class="lm-masters-head">
                                <span>Description Master</span>
                                <div class="lm-masters-head-actions">
                                    <button type="button" class="lm-row-edit-btn" data-field="description_html" title="Edit description"><i class="fas fa-pen"></i></button>
                                    <span class="lm-char-count is-short" id="lm-desc-char-count">0</span>
                                </div>
                            </div>
                            <div class="lm-prod-desc" id="lm-masters-description"></div>
                        </section>
                    </div>
                    <div class="lm-prod-pane" data-pane="images">
                        <div class="mb-3">
                            <button type="button" class="btn-lc btn-lc-primary btn-sm lm-prod-master-btn" data-master-source="images">
                                <i class="fas fa-images me-1"></i>Fetch from Image Master
                            </button>
                        </div>
                        <div class="lm-prod-images">
                            <div class="lm-prod-main-img"><img id="lm-prod-main-image" src="" alt=""></div>
                            <div class="lm-prod-thumbs" id="lm-prod-thumbs"></div>
                        </div>
                    </div>
                    <div class="lm-prod-pane" data-pane="variations">
                        <div class="lm-family-bar">
                            <div class="lc-help mb-0">Variations grouped by Product Master parent.</div>
                            <button type="button" class="btn-lc btn-lc-ghost btn-sm" id="lm-prod-copy-siblings-btn">
                                <i class="fas fa-copy me-1"></i>Copy details to siblings
                            </button>
                        </div>
                        <table class="lm-list-table"><thead><tr><th>SKU</th><th>Pack</th><th>Title</th><th>ASIN</th><th>Qty</th><th>Price</th></tr></thead><tbody id="lm-prod-variations"></tbody></table>
                    </div>
                    <div class="lm-prod-pane" data-pane="metafields">
                        <table class="lm-meta-table"><thead><tr><th>Attribute name</th><th>Attribute value</th></tr></thead><tbody id="lm-prod-metafields"></tbody></table>
                    </div>
                    <div class="lm-prod-pane" data-pane="listings">
                        <div class="lm-listed-head">Listed On</div>
                        <table class="lm-list-table"><thead><tr><th>Channel</th><th>Product Name</th><th>Qty</th><th>Price</th><th>Status</th></tr></thead><tbody id="lm-prod-listed"></tbody></table>
                        <div class="lm-unlist-head">Not Listed On</div>
                        <table class="lm-list-table"><thead><tr><th>Channel</th><th></th></tr></thead><tbody id="lm-prod-unlisted"></tbody></table>
                    </div>
                    <div class="lm-prod-pane" data-pane="orders">
                        <div class="text-muted">Orders for this SKU appear in Marketplace Orders. No linked order rows in Listing Manager yet.</div>
                    </div>
                    <div class="lm-prod-pane" data-pane="changelog">
                        <table class="lm-list-table"><thead><tr><th></th><th>Change Details</th><th>Change Time</th></tr></thead><tbody id="lm-prod-changelog"></tbody></table>
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
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let table = null;
    let draftsTable = null;
    let stockFilter = 'all';
    let draftsTab = 'drafts';
    let allChannels = [];
    let pendingSkusForDraft = [];
    let currentDraft = null;
    let multiEdit = false;
    let dirty = false;
    let editorImages = [];
    let imageSortable = null;
    let titleLimit = 80;
    let descLimit = 500000;
    let categoryTimer = null;
    let policyDefaults = {};
    let descMode = 'code'; // code|preview|rich

    function toast(message, type) {
        const wrap = document.getElementById('lm-toast-wrap');
        if (!wrap) return;
        const el = document.createElement('div');
        el.className = 'alert alert-' + (type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info') + ' shadow-sm';
        el.style.minWidth = '280px';
        el.textContent = message;
        wrap.appendChild(el);
        setTimeout(() => el.remove(), 4500);
    }

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    function logoSrc(logo) {
        const v = String(logo || '').trim();
        if (!v) return '';
        if (/^https?:\/\//i.test(v) || v.startsWith('/')) return v;
        return '/storage/' + v.replace(/^\/+/, '');
    }

    function statusPill(ui) {
        const map = {
            'Missing Info': 'lm-status-missing',
            'Ready': 'lm-status-ready',
            'Active': 'lm-status-active',
            'Failed': 'lm-status-failed',
        };
        const cls = map[ui] || 'lm-status-missing';
        return `<span class="lm-status-pill ${cls}">${escapeHtml(ui || 'Missing Info')}</span>`;
    }

    function showPanel(name) {
        $('.lm-page-tab').removeClass('active');
        $(`.lm-page-tab[data-panel="${name}"]`).addClass('active');
        $('.lm-panel').removeClass('active');
        $(`#lm-panel-${name}`).addClass('active');
        $('#lm-header-actions-products').toggleClass('d-none', name !== 'products');
        $('#lm-header-actions-drafts').toggleClass('d-none', name !== 'drafts');
        updatePageTitle();
        if (name === 'drafts') loadDrafts();
        setTimeout(sizeLmTables, 40);
    }

    function sizeLmTables() {
        const footer = document.querySelector('.footer');
        const footerH = footer ? footer.offsetHeight : 32;
        const gap = 14;
        const fit = (instance, selector) => {
            if (!instance) return;
            const el = document.querySelector(selector);
            if (!el || el.offsetParent === null) return;
            const top = el.getBoundingClientRect().top;
            const h = Math.max(280, Math.floor(window.innerHeight - top - footerH - gap));
            instance.setHeight(h);
        };
        fit(table, '#lm-products-table');
        fit(draftsTable, '#lm-drafts-table');
    }

    function updatePageTitle() {
        const channelId = parseInt($('#lm-draft-channel').val() || '0', 10);
        const ch = allChannels.find(c => Number(c.id) === channelId);
        if ($('#lm-panel-drafts').hasClass('active') && ch) {
            const ed = editorProfileForChannel(ch.channel);
            $('#lm-page-title').text(ed.page_title || ((ch.channel || 'Channel') + ' Listings'));
            $('#lm-quick-list-label').text(ed.header_quick || ('Quick/Auto List to ' + ch.channel));
            $('#lm-import-channel-btn').text(ed.header_import || 'Import');
            const listingUrl = ch.listing_url || '#';
            $('#lm-open-listing-page').attr('href', listingUrl).text('Open ' + (ch.channel || 'channel') + ' listing page');
        } else if ($('#lm-panel-drafts').hasClass('active')) {
            $('#lm-page-title').text('Channel Listings');
            $('#lm-quick-list-label').text('Quick/Auto List');
            $('#lm-import-channel-btn').text('Import');
        } else {
            $('#lm-page-title').text('Listing Manager');
        }
    }

    function editorProfileForChannel(name) {
        const n = String(name || '').toLowerCase().replace(/[\s\-_]/g, '');
        if (/ebay/.test(n)) {
            return { family: 'ebay', page_title: (name || 'eBay') + ' Listings', header_quick: 'Quick/Auto List to eBay', header_import: 'Import from eBay' };
        }
        if (/tiktok/.test(n)) {
            return { family: 'tiktok', page_title: (name || 'TikTok Shop') + ' Listings', header_quick: 'Quick/Auto List to TikTok Shop', header_import: 'Import from TikTok Shop' };
        }
        if (/temu/.test(n)) {
            return { family: 'temu', page_title: (name || 'Temu') + ' Listings', header_quick: 'Quick/Auto List to Temu', header_import: 'Import from Temu' };
        }
        if (/faire/.test(n)) {
            return { family: 'faire', page_title: (name || 'Faire') + ' Listings', header_quick: 'Quick/Auto List to Faire', header_import: 'Import from Faire' };
        }
        return { family: 'default', page_title: (name || 'Channel') + ' Listings', header_quick: 'Quick/Auto List to Channel', header_import: 'Import' };
    }

    function buildProductQuery() {
        return {
            stock: stockFilter,
            q_name: $('#lm-q-name').val() || '',
            q_sku: $('#lm-q-sku').val() || '',
            product_type: $('#lm-product-type').val() || 'all',
            status: 'all',
        };
    }

    function buildDraftQuery() {
        return {
            tab: draftsTab,
            channel_id: $('#lm-draft-channel').val() || 0,
            q: $('#lm-draft-q').val() || '',
            q_sku: $('#lm-draft-q-sku').val() || '',
        };
    }

    function loadTable() {
        if (!table) return;
        const params = new URLSearchParams(buildProductQuery()).toString();
        table.setData("{{ route('listing.manager.data') }}?" + params);
    }

    function loadDrafts() {
        if (!draftsTable) return;
        const params = new URLSearchParams(buildDraftQuery()).toString();
        draftsTable.setData("{{ route('listing.manager.drafts') }}?" + params);
        updatePageTitle();
        $('#lm-drafts-help').toggle(draftsTab === 'drafts');
        $('#lm-channel-banner').toggle(draftsTab === 'drafts');
    }

    function loadChannels() {
        return $.getJSON("{{ route('listing.manager.channels') }}").then(function (res) {
            allChannels = (res && res.channels) ? res.channels : [];
            const $sel = $('#lm-draft-channel');
            const current = $sel.val();
            $sel.find('option:not(:first)').remove();
            allChannels.filter(c => c.enabled).forEach(c => {
                $sel.append(`<option value="${c.id}">${escapeHtml(c.channel)}</option>`);
            });
            // Keep the user's current channel; do not force eBay.
            if (current && current !== '0') {
                $sel.val(current);
            }
            updatePageTitle();
            return allChannels;
        });
    }

    function renderChannelRows(selector, channels, enabledOnly, prechecked) {
        const list = enabledOnly ? channels.filter(c => c.enabled) : channels;
        const checked = new Set((prechecked || []).map(Number));
        $(selector).html(list.map(c => {
            const on = checked.has(Number(c.id));
            const src = logoSrc(c.logo);
            return `<label class="lm-channel-row ${on ? 'is-checked' : ''}">
                <input type="checkbox" class="form-check-input lm-channel-cb" value="${c.id}" ${on ? 'checked' : ''}>
                ${src ? `<img src="${escapeHtml(src)}" alt="">` : '<span class="lm-thumb-empty"><i class="fas fa-store"></i></span>'}
                <span class="fw-semibold">${escapeHtml(c.channel)}</span>
            </label>`;
        }).join(''));
        $(selector).off('change', '.lm-channel-cb').on('change', '.lm-channel-cb', function () {
            $(this).closest('.lm-channel-row').toggleClass('is-checked', this.checked);
        });
    }

    function openListModal(skus) {
        if (!skus.length) { toast('Select at least one product.', 'error'); return; }
        pendingSkusForDraft = skus;
        loadChannels().then(function () {
            const enabled = allChannels.filter(c => c.enabled);
            renderChannelRows('#lm-channel-list', enabled, false, []);
            $('#lm-select-all-channels').prop('checked', false);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('lmListChannelModal')).show();
        });
    }

    let currentProductSku = '';
    let currentProduct = null;
    let productEditMode = false;

    function setProductPane(pane) {
        $('#lm-prod-tabs .lm-prod-tab').removeClass('active');
        $('#lm-prod-tabs .lm-prod-tab[data-pane="' + pane + '"]').addClass('active');
        $('#lm-prod-content .lm-prod-pane').removeClass('active');
        $('#lm-prod-content .lm-prod-pane[data-pane="' + pane + '"]').addClass('active');
    }

    function money(v) {
        if (v == null || v === '') return '—';
        const n = Number(v);
        return Number.isFinite(n) ? ('$' + n.toFixed(2)) : String(v);
    }

    function renderProductInfo(p) {
        const rows = [
            ['Product Origin', escapeHtml(p.origin || 'Amz') + (p.origin === 'Main Store' ? ' <i class="fab fa-shopify text-success"></i>' : ' <i class="fab fa-amazon" style="color:#ff9900"></i>')],
            ['Product Name', escapeHtml(p.title || '')],
            ['Status', `<span class="lm-status-active">${escapeHtml(p.status || 'ACTIVE')}</span>`],
            ['SKU', escapeHtml(p.sku || '')],
            ['UPC', escapeHtml(p.upc || '—')],
            ['Manufacturer Part Number', escapeHtml(p.mpn || '—')],
            ['ASIN', escapeHtml(p.asin || '—')],
            ['Vendor / Brand', escapeHtml(p.vendor || '5 Core Inc.')],
            ['Manufacturer', escapeHtml(p.manufacturer || '5 Core Inc.')],
            ['Parent', escapeHtml(p.parent || p.sku || '—')],
            ['Product Type', escapeHtml(p.product_type || '—')],
            ['Tags', escapeHtml(p.tags || '—')],
            ['Collection', escapeHtml(p.collections || '—')],
            ['Price', money(p.price)],
            ['Sale Price', money(p.sale_price)],
            ['MSRP', money(p.msrp)],
            ['Manage Stock', escapeHtml(p.manage_stock || 'Yes')],
            ['In Stock', escapeHtml(p.in_stock || '—')],
            ['Shopify INV', p.quantity != null ? escapeHtml(String(p.quantity)) : '—'],
            ['Condition', escapeHtml(p.condition || '—')],
            ['Package Weight', escapeHtml(p.package_weight || '—')],
            ['Package Dimensions', escapeHtml(p.package_dimensions || '—')],
            ['Store product URL', p.store_url ? `<a href="${escapeHtml(p.store_url)}" target="_blank" rel="noopener">${escapeHtml(p.store_url)}</a>` : '—'],
            ['SEO Description', escapeHtml(p.seo_description || '—')],
            ['Meta Title', escapeHtml(p.meta_title || '—')],
            ['Short Description', escapeHtml(p.short_description || '—')],
            ['Last Modified', escapeHtml(p.updated_at || '—')],
            ['Last Imported', escapeHtml(p.imported_at || '—')],
        ];
        $('#lm-prod-info-grid').html(rows.map(([k, v]) => `<div class="k">${k}</div><div class="v">${v}</div>`).join(''));
        $('#lm-prod-description').html(p.description || '<span class="text-muted">No description</span>');
    }

    function stripLeadingBulletNum(text) {
        return String(text || '').replace(/^\s*\d+\s*[\.\)\-:]\s*/, '');
    }

    function masterRowEditBtn(field) {
        return `<button type="button" class="lm-row-edit-btn" data-field="${escapeHtml(field)}" title="Edit"><i class="fas fa-pen"></i></button>`;
    }

    function charCountClass(n) {
        const count = Number(n) || 0;
        if (count > 170) return 'is-over';
        if (count >= 100) return 'is-ok';
        return 'is-short';
    }

    function charCountHtml(n) {
        const count = Number(n) || 0;
        return `<span class="lm-char-count ${charCountClass(count)}">${count}</span>`;
    }

    function setCharCount($el, n) {
        if (!$el || !$el.length) return;
        const count = Number(n) || 0;
        $el.attr('class', 'lm-char-count ' + charCountClass(count)).text(String(count));
    }

    function renderMasterContent(pack) {
        const data = pack && typeof pack === 'object' ? pack : {};
        const titles = Array.isArray(data.titles) ? data.titles : [];
        const bullets = Array.isArray(data.bullets) ? data.bullets : [];
        const description = String(data.description || '');
        $('.lm-master-status[data-for="title"]').text(data.has_title === false ? (data.message || '') : '');
        $('.lm-master-status[data-for="bullets"]').text(data.has_bullets === false && data.source === 'bullets' ? (data.message || '') : '');
        $('.lm-master-status[data-for="description"]').text(data.has_description === false && data.source === 'description' ? (data.message || '') : '');
        const titleRows = titles.length ? titles : [
            { key: 'title150', label: 'Title 170', value: '', chars: 0 },
            { key: 'title100', label: 'Title 100', value: '', chars: 0 },
            { key: 'title80', label: 'Title 80', value: '', chars: 0 },
            { key: 'title75', label: 'Title 75', value: '', chars: 0 },
            { key: 'title60', label: 'Title 60', value: '', chars: 0 }
        ];
        $('#lm-masters-titles').html(titleRows.map(function (t) {
            const value = String(t.value || '').trim();
            const chars = value ? (t.chars || value.length) : 0;
            return `<div class="lm-masters-title-row" data-field="${escapeHtml(t.key || '')}">
                <div class="k">${escapeHtml(t.label || t.key || '')}</div>
                <div class="v">${value ? escapeHtml(value) : '<span class="text-muted">—</span>'}</div>
                ${masterRowEditBtn(t.key || '')}
                ${charCountHtml(chars)}
            </div>`;
        }).join(''));
        const paddedBullets = [0, 1, 2, 3, 4].map(function (i) {
            return String(bullets[i] || '').trim();
        });
        $('#lm-masters-bullets').html(`<table class="lm-bullet-table">
            <thead><tr><th class="num">#</th><th>Bullet</th><th class="act"></th><th class="cnt"></th></tr></thead>
            <tbody>${paddedBullets.map(function (b, i) {
            const field = 'bullet' + (i + 1);
            const display = stripLeadingBulletNum(b);
            return `<tr class="lm-masters-bullet-row" data-field="${field}">
                <td class="n">${i + 1}</td>
                <td class="v">${display ? escapeHtml(display) : '<span class="text-muted">—</span>'}</td>
                <td class="act">${masterRowEditBtn(field)}</td>
                <td class="cnt">${charCountHtml(b.length)}</td>
            </tr>`;
        }).join('')}</tbody></table>`);
        const $descHeadBtn = $('.lm-prod-pane[data-pane="description"] .lm-row-edit-btn[data-field="description_html"]');
        $descHeadBtn.removeClass('d-none').prop('disabled', false);
        setCharCount($('#lm-desc-char-count'), description.length);
        $('#lm-masters-description').removeClass('lm-desc-edit-wrap').html(description
            ? description
            : '<span class="text-muted">No description on Description Master for this SKU.</span>');
    }

    function masterFieldValue(field) {
        const pack = (currentProduct && currentProduct.master_content) || {};
        if (field === 'description_html') return String(pack.description || '');
        if (String(field).indexOf('bullet') === 0) {
            const idx = parseInt(String(field).replace('bullet', ''), 10) - 1;
            return String((pack.bullets || [])[idx] || '');
        }
        const row = (pack.titles || []).find(function (t) { return t.key === field; });
        return String((row && row.value) || '');
    }

    function beginMasterEdit(field) {
        cancelMasterEdit();
        if (field === 'description_html') {
            const current = masterFieldValue(field);
            $('.lm-prod-pane[data-pane="description"] .lm-row-edit-btn[data-field="description_html"]').addClass('d-none');
            $('#lm-masters-description')
                .addClass('lm-desc-edit-wrap')
                .html(`<textarea class="form-control font-monospace lm-master-edit-input" data-field="description_html"></textarea>
                    <div class="lm-row-edit-actions mt-2">
                        <button type="button" class="btn-lc btn-lc-primary btn-sm lm-master-save-btn" data-field="description_html">Save</button>
                        <button type="button" class="btn-lc btn-lc-ghost btn-sm lm-master-cancel-btn">Cancel</button>
                    </div>`);
            $('#lm-masters-description .lm-master-edit-input').val(current).focus();
            setCharCount($('#lm-desc-char-count'), current.length);
            return;
        }
        const $row = $(`.lm-masters-title-row[data-field="${field}"], .lm-masters-bullet-row[data-field="${field}"]`).first();
        if (!$row.length) return;
        const current = masterFieldValue(field);
        const rows = field.indexOf('title') === 0 ? 3 : 2;
        $row.find('.v').html(`<textarea class="form-control lm-master-edit-input" data-field="${escapeHtml(field)}" rows="${rows}"></textarea>`);
        $row.find('.lm-row-edit-btn').replaceWith(`<div class="lm-row-edit-actions">
            <button type="button" class="btn-lc btn-lc-primary btn-sm lm-master-save-btn" data-field="${escapeHtml(field)}">Save</button>
            <button type="button" class="btn-lc btn-lc-ghost btn-sm lm-master-cancel-btn">Cancel</button>
        </div>`);
        $row.find('.lm-master-edit-input').val(current).focus();
        setCharCount($row.find('.lm-char-count'), current.length);
    }

    function cancelMasterEdit() {
        const pack = (currentProduct && currentProduct.master_content) || {};
        if ($('.lm-master-edit-input').length) {
            renderMasterContent(pack);
        }
    }

    function saveMasterField(field, value) {
        const sku = currentProductSku;
        if (!sku) { toast('Open a product first.', 'error'); return; }
        const prefs = currentSyncPrefs();
        const $btn = $(`.lm-master-save-btn[data-field="${field}"]`);
        $btn.prop('disabled', true).text('Saving…');
        fetch("{{ url('/listing-manager/product/master-field') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf
            },
            body: JSON.stringify({
                sku,
                field,
                value,
                sync_siblings: prefs.siblings,
                sync_parent: prefs.parent
            })
        }).then(function (res) {
            return res.json().then(function (json) {
                if (!res.ok) throw new Error((json && json.message) || 'Save failed');
                return json;
            });
        }).then(function (json) {
            if (json.master_content) {
                mergeMasterContent(json.master_content);
            } else {
                cancelMasterEdit();
            }
            if (field.indexOf('title') === 0 && json.value) {
                currentProduct.title = json.master_content?.title || json.value;
            }
            if (field === 'description_html' && json.master_content?.description) {
                currentProduct.description = json.master_content.description;
            }
            toast(json.message || 'Saved.', 'success');
        }).catch(function (ex) {
            toast(ex.message || 'Save failed.', 'error');
            $btn.prop('disabled', false).text('Save');
        });
    }

    function mergeMasterContent(res) {
        if (!currentProduct) currentProduct = { sku: currentProductSku };
        const prev = currentProduct.master_content && typeof currentProduct.master_content === 'object'
            ? currentProduct.master_content
            : {};
        const next = Object.assign({}, prev, res || {});
        if (Array.isArray(res?.titles)) next.titles = res.titles;
        if (Array.isArray(res?.bullets)) next.bullets = res.bullets;
        if (res && Object.prototype.hasOwnProperty.call(res, 'description') && res.description) {
            next.description = res.description;
        }
        if (res?.title) next.title = res.title;
        currentProduct.master_content = next;
        renderMasterContent(next);
        return next;
    }

    function applySyncPrefs(prefs) {
        const siblings = !!(prefs && prefs.siblings);
        const parent = !!(prefs && prefs.parent);
        $('.lm-sync-siblings').prop('checked', siblings);
        $('.lm-sync-parent').prop('checked', parent);
    }

    function currentSyncPrefs() {
        return {
            siblings: $('.lm-sync-siblings').first().is(':checked'),
            parent: $('.lm-sync-parent').first().is(':checked')
        };
    }

    function saveSyncPrefs() {
        const prefs = currentSyncPrefs();
        fetch("{{ url('/listing-manager/product/sync-prefs') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf
            },
            body: JSON.stringify(prefs)
        }).catch(function () {});
        return prefs;
    }

    function fillProductEditForm(p) {
        $('#lm-edit-title').val(p.title || '');
        $('#lm-edit-sku').val(p.sku || '');
        $('#lm-edit-upc').val(p.upc || '');
        $('#lm-edit-vendor').val(p.vendor || '');
        $('#lm-edit-manufacturer').val(p.manufacturer || '');
        $('#lm-edit-type').val(p.product_type || '');
        $('#lm-edit-tags').val(p.tags || '');
        $('#lm-edit-price').val(p.price != null ? p.price : '');
        $('#lm-edit-sale-price').val(p.sale_price != null ? p.sale_price : '');
        $('#lm-edit-condition').val(p.condition || '');
        $('#lm-edit-short-desc').val(p.short_description || '');
        $('#lm-edit-meta-title').val(p.meta_title || '');
        $('#lm-edit-seo').val(p.seo_description || '');
        $('#lm-edit-description').val(p.description || '');
    }

    function collectProductEditFields() {
        return {
            sku: currentProductSku,
            title: $('#lm-edit-title').val() || '',
            upc: $('#lm-edit-upc').val() || '',
            vendor: $('#lm-edit-vendor').val() || '',
            manufacturer: $('#lm-edit-manufacturer').val() || '',
            product_type: $('#lm-edit-type').val() || '',
            tags: $('#lm-edit-tags').val() || '',
            price: $('#lm-edit-price').val() || '',
            sale_price: $('#lm-edit-sale-price').val() || '',
            condition: $('#lm-edit-condition').val() || '',
            short_description: $('#lm-edit-short-desc').val() || '',
            meta_title: $('#lm-edit-meta-title').val() || '',
            seo_description: $('#lm-edit-seo').val() || '',
            description: $('#lm-edit-description').val() || '',
        };
    }

    function setProductEditMode(on) {
        productEditMode = !!on;
        $('#lm-prod-view-wrap').toggleClass('d-none', productEditMode);
        $('#lm-prod-edit-form').toggleClass('d-none', !productEditMode);
        $('#lm-prod-edit-btn').toggleClass('d-none', productEditMode);
        $('#lm-prod-cancel-edit-btn').toggleClass('d-none', !productEditMode);
        $('#lm-prod-save-btn').toggleClass('d-none', !productEditMode);
        $('#lm-prod-push-btn').toggleClass('d-none', !productEditMode);
        if (!productEditMode) {
            $('#lm-prod-push-panel').addClass('d-none');
            $('#lm-prod-push-overlay').addClass('d-none');
        }
        if (productEditMode && currentProduct) {
            fillProductEditForm(currentProduct);
            setProductPane('info');
        }
    }

    function renderProductImages(images) {
        const list = Array.isArray(images) ? images.filter(Boolean) : [];
        const main = document.getElementById('lm-prod-main-image');
        const thumbs = document.getElementById('lm-prod-thumbs');
        if (!list.length) {
            main.removeAttribute('src');
            main.alt = 'No images';
            thumbs.innerHTML = '<div class="text-muted">No images from Image Master / Amz / Main Store.</div>';
            return;
        }
        main.src = list[0];
        thumbs.innerHTML = list.map((u, i) =>
            `<img src="${escapeHtml(u)}" alt="" class="${i === 0 ? 'active' : ''}" data-idx="${i}">`
        ).join('');
        $(thumbs).off('click', 'img').on('click', 'img', function () {
            $(thumbs).find('img').removeClass('active');
            $(this).addClass('active');
            main.src = this.src;
        });
    }

    function renderProductListings(p) {
        const listed = p.listed_on || [];
        $('#lm-prod-listed').html(listed.length ? listed.map(r => {
            const name = escapeHtml(r.product_name || p.title || '');
            // Keep listed channel name inside app (open draft editor when available)
            const nameCell = r.draft_id
                ? `<a href="#" class="lm-open-listed-draft" data-id="${r.draft_id}">${name}</a>`
                : name;
            const logo = logoSrc(r.logo);
            return `<tr>
                <td>${logo ? `<img src="${escapeHtml(logo)}" alt="" style="height:18px;margin-right:6px">` : ''}${escapeHtml(r.channel || '')}</td>
                <td>${nameCell}</td>
                <td>${r.qty != null ? escapeHtml(String(r.qty)) : '—'}</td>
                <td>${money(r.price)}</td>
                <td><span class="lm-status-active">${escapeHtml(r.status || 'ACTIVE')}</span></td>
            </tr>`;
        }).join('') : '<tr><td colspan="5" class="text-muted">Not listed on any channel yet.</td></tr>');

        const unlisted = p.not_listed_on || [];
        $('#lm-prod-unlisted').html(unlisted.length ? unlisted.map(r => {
            const logo = logoSrc(r.logo);
            return `<tr>
                <td>${logo ? `<img src="${escapeHtml(logo)}" alt="" style="height:18px;margin-right:6px">` : ''}${escapeHtml(r.channel || '')}</td>
                <td class="text-end">
                    <button type="button" class="btn-lc btn-lc-primary btn-sm lm-create-listing-btn"
                        data-channel-id="${r.id}" data-sku="${escapeHtml(p.sku || '')}">+ Create Listing</button>
                </td>
            </tr>`;
        }).join('') : '<tr><td colspan="2" class="text-muted">All enabled channels already have a listing/draft.</td></tr>');
    }

    function renderProductExtras(p) {
        const vars = p.variations || [];
        $('#lm-prod-variations').html(vars.length ? vars.map(v => `<tr class="${v.is_current ? 'lm-var-current' : ''}">
            <td><a href="#" class="lm-name-link lm-open-sibling" data-sku="${escapeHtml(v.sku || '')}">${escapeHtml(v.sku || '')}</a></td>
            <td><span class="lm-var-label">${escapeHtml(v.variation_label || v.sku || '')}</span></td>
            <td>${escapeHtml(v.title || '')}</td>
            <td>${escapeHtml(v.asin || '—')}</td>
            <td>${v.quantity != null ? escapeHtml(String(v.quantity)) : '—'}</td>
            <td>${money(v.price)}</td>
        </tr>`).join('') : '<tr><td colspan="6" class="text-muted">No parent variations in Product Master</td></tr>');

        const meta = p.metafields || [];
        $('#lm-prod-metafields').html(meta.length ? meta.map(m => `<tr>
            <th>${escapeHtml(m.name || '')}</th>
            <td><code style="white-space:pre-wrap;font-size:.78rem">${escapeHtml(m.value || '')}</code></td>
        </tr>`).join('') : '<tr><td colspan="2" class="text-muted">No metafields</td></tr>');

        const log = p.changelog || [];
        $('#lm-prod-changelog').html(log.length ? log.map(c => {
            const thumb = c.thumbnail
                ? `<img src="${escapeHtml(c.thumbnail)}" alt="" style="width:40px;height:40px;object-fit:contain;border:1px solid #e5e7eb;border-radius:4px">`
                : '<span class="lm-thumb-empty"><i class="fas fa-image"></i></span>';
            return `<tr>
                <td>${thumb}</td>
                <td>${escapeHtml(c.details || '')}</td>
                <td>${escapeHtml(c.changed_at || '—')}</td>
            </tr>`;
        }).join('') : '<tr><td colspan="3" class="text-muted">No changes logged yet.</td></tr>');
    }

    function fillProductModal(p) {
        currentProduct = p;
        currentProductSku = p.sku || '';
        setProductEditMode(false);
        $('#lm-prod-title').text(p.title || p.sku || 'Product');
        renderProductInfo(p);
        renderMasterContent(p.master_content || {});
        applySyncPrefs(p.sync_prefs);
        const images = p.images || p.amazon_images || [];
        if (p.hero_image && images[0] !== p.hero_image) {
            images.unshift(p.hero_image);
        }
        renderProductImages(images);
        renderProductListings(p);
        renderProductExtras(p);
        setProductPane('info');
    }

    function listingManagerProductUrl(sku, edit) {
        const url = new URL("{{ url('/listing-manager') }}", window.location.origin);
        url.searchParams.set('sku', String(sku || '').trim());
        if (edit) url.searchParams.set('edit', '1');
        return url.toString();
    }

    function openProductInNewTab(sku) {
        const s = String(sku || '').trim();
        if (!s) { toast('Missing SKU.', 'error'); return; }
        window.open(listingManagerProductUrl(s, true), '_blank', 'noopener');
    }

    function openProductModal(sku, pane, opts) {
        if (!sku) { toast('Missing SKU.', 'error'); return; }
        currentProductSku = sku;
        currentProduct = null;
        $('#lm-prod-loading').removeClass('d-none').text('Loading product from Amz / Main Store…');
        $('#lm-prod-content').addClass('d-none');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('lmProductModal')).show();
        $.getJSON("{{ route('listing.manager.product') }}", { sku })
            .done(function (res) {
                if (!res || !res.success || !res.product) {
                    $('#lm-prod-loading').text(res?.message || 'Could not load product.');
                    return;
                }
                fillProductModal(res.product);
                if (pane) setProductPane(pane);
                if (opts && opts.edit) setProductEditMode(true);
                $('#lm-prod-loading').addClass('d-none');
                $('#lm-prod-content').removeClass('d-none');
            })
            .fail(function (xhr) {
                $('#lm-prod-loading').text(xhr.responseJSON?.message || 'Failed to load product.');
            });
    }

    function loadProductTypes() {
        $.getJSON("{{ route('listing.manager.product.types') }}", function (res) {
            const $sel = $('#lm-product-type');
            const cur = $sel.val();
            $sel.find('option:not(:first)').remove();
            (res.types || []).forEach(t => $sel.append(`<option value="${escapeHtml(t)}">${escapeHtml(t)}</option>`));
            $sel.val(cur || 'all');
        });
    }

    function syncImagesHidden() {
        $('#lc-images').val(editorImages.join('\n'));
    }

    function getDescriptionValue() {
        if (descMode === 'rich') {
            return String($('#lc-description-rich').html() || '').trim();
        }
        return String($('#lc-description').val() || '');
    }

    function setDescriptionValue(html) {
        $('#lc-description').val(html || '');
        $('#lc-description-rich').html(html || '');
        updateDescGutter();
        if (descMode === 'preview') {
            $('#lc-description-preview').html(html || '<p class="text-muted">Nothing to preview.</p>');
        }
    }

    function updateDescGutter() {
        const text = String($('#lc-description').val() || '');
        const lines = Math.max(1, text.split('\n').length);
        let html = '';
        for (let i = 1; i <= lines; i++) html += i + (i < lines ? '\n' : '');
        $('#lc-desc-gutter').text(html);
    }

    function setDescMode(mode) {
        if (mode === 'fullscreen') {
            $('#lc-desc-wrap').toggleClass('is-fullscreen');
            const $btn = $('#lc-desc-modes [data-desc-mode="fullscreen"] i');
            $btn.toggleClass('fa-expand', !$('#lc-desc-wrap').hasClass('is-fullscreen'));
            $btn.toggleClass('fa-compress', $('#lc-desc-wrap').hasClass('is-fullscreen'));
            return;
        }
        // Sync content between modes
        if (descMode === 'rich' && mode !== 'rich') {
            $('#lc-description').val($('#lc-description-rich').html() || '');
        } else if (descMode !== 'rich' && mode === 'rich') {
            $('#lc-description-rich').html($('#lc-description').val() || '');
        }
        descMode = mode;
        $('#lc-desc-modes [data-desc-mode="code"], #lc-desc-modes [data-desc-mode="preview"], #lc-desc-modes [data-desc-mode="rich"]').removeClass('active');
        $('#lc-desc-modes [data-desc-mode="' + mode + '"]').addClass('active');
        $('#lc-desc-code-row').toggle(mode === 'code');
        $('#lc-description-preview').toggle(mode === 'preview');
        $('#lc-description-rich').toggle(mode === 'rich');
        if (mode === 'preview') {
            $('#lc-description-preview').html(getDescriptionValue() || '<p class="text-muted">Nothing to preview. Click Optimize Description for eBay.</p>');
        }
        if (mode === 'code') updateDescGutter();
        $('#lc-switch-rich-btn').text(mode === 'rich' ? 'Switch to HTML Editor' : 'Switch to Rich Text Editor');
    }

    function isPlaceholderAmazonUrl(url) {
        // Broken ASIN placeholders like .../images/P/B0XXXX._AC_SL500_.jpg
        return /\/images\/P\/[A-Z0-9]{8,}/i.test(String(url || ''));
    }

    function sanitizeEditorImages(list) {
        return (list || []).map(u => String(u || '').trim()).filter(u => u && !isPlaceholderAmazonUrl(u));
    }

    function renderImages() {
        editorImages = sanitizeEditorImages(editorImages);
        syncImagesHidden();
        const $grid = $('#lc-image-preview');
        if (!editorImages.length) {
            $grid.html('<div class="text-muted small">No images yet — click <strong>Load Images From Image Master</strong> or Upload.</div>');
            return;
        }
        $grid.html(editorImages.map((url, i) => `
            <div class="lc-image-card" data-url="${encodeURIComponent(url)}">
                <img src="${escapeHtml(url)}" alt="" loading="lazy"
                     onerror="this.style.display='none'; this.parentElement.classList.add('is-broken'); if(!this.parentElement.querySelector('.lc-img-fail')){ const s=document.createElement('span'); s.className='lc-img-fail'; s.textContent='Image failed to load'; this.parentElement.appendChild(s);}">
                ${i === 0 ? '<span class="primary-tag">Primary</span>' : ''}
                <button type="button" class="lc-img-del" title="Delete">&times;</button>
            </div>
        `).join(''));
        if (imageSortable) imageSortable.destroy();
        if (window.Sortable) {
            imageSortable = Sortable.create($grid[0], {
                animation: 150,
                ghostClass: 'sortable-ghost',
                onEnd: function () {
                    editorImages = $grid.find('.lc-image-card').map(function () {
                        try { return decodeURIComponent($(this).attr('data-url') || ''); } catch (e) { return ''; }
                    }).get().filter(Boolean);
                    dirty = true;
                    renderImages();
                }
            });
        }
    }

    function collectDetails() {
        if ($('.lc-mp-category-manual').is(':visible')) {
            $('#lc-category-id').val($('#lc-category-id-visible').val() || '');
            $('#lc-category-path-input').val($('#lc-category-path-visible').val() || '');
        }
        const images = editorImages.slice();
        const brand = ($('#lc-brand').val() || $('#lc-brand-id').val() || '').trim() || '5 Core Inc.';
        const manufacturer = ($('#lc-manufacturer').val() || $('#lc-manufacturer-specific').val() || '').trim() || '5 Core Inc.';
        const mpn = ($('#lc-mpn').val() || '').trim() || ($('#lc-sku').val() || '').trim();
        const upc = ($('#lc-upc').val() || $('#lc-upc-specific').val() || '').trim();
        const specifics = {
            Brand: brand,
            Manufacturer: manufacturer,
            MPN: mpn,
            UPC: upc,
            'Speaker Size': $('#lc-spec-speaker').val() || '',
            'Voice Coil': $('#lc-spec-coil').val() || '',
            'RMS Power': $('#lc-spec-rms').val() || '',
        };
        return {
            description: getDescriptionValue(),
            condition: $('#lc-condition').val() || '',
            condition_description: $('#lc-condition-desc').val() || '',
            brand,
            manufacturer,
            mpn,
            upc,
            ean: $('#lc-ean').val() || '',
            isbn: $('#lc-isbn').val() || '',
            epid: $('#lc-epid').val() || '',
            primary_category_id: $('#lc-category-id').val() || '',
            primary_category_path: $('#lc-category-path-input').val() || '',
            secondary_category_id: $('#lc-secondary-category-id').val() || '',
            listing_format: $('input[name="lc-format"]:checked').val() || 'FixedPriceItem',
            duration: $('#lc-duration').val() || 'GTC',
            image_url: images[0] || '',
            images,
            item_specifics: specifics,
            location_city: $('#lc-location-city').val() || '',
            location_country: $('#lc-location-country').val() || '',
            location_postal_code: $('#lc-location-postal').val() || '',
            package_length: $('#lc-pkg-l').val() || '',
            package_width: $('#lc-pkg-w').val() || '',
            package_height: $('#lc-pkg-h').val() || '',
            package_weight_lb: $('#lc-pkg-lb').val() || '',
            package_weight_oz: $('#lc-pkg-oz').val() || '',
            shipping_policy_id: $('#lc-shipping-policy').val() || '',
            payment_policy_id: $('#lc-payment-policy').val() || '',
            return_policy_id: $('#lc-return-policy').val() || '',
            vat_percent: $('#lc-vat').val() || '',
            gallery_plus: $('#lc-gallery-plus').is(':checked'),
            best_offer: $('#lc-best-offer').is(':checked'),
            auto_relist: $('#lc-auto-relist').is(':checked'),
            private_listing: $('#lc-private-listing').is(':checked'),
            warehouse_id: $('#lc-warehouse-id').val() || '',
            make: $('#lc-reverb-make').val() || '',
            model: $('#lc-reverb-model').val() || '',
            finish: $('#lc-reverb-finish').val() || '',
            year: $('#lc-reverb-year').val() || '',
            condition_name: $('#lc-reverb-condition').val()
                ? String($('#lc-reverb-condition option:selected').data('name') || $('#lc-reverb-condition option:selected').text() || '').trim()
                : '',
            condition_uuid: $('#lc-reverb-condition').val() || '',
            category_uuid: $('#lc-category-id').val() || '',
            category_name: $('#lc-category-path-input').val() || '',
            upc_does_not_apply: $('#lc-upc-does-not-apply').is(':checked'),
            handmade: $('#lc-reverb-handmade').is(':checked'),
            offers_enabled: $('#lc-reverb-offers').is(':checked'),
            has_inventory: $('#lc-has-inventory').is(':checked'),
            local_pickup_only: $('#lc-reverb-local-pickup').is(':checked'),
            shipping_profile_id: $('#lc-reverb-shipping-profile').val() || '',
            shipping_rates: parseReverbShippingRates($('#lc-reverb-shipping-rates').val()),
            videos: parseReverbVideoList($('#lc-reverb-videos').val()),
            price_currency: $('#lc-price-currency').val() || 'USD',
            bullet_1: ($('#lc-bullets').val() || '').split(/\r?\n/).map(s => s.trim()).filter(Boolean)[0] || '',
            bullet_2: ($('#lc-bullets').val() || '').split(/\r?\n/).map(s => s.trim()).filter(Boolean)[1] || '',
            bullet_3: ($('#lc-bullets').val() || '').split(/\r?\n/).map(s => s.trim()).filter(Boolean)[2] || '',
            bullet_4: ($('#lc-bullets').val() || '').split(/\r?\n/).map(s => s.trim()).filter(Boolean)[3] || '',
            bullet_5: ($('#lc-bullets').val() || '').split(/\r?\n/).map(s => s.trim()).filter(Boolean)[4] || '',
        };
    }

    function applyMasterPayload(res) {
        if (res.draft) currentDraft = res.draft;
        if (res.title) $('#lc-title').val(res.title);
        if (res.description) {
            setDescriptionValue(res.description);
            setDescMode('preview');
        }
        if (Array.isArray(res.images) && res.images.length) {
            editorImages = sanitizeEditorImages(res.images);
        }
        if (Array.isArray(res.videos) && res.videos.length) {
            $('#lc-reverb-videos').val(res.videos.join('\n'));
        }
        if (Array.isArray(res.bullets) && res.bullets.length) {
            $('#lc-bullets').val(res.bullets.join('\n'));
        }
        if (res.upc) $('#lc-upc, #lc-upc-specific').val(res.upc);
        if (res.brand) $('#lc-brand, #lc-brand-id').val(res.brand);
        if (res.manufacturer) $('#lc-manufacturer, #lc-manufacturer-specific').val(res.manufacturer);
        if (res.price != null && res.price !== '') $('#lc-price').val(res.price);
        if (res.quantity != null && res.quantity !== '') $('#lc-qty').val(res.quantity);
        if (res.make) $('#lc-reverb-make').val(res.make);
        if (res.model) $('#lc-reverb-model').val(res.model);
        if (res.finish) $('#lc-reverb-finish').val(res.finish);
        if (res.year) $('#lc-reverb-year').val(res.year);
        if (res.condition_name) fillReverbConditions([], res.condition_name);
        if (res.shipping_profile_id) $('#lc-reverb-shipping-profile').val(res.shipping_profile_id);
        if (res.package_length != null) $('#lc-pkg-l').val(res.package_length);
        if (res.package_width != null) $('#lc-pkg-w').val(res.package_width);
        if (res.package_height != null) $('#lc-pkg-h').val(res.package_height);
        if (res.package_weight_lb != null) $('#lc-pkg-lb').val(res.package_weight_lb);
        if (res.package_weight_oz != null) $('#lc-pkg-oz').val(res.package_weight_oz);
        dirty = true;
        refreshEditorUi(currentDraft);
    }

    function parseReverbVideoList(raw) {
        return String(raw || '').split(/\r?\n/).map(s => s.trim()).filter(Boolean);
    }

    function parseReverbShippingRates(raw) {
        const text = String(raw || '').trim();
        if (!text) return [];
        try {
            const parsed = JSON.parse(text);
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }

    function clientReady() {
        const d = collectDetails();
        const title = String($('#lc-title').val() || '').trim();
        const desc = String(getDescriptionValue() || d.description || '').trim();
        const price = parseFloat($('#lc-price').val());
        const qty = $('#lc-qty').val();
        const errors = { title: [], images: [], pricing: [], category: [], policies: [] };
        if (!title) errors.title.push('Title');
        else if (title.length > titleLimit) errors.title.push('Title length');
        if (!desc) errors.title.push('Description');
        else if (desc.length > descLimit) errors.title.push('Description length');
        if (!d.images.length) errors.images.push('Images');
        if (!(price > 0)) errors.pricing.push('Price');
        if (qty === '' || qty === null) errors.pricing.push('Quantity');
        const channel = String((currentDraft && currentDraft.channel) || '').toLowerCase();
        const isEbay = /ebay/.test(channel);
        if (isEbay) {
            if (!d.primary_category_id) errors.category.push('Category');
            if (!d.condition) errors.category.push('Condition');
            if (!d.shipping_policy_id) errors.policies.push('Shipping');
            if (!d.payment_policy_id) errors.policies.push('Payment');
            if (!d.return_policy_id) errors.policies.push('Return');
            if (!d.location_country) errors.policies.push('Country');
            if (!d.location_postal_code) errors.policies.push('Postal');
        }
        const isTiktok = /tiktok/.test(channel);
        const isTemu = /temu/.test(channel);
        const isReverb = ((currentDraft && currentDraft.editor && currentDraft.editor.family) === 'reverb') || /reverb/.test(channel);
        if (isReverb) {
            if (!d.primary_category_id) errors.category.push('Category');
            if (!String(d.make || '').trim()) errors.category.push('Make');
            if (!String(d.model || '').trim()) errors.category.push('Model');
            if (!String(d.condition_name || '').trim() && !String(d.condition_uuid || '').trim()) errors.category.push('Condition');
            const hasShip = String(d.shipping_profile_id || '').trim()
                || (Array.isArray(d.shipping_rates) && d.shipping_rates.length)
                || !!d.local_pickup_only;
            if (!hasShip) errors.policies.push('Shipping');
        }
        if (isTiktok || isTemu) {
            if (!d.primary_category_id) errors.category.push('Category');
            else if (isTiktok && !/^\d+$/.test(String(d.primary_category_id))) errors.category.push('Category');
            if (!String(d.package_weight_lb || '').trim() && !String(d.package_weight_oz || '').trim()) {
                errors.policies.push('Weight');
            } else if (!((parseFloat(d.package_weight_lb) || 0) + ((parseFloat(d.package_weight_oz) || 0) / 16) > 0)) {
                errors.policies.push('Weight');
            }
        }
        return errors;
    }

    function refreshEditorUi(serverDraft) {
        const title = String($('#lc-title').val() || '');
        const desc = getDescriptionValue();
        $('#lc-title-count')
            .text(`Characters: ${title.length}/${titleLimit}`)
            .toggleClass('lc-char-warn', title.length > titleLimit)
            .toggleClass('lc-char-ok', title.length <= titleLimit);
        $('#lc-desc-count')
            .text(`Characters: ${desc.length}/${descLimit}`)
            .toggleClass('lc-char-warn', desc.length > descLimit)
            .toggleClass('lc-char-ok', desc.length <= descLimit);
        updateDescGutter();

        renderImages();

        const path = $('#lc-category-path-input').val() || '';
        const catId = String($('#lc-category-id').val() || '').trim();
        $('#lc-category-path').text(path || (catId ? catId : 'Select a category'));
        $('#lc-category-id-chip').text(catId ? ((/^\d+$/.test(catId) ? ('ID '+catId) : catId)) : '');
        $('#lc-category-suggested').toggle(!!path);

        const err = clientReady();
        const tabMap = { title: 'title', images: 'images', pricing: 'pricing', category: 'category', policies: 'policies' };
        $('#lc-tabs .lc-tab').each(function () {
            const pane = $(this).data('pane');
            $(this).find('.lc-err').remove();
            const key = Object.keys(tabMap).find(k => tabMap[k] === pane);
            if (key && err[key] && err[key].length) $(this).append('<span class="lc-err">!</span>');
        });

        const family = (serverDraft && serverDraft.editor && serverDraft.editor.family) || '';
        const banners = [];
        if (err.category.length) banners.push(['danger', (family === 'tiktok' ? 'TikTok Category' : (family === 'temu' ? 'Temu Category' : (family === 'reverb' ? 'Reverb Details' : 'Category'))) + ' tab is missing required information. Please fill in those required fields.']);
        if (err.policies.length && family === 'ebay') banners.push(['danger', 'Business Policies tab is missing required information. Please fill in those required fields.']);
        if (err.policies.length && family === 'tiktok') banners.push(['danger', 'Warehouse & Package tab is missing required information. Please fill in those required fields.']);
        if (err.policies.length && family === 'temu') banners.push(['danger', 'Package tab is missing required information. Please fill in those required fields.']);
        if (err.policies.length && family === 'reverb') banners.push(['danger', 'Shipping & Package tab is missing required information. Please fill in those required fields.']);
        if (err.title.length) banners.push(['danger', 'Title & Description tab is missing required information. Please fill in those required fields.']);
        if (err.images.length) banners.push(['danger', 'Images tab is missing required information. Please fill in those required fields.']);
        if (err.pricing.length) banners.push(['danger', 'Price & Stock tab is missing required information. Please fill in those required fields.']);
        if (!(serverDraft && serverDraft.status === 'listed')) {
            const ch = escapeHtml((serverDraft && serverDraft.channel) || 'channel');
            banners.push(['info', `This is a draft listing and not yet published to ${ch}.`]);
        }
        if (family === 'ebay' && !String($('#lc-shipping-policy').val() || '').trim()) {
            banners.push(['warn', 'To use business policies, your account must be authorized by eBay. Click Refresh list of policies after creating them on eBay.']);
        }
        $('#lc-banners').html(banners.map(([t, m]) => `<div class="lc-banner lc-banner-${t}">${escapeHtml(m)}</div>`).join(''));

        const catOk = catId !== '' && (family !== 'tiktok' || /^\d+$/.test(catId));
        $('#lc-category-id-warn').toggleClass('d-none', !['ebay', 'tiktok', 'temu', 'reverb'].includes(family) || catOk);
        $('#lc-reverb-condition-warn').toggleClass('d-none', family !== 'reverb' || !!$('#lc-reverb-condition').val());
        $('#lc-condition-warn').toggleClass('d-none', family !== 'ebay' || !!$('#lc-condition').val());
        $('#lc-shipping-warn').toggle(family === 'ebay' && !$('#lc-shipping-policy').val());
        $('#lc-payment-warn').toggle(family === 'ebay' && !$('#lc-payment-policy').val());
        $('#lc-return-warn').toggle(family === 'ebay' && !$('#lc-return-policy').val());

        const ready = !Object.values(err).some(a => a.length);
        const listed = serverDraft && serverDraft.status === 'listed';
        $('#lc-publish-btn, #lc-save-close-btn, #lc-save-btn').prop('disabled', !!listed);
        if (!ready) $('#lc-publish-btn').prop('disabled', true);
        if (listed) {
            $('#lc-publish-btn').prop('disabled', true).html('<i class="fas fa-check me-1"></i>Published');
        } else {
            const ch = (serverDraft && serverDraft.channel) ? ` to ${serverDraft.channel}` : '';
            $('#lc-publish-btn').html(`<i class="fas fa-cloud-upload-alt me-1"></i>Save &amp; Publish${ch ? escapeHtml(ch) : ''}`);
        }
        renderFamilyRows(serverDraft);
    }

    function fillPolicySelect($el, rows, selected) {
        const opts = ['<option value="">Please select</option>'].concat(
            (rows || []).map(r => {
                const id = String(r.id || '');
                const name = String(r.name || id);
                const label = name.includes(id) ? name : `${name} (${id})`;
                return `<option value="${escapeHtml(id)}" ${selected && String(selected) === id ? 'selected' : ''}>${escapeHtml(label)}</option>`;
            })
        );
        $el.html(opts.join(''));
        if (selected) $el.val(String(selected));
    }

    function loadPolicies(selected) {
        return $.getJSON("{{ route('listing.manager.ebay.policies') }}").then(function (res) {
            policyDefaults = res.defaults || {};
            const sel = selected || {};
            fillPolicySelect($('#lc-shipping-policy'), res.shipping || [], sel.shipping_policy_id || policyDefaults.shipping_policy_id);
            fillPolicySelect($('#lc-payment-policy'), res.payment || [], sel.payment_policy_id || policyDefaults.payment_policy_id);
            fillPolicySelect($('#lc-return-policy'), res.return || [], sel.return_policy_id || policyDefaults.return_policy_id);
            return res;
        }).fail(function () {
            fillPolicySelect($('#lc-shipping-policy'), [], selected?.shipping_policy_id);
            fillPolicySelect($('#lc-payment-policy'), [{ id: '307554145021', name: 'eBay Managed Payments (307554145021)' }], selected?.payment_policy_id || '307554145021');
            fillPolicySelect($('#lc-return-policy'), [{ id: '329818346021', name: '30 days money back (329818346021)' }], selected?.return_policy_id || '329818346021');
        });
    }

    function fillReverbConditions(rows, selected) {
        const $el = $('#lc-reverb-condition');
        if (!$el.length) return;
        const current = selected
            || $el.val()
            || (currentDraft && currentDraft.listing_details && (currentDraft.listing_details.condition_uuid || currentDraft.listing_details.condition_name))
            || '';
        if (Array.isArray(rows) && rows.length) {
            const opts = ['<option value="">Please select</option>'];
            rows.forEach(function (r) {
                const id = String(r.id || r.name || '');
                const name = String(r.name || id);
                opts.push('<option value="' + escapeHtml(id) + '" data-name="' + escapeHtml(name) + '">' + escapeHtml(name) + '</option>');
            });
            $el.html(opts.join(''));
        }
        if (!current) return;
        const match = $el.find('option').filter(function () {
            const name = String($(this).data('name') || $(this).text() || '').toLowerCase();
            return this.value === String(current) || name === String(current).toLowerCase();
        }).first();
        if (match.length) {
            $el.val(match.val());
        } else if (!$el.find('option[value="' + String(current).replace(/"/g, '') + '"]').length) {
            $el.append('<option value="' + escapeHtml(String(current)) + '" data-name="' + escapeHtml(String(current)) + '" selected>' + escapeHtml(String(current)) + '</option>');
        }
    }

    function searchCategories(q) {
        const $box = $('#lc-category-results');
        const family = (currentDraft && currentDraft.editor && currentDraft.editor.family) || '';
        const channel = (currentDraft && currentDraft.channel) || '';
        const title = String($('#lc-title').val() || (currentDraft && currentDraft.title) || '').trim();
        if (family !== 'tiktok' && family !== 'reverb' && (!q || q.length < 2)) {
            $box.html('<div class="text-muted small p-3">Type a keyword to search marketplace categories.</div>');
            return;
        }
        if (family === 'tiktok' && !q && !title) {
            $box.html('<div class="text-muted small p-3">Type a keyword such as speaker, or keep the product title to load TikTok suggestions.</div>');
            return;
        }
        const searchingLabel = family === 'tiktok'
            ? 'Searching TikTok Shop categories…'
            : (family === 'reverb' ? 'Searching Reverb categories…' : 'Searching…');
        $box.html('<div class="text-muted small p-3">' + searchingLabel + '</div>');
        const desc = String(getDescriptionValue() || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 500);
        if (window._lcCatXhr && window._lcCatXhr.abort) {
            window._lcCatXhr.abort();
        }
        window._lcCatXhr = $.ajax({
            url: "{{ route('listing.manager.ebay.categories') }}",
            method: family === 'tiktok' ? 'POST' : 'GET',
            data: { q, channel, title, description: desc },
            dataType: 'json',
            timeout: 25000,
            success: function (res) {
                if (family === 'reverb' && Array.isArray(res.conditions)) {
                    fillReverbConditions(res.conditions);
                }
                const rows = res.categories || [];
                if (!rows.length) {
                    $box.html(`<div class="text-muted small p-3">${escapeHtml(res.message || 'No categories found.')}</div>`);
                    return;
                }
                $box.html(rows.map(r => `
                    <button type="button" class="lc-cat-item" data-id="${escapeHtml(r.id)}" data-path="${escapeHtml(r.path)}">
                        ${escapeHtml(r.path)}
                        ${r.suggested ? '<span class="lc-suggested">Suggested</span>' : ''}
                        ${r.restricted ? '<span class="badge bg-secondary ms-1">Restricted</span>' : ''}
                    </button>
                `).join(''));
            },
            error: function (xhr, status) {
                if (status === 'abort') return;
                const msg = (xhr.responseJSON && xhr.responseJSON.message)
                    || (status === 'timeout' ? 'TikTok category search timed out. Try again.' : 'Category search failed.');
                $box.html(`<div class="text-danger small p-3">${escapeHtml(msg)}</div>`);
            }
        });
    }

    function applyEditorProfile(draft, pane) {
        const ed = (draft && draft.editor) || { family: 'default', tabs: [] };
        const tabIds = (ed.tabs || []).map(t => t.id);
        $('#lmListingEditorModal').attr('data-mp-family', ed.family || 'default');
        $('#lc-tabs .lc-tab').each(function () {
            const paneId = String($(this).data('pane') || '');
            const spec = (ed.tabs || []).find(t => t.id === paneId);
            this.hidden = !spec;
            if (spec && spec.label) {
                const $err = $(this).find('.lc-err').detach();
                $(this).text(spec.label);
                if ($err.length) $(this).append($err);
            }
        });
        const fields = ed.identifier_fields || ['sku', 'asin', 'brand', 'manufacturer', 'upc'];
        $('[data-id-field]').each(function () {
            $(this).toggle(fields.includes(String($(this).data('id-field'))));
        });
        $('.lc-ebay-only').toggleClass('d-none', !ed.ebay);
        $('.lc-tiktok-only').toggleClass('d-none', !ed.tiktok);
        $('.lc-temu-only').toggleClass('d-none', !ed.temu);
        $('.lc-reverb-only').toggleClass('d-none', !ed.reverb);
        $('.lc-mp-category-manual').toggleClass('d-none', !ed.temu);
        $('.lc-mp-category-search').toggleClass('d-none', !(ed.ebay || ed.tiktok || ed.reverb));
        $('.lc-mp-category-selected').toggleClass('d-none', !(ed.ebay || ed.tiktok || ed.temu || ed.reverb));
        $('.lc-weight-req').toggle(!!(ed.tiktok || ed.temu));
        $('#lc-asin-label').text(ed.ebay ? 'ASIN / Source' : 'Source ASIN');
        $('#lc-category-heading').text(ed.tiktok ? 'TikTok Category' : (ed.temu ? 'Temu Category' : (ed.reverb ? 'Reverb Category' : 'Category')));
        $('#lc-category-id-visible').attr('placeholder', ed.category_placeholder || 'Category ID');
        $('#lc-category-search').attr('placeholder', ed.category_placeholder || 'Search categories');
        $('#lc-optimize-desc-label').text(ed.optimize_label || 'Optimize Description');
        $('#lc-policies-title').text(
            ed.ebay ? 'Business Policies' : (ed.tiktok ? 'Warehouse & Package' : (ed.temu ? 'Package' : (ed.reverb ? 'Shipping & Package' : 'Shipping')))
        );
        $('#lc-pricing-heading').text(ed.pricing_title || 'Pricing');
        $('#lc-title-heading').text(ed.title_heading || 'Title & Description');
        $('#lc-identifier-help').text(ed.identifier_help || '').toggle(!!ed.identifier_help);
        $('#lc-images-help').text(ed.images_help || 'Drag images to reorder. First image is Primary. Use Load Images From Image Master.');
        $('#lc-category-help').text(ed.category_help || '').toggle(!!ed.category_help);
        $('#lc-policies-help').text(ed.policies_help || '').toggle(!!ed.policies_help);
        const chLabel = (draft && draft.channel) || ed.label || '';
        if (chLabel) {
            $('#lc-editor-channel-badge').text(chLabel).show();
        } else {
            $('#lc-editor-channel-badge').hide();
        }
        let firstPane = tabIds[0] || 'identifiers';
        if (pane && tabIds.includes(pane)) firstPane = pane;
        $('#lc-tabs .lc-tab').removeClass('active').filter('[data-pane="' + firstPane + '"]').addClass('active');
        $('#lmListingEditorModal .lc-pane').removeClass('active').filter('[data-pane="' + firstPane + '"]').addClass('active');
    }

    function fillEditor(draft, pane) {
        currentDraft = draft;
        dirty = false;
        applyEditorProfile(draft, pane);
        const d = draft.listing_details || {};
        const snap = draft.amazon_snapshot || {};
        titleLimit = Number((draft.limits && draft.limits.title) || 80);
        descLimit = Number((draft.limits && draft.limits.description) || 500000);
        $('#lc-draft-id').val(draft.id);
        $('#lc-editor-title').text(draft.title || draft.sku || 'Listing');
        $('#lc-sku').val(draft.sku || '');
        $('#lc-asin').val(draft.asin || snap.asin || '');
        const sku = String(draft.sku || '').trim();
        const defaultBrand = '5 Core Inc.';
        const defaultManufacturer = '5 Core Inc.';
        const upcVal = d.upc || (d.item_specifics && d.item_specifics.UPC) || snap.upc || '';
        $('#lc-upc').val(upcVal);
        $('#lc-ean').val(d.ean || '');
        $('#lc-isbn').val(d.isbn || '');
        $('#lc-epid').val(d.epid || '');
        $('#lc-title').val(draft.title || snap.item_name || snap.title || '');
        setDescriptionValue(d.description || snap.product_description || '');
        const loadedBullets = [d.bullet_1, d.bullet_2, d.bullet_3, d.bullet_4, d.bullet_5]
            .map(b => String(b || '').trim()).filter(Boolean);
        $('#lc-bullets').val(loadedBullets.join('\n'));
        setDescMode('code');
        const snapImages = Array.isArray(snap.images) ? snap.images : [];
        editorImages = sanitizeEditorImages(
            Array.isArray(d.images) && d.images.length ? d.images.slice() : (d.image_url ? [d.image_url] : snapImages.slice())
        );
        if (!editorImages.length && draft.thumbnail && !isPlaceholderAmazonUrl(draft.thumbnail)) {
            editorImages = [draft.thumbnail];
        }
        if (!editorImages.length && snap.thumbnail_image && !isPlaceholderAmazonUrl(snap.thumbnail_image)) {
            editorImages = [snap.thumbnail_image];
        }
        $('#lc-gallery-plus').prop('checked', !!d.gallery_plus);
        $('#lc-price').val(draft.price != null ? draft.price : '');
        $('#lc-qty').val(draft.quantity != null ? draft.quantity : '');
        $('#lc-best-offer').prop('checked', !!d.best_offer);
        $('#lc-category-id').val(d.primary_category_id || d.category_uuid || '');
        $('#lc-category-path-input').val(d.primary_category_path || d.category_name || d.category || '');
        $('#lc-secondary-category-id').val(d.secondary_category_id || '');
        $('#lc-condition').val(d.condition || 'New');
        $('#lc-condition-desc').val(d.condition_description || '');
        $('#lc-duration').val(d.duration || 'GTC');
        $(`input[name="lc-format"][value="${d.listing_format || 'FixedPriceItem'}"]`).prop('checked', true);
        $('#lc-private-listing').prop('checked', !!d.private_listing);
        const specs = d.item_specifics || {};
        $('#lc-brand, #lc-brand-id').val(d.brand || specs.Brand || defaultBrand);
        $('#lc-manufacturer, #lc-manufacturer-specific').val(d.manufacturer || specs.Manufacturer || defaultManufacturer);
        $('#lc-mpn').val(d.mpn || specs.MPN || sku);
        $('#lc-brand, #lc-brand-id').off('input.brandSync').on('input.brandSync', function () {
            $('#lc-brand, #lc-brand-id').not(this).val($(this).val());
        });
        $('#lc-manufacturer, #lc-manufacturer-specific').off('input.mfrSync').on('input.mfrSync', function () {
            $('#lc-manufacturer, #lc-manufacturer-specific').not(this).val($(this).val());
        });
        $('#lc-upc-specific').val(upcVal);
        // Keep Product Identifiers UPC and Required Item Specifics UPC in sync
        $('#lc-upc, #lc-upc-specific').off('input.upcSync').on('input.upcSync', function () {
            const v = $(this).val();
            $('#lc-upc, #lc-upc-specific').not(this).val(v);
        });
        $('#lc-spec-speaker').val(specs['Speaker Size'] || '');
        $('#lc-spec-coil').val(specs['Voice Coil'] || '');
        $('#lc-spec-rms').val(specs['RMS Power'] || '');
        $('#lc-location-city').val(d.location_city || policyDefaults.location_city || 'Bellefontaine');
        $('#lc-location-country').val(d.location_country || policyDefaults.location_country || 'US');
        $('#lc-location-postal').val(d.location_postal_code || policyDefaults.location_postal_code || '43311');
        $('#lc-pkg-l').val(d.package_length ?? '');
        $('#lc-pkg-w').val(d.package_width ?? '');
        $('#lc-pkg-h').val(d.package_height ?? '');
        $('#lc-pkg-lb').val(d.package_weight_lb ?? '');
        $('#lc-pkg-oz').val(d.package_weight_oz ?? '');
        $('#lc-vat').val(d.vat_percent || '');
        $('#lc-auto-relist').prop('checked', !!d.auto_relist);
        $('#lc-warehouse-id').val(d.warehouse_id || '');
        $('#lc-category-id-visible').val(d.primary_category_id || d.category_uuid || '');
        $('#lc-category-path-visible').val(d.primary_category_path || d.category_name || d.category || '');
        if (d.category_uuid && !$('#lc-category-id').val()) {
            $('#lc-category-id').val(d.category_uuid);
        }
        $('#lc-reverb-make').val(d.make || '');
        $('#lc-reverb-model').val(d.model || '');
        $('#lc-reverb-finish').val(d.finish || '');
        $('#lc-reverb-year').val(d.year || '');
        $('#lc-upc-does-not-apply').prop('checked', !!d.upc_does_not_apply);
        $('#lc-reverb-handmade').prop('checked', !!d.handmade);
        $('#lc-reverb-offers').prop('checked', d.offers_enabled !== false);
        $('#lc-has-inventory').prop('checked', d.has_inventory !== false);
        $('#lc-reverb-local-pickup').prop('checked', !!d.local_pickup_only);
        $('#lc-reverb-shipping-profile').val(d.shipping_profile_id || '');
        $('#lc-price-currency').val(d.price_currency || 'USD');
        $('#lc-reverb-videos').val(Array.isArray(d.videos) ? d.videos.join('\n') : '');
        $('#lc-reverb-shipping-rates').val(Array.isArray(d.shipping_rates) && d.shipping_rates.length
            ? JSON.stringify(d.shipping_rates, null, 2)
            : (typeof d.shipping_rates === 'string' ? d.shipping_rates : ''));
        if (d.condition_uuid || d.condition_name) {
            fillReverbConditions([], d.condition_uuid || d.condition_name);
        }
        const finish = function () {
            $('#lc-editor-loading').hide();
            $('#lc-editor-body').show();
            refreshEditorUi(draft);
            const family = (draft.editor && draft.editor.family) || '';
            if ((family === 'tiktok' || family === 'reverb') && !String($('#lc-category-id').val() || '').trim()) {
                searchCategories(family === 'reverb' ? String($('#lc-title').val() || '').trim() : '');
            } else if (family === 'reverb') {
                searchCategories(String($('#lc-category-search').val() || $('#lc-title').val() || '').trim());
            }
            if (!editorImages.length && draft.id) {
                $.ajax({
                    url: "{{ url('/listing-manager/drafts') }}/" + draft.id + '/load-images',
                    method: 'POST',
                    timeout: 20000,
                }).done(function (res) {
                    const imgs = sanitizeEditorImages(res.images || []);
                    if (!imgs.length) return;
                    editorImages = imgs;
                    if (res.draft) currentDraft = res.draft;
                    refreshEditorUi(currentDraft);
                });
            }
        };
        if (draft.editor && draft.editor.ebay) {
            loadPolicies({
                shipping_policy_id: d.shipping_policy_id,
                payment_policy_id: d.payment_policy_id,
                return_policy_id: d.return_policy_id,
            }).always(finish);
        } else {
            finish();
        }
    }

    function openEditor(id, pane) {
        if (!id) { toast('Could not load draft.', 'error'); return; }
        $('#lc-editor-loading').html('<i class="fas fa-spinner fa-spin me-2"></i>Loading product details…').show();
        $('#lc-editor-body').hide();
        $('#lc-editor-title').text('Loading…');
        $('#lc-editor-channel-badge').hide();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('lmListingEditorModal')).show();
        $.getJSON("{{ url('/listing-manager/drafts') }}/" + id, function (res) {
            if (!res.success || !res.draft) {
                $('#lc-editor-loading').text('Could not load draft.');
                toast('Could not load draft.', 'error');
                return;
            }
            fillEditor(res.draft, pane);
        }).fail(function (xhr) {
            $('#lc-editor-loading').text(xhr.responseJSON?.message || 'Could not load draft.');
            toast(xhr.responseJSON?.message || 'Could not load draft.', 'error');
        });
    }

    function renderFamilyRows(draft) {
        const family = (draft && draft.family) || {};
        const kids = (draft && draft.variations && draft.variations.length) ? draft.variations : (family.children || []);
        $('#lc-family-parent').text(family.parent || (draft && draft.sku) || '—');
        $('#lc-family-rows').html(kids.length ? kids.map(v => `<tr class="${v.is_current ? 'lm-var-current' : ''}">
            <td>${escapeHtml(v.sku || '')}</td>
            <td><span class="lm-var-label">${escapeHtml(v.variation_label || '')}</span></td>
            <td>${escapeHtml(v.title || '')}</td>
            <td>${escapeHtml(v.asin || '—')}</td>
            <td>${v.quantity != null ? escapeHtml(String(v.quantity)) : '—'}</td>
            <td>${money(v.price)}</td>
        </tr>`).join('') : '<tr><td colspan="6" class="text-muted">This SKU has no siblings on the same Product Master parent.</td></tr>');
    }

    function copyDraftToSiblings(id) {
        if (!id) return;
        const $btn = $('#lc-copy-siblings-btn').prop('disabled', true);
        $.ajax({
            url: "{{ url('/listing-manager/drafts') }}/" + id + '/copy-siblings',
            method: 'POST',
            success: function (res) {
                toast(res.message || 'Copied to siblings.', 'success');
                if (res.draft) fillEditor(res.draft);
                loadDrafts();
            },
            error: xhr => toast(xhr.responseJSON?.message || 'Copy to siblings failed.', 'error'),
            complete: () => $btn.prop('disabled', false),
        });
    }

    function saveDraft(opts) {
        opts = opts || {};
        const id = $('#lc-draft-id').val();
        if (!id) return $.Deferred().reject().promise();
        const payload = {
            title: $('#lc-title').val(),
            price: $('#lc-price').val(),
            quantity: $('#lc-qty').val(),
            listing_details: collectDetails(),
        };
        return $.ajax({
            url: "{{ url('/listing-manager/drafts') }}/" + id,
            method: 'PUT',
            data: payload,
        }).then(function (res) {
            if (res.draft) {
                currentDraft = res.draft;
                fillEditor(res.draft);
            }
            dirty = false;
            if (!opts.silent) toast(res.message || 'Saved.', 'success');
            loadDrafts();
            return res;
        }, function (xhr) {
            toast(xhr.responseJSON?.message || 'Save failed.', 'error');
            throw xhr;
        });
    }

    function publishDraft() {
        const id = $('#lc-draft-id').val();
        if (!id) return;
        const $btn = $('#lc-publish-btn');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Publishing…');
        saveDraft({ silent: true }).then(function () {
            return $.ajax({
                url: "{{ url('/listing-manager/drafts') }}/" + id + '/publish',
                method: 'POST',
            });
        }).then(function (res) {
            toast(res.message || 'Published.', 'success');
            if (res.draft) fillEditor(res.draft);
            loadDrafts();
            if (res.success) {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('lmListingEditorModal')).hide();
            }
        }).fail(function (xhr) {
            const res = xhr.responseJSON || {};
            toast(res.message || 'Publish failed.', 'error');
            if (res.draft) fillEditor(res.draft);
        }).always(function () {
            refreshEditorUi(currentDraft);
        });
    }

    $(function () {
        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': csrf } });

        table = new Tabulator('#lm-products-table', {
            layout: 'fitColumns',
            height: Math.max(360, window.innerHeight - 280),
            placeholder: 'No listings found. Click Import from Amz.',
            selectableRows: true,
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [50, 100, 250, 500],
            ajaxURL: "{{ route('listing.manager.data') }}",
            ajaxParams: buildProductQuery,
            ajaxResponse: function (_u, _p, response) {
                const meta = (response && response.meta) || {};
                $('#lm-all-count').text(Number(meta.total || 0).toLocaleString());
                $('#lm-in-stock-count').text(Number(meta.in_stock || 0).toLocaleString());
                $('#lm-out-stock-count').text(Number(meta.out_of_stock || 0).toLocaleString());
                return (response && response.data) ? response.data : [];
            },
            columns: [
                { formatter: 'rowSelection', titleFormatter: 'rowSelection', hozAlign: 'center', headerSort: false, width: 44 },
                {
                    title: '', field: 'thumbnail', width: 44, hozAlign: 'center', headerSort: false,
                    formatter: (cell) => {
                        const src = cell.getValue();
                        if (!src) return '<span class="lm-thumb-empty"><i class="fas fa-image"></i></span>';
                        return `<img class="lm-thumb" src="${escapeHtml(src)}" alt="">`;
                    }
                },
                {
                    title: 'Hero Image', field: 'hero_image', width: 88, hozAlign: 'center', headerSort: false,
                    formatter: (cell) => {
                        const src = cell.getValue();
                        if (!src) return '<span class="lm-thumb-empty" title="No Image Master / Images tab photo"><i class="fas fa-image"></i></span>';
                        return `<span class="lm-hero-wrap" title="Open Images tab"><img class="lm-thumb" src="${escapeHtml(src)}" alt="Hero"></span>`;
                    },
                    cellClick: (e, cell) => {
                        const sku = String(cell.getRow().getData().sku || '').trim();
                        if (sku) openProductModal(sku, 'images');
                    }
                },
                { title: 'SKU', field: 'sku', minWidth: 140 },
                {
                    title: '', field: '_magnify', width: 42, hozAlign: 'center', headerSort: false,
                    formatter: () => '<button type="button" class="btn btn-link btn-sm p-0 lm-sku-magnify" title="Open editor in new tab"><i class="fas fa-search"></i></button>',
                    cellClick: (e, cell) => {
                        e.preventDefault();
                        e.stopPropagation();
                        openProductInNewTab(cell.getRow().getData().sku);
                    }
                },
                {
                    title: 'Name', field: 'name', minWidth: 220, widthGrow: 4,
                    formatter: (cell) => {
                        const row = cell.getRow().getData();
                        const name = escapeHtml(cell.getValue() || '');
                        const sku = escapeHtml(String(row.sku || '').trim());
                        if (!sku) return name;
                        return `<a href="#" class="lm-name-link lm-open-product" data-sku="${sku}" title="${name}">${name}</a>`;
                    },
                    cellClick: (e, cell) => {
                        const a = e.target.closest('.lm-open-product');
                        if (!a) return;
                        e.preventDefault();
                        e.stopPropagation();
                        openProductModal(String(cell.getRow().getData().sku || '').trim());
                    }
                },
                { title: 'Origin', field: 'origin', width: 72, hozAlign: 'center', visible: false, formatter: () => '<span style="color:#ff9900;font-weight:700"><i class="fab fa-amazon"></i></span>' },
                {
                    title: 'INV', field: 'total_available', width: 72, hozAlign: 'center', sorter: 'number',
                    formatter: (cell) => {
                        const n = Number(cell.getValue() ?? 0);
                        return `<span class="lm-qty-pill ${n > 0 ? '' : 'is-zero'}">${escapeHtml(String(n))}</span>`;
                    }
                },
                {
                    title: 'Price',
                    field: 'price',
                    width: 90,
                    hozAlign: 'right',
                    headerHozAlign: 'center',
                    sorter: 'number',
                    formatter: (cell) => money(cell.getValue())
                },
                { title: 'Drafts', field: 'draft_channels', width: 80, hozAlign: 'center', formatter: c => Number(c.getValue()||0) ? `<span class="badge bg-primary">${c.getValue()}</span>` : '0' },
            ],
        });
        table.on('rowSelectionChanged', () => $('#lm-selected-count').text(table.getSelectedData().length));
        table.on('tableBuilt', sizeLmTables);

        draftsTable = new Tabulator('#lm-drafts-table', {
            layout: 'fitColumns',
            height: Math.max(360, window.innerHeight - 300),
            placeholder: 'No drafts yet. From All Products → List Products On Channel.',
            selectableRows: true,
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [50, 100, 250],
            ajaxURL: "{{ route('listing.manager.drafts') }}",
            ajaxParams: buildDraftQuery,
            ajaxResponse: function (_u, _p, response) {
                const meta = (response && response.meta) || {};
                $('#lm-meta-drafts').text(Number(meta.drafts_tab || 0).toLocaleString());
                $('#lm-meta-active').text(Number(meta.active_tab || 0).toLocaleString());
                let rows = (response && response.data) ? response.data : [];
                const sort = $('#lm-draft-sort').val();
                if (sort === 'name') rows = rows.slice().sort((a,b) => String(a.title||'').localeCompare(String(b.title||'')));
                if (sort === 'sku') rows = rows.slice().sort((a,b) => String(a.sku||'').localeCompare(String(b.sku||'')));
                return rows;
            },
            columns: [
                { formatter: 'rowSelection', titleFormatter: 'rowSelection', hozAlign: 'center', headerSort: false, width: 44 },
                {
                    title: '', field: 'thumbnail', width: 44, hozAlign: 'center', headerSort: false,
                    formatter: (cell) => {
                        const src = cell.getValue();
                        if (!src) return '<span class="lm-thumb-empty"><i class="fas fa-image"></i></span>';
                        return `<img class="lm-thumb" src="${escapeHtml(src)}" alt="">`;
                    }
                },
                {
                    title: 'Hero Image', field: 'hero_image', width: 88, hozAlign: 'center', headerSort: false,
                    formatter: (cell) => {
                        const src = cell.getValue();
                        if (!src) return '<span class="lm-thumb-empty" title="No Image Master / Images tab photo"><i class="fas fa-image"></i></span>';
                        return `<span class="lm-hero-wrap" title="Open Images tab"><img class="lm-thumb" src="${escapeHtml(src)}" alt="Hero"></span>`;
                    },
                    cellClick: (e, cell) => {
                        openEditor(cell.getRow().getData().id, 'images');
                    }
                },
                {
                    title: '', field: 'id', width: 42, hozAlign: 'center', headerSort: false,
                    formatter: () => '<button type="button" class="btn btn-link btn-sm p-0 lm-open-editor" title="Edit"><i class="fas fa-pen"></i></button>',
                    cellClick: (e, cell) => {
                        e.preventDefault();
                        openEditor(cell.getRow().getData().id);
                    }
                },
                {
                    title: 'Status', field: 'ui_status', width: 120, hozAlign: 'center',
                    formatter: c => statusPill(c.getValue())
                },
                { title: 'SKU', field: 'sku', minWidth: 130 },
                {
                    title: '', field: '_magnify', width: 42, hozAlign: 'center', headerSort: false,
                    formatter: () => '<button type="button" class="btn btn-link btn-sm p-0 lm-sku-magnify" title="Open editor in new tab"><i class="fas fa-search"></i></button>',
                    cellClick: (e, cell) => {
                        e.preventDefault();
                        e.stopPropagation();
                        openProductInNewTab(cell.getRow().getData().sku);
                    }
                },
                {
                    title: 'Name', field: 'title', minWidth: 220, widthGrow: 4,
                    formatter: (cell) => `<a href="#" class="lm-name-link lm-open-name">${escapeHtml(cell.getValue() || '')}</a>`,
                    cellClick: (e, cell) => {
                        e.preventDefault();
                        openEditor(cell.getRow().getData().id);
                    }
                },
                { title: 'Channel', field: 'channel', minWidth: 100 },
                { title: 'Quantity', field: 'quantity', width: 90, hozAlign: 'center' },
                {
                    title: 'Price',
                    field: 'price',
                    width: 90,
                    hozAlign: 'right',
                    headerHozAlign: 'center',
                    sorter: 'number',
                    formatter: (cell) => money(cell.getValue())
                },
            ],
        });
        draftsTable.on('rowSelectionChanged', () => $('#lm-draft-selected-count').text(draftsTable.getSelectedData().length));
        draftsTable.on('tableBuilt', sizeLmTables);
        window.addEventListener('resize', sizeLmTables);
        setTimeout(sizeLmTables, 80);

        $('.lm-page-tab').on('click', function () { showPanel($(this).data('panel')); });
        $('.lm-stock-tab').on('click', function () {
            $('.lm-stock-tab').removeClass('active');
            $(this).addClass('active');
            stockFilter = $(this).data('stock');
            loadTable();
        });
        $('.lm-channel-tab').on('click', function () {
            $('.lm-channel-tab').removeClass('active');
            $(this).addClass('active');
            draftsTab = $(this).data('tab');
            loadDrafts();
        });

        $('#lm-search-btn').on('click', loadTable);
        $('#lm-clear-btn').on('click', function () {
            $('#lm-q-name,#lm-q-sku').val('');
            $('#lm-product-type').val('all');
            loadTable();
        });
        $('#lm-q-name,#lm-q-sku').on('keydown', e => { if (e.key === 'Enter') loadTable(); });

        $('#lm-draft-search-btn,#lm-draft-sort').on('click change', loadDrafts);
        $('#lm-draft-clear-btn').on('click', function () {
            $('#lm-draft-q,#lm-draft-q-sku').val('');
            loadDrafts();
        });
        $('#lm-draft-channel').on('change', function () { updatePageTitle(); loadDrafts(); });
        $('#lm-draft-q,#lm-draft-q-sku').on('keydown', e => { if (e.key === 'Enter') loadDrafts(); });

        $('#lm-import-amazon-btn').on('click', function () {
            const $btn = $(this);
            if (!confirm('Import all Amz listing details? This may take a few minutes.')) return;
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Importing…');
            $.ajax({
                url: "{{ route('listing.manager.import') }}",
                method: 'POST',
                success: function (res) {
                    toast(res.message || 'Import complete.', res.success ? 'success' : 'error');
                    loadProductTypes();
                    loadTable();
                },
                error: xhr => toast(xhr.responseJSON?.message || 'Import failed.', 'error'),
                complete: () => $btn.prop('disabled', false).html('<i class="fas fa-cloud-download-alt me-1"></i>Import from Amz'),
            });
        });

        $('#lm-add-selected-drafts').on('click', () => openListModal((table.getSelectedData() || []).map(r => r.sku).filter(Boolean)));
        $('#lm-add-all-drafts').on('click', () => openListModal((table.getData('active') || []).map(r => r.sku).filter(Boolean)));
        $('#lm-quick-list-btn').on('click', () => showPanel('products'));

        $('#lm-select-all-channels').on('change', function () {
            const on = this.checked;
            $('#lm-channel-list .lm-channel-cb').prop('checked', on).each(function () {
                $(this).closest('.lm-channel-row').toggleClass('is-checked', on);
            });
        });

        $('#lm-add-draft-now-btn').on('click', function () {
            const channelIds = $('#lm-channel-list .lm-channel-cb:checked').map(function () { return parseInt(this.value, 10); }).get();
            if (!channelIds.length) { toast('Select at least one marketplace.', 'error'); return; }
            const $btn = $(this).prop('disabled', true).text('Adding…');
            $.ajax({
                url: "{{ route('listing.manager.drafts.add') }}",
                method: 'POST',
                data: { skus: pendingSkusForDraft, channel_ids: channelIds, include_siblings: $('#lm-include-siblings').is(':checked') ? 1 : 0 },
                success: function (res) {
                    toast(res.message || 'Drafts added.', 'success');
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('lmListChannelModal')).hide();
                    loadTable();
                    showPanel('drafts');
                    if (channelIds[0]) $('#lm-draft-channel').val(String(channelIds[0]));
                    loadDrafts();
                },
                error: xhr => toast(xhr.responseJSON?.message || 'Failed to add drafts.', 'error'),
                complete: () => $btn.prop('disabled', false).text('Add As Draft Now'),
            });
        });

        $('#lm-manage-channels-btn,#lm-channel-settings-btn').on('click', function () {
            loadChannels().then(function () {
                renderChannelRows('#lm-manage-channel-list', allChannels, false, allChannels.filter(c => c.enabled).map(c => c.id));
                bootstrap.Modal.getOrCreateInstance(document.getElementById('lmManageChannelsModal')).show();
            });
        });
        $('#lm-save-channels-btn').on('click', function () {
            const ids = $('#lm-manage-channel-list .lm-channel-cb:checked').map(function () { return parseInt(this.value, 10); }).get();
            $.ajax({
                url: "{{ route('listing.manager.channels.save') }}",
                method: 'POST',
                data: { channel_ids: ids },
                success: function (res) {
                    toast(res.message || 'Saved.', 'success');
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('lmManageChannelsModal')).hide();
                    loadChannels();
                },
                error: xhr => toast(xhr.responseJSON?.message || 'Save failed.', 'error'),
            });
        });

        $('#lm-draft-refresh-btn').on('click', function () {
            $.ajax({
                url: "{{ route('listing.manager.drafts.refresh') }}",
                method: 'POST',
                success: res => { toast(res.message || 'Status refreshed.', 'success'); loadDrafts(); },
                error: xhr => toast(xhr.responseJSON?.message || 'Refresh failed.', 'error'),
            });
        });

        // Product detail modal (All Products → Name click)
        $('#lm-prod-tabs').on('click', '.lm-prod-tab', function () {
            setProductPane($(this).data('pane'));
        });
        $(document).on('change', '.lm-sync-siblings, .lm-sync-parent', function () {
            const isSiblings = $(this).hasClass('lm-sync-siblings');
            if (isSiblings) $('.lm-sync-siblings').prop('checked', this.checked);
            else $('.lm-sync-parent').prop('checked', this.checked);
            saveSyncPrefs();
        });
        $(document).on('click', '.lm-row-edit-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();
            beginMasterEdit(String($(this).attr('data-field') || ''));
        });
        $(document).on('click', '.lm-master-cancel-btn', function (e) {
            e.preventDefault();
            cancelMasterEdit();
        });
        $(document).on('click', '.lm-master-save-btn', function (e) {
            e.preventDefault();
            const field = String($(this).attr('data-field') || '');
            const value = String($('.lm-master-edit-input[data-field="' + field + '"]').val() || '');
            saveMasterField(field, value);
        });
        $(document).on('input', '.lm-master-edit-input', function () {
            const field = String($(this).attr('data-field') || '');
            const n = String($(this).val() || '').length;
            if (field === 'description_html') {
                setCharCount($('#lm-desc-char-count'), n);
            } else {
                setCharCount($(this).closest('[data-field]').find('.lm-char-count'), n);
            }
        });
        $(document).on('click', '.lm-master-sync-btn', function () {
            const sku = currentProductSku;
            if (!sku) { toast('Open a product first.', 'error'); return; }
            const $btn = $(this);
            if ($btn.data('loading')) return;
            const source = String($btn.attr('data-master-source') || 'content');
            const idleHtml = $btn.html();
            $btn.data('loading', true).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Syncing…');
            const prefs = currentSyncPrefs();
            $.getJSON("{{ url('/listing-manager/product/from-master') }}", {
                sku,
                source,
                sync_siblings: prefs.siblings ? 1 : 0,
                sync_parent: prefs.parent ? 1 : 0
            })
                .done(function (res) {
                    const pack = mergeMasterContent(res);
                    if (source === 'content' && pack.title) currentProduct.title = pack.title;
                    if (source === 'description' && pack.description) currentProduct.description = pack.description;
                    const ok = source === 'bullets'
                        ? (Array.isArray(res.bullets) && res.bullets.length > 0)
                        : (source === 'description' ? !!res.description : !!(res.has_title || res.title));
                    toast(res.message || 'Synced.', ok ? 'success' : 'error');
                })
                .fail(xhr => toast(xhr.responseJSON?.message || 'Could not sync from Product Masters.', 'error'))
                .always(function () {
                    $btn.data('loading', false).prop('disabled', false).html(idleHtml);
                });
        });
        $(document).on('click', '.lm-master-apply-btn', function () {
            if (!currentProduct) { toast('Open a product first.', 'error'); return; }
            const pack = currentProduct.master_content || {};
            const which = String($(this).attr('data-apply') || '');
            if (which === 'title') {
                const title = String(pack.title || '').trim();
                if (!title) { toast('No Title Master value to apply. Sync first.', 'error'); return; }
                currentProduct.title = title;
                if (productEditMode) fillProductEditForm(currentProduct);
                else renderProductInfo(currentProduct);
                $('#lm-prod-title').text(title);
                toast('Applied Title Master to Product Info.', 'success');
                return;
            }
            if (which === 'description') {
                const description = String(pack.description || '').trim();
                if (!description) { toast('No Description Master value to apply. Sync first.', 'error'); return; }
                currentProduct.description = description;
                if (productEditMode) fillProductEditForm(currentProduct);
                else renderProductInfo(currentProduct);
                toast('Applied Description Master to Product Info.', 'success');
            }
        });
        $('#lm-prod-update-btn').on('click', function () {
            if (!currentProductSku) return;
            const $btn = $(this).prop('disabled', true);
            openProductModal(currentProductSku);
            setTimeout(() => $btn.prop('disabled', false), 800);
        });
        $('#lm-prod-edit-btn').on('click', function () {
            if (!currentProduct) { toast('Load a product first.', 'error'); return; }
            setProductEditMode(true);
        });
        $('#lm-prod-cancel-edit-btn').on('click', function () {
            setProductEditMode(false);
        });
        $('#lm-prod-save-btn').on('click', function () {
            if (!currentProductSku) return;
            const $btn = $(this);
            if ($btn.data('loading')) return;
            $btn.data('loading', true).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Saving…');
            $.ajax({
                url: "{{ url('/listing-manager/product/save') }}",
                method: 'POST',
                data: collectProductEditFields(),
                success: function (res) {
                    toast(res.message || 'Saved.', 'success');
                    if (currentProduct) {
                        currentProduct = Object.assign({}, currentProduct, collectProductEditFields());
                        $('#lm-prod-title').text(currentProduct.title || currentProduct.sku || 'Product');
                    }
                    loadTable();
                },
                error: xhr => toast(xhr.responseJSON?.message || 'Could not save product.', 'error'),
                complete: function () {
                    $btn.data('loading', false).prop('disabled', false).html('<i class="fas fa-save me-1"></i>Save Changes');
                }
            });
        });
        function productChannelFlags(p) {
            const listed = new Set();
            const drafted = new Set();
            const listedIds = new Set();
            const draftedIds = new Set();
            (p && p.listed_on ? p.listed_on : []).forEach(function (r) {
                listed.add(String(r.channel || '').toLowerCase());
                if (r.channel_id) listedIds.add(Number(r.channel_id));
            });
            (p && p.drafts ? p.drafts : []).forEach(function (r) {
                const name = String(r.channel || '').toLowerCase();
                const id = Number(r.channel_id || 0);
                if (String(r.status || '') === 'listed') {
                    listed.add(name);
                    if (id) listedIds.add(id);
                } else {
                    drafted.add(name);
                    if (id) draftedIds.add(id);
                }
            });
            return { listed, drafted, listedIds, draftedIds };
        }

        function renderPushChannelRows(channels, prechecked, flags) {
            const checked = new Set((prechecked || []).map(Number));
            const listed = flags && flags.listed ? flags.listed : new Set();
            const drafted = flags && flags.drafted ? flags.drafted : new Set();
            $('#lm-prod-push-channels').html(channels.map(function (c) {
                const on = checked.has(Number(c.id));
                const src = logoSrc(c.logo);
                const name = String(c.channel || '').toLowerCase();
                const listedIds = flags && flags.listedIds ? flags.listedIds : new Set();
                const draftedIds = flags && flags.draftedIds ? flags.draftedIds : new Set();
                let badge = '<span class="lm-push-badge">Not listed</span>';
                if (listed.has(name) || listedIds.has(Number(c.id))) badge = '<span class="lm-push-badge listed">Listed</span>';
                else if (drafted.has(name) || draftedIds.has(Number(c.id))) badge = '<span class="lm-push-badge draft">Draft</span>';
                return `<label class="lm-channel-row ${on ? 'is-checked' : ''}">
                    <input type="checkbox" class="form-check-input lm-channel-cb" value="${c.id}" ${on ? 'checked' : ''}>
                    ${src ? `<img src="${escapeHtml(src)}" alt="">` : '<span class="lm-thumb-empty"><i class="fas fa-store"></i></span>'}
                    <span class="fw-semibold">${escapeHtml(c.channel)}</span>
                    ${badge}
                </label>`;
            }).join('') || '<div class="text-muted small">No marketplaces enabled. Use Add Marketplaces first.</div>');
            $('#lm-prod-push-channels').off('change', '.lm-channel-cb').on('change', '.lm-channel-cb', function () {
                $(this).closest('.lm-channel-row').toggleClass('is-checked', this.checked);
                const boxes = $('#lm-prod-push-channels .lm-channel-cb');
                $('#lm-push-select-all').prop('checked', boxes.length > 0 && boxes.filter(':checked').length === boxes.length);
            });
        }

        function renderPushPending(channelIds) {
            const rows = channelIds.map(function (id) {
                const ch = allChannels.find(c => Number(c.id) === Number(id));
                const name = ch ? ch.channel : ('Channel ' + id);
                return `<div class="lm-push-status-row" data-channel-id="${id}">
                    <span class="ico text-muted"><i class="fas fa-clock"></i></span>
                    <div><strong>${escapeHtml(name)}</strong><div class="text-muted">Waiting…</div></div>
                </div>`;
            });
            $('#lm-push-results').removeClass('d-none').html(rows.join(''));
        }

        function setPushRowStatus(id, state, message, mode) {
            const ch = allChannels.find(c => Number(c.id) === Number(id));
            const name = ch ? ch.channel : ('Channel ' + id);
            const $row = $('#lm-push-results .lm-push-status-row[data-channel-id="' + id + '"]');
            let ico = '<i class="fas fa-clock"></i>';
            let cls = '';
            let badge = '';
            if (state === 'updating') {
                ico = '<i class="fas fa-spinner fa-spin"></i>';
            } else if (state === 'ok') {
                ico = '<i class="fas fa-check-circle"></i>';
                cls = 'ok';
                badge = mode === 'draft'
                    ? '<span class="lm-push-badge draft">Draft only</span>'
                    : '<span class="lm-push-badge listed">Live</span>';
            } else if (state === 'fail') {
                ico = '<i class="fas fa-times-circle"></i>';
                cls = 'fail';
            }
            const html = `<span class="ico">${ico}</span>
                <div><strong>${escapeHtml(name)}</strong> ${badge}
                <div class="${cls || 'text-muted'}">${escapeHtml(message || '')}</div></div>`;
            if ($row.length) {
                $row.attr('class', 'lm-push-status-row ' + cls).html(html);
            } else {
                $('#lm-push-results').append('<div class="lm-push-status-row ' + cls + '" data-channel-id="' + id + '">' + html + '</div>');
            }
        }

        function renderPushResults(rows, fallback) {
            if (!rows.length) {
                $('#lm-push-results').removeClass('d-none').html('<div class="fail">' + escapeHtml(fallback || 'No marketplace results returned.') + '</div>');
                return;
            }
            $('#lm-push-results').removeClass('d-none').html(rows.map(function (r) {
                const ok = !!r.success;
                const mode = String(r.mode || '');
                const badge = mode === 'live'
                    ? '<span class="lm-push-badge listed">Live</span>'
                    : (mode === 'draft' ? '<span class="lm-push-badge draft">Draft only</span>' : '');
                return `<div class="lm-push-status-row ${ok ? 'ok' : 'fail'}">
                    <span class="ico">${ok ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-times-circle"></i>'}</span>
                    <div><strong>${escapeHtml(r.channel || r.marketplace || 'Channel')}</strong> ${badge}
                    <div>${escapeHtml(r.message || (ok ? 'Updated.' : 'Failed.'))}</div></div>
                </div>`;
            }).join(''));
        }

        function openProductPushPanel() {
            if (!currentProductSku) { toast('Open a product first.', 'error'); return; }
            const $openBtn = $('#lm-prod-push-btn');
            if ($openBtn.data('loading')) return;
            const openIdle = $openBtn.html();
            $openBtn.data('loading', true).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Loading…');
            setProductPane('info');
            $('#lm-push-results').addClass('d-none').empty();
            $('#lm-push-part-title, #lm-push-part-description, #lm-push-part-price').prop('checked', true);
            $('#lm-prod-push-panel').removeClass('d-none');
            $('#lm-prod-push-channels').html('<div class="text-muted small"><i class="fas fa-spinner fa-spin me-1"></i>Loading marketplaces…</div>');
            const flags = productChannelFlags(currentProduct);
            loadChannels().then(function () {
                const enabled = allChannels.filter(c => c.enabled);
                const extra = allChannels.filter(c => {
                    const name = String(c.channel || '').toLowerCase();
                    return !c.enabled && (flags.listed.has(name) || flags.listedIds.has(Number(c.id)));
                });
                const list = enabled.concat(extra);
                const pre = list.filter(c => {
                    const name = String(c.channel || '').toLowerCase();
                    return flags.listed.has(name) || flags.drafted.has(name)
                        || flags.listedIds.has(Number(c.id)) || flags.draftedIds.has(Number(c.id));
                }).map(c => c.id);
                renderPushChannelRows(list, pre, flags);
                $('#lm-push-select-all').prop('checked', pre.length > 0 && pre.length === list.length);
                const panel = document.getElementById('lm-prod-push-panel');
                if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }).fail(function () {
                toast('Could not load marketplaces.', 'error');
            }).always(function () {
                $openBtn.data('loading', false).prop('disabled', false).html(openIdle);
            });
        }

        $('#lm-prod-push-btn').on('click', function () {
            openProductPushPanel();
        });
        $('#lm-prod-push-hide-btn').on('click', function () {
            $('#lm-prod-push-panel').addClass('d-none');
        });
        $('#lm-push-select-all').on('change', function () {
            const on = this.checked;
            $('#lm-prod-push-channels .lm-channel-cb').prop('checked', on).each(function () {
                $(this).closest('.lm-channel-row').toggleClass('is-checked', on);
            });
        });
        $('#lm-push-now-btn').on('click', async function () {
            const channelIds = $('#lm-prod-push-channels .lm-channel-cb:checked').map(function () {
                return parseInt(this.value, 10);
            }).get().filter(n => n > 0);
            if (!channelIds.length) { toast('Select at least one marketplace.', 'error'); return; }
            const parts = [];
            if ($('#lm-push-part-title').is(':checked')) parts.push('title');
            if ($('#lm-push-part-description').is(':checked')) parts.push('description');
            if ($('#lm-push-part-price').is(':checked')) parts.push('price');
            if (!parts.length) { toast('Choose Title, Description, and/or Price to push.', 'error'); return; }
            const $btn = $(this);
            if ($btn.data('loading')) return;
            const idleHtml = $btn.html();
            $btn.data('loading', true).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Updating…');
            $('#lm-prod-push-btn').prop('disabled', true);
            $('#lm-prod-push-overlay').addClass('d-none');
            renderPushPending(channelIds);
            const fields = collectProductEditFields();
            let ok = 0;
            let fail = 0;
            try {
                await $.ajax({
                    url: "{{ url('/listing-manager/product/save') }}",
                    method: 'POST',
                    data: fields,
                    timeout: 30000,
                });
            } catch (xhr) {
                const msg = (xhr && xhr.responseJSON && xhr.responseJSON.message) || 'Could not save product before pushing.';
                toast(msg, 'error');
                $btn.data('loading', false).prop('disabled', false).html(idleHtml);
                $('#lm-prod-push-btn').prop('disabled', false);
                return;
            }
            for (let i = 0; i < channelIds.length; i++) {
                const id = channelIds[i];
                const ch = allChannels.find(c => Number(c.id) === Number(id));
                const name = ch ? ch.channel : ('Channel ' + id);
                $btn.html('<i class="fas fa-spinner fa-spin me-1"></i>' + (i + 1) + '/' + channelIds.length);
                setPushRowStatus(id, 'updating', 'Updating ' + name + '…');
                try {
                    const res = await $.ajax({
                        url: "{{ url('/listing-manager/product/push') }}",
                        method: 'POST',
                        data: Object.assign({}, fields, { channel_ids: [id], parts, skip_save: 1 }),
                        timeout: 120000,
                    });
                    const row = (res.results && res.results[0]) ? res.results[0] : null;
                    if (row && row.success) {
                        ok++;
                        setPushRowStatus(id, 'ok', row.message || 'Updated.', row.mode);
                    } else {
                        fail++;
                        setPushRowStatus(id, 'fail', (row && row.message) || res.message || 'Update failed.', row && row.mode);
                    }
                } catch (xhr) {
                    fail++;
                    const msg = (xhr && xhr.responseJSON && xhr.responseJSON.message)
                        || (xhr && xhr.statusText)
                        || 'Could not update this marketplace.';
                    setPushRowStatus(id, 'fail', msg);
                }
            }
            toast('Updated ' + ok + ' marketplace(s)' + (fail ? (', ' + fail + ' failed.') : '.'), fail === 0 ? 'success' : 'error');
            if (currentProduct) {
                currentProduct = Object.assign({}, currentProduct, fields);
                $('#lm-prod-title').text(currentProduct.title || currentProduct.sku || 'Product');
            }
            loadTable();
            $btn.data('loading', false).prop('disabled', false).html(idleHtml);
            $('#lm-prod-push-btn').prop('disabled', false);
        });
        $('#lm-prod-content').on('click', '.lm-create-listing-btn', function () {
            const sku = String($(this).data('sku') || currentProductSku || '').trim();
            const channelId = parseInt($(this).data('channel-id'), 10);
            if (!sku || !channelId) { toast('Missing channel or SKU.', 'error'); return; }
            const $btn = $(this).prop('disabled', true).text('Creating…');
            $.ajax({
                url: "{{ route('listing.manager.drafts.add') }}",
                method: 'POST',
                data: { skus: [sku], channel_ids: [channelId] },
                success: function (res) {
                    toast(res.message || 'Draft created.', 'success');
                    openProductModal(sku);
                    loadTable();
                },
                error: xhr => toast(xhr.responseJSON?.message || 'Could not create listing draft.', 'error'),
                complete: () => $btn.prop('disabled', false).text('+ Create Listing'),
            });
        });
        $('#lm-prod-content').on('click', '.lm-open-listed-draft', function (e) {
            e.preventDefault();
            const id = parseInt($(this).data('id'), 10);
            if (!id) return;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('lmProductModal')).hide();
            openEditor(id);
        });

        $('#lm-multi-edit-btn').on('click', function () {
            multiEdit = !multiEdit;
            $(this).toggleClass('btn-lc-primary', multiEdit).toggleClass('btn-lc-ghost', !multiEdit);
            toast(multiEdit ? 'Multi Edit Mode on — select rows, then open one listing.' : 'Multi Edit Mode off.', 'info');
        });

        $('#lm-action-publish-selected').on('click', function () {
            const rows = (draftsTable.getSelectedData() || []).filter(r => r.ui_status === 'Ready');
            if (!rows.length) { toast('Select Ready drafts only.', 'error'); return; }
            let i = 0;
            function next() {
                if (i >= rows.length) { toast('Publish finished.', 'success'); loadDrafts(); return; }
                const row = rows[i++];
                $.ajax({
                    url: "{{ url('/listing-manager/drafts') }}/" + row.id + '/publish',
                    method: 'POST',
                }).always(next);
            }
            next();
        });

        $('#lm-action-delete-selected').on('click', function () {
            const rows = draftsTable.getSelectedData() || [];
            if (!rows.length) { toast('Select drafts to delete.', 'error'); return; }
            if (!confirm('Delete ' + rows.length + ' draft(s)?')) return;
            Promise.all(rows.map(r => $.ajax({ url: "{{ url('/listing-manager/drafts') }}/" + r.id, method: 'DELETE' })))
                .then(() => { toast('Deleted.', 'success'); loadDrafts(); loadTable(); });
        });

        // Editor tabs + live validation
        $('#lc-tabs').on('click', '.lc-tab', function () {
            if (this.hidden) return;
            const pane = $(this).data('pane');
            $('#lc-tabs .lc-tab').removeClass('active');
            $(this).addClass('active');
            $('#lmListingEditorModal .lc-pane').removeClass('active');
            $(`#lmListingEditorModal .lc-pane[data-pane="${pane}"]`).addClass('active');
            if (pane === 'category' && currentDraft && currentDraft.editor && (currentDraft.editor.tiktok || currentDraft.editor.reverb)) {
                const q = String($('#lc-category-search').val() || '').trim();
                searchCategories(q);
            }
        });
        $('#lc-category-id-visible, #lc-category-path-visible').on('input', function () {
            $('#lc-category-id').val($('#lc-category-id-visible').val() || '');
            $('#lc-category-path-input').val($('#lc-category-path-visible').val() || '');
        });
        $('#lmListingEditorModal').on('input change', 'input, textarea, select', function () {
            dirty = true;
            refreshEditorUi(currentDraft);
        });
        $('#lc-title, #lc-description').on('input', function () { refreshEditorUi(currentDraft); });
        $('#lc-description-rich').on('input', function () { refreshEditorUi(currentDraft); });
        $('#lc-desc-modes').on('click', 'button', function () {
            setDescMode($(this).data('desc-mode'));
        });
        $('#lc-switch-rich-btn').on('click', function () {
            setDescMode(descMode === 'rich' ? 'code' : 'rich');
        });
        $('#lc-image-preview').on('click', '.lc-img-del', function (e) {
            e.preventDefault();
            e.stopPropagation();
            let url = '';
            try { url = decodeURIComponent($(this).closest('.lc-image-card').attr('data-url') || ''); } catch (err) { url = ''; }
            editorImages = editorImages.filter(u => u !== url);
            dirty = true;
            refreshEditorUi(currentDraft);
        });
        $('#lc-upload-image').on('change', function () {
            const file = this.files && this.files[0];
            const id = $('#lc-draft-id').val();
            if (!file || !id) return;
            const fd = new FormData();
            fd.append('image', file);
            fd.append('_token', csrf);
            $.ajax({
                url: "{{ url('/listing-manager/drafts') }}/" + id + '/images',
                method: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                success: function (res) {
                    if (res.url) {
                        editorImages.push(res.url);
                        refreshEditorUi(currentDraft);
                        toast('Image uploaded.', 'success');
                    }
                    if (res.draft) currentDraft = res.draft;
                },
                error: xhr => toast(xhr.responseJSON?.message || 'Upload failed.', 'error'),
                complete: () => { $('#lc-upload-image').val(''); },
            });
        });
        $(document).on('click', '.lc-load-master-btn', function () {
            const id = $('#lc-draft-id').val();
            if (!id) { toast('Open a draft first.', 'error'); return; }
            const $btn = $(this);
            if ($btn.data('loading')) return;
            const source = String($btn.attr('data-master-source') || '');
            const idleHtml = $btn.html();
            $btn.data('loading', true).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Loading…');
            $.ajax({
                url: "{{ url('/listing-manager/drafts') }}/" + id + '/load-master',
                method: 'POST',
                data: { source },
                timeout: 35000,
                success: function (res) {
                    if (!res.success) {
                        toast(res.message || 'Load failed.', 'error');
                        return;
                    }
                    applyMasterPayload(res);
                    toast(res.message || 'Loaded from master.', 'success');
                },
                error: xhr => toast(xhr.responseJSON?.message || 'Could not load from master.', 'error'),
                complete: function () {
                    $btn.data('loading', false).prop('disabled', false).html(idleHtml);
                }
            });
        });
        $(document).on('click', '.lm-prod-master-btn', function () {
            const sku = currentProductSku;
            if (!sku) { toast('Open a product first.', 'error'); return; }
            const $btn = $(this);
            if ($btn.data('loading')) return;
            const source = String($btn.attr('data-master-source') || '');
            const idleHtml = $btn.html();
            $btn.data('loading', true).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Loading…');
            $.getJSON("{{ url('/listing-manager/product/from-master') }}", { sku, source })
                .done(function (res) {
                    if (!res.success) {
                        toast(res.message || 'Load failed.', 'error');
                        return;
                    }
                    if (!currentProduct) currentProduct = { sku };
                    if (res.title) currentProduct.title = res.title;
                    if (res.description) currentProduct.description = res.description;
                    if (res.upc) currentProduct.upc = res.upc;
                    if (res.brand) currentProduct.vendor = res.brand;
                    if (res.manufacturer) currentProduct.manufacturer = res.manufacturer;
                    if (res.price != null) currentProduct.price = res.price;
                    if (res.quantity != null) currentProduct.quantity = res.quantity;
                    if (Array.isArray(res.images) && res.images.length) currentProduct.images = res.images;
                    if (productEditMode) {
                        fillProductEditForm(currentProduct);
                    } else {
                        renderProductInfo(currentProduct);
                        if (res.images) renderProductImages(currentProduct.images);
                    }
                    if (res.title) $('#lm-prod-title').text(res.title);
                    toast(res.message || 'Loaded from master.', 'success');
                })
                .fail(xhr => toast(xhr.responseJSON?.message || 'Could not load from master.', 'error'))
                .always(function () {
                    $btn.data('loading', false).prop('disabled', false).html(idleHtml);
                });
        });
        $('#lc-load-images-btn').on('click', function () {
            const id = $('#lc-draft-id').val();
            if (!id) { toast('Open a draft first.', 'error'); return; }
            const $btn = $(this);
            if ($btn.data('loading')) return;
            $btn.data('loading', true).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Loading…');
            $.ajax({
                url: "{{ url('/listing-manager/drafts') }}/" + id + '/load-images',
                method: 'POST',
                timeout: 35000,
                success: function (res) {
                    const imgs = sanitizeEditorImages(res.images || []);
                    if (!imgs.length) {
                        toast(res.message || 'No images returned.', 'error');
                        return;
                    }
                    editorImages = imgs;
                    dirty = true;
                    if (res.draft) {
                        currentDraft = res.draft;
                        // Keep other fields; only refresh images + validation
                        const d = res.draft.listing_details || {};
                        if (res.draft.thumbnail) currentDraft.thumbnail = res.draft.thumbnail;
                        currentDraft.amazon_snapshot = res.draft.amazon_snapshot || currentDraft.amazon_snapshot;
                    }
                    refreshEditorUi(currentDraft);
                    toast(res.message || ('Loaded ' + imgs.length + ' image(s).'), 'success');
                },
                error: function (xhr) {
                    toast(xhr.responseJSON?.message || 'Could not load images from Image Master.', 'error');
                },
                complete: function () {
                    $btn.data('loading', false).prop('disabled', false).html('Load Images From Image Master');
                }
            });
        });
        $('#lc-reload-amazon-btn').on('click', function () {
            const id = $('#lc-draft-id').val();
            if (!id) return;
            const $btn = $(this).prop('disabled', true).text('Reloading…');
            $.ajax({
                url: "{{ url('/listing-manager/drafts') }}/" + id + '/reload-store',
                method: 'POST',
                success: function (res) {
                    toast(res.message || 'Reloaded.', 'success');
                    if (res.draft) {
                        fillEditor(res.draft);
                        setDescMode('preview');
                    }
                },
                error: xhr => toast(xhr.responseJSON?.message || 'Reload failed.', 'error'),
                complete: () => $btn.prop('disabled', false).text('Reload from Main Store'),
            });
        });
        $(document).on('click', '.lc-load-desc-btn', function () {
            const id = $('#lc-draft-id').val();
            if (!id) return;
            const $btn = $(this);
            if ($btn.data('loading')) return;
            const source = String($btn.attr('data-desc-source') || 'shopify');
            const idleHtml = $btn.html();
            $btn.data('loading', true).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Loading…');
            $.ajax({
                url: "{{ url('/listing-manager/drafts') }}/" + id + '/load-description',
                method: 'POST',
                data: { source },
                timeout: 35000,
                success: function (res) {
                    if (!res.success) {
                        toast(res.message || 'Load failed.', 'error');
                        return;
                    }
                    if (res.draft) currentDraft = res.draft;
                    setDescriptionValue(res.description || '');
                    dirty = true;
                    setDescMode('preview');
                    refreshEditorUi(currentDraft);
                    toast(res.message || 'Description loaded.', 'success');
                },
                error: xhr => toast(xhr.responseJSON?.message || 'Could not load description.', 'error'),
                complete: function () {
                    $btn.data('loading', false).prop('disabled', false).html(idleHtml);
                }
            });
        });
        $('#lc-category-search').on('input', function () {
            const q = String($(this).val() || '').trim();
            clearTimeout(categoryTimer);
            categoryTimer = setTimeout(() => searchCategories(q), 350);
        });
        $('#lc-category-results').on('click', '.lc-cat-item', function () {
            const id = String($(this).data('id') || '');
            const path = String($(this).data('path') || '');
            $('#lc-category-id').val(id);
            $('#lc-category-path-input').val(path);
            $('#lc-category-id-visible').val(id);
            $('#lc-category-path-visible').val(path);
            dirty = true;
            refreshEditorUi(currentDraft);
            toast('Category selected.', 'success');
        });
        $('#lc-refresh-policies').on('click', function () {
            loadPolicies({
                shipping_policy_id: $('#lc-shipping-policy').val(),
                payment_policy_id: $('#lc-payment-policy').val(),
                return_policy_id: $('#lc-return-policy').val(),
            }).then(() => {
                toast('Policies refreshed.', 'success');
                refreshEditorUi(currentDraft);
            });
        });
        $('#lc-optimize-desc-btn').on('click', function () {
            const id = $('#lc-draft-id').val();
            if (!id) return;
            const $btn = $(this);
            if ($btn.data('loading')) return;
            $btn.data('loading', true).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Optimizing…');
            // Ensure latest rich/code content is in textarea before send
            if (descMode === 'rich') {
                $('#lc-description').val($('#lc-description-rich').html() || '');
            }
            $.ajax({
                url: "{{ url('/listing-manager/drafts') }}/" + id + '/optimize-description',
                method: 'POST',
                data: {
                    description: getDescriptionValue(),
                    images: editorImages,
                },
                success: function (res) {
                    if (!res.success) {
                        toast(res.message || 'Optimize failed.', 'error');
                        return;
                    }
                    setDescriptionValue(res.description || '');
                    if (Array.isArray(res.images) && res.images.length) {
                        editorImages = sanitizeEditorImages(res.images);
                    }
                    if (res.draft) currentDraft = res.draft;
                    dirty = true;
                    setDescMode('preview');
                    refreshEditorUi(currentDraft);
                    toast(res.message || 'Description optimized.', 'success');
                },
                error: function (xhr) {
                    toast(xhr.responseJSON?.message || 'Optimize failed.', 'error');
                },
                complete: function () {
                    const label = (currentDraft && currentDraft.editor && currentDraft.editor.optimize_label)
                        || 'Optimize Description';
                    $btn.data('loading', false).prop('disabled', false)
                        .html('<i class="fas fa-magic me-1"></i>' + label);
                }
            });
        });

        $('#lc-save-btn').on('click', function () {
            const $btn = $(this).prop('disabled', true).text('Saving…');
            saveDraft().always(function () {
                $btn.prop('disabled', false).text('Save Changes');
                refreshEditorUi(currentDraft);
            });
        });
        $('#lc-save-close-btn').on('click', function () {
            const $btn = $(this).prop('disabled', true).text('Saving…');
            saveDraft().then(function () {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('lmListingEditorModal')).hide();
            }).always(function () {
                $btn.prop('disabled', false).text('Save & Close');
                refreshEditorUi(currentDraft);
            });
        });
        $('#lc-publish-btn').on('click', publishDraft);
        $('#lc-copy-siblings-btn').on('click', function () {
            copyDraftToSiblings($('#lc-draft-id').val());
        });
        $('#lm-prod-copy-siblings-btn').on('click', function () {
            const drafts = (currentProduct && currentProduct.drafts) || [];
            const id = drafts[0] && drafts[0].id;
            if (!id) {
                toast('Create a channel listing first, then copy details to siblings.', 'error');
                return;
            }
            copyDraftToSiblings(id);
        });
        $(document).on('click', '.lm-open-sibling', function (e) {
            e.preventDefault();
            const sku = $(this).data('sku');
            if (sku) openProductModal(String(sku));
        });
        $('#lc-delete-btn').on('click', function () {
            const id = $('#lc-draft-id').val();
            if (!id || !confirm('Delete this draft listing?')) return;
            $.ajax({
                url: "{{ url('/listing-manager/drafts') }}/" + id,
                method: 'DELETE',
                success: function (res) {
                    toast(res.message || 'Deleted.', 'success');
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('lmListingEditorModal')).hide();
                    loadDrafts();
                    loadTable();
                },
                error: xhr => toast(xhr.responseJSON?.message || 'Delete failed.', 'error'),
            });
        });

        const params = new URLSearchParams(window.location.search);
        if (params.get('tab') === 'drafts' || params.get('tab') === 'channel') {
            showPanel('drafts');
        }
        const skuParam = String(params.get('sku') || '').trim();
        if (skuParam) {
            openProductModal(skuParam, null, { edit: params.get('edit') !== '0' });
        }

        loadChannels().then(loadDrafts);
        loadProductTypes();
    });
})();
</script>
@endsection
