@php
    $isAdmin = auth()->user()->email === 'president@5core.com';
    $isManager = auth()->user()->is5CoreMember();
    $isEmployee = !$isAdmin && !$isManager;
@endphp

<meta name="user-id" content="{{ auth()->id() }}">
<div class="performance-management-container" data-user-id="{{ auth()->id() }}">
    <!-- Inner Tabs for Performance Management -->
    <ul class="nav nav-tabs mb-4" id="performanceTabs" role="tablist">
        @if($isAdmin)
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="checklist-tab" data-bs-toggle="tab" data-bs-target="#checklist-content" type="button" role="tab">
                    <i class="ri-checkbox-line me-2"></i>Checklist Management
                </button>
            </li>
        @endif
        @if($isAdmin || $isManager)
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ !$isAdmin ? 'active' : '' }}" id="review-tab" data-bs-toggle="tab" data-bs-target="#review-content" type="button" role="tab">
                    <i class="ri-file-list-3-line me-2"></i>Create Review
                </button>
            </li>
        @endif
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $isEmployee ? 'active' : '' }}" id="dashboard-tab" data-bs-toggle="tab" data-bs-target="#dashboard-content" type="button" role="tab">
                <i class="ri-dashboard-line me-2"></i>Performance Dashboard
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history-content" type="button" role="tab">
                <i class="ri-history-line me-2"></i>Review History
            </button>
        </li>
    </ul>

    <div class="tab-content" id="performanceTabContent">
        <!-- Checklist Management Tab (Admin Only) -->
        @if($isAdmin)
        <div class="tab-pane fade show active" id="checklist-content" role="tabpanel">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="ri-settings-3-line me-2"></i>Checklist Management</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Select Designation</label>
                            <select id="checklist-designation-select" class="form-select">
                                <option value="">-- Select Designation --</option>
                            </select>
                        </div>
                        <div class="col-md-6 text-end">
                            <button class="btn btn-success" id="add-designation-btn">
                                <i class="ri-add-line me-2"></i>Add Designation
                            </button>
                        </div>
                    </div>

                    <div id="checklist-container" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6>Checklist Structure</h6>
                            <div class="btn-group" role="group">
                                <button class="btn btn-success btn-sm" id="export-checklist-btn" title="Export to CSV">
                                    <i class="ri-download-line me-1"></i>Export CSV
                                </button>
                                <button class="btn btn-info btn-sm" id="import-checklist-btn" title="Import from CSV">
                                    <i class="ri-upload-line me-1"></i>Import CSV
                                </button>
                                <button class="btn btn-primary btn-sm" id="add-category-btn">
                                    <i class="ri-add-line me-1"></i>Add Category
                                </button>
                            </div>
                        </div>
                        <div id="categories-container"></div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Create Review Tab (Admin/Manager) -->
        @if($isAdmin || $isManager)
        <div class="tab-pane fade {{ !$isAdmin ? 'show active' : '' }}" id="review-content" role="tabpanel">
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="ri-file-edit-line me-2"></i>Create Performance Review</h5>
                </div>
                <div class="card-body">
                    <form id="review-form">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Employee <span class="text-danger">*</span></label>
                                <select id="review-employee-select" class="form-select" required>
                                    <option value="">-- Select Employee --</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Designation <span class="text-danger">*</span></label>
                                <select id="review-designation-select" class="form-select" required>
                                    <option value="">-- Select Designation --</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Review Period <span class="text-danger">*</span></label>
                                <select id="review-period-select" class="form-select" required>
                                    <option value="Weekly">Weekly</option>
                                    <option value="Monthly" selected>Monthly</option>
                                    <option value="Custom">Custom</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Review Date <span class="text-danger">*</span></label>
                                <input type="date" id="review-date" class="form-control" value="{{ date('Y-m-d') }}" required>
                            </div>
                            <div class="col-md-4" id="period-start-container" style="display: none;">
                                <label class="form-label fw-bold">Period Start Date</label>
                                <input type="date" id="period-start-date" class="form-control">
                            </div>
                            <div class="col-md-4" id="period-end-container" style="display: none;">
                                <label class="form-label fw-bold">Period End Date</label>
                                <input type="date" id="period-end-date" class="form-control">
                            </div>
                        </div>

                        <div id="review-checklist-container" style="display: none;">
                            <hr>
                            <h6 class="mb-3">Performance Checklist</h6>
                            <div id="review-checklist-items"></div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Overall Feedback</label>
                                <textarea id="overall-feedback" class="form-control" rows="3" placeholder="Enter overall feedback..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="ri-save-line me-2"></i>Submit Review
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endif

        <!-- Performance Dashboard Tab -->
        <div class="tab-pane fade {{ $isEmployee ? 'show active' : '' }}" id="dashboard-content" role="tabpanel">
            @if($isAdmin || $isManager)
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Select Employee to View Performance</label>
                    <select id="dashboard-employee-select" class="form-select">
                        <option value="">-- Select Employee --</option>
                    </select>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <div id="dashboard-current-user" class="text-muted">
                        <i class="ri-user-line me-1"></i>
                        <span id="dashboard-user-name">Loading...</span>
                    </div>
                </div>
            </div>
            @else
            <div class="mb-3">
                <h5 class="mb-0">
                    <i class="ri-user-line me-2"></i>
                    <span id="dashboard-user-name">{{ auth()->user()->name }}</span>
                </h5>
                <small class="text-muted">Your Performance Dashboard</small>
            </div>
            @endif
            
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card-performance bg-gradient-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="mb-1 small opacity-75">Current Score</p>
                                <h3 class="mb-0" id="current-score">-</h3>
                            </div>
                            <i class="ri-star-line" style="font-size: 2.5rem; opacity: 0.3;"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card-performance bg-gradient-info text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="mb-1 small opacity-75">Predicted Score</p>
                                <h3 class="mb-0" id="predicted-score">-</h3>
                            </div>
                            <i class="ri-line-chart-line" style="font-size: 2.5rem; opacity: 0.3;"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card-performance bg-gradient-success text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="mb-1 small opacity-75">Performance Level</p>
                                <h5 class="mb-0" id="performance-level">-</h5>
                            </div>
                            <i class="ri-trophy-line" style="font-size: 2.5rem; opacity: 0.3;"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card-performance bg-gradient-warning text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="mb-1 small opacity-75">Total Reviews</p>
                                <h3 class="mb-0" id="total-reviews">-</h3>
                            </div>
                            <i class="ri-file-list-3-line" style="font-size: 2.5rem; opacity: 0.3;"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="ri-line-chart-line me-2"></i>Performance Trend</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="trend-chart" height="100"></canvas>
                        </div>
                    </div>
                    <div class="card shadow-sm">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="ri-bar-chart-line me-2"></i>Category Breakdown</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="category-chart" height="100"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="ri-lightbulb-line me-2"></i>AI Feedback</h5>
                        </div>
                        <div class="card-body">
                            <div id="ai-feedback-content" class="text-muted">
                                <p class="mb-0">No feedback available yet.</p>
                            </div>
                        </div>
                    </div>
                    <div class="card shadow-sm">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="ri-alert-line me-2"></i>Focus Areas</h5>
                        </div>
                        <div class="card-body">
                            <div id="weak-areas-content">
                                <p class="text-muted mb-0">No weak areas identified.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Review History Tab -->
        <div class="tab-pane fade" id="history-content" role="tabpanel">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0"><i class="ri-history-line me-2"></i>Review History</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="reviews-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Period</th>
                                    <th>Employee</th>
                                    <th>Reviewer</th>
                                    <th>Score</th>
                                    <th>Level</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="reviews-tbody">
                                <tr>
                                    <td colspan="7" class="text-center text-muted">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
@include('pages.performance-modals')
{{-- Scripts are loaded from add-user.blade.php (or parent) @section('script') --}}
