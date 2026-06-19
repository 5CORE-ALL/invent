@extends('layouts.vertical', ['title' => 'TikTok Competitor Search', 'mode' => 'light'])

@section('css')
<link href="{{ URL::asset('build/assets/app-C9T8gcC6.css') }}" rel="stylesheet" type="text/type" />
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
    .search-container {
        background: #fff;
        border-radius: 8px;
        padding: 30px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .product-card {
        border: 1px solid #e0e0e0;
        border-radius: 6px;
        background: #fff;
        transition: all 0.2s ease;
        height: 100%;
        display: flex;
        flex-direction: column;
        position: relative;
        overflow: hidden;
    }

    .product-card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
        border-color: #ff0050;
    }

    .product-checkbox {
        position: absolute;
        top: 8px;
        left: 8px;
        width: 20px;
        height: 20px;
        cursor: pointer;
        z-index: 10;
    }

    .product-image-container {
        position: relative;
        padding: 10px;
        background: #fafafa;
        text-align: center;
        height: 140px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .product-image {
        max-width: 100%;
        max-height: 120px;
        object-fit: contain;
        cursor: pointer;
        transition: transform 0.2s ease;
    }

    .product-image:hover {
        transform: scale(1.05);
    }

    .product-body {
        padding: 10px;
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .product-title {
        font-weight: 500;
        color: #ff0050;
        margin-bottom: 8px;
        font-size: 13px;
        line-height: 1.3;
        height: 50px;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-decoration: none;
        cursor: pointer;
    }

    .product-title:hover {
        text-decoration: underline;
        color: #cc003f;
    }

    .product-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
        margin-bottom: 8px;
    }

    .meta-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: 600;
    }

    .price-badge {
        background: #FFE7EF;
        color: #ff0050;
        font-size: 14px;
        font-weight: 700;
        padding: 4px 10px;
    }

    .rating-badge {
        background: #FFF8E1;
        color: #F57C00;
        font-size: 11px;
        font-weight: 600;
    }

    .sold-badge {
        background: #E0F2F1;
        color: #00796B;
        font-size: 11px;
        font-weight: 600;
    }

    .pid-badge {
        background: #E3F2FD;
        color: #1565C0;
        font-family: monospace;
        font-size: 10px;
    }

    .search-history {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 15px;
    }

    .history-badge {
        background: #FFE7EF;
        color: #ff0050;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .history-badge:hover {
        background: #ff0050;
        color: white;
    }

    .loading-spinner {
        display: none;
        text-align: center;
        padding: 40px;
    }

    .spinner-border {
        width: 3rem;
        height: 3rem;
    }

    .stats-container {
        display: flex;
        gap: 20px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .stat-card {
        background: linear-gradient(135deg, #ff0050 0%, #00f2ea 100%);
        color: white;
        padding: 20px;
        border-radius: 8px;
        flex: 1;
        min-width: 180px;
        text-align: center;
    }

    .stat-value {
        font-size: 32px;
        font-weight: 700;
        margin-bottom: 5px;
    }

    .stat-label {
        font-size: 14px;
        opacity: 0.9;
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="javascript: void(0);">Repricer</a></li>
                        <li class="breadcrumb-item active">TikTok Competitor Search</li>
                    </ol>
                </div>
                <h4 class="page-title">TikTok Shop Competitor Discovery</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="search-container">
                <h5 class="mb-3">Search TikTok Shop Products</h5>
                <p class="text-muted mb-4">
                    Enter a keyword to discover TikTok Shop competitor listings and analyze their pricing, ratings,
                    sold counts, and ranking position.
                </p>

                <form id="searchForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="searchQuery" class="form-label">Search Query</label>
                                <input type="text" class="form-control" id="searchQuery" name="query"
                                    placeholder="e.g., wireless headphones, yoga mat, kitchen knife set" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="region" class="form-label">Region</label>
                                <select class="form-select" id="region" name="region">
                                    <option value="US" selected>United States</option>
                                    <option value="GB">United Kingdom</option>
                                    <option value="MY">Malaysia</option>
                                    <option value="PH">Philippines</option>
                                    <option value="TH">Thailand</option>
                                    <option value="VN">Vietnam</option>
                                    <option value="ID">Indonesia</option>
                                    <option value="SG">Singapore</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="maxProducts" class="form-label">Max Products</label>
                                <select class="form-select" id="maxProducts" name="max_products">
                                    <option value="50">50</option>
                                    <option value="100" selected>100</option>
                                    <option value="200">200</option>
                                    <option value="500">500</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg" style="background:#ff0050;border-color:#ff0050;">
                        <i class="mdi mdi-magnify me-2"></i>Search TikTok Shop
                    </button>
                    <button type="button" class="btn btn-secondary btn-lg" id="loadHistoryBtn">
                        <i class="mdi mdi-history me-2"></i>Load History
                    </button>
                    <button type="button" class="btn btn-outline-info btn-lg" id="viewRawResponseBtn"
                        title="Run a search first, then inspect the raw provider response.">
                        <i class="mdi mdi-code-json me-2"></i>View Raw Response
                    </button>
                </form>

                <div id="searchHistory" class="mt-4" style="display: none;">
                    <h6 class="text-muted">Recent Searches:</h6>
                    <div class="search-history" id="historyContainer"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="loading-spinner" id="loadingSpinner">
        <div class="spinner-border text-primary" role="status" style="color:#ff0050 !important;">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-3 text-muted">Searching TikTok Shop... this can take 30–90 seconds.</p>
    </div>

    <div class="row mt-4" id="resultsContainer" style="display: none;">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-4">Search Results</h5>

                    <div class="stats-container mb-4" id="statsContainer"></div>

                    <div class="card bg-light mb-4" id="sortFilterContainer" style="display: none;">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label fw-bold">Sort By</label>
                                    <select class="form-select" id="sortBy">
                                        <option value="position">TikTok Position (Default)</option>
                                        <option value="price_low_high">Price: Low to High</option>
                                        <option value="price_high_low">Price: High to Low</option>
                                        <option value="rating_high_low">Rating: High to Low</option>
                                        <option value="reviews_high_low">Reviews: Most First</option>
                                        <option value="sold_high_low">Sold Count: High to Low</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-bold">Min Price</label>
                                    <input type="number" class="form-control" id="minPrice" placeholder="$0.00" step="0.01" min="0">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-bold">Max Price</label>
                                    <input type="number" class="form-control" id="maxPrice" placeholder="$999.99" step="0.01" min="0">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-bold">Min Rating</label>
                                    <select class="form-select" id="minRating">
                                        <option value="">All Ratings</option>
                                        <option value="4">4★ &amp; Up</option>
                                        <option value="3">3★ &amp; Up</option>
                                        <option value="2">2★ &amp; Up</option>
                                    </select>
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <button type="button" class="btn btn-primary me-2" id="applyFiltersBtn"
                                        style="background:#ff0050;border-color:#ff0050;">
                                        <i class="mdi mdi-filter me-2"></i>Apply
                                    </button>
                                    <button type="button" class="btn btn-secondary" id="resetFiltersBtn">
                                        <i class="mdi mdi-refresh me-2"></i>Reset
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4" id="bulkActionsContainer" style="display: none;">
                        <div class="card bg-light">
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="selectAllCheckbox"
                                                style="width: 22px; height: 22px; cursor: pointer;">
                                            <label class="form-check-label ms-2 fw-bold" for="selectAllCheckbox">
                                                <i class="mdi mdi-checkbox-multiple-marked"></i> Select All Products
                                            </label>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="card h-100">
                                            <div class="card-header bg-info text-white py-2">
                                                <h6 class="mb-0"><i class="mdi mdi-lightning-bolt"></i> Quick Single SKU Assignment</h6>
                                            </div>
                                            <div class="card-body">
                                                <label for="bulkSkuSelect" class="form-label mb-2">Select one SKU:</label>
                                                <select class="form-select select2-bulk-sku" id="bulkSkuSelect">
                                                    <option value="">Choose a SKU...</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="card h-100">
                                            <div class="card-header bg-warning text-dark py-2">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <h6 class="mb-0"><i class="mdi mdi-checkbox-multiple-marked-outline"></i> Multiple SKU Assignment</h6>
                                                    <button type="button" class="btn btn-sm btn-light" id="toggleSkuList">
                                                        <i class="mdi mdi-chevron-down"></i> Show SKUs
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="card-body" id="skuCheckboxContainer" style="display: none;">
                                                <div class="mb-2 d-flex gap-2">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllSkus">
                                                        <i class="mdi mdi-checkbox-marked"></i> Select All
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAllSkus">
                                                        <i class="mdi mdi-checkbox-blank-outline"></i> Deselect All
                                                    </button>
                                                    <input type="text" class="form-control form-control-sm" id="skuSearchInput" placeholder="Search SKUs...">
                                                </div>
                                                <div id="skuCheckboxList" style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; padding: 10px;">
                                                    <p class="text-muted">Loading SKUs...</p>
                                                </div>
                                                <div class="mt-2">
                                                    <small class="text-muted">
                                                        <i class="mdi mdi-information"></i>
                                                        Selected: <span id="selectedSkuCount" class="fw-bold text-primary">0</span> SKU(s)
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12 text-center">
                                        <button type="button" class="btn btn-success btn-lg px-5" id="saveCompetitorsBtn">
                                            <i class="mdi mdi-content-save me-2"></i>Save Selected Competitors
                                        </button>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <i class="mdi mdi-information-outline"></i>
                                                Competitors will be assigned to all selected or checked SKUs
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3" id="resultsContent"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: View Raw Response -->
<div class="modal fade" id="rawResponseModal" tabindex="-1" aria-labelledby="rawResponseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rawResponseModalLabel">
                    <i class="mdi mdi-code-json me-2"></i>Raw Provider Response
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="p-3 bg-light border-bottom small">
                    <span id="rawResponseMeta"></span>
                </div>
                <pre id="rawResponsePre" class="p-4 mb-0 bg-dark text-light"
                    style="max-height: 70vh; overflow: auto; font-size: 12px;"></pre>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function () {
    let availableSkus = [];
    let currentSearchQuery = '';
    let currentRegion = 'US';

    function escapeHtml(value) {
        if (value == null) return '';
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function initializeSelect2() {
        try { $('.select2-bulk-sku').select2('destroy'); } catch (e) {}
        $('.select2-bulk-sku').select2({
            theme: 'bootstrap-5',
            placeholder: 'Select SKU for all checked products',
            allowClear: true,
            width: '100%'
        });
    }

    loadSkus();

    $('#searchForm').on('submit', function (e) {
        e.preventDefault();
        const query = $('#searchQuery').val().trim();
        const region = $('#region').val();
        const maxProducts = parseInt($('#maxProducts').val(), 10) || 100;
        if (!query) { alert('Please enter a search query'); return; }
        performSearch(query, region, maxProducts);
    });

    $('#loadHistoryBtn').on('click', loadSearchHistory);

    $('#viewRawResponseBtn').on('click', function () {
        const query = $('#searchQuery').val().trim() || currentSearchQuery;
        if (!query) { alert('Run a search first, then click View Raw Response.'); return; }
        $.get('/repricer/tiktok-search/raw-response', { query: query })
            .done(function (data) {
                if (data.success && data.response) {
                    $('#rawResponseMeta').text(
                        'Query: ' + (data.meta.search_query || '') +
                        ' | Region: ' + (data.meta.region || '') +
                        ' | Provider: ' + (data.meta.provider || '') +
                        ' | Items: ' + (data.meta.items_count ?? 'n/a') +
                        ' | Saved at: ' + (data.meta.created_at || '')
                    );
                    $('#rawResponsePre').text(JSON.stringify(data.response, null, 2));
                    new bootstrap.Modal(document.getElementById('rawResponseModal')).show();
                } else {
                    alert(data.message || 'No raw response found.');
                }
            })
            .fail(function (xhr) {
                const msg = xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'Could not load raw response. Run a search first.';
                alert(msg);
            });
    });

    $('#saveCompetitorsBtn').on('click', saveSelectedCompetitors);

    $('#selectAllCheckbox').on('change', function () {
        $('.competitor-checkbox').prop('checked', $(this).prop('checked'));
    });

    $('#toggleSkuList').on('click', function () {
        const $container = $('#skuCheckboxContainer');
        if ($container.is(':visible')) {
            $container.slideUp();
            $(this).html('<i class="mdi mdi-chevron-down"></i> Show SKUs');
        } else {
            $container.slideDown();
            $(this).html('<i class="mdi mdi-chevron-up"></i> Hide SKUs');
        }
    });

    $('#selectAllSkus').on('click', function () {
        $('.sku-checkbox:visible').prop('checked', true);
        updateSelectedSkuCount();
    });
    $('#deselectAllSkus').on('click', function () {
        $('.sku-checkbox').prop('checked', false);
        updateSelectedSkuCount();
    });
    $('#skuSearchInput').on('input', function () {
        const term = $(this).val().toLowerCase();
        $('.sku-checkbox').each(function () {
            const $cb = $(this);
            const sku = $cb.val().toLowerCase();
            const $parent = $cb.closest('.form-check');
            sku.includes(term) ? $parent.show() : $parent.hide();
        });
    });
    $(document).on('change', '.sku-checkbox', updateSelectedSkuCount);

    $('#applyFiltersBtn').on('click', applyFiltersAndSort);
    $('#resetFiltersBtn').on('click', function () {
        $('#sortBy').val('position');
        $('#minPrice').val('');
        $('#maxPrice').val('');
        $('#minRating').val('');
        applyFiltersAndSort();
    });

    function performSearch(query, region, maxProducts) {
        currentSearchQuery = query;
        currentRegion = region;

        $('#loadingSpinner').show();
        $('#resultsContainer').hide();

        $.ajax({
            url: '/repricer/tiktok-search/search',
            method: 'POST',
            data: {
                query: query,
                region: region,
                max_products: maxProducts,
                _token: '{{ csrf_token() }}'
            },
            success: function (response) {
                $('#loadingSpinner').hide();
                if (response.success) {
                    displayResults(response);
                    loadFilterOptions(query);
                } else {
                    let msg = response.message || 'Unknown error';
                    if (response.error) msg += '\n\nDetails: ' + response.error;
                    if (response.details) msg += '\n\nAPI Response: ' + JSON.stringify(response.details, null, 2);
                    alert('Error: ' + msg);
                    console.error('Apify TikTok Error:', response);
                }
            },
            error: function (xhr) {
                $('#loadingSpinner').hide();
                let msg = 'Failed to fetch search results';
                if (xhr.responseJSON) {
                    msg = xhr.responseJSON.message || msg;
                    if (xhr.responseJSON.error) msg += '\n\nError: ' + xhr.responseJSON.error;
                    if (xhr.responseJSON.details) msg += '\n\nDetails: ' + JSON.stringify(xhr.responseJSON.details, null, 2);
                }
                alert('Error: ' + msg);
                console.error('AJAX Error:', xhr.responseJSON || xhr.responseText);
            }
        });
    }

    function displayResults(response) {
        const results = response.data || [];
        const totalResults = response.total_results || 0;

        const validPrices = results.filter(r => r.price && parseFloat(r.price) > 0);
        const totalPrice = validPrices.reduce((s, r) => s + parseFloat(r.price), 0).toFixed(2);
        const avgPrice = validPrices.length ? (validPrices.reduce((s, r) => s + parseFloat(r.price), 0) / validPrices.length).toFixed(2) : 0;
        const validRatings = results.filter(r => r.rating && parseFloat(r.rating) > 0);
        const avgRating = validRatings.length ? (validRatings.reduce((s, r) => s + parseFloat(r.rating), 0) / validRatings.length).toFixed(2) : 0;
        const totalSold = results.reduce((s, r) => s + (parseInt(r.sold_count, 10) || 0), 0);

        $('#statsContainer').html(`
            <div class="stat-card">
                <div class="stat-value">${totalResults}</div>
                <div class="stat-label">Total Products Found</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #ff0050 0%, #ff4081 100%);">
                <div class="stat-value">$${totalPrice}</div>
                <div class="stat-label">Total Price Sum</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #00f2ea 0%, #007aff 100%);">
                <div class="stat-value">$${avgPrice}</div>
                <div class="stat-label">Average Price</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #f5a623 0%, #ff6f00 100%);">
                <div class="stat-value">${avgRating} ★</div>
                <div class="stat-label">Average Rating</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #2e7d32 0%, #00bfa5 100%);">
                <div class="stat-value">${totalSold.toLocaleString()}</div>
                <div class="stat-label">Total Sold (sum)</div>
            </div>
        `);

        let html = '';
        if (results.length === 0) {
            html = '<p class="text-muted">No results found for this query.</p>';
            $('#bulkActionsContainer').hide();
        } else {
            $('#bulkActionsContainer').show();

            let bulkSkuOptions = '<option value="">Select SKU for all checked rows</option>';
            availableSkus.forEach(function (sku) {
                if (!sku.toUpperCase().startsWith('PARENT')) {
                    bulkSkuOptions += `<option value="${escapeHtml(sku)}">${escapeHtml(sku)}</option>`;
                }
            });
            $('#bulkSkuSelect').html(bulkSkuOptions);
            $('#selectAllCheckbox').prop('checked', false);

            results.forEach(function (item) {
                const price = item.price ? `$${parseFloat(item.price).toFixed(2)}` : 'N/A';
                const rating = item.rating ? `${parseFloat(item.rating).toFixed(1)} ★` : '';
                const reviews = item.reviews ? `(${Number(item.reviews).toLocaleString()})` : '';
                const sold = item.sold_count ? `${Number(item.sold_count).toLocaleString()} sold` : '';
                const image = item.image || 'https://via.placeholder.com/100';
                const productLink = item.product_link || '#';
                const priceValue = item.price || 0;

                html += `
                    <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 mb-3">
                        <div class="product-card">
                            <input type="checkbox" class="competitor-checkbox product-checkbox form-check-input"
                                data-product-id="${escapeHtml(item.product_id || '')}"
                                data-marketplace="${escapeHtml(item.marketplace || 'tiktok')}"
                                data-region="${escapeHtml(item.region || 'US')}"
                                data-title="${escapeHtml(item.title || '')}"
                                data-brand="${escapeHtml(item.brand_name || '')}"
                                data-seller="${escapeHtml(item.seller_name || '')}"
                                data-price="${priceValue}"
                                data-min-price="${item.min_price ?? ''}"
                                data-max-price="${item.max_price ?? ''}"
                                data-link="${escapeHtml(productLink)}"
                                data-image="${escapeHtml(item.image || '')}"
                                data-rating="${item.rating != null ? item.rating : ''}"
                                data-reviews="${item.reviews != null ? item.reviews : ''}"
                                data-sold-count="${item.sold_count != null ? item.sold_count : ''}">

                            <div class="product-image-container">
                                <img src="${image}" alt="${escapeHtml(item.title || 'Product')}"
                                    class="product-image"
                                    onclick="window.open('${escapeHtml(productLink)}', '_blank')">
                            </div>

                            <div class="product-body">
                                <a href="${escapeHtml(productLink)}" target="_blank" class="product-title text-decoration-none">
                                    ${escapeHtml(item.title || 'No title')}
                                </a>

                                <div class="product-meta">
                                    <span class="meta-badge price-badge">${price}</span>
                                    ${rating ? `<span class="meta-badge rating-badge">${rating} ${reviews}</span>` : ''}
                                    ${sold ? `<span class="meta-badge sold-badge">${sold}</span>` : ''}
                                </div>

                                <div class="product-meta">
                                    <span class="meta-badge pid-badge">${escapeHtml(item.product_id || '')}</span>
                                    ${item.seller_name ? `<span class="meta-badge" style="background:#F3E5F5;color:#6A1B9A;">${escapeHtml(item.seller_name)}</span>` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
        }

        $('#resultsContent').html(html);
        $('#resultsContainer').show();

        setTimeout(initializeSelect2, 100);
    }

    function loadSearchHistory() {
        $.ajax({
            url: '/repricer/tiktok-search/history',
            method: 'GET',
            success: function (response) {
                if (response.success && response.data.length > 0) {
                    let html = '';
                    response.data.forEach(function (q) {
                        html += `<span class="history-badge" onclick="loadHistoryQuery('${q.replace(/'/g, "\\'")}')">${escapeHtml(q)}</span>`;
                    });
                    $('#historyContainer').html(html);
                    $('#searchHistory').show();
                } else {
                    alert('No search history found');
                }
            },
            error: function () { alert('Failed to load search history'); }
        });
    }

    function loadSkus() {
        $.ajax({
            url: '/repricer/tiktok-search/skus',
            method: 'GET',
            success: function (response) {
                if (response.success) {
                    availableSkus = response.data || [];
                    populateSkuCheckboxes();
                }
            },
            error: function () { console.error('Failed to load SKUs'); }
        });
    }

    function populateSkuCheckboxes() {
        let html = '';
        const filteredSkus = availableSkus.filter(s => !s.toUpperCase().startsWith('PARENT'));

        if (filteredSkus.length === 0) {
            html = '<p class="text-muted">No SKUs available</p>';
        } else {
            filteredSkus.forEach(function (sku) {
                const safeSku = escapeHtml(sku);
                const skuId = 'sku-' + sku.replace(/[^a-zA-Z0-9]/g, '_');
                html += `
                    <div class="form-check mb-2">
                        <input class="form-check-input sku-checkbox" type="checkbox" value="${safeSku}" id="${skuId}">
                        <label class="form-check-label" for="${skuId}" style="cursor: pointer;">${safeSku}</label>
                    </div>
                `;
            });
        }
        $('#skuCheckboxList').html(html);
        updateSelectedSkuCount();
    }

    function updateSelectedSkuCount() {
        $('#selectedSkuCount').text($('.sku-checkbox:checked').length);
    }

    function saveSelectedCompetitors() {
        const bulkSku = $('#bulkSkuSelect').val();
        const checkedSkus = $('.sku-checkbox:checked').map(function () { return $(this).val(); }).get();

        let selectedSkus = [];
        if (bulkSku) selectedSkus.push(bulkSku);
        checkedSkus.forEach(s => { if (!selectedSkus.includes(s)) selectedSkus.push(s); });

        if (selectedSkus.length === 0) {
            alert('Please select at least one SKU (dropdown or checkboxes).');
            return;
        }

        const checkedCompetitors = $('.competitor-checkbox:checked');
        if (checkedCompetitors.length === 0) {
            alert('Please select at least one competitor product.');
            return;
        }

        const competitors = [];
        checkedCompetitors.each(function () {
            const cb = $(this);
            const payload = {
                product_id: String(cb.data('product-id') || ''),
                marketplace: cb.data('marketplace') || 'tiktok',
                region: cb.data('region') || 'US',
                product_title: cb.data('title') || null,
                brand_name: cb.data('brand') || null,
                seller_name: cb.data('seller') || null,
                product_link: cb.data('link') || null,
                image: cb.data('image') || null,
                price: parseFloat(cb.data('price')) || 0,
                min_price: cb.attr('data-min-price') !== '' && cb.attr('data-min-price') != null ? parseFloat(cb.attr('data-min-price')) : null,
                max_price: cb.attr('data-max-price') !== '' && cb.attr('data-max-price') != null ? parseFloat(cb.attr('data-max-price')) : null,
                rating: cb.attr('data-rating') !== '' && cb.attr('data-rating') != null ? parseFloat(cb.attr('data-rating')) : null,
                reviews: cb.attr('data-reviews') !== '' && cb.attr('data-reviews') != null ? parseInt(cb.attr('data-reviews'), 10) : null,
                sold_count: cb.attr('data-sold-count') !== '' && cb.attr('data-sold-count') != null ? parseInt(cb.attr('data-sold-count'), 10) : null
            };
            selectedSkus.forEach(function (sku) {
                competitors.push(Object.assign({}, payload, { sku: String(sku).trim() }));
            });
        });

        const totalMappings = competitors.length;
        const competitorCount = checkedCompetitors.length;
        const skuCount = selectedSkus.length;

        if (!confirm(
            `You are about to create ${totalMappings} competitor mapping(s):\n\n` +
            `• ${competitorCount} competitor(s)\n` +
            `• ${skuCount} SKU(s)\n` +
            `• Total mappings: ${competitorCount} × ${skuCount} = ${totalMappings}\n\nContinue?`
        )) return;

        const $btn = $('#saveCompetitorsBtn');
        const originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin me-2"></i>Saving...');

        $.ajax({
            url: '/repricer/tiktok-search/store-competitors',
            method: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            data: JSON.stringify({ competitors: competitors, _token: '{{ csrf_token() }}' }),
            success: function (response) {
                if (response.success) {
                    alert(`✅ Success!\n\n${response.message}\n\nMappings created: ${totalMappings}`);
                    $('.competitor-checkbox:checked').prop('checked', false);
                    $('#selectAllCheckbox').prop('checked', false);
                    $('#bulkSkuSelect').val('').trigger('change');
                    $('.sku-checkbox').prop('checked', false);
                    updateSelectedSkuCount();
                } else {
                    alert('Error: ' + (response.message || 'Failed to save competitors'));
                }
            },
            error: function (xhr) {
                let msg = 'Failed to save competitors';
                if (xhr.responseJSON) {
                    if (xhr.responseJSON.errors) {
                        msg = 'Validation failed:\n' + Object.values(xhr.responseJSON.errors).flat().join('\n');
                    } else if (xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    if (xhr.responseJSON.error) msg += '\n\n' + xhr.responseJSON.error;
                }
                alert('Error: ' + msg);
                console.error('Save Error:', xhr.responseJSON || xhr.responseText);
            },
            complete: function () { $btn.prop('disabled', false).html(originalHtml); }
        });
    }

    function applyFiltersAndSort() {
        if (!currentSearchQuery) { alert('Please perform a search first'); return; }

        const sortBy = $('#sortBy').val();
        const minPrice = $('#minPrice').val();
        const maxPrice = $('#maxPrice').val();
        const minRating = $('#minRating').val();

        $('#loadingSpinner').show();

        $.ajax({
            url: '/repricer/tiktok-search/results',
            method: 'GET',
            data: {
                query: currentSearchQuery,
                region: currentRegion,
                sort_by: sortBy,
                min_price: minPrice,
                max_price: maxPrice,
                min_rating: minRating
            },
            success: function (response) {
                $('#loadingSpinner').hide();
                if (response.success) displayResults(response);
                else alert('Error: ' + (response.message || 'Failed to apply filters'));
            },
            error: function (xhr) {
                $('#loadingSpinner').hide();
                alert('Error: Failed to apply filters');
                console.error('Filter Error:', xhr.responseJSON || xhr.responseText);
            }
        });
    }

    function loadFilterOptions(query) {
        $.ajax({
            url: '/repricer/tiktok-search/filter-options',
            method: 'GET',
            data: { query: query },
            success: function (response) {
                if (response.success && response.data) $('#sortFilterContainer').show();
            },
            error: function () { console.error('Failed to load filter options'); }
        });
    }

    window.loadHistoryQuery = function (query) {
        $('#searchQuery').val(query);
        performSearch(query, $('#region').val(), parseInt($('#maxProducts').val(), 10) || 100);
    };
});
</script>
@endsection
