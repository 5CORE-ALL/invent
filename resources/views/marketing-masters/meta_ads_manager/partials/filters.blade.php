<div class="mb-4">
    <!-- Title -->
    <h4 class="fw-bold text-primary mb-3 d-flex align-items-center">
        <i class="fa-solid fa-chart-line me-2"></i>
        {{ $adType ?? 'META' }} ADS
        @if(isset($latestUpdatedAt))
            <small class="text-muted ms-3" style="font-size: 0.75rem;">
                Last Updated: {{ $latestUpdatedAt }}
            </small>
        @endif
    </h4>

    <!-- Filters Row -->
    <div class="row g-3 mb-3">
        <!-- Filters -->
        <div class="col-md-6">
            <div class="d-flex gap-2">
                <select id="status-filter" class="form-select form-select-md">
                    <option value="">All Status</option>
                    <option value="ACTIVE">Active</option>
                    <option value="INACTIVE">Inactive</option>
                    <option value="NOT_DELIVERING">Not Delivering</option>
                </select>
            </div>
        </div>

        <!-- Stats -->
        <div class="col-md-6">
            <div class="d-flex gap-2 justify-content-end">
                <button type="button" class="btn btn-sm btn-success" id="sync-btn">
                    <i class="fa fa-sync me-1"></i>Sync from Google Sheets
                </button>
                <button class="btn btn-success btn-md">
                    <i class="fa fa-bullhorn me-1"></i>
                    Total Ads: <span id="total-campaigns" class="fw-bold ms-1 fs-4">0</span>
                </button>
                <button class="btn btn-primary btn-md">
                    <i class="fa fa-percent me-1"></i>
                    Filtered: <span id="percentage-campaigns" class="fw-bold ms-1 fs-4">0%</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Search and Controls Row -->
    <div class="row g-3">
        <div class="col-md-6">
            <div class="d-flex gap-2">
                <div class="input-group">
                    <input type="text" id="global-search" class="form-control form-control-md"
                        placeholder="Search campaign...">
                </div>
            </div>
        </div>
    </div>
</div>
