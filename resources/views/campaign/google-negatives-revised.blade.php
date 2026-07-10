@extends('layouts.vertical', ['title' => 'Revised — Negative Keywords', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .gs-revised .tabulator .tabulator-header .tabulator-col {
            font-size: 0.8rem;
        }
        .gs-revised .tabulator-row .tabulator-cell {
            font-size: 0.82rem;
        }
        .gs-revised .gs-revised-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            border-radius: 8px;
            padding: 0.3rem 0.65rem;
            font-size: 0.8rem;
            font-weight: 700;
            line-height: 1.2;
            white-space: nowrap;
            background-color: #dc2626;
            color: #fff;
        }
        .gs-revised .neg-count-pill {
            display: inline-block;
            min-width: 34px;
            padding: 1px 8px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.78rem;
            color: #fff;
            background-color: #ef4444;
        }
        .gs-revised .neg-count-pill.is-zero {
            background-color: #94a3b8;
        }
        .gs-revised .audit-dot {
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            cursor: pointer;
            border: 1px solid rgba(0, 0, 0, 0.15);
            vertical-align: middle;
        }
        .gs-revised .audit-dot-red {
            background-color: #dc2626;
        }
        .gs-revised .audit-dot-green {
            background-color: #16a34a;
        }
        .gs-revised .audit-history {
            font-size: 0.72rem;
            line-height: 1.3;
            text-align: left;
            white-space: normal;
        }
        .gs-revised .audit-history .hist-row {
            border-bottom: 1px dashed #e9ecef;
            padding: 1px 0;
        }
        .gs-revised .audit-history .hist-fixed-yes {
            color: #16a34a;
            font-weight: 600;
        }
        .gs-revised .audit-history .hist-fixed-no {
            color: #dc2626;
            font-weight: 600;
        }
        .gs-revised .neg-match-badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 10px;
            font-size: 11px;
            color: #fff;
        }
        .gs-revised .neg-level-badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 10px;
            font-size: 11px;
            color: #fff;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', ['sub_title' => 'Google Ads', 'page_title' => 'Revised — Negative Keywords'])

    <div class="row gs-revised">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-2">
                    <div class="d-flex flex-wrap align-items-center gap-1 mb-2">
                        <div class="gs-revised-badge" id="gsRevisedPendingWrap" title="Pending: number of campaigns with a red dot (not audited in the last 30 days).">
                            <span>Pending</span>
                            <span class="tabular-nums" id="gsRevisedPendingValue">0</span>
                        </div>
                    </div>
                    <div id="gsRevisedTable"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Audit submit modal --}}
    <div class="modal fade" id="gsRevisedAuditModal" tabindex="-1" aria-labelledby="gsRevisedAuditModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="gsRevisedAuditModalLabel">Audit Campaign</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2" id="gsRevisedAuditModalCampaign"></p>
                    <input type="hidden" id="gsRevisedAuditCampaignId">
                    <input type="hidden" id="gsRevisedAuditCampaignName">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="gsRevisedAuditFixed">
                        <label class="form-check-label fw-semibold" for="gsRevisedAuditFixed">Fixed?</label>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold mb-1" for="gsRevisedAuditDetails">Details <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="gsRevisedAuditDetails" rows="3" placeholder="What was checked / done?"></textarea>
                    </div>
                    <p class="small text-danger mb-0 d-none" id="gsRevisedAuditError"></p>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" id="gsRevisedAuditSaveBtn">Submit</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Negative keywords viewer modal --}}
    <div class="modal fade" id="gsRevisedNegModal" tabindex="-1" aria-labelledby="gsRevisedNegModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title" id="gsRevisedNegModalLabel">
                        <i class="fas fa-ban" style="color:#ef4444;"></i>
                        Negative keywords — <span id="gsRevisedNegCid" class="text-muted"></span>
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2" id="gsRevisedNegBody" style="max-height:60vh;overflow:auto;">
                    <p class="text-muted small mb-0">Loading…</p>
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
        (function () {
            var revisedDataUrl = @json(route('google.shopping.campaigns.revised.data'));
            var revisedSaveUrl = @json(route('google.shopping.campaigns.revised.save'));
            var negKwUrl = @json(route('google.shopping.campaigns.negatives'));
            var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

            function esc(str) {
                return String(str == null ? '' : str)
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }

            function renderHistory(list) {
                if (!list || !list.length) { return '<span class="text-muted">No history</span>'; }
                return '<div class="audit-history">' + list.map(function (h) {
                    var cls = h.fixed ? 'hist-fixed-yes' : 'hist-fixed-no';
                    var txt = h.fixed ? 'Fixed' : 'Not fixed';
                    return '<div class="hist-row"><span class="text-muted">' + esc(h.created_at || '') + '</span> · '
                        + '<span class="' + cls + '">' + txt + '</span> · ' + esc(h.details || '') + '</div>';
                }).join('') + '</div>';
            }

            var table = new Tabulator('#gsRevisedTable', {
                ajaxURL: revisedDataUrl,
                ajaxResponse: function (url, params, response) {
                    return (response && Array.isArray(response.data)) ? response.data : (response || []);
                },
                layout: 'fitColumns',
                pagination: true,
                paginationSize: 50,
                paginationSizeSelector: [25, 50, 100, 250, 500],
                paginationCounter: 'rows',
                placeholder: 'No campaigns',
                initialSort: [{ column: 'neg_count', dir: 'desc' }],
                columns: [
                    {
                        title: 'Campaign', field: 'campaign_name', headerFilter: 'input', widthGrow: 4, tooltip: true,
                        formatter: function (c) { return esc(c.getValue()); }
                    },
                    {
                        title: 'Neg KW', field: 'neg_count', hozAlign: 'center', headerHozAlign: 'center', width: 110, sorter: 'number',
                        formatter: function (c) {
                            var n = parseInt(c.getValue(), 10) || 0;
                            var cls = n === 0 ? 'neg-count-pill is-zero' : 'neg-count-pill';
                            return '<span class="' + cls + '">' + n.toLocaleString() + '</span>';
                        },
                        cellClick: function (e, cell) {
                            var d = cell.getData();
                            if ((parseInt(d.neg_count, 10) || 0) > 0) { openNegModal(d); }
                        }
                    },
                    {
                        title: 'View', field: 'view', headerSort: false, hozAlign: 'center', headerHozAlign: 'center', width: 70,
                        formatter: function () {
                            return '<i class="fa fa-eye" title="View negative keywords" style="cursor:pointer;color:#0d6efd;"></i>';
                        },
                        cellClick: function (e, cell) { openNegModal(cell.getData()); }
                    },
                    {
                        title: 'Status', field: 'dot', headerSort: true, hozAlign: 'center', headerHozAlign: 'center', width: 90,
                        formatter: function (c) {
                            var d = c.getData();
                            var cls = d.dot === 'green' ? 'audit-dot-green' : 'audit-dot-red';
                            var title = d.dot === 'green'
                                ? ('Audited ' + (d.latest_audit_at || '') + ' (within 30 days). Click to add an audit.')
                                : 'Not audited in the last 30 days. Click to add an audit.';
                            return '<span class="audit-dot ' + cls + '" title="' + esc(title) + '"></span>';
                        },
                        cellClick: function (e, cell) { openAuditModal(cell.getData()); }
                    },
                    { title: 'History', field: 'history', headerSort: false, widthGrow: 4, formatter: function (c) { return renderHistory(c.getValue()); } }
                ]
            });

            function updatePending() {
                var rows = table.getData();
                var pending = rows.reduce(function (n, r) { return n + (r && r.dot === 'red' ? 1 : 0); }, 0);
                var el = document.getElementById('gsRevisedPendingValue');
                if (el) { el.textContent = Number(pending).toLocaleString('en-US'); }
            }
            table.on('dataProcessed', updatePending);

            // ---- Audit modal ----
            function openAuditModal(row) {
                document.getElementById('gsRevisedAuditCampaignId').value = row.campaign_id || '';
                document.getElementById('gsRevisedAuditCampaignName').value = row.campaign_name || '';
                document.getElementById('gsRevisedAuditModalCampaign').textContent = row.campaign_name || row.campaign_id || '';
                document.getElementById('gsRevisedAuditFixed').checked = false;
                document.getElementById('gsRevisedAuditDetails').value = '';
                var err = document.getElementById('gsRevisedAuditError');
                err.classList.add('d-none');
                err.textContent = '';
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('gsRevisedAuditModal')).show();
                }
            }

            document.getElementById('gsRevisedAuditSaveBtn').addEventListener('click', function () {
                var btn = this;
                var err = document.getElementById('gsRevisedAuditError');
                var cid = document.getElementById('gsRevisedAuditCampaignId').value;
                var cname = document.getElementById('gsRevisedAuditCampaignName').value;
                var fixed = document.getElementById('gsRevisedAuditFixed').checked;
                var details = (document.getElementById('gsRevisedAuditDetails').value || '').trim();

                if (!details) {
                    err.textContent = 'Details is required.';
                    err.classList.remove('d-none');
                    return;
                }
                err.classList.add('d-none');
                btn.disabled = true;

                fetch(revisedSaveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ campaign_id: cid, campaign_name: cname, fixed: fixed ? 1 : 0, details: details })
                }).then(function (res) {
                    return res.json().then(function (b) { return { ok: res.ok, body: b }; });
                }).then(function (out) {
                    btn.disabled = false;
                    if (out.ok && out.body && out.body.ok) {
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('gsRevisedAuditModal')).hide();
                        table.setData(revisedDataUrl).then(updatePending);
                    } else {
                        var msg = 'Failed to save.';
                        if (out.body && out.body.errors) {
                            msg = Object.values(out.body.errors).map(function (a) { return a.join(' '); }).join(' ');
                        } else if (out.body && out.body.message) {
                            msg = out.body.message;
                        }
                        err.textContent = msg;
                        err.classList.remove('d-none');
                    }
                }).catch(function () {
                    btn.disabled = false;
                    err.textContent = 'Failed to save.';
                    err.classList.remove('d-none');
                });
            });

            // ---- Negative keywords viewer modal ----
            function matchBadge(m) {
                var u = String(m || '').toUpperCase();
                var bg = u === 'EXACT' ? '#0d6efd' : (u === 'PHRASE' ? '#6f42c1' : (u === 'BROAD' ? '#20c997' : '#6c757d'));
                return '<span class="neg-match-badge" style="background:' + bg + ';">' + esc(u || '--') + '</span>';
            }
            function levelBadge(l) {
                var isCamp = String(l).toUpperCase() === 'CAMPAIGN';
                var bg = isCamp ? '#334155' : '#0891b2';
                return '<span class="neg-level-badge" style="background:' + bg + ';">' + (isCamp ? 'Campaign' : 'Ad group') + '</span>';
            }
            function openNegModal(row) {
                var cid = String(row.campaign_id || '').replace(/\D/g, '');
                if (!cid) return;
                var body = document.getElementById('gsRevisedNegBody');
                var titleCid = document.getElementById('gsRevisedNegCid');
                if (titleCid) { titleCid.textContent = (row.campaign_name ? row.campaign_name + ' ' : '') + '(' + cid + ')'; }
                if (body) { body.innerHTML = '<p class="text-muted small mb-0">Loading…</p>'; }
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('gsRevisedNegModal')).show();
                }
                fetch(negKwUrl + '?campaign_id=' + encodeURIComponent(cid), {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function (r) { return r.json(); }).then(function (resp) {
                    renderNeg(resp || {});
                }).catch(function () {
                    if (body) { body.innerHTML = '<p class="text-danger small mb-0">Failed to load negative keywords.</p>'; }
                });
            }
            function renderNeg(resp) {
                var body = document.getElementById('gsRevisedNegBody');
                if (!body) return;
                var rows = Array.isArray(resp.data) ? resp.data : [];
                var counts = resp.counts || {};
                if (!rows.length) {
                    body.innerHTML = '<p class="text-muted small mb-0">No negative keywords stored for this campaign.</p>';
                    return;
                }
                var summary = '<div class="small text-muted mb-2">'
                            + 'Campaign-level: <strong>' + (counts.campaign || 0) + '</strong> · '
                            + 'Ad group-level: <strong>' + (counts.ad_group || 0) + '</strong> · '
                            + 'Total: <strong>' + (counts.total || rows.length) + '</strong></div>';
                var html = summary
                         + '<table class="table table-sm table-hover mb-0"><thead><tr>'
                         + '<th>Level</th><th>Ad group</th><th>Negative keyword</th><th class="text-center">Match</th>'
                         + '</tr></thead><tbody>';
                rows.forEach(function (d) {
                    html += '<tr>'
                          + '<td>' + levelBadge(d.level) + '</td>'
                          + '<td>' + (d.ad_group_name ? esc(d.ad_group_name) : '<span class="text-muted">--</span>') + '</td>'
                          + '<td>' + esc(d.keyword_text) + '</td>'
                          + '<td class="text-center">' + matchBadge(d.match_type) + '</td>'
                          + '</tr>';
                });
                html += '</tbody></table>';
                body.innerHTML = html;
            }
        })();
    </script>
@endsection
