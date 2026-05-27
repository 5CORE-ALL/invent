@extends('layouts.vertical', ['title' => 'Follow-Up History'])

@section('css')
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<style>
    .tabulator {
        font-size: 13px;
        border: 1px solid #dee2e6;
    }
    
    .tabulator .tabulator-header {
        background: linear-gradient(90deg, #e0e7ff 0%, #f4f7fa 100%);
        border-bottom: 2px solid #2563eb;
        font-weight: 600;
    }
    
    .tabulator .tabulator-header .tabulator-col {
        border-right: 1px solid #e5e7eb;
    }
    
    .tabulator-row {
        min-height: 35px !important;
    }
    
    .tabulator-row:hover {
        background-color: #f8f9fa !important;
    }
    
    .tabulator-cell {
        padding: 8px !important;
    }
    
    .delete-btn-table {
        padding: 4px 8px;
        font-size: 12px;
    }
</style>
@endsection

@section('content')
<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        @include('purchase-master.partials.page-info-toolbar', ['pageKey' => 'follow_up_history'])
                        <h4 class="mb-0">
                            <i class="fas fa-comments"></i> Follow-Up History
                        </h4>
                    </div>
                    <button class="btn btn-light btn-sm" id="add-remark-btn">
                        <i class="fas fa-plus"></i> Add New Update
                    </button>
                </div>
                <div class="card-body">
                    <!-- Summary Statistics Badges -->
                    <div class="mb-3 d-flex gap-2 flex-wrap">
                        <span class="badge bg-info text-white fs-6 px-3 py-2">
                            <i class="fas fa-building"></i> Suppliers: <strong>{{ $suppliers->count() }}</strong>
                        </span>
                        <span class="badge bg-success text-white fs-6 px-3 py-2">
                            <i class="fas fa-comments"></i> Total Updates: <strong id="total-remarks-badge">{{ $totalRemarks }}</strong>
                        </span>
                    </div>

                    <div id="remarks-table"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- History Modal -->
<div class="modal fade" id="historyModal" tabindex="-1" aria-labelledby="historyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="historyModalLabel">
                    <i class="fas fa-history"></i> Update History - <span id="history-supplier-name"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="history-content">
                    <div class="text-center py-3">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Remark Modal -->
<div class="modal fade" id="remarkModal" tabindex="-1" aria-labelledby="remarkModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="remarkModalLabel">
                    <i class="fas fa-comment-medical"></i> Add New Update
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="remark-form">
                    <div class="mb-3">
                        <label for="modal-supplier-select" class="form-label">Supplier Name <span class="text-danger">*</span></label>
                        <select id="modal-supplier-select" class="form-select" required>
                            <option value="">Select Supplier</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->name }}">{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="modal-remark-input" class="form-label">Update <span class="text-danger">*</span></label>
                        <textarea id="modal-remark-input" class="form-control" rows="4" placeholder="Enter your update here..." required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" id="save-remark-modal-btn">
                    <i class="fas fa-save"></i> Save Update
                </button>
            </div>
        </div>
    </div>
</div>


<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
// Define global functions first (before DOMContentLoaded)
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showError(message) {
    alert(message);
}

