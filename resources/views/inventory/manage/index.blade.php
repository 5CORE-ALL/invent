@extends('layouts.vertical', ['title' => 'Manage Inventory', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .card {
            box-shadow: 0 0 35px 0 rgba(154, 161, 171, 0.15);
            border: none;
            margin-bottom: 24px;
        }
        .inventory-table td {
            vertical-align: middle;
        }
        .qty-badge {
            font-size: 1rem;
            padding: 0.5rem 1rem;
            min-width: 60px;
            display: inline-block;
        }
        .action-btn {
            margin: 0 2px;
        }
        .modal-header {
            background-color: #f8f9fa;
        }
        .log-timeline {
            position: relative;
            padding-left: 30px;
        }
        .log-timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        .log-item {
            position: relative;
            margin-bottom: 1.5rem;
        }
        .log-item::before {
            content: '';
            position: absolute;
            left: -24px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #0d6efd;
            border: 2px solid #fff;
        }
        .filter-section {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 0.25rem;
            margin-bottom: 1rem;
        }
    </style>
@endsection

@section('content')
    <!-- Page Title -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('any', 'index') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="#">Inventory Management</a></li>
                        <li class="breadcrumb-item active">Manage Inventory</li>
                    </ol>
                </div>
                <h4 class="page-title">
                    <i class="ri-stack-line me-2"></i>Manage Inventory & Shopify Sync
                </h4>
            </div>
        </div>
    </div>

    <!-- Filters & Export Section -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="{{ route('inventory.manage.index') }}" id="filterForm">
                        <div class="row align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">Search SKU</label>
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Enter SKU..." value="{{ request('search') }}">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="ri-search-line me-1"></i> Search
                                </button>
                                <a href="{{ route('inventory.manage.index') }}" class="btn btn-secondary">
                                    <i class="ri-refresh-line me-1"></i> Reset
                                </a>
                            </div>
                            <div class="col-md-5 text-end">
                                <button type="button" class="btn btn-success" onclick="exportInventory()">
                                    <i class="ri-file-excel-line me-1"></i> Export CSV
                                </button>
                                <button type="button" class="btn btn-info" onclick="exportWithShopify()">
                                    <i class="ri-file-download-line me-1"></i> Export with Shopify Data
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Inventory Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="header-title mb-0">
                        <i class="ri-list-check me-1"></i> Inventory List
                        <span class="badge bg-primary ms-2">{{ $inventories->total() }} SKUs</span>
                    </h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover inventory-table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>SKU</th>
                                    <th class="text-center">Available Qty</th>
                                    <th class="text-center">On Hand</th>
                                    <th>Shopify Status</th>
                                    <th>Last Updated</th>
                                    <th>Updated By</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($inventories as $item)
                                @php
                                    $inventory = $item->inventory_data;
                                    $lastLog = $item->latest_log;
                                    $availableQty = $inventory->available_qty ?? $item->available_to_sell ?? 0;
                                    $onHandQty = $inventory->on_hand ?? $item->on_hand ?? 0;
                                    $inventoryId = $inventory->id ?? null;
                                @endphp
                                <tr id="row-{{ $item->id }}">
                                    <td>
                                        <strong>{{ $item->sku }}</strong>
                                        @if($item->variant_id)
                                            <i class="ri-checkbox-circle-fill text-success ms-1" 
                                               title="Linked to Shopify"></i>
                                        @else
                                            <i class="ri-error-warning-line text-warning ms-1" 
                                               title="Not linked to Shopify"></i>
                                        @endif
                                        @if($item->product_title)
                                            <br><small class="text-muted">{{ Str::limit($item->product_title, 40) }}</small>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-primary qty-badge" id="qty-{{ $item->id }}">
                                            {{ $availableQty }}
                                        </span>
                                        @if($item->available_to_sell)
                                            <br><small class="text-muted">Shopify: {{ $item->available_to_sell }}</small>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary qty-badge">
                                            {{ $onHandQty }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($lastLog && $lastLog->pushed_to_shopify)
                                            <span class="badge bg-success">
                                                <i class="ri-check-line"></i> Synced
                                            </span>
                                            <small class="text-muted d-block">
                                                {{ $lastLog->shopify_pushed_at?->diffForHumans() }}
                                            </small>
                                        @elseif($lastLog && $lastLog->shopify_error)
                                            <span class="badge bg-danger">
                                                <i class="ri-error-warning-line"></i> Error
                                            </span>
                                        @elseif($item->variant_id)
                                            <span class="badge bg-info">
                                                <i class="ri-cloud-line"></i> In Shopify
                                            </span>
                                        @else
                                            <span class="badge bg-secondary">Not Synced</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($lastLog)
                                            {{ $lastLog->created_at->format('M d, Y H:i') }}
                                            <small class="text-muted d-block">
                                                {{ $lastLog->created_at->diffForHumans() }}
                                            </small>
                                        @else
                                            <span class="text-muted">Never</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($lastLog && $lastLog->creator)
                                            {{ $lastLog->creator->name }}
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($inventoryId)
                                            <button class="btn btn-sm btn-primary action-btn" 
                                                    onclick="openUpdateModal({{ $inventoryId }}, '{{ $item->sku }}', {{ $availableQty }})"
                                                    title="Update Quantity">
                                                <i class="ri-edit-line"></i>
                                            </button>
                                            <button class="btn btn-sm btn-info action-btn" 
                                                    onclick="viewLogs({{ $inventoryId }}, '{{ $item->sku }}')"
                                                    title="View History">
                                                <i class="ri-history-line"></i>
                                            </button>
                                            @if($inventory->shopify_inventory_item_id)
                                                <button class="btn btn-sm btn-success action-btn" 
                                                        onclick="syncToShopify({{ $inventoryId }})"
                                                        title="Sync to Shopify">
                                                    <i class="ri-refresh-line"></i>
                                                </button>
                                            @endif
                                        @else
                                            <button class="btn btn-sm btn-secondary action-btn" disabled
                                                    title="Not in Inventory Master">
                                                <i class="ri-lock-line"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <i class="ri-inbox-line" style="font-size: 3rem; color: #ccc;"></i>
                                        <p class="text-muted mt-2">No inventory found</p>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($inventories->hasPages())
                    <div class="mt-3">
                        {{ $inventories->links() }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@section('modal')
    <!-- Update Quantity Modal -->
    <div class="modal fade" id="updateQtyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="ri-edit-line me-1"></i> Update Quantity
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="updateQtyForm">
                        <input type="hidden" id="update_inventory_id" name="inventory_id">
                        
                        <div class="mb-3">
                            <label class="form-label">SKU</label>
                            <input type="text" class="form-control" id="update_sku" readonly>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Current Quantity</label>
                                    <input type="text" class="form-control" id="update_current_qty" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">New Quantity <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="update_new_qty" 
                                           name="new_qty" required min="0">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" id="update_notes" name="notes" 
                                      rows="3" placeholder="Reason for update..."></textarea>
                        </div>

                        <div class="alert alert-info">
                            <i class="ri-information-line me-1"></i>
                            This will update the inventory and automatically sync to Shopify (if linked).
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitUpdate()">
                        <i class="ri-save-line me-1"></i> Update & Sync
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Logs Modal -->
    <div class="modal fade" id="logsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="ri-history-line me-1"></i> Inventory History: <span id="logs_sku"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="logsContent" class="log-timeline">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        const updateModal = new bootstrap.Modal(document.getElementById('updateQtyModal'));
        const logsModal = new bootstrap.Modal(document.getElementById('logsModal'));

        function openUpdateModal(id, sku, currentQty) {
            document.getElementById('update_inventory_id').value = id;
            document.getElementById('update_sku').value = sku;
            document.getElementById('update_current_qty').value = currentQty;
            document.getElementById('update_new_qty').value = currentQty;
            document.getElementById('update_notes').value = '';
            updateModal.show();
        }

        async function submitUpdate() {
            const formData = new FormData(document.getElementById('updateQtyForm'));
            
            try {
                const response = await fetch('{{ route("inventory.manage.update") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    alert('✅ Inventory updated successfully!\n\n' +
                          'Old Qty: ' + data.data.old_qty + '\n' +
                          'New Qty: ' + data.data.new_qty + '\n' +
                          'Shopify: ' + (data.data.shopify_pushed ? 'Synced ✓' : 'Not synced'));
                    updateModal.hide();
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            } catch (error) {
                alert('❌ Error: ' + error.message);
            }
        }

        async function viewLogs(inventoryId, sku) {
            document.getElementById('logs_sku').textContent = sku;
            document.getElementById('logsContent').innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>`;
            logsModal.show();

            try {
                const response = await fetch(`/inventory/manage/${inventoryId}/logs`);
                const data = await response.json();

                if (data.success && data.logs.data.length > 0) {
                    let html = '';
                    data.logs.data.forEach(log => {
                        const source = log.change_source.replace(/_/g, ' ').toUpperCase();
                        const icon = log.pushed_to_shopify ? 'ri-check-line text-success' : 'ri-time-line text-warning';
                        
                        html += `
                            <div class="log-item">
                                <div class="card mb-2">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1">
                                                    <i class="${icon} me-1"></i>
                                                    ${log.old_qty} → ${log.new_qty} 
                                                    <span class="badge bg-${log.qty_change >= 0 ? 'success' : 'danger'}">
                                                        ${log.qty_change >= 0 ? '+' : ''}${log.qty_change}
                                                    </span>
                                                </h6>
                                                <p class="text-muted mb-1 small">${source}</p>
                                                ${log.notes ? `<p class="mb-1">${log.notes}</p>` : ''}
                                                ${log.shopify_error ? `<p class="text-danger small mb-0"><i class="ri-error-warning-line"></i> ${log.shopify_error}</p>` : ''}
                                            </div>
                                            <div class="text-end">
                                                <small class="text-muted">${new Date(log.created_at).toLocaleString()}</small>
                                                ${log.creator ? `<br><small class="text-muted">by ${log.creator.name}</small>` : ''}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>`;
                    });
                    document.getElementById('logsContent').innerHTML = html;
                } else {
                    document.getElementById('logsContent').innerHTML = `
                        <div class="text-center py-4 text-muted">
                            <i class="ri-inbox-line" style="font-size: 2rem;"></i>
                            <p class="mt-2">No history found</p>
                        </div>`;
                }
            } catch (error) {
                document.getElementById('logsContent').innerHTML = `
                    <div class="alert alert-danger">Error loading logs: ${error.message}</div>`;
            }
        }

        async function syncToShopify(inventoryId) {
            if (!confirm('Sync this SKU to Shopify now?')) return;

            try {
                const response = await fetch('{{ route("inventory.manage.sync") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ inventory_id: inventoryId })
                });

                const data = await response.json();

                if (data.success) {
                    alert('✅ Successfully synced to Shopify!');
                    location.reload();
                } else {
                    alert('❌ Sync failed: ' + data.message);
                }
            } catch (error) {
                alert('❌ Error: ' + error.message);
            }
        }

        function exportInventory() {
            const params = new URLSearchParams(window.location.search);
            window.location.href = '{{ route("inventory.manage.export") }}?' + params.toString();
        }

        function exportWithShopify() {
            const params = new URLSearchParams(window.location.search);
            window.location.href = '{{ route("inventory.manage.export-shopify") }}?' + params.toString();
        }
    </script>
@endsection
