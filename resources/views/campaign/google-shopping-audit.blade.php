@extends('layouts.vertical', ['title' => 'Google Shopping Ads Audit', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .gs-ads-audit .tabulator .tabulator-header .tabulator-col {
            font-size: 0.8rem;
        }
        .gs-ads-audit .tabulator-row .tabulator-cell {
            font-size: 0.82rem;
        }
        .gs-ads-audit .gs-ads-audit-badge {
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
        .gs-ads-audit .audit-dot {
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            cursor: pointer;
            border: 1px solid rgba(0, 0, 0, 0.15);
            vertical-align: middle;
        }
        .gs-ads-audit .audit-dot-red {
            background-color: #dc2626;
        }
        .gs-ads-audit .audit-dot-green {
            background-color: #16a34a;
        }
        .gs-ads-audit .audit-history {
            font-size: 0.72rem;
            line-height: 1.3;
            text-align: left;
            white-space: normal;
        }
        .gs-ads-audit .audit-history .hist-row {
            border-bottom: 1px dashed #e9ecef;
            padding: 1px 0;
        }
        .gs-ads-audit .audit-history .hist-fixed-yes {
            color: #16a34a;
            font-weight: 600;
        }
        .gs-ads-audit .audit-history .hist-fixed-no {
            color: #dc2626;
            font-weight: 600;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', ['sub_title' => 'Google Ads', 'page_title' => 'Google Shopping Ads Audit'])

    <div class="row gs-ads-audit">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-2">
                    <div class="d-flex flex-wrap align-items-center gap-1 mb-2">
                        <div class="gs-ads-audit-badge" id="gsAdsAuditPendingWrap" title="Pending Audit: number of campaigns with a red dot (not audited in the last 30 days).">
                            <span>Pending Audit</span>
                            <span class="tabular-nums" id="gsAdsAuditPendingValue">0</span>
                        </div>
                    </div>
                    <div id="gsAdsAuditTable"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Audit submit modal --}}
    <div class="modal fade" id="gsAdsAuditModal" tabindex="-1" aria-labelledby="gsAdsAuditModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="gsAdsAuditModalLabel">Audit Campaign</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2" id="gsAdsAuditModalCampaign"></p>
                    <input type="hidden" id="gsAdsAuditCampaignId">
                    <input type="hidden" id="gsAdsAuditCampaignName">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="gsAdsAuditFixed">
                        <label class="form-check-label fw-semibold" for="gsAdsAuditFixed">Fixed?</label>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold mb-1" for="gsAdsAuditDetails">Details <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="gsAdsAuditDetails" rows="3" placeholder="What was checked / done?"></textarea>
                    </div>
                    <p class="small text-danger mb-0 d-none" id="gsAdsAuditError"></p>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" id="gsAdsAuditSaveBtn">Submit</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        (function () {
            var auditDataUrl = @json(route('google.shopping.audit.data'));
            var auditSaveUrl = @json(route('google.shopping.audit.save'));
            var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

            function esc(str) {
                return String(str == null ? '' : str)
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }

            function fmtPct(v) {
                if (v === null || v === undefined || v === '') { return '<span class="text-muted">--</span>'; }
                return '<span class="fw-semibold">' + Number(v).toFixed(2) + '%</span>';
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

            var table = new Tabulator('#gsAdsAuditTable', {
                ajaxURL: auditDataUrl,
                ajaxResponse: function (url, params, response) {
                    return (response && Array.isArray(response.data)) ? response.data : (response || []);
                },
                layout: 'fitColumns',
                pagination: true,
                paginationSize: 50,
                paginationSizeSelector: [25, 50, 100, 250, 500],
                paginationCounter: 'rows',
                placeholder: 'No campaigns',
                columns: [
                    { title: 'Campaign', field: 'campaign_name', headerFilter: 'input', widthGrow: 3, tooltip: true, formatter: function (c) { return esc(c.getValue()); } },
                    { title: 'Cvr', field: 'cvr', hozAlign: 'center', headerHozAlign: 'center', width: 90, formatter: function (c) { return fmtPct(c.getValue()); } },
                    { title: 'CTR', field: 'ctr', hozAlign: 'center', headerHozAlign: 'center', width: 90, formatter: function (c) { return fmtPct(c.getValue()); } },
                    { title: 'ACOS', field: 'acos', hozAlign: 'center', headerHozAlign: 'center', width: 90, formatter: function (c) { return fmtPct(c.getValue()); } },
                    {
                        title: 'Link', field: 'link', headerSort: false, hozAlign: 'center', headerHozAlign: 'center', width: 70,
                        formatter: function (c) {
                            return '<a href="' + esc(c.getValue()) + '" target="_blank" rel="noopener" title="Open Google Shopping grid"><i class="fa fa-external-link-alt"></i></a>';
                        }
                    },
                    {
                        title: 'Status', field: 'dot', headerSort: true, hozAlign: 'center', headerHozAlign: 'center', width: 80,
                        formatter: function (c) {
                            var d = c.getData();
                            var cls = d.dot === 'green' ? 'audit-dot-green' : 'audit-dot-red';
                            var title = d.dot === 'green'
                                ? ('Audited ' + (d.latest_audit_at || '') + ' (within 30 days). Click to add an audit.')
                                : 'Not audited in the last 30 days. Click to add an audit.';
                            return '<span class="audit-dot ' + cls + '" title="' + esc(title) + '"></span>';
                        },
                        cellClick: function (e, cell) {
                            openAuditModal(cell.getData());
                        }
                    },
                    { title: 'History', field: 'history', headerSort: false, widthGrow: 3, formatter: function (c) { return renderHistory(c.getValue()); } }
                ]
            });

            function updatePending() {
                var rows = table.getData();
                var pending = rows.reduce(function (n, r) { return n + (r && r.dot === 'red' ? 1 : 0); }, 0);
                var el = document.getElementById('gsAdsAuditPendingValue');
                if (el) { el.textContent = Number(pending).toLocaleString('en-US'); }
            }
            table.on('dataProcessed', updatePending);

            function openAuditModal(row) {
                document.getElementById('gsAdsAuditCampaignId').value = row.campaign_id || '';
                document.getElementById('gsAdsAuditCampaignName').value = row.campaign_name || '';
                document.getElementById('gsAdsAuditModalCampaign').textContent = row.campaign_name || row.campaign_id || '';
                document.getElementById('gsAdsAuditFixed').checked = false;
                document.getElementById('gsAdsAuditDetails').value = '';
                var err = document.getElementById('gsAdsAuditError');
                err.classList.add('d-none');
                err.textContent = '';
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('gsAdsAuditModal')).show();
                }
            }

            document.getElementById('gsAdsAuditSaveBtn').addEventListener('click', function () {
                var btn = this;
                var err = document.getElementById('gsAdsAuditError');
                var cid = document.getElementById('gsAdsAuditCampaignId').value;
                var cname = document.getElementById('gsAdsAuditCampaignName').value;
                var fixed = document.getElementById('gsAdsAuditFixed').checked;
                var details = (document.getElementById('gsAdsAuditDetails').value || '').trim();

                if (!details) {
                    err.textContent = 'Details is required.';
                    err.classList.remove('d-none');
                    return;
                }
                err.classList.add('d-none');
                btn.disabled = true;

                fetch(auditSaveUrl, {
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
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('gsAdsAuditModal')).hide();
                        table.setData(auditDataUrl).then(updatePending);
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
        })();
    </script>
@endsection
