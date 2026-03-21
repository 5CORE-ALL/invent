@extends('layouts.vertical', ['title' => 'Loss/Gain', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
<meta name="csrf-token" content="{{ csrf_token() }}">

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .table-container {
            overflow-x: auto;
        }
        .table-bordered {
            border: 1px solid #dee2e6;
        }
        .table-bordered th,
        .table-bordered td {
            border: 1px solid #dee2e6;
        }
        .badge {
            display: inline-block;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
        }
        .bg-primary {
            background-color: #0d6efd !important;
            color: #fff;
        }
        .loss-gain-column {
            text-align: center !important;
        }
        .sortable-th {
            cursor: pointer;
            user-select: none;
        }
        .sortable-th:hover {
            background-color: #f0f0f0;
        }
        .sort-arrow {
            margin-left: 5px;
            font-size: 0.8em;
        }
        .badge.badge-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            line-height: 1.5;
        }
        .bg-danger {
            background-color: #dc3545 !important;
            color: #fff;
        }
        .text-danger {
            color: #dc3545 !important;
        }
        @media (max-width: 1200px) {
            .table-container {
                font-size: 0.875rem;
            }
            .table th, .table td {
                padding: 0.5rem;
            }
        }
        /* Sticky page header: title + filters stay visible when scrolling (below topbar) */
        .lost-gain-sticky-header {
            position: sticky;
            top: var(--tz-topbar-height, 70px);
            z-index: 102;
            background-color: #f3f6f9 !important;
            padding-bottom: 0.5rem;
            margin-bottom: 0;
            box-shadow: 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        .lost-gain-sticky-header .card,
        .lost-gain-sticky-header .card-body,
        .lost-gain-sticky-header .page-title-box {
            background-color: #f3f6f9 !important;
        }
        /* Single-row filter toolbar */
        .lost-gain-toolbar {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            gap: 0.4rem;
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 1px;
        }
        .lost-gain-toolbar .form-select-sm,
        .lost-gain-toolbar .form-control-sm {
            font-size: 0.8125rem;
        }
        .lost-gain-toolbar-search {
            flex: 1 1 8rem;
            min-width: 7rem;
            max-width: 18rem;
        }
        /* Loss/Gain total: button, 1.5× prior badge size */
        button.lost-gain-sum-badge.btn {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            font-size: 1.828125rem;
            font-weight: 600;
            padding: 0.675rem 1.2375rem;
            line-height: 1.35;
            vertical-align: middle;
            border-radius: 0.5625rem;
            border: none;
            box-shadow: none;
        }
        button.lost-gain-sum-badge.btn:focus {
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.35);
        }
        button.lost-gain-sum-badge.btn.btn-danger:focus {
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.35);
        }
        .lost-gain-sum-badge .fa-dollar-sign {
            font-size: 0.85em;
            opacity: 0.95;
        }
        @media (max-width: 991.98px) {
            .lost-gain-toolbar {
                flex-wrap: wrap;
                row-gap: 0.35rem;
            }
            .lost-gain-toolbar-search {
                flex: 1 1 100%;
                max-width: none;
            }
        }
        /* Fixed-height scroll area so thead position:sticky activates when scrolling rows */
        .lost-gain-table-wrapper {
            height: max(240px, calc(100vh - var(--tz-topbar-height, 70px) - 230px));
            max-height: max(240px, calc(100vh - var(--tz-topbar-height, 70px) - 230px));
            overflow: auto;
            -webkit-overflow-scrolling: touch;
        }
        .lost-gain-table-wrapper .table {
            margin-bottom: 0;
        }
        .lost-gain-table-wrapper .table thead th {
            position: sticky;
            z-index: 100;
            background-color: #fff3cd !important;
            background-clip: padding-box;
            border-bottom: 1px solid #e6d491;
            box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.08);
        }
        .lost-gain-table-wrapper .table thead th.sortable-th:hover {
            background-color: #ffe69c !important;
        }
        .lost-gain-table-wrapper .table thead tr:first-child th {
            top: 0;
        }
        /* Keep tbody below sticky header in stacking order */
        .lost-gain-table-wrapper .table tbody {
            position: relative;
            z-index: 0;
        }
        #lostGainTable thead th,
        #lostGainTable tbody td {
            text-align: center !important;
            vertical-align: middle;
        }
        #historyBadgeBtn:focus-visible {
            outline: 2px solid rgba(13, 110, 253, 0.5);
            outline-offset: 2px;
        }
        .lost-gain-history-badge {
            min-width: 1.85rem;
            min-height: 1.85rem;
            padding: 0.35em 0.45em;
        }
        .lost-gain-history-badge .fa-history {
            font-size: 0.9rem;
            line-height: 1;
        }
    </style>
@endsection

