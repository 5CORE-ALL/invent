@extends('layouts.vertical', ['title' => 'Missing Mapping', 'sidenav' => 'condensed'])

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

        .map-channel-dd .btn {
            color: #fff !important;
            font-weight: 700;
            border: 0;
            white-space: nowrap;
        }

        .map-channel-dd .btn.map-active {
            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.55);
            outline: 2px solid #fff;
            outline-offset: -1px;
        }

        .map-channel-dd .dropdown-item.active,
        .map-channel-dd .dropdown-item:active {
            background-color: #343a40;
        }

        .map-channel-dd .dropdown-menu {
            min-width: 9rem;
        }

        .map-ch-ebay { background-color: #6c757d !important; }
        .map-ch-ebay2 { background-color: #0dcaf0 !important; color: #000 !important; }
        .map-ch-ebay3 { background-color: #198754 !important; }
        .map-ch-amazon { background-color: #ffc107 !important; color: #000 !important; }
        .map-ch-reverb { background-color: #6f42c1 !important; }
        .map-ch-macys { background-color: #c8102e !important; }
        .map-ch-bestbuy { background-color: #0046be !important; }
        .map-ch-tiendamia { background-color: #009688 !important; }
        .map-ch-temu { background-color: #fb6c1e !important; }
        .map-ch-shein { background-color: #333 !important; }
        .map-ch-newegg { background-color: #f59e0b !important; color: #000 !important; }
        .map-ch-aliexpress { background-color: #e62e04 !important; }

        .map-ch-ebay.map-active { background-color: #dc3545 !important; color: #fff !important; }
        .map-ch-ebay2.map-active { background-color: #0d6efd !important; color: #fff !important; }
        .map-ch-ebay3.map-active { background-color: #212529 !important; color: #fff !important; }
        .map-ch-amazon.map-active { background-color: #dc3545 !important; color: #fff !important; }
        .map-ch-reverb.map-active { background-color: #3d1f73 !important; }
        .map-ch-macys.map-active { background-color: #7a0a1c !important; }
        .map-ch-bestbuy.map-active { background-color: #00257a !important; }
        .map-ch-tiendamia.map-active { background-color: #00574d !important; }
        .map-ch-temu.map-active { background-color: #a8430c !important; }
        .map-ch-shein.map-active { background-color: #000 !important; }
        .map-ch-newegg.map-active { background-color: #b45309 !important; color: #fff !important; }
        .map-ch-aliexpress.map-active { background-color: #a31f02 !important; }
    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', ['page_title' => 'Missing Mapping', 'sub_title' => 'Missing Mapping'])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="mb-3 d-flex gap-2 flex-wrap" id="map-channel-filters">
                        <div class="dropdown map-channel-dd">
                            <button class="btn btn-sm dropdown-toggle map-ch-ebay" type="button" data-bs-toggle="dropdown" data-channel="ebay" aria-expanded="false" title="eBay filters">
                                <span class="map-ch-label">E</span>
                            </button>
                            <ul class="dropdown-menu">
                                <li><button type="button" class="dropdown-item d-flex justify-content-between" data-filter="enp" title="Not Mapped — listed but INV does not match eBay Inv"><span>NM</span><span class="fw-bold ms-3" data-count="nm">0</span></button></li>
                                <li><button type="button" class="dropdown-item d-flex justify-content-between" data-filter="esm" title="SKU Mismatch"><span>SM</span><span class="fw-bold ms-3" data-count="sm">0</span></button></li>
                                <li><button type="button" class="dropdown-item d-flex justify-content-between" data-filter="eml" title="Missing Listing — not listed, REQ, INV &gt; 0"><span>NL</span><span class="fw-bold ms-3" data-count="nl">0</span></button></li>
                            </ul>
                        </div>
                        <div class="dropdown map-channel-dd">
                            <button class="btn btn-sm dropdown-toggle map-ch-ebay2" type="button" data-bs-toggle="dropdown" data-channel="ebay2" aria-expanded="false" title="eBay 2 filters">
                                <span class="map-ch-label">E2</span>
                            </button>
                            <ul class="dropdown-menu">
                                <li><button type="button" class="dropdown-item d-flex justify-content-between" data-filter="e2np" title="Not Mapped"><span>NM</span><span class="fw-bold ms-3" data-count="nm">0</span></button></li>
                                <li><button type="button" class="dropdown-item d-flex justify-content-between" data-filter="e2sm" title="SKU Mismatch"><span>SM</span><span class="fw-bold ms-3" data-count="sm">0</span></button></li>
                                <li><button type="button" class="dropdown-item d-flex justify-content-between" data-filter="e2ml" title="Missing Listing"><span>NL</span><span class="fw-bold ms-3" data-count="nl">0</span></button></li>
                            </ul>
                        </div>
                        <div class="dropdown map-channel-dd">
                            <button class="btn btn-sm dropdown-toggle map-ch-ebay3" type="button" data-bs-toggle="dropdown" data-channel="ebay3" aria-expanded="false" title="eBay 3 filters">
                                <span class="map-ch-label">E3</span>
                            </button>
                            <ul class="dropdown-menu">
                                <li><button type="button" class="dropdown-item d-flex justify-content-between" data-filter="e3np" title="Not Mapped"><span>NM</span><span class="fw-bold ms-3" data-count="nm">0</span></button></li>
                                <li><button type="button" class="dropdown-item d-flex justify-content-between" data-filter="e3sm" title="SKU Mismatch"><span>SM</span><span class="fw-bold ms-3" data-count="sm">0</span></button></li>
                                <li><button type="button" class="dropdown-item d-flex justify-content-between" data-filter="e3ml" title="Missing Listing"><span>NL</span><span class="fw-bold ms-3" data-count="nl">0</span></button></li>
                            </ul>
                        </div>
                        <div class="dropdown map-channel-dd">
                            <button class="btn btn-sm dropdown-toggle map-ch-amazon" type="button" data-bs-toggle="dropdown" data-channel="amazon" aria-expanded="false" title="Amazon filters">
                                <span class="map-ch-label">Amz</span>
                            </button>
                            <ul class="dropdown-menu">
                                <li><button type="button" class="dropdown-item d-flex justify-content-between" data-filter="anp" title="Not Mapped"><span>NM</span><span class="fw-bold ms-3" data-count="nm">0</span></button></li>
                                <li><button type="button" class="dropdown-item d-flex justify-content-between" data-filter="asm" title="SKU Mismatch"><span>SM</span><span class="fw-bold ms-3" data-count="sm">0</span></button></li>
                                <li><button type="button" class="dropdown-item d-flex justify-content-between" data-filter="aml" title="Missing Listing"><span>NL</span><span class="fw-bold ms-3" data-count="nl">0</span></button></li>
                            </ul>
                        </div>
                        <div class="dropdown map-channel-dd">
                            <button class="btn btn-sm dropdown-toggle map-ch-reverb" type="button" data-bs-toggle="dropdown" data-channel="reverb" aria-expanded="false" title="Reverb filters">
                                <span class="map-ch-label">R</span>
                            </button>
                            <ul class="dropdown-menu">
                                <li><button type="button" class="dropdown-item d-flex justify-content-between" data-filter="rnp" title="Not Mapped"><span>NM</span><span class="fw-bold ms-3" data-count="nm">0</span></button></li>
                                <li><button type="button" class="dropdown-item d-flex justify-content-between" data-filter="rsm" title="SKU Mismatch"><span>SM</span><span class="fw-bold ms-3" data-count="sm">0</span></button></li>
                                <li><button type="button" class="dropdown-item d-flex justify-content-between" data-filter="rml" title="Missing Listing"><span>NL</span><span class="fw-bold ms-3" data-count="nl">0</span></button></li>
                            </ul>
                        </div>
                        <div class="dropdown map-channel-dd">
                            <button class="btn btn-sm dropdown-toggle map-ch-macys" type="button" data-bs-toggle="dropdown" data-channel="macys" aria-expanded="false" title="Macy's filters">
                                <span class="map-ch-label">M</span>
                            </button>
                            <ul class="dropdown-menu">
                                <li><button type="button" class="dropdown-item d-flex justify-content-between" data-filter="mnp" title="Not Mapped"><span>NM</span><span class="fw-bold ms-3" data-count="nm">0</span></button></li>
                                <li><button type="button" class="dropdown-item d-flex justify-content-between" data-filter="msm" title="SKU Mismatch"><span>SM</span><span class="fw-bold ms-3" data-count="sm">0</span></button></li>
                                <li><button type="button" class="dropdown-item d-flex justify-content-between" data-filter="mml" title="Missing Listing"><span>NL</span><span class="fw-bold ms-3" data-count="nl">0</span></button></li>
                            </ul>
                        </div>
                        <div class="dropdown map-channel-dd">
                            <button class="btn btn-sm dropdown-toggle map-ch-bestbuy" type="button" data-bs-toggle="dropdown" data-channel="bestbuy" aria-expanded="false" title="Best Buy filters">
                                <span class="map-ch-label">BB</span>
                            </button>
                            <ul class="dropdown-menu">
                                <li><button type="button" class="dropdown-item d-flex justify-content-between" data-filter="bbnp" title="Not Mapped"><span>NM</span><span class="fw-bold ms-3" data-count="nm">0</span></button></li>
                                <li><button type="button" class="dropdown-item d-flex justify-content-between" data-filter="bbsm" title="SKU Mismatch"><span>SM</span><span class="fw-bold ms-3" data-count="sm">0</span></button></li>
                                <li><button type="button" class="dropdown-item d-flex justify-content-between" data-filter="bbml" title="Missing Listing"><span>NL</span><span class="fw-bold ms-3" data-count="nl">0</span></button></li>
                            </ul>
                        </div>
                        <div class="dropdown map-channel-dd">
                            <button class="btn btn-sm dropdown-toggle map-ch-tiendamia" type="button" data-bs-toggle="dropdown" data-channel="tiendamia" aria-expanded="false" title="Tiendamia filters">
                                <span class="map-ch-label">TDM</span>
                            </button>
                            <ul class="dropdown-menu">
                                <li><button type="button" class="dropdown-item d-flex justify-content-between" data-filter="tnp" title="Not Mapped"><span>NM</span><span class="fw-bold ms-3" data-count="nm">0</span></button></li>
                                <li><button type="button" class="dropdown-item d-flex justify-content-between" data-filter="tsm" title="SKU Mismatch"><span>SM</span><span class="fw-bold ms-3" data-count="sm">0</span></button></li>
                                <li><button type="button" class="dropdown-item d-flex justify-content-between" data-filter="tml" title="Missing Listing"><span>NL</span><span class="fw-bold ms-3" data-count="nl">0</span></button></li>
                            </ul>
                        </div>
                        <div class="dropdown map-channel-dd">
                            <button class="btn btn-sm dropdown-toggle map-ch-temu" type="button" data-bs-toggle="dropdown" data-channel="temu" aria-expanded="false" title="Temu filters">
                                <span class="map-ch-label">TM1</span>
                            </button>
                            <ul class="dropdown-menu">
                                <li><button type="button" class="dropdown-item d-flex justify-content-between" data-filter="tunp" title="Not Mapped"><span>NM</span><span class="fw-bold ms-3" data-count="nm">0</span></button></li>
                                <li><button type="button" class="dropdown-item d-flex justify-content-between" data-filter="tusm" title="SKU Mismatch"><span>SM</span><span class="fw-bold ms-3" data-count="sm">0</span></button></li>
                                <li><button type="button" class="dropdown-item d-flex justify-content-between" data-filter="tuml" title="Missing Listing"><span>NL</span><span class="fw-bold ms-3" data-count="nl">0</span></button></li>
                            </ul>
                        </div>
                        <div class="dropdown map-channel-dd">
                            <button class="btn btn-sm dropdown-toggle map-ch-shein" type="button" data-bs-toggle="dropdown" data-channel="shein" aria-expanded="false" title="Shein filters">
                                <span class="map-ch-label">SHN</span>
                            </button>
                            <ul class="dropdown-menu">
                                <li><button type="button" class="dropdown-item d-flex justify-content-between" data-filter="shnp" title="Not Mapped"><span>NM</span><span class="fw-bold ms-3" data-count="nm">0</span></button></li>
                                <li><button type="button" class="dropdown-item d-flex justify-content-between" data-filter="shsm" title="SKU Mismatch"><span>SM</span><span class="fw-bold ms-3" data-count="sm">0</span></button></li>
                                <li><button type="button" class="dropdown-item d-flex justify-content-between" data-filter="shml" title="Missing Listing"><span>NL</span><span class="fw-bold ms-3" data-count="nl">0</span></button></li>
                            </ul>
                        </div>
                        <div class="dropdown map-channel-dd">
                            <button class="btn btn-sm dropdown-toggle map-ch-newegg" type="button" data-bs-toggle="dropdown" data-channel="newegg" aria-expanded="false" title="Newegg filters">
                                <span class="map-ch-label">NE</span>
                            </button>
                            <ul class="dropdown-menu">
                                <li><button type="button" class="dropdown-item d-flex justify-content-between" data-filter="nenp" title="Not Mapped"><span>NM</span><span class="fw-bold ms-3" data-count="nm">0</span></button></li>
                                <li><button type="button" class="dropdown-item d-flex justify-content-between" data-filter="nesm" title="SKU Mismatch"><span>SM</span><span class="fw-bold ms-3" data-count="sm">0</span></button></li>
                                <li><button type="button" class="dropdown-item d-flex justify-content-between" data-filter="neml" title="Missing Listing"><span>NL</span><span class="fw-bold ms-3" data-count="nl">0</span></button></li>
                            </ul>
                        </div>
                        <div class="dropdown map-channel-dd">
                            <button class="btn btn-sm dropdown-toggle map-ch-aliexpress" type="button" data-bs-toggle="dropdown" data-channel="aliexpress" aria-expanded="false" title="AliExpress filters">
                                <span class="map-ch-label">ALI</span>
                            </button>
                            <ul class="dropdown-menu">
                                <li><button type="button" class="dropdown-item d-flex justify-content-between" data-filter="alinp" title="Not Mapped"><span>NM</span><span class="fw-bold ms-3" data-count="nm">0</span></button></li>
                                <li><button type="button" class="dropdown-item d-flex justify-content-between" data-filter="alism" title="SKU Mismatch"><span>SM</span><span class="fw-bold ms-3" data-count="sm">0</span></button></li>
                                <li><button type="button" class="dropdown-item d-flex justify-content-between" data-filter="aliml" title="Missing Listing"><span>NL</span><span class="fw-bold ms-3" data-count="nl">0</span></button></li>
                            </ul>
                        </div>
                    </div>
                    <div class="mb-3 d-flex gap-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="req-only-toggle" style="cursor:pointer;">
                            <label class="form-check-label" for="req-only-toggle" style="cursor:pointer;">Show Req only</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="hide-small-diff-toggle" style="cursor:pointer;">
                            <label class="form-check-label" for="hide-small-diff-toggle" style="cursor:pointer;">Hide &le;3% diff rows</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="site-only-toggle" style="cursor:pointer;">
                            <label class="form-check-label" for="site-only-toggle" style="cursor:pointer;">
                                Show SKUs not in Product Master <span id="site-only-count" class="text-danger fw-bold"></span>
                            </label>
                        </div>
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
            var showSiteOnly = false; // "Show SKUs not in Product Master" toggle
            var hideSmallDiff = false; // "Hide ≤3% diff rows" toggle

            var invFieldByMarket = { ebay: 'Ebay Inv', ebay2: 'Ebay2 Inv', ebay3: 'Ebay3 Inv', amazon: 'Amazon Inv', reverb: 'Reverb Inv', macys: 'Macys Inv', bestbuy: 'Bestbuy Inv', tiendamia: 'Tiendamia Inv', temu: 'Temu Inv', shein: 'Shein Inv', newegg: 'Newegg Inv', aliexpress: 'Ali Inv' };
            var nrFieldByMarket  = { ebay: 'ebay_nr_req', ebay2: 'ebay2_nr_req', ebay3: 'ebay3_nr_req', amazon: 'amazon_nr_req', reverb: 'reverb_nr_req', macys: 'macys_nr_req', bestbuy: 'bestbuy_nr_req', tiendamia: 'tiendamia_nr_req', temu: 'temu_nr_req', shein: 'shein_nr_req', newegg: 'newegg_nr_req', aliexpress: 'aliexpress_nr_req' };
            var within3FieldByMarket = { ebay: 'ebay_within3', ebay2: 'ebay2_within3', ebay3: 'ebay3_within3', amazon: 'amazon_within3', reverb: 'reverb_within3', macys: 'macys_within3', bestbuy: 'bestbuy_within3', tiendamia: 'tiendamia_within3', temu: 'temu_within3', shein: 'shein_within3', newegg: 'newegg_within3', aliexpress: 'aliexpress_within3' };

            // NR/REQ column: green "Req", red "Not Req" (anything other than REQ).
            function nrReqFormatter(cell) {
                var v = cell.getValue();
                if (v && v !== 'REQ') return '<span style="color:#a00211;font-weight:600;">Not Req</span>';
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

            // Sort the Diff column by the active marketplace's (INV - Inv) value.
            function diffSorter(a, b, aRow, bRow) {
                function d(row) {
                    if (!activeMarket) return 0;
                    var x = row.getData();
                    return (parseFloat(x['INV']) || 0) - (parseFloat(x[invFieldByMarket[activeMarket]]) || 0);
                }
                return d(aRow) - d(bRow);
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

            // Per-channel filter keys + API count fields (one dropdown per channel).
            var channelFilters = {
                ebay: {
                    code: 'E',
                    filters: {
                        enp: { field: 'is_not_map', countKey: 'not_map_count', type: 'NM' },
                        esm: { field: 'has_issue', countKey: 'mismatch_count', type: 'SM' },
                        eml: { field: 'missing_listing', countKey: 'missing_listing_count', type: 'NL' },
                    },
                },
                ebay2: {
                    code: 'E2',
                    filters: {
                        e2np: { field: 'ebay2_not_map', countKey: 'ebay2_not_map_count', type: 'NM' },
                        e2sm: { field: 'ebay2_mismatch', countKey: 'ebay2_mismatch_count', type: 'SM' },
                        e2ml: { field: 'ebay2_missing_listing', countKey: 'ebay2_missing_listing_count', type: 'NL' },
                    },
                },
                ebay3: {
                    code: 'E3',
                    filters: {
                        e3np: { field: 'ebay3_not_map', countKey: 'ebay3_not_map_count', type: 'NM' },
                        e3sm: { field: 'ebay3_mismatch', countKey: 'ebay3_mismatch_count', type: 'SM' },
                        e3ml: { field: 'ebay3_missing_listing', countKey: 'ebay3_missing_listing_count', type: 'NL' },
                    },
                },
                amazon: {
                    code: 'Amz',
                    filters: {
                        anp: { field: 'amazon_not_map', countKey: 'amazon_not_map_count', type: 'NM' },
                        asm: { field: 'amazon_mismatch', countKey: 'amazon_mismatch_count', type: 'SM' },
                        aml: { field: 'amazon_missing_listing', countKey: 'amazon_missing_listing_count', type: 'NL' },
                    },
                },
                reverb: {
                    code: 'R',
                    filters: {
                        rnp: { field: 'reverb_not_map', countKey: 'reverb_not_map_count', type: 'NM' },
                        rsm: { field: 'reverb_mismatch', countKey: 'reverb_mismatch_count', type: 'SM' },
                        rml: { field: 'reverb_missing_listing', countKey: 'reverb_missing_listing_count', type: 'NL' },
                    },
                },
                macys: {
                    code: 'M',
                    filters: {
                        mnp: { field: 'macys_not_map', countKey: 'macys_not_map_count', type: 'NM' },
                        msm: { field: 'macys_mismatch', countKey: 'macys_mismatch_count', type: 'SM' },
                        mml: { field: 'macys_missing_listing', countKey: 'macys_missing_listing_count', type: 'NL' },
                    },
                },
                bestbuy: {
                    code: 'BB',
                    filters: {
                        bbnp: { field: 'bestbuy_not_map', countKey: 'bestbuy_not_map_count', type: 'NM' },
                        bbsm: { field: 'bestbuy_mismatch', countKey: 'bestbuy_mismatch_count', type: 'SM' },
                        bbml: { field: 'bestbuy_missing_listing', countKey: 'bestbuy_missing_listing_count', type: 'NL' },
                    },
                },
                tiendamia: {
                    code: 'TDM',
                    filters: {
                        tnp: { field: 'tiendamia_not_map', countKey: 'tiendamia_not_map_count', type: 'NM' },
                        tsm: { field: 'tiendamia_mismatch', countKey: 'tiendamia_mismatch_count', type: 'SM' },
                        tml: { field: 'tiendamia_missing_listing', countKey: 'tiendamia_missing_listing_count', type: 'NL' },
                    },
                },
                temu: {
                    code: 'TM1',
                    filters: {
                        tunp: { field: 'temu_not_map', countKey: 'temu_not_map_count', type: 'NM' },
                        tusm: { field: 'temu_mismatch', countKey: 'temu_mismatch_count', type: 'SM' },
                        tuml: { field: 'temu_missing_listing', countKey: 'temu_missing_listing_count', type: 'NL' },
                    },
                },
                shein: {
                    code: 'SHN',
                    filters: {
                        shnp: { field: 'shein_not_map', countKey: 'shein_not_map_count', type: 'NM' },
                        shsm: { field: 'shein_mismatch', countKey: 'shein_mismatch_count', type: 'SM' },
                        shml: { field: 'shein_missing_listing', countKey: 'shein_missing_listing_count', type: 'NL' },
                    },
                },
                newegg: {
                    code: 'NE',
                    filters: {
                        nenp: { field: 'newegg_not_map', countKey: 'newegg_not_map_count', type: 'NM' },
                        nesm: { field: 'newegg_mismatch', countKey: 'newegg_mismatch_count', type: 'SM' },
                        neml: { field: 'newegg_missing_listing', countKey: 'newegg_missing_listing_count', type: 'NL' },
                    },
                },
                aliexpress: {
                    code: 'ALI',
                    filters: {
                        alinp: { field: 'aliexpress_not_map', countKey: 'aliexpress_not_map_count', type: 'NM' },
                        alism: { field: 'aliexpress_mismatch', countKey: 'aliexpress_mismatch_count', type: 'SM' },
                        aliml: { field: 'aliexpress_missing_listing', countKey: 'aliexpress_missing_listing_count', type: 'NL' },
                    },
                },
            };

            var filterMeta = {};
            Object.keys(channelFilters).forEach(function (market) {
                var ch = channelFilters[market];
                Object.keys(ch.filters).forEach(function (fkey) {
                    filterMeta[fkey] = Object.assign({ market: market, code: ch.code }, ch.filters[fkey]);
                });
            });

            function updateChannelCounts(response) {
                document.querySelectorAll('#map-channel-filters .map-channel-dd').forEach(function (dd) {
                    var btn = dd.querySelector('[data-channel]');
                    var market = btn.getAttribute('data-channel');
                    var ch = channelFilters[market];
                    if (!ch) return;
                    Object.keys(ch.filters).forEach(function (fkey) {
                        var meta = ch.filters[fkey];
                        var n = response[meta.countKey] || 0;
                        var el = dd.querySelector('[data-filter="' + fkey + '"] [data-count]');
                        if (el) el.textContent = n.toLocaleString();
                    });
                    var label = dd.querySelector('.map-ch-label');
                    if (label) label.textContent = ch.code;
                });
            }

            var table = new Tabulator('#map-issues-table', {
                layout: 'fitColumns',
                placeholder: 'No Data Available',
                ajaxURL: "{{ route('map.issues.data') }}",
                ajaxResponse: function (url, params, response) {
                    updateChannelCounts(response || {});
                    document.getElementById('site-only-count').textContent =
                        response.pm_missing_count ? '(' + response.pm_missing_count.toLocaleString() + ')' : '';
                    return response.data || [];
                },
                pagination: true,
                paginationMode: 'local',
                paginationSize: 50,
                paginationSizeSelector: [25, 50, 100, 250],
                columns: [
                    {
                        title: '(Child) SKU', field: '(Child) sku', headerFilter: 'input', widthGrow: 2,
                        formatter: function (cell) {
                            var v = cell.getValue();
                            if (cell.getRow().getData().pm_missing) {
                                return '<span style="color:#a00211;font-weight:600;">' + v +
                                    ' <small>(not in PM)</small></span>';
                            }
                            return v;
                        },
                    },
                    { title: 'Listed On', field: 'listed_on', visible: false, widthGrow: 1 },
                    { title: 'Marketplace SKU', field: 'mp_sku', visible: false, widthGrow: 2, formatter: marketSkuFormatter },
                    { title: 'NR/REQ', field: 'ebay_nr_req', visible: false, editor: 'list', editorParams: { values: { REQ: 'Req', NRL: 'Not Req' } }, formatter: nrReqFormatter, cellEdited: nrEdited('ebay') },
                    { title: 'NR/REQ', field: 'ebay2_nr_req', visible: false, editor: 'list', editorParams: { values: { REQ: 'Req', NR: 'Not Req' } }, formatter: nrReqFormatter, cellEdited: nrEdited('ebay2') },
                    { title: 'NR/REQ', field: 'ebay3_nr_req', visible: false, editor: 'list', editorParams: { values: { REQ: 'Req', NR: 'Not Req' } }, formatter: nrReqFormatter, cellEdited: nrEdited('ebay3') },
                    { title: 'NR/REQ', field: 'amazon_nr_req', visible: false, editor: 'list', editorParams: { values: { REQ: 'Req', NRL: 'Not Req' } }, formatter: nrReqFormatter, cellEdited: nrEdited('amazon') },
                    { title: 'NR/REQ', field: 'reverb_nr_req', visible: false, editor: 'list', editorParams: { values: { REQ: 'Req', NR: 'Not Req' } }, formatter: nrReqFormatter, cellEdited: nrEdited('reverb') },
                    { title: 'NR/REQ', field: 'macys_nr_req', visible: false, editor: 'list', editorParams: { values: { REQ: 'Req', NR: 'Not Req' } }, formatter: nrReqFormatter, cellEdited: nrEdited('macys') },
                    { title: 'NR/REQ', field: 'bestbuy_nr_req', visible: false, editor: 'list', editorParams: { values: { REQ: 'Req', NR: 'Not Req' } }, formatter: nrReqFormatter, cellEdited: nrEdited('bestbuy') },
                    { title: 'NR/REQ', field: 'tiendamia_nr_req', visible: false, editor: 'list', editorParams: { values: { REQ: 'Req', NR: 'Not Req' } }, formatter: nrReqFormatter, cellEdited: nrEdited('tiendamia') },
                    { title: 'NR/REQ', field: 'temu_nr_req', visible: false, editor: 'list', editorParams: { values: { REQ: 'Req', NR: 'Not Req' } }, formatter: nrReqFormatter, cellEdited: nrEdited('temu') },
                    { title: 'NR/REQ', field: 'shein_nr_req', visible: false, formatter: nrReqFormatter },
                    { title: 'NR/REQ', field: 'newegg_nr_req', visible: false, editor: 'list', editorParams: { values: { REQ: 'Req', NR: 'Not Req' } }, formatter: nrReqFormatter, cellEdited: nrEdited('newegg') },
                    { title: 'NR/REQ', field: 'aliexpress_nr_req', visible: false, editor: 'list', editorParams: { values: { REQ: 'Req', NR: 'Not Req' } }, formatter: nrReqFormatter, cellEdited: nrEdited('aliexpress') },
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
                    {
                        title: 'Amazon Inv', field: 'Amazon Inv', hozAlign: 'right', sorter: 'number',
                        formatter: invFormatter('amazon_mismatch'),
                        cellClick: function (e, cell) {
                            if (e.target.classList.contains('map-info-icon')) {
                                var d = cell.getRow().getData();
                                showIssueModal('Amazon', d['(Child) sku'], d.amazon_sku, d.amazon_issue);
                            }
                        },
                    },
                    {
                        title: 'Reverb Inv', field: 'Reverb Inv', hozAlign: 'right', sorter: 'number',
                        formatter: invFormatter('reverb_mismatch'),
                        cellClick: function (e, cell) {
                            if (e.target.classList.contains('map-info-icon')) {
                                var d = cell.getRow().getData();
                                showIssueModal('Reverb', d['(Child) sku'], d.reverb_sku, d.reverb_issue);
                            }
                        },
                    },
                    {
                        title: 'Macys Inv', field: 'Macys Inv', hozAlign: 'right', sorter: 'number',
                        formatter: invFormatter('macys_mismatch'),
                        cellClick: function (e, cell) {
                            if (e.target.classList.contains('map-info-icon')) {
                                var d = cell.getRow().getData();
                                showIssueModal("Macy's", d['(Child) sku'], d.macys_sku, d.macys_issue);
                            }
                        },
                    },
                    {
                        title: 'Bestbuy Inv', field: 'Bestbuy Inv', hozAlign: 'right', sorter: 'number',
                        formatter: invFormatter('bestbuy_mismatch'),
                        cellClick: function (e, cell) {
                            if (e.target.classList.contains('map-info-icon')) {
                                var d = cell.getRow().getData();
                                showIssueModal('Best Buy', d['(Child) sku'], d.bestbuy_sku, d.bestbuy_issue);
                            }
                        },
                    },
                    {
                        title: 'Tiendamia Inv', field: 'Tiendamia Inv', hozAlign: 'right', sorter: 'number',
                        formatter: invFormatter('tiendamia_mismatch'),
                        cellClick: function (e, cell) {
                            if (e.target.classList.contains('map-info-icon')) {
                                var d = cell.getRow().getData();
                                showIssueModal('Tiendamia', d['(Child) sku'], d.tiendamia_sku, d.tiendamia_issue);
                            }
                        },
                    },
                    {
                        title: 'Temu Inv', field: 'Temu Inv', hozAlign: 'right', sorter: 'number',
                        formatter: invFormatter('temu_mismatch'),
                        cellClick: function (e, cell) {
                            if (e.target.classList.contains('map-info-icon')) {
                                var d = cell.getRow().getData();
                                showIssueModal('Temu', d['(Child) sku'], d.temu_sku, d.temu_issue);
                            }
                        },
                    },
                    {
                        title: 'Shein Inv', field: 'Shein Inv', hozAlign: 'right', sorter: 'number',
                        formatter: invFormatter('shein_mismatch'),
                        cellClick: function (e, cell) {
                            if (e.target.classList.contains('map-info-icon')) {
                                var d = cell.getRow().getData();
                                showIssueModal('Shein', d['(Child) sku'], d.shein_sku, d.shein_issue);
                            }
                        },
                    },
                    {
                        title: 'Newegg Inv', field: 'Newegg Inv', hozAlign: 'right', sorter: 'number',
                        formatter: invFormatter('newegg_mismatch'),
                        cellClick: function (e, cell) {
                            if (e.target.classList.contains('map-info-icon')) {
                                var d = cell.getRow().getData();
                                showIssueModal('Newegg', d['(Child) sku'], d.newegg_sku, d.newegg_issue);
                            }
                        },
                    },
                    {
                        title: 'Ali Inv', field: 'Ali Inv', hozAlign: 'right', sorter: 'number',
                        formatter: invFormatter('aliexpress_mismatch'),
                        cellClick: function (e, cell) {
                            if (e.target.classList.contains('map-info-icon')) {
                                var d = cell.getRow().getData();
                                showIssueModal('AliExpress', d['(Child) sku'], d.aliexpress_sku, d.aliexpress_issue);
                            }
                        },
                    },
                    { title: 'Diff', field: 'diff', visible: false, hozAlign: 'right', formatter: diffFormatter, sorter: diffSorter },
                ],
            });

            function applyFilters() {
                document.querySelectorAll('#map-channel-filters [data-channel]').forEach(function (btn) {
                    var market = btn.getAttribute('data-channel');
                    var on = !!(activeFilter && filterMeta[activeFilter] && filterMeta[activeFilter].market === market);
                    btn.classList.toggle('map-active', on);
                });
                document.querySelectorAll('#map-channel-filters [data-filter]').forEach(function (item) {
                    item.classList.toggle('active', item.getAttribute('data-filter') === activeFilter);
                });

                if (activeFilter && filterMeta[activeFilter]) {
                    activeMarket = filterMeta[activeFilter].market;
                    table.showColumn('mp_sku');
                    table.showColumn('diff');
                    table.setSort('diff', 'asc');
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
                    table.clearSort();
                    Object.keys(invFieldByMarket).forEach(function (m) {
                        table.showColumn(invFieldByMarket[m]);
                        table.hideColumn(nrFieldByMarket[m]);
                    });
                }

                // "Listed On" column only matters for the site-only view.
                if (showSiteOnly) {
                    table.showColumn('listed_on');
                } else {
                    table.hideColumn('listed_on');
                }

                // Combine the channel filter with the "Req only" filter, or show site-only rows.
                var filters = [];
                if (showSiteOnly) {
                    filters.push({ field: 'pm_missing', type: '=', value: true });
                } else {
                    filters.push({ field: 'pm_missing', type: '!=', value: true });
                    if (activeFilter && filterMeta[activeFilter]) {
                        filters.push({ field: filterMeta[activeFilter].field, type: '=', value: true });
                    }
                    if (reqOnly && activeMarket) {
                        filters.push({ field: nrFieldByMarket[activeMarket], type: '=', value: 'REQ' });
                    }
                    if (hideSmallDiff && activeMarket) {
                        filters.push({ field: within3FieldByMarket[activeMarket], type: '!=', value: true });
                    }
                }
                table.setFilter(filters);
                table.redraw(true);
            }

            // (Re)load data, including site-only SKUs when the toggle is on.
            function loadData() {
                table.setData("{{ route('map.issues.data') }}", { site_only: showSiteOnly ? 1 : 0 })
                    .then(applyFilters);
            }

            document.querySelectorAll('#map-channel-filters [data-filter]').forEach(function (item) {
                item.addEventListener('click', function () {
                    var k = item.getAttribute('data-filter');
                    activeFilter = (activeFilter === k) ? null : k;
                    applyFilters();
                });
            });

            document.getElementById('req-only-toggle').addEventListener('change', function () {
                reqOnly = this.checked;
                applyFilters();
            });

            document.getElementById('hide-small-diff-toggle').addEventListener('change', function () {
                hideSmallDiff = this.checked;
                applyFilters();
            });

            document.getElementById('site-only-toggle').addEventListener('change', function () {
                showSiteOnly = this.checked;
                // Site-only view ignores badge filters.
                activeFilter = null;
                loadData();
            });
        });
    </script>
@endsection
