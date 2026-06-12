@extends('layouts.vertical', ['title' => 'Chargeback Issues'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        #chargeback-tabulator .tabulator-header .tabulator-col-title {
            font-weight: 600;
        }

        #chargeback-tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #2563eb;
            color: #fff;
        }

        #chargeback-tabulator .tabulator-cell.cb-editable {
            background-color: #fbfdff;
        }

        #chargeback-tabulator .tabulator-cell.cb-editable:hover {
            background-color: #eef5ff;
            cursor: text;
        }

        .cb-thumb {
            width: 38px;
            height: 38px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #e5e7eb;
            background: #fff;
        }

        .cb-row-btn {
            border: none;
            background: transparent;
            padding: 2px 6px;
            cursor: pointer;
            color: #2563eb;
        }

        .cb-row-btn.cb-danger {
            color: #dc2626;
        }

        .cb-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
            justify-content: space-between;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Chargeback Issues',
        'sub_title' => 'Customer Care',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="cb-toolbar mb-3">
                        <div>
                            <h5 class="mb-0">Chargeback Issues</h5>
                            <small class="text-muted">Same records as All Issues, filtered to the
                                <strong>Chargeback</strong> department. Double-click a cell to edit inline.</small>
                        </div>
                        <div class="d-flex gap-2">
                            <input type="search" id="cb-search" class="form-control form-control-sm"
                                style="max-width: 240px;" placeholder="Search all columns...">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="cb-refresh">
                                <i class="fa-solid fa-rotate"></i> Refresh
                            </button>
                            <button type="button" class="btn btn-sm btn-primary" id="cb-add">
                                <i class="fa-solid fa-plus"></i> Add Issue
                            </button>
                        </div>
                    </div>

                    <div id="chargeback-tabulator"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Add / Edit modal --}}
    <div class="modal fade" id="cb-issue-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form id="cb-issue-form">
                    <div class="modal-header">
                        <h5 class="modal-title" id="cb-issue-modal-title">Add Chargeback Issue</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="cb-id" name="id">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">SKU <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="cb-sku" name="sku" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Parent</label>
                                <input type="text" class="form-control" id="cb-parent" name="parent">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">QTY <span class="text-danger">*</span></label>
                                <input type="number" step="any" class="form-control" id="cb-qty" name="qty" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Order QTY</label>
                                <input type="number" step="any" class="form-control" id="cb-order_qty" name="order_qty">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Order #</label>
                                <input type="text" class="form-control" id="cb-order_number" name="order_number">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Marketplace 1</label>
                                <input type="text" class="form-control" id="cb-marketplace_1" name="marketplace_1"
                                    list="cb-marketplace-datalist">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Marketplace 2</label>
                                <input type="text" class="form-control" id="cb-marketplace_2" name="marketplace_2"
                                    list="cb-marketplace-datalist">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">What Happened</label>
                                <input type="text" class="form-control" id="cb-what_happened" name="what_happened">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Root Cause Found</label>
                                <input type="text" class="form-control" id="cb-issue" name="issue"
                                    list="cb-root-cause-found-datalist">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Issue Remark</label>
                                <input type="text" class="form-control" id="cb-issue_remark" name="issue_remark">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Action 1</label>
                                <input type="text" class="form-control" id="cb-action_1" name="action_1">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Action 1 Remark</label>
                                <input type="text" class="form-control" id="cb-action_1_remark" name="action_1_remark">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Replacement Tracking</label>
                                <input type="text" class="form-control" id="cb-replacement_tracking"
                                    name="replacement_tracking">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Root Cause Fixed</label>
                                <input type="text" class="form-control" id="cb-c_action_1" name="c_action_1"
                                    list="cb-root-cause-fixed-datalist">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Root Cause Fixed Remark</label>
                                <input type="text" class="form-control" id="cb-c_action_1_remark" name="c_action_1_remark">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Tracking #</label>
                                <input type="text" class="form-control" id="cb-tracking_number" name="tracking_number">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Refund Amount</label>
                                <input type="number" step="any" class="form-control" id="cb-refund_amount"
                                    name="refund_amount">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Total Loss</label>
                                <input type="number" step="any" class="form-control" id="cb-total_loss" name="total_loss">
                            </div>

                            <div class="col-md-8">
                                <label class="form-label">Issue Link</label>
                                <input type="url" class="form-control" id="cb-issue_link" name="issue_link">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Department</label>
                                <input type="text" class="form-control" value="Chargeback" disabled>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Close Note</label>
                                <input type="text" class="form-control" id="cb-close_note" name="close_note">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="cb-save-btn">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <datalist id="cb-marketplace-datalist">
        @foreach (($marketplaces ?? collect()) as $mp)
            <option value="{{ $mp }}"></option>
        @endforeach
    </datalist>
    <datalist id="cb-root-cause-found-datalist"></datalist>
    <datalist id="cb-root-cause-fixed-datalist"></datalist>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        (function () {
            'use strict';

            const DEPARTMENT = 'Chargeback';
            const CSRF = @json(csrf_token());
            const URLS = {
                list: @json(route('customer.care.dispatch.issues.list.index')),
                store: @json(route('customer.care.dispatch.issues.list.store')),
                updateBase: @json(route('customer.care.dispatch.issues.list.index', [], false)),
                skuDetails: @json(route('customer.care.dispatch.issues.sku.details')),
                archiveBase: @json(url('/customer-care/all-issues/issues')),
                dropdownOptions: @json(route('customer.care.dispatch.issues.dropdown.options.index')),
            };

            const jsonHeaders = {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': CSRF,
            };

            const editableFields = [
                'sku', 'parent', 'qty', 'order_qty', 'order_number', 'marketplace_1', 'marketplace_2',
                'what_happened', 'issue', 'issue_remark', 'action_1', 'action_1_remark', 'replacement_tracking',
                'c_action_1', 'c_action_1_remark', 'tracking_number', 'refund_amount', 'total_loss',
                'issue_link', 'close_note',
            ];

            function buildPayload(data) {
                const payload = { department: [DEPARTMENT] };
                editableFields.forEach(function (key) {
                    let val = data[key];
                    if (val === undefined) val = null;
                    if (typeof val === 'string') val = val.trim();
                    payload[key] = val === '' ? null : val;
                });
                // sku & qty are required by the server.
                payload.sku = (payload.sku ?? '').toString();
                payload.qty = data.qty === null || data.qty === undefined || data.qty === '' ? 0 : Number(data.qty);
                return payload;
            }

            async function persistRow(rowData) {
                const id = rowData.id;
                const isUpdate = !!id;
                const url = isUpdate ? (URLS.updateBase + '/' + id) : URLS.store;
                const res = await fetch(url, {
                    method: isUpdate ? 'PUT' : 'POST',
                    headers: jsonHeaders,
                    body: JSON.stringify(buildPayload(rowData)),
                });
                if (!res.ok) {
                    let msg = 'Save failed (' + res.status + ').';
                    try {
                        const err = await res.json();
                        if (err && err.message) msg = err.message;
                    } catch (e) { /* ignore */ }
                    throw new Error(msg);
                }
                return res.json();
            }

            const linkFormatter = function (cell) {
                const val = cell.getValue();
                if (!val) return '';
                const safe = String(val).replace(/"/g, '&quot;');
                return '<a href="' + safe + '" target="_blank" rel="noopener">link</a>';
            };

            const imageFormatter = function (cell) {
                const url = cell.getValue();
                if (!url) return '';
                return '<img src="' + String(url).replace(/"/g, '&quot;') + '" class="cb-thumb" alt="">';
            };

            const actionFormatter = function () {
                return '<button type="button" class="cb-row-btn cb-edit" title="Edit"><i class="fa-solid fa-pen"></i></button>' +
                    '<button type="button" class="cb-row-btn cb-danger cb-archive" title="Archive"><i class="fa-solid fa-box-archive"></i></button>';
            };

            function txtCol(title, field, width, editor) {
                return {
                    title: title,
                    field: field,
                    width: width,
                    editor: editor || 'input',
                    editableTitle: false,
                    cssClass: 'cb-editable',
                    headerFilter: 'input',
                };
            }

            const table = new Tabulator('#chargeback-tabulator', {
                layout: 'fitDataStretch',
                height: '70vh',
                placeholder: 'No chargeback issues yet.',
                index: 'id',
                pagination: true,
                paginationSize: 50,
                paginationSizeSelector: [25, 50, 100, 200],
                reactiveData: false,
                columns: [
                    { title: 'ID', field: 'id', width: 70, headerFilter: 'input', editor: false },
                    { title: 'Image', field: 'image_url', width: 70, formatter: imageFormatter, headerSort: false, editor: false },
                    txtCol('SKU', 'sku', 140),
                    txtCol('Parent', 'parent', 130),
                    txtCol('QTY', 'qty', 80, 'number'),
                    txtCol('Order QTY', 'order_qty', 90, 'number'),
                    txtCol('Order #', 'order_number', 130),
                    txtCol('Marketplace 1', 'marketplace_1', 130),
                    txtCol('Marketplace 2', 'marketplace_2', 130),
                    txtCol('What Happened', 'what_happened', 160),
                    txtCol('Root Cause Found', 'issue', 160),
                    txtCol('Issue Remark', 'issue_remark', 160),
                    txtCol('Action 1', 'action_1', 140),
                    txtCol('Action 1 Remark', 'action_1_remark', 160),
                    txtCol('Replacement Tracking', 'replacement_tracking', 150),
                    txtCol('Root Cause Fixed', 'c_action_1', 150),
                    txtCol('Root Cause Fixed Remark', 'c_action_1_remark', 170),
                    txtCol('Tracking #', 'tracking_number', 130),
                    { title: 'Refund', field: 'refund_amount', width: 100, editor: 'number', cssClass: 'cb-editable', headerFilter: 'input' },
                    { title: 'Total Loss', field: 'total_loss', width: 100, editor: 'number', cssClass: 'cb-editable', headerFilter: 'input' },
                    { title: 'Issue Link', field: 'issue_link', width: 100, formatter: linkFormatter, editor: 'input', headerSort: false },
                    txtCol('Close Note', 'close_note', 150),
                    { title: 'Department', field: 'department', width: 130, editor: false, headerFilter: 'input' },
                    { title: 'Created By', field: 'created_by', width: 130, editor: false, headerFilter: 'input' },
                    { title: 'Created At', field: 'created_at_display', width: 140, editor: false, headerFilter: 'input' },
                    { title: 'Actions', field: '_actions', width: 100, formatter: actionFormatter, headerSort: false, editor: false, frozen: true },
                ],
            });

            table.on('cellEdited', async function (cell) {
                const row = cell.getRow();
                const data = row.getData();
                try {
                    await persistRow(data);
                } catch (e) {
                    alert(e.message || 'Update failed.');
                    loadData();
                }
            });

            table.on('cellClick', function (e, cell) {
                if (cell.getField() !== '_actions') return;
                const target = e.target.closest('button');
                if (!target) return;
                const data = cell.getRow().getData();
                if (target.classList.contains('cb-edit')) {
                    openModal(data);
                } else if (target.classList.contains('cb-archive')) {
                    archiveRow(data.id);
                }
            });

            async function loadData() {
                try {
                    const res = await fetch(URLS.list + '?department=' + encodeURIComponent(DEPARTMENT), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const json = await res.json();
                    const rows = Array.isArray(json && json.data) ? json.data : [];
                    table.replaceData(rows);
                } catch (e) {
                    console.error('Failed to load chargeback issues', e);
                }
            }

            async function archiveRow(id) {
                if (!id || !confirm('Archive this issue?')) return;
                try {
                    const res = await fetch(URLS.archiveBase + '/' + id + '/archive', {
                        method: 'POST',
                        headers: jsonHeaders,
                        body: '{}',
                    });
                    if (!res.ok) {
                        const err = await res.json().catch(function () { return {}; });
                        throw new Error(err.message || 'Archive failed (' + res.status + ').');
                    }
                    loadData();
                } catch (e) {
                    alert(e.message || 'Archive failed.');
                }
            }

            // ---- Modal handling ----
            const modalEl = document.getElementById('cb-issue-modal');
            const modal = new bootstrap.Modal(modalEl);
            const form = document.getElementById('cb-issue-form');
            const modalFields = [
                'sku', 'parent', 'qty', 'order_qty', 'order_number', 'marketplace_1', 'marketplace_2',
                'what_happened', 'issue', 'issue_remark', 'action_1', 'action_1_remark', 'replacement_tracking',
                'c_action_1', 'c_action_1_remark', 'tracking_number', 'refund_amount', 'total_loss',
                'issue_link', 'close_note',
            ];

            function openModal(data) {
                form.reset();
                document.getElementById('cb-id').value = (data && data.id) || '';
                document.getElementById('cb-issue-modal-title').textContent = data && data.id
                    ? 'Edit Chargeback Issue #' + data.id
                    : 'Add Chargeback Issue';
                if (data) {
                    modalFields.forEach(function (f) {
                        const el = document.getElementById('cb-' + f);
                        if (el) el.value = data[f] === null || data[f] === undefined ? '' : data[f];
                    });
                }
                modal.show();
            }

            document.getElementById('cb-add').addEventListener('click', function () { openModal(null); });
            document.getElementById('cb-refresh').addEventListener('click', loadData);

            document.getElementById('cb-search').addEventListener('input', function () {
                const term = this.value.trim().toLowerCase();
                if (!term) {
                    table.clearFilter(false);
                    return;
                }
                table.setFilter(function (row) {
                    return Object.values(row).some(function (v) {
                        return v !== null && v !== undefined && String(v).toLowerCase().includes(term);
                    });
                });
            });

            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                const data = { id: document.getElementById('cb-id').value || null };
                modalFields.forEach(function (f) {
                    const el = document.getElementById('cb-' + f);
                    data[f] = el ? el.value : null;
                });
                if (!data.sku || !data.sku.trim()) { alert('SKU is required.'); return; }
                if (data.qty === '' || data.qty === null) { alert('QTY is required.'); return; }

                const btn = document.getElementById('cb-save-btn');
                btn.disabled = true;
                try {
                    await persistRow(data);
                    modal.hide();
                    loadData();
                } catch (err) {
                    alert(err.message || 'Save failed.');
                } finally {
                    btn.disabled = false;
                }
            });

            // SKU lookup autofill in modal
            document.getElementById('cb-sku').addEventListener('blur', async function () {
                const sku = this.value.trim();
                if (!sku) return;
                try {
                    const res = await fetch(URLS.skuDetails + '?sku=' + encodeURIComponent(sku), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const info = await res.json();
                    if (info && info.found) {
                        if (!document.getElementById('cb-parent').value && info.parent) {
                            document.getElementById('cb-parent').value = info.parent;
                        }
                        if (!document.getElementById('cb-qty').value && info.qty !== undefined && info.qty !== null) {
                            document.getElementById('cb-qty').value = info.qty;
                        }
                    }
                } catch (e) { /* ignore lookup errors */ }
            });

            async function loadDropdownOptions(fieldType, datalistId) {
                try {
                    const res = await fetch(URLS.dropdownOptions + '?field_type=' + encodeURIComponent(fieldType), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const json = await res.json();
                    const opts = Array.isArray(json && json.data) ? json.data : [];
                    const dl = document.getElementById(datalistId);
                    if (dl) {
                        dl.innerHTML = opts.map(function (o) {
                            return '<option value="' + String(o).replace(/"/g, '&quot;') + '"></option>';
                        }).join('');
                    }
                } catch (e) { /* ignore */ }
            }

            // Init
            loadData();
            loadDropdownOptions('root_cause_found', 'cb-root-cause-found-datalist');
            loadDropdownOptions('root_cause_fixed', 'cb-root-cause-fixed-datalist');
        })();
    </script>
@endsection
