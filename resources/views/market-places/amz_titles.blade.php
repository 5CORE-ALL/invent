@extends('layouts.vertical', ['title' => 'Amz Titles', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        #amz-tt-wrap .tabulator { border: 1px solid #dee2e6; border-radius: 8px; font-size: 12px; }
        #amz-tt-wrap .tabulator .tabulator-header { background: #f8f9fa; }
        #amz-tt-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            white-space: normal !important; font-size: 11px; font-weight: 600; text-align: center; line-height: 1.2; padding: 4px 2px;
        }
        #amz-tt-wrap .tabulator .tabulator-cell { padding: 3px 4px !important; }
        #amz-tt-wrap .tabulator-row.tabulator-selected { background: #e7f1ff !important; }
        #amz-tt-wrap .tabulator-row.amz-tt-parent-row { background: #fff3cd !important; font-weight: 600; }
        #amz-tt-wrap .tabulator-row.amz-tt-parent-row.tabulator-selected { background: #ffe08a !important; }
        .amz-tt-thumb { width: 36px; height: 36px; object-fit: contain; border-radius: 4px; background: #fff; }
        .amz-tt-title170 {
            display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical;
            overflow: hidden; white-space: normal; line-height: 1.25; font-size: 11px;
            cursor: pointer; min-height: 1.2em;
        }
        .amz-tt-title170.is-fallback { color: #6c757d; font-style: italic; }
        .amz-tt-title170-meta { font-size: 10px; color: #6c757d; margin-top: 2px; }
        .amz-tt-title170-meta.over { color: #dc3545; font-weight: 600; }
        .amz-tt-char-count { font-weight: 600; font-size: 12px; color: #212529; }
        .amz-tt-char-count.over { color: #dc3545; font-weight: 700; }
        #amz-tt-wrap .tabulator-cell[tabulator-field="title150"] { max-width: 420px; }
        .amz-tt-ai-cell { display: inline-flex; align-items: center; justify-content: center; }
        .amz-tt-wand-btn {
            border: 0; background: transparent; color: #6f42c1; padding: 0 2px;
            cursor: pointer; font-size: 14px; line-height: 1;
        }
        .amz-tt-wand-btn:hover { color: #59359a; }
        .amz-tt-wand-btn:disabled { opacity: 0.5; cursor: wait; }
        .amz-tt-kw-list { margin: 0; padding: 0; list-style: none; font-size: 11px; line-height: 1.25; text-align: left; }
        .amz-tt-kw-list li {
            display: flex; align-items: flex-start; gap: 5px; margin-bottom: 3px;
        }
        .amz-tt-kw-list li .amz-tt-kw-check { margin-top: 2px; flex-shrink: 0; pointer-events: none; }
        .amz-tt-kw-list li.is-used .amz-tt-kw-text { color: #198754; font-weight: 600; }
        .amz-tt-kw-list li:not(.is-used) .amz-tt-kw-text { color: #6c757d; }
        .amz-tt-kw-meta { font-size: 10px; color: #adb5bd; }
        .amz-tt-neg-list { margin: 0; padding: 0; list-style: none; font-size: 11px; line-height: 1.25; text-align: left; max-height: 280px; overflow: auto; }
        .amz-tt-neg-list li {
            display: flex; align-items: flex-start; gap: 5px; margin-bottom: 3px;
        }
        .amz-tt-neg-list li .amz-tt-neg-check { margin-top: 2px; flex-shrink: 0; cursor: pointer; }
        .amz-tt-neg-list li.is-pushed .amz-tt-neg-text { color: #198754; }
        .amz-tt-neg-list li:not(.is-checked) .amz-tt-neg-text { opacity: 0.45; text-decoration: line-through; }
        .amz-tt-neg-actions { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }
        .amz-tt-neg-actions .btn { font-size: 10px; padding: 1px 6px; }
        .amz-tt-score { font-weight: 700; font-size: 12px; }
        .amz-tt-score-yellow {
            display: inline-block; min-width: 2.4em; padding: 2px 6px;
            font-weight: 700; font-size: 12px; color: #000 !important;
            background: #ffc107; border-radius: 4px; line-height: 1.2;
        }
        .amz-tt-comp-cell { display: flex; flex-direction: column; align-items: center; gap: 2px; line-height: 1.2; }
        .amz-tt-comp-price { font-weight: 700; font-size: 13px; color: #198754; }
        .amz-tt-comp-view { font-size: 11px; color: #0d6efd; text-decoration: none; cursor: pointer; }
        .amz-tt-comp-view:hover { text-decoration: underline; }
        #amz-tt-comp-list .amz-tt-comp-item {
            display: flex; gap: 10px; align-items: flex-start;
            padding: 8px 0; border-bottom: 1px solid #eee; font-size: 12px;
        }
        #amz-tt-comp-list .amz-tt-comp-item img {
            width: 48px; height: 48px; object-fit: contain; border-radius: 4px; background: #fff;
        }
        #amz-tt-comp-list .amz-tt-comp-item .amz-tt-comp-meta { flex: 1; min-width: 0; }
        #amz-tt-comp-list .amz-tt-comp-item .amz-tt-comp-item-price { font-weight: 700; color: #198754; white-space: nowrap; }
        .amz-tt-approve-btn, .amz-tt-push-btn { font-size: 11px; padding: 2px 8px; }
        #amz-tt-prompt-badge {
            cursor: pointer; display: inline-flex; align-items: center; gap: 5px;
        }
        #amz-tt-prompt-badge .amz-tt-prompt-badge-dot {
            width: 8px; height: 8px; border-radius: 50%; background: #fff;
            box-shadow: 0 0 0 1px rgba(255,255,255,0.4);
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Amz Titles',
        'sub_title'  => 'Amz titles overview',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <span id="amz-tt-total" class="badge bg-secondary">Total: —</span>
                        <span id="amz-tt-selected" class="badge bg-primary">Selected: 0</span>
                        <button type="button" id="amz-tt-refresh-btn" class="btn btn-sm btn-outline-primary" title="Reload table">
                            <i class="fa fa-refresh"></i>
                        </button>
                        <button type="button" id="amz-tt-pull-btn" class="btn btn-sm btn-warning text-dark"
                            title="Pull Amz titles for selected rows only → Current Title">
                            <i class="fas fa-cloud-download-alt me-1"></i> Pull Titles from Amz
                        </button>
                        <button type="button" id="amz-tt-prompt-badge" class="badge bg-primary border-0"
                            title="Edit AI analyze prompt">
                            <span class="amz-tt-prompt-badge-dot" aria-hidden="true"></span>
                            AI Prompt
                        </button>
                        <span class="text-muted small" id="amz-tt-status-line">Loading…</span>
                    </div>

                    <div id="amz-tt-wrap">
                        <div class="p-2 bg-light border rounded-top d-flex align-items-center gap-2 flex-wrap">
                            <input type="search" id="amz-tt-search" class="form-control form-control-sm"
                                placeholder="Search Parent / SKU / Title..." autocomplete="off" style="max-width: 320px;">
                            <select id="amz-tt-row-type" class="form-select form-select-sm" style="width: auto;"
                                title="Filter by row type">
                                <option value="all" selected>All</option>
                                <option value="sku">SKU</option>
                                <option value="parent">Parent</option>
                            </select>
                            <label class="small text-muted mb-0 d-flex align-items-center gap-1">
                                <input type="checkbox" id="amz-tt-inv-gt0" class="form-check-input m-0"> INV &gt; 0
                            </label>
                        </div>
                        <div id="amz-titles-table"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="amzTtPromptModal" tabindex="-1" aria-labelledby="amzTtPromptModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title mb-0" id="amzTtPromptModalLabel">
                        <i class="fas fa-wand-magic-sparkles me-1"></i> AI Analyze Prompt
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2">
                        This prompt is used when you click the magic wand on a SKU row.
                    </p>
                    <textarea id="amz-tt-ai-prompt" class="form-control" rows="8"
                        placeholder="Analyze the Title for Product in Link..."></textarea>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="small text-muted" id="amz-tt-ai-prompt-count">0 characters</span>
                        <button type="button" id="amz-tt-ai-prompt-reset" class="btn btn-sm btn-outline-secondary">Reset default</button>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="amz-tt-ai-prompt-save" class="btn btn-sm btn-primary">Save prompt</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="amzTtCompetitorsModal" tabindex="-1" aria-labelledby="amzTtCompetitorsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title mb-0" id="amzTtCompetitorsModalLabel">
                        <i class="fas fa-store me-1"></i> Competitors
                        <span class="text-muted fw-normal" id="amz-tt-comp-sku-label"></span>
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small text-muted" id="amz-tt-comp-summary">LMP Amz data</span>
                        <button type="button" id="amz-tt-comp-refresh" class="btn btn-sm btn-outline-secondary" title="Reload from LMP">
                            <i class="fa fa-refresh"></i> Reload
                        </button>
                    </div>
                    <div id="amz-tt-comp-list">
                        <div class="text-center text-muted py-4">Loading…</div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        let amzTtTable = null;
        const AMZ_TT_TITLE_SAVE_URL = @json(route('title.master.save'));
        const AMZ_TT_AI_ANALYZE_URL = @json(route('amz.titles.ai.analyze'));
        const AMZ_TT_NEG_SUGGEST_URL = @json(route('amz.titles.negatives.suggest'));
        const AMZ_TT_NEG_APPROVE_URL = @json(route('amz.titles.negatives.approve'));
        const AMZ_TT_PUSH_URL = @json(route('amazon.push.title'));
        const AMZ_TT_COMPETITORS_URL = @json(route('amazon.competitors.get'));
        const AMZ_TT_CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const AMZ_TT_TITLE170_MAX = 170;
        const AMZ_TT_TITLE170_MIN = 150;
        let amzTtCompContext = { sku: '', linked: [] };
        const AMZ_TT_PROMPT_KEY = 'amz_tt_ai_prompt_v4';
        const AMZ_TT_DEFAULT_PROMPT = @json($defaultAiPrompt);
        try {
            localStorage.removeItem('amz_tt_ai_prompt');
            localStorage.removeItem('amz_tt_ai_prompt_v2');
            localStorage.removeItem('amz_tt_ai_prompt_v3'); // drop mistakes/suggestions wording
        } catch (e) {}

        function amzTtEsc(s) {
            return String(s ?? '')
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        /** Saved Title 170 (product_master.title150) wins over Amz datasheet title — same as Title Master. */
        function amzTtGetTitle170Text(row) {
            if (!row) return '';
            const t = row.title150;
            if (t != null && String(t).trim() !== '') return String(t);
            const a = row.amazon_title;
            if (a != null && String(a).trim() !== '') return String(a);
            return '';
        }

        function amzTtSaveTitle170(sku, title150) {
            return fetch(AMZ_TT_TITLE_SAVE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': AMZ_TT_CSRF,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ sku: sku, title150: title150 }),
            }).then(function(r) {
                return r.json().then(function(body) {
                    if (!r.ok || !body || !body.success) {
                        throw new Error((body && body.message) ? body.message : 'Save failed');
                    }
                    return body;
                });
            });
        }

        function amzTtColumns() {
            return [
                {
                    formatter: 'rowSelection',
                    titleFormatter: 'rowSelection',
                    titleFormatterParams: {
                        rowRange: 'active', // header checkbox = all filtered rows (all pages)
                    },
                    hozAlign: 'center',
                    headerSort: false,
                    width: 44,
                    frozen: true,
                },
                {
                    title: 'Image',
                    field: 'image',
                    hozAlign: 'center',
                    headerSort: false,
                    width: 52,
                    frozen: true,
                    formatter: function(cell) {
                        const url = cell.getValue();
                        if (!url) return '<span class="text-muted">—</span>';
                        return '<img class="amz-tt-thumb" src="' + amzTtEsc(url) + '" alt="">';
                    },
                },
                { title: 'Parent', field: 'parent', width: 110, frozen: true },
                {
                    title: 'SKU',
                    field: 'sku',
                    width: 140,
                    frozen: true,
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const sku = cell.getValue() || '';
                        if (row.is_parent_summary) {
                            return '<span title="Parent summary row">' + amzTtEsc(sku) + '</span>';
                        }
                        return amzTtEsc(sku);
                    },
                },
                {
                    title: 'Link',
                    field: 'buyer_link',
                    hozAlign: 'center',
                    headerHozAlign: 'center',
                    headerSort: false,
                    width: 48,
                    frozen: true,
                    headerTooltip: 'Buyer link — https://www.amazon.com/dp/{asin}',
                    titleFormatter: function() {
                        return '<i class="fas fa-link" title="Buyer link"></i>';
                    },
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        if (row.is_parent_summary) return '';
                        const asin = String(row.asin || '').trim();
                        const href = String(row.buyer_link || '').trim()
                            || (asin ? ('https://www.amazon.com/dp/' + encodeURIComponent(asin)) : '');
                        if (!href) {
                            return '<span class="text-muted" title="No Amz ASIN">—</span>';
                        }
                        return '<a href="' + amzTtEsc(href) + '" target="_blank" rel="noopener noreferrer"'
                            + ' title="Buyer link — ASIN ' + amzTtEsc(asin) + '"'
                            + ' style="color:#198754;font-weight:600;text-decoration:none;"'
                            + ' onclick="event.stopPropagation();">'
                            + '<i class="fas fa-external-link-alt"></i></a>';
                    },
                },
                {
                    title: 'INV',
                    field: 'inv',
                    hozAlign: 'center',
                    width: 60,
                    frozen: true,
                    sorter: 'number',
                    formatter: function(cell) {
                        return Math.round(parseFloat(cell.getValue()) || 0).toLocaleString('en-US');
                    },
                },
                {
                    title: 'OV L30',
                    field: 'ov_l30',
                    hozAlign: 'center',
                    width: 70,
                    sorter: 'number',
                    formatter: function(cell) {
                        return Math.round(parseFloat(cell.getValue()) || 0).toLocaleString('en-US');
                    },
                },
                {
                    title: 'Dil',
                    field: 'dil_pct',
                    hozAlign: 'center',
                    width: 55,
                    sorter: 'number',
                    headerTooltip: 'Dil% = OV L30 / INV × 100',
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const inv = parseFloat(row.inv) || 0;
                        const ov = parseFloat(row.ov_l30) || 0;
                        if (inv <= 0) return '<span style="color:#6c757d;">0%</span>';
                        const dil = (ov / inv) * 100;
                        let color = '#e83e8c';
                        if (dil < 16.66) color = '#a00211';
                        else if (dil < 25) color = '#ffc107';
                        else if (dil < 50) color = '#28a745';
                        return '<span style="color:' + color + ';font-weight:600;">' + Math.round(dil) + '%</span>';
                    },
                },
                {
                    title: 'Amz L30',
                    field: 'amz_l30',
                    hozAlign: 'center',
                    width: 75,
                    sorter: 'number',
                    headerTooltip: 'Amz units ordered L30 (A L30)',
                    formatter: function(cell) {
                        return Math.round(parseFloat(cell.getValue()) || 0).toLocaleString('en-US');
                    },
                },
                {
                    title: 'Competitors',
                    field: 'lmp_price',
                    width: 100,
                    hozAlign: 'center',
                    headerHozAlign: 'center',
                    sorter: 'number',
                    headerTooltip: 'LMP Amz competitors (amazon_sku_competitors) — lowest price + View list',
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        if (row.is_parent_summary) return '<span class="text-muted">—</span>';
                        const count = parseInt(row.lmp_count, 10) || 0;
                        const price = cell.getValue();
                        if (!price && count === 0) {
                            return '<span class="text-muted">N/A</span>';
                        }
                        let html = '<div class="amz-tt-comp-cell">';
                        if (price != null && price !== '' && !isNaN(parseFloat(price))) {
                            html += '<span class="amz-tt-comp-price">$' + parseFloat(price).toFixed(2) + '</span>';
                        }
                        if (count > 0) {
                            html += '<a href="#" class="amz-tt-comp-view" data-sku="' + amzTtEsc(row.sku || '') + '"'
                                + ' title="View LMP competitors">'
                                + '<i class="fa fa-eye"></i> View ' + count + '</a>';
                        }
                        html += '</div>';
                        return html;
                    },
                    cellClick: function(e, cell) {
                        const a = e.target.closest && e.target.closest('.amz-tt-comp-view');
                        if (!a) return;
                        e.preventDefault();
                        e.stopPropagation();
                        const row = cell.getRow().getData();
                        amzTtOpenCompetitorsModal(row.sku, row.linked_lmp_skus || []);
                    },
                },
                {
                    title: 'Current Title',
                    field: 'title150',
                    width: 360,
                    minWidth: 200,
                    headerSort: true,
                    headerTooltip: 'Current Title — product_master.title150 (same as Title Master). Click to edit.',
                    editor: 'textarea',
                    editorParams: {
                        elementAttributes: { maxlength: String(AMZ_TT_TITLE170_MAX) },
                    },
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const saved = row.title150 != null && String(row.title150).trim() !== '';
                        const text = amzTtGetTitle170Text(row);
                        if (!text) {
                            return '<span class="text-muted" title="Click to edit">—</span>';
                        }
                        return '<div class="amz-tt-title170' + (saved ? '' : ' is-fallback') + '" title="'
                            + amzTtEsc(text) + (saved ? '' : ' (from Amz listing — save to Title Master)') + '">'
                            + amzTtEsc(text) + '</div>'
                            + (saved ? '' : '<div class="amz-tt-title170-meta">listing</div>');
                    },
                    sorter: function(a, b, aRow, bRow) {
                        const ta = amzTtGetTitle170Text(aRow.getData()).toLowerCase();
                        const tb = amzTtGetTitle170Text(bRow.getData()).toLowerCase();
                        return ta.localeCompare(tb);
                    },
                    editable: true,
                },
                {
                    title: 'Char Count',
                    field: 'title_char_count',
                    width: 80,
                    hozAlign: 'center',
                    headerHozAlign: 'center',
                    headerSort: true,
                    headerTooltip: 'Character count of Current Title (red if over ' + AMZ_TT_TITLE170_MAX + ')',
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const text = amzTtGetTitle170Text(row);
                        if (!text) return '<span class="text-muted">0</span>';
                        const len = Array.from(text).length;
                        const over = len > AMZ_TT_TITLE170_MAX;
                        return '<span class="amz-tt-char-count' + (over ? ' over' : '') + '" title="'
                            + len + ' / ' + AMZ_TT_TITLE170_MAX + '">'
                            + len + '</span>';
                    },
                    sorter: function(a, b, aRow, bRow) {
                        const la = Array.from(amzTtGetTitle170Text(aRow.getData())).length;
                        const lb = Array.from(amzTtGetTitle170Text(bRow.getData())).length;
                        return la - lb;
                    },
                },
                {
                    title: 'Overall Score',
                    field: 'ai_overall_score',
                    width: 90,
                    hozAlign: 'center',
                    sorter: 'number',
                    headerTooltip: 'AI overall score (0–100)',
                    formatter: function(cell) { return amzTtFormatScore(cell.getValue(), '#6f42c1'); },
                },
                {
                    title: 'AI',
                    field: 'ai_wand',
                    width: 48,
                    hozAlign: 'center',
                    headerHozAlign: 'center',
                    headerSort: false,
                    headerTooltip: 'AI analyze this SKU title. Edit prompt via AI Prompt badge on top.',
                    titleFormatter: function() {
                        return '<i class="fas fa-wand-magic-sparkles" title="AI Magic Wand"></i>';
                    },
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        // Parent rows: no wand — analyze per SKU only
                        if (row.is_parent_summary) {
                            return '<span class="text-muted">—</span>';
                        }
                        const busy = !!row.ai_busy;
                        return '<div class="amz-tt-ai-cell">'
                            + '<button type="button" class="amz-tt-wand-btn" data-sku="' + amzTtEsc(row.sku || '') + '"'
                            + (busy ? ' disabled' : '')
                            + ' title="Analyze this SKU title with AI">'
                            + (busy ? '<i class="fa fa-spinner fa-spin"></i>' : '<i class="fas fa-wand-magic-sparkles"></i>')
                            + '</button></div>';
                    },
                    cellClick: function(e, cell) {
                        e.stopPropagation();
                        if (e.target.closest && e.target.closest('.amz-tt-wand-btn')) {
                            amzTtRunAiAnalyze(cell.getRow());
                        }
                    },
                },
                {
                    title: 'Top Keywords',
                    field: 'top_keywords',
                    width: 220,
                    minWidth: 160,
                    headerSort: false,
                    headerTooltip: 'Top 20 Amz Ads keywords (L30 impressions). Ticked when the keyword appears in AI Title.',
                    formatter: function(cell) {
                        return amzTtFormatTopKeywords(cell.getRow().getData());
                    },
                },
                {
                    title: 'Negative KW',
                    field: 'negative_keywords',
                    width: 240,
                    minWidth: 180,
                    headerSort: false,
                    headerTooltip: 'Top 30 AI negative keywords. Checked by default — uncheck to skip. Approve pushes checked terms to Amz Ads KW(-).',
                    formatter: function(cell) {
                        return amzTtFormatNegativeKeywords(cell.getRow().getData());
                    },
                    cellClick: function(e, cell) {
                        const t = e.target;
                        if (!t) return;
                        if (t.classList && t.classList.contains('amz-tt-neg-check')) {
                            e.stopPropagation();
                            amzTtToggleNegativeKeyword(cell.getRow(), parseInt(t.getAttribute('data-idx'), 10), t.checked);
                            return;
                        }
                        if (t.closest && t.closest('.amz-tt-neg-load-btn')) {
                            e.stopPropagation();
                            amzTtLoadNegativeKeywords(cell.getRow(), false);
                            return;
                        }
                        if (t.closest && t.closest('.amz-tt-neg-reload-btn')) {
                            e.stopPropagation();
                            amzTtLoadNegativeKeywords(cell.getRow(), true);
                            return;
                        }
                        if (t.closest && t.closest('.amz-tt-neg-approve-btn')) {
                            e.stopPropagation();
                            amzTtApproveNegativeKeywords(cell.getRow());
                        }
                    },
                },
                {
                    title: 'Vis %',
                    field: 'ai_visibility_score',
                    width: 60,
                    hozAlign: 'center',
                    sorter: 'number',
                    headerTooltip: 'AI visibility score (0–100)',
                    formatter: function(cell) { return amzTtFormatScoreYellow(cell.getValue()); },
                },
                {
                    title: 'Conv %',
                    field: 'ai_conversion_score',
                    width: 65,
                    hozAlign: 'center',
                    sorter: 'number',
                    headerTooltip: 'AI conversion score (0–100)',
                    formatter: function(cell) { return amzTtFormatScoreYellow(cell.getValue()); },
                },
                {
                    title: 'AI Title',
                    field: 'ai_suggested_title',
                    width: 280,
                    minWidth: 180,
                    headerTooltip: 'AI suggested title (target 150–170 chars) — Approve to apply to Current Title',
                    formatter: function(cell) {
                        const text = cell.getValue();
                        if (!text) return '<span class="text-muted">—</span>';
                        const len = Array.from(String(text)).length;
                        const ok = len >= AMZ_TT_TITLE170_MIN && len <= AMZ_TT_TITLE170_MAX;
                        const over = len > AMZ_TT_TITLE170_MAX;
                        const under = len > 0 && len < AMZ_TT_TITLE170_MIN;
                        return '<div class="amz-tt-title170" title="' + amzTtEsc(text) + '">' + amzTtEsc(text) + '</div>'
                            + '<div class="amz-tt-title170-meta' + ((over || under) ? ' over' : '') + '">'
                            + len + '/' + AMZ_TT_TITLE170_MAX
                            + (ok ? '' : (under ? ' · need ≥' + AMZ_TT_TITLE170_MIN : ' · over'))
                            + '</div>';
                    },
                },
                {
                    title: 'AI Score',
                    field: 'ai_title_score',
                    width: 80,
                    hozAlign: 'center',
                    headerHozAlign: 'center',
                    sorter: 'number',
                    headerTooltip: 'Combined AI Title score (0–100) — visibility + conversion merged',
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        if (!row.ai_suggested_title) return '<span class="text-muted">—</span>';
                        return amzTtFormatScore(cell.getValue(), '#0d6efd');
                    },
                },
                {
                    title: 'Approve',
                    field: 'ai_approved',
                    width: 90,
                    hozAlign: 'center',
                    headerSort: false,
                    headerTooltip: 'Apply AI Title to Current Title (Title Master sync)',
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        if (row.is_parent_summary || !row.ai_suggested_title) {
                            return '<span class="text-muted">—</span>';
                        }
                        if (row.ai_approved) {
                            return '<span class="badge bg-success">Approved</span>';
                        }
                        return '<button type="button" class="btn btn-sm btn-outline-success amz-tt-approve-btn">Approve</button>';
                    },
                    cellClick: function(e, cell) {
                        if (!(e.target.closest && e.target.closest('.amz-tt-approve-btn'))) return;
                        e.stopPropagation();
                        amzTtApproveAiTitle(cell.getRow());
                    },
                },
                {
                    title: 'Push',
                    field: 'ai_pushed',
                    width: 85,
                    hozAlign: 'center',
                    headerSort: false,
                    headerTooltip: 'Push Current Title to Amz via SP-API',
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        if (row.is_parent_summary) {
                            return '<span class="text-muted">—</span>';
                        }
                        const title = amzTtGetTitle170Text(row);
                        if (!title) return '<span class="text-muted">—</span>';
                        if (row.ai_pushed) {
                            return '<span class="badge bg-primary">Pushed</span>';
                        }
                        return '<button type="button" class="btn btn-sm btn-outline-primary amz-tt-push-btn"'
                            + (row.ai_pushing ? ' disabled' : '') + '>'
                            + (row.ai_pushing ? '<i class="fa fa-spinner fa-spin"></i>' : 'Push')
                            + '</button>';
                    },
                    cellClick: function(e, cell) {
                        if (!(e.target.closest && e.target.closest('.amz-tt-push-btn'))) return;
                        e.stopPropagation();
                        amzTtPushToAmazon(cell.getRow());
                    },
                },
            ];
        }

        function amzTtGetPrompt() {
            try {
                const saved = localStorage.getItem(AMZ_TT_PROMPT_KEY);
                if (saved != null && String(saved).trim() !== '') return String(saved);
            } catch (e) {}
            return AMZ_TT_DEFAULT_PROMPT;
        }

        function amzTtSetPrompt(text) {
            try { localStorage.setItem(AMZ_TT_PROMPT_KEY, text); } catch (e) {}
        }

        function amzTtOpenPromptModal() {
            const ta = document.getElementById('amz-tt-ai-prompt');
            if (ta) {
                ta.value = amzTtGetPrompt();
                const countEl = document.getElementById('amz-tt-ai-prompt-count');
                if (countEl) countEl.textContent = ta.value.length.toLocaleString() + ' characters';
            }
            const modalEl = document.getElementById('amzTtPromptModal');
            if (modalEl && typeof bootstrap !== 'undefined') {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
        }

        function amzTtFormatScore(val, color) {
            if (val == null || val === '' || isNaN(parseFloat(val))) {
                return '<span class="text-muted">—</span>';
            }
            const n = Math.round(parseFloat(val));
            let c = color || '#6c757d';
            if (n < 40) c = '#a00211';
            else if (n < 70) c = '#ffc107';
            return '<span class="amz-tt-score" style="color:' + c + ';">' + n + '%</span>';
        }

        function amzTtFormatScoreYellow(val) {
            if (val == null || val === '' || isNaN(parseFloat(val))) {
                return '<span class="text-muted">—</span>';
            }
            const n = Math.round(parseFloat(val));
            return '<span class="amz-tt-score-yellow">' + n + '%</span>';
        }

        function amzTtKeywordUsedInAiTitle(keyword, aiTitle) {
            const kw = String(keyword || '').trim().toLowerCase();
            const title = String(aiTitle || '').trim().toLowerCase();
            if (!kw || !title) return false;
            // Phrase match (case-insensitive). Collapse extra spaces.
            const normKw = kw.replace(/\s+/g, ' ');
            const normTitle = title.replace(/\s+/g, ' ');
            return normTitle.indexOf(normKw) !== -1;
        }

        function amzTtFormatTopKeywords(row) {
            if (!row) return '<span class="text-muted">—</span>';
            const list = Array.isArray(row.top_keywords) ? row.top_keywords : [];
            if (!list.length) return '<span class="text-muted">—</span>';
            const aiTitle = String(row.ai_suggested_title || '').trim();
            let used = 0;
            const html = list.map(function(item, idx) {
                const kw = (item && typeof item === 'object') ? String(item.keyword || '') : String(item || '');
                const imp = (item && typeof item === 'object' && item.impressions != null)
                    ? Number(item.impressions)
                    : null;
                if (!kw.trim()) return '';
                const isUsed = amzTtKeywordUsedInAiTitle(kw, aiTitle);
                if (isUsed) used++;
                return '<li class="' + (isUsed ? 'is-used' : '') + '">'
                    + '<input type="checkbox" class="form-check-input amz-tt-kw-check" disabled'
                    + (isUsed ? ' checked' : '')
                    + ' title="' + (isUsed ? 'Used in AI Title' : 'Not in AI Title') + '">'
                    + '<span class="amz-tt-kw-text" title="'
                    + amzTtEsc(kw) + (imp != null ? (' · ' + imp.toLocaleString() + ' impr') : '')
                    + '">' + (idx + 1) + '. ' + amzTtEsc(kw)
                    + (imp != null ? (' <span class="amz-tt-kw-meta">(' + imp.toLocaleString() + ')</span>') : '')
                    + '</span></li>';
            }).join('');
            return '<ul class="amz-tt-kw-list">' + html + '</ul>'
                + '<div class="amz-tt-kw-meta mt-1">' + used + '/' + list.length + ' in AI Title</div>';
        }

        function amzTtNormalizeNegItems(list) {
            if (!Array.isArray(list)) return [];
            return list.map(function(item) {
                if (item && typeof item === 'object') {
                    const keyword = String(item.keyword || '').trim();
                    if (!keyword) return null;
                    return {
                        keyword: keyword,
                        checked: item.checked == null ? true : !!item.checked,
                        pushed: !!item.pushed,
                    };
                }
                const keyword = String(item || '').trim();
                if (!keyword) return null;
                return { keyword: keyword, checked: true, pushed: false };
            }).filter(Boolean).slice(0, 30);
        }

        function amzTtFormatNegativeKeywords(row) {
            if (!row) return '<span class="text-muted">—</span>';
            if (row.neg_busy) {
                return '<div class="text-muted small"><i class="fa fa-spinner fa-spin me-1"></i> Working…</div>';
            }
            const list = amzTtNormalizeNegItems(row.negative_keywords);
            let body = '';
            if (!list.length) {
                body = '<div class="text-muted small mb-1">No negatives loaded yet.</div>';
            } else {
                let checkedN = 0;
                body = '<ul class="amz-tt-neg-list">' + list.map(function(item, idx) {
                    if (item.checked) checkedN++;
                    return '<li class="' + (item.checked ? 'is-checked' : '') + (item.pushed ? ' is-pushed' : '') + '">'
                        + '<input type="checkbox" class="form-check-input amz-tt-neg-check" data-idx="' + idx + '"'
                        + (item.checked ? ' checked' : '')
                        + ' title="Include when Approve pushes to Amz KW(-)">'
                        + '<span class="amz-tt-neg-text">' + (idx + 1) + '. ' + amzTtEsc(item.keyword)
                        + (item.pushed ? ' <span class="badge bg-success" style="font-size:9px;">pushed</span>' : '')
                        + '</span></li>';
                }).join('') + '</ul>'
                + '<div class="amz-tt-kw-meta mt-1">' + checkedN + '/' + list.length + ' selected · max 30</div>';
            }
            const hasList = list.length > 0;
            const checkedCount = list.filter(function(i) { return i.checked && !i.pushed; }).length;
            return body
                + '<div class="amz-tt-neg-actions">'
                + (hasList
                    ? '<button type="button" class="btn btn-outline-secondary amz-tt-neg-reload-btn" title="Regenerate Top 30"><i class="fa fa-refresh"></i></button>'
                    : '<button type="button" class="btn btn-outline-primary amz-tt-neg-load-btn">Load Top 30</button>')
                + '<button type="button" class="btn btn-success amz-tt-neg-approve-btn"'
                + (checkedCount ? '' : ' disabled')
                + ' title="Push checked negatives to Amz Ads">'
                + 'Approve</button>'
                + '</div>';
        }

        function amzTtToggleNegativeKeyword(row, idx, checked) {
            if (!row || isNaN(idx)) return;
            const data = row.getData();
            const items = amzTtNormalizeNegItems(data.negative_keywords);
            if (!items[idx]) return;
            items[idx].checked = !!checked;
            row.update({ negative_keywords: items });
            try { row.reformat(); } catch (e) {}
        }

        function amzTtApplyNegativesToParentRows(parent, items) {
            if (!amzTtTable || !parent) return;
            (amzTtTable.getRows() || []).forEach(function(r) {
                const d = r.getData();
                if (d && String(d.parent || '').trim() === String(parent).trim()) {
                    r.update({ negative_keywords: items, neg_busy: false });
                    try { r.reformat(); } catch (e) {}
                }
            });
        }

        function amzTtLoadNegativeKeywords(row, force) {
            if (!row) return;
            const data = row.getData();
            const parent = String(data.parent || '').trim();
            if (!parent) {
                alert('Parent is required to load negative keywords.');
                return;
            }
            const sku = data.is_parent_summary ? '' : String(data.sku || '');
            row.update({ neg_busy: true });
            try { row.reformat(); } catch (e) {}
            $('#amz-tt-status-line').text('Loading Top 30 Negative KW for ' + parent + '…');

            fetch(AMZ_TT_NEG_SUGGEST_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': AMZ_TT_CSRF,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ parent: parent, sku: sku, force: !!force }),
            })
                .then(async function(r) {
                    let body = null;
                    try { body = await r.json(); } catch (e) { body = null; }
                    if (!r.ok || !body || !body.success) {
                        throw new Error((body && body.message) ? body.message : ('Load failed (HTTP ' + r.status + ')'));
                    }
                    return body;
                })
                .then(function(body) {
                    const items = amzTtNormalizeNegItems(body.negative_keywords || []);
                    amzTtApplyNegativesToParentRows(parent, items);
                    $('#amz-tt-status-line').text(
                        'Negative KW · ' + parent + ' · ' + items.length
                        + (body.cached ? ' (cached)' : ' (AI)')
                        + ' · uncheck any, then Approve to push'
                    );
                })
                .catch(function(err) {
                    row.update({ neg_busy: false });
                    try { row.reformat(); } catch (e) {}
                    $('#amz-tt-status-line').text('Negative KW failed: ' + (err && err.message ? err.message : 'error'));
                    alert('Negative KW failed: ' + (err && err.message ? err.message : 'error'));
                });
        }

        function amzTtApproveNegativeKeywords(row) {
            if (!row) return;
            const data = row.getData();
            const parent = String(data.parent || '').trim();
            if (!parent) {
                alert('Parent is required to approve negatives.');
                return;
            }
            const selected = amzTtNormalizeNegItems(data.negative_keywords)
                .filter(function(i) { return i.checked && !i.pushed; })
                .map(function(i) { return i.keyword; });
            if (!selected.length) {
                alert('Check at least one Negative KW to approve/push.');
                return;
            }
            if (!confirm(
                'Push ' + selected.length + ' negative keyword(s) to Amz Ads for parent:\n'
                + parent + '\n\nMatch type: NEGATIVE_PHRASE'
            )) {
                return;
            }

            row.update({ neg_busy: true });
            try { row.reformat(); } catch (e) {}
            $('#amz-tt-status-line').text('Approving ' + selected.length + ' Negative KW → Amz…');

            fetch(AMZ_TT_NEG_APPROVE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': AMZ_TT_CSRF,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    parent: parent,
                    sku: data.is_parent_summary ? '' : data.sku,
                    keywords: selected,
                    match_type: 'PHRASE',
                }),
            })
                .then(async function(r) {
                    let body = null;
                    try { body = await r.json(); } catch (e) { body = null; }
                    if (!r.ok || !body || !body.success) {
                        throw new Error((body && body.message) ? body.message : ('Approve failed (HTTP ' + r.status + ')'));
                    }
                    return body;
                })
                .then(function(body) {
                    const items = amzTtNormalizeNegItems(body.negative_keywords || data.negative_keywords);
                    amzTtApplyNegativesToParentRows(parent, items);
                    $('#amz-tt-status-line').text(
                        'Negative KW pushed · ' + parent
                        + ' · added ' + (body.added != null ? body.added : '—')
                        + (body.duplicates ? (' · dup ' + body.duplicates) : '')
                        + (body.failed ? (' · fail ' + body.failed) : '')
                    );
                })
                .catch(function(err) {
                    row.update({ neg_busy: false });
                    try { row.reformat(); } catch (e) {}
                    $('#amz-tt-status-line').text('Approve Negative KW failed: ' + (err && err.message ? err.message : 'error'));
                    alert('Approve Negative KW failed: ' + (err && err.message ? err.message : 'error'));
                });
        }

        function amzTtOpenCompetitorsModal(sku, linkedSkus) {
            sku = String(sku || '').trim();
            if (!sku || sku.toUpperCase().indexOf('PARENT') === 0) return;
            amzTtCompContext = {
                sku: sku,
                linked: Array.isArray(linkedSkus) ? linkedSkus : [],
            };
            $('#amz-tt-comp-sku-label').text('· ' + sku);
            $('#amz-tt-comp-summary').text('Loading LMP Amz competitors…');
            $('#amz-tt-comp-list').html('<div class="text-center text-muted py-4"><i class="fa fa-spinner fa-spin me-1"></i> Loading…</div>');
            const modalEl = document.getElementById('amzTtCompetitorsModal');
            if (modalEl && typeof bootstrap !== 'undefined') {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
            amzTtLoadCompetitors();
        }

        function amzTtLoadCompetitors() {
            const sku = amzTtCompContext.sku;
            if (!sku) return;
            $.ajax({
                url: AMZ_TT_COMPETITORS_URL,
                method: 'GET',
                data: {
                    sku: sku,
                    linked_lmp_skus: amzTtCompContext.linked || [],
                },
                traditional: true,
            })
                .done(function(resp) {
                    if (!resp || !resp.success) {
                        $('#amz-tt-comp-list').html('<div class="text-danger py-3">Failed to load competitors.</div>');
                        return;
                    }
                    const list = resp.competitors || [];
                    const lowest = resp.lowest_price;
                    $('#amz-tt-comp-summary').text(
                        list.length + ' competitor(s)'
                        + (lowest != null ? (' · LMP $' + parseFloat(lowest).toFixed(2)) : '')
                        + ' · LMP Amz data'
                    );
                    if (!list.length) {
                        $('#amz-tt-comp-list').html('<div class="text-muted py-3">No competitors found for this SKU.</div>');
                        return;
                    }
                    let html = '';
                    list.forEach(function(c, i) {
                        const title = c.product_title || c.title || '—';
                        const price = (c.price != null && !isNaN(parseFloat(c.price)))
                            ? ('$' + parseFloat(c.price).toFixed(2))
                            : '—';
                        const asin = c.asin || '';
                        const link = c.product_link || c.link
                            || (asin ? ('https://www.amazon.com/dp/' + asin) : '');
                        const img = c.image || (asin ? ('https://m.media-amazon.com/images/P/' + asin + '._AC_SL160_.jpg') : '');
                        const ignored = !!c.ignored;
                        html += '<div class="amz-tt-comp-item' + (ignored ? ' opacity-50' : '') + '">';
                        html += img
                            ? ('<img src="' + amzTtEsc(img) + '" alt="">')
                            : '<div style="width:48px;height:48px;background:#f1f3f5;border-radius:4px;"></div>';
                        html += '<div class="amz-tt-comp-meta">';
                        html += '<div class="fw-semibold">' + (i + 1) + '. ' + amzTtEsc(title) + '</div>';
                        html += '<div class="text-muted small">';
                        if (asin) {
                            html += link
                                ? ('<a href="' + amzTtEsc(link) + '" target="_blank" rel="noopener">' + amzTtEsc(asin) + '</a>')
                                : amzTtEsc(asin);
                            html += ' · ';
                        }
                        if (c.seller_name) html += amzTtEsc(c.seller_name) + ' · ';
                        if (c.rating != null) html += parseFloat(c.rating).toFixed(1) + '★ ';
                        if (c.reviews != null) html += '(' + Number(c.reviews).toLocaleString() + ' reviews)';
                        if (ignored) html += ' · <span class="badge bg-secondary">ignored</span>';
                        html += '</div></div>';
                        html += '<div class="amz-tt-comp-item-price">' + price + '</div>';
                        html += '</div>';
                    });
                    $('#amz-tt-comp-list').html(html);

                    // Refresh table LMP cell from API lowest
                    if (amzTtTable && lowest != null) {
                        (amzTtTable.getRows() || []).forEach(function(r) {
                            const d = r.getData();
                            if (d && d.sku === sku) {
                                r.update({
                                    lmp_price: parseFloat(lowest),
                                    lmp_count: list.length,
                                });
                            }
                        });
                    }
                })
                .fail(function(xhr) {
                    const msg = (xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message))
                        || 'Could not load competitors';
                    $('#amz-tt-comp-list').html('<div class="text-danger py-3">' + amzTtEsc(msg) + '</div>');
                });
        }

        /** SKU row only — parent wand removed. */
        function amzTtResolveAnalysisTarget(rowData) {
            if (!rowData || rowData.is_parent_summary) return null;
            const sku = String(rowData.sku || '').trim();
            if (!sku || sku.toUpperCase().indexOf('PARENT') === 0) return null;
            return {
                rowSku: sku,
                targetSku: sku,
                buyerLink: rowData.buyer_link || '',
                title: amzTtGetTitle170Text(rowData),
                parent: rowData.parent || '',
            };
        }

        function amzTtRunAiAnalyze(row) {
            if (!row) return;
            const data = row.getData();
            if (data.is_parent_summary) {
                alert('Use the wand on a SKU row, not the parent.');
                return;
            }
            const target = amzTtResolveAnalysisTarget(data);
            if (!target || !target.targetSku) {
                alert('No SKU found for AI analysis.');
                return;
            }
            if (!target.buyerLink && !target.title && !target.targetSku) {
                alert('Need a SKU, buyer link, or Current Title to analyze.');
                return;
            }

            row.update({ ai_busy: true });
            $('#amz-tt-status-line').text('AI analyzing ' + target.targetSku + '…');

            fetch(AMZ_TT_AI_ANALYZE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': AMZ_TT_CSRF,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    sku: target.targetSku,
                    buyer_link: target.buyerLink,
                    current_title: target.title,
                    parent: target.parent,
                    prompt: amzTtGetPrompt(),
                }),
            })
                .then(async function(r) {
                    let body = null;
                    try { body = await r.json(); } catch (e) { body = null; }
                    if (!r.ok || !body || !body.success) {
                        let msg = (body && body.message) ? body.message : ('AI analyze failed (HTTP ' + r.status + ')');
                        if (body && body.errors && typeof body.errors === 'object') {
                            const first = Object.values(body.errors).flat().find(Boolean);
                            if (first) msg = String(first);
                        }
                        throw new Error(msg);
                    }
                    return body;
                })
                .then(function(body) {
                    row.update({
                        ai_busy: false,
                        ai_target_sku: body.sku || target.targetSku,
                        ai_visibility_score: body.visibility_score,
                        ai_conversion_score: body.conversion_score,
                        ai_overall_score: body.overall_score,
                        ai_suggested_title: body.suggested_title || null,
                        ai_title_score: body.ai_title_score,
                        ai_approved: false,
                        ai_pushed: false,
                    });
                    try { row.reformat(); } catch (e) {}
                    const aiLen = body.char_count != null
                        ? body.char_count
                        : (body.suggested_title ? Array.from(String(body.suggested_title)).length : 0);
                    $('#amz-tt-status-line').text(
                        'AI done · ' + (body.sku || target.targetSku)
                        + ' · Score ' + (body.overall_score != null ? body.overall_score + '%' : '—')
                        + ' · AI Title ' + aiLen + ' chars'
                        + (body.length_ok ? ' (OK 150–170)' : (aiLen && aiLen < AMZ_TT_TITLE170_MIN ? ' (under 150 — re-run wand)' : ''))
                    );
                })
                .catch(function(err) {
                    row.update({ ai_busy: false });
                    $('#amz-tt-status-line').text('AI failed: ' + (err && err.message ? err.message : 'error'));
                    alert('AI analyze failed: ' + (err && err.message ? err.message : 'error'));
                });
        }

        function amzTtApproveAiTitle(row) {
            const data = row.getData();
            if (data.is_parent_summary) {
                alert('Approve on a SKU row, not the parent.');
                return;
            }
            const suggested = String(data.ai_suggested_title || '').trim();
            if (!suggested) return;

            const saveSku = String(data.sku || '').trim();
            if (!saveSku || saveSku.toUpperCase().indexOf('PARENT') === 0) {
                alert('Approve needs a SKU.');
                return;
            }

            $('#amz-tt-status-line').text('Approving AI title for ' + saveSku + '…');
            amzTtSaveTitle170(saveSku, suggested)
                .then(function() {
                    const charCount = Array.from(String(suggested)).length;
                    row.update({
                        ai_approved: true,
                        title150: suggested,
                        title_char_count: charCount,
                    });
                    $('#amz-tt-status-line').text('Approved · Current Title updated · synced with Title Master');
                })
                .catch(function(err) {
                    alert('Approve failed: ' + (err && err.message ? err.message : 'error'));
                });
        }

        function amzTtPushToAmazon(row) {
            const data = row.getData();
            if (data.is_parent_summary) {
                alert('Push on a SKU row, not the parent.');
                return;
            }
            const pushSku = String(data.sku || '').trim();
            if (!pushSku || pushSku.toUpperCase().indexOf('PARENT') === 0) {
                alert('Push needs a SKU.');
                return;
            }

            const title = String(amzTtGetTitle170Text(data) || data.ai_suggested_title || '').trim();
            if (!title) {
                alert('No title to push. Approve an AI title or set Current Title first.');
                return;
            }
            if (!confirm('Push title to Amz for SKU:\n' + pushSku + '\n\n' + title)) return;

            row.update({ ai_pushing: true });
            $('#amz-tt-status-line').text('Pushing title to Amz…');

            fetch(AMZ_TT_PUSH_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': AMZ_TT_CSRF,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ sku: pushSku, title: title, asin: data.asin || null }),
            })
                .then(function(r) {
                    return r.json().then(function(body) {
                        if (!r.ok || !body || !body.success) {
                            throw new Error((body && body.message) ? body.message : 'Push failed');
                        }
                        return body;
                    });
                })
                .then(function(body) {
                    row.update({ ai_pushing: false, ai_pushed: true });
                    $('#amz-tt-status-line').text(body.message || ('Pushed to Amz · ' + pushSku));
                })
                .catch(function(err) {
                    row.update({ ai_pushing: false });
                    $('#amz-tt-status-line').text('Push failed: ' + (err && err.message ? err.message : 'error'));
                    alert('Push failed: ' + (err && err.message ? err.message : 'error'));
                });
        }

        function amzTtUpdateCounts() {
            if (!amzTtTable) return;
            const shown = amzTtTable.getDataCount('active');
            const total = amzTtTable.getDataCount();
            $('#amz-tt-total').text('Total: ' + shown.toLocaleString() + (shown !== total ? ' / ' + total.toLocaleString() : ''));
            $('#amz-tt-selected').text('Selected: ' + amzTtTable.getSelectedData().length);
        }

        function amzTtApplyFilters() {
            if (!amzTtTable) return;
            const q = ($('#amz-tt-search').val() || '').toString().trim().toLowerCase();
            const invGt0 = $('#amz-tt-inv-gt0').is(':checked');
            const rowType = ($('#amz-tt-row-type').val() || 'all').toString();
            amzTtTable.setFilter(function(data) {
                const isParent = !!(data && data.is_parent_summary);
                if (rowType === 'sku' && isParent) return false;
                if (rowType === 'parent' && !isParent) return false;
                if (invGt0 && !(parseFloat(data.inv) > 0)) return false;
                if (!q) return true;
                const hay = [data.parent, data.sku, data.title150, data.amazon_title]
                    .map(v => String(v || '').toLowerCase()).join(' ');
                return hay.indexOf(q) !== -1;
            });
            amzTtUpdateCounts();
        }

        function initAmzTitlesTable() {
            amzTtTable = new Tabulator('#amz-titles-table', {
                height: '70vh',
                layout: 'fitDataStretch',
                placeholder: 'Loading…',
                // true = unlimited multi-select via checkboxes (do NOT use rangeMode "click"
                // — that forces Ctrl-click for non-contiguous rows and breaks checkbox multi-add)
                selectableRows: true,
                selectableRowsRollingSelection: true,
                selectableRowsPersistence: true,
                pagination: true,
                paginationSize: 100,
                paginationSizeSelector: [50, 100, 250, 500],
                ajaxURL: @json(route('amz.titles.data')),
                ajaxConfig: 'GET',
                ajaxResponse: function(url, params, response) {
                    if (response && response.meta && response.meta.refreshed_at) {
                        const m = response.meta;
                        $('#amz-tt-status-line').text(
                            'Loaded · ' + m.refreshed_at
                            + ' · SKUs: ' + (m.sku_count || 0).toLocaleString()
                            + ' · Parents: ' + (m.parent_count || 0).toLocaleString()
                        );
                    }
                    return (response && response.data) ? response.data : [];
                },
                columns: amzTtColumns(),
                rowFormatter: function(row) {
                    const el = row.getElement();
                    if (!el) return;
                    if (row.getData().is_parent_summary) el.classList.add('amz-tt-parent-row');
                    else el.classList.remove('amz-tt-parent-row');
                },
            });

            amzTtTable.on('dataProcessed', amzTtUpdateCounts);
            amzTtTable.on('rowSelectionChanged', amzTtUpdateCounts);
            amzTtTable.on('pageLoaded', amzTtUpdateCounts);

            amzTtTable.on('cellEditing', function(cell) {
                if (cell.getField() !== 'title150') return;
                // Prefill editor with effective Title 170 (saved or Amazon fallback).
                const row = cell.getRow().getData();
                const effective = amzTtGetTitle170Text(row);
                if ((cell.getValue() == null || String(cell.getValue()).trim() === '') && effective) {
                    cell.setValue(effective, true);
                }
            });

            amzTtTable.on('cellEdited', function(cell) {
                if (cell.getField() !== 'title150') return;
                const row = cell.getRow().getData();
                const sku = String(row.sku || '').trim();
                if (!sku) return;

                let value = cell.getValue();
                value = value == null ? '' : String(value).replace(/\u00a0/g, ' ').trim();
                if (Array.from(value).length > AMZ_TT_TITLE170_MAX) {
                    value = Array.from(value).slice(0, AMZ_TT_TITLE170_MAX).join('');
                }

                const prev = cell.getOldValue();
                $('#amz-tt-status-line').text('Saving Current Title…');

                amzTtSaveTitle170(sku, value)
                    .then(function() {
                        cell.getRow().update({
                            title150: value === '' ? null : value,
                            title_char_count: Array.from(value).length,
                        });
                        $('#amz-tt-status-line').text('Current Title saved · synced with Title Master');
                    })
                    .catch(function(err) {
                        cell.setValue(prev, true);
                        $('#amz-tt-status-line').text('Save failed: ' + (err && err.message ? err.message : 'error'));
                        alert('Failed to save Current Title: ' + (err && err.message ? err.message : 'error'));
                    });
            });
        }

        let amzTtPullPollTimer = null;

        function amzTtApplyPullStatus(st) {
            if (!st) return;
            const msg = st.message || '';
            if (st.running) {
                const done = st.done || 0;
                const total = st.total || 0;
                const lot = st.lot_index ? ('Lot ' + st.lot_index + '/' + (st.lots || '?') + ' · ') : '';
                $('#amz-tt-status-line').text(lot + (msg || ('Pulling titles… ' + done + '/' + total)));
                $('#amz-tt-pull-btn').prop('disabled', true)
                    .html('<i class="fa fa-spinner fa-spin me-1"></i> Pulling…');
            } else {
                if (msg) $('#amz-tt-status-line').text(msg);
                $('#amz-tt-pull-btn').prop('disabled', false)
                    .html('<i class="fas fa-cloud-download-alt me-1"></i> Pull Titles from Amz');
            }
        }

        function amzTtStopPullPoll() {
            if (amzTtPullPollTimer) {
                clearInterval(amzTtPullPollTimer);
                amzTtPullPollTimer = null;
            }
        }

        function amzTtStartPullPoll() {
            amzTtStopPullPoll();
            amzTtPullPollTimer = setInterval(function() {
                $.getJSON(@json(route('amz.titles.pull.status')))
                    .done(function(resp) {
                        const st = resp && resp.status ? resp.status : {};
                        amzTtApplyPullStatus(st);
                        if (!st.running) {
                            amzTtStopPullPoll();
                            if (amzTtTable) amzTtTable.replaceData();
                        }
                    });
            }, 3000);
        }

        $(function() {
            initAmzTitlesTable();

            $('#amz-tt-search').on('keyup input', function() { amzTtApplyFilters(); });
            $('#amz-tt-row-type').on('change', function() { amzTtApplyFilters(); });
            $('#amz-tt-inv-gt0').on('change', function() { amzTtApplyFilters(); });
            $('#amz-tt-refresh-btn').on('click', function() {
                if (amzTtTable) amzTtTable.replaceData();
            });

            $('#amz-tt-prompt-badge').on('click', function(e) {
                e.preventDefault();
                amzTtOpenPromptModal();
            });

            $('#amz-tt-ai-prompt').on('input', function() {
                $('#amz-tt-ai-prompt-count').text((this.value || '').length.toLocaleString() + ' characters');
            });
            $('#amz-tt-ai-prompt-reset').on('click', function() {
                const ta = document.getElementById('amz-tt-ai-prompt');
                if (ta) {
                    ta.value = AMZ_TT_DEFAULT_PROMPT;
                    $('#amz-tt-ai-prompt-count').text(ta.value.length.toLocaleString() + ' characters');
                }
            });
            $('#amz-tt-ai-prompt-save').on('click', function() {
                const ta = document.getElementById('amz-tt-ai-prompt');
                const text = ta ? String(ta.value || '').trim() : '';
                amzTtSetPrompt(text || AMZ_TT_DEFAULT_PROMPT);
                const modalEl = document.getElementById('amzTtPromptModal');
                if (modalEl && typeof bootstrap !== 'undefined') {
                    bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                }
                $('#amz-tt-status-line').text('AI prompt saved');
            });

            $('#amz-tt-comp-refresh').on('click', function() {
                $('#amz-tt-comp-list').html('<div class="text-center text-muted py-4"><i class="fa fa-spinner fa-spin me-1"></i> Loading…</div>');
                amzTtLoadCompetitors();
            });

            $.getJSON(@json(route('amz.titles.pull.status'))).done(function(resp) {
                const st = resp && resp.status ? resp.status : {};
                amzTtApplyPullStatus(st);
                if (st.running) amzTtStartPullPoll();
            });

            $('#amz-tt-pull-btn').on('click', function() {
                if (!amzTtTable) return;

                const selectedRows = amzTtTable.getSelectedData().filter(function(r) { return r && r.sku; });
                if (!selectedRows.length) {
                    alert('Select one or more rows first.\n\nPull Titles only runs for selected SKUs (parent rows expand to their children).');
                    return;
                }

                // Selected SKUs + children of selected parent rows (unique).
                const skuSet = {};
                const allData = amzTtTable.getData() || [];
                selectedRows.forEach(function(r) {
                    if (r.is_parent_summary) {
                        const parent = String(r.parent || '').trim();
                        allData.forEach(function(c) {
                            if (c && !c.is_parent_summary && String(c.parent || '').trim() === parent && c.sku) {
                                skuSet[c.sku] = true;
                            }
                        });
                    } else if (r.sku) {
                        skuSet[r.sku] = true;
                    }
                });
                const selected = Object.keys(skuSet);
                if (!selected.length) {
                    alert('No child SKUs found in the selection.');
                    return;
                }

                if (!confirm(
                    'Pull Amz listing titles for ' + selected.length + ' selected SKU(s)?\n\n' +
                    '· Selected rows only (parents → their children)\n' +
                    '· Uses Amz SP-API (Listings item_name)\n' +
                    '· Writes to Current Title (product_master.title150)\n' +
                    '· Synced with Title Master'
                )) {
                    return;
                }

                const $btn = $(this);
                $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Pulling…');
                $('#amz-tt-status-line').text('Pulling titles for ' + selected.length + ' selected SKU(s)…');

                $.ajax({
                    url: @json(route('amz.titles.pull')),
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': AMZ_TT_CSRF },
                    data: { skus: selected },
                    timeout: Math.max(120000, selected.length * 20000),
                    success: function(resp) {
                        const st = (resp && resp.status) ? resp.status : {};
                        $('#amz-tt-status-line').text((resp && resp.message) || st.message || 'Pull finished');
                        amzTtApplyPullStatus(st);
                        if (st.running) {
                            amzTtStartPullPoll();
                        } else {
                            amzTtStopPullPoll();
                            if (amzTtTable) amzTtTable.replaceData();
                        }
                    },
                    error: function(xhr) {
                        const st = xhr.responseJSON && xhr.responseJSON.status ? xhr.responseJSON.status : null;
                        if (st) amzTtApplyPullStatus(st);
                        else {
                            $btn.prop('disabled', false)
                                .html('<i class="fas fa-cloud-download-alt me-1"></i> Pull Titles from Amz');
                        }
                        const msg = (xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message))
                            || (xhr.statusText === 'timeout' ? 'Pull timed out — try again' : 'Failed to pull titles');
                        $('#amz-tt-status-line').text(msg);
                        alert(msg);
                    },
                });
            });
        });
    </script>
@endsection
