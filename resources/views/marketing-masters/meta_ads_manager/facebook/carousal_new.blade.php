@extends('layouts.vertical', ['title' => 'Social Media Ads', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    @include('marketing-masters.meta_ads_manager.partials.styles')
@endsection
@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Social Media Ads',
        'sub_title' => 'Social Media Ads',
    ])
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-3">
                    <div class="mb-4">
                        <!-- Title and New Campaign Button -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="fw-bold text-primary mb-0 d-flex align-items-center">
                                <i class="fa-solid fa-chart-line me-2"></i>
                                Social Media Ads
                            </h4>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newCampaignModal">
                                <i class="fa fa-plus-circle me-2"></i>New Campaign
                            </button>
                        </div>

                        <!-- Filters Row -->
                        <div class="row g-3 mb-3">
                            <!-- Stats -->
                            <div class="col-md-12">
                                <div class="d-flex gap-2 justify-content-end">
                                    <button class="btn btn-success btn-md">
                                        <i class="fa fa-bullhorn me-1"></i>
                                        Total SKUs: <span id="total-campaigns" class="fw-bold ms-1 fs-4">0</span>
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
                            <div class="col-md-4">
                                <div class="d-flex gap-2">
                                    <div class="input-group">
                                        <input type="text" id="global-search" class="form-control form-control-md"
                                            placeholder="Search SKU or Group...">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex gap-2">
                                    <select id="group-filter" class="form-select form-select-md">
                                        <option value="">All Groups</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex gap-2 justify-content-end">
                                    <select id="inv-filter" class="form-select form-select-md" style="max-width: 200px;">
                                        <option value="">All INV</option>
                                        <option value="0">INV = 0</option>
                                        <option value=">0">INV > 0</option>
                                        <option value="1-10">INV 1-10</option>
                                        <option value="11-50">INV 11-50</option>
                                        <option value="51-100">INV 51-100</option>
                                        <option value=">100">INV > 100</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Table Section -->
                    <div id="budget-under-table"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- New Campaign Modal -->
    <div class="modal fade" id="newCampaignModal" tabindex="-1" aria-labelledby="newCampaignModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="newCampaignModalLabel">
                        <i class="fa fa-plus-circle me-2"></i>+ New Campaign
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="campaign-form">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="form-ad-type" class="form-label">AD TYPE</label>
                                <input type="text" class="form-control" id="form-ad-type" name="ad_type" placeholder="Enter AD TYPE">
                            </div>
                            <div class="col-md-6">
                                <label for="form-group" class="form-label">Group <span class="text-danger">*</span></label>
                                <select class="form-select" id="form-group" name="group" required>
                                    <option value="">Select Group...</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="form-l-page" class="form-label">L Page</label>
                                <input type="text" class="form-control" id="form-l-page" name="l_page" placeholder="Enter L Page">
                            </div>
                            <div class="col-md-6">
                                <label for="form-purpose" class="form-label">Purpose</label>
                                <input type="text" class="form-control" id="form-purpose" name="purpose" placeholder="Enter Purpose">
                            </div>
                            <div class="col-md-6">
                                <label for="form-audience" class="form-label">Audience</label>
                                <input type="text" class="form-control" id="form-audience" name="audience" placeholder="Enter Audience">
                            </div>
                            <div class="col-md-6">
                                <label for="form-campaign" class="form-label">Campaign</label>
                                <input type="text" class="form-control" id="form-campaign" name="campaign" placeholder="Enter Campaign Name">
                            </div>
                            <div class="col-md-6">
                                <label for="form-campaign-id" class="form-label">Campaign ID</label>
                                <input type="text" class="form-control" id="form-campaign-id" name="campaign_id" placeholder="Enter Campaign ID">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fa fa-times me-2"></i>Close
                    </button>
                    <button type="button" class="btn btn-primary" id="save-campaign-btn">
                        <i class="fa fa-save me-2"></i>Save Campaign
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        var dataUrl = "{{ route('meta.ads.facebook.carousal.new.data') }}";
    </script>
    @include('marketing-masters.meta_ads_manager.partials.table-script-product-sku')
@endsection

