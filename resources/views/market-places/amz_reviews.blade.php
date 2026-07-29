@extends('layouts.vertical', ['title' => 'Amz Reviews', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        #amz-rv-wrap .tabulator { border: 1px solid #dee2e6; border-radius: 8px; font-size: 12px; }
        #amz-rv-wrap .tabulator .tabulator-header { background: #f8f9fa; }
        #amz-rv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            white-space: normal !important; font-size: 11px; font-weight: 600; text-align: center; line-height: 1.2; padding: 4px 2px;
        }
        #amz-rv-wrap .tabulator .tabulator-cell { padding: 3px 4px !important; }
        #amz-rv-wrap .tabulator-row.tabulator-selected { background: #e7f1ff !important; }
        .amz-rv-thumb { width: 36px; height: 36px; object-fit: contain; border-radius: 4px; background: #fff; }
        .amz-rv-history-btn {
            border: 0; background: transparent; color: #0d6efd; padding: 0 2px; line-height: 1;
            cursor: pointer; font-size: 11px;
        }
        .amz-rv-history-btn:hover { color: #0a58ca; }
        .amz-rv-stop-sign {
            display: inline-flex; align-items: center; justify-content: center;
            width: 28px; height: 28px; border-radius: 50%;
            background: #dc2626; color: #fff; font-size: 8px; font-weight: 800;
            letter-spacing: -0.02em; line-height: 1; border: 2px solid #fff;
            box-shadow: 0 0 0 1.5px #dc2626; text-transform: uppercase;
        }
        .amz-rv-stop-sign-mini {
            display: inline-flex; align-items: center; justify-content: center;
            width: 18px; height: 18px; border-radius: 50%;
            background: #dc2626; color: #fff; font-size: 6px; font-weight: 800;
            border: 1.5px solid #fff; box-shadow: 0 0 0 1px #dc2626; line-height: 1;
        }
        #amz-rv-stop-ads-badge.active {
            background: #dc2626 !important; color: #fff !important; border-color: #dc2626 !important;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Amz Reviews',
        'sub_title'  => 'Amazon product ratings & reviews',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <span id="amz-rv-total" class="badge bg-secondary">Total: —</span>
                        <span id="amz-rv-selected" class="badge bg-primary">Selected: 0</span>
                        <button type="button" id="amz-rv-stop-ads-badge"
                            class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1"
                            title="Stop Ads: rating below 3 — click to filter">
                            <span class="amz-rv-stop-sign-mini" aria-hidden="true">STOP</span>
                            <span>Stop Ads: <span id="amz-rv-stop-ads-count">0</span></span>
                        </button>
                        <button type="button" id="amz-rv-refresh-btn" class="btn btn-sm btn-outline-primary" title="Reload table">
                            <i class="fa fa-refresh"></i>
                        </button>
                        <span class="text-muted small" id="amz-rv-status-line">
                            Ratings from amazon:collect-reviews
                        </span>
                    </div>

                    <div id="amz-rv-wrap">
                        <div class="p-2 bg-light border rounded-top d-flex align-items-center gap-2 flex-wrap">
                            <input type="search" id="amz-rv-search" class="form-control form-control-sm"
                                placeholder="Search Parent / SKU..." autocomplete="off" style="max-width: 320px;">
                            <label class="small text-muted mb-0 d-flex align-items-center gap-1">
                                <input type="checkbox" id="amz-rv-inv-gt0" class="form-check-input m-0"> INV &gt; 0
                            </label>
                            <select id="amz-rv-rating-filter" class="form-select form-select-sm"
                                style="width: auto;" title="Filter by Reviews color (Amazon avg rating)">
                                <option value="all">Reviews</option>
                                <option value="red">Red &lt;3</option>
                                <option value="yellow">Yellow 3-3.5</option>
                                <option value="blue">Blue 3.51-3.99</option>
                                <option value="green">Green 4-4.5</option>
                                <option value="pink">Pink &gt;4.5</option>
                                <option value="blank">No rating</option>
                            </select>
                            <label class="small text-muted mb-0 d-flex align-items-center gap-1"
                                title="Show only Stop Ads (rating below 3)">
                                <input type="checkbox" id="amz-rv-stop-ads-filter" class="form-check-input m-0"> Stop Ads
                            </label>
                        </div>
                        <div id="amz-reviews-table"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="amzRvHistoryModal" tabindex="-1" aria-labelledby="amzRvHistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title mb-0" id="amzRvHistoryModalLabel">
                        <i class="fas fa-history me-1"></i> Rating history
                    </h6>
                    <div class="d-flex align-items-center gap-2 ms-auto me-2">
                        <select id="amz-rv-history-range" class="form-select form-select-sm" style="width: 110px;">
                            <option value="0">Lifetime</option>
                            <option value="90">L90</option>
                            <option value="60">L60</option>
                            <option value="30" selected>L30</option>
                            <option value="7">L7</option>
                        </select>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body p-3">
                    <div id="amz-rv-history-loading" class="text-center py-4 text-muted" style="display:none;">
                        <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                        Loading rating history…
                    </div>
                    <div id="amz-rv-history-nodata" class="text-center py-4 text-muted" style="display:none;">
                        No rating snapshot history for this SKU yet.
                    </div>
                    <div id="amz-rv-history-container" style="display:none;">
                        <div id="amz-rv-history-chart" style="min-height: 280px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        let amzRvTable = null;
        let amzRvHistoryChart = null;
        let amzRvHistorySku = '';
        let amzRvHistoryParent = '';
        let amzRvHistoryDays = 30;
        const AMZ_RV_CHART_URL = @json(route('cvr.master.chart.data'));

        function amzRvEsc(s) {
            return String(s ?? '')
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function amzRvColumns() {
            return [
                {
                    formatter: 'rowSelection',
                    titleFormatter: 'rowSelection',
                    hozAlign: 'center',
                    headerSort: false,
                    width: 40,
                    frozen: true,
                },
                {
                    title: 'Image',
                    field: 'image',
                    hozAlign: 'center',
                    headerSort: false,
                    width: 52,
                    frozen: true,
                    formatter: function(cell) {
                        const url = cell.getValue();
                        if (!url) return '<span class="text-muted">—</span>';
                        return '<img class="amz-rv-thumb" src="' + amzRvEsc(url) + '" alt="">';
                    },
                },
                { title: 'Parent', field: 'parent', width: 110, frozen: true },
                { title: 'SKU', field: 'sku', width: 120, frozen: true },
                {
                    title: 'INV',
                    field: 'inv',
                    hozAlign: 'center',
                    width: 60,
                    frozen: true,
                    sorter: 'number',
                    formatter: function(cell) {
                        return Math.round(parseFloat(cell.getValue()) || 0).toLocaleString('en-US');
                    },
                },
                {
                    title: 'OV L30',
                    field: 'ov_l30',
                    hozAlign: 'center',
                    width: 70,
                    sorter: 'number',
                    formatter: function(cell) {
                        return Math.round(parseFloat(cell.getValue()) || 0).toLocaleString('en-US');
                    },
                },
                {
                    title: 'Dil',
                    field: 'dil_pct',
                    hozAlign: 'center',
                    width: 55,
                    sorter: 'number',
                    headerTooltip: 'Dil% = OV L30 / INV × 100',
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const inv = parseFloat(row.inv) || 0;
                        const ov = parseFloat(row.ov_l30) || 0;
                        if (inv <= 0) return '<span style="color:#6c757d;">0%</span>';
                        const dil = (ov / inv) * 100;
                        let color = '#e83e8c';
                        if (dil < 16.66) color = '#a00211';
                        else if (dil < 25) color = '#ffc107';
                        else if (dil < 50) color = '#28a745';
                        return '<span style="color:' + color + ';font-weight:600;">' + Math.round(dil) + '%</span>';
                    },
                },
                {
                    title: 'Amz L30',
                    field: 'amz_l30',
                    hozAlign: 'center',
                    width: 75,
                    sorter: 'number',
                    headerTooltip: 'Amazon units ordered L30 (A L30)',
                    formatter: function(cell) {
                        return Math.round(parseFloat(cell.getValue()) || 0).toLocaleString('en-US');
                    },
                },
                {
                    title: 'Reviews',
                    field: 'amz_avg_rating',
                    hozAlign: 'center',
                    headerSort: true,
                    width: 110,
                    headerTooltip: 'Avg rating + review count from amazon:collect-reviews',
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const sku = String(row.sku || '');
                        const parent = String(row.parent || '');
                        const histBtn = '<button type="button" class="amz-rv-history-btn" data-sku="'
                            + amzRvEsc(sku) + '" data-parent="' + amzRvEsc(parent) + '" title="View rating history">'
                            + '<i class="fas fa-history"></i></button>';
                        const rating = row.amz_avg_rating;
                        const reviews = row.amz_review_count;
                        if (rating === null || rating === undefined || rating === '' || parseFloat(rating) <= 0) {
                            return '<div style="display:flex;flex-direction:column;align-items:center;gap:2px;">'
                                + '<span class="text-muted">—</span>' + histBtn + '</div>';
                        }
                        const ratingVal = parseFloat(rating);
                        let ratingColor = '#a00211';
                        if (ratingVal >= 3 && ratingVal <= 3.5) ratingColor = '#ffc107';
                        else if (ratingVal >= 3.51 && ratingVal <= 3.99) ratingColor = '#3591dc';
                        else if (ratingVal >= 4 && ratingVal <= 4.5) ratingColor = '#28a745';
                        else if (ratingVal > 4.5) ratingColor = '#e83e8c';
                        const count = parseInt(reviews, 10) || 0;
                        const reviewColor = count < 4 ? '#a00211' : '#6c757d';
                        const reviewLabel = count === 1 ? '1 review' : (count.toLocaleString() + ' reviews');
                        const src = row.amz_reviews_source ? String(row.amz_reviews_source) : 'amazon';
                        return '<div style="display:flex;flex-direction:column;align-items:center;gap:2px;" title="Source: '
                            + amzRvEsc(src) + '">'
                            + '<span style="color:' + ratingColor + ';font-weight:600;">'
                            + '<i class="fa fa-star"></i> ' + ratingVal.toFixed(1) + '</span>'
                            + '<span style="font-size:11px;color:' + reviewColor + ';font-weight:600;">'
                            + amzRvEsc(reviewLabel) + '</span>'
                            + histBtn + '</div>';
                    },
                    sorter: function(a, b, aRow, bRow) {
                        const ra = parseFloat(aRow.getData().amz_avg_rating) || 0;
                        const rb = parseFloat(bRow.getData().amz_avg_rating) || 0;
                        return ra - rb;
                    },
                },
                {
                    title: 'Stop Ads',
                    field: 'stop_ads',
                    hozAlign: 'center',
                    headerSort: true,
                    width: 75,
                    headerTooltip: 'Stop Ads when Amazon avg rating is below 3',
                    formatter: function(cell) {
                        if (!cell.getValue()) return '<span class="text-muted">—</span>';
                        return '<span class="amz-rv-stop-sign" title="Stop Ads — rating below 3">STOP</span>';
                    },
                    sorter: function(a, b) {
                        return (a ? 1 : 0) - (b ? 1 : 0);
                    },
                },
            ];
        }

        function amzRvIsStopAds(data) {
            if (data && (data.stop_ads === true || data.stop_ads === 1 || data.stop_ads === '1')) return true;
            const rating = parseFloat(data && data.amz_avg_rating);
            return Number.isFinite(rating) && rating > 0 && rating < 3;
        }

        function amzRvUpdateCounts() {
            if (!amzRvTable) return;
            const shown = amzRvTable.getDataCount('active');
            const total = amzRvTable.getDataCount();
            $('#amz-rv-total').text('Total: ' + shown.toLocaleString() + (shown !== total ? ' / ' + total.toLocaleString() : ''));
            $('#amz-rv-selected').text('Selected: ' + amzRvTable.getSelectedData().length);
            const all = amzRvTable.getData() || [];
            $('#amz-rv-stop-ads-count').text(all.filter(amzRvIsStopAds).length.toLocaleString());
        }

        function amzRvApplyFilters() {
            if (!amzRvTable) return;
            const q = ($('#amz-rv-search').val() || '').toString().trim().toLowerCase();
            const invGt0 = $('#amz-rv-inv-gt0').is(':checked');
            const ratingFilter = ($('#amz-rv-rating-filter').val() || 'all').toString();
            const stopAdsOnly = $('#amz-rv-stop-ads-filter').is(':checked');
            amzRvTable.setFilter(function(data) {
                if (invGt0 && !(parseFloat(data.inv) > 0)) return false;
                if (stopAdsOnly && !amzRvIsStopAds(data)) return false;

                if (ratingFilter !== 'all') {
                    const rawRating = data.amz_avg_rating;
                    const rating = parseFloat(rawRating);
                    const hasRating = !(rawRating === null || rawRating === undefined
                        || (typeof rawRating === 'string' && rawRating.trim() === '')
                        || isNaN(rating) || rating <= 0);

                    if (ratingFilter === 'blank') {
                        if (hasRating) return false;
                    } else if (!hasRating) {
                        return false;
                    } else if (ratingFilter === 'red') {
                        if (!(rating < 3)) return false;
                    } else if (ratingFilter === 'yellow') {
                        if (!(rating >= 3 && rating <= 3.5)) return false;
                    } else if (ratingFilter === 'blue') {
                        if (!(rating >= 3.51 && rating <= 3.99)) return false;
                    } else if (ratingFilter === 'green') {
                        if (!(rating >= 4 && rating <= 4.5)) return false;
                    } else if (ratingFilter === 'pink') {
                        if (!(rating > 4.5)) return false;
                    }
                }

                if (!q) return true;
                const hay = [data.parent, data.sku].map(v => String(v || '').toLowerCase()).join(' ');
                return hay.indexOf(q) !== -1;
            });
            amzRvUpdateCounts();
            $('#amz-rv-stop-ads-badge').toggleClass('active', stopAdsOnly);
        }

        function amzRvOpenHistoryModal(sku, parent) {
            amzRvHistorySku = String(sku || '').trim();
            amzRvHistoryParent = String(parent || '').trim();
            if (!amzRvHistorySku && !amzRvHistoryParent) return;

            amzRvHistoryDays = 30;
            const rangeEl = document.getElementById('amz-rv-history-range');
            if (rangeEl) rangeEl.value = '30';

            const label = amzRvHistorySku || amzRvHistoryParent;
            const titleEl = document.getElementById('amzRvHistoryModalLabel');
            if (titleEl) {
                titleEl.innerHTML = '<i class="fas fa-history me-1"></i> Rating history — ' + amzRvEsc(label);
            }

            const modalEl = document.getElementById('amzRvHistoryModal');
            if (modalEl && typeof bootstrap !== 'undefined') {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
            amzRvLoadHistoryChart();
        }

        function amzRvLoadHistoryChart() {
            const loading = document.getElementById('amz-rv-history-loading');
            const container = document.getElementById('amz-rv-history-container');
            const noData = document.getElementById('amz-rv-history-nodata');
            if (loading) loading.style.display = '';
            if (container) container.style.display = 'none';
            if (noData) noData.style.display = 'none';

            const params = new URLSearchParams();
            params.set('metric', 'rating');
            params.set('days', String(amzRvHistoryDays || 0));
            if (amzRvHistorySku) params.set('sku', amzRvHistorySku);
            else if (amzRvHistoryParent) params.set('parent', amzRvHistoryParent);

            fetch(AMZ_RV_CHART_URL + '?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(r => r.json())
                .then(response => {
                    if (loading) loading.style.display = 'none';
                    const points = response && response.success && Array.isArray(response.data) ? response.data : [];
                    if (!points.length) {
                        if (noData) noData.style.display = '';
                        return;
                    }
                    if (container) container.style.display = '';
                    amzRvRenderHistoryChart(points);
                })
                .catch(() => {
                    if (loading) loading.style.display = 'none';
                    if (noData) noData.style.display = '';
                });
        }

        function amzRvRenderHistoryChart(points) {
            const el = document.getElementById('amz-rv-history-chart');
            if (!el || typeof Highcharts === 'undefined') return;

            const categories = points.map(p => p.date);
            const values = points.map(p => {
                const v = parseFloat(p.value);
                return Number.isFinite(v) ? Math.round(v * 100) / 100 : null;
            });

            if (amzRvHistoryChart) {
                try { amzRvHistoryChart.destroy(); } catch (e) {}
                amzRvHistoryChart = null;
            }

            amzRvHistoryChart = Highcharts.chart(el, {
                chart: { type: 'line', height: 280, backgroundColor: 'transparent' },
                title: { text: null },
                credits: { enabled: false },
                xAxis: { categories, tickInterval: Math.max(1, Math.floor(categories.length / 8)) },
                yAxis: {
                    title: { text: 'Rating' },
                    min: 0,
                    max: 5,
                    tickInterval: 0.5,
                },
                legend: { enabled: false },
                tooltip: {
                    pointFormatter: function() {
                        return '<b>' + Number(this.y).toFixed(1) + '</b> stars';
                    },
                },
                plotOptions: {
                    line: {
                        marker: { enabled: true, radius: 3 },
                        color: '#e83e8c',
                        lineWidth: 2,
                    },
                },
                series: [{ name: 'Rating', data: values }],
            });
        }

        function initAmzReviewsTable() {
            amzRvTable = new Tabulator('#amz-reviews-table', {
                height: '70vh',
                layout: 'fitDataStretch',
                placeholder: 'Loading…',
                selectableRows: true,
                selectableRowsRangeMode: 'click',
                pagination: true,
                paginationSize: 100,
                paginationSizeSelector: [50, 100, 250, 500],
                ajaxURL: @json(route('amz.reviews.data')),
                ajaxConfig: 'GET',
                ajaxResponse: function(url, params, response) {
                    if (response && response.meta) {
                        if (response.meta.refreshed_at) {
                            $('#amz-rv-status-line').text(
                                'Loaded · ' + response.meta.refreshed_at
                                + ' · Cached reviews: ' + (response.meta.reviews_cached || 0).toLocaleString()
                            );
                        }
                        if (response.meta.stop_ads_count != null) {
                            $('#amz-rv-stop-ads-count').text(Number(response.meta.stop_ads_count).toLocaleString());
                        }
                    }
                    return (response && response.data) ? response.data : [];
                },
                columns: amzRvColumns(),
            });

            amzRvTable.on('dataProcessed', amzRvUpdateCounts);
            amzRvTable.on('rowSelectionChanged', amzRvUpdateCounts);
            amzRvTable.on('pageLoaded', amzRvUpdateCounts);
        }

        $(function() {
            initAmzReviewsTable();

            $('#amz-rv-search').on('keyup input', function() { amzRvApplyFilters(); });
            $('#amz-rv-inv-gt0').on('change', function() { amzRvApplyFilters(); });
            $('#amz-rv-rating-filter').on('change', function() { amzRvApplyFilters(); });
            $('#amz-rv-stop-ads-filter').on('change', function() { amzRvApplyFilters(); });
            $('#amz-rv-stop-ads-badge').on('click', function() {
                const $cb = $('#amz-rv-stop-ads-filter');
                $cb.prop('checked', !$cb.is(':checked'));
                amzRvApplyFilters();
            });
            $('#amz-rv-refresh-btn').on('click', function() {
                if (amzRvTable) amzRvTable.replaceData();
            });

            $(document).on('click', '.amz-rv-history-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                amzRvOpenHistoryModal($(this).attr('data-sku'), $(this).attr('data-parent'));
            });

            $('#amz-rv-history-range').on('change', function() {
                const days = parseInt($(this).val(), 10);
                amzRvHistoryDays = Number.isFinite(days) ? days : 30;
                const rangeLabel = amzRvHistoryDays <= 0 ? 'Lifetime' : ('L' + amzRvHistoryDays);
                const label = amzRvHistorySku || amzRvHistoryParent;
                const titleEl = document.getElementById('amzRvHistoryModalLabel');
                if (titleEl) {
                    titleEl.innerHTML = '<i class="fas fa-history me-1"></i> Rating history — '
                        + amzRvEsc(label) + ' · ' + rangeLabel;
                }
                amzRvLoadHistoryChart();
            });
        });
    </script>
@endsection
