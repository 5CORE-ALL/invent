@extends('layouts.vertical', ['title' => 'Review Master'])
@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    .card {
        border-radius: 1rem;
        box-shadow: 0 2px 12px rgba(0,0,0,0.07);
        transition: box-shadow 0.2s;
    }
    .card:hover {
        box-shadow: 0 4px 24px rgba(0,0,0,0.12);
    }
    .card-header {
        border-radius: 1rem 1rem 0 0;
        background: linear-gradient(90deg, #f8fafc 60%, #e9ecef 100%);
    }
    .table th, .table td {
        vertical-align: middle !important;
    }
    .tbody tr:hover {
        background-color: #f1f3f6;
        transition: background 0.2s;
    }
    .btn-outline-primary, .btn-outline-success {
        border-radius: 0.5rem;
        font-weight: 500;
        transition: background 0.2s, color 0.2s;
    }
    .btn-outline-primary:hover, .btn-outline-success:hover {
        background: linear-gradient(90deg, #e3f2fd 60%, #e9ecef 100%);
        color: #0d6efd;
    }
    .badge {
        font-size: 0.95em;
        padding: 0.5em 0.8em;
        border-radius: 0.7em;
    }
    #showMoreMarketplaces {
        color: #0d6efd;
        font-weight: 500;
        text-decoration: underline;
    }
    #showMoreMarketplaces:hover {
        color: #0a58ca;
    }
    .table-responsive {
        border: 1px solid #e9ecef;
        background: #f8fafc;
    }
</style>
@endsection

@section('content')
@include('layouts.shared.page-title', ['page_title' => 'Review Master', 'sub_title' => 'Review Master'])

