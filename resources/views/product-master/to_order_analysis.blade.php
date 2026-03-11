@if (request()->ajax())
    @include('product-master.partials.to_order_table', ['data' => $data])
@else
    @extends('layouts.vertical', ['title' => 'To Order Analysis', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
    @section('css')
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            .pagination-wrapper {
                width: auto;
                overflow-x: auto;
            }

            .pagination-wrapper .pagination {
                margin: 0;
                background: #fff;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
                border-radius: 4px;
                display: flex;
                flex-wrap: nowrap;
                gap: 4px;
            }

            .pagination-wrapper .page-item .page-link {
                padding: 0.5rem 1rem;
                min-width: 40px;
                text-align: center;
                color: #464646;
                border: 1px solid #f1f1f1;
                font-weight: 500;
                transition: all 0.2s ease;
                border-radius: 6px;
            }

            .pagination-wrapper .page-item.active .page-link {
                background: linear-gradient(135deg, #3bc0c3, #3bc0c3);
                border: none;
                color: white;
                font-weight: 600;
                box-shadow: 0 2px 4px rgba(114, 124, 245, 0.2);
            }

            .pagination-wrapper .page-item .page-link:hover:not(.active) {
                background-color: #f8f9fa;
                color: #3bc0c3;
                border-color: #e9ecef;
            }

            .pagination-wrapper p.small,
            .pagination-wrapper div.flex.items-center.justify-between {
                display: none !important;
            }

            @media (max-width: 576px) {
                .pagination-wrapper .page-item .page-link {
                    padding: 0.4rem 0.8rem;
                    min-width: 35px;
                    font-size: 0.875rem;
                }
            }

            .copy-link-cell {
                cursor: pointer;
                color: #007bff;
                text-decoration: underline;
            }

            .editable-cell:empty::before {
                content: attr(data-placeholder);
                color: #888;
                font-style: italic;
                pointer-events: none;
                display: inline-block;
                opacity: 0.6;
            }

            #suppliers-table {
                table-layout: auto !important;
                width: 100%;
                white-space: nowrap;
            }

            #suppliers-table th,
            #suppliers-table td {
                white-space: nowrap;
                /* content ek line me rahega */
            }

            .editable-cell {
                min-height: 28px;
            }

            .table-bordered {
                border: 1px solid #b8b9ba !important;
            }

            .image-hover-wrapper {
                position: relative;
                display: inline-block;
            }

            .image-hover-preview {
                display: none;
                position: absolute;
                top: -10px;
                left: 70px;
                z-index: 9999;
                background: #fff;
                padding: 6px;
                border: 1px solid #ccc;
                box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
            }

            .image-hover-preview img {
                width: 350px;
                height: auto;
                object-fit: contain;
                border-radius: 4px;
            }

            .image-hover-wrapper:hover .image-hover-preview {
                display: block;
            }

            #mslDetailsModal.show {
                z-index: 1055;
            }
            .cursor-pointer {
                cursor: pointer;
            }
            .open-msl-modal-cell.cursor-pointer:hover {
                background-color: rgba(59, 192, 195, 0.12);
            }
        </style>
    @endsection
    @section('content')
        @include('layouts.shared.page-title', [
            'page_title' => 'To Order Analysis',
            'sub_title' => 'TO BE DC',
        ])

        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body">

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="mb-0">To Order Analysis</h4>
                        </div>

                        <div class="row mb-4 g-3 align-items-end justify-content-between">
                            {{-- ▶️ Navigation --}}
                            <div class="col-auto">
                                <label class="form-label fw-semibold mb-1 d-block">▶️ Navigation</label>
                                <div class="btn-group time-navigation-group" role="group">
                                    <button id="play-backward" class="btn btn-light rounded-circle shadow-sm me-2"
                                        title="Previous parent">
                                        <i class="fas fa-step-backward"></i>
                                    </button>
                                    <button id="play-pause" class="btn btn-light rounded-circle shadow-sm me-2"
                                        style="display: none;" title="Pause">
                                        <i class="fas fa-pause"></i>
                                    </button>
                                    <button id="play-auto" class="btn btn-primary rounded-circle shadow-sm me-2"
                                        title="Play">
                                        <i class="fas fa-play"></i>
                                    </button>
                                    <button id="play-forward" class="btn btn-light rounded-circle shadow-sm"
                                        title="Next parent">
                                        <i class="fas fa-step-forward"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="col-auto">
                                <label class="form-label fw-semibold mb-1 d-block">Parent / Sku</label>
                                <select id="row-data-type" class="form-select border border-primary" style="width: 150px;">
                                    <option value="all">🔁 Show All</option>
                                    <option value="sku">🔹 SKU (Child)</option>
                                    <option value="parent">🔸 Parent</option>
                                </select>
                            </div>

                            <div class="col-auto">
                                <label class="form-label fw-semibold mb-1 d-block">Pending Status</label>
                                <select id="row-data-pending-status" class="form-select border border-primary" style="width: 150px;">
                                    <option value="">select color</option>
                                    <option value="green">Green <span id="greenCount"></span></option>
                                    <option value="yellow">yellow <span id="yellowCount"></span></option>
                                    <option value="red">red <span id="redCount"></span></option>
                                </select>
                            </div>

                            {{-- 🕒 Pending Items --}}
                            <div class="col-auto">
                                <label class="form-label fw-semibold mb-1 d-block">🕒 Pending Items</label>
                                <div class="fw-bold text-primary" style="font-size: 1.1rem;">
                                    {{ collect($allProcessedData)->filter(
                                            fn($row) => strtolower($row->Stage) !== 'mfrg progress' && !$row->is_parent && (int) $row->{'Approved QTY'} > 0,
                                        )->count() }}
                                </div>
                            </div>

                            <div class="col-auto" hidden>
                                <label class="form-label fw-semibold mb-1 d-block">Total Approved Qty</label>
                                <div class="fw-bold text-primary" style="font-size: 1.1rem;">
                                    {{ collect($allProcessedData)->filter(
                                            fn($row) => strtolower($row->Stage) !== 'mfrg progress' && !$row->is_parent && (int) $row->{'Approved QTY'} > 0,
                                        )->sum(fn($row) => (int) $row->{'Approved QTY'}) }}
                                </div>
                            </div>

                            {{-- 📦 Total CBM --}}
                            <div class="col-auto">
                                <label class="form-label fw-semibold mb-1 d-block">📦 Total CBM</label>
                                <div class="fw-bold text-success" style="font-size: 1.1rem;">
                                    {{ number_format($totalCBM ?? 0, 0) }}
                                </div>
                            </div>

                            {{-- 💰 Amount (Or.QTY * CP) --}}
                            <div class="col-auto">
                                <label class="form-label fw-semibold mb-1 d-block">💰 Amount</label>
                                <div class="fw-bold text-danger" style="font-size: 1.1rem;">
                                    @php
                                        $totalAmount = collect($allProcessedData ?? [])->sum(function ($row) {
                                            $orderQty = (int) ($row->{'Or.QTY'} ?? $row->order_qty ?? 0);
                                            $cp = (float) ($row->CP ?? ($row->{'CP'} ?? 0));
                                            return $orderQty * $cp;
                                        });
                                    @endphp
                                    {{ number_format($totalAmount, 2) }}
                                </div>
                            </div>

                            {{-- 🎯 Stage Filter --}}
                            <div class="col-auto">
                                <label class="form-label fw-semibold mb-1 d-block">🎯 Stage</label>
                                <select id="stage-filter" class="form-select form-select-sm" style="min-width: 160px;">
                                    <option value="">All Stages</option>
                                    <option value="rfq sent">RFQ Sent</option>
                                    <option value="analytics">Analytics</option>
                                    <option value="to approve">To Approve</option>
                                    <option value="approved">Approved</option>
                                    <option value="advance">Advance</option>
                                    <option value="mfrg progress">Mfrg Progress</option>
                                </select>
                            </div>

                            {{-- Show NR/LATER Buttons --}}
                            <div class="col-auto">
                                <label class="form-label fw-semibold mb-1 d-block">🔍 NRP Filter</label>
                                <div class="btn-group" role="group">
                                    <button type="button" id="toggle-nr-rows" class="btn btn-sm" 
                                        style="background-color: #343a40; color: white; border: 1px solid #343a40;">
                                        Show NR
                                    </button>
                                    <button type="button" id="toggle-later-rows" class="btn btn-sm" 
                                        style="background-color: #343a40; color: white; border: 1px solid #343a40;">
                                        Show LATER
                                    </button>
                                </div>
                            </div>

                            {{-- 🔍 Search --}}
                            <div class="col-auto">
                                <label for="search-input" class="form-label fw-semibold mb-1 d-block">🔍 Search</label>
                                <input type="text" id="search-input" class="form-control form-control-sm"
                                    placeholder="Search suppliers...">
                            </div>
                        </div>

                        <div class="table-responsive" id="table-container">
                            <table class="table table-centered table-bordered mb-0" id="suppliers-table">
                                <thead class="table-light">
                                    <tr class="fw-bold text-uppercase">
                                        <th>Image</th>
                                        <th>Parent</th>
                                        <th>SKU</th>
                                        <th title="Approved Quantity">Appr. QTY</th>
                                        <th title="Minimum Stock Level">MSL</th>
                                        <th title="Date of Approval">DOA</th>
                                        <th>Supplier</th>
                                        <th title="Rating and reviews from Jungle Scout">Reviews</th>
                                        <th>Review</th>
                                        <th>RFQ Form <i class="mdi mdi-link-variant"></i> </th>
                                        <th>Rfq Report <i class="mdi mdi-link-variant"></i> </th>
                                        <th>Sheet <i class="mdi mdi-link-variant"></i> </th>
                                        <th>NRP</th>
                                        <th>Stage</th>
                                        <th>Adv date</th>
                                        <th>Order Qty</th>
                                    </tr>
                                </thead>
                                @include('product-master.partials.to_order_table', ['data' => $data])
                            </table>
                        </div>

                        <div class="d-flex justify-content-end my-3 mx-3">
                            <div class="pagination-wrapper">
                                {{ $data->onEachSide(1)->links('pagination::bootstrap-5') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MSL Details Modal (same as forecast page) -->
        <div class="modal fade" id="mslDetailsModal" tabindex="-1" role="dialog" aria-labelledby="mslModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl" role="document">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="mslModalLabel">MSL Details</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="d-flex flex-nowrap overflow-auto flex-wrap gap-3">
                            <div class="card border-left-primary shadow-sm mb-0" style="min-width: 200px;">
                                <div class="card-body">
                                    <h6 class="card-title text-muted">MSL</h6>
                                    <p class="card-text font-weight-bold text-dark" id="toorder-modalsmsl">—</p>
                                </div>
                            </div>
                            <div class="card border-left-success shadow-sm mb-0" style="min-width: 200px;">
                                <div class="card-body">
                                    <h6 class="card-title text-muted">S-MSL</h6>
                                    <p class="card-text font-weight-bold text-dark" id="toorder-modals_msl">—</p>
                                </div>
                            </div>
                            <div class="card border-left-info shadow-sm mb-0" style="min-width: 200px;">
                                <div class="card-body">
                                    <h6 class="card-title text-muted">LP * msl</h6>
                                    <p class="card-text font-weight-bold text-dark" id="toorder-modallp_msl">—</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Review Modal -->
        <div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form id="reviewForm">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="reviewModalLabel">📝 To Order Review</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">🏭 Parent</label>
                                    <input type="text" class="form-control" id="review_parent" name="parent"
                                        readonly>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">🔢 SKU</label>
                                    <input type="text" class="form-control" id="review_sku" name="sku" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">🏢 Supplier</label>
                                    <input type="text" class="form-control" id="review_supplier" name="supplier"
                                        readonly>
                                </div>

                                <div class="col-md-12">
                                    <label for="positive_review" class="form-label">✨ Positive Review <span
                                            class="text-danger">*</span></label>
                                    <textarea class="form-control" id="positive_review" name="positive_review" rows="3"
                                        placeholder="Enter positive aspects..." required></textarea>
                                </div>
                                <div class="col-md-12">
                                    <label for="negative_review" class="form-label">⚠️ Negative Review <span
                                            class="text-danger">*</span></label>
                                    <textarea class="form-control" id="negative_review" name="negative_review" rows="3"
                                        placeholder="Enter areas of concern..." required></textarea>
                                </div>
                                <div class="col-md-12">
                                    <label for="improvement" class="form-label">📈 Improvement Required <span
                                            class="text-danger">*</span></label>
                                    <textarea class="form-control" id="improvement" name="improvement" rows="3"
                                        placeholder="Enter suggested improvements..." required></textarea>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <label for="date_updated" class="form-label">📅 Date Updated <span
                                                class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="date_updated" name="date_updated"
                                            required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="clink" class="form-label">🔗 Competitor Link</label>
                                        <div class="input-group">
                                            <a href="#" class="btn btn-outline-primary" id="clink"
                                                target="_blank">
                                                <i class="mdi mdi-eye me-1"></i>
                                                View Competitor Link
                                            </a>
                                        </div>
                                        <small class="text-muted mt-1 d-block">Click to view the competitor link</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">❌ Close</button>
                            <button type="submit" class="btn btn-primary">
                                💾 Save Review
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endsection
    @section('script')
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                document.documentElement.setAttribute("data-sidenav-size", "condensed");

                const groupedSkuData = @json($groupedDataJson);
                const parentKeys = Object.keys(groupedSkuData);

                const groupedSupplierData = {!! $groupedSupplierJson !!};
                const supplierKeys = Object.keys(groupedSupplierData);

                let currentSupplierIndex = 0;
                let supplierPlaying = false;

                let currentIndex = 0;
                let isPlaying = false;

                const tableBody = document.querySelector('#suppliers-table tbody');
                const originalTableHtml = tableBody.innerHTML;

                // --- Search ---
                let searchTimer;
                document.getElementById('search-input').addEventListener('keyup', function() {
                    clearTimeout(searchTimer);
                    let searchTerm = this.value.toLowerCase();

                    searchTimer = setTimeout(() => {
                        fetch(`/to-order-analysis?search=${searchTerm}`, {
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            })
                            .then(res => res.text())
                            .then(html => {
                                tableBody.innerHTML = html;
                                attachEditableListeners();
                                attachStageListeners();
                            });
                    }, 300);
                });

                // --- Play Mode ---
                function renderGroup(supplierKey) {
                    const rows = Object.values(groupedSupplierData[supplierKey] || {});

                    if (!rows.length) return;

                    const supplierOptionsHtml = `{!! collect($suppliers)->map(function ($supplier) {
                            return '<option value="' . e($supplier) . '">' . e($supplier) . '</option>';
                        })->implode('') !!}`;

                    let html = '';
                    rows.forEach(item => {
                        const approvedQty = parseInt(item['Approved QTY'] ?? 0);
                        let daysDiff = null;
                        let bgColor = '';
                        if (item['Date of Appr']) {
                            const apprDate = new Date(item['Date of Appr']);
                            const today = new Date();
                            daysDiff = Math.floor((today - apprDate) / (1000 * 60 * 60 * 24));

                            if (daysDiff > 14) {
                                bgColor = 'background-color: red; color: white;';
                            } else if (daysDiff > 7) {
                                bgColor = 'background-color: yellow; color: black;';
                            } else {
                                bgColor = 'background-color: green; color: white;';
                            }
                        }
                        html += `
                        <tr style="${item.is_parent ? 'background-color:#e0f7ff;' : ''}" data-is-parent=" ${item.is_parent ? '1' : '0'}">
                            <td>${item['Image'] ? `<img src="${item['Image']}" style="width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid #eee;">` : `<span class="text-muted">No Image</span>`}</td>
                            <td class="fw-semibold">${item.Parent ?? '-'}</td>
                            <td><span class="fw-semibold text-dark">${item.SKU ?? '-'}</span></td>
                            <td><input type="number" class="form-control form-control-sm order-qty" data-sku="${item.SKU}" data-column="approved_qty" value="${approvedQty}" style="width:100px;"></td>
                            <td class="text-center open-msl-modal-cell ${(item.msl != null && item.msl > 0) ? 'cursor-pointer' : ''}" style="min-width:70px;" ${(item.msl != null && item.msl > 0) ? `data-msl="${item.msl}" data-s-msl="${item.s_msl != null ? item.s_msl : ''}" data-lp-msl="${item.lp_msl != null ? item.lp_msl : ''}" title="Click for MSL details"` : ''}>
                                ${(item.msl != null && item.msl > 0) ? `<span class="d-inline-flex align-items-center gap-1"><span>${item.msl}</span><i class="ri-information-line text-primary small ms-1" style="font-size:14px;opacity:0.9;" title="MSL details"></i></span>` : '<span class="text-muted">—</span>'}
                            </td>
                            <td class="date-cell" data-dateOfAppr="${daysDiff}">
                                <div style="display: flex; flex-direction: column; align-items: flex-start;">
                                    <input type="date" class="form-control form-control-sm stage-select"
                                        data-sku="${item.SKU}" data-column="Date of Appr"
                                        value="${item['Date of Appr'] ?? ''}" style="width: 82px; ${bgColor}">

                                    ${daysDiff !== null ? `<small style="font-size:12px;color:rgb(72,69,69);">${daysDiff} days ago</small>` : ''}
                                </div>
                            </td>
                            <td>
                                <select class="form-select stage-select" data-sku="${item.SKU}" data-column="Supplier">
                                    ${supplierOptionsHtml.replace(`value="${item.Supplier}"`, `value="${item.Supplier}" selected`)}
                                </select>
                            </td>
                            <td class="text-center" style="min-width:80px;">
                                ${(function(){
                                    const rating = item.rating != null && item.rating > 0 ? parseFloat(item.rating) : null;
                                    const reviews = item.reviews != null ? parseInt(item.reviews) : null;
                                    if (!rating) return '<span class="text-muted">-</span>';
                                    let ratingColor = rating < 3 ? '#a00211' : (rating <= 3.5 ? '#ffc107' : (rating <= 3.99 ? '#3591dc' : (rating <= 4.5 ? '#28a745' : '#e83e8c')));
                                    const reviewColor = (reviews != null && reviews < 4) ? '#a00211' : '#6c757d';
                                    return '<div class="d-flex flex-column align-items-center gap-0" style="font-size:0.9rem;"><span style="color:'+ratingColor+';font-weight:600;"><i class="fas fa-star"></i> '+rating.toFixed(1)+'</span>' + (reviews != null ? '<span style="font-size:11px;color:'+reviewColor+';font-weight:600;">'+parseInt(reviews).toLocaleString()+' reviews</span>' : '') + '</div>';
                                })()}
                            </td>
                            <td>
                                <button class="btn btn-sm ${item.review ? 'btn-outline-success' : 'btn-outline-dark'} open-review-modal"
                                    data-parent="${item.Parent}" data-sku="${item.SKU}" data-supplier="${item.Supplier}"
                                    data-positive="${item.positive_review}" data-negative="${item.negative_review}"
                                    data-improvement="${item.improvement}" data-clink="${item.Clink}" data-date_updated="${item.date_updated}">
                                    <i class="fas ${item.review ? 'fa-eye' : 'fa-pen'} me-1"></i> ${item.review ? 'View Review' : 'Review'}
                                </button>
                            </td>
                            ${item['RFQ Form Link'] ? `<td><a href="${item['RFQ Form Link']}" class="btn btn-sm btn-outline-primary"><i class="mdi mdi-content-copy"></i> Open</a></td>` : `<td contenteditable="true" class="editable-cell" data-sku="${item.SKU}" data-column="RFQ Form Link" style="background:#f8fafd;min-width:180px;"></td>`}
                            ${item['Rfq Report Link'] ? `<td><a href="${item['Rfq Report Link']}" class="btn btn-sm btn-outline-success"><i class="mdi mdi-content-copy"></i> Open</a></td>` : `<td contenteditable="true" class="editable-cell report-cell" data-sku="${item.SKU}" data-column="Rfq Report Link" style="background:#f8fafd;"></td>`}
                            ${item.sheet_link ? `<td><a href="${item.sheet_link}" class="btn btn-sm btn-outline-success"><i class="mdi mdi-content-copy"></i> Open</a></td>` : `<td contenteditable="true" class="editable-cell report-cell" data-sku="${item.SKU}" data-column="sheet_link" style="background:#f8fafd;"></td>`}
                            <td>
                                <select class="form-select form-select-sm stage-select" data-sku="${item.SKU}" data-column="nrl">
                                    ${['REQ','NR'].map(nrLOption =>
                                        `<option value="${nrLOption}" ${nrLOption === item.nrl ? 'selected' : ''}>${nrLOption}</option>`
                                    ).join('')}
                                </select>
                            </td>
                            <td>
                                <select class="form-select form-select-sm stage-select" data-sku="${item.SKU}" data-column="Stage">
                                    ${['RFQ Sent','Analytics','To Approve','Approved','Advance','Mfrg Progress'].map(stageOption =>
                                        `<option value="${stageOption}" ${stageOption === item.Stage ? 'selected' : ''}>${stageOption}</option>`
                                    ).join('')}
                                </select>
                            </td>
                            <td><input type="date" class="form-control form-control-sm stage-select" data-sku="${item.SKU}" data-column="Adv date" value="${item['Adv date'] ?? ''}" style="width: 80px;"></td>
                            <td><input type="number" class="form-control form-control-sm order-qty" data-sku="${item.SKU}" data-column="order_qty" value="${item.order_qty ?? ''}" style="width:110px;"></td>
                        </tr>`;
                    });
                    console.log("Table Body Element Found?", tableBody);

                    tableBody.innerHTML = html;
                    attachEditableListeners();
                    attachStageListeners();
                }

                document.getElementById('play-auto').addEventListener('click', () => {
                    isPlaying = true;
                    currentSupplierIndex = 0;
                    renderGroup(supplierKeys[currentSupplierIndex]);
                    document.getElementById('play-pause').style.display = 'inline-block';
                    document.getElementById('play-auto').style.display = 'none';
                });

                document.getElementById('play-forward').addEventListener('click', () => {
                    if (!isPlaying) return;
                    currentSupplierIndex = (currentSupplierIndex + 1) % supplierKeys.length;
                    renderGroup(supplierKeys[currentSupplierIndex]);
                });

                document.getElementById('play-backward').addEventListener('click', () => {
                    if (!isPlaying) return;
                    currentSupplierIndex = (currentSupplierIndex - 1 + supplierKeys.length) % supplierKeys
                        .length;
                    renderGroup(supplierKeys[currentSupplierIndex]);
                });

                document.getElementById('play-pause').addEventListener('click', () => {
                    isPlaying = false;
                    tableBody.innerHTML = originalTableHtml;
                    document.getElementById('play-pause').style.display = 'none';
                    document.getElementById('play-auto').style.display = 'inline-block';
                    attachEditableListeners();
                    attachStageListeners();
                });


                // --- Stage Filter ---
                document.getElementById('stage-filter').addEventListener('change', function() {
                    refreshToOrderData();
                });

                // --- Listeners ---
                function attachEditableListeners() {
                    document.querySelectorAll('.editable-cell').forEach(cell => {
                        cell.addEventListener('blur', function() {
                            const sku = this.dataset.sku,
                                column = this.dataset.column,
                                value = this.innerText.trim();
                            if (!value) return;

                            fetch('/update-link', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                    },
                                    body: JSON.stringify({
                                        sku,
                                        column,
                                        value
                                    })
                                })
                                .then(res => res.json())
                                .then(res => {
                                    if (res.success) {
                                        const btnClass = column === 'RFQ Form Link' ?
                                            'btn-outline-primary' : 'btn-outline-success';
                                        this.outerHTML =
                                            `<td><a href="${value}" class="btn btn-sm ${btnClass}" data-link="${value}"><i class="mdi mdi-link"></i> Open</a></td>`;
                                    } else {
                                        this.style.backgroundColor = '#ffebee';
                                        alert('Error: ' + res.message);
                                    }
                                });
                        });
                    });

                    document.querySelectorAll('.order-qty').forEach(input => {
                        input.addEventListener('blur', function() {
                            const sku = this.dataset.sku,
                                column = this.dataset.column,
                                value = this.value;
                            fetch('/update-link', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                    },
                                    body: JSON.stringify({
                                        sku,
                                        column,
                                        value
                                    })
                                })
                                .then(res => res.json())
                                .then(res => {
                                    if (res.success) {
                                        this.style.border = '2px solid green';
                                        setTimeout(() => this.style.border = '', 1000);
                                    } else {
                                        this.style.border = '2px solid red';
                                        alert('❌ Error: ' + res.message);
                                    }
                                });
                        });
                    });
                }

                function attachStageListeners() {
                    document.querySelectorAll('.stage-select, .date-input').forEach(input => {
                        input.addEventListener('change', function() {
                            const sku = this.dataset.sku,
                                column = this.dataset.column,
                                value = this.value;
                            const row = this.closest('tr');

                            fetch('/update-link', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                    },
                                    body: JSON.stringify({
                                        sku,
                                        column,
                                        value
                                    })
                                })
                                .then(res => res.json())
                                .then(res => {
                                    if (res.success) {
                                        this.style.border = '2px solid #28a745';
                                        setTimeout(() => this.style.border = '', 1000);

                                        if (column === 'Stage' && value.toLowerCase() ===
                                            'mfrg progress') {
                                            const parent = row.querySelector('td:nth-child(2)')
                                                ?.innerText?.trim() || '';
                                            const skuVal = row.querySelector('td:nth-child(3)')
                                                ?.innerText?.trim() || '';
                                            const order_qty = row.querySelector(
                                                    'input[data-column="order_qty"]')?.value
                                            ?.trim() || '';
                                            const supplier = row.querySelector('td:nth-child(6)')
                                                ?.innerText?.trim() || '';
                                            const advDate = row.querySelector('input[type="date"]')
                                                ?.value || '';

                                            fetch('/mfrg-progresses/insert', {
                                                    method: 'POST',
                                                    headers: {
                                                        'Content-Type': 'application/json',
                                                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                                    },
                                                    body: JSON.stringify({
                                                        parent,
                                                        sku: skuVal,
                                                        order_qty,
                                                        supplier,
                                                        adv_date: advDate
                                                    })
                                                })
                                                .then(r => r.json())
                                                .then(r => {
                                                    if (r.success) {
                                                        row.remove();
                                                        const currentStage = document
                                                            .getElementById('stage-filter')
                                                            .value.toLowerCase();
                                                        if (currentStage && currentStage !==
                                                            'mfrg progress') {
                                                            document.getElementById(
                                                                    'stage-filter')
                                                                .dispatchEvent(new Event(
                                                                    'change'));
                                                        }
                                                        if (groupedSkuData[parent]) {
                                                            groupedSkuData[parent] = Object
                                                                .fromEntries(
                                                                    Object.entries(
                                                                        groupedSkuData[parent])
                                                                    .filter(([key, val]) => val
                                                                        .SKU !== skuVal)
                                                                );
                                                        }
                                                    } else {
                                                        alert('❌ Failed to insert: ' + r
                                                            .message);
                                                    }
                                                });
                                        }
                                    } else {
                                        this.style.border = '2px solid red';
                                        alert('❌ Error: ' + res.message);
                                    }
                                });
                        });
                    });
                }

                // --- Copy to Clipboard ---
                $(document).on('click', '.copy-btn', function() {
                    const btn = $(this);
                    const link = btn.data('link');

                    navigator.clipboard.writeText(link).then(() => {
                        // btn.text('Copied!');
                        setTimeout(() => btn.text('Open Link'), 1500);
                    }).catch(() => {
                        alert('Failed to copy!');
                    });
                });

                // --- Review Modal ---
                $(document).on('click', '.open-msl-modal-cell[data-msl]', function(e) {
                    e.preventDefault();
                    const el = $(this);
                    const msl = el.attr('data-msl');
                    const sMsl = el.attr('data-s-msl');
                    const lpMsl = el.attr('data-lp-msl');
                    $('#toorder-modalsmsl').text(msl ? msl : '—');
                    $('#toorder-modals_msl').text(sMsl !== undefined && sMsl !== '' ? sMsl : '—');
                    $('#toorder-modallp_msl').text(lpMsl !== undefined && lpMsl !== '' ? lpMsl : '—');
                    $('#mslDetailsModal')
                        .addClass('show')
                        .css('display', 'block')
                        .attr('aria-modal', 'true')
                        .removeAttr('aria-hidden');
                    $('body').addClass('modal-open');
                    if ($('.modal-backdrop').length === 0) {
                        $('body').append('<div class="modal-backdrop fade show" style="z-index: 1050;"></div>');
                    }
                });
                $(document).on('click', '#mslDetailsModal .btn-close, #mslDetailsModal [data-bs-dismiss="modal"]', function() {
                    $('#mslDetailsModal')
                        .removeClass('show')
                        .css('display', 'none')
                        .removeAttr('aria-modal')
                        .attr('aria-hidden', 'true');
                    $('body').removeClass('modal-open');
                    $('.modal-backdrop').remove();
                });
                $(document).on('click', '.modal-backdrop', function() {
                    if ($('#mslDetailsModal').hasClass('show')) {
                        $('#mslDetailsModal').removeClass('show').css('display', 'none').removeAttr('aria-modal').attr('aria-hidden', 'true');
                        $('body').removeClass('modal-open');
                        $(this).remove();
                    }
                });

                $(document).on('click', '.open-review-modal', function() {
                    const btn = $(this);
                    $('#review_parent').val(btn.data('parent'));
                    $('#review_sku').val(btn.data('sku'));
                    $('#review_supplier').val(btn.data('supplier'));
                    $('#positive_review').val(btn.data('positive') || '');
                    $('#negative_review').val(btn.data('negative') || '');
                    $('#improvement').val(btn.data('improvement') || '');
                    $('#clink').attr('href', btn.data('clink') || '#');
                    $('#date_updated').val(btn.data('date_updated') || new Date().toISOString().split('T')[0]);
                    $('#reviewModal').modal('show');
                });

                $('#reviewForm').on('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    $.ajax({
                        url: '{{ route('save.to_order_review') }}',
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        success: res => {
                            if (res.success) {
                                alert('Review saved successfully!');
                                $('#reviewModal').modal('hide');
                            } else {
                                alert('Failed to save review: ' + (res.message || 'Unknown error'));
                            }
                        },
                        error: xhr => {
                            alert('Error saving review: ' + (xhr.responseJSON?.message ||
                                'Unknown error occurred'));
                        }
                    });
                });

                // Zoom out table
                document.body.style.zoom = '80%';

                // Initial listener attach
                attachEditableListeners();
                attachStageListeners();

            });

            // Initial load: show only SKU rows
            document.addEventListener("DOMContentLoaded", function() {
                filterRows("sku");
            });

            // On dropdown change
            document.getElementById("row-data-type").addEventListener("change", function() {
                filterRows(this.value);
            });

            function filterRows(type) {
                const rows = document.querySelectorAll("#suppliers-table tr");

                rows.forEach(row => {
                    const isParent = row.dataset.isParent === "1";

                    if (type === "all") {
                        row.style.display = "";
                    } else if (type === "parent" && isParent) {
                        row.style.display = "";
                    } else if (type === "sku" && !isParent) {
                        row.style.display = "";
                    } else {
                        row.style.display = "none";
                    }
                });
            }

            const greenSpan = document.getElementById("greenCount");
            const yellowSpan = document.getElementById("yellowCount");
            const redSpan = document.getElementById("redCount");

            const filterSelect = document.getElementById("row-data-pending-status");
            const rows = document.querySelectorAll("#suppliers-table tr");

            function updateCounts() {
                let green = 0, yellow = 0, red = 0;

                rows.forEach(row => {
                    const input = row.querySelector('input[data-column="Date of Appr"]');
                    if (input) {
                        const bg = input.style.backgroundColor;
                        if (bg === 'green') green++;
                        else if (bg === 'yellow') yellow++;
                        else if (bg === 'red') red++;
                    }
                });

                greenSpan.innerText = `(${green})`;
                yellowSpan.innerText = `(${yellow})`;
                redSpan.innerText = `(${red})`;
            }

            function filterDateRows(type) {
                rows.forEach(row => {
                    const input = row.querySelector('input[data-column="Date of Appr"]');
                    if (!input) return;
                    const bg = input.style.backgroundColor;
                    row.style.display = (!type || bg === type) ? '' : 'none';
                });
            }

            updateCounts();

            filterSelect.addEventListener("change", function() {
                filterDateRows(this.value);
            });

            // NR and LATER toggle buttons
            let showNR = false;
            let showLATER = false;

            document.getElementById('toggle-nr-rows').addEventListener('click', function() {
                showNR = !showNR;
                this.textContent = showNR ? 'Hide NR' : 'Show NR';
                this.style.backgroundColor = showNR ? '#28a745' : '#343a40';
                refreshToOrderData();
            });

            document.getElementById('toggle-later-rows').addEventListener('click', function() {
                showLATER = !showLATER;
                this.textContent = showLATER ? 'Hide LATER' : 'Show LATER';
                this.style.backgroundColor = showLATER ? '#28a745' : '#343a40';
                refreshToOrderData();
            });

            function refreshToOrderData() {
                const searchTerm = document.getElementById('search-input').value;
                const stageTerm = document.getElementById('stage-filter').value;
                
                fetch(`/to-order-analysis?search=${searchTerm}&stage=${stageTerm}&showNR=${showNR ? 1 : 0}&showLATER=${showLATER ? 1 : 0}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(res => res.text())
                .then(html => {
                    tableBody.innerHTML = html;
                    attachEditableListeners();
                    attachStageListeners();
                })
                .catch(err => console.error('Error refreshing data:', err));
            }
        </script>
    @endsection
@endif
