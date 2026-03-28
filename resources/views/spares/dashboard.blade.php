@extends('layouts.vertical', ['title' => 'Spare Parts Dashboard', 'mode' => '', 'demo' => ''])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        .dashboard-card-value {
            font-size: 1.4rem;
            font-weight: 700;
        }

        .table-sticky thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #f8f9fa;
        }

        .tab-pane .table-responsive {
            max-height: 460px;
        }

        .hierarchy-list ul {
            margin-top: 0.4rem;
            margin-bottom: 0.4rem;
        }
    </style>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <h4 class="mb-0">Spare Parts Dashboard</h4>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSpareModal">
                + Add Spare
            </button>
        </div>

        <div class="row g-3 mb-3" id="summaryCards">
            <div class="col-6 col-md-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small">Total Spare Parts</div>
                        <div class="dashboard-card-value" data-summary="total_spares">0</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card h-100 border-warning">
                    <div class="card-body">
                        <div class="text-muted small">Low Stock Items</div>
                        <div class="dashboard-card-value text-warning" data-summary="low_stock_items">0</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small">Pending Requisitions</div>
                        <div class="dashboard-card-value" data-summary="pending_requisitions">0</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small">Open Purchase Orders</div>
                        <div class="dashboard-card-value" data-summary="open_purchase_orders">0</div>
                    </div>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs mb-3" id="sparesTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-spares" type="button">Spare Parts List</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-requisitions" type="button">Requisitions</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-issues" type="button">Issue Items</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-pos" type="button">Purchase Orders</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-hierarchy" type="button">Hierarchy View</button>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="tab-spares">
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle table-sticky mb-0">
                                <thead>
                                    <tr>
                                        <th>SKU</th>
                                        <th>Part Name</th>
                                        <th>Parent SKU</th>
                                        <th>Supplier</th>
                                        <th>Available Qty</th>
                                        <th>Reorder Level</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="sparesTableBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-requisitions">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-end mb-2">
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createRequisitionModal">
                                Create Requisition
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle table-sticky mb-0">
                                <thead>
                                    <tr>
                                        <th>Req ID</th>
                                        <th>SKU</th>
                                        <th>Qty</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="requisitionsTableBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-issues">
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle table-sticky mb-0">
                                <thead>
                                    <tr>
                                        <th>Req ID</th>
                                        <th>SKU</th>
                                        <th>Requested Qty</th>
                                        <th>Issued Qty</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="issueItemsTableBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-pos">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-end mb-2">
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createPoModal">
                                Create PO
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle table-sticky mb-0">
                                <thead>
                                    <tr>
                                        <th>PO ID</th>
                                        <th>Supplier</th>
                                        <th>SKU</th>
                                        <th>Qty</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="poTableBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-hierarchy">
                <div class="card">
                    <div class="card-body hierarchy-list" id="hierarchyTree"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="position-fixed top-0 end-0 p-3" style="z-index: 1080">
        <div id="appToast" class="toast text-bg-dark border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="appToastMessage">Done</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addSpareModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" id="addSpareForm">
                <div class="modal-header">
                    <h5 class="modal-title">Add Spare</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-2">
                    <div class="col-12">
                        <label class="form-label">SKU</label>
                        <input type="text" class="form-control" name="sku" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Part Name</label>
                        <input type="text" class="form-control" name="part_name" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Parent SKU</label>
                        <input type="text" class="form-control" name="parent_sku">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Supplier</label>
                        <input list="supplierOptions" class="form-control" name="supplier" placeholder="Supplier name or id">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Qty</label>
                        <input type="number" min="0" class="form-control" name="qty" value="0" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Reorder Level</label>
                        <input type="number" min="0" class="form-control" name="reorder_level" value="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary btn-sm" type="submit">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="createRequisitionModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" id="createRequisitionForm">
                <div class="modal-header">
                    <h5 class="modal-title">Create Requisition</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-2">
                    <div class="col-12">
                        <label class="form-label">SKU</label>
                        <input type="text" class="form-control" name="sku" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Qty</label>
                        <input type="number" min="1" class="form-control" name="qty" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary btn-sm" type="submit">Create</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="issueItemModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" id="issueItemForm">
                <div class="modal-header">
                    <h5 class="modal-title">Issue Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="requisition_item_id" id="issueRequisitionItemId">
                    <div class="mb-2 small text-muted" id="issueItemMeta"></div>
                    <label class="form-label">Issue Qty</label>
                    <input type="number" min="1" class="form-control" name="issue_qty" id="issueQty" required>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary btn-sm" type="submit">Issue</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="createPoModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" id="createPoForm">
                <div class="modal-header">
                    <h5 class="modal-title">Create Purchase Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-2">
                    <div class="col-12">
                        <label class="form-label">Supplier</label>
                        <input list="supplierOptions" class="form-control" name="supplier" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">SKU</label>
                        <input type="text" class="form-control" name="sku" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Qty</label>
                        <input type="number" min="1" class="form-control" name="qty" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary btn-sm" type="submit">Create PO</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="receivePoModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" id="receivePoForm">
                <div class="modal-header">
                    <h5 class="modal-title">Receive Stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="receivePoId">
                    <input type="hidden" name="po_item_id" id="receivePoItemId">
                    <div class="mb-2 small text-muted" id="receivePoMeta"></div>
                    <label class="form-label">Receive Qty</label>
                    <input type="number" min="1" class="form-control" name="qty" id="receiveQtyInput" required>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary btn-sm" type="submit">Receive</button>
                </div>
            </form>
        </div>
    </div>

    <datalist id="supplierOptions"></datalist>
