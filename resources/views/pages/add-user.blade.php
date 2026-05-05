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

        /* Button group styling for import with templates */
        .btn-group {
            display: inline-flex;
            border-radius: 0.25rem;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .btn-group .btn {
            border-radius: 0;
            margin: 0;
        }

        .btn-group .btn:first-child {
            border-top-left-radius: 0.25rem;
            border-bottom-left-radius: 0.25rem;
        }

        .btn-group .btn:last-child {
            border-top-right-radius: 0.25rem;
            border-bottom-right-radius: 0.25rem;
            border-left: 1px solid rgba(255, 255, 255, 0.3);
            padding: 0.375rem 0.75rem;
        }

        .btn-group .btn-outline {
            background: rgba(255, 255, 255, 0.2);
        }

        .btn-group .btn-outline:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        /* Make input fields wider and more visible when editing */
        .users-table .user-edit {
            min-width: 120px;
            width: 100%;
        }

        .users-table input[type="text"].user-edit,
        .users-table input[type="email"].user-edit,
        .users-table input[type="number"].user-edit {
            min-width: 120px;
            padding: 0.375rem 0.75rem;
        }

        /* Specific width for bank and UPI fields */
        .users-table [data-field="bank_1"] .user-edit,
        .users-table [data-field="bank_2"] .user-edit,
        .users-table [data-field="upi_id"] .user-edit {
            min-width: 150px;
        }

        /* Make table cells accommodate larger inputs */
        .users-table td {
            min-width: 100px;
        }

        /* Blue button for salary hide */
        .btn-warning-soft {
            background-color: #084298!important;
            color: #fff;
        }

        .btn-warning-soft:hover {
            background-color: #0a58ca;
            color: #fff;
        }

        /* Compact Salary Table */
        #salaryTable {
            font-size: 18px;
        }

        #salaryTable th {
            padding: 10px 7px !important;
            font-size: 14px;
            white-space: nowrap;
            text-align: center;
        }

        #salaryTable td {
            padding: 10px 7px !important;
            font-size: 15px;
            text-align: center;
            vertical-align: middle;
        }

        #salaryTable .badge,
        #salaryTable .amount-lm-badge,
        #salaryTable .amount-p-badge,
        #salaryTable .adv-inc-other-badge,
        #salaryTable .salary-badge,
        #salaryTable .salary-lm-badge,
        #salaryTable .hours-lm-badge,
        #salaryTable .other-badge,
        #salaryTable .increment-badge {
            font-size: 14px;
            padding: 0;
            background: none !important;
            color: inherit !important;
            border: none !important;
            box-shadow: none !important;
        }

        #salaryTable .avatar-circle {
            width: 38px;
            height: 38px;
            font-size: 16px;
        }

        #salaryTable .btn-action {
            width: 28px;
            height: 28px;
            font-size: 11px;
        }

        #salaryTable .action-buttons {
            gap: 4px;
        }

        /* Make salary table fit in viewport */
        #salary-content .users-active-table-wrap {
            zoom: 0.90;
            -moz-transform: scale(0.90);
            -moz-transform-origin: 0 0;
        }

        /* Adjust form controls in salary table */
        #salaryTable .form-control-sm {
            font-size: 11px;
            padding: 4px 6px;
        }

        /* Hide serial number column in salary table */
        #salaryTable th:first-child,
        #salaryTable td:first-child {
            display: none;
        }

        /* Reduce width of B1, B2, and UPI columns */
        #salaryTable th:nth-last-child(4),
        #salaryTable td:nth-last-child(4),
        #salaryTable th:nth-last-child(3),
        #salaryTable td:nth-last-child(3),
        #salaryTable th:nth-last-child(2),
        #salaryTable td:nth-last-child(2) {
            width: 40px;
            max-width: 40px;
            padding: 6px 2px !important;
            text-align: center;
        }

        /* Data indicator dots */
        .data-indicator {
            position: relative;
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .data-indicator:hover {
            transform: scale(1.3);
        }

        /* Green dot for filled data */
        .indicator-filled {
            background-color: #28a745;
            box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.2);
        }

        /* Red dot for empty data */
        .indicator-empty {
            background-color: #dc3545;
            box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.2);
        }

        /* Tooltip styling */
        .data-indicator .tooltip-text {
            visibility: hidden;
            position: absolute;
            z-index: 1000;
            background-color: #333;
            color: #fff;
            text-align: center;
            padding: 8px 12px;
            border-radius: 6px;
            white-space: nowrap;
            font-size: 12px;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        /* Tooltip arrow */
        .data-indicator .tooltip-text::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #333 transparent transparent transparent;
        }

        /* Show tooltip on hover */
        .data-indicator:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }
    </style>
@endsection

