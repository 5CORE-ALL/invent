@extends('layouts.vertical', ['title' => 'Google Competitor Search', 'mode' => 'light'])

@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
    .search-container { background: #fff; border-radius: 8px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    .product-card { border: 1px solid #e0e0e0; border-radius: 6px; background: #fff; transition: all 0.2s ease; height: 100%; display: flex; flex-direction: column; position: relative; overflow: hidden; }
    .product-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); transform: translateY(-2px); border-color: #34a853; }
    .product-checkbox { position: absolute; top: 8px; left: 8px; width: 20px; height: 20px; cursor: pointer; z-index: 10; }
    .product-image-container { padding: 10px; background: #fafafa; text-align: center; height: 140px; display: flex; align-items: center; justify-content: center; }
    .product-image { max-width: 100%; max-height: 120px; object-fit: contain; cursor: pointer; }
    .product-body { padding: 10px; flex: 1; display: flex; flex-direction: column; }
    .product-title { font-weight: 500; color: #1a73e8; margin-bottom: 8px; font-size: 13px; line-height: 1.3; height: 50px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
    .meta-badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; margin-right: 4px; margin-bottom: 4px; }
    .price-badge { background: #E8F5E9; color: #2E7D32; font-size: 14px; font-weight: 700; }
    .source-badge { background: #E3F2FD; color: #1565C0; }
    .loading-spinner { display: none; text-align: center; padding: 40px; }
    .stat-card { display: inline-block; min-width: 160px; margin-right: 12px; margin-bottom: 12px; padding: 16px 20px; border-radius: 8px; background: linear-gradient(135deg, #34a853 0%, #0d652d 100%); color: #fff; }
    .stat-value { font-size: 24px; font-weight: 700; }
    .stat-label { font-size: 12px; opacity: 0.9; }
    .history-badge { display: inline-block; padding: 6px 12px; margin: 4px; background: #f1f3f4; border-radius: 20px; cursor: pointer; font-size: 13px; }
    .history-badge:hover { background: #e8f0fe; color: #1a73e8; }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <h4 class="page-title">Google Shopping Competitor Search</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="search-container">
                <form id="searchForm">
                    <div class="row">
                        <div class="col-md-8">
                            <label for="searchQuery" class="form-label">Search Query</label>
                            <input type="text" class="form-control" id="searchQuery" name="query"
                                   placeholder="e.g., Rockville RVP15W8 15 inch subwoofer" required
                                   value="{{ request('sku') ? e(request('sku')) : '' }}">
                        </div>
                        <div class="col-md-4">
                            <label for="maxPages" class="form-label">Search Pages</label>
                            <select class="form-select" id="maxPages">
                                <option value="1" selected>1 page (~40 items)</option>
                                <option value="2">2 pages (~80 items)</option>
                                <option value="3">3 pages (~120 items)</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="expandSellers" checked>
                        <label class="form-check-label" for="expandSellers">
                            Expand multi-seller offers (Walmart, eBay, Target, etc.)
                        </label>
                    </div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="deepExpand">
                        <label class="form-check-label" for="deepExpand">
                            Deep expand (slower) — more products and extra store pages
                        </label>
                    </div>
                    <button type="submit" class="btn btn-success btn-lg mt-3">
                        <i class="mdi mdi-magnify me-2"></i>Search Google Shopping
                    </button>
                    <button type="button" class="btn btn-secondary btn-lg mt-3" id="loadHistoryBtn">
                        <i class="mdi mdi-history me-2"></i>Load History
                    </button>
                </form>
                <div id="searchHistory" class="mt-4" style="display:none;">
                    <h6 class="text-muted">Recent Searches:</h6>
                    <div id="historyContainer"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="loading-spinner" id="loadingSpinner">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-3 text-muted">Searching Google Shopping… usually under 30 seconds (deep expand may take longer).</p>
    </div>

    <div class="row mt-4" id="resultsContainer" style="display:none;">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-4">Search Results</h5>
                    <div class="mb-4" id="statsContainer"></div>

                    <div class="card bg-light mb-4">
                        <div class="card-body">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label fw-bold">Sort By</label>
                                    <select class="form-select" id="sortBy">
                                        <option value="price_low_high">Price: Low to High</option>
                                        <option value="price_high_low">Price: High to Low</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-bold">Min Price</label>
                                    <input type="number" class="form-control" id="minPrice" step="0.01" min="0">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-bold">Max Price</label>
                                    <input type="number" class="form-control" id="maxPrice" step="0.01" min="0">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold">Source</label>
                                    <input type="text" class="form-control" id="sourceFilter" placeholder="e.g., Rockville Audio">
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-primary w-100" id="applyFiltersBtn">Apply</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card bg-light mb-4" id="bulkActionsContainer" style="display:none;">
                        <div class="card-body">
                            <div class="form-check mb-3">
                                <input type="checkbox" class="form-check-input" id="selectAllCheckbox" style="width:22px;height:22px;">
                                <label class="form-check-label ms-2 fw-bold" for="selectAllCheckbox">Select All Products</label>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="bulkSkuSelect" class="form-label">Quick SKU assignment</label>
                                    <select class="form-select select2-bulk-sku" id="bulkSkuSelect">
                                        <option value="">Choose a SKU...</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Multiple SKUs</label>
                                    <div id="skuCheckboxList" style="max-height:180px;overflow-y:auto;border:1px solid #ddd;border-radius:4px;padding:10px;">
                                        <p class="text-muted mb-0">Loading SKUs...</p>
                                    </div>
                                </div>
                                <div class="col-12 text-center">
                                    <button type="button" class="btn btn-success btn-lg" id="saveCompetitorsBtn">
                                        <i class="mdi mdi-content-save me-2"></i>Save Selected to Google LMP
                                    </button>
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
@endsection

@section('script')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(function() {
    let availableSkus = [];
    let currentSearchQuery = '';

    loadSkus();

    $('#searchForm').on('submit', function(e) {
        e.preventDefault();
        const query = $('#searchQuery').val().trim();
        if (!query) return alert('Please enter a search query');
        performSearch(query);
    });

    $('#loadHistoryBtn').on('click', loadSearchHistory);
    $('#saveCompetitorsBtn').on('click', saveSelectedCompetitors);
    $('#selectAllCheckbox').on('change', function() {
        $('.competitor-checkbox').prop('checked', $(this).prop('checked'));
    });
    $('#applyFiltersBtn').on('click', applyFiltersAndSort);

    function performSearch(query) {
        currentSearchQuery = query;
        $('#loadingSpinner').show();
        $('#resultsContainer').hide();

        $.ajax({
            url: '/repricer/google-search/search',
            method: 'POST',
            data: {
                query: query,
                max_pages: $('#maxPages').val(),
                expand_sellers: $('#expandSellers').is(':checked') ? 1 : 0,
                deep_expand: $('#deepExpand').is(':checked') ? 1 : 0,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                $('#loadingSpinner').hide();
                if (response.success) displayResults(response);
                else alert(response.message || 'Search failed');
            },
            error: function(xhr) {
                $('#loadingSpinner').hide();
                alert((xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Search failed');
            }
        });
    }

    function applyFiltersAndSort() {
        if (!currentSearchQuery) return alert('Perform a search first');
        $('#loadingSpinner').show();
        $.get('/repricer/google-search/results', {
            query: currentSearchQuery,
            sort_by: $('#sortBy').val(),
            min_price: $('#minPrice').val(),
            max_price: $('#maxPrice').val(),
            source: $('#sourceFilter').val()
        }).done(function(response) {
            $('#loadingSpinner').hide();
            if (response.success) displayResults(response);
        }).fail(function() {
            $('#loadingSpinner').hide();
            alert('Failed to filter results');
        });
    }

    function displayResults(response) {
        const results = response.data || [];
        const stats = response.price_stats || {};
        $('#statsContainer').html(
            '<div class="stat-card"><div class="stat-value">' + (response.total_results || 0) + '</div><div class="stat-label">Results</div></div>' +
            '<div class="stat-card" style="background:linear-gradient(135deg,#4285f4 0%,#1a73e8 100%);"><div class="stat-value">$' + parseFloat(stats.min_price || 0).toFixed(2) + '</div><div class="stat-label">Lowest</div></div>' +
            '<div class="stat-card" style="background:linear-gradient(135deg,#fbbc04 0%,#f29900 100%);"><div class="stat-value">$' + parseFloat(stats.avg_price || 0).toFixed(2) + '</div><div class="stat-label">Average</div></div>' +
            (response.unique_sources && response.unique_sources.length ? '<div class="mt-2"><small class="text-muted">Sources: ' + response.unique_sources.slice(0, 15).join(', ') + (response.unique_sources.length > 15 ? '…' : '') + '</small></div>' : '')
        );

        if (!results.length) {
            $('#resultsContent').html('<p class="text-muted">No results found.</p>');
            $('#bulkActionsContainer').hide();
        } else {
            $('#bulkActionsContainer').show();
            let bulkSkuOptions = '<option value="">Choose a SKU...</option>';
            availableSkus.filter(s => !s.toUpperCase().startsWith('PARENT')).forEach(function(sku) {
                bulkSkuOptions += '<option value="' + sku + '">' + sku + '</option>';
            });
            $('#bulkSkuSelect').html(bulkSkuOptions).select2({ theme: 'bootstrap-5', width: '100%' });

            let html = '';
            results.forEach(function(item) {
                const price = item.price ? '$' + parseFloat(item.price).toFixed(2) : 'N/A';
                const image = item.image || 'https://via.placeholder.com/100';
                const link = item.link || '#';
                html += '<div class="col-xl-3 col-lg-4 col-md-6 mb-3">' +
                    '<div class="product-card">' +
                    '<input type="checkbox" class="competitor-checkbox product-checkbox form-check-input" ' +
                    'data-product-id="' + (item.product_id || '') + '" ' +
                    'data-source="' + (item.source || '').replace(/"/g, '&quot;') + '" ' +
                    'data-title="' + (item.title || '').replace(/"/g, '&quot;') + '" ' +
                    'data-price="' + (item.price || 0) + '" ' +
                    'data-link="' + link.replace(/"/g, '&quot;') + '" ' +
                    'data-image="' + (item.image || '').replace(/"/g, '&quot;') + '" ' +
                    'data-rating="' + (item.rating || '') + '" ' +
                    'data-reviews="' + (item.reviews || '') + '">' +
                    '<div class="product-image-container"><img src="' + image + '" class="product-image" alt="" onclick="window.open(\'' + link.replace(/'/g, "\\'") + '\', \'_blank\')"></div>' +
                    '<div class="product-body">' +
                    '<a href="' + link + '" target="_blank" class="product-title text-decoration-none">' + (item.title || 'No title') + '</a>' +
                    '<div><span class="meta-badge price-badge">' + price + '</span>' +
                    (item.source ? '<span class="meta-badge source-badge">' + item.source + '</span>' : '') +
                    (item.rating ? '<span class="meta-badge">★ ' + item.rating + '</span>' : '') +
                    '</div></div></div></div></div>';
            });
            $('#resultsContent').html(html);
        }
        $('#resultsContainer').show();
    }

    function loadSearchHistory() {
        $.get('/repricer/google-search/history', function(response) {
            if (response.success && response.data.length) {
                let html = '';
                response.data.forEach(function(query) {
                    html += '<span class="history-badge" onclick="loadHistoryQuery(\'' + query.replace(/'/g, "\\'") + '\')">' + query + '</span>';
                });
                $('#historyContainer').html(html);
                $('#searchHistory').show();
            } else alert('No search history found');
        });
    }

    window.loadHistoryQuery = function(query) {
        $('#searchQuery').val(query);
        performSearch(query);
    };

    function loadSkus() {
        $.get('/repricer/google-search/skus', function(response) {
            if (!response.success) return;
            availableSkus = response.data || [];
            let html = '';
            availableSkus.filter(s => !s.toUpperCase().startsWith('PARENT')).forEach(function(sku) {
                const id = sku.replace(/[^a-zA-Z0-9]/g, '_');
                html += '<div class="form-check mb-1"><input class="form-check-input sku-checkbox" type="checkbox" value="' + sku + '" id="sku-' + id + '"><label class="form-check-label" for="sku-' + id + '">' + sku + '</label></div>';
            });
            $('#skuCheckboxList').html(html || '<p class="text-muted mb-0">No SKUs found</p>');
        });
    }

    function saveSelectedCompetitors() {
        const selectedSkus = [];
        const bulkSku = $('#bulkSkuSelect').val();
        if (bulkSku) selectedSkus.push(bulkSku);
        $('.sku-checkbox:checked').each(function() {
            const sku = $(this).val();
            if (sku && !selectedSkus.includes(sku)) selectedSkus.push(sku);
        });
        if (!selectedSkus.length) return alert('Select at least one SKU');

        const checked = $('.competitor-checkbox:checked');
        if (!checked.length) return alert('Select at least one product');

        const competitors = [];
        checked.each(function() {
            const $cb = $(this);
            selectedSkus.forEach(function(sku) {
                competitors.push({
                    product_id: String($cb.data('product-id') || ''),
                    source: $cb.data('source') || null,
                    sku: sku,
                    search_query: currentSearchQuery,
                    product_title: $cb.data('title') || null,
                    product_link: $cb.data('link') || null,
                    image: $cb.data('image') || null,
                    price: parseFloat($cb.data('price')) || 0,
                    rating: $cb.data('rating') || null,
                    reviews: $cb.data('reviews') || null,
                });
            });
        });

        if (!confirm('Save ' + competitors.length + ' Google LMP mapping(s)?')) return;

        const $btn = $('#saveCompetitorsBtn');
        $btn.prop('disabled', true);
        $.ajax({
            url: '/repricer/google-search/store-competitors',
            method: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            data: JSON.stringify({ competitors: competitors, _token: '{{ csrf_token() }}' }),
            success: function(response) {
                alert(response.success ? response.message : (response.message || 'Save failed'));
                if (response.success) $('.competitor-checkbox:checked').prop('checked', false);
            },
            error: function(xhr) {
                alert((xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Save failed');
            },
            complete: function() { $btn.prop('disabled', false); }
        });
    }
});
</script>
@endsection
