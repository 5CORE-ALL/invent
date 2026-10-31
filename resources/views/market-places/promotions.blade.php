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
                            </div>
                        </div>
                    </div>
                    <div id="promotions-table" style="height: calc(100vh - 320px);"></div>
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
    const csrf = $('meta[name="csrf-token"]').attr('content');

    // First three columns: active channels (Img | Channels | Status)
    const startColumns = [
            {
                title: 'Img',
                field: 'image',
                width: 90,
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
                minWidth: 220,
                formatter: function (cell) {
                    return `<span class="promo-channel-name">${escapeHtml(cell.getValue() || '')}</span>`;
                },
            },
            {
                title: 'Status',
                field: 'status',
                width: 130,
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
                    minWidth: 150,
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
                minWidth: 150,
                headerSort: true,
                formatter: function (cell) {
                    return textCell(cell.getValue(), 'promo-dyn-text');
                },
            };
        });

        const notes = {
            title: 'Notes and Remarks',
            field: 'notes_remarks',
            minWidth: 240,
            headerSort: false,
            formatter: function (cell) {
                return textCell(cell.getValue(), 'promo-notes-text');
            },
        };

        const edit = {
            title: 'Edit',
            field: '_edit',
            width: 80,
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
            width: 90,
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

        // Rule sits right after the Coupons column (or after Status if there is none yet).
        const couponIdx = dynamicColumns.findIndex(isCouponColumn);
        const cols = startColumns.slice();
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
        pagination: true,
        paginationSize: 50,
        paginationSizeSelector: [25, 50, 100, true],
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
            },
            error: function () {
                table.setData([]);
                alert('Failed to load promotions.');
            },
        });
    }
    table.on('tableBuilt', loadData);

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