function showSuccess(message) {
    const alert = document.createElement('div');
    alert.className = 'alert alert-success alert-dismissible fade show position-fixed top-0 end-0 m-3';
    alert.style.zIndex = '9999';
    alert.innerHTML = `
        <i class="fas fa-check-circle"></i> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    document.body.appendChild(alert);
    setTimeout(() => alert.remove(), 3000);
}

// Show history modal - GLOBAL function
window.showHistory = function(supplierName) {
    console.log('showHistory called for:', supplierName);
    
    try {
        document.getElementById('history-supplier-name').textContent = supplierName;
        document.getElementById('history-content').innerHTML = `
            <div class="text-center py-3">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `;
        
        const modalElement = document.getElementById('historyModal');
        if (!modalElement) {
            console.error('History modal element not found!');
            alert('Error: Modal element not found');
            return;
        }
        
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
        
        // Fetch history
        fetch(`/purchase-master/follow-up-history/supplier/${encodeURIComponent(supplierName)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.length > 0) {
                    let html = '<table class="table table-sm table-hover">';
                    html += `
                        <thead class="table-light">
                            <tr>
                                <th style="width: 150px">Date & Time</th>
                                <th>Update</th>
                                <th style="width: 100px">Created By</th>
                                <th style="width: 80px">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                    `;
                    
                    data.data.forEach((item, index) => {
                        const date = new Date(item.created_at);
                        const day = date.getDate();
                        const month = date.toLocaleDateString('en-US', { month: 'short' });
                        const formattedTime = date.toLocaleTimeString('en-US', { 
                            hour: '2-digit', 
                            minute: '2-digit',
                            hour12: true 
                        });
                        
                        const isLatest = index === 0;
                        
                        html += `
                            <tr ${isLatest ? 'class="table-success"' : ''}>
                                <td>
                                    <strong>${day} ${month}</strong><br>
                                    <small class="text-muted">${formattedTime}</small>
                                    ${isLatest ? '<br><span class="badge bg-success badge-sm">Latest</span>' : ''}
                                </td>
                                <td>${escapeHtml(item.remark)}</td>
                                <td><small>${item.created_by ? escapeHtml(item.created_by) : '-'}</small></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteRemarkFromHistory(${item.id})" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                    
                    html += '</tbody></table>';
                    document.getElementById('history-content').innerHTML = html;
                } else {
                    document.getElementById('history-content').innerHTML = `
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No update history found.</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error loading history:', error);
                document.getElementById('history-content').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> Failed to load history.
                    </div>
                `;
            });
    } catch(error) {
        console.error('Error opening modal:', error);
        alert('Error opening modal: ' + error.message);
    }
};

// Delete remark from history modal - GLOBAL function
window.deleteRemarkFromHistory = function(id) {
    if (!confirm('Are you sure you want to delete this update?')) {
        return;
    }
    
    fetch(`/purchase-master/follow-up-history/delete/${id}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload main table
            if (typeof window.loadRemarksTable === 'function') {
                window.loadRemarksTable();
            }
            // Close and reopen history modal with updated data
            const supplierName = document.getElementById('history-supplier-name').textContent;
            bootstrap.Modal.getInstance(document.getElementById('historyModal')).hide();
            setTimeout(() => showHistory(supplierName), 300);
            showSuccess('Update deleted successfully!');
        } else {
            alert('Failed to delete update: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error deleting update:', error);
        alert('An error occurred while deleting the update.');
    });
};

// Delete remark from main table - GLOBAL function
window.deleteRemark = function(id) {
    if (!confirm('Are you sure you want to delete this update?')) {
        return;
    }
    
    fetch(`/purchase-master/follow-up-history/delete/${id}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (typeof window.loadRemarksTable === 'function') {
                window.loadRemarksTable();
            }
            showSuccess('Update deleted successfully!');
        } else {
            alert('Failed to delete update: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error deleting update:', error);
        alert('An error occurred while deleting the update.');
    });
};

document.addEventListener('DOMContentLoaded', function() {
    // Check if Bootstrap is loaded
    if (typeof bootstrap === 'undefined') {
        console.error('Bootstrap is not loaded!');
        alert('Error: Bootstrap library is not loaded. Modals will not work.');
        return;
    }
    
    let table;
    
    // Initialize Tabulator
    table = new Tabulator("#remarks-table", {
        layout: "fitColumns",
        placeholder: "No remarks found",
        pagination: false,
        height: "600px",
        columns: [
            {
                title: "#", 
                field: "id", 
                width: 70,
                hozAlign: "center",
                headerSort: false,
                formatter: function(cell) {
                    const value = cell.getValue();
                    return value ? value : '-';
                }
            },
            {
                title: "Supplier", 
                field: "supplier_name", 
                width: 150,
                headerFilter: "input",
                headerFilterPlaceholder: "Filter..."
            },
            {
                title: "Update", 
                field: "remark", 
                widthGrow: 3,
                headerFilter: "input",
                headerFilterPlaceholder: "Search updates...",
                formatter: function(cell) {
                    const value = cell.getValue();
                    if (!value || value === '') {
                        return '<span class="text-muted fst-italic">No updates yet</span>';
                    }
                    return `<div style="white-space: normal; line-height: 1.4;">${escapeHtml(value)}</div>`;
                }
            },
            {
                title: "Created By", 
                field: "created_by", 
                width: 130,
                formatter: function(cell) {
                    const value = cell.getValue();
                    return value ? `<i class="fas fa-user"></i> ${escapeHtml(value)}` : '-';
                }
            },
            {
                title: "Date & Time", 
                field: "created_at", 
                width: 130,
                sorter: "datetime",
                sorterParams: {
                    format: "YYYY-MM-DD HH:mm:ss"
                },
                formatter: function(cell) {
                    const value = cell.getValue();
                    if (!value) {
                        return '<span class="text-muted">-</span>';
                    }
                    const date = new Date(value);
                    const day = date.getDate();
                    const month = date.toLocaleDateString('en-US', { month: 'short' });
                    const formattedTime = date.toLocaleTimeString('en-US', { 
                        hour: '2-digit', 
                        minute: '2-digit',
                        hour12: true 
                    });
                    return `<div><strong>${day} ${month}</strong><br><small class="text-muted">${formattedTime}</small></div>`;
                }
            },
            {
                title: "Actions", 
                field: "id",
                width: 150,
                hozAlign: "center",
                headerSort: false,
                formatter: function(cell) {
                    const row = cell.getData();
                    const id = cell.getValue();
                    let html = '';
                    
                    // History button (always show for suppliers with data)
                    const count = row.total_count || (id ? 1 : 0);
                    if (count > 0) {
                        html += `<button class="btn btn-sm btn-outline-primary me-1" onclick="showHistory('${escapeHtml(row.supplier_name)}')" title="View History">
                            <i class="fas fa-history"></i> ${count > 1 ? `(${count})` : ''}
                        </button>`;
                    }
                    
                    // Delete button (only if there's an update to delete)
                    if (id && id !== null) {
                        html += `<button class="btn btn-sm btn-danger" onclick="deleteRemark(${id}); return false;" title="Delete">
                            <span style="color: white;">✕</span>
                        </button>`;
                    }
                    
                    return html || '<span class="text-muted">-</span>';
                }
            }
        ],
        initialSort: [
            {column: "created_at", dir: "desc"}
        ]
    });
    
    window.loadRemarksTable = loadRemarks;
    loadRemarks();
    
    document.getElementById('add-remark-btn').addEventListener('click', openAddModal);
    document.getElementById('save-remark-modal-btn').addEventListener('click', saveRemark);
    
    function loadRemarks() {
        fetch('/purchase-master/follow-up-history/remarks?supplier_name=all')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    table.setData(data.data);
                    // Update total updates badge
                    document.getElementById('total-remarks-badge').textContent = data.data.length;
                } else {
                    showError('Failed to load updates: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error loading updates:', error);
                showError('An error occurred while loading updates.');
            });
    }
    
    function openAddModal() {
        try {
            document.getElementById('modal-supplier-select').value = '';
            document.getElementById('modal-remark-input').value = '';
            const modalElement = document.getElementById('remarkModal');
            if (!modalElement) {
                console.error('Remark modal element not found!');
                alert('Error: Modal element not found');
                return;
            }
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        } catch(error) {
            console.error('Error opening add modal:', error);
            alert('Error opening modal: ' + error.message);
        }
    }
    
    window.saveRemark = function() {
        const supplierName = document.getElementById('modal-supplier-select').value;
        const remarkText = document.getElementById('modal-remark-input').value.trim();
        
        if (!supplierName) {
            alert('Please select a supplier.');
            return;
        }
        
        if (!remarkText) {
            alert('Please enter an update.');
            return;
        }
        
        fetch('/purchase-master/follow-up-history/store', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                supplier_name: supplierName,
                remark: remarkText
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const modal = bootstrap.Modal.getInstance(document.getElementById('remarkModal'));
                modal.hide();
                loadRemarks();
                showSuccess('Update saved successfully!');
            } else {
                alert('Failed to save update: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error saving update:', error);
            alert('An error occurred while saving the update.');
        });
    };
});
</script>
@endsection
