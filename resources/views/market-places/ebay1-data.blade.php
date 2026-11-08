@extends('layouts.vertical', ['title' => 'Ebay1 Data', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        .e1d-card { border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 2px 12px rgba(44,110,213,.06); }
        .e1d-card .card-body { padding: 1rem 1.25rem; }
        .e1d-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; margin-bottom: .75rem; }
        .e1d-toolbar input, .e1d-toolbar select {
            height: 32px; border-radius: 8px; border: 1px solid #cbd5e1; padding: 0 .6rem; font-size: .84rem;
        }
        .e1d-toolbar input { min-width: 240px; }
        .e1d-toolbar .btn { padding: .3rem .65rem; font-size: .8rem; }
        .e1d-badges { display: flex; flex-wrap: wrap; gap: .4rem; margin-left: auto; }
        .e1d-badge { padding: .25rem .6rem; border-radius: 6px; font-size: .78rem; font-weight: 700; color: #fff; }
        .e1d-badge.req { background: #16a34a; }
        .e1d-badge.nrl { background: #dc2626; }
        .e1d-badge.listed { background: #0284c7; }
        .e1d-badge.missing { background: #be123c; }
        .e1d-badge.rows { background: #334155; }
        .e1d-tabs {
            display: flex; flex-wrap: nowrap; gap: 4px; overflow-x: auto; overflow-y: hidden;
            border-bottom: 2px solid #e2e8f0; margin-bottom: .75rem; padding-bottom: 0;
            scrollbar-width: thin;
        }
        .e1d-tab {
            flex: 0 0 auto; max-width: 280px; display: inline-flex; align-items: center; gap: 6px;
            border: 1px solid #e2e8f0; border-bottom: none; border-radius: 8px 8px 0 0;
            background: #f8fafc; color: #475569; padding: .4rem .75rem; font-size: .8rem; font-weight: 600;
            cursor: pointer; white-space: nowrap;
        }
        .e1d-tab .e1d-tab-name { overflow: hidden; text-overflow: ellipsis; }
        .e1d-tab .e1d-tab-count {
            background: #e2e8f0; color: #334155; border-radius: 10px; padding: 0 .45rem; font-size: .72rem;
        }
        .e1d-tab:hover { background: #eff6ff; color: #1d4ed8; }
        .e1d-tab.active { background: #2c6ed5; border-color: #2c6ed5; color: #fff; }
        .e1d-tab.active .e1d-tab-count { background: rgba(255,255,255,.25); color: #fff; }
        #ebay1-data-table { border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; background: #fff; }
        #ebay1-data-table .tabulator-header { background: linear-gradient(135deg,#2c6ed5 0%,#1a56b7 100%); color: #fff; }
        #ebay1-data-table .tabulator-col { background: transparent !important; border-right: 1px solid rgba(255,255,255,.15); }
        #ebay1-data-table .tabulator-col .tabulator-col-title { color: #fff; font-size: 12px; font-weight: 700; }
        #ebay1-data-table .tabulator-header .tabulator-col.tabulator-frozen { background: #2c6ed5 !important; }
        .e1g-wrap { position: relative; display: inline-block; }
        .e1d-toolbar .e1g-panel {
            position: absolute; top: 100%; left: 0; z-index: 1500; width: min(480px, 92vw); max-height: 65vh; overflow: auto;
            background: #fff; border: 1px solid #cbd5e1; border-radius: 10px; box-shadow: 0 10px 30px rgba(15,23,42,.2);
            padding: .4rem; margin-top: 4px; color: #334155; font-size: .84rem;
        }
        .e1g-panel[hidden] { display: none; }
        .e1d-toolbar .e1g-panel input[type=checkbox] { height: auto; min-width: 0; width: auto; padding: 0; margin: 0; border-radius: 3px; }
        .e1d-toolbar .e1g-panel input[type=text], .e1d-toolbar .e1g-panel input[type=search] { height: 28px; min-width: 0; width: 100%; font-size: .8rem; }
        .e1d-toolbar .e1g-panel select { height: 26px; font-size: .74rem; max-width: 120px; padding: 0 .2rem; border-radius: 6px; }
        .e1g-label { display: inline-flex; align-items: center; gap: 6px; margin: 0; cursor: pointer; }
        .e1g-top { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: .35rem .3rem; font-size: .78rem; color: #64748b; border-bottom: 1px solid #e2e8f0; margin-bottom: .3rem; }
        .e1g-group { border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: .3rem; background: #f8fafc; }
        .e1g-head { display: flex; align-items: center; gap: 6px; padding: .3rem .5rem; }
        .e1g-name { font-weight: 700; flex: 1; cursor: pointer; }
        .e1g-count { color: #64748b; font-size: .75rem; }
        .e1g-body { padding: .2rem .5rem .5rem 1.6rem; background: #fff; border-top: 1px solid #e2e8f0; border-radius: 0 0 8px 8px; }
        .e1g-col { display: flex; align-items: center; gap: 6px; padding: .12rem 0; }
        .e1g-col .e1g-label { flex: 1; }
        .e1g-icon { border: 0; background: transparent; color: #64748b; padding: 0 .3rem; font-weight: 700; line-height: 1; }
        .e1g-icon:hover { color: #dc2626; }
        .e1g-add { display: flex; gap: 6px; margin-top: .35rem; }
        .e1g-new { display: flex; gap: 6px; padding: .4rem .2rem .1rem; border-top: 1px solid #e2e8f0; margin-top: .3rem; }
        /* Frozen columns (select box and SKU on the left, pen on the right) stay solid while the rest scrolls under them. */
        #ebay1-data-table .tabulator-row .tabulator-cell.tabulator-frozen { background: #fff; z-index: 13; }
        #ebay1-data-table .tabulator-row.tabulator-row-even .tabulator-cell.tabulator-frozen { background: #f8fafc; }
        #ebay1-data-table .tabulator-row:hover .tabulator-cell.tabulator-frozen { background: #e8f0fe !important; }
        #ebay1-data-table .tabulator-row.tabulator-selected .tabulator-cell.tabulator-frozen { background: #d6e6ff; }
        #ebay1-data-table .tabulator-cell.tabulator-frozen-left[tabulator-field="sku"],
        #ebay1-data-table .tabulator-col.tabulator-frozen-left[tabulator-field="sku"] { box-shadow: 3px 0 6px rgba(15,23,42,.15); }
        #ebay1-data-table .tabulator-frozen.tabulator-frozen-right { box-shadow: -3px 0 6px rgba(15,23,42,.15); z-index: 14; }
        #ebay1-data-table .tabulator-col[tabulator-field="_edit"] .tabulator-col-title,
        #ebay1-data-table .tabulator-col[tabulator-field="_copy"] .tabulator-col-title { writing-mode: horizontal-tb; transform: none; }
        /* Cell colours (text colour / fill), like Excel. They sit before the selection rules, so a selected cell still shows as selected. */
        #ebay1-data-table .tabulator-row .tabulator-cell.e1d-bg.e1d-bg.e1d-bg { background-color: var(--e1d-bg) !important; }
        #ebay1-data-table .tabulator-row .tabulator-cell.e1d-fg, #ebay1-data-table .tabulator-row .tabulator-cell.e1d-fg * { color: var(--e1d-fg) !important; }
        .e1d-fmt .e1d-fmt-main { position: relative; padding: 2px 8px 7px; }
        .e1d-fmt .e1d-fmt-caret { padding: 2px 5px; }
        .e1d-fmt .e1d-swatch { position: absolute; left: 6px; right: 6px; bottom: 2px; height: 3px; border-radius: 1px; background: #ff0000; }
        #e1dPal { position: fixed; z-index: 2500; display: none; width: 236px; padding: 8px; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; box-shadow: 0 10px 30px rgba(15,23,42,.25); }
        #e1dPal .e1d-pal-title { font-size: 11px; font-weight: 700; color: #475569; margin-bottom: 6px; }
        #e1dPal .e1d-pal-grid { display: grid; grid-template-columns: repeat(10, 1fr); gap: 3px; margin-bottom: 4px; }
        #e1dPal .e1d-sw { width: 20px; height: 20px; padding: 0; border: 1px solid #cbd5e1; border-radius: 3px; cursor: pointer; }
        #e1dPal .e1d-sw:hover { outline: 2px solid #1a56b7; outline-offset: 1px; }
        #e1dPal .e1d-pal-btn { display: block; width: 100%; text-align: left; padding: 4px 6px; margin-bottom: 6px; font-size: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; cursor: pointer; }
        #e1dPal .e1d-pal-btn:hover { background: #e8f0fe; }
        #e1dPal .e1d-pal-foot { margin-top: 6px; padding-top: 6px; border-top: 1px solid #e2e8f0; font-size: 12px; display: flex; align-items: center; gap: 6px; }
        /* Excel-like cell selection: click a cell, Shift+click or drag for a block, Ctrl+C / Ctrl+V. */
        #ebay1-data-table .tabulator-cell { -webkit-user-select: none; user-select: none; }
        #ebay1-data-table .tabulator-row .tabulator-cell.e1d-cell-sel.e1d-cell-sel.e1d-cell-sel { background: #cfe0ff !important; box-shadow: inset 0 0 0 1px #8fb0f0; }
        #ebay1-data-table .tabulator-row .tabulator-cell.e1d-cell-sel.e1d-cell-sel.e1d-cell-sel.e1d-cell-act { background: #fff !important; box-shadow: inset 0 0 0 2px #1a56b7; }
        /* Small pen button to save space. */
        #ebay1-data-table .tabulator-cell.e1d-edit-col { padding: 2px !important; }
        #ebay1-data-table .e1d-edit-btn { padding: 0 !important; width: 22px; height: 20px; line-height: 18px; font-size: 10px; border-radius: 4px; }
        #ebay1-data-table .tabulator-col[tabulator-field="_edit"] .tabulator-col-content,
        #ebay1-data-table .tabulator-col[tabulator-field="_copy"] .tabulator-col-content { padding: 8px 0 !important; }
        #ebay1-data-table .tabulator-col[tabulator-field="_edit"] .tabulator-col-title i,
        #ebay1-data-table .tabulator-col[tabulator-field="_copy"] .tabulator-col-title i { font-size: 11px; transform: none; margin: 0; }
        /* Every header is written vertically, reading bottom to top. */
        #ebay1-data-table .tabulator-header,
        #ebay1-data-table .tabulator-header .tabulator-headers,
        #ebay1-data-table .tabulator-col,
        #ebay1-data-table .tabulator-col-content,
        #ebay1-data-table .tabulator-col-title-holder { height: 105px !important; }
        #ebay1-data-table .tabulator-col-content {
            display: flex !important; align-items: flex-end; justify-content: center; padding: 8px 2px !important;
        }
        #ebay1-data-table .tabulator-col-title-holder { display: flex; align-items: center; justify-content: flex-end; }
        #ebay1-data-table .tabulator-col .tabulator-col-title {
            writing-mode: vertical-rl; transform: rotate(180deg); white-space: nowrap; overflow: hidden;
            text-overflow: ellipsis; max-height: 76px; line-height: 1.15; letter-spacing: .02em; text-align: center;
        }
        /* Red count of rows with no data, at the top of the header. */
        #ebay1-data-table .tabulator-col .e1d-miss {
            position: absolute; top: 4px; left: 50%; transform: translateX(-50%); z-index: 3; min-width: 18px; padding: 0 5px;
            border-radius: 9px; background: #dc2626; color: #fff; border: 1px solid #fff; font-size: 10px; font-weight: 700;
            line-height: 15px; text-align: center; pointer-events: auto; cursor: default;
        }
        #ebay1-data-table .tabulator-col .tabulator-col-title i { transform: rotate(90deg); display: inline-block; margin-top: 4px; }
        #ebay1-data-table .tabulator-row:nth-child(even) { background: #f8fafc; }
        #ebay1-data-table .tabulator-row:hover { background: #e8f0fe !important; }
        #ebay1-data-table .tabulator-cell { font-size: 12px; color: #475569; padding: 4px 6px; }
        #ebay1-data-table .tabulator-col-content { padding-left: 4px; padding-right: 4px; }
        .e1d-pill { display: inline-block; padding: .1rem .5rem; border-radius: 6px; font-size: .75rem; font-weight: 700; color: #fff; }
        .e1d-copy { cursor: pointer; color: #64748b; margin-right: .45rem; padding: 0 .15rem; }
        .e1d-copy:hover { color: #0284c7; }
        .e1d-title-text { vertical-align: middle; }
        .e1d-tick { font-size: 1.1rem; vertical-align: middle; }
        .e1d-tick.green { color: #16a34a; }
        .e1d-tick.red { color: #dc2626; }
        .e1d-dot { display: inline-block; width: 14px; height: 14px; border-radius: 50%; vertical-align: middle; cursor: pointer; }
        .e1d-dot.green { background: #16a34a; }
        .e1d-dot.red { background: #dc2626; }
        .e1d-pill.green { background: #16a34a; }
        .e1d-pill.red { background: #dc2626; }
        .e1d-pill.amber { background: #d97706; }
        .e1d-pill.blue { background: #0284c7; }
        .e1d-pill.grey { background: #64748b; }
        .e1d-muted { color: #94a3b8; }
        .e1d-modal { position: fixed; inset: 0; background: rgba(15,23,42,.28); backdrop-filter: blur(3px); z-index: 2000; display: flex; align-items: stretch; justify-content: flex-end; }
        .e1d-modal[hidden] { display: none; }
        .e1d-addlist { flex: 1 1 auto; min-height: 140px; overflow-y: auto; border: 1px solid #eef2f7; border-radius: 12px; background: #fff; scrollbar-width: thin; }
        .e1d-addlist:empty { display: none; }
        .e1d-chips { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 6px; }
        .e1d-chips:empty { display: none; }
        .e1d-chip { background: #dcfce7; color: #166534; border-radius: 12px; padding: 1px 4px 1px 10px; font-size: .78rem; font-weight: 600; }
        .e1d-chip button { border: 0; background: transparent; color: #166534; font-weight: 700; padding: 0 4px; }
        .e1d-addopt { display: flex; align-items: center; gap: 10px; padding: .55rem .9rem; font-size: .88rem; color: #334155; cursor: pointer; margin: 0; border-bottom: 1px solid #f4f6fa; transition: background .12s; }
        .e1d-cnt { margin-left: auto; font-size: .8rem; font-weight: 700; font-variant-numeric: tabular-nums; }
        .e1d-cnt.green { color: #16a34a; }
        .e1d-cnt.red { color: #dc2626; }
        .e1d-job { border: 1px solid #e6ecf5; border-radius: 12px; background: #fcfdff; padding: .65rem .9rem; margin: 0 0 .75rem; box-shadow: 0 2px 10px rgba(15,23,42,.05); }
        .e1d-job[hidden] { display: none; }
        .e1d-job-head { display: flex; align-items: center; gap: .6rem; font-size: .88rem; color: #0f172a; }
        .e1d-job-counts { margin-left: auto; display: flex; gap: .8rem; font-weight: 700; font-variant-numeric: tabular-nums; }
        .e1d-ok { color: #16a34a; }
        .e1d-bad { color: #dc2626; }
        .e1d-job-x { border: 0; background: transparent; color: #94a3b8; width: 26px; height: 26px; border-radius: 50%; }
        .e1d-job-x:hover { background: #f1f5f9; color: #334155; }
        .e1d-job-bar { display: flex; height: 8px; border-radius: 999px; background: #e9eef6; overflow: hidden; margin-top: .5rem; }
        .e1d-job-ok { background: #16a34a; transition: width .25s; }
        .e1d-job-bad { background: #dc2626; transition: width .25s; }
        .e1d-job-note { font-size: .78rem; color: #dc2626; margin-top: .4rem; white-space: pre-line; }
        .e1d-job-note:empty { display: none; }
        .e1d-job-icon .fa-spin { color: #2c6ed5; }
        .e1d-sku-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .06em; color: #64748b; font-weight: 600; }
        .e1d-sku-list { display: flex; flex-wrap: wrap; gap: 4px; max-height: 88px; overflow: auto; margin: .25rem 0 .7rem; }
        .e1d-sku-list span { background: #eef4ff; color: #1e40af; border-radius: 999px; padding: 2px 8px; font-size: .75rem; font-weight: 600; }
        .e1d-editfields { display: flex; flex-direction: column; gap: .5rem; max-height: 38vh; overflow-y: auto; padding-right: 2px; }
        .e1d-editfields:empty::before { content: 'Tick a column first'; color: #94a3b8; font-size: .85rem; padding: .35rem .1rem; }
        .e1d-editfield label { font-size: .74rem; font-weight: 600; color: #475569; margin: 0 0 .15rem; display: block; }
        .e1d-editfield input { width: 100%; }
        .e1d-modal-box { position: relative; }
        .e1d-x { position: absolute; top: 1.1rem; right: 1.1rem; width: 34px; height: 34px; border: 0; border-radius: 50%; background: #f1f5f9; color: #475569; display: flex; align-items: center; justify-content: center; transition: background .12s; }
        .e1d-x:hover { background: #e2e8f0; color: #0f172a; }
        .e1d-hdrrow { display: flex; align-items: center; gap: 8px; padding: .35rem .6rem; border-bottom: 1px solid #f1f5f9; }
        .e1d-hdrrow .e1d-hdrorig { flex: 0 0 38%; font-size: .78rem; color: #64748b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .e1d-hdrrow input { flex: 1 1 auto; min-width: 0; }
        .e1d-hdrrow input.changed { border-color: #0284c7; background: #f0f9ff; }
        .e1d-hdrrow .e1d-hdrundo { border: 0; background: transparent; color: #94a3b8; padding: 0 .3rem; }
        .e1d-hdrrow .e1d-hdrundo:hover { color: #dc2626; }
        .e1d-addopt:last-child { border-bottom: 0; }
        .e1d-addopt:hover { background: #f5f8ff; }
        .e1d-addopt input[type=checkbox] { width: 16px; height: 16px; accent-color: #2c6ed5; flex: 0 0 auto; }
        .e1d-addlist button { display: block; width: 100%; text-align: left; border: 0; border-bottom: 1px solid #f4f6fa; background: #fff; padding: .55rem .9rem; font-size: .88rem; color: #334155; transition: background .12s; }
        .e1d-addlist button:hover, .e1d-addlist button.active { background: #f5f8ff; color: #1d4ed8; }
        .e1d-addlist button.new { color: #15803d; font-weight: 700; border-top: 1px solid #e2e8f0; }
        /* Full-height side panel: header on top, list fills the middle, buttons pinned to the bottom. */
        .e1d-modal-box { background: #fcfdff; height: 100vh; width: min(540px, 100vw); padding: 1.75rem 1.75rem 1.25rem; display: flex; flex-direction: column; gap: .35rem; overflow-y: auto; box-shadow: -12px 0 40px rgba(15,23,42,.14); animation: e1dSlide .22s ease-out; }
        @keyframes e1dSlide { from { transform: translateX(32px); opacity: 0; } to { transform: none; opacity: 1; } }
        .e1d-modal-box > h5 { font-size: 1.15rem; font-weight: 600; letter-spacing: .01em; color: #0f172a; margin: 0 0 .15rem !important; }
        .e1d-modal-box > h5 i { margin-right: .35rem; }
        .e1d-modal-box > p { margin: 0 0 .9rem !important; color: #94a3b8 !important; }
        .e1d-modal-box .form-label { font-size: .72rem !important; text-transform: uppercase; letter-spacing: .06em; color: #64748b; margin: .6rem 0 .25rem; font-weight: 600 !important; }
        .e1d-modal-box .form-control, .e1d-modal-box .form-select { border-radius: 10px; border-color: #e5eaf2; background: #fff; padding: .5rem .75rem; font-size: .9rem; height: auto; box-shadow: none; }
        .e1d-modal-box .form-control:focus, .e1d-modal-box .form-select:focus { border-color: #8db2f0; box-shadow: 0 0 0 3px rgba(44,110,213,.12); }
        .e1d-modal-box .alert { border: 0; border-radius: 10px; margin: .5rem 0 !important; }
        .e1d-modal-box > .d-flex:last-child { margin-top: auto; padding-top: 1rem; border-top: 1px solid #eef2f7; }
        .e1d-modal-box .btn { border-radius: 999px; padding: .42rem 1.15rem; font-weight: 600; }
        .e1d-modal-box .btn-light { background: #f1f5f9; border-color: #f1f5f9; color: #475569; }
        .e1d-modal-box .btn-light:hover { background: #e2e8f0; }
        .e1d-chip { padding: 3px 6px 3px 12px; }
    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', [
        'page_title' => 'Ebay1 Data',
        'sub_title' => 'Every eBay 1 listing row, category wise',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card e1d-card">
                <div class="card-body">
                    <div class="e1d-toolbar">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="e1dAuto" aria-pressed="false" title="Auto Push: when ON, the cells you edit and the cells autofill changes are pushed to the live eBay 1 listings by themselves (only those cells)">
                            <i class="fas fa-bolt"></i> Auto Push: <b id="e1dAutoState">OFF</b>
                        </button>
                        <div class="btn-group btn-group-sm e1d-fmt" role="group" aria-label="Cell text colour">
                            <button type="button" class="btn btn-outline-secondary e1d-fmt-main" id="e1dTxtApply" title="Text colour: colour the text of the selected cells (the bar shows the colour used)" aria-label="Text colour">
                                <i class="fas fa-font"></i><span class="e1d-swatch" id="e1dTxtSwatch"></span>
                            </button>
                            <button type="button" class="btn btn-outline-secondary e1d-fmt-caret" id="e1dTxtMenu" title="Pick a text colour" aria-label="Pick a text colour"><i class="fas fa-caret-down"></i></button>
                        </div>
                        <div class="btn-group btn-group-sm e1d-fmt" role="group" aria-label="Cell background colour">
                            <button type="button" class="btn btn-outline-secondary e1d-fmt-main" id="e1dBgApply" title="Fill colour: colour the background of the selected cells (the bar shows the colour used)" aria-label="Fill colour">
                                <i class="fas fa-fill-drip"></i><span class="e1d-swatch" id="e1dBgSwatch"></span>
                            </button>
                            <button type="button" class="btn btn-outline-secondary e1d-fmt-caret" id="e1dBgMenu" title="Pick a fill colour" aria-label="Pick a fill colour"><i class="fas fa-caret-down"></i></button>
                        </div>
                        <input type="search" id="e1dSearch" placeholder="Search SKU, parent, title or item ID…" autocomplete="off">
                        <select id="e1dListed" title="Listing state">
                            <option value="all">All listing states</option>
                            <option value="Listed">Listed</option>
                            <option value="Missing">Missing L</option>
                            <option value="Created">Created (not live yet)</option>
                        </select>
                        <select id="e1dNr" title="Required or not">
                            <option value="all">REQ + NRL</option>
                            <option value="REQ">REQ only</option>
                            <option value="NR">NRL only</option>
                        </select>
                        <select id="e1dStock" title="Stock">
                            <option value="all">Any stock</option>
                            <option value="in">In stock (INV &gt; 0)</option>
                            <option value="out">Out of stock</option>
                        </select>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="e1dRefresh" title="Refresh: reload the data" aria-label="Refresh">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-success" id="e1dExport" title="CSV: download the rows of this tab" aria-label="CSV">
                            <i class="fas fa-file-csv"></i>
                        </button>
                        <span class="small text-muted" id="e1dPullStatus"></span>
                        <button type="button" class="btn btn-sm btn-primary" id="e1dPush" title="Push the item specifics of the ticked rows, or of the whole open tab, to the live eBay 1 listings" disabled>
                            <i class="fas fa-cloud-upload-alt"></i> <span id="e1dPushLabel">0</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-success" id="e1dAddCol" title="Add Column: add a column to the open category tab" aria-label="Add Column" disabled>
                            <i class="fas fa-plus"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-info text-white" id="e1dSrcBtn" title="Source Data: choose which Technical Specifications column a column of this tab always reads from" aria-label="Source Data" disabled>
                            <i class="fas fa-link"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-warning" id="e1dEditCol" title="Edit Column: set one or more columns to a value on every row of the open tab, or only on the ticked rows" aria-label="Edit Column" disabled>
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-secondary" id="e1dHdrBtn" title="Edit Column Header: rename the header of any column of this table" aria-label="Edit Column Header">
                            <i class="fas fa-heading"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-danger" id="e1dDelCol" title="Delete Column: delete one item-specific column of the open category tab, here and on the live eBay 1 listings" aria-label="Delete Column" disabled>
                            <i class="fas fa-trash-alt"></i>
                        </button>
                        <div class="e1g-wrap" id="e1gWrap">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="e1gBtn" title="Column headers: show the columns of a header (Basics, Common, Others and your own)" aria-label="Column headers">
                                <i class="fas fa-columns"></i>
                            </button>
                            <div class="e1g-panel" id="e1gPanel" hidden>
                                <input type="search" id="e1gSearch" placeholder="Search headers or columns…" autocomplete="off">
                                <div id="e1gBody"></div>
                            </div>
                        </div>
                        <div class="e1d-badges" id="e1dBadges"></div>
                    </div>

                    <div class="e1d-job" id="e1dJob" hidden>
                        <div class="e1d-job-head">
                            <span class="e1d-job-icon" id="e1dJobIcon"></span>
                            <strong id="e1dJobTitle"></strong>
                            <span class="e1d-job-counts"><span class="e1d-ok" id="e1dJobOk"></span> <span class="e1d-bad" id="e1dJobBad"></span></span>
                            <button type="button" class="e1d-job-x" id="e1dJobClose" title="Dismiss" aria-label="Dismiss"><i class="fas fa-times"></i></button>
                        </div>
                        <div class="e1d-job-bar"><span class="e1d-job-ok" id="e1dJobBarOk"></span><span class="e1d-job-bad" id="e1dJobBarBad"></span></div>
                        <div class="e1d-job-note" id="e1dJobNote"></div>
                    </div>

                    <div class="e1d-tabs" id="e1dTabs" role="tablist" aria-label="eBay categories"></div>

                    <div id="ebay1-data-table"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="e1d-modal" id="e1dAddModal" hidden>
        <div class="e1d-modal-box" role="dialog" aria-modal="true" aria-labelledby="e1dAddTitle">
            <h5 id="e1dAddTitle" class="mb-2"><i class="fas fa-plus-circle text-success"></i> Add Column</h5>
            <p class="small text-muted mb-2" id="e1dAddIntro"></p>
            <label class="form-label small fw-bold" for="e1dAddInput">Search a column, or type a new name</label>
            <div class="e1d-chips" id="e1dAddChips"></div>
            <input type="text" id="e1dAddInput" class="form-control form-control-sm mb-1" placeholder="e.g. Impedance" autocomplete="off">
            <div class="e1d-addlist" id="e1dAddList" role="listbox"></div>
            <div class="small my-2" id="e1dAddHint"></div>
            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-sm btn-light" id="e1dAddCancel">Cancel</button>
                <button type="button" class="btn btn-sm btn-success" id="e1dAddGo" disabled>Add column</button>
            </div>
        </div>
    </div>

    <div class="e1d-modal" id="e1dSrcModal" hidden>
        <div class="e1d-modal-box" role="dialog" aria-modal="true" aria-labelledby="e1dSrcTitle">
            <h5 id="e1dSrcTitle" class="mb-2"><i class="fas fa-link text-info"></i> Source Data</h5>
            <p class="small text-muted mb-2" id="e1dSrcIntro"></p>
            <label class="form-label small fw-bold" for="e1dSrcCol">Column of this tab</label>
            <select id="e1dSrcCol" class="form-select form-select-sm mb-2"></select>
            <label class="form-label small fw-bold" for="e1dSrcInput">Technical Specifications column to read from</label>
            <input type="text" id="e1dSrcInput" class="form-control form-control-sm mb-1" placeholder="Search a column…" autocomplete="off">
            <div class="e1d-addlist" id="e1dSrcList" role="listbox"></div>
            <div class="small my-2" id="e1dSrcHint"></div>
            <div class="d-flex justify-content-between gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="e1dSrcClear" title="Stop syncing this column">Remove source</button>
                <span class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-light" id="e1dSrcCancel">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" id="e1dSrcSave" disabled>Save</button>
                </span>
            </div>
        </div>
    </div>

    <div class="e1d-modal" id="e1dEditModal" hidden>
        <div class="e1d-modal-box" role="dialog" aria-modal="true" aria-labelledby="e1dEditTitle">
            <h5 id="e1dEditTitle" class="mb-2"><i class="fas fa-edit text-warning"></i> Edit Column</h5>
            <p class="small text-muted mb-2" id="e1dEditIntro"></p>
            <div id="e1dEditSkus"></div>
            <label class="form-label small fw-bold" for="e1dEditSearch">1. Search and tick the columns to edit</label>
            <input type="text" id="e1dEditSearch" class="form-control form-control-sm mb-1" placeholder="Quick search a column…" autocomplete="off">
            <label class="e1d-addopt fw-bold border-bottom mb-1"><input type="checkbox" id="e1dEditAll"> Columns shown (tick one or more)</label>
            <div class="e1d-addlist" id="e1dEditList" role="listbox"></div>
            <div class="small my-2 fw-bold" id="e1dEditPicked"></div>
            <label class="form-label small fw-bold">2. New data for each ticked column</label>
            <div class="e1d-editfields" id="e1dEditFields"></div>
            <div class="small my-2" id="e1dEditHint"></div>
            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-sm btn-light" id="e1dEditCancel">Cancel</button>
                <button type="button" class="btn btn-sm btn-warning" id="e1dEditGo" disabled>Apply</button>
            </div>
        </div>
    </div>

    <div class="e1d-modal" id="e1dHdrModal" hidden>
        <div class="e1d-modal-box" role="dialog" aria-modal="true" aria-labelledby="e1dHdrTitle">
            <h5 id="e1dHdrTitle" class="mb-2"><i class="fas fa-heading text-secondary"></i> Edit Column Header</h5>
            <p class="small text-muted mb-2" id="e1dHdrIntro"></p>
            <input type="text" id="e1dHdrSearch" class="form-control form-control-sm mb-2" placeholder="Quick search a column…" autocomplete="off">
            <div class="e1d-addlist mb-2" id="e1dHdrList"></div>
            <div class="small text-muted mb-2">Only the name shown in the table header changes. Item specific names on eBay and the data stay as they are. Saved in this browser.</div>
            <div class="d-flex justify-content-between gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="e1dHdrReset" title="Put every header back to its original name">Reset all</button>
                <span class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-light" id="e1dHdrCancel">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" id="e1dHdrGo">Save</button>
                </span>
            </div>
        </div>
    </div>

    <div class="e1d-modal" id="e1dDelModal" hidden>
        <div class="e1d-modal-box" role="dialog" aria-modal="true" aria-labelledby="e1dDelTitle">
            <h5 id="e1dDelTitle" class="mb-2"><i class="fas fa-trash-alt text-danger"></i> Delete Column</h5>
            <p class="small text-muted mb-2" id="e1dDelIntro"></p>
            <input type="text" id="e1dDelSearch" class="form-control form-control-sm mb-1" placeholder="Quick search a column…" autocomplete="off">
            <label class="e1d-addopt fw-bold border-bottom mb-1"><input type="checkbox" id="e1dDelAll"> Columns of this tab (tick one or more)</label>
            <div class="e1d-addlist mb-2" id="e1dDelList"></div>
            <div class="alert alert-danger small py-2 mb-2" id="e1dDelWarn"></div>
            <div class="small mb-2" id="e1dDelProgress"></div>
            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-sm btn-light" id="e1dDelCancel">Cancel</button>
                <button type="button" class="btn btn-sm btn-danger" id="e1dDelGo">Delete column</button>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        (function () {
            'use strict';

            const ALL = '__all__';
            const NO_CATEGORY = 'No category';
            let table = null;
            let tableReady = false;
            let rows = [];
            let activeTab = ALL;
            let searchTimer = null;

            function esc(value) {
                return String(value == null ? '' : value)
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
            }

            function catKey(row) {
                return String(row.category || '').trim() || NO_CATEGORY;
            }

            // Tab label: the last part of the category path; the whole path is the tooltip.
            function catLabel(key) {
                if (key === NO_CATEGORY) return key;
                const parts = key.split(/\s*(?:>|:|\/)\s*/).filter(Boolean);
                return parts.length ? parts[parts.length - 1] : key;
            }

            function isParentRow(row) {
                const sku = String(row.sku || '').trim();
                return /parent/i.test(sku) || (String(row.parent || '').trim() !== '' && sku.toLowerCase() === String(row.parent).trim().toLowerCase());
            }

            function inStock(row) {
                return Number(row.inv) > 0;
            }

            function isMissing(row) {
                return inStock(row) && row.nr_req !== 'NR' && row.listed !== 'Listed' && row.listed !== 'Created';
            }

            function passesControls(row) {
                const q = String(document.getElementById('e1dSearch').value || '').trim().toLowerCase();
                if (q) {
                    const hay = (row.sku + ' ' + row.parent + ' ' + row.ebay_title + ' ' + row.item_id).toLowerCase();
                    if (hay.indexOf(q) === -1) return false;
                }
                const listed = document.getElementById('e1dListed').value;
                if (listed === 'Listed' && row.listed !== 'Listed') return false;
                if (listed === 'Created' && row.listed !== 'Created') return false;
                if (listed === 'Missing' && !isMissing(row)) return false;
                const nr = document.getElementById('e1dNr').value;
                if (nr === 'REQ' && row.nr_req === 'NR') return false;
                if (nr === 'NR' && row.nr_req !== 'NR') return false;
                const stock = document.getElementById('e1dStock').value;
                if (stock === 'in' && !inStock(row)) return false;
                if (stock === 'out' && inStock(row)) return false;
                return true;
            }

            function passesTab(row) {
                return activeTab === ALL || catKey(row) === activeTab;
            }

            // Tabs are counted with the search and filters applied, so the numbers match what the tab then shows.
            function renderTabs() {
                const counts = new Map();
                let total = 0;
                rows.forEach(function (row) {
                    if (!passesControls(row)) return;
                    total++;
                    const key = catKey(row);
                    counts.set(key, (counts.get(key) || 0) + 1);
                });
                const keys = Array.from(counts.keys()).sort(function (a, b) {
                    if (a === NO_CATEGORY) return 1;
                    if (b === NO_CATEGORY) return -1;
                    return catLabel(a).localeCompare(catLabel(b));
                });
                if (activeTab !== ALL && !counts.has(activeTab)) activeTab = ALL;

                const holder = document.getElementById('e1dTabs');
                holder.innerHTML = '';
                function addTab(key, label, title, count) {
                    const tab = document.createElement('button');
                    tab.type = 'button';
                    tab.className = 'e1d-tab' + (key === activeTab ? ' active' : '');
                    tab.setAttribute('role', 'tab');
                    tab.title = title;
                    tab.innerHTML = '<span class="e1d-tab-name">' + esc(label) + '</span><span class="e1d-tab-count">' + count + '</span>';
                    tab.addEventListener('click', function () {
                        activeTab = key;
                        renderTabs();
                        syncSpecColumns();
                        applyFilters();
                        updateDelButton();
                        updatePushButton();
                    });
                    holder.appendChild(tab);
                }
                addTab(ALL, 'All', 'All categories', total);
                keys.forEach(function (key) { addTab(key, catLabel(key), key, counts.get(key)); });
            }

            function renderBadges() {
                let req = 0, nrl = 0, listed = 0, missing = 0, shown = 0;
                const selected = table && tableReady ? table.getSelectedRows().length : 0;
                rows.forEach(function (row) {
                    if (!passesTab(row) || !passesControls(row)) return;
                    shown++;
                    if (!inStock(row)) return;
                    if (row.nr_req === 'NR') { nrl++; return; }
                    req++;
                    if (row.listed === 'Created') return;
                    if (row.listed === 'Listed') listed++; else missing++;
                });
                document.getElementById('e1dBadges').innerHTML =
                    '<span class="e1d-badge req" title="In stock, required">REQ ' + req + '</span>' +
                    '<span class="e1d-badge nrl" title="In stock, not required">NRL ' + nrl + '</span>' +
                    '<span class="e1d-badge listed">Listed ' + listed + '</span>' +
                    '<span class="e1d-badge missing">Missing L ' + missing + '</span>' +
                    '<span class="e1d-badge rows">Rows ' + shown + '</span>' +
                    (selected ? '<span class="e1d-badge listed" title="Rows ticked in the first column">Selected ' + selected + '</span>' : '');
            }

            function applyFilters() {
                if (table && tableReady) {
                    table.setFilter(function (row) { return passesTab(row) && passesControls(row); });
                }
                renderBadges();
            }

            // The place the user is working in: page, vertical and horizontal scroll. A save that refreshes the table
            // puts the view back (refreshAll(true)), instead of jumping to the first page and the top left.
            function viewState() {
                if (!table || !tableReady) return null;
                const holder = table.element.querySelector('.tabulator-tableholder');
                let page = false;
                try { page = table.getPage(); } catch (e) {}
                return { top: holder ? holder.scrollTop : 0, left: holder ? holder.scrollLeft : 0, page: page };
            }

            function restoreView(state) {
                if (!state || !table || !tableReady) return;
                const apply = function () {
                    const holder = table.element.querySelector('.tabulator-tableholder');
                    if (holder) {
                        holder.scrollTop = state.top;
                        holder.scrollLeft = state.left;
                    }
                    csPaint();
                };
                cellHold = Date.now() + 800;
                let go = Promise.resolve();
                try {
                    if (state.page && table.getPage() !== state.page) go = Promise.resolve(table.setPage(state.page)).catch(function () {});
                } catch (e) {}
                go.then(function () {
                    apply();
                    requestAnimationFrame(apply);
                    setTimeout(apply, 120);
                });
            }

            function refreshAll(keepView) {
                const view = keepView === true ? viewState() : null;
                renderTabs();
                syncSpecColumns();
                applyFilters();
                updateDelButton();
                updatePushButton();
                updateMissingBadges();
                if (view) restoreView(view);
            }

            // Red count on every header: how many rows of the open tab have no data in that column, missing listings included.
            const E1_NO_MISS = new Set(['_edit', '_copy', 'nr_req', 'listed']);

            function isMissingValue(value) {
                if (value === null || value === undefined) return true;
                if (Array.isArray(value)) return value.length === 0;
                const text = String(value).trim();
                return text === '' || text === '—';
            }

            function updateMissingBadges() {
                if (!table || !tableReady) return;
                // Every row of the open tab counts, listed or not: search, listing-state, REQ/NRL and stock filters
                // do not hide the rows of missing listings from the count.
                const data = rows.filter(passesTab);
                table.getColumns().forEach(function (col) {
                    const field = col.getField();
                    const el = col.getElement();
                    if (!el) return;
                    let badge = el.querySelector(':scope > .e1d-miss');
                    if (!field || E1_NO_MISS.has(field)) {
                        if (badge) badge.remove();
                        return;
                    }
                    let missing = 0;
                    for (let i = 0; i < data.length; i++) {
                        if (isMissingValue(data[i][field])) missing++;
                    }
                    if (!missing) {
                        if (badge) badge.remove();
                        return;
                    }
                    if (!badge) {
                        badge = document.createElement('span');
                        badge.className = 'e1d-miss';
                        el.appendChild(badge);
                    }
                    badge.textContent = missing > 999 ? '999+' : String(missing);
                    badge.title = missing + ' of ' + data.length + ' rows of this tab have no data in this column (missing listings included)';
                });
            }

            // ---- Delete Column: removes one item specific from every SKU of the open tab, here and on eBay 1. ----
            // ---- Add Column: pick a Technical Specifications column, or type a new name, for the open tab. ----
            let columnOptions = null;

            function normName(value) {
                return String(value || '').toLowerCase().replace(/[^a-z0-9]+/g, '');
            }

            async function loadColumnOptions() {
                if (columnOptions) return;
                try {
                    const res = await fetch('/ebay1-data/column-options', { headers: { Accept: 'application/json' } });
                    const json = await res.json();
                    columnOptions = res.ok && Array.isArray(json.columns) ? json.columns : [];
                } catch (e) {
                    columnOptions = [];
                }
                renderAddList();
                renderSrcList();
            }

            // Columns picked so far, in the order they were picked: normalised key => name.
            let addPicked = new Map();

            function cleanName(value) {
                return String(value || '').replace(/\s+/g, ' ').trim();
            }

            function addState() {
                const typed = cleanName(document.getElementById('e1dAddInput').value);
                const key = normName(typed);
                const inTab = new Set(specNamesForTab().map(normName));
                const known = (columnOptions || []).find(function (name) { return normName(name) === key; }) || '';
                return { typed: typed, key: key, inTab: key !== '' && inTab.has(key), known: known, tabKeys: inTab };
            }

            function togglePicked(name) {
                const key = normName(name);
                if (key === '') return;
                if (addPicked.has(key)) addPicked.delete(key); else addPicked.set(key, name);
                renderAddList();
            }

            function renderAddList() {
                const list = document.getElementById('e1dAddList');
                const chips = document.getElementById('e1dAddChips');
                const hint = document.getElementById('e1dAddHint');
                const go = document.getElementById('e1dAddGo');
                const s = addState();
                list.innerHTML = '';
                chips.innerHTML = '';
                addPicked.forEach(function (name, key) {
                    const chip = document.createElement('span');
                    chip.className = 'e1d-chip';
                    chip.appendChild(document.createTextNode(name + ' '));
                    const x = document.createElement('button');
                    x.type = 'button';
                    x.title = 'Remove';
                    x.innerHTML = '&times;';
                    x.addEventListener('click', function () { addPicked.delete(key); renderAddList(); });
                    chip.appendChild(x);
                    chips.appendChild(chip);
                });
                go.textContent = addPicked.size > 1 ? 'Add ' + addPicked.size + ' columns' : 'Add column';
                go.disabled = addPicked.size === 0 || columnOptions === null;
                if (columnOptions === null) {
                    hint.textContent = 'Loading columns…';
                    return;
                }
                columnOptions.filter(function (name) {
                    const k = normName(name);
                    return !s.tabKeys.has(k) && (s.key === '' || k.indexOf(s.key) !== -1);
                }).slice(0, 100).forEach(function (name) {
                    const row = document.createElement('label');
                    row.className = 'e1d-addopt';
                    const cb = document.createElement('input');
                    cb.type = 'checkbox';
                    cb.checked = addPicked.has(normName(name));
                    cb.addEventListener('change', function () { togglePicked(name); });
                    row.appendChild(cb);
                    row.appendChild(document.createTextNode(' ' + name));
                    list.appendChild(row);
                });
                if (s.key !== '' && !s.known && !s.inTab) {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'new';
                    b.textContent = '+ Create new column “' + s.typed + '”';
                    b.addEventListener('click', function () {
                        addPicked.set(s.key, s.typed);
                        document.getElementById('e1dAddInput').value = '';
                        renderAddList();
                        document.getElementById('e1dAddInput').focus();
                    });
                    list.appendChild(b);
                }
                if (s.inTab) hint.textContent = 'This column is already in this tab.';
                else if (addPicked.size) hint.textContent = addPicked.size + ' selected. Tick more, or press Add.';
                else hint.textContent = 'Tick one or more columns, or type a name that is not there to create it.';
            }

            function openAddModal() {
                if (activeTab === ALL) return;
                document.getElementById('e1dAddIntro').textContent = 'Category: ' + catLabel(activeTab);
                const input = document.getElementById('e1dAddInput');
                input.value = '';
                addPicked = new Map();
                document.getElementById('e1dAddModal').hidden = false;
                renderAddList();
                input.focus();
                loadColumnOptions();
            }

            function closeAddModal() {
                document.getElementById('e1dAddModal').hidden = true;
            }

            // Enter: put the typed name on the list of picked columns; Enter on an empty box adds them all.
            function addTypedOrSubmit() {
                const s = addState();
                if (s.key === '' || s.inTab) {
                    if (s.key === '' && addPicked.size) runAddColumn();
                    return;
                }
                addPicked.set(s.key, s.known || s.typed);
                document.getElementById('e1dAddInput').value = '';
                renderAddList();
            }

            async function runAddColumn() {
                if (!addPicked.size || activeTab === ALL) return;
                const names = Array.from(addPicked.values());
                const go = document.getElementById('e1dAddGo');
                go.disabled = true;
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const skus = rows.filter(passesTab).map(function (row) { return row.sku; });
                document.getElementById('e1dAddHint').textContent = 'Adding…';
                try {
                    const res = await fetch('/ebay1-data/add-column', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify({ names: names, category: activeTab === NO_CATEGORY ? '' : activeTab, skus: skus }),
                    });
                    const json = await res.json();
                    if (!res.ok || !json.success) throw new Error(json.message || 'Request failed');
                    columnOptions = null;
                    closeAddModal();
                    const added = json.names || names;
                    document.getElementById('e1dPullStatus').textContent = added.length === 1
                        ? 'Column "' + added[0] + '" added.'
                        : added.length + ' columns added.';
                    await loadData();
                } catch (e) {
                    document.getElementById('e1dAddHint').innerHTML = '<span class="text-danger">' + esc(e.message || 'Failed') + '</span>';
                    go.disabled = false;
                }
            }

            // ---- REQ / NRL: saved to the same data as the Listing eBay page, then the counts are refreshed. ----
            async function saveNrReq(cell) {
                const row = cell.getRow().getData();
                const value = cell.getValue() === 'NR' ? 'NR' : 'REQ';
                const status = document.getElementById('e1dPullStatus');
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                try {
                    const res = await fetch('/ebay1-data/nr-req', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify({ sku: row.sku, nr_req: value }),
                    });
                    const json = await res.json();
                    if (!res.ok || !json.success) throw new Error(json.message || 'Request failed');
                    row.nr_req = json.nr_req;
                    status.textContent = row.sku + ' set to ' + (json.nr_req === 'NR' ? 'NRL' : 'REQ') + '.';
                    refreshAll(true);
                } catch (e) {
                    cell.restoreOldValue();
                    status.textContent = 'Could not save ' + row.sku + ': ' + (e.message || 'failed');
                }
            }

            // ---- Push: ticked rows if any, otherwise every SKU of the open tab, in one go. ----
            function pushScope() {
                const selected = table && tableReady ? table.getSelectedData().filter(passesTab) : [];
                const base = selected.length ? selected : rows.filter(passesTab);
                const targets = base.filter(function (row) {
                    if (!/^\d{9,15}$/.test(String(row.item_id || '').trim())) return false;
                    if (row.title_pending && String(row.ebay_title || '').trim() !== '') return true;
                    if (row.price_pending && Number(row.ebay_price) > 0) return true;
                    const spec = row.specifics && !Array.isArray(row.specifics) ? row.specifics : {};
                    return Object.keys(spec).some(function (name) { return String(spec[name] || '').trim() !== ''; });
                });
                return { selected: selected.length > 0, targets: targets };
            }

            function updatePushButton() {
                const btn = document.getElementById('e1dPush');
                if (!btn || btn.dataset.busy === '1') return;
                const scope = pushScope();
                btn.disabled = scope.targets.length === 0;
                document.getElementById('e1dPushLabel').textContent = String(scope.targets.length);
                btn.setAttribute('aria-label', 'Push to eBay 1');
                btn.title = 'Push to eBay 1 (' + scope.targets.length + (scope.selected ? ' ticked' : '') + '): '
                    + (scope.selected
                        ? 'push the ticked rows to the live eBay 1 listings'
                        : (activeTab === ALL ? 'push every listed SKU of all categories' : 'push the whole open category to the live eBay 1 listings'));
            }

            async function pushToEbay() {
                const btn = document.getElementById('e1dPush');
                const status = document.getElementById('e1dPullStatus');
                const scope = pushScope();
                const todo = scope.targets;
                if (!todo.length) return;
                const where = scope.selected
                    ? todo.length + ' selected SKU' + (todo.length === 1 ? '' : 's')
                    : 'all ' + todo.length + ' SKUs of ' + (activeTab === ALL ? 'every category' : '"' + catLabel(activeTab) + '"');
                if (!window.confirm('Push the item specifics of ' + where + ' to the live eBay 1 listings?\nAn edited eBay title and an edited price are sent too (a pushed price can be replaced later by the saved S PRC sync). Blank values are not sent and values already on eBay are left alone.')) return;
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                btn.dataset.busy = '1';
                btn.disabled = true;
                let pushed = 0, unchanged = 0, skipped = 0, failed = 0;
                const problems = [];
                const size = 5;
                for (let i = 0; i < todo.length; i += size) {
                    const chunk = todo.slice(i, i + size).map(function (row) {
                        const specifics = {};
                        Object.keys(row.specifics || {}).forEach(function (name) {
                            const v = String(row.specifics[name] || '').trim();
                            if (v !== '') specifics[name] = v;
                        });
                        const item = { sku: row.sku, item_id: String(row.item_id || '').trim(), specifics: specifics };
                        if (row.title_pending) item.title = String(row.ebay_title || '').trim();
                        if (row.price_pending && Number(row.ebay_price) > 0) item.price = Number(row.ebay_price);
                        return item;
                    });
                    status.textContent = 'Pushing ' + Math.min(i + size, todo.length) + ' / ' + todo.length + '…';
                    try {
                        const res = await fetch('/ebay1-data/push', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                            body: JSON.stringify({ items: chunk }),
                        });
                        const json = await res.json();
                        if (!res.ok || !json.success) throw new Error(json.message || 'Request failed');
                        (json.results || []).forEach(function (r) {
                            if (r.status === 'failed') { failed++; problems.push(r.sku + ': ' + r.message); }
                            else if (r.status === 'pushed') pushed++;
                            else if (r.status === 'unchanged') unchanged++;
                            else skipped++;
                        });
                    } catch (e) {
                        failed += chunk.length;
                        problems.push(e.message || 'Request failed');
                    }
                }
                btn.dataset.busy = '0';
                status.textContent = 'Pushed ' + pushed + ', unchanged ' + unchanged + (skipped ? ', skipped ' + skipped : '') + (failed ? ', failed ' + failed : '') + '.';
                if (problems.length) window.alert(problems.slice(0, 15).join('\n') + (problems.length > 15 ? '\n…' : ''));
                await loadData();
            }

            // ---- Auto Push: when ON, only the cells that were edited (by hand) or changed by autofill are pushed. ----
            let autoPushOn = false;
            let autoPushBusy = false;
            let autoPushTimer = null;
            const autoSeen = new Set(); // SKUs tried in this page session, so nothing is pushed twice in a loop

            // Remembers on the row that these item specifics were edited and still wait for a push.
            function addPushCol(row, name) {
                const list = Array.isArray(row.push_cols) ? row.push_cols.slice() : [];
                if (!list.some(function (n) { return gKey(n) === gKey(name); })) list.push(name);
                return list;
            }

            function autoItem(row) {
                if (!/^\d{9,15}$/.test(String(row.item_id || '').trim())) return null;
                const spec = row.specifics && !Array.isArray(row.specifics) ? row.specifics : {};
                const specifics = {};
                (row.push_cols || []).forEach(function (name) {
                    const v = String(spec[name] == null ? '' : spec[name]).trim();
                    if (v !== '' && gKey(name) !== 'upc') specifics[name] = v;
                });
                const item = { sku: row.sku, item_id: String(row.item_id).trim(), specifics: specifics };
                if (row.title_pending && String(row.ebay_title || '').trim() !== '') item.title = String(row.ebay_title).trim();
                if (row.price_pending && Number(row.ebay_price) > 0) item.price = Number(row.ebay_price);
                return Object.keys(specifics).length || item.title || item.price ? item : null;
            }

            function autoItems(includeSeen) {
                const out = [];
                rows.forEach(function (row) {
                    if (!includeSeen && autoSeen.has(String(row.sku))) return;
                    const item = autoItem(row);
                    if (item) out.push(item);
                });
                return out;
            }

            function renderAutoButton() {
                const btn = document.getElementById('e1dAuto');
                if (!btn) return;
                btn.classList.toggle('btn-success', autoPushOn);
                btn.classList.toggle('btn-outline-secondary', !autoPushOn);
                btn.setAttribute('aria-pressed', autoPushOn ? 'true' : 'false');
                document.getElementById('e1dAutoState').textContent = autoPushOn ? 'ON' : 'OFF';
            }

            function autoPushSoon() {
                if (!autoPushOn) return;
                clearTimeout(autoPushTimer);
                autoPushTimer = setTimeout(runAutoPush, 1200);
            }

            async function runAutoPush() {
                if (!autoPushOn || autoPushBusy) return;
                const items = autoItems(false);
                if (!items.length) return;
                const status = document.getElementById('e1dPullStatus');
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                autoPushBusy = true;
                let pushed = 0, unchanged = 0, skipped = 0, failed = 0;
                const problems = [];
                const size = 5;
                for (let i = 0; i < items.length && autoPushOn; i += size) {
                    const chunk = items.slice(i, i + size);
                    chunk.forEach(function (item) { autoSeen.add(String(item.sku)); });
                    status.textContent = 'Auto push ' + Math.min(i + size, items.length) + ' / ' + items.length + '…';
                    try {
                        const res = await fetch('/ebay1-data/push', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                            body: JSON.stringify({ items: chunk }),
                        });
                        const json = await res.json();
                        if (!res.ok || !json.success) throw new Error(json.message || 'Request failed');
                        (json.results || []).forEach(function (r) {
                            if (r.status === 'failed') { failed++; problems.push(r.sku + ': ' + r.message); }
                            else if (r.status === 'pushed') pushed++;
                            else if (r.status === 'unchanged') unchanged++;
                            else skipped++;
                        });
                    } catch (e) {
                        failed += chunk.length;
                        problems.push(e.message || 'Request failed');
                    }
                }
                autoPushBusy = false;
                status.textContent = 'Auto push: pushed ' + pushed + ', unchanged ' + unchanged + (skipped ? ', skipped ' + skipped : '') + (failed ? ', failed ' + failed : '') + '.';
                if (failed) rowToast('Auto push: ' + failed + ' failed. ' + problems.slice(0, 3).join(' | '), false);
                else if (pushed) rowToast('Auto push: ' + pushed + ' SKU' + (pushed === 1 ? '' : 's') + ' pushed to eBay 1.', true);
                // Reload so the pushed cells are no longer marked as waiting.
                if (pushed || unchanged) await loadData();
            }

            async function toggleAutoPush() {
                const turnOn = !autoPushOn;
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                if (turnOn) {
                    const waiting = autoItems(true).length;
                    if (!window.confirm('Turn Auto Push ON?\n' + (waiting
                        ? waiting + ' SKU' + (waiting === 1 ? ' has' : 's have') + ' edited or autofilled cells that are not on eBay 1 yet. Only those cells are pushed to the live listings now, and every later edit is pushed by itself.'
                        : 'Nothing is waiting now. From now on, every cell you edit or autofill changes is pushed to the live eBay 1 listings by itself.'))) return;
                }
                const btn = document.getElementById('e1dAuto');
                btn.disabled = true;
                try {
                    const res = await fetch('/ebay1-data/auto-push', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify({ enabled: turnOn }),
                    });
                    let json = {};
                    try { json = await res.json(); } catch (e) {}
                    if (!res.ok || !json.success) throw new Error(json.message || 'Could not save');
                    autoPushOn = !!json.enabled;
                    autoSeen.clear();
                    clearTimeout(autoPushTimer);
                    renderAutoButton();
                    document.getElementById('e1dPullStatus').textContent = autoPushOn ? 'Auto Push is ON.' : 'Auto Push is OFF.';
                    if (autoPushOn) autoPushSoon();
                } catch (e) {
                    rowToast('Auto Push: ' + (e.message || 'could not change'), false);
                } finally {
                    btn.disabled = false;
                }
            }

            // ---- Source Data: a column of this tab always reads from a Technical Specifications column. ----
            function tabCategory() {
                return activeTab === NO_CATEGORY ? '' : activeTab;
            }

            function sourceFor(columnName, category) {
                const cat = String(category == null ? tabCategory() : category).trim().toLowerCase();
                const key = normName(columnName);
                const hit = columnSources.find(function (s) {
                    return String(s.category || '').trim().toLowerCase() === cat && normName(s.column) === key;
                });
                return hit ? hit.source : '';
            }

            function srcState() {
                const typed = String(document.getElementById('e1dSrcInput').value || '').replace(/\s+/g, ' ').trim();
                const key = normName(typed);
                const match = (columnOptions || []).find(function (name) { return normName(name) === key; });
                return { typed: typed, key: key, match: match || '' };
            }

            function renderSrcList() {
                const list = document.getElementById('e1dSrcList');
                const hint = document.getElementById('e1dSrcHint');
                const save = document.getElementById('e1dSrcSave');
                list.innerHTML = '';
                if (columnOptions === null) {
                    hint.textContent = 'Loading columns…';
                    save.disabled = true;
                    return;
                }
                const s = srcState();
                columnOptions.filter(function (name) {
                    return s.key === '' || normName(name).indexOf(s.key) !== -1;
                }).slice(0, 60).forEach(function (name) {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.textContent = name;
                    if (normName(name) === s.key) b.className = 'active';
                    b.addEventListener('click', function () {
                        document.getElementById('e1dSrcInput').value = name;
                        renderSrcList();
                    });
                    list.appendChild(b);
                });
                const col = document.getElementById('e1dSrcCol').value;
                const current = col ? sourceFor(col) : '';
                if (!col) hint.textContent = 'Choose a column of this tab.';
                else if (s.key === '') hint.textContent = current ? 'Currently reads from “' + current + '”.' : 'No source yet. Pick the Technical Specifications column to read from.';
                else if (!s.match) hint.innerHTML = '<span class="text-danger">Pick a column from the list.</span>';
                else hint.innerHTML = 'After saving, <strong>' + esc(col) + '</strong> always shows the current value of <strong>' + esc(s.match) + '</strong> from Technical Specifications.';
                save.disabled = !col || !s.match;
                document.getElementById('e1dSrcClear').disabled = !current;
            }

            function openSrcModal() {
                const names = specNamesForTab();
                if (activeTab === ALL || !names.length) return;
                document.getElementById('e1dSrcIntro').textContent = 'Category: ' + catLabel(activeTab);
                const sel = document.getElementById('e1dSrcCol');
                sel.innerHTML = '';
                names.forEach(function (name) {
                    const opt = document.createElement('option');
                    opt.value = name;
                    const src = sourceFor(name);
                    opt.textContent = src ? name + '  ⇄  ' + src : name;
                    sel.appendChild(opt);
                });
                document.getElementById('e1dSrcInput').value = sourceFor(sel.value);
                document.getElementById('e1dSrcModal').hidden = false;
                renderSrcList();
                document.getElementById('e1dSrcInput').focus();
                loadColumnOptions();
            }

            function closeSrcModal() {
                document.getElementById('e1dSrcModal').hidden = true;
            }

            async function postSource(source) {
                const column = document.getElementById('e1dSrcCol').value;
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const save = document.getElementById('e1dSrcSave');
                const hint = document.getElementById('e1dSrcHint');
                save.disabled = true;
                hint.textContent = 'Saving…';
                try {
                    const res = await fetch('/ebay1-data/save-source', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify({ category: tabCategory(), column: column, source: source }),
                    });
                    const json = await res.json();
                    if (!res.ok || !json.success) throw new Error(json.message || 'Request failed');
                    closeSrcModal();
                    document.getElementById('e1dPullStatus').textContent = json.removed
                        ? 'Source removed from "' + column + '".'
                        : '"' + column + '" now reads from "' + json.source + '".';
                    await loadData();
                } catch (e) {
                    hint.innerHTML = '<span class="text-danger">' + esc(e.message || 'Failed') + '</span>';
                    save.disabled = false;
                }
            }

            function updateDelButton() {
                const addBtn = document.getElementById('e1dAddCol');
                if (addBtn) addBtn.disabled = activeTab === ALL;
                const srcBtn = document.getElementById('e1dSrcBtn');
                if (srcBtn) srcBtn.disabled = activeTab === ALL || specNamesForTab().length === 0;
                const editBtn = document.getElementById('e1dEditCol');
                if (editBtn) editBtn.disabled = activeTab === ALL || editableColumnNames().length === 0;
                const btn = document.getElementById('e1dDelCol');
                if (!btn) return;
                btn.disabled = false;
                btn.title = 'Hide a page column such as L7, or delete an item-specific column here and on the live eBay 1 listings';
            }

            // Columns ticked in the Delete Column window.
            let delPicked = new Set();
            // True while a delete keeps running. The window can be closed meanwhile; progress then shows in the toolbar.
            let delRunning = false;

            function delTargets(names) {
                const list = Array.isArray(names) ? names : [names];
                return rows.filter(function (row) {
                    return passesTab(row)
                        && row.specifics && !Array.isArray(row.specifics)
                        && list.some(function (name) { return Object.prototype.hasOwnProperty.call(row.specifics, name); });
                });
            }

            function refreshDelWarning() {
                const picked = Array.from(delPicked);
                const pagePicked = picked.filter(pageColumnByName);
                const specPicked = picked.filter(function (name) { return !pageColumnByName(name); });
                const parts = [];
                if (!picked.length) parts.push('Tick one or more columns.');
                if (pagePicked.length) {
                    const hiding = pagePicked.filter(function (name) { return !hiddenFields.has(pageColumnByName(name).field); });
                    const showing = pagePicked.filter(function (name) { return hiddenFields.has(pageColumnByName(name).field); });
                    if (hiding.length) parts.push('Hides <strong>' + esc(hiding.join(', ')) + '</strong> on this page only. Nothing is sent to eBay. Search the name here again to show it.');
                    if (showing.length) parts.push('Shows <strong>' + esc(showing.join(', ')) + '</strong> on this page again.');
                }
                if (specPicked.length) {
                    const n = delTargets(specPicked).length;
                    const label = specPicked.length === 1 ? '<strong>' + esc(specPicked[0]) + '</strong>' : '<strong>' + specPicked.length + ' columns</strong>';
                    parts.push(n === 0
                        ? 'No SKU in this category has ' + label + '. ' + (specPicked.length === 1 ? 'The column is' : 'The columns are') + ' only removed from this tab.'
                        : 'This removes ' + label + ' from <strong>' + n + '</strong> SKU' + (n === 1 ? '' : 's') + ' in this category: on the live eBay 1 listing where there is one, and always in the app. It cannot be undone here.');
                }
                document.getElementById('e1dDelWarn').innerHTML = parts.join(' ');
                const go = document.getElementById('e1dDelGo');
                go.disabled = !picked.length || delRunning;
                if (!specPicked.length && pagePicked.length) {
                    const allHidden = pagePicked.every(function (name) { return hiddenFields.has(pageColumnByName(name).field); });
                    go.textContent = allHidden
                        ? (pagePicked.length > 1 ? 'Show ' + pagePicked.length + ' columns' : 'Show column')
                        : (pagePicked.length > 1 ? 'Hide ' + pagePicked.length + ' columns' : 'Hide column');
                } else if (pagePicked.length) {
                    go.textContent = 'Apply';
                } else {
                    go.textContent = specPicked.length > 1 ? 'Delete ' + specPicked.length + ' columns' : 'Delete column';
                }
                const all = document.getElementById('e1dDelAll');
                const visible = delVisibleNames();
                const ticked = visible.filter(function (name) { return delPicked.has(name); }).length;
                all.checked = visible.length > 0 && ticked === visible.length;
                all.indeterminate = ticked > 0 && ticked < visible.length;
            }

            // Page columns such as L7, Price and Views. Hiding one removes it from the table only.
            const E1_HIDE_KEY = 'ebay1_data_hidden_fields_v1';
            let hiddenFields = new Set();
            try { hiddenFields = new Set(JSON.parse(localStorage.getItem(E1_HIDE_KEY) || '[]')); } catch (e) { hiddenFields = new Set(); }

            function pageColumns() {
                return baseColumns().filter(function (col) {
                    return col.field && col.title && col.field !== '_edit' && col.field !== '_copy';
                }).map(function (col) { return { name: String(col.title), field: col.field }; });
            }

            function pageColumnByName(name) {
                const key = gKey(name);
                return pageColumns().find(function (col) { return gKey(col.name) === key; }) || null;
            }

            function saveHiddenFields() {
                try { localStorage.setItem(E1_HIDE_KEY, JSON.stringify(Array.from(hiddenFields))); } catch (e) {}
            }

            // Item specifics of the tab, plus every page column, so L7 and the rest can be found.
            function allDeleteNames() {
                const names = pageColumns().map(function (col) { return col.name; });
                const seen = new Set(names.map(gKey));
                specNamesForTab().forEach(function (name) {
                    if (!seen.has(gKey(name))) {
                        seen.add(gKey(name));
                        names.push(name);
                    }
                });
                return names;
            }

            // Columns of the open tab, A to Z, narrowed by the quick search.
            function delVisibleNames() {
                const q = String(document.getElementById('e1dDelSearch').value || '').trim().toLowerCase();
                return allDeleteNames().slice()
                    .sort(function (a, b) { return a.localeCompare(b, undefined, { sensitivity: 'base' }); })
                    .filter(function (name) { return !q || name.toLowerCase().indexOf(q) !== -1; });
            }

            function renderDelList() {
                const names = allDeleteNames();
                delPicked = new Set(Array.from(delPicked).filter(function (name) { return names.some(function (n) { return gKey(n) === gKey(name); }); }));
                const list = document.getElementById('e1dDelList');
                const delTotal = rows.filter(passesTab).length;
                list.innerHTML = '';
                const shown = delVisibleNames();
                if (!shown.length) list.innerHTML = '<div class="small text-muted p-2">No matching column.</div>';
                shown.forEach(function (name) {
                    const row = document.createElement('label');
                    row.className = 'e1d-addopt';
                    const cb = document.createElement('input');
                    cb.type = 'checkbox';
                    cb.checked = delPicked.has(name);
                    cb.addEventListener('change', function () {
                        if (cb.checked) delPicked.add(name); else delPicked.delete(name);
                        refreshDelWarning();
                    });
                    row.appendChild(cb);
                    row.appendChild(document.createTextNode(' ' + name + ' '));
                    const pageCol = pageColumnByName(name);
                    const count = document.createElement('span');
                    if (pageCol) {
                        const hidden = hiddenFields.has(pageCol.field);
                        count.className = 'e1d-cnt ' + (hidden ? 'red' : 'green');
                        count.textContent = hidden ? 'hidden' : 'page';
                        count.title = hidden
                            ? 'Hidden on this page. Tick it and press the button to show it again.'
                            : 'A column of this page. Hiding it does not change eBay.';
                    } else {
                        const have = delTargets(name).length;
                        count.className = 'e1d-cnt ' + (have === delTotal ? 'green' : 'red');
                        count.textContent = have + '/' + delTotal;
                        count.title = have + ' of ' + delTotal + ' SKUs in this category have this column';
                    }
                    row.appendChild(count);
                    list.appendChild(row);
                });
                refreshDelWarning();
            }

            function openDelModal(keepPicked) {
                if (!allDeleteNames().length) return;
                if (keepPicked !== true && !delRunning) delPicked = new Set();
                document.getElementById('e1dDelSearch').value = '';
                document.getElementById('e1dDelIntro').textContent = 'Category: ' + catLabel(activeTab);
                if (!delRunning) document.getElementById('e1dDelProgress').textContent = '';
                document.getElementById('e1dDelModal').hidden = false;
                renderDelList();
            }

            function closeDelModal() {
                document.getElementById('e1dDelModal').hidden = true;
            }

            // Progress shown outside the side panel: bar and counts, green for done and red for failed.
            function jobShow(title, total, done, failed, running, problems) {
                const box = document.getElementById('e1dJob');
                box.hidden = false;
                document.getElementById('e1dJobTitle').textContent = title;
                document.getElementById('e1dJobIcon').innerHTML = running
                    ? '<i class="fas fa-spinner fa-spin"></i>'
                    : (failed ? '<i class="fas fa-exclamation-circle e1d-bad"></i>' : '<i class="fas fa-check-circle e1d-ok"></i>');
                document.getElementById('e1dJobOk').textContent = done ? '✔ ' + done + ' done' : '';
                document.getElementById('e1dJobBad').textContent = failed ? '✖ ' + failed + ' failed' : '';
                const t = Math.max(total, 1);
                document.getElementById('e1dJobBarOk').style.width = Math.min(100, done / t * 100) + '%';
                document.getElementById('e1dJobBarBad').style.width = Math.min(100, failed / t * 100) + '%';
                const list = problems || [];
                document.getElementById('e1dJobNote').textContent = list.slice(0, 6).join('\n') + (list.length > 6 ? '\n… and ' + (list.length - 6) + ' more' : '');
            }

            async function runDelete() {
                const names = Array.from(delPicked);
                if (!names.length || delRunning) return;
                const pagePicked = names.filter(pageColumnByName);
                const specPicked = names.filter(function (name) { return !pageColumnByName(name); });
                const pageNote = pagePicked.map(function (name) {
                    return (hiddenFields.has(pageColumnByName(name).field) ? 'Show ' : 'Hide ') + name;
                }).join(', ');
                const todo = specPicked.length ? delTargets(specPicked) : [];
                const label = specPicked.length === 1 ? '"' + specPicked[0] + '"' : specPicked.length + ' columns';
                const question = (pageNote ? pageNote + ' on this page?' : '')
                    + (specPicked.length
                        ? (pageNote ? '\n\n' : '') + (todo.length
                            ? 'Delete ' + label + ' from ' + todo.length + ' SKUs (live eBay 1 listing where there is one, and the app)?'
                            : 'Remove ' + label + ' from this tab?')
                        : '');
                if (!window.confirm(question)) return;
                pagePicked.forEach(function (name) {
                    const field = pageColumnByName(name).field;
                    if (hiddenFields.has(field)) hiddenFields.delete(field);
                    else hiddenFields.add(field);
                });
                if (pagePicked.length) {
                    saveHiddenFields();
                    applyColumnGroupVisibility();
                }
                if (!specPicked.length) {
                    closeDelModal();
                    return;
                }
                const namesForEbay = specPicked;
                const category = activeTab === NO_CATEGORY ? '' : activeTab;
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const goBtn = document.getElementById('e1dDelGo');
                const progress = document.getElementById('e1dDelProgress');
                goBtn.disabled = true;
                delRunning = true;
                const modalOpen = function () { return !document.getElementById('e1dDelModal').hidden; };
                let done = 0, failed = 0;
                const problems = [];
                const size = 5;
                // With no listing to change, one call still drops the columns of this tab.
                for (let i = 0; i < Math.max(todo.length, 1); i += size) {
                    const chunk = todo.slice(i, i + size).map(function (row) { return { sku: row.sku, item_id: String(row.item_id).trim() }; });
                    progress.textContent = 'Deleting ' + Math.min(i + size, todo.length) + ' / ' + todo.length + '… (you can close this window, it keeps running)';
                    jobShow('Deleting ' + label + '…', todo.length, done, failed, true, problems);
                    try {
                        const res = await fetch('/ebay1-data/delete-specific', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                            body: JSON.stringify({ names: namesForEbay, category: category, items: chunk }),
                        });
                        const json = await res.json();
                        if (!res.ok || !json.success) throw new Error(json.message || 'Request failed');
                        (json.results || []).forEach(function (r) {
                            if (r.status === 'failed') { failed++; problems.push(r.sku + ': ' + r.message); } else { done++; }
                        });
                    } catch (e) {
                        failed += chunk.length;
                        problems.push(e.message || 'Request failed');
                    }
                }
                const summary = 'Deleted ' + done + (failed ? ', failed ' + failed : '') + '.';
                progress.textContent = summary;
                await loadData();
                delRunning = false;
                jobShow('Delete finished: ' + label, todo.length, done, failed, false, problems);
                if (failed) {
                    if (modalOpen()) {
                        openDelModal(true);
                        document.getElementById('e1dDelProgress').textContent = 'Deleted ' + done + ', failed ' + failed + '. Delete again to retry the failed SKUs.';
                    }
                } else if (modalOpen()) {
                    closeDelModal();
                }
            }

            function numberFormatter(decimals) {
                return function (cell) {
                    const v = cell.getValue();
                    if (v === null || v === undefined || v === '') return '<span class="e1d-muted">—</span>';
                    return Number(v).toLocaleString(undefined, { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
                };
            }

            // Copy icon of a cell: copies the full cell value (clipboard API, with a fallback for plain http).
            function copyCellClick(e, cell) {
                const target = e.target.closest ? e.target.closest('.e1d-copy') : null;
                if (!target) return;
                e.stopPropagation();
                const text = String(cell.getValue() || '').trim();
                const icon = target.querySelector('i');
                const done = function (ok) {
                    if (icon) icon.className = ok ? 'fas fa-check' : 'fas fa-times';
                    setTimeout(function () { if (icon) icon.className = 'far fa-copy'; }, 1200);
                };
                const fallback = function () {
                    const ta = document.createElement('textarea');
                    ta.value = text;
                    ta.style.position = 'fixed';
                    ta.style.opacity = '0';
                    document.body.appendChild(ta);
                    ta.select();
                    let ok = false;
                    try { ok = document.execCommand('copy'); } catch (err) { ok = false; }
                    document.body.removeChild(ta);
                    done(ok);
                };
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(function () { done(true); }, fallback);
                } else {
                    fallback();
                }
            }

            function textFormatter(cell) {
                const v = String(cell.getValue() || '').trim();
                return v ? esc(v) : '<span class="e1d-muted">—</span>';
            }

            // Item specifics pulled from eBay become columns of the active category tab (none on the All tab).
            let specSignature = '';
            let columnSources = [];

            function specKey(name) {
                return 'sp_' + String(name).toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
            }

            function flattenSpecifics(list) {
                list.forEach(function (row) {
                    const spec = row.specifics && !Array.isArray(row.specifics) ? row.specifics : {};
                    Object.keys(spec).forEach(function (name) { row[specKey(name)] = spec[name]; });
                });
            }

            function specNamesForTab() {
                if (activeTab === ALL) return [];
                const counts = new Map();
                rows.forEach(function (row) {
                    if (!passesTab(row) || !row.specifics || Array.isArray(row.specifics)) return;
                    Object.keys(row.specifics).forEach(function (name) { counts.set(name, (counts.get(name) || 0) + 1); });
                });
                return Array.from(counts.keys()).sort(function (a, b) {
                    return (counts.get(b) - counts.get(a)) || a.localeCompare(b);
                });
            }

            // ---- Edit Column: one value for a whole column, or for the ticked rows only ----
            const editPicked = new Map();
            let editBusy = false;
            let editRow = null; // set when the pen button of one row opened the window

            // Every item-specific column of the open tab, including Brand, Country of Origin, UPC and sourced columns.
            function editableColumnNames() {
                return specNamesForTab();
            }

            // eBay title and Price are page columns, not item specifics, so they are added beside the specifics.
            function editNames() {
                const names = ['eBay title', 'Price'];
                editableColumnNames().forEach(function (name) {
                    if (gKey(name) !== 'ebaytitle' && gKey(name) !== 'price') names.push(name);
                });
                return names;
            }

            // What a saved title or price changes on a row. Item specifics return null.
            function pagePatch(name, value) {
                const k = gKey(name);
                if (k === 'ebaytitle') return { ebay_title: value, title_pending: true };
                if (k === 'price') {
                    const n = parseFloat(String(value).replace(/[,$\s]/g, ''));
                    return isNaN(n) ? null : { ebay_price: n, price_pending: true };
                }
                return null;
            }

            function pageValue(row, name) {
                const k = gKey(name);
                if (k === 'ebaytitle') return String(row.ebay_title || '');
                if (k === 'price') return row.ebay_price == null || row.ebay_price === '' ? '' : String(row.ebay_price);
                return null;
            }

            function renderEditSkus() {
                const box = document.getElementById('e1dEditSkus');
                const scope = editScope();
                const skus = scope.targets.map(function (row) { return row.sku; }).filter(Boolean);
                const head = scope.single ? 'SKU' : (scope.selected ? 'Selected SKUs' : 'SKUs in this tab');
                box.innerHTML = '<div class="e1d-sku-label">' + esc(head) + ' (' + skus.length + ')</div>'
                    + '<div class="e1d-sku-list">' + skus.map(function (sku) { return '<span>' + esc(sku) + '</span>'; }).join('') + '</div>';
            }

            function editScope() {
                if (editRow) return { selected: false, single: true, targets: [editRow] };
                const selected = table && tableReady ? table.getSelectedData().filter(passesTab) : [];
                const base = selected.length ? selected : (table && tableReady ? table.getData('active').filter(passesTab) : []);
                return { selected: selected.length > 0, targets: base };
            }

            // Columns ticked in the window, each with the data typed for it (name -> value) and its suggestions.
            function visibleEditNames() {
                const q = String(document.getElementById('e1dEditSearch').value || '').trim().toLowerCase();
                return editNames()
                    .slice()
                    .sort(function (a, b) { return a.localeCompare(b, undefined, { sensitivity: 'base' }); })
                    .filter(function (n) { return !q || n.toLowerCase().indexOf(q) !== -1; });
            }

            function editSuggestions(name) {
                const values = new Map();
                const sample = editRow ? rows.filter(function (r) { return (r.category || '') === (editRow.category || ''); }) : editScope().targets;
                if (pageValue({}, name) !== null) {
                    sample.forEach(function (row) {
                        const v = String(pageValue(row, name) || '').trim();
                        if (v !== '') values.set(v, (values.get(v) || 0) + 1);
                    });
                    return Array.from(values.keys()).sort(function (a, b) { return values.get(b) - values.get(a); }).slice(0, 40);
                }
                sample.forEach(function (row) {
                    const spec = row.specifics && !Array.isArray(row.specifics) ? row.specifics : {};
                    Object.keys(spec).forEach(function (k) {
                        const v = String(spec[k] || '').trim();
                        if (gKey(k) === gKey(name) && v !== '') values.set(v, (values.get(v) || 0) + 1);
                    });
                });
                return Array.from(values.keys()).sort(function (a, b) { return values.get(b) - values.get(a); }).slice(0, 40);
            }

            function renderEditFields() {
                const box = document.getElementById('e1dEditFields');
                const names = Array.from(editPicked.keys());
                box.innerHTML = '';
                names.forEach(function (name, i) {
                    const wrap = document.createElement('div');
                    wrap.className = 'e1d-editfield';
                    const label = document.createElement('label');
                    label.textContent = name;
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.className = 'form-control form-control-sm';
                    input.autocomplete = 'off';
                    input.placeholder = gKey(name) === 'ebaytitle' ? 'eBay title, 80 characters' : (gKey(name) === 'price' ? 'Price, for example 24.99' : 'Type the data for ' + name);
                    if (gKey(name) === 'ebaytitle') input.maxLength = 80;
                    input.value = editPicked.get(name) || '';
                    input.disabled = editBusy;
                    const listId = 'e1dEditDl' + i;
                    input.setAttribute('list', listId);
                    const dl = document.createElement('datalist');
                    dl.id = listId;
                    dl.innerHTML = editSuggestions(name).map(function (v) { return '<option value="' + esc(v) + '"></option>'; }).join('');
                    input.addEventListener('input', function () { editPicked.set(name, input.value); });
                    input.addEventListener('keydown', function (e) {
                        if (e.key === 'Enter') { e.preventDefault(); runEditColumn(); }
                    });
                    wrap.appendChild(label);
                    wrap.appendChild(input);
                    wrap.appendChild(dl);
                    box.appendChild(wrap);
                });
            }

            function refreshEditState() {
                const scope = editScope();
                const n = editPicked.size;
                document.getElementById('e1dEditPicked').textContent = n ? n + ' column' + (n === 1 ? '' : 's') + ' ticked' : 'No column ticked yet.';
                document.getElementById('e1dEditHint').textContent = n
                    ? (scope.single
                        ? 'Changes only ' + editRow.sku + '.'
                        : 'Changes ' + scope.targets.length + (scope.selected ? ' ticked' : '') + ' row' + (scope.targets.length === 1 ? '' : 's') + '.')
                        + ' Nothing is sent to eBay until you press Push.'
                    : '';
                document.getElementById('e1dEditGo').disabled = !n || editBusy || scope.targets.length === 0;
                const visible = visibleEditNames();
                const ticked = visible.filter(function (name) { return editPicked.has(name); }).length;
                const all = document.getElementById('e1dEditAll');
                all.checked = visible.length > 0 && ticked === visible.length;
                all.indeterminate = ticked > 0 && ticked < visible.length;
            }

            // A column's starting value: a single row starts with its own current value.
            function startingEditValue(name) {
                if (!editRow) return '';
                if (pageValue(editRow, name) !== null) return pageValue(editRow, name);
                const spec = editRow.specifics && !Array.isArray(editRow.specifics) ? editRow.specifics : {};
                const own = Object.keys(spec).find(function (k) { return gKey(k) === gKey(name); });
                return own ? String(spec[own] || '') : '';
            }

            function toggleEditColumn(name, on) {
                if (on) {
                    if (!editPicked.has(name)) editPicked.set(name, startingEditValue(name));
                } else {
                    editPicked.delete(name);
                }
                renderEditFields();
                refreshEditState();
            }

            function renderEditList() {
                const list = document.getElementById('e1dEditList');
                const names = visibleEditNames();
                list.innerHTML = '';
                if (!names.length) list.innerHTML = '<div class="small text-muted p-2">No matching column.</div>';
                names.forEach(function (name) {
                    const row = document.createElement('label');
                    row.className = 'e1d-addopt';
                    const cb = document.createElement('input');
                    cb.type = 'checkbox';
                    cb.checked = editPicked.has(name);
                    cb.disabled = editBusy;
                    cb.addEventListener('change', function () { toggleEditColumn(name, cb.checked); });
                    row.appendChild(cb);
                    row.appendChild(document.createTextNode(' ' + name));
                    list.appendChild(row);
                });
                refreshEditState();
            }

            // Without a row: the whole open tab (or the ticked rows). With a row (pen button): that row only.
            function openEditModal(row) {
                editRow = row && row.sku ? row : null;
                const scope = editScope();
                if (!scope.targets.length) {
                    editRow = null;
                    window.alert('There are no rows in this tab to edit.');
                    return;
                }
                if (!editNames().length) {
                    editRow = null;
                    window.alert('There are no columns to edit on this tab.');
                    return;
                }
                editPicked.clear();
                document.getElementById('e1dEditSearch').value = '';
                document.getElementById('e1dEditIntro').textContent = scope.single
                    ? 'Editing this SKU only.'
                    : (scope.selected
                        ? 'Only the ticked rows will change.'
                        : 'Every row shown in this tab will change. Tick rows first to change only those.');
                document.getElementById('e1dEditModal').hidden = false;
                renderEditSkus();
                renderEditFields();
                renderEditList();
                document.getElementById('e1dEditSearch').focus();
            }

            function closeEditModal() {
                if (editBusy) return;
                document.getElementById('e1dEditModal').hidden = true;
            }

            async function runEditColumn() {
                if (editBusy || !editPicked.size) return;
                const scope = editScope();
                if (!scope.targets.length) return;
                let edits = Array.from(editPicked.entries()).map(function (e) { return { column: e[0], value: String(e[1] || '').trim() }; });
                const blank = edits.filter(function (e) { return e.value === ''; });
                // A ticked column left empty is skipped, so filling the others does not ask to clear it.
                // The question only comes up when every ticked column is empty, which really is a clear.
                if (blank.length === edits.length) {
                    if (!window.confirm('Clear ' + (blank.length === 1 ? blank[0].column : blank.length + ' columns') + ' on ' + scope.targets.length + ' row' + (scope.targets.length === 1 ? '' : 's') + '?')) return;
                } else {
                    edits = edits.filter(function (e) { return e.value !== ''; });
                }
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const skus = scope.targets.map(function (row) { return row.sku; });
                const go = document.getElementById('e1dEditGo');
                const saved = [];
                editBusy = true;
                renderEditList();
                renderEditFields();
                try {
                    for (let c = 0; c < edits.length; c++) {
                        const edit = edits[c];
                        for (let i = 0; i < skus.length; i += 300) {
                            go.textContent = 'Saving ' + (c + 1) + ' / ' + edits.length + '…';
                            const res = await fetch('/ebay1-data/edit-column', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                                body: JSON.stringify({ column: edit.column, value: edit.value, skus: skus.slice(i, i + 300) }),
                            });
                            let json = {};
                            try { json = await res.json(); } catch (e) {}
                            if (!res.ok || json.success === false) throw new Error(edit.column + ': ' + (json.message || 'Could not save'));
                        }
                        saved.push(edit);
                    }
                } catch (e) {
                    window.alert((saved.length ? 'Saved ' + saved.length + ' of ' + edits.length + ' columns. ' : '') + (e.message || 'Could not save the columns'));
                }
                // Show what was saved, even when a later column failed.
                if (saved.length) {
                    const bySku = new Map();
                    table.getRows().forEach(function (tRow) { bySku.set(String(tRow.getData().sku), tRow); });
                    table.blockRedraw();
                    try {
                        scope.targets.forEach(function (row) {
                            const tRow = bySku.get(String(row.sku));
                            const data = tRow ? tRow.getData() : row;
                            const spec = data.specifics && !Array.isArray(data.specifics) ? data.specifics : {};
                            const patch = {};
                            saved.forEach(function (edit) {
                                const page = pagePatch(edit.column, edit.value);
                                if (page) {
                                    Object.assign(patch, page);
                                    return;
                                }
                                const existing = Object.keys(spec).find(function (k) { return gKey(k) === gKey(edit.column); });
                                spec[existing || edit.column] = edit.value;
                                patch[specKey(edit.column)] = edit.value;
                                patch.push_cols = addPushCol({ push_cols: patch.push_cols || data.push_cols }, existing || edit.column);
                            });
                            patch.specifics = spec;
                            const source = rows.find(function (r) { return String(r.sku) === String(row.sku); });
                            if (source) Object.assign(source, patch);
                            if (tRow) tRow.update(patch);
                            else Object.assign(row, patch);
                            autoSeen.delete(String(row.sku));
                        });
                    } finally {
                        table.restoreRedraw();
                    }
                    autoPushSoon();
                }
                editBusy = false;
                go.textContent = 'Apply';
                if (saved.length === edits.length) {
                    document.getElementById('e1dEditModal').hidden = true;
                    refreshAll(true);
                } else {
                    saved.forEach(function (edit) { editPicked.delete(edit.column); });
                    renderEditList();
                    renderEditFields();
                    if (saved.length) refreshAll(true);
                }
            }

            // ---- Column headers: named headers (Basics, Common, Others, ...) with tick boxes ----
            // Ticking a header or a column shows only the ticked columns (the select box and SKU always stay).
            // A column can sit in several headers: it can be moved to another header or copied to one.
            const E1G_SEL_KEY = 'ebay1_data_group_sel_v1';
            const E1G_DEFAULTS = ['Basics', 'Common', 'Others'];
            let e1gGroups = [];
            let e1gKnown = null;
            const e1gOpen = new Set();
            const e1gSel = (function () {
                try {
                    const parsed = JSON.parse(localStorage.getItem(E1G_SEL_KEY) || '[]');
                    return new Set(Array.isArray(parsed) ? parsed : []);
                } catch (e) {
                    return new Set();
                }
            })();
            let e1gSaving = Promise.resolve();

            function gKey(name) {
                return String(name || '').toLowerCase().replace(/[^a-z0-9]+/g, '');
            }

            function gClean(value) {
                return String(value || '').replace(/\s+/g, ' ').trim();
            }

            function e1gToast(message) {
                window.alert(message);
            }

            function initColumnGroups(saved) {
                const list = Array.isArray(saved) && saved.length ? saved : E1G_DEFAULTS.map(function (n) { return { name: n, columns: [] }; });
                e1gGroups = list.map(function (g) {
                    return { name: String(g.name || ''), columns: Array.isArray(g.columns) ? g.columns.map(String) : [] };
                }).filter(function (g) { return g.name !== ''; });
                e1gKnown = null;
            }

            function persistColumnGroups() {
                const payload = { groups: e1gGroups.map(function (g) { return { name: g.name, columns: g.columns }; }) };
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                e1gSaving = e1gSaving.then(async function () {
                    const res = await fetch('/ebay1-data/column-groups', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify(payload),
                    });
                    let json = {};
                    try { json = await res.json(); } catch (e) {}
                    if (!res.ok || json.success === false) throw new Error(json.message || 'Could not save the headers');
                }).catch(function (e) { e1gToast(e.message || 'Could not save the headers'); });
            }

            // Every column the page can show: the fixed columns and every item specific of any category.
            function e1gKnownColumns() {
                if (e1gKnown) return e1gKnown;
                const map = new Map();
                baseColumns().forEach(function (c) {
                    if (c.title && c.field) map.set(gKey(c.title), c.title);
                });
                const found = new Set();
                rows.forEach(function (row) {
                    if (!row.specifics || Array.isArray(row.specifics)) return;
                    Object.keys(row.specifics).forEach(function (n) { found.add(n); });
                });
                Array.from(found).sort(function (a, b) { return a.localeCompare(b); }).forEach(function (n) {
                    if (!map.has(gKey(n))) map.set(gKey(n), n);
                });
                e1gKnown = map;
                return map;
            }

            function e1gGroupNames(group, map) {
                const out = [];
                const seen = new Set();
                group.columns.forEach(function (c) {
                    const key = gKey(c);
                    const real = map.get(key);
                    if (real && !seen.has(key)) {
                        seen.add(key);
                        out.push(real);
                    }
                });
                return out;
            }

            // Show only the ticked columns that exist in the open tab; nothing ticked (or nothing matching) shows all.
            function applyColumnGroupVisibility() {
                if (!table || !tableReady) return;
                const fieldKey = new Map();
                baseColumns().forEach(function (c) { if (c.field && c.title) fieldKey.set(c.field, gKey(c.title)); });
                specNamesForTab().forEach(function (n) { fieldKey.set(specKey(n), gKey(n)); });
                let matched = 0;
                if (e1gSel.size) fieldKey.forEach(function (k) { if (e1gSel.has(k)) matched++; });
                const filtering = matched > 0;
                table.blockRedraw();
                try {
                    table.getColumns().forEach(function (col) {
                        const field = col.getDefinition().field;
                        if (!field || field === '_edit' || field === '_copy') return;
                        if (hiddenFields.has(field)) {
                            if (col.isVisible()) col.hide();
                            return;
                        }
                        if (field === 'sku') return;
                        const want = !filtering || e1gSel.has(fieldKey.get(field));
                        if (want && !col.isVisible()) col.show();
                        else if (!want && col.isVisible()) col.hide();
                    });
                } finally {
                    table.restoreRedraw();
                }
            }

            function e1gSaveSel() {
                try { localStorage.setItem(E1G_SEL_KEY, JSON.stringify(Array.from(e1gSel))); } catch (e) {}
            }

            function e1gApplySel() {
                e1gSaveSel();
                applyColumnGroupVisibility();
            }

            function renderColumnGroups() {
                const body = document.getElementById('e1gBody');
                if (!body) return;
                const panel = document.getElementById('e1gPanel');
                const scroll = panel.scrollTop;
                const q = String(document.getElementById('e1gSearch').value || '').trim().toLowerCase();
                const map = e1gKnownColumns();
                const allNames = Array.from(map.values());
                let html = '<datalist id="e1gNames">' + allNames.map(function (n) { return '<option value="' + esc(n) + '"></option>'; }).join('') + '</datalist>';
                html += '<div class="e1g-top"><span>' + (e1gSel.size ? e1gSel.size + ' column' + (e1gSel.size === 1 ? '' : 's') + ' ticked' : 'All columns shown') + '</span>'
                    + '<button type="button" class="btn btn-sm btn-outline-secondary py-0" data-act="clear"' + (e1gSel.size ? '' : ' disabled') + '>Show all</button></div>';
                e1gGroups.forEach(function (group, gi) {
                    const names = e1gGroupNames(group, map);
                    const nameHit = q !== '' && group.name.toLowerCase().indexOf(q) !== -1;
                    const hits = q !== '' ? names.filter(function (n) { return n.toLowerCase().indexOf(q) !== -1; }) : names;
                    if (q !== '' && !nameHit && !hits.length) return;
                    const open = q !== '' ? true : e1gOpen.has(group.name);
                    const visibleNames = (q !== '' && !nameHit) ? hits : names;
                    html += '<div class="e1g-group"><div class="e1g-head">'
                        + '<input type="checkbox" class="e1g-gcb" data-g="' + gi + '" title="Show the columns of this header">'
                        + '<span class="e1g-name" data-act="toggle" data-g="' + gi + '">' + esc(group.name) + '</span>'
                        + '<span class="e1g-count">' + names.length + '</span>'
                        + '<button type="button" class="e1g-icon" data-act="toggle" data-g="' + gi + '" title="Open or close">' + (open ? '&#9662;' : '&#9656;') + '</button>'
                        + '<button type="button" class="e1g-icon" data-act="delgroup" data-g="' + gi + '" title="Delete this header (its columns stay)">&times;</button>'
                        + '</div>';
                    if (open) {
                        const others = e1gGroups.map(function (g, i) { return { g: g, i: i }; }).filter(function (o) { return o.i !== gi; });
                        html += '<div class="e1g-body">';
                        visibleNames.forEach(function (name) {
                            const key = gKey(name);
                            html += '<div class="e1g-col"><label class="e1g-label"><input type="checkbox" class="e1g-ccb" data-key="' + esc(key) + '"' + (e1gSel.has(key) ? ' checked' : '') + '> ' + esc(name) + '</label>';
                            if (others.length) {
                                html += '<select data-act="xfer" data-g="' + gi + '" data-name="' + esc(name) + '"><option value="">Move / copy…</option>'
                                    + '<optgroup label="Move to">' + others.map(function (o) { return '<option value="move:' + o.i + '">' + esc(o.g.name) + '</option>'; }).join('') + '</optgroup>'
                                    + '<optgroup label="Copy to">' + others.map(function (o) { return '<option value="copy:' + o.i + '">' + esc(o.g.name) + '</option>'; }).join('') + '</optgroup></select>';
                            }
                            html += '<button type="button" class="e1g-icon" data-act="remove" data-g="' + gi + '" data-name="' + esc(name) + '" title="Remove from this header">&times;</button></div>';
                        });
                        if (!visibleNames.length) html += '<div class="text-muted small">No columns in this header yet.</div>';
                        html += '<div class="e1g-add"><input type="text" list="e1gNames" data-add="' + gi + '" placeholder="+ Add a column to ' + esc(group.name) + '" autocomplete="off"></div></div>';
                    }
                    html += '</div>';
                });
                html += '<div class="e1g-new"><input type="text" id="e1gNewGroup" placeholder="New header name" autocomplete="off" maxlength="100">'
                    + '<button type="button" class="btn btn-sm btn-primary py-0" data-act="newgroup" title="Add a header">+</button></div>';
                body.innerHTML = html;
                body.querySelectorAll('.e1g-gcb').forEach(function (cb) {
                    const names = e1gGroupNames(e1gGroups[Number(cb.dataset.g)], map);
                    const on = names.filter(function (n) { return e1gSel.has(gKey(n)); }).length;
                    cb.checked = names.length > 0 && on === names.length;
                    cb.indeterminate = on > 0 && on < names.length;
                    cb.disabled = names.length === 0;
                });
                panel.scrollTop = scroll;
            }

            function e1gAddTo(group, name) {
                const key = gKey(name);
                if (!group.columns.some(function (c) { return gKey(c) === key; })) group.columns.push(name);
            }

            function e1gRemoveFrom(group, name) {
                const key = gKey(name);
                group.columns = group.columns.filter(function (c) { return gKey(c) !== key; });
            }

            function e1gNewGroup() {
                const input = document.getElementById('e1gNewGroup');
                const name = gClean(input ? input.value : '');
                if (!name) return;
                if (e1gGroups.some(function (g) { return g.name.toLowerCase() === name.toLowerCase(); })) {
                    e1gToast('A header named ' + name + ' already exists.');
                    return;
                }
                e1gGroups.push({ name: name, columns: [] });
                e1gOpen.add(name);
                persistColumnGroups();
                renderColumnGroups();
                const next = document.querySelector('#e1gBody [data-add="' + (e1gGroups.length - 1) + '"]');
                if (next) next.focus();
            }

            function bindColumnGroups() {
                const wrap = document.getElementById('e1gWrap');
                const panel = document.getElementById('e1gPanel');
                const body = document.getElementById('e1gBody');
                document.getElementById('e1gBtn').addEventListener('click', function () {
                    panel.hidden = !panel.hidden;
                    if (!panel.hidden) {
                        renderColumnGroups();
                        document.getElementById('e1gSearch').focus();
                    }
                });
                document.getElementById('e1gSearch').addEventListener('input', renderColumnGroups);
                document.addEventListener('mousedown', function (e) {
                    if (!wrap.contains(e.target)) panel.hidden = true;
                });
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') panel.hidden = true;
                });
                body.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' && e.target.id === 'e1gNewGroup') {
                        e.preventDefault();
                        e1gNewGroup();
                    }
                });
                body.addEventListener('click', function (e) {
                    const el = e.target.closest('[data-act]');
                    if (!el || el.tagName === 'SELECT') return;
                    const act = el.dataset.act;
                    const group = e1gGroups[Number(el.dataset.g)];
                    if (act === 'newgroup') {
                        e1gNewGroup();
                    } else if (act === 'clear') {
                        e1gSel.clear();
                        e1gApplySel();
                        renderColumnGroups();
                    } else if (act === 'toggle' && group) {
                        if (e1gOpen.has(group.name)) e1gOpen.delete(group.name);
                        else e1gOpen.add(group.name);
                        renderColumnGroups();
                    } else if (act === 'delgroup' && group) {
                        if (!window.confirm('Delete the header "' + group.name + '"? Its columns stay in the table.')) return;
                        e1gGroups = e1gGroups.filter(function (g) { return g !== group; });
                        persistColumnGroups();
                        renderColumnGroups();
                    } else if (act === 'remove' && group) {
                        e1gRemoveFrom(group, el.dataset.name);
                        persistColumnGroups();
                        renderColumnGroups();
                    }
                });
                body.addEventListener('change', function (e) {
                    const t = e.target;
                    const map = e1gKnownColumns();
                    if (t.classList.contains('e1g-gcb')) {
                        e1gGroupNames(e1gGroups[Number(t.dataset.g)], map).forEach(function (n) {
                            if (t.checked) e1gSel.add(gKey(n));
                            else e1gSel.delete(gKey(n));
                        });
                        e1gApplySel();
                        renderColumnGroups();
                    } else if (t.classList.contains('e1g-ccb')) {
                        if (t.checked) e1gSel.add(t.dataset.key);
                        else e1gSel.delete(t.dataset.key);
                        e1gApplySel();
                        renderColumnGroups();
                    } else if (t.dataset.act === 'xfer') {
                        const parts = String(t.value || '').split(':');
                        const from = e1gGroups[Number(t.dataset.g)];
                        const to = e1gGroups[Number(parts[1])];
                        if (!from || !to || !parts[0]) return;
                        e1gAddTo(to, t.dataset.name);
                        if (parts[0] === 'move') e1gRemoveFrom(from, t.dataset.name);
                        e1gOpen.add(to.name);
                        persistColumnGroups();
                        renderColumnGroups();
                    } else if (t.dataset.add !== undefined) {
                        const group = e1gGroups[Number(t.dataset.add)];
                        const real = map.get(gKey(t.value));
                        if (!group) return;
                        if (!real) {
                            if (gClean(t.value) !== '') e1gToast('No column named ' + t.value + '. Create it with Add Column first.');
                            return;
                        }
                        e1gAddTo(group, real);
                        persistColumnGroups();
                        renderColumnGroups();
                    }
                });
            }

            function syncSpecColumns(force) {
                if (!table || !tableReady) return;
                const names = specNamesForTab();
                const signature = activeTab + '|' + names.map(function (name) { return name + '=' + sourceFor(name); }).join('|');
                if (!force && signature === specSignature) return;
                specSignature = signature;
                const specColumns = names.map(function (name) {
                    const src = sourceFor(name);
                    return {
                        title: src ? esc(name) + ' <i class="fas fa-link" style="font-size:10px;opacity:.8"></i>' : esc(name),
                        field: specKey(name),
                        specName: name,
                        headerTooltip: src ? 'Synced from Technical Specifications: ' + src : name,
                        formatter: textFormatter,
                    };
                });
                table.setColumns(baseColumns().concat(specColumns, [copyColumn(), editColumn()]));
                applyColumnGroupVisibility();
            }

            // One line per word in the header. A trailing link icon (Source Data columns) stays on the last line.
            function breakTitle(cell) {
                const raw = String(cell.getValue() == null ? '' : cell.getValue());
                const at = raw.indexOf(' <i ');
                const text = at === -1 ? raw : raw.slice(0, at);
                const icon = at === -1 ? '' : raw.slice(at);
                // A header renamed with Edit Column Header shows its own name. The column itself keeps its real name.
                const holder = document.createElement('div');
                holder.innerHTML = text;
                const custom = headerLabels[gKey(holder.textContent)];
                if (custom) return esc(custom).trim().split(/\s+/).join('<br>') + icon;
                const safe = at === -1 && /<[a-z]/i.test(raw) ? raw : text.trim().split(/\s+/).join('<br>');
                return safe + icon;
            }

            // ---- Edit Column Header: rename the header text of any column (this browser only). ----
            const E1_HDR_KEY = 'ebay1_data_header_names_v1';
            let headerLabels = {};
            try {
                const parsed = JSON.parse(localStorage.getItem(E1_HDR_KEY) || '{}');
                if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) headerLabels = parsed;
            } catch (e) { headerLabels = {}; }
            const hdrDraft = new Map(); // column key -> { orig, text }

            function hdrNames() {
                return allDeleteNames().slice().sort(function (a, b) { return a.localeCompare(b, undefined, { sensitivity: 'base' }); });
            }

            function hdrVisible() {
                const q = String(document.getElementById('e1dHdrSearch').value || '').trim().toLowerCase();
                return hdrNames().filter(function (name) {
                    if (!q) return true;
                    const d = hdrDraft.get(gKey(name));
                    return name.toLowerCase().indexOf(q) !== -1 || (d && d.text.toLowerCase().indexOf(q) !== -1);
                });
            }

            function renderHdrList() {
                const list = document.getElementById('e1dHdrList');
                hdrNames().forEach(function (name) {
                    const key = gKey(name);
                    if (!hdrDraft.has(key)) hdrDraft.set(key, { orig: name, text: headerLabels[key] || name });
                });
                list.innerHTML = '';
                const shown = hdrVisible();
                if (!shown.length) list.innerHTML = '<div class="small text-muted p-2">No matching column.</div>';
                shown.forEach(function (name) {
                    const key = gKey(name);
                    const draft = hdrDraft.get(key);
                    const row = document.createElement('div');
                    row.className = 'e1d-hdrrow';
                    const orig = document.createElement('span');
                    orig.className = 'e1d-hdrorig';
                    orig.textContent = name;
                    orig.title = name;
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.maxLength = 60;
                    input.className = 'form-control form-control-sm' + (draft.text !== name ? ' changed' : '');
                    input.value = draft.text;
                    input.addEventListener('input', function () {
                        draft.text = input.value;
                        input.classList.toggle('changed', draft.text.trim() !== name);
                    });
                    input.addEventListener('keydown', function (e) {
                        if (e.key === 'Enter') { e.preventDefault(); saveHdrNames(); }
                    });
                    const undo = document.createElement('button');
                    undo.type = 'button';
                    undo.className = 'e1d-hdrundo';
                    undo.title = 'Back to "' + name + '"';
                    undo.innerHTML = '<i class="fas fa-undo"></i>';
                    undo.addEventListener('click', function () {
                        draft.text = name;
                        input.value = name;
                        input.classList.remove('changed');
                    });
                    row.appendChild(orig);
                    row.appendChild(input);
                    row.appendChild(undo);
                    list.appendChild(row);
                });
            }

            function openHdrModal() {
                hdrDraft.clear();
                document.getElementById('e1dHdrSearch').value = '';
                document.getElementById('e1dHdrIntro').textContent = activeTab === ALL
                    ? 'Page columns. Open a category tab to also rename its item-specific columns.'
                    : 'Category: ' + catLabel(activeTab);
                document.getElementById('e1dHdrModal').hidden = false;
                renderHdrList();
                document.getElementById('e1dHdrSearch').focus();
            }

            function closeHdrModal() {
                document.getElementById('e1dHdrModal').hidden = true;
            }

            function redrawHeaders() {
                if (!table || !tableReady) return;
                syncSpecColumns(true);
                updateMissingBadges();
            }

            function saveHdrNames() {
                hdrDraft.forEach(function (d, key) {
                    const text = String(d.text || '').replace(/\s+/g, ' ').trim();
                    if (text === '' || text === d.orig) delete headerLabels[key];
                    else headerLabels[key] = text;
                });
                try { localStorage.setItem(E1_HDR_KEY, JSON.stringify(headerLabels)); } catch (e) {}
                closeHdrModal();
                redrawHeaders();
            }

            function resetHdrNames() {
                if (!Object.keys(headerLabels).length) return;
                if (!window.confirm('Put every column header back to its original name?')) return;
                headerLabels = {};
                try { localStorage.removeItem(E1_HDR_KEY); } catch (e) {}
                closeHdrModal();
                redrawHeaders();
            }

            // Pen button, always the last column and frozen on the right whatever the horizontal scroll.
            function editColumn() {
                return {
                    title: '', field: '_edit', width: 32, minWidth: 32, hozAlign: 'center', headerHozAlign: 'center',
                    headerSort: false, resizable: false, frozen: true, download: false, cssClass: 'e1d-edit-col',
                    headerTooltip: 'Edit this row',
                    titleFormatter: function () { return '<i class="fas fa-pen" title="Edit"></i>'; },
                    formatter: function () {
                        return '<button type="button" class="btn btn-sm btn-primary e1d-edit-btn" title="Edit this row"><i class="fas fa-pen"></i></button>';
                    },
                    cellClick: function (e, cell) {
                        if (e.target.closest('.e1d-edit-btn')) openEditModal(cell.getRow().getData());
                    },
                };
            }

            // ---- Copy row / paste: copy the shareable data of one row, paste it into the ticked rows (right click). ----
            // Data that belongs to one SKU is never copied: SKU, parent, item id, UPC, MPN, model, EAN, GTIN, ISBN, serial and part numbers.
            // The eBay title and the price are copied with the item specifics.
            const E1_CLIP_KEY = 'ebay1_data_row_clip_v2';
            const E1_UNIQUE_KEYS = new Set(['sku', 'parent', 'itemid', 'upc', 'ean', 'gtin', 'isbn', 'mpn', 'model', 'modelnumber', 'modelname',
                'manufacturerpartnumber', 'partnumber', 'serialnumber', 'epid', 'ebaytitle', 'title', 'price']);
            let rowClip = null;
            let pasteBusy = false;
            try { rowClip = JSON.parse(localStorage.getItem(E1_CLIP_KEY) || 'null'); } catch (e) { rowClip = null; }
            if (!rowClip || !Array.isArray(rowClip.entries)) rowClip = null;

            function copyRowData(row) {
                const spec = row && row.specifics && !Array.isArray(row.specifics) ? row.specifics : {};
                const entries = [];
                Object.keys(spec).forEach(function (name) {
                    const value = String(spec[name] == null ? '' : spec[name]).trim();
                    if (value === '' || E1_UNIQUE_KEYS.has(gKey(name))) return;
                    entries.push({ name: name, value: value });
                });
                const title = String(row.ebay_title || '').trim();
                if (title !== '') entries.push({ name: 'eBay title', value: title.slice(0, 80) });
                const price = Number(row.ebay_price);
                if (row.ebay_price !== null && row.ebay_price !== '' && price > 0) entries.push({ name: 'Price', value: price.toFixed(2) });
                const status = document.getElementById('e1dPullStatus');
                if (!entries.length) {
                    status.textContent = 'Nothing to copy from ' + row.sku + '.';
                    rowToast('Nothing copied from ' + row.sku + ': it has no item-specific values to share. Pick a row that shows data in its columns (SKU, ID, UPC, MPN and Model are never copied).', false);
                    return false;
                }
                rowClip = { sku: row.sku, entries: entries };
                try { localStorage.setItem(E1_CLIP_KEY, JSON.stringify(rowClip)); } catch (e) {}
                status.textContent = 'Copied ' + entries.length + ' values of ' + row.sku + '. Right click the rows to paste.';
                rowToast('Copied ' + entries.length + ' values of ' + row.sku + '. Tick the rows, right click one and choose Paste.', true);
                return true;
            }

            // A message that cannot be missed: shown at the bottom of the screen for a few seconds.
            let rowToastTimer = null;
            function rowToast(message, ok) {
                let box = document.getElementById('e1dToast');
                if (!box) {
                    box = document.createElement('div');
                    box.id = 'e1dToast';
                    box.style.cssText = 'position:fixed;left:50%;bottom:28px;transform:translateX(-50%);z-index:3000;max-width:min(560px,92vw);padding:10px 16px;border-radius:12px;color:#fff;font-size:13px;font-weight:600;box-shadow:0 10px 30px rgba(15,23,42,.3);';
                    document.body.appendChild(box);
                }
                box.style.background = ok ? '#16a34a' : '#dc2626';
                box.textContent = message;
                box.style.display = 'block';
                clearTimeout(rowToastTimer);
                rowToastTimer = setTimeout(function () { box.style.display = 'none'; }, ok ? 3500 : 7000);
            }

            function pasteTargets(clickedRow) {
                const selected = table && tableReady ? table.getSelectedData().filter(passesTab) : [];
                const base = selected.length ? selected : (clickedRow ? [clickedRow] : []);
                return base.filter(function (row) { return row.sku && (!rowClip || String(row.sku) !== String(rowClip.sku)); });
            }

            async function pasteRowData(targets) {
                if (!rowClip || pasteBusy || !targets.length) return;
                const entries = rowClip.entries;
                if (!window.confirm('Paste ' + entries.length + ' values copied from ' + rowClip.sku + ' into ' + targets.length + ' row' + (targets.length === 1 ? '' : 's') + '?\nSKU, item ID, UPC, MPN and model are not touched. The eBay title and price are changed too. Nothing goes to eBay until you press Push.')) return;
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const skus = targets.map(function (row) { return row.sku; });
                const problems = [];
                const saved = [];
                let failed = 0;
                pasteBusy = true;
                for (let c = 0; c < entries.length; c++) {
                    jobShow('Pasting ' + rowClip.sku + ' into ' + targets.length + ' rows…', entries.length, saved.length, failed, true, problems);
                    try {
                        for (let i = 0; i < skus.length; i += 300) {
                            const res = await fetch('/ebay1-data/edit-column', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                                body: JSON.stringify({ column: entries[c].name, value: entries[c].value, skus: skus.slice(i, i + 300) }),
                            });
                            let json = {};
                            try { json = await res.json(); } catch (e) {}
                            if (!res.ok || json.success === false) throw new Error(json.message || 'Could not save');
                        }
                        saved.push(entries[c]);
                    } catch (e) {
                        failed++;
                        problems.push(entries[c].name + ': ' + (e.message || 'failed'));
                    }
                }
                if (saved.length) {
                    const bySku = new Map();
                    table.getRows().forEach(function (tRow) { bySku.set(String(tRow.getData().sku), tRow); });
                    table.blockRedraw();
                    try {
                        targets.forEach(function (row) {
                            const source = rows.find(function (r) { return String(r.sku) === String(row.sku); }) || row;
                            const spec = source.specifics && !Array.isArray(source.specifics) ? source.specifics : {};
                            const patch = {};
                            saved.forEach(function (entry) {
                                const page = pagePatch(entry.name, entry.value);
                                if (page) {
                                    Object.assign(patch, page);
                                    return;
                                }
                                const existing = Object.keys(spec).find(function (k) { return gKey(k) === gKey(entry.name); });
                                spec[existing || entry.name] = entry.value;
                                patch[specKey(entry.name)] = entry.value;
                            });
                            patch.specifics = spec;
                            Object.assign(source, patch);
                            const tRow = bySku.get(String(row.sku));
                            if (tRow) tRow.update(patch);
                        });
                    } finally {
                        table.restoreRedraw();
                    }
                }
                pasteBusy = false;
                jobShow('Paste finished: ' + saved.length + ' of ' + entries.length + ' values into ' + targets.length + ' rows', entries.length, saved.length, failed, false, problems);
                rowToast('Pasted ' + saved.length + ' of ' + entries.length + ' values into ' + targets.length + ' row' + (targets.length === 1 ? '' : 's') + (failed ? ', ' + failed + ' failed' : '') + '.', failed === 0);
                refreshAll();
            }

            // Copy button, just before the pen. Frozen on the right with it.
            function copyColumn() {
                return {
                    title: '', field: '_copy', width: 32, minWidth: 32, hozAlign: 'center', headerHozAlign: 'center',
                    headerSort: false, resizable: false, frozen: true, download: false, cssClass: 'e1d-edit-col',
                    headerTooltip: 'Copy this row (not SKU, ID or UPC). Right click rows to paste.',
                    titleFormatter: function () { return '<i class="far fa-copy" title="Copy row"></i>'; },
                    formatter: function () {
                        return '<button type="button" class="btn btn-sm btn-outline-secondary e1d-edit-btn e1d-rowcopy-btn" title="Copy this row. Right click the rows to paste it."><i class="far fa-copy"></i></button>';
                    },
                    cellClick: function (e, cell) {
                        const btn = e.target.closest('.e1d-rowcopy-btn');
                        if (!btn) return;
                        const ok = copyRowData(cell.getRow().getData());
                        const icon = btn.querySelector('i');
                        icon.className = ok ? 'fas fa-check' : 'fas fa-times';
                        setTimeout(function () { icon.className = 'far fa-copy'; }, 1200);
                    },
                };
            }

            // Right click on a row: paste the copied data into the ticked rows (or the clicked row when none is ticked).
            function rowMenu(e, component) {
                const clicked = component.getData();
                const targets = pasteTargets(clicked);
                const items = [];
                items.push({
                    label: rowClip
                        ? '<i class="fas fa-paste"></i> Paste ' + rowClip.entries.length + ' values from ' + esc(rowClip.sku) + ' into ' + targets.length + ' row' + (targets.length === 1 ? '' : 's')
                        : '<i class="fas fa-paste"></i> Paste (copy a row first with the copy button)',
                    disabled: !rowClip || !targets.length || pasteBusy,
                    action: function () { pasteRowData(targets); },
                });
                items.push({
                    label: '<i class="far fa-copy"></i> Copy data of ' + esc(clicked.sku),
                    action: function () { copyRowData(clicked); },
                });
                return items;
            }

            function baseColumns() {
                return [
                        {
                            formatter: 'rowSelection', titleFormatter: 'rowSelection', titleFormatterParams: { rowRange: 'active' },
                            hozAlign: 'center', headerHozAlign: 'center', headerSort: false, resizable: false,
                            width: 44, minWidth: 44, frozen: true, download: false, cssClass: 'e1d-select-col',
                        },
                        // The select box and SKU stay side by side on the left while scrolling; Parent follows them.
                        {
                            title: 'SKU', field: 'sku', hozAlign: 'left', minWidth: 110, frozen: true,
                            formatter: function (cell) {
                                const v = String(cell.getValue() || '').trim();
                                if (!v) return '<span class="e1d-muted">—</span>';
                                return '<span class="e1d-copy" title="Copy SKU"><i class="far fa-copy"></i></span><strong>' + esc(v) + '</strong>';
                            },
                            cellClick: copyCellClick,
                        },
                        { title: 'Parent', field: 'parent', hozAlign: 'left', formatter: textFormatter },
                        { title: 'INV', field: 'inv', formatter: numberFormatter(0) },
                        { title: 'Sold L30', field: 'sold_l30', formatter: numberFormatter(0) },
                        {
                            title: 'REQ/NRL', field: 'nr_req', 
                            headerTooltip: 'Click a value to change it. Saved to the same data as the Listing eBay page.',
                            editor: 'list',
                            editorParams: { values: { REQ: 'REQ', NR: 'NRL' }, autocomplete: false, listOnEmpty: true, clearable: false },
                            formatter: function (cell) {
                                return cell.getValue() === 'NR'
                                    ? '<span class="e1d-dot red" title="NRL"></span>'
                                    : '<span class="e1d-dot green" title="REQ"></span>';
                            },
                            cellEdited: function (cell) { saveNrReq(cell); },
                        },
                        {
                            title: 'Listed', field: 'listed', 
                            sorter: function (a, b, aRow, bRow) {
                                const rank = function (d) { return d.listed === 'Listed' ? 2 : (d.listed === 'Created' ? 1 : 0); };
                                return rank(aRow.getData()) - rank(bRow.getData());
                            },
                            formatter: function (cell) {
                                const d = cell.getRow().getData();
                                if (d.listed === 'Listed') return '<i class="fas fa-check-square e1d-tick green" title="Listed"></i>';
                                if (d.listed === 'Created') return '<span class="e1d-pill amber">Created</span>';
                                return '<i class="fas fa-times e1d-tick red" title="Missing L"></i>';
                            },
                        },
                        {
                            title: 'Item ID', field: 'item_id', 
                            formatter: function (cell) {
                                const id = String(cell.getValue() || '').trim();
                                if (!id) return '<span class="e1d-muted">—</span>';
                                const d = cell.getRow().getData();
                                const href = /^https?:/i.test(d.buyer_link || '') ? d.buyer_link : 'https://www.ebay.com/itm/' + encodeURIComponent(id);
                                return '<a href="' + esc(href) + '" target="_blank" rel="noopener">' + esc(id) + '</a>';
                            },
                        },
                        { title: 'Status', field: 'listing_status',  formatter: textFormatter },
                        {
                            title: 'eBay title', field: 'ebay_title', 
                            formatter: function (cell) {
                                const v = String(cell.getValue() || '').trim();
                                if (!v) return '<span class="e1d-muted">—</span>';
                                const shown = v.length > 80 ? v.slice(0, 80).trimEnd() + '…' : v;
                                return '<span class="e1d-copy" title="Copy title"><i class="far fa-copy"></i></span>'
                                    + '<span class="e1d-title-text" title="' + esc(v) + '">' + esc(shown) + '</span>';
                            },
                            cellClick: copyCellClick,
                        },
                        { title: 'Price', field: 'ebay_price', formatter: numberFormatter(2) },
                        { title: 'Stock', field: 'ebay_stock', formatter: numberFormatter(0) },
                        { title: 'L7', field: 'ebay_l7', formatter: numberFormatter(0) },
                        { title: 'L30', field: 'ebay_l30', formatter: numberFormatter(0) },
                        { title: 'L60', field: 'ebay_l60', formatter: numberFormatter(0) },
                        { title: 'Views', field: 'views', formatter: numberFormatter(0) },
                        { title: 'L7 Views', field: 'l7_views', formatter: numberFormatter(0) },
                        { title: 'L1 Views', field: 'l1_views', formatter: numberFormatter(0) },
                        { title: 'Organic Clicks', field: 'organic_clicks', formatter: numberFormatter(0) },
                        { title: 'Reason', field: 'inactive_reason',  formatter: textFormatter },
                        { title: 'Report date', field: 'report_date',  formatter: textFormatter },
                        { title: 'Category', field: 'category',  formatter: textFormatter },
                ];
            }

            // ---- Excel-like cells: click selects a cell, Shift+click / Shift+arrows / drag select a block,
            // Ctrl+C copies it (tab separated, so it pastes into Excel), Ctrl+V pastes into the selected cell(s). ----
            const CS_LOCKED_ALWAYS = new Set(['brand', 'durabilityguarantee', 'mpn', 'model', 'upc']);
            const CS_LOCKED_MISSING = new Set(['manufacturer', 'condition', 'countryoforigin']);
            let cellSel = null; // { a: {sku, field} anchor, f: {sku, field} moving end }
            let cellDrag = false;
            let cellBusy = false;
            let cellKeep = false;
            let cellHold = 0; // until this time, a refresh does not clear the selection

            function csColumns() {
                if (!table || !tableReady) return [];
                return table.getColumns().filter(function (col) {
                    const f = col.getField();
                    return f && f.charAt(0) !== '_' && col.isVisible();
                });
            }

            function csIndex() {
                const rowList = table.getRows('display').filter(function (r) { return r.getData && r.getData() && r.getData().sku !== undefined; });
                const cols = csColumns();
                const rIdx = new Map();
                rowList.forEach(function (r, i) { rIdx.set(String(r.getData().sku), i); });
                const cIdx = new Map();
                cols.forEach(function (c, i) { cIdx.set(c.getField(), i); });
                return { rowList: rowList, cols: cols, rIdx: rIdx, cIdx: cIdx };
            }

            function csBounds(ix) {
                if (!cellSel) return null;
                const ar = ix.rIdx.get(cellSel.a.sku), fr = ix.rIdx.get(cellSel.f.sku);
                const ac = ix.cIdx.get(cellSel.a.field), fc = ix.cIdx.get(cellSel.f.field);
                if (ar === undefined || fr === undefined || ac === undefined || fc === undefined) return null;
                return { r1: Math.min(ar, fr), r2: Math.max(ar, fr), c1: Math.min(ac, fc), c2: Math.max(ac, fc), ar: ar, ac: ac, fr: fr, fc: fc };
            }

            function csPaint() {
                if (!table || !tableReady) return;
                table.element.querySelectorAll('.e1d-cell-sel').forEach(function (el) { el.classList.remove('e1d-cell-sel', 'e1d-cell-act'); });
                if (!cellSel) return;
                const ix = csIndex();
                const b = csBounds(ix);
                if (!b) { cellSel = null; return; }
                table.getRows('visible').forEach(function (row) {
                    const i = ix.rIdx.get(String(row.getData().sku));
                    if (i === undefined || i < b.r1 || i > b.r2) return;
                    for (let c = b.c1; c <= b.c2; c++) {
                        const cell = row.getCell(ix.cols[c].getField());
                        if (!cell) continue;
                        const el = cell.getElement();
                        el.classList.add('e1d-cell-sel');
                        if (i === b.ar && c === b.ac) el.classList.add('e1d-cell-act');
                    }
                });
            }

            function csClear() {
                if (cellKeep || Date.now() < cellHold) return;
                if (!cellSel) return;
                cellSel = null;
                csPaint();
            }

            function csCellPoint(cell) {
                const field = cell.getField();
                const sku = cell.getRow().getData().sku;
                if (!field || field.charAt(0) === '_' || sku === undefined || sku === null || sku === '') return null;
                return { sku: String(sku), field: field };
            }

            function csDown(e, cell) {
                if (e.button !== 0) return;
                const pt = csCellPoint(cell);
                if (!pt) return;
                if (e.shiftKey && cellSel) {
                    cellSel.f = pt;
                    e.preventDefault();
                } else {
                    cellSel = { a: pt, f: pt };
                }
                cellDrag = true;
                csPaint();
            }

            function csEnter(e, cell) {
                if (!cellDrag || !cellSel) return;
                if (!(e.buttons & 1)) { cellDrag = false; return; }
                const pt = csCellPoint(cell);
                if (!pt || (pt.sku === cellSel.f.sku && pt.field === cellSel.f.field)) return;
                cellSel.f = pt;
                csPaint();
            }

            // Keys and paste are left to inputs, the list editor and open side panels.
            function csBlocked(e) {
                const t = e.target;
                if (document.querySelector('.e1d-modal:not([hidden])')) return true;
                if (t && t.closest && t.closest('input, textarea, select, [contenteditable="true"], .tabulator-edit-list, .tabulator-editing')) return true;
                return false;
            }

            function csCellText(data, col) {
                const f = col.getField();
                let v = data[f];
                if (v === null || v === undefined || typeof v === 'object') return '';
                if (f === 'nr_req') return v === 'NR' ? 'NRL' : String(v);
                v = String(v);
                if (/[\t\r\n"]/.test(v)) v = '"' + v.replace(/"/g, '""') + '"';
                return v;
            }

            function csWriteClipboard(text) {
                const fallback = function () {
                    const ta = document.createElement('textarea');
                    ta.value = text;
                    ta.style.cssText = 'position:fixed;left:-9999px;top:0;opacity:0;';
                    document.body.appendChild(ta);
                    ta.select();
                    try { document.execCommand('copy'); } catch (e) {}
                    ta.remove();
                };
                if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(text).catch(fallback);
                else fallback();
            }

            function csCopy() {
                if (!table || !tableReady) return;
                const ix = csIndex();
                const b = csBounds(ix);
                if (!b) return;
                const lines = [];
                for (let r = b.r1; r <= b.r2; r++) {
                    const data = ix.rowList[r].getData();
                    const vals = [];
                    for (let c = b.c1; c <= b.c2; c++) vals.push(csCellText(data, ix.cols[c]));
                    lines.push(vals.join('\t'));
                }
                csWriteClipboard(lines.join('\n'));
                const count = (b.r2 - b.r1 + 1) * (b.c2 - b.c1 + 1);
                rowToast('Copied ' + count + ' cell' + (count === 1 ? '' : 's') + '.', true);
            }

            function csParse(text) {
                const out = [];
                let row = [], cur = '', q = false;
                text = String(text).replace(/\r\n?/g, '\n');
                for (let i = 0; i < text.length; i++) {
                    const ch = text.charAt(i);
                    if (q) {
                        if (ch === '"') {
                            if (text.charAt(i + 1) === '"') { cur += '"'; i++; } else q = false;
                        } else cur += ch;
                    } else if (ch === '"' && cur === '') q = true;
                    else if (ch === '\t') { row.push(cur); cur = ''; }
                    else if (ch === '\n') { row.push(cur); out.push(row); row = []; cur = ''; }
                    else cur += ch;
                }
                row.push(cur);
                out.push(row);
                // Excel ends what it copies with a line break: that is not an extra row.
                if (out.length > 1 && out[out.length - 1].length === 1 && out[out.length - 1][0] === '') out.pop();
                return out;
            }

            // What one pasted value does to one cell: an edit, or why it is skipped.
            function csEditFor(data, col, raw) {
                const field = col.getField();
                const def = col.getDefinition ? col.getDefinition() : {};
                const value = String(raw === null || raw === undefined ? '' : raw).trim();
                if (field === 'nr_req') {
                    const v = value.toUpperCase();
                    if (v === 'NR' || v === 'NRL') return { kind: 'nr', value: 'NR' };
                    if (v === 'REQ') return { kind: 'nr', value: 'REQ' };
                    return { skip: 'blank' };
                }
                if (field === 'ebay_title') {
                    if (!value) return { skip: 'blank' };
                    if (value.length > 80) return { skip: 'long' };
                    return { kind: 'col', column: 'eBay title', value: value };
                }
                if (field === 'ebay_price') {
                    const n = parseFloat(value.replace(/[,$\s]/g, ''));
                    if (isNaN(n) || n < 0.01 || n > 100000) return { skip: 'blank' };
                    return { kind: 'col', column: 'Price', value: n.toFixed(2) };
                }
                if (def && def.specName) {
                    if (!value) return { skip: 'blank' };
                    const key = gKey(def.specName);
                    const missing = ['Listed', 'Created'].indexOf(String(data.listed)) === -1;
                    if (CS_LOCKED_ALWAYS.has(key) || (missing && CS_LOCKED_MISSING.has(key))) return { skip: 'locked' };
                    return { kind: 'col', column: def.specName, value: value };
                }
                return { skip: 'readonly' };
            }

            async function csPaste(text) {
                if (cellBusy || !table || !tableReady) return;
                const grid = csParse(text);
                const ix = csIndex();
                const b = csBounds(ix);
                if (!b || !grid.length) return;
                const single = grid.length === 1 && grid[0].length === 1;
                const targets = [];
                let region = null;
                if (single && (b.r1 !== b.r2 || b.c1 !== b.c2)) {
                    for (let r = b.r1; r <= b.r2; r++) for (let c = b.c1; c <= b.c2; c++) targets.push({ r: r, c: c, v: grid[0][0] });
                } else {
                    let r2 = b.r1, c2 = b.c1;
                    grid.forEach(function (line, i) {
                        line.forEach(function (v, j) {
                            const r = b.r1 + i, c = b.c1 + j;
                            if (r >= ix.rowList.length || c >= ix.cols.length) return;
                            targets.push({ r: r, c: c, v: v });
                            r2 = Math.max(r2, r);
                            c2 = Math.max(c2, c);
                        });
                    });
                    region = { r2: r2, c2: c2 };
                }

                const skipped = { blank: 0, locked: 0, readonly: 0, long: 0 };
                const groups = new Map(); // column|value -> { kind, column, value, skus }
                const nrBySku = new Map();
                let cells = 0;
                targets.forEach(function (t) {
                    const data = ix.rowList[t.r].getData();
                    const edit = csEditFor(data, ix.cols[t.c], t.v);
                    if (edit.skip) { skipped[edit.skip]++; return; }
                    cells++;
                    const sku = String(data.sku);
                    if (edit.kind === 'nr') { nrBySku.set(sku, edit.value); return; }
                    const key = edit.column + '\u0001' + edit.value;
                    if (!groups.has(key)) groups.set(key, { column: edit.column, value: edit.value, skus: [] });
                    groups.get(key).skus.push(sku);
                });
                const skippedTotal = skipped.blank + skipped.locked + skipped.readonly + skipped.long;
                const skippedText = function () {
                    const parts = [];
                    if (skipped.readonly) parts.push(skipped.readonly + ' read-only');
                    if (skipped.locked) parts.push(skipped.locked + ' fixed (autofill) value');
                    if (skipped.long) parts.push(skipped.long + ' title over 80 characters');
                    if (skipped.blank) parts.push(skipped.blank + ' blank / not valid');
                    return parts.join(', ');
                };
                if (!cells) {
                    rowToast('Nothing pasted' + (skippedTotal ? ' (' + skippedText() + ').' : '.'), false);
                    return;
                }
                if (cells > 100 && !window.confirm('Paste into ' + cells + ' cells?')) return;

                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const post = async function (url, body) {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify(body),
                    });
                    let json = {};
                    try { json = await res.json(); } catch (e) {}
                    if (!res.ok || json.success === false) throw new Error(json.message || 'Could not save');
                    return json;
                };
                const jobs = [];
                groups.forEach(function (g) {
                    for (let i = 0; i < g.skus.length; i += 300) jobs.push({ kind: 'col', column: g.column, value: g.value, skus: g.skus.slice(i, i + 300) });
                });
                nrBySku.forEach(function (value, sku) { jobs.push({ kind: 'nr', value: value, skus: [sku] }); });

                const status = document.getElementById('e1dPullStatus');
                const done = [];
                let failure = '';
                cellBusy = true;
                try {
                    for (let i = 0; i < jobs.length; i++) {
                        const job = jobs[i];
                        if (jobs.length > 1) status.textContent = 'Pasting ' + (i + 1) + ' / ' + jobs.length + '…';
                        if (job.kind === 'nr') await post('/ebay1-data/nr-req', { sku: job.skus[0], nr_req: job.value });
                        else await post('/ebay1-data/edit-column', { column: job.column, value: job.value, skus: job.skus });
                        done.push(job);
                    }
                } catch (e) {
                    failure = e.message || 'Could not save';
                }
                cellBusy = false;

                if (done.length) {
                    const rowBySku = new Map();
                    rows.forEach(function (r) { rowBySku.set(String(r.sku), r); });
                    const tableBySku = new Map();
                    table.getRows().forEach(function (tRow) { tableBySku.set(String(tRow.getData().sku), tRow); });
                    table.blockRedraw();
                    try {
                        done.forEach(function (job) {
                            job.skus.forEach(function (sku) {
                                const source = rowBySku.get(sku);
                                const tRow = tableBySku.get(sku);
                                const data = tRow ? tRow.getData() : source;
                                if (!data) return;
                                let patch;
                                if (job.kind === 'nr') {
                                    patch = { nr_req: job.value };
                                } else {
                                    patch = pagePatch(job.column, job.value);
                                    if (!patch) {
                                        const spec = data.specifics && !Array.isArray(data.specifics) ? data.specifics : {};
                                        const existing = Object.keys(spec).find(function (k) { return gKey(k) === gKey(job.column); });
                                        spec[existing || job.column] = job.value;
                                        patch = { specifics: spec };
                                        patch[specKey(job.column)] = job.value;
                                        patch.push_cols = addPushCol(data, existing || job.column);
                                    }
                                }
                                autoSeen.delete(sku);
                                if (source) Object.assign(source, patch);
                                if (tRow) tRow.update(patch);
                            });
                        });
                    } finally {
                        table.restoreRedraw();
                    }
                    // Keep the selection through the refresh, like Excel. A pasted block stays selected.
                    if (region) {
                        const last = ix.rowList[region.r2].getData().sku;
                        cellSel = { a: { sku: String(ix.rowList[b.r1].getData().sku), field: ix.cols[b.c1].getField() }, f: { sku: String(last), field: ix.cols[region.c2].getField() } };
                    }
                    cellKeep = true;
                    try { refreshAll(true); } finally { cellKeep = false; }
                    csPaint();
                    autoPushSoon();
                }
                if (failure) {
                    rowToast('Pasted ' + done.length + ' of ' + jobs.length + ' changes, then it stopped: ' + failure, false);
                } else {
                    status.textContent = 'Pasted ' + cells + ' cell' + (cells === 1 ? '' : 's') + '.';
                    rowToast('Pasted ' + cells + ' cell' + (cells === 1 ? '' : 's') + (skippedTotal ? ' · skipped ' + skippedText() : '') + '.', true);
                }
            }

            function csMove(e) {
                const ix = csIndex();
                const b = csBounds(ix);
                if (!b) return;
                let r = b.fr, c = b.fc;
                if (e.key === 'ArrowUp') r--;
                else if (e.key === 'ArrowDown') r++;
                else if (e.key === 'ArrowLeft') c--;
                else if (e.key === 'ArrowRight') c++;
                r = Math.max(0, Math.min(ix.rowList.length - 1, r));
                c = Math.max(0, Math.min(ix.cols.length - 1, c));
                const pt = { sku: String(ix.rowList[r].getData().sku), field: ix.cols[c].getField() };
                if (e.shiftKey) cellSel.f = pt;
                else cellSel = { a: pt, f: pt };
                csPaint();
                try { table.scrollToRow(ix.rowList[r], 'nearest', false).catch(function () {}); } catch (err) {}
                try { table.scrollToColumn(pt.field, 'nearest', false).catch(function () {}); } catch (err) {}
            }

            function csBind() {
                table.on('cellMouseDown', csDown);
                table.on('cellMouseEnter', csEnter);
                table.on('renderComplete', csPaint);
                table.on('scrollVertical', csPaint);
                table.on('pageLoaded', csClear);
                table.on('dataSorted', csClear);
                table.on('dataFiltered', csClear);
            }

            function csBindDocument() {
                document.addEventListener('mouseup', function () { cellDrag = false; });
                document.addEventListener('mousedown', function (e) {
                    if (!cellSel || !table) return;
                    if (e.target.closest && e.target.closest('#ebay1-data-table, .tabulator-menu, .e1d-modal, .e1d-fmt')) return;
                    csClear();
                });
                document.addEventListener('keydown', function (e) {
                    if (!cellSel || cellBusy || csBlocked(e)) return;
                    const mod = e.ctrlKey || e.metaKey;
                    const key = String(e.key || '');
                    if (mod && key.toLowerCase() === 'c') {
                        if (window.getSelection && String(window.getSelection()).trim() !== '') return;
                        e.preventDefault();
                        csCopy();
                    } else if (mod && key.toLowerCase() === 'a') {
                        const ix = csIndex();
                        if (!ix.rowList.length || !ix.cols.length) return;
                        e.preventDefault();
                        cellSel = {
                            a: { sku: String(ix.rowList[0].getData().sku), field: ix.cols[0].getField() },
                            f: { sku: String(ix.rowList[ix.rowList.length - 1].getData().sku), field: ix.cols[ix.cols.length - 1].getField() },
                        };
                        csPaint();
                    } else if (!mod && key === 'Escape') {
                        csClear();
                    } else if (!mod && /^Arrow(Up|Down|Left|Right)$/.test(key)) {
                        e.preventDefault();
                        csMove(e);
                    }
                });
                document.addEventListener('paste', function (e) {
                    if (!cellSel || cellBusy || csBlocked(e)) return;
                    const text = e.clipboardData ? e.clipboardData.getData('text/plain') : '';
                    if (text === '' || text === null || text === undefined) return;
                    e.preventDefault();
                    csPaste(text);
                });
            }

            // ---- Cell colours: text colour and fill of the selected cells (one or many), like Excel. Saved for everyone. ----
            let cellStyles = {}; // SKU key -> field -> [text colour, fill]
            const E1_PAL = [
                ['#c00000', '#ff0000', '#ffc000', '#ffff00', '#92d050', '#00b050', '#00b0f0', '#0070c0', '#002060', '#7030a0'],
                ['#000000', '#262626', '#404040', '#595959', '#7f7f7f', '#a6a6a6', '#bfbfbf', '#d9d9d9', '#f2f2f2', '#ffffff'],
                ['#ffc7ce', '#ffeb9c', '#c6efce', '#ddebf7', '#e4dfec', '#fce4d6', '#fff2cc', '#e2efda', '#d9e1f2', '#ededed'],
            ];
            const fmtLast = { color: '#ff0000', bg: '#ffff00' };
            try {
                fmtLast.color = localStorage.getItem('ebay1_fmt_color') || fmtLast.color;
                fmtLast.bg = localStorage.getItem('ebay1_fmt_bg') || fmtLast.bg;
            } catch (e) {}
            let palMode = 'color';

            function styleKey(sku) {
                return String(sku === null || sku === undefined ? '' : sku).replace(/\u00a0/g, ' ').trim().toUpperCase();
            }

            // Puts the saved colours of one row on its cells (CSS variables, so selection and hover keep working).
            function styleRow(row) {
                const st = cellStyles[styleKey(row.getData().sku)] || null;
                row.getCells().forEach(function (cell) {
                    const f = cell.getField();
                    const el = cell.getElement();
                    if (!el) return;
                    const s = st && f ? st[f] : null;
                    if (s && s[0]) { el.style.setProperty('--e1d-fg', s[0]); el.classList.add('e1d-fg'); }
                    else { el.style.removeProperty('--e1d-fg'); el.classList.remove('e1d-fg'); }
                    if (s && s[1]) { el.style.setProperty('--e1d-bg', s[1]); el.classList.add('e1d-bg'); }
                    else { el.style.removeProperty('--e1d-bg'); el.classList.remove('e1d-bg'); }
                });
            }

            function restyleVisible() {
                if (!table || !tableReady) return;
                table.getRows('visible').forEach(styleRow);
            }

            // patch: { color: '#rrggbb' | null } and/or { bg: '#rrggbb' | null }. null = back to the default colour.
            async function applyCellStyle(patch) {
                if (!table || !tableReady) return;
                const ix = csIndex();
                const b = csBounds(ix);
                if (!b) { rowToast('Select the cells first, then pick a colour.', false); return; }
                const cells = [];
                for (let r = b.r1; r <= b.r2; r++) {
                    const sku = String(ix.rowList[r].getData().sku);
                    for (let c = b.c1; c <= b.c2; c++) cells.push({ sku: sku, field: ix.cols[c].getField() });
                }
                cells.forEach(function (cell) {
                    const k = styleKey(cell.sku);
                    const cur = (cellStyles[k] && cellStyles[k][cell.field]) || [null, null];
                    const next = [
                        Object.prototype.hasOwnProperty.call(patch, 'color') ? patch.color : cur[0],
                        Object.prototype.hasOwnProperty.call(patch, 'bg') ? patch.bg : cur[1],
                    ];
                    if (!next[0] && !next[1]) {
                        if (cellStyles[k]) {
                            delete cellStyles[k][cell.field];
                            if (!Object.keys(cellStyles[k]).length) delete cellStyles[k];
                        }
                    } else {
                        (cellStyles[k] = cellStyles[k] || {})[cell.field] = next;
                    }
                });
                restyleVisible();
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                try {
                    for (let i = 0; i < cells.length; i += 1500) {
                        const res = await fetch('/ebay1-data/cell-styles', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                            body: JSON.stringify(Object.assign({ cells: cells.slice(i, i + 1500) }, patch)),
                        });
                        let json = {};
                        try { json = await res.json(); } catch (e) {}
                        if (!res.ok || json.success === false) throw new Error(json.message || 'Could not save');
                    }
                    rowToast('Colour applied to ' + cells.length + ' cell' + (cells.length === 1 ? '' : 's') + '.', true);
                } catch (e) {
                    rowToast('Could not save the colour: ' + (e.message || 'failed'), false);
                    loadData();
                }
            }

            function renderFmtSwatches() {
                document.getElementById('e1dTxtSwatch').style.background = fmtLast.color;
                document.getElementById('e1dBgSwatch').style.background = fmtLast.bg;
            }

            function closePalette() {
                const pal = document.getElementById('e1dPal');
                if (pal) pal.style.display = 'none';
            }

            function pickColour(mode, value) {
                if (value) {
                    fmtLast[mode] = value;
                    try { localStorage.setItem(mode === 'color' ? 'ebay1_fmt_color' : 'ebay1_fmt_bg', value); } catch (e) {}
                    renderFmtSwatches();
                }
                closePalette();
                applyCellStyle(mode === 'color' ? { color: value || null } : { bg: value || null });
            }

            function openPalette(mode, anchor) {
                let pal = document.getElementById('e1dPal');
                if (pal && pal.style.display === 'block' && palMode === mode) { closePalette(); return; }
                palMode = mode;
                if (!pal) {
                    pal = document.createElement('div');
                    pal.id = 'e1dPal';
                    pal.className = 'e1d-fmt';
                    document.body.appendChild(pal);
                    pal.addEventListener('click', function (e) {
                        const sw = e.target.closest('[data-c]');
                        if (sw) { pickColour(palMode, sw.getAttribute('data-c')); return; }
                        if (e.target.closest('[data-none]')) { pickColour(palMode, null); return; }
                        if (e.target.closest('[data-clear]')) { closePalette(); applyCellStyle({ color: null, bg: null }); }
                    });
                    pal.addEventListener('change', function (e) {
                        if (e.target.matches('input[type="color"]')) pickColour(palMode, e.target.value);
                    });
                }
                const isText = mode === 'color';
                pal.innerHTML = '<div class="e1d-pal-title">' + (isText ? 'Text colour' : 'Cell fill') + ' · selected cells</div>'
                    + '<button type="button" class="e1d-pal-btn" data-none="1">' + (isText ? 'Automatic (default text colour)' : 'No Fill (default background)') + '</button>'
                    + E1_PAL.map(function (line) {
                        return '<div class="e1d-pal-grid">' + line.map(function (c) { return '<button type="button" class="e1d-sw" data-c="' + c + '" style="background:' + c + '" title="' + c + '"></button>'; }).join('') + '</div>';
                    }).join('')
                    + '<div class="e1d-pal-foot"><label class="mb-0">More colours <input type="color" value="' + (isText ? fmtLast.color : fmtLast.bg) + '"></label>'
                    + '<button type="button" class="e1d-pal-btn mb-0" style="width:auto;margin-left:auto" data-clear="1">Clear formatting</button></div>';
                const rect = anchor.getBoundingClientRect();
                pal.style.display = 'block';
                pal.style.left = Math.max(4, Math.min(rect.left, window.innerWidth - 244)) + 'px';
                pal.style.top = (rect.bottom + 4) + 'px';
            }

            function fmtBind() {
                renderFmtSwatches();
                document.getElementById('e1dTxtApply').addEventListener('click', function () { closePalette(); pickColour('color', fmtLast.color); });
                document.getElementById('e1dBgApply').addEventListener('click', function () { closePalette(); pickColour('bg', fmtLast.bg); });
                document.getElementById('e1dTxtMenu').addEventListener('click', function () { openPalette('color', this); });
                document.getElementById('e1dBgMenu').addEventListener('click', function () { openPalette('bg', this); });
                document.addEventListener('mousedown', function (e) {
                    if (e.target.closest && e.target.closest('#e1dPal, .e1d-fmt')) return;
                    closePalette();
                });
                document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closePalette(); });
            }

            function buildTable() {
                table = new Tabulator('#ebay1-data-table', {
                    rowFormatter: styleRow,
                    data: rows,
                    layout: 'fitDataFill',
                    height: 'calc(100vh - 330px)',
                    pagination: true,
                    paginationSize: 100,
                    paginationSizeSelector: [50, 100, 200, 500, true],
                    placeholder: 'No rows',
                    initialSort: [{ column: 'sku', dir: 'asc' }],
                    // Rows are ticked only with their checkbox, any number of them. (Click-range mode replaced the
                    // previous tick with the new one, so only one row stayed selected.)
                    selectableRows: 'highlight',
                    // Header titles break at every space (<br>), so the vertical headers stay short.
                    columnDefaults: { titleFormatter: breakTitle, hozAlign: 'center', headerHozAlign: 'center' },
                    columns: baseColumns().concat([copyColumn(), editColumn()]),
                    rowContextMenu: rowMenu,
                });
                csBind();
                table.on('rowSelectionChanged', function () { renderBadges(); updatePushButton(); });
                table.on('tableBuilt', function () {
                    tableReady = true;
                    syncSpecColumns(true);
                    applyFilters();
                    updateMissingBadges();
                });
                table.on('dataFiltered', function () { /* badges are computed from the same rules */ });
            }

            async function loadData() {
                const placeholder = document.getElementById('e1dBadges');
                placeholder.innerHTML = '<span class="e1d-badge rows">Loading…</span>';
                try {
                    const res = await fetch('/ebay1-data/rows', { headers: { Accept: 'application/json' } });
                    const json = await res.json();
                    if (!res.ok || json.status !== 200) {
                        throw new Error(json.message || 'Failed to load Ebay1 Data');
                    }
                    rows = (json.data || []).filter(function (row) { return !isParentRow(row); });
                    columnSources = Array.isArray(json.sources) ? json.sources : [];
                    autoPushOn = !!json.auto_push;
                    renderAutoButton();
                    cellStyles = json.cell_styles && !Array.isArray(json.cell_styles) ? json.cell_styles : {};
                    initColumnGroups(json.column_groups);
                    flattenSpecifics(rows);
                    const view = viewState();
                    if (table && tableReady) {
                        table.replaceData(rows);
                        renderTabs();
                        syncSpecColumns(true);
                    } else if (!table) {
                        buildTable();
                    }
                    refreshAll();
                    // A reload (Refresh button, after a push) keeps the page and scroll position the user was at.
                    restoreView(view);
                    restyleVisible();
                    autoPushSoon();
                } catch (e) {
                    placeholder.innerHTML = '<span class="e1d-badge nrl">' + esc(e.message || 'Failed to load') + '</span>';
                }
            }

            document.addEventListener('DOMContentLoaded', function () {
                document.getElementById('e1dSearch').addEventListener('input', function () {
                    clearTimeout(searchTimer);
                    searchTimer = setTimeout(refreshAll, 200);
                });
                ['e1dListed', 'e1dNr', 'e1dStock'].forEach(function (id) {
                    document.getElementById(id).addEventListener('change', refreshAll);
                });
                bindColumnGroups();
                csBindDocument();
                fmtBind();
                document.getElementById('e1dEditCol').addEventListener('click', function () { openEditModal(null); });
                document.getElementById('e1dEditCancel').addEventListener('click', closeEditModal);
                document.getElementById('e1dEditGo').addEventListener('click', runEditColumn);
                document.getElementById('e1dEditSearch').addEventListener('input', renderEditList);
                document.getElementById('e1dEditAll').addEventListener('change', function () {
                    const on = this.checked;
                    visibleEditNames().forEach(function (name) {
                        if (on) { if (!editPicked.has(name)) editPicked.set(name, startingEditValue(name)); }
                        else editPicked.delete(name);
                    });
                    renderEditList();
                    renderEditFields();
                });
                document.getElementById('e1dRefresh').addEventListener('click', loadData);
                document.getElementById('e1dPush').addEventListener('click', pushToEbay);
                document.getElementById('e1dAuto').addEventListener('click', toggleAutoPush);
                document.getElementById('e1dAddCol').addEventListener('click', openAddModal);
                document.getElementById('e1dAddCancel').addEventListener('click', closeAddModal);
                document.getElementById('e1dAddGo').addEventListener('click', runAddColumn);
                document.getElementById('e1dAddInput').addEventListener('input', renderAddList);
                document.getElementById('e1dAddInput').addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') { e.preventDefault(); addTypedOrSubmit(); }
                    if (e.key === 'Escape') closeAddModal();
                });
                document.getElementById('e1dSrcBtn').addEventListener('click', openSrcModal);
                document.getElementById('e1dSrcCancel').addEventListener('click', closeSrcModal);
                document.getElementById('e1dSrcCol').addEventListener('change', function () {
                    document.getElementById('e1dSrcInput').value = sourceFor(this.value);
                    renderSrcList();
                });
                document.getElementById('e1dSrcInput').addEventListener('input', renderSrcList);
                document.getElementById('e1dSrcSave').addEventListener('click', function () { postSource(srcState().match); });
                document.getElementById('e1dSrcClear').addEventListener('click', function () { postSource(''); });
                document.getElementById('e1dDelCol').addEventListener('click', function () { openDelModal(); });
                document.getElementById('e1dDelCancel').addEventListener('click', closeDelModal);
                document.getElementById('e1dDelAll').addEventListener('change', function () {
                    const visible = delVisibleNames();
                    if (this.checked) visible.forEach(function (name) { delPicked.add(name); });
                    else visible.forEach(function (name) { delPicked.delete(name); });
                    renderDelList();
                });
                document.getElementById('e1dDelSearch').addEventListener('input', renderDelList);
                document.getElementById('e1dHdrBtn').addEventListener('click', openHdrModal);
                document.getElementById('e1dHdrCancel').addEventListener('click', closeHdrModal);
                document.getElementById('e1dHdrGo').addEventListener('click', saveHdrNames);
                document.getElementById('e1dHdrReset').addEventListener('click', resetHdrNames);
                document.getElementById('e1dHdrSearch').addEventListener('input', renderHdrList);
                document.getElementById('e1dDelGo').addEventListener('click', runDelete);
                document.getElementById('e1dJobClose').addEventListener('click', function () { document.getElementById('e1dJob').hidden = true; });
                // Click outside a side panel, or Esc, does the same as its Cancel button.
                const dismissModal = function (modal) {
                    if (!modal) return;
                    const cancel = modal.querySelector('[id$="Cancel"]');
                    if (cancel && !cancel.disabled) cancel.click();
                    else modal.hidden = true;
                };
                // A close (x) button on every side panel.
                document.querySelectorAll('.e1d-modal').forEach(function (modal) {
                    const box = modal.querySelector('.e1d-modal-box');
                    if (!box) return;
                    const x = document.createElement('button');
                    x.type = 'button';
                    x.className = 'e1d-x';
                    x.title = 'Close';
                    x.setAttribute('aria-label', 'Close');
                    x.innerHTML = '<i class="fas fa-times"></i>';
                    x.addEventListener('click', function () { dismissModal(modal); });
                    box.prepend(x);
                });
                window.addEventListener('beforeunload', function (e) {
                    if (!delRunning) return;
                    e.preventDefault();
                    e.returnValue = '';
                });
                document.querySelectorAll('.e1d-modal').forEach(function (modal) {
                    modal.addEventListener('mousedown', function (e) { if (e.target === modal) dismissModal(modal); });
                });
                document.addEventListener('keydown', function (e) {
                    if (e.key !== 'Escape') return;
                    dismissModal(document.querySelector('.e1d-modal:not([hidden])'));
                });
                document.getElementById('e1dExport').addEventListener('click', function () {
                    if (!table) return;
                    const label = activeTab === ALL ? 'all' : catLabel(activeTab).replace(/[^a-z0-9]+/gi, '_').toLowerCase();
                    table.download('csv', 'ebay1_data_' + label + '.csv');
                });
                loadData();
            });
        })();
    </script>
@endsection
