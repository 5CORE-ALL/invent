@extends('layouts.vertical', ['title' => 'Map Issues', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">

    <style>
        .map-cell-error {
            background-color: #f8d7da !important;
            color: #a00211 !important;
            font-weight: 600;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', ['page_title' => 'Map Issues', 'sub_title' => 'Map Issues'])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="mb-3 d-flex gap-2 flex-wrap">
                        <span class="badge bg-secondary fs-6 p-2" id="not-map-count-badge"
                            style="color: white; font-weight: bold; cursor: pointer;"
                            title="eBay Not Mapped — listed on eBay but INV does not match eBay Inv">E NP: 0</span>
                        <span class="badge bg-secondary fs-6 p-2" id="mismatch-count-badge"
                            style="color: white; font-weight: bold; cursor: pointer;"
                            title="eBay SKU Mismatch — eBay SKU does not exactly match the Product Master SKU">E SM: 0</span>
                        <span class="badge bg-warning fs-6 p-2" id="missing-listing-count-badge"
                            style="color: white; font-weight: bold; cursor: pointer;"
                            title="eBay Missing Listing — not listed on eBay, marked REQ, INV > 0">E ML: 0</span>
                        <span class="badge bg-info fs-6 p-2" id="ebay2-not-map-count-badge"
                            style="color: white; font-weight: bold; cursor: pointer;"
                            title="eBay 2 Not Mapped — listed on eBay 2 but INV does not match eBay2 Inv">E2 NP: 0</span>
                        <span class="badge bg-info fs-6 p-2" id="ebay2-mismatch-count-badge"
                            style="color: white; font-weight: bold; cursor: pointer;"
                            title="eBay 2 SKU Mismatch — eBay 2 SKU does not exactly match the Product Master SKU">E2 SM: 0</span>
                        <span class="badge bg-info fs-6 p-2" id="ebay2-missing-listing-count-badge"
                            style="color: white; font-weight: bold; cursor: pointer;"
                            title="eBay 2 Missing Listing — not listed on eBay 2, marked REQ, INV > 0">E2 ML: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="ebay3-not-map-count-badge"
                            style="color: white; font-weight: bold; cursor: pointer;"
                            title="eBay 3 Not Mapped — listed on eBay 3 but INV does not match eBay3 Inv">E3 NP: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="ebay3-mismatch-count-badge"
                            style="color: white; font-weight: bold; cursor: pointer;"
                            title="eBay 3 SKU Mismatch — eBay 3 SKU does not exactly match the Product Master SKU">E3 SM: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="ebay3-missing-listing-count-badge"
                            style="color: white; font-weight: bold; cursor: pointer;"
                            title="eBay 3 Missing Listing — not listed on eBay 3, marked REQ, INV > 0">E3 ML: 0</span>
                    </div>
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="req-only-toggle" style="cursor:pointer;">
                        <label class="form-check-label" for="req-only-toggle" style="cursor:pointer;">Show Req only</label>
                    </div>
                    <div id="map-issues-table"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="map-issue-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Map Issue</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <th style="width: 40%;">Marketplace</th>
                            <td id="modal-marketplace"></td>
                        </tr>
                        <tr>
                            <th>Product Master SKU</th>
                            <td id="modal-pm-sku"></td>
                        </tr>
                        <tr>
                            <th id="modal-site-sku-label">eBay SKU</th>
                            <td id="modal-ebay-sku"></td>
                        </tr>
                        <tr>
                            <th>Issue</th>
                            <td id="modal-issue" class="text-danger fw-bold"></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var activeFilter = null;  // which badge is active, or null
            var activeMarket = null;  // 'ebay' | 'ebay2' | 'ebay3' for the active badge
            var reqOnly = false;      // "Show Req only" toggle

            var invFieldByMarket = { ebay: 'Ebay Inv', ebay2: 'Ebay2 Inv', ebay3: 'Ebay3 Inv' };
            var nrFieldByMarket  = { ebay: 'ebay_nr_req', ebay2: 'ebay2_nr_req', ebay3: 'ebay3_nr_req' };

            // NR/REQ column: green "Req", red "Not Req".
            function nrReqFormatter(cell) {
                var v = cell.getValue();
                if (v === 'NRL') return '<span style="color:#a00211;font-weight:600;">Not Req</span>';
                return '<span style="color:#28a745;font-weight:600;">Req</span>';
            }

            // Persist a NR/REQ change for the given marketplace.
            function saveNrReq(market, cell) {
                var d = cell.getRow().getData();
                fetch("{{ route('map.issues.update.nr') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ sku: d['(Child) sku'], marketplace: market, status: cell.getValue() }),
                }).then(function (r) { return r.json(); }).then(function (res) {
                    if (!res || !res.success) {
                        alert('Failed to update NR/REQ status');
                        cell.restoreOldValue();
                    }
                }).catch(function () {
                    alert('Failed to update NR/REQ status');
                    cell.restoreOldValue();
                });
            }

            function nrEdited(market) {
                return function (cell) { saveNrReq(market, cell); };
            }

            // Marketplace SKU column: shows the active marketplace's SKU.
            function marketSkuFormatter(cell) {
                if (!activeMarket) return '';
                var v = cell.getRow().getData()[activeMarket + '_sku'];
                return (v === null || v === undefined || v === '') ? '-' : v;
            }

            // Diff column: Shopify INV minus the active marketplace's Inv.
            // Green dot for positive (+), red dot for negative (-).
            function diffFormatter(cell) {
                if (!activeMarket) return '';
                var d = cell.getRow().getData();
                var inv = parseFloat(d['INV']) || 0;
                var mp = parseFloat(d[invFieldByMarket[activeMarket]]) || 0;
                var diff = inv - mp;
                var color = diff > 0 ? '#28a745' : (diff < 0 ? '#a00211' : '#6c757d');
                var sign = diff > 0 ? '+' : '';
                var dot = '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;' +
                    'background:' + color + ';margin-right:6px;vertical-align:middle;"></span>';
                return dot + '<span style="color:' + color + ';font-weight:600;">' + sign + diff + '</span>';
            }

            // Info-icon cell formatter: shows an icon when `mismatchField` is true on the row.
            // Renders "NL" (not listed) instead of 0 / empty values.
            function invFormatter(mismatchField) {
                return function (cell) {
                    var d = cell.getRow().getData();
                    var v = cell.getValue();
                    var num = parseFloat(v);
                    var isNL = (v === null || v === undefined || v === '' || isNaN(num) || num === 0);
                    var display = isNL ? '<span style="color:#a00211;font-weight:600;">NL</span>' : v;
                    if (d[mismatchField]) {
                        return display + ' <i class="fas fa-info-circle map-info-icon" title="View issue" ' +
                            'style="cursor:pointer;color:#a00211;margin-left:6px;"></i>';
                    }
                    return display;
                };
            }

            var issueModal = new bootstrap.Modal(document.getElementById('map-issue-modal'));

            function showIssueModal(marketplace, pmSku, siteSku, issue) {
                document.getElementById('modal-marketplace').textContent = marketplace;
                document.getElementById('modal-pm-sku').textContent = pmSku || '-';
                document.getElementById('modal-site-sku-label').textContent = marketplace + ' SKU';
                document.getElementById('modal-ebay-sku').textContent = siteSku || '-';
                document.getElementById('modal-issue').textContent = issue || '-';
                issueModal.show();
            }

            var table = new Tabulator('#map-issues-table', {
                layout: 'fitColumns',
                placeholder: 'No Data Available',
                ajaxURL: "{{ route('map.issues.data') }}",
                ajaxResponse: function (url, params, response) {
                    document.getElementById('not-map-count-badge').textContent =
                        'E NP: ' + (response.not_map_count || 0).toLocaleString();
                    document.getElementById('mismatch-count-badge').textContent =
                        'E SM: ' + (response.mismatch_count || 0).toLocaleString();
                    document.getElementById('missing-listing-count-badge').textContent =
                        'E ML: ' + (response.missing_listing_count || 0).toLocaleString();
                    document.getElementById('ebay2-not-map-count-badge').textContent =
                        'E2 NP: ' + (response.ebay2_not_map_count || 0).toLocaleString();
                    document.getElementById('ebay2-mismatch-count-badge').textContent =
                        'E2 SM: ' + (response.ebay2_mismatch_count || 0).toLocaleString();
                    document.getElementById('ebay2-missing-listing-count-badge').textContent =
                        'E2 ML: ' + (response.ebay2_missing_listing_count || 0).toLocaleString();
                    document.getElementById('ebay3-not-map-count-badge').textContent =
                        'E3 NP: ' + (response.ebay3_not_map_count || 0).toLocaleString();
                    document.getElementById('ebay3-mismatch-count-badge').textContent =
                        'E3 SM: ' + (response.ebay3_mismatch_count || 0).toLocaleString();
                    document.getElementById('ebay3-missing-listing-count-badge').textContent =
                        'E3 ML: ' + (response.ebay3_missing_listing_count || 0).toLocaleString();
                    return response.data || [];
                },
                pagination: true,
                paginationMode: 'local',
                paginationSize: 50,
                paginationSizeSelector: [25, 50, 100, 250],
                columns: [
                    { title: '(Child) SKU', field: '(Child) sku', headerFilter: 'input', widthGrow: 2 },
                    { title: 'Marketplace SKU', field: 'mp_sku', visible: false, widthGrow: 2, formatter: marketSkuFormatter },
                    { title: 'NR/REQ', field: 'ebay_nr_req', visible: false, editor: 'list', editorParams: { values: { REQ: 'Req', NRL: 'Not Req' } }, formatter: nrReqFormatter, cellEdited: nrEdited('ebay') },
                    { title: 'NR/REQ', field: 'ebay2_nr_req', visible: false, editor: 'list', editorParams: { values: { REQ: 'Req', NRL: 'Not Req' } }, formatter: nrReqFormatter, cellEdited: nrEdited('ebay2') },
                    { title: 'NR/REQ', field: 'ebay3_nr_req', visible: false, editor: 'list', editorParams: { values: { REQ: 'Req', NRL: 'Not Req' } }, formatter: nrReqFormatter, cellEdited: nrEdited('ebay3') },
                    { title: 'INV', field: 'INV', hozAlign: 'right', sorter: 'number' },
                    {
                        title: 'Ebay Inv', field: 'Ebay Inv', hozAlign: 'right', sorter: 'number',
                        formatter: invFormatter('ebay_mismatch'),
                        cellClick: function (e, cell) {
                            if (e.target.classList.contains('map-info-icon')) {
                                var d = cell.getRow().getData();
                                showIssueModal('eBay', d['(Child) sku'], d.ebay_sku, d.issue);
                            }
                        },
                    },
                    {
                        title: 'Ebay2 Inv', field: 'Ebay2 Inv', hozAlign: 'right', sorter: 'number',
                        formatter: invFormatter('ebay2_mismatch'),
                        cellClick: function (e, cell) {
                            if (e.target.classList.contains('map-info-icon')) {
                                var d = cell.getRow().getData();
                                showIssueModal('eBay 2', d['(Child) sku'], d.ebay2_sku, d.ebay2_issue);
                            }
                        },
                    },
                    {
                        title: 'Ebay3 Inv', field: 'Ebay3 Inv', hozAlign: 'right', sorter: 'number',
                        formatter: invFormatter('ebay3_mismatch'),
                        cellClick: function (e, cell) {
                            if (e.target.classList.contains('map-info-icon')) {
                                var d = cell.getRow().getData();
                                showIssueModal('eBay 3', d['(Child) sku'], d.ebay3_sku, d.ebay3_issue);
                            }
                        },
                    },
                    { title: 'Diff', field: 'diff', visible: false, hozAlign: 'right', formatter: diffFormatter },
                ],
            });

            // Each badge maps to a single boolean filter field. All are mutually exclusive.
            // eBay badges use gray/red; eBay 2 badges use blue/dark-blue.
            var badges = {
                enp:  { el: document.getElementById('not-map-count-badge'),        field: 'is_not_map',    market: 'ebay',  off: 'bg-secondary', on: 'bg-danger' },
                esm:  { el: document.getElementById('mismatch-count-badge'),       field: 'has_issue',     market: 'ebay',  off: 'bg-secondary', on: 'bg-danger' },
                eml:  { el: document.getElementById('missing-listing-count-badge'), field: 'missing_listing', market: 'ebay', off: 'bg-warning',  on: 'bg-danger' },
                e2np: { el: document.getElementById('ebay2-not-map-count-badge'),  field: 'ebay2_not_map', market: 'ebay2', off: 'bg-info',      on: 'bg-primary' },
                e2sm: { el: document.getElementById('ebay2-mismatch-count-badge'), field: 'ebay2_mismatch',market: 'ebay2', off: 'bg-info',      on: 'bg-primary' },
                e2ml: { el: document.getElementById('ebay2-missing-listing-count-badge'), field: 'ebay2_missing_listing', market: 'ebay2', off: 'bg-info', on: 'bg-primary' },
                e3np: { el: document.getElementById('ebay3-not-map-count-badge'),  field: 'ebay3_not_map', market: 'ebay3', off: 'bg-success',   on: 'bg-dark' },
                e3sm: { el: document.getElementById('ebay3-mismatch-count-badge'), field: 'ebay3_mismatch',market: 'ebay3', off: 'bg-success',   on: 'bg-dark' },
                e3ml: { el: document.getElementById('ebay3-missing-listing-count-badge'), field: 'ebay3_missing_listing', market: 'ebay3', off: 'bg-success', on: 'bg-dark' },
            };

            function applyFilters() {
                Object.keys(badges).forEach(function (k) {
                    var b = badges[k];
                    var on = (activeFilter === k);
                    b.el.classList.toggle(b.off, !on);
                    b.el.classList.toggle(b.on, on);
                });

                if (activeFilter) {
                    activeMarket = badges[activeFilter].market;
                    table.showColumn('mp_sku');
                    table.showColumn('diff');
                    // Show only the active marketplace's Inv + NR/REQ columns.
                    Object.keys(invFieldByMarket).forEach(function (m) {
                        if (m === activeMarket) {
                            table.showColumn(invFieldByMarket[m]);
                            table.showColumn(nrFieldByMarket[m]);
                        } else {
                            table.hideColumn(invFieldByMarket[m]);
                            table.hideColumn(nrFieldByMarket[m]);
                        }
                    });
                } else {
                    activeMarket = null;
                    table.hideColumn('mp_sku');
                    table.hideColumn('diff');
                    Object.keys(invFieldByMarket).forEach(function (m) {
                        table.showColumn(invFieldByMarket[m]);
                        table.hideColumn(nrFieldByMarket[m]);
                    });
                }

                // Combine the badge filter with the "Req only" filter.
                var filters = [];
                if (activeFilter) {
                    filters.push({ field: badges[activeFilter].field, type: '=', value: true });
                }
                if (reqOnly && activeMarket) {
                    filters.push({ field: nrFieldByMarket[activeMarket], type: '=', value: 'REQ' });
                }
                if (filters.length) {
                    table.setFilter(filters);
                } else {
                    table.clearFilter();
                }
                table.redraw(true);
            }

            Object.keys(badges).forEach(function (k) {
                badges[k].el.addEventListener('click', function () {
                    activeFilter = (activeFilter === k) ? null : k;
                    applyFilters();
                });
            });

            document.getElementById('req-only-toggle').addEventListener('change', function () {
                reqOnly = this.checked;
                applyFilters();
            });
        });
    </script>
@endsection
