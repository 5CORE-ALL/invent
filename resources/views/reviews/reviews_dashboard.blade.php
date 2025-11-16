@extends('layouts.vertical', ['title' => 'Product Marketing', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .parent-row {
            background-color: #e3f2fd !important;
        }

        .add-review-btn {
            background: none;
            border: none;
            color: #28a745;
            font-size: 18px;
            cursor: pointer;
            padding: 0 5px;
        }

        .add-review-btn:hover {
            color: #218838;
        }

        .add-review-btn.has-reviews {
            color: #3907ff !important;
        }

        .add-review-btn.has-reviews:hover {
             color: #3907ff !important;
        }

        .star-rating {
            display: inline-flex;
            gap: 3px;
            direction: ltr;
        }

        .star-rating input[type="radio"] {
            display: none;
        }

        .star-rating label {
            font-size: 28px;
            color: #ddd;
            cursor: pointer;
            position: relative;
        }

        .star-rating label:hover,
        .star-rating label.selected {
            color: #ffc107;
        }

        .star-rating label.half-selected::before {
            content: '★';
            position: absolute;
            left: 0;
            color: #ffc107;
            overflow: hidden;
            width: 50%;
        }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4>Product Data</h4>

                        <!-- 🔍 Filter Controls -->
                        <div class="d-flex gap-2">
                            <select id="filter-parent" class="form-select" style="width:180px;">
                                <option value="">Filter by Parent</option>
                            </select>

                            <select id="filter-sku" class="form-select" style="width:180px;">
                                <option value="">Filter by SKU</option>
                            </select>

                            <button id="clear-filters" class="btn btn-sm btn-secondary">
                                <i class="fa fa-times"></i> Clear Filters
                            </button>
                        </div>
                    </div>

                    <div class="card-body">
                        <div id="reviews-table"></div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Add Review Modal -->
    <div class="modal fade" id="addReviewModal" tabindex="-1" aria-labelledby="addReviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addReviewModalLabel">Add Review</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Existing Reviews Section -->
                    <div id="existingReviewsSection" style="display: none;">
                        <h6 class="mb-3">Existing Reviews</h6>
                        <div id="existingReviewsList" class="mb-4"></div>
                        <hr>
                    </div>

                    <form id="addReviewForm">
                        <input type="hidden" id="review_sku" name="sku">
                        <input type="hidden" id="review_channel" name="channel">
                        <input type="hidden" id="review_data" name="review_data">

                        <div class="mb-3">
                            <label class="form-label"><strong>SKU:</strong> <span id="display_sku"></span></label>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><strong>Channel:</strong> <span id="display_channel"></span></label>
                        </div>

                        <div class="mb-3">
                            <label for="review_link" class="form-label">Review Link</label>
                            <input type="url" class="form-control" id="review_link" name="link" placeholder="https://example.com/review">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Rating (0.5 - 5 stars)</label>
                            <div class="star-rating" id="starRating">
                                <label data-value="1">★</label>
                                <label data-value="2">★</label>
                                <label data-value="3">★</label>
                                <label data-value="4">★</label>
                                <label data-value="5">★</label>
                            </div>
                            <input type="hidden" id="rating_value" name="rating" value="">
                            <small class="text-muted d-block mt-2">Click left side for half star, right side for full star. Selected: <strong id="rating_display">0</strong></small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveReview">Save Review</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
    <script>
        $(document).ready(function() {
            // ✅ Initialize Tabulator
            const table = new Tabulator("#reviews-table", {
                ajaxURL: "channels-reviews/details",
                layout: "fitData",
                pagination: true,
                paginationSize: 50,

                rowFormatter: function(row) {
                    const data = row.getData();
                    const sku = data["SKU"] || '';

                    if (sku.toUpperCase().includes("PARENT")) {
                        row.getElement().classList.add("parent-row");
                    }
                },
                columns: [{
                        title: "Parent",
                        field: "Parent",
                        hozAlign: "center"


                    },
                    {
                        title: "SKU",
                        field: "SKU",
                        hozAlign: "center"
                    },
                    {
                        title: "Shopify INV",
                        field: "Shopify_INV",
                        hozAlign: "center"
                    },
                    {
                        title: "OVL3",
                        field: "OVL3",
                        hozAlign: "center"
                    },
                    {
                        title: "Dil",
                        field: "Dil",
                        hozAlign: "center",
                        formatter: function(cell, formatterParams, onRendered) {
                            const rowData = cell.getRow().getData();
                            const value = cell.getValue();
                            const style = rowData.Dil_Style || '';

                            return `<span style="${style}">${value}%</span>`;
                        }
                    },
                    {
                        title: "Amazon Reviews",
                        field: "Amazon_Reviews",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const value = cell.getValue();
                            const hasReviews = (value && value !== '-') ? 'has-reviews' : '';
                            return `
                                <button class="add-review-btn ${hasReviews}" onclick="openReviewModal('${rowData.SKU}', 'Amazon')" title="View/Add Reviews">
                                    <i class="fa fa-plus-circle"></i>
                                </button>
                            `;
                        }
                    },
                    {
                        title: "Ebay 1 Reviews",
                        field: "Ebay_One_Reviews",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const value = cell.getValue();
                            const hasReviews = (value && value !== '-') ? 'has-reviews' : '';
                            return `
                                <button class="add-review-btn ${hasReviews}" onclick="openReviewModal('${rowData.SKU}', 'Ebay One')" title="View/Add Reviews">
                                    <i class="fa fa-plus-circle"></i>
                                </button>
                            `;
                        }
                    },
                    {
                        title: "Ebay 2 Reviews",
                        field: "Ebay_Two_Reviews",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const value = cell.getValue();
                            const hasReviews = (value && value !== '-') ? 'has-reviews' : '';
                            return `
                                <button class="add-review-btn ${hasReviews}" onclick="openReviewModal('${rowData.SKU}', 'Ebay Two')" title="View/Add Reviews">
                                    <i class="fa fa-plus-circle"></i>
                                </button>
                            `;
                        }
                    },
                    {
                        title: "Ebay 3 Reviews",
                        field: "Ebay_Three_Reviews",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const value = cell.getValue();
                            const hasReviews = (value && value !== '-') ? 'has-reviews' : '';
                            return `
                                <button class="add-review-btn ${hasReviews}" onclick="openReviewModal('${rowData.SKU}', 'Ebay Three')" title="View/Add Reviews">
                                    <i class="fa fa-plus-circle"></i>
                                </button>
                            `;
                        }
                    },
                    {
                        title: "Temu Reviews",
                        field: "Temu_Reviews",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const value = cell.getValue();
                            const hasReviews = (value && value !== '-') ? 'has-reviews' : '';
                            return `
                                <button class="add-review-btn ${hasReviews}" onclick="openReviewModal('${rowData.SKU}', 'Temu')" title="View/Add Reviews">
                                    <i class="fa fa-plus-circle"></i>
                                </button>
                            `;
                        }
                    }
                ],
                ajaxResponse: function(url, params, response) {
                    // ✅ Populate dropdown filters dynamically
                    let parentSet = new Set();
                    let skuSet = new Set();

                    response.forEach(row => {
                        if (row.Parent && row.Parent !== '-') parentSet.add(row.Parent);
                        if (row.SKU && row.SKU !== '-') skuSet.add(row.SKU);
                    });

                    const parentSelect = $("#filter-parent");
                    const skuSelect = $("#filter-sku");

                    // Empty previous options (except first)
                    parentSelect.find("option:not(:first)").remove();
                    skuSelect.find("option:not(:first)").remove();

                    [...parentSet].sort().forEach(p => parentSelect.append(
                        `<option value="${p}">${p}</option>`));
                    [...skuSet].sort().forEach(s => skuSelect.append(
                        `<option value="${s}">${s}</option>`));

                    return response; // return for rendering
                }
            });

            // ✅ Parent filter
            $("#filter-parent").on("change", function() {
                const val = $(this).val();
                table.setFilter("Parent", "like", val);
            });

            // ✅ SKU filter
            $("#filter-sku").on("change", function() {
                const val = $(this).val();
                table.setFilter("SKU", "like", val);
            });

            // ✅ Clear filters
            $("#clear-filters").on("click", function() {
                $("#filter-parent").val("");
                $("#filter-sku").val("");
                table.clearFilter();
            });
        });

        // Open Review Modal
        function openReviewModal(sku, channel) {
            $('#review_sku').val(sku);
            $('#review_channel').val(channel);
            $('#display_sku').text(sku);
            $('#display_channel').text(channel);
            
            // Reset form
            $('#review_link').val('');
            $('#rating_value').val('');
            $('#rating_display').text('0');
            $('.star-rating label').removeClass('selected half-selected');
            
            // Get existing reviews for this SKU and channel
            const table = Tabulator.findTable("#reviews-table")[0];
            const rows = table.getData();
            const row = rows.find(r => r.SKU === sku);
            
            if (row) {
                const channelFieldMap = {
                    'Amazon': 'Amazon_Reviews',
                    'Ebay One': 'Ebay_One_Reviews',
                    'Ebay Two': 'Ebay_Two_Reviews',
                    'Ebay Three': 'Ebay_Three_Reviews',
                    'Temu': 'Temu_Reviews'
                };
                
                const fieldName = channelFieldMap[channel];
                const reviewData = row[fieldName];
                
                $('#review_data').val(reviewData);
                
                // Display existing reviews
                if (reviewData && reviewData !== '-') {
                    try {
                        const reviews = JSON.parse(reviewData);
                        if (Array.isArray(reviews) && reviews.length > 0) {
                            let html = '<div class="list-group">';
                            reviews.forEach((review, index) => {
                                const stars = '★'.repeat(Math.floor(review.rating)) + (review.rating % 1 !== 0 ? '☆' : '');
                                html += `
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <strong>Review ${index + 1}</strong>
                                                <div class="text-warning">${stars} (${review.rating})</div>
                                                <a href="${review.link}" target="_blank" class="small">${review.link}</a>
                                                <div class="text-muted small">${review.date}</div>
                                            </div>
                                        </div>
                                    </div>
                                `;
                            });
                            html += '</div>';
                            $('#existingReviewsList').html(html);
                            $('#existingReviewsSection').show();
                        } else {
                            $('#existingReviewsSection').hide();
                        }
                    } catch (e) {
                        $('#existingReviewsSection').hide();
                    }
                } else {
                    $('#existingReviewsSection').hide();
                }
            }
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('addReviewModal'));
            modal.show();
        }

        // Star Rating Click Handler
        $(document).on('click', '.star-rating label', function(e) {
            const $label = $(this);
            const starValue = parseFloat($label.data('value'));
            const clickX = e.pageX - $label.offset().left;
            const labelWidth = $label.width();
            
            // Determine if click was on left half (half star) or right half (full star)
            let rating;
            if (clickX < labelWidth / 2) {
                // Left half clicked - half star
                rating = starValue - 0.5;
            } else {
                // Right half clicked - full star
                rating = starValue;
            }
            
            // Update hidden input and display
            $('#rating_value').val(rating);
            $('#rating_display').text(rating);
            
            // Update visual stars
            $('.star-rating label').removeClass('selected half-selected');
            
            $('.star-rating label').each(function() {
                const value = parseFloat($(this).data('value'));
                if (value <= Math.floor(rating)) {
                    $(this).addClass('selected');
                } else if (value === Math.ceil(rating) && rating % 1 !== 0) {
                    $(this).addClass('half-selected');
                }
            });
        });

        // Save Review
        $('#saveReview').on('click', function() {
            const sku = $('#review_sku').val();
            const channel = $('#review_channel').val();
            const link = $('#review_link').val();
            const rating = $('#rating_value').val();

            if (!link) {
                alert('Please enter a review link');
                return;
            }

            if (!rating) {
                alert('Please select a rating');
                return;
            }

            // Send AJAX request to save review
            $.ajax({
                url: '/channels-reviews/save',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    sku: sku,
                    channel: channel,
                    link: link,
                    rating: rating
                },
                success: function(response) {
                    alert('Review saved successfully!');
                    $('#addReviewModal').modal('hide');
                    
                    // Reload table data
                    const table = Tabulator.findTable("#reviews-table")[0];
                    if (table) {
                        table.setData();
                    }
                },
                error: function(xhr) {
                    alert('Error saving review: ' + (xhr.responseJSON?.message || 'Unknown error'));
                }
            });
        });
    </script>
@endsection