<div class="container-fluid px-2 px-md-4">
    <div class="row">
        <div class="col-12">
            <div class="row mb-4 align-items-center">
                <div class="col-md-6 mb-2 mb-md-0">
                    <button type="button" class="btn btn-primary btn-sm me-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#importModal">
                        <i class="fas fa-file-import me-1"></i> Import CSV
                    </button>
                    <a href="{{ route('negative.reviews.export') }}" class="btn btn-success btn-sm shadow-sm me-2">
                        <i class="fas fa-file-export me-1"></i> Export CSV
                    </a>
                    <a href="{{ route('negative.reviews') }}" class="btn btn-info btn-sm shadow-sm">
                        <i class="fas fa-eye me-1"></i> Show All Reviews
                    </a>
                </div>
                <div class="col-md-6 text-md-end">
                    <h4 class="mb-0 text-dark fw-semibold">
                        <i class="fas fa-flag me-2 text-danger"></i> Negative Reviews Overview
                    </h4>
                </div>
            </div>
            <!-- Card Row 1 -->
            <div class="row g-4">
                <!-- Star Ratings Card -->
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-header py-2 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-semibold text-black">
                                🚩 Total Negative Reviews
                            </h5>
                            <span class="badge bg-info fs-5 fw-semibold">{{ $starRatings['all'] }}</span>
                        </div>
                        <div class="card-body p-3">
                            <table class="table table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-center">Rating</th>
                                        <th class="text-center">Count</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="table-info">
                                        <td class="text-center fw-bold">All</td>
                                        <td class="text-center">
                                            <a href="javascript:void(0);" class="badge bg-primary text-decoration-none fs-5" id="allRatingsCountBtn" style="cursor:pointer;">{{ $starRatings['all'] }}</a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-center">
                                            <a href="{{ route('negative.reviews', ['rating' => 1]) }}" class="text-decoration-none text-black fw-semibold">
                                                <span class="text-warning">★</span> 1-star
                                            </a>
                                        </td>
                                        <td class="text-center">
                                            <a href="{{ route('negative.reviews', ['rating' => 1]) }}" class="badge bg-primary text-decoration-none fs-5" id="allRatingsCountBtn" style="cursor:pointer;">{{ $starRatings['1'] }}</a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-center">
                                            <a href="{{ route('negative.reviews', ['rating' => 2]) }}" class="text-decoration-none text-black fw-semibold">
                                                <span class="text-warning">★ ★</span> 2-star
                                            </a>
                                        </td>
                                        <td class="text-center">
                                            <a href="{{ route('negative.reviews', ['rating' => 2]) }}" class="badge bg-primary text-decoration-none fs-5" id="allRatingsCountBtn" style="cursor:pointer;">{{ $starRatings['2'] }}</a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-center">
                                            <a href="{{ route('negative.reviews', ['rating' => 3]) }}" class="text-decoration-none text-black fw-semibold">
                                                <span class="text-warning">★ ★ ★</span> 3-star
                                            </a>
                                        </td>
                                        <td class="text-center">
                                            <a href="{{ route('negative.reviews', ['rating' => 3]) }}" class="badge bg-primary text-decoration-none fs-5" id="allRatingsCountBtn" style="cursor:pointer;">{{ $starRatings['3'] }}</a>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <!-- Resolution Status Card -->
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-header py-2 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-semibold text-black">
                                ✔️ Resolved vs Unresolved
                            </h5>
                            <span class="badge bg-info fs-5 fw-semibold">{{ $resolvedStatus['all'] }}</span>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-center">Status</th>
                                        <th class="text-center">Count</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="table-info">
                                        <td class="text-center fw-bold">All</td>
                                        <td class="text-center">
                                            <a href="javascript:void(0);" class="badge bg-primary text-decoration-none fs-5" id="allRatingsCountBtn" style="cursor:pointer;">{{ $resolvedStatus['all'] }}</a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-center">
                                            <a href="{{ route('negative.reviews', ['action_status' => 'resolved']) }}">
                                                <span class="badge bg-success d-inline-flex align-items-center" style="font-size:1em;">
                                                    <i class="fas fa-check-circle me-1"></i> Resolved
                                                </span>
                                            </a>
                                        </td>
                                        <td class="text-center">
                                            <a href="{{ route('negative.reviews', ['action_status' => 'resolved']) }}" class="badge bg-primary text-decoration-none fs-5" id="allRatingsCountBtn" style="cursor:pointer;">{{ $resolvedStatus['resolved'] }}</a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-center">
                                            <a href="{{ route('negative.reviews', ['action_status' => 'pending']) }}">
                                                <span class="badge bg-warning text-dark d-inline-flex align-items-center" style="font-size:1em;">
                                                    <i class="fas fa-clock me-1"></i> Pending
                                                </span>
                                            </a>
                                        </td>
                                        <td class="text-center">
                                            <a href="{{ route('negative.reviews', ['action_status' => 'pending']) }}" class="badge bg-primary text-decoration-none fs-5" id="allRatingsCountBtn" style="cursor:pointer;">{{ $resolvedStatus['pending'] }}</a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-center fw-bold">
                                            <span class="badge bg-info text-white d-inline-flex align-items-center" style="font-size:1em;">
                                                📊 Pending %
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <a href="javascript:void(0);" class="badge bg-primary text-decoration-none fs-5" id="allRatingsCountBtn" style="cursor:pointer;">{{ $pendingPercentage }} %</a>
                                        </td>
                                    </tr>
                                </tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <!-- Top Marketplace by Negativity Card -->
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-header py-2 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-semibold text-black">
                                📊 Top Marketplace by Negativity
                            </h5>
                            <span class="badge bg-info fs-5 fw-semibold">{{ count($marketplaces) }}</span>
                        </div>
                        <div class="card-body">
                            <div style="max-height: 268px; overflow-y: auto;">
                                
                                @php
                                    $allMarketplaces = collect($marketplaces)->map(function($count, $name) {
                                        $icon = '<i class="fas fa-store me-1 text-muted"></i>'; // default icon
                                        $lower = strtolower($name);
                                        if ($lower === 'amazon') {
                                            $icon = '<i class="fab fa-amazon me-1 text-warning"></i>';
                                        } elseif ($lower === 'temu') {
                                            $icon = '<i class="fas fa-store me-1 text-info"></i>';
                                        } elseif ($lower === 'flipkart') {
                                            $icon = '<i class="fas fa-store me-1 text-secondary"></i>';
                                        } elseif ($lower === 'meesho') {
                                            $icon = '<i class="fas fa-store me-1 text-secondary"></i>';
                                        } elseif ($lower === 'snapdeal') {
                                            $icon = '<i class="fas fa-store me-1 text-secondary"></i>';
                                        } elseif ($lower === 'shopclues') {
                                            $icon = '<i class="fas fa-store me-1 text-secondary"></i>';
                                        }

                                        return [
                                            'name' => $name,
                                            'count' => $count,
                                            'icon' => $icon
                                        ];
                                    })->values();
                                @endphp


                                <table class="table table-bordered align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center">Marketplace</th>
                                            <th class="text-center">Count</th>
                                        </tr>
                                    </thead>
                                    <tbody id="marketplaceTable">
                                        {{-- First 4 rows --}}
                                        @foreach($allMarketplaces->take(4) as $marketplace)
                                            <tr>
                                                <td class="text-center fw-semibold">
                                                    <a href="{{ route('negative.reviews', ['marketplace' => $marketplace['name']]) }}" class="text-decoration-none text-black fw-semibold">
                                                        {!! $marketplace['icon'] !!}{{ $marketplace['name'] }}
                                                    </a>
                                                </td>
                                                <td class="text-center">
                                                    <a href="{{ route('negative.reviews', ['marketplace' => $marketplace['name']]) }}" class="text-decoration-none text-black fw-semibold">
                                                        <span class="badge bg-primary fs-5">{{ $marketplace['count'] }}</span>
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>

                                @if($allMarketplaces->count() > 4)
                                    <div class="text-center mt-2">
                                        <button class="btn btn-link p-0" id="showMoreMarketplaces">Show More</button>
                                    </div>
                                @endif

                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Card Row 2 -->
            <div class="row g-4 mt-2 mb-4">
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-header py-2 d-flex align-items-center">
                            <h5 class="mb-0 fw-semibold text-black">
                                <i class="fas fa-clock me-1"></i> Avg. Resolution Time
                            </h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-center">Total Actions</th>
                                        <th class="text-center">Avg. Action Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="text-center">
                                            <span class="badge bg-primary">
                                                {{ $actionSummary['totalActions'] }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-info text-dark">
                                                {{ $actionSummary['avgActionTime'] }} days
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <!-- Affected Listing Details Card -->
                <div class="col-lg-8">
                    <div class="card p-3 shadow-sm border-0 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="mb-0">
                                🛍️ <span class="fw-semibold text-primary">Affected Listing Details</span>
                                <span id="affected-count" class="badge bg-info ms-2"></span>
                            </h5>
                            <div>
                                <select id="skuFilter" class="form-select form-select-sm" style="min-width: 180px;">
                                    <option value="">Filter by SKU</option>
                                </select>
                            </div>
                        </div>
                        <div id="affected-listings-table-content" class="table-responsive" style="max-height: 320px; overflow-y: auto; display: none;">
                            <table class="table table-bordered table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-center">SKU</th>
                                        <th class="text-center">Marketplace</th>
                                        <th class="text-center">Total Negative</th>
                                        <th class="text-center text-danger">⭐</th>
                                        <th class="text-center text-warning">⭐ ⭐</th>
                                        <th class="text-center text-info">⭐ ⭐ ⭐</th>
                                    </tr>
                                </thead>
                                <tbody id="affectedListingsBody">
                                    <!-- Data will be rendered by JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('negative.reviews.import') }}" method="POST" enctype="multipart/form-data" class="modal-content" id="importForm">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="importModalLabel">Import Reviews</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Drag & Drop Area -->
                <div id="drop-area" class="border border-2 border-primary rounded p-4 text-center mb-3" style="cursor:pointer; position:relative;">
                    <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                    <p class="mb-1">Drag & drop your file here, or click to select</p>
                    <input type="file" name="review_file" id="importFileInput" style="opacity:0;position:absolute;top:0;left:0;width:100%;height:100%;cursor:pointer;" required>
                    <div id="fileName" class="small text-muted"></div>
                </div>
                <div id="selectedFilePreview" class="mt-2"></div>
                <a href="{{ asset('sample_excel/negative_reviews_template.xlsx') }}" class="btn btn-link">
                    <i class="fas fa-download me-1"></i> Download Sample File
                </a>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-file-import me-1"></i> Import
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.documentElement.setAttribute("data-sidenav-size", "condensed");
            openImportModal();
            showTopMarketPlaceByNegativity();
            renderAffectedListings(@json($affectedListings));
            //openImportModal function to handle file import
            function openImportModal(){
                const dropArea = document.getElementById('drop-area');
                const fileInput = document.getElementById('importFileInput');
                const fileName = document.getElementById('fileName');
                const filePreview = document.getElementById('selectedFilePreview');

                dropArea.addEventListener('click', function(e) {
                    if (e.target !== fileInput) {
                        fileInput.click();
                    }
                });

                dropArea.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    dropArea.classList.add('bg-light');
                });

                dropArea.addEventListener('dragleave', (e) => {
                    e.preventDefault();
                    dropArea.classList.remove('bg-light');
                });

                dropArea.addEventListener('drop', (e) => {
                    e.preventDefault();
                    dropArea.classList.remove('bg-light');
                    if (e.dataTransfer.files.length) {
                        fileInput.files = e.dataTransfer.files;
                        showFileInfo(e.dataTransfer.files[0]);
                    }
                });

                fileInput.addEventListener('change', () => {
                    if (fileInput.files.length) {
                        showFileInfo(fileInput.files[0]);
                    } else {
                        fileName.textContent = '';
                        filePreview.innerHTML = '';
                    }
                });

                function showFileInfo(file) {
                    fileName.textContent = file.name;
                    filePreview.innerHTML = `
                        <div class="alert alert-info py-2 px-3 mb-0 text-start">
                            <strong>File:</strong> ${file.name} <br>
                            <strong>Size:</strong> ${(file.size/1024).toFixed(2)} KB <br>
                            <strong>Type:</strong> ${file.type || 'Unknown'}
                        </div>
                    `;
                }

                if (fileInput.files.length) {
                    showFileInfo(fileInput.files[0]);
                }
            }

            function showTopMarketPlaceByNegativity() {
                const allMarketplaces = @json($allMarketplaces);
                let showing = 4;

                const table = document.getElementById('marketplaceTable');
                const showMoreBtn = document.getElementById('showMoreMarketplaces');

                const baseRoute = "{{ route('negative.reviews') }}";

                if (showMoreBtn) {
                    showMoreBtn.addEventListener('click', function () {
                        showing += 4;
                        table.innerHTML = '';
                        
                        allMarketplaces.slice(0, showing).forEach(function (marketplace) {
                            const reviewLink = `${baseRoute}?marketplace=${encodeURIComponent(marketplace.name)}`;
                            
                            table.innerHTML += `<tr>
                                <td class="text-center fw-semibold">
                                    <a href="${reviewLink}" class="text-decoration-none text-black fw-semibold">
                                        ${marketplace.icon}${marketplace.name}
                                    </a>
                                </td>
                                <td class="text-center">
                                    <a href="${reviewLink}" class="text-decoration-none text-black fw-semibold">
                                        <span class="badge bg-primary fs-5">${marketplace.count}</span>
                                    </a>
                                </td>
                            </tr>`;
                        });

                        if (showing >= allMarketplaces.length) {
                            showMoreBtn.style.display = 'none';
                        }
                    });
                }
            }

            function renderAffectedListings(affectedListings) {
                const listings = @json($affectedListings);

                listings.sort((a, b) => b.total - a.total); 

                const tbody = document.getElementById('affectedListingsBody');
                const skuFilter = document.getElementById('skuFilter');
                const affectedCount = document.getElementById('affected-count');
                const tableContent = document.getElementById('affected-listings-table-content');
                const maxInitial = 10;
                let loaded = maxInitial;
                let filteredListings = [];

                const uniqueSkus = [...new Set(listings.map(l => l.sku))];
                uniqueSkus.forEach(sku => {
                    const opt = document.createElement('option');
                    opt.value = sku;
                    opt.textContent = sku;
                    skuFilter.appendChild(opt);
                });

                function renderRows(start, end, data) {
                    for (let i = start; i < end && i < data.length; i++) {
                        const l = data[i];
                        const row = document.createElement('tr');

                        let marketplaceHtml = '';
                        if (Array.isArray(l.marketplaces) && l.marketplaces.length > 0) {
                            console.log(l.marketplaces);
                            marketplaceHtml = `<select class="form-select form-select-sm" style="width: 180px;">` +
                                l.marketplaces.map(m => {
                                    const stars = l.marketplaceStars?.[m] || {};
                                    const parts = [];

                                    if (stars.star1) parts.push(`1⭐ ${stars.star1}`);
                                    if (stars.star2) parts.push(`2⭐ ${stars.star2}`);
                                    if (stars.star3) parts.push(`3⭐ ${stars.star3}`);

                                    if (parts.length === 0) return '';

                                    return `<option value="${m}">${m}: ${parts.join(', ')}</option>`;
                                }).join('');

                                `</select>`;
                        } else {
                            marketplaceHtml = 'No marketplace';
                        }

                        row.innerHTML = `
                            <td class="text-center fw-semibold">${l.sku}</td>
                            <td class="text-center">${marketplaceHtml}</td>
                            <td class="text-center fw-bold text-danger">
                                <span class="badge bg-danger">${l.total || 0}</span>
                            </td>
                            <td class="text-center text-danger">
                                <span class="badge bg-danger">${l.star1 || 0}</span>
                            </td>
                            <td class="text-center text-warning">
                                <span class="badge bg-warning text-dark">${l.star2 || 0}</span>
                            </td>
                            <td class="text-center text-info">
                                <span class="badge bg-info text-dark">${l.star3 || 0}</span>
                            </td>
                        `;
                        tbody.appendChild(row);
                    }
                }



                function renderTable(data) {
                    tbody.innerHTML = '';
                    loaded = maxInitial;
                    renderRows(0, maxInitial, data);
                }

                tableContent.style.display = 'none';
                affectedCount.textContent = '';

                const container = tbody.parentElement.parentElement;
                container.addEventListener('scroll', function () {
                    if (container.scrollTop + container.clientHeight >= container.scrollHeight - 10) {
                        if (loaded < filteredListings.length) {
                            renderRows(loaded, loaded + 5, filteredListings);
                            loaded += 5;
                        }
                    }
                });

                skuFilter.addEventListener('change', function () {
                    const val = this.value;
                    if (val) {
                        filteredListings = listings.filter(l => l.sku === val);
                        renderTable(filteredListings);
                        tableContent.style.display = '';
                        affectedCount.textContent = filteredListings.length;
                    } else {
                        tableContent.style.display = 'none';
                        affectedCount.textContent = '';
                        tbody.innerHTML = '';
                    }
                });
            }
        });
    </script>
@endsection