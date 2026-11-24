@extends('layouts.vertical', ['title' => 'Purchase Comparison — Transposed'])

@section('css')
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<style>
    .tx-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 12px; }
    .tx-toolbar .tx-sku { font-weight: 700; }
    .tx-status { font-size: .8rem; margin-left: auto; }
    .tx-status.saving { color: #b45309; }
    .tx-status.saved { color: #15803d; }
    .tx-status.error { color: #b91c1c; font-weight: 600; }

    #tx-table .tabulator-header .tabulator-col { background: #f1f5f9; border-right: 1px solid #e2e8f0; }
    #tx-table .tabulator-header .tabulator-col .tabulator-col-title { white-space: normal; font-size: .78rem; font-weight: 700; color: #1e293b; }
    #tx-table .tabulator-row .tabulator-cell { white-space: pre-wrap; word-break: break-word; font-size: .82rem; vertical-align: middle; border-right: 1px solid #e2e8f0; }
    #tx-table .tabulator-row.tabulator-row-even { background: #fafbfc; }
    #tx-table .tabulator-cell.tx-name-cell { font-weight: 700; }
    #tx-table .tabulator-cell.tx-readonly { cursor: default; }

    .tx-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 5px; background: #94a3b8; flex: 0 0 auto; }
    .tx-dot-critical { background: #dc2626; }
    .tx-dot-important { background: #f59e0b; }
    .tx-dot-normal { background: #94a3b8; }
    .tx-head { display: flex; align-items: center; gap: 2px; }
    .tx-head .tx-spec-del { margin-left: auto; border: 0; background: transparent; color: #94a3b8; font-size: .9rem; line-height: 1; padding: 0 2px; cursor: pointer; }
    .tx-head .tx-spec-del:hover { color: #dc2626; }

    .tx-img { max-width: 70px; max-height: 70px; border-radius: 4px; object-fit: cover; cursor: zoom-in; display: block; margin: 0 auto; background: #f1f5f9; }
    .tx-link-dot { display: inline-block; width: 14px; height: 14px; border-radius: 50%; background: #16a34a; }
    .tx-link-dot.missing { background: #dc2626; }
    .tx-del-supplier { border: 0; background: transparent; color: #94a3b8; cursor: pointer; font-size: 1rem; line-height: 1; }
    .tx-del-supplier:hover { color: #dc2626; }
    .tx-chip { display: inline-block; font-size: .65rem; font-weight: 600; padding: 1px 6px; border-radius: 10px; background: #e2e8f0; color: #475569; margin-left: 6px; vertical-align: middle; }
</style>
@endsection

@section('content')
<div class="container-fluid mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white d-flex align-items-center gap-2">
            <h4 class="mb-0"><i class="mdi mdi-table-pivot"></i> Purchase Comparison — Transposed</h4>
            <span class="tx-sku ms-2" id="tx-sku-label"></span>
        </div>
        <div class="card-body">
            <div class="tx-toolbar">
                <a class="btn btn-sm btn-outline-secondary" id="tx-back-btn" href="#"><i class="mdi mdi-arrow-left"></i> Sheet view</a>
                <input type="text" id="tx-search" class="form-control form-control-sm" style="max-width:220px;" placeholder="Search supplier…">
                <select id="tx-priority-filter" class="form-select form-select-sm" style="max-width:190px;" title="Show only the specs with this priority">
                    <option value="all">Specs: All</option>
                    <option value="critical">Specs: Critical only</option>
                    <option value="critical_important">Specs: Critical + Important</option>
                </select>
                <button type="button" class="btn btn-sm btn-primary" id="tx-add-supplier"><i class="mdi mdi-table-row-plus-after"></i> Add Supplier</button>
                <button type="button" class="btn btn-sm btn-outline-primary" id="tx-add-spec"><i class="mdi mdi-table-column-plus-after"></i> Add Spec</button>
                <span class="tx-status" id="tx-status"></span>
            </div>
            <div id="tx-loading" class="text-muted py-4 text-center">Loading…</div>
            <div id="tx-error" class="alert alert-warning d-none"></div>
            <div id="tx-table" class="d-none"></div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const SKU = @json($sheetSku);
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const sheetGetUrl = @json(route('comparison.sheet.get'));
    const sheetSaveUrl = @json(route('comparison.sheet.save'));
    const sheetImageUrl = @json(route('comparison.sheet.image'));
    const sheetPageUrl = @json(route('comparison.sheet.page'));

    // ---- state (the grid is stored exactly like the normal sheet view: cells[row][col]) ----
    let cells = [];
    let manualFormats = { cells: {}, rows: {}, cols: {} };
    let autoFormats = { cells: {}, rows: {}, cols: {} };
    let sheetSku = SKU;
    let parentSku = '';
    let linkedSkus = [];
    let expectedUpdatedAt = '';
    let ctx = null;                 // column roles of the current grid
    let table = null;
    let forcedSupplierCols = new Set(); // supplier columns the user just added (kept even while blank)
    let suppressEdit = false;
    let dirty = false;
    let saveTimer = null;
    let saveInFlight = false;
    let saveQueued = false;

    const $ = id => document.getElementById(id);
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    document.getElementById('tx-sku-label').textContent = SKU;
    $('tx-back-btn').setAttribute('href', sheetPageUrl + '?sku=' + encodeURIComponent(SKU));

    function setStatus(text, kind) {
        const el = $('tx-status');
        el.textContent = text || '';
        el.className = 'tx-status' + (kind ? ' ' + kind : '');
    }

    // ---- grid helpers (ported from the sheet view) ----
    const lc = v => String(v ?? '').trim().toLowerCase();
    const colCount = () => Math.max(1, ...cells.map(r => Array.isArray(r) ? r.length : 0));

    function normalizeGrid() {
        const n = colCount();
        cells = cells.map(r => {
            const row = Array.isArray(r) ? r.slice() : [String(r ?? '')];
            while (row.length < n) row.push('');
            return row.map(v => v == null ? '' : String(v));
        });
    }

    function headerLabelOf(value) {
        const t = String(value ?? '');
        const p = t.indexOf('|');
        return (p >= 0 ? t.slice(0, p) : t).trim().toLowerCase();
    }

    function detectSpecCol() {
        const scores = {};
        const maxRows = Math.min(cells.length, 30);
        for (let r = 0; r < maxRows; r++) {
            const row = cells[r] || [];
            for (let c = 0; c < row.length; c++) {
                const raw = row[c];
                if (raw == null || raw === '' || String(raw).length > 2000) continue;
                const t = lc(raw);
                if (!t || t.startsWith('http')) continue;
                if (t.includes('supplier') || t.includes('product photo') || t.includes('person name review') || t.includes('company name')) {
                    scores[c] = (scores[c] || 0) + 1;
                }
            }
        }
        const keys = Object.keys(scores);
        if (!keys.length) return 2;
        keys.sort((a, b) => scores[b] - scores[a]);
        return parseInt(keys[0], 10);
    }

    function isPriorityValue(v) { return ['critical', 'important', 'normal'].includes(lc(v)); }

    function columnLooksLikePriorityOnly(c) {
        if (c < 0) return false;
        let saw = false;
        for (let r = 0; r < Math.min(cells.length, 40); r++) {
            const t = String((cells[r] || [])[c] || '').trim();
            if (!t) continue;
            if (r === 0 && ['critical', 'qc'].includes(headerLabelOf(t))) continue;
            if (isPriorityValue(t)) saw = true; else return false;
        }
        return saw;
    }

    function findHeaderCol(name, from) {
        for (let c = Math.max(0, from || 0); c < colCount(); c++) {
            if (headerLabelOf((cells[0] || [])[c]) === name) return c;
        }
        return null;
    }

    function detectCriticalCol(specCol) {
        const byHeader = findHeaderCol('critical', specCol + 1);
        if (byHeader !== null) return byHeader;
        const cand = specCol + 1;
        if (headerLabelOf((cells[0] || [])[cand]) === 'qc') return null;
        return columnLooksLikePriorityOnly(cand) ? cand : null;
    }

    function detectQcCol(specCol, criticalCol) {
        const byHeader = findHeaderCol('qc', specCol + 1);
        if (byHeader !== null) return byHeader;
        const cand = (criticalCol !== null ? criticalCol : specCol) + 1;
        if (headerLabelOf((cells[0] || [])[cand]) === 'critical') return null;
        return columnLooksLikePriorityOnly(cand) ? cand : null;
    }

    function isSupplierNameLabel(text) {
        const label = lc(text);
        if (!label || label.includes('company name')) return false;
        if (label.includes('person name review') || label.includes('supplier name')) return true;
        return label === 'suppliers';
    }

    function findSupplierNameRow(specCol) {
        for (let r = 0; r < cells.length; r++) {
            if (isSupplierNameLabel((cells[r] || [])[specCol])) return r;
        }
        return null;
    }

    function computeContext() {
        normalizeGrid();
        const specCol = detectSpecCol();
        const criticalCol = detectCriticalCol(specCol);
        const qcCol = detectQcCol(specCol, criticalCol);
        const firstSupplierCol = qcCol !== null ? qcCol + 1 : (criticalCol !== null ? criticalCol + 1 : specCol + 1);
        ctx = { specCol, criticalCol, qcCol, firstSupplierCol, supplierRow: findSupplierNameRow(specCol) };
        return ctx;
    }

    function supplierNameForCol(c) {
        if (ctx.supplierRow !== null) {
            const v = String((cells[ctx.supplierRow] || [])[c] || '').trim();
            if (v) return v;
        }
        const header = String((cells[0] || [])[c] || '').trim();
        const skip = ['amazon', 'amz', '5 core', '5core', '5-core', 'critical', 'qc', 'supplier', 'spec'];
        if (header && !skip.includes(header.toLowerCase()) && !/^[A-Z]{1,3}$/.test(header)) return header;
        return '';
    }

    function normalizePriority(v) {
        const t = lc(v);
        return t === 'critical' ? 'Critical' : (t === 'important' ? 'Important' : 'Normal');
    }

    function priorityOfRow(r) {
        if (ctx.criticalCol === null) return 'Normal';
        const raw = (cells[r] || [])[ctx.criticalCol];
        if (r === 0) {
            const t = String(raw ?? '');
            const p = t.indexOf('|');
            return p >= 0 ? normalizePriority(t.slice(p + 1)) : 'Normal';
        }
        if (String(raw ?? '').trim() === '') {
            const label = lc((cells[r] || [])[ctx.specCol]);
            return (label === 'supplier name' || label === 'supplier') ? 'Critical' : 'Normal';
        }
        return normalizePriority(raw);
    }

    // ---- cell kinds ----
    const imgExt = /\.(jpe?g|png|gif|webp|bmp|svg)(\?.*)?$/i;
    function isImageValue(v) {
        const t = String(v ?? '').trim();
        if (!t) return false;
        return t.startsWith('[cmp-photo:') || t.startsWith('[embedded-image:') || t.startsWith('data:image/')
            || (/^https?:\/\//i.test(t) && imgExt.test(t));
    }
    function isHttp(v) { return /^https?:\/\//i.test(String(v ?? '').trim()); }
    function isLinkRowLabel(label) { const l = lc(label); return l === 'supplier link' || l === 'link'; }
    function isCommLabel(label) { const l = lc(label); return l === 'comm' || l.includes('communication'); }

    function imageSrc(value, r, c) {
        const t = String(value).trim();
        if (/^https?:\/\//i.test(t)) return t;
        const params = new URLSearchParams({ sheet_sku: sheetSku || SKU, sku: SKU, v: '1' });
        const m = t.match(/^\[cmp-photo:([A-Za-z0-9._-]+\.(?:jpe?g|png|gif|webp))\]$/i);
        if (m) {
            params.set('photo', m[1]);
        } else {
            params.set('row', String(r));
            params.set('col', String(c));
        }
        return sheetImageUrl + '?' + params.toString();
    }

    // ---- colours (manual formats override the automatic ones, as in the sheet view) ----
    function fmtColor(kind, key) {
        const m = (manualFormats[kind] || {})[key];
        if (m) return m;
        return (autoFormats[kind] || {})[key] || '';
    }
    function cellBg(r, c) {
        return fmtColor('cells', r + ':' + c) || fmtColor('rows', String(r)) || '';
    }

    // ---- build table data/columns from the grid ----
    function supplierColumnIndexes() {
        const out = [];
        const n = colCount();
        const first = ctx.firstSupplierCol;
        // Amazon + 5 Core columns sit just left of Spec.
        [ctx.specCol - 2, ctx.specCol - 1].forEach(c => { if (c >= 0 && c !== ctx.specCol) out.push(c); });
        for (let c = first; c < n; c++) {
            if (c === ctx.specCol) continue;
            let has = forcedSupplierCols.has(c) || supplierNameForCol(c) !== '';
            if (!has) {
                for (let r = 0; r < cells.length; r++) {
                    if (String((cells[r] || [])[c] || '').trim() !== '') { has = true; break; }
                }
            }
            if (has) out.push(c);
        }
        return out;
    }

    function specRowIndexes() {
        const out = [];
        const filter = $('tx-priority-filter').value;
        for (let r = 0; r < cells.length; r++) {
            if (r === ctx.supplierRow) continue;           // shown as the first (frozen) column
            const label = String((cells[r] || [])[ctx.specCol] || '').trim();
            if (isCommLabel(label)) continue;               // communication is managed in the full sheet view
            const rowHasData = (cells[r] || []).some((v, c) => c !== ctx.specCol && c !== ctx.criticalCol && c !== ctx.qcCol && String(v ?? '').trim() !== '');
            if (!label && !rowHasData) continue;
            const pr = priorityOfRow(r);
            if (filter === 'critical' && pr !== 'Critical') continue;
            if (filter === 'critical_important' && pr === 'Normal') continue;
            out.push(r);
        }
        return out;
    }

    function buildRows() {
        return supplierColumnIndexes().map(c => {
            const lead = c === ctx.specCol - 2 ? 'Amz' : (c === ctx.specCol - 1 ? '5 Core' : '');
            const row = { _col: c, _lead: lead, _name: lead || supplierNameForCol(c) };
            cells.forEach((gridRow, r) => { row['r' + r] = String((gridRow || [])[c] ?? ''); });
            return row;
        });
    }

    function buildColumns() {
        const cols = [];
        const specHeaderBg = fmtColor('cols', String(ctx.specCol));

        cols.push({
            title: 'Supplier', field: '_name', frozen: true, minWidth: 170, width: 190, headerSort: true,
            formatter: function (cell) {
                const d = cell.getData();
                const el = cell.getElement();
                el.classList.add('tx-name-cell');
                const bg = (ctx.supplierRow !== null ? fmtColor('rows', String(ctx.supplierRow)) : '') || fmtColor('cols', String(d._col));
                if (bg) el.style.background = bg;
                return esc(cell.getValue() || '—');
            },
            editor: 'input',
            editable: function (cell) { return !cell.getData()._lead && ctx.supplierRow !== null; },
            cellEdited: function (cell) {
                const d = cell.getData();
                if (suppressEdit || d._lead || ctx.supplierRow === null) return;
                cells[ctx.supplierRow][d._col] = String(cell.getValue() ?? '');
                touch();
            },
        });

        specRowIndexes().forEach(r => {
            const label = String((cells[r] || [])[ctx.specCol] || '').trim() || ('Row ' + (r + 1));
            const pr = priorityOfRow(r);
            const field = 'r' + r;
            const isLinkRow = isLinkRowLabel(label);
            cols.push({
                title: label, field: field, minWidth: 110, width: 150, headerSort: false,
                titleFormatter: function (cell, params, onRendered) {
                    const html = '<div class="tx-head" title="' + esc(label) + ' · ' + pr + '">'
                        + '<span class="tx-dot tx-dot-' + pr.toLowerCase() + '"></span><span>' + esc(label) + '</span>'
                        + '<button type="button" class="tx-spec-del" data-row="' + r + '" title="Delete this spec for every supplier">&times;</button></div>';
                    onRendered(function () {
                        const colEl = cell.getElement().closest('.tabulator-col');
                        if (colEl && specHeaderBg) colEl.style.background = specHeaderBg;
                    });
                    return html;
                },
                headerClick: function (e) {
                    const btn = e.target.closest('.tx-spec-del');
                    if (!btn) return;
                    e.stopPropagation();
                    deleteSpecRow(parseInt(btn.dataset.row, 10));
                },
                formatter: function (cell) {
                    const d = cell.getData();
                    const v = String(cell.getValue() ?? '');
                    const el = cell.getElement();
                    const bg = cellBg(r, d._col) || fmtColor('cols', String(d._col));
                    el.style.background = bg || '';
                    el.classList.remove('tx-readonly');
                    const t = v.trim();
                    if (isLinkRow && isHttp(t)) {
                        return '<a href="' + esc(t) + '" target="_blank" rel="noopener noreferrer" title="' + esc(t) + '"><span class="tx-link-dot"></span></a>';
                    }
                    if (isImageValue(t)) {
                        el.classList.add('tx-readonly');
                        const src = imageSrc(t, r, d._col);
                        return '<a href="' + esc(src) + '" target="_blank" rel="noopener"><img class="tx-img" loading="lazy" src="' + esc(src) + '" alt="Product photo"></a>';
                    }
                    if (isHttp(t)) {
                        return '<a href="' + esc(t) + '" target="_blank" rel="noopener noreferrer" title="' + esc(t) + '"><span class="tx-link-dot"></span></a>';
                    }
                    if (isLinkRow) return '<span class="tx-link-dot missing" title="No link"></span>';
                    return esc(v);
                },
                editor: 'textarea',
                editable: function (cell) { return !isImageValue(cell.getValue()); },
                cellEdited: function (cell) {
                    if (suppressEdit) return;
                    cells[r][cell.getData()._col] = String(cell.getValue() ?? '');
                    touch();
                },
            });
        });

        cols.push({
            title: '', field: '_del', width: 44, hozAlign: 'center', headerSort: false, frozen: false,
            formatter: function (cell) {
                return cell.getData()._lead ? '' : '<button type="button" class="tx-del-supplier" title="Delete this supplier column">&times;</button>';
            },
            cellClick: function (e, cell) {
                if (!e.target.closest('.tx-del-supplier')) return;
                deleteSupplier(cell.getData()._col);
            },
        });
        return cols;
    }

    function buildTable() {
        computeContext();
        const holder = $('tx-table');
        holder.classList.remove('d-none');
        if (table) { try { table.destroy(); } catch (e) { /* ignore */ } table = null; }
        table = new Tabulator('#tx-table', {
            data: buildRows(),
            columns: buildColumns(),
            layout: 'fitData',
            height: 'calc(100vh - 260px)',
            index: '_col',
            placeholder: 'No suppliers on this sheet yet',
            movableColumns: true,
            resizableColumns: true,
            reactiveData: false,
        });
        applySearch();
    }

    function applySearch() {
        if (!table) return;
        const q = lc($('tx-search').value);
        if (!q) { table.clearFilter(); return; }
        table.setFilter(function (d) { return lc(d._name).includes(q) || lc(d._lead).includes(q); });
    }

    // ---- editing operations ----
    function shiftFormatMap(map, index, kind) {
        // kind: 'row' | 'col' | 'cell-row' | 'cell-col' — drop `index`, shift the ones after it by -1.
        const out = {};
        Object.keys(map || {}).forEach(key => {
            if (kind === 'row' || kind === 'col') {
                const n = parseInt(key, 10);
                if (Number.isNaN(n) || n === index) return;
                out[String(n > index ? n - 1 : n)] = map[key];
                return;
            }
            const [rs, cs] = key.split(':');
            let r = parseInt(rs, 10), c = parseInt(cs, 10);
            if (Number.isNaN(r) || Number.isNaN(c)) return;
            if (kind === 'cell-row') { if (r === index) return; if (r > index) r -= 1; }
            else { if (c === index) return; if (c > index) c -= 1; }
            out[r + ':' + c] = map[key];
        });
        return out;
    }

    function addSupplier() {
        if (!cells.length) return;
        computeContext();
        const name = (window.prompt('Supplier name for the new row:') || '').trim();
        if (!name) return;
        const newCol = colCount();
        cells = cells.map(r => r.concat(['']));
        if (ctx.supplierRow !== null) cells[ctx.supplierRow][newCol] = name;
        else cells[0][newCol] = name;
        forcedSupplierCols.add(newCol);
        buildTable();
        touch(0);
    }

    function deleteSupplier(c) {
        computeContext();
        const name = supplierNameForCol(c) || ('column ' + (c + 1));
        if (!window.confirm('Delete ' + name + '? This removes the supplier from the sheet.')) return;
        cells = cells.map(r => r.filter((_, i) => i !== c));
        manualFormats.cols = shiftFormatMap(manualFormats.cols, c, 'col');
        manualFormats.cells = shiftFormatMap(manualFormats.cells, c, 'cell-col');
        autoFormats = { cells: {}, rows: {}, cols: {} };
        forcedSupplierCols = new Set([...forcedSupplierCols].filter(x => x !== c).map(x => x > c ? x - 1 : x));
        buildTable();
        touch(0);
    }

    function addSpec() {
        computeContext();
        const label = (window.prompt('Spec name for the new column:') || '').trim();
        if (!label) return;
        const row = Array.from({ length: colCount() }, () => '');
        row[ctx.specCol] = label;
        if (ctx.criticalCol !== null) row[ctx.criticalCol] = 'Normal';
        if (ctx.qcCol !== null) row[ctx.qcCol] = 'Normal';
        cells.push(row);
        buildTable();
        touch(0);
    }

    function deleteSpecRow(r) {
        computeContext();
        if (r === ctx.supplierRow || !cells[r]) return;
        const label = String(cells[r][ctx.specCol] || '').trim() || ('Row ' + (r + 1));
        if (!window.confirm('Delete the "' + label + '" spec? This removes it for every supplier.')) return;
        cells.splice(r, 1);
        manualFormats.rows = shiftFormatMap(manualFormats.rows, r, 'row');
        manualFormats.cells = shiftFormatMap(manualFormats.cells, r, 'cell-row');
        autoFormats = { cells: {}, rows: {}, cols: {} };
        buildTable();
        touch(0);
    }

    // ---- saving (same endpoint and payload as the normal sheet view) ----
    function touch(delay) {
        dirty = true;
        setStatus('Unsaved changes…', 'saving');
        clearTimeout(saveTimer);
        saveTimer = setTimeout(save, typeof delay === 'number' ? delay : 700);
    }

    async function save() {
        if (saveInFlight) { saveQueued = true; return; }
        saveInFlight = true;
        setStatus('Saving…', 'saving');
        try {
            const res = await fetch(sheetSaveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json', 'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    sku: SKU,
                    parent: parentSku || '',
                    linked_skus: [SKU],
                    bulk_edit_skus: [],
                    cells: cells,
                    formats: manualFormats,
                    google_sheet_url: '',
                    google_sheet_tab: 'Sheet1',
                    expected_updated_at: expectedUpdatedAt || '',
                }),
            });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.message || 'Save failed.');
            if (data.updated_at_iso) expectedUpdatedAt = String(data.updated_at_iso);
            if (data.auto_formats) autoFormats = data.auto_formats;
            adoptPhotoTokens(data.cells);
            refreshFormatting();
            dirty = saveQueued;
            setStatus('Saved', 'saved');
        } catch (err) {
            setStatus(err.message || 'Save failed.', 'error');
        } finally {
            saveInFlight = false;
            if (saveQueued) { saveQueued = false; touch(300); }
        }
    }

    // Server rewrites pasted/embedded photos into stable [cmp-photo:…] tokens.
    function adoptPhotoTokens(returned) {
        if (!Array.isArray(returned)) return;
        suppressEdit = true;
        try {
            returned.forEach((row, r) => {
                (row || []).forEach((v, c) => {
                    const t = String(v ?? '');
                    const mine = String((cells[r] || [])[c] ?? '');
                    if (t.startsWith('[cmp-photo:') && mine !== t && isImageValue(mine)) {
                        cells[r][c] = t;
                        const tr = table && table.getRow(c);
                        if (tr) tr.update({ ['r' + r]: t });
                    }
                });
            });
        } finally { suppressEdit = false; }
    }

    function refreshFormatting() {
        if (!table) return;
        table.getRows().forEach(r => r.reformat());
    }

    // ---- load ----
    async function load() {
        if (!SKU) {
            $('tx-loading').classList.add('d-none');
            $('tx-error').textContent = 'No SKU given. Open this page with ?sku=…';
            $('tx-error').classList.remove('d-none');
            return;
        }
        try {
            const res = await fetch(sheetGetUrl + '?sku=' + encodeURIComponent(SKU), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.message || 'Could not load the sheet.');
            cells = Array.isArray(data.cells) ? data.cells : [];
            manualFormats = { cells: {}, rows: {}, cols: {}, ...(data.formats || {}) };
            ['cells', 'rows', 'cols'].forEach(k => { if (Array.isArray(manualFormats[k])) manualFormats[k] = {}; });
            autoFormats = { cells: {}, rows: {}, cols: {}, ...(data.auto_formats || {}) };
            ['cells', 'rows', 'cols'].forEach(k => { if (Array.isArray(autoFormats[k])) autoFormats[k] = {}; });
            sheetSku = data.sheet_sku || SKU;
            parentSku = data.parent || '';
            linkedSkus = data.linked_skus || [];
            expectedUpdatedAt = String(data.updated_at_iso || '');
            $('tx-loading').classList.add('d-none');
            buildTable();
            setStatus(data.has_sheet_data ? '' : 'No sheet saved yet — edits create it.', '');
        } catch (err) {
            $('tx-loading').classList.add('d-none');
            $('tx-error').textContent = err.message || 'Could not load the sheet.';
            $('tx-error').classList.remove('d-none');
        }
    }

    $('tx-add-supplier').addEventListener('click', addSupplier);
    $('tx-add-spec').addEventListener('click', addSpec);
    $('tx-search').addEventListener('input', applySearch);
    $('tx-priority-filter').addEventListener('change', function () { if (cells.length) buildTable(); });
    window.addEventListener('beforeunload', function (e) {
        if (dirty || saveInFlight) { e.preventDefault(); e.returnValue = ''; }
    });

    load();
});
</script>
@endsection
