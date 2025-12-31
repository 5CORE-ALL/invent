@extends('layouts.vertical', ['title' => 'Ready To Ship', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
<style>
    .custom-select-wrapper {
        position: relative;
        font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
    }
    .custom-select-box {
        transition: border-color 0.18s, box-shadow 0.18s;
        background: #fff;
    }
    .custom-select-box.active, .custom-select-box:focus-within {
        border-color: #3bc0c3;
        box-shadow: 0 0 0 2px #3bc0c340;
    }
    .custom-select-dropdown {
        animation: fadeIn 0.18s;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-8px);}
        to { opacity: 1; transform: translateY(0);}
    }
    .custom-select-option {
        cursor: pointer;
        font-size: 1rem;
        transition: background 0.13s, color 0.13s;
        margin: 0 4px;
        color: #222;
        background: #fff;
        border-radius: 6px;
        user-select: none;
    }
    .custom-select-option.selected,
    .custom-select-option:hover,
    .custom-select-option.bg-primary {
        background: #3bc0c3 !important;
        color: #fff !important;
    }
    .custom-select-option:not(:last-child) {
        margin-bottom: 2px;
    }
    .custom-select-dropdown::-webkit-scrollbar {
        width: 7px;
        background: #f4f6fa;
        border-radius: 6px;
    }
    .custom-select-dropdown::-webkit-scrollbar-thumb {
        background: #e0e6ed;
        border-radius: 6px;
    }
    .preview-popup {
        position: fixed;
        display: none;
        z-index: 9999;
        pointer-events: none;
        width: 350px;
        height: 350px;
        object-fit: cover;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        transition: all 0.2s ease;
    }
