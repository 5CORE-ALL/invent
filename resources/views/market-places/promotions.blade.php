@extends('layouts.vertical', ['title' => 'Promotions', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .promo-channel-logo {
            width: 36px;
            height: 36px;
            object-fit: contain;
            border-radius: 6px;
            background: #fff;
            border: 1px solid #e9ecef;
            padding: 2px;
            display: inline-block;
            vertical-align: middle;
        }
        .promo-channel-logo-placeholder {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 6px;
            background: #f8f9fa;
            border: 1px dashed #ced4da;
            color: #adb5bd;
            font-size: 14px;
            vertical-align: middle;
        }
        .promo-channel-logo-link {
            display: inline-flex;
            line-height: 1;
        }
        .promo-channel-name {
            font-weight: 600;
            color: #1e293b;
        }
        .promo-status-chip {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 12px;
            background: #dcfce7;
            color: #15803d;
        }
        #promo-filter-bar {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 14px;
        }
        #promo-filter-bar .promo-filter-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 4px;
        }
        #promotions-table .tabulator-row .tabulator-cell {
            vertical-align: middle;
        }
        .promo-dyn-text {
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #334155;
        }
        .promo-notes-text {
            display: block;
            white-space: normal;
            line-height: 1.3;
            color: #334155;
        }
        .promo-empty {
            color: #cbd5e1;
        }
        .promo-tick, .promo-cross {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            font-size: 14px;
        }
        .promo-tick {
            background: #dcfce7;
            color: #16a34a;
        }
        .promo-cross {
            background: #fee2e2;
            color: #dc2626;
        }
        .promo-rule-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border: 0;
            border-radius: 8px;
            background: #5bbcc4;
            color: #fff;
            font-size: 15px;
            cursor: pointer;
            line-height: 1;
        }
        .promo-rule-btn:hover:not(.is-disabled) {
            background: #3da5ae;
        }
        .promo-rule-btn.is-disabled {
            background: #f1f5f9;
            color: #cbd5e1;
            cursor: not-allowed;
        }
        .promo-edit-btn {
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #0d6efd;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            cursor: pointer;
            line-height: 1;
        }
        .promo-edit-btn:hover {
            background: #0d6efd;
            color: #fff;
            border-color: #0d6efd;
        }

        /* ---- Compact layout: everything fits on one screen ---- */
        #promotions-table .tabulator-header .tabulator-col {
            font-size: 12px;
        }
        #promotions-table .tabulator-header .tabulator-col .tabulator-col-content {
            padding: 4px 6px;
        }
        #promotions-table .tabulator-row .tabulator-cell {
            padding: 2px 6px;
            font-size: 12px;
        }
        #promotions-table .promo-channel-logo,
        #promotions-table .promo-channel-logo-placeholder {
            width: 24px;
            height: 24px;
            padding: 1px;
            font-size: 11px;
        }
        #promotions-table .promo-status-chip {
            padding: 1px 8px;
            font-size: 11px;
        }
        #promotions-table .promo-tick,
        #promotions-table .promo-cross {
            width: 20px;
            height: 20px;
            font-size: 11px;
        }
        #promotions-table .promo-rule-btn,
        #promotions-table .promo-edit-btn {
            width: 24px;
            height: 24px;
            font-size: 12px;
            border-radius: 6px;
        }
        #promotions-table .promo-notes-text {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.2;
        }
        /* Frozen Img + Channels columns: keep them opaque with a divider */
        #promotions-table .tabulator-frozen {
            background: #fff;
            z-index: 3;
        }
        #promotions-table .tabulator-header .tabulator-frozen {
            background: #f8f9fa;
        }
        #promotions-table .tabulator-row.tabulator-row-even .tabulator-frozen {
            background: #fafafa;
        }
        #promotions-table .tabulator-frozen.tabulator-frozen-right,
        #promotions-table .promo-frozen-last {
            border-right: 2px solid #cbd5e1 !important;
        }

        /* Prmt% column — same green button + applied/total badge as /ebay-tabulator-view */
        .promo-prmt-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 1px 8px;
            border: 1px solid #198754;
            border-radius: 6px;
            background: #198754;
            color: #fff;
            font-size: 11px;
            font-weight: 600;
            line-height: 1.5;
            cursor: pointer;
            white-space: nowrap;
        }
        .promo-prmt-btn:hover {
            background: #157347;
            border-color: #146c43;
        }
        .promo-prmt-btn .badge {
            font-size: 10px;
            font-weight: 600;
        }
        .promo-prmt-btn.is-disabled {
            background: #f1f5f9;
            border-color: #e2e8f0;
            color: #cbd5e1;
            cursor: not-allowed;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Promotions',
        'sub_title'  => 'Active Channels',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <span class="badge bg-primary fs-6 p-2" style="font-weight:bold;" title="Channels in current filter">
                            CHANNELS: <span id="promo-channel-count">0</span>
                        </span>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div id="promo-filter-bar" class="mb-2">
                        <div class="d-flex flex-wrap align-items-end gap-3">
                            <div class="flex-grow-1" style="min-width:200px;">
                                <label class="promo-filter-label" for="promo-search">Search</label>
                                <input type="text" id="promo-search" class="form-control form-control-sm" placeholder="Search by Channel...">
                            </div>
                            <div class="d-flex align-items-end gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="promo-filter-clear">Clear</button>
                                <button type="button" class="btn btn-sm btn-success" id="promo-add-column-btn">
                                    <i class="fas fa-plus me-1"></i> Add Column
                                </button>
                                @include('partials.ace-buttons', ['acePage' => 'active_channels'])
                            </div>
                        </div>
                    </div>
                    <div id="promotions-table" style="height: calc(100vh - 260px);"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- EL30 Dil % Coupons modals (same as the eBay pages). The buttons stay hidden;
         the Rule column's coupon icon triggers them. --}}
    <div class="d-none">
        @foreach (['ebay1', 'ebay2', 'ebay3'] as $promoEbayKey)
            @include('partials.ebay-zero-sold-coupon', ['ebayZeroSoldPart' => 'button', 'ebayZeroSoldChannel' => $promoEbayKey])
        @endforeach
        @include('partials.amazon-dil-price-coupon', ['amazonCouponPart' => 'button'])
    </div>
    @foreach (['ebay1', 'ebay2', 'ebay3'] as $promoEbayKey)
        @include('partials.ebay-zero-sold-coupon', ['ebayZeroSoldPart' => 'modal', 'ebayZeroSoldChannel' => $promoEbayKey, 'ebayZeroSoldSnapshot' => true])
    @endforeach
    {{-- Amazon: Dil % x Price coupon rule (reuses the .el30cpn-* styles from the eBay modals above) --}}
    @include('partials.amazon-dil-price-coupon', ['amazonCouponPart' => 'modal'])

    {{-- Prmt% modal: shared Dil vs PRMT rule (same store/endpoints as the Prmt% button on the eBay pages) --}}
    @include('partials.dil-prmt-modal-style')
    <div class="modal fade dpm" id="promo-prmt-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6">
                        <i class="fas fa-sliders-h me-1"></i> Dil vs PRMT
                        <span class="badge bg-light text-dark ms-1" id="promo-prmt-modal-channel"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2">
                    <p class="small text-muted mb-2">
                        Shared Dil vs PRMT for <strong>all marketplaces</strong>:
                        <strong>0–3%</strong> … <strong>21–24%</strong>, then <strong>24–25%</strong>.
                        Changing the first row sets the following slabs to &minus;1 each (minimum 0).
                        <strong>Save</strong> stores the slabs for every marketplace.
                        <strong>Save &amp; Apply</strong> also applies PRMT% to every SKU of this channel
                        (eBay: pushes the changed % as markdown sales; Amazon: updates S PRC, which the 4 AM / 8 PM job pushes).
                        If <strong>INV = 0</strong>, PRMT% is <strong>0</strong>.
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0" id="promo-prmt-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:52%;">Dil%</th>
                                    <th style="width:18%;" class="text-center" title="SKUs currently getting this row's PRMT %">Count</th>
                                    <th style="width:30%;" class="text-end">PRMT %</th>
                                </tr>
                            </thead>
                            <tbody id="promo-prmt-tbody"></tbody>
                        </table>
                    </div>
                    <div class="mt-2 d-flex align-items-center gap-2 flex-wrap">
                        <button type="button" class="btn btn-sm btn-outline-success" id="promo-prmt-add-row">
                            <i class="fas fa-plus me-1"></i>Add more row
                        </button>
                        <span class="badge bg-secondary" id="promo-prmt-row-count">0 rows</span>
                        <span class="small text-muted" id="promo-prmt-count-total"></span>
                        <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="promo-prmt-restore" title="Bring back deleted rows">
                            <i class="fas fa-undo me-1"></i>Restore <span id="promo-prmt-deleted-count">0</span> deleted
                        </button>
                    </div>
                    <div class="small text-muted mt-2" id="promo-prmt-status"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm" id="promo-prmt-save"
                        title="Store the Dil vs PRMT slabs for all marketplaces. SKUs are not changed until you Apply (or the 10-minute auto job runs).">
                        <i class="fas fa-save me-1"></i>Save
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" id="promo-prmt-save-apply"
                        title="Save the slabs, then apply PRMT% to every SKU of this channel. eBay: listing Dil → slab, changed % pushed as markdown sales. Amazon: SKU Dil → slab → S PRC (pushed by the 4 AM / 8 PM job).">
                        <i class="fas fa-bolt me-1"></i>Save &amp; Apply
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Add Column modal --}}
    <div class="modal fade" id="promo-add-column-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Column</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label fw-semibold" for="promo-new-column-name">Column header name</label>
                    <input type="text" id="promo-new-column-name" class="form-control" maxlength="120" placeholder="e.g. Coupon, Discount %, Deal Type">
                    <div class="text-danger small mt-2 d-none" id="promo-add-column-error"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="promo-add-column-save">Add Column</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Edit row modal (pen button): edits every created column + Notes and Remarks --}}
    <div class="modal fade" id="promo-edit-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit — <span id="promo-edit-channel"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="promo-edit-fields"></div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold" for="promo-edit-notes">Notes and Remarks</label>
                        <textarea id="promo-edit-notes" class="form-control" rows="4" maxlength="5000"></textarea>
                    </div>
                    <div class="text-danger small mt-2 d-none" id="promo-edit-error"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="promo-edit-save">Save</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
