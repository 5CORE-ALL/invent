@extends('layouts.vertical', ['title' => 'Ready To Ship', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
<style>
    .custom-select-wrapper {
        position: relative;
        font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
    }
    .custom-select-box {
        transition: border-color 0.18s, box-shadow 0.18s;
        background: #fff;
    }
    .custom-select-box.active, .custom-select-box:focus-within {
        border-color: #3bc0c3;
        box-shadow: 0 0 0 2px #3bc0c340;
    }
    .custom-select-dropdown {
        animation: fadeIn 0.18s;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-8px);}
        to { opacity: 1; transform: translateY(0);}
    }
    .custom-select-option {
        cursor: pointer;
        font-size: 1rem;
        transition: background 0.13s, color 0.13s;
        margin: 0 4px;
        color: #222;
        background: #fff;
        border-radius: 6px;
        user-select: none;
    }
    .custom-select-option.selected,
    .custom-select-option:hover,
    .custom-select-option.bg-primary {
        background: #3bc0c3 !important;
        color: #fff !important;
    }
    .custom-select-option:not(:last-child) {
        margin-bottom: 2px;
    }
    .custom-select-dropdown::-webkit-scrollbar {
        width: 7px;
        background: #f4f6fa;
        border-radius: 6px;
    }
    /* Forecast Analysis–style supplier (mfrg_progress; column 1) */
    td.forecast-current-supplier-cell-r2s {
        vertical-align: middle;
        text-align: center;
        max-width: 92px;
    }
    td.forecast-current-supplier-cell-r2s .forecast-supplier-name {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        overflow-wrap: anywhere;
        word-break: break-word;
        line-height: 1.15;
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
        text-align: center;
        font-weight: 600;
        font-size: 0.72rem;
    }

    .custom-select-dropdown::-webkit-scrollbar-thumb {
        background: #e0e6ed;
        border-radius: 6px;
    }
