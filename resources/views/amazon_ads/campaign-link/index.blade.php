@extends('layouts.vertical', ['title' => 'Amazon Campaign Link'])

@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<style>
    .tabulator { font-size: 13px; border: 1px solid #dee2e6; }
    .tabulator .tabulator-header {
        background: linear-gradient(90deg, #e0e7ff 0%, #f4f7fa 100%);
        border-bottom: 2px solid #2563eb; font-weight: 600;
    }
    .tabulator-row { min-height: 38px !important; }
    .tabulator-row:hover { background-color: #f8f9fa !important; }
    .tabulator-cell { padding: 8px !important; }
    .tabulator .tabulator-cell.acl-linked-col { padding: 4px 8px !important; }
    .acl-linked-wrap { display: flex; flex-wrap: wrap; align-items: center; gap: 4px; }
    .acl-linked-badge {
        display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 999px;
        background: #eef2ff; border: 1px solid #c7d2fe; color: #3730a3; font-size: 12px; max-width: 260px;
    }
    .acl-linked-badge span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .acl-linked-badge .acl-remove { font-size: 0.6rem; opacity: 0.6; padding: 0; }
    .acl-linked-badge .acl-remove:hover { opacity: 1; }
    .acl-link-btn { padding: 2px 8px; }
    .acl-suggestion-item { cursor: pointer; }
    .acl-suggestion-item .form-check-input { pointer-events: none; }
    .acl-selected-chip {
        display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 999px;
        background: #f1f5f9; border: 1px solid #e2e8f0; font-size: 12px;
    }
    .acl-selected-chip button { border: 0; background: transparent; padding: 0; line-height: 1; font-size: 14px; color: #64748b; }
</style>
@endsection

@section('content')
<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="mdi mdi-link-variant"></i> Amazon Campaign Link</h4>
                    <span class="small">Link SP campaigns into groups so keywords can be pushed across the whole group.</span>
                </div>
                <div class="card-body">
                    <div class="mb-3 d-flex gap-2 flex-wrap align-items-center">
                        <input type="text" id="acl-search-campaign" class="form-control form-control-sm"
                               style="max-width: 320px;" placeholder="Search campaign...">
                        <button type="button" id="acl-bulk-push" class="btn btn-sm btn-success ms-auto"
                                title="Import keywords into every linked campaign at once">
                            <i class="fas fa-cloud-download-alt"></i> Bulk Push (all linked)
                        </button>
                    </div>
                    <div id="acl-table"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="aclLinkModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-link"></i> Link Campaigns</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2">Link one or more campaigns to <strong id="acl-source"></strong>. All linked campaigns will share keywords.</p>
                <label for="acl-input" class="form-label mb-1">Search campaign to link</label>
                <input type="text" id="acl-input" class="form-control" placeholder="Search campaign..." autocomplete="off">
                <div id="acl-suggestions" class="list-group mt-2 d-none" style="max-height: 260px; overflow-y: auto;"></div>
                <div id="acl-selected-wrap" class="mt-2 d-none">
                    <div class="small text-muted mb-1">Selected to link (<span id="acl-selected-modal-count">0</span>):</div>
                    <div id="acl-selected-campaigns" class="d-flex flex-wrap gap-1"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="acl-save-btn">
                    <i class="fas fa-link"></i> <span id="acl-save-btn-label">Link Campaign(s)</span>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="aclKeywordsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title"><i class="fas fa-key"></i> Keywords — <span id="acl-kw-title"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="acl-kw-loading" class="text-center py-4">
                    <div class="spinner-border text-secondary" role="status"><span class="visually-hidden">Loading...</span></div>
                    <p class="mt-2 mb-0">Loading keywords...</p>
                </div>
                <div id="acl-kw-empty" class="alert alert-info mb-0 d-none"><i class="fa fa-info-circle"></i> No keywords found for this campaign.</div>
                <div id="acl-kw-error" class="alert alert-danger mb-0 d-none"></div>
                <div id="acl-kw-table-wrap" class="table-responsive d-none" style="max-height: 65vh;">
                    <table class="table table-sm table-bordered table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Keyword</th>
                                <th style="width: 90px;">Match</th>
                                <th style="width: 70px;" class="text-end">Impr</th>
                                <th style="width: 60px;" class="text-end">Clk</th>
                                <th style="width: 80px;" class="text-end">Cost</th>
                                <th style="width: 70px;" class="text-end">CPC</th>
                                <th style="width: 60px;" class="text-end">Sold</th>
                                <th style="width: 80px;" class="text-end">Sales</th>
                                <th style="width: 70px;" class="text-end">ACOS</th>
                            </tr>
                        </thead>
                        <tbody id="acl-kw-tbody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="aclCompareModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-scale-balanced"></i> Live Compare — <span id="acl-cmp-title"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="acl-cmp-loading" class="text-center py-4">
                    <div class="spinner-border text-info" role="status"><span class="visually-hidden">Loading...</span></div>
                    <p class="mt-2 mb-0">Checking Amazon live…</p>
                </div>
                <div id="acl-cmp-error" class="alert alert-danger mb-0 d-none"></div>
                <div id="acl-cmp-body" class="d-none">
                    <div id="acl-cmp-summary" class="alert alert-light border mb-3"></div>
                    <div id="acl-cmp-empty" class="alert alert-success mb-0 d-none"><i class="fa fa-check-circle"></i> Nothing missing — this campaign already has every keyword from its linked campaign(s).</div>
                    <div id="acl-cmp-table-wrap" class="table-responsive d-none" style="max-height: 55vh;">
                        <table class="table table-sm table-bordered table-hover mb-0">
                            <thead class="table-light">
                                <tr><th>#</th><th>Missing Keyword</th><th style="width: 120px;">Match</th></tr>
                            </thead>
                            <tbody id="acl-cmp-tbody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success d-none" id="acl-cmp-push-btn"><i class="fas fa-cloud-download-alt"></i> Push these now</button>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const dataUrl = @json(route('amazon.ads.campaign-link.data'));
    const campaignsUrl = @json(route('amazon.ads.campaign-link.campaigns'));
    const keywordsUrl = @json(route('amazon.ads.campaign-link.keywords'));
    const linkUrl = @json(route('amazon.ads.campaign-link.link'));
    const removeUrl = @json(route('amazon.ads.campaign-link.remove'));
    const pushUrl = @json(route('amazon.ads.campaign-link.push'));
    const pushAllUrl = @json(route('amazon.ads.campaign-link.push-all'));
    const compareUrl = @json(route('amazon.ads.campaign-link.compare'));

    let table = null;
    let modalSource = '';
    let modalExisting = [];
    const selectedInModal = new Set();
    const linkModal = new bootstrap.Modal(document.getElementById('aclLinkModal'));
    const keywordsModal = new bootstrap.Modal(document.getElementById('aclKeywordsModal'));
    const compareModal = new bootstrap.Modal(document.getElementById('aclCompareModal'));
    let compareCampaign = '';

    // Update a row's live keyword count in the grid so it stays consistent with Amazon.
    function updateRowLiveCount(campaign, liveCount) {
        if (liveCount === null || liveCount === undefined || !table) return;
        const rowComp = table.getRows().find(r => r.getData().campaign === campaign);
        if (rowComp) { rowComp.update({ keyword_count: liveCount }); }
    }

    async function openCompareModal(campaign) {
        compareCampaign = campaign;
        document.getElementById('acl-cmp-title').textContent = campaign;
        const loading = document.getElementById('acl-cmp-loading');
        const errorEl = document.getElementById('acl-cmp-error');
        const bodyEl = document.getElementById('acl-cmp-body');
        const emptyEl = document.getElementById('acl-cmp-empty');
        const wrap = document.getElementById('acl-cmp-table-wrap');
        const pushBtn = document.getElementById('acl-cmp-push-btn');
        loading.classList.remove('d-none');
        errorEl.classList.add('d-none');
        bodyEl.classList.add('d-none');
        emptyEl.classList.add('d-none');
        wrap.classList.add('d-none');
        pushBtn.classList.add('d-none');
        document.getElementById('acl-cmp-tbody').innerHTML = '';
        compareModal.show();
        try {
            const url = new URL(compareUrl, window.location.origin);
            url.searchParams.set('campaign', campaign);
            const res = await fetch(url, { headers: { Accept: 'application/json' } });
            const json = await res.json();
            if (!json.success) throw new Error(json.message || 'Compare failed.');
            loading.classList.add('d-none');
            bodyEl.classList.remove('d-none');
            const liveTxt = json.live_ok ? (json.dest_live_count + ' (live on Amazon)') : (json.dest_live_count + ' (Amazon check unavailable — using stored data)');
            document.getElementById('acl-cmp-summary').innerHTML =
                '<strong>' + escHtml(campaign) + '</strong> currently has <strong>' + liveTxt + '</strong> keyword(s).<br>'
                + 'Linked campaign(s) [' + (json.linked_campaigns || []).map(escHtml).join(', ') + '] contain <strong>' + json.group_source_count + '</strong> keyword(s).<br>'
                + '<strong>' + json.missing_count + '</strong> are missing here and would be pushed.';
            // Keep the grid count consistent with the live number.
            updateRowLiveCount(campaign, json.dest_live_count);
            const miss = json.missing || [];
            if (miss.length === 0) { emptyEl.classList.remove('d-none'); return; }
            document.getElementById('acl-cmp-tbody').innerHTML = miss.map(function (m, i) {
                return '<tr><td>' + (i + 1) + '</td><td>' + escHtml(m.keywordText) + '</td><td>' + escHtml(m.matchType) + '</td></tr>';
            }).join('');
            wrap.classList.remove('d-none');
            pushBtn.classList.remove('d-none');
        } catch (e) {
            loading.classList.add('d-none');
            errorEl.textContent = e.message;
            errorEl.classList.remove('d-none');
        }
    }

    document.getElementById('acl-cmp-push-btn').addEventListener('click', async function () {
        if (!compareCampaign) return;
        this.disabled = true;
        const original = this.innerHTML;
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Pushing...';
        try {
            const json = await postJson(pushUrl, { campaign: compareCampaign });
            alert(json.message || 'Done.');
            updateRowLiveCount(compareCampaign, json.dest_live_count);
            compareModal.hide();
        } catch (err) { alert(err.message); }
        finally { this.disabled = false; this.innerHTML = original; }
    });

    function escHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
    }
    function escAttr(s) { return escHtml(s).replace(/'/g, '&#39;'); }

    async function postJson(url, body) {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify(body),
        });
        const json = await res.json().catch(() => ({}));
        if (!res.ok || !json.success) { throw new Error(json.message || 'Request failed.'); }
        return json;
    }

    // ---- Keyword count cell (clickable) ----
    function keywordCountFormatter(cell) {
        const n = cell.getValue();
        const campaign = cell.getRow().getData().campaign;
        if (!n) { return '<span class="text-muted">0</span>'; }
        return '<a href="#" class="acl-kw-open fw-semibold text-primary" data-campaign="' + escAttr(campaign) + '">' + escHtml(String(n)) + '</a>';
    }

    function fmtNum(v, dec) {
        if (v === null || v === undefined || v === '') return '<span class="text-muted">—</span>';
        const n = Number(v);
        if (!isFinite(n)) return '<span class="text-muted">—</span>';
        return dec ? n.toLocaleString('en-US', { minimumFractionDigits: dec, maximumFractionDigits: dec }) : n.toLocaleString('en-US');
    }

    async function openKeywordsModal(campaign) {
        document.getElementById('acl-kw-title').textContent = campaign;
        const loading = document.getElementById('acl-kw-loading');
        const empty = document.getElementById('acl-kw-empty');
        const errorEl = document.getElementById('acl-kw-error');
        const wrap = document.getElementById('acl-kw-table-wrap');
        const tbody = document.getElementById('acl-kw-tbody');
        loading.classList.remove('d-none');
        empty.classList.add('d-none');
        errorEl.classList.add('d-none');
        wrap.classList.add('d-none');
        tbody.innerHTML = '';
        keywordsModal.show();
        try {
            const url = new URL(keywordsUrl, window.location.origin);
            url.searchParams.set('campaign', campaign);
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            if (!json.success) throw new Error(json.message || 'Failed to load keywords.');
            loading.classList.add('d-none');
            const kws = json.keywords || [];
            if (kws.length === 0) { empty.classList.remove('d-none'); return; }
            tbody.innerHTML = kws.map(function (k) {
                return '<tr>'
                    + '<td>' + escHtml(k.keyword) + '</td>'
                    + '<td>' + escHtml(k.match_type || '—') + '</td>'
                    + '<td class="text-end">' + fmtNum(k.impressions) + '</td>'
                    + '<td class="text-end">' + fmtNum(k.clicks) + '</td>'
                    + '<td class="text-end">' + fmtNum(k.cost, 2) + '</td>'
                    + '<td class="text-end">' + fmtNum(k.cpc, 2) + '</td>'
                    + '<td class="text-end">' + fmtNum(k.sold) + '</td>'
                    + '<td class="text-end">' + fmtNum(k.sales, 2) + '</td>'
                    + '<td class="text-end">' + (k.acos === null || k.acos === undefined ? '<span class="text-muted">—</span>' : (Number(k.acos).toFixed(1) + '%')) + '</td>'
                    + '</tr>';
            }).join('');
            document.getElementById('acl-kw-title').textContent = campaign + ' (' + kws.length + ')';
            wrap.classList.remove('d-none');
        } catch (e) {
            loading.classList.add('d-none');
            errorEl.textContent = e.message;
            errorEl.classList.remove('d-none');
        }
    }

    // ---- Linked campaigns cell ----
    function linkedCellFormatter(cell) {
        const row = cell.getRow().getData();
        const campaign = row.campaign;
        const group = Array.isArray(row.linked_campaigns) ? row.linked_campaigns : [campaign];
        const others = group.filter(c => c !== campaign);
        const campAttr = escAttr(campaign);
        let html = '<div class="acl-linked-wrap">';
        others.forEach(function (c) {
            html += '<span class="acl-linked-badge"><span title="' + escAttr(c) + '">' + escHtml(c) + '</span>'
                 + '<button type="button" class="btn-close acl-remove" data-campaign="' + campAttr + '" data-linked="' + escAttr(c) + '" aria-label="Remove"></button></span>';
        });
        html += '<button type="button" class="btn btn-sm btn-outline-primary acl-link-btn" data-campaign="' + campAttr + '" title="Link a campaign"><i class="fas fa-plus"></i></button>';
        if (others.length > 0) {
            html += '<button type="button" class="btn btn-sm btn-outline-info acl-compare-btn ms-1" data-campaign="' + campAttr + '" title="Live-check Amazon: what is actually missing vs linked campaign(s)">'
                 + '<i class="fas fa-scale-balanced"></i> Compare</button>';
            html += '<button type="button" class="btn btn-sm btn-success acl-push-btn ms-1" data-campaign="' + campAttr + '" title="Import all keywords from linked campaign(s) into this campaign">'
                 + '<i class="fas fa-cloud-download-alt"></i> Push</button>';
        }
        html += '</div>';
        return html;
    }

    // ---- Link modal ----
    function renderSelectedChips() {
        const wrap = document.getElementById('acl-selected-wrap');
        const list = document.getElementById('acl-selected-campaigns');
        const count = document.getElementById('acl-selected-modal-count');
        const saveLabel = document.getElementById('acl-save-btn-label');
        const arr = Array.from(selectedInModal);
        count.textContent = String(arr.length);
        saveLabel.textContent = arr.length > 1 ? 'Link Campaign(s)' : 'Link Campaign';
        if (arr.length === 0) { wrap.classList.add('d-none'); list.innerHTML = ''; return; }
        wrap.classList.remove('d-none');
        list.innerHTML = arr.map(c =>
            '<span class="acl-selected-chip">' + escHtml(c) +
            '<button type="button" class="acl-selected-remove" data-c="' + escAttr(c) + '" title="Remove">&times;</button></span>'
        ).join('');
    }

    let suggestTimer = null;
    let suggestReqId = 0;
    async function renderSuggestions(term) {
        const wrap = document.getElementById('acl-suggestions');
        const q = String(term || '').trim();
        // Match sku-link-lmp: only show suggestions once the user types a search term.
        if (q === '') { wrap.classList.add('d-none'); wrap.innerHTML = ''; return; }
        const reqId = ++suggestReqId;
        try {
            const url = new URL(campaignsUrl, window.location.origin);
            url.searchParams.set('q', q);
            url.searchParams.set('exclude', modalSource);
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            if (reqId !== suggestReqId) return;
            const srcNorm = modalSource.toUpperCase();
            const existingNorm = new Set(modalExisting.map(c => String(c).toUpperCase()));
            const list = (json.campaigns || [])
                .map(c => String(c).trim())
                .filter(c => c && c.toUpperCase() !== srcNorm && !existingNorm.has(c.toUpperCase()));
            if (list.length === 0) { wrap.classList.add('d-none'); wrap.innerHTML = ''; return; }
            wrap.classList.remove('d-none');
            wrap.innerHTML = list.map(function (c) {
                const checked = selectedInModal.has(c) ? 'checked' : '';
                return '<div class="list-group-item list-group-item-action py-2 acl-suggestion-item d-flex align-items-center gap-2 mb-0">'
                     + '<input type="checkbox" class="form-check-input acl-suggestion-cb" value="' + escAttr(c) + '" ' + checked + '>'
                     + '<span class="flex-grow-1">' + escHtml(c) + '</span></div>';
            }).join('');
        } catch (e) {
            if (reqId !== suggestReqId) return;
            wrap.classList.add('d-none'); wrap.innerHTML = '';
        }
    }

    function openLinkModal(sourceCampaign, existingLinked) {
        modalSource = sourceCampaign;
        modalExisting = Array.isArray(existingLinked) ? existingLinked.filter(c => c !== sourceCampaign) : [];
        selectedInModal.clear();
        document.getElementById('acl-source').textContent = sourceCampaign;
        document.getElementById('acl-input').value = '';
        const wrap = document.getElementById('acl-suggestions');
        wrap.classList.add('d-none'); wrap.innerHTML = '';
        renderSelectedChips();
        linkModal.show();
        setTimeout(() => document.getElementById('acl-input')?.focus(), 300);
    }

    document.getElementById('acl-input').addEventListener('input', function () {
        if (suggestTimer) clearTimeout(suggestTimer);
        const v = this.value;
        suggestTimer = setTimeout(() => renderSuggestions(v), 250);
    });
    document.getElementById('acl-suggestions').addEventListener('click', function (e) {
        const item = e.target.closest('.acl-suggestion-item'); if (!item) return;
        const cb = item.querySelector('.acl-suggestion-cb'); if (!cb) return;
        // Row is a <div> (no native label toggle) and the checkbox has pointer-events:none,
        // so this handler is the single source of truth: always toggle once.
        cb.checked = !cb.checked;
        if (cb.checked) selectedInModal.add(cb.value); else selectedInModal.delete(cb.value);
        renderSelectedChips();
    });
    document.getElementById('acl-selected-campaigns').addEventListener('click', function (e) {
        const btn = e.target.closest('.acl-selected-remove'); if (!btn) return;
        selectedInModal.delete(btn.dataset.c);
        document.querySelectorAll('.acl-suggestion-cb').forEach(cb => { if (cb.value === btn.dataset.c) cb.checked = false; });
        renderSelectedChips();
    });

    document.getElementById('acl-save-btn').addEventListener('click', async function () {
        // Only link campaigns explicitly selected via checkboxes — the search box is a filter,
        // not a way to add arbitrary text as a campaign.
        const targets = Array.from(selectedInModal);
        if (targets.length === 0) { alert('Select at least one campaign to link.'); return; }
        const btn = this; btn.disabled = true;
        try {
            const json = await postJson(linkUrl, { campaigns: [modalSource, ...targets] });
            applyAffected(json.affected || []);
            linkModal.hide();
        } catch (e) { alert(e.message); }
        finally { btn.disabled = false; }
    });

    // Update affected rows in place, else reload.
    function applyAffected(affected) {
        if (!table || !Array.isArray(affected) || affected.length === 0) { if (table) table.setData(); return; }
        let missing = false;
        affected.forEach(function (row) {
            const existing = table.getRows().find(r => r.getData().campaign === row.campaign);
            if (existing) { existing.update(row); } else { missing = true; }
        });
        if (missing) table.setData();
    }

    // ---- Grid events (remove / link buttons / keyword count) ----
    document.getElementById('acl-table').addEventListener('click', async function (e) {
        const kwOpen = e.target.closest('.acl-kw-open');
        if (kwOpen) {
            e.preventDefault();
            e.stopPropagation();
            const campaign = kwOpen.dataset.campaign || '';
            if (campaign) openKeywordsModal(campaign);
            return;
        }
        const removeBtn = e.target.closest('.acl-remove');
        if (removeBtn) {
            e.stopPropagation();
            const campaign = removeBtn.dataset.campaign || '';
            const linked = removeBtn.dataset.linked || '';
            if (!campaign || !linked) return;
            try {
                const json = await postJson(removeUrl, { campaign: campaign, linked_campaign: linked });
                applyAffected(json.affected || []);
            } catch (err) { alert(err.message); }
            return;
        }
        const linkBtn = e.target.closest('.acl-link-btn');
        if (linkBtn) {
            e.stopPropagation();
            const campaign = linkBtn.dataset.campaign || '';
            if (!campaign) return;
            const rowComp = table.getRows().find(r => r.getData().campaign === campaign);
            const existing = rowComp ? (rowComp.getData().linked_campaigns || []) : [];
            openLinkModal(campaign, existing);
            return;
        }
        const cmpBtn = e.target.closest('.acl-compare-btn');
        if (cmpBtn) {
            e.stopPropagation();
            if (cmpBtn.dataset.campaign) openCompareModal(cmpBtn.dataset.campaign);
            return;
        }
        const pushBtn = e.target.closest('.acl-push-btn');
        if (pushBtn) {
            e.stopPropagation();
            const campaign = pushBtn.dataset.campaign || '';
            if (!campaign) return;
            const original = pushBtn.innerHTML;
            pushBtn.disabled = true;
            pushBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Pushing...';
            try {
                const json = await postJson(pushUrl, { campaign: campaign });
                alert(json.message || 'Done.');
                // Keep the grid count consistent with Amazon's live number.
                updateRowLiveCount(campaign, json.dest_live_count);
            } catch (err) {
                alert(err.message);
            } finally {
                pushBtn.disabled = false;
                pushBtn.innerHTML = original;
            }
        }
    });

    // ---- Table ----
    const searchInput = document.getElementById('acl-search-campaign');
    table = new Tabulator('#acl-table', {
        ajaxURL: dataUrl,
        ajaxConfig: 'GET',
        ajaxURLGenerator: function (url, config, params) {
            const query = new URLSearchParams({ page: String(params.page || 1), size: String(params.size || 50) });
            const term = (searchInput?.value || '').trim();
            if (term) query.set('campaign', term);
            return `${url}?${query.toString()}`;
        },
        ajaxResponse: function (url, params, response) {
            if (!response.success) throw new Error(response.message || 'Failed to load campaigns.');
            return { data: response.data || [], last_page: response.last_page || 1 };
        },
        pagination: true,
        paginationMode: 'remote',
        filterMode: 'remote',
        paginationSize: 50,
        paginationSizeSelector: [25, 50, 100, 200],
        layout: 'fitColumns',
        resizableColumns: true,
        height: '650px',
        placeholder: 'No campaigns found',
        columns: [
            { title: 'Campaign', field: 'campaign', hozAlign: 'left', minWidth: 260, widthGrow: 3 },
            { title: 'Keywords', field: 'keyword_count', hozAlign: 'center', width: 100, formatter: keywordCountFormatter },
            { title: 'Linked', field: 'linked_count', hozAlign: 'center', width: 90 },
            { title: 'Linked Campaigns', field: 'linked_campaigns', cssClass: 'acl-linked-col', headerSort: false,
              minWidth: 320, widthGrow: 4, formatter: linkedCellFormatter },
        ],
    });

    let searchTimer = null;
    searchInput.addEventListener('input', function () {
        if (searchTimer) clearTimeout(searchTimer);
        searchTimer = setTimeout(() => table.setData(), 300);
    });

    // ---- Bulk push (all linked) ----
    document.getElementById('acl-bulk-push').addEventListener('click', async function () {
        if (!confirm('Import keywords into ALL linked campaigns now? This creates keywords on Amazon.')) return;
        const btn = this;
        const original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Pushing all...';
        try {
            const json = await postJson(pushAllUrl, {});
            alert(json.message || 'Done.');
            table.setData();
        } catch (err) {
            alert(err.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = original;
        }
    });
});
</script>
@endsection