@section('content')
    @php
    // Helper function to get the correct email for TeamLogger lookup
    function getTeamLoggerEmail($userEmail, $mapping) {
        $normalizedEmail = strtolower(trim($userEmail));
        return $mapping[$normalizedEmail] ?? $normalizedEmail;
    }
    @endphp
    <div class="container-fluid team-management-page px-3 px-lg-4 px-xxl-5 mt-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="text-primary fw-bold mb-1">Team Management</h2>
                <p class="text-muted">View and manage users & performance</p>
            </div>
            @if($canEdit)
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-primary" id="copySalaryBtn">
                    <i class="ri-file-copy-line me-2"></i>Copy Salary LM → PP
                </button>
                <div class="btn-group">
                    <button type="button" class="btn btn-info" id="importBtn">
                        <i class="ri-upload-2-line me-2"></i>Import Salary Data
                    </button>
                    <button type="button" class="btn btn-info btn-outline" id="downloadSalaryTemplateBtn" title="Download Salary Data Template">
                        <i class="ri-file-download-line"></i>
                    </button>
                </div>
                <input type="file" id="importFile" accept=".csv" style="display: none;">
                <button type="button" class="btn btn-success" id="exportBtn">
                    <i class="ri-download-2-line me-2"></i>Salary Sheet
                </button>
            </div>
            @endif
        </div>
        
        @if($canEdit)
        <div class="mb-4">
            @if($canEdit)
            <div class="d-flex gap-2 flex-nowrap w-100 salary-badges-container">
                <span class="badge bg-primary fs-6 px-2 py-2 flex-fill text-center">
                    <i class="ri-team-line me-1"></i>
                    Team: {{ $salaryUsers->count() }}
                </span>
                <span class="badge bg-success fs-6 px-2 py-2 flex-fill text-center">
                    <i class="ri-money-dollar-circle-line me-1"></i>
                    Salary PP: ₹{{ number_format($totalSalaryPP, 0) }}
                </span>
                <span class="badge bg-info fs-6 px-2 py-2 flex-fill text-center">
                    <i class="ri-funds-line me-1"></i>
                    Increment: ₹{{ number_format($totalIncrement, 0) }}
                </span>
                <span class="badge bg-warning fs-6 px-2 py-2 flex-fill text-center">
                    <i class="ri-wallet-3-line me-1"></i>
                    Salary LM: ₹{{ number_format($totalSalaryPP + $totalIncrement, 0) }}
                </span>
                @php
                    $totalAmountP = $users->sum(function($user) use ($teamLoggerData, $emailMapping) {
                        $userEmail = strtolower(trim($user->email));
                        $teamLoggerEmail = getTeamLoggerEmail($userEmail, $emailMapping);
                        $hoursLM = $teamLoggerData[$teamLoggerEmail]['hours'] ?? 0;
                        $salaryPP = $user->userSalary?->salary_pp ?? 0;
                        $increment = $user->userSalary?->increment ?? 0;
                        $salaryLM = $salaryPP + $increment;
                        $other = $user->userSalary?->other ?? 0;
                        $advIncOther = $user->userSalary?->adv_inc_other ?? 0;
                        return (($hoursLM * $salaryLM) / 200) + $other - $advIncOther;
                    });
                    $totalAdvIncOther = $users->sum(function($user) {
                        return $user->userSalary?->adv_inc_other ?? 0;
                    });
                @endphp
                <span class="badge bg-secondary fs-6 px-2 py-2 flex-fill text-center">
                    <i class="ri-calculator-line me-1"></i>
                    Amount P: ₹{{ number_format(round($totalAmountP / 100) * 100, 0) }}
                </span>
                <span class="badge bg-dark fs-6 px-2 py-2 flex-fill text-center">
                    <i class="ri-file-list-3-line me-1"></i>
                    Advance: ₹{{ number_format($totalAdvIncOther, 0) }}
                </span>
            </div>
            @endif
        </div>
        @endif

        <!-- Tabs Navigation -->
        <ul class="nav nav-tabs mb-4" id="userManagementTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="users-tab" data-bs-toggle="tab" data-bs-target="#users-content" type="button" role="tab">
                    <i class="ri-user-line me-2"></i>Users
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="salary-tab" data-bs-toggle="tab" data-bs-target="#salary-content" type="button" role="tab">
                    <i class="ri-money-dollar-circle-line me-2"></i>Salary
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
                <div class="mb-2">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                                <input type="text" id="searchInput" class="form-control border-0 bg-light" 
                                    placeholder="Search by name, phone, email, designation, R&amp;R, resources, training, or checklist" onkeyup="filterTable()">
                    </div>
                </div>

                <!-- Users Table (scrolls inside viewport so edit mode stays on one screen) -->
                <div class="users-active-table-wrap">
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
                                <th>Action</th>
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
                                                <span class="user-display small text-break d-block user-resources-display">{{ $resourcesVal }}</span>
                                            @else
                                                <span class="user-display">-</span>
                                            @endif
                                            <textarea class="form-control form-control-sm user-edit user-edit-textarea d-none" rows="2" data-field="resources" placeholder="Links, docs, or notes">{{ $resourcesVal }}</textarea>
                                        </div>
                                    </td>
                                    <td>
                                        @php $trainingVal = $user->userRR?->training ?? ''; @endphp
                                        <div class="user-training-cell" data-field="training">
                                            @if($trainingVal !== '')
                                                <span class="user-display small text-break d-block user-training-display">{{ $trainingVal }}</span>
                                            @else
                                                <span class="user-display">-</span>
                                            @endif
                                            <textarea class="form-control form-control-sm user-edit user-edit-textarea d-none" rows="2" data-field="training" placeholder="Training notes or link">{{ $trainingVal }}</textarea>
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
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center py-4 text-muted">No users found</td>
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
                                        <th>Salary PP</th>
                                        <th>Incr</th>
                                        <th>Salary LM</th>
                                        <th>Hours LM</th>
                                        <th>Other</th>
                                        <th>Amt LM</th>
                                        <th>Amt P</th>
                                        <th>Adv</th>
                                        <th>B1</th>
                                        <th>B2</th>
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
                                                    <span class="small text-break d-block inactive-long-text">{{ $iuResources }}</span>
                                                @else
                                                    <span>-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @php $iuTraining = $iu->userRR?->training ?? ''; @endphp
                                                @if($iuTraining !== '')
                                                    <span class="small text-break d-block inactive-long-text">{{ $iuTraining }}</span>
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
                                            <td>
                                                @php $iuSalaryPP = $iu->userSalary?->salary_pp ?? ''; @endphp
                                                @if($iuSalaryPP !== '')
                                                    <span class="salary-badge">₹{{ number_format($iuSalaryPP, 0) }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @php $iuIncrement = $iu->userSalary?->increment ?? ''; @endphp
                                                @if($iuIncrement !== '')
                                                    <span class="increment-badge">₹{{ number_format($iuIncrement, 0) }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @php 
                                                    $iuSalaryPPVal = $iu->userSalary?->salary_pp ?? 0;
                                                    $iuIncrementVal = $iu->userSalary?->increment ?? 0;
                                                    $iuSalaryLM = $iuSalaryPPVal + $iuIncrementVal;
                                                @endphp
                                                @if($iuSalaryLM > 0)
                                                    <span class="salary-lm-badge">₹{{ number_format($iuSalaryLM, 0) }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @php 
                                                    $iuEmail = strtolower(trim($iu->email));
                                                    $iuTeamLoggerEmail = getTeamLoggerEmail($iuEmail, $emailMapping);
                                                    $iuHoursLM = $teamLoggerData[$iuTeamLoggerEmail]['hours'] ?? 0;
                                                @endphp
                                                @if($iuHoursLM > 0)
                                                    <span class="hours-lm-badge" title="{{ $previousMonth }}: {{ $iuHoursLM }} hours">{{ $iuHoursLM }}h</span>
                                                @else
                                                    <span class="text-muted" title="No hours logged in {{ $previousMonth }}">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @php $iuOther = $iu->userSalary?->other ?? 0; @endphp
                                                @if($iuOther > 0)
                                                    <span class="other-badge">₹{{ number_format($iuOther, 0) }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @php 
                                                    $iuEmail = strtolower(trim($iu->email));
                                                    $iuTeamLoggerEmail = getTeamLoggerEmail($iuEmail, $emailMapping);
                                                    $iuHoursLM = $teamLoggerData[$iuTeamLoggerEmail]['hours'] ?? 0;
                                                    $iuSalaryPPVal = $iu->userSalary?->salary_pp ?? 0;
                                                    $iuIncrementVal = $iu->userSalary?->increment ?? 0;
                                                    $iuSalaryLM = $iuSalaryPPVal + $iuIncrementVal;
                                                    $iuOther = $iu->userSalary?->other ?? 0;
                                                    $iuAdvIncOther = $iu->userSalary?->adv_inc_other ?? 0;
                                                    $iuAmountLM = (($iuHoursLM * $iuSalaryPPVal) / 200) - $iuAdvIncOther;
                                                @endphp
                                                @if($iuAmountLM != 0)
                                                    <span class="amount-lm-badge">₹{{ number_format($iuAmountLM, 0) }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @php 
                                                    $iuEmail = strtolower(trim($iu->email));
                                                    $iuTeamLoggerEmail = getTeamLoggerEmail($iuEmail, $emailMapping);
                                                    $iuHoursLM = $teamLoggerData[$iuTeamLoggerEmail]['hours'] ?? 0;
                                                    $iuSalaryPPVal = $iu->userSalary?->salary_pp ?? 0;
                                                    $iuOther = $iu->userSalary?->other ?? 0;
                                                    $iuAmountP = (($iuHoursLM * $iuSalaryPPVal) / 200) + $iuOther;
                                                @endphp
                                                @if($iuAmountP != 0)
                                                    <span class="amount-p-badge">₹{{ number_format(round($iuAmountP / 100) * 100, 0) }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @php $iuAdvIncOther = $iu->userSalary?->adv_inc_other ?? 0; @endphp
                                                @if($iuAdvIncOther > 0)
                                                    <span class="adv-inc-other-badge">₹{{ number_format($iuAdvIncOther, 0) }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @php $iuBank1 = $iu->userSalary?->bank_1 ?? ''; @endphp
                                                <span>{{ $iuBank1 ?: '—' }}</span>
                                            </td>
                                            <td>
                                                @php $iuBank2 = $iu->userSalary?->bank_2 ?? ''; @endphp
                                                <span>{{ $iuBank2 ?: '—' }}</span>
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
        .team-management-page .table-responsive {
            overflow-x: auto;
            overflow-y: visible;
        }

        /* Active users: keep table + edit row within the viewport */
        .team-management-page .user-resources-display,
        .team-management-page .user-training-display,
        .team-management-page .inactive-long-text {
            max-width: 100%;
        }

        #users-content .users-active-table-wrap {
            overflow: auto;
            max-height: calc(100dvh - 260px);
            -webkit-overflow-scrolling: touch;
            border-radius: 6px;
            border: 1px solid #e9ecef;
        }

        #users-content .users-active-table-wrap .users-table thead th {
            position: sticky;
            top: 0;
            z-index: 4;
            background-color: #f8f9fa;
            box-shadow: 0 1px 0 #dee2e6;
        }

        #salary-content .users-active-table-wrap {
            overflow: auto;
            max-height: calc(100dvh - 260px);
            -webkit-overflow-scrolling: touch;
            border-radius: 6px;
            border: 1px solid #e9ecef;
        }

        #salary-content .users-active-table-wrap .users-table thead th {
            position: sticky;
            top: 0;
            z-index: 4;
            background-color: #f8f9fa;
            box-shadow: 0 1px 0 #dee2e6;
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

        /* Salary Badge */
        .salary-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            background-color: #d1f4e0;
            color: #0d8a4d;
            font-size: 13px;
            font-weight: 600;
        }

        /* Increment Badge */
        .increment-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            background-color: #cfe2ff;
            color: #084298;
            font-size: 13px;
            font-weight: 600;
        }

        /* Salary LM Badge */
        .salary-lm-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            background-color: #fff3cd;
            color: #997404;
            font-size: 13px;
            font-weight: 600;
        }

        /* Hours LM Badge */
        .hours-lm-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            background-color: #d1ecf1;
            color: #0c5460;
            font-size: 13px;
            font-weight: 600;
            cursor: help;
        }

        /* Other Badge */
        .other-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            background-color: #f8d7da;
            color: #721c24;
            font-size: 13px;
            font-weight: 600;
        }

        /* Import Buttons - Black text and icons */
        #importBtn,
        #importBtn i {
            color: #000 !important;
        }

        /* Salary Badges Container - Increased Height */
        .salary-badges-container .badge {
            line-height: 1.5;
            padding-top: 0.5rem !important;
            padding-bottom: 0.5rem !important;
        }

        /* Amount LM Badge */
        .amount-lm-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            background-color: #d4edda;
            color: #155724;
            font-size: 13px;
            font-weight: 600;
        }

        /* Amount P Badge */
        .amount-p-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            background-color: #e7e8ea;
            color: #383d41;
            font-size: 13px;
            font-weight: 600;
        }

        /* Adv/Inc/Other Badge */
        .adv-inc-other-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            background-color: #d1d3e2;
            color: #2e2f45;
            font-size: 13px;
            font-weight: 600;
        }

        /* Action Button */
        .btn-action {
            width: 32px;
            height: 32px;
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
            font-size: 14px;
            flex-shrink: 0;
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
            flex-direction: row;
            gap: 8px;
            align-items: center;
            justify-content: center;
            flex-wrap: nowrap;
        }

        /* Edit Mode */
        .editing-row {
            background-color: #fff3cd !important;
        }

        .users-table tbody tr.editing-row td {
            padding: 8px 10px;
            vertical-align: top;
        }

        .users-table tbody tr.editing-row .avatar-circle {
            width: 32px;
            height: 32px;
            font-size: 14px;
        }

        .user-edit {
            min-width: 150px;
            font-size: 14px;
        }

        .users-table tbody tr.editing-row .user-edit {
            min-width: 0;
            width: 100%;
            max-width: 100%;
        }

        .users-table tbody tr.editing-row textarea.user-edit-textarea {
            min-height: 2.25rem;
            max-height: 4.25rem;
            resize: none;
            overflow-y: auto;
            line-height: 1.35;
            word-break: break-word;
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

        function filterSalaryTable() {
            const input = document.getElementById('searchSalaryInput');
            if (!input) return;
            const filter = input.value.toLowerCase();
            const table = document.getElementById('salaryTable');
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
                            if (!cell) return; // Skip if no data-field parent (e.g., calculated fields)
                            
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

                        const salaryCellEdit = row.querySelector('[data-field="salary_pp"]');
                        if (salaryCellEdit) {
                            const inp = salaryCellEdit.querySelector('input.user-edit');
                            if (inp) {
                                originalData[userId]['salary_pp'] = inp.value;
                            }
                        }

                        const incrementCellEdit = row.querySelector('[data-field="increment"]');
                        if (incrementCellEdit) {
                            const inp = incrementCellEdit.querySelector('input.user-edit');
                            if (inp) {
                                originalData[userId]['increment'] = inp.value;
                            }
                        }

                        const otherCellEdit = row.querySelector('[data-field="other"]');
                        if (otherCellEdit) {
                            const inp = otherCellEdit.querySelector('input.user-edit');
                            if (inp) {
                                originalData[userId]['other'] = inp.value;
                            }
                        }

                        const advIncOtherCellEdit = row.querySelector('[data-field="adv_inc_other"]');
                        if (advIncOtherCellEdit) {
                            const inp = advIncOtherCellEdit.querySelector('input.user-edit');
                            if (inp) {
                                originalData[userId]['adv_inc_other'] = inp.value;
                            }
                        }

                        // Show save/cancel, hide edit & delete
                        const editBtn = row.querySelector('.edit-btn');
                        if (editBtn) editBtn.classList.add('d-none');
                        const userIconBtn = row.querySelector('.user-icon-btn');
                        if (userIconBtn) userIconBtn.classList.add('d-none');
                        const delBtn = row.querySelector('.delete-btn');
                        if (delBtn) delBtn.classList.add('d-none');
                        const saveBtn = row.querySelector('.save-btn');
                        if (saveBtn) saveBtn.classList.remove('d-none');
                        const cancelBtn = row.querySelector('.cancel-btn');
                        if (cancelBtn) cancelBtn.classList.remove('d-none');
                        row.classList.add('editing-row');
                        row.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
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
                                        } else {
                                            display.className = 'user-display';
                                        }
                                    } else if (field === 'resources') {
                                        const rv = originalData[userId][field] || '';
                                        display.textContent = rv || '-';
                                        if (rv) {
                                            display.className = 'user-display small text-break d-block user-resources-display';
                                        } else {
                                            display.className = 'user-display';
                                        }
                                    } else if (field === 'phone') {
                                        renderPhoneDisplay(display, originalData[userId][field]);
                                    } else if (field === 'email') {
                                        renderEmailDisplay(display, originalData[userId][field]);
                                    } else if (field === 'salary_pp') {
                                        const sv = originalData[userId][field] || '';
                                        if (sv !== '') {
                                            display.textContent = '₹' + Math.round(parseFloat(sv)).toLocaleString('en-IN');
                                            display.className = 'user-display salary-badge';
                                        } else {
                                            display.textContent = '—';
                                            display.className = 'user-display text-muted';
                                        }
                                    } else if (field === 'increment') {
                                        const iv = originalData[userId][field] || '';
                                        if (iv !== '') {
                                            display.textContent = '₹' + Math.round(parseFloat(iv)).toLocaleString('en-IN');
                                            display.className = 'user-display increment-badge';
                                        } else {
                                            display.textContent = '—';
                                            display.className = 'user-display text-muted';
                                        }
                                    } else if (field === 'other') {
                                        const ov = originalData[userId][field] || '';
                                        if (ov !== '' && parseFloat(ov) > 0) {
                                            display.textContent = '₹' + Math.round(parseFloat(ov)).toLocaleString('en-IN');
                                            display.className = 'user-display other-badge';
                                        } else {
                                            display.textContent = '—';
                                            display.className = 'user-display text-muted';
                                        }
                                    } else if (field === 'adv_inc_other') {
                                        const aio = originalData[userId][field] || '';
                                        if (aio !== '' && parseFloat(aio) > 0) {
                                            display.textContent = '₹' + Math.round(parseFloat(aio)).toLocaleString('en-IN');
                                            display.className = 'user-display adv-inc-other-badge';
                                        } else {
                                            display.textContent = '—';
                                            display.className = 'user-display text-muted';
                                        }
                                    } else {
                                        display.textContent = originalData[userId][field];
                                    }
                                }
                            }
                        });

                        // Show edit & delete, hide save/cancel
                        const editBtn2 = row.querySelector('.edit-btn');
                        if (editBtn2) editBtn2.classList.remove('d-none');
                        const userIconBtn2 = row.querySelector('.user-icon-btn');
                        if (userIconBtn2) userIconBtn2.classList.remove('d-none');
                        const delBtn2 = row.querySelector('.delete-btn');
                        if (delBtn2) delBtn2.classList.remove('d-none');
                        const saveBtn2 = row.querySelector('.save-btn');
                        if (saveBtn2) saveBtn2.classList.add('d-none');
                        const cancelBtn2 = row.querySelector('.cancel-btn');
                        if (cancelBtn2) cancelBtn2.classList.add('d-none');
                        row.classList.remove('editing-row');

                        // Recalculate Salary LM and Amount LM after cancel
                        const salaryLMDisplay = row.querySelector('.user-salary-lm-cell .user-display');
                        const salaryVal = parseFloat(originalData[userId]['salary_pp']) || 0;
                        const incrementVal = parseFloat(originalData[userId]['increment']) || 0;
                        const salaryLMValue = salaryVal + incrementVal;
                        if (salaryLMDisplay) {
                            if (salaryLMValue > 0) {
                                salaryLMDisplay.textContent = '₹' + Math.round(salaryLMValue).toLocaleString('en-IN');
                                salaryLMDisplay.className = 'user-display salary-lm-badge';
                            } else {
                                salaryLMDisplay.textContent = '—';
                                salaryLMDisplay.className = 'user-display text-muted';
                            }
                        }

                        // Recalculate Amount LM
                        const amountLMDisplay = row.querySelector('.user-amount-lm-cell span');
                        const hoursLMCell = row.querySelector('.user-hours-lm-cell .hours-lm-badge');
                        const hoursLM = hoursLMCell ? parseFloat(hoursLMCell.textContent) || 0 : 0;
                        const amountLM = ((hoursLM * salaryLMValue) / 200);
                        if (amountLMDisplay) {
                            if (amountLM != 0) {
                                amountLMDisplay.textContent = '₹' + Math.round(amountLM).toLocaleString('en-IN');
                                amountLMDisplay.className = 'amount-lm-badge';
                            } else {
                                amountLMDisplay.textContent = '—';
                                amountLMDisplay.className = 'text-muted';
                            }
                        }

                        // Recalculate Amount P
                        const amountPDisplay = row.querySelector('.user-amount-p-cell span');
                        const otherVal = parseFloat(originalData[userId]['other']) || 0;
                        const advIncOtherVal = parseFloat(originalData[userId]['adv_inc_other']) || 0;
                        const amountP = ((hoursLM * salaryLMValue) / 200) + otherVal - advIncOtherVal;
                        if (amountPDisplay) {
                            if (amountP != 0) {
                                amountPDisplay.textContent = '₹' + (Math.round(amountP / 100) * 100).toLocaleString('en-IN');
                                amountPDisplay.className = 'amount-p-badge';
                            } else {
                                amountPDisplay.textContent = '—';
                                amountPDisplay.className = 'text-muted';
                            }
                        }

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
                        
                        // Collect updated data (with null checks for optional fields)
                        const nameInput = row.querySelector('[data-field="name"] .user-edit');
                        const phoneInput = row.querySelector('[data-field="phone"] .user-edit') || row.querySelector('.salary-tab-phone');
                        const emailInput = row.querySelector('[data-field="email"] .user-edit') || row.querySelector('.salary-tab-email');
                        const designationInput = row.querySelector('[data-field="designation"] .user-edit') || row.querySelector('.salary-tab-designation');
                        const rrRoleInput = row.querySelector('[data-field="rr_role"] .user-edit') || row.querySelector('.salary-tab-rr');
                        const resourcesInput = row.querySelector('[data-field="resources"] textarea.user-edit') || row.querySelector('.salary-tab-resources');
                        const trainingInput = row.querySelector('[data-field="training"] textarea.user-edit') || row.querySelector('.salary-tab-training');
                        const salaryPpInput = row.querySelector('[data-field="salary_pp"] .user-edit');
                        const incrementInput = row.querySelector('[data-field="increment"] .user-edit');
                        const otherInput = row.querySelector('[data-field="other"] .user-edit');
                        const advIncOtherInput = row.querySelector('[data-field="adv_inc_other"] .user-edit');
                        const bank1Input = row.querySelector('[data-field="bank_1"] .user-edit');
                        const bank2Input = row.querySelector('[data-field="bank_2"] .user-edit');
                        const upiIdInput = row.querySelector('[data-field="upi_id"] .user-edit');

                        const data = {
                            name: nameInput ? nameInput.value.trim() : '',
                            phone: phoneInput ? phoneInput.value.trim() : '',
                            email: emailInput ? emailInput.value.trim() : '',
                            designation: designationInput ? designationInput.value.trim() : '',
                            rr_role: rrRoleInput ? rrRoleInput.value.trim() : '',
                            resources: resourcesInput ? resourcesInput.value.trim() : '',
                            training: trainingInput ? trainingInput.value.trim() : '',
                            salary_pp: salaryPpInput ? salaryPpInput.value.trim() : '',
                            increment: incrementInput ? incrementInput.value.trim() : '',
                            other: otherInput ? otherInput.value.trim() : '',
                            adv_inc_other: advIncOtherInput ? advIncOtherInput.value.trim() : '',
                            bank_1: bank1Input ? bank1Input.value.trim() : '',
                            bank_2: bank2Input ? bank2Input.value.trim() : '',
                            upi_id: upiIdInput ? upiIdInput.value.trim() : '',
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
                        
                        // Only append numeric fields if they exist in the current tab
                        if (salaryPpInput) formData.append('salary_pp', data.salary_pp);
                        if (incrementInput) formData.append('increment', data.increment);
                        if (otherInput) formData.append('other', data.other);
                        if (advIncOtherInput) formData.append('adv_inc_other', data.adv_inc_other);
                        if (bank1Input) formData.append('bank_1', data.bank_1);
                        if (bank2Input) formData.append('bank_2', data.bank_2);
                        if (upiIdInput) formData.append('upi_id', data.upi_id);
                        
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
                                const salaryDisplay = row.querySelector('[data-field="salary_pp"] .user-display');
                                const salaryInput = row.querySelector('[data-field="salary_pp"] .user-edit');
                                const incrementDisplay = row.querySelector('[data-field="increment"] .user-display');
                                const incrementInput = row.querySelector('[data-field="increment"] .user-edit');
                                const otherDisplay = row.querySelector('[data-field="other"] .user-display');
                                const otherInput = row.querySelector('[data-field="other"] .user-edit');
                                const advIncOtherDisplay = row.querySelector('[data-field="adv_inc_other"] .user-display');
                                const advIncOtherInput = row.querySelector('[data-field="adv_inc_other"] .user-edit');
                                const bank1Display = row.querySelector('[data-field="bank_1"] .user-display');
                                const bank1Input = row.querySelector('[data-field="bank_1"] .user-edit');
                                const bank2Display = row.querySelector('[data-field="bank_2"] .user-display');
                                const bank2Input = row.querySelector('[data-field="bank_2"] .user-edit');
                                const upiIdDisplay = row.querySelector('[data-field="upi_id"] .user-display');
                                const upiIdInput = row.querySelector('[data-field="upi_id"] .user-edit');
                                
                                if (nameDisplay) {
                                    nameDisplay.textContent = result.user.name;
                                }
                                if (phoneDisplay) {
                                    renderPhoneDisplay(phoneDisplay, result.user.phone);
                                }
                                if (emailDisplay) {
                                    renderEmailDisplay(emailDisplay, result.user.email);
                                }
                                
                                if (designationDisplay) {
                                    if (result.user.designation) {
                                        designationDisplay.textContent = result.user.designation;
                                        designationDisplay.className = 'designation-badge user-display';
                                    } else {
                                        designationDisplay.textContent = '-';
                                        designationDisplay.className = 'user-display';
                                    }
                                }

                                const rrCell = row.querySelector('.user-rr-cell');
                                if (rrCell && typeof result.user.has_rr_portfolio === 'boolean') {
                                    rrCell.dataset.hasPortfolio = result.user.has_rr_portfolio ? '1' : '0';
                                }
                                if (rrRoleDisplay) {
                                    renderRrRoleDisplay(rrRoleDisplay, result.user.rr_role);
                                }

                                const res = result.user.resources || '';
                                if (resourcesDisplay) {
                                    resourcesDisplay.textContent = res || '-';
                                    if (res) {
                                        resourcesDisplay.className = 'user-display small text-break d-block user-resources-display';
                                    } else {
                                        resourcesDisplay.className = 'user-display';
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
                                    } else {
                                        trainingDisplay.className = 'user-display';
                                    }
                                }
                                if (trainingTextarea) {
                                    trainingTextarea.value = trn;
                                }

                                const sal = result.user.salary_pp || '';
                                if (salaryDisplay) {
                                    if (sal !== '') {
                                        salaryDisplay.textContent = '₹' + Math.round(parseFloat(sal)).toLocaleString('en-IN');
                                        salaryDisplay.className = 'user-display salary-badge';
                                    } else {
                                        salaryDisplay.textContent = '—';
                                        salaryDisplay.className = 'user-display text-muted';
                                    }
                                }
                                if (salaryInput) {
                                    salaryInput.value = sal !== '' ? Math.round(parseFloat(sal)) : '';
                                }

                                const inc = result.user.increment || '';
                                if (incrementDisplay) {
                                    if (inc !== '') {
                                        incrementDisplay.textContent = '₹' + Math.round(parseFloat(inc)).toLocaleString('en-IN');
                                        incrementDisplay.className = 'user-display increment-badge';
                                    } else {
                                        incrementDisplay.textContent = '—';
                                        incrementDisplay.className = 'user-display text-muted';
                                    }
                                }
                                if (incrementInput) {
                                    incrementInput.value = inc !== '' ? Math.round(parseFloat(inc)) : '';
                                }

                                const oth = result.user.other || '';
                                if (otherDisplay) {
                                    if (oth !== '' && parseFloat(oth) > 0) {
                                        otherDisplay.textContent = '₹' + Math.round(parseFloat(oth)).toLocaleString('en-IN');
                                        otherDisplay.className = 'user-display other-badge';
                                    } else {
                                        otherDisplay.textContent = '—';
                                        otherDisplay.className = 'user-display text-muted';
                                    }
                                }
                                if (otherInput) {
                                    otherInput.value = oth !== '' ? Math.round(parseFloat(oth)) : '';
                                }

                                const aio = result.user.adv_inc_other || '';
                                if (advIncOtherDisplay) {
                                    if (aio !== '' && parseFloat(aio) > 0) {
                                        advIncOtherDisplay.textContent = '₹' + Math.round(parseFloat(aio)).toLocaleString('en-IN');
                                        advIncOtherDisplay.className = 'user-display adv-inc-other-badge';
                                    } else {
                                        advIncOtherDisplay.textContent = '—';
                                        advIncOtherDisplay.className = 'user-display text-muted';
                                    }
                                }
                                if (advIncOtherInput) {
                                    advIncOtherInput.value = aio !== '' ? Math.round(parseFloat(aio)) : '';
                                }

                                const b1 = result.user.bank_1 || '';
                                if (bank1Display) {
                                    const indicator = bank1Display.querySelector('.data-indicator');
                                    const tooltipText = bank1Display.querySelector('.tooltip-text');
                                    if (indicator && tooltipText) {
                                        indicator.className = `data-indicator ${b1 ? 'indicator-filled' : 'indicator-empty'}`;
                                        tooltipText.textContent = b1 || 'No Bank 1 data';
                                    }
                                }
                                if (bank1Input) {
                                    bank1Input.value = b1;
                                }

                                const b2 = result.user.bank_2 || '';
                                if (bank2Display) {
                                    const indicator = bank2Display.querySelector('.data-indicator');
                                    const tooltipText = bank2Display.querySelector('.tooltip-text');
                                    if (indicator && tooltipText) {
                                        indicator.className = `data-indicator ${b2 ? 'indicator-filled' : 'indicator-empty'}`;
                                        tooltipText.textContent = b2 || 'No Bank 2 data';
                                    }
                                }
                                if (bank2Input) {
                                    bank2Input.value = b2;
                                }

                                const upi = result.user.upi_id || '';
                                if (upiIdDisplay) {
                                    const indicator = upiIdDisplay.querySelector('.data-indicator');
                                    const tooltipText = upiIdDisplay.querySelector('.tooltip-text');
                                    if (indicator && tooltipText) {
                                        indicator.className = `data-indicator ${upi ? 'indicator-filled' : 'indicator-empty'}`;
                                        tooltipText.textContent = upi || 'No UPI ID data';
                                    }
                                }
                                if (upiIdInput) {
                                    upiIdInput.value = upi;
                                }

                                // Update avatar initial
                                const avatar = row.querySelector('.avatar-circle');
                                if (avatar) {
                                    avatar.textContent = result.user.name.charAt(0).toUpperCase();
                                }

                                // Hide inputs, show displays FIRST
                                row.querySelectorAll('.user-edit').forEach(input => {
                                    input.classList.add('d-none');
                                });
                                row.querySelectorAll('.user-display').forEach(display => {
                                    display.classList.remove('d-none');
                                });

                                // NOW update calculated fields AFTER display is visible
                                // Update Salary LM (calculated field)
                                const salaryLMDisplay = row.querySelector('.user-salary-lm-cell .user-display');
                                const salaryLMValue = (parseFloat(sal) || 0) + (parseFloat(inc) || 0);
                                if (salaryLMDisplay) {
                                    if (salaryLMValue > 0) {
                                        salaryLMDisplay.textContent = '₹' + Math.round(salaryLMValue).toLocaleString('en-IN');
                                        salaryLMDisplay.className = 'user-display salary-lm-badge';
                                    } else {
                                        salaryLMDisplay.textContent = '—';
                                        salaryLMDisplay.className = 'user-display text-muted';
                                    }
                                }

                                // Update Amount LM (calculated field)
                                const amountLMDisplay = row.querySelector('.user-amount-lm-cell span');
                                const hoursLMCell = row.querySelector('.user-hours-lm-cell .hours-lm-badge');
                                const hoursLM = hoursLMCell ? parseFloat(hoursLMCell.textContent) || 0 : 0;
                                const amountLM = ((hoursLM * salaryLMValue) / 200);
                                if (amountLMDisplay) {
                                    if (amountLM != 0) {
                                        amountLMDisplay.textContent = '₹' + Math.round(amountLM).toLocaleString('en-IN');
                                        amountLMDisplay.className = 'amount-lm-badge';
                                    } else {
                                        amountLMDisplay.textContent = '—';
                                        amountLMDisplay.className = 'text-muted';
                                    }
                                }

                                // Update Amount P (calculated field)
                                const amountPDisplay = row.querySelector('.user-amount-p-cell span');
                                const amountP = ((hoursLM * salaryLMValue) / 200) + (parseFloat(oth) || 0) - (parseFloat(aio) || 0);
                                if (amountPDisplay) {
                                    if (amountP != 0) {
                                        amountPDisplay.textContent = '₹' + (Math.round(amountP / 100) * 100).toLocaleString('en-IN');
                                        amountPDisplay.className = 'amount-p-badge';
                                    } else {
                                        amountPDisplay.textContent = '—';
                                        amountPDisplay.className = 'text-muted';
                                    }
                                }

                                // Update original data
                                row.querySelectorAll('[data-field]').forEach(cell => {
                                    const field = cell.dataset.field;
                                    if (field === 'training' || field === 'resources' || field === 'salary_pp' || field === 'increment' || field === 'other' || field === 'adv_inc_other') {
                                        return;
                                    }
                                    cell.dataset.original = result.user[field] || '';
                                });

                                // Update data-original for salary fields
                                const salaryCell = row.querySelector('[data-field="salary_pp"]');
                                if (salaryCell) salaryCell.dataset.original = sal;
                                
                                const incrementCell = row.querySelector('[data-field="increment"]');
                                if (incrementCell) incrementCell.dataset.original = inc;
                                
                                const otherCell = row.querySelector('[data-field="other"]');
                                if (otherCell) otherCell.dataset.original = oth;
                                
                                const advIncOtherCell = row.querySelector('[data-field="adv_inc_other"]');
                                if (advIncOtherCell) advIncOtherCell.dataset.original = aio;
                                
                                const bank1Cell = row.querySelector('[data-field="bank_1"]');
                                if (bank1Cell) bank1Cell.dataset.original = b1;
                                
                                const bank2Cell = row.querySelector('[data-field="bank_2"]');
                                if (bank2Cell) bank2Cell.dataset.original = b2;

                                const upiIdCell = row.querySelector('[data-field="upi_id"]');
                                if (upiIdCell) upiIdCell.dataset.original = upi;

                                updateChecklistCell(row);

                                // Show edit & delete, hide save/cancel
                                const editBtn3 = row.querySelector('.edit-btn');
                                if (editBtn3) editBtn3.classList.remove('d-none');
                                const userIconBtn3 = row.querySelector('.user-icon-btn');
                                if (userIconBtn3) userIconBtn3.classList.remove('d-none');
                                const delBtn3 = row.querySelector('.delete-btn');
                                if (delBtn3) delBtn3.classList.remove('d-none');
                                const saveBtn3 = row.querySelector('.save-btn');
                                if (saveBtn3) saveBtn3.classList.add('d-none');
                                const cancelBtn3 = row.querySelector('.cancel-btn');
                                if (cancelBtn3) cancelBtn3.classList.add('d-none');
                                row.classList.remove('editing-row');
                                
                                delete originalData[userId];

                                // Update total salary PP and increment badges
                                updateTotalBadges();

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

                // Hide from Salary
                document.querySelectorAll('.salary-hide-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const userId = this.dataset.userId;
                        const row = this.closest('tr');
                        
                        if (!confirm('Hide this user from Salary tab? You can show them again later.')) return;

                        fetch(`/users/${userId}/toggle-salary-visibility`, {
                            method: 'POST',
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
                                // Remove the row from the table with fade effect
                                row.style.opacity = '0';
                                row.style.transition = 'opacity 0.3s';
                                setTimeout(() => {
                                    row.remove();
                                    // Update row numbers
                                    document.querySelectorAll('#salaryTable tbody tr').forEach((tr, idx) => {
                                        tr.querySelector('td:first-child').textContent = idx + 1;
                                    });
                                }, 300);
                            } else {
                                showToast(result.message || 'Failed to hide user', 'error');
                            }
                        })
                        .catch(() => showToast('Failed to hide user', 'error'));
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

        function updateTotalBadges() {
            let totalSalary = 0;
            let totalIncrement = 0;
            let totalAmountP = 0;
            let totalAdvIncOther = 0;
            
            document.querySelectorAll('#usersTable tbody tr').forEach(row => {
                const salaryCell = row.querySelector('[data-field="salary_pp"] .user-edit');
                const salaryVal = (salaryCell && salaryCell.value) ? (parseFloat(salaryCell.value) || 0) : 0;
                totalSalary += salaryVal;
                
                const incrementCell = row.querySelector('[data-field="increment"] .user-edit');
                const incrementVal = (incrementCell && incrementCell.value) ? (parseFloat(incrementCell.value) || 0) : 0;
                totalIncrement += incrementVal;
                
                // Calculate Salary LM for this row
                const salaryLMVal = salaryVal + incrementVal;
                
                // Calculate Amount P for this row
                const hoursLMCell = row.querySelector('.user-hours-lm-cell .hours-lm-badge');
                const hoursLM = hoursLMCell ? (parseFloat(hoursLMCell.textContent) || 0) : 0;
                const otherCell = row.querySelector('[data-field="other"] .user-edit');
                const otherVal = (otherCell && otherCell.value) ? (parseFloat(otherCell.value) || 0) : 0;
                
                // Sum Adv/Inc/Other
                const advIncOtherCell = row.querySelector('[data-field="adv_inc_other"] .user-edit');
                const advIncOtherVal = (advIncOtherCell && advIncOtherCell.value) ? (parseFloat(advIncOtherCell.value) || 0) : 0;
                totalAdvIncOther += advIncOtherVal;
                
                const amountP = ((hoursLM * salaryLMVal) / 200) + otherVal - advIncOtherVal;
                totalAmountP += amountP;
            });
            
            const totalSalaryLM = totalSalary + totalIncrement;
            
            const salaryBadge = document.querySelector('.badge.bg-success');
            if (salaryBadge) {
                salaryBadge.innerHTML = '<i class="ri-money-dollar-circle-line me-2"></i>Salary PP: ₹' + Math.round(totalSalary).toLocaleString('en-IN');
            }
            
            const incrementBadge = document.querySelector('.badge.bg-info');
            if (incrementBadge) {
                incrementBadge.innerHTML = '<i class="ri-funds-line me-2"></i>Increment: ₹' + Math.round(totalIncrement).toLocaleString('en-IN');
            }
            
            const salaryLMBadge = document.querySelector('.badge.bg-warning');
            if (salaryLMBadge) {
                salaryLMBadge.innerHTML = '<i class="ri-wallet-3-line me-2"></i>Salary LM: ₹' + Math.round(totalSalaryLM).toLocaleString('en-IN');
            }
            
            const amountPBadge = document.querySelector('.badge.bg-secondary');
            if (amountPBadge) {
                amountPBadge.innerHTML = '<i class="ri-calculator-line me-2"></i>Amount P: ₹' + (Math.round(totalAmountP / 100) * 100).toLocaleString('en-IN');
            }
            
            const advIncOtherBadge = document.querySelector('.badge.bg-dark');
            if (advIncOtherBadge) {
                advIncOtherBadge.innerHTML = '<i class="ri-file-list-3-line me-2"></i>Advance: ₹' + Math.round(totalAdvIncOther).toLocaleString('en-IN');
            }
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

        // Import functionality
        document.addEventListener('DOMContentLoaded', function() {
            const importBtn = document.getElementById('importBtn');
            const importFile = document.getElementById('importFile');
            
            if (importBtn && importFile) {
                importBtn.addEventListener('click', function() {
                    importFile.click();
                });
                
                importFile.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (!file) return;
                    
                    // Validate file type
                    if (!file.name.endsWith('.csv')) {
                        showToast('Please select a CSV file', 'error');
                        return;
                    }
                    
                    // Show loading state
                    const originalHTML = importBtn.innerHTML;
                    importBtn.disabled = true;
                    importBtn.innerHTML = '<i class="ri-loader-4-line spin me-2"></i>Importing...';
                    
                    // Prepare form data
                    const formData = new FormData();
                    formData.append('file', file);
                    formData.append('_token', '{{ csrf_token() }}');
                    
                    // Send to server
                    fetch('{{ route("users.import") }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: formData
                    })
                    .then(response => response.json())
                    .then(result => {
                        importBtn.disabled = false;
                        importBtn.innerHTML = originalHTML;
                        importFile.value = ''; // Reset file input
                        
                        if (result.success) {
                            showToast(result.message, 'success');
                            // Reload page to show updated values
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
                        } else {
                            showToast(result.message || 'Import failed', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        importBtn.disabled = false;
                        importBtn.innerHTML = originalHTML;
                        importFile.value = '';
                        showToast('An error occurred while importing', 'error');
                    });
                });
            }
        });

        // Copy Salary LM to PP functionality
        document.addEventListener('DOMContentLoaded', function() {
            const copySalaryBtn = document.getElementById('copySalaryBtn');
            if (copySalaryBtn) {
                copySalaryBtn.addEventListener('click', function() {
                    if (confirm('⚠️ WARNING: This will copy all Salary LM values to Salary PP and reset increments to 0 for all active users.\n\nThis action cannot be undone. Do you want to continue?')) {
                        // Show loading state
                        const originalHTML = copySalaryBtn.innerHTML;
                        copySalaryBtn.disabled = true;
                        copySalaryBtn.innerHTML = '<i class="ri-loader-4-line spin me-2"></i>Processing...';
                        
                        // Send request to server
                        fetch('{{ route("users.copySalaryLmToPp") }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json',
                                'Content-Type': 'application/json'
                            }
                        })
                        .then(response => response.json())
                        .then(result => {
                            copySalaryBtn.disabled = false;
                            copySalaryBtn.innerHTML = originalHTML;
                            
                            if (result.success) {
                                showToast(result.message, 'success');
                                // Reload page to show updated values
                                setTimeout(() => {
                                    window.location.reload();
                                }, 1500);
                            } else {
                                showToast(result.message || 'Operation failed', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            copySalaryBtn.disabled = false;
                            copySalaryBtn.innerHTML = originalHTML;
                            showToast('An error occurred while processing the request', 'error');
                        });
                    }
                });
            }
        });

        // Export functionality
        document.addEventListener('DOMContentLoaded', function() {
            const exportBtn = document.getElementById('exportBtn');
            if (exportBtn) {
                exportBtn.addEventListener('click', function() {
                    // Show loading state
                    const originalHTML = exportBtn.innerHTML;
                    exportBtn.disabled = true;
                    exportBtn.innerHTML = '<i class="ri-loader-4-line spin me-2"></i>Exporting...';

                    // Trigger server-side export
                    window.location.href = '{{ route("users.export") }}';

                    // Reset button after a delay
                    setTimeout(() => {
                        exportBtn.disabled = false;
                        exportBtn.innerHTML = originalHTML;
                        showToast('Data exported successfully! A copy has been saved to history.', 'success');
                    }, 2000);
                });
            }
        });

        // Download Salary PP Template
        document.addEventListener('DOMContentLoaded', function() {
            const downloadSalaryTemplateBtn = document.getElementById('downloadSalaryTemplateBtn');
            if (downloadSalaryTemplateBtn) {
                downloadSalaryTemplateBtn.addEventListener('click', function() {
                    // Get all active users with their salary data
                    const activeUsersData = {!! json_encode($users->map(function($user) {
                        return array(
                            'name' => $user->name,
                            'salary_pp' => $user->userSalary?->salary_pp ?? '',
                            'increment' => $user->userSalary?->increment ?? '',
                            'other' => $user->userSalary?->other ?? '',
                            'adv_inc_other' => $user->userSalary?->adv_inc_other ?? ''
                        );
                    })) !!};
                    
                    // Create CSV content with headers
                    let csvContent = '"Name","Salary PP","Increment","Other","Adv"\n';
                    
                    // Add all active users with their current salary data or empty
                    activeUsersData.forEach(user => {
                        const salaryPP = user.salary_pp ? Math.round(parseFloat(user.salary_pp)) : '';
                        const increment = user.increment ? Math.round(parseFloat(user.increment)) : '';
                        const other = user.other ? Math.round(parseFloat(user.other)) : '';
                        const advIncOther = user.adv_inc_other ? Math.round(parseFloat(user.adv_inc_other)) : '';
                        csvContent += `"${user.name}","${salaryPP}","${increment}","${other}","${advIncOther}"\n`;
                    });
                    
                    // Create blob and download
                    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                    const link = document.createElement('a');
                    const url = URL.createObjectURL(blob);
                    
                    link.setAttribute('href', url);
                    link.setAttribute('download', 'salary_data_import_template.csv');
                    link.style.visibility = 'hidden';
                    
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    showToast('Template downloaded successfully!', 'success');
                });
            }
        });
    </script>
            </div>
            <!-- End Users Tab -->

            <!-- Salary Tab -->
            <div class="tab-pane fade" id="salary-content" role="tabpanel">
                @if($canEdit)
                <div class="card shadow-sm">
                    <div class="card-body">
                        <!-- Search Form -->
                        <div class="mb-2">
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0">
                                    <i class="fas fa-search text-muted"></i>
                                </span>
                                <input type="text" id="searchSalaryInput" class="form-control border-0 bg-light" 
                                    placeholder="Search by name, salary, increment, salary LM, hours LM, other, amount LM, amount P, advance, or banks" onkeyup="filterSalaryTable()">
                            </div>
                        </div>

                        <!-- Salary Table -->
                        <div class="users-active-table-wrap">
                            <table class="table users-table align-middle" id="salaryTable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>Salary PP</th>
                                        <th>Incr</th>
                                        <th>Salary LM</th>
                                        <th>Hours LM</th>
                                        <th>Other</th>
                                        <th>Amt LM</th>
                                        <th>Amt P</th>
                                        <th>Adv</th>
                                        <th>B1</th>
                                        <th>B2</th>
                                        <th>UPI</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($salaryUsers as $index => $user)
                                        <tr data-user-id="{{ $user->id }}" data-show-in-salary="{{ $user->show_in_salary ? 'true' : 'false' }}">
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
                                                <!-- Hidden fields for required data not displayed in Salary tab -->
                                                <input type="hidden" class="salary-tab-email" value="{{ $user->email }}" data-field="email">
                                                <input type="hidden" class="salary-tab-phone" value="{{ $user->phone ?? '' }}" data-field="phone">
                                                <input type="hidden" class="salary-tab-designation" value="{{ $user->designation ?? '' }}" data-field="designation">
                                                <input type="hidden" class="salary-tab-rr" value="{{ $user->userRR?->role ?? '' }}" data-field="rr_role">
                                                <input type="hidden" class="salary-tab-resources" value="{{ $user->userRR?->resources ?? '' }}" data-field="resources">
                                                <input type="hidden" class="salary-tab-training" value="{{ $user->userRR?->training ?? '' }}" data-field="training">
                                            </td>
                                            <td>
                                                @php $salaryPP = $user->userSalary?->salary_pp ?? ''; @endphp
                                                <div class="user-salary-cell" data-field="salary_pp">
                                                    @if($salaryPP !== '')
                                                        <span class="user-display salary-badge">₹{{ number_format($salaryPP, 0) }}</span>
                                                    @else
                                                        <span class="user-display text-muted">—</span>
                                                    @endif
                                                    <input type="number" step="1" min="0" class="form-control form-control-sm user-edit d-none" value="{{ $salaryPP !== '' ? round($salaryPP) : '' }}" data-field="salary_pp" placeholder="Salary PP">
                                                </div>
                                            </td>
                                            <td>
                                                @php $increment = $user->userSalary?->increment ?? ''; @endphp
                                                <div class="user-increment-cell" data-field="increment">
                                                    @if($increment !== '')
                                                        <span class="user-display increment-badge">₹{{ number_format($increment, 0) }}</span>
                                                    @else
                                                        <span class="user-display text-muted">—</span>
                                                    @endif
                                                    <input type="number" step="1" min="0" class="form-control form-control-sm user-edit d-none" value="{{ $increment !== '' ? round($increment) : '' }}" data-field="increment" placeholder="Increment">
                                                </div>
                                            </td>
                                            <td>
                                                @php 
                                                    $salaryPPVal = $user->userSalary?->salary_pp ?? 0;
                                                    $incrementVal = $user->userSalary?->increment ?? 0;
                                                    $salaryLM = $salaryPPVal + $incrementVal;
                                                @endphp
                                                <div class="user-salary-lm-cell">
                                                    @if($salaryLM > 0)
                                                        <span class="user-display salary-lm-badge">₹{{ number_format($salaryLM, 0) }}</span>
                                                    @else
                                                        <span class="user-display text-muted">—</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                @php 
                                                    $userEmail = strtolower(trim($user->email));
                                                    $teamLoggerEmail = getTeamLoggerEmail($userEmail, $emailMapping);
                                                    $hoursLM = $teamLoggerData[$teamLoggerEmail]['hours'] ?? 0;
                                                @endphp
                                                <div class="user-hours-lm-cell">
                                                    @if($hoursLM > 0)
                                                        <span class="hours-lm-badge" title="{{ $previousMonth }}: {{ $hoursLM }} hours">{{ $hoursLM }}h</span>
                                                    @else
                                                        <span class="text-muted" title="No hours logged in {{ $previousMonth }}">—</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                @php $other = $user->userSalary?->other ?? ''; @endphp
                                                <div class="user-other-cell" data-field="other" data-original="{{ $other }}">
                                                    @if($other !== '' && $other > 0)
                                                        <span class="user-display other-badge">₹{{ number_format($other, 0) }}</span>
                                                    @else
                                                        <span class="user-display text-muted">—</span>
                                                    @endif
                                                    <input type="number" step="1" min="0" class="form-control form-control-sm user-edit d-none" value="{{ $other !== '' ? round($other) : '' }}" data-field="other" placeholder="Other">
                                                </div>
                                            </td>
                                            <td>
                                                @php 
                                                    $userEmail = strtolower(trim($user->email));
                                                    $teamLoggerEmail = getTeamLoggerEmail($userEmail, $emailMapping);
                                                    $hoursLM = $teamLoggerData[$teamLoggerEmail]['hours'] ?? 0;
                                                    $salaryPPVal = $user->userSalary?->salary_pp ?? 0;
                                                    $incrementVal = $user->userSalary?->increment ?? 0;
                                                    $salaryLM = $salaryPPVal + $incrementVal;
                                                    $other = $user->userSalary?->other ?? 0;
                                                    $advIncOther = $user->userSalary?->adv_inc_other ?? 0;
                                                    $amountLM = (($hoursLM * $salaryLM) / 200);
                                                @endphp
                                                <div class="user-amount-lm-cell">
                                                    @if($amountLM != 0)
                                                        <span class="amount-lm-badge">₹{{ number_format($amountLM, 0) }}</span>
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                @php 
                                                    $userEmail = strtolower(trim($user->email));
                                                    $teamLoggerEmail = getTeamLoggerEmail($userEmail, $emailMapping);
                                                    $hoursLM = $teamLoggerData[$teamLoggerEmail]['hours'] ?? 0;
                                                    $salaryPPVal = $user->userSalary?->salary_pp ?? 0;
                                                    $incrementVal = $user->userSalary?->increment ?? 0;
                                                    $salaryLM = $salaryPPVal + $incrementVal;
                                                    $other = $user->userSalary?->other ?? 0;
                                                    $advIncOther = $user->userSalary?->adv_inc_other ?? 0;
                                                    $amountP = (($hoursLM * $salaryLM) / 200) + $other - $advIncOther;
                                                @endphp
                                                <div class="user-amount-p-cell">
                                                    @if($amountP != 0)
                                                        <span class="amount-p-badge">₹{{ number_format(round($amountP / 100) * 100, 0) }}</span>
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                @php $advIncOther = $user->userSalary?->adv_inc_other ?? ''; @endphp
                                                <div class="user-adv-inc-other-cell" data-field="adv_inc_other" data-original="{{ $advIncOther }}">
                                                    @if($advIncOther !== '' && $advIncOther > 0)
                                                        <span class="user-display adv-inc-other-badge">₹{{ number_format($advIncOther, 0) }}</span>
                                                    @else
                                                        <span class="user-display text-muted">—</span>
                                                    @endif
                                                    <input type="number" step="1" min="0" class="form-control form-control-sm user-edit d-none" value="{{ $advIncOther !== '' ? round($advIncOther) : '' }}" data-field="adv_inc_other" placeholder="Adv">
                                                </div>
                                            </td>
                                            <td>
                                                @php $bank1 = $user->userSalary?->bank_1 ?? ''; @endphp
                                                <div class="user-bank-1-cell" data-field="bank_1" data-original="{{ $bank1 }}">
                                                    <span class="user-display">
                                                        <span class="data-indicator {{ $bank1 ? 'indicator-filled' : 'indicator-empty' }}" data-tooltip="{{ $bank1 ?: 'No data' }}">
                                                            <span class="tooltip-text">{{ $bank1 ?: 'No Bank 1 data' }}</span>
                                                        </span>
                                                    </span>
                                                    <input type="text" class="form-control form-control-sm user-edit d-none" value="{{ $bank1 }}" data-field="bank_1" placeholder="B1">
                                                </div>
                                            </td>
                                            <td>
                                                @php $bank2 = $user->userSalary?->bank_2 ?? ''; @endphp
                                                <div class="user-bank-2-cell" data-field="bank_2" data-original="{{ $bank2 }}">
                                                    <span class="user-display">
                                                        <span class="data-indicator {{ $bank2 ? 'indicator-filled' : 'indicator-empty' }}" data-tooltip="{{ $bank2 ?: 'No data' }}">
                                                            <span class="tooltip-text">{{ $bank2 ?: 'No Bank 2 data' }}</span>
                                                        </span>
                                                    </span>
                                                    <input type="text" class="form-control form-control-sm user-edit d-none" value="{{ $bank2 }}" data-field="bank_2" placeholder="B2">
                                                </div>
                                            </td>
                                            <td>
                                                @php $upiId = $user->userSalary?->upi_id ?? ''; @endphp
                                                <div class="user-upi-id-cell" data-field="upi_id" data-original="{{ $upiId }}">
                                                    <span class="user-display">
                                                        <span class="data-indicator {{ $upiId ? 'indicator-filled' : 'indicator-empty' }}" data-tooltip="{{ $upiId ?: 'No data' }}">
                                                            <span class="tooltip-text">{{ $upiId ?: 'No UPI ID data' }}</span>
                                                        </span>
                                                    </span>
                                                    <input type="text" class="form-control form-control-sm user-edit d-none" value="{{ $upiId }}" data-field="upi_id" placeholder="UPI">
                                                </div>
                                            </td>
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
                                                    @if($canEdit)
                                                        <button type="button" class="btn-action btn-warning-soft salary-hide-btn" data-user-id="{{ $user->id }}" title="Hide from Salary">
                                                            <i class="ri-eye-off-line"></i>
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
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="13" class="text-center py-4 text-muted">No users found</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @else
                    <div class="alert alert-warning">
                        <i class="ri-lock-line me-2"></i>You don't have permission to view salary information.
                    </div>
                @endif
            </div>
            <!-- End Salary Tab -->

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
