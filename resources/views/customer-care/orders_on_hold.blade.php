@extends('layouts.vertical', ['title' => 'Orders On Hold', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        .orders-hold-table {
            table-layout: fixed;
            width: 100%;
            font-size: 12px;
        }

        .orders-hold-table th,
        .orders-hold-table td {
            padding: 0.45rem 0.4rem;
            vertical-align: middle;
        }

        .orders-hold-table td {
            word-break: break-word;
            white-space: normal;
        }

        .orders-hold-col-idx {
            width: 4%;
        }

        .orders-hold-col-sku {
            width: 11%;
        }

        .orders-hold-col-qty {
            width: 7%;
        }

        .orders-hold-col-parent {
            width: 13%;
        }

        .orders-hold-col-mp {
            width: 11%;
        }

        .orders-hold-col-issue {
            width: 10%;
        }

        .orders-hold-col-created-by {
            width: 11%;
        }

        .orders-hold-col-created-at {
            width: 12%;
        }

        .orders-hold-col-action {
            width: 10%;
        }

        .hold-action-btn {
            width: 30px;
            height: 30px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .hold-close-actions {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-width: 72px;
        }

        .orders-hold-close-cell {
            padding: 0.3rem 0.25rem !important;
            text-align: center;
            vertical-align: middle;
        }

        .what-happened-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            background-color: #dc3545;
            vertical-align: middle;
        }

        .what-happened-dot-damaged {
            background-color: #b8860b;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Orders On Hold',
        'sub_title' => 'Customer Care',
    ])

    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-start align-items-center gap-2 mb-3">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ordersOnHoldIssueModal">
                    <i class="mdi mdi-plus me-1"></i> Add Hold Issue
                </button>
                <button type="button" class="btn btn-outline-secondary" id="btnShowHistory">
                    <i class="mdi mdi-history me-1"></i> History
                </button>
            </div>
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-0">Use <strong>Add Hold Issue</strong> to record SKU hold issues. SKU lookup auto-fills Parent and available QTY.</p>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">Orders On Hold Records</h5>
                    <span class="badge bg-light text-dark" id="hold_issue_total_count">0</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm mb-0 orders-hold-table">
                            <thead class="table-light">
                                <tr>
                                    <th class="orders-hold-col-idx">#</th>
                                    <th class="orders-hold-col-sku">SKU</th>
                                    <th class="orders-hold-col-qty">QTY</th>
                                    <th class="orders-hold-col-parent">Parent</th>
                                    <th class="orders-hold-col-mp">MKT1</th>
                                    <th class="orders-hold-col-mp">MKT2</th>
                                    <th class="orders-hold-col-action">What Happened?</th>
                                    <th class="orders-hold-col-action">Action 1</th>
                                    <th class="orders-hold-col-issue">Root Cause<br>Found</th>
                                    <th class="orders-hold-col-action">C Action 1</th>
                                    <th class="orders-hold-col-action">Close</th>
                                    <th class="orders-hold-col-created-by">Created By</th>
                                    <th class="orders-hold-col-created-at">Created At</th>
                                </tr>
                            </thead>
                            <tbody id="hold_issue_table_body">
                                <tr id="hold_issue_empty_row">
                                    <td colspan="13" class="text-center text-muted py-4">No records found.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mt-3 d-none" id="holdIssueHistoryCard">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">Order History</h5>
                    <span class="badge bg-light text-dark" id="hold_issue_history_total_count">0</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm mb-0 orders-hold-table">
                            <thead class="table-light">
                                <tr>
                                    <th class="orders-hold-col-idx">#</th>
                                    <th class="orders-hold-col-sku">SKU</th>
                                    <th class="orders-hold-col-qty">QTY</th>
                                    <th class="orders-hold-col-parent">Parent</th>
                                    <th class="orders-hold-col-mp">MKT1</th>
                                    <th class="orders-hold-col-mp">MKT2</th>
                                    <th class="orders-hold-col-action">What Happened?</th>
                                    <th class="orders-hold-col-action">Action 1</th>
                                    <th class="orders-hold-col-issue">Root Cause<br>Found</th>
                                    <th class="orders-hold-col-action">C Action 1</th>
                                    <th class="orders-hold-col-action">Close</th>
                                    <th class="orders-hold-col-action">Event</th>
                                    <th class="orders-hold-col-created-by">Created By</th>
                                    <th class="orders-hold-col-created-at">Logged At</th>
                                </tr>
                            </thead>
                            <tbody id="hold_issue_history_table_body">
                                <tr id="hold_issue_history_empty_row">
                                    <td colspan="14" class="text-center text-muted py-4">No history found.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="ordersOnHoldIssueModal" tabindex="-1" aria-labelledby="ordersOnHoldIssueModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ordersOnHoldIssueModalLabel">Orders On Hold Issue</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="ordersOnHoldIssueForm" autocomplete="off">
                    <div class="modal-body">
                        <div id="ordersOnHoldIssueAlert" class="alert alert-danger d-none mb-3" role="alert"></div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="hold_issue_sku" class="form-label">SKU <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="hold_issue_sku" name="sku"
                                    list="hold_issue_sku_datalist" placeholder="Search SKU" required>
                                <datalist id="hold_issue_sku_datalist"></datalist>
                            </div>

                            <div class="col-md-3">
                                <label for="hold_issue_qty" class="form-label">QTY</label>
                                <input type="number" class="form-control" id="hold_issue_qty" name="qty" readonly>
                            </div>

                            <div class="col-md-3">
                                <label for="hold_issue_parent" class="form-label">Parent</label>
                                <input type="text" class="form-control" id="hold_issue_parent" name="parent" readonly>
                            </div>

                            <div class="col-md-6">
                                <label for="hold_issue_marketplace_1" class="form-label">MKT1</label>
                                <input type="text" class="form-control" id="hold_issue_marketplace_1" name="marketplace_1"
                                    list="hold_issue_marketplace_datalist" placeholder="Select Marketplace">
                            </div>

                            <div class="col-md-6">
                                <label for="hold_issue_marketplace_2" class="form-label">MKT2</label>
                                <input type="text" class="form-control" id="hold_issue_marketplace_2" name="marketplace_2"
                                    list="hold_issue_marketplace_datalist" placeholder="Select Marketplace">
                            </div>

                            <div class="col-md-6">
                                <label for="hold_issue_what_happened" class="form-label">What Happened?</label>
                                <select class="form-select" id="hold_issue_what_happened" name="what_happened">
                                    <option value="">Select</option>
                                    <option value="0 Stock">0 Stock</option>
                                    <option value="Damaged">Damaged</option>
                                </select>
                            </div>

                            <datalist id="hold_issue_marketplace_datalist">
                                @foreach (($marketplaces ?? collect()) as $marketplace)
                                    <option value="{{ $marketplace }}"></option>
                                @endforeach
                            </datalist>

                            <div class="col-md-4">
                                <label for="hold_issue_action_1" class="form-label">Action 1</label>
                                <select class="form-select" id="hold_issue_action_1" name="action_1">
                                    <option value="">Select Action 1</option>
                                    <option value="Upgraded + Stock Alternate">Upgraded + Stock Alternate</option>
                                    <option value="Alternate Sent + Stock Alternate">Alternate Sent + Stock Alternate</option>
                                    <option value="Sent Wrong Item + Stock Outgoing">Sent Wrong Item + Stock Outgoing</option>
                                    <option value="Cancelled">Cancelled</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <div class="col-md-8 d-none" id="action1RemarkWrap">
                                <label for="hold_issue_action_1_remark" class="form-label">Action 1 Remark</label>
                                <input type="text" class="form-control" id="hold_issue_action_1_remark" name="action_1_remark"
                                    placeholder="Write remark for Other">
                            </div>

                            <div class="col-12">
                                <label for="hold_issue_text" class="form-label">Root Cause Found <span class="text-danger">*</span></label>
                                <select class="form-select" id="hold_issue_text" name="issue" required>
                                    <option value="">Select Root Cause Found</option>
                                    <option value="Mapping">Mapping</option>
                                    <option value="Replacement Issued But not Entered">Replacement Issued But not Entered</option>
                                    <option value="FBA stock Issued But not Entered">FBA stock Issued But not Entered</option>
                                    <option value="Alternate Issued But not Entered">Alternate Issued But not Entered</option>
                                    <option value="Stock Balance not Entered">Stock Balance not Entered</option>
                                    <option value="Reserve Stock Issue">Reserve Stock Issue</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <div class="col-12 d-none" id="rootCauseRemarkWrap">
                                <label for="hold_issue_remark" class="form-label">Root Cause Remark</label>
                                <input type="text" class="form-control" id="hold_issue_remark" name="issue_remark"
                                    placeholder="Write remark for Other">
                            </div>

                            <div class="col-md-4">
                                <label for="hold_issue_c_action_1" class="form-label">C Action 1</label>
                                <input type="text" class="form-control" id="hold_issue_c_action_1" name="c_action_1"
                                    placeholder="Enter C Action 1">
                            </div>

                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        (function() {
            const skuSearchUrl = @json(route('customer.care.followups.skus'));
            const skuDetailsUrl = @json(route('customer.care.orders.on.hold.sku.details'));
            const recordsListUrl = @json(route('customer.care.orders.on.hold.issues.index'));
            const recordsStoreUrl = @json(route('customer.care.orders.on.hold.issues.store'));
            const recordsUpdateBaseUrl = @json(url('/customer-care/orders-on-hold/issues'));
            const historyListUrl = @json(route('customer.care.orders.on.hold.history.index'));
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            const skuInput = document.getElementById('hold_issue_sku');
            const skuDatalist = document.getElementById('hold_issue_sku_datalist');
            const qtyInput = document.getElementById('hold_issue_qty');
            const parentInput = document.getElementById('hold_issue_parent');
            const marketplace1Input = document.getElementById('hold_issue_marketplace_1');
            const marketplace2Input = document.getElementById('hold_issue_marketplace_2');
            const whatHappenedInput = document.getElementById('hold_issue_what_happened');
            const issueInput = document.getElementById('hold_issue_text');
            const issueRemarkInput = document.getElementById('hold_issue_remark');
            const rootCauseRemarkWrap = document.getElementById('rootCauseRemarkWrap');
            const action1Input = document.getElementById('hold_issue_action_1');
            const action1RemarkInput = document.getElementById('hold_issue_action_1_remark');
            const action1RemarkWrap = document.getElementById('action1RemarkWrap');
            const cAction1Input = document.getElementById('hold_issue_c_action_1');
            const form = document.getElementById('ordersOnHoldIssueForm');
            const alertBox = document.getElementById('ordersOnHoldIssueAlert');
            const tableBody = document.getElementById('hold_issue_table_body');
            const emptyRow = document.getElementById('hold_issue_empty_row');
            const totalCountEl = document.getElementById('hold_issue_total_count');
            const historyTotalCountEl = document.getElementById('hold_issue_history_total_count');
            const modalEl = document.getElementById('ordersOnHoldIssueModal');
            const historyTableBody = document.getElementById('hold_issue_history_table_body');
            const historyEmptyRow = document.getElementById('hold_issue_history_empty_row');
            const historyCard = document.getElementById('holdIssueHistoryCard');
            const btnShowHistory = document.getElementById('btnShowHistory');

            let skuTimer = null;
            let holdIssueRows = [];
            let holdIssueHistoryRows = [];
            let editingIssueId = null;

            function escAttr(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/"/g, '&quot;')
                    .replace(/</g, '&lt;');
            }

            function showAlert(message, type = 'danger') {
                alertBox.textContent = message;
                alertBox.classList.remove('alert-danger', 'alert-success');
                alertBox.classList.add(type === 'success' ? 'alert-success' : 'alert-danger');
                alertBox.classList.remove('d-none');
            }

            function hideAlert() {
                alertBox.classList.add('d-none');
                alertBox.textContent = '';
            }

            function escapeHtml(value) {
                const el = document.createElement('div');
                el.textContent = String(value ?? '');
                return el.innerHTML;
            }

            function whatHappenedDotHtml(value) {
                const text = String(value || '').trim();
                if (text.toLowerCase() === '0 stock') {
                    return '<span class="what-happened-dot" title="0 Stock"></span>';
                }
                if (text.toLowerCase() === 'damaged') {
                    return '<span class="what-happened-dot what-happened-dot-damaged" title="Damaged"></span>';
                }
                return '—';
            }

            function action1DisplayHtml(value, remark) {
                const action = String(value || '').trim();
                const rmk = String(remark || '').trim();
                if (!action) return '—';
                if (action === 'Other' && rmk) {
                    return escapeHtml(action + ': ' + rmk);
                }
                return escapeHtml(action);
            }

            function rootCauseDisplayHtml(value, remark) {
                const root = String(value || '').trim();
                const rmk = String(remark || '').trim();
                if (!root) return '—';
                if (root === 'Other' && rmk) {
                    return escapeHtml(root + ': ' + rmk);
                }
                return escapeHtml(root);
            }

            function toggleRootCauseRemarkField() {
                const selected = String(issueInput?.value || '').trim();
                const isOther = selected === 'Other';
                if (rootCauseRemarkWrap) {
                    rootCauseRemarkWrap.classList.toggle('d-none', !isOther);
                }
                if (issueRemarkInput) {
                    issueRemarkInput.required = isOther;
                    if (!isOther) issueRemarkInput.value = '';
                }
            }

            function toggleAction1RemarkField() {
                const selected = String(action1Input?.value || '').trim();
                const isOther = selected === 'Other';
                if (action1RemarkWrap) {
                    action1RemarkWrap.classList.toggle('d-none', !isOther);
                }
                if (action1RemarkInput) {
                    action1RemarkInput.required = isOther;
                    if (!isOther) action1RemarkInput.value = '';
                }
            }

            function updateTotalCount() {
                totalCountEl.textContent = String(holdIssueRows.length);
            }

            function renderRows() {
                if (!tableBody) return;

                if (!holdIssueRows.length) {
                    if (emptyRow) emptyRow.classList.remove('d-none');
                    updateTotalCount();
                    return;
                }

                if (emptyRow) emptyRow.classList.add('d-none');

                const dataRowsHtml = holdIssueRows.map((row, index) => {
                    const buttonsHtml =
                        '<div class="hold-close-actions">' +
                        '<button type="button" class="btn btn-sm btn-outline-primary hold-action-btn hold-edit-btn" data-id="' + row.id +
                        '" title="Edit"><i class="mdi mdi-pencil"></i></button>' +
                        '<button type="button" class="btn btn-sm btn-outline-danger hold-action-btn hold-archive-btn" data-id="' + row.id +
                        '" title="Archive"><i class="mdi mdi-archive"></i></button>' +
                        '</div>';
                    return '<tr>' +
                        '<td>' + escapeHtml(row.id) + '</td>' +
                        '<td>' + escapeHtml(row.sku) + '</td>' +
                        '<td>' + escapeHtml(row.qty) + '</td>' +
                        '<td>' + escapeHtml(row.parent) + '</td>' +
                        '<td>' + escapeHtml(row.marketplace_1) + '</td>' +
                        '<td>' + escapeHtml(row.marketplace_2) + '</td>' +
                        '<td>' + whatHappenedDotHtml(row.what_happened) + '</td>' +
                        '<td>' + action1DisplayHtml(row.action_1, row.action_1_remark) + '</td>' +
                        '<td>' + rootCauseDisplayHtml(row.issue, row.issue_remark) + '</td>' +
                        '<td>' + escapeHtml(row.c_action_1) + '</td>' +
                        '<td class="orders-hold-close-cell">' + buttonsHtml + '</td>' +
                        '<td>' + escapeHtml(row.created_by) + '</td>' +
                        '<td>' + escapeHtml(row.created_at) + '</td>' +
                        '</tr>';
                }).join('');

                tableBody.innerHTML = (emptyRow ? emptyRow.outerHTML : '') + dataRowsHtml;
                const nextEmpty = document.getElementById('hold_issue_empty_row');
                if (nextEmpty && holdIssueRows.length) nextEmpty.classList.add('d-none');
                updateTotalCount();
            }

            function updateHistoryTotalCount() {
                if (historyTotalCountEl) {
                    historyTotalCountEl.textContent = String(holdIssueHistoryRows.length);
                }
            }

            function renderHistoryRows() {
                if (!historyTableBody) return;

                if (!holdIssueHistoryRows.length) {
                    if (historyEmptyRow) historyEmptyRow.classList.remove('d-none');
                    updateHistoryTotalCount();
                    return;
                }

                if (historyEmptyRow) historyEmptyRow.classList.add('d-none');

                const dataRowsHtml = holdIssueHistoryRows.map((row, index) => {
                    return '<tr>' +
                        '<td>' + escapeHtml(row.issue_ref || row.orders_on_hold_issue_id || row.id) + '</td>' +
                        '<td>' + escapeHtml(row.sku) + '</td>' +
                        '<td>' + escapeHtml(row.qty) + '</td>' +
                        '<td>' + escapeHtml(row.parent) + '</td>' +
                        '<td>' + escapeHtml(row.marketplace_1) + '</td>' +
                        '<td>' + escapeHtml(row.marketplace_2) + '</td>' +
                        '<td>' + whatHappenedDotHtml(row.what_happened) + '</td>' +
                        '<td>' + action1DisplayHtml(row.action_1, row.action_1_remark) + '</td>' +
                        '<td>' + rootCauseDisplayHtml(row.issue, row.issue_remark) + '</td>' +
                        '<td>' + escapeHtml(row.c_action_1) + '</td>' +
                        '<td>' + escapeHtml(row.close_note) + '</td>' +
                        '<td>' + escapeHtml(row.event_type) + '</td>' +
                        '<td>' + escapeHtml(row.created_by) + '</td>' +
                        '<td>' + escapeHtml(row.logged_at) + '</td>' +
                        '</tr>';
                }).join('');

                historyTableBody.innerHTML = (historyEmptyRow ? historyEmptyRow.outerHTML : '') + dataRowsHtml;
                const nextEmpty = document.getElementById('hold_issue_history_empty_row');
                if (nextEmpty && holdIssueHistoryRows.length) nextEmpty.classList.add('d-none');
                updateHistoryTotalCount();
            }

            function normalizeRecord(row) {
                return {
                    id: row?.id ?? null,
                    sku: row?.sku ?? '',
                    qty: row?.qty ?? 0,
                    parent: row?.parent ?? '',
                    marketplace_1: row?.marketplace_1 ?? '',
                    marketplace_2: row?.marketplace_2 ?? '',
                    what_happened: row?.what_happened ?? '',
                    issue: row?.issue ?? '',
                    issue_remark: row?.issue_remark ?? '',
                    action_1: row?.action_1 ?? '',
                    action_1_remark: row?.action_1_remark ?? '',
                    c_action_1: row?.c_action_1 ?? '',
                    close_note: row?.close_note ?? '',
                    created_by: row?.created_by ?? 'System',
                    created_at: row?.created_at_display ?? row?.created_at ?? '',
                };
            }

            function normalizeHistoryRecord(row) {
                return {
                    id: row?.id ?? null,
                    orders_on_hold_issue_id: row?.orders_on_hold_issue_id ?? null,
                    revision_no: row?.revision_no ?? null,
                    issue_ref: row?.issue_ref ?? null,
                    event_type: row?.event_type ?? '',
                    sku: row?.sku ?? '',
                    qty: row?.qty ?? 0,
                    parent: row?.parent ?? '',
                    marketplace_1: row?.marketplace_1 ?? '',
                    marketplace_2: row?.marketplace_2 ?? '',
                    what_happened: row?.what_happened ?? '',
                    issue: row?.issue ?? '',
                    issue_remark: row?.issue_remark ?? '',
                    action_1: row?.action_1 ?? '',
                    action_1_remark: row?.action_1_remark ?? '',
                    c_action_1: row?.c_action_1 ?? '',
                    close_note: row?.close_note ?? '',
                    created_by: row?.created_by ?? 'System',
                    logged_at: row?.logged_at_display ?? row?.logged_at ?? '',
                };
            }

            async function loadHoldIssueRows() {
                try {
                    const response = await fetch(recordsListUrl, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        }
                    });
                    const data = await response.json();
                    holdIssueRows = Array.isArray(data?.data) ? data.data.map(normalizeRecord) : [];
                    renderRows();
                } catch (error) {
                    holdIssueRows = [];
                    renderRows();
                }
            }

            async function loadHoldIssueHistoryRows() {
                try {
                    const response = await fetch(historyListUrl, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        }
                    });
                    const data = await response.json();
                    holdIssueHistoryRows = Array.isArray(data?.data) ? data.data.map(normalizeHistoryRecord) : [];
                    renderHistoryRows();
                } catch (error) {
                    holdIssueHistoryRows = [];
                    renderHistoryRows();
                }
            }

            function resetForm() {
                form.reset();
                editingIssueId = null;
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.textContent = 'Save';
                qtyInput.value = '';
                parentInput.value = '';
                marketplace1Input.value = '';
                marketplace2Input.value = '';
                whatHappenedInput.value = '';
                issueRemarkInput.value = '';
                toggleRootCauseRemarkField();
                action1Input.value = '';
                action1RemarkInput.value = '';
                toggleAction1RemarkField();
                cAction1Input.value = '';
                hideAlert();
            }

            function getRecordById(id) {
                const normalizedId = Number(id);
                return holdIssueRows.find(r => Number(r.id) === normalizedId) || null;
            }

            function openEditModal(record) {
                if (!record) return;
                editingIssueId = Number(record.id);
                skuInput.value = record.sku || '';
                qtyInput.value = record.qty ?? '';
                parentInput.value = record.parent || '';
                marketplace1Input.value = record.marketplace_1 || '';
                marketplace2Input.value = record.marketplace_2 || '';
                whatHappenedInput.value = record.what_happened || '';
                issueInput.value = record.issue || '';
                issueRemarkInput.value = record.issue_remark || '';
                toggleRootCauseRemarkField();
                action1Input.value = record.action_1 || '';
                action1RemarkInput.value = record.action_1_remark || '';
                toggleAction1RemarkField();
                cAction1Input.value = record.c_action_1 || '';
                hideAlert();

                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.textContent = 'Update';

                const modalInstance = window.bootstrap?.Modal?.getOrCreateInstance(modalEl);
                if (modalInstance) modalInstance.show();
            }

            async function archiveRecord(recordId) {
                if (!confirm('Archive this record?')) return;
                try {
                    const response = await fetch(recordsUpdateBaseUrl + '/' + encodeURIComponent(recordId) + '/archive', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                    });
                    const data = await response.json();
                    if (!response.ok) {
                        showAlert(data?.message || 'Unable to archive record.');
                        return;
                    }
                    holdIssueRows = holdIssueRows.filter(r => Number(r.id) !== Number(recordId));
                    renderRows();
                    loadHoldIssueHistoryRows();
                    showAlert(data?.message || 'Hold issue archived successfully.', 'success');
                } catch (error) {
                    showAlert('Unable to archive record. Please try again.');
                }
            }

            async function refreshSkuSuggestions(query) {
                const q = String(query || '').trim();
                if (q.length < 1) {
                    skuDatalist.innerHTML = '';
                    return;
                }

                try {
                    const response = await fetch(skuSearchUrl + '?q=' + encodeURIComponent(q), {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const data = await response.json();
                    const list = Array.isArray(data?.skus) ? data.skus : [];

                    skuDatalist.innerHTML = list.map(item => {
                        const sku = item?.sku ?? '';
                        const parent = item?.parent ?? '';
                        const label = parent ? (parent + ' · ' + sku) : sku;
                        return '<option value="' + escAttr(sku) + '" label="' + escAttr(label) + '"></option>';
                    }).join('');
                } catch (e) {
                    skuDatalist.innerHTML = '';
                }
            }

            async function fillSkuDetails() {
                const sku = skuInput.value.trim();
                qtyInput.value = '';
                parentInput.value = '';

                if (!sku) return;

                try {
                    const response = await fetch(skuDetailsUrl + '?sku=' + encodeURIComponent(sku), {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const data = await response.json();

                    if (!response.ok || !data.found) {
                        return;
                    }

                    qtyInput.value = data.qty ?? 0;
                    parentInput.value = data.parent ?? '';
                } catch (e) {
                    // Keep inputs blank on request errors.
                }
            }

            skuInput.addEventListener('input', () => {
                clearTimeout(skuTimer);
                skuTimer = setTimeout(() => refreshSkuSuggestions(skuInput.value), 220);
            });

            skuInput.addEventListener('focus', () => {
                clearTimeout(skuTimer);
                refreshSkuSuggestions(skuInput.value);
            });

            skuInput.addEventListener('change', fillSkuDetails);
            skuInput.addEventListener('blur', fillSkuDetails);

            issueInput.addEventListener('change', toggleRootCauseRemarkField);

            action1Input.addEventListener('change', toggleAction1RemarkField);

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                hideAlert();

                const sku = skuInput.value.trim();
                const issue = issueInput.value.trim();

                if (!sku) {
                    showAlert('SKU is required.');
                    skuInput.focus();
                    return;
                }
                if (!issue) {
                    showAlert('Root Cause Found is required.');
                    issueInput.focus();
                    return;
                }
                if (issueInput.value.trim() === 'Other' && issueRemarkInput.value.trim() === '') {
                    showAlert('Please enter Root Cause remark for Other.');
                    issueRemarkInput.focus();
                    return;
                }
                if (action1Input.value.trim() === 'Other' && action1RemarkInput.value.trim() === '') {
                    showAlert('Please enter Action 1 remark for Other.');
                    action1RemarkInput.focus();
                    return;
                }

                try {
                    const payload = {
                        sku: sku,
                        qty: qtyInput.value === '' ? 0 : Number(qtyInput.value),
                        parent: parentInput.value.trim(),
                        marketplace_1: marketplace1Input.value.trim(),
                        marketplace_2: marketplace2Input.value.trim(),
                        what_happened: whatHappenedInput.value.trim(),
                        issue: issue,
                        issue_remark: issueRemarkInput.value.trim(),
                        action_1: action1Input.value.trim(),
                        action_1_remark: action1RemarkInput.value.trim(),
                        c_action_1: cAction1Input.value.trim(),
                    };

                    const isEdit = editingIssueId !== null;
                    const targetUrl = isEdit
                        ? recordsUpdateBaseUrl + '/' + encodeURIComponent(editingIssueId)
                        : recordsStoreUrl;

                    const response = await fetch(targetUrl, {
                        method: isEdit ? 'PUT' : 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify(payload),
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        if (data?.errors && typeof data.errors === 'object') {
                            const firstKey = Object.keys(data.errors)[0];
                            const firstError = firstKey ? data.errors[firstKey]?.[0] : null;
                            showAlert(firstError || data?.message || 'Unable to save hold issue.');
                            return;
                        }
                        showAlert(data?.message || 'Unable to save hold issue.');
                        return;
                    }

                    if (isEdit) {
                        await loadHoldIssueRows();
                    } else {
                        holdIssueRows.unshift(normalizeRecord(data?.row || {}));
                        renderRows();
                    }
                    loadHoldIssueHistoryRows();
                    showAlert(data?.message || (isEdit ? 'Hold issue updated successfully.' : 'Hold issue saved successfully.'), 'success');

                    const modalInstance = window.bootstrap?.Modal?.getInstance(modalEl);
                    if (modalInstance) {
                        setTimeout(() => {
                            modalInstance.hide();
                        }, 250);
                    }
                } catch (error) {
                    showAlert('Unable to save hold issue. Please try again.');
                }
            });

            tableBody.addEventListener('click', (event) => {
                const editBtn = event.target.closest('.hold-edit-btn');
                if (editBtn) {
                    const record = getRecordById(editBtn.getAttribute('data-id'));
                    openEditModal(record);
                    return;
                }

                const archiveBtn = event.target.closest('.hold-archive-btn');
                if (archiveBtn) {
                    archiveRecord(archiveBtn.getAttribute('data-id'));
                }
            });

            btnShowHistory.addEventListener('click', () => {
                historyCard.classList.remove('d-none');
                loadHoldIssueHistoryRows();
                historyCard.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            });

            modalEl.addEventListener('hidden.bs.modal', resetForm);
            toggleRootCauseRemarkField();
            toggleAction1RemarkField();
            renderRows();
            renderHistoryRows();
            loadHoldIssueRows();
        })();
    </script>
@endsection