@endsection

@section('script')
    @php
        $dashboardPayload = [
            'summary' => $summary ?? [],
            'spares' => $spares ?? [],
            'requisitions' => $requisitions ?? [],
            'issue_items' => $issue_items ?? [],
            'purchase_orders' => $purchase_orders ?? [],
            'hierarchy' => $hierarchy ?? [],
            'suppliers' => $suppliers ?? [],
        ];
    @endphp
    <script>
        (function() {
            const csrf = $('meta[name="csrf-token"]').attr('content');
            const routes = {
                data: @json(route('inventory.spares.dashboard', ['ajax' => 1])),
                storeSpare: @json(route('inventory.spares.store')),
                createRequisition: @json(route('inventory.spares.requisitions.create')),
                updateRequisitionStatus: @json(route('inventory.spares.requisitions.status', ['requisition' => '__ID__'])),
                issue: @json(route('inventory.spares.issue')),
                createPo: @json(route('inventory.spares.purchase-orders.create')),
                poAction: @json(route('inventory.spares.purchase-orders.receive', ['purchaseOrder' => '__ID__']))
            };

            let dashboardData = @json($dashboardPayload);

            const issueModal = new bootstrap.Modal(document.getElementById('issueItemModal'));
            const receiveModal = new bootstrap.Modal(document.getElementById('receivePoModal'));
            const toast = new bootstrap.Toast(document.getElementById('appToast'));

            function showToast(message, isError = false) {
                $('#appToast').toggleClass('text-bg-danger', isError).toggleClass('text-bg-dark', !isError);
                $('#appToastMessage').text(message || (isError ? 'Something went wrong.' : 'Done'));
                toast.show();
            }

            function safe(value) {
                return $('<div>').text(value == null ? '' : String(value)).html();
            }

            function statusBadge(status) {
                const map = {
                    draft: 'secondary',
                    submitted: 'info',
                    approved: 'primary',
                    issued: 'warning',
                    closed: 'dark',
                    sent: 'info',
                    partially_received: 'warning',
                    received: 'success',
                    low_stock: 'danger',
                    healthy: 'success'
                };
                const label = String(status || '').replace(/_/g, ' ');
                return `<span class="badge bg-${map[status] || 'secondary'}">${safe(label)}</span>`;
            }

            function renderSummary() {
                Object.entries(dashboardData.summary || {}).forEach(([key, value]) => {
                    $(`[data-summary="${key}"]`).text(value ?? 0);
                });
            }

            function renderSuppliers() {
                const rows = (dashboardData.suppliers || []).map(s => `<option value="${safe(s.label || s.id)}"></option>`);
                $('#supplierOptions').html(rows.join(''));
            }

            function renderSpares() {
                const rows = (dashboardData.spares || []).map(row => `
                    <tr>
                        <td>${safe(row.sku)}</td>
                        <td>${safe(row.part_name)}</td>
                        <td>${safe(row.parent_sku)}</td>
                        <td>${safe(row.supplier)}</td>
                        <td>${safe(row.available_qty)}</td>
                        <td>${safe(row.reorder_level)}</td>
                        <td>${statusBadge(row.status)}</td>
                    </tr>
                `);
                $('#sparesTableBody').html(rows.length ? rows.join('') : '<tr><td colspan="7" class="text-center text-muted">No spares found.</td></tr>');
            }

            function requisitionActionButtons(row) {
                const actions = [];
                if (row.status === 'draft') {
                    actions.push(`<button class="btn btn-outline-info btn-sm req-status-btn" data-id="${row.requisition_id}" data-status="submitted">Submit</button>`);
                }
                if (row.status === 'submitted' || row.status === 'draft') {
                    actions.push(`<button class="btn btn-outline-primary btn-sm req-status-btn" data-id="${row.requisition_id}" data-status="approved">Approve</button>`);
                }
                if (row.status === 'approved') {
                    const remaining = Math.max((row.approved_qty || row.qty) - (row.issued_qty || 0), 0);
                    if (remaining > 0) {
                        actions.push(`<button class="btn btn-outline-warning btn-sm issue-open-btn" data-item-id="${row.item_id}" data-sku="${safe(row.sku)}" data-remaining="${remaining}" data-req="${row.requisition_id}">Issue</button>`);
                    }
                    if (remaining === 0) {
                        actions.push(`<button class="btn btn-outline-warning btn-sm req-status-btn" data-id="${row.requisition_id}" data-status="issued">Mark Issued</button>`);
                    }
                }
                if (row.status === 'issued') {
                    actions.push(`<button class="btn btn-outline-dark btn-sm req-status-btn" data-id="${row.requisition_id}" data-status="closed">Close</button>`);
                }
                return actions.join(' ');
            }

            function renderRequisitions() {
                const rows = (dashboardData.requisitions || []).map(row => `
                    <tr>
                        <td>#${safe(row.requisition_id)}</td>
                        <td>${safe(row.sku)}</td>
                        <td>${safe(row.qty)}</td>
                        <td>${statusBadge(row.status)}</td>
                        <td class="text-nowrap">${requisitionActionButtons(row)}</td>
                    </tr>
                `);
                $('#requisitionsTableBody').html(rows.length ? rows.join('') : '<tr><td colspan="5" class="text-center text-muted">No requisitions found.</td></tr>');
            }

            function renderIssueItems() {
                const rows = (dashboardData.issue_items || []).map(row => `
                    <tr>
                        <td>#${safe(row.requisition_id)}</td>
                        <td>${safe(row.sku)}</td>
                        <td>${safe(row.requested_qty)}</td>
                        <td>${safe(row.issued_qty)}</td>
                        <td>
                            <button class="btn btn-outline-warning btn-sm issue-open-btn"
                                data-item-id="${row.item_id}"
                                data-sku="${safe(row.sku)}"
                                data-remaining="${row.remaining_qty}"
                                data-req="${row.requisition_id}">
                                Issue
                            </button>
                        </td>
                    </tr>
                `);
                $('#issueItemsTableBody').html(rows.length ? rows.join('') : '<tr><td colspan="5" class="text-center text-muted">No approved requisitions pending issue.</td></tr>');
            }

            function poActionButtons(row) {
                const actions = [];
                if (row.status === 'draft') {
                    actions.push(`<button class="btn btn-outline-info btn-sm po-sent-btn" data-po-id="${row.po_id}">Mark as Sent</button>`);
                }
                if ((row.status === 'sent' || row.status === 'partially_received') && row.remaining_qty > 0) {
                    actions.push(`<button class="btn btn-outline-success btn-sm po-receive-open-btn"
                        data-po-id="${row.po_id}"
                        data-item-id="${row.item_id}"
                        data-remaining="${row.remaining_qty}"
                        data-sku="${safe(row.sku)}"
                        data-po-number="${safe(row.po_number)}">Receive Stock</button>`);
                }
                return actions.join(' ');
            }

            function renderPOs() {
                const rows = (dashboardData.purchase_orders || []).map(row => `
                    <tr>
                        <td>${safe(row.po_number)} (#${safe(row.po_id)})</td>
                        <td>${safe(row.supplier)}</td>
                        <td>${safe(row.sku)}</td>
                        <td>${safe(row.qty_ordered)}</td>
                        <td>${statusBadge(row.status)}</td>
                        <td class="text-nowrap">${poActionButtons(row)}</td>
                    </tr>
                `);
                $('#poTableBody').html(rows.length ? rows.join('') : '<tr><td colspan="6" class="text-center text-muted">No purchase orders found.</td></tr>');
            }

            function renderHierarchy() {
                const rows = (dashboardData.hierarchy || []).map(group => {
                    const children = (group.spares || []).map(s => `<li>${safe(s)}</li>`).join('');
                    return `<li><strong>${safe(group.parent_sku || 'Unknown Parent')}</strong><ul>${children || '<li>No spare SKUs</li>'}</ul></li>`;
                });
                $('#hierarchyTree').html(rows.length ? `<ul>${rows.join('')}</ul>` : '<div class="text-muted">No hierarchy data found.</div>');
            }

            function renderAll() {
                renderSummary();
                renderSuppliers();
                renderSpares();
                renderRequisitions();
                renderIssueItems();
                renderPOs();
                renderHierarchy();
            }

            function parseError(xhr) {
                if (xhr.responseJSON?.message) {
                    return xhr.responseJSON.message;
                }
                if (xhr.responseJSON?.errors) {
                    const firstKey = Object.keys(xhr.responseJSON.errors)[0];
                    if (firstKey && xhr.responseJSON.errors[firstKey][0]) {
                        return xhr.responseJSON.errors[firstKey][0];
                    }
                }
                return 'Request failed.';
            }

            function applyResponseData(resp, successMessage) {
                if (resp?.data) {
                    dashboardData = resp.data;
                    renderAll();
                }
                showToast(resp?.message || successMessage || 'Saved.');
            }

            function post(url, data) {
                return $.ajax({
                    url,
                    method: 'POST',
                    data,
                    headers: {
                        'X-CSRF-TOKEN': csrf
                    }
                });
            }

            $('#addSpareForm').on('submit', function(e) {
                e.preventDefault();
                post(routes.storeSpare, $(this).serialize())
                    .done(resp => {
                        applyResponseData(resp, 'Spare added.');
                        this.reset();
                        bootstrap.Modal.getInstance(document.getElementById('addSpareModal')).hide();
                    })
                    .fail(xhr => showToast(parseError(xhr), true));
            });

            $('#createRequisitionForm').on('submit', function(e) {
                e.preventDefault();
                post(routes.createRequisition, $(this).serialize())
                    .done(resp => {
                        applyResponseData(resp, 'Requisition created.');
                        this.reset();
                        bootstrap.Modal.getInstance(document.getElementById('createRequisitionModal')).hide();
                    })
                    .fail(xhr => showToast(parseError(xhr), true));
            });

            $(document).on('click', '.req-status-btn', function() {
                const id = $(this).data('id');
                const status = $(this).data('status');
                const url = routes.updateRequisitionStatus.replace('__ID__', id);
                post(url, {
                    status
                }).done(resp => applyResponseData(resp, 'Requisition updated.'))
                    .fail(xhr => showToast(parseError(xhr), true));
            });

            $(document).on('click', '.issue-open-btn', function() {
                const itemId = $(this).data('item-id');
                const remaining = Number($(this).data('remaining'));
                const sku = $(this).data('sku');
                const req = $(this).data('req');
                $('#issueRequisitionItemId').val(itemId);
                $('#issueQty').val(remaining).attr('max', remaining);
                $('#issueItemMeta').text(`Req #${req} | SKU: ${sku} | Remaining: ${remaining}`);
                issueModal.show();
            });

            $('#issueItemForm').on('submit', function(e) {
                e.preventDefault();
                post(routes.issue, $(this).serialize())
                    .done(resp => {
                        applyResponseData(resp, 'Issued successfully.');
                        issueModal.hide();
                        this.reset();
                    })
                    .fail(xhr => showToast(parseError(xhr), true));
            });

            $('#createPoForm').on('submit', function(e) {
                e.preventDefault();
                post(routes.createPo, $(this).serialize())
                    .done(resp => {
                        applyResponseData(resp, 'PO created.');
                        this.reset();
                        bootstrap.Modal.getInstance(document.getElementById('createPoModal')).hide();
                    })
                    .fail(xhr => showToast(parseError(xhr), true));
            });

            $(document).on('click', '.po-sent-btn', function() {
                const poId = $(this).data('po-id');
                const url = routes.poAction.replace('__ID__', poId);
                post(url, {
                    action: 'sent'
                }).done(resp => applyResponseData(resp, 'PO marked as sent.'))
                    .fail(xhr => showToast(parseError(xhr), true));
            });

            $(document).on('click', '.po-receive-open-btn', function() {
                $('#receivePoId').val($(this).data('po-id'));
                $('#receivePoItemId').val($(this).data('item-id'));
                $('#receiveQtyInput').val($(this).data('remaining')).attr('max', $(this).data('remaining'));
                $('#receivePoMeta').text(`${$(this).data('po-number')} | SKU: ${$(this).data('sku')} | Remaining: ${$(this).data('remaining')}`);
                receiveModal.show();
            });

            $('#receivePoForm').on('submit', function(e) {
                e.preventDefault();
                const poId = $('#receivePoId').val();
                const url = routes.poAction.replace('__ID__', poId);
                const payload = $(this).serialize() + '&action=receive';
                post(url, payload)
                    .done(resp => {
                        applyResponseData(resp, 'Stock received.');
                        receiveModal.hide();
                        this.reset();
                    })
                    .fail(xhr => showToast(parseError(xhr), true));
            });

            renderAll();
        })();
    </script>
@endsection
