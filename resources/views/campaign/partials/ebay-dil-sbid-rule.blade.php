@php
    $dilSbidAccount = $account ?? 'eBay';
@endphp
@if(($part ?? 'modal') === 'modal')
<div class="modal fade is-off" id="dilSbidRuleModal" tabindex="-1" aria-labelledby="dilSbidRuleModalLabel" aria-hidden="true">
    <style>
        #dilSbidRuleModal .modal-dialog { max-width: 760px; }
        #dil-sbid-cvr-table .dil-sbid-cvr-input { width: 88px; display: inline-block; }
        #dil-sbid-table thead th { background: #e7f1fb; color: #1e3a5f; font-weight: 600; }
        #dilSbidRuleModal input[type=number]::-webkit-inner-spin-button,
        #dilSbidRuleModal input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        #dilSbidRuleModal input[type=number] { -moz-appearance: textfield; appearance: textfield; }
        #dil-sbid-table .form-control { border-radius: 0.55rem; }
        #dilSbidRuleModal.is-off #dil-sbid-table { opacity: 0.55; }
        .dil-sbid-switch .form-check-input { width: 2.4em; height: 1.25em; cursor: pointer; }
        .dil-sbid-dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; margin-left: 6px; vertical-align: middle; }
        .dil-sbid-badge { display: inline-block; min-width: 72px; text-align: center; font-weight: 700; font-size: 12px; padding: 4px 8px; border-radius: 8px; }
        .dil-sbid-es { background: #cff4fc; color: #055160; }
        .dil-sbid-off { background: #f8d7da; color: #842029; }
    </style>
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="dilSbidRuleModalLabel">
                    <i class="fas fa-percent me-2 text-primary"></i>Dil vs SBid
                    <span class="badge bg-secondary ms-2" style="font-size:11px;">{{ $dilSbidAccount }} only</span>
                </h5>
                <div class="form-check form-switch dil-sbid-switch mb-0 ms-auto me-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="dil-sbid-enabled">
                    <label class="form-check-label small fw-semibold" for="dil-sbid-enabled" id="dil-sbid-enabled-label">Off</label>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small mb-2" id="dil-sbid-mode-note">Off. S Bid is not changed.</p>
                <ul class="small text-muted mb-3 ps-3">
                    <li>Count is unique SKUs. Dil is CP Master Dil: round(OV L30 sold ÷ Inventory × 100). Inventory 0 and missing data are not counted. First matching slab wins.</li>
                    <li><strong>0–0</strong> is SKUs with <strong>OV L30 sold = 0</strong>. Every ad for that SKU uses its <strong>ES Bid</strong>.</li>
                    <li>Every other slab uses the <strong>S Bid %</strong> you type on that row.</li>
                    <li><strong>CVR overlay</strong> then adjusts that S Bid, same as Sprc Dil. Down = CVR is below the threshold and the arrow is down (CVR L30 under CVR L60). Up = CVR is above the threshold and the arrow is up. Flat arrows are left alone.</li>
                    <li>Saved for {{ $dilSbidAccount }} only. eBay, eBay 2, and eBay 3 each keep their own slabs.</li>
                </ul>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0" id="dil-sbid-table">
                        <thead>
                            <tr>
                                <th class="text-center" style="width:110px;">From</th>
                                <th class="text-center" style="width:110px;">To</th>
                                <th class="text-center" style="width:90px;" title="Unique SKUs. 0–0 is OV L30 sold = 0 with Inventory. Each ad for that SKU uses the same slab.">Count</th>
                                <th class="text-end" style="width:140px;">S Bid</th>
                                <th style="width:36px;"></th>
                            </tr>
                        </thead>
                        <tbody id="dil-sbid-tbody"></tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="dil-sbid-add-btn">
                    <i class="fas fa-plus me-1"></i>Add slab
                </button>
                <div class="fw-semibold small mt-3 mb-1">CVR overlay — S Bid</div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0" id="dil-sbid-cvr-table">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th class="text-center">CVR%</th>
                                <th class="text-end">Adj S Bid</th>
                                <th class="text-center" style="width:80px;" title="Unique SKUs. Down: CVR below the threshold and a down arrow. Up: CVR above the threshold and an up arrow.">Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Down</td>
                                <td class="text-center">
                                    &lt;
                                    <input type="number" min="0" step="0.1" class="form-control form-control-sm text-end dil-sbid-cvr-input" id="dil-sbid-cvr-down-lt" value="7">
                                </td>
                                <td class="text-end">
                                    <input type="number" step="1" class="form-control form-control-sm text-end dil-sbid-cvr-input" id="dil-sbid-cvr-down-adj" value="-10">
                                </td>
                                <td class="text-center fw-semibold"><span id="dil-sbid-cvr-down-count">0</span></td>
                            </tr>
                            <tr>
                                <td>Up</td>
                                <td class="text-center">
                                    &gt;
                                    <input type="number" min="0" step="0.1" class="form-control form-control-sm text-end dil-sbid-cvr-input" id="dil-sbid-cvr-up-gt" value="10">
                                </td>
                                <td class="text-end">
                                    <input type="number" step="1" class="form-control form-control-sm text-end dil-sbid-cvr-input" id="dil-sbid-cvr-up-adj" value="10">
                                </td>
                                <td class="text-center fw-semibold"><span id="dil-sbid-cvr-up-count">0</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="small text-danger mb-0 mt-2 d-none" id="dil-sbid-err"></p>
            </div>
            <div class="modal-footer py-2 d-flex justify-content-between">
                <span class="small text-muted" id="dil-sbid-status"></span>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="dil-sbid-apply-btn">
                    <i class="fas fa-save me-1"></i>Save and Apply
                </button>
            </div>
        </div>
    </div>
</div>
@else
const DIL_SBID_GET_URL = @json($getUrl);
const DIL_SBID_SAVE_URL = @json($saveUrl);
const DIL_SBID_APPLY_URL = @json($applyUrl);
const DIL_SBID_DEFAULTS = @json(\App\Support\DilVsSbidRule::defaultSlabs());
const DIL_SBID_CVR_DEFAULTS = @json(\App\Support\DilVsSbidRule::defaultCvr());
const DIL_SBID_COLORS = ['#6f42c1','#3b82f6','#14b8a6','#22c55e','#84cc16','#eab308','#f59e0b','#ea580c','#dc3545','#e83e8c','#7c3aed','#0ea5e9'];
let currentDilSbidSlabs = DIL_SBID_DEFAULTS.map(function(s) { return Object.assign({}, s); });
let currentDilSbidCvr = Object.assign({}, DIL_SBID_CVR_DEFAULTS);
var dilSbidEnabled = false;
let dilSbidSaveTimer = null;

function campaignSbid(row) {
    if (dilSbidEnabled) return dilSbidOfRow(row);
    return { bid: 0, color: '#6c757d', skip: true, title: 'Dil vs SBid is off' };
}
function dilSbidPaintMode() {
    const on = !!dilSbidEnabled;
    const box = document.getElementById('dil-sbid-enabled');
    const label = document.getElementById('dil-sbid-enabled-label');
    const note = document.getElementById('dil-sbid-mode-note');
    const modal = document.getElementById('dilSbidRuleModal');
    if (box) box.checked = on;
    if (label) label.textContent = on ? 'On' : 'Off';
    if (note) {
        note.textContent = on
            ? 'On. S Bid uses these Dil slabs, then the CVR overlay.'
            : 'Off. S Bid is not changed.';
        note.className = on ? 'small mb-2 text-success' : 'small mb-2 text-muted';
    }
    if (modal) modal.classList.toggle('is-off', !on);
}

function dilSbidMode(i) {
    return i <= 0 ? 'es_bid' : 'dynamic';
}
function dilSbidRound(n) {
    return Math.round((Number(n) || 0) * 100) / 100;
}
function dilSbidContains(dil, slab, prevMax) {
    const min = parseFloat(slab.min);
    const max = parseFloat(slab.max);
    if (!isFinite(min) || !isFinite(max)) return false;
    if (Math.abs(min) < 1e-7 && Math.abs(max) < 1e-7) return Math.abs(dil) < 1e-7;
    const sharesEdge = prevMax !== null && Math.abs(min - prevMax) < 0.0001;
    const loOk = sharesEdge ? dil > min : dil >= min;
    return loOk && dil <= max;
}
function dilSbidRows() {
    try {
        if (typeof allTableData !== 'undefined' && Array.isArray(allTableData) && allTableData.length) return allTableData;
        if (typeof table !== 'undefined' && table && typeof table.getData === 'function') return table.getData() || [];
    } catch (e) {}
    return [];
}
function dilSbidSkuKey(row) {
    if (!row) return '';
    if (row.sku_matched != null && row.sku_matched !== '') {
        if (!(row.sku_matched == 1 || row.sku_matched === true || row.sku_matched === '1')) return '';
        const sku = String(row.resolved_sku || row.sku || '').trim();
        if (!sku || sku.toUpperCase().indexOf('PARENT') !== -1) return '';
        return sku.toUpperCase();
    }
    if (typeof chPromoIsChildRow === 'function' && !chPromoIsChildRow(row)) return '';
    const child = String((typeof chPromoSku === 'function' ? chPromoSku(row) : (row['(Child) sku'] || row.sku || '')) || '').trim();
    if (!child || child.toUpperCase().indexOf('PARENT') !== -1) return '';
    return child.toUpperCase();
}
function dilSbidMetric(row) {
    if (row && row.sku_matched != null && row.sku_matched !== '') {
        const dil = (typeof dilValue === 'function') ? dilValue(row) : null;
        if (dil === null || !isFinite(dil)) return null;
        const raw = row.shopify_qty;
        if (raw === null || raw === undefined || raw === '') return dil;
        const ovl = Number(raw);
        if (!isFinite(ovl)) return dil;
        if (ovl === 0) return 0;
        if (dil === 0) {
            const inv = Number(row.shopify_inv);
            return inv > 0 ? (ovl / inv) * 100 : null;
        }
        return dil;
    }
    if (typeof chPromoShopifyInv === 'function' && typeof chPromoOvL30 === 'function') {
        const inv = chPromoShopifyInv(row);
        if (!(inv > 0)) return null;
        const ovl = chPromoOvL30(row);
        if (!(ovl > 0)) return 0;
        return (ovl / inv) * 100;
    }
    return null;
}
function dilSbidCvrNow() {
    const num = function(id, fallback) {
        const el = document.getElementById(id);
        const n = el ? parseFloat(el.value) : NaN;
        return isFinite(n) ? n : fallback;
    };
    currentDilSbidCvr = {
        down_lt: Math.max(0, num('dil-sbid-cvr-down-lt', DIL_SBID_CVR_DEFAULTS.down_lt)),
        down_adj: num('dil-sbid-cvr-down-adj', DIL_SBID_CVR_DEFAULTS.down_adj),
        up_gt: Math.max(0, num('dil-sbid-cvr-up-gt', DIL_SBID_CVR_DEFAULTS.up_gt)),
        up_adj: num('dil-sbid-cvr-up-adj', DIL_SBID_CVR_DEFAULTS.up_adj)
    };
    return currentDilSbidCvr;
}
function dilSbidPaintCvr() {
    const cfg = currentDilSbidCvr || DIL_SBID_CVR_DEFAULTS;
    const set = function(id, value) {
        const el = document.getElementById(id);
        if (el) el.value = value;
    };
    set('dil-sbid-cvr-down-lt', cfg.down_lt);
    set('dil-sbid-cvr-down-adj', cfg.down_adj);
    set('dil-sbid-cvr-up-gt', cfg.up_gt);
    set('dil-sbid-cvr-up-adj', cfg.up_adj);
}
function dilSbidCvrParts(row) {
    const views = parseFloat(row && row.views) || 0;
    if (!(views > 0)) return null;
    if (row && row['eBay L30'] != null) {
        const cvr = (row.SCVR != null && row.SCVR !== '') ? parseFloat(row.SCVR) : ((parseFloat(row['eBay L30']) || 0) / views) * 100;
        const cvr60 = (row.CVR_60 != null && row.CVR_60 !== '') ? parseFloat(row.CVR_60) : ((parseFloat(row['eBay L60']) || 0) / views) * 100;
        return { cvr: isFinite(cvr) ? cvr : 0, cvr60: isFinite(cvr60) ? cvr60 : 0 };
    }
    const l30 = parseFloat(row && row.ebay_l30) || 0;
    const l60 = parseFloat(row && row.ebay_l60) || 0;
    return { cvr: (l30 / views) * 100, cvr60: (l60 / views) * 100 };
}
function dilSbidCvrTrend(parts) {
    if (!parts) return 'flat';
    const tol = 0.1;
    if (parts.cvr === 0 || parts.cvr < parts.cvr60 - tol) return 'down';
    if (parts.cvr > parts.cvr60 + tol) return 'up';
    return 'flat';
}
function dilSbidApplyCvr(bid, row) {
    const cfg = currentDilSbidCvr || DIL_SBID_CVR_DEFAULTS;
    const parts = dilSbidCvrParts(row);
    const trend = dilSbidCvrTrend(parts);
    let adj = 0;
    let why = '';
    if (parts && trend === 'down' && parts.cvr < cfg.down_lt) {
        adj = cfg.down_adj;
        why = 'CVR Down < ' + cfg.down_lt + '% and down arrow';
    } else if (parts && trend === 'up' && parts.cvr > cfg.up_gt) {
        adj = cfg.up_adj;
        why = 'CVR Up > ' + cfg.up_gt + '% and up arrow';
    }
    let next = dilSbidRound(bid + adj);
    if (next < 0) next = 0;
    return { bid: next, adj: adj, why: why };
}
function dilSbidOfRow(row) {
    const dil = dilSbidMetric(row);
    if (dil === null || !isFinite(dil)) {
        return { bid: 0, color: '#6c757d', skip: true, off: false, title: 'No CP Master Dil' };
    }
    const esBid = parseFloat(row && row.suggested_bid) || 0;
    let prevMax = null;
    for (let i = 0; i < currentDilSbidSlabs.length; i++) {
        const slab = currentDilSbidSlabs[i];
        if (dilSbidContains(dil, slab, prevMax)) {
            const mode = dilSbidMode(i);
            let base = null;
            let title = '';
            let color = '#0d6efd';
            if (mode === 'es_bid') {
                if (!(esBid > 0)) return { bid: 0, color: '#6c757d', skip: true, off: false, title: 'Dil 0 but ES Bid is empty' };
                base = esBid;
                title = 'Dil 0 → ES Bid';
                color = '#0dcaf0';
            } else {
                const typed = parseFloat(slab.bid);
                if (!(isFinite(typed) && typed > 0)) {
                    return { bid: 0, color: '#6c757d', skip: true, off: false, title: 'Type an S Bid % on this slab' };
                }
                base = typed;
                title = 'Dil ' + dilSbidRound(dil) + '% → S Bid ' + typed + '%';
            }
            const adj = dilSbidApplyCvr(base, row);
            if (adj.why) {
                const sign = adj.adj > 0 ? '+' : '';
                title += ' ' + sign + adj.adj + ' (' + adj.why + ') → ' + adj.bid + '%';
            }
            if (!(adj.bid > 0)) return { bid: 0, color: '#6c757d', skip: true, off: false, title: title };
            return { bid: adj.bid, color: color, skip: false, off: false, title: title };
        }
        prevMax = parseFloat(slab.max);
    }
    return { bid: 0, color: '#6c757d', skip: true, off: false, title: 'No matching Dil slab' };
}
function dilSbidCounts() {
    const counts = currentDilSbidSlabs.map(function() { return 0; });
    const rows = dilSbidRows();
    const seen = {};
    rows.forEach(function(d) {
        const sku = dilSbidSkuKey(d);
        if (!sku || seen[sku]) return;
        const dil = dilSbidMetric(d);
        if (dil === null || !isFinite(dil)) return;
        seen[sku] = true;
        let prevMax = null;
        for (let i = 0; i < currentDilSbidSlabs.length; i++) {
            if (dilSbidContains(dil, currentDilSbidSlabs[i], prevMax)) {
                counts[i]++;
                break;
            }
            prevMax = parseFloat(currentDilSbidSlabs[i].max);
        }
    });
    return counts;
}
function dilSbidCvrCounts() {
    const counts = { down: 0, up: 0 };
    const cfg = currentDilSbidCvr || DIL_SBID_CVR_DEFAULTS;
    const rows = dilSbidRows();
    const seen = {};
    rows.forEach(function(d) {
        const sku = dilSbidSkuKey(d);
        if (!sku || seen[sku]) return;
        const parts = dilSbidCvrParts(d);
        if (!parts) return;
        seen[sku] = true;
        const trend = dilSbidCvrTrend(parts);
        if (trend === 'down' && parts.cvr < cfg.down_lt) counts.down++;
        else if (trend === 'up' && parts.cvr > cfg.up_gt) counts.up++;
    });
    return counts;
}
function dilSbidRead() {
    const rows = [];
    document.querySelectorAll('#dil-sbid-tbody tr').forEach(function(tr, i) {
        const min = parseFloat(tr.querySelector('.dil-sbid-min').value);
        const max = parseFloat(tr.querySelector('.dil-sbid-max').value);
        const bidEl = tr.querySelector('.dil-sbid-bid');
        rows.push({
            min: isFinite(min) ? min : 0,
            max: isFinite(max) ? max : 0,
            mode: dilSbidMode(i),
            bid: bidEl ? parseFloat(bidEl.value) : null
        });
    });
    if (rows.length) currentDilSbidSlabs = rows;
    return currentDilSbidSlabs;
}
function renderDilSbidTable() {
    const tbody = document.getElementById('dil-sbid-tbody');
    if (!tbody) return;
    const counts = dilSbidCounts();
    tbody.innerHTML = '';
    currentDilSbidSlabs.forEach(function(slab, i) {
        const mode = dilSbidMode(i);
        const color = DIL_SBID_COLORS[i % DIL_SBID_COLORS.length];
        let bidCell = '';
        if (mode === 'es_bid') {
            bidCell = '<span class="dil-sbid-badge dil-sbid-es" title="This slab uses each listing’s ES Bid">ES BID</span>';
        } else {
            const bid = (slab.bid === null || slab.bid === undefined || slab.bid === '') ? '' : slab.bid;
            bidCell = '<input type="number" min="0" step="0.1" class="form-control form-control-sm text-end fw-semibold dil-sbid-bid" value="' + bid + '" title="S Bid % for Dil in this slab">';
        }
        const tr = document.createElement('tr');
        tr.innerHTML = ''
            + '<td><input type="number" step="0.1" class="form-control form-control-sm text-end dil-sbid-min" value="' + slab.min + '"></td>'
            + '<td><input type="number" step="0.1" class="form-control form-control-sm text-end dil-sbid-max" value="' + slab.max + '"></td>'
            + '<td class="text-center fw-semibold"><span class="dil-sbid-count">' + (counts[i] || 0) + '</span> <span class="dil-sbid-dot" style="background:' + color + '"></span></td>'
            + '<td class="text-end">' + bidCell + '</td>'
            + '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 dil-sbid-del" data-idx="' + i + '" title="Remove slab">&times;</button></td>';
        tbody.appendChild(tr);
    });
    dilSbidPaintCounts();
}
function dilSbidScheduleSave() {
    clearTimeout(dilSbidSaveTimer);
    dilSbidSaveTimer = setTimeout(function() { dilSbidSave(false); }, 500);
}
function dilSbidPaintCounts() {
    const counts = dilSbidCounts();
    document.querySelectorAll('#dil-sbid-tbody tr').forEach(function(tr, i) {
        const el = tr.querySelector('.dil-sbid-count');
        if (el) el.textContent = String(counts[i] || 0);
    });
    const cvrCounts = dilSbidCvrCounts();
    const down = document.getElementById('dil-sbid-cvr-down-count');
    const up = document.getElementById('dil-sbid-cvr-up-count');
    if (down) down.textContent = String(cvrCounts.down || 0);
    if (up) up.textContent = String(cvrCounts.up || 0);
}
function dilSbidSave(thenApply) {
    const errEl = document.getElementById('dil-sbid-err');
    const statusEl = document.getElementById('dil-sbid-status');
    if (errEl) errEl.classList.add('d-none');
    dilSbidRead();
    for (let i = 0; i < currentDilSbidSlabs.length; i++) {
        if (!(parseFloat(currentDilSbidSlabs[i].max) >= parseFloat(currentDilSbidSlabs[i].min))) {
            if (errEl) {
                errEl.textContent = 'Slab ' + (i + 1) + ': To must be at least From';
                errEl.classList.remove('d-none');
            }
            return;
        }
    }
    $.ajax({
        url: DIL_SBID_SAVE_URL,
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        contentType: 'application/json',
        data: JSON.stringify({ slabs: currentDilSbidSlabs, enabled: !!dilSbidEnabled, cvr: dilSbidCvrNow() }),
        success: function(resp) {
            const before = currentDilSbidSlabs.length;
            if (resp && Array.isArray(resp.slabs) && resp.slabs.length) currentDilSbidSlabs = resp.slabs;
            if (currentDilSbidSlabs.length !== before) renderDilSbidTable();
            if (typeof table !== 'undefined' && table && table.redraw) table.redraw(true);
            dilSbidPaintCounts();
            if (thenApply) dilSbidApply();
            else if (statusEl) statusEl.textContent = 'Saved';
        },
        error: function(xhr) {
            if (errEl) {
                errEl.textContent = (xhr.responseJSON && xhr.responseJSON.error) || 'Could not save';
                errEl.classList.remove('d-none');
            }
        }
    });
}
function dilSbidPaintStatus(resp) {
    if (typeof table === 'undefined' || !table || !resp || !Array.isArray(resp.results)) return;
    const paused = {};
    const running = {};
    resp.results.forEach(function(r) {
        if (!r || r.listing_id == null || r.status !== 'pushed') return;
        const id = String(r.listing_id);
        if (r.bid === 'OFF') paused[id] = true;
        else running[id] = true;
    });
    table.getRows().forEach(function(row) {
        const id = String((row.getData() || {}).listing_id || '');
        if (paused[id]) row.update({ campaign_status: 'PAUSED' });
        else if (running[id]) row.update({ campaign_status: 'RUNNING' });
    });
}
function dilSbidApply() {
    const errEl = document.getElementById('dil-sbid-err');
    const statusEl = document.getElementById('dil-sbid-status');
    const btn = document.getElementById('dil-sbid-apply-btn');
    const ids = [];
    const seenIds = {};
    dilSbidRows().forEach(function(d) {
        const id = d && (d.listing_id || d.eBay_item_id || d.ebay_item_id);
        if (!id || seenIds[id]) return;
        seenIds[id] = true;
        ids.push(String(id));
    });
    if (!ids.length) {
        if (statusEl) statusEl.textContent = 'No listings loaded';
        return;
    }
    if (btn) btn.disabled = true;
    if (statusEl) statusEl.textContent = 'Applying ' + ids.length + '…';
    $.ajax({
        url: DIL_SBID_APPLY_URL,
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        contentType: 'application/json',
        data: JSON.stringify({ listing_ids: ids }),
        timeout: 180000,
        success: function(resp) {
            if (btn) btn.disabled = false;
            if (resp && resp.error) {
                if (errEl) { errEl.textContent = resp.error; errEl.classList.remove('d-none'); }
                if (statusEl) statusEl.textContent = '';
                return;
            }
            const s = resp.success || 0, f = resp.failed || 0, sk = resp.skipped || 0;
            dilSbidPaintStatus(resp);
            if (statusEl) statusEl.textContent = 'Applied: ' + s + ' pushed · ' + f + ' failed · ' + sk + ' skipped';
        },
        error: function(xhr) {
            if (btn) btn.disabled = false;
            if (errEl) {
                errEl.textContent = (xhr.responseJSON && xhr.responseJSON.error) || xhr.responseText || 'Apply failed';
                errEl.classList.remove('d-none');
            }
        }
    });
}

$.get(DIL_SBID_GET_URL, function(data) {
    if (data && Array.isArray(data.slabs) && data.slabs.length) {
        currentDilSbidSlabs = data.slabs.filter(function(s) { return s && s.mode !== 'auto_off'; });
    }
    dilSbidEnabled = !!(data && data.enabled);
    if (data && data.cvr) currentDilSbidCvr = Object.assign({}, DIL_SBID_CVR_DEFAULTS, data.cvr);
    dilSbidPaintCvr();
    dilSbidPaintMode();
    renderDilSbidTable();
    if (typeof table !== 'undefined' && table && table.redraw) table.redraw(true);
});
document.getElementById('dil-sbid-enabled').addEventListener('change', function() {
    dilSbidEnabled = !!this.checked;
    dilSbidPaintMode();
    if (typeof table !== 'undefined' && table && table.redraw) table.redraw(true);
    clearTimeout(dilSbidSaveTimer);
    dilSbidSave(false);
});
document.getElementById('dilSbidRuleModal').addEventListener('show.bs.modal', function() {
    renderDilSbidTable();
});
document.getElementById('dil-sbid-tbody').addEventListener('input', function() {
    dilSbidRead();
    dilSbidPaintCounts();
    if (typeof table !== 'undefined' && table && table.redraw) table.redraw(true);
    dilSbidScheduleSave();
});
document.getElementById('dil-sbid-tbody').addEventListener('click', function(ev) {
    const btn = ev.target.closest('.dil-sbid-del');
    if (!btn) return;
    dilSbidRead();
    if (currentDilSbidSlabs.length <= 1) return;
    currentDilSbidSlabs.splice(parseInt(btn.getAttribute('data-idx'), 10), 1);
    renderDilSbidTable();
    if (typeof table !== 'undefined' && table && table.redraw) table.redraw(true);
    dilSbidScheduleSave();
});
document.getElementById('dil-sbid-add-btn').addEventListener('click', function() {
    dilSbidRead();
    let next = 0;
    currentDilSbidSlabs.forEach(function(s) {
        const max = parseFloat(s.max);
        if (isFinite(max) && max > next && max < 9999) next = max;
    });
    const max = next >= 100 ? 9999 : dilSbidRound(next + 10);
    currentDilSbidSlabs.push({ min: next, max: max, mode: 'dynamic', bid: '' });
    renderDilSbidTable();
    dilSbidScheduleSave();
});
document.getElementById('dil-sbid-apply-btn').addEventListener('click', function() {
    clearTimeout(dilSbidSaveTimer);
    dilSbidSave(true);
});
document.getElementById('dil-sbid-cvr-table').addEventListener('input', function() {
    dilSbidCvrNow();
    dilSbidPaintCounts();
    if (typeof table !== 'undefined' && table && table.redraw) table.redraw(true);
    dilSbidScheduleSave();
});
@endif