(function () {
    let table = null;

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function updateBadges() {
        const visible = table ? table.getData('active') : [];
        $('#promo-channel-count').text(visible.length.toLocaleString());
    }

    function applyFilters() {
        if (!table) return;
        const q = ($('#promo-search').val() || '').trim().toLowerCase();

        if (!q) {
            table.clearFilter();
        } else {
            table.setFilter(function (data) {
                return String(data.channel || '').toLowerCase().includes(q)
                    || String(data.alias || '').toLowerCase().includes(q);
            });
        }
        updateBadges();
    }

    let dynamicColumns = [];
    // Prmt% badge counts per channel key: { ebay1: {applied, total}, ... }
    let prmtSummary = {};
    // Shared Dil vs PRMT rules (same list the eBay pages edit) + modal state
    let prmtRules = [];
    let prmtModal = null;
    // Dil of every SKU with INV > 0 for the channel in the modal, cached per channel key
    const prmtDilCache = {};
    let prmtDilChannel = null;
    const csrf = $('meta[name="csrf-token"]').attr('content');

    // First three columns: active channels (Img | Channels | Status)
    const startColumns = [
            {
                title: 'Img',
                field: 'image',
                width: 56,
                frozen: true,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: false,
                formatter: function (cell) {
                    const row = cell.getRow().getData();
                    const logo = cell.getValue();
                    const channel = escapeHtml(row.channel || '');
                    const sellerLink = (row.seller_link || '').trim();

                    const imgHtml = logo
                        ? `<img src="/storage/${escapeHtml(logo)}" alt="${channel}" class="promo-channel-logo" onerror="this.style.display='none'"/>`
                        : `<span class="promo-channel-logo-placeholder" title="No logo"><i class="fas fa-image"></i></span>`;

                    if (sellerLink) {
                        return `<a href="${escapeHtml(sellerLink)}" target="_blank" rel="noopener noreferrer" class="promo-channel-logo-link" title="Open seller page">${imgHtml}</a>`;
                    }
                    return imgHtml;
                },
            },
            {
                title: 'Channels',
                field: 'channel',
                width: 190,
                frozen: true,
                cssClass: 'promo-frozen-last',
                formatter: function (cell) {
                    return `<span class="promo-channel-name">${escapeHtml(cell.getValue() || '')}</span>`;
                },
            },
            {
                title: 'Status',
                field: 'status',
                width: 90,
                hozAlign: 'center',
                headerHozAlign: 'center',
                formatter: function (cell) {
                    return `<span class="promo-status-chip">${escapeHtml(cell.getValue() || 'Active')}</span>`;
                },
            },
    ];

    function textCell(value, cls) {
        const v = String(value ?? '').trim();
        if (!v) return '<span class="promo-empty">—</span>';
        return `<span class="${cls}" title="${escapeHtml(v)}">${escapeHtml(v)}</span>`;
    }

    // A column whose header contains "coupon" is auto-filled with ✔ / ✖ per marketplace.
    function isCouponColumn(col) {
        return /coupon/i.test(String(col && col.name || ''));
    }

    // Manual override ('yes'/'no') wins; otherwise the server's per-channel default.
    // Returns true / false / null (unknown).
    function effectiveCoupon(rowData, col) {
        const manual = String(rowData['dyn_' + col.id] || '').trim().toLowerCase();
        if (manual === 'yes') return true;
        if (manual === 'no') return false;
        return rowData.coupon_allowed === true ? true : (rowData.coupon_allowed === false ? false : null);
    }

    function couponCell(state) {
        if (state === true) {
            return '<span class="promo-tick" title="Coupons can be given"><i class="fas fa-check"></i></span>';
        }
        if (state === false) {
            return '<span class="promo-cross" title="Coupons cannot be given"><i class="fas fa-times"></i></span>';
        }
        return '<span class="promo-empty" title="Unknown">—</span>';
    }

    // Img | Channels | Status | <created columns…> | Notes and Remarks | Edit (pen)
    function buildColumns() {
        const created = dynamicColumns.map(function (col) {
            if (isCouponColumn(col)) {
                return {
                    title: col.name,
                    field: 'dyn_' + col.id,
                    minWidth: 90,
                    hozAlign: 'center',
                    headerHozAlign: 'center',
                    headerSort: true,
                    sorter: function (a, b, aRow, bRow) {
                        const rank = function (s) { return s === true ? 2 : (s === false ? 1 : 0); };
                        return rank(effectiveCoupon(aRow.getData(), col)) - rank(effectiveCoupon(bRow.getData(), col));
                    },
                    formatter: function (cell) {
                        return couponCell(effectiveCoupon(cell.getRow().getData(), col));
                    },
                };
            }
            return {
                title: col.name,
                field: 'dyn_' + col.id,
                minWidth: 100,
                headerSort: true,
                formatter: function (cell) {
                    return textCell(cell.getValue(), 'promo-dyn-text');
                },
            };
        });

        const notes = {
            title: 'Notes and Remarks',
            field: 'notes_remarks',
            minWidth: 160,
            headerSort: false,
            formatter: function (cell) {
                return textCell(cell.getValue(), 'promo-notes-text');
            },
        };

        const edit = {
            title: 'Edit',
            field: '_edit',
            width: 56,
            hozAlign: 'center',
            headerHozAlign: 'center',
            headerSort: false,
            formatter: function () {
                return '<button type="button" class="promo-edit-btn" title="Edit"><i class="fas fa-pen"></i></button>';
            },
            cellClick: function (e, cell) {
                e.stopPropagation();
                openEditModal(cell.getRow().getData());
            },
        };

        // "Rule": coupon icon that opens the eBay "EL30 Dil % Coupons" modal.
        const rule = {
            title: 'Rule',
            field: 'coupon_rule',
            width: 60,
            hozAlign: 'center',
            headerHozAlign: 'center',
            headerSort: false,
            formatter: function (cell) {
                const key = cell.getValue();
                if (!key) {
                    return '<span class="promo-rule-btn is-disabled" title="No coupon rule for this channel"><i class="fas fa-ticket-alt"></i></span>';
                }
                const tip = key === 'amazon' ? 'Amazon Dil % × Price coupon rule' : 'EL30 Dil % Coupons rule';
                return '<button type="button" class="promo-rule-btn" title="' + tip + '"><i class="fas fa-ticket-alt"></i></button>';
            },
            cellClick: function (e, cell) {
                e.stopPropagation();
                const key = cell.getValue();
                if (key) {
                    // eBay 1/2/3 → EL30 Dil % modal; Amazon → Dil % × Price modal.
                    const btnId = key === 'amazon' ? 'amazon-dil-price-coupon-btn' : key + '-zero-sold-coupon-btn';
                    $('#' + btnId).trigger('click');
                }
            },
        };

        // "Prmt%": same green button + applied/total badge as /ebay-tabulator-view; opens Dil vs PRMT.
        const prmt = {
            title: 'Prmt%',
            field: 'prmt_rule',
            width: 110,
            hozAlign: 'center',
            headerHozAlign: 'center',
            headerSort: true,
            sorter: function (a, b, aRow, bRow) {
                const rank = function (row) {
                    const s = prmtSummary[row.getData().prmt_rule];
                    return s ? s.applied : -1;
                };
                return rank(aRow) - rank(bRow);
            },
            formatter: function (cell) {
                const key = cell.getValue();
                if (!key) {
                    return '<span class="promo-prmt-btn is-disabled" title="No Prmt% rule for this channel"><i class="fas fa-sliders-h"></i> Prmt%</span>';
                }
                const s = prmtSummary[key];
                const badge = s ? (s.applied + '/' + s.total) : '…';
                const tip = s
                    ? (s.applied + ' of ' + s.total + ' SKUs have PRMT% applied')
                    : 'SKUs with PRMT% applied / total SKUs';
                return '<button type="button" class="promo-prmt-btn" title="Shared Dil vs PRMT rule (all marketplaces)">'
                    + '<i class="fas fa-sliders-h"></i> Prmt% '
                    + '<span class="badge bg-light text-dark" title="' + escapeHtml(tip) + '">' + badge + '</span></button>';
            },
            cellClick: function (e, cell) {
                e.stopPropagation();
                const key = cell.getValue();
                if (key) openPrmtModal(cell.getRow().getData());
            },
        };

        // Prmt% sits right after Status; Rule sits right after the Coupons column (or after Prmt% if none yet).
        const couponIdx = dynamicColumns.findIndex(isCouponColumn);
        const cols = startColumns.slice();
        cols.push(prmt);
        if (couponIdx === -1) {
            cols.push(rule);
        }
        created.forEach(function (c, i) {
            cols.push(c);
            if (i === couponIdx) {
                cols.push(rule);
            }
        });
        cols.push(notes, edit);
        return cols;
    }

    // Flatten server row: values{colId:value} → dyn_<colId> fields
    function flattenRow(row) {
        const out = Object.assign({}, row);
        const values = row.values || {};
        dynamicColumns.forEach(function (col) {
            out['dyn_' + col.id] = values[col.id] != null ? values[col.id] : '';
        });
        return out;
    }

    table = new Tabulator('#promotions-table', {
        layout: 'fitColumns',
        placeholder: 'Loading channels…',
        height: '100%',
        rowHeight: 32,
        pagination: false,
        movableColumns: false,
        data: [],
        columns: buildColumns(),
    });

    function loadData() {
        $.ajax({
            url: '{{ route("promotions.data") }}',
            type: 'GET',
            success: function (response) {
                const ok = response && response.success;
                dynamicColumns = ok && Array.isArray(response.columns) ? response.columns : [];
                const rows = ok && Array.isArray(response.data) ? response.data : [];
                table.setColumns(buildColumns());
                table.setData(rows.map(flattenRow)).then(applyFilters);
                if (rows.some(function (r) { return r.prmt_rule; })) loadPrmtSummary();
            },
            error: function () {
                table.setData([]);
                alert('Failed to load promotions.');
            },
        });
    }
    table.on('tableBuilt', loadData);

    // Size the grid to exactly fill the rest of the viewport so all rows fit on one screen.
    function fitTableHeight() {
        const el = document.getElementById('promotions-table');
        if (!el) return;
        const top = el.getBoundingClientRect().top + window.scrollY;
        const h = Math.max(240, window.innerHeight - top - 24);
        el.style.height = h + 'px';
        if (table && table.redraw) table.redraw(true);
    }
    window.addEventListener('resize', fitTableHeight);
    setTimeout(fitTableHeight, 0);

    // ---------- Add Column ----------
    let addColumnModal = null;
    function getAddColumnModal() {
        if (!addColumnModal) addColumnModal = new bootstrap.Modal(document.getElementById('promo-add-column-modal'));
        return addColumnModal;
    }

    $('#promo-add-column-btn').on('click', function () {
        $('#promo-new-column-name').val('');
        $('#promo-add-column-error').addClass('d-none').text('');
        getAddColumnModal().show();
        setTimeout(function () { $('#promo-new-column-name').trigger('focus'); }, 300);
    });

    function saveNewColumn() {
        const name = ($('#promo-new-column-name').val() || '').trim();
        const $err = $('#promo-add-column-error');
        if (!name) {
            $err.removeClass('d-none').text('Please enter a column name.');
            return;
        }
        const $btn = $('#promo-add-column-save').prop('disabled', true);
        $.ajax({
            url: '{{ route("promotions.columns.store") }}',
            method: 'POST',
            data: { name: name, _token: csrf },
            success: function (res) {
                if (!res || !res.success) {
                    $err.removeClass('d-none').text((res && res.message) || 'Failed to add column.');
                    return;
                }
                getAddColumnModal().hide();
                loadData();
            },
            error: function (xhr) {
                const msg = (xhr.responseJSON && (xhr.responseJSON.message
                    || (xhr.responseJSON.errors && Object.values(xhr.responseJSON.errors)[0][0])))
                    || 'Failed to add column.';
                $err.removeClass('d-none').text(msg);
            },
            complete: function () { $btn.prop('disabled', false); },
        });
    }
    $('#promo-add-column-save').on('click', saveNewColumn);
    $('#promo-new-column-name').on('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); saveNewColumn(); }
    });

    // ---------- Edit (pen) ----------
    let editModal = null;
    let editingId = null;
    function getEditModal() {
        if (!editModal) editModal = new bootstrap.Modal(document.getElementById('promo-edit-modal'));
        return editModal;
    }

    function openEditModal(row) {
        editingId = row.id;
        $('#promo-edit-channel').text(row.channel || '');
        $('#promo-edit-error').addClass('d-none').text('');

        const $fields = $('#promo-edit-fields').empty();
        dynamicColumns.forEach(function (col) {
            const val = row['dyn_' + col.id] != null ? row['dyn_' + col.id] : '';
            const $group = $('<div class="mb-3"></div>');
            $group.append($('<label class="form-label fw-semibold"></label>').text(col.name));
            if (isCouponColumn(col)) {
                const auto = row.coupon_allowed === true ? 'Yes' : (row.coupon_allowed === false ? 'No' : 'Unknown');
                const $sel = $('<select class="form-select promo-edit-input"></select>')
                    .attr('data-col-id', col.id)
                    .append($('<option value=""></option>').text('Auto (' + auto + ')'))
                    .append('<option value="yes">Yes ✔</option>')
                    .append('<option value="no">No ✖</option>');
                const cur = String(val).trim().toLowerCase();
                $sel.val(cur === 'yes' || cur === 'no' ? cur : '');
                $group.append($sel);
            } else {
                $group.append($('<input type="text" class="form-control promo-edit-input" maxlength="2000">')
                    .attr('data-col-id', col.id)
                    .val(val));
            }
            $fields.append($group);
        });

        $('#promo-edit-notes').val(row.notes_remarks || '');
        getEditModal().show();
    }

    $('#promo-edit-save').on('click', function () {
        if (!editingId) return;
        const values = {};
        $('#promo-edit-fields .promo-edit-input').each(function () {
            values[$(this).attr('data-col-id')] = $(this).val();
        });
        const notes = $('#promo-edit-notes').val() || '';
        const $btn = $(this).prop('disabled', true);
        const $err = $('#promo-edit-error');

        $.ajax({
            url: '{{ route("promotions.save.row") }}',
            method: 'POST',
            data: { id: editingId, notes_remarks: notes, values: values, _token: csrf },
            success: function (res) {
                if (!res || !res.success) {
                    $err.removeClass('d-none').text((res && res.message) || 'Failed to save.');
                    return;
                }
                const update = { notes_remarks: notes };
                dynamicColumns.forEach(function (col) {
                    update['dyn_' + col.id] = values[col.id] != null ? values[col.id] : '';
                });
                const target = table.getRows().find(function (r) {
                    return Number(r.getData().id) === Number(editingId);
                });
                if (target) target.update(update);
                getEditModal().hide();
            },
            error: function (xhr) {
                $err.removeClass('d-none').text((xhr.responseJSON && xhr.responseJSON.message) || 'Failed to save.');
            },
            complete: function () { $btn.prop('disabled', false); },
        });
    });

    // ---------- Prmt% (shared Dil vs PRMT rule) ----------
    function loadPrmtSummary() {
        $.ajax({
            url: '{{ route("promotions.prmt.summary") }}',
            type: 'GET',
            dataType: 'json',
            success: function (res) {
                if (!res || !res.success) return;
                prmtSummary = res.data || {};
                if (table) {
                    table.getRows().forEach(function (r) {
                        if (r.getData().prmt_rule) r.reformat();
                    });
                }
            },
        });
    }

    // The Dil vs PRMT slabs are one shared store; /channel-promo-pricing only accepts a data-view channel
    // for the rules URL, so Amazon reads/saves them through ebay1 (same rows, same store).
    function prmtRulesUrl(key) {
        const c = key === 'amazon' ? 'ebay1' : key;
        return '/channel-promo-pricing/' + encodeURIComponent(c) + '/dil-prmt';
    }

    function getPrmtModal() {
        if (!prmtModal) prmtModal = new bootstrap.Modal(document.getElementById('promo-prmt-modal'));
        return prmtModal;
    }

    function prmtIsZeroSoldKey(key) {
        return String(key || '').indexOf('0-sold-') === 0;
    }

    function prmtFmt(n) {
        return String(Math.round(Number(n) * 100) / 100);
    }

    // Built-in slab range comes from the saved min/max, else from its key ("3-6" → 3…6).
    function prmtBuiltinRange(r) {
        const key = String(r.key || '');
        if (key.indexOf('custom-') === 0 || r.deleted) return null;
        const m = /^(\d+(?:\.\d+)?)-(\d+(?:\.\d+)?)$/.exec(key);
        if (!m || prmtIsZeroSoldKey(key)) return null;
        let lo = parseFloat(m[1]);
        let hi = parseFloat(m[2]);
        if (r.min != null && r.min !== '' && isFinite(Number(r.min))) lo = Number(r.min);
        if (key === '0-3' && Math.abs(lo - 0.01) < 0.0001) lo = 0;
        if (r.max != null && r.max !== '' && isFinite(Number(r.max))) hi = Number(r.max);
        if (!(hi >= lo)) return null;
        return { min: lo, max: hi };
    }

    function prmtDefaultRules() {
        const rules = [];
        let prmt = 12;
        for (let min = 0; min < 24; min += 3) {
            rules.push({ key: min + '-' + (min + 3), label: min + '–' + (min + 3) + '%', prmt: prmt });
            prmt -= 1;
        }
        rules.push({ key: '24-25', label: '24–25%', prmt: 1 });
        return rules;
    }

    function renderPrmtTable() {
        const $tb = $('#promo-prmt-tbody').empty();
        let rowNo = 0;
        let deleted = 0;
        let firstShown = true;
        prmtRules.forEach(function (r, idx) {
            if (prmtIsZeroSoldKey(r.key) || r.key === 'eq-0') return;
            if (r.deleted) { deleted++; return; }
            rowNo++;
            const prmt = isFinite(Number(r.prmt)) ? Number(r.prmt) : 0;
            const isCustom = String(r.key).indexOf('custom-') === 0;
            const range = isCustom ? { min: r.min, max: r.max } : (prmtBuiltinRange(r) || { min: '', max: '' });
            const lo = (range.min != null && range.min !== '' && isFinite(Number(range.min))) ? Number(range.min) : '';
            const hi = (range.max != null && range.max !== '' && isFinite(Number(range.max))) ? Number(range.max) : '';
            const rangeCell = '<td><div class="d-flex align-items-center gap-1">'
                + '<span class="text-muted small" style="min-width:20px;">' + rowNo + '.</span>'
                + '<input type="number" class="form-control form-control-sm promo-prmt-range" data-bound="min" min="0" step="0.1" style="width:78px;" value="' + lo + '" title="Dil% from (inclusive)">'
                + '<span>–</span>'
                + '<input type="number" class="form-control form-control-sm promo-prmt-range" data-bound="max" min="0" step="0.1" style="width:78px;" value="' + hi + '" title="Dil% to (exclusive)">'
                + '<span>%</span>'
                + '<button type="button" class="btn btn-sm btn-outline-danger ms-auto promo-prmt-remove" title="Delete this row"><i class="fas fa-trash"></i></button>'
                + '</div></td>';
            const countCell = '<td class="text-center"><span class="badge ch-promo-dil-prmt-count promo-prmt-count" '
                + 'title="SKUs currently getting this row\'s PRMT %">…</span></td>';
            $tb.append(
                '<tr data-key="' + escapeHtml(r.key) + '"' + (isCustom ? ' class="promo-prmt-custom"' : '') + '>'
                + rangeCell
                + countCell
                + '<td class="text-end"><input type="number" class="form-control form-control-sm promo-prmt-input" min="0" step="0.1" value="' + prmt + '" data-idx="' + idx + '"'
                + ((!isCustom && firstShown) ? ' title="Changing this sets following slabs to −1 each, minimum 0"' : '')
                + '></td></tr>'
            );
            if (!isCustom) firstShown = false;
        });
        $('#promo-prmt-row-count').text(rowNo + (rowNo === 1 ? ' row' : ' rows'));
        $('#promo-prmt-restore').toggleClass('d-none', deleted === 0);
        $('#promo-prmt-deleted-count').text(deleted);
        refreshPrmtCounts();
    }

    // Which slab a Dil falls in — same order as the eBay/Amazon pages: custom rows first, then built-in
    // ranges (min ≤ Dil < max; "24-25" and From = To include max).
    function prmtSlabKey(dil, list) {
        if (!isFinite(dil)) return 'none';
        for (let i = 0; i < list.length; i++) {
            const c = list[i];
            if (String(c.key).indexOf('custom-') !== 0) continue;
            const lo = Number(c.min), hi = Number(c.max);
            if (c.min != null && c.max != null && isFinite(lo) && isFinite(hi) && dil >= lo
                && (hi === lo ? dil <= hi : dil < hi)) return c.key;
        }
        for (let i = 0; i < list.length; i++) {
            const c = list[i];
            if (String(c.key).indexOf('custom-') === 0 || c.deleted || prmtIsZeroSoldKey(c.key)) continue;
            const range = prmtBuiltinRange(c);
            if (!range) continue;
            const inclusiveMax = c.key === '24-25' || range.max === range.min;
            if (dil >= range.min && (dil < range.max || (inclusiveMax && dil <= range.max))) return c.key;
        }
        return 'none';
    }

    // Fill the Count column (and the "Applied SKUs" total) from the cached Dil list and the slabs as currently typed.
    function refreshPrmtCounts() {
        const dils = prmtDilCache[prmtDilChannel];
        const $rows = $('#promo-prmt-tbody tr');
        if (!$rows.length) return;
        if (!Array.isArray(dils)) {
            $rows.find('.promo-prmt-count').text('…');
            $('#promo-prmt-count-total').text('');
            return;
        }
        const list = readPrmtRules();
        const counts = { none: 0 };
        dils.forEach(function (d) {
            const k = prmtSlabKey(Number(d), list);
            counts[k] = (counts[k] || 0) + 1;
        });
        $rows.each(function () {
            $(this).find('.promo-prmt-count').text(counts[String($(this).attr('data-key') || '')] || 0);
        });
        const total = dils.length;
        $('#promo-prmt-count-total').text('Applied SKUs: ' + (total - counts.none) + ' of ' + total
            + (counts.none ? ' (' + counts.none + ' outside all ranges)' : ''));
    }

    function loadPrmtDils(key, force) {
        prmtDilChannel = key;
        if (!force && Array.isArray(prmtDilCache[key])) {
            refreshPrmtCounts();
            return;
        }
        delete prmtDilCache[key];
        refreshPrmtCounts();
        $.ajax({
            url: '{{ route("promotions.prmt.dils") }}',
            type: 'GET',
            dataType: 'json',
            data: { channel: key, refresh: force ? 1 : 0 },
        }).done(function (res) {
            if (res && res.success && Array.isArray(res.dils)) prmtDilCache[key] = res.dils;
        }).fail(function () {
            $('#promo-prmt-count-total').text('Could not load SKU counts.');
        }).always(function () {
            if (prmtDilChannel === key && Array.isArray(prmtDilCache[key])) refreshPrmtCounts();
        });
    }

    // Copy what is typed in the modal back into prmtRules, and return the list to save.
    function readPrmtRules() {
        $('#promo-prmt-tbody tr').each(function () {
            const key = String($(this).attr('data-key') || '');
            const rule = prmtRules.find(function (r) { return r.key === key; });
            if (!rule) return;
            const val = parseFloat($(this).find('.promo-prmt-input').val());
            rule.prmt = (isFinite(val) && val >= 0) ? val : 0;
            const lo = parseFloat($(this).find('.promo-prmt-range[data-bound="min"]').val());
            const hi = parseFloat($(this).find('.promo-prmt-range[data-bound="max"]').val());
            rule.min = isFinite(lo) ? Math.max(0, lo) : null;
            rule.max = isFinite(hi) ? hi : null;
            if (isFinite(rule.min) && isFinite(rule.max) && rule.max >= rule.min) {
                rule.label = prmtFmt(rule.min) + '–' + prmtFmt(rule.max) + '%';
            }
        });
        return prmtRules.map(function (r) {
            const out = { key: r.key, label: r.label, prmt: Number(r.prmt) || 0 };
            if (String(r.key).indexOf('custom-') === 0) {
                out.min = r.min;
                out.max = r.max;
            } else {
                const range = prmtBuiltinRange(r);
                if (range) { out.min = range.min; out.max = range.max; }
                out.deleted = !!r.deleted;
            }
            return out;
        });
    }

    // First row drives the rest: each following built-in slab is 1 lower (min 0).
    function cascadePrmtFromFirst() {
        const $rows = $('#promo-prmt-tbody tr').not('.promo-prmt-custom');
        if (!$rows.length) return;
        const firstVal = parseFloat($rows.eq(0).find('.promo-prmt-input').val());
        if (!isFinite(firstVal)) return;
        $rows.each(function (i) {
            if (i > 0) $(this).find('.promo-prmt-input').val(Math.max(0, firstVal - i));
        });
    }

    function openPrmtModal(row) {
        $('#promo-prmt-modal-channel').text(row.channel || '');
        $('#promo-prmt-status').text('Loading…');
        prmtRules = prmtDefaultRules();
        prmtDilChannel = row.prmt_rule;
        renderPrmtTable();
        getPrmtModal().show();
        loadPrmtDils(row.prmt_rule, false);
        $.ajax({
            url: prmtRulesUrl(row.prmt_rule),
            method: 'GET',
            dataType: 'json',
        }).done(function (res) {
            if (res && Array.isArray(res.rules) && res.rules.length) {
                prmtRules = res.rules.map(function (r) { return Object.assign({}, r); });
            }
            renderPrmtTable();
            $('#promo-prmt-status').text(res && res.is_default
                ? 'Using first-time defaults (0–3 … 21–24, 24–25). Save to store them.'
                : 'Loaded shared Dil vs PRMT rules (all marketplaces).');
        }).fail(function () {
            $('#promo-prmt-status').text('Could not load saved rules — showing defaults.');
        });
        $('#promo-prmt-save-apply').data('channel', row.prmt_rule);
    }

    $('#promo-prmt-tbody').on('input change', '.promo-prmt-input', function () {
        if (this === $('#promo-prmt-tbody .promo-prmt-input').get(0)) cascadePrmtFromFirst();
    });
    $('#promo-prmt-tbody').on('change', '.promo-prmt-range', function () {
        refreshPrmtCounts();
    });
    $('#promo-prmt-tbody').on('click', '.promo-prmt-remove', function (e) {
        e.preventDefault();
        readPrmtRules();
        const key = String($(this).closest('tr').attr('data-key') || '');
        const rule = prmtRules.find(function (r) { return r.key === key; });
        if (!rule) return;
        if (!window.confirm('Delete the ' + String(rule.label || rule.key) + ' row?')) return;
        if (key.indexOf('custom-') === 0) {
            prmtRules = prmtRules.filter(function (r) { return r.key !== key; });
        } else {
            rule.deleted = true;
        }
        renderPrmtTable();
        $('#promo-prmt-status').text('Row deleted. Click Save to store.');
    });
    $('#promo-prmt-restore').on('click', function (e) {
        e.preventDefault();
        readPrmtRules();
        prmtRules.forEach(function (r) { if (r.deleted) r.deleted = false; });
        renderPrmtTable();
        $('#promo-prmt-status').text('Deleted rows restored. Click Save to store.');
    });
    $('#promo-prmt-add-row').on('click', function (e) {
        e.preventDefault();
        readPrmtRules();
        let customCount = 0;
        let start = 25;
        prmtRules.forEach(function (r) {
            if (String(r.key).indexOf('custom-') !== 0) return;
            customCount++;
            if (isFinite(Number(r.max))) start = Math.max(start, Number(r.max));
        });
        if (customCount >= 30) {
            $('#promo-prmt-status').text('Maximum 30 extra rows.');
            return;
        }
        prmtRules.push({
            key: 'custom-' + Date.now().toString(36) + Math.floor(Math.random() * 1296).toString(36),
            label: prmtFmt(start) + '–' + prmtFmt(start + 5) + '%',
            min: start,
            max: start + 5,
            prmt: 0,
        });
        renderPrmtTable();
        $('#promo-prmt-tbody tr.promo-prmt-custom').last().find('.promo-prmt-range[data-bound="min"]').trigger('focus').trigger('select');
        $('#promo-prmt-status').text('Row added — set Dil% range and PRMT %, then Save.');
    });
    // Validate the ranges and POST the shared slabs. Resolves with the server response, rejects when invalid / failed.
    function savePrmtRules(channel) {
        const dfd = $.Deferred();
        const rules = readPrmtRules();
        let bad = false;
        $('#promo-prmt-tbody tr').each(function () {
            const lo = parseFloat($(this).find('.promo-prmt-range[data-bound="min"]').val());
            const hi = parseFloat($(this).find('.promo-prmt-range[data-bound="max"]').val());
            if (!isFinite(lo) || !isFinite(hi) || lo < 0 || hi < lo) bad = true;
        });
        if (bad) {
            $('#promo-prmt-status').text('Each row needs a valid Dil% range (From ≥ 0 and To ≥ From).');
            return dfd.reject().promise();
        }
        $.ajax({
            url: prmtRulesUrl(channel),
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            contentType: 'application/json',
            data: JSON.stringify({ rules: rules, _token: csrf }),
        }).done(function (res) {
            if (res && Array.isArray(res.rules)) {
                prmtRules = res.rules.map(function (r) { return Object.assign({}, r); });
                renderPrmtTable();
            }
            dfd.resolve(res);
        }).fail(function (xhr) {
            $('#promo-prmt-status').text((xhr.responseJSON && xhr.responseJSON.message) || 'Failed to save.');
            dfd.reject();
        });
        return dfd.promise();
    }

    // After Apply the job runs in the background: refresh the badge a few times while it works.
    function pollPrmtSummaryAfterApply() {
        [8000, 25000, 60000, 120000].forEach(function (ms) { setTimeout(loadPrmtSummary, ms); });
    }

    function setPrmtBusy(busy) {
        $('#promo-prmt-save, #promo-prmt-save-apply').prop('disabled', busy);
    }

    // Save: store the slabs only.
    $('#promo-prmt-save').on('click', function () {
        const channel = $('#promo-prmt-save-apply').data('channel') || 'ebay1';
        setPrmtBusy(true);
        savePrmtRules(channel).done(function () {
            $('#promo-prmt-status').text('Saved — applies to all marketplaces. Click Save & Apply to update the SKUs now.');
        }).always(function () { setPrmtBusy(false); });
    });

    // Save & Apply: store the slabs, then apply PRMT% to every SKU of this channel (server job).
    $('#promo-prmt-save-apply').on('click', function () {
        const channel = $(this).data('channel') || 'ebay1';
        setPrmtBusy(true);
        savePrmtRules(channel).done(function () {
            $('#promo-prmt-status').text('Saved. Starting apply…');
            $.ajax({
                url: '{{ route("promotions.prmt.apply") }}',
                method: 'POST',
                dataType: 'json',
                data: { channel: channel, _token: csrf },
            }).done(function (res) {
                $('#promo-prmt-status').text(
                    (res && res.message) || 'Applying in the background.'
                );
                pollPrmtSummaryAfterApply();
                loadPrmtDils(channel, true);
            }).fail(function (xhr) {
                $('#promo-prmt-status').text('Saved, but apply failed: '
                    + ((xhr.responseJSON && xhr.responseJSON.message) || 'error'));
            }).always(function () { setPrmtBusy(false); });
        }).fail(function () { setPrmtBusy(false); });
    });

    $('#promo-search').on('keyup', applyFilters);
    $('#promo-filter-clear').on('click', function () {
        $('#promo-search').val('');
        applyFilters();
    });
})();
</script>
<script>
    // The EL30 coupon partial looks for a SKU grid at window.table / window.allTableData and
    // polls until one exists. This page has none, so give it an inert stub (no getData) so it
    // sees zero SKUs: it only loads/saves the slab rule and never pushes coupons or writes
    // zero-count history. The Count column + history dots run in snapshot mode: they read the
    // per-slab counts the eBay pages already saved (/ebay*-el30-cpn-history).
    window.table = window.table || { on: function () {} };
</script>
<script>
    @include('partials.ebay-zero-sold-coupon', ['ebayZeroSoldPart' => 'script', 'ebayZeroSoldChannel' => 'ebay1', 'ebayZeroSoldSnapshot' => true])
    @include('partials.ebay-zero-sold-coupon', ['ebayZeroSoldPart' => 'script', 'ebayZeroSoldChannel' => 'ebay2', 'ebayZeroSoldSnapshot' => true])
    @include('partials.ebay-zero-sold-coupon', ['ebayZeroSoldPart' => 'script', 'ebayZeroSoldChannel' => 'ebay3', 'ebayZeroSoldSnapshot' => true])
    @include('partials.amazon-dil-price-coupon', ['amazonCouponPart' => 'script'])
</script>
@endsection
