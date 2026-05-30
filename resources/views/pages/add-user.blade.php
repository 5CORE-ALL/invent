@extends('layouts.vertical', ['title' => 'Team Management'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        /* Tabulator users table */
        #usersTabulator .avatar-circle {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 13px;
            flex: 0 0 auto;
        }
        #usersTabulator .user-avatar-img {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            object-fit: cover;
            background: #eef2ff;
            flex: 0 0 auto;
        }
        #usersTabulator .tbl-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        #usersTabulator .tbl-dot--green { background: #22c55e; }
        #usersTabulator .tbl-dot--red { background: #ef4444; }
        #usersTabulator .designation-badge {
            background: #eef2ff;
            color: #4338ca;
            font-weight: 600;
            border-radius: 6px;
            padding: 2px 8px;
            font-size: 12px;
        }
    </style>
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
            @if($canEdit && $canViewSalary)
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
        
        @if($canEdit && $canViewSalary)
        <div class="mb-4">
            <div class="d-flex gap-2 flex-nowrap w-100 salary-badges-container">
                <span class="badge bg-primary fs-6 px-2 py-2 flex-fill text-center">
                    <i class="ri-team-line me-1"></i>
                    Team: <span id="teamCountBadge">{{ $users->count() }}</span>
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
        </div>
        @endif

        <!-- Tabs Navigation -->
        <ul class="nav nav-tabs mb-4" id="userManagementTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="users-tab" data-bs-toggle="tab" data-bs-target="#users-content" type="button" role="tab">
                    <i class="ri-user-line me-2"></i>Users
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="userManagementTabContent">
            <!-- Users Tab -->
            <div class="tab-pane fade show active" id="users-content" role="tabpanel">

        <!-- Users Table Section (Tabulator) -->
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <div class="input-group" style="max-width: 440px;">
                        <span class="input-group-text bg-light border-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" id="usersSearch" class="form-control border-0 bg-light"
                            placeholder="Search by name, phone, email, designation, R&amp;R, resources, training">
                    </div>
                    <div class="btn-group status-toggle" role="group" aria-label="Filter by status">
                        <button type="button" class="btn btn-success active" id="statusActiveBtn">
                            <i class="ri-checkbox-blank-circle-fill me-1"></i>Active
                        </button>
                        <button type="button" class="btn btn-outline-danger" id="statusInactiveBtn">
                            <i class="ri-checkbox-blank-circle-fill me-1"></i>Inactive
                        </button>
                    </div>
                </div>

                <div id="usersTabulator"></div>
            </div>
        </div>

    </div>

    <!-- Edit User Modal -->
    @if($canEdit)
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form id="editUserForm">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="ri-edit-line me-2"></i>Edit User</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="editUserId" name="id">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="editName" name="name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="editEmail" name="email" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Phone</label>
                                <input type="text" class="form-control" id="editPhone" name="phone" maxlength="20">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Designation</label>
                                <input type="text" class="form-control" id="editDesignation" name="designation">
                            </div>
                        </div>
                        <div class="alert alert-danger mt-3 d-none" id="editUserError"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="editUserSaveBtn">
                            <i class="ri-save-line me-1"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    <!-- Resume Modal -->
    @if($canViewResume)
    <div class="modal fade" id="resumeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="ri-file-user-line me-2"></i>Resume — <span id="resumeUserName"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="resumeUserId">
                    <div class="mb-3">
                        <span class="fw-semibold d-block mb-1">Current file</span>
                        <div id="resumeCurrent" class="d-flex align-items-center gap-2"></div>
                    </div>
                    @if($canEditResume)
                    <div class="mb-2">
                        <label class="form-label fw-semibold" for="resumeFile">Upload / replace (PDF, DOC, DOCX — max 10MB)</label>
                        <input type="file" class="form-control" id="resumeFile" accept=".pdf,.doc,.docx">
                    </div>
                    @endif
                    <div class="alert alert-danger mt-3 d-none" id="resumeError"></div>
                </div>
                <div class="modal-footer">
                    @if($canEditResume)
                    <button type="button" class="btn btn-outline-danger me-auto d-none" id="resumeDeleteBtn">
                        <i class="ri-delete-bin-line me-1"></i>Remove
                    </button>
                    @endif
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    @if($canEditResume)
                    <button type="button" class="btn btn-primary" id="resumeUploadBtn">
                        <i class="ri-upload-2-line me-1"></i>Save
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Docs Modal -->
    @if($canViewResume)
    <div class="modal fade" id="docsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="ri-folder-3-line me-2"></i>Docs — <span id="docsUserName"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="docsUserId">

                    <h6 class="fw-semibold mb-2"><i class="ri-attachment-2 me-1"></i>Attached</h6>
                    <ul class="list-group mb-3" id="docsList"></ul>

                    @if($canEditResume)
                    <div class="border rounded p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-semibold mb-0"><i class="ri-add-circle-line me-1"></i>Add documents</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="docAddRowBtn">
                                <i class="ri-add-line me-1"></i>Add option
                            </button>
                        </div>
                        <div class="row g-2 fw-semibold text-muted small d-none d-md-flex mb-1">
                            <div class="col-md-4">File name</div>
                            <div class="col-md-4">File upload</div>
                            <div class="col-md-3">File link</div>
                            <div class="col-md-1"></div>
                        </div>
                        <div id="docRows"></div>
                        <div class="text-muted small mt-1">For each option add a file <em>or</em> a link (a name is optional).</div>
                    </div>
                    @endif

                    <div class="alert alert-danger mt-3 d-none" id="docsError"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    @if($canEditResume)
                    <button type="button" class="btn btn-primary" id="docsSaveBtn">
                        <i class="ri-save-line me-1"></i>Save
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Bank Details Modal -->
    @if($canViewSalary)
    <div class="modal fade" id="bankModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="ri-bank-line me-2"></i>Bank Details — <span id="bankUserName"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="bankUserId">
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="bankInput1">Bank 1</label>
                        <textarea class="form-control" id="bankInput1" rows="2" placeholder="Account name, bank, account number..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="bankInput2">Bank 2</label>
                        <textarea class="form-control" id="bankInput2" rows="2" placeholder="Secondary bank details..."></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold" for="bankInputUpi">UPI ID</label>
                        <input type="text" class="form-control" id="bankInputUpi" placeholder="name@bank">
                    </div>
                    <div class="text-muted small"><i class="ri-information-line me-1"></i>Leave a field blank to keep its current value — bank details are never deleted, only updated.</div>
                    <div class="alert alert-danger mt-2 d-none" id="bankError"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="bankSaveBtn"><i class="ri-save-line me-1"></i>Save</button>
                </div>
            </div>
        </div>
    </div>
    @endif

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

    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>

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
                        const hoursLmInput = row.querySelector('[data-field="hours_lm"] .user-edit');
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
                            hours_lm: hoursLmInput ? hoursLmInput.value.trim() : '',
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
                        if (hoursLmInput) formData.append('hours_lm', data.hours_lm);
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

                                // Update Hours LM display with saved value
                                const hoursLMDisplay = row.querySelector('.user-hours-lm-cell .user-display');
                                const hoursLMValue = parseFloat(data.hours_lm) || 0;
                                if (hoursLMDisplay) {
                                    if (hoursLMValue > 0) {
                                        hoursLMDisplay.textContent = hoursLMValue + 'h';
                                        hoursLMDisplay.className = 'user-display hours-lm-badge';
                                    } else {
                                        hoursLMDisplay.textContent = '—';
                                        hoursLMDisplay.className = 'user-display text-muted';
                                    }
                                }

                                // Update Amount LM (calculated field)
                                const amountLMDisplay = row.querySelector('.user-amount-lm-cell span');
                                const hoursLM = hoursLMValue;
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
                                
                                // Reload page if Hours LM was edited to fetch fresh TeamLogger data
                                if (hoursLmInput && data.hours_lm) {
                                    setTimeout(() => {
                                        window.location.reload();
                                    }, 1000);
                                }
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
            
            document.querySelectorAll('#salaryTable tbody tr').forEach(row => {
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

        // ============ Active Users Tabulator + Edit Modal ============
        @php
            $usersTableData = $allUsers->values()->map(function ($u) use ($currentMonthData, $emailMapping) {
                $tlEmail = getTeamLoggerEmail($u->email ?? '', $emailMapping);
                $workingHours = $currentMonthData[$tlEmail]['hours'] ?? 0;
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'avatar_url' => ! empty($u->avatar)
                        ? asset('storage/' . $u->avatar)
                        : asset('images/users/add-image-placeholder.svg'),
                    'phone' => $u->phone ?? '',
                    'email' => $u->email ?? '',
                    'designation' => $u->designation ?? '',
                    'working_hours' => $workingHours,
                    'resources' => $u->userRR?->resources ?? '',
                    'training' => $u->userRR?->training ?? '',
                    'checklist_url' => ! empty($u->designation)
                        ? route('performance.checklist.get', ['designationId' => $u->designation])
                        : '',
                    'has_resume' => ! empty($u->resume_path),
                    'resume_url' => route('users.resume.show', $u),
                    'resume_name' => $u->resume_original_name ?? '',
                    'is_active' => (bool) $u->is_active,
                    'bank_1' => $u->userSalary?->bank_1 ?? '',
                    'bank_2' => $u->userSalary?->bank_2 ?? '',
                    'upi_id' => $u->userSalary?->upi_id ?? '',
                    'has_docs' => $u->docs->isNotEmpty(),
                    'docs' => $u->docs->map(function ($d) use ($u) {
                        return [
                            'id' => $d->id,
                            'type' => $d->type,
                            'name' => $d->type === 'file' ? ($d->label ?: ($d->original_name ?: 'File')) : ($d->label ?: $d->url),
                            'url' => $d->type === 'link' ? $d->url : route('users.docs.download', [$u->id, $d->id]),
                        ];
                    })->values(),
                    'is_self' => $u->id === auth()->id(),
                ];
            });
        @endphp
        document.addEventListener('DOMContentLoaded', function () {
            const tableEl = document.getElementById('usersTabulator');
            if (!tableEl || typeof Tabulator === 'undefined') return;

            const usersData = @json($usersTableData);
            const csrfToken = '{{ csrf_token() }}';
            const canViewResume = {{ $canViewResume ? 'true' : 'false' }};
            const canEditResume = {{ $canEditResume ? 'true' : 'false' }};
            const canViewSalary = {{ $canViewSalary ? 'true' : 'false' }};

            const esc = (s) => String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            const dotFmt = (val, label) => {
                const has = val !== null && String(val).trim() !== '';
                return '<span class="tbl-dot ' + (has ? 'tbl-dot--green' : 'tbl-dot--red') + '" title="' +
                    (has ? esc(val) : 'No ' + label) + '"></span>';
            };
            const textFmt = (c) => c.getValue() ? esc(c.getValue()) : '<span class="text-muted">-</span>';

            const columns = [
                { title: '#', formatter: 'rownum', width: 55, hozAlign: 'center', headerSort: false },
                {
                    title: 'Image', field: 'avatar_url', width: 80, hozAlign: 'center', headerSort: false,
                    formatter: (c) => {
                        const d = c.getRow().getData();
                        const placeholder = '{{ asset('images/users/add-image-placeholder.svg') }}';
                        const src = d.avatar_url || placeholder;
                        return '<img src="' + src + '" class="user-avatar-img" alt="" loading="lazy" onerror="this.onerror=null;this.src=\'' + placeholder + '\';">';
                    }
                },
                { title: 'Name', field: 'name', minWidth: 160, formatter: (c) => esc(c.getValue() || '') },
                { title: 'Phone', field: 'phone', width: 80, hozAlign: 'center', headerSort: false, formatter: (c) => dotFmt(c.getValue(), 'phone number') },
                { title: 'Email', field: 'email', width: 80, hozAlign: 'center', headerSort: false, formatter: (c) => dotFmt(c.getValue(), 'email') },
                { title: 'Designation', field: 'designation', minWidth: 150, formatter: (c) => c.getValue() ? '<span class="designation-badge">' + esc(c.getValue()) + '</span>' : '<span class="text-muted">-</span>' },
                {
                    title: 'Hours', field: 'working_hours', width: 90, hozAlign: 'center', headerSort: true,
                    titleFormatter: () => '<span title="Productive working hours ({{ $currentMonth }})">Hours</span>',
                    formatter: (c) => {
                        const v = parseFloat(c.getValue());
                        if (!v || isNaN(v)) return '<span class="text-muted">—</span>';
                        return '<span class="badge bg-info" title="{{ $currentMonth }}">' + v + 'h</span>';
                    }
                },
                {
                    title: 'Active', field: 'is_active', width: 80, hozAlign: 'center', headerSort: false,
                    formatter: (c) => {
                        const d = c.getRow().getData();
                        const active = d.is_active !== false;
                        const dotCls = active ? 'tbl-dot--green' : 'tbl-dot--red';
                        const clickable = canEdit && !d.is_self;
                        const tip = active
                            ? (clickable ? 'Active — click to deactivate' : 'Active')
                            : (clickable ? 'Inactive — click to activate' : 'Inactive');
                        const style = clickable ? 'cursor:pointer;' : '';
                        return '<span class="tbl-dot status-dot ' + dotCls + '" style="' + style + '" title="' + tip + '"></span>';
                    },
                    cellClick: function (e, cell) {
                        if (!canEdit) return;
                        const d = cell.getRow().getData();
                        if (d.is_self) return;
                        toggleActive(d, cell.getRow());
                    }
                },
            ];

            if (canViewResume) {
                columns.push({
                    title: 'Docs', field: 'has_docs', width: 90, hozAlign: 'center', headerSort: false,
                    formatter: (c) => {
                        const d = c.getRow().getData();
                        const tip = d.has_docs
                            ? (canEditResume ? 'Docs attached — click to view/manage' : 'View docs')
                            : (canEditResume ? 'No docs — click to add' : 'No docs');
                        const cls = d.has_docs ? 'tbl-dot--green' : 'tbl-dot--red';
                        return '<span class="tbl-dot docs-dot ' + cls + '" style="cursor:pointer;" title="' + esc(tip) + '"></span>';
                    },
                    cellClick: function (e, cell) {
                        openDocsModal(cell.getRow().getData(), cell.getRow());
                    }
                });
            }

            if (canViewSalary) {
                columns.push({
                    title: 'Bank', field: 'bank_1', width: 90, hozAlign: 'center', headerSort: false,
                    formatter: (c) => {
                        const d = c.getRow().getData();
                        const has = [d.bank_1, d.bank_2, d.upi_id].some((v) => v && String(v).trim() !== '');
                        return has
                            ? '<button type="button" class="btn btn-sm btn-success bank-view-btn py-0" title="View / edit bank details"><i class="ri-eye-line"></i></button>'
                            : '<button type="button" class="btn btn-sm btn-outline-secondary bank-view-btn py-0" title="Add bank details"><i class="ri-bank-line"></i></button>';
                    },
                    cellClick: function (e, cell) {
                        if (!e.target.closest('.bank-view-btn')) return;
                        openBankModal(cell.getRow().getData(), cell.getRow());
                    }
                });
            }

            if (canEdit) {
                columns.push({
                    title: 'Action', field: 'id', width: 110, hozAlign: 'center', headerSort: false, frozen: true,
                    formatter: (c) => {
                        const d = c.getRow().getData();
                        let html = '<button type="button" class="btn btn-sm btn-light border edit-user-btn" title="Edit"><i class="ri-edit-line"></i></button>';
                        if (!d.is_self) {
                            html += ' <button type="button" class="btn btn-sm btn-light border text-danger delete-user-btn" title="Deactivate user"><i class="ri-delete-bin-line"></i></button>';
                        }
                        return html;
                    },
                    cellClick: function (e, cell) {
                        const d = cell.getRow().getData();
                        if (e.target.closest('.edit-user-btn')) {
                            openEditModal(d);
                        } else if (e.target.closest('.delete-user-btn')) {
                            deactivateUser(d, cell.getRow());
                        }
                    }
                });
            }

            const usersTable = new Tabulator('#usersTabulator', {
                data: usersData,
                index: 'id',
                layout: 'fitColumns',
                height: '65vh',
                placeholder: 'No users found',
                pagination: true,
                paginationSize: 50,
                paginationSizeSelector: [25, 50, 100, 200],
                columnDefaults: {
                    hozAlign: 'center',
                    headerHozAlign: 'center',
                    vertAlign: 'middle',
                },
                columns: columns,
                tableBuilt: function () {
                    updateActiveCount();
                },
            });
            window.usersTable = usersTable;

            // Toggle a user's active status by clicking the status dot.
            function toggleActive(d, row) {
                const userId = d.id;
                if (d.is_active !== false) {
                    if (!confirm('Set this user to Inactive?')) return;
                    fetch('/users/' + userId, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(r => r.json())
                    .then(result => {
                        if (result.success) {
                            showToast(result.message, 'success');
                            if (row) row.update({ is_active: false });
                            applyUsersFilter();
                        } else {
                            showToast(result.message || 'Failed to update status', 'error');
                        }
                    })
                    .catch(() => showToast('Failed to update status', 'error'));
                } else {
                    if (!confirm('Set this user to Active?')) return;
                    const fd = new FormData();
                    fd.append('_token', csrfToken);
                    fetch('/users/' + userId + '/restore', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                        body: fd
                    })
                    .then(r => r.json())
                    .then(result => {
                        if (result.success) {
                            showToast(result.message, 'success');
                            if (row) row.update({ is_active: true });
                            applyUsersFilter();
                        } else {
                            showToast(result.message || 'Failed to update status', 'error');
                        }
                    })
                    .catch(() => showToast('Failed to update status', 'error'));
                }
            }

            const searchInput = document.getElementById('usersSearch');
            let statusFilter = 'active';

            function applyUsersFilter() {
                const term = (searchInput && searchInput.value ? searchInput.value : '').toLowerCase();
                usersTable.setFilter((data) => {
                    const statusOk = statusFilter === 'active' ? (data.is_active !== false) : (data.is_active === false);
                    if (!statusOk) return false;
                    if (!term) return true;
                    return ['name', 'email', 'phone', 'designation', 'resources', 'training']
                        .some((f) => String(data[f] || '').toLowerCase().includes(term));
                });
                updateActiveCount();
            }
            window.applyUsersFilter = applyUsersFilter;

            // Team badge always shows the total number of ACTIVE rows (regardless of the toggle/search).
            function updateActiveCount() {
                const badge = document.getElementById('teamCountBadge');
                if (!badge) return;
                let data = [];
                try { data = usersTable.getData(); } catch (e) { /* table not built yet */ }
                if (!data || !data.length) data = usersData; // fallback before the table finishes building
                badge.textContent = data.filter((d) => d.is_active !== false).length;
            }
            window.updateActiveCount = updateActiveCount;

            if (searchInput) {
                searchInput.addEventListener('keyup', applyUsersFilter);
            }

            const statusActiveBtn = document.getElementById('statusActiveBtn');
            const statusInactiveBtn = document.getElementById('statusInactiveBtn');
            function setStatusFilter(status) {
                statusFilter = status;
                if (status === 'active') {
                    statusActiveBtn.className = 'btn btn-success active';
                    statusInactiveBtn.className = 'btn btn-outline-danger';
                } else {
                    statusActiveBtn.className = 'btn btn-outline-success';
                    statusInactiveBtn.className = 'btn btn-danger active';
                }
                applyUsersFilter();
            }
            if (statusActiveBtn) statusActiveBtn.addEventListener('click', () => setStatusFilter('active'));
            if (statusInactiveBtn) statusInactiveBtn.addEventListener('click', () => setStatusFilter('inactive'));

            // Default view: active users only.
            applyUsersFilter();

            const modalEl = document.getElementById('editUserModal');
            const bsModal = (modalEl && window.bootstrap) ? new bootstrap.Modal(modalEl) : null;
            const form = document.getElementById('editUserForm');
            const errBox = document.getElementById('editUserError');

            function openEditModal(d) {
                if (!bsModal) return;
                errBox.classList.add('d-none');
                errBox.textContent = '';
                document.getElementById('editUserId').value = d.id;
                document.getElementById('editName').value = d.name || '';
                document.getElementById('editEmail').value = d.email || '';
                document.getElementById('editPhone').value = d.phone || '';
                document.getElementById('editDesignation').value = d.designation || '';
                bsModal.show();
            }

            if (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    const saveBtn = document.getElementById('editUserSaveBtn');
                    const orig = saveBtn.innerHTML;
                    saveBtn.disabled = true;
                    saveBtn.innerHTML = '<i class="ri-loader-4-line"></i> Saving...';
                    errBox.classList.add('d-none');

                    const userId = document.getElementById('editUserId').value;
                    const fd = new FormData();
                    fd.append('name', document.getElementById('editName').value.trim());
                    fd.append('email', document.getElementById('editEmail').value.trim());
                    fd.append('phone', document.getElementById('editPhone').value.trim());
                    fd.append('designation', document.getElementById('editDesignation').value.trim());
                    fd.append('_token', csrfToken);
                    fd.append('_method', 'PUT');

                    fetch('/users/' + userId, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                        body: fd
                    })
                    .then(r => r.json())
                    .then(result => {
                        saveBtn.disabled = false;
                        saveBtn.innerHTML = orig;
                        if (result.success) {
                            const row = usersTable.getRow(Number(userId));
                            if (row) {
                                row.update({
                                    name: result.user.name,
                                    email: result.user.email,
                                    phone: result.user.phone || '',
                                    designation: result.user.designation || '',
                                    rr_role: result.user.rr_role || '',
                                    rr_has_portfolio: result.user.has_rr_portfolio === true,
                                    resources: result.user.resources || '',
                                    training: result.user.training || '',
                                });
                            }
                            bsModal.hide();
                            showToast(result.message || 'User updated successfully.', 'success');
                        } else {
                            let msg = result.message || 'Update failed';
                            if (result.errors) msg = Object.values(result.errors).flat().join(' ');
                            errBox.textContent = msg;
                            errBox.classList.remove('d-none');
                        }
                    })
                    .catch(() => {
                        saveBtn.disabled = false;
                        saveBtn.innerHTML = orig;
                        errBox.textContent = 'An error occurred while updating user.';
                        errBox.classList.remove('d-none');
                    });
                });
            }

            function deactivateUser(d, row) {
                if (!confirm('Deactivate this user? They will not be able to sign in until you use Recover.')) return;
                fetch('/users/' + d.id, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(r => r.json())
                .then(result => {
                    if (result.success) {
                        showToast(result.message, 'success');
                        row.update({ is_active: false });
                        if (window.applyUsersFilter) window.applyUsersFilter();
                    } else {
                        showToast(result.message || 'Delete failed', 'error');
                    }
                })
                .catch(() => showToast('Delete failed', 'error'));
            }

            // ---- Resume modal (HR only) ----
            const resumeModalEl = document.getElementById('resumeModal');
            const bsResumeModal = (resumeModalEl && window.bootstrap) ? new bootstrap.Modal(resumeModalEl) : null;
            let resumeTargetRow = null;

            function openResumeModal(d, row) {
                if (!bsResumeModal) return;
                resumeTargetRow = row;
                document.getElementById('resumeUserId').value = d.id;
                document.getElementById('resumeUserName').textContent = d.name || '';
                const fileInput = document.getElementById('resumeFile');
                if (fileInput) fileInput.value = '';
                const errEl = document.getElementById('resumeError');
                if (errEl) { errEl.classList.add('d-none'); errEl.textContent = ''; }
                const current = document.getElementById('resumeCurrent');
                const delBtn = document.getElementById('resumeDeleteBtn');
                if (d.has_resume) {
                    current.innerHTML = '<a href="' + d.resume_url + '" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success"><i class="ri-file-download-line me-1"></i>' + esc(d.resume_name || 'View resume') + '</a>';
                    if (delBtn) delBtn.classList.remove('d-none');
                } else {
                    current.innerHTML = '<span class="text-muted">No resume uploaded yet.</span>';
                    if (delBtn) delBtn.classList.add('d-none');
                }
                bsResumeModal.show();
            }

            const resumeUploadBtn = document.getElementById('resumeUploadBtn');
            if (resumeUploadBtn) {
                resumeUploadBtn.addEventListener('click', function () {
                    const fileInput = document.getElementById('resumeFile');
                    const errEl = document.getElementById('resumeError');
                    if (!fileInput.files || !fileInput.files[0]) {
                        errEl.textContent = 'Please choose a file to upload.';
                        errEl.classList.remove('d-none');
                        return;
                    }
                    const userId = document.getElementById('resumeUserId').value;
                    const orig = resumeUploadBtn.innerHTML;
                    resumeUploadBtn.disabled = true;
                    resumeUploadBtn.innerHTML = '<i class="ri-loader-4-line"></i> Saving...';
                    errEl.classList.add('d-none');

                    const fd = new FormData();
                    fd.append('resume', fileInput.files[0]);
                    fd.append('_token', csrfToken);

                    fetch('/users/' + userId + '/resume', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                        body: fd
                    })
                    .then(r => r.json())
                    .then(result => {
                        resumeUploadBtn.disabled = false;
                        resumeUploadBtn.innerHTML = orig;
                        if (result.success) {
                            if (resumeTargetRow) {
                                resumeTargetRow.update({ has_resume: true, resume_name: result.resume_name, resume_url: result.resume_url });
                            }
                            bsResumeModal.hide();
                            showToast(result.message || 'Resume uploaded.', 'success');
                        } else {
                            let msg = result.message || 'Upload failed';
                            if (result.errors) msg = Object.values(result.errors).flat().join(' ');
                            errEl.textContent = msg;
                            errEl.classList.remove('d-none');
                        }
                    })
                    .catch(() => {
                        resumeUploadBtn.disabled = false;
                        resumeUploadBtn.innerHTML = orig;
                        errEl.textContent = 'An error occurred while uploading.';
                        errEl.classList.remove('d-none');
                    });
                });
            }

            const resumeDeleteBtn = document.getElementById('resumeDeleteBtn');
            if (resumeDeleteBtn) {
                resumeDeleteBtn.addEventListener('click', function () {
                    if (!confirm('Remove this resume file?')) return;
                    const userId = document.getElementById('resumeUserId').value;
                    const orig = resumeDeleteBtn.innerHTML;
                    resumeDeleteBtn.disabled = true;
                    resumeDeleteBtn.innerHTML = '<i class="ri-loader-4-line"></i>';

                    fetch('/users/' + userId + '/resume', {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(r => r.json())
                    .then(result => {
                        resumeDeleteBtn.disabled = false;
                        resumeDeleteBtn.innerHTML = orig;
                        if (result.success) {
                            if (resumeTargetRow) {
                                resumeTargetRow.update({ has_resume: false, resume_name: '' });
                            }
                            bsResumeModal.hide();
                            showToast(result.message || 'Resume removed.', 'success');
                        } else {
                            showToast(result.message || 'Failed to remove', 'error');
                        }
                    })
                    .catch(() => {
                        resumeDeleteBtn.disabled = false;
                        resumeDeleteBtn.innerHTML = orig;
                        showToast('Failed to remove resume', 'error');
                    });
                });
            }

            // ---- Bank details modal (editable; update-only, never deletes) ----
            const bankModalEl = document.getElementById('bankModal');
            const bsBankModal = (bankModalEl && window.bootstrap) ? new bootstrap.Modal(bankModalEl) : null;
            let bankTargetRow = null;

            function openBankModal(d, row) {
                if (!bsBankModal) return;
                bankTargetRow = row || null;
                document.getElementById('bankUserId').value = d.id;
                document.getElementById('bankUserName').textContent = d.name || '';
                document.getElementById('bankInput1').value = d.bank_1 || '';
                document.getElementById('bankInput2').value = d.bank_2 || '';
                document.getElementById('bankInputUpi').value = d.upi_id || '';
                const err = document.getElementById('bankError');
                if (err) { err.classList.add('d-none'); err.textContent = ''; }
                bsBankModal.show();
            }

            const bankSaveBtn = document.getElementById('bankSaveBtn');
            if (bankSaveBtn) {
                bankSaveBtn.addEventListener('click', function () {
                    const userId = document.getElementById('bankUserId').value;
                    const err = document.getElementById('bankError');
                    const orig = bankSaveBtn.innerHTML;
                    bankSaveBtn.disabled = true;
                    bankSaveBtn.innerHTML = '<i class="ri-loader-4-line"></i> Saving...';
                    if (err) err.classList.add('d-none');

                    const fd = new FormData();
                    fd.append('bank_1', document.getElementById('bankInput1').value.trim());
                    fd.append('bank_2', document.getElementById('bankInput2').value.trim());
                    fd.append('upi_id', document.getElementById('bankInputUpi').value.trim());
                    fd.append('_token', csrfToken);

                    fetch('/users/' + userId + '/bank', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                        body: fd
                    })
                    .then(r => r.json())
                    .then(result => {
                        bankSaveBtn.disabled = false;
                        bankSaveBtn.innerHTML = orig;
                        if (result.success) {
                            if (bankTargetRow) {
                                bankTargetRow.update({ bank_1: result.bank_1, bank_2: result.bank_2, upi_id: result.upi_id });
                            }
                            bsBankModal.hide();
                            showToast(result.message || 'Bank details updated.', 'success');
                        } else {
                            let m = result.message || 'Failed to update';
                            if (result.errors) m = Object.values(result.errors).flat().join(' ');
                            if (err) { err.textContent = m; err.classList.remove('d-none'); }
                        }
                    })
                    .catch(() => {
                        bankSaveBtn.disabled = false;
                        bankSaveBtn.innerHTML = orig;
                        if (err) { err.textContent = 'An error occurred while saving.'; err.classList.remove('d-none'); }
                    });
                });
            }

            // ---- Docs modal ----
            const docsModalEl = document.getElementById('docsModal');
            const bsDocsModal = (docsModalEl && window.bootstrap) ? new bootstrap.Modal(docsModalEl) : null;
            let docsTargetRow = null;

            function renderDocsList(docs) {
                const list = document.getElementById('docsList');
                if (!list) return;
                if (!docs || !docs.length) {
                    list.innerHTML = '<li class="list-group-item text-muted">No documents yet.</li>';
                    return;
                }
                list.innerHTML = docs.map(function (doc) {
                    const icon = doc.type === 'file' ? 'ri-file-3-line' : 'ri-link';
                    const del = canEditResume
                        ? '<button type="button" class="btn btn-sm btn-outline-danger doc-del-btn" data-doc-id="' + doc.id + '"><i class="ri-delete-bin-line"></i></button>'
                        : '';
                    return '<li class="list-group-item d-flex justify-content-between align-items-center gap-2">' +
                        '<a href="' + doc.url + '" target="_blank" rel="noopener" class="text-decoration-none text-break">' +
                        '<i class="' + icon + ' me-2"></i>' + esc(doc.name || (doc.type === 'file' ? 'File' : 'Link')) + '</a>' +
                        del + '</li>';
                }).join('');

                list.querySelectorAll('.doc-del-btn').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        deleteDoc(btn.getAttribute('data-doc-id'), btn);
                    });
                });
            }

            function openDocsModal(d, row) {
                if (!bsDocsModal) return;
                docsTargetRow = row;
                document.getElementById('docsUserId').value = d.id;
                document.getElementById('docsUserName').textContent = d.name || '';
                const errEl = document.getElementById('docsError');
                if (errEl) { errEl.classList.add('d-none'); errEl.textContent = ''; }
                resetDocRows();
                renderDocsList(d.docs || []);
                bsDocsModal.show();
            }

            function docRowTemplate() {
                return '<div class="doc-row row g-2 align-items-center mb-2">' +
                    '<div class="col-md-4"><input type="text" class="form-control form-control-sm doc-row-name" placeholder="File name (optional)"></div>' +
                    '<div class="col-md-4"><input type="file" class="form-control form-control-sm doc-row-file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.png,.jpg,.jpeg,.gif,.zip,.txt,.csv"></div>' +
                    '<div class="col-md-3"><input type="url" class="form-control form-control-sm doc-row-link" placeholder="https://link"></div>' +
                    '<div class="col-md-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger doc-row-remove" title="Remove"><i class="ri-close-line"></i></button></div>' +
                    '</div>';
            }

            function addDocRow() {
                const container = document.getElementById('docRows');
                if (!container) return;
                container.insertAdjacentHTML('beforeend', docRowTemplate());
                const row = container.lastElementChild;
                row.querySelector('.doc-row-remove').addEventListener('click', function () {
                    row.remove();
                    if (!document.querySelectorAll('#docRows .doc-row').length) addDocRow();
                });
            }

            function resetDocRows() {
                const container = document.getElementById('docRows');
                if (!container) return;
                container.innerHTML = '';
                addDocRow();
            }

            function applyDocsResult(result) {
                if (docsTargetRow) {
                    docsTargetRow.update({ has_docs: result.has_docs, docs: result.docs });
                }
                renderDocsList(result.docs || []);
            }

            function docsError(msg) {
                const errEl = document.getElementById('docsError');
                if (!errEl) return;
                errEl.textContent = msg;
                errEl.classList.remove('d-none');
            }

            function deleteDoc(docId, btn) {
                if (!confirm('Remove this document?')) return;
                const userId = document.getElementById('docsUserId').value;
                if (btn) { btn.disabled = true; btn.innerHTML = '<i class="ri-loader-4-line"></i>'; }
                fetch('/users/' + userId + '/docs/' + docId, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(r => r.json())
                .then(result => {
                    if (result.success) {
                        applyDocsResult(result);
                        showToast('Document removed.', 'success');
                    } else {
                        showToast(result.message || 'Failed to remove', 'error');
                    }
                })
                .catch(() => showToast('Failed to remove document', 'error'));
            }

            const docAddRowBtn = document.getElementById('docAddRowBtn');
            if (docAddRowBtn) {
                docAddRowBtn.addEventListener('click', addDocRow);
            }

            const docsSaveBtn = document.getElementById('docsSaveBtn');
            if (docsSaveBtn) {
                docsSaveBtn.addEventListener('click', function () {
                    const userId = document.getElementById('docsUserId').value;
                    const rows = Array.from(document.querySelectorAll('#docRows .doc-row'));
                    const fd = new FormData();
                    fd.append('_token', csrfToken);
                    let count = 0;

                    rows.forEach(function (row) {
                        const name = row.querySelector('.doc-row-name').value.trim();
                        const link = row.querySelector('.doc-row-link').value.trim();
                        const fileInput = row.querySelector('.doc-row-file');
                        const file = (fileInput.files && fileInput.files[0]) ? fileInput.files[0] : null;
                        if (!file && !link) return; // skip empty rows

                        fd.append('rows[' + count + '][name]', name);
                        fd.append('rows[' + count + '][link]', link);
                        if (file) fd.append('rows[' + count + '][file]', file);
                        count++;
                    });

                    if (count === 0) { docsError('Add at least one file or link.'); return; }

                    const orig = docsSaveBtn.innerHTML;
                    docsSaveBtn.disabled = true;
                    docsSaveBtn.innerHTML = '<i class="ri-loader-4-line"></i> Saving...';
                    document.getElementById('docsError').classList.add('d-none');

                    fetch('/users/' + userId + '/docs', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }, body: fd })
                    .then(r => r.json())
                    .then(result => {
                        docsSaveBtn.disabled = false;
                        docsSaveBtn.innerHTML = orig;
                        if (result.success) {
                            resetDocRows();
                            applyDocsResult(result);
                            showToast(result.message || 'Saved.', 'success');
                        } else {
                            let m = result.message || 'Failed to save';
                            if (result.errors) m = Object.values(result.errors).flat().join(' ');
                            docsError(m);
                        }
                    })
                    .catch(() => { docsSaveBtn.disabled = false; docsSaveBtn.innerHTML = orig; docsError('An error occurred while saving.'); });
                });
            }
        });
    </script>
            </div>
            <!-- End Users Tab -->
        </div>
        <!-- End Tab Content -->
    </div>
@endsection
