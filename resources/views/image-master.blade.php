@extends('layouts.vertical', ['title' => 'Image Master', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    @include('partials.marketplace-master-button-colors')
    <style>
        .card.im-master-card { border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 2px 12px rgba(44,110,213,.06); }
        .table-responsive { position:relative; border:1px solid #e2e8f0; border-radius:10px; max-height:640px; overflow:auto; background:#fff; }
        #im-master-table thead th { position:sticky; top:0; vertical-align:middle!important; background:linear-gradient(135deg,#2c6ed5 0%,#1a56b7 100%)!important; color:#fff; z-index:10; padding:6px 8px; font-size:10px; font-weight:600; text-transform:uppercase; }
        #im-master-table tbody td { padding:8px 10px; vertical-align:middle!important; border-bottom:1px solid #edf2f9; font-size:11px; }
        .im-thumb { width:56px; height:56px; object-fit:cover; border-radius:6px; border:1px solid #e2e8f0; background:#f8fafc; }
        .im-img-count { font-size:12px; font-weight:700; background:none; white-space:nowrap; }
        .im-img-count.ok { color:#16a34a; }
        .im-img-count.low { color:#dc2626; }
        .marketplaces-cell { vertical-align:middle!important; }
        .bp-mp-inline { display:flex; flex-wrap:wrap; align-items:flex-end; gap:6px; justify-content:flex-start; min-width:120px; }
        .bp-mp-th-title { font-weight:600; letter-spacing:0.2px; }
        .bp-mp-th-icons { display:flex; flex-wrap:wrap; gap:4px; margin-top:6px; justify-content:center; align-items:center; }
        .bp-mp-th-pill { width:22px; height:22px; border-radius:4px; font-size:8px; font-weight:700; color:#fff; display:inline-flex; align-items:center; justify-content:center; line-height:1; }
        .bp-mp-stack { display:flex; flex-direction:column; align-items:center; gap:3px; border:none; background:transparent; padding:0; cursor:pointer; }
        .bp-mp-stack:hover .marketplace-btn:not(:disabled) { transform:translateY(-1px); box-shadow:0 2px 6px rgba(0,0,0,.18); }
        .bp-mp-stack:disabled { cursor:not-allowed; opacity:.45; filter:grayscale(.7); }
        .bp-mp-dot { width:10px; height:10px; border-radius:50%; border:2px solid #94a3b8; background:transparent; transition:background .15s,border-color .15s; flex-shrink:0; }
        .bp-mp-dot.pushed { background:#22c55e; border-color:#22c55e; }
        .bp-mp-dot.failed { background:#ef4444; border-color:#ef4444; }
        .marketplace-btn { width:28px; height:28px; border:none; border-radius:4px; color:#fff; font-weight:600; font-size:11px; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; transition:all .2s; padding:0; }
        .modal-header-gradient { background:linear-gradient(135deg,#6B73FF 0%,#000DFF 100%); color:#fff; }
        /* ── Image card grid ─────────────────────────────────────── */
        .im-grid { display:flex; flex-wrap:wrap; gap:10px; min-height:40px; padding:4px 0; }
        .im-card { width:120px; border:2px solid #e2e8f0; border-radius:10px; overflow:hidden; background:#fff; box-shadow:0 1px 4px rgba(0,0,0,.06); transition:box-shadow .15s,border-color .15s; user-select:none; position:relative; }
        .im-card:hover { box-shadow:0 3px 12px rgba(44,110,213,.18); border-color:#93c5fd; }
        .im-card.im-card-dragging { opacity:.45; border:2px dashed #6366f1; }
        .im-card-img-wrap { position:relative; width:120px; height:100px; background:#f1f5f9; overflow:hidden; }
        .im-card-img-wrap img { width:100%; height:100%; object-fit:cover; display:block; }
        /* Edit product images modal: full height, top to bottom */
        #editImModal .modal-dialog { --bs-modal-margin:0; height:100vh; margin:0 auto; }
        #editImModal .modal-content { height:100vh; max-height:100vh; border-radius:0; }
        /* Hero 1 / Hero 2 cards: 1.5x size in the top row */
        .im-grid-hero { gap:14px; margin-bottom:6px; }
        .im-card.im-card-hero { width:180px; }
        .im-card.im-card-hero .im-card-img-wrap { width:180px; height:150px; }
        .im-card.im-card-hero .im-card-name { max-width:162px; font-size:13px; }
        .im-card.im-card-hero .im-card-filename { max-width:162px; font-size:10px; }
        .im-card.im-card-hero .im-card-arrows button { font-size:13px; padding:3px 0; }
        /* Auto-fit layout: wide modal, 7 columns. Row 1 = Hero 1 + Hero 2 only (2 columns each, 2x height);
           rows 2-3 = the other images spread across the width, then Move-to-end and Sandbox as the last cells. */
        #editImModal .modal-dialog.modal-xl { max-width:min(98vw, 1900px); }
        #editImModal { --im-h:clamp(80px, min(calc((100vh - 350px) / 4), calc((min(98vw, 1900px) - 100px) / 7 * 0.95)), 260px); }
        #imGrid .im-grid { display:grid; grid-template-columns:repeat(7, minmax(0, 1fr)); gap:10px; align-items:start; }
        #imGrid .im-card, #imGrid .im-sandbox { width:100%; margin:0; }
        #imGrid .im-card.im-card-hero { grid-column:span 2; }
        #imGrid .im-card-hero + .im-card:not(.im-card-hero) { grid-column-start:1; }
        #imGrid .im-card .im-card-img-wrap { width:100%; height:var(--im-h); background:#fff; }
        #imGrid .im-card .im-card-img-wrap img { object-fit:contain; }
        #imGrid .im-card.im-card-hero .im-card-img-wrap { height:calc(var(--im-h) * 2); }
        #imGrid .im-card-name, #imGrid .im-card-filename { max-width:100%; }
        /* Hover zoom: 2x (Hero cards 1.25x, they are already large). Origin is set by JS so edge cards grow inward. */
        #imGrid .im-card[data-idx] { transition:transform .15s ease, box-shadow .15s; }
        #imGrid .im-card[data-idx]:hover:not(.im-card-dragging) { transform:scale(2); z-index:30; box-shadow:0 10px 30px rgba(15,23,42,.35); }
        #imGrid .im-card.im-card-hero[data-idx]:hover:not(.im-card-dragging) { transform:scale(1.25); }
        /* Download / Edit: shown only on hover, over the bottom of the image */
        #imGrid .im-card-arrows { position:absolute; left:0; right:0; bottom:6px; margin:0; justify-content:center; gap:6px; z-index:4; opacity:0; pointer-events:none; transition:opacity .12s; }
        #imGrid .im-card:hover .im-card-arrows { opacity:1; pointer-events:auto; }
        #imGrid .im-card-arrows button { flex:0 0 auto; width:34px; height:28px; padding:0; border:0; border-radius:6px; color:#fff; font-size:13px; box-shadow:0 1px 4px rgba(0,0,0,.35); }
        #imGrid .im-card-arrows .im-dl { background:#16a34a; }
        #imGrid .im-card-arrows .im-dl:hover { background:#15803d; color:#fff; }
        #imGrid .im-card-arrows .im-ai { background:#d4a017; }
        #imGrid .im-card-arrows .im-ai:hover { background:#b8860b; color:#fff; }
        #imGrid .im-card-arrows { flex-wrap:wrap; gap:4px; }
        #imGrid .im-card-arrows button { width:28px; height:26px; font-size:12px; display:inline-flex; align-items:center; justify-content:center; }
        #imGrid .im-card-arrows .im-cp { background:#2563eb; }
        #imGrid .im-card-arrows .im-cp:hover { background:#1d4ed8; color:#fff; }
        #imGrid .im-card-arrows .im-ps { background:#7c3aed; }
        #imGrid .im-card-arrows .im-ps:hover { background:#6d28d9; color:#fff; }
        #imGrid .im-card-arrows .im-card-del { position:static; display:inline-flex; border-radius:6px; background:#dc2626; }
        #imGrid .im-card-arrows .im-card-del:hover { background:#b91c1c; }
        #imGrid .im-card.im-drop-end { min-height:calc(var(--im-h) + 34px); }
        #imGrid .im-sandbox { min-height:calc(var(--im-h) + 34px); padding:6px 8px; border-radius:10px; }
        #imGrid .im-sandbox-grid { display:flex; flex-wrap:wrap; gap:5px; }
        #imGrid .im-sb-card { width:54px; grid-column:auto; }
        #imGrid .im-sb-card .im-card-img-wrap { height:46px; }
        #imGrid .im-sb-card .im-card-footer { display:none; }
        #imGrid .im-sb-empty { width:100%; min-height:calc(var(--im-h) * 0.55); border-radius:8px; }
        .im-drop-end { border:2px dashed #cbd5e1; background:#f8fafc; box-shadow:none; min-height:150px; display:flex; align-items:center; justify-content:center; }
        .im-drop-end-inner, .im-sb-empty { display:flex; flex-direction:column; align-items:center; gap:4px; color:#94a3b8; font-size:11px; font-weight:600; text-align:center; }
        .im-sandbox { margin-top:16px; border:2px dashed #94a3b8; border-radius:12px; padding:10px 12px; background:#f8fafc; }
        .im-sandbox-title { font-size:12px; font-weight:700; color:#334155; margin-bottom:6px; }
        .im-sandbox-grid { align-items:flex-start; }
        .im-sb-empty { width:120px; min-height:100px; border:2px dashed #cbd5e1; border-radius:10px; justify-content:center; background:#fff; }
        .im-sb-card .im-card-img-wrap { height:90px; }
        .im-drop-over { border-color:#6366f1 !important; box-shadow:0 0 0 3px rgba(99,102,241,.25) !important; background:#eef2ff; }
        .im-sandbox.im-drop-over { background:#eef2ff; }
        .im-card-badge { position:absolute; top:4px; left:4px; background:rgba(0,0,0,.55); color:#fff; border-radius:4px; font-size:9px; font-weight:700; padding:1px 5px; line-height:1.5; pointer-events:none; z-index:3; }
        .im-card-del { position:absolute; top:4px; right:4px; background:rgba(220,38,38,.88); border:none; color:#fff; border-radius:50%; width:22px; height:22px; font-size:11px; cursor:pointer; display:none; align-items:center; justify-content:center; padding:0; line-height:1; transition:background .12s; z-index:5; }
        .im-card-del:hover { background:#b91c1c; }
        .im-card:hover .im-card-del { display:flex; }
        .im-card-footer { padding:4px 6px 5px; }
        .im-card-name { font-size:11px; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:108px; font-weight:600; }
        .im-card-filename { font-size:9px; color:#94a3b8; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:108px; margin-top:1px; }
        .im-card-arrows { display:flex; gap:3px; margin-top:3px; }
        .im-card-arrows button { flex:1; border:1px solid #e2e8f0; background:#f8fafc; border-radius:4px; font-size:11px; cursor:pointer; padding:1px 0; color:#475569; transition:background .1s; }
        .im-card-arrows button:hover { background:#e0e7ff; color:#4338ca; }
        .im-card.is-main-default { border-color:#22c55e; box-shadow:0 0 0 2px rgba(34,197,94,.25); }
        .im-card-badge-main { background:#16a34a; }
        .im-main-mp-section { border:1px solid #e2e8f0; border-radius:8px; padding:10px 12px; background:#f8fafc; }
        .im-main-mp-row { display:flex; align-items:center; gap:8px; margin-bottom:6px; }
        .im-main-mp-row label { min-width:92px; font-size:11px; font-weight:600; margin:0; color:#334155; }
        .im-main-mp-row select { flex:1; min-width:0; font-size:11px; padding:2px 6px; }
        /* stored-image badge tint */
        .im-card.is-stored .im-card-img-wrap { border-bottom:2px solid #6366f1; }
        /* pending upload preview */
        .im-pending-card { width:90px; position:relative; }
        .im-pending-img-wrap { position:relative; display:inline-block; }
        .im-pending-del { position:absolute; top:2px; right:2px; background:rgba(220,38,38,.88); border:none; color:#fff; border-radius:50%; width:20px; height:20px; font-size:10px; cursor:pointer; display:flex; align-items:center; justify-content:center; padding:0; line-height:1; z-index:2; }
        .im-pending-del:hover { background:#b91c1c; }
        /* selection bar */
        .im-select-bar { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:6px; }
        .im-select-info { font-size:11px; font-weight:600; color:#6366f1; background:#eef2ff; border-radius:5px; padding:2px 8px; }
        /* ─────────────────────────────────────────────────────────── */
        .toast-container { z-index:1100; }
        .shopify-row-pull-btn { background:#f59e0b; color:#fff; border:none; padding:5px 8px; border-radius:4px; }
        .shopify-row-pull-btn:hover { background:#d97706; color:#fff; }
        .im-hist { display:inline-flex; flex-direction:column; gap:2px; cursor:help; white-space:nowrap; font-size:10px; line-height:1.25; }
        .im-hist-date { font-weight:600; color:#334155; }
        .im-hist-counts .ok { color:#16a34a; font-weight:700; }
        .im-hist-counts .bad { color:#dc2626; font-weight:700; }
        #imHistoryPop { position:fixed; z-index:2000; display:none; width:320px; max-height:420px; overflow:auto; background:#fff; border:1px solid #cbd5e1; border-radius:8px; box-shadow:0 8px 24px rgba(15,23,42,.18); padding:10px 12px; font-size:11px; color:#0f172a; pointer-events:none; }
        .im-hist-act { font-weight:700; color:#2563eb; }
        .im-status-cell { display:inline-flex; align-items:center; gap:6px; font-weight:500; color:#4a5568; white-space:nowrap; }
        .im-status-marble { display:inline-block; width:12px; height:12px; border-radius:50%; flex-shrink:0; box-shadow:inset 2px 2px 3px rgba(255,255,255,.55), inset -2px -2px 4px rgba(0,0,0,.35); }
        .im-status-marble--active { background:radial-gradient(circle at 32% 28%, #bbf7d0, #22c55e 42%, #14532d); }
        .im-status-marble--inactive, .im-status-marble--dc { background:radial-gradient(circle at 32% 28%, #fecaca, #ef4444 42%, #7f1d1d); }
        .im-status-marble--upcoming { background:radial-gradient(circle at 32% 28%, #fffef2, #eab308 45%, #713f12); }
        .im-status-marble--2bdc { background:radial-gradient(circle at 32% 28%, #bfdbfe, #2563eb 45%, #1e3a8a); }
        .im-status-marble--muted { background:radial-gradient(circle at 32% 28%, #e5e7eb, #9ca3af 45%, #374151); }
        .im-audit-st { display:inline-block; padding:0 6px; border-radius:8px; font-size:9px; font-weight:700; background:#e2e8f0; color:#334155; }
        .im-audit-st.done { background:#dcfce7; color:#166534; }
        .im-audit-st.work { background:#dbeafe; color:#1d4ed8; }
        .im-audit-st.todo { background:#fef3c7; color:#92400e; }
        .im-audit-st.bad { background:#fee2e2; color:#991b1b; }
        #imHistoryPop .hp-title { font-weight:700; font-size:12px; margin-bottom:4px; }
        #imHistoryPop .hp-meta { color:#475569; margin-bottom:6px; }
        #imHistoryPop .hp-section { font-weight:700; margin:6px 0 3px; text-transform:uppercase; font-size:9px; letter-spacing:.3px; color:#64748b; }
        #imHistoryPop .hp-row { display:flex; gap:6px; align-items:flex-start; padding:1px 0; }
        #imHistoryPop .hp-row .mk { min-width:16px; font-weight:700; }
        #imHistoryPop .hp-row .mk.ok { color:#16a34a; }
        #imHistoryPop .hp-row .mk.bad { color:#dc2626; }
        #imHistoryPop .hp-row .nm { font-weight:600; min-width:92px; }
        #imHistoryPop .hp-row .msg { color:#64748b; white-space:normal; word-break:break-word; }
        .im-slot-th { min-width:84px; text-align:center; }
        .im-slot-th .im-slot-name { width:104px; max-width:100%; text-align:center; background:transparent; border:1px solid transparent; color:#fff; font-size:10px; font-weight:700; text-transform:uppercase; padding:1px 3px; border-radius:4px; }
        .im-slot-th .im-slot-name:hover, .im-slot-th .im-slot-name:focus { background:rgba(255,255,255,.18); border-color:rgba(255,255,255,.6); outline:none; }
        .im-slot-th .im-slot-name::placeholder { color:rgba(255,255,255,.7); }
        .im-slot-thumb { width:56px; height:56px; object-fit:cover; border-radius:6px; border:1px solid #e2e8f0; background:#f8fafc; }
        .im-view-btn, .im-view-btn:hover, .im-view-btn:focus, .im-view-btn:active { width:auto; height:auto; padding:2px 4px; display:inline-flex; align-items:center; justify-content:center; font-size:13px; line-height:1; color:#000 !important; background:transparent !important; border:0 !important; box-shadow:none !important; }
        .im-view-btn:hover { opacity:.65; }
        .im-slot-cell { position:relative; display:inline-block; line-height:0; }
        .im-ai-edit-btn { position:absolute; right:-6px; bottom:-6px; width:22px; height:20px; padding:0; display:inline-flex; align-items:center; justify-content:center; font-size:9px; line-height:1; color:#475569; background:#fff; border:1px solid #94a3b8; border-radius:5px; cursor:pointer; box-shadow:0 1px 3px rgba(0,0,0,.2); }
        .im-ai-edit-btn { opacity:0; pointer-events:none; transition:opacity .12s; }
        .im-slot-cell:hover .im-ai-edit-btn, .im-ai-edit-btn:focus-visible { opacity:1; pointer-events:auto; }
        .im-ai-edit-btn:hover { background:#2563eb; border-color:#2563eb; color:#fff; }
        .ai-edit-pane { border:1px solid #e2e8f0; border-radius:10px; padding:8px; text-align:center; background:#f8fafc; min-height:260px; display:flex; flex-direction:column; align-items:center; justify-content:center; }
        .ai-edit-pane img { max-width:100%; max-height:340px; object-fit:contain; border-radius:6px; background:#fff; }
        .ai-edit-thumbs { display:flex; flex-wrap:wrap; gap:6px; justify-content:center; margin-top:8px; }
        .ai-edit-thumbs img { width:64px; height:64px; object-fit:cover; border-radius:6px; border:2px solid #e2e8f0; cursor:pointer; background:#fff; }
        .ai-edit-thumbs img.sel { border-color:#16a34a; box-shadow:0 0 0 2px rgba(22,163,74,.25); }
        .ai-edit-pane .ai-edit-cap { font-size:11px; font-weight:700; color:#475569; margin-bottom:6px; text-transform:uppercase; }
        .im-prompts-menu .ip-row { display:flex; align-items:center; gap:10px; padding:4px 6px; border-bottom:1px solid #f1f5f9; }
        .im-prompts-menu .ip-row:last-child { border-bottom:none; }
        .im-prompts-menu .ip-name { flex:1; font-size:12px; font-weight:600; color:#334155; white-space:nowrap; }
        .im-prompts-menu .ip-dot { width:8px; height:8px; border-radius:50%; background:#22c55e; display:inline-block; margin-left:6px; }
        .hero-row { display:grid; grid-template-columns:22px 92px 1fr 40px; gap:8px; align-items:center; padding:4px 0; border-bottom:1px solid #f1f5f9; font-size:12px; }
        .hero-row-head { font-weight:700; font-size:10px; text-transform:uppercase; color:#64748b; border-bottom:2px solid #e2e8f0; }
        .hero-row-all { background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:6px 8px; }
        .hero-mp { font-weight:600; color:#334155; }
        .hero-mp-logo { max-height:26px; max-width:100%; object-fit:contain; display:block; }
        /* Main Image modal: half width, docked to the right side of the page */
        #heroImageModal .modal-dialog { max-width:min(400px, 96vw); margin-left:auto; margin-right:12px; }
        .hero-thumbs { display:flex; gap:4px; }
        .hero-thumbs img { width:32px; height:32px; object-fit:cover; border-radius:4px; border:1px solid #e2e8f0; background:#f8fafc; }
        .im-icon-expand { display:inline-flex; align-items:center; }
        .im-icon-expand .im-icon-expand-text { max-width:0; overflow:hidden; white-space:nowrap; opacity:0; margin-left:0; transition:max-width .25s ease, opacity .2s ease, margin-left .25s ease; }
        .im-icon-expand:hover .im-icon-expand-text, .im-icon-expand:focus-visible .im-icon-expand-text { max-width:140px; opacity:1; margin-left:6px; }
        .im-col-hidden { display:none!important; }
        .im-mp-open { width:30px; height:30px; padding:0; display:inline-flex; align-items:center; justify-content:center; border-radius:50%; }
        .mpg-wrap { display:flex; gap:12px; align-items:flex-start; overflow-x:auto; padding-bottom:6px; }
        .mpg-col { flex:0 0 210px; border:1px solid #e2e8f0; border-radius:10px; background:#f8fafc; }
        .mpg-col.mpg-over { border-color:#6366f1; box-shadow:0 0 0 2px rgba(99,102,241,.2); }
        .mpg-head { display:flex; align-items:center; gap:6px; padding:8px; border-bottom:1px solid #e2e8f0; background:linear-gradient(135deg,#2c6ed5 0%,#1a56b7 100%); color:#fff; border-radius:9px 9px 0 0; }
        .mpg-head .mpg-name-input { flex:1; min-width:0; background:transparent; border:1px solid transparent; color:#fff; font-size:11px; font-weight:700; text-transform:uppercase; padding:1px 4px; border-radius:4px; }
        .mpg-head .mpg-name-input:focus { background:rgba(255,255,255,.18); border-color:rgba(255,255,255,.6); outline:none; }
        .mpg-head .mpg-count { font-size:10px; opacity:.85; white-space:nowrap; }
        .mpg-head .mpg-del { background:none; border:none; color:#fff; opacity:.75; padding:0 2px; cursor:pointer; font-size:12px; }
        .mpg-head .mpg-del:hover { opacity:1; }
        .mpg-list { min-height:56px; padding:8px; display:flex; flex-direction:column; gap:5px; }
        .mpg-list:empty::after { content:'Drop marketplaces here'; color:#94a3b8; font-size:11px; text-align:center; padding:14px 0; }
        .mpg-item { display:flex; align-items:center; gap:6px; background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:4px 6px; cursor:grab; font-size:11px; margin:0; }
        .mpg-item.dragging { opacity:.4; }
        .mpg-item.disabled { opacity:.55; cursor:grab; }
        .mpg-item .marketplace-btn { width:26px; height:22px; font-size:9px; cursor:inherit; flex-shrink:0; }
        .mpg-item .mpg-lbl { flex:1; min-width:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-weight:600; color:#334155; }
        .mpg-add-bar { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
        #im-master-table thead th[data-sort] { cursor:pointer; user-select:none; white-space:nowrap; }
        #im-master-table thead th[data-sort]:hover { filter:brightness(1.12); }
        .im-badge-row { display:grid; grid-auto-flow:column; grid-auto-columns:1fr; gap:8px; align-items:center; width:max-content; }
        .im-badge-row .im-stat-badge { display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:600; padding:6px 12px; min-width:130px; white-space:nowrap; }
        #im-master-table thead th .im-sort-ind { display:inline-block; margin-left:4px; font-size:9px; opacity:.45; }
        #im-master-table thead th.im-sorted .im-sort-ind { opacity:1; }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('layouts.shared/page-title', [
        'page_title' => 'Image Master',
        'sub_title' => 'Product images by marketplace',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card im-master-card">
                <div class="card-body">
                    <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
                        <button type="button" id="exportBtn" class="btn btn-primary btn-sm" title="Export" aria-label="Export"><i class="fas fa-download"></i></button>
                        <button type="button" id="heroImageBtn" class="btn btn-warning btn-sm" title="Choose the main image per marketplace for the selected rows (or all rows in view if none selected)"><i class="fas fa-star"></i> Main</button>
                        <div class="dropdown" id="promptsDropdown">
                            <button type="button" id="promptsBtn" class="btn btn-dark btn-sm dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"><i class="fas fa-wand-magic-sparkles"></i> Prompts</button>
                            <div class="dropdown-menu im-prompts-menu p-2" id="promptsMenu" style="min-width:260px;max-height:70vh;overflow:auto;"></div>
                        </div>
                        <button type="button" id="pushPickerBtn" class="btn btn-outline-primary btn-sm" title="Push selected rows to marketplaces" aria-label="Push"><i class="fas fa-cloud-upload-alt"></i></button>
                        <input type="text" id="parentSearchIm" class="form-control form-control-sm" style="width:150px;" placeholder="Search Parent" title="Search Parent">
                        <input type="text" id="skuSearchIm" class="form-control form-control-sm" style="width:150px;" placeholder="Search SKU" title="Search SKU">
                        <select id="statusFilter" class="form-select form-select-sm" style="width:auto;" title="Filter by status"><option value="all">Status: All</option></select>
                        <select id="rowTypeFilter" class="form-select form-select-sm" style="width:auto;" title="Which rows to list: SKU rows, PARENT rows, or both">
                            <option value="sku" selected>SKU</option>
                            <option value="parent">Parent</option>
                            <option value="both">Both</option>
                        </select>
                        <select id="invFilter" class="form-select form-select-sm" style="width:auto;" title="INV filter (Shopify inventory)">
                            <option value="gt0" selected>Inv&gt;0</option>
                            <option value="eq0">Inv=0</option>
                            <option value="all">All</option>
                        </select>
                        <div class="im-badge-row">
                            <span class="badge bg-secondary im-stat-badge" id="rowCountBadge">0 products</span>
                            <span class="badge bg-success im-stat-badge" id="missingImagesBadge" title="SKUs with fewer than 14 images (red in the Images column)">Missing images: 0</span>
                            <span class="badge bg-success im-stat-badge" id="auditAlertBadge" title="Rows with an open audit task (red triangle)">Audit alerts: 0</span>
                            <span class="badge bg-success im-stat-badge" id="auditOverdueBadge" title="Rows not audited in over 30 days (yellow dot)">Audit overdue: 0</span>
                            <span class="badge bg-dark im-stat-badge" id="selectedCountBadge">0 selected</span>
                        </div>
                    </div>
                    <div id="pushProgressBox" class="alert alert-info mb-3 py-2 px-3" style="display:none;word-break:break-word;">
                        <div class="d-flex align-items-center gap-2">
                            <div class="spinner-border spinner-border-sm text-primary" id="pushSpinner" role="status"></div>
                            <div class="small fw-semibold flex-grow-1" id="pushProgressTitle">Pushing...</div>
                            <button type="button" class="btn-close btn-sm" id="pushProgressClose" style="font-size:10px;" onclick="document.getElementById('pushProgressBox').style.display='none'"></button>
                        </div>
                        <div class="progress mt-2" id="pushProgressBarWrap" style="height:16px;display:none;">
                            <div id="pushProgressBar" class="progress-bar bg-info" role="progressbar" style="width:0%;font-size:10px;line-height:16px;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="small mt-2" id="pushProgressDetails" style="max-height:200px;overflow-y:auto;white-space:pre-wrap;"></div>
                    </div>
                    <div class="table-responsive">
                        <table id="im-master-table" class="table w-100">
                            <thead>
                                <tr>
                                    <th style="width:36px;text-align:center;" data-sort="selected" title="Sort by selected">
                                        <input type="checkbox" class="form-check-input" id="selectAllRowsIm" title="Select all visible rows" aria-label="Select all visible rows">
                                    </th>
                                    <th data-sort="parent">Parent</th>
                                    <th data-sort="sku">SKU</th>
                                    <th data-sort="auditst" style="text-align:center;" title="Red triangle = audit task still open · Yellow dot = not audited in over 30 days · Green = audited, nothing open">Audit</th>
                                    <th data-sort="inv" style="text-align:center;" title="Shopify inventory (parents show the total of their SKUs)">INV</th>
                                    <th data-sort="status" style="text-align:center;" title="Status from Product Master">Status</th>
                                    <th style="width:72px;text-align:center;" data-sort="images" title="Saved Product Master images for this SKU">Images</th>
                                    <th data-sort="name">Product Name</th>
                                    <th id="imActionTh">Action</th>
                                    <th style="width:54px;text-align:center;" data-sort="mp" title="Open marketplaces picker (groups, checkboxes, push)">MP</th>
                                    <th class="im-col-hidden" title="eBay1–3, Macy's, Amz, Temu, Reverb, Wayfair, Best Buy">
                                        <div class="bp-mp-th-title">MARKET PLACES</div>
                                        <div class="bp-mp-th-icons">
                                            <span class="bp-mp-th-pill btn-ebay1">E1</span><span class="bp-mp-th-pill btn-ebay2">E2</span><span class="bp-mp-th-pill btn-ebay3">E3</span><span class="bp-mp-th-pill btn-macy">M</span><span class="bp-mp-th-pill btn-amazon">A</span><span class="bp-mp-th-pill btn-temu">T</span><span class="bp-mp-th-pill btn-reverb">R</span><span class="bp-mp-th-pill btn-wayfair">W</span><span class="bp-mp-th-pill btn-bestbuy">B</span>
                                        </div>
                                    </th>
                                    <th data-sort="history" title="Hover a row's History to see the last image change or push">History</th>
                                    <th data-sort="audit" title="Audit tasks raised for this SKU (hover for details)">Audit History</th>
                                </tr>
                            </thead>
                            <tbody id="table-body"></tbody>
                        </table>
                    </div>
                    <div id="rainbow-loader" class="text-center py-4" style="display:none;">
                        <div class="spinner-border text-primary"></div>
                        <div class="mt-2 text-muted small">Loading Image Master…</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editImModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient flex-wrap gap-y-1">
                    <h5 class="modal-title text-nowrap"><i class="fas fa-images me-2"></i>Edit product images</h5>
                    <div class="d-flex align-items-center gap-3 ms-3 text-white text-nowrap">
                        <span><strong>SKU:</strong> <span id="modalSkuLabel"></span></span>
                        <span><strong>Parent:</strong> <span id="modalProductLabel"></span></span>
                        <button type="button" class="btn btn-light btn-sm" id="specsBtn" title="View the specifications of this SKU">Specs</button>
                        <button type="button" class="btn btn-light btn-sm" id="descBtn" title="View the description of this SKU">Desc</button>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap ms-3 me-auto small text-white">
                        <label class="btn btn-light btn-sm mb-0" for="modalFileInput" style="cursor:pointer;" title="Upload images (max 20)" aria-label="Upload images">
                            <i class="fas fa-upload"></i>
                        </label>
                        <input type="file" class="d-none" id="modalFileInput" accept=".jpg,.jpeg,.png,.webp" multiple>
                        <span id="fileChosenLabel" style="opacity:.85;"></span>
                        <button type="button" class="btn btn-success btn-sm" id="uploadImagesBtn" style="display:none;">
                            <i class="fas fa-plus me-1"></i>Add to list
                        </button>
                        <div class="spinner-border spinner-border-sm text-light" id="uploadSpinner" role="status" style="display:none;"></div>
                        <span class="fw-semibold" id="uploadSuccessMsg" style="display:none;"></span>
                    </div>
                    <div class="d-flex align-items-center gap-1 me-2">
                        <button type="button" class="btn btn-warning btn-sm fw-semibold" id="auditBtn" title="Raise an audit task for this SKU in Task Manager"><i class="fas fa-clipboard-check me-1"></i>Audit</button>
                        <button type="button" class="btn btn-success btn-sm" id="savePmBtn" title="Save images" aria-label="Save images"><i class="fas fa-save"></i></button>
                        <button type="button" class="btn btn-light btn-sm" id="downloadAllImagesBtn" style="display:none;" title="Download all images of this SKU in the shown order as a ZIP file" aria-label="Download all (ZIP)">
                            <i class="fas fa-download"></i>
                        </button>
                        <button type="button" class="btn btn-light btn-sm text-danger" id="removeAllImagesBtn" style="display:none;" title="Remove all images" aria-label="Remove all">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="modalSku">

                    {{-- Upload controls live in the modal header; only the pre-upload preview stays here --}}
                    <div id="uploadPreviewList" class="border rounded p-2 mb-3" style="display:none;background:#f8fafc;">
                        <div class="small text-muted mb-1">Selected files will be uploaded when you click <strong>Add to list</strong> or <strong>Save Images</strong>.</div>
                        <div id="uploadPreviewItems" class="d-flex flex-wrap gap-2"></div>
                    </div>

                    <!-- <div class="mb-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="fetchAmazonBtn"><i class="fab fa-amazon"></i> Fetch Amz images</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="fetchEbay1Btn"><i class="fab fa-ebay"></i> Fetch eBay1</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="fetchEbay2Btn">eBay2</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="fetchEbay3Btn">eBay3</button>
                    </div> -->
                    <div class="fw-semibold small mb-1 d-flex align-items-center justify-content-between gap-2 flex-wrap">
                        <span>Order — drag a card onto another to <strong>swap</strong> (hold <kbd>Shift</kbd> to insert), or park it in the sandbox below</span>
                        <div class="d-flex align-items-center gap-2">
                        </div>
                    </div>
                    <div id="imSlots"></div>
                    <div class="alert alert-info small mt-3 mb-0">
                        Save images here first. Use the <strong>Main Image</strong> button in the toolbar to choose the main image per marketplace, and the MP button in the table row to push.
                    </div>
                </div>
            </div>
        </div>
    </div>

    </div>

    <div class="modal fade" id="auditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title"><i class="fas fa-clipboard-check me-2"></i>Audit</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="auditAlert" class="small mb-2"></div>
                    <div class="mb-2">
                        <label for="auditGroup" class="form-label fw-bold small mb-1">Group</label>
                        <input type="text" class="form-control form-control-sm" id="auditGroup" placeholder="Group" autocomplete="off">
                    </div>
                    <div class="mb-2">
                        <label for="auditTitle" class="form-label fw-bold small mb-1">Task <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="auditTitle" placeholder="Enter Task" autocomplete="off">
                    </div>
                    <div class="mb-2">
                        <label for="auditScreens" class="form-label fw-bold small mb-1"><i class="fas fa-camera me-1"></i>Screenshots</label>
                        <input type="file" class="form-control form-control-sm" id="auditScreens" accept="image/*" multiple>
                        <div class="form-text" style="font-size:10px;">Optional. Max 12.</div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-bold small mb-1">Assignee</label>
                        <input type="text" class="form-control form-control-sm" id="auditAssignee" value="images3@5core.com" readonly style="background:#e9ecef;">
                    </div>
                    <div class="row g-2">
                        <div class="col-4">
                            <label for="auditEtc" class="form-label fw-bold small mb-1">ETC (Min)</label>
                            <input type="number" class="form-control form-control-sm" id="auditEtc" value="10" min="1">
                        </div>
                        <div class="col-4">
                            <label for="auditPriority" class="form-label fw-bold small mb-1">Priority</label>
                            <select class="form-select form-select-sm" id="auditPriority">
                                <option value="normal">Normal</option>
                                <option value="high">Urgent</option>
                                <option value="low">Low</option>
                            </select>
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-bold small mb-1">Assignor</label>
                            <input type="text" class="form-control form-control-sm" value="{{ auth()->user()->name ?? '' }}" readonly style="background:#e9ecef;" title="{{ auth()->user()->email ?? '' }}">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success btn-sm" id="auditSaveBtn"><i class="fas fa-save me-1"></i>Save</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="skuTextModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title"><i class="fas fa-file-lines me-2"></i><span id="skuTextTitle">Specs</span> — <span id="skuTextSku"></span></h5>
                    <button type="button" class="btn btn-light btn-sm ms-auto me-2" id="skuTextCopyBtn" title="Copy to clipboard" aria-label="Copy"><i class="fas fa-copy"></i></button>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="small text-muted mb-2" id="skuTextTitleLine"></div>
                    <div id="skuTextBody"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="shopifyPullModal" tabindex="-1" aria-labelledby="shopifyPullModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title" id="shopifyPullModalTitle"><i class="fas fa-download me-2"></i>Shopify Image Pull</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning small mb-3">
                        This imports current product images from Shopify into Product Master only. It does not push anything back to Shopify.
                    </div>
                    <div class="small text-muted mb-2" id="shopifyPullScopeText">Scope: currently filtered SKUs.</div>
                    <div id="shopifyPullPanel" class="border rounded bg-light p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong>Progress</strong>
                            <span class="small text-muted" id="shopifyPullStatus">Ready</span>
                        </div>
                        <div class="progress mb-3" style="height: 12px;">
                            <div id="shopifyPullProgress" class="progress-bar bg-warning" role="progressbar" style="width:0%"></div>
                        </div>
                        <div id="shopifyPullLog" class="small font-monospace bg-white border rounded p-2" style="max-height:280px; overflow:auto;"></div>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" id="startShopifyPullBtn" class="btn btn-warning"><i class="fas fa-play"></i> Run in BG</button>
                    <button type="button" id="pauseShopifyPullBtn" class="btn btn-outline-warning" style="display:none;"><i class="fas fa-pause"></i> Pause</button>
                    <button type="button" id="resumeShopifyPullBtn" class="btn btn-outline-success" style="display:none;"><i class="fas fa-play"></i> Resume</button>
                    <button type="button" id="stopShopifyPullBtn" class="btn btn-outline-danger" style="display:none;"><i class="fas fa-stop"></i> Stop</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="shopifyPullConfirmModal" tabindex="-1" aria-labelledby="shopifyPullConfirmTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning-subtle">
                    <h5 class="modal-title" id="shopifyPullConfirmTitle"><i class="fas fa-triangle-exclamation me-2"></i>Confirm Shopify Pull</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2" id="shopifyPullConfirmScope">Do you want to pull images from Shopify?</p>
                    <div class="alert alert-warning small mb-0">
                        This action will update the existing Product Master image fields. It will not update Shopify.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" id="shopifyPullConfirmCancelBtn" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" id="shopifyPullConfirmBtn"><i class="fas fa-download"></i> Yes, Pull Images</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── AI edit modal (Nano Banana) ────────────────────────────── --}}
    <div class="modal fade" id="aiEditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title"><i class="fas fa-pen me-2"></i>Edit <span id="aiEditName"></span> — <span id="aiEditSku"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="ai-edit-pane"><div class="ai-edit-cap">Current image</div><div id="aiEditOriginal"></div></div>
                        </div>
                        <div class="col-md-6">
                            <div class="ai-edit-pane"><div class="ai-edit-cap">New image (Nano Banana)</div><div id="aiEditResult"><span class="text-muted small">Click <strong>Prompt</strong> to generate.</span></div></div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label small fw-semibold mb-1">Prompt for this image</label>
                        <textarea id="aiEditPrompt" class="form-control" rows="4" placeholder="Describe how to change this image…"></textarea>
                        <div id="aiEditMsg" class="small mt-2"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" id="aiEditSavePromptBtn"><i class="fas fa-save me-1"></i>Save prompt</button>
                    <div class="d-flex align-items-center gap-1" title="How many images to generate each time">
                        <label for="aiEditCount" class="small text-muted mb-0">Images</label>
                        <select id="aiEditCount" class="form-select form-select-sm" style="width:64px;"><option>1</option><option>2</option><option>3</option><option>4</option></select>
                    </div>
                    <button type="button" class="btn btn-primary" id="aiEditGenerateBtn" title="Generate new image(s) from this prompt"><i class="fas fa-wand-magic-sparkles me-1"></i><span id="aiEditGenLabel">Prompt</span></button>
                    <button type="button" class="btn btn-success" id="aiEditApplyBtn" style="display:none;"><i class="fas fa-check me-1"></i>Use selected image (overwrite)</button>
                    <button type="button" class="btn btn-secondary ms-auto" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Slot prompt modal ──────────────────────────────────────── --}}
    <div class="modal fade" id="slotPromptModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title"><i class="fas fa-wand-magic-sparkles me-2"></i>Prompt — <span id="slotPromptName"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <textarea id="slotPromptText" class="form-control" rows="10" placeholder="Write the prompt for this image…"></textarea>
                    <div id="slotPromptMsg" class="small mt-2"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary me-auto" id="slotPromptCopyBtn"><i class="fas fa-copy me-1"></i>Copy</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" id="slotPromptSaveBtn"><i class="fas fa-save me-1"></i>Save</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Hero image modal ───────────────────────────────────────── --}}
    <div class="modal fade" id="heroImageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title"><i class="fas fa-star me-2"></i>Main Image — <span id="heroSkuLabel"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">When you push, the <strong>Main image</strong> chosen here is sent first on each marketplace. Default is Image 1.</p>
                    <div id="heroScopeNote" class="alert alert-warning small py-2 mb-3" style="display:none;"></div>
                    <label class="hero-row hero-row-selall mb-0" style="cursor:pointer;" title="Tick marketplaces, then change one of the ticked dropdowns to set them all at once">
                        <input type="checkbox" class="form-check-input m-0" id="heroSelAll">
                        <span class="hero-mp">Select all</span><span></span><span></span>
                    </label>
                    <div id="heroRows"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" id="heroSaveBtn"><i class="fas fa-save"></i> Save</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── MP picker modal (groups, drag & drop, checkboxes) ───────── --}}
    <div class="modal fade" id="mpPickerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title"><i class="fas fa-search me-2"></i>Marketplaces — <span id="mpPickerSku"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mpg-add-bar mb-3">
                        <input type="text" id="mpgNewName" class="form-control form-control-sm" style="max-width:220px;" placeholder="New column name">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="mpgAddBtn"><i class="fas fa-plus me-1"></i>Add column</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="mpgResetBtn" title="Restore default groups">Reset layout</button>
                        <span class="text-muted small ms-auto">Drag marketplaces between columns to regroup. Green dot = already pushed.</span>
                    </div>
                    <div id="mpgWrap" class="mpg-wrap"></div>
                    <div id="mpgOthers" class="mt-3"></div>
                </div>
                <div class="modal-footer">
                    <span class="me-auto small text-muted" id="mpgSelCount">0 selected</span>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-warning" id="mpgPushBtn"><i class="fas fa-cloud-upload-alt"></i> Push selected</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Push mode selection popup ──────────────────────────────── --}}
    <div class="modal fade" id="pushModeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header py-2" style="background:linear-gradient(135deg,#6B73FF 0%,#000DFF 100%);">
                    <h6 class="modal-title text-white mb-0"><i class="fas fa-cloud-upload-alt me-2"></i>How to push images?</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pb-1">
                    <p class="small mb-1">Pushing <strong id="pmImageCount"></strong> image(s) to <strong id="pmMpCount"></strong> marketplace(s).</p>
                    <p class="small text-muted mb-0">What should happen to the <strong>existing</strong> marketplace images?</p>
                    <p class="small text-warning mb-0 mt-2" id="pmAddHint" style="display:none;">
                        Add is only available when every selected marketplace is Shopify Main, Shopify PLS, or Reverb.
                    </p>
                </div>
                <div class="modal-footer flex-column gap-2 pt-2 pb-3 border-0">
                    <button type="button" class="btn btn-danger w-100" id="pmReplaceBtn">
                        <i class="fas fa-exchange-alt me-1"></i><strong>Replace</strong> — remove existing, use only selected
                    </button>
                    <button type="button" class="btn btn-success w-100" id="pmAddBtn">
                        <i class="fas fa-plus me-1"></i><strong>Add</strong> — keep existing, append selected
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm w-100" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>
    {{-- ── End push mode popup ─────────────────────────────────────── --}}

    <div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>
    <div id="imHistoryPop" role="tooltip"></div>
@endsection

@section('script')
@include('partials.marketplace-api-config')
@include('partials.all-marketplace-master-channels', ['allMpMaster' => 'image'])
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const __mp = window.__ALL_MP__ || {};
    const MARKETPLACES = __mp.marketplaces || [];
    const ENABLED_MARKETPLACES = __mp.enabled || [];
    const LABELS = __mp.labels || {};
    const MP_TILE = {};
    const MP_SHORT = {};
    Object.entries(__mp.tiles || {}).forEach(([k, v]) => {
        MP_TILE[k] = v.cls;
        MP_SHORT[k] = v.short;
    });
    const GROUPS = __mp.groups || { gChannels: [], gShopify: [] };
    const ADD_MODE_MARKETPLACES = ['shopify_main', 'shopify_pls', 'reverb'];
    const SHOPIFY_MARKETPLACES = ['shopify_main', 'shopify_pls'];
    const PM_MAX_IMAGES = 20;
    const MP_IMAGE_LIMITS = {
        ebay: 12, ebay2: 12, ebay3: 12, amazon: 9, temu: 12, temu2: 12, wayfair: 12, bestbuy: 12, macy: 12, reverb: 25,
        doba: 12, walmart: 12, faire: 12, shein: 12, aliexpress: 12,
        shopify_main: 20, shopify_pls: 20,
    };
    const EBAY3_WARN = 'eBay3 has different listing structure. Please verify images before pushing.';

    let tableData = [];
    let visibleFilteredRows = [];
    const bySku = new Map();
    const selectedSkus = new Set();
    let editModal;
    let shopifyPullModal;
    let shopifyPullConfirmModal;
    let shopifyPullPollTimer = null;
    let shopifyPullSelectedSkus = null;
    let shopifyPullConfirmResolver = null;
    /** Remaining SKU chunks queued after the active background pull job. */
    let shopifyPullChunkQueue = [];
    let shopifyPullChunkMeta = { totalSkus: 0, chunkIndex: 0, chunkCount: 0, ok: 0, fail: 0, cancelled: false };
    let shopifyPullAdvancingChunk = false;
    const SHOPIFY_PULL_CHUNK_SIZE = 25;
    let modalUrls = [];
    let modalSandbox = [];   // images parked in the drag & drop sandbox (not part of the saved order)
    let knownImageUrls = []; // every image URL seen this modal session (loaded + uploaded/fetched), to detect removals on Save
    let modalMainByMp = {};
    let modalHero2ByMp = {};   // mp -> image index (absent = automatic)
    let pendingFiles = [];
    let selectedUrls = new Set();   // URLs checked for push

    // Encode URL so spaces/special chars load correctly in <img src>.
    // Local storage URLs should load from the same host/port the user has open.
    const imgSrc = url => {
        try {
            const parsed = new URL(String(url), window.location.origin);
            if ((parsed.hostname === 'localhost' || parsed.hostname === '127.0.0.1') && parsed.pathname.startsWith('/storage/')) {
                return window.location.origin + encodeURI(decodeURIComponent(parsed.pathname)) + parsed.search;
            }
            return encodeURI(decodeURIComponent(String(url)));
        } catch(_) {
            return encodeURI(String(url));
        }
    };

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));

    function pushFailureMessage(j, mp, httpStatus) {
        const rowRes = (j && j.results && j.results[mp]) ? j.results[mp] : null;
        if (rowRes?.message) return String(rowRes.message);
        if (j?.message) return String(j.message);
        if (rowRes && rowRes.success === false) {
            return `${LABELS[mp] || mp} push failed (no error detail). Check server logs.`;
        }
        if (httpStatus) return `Request failed (HTTP ${httpStatus}).`;
        return 'Push failed.';
    }

    function isImagePushJobActive(status) {
        return status === 'running';
    }

    function sleepMs(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    async function fetchImagePushJobStatus() {
        const res = await fetch('/image-master/push/status', { headers: { 'Accept': 'application/json' } });
        const j = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(j.message || `HTTP ${res.status}`);
        return j;
    }

    function renderImagePushJobProgress(j, titleFallback) {
        const job = j?.job || {};
        const lines = (job.messages || []).slice(-25).map(item =>
            `<div class="${item.ok ? 'text-success' : 'text-danger'}">[${esc(item.time || '')}] ${esc(item.message || '')}</div>`
        );
        const title = job.last_message || titleFallback || 'Image push in progress…';
        const idx = Number(job.current_index ?? 0);
        const total = Number(job.total ?? 0);
        const detail = total > 0 ? `${title} (${idx}/${total})` : title;
        // Spinner spins only while the job is actually running (so the final render stops it).
        setPushProgress(true, detail, lines.join(''), false, isImagePushJobActive(job.status));
        setPushProgressBar(idx, total);
    }

    // On page load / refresh: if a background image push is still running, re-show its progress
    // and keep polling until it finishes — so refreshing the page doesn't lose the progress view.
    async function resumeImagePushProgress() {
        try {
            let j = await fetchImagePushJobStatus();
            if (!isImagePushJobActive(j?.job?.status)) return;
            renderImagePushJobProgress(j, 'Image push in progress…');
            while (isImagePushJobActive(j?.job?.status)) {
                await sleepMs(2500);
                j = await fetchImagePushJobStatus();
                renderImagePushJobProgress(j);
            }
            renderImagePushJobProgress(j); // final state; box stays until the user closes it
        } catch (_) { /* ignore */ }
    }

    async function queueImagePushAndWait(payload, onProgress) {
        setPushProgressBar(0, 0); // indeterminate until the job reports a total
        const res = await fetch('/image-master/push', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify(payload),
        });
        const rawText = await res.text();
        let j = null;
        try { j = rawText ? JSON.parse(rawText) : null; } catch (_) {}
        if (!j) {
            throw new Error(res.ok ? 'Invalid server response' : `HTTP ${res.status}${rawText ? ': ' + rawText.slice(0, 200) : ''}`);
        }
        if (j.dry_run || j.queued === false) {
            setPushProgressBar(1, 1, j?.success === false);
            return { res, j };
        }
        if (res.status === 409 && isImagePushJobActive(j?.job?.status)) {
            if (onProgress) onProgress(j);
        } else if (onProgress) {
            onProgress(j);
        }
        while (isImagePushJobActive(j?.job?.status)) {
            await sleepMs(2500);
            j = await fetchImagePushJobStatus();
            if (onProgress) onProgress(j);
        }
        const ft = Number(j?.job?.total ?? 0);
        setPushProgressBar(ft > 0 ? ft : 1, ft > 0 ? ft : 1, j?.success === false);
        return { res: { ok: j.success !== false, status: j.success ? 200 : 422 }, j };
    }

    function toast(msg, ok=true) {
        if (!window.bootstrap?.Toast) { alert(msg); return; }
        const id = 't'+Date.now();
        document.getElementById('toastContainer').insertAdjacentHTML('beforeend',
            `<div id="${id}" class="toast align-items-center text-bg-${ok?'success':'danger'} border-0"><div class="d-flex"><div class="toast-body">${esc(msg)}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>`);
        const el = document.getElementById(id);
        const t = new bootstrap.Toast(el, { delay: 3200 });
        t.show();
        el.addEventListener('hidden.bs.toast', () => el.remove());
    }

    // Visual progress bar inside the push box. total<=0 => indeterminate "Queued…" bar.
    function setPushProgressBar(done, total, hasError = false) {
        const wrap = document.getElementById('pushProgressBarWrap');
        const bar = document.getElementById('pushProgressBar');
        if (!wrap || !bar) return;
        wrap.style.display = '';
        const t = Number(total) || 0;
        if (t <= 0) {
            bar.className = 'progress-bar progress-bar-striped progress-bar-animated bg-info';
            bar.style.width = '100%';
            bar.textContent = 'Queued…';
            bar.setAttribute('aria-valuenow', '0');
            return;
        }
        const d = Math.max(0, Math.min(Number(done) || 0, t));
        const pct = Math.round((d / t) * 100);
        const finished = d >= t;
        bar.className = 'progress-bar ' + (hasError ? 'bg-danger' : (finished ? 'bg-success' : 'bg-info progress-bar-striped progress-bar-animated'));
        bar.style.width = pct + '%';
        bar.textContent = `${d}/${t} (${pct}%)`;
        bar.setAttribute('aria-valuenow', String(pct));
    }

    function setPushProgress(visible, title = '', detailsHtml = '', hasError = false, busy = false) {
        const box = document.getElementById('pushProgressBox');
        const t = document.getElementById('pushProgressTitle');
        const d = document.getElementById('pushProgressDetails');
        const spinner = document.getElementById('pushSpinner');
        if (!box || !t || !d) return;
        box.style.display = visible ? 'block' : 'none';
        if (title) t.textContent = title;
        d.innerHTML = detailsHtml || '';
        // Spinner only spins while a push is actively in progress (busy), not when finished.
        if (spinner) spinner.style.display = visible && busy && !hasError ? 'inline-block' : 'none';
        box.className = 'alert mb-3 py-2 px-3' + (hasError ? ' alert-danger' : ' alert-info');
        box.style.wordBreak = 'break-word';
        if (!visible) {
            const wrap = document.getElementById('pushProgressBarWrap');
            const bar = document.getElementById('pushProgressBar');
            if (wrap) wrap.style.display = 'none';
            if (bar) { bar.style.width = '0%'; bar.textContent = ''; bar.className = 'progress-bar bg-info'; }
        }
    }

    function confirmEbay3Push(mps) {
        if (!Array.isArray(mps) || !mps.includes('ebay3')) return true;
        return window.confirm(EBAY3_WARN);
    }

    function loadData() {
        document.getElementById('rainbow-loader').style.display = 'block';
        return fetch('/image-master-data').then(r=>r.json()).then(res => {
            const raw = Array.isArray(res.data) ? res.data : [];
            tableData = raw.filter(i => i && i.SKU); // PARENT rows are listed too (bulk push / pull still skip them)
            refreshStatusFilter();
            // INV = Shopify inventory; a PARENT row shows the total of its SKUs (like Product Master).
            const parentInv = {};
            tableData.forEach(r => {
                if (!String(r.SKU).toUpperCase().includes('PARENT') && r.Parent) parentInv[r.Parent] = (parentInv[r.Parent] || 0) + (Number(r.shopify_inv) || 0);
            });
            tableData.forEach(r => {
                r._inv = String(r.SKU).toUpperCase().includes('PARENT') ? (parentInv[r.Parent] || 0) : (Number(r.shopify_inv) || 0);
            });
            bySku.clear();
            tableData.forEach(r => bySku.set(String(r.SKU), r));
            renderTable(tableData);
        }).catch(e => toast('Load failed: '+e.message, false))
        .finally(() => { document.getElementById('rainbow-loader').style.display = 'none'; });
    }

    function hasPushedImages(val) {
        const s = (val || '').trim();
        if (!s || s === '[]') return false;
        try {
            const parsed = JSON.parse(s);
            return !(Array.isArray(parsed) && parsed.length === 0);
        } catch (_) {
            return true;
        }
    }

    function mpStackHtml(sku, mp, val) {
        const pushed = hasPushedImages(val);
        const tile = MP_TILE[mp] || 'btn-secondary';
        const short = MP_SHORT[mp] || mp;
        const st = mpPushTileState(mp, { label: LABELS[mp], implemented: ENABLED_MARKETPLACES.includes(mp) });
        return `<button type="button" class="bp-mp-stack${st.noApiClass}" data-push-mp="${esc(mp)}" data-sku="${esc(sku)}" data-api-configured="${st.configured ? '1' : '0'}" title="${esc(st.title)}" ${st.disabled ? 'disabled' : ''}>
            <span class="bp-mp-dot ${pushed?'pushed':''}"></span>
            <span class="marketplace-btn ${tile}">${esc(short)}</span>
        </button>`;
    }

    function groupCell(gkey, sku, row) {
        const keys = (GROUPS[gkey] || []).filter((mp) => ENABLED_MARKETPLACES.includes(mp));
        const im = row.image_master || {};
        return `<div class="marketplaces-cell"><div class="bp-mp-inline">${keys.map(mp => mpStackHtml(sku, mp, im[mp]||'')).join('')}</div></div>`;
    }

    function updateSelectedCount() {
        const badge = document.getElementById('selectedCountBadge');
        if (badge) badge.textContent = selectedSkus.size + ' selected';
    }

    function syncSelectAllCheckbox() {
        const selectAll = document.getElementById('selectAllRowsIm');
        if (!selectAll) return;
        const visibleSkus = visibleFilteredRows.map(r => String(r.SKU || '')).filter(Boolean);
        if (!visibleSkus.length) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
            return;
        }
        const selectedVisible = visibleSkus.filter(sku => selectedSkus.has(sku));
        selectAll.checked = selectedVisible.length === visibleSkus.length;
        selectAll.indeterminate = selectedVisible.length > 0 && selectedVisible.length < visibleSkus.length;
    }

    /** Header checkbox: fill/clear all visible row checkboxes. */
    function fillAllVisibleRows(checked) {
        visibleFilteredRows.forEach(r => {
            const sku = String(r.SKU || '');
            if (!sku) return;
            if (checked) selectedSkus.add(sku);
            else selectedSkus.delete(sku);
        });
        document.querySelectorAll('.row-select-im[data-sku]').forEach(cb => {
            cb.checked = !!checked;
        });
        updateSelectedCount();
        syncSelectAllCheckbox();
    }

    function bindRowCheckboxEvents(root = document) {
        root.querySelectorAll('.row-select-im[data-sku]').forEach(cb => {
            cb.addEventListener('change', function() {
                const sku = String(this.dataset.sku || '');
                if (!sku) return;
                if (this.checked) selectedSkus.add(sku);
                else selectedSkus.delete(sku);
                updateSelectedCount();
                syncSelectAllCheckbox();
            });
        });
    }

    function fmtHistoryDate(raw, withTime = true) {
        if (!raw) return '';
        const d = new Date(String(raw).replace(' ', 'T'));
        if (isNaN(d.getTime())) return String(raw);
        const opts = withTime
            ? { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }
            : { month: 'short', day: 'numeric', year: 'numeric' };
        return d.toLocaleString(undefined, opts);
    }

    /** Compact "1 APR" label; the year is shown in the hover popup. */
    function fmtHistoryShort(raw) {
        if (!raw) return '';
        const d = new Date(String(raw).replace(' ', 'T'));
        if (isNaN(d.getTime())) return String(raw);
        return d.getDate() + ' ' + d.toLocaleString('en-US', { month: 'short' }).toUpperCase();
    }

    const HIST_ACTION_LABEL = {
        add: 'Added', edit: 'Edited', remove: 'Removed', reorder: 'Reordered',
        upload: 'Uploaded', delete: 'Deleted', shopify_pull: 'Shopify pull', push: 'Pushed',
    };
    const histTs = (raw) => { const t = new Date(String(raw || '').replace(' ', 'T')).getTime(); return isNaN(t) ? null : t; };

    /** Most recent event of any kind (image change or marketplace push) for a row. */
    function historyLatest(r) {
        const acts = Array.isArray(r.activity) ? r.activity : [];
        const h = r.push_history;
        let best = null;
        if (acts.length) {
            const t = histTs(acts[0].at);
            if (t !== null) best = { ts: t, at: acts[0].at, kind: acts[0].action };
        }
        if (h && h.last_at) {
            const t = histTs(h.last_at);
            if (t !== null && (!best || t > best.ts)) best = { ts: t, at: h.last_at, kind: 'push' };
        }
        return best;
    }

    function historyCellHtml(sku, r) {
        const latest = historyLatest(r);
        if (!latest) return '<span class="text-muted">—</span>';
        const h = r.push_history;
        const acts = Array.isArray(r.activity) ? r.activity : [];
        let line2;
        if (latest.kind === 'push' && h) {
            const pushed = Array.isArray(h.pushed) ? h.pushed : [];
            const ok = pushed.filter(p => p.success).length;
            const bad = pushed.length - ok;
            line2 = `<span class="ok">${ok}✓</span>${bad ? ` <span class="bad">${bad}✗</span>` : ''}`;
        } else {
            line2 = `<span class="im-hist-act">${esc(HIST_ACTION_LABEL[latest.kind] || latest.kind)}</span>`;
        }
        const total = acts.length + (h && h.last_at ? 1 : 0);
        return `<span class="im-hist" data-history-sku="${esc(sku)}">
            <span class="im-hist-date"><i class="far fa-clock me-1"></i>${esc(fmtHistoryShort(latest.at))}</span>
            <span class="im-hist-counts">${line2}${total > 1 ? ` <span class="text-muted">· ${total}</span>` : ''}</span>
        </span>`;
    }

    function pushPopHtml(sku, h) {
        const pushed = Array.isArray(h.pushed) ? h.pushed : [];
        const ok = pushed.filter(p => p.success);
        const bad = pushed.filter(p => !p.success);
        const who = h.user_name || h.user_email
            ? `${esc(h.user_name || h.user_email)}${h.user_email && h.user_name ? ` (${esc(h.user_email)})` : ''}${h.user_id ? ` · ID #${esc(h.user_id)}` : ''}`
            : '<span class="text-muted">Unknown (pushed before history tracking)</span>';
        const line = (p, isOk) => `<div class="hp-row"><span class="mk ${isOk ? 'ok' : 'bad'}">${isOk ? '✓' : '✗'}</span><span class="nm">${esc(LABELS[p.marketplace] || p.marketplace)}</span>${!isOk && p.message ? `<span class="msg">${esc(p.message)}</span>` : ''}</div>`;
        const lastSuccess = Object.entries(h.last_success || {})
            .filter(([mp]) => !ok.some(p => p.marketplace === mp))
            .sort((a, b) => String(b[1]).localeCompare(String(a[1])));
        return `<div class="hp-section">Last marketplace push</div>
            <div class="hp-meta"><div><b>Date:</b> ${esc(fmtHistoryDate(h.last_at))}</div><div><b>By:</b> ${who}</div>${h.mode ? `<div><b>Mode:</b> ${esc(h.mode)}</div>` : ''}</div>
            ${ok.length ? `<div class="hp-section">Pushed successfully (${ok.length})</div>${ok.map(p => line(p, true)).join('')}` : ''}
            ${bad.length ? `<div class="hp-section">Failed (${bad.length})</div>${bad.map(p => line(p, false)).join('')}` : ''}
            ${lastSuccess.length ? `<div class="hp-section">Earlier successful pushes</div>${lastSuccess.map(([mp, at]) => `<div class="hp-row"><span class="mk ok">✓</span><span class="nm">${esc(LABELS[mp] || mp)}</span><span class="msg">${esc(fmtHistoryDate(at))}</span></div>`).join('')}` : ''}`;
    }

    function historyPopHtml(sku, r) {
        const acts = Array.isArray(r.activity) ? r.activity : [];
        const h = r.push_history;
        const actHtml = acts.map(a => {
            const who = a.user_name || a.user_email || 'System';
            return `<div class="hp-row"><span class="mk ok">●</span><span class="msg" style="color:#0f172a;">
                <b>${esc(HIST_ACTION_LABEL[a.action] || a.action)}</b> — ${esc(a.summary || '')}<br>
                <span style="color:#64748b;">${esc(fmtHistoryDate(a.at))} · ${esc(who)}</span></span></div>`;
        }).join('');
        return `<div class="hp-title">${esc(sku)} — history</div>
            ${acts.length ? `<div class="hp-section">Image changes (latest ${acts.length})</div>${actHtml}` : ''}
            ${h && h.last_at ? pushPopHtml(sku, h) : ''}`;
    }

    (function bindHistoryHover() {
        const pop = document.getElementById('imHistoryPop');
        const tbody = document.getElementById('table-body');
        if (!pop || !tbody) return;
        const hide = () => { pop.style.display = 'none'; };
        tbody.addEventListener('mouseover', (e) => {
            const el = e.target.closest('[data-history-sku],[data-audit-sku]');
            if (!el) return;
            const isAudit = el.dataset.auditSku !== undefined;
            const sku = isAudit ? el.dataset.auditSku : el.dataset.historySku;
            const hr = bySku.get(sku);
            if (!hr) return;
            if (isAudit) {
                if (!Array.isArray(hr.audits) || !hr.audits.length) return;
                pop.innerHTML = auditPopHtml(sku, hr);
            } else {
                if (!historyLatest(hr)) return;
                pop.innerHTML = historyPopHtml(sku, hr);
            }
            pop.style.display = 'block';
            const r = el.getBoundingClientRect();
            const pw = pop.offsetWidth, ph = pop.offsetHeight;
            let left = r.right + 8;
            if (left + pw > window.innerWidth - 8) left = Math.max(8, r.left - pw - 8);
            let top = r.top;
            if (top + ph > window.innerHeight - 8) top = Math.max(8, window.innerHeight - ph - 8);
            pop.style.left = left + 'px';
            pop.style.top = top + 'px';
        });
        tbody.addEventListener('mouseout', (e) => {
            const el = e.target.closest('[data-history-sku],[data-audit-sku]');
            if (el && !el.contains(e.relatedTarget)) hide();
        });
        document.querySelector('.table-responsive')?.addEventListener('scroll', hide, { passive: true });
    })();

    // ── Status column: same value and look as the STATUS column on Product Master ──
    function pmStatusRaw(r) {
        let raw = r && r.status;
        if ((raw === null || raw === undefined || raw === '') && r && r.Values) {
            let V = r.Values;
            if (typeof V === 'string') { try { V = JSON.parse(V); } catch (_) { V = {}; } }
            raw = V && V.status;
        }
        return String(raw || '').trim();
    }
    function statusCellHtml(r) {
        const raw = pmStatusRaw(r);
        if (!raw) return '<span class="text-muted">—</span>';
        const lower = raw.toLowerCase(), upper = raw.toUpperCase();
        const mod = lower === 'active' ? 'active' : lower === 'inactive' ? 'inactive' : upper === 'DC' ? 'dc' : lower === 'upcoming' ? 'upcoming' : upper === '2BDC' ? '2bdc' : 'muted';
        const label = lower === 'upcoming' ? 'Coming' : lower === 'active' ? 'Active' : lower === 'inactive' ? 'Inactive' : raw;
        return `<span class="im-status-cell"><span class="im-status-marble im-status-marble--${mod}" title="${esc(label)}" aria-label="${esc(label)}"></span></span>`;
    }
    const statusLabelOf = (raw) => { const l = String(raw).toLowerCase(); return l === 'upcoming' ? 'Coming' : l === 'active' ? 'Active' : l === 'inactive' ? 'Inactive' : raw; };

    /** Status dropdown (header): one option per value that exists in the data, plus Missing. */
    function refreshStatusFilter() {
        const sel = document.getElementById('statusFilter');
        if (!sel) return;
        const prev = sel.value || 'all';
        const seen = new Map();
        let missing = false;
        tableData.forEach(r => {
            const raw = pmStatusRaw(r);
            if (!raw) { missing = true; return; }
            if (!seen.has(raw.toLowerCase())) seen.set(raw.toLowerCase(), statusLabelOf(raw));
        });
        const order = ['active', 'inactive', 'dc', 'upcoming', '2bdc'];
        const keys = [...seen.keys()].sort((a, b) => {
            const ia = order.indexOf(a), ib = order.indexOf(b);
            return (ia < 0 ? 99 : ia) - (ib < 0 ? 99 : ib) || a.localeCompare(b);
        });
        sel.innerHTML = '<option value="all">Status: All</option>'
            + keys.map(k => `<option value="${esc(k)}">${esc(seen.get(k))}</option>`).join('')
            + (missing ? '<option value="__none">Missing</option>' : '');
        sel.value = [...sel.options].some(o => o.value === prev) ? prev : 'all';
    }

    // ── Audit History column: Task Manager tasks raised from the Audit button ──
    const auditStClass = (st) => {
        const x = String(st || '').toLowerCase();
        if (['done', 'approved', 'archived'].includes(x)) return 'done';
        if (['working', 'monitor', 'need approval'].includes(x)) return 'work';
        if (['todo', 'hold', 'dependent'].includes(x)) return 'todo';
        if (['rework', 'need help', 'deleted'].includes(x)) return 'bad';
        return '';
    };
    // Audit state: 'red' = an audit task is still open, 'yellow' = no audit in the last 30 days, 'green' = audited recently.
    const AUDIT_DONE_STATES = ['done', 'approved', 'archived', 'deleted'];
    function auditState(r) {
        const list = Array.isArray(r.audits) ? r.audits : [];
        const open = list.some(a => {
            const st = String(a.task_status || '').trim().toLowerCase();
            return st !== '' && !AUDIT_DONE_STATES.includes(st);
        });
        if (open) return 'red';
        const latest = list.reduce((m, a) => Math.max(m, histTs(a.at) || 0), 0);
        if (!latest || Date.now() - latest > 30 * 86400000) return 'yellow';
        return 'green';
    }
    function auditStateHtml(r) {
        const s = auditState(r);
        if (s === 'red') return '<i class="fas fa-exclamation-triangle" style="color:#dc2626;font-size:15px;" title="Audit task still open"></i>';
        const c = s === 'yellow' ? '#eab308' : '#22c55e';
        const t = s === 'yellow' ? 'Not audited in over 30 days' : 'Audited within 30 days';
        return `<span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:${c};" title="${t}"></span>`;
    }
    function auditCellHtml(sku, r) {
        const list = Array.isArray(r.audits) ? r.audits : [];
        if (!list.length) return '<span class="text-muted">—</span>';
        const a = list[0];
        return `<span class="im-hist" data-audit-sku="${esc(sku)}">
            <span class="im-hist-date"><i class="far fa-clipboard me-1"></i>${esc(fmtHistoryShort(a.at))}</span>
            <span class="im-hist-counts">${a.task_status ? `<span class="im-audit-st ${auditStClass(a.task_status)}">${esc(a.task_status)}</span>` : '<span class="im-hist-act">Audit</span>'}${list.length > 1 ? ` <span class="text-muted">· ${list.length}</span>` : ''}</span>
        </span>`;
    }
    function auditPopHtml(sku, r) {
        const list = Array.isArray(r.audits) ? r.audits : [];
        return `<div class="hp-title">${esc(sku)} — audit history</div>` + list.map(a => {
            const d = a.details || {};
            const ids = Array.isArray(d.task_ids) && d.task_ids.length ? d.task_ids.map(i => '#' + i).join(', ') : '';
            const who = a.user_name || a.user_email || 'Unknown';
            return `<div class="hp-row"><span class="mk ok">●</span><span class="msg" style="color:#0f172a;">
                <b>${esc(d.title || a.summary || 'Audit')}</b>${a.task_status ? ` <span class="im-audit-st ${auditStClass(a.task_status)}">${esc(a.task_status)}</span>` : ''}<br>
                ${d.group ? `<span style="color:#64748b;">${esc(d.group)}</span><br>` : ''}
                <span style="color:#64748b;">${esc(fmtHistoryDate(a.at))} · by ${esc(who)}${d.assignee ? ' → ' + esc(d.assignee) : ''}${ids ? ' · Task ' + esc(ids) : ''}</span></span></div>`;
        }).join('');
    }

    let imSort = { key: null, dir: 'asc' };

    function groupPushedCount(gkey, row) {
        const keys = (GROUPS[gkey] || []).filter((mp) => ENABLED_MARKETPLACES.includes(mp));
        const im = row.image_master || {};
        return keys.filter(mp => hasPushedImages(im[mp] || '')).length;
    }

    function imSortValue(key, r) {
        const sku = String(r.SKU || '');
        if (/^slot\d+$/.test(key)) {
            const u = pmImageUrls(r)[Number(key.slice(4))];
            return u ? String(u).split('?')[0].split('/').pop().toLowerCase() : null;
        }
        switch (key) {
            case 'selected': return selectedSkus.has(sku) ? 1 : 0;
            case 'inv': return r._inv;
            case 'status': return pmStatusRaw(r).toLowerCase() || null;
            case 'parent': return String(r.Parent || '') || null;
            case 'sku': return sku;
            case 'images': return pmImageUrls(r).length;
            case 'name': return String(r.Parent || sku);
            case 'history': {
                const l = historyLatest(r);
                return l ? l.ts : null;
            }
            case 'auditst': return { green: 1, yellow: 2, red: 3 }[auditState(r)];
            case 'audit': {
                const a = Array.isArray(r.audits) && r.audits[0] ? histTs(r.audits[0].at) : null;
                return a;
            }
            case 'mp': {
                const im = r.image_master || {};
                return ENABLED_MARKETPLACES.filter(mp => hasPushedImages(im[mp] || '')).length;
            }
            case 'channels': return groupPushedCount('gChannels', r);
            case 'shopify': return groupPushedCount('gShopify', r);
        }
        return null;
    }

    function sortRows(rows) {
        if (!imSort.key) return rows;
        const dir = imSort.dir === 'desc' ? -1 : 1;
        return rows.slice().sort((a, b) => {
            const va = imSortValue(imSort.key, a), vb = imSortValue(imSort.key, b);
            // empty values always last
            if (va === null && vb === null) return 0;
            if (va === null) return 1;
            if (vb === null) return -1;
            if (typeof va === 'number' && typeof vb === 'number') return (va - vb) * dir;
            return String(va).localeCompare(String(vb), undefined, { numeric: true, sensitivity: 'base' }) * dir;
        });
    }

    function updateSortIndicators() {
        document.querySelectorAll('#im-master-table thead th[data-sort]').forEach(th => {
            let ind = th.querySelector('.im-sort-ind');
            if (!ind) {
                ind = document.createElement('span');
                ind.className = 'im-sort-ind';
                th.appendChild(ind);
            }
            const active = imSort.key === th.dataset.sort;
            th.classList.toggle('im-sorted', active);
            ind.textContent = active ? (imSort.dir === 'asc' ? '▲' : '▼') : '⇅';
        });
    }

    // ── Image slot columns: one image per column (Hero 1, Hero 2, 3 … 14), editable headers ──
    const IM_SLOT_COUNT = 14;
    const IM_SLOT_KEY = 'im_slot_names_v1';
    // Columns 3-14 default to these titles (Hero 1 / Hero 2 keep their names).
    const IM_SLOT_TITLES = ['LIFESTYLE', 'SOLUTION', 'PERFORMANCE', 'DIMENSIONS', 'COMPATIBILITY', 'MATERIAL', 'ANGLES', 'UGC', "WHAT'S INCLUDED", 'HOW TO USE', 'USE CASES', 'TRUST'];
    const imSlotDefault = (i) => i === 0 ? 'Hero 1' : i === 1 ? 'Hero 2' : (IM_SLOT_TITLES[i - 2] || String(i + 1));
    // Old numeric defaults ("3" … "14") saved in a browser are treated as "not renamed".
    const imIsOldDefault = (i, v) => String(v).trim() === String(i + 1) && i >= 2;
    let imSlotNames = (() => {
        let saved = [];
        try { saved = JSON.parse(localStorage.getItem(IM_SLOT_KEY) || '[]'); } catch (_) {}
        return Array.from({ length: IM_SLOT_COUNT }, (_, i) => (saved && typeof saved[i] === 'string' && saved[i].trim() && !imIsOldDefault(i, saved[i])) ? saved[i] : imSlotDefault(i));
    })();
    (function buildSlotHeaders() {
        const actionTh = document.getElementById('imActionTh');
        if (!actionTh) return;
        // Column before Hero 1: opens the image editor for the row (magnifying glass).
        const viewTh = document.createElement('th');
        viewTh.style.cssText = 'width:36px;min-width:36px;text-align:center;';
        viewTh.title = 'Open the image editor for this row';
        viewTh.innerHTML = '<i class="fas fa-search" style="color:#000;" aria-hidden="true"></i>';
        actionTh.parentNode.insertBefore(viewTh, actionTh);
        for (let i = 0; i < IM_SLOT_COUNT; i++) {
            const th = document.createElement('th');
            th.className = 'im-slot-th';
            th.dataset.sort = 'slot' + i;
            th.title = `Image ${i + 1} — click header name to rename, click elsewhere on the header to sort`;
            const inp = document.createElement('input');
            inp.type = 'text';
            inp.className = 'im-slot-name';
            inp.value = imSlotNames[i];
            inp.placeholder = imSlotDefault(i);
            inp.maxLength = 24;
            inp.addEventListener('change', () => {
                imSlotNames[i] = inp.value.trim() || imSlotDefault(i);
                inp.value = imSlotNames[i];
                try { localStorage.setItem(IM_SLOT_KEY, JSON.stringify(imSlotNames)); } catch (_) {}
                // Save on the server so every user sees the same column titles.
                fetch('/image-master/slot-names', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ slot: i + 1, name: imSlotNames[i] }),
                }).then(r => r.json()).then(j => { if (!j.success) toast(j.message || 'Could not save the column title', false); })
                  .catch(() => toast('Could not save the column title', false));
            });
            inp.addEventListener('keydown', (e) => { if (e.key === 'Enter') inp.blur(); });
            th.appendChild(inp);
            actionTh.parentNode.insertBefore(th, actionTh);

        }
    })();

    document.querySelectorAll('#im-master-table thead th[data-sort]').forEach(th => {
        th.addEventListener('click', (e) => {
            if (e.target.closest('input, button, select, a')) return;
            const key = th.dataset.sort;
            if (imSort.key === key) imSort.dir = imSort.dir === 'asc' ? 'desc' : 'asc';
            else imSort = { key, dir: 'asc' };
            renderTable(tableData);
        });
    });

    function renderTable(rows) {
        const tbody = document.getElementById('table-body');
        const q = (document.getElementById('skuSearchIm')?.value || '').trim().toLowerCase();
        if (q) rows = rows.filter(r => String(r.SKU||'').toLowerCase().includes(q));
        const pq = (document.getElementById('parentSearchIm')?.value || '').trim().toLowerCase();
        if (pq) rows = rows.filter(r => String(r.Parent || '').toLowerCase().includes(pq));
        const rowType = document.getElementById('rowTypeFilter')?.value || 'sku';
        if (rowType !== 'both') {
            const wantParent = rowType === 'parent';
            rows = rows.filter(r => String(r.SKU || '').toUpperCase().includes('PARENT') === wantParent);
        }
        const stFilter = document.getElementById('statusFilter')?.value || 'all';
        if (stFilter !== 'all') rows = rows.filter(r => { const raw = pmStatusRaw(r).toLowerCase(); return stFilter === '__none' ? !raw : raw === stFilter; });
        const invMode = document.getElementById('invFilter')?.value || 'gt0';
        if (invMode === 'gt0') rows = rows.filter(r => (r._inv || 0) > 0);
        else if (invMode === 'eq0') rows = rows.filter(r => !(r._inv > 0));
        document.getElementById('rowCountBadge').textContent = rows.length + (rowType === 'parent' ? ' parents' : rowType === 'both' ? ' rows' : ' products');
        rows = sortRows(rows);
        updateSortIndicators();
        visibleFilteredRows = rows;
        // "Missing images" badge: rows whose Images count is red (below 14). Red when any, green when none.
        const missingBadge = document.getElementById('missingImagesBadge');
        if (missingBadge) {
            const missing = rows.filter(r => pmImageUrls(r).length < 14).length;
            missingBadge.textContent = 'Missing images: ' + missing;
            missingBadge.classList.toggle('bg-danger', missing > 0);
            missingBadge.classList.toggle('bg-success', missing === 0);
        }
        // Audit badges: rows with an open audit task (red triangle) / not audited in 30+ days (yellow dot).
        const auditStates = rows.map(auditState);
        const aRed = auditStates.filter(s => s === 'red').length;
        const aYellow = auditStates.filter(s => s === 'yellow').length;
        const aRedBadge = document.getElementById('auditAlertBadge');
        if (aRedBadge) {
            aRedBadge.textContent = 'Audit alerts: ' + aRed;
            aRedBadge.classList.toggle('bg-danger', aRed > 0);
            aRedBadge.classList.toggle('bg-success', aRed === 0);
        }
        const aYellowBadge = document.getElementById('auditOverdueBadge');
        if (aYellowBadge) {
            aYellowBadge.textContent = 'Audit overdue: ' + aYellow;
            aYellowBadge.classList.toggle('bg-warning', aYellow > 0);
            aYellowBadge.classList.toggle('text-dark', aYellow > 0);
            aYellowBadge.classList.toggle('bg-success', aYellow === 0);
        }
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="27" class="text-center text-muted py-3">No products</td></tr>';
            updateSelectedCount();
            syncSelectAllCheckbox();
            return;
        }
        tbody.innerHTML = rows.map(r => {
            const sku = String(r.SKU||'');
            const checked = selectedSkus.has(sku) ? 'checked' : '';
            const slotUrls = pmImageUrls(r);
            const slotCells = Array.from({ length: IM_SLOT_COUNT }, (_, i) => {
                const u = slotUrls[i];
                if (!u) return '<td class="text-center"><span class="text-muted">—</span></td>';
                return `<td class="text-center"><div class="im-slot-cell">
                    <img class="im-slot-thumb" src="${esc(imgSrc(u))}" alt="${esc(imSlotNames[i])}" title="${esc(imSlotNames[i])}" loading="lazy">
                    <button type="button" class="im-ai-edit-btn" data-ai-edit="${esc(sku)}" data-slot="${i + 1}" title="Edit ${esc(imSlotNames[i])} with AI" aria-label="Edit ${esc(imSlotNames[i])}"><i class="fas fa-pen"></i></button>
                </div></td>`;
            }).join('');
            const imgCount = slotUrls.length;
            const IM_TARGET_IMAGES = 14; // green at 14 or more, red below
            const imgCountHtml = `<span class="im-img-count ${imgCount >= IM_TARGET_IMAGES ? 'ok' : 'low'}" title="${imgCount} of ${IM_TARGET_IMAGES} images saved">${imgCount}/${IM_TARGET_IMAGES}</span>`;
            return `<tr data-sku="${esc(sku)}">
                <td class="text-center"><input type="checkbox" class="form-check-input row-select-im" data-sku="${esc(sku)}" ${checked} aria-label="Select ${esc(sku)}"></td>
                <td>${esc(r.Parent || '')}</td>
                <td>${esc(sku)}</td>
                <td class="text-center">${auditStateHtml(r)}</td>
                <td class="text-center">${r._inv}</td>
                <td class="text-center">${statusCellHtml(r)}</td>
                <td class="text-center">${imgCountHtml}</td>
                <td>${esc(r.Parent||sku)}</td>
                <td class="text-center"><button type="button" class="btn btn-sm edit-btn im-view-btn" data-edit="${esc(sku)}" title="View / edit images" aria-label="View / edit images for ${esc(sku)}"><i class="fas fa-search"></i></button></td>
                ${slotCells}
                <td>
                    <div class="d-flex gap-1 flex-wrap align-items-center">
                        <button type="button" class="shopify-row-pull-btn" data-shopify-pull-sku="${esc(sku)}" title="Pull Shopify images for this SKU"><i class="fas fa-download"></i></button>
                    </div>
                </td>
                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-primary im-mp-open" data-mp-open="${esc(sku)}" title="Marketplaces for ${esc(sku)}"><i class="fas fa-search"></i></button></td>
                <td>${historyCellHtml(sku, r)}</td>
                <td>${auditCellHtml(sku, r)}</td>
            </tr>`;
        }).join('');
        bindRowCheckboxEvents(tbody);
        updateSelectedCount();
        syncSelectAllCheckbox();
        document.querySelectorAll('.im-mp-open[data-mp-open]').forEach(b => b.addEventListener('click', () => openMpPicker(b.dataset.mpOpen)));
        document.querySelectorAll('.im-ai-edit-btn[data-ai-edit]').forEach(b => b.addEventListener('click', () => openAiEditModal(b.dataset.aiEdit, parseInt(b.dataset.slot, 10))));
        document.querySelectorAll('.edit-btn[data-edit]').forEach(b => b.addEventListener('click', () => openEditModal(b.dataset.edit)));
        document.querySelectorAll('.shopify-row-pull-btn[data-shopify-pull-sku]').forEach(b => b.addEventListener('click', () => startSingleShopifyPull(b.dataset.shopifyPullSku, b)));
        document.querySelectorAll('.bp-mp-stack[data-push-mp]:not(:disabled)').forEach(b => b.addEventListener('click', () => {
            quickPush(b.dataset.sku, b.dataset.pushMp);
        }));
    }


    // ── MP picker modal: grouped marketplaces, drag & drop regroup, checkboxes ──
    const MPG_KEY = 'im_mp_groups_v2';
    function defaultMpGroups() {
        return [
            { id: 'gChannels', name: 'Market Places', mps: [...(GROUPS.gChannels || []), ...(GROUPS.gShopify || [])] },
        ];
    }
    function normalizeMpGroups(gs) {
        const seen = new Set();
        let out = (Array.isArray(gs) ? gs : []).filter(g => g && g.id).map(g => ({
            id: String(g.id), name: String(g.name || 'Group'),
            mps: (Array.isArray(g.mps) ? g.mps : []).filter(mp => {
                if (!ENABLED_MARKETPLACES.includes(mp) || seen.has(mp)) return false;
                seen.add(mp); return true;
            }),
        }));
        if (!out.length) out = defaultMpGroups().map(g => ({ ...g, mps: g.mps.filter(mp => { if (!ENABLED_MARKETPLACES.includes(mp) || seen.has(mp)) return false; seen.add(mp); return true; }) }));
        ENABLED_MARKETPLACES.forEach(mp => { if (!seen.has(mp)) { out[0].mps.push(mp); seen.add(mp); } });
        return out;
    }
    function loadMpGroups() {
        try { return normalizeMpGroups(JSON.parse(localStorage.getItem(MPG_KEY) || 'null')); }
        catch (_) { return normalizeMpGroups(null); }
    }
    let mpGroups = loadMpGroups();
    let mpPickerSku = '';
    const mpPickerSelected = new Set();
    let mpDragMp = null;
    const saveMpGroups = () => { try { localStorage.setItem(MPG_KEY, JSON.stringify(mpGroups)); } catch (_) {} };

    function mpItemState(mp) {
        return mpPushTileState(mp, { label: LABELS[mp], implemented: ENABLED_MARKETPLACES.includes(mp) });
    }

    function renderMpPicker() {
        const wrap = document.getElementById('mpgWrap');
        if (!wrap) return;
        const row = bySku.get(String(mpPickerSku)) || {};
        const im = row.image_master || {};
        wrap.innerHTML = mpGroups.map(g => `
            <div class="mpg-col" data-gid="${esc(g.id)}">
                <div class="mpg-head">
                    <input type="checkbox" class="form-check-input mpg-group-chk m-0" data-gid="${esc(g.id)}" title="Select all in this column" aria-label="Select all in ${esc(g.name)}">
                    <input type="text" class="mpg-name-input" data-gid="${esc(g.id)}" value="${esc(g.name)}" title="Rename column">
                    <span class="mpg-count">${g.mps.length}</span>
                    ${mpGroups.length > 1 ? `<button type="button" class="mpg-del" data-gid="${esc(g.id)}" title="Delete column (marketplaces move to the first other column)"><i class="fas fa-times"></i></button>` : ''}
                </div>
                <div class="mpg-list" data-gid="${esc(g.id)}">
                    ${g.mps.map(mp => {
                        const st = mpItemState(mp);
                        const pushed = hasPushedImages(im[mp] || '');
                        return `<label class="mpg-item ${st.disabled ? 'disabled' : ''}" draggable="true" data-mp="${esc(mp)}" title="${esc(st.title || '')}">
                            <input type="checkbox" class="form-check-input mpg-chk m-0" data-mp="${esc(mp)}" ${st.disabled ? 'disabled' : ''} ${mpPickerSelected.has(mp) && !st.disabled ? 'checked' : ''}>
                            <span class="marketplace-btn ${MP_TILE[mp] || 'btn-secondary'}">${esc(MP_SHORT[mp] || mp)}</span>
                            <span class="mpg-lbl">${esc(cmName(mp))}</span>
                            <span class="bp-mp-dot ${pushed ? 'pushed' : ''}"></span>
                        </label>`;
                    }).join('')}
                </div>
            </div>`).join('');
        const oth = document.getElementById('mpgOthers');
        if (oth) {
            oth.innerHTML = cmOthers.length
                ? `<div class="small fw-semibold text-muted mb-2">Other channels in Channel Master (image push not available)</div>
                   <div class="d-flex flex-wrap gap-2">${cmOthers.map(c => `<span class="mpg-item disabled" style="cursor:default;min-width:180px;">
                        <input type="checkbox" class="form-check-input m-0" disabled>
                        ${c.logo ? `<img src="${esc(c.logo)}" alt="" style="width:26px;height:22px;object-fit:contain;" onerror="this.style.display='none'">` : ''}
                        <span class="mpg-lbl">${esc(c.channel)}</span></span>`).join('')}</div>`
                : '';
        }
        refreshMpPickerChecks();
    }

    function refreshMpPickerChecks() {
        mpGroups.forEach(g => {
            const chk = document.querySelector(`.mpg-group-chk[data-gid="${CSS.escape(g.id)}"]`);
            if (!chk) return;
            const sel = g.mps.filter(mp => !mpItemState(mp).disabled);
            const n = sel.filter(mp => mpPickerSelected.has(mp)).length;
            chk.checked = sel.length > 0 && n === sel.length;
            chk.indeterminate = n > 0 && n < sel.length;
            chk.disabled = sel.length === 0;
        });
        const c = document.getElementById('mpgSelCount');
        if (c) c.textContent = mpPickerSelected.size + ' selected';
    }

    let mpPickerBulk = false;
    function openMpPicker(sku, bulk = false) {
        mpPickerBulk = !!bulk;
        mpPickerSku = bulk ? '' : String(sku);
        mpPickerSelected.clear();
        document.getElementById('mpPickerSku').textContent = bulk ? (selectedSkus.size + ' selected product(s)') : sku;
        renderMpPicker();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('mpPickerModal')).show();
    }

    function moveMpTo(mp, gid, beforeMp) {
        mpGroups.forEach(g => { g.mps = g.mps.filter(x => x !== mp); });
        const target = mpGroups.find(g => g.id === gid);
        if (!target) return;
        const idx = beforeMp ? target.mps.indexOf(beforeMp) : -1;
        if (idx >= 0) target.mps.splice(idx, 0, mp); else target.mps.push(mp);
        saveMpGroups();
        renderMpPicker();
    }

    (function bindMpPicker() {
        const wrap = document.getElementById('mpgWrap');
        if (!wrap) return;
        wrap.addEventListener('change', (e) => {
            const t = e.target;
            if (t.classList.contains('mpg-chk')) {
                if (t.checked) mpPickerSelected.add(t.dataset.mp); else mpPickerSelected.delete(t.dataset.mp);
                refreshMpPickerChecks();
            } else if (t.classList.contains('mpg-group-chk')) {
                const g = mpGroups.find(x => x.id === t.dataset.gid);
                if (!g) return;
                g.mps.filter(mp => !mpItemState(mp).disabled).forEach(mp => {
                    if (t.checked) mpPickerSelected.add(mp); else mpPickerSelected.delete(mp);
                });
                renderMpPicker();
            } else if (t.classList.contains('mpg-name-input')) {
                const g = mpGroups.find(x => x.id === t.dataset.gid);
                if (g) { g.name = t.value.trim() || g.name; saveMpGroups(); }
            }
        });
        wrap.addEventListener('click', (e) => {
            const del = e.target.closest('.mpg-del');
            if (!del) return;
            const idx = mpGroups.findIndex(g => g.id === del.dataset.gid);
            if (idx < 0 || mpGroups.length < 2) return;
            const [removed] = mpGroups.splice(idx, 1);
            if (removed.mps.length) mpGroups[0].mps.push(...removed.mps);
            saveMpGroups();
            renderMpPicker();
        });
        wrap.addEventListener('dragstart', (e) => {
            const item = e.target.closest('.mpg-item');
            if (!item) return;
            mpDragMp = item.dataset.mp;
            item.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            try { e.dataTransfer.setData('text/plain', mpDragMp); } catch (_) {}
        });
        wrap.addEventListener('dragend', () => {
            mpDragMp = null;
            wrap.querySelectorAll('.dragging').forEach(el => el.classList.remove('dragging'));
            wrap.querySelectorAll('.mpg-over').forEach(el => el.classList.remove('mpg-over'));
        });
        wrap.addEventListener('dragover', (e) => {
            if (!mpDragMp) return;
            const col = e.target.closest('.mpg-col');
            if (!col) return;
            e.preventDefault();
            wrap.querySelectorAll('.mpg-over').forEach(el => { if (el !== col) el.classList.remove('mpg-over'); });
            col.classList.add('mpg-over');
        });
        wrap.addEventListener('drop', (e) => {
            if (!mpDragMp) return;
            const col = e.target.closest('.mpg-col');
            if (!col) return;
            e.preventDefault();
            const list = col.querySelector('.mpg-list');
            const items = [...list.querySelectorAll('.mpg-item')].filter(el => el.dataset.mp !== mpDragMp);
            const before = items.find(el => { const r = el.getBoundingClientRect(); return e.clientY < r.top + r.height / 2; });
            moveMpTo(mpDragMp, col.dataset.gid, before ? before.dataset.mp : null);
            mpDragMp = null;
        });

        document.getElementById('mpgAddBtn')?.addEventListener('click', () => {
            const inp = document.getElementById('mpgNewName');
            const name = (inp.value || '').trim();
            if (!name) { toast('Enter a column name first.', false); inp.focus(); return; }
            mpGroups.push({ id: 'g' + Date.now().toString(36), name, mps: [] });
            inp.value = '';
            saveMpGroups();
            renderMpPicker();
        });
        document.getElementById('mpgNewName')?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); document.getElementById('mpgAddBtn').click(); }
        });
        document.getElementById('mpgResetBtn')?.addEventListener('click', () => {
            mpGroups = normalizeMpGroups(null);
            saveMpGroups();
            renderMpPicker();
        });
        document.getElementById('mpgPushBtn')?.addEventListener('click', () => pushFromMpPicker());
    })();

    function pushFromMpPicker() {
        if (mpPickerBulk) {
            const bulkMps = ENABLED_MARKETPLACES.filter(mp => mpPickerSelected.has(mp) && !mpItemState(mp).disabled);
            if (!bulkMps.length) { toast('Select at least one marketplace.', false); return; }
            bootstrap.Modal.getOrCreateInstance(document.getElementById('mpPickerModal')).hide();
            bulkPushAll('selected', bulkMps);
            return;
        }
        const sku = mpPickerSku;
        const row = bySku.get(String(sku));
        const mps = ENABLED_MARKETPLACES.filter(mp => mpPickerSelected.has(mp) && !mpItemState(mp).disabled);
        if (!row) return;
        if (!mps.length) { toast('Select at least one marketplace.', false); return; }
        const urls = pmImageUrls(row);
        if (!urls.length) { toast('No images on Product Master for this SKU — open Edit first.', false); return; }
        if (!confirmEbay3Push(mps)) return;
        if (!window.confirm(`Push ${urls.length} image(s) for ${sku} to ${mps.length} marketplace(s)?\n\n${mps.map(mp => cmName(mp)).join(', ')}\n\nThis will replace existing marketplace images.`)) return;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('mpPickerModal')).hide();
        setPushProgress(true, `Queuing push for ${mps.length} marketplace(s)…`, '', false, true);
        queueImagePushAndWait({
            sku,
            mode: 'replace',
            updates: mps.map(mp => ({ marketplace: mp, images: urls })),
            main_by_marketplace: mainByMarketplacePayloadFromRow(row),
        }, (st) => renderImagePushJobProgress(st, `Pushing ${sku} to ${mps.length} marketplace(s)…`))
        .then(({ res, j }) => {
            let ok = 0, fail = 0;
            const lines = mps.map((mp, i) => {
                const r = j && j.results ? j.results[mp] : null;
                const good = !!(r && r.success);
                if (good) ok++; else fail++;
                return `${i + 1}/${mps.length} ${esc(LABELS[mp] || mp)}: ${good ? 'OK' : 'Failed'}${r && r.message ? ` - ${esc(r.message)}` : ''}`;
            });
            if (!(j && j.results) && !res.ok) lines.unshift(esc(j?.message || `HTTP ${res.status}`));
            setPushProgress(true, `Push finished: ${ok} success, ${fail} failed`, lines.join('<br>'), fail > 0);
            toast(fail ? `Push completed with ${fail} failure(s).` : `Pushed to ${ok} marketplace(s).`, !fail);
            loadData();
        }).catch(e => {
            setPushProgress(true, 'Push failed', esc(e.message || 'Request failed'), true);
            toast(e.message || 'Push failed', false);
        });
    }

    function defaultMainByMarketplace() {
        const o = {};
        MARKETPLACES.forEach(mp => { o[mp] = 0; });
        return o;
    }

    function normalizeMainByMarketplace(raw) {
        const out = defaultMainByMarketplace();
        if (!raw || typeof raw !== 'object') return out;
        MARKETPLACES.forEach(mp => {
            if (raw[mp] != null && raw[mp] !== '') {
                out[mp] = Math.max(0, Math.min(PM_MAX_IMAGES - 1, parseInt(raw[mp], 10) || 0));
            }
        });
        return out;
    }

    const HERO2_SUFFIX = '__hero2';

    /** Hero 2 is stored server-side as "<mp>__hero2": image index + 1 (absent/0 = automatic). */
    function hero2FromRaw(raw) {
        const out = {};
        if (!raw || typeof raw !== 'object') return out;
        MARKETPLACES.forEach(mp => {
            const v = parseInt(raw[mp + HERO2_SUFFIX], 10) || 0;
            if (v > 0) out[mp] = Math.min(PM_MAX_IMAGES - 1, v - 1);
        });
        return out;
    }

    function clampMainIndicesAfterDelete(deletedIdx) {
        MARKETPLACES.forEach(mp => {
            let idx = modalMainByMp[mp] ?? 0;
            if (idx === deletedIdx) idx = 0;
            else if (idx > deletedIdx) idx--;
            modalMainByMp[mp] = Math.max(0, idx);
            if (modalHero2ByMp[mp] != null) {
                const h = modalHero2ByMp[mp];
                if (h === deletedIdx) delete modalHero2ByMp[mp];
                else if (h > deletedIdx) modalHero2ByMp[mp] = h - 1;
            }
        });
    }

    function remapMainIndicesAfterMove(from, to) {
        if (from === to) return;
        MARKETPLACES.forEach(mp => {
            let idx = modalMainByMp[mp] ?? 0;
            if (idx === from) idx = to;
            else if (from < to && idx > from && idx <= to) idx--;
            else if (from > to && idx >= to && idx < from) idx++;
            modalMainByMp[mp] = idx;
            if (modalHero2ByMp[mp] != null) {
                let h = modalHero2ByMp[mp];
                if (h === from) h = to;
                else if (from < to && h > from && h <= to) h--;
                else if (from > to && h >= to && h < from) h++;
                modalHero2ByMp[mp] = h;
            }
        });
    }

    function clampMainIndicesToLength() {
        const max = Math.max(0, modalUrls.length - 1);
        MARKETPLACES.forEach(mp => {
            let idx = modalMainByMp[mp] ?? 0;
            if (modalUrls.length === 0) idx = 0;
            else if (idx > max) idx = 0;
            modalMainByMp[mp] = idx;
            if (modalHero2ByMp[mp] != null && (modalUrls.length < 2 || modalHero2ByMp[mp] > max)) delete modalHero2ByMp[mp];
        });
    }

    function mainByMarketplacePayload() {
        const out = {};
        const max = Math.max(0, modalUrls.length - 1);
        MARKETPLACES.forEach(mp => {
            const idx = Math.max(0, Math.min(max, modalMainByMp[mp] ?? 0));
            if (idx > 0) out[mp] = idx;
            const h = modalHero2ByMp[mp];
            if (h != null && h <= max && h !== idx) out[mp + HERO2_SUFFIX] = h + 1;
        });
        return out;
    }

    function mainByMarketplacePayloadFromRow(row) {
        const out = {};
        const map = normalizeMainByMarketplace(row?.image_main_by_marketplace);
        MARKETPLACES.forEach(mp => {
            const idx = map[mp] ?? 0;
            if (idx > 0) out[mp] = idx;
        });
        const h2 = hero2FromRaw(row?.image_main_by_marketplace);
        Object.keys(h2).forEach(mp => { if (h2[mp] !== (map[mp] ?? 0)) out[mp + HERO2_SUFFIX] = h2[mp] + 1; });
        return out;
    }

    function mainImageLabelFor(mp, idx) {
        return `Image ${(idx ?? 0) + 1}`;
    }

    function mainImageIndexFor(mp, source) {
        if (source === 'modal') return modalMainByMp[mp] ?? 0;
        return normalizeMainByMarketplace(source?.image_main_by_marketplace)[mp] ?? 0;
    }

    function pmImageUrls(row) {
        const u = [];
        for (let i=1;i<=PM_MAX_IMAGES;i++) {
            const v = row['image'+i];
            if (v && String(v).trim()) u.push(String(v).trim());
        }
        if (u.length) return u;
        ['main_image'].forEach(k => {
            const v = row[k];
            if (v && String(v).trim()) u.push(String(v).trim());
        });
        return u.slice(0, PM_MAX_IMAGES);
    }


    // ── Prompts: one editable/copyable prompt per image slot (shared, saved on the server) ──
    let slotPrompts = {};
    let slotPromptCurrent = 0;
    fetch('/image-master/slot-prompts', { headers: { 'Accept': 'application/json' } })
        .then(r => r.json()).then(j => {
            if (j && j.prompts) slotPrompts = j.prompts;
            // Column titles saved on the server win over this browser's copy.
            if (j && j.names) {
                for (let i = 0; i < IM_SLOT_COUNT; i++) imSlotNames[i] = (typeof j.names[i + 1] === 'string' && j.names[i + 1].trim() && !imIsOldDefault(i, j.names[i + 1])) ? j.names[i + 1] : imSlotDefault(i);
                try { localStorage.setItem(IM_SLOT_KEY, JSON.stringify(imSlotNames)); } catch (_) {}
                document.querySelectorAll('.im-slot-th .im-slot-name').forEach((inp, i) => { if (imSlotNames[i] !== undefined) inp.value = imSlotNames[i]; });
                renderPromptsMenu();
                if (tableData && tableData.length) renderTable(tableData);
            }
        })
        .catch(() => {});

    function renderPromptsMenu() {
        const menu = document.getElementById('promptsMenu');
        if (!menu) return;
        menu.innerHTML = imSlotNames.map((name, i) => {
            const has = !!String(slotPrompts[i + 1] || '').trim();
            return `<div class="ip-row">
                <span class="ip-name">${esc(name)}${has ? '<span class="ip-dot" title="Prompt saved"></span>' : ''}</span>
                <button type="button" class="btn btn-sm btn-outline-primary ip-open" data-slot="${i + 1}">Prompt</button>
            </div>`;
        }).join('');
    }
    document.getElementById('promptsDropdown')?.addEventListener('show.bs.dropdown', renderPromptsMenu);
    document.getElementById('promptsMenu')?.addEventListener('click', (e) => {
        const b = e.target.closest('.ip-open');
        if (!b) return;
        slotPromptCurrent = parseInt(b.dataset.slot, 10) || 0;
        document.getElementById('slotPromptName').textContent = imSlotNames[slotPromptCurrent - 1] || ('Image ' + slotPromptCurrent);
        document.getElementById('slotPromptText').value = slotPrompts[slotPromptCurrent] || '';
        document.getElementById('slotPromptMsg').textContent = '';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('slotPromptModal')).show();
    });

    document.getElementById('slotPromptCopyBtn')?.addEventListener('click', async () => {
        const ta = document.getElementById('slotPromptText');
        const msg = document.getElementById('slotPromptMsg');
        const text = ta.value;
        if (!text) { msg.className = 'small mt-2 text-muted'; msg.textContent = 'Nothing to copy.'; return; }
        try {
            await navigator.clipboard.writeText(text);
        } catch (_) {
            ta.select();
            document.execCommand('copy');
        }
        msg.className = 'small mt-2 text-success';
        msg.textContent = 'Copied to clipboard.';
    });

    document.getElementById('slotPromptSaveBtn')?.addEventListener('click', async () => {
        const btn = document.getElementById('slotPromptSaveBtn');
        const msg = document.getElementById('slotPromptMsg');
        const prompt = document.getElementById('slotPromptText').value;
        const label = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';
        try {
            const r = await fetch('/image-master/slot-prompts', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ slot: slotPromptCurrent, prompt }),
            });
            const j = await r.json().catch(() => ({}));
            if (r.ok && j.success) {
                if (prompt.trim()) slotPrompts[slotPromptCurrent] = prompt.trim(); else delete slotPrompts[slotPromptCurrent];
                msg.className = 'small mt-2 text-success';
                msg.textContent = j.message || 'Saved.';
                toast(j.message || 'Prompt saved');
            } else {
                msg.className = 'small mt-2 text-danger';
                msg.textContent = j.message || 'Save failed.';
            }
        } catch (e) {
            msg.className = 'small mt-2 text-danger';
            msg.textContent = e.message || 'Save failed.';
        } finally {
            btn.disabled = false;
            btn.innerHTML = label;
        }
    });

    // ── AI edit (Nano Banana): per-image Edit modal ───────────────────────────
    let aiEdit = { sku: '', slot: 0, path: '', results: [], selected: -1 };

    function aiEditMsg(text, cls) {
        const m = document.getElementById('aiEditMsg');
        m.className = 'small mt-2 ' + (cls || 'text-muted');
        m.textContent = text || '';
    }

    function renderAiResults() {
        const box = document.getElementById('aiEditResult');
        const apply = document.getElementById('aiEditApplyBtn');
        const r = aiEdit.results;
        if (!r.length) {
            box.innerHTML = '<span class="text-muted small">Click <strong>Prompt</strong> to generate.</span>';
            apply.style.display = 'none';
            aiEdit.path = '';
            return;
        }
        if (aiEdit.selected < 0 || aiEdit.selected >= r.length) aiEdit.selected = r.length - 1;
        aiEdit.path = r[aiEdit.selected].path;
        box.innerHTML = `<img src="${esc(r[aiEdit.selected].url)}" alt="">`
            + `<div class="ai-edit-thumbs">${r.map((x, i) => `<img src="${esc(x.url)}" data-i="${i}" class="${i === aiEdit.selected ? 'sel' : ''}" alt="Option ${i + 1}" title="Option ${i + 1}">`).join('')}</div>`;
        apply.style.display = '';
    }

    document.getElementById('aiEditResult')?.addEventListener('click', (e) => {
        const t = e.target.closest('.ai-edit-thumbs img');
        if (!t) return;
        aiEdit.selected = parseInt(t.dataset.i, 10) || 0;
        renderAiResults();
    });

    function openAiEditModal(sku, slot, fromEdit) {
        const row = bySku.get(String(sku));
        if (!row) return;
        const url = pmImageUrls(row)[slot - 1];
        if (!url) return;
        aiEdit = { sku: String(sku), slot, path: '', results: [], selected: -1, fromEdit: !!fromEdit };
        document.getElementById('aiEditGenLabel').textContent = 'Prompt';
        document.getElementById('aiEditName').textContent = imSlotNames[slot - 1] || ('Image ' + slot);
        document.getElementById('aiEditSku').textContent = sku;
        document.getElementById('aiEditOriginal').innerHTML = `<img src="${esc(imgSrc(url))}" alt="">`;
        renderAiResults();
        document.getElementById('aiEditPrompt').value = slotPrompts[slot] || '';
        aiEditMsg(slotPrompts[slot] ? '' : 'No saved prompt for this image yet — write one below (or use the Prompts button in the toolbar).');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('aiEditModal')).show();
    }

    // ── Specs / Desc modal (read-only, from Technical Specifications + Description masters) ──
    const skuTextCache = {};
    let skuTextPlain = '';
    async function openSkuText(mode) {
        const sku = document.getElementById('modalSku').value;
        if (!sku) return;
        document.getElementById('skuTextTitle').textContent = mode === 'specs' ? 'Specifications' : 'Description';
        document.getElementById('skuTextSku').textContent = sku;
        document.getElementById('skuTextTitleLine').textContent = '';
        const body = document.getElementById('skuTextBody');
        body.innerHTML = '<div class="text-muted py-3"><span class="spinner-border spinner-border-sm me-2"></span>Loading…</div>';
        skuTextPlain = '';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('skuTextModal')).show();
        try {
            let j = skuTextCache[sku];
            if (!j) {
                const r = await fetch('/image-master/sku-text?sku=' + encodeURIComponent(sku), { headers: { 'Accept': 'application/json' } });
                j = await r.json().catch(() => ({}));
                if (!r.ok || !j.success) throw new Error(j.message || 'Could not load.');
                skuTextCache[sku] = j;
            }
            document.getElementById('skuTextTitleLine').textContent = j.title || '';
            if (mode === 'specs') {
                if (!j.specs.length) { body.innerHTML = '<div class="text-muted py-3">No specifications saved for this SKU yet.</div>'; return; }
                skuTextPlain = j.specs.map(x => x.key && x.value ? `${x.key}: ${x.value}` : (x.key || x.value)).join('\n');
                body.innerHTML = '<table class="table table-sm table-bordered align-middle mb-0"><tbody>'
                    + j.specs.map(x => `<tr><th style="width:38%;background:#f8fafc;">${esc(x.key)}</th><td>${esc(x.value)}</td></tr>`).join('')
                    + '</tbody></table>';
            } else {
                if (!j.descriptions.length) { body.innerHTML = '<div class="text-muted py-3">No description saved for this SKU yet.</div>'; return; }
                skuTextPlain = j.descriptions.map(x => j.descriptions.length > 1 ? `${x.label}\n${x.text}` : x.text).join('\n\n');
                body.innerHTML = j.descriptions.map(x => `<div class="mb-3"><div class="fw-semibold small text-muted mb-1">${esc(x.label)} <span class="fw-normal">(${x.text.length} chars)</span></div><div style="white-space:pre-wrap;border:1px solid #e2e8f0;border-radius:6px;padding:8px 10px;background:#f8fafc;font-size:13px;">${esc(x.text)}</div></div>`).join('');
            }
        } catch (e) {
            body.innerHTML = `<div class="text-danger py-3">${esc(e.message || 'Could not load.')}</div>`;
        }
    }
    document.getElementById('specsBtn')?.addEventListener('click', () => openSkuText('specs'));
    document.getElementById('descBtn')?.addEventListener('click', () => openSkuText('desc'));
    document.getElementById('skuTextCopyBtn')?.addEventListener('click', async () => {
        if (!skuTextPlain) return;
        try { await navigator.clipboard.writeText(skuTextPlain); toast('Copied'); } catch (_) { toast('Copy failed', false); }
    });
    // ── Audit: raise a Task Manager task (assignor = you, assignee = images3@5core.com) ──
    document.getElementById('auditBtn')?.addEventListener('click', () => {
        const sku = document.getElementById('modalSku').value;
        if (!sku) return;
        const row = bySku.get(String(sku)) || {};
        document.getElementById('auditGroup').value = [row.Parent || '', sku, 'Image Text'].filter(Boolean).join(' / ');
        document.getElementById('auditTitle').value = '';
        document.getElementById('auditScreens').value = '';
        document.getElementById('auditEtc').value = 10;
        document.getElementById('auditPriority').value = 'normal';
        document.getElementById('auditAlert').innerHTML = '';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('auditModal')).show();
        setTimeout(() => document.getElementById('auditTitle').focus(), 300);
    });
    document.getElementById('auditSaveBtn')?.addEventListener('click', async () => {
        const alertEl = document.getElementById('auditAlert');
        const title = document.getElementById('auditTitle').value.trim();
        if (!title) { alertEl.innerHTML = '<span class="text-danger">Please enter a task.</span>'; return; }
        const fd = new FormData();
        fd.append('sku', document.getElementById('modalSku').value);
        fd.append('group', document.getElementById('auditGroup').value.trim());
        fd.append('title', title);
        fd.append('priority', document.getElementById('auditPriority').value);
        fd.append('etc_minutes', document.getElementById('auditEtc').value || 10);
        Array.from(document.getElementById('auditScreens').files || []).forEach(f => fd.append('screenshots[]', f));
        const btn = document.getElementById('auditSaveBtn');
        const label = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';
        try {
            const r = await fetch('/image-master/audit', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }, body: fd });
            const j = await r.json().catch(() => ({}));
            if (!r.ok || !j.success) throw new Error(j.message || (j.errors ? Object.values(j.errors).flat()[0] : 'Could not create the task.'));
            toast(j.message || 'Audit task created');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('auditModal')).hide();
            loadData();
        } catch (e) {
            alertEl.innerHTML = `<span class="text-danger">${esc(e.message || 'Could not create the task.')}</span>`;
        } finally {
            btn.disabled = false;
            btn.innerHTML = label;
        }
    });
    document.getElementById('auditModal')?.addEventListener('shown.bs.modal', function () {
        if (!document.getElementById('editImModal')?.classList.contains('show')) return;
        this.style.zIndex = 1075;
        const bds = document.querySelectorAll('.modal-backdrop');
        if (bds.length) bds[bds.length - 1].style.zIndex = 1070;
    });
    document.getElementById('auditModal')?.addEventListener('hidden.bs.modal', function () {
        this.style.zIndex = '';
        if (document.getElementById('editImModal')?.classList.contains('show')) document.body.classList.add('modal-open');
    });

    // Opens on top of the product-images editor: lift it (and its backdrop) above.
    document.getElementById('skuTextModal')?.addEventListener('shown.bs.modal', function () {
        this.style.zIndex = 1075;
        const bds = document.querySelectorAll('.modal-backdrop');
        if (bds.length) bds[bds.length - 1].style.zIndex = 1070;
    });
    document.getElementById('skuTextModal')?.addEventListener('hidden.bs.modal', function () {
        this.style.zIndex = '';
        if (document.getElementById('editImModal')?.classList.contains('show')) document.body.classList.add('modal-open');
    });

    // The AI editor can open on top of the product-images editor: lift it (and its backdrop) above.
    document.getElementById('aiEditModal')?.addEventListener('shown.bs.modal', function () {
        if (!document.getElementById('editImModal')?.classList.contains('show')) return;
        this.style.zIndex = 1075;
        const bds = document.querySelectorAll('.modal-backdrop');
        if (bds.length) bds[bds.length - 1].style.zIndex = 1070;
    });
    document.getElementById('aiEditModal')?.addEventListener('hidden.bs.modal', function () {
        this.style.zIndex = '';
        if (document.getElementById('editImModal')?.classList.contains('show')) document.body.classList.add('modal-open');
    });

    document.getElementById('aiEditSavePromptBtn')?.addEventListener('click', async () => {
        const prompt = document.getElementById('aiEditPrompt').value;
        try {
            const r = await fetch('/image-master/slot-prompts', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ slot: aiEdit.slot, prompt }),
            });
            const j = await r.json().catch(() => ({}));
            if (r.ok && j.success) {
                if (prompt.trim()) slotPrompts[aiEdit.slot] = prompt.trim(); else delete slotPrompts[aiEdit.slot];
                aiEditMsg(j.message || 'Prompt saved.', 'text-success');
            } else {
                aiEditMsg(j.message || 'Could not save prompt.', 'text-danger');
            }
        } catch (e) {
            aiEditMsg(e.message || 'Could not save prompt.', 'text-danger');
        }
    });

    document.getElementById('aiEditGenerateBtn')?.addEventListener('click', async () => {
        const btn = document.getElementById('aiEditGenerateBtn');
        const prompt = document.getElementById('aiEditPrompt').value.trim();
        if (!prompt) { aiEditMsg('Write a prompt first.', 'text-danger'); return; }
        const count = Math.max(1, Math.min(4, parseInt(document.getElementById('aiEditCount').value, 10) || 1));
        const label = btn.innerHTML;
        btn.disabled = true;
        let made = 0;
        let error = '';
        for (let n = 1; n <= count; n++) {
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span>Generating ${n}/${count}…`;
            aiEditMsg(`Nano Banana is generating image ${n} of ${count}… this can take up to a minute each.`);
            try {
                const r = await fetch('/image-master/ai-edit/generate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ sku: aiEdit.sku, slot: aiEdit.slot, prompt }),
                });
                const j = await r.json().catch(() => ({}));
                if (r.ok && j.success) {
                    aiEdit.results.push({ path: j.path, url: j.preview_url });
                    aiEdit.selected = aiEdit.results.length - 1;
                    made++;
                    renderAiResults();
                } else {
                    error = j.message || 'Generation failed.';
                    break;
                }
            } catch (e) {
                error = e.message || 'Generation failed.';
                break;
            }
        }
        btn.disabled = false;
        btn.innerHTML = label;
        if (aiEdit.results.length) document.getElementById('aiEditGenLabel').textContent = 'Generate more';
        if (error) {
            aiEditMsg((made ? `${made} generated, then stopped: ` : '') + error, 'text-danger');
        } else {
            aiEditMsg(`${made} new image(s) generated (${aiEdit.results.length} total). Click a thumbnail to choose one, or generate more.`, 'text-success');
        }
    });

    document.getElementById('aiEditApplyBtn')?.addEventListener('click', async () => {
        if (!aiEdit.path) return;
        if (!window.confirm(`Overwrite ${imSlotNames[aiEdit.slot - 1] || 'this image'} for ${aiEdit.sku} with the new image?`)) return;
        const btn = document.getElementById('aiEditApplyBtn');
        const label = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';
        try {
            const r = await fetch('/image-master/ai-edit/apply', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ sku: aiEdit.sku, slot: aiEdit.slot, path: aiEdit.path, prompt: document.getElementById('aiEditPrompt').value }),
            });
            const j = await r.json().catch(() => ({}));
            if (r.ok && j.success) {
                toast(j.message || 'Image replaced');
                const reopenSku = aiEdit.fromEdit ? aiEdit.sku : null;
                bootstrap.Modal.getOrCreateInstance(document.getElementById('aiEditModal')).hide();
                const reloaded = loadData();
                // Editor was open underneath: refresh it so it shows the new image (not stale URLs).
                if (reopenSku) reloaded.then(() => openEditModal(reopenSku));
            } else {
                aiEditMsg(j.message || 'Could not save the image.', 'text-danger');
            }
        } catch (e) {
            aiEditMsg(e.message || 'Could not save the image.', 'text-danger');
        } finally {
            btn.disabled = false;
            btn.innerHTML = label;
        }
    });

    // ── Main Image modal: main (Hero 1) image per marketplace ─────────────────────
    let heroRows = [];        // rows the settings will be applied to
    let heroUsingSelection = false;
    let heroMain = {};   // mp -> index

    /** Only two choices: Hero image 1 (slot 1) or Hero image 2 (slot 2). A leftover older choice (3+) is kept as an extra option. */
    function heroOptionsHtml(selected) {
        const sel = Number.isInteger(selected) ? selected : 0;
        let h = `<option value="0"${sel === 0 ? ' selected' : ''}>Hero image 1</option><option value="1"${sel === 1 ? ' selected' : ''}>Hero image 2</option>`;
        if (sel > 1) h += `<option value="${sel}" selected>Image ${sel + 1} (current)</option>`;
        return h;
    }

    function heroThumbsHtml(urls, mp) {
        const m = heroMain[mp] ?? 0;
        const t = (i) => urls[i] ? `<img src="${esc(imgSrc(urls[i]))}" alt="">` : '';
        return `<span class="hero-thumbs">${t(m)}</span>`;
    }

    // Marketplaces shown in this modal = active channels (with logos) from the Active Channel pages.
    let heroChannels = [];
    let cmOthers = [];
    fetch('/image-master/active-channels', { headers: { 'Accept': 'application/json' } })
        .then(r => r.json()).then(j => {
            heroChannels = (j && Array.isArray(j.channels)) ? j.channels : [];
            cmOthers = (j && Array.isArray(j.others)) ? j.others : [];
            if (document.getElementById('heroImageModal')?.classList.contains('show')) renderHeroRows();
            if (document.getElementById('mpPickerModal')?.classList.contains('show')) renderMpPicker();
        }).catch(() => {});
    const heroMpList = () => heroChannels.length ? heroChannels.map(c => c.key) : MARKETPLACES;
    // Channel name exactly as in Channel Master (falls back to the registry label).
    const cmName = (mp) => { const c = heroChannels.find(x => x.key === mp); return (c && c.channel) || LABELS[mp] || mp; };
    const heroMpInfo = (mp) => { const c = heroChannels.find(x => x.key === mp); return c ? { ...c, label: c.channel || c.label } : { key: mp, label: LABELS[mp] || mp, logo: null }; };

    let heroChecked = new Set(); // ticked marketplaces: changing one ticked dropdown sets all ticked ones
    function syncHeroSelAll() {
        const all = document.getElementById('heroSelAll');
        if (!all) return;
        const list = heroMpList();
        all.checked = list.length > 0 && list.every(mp => heroChecked.has(mp));
        all.indeterminate = !all.checked && heroChecked.size > 0;
    }

    function renderHeroRows() {
        const single = heroRows.length === 1 ? pmImageUrls(heroRows[0]) : null;
        const wrap = document.getElementById('heroRows');
        if (!wrap) return;
        wrap.innerHTML = heroMpList().map(mp => { const info = heroMpInfo(mp); return `
            <div class="hero-row" data-mp="${esc(mp)}">
                <input type="checkbox" class="form-check-input m-0 hero-chk" data-mp="${esc(mp)}"${heroChecked.has(mp) ? ' checked' : ''} aria-label="Select ${esc(info.label)}">
                <span class="hero-mp" title="${esc(info.label)}">${info.logo ? `<img class="hero-mp-logo" src="${esc(info.logo)}" alt="${esc(info.label)}" onerror="this.outerHTML='${esc(info.label).replace(/'/g, '&#39;')}'">` : esc(info.label)}</span>
                <select class="form-select form-select-sm hero-sel-main" data-mp="${esc(mp)}">${heroOptionsHtml(heroMain[mp] ?? 0)}</select>
                <span class="hero-thumbs-cell">${single ? heroThumbsHtml(single, mp) : ''}</span>
            </div>`; }).join('');
        syncHeroSelAll();
    }

    function openHeroModal() {
        const withImages = (r) => pmImageUrls(r).length > 0;
        const selected = tableData.filter(r => selectedSkus.has(String(r.SKU || '')) && withImages(r));
        heroUsingSelection = selected.length > 0;
        heroRows = heroUsingSelection ? selected : currentFilteredRowsForPull().filter(withImages);
        if (!heroRows.length) {
            toast('No products with images to set hero images for.', false);
            return;
        }
        if (heroRows.length === 1) {
            heroMain = normalizeMainByMarketplace(heroRows[0].image_main_by_marketplace);
        } else {
            heroMain = defaultMainByMarketplace();
        }
        document.getElementById('heroSkuLabel').textContent = heroRows.length === 1
            ? String(heroRows[0].SKU)
            : `${heroRows.length} SKUs (${heroUsingSelection ? 'selected' : 'all in current view'})`;
        const note = document.getElementById('heroScopeNote');
        if (note) {
            note.textContent = heroRows.length > 1
                ? `These settings will be applied to ${heroRows.length} SKUs, replacing their current hero choices. Image numbers that a SKU does not have are skipped for that SKU.`
                : '';
            note.style.display = note.textContent ? '' : 'none';
        }
        heroChecked = new Set();
        renderHeroRows();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('heroImageModal')).show();
    }
    document.getElementById('heroImageBtn')?.addEventListener('click', openHeroModal);

    function heroSetFor(mp, rawVal) {
        heroMain[mp] = parseInt(rawVal, 10) || 0;
    }

    document.getElementById('heroRows')?.addEventListener('change', (e) => {
        const chk = e.target.closest('input.hero-chk');
        if (chk) {
            if (chk.checked) heroChecked.add(chk.dataset.mp); else heroChecked.delete(chk.dataset.mp);
            syncHeroSelAll();
            return;
        }
        const sel = e.target.closest('select[data-mp]');
        if (!sel) return;
        // A ticked row's change applies to every ticked marketplace; an unticked row only changes itself.
        const targets = heroChecked.has(sel.dataset.mp) ? [...heroChecked] : [sel.dataset.mp];
        targets.forEach(mp => {
            heroSetFor(mp, sel.value);
            const row = document.querySelector(`#heroRows .hero-row[data-mp="${CSS.escape(mp)}"]`);
            const other = row?.querySelector('select.hero-sel-main');
            if (other && other !== sel) {
                if (![...other.options].some(o => o.value === sel.value)) other.add(new Option(sel.options[sel.selectedIndex].text, sel.value));
                other.value = sel.value;
            }
            const cell = row?.querySelector('.hero-thumbs-cell');
            if (cell && heroRows.length === 1) cell.innerHTML = heroThumbsHtml(pmImageUrls(heroRows[0]), mp);
        });
    });
    document.getElementById('heroSelAll')?.addEventListener('change', (e) => {
        heroChecked = e.target.checked ? new Set(heroMpList()) : new Set();
        document.querySelectorAll('#heroRows .hero-chk').forEach(c => { c.checked = heroChecked.has(c.dataset.mp); });
        syncHeroSelAll();
    });

    document.getElementById('heroSaveBtn')?.addEventListener('click', async () => {
        if (!heroRows.length) return;
        if (heroRows.length > 1 && !window.confirm(`Apply these hero image settings to ${heroRows.length} SKUs${heroUsingSelection ? '' : ' (everything in the current view)'}?`)) return;
        const btn = document.getElementById('heroSaveBtn');
        const label = btn.innerHTML;
        btn.disabled = true;
        let ok = 0, fail = 0, firstErr = '';
        for (let i = 0; i < heroRows.length; i++) {
            const row = heroRows[i];
            const urls = pmImageUrls(row);
            const payload = {};
            const keepHero2 = hero2FromRaw(row.image_main_by_marketplace); // Hero 2 choices are not edited here; keep them
            MARKETPLACES.forEach(mp => {
                const m = Math.max(0, Math.min(urls.length - 1, heroMain[mp] ?? 0));
                if (m > 0) payload[mp] = m;
                const h = keepHero2[mp];
                if (h != null && h !== m && h <= urls.length - 1) payload[mp + HERO2_SUFFIX] = h + 1;
            });
            btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Saving ${i + 1}/${heroRows.length}…`;
            try {
                const r = await fetch('/image-master/save-pm', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ sku: String(row.SKU), images: urls, main_by_marketplace: payload, removed_urls: [] }),
                });
                const j = await r.json().catch(() => ({}));
                if (j.success) {
                    row.image_main_by_marketplace = j.image_main_by_marketplace || payload;
                    ok++;
                } else {
                    fail++;
                    if (!firstErr) firstErr = `${row.SKU}: ${j.message || 'Save failed'}`;
                }
            } catch (e) {
                fail++;
                if (!firstErr) firstErr = `${row.SKU}: ${e.message || 'Save failed'}`;
            }
        }
        btn.disabled = false;
        btn.innerHTML = label;
        if (fail === 0) {
            toast(`Hero images saved for ${ok} SKU(s)`);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('heroImageModal')).hide();
        } else {
            toast(`Saved ${ok}, failed ${fail}. ${firstErr}`, false);
        }
        loadData();
    });

    // storedImageIds: url → DB id (so we can delete from server)
    const storedImageMeta = new Map();

    function openEditModal(sku) {
        const row = bySku.get(String(sku));
        if (!row) return;
        document.getElementById('modalSku').value = sku;
        document.getElementById('modalSkuLabel').textContent = sku;
        document.getElementById('modalProductLabel').textContent = row.Parent || sku;
        storedImageMeta.clear();
        modalUrls = pmImageUrls(row);
        modalSandbox = [];
        knownImageUrls = [...modalUrls];
        modalMainByMp = normalizeMainByMarketplace(row.image_main_by_marketplace);
        modalHero2ByMp = hero2FromRaw(row.image_main_by_marketplace);
        renderSlots();
        // Reset the upload section
        pendingFiles = [];
        updatePendingFileUi();
        document.getElementById('uploadSuccessMsg').style.display = 'none';
        if (editModal) editModal.show();
        // Load stored-image metadata for saved URLs in the background.
        // Do not append every upload here; the modal list must reflect Product Master.
        loadStoredSkuImages(sku);
    }

    async function loadStoredSkuImages(sku) {
        try {
            const r = await fetch('/image-master/sku-images?sku=' + encodeURIComponent(sku));
            const j = await r.json();
            if (!j.success || !j.images?.length) return;
            const existingSet = new Set(modalUrls);
            j.images.forEach(img => {
                if (existingSet.has(img.url)) {
                    storedImageMeta.set(img.url, { id: img.id, name: img.name });
                }
            });
            renderSlots();
        } catch (_) {}
    }

    let imClipBlob = null; // last image copied with the card Copy button
    async function imToPngBlob(blob) {
        if (blob.type === 'image/png') return blob;
        const bmp = await createImageBitmap(blob);
        const cv = document.createElement('canvas');
        cv.width = bmp.width; cv.height = bmp.height;
        cv.getContext('2d').drawImage(bmp, 0, 0);
        return await new Promise((res, rej) => cv.toBlob(b => b ? res(b) : rej(new Error('Convert failed')), 'image/png'));
    }

    function renderSlots() {
        const el = document.getElementById('imSlots');
        const removeAllBtn = document.getElementById('removeAllImagesBtn');
        if (removeAllBtn) removeAllBtn.style.display = modalUrls.length ? 'inline-flex' : 'none';
        const downloadAllBtn = document.getElementById('downloadAllImagesBtn');
        if (downloadAllBtn) downloadAllBtn.style.display = modalUrls.length ? 'inline-flex' : 'none';
        if (!modalUrls.length && !modalSandbox.length) {
            el.innerHTML = '<div class="text-muted small py-2">No images yet. Upload or fetch from a marketplace above.</div>';
            return;
        }

        const cardHtml = (url, idx) => {
                const meta     = storedImageMeta.get(url);
                const isStored = !!meta;
                const name     = meta?.name ?? decodeURIComponent(url.split('/').pop().split('?')[0]);
                const dbId     = meta?.id ?? '';
                const isDefaultMain = idx === 0;
                const slotName = imSlotNames[idx] || ('Image ' + (idx + 1));
                const heroCls  = idx < 2 ? ' im-card-hero' : '';
                return `<div class="im-card${heroCls}${isStored?' is-stored':''}${isDefaultMain?' is-main-default':''}"
                            draggable="true" data-idx="${idx}"
                            data-url="${esc(url)}" data-dbid="${esc(String(dbId))}">
                    <div class="im-card-img-wrap">
                        <img src="${esc(imgSrc(url))}" alt=""
                             onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22120%22 height=%22100%22%3E%3Crect width=%22120%22 height=%22100%22 fill=%22%23f1f5f9%22/%3E%3Ctext x=%2260%22 y=%2254%22 font-size=%2211%22 fill=%22%2394a3b8%22 text-anchor=%22middle%22%3ENo preview%3C/text%3E%3C/svg%3E'">
                        <span class="im-card-badge${isDefaultMain?' im-card-badge-main':''}">${isDefaultMain ? 'MAIN' : (idx + 1)}</span>
                        <div class="im-card-arrows">
                            <button type="button" class="im-dl" data-i="${idx}" title="Download this image"><i class="fas fa-download"></i></button>
                            <button type="button" class="im-cp" data-i="${idx}" title="Copy this image"><i class="fas fa-copy"></i></button>
                            <button type="button" class="im-ps" data-i="${idx}" title="Paste copied image here (replaces this slot)"><i class="fas fa-paste"></i></button>
                            <button type="button" class="im-ai" data-i="${idx}" title="Edit this image with AI (Nano Banana)"><i class="fas fa-pen"></i></button>
                            <button type="button" class="im-card-del" data-i="${idx}" title="Delete image"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                    <div class="im-card-footer">
                        <div class="im-card-name" title="${esc(name)}">${esc(slotName)}</div>
                    </div>
                </div>`;
        };
        const heroCards = modalUrls.slice(0, 2).map((u, i) => cardHtml(u, i)).join('');
        const restCards = modalUrls.slice(2).map((u, i) => cardHtml(u, i + 2)).join('');
        const endBox = '<div class="im-card im-drop-end" data-drop="end" title="Drop an image here to move it to the end"><div class="im-drop-end-inner"><i class="fas fa-arrow-right-to-bracket"></i><span>Move to end</span></div></div>';
        const sbCards = modalSandbox.map((url, i) => {
            const meta = storedImageMeta.get(url);
            const nm = meta?.name ?? decodeURIComponent(url.split('/').pop().split('?')[0]);
            return `<div class="im-card im-sb-card" draggable="true" data-sb="${i}" title="Drag back into any slot">
                <div class="im-card-img-wrap"><img src="${esc(imgSrc(url))}" alt=""></div>
                <div class="im-card-footer"><div class="im-card-filename" title="${esc(url)}">${esc(nm)}</div></div>
            </div>`;
        }).join('');
        const sandboxCell = `<div class="im-sandbox" id="imSandbox" title="Sandbox: drag images here to park them, then drag them back into any slot. Anything left here is added to the end when you save.">
                <div class="im-sandbox-title mb-1"><i class="fas fa-box-open me-1"></i>Sandbox</div>
                <div class="im-grid im-sandbox-grid">${sbCards}<div class="im-sb-empty"><i class="fas fa-image"></i><span>Drop image here</span></div></div>
            </div>`;
        const showEnd = modalUrls.length && modalUrls.length < 14;
        el.innerHTML = '<div id="imGrid">'
            + `<div class="im-grid">${heroCards}${restCards}${showEnd ? endBox : ''}${sandboxCell}</div>`
            + '</div>';

        const grid = document.getElementById('imGrid');

        // ── Delete button ──────────────────────────────────────────
        grid.querySelectorAll('.im-card-del').forEach(b => {
            b.addEventListener('click', async (e) => {
                e.stopPropagation();
                const i      = +b.dataset.i;
                clampMainIndicesAfterDelete(i);
                const removed = modalUrls.splice(i, 1)[0];
                storedImageMeta.delete(removed);
                renderSlots();
            });
        });

        // ── Download one image ─────────────────────────────────────
        grid.querySelectorAll('.im-dl').forEach(b => {
            b.addEventListener('click', async (e) => {
                e.stopPropagation();
                const i = +b.dataset.i;
                const url = modalUrls[i];
                if (!url) return;
                b.disabled = true;
                try {
                    await downloadFromServer(
                        '/image-master/download?url=' + encodeURIComponent(url)
                            + '&sku=' + encodeURIComponent(document.getElementById('modalSku').value)
                            + '&n=' + (i + 1),
                        { headers: { 'Accept': 'application/json' } },
                        'image_' + (i + 1) + '.jpg'
                    );
                } catch (err) {
                    alert(err.message || 'Download failed.');
                } finally {
                    b.disabled = false;
                }
            });
        });

        // ── Copy one image (system clipboard + in-page fallback) ──────────
        grid.querySelectorAll('.im-cp').forEach(b => {
            b.addEventListener('click', async (e) => {
                e.stopPropagation();
                const i = +b.dataset.i;
                const url = modalUrls[i];
                if (!url) return;
                b.disabled = true;
                try {
                    const res = await fetch('/image-master/download?url=' + encodeURIComponent(url)
                        + '&sku=' + encodeURIComponent(document.getElementById('modalSku').value) + '&n=' + (i + 1),
                        { headers: { 'Accept': 'image/*' } });
                    if (!res.ok) throw new Error('Could not read the image (' + res.status + ').');
                    imClipBlob = await res.blob();
                    let sys = false;
                    try {
                        const png = await imToPngBlob(imClipBlob);
                        await navigator.clipboard.write([new ClipboardItem({ 'image/png': png })]);
                        sys = true;
                    } catch (_) {}
                    toast(sys ? 'Image copied' : 'Image copied (paste works inside this page only)');
                } catch (err) {
                    toast(err.message || 'Copy failed', false);
                } finally {
                    b.disabled = false;
                }
            });
        });

        // ── Paste the copied image into this slot (replaces it) ───────────
        grid.querySelectorAll('.im-ps').forEach(b => {
            b.addEventListener('click', async (e) => {
                e.stopPropagation();
                const i = +b.dataset.i;
                const oldUrl = modalUrls[i];
                if (!oldUrl) return;
                let blob = null;
                try {
                    const items = await navigator.clipboard.read();
                    for (const it of items) {
                        const t = it.types.find(x => x.startsWith('image/'));
                        if (t) { blob = await it.getType(t); break; }
                    }
                } catch (_) {}
                if (!blob) blob = imClipBlob;
                if (!blob) { toast('Nothing to paste. Copy an image first.', false); return; }
                b.disabled = true;
                try {
                    const ext = (blob.type.split('/')[1] || 'png').replace('jpeg', 'jpg');
                    const fd = new FormData();
                    fd.append('sku', document.getElementById('modalSku').value);
                    fd.append('files[]', new File([blob], 'pasted_' + Date.now() + '.' + ext, { type: blob.type || 'image/png' }));
                    const r = await fetch('/image-master/upload', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                        body: fd,
                    });
                    const j = await r.json();
                    if (!(j.success && j.urls?.length)) throw new Error(j.message || 'Paste failed');
                    (j.images || []).forEach(img => storedImageMeta.set(img.url, { id: img.id, name: img.name }));
                    storedImageMeta.delete(oldUrl);
                    modalUrls[i] = j.urls[0];
                    knownImageUrls = knownImageUrls.concat(j.urls);
                    renderSlots();
                    toast('Image pasted. Click Save to keep the change.');
                } catch (err) {
                    toast(err.message || 'Paste failed', false);
                    b.disabled = false;
                }
            });
        });

        // ── Edit one image with AI ────────────────────────────────
        grid.querySelectorAll('.im-ai').forEach(b => {
            b.addEventListener('click', (e) => {
                e.stopPropagation();
                const url = modalUrls[+b.dataset.i];
                const sku = document.getElementById('modalSku').value;
                const row = bySku.get(String(sku));
                const slot = row ? pmImageUrls(row).indexOf(url) + 1 : 0;
                if (!slot) { alert('Save the images first, then edit this one with AI.'); return; }
                openAiEditModal(sku, slot, true);
            });
        });

        clampMainIndicesToLength();
    }


    // ── Drag & drop between slots, with a sandbox holding area ───────────────
    function remapSwapIndices(a, b) {
        const sw = (v) => (v === a ? b : v === b ? a : v);
        MARKETPLACES.forEach(mp => {
            modalMainByMp[mp] = sw(modalMainByMp[mp] ?? 0);
            if (modalHero2ByMp[mp] != null) modalHero2ByMp[mp] = sw(modalHero2ByMp[mp]);
        });
    }
    function remapInsertAt(pos) {
        MARKETPLACES.forEach(mp => {
            if ((modalMainByMp[mp] ?? 0) >= pos) modalMainByMp[mp] = (modalMainByMp[mp] ?? 0) + 1;
            if (modalHero2ByMp[mp] != null && modalHero2ByMp[mp] >= pos) modalHero2ByMp[mp]++;
        });
    }
    function resetRefsAt(pos) {
        MARKETPLACES.forEach(mp => {
            if ((modalMainByMp[mp] ?? 0) === pos) modalMainByMp[mp] = 0;
            if (modalHero2ByMp[mp] === pos) delete modalHero2ByMp[mp];
        });
    }

    // Hover zoom: grow the card toward the free space (left/right/top/bottom edges of the modal body).
    document.getElementById('imSlots')?.addEventListener('mouseover', (e) => {
        const card = e.target.closest ? e.target.closest('.im-card[data-idx]') : null;
        if (!card || (e.relatedTarget && card.contains(e.relatedTarget))) return; // only when entering the card
        const body = document.querySelector('#editImModal .modal-body');
        if (!body) return;
        const c = card.getBoundingClientRect(), b = body.getBoundingClientRect();
        const k = card.classList.contains('im-card-hero') ? 0.25 : 1;
        const x = c.left - c.width * k / 2 < b.left ? 'left' : (c.right + c.width * k / 2 > b.right ? 'right' : 'center');
        const y = c.top - c.height * k / 2 < b.top ? 'top' : (c.bottom + c.height * k / 2 > b.bottom ? 'bottom' : 'center');
        card.style.transformOrigin = x + ' ' + y;
    });

    let imDrag = null; // { from: 'main' | 'sb', idx }
    (function bindSlotsDnD() {
        const root = document.getElementById('imSlots');
        if (!root) return;
        const clearOver = () => root.querySelectorAll('.im-drop-over').forEach(x => x.classList.remove('im-drop-over'));
        const resolveTarget = (t) => {
            if (!t || !t.closest) return null;
            const end = t.closest('.im-drop-end');
            if (end) return { kind: 'end', el: end };
            const sbPanel = t.closest('.im-sandbox');
            if (sbPanel) return { kind: 'sandbox', el: sbPanel };
            const card = t.closest('.im-card[data-idx]');
            if (card) return { kind: 'main', idx: +card.dataset.idx, el: card };
            return null;
        };

        root.addEventListener('dragstart', (e) => {
            const card = e.target.closest ? e.target.closest('.im-card') : null;
            if (!card) return;
            if (card.dataset.sb !== undefined) imDrag = { from: 'sb', idx: +card.dataset.sb };
            else if (card.dataset.idx !== undefined) imDrag = { from: 'main', idx: +card.dataset.idx };
            else return;
            card.classList.add('im-card-dragging');
            e.dataTransfer.effectAllowed = 'move';
            try { e.dataTransfer.setData('text/plain', String(imDrag.idx)); } catch (_) {}
        });
        root.addEventListener('dragend', () => {
            imDrag = null;
            clearOver();
            root.querySelectorAll('.im-card-dragging').forEach(x => x.classList.remove('im-card-dragging'));
        });
        root.addEventListener('dragover', (e) => {
            if (!imDrag) return;
            const tg = resolveTarget(e.target);
            if (!tg) return;
            if (tg.kind === 'sandbox' && imDrag.from === 'sb') return;
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            clearOver();
            tg.el.classList.add('im-drop-over');
        });
        root.addEventListener('drop', (e) => {
            if (!imDrag) return;
            const tg = resolveTarget(e.target);
            if (!tg) return;
            e.preventDefault();
            const src = imDrag;
            imDrag = null;
            const insert = e.shiftKey || e.altKey;

            if (tg.kind === 'main') {
                const t = tg.idx;
                if (src.from === 'main') {
                    const f = src.idx;
                    if (f === t) { clearOver(); return; }
                    if (insert) {
                        remapMainIndicesAfterMove(f, t);
                        const moved = modalUrls.splice(f, 1)[0];
                        modalUrls.splice(t, 0, moved);
                    } else {
                        remapSwapIndices(f, t);
                        [modalUrls[f], modalUrls[t]] = [modalUrls[t], modalUrls[f]];
                    }
                } else {
                    const sbUrl = modalSandbox[src.idx];
                    if (sbUrl === undefined) return;
                    if (insert) {
                        remapInsertAt(t);
                        modalUrls.splice(t, 0, sbUrl);
                        modalSandbox.splice(src.idx, 1);
                    } else {
                        const old = modalUrls[t];
                        resetRefsAt(t);
                        modalUrls[t] = sbUrl;
                        modalSandbox.splice(src.idx, 1, old);
                    }
                }
            } else if (tg.kind === 'end') {
                if (src.from === 'main') {
                    const f = src.idx;
                    const last = modalUrls.length - 1;
                    if (f === last) { clearOver(); return; }
                    remapMainIndicesAfterMove(f, last);
                    modalUrls.push(modalUrls.splice(f, 1)[0]);
                } else {
                    const sbUrl = modalSandbox.splice(src.idx, 1)[0];
                    if (sbUrl !== undefined) modalUrls.push(sbUrl);
                }
            } else if (tg.kind === 'sandbox') {
                if (src.from !== 'main') return;
                clampMainIndicesAfterDelete(src.idx);
                modalSandbox.push(modalUrls.splice(src.idx, 1)[0]);
            }
            clearOver();
            renderSlots();
        });
    })();

    // Fetch a file from the server and save it with the name the server gives it.
    async function downloadFromServer(url, options, fallbackName) {
        const res = await fetch(url, options);
        if (!res.ok) {
            const j = await res.json().catch(() => null);
            throw new Error((j && j.message) || ('Download failed (' + res.status + ').'));
        }
        const disposition = res.headers.get('Content-Disposition') || '';
        const match = disposition.match(/filename\*?=(?:UTF-8'')?"?([^";]+)"?/i);
        const name = match ? decodeURIComponent(match[1]) : fallbackName;
        const blob = await res.blob();
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = name;
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(link.href), 10000);
    }

    document.getElementById('downloadAllImagesBtn')?.addEventListener('click', async function () {
        if (!modalUrls.length) return;
        const btn = this;
        const label = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        try {
            await downloadFromServer('/image-master/download-zip', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ sku: document.getElementById('modalSku').value, urls: modalUrls })
            }, 'images.zip');
        } catch (err) {
            alert(err.message || 'Download failed.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = label;
        }
    });

    document.getElementById('removeAllImagesBtn')?.addEventListener('click', () => {
        if (!modalUrls.length) return;
        if (!confirm('Remove all images from this list? Click Save Images afterward to apply the change to Product Master.')) return;
        modalUrls = [];
        modalSandbox = [];
        storedImageMeta.clear();
        modalMainByMp = defaultMainByMarketplace();
        modalHero2ByMp = {};
        pendingFiles = [];
        updatePendingFileUi();
        document.getElementById('uploadSuccessMsg').style.display = 'none';
        renderSlots();
    });

    function syncPendingFileChrome() {
        const label = document.getElementById('fileChosenLabel');
        const list = document.getElementById('uploadPreviewList');
        const items = document.getElementById('uploadPreviewItems');
        const btn = document.getElementById('uploadImagesBtn');
        const input = document.getElementById('modalFileInput');

        if (!pendingFiles.length) {
            if (label) label.textContent = '';
            if (list) list.style.display = 'none';
            if (btn) btn.style.display = 'none';
            if (items) items.innerHTML = '';
            if (input) input.value = '';
            return;
        }

        if (label) {
            label.textContent = pendingFiles.length === 1
                ? pendingFiles[0].name
                : `${pendingFiles.length} files selected`;
        }
        if (list) list.style.display = 'block';
        if (btn) btn.style.display = 'inline-flex';
    }

    function reindexPendingPreviewCards() {
        document.querySelectorAll('#uploadPreviewItems .im-pending-card').forEach((card, idx) => {
            const delBtn = card.querySelector('.im-pending-del');
            if (delBtn) delBtn.dataset.idx = String(idx);
        });
    }

    function updatePendingFileUi() {
        const items = document.getElementById('uploadPreviewItems');
        if (!items) {
            syncPendingFileChrome();
            return;
        }

        items.innerHTML = '';
        if (!pendingFiles.length) {
            syncPendingFileChrome();
            return;
        }

        pendingFiles.forEach((f, idx) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                const card = document.createElement('div');
                card.className = 'im-pending-card text-center';
                card.innerHTML = `
                    <div class="im-pending-img-wrap">
                        <img src="${esc(e.target.result)}" alt="" style="width:80px;height:80px;object-fit:cover;border-radius:6px;border:1px solid #e2e8f0;">
                        <button type="button" class="im-pending-del" data-idx="${idx}" title="Remove"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="small text-truncate mt-1" style="max-width:88px;" title="${esc(f.name)}">${esc(f.name)}</div>`;
                items.appendChild(card);
            };
            reader.readAsDataURL(f);
        });

        syncPendingFileChrome();
    }

    document.getElementById('uploadPreviewItems')?.addEventListener('click', (e) => {
        const btn = e.target.closest('.im-pending-del');
        if (!btn) return;
        const idx = +btn.dataset.idx;
        if (Number.isNaN(idx) || idx < 0 || idx >= pendingFiles.length) return;
        pendingFiles.splice(idx, 1);
        btn.closest('.im-pending-card')?.remove();
        if (!pendingFiles.length) {
            syncPendingFileChrome();
            return;
        }
        reindexPendingPreviewCards();
        syncPendingFileChrome();
    });

    // ── ADD IMAGES: two-step flow (preview → explicit upload) ───────────────────

    document.getElementById('modalFileInput')?.addEventListener('change', function () {
        pendingFiles = Array.from(this.files || []);
        document.getElementById('uploadSuccessMsg').style.display = 'none';
        updatePendingFileUi();
    });

    async function uploadPendingFiles() {
        const sku = document.getElementById('modalSku').value;
        if (!pendingFiles.length) return true;
        if (modalUrls.length >= PM_MAX_IMAGES) {
            toast(`You already have the maximum of ${PM_MAX_IMAGES} images. Remove some before adding more.`, false);
            return false;
        }

        // Enforce the image cap: if the selection would exceed it, add NOTHING and let the user
        // remove the extra files (or existing images) and try again — no silent partial add.
        const allowed = PM_MAX_IMAGES - modalUrls.length;
        if (pendingFiles.length > allowed) {
            toast(`Image limit is ${PM_MAX_IMAGES}. You already have ${modalUrls.length}, so only ${allowed} more can be added. You selected ${pendingFiles.length} — please remove ${pendingFiles.length - allowed} and try again.`, false);
            return false;
        }

        const btn     = document.getElementById('uploadImagesBtn');
        const spinner = document.getElementById('uploadSpinner');
        const msg     = document.getElementById('uploadSuccessMsg');

        if (btn) btn.disabled = true;
        if (spinner) spinner.style.display = 'inline-block';
        if (msg) msg.style.display = 'none';

        try {
            const fd = new FormData();
            fd.append('sku', sku);
            pendingFiles.slice(0, allowed).forEach(f => fd.append('files[]', f));

            const r = await fetch('/image-master/upload', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: fd,
            });
            const j = await r.json();

            if (j.success && j.urls?.length) {
                // Register DB ids so ✕ can delete from server
                (j.images || []).forEach(img => {
                    storedImageMeta.set(img.url, { id: img.id, name: img.name });
                });
                modalUrls = modalUrls.concat(j.urls).slice(0, PM_MAX_IMAGES);
                knownImageUrls = knownImageUrls.concat(j.urls);
                renderSlots();
                const count = j.urls.length;
                msg.textContent   = `${count} image${count > 1 ? 's' : ''} uploaded successfully!`;
                msg.style.display = 'inline';
                toast(`${count} image${count > 1 ? 's' : ''} uploaded`);
                pendingFiles = [];
                updatePendingFileUi();
            } else {
                toast(j.message || 'Upload failed', false);
                return false;
            }
        } catch (e) {
            toast(e.message || 'Upload error', false);
            return false;
        } finally {
            if (btn) btn.disabled = false;
            if (spinner) spinner.style.display = 'none';
        }
        return true;
    }

    document.getElementById('uploadImagesBtn')?.addEventListener('click', async function () {
        await uploadPendingFiles();
    });
    // ── END ADD IMAGES ───────────────────────────────────────────────────────────

    document.getElementById('fetchAmazonBtn')?.addEventListener('click', async () => {
        const sku = document.getElementById('modalSku').value;
        const r = await fetch('/image-master/amazon-images?sku='+encodeURIComponent(sku));
        const j = await r.json();
        if (j.success && j.images?.length) {
            const add = j.images.map(x => typeof x==='string'?x:(x.url||x.locator||'')).filter(Boolean);
            modalUrls = modalUrls.concat(add).slice(0, PM_MAX_IMAGES);
            knownImageUrls = knownImageUrls.concat(add);
            renderSlots();
            toast('Amz images loaded');
        } else toast(j.message || 'No Amz images', false);
    });

    async function fetchEbay(account) {
        const sku = document.getElementById('modalSku').value;
        const r = await fetch('/image-master/ebay-images?sku='+encodeURIComponent(sku)+'&account='+encodeURIComponent(account));
        const j = await r.json();
        if (j.success && j.images?.length) {
            modalUrls = modalUrls.concat(j.images).slice(0, PM_MAX_IMAGES);
            knownImageUrls = knownImageUrls.concat(j.images);
            renderSlots();
            toast('eBay images loaded');
        } else toast(j.message || 'No eBay images', false);
    }
    document.getElementById('fetchEbay1Btn')?.addEventListener('click', () => fetchEbay('ebay'));
    document.getElementById('fetchEbay2Btn')?.addEventListener('click', () => fetchEbay('ebay2'));
    document.getElementById('fetchEbay3Btn')?.addEventListener('click', () => fetchEbay('ebay3'));

    document.getElementById('savePmBtn')?.addEventListener('click', async () => {
        const sku = document.getElementById('modalSku').value;
        const saveBtn = document.getElementById('savePmBtn');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        }

        try {
            if (modalSandbox.length) {
                if (!window.confirm(`${modalSandbox.length} image(s) are still in the sandbox. They will be added to the end of the list when saving. Continue?`)) return;
                modalUrls = modalUrls.concat(modalSandbox);
                modalSandbox = [];
                renderSlots();
            }
            const uploaded = await uploadPendingFiles();
            if (!uploaded) return;

            if (modalUrls.length === 0 && !confirm('Save this product with no images? This only clears Product Master images; it will not change marketplace listings.')) {
                return;
            }

            // Images seen this session but no longer in the list = removed by the user. The server
            // deletes only those (and only when they match a product_images row for THIS sku and are
            // not in the saved set), so a kept image can never be deleted by mistake.
            const removedUrls = knownImageUrls.filter(u => !modalUrls.includes(u));

            const r = await fetch('/image-master/save-pm', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ sku, images: modalUrls, main_by_marketplace: mainByMarketplacePayload(), removed_urls: removedUrls }),
            });
            const j = await r.json();
            if (j.success) {
                toast(modalUrls.length === 0 ? 'Product Master images cleared' : 'Images saved to Product Master');
                const savedRow = bySku.get(String(sku));
                if (savedRow && j.image_main_by_marketplace) {
                    savedRow.image_main_by_marketplace = j.image_main_by_marketplace;
                }
                loadData();
                if (editModal) editModal.hide();
            } else {
                toast(j.message || 'Save failed', false);
            }
        } catch (e) {
            toast(e.message || 'Save failed', false);
        } finally {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fas fa-save"></i>';
            }
        }
    });

    // ── Push mode popup ────────────────────────────────────────────────────────
    let pushModeModal;
    if (window.bootstrap?.Modal) {
        pushModeModal = new bootstrap.Modal(document.getElementById('pushModeModal'));
    }

    function syncPushModeModal(checks, imageCount) {
        const addBtn = document.getElementById('pmAddBtn');
        const addHint = document.getElementById('pmAddHint');
        const addOk = checks.length > 0 && checks.every(mp => ADD_MODE_MARKETPLACES.includes(mp));
        if (addBtn) {
            addBtn.disabled = !addOk;
            addBtn.title = addOk ? '' : 'Add mode requires Shopify Main, Shopify PLS, and/or Reverb only';
        }
        if (addHint) addHint.style.display = addOk ? 'none' : 'block';
        const countEl = document.getElementById('pmImageCount');
        const mpCountEl = document.getElementById('pmMpCount');
        if (countEl) countEl.textContent = String(imageCount);
        if (mpCountEl) mpCountEl.textContent = String(checks.length);
    }

    function canPushImages(checks, imagesToPush) {
        if (imagesToPush.length > 0) return true;
        return checks.length > 0 && checks.every(mp => SHOPIFY_MARKETPLACES.includes(mp));
    }

    function confirmShopifyClear(checks) {
        const names = checks.map(mp => LABELS[mp] || mp).join(', ');
        return window.confirm(`Remove all images from ${names}? This only works for Shopify stores.`);
    }

    document.getElementById('pushModalBtn')?.addEventListener('click', () => {
        const sku    = document.getElementById('modalSku').value;
        const checks = Array.from(document.querySelectorAll('.im-mp-chk:checked:not(:disabled)')).map(c => c.value);
        if (!checks.length) { toast('Select at least one marketplace', false); return; }

        const imagesToPush = selectedUrls.size > 0
            ? modalUrls.filter(u => selectedUrls.has(u))
            : modalUrls;
        if (!canPushImages(checks, imagesToPush)) {
            toast('No images to push. Save images first, or select only Shopify to clear all images.', false);
            return;
        }
        if (imagesToPush.length === 0 && !confirmShopifyClear(checks)) return;
        if (!confirmEbay3Push(checks)) return;

        syncPushModeModal(checks, imagesToPush.length);
        if (pushModeModal) pushModeModal.show();
    });

    async function doPush(mode) {
        if (pushModeModal) pushModeModal.hide();

        const sku    = document.getElementById('modalSku').value;
        const checks = Array.from(document.querySelectorAll('.im-mp-chk:checked:not(:disabled)')).map(c => c.value);
        // Always iterate modalUrls to preserve visual grid order — never iterate the Set directly
        const imagesToPush = selectedUrls.size > 0
            ? modalUrls.filter(u => selectedUrls.has(u))   // grid order, selected only
            : [...modalUrls];                               // grid order, all

        if (!canPushImages(checks, imagesToPush)) {
            toast('No images to push. Save images first, or select only Shopify to clear all images.', false);
            return;
        }
        if (imagesToPush.length === 0 && mode === 'replace' && !confirmShopifyClear(checks)) return;

        const selLabel = selectedUrls.size > 0 ? `${imagesToPush.length} selected` : (imagesToPush.length ? `all ${imagesToPush.length}` : 'clear-all');
        const modeLabel = mode === 'add' ? 'adding to' : 'replacing';

        const progress = [];
        let okCount = 0, failCount = 0, metricsFailCount = 0;
        const updates = checks.map(mp => ({ marketplace: mp, images: imagesToPush }));
        try {
            setPushProgress(true, `Queuing push for ${checks.length} marketplace(s)…`, '', false, true);
            const { res, j } = await queueImagePushAndWait({
                sku,
                mode,
                updates,
                main_by_marketplace: mainByMarketplacePayload(),
            }, (st) => renderImagePushJobProgress(st, `Pushing ${selLabel} image(s) to ${checks.length} marketplace(s)…`));

            if (j && j.results) {
                okCount = 0;
                failCount = 0;
                metricsFailCount = Number(j.total_metrics_failed ?? 0);
                let idx = 0;
                for (const mp of checks) {
                    idx++;
                    const row = j.results[mp] ? j.results[mp] : null;
                    const rowOk = !!(row && row.success);
                    if (rowOk) { okCount++; } else { failCount++; }
                    progress.push(`${idx}/${checks.length} ${LABELS[mp]}: ${rowOk ? 'OK' : 'Failed'}${row && row.message ? ` - ${esc(row.message)}` : ''}`);
                }
            } else if (!res.ok) {
                failCount = checks.length;
                progress.push(esc(j?.message || `HTTP ${res.status}`));
            }
            const hasAnyError = failCount > 0;
            setPushProgress(true, `Push finished: ${okCount} success, ${failCount} failed${metricsFailCount ? `, ${metricsFailCount} metrics save failed` : ''}`, progress.join('<br>'), hasAnyError);
            if (!hasAnyError) {
                toast(`Pushed to ${okCount} marketplace(s).${metricsFailCount ? ` ${metricsFailCount} metrics save failed.` : ''}`, !metricsFailCount);
                loadData();
                if (editModal) editModal.hide();
                // Progress stays visible until the user closes it (the ✕ on the progress box).
            } else {
                toast(`Push completed with failures (${failCount}). See error details below.`, false);
                loadData();
                // Keep failures visible until user clicks elsewhere — don't auto-hide
            }
        } catch (e) {
            failCount = checks.length;
            setPushProgress(true, 'Push failed', esc(e.message || 'Request failed'), true);
            toast(e.message || 'Push failed', false);
        } finally {
            // spinner already stopped in setPushProgress(hasError=true); do nothing here
        }
    }

    document.getElementById('pmReplaceBtn')?.addEventListener('click', () => doPush('replace'));
    document.getElementById('pmAddBtn')?.addEventListener('click', ()     => doPush('add'));
    // ── End push mode popup ────────────────────────────────────────────────────

    function quickPush(sku, mp) {
        if (!marketplaceApiGuard(mp, LABELS[mp])) return;
        const row = bySku.get(String(sku));
        if (!row) return;
        const urls = pmImageUrls(row);
        if (!urls.length) { toast('No images on Product Master for this SKU — open Edit first.', false); openEditModal(sku); return; }
        if (!confirmEbay3Push([mp])) return;
        const mainIdx = mainImageIndexFor(mp, row);
        const mainLabel = mainImageLabelFor(mp, mainIdx);
        if (!window.confirm(`Push ${urls.length} image(s) for ${sku} to ${LABELS[mp]}?\n\nMain image for this platform: ${mainLabel} (sent first).\nThis will replace existing marketplace images.`)) return;
        setPushProgress(true, `Queuing ${LABELS[mp]} push…`, '', false, true);
        queueImagePushAndWait({
            sku,
            mode: 'replace',
            updates: [{ marketplace: mp, images: urls }],
            main_by_marketplace: mainByMarketplacePayloadFromRow(row),
        }, (st) => renderImagePushJobProgress(st, `Pushing to ${LABELS[mp]}…`))
        .then(({ res, j }) => {
            const rowRes = (j.results && j.results[mp]) ? j.results[mp] : null;
            const ok = !!(rowRes && rowRes.success);
            const msg = ok ? (rowRes?.message || j.message || 'Updated') : pushFailureMessage(j, mp, res.status);
            setPushProgress(true, `Push finished: ${ok ? '1 success' : '1 failed'}`, `1/1 ${LABELS[mp]}: ${ok ? '✓ OK' : '✗ Failed'} — ${esc(msg)}`, !ok);
            if (ok) { toast(LABELS[mp]+' pushed'); loadData(); }
            else toast(msg, false);
        }).catch(e => {
            setPushProgress(true, 'Push finished: 1 failed', `1/1 ${LABELS[mp]}: ✗ Failed — ${esc(e.message || 'Request failed')}`, true);
            toast(e.message, false);
        });
    }

    document.getElementById('skuSearchIm')?.addEventListener('input', () => renderTable(tableData));
    document.getElementById('parentSearchIm')?.addEventListener('input', () => renderTable(tableData));
    document.getElementById('rowTypeFilter')?.addEventListener('change', () => renderTable(tableData));
    document.getElementById('invFilter')?.addEventListener('change', () => renderTable(tableData));
    document.getElementById('statusFilter')?.addEventListener('change', () => renderTable(tableData));

    document.getElementById('exportBtn')?.addEventListener('click', () => {
        const rows = tableData.map(row => {
            const o = {
                SKU: row.SKU,
                'Image Count': pmImageUrls(row).length,
                Product: row.Parent||'',
                Preview: row.preview_thumb||'',
            };
            const im = row.image_master||{};
            MARKETPLACES.forEach(mp => { o[LABELS[mp]] = im[mp]||''; });
            return o;
        });
        const ws = XLSX.utils.json_to_sheet(rows);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Image Master');
        XLSX.writeFile(wb, 'image_master_'+new Date().toISOString().split('T')[0]+'.xlsx');
    });

    document.getElementById('importBtn')?.addEventListener('click', () => document.getElementById('importFile').click());
    document.getElementById('importFile')?.addEventListener('change', function(e) {
        const f = e.target.files?.[0];
        if (!f) return;
        const reader = new FileReader();
        reader.onload = function(ev) {
            try {
                const wb = XLSX.read(new Uint8Array(ev.target.result), { type:'array' });
                const json = XLSX.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]]);
                json.forEach(row => {
                    const sku = String(row.SKU||'').trim();
                    if (!sku) return;
                    const item = bySku.get(sku);
                    if (!item) return;
                    if (!item.image_master) item.image_master = {};
                    MARKETPLACES.forEach(mp => {
                        const k = LABELS[mp];
                        if (row[k] != null) item.image_master[mp] = String(row[k]);
                    });
                });
                renderTable(tableData);
                toast('Import merged into table (save/push from Edit modal)');
            } catch (err) { toast('Import failed', false); }
        };
        reader.readAsArrayBuffer(f);
        e.target.value = '';
    });

    let bulkPushAllRunning = false;

    function failedMarketplaceSummary(results) {
        if (!results || typeof results !== 'object') return '';
        const failed = MARKETPLACES.filter(mp => results[mp] && !results[mp].success);
        if (!failed.length) return '';
        return failed.map(mp => LABELS[mp] || mp).join(', ');
    }

    function rowsForBulkPush(mode) {
        const filtered = currentFilteredRowsForPull().filter(r => pmImageUrls(r).length > 0);
        if (mode === 'selected') {
            return filtered.filter(r => selectedSkus.has(String(r.SKU || '')));
        }
        return filtered;
    }

    async function bulkPushAll(mode = 'all', customMps = null) {
        if (bulkPushAllRunning) {
            toast('Bulk push is already running.', false);
            return;
        }
        if (mode === 'selected' && selectedSkus.size === 0) {
            toast('Select at least one row (checkbox) before Push Selected.', false);
            return;
        }
        const rows = rowsForBulkPush(mode);
        if (!rows.length) {
            toast(mode === 'selected'
                ? 'No selected products with images to push.'
                : 'No products with images in the current view.', false);
            return;
        }
        const baseMps = Array.isArray(customMps) && customMps.length ? customMps : ENABLED_MARKETPLACES;
        const configuredMps = typeof filterConfiguredMarketplaces === 'function'
            ? filterConfiguredMarketplaces(baseMps)
            : baseMps.filter(mp => isMarketplaceApiConfigured(mp));
        if (!configuredMps.length) {
            toast('No marketplaces have API credentials configured.', false);
            return;
        }
        const mpCount = configuredMps.length;
        const skuLabel = rows.length === 1 ? '1 product' : `${rows.length} products`;
        const scopeLabel = mode === 'selected' ? 'selected ' : '';
        if (!window.confirm(
            `Push images to ${mpCount} configured marketplace(s) for ${scopeLabel}${skuLabel}?\n\n`
            + 'Per-platform main image settings (Image 1 by default) will be applied.\n'
            + 'Each product is sent in one batch; this may take a long time.'
        )) return;
        if (!confirmEbay3Push(configuredMps)) return;

        bulkPushAllRunning = true;
        const pushAllBtn = document.getElementById('pushAllBtn');
        const pushSelectedBtn = document.getElementById('pushSelectedBtn');
        if (pushAllBtn) pushAllBtn.disabled = true;
        if (pushSelectedBtn) pushSelectedBtn.disabled = true;

        let okSkus = 0;
        let failSkus = 0;
        const progress = [];

        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            const sku = String(row.SKU || '');
            const urls = pmImageUrls(row);
            if (!urls.length) continue;

            const updates = configuredMps.map(mp => ({ marketplace: mp, images: urls }));
            const main_by_marketplace = mainByMarketplacePayloadFromRow(row);
            setPushProgress(
                true,
                `Bulk push ${i + 1}/${rows.length}: queuing ${sku}… (${mpCount} marketplaces)`,
                progress.slice(-40).join('<br>'),
                false,
                true
            );

            try {
                const { res, j } = await queueImagePushAndWait(
                    { sku, mode: 'replace', updates, main_by_marketplace },
                    (st) => renderImagePushJobProgress(st, `Bulk push ${i + 1}/${rows.length}: ${sku}…`)
                );
                const mpFailed = Number(j?.total_failed ?? 0);
                const mpOk = Number(j?.total_success ?? 0);
                const metricsFailed = Number(j?.total_metrics_failed ?? 0);
                const batchOk = res.ok && j && mpFailed === 0 && (mpOk > 0 || j.success);
                if (batchOk) {
                    okSkus++;
                    const metricsNote = metricsFailed > 0 ? ` (${metricsFailed} metrics save failed)` : '';
                    progress.push(`✓ ${esc(sku)} — ${mpOk}/${mpCount} marketplace(s)${metricsNote}`);
                } else {
                    failSkus++;
                    const failedMps = failedMarketplaceSummary(j?.results);
                    const errMsg = j?.message || (mpFailed ? `${mpFailed} marketplace(s) failed` : `HTTP ${res.status}`);
                    progress.push(`✗ ${esc(sku)} — ${esc(errMsg)}${failedMps ? ` (${esc(failedMps)})` : ''}`);
                }
            } catch (e) {
                failSkus++;
                progress.push(`✗ ${esc(sku)} — ${esc(e.message || 'Request failed')}`);
            }

            if (i < rows.length - 1) await sleepMs(1500);
        }

        bulkPushAllRunning = false;
        if (pushAllBtn) pushAllBtn.disabled = false;
        if (pushSelectedBtn) pushSelectedBtn.disabled = false;

        const hasError = failSkus > 0;
        setPushProgress(
            true,
            `Bulk push finished: ${okSkus} SKU(s) ok, ${failSkus} failed`,
            progress.join('<br>'),
            hasError
        );
        toast(`Bulk push done: ${okSkus} ok, ${failSkus} failed`, !hasError);
        loadData();
        // Progress stays visible until the user closes it (the ✕ on the progress box).
    }

    document.getElementById('selectAllRowsIm')?.addEventListener('change', function() {
        fillAllVisibleRows(!!this.checked);
    });
    document.getElementById('pushPickerBtn')?.addEventListener('click', () => {
        if (selectedSkus.size === 0) { toast('Select at least one row (checkbox) before Push.', false); return; }
        openMpPicker('', true);
    });
    document.getElementById('pushSelectedBtn')?.addEventListener('click', () => bulkPushAll('selected'));
    document.getElementById('pushAllBtn')?.addEventListener('click', () => bulkPushAll('all'));

    // ── Shopify image pull (mirrors Bullet Points pull) ───────────────────────

    function appendShopifyPullLog(message, ok = true) {
        const log = document.getElementById('shopifyPullLog');
        if (!log) return;
        const line = document.createElement('div');
        line.className = ok ? 'text-success' : 'text-danger';
        line.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
        log.appendChild(line);
        log.scrollTop = log.scrollHeight;
    }

    function setShopifyPullProgress(done, total, text) {
        const pct = total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 0;
        const bar = document.getElementById('shopifyPullProgress');
        const status = document.getElementById('shopifyPullStatus');
        if (bar) bar.style.width = pct + '%';
        if (status) status.textContent = text || `${done}/${total}`;
    }

    function currentFilteredRowsForPull() {
        const skuQ = (document.getElementById('skuSearchIm')?.value || '').trim().toLowerCase();
        return tableData.filter(r => {
            const sku = String(r.SKU || '');
            if (!sku || sku.toUpperCase().includes('PARENT')) return false;
            if (skuQ && !sku.toLowerCase().includes(skuQ)) return false;
            return true;
        });
    }

    function isShopifyPullActive(status) {
        return ['running', 'paused', 'stopping'].includes(status);
    }

    function renderShopifyPullJob(job) {
        job = job || {};
        const log = document.getElementById('shopifyPullLog');
        const pullBtn = document.getElementById('startShopifyPullBtn');
        const pauseBtn = document.getElementById('pauseShopifyPullBtn');
        const resumeBtn = document.getElementById('resumeShopifyPullBtn');
        const stopBtn = document.getElementById('stopShopifyPullBtn');
        const status = job.status || 'idle';
        const total = Number(job.total || 0);
        const done = Number(job.current_index || 0);
        const active = isShopifyPullActive(status);

        if (pullBtn) pullBtn.disabled = active;
        if (pauseBtn) pauseBtn.style.display = status === 'running' ? 'inline-block' : 'none';
        if (resumeBtn) resumeBtn.style.display = status === 'paused' ? 'inline-block' : 'none';
        if (stopBtn) stopBtn.style.display = active ? 'inline-block' : 'none';

        let text = job.last_message || 'Ready';
        if (status === 'running' && job.current_sku) text = `Running ${done + 1}/${total}: ${job.current_sku}`;
        if (status === 'paused') text = `Paused ${done}/${total}`;
        if (status === 'completed') text = `Done: ${job.ok_count || 0} ok, ${job.fail_count || 0} failed`;
        if (status === 'stopped') text = `Stopped: ${job.ok_count || 0} ok, ${job.fail_count || 0} failed`;
        setShopifyPullProgress(done, total, text);

        if (log) {
            log.innerHTML = '';
            (job.messages || []).forEach(item => {
                const line = document.createElement('div');
                line.className = item.ok ? 'text-success' : 'text-danger';
                line.textContent = `[${item.time || ''}] ${item.message || ''}`;
                log.appendChild(line);
            });
            log.scrollTop = log.scrollHeight;
        }
    }

    async function fetchShopifyPullStatus() {
        const res = await fetch('/image-master/shopify-pull/status', {
            headers: { 'Accept': 'application/json' }
        });
        const payload = await res.json().catch(() => ({}));
        if (!res.ok || !payload.success) throw new Error(payload.message || 'Unable to load Shopify pull status');
        return payload.job || {};
    }

    function clearShopifyPullChunkQueue(cancelled = false) {
        shopifyPullChunkQueue = [];
        if (cancelled) shopifyPullChunkMeta.cancelled = true;
    }

    function chunkArray(list, size) {
        const out = [];
        for (let i = 0; i < list.length; i += size) out.push(list.slice(i, i + size));
        return out;
    }

    async function maybeStartNextShopifyPullChunk(finishedJob) {
        if (shopifyPullAdvancingChunk) return;
        if (shopifyPullChunkMeta.cancelled) {
            clearShopifyPullChunkQueue();
            return;
        }
        if (!shopifyPullChunkQueue.length) {
            if (shopifyPullChunkMeta.chunkCount > 1 && finishedJob) {
                const ok = shopifyPullChunkMeta.ok + Number(finishedJob.ok_count || 0);
                const fail = shopifyPullChunkMeta.fail + Number(finishedJob.fail_count || 0);
                const chunksDone = shopifyPullChunkMeta.chunkCount;
                shopifyPullChunkMeta.chunkCount = 0; // prevent repeat toast on later polls
                toast(`Shopify pull finished all chunks: ${ok} ok, ${fail} failed`, fail === 0);
                appendShopifyPullLog(`All ${chunksDone} chunk(s) finished: ${ok} ok, ${fail} failed.`, fail === 0);
            }
            return;
        }

        shopifyPullAdvancingChunk = true;
        try {
            if (finishedJob) {
                shopifyPullChunkMeta.ok += Number(finishedJob.ok_count || 0);
                shopifyPullChunkMeta.fail += Number(finishedJob.fail_count || 0);
            }

            const nextChunk = shopifyPullChunkQueue.shift();
            shopifyPullChunkMeta.chunkIndex += 1;
            const label = `chunk ${shopifyPullChunkMeta.chunkIndex}/${shopifyPullChunkMeta.chunkCount} (${nextChunk.length} SKU(s))`;
            appendShopifyPullLog(`Starting ${label}…`, true);
            toast(`Starting Shopify pull ${label}…`);

            const res = await fetch('/image-master/shopify-pull/start', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ skus: nextChunk })
            });
            const payload = await res.json().catch(() => ({}));
            if (!res.ok || !payload.success) throw new Error(payload.message || 'Unable to start next pull chunk');
            renderShopifyPullJob(payload.job);
            startShopifyPullPolling();
        } catch (e) {
            appendShopifyPullLog('Next chunk failed to start: ' + e.message, false);
            toast('Shopify pull chunk failed: ' + e.message, false);
            clearShopifyPullChunkQueue(true);
        } finally {
            shopifyPullAdvancingChunk = false;
        }
    }

    async function pollShopifyPullStatus() {
        try {
            const job = await fetchShopifyPullStatus();
            const wasActive = shopifyPullPollTimer !== null;
            const status = job.status || 'idle';
            renderShopifyPullJob(job);
            if (isShopifyPullActive(status)) {
                startShopifyPullPolling();
                return;
            }
            stopShopifyPullPolling();
            if (status === 'stopped') {
                clearShopifyPullChunkQueue(true);
                if (wasActive) loadData();
                return;
            }
            if (status === 'completed') {
                const hadMoreChunks = shopifyPullChunkQueue.length > 0 && !shopifyPullChunkMeta.cancelled;
                await maybeStartNextShopifyPullChunk(job);
                if (wasActive && !hadMoreChunks) loadData();
                else if (hadMoreChunks) { /* next chunk polling already started */ }
                else if (wasActive) loadData();
            }
        } catch (e) {
            appendShopifyPullLog('Status check failed: ' + e.message, false);
        }
    }

    function startShopifyPullPolling() {
        if (shopifyPullPollTimer !== null) return;
        shopifyPullPollTimer = window.setInterval(pollShopifyPullStatus, 3000);
    }

    function stopShopifyPullPolling() {
        if (shopifyPullPollTimer === null) return;
        window.clearInterval(shopifyPullPollTimer);
        shopifyPullPollTimer = null;
    }

    async function openShopifyPullModal(skus = null) {
        shopifyPullSelectedSkus = Array.isArray(skus) && skus.length
            ? skus.map(sku => String(sku || '').trim()).filter(Boolean)
            : null;
        const scope = document.getElementById('shopifyPullScopeText');
        if (scope) {
            if (shopifyPullSelectedSkus) {
                const n = shopifyPullSelectedSkus.length;
                const preview = shopifyPullSelectedSkus.slice(0, 8).join(', ');
                scope.textContent = n === 1
                    ? `Scope: selected SKU ${preview}.`
                    : `Scope: ${n} selected SKU(s) — ${preview}${n > 8 ? '…' : ''}. Pulled in chunks of ${SHOPIFY_PULL_CHUNK_SIZE}.`;
            } else {
                scope.textContent = `Scope: currently filtered SKUs. Pulled in chunks of ${SHOPIFY_PULL_CHUNK_SIZE}.`;
            }
        }
        if (shopifyPullModal) shopifyPullModal.show();
        await pollShopifyPullStatus();
    }

    function confirmShopifyPull(scopeText) {
        const scope = document.getElementById('shopifyPullConfirmScope');
        if (scope) {
            scope.textContent = `Do you want to pull images from Shopify for ${scopeText}?`;
        }

        return new Promise(resolve => {
            shopifyPullConfirmResolver = resolve;
            if (shopifyPullConfirmModal) {
                shopifyPullConfirmModal.show();
            } else {
                resolve(false);
            }
        });
    }

    async function startShopifyPullJobForSkus(skus, options = {}) {
        skus = (skus || []).map(sku => String(sku || '').trim()).filter(Boolean);
        if (!skus.length) {
            toast('No SKUs loaded to pull from Shopify.', false);
            return false;
        }

        const chunks = chunkArray(skus, SHOPIFY_PULL_CHUNK_SIZE);
        const chunkNote = chunks.length > 1
            ? ` in ${chunks.length} chunks of up to ${SHOPIFY_PULL_CHUNK_SIZE}`
            : '';
        const scopeText = options.scopeText || `${skus.length} SKU(s)${chunkNote}`;
        if (!await confirmShopifyPull(scopeText)) {
            return false;
        }

        shopifyPullChunkMeta = {
            totalSkus: skus.length,
            chunkIndex: 1,
            chunkCount: chunks.length,
            ok: 0,
            fail: 0,
            cancelled: false,
        };
        shopifyPullChunkQueue = chunks.slice(1);
        const firstChunk = chunks[0];

        try {
            const res = await fetch('/image-master/shopify-pull/start', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ skus: firstChunk })
            });
            const payload = await res.json().catch(() => ({}));
            if (!res.ok || !payload.success) throw new Error(payload.message || 'Unable to start Shopify pull');
            renderShopifyPullJob(payload.job);
            startShopifyPullPolling();
            const startedMsg = chunks.length > 1
                ? `Background Shopify pull started (chunk 1/${chunks.length}, ${firstChunk.length} SKU(s)). Remaining chunks start automatically.`
                : (options.successMessage || payload.message || 'Background Shopify image pull started.');
            if (chunks.length > 1) {
                appendShopifyPullLog(`Queued ${chunks.length} chunk(s) for ${skus.length} SKU(s).`, true);
            }
            toast(startedMsg);
            return true;
        } catch (e) {
            clearShopifyPullChunkQueue(true);
            toast('Shopify pull start failed: ' + e.message, false);
            if (e.message.includes('already')) pollShopifyPullStatus();
            return false;
        }
    }

    async function startSingleShopifyPull(sku, btn) {
        sku = String(sku || '').trim();
        if (!sku) {
            toast('SKU missing for Shopify pull.', false);
            return;
        }

        const oldHtml = btn ? btn.innerHTML : '';
        const oldTitle = btn ? btn.getAttribute('title') : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btn.setAttribute('title', 'Syncing Shopify images...');
        }

        const started = await startShopifyPullJobForSkus([sku], {
            scopeText: `SKU ${sku}`,
            successMessage: `Shopify image sync started for ${sku}.`,
        });

        if (!started && btn) {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
            if (oldTitle) btn.setAttribute('title', oldTitle);
        }
    }

    async function startShopifyPullToLocal() {
        const fromCheckboxes = Array.from(selectedSkus).filter(Boolean);
        const rows = (shopifyPullSelectedSkus && shopifyPullSelectedSkus.length)
            ? shopifyPullSelectedSkus.map(sku => ({ SKU: sku }))
            : (fromCheckboxes.length
                ? fromCheckboxes.map(sku => ({ SKU: sku }))
                : currentFilteredRowsForPull());
        const skus = rows.map(row => String(row.SKU || '').trim()).filter(Boolean);
        if (!skus.length) {
            toast('No SKUs loaded to pull from Shopify.', false);
            return;
        }
        const scopeText = (shopifyPullSelectedSkus && shopifyPullSelectedSkus.length) || fromCheckboxes.length
            ? `${skus.length} selected SKU(s)`
            : `${skus.length} currently filtered SKU(s)`;
        await startShopifyPullJobForSkus(skus, { scopeText });
    }

    async function controlShopifyPull(action) {
        try {
            if (action === 'stop') clearShopifyPullChunkQueue(true);
            const res = await fetch(`/image-master/shopify-pull/${action}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({})
            });
            const payload = await res.json().catch(() => ({}));
            if (!res.ok || !payload.success) throw new Error(payload.message || `Unable to ${action} Shopify pull`);
            renderShopifyPullJob(payload.job);
            if (isShopifyPullActive((payload.job || {}).status || 'idle')) startShopifyPullPolling();
            toast(`Shopify pull ${action} requested.`);
        } catch (e) {
            toast(`Shopify pull ${action} failed: ${e.message}`, false);
        }
    }

    document.getElementById('pullShopifyBtn')?.addEventListener('click', () => {
        const selected = Array.from(selectedSkus).filter(Boolean);
        openShopifyPullModal(selected.length ? selected : null);
    });
    document.getElementById('startShopifyPullBtn')?.addEventListener('click', startShopifyPullToLocal);
    document.getElementById('pauseShopifyPullBtn')?.addEventListener('click', () => controlShopifyPull('pause'));
    document.getElementById('resumeShopifyPullBtn')?.addEventListener('click', () => controlShopifyPull('resume'));
    document.getElementById('stopShopifyPullBtn')?.addEventListener('click', () => controlShopifyPull('stop'));
    document.getElementById('shopifyPullConfirmBtn')?.addEventListener('click', () => {
        if (shopifyPullConfirmResolver) shopifyPullConfirmResolver(true);
        shopifyPullConfirmResolver = null;
        if (shopifyPullConfirmModal) shopifyPullConfirmModal.hide();
    });
    document.getElementById('shopifyPullConfirmModal')?.addEventListener('hidden.bs.modal', () => {
        if (shopifyPullConfirmResolver) shopifyPullConfirmResolver(false);
        shopifyPullConfirmResolver = null;
    });

    if (window.bootstrap?.Modal) {
        editModal = new bootstrap.Modal(document.getElementById('editImModal'));
        shopifyPullModal = new bootstrap.Modal(document.getElementById('shopifyPullModal'));
        shopifyPullConfirmModal = new bootstrap.Modal(document.getElementById('shopifyPullConfirmModal'));
    }
    document.getElementById('mpSelAllBtn')?.addEventListener('click', (e) => {
        e.preventDefault();
        document.querySelectorAll('#editImModal .im-mp-chk:not(:disabled)').forEach(c => { c.checked = true; });
    });
    document.getElementById('mpSelNoneBtn')?.addEventListener('click', (e) => {
        e.preventDefault();
        document.querySelectorAll('#editImModal .im-mp-chk').forEach(c => { c.checked = false; });
    });
    loadData();
    pollShopifyPullStatus();
    resumeImagePushProgress();
});
</script>
@endsection