</style>
@endsection
@section('content')
@include('layouts.shared.page-title', ['page_title' => 'Ready To Ship', 'sub_title' => 'Ready To Ship'])
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <!-- Filters Row - First Row -->
                <div class="column-controls card mb-3 p-3 shadow-sm" id="columnControls" style="background: #f8f9fa; border-radius: 10px;">
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <!-- Navigation -->
                        <div class="col-auto">
                            <label class="form-label fw-semibold mb-1 d-block">▶️ Navigation</label>
                            <div class="btn-group time-navigation-group" role="group">
                                <button id="play-backward" class="btn btn-light rounded-circle shadow-sm me-2" title="Previous supplier">
                                    <i class="fas fa-step-backward"></i>
                                </button>
                                <button id="play-pause" class="btn btn-light rounded-circle shadow-sm me-2" style="display: none;" title="Pause">
                                    <i class="fas fa-pause"></i>
                                </button>
                                <button id="play-auto" class="btn btn-primary rounded-circle shadow-sm me-2" title="Play">
                                    <i class="fas fa-play"></i>
                                </button>
                                <button id="play-forward" class="btn btn-light rounded-circle shadow-sm me-2" title="Next supplier">
                                    <i class="fas fa-step-forward"></i>
                                </button>
                                <button id="supplier-remarks-btn" class="btn btn-success shadow-sm" style="border-radius: 6px; margin-left: 8px;" title="Follow-up History">
                                    <i class="fas fa-comment-alt"></i> Follow-up History
                                </button>
                                <button type="button" id="r2s-supplier-summary-btn" class="btn btn-info shadow-sm text-white" style="border-radius: 6px; margin-left: 8px;" title="Supplier-wise summary of visible table rows">
                                    <i class="fas fa-table-list"></i> Supplier Summary
                                </button>
                            </div>
                        </div>

                        <!-- Toggle Columns Dropdown -->
                        <div class="col-auto">
                            <label class="form-label fw-semibold mb-1 d-block" style="visibility: hidden;">Columns</label>
                            <div class="column-dropdown position-relative">
                                <button class="btn text-white column-dropdown-btn d-flex align-items-center gap-1" id="columnDropdownBtn" style="border-radius: 6px;">
                                    <i class="mdi mdi-format-columns"></i> Toggle Columns
                                </button>
                                <div class="column-dropdown-content" id="columnDropdownContent"
                                    style="position: absolute; left: 0; top: 110%; min-width: 220px; z-index: 20; background: #fff; box-shadow: 0 2px 12px rgba(60,192,195,0.10); border-radius: 8px; border: 1px solid #e3e3e3; padding: 12px; max-height: 350px; overflow-y: auto;">
                                    <!-- Dynamic Checkboxes -->
                                </div>
                            </div>
                        </div>

                        <!-- Zone filter: matches Zone column (zone_x). No separate "Select zone" option. -->
                        <div class="col-auto">
                            <label class="form-label fw-semibold mb-1 d-block" style="visibility: hidden;">Zone</label>
                            <select id="zoneFilter" class="form-select border-2 rounded-2 fw-bold" style="min-width: 120px;" title="Filter by zone (Zone column)">
                                <option value="">All Zones</option>
                                @foreach(($supplierZoneListOptions ?? ['GHZ', 'Ningbo', 'Tianjin']) as $zf)
                                    <option value="{{ $zf }}">{{ $zf }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-auto">
                            <label class="form-label fw-semibold mb-1 d-block" style="visibility: hidden;">SKU</label>
                            <input type="search" id="r2sToolbarSkuFilter" class="form-control border-2 rounded-2 fw-bold" style="min-width: 132px; height: 42px;" placeholder="Filter SKU…" autocomplete="off" title="Contains match on SKU" aria-label="Filter rows by SKU">
                        </div>
                        <div class="col-auto">
                            <label class="form-label fw-semibold mb-1 d-block" style="visibility: hidden;">Supplier</label>
                            <input type="search" id="r2sToolbarSupplierFilter" class="form-control border-2 rounded-2 fw-bold" style="min-width: 148px; height: 42px;" placeholder="Filter Supplier…" autocomplete="off" title="Contains match on Supplier column" aria-label="Filter rows by supplier">
                        </div>

                        <!-- Move to transit: container + Move (always visible) -->
                        <div class="col-auto">
                            <label class="form-label fw-semibold mb-1 d-block" style="visibility: hidden;">To container</label>
                            <div class="d-flex align-items-center gap-2 flex-nowrap">
                                <select id="r2s-move-tab-select" class="form-select border-2 rounded-2 fw-bold" style="min-width: 160px;" title="Target container">
                                    <option value="">Container</option>
                                    @foreach($transitTabs as $tab)
                                        <option value="{{ $tab }}">{{ $tab }}</option>
                                    @endforeach
                                </select>
                                <button type="button" id="r2s-move-to-transit-btn" class="btn btn-info fw-bold border-2 rounded-2 px-3" style="white-space: nowrap; position: relative; z-index: 106;" title="Move checked rows to selected container">
                                    <i class="mdi mdi-truck-fast"></i> Move
                                </button>
                            </div>
                        </div>
                        {{-- Move handler MUST register here: main page script is 1000+ lines; any JS error above Move blocks the listener --}}
                        <script>
                        (function () {
                            var R2S_MOVE_URL = @json(rtrim(request()->getSchemeAndHttpHost() . request()->getBasePath(), '/') . '/ready-to-ship/move-to-transit');
                            var R2S_CSRF_FALLBACK = @json(csrf_token());
                            function r2sCsrf() {
                                var m = document.querySelector('meta[name="csrf-token"]');
                                return (m && m.getAttribute('content')) || R2S_CSRF_FALLBACK;
                            }
                            function r2sCheckedRows() {
                                var t = document.getElementById('readyToShipTable');
                                if (!t) return [];
                                return Array.prototype.slice.call(t.querySelectorAll('tbody .r2s-row-checkbox')).filter(function (cb) { return cb.checked; });
                            }
                            /** Rec. QTY column (20); falls back to Or. QTY (4) if empty/invalid */
                            function r2sRecQtyFromRow(tr) {
                                if (!tr) return 0;
                                var recInp = tr.querySelector('td[data-column="20"] input');
                                var v = recInp ? parseFloat(String(recInp.value).trim()) : NaN;
                                if (!isNaN(v) && v >= 0) return v;
                                var orInp = tr.querySelector('td[data-column="4"] input');
                                var ov = orInp ? parseFloat(String(orInp.value).trim()) : 0;
                                return !isNaN(ov) && ov >= 0 ? ov : 0;
                            }
                            function r2sDoMove(ev) {
                                if (ev) {
                                    ev.preventDefault();
                                    ev.stopPropagation();
                                }
                                var btn = document.getElementById('r2s-move-to-transit-btn');
                                var checked = r2sCheckedRows();
                                if (!checked.length) {
                                    alert('No rows selected.');
                                    return;
                                }
                                var tabSel = document.getElementById('r2s-move-tab-select');
                                var tabName = (tabSel && tabSel.value) ? String(tabSel.value).trim() : '';
                                if (!tabName) {
                                    alert('Please choose a container from the dropdown.');
                                    return;
                                }
                                var idList = checked.map(function (c) {
                                    var n = parseInt(c.getAttribute('data-id'), 10);
                                    return (!isNaN(n) && n > 0) ? n : null;
                                }).filter(Boolean);
                                var skus = checked.map(function (c) { return (c.getAttribute('data-sku') || '').trim(); }).filter(Boolean);
                                var recQtyById = {};
                                var recQtyBySku = {};
                                checked.forEach(function (c) {
                                    var tr = c.closest('tr');
                                    var q = r2sRecQtyFromRow(tr);
                                    var id = parseInt(c.getAttribute('data-id'), 10);
                                    if (!isNaN(id) && id > 0) recQtyById[id] = q;
                                    var sku = (c.getAttribute('data-sku') || '').trim();
                                    if (sku) {
                                        var skuK = sku.toUpperCase().replace(/\s+/g, ' ').trim();
                                        if (skuK) recQtyBySku[skuK] = q;
                                    }
                                });
                                var payload = { tab_name: tabName, rec_qty_by_id: recQtyById, rec_qty_by_sku: recQtyBySku };
                                if (idList.length) {
                                    payload.ids = idList;
                                } else if (checked.length === 1 && skus.length === 1) {
                                    payload.skus = [skus[0]];
                                } else {
                                    alert('Row IDs missing on this page. Hard refresh (Ctrl+Shift+R) and try again.');
                                    return;
                                }
                                if (btn) btn.disabled = true;
                                fetch(R2S_MOVE_URL, {
                                    method: 'POST',
                                    credentials: 'same-origin',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': r2sCsrf(),
                                        'X-Requested-With': 'XMLHttpRequest'
                                    },
                                    body: JSON.stringify(payload)
                                }).then(function (res) {
                                    return res.text().then(function (text) { return { res: res, text: text }; });
                                }).then(function (o) {
                                    if (btn) btn.disabled = false;
                                    var data;
                                    try {
                                        data = o.text ? JSON.parse(o.text) : {};
                                    } catch (e) {
                                        if (o.res.status === 419) alert('Session expired—refresh and log in again.');
                                        else alert('Server error (HTTP ' + o.res.status + '). Check you are logged in.');
                                        return;
                                    }
                                    if (data.success) {
                                        var rem = data.removed_ids || [];
                                        rem.forEach(function (id) {
                                            var cb = document.querySelector('#readyToShipTable .r2s-row-checkbox[data-id="' + id + '"]');
                                            var tr = cb ? cb.closest('tr') : null;
                                            if (tr) tr.remove();
                                        });
                                        (data.partial_updates || []).forEach(function (u) {
                                            var cb = document.querySelector('#readyToShipTable .r2s-row-checkbox[data-id="' + u.id + '"]');
                                            var tr = cb ? cb.closest('tr') : null;
                                            if (tr) {
                                                var nq = u.new_qty != null ? String(u.new_qty) : '';
                                                var orInp = tr.querySelector('td[data-column="4"] input');
                                                if (orInp) orInp.value = nq;
                                                var recInp = tr.querySelector('td[data-column="20"] input');
                                                if (recInp) recInp.value = nq;
                                                if (cb) cb.checked = false;
                                            }
                                        });
                                        if (tabSel) tabSel.value = '';
                                        if (typeof window.r2sAfterMoveSuccess === 'function') {
                                            try { window.r2sAfterMoveSuccess(); } catch (e2) {}
                                        }
                                        alert(data.message || 'Moved successfully.');
                                    } else {
                                        alert(data.message || 'Move failed.');
                                    }
                                }).catch(function () {
                                    if (btn) btn.disabled = false;
                                    alert('Network error.');
                                });
                            }
                            (function attachR2sMove() {
                                var b = document.getElementById('r2s-move-to-transit-btn');
                                if (b) {
                                    b.onclick = function (ev) { r2sDoMove(ev); };
                                } else {
                                    document.addEventListener('DOMContentLoaded', function () {
                                        var b2 = document.getElementById('r2s-move-to-transit-btn');
                                        if (b2) b2.onclick = function (ev) { r2sDoMove(ev); };
                                    });
                                }
                            })();
                            window.r2sDoMoveToTransit = r2sDoMove;
                        })();
                        </script>

                        <!-- 💰 Advance + Pending Summary -->
                        <div class="col-auto">
                            <label class="form-label fw-semibold mb-1 d-block" style="visibility: hidden;">Advance</label>
                            <div id="advance-total-wrapper" style="display: none;">
                                <div id="advance-total-display" class="py-1 px-2 rounded shadow-sm d-inline-flex align-items-center gap-2 flex-wrap"
                                    style="background: linear-gradient(90deg, #e6f4f1 60%, #f8f9fa 100%); color: #10635b; font-weight: 600; font-size: 16px; border: 1.5px solid #3bc0c3; box-shadow: 0 2px 8px rgba(60,192,195,0.08); transition: all 0.3s ease;">
                                    <div class="d-flex align-items-center gap-2" style="min-width: 170px;">
                                        <span class="rounded-circle d-flex align-items-center justify-content-center" style="background: #d1f2eb; width: 36px; height: 36px;">
                                            <i class="mdi mdi-cash-multiple" style="font-size: 22px; color: #10b39c;"></i>
                                        </span>
                                        <span>
                                            <span style="font-size: 13px; color: #23979b;">Total Advance</span><br>
                                            <span style="font-size: 18px; color: #10635b;">$ <span id="advance-amount">0</span></span>
                                        </span>
                                    </div>

                                    <div class="vr" style="height: 38px; width: 2px; background: #cde7e2; margin: 0 18px;"></div>

                                    <div class="d-flex align-items-center gap-2" style="min-width: 170px;">
                                        <span class="rounded-circle d-flex align-items-center justify-content-center" style="background: #ffeaea; width: 36px; height: 36px;">
                                            <i class="mdi mdi-alert-decagram" style="font-size: 22px; color: #ff6b6b;"></i>
                                        </span>
                                        <span>
                                            <span style="font-size: 13px; color: #ff6b6b;">Total Pending</span><br>
                                            <span style="font-size: 18px; color: #b23c3c;">$ <span id="pending-amount">0</span></span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="col-auto">
                            <label class="form-label fw-semibold mb-1 d-block" style="visibility: hidden;">Actions</label>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <button id="delete-selected-btn" class="btn btn-primary text-black d-none" style="border-radius: 6px;">
                                    <i class="mdi mdi-backup-restore"></i> Revert to MFRG
                                </button>
                                <button id="delete-selected-item" class="btn btn-danger d-none" style="border-radius: 6px;">
                                    <i class="mdi mdi-trash-can"></i> Delete
                                </button>
                                <button type="button" id="r2s-add-tab-btn" class="btn btn-primary btn-sm" style="border-radius: 6px;">
                                    <i class="fas fa-plus"></i> Add Container
                                </button>
                                <button type="button" class="btn btn-info btn-sm" style="border-radius: 6px;" data-bs-toggle="modal" data-bs-target="#r2sTransitAddItemModal">
                                    <i class="fas fa-plus"></i> Add Notes
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Counts/Stats Row - Second Row -->
                <div class="card mb-4 shadow-sm border-0" style="background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between" style="flex-wrap: nowrap; gap: 0; overflow-x: auto;">
                            <div class="text-center flex-fill" style="min-width: 110px;">
                                <div class="text-muted mb-1" style="font-size: 0.75rem; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase;">
                                    💰 Amount
                                </div>
                                <div id="total-amount" class="fw-bold text-dark" style="font-size: 2.5rem; line-height: 1.2; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
                                    $0
                                </div>
                            </div>
                            <div class="vr mx-3" style="height: 50px; width: 1px; background: linear-gradient(to bottom, transparent, #dee2e6, transparent);"></div>
                            <div class="text-center flex-fill" style="min-width: 110px;">
                                <div class="text-muted mb-1" style="font-size: 0.75rem; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase;">
                                    📊 Total CBM
                                </div>
                                <div id="total-cbm" class="fw-bold text-success" style="font-size: 2.5rem; line-height: 1.2; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
                                    0
                                </div>
                            </div>
                            <div class="vr mx-3" style="height: 50px; width: 1px; background: linear-gradient(to bottom, transparent, #dee2e6, transparent);"></div>
                            <div class="text-center flex-fill" style="min-width: 110px;">
                                <div class="text-muted mb-1" style="font-size: 0.75rem; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase;">
                                    🔢 Items
                                </div>
                                <div id="total-order-items" class="fw-bold text-warning" style="font-size: 2.5rem; line-height: 1.2; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
                                    0
                                </div>
                            </div>
                            <div class="vr mx-3" style="height: 50px; width: 1px; background: linear-gradient(to bottom, transparent, #dee2e6, transparent);"></div>
                            <div class="text-center flex-fill" style="min-width: 110px;">
                                <div class="text-muted mb-1" style="font-size: 0.75rem; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase;">
                                    👥 Suppliers
                                </div>
                                <div id="followSupplierCount" class="fw-bold text-danger" style="font-size: 2.5rem; line-height: 1.2; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #dc3545;">
                                    0
                                </div>
                            </div>
                            <div class="vr mx-3" style="height: 50px; width: 1px; background: linear-gradient(to bottom, transparent, #dee2e6, transparent); display: none;" id="supplier-badge-vr"></div>
                            <div class="text-center flex-fill" style="min-width: 140px; display: none;" id="supplier-badge-container">
                                <div class="text-muted mb-1" style="font-size: 0.75rem; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase;">
                                    🏭 Current Supplier
                                </div>
                                <div id="current-supplier" class="fw-bold text-white" style="font-size: 2.5rem; line-height: 1.2; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #28a745; padding: 8px 16px; border-radius: 6px; display: inline-block; min-width: 120px; word-break: break-word;">
                                    -
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Supplier Remarks Modal -->
                <div class="modal fade" id="supplierRemarksModal" tabindex="-1" aria-labelledby="supplierRemarksModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-success text-white">
                                <h5 class="modal-title" id="supplierRemarksModalLabel">
                                    <i class="fas fa-comment-alt"></i> Follow-up History
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Current Supplier:</label>
                                    <div id="modal-supplier-name" class="badge bg-success fs-6 p-2" style="font-size: 1rem !important;">
                                        -
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="supplier-remark-input" class="form-label fw-bold">Add to Follow-up History:</label>
                                    <textarea class="form-control" id="supplier-remark-input" rows="3" placeholder="Enter follow-up note here..."></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Follow-up History:</label>
                                    <div id="remarksList" class="border rounded p-3" style="max-height: 300px; overflow-y: auto; background: #f8f9fa;">
                                        <p class="text-muted mb-0">No follow-up history yet.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="button" class="btn btn-success" id="saveRemarkBtn">
                                    <i class="fas fa-save"></i> Save Remark
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Supplier Summary (visible rows only, DOM — no API) -->
                <div class="modal fade" id="r2sSupplierSummaryModal" tabindex="-1" aria-labelledby="r2sSupplierSummaryModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header bg-info text-white">
                                <h5 class="modal-title" id="r2sSupplierSummaryModalLabel">
                                    <i class="fas fa-table-list me-2"></i>Supplier Summary
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p class="text-muted small mb-2">Totals are from <strong>currently visible</strong> rows in the table (after stage, zone, SKU, and supplier filters).</p>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped table-bordered align-middle mb-0" id="r2s-supplier-summary-table">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Supplier</th>
                                                <th class="text-end">Total QTY</th>
                                                <th class="text-end">Total CBM</th>
                                                <th class="text-end">Total TOTAL CBM</th>
                                            </tr>
                                        </thead>
                                        <tbody id="r2s-supplier-summary-tbody"></tbody>
                                        <tfoot class="table-secondary fw-bold" id="r2s-supplier-summary-tfoot"></tfoot>
                                    </table>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" id="r2s-supplier-summary-csv-btn" title="Download summary as CSV">
                                    <i class="fas fa-file-csv me-1"></i> Export CSV
                                </button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Same Add Notes form as transit-container-details --}}
                <div class="modal fade" id="r2sTransitAddItemModal" tabindex="-1" aria-labelledby="r2sTransitAddItemModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-centered shadow-none">
                        <div class="modal-content border-0 shadow-lg">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title fw-bold" id="r2sTransitAddItemModalLabel">
                                    <i class="fas fa-file-invoice me-2"></i> Add Notes
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form id="r2sPurchaseOrderForm" method="POST" action="{{ url('transit-container/save') }}" enctype="multipart/form-data" autocomplete="off">
                                @csrf
                                <div class="modal-body">
                                    <div>
                                        <h5 class="fw-semibold mb-2 text-primary">
                                            <i class="fas fa-boxes-stacked me-1"></i> Notes
                                        </h5>
                                        <div class="row g-2">
                                            <div class="col-md-3">
                                                <label class="form-label fw-semibold">Container <span class="text-danger">*</span></label>
                                                <select class="form-select" name="tab_name" id="r2s-transit-tab-select" required>
                                                    <option value="" disabled selected>select container</option>
                                                    @foreach($transitTabs as $tab)
                                                        <option value="{{ $tab }}">{{ $tab }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div id="r2sProductRowsWrapper">
                                            <div class="row g-2 product-row r2s-product-row border rounded p-2 mt-2 position-relative">
                                                <div class="d-flex justify-content-end position-absolute top-0 end-0 p-2 ">
                                                    <i class="fas fa-trash-alt text-danger r2s-delete-product-row-btn" style="cursor: pointer; font-size: 1.2rem; margin-top:-10px;"></i>
                                                </div>
                                                <div class="col-md-3">
                                                    <select class="form-select r2s-sku-select" name="our_sku[]" required>
                                                        <option value="" disabled selected>Select SKU</option>
                                                        @foreach($transitSkus as $sku)
                                                            <option value="{{ $sku }}">{{ $sku }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label fw-semibold">Supplier</label>
                                                    <select class="form-select" name="supplier_name[]">
                                                        <option value="" disabled>Select Supplier</option>
                                                        @foreach($transitSuppliers as $supplier)
                                                            <option value="{{ $supplier->name }}">{{ $supplier->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label fw-semibold">Qty/Ctns</label>
                                                    <input type="number" class="form-control" name="no_of_units[]" step="any">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label fw-semibold">Qty Ctns</label>
                                                    <input type="number" class="form-control" name="total_ctn[]" step="any">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label fw-semibold">Qty</label>
                                                    <input type="number" class="form-control" name="pcs_qty[]" step="any">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label fw-semibold">Rate ($)</label>
                                                    <input type="number" class="form-control" name="rate[]" step="any">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label fw-semibold">CBM</label>
                                                    <input type="number" class="form-control" name="cbm[]" step="any">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label fw-semibold">Unit</label>
                                                    <input type="text" class="form-control" name="unit[]">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label fw-semibold">Changes</label>
                                                    <input type="text" class="form-control" name="changes[]">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label fw-semibold">Specifications</label>
                                                    <textarea class="form-control" name="specification[]" rows="2"></textarea>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <button type="button" class="btn btn-outline-primary btn-sm" id="r2sAddItemRowBtn">
                                                <i class="fas fa-plus-circle me-1"></i> Add Item Row
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <template id="r2s-product-row-template">
                                    <div class="row g-2 r2s-product-row border rounded p-2 mt-2 position-relative">
                                        <div class="d-flex justify-content-end position-absolute top-0 end-0 p-2 ">
                                            <i class="fas fa-trash-alt text-danger r2s-delete-product-row-btn" style="cursor: pointer; font-size: 1.2rem; margin-top:-10px;"></i>
                                        </div>
                                        <div class="col-md-3">
                                            <select class="form-select r2s-sku-select" name="our_sku[]" required>
                                                <option value="" disabled selected>Select SKU</option>
                                                @foreach($transitSkus as $sku)
                                                    <option value="{{ $sku }}">{{ $sku }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-semibold">Supplier</label>
                                            <select class="form-select" name="supplier_name[]">
                                                <option value="" disabled selected>Select Supplier</option>
                                                @foreach($transitSuppliers as $supplier)
                                                    <option value="{{ $supplier->name }}">{{ $supplier->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-semibold">Qty/Ctns</label>
                                            <input type="number" class="form-control" name="no_of_units[]" step="any">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-semibold">Qty Ctns</label>
                                            <input type="number" class="form-control" name="total_ctn[]" step="any">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-semibold">Qty</label>
                                            <input type="number" class="form-control" name="pcs_qty[]" step="any">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-semibold">Rate ($)</label>
                                            <input type="number" class="form-control" name="rate[]" step="any">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-semibold">CBM</label>
                                            <input type="number" class="form-control" name="cbm[]" step="any">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-semibold">Unit</label>
                                            <input type="text" class="form-control" name="unit[]">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-semibold">Changes</label>
                                            <input type="text" class="form-control" name="changes[]">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-semibold">Specifications</label>
                                            <textarea class="form-control" name="specification[]" rows="2"></textarea>
                                        </div>
                                    </div>
                                </template>
                                <div class="modal-footer bg-white">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                        <i class="fas fa-times me-1"></i> Close
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Save
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="wide-table-wrapper table-container">
                    <table class="wide-table" id="readyToShipTable">
                        <thead>
                            <tr>
                                <th data-column="0" style="width: 50px;">
                                    <input type="checkbox" id="selectAllCheckbox" title="Select All">
                                    <div class="resizer"></div>
                                </th>
                                <th data-column="26" data-column-name="zone_x">Zone<div class="resizer"></div>
                                </th>
                                <th data-column="1">Supplier<div class="resizer"></div></th>
                                <th data-column="2" hidden>
                                    Parent
                                    <div class="resizer"></div>
                                </th>
                                <th data-column="3">
                                    SKU
                                    <div class="resizer"></div>
                                </th>
                                <th data-column="21" data-column-name="stage" class="text-center">Stage<div class="resizer"></div></th>
                                <th data-column="22" data-column-name="nr" class="text-center" hidden>NRP<div class="resizer"></div></th>
                                <th data-column="4" data-column-name="qty" class="text-center">Or. QTY<div class="resizer"></div></th>
                                <th data-column="20" data-column-name="rec_qty" class="text-center">Rec. QTY<div class="resizer"></div></th>
                                <th data-column="18" data-column-name="qty" class="text-center" hidden>Rate<div class="resizer"></div></th>
                                <th data-column="6" data-column-name="cbm" hidden>CBM<div class="resizer"></div>
                                </th>
                                <th data-column="19" data-column-name="total_cbm">Total CBM<div class="resizer"></div>
                                </th>
                                <th data-column="25" data-column-name="cp">CP<div class="resizer"></div>
                                </th>
                                <th data-column="24" data-column-name="amount">Amount<div class="resizer"></div>
                                </th>
                                <th data-column="8" data-column-name="shipped_cbm_in_container" hidden>Balance<div
                                        class="resizer"></div>
                                </th>
                                <th data-column="15" data-column-name="packing_list">Packing<br/>List
                                    @if(!empty($packingListSheetEditUrl ?? ''))
                                        <a href="{{ $packingListSheetEditUrl }}" target="_blank" rel="noopener" class="small ms-1 align-top" title="Open packing list Google Sheet (edit links)" aria-label="Open packing list Google Sheet">↗</a>
                                    @endif
                                    <div class="resizer"></div>
                                </th>
                                <th data-column="10" data-column-name="pay_term">Terms<div class="resizer"></div>
                                </th>
                                <th data-column="11" data-column-name="payment_confirmation" hidden>ADV<br/>CONFIRM<div
                                        class="resizer"></div>
                                </th>
                                <th data-column="12" data-column-name="model_number" hidden>Model<br/>Number<div class="resizer">
                                    </div>
                                </th>
                                <th data-column="13" data-column-name="photo_mail_send">New<br/>Photo<div
                                        class="resizer"></div>
                                </th>
                                <th data-column="14" data-column-name="followup_delivery" hidden>Followup<br/>Delivery<div
                                        class="resizer"></div>
                                </th>
                                <th data-column="16" data-column-name="container_rfq" hidden>Container<br/>RFQ<div class="resizer">
                                    </div>
                                </th>
                                <th data-column="17" data-column-name="quote_result" hidden>Quote<br/>Result<div class="resizer">
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($readyToShipList as $item)
                                @php
                                    $nrValue = strtoupper(trim($item->nr ?? ''));
                                @endphp
                                @continue($nrValue === 'NR')
                                @php
                                    $r2sPackingNorm = \App\Services\ReadyToShipPackingListSheetService::normalizeSku($item->sku ?? '');
                                    $r2sPackingLink = ($packingListLinks ?? [])[$r2sPackingNorm] ?? null;
                                    $mfrgSup = trim((string) ($item->mfrg_supplier ?? ''));
                                    $mfrgDisplay = $mfrgSup !== '' ? $mfrgSup : '—';
                                    $supplierZoneMapLocal = $supplierZoneMap ?? [];
                                    $mappedZone = '';
                                    if ($mfrgSup !== '') {
                                        if (isset($supplierZoneMapLocal[$mfrgSup])) {
                                            $mappedZone = trim((string) $supplierZoneMapLocal[$mfrgSup]);
                                        } else {
                                            foreach ($supplierZoneMapLocal as $n => $z) {
                                                if (strcasecmp(trim((string) $n), $mfrgSup) === 0) {
                                                    $mappedZone = trim((string) $z);
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                    $supplierZoneListOpts = $supplierZoneListOptions ?? ['GHZ', 'Ningbo', 'Tianjin'];
                                    $zoneXStored = trim((string) ($item->zone_x ?? ''));
                                    $zoneXSelected = $zoneXStored !== '' ? $zoneXStored : $mappedZone;
                                    if ($zoneXSelected !== '' && ! in_array($zoneXSelected, $supplierZoneListOpts, true)) {
                                        $supplierZoneListOpts = array_values(array_unique(array_merge([$zoneXSelected], $supplierZoneListOpts)));
                                    }
                                @endphp
                            <tr data-stage="{{ $item->stage ?? '' }}" class="stage-row" data-r2s-supplier="{{ e($item->supplier ?? '') }}" data-mfrg-supplier="{{ e($mfrgSup) }}">
                                <td data-column="0">
                                    <input type="checkbox" class="r2s-row-checkbox" data-id="{{ $item->id }}" data-sku="{{ e($item->sku) }}" aria-label="Select row">
                                </td>
                                <td data-column="26" class="text-center align-middle">
                                    <select data-sku="{{ $item->sku }}" data-column="zone_x" class="form-select form-select-sm auto-save r2s-zone-x-select" style="width: 96px; font-size: 13px;" title="Zones from supplier master (supplier list)">
                                        <option value="">—</option>
                                        @foreach ($supplierZoneListOpts as $zxOpt)
                                            <option value="{{ $zxOpt }}" {{ $zoneXSelected === $zxOpt ? 'selected' : '' }}>{{ $zxOpt }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td data-column="1" class="forecast-current-supplier-cell-r2s">
                                    <span class="forecast-supplier-name" title="{{ e($mfrgSup) }}">{{ e($mfrgDisplay) }}</span>
                                </td>
                                
                                <td data-column="2" class="text-center" hidden>{{ $item->parent }}</td>
                                <td data-column="3" class="text-center">{{ $item->sku }}</td>
                                <td data-column="21" class="text-center">
                                    @php
                                        $stageValue = $item->stage ?? '';
                                        $bgColor = '#fff';
                                        if ($stageValue === 'to_order_analysis') {
                                            $bgColor = '#ffc107'; // Yellow
                                        } elseif ($stageValue === 'mip') {
                                            $bgColor = '#0d6efd'; // Blue
                                        } elseif ($stageValue === 'r2s') {
                                            $bgColor = '#198754'; // Green
                                        }
                                    @endphp
                                    <select class="form-select form-select-sm editable-select-stage" 
                                        data-type="Stage"
                                        data-sku="{{ $item->sku }}"
                                        data-parent="{{ $item->parent ?? '' }}"
                                        style="width: auto; min-width: 100px; padding: 4px 24px 4px 8px;
                                            font-size: 0.875rem; border-radius: 4px; border: 1px solid #dee2e6;
                                            background-color: {{ $bgColor }}; color: #000;">
                                        <option value="">Select</option>
                                        <option value="appr_req" {{ $stageValue === 'appr_req' ? 'selected' : '' }}>Appr. Req</option>
                                        <option value="mip" {{ $stageValue === 'mip' ? 'selected' : '' }}>MIP</option>
                                        <option value="r2s" {{ $stageValue === 'r2s' ? 'selected' : '' }}>R2S</option>
                                        <option value="transit" {{ $stageValue === 'transit' ? 'selected' : '' }}>Transit</option>
                                        <option value="all_good" {{ $stageValue === 'all_good' ? 'selected' : '' }}>😊 All Good</option>
                                        <option value="to_order_analysis" {{ $stageValue === 'to_order_analysis' ? 'selected' : '' }}>2 Order</option>
                                    </select>
                                </td>
                                <td data-column="22" class="text-center" hidden>
                                    @php
                                        $nrValue = strtoupper(trim($item->nr ?? ''));
                                        $bgColor = '#ffffff';
                                        $textColor = '#000000';
                                        if (!$nrValue || $nrValue === '') {
                                            $nrValue = 'REQ';
                                        }
                                        // Normalize value to match expected values
                                        if ($nrValue !== 'REQ' && $nrValue !== 'NR' && $nrValue !== 'LATER') {
                                            $nrValue = 'REQ'; // Default to REQ if value doesn't match
                                        }
                                        if ($nrValue === 'NR') {
                                            $bgColor = '#dc3545';
                                            $textColor = '#ffffff';
                                        } else if ($nrValue === 'REQ') {
                                            $bgColor = '#28a745';
                                            $textColor = '#000000';
                                        } else if ($nrValue === 'LATER') {
                                            $bgColor = '#ffc107';
                                            $textColor = '#000000';
                                        }
                                    @endphp
                                    <select class="form-select form-select-sm editable-select-nrp" 
                                        data-type="NR"
                                        data-sku="{{ $item->sku }}"
                                        data-parent="{{ $item->parent ?? '' }}"
                                        style="width: auto; min-width: 85px; padding: 4px 8px;
                                            font-size: 0.875rem; border-radius: 4px; border: 1px solid #dee2e6;
                                            background-color: {{ $bgColor }}; color: {{ $textColor }};">
                                        <option value="REQ" {{ $nrValue === 'REQ' ? 'selected' : '' }}>REQ</option>
                                        <option value="NR" {{ $nrValue === 'NR' ? 'selected' : '' }}>2BDC</option>
                                        <option value="LATER" {{ $nrValue === 'LATER' ? 'selected' : '' }}>LATER</option>
                                    </select>
                                </td>
                                <td data-column="4" class="text-center" style="background-color: #e9ecef;">
                                    <input type="number" 
                                        value="{{ $item->qty }}" 
                                        readonly
                                        style="width:80px; text-align:center; background-color: #e9ecef; cursor: not-allowed; border: none;"
                                        class="form-control form-control-sm">
                                </td>
                                <td data-column="20" class="text-center">
                                    @php
                                        $orderQty = $item->qty ?? '';
                                        $recQtyVal = ($item->rec_qty !== null && $item->rec_qty !== '') ? $item->rec_qty : $orderQty;
                                    @endphp
                                    <input type="number" 
                                           class="form-control auto-save" 
                                           data-sku="{{ $item->sku }}" 
                                           data-column="rec_qty" 
                                           value="{{ $recQtyVal }}" 
                                           min="0"
                                           max="10000"
                                           style="font-size: 0.95rem; height: 36px; width: 90px;">
                                </td>
                                <td data-column="18" hidden>
                                    <input type="number" 
                                           class="form-control auto-save" 
                                           data-sku="{{ $item->sku }}" 
                                           data-column="rate" 
                                           value="{{ $item->rate }}" 
                                           min="0"
                                           max="10000"
                                           style="font-size: 0.95rem; height: 36px; width: 90px;">
                                </td>
                                <td data-column="6" hidden>{{ isset($item->CBM) && $item->CBM !== null ? number_format((float)$item->CBM, 4) : 'N/A' }}</td>
                                <td data-column="19">{{ is_numeric($item->qty ?? null) && is_numeric($item->CBM ?? null) ? number_format($item->qty * $item->CBM, 2, '.', '') : '' }}</td>
                                <td data-column="25" class="text-center">
                                    @php $cpValue = $item->CP ?? null; @endphp
                                    {{ is_numeric($cpValue) ? number_format($cpValue, 2, '.', '') : '' }}
                                </td>
                                <td data-column="24" class="text-center">
                                    @php $cpValue = $item->CP ?? null; @endphp
                                    {{ is_numeric($item->qty ?? null) && is_numeric($cpValue) ? number_format($item->qty * $cpValue, 0, '.', '') : '' }}
                                </td>
                                <td data-column="8" hidden>{{ $item->shipped_cbm_in_container }}</td>
                                <td data-column="15" class="text-center r2s-packing-list-cell" data-r2s-sku-norm="{{ $r2sPackingNorm }}">
                                    @php
                                        $packing = $item->packing_list ?? 'No';
                                        $packingYes = strtoupper(trim($packing)) === 'YES';
                                    @endphp
                                    <div class="r2s-packing-list-inner d-inline-flex align-items-center justify-content-center gap-1 flex-wrap">
                                        @if($r2sPackingLink)
                                            <a href="{{ $r2sPackingLink }}" class="r2s-packing-list-link text-nowrap" target="_blank" rel="noopener" style="color:#0d9488;font-weight:600;">ready-to-ship</a>
                                        @endif
                                        <span
                                            class="packing-toggle {{ $r2sPackingLink ? 'd-none' : '' }}"
                                            data-sku="{{ $item->sku }}"
                                            data-column="packing_list"
                                            data-value="{{ $packingYes ? 'Yes' : 'No' }}"
                                            style="display:inline-block;width:14px;height:14px;border-radius:50%;cursor:pointer;background-color: {{ $packingYes ? '#28a745' : '#dc3545' }};">
                                        </span>
                                        <button type="button" class="btn btn-link btn-sm p-0 align-baseline r2s-packing-link-edit text-secondary" style="font-size:0.7rem;line-height:1;text-decoration:underline;" title="Set or edit URL (saves to database and Google Sheet)" data-sku="{{ e($item->sku) }}" data-current-url="{{ e($r2sPackingLink ?? '') }}">link</button>
                                    </div>
                                </td>
                                <td data-column="10">
                                    <select data-sku="{{ $item->sku }}" data-column="pay_term"
                                        class="form-select form-select-sm auto-save"
                                        style="min-width: 90px; font-size: 13px;">
                                        <option value="EXW" {{ ($item->pay_term ?? '') == 'EXW' ? 'selected' : '' }}>EXW
                                        </option>
                                        <option value="FOB" {{ ($item->pay_term ?? '') == 'FOB' ? 'selected' : '' }}>FOB
                                        </option>
                                    </select>
                                </td>
                                <td data-column="11" hidden>
                                    <select data-sku="{{ $item->sku }}" data-column="payment_confirmation"
                                        class="form-select form-select-sm auto-save"
                                        style="min-width: 90px; font-size: 13px;">
                                        <option value="Yes" {{ ($item->payment_confirmation ?? '') == 'Yes' ? 'selected'
                                            : '' }}>Yes</option>
                                        <option value="No" {{ ($item->payment_confirmation ?? '') == 'No' ? 'selected' :
                                            '' }}>No</option>
                                    </select>
                                </td>
                                <td data-column="12" hidden>{{ $item->model_number }}</td>
                                <td data-column="13" class="text-center">
                                    @php
                                        $newPhoto = $item->photo_mail_send ?? 'No';
                                        $newPhotoYes = strtoupper(trim($newPhoto)) === 'YES';
                                    @endphp
                                    <span
                                        class="new-photo-toggle"
                                        data-sku="{{ $item->sku }}"
                                        data-column="photo_mail_send"
                                        data-value="{{ $newPhotoYes ? 'Yes' : 'No' }}"
                                        style="display:inline-block;width:14px;height:14px;border-radius:50%;cursor:pointer;background-color: {{ $newPhotoYes ? '#28a745' : '#dc3545' }};">
                                    </span>
                                </td>
                                <td data-column="14" hidden>{{ $item->followup_delivery }}</td>
                                <td data-column="16" hidden>{{ $item->container_rfq }}</td>
                                <td data-column="17" hidden>{{ $item->quote_result }}</td>
                                 <td class="total-value d-none">
                                    @php $cpValue = $item->CP ?? null; @endphp
                                    {{ is_numeric($item->qty ?? null) && is_numeric($cpValue) ? round($item->qty * $cpValue) : '' }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.body.style.zoom = '85%';

    /** R2S assigned supplier (ready_to_ship.supplier) after Supplier column removed from grid */
    window.r2sRowAssignedSupplier = function (row) {
        if (!row || !row.getAttribute) return '';
        return String(row.getAttribute('data-r2s-supplier') || '').trim();
    };

    document.addEventListener('DOMContentLoaded', function() {
        /** null = all R2S; 'unassigned' = no supplier; string = that supplier (dropdown or play mode) */
        window.r2sSupplierNavLock = null;

        document.documentElement.setAttribute("data-sidenav-size", "condensed");

        // Column resizing functionality
        const resizers = document.querySelectorAll('.resizer');
        resizers.forEach(resizer => resizer.addEventListener('mousedown', initResize));

        // Restore column widths from localStorage
        restoreColumnWidths();

        function saveColumnWidths() {
            const widths = {};
            document.querySelectorAll('.wide-table thead th').forEach(th => {
                const col = th.getAttribute('data-column');
                widths[col] = th.offsetWidth;
            });
            localStorage.setItem('columnWidths_readyToShip', JSON.stringify(widths));
        }

        function restoreColumnWidths() {
            const widths = JSON.parse(localStorage.getItem('columnWidths_readyToShip') || '{}');
            Object.keys(widths).forEach(columnIndex => {
                const th = document.querySelector(`.wide-table thead th[data-column="${columnIndex}"]`);
                if (th) {
                    th.style.width = th.style.minWidth = th.style.maxWidth = widths[columnIndex] + 'px';
                }
            });
        }

        // Simple header sort (click column header to sort)
        function setupHeaderSorting() {
            const table = document.querySelector('table.wide-table');
            if (!table) return;
            const tbody = table.querySelector('tbody');
            if (!tbody) return;

            const numericColumns = new Set(['4', '18', '19', '20', '24', '25']); // qty, rate, cbm, rec qty, amount, cp

            table.querySelectorAll('thead th[data-column]').forEach(th => {
                th.style.cursor = 'pointer';
                th.addEventListener('click', function (e) {
                    if (e.target.classList.contains('resizer')) return;
                    // Don't sort when focusing/typing column search inputs
                    if (e.target.matches && (e.target.matches('input') || e.target.matches('select') || e.target.matches('label'))) return;

                    const col = this.getAttribute('data-column');
                    if (!col) return;

                    const currentDir = this.dataset.sortDir === 'asc' ? 'asc' : (this.dataset.sortDir === 'desc' ? 'desc' : null);
                    const nextDir = currentDir === 'asc' ? 'desc' : 'asc';
                    this.dataset.sortDir = nextDir;

                    const rows = Array.from(tbody.querySelectorAll('tr.stage-row'));

                    rows.sort((a, b) => {
                        const aCell = a.querySelector(`td[data-column="${col}"]`);
                        const bCell = b.querySelector(`td[data-column="${col}"]`);
                        const aText = aCell ? aCell.textContent.trim() : '';
                        const bText = bCell ? bCell.textContent.trim() : '';

                        let cmp = 0;
                        if (numericColumns.has(col)) {
                            const aNum = parseFloat(aText.replace(/,/g, '')) || 0;
                            const bNum = parseFloat(bText.replace(/,/g, '')) || 0;
                            cmp = aNum - bNum;
                        } else {
                            cmp = aText.localeCompare(bText, undefined, { sensitivity: 'base' });
                        }

                        return nextDir === 'asc' ? cmp : -cmp;
                    });

                    // Re-append rows in new order
                    rows.forEach(row => tbody.appendChild(row));

                    // Reapply existing filters after sort (R2S + zone)
                    if (typeof filterByR2SStage === 'function') {
                        filterByR2SStage();
                    }
                });
            });
        }

        function initResize(e) {
            e.preventDefault();
            const th = e.target.parentElement;
            const startX = e.clientX;
            const startWidth = th.offsetWidth;

            e.target.classList.add('resizing');
            th.style.width = th.style.minWidth = th.style.maxWidth = startWidth + 'px';

            const resize = (e) => {
                const newWidth = startWidth + e.clientX - startX;
                if (newWidth > 80) {
                    th.style.width = th.style.minWidth = th.style.maxWidth = newWidth + 'px';
                }
            };

            const stopResize = () => {
                document.removeEventListener('mousemove', resize);
                document.removeEventListener('mouseup', stopResize);
                e.target.classList.remove('resizing');
                saveColumnWidths();
            };

            document.addEventListener('mousemove', resize);
            document.addEventListener('mouseup', stopResize);
        }

        setupHeaderSorting();

        // Column visibility functionality
        const showAllBtn = document.getElementById('showAllColumns');
        const dropdownBtn = document.getElementById('columnDropdownBtn');
        const dropdownContent = document.getElementById('columnDropdownContent');
        const ths = document.querySelectorAll('.wide-table thead th');

        // Capitalize column names and create checkboxes
        if (!dropdownContent || !dropdownBtn) {
            console.warn('[ReadyToShip] Column dropdown elements missing; skipping column visibility UI.');
        } else {
        dropdownContent.innerHTML = '';
        ths.forEach((th, i) => {
            const colIndex = i + 1;
            const colName = capitalizeWords((th.textContent || '').trim());
            const item = document.createElement('div');
            item.className = 'column-checkbox-item';

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.id = `column-${colIndex}`;
            checkbox.className = 'column-checkbox';
            checkbox.setAttribute('data-column', colIndex);

            const label = document.createElement('label');
            label.htmlFor = `column-${colIndex}`;
            label.innerHTML = `${colName} <i class="mdi mdi-eye text-primary"></i>`;

            item.appendChild(checkbox);
            item.appendChild(label);
            dropdownContent.appendChild(item);
        });

        // Restore hidden columns from localStorage
        const hiddenColumns = getHiddenColumns();
        document.querySelectorAll('.column-checkbox').forEach(checkbox => {
            const columnIndex = checkbox.getAttribute('data-column');
            const th = document.querySelector(`.wide-table thead th[data-column="${columnIndex}"]`);
            if (!th) return;
            const label = document.querySelector(`label[for="column-${columnIndex}"]`);
            const colName = capitalizeWords((th.textContent || '').trim());

            checkbox.checked = !hiddenColumns.includes(columnIndex);
            document.querySelectorAll(`[data-column="${columnIndex}"]`).forEach(cell => {
                cell.style.display = checkbox.checked ? '' : 'none';
            });
            label.innerHTML = `${colName} <i class="mdi mdi-eye${checkbox.checked ? ' text-primary' : '-off text-muted'}"></i>`;
        });

        // Toggle dropdown
        dropdownBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdownContent.classList.toggle('show');
        });

        // Close dropdown when clicking outside
        window.addEventListener('click', (e) => {
            if (!e.target.matches('.column-dropdown-btn') && !dropdownContent.contains(e.target)) {
                dropdownContent.classList.remove('show');
            }
        });

        // Checkbox change event
        dropdownContent.querySelectorAll('.column-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const columnIndex = this.getAttribute('data-column');
                const th = document.querySelector(`.wide-table thead th[data-column="${columnIndex}"]`);
                if (!th) return;
                const label = document.querySelector(`label[for="column-${columnIndex}"]`);
                const colName = capitalizeWords((th.textContent || '').trim());
                let hidden = getHiddenColumns();

                document.querySelectorAll(`[data-column="${columnIndex}"]`).forEach(cell => {
                    cell.style.display = this.checked ? '' : 'none';
                });

                if (this.checked) {
                    hidden = hidden.filter(c => c !== columnIndex);
                    label.innerHTML = `${colName} <i class="mdi mdi-eye text-primary"></i>`;
                } else {
                    hidden.push(columnIndex);
                    label.innerHTML = `${colName} <i class="mdi mdi-eye-off text-muted"></i>`;
                }
                saveHiddenColumns(hidden);
            });
        });

        } // end column dropdown else

        // Show all columns functionality (button optional — was missing and broke all later script)
        if (showAllBtn) {
            showAllBtn.addEventListener('click', showAllColumns);
        }

        function showAllColumns() {
            document.querySelectorAll('.column-checkbox').forEach(checkbox => {
                checkbox.checked = true;
                const columnIndex = checkbox.getAttribute('data-column');
                const th = document.querySelector(`.wide-table thead th[data-column="${columnIndex}"]`);
                if (!th) return;
                const label = document.querySelector(`label[for="column-${columnIndex}"]`);
                document.querySelectorAll(`[data-column="${columnIndex}"]`).forEach(cell => {
                    cell.style.display = '';
                });
                label.innerHTML = `${th.childNodes[0].nodeValue.trim()} <i class="mdi mdi-eye text-primary"></i>`;
            });
            saveHiddenColumns([]);
        }

        // Reusable AJAX call for forecast data updates
        function updateForecastField(data, onSuccess = () => {}, onFail = () => {}) {
            fetch('/update-forecast-data', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(data)
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    onSuccess();
                } else {
                    onFail();
                }
            })
            .catch(err => {
                console.error('AJAX failed:', err);
                alert('Error saving data.');
                onFail();
            });
        }

        // Reusable AJAX call for updating forecast_analysis table
        function updateForecastField(data, onSuccess = () => {}, onFail = () => {}) {
            fetch('/update-forecast-data', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(res => res.json())
            .then(result => {
                if (result.success) {
                    console.log('Saved:', result.message);
                    onSuccess();
                } else {
                    console.warn('Not saved:', result.message);
                    onFail();
                }
            })
            .catch(err => {
                console.error('AJAX failed:', err);
                alert('Error saving data.');
                onFail();
            });
        }

        // Stage Update Handler
        function setupStageUpdate() {
            document.querySelectorAll('.editable-select-stage').forEach(function(select) {
                select.addEventListener('change', function() {
                    const sku = this.dataset.sku;
                    const parent = this.dataset.parent;
                    const value = this.value.trim();

                    // Update background color immediately
                    let bgColor = '#fff';
                    if (value === 'to_order_analysis') {
                        bgColor = '#ffc107'; // Yellow
                    } else if (value === 'mip') {
                        bgColor = '#0d6efd'; // Blue
                    } else if (value === 'r2s') {
                        bgColor = '#198754'; // Green
                    }
                    this.style.backgroundColor = bgColor;
                    this.style.color = '#000';

                    // Get order_qty for validation
                    const row = this.closest('tr');
                    const qtyInput = row.querySelector('td[data-column="4"] input');
                    const orderQty = qtyInput ? parseFloat(qtyInput.value) : 0;

                    if (!orderQty || orderQty === 0) {
                        alert("Order Qty cannot be empty or zero.");
                        this.value = '';
                        this.style.backgroundColor = '#fff';
                        return;
                    }

                    updateForecastField({
                        sku: sku,
                        parent: parent,
                        column: 'Stage',
                        value: value
                    }, function() {
                        // Success - update the select value to ensure it matches saved value
                        this.value = value;
                        // Color already updated
                    }, function() {
                        alert('Failed to save Stage.');
                        // Revert color and value
                        this.style.backgroundColor = '#fff';
                        // Reload page to get correct value from database
                        location.reload();
                    });
                });
            });
        }

        // NRP Update Handler
        function setupNRPUpdate() {
            document.querySelectorAll('.editable-select-nrp').forEach(function(select) {
                select.addEventListener('change', function() {
                    const sku = this.dataset.sku;
                    const parent = this.dataset.parent;
                    const value = this.value.trim();
                    const row = this.closest('tr');

                    // Update background color immediately
                    let bgColor = '#ffffff';
                    let textColor = '#000000';
                    if (value === 'NR') {
                        bgColor = '#dc3545';
                        textColor = '#ffffff';
                    } else if (value === 'REQ') {
                        bgColor = '#28a745';
                        textColor = '#000000';
                    } else if (value === 'LATER') {
                        bgColor = '#ffc107';
                        textColor = '#000000';
                    }
                    this.style.backgroundColor = bgColor;
                    this.style.color = textColor;

                    updateForecastField({
                        sku: sku,
                        parent: parent,
                        column: 'NR',
                        value: value
                    }, function() {
                        // Success - update the select value to ensure it matches saved value
                        this.value = value;
                        // Color already updated
                        
                        // Hide/show row based on NRP value
                        if (value === 'NR') {
                            if (row) row.style.display = 'none';
                        } else {
                            if (row) row.style.display = '';
                        }
                    }, function() {
                        alert('Failed to save NRP.');
                        // Revert color and value
                        this.style.backgroundColor = '#fff';
                        this.style.color = '#000';
                        // Reload page to get correct value from database
                        location.reload();
                    });
                });
            });
        }

        // R2S + zone toolbar + supplier nav lock (dropdown / play)
        function filterByR2SStage() {
            const table = document.getElementById('readyToShipTable');
            const zoneFilter = document.getElementById('zoneFilter');
            const rawZone = zoneFilter ? zoneFilter.value.trim() : '';
            const selectedZone = rawZone.toLowerCase();
            const skuFilterInput = document.getElementById('r2sToolbarSkuFilter');
            const supFilterInput = document.getElementById('r2sToolbarSupplierFilter');
            const skuNeedle = skuFilterInput ? skuFilterInput.value.trim().toLowerCase() : '';
            const supNeedle = supFilterInput ? supFilterInput.value.trim().toLowerCase() : '';
            const rows = table
                ? table.querySelectorAll('tbody tr.stage-row')
                : document.querySelectorAll('.wide-table tbody tr.stage-row');
            const visibleRows = [];

            rows.forEach(row => {
                const rowStageAttr = row.getAttribute('data-stage')
                    ? row.getAttribute('data-stage').toLowerCase().trim()
                    : '';
                const stageSelect = row.querySelector('.editable-select-stage');
                const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
                const rowStage = rowStageSelect || rowStageAttr;
                const isR2S = rowStage === 'r2s';

                const zoneSelect = row.querySelector('select[data-column="zone_x"]');
                const rowZoneRaw = zoneSelect ? zoneSelect.value.trim() : '';
                const rowZone = rowZoneRaw.toLowerCase();
                const zoneMatch = !selectedZone || rowZone === selectedZone;

                if (!isR2S) {
                    row.style.display = 'none';
                    return;
                }
                const navLock = window.r2sSupplierNavLock;
                if (navLock === 'unassigned') {
                    const sn = typeof window.r2sRowAssignedSupplier === 'function' ? window.r2sRowAssignedSupplier(row) : '';
                    if (sn && sn !== 'supplier') {
                        row.style.display = 'none';
                        return;
                    }
                } else if (navLock && typeof navLock === 'string') {
                    const sn = typeof window.r2sRowAssignedSupplier === 'function' ? window.r2sRowAssignedSupplier(row) : '';
                    if (sn.toLowerCase() !== navLock.toLowerCase()) {
                        row.style.display = 'none';
                        return;
                    }
                }
                if (!zoneMatch) {
                    row.style.display = 'none';
                    return;
                }

                let skuMatch = true;
                if (skuNeedle) {
                    const cb = row.querySelector('.r2s-row-checkbox');
                    const skuFromCb = cb ? String(cb.getAttribute('data-sku') || '').trim().toLowerCase() : '';
                    const skuTd = row.querySelector('td[data-column="3"]');
                    const skuFromTd = skuTd ? skuTd.textContent.trim().toLowerCase() : '';
                    const skuHaystack = skuFromCb || skuFromTd;
                    skuMatch = skuHaystack.includes(skuNeedle);
                }
                let supMatch = true;
                if (supNeedle) {
                    const supSpan = row.querySelector('td[data-column="1"] .forecast-supplier-name');
                    const supText = supSpan ? supSpan.textContent.trim().toLowerCase() : '';
                    supMatch = supText.includes(supNeedle);
                }
                if (!skuMatch || !supMatch) {
                    row.style.display = 'none';
                    return;
                }

                row.style.display = '';
                visibleRows.push(row);
            });

            calculateSupplierTotals(visibleRows);
            if (typeof updateFollowSupplierCount === 'function') {
                updateFollowSupplierCount();
            }
        }

        window.filterByR2SStage = filterByR2SStage;

        // Initialize stage handlers
        setupStageUpdate();
        setupNRPUpdate();
        // Filter to show only R2S stage on page load
        filterByR2SStage();

        // Save data on input change
        document.querySelectorAll('.auto-save').forEach(input => {
            input.addEventListener('change', function() {
                const { sku, column } = this.dataset;
                const value = this.value;

                if (!sku || !column) return;

                if (column === 'zone_x') {
                    setTimeout(() => {
                        filterByR2SStage();
                    }, 100);
                }

                fetch('/ready-to-ship/inline-update-by-sku', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ sku, column, value })
                })
                .then(res => res.json())
                .then(res => {
                    this.style.border = res.success ? '2px solid green' : '2px solid red';
                    if (!res.success) alert('Error: ' + res.message);
                    setTimeout(() => this.style.border = '', 1000);
                })
                .catch(() => {
                    this.style.border = '2px solid red';
                    alert('AJAX error occurred.');
                });
            });
        });

        // New Photo toggle (red/green dot) using ready_to_ship.photo_mail_send (Yes/No)
        document.querySelectorAll('.new-photo-toggle').forEach(dot => {
            dot.addEventListener('click', function () {
                const sku = this.dataset.sku;
                const column = this.dataset.column || 'photo_mail_send';
                const current = (this.dataset.value || 'No').toLowerCase() === 'yes' ? 'Yes' : 'No';
                const next = current === 'Yes' ? 'No' : 'Yes';

                fetch('/ready-to-ship/inline-update-by-sku', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ sku, column, value: next })
                })
                .then(res => res.json())
                .then(res => {
                    if (!res.success) {
                        alert('Error: ' + res.message);
                        return;
                    }
                    this.dataset.value = next;
                    this.style.backgroundColor = next === 'Yes' ? '#28a745' : '#dc3545';
                })
                .catch(() => {
                    alert('AJAX error occurred.');
                });
            });
        });

        // Packing List toggle (red/green dot) using ready_to_ship.packing_list (Yes/No)
        document.querySelectorAll('.packing-toggle').forEach(dot => {
            dot.addEventListener('click', function () {
                const sku = this.dataset.sku;
                const column = this.dataset.column || 'packing_list';
                const current = (this.dataset.value || 'No').toLowerCase() === 'yes' ? 'Yes' : 'No';
                const next = current === 'Yes' ? 'No' : 'Yes';

                fetch('/ready-to-ship/inline-update-by-sku', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ sku, column, value: next })
                })
                .then(res => res.json())
                .then(res => {
                    if (!res.success) {
                        alert('Error: ' + res.message);
                        return;
                    }
                    this.dataset.value = next;
                    this.style.backgroundColor = next === 'Yes' ? '#28a745' : '#dc3545';
                })
                .catch(() => {
                    alert('AJAX error occurred.');
                });
            });
        });

        // Refresh packing-list links from Google Sheet CSV (cached server-side; poll picks up sheet edits)
        (function () {
            var pollUrl = @json(route('ready.to.ship.packing.list.links'));
            var intervalMs = 120000;

            function applyPackingListLinks(links) {
                if (!links || typeof links !== 'object') {
                    return;
                }
                document.querySelectorAll('td.r2s-packing-list-cell[data-r2s-sku-norm]').forEach(function (td) {
                    var key = td.getAttribute('data-r2s-sku-norm');
                    if (!key) {
                        return;
                    }
                    var href = links[key] || '';
                    var inner = td.querySelector('.r2s-packing-list-inner');
                    if (!inner) {
                        return;
                    }
                    var a = inner.querySelector('a.r2s-packing-list-link');
                    var dot = inner.querySelector('.packing-toggle');
                    var editBtn = inner.querySelector('.r2s-packing-link-edit');
                    if (href) {
                        if (!a) {
                            a = document.createElement('a');
                            a.className = 'r2s-packing-list-link text-nowrap';
                            a.target = '_blank';
                            a.rel = 'noopener';
                            a.textContent = 'ready-to-ship';
                            a.style.color = '#0d9488';
                            a.style.fontWeight = '600';
                            inner.insertBefore(a, inner.firstChild);
                        }
                        a.setAttribute('href', href);
                        a.classList.remove('d-none');
                        if (dot) {
                            dot.classList.add('d-none');
                        }
                    } else {
                        if (a) {
                            a.classList.add('d-none');
                            a.removeAttribute('href');
                        }
                        if (dot) {
                            dot.classList.remove('d-none');
                        }
                    }
                    if (editBtn) {
                        editBtn.setAttribute('data-current-url', href || '');
                    }
                });
            }

            function poll() {
                fetch(pollUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data && data.success && data.links) {
                            applyPackingListLinks(data.links);
                        }
                    })
                    .catch(function () { /* ignore */ });
            }

            if (pollUrl) {
                setInterval(poll, intervalMs);
            }
        })();

        // Add / edit packing list URL (DB + Google Sheet)
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.r2s-packing-link-edit');
            var tbl = document.getElementById('readyToShipTable');
            if (!btn || !tbl || !tbl.contains(btn)) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            var sku = btn.getAttribute('data-sku');
            var cur = btn.getAttribute('data-current-url') || '';
            var msg = window.prompt('Packing list URL (https://...). Leave empty to remove link.', cur);
            if (msg === null) {
                return;
            }
            var v = String(msg).trim();
            fetch('/ready-to-ship/inline-update-by-sku', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ sku: sku, column: 'packing_list_link', value: v })
            })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.success) {
                        alert('Error: ' + (res.message || 'Save failed'));
                        return;
                    }
                    var td = btn.closest('td.r2s-packing-list-cell');
                    var inner = td ? td.querySelector('.r2s-packing-list-inner') : null;
                    if (!inner) {
                        return;
                    }
                    var a = inner.querySelector('a.r2s-packing-list-link');
                    var dot = inner.querySelector('.packing-toggle');
                    btn.setAttribute('data-current-url', v);
                    if (v) {
                        if (!a) {
                            a = document.createElement('a');
                            a.className = 'r2s-packing-list-link text-nowrap';
                            a.target = '_blank';
                            a.rel = 'noopener';
                            a.textContent = 'ready-to-ship';
                            a.style.color = '#0d9488';
                            a.style.fontWeight = '600';
                            inner.insertBefore(a, inner.firstChild);
                        }
                        a.setAttribute('href', v);
                        a.classList.remove('d-none');
                        if (dot) {
                            dot.classList.add('d-none');
                        }
                    } else {
                        if (a) {
                            a.remove();
                        }
                        if (dot) {
                            dot.classList.remove('d-none');
                        }
                    }
                })
                .catch(function () {
                    alert('AJAX error occurred.');
                });
        });

        // Debug: set true for checkbox/Move alerts + extra console logs
        const R2S_DEBUG_UI = false;
        const r2sLog = function(tag, payload) {
            console.log('%c[ReadyToShip]', 'color:#0d9488;font-weight:bold;', tag, payload !== undefined ? payload : '');
        };

        // Row checkboxes: unique class + #readyToShipTable only (avoids clash with other pages using .row-checkbox)
        const readyToShipTableEl = document.getElementById('readyToShipTable');
        function r2sRowCheckboxEls() {
            const t = document.getElementById('readyToShipTable');
            return t ? Array.from(t.querySelectorAll('tbody .r2s-row-checkbox')) : [];
        }
        function r2sCheckedCount() {
            return r2sRowCheckboxEls().filter(function(cb) { return cb.checked; }).length;
        }

        const selectAllCheckbox = document.getElementById('selectAllCheckbox');
        const deleteBtn = document.getElementById('delete-selected-btn');
        const deleteSelectedItemBtn = document.getElementById('delete-selected-item');
        const moveToTransitBtn = document.getElementById('r2s-move-to-transit-btn');
        let r2sSuppressRowCheckboxAlert = false;

        r2sLog('init', {
            tableFound: !!readyToShipTableEl,
            rowCheckboxes: r2sRowCheckboxEls().length,
            moveButtonFound: !!moveToTransitBtn,
            selectAllFound: !!selectAllCheckbox,
        });
        if (R2S_DEBUG_UI && !readyToShipTableEl) {
            alert('[ReadyToShip] Table #readyToShipTable NOT found.');
        }
        if (R2S_DEBUG_UI && !moveToTransitBtn) {
            alert('[ReadyToShip] Move button NOT found (id=r2s-move-to-transit-btn). Check layout.');
        }

        // Select All checkbox handler
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const rows = r2sRowCheckboxEls();
                r2sLog('SELECT ALL checkbox clicked', { checked: this.checked, rowCount: rows.length });
                if (R2S_DEBUG_UI) {
                    alert('[ReadyToShip] Select-all checkbox: ' + (this.checked ? 'CHECKED (all rows)' : 'UNCHECKED'));
                }
                r2sSuppressRowCheckboxAlert = true;
                rows.forEach(function(checkbox) {
                    checkbox.checked = selectAllCheckbox.checked;
                });
                r2sSuppressRowCheckboxAlert = false;
                updateButtonVisibility();
                updateSelectAllState();
            });
        }

        // Delegated change: works after sort/reorder; count only inside #readyToShipTable
        if (readyToShipTableEl) {
            readyToShipTableEl.addEventListener('change', function(e) {
                const el = e.target;
                if (!el || !el.classList.contains('r2s-row-checkbox')) return;
                const countScoped = r2sCheckedCount();
                const info = {
                    dataId: el.getAttribute('data-id'),
                    dataSku: el.getAttribute('data-sku'),
                    checked: el.checked,
                    checkedCountThisTable: countScoped,
                    countLegacyGlobal: document.querySelectorAll('.row-checkbox:checked').length,
                };
                r2sLog('ROW checkbox change (r2s-row-checkbox)', info);
                if (R2S_DEBUG_UI && !r2sSuppressRowCheckboxAlert) {
                    alert('[ReadyToShip] Row checkbox\nSKU: ' + (info.dataSku || '—') + '\nID: ' + (info.dataId || '—') + '\nChecked: ' + info.checked + '\nChecked in this table: ' + info.checkedCountThisTable);
                }
                updateButtonVisibility();
                updateSelectAllState();
            });
        }

        function updateSelectAllState() {
            const rows = r2sRowCheckboxEls();
            if (selectAllCheckbox && rows.length > 0) {
                const allChecked = rows.every(function(cb) { return cb.checked; });
                const someChecked = rows.some(function(cb) { return cb.checked; });
                selectAllCheckbox.checked = allChecked;
                selectAllCheckbox.indeterminate = someChecked && !allChecked;
            }
        }

        function updateButtonVisibility() {
            const anyChecked = r2sCheckedCount() > 0;
            if (deleteBtn) deleteBtn.classList.toggle('d-none', !anyChecked);
            if (deleteSelectedItemBtn) deleteSelectedItemBtn.classList.toggle('d-none', !anyChecked);
        }

        window.r2sAfterMoveSuccess = function() {
            try {
                updateButtonVisibility();
                updateSelectAllState();
            } catch (e) {}
        };

        // Initialize select all state
        updateSelectAllState();

        // Delete selected rows
        if (deleteBtn) {
            deleteBtn.addEventListener('click', function() {
                const selectedSkus = r2sRowCheckboxEls().filter(function(cb) { return cb.checked; })
                    .map(function(cb) { return cb.getAttribute('data-sku'); }).filter(Boolean);

                if (!selectedSkus.length) return alert("No rows selected.");

                fetch('/ready-to-ship/revert-back-mfrg', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ skus: selectedSkus })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        selectedSkus.forEach(function(sku) {
                            const cb = r2sRowCheckboxEls().find(function(c) { return c.getAttribute('data-sku') === sku; });
                            if (cb) cb.closest('tr')?.remove();
                        });
                        updateButtonVisibility();
                        updateSelectAllState();
                    } else {
                        alert('Revert failed');
                    }
                })
                .catch(() => alert('Error occurred during revert.'));
            });
        }

        // Move is handled by inline script after the Move button (avoids duplicate fetch if main script errors earlier).

        if (deleteSelectedItemBtn) {
            deleteSelectedItemBtn.addEventListener('click', function() {
                const selectedSkus = r2sRowCheckboxEls().filter(function(cb) { return cb.checked; })
                    .map(function(cb) { return cb.getAttribute('data-sku'); }).filter(Boolean);

                if (!selectedSkus.length) return alert("No rows selected.");

                if (!confirm("Are you sure you want to delete the selected items? This action cannot be undone.")) {
                    return;
                }

                fetch('/ready-to-ship/delete-items', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ skus: selectedSkus })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        selectedSkus.forEach(function(sku) {
                            const cb = r2sRowCheckboxEls().find(function(c) { return c.getAttribute('data-sku') === sku; });
                            if (cb) cb.closest('tr')?.remove();
                        });
                        updateButtonVisibility();
                        updateSelectAllState();
                    } else {
                        alert('Delete failed');
                    }
                })
                .catch(() => alert('Error occurred during deletion.'));
            });
        }

        // Helper functions
        function capitalizeWords(str) {
            return str.replace(/\w\S*/g, txt => txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase());
        }

        function saveHiddenColumns(hidden) {
            localStorage.setItem('hiddenColumns_readyToShip', JSON.stringify(hidden));
        }

        function getHiddenColumns() {
            return JSON.parse(localStorage.getItem('hiddenColumns_readyToShip') || '[]');
        }

        setupSupplierSelect();

        // Initialize counts on page load
        setTimeout(() => {
            calculateTotalCBM();
            calculateTotalAmount();
            calculateTotalOrderItems();
            updateFollowSupplierCount();
            updateSupplierCounts();
        }, 300);

        function setupSupplierSelect() {
            const selectBox = document.getElementById('customSelectBox');
            const dropdown = document.getElementById('customSelectDropdown');
            if (!selectBox || !dropdown) return;

            const selectedText = document.getElementById('customSelectSelectedText');
            const searchInput = document.getElementById('customSelectSearchInput');
            const optionsContainer = document.getElementById('customSelectOptions');
            const wrapper = document.getElementById('advance-total-wrapper');

            let allOptions = Array.from(optionsContainer.querySelectorAll('.custom-select-option'));

            selectBox.addEventListener('click', function () {
                dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
                selectBox.classList.toggle('active', dropdown.style.display === 'block');
                searchInput.value = '';
                allOptions.forEach(option => option.style.display = '');
                updateSupplierCounts();
                setTimeout(() => searchInput.focus(), 100);
            });

            optionsContainer.addEventListener('click', function (e) {
                if (!e.target.classList.contains('custom-select-option')) return;

                allOptions.forEach(opt => opt.classList.remove('selected', 'bg-primary', 'text-white'));
                e.target.classList.add('selected', 'bg-primary', 'text-white');
                
                const optionText = e.target.textContent.trim();
                const displayText = optionText.replace(/\s*\(\d+\)\s*$/, '');
                selectedText.textContent = displayText;
                dropdown.style.display = 'none';
                selectBox.classList.remove('active');

                const selectedSupplier = optionText.replace(/\s*\(\d+\)\s*$/, '').trim();
                const selectedValue = e.target.getAttribute('data-value');
                const r2sTbl = document.getElementById('readyToShipTable');

                if (!selectedSupplier || selectedSupplier === 'Select supplier' || selectedSupplier === 'All supplier') {
                    if (wrapper) wrapper.style.display = 'none';
                    window.r2sSupplierNavLock = null;
                    if (typeof window.filterByR2SStage === 'function') {
                        window.filterByR2SStage();
                    }
                    calculateTotalCBM();
                    calculateTotalAmount();
                    calculateTotalOrderItems();
                    updateFollowSupplierCount();
                    return;
                }

                if (selectedValue === '__all_suppliers__' || selectedSupplier === 'Supplier') {
                    window.r2sSupplierNavLock = 'unassigned';
                } else {
                    window.r2sSupplierNavLock = selectedSupplier;
                }
                if (typeof window.filterByR2SStage === 'function') {
                    window.filterByR2SStage();
                }
                const matchingRows = r2sTbl
                    ? Array.from(r2sTbl.querySelectorAll('tbody tr.stage-row')).filter(function (r) {
                        return r.style.display !== 'none';
                    })
                    : [];

                // Hide advance wrapper when any supplier is selected
                if (wrapper) wrapper.style.display = 'none';
                
                if (selectedValue === '__all_suppliers__' || selectedSupplier === 'Supplier') {
                    calculateTotalCBM();
                    calculateTotalAmount();
                    calculateTotalOrderItems();
                    updateFollowSupplierCount();
                    return;
                }

                // Calculate advance and pending for selected supplier
                let totalGroupValue = 0;
                matchingRows.forEach(row => {
                    const qtyCell = row.querySelector('td[data-column="4"]');
                    let qty = 0;
                    if (qtyCell) {
                        const qtyInput = qtyCell.querySelector('input');
                        if (qtyInput) {
                            qty = parseFloat(qtyInput.value) || 0;
                        } else {
                            qty = parseFloat(qtyCell.textContent.trim()) || parseFloat(qtyCell.getAttribute('data-qty')) || 0;
                        }
                    }
                    const rate = parseFloat(row.querySelector('input[data-column="rate"]')?.value || '0') || 0;
                    totalGroupValue += qty * rate;
                });

                let totalAdvance = 0;
                matchingRows.forEach(row => {
                    const input = row.querySelector('input[data-supplier]');
                    if (input && !input.disabled) {
                        totalAdvance += parseFloat(input.value || '0') || 0;
                    }
                });

                let totalPending = 0;
                matchingRows.forEach(row => {
                    const qtyCell = row.querySelector('td[data-column="4"]');
                    let qty = 0;
                    if (qtyCell) {
                        const qtyInput = qtyCell.querySelector('input');
                        if (qtyInput) {
                            qty = parseFloat(qtyInput.value) || 0;
                        } else {
                            qty = parseFloat(qtyCell.textContent.trim()) || parseFloat(qtyCell.getAttribute('data-qty')) || 0;
                        }
                    }
                    const rate = parseFloat(row.querySelector('input[data-column="rate"]')?.value || '0') || 0;
                    const rowTotal = qty * rate;
                    let rowAdvance = 0;
                    if (totalGroupValue > 0 && rowTotal > 0) {
                        rowAdvance = (rowTotal / totalGroupValue) * totalAdvance;
                    }
                    const rowPending = rowTotal - rowAdvance;
                    totalPending += rowPending;
                });

                if (document.getElementById('advance-amount')) {
                    document.getElementById('advance-amount').textContent = totalAdvance.toFixed(2);
                }
                if (document.getElementById('pending-amount')) {
                    document.getElementById('pending-amount').textContent = totalPending.toFixed(2);
                }

                calculateTotalCBM();
                calculateTotalAmount();
                calculateTotalOrderItems();
                updateFollowSupplierCount();
            });


            // Search filter
            searchInput.addEventListener('input', function () {
                const search = this.value.trim().toLowerCase();
                allOptions.forEach(option => {
                    option.style.display = option.textContent.toLowerCase().includes(search) ? '' : 'none';
                });
            });

            // Keyboard navigation
            searchInput.addEventListener('keydown', function (e) {
                let visibleOptions = allOptions.filter(opt => opt.style.display !== 'none');
                let selectedIdx = visibleOptions.findIndex(opt => opt.classList.contains('selected'));
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (selectedIdx < visibleOptions.length - 1) {
                        if (selectedIdx >= 0) visibleOptions[selectedIdx].classList.remove('selected', 'bg-primary', 'text-white');
                        visibleOptions[selectedIdx + 1].classList.add('selected', 'bg-primary', 'text-white');
                        visibleOptions[selectedIdx + 1].scrollIntoView({ block: 'nearest' });
                    }
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (selectedIdx > 0) {
                        visibleOptions[selectedIdx].classList.remove('selected', 'bg-primary', 'text-white');
                        visibleOptions[selectedIdx - 1].classList.add('selected', 'bg-primary', 'text-white');
                        visibleOptions[selectedIdx - 1].scrollIntoView({ block: 'nearest' });
                    }
                } else if (e.key === 'Enter') {
                    if (selectedIdx >= 0) {
                        visibleOptions[selectedIdx].click();
                    }
                }
            });

            // Close dropdown on outside click
            document.addEventListener('mousedown', function (e) {
                if (!selectBox.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.style.display = 'none';
                    selectBox.classList.remove('active');
                }
            });
        }

        const zoneFilterElement = document.getElementById('zoneFilter');
        if (zoneFilterElement) {
            zoneFilterElement.addEventListener('change', function () {
                filterByR2SStage();
            });
        }

        let r2sToolbarTextFilterTimer = null;
        function scheduleFilterByR2SStageFromToolbar() {
            clearTimeout(r2sToolbarTextFilterTimer);
            r2sToolbarTextFilterTimer = setTimeout(function () {
                r2sToolbarTextFilterTimer = null;
                filterByR2SStage();
            }, 200);
        }
        const r2sSkuFilterEl = document.getElementById('r2sToolbarSkuFilter');
        const r2sSupFilterEl = document.getElementById('r2sToolbarSupplierFilter');
        if (r2sSkuFilterEl) {
            r2sSkuFilterEl.addEventListener('input', scheduleFilterByR2SStageFromToolbar);
        }
        if (r2sSupFilterEl) {
            r2sSupFilterEl.addEventListener('input', scheduleFilterByR2SStageFromToolbar);
        }

        function calculateSupplierTotals(visibleRows) {
            let totalAmount = 0;
            let totalCBM = 0;

            visibleRows.forEach(row => {
                // Amount
                const amountCell = row.querySelector('.total-value');
                const amountValue = parseFloat(amountCell?.textContent.trim());
                if (!isNaN(amountValue)) totalAmount += amountValue;

                // CBM
                const cbmCell = row.querySelector('[data-column="19"]');
                const cbmValue = parseFloat(cbmCell?.textContent.trim());
                if (!isNaN(cbmValue)) totalCBM += cbmValue;
            });

            const totalAmountEl = document.getElementById('total-amount');
            const totalCbmEl = document.getElementById('total-cbm');
            const totalItemsEl = document.getElementById('total-order-items');
            if (totalAmountEl) totalAmountEl.textContent = '$' + totalAmount.toFixed(0);
            if (totalCbmEl) totalCbmEl.textContent = totalCBM.toFixed(0);
            if (totalItemsEl) totalItemsEl.textContent = String(visibleRows.length);
        }

        // Filter to show only R2S stage on page load
        setTimeout(() => {
            filterByR2SStage();
        }, 100);

    });