</style>
@endsection
@section('content')
@include('layouts.shared.page-title', ['page_title' => 'Ready To Ship', 'sub_title' => 'Ready To Ship'])
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <!-- Filters Row - First Row -->
                <div class="column-controls card mb-3 p-3 shadow-sm" id="columnControls" style="background: #f8f9fa; border-radius: 10px;">
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <!-- Navigation -->
                        <div class="col-auto">
                            <label class="form-label fw-semibold mb-1 d-block">▶️ Navigation</label>
                            <div class="btn-group time-navigation-group" role="group">
                                <button id="play-backward" class="btn btn-light rounded-circle shadow-sm me-2" title="Previous supplier">
                                    <i class="fas fa-step-backward"></i>
                                </button>
                                <button id="play-pause" class="btn btn-light rounded-circle shadow-sm me-2" style="display: none;" title="Pause">
                                    <i class="fas fa-pause"></i>
                                </button>
                                <button id="play-auto" class="btn btn-primary rounded-circle shadow-sm me-2" title="Play">
                                    <i class="fas fa-play"></i>
                                </button>
                                <button id="play-forward" class="btn btn-light rounded-circle shadow-sm me-2" title="Next supplier">
                                    <i class="fas fa-step-forward"></i>
                                </button>
                                <button id="supplier-remarks-btn" class="btn btn-success shadow-sm" style="border-radius: 6px; margin-left: 8px;" title="Supplier Remarks/Updates">
                                    <i class="fas fa-comment-alt"></i> Remarks
                                </button>
                            </div>
                        </div>

                        <!-- Search Table -->
                        <div class="col-auto">
                            <label class="form-label fw-semibold mb-1 d-block" style="visibility: hidden;">Search</label>
                            <input type="text" class="form-control" id="wholeSearchInput"
                                placeholder="🔍 Search entire table..."
                                style="width: 200px; font-size: 0.97rem; height: 36px; border-radius: 6px;">
                        </div>

                        <!-- Toggle Columns Dropdown -->
                        <div class="col-auto">
                            <label class="form-label fw-semibold mb-1 d-block" style="visibility: hidden;">Columns</label>
                            <div class="column-dropdown position-relative">
                                <button class="btn text-white column-dropdown-btn d-flex align-items-center gap-1" id="columnDropdownBtn" style="border-radius: 6px;">
                                    <i class="mdi mdi-format-columns"></i> Toggle Columns
                                </button>
                                <div class="column-dropdown-content" id="columnDropdownContent"
                                    style="position: absolute; left: 0; top: 110%; min-width: 220px; z-index: 20; background: #fff; box-shadow: 0 2px 12px rgba(60,192,195,0.10); border-radius: 8px; border: 1px solid #e3e3e3; padding: 12px; max-height: 350px; overflow-y: auto;">
                                    <!-- Dynamic Checkboxes -->
                                </div>
                            </div>
                        </div>

                        <!-- Show All Columns -->
                        <div class="col-auto">
                            <label class="form-label fw-semibold mb-1 d-block" style="visibility: hidden;">Show All</label>
                            <button class="btn text-white show-all-columns d-flex align-items-center gap-1" id="showAllColumns" style="border-radius: 6px;">
                                <i class="mdi mdi-eye-check-outline"></i> Show All
                            </button>
                        </div>

                        <!-- Zone Filter -->
                        <div class="col-auto">
                            <label class="form-label fw-semibold mb-1 d-block" style="visibility: hidden;">Zone</label>
                            <select id="zoneFilter" class="form-select border-2 rounded-2 fw-bold" style="min-width: 120px;">
                                <option value="">select zone</option>
                                <option value="GHZ">GHZ</option>
                                <option value="Ningbo">Ningbo</option>
                                <option value="Tianjin">Tianjin</option>
                            </select>
                        </div>

                        <!-- Supplier Dropdown -->
                        <div class="col-auto">
                            <label class="form-label fw-semibold mb-1 d-block" style="visibility: hidden;">Supplier</label>
                            <div class="custom-select-wrapper" style="min-width: 150px; position: relative;">
                                <div class="custom-select-box d-flex align-items-center justify-content-between" id="customSelectBox"
                                    style="border: 1.5px solid #e0e6ed; border-radius: 7px; background: #fff; height: 38px; padding: 0 14px; cursor: pointer; box-shadow: 0 1px 4px rgba(60,192,195,0.07); transition: border-color 0.2s;">
                                    <span id="customSelectSelectedText" class="flex-grow-1 text-truncate" style="font-size: 1rem; color: #222;">All supplier</span>
                                    <i class="mdi mdi-menu-down" style="font-size: 1.3rem; color: #3bc0c3;"></i>
                                </div>
                                <div class="custom-select-dropdown shadow" id="customSelectDropdown"
                                    style="display: none; position: absolute; z-index: 30; background: #fff; min-width: 220px; max-width: 320px; border-radius: 10px; border: 1.5px solid #e0e6ed; margin-top: 4px;">
                                    <div class="p-2 border-bottom" style="background: #f8f9fa; border-radius: 10px 10px 0 0;">
                                        <input type="text" class="form-control border-0 shadow-none" id="customSelectSearchInput"
                                            placeholder="🔍 Search supplier..." style="font-size: 0.97rem; height: 32px; border-radius: 6px; background: #f4f6fa;">
                                    </div>
                                    <div id="customSelectOptions" style="max-height: 160px; overflow-y: auto; padding: 2px 0;">
                                        <div class="custom-select-option px-3 py-2 rounded" data-value="">All supplier</div>
                                        <div class="custom-select-option px-3 py-2 rounded" data-value="__all_suppliers__" style="font-weight: 600; border-bottom: 1px solid #e0e6ed; margin-bottom: 4px;">Supplier</div>
                                        @foreach ($suppliers as $item)
                                            <div class="custom-select-option px-3 py-2 rounded" data-value="">{{ $item }}</div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 💰 Advance + Pending Summary -->
                        <div class="col-auto">
                            <label class="form-label fw-semibold mb-1 d-block" style="visibility: hidden;">Advance</label>
                            <div id="advance-total-wrapper" style="display: none;">
                                <div id="advance-total-display" class="py-1 px-2 rounded shadow-sm d-inline-flex align-items-center gap-2 flex-wrap"
                                    style="background: linear-gradient(90deg, #e6f4f1 60%, #f8f9fa 100%); color: #10635b; font-weight: 600; font-size: 16px; border: 1.5px solid #3bc0c3; box-shadow: 0 2px 8px rgba(60,192,195,0.08); transition: all 0.3s ease;">
                                    <div class="d-flex align-items-center gap-2" style="min-width: 170px;">
                                        <span class="rounded-circle d-flex align-items-center justify-content-center" style="background: #d1f2eb; width: 36px; height: 36px;">
                                            <i class="mdi mdi-cash-multiple" style="font-size: 22px; color: #10b39c;"></i>
                                        </span>
                                        <span>
                                            <span style="font-size: 13px; color: #23979b;">Total Advance</span><br>
                                            <span style="font-size: 18px; color: #10635b;">$ <span id="advance-amount">0</span></span>
                                        </span>
                                    </div>

                                    <div class="vr" style="height: 38px; width: 2px; background: #cde7e2; margin: 0 18px;"></div>

                                    <div class="d-flex align-items-center gap-2" style="min-width: 170px;">
                                        <span class="rounded-circle d-flex align-items-center justify-content-center" style="background: #ffeaea; width: 36px; height: 36px;">
                                            <i class="mdi mdi-alert-decagram" style="font-size: 22px; color: #ff6b6b;"></i>
                                        </span>
                                        <span>
                                            <span style="font-size: 13px; color: #ff6b6b;">Total Pending</span><br>
                                            <span style="font-size: 18px; color: #b23c3c;">$ <span id="pending-amount">0</span></span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="col-auto">
                            <label class="form-label fw-semibold mb-1 d-block" style="visibility: hidden;">Actions</label>
                            <div class="d-flex align-items-center gap-2">
                                <button class="btn btn-info move-to-transit-btn d-none" style="border-radius: 6px;">
                                    <i class="mdi mdi-truck-fast"></i> Move to Transit INV
                                </button>
                                <button id="delete-selected-btn" class="btn btn-primary text-black d-none" style="border-radius: 6px;">
                                    <i class="mdi mdi-backup-restore"></i> Revert to MFRG
                                </button>
                                <button id="delete-selected-item" class="btn btn-danger d-none" style="border-radius: 6px;">
                                    <i class="mdi mdi-trash-can"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Counts/Stats Row - Second Row -->
                <div class="card mb-4 shadow-sm border-0" style="background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between" style="flex-wrap: nowrap; gap: 0; overflow-x: auto;">
                            <div class="text-center flex-fill" style="min-width: 110px;">
                                <div class="text-muted mb-1" style="font-size: 0.75rem; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase;">
                                    💰 Total Amount
                                </div>
                                <div id="total-amount" class="fw-bold text-dark" style="font-size: 2.5rem; line-height: 1.2; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
                                    0
                                </div>
                            </div>
                            <div class="vr mx-3" style="height: 50px; width: 1px; background: linear-gradient(to bottom, transparent, #dee2e6, transparent);"></div>
                            <div class="text-center flex-fill" style="min-width: 110px;">
                                <div class="text-muted mb-1" style="font-size: 0.75rem; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase;">
                                    📦 Total Ord. Qty
                                </div>
                                <div id="total-order-qty" class="fw-bold text-info" style="font-size: 2.5rem; line-height: 1.2; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
                                    0
                                </div>
                            </div>
                            <div class="vr mx-3" style="height: 50px; width: 1px; background: linear-gradient(to bottom, transparent, #dee2e6, transparent);"></div>
                            <div class="text-center flex-fill" style="min-width: 110px;">
                                <div class="text-muted mb-1" style="font-size: 0.75rem; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase;">
                                    📊 Total CBM
                                </div>
                                <div id="total-cbm" class="fw-bold text-success" style="font-size: 2.5rem; line-height: 1.2; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
                                    0
                                </div>
                            </div>
                            <div class="vr mx-3" style="height: 50px; width: 1px; background: linear-gradient(to bottom, transparent, #dee2e6, transparent);"></div>
                            <div class="text-center flex-fill" style="min-width: 110px;">
                                <div class="text-muted mb-1" style="font-size: 0.75rem; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase;">
                                    🔢 Total Items
                                </div>
                                <div id="total-order-items" class="fw-bold text-warning" style="font-size: 2.5rem; line-height: 1.2; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
                                    0
                                </div>
                            </div>
                            <div class="vr mx-3" style="height: 50px; width: 1px; background: linear-gradient(to bottom, transparent, #dee2e6, transparent);"></div>
                            <div class="text-center flex-fill" style="min-width: 110px;">
                                <div class="text-muted mb-1" style="font-size: 0.75rem; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase;">
                                    📦 Total CTN CBM
                                </div>
                                <div id="total-ctn-cbm" class="fw-bold text-primary" style="font-size: 2.5rem; line-height: 1.2; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
                                    0
                                </div>
                            </div>
                            <div class="vr mx-3" style="height: 50px; width: 1px; background: linear-gradient(to bottom, transparent, #dee2e6, transparent);"></div>
                            <div class="text-center flex-fill" style="min-width: 110px;">
                                <div class="text-muted mb-1" style="font-size: 0.75rem; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase;">
                                    👥 Follow Supplier
                                </div>
                                <div id="followSupplierCount" class="fw-bold text-danger" style="font-size: 2.5rem; line-height: 1.2; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #dc3545;">
                                    0
                                </div>
                            </div>
                            <div class="vr mx-3" style="height: 50px; width: 1px; background: linear-gradient(to bottom, transparent, #dee2e6, transparent); display: none;" id="supplier-badge-vr"></div>
                            <div class="text-center flex-fill" style="min-width: 140px; display: none;" id="supplier-badge-container">
                                <div class="text-muted mb-1" style="font-size: 0.75rem; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase;">
                                    🏭 Current Supplier
                                </div>
                                <div id="current-supplier" class="fw-bold text-white" style="font-size: 2.5rem; line-height: 1.2; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #28a745; padding: 8px 16px; border-radius: 6px; display: inline-block; min-width: 120px; word-break: break-word;">
                                    -
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Supplier Remarks Modal -->
                <div class="modal fade" id="supplierRemarksModal" tabindex="-1" aria-labelledby="supplierRemarksModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-success text-white">
                                <h5 class="modal-title" id="supplierRemarksModalLabel">
                                    <i class="fas fa-comment-alt"></i> Supplier Remarks/Updates
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Current Supplier:</label>
                                    <div id="modal-supplier-name" class="badge bg-success fs-6 p-2" style="font-size: 1rem !important;">
                                        -
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="supplier-remark-input" class="form-label fw-bold">Add New Remark/Update:</label>
                                    <textarea class="form-control" id="supplier-remark-input" rows="3" placeholder="Enter your remark or update here..."></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Saved Remarks/Updates:</label>
                                    <div id="remarksList" class="border rounded p-3" style="max-height: 300px; overflow-y: auto; background: #f8f9fa;">
                                        <p class="text-muted mb-0">No remarks yet.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="button" class="btn btn-success" id="saveRemarkBtn">
                                    <i class="fas fa-save"></i> Save Remark
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="wide-table-wrapper table-container">
                    <table class="wide-table">
                        <thead>
                            <tr>
                                <th data-column="0" style="width: 50px;">
                                    <input type="checkbox" id="selectAllCheckbox" title="Select All">
                                    <div class="resizer"></div>
                                </th>
                                <th data-column="7" data-column-name="area">ZONE<div class="resizer"></div>
                                </th>
                                <th data-column="1">Image<div class="resizer"></div></th>
                                <th data-column="2" hidden>
                                    Parent
                                    <div class="resizer"></div>
                                    <input type="text" class="form-control column-search" data-search-column="2"
                                        placeholder="Search Parent..."
                                        style="margin-top:4px; font-size:12px; height:28px; width: 120px;">
                                    <div class="search-results" data-results-column="2"
                                        style="position:relative; z-index:10;"></div>
                                </th>
                                <th data-column="3">
                                    SKU
                                    <div class="resizer"></div>
                                    <input type="text" class="form-control column-search" data-search-column="3"
                                        placeholder="Search SKU..."
                                        style="margin-top:4px; font-size:12px; height:28px; width: 120px;">
                                    <div class="search-results" data-results-column="3"
                                        style="position:relative; z-index:10;"></div>
                                </th>
                                <th data-column="21" data-column-name="stage" class="text-center">Stage<div class="resizer"></div></th>
                                <th data-column="22" data-column-name="nr" class="text-center">NRP<div class="resizer"></div></th>
                                <th data-column="4" data-column-name="qty" class="text-center">Or. QTY<div class="resizer"></div></th>
                                <th data-column="20" data-column-name="rec_qty" class="text-center">Rec. QTY<div class="resizer"></div></th>
                                <th data-column="18" data-column-name="qty" class="text-center" hidden>Rate<div class="resizer"></div></th>
                                <th data-column="5" data-column-name="supplier">Supplier<div class="resizer"></div>
                                </th>
                                <th data-column="6" data-column-name="cbm" hidden>CBM<div class="resizer"></div>
                                </th>
                                <th data-column="19" data-column-name="total_cbm">Total CBM<div class="resizer"></div>
                                </th>
                                <th data-column="8" data-column-name="shipped_cbm_in_container" hidden>Balance<div
                                        class="resizer"></div>
                                </th>
                                <th data-column="9" data-column-name="payment" hidden>Payment<div class="resizer"></div>
                                </th>
                                <th data-column="10" data-column-name="pay_term">Pay<br/>Term<div class="resizer"></div>
                                </th>
                                <th data-column="11" data-column-name="payment_confirmation">Payment<br/>Confirmation<div
                                        class="resizer"></div>
                                </th>
                                <th data-column="12" data-column-name="model_number" hidden>Model<br/>Number<div class="resizer">
                                    </div>
                                </th>
                                <th data-column="13" data-column-name="photo_mail_send">Photo Mail<br/>Send<div
                                        class="resizer"></div>
                                </th>
                                <th data-column="14" data-column-name="followup_delivery">Followup<br/>Delivery<div
                                        class="resizer"></div>
                                </th>
                                <th data-column="15" data-column-name="packing_list">Packing<br/>List<div class="resizer">
                                    </div>
                                </th>
                                <th data-column="16" data-column-name="container_rfq" hidden>Container<br/>RFQ<div class="resizer">
                                    </div>
                                </th>
                                <th data-column="17" data-column-name="quote_result" hidden>Quote<br/>Result<div class="resizer">
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($readyToShipList as $item)
                                @php
                                    $nrValue = strtoupper(trim($item->nr ?? ''));
                                @endphp
                                @continue($nrValue === 'NR')
                            <tr data-stage="{{ $item->stage ?? '' }}" class="stage-row">
                                <td data-column="0">
                                    <input type="checkbox" class="row-checkbox" data-sku="{{ $item->sku }}">
                                </td>
                                <td data-column="7">
                                    <select data-sku="{{ $item->sku }}" data-column="area" class="form-select form-select-sm auto-save" style="width: 90px; font-size: 13px;">
                                        <option value="">select zone</option>
                                        <option value="GHZ" {{ ($item->area ?? '') == 'GHZ' ? 'selected' : '' }}>GHZ</option>
                                        <option value="Ningbo" {{ ($item->area ?? '') == 'Ningbo' ? 'selected' : '' }}>Ningbo</option>
                                        <option value="Tianjin" {{ ($item->area ?? '') == 'Tianjin' ? 'selected' : '' }}>Tianjin</option>
                                    </select>
                                </td>
                                <td data-column="1">
                                    @if(!empty($item->Image))
                                        @php
                                            // Check if it's a storage path or full URL
                                            $imageUrl = $item->Image;
                                            if (strpos($imageUrl, 'storage/') === 0 || strpos($imageUrl, '/storage/') === 0) {
                                                $imageUrl = asset($imageUrl);
                                            } elseif (strpos($imageUrl, 'http') !== 0 && strpos($imageUrl, '//') !== 0) {
                                                // If it's a relative path, make it absolute
                                                $imageUrl = asset($imageUrl);
                                            }
                                        @endphp
                                        <img src="{{ $imageUrl }}" class="hover-img" data-src="{{ $imageUrl }}" alt="Image" style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px;" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';">
                                        <span class="text-muted" style="display: none;">No</span>
                                    @else
                                        <span class="text-muted">No</span>
                                    @endif
                                </td>
                                
                                <td data-column="2" class="text-center" hidden>{{ $item->parent }}</td>
                                <td data-column="3" class="text-center">{{ $item->sku }}</td>
                                <td data-column="21" class="text-center">
                                    @php
                                        $stageValue = $item->stage ?? '';
                                        $bgColor = '#fff';
                                        if ($stageValue === 'to_order_analysis') {
                                            $bgColor = '#ffc107'; // Yellow
                                        } elseif ($stageValue === 'mip') {
                                            $bgColor = '#0d6efd'; // Blue
                                        } elseif ($stageValue === 'r2s') {
                                            $bgColor = '#198754'; // Green
                                        }
                                    @endphp
                                    <select class="form-select form-select-sm editable-select-stage" 
                                        data-type="Stage"
                                        data-sku="{{ $item->sku }}"
                                        data-parent="{{ $item->parent ?? '' }}"
                                        style="width: auto; min-width: 100px; padding: 4px 24px 4px 8px;
                                            font-size: 0.875rem; border-radius: 4px; border: 1px solid #dee2e6;
                                            background-color: {{ $bgColor }}; color: #000;">
                                        <option value="">Select</option>
                                        <option value="appr_req" {{ $stageValue === 'appr_req' ? 'selected' : '' }}>Appr. Req</option>
                                        <option value="mip" {{ $stageValue === 'mip' ? 'selected' : '' }}>MIP</option>
                                        <option value="r2s" {{ $stageValue === 'r2s' ? 'selected' : '' }}>R2S</option>
                                        <option value="transit" {{ $stageValue === 'transit' ? 'selected' : '' }}>Transit</option>
                                        <option value="all_good" {{ $stageValue === 'all_good' ? 'selected' : '' }}>😊 All Good</option>
                                        <option value="to_order_analysis" {{ $stageValue === 'to_order_analysis' ? 'selected' : '' }}>2 Order</option>
                                    </select>
                                </td>
                                <td data-column="22" class="text-center">
                                    @php
                                        $nrValue = strtoupper(trim($item->nr ?? ''));
                                        $bgColor = '#ffffff';
                                        $textColor = '#000000';
                                        if (!$nrValue || $nrValue === '') {
                                            $nrValue = 'REQ';
                                        }
                                        // Normalize value to match expected values
                                        if ($nrValue !== 'REQ' && $nrValue !== 'NR' && $nrValue !== 'LATER') {
                                            $nrValue = 'REQ'; // Default to REQ if value doesn't match
                                        }
                                        if ($nrValue === 'NR') {
                                            $bgColor = '#dc3545';
                                            $textColor = '#ffffff';
                                        } else if ($nrValue === 'REQ') {
                                            $bgColor = '#28a745';
                                            $textColor = '#000000';
                                        } else if ($nrValue === 'LATER') {
                                            $bgColor = '#ffc107';
                                            $textColor = '#000000';
                                        }
                                    @endphp
                                    <select class="form-select form-select-sm editable-select-nrp" 
                                        data-type="NR"
                                        data-sku="{{ $item->sku }}"
                                        data-parent="{{ $item->parent ?? '' }}"
                                        style="width: auto; min-width: 85px; padding: 4px 8px;
                                            font-size: 0.875rem; border-radius: 4px; border: 1px solid #dee2e6;
                                            background-color: {{ $bgColor }}; color: {{ $textColor }};">
                                        <option value="REQ" {{ $nrValue === 'REQ' ? 'selected' : '' }}>REQ</option>
                                        <option value="NR" {{ $nrValue === 'NR' ? 'selected' : '' }}>2BDC</option>
                                        <option value="LATER" {{ $nrValue === 'LATER' ? 'selected' : '' }}>LATER</option>
                                    </select>
                                </td>
                                <td data-column="4" class="text-center" style="background-color: #e9ecef;">
                                    <input type="number" 
                                        value="{{ $item->qty }}" 
                                        readonly
                                        style="width:80px; text-align:center; background-color: #e9ecef; cursor: not-allowed; border: none;"
                                        class="form-control form-control-sm">
                                </td>
                                <td data-column="20" class="text-center">
                                    <input type="number" 
                                           class="form-control auto-save" 
                                           data-sku="{{ $item->sku }}" 
                                           data-column="rec_qty" 
                                           value="{{ $item->rec_qty }}" 
                                           min="0"
                                           max="10000"
                                           style="font-size: 0.95rem; height: 36px; width: 90px;">
                                </td>
                                <td data-column="18" hidden>
                                    <input type="number" 
                                           class="form-control auto-save" 
                                           data-sku="{{ $item->sku }}" 
                                           data-column="rate" 
                                           value="{{ $item->rate }}" 
                                           min="0"
                                           max="10000"
                                           style="font-size: 0.95rem; height: 36px; width: 90px;">
                                </td>
                                <td data-column="5">
                                    <select data-sku="{{ $item->sku }}" data-column="supplier" class="form-select form-select-sm auto-save" style="min-width: 150px; font-size: 13px;">
                                        <option value="">Select supplier</option>
                                        @foreach ($suppliers as $supplierName)
                                            <option value="{{ $supplierName }}" {{ ($item->supplier ?? '') == $supplierName ? 'selected' : '' }}>
                                                {{ $supplierName }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td data-column="6" hidden>{{ isset($item->CBM) && $item->CBM !== null ? number_format((float)$item->CBM, 4) : 'N/A' }}</td>
                                <td data-column="19">{{ is_numeric($item->qty ?? null) && is_numeric($item->CBM ?? null) ? number_format($item->qty * $item->CBM, 2, '.', '') : '' }}</td>
                                
                                <td data-column="8" hidden>{{ $item->shipped_cbm_in_container }}</td>
                                <td data-column="9" hidden>{{ $item->payment }}</td>
                                <td data-column="10">
                                    <select data-sku="{{ $item->sku }}" data-column="pay_term"
                                        class="form-select form-select-sm auto-save"
                                        style="min-width: 90px; font-size: 13px;">
                                        <option value="EXW" {{ ($item->pay_term ?? '') == 'EXW' ? 'selected' : '' }}>EXW
                                        </option>
                                        <option value="FOB" {{ ($item->pay_term ?? '') == 'FOB' ? 'selected' : '' }}>FOB
                                        </option>
                                    </select>
                                </td>
                                <td data-column="11">
                                    <select data-sku="{{ $item->sku }}" data-column="payment_confirmation"
                                        class="form-select form-select-sm auto-save"
                                        style="min-width: 90px; font-size: 13px;">
                                        <option value="Yes" {{ ($item->payment_confirmation ?? '') == 'Yes' ? 'selected'
                                            : '' }}>Yes</option>
                                        <option value="No" {{ ($item->payment_confirmation ?? '') == 'No' ? 'selected' :
                                            '' }}>No</option>
                                    </select>
                                </td>
                                <td data-column="12" hidden>{{ $item->model_number }}</td>
                                <td data-column="13">{{ $item->photo_mail_send }}</td>
                                <td data-column="14">{{ $item->followup_delivery }}</td>
                                <td data-column="15">{{ $item->packing_list }}</td>
                                <td data-column="16" hidden>{{ $item->container_rfq }}</td>
                                <td data-column="17" hidden>{{ $item->quote_result }}</td>
                                 <td class="total-value d-none">
                                    {{ is_numeric($item->qty ?? null) && is_numeric($item->rate ?? null) ? ($item->qty * $item->rate) : '' }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.body.style.zoom = '85%';

    document.addEventListener('DOMContentLoaded', function() {
        document.documentElement.setAttribute("data-sidenav-size", "condensed");

        // Column resizing functionality
        const resizers = document.querySelectorAll('.resizer');
        resizers.forEach(resizer => resizer.addEventListener('mousedown', initResize));

        // Restore column widths from localStorage
        restoreColumnWidths();

        function saveColumnWidths() {
            const widths = {};
            document.querySelectorAll('.wide-table thead th').forEach(th => {
                const col = th.getAttribute('data-column');
                widths[col] = th.offsetWidth;
            });
            localStorage.setItem('columnWidths_readyToShip', JSON.stringify(widths));
        }

        function restoreColumnWidths() {
            const widths = JSON.parse(localStorage.getItem('columnWidths_readyToShip') || '{}');
            Object.keys(widths).forEach(columnIndex => {
                const th = document.querySelector(`.wide-table thead th[data-column="${columnIndex}"]`);
                if (th) {
                    th.style.width = th.style.minWidth = th.style.maxWidth = widths[columnIndex] + 'px';
                }
            });
        }

        function initResize(e) {
            e.preventDefault();
            const th = e.target.parentElement;
            const startX = e.clientX;
            const startWidth = th.offsetWidth;

            e.target.classList.add('resizing');
            th.style.width = th.style.minWidth = th.style.maxWidth = startWidth + 'px';

            const resize = (e) => {
                const newWidth = startWidth + e.clientX - startX;
                if (newWidth > 80) {
                    th.style.width = th.style.minWidth = th.style.maxWidth = newWidth + 'px';
                }
            };

            const stopResize = () => {
                document.removeEventListener('mousemove', resize);
                document.removeEventListener('mouseup', stopResize);
                e.target.classList.remove('resizing');
                saveColumnWidths();
            };

            document.addEventListener('mousemove', resize);
            document.addEventListener('mouseup', stopResize);
        }

        // Column visibility functionality
        const showAllBtn = document.getElementById('showAllColumns');
        const dropdownBtn = document.getElementById('columnDropdownBtn');
        const dropdownContent = document.getElementById('columnDropdownContent');
        const ths = document.querySelectorAll('.wide-table thead th');

        // Capitalize column names and create checkboxes
        dropdownContent.innerHTML = '';
        ths.forEach((th, i) => {
            const colIndex = i + 1;
            const colName = capitalizeWords((th.textContent || '').trim());
            const item = document.createElement('div');
            item.className = 'column-checkbox-item';

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.id = `column-${colIndex}`;
            checkbox.className = 'column-checkbox';
            checkbox.setAttribute('data-column', colIndex);

            const label = document.createElement('label');
            label.htmlFor = `column-${colIndex}`;
            label.innerHTML = `${colName} <i class="mdi mdi-eye text-primary"></i>`;

            item.appendChild(checkbox);
            item.appendChild(label);
            dropdownContent.appendChild(item);
        });

        // Restore hidden columns from localStorage
        const hiddenColumns = getHiddenColumns();
        document.querySelectorAll('.column-checkbox').forEach(checkbox => {
            const columnIndex = checkbox.getAttribute('data-column');
            const th = document.querySelector(`.wide-table thead th[data-column="${columnIndex}"]`);
            if (!th) return;
            const label = document.querySelector(`label[for="column-${columnIndex}"]`);
            const colName = capitalizeWords((th.textContent || '').trim());

            checkbox.checked = !hiddenColumns.includes(columnIndex);
            document.querySelectorAll(`[data-column="${columnIndex}"]`).forEach(cell => {
                cell.style.display = checkbox.checked ? '' : 'none';
            });
            label.innerHTML = `${colName} <i class="mdi mdi-eye${checkbox.checked ? ' text-primary' : '-off text-muted'}"></i>`;
        });

        // Toggle dropdown
        dropdownBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdownContent.classList.toggle('show');
        });

        // Close dropdown when clicking outside
        window.addEventListener('click', (e) => {
            if (!e.target.matches('.column-dropdown-btn') && !dropdownContent.contains(e.target)) {
                dropdownContent.classList.remove('show');
            }
        });

        // Checkbox change event
        dropdownContent.querySelectorAll('.column-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const columnIndex = this.getAttribute('data-column');
                const th = document.querySelector(`.wide-table thead th[data-column="${columnIndex}"]`);
                if (!th) return;
                const label = document.querySelector(`label[for="column-${columnIndex}"]`);
                const colName = capitalizeWords((th.textContent || '').trim());
                let hidden = getHiddenColumns();

                document.querySelectorAll(`[data-column="${columnIndex}"]`).forEach(cell => {
                    cell.style.display = this.checked ? '' : 'none';
                });

                if (this.checked) {
                    hidden = hidden.filter(c => c !== columnIndex);
                    label.innerHTML = `${colName} <i class="mdi mdi-eye text-primary"></i>`;
                } else {
                    hidden.push(columnIndex);
                    label.innerHTML = `${colName} <i class="mdi mdi-eye-off text-muted"></i>`;
                }
                saveHiddenColumns(hidden);
            });
        });

        // Show all columns functionality
        showAllBtn.addEventListener('click', showAllColumns);

        function showAllColumns() {
            document.querySelectorAll('.column-checkbox').forEach(checkbox => {
                checkbox.checked = true;
                const columnIndex = checkbox.getAttribute('data-column');
                const th = document.querySelector(`.wide-table thead th[data-column="${columnIndex}"]`);
                if (!th) return;
                const label = document.querySelector(`label[for="column-${columnIndex}"]`);
                document.querySelectorAll(`[data-column="${columnIndex}"]`).forEach(cell => {
                    cell.style.display = '';
                });
                label.innerHTML = `${th.childNodes[0].nodeValue.trim()} <i class="mdi mdi-eye text-primary"></i>`;
            });
            saveHiddenColumns([]);
        }

        // Search functionality for the entire table
        const wholeSearchInput = document.getElementById('wholeSearchInput');
        const rows = document.querySelectorAll('.wide-table tbody tr');

        wholeSearchInput.addEventListener('input', filterRows);
        wholeSearchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') filterRows();
        });

        function filterRows() {
            const search = wholeSearchInput.value.trim().toLowerCase();
            rows.forEach(row => {
                // Check if stage is R2S
                const stageSelect = row.querySelector('.editable-select-stage');
                const rowStage = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
                const isR2S = rowStage === 'r2s';
                
                // Only show R2S stage rows
                if (!isR2S) {
                    row.style.display = 'none';
                    return;
                }
                
                const found = Array.from(row.querySelectorAll('td')).some(td => td.textContent.toLowerCase().includes(search));
                row.style.display = found || search === '' ? '' : 'none';
            });
        }

        // Column-specific search functionality
        document.querySelectorAll('.column-search').forEach(input => {
            input.addEventListener('input', function() {
                const col = this.getAttribute('data-search-column');
                const searchValue = this.value.trim().toLowerCase();
                rows.forEach(row => {
                    // Check if stage is R2S
                    const stageSelect = row.querySelector('.editable-select-stage');
                    const rowStage = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
                    const isR2S = rowStage === 'r2s';
                    
                    // Only show R2S stage rows
                    if (!isR2S) {
                        row.style.display = 'none';
                        return;
                    }
                    
                    const cell = row.querySelector(`td[data-column="${col}"]`);
                    row.style.display = cell && (cell.textContent.toLowerCase().includes(searchValue) || searchValue === '') ? '' : 'none';
                });
            });
        });

        // Reusable AJAX call for forecast data updates
        function updateForecastField(data, onSuccess = () => {}, onFail = () => {}) {
            fetch('/update-forecast-data', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(data)
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    onSuccess();
                } else {
                    onFail();
                }
            })
            .catch(err => {
                console.error('AJAX failed:', err);
                alert('Error saving data.');
                onFail();
            });
        }

        // Reusable AJAX call for updating forecast_analysis table
        function updateForecastField(data, onSuccess = () => {}, onFail = () => {}) {
            fetch('/update-forecast-data', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(res => res.json())
            .then(result => {
                if (result.success) {
                    console.log('Saved:', result.message);
                    onSuccess();
                } else {
                    console.warn('Not saved:', result.message);
                    onFail();
                }
            })
            .catch(err => {
                console.error('AJAX failed:', err);
                alert('Error saving data.');
                onFail();
            });
        }

        // Stage Update Handler
        function setupStageUpdate() {
            document.querySelectorAll('.editable-select-stage').forEach(function(select) {
                select.addEventListener('change', function() {
                    const sku = this.dataset.sku;
                    const parent = this.dataset.parent;
                    const value = this.value.trim();

                    // Update background color immediately
                    let bgColor = '#fff';
                    if (value === 'to_order_analysis') {
                        bgColor = '#ffc107'; // Yellow
                    } else if (value === 'mip') {
                        bgColor = '#0d6efd'; // Blue
                    } else if (value === 'r2s') {
                        bgColor = '#198754'; // Green
                    }
                    this.style.backgroundColor = bgColor;
                    this.style.color = '#000';

                    // Get order_qty for validation
                    const row = this.closest('tr');
                    const qtyInput = row.querySelector('td[data-column="4"] input');
                    const orderQty = qtyInput ? parseFloat(qtyInput.value) : 0;

                    if (!orderQty || orderQty === 0) {
                        alert("Order Qty cannot be empty or zero.");
                        this.value = '';
                        this.style.backgroundColor = '#fff';
                        return;
                    }

                    updateForecastField({
                        sku: sku,
                        parent: parent,
                        column: 'Stage',
                        value: value
                    }, function() {
                        // Success - update the select value to ensure it matches saved value
                        this.value = value;
                        // Color already updated
                    }, function() {
                        alert('Failed to save Stage.');
                        // Revert color and value
                        this.style.backgroundColor = '#fff';
                        // Reload page to get correct value from database
                        location.reload();
                    });
                });
            });
        }

        // NRP Update Handler
        function setupNRPUpdate() {
            document.querySelectorAll('.editable-select-nrp').forEach(function(select) {
                select.addEventListener('change', function() {
                    const sku = this.dataset.sku;
                    const parent = this.dataset.parent;
                    const value = this.value.trim();
                    const row = this.closest('tr');

                    // Update background color immediately
                    let bgColor = '#ffffff';
                    let textColor = '#000000';
                    if (value === 'NR') {
                        bgColor = '#dc3545';
                        textColor = '#ffffff';
                    } else if (value === 'REQ') {
                        bgColor = '#28a745';
                        textColor = '#000000';
                    } else if (value === 'LATER') {
                        bgColor = '#ffc107';
                        textColor = '#000000';
                    }
                    this.style.backgroundColor = bgColor;
                    this.style.color = textColor;

                    updateForecastField({
                        sku: sku,
                        parent: parent,
                        column: 'NR',
                        value: value
                    }, function() {
                        // Success - update the select value to ensure it matches saved value
                        this.value = value;
                        // Color already updated
                        
                        // Hide/show row based on NRP value
                        if (value === 'NR') {
                            if (row) row.style.display = 'none';
                        } else {
                            if (row) row.style.display = '';
                        }
                    }, function() {
                        alert('Failed to save NRP.');
                        // Revert color and value
                        this.style.backgroundColor = '#fff';
                        this.style.color = '#000';
                        // Reload page to get correct value from database
                        location.reload();
                    });
                });
            });
        }

        // Filter to show only R2S stage rows
        function filterByR2SStage() {
            const zoneFilter = document.getElementById('zoneFilter');
            const selectedZone = zoneFilter ? zoneFilter.value.trim().toLowerCase() : '';
            const rows = document.querySelectorAll('.wide-table tbody tr');
            const visibleRows = [];

            rows.forEach(row => {
                // Check stage from data attribute first (more reliable)
                const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
                
                // Also check from select dropdown value
                const stageSelect = row.querySelector('.editable-select-stage');
                const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
                
                // Use select value if available, otherwise use data attribute
                const rowStage = rowStageSelect || rowStageAttr;
                
                // Only show R2S stage rows
                const isR2S = rowStage === 'r2s';

                // Check zone filter
                const selectInRow = row.querySelector('select[data-column="area"]');
                const rowZone = selectInRow ? selectInRow.value.trim().toLowerCase() : '';
                const zoneMatch = !selectedZone || rowZone === selectedZone;

                if (isR2S && zoneMatch) {
                    row.style.display = '';
                    visibleRows.push(row);
                } else {
                    row.style.display = 'none';
                }
            });

            // Recalculate totals after filtering
            calculateSupplierTotals(visibleRows);
        }

        // Initialize stage handlers
        setupStageUpdate();
        setupNRPUpdate();
        // Filter to show only R2S stage on page load
        filterByR2SStage();

        // Supplier to Zone mapping
        const supplierZoneMap = @json($supplierZoneMap ?? []);

        // Auto-populate zone for already selected suppliers on page load
        function autoPopulateZoneForSelectedSuppliers() {
            document.querySelectorAll('select[data-column="supplier"]').forEach(supplierSelect => {
                const selectedSupplier = supplierSelect.value;
                if (selectedSupplier && supplierZoneMap[selectedSupplier]) {
                    const row = supplierSelect.closest('tr');
                    const zoneSelect = row.querySelector('select[data-column="area"]');
                    if (zoneSelect && !zoneSelect.value) {
                        // Only update if zone is not already set
                        zoneSelect.value = supplierZoneMap[selectedSupplier];
                    }
                }
            });
            // Reapply filter after auto-populating zones
            filterByR2SStage();
        }

        // Run on page load
        autoPopulateZoneForSelectedSuppliers();

        // Save data on input change
        document.querySelectorAll('.auto-save').forEach(input => {
            input.addEventListener('change', function() {
                const { sku, column } = this.dataset;
                const value = this.value;

                if (!sku || !column) return;

                // If supplier is changed, auto-update zone
                if (column === 'supplier' && value && supplierZoneMap[value]) {
                    const row = this.closest('tr');
                    const zoneSelect = row.querySelector('select[data-column="area"]');
                    if (zoneSelect) {
                        zoneSelect.value = supplierZoneMap[value];
                        // Trigger change event to save zone
                        zoneSelect.dispatchEvent(new Event('change'));
                        // Reapply filter after zone is updated
                        setTimeout(() => {
                            filterByR2SStage();
                        }, 100);
                    }
                }

                // If zone is manually changed, reapply filter
                if (column === 'area') {
                    setTimeout(() => {
                        filterByR2SStage();
                    }, 100);
                }

                fetch('/ready-to-ship/inline-update-by-sku', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ sku, column, value })
                })
                .then(res => res.json())
                .then(res => {
                    this.style.border = res.success ? '2px solid green' : '2px solid red';
                    if (!res.success) alert('Error: ' + res.message);
                    setTimeout(() => this.style.border = '', 1000);
                })
                .catch(() => {
                    this.style.border = '2px solid red';
                    alert('AJAX error occurred.');
                });
            });
        });

        // Select All functionality
        const selectAllCheckbox = document.getElementById('selectAllCheckbox');
        const deleteBtn = document.getElementById('delete-selected-btn');
        const deleteSelectedItemBtn = document.getElementById('delete-selected-item');
        const moveToTransitBtn = document.querySelector('.move-to-transit-btn');
        const checkboxes = document.querySelectorAll('.row-checkbox');

        // Select All checkbox handler
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                checkboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateButtonVisibility();
                updateSelectAllState();
            });
        }

        // Individual checkbox change handlers
        checkboxes.forEach(cb => {
            cb.addEventListener('change', function() {
                updateButtonVisibility();
                updateSelectAllState();
            });
        });

        function updateSelectAllState() {
            if (selectAllCheckbox && checkboxes.length > 0) {
                const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                const someChecked = Array.from(checkboxes).some(cb => cb.checked);
                selectAllCheckbox.checked = allChecked;
                selectAllCheckbox.indeterminate = someChecked && !allChecked;
            }
        }

        function updateButtonVisibility() {
            const anyChecked = document.querySelectorAll('.row-checkbox:checked').length > 0;
            deleteBtn.classList.toggle('d-none', !anyChecked);
            moveToTransitBtn.classList.toggle('d-none', !anyChecked);
            deleteSelectedItemBtn.classList.toggle('d-none', !anyChecked);
        }

        // Initialize select all state
        updateSelectAllState();

        // Delete selected rows
        deleteBtn.addEventListener('click', function() {
            const selectedSkus = Array.from(document.querySelectorAll('.row-checkbox:checked'))
                .map(cb => cb.getAttribute('data-sku')).filter(Boolean);

            if (!selectedSkus.length) return alert("No rows selected.");

            fetch('/ready-to-ship/revert-back-mfrg', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ skus: selectedSkus })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    selectedSkus.forEach(sku => {
                        const row = document.querySelector(`.row-checkbox[data-sku="${sku}"]`)?.closest('tr');
                        if (row) row.remove();
                    });
                    updateButtonVisibility();
                    updateSelectAllState();
                } else {
                    alert('Revert failed');
                }
            })
            .catch(() => alert('Error occurred during revert.'));
        });

        // Move to Transit INV
        moveToTransitBtn.addEventListener('click', function() {
            const selectedSkus = Array.from(document.querySelectorAll('.row-checkbox:checked'))
                .map(cb => cb.getAttribute('data-sku')).filter(Boolean);

            if (!selectedSkus.length) return alert("No rows selected.");

            const tabName = prompt("Please enter container name:");
            if (!tabName || !tabName.trim()) return alert("Tab name is required.");

            fetch('/ready-to-ship/move-to-transit', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ skus: selectedSkus, tab_name: tabName.trim() })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    selectedSkus.forEach(sku => {
                        const checkbox = document.querySelector(`.row-checkbox[data-sku="${sku}"]`);
                        if (checkbox) {
                            checkbox.closest('tr').remove();
                        }
                    });
                    updateButtonVisibility();
                    updateSelectAllState();
                } else {
                    alert('Move to Transit failed');
                }
            })
            .catch(() => alert('Error occurred during transit.'));
        });

        deleteSelectedItemBtn.addEventListener('click', function() {
            const selectedSkus = Array.from(document.querySelectorAll('.row-checkbox:checked')).map(cb => cb.getAttribute('data-sku')).filter(Boolean);

            if (!selectedSkus.length) return alert("No rows selected.");

            if (!confirm("Are you sure you want to delete the selected items? This action cannot be undone.")) {
                return;
            }

            fetch('/ready-to-ship/delete-items', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ skus: selectedSkus })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    selectedSkus.forEach(sku => {
                        const row = document.querySelector(`.row-checkbox[data-sku="${sku}"]`)?.closest('tr');
                        if (row) row.remove();
                    });
                    updateButtonVisibility();
                    updateSelectAllState();
                } else {
                    alert('Delete failed');
                }
            })
            .catch(() => alert('Error occurred during deletion.'));
        });

        // Helper functions
        function capitalizeWords(str) {
            return str.replace(/\w\S*/g, txt => txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase());
        }

        function saveHiddenColumns(hidden) {
            localStorage.setItem('hiddenColumns_readyToShip', JSON.stringify(hidden));
        }

        function getHiddenColumns() {
            return JSON.parse(localStorage.getItem('hiddenColumns_readyToShip') || '[]');
        }

        setupSupplierSelect();

        // Initialize counts on page load
        setTimeout(() => {
            calculateTotalCBM();
            calculateTotalAmount();
            calculateTotalOrderQty();
            calculateTotalOrderItems();
            calculateTotalCTNCBM();
            updateFollowSupplierCount();
            updateSupplierCounts();
        }, 300);

        function setupSupplierSelect() {
            const selectBox = document.getElementById('customSelectBox');
            const dropdown = document.getElementById('customSelectDropdown');
            const selectedText = document.getElementById('customSelectSelectedText');
            const searchInput = document.getElementById('customSelectSearchInput');
            const optionsContainer = document.getElementById('customSelectOptions');
            const wrapper = document.getElementById('advance-total-wrapper');

            let allOptions = Array.from(optionsContainer.querySelectorAll('.custom-select-option'));

            selectBox.addEventListener('click', function () {
                dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
                selectBox.classList.toggle('active', dropdown.style.display === 'block');
                searchInput.value = '';
                allOptions.forEach(option => option.style.display = '');
                updateSupplierCounts();
                setTimeout(() => searchInput.focus(), 100);
            });

            optionsContainer.addEventListener('click', function (e) {
                if (!e.target.classList.contains('custom-select-option')) return;

                allOptions.forEach(opt => opt.classList.remove('selected', 'bg-primary', 'text-white'));
                e.target.classList.add('selected', 'bg-primary', 'text-white');
                
                const optionText = e.target.textContent.trim();
                const displayText = optionText.replace(/\s*\(\d+\)\s*$/, '');
                selectedText.textContent = displayText;
                dropdown.style.display = 'none';
                selectBox.classList.remove('active');

                const selectedSupplier = optionText.replace(/\s*\(\d+\)\s*$/, '').trim();
                const selectedValue = e.target.getAttribute('data-value');
                const allRows = document.querySelectorAll('tbody tr');

                if (!selectedSupplier || selectedSupplier === 'Select supplier' || selectedSupplier === 'All supplier') {
                    if (wrapper) wrapper.style.display = 'none';
                    allRows.forEach(row => {
                        const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
                        const stageSelect = row.querySelector('.editable-select-stage');
                        const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
                        const rowStage = rowStageSelect || rowStageAttr;
                        if (rowStage === 'r2s') {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                    calculateTotalCBM();
                    calculateTotalAmount();
                    calculateTotalOrderQty();
                    calculateTotalOrderItems();
                    calculateTotalCTNCBM();
                    updateFollowSupplierCount();
                    return;
                }

                allRows.forEach(row => row.style.display = '');
                let matchingRows = [];
                
                if (selectedValue === '__all_suppliers__' || selectedSupplier === 'Supplier') {
                    allRows.forEach(row => {
                        const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
                        const stageSelect = row.querySelector('.editable-select-stage');
                        const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
                        const rowStage = rowStageSelect || rowStageAttr;
                        if (rowStage !== 'r2s') {
                            row.style.display = 'none';
                            return;
                        }
                        const supplierCell = row.querySelector('td[data-column="5"]');
                        if (supplierCell) {
                            const supplierSelect = supplierCell.querySelector('select[data-column="supplier"]');
                            const supplierName = supplierSelect ? supplierSelect.value.trim() : '';
                            if (!supplierName || supplierName === '' || supplierName === 'supplier') {
                                row.style.display = '';
                                matchingRows.push(row);
                            } else {
                                row.style.display = 'none';
                            }
                        } else {
                            row.style.display = '';
                            matchingRows.push(row);
                        }
                    });
                } else {
                    allRows.forEach(row => {
                        const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
                        const stageSelect = row.querySelector('.editable-select-stage');
                        const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
                        const rowStage = rowStageSelect || rowStageAttr;
                        if (rowStage !== 'r2s') {
                            row.style.display = 'none';
                            return;
                        }
                        const supplierCell = row.querySelector('td[data-column="5"]');
                        if (supplierCell) {
                            const supplierSelect = supplierCell.querySelector('select[data-column="supplier"]');
                            const supplierName = supplierSelect ? supplierSelect.value.trim() : '';
                            if (supplierName.toLowerCase() === selectedSupplier.toLowerCase()) {
                                row.style.display = '';
                                matchingRows.push(row);
                            } else {
                                row.style.display = 'none';
                            }
                        } else {
                            row.style.display = 'none';
                        }
                    });
                }

                if (selectedValue === '__all_suppliers__' || selectedSupplier === 'Supplier') {
                    if (wrapper) wrapper.style.display = 'none';
                    calculateTotalCBM();
                    calculateTotalAmount();
                    calculateTotalOrderQty();
                    calculateTotalOrderItems();
                    calculateTotalCTNCBM();
                    updateFollowSupplierCount();
                    return;
                } else {
                    if (wrapper) wrapper.style.display = 'block';
                }

                // Calculate advance and pending for selected supplier
                let totalGroupValue = 0;
                matchingRows.forEach(row => {
                    const qtyCell = row.querySelector('td[data-column="4"]');
                    let qty = 0;
                    if (qtyCell) {
                        const qtyInput = qtyCell.querySelector('input');
                        if (qtyInput) {
                            qty = parseFloat(qtyInput.value) || 0;
                        } else {
                            qty = parseFloat(qtyCell.textContent.trim()) || parseFloat(qtyCell.getAttribute('data-qty')) || 0;
                        }
                    }
                    const rate = parseFloat(row.querySelector('input[data-column="rate"]')?.value || '0') || 0;
                    totalGroupValue += qty * rate;
                });

                let totalAdvance = 0;
                matchingRows.forEach(row => {
                    const input = row.querySelector('input[data-supplier]');
                    if (input && !input.disabled) {
                        totalAdvance += parseFloat(input.value || '0') || 0;
                    }
                });

                let totalPending = 0;
                matchingRows.forEach(row => {
                    const qtyCell = row.querySelector('td[data-column="4"]');
                    let qty = 0;
                    if (qtyCell) {
                        const qtyInput = qtyCell.querySelector('input');
                        if (qtyInput) {
                            qty = parseFloat(qtyInput.value) || 0;
                        } else {
                            qty = parseFloat(qtyCell.textContent.trim()) || parseFloat(qtyCell.getAttribute('data-qty')) || 0;
                        }
                    }
                    const rate = parseFloat(row.querySelector('input[data-column="rate"]')?.value || '0') || 0;
                    const rowTotal = qty * rate;
                    let rowAdvance = 0;
                    if (totalGroupValue > 0 && rowTotal > 0) {
                        rowAdvance = (rowTotal / totalGroupValue) * totalAdvance;
                    }
                    const rowPending = rowTotal - rowAdvance;
                    totalPending += rowPending;
                });

                if (document.getElementById('advance-amount')) {
                    document.getElementById('advance-amount').textContent = totalAdvance.toFixed(2);
                }
                if (document.getElementById('pending-amount')) {
                    document.getElementById('pending-amount').textContent = totalPending.toFixed(2);
                }

                calculateTotalCBM();
                calculateTotalAmount();
                calculateTotalOrderQty();
                calculateTotalOrderItems();
                calculateTotalCTNCBM();
                updateFollowSupplierCount();
            });


            // Search filter
            searchInput.addEventListener('input', function () {
                const search = this.value.trim().toLowerCase();
                allOptions.forEach(option => {
                    option.style.display = option.textContent.toLowerCase().includes(search) ? '' : 'none';
                });
            });

            // Keyboard navigation
            searchInput.addEventListener('keydown', function (e) {
                let visibleOptions = allOptions.filter(opt => opt.style.display !== 'none');
                let selectedIdx = visibleOptions.findIndex(opt => opt.classList.contains('selected'));
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (selectedIdx < visibleOptions.length - 1) {
                        if (selectedIdx >= 0) visibleOptions[selectedIdx].classList.remove('selected', 'bg-primary', 'text-white');
                        visibleOptions[selectedIdx + 1].classList.add('selected', 'bg-primary', 'text-white');
                        visibleOptions[selectedIdx + 1].scrollIntoView({ block: 'nearest' });
                    }
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (selectedIdx > 0) {
                        visibleOptions[selectedIdx].classList.remove('selected', 'bg-primary', 'text-white');
                        visibleOptions[selectedIdx - 1].classList.add('selected', 'bg-primary', 'text-white');
                        visibleOptions[selectedIdx - 1].scrollIntoView({ block: 'nearest' });
                    }
                } else if (e.key === 'Enter') {
                    if (selectedIdx >= 0) {
                        visibleOptions[selectedIdx].click();
                    }
                }
            });

            // Close dropdown on outside click
            document.addEventListener('mousedown', function (e) {
                if (!selectBox.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.style.display = 'none';
                    selectBox.classList.remove('active');
                }
            });
        }

        function calculateSupplierTotals(visibleRows) {
            let totalAmount = 0;
            let totalCBM = 0;
            let totalOrderQty = 0;

            visibleRows.forEach(row => {
                // Amount
                const amountCell = row.querySelector('.total-value');
                const amountValue = parseFloat(amountCell?.textContent.trim());
                if (!isNaN(amountValue)) totalAmount += amountValue;

                // CBM
                const cbmCell = row.querySelector('[data-column="19"]');
                const cbmValue = parseFloat(cbmCell?.textContent.trim());
                if (!isNaN(cbmValue)) totalCBM += cbmValue;

                // Order Qty
                const qtyInput = row.querySelector('td[data-column="4"] input');
                const qtyValue = parseFloat(qtyInput?.value || qtyInput?.textContent || 0);
                if (!isNaN(qtyValue)) totalOrderQty += qtyValue;
            });

            document.getElementById('total-amount').textContent = totalAmount.toFixed(0);
            document.getElementById('total-cbm').textContent = totalCBM.toFixed(0);
            document.getElementById('total-order-qty').textContent = totalOrderQty;
        }

        // Zone filter event listener
        const zoneFilterElement = document.getElementById('zoneFilter');
        if (zoneFilterElement) {
            zoneFilterElement.addEventListener('change', function() {
                filterByR2SStage(); // Filter by R2S stage and zone
            });
        }

        // Filter to show only R2S stage on page load
        setTimeout(() => {
            filterByR2SStage();
        }, 100);

    });