@section('content')
    @push('page-title-after')
        <div class="flex-shrink-0 align-self-center" title="Sum of Loss/Gain for rows in view (excludes I &amp; A from dataset total; search filters apply)">
            <button type="button" class="btn btn-primary lost-gain-sum-badge" id="lostGainBadge" tabindex="-1" aria-live="polite" aria-label="Loss/Gain sum">
                <i class="fas fa-dollar-sign" aria-hidden="true"></i>
                <span id="lostGainTotal">0</span>
            </button>
        </div>
    @endpush
    <div class="lost-gain-sticky-header">
        @include('layouts.shared/page-title', ['page_title' => 'Lost Gain', 'sub_title' => 'Loss/Gain'])
        <div class="row">
            <div class="col-12">
                <div class="card mb-0">
                    <div class="card-body py-2 px-3">
                        <div class="lost-gain-toolbar" role="toolbar" aria-label="Loss/Gain filters">
                            <div class="flex-shrink-0">
                                <label for="reasonFilter" class="visually-hidden">Reason</label>
                                <select id="reasonFilter" class="form-select form-select-sm" style="width: 9.75rem; min-width: 7rem;" title="Reason">
                                    <option value="">All Reasons</option>
                                    <option value="Count">Count</option>
                                    <option value="Received">Received</option>
                                    <option value="Return Restock">Return Restock</option>
                                    <option value="Damaged">Damaged</option>
                                    <option value="Theft or Loss">Theft or Loss</option>
                                    <option value="Promotion">Promotion</option>
                                    <option value="Suspense">Suspense</option>
                                    <option value="Unknown">Unknown</option>
                                    <option value="Adjustment">Adjustment</option>
                                    <option value="Combo">Combo</option>
                                    <option value="Maybe FBA">Maybe FBA</option>
                                    <option value="Need 2 Find">Need 2 Find</option>
                                </select>
                            </div>
                            <div class="flex-shrink-0">
                                <label for="approvedByFilter" class="visually-hidden">Approved By</label>
                                <select id="approvedByFilter" class="form-select form-select-sm" style="width: 9rem; min-width: 6.5rem;" title="Approved By">
                                    <option value="">All Users</option>
                                </select>
                            </div>
                            <div class="flex-shrink-0">
                                <label for="dateFromFilter" class="visually-hidden">Date From</label>
                                <input type="date" id="dateFromFilter" class="form-control form-control-sm" style="width: 9.25rem; min-width: 8.5rem;" title="Date From">
                            </div>
                            <div class="flex-shrink-0">
                                <label for="dateToFilter" class="visually-hidden">Date To</label>
                                <input type="date" id="dateToFilter" class="form-control form-control-sm" style="width: 9.25rem; min-width: 8.5rem;" title="Date To">
                            </div>
                            <div class="lost-gain-toolbar-search">
                                <label for="lostGainSearch" class="visually-hidden">Search all columns</label>
                                <input type="text" id="lostGainSearch" class="form-control form-control-sm w-100" placeholder="Search all columns">
                            </div>
                            <div class="flex-shrink-0">
                                <label for="iaStatusFilter" class="visually-hidden">I&amp;A status</label>
                                <select id="iaStatusFilter" class="form-select form-select-sm" style="width: 9.25rem; min-width: 8.25rem;" title="Filter by I&amp;A status (counts exclude hidden rows)">
                                    <option value="all">ALL (0)</option>
                                    <option value="ia">I &amp; A (0)</option>
                                    <option value="pending" selected>Pending (0)</option>
                                </select>
                            </div>
                            <div class="flex-shrink-0">
                                <button type="button" id="bulkIABtn" class="btn btn-dark btn-sm text-nowrap" disabled>
                                    <i class="fas fa-archive"></i> Mark I &amp; A
                                </button>
                            </div>
                            <div class="flex-shrink-0">
                                <button type="button" class="btn btn-dark btn-sm text-nowrap" id="adjustedToolbarBadge" tabindex="-1" aria-live="polite" aria-label="Adjusted quantity sum" title="Sum of Adjusted Qty for rows in view (I&amp;A filter and search apply)">
                                    <i class="fas fa-sliders-h" aria-hidden="true"></i> Adjusted Qty: <span id="adjustedToolbarTotal">0</span>
                                </button>
                            </div>
                            <div class="flex-shrink-0">
                                <button type="button" id="historyBadgeBtn" class="btn btn-sm p-0 border-0 bg-transparent lh-1" title="Show or hide Adjust Quantity history (latest first)" aria-label="Show or hide Adjust Quantity history" aria-expanded="false" aria-controls="aqHistoryPanel">
                                    <span class="badge rounded-pill bg-secondary d-inline-flex align-items-center justify-content-center lost-gain-history-badge" id="historyBadgeLabel">
                                        <i class="fas fa-history" aria-hidden="true"></i>
                                    </span>
                                </button>
                            </div>
                        </div>
                        <div class="d-none" aria-hidden="true">
                            <span class="badge bg-secondary badge-sm">I&amp;A Total: <span id="iaTotal">0</span></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-container lost-gain-table-wrapper">
                        <table class="table table-bordered text-center" id="lostGainTable">
                            <thead>
                                <tr>
                                    <th>
                                        <input type="checkbox" id="selectAllCheckbox" title="Select All">
                                    </th>
                                    <th class="sortable-th" data-sort="parent" data-sort-type="text">
                                        Parent <span class="sort-arrow"></span>
                                    </th>
                                    <th class="sortable-th" data-sort="sku" data-sort-type="text">
                                        SKU <span class="sort-arrow"></span>
                                    </th>
                                    <th class="sortable-th text-center" data-sort="verified_stock" data-sort-type="number">
                                        Verified <span class="sort-arrow"></span>
                                    </th>
                                    <th class="sortable-th text-center" data-sort="to_adjust" data-sort-type="number">
                                        Adjusted <span class="sort-arrow"></span>
                                    </th>
                                    <th class="sortable-th" data-sort="unit" data-sort-type="text">
                                        Unit <span class="sort-arrow"></span>
                                    </th>
                                    <th class="loss-gain-column sortable-th" data-sort="loss_gain" data-sort-type="number">
                                        Loss/Gain <span class="sort-arrow">↓</span>
                                    </th>
                                    <th class="sortable-th" data-sort="reason" data-sort-type="text">
                                        Reason <span class="sort-arrow"></span>
                                    </th>
                                    <th class="sortable-th" data-sort="approved_by" data-sort-type="text">
                                        Appr By <span class="sort-arrow"></span>
                                    </th>
                                    <th class="sortable-th" data-sort="approved_at" data-sort-type="date">
                                        Approved <span class="sort-arrow"></span>
                                    </th>
                                    <th class="sortable-th" data-sort="remarks" data-sort-type="text">
                                        Remarks <span class="sort-arrow"></span>
                                    </th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Will be populated via JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3 d-none" id="aqHistoryPanel" role="region" aria-label="AQ and AV adjustment history">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                        <h5 class="card-title mb-0">Adjustment history (AQ &amp; AV)</h5>
                        <span class="text-muted small" id="aqHistoryCountWrap"><span id="aqHistoryCount">—</span></span>
                    </div>
                    <div class="mb-2">
                        <label for="aqHistorySearchAll" class="visually-hidden">Search history</label>
                        <input type="text" id="aqHistorySearchAll" class="form-control form-control-sm" style="max-width: 22rem;" placeholder="Search all columns" autocomplete="off">
                    </div>
                    <div class="mb-2">
                        <button type="button" class="btn btn-link btn-sm p-0 small" id="aqHistoryToggleColFilters" aria-expanded="false">Column filters</button>
                    </div>
                    <div id="aqHistoryColFiltersRow" class="row g-2 mb-3 d-none">
                        <div class="col-6 col-md">
                            <label class="visually-hidden" for="aqHistColKind">Filter Type</label>
                            <input type="text" id="aqHistColKind" class="form-control form-control-sm aq-hist-col-filter" placeholder="Type (AQ/AV)" autocomplete="off">
                        </div>
                        <div class="col-6 col-md">
                            <label class="visually-hidden" for="aqHistColWhen">Filter When</label>
                            <input type="text" id="aqHistColWhen" class="form-control form-control-sm aq-hist-col-filter" placeholder="When (ET)" autocomplete="off">
                        </div>
                        <div class="col-6 col-md">
                            <label class="visually-hidden" for="aqHistColUser">Filter User</label>
                            <input type="text" id="aqHistColUser" class="form-control form-control-sm aq-hist-col-filter" placeholder="User" autocomplete="off">
                        </div>
                        <div class="col-6 col-md">
                            <label class="visually-hidden" for="aqHistColSku">Filter SKU</label>
                            <input type="text" id="aqHistColSku" class="form-control form-control-sm aq-hist-col-filter" placeholder="SKU" autocomplete="off">
                        </div>
                        <div class="col-6 col-md">
                            <label class="visually-hidden" for="aqHistColAdj">Filter Adjusted Qty</label>
                            <input type="text" id="aqHistColAdj" class="form-control form-control-sm aq-hist-col-filter" placeholder="Adjusted Qty" autocomplete="off">
                        </div>
                        <div class="col-6 col-md">
                            <label class="visually-hidden" for="aqHistColLg">Filter Loss/Gain</label>
                            <input type="text" id="aqHistColLg" class="form-control form-control-sm aq-hist-col-filter" placeholder="Loss/Gain" autocomplete="off">
                        </div>
                    </div>
                    <div class="table-container" style="max-height: min(60vh, 520px); overflow: auto;">
                        <table class="table table-bordered table-sm text-center mb-0" id="aqHistoryTable">
                            <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                                <tr>
                                    <th>Type</th>
                                    <th>When (ET)</th>
                                    <th>User</th>
                                    <th>SKU</th>
                                    <th>Adjusted Qty</th>
                                    <th>Loss/Gain</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="6" class="text-center text-muted">Open History to load.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            let tableRows = [];
            /** Cached AQ history rows (API returns newest first). */
            let aqHistoryData = [];
            let aqHistoryStale = true;
            let aqHistoryLoading = false;
            /** Active column sort: direction -1 = desc (high→low / Z→A / newest first), 1 = asc */
            let columnSort = { field: 'loss_gain', direction: -1 };
            /** I&A row view: all | ia (I&A only) | pending (not marked) */
            let iaFilterMode = 'pending';

            /**
             * I&A is stored per SKU. Any row with numeric Adjusted === 0 triggers auto I&A for that SKU
             * (same as manually marking one row — all rows for the SKU share the flag).
             */
            function autoMarkZeroAdjustAsIA(done) {
                const skusToMarkSet = new Set();
                tableRows.forEach(row => {
                    const adj = parseFloat(row.to_adjust);
                    if (Number.isFinite(adj) && adj === 0 && !row.isIA) {
                        skusToMarkSet.add(row.sku);
                    }
                });
                const skusToMark = Array.from(skusToMarkSet);
                if (skusToMark.length === 0) {
                    done();
                    return;
                }
                $.ajax({
                    url: '/lost-gain-update-ia',
                    method: 'POST',
                    data: {
                        skus: skusToMark,
                        is_ia: true,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(res) {
                        if (res.success) {
                            const marked = new Set(skusToMark);
                            tableRows.forEach(row => {
                                if (marked.has(row.sku)) {
                                    row.isIA = true;
                                }
                            });
                        }
                        done();
                    },
                    error: function() {
                        console.warn('Auto I&A (zero Adjusted) could not be saved');
                        done();
                    }
                });
            }

            function finishLostGainTableInit() {
                columnSort = { field: 'loss_gain', direction: -1 };
                tableRows.sort((a, b) => (b.loss_gain - a.loss_gain));
                autoMarkZeroAdjustAsIA(function() {
                    renderTableRows(tableRows);
                    updateTotals();
                    initSort();
                    applyFilters();
                });
            }

            /** Row counts for I&A filter labels (rows hidden after AQ are excluded). */
            function updateIaStatusFilterCounts() {
                const rows = tableRows.filter(r => !r.aqHidden);
                const allCnt = rows.length;
                const iaCnt = rows.filter(r => r.isIA).length;
                const pendCnt = rows.filter(r => !r.isIA).length;
                const $sel = $('#iaStatusFilter');
                $sel.find('option[value="all"]').text('ALL (' + allCnt + ')');
                $sel.find('option[value="ia"]').text('I & A (' + iaCnt + ')');
                $sel.find('option[value="pending"]').text('Pending (' + pendCnt + ')');
            }

            function derivedLossGain(oldTa, oldLg, newTa, lp) {
                const o = parseFloat(oldTa) || 0;
                const lg = parseFloat(oldLg) || 0;
                const lpVal = parseFloat(lp) || 0;
                if (Math.abs(o) > 1e-9) {
                    return Math.round(lg * (newTa / o) * 100) / 100;
                }
                return lpVal ? Math.round(newTa * lpVal * 100) / 100 : 0;
            }

            /** After AV: derive new Adjusted Qty from old pair and new Loss/Gain. */
            function derivedToAdjustFromLossGain(oldTa, oldLg, newLg, lp) {
                const ota = parseFloat(oldTa) || 0;
                const olg = parseFloat(oldLg) || 0;
                const nlg = parseFloat(newLg) || 0;
                const lpVal = parseFloat(lp) || 0;
                if (Math.abs(olg) > 1e-6) {
                    return Math.round(ota * (nlg / olg));
                }
                if (lpVal > 1e-6) {
                    return Math.round(nlg / lpVal);
                }
                return Math.round(ota);
            }

            function lgMoneyToCents(x) {
                return Math.round((parseFloat(x) || 0) * 100);
            }

            function lgCentsToMoney(c) {
                return Math.round(c) / 100;
            }

            function formatLossGainCell(lossGainValue) {
                const n = parseFloat(lossGainValue);
                if (!Number.isFinite(n) || n === 0) {
                    return '-';
                }
                const t = Math.trunc(n);
                if (t < 0) {
                    return '-$' + Math.abs(t);
                }
                return '$' + t;
            }

            function lostGainEscapeAttr(s) {
                return String(s ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/"/g, '&quot;')
                    .replace(/</g, '&lt;');
            }

            /**
             * Net positive to_adjust in the selection against negatives, capped by total positive.
             * Negatives are filled most-negative first; positives are reduced in row-index order.
             */
            function buildAqPlan(indicesUnique) {
                for (let k = 0; k < indicesUnique.length; k++) {
                    const i = indicesUnique[k];
                    const r = tableRows[i];
                    if (!r) {
                        continue;
                    }
                    const ta = parseFloat(r.to_adjust) || 0;
                    if (ta !== 0 && !r.inventory_id) {
                        return { error: 'A selected row is missing inventory id. Reload the page and try again.' };
                    }
                }
                const entries = indicesUnique.map(i => ({ index: i, row: tableRows[i] }))
                    .filter(e => e.row && e.row.inventory_id);
                if (entries.length === 0) {
                    return { error: 'Selected rows are missing inventory ids. Reload the page and try again.' };
                }
                const pos = entries.filter(e => (parseFloat(e.row.to_adjust) || 0) > 0)
                    .sort((a, b) => a.index - b.index);
                const neg = entries.filter(e => (parseFloat(e.row.to_adjust) || 0) < 0);
                if (neg.length === 0) {
                    return { error: 'Include at least one row with negative Adjusted Qty.' };
                }
                if (pos.length === 0) {
                    return { error: 'Include at least one row with positive Adjusted Qty to offset negatives.' };
                }
                let pool = pos.reduce((s, e) => s + (parseFloat(e.row.to_adjust) || 0), 0);
                if (pool <= 0) {
                    return { error: 'No positive Adjusted Qty available in the selection.' };
                }
                const initialPool = pool;
                const newVals = {};
                indicesUnique.forEach(i => {
                    const r = tableRows[i];
                    if (r) {
                        newVals[i] = parseFloat(r.to_adjust) || 0;
                    }
                });
                const negSorted = [...neg].sort((a, b) =>
                    (parseFloat(a.row.to_adjust) || 0) - (parseFloat(b.row.to_adjust) || 0)
                );
                for (const e of negSorted) {
                    let cur = newVals[e.index];
                    if (cur >= 0) {
                        continue;
                    }
                    const need = -cur;
                    const apply = Math.min(need, pool);
                    newVals[e.index] = cur + apply;
                    pool -= apply;
                }
                let deduct = initialPool - pool;
                for (const e of pos) {
                    let cur = newVals[e.index];
                    const take = Math.min(Math.max(0, cur), deduct);
                    newVals[e.index] = cur - take;
                    deduct -= take;
                }
                const updates = [];
                for (const i of indicesUnique) {
                    if (newVals[i] === undefined) {
                        continue;
                    }
                    const row = tableRows[i];
                    const oldTa = parseFloat(row.to_adjust) || 0;
                    const newTa = Math.round(newVals[i]);
                    if (oldTa === newTa) {
                        continue;
                    }
                    const oldLg = parseFloat(row.loss_gain) || 0;
                    const newLg = derivedLossGain(oldTa, oldLg, newTa, row.lp);
                    updates.push({
                        inventory_id: row.inventory_id,
                        to_adjust: newTa,
                        loss_gain: newLg,
                        rowIndex: i,
                        oldTa,
                        newTa,
                        newLg,
                    });
                }
                if (updates.length === 0) {
                    return { error: 'Nothing to change for the current selection.' };
                }
                return { updates };
            }

            /**
             * Net positive loss_gain (dollar value) against negatives, capped by total positive value.
             * Same allocation order as AQ: most-negative Loss/Gain first, then reduce positives in row order.
             * Updates to_adjust from the new loss_gain using lp / proportionality.
             */
            function buildAvPlan(indicesUnique) {
                for (let k = 0; k < indicesUnique.length; k++) {
                    const i = indicesUnique[k];
                    const r = tableRows[i];
                    if (!r) {
                        continue;
                    }
                    const lg = parseFloat(r.loss_gain) || 0;
                    if (Math.abs(lg) > 1e-9 && !r.inventory_id) {
                        return { error: 'A selected row is missing inventory id. Reload the page and try again.' };
                    }
                }
                const entries = indicesUnique.map(i => ({ index: i, row: tableRows[i] }))
                    .filter(e => e.row && e.row.inventory_id);
                if (entries.length === 0) {
                    return { error: 'Selected rows are missing inventory ids. Reload the page and try again.' };
                }
                const pos = entries.filter(e => (parseFloat(e.row.loss_gain) || 0) > 0)
                    .sort((a, b) => a.index - b.index);
                const neg = entries.filter(e => (parseFloat(e.row.loss_gain) || 0) < 0);
                if (neg.length === 0) {
                    return { error: 'Include at least one row with negative Loss/Gain.' };
                }
                if (pos.length === 0) {
                    return { error: 'Include at least one row with positive Loss/Gain to offset negatives.' };
                }
                let pool = pos.reduce((s, e) => s + lgMoneyToCents(e.row.loss_gain), 0);
                if (pool <= 0) {
                    return { error: 'No positive Loss/Gain value available in the selection.' };
                }
                const initialPool = pool;
                const newCents = {};
                indicesUnique.forEach(i => {
                    const r = tableRows[i];
                    if (r) {
                        newCents[i] = lgMoneyToCents(r.loss_gain);
                    }
                });
                const negSorted = [...neg].sort((a, b) =>
                    lgMoneyToCents(a.row.loss_gain) - lgMoneyToCents(b.row.loss_gain)
                );
                for (const e of negSorted) {
                    let cur = newCents[e.index];
                    if (cur >= 0) {
                        continue;
                    }
                    const need = -cur;
                    const apply = Math.min(need, pool);
                    newCents[e.index] = cur + apply;
                    pool -= apply;
                }
                let deduct = initialPool - pool;
                for (const e of pos) {
                    let cur = newCents[e.index];
                    const take = Math.min(Math.max(0, cur), deduct);
                    newCents[e.index] = cur - take;
                    deduct -= take;
                }
                const updates = [];
                for (const i of indicesUnique) {
                    if (newCents[i] === undefined) {
                        continue;
                    }
                    const row = tableRows[i];
                    const oldLg = parseFloat(row.loss_gain) || 0;
                    const newLg = lgCentsToMoney(newCents[i]);
                    if (lgMoneyToCents(oldLg) === lgMoneyToCents(newLg)) {
                        continue;
                    }
                    const oldTa = parseFloat(row.to_adjust) || 0;
                    const newTa = derivedToAdjustFromLossGain(oldTa, oldLg, newLg, row.lp);
                    updates.push({
                        inventory_id: row.inventory_id,
                        to_adjust: newTa,
                        loss_gain: newLg,
                        rowIndex: i,
                        oldTa,
                        newTa,
                        newLg,
                    });
                }
                if (updates.length === 0) {
                    return { error: 'Nothing to change for the current selection.' };
                }
                return { updates };
            }

            function aqHistoryRowCells(h) {
                const kindRaw = (h.kind || 'aq').toString().toLowerCase();
                const typeLabel = kindRaw === 'av' ? 'AV' : 'AQ';
                const u = h.user_email || '—';
                const oa = h.old_to_adjust != null ? h.old_to_adjust : '—';
                const na = h.new_to_adjust != null ? h.new_to_adjust : '—';
                const olg = h.old_loss_gain != null ? Math.trunc(parseFloat(h.old_loss_gain)) : '—';
                const nlg = h.new_loss_gain != null ? Math.trunc(parseFloat(h.new_loss_gain)) : '—';
                const when = h.created_at || '—';
                const adjText = `${oa} → ${na}`;
                const lgText = `${olg} → ${nlg}`;
                return { typeLabel, when, u, sku: h.sku || '', adjText, lgText };
            }

            function renderAqHistoryTable() {
                const tbody = $('#aqHistoryTable tbody');
                tbody.empty();

                const qAll = ($('#aqHistorySearchAll').val() || '').trim().toLowerCase();
                const fKind = ($('#aqHistColKind').val() || '').trim().toLowerCase();
                const fWhen = ($('#aqHistColWhen').val() || '').trim().toLowerCase();
                const fUser = ($('#aqHistColUser').val() || '').trim().toLowerCase();
                const fSku = ($('#aqHistColSku').val() || '').trim().toLowerCase();
                const fAdj = ($('#aqHistColAdj').val() || '').trim().toLowerCase();
                const fLg = ($('#aqHistColLg').val() || '').trim().toLowerCase();

                const filtered = (aqHistoryData || []).filter(h => {
                    const c = aqHistoryRowCells(h);
                    const hay = [c.typeLabel, c.when, c.u, c.sku, c.adjText, c.lgText].join(' ').toLowerCase();
                    if (qAll && !hay.includes(qAll)) {
                        return false;
                    }
                    if (fKind && !String(c.typeLabel).toLowerCase().includes(fKind)) {
                        return false;
                    }
                    if (fWhen && !String(c.when).toLowerCase().includes(fWhen)) {
                        return false;
                    }
                    if (fUser && !String(c.u).toLowerCase().includes(fUser)) {
                        return false;
                    }
                    if (fSku && !String(c.sku).toLowerCase().includes(fSku)) {
                        return false;
                    }
                    if (fAdj && !String(c.adjText).toLowerCase().includes(fAdj)) {
                        return false;
                    }
                    if (fLg && !String(c.lgText).toLowerCase().includes(fLg)) {
                        return false;
                    }
                    return true;
                });

                if (aqHistoryLoading) {
                    $('#aqHistoryCount').text('Loading…');
                    tbody.append('<tr><td colspan="6" class="text-center text-muted">Loading…</td></tr>');
                    return;
                }
                const total = aqHistoryData.length;
                if (filtered.length === total) {
                    $('#aqHistoryCount').text(total + ' row' + (total === 1 ? '' : 's'));
                } else {
                    $('#aqHistoryCount').text(filtered.length + ' of ' + total + ' rows');
                }
                if (!aqHistoryData.length) {
                    tbody.append('<tr><td colspan="6" class="text-center text-muted">No history yet.</td></tr>');
                    return;
                }
                if (!filtered.length) {
                    tbody.append('<tr><td colspan="6" class="text-center text-muted">No rows match filters.</td></tr>');
                    return;
                }
                filtered.forEach(h => {
                    const c = aqHistoryRowCells(h);
                    tbody.append(`
                        <tr>
                            <td class="text-nowrap small fw-semibold">${c.typeLabel}</td>
                            <td class="text-nowrap small">${c.when}</td>
                            <td class="small text-break">${c.u}</td>
                            <td>${c.sku}</td>
                            <td>${c.adjText}</td>
                            <td>${c.lgText}</td>
                        </tr>
                    `);
                });
            }

            function loadAqHistory() {
                if (aqHistoryLoading) {
                    return;
                }
                aqHistoryLoading = true;
                renderAqHistoryTable();
                $.ajax({
                    url: '/lost-gain-aq-history',
                    method: 'GET',
                    data: { limit: 10000 },
                    success: function(res) {
                        aqHistoryData = res.data || [];
                        aqHistoryStale = false;
                        aqHistoryLoading = false;
                        $('#aqHistoryCountWrap').removeClass('text-danger');
                        renderAqHistoryTable();
                    },
                    error: function() {
                        aqHistoryLoading = false;
                        aqHistoryData = [];
                        $('#aqHistoryTable tbody').html('<tr><td colspan="6" class="text-center text-danger">Failed to load history.</td></tr>');
                        $('#aqHistoryCount').text('Failed to load');
                        $('#aqHistoryCountWrap').addClass('text-danger');
                    }
                });
            }

            function setHistoryPanelOpen(open) {
                const $panel = $('#aqHistoryPanel');
                const $label = $('#historyBadgeLabel');
                const $btn = $('#historyBadgeBtn');
                if (open) {
                    $panel.removeClass('d-none');
                    $label.removeClass('bg-secondary').addClass('bg-primary text-white');
                    $btn.attr('aria-expanded', 'true');
                    if (aqHistoryStale) {
                        loadAqHistory();
                    } else {
                        renderAqHistoryTable();
                    }
                } else {
                    $panel.addClass('d-none');
                    $label.removeClass('bg-primary text-white').addClass('bg-secondary');
                    $btn.attr('aria-expanded', 'false');
                }
            }

            $('#historyBadgeBtn').on('click', function() {
                const willOpen = $('#aqHistoryPanel').hasClass('d-none');
                setHistoryPanelOpen(willOpen);
            });

            $('#aqHistoryToggleColFilters').on('click', function(e) {
                e.preventDefault();
                const $row = $('#aqHistoryColFiltersRow');
                const nowHidden = $row.hasClass('d-none');
                $row.toggleClass('d-none', !nowHidden);
                $(this).attr('aria-expanded', nowHidden ? 'true' : 'false');
            });

            $('#aqHistorySearchAll, #aqHistColKind, #aqHistColWhen, #aqHistColUser, #aqHistColSku, #aqHistColAdj, #aqHistColLg').on('keyup input', function() {
                if (!$('#aqHistoryPanel').hasClass('d-none') && !aqHistoryLoading) {
                    renderAqHistoryTable();
                }
            });

            function runAdjustQuantity(triggerRowIndex) {
                const checked = [];
                $('.row-checkbox:checked').each(function() {
                    checked.push(parseInt($(this).data('row-index'), 10));
                });
                let indices = checked.length ? checked : [triggerRowIndex];
                indices = [...new Set(indices)].filter(i => Number.isFinite(i) && tableRows[i]);
                if (indices.length === 0) {
                    return;
                }
                const plan = buildAqPlan(indices);
                if (plan.error) {
                    alert(plan.error);
                    return;
                }
                const payload = plan.updates.map(u => ({
                    inventory_id: u.inventory_id,
                    to_adjust: u.newTa,
                    loss_gain: u.newLg,
                }));
                $.ajax({
                    url: '/lost-gain-adjust-quantity',
                    method: 'POST',
                    data: {
                        updates: payload,
                        kind: 'aq',
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(res) {
                        if (!res.success) {
                            alert(res.message || 'Adjust Quantity failed.');
                            return;
                        }
                        plan.updates.forEach(u => {
                            const row = tableRows[u.rowIndex];
                            if (!row) {
                                return;
                            }
                            row.to_adjust = u.newTa;
                            row.loss_gain = u.newLg;
                            row.formatted_loss_gain = formatLossGainCell(u.newLg);
                            if (u.newTa === 0) {
                                row.aqHidden = true;
                            }
                        });
                        autoMarkZeroAdjustAsIA(function() {
                            renderTableRows(tableRows);
                            updateTotals();
                            applyFilters();
                            aqHistoryStale = true;
                            if (!$('#aqHistoryPanel').hasClass('d-none')) {
                                loadAqHistory();
                            }
                            $('.row-checkbox').prop('checked', false);
                            $('#selectAllCheckbox').prop('checked', false);
                            updateBulkButtonState();
                        });
                    },
                    error: function(xhr) {
                        let msg = 'Adjust Quantity request failed.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                        alert(msg);
                    }
                });
            }

            function runAdjustValue(triggerRowIndex) {
                const checked = [];
                $('.row-checkbox:checked').each(function() {
                    checked.push(parseInt($(this).data('row-index'), 10));
                });
                let indices = checked.length ? checked : [triggerRowIndex];
                indices = [...new Set(indices)].filter(i => Number.isFinite(i) && tableRows[i]);
                if (indices.length === 0) {
                    return;
                }
                const plan = buildAvPlan(indices);
                if (plan.error) {
                    alert(plan.error);
                    return;
                }
                const payload = plan.updates.map(u => ({
                    inventory_id: u.inventory_id,
                    to_adjust: u.newTa,
                    loss_gain: u.newLg,
                }));
                $.ajax({
                    url: '/lost-gain-adjust-quantity',
                    method: 'POST',
                    data: {
                        updates: payload,
                        kind: 'av',
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(res) {
                        if (!res.success) {
                            alert(res.message || 'Adjust Value failed.');
                            return;
                        }
                        plan.updates.forEach(u => {
                            const row = tableRows[u.rowIndex];
                            if (!row) {
                                return;
                            }
                            row.to_adjust = u.newTa;
                            row.loss_gain = u.newLg;
                            row.formatted_loss_gain = formatLossGainCell(u.newLg);
                            if (u.newTa === 0) {
                                row.aqHidden = true;
                            }
                        });
                        autoMarkZeroAdjustAsIA(function() {
                            renderTableRows(tableRows);
                            updateTotals();
                            applyFilters();
                            aqHistoryStale = true;
                            if (!$('#aqHistoryPanel').hasClass('d-none')) {
                                loadAqHistory();
                            }
                            $('.row-checkbox').prop('checked', false);
                            $('#selectAllCheckbox').prop('checked', false);
                            updateBulkButtonState();
                        });
                    },
                    error: function(xhr) {
                        let msg = 'Adjust Value request failed.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                        alert(msg);
                    }
                });
            }
            
            // Load data on page load (History loads when panel opened)
            loadLostGainData();

            function loadLostGainData() {
                // Get filter values
                const reason = $('#reasonFilter').val() || '';
                const approvedBy = $('#approvedByFilter').val() || '';
                const dateFrom = $('#dateFromFilter').val() || '';
                const dateTo = $('#dateToFilter').val() || '';
                
                // Clear existing table rows
                tableRows = [];
                
                $.ajax({
                    url: '/verified-stock-activity-log',
                    method: 'GET',
                    data: {
                        reason: reason,
                        approved_by: approvedBy,
                        date_from: dateFrom,
                        date_to: dateTo
                    },
                    success: function(res) {
                        const tableBody = $('#lostGainTable tbody');
                        tableBody.empty();

                        if (!res.data || res.data.length === 0) {
                            tableBody.append('<tr><td colspan="12" class="text-center">No data found.</td></tr>');
                            updateIaStatusFilterCounts();
                        } else {
                            // Fetch parent and LP data for all SKUs
                            const skus = res.data.map(item => item.sku);
                            
                            if (skus.length === 0) {
                                updateIaStatusFilterCounts();
                                return;
                            }
                            
                            $.ajax({
                                url: '/lost-gain-product-data',
                                method: 'POST',
                                data: {
                                    skus: skus,
                                    _token: $('meta[name="csrf-token"]').attr('content')
                                },
                                success: function(productData) {
                                    const productMap = {};
                                    if (productData.data && Array.isArray(productData.data)) {
                                        productData.data.forEach(p => {
                                            productMap[p.sku] = p;
                                        });
                                    }

                                    // Collect unique approved_by values for dropdown
                                    const approvedBySet = new Set();
                                    
                                    res.data.forEach(item => {
                                        const product = productMap[item.sku] || {};
                                        const parentTitle = product.parent || '(No Parent)';
                                        const toAdjust = parseFloat(item.to_adjust) || 0;
                                        const lp = parseFloat(product.lp) || 0;
                                        const unitLabel = product.unit != null && String(product.unit).trim() !== ''
                                            ? String(product.unit).trim()
                                            : '—';

                                        let lossGainValue;
                                        if (item.loss_gain !== null && item.loss_gain !== undefined && item.loss_gain !== '') {
                                            lossGainValue = parseFloat(item.loss_gain);
                                        } else {
                                            lossGainValue = lp ? toAdjust * lp : 0;
                                        }

                                        const formattedLossGain = formatLossGainCell(lossGainValue);

                                        // Collect approved_by values
                                        if (item.approved_by && item.approved_by !== '-') {
                                            approvedBySet.add(item.approved_by);
                                        }

                                        tableRows.push({
                                            parent: parentTitle,
                                            sku: item.sku ?? '-',
                                            verified_stock: item.verified_stock ?? '-',
                                            to_adjust: item.to_adjust ?? '-',
                                            unit: unitLabel,
                                            loss_gain: lossGainValue,
                                            formatted_loss_gain: formattedLossGain,
                                            reason: item.reason ?? '-',
                                            approved_by: item.approved_by ?? '-',
                                            approved_at: item.approved_at ?? '-',
                                            remarks: item.remarks ?? '-',
                                            isIA: item.is_ia || false,
                                            inventory_id: item.id ?? null,
                                            lp: lp,
                                            aqHidden: false
                                        });
                                    });
                                    
                                    // Populate approved_by dropdown
                                    populateApprovedByDropdown(Array.from(approvedBySet).sort());

                                    finishLostGainTableInit();
                                },
                                error: function() {
                                    // Fallback: render without parent data
                                    // Collect unique approved_by values for dropdown
                                    const approvedBySet = new Set();
                                    
                                    res.data.forEach(item => {
                                        const toAdjust = parseFloat(item.to_adjust) || 0;
                                        let lossGainValue = parseFloat(item.loss_gain) || 0;
                                        
                                        // Collect approved_by values
                                        if (item.approved_by && item.approved_by !== '-') {
                                            approvedBySet.add(item.approved_by);
                                        }
                                        
                                        const formattedLossGain = formatLossGainCell(lossGainValue);
                                        
                                        tableRows.push({
                                            parent: '(No Parent)',
                                            sku: item.sku ?? '-',
                                            verified_stock: item.verified_stock ?? '-',
                                            to_adjust: item.to_adjust ?? '-',
                                            unit: '—',
                                            loss_gain: lossGainValue,
                                            formatted_loss_gain: formattedLossGain,
                                            reason: item.reason ?? '-',
                                            approved_by: item.approved_by ?? '-',
                                            approved_at: item.approved_at ?? '-',
                                            remarks: item.remarks ?? '-',
                                            isIA: item.is_ia || false,
                                            inventory_id: item.id ?? null,
                                            lp: 0,
                                            aqHidden: false
                                        });
                                    });
                                    
                                    // Populate approved_by dropdown
                                    populateApprovedByDropdown(Array.from(approvedBySet).sort());

                                    finishLostGainTableInit();
                                }
                            });
                        }
                    },
                    error: function() {
                        alert('Failed to load data.');
                        updateIaStatusFilterCounts();
                    }
                });
            }

            function renderTableRows(rows) {
                const tableBody = $('#lostGainTable tbody');
                tableBody.empty();
                
                rows.forEach((row, index) => {
                    if (row.aqHidden) {
                        return;
                    }
                    if (iaFilterMode === 'ia' && !row.isIA) {
                        return;
                    }
                    if (iaFilterMode === 'pending' && row.isIA) {
                        return;
                    }
                    
                    const iaChecked = row.isIA ? 'checked' : '';
                    // Apply red color for negative loss/gain values
                    const lossGainClass = row.loss_gain < 0 ? 'text-danger fw-bold' : '';
                    const lossGainDisplay = row.formatted_loss_gain !== '-' 
                        ? `<span class="${lossGainClass}">${row.formatted_loss_gain}</span>` 
                        : row.formatted_loss_gain;
                    const skuEsc = lostGainEscapeAttr(row.sku);
                    tableBody.append(`
                        <tr data-row-index="${index}" ${row.isIA ? 'class="table-warning"' : ''}>
                            <td class="text-center">
                                <input type="checkbox" class="row-checkbox" data-row-index="${index}">
                            </td>
                            <td>${row.parent}</td>
                            <td>${row.sku}</td>
                            <td>${row.verified_stock}</td>
                            <td>${row.to_adjust}</td>
                            <td>${row.unit != null ? row.unit : '—'}</td>
                            <td class="loss-gain-column">${lossGainDisplay}</td>
                            <td>${row.reason}</td>
                            <td>${row.approved_by}</td>
                            <td>${row.approved_at}</td>
                            <td>${row.remarks}</td>
                            <td class="text-center text-nowrap">
                                <button type="button" class="btn btn-sm ia-btn btn-warning text-dark" data-row-index="${index}" title="Ignore & Archive">
                                    I&A
                                </button>
                                <button type="button" class="btn btn-sm btn-primary aq-btn ms-1" data-row-index="${index}" title="Adjust Quantity">
                                    AQ
                                </button>
                                <button type="button" class="btn btn-sm btn-warning text-dark av-btn ms-1" data-row-index="${index}" title="Adjust Value (net Loss/Gain dollars)">
                                    AV
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-success shopify-inventory-history-btn ms-1" data-sku="${skuEsc}" title="Open Shopify inventory adjustment history for this SKU">
                                    <i class="fab fa-shopify" aria-hidden="true"></i>
                                </button>
                            </td>
                        </tr>
                    `);
                });
                
                // Attach click handlers for I&A buttons
                $('.ia-btn').off('click').on('click', function() {
                    const rowIndex = parseInt($(this).data('row-index'));
                    toggleIA(rowIndex);
                });

                $('.aq-btn').off('click').on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const rowIndex = parseInt($(this).data('row-index'), 10);
                    runAdjustQuantity(rowIndex);
                });

                $('.av-btn').off('click').on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const rowIndex = parseInt($(this).data('row-index'), 10);
                    runAdjustValue(rowIndex);
                });
                
                // Attach checkbox handlers
                $('.row-checkbox').off('change').on('change', function() {
                    updateBulkButtonState();
                });
                
                // Update bulk button state
                updateBulkButtonState();
                updateIaStatusFilterCounts();
            }

            function toggleIA(rowIndex) {
                if (tableRows[rowIndex]) {
                    const newIAStatus = !tableRows[rowIndex].isIA;
                    const sku = tableRows[rowIndex].sku;
                    
                    // Save to database
                    $.ajax({
                        url: '/lost-gain-update-ia',
                        method: 'POST',
                        data: {
                            skus: [sku],
                            is_ia: newIAStatus,
                            _token: $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function(res) {
                            if (res.success) {
                                if (res.updated > 0) {
                                    // Update ALL rows with the same SKU (not just the clicked one)
                                    tableRows.forEach((row, idx) => {
                                        if (row.sku === sku) {
                                            row.isIA = newIAStatus;
                                        }
                                    });
                                    
                                    renderTableRows(tableRows);
                                    updateTotals();
                                    applyFilters();
                                    
                                    // Show success message if there were any issues
                                    if (res.not_found && res.not_found.length > 0) {
                                        alert('Warning: ' + res.message);
                                    }
                                } else {
                                    alert('Failed to update: SKU not found in database.');
                                }
                            } else {
                                alert('Failed to save I&A status: ' + (res.message || 'Unknown error'));
                            }
                        },
                        error: function(xhr) {
                            let errorMsg = 'Failed to save I&A status. Please try again.';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMsg = xhr.responseJSON.message;
                            }
                            alert(errorMsg);
                        }
                    });
                }
            }

            function updateTotals() {
                let lossGainTotal = 0;
                let iaTotal = 0;
                let adjustedSumAll = 0;
                tableRows.forEach(row => {
                    adjustedSumAll += parseFloat(row.to_adjust) || 0;
                    if (row.isIA) {
                        iaTotal += row.loss_gain;
                    } else {
                        lossGainTotal += row.loss_gain;
                    }
                });
                
                $('#lostGainTotal').text(`${Math.trunc(lossGainTotal)}`);
                $('#iaTotal').text(`${Math.trunc(iaTotal)}`);
                $('#adjustedToolbarTotal').text(`${Math.trunc(adjustedSumAll)}`);
                
                // Update badge color based on value (red for negative)
                const lostGainBadge = $('#lostGainBadge');
                if (lossGainTotal < 0) {
                    lostGainBadge.removeClass('btn-primary').addClass('btn-danger');
                } else {
                    lostGainBadge.removeClass('btn-danger').addClass('btn-primary');
                }

                const adjustedToolbarBadge = $('#adjustedToolbarBadge');
                if (adjustedSumAll < 0) {
                    adjustedToolbarBadge.removeClass('btn-dark').addClass('btn-danger');
                } else {
                    adjustedToolbarBadge.removeClass('btn-danger').addClass('btn-dark');
                }
            }

            function updateBulkButtonState() {
                const checkedCount = $('.row-checkbox:checked').length;
                $('#bulkIABtn').prop('disabled', checkedCount === 0);
            }

            function bulkMarkAsIA() {
                const selectedRows = [];
                const selectedSkus = [];
                $('.row-checkbox:checked').each(function() {
                    const rowIndex = parseInt($(this).data('row-index'));
                    selectedRows.push(rowIndex);
                    if (tableRows[rowIndex]) {
                        selectedSkus.push(tableRows[rowIndex].sku);
                    }
                });

                if (selectedRows.length === 0 || selectedSkus.length === 0) {
                    return;
                }

                // Disable button during processing
                $('#bulkIABtn').prop('disabled', true).text('Processing...');

                // Save to database
                $.ajax({
                    url: '/lost-gain-update-ia',
                    method: 'POST',
                    data: {
                        skus: selectedSkus,
                        is_ia: true,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(res) {
                        if (res.success) {
                            if (res.updated > 0) {
                                // Get unique SKUs from selected rows
                                const selectedSkuSet = new Set(selectedSkus);
                                
                                // Update ALL rows with the same SKU (not just the selected ones)
                                tableRows.forEach((row, idx) => {
                                    if (selectedSkuSet.has(row.sku)) {
                                        row.isIA = true;
                                    }
                                });
                            }

                            // Re-render table and update totals
                            renderTableRows(tableRows);
                            updateTotals();
                            applyFilters();
                            
                            // Uncheck all checkboxes
                            $('.row-checkbox').prop('checked', false);
                            $('#selectAllCheckbox').prop('checked', false);
                            updateBulkButtonState();
                            
                            // Show message if there were issues
                            if (res.not_found && res.not_found.length > 0) {
                                alert('Warning: ' + res.message);
                            } else if (res.updated === 0) {
                                alert('Failed to update: No records found for the selected SKUs.');
                            }
                        } else {
                            alert('Failed to save I&A status: ' + (res.message || 'Unknown error'));
                        }
                    },
                    error: function(xhr) {
                        let errorMsg = 'Failed to save I&A status. Please try again.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        }
                        alert(errorMsg);
                    },
                    complete: function() {
                        // Re-enable button
                        $('#bulkIABtn').prop('disabled', false).html('<i class="fas fa-archive"></i> Mark I &amp; A');
                    }
                });
            }

            function clearColumnSortArrows() {
                $('#lostGainTable thead tr:first-child th.sortable-th .sort-arrow').text('');
            }

            function updateColumnSortArrow(field) {
                clearColumnSortArrows();
                const arrow = columnSort.direction === -1 ? '↓' : '↑';
                $(`#lostGainTable thead tr:first-child th.sortable-th[data-sort="${field}"] .sort-arrow`).text(arrow);
            }

            function getSortValue(row, field) {
                if (field === 'approved_at') {
                    const s = row.approved_at;
                    if (!s || s === '-') {
                        return Number.NEGATIVE_INFINITY;
                    }
                    const t = new Date(s).getTime();
                    return Number.isFinite(t) ? t : Number.NEGATIVE_INFINITY;
                }
                if (field === 'loss_gain' || field === 'to_adjust') {
                    const v = parseFloat(row[field]);
                    return Number.isFinite(v) ? v : 0;
                }
                if (field === 'verified_stock') {
                    const v = parseFloat(row.verified_stock);
                    if (Number.isFinite(v)) {
                        return v;
                    }
                    return String(row.verified_stock ?? '').toLowerCase();
                }
                return String(row[field] ?? '').toLowerCase();
            }

            function compareRowField(a, b, field) {
                const va = getSortValue(a, field);
                const vb = getSortValue(b, field);
                let cmp = 0;
                if (typeof va === 'number' && typeof vb === 'number') {
                    if (va < vb) {
                        cmp = -1;
                    } else if (va > vb) {
                        cmp = 1;
                    }
                } else {
                    cmp = String(va).localeCompare(String(vb));
                }
                return columnSort.direction === -1 ? -cmp : cmp;
            }

            function initSort() {
                const $headRow = $('#lostGainTable thead tr:first-child');
                $headRow.off('click', 'th.sortable-th').on('click', 'th.sortable-th', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const field = $(this).data('sort');
                    if (!field) {
                        return;
                    }
                    const sortType = $(this).data('sort-type') || 'text';
                    if (columnSort.field === field) {
                        columnSort.direction *= -1;
                    } else {
                        columnSort.field = field;
                        columnSort.direction = sortType === 'text' ? 1 : -1;
                    }
                    updateColumnSortArrow(field);
                    tableRows.sort((a, b) => compareRowField(a, b, field));
                    renderTableRows(tableRows);
                    updateTotals();
                    applyFilters();
                });
                updateColumnSortArrow(columnSort.field);
            }

            function applyFilters() {
                const generalSearch = $('#lostGainSearch').val().toLowerCase();
                
                let visibleLossGainTotal = 0;
                let visibleIATotal = 0;
                let visibleAdjustedTotal = 0;

                $('#lostGainTable tbody tr').each(function() {
                    const $row = $(this);
                    const rowIndex = parseInt($row.data('row-index'));
                    
                    const rowText = $row.text().toLowerCase();
                    
                    let isVisible = true;
                    
                    if (generalSearch && !rowText.includes(generalSearch)) {
                        isVisible = false;
                    }
                    
                    $row.toggle(isVisible);
                    
                    if (isVisible) {
                        if (tableRows[rowIndex]) {
                            visibleAdjustedTotal += parseFloat(tableRows[rowIndex].to_adjust) || 0;
                            const lossGainValue = tableRows[rowIndex].loss_gain || 0;
                            if (tableRows[rowIndex].isIA) {
                                visibleIATotal += lossGainValue;
                            } else {
                                visibleLossGainTotal += lossGainValue;
                            }
                        }
                    }
                });

                // Update the total badges with filtered totals
                $('#lostGainTotal').text(`${Math.trunc(visibleLossGainTotal)}`);
                $('#iaTotal').text(`${Math.trunc(visibleIATotal)}`);
                $('#adjustedToolbarTotal').text(`${Math.trunc(visibleAdjustedTotal)}`);
                
                // Update badge color based on value (red for negative)
                const lostGainBadge = $('#lostGainBadge');
                if (visibleLossGainTotal < 0) {
                    lostGainBadge.removeClass('btn-primary').addClass('btn-danger');
                } else {
                    lostGainBadge.removeClass('btn-danger').addClass('btn-primary');
                }

                const adjustedToolbarBadge = $('#adjustedToolbarBadge');
                if (visibleAdjustedTotal < 0) {
                    adjustedToolbarBadge.removeClass('btn-dark').addClass('btn-danger');
                } else {
                    adjustedToolbarBadge.removeClass('btn-danger').addClass('btn-dark');
                }
            }

            $('#lostGainTable').on('click', '.shopify-inventory-history-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const sku = $(this).data('sku');
                if (!sku || sku === '-') {
                    return;
                }
                const $icon = $(this).find('i');
                $icon.removeClass('fab fa-shopify').addClass('fas fa-spinner fa-spin');
                $.ajax({
                    url: '/shopify-inventory-history-url',
                    type: 'GET',
                    data: { sku: sku },
                    success: function(res) {
                        $icon.removeClass('fas fa-spinner fa-spin').addClass('fab fa-shopify');
                        if (res.success && res.url) {
                            window.open(res.url, '_blank', 'noopener,noreferrer');
                        } else {
                            alert(res.message || 'Could not get Shopify adjustment history link.');
                        }
                    },
                    error: function(xhr) {
                        $icon.removeClass('fas fa-spinner fa-spin').addClass('fab fa-shopify');
                        let msg = 'Could not load Shopify link.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                        alert(msg);
                    }
                });
            });

            // Search functionality
            $('#lostGainSearch').on('keyup', function() {
                applyFilters();
            });

            // Select all checkbox functionality
            $('#selectAllCheckbox').on('change', function() {
                const isChecked = $(this).is(':checked');
                $('.row-checkbox:visible').prop('checked', isChecked);
                updateBulkButtonState();
            });

            // Bulk I&A button functionality
            $('#bulkIABtn').on('click', function() {
                bulkMarkAsIA();
            });
            
            // Filter change handlers
            $('#reasonFilter, #approvedByFilter, #dateFromFilter, #dateToFilter').on('change', function() {
                loadLostGainData();
            });
            
            $('#iaStatusFilter').on('change', function() {
                iaFilterMode = $(this).val() || 'pending';
                renderTableRows(tableRows);
                updateTotals();
                applyFilters();
            });
            
            // Function to populate approved_by dropdown
            function populateApprovedByDropdown(approvedByList) {
                const dropdown = $('#approvedByFilter');
                const currentValue = dropdown.val();
                
                // Clear existing options except "All Users"
                dropdown.find('option:not(:first)').remove();
                
                // Add options
                approvedByList.forEach(name => {
                    dropdown.append(`<option value="${name}">${name}</option>`);
                });
                
                // Restore previous selection if it still exists
                if (currentValue) {
                    dropdown.val(currentValue);
                }
            }
        });
    </script>
@endsection