</script>
<script>
    // After main script sets window.filterByR2SStage (DOMContentLoaded), re-apply R2S filter
    function filterByR2SStageOnLoad() {
        if (typeof window.filterByR2SStage === 'function') {
            window.filterByR2SStage();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(filterByR2SStageOnLoad, 150);
        });
    } else {
        setTimeout(filterByR2SStageOnLoad, 150);
    }

</script>

<!-- Add MIP-style filters and counts JavaScript -->
<script>
    // Global functions for R2S stage (adapted from MIP page)
    function calculateTotalCBM() {
        let totalCBM = 0;
        document.querySelectorAll('table.wide-table tbody tr').forEach(row => {
            const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
            const stageSelect = row.querySelector('.editable-select-stage');
            const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
            const rowStage = rowStageSelect || rowStageAttr;
            if (rowStage !== 'r2s') return;
            if (row.style.display !== "none") {
                // Total CBM is stored in td[data-column="19"] which is qty * CBM
                const totalCbmCell = row.querySelector('td[data-column="19"]');
                if (totalCbmCell) {
                    const totalCbmText = totalCbmCell.textContent.trim();
                    // Remove commas and parse
                    if (totalCbmText !== '' && totalCbmText !== 'N/A') {
                        const value = parseFloat(totalCbmText.replace(/,/g, ''));
                        if (!isNaN(value)) totalCBM += value;
                    }
                }
            }
        });
        const totalCbmEl = document.getElementById('total-cbm');
        if (totalCbmEl) totalCbmEl.textContent = totalCBM.toFixed(0);
    }

    function calculateTotalAmount() {
        let totalAmount = 0;
        document.querySelectorAll('table.wide-table tbody tr').forEach(row => {
            const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
            const stageSelect = row.querySelector('.editable-select-stage');
            const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
            const rowStage = rowStageSelect || rowStageAttr;
            if (rowStage !== 'r2s') return;
            if (row.style.display !== "none") {
                const td = row.querySelector('.total-value');
                if (td) {
                    const value = parseFloat(td.textContent.trim());
                    if (!isNaN(value)) totalAmount += value;
                }
            }
        });
        const totalAmountEl = document.getElementById('total-amount');
        if (totalAmountEl) totalAmountEl.textContent = '$' + totalAmount.toFixed(0);
    }

    function calculateTotalOrderItems() {
        let totalItems = 0;
        document.querySelectorAll('table.wide-table tbody tr').forEach(row => {
            const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
            const stageSelect = row.querySelector('.editable-select-stage');
            const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
            const rowStage = rowStageSelect || rowStageAttr;
            if (rowStage !== 'r2s') return;
            if (row.style.display !== "none") {
                totalItems++;
            }
        });
        const totalItemsEl = document.getElementById('total-order-items');
        if (totalItemsEl) totalItemsEl.textContent = totalItems;
    }

    function updateFollowSupplierCount() {
        const followSupplierSpan = document.getElementById("followSupplierCount");
        if (!followSupplierSpan) return;
        const supplierSet = new Set();
        const allRows = document.querySelectorAll("table.wide-table tbody tr");
        allRows.forEach(row => {
            const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
            const stageSelect = row.querySelector('.editable-select-stage');
            const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
            const rowStage = rowStageSelect || rowStageAttr;
            if (rowStage !== 'r2s') return;
            if (row.style.display === 'none') return;
            const qtyCell = row.querySelector('td[data-column="4"]');
            let qty = 0;
            if (qtyCell) {
                const qtyInput = qtyCell.querySelector('input');
                if (qtyInput) {
                    qty = parseFloat(qtyInput.value) || 0;
                } else {
                    qty = parseFloat(qtyCell.textContent.trim()) || parseFloat(qtyCell.getAttribute('data-qty')) || 0;
                }
            }
            if (qty > 0) {
                const supplierName = typeof window.r2sRowAssignedSupplier === 'function' ? window.r2sRowAssignedSupplier(row) : '';
                if (supplierName && supplierName !== '' && supplierName !== 'supplier') {
                    supplierSet.add(supplierName);
                }
            }
        });
        followSupplierSpan.textContent = supplierSet.size;
    }

    function updateSupplierCounts() {
        const optionsContainer = document.getElementById('customSelectOptions');
        if (!optionsContainer) return;
        const allOptions = Array.from(optionsContainer.querySelectorAll('.custom-select-option'));
        const allRows = document.querySelectorAll('tbody tr');
        const supplierCounts = {};
        allRows.forEach(row => {
            const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
            const stageSelect = row.querySelector('.editable-select-stage');
            const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
            const rowStage = rowStageSelect || rowStageAttr;
            if (rowStage !== 'r2s') return;
            const supplierName = typeof window.r2sRowAssignedSupplier === 'function' ? window.r2sRowAssignedSupplier(row) : '';
            if (supplierName && supplierName !== '' && supplierName !== 'supplier') {
                supplierCounts[supplierName] = (supplierCounts[supplierName] || 0) + 1;
            }
        });
        allOptions.forEach(option => {
            const optionText = option.textContent.trim();
            const supplierName = optionText.replace(/\s*\(\d+\)\s*$/, '');
            if (optionText === 'All supplier' || optionText === 'Supplier' || option.getAttribute('data-value') === '__all_suppliers__') {
                return;
            }
            if (supplierCounts[supplierName] !== undefined) {
                option.textContent = `${supplierName} (${supplierCounts[supplierName]})`;
                option.setAttribute('data-count', supplierCounts[supplierName]);
            } else {
                option.textContent = supplierName;
                option.setAttribute('data-count', '0');
            }
        });
    }

    // Supplier remarks functions
    function getSupplierRemarks(supplier) {
        const remarksKey = `supplier_remarks_r2s_${supplier}`;
        const remarksJson = localStorage.getItem(remarksKey);
        return remarksJson ? JSON.parse(remarksJson) : [];
    }

    function saveSupplierRemark(supplier, remark) {
        if (!supplier || !remark.trim()) {
            alert('Please enter a follow-up note.');
            return;
        }
        const remarksKey = `supplier_remarks_r2s_${supplier}`;
        const remarks = getSupplierRemarks(supplier);
        const newRemark = {
            id: Date.now(),
            text: remark.trim(),
            timestamp: new Date().toLocaleString()
        };
        remarks.unshift(newRemark);
        localStorage.setItem(remarksKey, JSON.stringify(remarks));
        document.getElementById('supplier-remark-input').value = '';
        loadSupplierRemarks(supplier);
        alert('Remark saved successfully!');
    }

    function loadSupplierRemarks(supplier) {
        const remarksList = document.getElementById('remarksList');
        if (!remarksList) return;
        const remarks = getSupplierRemarks(supplier);
        if (remarks.length === 0) {
            remarksList.innerHTML = '<p class="text-muted mb-0">No follow-up history yet.</p>';
            return;
        }
        let html = '<div class="list-group">';
        remarks.forEach(remark => {
            html += `
                <div class="list-group-item mb-2" style="border-left: 4px solid #28a745;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <p class="mb-1">${remark.text}</p>
                            <small class="text-muted">${remark.timestamp}</small>
                        </div>
                        <button class="btn btn-sm btn-outline-danger delete-remark-btn" data-id="${remark.id}" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        remarksList.innerHTML = html;
        remarksList.querySelectorAll('.delete-remark-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                deleteSupplierRemark(supplier, parseInt(this.getAttribute('data-id')));
            });
        });
    }

    function deleteSupplierRemark(supplier, remarkId) {
        const remarksKey = `supplier_remarks_r2s_${supplier}`;
        const remarks = getSupplierRemarks(supplier);
        const filteredRemarks = remarks.filter(r => r.id !== remarkId);
        localStorage.setItem(remarksKey, JSON.stringify(filteredRemarks));
        loadSupplierRemarks(supplier);
    }

    // Play button functionality for R2S
    document.addEventListener("DOMContentLoaded", function () {
        const rows = document.querySelectorAll("table.wide-table tbody tr");
        const suppliers = [];
        let supplierIndex = 0;

        // Collect unique suppliers (only from R2S stage rows)
        rows.forEach(row => {
            const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
            const stageSelect = row.querySelector('.editable-select-stage');
            const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
            const rowStage = rowStageSelect || rowStageAttr;
            if (rowStage !== 'r2s') return;
            const supplierName = typeof window.r2sRowAssignedSupplier === 'function' ? window.r2sRowAssignedSupplier(row) : '';
            if (supplierName && supplierName !== '' && supplierName !== 'supplier' && !suppliers.includes(supplierName)) {
                suppliers.push(supplierName);
            }
        });

        function showSupplierRows(supplier) {
            window.r2sSupplierNavLock = supplier || null;
            if (typeof window.filterByR2SStage === 'function') {
                window.filterByR2SStage();
            }

            const supplierBadgeContainer = document.getElementById("supplier-badge-container");
            const supplierBadge = document.getElementById("current-supplier");
            const supplierBadgeVr = document.getElementById("supplier-badge-vr");
            if (supplierBadgeContainer) supplierBadgeContainer.style.display = "block";
            if (supplierBadgeVr) supplierBadgeVr.style.display = "block";
            if (supplierBadge) supplierBadge.textContent = supplier || "-";

            const modalSupplierName = document.getElementById("modal-supplier-name");
            if (modalSupplierName) modalSupplierName.textContent = supplier || "-";
            
            if (typeof loadSupplierRemarks === 'function') {
                loadSupplierRemarks(supplier);
            }

            // Update counts after a small delay to ensure DOM is updated
            setTimeout(() => {
                calculateTotalCBM();
                calculateTotalAmount();
                calculateTotalOrderItems();
                updateFollowSupplierCount();
            }, 100);
        }

        function refreshSupplierList() {
            suppliers.length = 0;
            rows.forEach(row => {
                const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
                const stageSelect = row.querySelector('.editable-select-stage');
                const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
                const rowStage = rowStageSelect || rowStageAttr;
                if (rowStage !== 'r2s') return;
                const supplierName = typeof window.r2sRowAssignedSupplier === 'function' ? window.r2sRowAssignedSupplier(row) : '';
                if (supplierName && supplierName !== '' && supplierName !== 'supplier' && !suppliers.includes(supplierName)) {
                    suppliers.push(supplierName);
                }
            });
        }

        function playNextSupplier() {
            supplierIndex = (supplierIndex + 1) % suppliers.length;
            showSupplierRows(suppliers[supplierIndex]);
            
            // Ensure counts are updated after a small delay
            setTimeout(() => {
                calculateTotalCBM();
                calculateTotalAmount();
                calculateTotalOrderItems();
                updateFollowSupplierCount();
            }, 100);
        }

        // Play button event delegation
        document.addEventListener("click", function(e) {
            let targetElement = e.target;
            if (targetElement.id === "play-auto" || (targetElement.closest("#play-auto") && targetElement.tagName === 'I')) {
                e.preventDefault();
                e.stopPropagation();
                refreshSupplierList();
                if (suppliers.length === 0) {
                    alert("No suppliers found. Please add suppliers to rows.");
                    return;
                }
                document.getElementById("play-auto").style.display = "none";
                document.getElementById("play-pause").style.display = "inline-block";
                supplierIndex = 0;
                showSupplierRows(suppliers[supplierIndex]);
            } else if (targetElement.id === "play-pause" || (targetElement.closest("#play-pause") && targetElement.tagName === 'I')) {
                e.preventDefault();
                e.stopPropagation();
                document.getElementById("play-pause").style.display = "none";
                document.getElementById("play-auto").style.display = "inline-block";
                const supplierBadgeContainer = document.getElementById("supplier-badge-container");
                const supplierBadgeVr = document.getElementById("supplier-badge-vr");
                if (supplierBadgeContainer) supplierBadgeContainer.style.display = "none";
                if (supplierBadgeVr) supplierBadgeVr.style.display = "none";
                window.r2sSupplierNavLock = null;
                if (typeof window.filterByR2SStage === 'function') {
                    window.filterByR2SStage();
                }
                const title = document.getElementById("current-supplier");
                if (title) title.textContent = "-";
                calculateTotalCBM();
                calculateTotalAmount();
                calculateTotalOrderItems();
                updateFollowSupplierCount();
            } else if (targetElement.id === "play-forward" || (targetElement.closest("#play-forward") && targetElement.tagName === 'I')) {
                e.preventDefault();
                e.stopPropagation();
                if (suppliers.length === 0) refreshSupplierList();
                if (suppliers.length > 0) playNextSupplier();
            } else if (targetElement.id === "play-backward" || (targetElement.closest("#play-backward") && targetElement.tagName === 'I')) {
                e.preventDefault();
                e.stopPropagation();
                if (suppliers.length === 0) refreshSupplierList();
                if (suppliers.length > 0) {
                    supplierIndex = (supplierIndex - 1 + suppliers.length) % suppliers.length;
                    showSupplierRows(suppliers[supplierIndex]);
                    
                    // Ensure counts are updated after a small delay
                    setTimeout(() => {
                        calculateTotalCBM();
                        calculateTotalAmount();
                        calculateTotalOrderItems();
                        updateFollowSupplierCount();
                    }, 100);
                }
            }
        });

        // Supplier remarks button
        const remarksBtn = document.getElementById('supplier-remarks-btn');
        if (remarksBtn) {
            remarksBtn.addEventListener('click', function() {
                const supplierBadge = document.getElementById('current-supplier');
                const currentSupplier = supplierBadge ? supplierBadge.textContent.trim() : '';
                if (!currentSupplier || currentSupplier === '-') {
                    alert('Please select a supplier first using the play button.');
                    return;
                }
                const modalSupplierName = document.getElementById('modal-supplier-name');
                if (modalSupplierName) modalSupplierName.textContent = currentSupplier;
                loadSupplierRemarks(currentSupplier);
                const modalElement = document.getElementById('supplierRemarksModal');
                if (modalElement) {
                    const modal = new bootstrap.Modal(modalElement);
                    modal.show();
                }
            });
        }

        // Save remark button
        const saveRemarkBtn = document.getElementById('saveRemarkBtn');
        if (saveRemarkBtn) {
            saveRemarkBtn.addEventListener('click', function() {
                const supplierBadge = document.getElementById('current-supplier');
                const currentSupplier = supplierBadge ? supplierBadge.textContent.trim() : '';
                const remarkInput = document.getElementById('supplier-remark-input');
                if (remarkInput && currentSupplier && currentSupplier !== '-') {
                    saveSupplierRemark(currentSupplier, remarkInput.value);
                }
            });
        }

        // Initialize counts on page load
        setTimeout(() => {
            calculateTotalCBM();
            calculateTotalAmount();
            calculateTotalOrderItems();
            updateFollowSupplierCount();
            updateSupplierCounts();
        }, 500);
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
(function () {
    const r2sProductValues = {!! $transitProductValuesMap !!};

    document.getElementById('r2s-add-tab-btn')?.addEventListener('click', async function () {
        const tabName = prompt('Enter new container name:');
        if (!tabName || tabName.trim() === '') {
            alert('Tab name is required.');
            return;
        }
        const response = await fetch('/transit-container/add-tab', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
            },
            body: JSON.stringify({ tab_name: tabName.trim() })
        });
        const result = await response.json();
        if (!result.success) {
            alert(result.message || 'Failed to create tab.');
            return;
        }
        location.reload();
    });

    function r2sInitFirstRowSelect2() {
        const $modal = $('#r2sTransitAddItemModal');
        const $sel = $modal.find('#r2sProductRowsWrapper .r2s-product-row:first .r2s-sku-select');
        if ($sel.length && !$sel.hasClass('select2-hidden-accessible')) {
            $sel.select2({ width: '100%', dropdownParent: $modal });
        }
    }

    $('#r2sTransitAddItemModal').on('shown.bs.modal', function () {
        r2sInitFirstRowSelect2();
    });

    document.getElementById('r2sAddItemRowBtn')?.addEventListener('click', function () {
        const tpl = document.getElementById('r2s-product-row-template');
        if (!tpl || !tpl.content) return;
        const node = tpl.content.cloneNode(true);
        document.getElementById('r2sProductRowsWrapper').appendChild(node);
        const $modal = $('#r2sTransitAddItemModal');
        const $last = $('#r2sProductRowsWrapper .r2s-product-row:last .r2s-sku-select');
        $last.select2({ width: '100%', dropdownParent: $modal });
        r2sBindDeleteBtns();
    });

    function r2sBindDeleteBtns() {
        const wrapper = document.getElementById('r2sProductRowsWrapper');
        if (!wrapper) return;
        wrapper.querySelectorAll('.r2s-delete-product-row-btn').forEach(function (btn) {
            btn.onclick = function () {
                const rows = wrapper.querySelectorAll('.r2s-product-row');
                if (rows.length > 1) {
                    const row = btn.closest('.r2s-product-row');
                    const $sku = $(row).find('.r2s-sku-select');
                    if ($sku.hasClass('select2-hidden-accessible')) {
                        $sku.select2('destroy');
                    }
                    row.remove();
                } else {
                    alert('At least one row is required.');
                }
            };
        });
    }
    r2sBindDeleteBtns();

    $(document).on('change', '.r2s-sku-select', function () {
        let selectedSku = $(this).val();
        if (!selectedSku) return;
        selectedSku = selectedSku.toUpperCase().trim().replace(/\s+/g, ' ');
        const row = $(this).closest('.r2s-product-row');
        const values = r2sProductValues[selectedSku];
        if (!values || typeof values !== 'object') {
            row.find('input[name="cbm[]"]').val('');
            row.find('input[name="rate[]"]').val('');
            row.find('input[name="unit[]"]').val('');
            return;
        }
        row.find('input[name="cbm[]"]').val(values.cbm ?? '');
        row.find('input[name="rate[]"]').val(values.cp ?? '');
        row.find('input[name="unit[]"]').val(values.unit ? String(values.unit).toLowerCase().trim() : '');
    });

    // Supplier Summary: visible #readyToShipTable rows only (DOM), grouped by supplier
    $(function () {
        var $tbody = $('#r2s-supplier-summary-tbody');
        var $tfoot = $('#r2s-supplier-summary-tfoot');
        var $modalEl = document.getElementById('r2sSupplierSummaryModal');
        var lastCsvLines = [];

        function r2sEffectiveQty($tr) {
            var recRaw = String($tr.find('td[data-column="20"] input').val() || '').trim();
            var orRaw = String($tr.find('td[data-column="4"] input').val() || '').trim();
            var q;
            if (recRaw !== '') {
                q = parseFloat(recRaw.replace(/,/g, ''));
                if (isFinite(q)) return q;
            }
            if (orRaw !== '') {
                q = parseFloat(orRaw.replace(/,/g, ''));
                if (isFinite(q)) return q;
            }
            return NaN;
        }

        function r2sParseCbmCell($tr) {
            var t = $tr.find('td[data-column="6"]').text().replace(/\s/g, '').trim();
            if (!t || /^n\/a$/i.test(t)) return 0;
            var n = parseFloat(String(t).replace(/,/g, ''));
            return isFinite(n) ? n : 0;
        }

        function r2sParseTotalCbmCell($tr) {
            var t = $tr.find('td[data-column="19"]').text().trim();
            var n = parseFloat(String(t).replace(/,/g, ''));
            return isFinite(n) ? n : 0;
        }

        function r2sSupplierFromRow($tr) {
            var v = String($tr.attr('data-r2s-supplier') || '').trim();
            return v || 'Unknown';
        }

        function csvEscape(cell) {
            var s = String(cell != null ? cell : '');
            if (/[",\r\n]/.test(s)) return '"' + s.replace(/"/g, '""') + '"';
            return s;
        }

        function fmtQty(n) {
            if (!isFinite(n)) return '0';
            if (Math.abs(n - Math.round(n)) < 1e-9) return String(Math.round(n));
            return n.toFixed(2);
        }

        function buildSupplierSummary() {
            var groups = {};
            var $visible = $('#readyToShipTable tbody tr.stage-row').filter(function () {
                var $r = $(this);
                if ($r.css('display') === 'none') return false;
                return $r.is(':visible');
            });

            $visible.each(function () {
                var $tr = $(this);
                var qty = r2sEffectiveQty($tr);
                if (!isFinite(qty) || qty === 0) return;

                var supplier = r2sSupplierFromRow($tr);
                var cbm = r2sParseCbmCell($tr);
                var totalCbm = r2sParseTotalCbmCell($tr);

                if (!groups[supplier]) {
                    groups[supplier] = { totalQty: 0, totalCbm: 0, totalTotalCbm: 0 };
                }
                groups[supplier].totalQty += qty;
                groups[supplier].totalCbm += cbm;
                groups[supplier].totalTotalCbm += totalCbm;
            });

            var names = Object.keys(groups).sort(function (a, b) {
                return a.toLowerCase().localeCompare(b.toLowerCase());
            });

            $tbody.empty();
            $tfoot.empty();

            var gQty = 0;
            var gCbm = 0;
            var gTot = 0;
            lastCsvLines = [['Supplier', 'Total QTY', 'Total CBM', 'Total TOTAL CBM']];

            if (names.length === 0) {
                $tbody.append('<tr><td colspan="4" class="text-muted text-center py-3">No rows with quantity in the current view.</td></tr>');
                return;
            }

            names.forEach(function (name) {
                var g = groups[name];
                gQty += g.totalQty;
                gCbm += g.totalCbm;
                gTot += g.totalTotalCbm;
                var $tr = $('<tr></tr>');
                $tr.append($('<td></td>').text(name));
                $tr.append($('<td class="text-end"></td>').text(fmtQty(g.totalQty)));
                $tr.append($('<td class="text-end"></td>').text(g.totalCbm.toFixed(4)));
                $tr.append($('<td class="text-end"></td>').text(g.totalTotalCbm.toFixed(2)));
                $tbody.append($tr);
                lastCsvLines.push([name, fmtQty(g.totalQty), g.totalCbm.toFixed(4), g.totalTotalCbm.toFixed(2)]);
            });

            lastCsvLines.push(['Grand Total', fmtQty(gQty), gCbm.toFixed(4), gTot.toFixed(2)]);

            var $ftr = $('<tr></tr>');
            $ftr.append($('<td></td>').text('Grand Total'));
            $ftr.append($('<td class="text-end"></td>').text(fmtQty(gQty)));
            $ftr.append($('<td class="text-end"></td>').text(gCbm.toFixed(4)));
            $ftr.append($('<td class="text-end"></td>').text(gTot.toFixed(2)));
            $tfoot.append($ftr);
        }

        $('#r2s-supplier-summary-btn').on('click', function () {
            buildSupplierSummary();
            if ($modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance($modalEl).show();
            } else if ($modalEl && $.fn.modal) {
                $($modalEl).modal('show');
            }
        });

        $('#r2s-supplier-summary-csv-btn').on('click', function () {
            if (!lastCsvLines.length || lastCsvLines.length <= 1) buildSupplierSummary();
            if (lastCsvLines.length <= 1) return;
            var csv = lastCsvLines.map(function (row) {
                return row.map(csvEscape).join(',');
            }).join('\r\n');
            var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'supplier-summary-' + new Date().toISOString().slice(0, 10) + '.csv';
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        });
    });
})();
</script>
@endsection