@extends('layouts.vertical', ['title' => 'User Management'])

@section('css')
    <link href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.css" rel="stylesheet">
    <style>
        .performance-tab-content {
            display: none;
        }
        .performance-tab-content.active {
            display: block;
        }
        .stat-card-performance {
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .stat-card-performance:hover {
            transform: translateY(-2px);
        }
        .performance-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-excellent { background: #10b981; color: white; }
        .badge-good { background: #3b82f6; color: white; }
        .badge-average { background: #f59e0b; color: white; }
        .badge-needs-improvement { background: #ef4444; color: white; }
        .bg-gradient-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .bg-gradient-info { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .bg-gradient-success { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .bg-gradient-warning { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
    </style>
@endsection

@section('content')
    <div class="container mt-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="text-primary fw-bold mb-1">User Management</h2>
                <p class="text-muted">View and manage users & performance</p>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <ul class="nav nav-tabs mb-4" id="userManagementTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="users-tab" data-bs-toggle="tab" data-bs-target="#users-content" type="button" role="tab">
                    <i class="ri-user-line me-2"></i>Users
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="performance-tab" data-bs-toggle="tab" data-bs-target="#performance-content" type="button" role="tab">
                    <i class="ri-bar-chart-line me-2"></i>Performance Management
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="userManagementTabContent">
            <!-- Users Tab -->
            <div class="tab-pane fade show active" id="users-content" role="tabpanel">

        <!-- Users Table Section -->
        <div class="card shadow-sm">
            <div class="card-body">
                <!-- Search Form -->
                <div class="mb-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" id="searchInput" class="form-control form-control-lg border-0 bg-light" 
                            placeholder="Search by name, email, or designation" onkeyup="filterTable()">
                    </div>
                </div>

                <!-- Users Table -->
                <div class="table-responsive">
                    <table class="table users-table align-middle" id="usersTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Designation</th>
                                @if($canEdit)
                                    <th>Action</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($users as $index => $user)
                                <tr data-user-id="{{ $user->id }}">
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle me-3">
                                                {{ strtoupper(substr($user->name, 0, 1)) }}
                                            </div>
                                            <div class="user-name-cell" data-field="name" data-original="{{ $user->name }}">
                                                <span class="user-display user-name">{{ $user->name }}</span>
                                                <input type="text" class="form-control form-control-sm user-edit d-none" value="{{ $user->name }}" data-field="name">
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="user-email-cell" data-field="email" data-original="{{ $user->email }}">
                                            <span class="user-display user-email">{{ $user->email }}</span>
                                            <input type="email" class="form-control form-control-sm user-edit d-none" value="{{ $user->email }}" data-field="email">
                                        </div>
                                    </td>
                                    <td>
                                        <div class="user-designation-cell" data-field="designation" data-original="{{ $user->designation ?? '' }}">
                                            @if($user->designation)
                                                <span class="designation-badge user-display">{{ $user->designation }}</span>
                                            @else
                                                <span class="user-display">-</span>
                                            @endif
                                            <input type="text" class="form-control form-control-sm user-edit d-none" value="{{ $user->designation ?? '' }}" data-field="designation" placeholder="Enter designation">
                                        </div>
                                    </td>
                                    @if($canEdit)
                                        <td>
                                            <div class="action-buttons">
                                                <button type="button" class="btn-action edit-btn" data-user-id="{{ $user->id }}" title="Edit">
                                                    <i class="ri-edit-line"></i>
                                                </button>
                                                @if($user->id !== auth()->id())
                                                    <button type="button" class="btn-action btn-danger-soft delete-btn" data-user-id="{{ $user->id }}" title="Deactivate user">
                                                        <i class="ri-user-unfollow-line"></i>
                                                    </button>
                                                @endif
                                                <button type="button" class="btn-action btn-success save-btn d-none" data-user-id="{{ $user->id }}" title="Save">
                                                    <i class="ri-check-line"></i>
                                                </button>
                                                <button type="button" class="btn-action btn-secondary cancel-btn d-none" data-user-id="{{ $user->id }}" title="Cancel">
                                                    <i class="ri-close-line"></i>
                                                </button>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $canEdit ? '5' : '4' }}" class="text-center py-4 text-muted">No users found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if($canEdit)
            <div class="card shadow-sm mt-4 border-warning border-opacity-25">
                <div class="card-body">
                    <h5 class="mb-1 text-dark fw-semibold">
                        <i class="ri-user-forbid-line me-2 text-warning"></i>Inactive users
                    </h5>
                    <p class="text-muted small mb-3">These accounts cannot sign in. Use <strong>Recover</strong> to activate them again.</p>
                    
                    @if($inactiveUsers->count() > 0)
                        <div class="mb-4">
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0">
                                    <i class="fas fa-search text-muted"></i>
                                </span>
                                <input type="text" id="searchInactiveInput" class="form-control form-control-lg border-0 bg-light"
                                    placeholder="Search inactive users" onkeyup="filterInactiveTable()">
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table users-table align-middle" id="inactiveUsersTable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Designation</th>
                                        <th>Deactivated at</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($inactiveUsers as $index => $iu)
                                        <tr data-inactive-user-id="{{ $iu->id }}">
                                            <td>{{ $index + 1 }}</td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-circle me-3 opacity-75">
                                                        {{ strtoupper(substr($iu->name, 0, 1)) }}
                                                    </div>
                                                    <span class="user-name">{{ $iu->name }}</span>
                                                </div>
                                            </td>
                                            <td><span class="user-email">{{ $iu->email }}</span></td>
                                            <td>
                                                @if($iu->designation)
                                                    <span class="designation-badge">{{ $iu->designation }}</span>
                                                @else
                                                    <span>-</span>
                                                @endif
                                            </td>
                                            <td><span class="text-muted small">{{ $iu->deactivated_at?->format('Y-m-d H:i') ?? '—' }}</span></td>
                                            <td>
                                                <button type="button" class="btn btn-recover restore-btn" data-user-id="{{ $iu->id }}" title="Recover — activate user">
                                                    <i class="ri-arrow-go-back-line me-1"></i> Recover
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="ri-checkbox-circle-line text-success" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-2 mb-0">No inactive users. All accounts are active.</p>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>

    <style>
        /* Table Styling */
        .users-table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
            background: #fff;
        }

        .users-table thead {
            background-color: #f8f9fa;
        }

        .users-table thead th {
            padding: 12px 16px;
            font-weight: 600;
            font-size: 14px;
            color: #495057;
            border-bottom: 1px solid #dee2e6;
            text-align: left;
        }

        .users-table tbody tr {
            border-bottom: 1px solid #e9ecef;
            transition: background-color 0.2s ease;
        }

        .users-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .users-table tbody td {
            padding: 16px;
            vertical-align: middle;
            font-size: 14px;
        }

        /* Avatar Circle */
        .avatar-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 16px;
            background-color: #e3f2fd;
            color: #1976d2;
            flex-shrink: 0;
        }

        /* User Name */
        .user-name {
            font-weight: 500;
            color: #212529;
        }

        /* User Email */
        .user-email {
            color: #6c757d;
        }

        /* Designation Badge */
        .designation-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            background-color: #e3f2fd;
            color: #1976d2;
            font-size: 13px;
            font-weight: 500;
        }

        /* Action Button */
        .btn-action {
            width: 36px;
            height: 36px;
            padding: 0;
            border: none;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            background-color: #20c997;
            color: #fff;
            font-size: 16px;
        }

        .btn-action:hover {
            background-color: #1aa179;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .btn-action:active {
            transform: translateY(0);
        }

        .btn-action.btn-success {
            background-color: #28a745;
        }

        .btn-action.btn-success:hover {
            background-color: #218838;
        }

        .btn-action.btn-secondary {
            background-color: #6c757d;
        }

        .btn-action.btn-secondary:hover {
            background-color: #5a6268;
        }

        .btn-danger-soft {
            background-color: #dc3545;
        }

        .btn-danger-soft:hover {
            background-color: #bb2d3b;
        }

        .btn-restore {
            background-color: #0d6efd;
        }

        .btn-restore:hover {
            background-color: #0b5ed7;
        }

        .btn-recover {
            display: inline-flex;
            align-items: center;
            padding: 0.45rem 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: #fff;
            background-color: #198754;
            border: none;
            border-radius: 6px;
            transition: background-color 0.2s ease, transform 0.15s ease;
        }

        .btn-recover:hover {
            background-color: #157347;
            color: #fff;
            transform: translateY(-1px);
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* Edit Mode */
        .editing-row {
            background-color: #fff3cd !important;
        }

        .user-edit {
            min-width: 150px;
            font-size: 14px;
        }

        .form-control:focus {
            box-shadow: none;
            border-color: #20c997;
        }

        /* Loading Spinner */
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .spin {
            animation: spin 1s linear infinite;
        }

        /* Card Styling */
        .card {
            border: none;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .card-header {
            border-bottom: 1px solid #e9ecef;
            padding: 16px 20px;
        }

        .card-body {
            padding: 20px;
        }
    </style>

    <script>
        const canEdit = {{ $canEdit ? 'true' : 'false' }};
        let originalData = {};

        function filterTable() {
            const input = document.getElementById('searchInput');
            if (!input) return;
            const filter = input.value.toLowerCase();
            const table = document.getElementById('usersTable');
            if (!table) return;
            const rows = table.getElementsByTagName('tr');

            for (let i = 1; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                let match = false;

                for (let j = 0; j < cells.length; j++) {
                    if (cells[j]) {
                        const text = cells[j].textContent || cells[j].innerText;
                        if (text.toLowerCase().indexOf(filter) > -1) {
                            match = true;
                            break;
                        }
                    }
                }

                if (match) {
                    rows[i].style.display = '';
                    rows[i].style.opacity = '1';
                } else {
                    rows[i].style.opacity = '0';
                    setTimeout(() => {
                        rows[i].style.display = 'none';
                    }, 300);
                }
            }
        }

        function filterInactiveTable() {
            const input = document.getElementById('searchInactiveInput');
            if (!input) return;
            const filter = input.value.toLowerCase();
            const table = document.getElementById('inactiveUsersTable');
            if (!table) return;
            const rows = table.getElementsByTagName('tr');

            for (let i = 1; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                let match = false;

                for (let j = 0; j < cells.length; j++) {
                    if (cells[j]) {
                        const text = cells[j].textContent || cells[j].innerText;
                        if (text.toLowerCase().indexOf(filter) > -1) {
                            match = true;
                            break;
                        }
                    }
                }

                if (match) {
                    rows[i].style.display = '';
                    rows[i].style.opacity = '1';
                } else {
                    rows[i].style.opacity = '0';
                    setTimeout(() => {
                        rows[i].style.display = 'none';
                    }, 300);
                }
            }
        }

        // Edit functionality
        if (canEdit) {
            document.addEventListener('DOMContentLoaded', function() {
                // Edit button click
                document.querySelectorAll('.edit-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const userId = this.dataset.userId;
                        const row = this.closest('tr');
                        originalData[userId] = {};

                        // Store original values and show edit inputs
                        row.querySelectorAll('.user-display').forEach(display => {
                            const cell = display.closest('[data-field]');
                            const field = cell.dataset.field;
                            originalData[userId][field] = cell.dataset.original;
                            
                            display.classList.add('d-none');
                            const input = cell.querySelector('.user-edit');
                            if (input) {
                                input.classList.remove('d-none');
                                input.focus();
                            }
                        });

                        // Show save/cancel, hide edit & delete
                        row.querySelector('.edit-btn').classList.add('d-none');
                        const delBtn = row.querySelector('.delete-btn');
                        if (delBtn) delBtn.classList.add('d-none');
                        row.querySelector('.save-btn').classList.remove('d-none');
                        row.querySelector('.cancel-btn').classList.remove('d-none');
                        row.classList.add('editing-row');
                    });
                });

                // Cancel button click
                document.querySelectorAll('.cancel-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const userId = this.dataset.userId;
                        const row = this.closest('tr');
                        
                        // Restore original values
                        row.querySelectorAll('[data-field]').forEach(cell => {
                            const field = cell.dataset.field;
                            const display = cell.querySelector('.user-display');
                            const input = cell.querySelector('.user-edit');
                            
                            if (originalData[userId] && originalData[userId][field] !== undefined) {
                                if (input) {
                                    input.value = originalData[userId][field];
                                    input.classList.add('d-none');
                                }
                                if (display) {
                                    display.classList.remove('d-none');
                                    if (field === 'designation') {
                                        display.textContent = originalData[userId][field] || '-';
                                        if (originalData[userId][field]) {
                                            display.className = 'designation-badge user-display';
                                        } else {
                                            display.className = 'user-display';
                                        }
                                    } else {
                                        display.textContent = originalData[userId][field];
                                    }
                                }
                            }
                        });

                        // Show edit & delete, hide save/cancel
                        row.querySelector('.edit-btn').classList.remove('d-none');
                        const delBtn2 = row.querySelector('.delete-btn');
                        if (delBtn2) delBtn2.classList.remove('d-none');
                        row.querySelector('.save-btn').classList.add('d-none');
                        row.querySelector('.cancel-btn').classList.add('d-none');
                        row.classList.remove('editing-row');
                        
                        delete originalData[userId];
                    });
                });

                // Save button click
                document.querySelectorAll('.save-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const userId = this.dataset.userId;
                        const row = this.closest('tr');
                        const saveBtn = this;
                        const originalBtnHtml = saveBtn.innerHTML;
                        
                        // Disable button and show loading
                        saveBtn.disabled = true;
                        saveBtn.innerHTML = '<i class="ri-loader-4-line spin"></i>';
                        
                        // Collect updated data
                        const data = {
                            name: row.querySelector('[data-field="name"] .user-edit').value.trim(),
                            email: row.querySelector('[data-field="email"] .user-edit').value.trim(),
                            designation: row.querySelector('[data-field="designation"] .user-edit').value.trim(),
                            _token: '{{ csrf_token() }}',
                            _method: 'PUT'
                        };

                        // Send AJAX request
                        const formData = new FormData();
                        formData.append('name', data.name);
                        formData.append('email', data.email);
                        formData.append('designation', data.designation);
                        formData.append('_token', '{{ csrf_token() }}');
                        formData.append('_method', 'PUT');

                        fetch(`/users/${userId}`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: formData
                        })
                        .then(response => response.json())
                        .then(result => {
                            if (result.success) {
                                // Update display values
                                const nameDisplay = row.querySelector('[data-field="name"] .user-display');
                                const emailDisplay = row.querySelector('[data-field="email"] .user-display');
                                const designationDisplay = row.querySelector('[data-field="designation"] .user-display');
                                
                                nameDisplay.textContent = result.user.name;
                                emailDisplay.textContent = result.user.email;
                                
                                if (result.user.designation) {
                                    designationDisplay.textContent = result.user.designation;
                                    designationDisplay.className = 'designation-badge user-display';
                                } else {
                                    designationDisplay.textContent = '-';
                                    designationDisplay.className = 'user-display';
                                }

                                // Update avatar initial
                                const avatar = row.querySelector('.avatar-circle');
                                avatar.textContent = result.user.name.charAt(0).toUpperCase();

                                // Hide inputs, show displays
                                row.querySelectorAll('.user-edit').forEach(input => {
                                    input.classList.add('d-none');
                                });
                                row.querySelectorAll('.user-display').forEach(display => {
                                    display.classList.remove('d-none');
                                });

                                // Update original data
                                row.querySelectorAll('[data-field]').forEach(cell => {
                                    const field = cell.dataset.field;
                                    cell.dataset.original = result.user[field] || '';
                                });

                                // Show edit & delete, hide save/cancel
                                row.querySelector('.edit-btn').classList.remove('d-none');
                                const delBtn3 = row.querySelector('.delete-btn');
                                if (delBtn3) delBtn3.classList.remove('d-none');
                                row.querySelector('.save-btn').classList.add('d-none');
                                row.querySelector('.cancel-btn').classList.add('d-none');
                                row.classList.remove('editing-row');
                                
                                delete originalData[userId];

                                // Show success message
                                showToast(result.message, 'success');
                            } else {
                                showToast(result.message || 'Failed to update user', 'error');
                                saveBtn.disabled = false;
                                saveBtn.innerHTML = originalBtnHtml;
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showToast('An error occurred while updating user', 'error');
                            saveBtn.disabled = false;
                            saveBtn.innerHTML = originalBtnHtml;
                        });
                    });
                });

                // Soft delete
                document.querySelectorAll('.delete-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const userId = this.dataset.userId;
                        if (!confirm('Deactivate this user? They will not be able to sign in until you use Recover.')) return;

                        fetch(`/users/${userId}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                        .then(r => r.json())
                        .then(result => {
                            if (result.success) {
                                showToast(result.message, 'success');
                                window.location.reload();
                            } else {
                                showToast(result.message || 'Delete failed', 'error');
                            }
                        })
                        .catch(() => showToast('Delete failed', 'error'));
                    });
                });

                // Restore
                document.querySelectorAll('.restore-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const userId = this.dataset.userId;
                        const formData = new FormData();
                        formData.append('_token', '{{ csrf_token() }}');

                        fetch(`/users/${userId}/restore`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: formData
                        })
                        .then(r => r.json())
                        .then(result => {
                            if (result.success) {
                                showToast(result.message, 'success');
                                window.location.reload();
                            } else {
                                showToast(result.message || 'Restore failed', 'error');
                            }
                        })
                        .catch(() => showToast('Restore failed', 'error'));
                    });
                });
            });
        }

        function showToast(message, type = 'success') {
            // Simple toast notification
            const toast = document.createElement('div');
            toast.className = `alert alert-${type === 'success' ? 'success' : 'danger'} position-fixed`;
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }
    </script>
            </div>
            <!-- End Users Tab -->

            <!-- Performance Management Tab -->
            <div class="tab-pane fade" id="performance-content" role="tabpanel">
                @include('pages.performance-management')
            </div>
            <!-- End Performance Management Tab -->
        </div>
        <!-- End Tab Content -->
    </div>
@endsection

{{-- Performance Management: scripts must be on this view so @yield('script') receives them (sections in @include are unreliable) --}}
@section('script')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="{{ asset('js/performance-management.js') }}"></script>
@endsection
