@extends('layouts.vertical', ['title' => 'Spare Parts', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/select-searchable.css') }}" rel="stylesheet">
    <style>
        .sp-card {
            border-radius: 0.5rem;
            border: 1px solid #e9ecef;
        }
        .sp-card h3 {
            font-size: 1.75rem;
            margin-bottom: 0;
        }
        .nav-tabs .nav-link.active {
            font-weight: 600;
        }
        .tree ul {
            list-style: none;
            padding-left: 1.25rem;
            margin-bottom: 0;
        }
        .tree li {
            padding: 0.15rem 0;
        }
        .breadcrumb-sm {
            font-size: 0.875rem;
        }
        #tab-requisitions .card,
        #tab-requisitions .card-body,
        #tab-po .card,
        #tab-po .card-body,
        #req-lines,
        #po-lines {
            overflow: visible !important;
        }
    </style>
@endsection

@section('content')
    <div class="container-fluid py-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">Spare Parts</h4>
                <nav class="breadcrumb-sm text-muted" aria-label="breadcrumb">
                    <span>Inventory Management</span>
                    <span class="mx-1">/</span>
                    <span class="text-body">Spare Parts</span>
                </nav>
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-refresh-summary">Refresh summary</button>
        </div>

        <div class="row g-3 mb-4" id="dashboard-cards">
            @php $s = $initialSummary ?? []; @endphp
            <div class="col-6 col-md-3">
                <div class="card sp-card h-100">
                    <div class="card-body">
                        <div class="text-muted small">Total Spare Parts</div>
                        <h3 id="card-total-spare">{{ $s['total_spare_parts'] ?? 0 }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card sp-card h-100 border-warning">
                    <div class="card-body">
                        <div class="text-muted small">Low Stock Items</div>
                        <h3 class="text-warning" id="card-low-stock">{{ $s['low_stock_items'] ?? 0 }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card sp-card h-100">
                    <div class="card-body">
                        <div class="text-muted small">Pending Requisitions</div>
                        <h3 id="card-pending-req">{{ $s['pending_requisitions'] ?? 0 }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card sp-card h-100">
                    <div class="card-body">
                        <div class="text-muted small">Pending Purchase Orders</div>
                        <h3 id="card-pending-po">{{ $s['pending_purchase_orders'] ?? 0 }}</h3>
                    </div>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-requisitions" type="button">Requisitions</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-issue" type="button">Issue Parts</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-po" type="button">Purchase Orders</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-low" type="button">Low Stock</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-parts" type="button">Spare Parts &amp; Parents</button>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="tab-requisitions">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">New requisition</h5>
                        <form id="form-requisition" class="row g-2 mb-4">
                            @csrf
                            <div class="col-md-3">
                                <label class="form-label">Department</label>
                                <input type="text" name="department" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Priority</label>
                                <select name="priority" class="form-select form-select-sm">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Notes</label>
                                <input type="text" name="notes" class="form-control form-control-sm">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Lines (select part from Product Master)</label>
                                <p class="small text-muted mb-1">Each line uses a dropdown of all SKUs from <code>product_master</code> (same catalog as Product Master). Large lists may take a moment to load once.</p>
                                <div id="req-lines"></div>
                                <button type="button" class="btn btn-sm btn-outline-primary mt-1" id="btn-add-req-line">Add line</button>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary btn-sm">Save draft</button>
                            </div>
                        </form>
                        <h5 class="card-title">Recent requisitions</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped" id="table-requisitions">
                                <thead><tr><th>ID</th><th>Status</th><th>Priority</th><th>Dept</th><th>Items</th><th>Actions</th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-issue">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Issue against approved requisitions</h5>
                        <div class="table-responsive">
                            <table class="table table-sm" id="table-issues">
                                <thead><tr><th>Req</th><th>SKU</th><th>Approved</th><th>Issued</th><th>Remaining</th><th>Stock</th><th>Qty</th><th></th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-po">
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title">New spare parts PO</h5>
                        <form id="form-po" class="row g-2">
                            @csrf
                            <div class="col-md-4">
                                <label class="form-label">Supplier</label>
                                <select name="supplier_id" id="po-supplier" class="form-select form-select-sm" required></select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Expected</label>
                                <input type="date" name="expected_at" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Notes</label>
                                <input type="text" name="notes" class="form-control form-control-sm">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Lines (SKU from Product Master)</label>
                                <p class="small text-muted mb-1">Same <code>product_master</code> SKU dropdown as requisitions.</p>
                                <div id="po-lines"></div>
                                <button type="button" class="btn btn-sm btn-outline-primary mt-1" id="btn-add-po-line">Add line</button>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary btn-sm">Create PO</button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Purchase orders</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped" id="table-po">
                                <thead><tr><th>PO #</th><th>Supplier</th><th>Status</th><th>Receive</th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-low">
                <div class="card">
                    <div class="card-body">
                        <div class="row g-2 mb-2">
                            <div class="col-md-4">
                                <label class="form-label">Parent group</label>
                                <select id="filter-low-parent" class="form-select form-select-sm">
                                    <option value="">All</option>
                                    @foreach ($parentOptions as $p)
                                        <option value="{{ $p->id }}">{{ $p->sku }} @if($p->productCategory?->category_name) — {{ $p->productCategory->category_name }} @endif</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="button" class="btn btn-sm btn-secondary" id="btn-reload-low">Apply</button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm" id="table-low">
                                <thead><tr><th>SKU</th><th>Parent</th><th>Stock</th><th>Reorder at</th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-parts">
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label">Parent group</label>
                                <select id="filter-parts-parent" class="form-select form-select-sm">
                                    <option value="">All</option>
                                    @foreach ($parentOptions as $p)
                                        <option value="{{ $p->id }}">{{ $p->sku }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Spare part type</label>
                                <select id="filter-parts-type" class="form-select form-select-sm">
                                    <option value="spare">Spare parts only</option>
                                    <option value="all">All (linked to parent filter)</option>
                                    <option value="parent">Parents with children</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-sm btn-secondary" id="btn-reload-parts">Apply</button>
                            </div>
                            <div class="col-md-auto d-flex align-items-end">
                                <button type="button" class="btn btn-sm btn-primary" id="btn-open-create-spare-part" data-bs-toggle="modal" data-bs-target="#modal-create-spare-part">Create Spare Part</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-lg-7">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Parts list</h5>
                                <div class="table-responsive" style="max-height:420px;overflow:auto;">
                                    <table class="table table-sm table-hover" id="table-parts">
                                        <thead class="sticky-top bg-light"><tr><th>Part name</th><th>SKU</th><th>Qty</th><th>Supplier</th><th>Stock</th><th>Parent</th><th>Reorder</th><th>Edit</th></tr></thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Tree (top-level spares)</h5>
                                <div id="tree-wrap" class="tree small border rounded p-2" style="max-height:420px;overflow:auto;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="modal-create-spare-part" tabindex="-1" aria-labelledby="modal-create-spare-part-label" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header py-2">
                        <h5 class="modal-title fs-6" id="modal-create-spare-part-label">Create spare part</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted mb-2">Chooses a parent product and SKU from Product Master, then stores the display name and MSL-Part in <code>spare_part_details</code>.</p>
                        <div class="mb-2">
                            <label class="form-label small mb-0" for="create-spare-parent">Parent</label>
                            <select class="form-select form-select-sm select-searchable" id="create-spare-parent" required>
                                <option value="">Select parent…</option>
                                @foreach ($parentOptions as $p)
                                    <option value="{{ $p->id }}">{{ $p->sku }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small mb-0" for="create-spare-sku">SKU</label>
                            <select class="form-select form-select-sm select-searchable" id="create-spare-sku" required>
                                <option value="">Select SKU…</option>
                                @foreach ($partSkus as $sku)
                                    <option value="{{ $sku->id }}">{{ $sku->sku }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small mb-0" for="create-spare-supplier-display">Supplier</label>
                            <input type="text" class="form-control form-control-sm" id="create-spare-supplier-display" readonly placeholder="Select a SKU to load supplier">
                            <input type="hidden" id="create-spare-supplier-id" value="">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small mb-0" for="create-spare-qty">Qty</label>
                            <input type="number" class="form-control form-control-sm" id="create-spare-qty" min="1" value="1" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small mb-0" for="create-spare-part-name">Part name</label>
                            <input type="text" class="form-control form-control-sm" id="create-spare-part-name" required maxlength="255" placeholder="Shown in Parts list">
                        </div>
                        <div class="mb-0">
                            <label class="form-label small mb-0" for="create-spare-msl-part">MSL-Part</label>
                            <input type="text" class="form-control form-control-sm" id="create-spare-msl-part" maxlength="255" placeholder="Optional">
                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-sm btn-primary" id="btn-submit-create-spare-part">Save</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="toast-container position-fixed bottom-0 end-0 p-3">
            <div id="toast-ok" class="toast align-items-center text-bg-success border-0" role="alert">
                <div class="d-flex"><div class="toast-body" id="toast-ok-body"></div></div>
            </div>
        </div>
        <select id="sp-part-sku-source" class="d-none" aria-hidden="true">
            <option value="">Select SKU…</option>
            @foreach ($partSkus as $sku)
                <option value="{{ $sku->id }}">{{ $sku->sku }}</option>
            @endforeach
        </select>
    </div>
@endsection

@section('script')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function () {
            const skuSelect = document.getElementById('create-spare-sku');
            const supplierDisplay = document.getElementById('create-spare-supplier-display');
            const supplierIdInput = document.getElementById('create-spare-supplier-id');
            const saveButton = document.getElementById('btn-submit-create-spare-part');
            const parentSelect = document.getElementById('create-spare-parent');
            const partNameInput = document.getElementById('create-spare-part-name');
            const mslPartInput = document.getElementById('create-spare-msl-part');
            const qtyInput = document.getElementById('create-spare-qty');
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';

            if (!skuSelect || !supplierDisplay || !supplierIdInput || !saveButton || !parentSelect || !partNameInput || !mslPartInput || !qtyInput) {
                return;
            }

            async function loadSupplierForSku() {
                const selected = skuSelect.options[skuSelect.selectedIndex];
                const sku = selected ? selected.textContent.trim() : '';

                if (!sku) {
                    supplierDisplay.value = '';
                    supplierIdInput.value = '';
                    return;
                }

                try {
                    const response = await fetch(`/refunds-supplier-for-sku?sku=${encodeURIComponent(sku)}`, {
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    const data = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        throw new Error(data.message || 'Failed to load supplier');
                    }

                    supplierDisplay.value = data.supplier_name || '';
                    supplierIdInput.value = data.supplier_id != null ? String(data.supplier_id) : '';
                } catch (error) {
                    supplierDisplay.value = '';
                    supplierIdInput.value = '';
                }
            }

            skuSelect.addEventListener('change', loadSupplierForSku);

            saveButton.addEventListener('click', async function () {
                const parentId = parentSelect.value;
                const productMasterId = skuSelect.value;
                const partName = partNameInput.value.trim();
                const mslPart = mslPartInput.value.trim();
                const quantity = parseInt(qtyInput.value, 10);
                const supplierId = supplierIdInput.value ? parseInt(supplierIdInput.value, 10) : null;

                if (!parentId || !productMasterId) {
                    alert('Choose parent and SKU.');
                    return;
                }
                if (!partName) {
                    alert('Enter part name.');
                    return;
                }
                if (!(quantity >= 1)) {
                    alert('Quantity must be at least 1.');
                    return;
                }

                const payload = {
                    parent_id: parseInt(parentId, 10),
                    product_master_id: parseInt(productMasterId, 10),
                    part_name: partName,
                    msl_part: mslPart || null,
                    quantity: quantity,
                };
                if (supplierId) {
                    payload.supplier_id = supplierId;
                }

                try {
                    const response = await fetch('/inventory/spare-parts/api/spare-part-details', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify(payload),
                    });
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        throw new Error(data.message || 'Save failed');
                    }

                    const modalEl = document.getElementById('modal-create-spare-part');
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) {
                        modal.hide();
                    }
                    alert('Spare part saved.');
                    window.location.reload();
                } catch (error) {
                    alert(error.message || 'Save failed');
                }
            });
        })();
    </script>
@endsection
