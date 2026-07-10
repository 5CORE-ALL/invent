@extends('layouts.vertical', ['title' => 'Amazon Ads Audit', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .amz-ads-audit .tabulator .tabulator-header .tabulator-col {
            font-size: 0.8rem;
        }
        .amz-ads-audit .tabulator-row .tabulator-cell {
            font-size: 0.82rem;
        }
        .amz-ads-audit .amz-ads-audit-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            border-radius: 6px;
            padding: 0.3rem 0.6rem;
            font-size: 0.8rem;
            font-weight: 600;
            line-height: 1.2;
            white-space: nowrap;
            color: #fff;
        }
        .amz-ads-audit .amz-ads-audit-badge-red {
            background-color: #dc2626;
        }
        .amz-ads-audit .amz-ads-audit-badge-green {
            background-color: #16a34a;
        }
        .amz-ads-audit .amz-ads-audit-badge .audit-dot {
            cursor: default;
            border-color: rgba(255, 255, 255, 0.6);
        }
        .amz-ads-audit .audit-dot {
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            cursor: pointer;
            border: 1px solid rgba(0, 0, 0, 0.15);
            vertical-align: middle;
        }
        .amz-ads-audit .audit-dot-red {
            background-color: #dc2626;
        }
        .amz-ads-audit .audit-dot-green {
            background-color: #16a34a;
        }
        .amz-ads-audit .audit-history {
            font-size: 0.72rem;
            line-height: 1.3;
            text-align: left;
            white-space: normal;
        }
        .amz-ads-audit .audit-history .hist-row {
            border-bottom: 1px dashed #e9ecef;
            padding: 1px 0;
        }
        .amz-ads-audit .audit-history .hist-fixed-yes {
            color: #16a34a;
            font-weight: 600;
        }
        .amz-ads-audit .audit-history .hist-fixed-no {
            color: #dc2626;
            font-weight: 600;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', ['sub_title' => 'Amazon Ads', 'page_title' => 'Amazon Ads Audit'])

    <div class="row amz-ads-audit">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-2">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <div class="amz-ads-audit-badge amz-ads-audit-badge-red" id="amzAdsAuditPendingWrap" title="Pending Audit: number of campaigns with a red dot (not audited in the last 30 days).">
                            <span>Pending</span>
                            <span class="tabular-nums" id="amzAdsAuditPendingValue">0</span>
                        </div>
                        <div class="amz-ads-audit-badge amz-ads-audit-badge-green" id="amzAdsAuditAuditedWrap" title="Audited: number of campaigns with a green dot (audited in the last 30 days).">
                            <span>Audited</span>
                            <span class="tabular-nums" id="amzAdsAuditAuditedValue">0</span>
                        </div>
                        <div class="btn-group btn-group-sm ms-auto" role="group" aria-label="Filter by ad type" id="amzAdsAuditTypeFilter">
                            <button type="button" class="btn btn-primary" data-adtype="ALL">All</button>
                            <button type="button" class="btn btn-outline-primary" data-adtype="KW" title="Keyword targeting campaigns">KW</button>
                            <button type="button" class="btn btn-outline-primary" data-adtype="PT" title="Product targeting campaigns (campaign name ends with PT)">PT</button>
                        </div>
                    </div>
                    <div id="amzAdsAuditTable"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Audit submit modal --}}
    <div class="modal fade" id="amzAdsAuditModal" tabindex="-1" aria-labelledby="amzAdsAuditModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="amzAdsAuditModalLabel">Audit Campaign</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2" id="amzAdsAuditModalCampaign"></p>
                    <input type="hidden" id="amzAdsAuditCampaignId">
                    <input type="hidden" id="amzAdsAuditCampaignName">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="amzAdsAuditFixed">
                        <label class="form-check-label fw-semibold" for="amzAdsAuditFixed">Fixed?</label>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold mb-1" for="amzAdsAuditDetails">Details <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="amzAdsAuditDetails" rows="3" placeholder="What was checked / done?"></textarea>
                    </div>
                    <p class="small text-danger mb-0 d-none" id="amzAdsAuditError"></p>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" id="amzAdsAuditSaveBtn">Submit</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        (function () {
            var auditDataUrl = @json(route('amazon.ads.audit.data'));
            var auditSaveUrl = @json(route('amazon.ads.audit.save'));
            var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

            function esc(str) {
                return String(str == null ? '' : str)
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }

            function fmtPct(v) {
                if (v === null || v === undefined || v === '') { return '<span class="text-muted">--</span>'; }
                return '<span class="fw-semibold">' + Number(v).toFixed(2) + '%</span>';
            }

            function fmtMoney(v) {
                if (v === null || v === undefined || v === '') { return '<span class="text-muted">--</span>'; }
                return '<span class="fw-semibold">$' + Number(v).toFixed(2) + '</span>';
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

            var table = new Tabulator('#amzAdsAuditTable', {
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
                    { title: 'SPL30', field: 'spl30', hozAlign: 'center', headerHozAlign: 'center', width: 90, formatter: function (c) { var v = c.getValue(); return (v === null || v === undefined || v === '') ? '<span class="text-muted">--</span>' : '<span class="fw-semibold">' + Number(v).toLocaleString('en-US') + '</span>'; } },
                    { title: 'Cvr', field: 'cvr', hozAlign: 'center', headerHozAlign: 'center', width: 90, formatter: function (c) { return fmtPct(c.getValue()); } },
                    { title: 'CPC L30', field: 'cpc', hozAlign: 'center', headerHozAlign: 'center', width: 100, formatter: function (c) { return fmtMoney(c.getValue()); } },
                    { title: 'ACOS', field: 'acos', hozAlign: 'center', headerHozAlign: 'center', width: 90, formatter: function (c) { return fmtPct(c.getValue()); } },
                    {
                        title: 'Link', field: 'link', headerSort: false, hozAlign: 'center', headerHozAlign: 'center', width: 70,
                        formatter: function (c) {
                            return '<a href="' + esc(c.getValue()) + '" target="_blank" rel="noopener" title="Open in Amazon Ads"><i class="fa fa-external-link-alt"></i></a>';
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
                // Count only the rows currently visible under the active ad-type filter.
                var rows = table.getData('active');
                var pending = 0, audited = 0;
                rows.forEach(function (r) {
                    if (!r) { return; }
                    if (r.dot === 'green') { audited++; } else { pending++; }
                });
                var pEl = document.getElementById('amzAdsAuditPendingValue');
                if (pEl) { pEl.textContent = Number(pending).toLocaleString('en-US'); }
                var aEl = document.getElementById('amzAdsAuditAuditedValue');
                if (aEl) { aEl.textContent = Number(audited).toLocaleString('en-US'); }
            }
            table.on('dataProcessed', updatePending);
            table.on('dataFiltered', updatePending);

            // Classify a campaign from its name: PT = name ends with " PT" (product targeting), else KW.
            function adTypeFromName(name) {
                var n = String(name == null ? '' : name).replace(/\s+/g, ' ').trim().toUpperCase().replace(/\.+$/, '');
                return /\sPT$/.test(n) ? 'PT' : 'KW';
            }

            // Ad-type filter (All / KW / PT) derived from the campaign name.
            (function () {
                var wrap = document.getElementById('amzAdsAuditTypeFilter');
                if (!wrap) { return; }
                wrap.addEventListener('click', function (e) {
                    var btn = e.target.closest('button[data-adtype]');
                    if (!btn) { return; }
                    var type = btn.getAttribute('data-adtype');

                    wrap.querySelectorAll('button[data-adtype]').forEach(function (b) {
                        var active = b === btn;
                        b.classList.toggle('btn-primary', active);
                        b.classList.toggle('btn-outline-primary', !active);
                    });

                    if (type === 'ALL') {
                        // Clears programmatic filters only; the Campaign header filter is unaffected.
                        table.clearFilter();
                    } else {
                        table.setFilter(function (data) {
                            return adTypeFromName(data.campaign_name) === type;
                        });
                    }
                    // Refresh the Pending / Audited counts for the newly filtered subset immediately.
                    updatePending();
                });
            })();

            function openAuditModal(row) {
                document.getElementById('amzAdsAuditCampaignId').value = row.campaign_id || '';
                document.getElementById('amzAdsAuditCampaignName').value = row.campaign_name || '';
                document.getElementById('amzAdsAuditModalCampaign').textContent = row.campaign_name || row.campaign_id || '';
                document.getElementById('amzAdsAuditFixed').checked = false;
                document.getElementById('amzAdsAuditDetails').value = '';
                var err = document.getElementById('amzAdsAuditError');
                err.classList.add('d-none');
                err.textContent = '';
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('amzAdsAuditModal')).show();
                }
            }

            document.getElementById('amzAdsAuditSaveBtn').addEventListener('click', function () {
                var btn = this;
                var err = document.getElementById('amzAdsAuditError');
                var cid = document.getElementById('amzAdsAuditCampaignId').value;
                var cname = document.getElementById('amzAdsAuditCampaignName').value;
                var fixed = document.getElementById('amzAdsAuditFixed').checked;
                var details = (document.getElementById('amzAdsAuditDetails').value || '').trim();

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
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('amzAdsAuditModal')).hide();
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
