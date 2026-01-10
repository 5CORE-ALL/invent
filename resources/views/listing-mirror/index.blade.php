@extends('layouts.vertical', ['title' => 'Listing Mirror', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

<meta name="csrf-token" content="{{ csrf_token() }}">

@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    .listing-mirror-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .listing-mirror-table th,
    .listing-mirror-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }
    
    .listing-mirror-table th {
        background-color: #f8f9fa;
        font-weight: 600;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    
    .sync-btn {
        padding: 6px 12px;
        margin: 2px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
        transition: all 0.3s;
    }
    
    .sync-btn:hover {
        opacity: 0.9;
        transform: translateY(-1px);
    }
    
    .sync-btn.inventory {
        background-color: #28a745;
        color: white;
    }
    
    .sync-btn.price {
        background-color: #007bff;
        color: white;
    }
    
    .sync-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .channel-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        margin: 2px;
    }
    
    .channel-available {
        background-color: #d4edda;
        color: #155724;
    }
    
    .channel-unavailable {
        background-color: #f8d7da;
        color: #721c24;
    }
    
    .sync-status {
        font-size: 11px;
        padding: 4px 8px;
        border-radius: 4px;
    }
    
    .status-completed {
        background-color: #d4edda;
        color: #155724;
    }
    
    .status-failed {
        background-color: #f8d7da;
        color: #721c24;
    }
    
    .status-processing {
        background-color: #fff3cd;
        color: #856404;
    }
    
    .status-pending {
        background-color: #d1ecf1;
        color: #0c5460;
    }
    
    .product-image {
        width: 50px;
        height: 50px;
        object-fit: cover;
        border-radius: 4px;
    }
    
    .search-filters {
        background: white;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .bulk-actions {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    
    .alert {
        padding: 12px 20px;
        border-radius: 4px;
        margin-bottom: 20px;
    }
    
    .alert-success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .alert-error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
</style>
@endsection

@section('content')
@include('layouts.shared/page-title', ['sub_title' => 'Multi-Channel', 'page_title' => 'Listing Mirror'])

<div class="row">
    <div class="col-12">
        <!-- Search and Filters -->
        <div class="search-filters">
            <form method="GET" action="{{ route('listing-mirror.index') }}" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Search by SKU, ASIN, or Title" value="{{ $search }}">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="Active" {{ $status == 'Active' ? 'selected' : '' }}>Active</option>
                        <option value="Inactive" {{ $status == 'Inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Search</button>
                </div>
                <div class="col-md-3">
                    <a href="{{ route('listing-mirror.index') }}" class="btn btn-secondary w-100">Clear</a>
                </div>
            </form>
        </div>

        <!-- Bulk Actions -->
        <div class="bulk-actions">
            <div class="row align-items-center">
                <div class="col-md-3">
                    <strong>Bulk Actions:</strong>
                </div>
                <div class="col-md-3">
                    <select id="bulkChannel" class="form-select form-select-sm">
                        <option value="shopify">Shopify</option>
                        <option value="ebay">eBay</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select id="bulkSyncType" class="form-select form-select-sm">
                        <option value="inventory">Inventory Only</option>
                        <option value="price">Price Only</option>
                        <option value="both">Both Inventory & Price</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="button" class="btn btn-primary btn-sm w-100" onclick="bulkSync()">
                        <i class="fas fa-sync"></i> Sync Selected
                    </button>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <div id="alertContainer"></div>

        <!-- Listings Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="listing-mirror-table">
                        <thead>
                            <tr>
                                <th>
                                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                                </th>
                                <th>Image</th>
                                <th>SKU</th>
                                <th>ASIN</th>
                                <th>Title</th>
                                <th>Amazon Price</th>
                                <th>Amazon Inventory</th>
                                <th>Status</th>
                                <th>Channels</th>
                                <th>Last Sync</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($listings as $listing)
                            <tr data-sku="{{ $listing->sku }}">
                                <td>
                                    <input type="checkbox" class="listing-checkbox" value="{{ $listing->sku }}">
                                </td>
                                <td>
                                    @if($listing->image)
                                    <img src="{{ $listing->image }}" alt="{{ $listing->sku }}" class="product-image">
                                    @else
                                    <div class="product-image" style="background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-image"></i>
                                    </div>
                                    @endif
                                </td>
                                <td><strong>{{ $listing->sku }}</strong></td>
                                <td>{{ $listing->asin ?? 'N/A' }}</td>
                                <td>{{ $listing->title ?? 'N/A' }}</td>
                                <td>${{ number_format($listing->price ?? 0, 2) }}</td>
                                <td>
                                    {{ $listing->inventory_amazon ?? ($listing->fba_quantity ?? 0) }}
                                    @if($listing->fba_quantity)
                                    <span class="badge bg-info">FBA</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $listing->listing_status == 'Active' ? 'success' : 'secondary' }}">
                                        {{ $listing->listing_status ?? 'Unknown' }}
                                    </span>
                                </td>
                                <td>
                                    @if($listing->shopify_available)
                                    <span class="channel-badge channel-available">Shopify</span>
                                    @else
                                    <span class="channel-badge channel-unavailable">Shopify</span>
                                    @endif
                                    
                                    @if($listing->ebay_available)
                                    <span class="channel-badge channel-available">eBay</span>
                                    @else
                                    <span class="channel-badge channel-unavailable">eBay</span>
                                    @endif
                                </td>
                                <td>
                                    @if($listing->last_sync)
                                    <div>
                                        <small class="sync-status status-{{ $listing->last_sync->status }}">
                                            {{ ucfirst($listing->last_sync->status) }}
                                        </small>
                                        <br>
                                        <small class="text-muted">
                                            {{ $listing->last_sync->synced_at ? $listing->last_sync->synced_at->diffForHumans() : 'Never' }}
                                        </small>
                                    </div>
                                    @else
                                    <small class="text-muted">Never</small>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group-vertical" style="gap: 4px;">
                                        @if($listing->shopify_available)
                                        <button class="sync-btn inventory" onclick="syncInventory('{{ $listing->sku }}', 'shopify')" title="Sync Inventory to Shopify">
                                            <i class="fas fa-box"></i> Inv (Shopify)
                                        </button>
                                        <button class="sync-btn price" onclick="syncPrice('{{ $listing->sku }}', 'shopify')" title="Sync Price to Shopify">
                                            <i class="fas fa-dollar-sign"></i> Price (Shopify)
                                        </button>
                                        @endif
                                        
                                        @if($listing->ebay_available)
                                        <button class="sync-btn inventory" onclick="syncInventory('{{ $listing->sku }}', 'ebay')" title="Sync Inventory to eBay">
                                            <i class="fas fa-box"></i> Inv (eBay)
                                        </button>
                                        <button class="sync-btn price" onclick="syncPrice('{{ $listing->sku }}', 'ebay')" title="Sync Price to eBay">
                                            <i class="fas fa-dollar-sign"></i> Price (eBay)
                                        </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="11" class="text-center py-4">
                                    <p class="text-muted">No listings found. Try adjusting your search filters.</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="mt-3">
                    {{ $listings->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('modal')
<!-- Sync History Modal (can be added later) -->
@endsection

@push('scripts')
<script>
    // Set up CSRF token for AJAX requests
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    function showAlert(message, type = 'success') {
        const alertContainer = document.getElementById('alertContainer');
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" onclick="this.parentElement.remove()" style="float: right;"></button>
        `;
        alertContainer.appendChild(alert);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            alert.remove();
        }, 5000);
    }

    function syncInventory(sku, channel) {
        if (!confirm(`Sync inventory for SKU ${sku} to ${channel}?`)) {
            return;
        }

        const btn = event.target.closest('.sync-btn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Syncing...';

        $.ajax({
            url: '{{ route("listing-mirror.sync-inventory") }}',
            method: 'POST',
            data: {
                sku: sku,
                channel: channel
            },
            success: function(response) {
                if (response.success) {
                    showAlert(`Inventory synced to ${channel} successfully!`, 'success');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showAlert(`Error: ${response.message}`, 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-box"></i> Inv (' + channel.charAt(0).toUpperCase() + channel.slice(1) + ')';
                }
            },
            error: function(xhr) {
                const errorMsg = xhr.responseJSON?.message || 'An error occurred';
                showAlert(`Error: ${errorMsg}`, 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-box"></i> Inv (' + channel.charAt(0).toUpperCase() + channel.slice(1) + ')';
            }
        });
    }

    function syncPrice(sku, channel) {
        if (!confirm(`Sync price for SKU ${sku} to ${channel}?`)) {
            return;
        }

        const btn = event.target.closest('.sync-btn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Syncing...';

        $.ajax({
            url: '{{ route("listing-mirror.sync-price") }}',
            method: 'POST',
            data: {
                sku: sku,
                channel: channel
            },
            success: function(response) {
                if (response.success) {
                    showAlert(`Price synced to ${channel} successfully!`, 'success');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showAlert(`Error: ${response.message}`, 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-dollar-sign"></i> Price (' + channel.charAt(0).toUpperCase() + channel.slice(1) + ')';
                }
            },
            error: function(xhr) {
                const errorMsg = xhr.responseJSON?.message || 'An error occurred';
                showAlert(`Error: ${errorMsg}`, 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-dollar-sign"></i> Price (' + channel.charAt(0).toUpperCase() + channel.slice(1) + ')';
            }
        });
    }

    function toggleSelectAll(checkbox) {
        const checkboxes = document.querySelectorAll('.listing-checkbox');
        checkboxes.forEach(cb => cb.checked = checkbox.checked);
    }

    function bulkSync() {
        const selected = Array.from(document.querySelectorAll('.listing-checkbox:checked'))
            .map(cb => cb.value);
        
        if (selected.length === 0) {
            showAlert('Please select at least one listing to sync.', 'error');
            return;
        }

        const channel = document.getElementById('bulkChannel').value;
        const syncType = document.getElementById('bulkSyncType').value;

        if (!confirm(`Sync ${selected.length} listing(s) to ${channel}?`)) {
            return;
        }

        $.ajax({
            url: '{{ route("listing-mirror.bulk-sync") }}',
            method: 'POST',
            data: {
                skus: selected,
                channel: channel,
                sync_type: syncType
            },
            success: function(response) {
                if (response.success) {
                    showAlert(`Bulk sync initiated for ${selected.length} listings!`, 'success');
                    setTimeout(() => location.reload(), 3000);
                } else {
                    showAlert(`Error: ${response.message}`, 'error');
                }
            },
            error: function(xhr) {
                const errorMsg = xhr.responseJSON?.message || 'An error occurred';
                showAlert(`Error: ${errorMsg}`, 'error');
            }
        });
    }
</script>
@endpush
