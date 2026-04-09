@extends('layouts.vertical', ['title' => 'Team Management'])

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
                <h2 class="text-primary fw-bold mb-1">Team Management</h2>
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
                            placeholder="Search by name, phone, email, designation, R&amp;R, resources, training, or checklist" onkeyup="filterTable()">
                    </div>
                </div>

                <!-- Users Table -->
                <div class="table-responsive">
                    <table class="table users-table align-middle" id="usersTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th scope="col" class="text-center" title="Phone" aria-label="Phone">
                                    <i class="ri-phone-line fs-5 text-secondary" aria-hidden="true"></i>
                                </th>
                                <th scope="col" class="text-center" title="Email" aria-label="Email">
                                    <i class="ri-mail-line fs-5 text-secondary" aria-hidden="true"></i>
                                </th>
                                <th>Designation</th>
                                <th>R&amp;R</th>
                                <th>Resources</th>
                                <th>Training</th>
                                <th>Checklist</th>
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
                                        <div class="user-phone-cell" data-field="phone" data-original="{{ $user->phone ?? '' }}">
                                            <span class="user-display user-phone">
                                                @if($user->phone)
                                                    <span class="phone-dot phone-dot--green phone-dot--has-tooltip" data-tooltip="{{ e($user->phone) }}" aria-label="Phone: {{ e($user->phone) }}"></span>
                                                    <span class="visually-hidden user-phone-search-text">{{ $user->phone }}</span>
                                                @else
                                                    <span class="phone-dot phone-dot--red phone-dot--has-tooltip" data-tooltip="No phone number" aria-label="No phone"></span>
                                                @endif
                                            </span>
                                            <input type="text" class="form-control form-control-sm user-edit d-none" value="{{ $user->phone ?? '' }}" data-field="phone" placeholder="Phone number" maxlength="20">
                                        </div>
                                    </td>
                                    <td>
                                        <div class="user-email-cell" data-field="email" data-original="{{ $user->email }}">
                                            <span class="user-display user-email">
                                                @if($user->email)
                                                    <span class="phone-dot phone-dot--green phone-dot--has-tooltip" data-tooltip="{{ e($user->email) }}" aria-label="Email: {{ e($user->email) }}"></span>
                                                    <span class="visually-hidden user-email-search-text">{{ $user->email }}</span>
                                                @else
                                                    <span class="phone-dot phone-dot--red phone-dot--has-tooltip" data-tooltip="No email" aria-label="No email"></span>
                                                @endif
                                            </span>
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
                                    <td>
                                        @php
                                            $rrRole = $user->userRR?->role ?? '';
                                            $hasRrPortfolio = ($user->rr_portfolio_assignments_count ?? 0) > 0;
                                            $rrDotGreen = trim($rrRole) !== '' || $hasRrPortfolio;
                                            $rrTooltip = $rrRole !== '' ? $rrRole : ($hasRrPortfolio ? 'R&R portfolio assigned' : '');
                                        @endphp
                                        <div class="user-rr-cell" data-field="rr_role" data-original="{{ $rrRole }}" data-portfolio-url="{{ route('users.rr-portfolio.show', $user) }}" data-has-portfolio="{{ $hasRrPortfolio ? '1' : '0' }}">
                                            <span class="user-display user-rr-display">
                                                <a href="{{ route('users.rr-portfolio.show', $user) }}" class="rr-portfolio-link text-decoration-none d-inline-flex align-items-center" title="View R&amp;R portfolio">
                                                @if($rrDotGreen)
                                                    <span class="phone-dot phone-dot--green phone-dot--has-tooltip" data-tooltip="{{ e($rrTooltip) }}" aria-label="{{ e($rrRole !== '' ? 'R&R: '.$rrRole : 'R&R portfolio assigned') }}"></span>
                                                    <span class="visually-hidden user-rr-search-text">{{ $rrRole }}@if($hasRrPortfolio && $rrRole === '') R&amp;R portfolio @endif</span>
                                                @else
                                                    <span class="phone-dot phone-dot--red phone-dot--has-tooltip" data-tooltip="No R&amp;R" aria-label="No R&amp;R"></span>
                                                @endif
                                                </a>
                                            </span>
                                            <input type="text" class="form-control form-control-sm user-edit d-none" value="{{ $rrRole }}" data-field="rr_role" placeholder="Role &amp; responsibility summary" maxlength="255">
                                        </div>
                                    </td>
                                    <td>
                                        @php $resourcesVal = $user->userRR?->resources ?? ''; @endphp
                                        <div class="user-resources-cell" data-field="resources">
                                            @if($resourcesVal !== '')
                                                <span class="user-display small text-break d-block user-resources-display" style="max-width: 280px;">{{ $resourcesVal }}</span>
                                            @else
                                                <span class="user-display">-</span>
                                            @endif
                                            <textarea class="form-control form-control-sm user-edit d-none" rows="2" data-field="resources" placeholder="Links, docs, or notes">{{ $resourcesVal }}</textarea>
                                        </div>
                                    </td>
                                    <td>
                                        @php $trainingVal = $user->userRR?->training ?? ''; @endphp
                                        <div class="user-training-cell" data-field="training">
                                            @if($trainingVal !== '')
                                                <span class="user-display small text-break d-block user-training-display" style="max-width: 280px;">{{ $trainingVal }}</span>
                                            @else
                                                <span class="user-display">-</span>
                                            @endif
                                            <textarea class="form-control form-control-sm user-edit d-none" rows="2" data-field="training" placeholder="Training notes or link">{{ $trainingVal }}</textarea>
                                        </div>
                                    </td>
                                    <td class="user-checklist-cell" data-checklist-base="{{ url('/performance/checklist') }}">
                                        <div class="checklist-cell-inner">
                                            @if(!empty($user->designation))
                                                <a href="{{ route('performance.checklist.get', ['designationId' => $user->designation]) }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary">View</a>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </div>
                                    </td>
                                    @if($canEdit)
                                        <td>
                                            <div class="action-buttons">
                                                <button type="button" class="btn-action edit-btn" data-user-id="{{ $user->id }}" title="Edit">
                                                    <i class="ri-edit-line"></i>
                                                </button>
                                                <button type="button" class="btn-action btn-user-icon user-icon-btn" data-user-id="{{ $user->id }}" title="User">
                                                    <i class="ri-user-line"></i>
                                                </button>
                                                @if($user->id !== auth()->id())
                                                    <button type="button" class="btn-action btn-danger-soft delete-btn" data-user-id="{{ $user->id }}" title="Deactivate user">
                                                        <i class="ri-delete-bin-line"></i>
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
                                    <td colspan="{{ $canEdit ? '10' : '9' }}" class="text-center py-4 text-muted">No users found</td>
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
                                        <th scope="col" class="text-center" title="Phone" aria-label="Phone">
                                            <i class="ri-phone-line fs-5 text-secondary" aria-hidden="true"></i>
                                        </th>
                                        <th scope="col" class="text-center" title="Email" aria-label="Email">
                                            <i class="ri-mail-line fs-5 text-secondary" aria-hidden="true"></i>
                                        </th>
                                        <th>Designation</th>
                                        <th>R&amp;R</th>
                                        <th>Resources</th>
                                        <th>Training</th>
                                        <th>Checklist</th>
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
                                            <td>
                                                @if($iu->phone)
                                                    <span class="phone-dot phone-dot--green phone-dot--has-tooltip" data-tooltip="{{ e($iu->phone) }}" aria-label="Phone: {{ e($iu->phone) }}"></span>
                                                    <span class="visually-hidden">{{ $iu->phone }}</span>
                                                @else
                                                    <span class="phone-dot phone-dot--red phone-dot--has-tooltip" data-tooltip="No phone number" aria-label="No phone"></span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($iu->email)
                                                    <span class="phone-dot phone-dot--green phone-dot--has-tooltip" data-tooltip="{{ e($iu->email) }}" aria-label="Email: {{ e($iu->email) }}"></span>
                                                    <span class="visually-hidden">{{ $iu->email }}</span>
                                                @else
                                                    <span class="phone-dot phone-dot--red phone-dot--has-tooltip" data-tooltip="No email" aria-label="No email"></span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($iu->designation)
                                                    <span class="designation-badge">{{ $iu->designation }}</span>
                                                @else
                                                    <span>-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @php
                                                    $iuRr = $iu->userRR?->role ?? '';
                                                    $iuHasPf = ($iu->rr_portfolio_assignments_count ?? 0) > 0;
                                                    $iuRrGreen = trim($iuRr) !== '' || $iuHasPf;
                                                    $iuRrTip = $iuRr !== '' ? $iuRr : ($iuHasPf ? 'R&R portfolio assigned' : '');
                                                @endphp
                                                <a href="{{ route('users.rr-portfolio.show', $iu) }}" class="rr-portfolio-link text-decoration-none d-inline-flex align-items-center" title="View R&amp;R portfolio">
                                                    @if($iuRrGreen)
                                                        <span class="phone-dot phone-dot--green phone-dot--has-tooltip" data-tooltip="{{ e($iuRrTip) }}" aria-label="{{ e($iuRr !== '' ? 'R&R: '.$iuRr : 'R&R portfolio assigned') }}"></span>
                                                        <span class="visually-hidden">{{ $iuRr }}@if($iuHasPf && $iuRr === '') R&amp;R portfolio @endif</span>
                                                    @else
                                                        <span class="phone-dot phone-dot--red phone-dot--has-tooltip" data-tooltip="No R&amp;R" aria-label="No R&amp;R"></span>
                                                    @endif
                                                </a>
                                            </td>
                                            <td>
                                                @php $iuResources = $iu->userRR?->resources ?? ''; @endphp
                                                @if($iuResources !== '')
                                                    <span class="small text-break d-block" style="max-width: 280px;">{{ $iuResources }}</span>
                                                @else
                                                    <span>-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @php $iuTraining = $iu->userRR?->training ?? ''; @endphp
                                                @if($iuTraining !== '')
                                                    <span class="small text-break d-block" style="max-width: 280px;">{{ $iuTraining }}</span>
                                                @else
                                                    <span>-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if(!empty($iu->designation))
                                                    <a href="{{ route('performance.checklist.get', ['designationId' => $iu->designation]) }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary">View</a>
                                                @else
                                                    <span class="text-muted">—</span>
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
            overflow: visible;
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

        .user-phone {
            color: #6c757d;
        }

        .phone-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            vertical-align: middle;
            flex-shrink: 0;
        }

        .phone-dot--green {
            background-color: #22c55e;
            box-shadow: 0 0 0 1px rgba(34, 197, 94, 0.35);
            cursor: help;
        }

        .phone-dot--red {
            background-color: #ef4444;
            box-shadow: 0 0 0 1px rgba(239, 68, 68, 0.35);
            cursor: default;
        }

        /* Large readable hover text (native title tooltips are tiny and cannot be styled) */
        .phone-dot.phone-dot--has-tooltip {
            position: relative;
            z-index: 1;
        }

        .phone-dot.phone-dot--has-tooltip:hover {
            z-index: 10050;
        }

        .phone-dot.phone-dot--has-tooltip:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            left: 50%;
            bottom: calc(100% + 12px);
            transform: translateX(-50%);
            padding: 14px 18px;
            min-width: 12rem;
            max-width: min(28rem, 92vw);
            background: #111827;
            color: #f9fafb;
            font-size: 1.5rem;
            line-height: 1.45;
            font-weight: 500;
            letter-spacing: 0.02em;
            border-radius: 12px;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.28);
            white-space: pre-wrap;
            word-break: break-word;
            pointer-events: none;
            text-align: center;
        }

        /* Native .table-responsive clips overflow; allow tall tooltips to show */
        .container.mt-4 .table-responsive {
            overflow-x: auto;
            overflow-y: visible;
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

        .btn-user-icon {
            background-color: #6c757d;
        }

        .btn-user-icon:hover {
            background-color: #5a6268;
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

        function renderPhoneDisplay(el, phone) {
            if (!el) return;
            const p = (phone != null ? String(phone) : '').trim();
            el.className = 'user-display user-phone';
            el.textContent = '';
            if (p) {
                const dot = document.createElement('span');
                dot.className = 'phone-dot phone-dot--green phone-dot--has-tooltip';
                dot.setAttribute('data-tooltip', p);
                dot.setAttribute('aria-label', 'Phone: ' + p);
                const hidden = document.createElement('span');
                hidden.className = 'visually-hidden user-phone-search-text';
                hidden.textContent = p;
                el.appendChild(dot);
                el.appendChild(hidden);
            } else {
                const dot = document.createElement('span');
                dot.className = 'phone-dot phone-dot--red phone-dot--has-tooltip';
                dot.setAttribute('data-tooltip', 'No phone number');
                dot.setAttribute('aria-label', 'No phone');
                el.appendChild(dot);
            }
        }

        function renderEmailDisplay(el, email) {
            if (!el) return;
            const e = (email != null ? String(email) : '').trim();
            el.className = 'user-display user-email';
            el.textContent = '';
            if (e) {
                const dot = document.createElement('span');
                dot.className = 'phone-dot phone-dot--green phone-dot--has-tooltip';
                dot.setAttribute('data-tooltip', e);
                dot.setAttribute('aria-label', 'Email: ' + e);
                const hidden = document.createElement('span');
                hidden.className = 'visually-hidden user-email-search-text';
                hidden.textContent = e;
                el.appendChild(dot);
                el.appendChild(hidden);
            } else {
                const dot = document.createElement('span');
                dot.className = 'phone-dot phone-dot--red phone-dot--has-tooltip';
                dot.setAttribute('data-tooltip', 'No email');
                dot.setAttribute('aria-label', 'No email');
                el.appendChild(dot);
            }
        }

        function renderRrRoleDisplay(el, rrRole) {
            if (!el) return;
            const cell = el.closest('.user-rr-cell');
            const portfolioUrl = cell?.dataset?.portfolioUrl || '#';
            const hasPortfolio = cell?.dataset?.hasPortfolio === '1';
            const r = (rrRole != null ? String(rrRole) : '').trim();
            const showGreen = r.length > 0 || hasPortfolio;
            const tip = r || (hasPortfolio ? 'R&R portfolio assigned' : '');
            el.className = 'user-display user-rr-display';
            el.textContent = '';
            const link = document.createElement('a');
            link.href = portfolioUrl;
            link.className = 'rr-portfolio-link text-decoration-none d-inline-flex align-items-center';
            link.title = 'View R&R portfolio';
            if (showGreen) {
                const dot = document.createElement('span');
                dot.className = 'phone-dot phone-dot--green phone-dot--has-tooltip';
                dot.setAttribute('data-tooltip', tip);
                dot.setAttribute('aria-label', r ? ('R&R: ' + r) : 'R&R portfolio assigned');
                link.appendChild(dot);
                el.appendChild(link);
                const hidden = document.createElement('span');
                hidden.className = 'visually-hidden user-rr-search-text';
                hidden.textContent = r + (hasPortfolio && !r ? ' R&R portfolio ' : '');
                el.appendChild(hidden);
            } else {
                const dot = document.createElement('span');
                dot.className = 'phone-dot phone-dot--red phone-dot--has-tooltip';
                dot.setAttribute('data-tooltip', 'No R&R');
                dot.setAttribute('aria-label', 'No R&R');
                link.appendChild(dot);
                el.appendChild(link);
            }
        }

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

        function updateChecklistCell(row) {
            const wrap = row.querySelector('.user-checklist-cell');
            const inner = row.querySelector('.checklist-cell-inner');
            if (!wrap || !inner) return;
            const base = wrap.dataset.checklistBase || '';
            const desCell = row.querySelector('[data-field="designation"] .user-display');
            let des = '';
            if (desCell) {
                const t = (desCell.textContent || '').trim();
                if (t && t !== '-') {
                    des = t;
                }
            }
            if (des && base) {
                inner.innerHTML = '<a href="' + base + '/' + encodeURIComponent(des) + '" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary">View</a>';
            } else {
                inner.innerHTML = '<span class="text-muted">—</span>';
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

                        const trainingCellEdit = row.querySelector('[data-field="training"]');
                        if (trainingCellEdit) {
                            const ta = trainingCellEdit.querySelector('textarea.user-edit');
                            if (ta) {
                                originalData[userId]['training'] = ta.value;
                            }
                        }

                        const resourcesCellEdit = row.querySelector('[data-field="resources"]');
                        if (resourcesCellEdit) {
                            const ta = resourcesCellEdit.querySelector('textarea.user-edit');
                            if (ta) {
                                originalData[userId]['resources'] = ta.value;
                            }
                        }

                        // Show save/cancel, hide edit & delete
                        row.querySelector('.edit-btn').classList.add('d-none');
                        const userIconBtn = row.querySelector('.user-icon-btn');
                        if (userIconBtn) userIconBtn.classList.add('d-none');
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
                                    } else if (field === 'rr_role') {
                                        renderRrRoleDisplay(display, originalData[userId][field]);
                                    } else if (field === 'training') {
                                        const tv = originalData[userId][field] || '';
                                        display.textContent = tv || '-';
                                        if (tv) {
                                            display.className = 'user-display small text-break d-block user-training-display';
                                            display.style.maxWidth = '280px';
                                        } else {
                                            display.className = 'user-display';
                                            display.style.maxWidth = '';
                                        }
                                    } else if (field === 'resources') {
                                        const rv = originalData[userId][field] || '';
                                        display.textContent = rv || '-';
                                        if (rv) {
                                            display.className = 'user-display small text-break d-block user-resources-display';
                                            display.style.maxWidth = '280px';
                                        } else {
                                            display.className = 'user-display';
                                            display.style.maxWidth = '';
                                        }
                                    } else if (field === 'phone') {
                                        renderPhoneDisplay(display, originalData[userId][field]);
                                    } else if (field === 'email') {
                                        renderEmailDisplay(display, originalData[userId][field]);
                                    } else {
                                        display.textContent = originalData[userId][field];
                                    }
                                }
                            }
                        });

                        // Show edit & delete, hide save/cancel
                        row.querySelector('.edit-btn').classList.remove('d-none');
                        const userIconBtn2 = row.querySelector('.user-icon-btn');
                        if (userIconBtn2) userIconBtn2.classList.remove('d-none');
                        const delBtn2 = row.querySelector('.delete-btn');
                        if (delBtn2) delBtn2.classList.remove('d-none');
                        row.querySelector('.save-btn').classList.add('d-none');
                        row.querySelector('.cancel-btn').classList.add('d-none');
                        row.classList.remove('editing-row');

                        updateChecklistCell(row);
                        
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
                            phone: row.querySelector('[data-field="phone"] .user-edit').value.trim(),
                            email: row.querySelector('[data-field="email"] .user-edit').value.trim(),
                            designation: row.querySelector('[data-field="designation"] .user-edit').value.trim(),
                            rr_role: row.querySelector('[data-field="rr_role"] .user-edit').value.trim(),
                            resources: row.querySelector('[data-field="resources"] textarea.user-edit').value.trim(),
                            training: row.querySelector('[data-field="training"] textarea.user-edit').value.trim(),
                            _token: '{{ csrf_token() }}',
                            _method: 'PUT'
                        };

                        // Send AJAX request
                        const formData = new FormData();
                        formData.append('name', data.name);
                        formData.append('phone', data.phone);
                        formData.append('email', data.email);
                        formData.append('designation', data.designation);
                        formData.append('rr_role', data.rr_role);
                        formData.append('resources', data.resources);
                        formData.append('training', data.training);
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
                                const phoneDisplay = row.querySelector('[data-field="phone"] .user-display');
                                const emailDisplay = row.querySelector('[data-field="email"] .user-display');
                                const designationDisplay = row.querySelector('[data-field="designation"] .user-display');
                                const rrRoleDisplay = row.querySelector('[data-field="rr_role"] .user-display');
                                const resourcesDisplay = row.querySelector('[data-field="resources"] .user-display');
                                const resourcesTextarea = row.querySelector('[data-field="resources"] textarea.user-edit');
                                const trainingDisplay = row.querySelector('[data-field="training"] .user-display');
                                const trainingTextarea = row.querySelector('[data-field="training"] textarea.user-edit');
                                
                                nameDisplay.textContent = result.user.name;
                                renderPhoneDisplay(phoneDisplay, result.user.phone);
                                renderEmailDisplay(emailDisplay, result.user.email);
                                
                                if (result.user.designation) {
                                    designationDisplay.textContent = result.user.designation;
                                    designationDisplay.className = 'designation-badge user-display';
                                } else {
                                    designationDisplay.textContent = '-';
                                    designationDisplay.className = 'user-display';
                                }

                                const rrCell = row.querySelector('.user-rr-cell');
                                if (rrCell && typeof result.user.has_rr_portfolio === 'boolean') {
                                    rrCell.dataset.hasPortfolio = result.user.has_rr_portfolio ? '1' : '0';
                                }
                                renderRrRoleDisplay(rrRoleDisplay, result.user.rr_role);

                                const res = result.user.resources || '';
                                if (resourcesDisplay) {
                                    resourcesDisplay.textContent = res || '-';
                                    if (res) {
                                        resourcesDisplay.className = 'user-display small text-break d-block user-resources-display';
                                        resourcesDisplay.style.maxWidth = '280px';
                                    } else {
                                        resourcesDisplay.className = 'user-display';
                                        resourcesDisplay.style.maxWidth = '';
                                    }
                                }
                                if (resourcesTextarea) {
                                    resourcesTextarea.value = res;
                                }

                                const trn = result.user.training || '';
                                if (trainingDisplay) {
                                    trainingDisplay.textContent = trn || '-';
                                    if (trn) {
                                        trainingDisplay.className = 'user-display small text-break d-block user-training-display';
                                        trainingDisplay.style.maxWidth = '280px';
                                    } else {
                                        trainingDisplay.className = 'user-display';
                                        trainingDisplay.style.maxWidth = '';
                                    }
                                }
                                if (trainingTextarea) {
                                    trainingTextarea.value = trn;
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
                                    if (field === 'training' || field === 'resources') {
                                        return;
                                    }
                                    cell.dataset.original = result.user[field] || '';
                                });

                                updateChecklistCell(row);

                                // Show edit & delete, hide save/cancel
                                row.querySelector('.edit-btn').classList.remove('d-none');
                                const userIconBtn3 = row.querySelector('.user-icon-btn');
                                if (userIconBtn3) userIconBtn3.classList.remove('d-none');
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
