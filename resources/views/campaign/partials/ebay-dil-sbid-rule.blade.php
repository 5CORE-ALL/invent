@php
    $dilSbidAccount = $account ?? 'eBay';
@endphp
@if(($part ?? 'modal') === 'modal')
<div class="modal fade is-off" id="dilSbidRuleModal" tabindex="-1" aria-labelledby="dilSbidRuleModalLabel" aria-hidden="true">
    <style>
        #dilSbidRuleModal .modal-dialog { max-width: 720px; }
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
                <p class="small mb-2" id="dil-sbid-mode-note">Off. S Bid uses View VS SBID (For L7 Views).</p>
                <ul class="small text-muted mb-3 ps-3">
                    <li>Dil = (L30 sold ÷ Inventory) × 100. Inventory 0 counts as Dil 0. First matching slab wins.</li>
                    <li><strong>0–0</strong> applies that listing’s <strong>ES Bid</strong>.</li>
                    <li><strong>0.1–10%</strong> uses the <strong>S Bid %</strong> you type on that row.</li>
                    <li><strong>10–20 … &gt;100%</strong> is <strong>Auto Off</strong> (the promoted listing is paused). The last slab (To 9999) catches Dil above 100.</li>
                    <li>Saved for {{ $dilSbidAccount }} only. eBay, eBay 2, and eBay 3 each keep their own slabs.</li>
                </ul>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0" id="dil-sbid-table">
                        <thead>
                            <tr>
                                <th class="text-center" style="width:110px;">From</th>
                                <th class="text-center" style="width:110px;">To</th>
                                <th class="text-center" style="width:90px;" title="Listings on this page whose Dil is in this slab">Count</th>
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
const DIL_SBID_COLORS = ['#6f42c1','#3b82f6','#14b8a6','#22c55e','#84cc16','#eab308','#f59e0b','#ea580c','#dc3545','#e83e8c','#7c3aed','#0ea5e9'];
let currentDilSbidSlabs = DIL_SBID_DEFAULTS.map(function(s) { return Object.assign({}, s); });
var dilSbidEnabled = false;
let dilSbidSaveTimer = null;

function campaignSbid(row) {
    if (dilSbidEnabled) return dilSbidOfRow(row);
    const res = (typeof getCombinedSbid === 'function') ? getCombinedSbid(row) : { bid: 0, color: '#6c757d', skip: true };
    if (res && res.zeroSoldMax) res.title = 'E L30 = 0 → maximum S Bid %';
    else if (res && !res.skip) res.title = 'View VS SBID';
    return res;
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
            ? 'On. S Bid uses these Dil slabs.'
            : 'Off. S Bid uses View VS SBID (For L7 Views).';
        note.className = on ? 'small mb-2 text-success' : 'small mb-2 text-muted';
    }
    if (modal) modal.classList.toggle('is-off', !on);
}

function dilSbidMode(i) {
    if (i <= 0) return 'es_bid';
    if (i === 1) return 'dynamic';
    return 'auto_off';
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
function dilSbidOfRow(row) {
    const dil = (typeof dilValue === 'function') ? dilValue(row) : 0;
    const esBid = parseFloat(row && row.suggested_bid) || 0;
    let prevMax = null;
    for (let i = 0; i < currentDilSbidSlabs.length; i++) {
        const slab = currentDilSbidSlabs[i];
        if (dilSbidContains(dil, slab, prevMax)) {
            const mode = dilSbidMode(i);
            if (mode === 'auto_off') {
                return { bid: -1, color: '#842029', skip: false, off: true, title: 'Dil ' + dilSbidRound(dil) + '% → Auto Off' };
            }
            if (mode === 'es_bid') {
                if (esBid > 0) return { bid: esBid, color: '#0dcaf0', skip: false, off: false, title: 'Dil 0 → ES Bid' };
                return { bid: 0, color: '#6c757d', skip: true, off: false, title: 'Dil 0 but ES Bid is empty' };
            }
            const bid = parseFloat(slab.bid);
            if (isFinite(bid) && bid > 0) {
                return { bid: bid, color: '#0d6efd', skip: false, off: false, title: 'Dil ' + dilSbidRound(dil) + '% → S Bid ' + bid + '%' };
            }
            return { bid: 0, color: '#6c757d', skip: true, off: false, title: 'Type an S Bid % on the 0.1–10 slab' };
        }
        prevMax = parseFloat(slab.max);
    }
    return { bid: 0, color: '#6c757d', skip: true, off: false, title: 'No matching Dil slab' };
}
function dilSbidCounts() {
    const counts = currentDilSbidSlabs.map(function() { return 0; });
    let rows = [];
    try {
        if (typeof table !== 'undefined' && table && typeof table.getData === 'function') rows = table.getData() || [];
    } catch (e) { rows = []; }
    rows.forEach(function(d) {
        const dil = (typeof dilValue === 'function') ? dilValue(d) : 0;
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
        } else if (mode === 'auto_off') {
            bidCell = '<span class="dil-sbid-badge dil-sbid-off" title="Promoted listing is paused">Auto Off</span>';
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
        data: JSON.stringify({ slabs: currentDilSbidSlabs, enabled: !!dilSbidEnabled }),
        success: function(resp) {
            if (resp && Array.isArray(resp.slabs) && resp.slabs.length) currentDilSbidSlabs = resp.slabs;
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
function dilSbidApply() {
    const errEl = document.getElementById('dil-sbid-err');
    const statusEl = document.getElementById('dil-sbid-status');
    const btn = document.getElementById('dil-sbid-apply-btn');
    const ids = [];
    try {
        if (typeof table !== 'undefined' && table) {
            (table.getData() || []).forEach(function(d) {
                if (d && d.listing_id) ids.push(String(d.listing_id));
            });
        }
    } catch (e) {}
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
    if (data && Array.isArray(data.slabs) && data.slabs.length) currentDilSbidSlabs = data.slabs;
    dilSbidEnabled = !!(data && data.enabled);
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
    currentDilSbidSlabs.push({ min: next, max: max, mode: 'auto_off', bid: null });
    renderDilSbidTable();
    dilSbidScheduleSave();
});
document.getElementById('dil-sbid-apply-btn').addEventListener('click', function() {
    clearTimeout(dilSbidSaveTimer);
    dilSbidSave(true);
});
@endif
