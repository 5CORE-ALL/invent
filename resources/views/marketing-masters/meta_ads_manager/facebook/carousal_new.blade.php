@extends('layouts.vertical', ['title' => 'FB GRP CAROUSAL ADS', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    @include('marketing-masters.meta_ads_manager.partials.styles')
@endsection
@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'FB GRP CAROUSAL ADS',
        'sub_title' => 'FB GRP CAROUSAL ADS',
    ])
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-3">
                    <div class="mb-4">
                        <!-- Title -->
                        <h4 class="fw-bold text-primary mb-3 d-flex align-items-center">
                            <i class="fa-solid fa-chart-line me-2"></i>
                            FB GRP CAROUSAL ADS
                        </h4>

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
                            <div class="col-md-6">
                                <div class="d-flex gap-2">
                                    <div class="input-group">
                                        <input type="text" id="global-search" class="form-control form-control-md"
                                            placeholder="Search SKU or Group...">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
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
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        var dataUrl = "{{ route('meta.ads.facebook.carousal.new.data') }}";
    </script>
    @include('marketing-masters.meta_ads_manager.partials.table-script-product-sku')
@endsection

