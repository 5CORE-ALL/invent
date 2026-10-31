@extends('layouts.vertical', ['title' => 'Marketplaces - Keyword Targeting'])

@section('css')
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<style>
    .amz-fbm-targeting .tabulator { font-size: 13px; border: 1px solid #dee2e6; border-radius: 0 0 8px 8px; }
    .amz-fbm-targeting .tabulator .tabulator-header {
        background: #dbeafe; border-bottom: 1px solid #93c5fd; font-weight: 600;
        position: sticky; top: var(--tz-topbar-height, 70px); z-index: 24;
    }
    .amz-fbm-targeting .tabulator .tabulator-header .tabulator-col .tabulator-col-title {
        font-size: 12.5px; text-align: center; padding: 6px 4px;
    }
    .amz-fbm-targeting .tabulator-row { min-height: 36px; }
    .amz-fbm-targeting .tabulator-row:hover { background-color: #f8fafc !important; }
    .amz-fbm-targeting .tabulator-cell { padding: 6px 8px !important; }
    .amz-fbm-targeting .aft-loadbar { background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:10px 14px; margin-bottom:10px; }
    .amz-fbm-targeting .aft-loadbar-top { display:flex; justify-content:space-between; gap:12px; font-size:.85rem; font-weight:600; color:#1e3a8a; margin-bottom:6px; }
    .amz-fbm-targeting .aft-low-badge { font-size: .78rem; cursor: pointer; user-select: none; border: 2px solid transparent; }
    .amz-fbm-targeting .aft-low-badge.is-active { border-color: #111827; box-shadow: 0 0 0 2px #fde68a; }
    .amz-fbm-targeting .aft-count-badge {
        display: inline-block; padding: 2px 10px; border-radius: 999px;
        background: #1d4ed8; border: 1px solid #1e40af; color: #fff;
        font-size: 12px; font-weight: 700; cursor: pointer;
    }
    .amz-fbm-targeting .aft-count-badge:hover { background: #1e40af; }
    .amz-fbm-targeting .aft-neg-badge { background: #b91c1c; border-color: #991b1b; }
    .amz-fbm-targeting .aft-neg-badge:hover { background: #991b1b; }
    .amz-fbm-targeting .aft-sneg-badge { background: #9a3412; border-color: #7c2d12; }
    .amz-fbm-targeting .aft-sneg-badge:hover { background: #7c2d12; }
    .amz-fbm-targeting .aft-spec-badge { background: #047857; border-color: #065f46; }
    .amz-fbm-targeting .aft-spec-badge:hover { background: #065f46; }
    #sk-list .sk-item {
        display: flex; align-items: center; justify-content: space-between; gap: 10px;
        padding: 6px 10px; border: 1px solid #a7f3d0; background: #ecfdf5; border-radius: 8px; margin-bottom: 6px; font-size: 13px;
    }
    #sk-list .aft-chip-text { flex: 1; min-width: 0; word-break: break-word; }
    #sk-list .aft-chip-match {
        padding: 2px 8px; border-radius: 999px; background: #047857; color: #fff; font-size: 11px; font-weight: 700;
    }
    .aft-empty { color: #94a3b8; font-size: 13px; }
    #aft-modal-targets.aft-modal-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
        max-height: 420px;
        overflow-y: auto;
        padding-right: 4px;
    }
    #aft-modal-targets .aft-chip {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        width: 100%;
        padding: 8px 12px;
        border-radius: 8px;
        background: #eef2ff;
        border: 1px solid #c7d2fe;
        color: #1e293b;
        font-size: 13px;
        line-height: 1.35;
    }
    #aft-modal-targets .aft-chip-text { flex: 1; min-width: 0; word-break: break-word; }
    #aft-modal-targets .aft-chip-match {
        flex-shrink: 0;
        padding: 2px 8px;
        border-radius: 999px;
        background: #1d4ed8;
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.02em;
    }
    .amz-fbm-targeting .kwc-btn {
        display: inline-flex; align-items: center; gap: 6px; padding: 2px 10px; border-radius: 6px;
        background: #fff; border: 1px solid #1d4ed8; color: #1d4ed8; font-size: 12px; font-weight: 700; cursor: pointer;
    }
    .amz-fbm-targeting .kwc-btn:hover { background: #eff6ff; }
    .amz-fbm-targeting .kwc-btn .kwc-n {
        background: #1d4ed8; color: #fff; border-radius: 999px; padding: 0 7px; font-size: 11px; line-height: 18px;
    }
    .amz-fbm-targeting .kwc-btn.kwc-empty .kwc-n { background: #94a3b8; }
    #kwc-current, #kwc-results { max-height: 220px; overflow-y: auto; }
    #kwc-chips .kwc-chip {
        display: inline-flex; align-items: center; gap: 6px; padding: 2px 8px; border-radius: 999px;
        background: #1d4ed8; color: #fff; font-size: 12px; font-weight: 600;
    }
    #kwc-chips .kwc-chip button { background: none; border: 0; color: #fff; padding: 0; line-height: 1; cursor: pointer; }
    #kwc-current .kwc-item, #kwc-results .kwc-item {
        display: flex; align-items: center; justify-content: space-between; gap: 10px;
        padding: 6px 10px; border: 1px solid #c7d2fe; background: #eef2ff; border-radius: 8px; margin-bottom: 6px; font-size: 13px;
    }
    #kwc-results .kwc-item { background: #fff; border-color: #e2e8f0; cursor: pointer; justify-content: flex-start; }
    #kwc-results .kwc-item:hover { background: #f8fafc; }
    #kwc-results .kwc-parent { color: #64748b; font-size: 11px; margin-left: auto; }
</style>
@endsection

@section('content')
<div class="container-fluid mt-4 amz-fbm-targeting">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h4 class="mb-0"><i class="mdi mdi-bullseye-arrow"></i> Keyword Targeting</h4>
                    <button type="button" id="aft-push-kw" class="btn btn-warning btn-sm fw-bold"
                            title="Send the keywords in the app to the KW campaigns and the HL campaigns on Amazon">
                        <i class="fas fa-upload"></i> Push KW to KW + HL Campaigns
                    </button>
                    <span class="small">Campaign names from the same SP reports table as Ads All. Targets from Amazon Ads (keywords + product targets).</span>
                </div>
                <div class="card-body">
                    <div class="mb-3 d-flex gap-2 flex-wrap align-items-center">
                        <input type="text" id="aft-cousin-search" class="form-control form-control-sm"
                               style="max-width: 260px;" placeholder="Search KW cousins..." autocomplete="off"
                               title="Filter by cousin name, cousin parent or cousin campaign">
                        <select id="aft-scope" class="form-select form-select-sm" style="max-width: 190px;" title="Show parent campaigns or single-SKU campaigns">
                            <option value="all">Parent + SKU</option>
                            <option value="parent">Parent</option>
                            <option value="sku">SKU</option>
                        </select>
                        <input type="text" id="aft-search" class="form-control form-control-sm"
                               style="max-width: 360px;" placeholder="Search parent or campaign name...">
                        <span class="text-muted small" id="aft-count"></span>
                        <span class="badge bg-danger" id="aft-missing-titles" style="font-size:.78rem;"
                              title="Parents with no title in Product Master">Missing Titles: 0</span>
                        <span class="badge bg-danger" id="aft-missing-cousins" style="font-size:.78rem;"
                              title="Parents with no KW cousins">Missing KW cousins: 0</span>
                        <span class="badge bg-primary aft-low-badge" role="button" tabindex="0" data-low="kw" id="aft-low-kw"
                              title="Campaigns with fewer than 50 keywords. Click to filter.">KW &lt; 50: 0</span>
                        <span class="badge bg-primary aft-low-badge" role="button" tabindex="0" data-low="neg" id="aft-low-neg"
                              title="Campaigns with fewer than 50 negative keywords. Click to filter.">Neg KW &lt; 50: 0</span>
                        <span class="badge bg-primary aft-low-badge" role="button" tabindex="0" data-low="spec" id="aft-low-spec"
                              title="Campaigns with fewer than 10 specific keywords. Click to filter.">Specific KW &lt; 10: 0</span>
                        <span class="badge bg-primary aft-low-badge" role="button" tabindex="0" data-low="sneg" id="aft-low-sneg"
                              title="Campaigns with fewer than 10 specific negative keywords. Click to filter.">Specific neg KW &lt; 10: 0</span>
                        <button type="button" id="aft-pull-kw" class="btn btn-sm btn-success ms-auto"
                                title="Add keywords that exist in Amazon KW campaigns but not in the app. Existing keywords are kept.">
                            <i class="fas fa-cloud-download-alt"></i> Pull Keywords
                        </button>
                        <button type="button" id="aft-pull-neg" class="btn btn-sm btn-danger"
                                title="Add negative keywords that exist in Amazon KW campaigns but not in the app. Existing negative keywords are kept.">
                            <i class="fas fa-ban"></i> Pull Negative KW
                        </button>
                    </div>
                    <div id="aft-pull-msg" class="small mb-2" style="display:none;"></div>
                    <div id="aft-loadbar" class="aft-loadbar" style="display:none;" role="status" aria-live="polite">
                        <div class="aft-loadbar-top">
                            <span id="aft-loadbar-text">Loading campaigns…</span>
                            <span id="aft-loadbar-eta" class="text-muted"></span>
                        </div>
                        <div class="progress" style="height:10px;">
                            <div id="aft-loadbar-fill" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%"></div>
                        </div>
                    </div>
                    <div id="aft-table"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="aftTargetsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-bullseye"></i> <span id="aft-modal-title">Targets</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2" id="aft-modal-count"></p>
                <div id="aft-modal-targets" class="aft-modal-list"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="pushKwModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="fas fa-upload"></i> Push keywords to Amazon</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2">
                    Keywords stored in the app are added to every KW campaign, and each parent's keywords (all of its KW campaigns together)
                    are added to its HL campaign. Keywords already live on a campaign (same text + match type) are skipped.
                    Only Broad / Phrase / Exact keywords are sent. Negative keywords are not touched.
                </p>
                <div id="pk-summary" class="fw-semibold mb-2"></div>
                <div id="pk-table-wrap" style="max-height: 420px; overflow-y: auto;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" id="pk-confirm" class="btn btn-warning btn-sm fw-bold" disabled>
                    <i class="fas fa-upload"></i> Push now
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="specificModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-key"></i> <span id="sk-heading">Specific KW</span> <span class="fw-normal">- <span id="sk-title"></span></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    <span id="sk-desc">Keywords added by hand for this campaign only. They are never imported from Amazon and never shared with KW cousins.</span>
                </p>
                <div class="row g-2 mb-2">
                    <div class="col-md-9">
                        <textarea id="sk-input" class="form-control form-control-sm" rows="3"
                                  placeholder="Type keywords, one per line (or comma separated)"></textarea>
                    </div>
                    <div class="col-md-3">
                        <select id="sk-match" class="form-select form-select-sm mb-2">
                            <option value="">No match type</option>
                            <option value="BROAD">Broad</option>
                            <option value="PHRASE">Phrase</option>
                            <option value="EXACT">Exact</option>
                        </select>
                        <button type="button" id="sk-add" class="btn btn-success btn-sm w-100">
                            <i class="fas fa-plus"></i> Add
                        </button>
                    </div>
                </div>
                <div id="sk-msg" class="small mb-2" style="display:none;"></div>
                <h6 class="mb-2"><span id="sk-list-heading">Specific keywords</span> <span class="text-muted small" id="sk-count"></span></h6>
                <div id="sk-list" style="max-height: 300px; overflow-y: auto;"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="kwcModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-link"></i> KW Cousins <span class="fw-normal">- <span id="kwc-title"></span></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    Cousins share the same target keywords and negative keywords. They are copied between all cousins
                    of a group (same keyword + match type is never duplicated).
                </p>
                <h6 class="mb-2">Cousin name</h6>
                <div class="input-group input-group-sm mb-3">
                    <input type="text" id="kwc-name" class="form-control" maxlength="191" placeholder="Name this cousin group (e.g. Cargo Straps)" autocomplete="off">
                    <button type="button" id="kwc-name-save" class="btn btn-primary"><i class="fas fa-save"></i> Save name</button>
                </div>
                <h6 class="mb-2">Current cousins <span class="text-muted small" id="kwc-current-count"></span></h6>
                <div id="kwc-current" class="mb-3"></div>

                <h6 class="mb-2">Add parents as KW cousins <span class="text-muted small fw-normal">(all campaigns under a parent join automatically)</span></h6>
                <input type="text" id="kwc-search" class="form-control form-control-sm mb-2" placeholder="Type to filter parents..." autocomplete="off">
                <div class="d-flex align-items-center gap-3 mb-2 small">
                    <label class="mb-0"><input type="checkbox" id="kwc-all"> Select all shown</label>
                    <a href="#" id="kwc-clear" class="text-decoration-none">Clear selection</a>
                    <span class="text-muted ms-auto" id="kwc-shown"></span>
                </div>
                <div id="kwc-chips" class="d-flex flex-wrap gap-1 mb-2"></div>
                <div id="kwc-results" class="mb-2"></div>
                <div id="kwc-msg" class="small" style="display:none;"></div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" id="kwc-sync" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-sync"></i> Sync keywords now
                </button>
                <div>
                    <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="button" id="kwc-add" class="btn btn-sm btn-success" disabled>
                        <i class="fas fa-plus"></i> Add &amp; Sync (<span id="kwc-sel-n">0</span>)
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
(function () {
    const dataUrl = @json(route('amazon.ads.fbm-targeting.data'));
    const searchEl = document.getElementById('aft-search');
    const scopeEl = document.getElementById('aft-scope');
    const cousinSearchEl = document.getElementById('aft-cousin-search');
    const countEl = document.getElementById('aft-count');
    const missingTitlesEl = document.getElementById('aft-missing-titles');
    const missingCousinsEl = document.getElementById('aft-missing-cousins');
    const lowBadgeEls = Array.prototype.slice.call(document.querySelectorAll('.aft-low-badge'));
    const lowLabels = { kw: 'KW', neg: 'Neg KW', spec: 'Specific KW', sneg: 'Specific neg KW' };
    let lowFilter = '';
    let searchTimer = null;

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, function (c) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]);
        });
    }

    // ---- Loading bar with an estimated time (shown only when a load is slow) ----
    // The estimate is a rolling average of this browser's previous load times (default 6 s the first time).
    const loadBar = (function () {
        const box = document.getElementById('aft-loadbar');
        const fill = document.getElementById('aft-loadbar-fill');
        const eta = document.getElementById('aft-loadbar-eta');
        const text = document.getElementById('aft-loadbar-text');
        const KEY = 'aft_load_ms';
        let started = 0, showTimer = null, tick = null;

        function estimate() {
            const v = Number(localStorage.getItem(KEY));
            return v > 500 ? v : 6000;
        }
        function paint() {
            const est = estimate();
            const elapsed = Date.now() - started;
            let pct;
            if (elapsed < est) {
                pct = (elapsed / est) * 90;
                const left = Math.max(1, Math.ceil((est - elapsed) / 1000));
                eta.textContent = 'about ' + left + 's left';
                text.textContent = 'Loading campaigns…';
            } else {
                // Past the estimate: creep toward 99% so the bar never looks stuck at 100%.
                pct = 90 + 9 * (1 - Math.exp(-(elapsed - est) / (est * 2)));
                eta.textContent = 'almost there · ' + Math.round(elapsed / 1000) + 's elapsed';
                text.textContent = 'Taking longer than usual…';
            }
            fill.style.width = Math.min(99, pct).toFixed(1) + '%';
        }
        return {
            start: function () {
                this.stop(false);
                started = Date.now();
                showTimer = setTimeout(function () {
                    box.style.display = '';
                    paint();
                    tick = setInterval(paint, 250);
                }, 700);
            },
            stop: function (record) {
                if (record && started) {
                    const took = Date.now() - started;
                    const prev = Number(localStorage.getItem(KEY));
                    const next = prev > 0 ? Math.round(prev * 0.6 + took * 0.4) : took;
                    try { localStorage.setItem(KEY, String(next)); } catch (e) { /* storage unavailable */ }
                }
                clearTimeout(showTimer);
                clearInterval(tick);
                showTimer = tick = null;
                started = 0;
                if (box.style.display !== 'none') {
                    fill.style.width = '100%';
                    setTimeout(function () { box.style.display = 'none'; }, 150);
                }
            },
        };
    })();

    const table = new Tabulator('#aft-table', {
        layout: 'fitColumns',
        placeholder: 'Loading campaigns…',
        pagination: true,
        paginationMode: 'remote',
        paginationSize: 50,
        paginationSizeSelector: [25, 50, 100],
        ajaxURL: dataUrl,
        ajaxConfig: 'GET',
        filterMode: 'remote',
        ajaxRequestFunc: function (url, _config, params) {
            const q = new URLSearchParams();
            q.set('page', String(params.page || 1));
            q.set('size', String(params.size || 50));
            const search = (searchEl && searchEl.value || '').trim();
            if (search) q.set('campaign', search);
            const cousinQ = (cousinSearchEl && cousinSearchEl.value || '').trim();
            if (cousinQ) q.set('cousin', cousinQ);
            const scope = scopeEl ? scopeEl.value : 'all';
            if (scope && scope !== 'all') q.set('scope', scope);
            if (lowFilter) q.set('low', lowFilter);
            loadBar.start();
            return fetch(url + '?' + q.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (res) {
                if (!res.ok) {
                    loadBar.stop(false);
                    return res.text().then(function (body) {
                        let reason = '';
                        try { reason = (JSON.parse(body) || {}).message || ''; } catch (e) { /* not JSON */ }
                        showPullMsg('Could not load campaigns (server error ' + res.status + ')' + (reason ? ': ' + reason : '. Please try again.'), false);
                        if (pullMsg) pullMsg.dataset.loadErr = '1';
                        throw new Error('Network response was not ok');
                    });
                }
                return res.json();
            }).then(function (json) {
                loadBar.stop(true);
                if (pullMsg && pullMsg.dataset.loadErr) { // clear an earlier load error once a load succeeds
                    delete pullMsg.dataset.loadErr;
                    pullMsg.style.display = 'none';
                }
                return json;
            }, function (err) {
                loadBar.stop(false);
                throw err;
            });
        },
        ajaxResponse: function (_url, _params, response) {
            const total = Number(response.total || 0);
            if (countEl) countEl.textContent = total ? (total.toLocaleString() + ' campaigns') : '';
            if (missingTitlesEl) {
                const mt = Number(response.missing_titles || 0);
                missingTitlesEl.textContent = 'Missing Titles: ' + mt.toLocaleString();
                missingTitlesEl.classList.toggle('bg-danger', mt > 0);
                missingTitlesEl.classList.toggle('bg-success', mt === 0);
                const names = Array.isArray(response.missing_title_parents) ? response.missing_title_parents : [];
                missingTitlesEl.title = mt > 0
                    ? 'Parents with no title in Product Master: ' + names.join(', ') + (mt > names.length ? ' …' : '')
                    : 'Every parent has a title in Product Master';
            }
            if (missingCousinsEl) {
                const mc = Number(response.missing_cousins || 0);
                missingCousinsEl.textContent = 'Missing KW cousins: ' + mc.toLocaleString();
                missingCousinsEl.classList.toggle('bg-danger', mc > 0);
                missingCousinsEl.classList.toggle('bg-success', mc === 0);
                const cn = Array.isArray(response.missing_cousin_parents) ? response.missing_cousin_parents : [];
                missingCousinsEl.title = mc > 0
                    ? 'Parents with no KW cousins: ' + cn.join(', ') + (mc > cn.length ? ' …' : '')
                    : 'Every parent has KW cousins';
            }
            lowBadgeEls.forEach(function (el) {
                const k = el.getAttribute('data-low');
                const n = Number((response.low_counts || {})[k] || 0);
                const lim = Number((response.low_limits || {})[k] || 0);
                el.innerHTML = escapeHtml(lowLabels[k]) + ' &lt; ' + lim + ': ' + n.toLocaleString();
                el.classList.toggle('bg-primary', n === 0 || lowFilter === k);
                el.classList.toggle('bg-warning', n > 0 && lowFilter !== k);
                el.classList.toggle('text-dark', n > 0 && lowFilter !== k);
                el.classList.toggle('is-active', lowFilter === k);
            });
            return {
                data: response.data || [],
                last_page: response.last_page || 1,
            };
        },
        rowFormatter: function (row) {
            const d = row.getData() || {};
            const el = row.getElement();
            // Visual group break whenever a new parent starts.
            el.style.borderTop = d.parent_first ? '2px solid #93c5fd' : '';
        },
        columns: [
            {
                title: 'KWC Cousin',
                field: 'cousin_name',
                minWidth: 150,
                widthGrow: 1,
                headerSort: false,
                formatter: function (cell) {
                    const n = String(cell.getValue() || '').trim();
                    return n ? '<strong class="text-primary">' + escapeHtml(n) + '</strong>' : '';
                },
            },
            {
                title: 'Parents',
                field: 'parent',
                minWidth: 180,
                widthGrow: 1,
                headerSort: false,
                formatter: function (cell) {
                    // Autofilled on every campaign row; the first row of a parent group is bold, the rest are lighter.
                    const d = cell.getRow().getData() || {};
                    const p = String(d.parent || '').trim();
                    if (!p) return '<span class="aft-empty">No parent</span>';
                    return d.parent_first
                        ? '<strong>' + escapeHtml(p) + '</strong>'
                        : '<span class="text-muted">' + escapeHtml(p) + '</span>';
                },
            },
            {
                title: 'Title',
                field: 'title',
                minWidth: 260,
                widthGrow: 2,
                headerSort: false,
                formatter: function (cell) {
                    const d = cell.getRow().getData() || {};
                    const t = String(d.title || '').trim();
                    if (!t) return '<span class="aft-empty">-</span>';
                    // Autofilled on every row; later rows of a parent are clamped to 2 lines (full title on hover).
                    const clamp = d.parent_first
                        ? ''
                        : 'display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;';
                    return '<div style="white-space:normal;line-height:1.3;' + clamp + '" title="' + escapeHtml(t) + '">' + escapeHtml(t) + '</div>';
                },
            },
            {
                title: 'Campaign name',
                field: 'campaign_name',
                minWidth: 280,
                widthGrow: 2,
                headerSort: false,
                formatter: function (cell) {
                    const name = escapeHtml(cell.getValue() || '');
                    const id = escapeHtml((cell.getRow().getData() || {}).campaign_id || '');
                    return '<div><strong>' + name + '</strong>' +
                        (id ? '<div class="text-muted" style="font-size:11px;">' + id + '</div>' : '') +
                        '</div>';
                },
            },
            {
                title: 'Status',
                field: 'status',
                hozAlign: 'center',
                minWidth: 100,
                width: 120,
                headerSort: false,
                formatter: function (cell) {
                    const st = String(cell.getValue() || '').trim().toUpperCase();
                    if (!st) return '<span class="aft-empty">-</span>';
                    const cls = st === 'ENABLED' ? 'bg-success' : (st === 'PAUSED' ? 'bg-warning text-dark' : 'bg-secondary');
                    const label = st.charAt(0) + st.slice(1).toLowerCase();
                    return '<span class="badge ' + cls + '">' + escapeHtml(label) + '</span>';
                },
            },
            {
                title: 'KW cousins',
                field: 'cousin_count',
                hozAlign: 'center',
                minWidth: 110,
                width: 130,
                headerSort: false,
                formatter: function (cell) {
                    const n = Number(cell.getValue() || 0);
                    return '<span class="kwc-btn' + (n ? '' : ' kwc-empty') + '" title="Manage KW cousins">KWC <span class="kwc-n">' + n + '</span></span>';
                },
                cellClick: function (_e, cell) {
                    openCousinsModal(cell.getRow().getData() || {});
                },
            },
            {
                title: 'Targets',
                field: 'target_count',
                hozAlign: 'center',
                minWidth: 100,
                width: 120,
                headerSort: false,
                formatter: function (cell) {
                    const n = Number(cell.getValue() || 0);
                    return '<span class="aft-count-badge" title="View targets">' + n.toLocaleString() + '</span>';
                },
                cellClick: function (_e, cell) {
                    openTargetsModal(cell.getRow().getData() || {}, 'kw');
                },
            },
            {
                title: 'Negative KW',
                field: 'negative_count',
                hozAlign: 'center',
                minWidth: 110,
                width: 130,
                headerSort: false,
                formatter: function (cell) {
                    const n = Number(cell.getValue() || 0);
                    return '<span class="aft-count-badge aft-neg-badge" title="View negative keywords">' + n.toLocaleString() + '</span>';
                },
                cellClick: function (_e, cell) {
                    openTargetsModal(cell.getRow().getData() || {}, 'neg');
                },
            },
            {
                title: 'Specific KW',
                field: 'specific_count',
                hozAlign: 'center',
                minWidth: 110,
                width: 130,
                headerSort: false,
                formatter: function (cell) {
                    const n = Number(cell.getValue() || 0);
                    return '<span class="aft-count-badge aft-spec-badge" title="View / add specific keywords">' + n.toLocaleString() + '</span>';
                },
                cellClick: function (_e, cell) {
                    openSpecificModal(cell.getRow().getData() || {}, 'kw');
                },
            },
            {
                title: 'S Negative KW',
                field: 'specific_neg_count',
                hozAlign: 'center',
                minWidth: 120,
                width: 140,
                headerSort: false,
                formatter: function (cell) {
                    const n = Number(cell.getValue() || 0);
                    return '<span class="aft-count-badge aft-sneg-badge" title="View / add specific negative keywords">' + n.toLocaleString() + '</span>';
                },
                cellClick: function (_e, cell) {
                    openSpecificModal(cell.getRow().getData() || {}, 'neg');
                },
            },
        ],
    });

    function openTargetsModal(row, kind) {
        const isNeg = kind === 'neg';
        const name = (row.campaign_name || 'Campaign') + (isNeg ? ' - Negative KW' : '');
        const list = Array.isArray(isNeg ? row.negatives : row.targets) ? (isNeg ? row.negatives : row.targets) : [];
        const titleEl = document.getElementById('aft-modal-title');
        const countLabel = document.getElementById('aft-modal-count');
        const listEl = document.getElementById('aft-modal-targets');
        if (titleEl) titleEl.textContent = name;
        if (countLabel) countLabel.textContent = list.length.toLocaleString() + (isNeg ? ' negative keyword' : ' target') + (list.length === 1 ? '' : 's');
        if (listEl) {
            if (list.length === 0) {
                listEl.innerHTML = '<span class="aft-empty">' + (isNeg ? 'No negative keywords yet' : 'No targets yet') + '</span>';
            } else {
                listEl.innerHTML = list.map(function (t) {
                    const raw = String(t || '').trim();
                    const m = raw.match(/^(.*)\s+\(([A-Za-z][A-Za-z0-9_/ -]{1,20})\)$/);
                    const text = m ? m[1].trim() : raw;
                    const match = m ? m[2].trim() : '';
                    return '<div class="aft-chip"><span class="aft-chip-text">' + escapeHtml(text) + '</span>' +
                        (match ? '<span class="aft-chip-match">' + escapeHtml(match) + '</span>' : '') +
                        '</div>';
                }).join('');
            }
        }
        const modalEl = document.getElementById('aftTargetsModal');
        if (modalEl && window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } else if (window.jQuery) {
            window.jQuery(modalEl).modal('show');
        }
    }

    const pullMsg = document.getElementById('aft-pull-msg');
    const csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    const pullDefs = [
        {
            btn: document.getElementById('aft-pull-kw'),
            url: @json(route('amazon.ads.fbm-targeting.pull-keywords')),
            what: 'keywords',
        },
        {
            btn: document.getElementById('aft-pull-neg'),
            url: @json(route('amazon.ads.fbm-targeting.pull-negatives')),
            what: 'negative keywords',
        },
    ];

    function showPullMsg(text, ok) {
        if (!pullMsg) return;
        pullMsg.style.display = 'block';
        pullMsg.className = 'small mb-2 ' + (ok ? 'text-success' : 'text-danger');
        pullMsg.textContent = text;
    }

    pullDefs.forEach(function (def) {
        if (!def.btn) return;
        def.btn.addEventListener('click', function () {
            if (!confirm('Pull ' + def.what + ' from all Amazon KW campaigns? Only ones missing in the app will be added; existing ones are kept.')) return;
            const original = def.btn.innerHTML;
            pullDefs.forEach(function (d) { if (d.btn) d.btn.disabled = true; });
            def.btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Pulling…';
            showPullMsg('Pulling ' + def.what + ' from Amazon, this can take a minute…', true);
            fetch(def.url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                },
            }).then(function (res) {
                return res.json().catch(function () { return {}; }).then(function (body) {
                    return { ok: res.ok && body.success !== false, body: body };
                });
            }).then(function (r) {
                const b = r.body || {};
                if (r.ok) {
                    showPullMsg((b.message || 'Done.') + ' (' + (b.campaigns || 0) + ' KW campaigns, ' + (b.fetched || 0) + ' ' + def.what + ' fetched)', true);
                    table.setPage(1);
                } else {
                    showPullMsg(b.message || 'Pull failed.', false);
                }
            }).catch(function (e) {
                showPullMsg('Pull failed: ' + e.message, false);
            }).finally(function () {
                pullDefs.forEach(function (d) { if (d.btn) d.btn.disabled = false; });
                def.btn.innerHTML = original;
            });
        });
    });

    // ---------- KW cousins ----------
    const cousinUrls = {
        campaigns: @json(route('amazon.ads.fbm-targeting.cousins.campaigns')),
        add: @json(route('amazon.ads.fbm-targeting.cousins.add')),
        remove: @json(route('amazon.ads.fbm-targeting.cousins.remove')),
        sync: @json(route('amazon.ads.fbm-targeting.cousins.sync')),
        name: @json(route('amazon.ads.fbm-targeting.cousins.name')),
    };
    const kwcState = { campaign: '', cousins: [], selected: new Set(), parents: [], shown: [] };
    // kwcState.cousins = [{parent, campaigns: [...]}] (one entry per cousin parent)
    const kwcEl = {
        modal: document.getElementById('kwcModal'),
        title: document.getElementById('kwc-title'),
        current: document.getElementById('kwc-current'),
        currentCount: document.getElementById('kwc-current-count'),
        search: document.getElementById('kwc-search'),
        results: document.getElementById('kwc-results'),
        msg: document.getElementById('kwc-msg'),
        add: document.getElementById('kwc-add'),
        sel: document.getElementById('kwc-sel-n'),
        sync: document.getElementById('kwc-sync'),
        chips: document.getElementById('kwc-chips'),
        name: document.getElementById('kwc-name'),
        nameSave: document.getElementById('kwc-name-save'),
        all: document.getElementById('kwc-all'),
        clear: document.getElementById('kwc-clear'),
        shown: document.getElementById('kwc-shown'),
    };

    function kwcMsg(text, ok) {
        kwcEl.msg.style.display = text ? 'block' : 'none';
        kwcEl.msg.className = 'small ' + (ok ? 'text-success' : 'text-danger');
        kwcEl.msg.textContent = text || '';
    }

    function kwcPost(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify(body),
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (b) {
                return { ok: res.ok && b.success !== false, body: b };
            });
        });
    }

    function kwcRenderCurrent() {
        const list = kwcState.cousins;
        kwcEl.currentCount.textContent = list.length ? '(' + list.length + ')' : '';
        if (!list.length) {
            kwcEl.current.innerHTML = '<span class="aft-empty">No cousins yet</span>';
            return;
        }
        kwcEl.current.innerHTML = list.map(function (c, i) {
            const n = (c.campaigns || []).length;
            const label = c.parent ? c.parent : (c.campaigns || [''])[0];
            const sub = c.parent ? '<span class="text-muted small ms-2">' + n + ' campaign' + (n === 1 ? '' : 's') + '</span>' : '';
            return '<div class="kwc-item" title="' + escapeHtml((c.campaigns || []).join(', ')) + '"><span><strong>' + escapeHtml(label) + '</strong>' + sub + '</span>' +
                '<button type="button" class="btn btn-sm btn-outline-danger py-0 kwc-remove" data-i="' + i + '" title="Remove cousin">&times;</button></div>';
        }).join('');
    }

    function kwcUpdateSel() {
        kwcEl.sel.textContent = String(kwcState.selected.size);
        kwcEl.add.disabled = kwcState.selected.size === 0;
        const picked = Array.from(kwcState.selected);
        kwcEl.chips.innerHTML = picked.map(function (p) {
            return '<span class="kwc-chip">' + escapeHtml(p) + '<button type="button" class="kwc-chip-x" data-p="' + escapeHtml(p) + '" title="Unselect">&times;</button></span>';
        }).join('');
        const shown = kwcState.shown;
        const allOn = shown.length > 0 && shown.every(function (p) { return kwcState.selected.has(p.parent); });
        kwcEl.all.checked = allOn;
        kwcEl.all.disabled = shown.length === 0;
    }

    // Instant, client-side filter over the parents loaded once per open.
    function kwcRenderResults() {
        const q = (kwcEl.search.value || '').trim().toLowerCase();
        const terms = q.split(/\s+/).filter(Boolean);
        kwcState.shown = kwcState.parents.filter(function (p) {
            const hay = String(p.parent).toLowerCase();
            return terms.every(function (t) { return hay.indexOf(t) !== -1; });
        });
        kwcEl.shown.textContent = kwcState.shown.length.toLocaleString() + ' of ' + kwcState.parents.length.toLocaleString() + ' parents';
        if (!kwcState.shown.length) {
            kwcEl.results.innerHTML = '<span class="aft-empty">No parents match</span>';
        } else {
            kwcEl.results.innerHTML = kwcState.shown.map(function (p) {
                const checked = kwcState.selected.has(p.parent) ? ' checked' : '';
                return '<label class="kwc-item"><input type="checkbox" class="kwc-pick" value="' + escapeHtml(p.parent) + '"' + checked + '>' +
                    '<span>' + escapeHtml(p.parent) + '</span>' +
                    '<span class="kwc-parent">' + Number(p.campaigns || 0) + ' campaign' + (Number(p.campaigns) === 1 ? '' : 's') + '</span></label>';
            }).join('');
        }
        kwcUpdateSel();
    }

    function kwcLoadResults() {
        const url = cousinUrls.campaigns + '?exclude=' + encodeURIComponent(kwcState.campaign);
        kwcEl.results.innerHTML = '<span class="aft-empty">Loading…</span>';
        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json().then(function (b) { return { ok: r.ok, body: b }; }); })
            .then(function (r) {
                if (!r.ok || !Array.isArray(r.body.parents)) throw new Error(r.body.message || 'bad response');
                kwcState.parents = r.body.parents;
                // Drop selections that are no longer available.
                const avail = new Set(kwcState.parents.map(function (p) { return p.parent; }));
                Array.from(kwcState.selected).forEach(function (p) { if (!avail.has(p)) kwcState.selected.delete(p); });
                kwcRenderResults();
            })
            .catch(function (e) {
                kwcEl.results.innerHTML = '<span class="text-danger small">Could not load parents: ' + escapeHtml(e.message) + '</span>';
            });
    }

    function openCousinsModal(row) {
        kwcState.campaign = row.campaign_name || '';
        kwcState.cousins = Array.isArray(row.cousin_parents) ? row.cousin_parents.slice() : [];
        kwcState.selected = new Set();
        kwcState.parents = [];
        kwcState.shown = [];
        kwcEl.title.textContent = kwcState.campaign;
        kwcEl.name.value = row.cousin_name || '';
        kwcEl.search.value = '';
        kwcMsg('', true);
        kwcRenderCurrent();
        kwcUpdateSel();
        kwcLoadResults();
        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(kwcEl.modal).show();
        } else if (window.jQuery) {
            window.jQuery(kwcEl.modal).modal('show');
        }
    }

    function kwcApplyAffected(affected) {
        const me = (affected || []).find(function (a) {
            return String(a.campaign).toUpperCase() === kwcState.campaign.toUpperCase();
        });
        if (me) {
            kwcState.cousins = me.cousin_parents || [];
            kwcEl.name.value = me.cousin_name || '';
        }
        kwcRenderCurrent();
        table.setPage(table.getPage() || 1);
    }

    kwcEl.search.addEventListener('input', kwcRenderResults);

    kwcEl.all.addEventListener('change', function () {
        kwcState.shown.forEach(function (p) {
            if (kwcEl.all.checked) kwcState.selected.add(p.parent); else kwcState.selected.delete(p.parent);
        });
        kwcRenderResults();
    });

    kwcEl.clear.addEventListener('click', function (e) {
        e.preventDefault();
        kwcState.selected = new Set();
        kwcRenderResults();
    });

    kwcEl.chips.addEventListener('click', function (e) {
        const x = e.target.closest('.kwc-chip-x');
        if (!x) return;
        kwcState.selected.delete(x.dataset.p);
        kwcRenderResults();
    });

    kwcEl.results.addEventListener('change', function (e) {
        const t = e.target;
        if (!t || !t.classList.contains('kwc-pick')) return;
        if (t.checked) kwcState.selected.add(t.value); else kwcState.selected.delete(t.value);
        kwcUpdateSel();
    });

    kwcEl.current.addEventListener('click', function (e) {
        const btn = e.target.closest('.kwc-remove');
        if (!btn) return;
        const entry = kwcState.cousins[Number(btn.dataset.i)];
        if (!entry) return;
        const label = entry.parent ? 'parent "' + entry.parent + '" (' + (entry.campaigns || []).length + ' campaigns)' : '"' + (entry.campaigns || [''])[0] + '"';
        if (!confirm('Remove ' + label + ' from this cousin group? Keywords already synced stay.')) return;
        const body = entry.parent
            ? { campaign: kwcState.campaign, parent: entry.parent }
            : { campaign: kwcState.campaign, cousin: (entry.campaigns || [''])[0] };
        kwcPost(cousinUrls.remove, body).then(function (r) {
            if (r.ok) {
                kwcApplyAffected(r.body.affected);
                kwcMsg(r.body.message || 'Removed.', true);
                kwcLoadResults();
            } else {
                kwcMsg(r.body.message || 'Could not remove.', false);
            }
        });
    });

    kwcEl.nameSave.addEventListener('click', function () {
        const original = kwcEl.nameSave.innerHTML;
        kwcEl.nameSave.disabled = true;
        kwcPost(cousinUrls.name, { campaign: kwcState.campaign, name: (kwcEl.name.value || '').trim() }).then(function (r) {
            if (r.ok) {
                kwcApplyAffected(r.body.affected);
                kwcMsg(r.body.message || 'Saved.', true);
            } else {
                kwcMsg((r.body && (r.body.message || (r.body.errors && Object.values(r.body.errors)[0]))) || 'Could not save name.', false);
            }
        }).catch(function (e) {
            kwcMsg('Failed: ' + e.message, false);
        }).finally(function () {
            kwcEl.nameSave.disabled = false;
            kwcEl.nameSave.innerHTML = original;
        });
    });

    kwcEl.name.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); kwcEl.nameSave.click(); }
    });

    kwcEl.add.addEventListener('click', function () {
        const picks = Array.from(kwcState.selected);
        if (!picks.length) return;
        const original = kwcEl.add.innerHTML;
        kwcEl.add.disabled = true;
        kwcEl.add.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Syncing…';
        kwcMsg('Saving cousins and syncing keywords…', true);
        kwcPost(cousinUrls.add, { campaign: kwcState.campaign, parents: picks, name: (kwcEl.name.value || '').trim() }).then(function (r) {
            if (r.ok) {
                kwcState.selected = new Set();
                kwcApplyAffected(r.body.affected);
                kwcMsg(r.body.message || 'Saved.', true);
                kwcLoadResults();
            } else {
                kwcMsg((r.body && (r.body.message || (r.body.errors && Object.values(r.body.errors)[0]))) || 'Could not add cousins.', false);
            }
        }).catch(function (e) {
            kwcMsg('Failed: ' + e.message, false);
        }).finally(function () {
            kwcEl.add.innerHTML = original;
            kwcUpdateSel();
        });
    });

    kwcEl.sync.addEventListener('click', function () {
        const original = kwcEl.sync.innerHTML;
        kwcEl.sync.disabled = true;
        kwcEl.sync.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Syncing…';
        kwcMsg('Syncing keywords…', true);
        kwcPost(cousinUrls.sync, { campaign: kwcState.campaign }).then(function (r) {
            kwcMsg(r.body.message || (r.ok ? 'Synced.' : 'Sync failed.'), r.ok);
            if (r.ok) kwcApplyAffected(r.body.affected);
        }).catch(function (e) {
            kwcMsg('Failed: ' + e.message, false);
        }).finally(function () {
            kwcEl.sync.disabled = false;
            kwcEl.sync.innerHTML = original;
        });
    });

    // ---------- Push KW to KW + HL campaigns ----------
    const pushUrl = @json(route('amazon.ads.fbm-targeting.push-keywords'));
    const pkBtn = document.getElementById('aft-push-kw');
    const pkConfirm = document.getElementById('pk-confirm');
    const pkSummary = document.getElementById('pk-summary');
    const pkWrap = document.getElementById('pk-table-wrap');
    const pkModalEl = document.getElementById('pushKwModal');

    function pkPost(dryRun) {
        return fetch(pushUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ dry_run: dryRun }),
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (b) {
                return { ok: res.ok && b.success !== false, body: b };
            });
        });
    }

    function pkRender(body, done) {
        const rows = body.rows || [];
        if (!rows.length) {
            pkWrap.innerHTML = '<span class="aft-empty">Nothing to push. Every campaign already has these keywords.</span>';
            return;
        }
        pkWrap.innerHTML = '<table class="table table-sm table-bordered align-middle mb-0"><thead class="table-light"><tr>' +
            '<th style="width:70px">Type</th><th>Campaign</th><th style="width:140px" class="text-end">' + (done ? 'Added' : 'To add') + '</th>' +
            (done ? '<th style="width:90px" class="text-end">Failed</th><th>Note</th>' : '') + '</tr></thead><tbody>' +
            rows.map(function (r) {
                return '<tr><td><span class="badge ' + (r.type === 'HL' ? 'bg-info' : 'bg-primary') + '">' + escapeHtml(r.type) + '</span></td>' +
                    '<td>' + escapeHtml(r.campaign_name) + '</td>' +
                    '<td class="text-end">' + (done ? Number(r.added || 0) + ' / ' + Number(r.to_add || 0) : Number(r.to_add || 0).toLocaleString()) + '</td>' +
                    (done ? '<td class="text-end ' + (r.failed ? 'text-danger fw-bold' : '') + '">' + Number(r.failed || 0) + '</td><td class="small">' + escapeHtml(r.message || '') + '</td>' : '') +
                    '</tr>';
            }).join('') + '</tbody></table>';
    }

    function pkOpen() {
        pkSummary.className = 'fw-semibold mb-2';
        pkSummary.textContent = 'Checking what is missing on Amazon…';
        pkWrap.innerHTML = '<span class="aft-empty">Loading…</span>';
        pkConfirm.disabled = true;
        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(pkModalEl).show();
        } else if (window.jQuery) {
            window.jQuery(pkModalEl).modal('show');
        }
        pkPost(true).then(function (r) {
            const b = r.body || {};
            if (!r.ok) {
                pkSummary.className = 'fw-semibold mb-2 text-danger';
                pkSummary.textContent = b.message || 'Could not check Amazon.';
                pkWrap.innerHTML = '';
                return;
            }
            pkSummary.textContent = b.message || '';
            pkRender(b, false);
            const t = b.totals || {};
            pkConfirm.disabled = (Number(t.kw_to_add || 0) + Number(t.hl_to_add || 0)) === 0;
        }).catch(function (e) {
            pkSummary.className = 'fw-semibold mb-2 text-danger';
            pkSummary.textContent = 'Failed: ' + e.message;
        });
    }

    if (pkBtn) pkBtn.addEventListener('click', pkOpen);

    if (pkConfirm) {
        pkConfirm.addEventListener('click', function () {
            if (!confirm('This adds keywords to live campaigns on Amazon. Continue?')) return;
            const original = pkConfirm.innerHTML;
            pkConfirm.disabled = true;
            pkConfirm.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Pushing…';
            pkSummary.className = 'fw-semibold mb-2';
            pkSummary.textContent = 'Pushing keywords to Amazon, this can take a few minutes…';
            pkPost(false).then(function (r) {
                const b = r.body || {};
                pkSummary.className = 'fw-semibold mb-2 ' + (r.ok ? ((b.totals && b.totals.failed) ? 'text-warning' : 'text-success') : 'text-danger');
                pkSummary.textContent = b.message || (r.ok ? 'Done.' : 'Push failed.');
                if (r.ok) pkRender(b, true); else pkWrap.innerHTML = '';
            }).catch(function (e) {
                pkSummary.className = 'fw-semibold mb-2 text-danger';
                pkSummary.textContent = 'Push failed: ' + e.message;
            }).finally(function () {
                pkConfirm.innerHTML = original;
            });
        });
    }

    // ---------- Specific KW (manual, independent of cousins) ----------
    const specificUrls = {
        add: @json(route('amazon.ads.fbm-targeting.specific.add')),
        remove: @json(route('amazon.ads.fbm-targeting.specific.remove')),
    };
    const skState = { campaignId: '', list: [], kind: 'kw' };
    const skOptions = {
        kw: [['', 'No match type'], ['BROAD', 'Broad'], ['PHRASE', 'Phrase'], ['EXACT', 'Exact']],
        neg: [['', 'No match type'], ['NEGATIVE_PHRASE', 'Negative phrase'], ['NEGATIVE_EXACT', 'Negative exact']],
    };
    const skEl = {
        modal: document.getElementById('specificModal'),
        title: document.getElementById('sk-title'),
        input: document.getElementById('sk-input'),
        match: document.getElementById('sk-match'),
        add: document.getElementById('sk-add'),
        msg: document.getElementById('sk-msg'),
        list: document.getElementById('sk-list'),
        count: document.getElementById('sk-count'),
        heading: document.getElementById('sk-heading'),
        listHeading: document.getElementById('sk-list-heading'),
        desc: document.getElementById('sk-desc'),
    };

    function skMsg(text, ok) {
        skEl.msg.style.display = text ? 'block' : 'none';
        skEl.msg.className = 'small mb-2 ' + (ok ? 'text-success' : 'text-danger');
        skEl.msg.textContent = text || '';
    }

    function skPost(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify(body),
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (b) {
                return { ok: res.ok && b.success !== false, body: b };
            });
        });
    }

    function skRender() {
        const list = skState.list;
        skEl.count.textContent = list.length ? '(' + list.length + ')' : '';
        if (!list.length) {
            skEl.list.innerHTML = '<span class="aft-empty">' + (skState.kind === 'neg' ? 'No specific negative keywords yet' : 'No specific keywords yet') + '</span>';
            return;
        }
        skEl.list.innerHTML = list.map(function (k) {
            return '<div class="sk-item"><span class="aft-chip-text">' + escapeHtml(k.keyword) + '</span>' +
                (k.match_type ? '<span class="aft-chip-match">' + escapeHtml(k.match_type) + '</span>' : '') +
                '<button type="button" class="btn btn-sm btn-outline-danger py-0 sk-remove" data-id="' + Number(k.id) + '" title="Remove">&times;</button></div>';
        }).join('');
    }

    function skRefreshGrid() {
        table.setPage(table.getPage() || 1);
    }

    function openSpecificModal(row, kind) {
        const neg = kind === 'neg';
        skState.kind = neg ? 'neg' : 'kw';
        skState.campaignId = String(row.campaign_id || '');
        const src = neg ? row.specific_neg : row.specific;
        skState.list = Array.isArray(src) ? src.slice() : [];
        skEl.heading.textContent = neg ? 'S Negative KW' : 'Specific KW';
        skEl.listHeading.textContent = neg ? 'Specific negative keywords' : 'Specific keywords';
        skEl.desc.textContent = neg
            ? 'Negative keywords added by hand for this campaign only. They are never imported from Amazon and never shared with KW cousins.'
            : 'Keywords added by hand for this campaign only. They are never imported from Amazon and never shared with KW cousins.';
        skEl.match.innerHTML = skOptions[skState.kind].map(function (o) {
            return '<option value="' + o[0] + '">' + o[1] + '</option>';
        }).join('');
        skEl.title.textContent = row.campaign_name || '';
        skEl.input.value = '';
        skEl.match.value = '';
        skMsg('', true);
        skRender();
        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(skEl.modal).show();
        } else if (window.jQuery) {
            window.jQuery(skEl.modal).modal('show');
        }
    }

    skEl.add.addEventListener('click', function () {
        const text = (skEl.input.value || '').trim();
        if (!text) { skMsg('Enter at least one keyword.', false); return; }
        skEl.add.disabled = true;
        skPost(specificUrls.add, { campaign_id: skState.campaignId, keywords: text, match_type: skEl.match.value, kind: skState.kind })
            .then(function (r) {
                const b = r.body || {};
                if (r.ok) {
                    skState.list = b.specific || [];
                    skEl.input.value = '';
                    skRender();
                    skMsg(b.message || 'Added.', true);
                    skRefreshGrid();
                } else {
                    skMsg(b.message || (b.errors && Object.values(b.errors)[0]) || 'Could not add.', false);
                }
            })
            .catch(function (e) { skMsg('Failed: ' + e.message, false); })
            .finally(function () { skEl.add.disabled = false; });
    });

    skEl.list.addEventListener('click', function (e) {
        const btn = e.target.closest('.sk-remove');
        if (!btn) return;
        skPost(specificUrls.remove, { campaign_id: skState.campaignId, id: Number(btn.dataset.id), kind: skState.kind }).then(function (r) {
            const b = r.body || {};
            if (r.ok) {
                skState.list = b.specific || [];
                skRender();
                skMsg(b.message || 'Removed.', true);
                skRefreshGrid();
            } else {
                skMsg(b.message || 'Could not remove.', false);
            }
        });
    });

    if (cousinSearchEl) {
        cousinSearchEl.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () { table.setPage(1); }, 300);
        });
    }

    // Click a "low count" badge to show only those campaigns; click again to clear.
    lowBadgeEls.forEach(function (el) {
        const toggle = function () {
            const k = el.getAttribute('data-low');
            lowFilter = lowFilter === k ? '' : k;
            table.setPage(1);
        };
        el.addEventListener('click', toggle);
        el.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
        });
    });

    if (scopeEl) {
        scopeEl.addEventListener('change', function () { table.setPage(1); });
    }

    if (searchEl) {
        searchEl.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () { table.setPage(1); }, 300);
        });
    }
})();
</script>
@endsection