</script>
<script>
    const popup = document.createElement('img');
    popup.className = 'preview-popup';
    document.body.appendChild(popup);

    document.querySelectorAll('.hover-img').forEach(img => {
        img.addEventListener('mouseenter', e => {
            popup.src = img.dataset.src;
            popup.style.display = 'block';
        });
        img.addEventListener('mousemove', e => {
            popup.style.top = (e.clientY + 20) + 'px';
            popup.style.left = (e.clientX + 20) + 'px';
        });
        img.addEventListener('mouseleave', e => {
            popup.style.display = 'none';
        });
    });

    // Filter to show only R2S stage on page load
    function filterByR2SStageOnLoad() {
        const rows = document.querySelectorAll('tbody tr.stage-row');
        rows.forEach(row => {
            const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
            const stageSelect = row.querySelector('.editable-select-stage');
            const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
            const rowStage = rowStageSelect || rowStageAttr;
            
            if (rowStage === 'r2s') {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    // Initialize filter on page load
    filterByR2SStageOnLoad();

</script>

<!-- Add MIP-style filters and counts JavaScript -->
<script>
    // Global functions for R2S stage (adapted from MIP page)
    function calculateTotalCBM() {
        let totalCBM = 0;
        document.querySelectorAll('table.wide-table tbody tr').forEach(row => {
            const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
            const stageSelect = row.querySelector('.editable-select-stage');
            const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
            const rowStage = rowStageSelect || rowStageAttr;
            if (rowStage !== 'r2s') return;
            if (row.style.display !== "none") {
                // Total CBM is stored in td[data-column="19"] which is qty * CBM
                const totalCbmCell = row.querySelector('td[data-column="19"]');
                if (totalCbmCell) {
                    const totalCbmText = totalCbmCell.textContent.trim();
                    // Remove commas and parse
                    if (totalCbmText !== '' && totalCbmText !== 'N/A') {
                        const value = parseFloat(totalCbmText.replace(/,/g, ''));
                        if (!isNaN(value)) totalCBM += value;
                    }
                }
            }
        });
        const totalCbmEl = document.getElementById('total-cbm');
        if (totalCbmEl) totalCbmEl.textContent = totalCBM.toFixed(0);
    }

    function calculateTotalAmount() {
        let totalAmount = 0;
        document.querySelectorAll('table.wide-table tbody tr').forEach(row => {
            const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
            const stageSelect = row.querySelector('.editable-select-stage');
            const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
            const rowStage = rowStageSelect || rowStageAttr;
            if (rowStage !== 'r2s') return;
            if (row.style.display !== "none") {
                const td = row.querySelector('.total-value');
                if (td) {
                    const value = parseFloat(td.textContent.trim());
                    if (!isNaN(value)) totalAmount += value;
                }
            }
        });
        const totalAmountEl = document.getElementById('total-amount');
        if (totalAmountEl) totalAmountEl.textContent = totalAmount.toFixed(0);
    }

    function calculateTotalOrderQty() {
        let totalOrderQty = 0;
        document.querySelectorAll('table.wide-table tbody tr').forEach(row => {
            const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
            const stageSelect = row.querySelector('.editable-select-stage');
            const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
            const rowStage = rowStageSelect || rowStageAttr;
            if (rowStage !== 'r2s') return;
            if (row.style.display !== "none") {
                const cell = row.querySelector('td[data-column="4"]');
                if (cell) {
                    const input = cell.querySelector('input');
                    let value = 0;
                    if (input) {
                        value = parseFloat(input.value) || 0;
                    } else {
                        value = parseFloat(cell.textContent.trim()) || parseFloat(cell.getAttribute('data-qty')) || 0;
                    }
                    if (!isNaN(value)) totalOrderQty += value;
                }
            }
        });
        const totalOrderQtyEl = document.getElementById('total-order-qty');
        if (totalOrderQtyEl) totalOrderQtyEl.textContent = totalOrderQty;
    }

    function calculateTotalOrderItems() {
        let totalItems = 0;
        document.querySelectorAll('table.wide-table tbody tr').forEach(row => {
            const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
            const stageSelect = row.querySelector('.editable-select-stage');
            const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
            const rowStage = rowStageSelect || rowStageAttr;
            if (rowStage !== 'r2s') return;
            if (row.style.display !== "none") {
                totalItems++;
            }
        });
        const totalItemsEl = document.getElementById('total-order-items');
        if (totalItemsEl) totalItemsEl.textContent = totalItems;
    }

    function calculateTotalCTNCBM() {
        let totalCTNCBM = 0;
        document.querySelectorAll('table.wide-table tbody tr').forEach(row => {
            const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
            const stageSelect = row.querySelector('.editable-select-stage');
            const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
            const rowStage = rowStageSelect || rowStageAttr;
            if (rowStage !== 'r2s') return;
            if (row.style.display !== "none") {
                const qtyCell = row.querySelector('td[data-column="4"]');
                let qty = 0;
                if (qtyCell) {
                    const qtyInput = qtyCell.querySelector('input');
                    if (qtyInput) {
                        qty = parseFloat(qtyInput.value) || 0;
                    } else {
                        qty = parseFloat(qtyCell.textContent.trim()) || parseFloat(qtyCell.getAttribute('data-qty')) || 0;
                    }
                }
                const ctnCbmECell = row.querySelector('td[data-column="20"]');
                let ctnCbmE = 0;
                if (ctnCbmECell) {
                    const ctnCbmEText = ctnCbmECell.textContent.trim();
                    if (ctnCbmEText !== 'N/A' && ctnCbmEText !== '') {
                        ctnCbmE = parseFloat(ctnCbmEText.replace(/,/g, '')) || 0;
                    }
                }
                const rowTotal = qty * ctnCbmE;
                if (!isNaN(rowTotal)) totalCTNCBM += rowTotal;
            }
        });
        const totalCTNCBMEl = document.getElementById('total-ctn-cbm');
        if (totalCTNCBMEl) totalCTNCBMEl.textContent = totalCTNCBM.toFixed(2);
    }

    function updateFollowSupplierCount() {
        const followSupplierSpan = document.getElementById("followSupplierCount");
        if (!followSupplierSpan) return;
        const supplierSet = new Set();
        const allRows = document.querySelectorAll("table.wide-table tbody tr");
        allRows.forEach(row => {
            const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
            const stageSelect = row.querySelector('.editable-select-stage');
            const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
            const rowStage = rowStageSelect || rowStageAttr;
            if (rowStage !== 'r2s') return;
            const qtyCell = row.querySelector('td[data-column="4"]');
            let qty = 0;
            if (qtyCell) {
                const qtyInput = qtyCell.querySelector('input');
                if (qtyInput) {
                    qty = parseFloat(qtyInput.value) || 0;
                } else {
                    qty = parseFloat(qtyCell.textContent.trim()) || parseFloat(qtyCell.getAttribute('data-qty')) || 0;
                }
            }
            if (qty > 0) {
                const supplierCell = row.querySelector('td[data-column="5"]');
                if (supplierCell) {
                    const supplierSelect = supplierCell.querySelector('select[data-column="supplier"]');
                    const supplierName = supplierSelect ? supplierSelect.value.trim() : '';
                    if (supplierName && supplierName !== '' && supplierName !== 'supplier') {
                        supplierSet.add(supplierName);
                    }
                }
            }
        });
        followSupplierSpan.textContent = supplierSet.size;
    }

    function updateSupplierCounts() {
        const optionsContainer = document.getElementById('customSelectOptions');
        if (!optionsContainer) return;
        const allOptions = Array.from(optionsContainer.querySelectorAll('.custom-select-option'));
        const allRows = document.querySelectorAll('tbody tr');
        const supplierCounts = {};
        allRows.forEach(row => {
            const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
            const stageSelect = row.querySelector('.editable-select-stage');
            const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
            const rowStage = rowStageSelect || rowStageAttr;
            if (rowStage !== 'r2s') return;
            const supplierCell = row.querySelector('td[data-column="5"]');
            if (supplierCell) {
                const supplierSelect = supplierCell.querySelector('select[data-column="supplier"]');
                const supplierName = supplierSelect ? supplierSelect.value.trim() : '';
                if (supplierName && supplierName !== '' && supplierName !== 'supplier') {
                    supplierCounts[supplierName] = (supplierCounts[supplierName] || 0) + 1;
                }
            }
        });
        allOptions.forEach(option => {
            const optionText = option.textContent.trim();
            const supplierName = optionText.replace(/\s*\(\d+\)\s*$/, '');
            if (optionText === 'All supplier' || optionText === 'Supplier' || option.getAttribute('data-value') === '__all_suppliers__') {
                return;
            }
            if (supplierCounts[supplierName] !== undefined) {
                option.textContent = `${supplierName} (${supplierCounts[supplierName]})`;
                option.setAttribute('data-count', supplierCounts[supplierName]);
            } else {
                option.textContent = supplierName;
                option.setAttribute('data-count', '0');
            }
        });
    }

    // Supplier remarks functions
    function getSupplierRemarks(supplier) {
        const remarksKey = `supplier_remarks_r2s_${supplier}`;
        const remarksJson = localStorage.getItem(remarksKey);
        return remarksJson ? JSON.parse(remarksJson) : [];
    }

    function saveSupplierRemark(supplier, remark) {
        if (!supplier || !remark.trim()) {
            alert('Please enter a remark.');
            return;
        }
        const remarksKey = `supplier_remarks_r2s_${supplier}`;
        const remarks = getSupplierRemarks(supplier);
        const newRemark = {
            id: Date.now(),
            text: remark.trim(),
            timestamp: new Date().toLocaleString()
        };
        remarks.unshift(newRemark);
        localStorage.setItem(remarksKey, JSON.stringify(remarks));
        document.getElementById('supplier-remark-input').value = '';
        loadSupplierRemarks(supplier);
        alert('Remark saved successfully!');
    }

    function loadSupplierRemarks(supplier) {
        const remarksList = document.getElementById('remarksList');
        if (!remarksList) return;
        const remarks = getSupplierRemarks(supplier);
        if (remarks.length === 0) {
            remarksList.innerHTML = '<p class="text-muted mb-0">No remarks yet.</p>';
            return;
        }
        let html = '<div class="list-group">';
        remarks.forEach(remark => {
            html += `
                <div class="list-group-item mb-2" style="border-left: 4px solid #28a745;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <p class="mb-1">${remark.text}</p>
                            <small class="text-muted">${remark.timestamp}</small>
                        </div>
                        <button class="btn btn-sm btn-outline-danger delete-remark-btn" data-id="${remark.id}" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        remarksList.innerHTML = html;
        remarksList.querySelectorAll('.delete-remark-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                deleteSupplierRemark(supplier, parseInt(this.getAttribute('data-id')));
            });
        });
    }

    function deleteSupplierRemark(supplier, remarkId) {
        const remarksKey = `supplier_remarks_r2s_${supplier}`;
        const remarks = getSupplierRemarks(supplier);
        const filteredRemarks = remarks.filter(r => r.id !== remarkId);
        localStorage.setItem(remarksKey, JSON.stringify(filteredRemarks));
        loadSupplierRemarks(supplier);
    }

    // Play button functionality for R2S
    document.addEventListener("DOMContentLoaded", function () {
        const rows = document.querySelectorAll("table.wide-table tbody tr");
        const suppliers = [];
        let supplierIndex = 0;

        // Collect unique suppliers (only from R2S stage rows)
        rows.forEach(row => {
            const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
            const stageSelect = row.querySelector('.editable-select-stage');
            const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
            const rowStage = rowStageSelect || rowStageAttr;
            if (rowStage !== 'r2s') return;
            const supplierCell = row.querySelector('td[data-column="5"]');
            if (supplierCell) {
                const supplierSelect = supplierCell.querySelector('select[data-column="supplier"]');
                const supplierName = supplierSelect ? supplierSelect.value.trim() : '';
                if (supplierName && supplierName !== '' && supplierName !== 'supplier' && !suppliers.includes(supplierName)) {
                    suppliers.push(supplierName);
                }
            }
        });

        function showSupplierRows(supplier) {
            rows.forEach(row => {
                const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
                const stageSelect = row.querySelector('.editable-select-stage');
                const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
                const rowStage = rowStageSelect || rowStageAttr;
                if (rowStage !== 'r2s') {
                    row.style.display = "none";
                    return;
                }
                const cell = row.querySelector('td[data-column="5"]');
                if (cell) {
                    const supplierSelect = cell.querySelector('select[data-column="supplier"]');
                    const supplierName = supplierSelect ? supplierSelect.value.trim() : '';
                    if (supplierName === supplier) {
                        row.style.display = "";
                    } else {
                        row.style.display = "none";
                    }
                } else {
                    row.style.display = "none";
                }
            });

            const supplierBadgeContainer = document.getElementById("supplier-badge-container");
            const supplierBadge = document.getElementById("current-supplier");
            const supplierBadgeVr = document.getElementById("supplier-badge-vr");
            if (supplierBadgeContainer) supplierBadgeContainer.style.display = "block";
            if (supplierBadgeVr) supplierBadgeVr.style.display = "block";
            if (supplierBadge) supplierBadge.textContent = supplier || "-";

            const modalSupplierName = document.getElementById("modal-supplier-name");
            if (modalSupplierName) modalSupplierName.textContent = supplier || "-";
            
            if (typeof loadSupplierRemarks === 'function') {
                loadSupplierRemarks(supplier);
            }

            // Update counts after a small delay to ensure DOM is updated
            setTimeout(() => {
                calculateTotalCBM();
                calculateTotalAmount();
                calculateTotalOrderQty();
                calculateTotalOrderItems();
                calculateTotalCTNCBM();
                updateFollowSupplierCount();
            }, 100);
        }

        function refreshSupplierList() {
            suppliers.length = 0;
            rows.forEach(row => {
                const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
                const stageSelect = row.querySelector('.editable-select-stage');
                const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
                const rowStage = rowStageSelect || rowStageAttr;
                if (rowStage !== 'r2s') return;
                const supplierCell = row.querySelector('td[data-column="5"]');
                if (supplierCell) {
                    const supplierSelect = supplierCell.querySelector('select[data-column="supplier"]');
                    const supplierName = supplierSelect ? supplierSelect.value.trim() : '';
                    if (supplierName && supplierName !== '' && supplierName !== 'supplier' && !suppliers.includes(supplierName)) {
                        suppliers.push(supplierName);
                    }
                }
            });
        }

        function playNextSupplier() {
            supplierIndex = (supplierIndex + 1) % suppliers.length;
            showSupplierRows(suppliers[supplierIndex]);
            
            // Ensure counts are updated after a small delay
            setTimeout(() => {
                calculateTotalCBM();
                calculateTotalAmount();
                calculateTotalOrderQty();
                calculateTotalOrderItems();
                calculateTotalCTNCBM();
                updateFollowSupplierCount();
            }, 100);
        }

        // Play button event delegation
        document.addEventListener("click", function(e) {
            let targetElement = e.target;
            if (targetElement.id === "play-auto" || (targetElement.closest("#play-auto") && targetElement.tagName === 'I')) {
                e.preventDefault();
                e.stopPropagation();
                refreshSupplierList();
                if (suppliers.length === 0) {
                    alert("No suppliers found. Please add suppliers to rows.");
                    return;
                }
                document.getElementById("play-auto").style.display = "none";
                document.getElementById("play-pause").style.display = "inline-block";
                supplierIndex = 0;
                showSupplierRows(suppliers[supplierIndex]);
            } else if (targetElement.id === "play-pause" || (targetElement.closest("#play-pause") && targetElement.tagName === 'I')) {
                e.preventDefault();
                e.stopPropagation();
                document.getElementById("play-pause").style.display = "none";
                document.getElementById("play-auto").style.display = "inline-block";
                const supplierBadgeContainer = document.getElementById("supplier-badge-container");
                const supplierBadgeVr = document.getElementById("supplier-badge-vr");
                if (supplierBadgeContainer) supplierBadgeContainer.style.display = "none";
                if (supplierBadgeVr) supplierBadgeVr.style.display = "none";
                rows.forEach(row => {
                    const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
                    const stageSelect = row.querySelector('.editable-select-stage');
                    const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
                    const rowStage = rowStageSelect || rowStageAttr;
                    if (rowStage === 'r2s') {
                        row.style.display = "";
                    } else {
                        row.style.display = "none";
                    }
                });
                const title = document.getElementById("current-supplier");
                if (title) title.textContent = "-";
                calculateTotalCBM();
                calculateTotalAmount();
                calculateTotalOrderQty();
                calculateTotalOrderItems();
                calculateTotalCTNCBM();
                updateFollowSupplierCount();
            } else if (targetElement.id === "play-forward" || (targetElement.closest("#play-forward") && targetElement.tagName === 'I')) {
                e.preventDefault();
                e.stopPropagation();
                if (suppliers.length === 0) refreshSupplierList();
                if (suppliers.length > 0) playNextSupplier();
            } else if (targetElement.id === "play-backward" || (targetElement.closest("#play-backward") && targetElement.tagName === 'I')) {
                e.preventDefault();
                e.stopPropagation();
                if (suppliers.length === 0) refreshSupplierList();
                if (suppliers.length > 0) {
                    supplierIndex = (supplierIndex - 1 + suppliers.length) % suppliers.length;
                    showSupplierRows(suppliers[supplierIndex]);
                    
                    // Ensure counts are updated after a small delay
                    setTimeout(() => {
                        calculateTotalCBM();
                        calculateTotalAmount();
                        calculateTotalOrderQty();
                        calculateTotalOrderItems();
                        calculateTotalCTNCBM();
                        updateFollowSupplierCount();
                    }, 100);
                }
            }
        });

        // Supplier remarks button
        const remarksBtn = document.getElementById('supplier-remarks-btn');
        if (remarksBtn) {
            remarksBtn.addEventListener('click', function() {
                const supplierBadge = document.getElementById('current-supplier');
                const currentSupplier = supplierBadge ? supplierBadge.textContent.trim() : '';
                if (!currentSupplier || currentSupplier === '-') {
                    alert('Please select a supplier first using the play button.');
                    return;
                }
                const modalSupplierName = document.getElementById('modal-supplier-name');
                if (modalSupplierName) modalSupplierName.textContent = currentSupplier;
                loadSupplierRemarks(currentSupplier);
                const modalElement = document.getElementById('supplierRemarksModal');
                if (modalElement) {
                    const modal = new bootstrap.Modal(modalElement);
                    modal.show();
                }
            });
        }

        // Save remark button
        const saveRemarkBtn = document.getElementById('saveRemarkBtn');
        if (saveRemarkBtn) {
            saveRemarkBtn.addEventListener('click', function() {
                const supplierBadge = document.getElementById('current-supplier');
                const currentSupplier = supplierBadge ? supplierBadge.textContent.trim() : '';
                const remarkInput = document.getElementById('supplier-remark-input');
                if (remarkInput && currentSupplier && currentSupplier !== '-') {
                    saveSupplierRemark(currentSupplier, remarkInput.value);
                }
            });
        }

        // Initialize counts on page load
        setTimeout(() => {
            calculateTotalCBM();
            calculateTotalAmount();
            calculateTotalOrderQty();
            calculateTotalOrderItems();
            calculateTotalCTNCBM();
            updateFollowSupplierCount();
            updateSupplierCounts();
        }, 500);
    });
</script>
@endsection