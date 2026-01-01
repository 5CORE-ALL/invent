@extends('layouts.vertical', ['title' => 'MIP', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
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
    td {
        overflow: visible !important;
    }
    
</style>
@endsection
@section('content')
@include('layouts.shared.page-title', ['page_title' => 'MIP', 'sub_title' => 'MIP'])
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
                                <button id="play-backward" class="btn btn-light rounded-circle shadow-sm me-2" title="Previous parent">
                                    <i class="fas fa-step-backward"></i>
                                </button>
                                <button id="play-pause" class="btn btn-light rounded-circle shadow-sm me-2" style="display: none;" title="Pause">
                                    <i class="fas fa-pause"></i>
                                </button>
                                <button id="play-auto" class="btn btn-primary rounded-circle shadow-sm me-2" title="Play">
                                    <i class="fas fa-play"></i>
                                </button>
                                <button id="play-forward" class="btn btn-light rounded-circle shadow-sm me-2" title="Next parent">
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

                        <!-- Delete Selected Button -->
                        <div class="col-auto">
                            <label class="form-label fw-semibold mb-1 d-block" style="visibility: hidden;">Delete</label>
                            <button class="btn btn-danger d-flex align-items-center gap-1" id="deleteSelectedBtn" style="border-radius: 6px; display: none;">
                                <i class="mdi mdi-delete"></i> Delete Selected (<span id="selectedCount">0</span>)
                            </button>
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

                        <!-- Pending Status -->
                        <div class="col-auto">
                            <label class="form-label fw-semibold mb-1 d-block">Pending Status</label>
                            <select id="row-data-pending-status" class="form-select border border-primary" style="min-width: 120px;">
                                <option value="">select color</option>
                                <option value="green">Green <span id="greenCount"></span></option>
                                <option value="yellow">yellow <span id="yellowCount"></span></option>
                                <option value="red">red <span id="redCount"></span></option>
                            </select>
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
                                    <button type="button" class="btn btn-success" id="save-remark-btn">
                                        <i class="fas fa-save"></i> Save Remark
                                    </button>
                                </div>
                                <hr>
                                <div>
                                    <label class="form-label fw-bold mb-2">Saved Remarks/Updates:</label>
                                    <div id="remarks-list" style="max-height: 400px; overflow-y: auto;">
                                        <p class="text-muted">No remarks saved yet.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
                                <th data-column="1">Image<div class="resizer"></div></th>
                                <th data-column="2" hidden>
                                    Parent
                                    <div class="resizer"></div>
                                    <input type="text" class="form-control column-search" data-search-column="2" placeholder="Search Parent..." style="margin-top:4px; font-size:12px; height:28px;">
                                    <div class="search-results" data-results-column="2" style="position:relative; z-index:10;"></div>
                                </th>
                                <th data-column="3">
                                    SKU
                                    <div class="resizer"></div>
                                    <input type="text" class="form-control column-search" data-search-column="3" placeholder="Search SKU..." style="margin-top:4px; font-size:12px; height:28px; width: 150px;">
                                    <div class="search-results" data-results-column="3" style="position:relative; z-index:10;"></div>
                                </th>
                                <th data-column="17" class="text-center">Stage<div class="resizer"></div></th>
                                <th data-column="18" class="text-center" hidden>NRP<div class="resizer"></div></th>
                                <th data-column="4" class="text-center">Order<br/>QTY<div class="resizer"></div></th>
                                <th data-column="5" hidden>Rate<div class="resizer"></div></th>
                                <th data-column="6" class="text-center" style="width: 150px; min-width: 150px; max-width: 150px;">Supplier<div class="resizer"></div></th>
                                <th data-column="21" class="text-center" style="width: 120px; min-width: 120px;">Supplier<br/>SKU<div class="resizer"></div></th>
                                <th data-column="7" hidden>Advance<br/>Amt<div class="resizer"></div></th>
                                <th data-column="8" hidden>Adv<br/>Date<div class="resizer"></div></th>
                                <th data-column="9" hidden>pay conf.<br/>date<div class="resizer"></div></th>
                                {{-- <th data-column="9">pay term<div class="resizer"></div></th> --}}
                                <th data-column="10" class="text-center">Order<br/>Date<div class="resizer"></div></th>
                                <th data-column="11">Del<br/>Date<div class="resizer"></div></th>
                                {{-- <th data-column="12">O Links<div class="resizer"></div></th> --}}
                                <th data-column="12" hidden>value<div class="resizer"></div></th>
                                <th data-column="13" hidden>Payment<br/>Pending<div class="resizer"></div></th>
                                {{-- <th data-column="15">photo<br/>packing<div class="resizer"></div></th> --}}
                                {{-- <th data-column="16">photo int.<br/>sale<div class="resizer"></div></th> --}}
                                <th data-column="14">CBM<div class="resizer"></div></th>
                                <th data-column="15" hidden>total<br/>cbm<div class="resizer"></div></th>
                                <th data-column="20" class="text-center">CTN CBM E<div class="resizer"></div></th>
                                {{-- <th data-column="19" class="text-center">BARCODE<br/>&<br/>SKU<div class="resizer"></div></th> --}}
                                {{-- <th data-column="20">artwork<br/>&<br/>maual<br/>book<div class="resizer"></div></th> --}}
                                {{-- <th data-column="21">notes<div class="resizer"></div></th> --}}
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($data as $item)
                                @php
                                    $readyToShip = $item->ready_to_ship ?? '';
                                    $stageValue = $item->stage ?? '';
                                    $nrValue = strtoupper(trim($item->nr ?? ''));
                                @endphp
                                @continue($readyToShip === 'Yes')
                                @continue($nrValue === 'NR')
                                <tr data-stage="{{ $stageValue ?? '' }}" class="stage-row" data-sku="{{ $item->sku }}">
                                    <td data-column="0" class="text-center">
                                        <input type="checkbox" class="row-checkbox" data-sku="{{ $item->sku }}">
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
                                    <td data-column="2" class="text-center" hidden>
                                        {{ $item->parent ?? '' }}
                                    </td>
                                    <td data-column="3" class="text-center">
                                        {{ $item->sku ?? '' }}
                                    </td>
                                    <td data-column="17" class="text-center">
                                        @php
                                            $stageValue = $item->stage ?? '';
                                            $bgColor = '#fff';
                                            if ($stageValue === 'to_order_analysis') {
                                                $bgColor = '#ffc107'; // Yellow
                                            } elseif ($stageValue === 'mip') {
                                                $bgColor = '#ADD8E6'; // Light Blue
                                            } elseif ($stageValue === 'r2s') {
                                                $bgColor = '#90EE90'; // Light Green
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
                                    <td data-column="18" class="text-center" hidden>
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
                                    <td data-column="4" data-qty="{{ $item->qty ?? 0 }}" style="text-align: end; background-color: #e9ecef;">
                                        <input type="number" 
                                            value="{{ $item->qty ?? 0 }}" 
                                            readonly
                                            style="width:80px; text-align:center; background-color: #e9ecef; cursor: not-allowed; border: none;"
                                            class="form-control form-control-sm">
                                    </td>
                                    <td data-column="5" hidden>
                                        <div class="input-group input-group-sm" style="width:105px;">
                                            <span class="input-group-text" style="padding: 0 6px; background: #e9ecef;">
                                                <span style="font-size: 13px; color: #6c757d;">
                                                    {{ ($item->currency_from_po ?? $item->rate_currency ?? 'USD') == 'USD' ? '$' : '¥' }}
                                                </span>
                                            </span>
                                            <input data-sku="{{ $item->sku }}" data-column="rate" type="text" 
                                                value="{{ $item->price_from_po ?? $item->rate ?? '' }}" 
                                                class="form-control form-control-sm" 
                                                style="background: #f9f9f9; font-size: 13px; cursor: not-allowed;" 
                                                readonly />
                                        </div>
                                    </td>

                                    <td data-column="6" class="text-center" style="width: 150px; min-width: 150px; max-width: 150px;">
                                        <select data-sku="{{ $item->sku }}" data-column="supplier" class="form-select form-select-sm auto-save" style="min-width: 140px; font-size: 12px;">
                                            <option value="">supplier</option>
                                            @foreach ($suppliers as $supplierName)
                                                <option value="{{ $supplierName }}" {{ ($item->supplier ?? '') == $supplierName ? 'selected' : '' }}>
                                                    {{ $supplierName }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td data-column="21" class="text-center" style="width: 120px; min-width: 120px;">
                                        <input type="text" 
                                            data-sku="{{ $item->sku }}" 
                                            data-column="supplier_sku" 
                                            class="form-control form-control-sm auto-save" 
                                            value="{{ $item->supplier_sku ?? '' }}" 
                                            placeholder="Supplier SKU"
                                            style="min-width: 110px; font-size: 12px; text-align: center;">
                                    </td>
                                    <td data-column="7" hidden>
                                        @php
                                            $supplier = $item->supplier ?? '';
                                            $grouped = collect($data)->where('supplier', $supplier);
                                            $firstAdvance = $grouped->first()->advance_amt ?? 0;
                                        @endphp

                                        @if ($loop->first || $item->sku === $grouped->first()->sku)
                                            <input type="number"
                                                class="form-control form-control-sm auto-save"
                                                data-sku="{{ $item->sku }}"
                                                data-supplier="{{ $supplier }}"
                                                data-column="advance_amt"
                                                value="{{ $firstAdvance }}"
                                                min="0"
                                                max="10000"
                                                step="0.01"
                                                style="min-width: 90px; max-width: 120px; font-size: 13px;"
                                                placeholder="Advance Amt"
                                                onchange="if(this.value > 10000) {
                                                    alert('Amount cannot exceed 10000');
                                                    this.value = '';
                                                    return false;
                                                }">
                                        @else
                                            <input type="number" class="form-control form-control-sm"
                                                value="{{ $firstAdvance }}"
                                                disabled
                                                style="min-width: 90px; max-width: 120px; font-size: 13px; background: #e9ecef;" />
                                        @endif
                                    </td>
                                    <td data-column="8" hidden>
                                        <input type="date" value="{{ !empty($item->adv_date) ? \Carbon\Carbon::parse($item->adv_date)->format('Y-m-d') : '' }}" data-sku="{{ $item->sku }}" 
                                        data-column="adv_date" class="form-control form-control-sm auto-save" style="width: 80px; font-size: 13px;">
                                    </td>
                                    <td data-column="9" hidden>
                                        <input type="date" value="{{ !empty($item->pay_conf_date) ? \Carbon\Carbon::parse($item->pay_conf_date)->format('Y-m-d') : '' }}" data-sku="{{ $item->sku }}"
                                         data-column="pay_conf_date" class="form-control form-control-sm auto-save" style="width: 80px; font-size: 13px;">
                                    </td>
                                    @php
                                        $textColor = '';
                                        $daysDiff = null;
                                        $formattedDate = '';

                                        if (!empty($item->created_at)) {
                                            $date = \Carbon\Carbon::parse($item->created_at);
                                            $daysDiff = $date->diffInDays(\Carbon\Carbon::today());
                                            
                                            // Format date with 3-letter month in uppercase: 15 DEC (without year)
                                            $day = $date->format('d');
                                            $month = strtoupper($date->format('M')); // M gives 3-letter month like Jan, Feb - convert to uppercase
                                            $formattedDate = $day . ' ' . $month;

                                            if ($daysDiff > 25) {
                                                $textColor = 'color: red;';
                                            } elseif ($daysDiff >= 15 && $daysDiff <= 25) {
                                                $textColor = 'color: #ffc107;'; // Yellow text
                                            } else {
                                                $textColor = 'color: black;';
                                            }
                                        }
                                    @endphp
                                    <td data-column="10">
                                        <div style="display: flex; flex-direction: column; align-items: flex-start; gap: 2px;">
                                            <input type="date" data-sku="{{ $item->sku }}" data-column="created_at" value="{{ !empty($item->created_at) ? \Carbon\Carbon::parse($item->created_at)->format('Y-m-d') : '' }}" 
                                            class="form-control form-control-sm auto-save" style="width: 80px; font-size: 13px; {{ $textColor }}">
                                            @if ($daysDiff !== null && !empty($formattedDate))
                                                <span style="font-size: 11px; {{ $textColor }}; white-space: nowrap; font-weight: 500;">
                                                    {{ $formattedDate }} ({{ $daysDiff }} D)
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td data-column="11">
                                        @php
                                            $delFormattedDate = '';
                                            if (!empty($item->del_date)) {
                                                $delDate = \Carbon\Carbon::parse($item->del_date);
                                                $delDay = $delDate->format('d');
                                                $delMonth = strtoupper($delDate->format('M'));
                                                $delFormattedDate = $delDay . ' ' . $delMonth;
                                            }
                                        @endphp
                                        <div style="display: flex; flex-direction: column; align-items: flex-start; gap: 2px;">
                                            <input type="date" data-sku="{{ $item->sku }}" data-column="del_date" 
                                                value="{{ !empty($item->del_date) ? \Carbon\Carbon::parse($item->del_date)->format('Y-m-d') : '' }}" 
                                            class="form-control form-control-sm auto-save" style="width: 80px; font-size: 13px;">
                                            @if (!empty($delFormattedDate))
                                                <span style="font-size: 11px; color: black; white-space: nowrap; font-weight: 500;">
                                                    {{ $delFormattedDate }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    {{-- <td data-column="12">
                                        <div class="input-group input-group-sm align-items-center" style="gap: 4px;">
                                            <span class="input-group-text open-link-icon border-0 p-0 bg-transparent" style="{{ empty($item->o_links) ? 'display:none;' : '' }}; background: none !important;">
                                                <a href="{{ $item->o_links ?? '#' }}" target="_blank" title="Open Link" style="color: #3bc0c3; font-size: 20px; display: flex; align-items: center; background: none;">
                                                    <i class="mdi mdi-open-in-new" style="transition: color 0.2s; cursor: pointer;"></i>
                                                </a>
                                            </span>
                                            <span class="input-group-text edit-link-icon border-0 p-0 bg-transparent" style="cursor:pointer; background: none !important;">
                                                <a href="javascript:void(0);" class="edit-o-links" title="Edit" style="color: #6c757d; font-size: 20px; display: flex; align-items: center; background: none;">
                                                    <i class="mdi mdi-pencil-outline" style="transition: color 0.2s; cursor: pointer;"></i>
                                                </a>
                                            </span>
                                                </a>
                                            </span>
                                            <input type="text" class="form-control form-control-sm o-links-input d-none auto-save" value="{{ $item->o_links ?? '' }}" data-sku="{{ $item->sku }}" data-column="o_links" placeholder="Paste or type link here..." style="font-size: 13px; min-width: 180px; border-radius: 20px; box-shadow: 0 1px 4px rgba(60,192,195,0.08); border: 1px solid #e3e3e3; padding-left: 14px; background: #f8fafd;">
                                        </div>
                                    </td> --}}

                                    <td class="total-value d-none" data-column="12">
                                        {{ is_numeric($item->qty ?? null) && is_numeric($item->rate ?? null) ? ($item->qty * $item->rate) : '' }}
                                    </td>
                                    <td data-column="13" hidden>
                                        @php
                                            $supplier = $item->supplier ?? '';
                                            $grouped = collect($data)->where('supplier', $supplier);

                                            $supplierAdvance = $grouped->first()->advance_amt ?? 0;

                                            $totalValue = $grouped->sum(function ($row) {
                                                return (is_numeric($row->qty) && is_numeric($row->rate)) ? $row->qty * $row->rate : 0;
                                            });

                                            $thisRowValue = (is_numeric($item->qty ?? null) && is_numeric($item->rate ?? null)) ? $item->qty * $item->rate : 0;

                                            $rowAdvance = $totalValue > 0 ? ($thisRowValue / $totalValue) * $supplierAdvance : 0;
                                            $pending = $thisRowValue - $rowAdvance;
                                        @endphp
                                        {{ number_format($pending, 0) }}
                                    </td>

                                    {{-- <td data-column="15">
                                        <div class="image-upload-field d-flex align-items-center gap-2">
                                            @if(!empty($item->photo_packing))
                                                <a href="{{ $item->photo_packing }}" target="_blank" class="me-1" title="View Photo" style="width:50px;">
                                                    <img src="{{ $item->photo_packing }}" class="img-thumbnail border" style="height: 50px; width: 50px; object-fit: cover; background: #f8f9fa;">
                                                </a>
                                            @endif

                                            <label class="btn btn-sm btn-outline-primary d-flex align-items-center mb-0" style="padding: 4px 10px; border-radius: 6px; font-size: 15px;" title="Upload Photo">
                                                <i class="mdi mdi-upload" style="font-size: 18px; margin-right: 4px;"></i>
                                                <span style="font-size: 13px;"></span>
                                                <input type="file" class="d-none auto-upload" data-column="photo_packing" data-sku="{{ $item->sku }}">
                                            </label>
                                        </div>
                                    </td> --}}

                                    {{-- <td data-column="16">
                                        <div class="image-upload-field d-flex align-items-center gap-2">
                                            @if(!empty($item->photo_int_sale))
                                                <a href="{{ $item->photo_int_sale }}" target="_blank" class="me-1" title="View Photo" style="width:50px;">
                                                    <img src="{{ $item->photo_int_sale }}" class="img-thumbnail border" style="height: 50px; width: 50px; object-fit: cover; background: #f8f9fa;">
                                                </a>
                                            @endif

                                            <label class="btn btn-sm btn-outline-primary d-flex align-items-center mb-0" style="padding: 4px 10px; border-radius: 6px; font-size: 15px;" title="Upload Photo">
                                                <i class="mdi mdi-upload" style="font-size: 18px; margin-right: 4px;"></i>
                                                <span style="font-size: 13px;"></span>
                                                <input type="file" class="d-none auto-upload" data-column="photo_int_sale" data-sku="{{ $item->sku }}">
                                            </label>
                                        </div>
                                    </td> --}}

                                    <td data-column="14">
                                        {{ isset($item->CBM) ? number_format($item->CBM, 4) : 'N/A' }}
                                    </td>

                                    <td data-column="20" class="text-center">
                                        {{ isset($item->ctn_cbm_e) && $item->ctn_cbm_e !== null ? number_format($item->ctn_cbm_e, 4) : 'N/A' }}
                                    </td>

                                    <td data-column="15" hidden>
                                        <input type="number"
                                            data-sku="{{ $item->sku }}"
                                            data-column="total_cbm"
                                            step="0.000000001"
                                            value="{{ is_numeric($item->qty ?? null) && is_numeric($item->CBM ?? null) ? number_format($item->qty * $item->CBM, 2, '.', '') : '' }}"
                                            class="form-control form-control-sm auto-save"
                                            style="min-width: 90px; width: 100px; font-size: 13px;"
                                            placeholder="Total CBM"
                                            readonly>
                                    </td>

                                    {{-- <td data-column="19">
                                        <div class="image-upload-field d-flex align-items-center gap-2">
                                            @if(!empty($item->barcode_sku))
                                                <a href="{{ $item->barcode_sku }}" target="_blank" class="me-1" title="View Photo" style="width:50px;">
                                                    <img src="{{ $item->barcode_sku }}" class="img-thumbnail border" style="height: 50px; width: 50px; object-fit: cover; background: #f8f9fa;">
                                                </a>
                                            @endif

                                            <label class="btn btn-sm btn-outline-primary d-flex align-items-center mb-0" style="padding: 4px 10px; border-radius: 6px; font-size: 15px;" title="Upload Photo">
                                                <i class="mdi mdi-upload" style="font-size: 18px; margin-right: 4px;"></i>
                                                <span style="font-size: 13px;"></span>
                                                <input type="file" class="d-none auto-upload" data-column="barcode_sku" data-sku="{{ $item->sku }}">
                                            </label>
                                        </div>
                                    </td>

                                    <td data-column="20">
                                        <input type="text" class="form-control form-control-sm auto-save" data-sku="{{ $item->sku }}" data-column="artwork_manual_book" value="{{ $item->artwork_manual_book ?? '' }}" placeholder="Artwork Manual Book">
                                    </td>

                                    <td data-column="21">
                                        <input type="text" class="form-control form-control-sm auto-save" data-sku="{{ $item->sku }}" data-column="notes" value="{{ $item->notes ?? '' }}" style="font-size: 13px;" placeholder="Notes">
                                    </td> --}}

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

    document.addEventListener('DOMContentLoaded', function () {
        document.documentElement.setAttribute("data-sidenav-size", "condensed");

        const table = document.querySelector('.wide-table');
        const rows = table.querySelectorAll('tbody tr');

        // Column Resizing
        initColumnResizing();
        // restoreColumnWidths();

        // Column Visibility
        setupColumnVisibility();

        // Full Table Search
        setupWholeTableSearch();

        // ✅ Column-Specific Filter (Professional Version)
        setupColumnSearch();

        // Inline Auto-Save
        setupAutoSave();

        // Stage Update Handler
        setupStageUpdate();
        setupNRPUpdate();

        // Filter to show only MIP stage on page load
        filterByMIPStage();

        // Currency Conversion
        setupCurrencyConversion();

        // Open/Edit O Links
        setupOlinkEditor();

        // Supplier Wise Advance + Pending
        setupSupplierAdvanceCalculation();

        // Total CBM calculation
        calculateTotalCBM();

        //total order qty
        calculateTotalOrderQty();

        //total amount
        calculateTotalAmount();

        //total order items count
        calculateTotalOrderItems();

        //total CTN CBM
        calculateTotalCTNCBM();

        // Delete with checkbox functionality
        setupDeleteWithCheckbox();

        // Filter to show only MIP stage on page load
        setTimeout(() => {
            filterByMIPStage();
        }, 100);

        // ========= FUNCTIONS ========= //

        function initColumnResizing() {
            const resizers = document.querySelectorAll('.resizer');
            resizers.forEach(resizer => {
                resizer.addEventListener('mousedown', initResize);
            });

            function initResize(e) {
                e.preventDefault();
                const th = e.target.parentElement;
                const startX = e.clientX;
                const startWidth = th.offsetWidth;
                e.target.classList.add('resizing');
                th.style.width = th.style.minWidth = th.style.maxWidth = startWidth + 'px';

                document.addEventListener('mousemove', resize);
                document.addEventListener('mouseup', stopResize);

                function resize(e) {
                    const newWidth = startWidth + e.clientX - startX;
                    if (newWidth > 80) {
                        th.style.width = th.style.minWidth = th.style.maxWidth = newWidth + 'px';

                    }
                }

                function stopResize() {
                    document.removeEventListener('mousemove', resize);
                    document.removeEventListener('mouseup', stopResize);
                    document.querySelectorAll('.resizing').forEach(el => el.classList.remove('resizing'));
                    saveColumnWidths();
                }
            }

        function saveColumnWidths() {
            const widths = {};
            document.querySelectorAll('.wide-table thead th').forEach(th => {
                const col = th.getAttribute('data-column');
                widths[col] = th.offsetWidth;
            });
            localStorage.setItem('columnWidths_mfrg', JSON.stringify(widths));
        }

        function restoreColumnWidths() {
            const widths = JSON.parse(localStorage.getItem('columnWidths_mfrg') || '{}');
                Object.keys(widths).forEach(col => {
                    const th = document.querySelector(`.wide-table thead th[data-column="${col}"]`);
                    if (th) {
                        th.style.width = th.style.minWidth = th.style.maxWidth = widths[col] + 'px';
                    }
                });
            }
            restoreColumnWidths();
        }

        function setupColumnVisibility() {
            const showAllBtn = document.getElementById('showAllColumns');
            const dropdownBtn = document.getElementById('columnDropdownBtn');
            const dropdownContent = document.getElementById('columnDropdownContent');
            const ths = document.querySelectorAll('.wide-table thead th');

            function capitalizeWords(str) {
                return str.replace(/\w\S*/g, txt => txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase());
            }

            dropdownBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                dropdownContent.classList.toggle('show');
            });

            window.addEventListener('click', function (e) {
                if (!e.target.matches('.column-dropdown-btn') && !dropdownContent.contains(e.target)) {
                    dropdownContent.classList.remove('show');
                }
            });

            dropdownContent.innerHTML = '';
            ths.forEach((th, i) => {
                const colIndex = i + 1;
                if (!th) return;
                const colNameText = th.childNodes[0]?.nodeValue?.trim() || th.textContent?.trim() || `Column ${colIndex}`;
                const colName = capitalizeWords(colNameText);
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

            function saveHiddenColumns(hidden) {
                localStorage.setItem('hiddenColumns_mfrg', JSON.stringify(hidden));
            }

            function getHiddenColumns() {
                return JSON.parse(localStorage.getItem('hiddenColumns_mfrg') || '[]');
            }

            const hiddenColumns = getHiddenColumns();
            // Ensure column 1 (Image) is always visible - remove it from hidden columns if present
            if (hiddenColumns.includes('1')) {
                hiddenColumns.splice(hiddenColumns.indexOf('1'), 1);
                saveHiddenColumns(hiddenColumns);
            }
            
            document.querySelectorAll('.column-checkbox').forEach(checkbox => {
                const columnIndex = checkbox.getAttribute('data-column');
                const th = document.querySelector(`.wide-table thead th[data-column="${columnIndex}"]`);
                const label = document.querySelector(`label[for="column-${columnIndex}"]`);
                if (!th || !label) return;
                const colNameText = th.childNodes[0]?.nodeValue?.trim() || th.textContent?.trim() || `Column ${columnIndex}`;
                const colName = capitalizeWords(colNameText);
                
                // Force column 1 (Image) to always be visible
                if (columnIndex === '1') {
                    checkbox.checked = true;
                    document.querySelectorAll(`[data-column="${columnIndex}"]`).forEach(cell => cell.style.display = '');
                    label.innerHTML = `${colName} <i class="mdi mdi-eye text-primary"></i>`;
                } else if (hiddenColumns.includes(columnIndex)) {
                    checkbox.checked = false;
                    document.querySelectorAll(`[data-column="${columnIndex}"]`).forEach(cell => cell.style.display = 'none');
                    label.innerHTML = `${colName} <i class="mdi mdi-eye-off text-muted"></i>`;
                } else {
                    checkbox.checked = true;
                    document.querySelectorAll(`[data-column="${columnIndex}"]`).forEach(cell => cell.style.display = '');
                    label.innerHTML = `${colName} <i class="mdi mdi-eye text-primary"></i>`;
                }
            });

            dropdownContent.querySelectorAll('.column-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', function () {
                    const columnIndex = this.getAttribute('data-column');
                    const th = document.querySelector(`.wide-table thead th[data-column="${columnIndex}"]`);
                    const label = document.querySelector(`label[for="column-${columnIndex}"]`);
                    if (!th || !label) return;
                    
                    // Prevent hiding column 1 (Image)
                    if (columnIndex === '1') {
                        this.checked = true;
                        alert('Image column cannot be hidden');
                        return;
                    }
                    
                    const colNameText = th.childNodes[0]?.nodeValue?.trim() || th.textContent?.trim() || `Column ${columnIndex}`;
                    const colName = capitalizeWords(colNameText);
                    let hidden = getHiddenColumns();
                    if (this.checked) {
                        document.querySelectorAll(`[data-column="${columnIndex}"]`).forEach(cell => cell.style.display = '');
                        label.innerHTML = `${colName} <i class="mdi mdi-eye text-primary"></i>`;
                        hidden = hidden.filter(c => c !== columnIndex);
                    } else {
                        document.querySelectorAll(`[data-column="${columnIndex}"]`).forEach(cell => cell.style.display = 'none');
                        label.innerHTML = `${colName} <i class="mdi mdi-eye-off text-muted"></i>`;
                        if (!hidden.includes(columnIndex)) hidden.push(columnIndex);
                    }
                    saveHiddenColumns(hidden);
                });
            });

            showAllBtn.addEventListener('click', function () {
                document.querySelectorAll('.column-checkbox').forEach(checkbox => {
                    checkbox.checked = true;
                    const columnIndex = checkbox.getAttribute('data-column');
                    document.querySelectorAll(`[data-column="${columnIndex}"]`).forEach(cell => cell.style.display = '');
                    const th = document.querySelector(`.wide-table thead th[data-column="${columnIndex}"]`);
                    const label = document.querySelector(`label[for="column-${columnIndex}"]`);
                    if (!th || !label) return;
                    const colNameText = th.childNodes[0]?.nodeValue?.trim() || th.textContent?.trim() || `Column ${columnIndex}`;
                    const colName = capitalizeWords(colNameText);
                    label.innerHTML = `${colName} <i class="mdi mdi-eye text-primary"></i>`;
                });
                saveHiddenColumns([]);
            });
        }

        function setupWholeTableSearch() {
            const searchInput = document.getElementById('wholeSearchInput');
            searchInput.addEventListener('input', function () {
                const search = this.value.trim().toLowerCase();
                rows.forEach(row => {
                    // Check if stage is MIP
                    const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
                    const stageSelect = row.querySelector('.editable-select-stage');
                    const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
                    const rowStage = rowStageSelect || rowStageAttr;
                    const isMIP = rowStage === 'mip';
                    
                    // Only show MIP stage rows
                    if (!isMIP) {
                        row.style.display = 'none';
                        return;
                    }
                    
                    const match = Array.from(row.querySelectorAll('td')).some(td => td.textContent.toLowerCase().includes(search));
                    row.style.display = match ? '' : 'none';
                });
            });
        }

        // ✅ Fixed version of column search (supports multiple filters) - Only MIP stage
        function setupColumnSearch() {
            document.querySelectorAll('.column-search').forEach(input => {
                input.addEventListener('input', function () {
                    const filters = {};
                    document.querySelectorAll('.column-search').forEach(searchInput => {
                        const col = searchInput.getAttribute('data-search-column');
                        const val = searchInput.value.trim().toLowerCase();
                        if (val !== '') {
                            filters[col] = val;
                        }
                    });

                    rows.forEach(row => {
                        // Check if stage is MIP
                        const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
                        const stageSelect = row.querySelector('.editable-select-stage');
                        const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
                        const rowStage = rowStageSelect || rowStageAttr;
                        const isMIP = rowStage === 'mip';
                        
                        // Only show MIP stage rows
                        if (!isMIP) {
                            row.style.display = 'none';
                            return;
                        }
                        
                        let show = true;
                        for (const col in filters) {
                            const cell = row.querySelector(`td[data-column="${col}"]`);
                            if (!cell || !cell.textContent.toLowerCase().includes(filters[col])) {
                                show = false;
                                break;
                            }
                        }
                        row.style.display = show ? '' : 'none';
                    });
                });
            });
        }

        function setupAutoSave() {
            document.querySelectorAll('.auto-save').forEach(function (input) {
                input.addEventListener('change', function () {
                    const sku = this.dataset.sku;
                    const column = this.dataset.column;
                    const value = this.value;
                    const row = this.closest('tr');

                    if (!sku || !column) return;

                    // ✅ Save via AJAX
                    fetch('/mfrg-progresses/inline-update-by-sku', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ sku, column, value })
                    })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) {
                            this.style.border = '2px solid green';
                            setTimeout(() => this.style.border = '', 1000);

                            // Update supplier counts if supplier changed
                            if (column === 'supplier' && updateSupplierCounts) {
                                updateSupplierCounts();
                            }

                            // ✅ Recalculate Total on rate change
                            if (column === 'rate') {
                                const qtyCell = row.querySelector('td[data-column="4"]');
                                const totalCell = row.querySelector('td[data-column="12"]');
                                const qty = parseFloat(qtyCell?.innerText?.trim() || '0');
                                const rate = parseFloat(value);
                                if (!isNaN(qty) && !isNaN(rate)) {
                                    totalCell.innerText = (qty * rate).toFixed(2);
                                }
                            }

                            // ✅ Recalculate Pending on advance_amt change
                            if (column === 'advance_amt') {
                                const totalCell = row.querySelector('td[data-column="12"]');
                                const pendingCell = row.querySelector('td[data-column="13"]');
                                const total = parseFloat(totalCell?.innerText?.trim() || '0');
                                const advance = parseFloat(value);
                                if (!isNaN(total) && !isNaN(advance)) {
                                    pendingCell.innerText = (total - advance).toFixed(2);
                                }
                            }

                            // ✅ Insert into Ready to Ship table
                            if (column === 'ready_to_ship' && value === 'Yes') {
                                const parent = row.querySelector('td[data-column="2"]')?.innerText?.trim() || '';
                                const skuVal = row.querySelector('td[data-column="3"]')?.innerText?.trim() || '';
                                const supplierSelect = row.querySelector('td[data-column="6"] select[data-column="supplier"]');
                                const supplier = supplierSelect ? supplierSelect.value.trim() : '';
                                const qty = row.querySelector('td[data-column="4"]')?.innerText?.trim() || '';
                                const totalCbm = row.querySelector('td[data-column="15"] input')?.value?.trim() || '';
                                const rate = row.querySelector('td[data-column="5"] input')?.value?.trim() || '';                                

                                fetch('/ready-to-ship/insert', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                    },
                                    body: JSON.stringify({
                                        parent: parent,
                                        sku: skuVal,
                                        supplier: supplier,
                                        qty: qty,
                                        totalCbm: totalCbm,
                                        rate: rate
                                    })
                                })
                                .then(r => r.json())
                                .then(r => {
                                    if (r.success) {
                                        row.remove();
                                    } else {
                                        alert('❌ Failed to insert into Ready to Ship: ' + r.message);
                                    }
                                })
                                .catch(() => {
                                    alert('❌ Error during Ready to Ship insert.');
                                });
                            }

                        } else {
                            this.style.border = '2px solid red';
                            console.log('❌ Error:', res.message);
                        }
                    })
                    .catch(() => {
                        this.style.border = '2px solid red';
                        alert('❌ AJAX error occurred.');
                    });
                });
            });
        }

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
                        bgColor = '#ADD8E6'; // Light Blue
                    } else if (value === 'r2s') {
                        bgColor = '#90EE90'; // Light Green
                    }
                    this.style.backgroundColor = bgColor;
                    this.style.color = '#000';

                    // Get order_qty for validation
                    const row = this.closest('tr');
                    const qtyCell = row.querySelector('td[data-column="4"] input');
                    const orderQty = qtyCell ? parseFloat(qtyCell.value) : 0;

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

        // Filter to show only MIP stage rows
        function filterByMIPStage() {
            const rows = document.querySelectorAll('.wide-table tbody tr');

            rows.forEach(row => {
                // Check stage from data attribute first (more reliable)
                const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
                
                // Also check from select dropdown value
                const stageSelect = row.querySelector('.editable-select-stage');
                const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
                
                // Use select value if available, otherwise use data attribute
                const rowStage = rowStageSelect || rowStageAttr;
                
                // Only show MIP stage rows
                if (rowStage === 'mip') {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Recalculate totals after filtering
            calculateTotalCBM();
            calculateTotalAmount();
            calculateTotalOrderQty();
            calculateTotalOrderItems();
            calculateTotalCTNCBM();
            updateFollowSupplierCount();
        }

        function setupAutoUpload() {
            document.querySelectorAll('.auto-upload').forEach(function(input) {
                input.addEventListener('change', function () {
                    const sku = this.dataset.sku;
                    const column = this.dataset.column;
                    const file = this.files[0];
                    const parentDiv = input.closest('.image-upload-field');

                    if (!sku || !column || !file) return;

                    const formData = new FormData();
                    formData.append('sku', sku);
                    formData.append('column', column);
                    formData.append('value', file); 

                    fetch('/mfrg-progresses/inline-update-by-sku', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: formData
                    })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success && res.url) {
                            // ✅ Update with image preview + upload field again
                            parentDiv.innerHTML = `
                                <a href="${res.url}" target="_blank" style="width:50px;">
                                    <img src="${res.url}" alt="Uploaded" style="height: 50px; width: 50px; object-fit: cover; background: #f8f9fa;">
                                </a>
                                <label class="btn btn-sm btn-outline-primary d-flex align-items-center mb-0" style="padding: 4px 10px; border-radius: 6px; font-size: 15px;" title="Upload Photo">
                                    <i class="mdi mdi-upload" style="font-size: 18px; margin-right: 4px;"></i>
                                    <span style="font-size: 13px;"></span>
                                    <input type="file" class="d-none auto-upload" data-column="${column}" data-sku="${sku}">
                                </label>
                            `;
                            setupAutoUpload(); // re-bind to new input
                        } else {
                            alert("❌ Upload failed: " + (res.message || 'Unknown error'));
                        }
                    })
                    .catch(() => {
                        alert("❌ AJAX error during upload");
                    });
                });
            });
        }

        setupAutoUpload();

        function setupCurrencyConversion() {
            document.querySelectorAll('.input-group').forEach(group => {
                const select = group.querySelector('.currency-select');
                const input = group.querySelector('.amount-input');

                if (!select || !input) return;

                let baseCurrency = select.value;
                let baseAmount = parseFloat(input.value) || 0;

                input.addEventListener('input', () => {
                    baseAmount = parseFloat(input.value) || 0;
                    baseCurrency = select.value;
                });

                select.addEventListener('change', function () {
                    const newCurrency = select.value;

                    if (baseCurrency === newCurrency || isNaN(baseAmount) || baseAmount === 0) return;

                    const url = `/convert-currency?amount=${baseAmount}&from=${baseCurrency}&to=${newCurrency}`;

                    fetch(url)
                        .then(res => res.json())
                        .then(data => {
                            console.log('Currency API response:', data);
                            if (data.rates && data.rates[newCurrency]) {
                                const converted = data.rates[newCurrency];
                                input.value = parseFloat(converted).toFixed(2);
                                baseAmount = parseFloat(converted);
                                baseCurrency = newCurrency;
                            } else {
                                alert('Invalid currency response (missing rates).');
                            }
                        })
                        .catch(err => {
                            alert('Currency conversion failed.');
                            console.error(err);
                        });
                });
            });
        }

        function setupOlinkEditor() {
            document.querySelectorAll('.edit-o-links').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const group = btn.closest('.input-group');
                    const input = group.querySelector('.o-links-input');
                    const openIcon = group.querySelector('.open-link-icon');
                    const editIcon = group.querySelector('.edit-link-icon');

                    input.classList.remove('d-none');
                    input.focus();
                    openIcon.style.display = 'none';
                    editIcon.style.display = 'none';

                    input.addEventListener('blur', function () {
                        input.classList.add('d-none');
                        openIcon.style.display = 'inline-flex';
                        editIcon.style.display = 'inline-flex';
                    });

                    input.addEventListener('keydown', function (e) {
                        if (e.key === 'Enter') {
                            input.blur();
                        }
                    });
                });
            });
        }

        function setupDeleteWithCheckbox() {
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            const rowCheckboxes = document.querySelectorAll('.row-checkbox');
            const deleteBtn = document.getElementById('deleteSelectedBtn');
            const selectedCountSpan = document.getElementById('selectedCount');

            // Select All functionality
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    rowCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                    updateDeleteButton();
                });
            }

            // Individual checkbox change
            rowCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    updateSelectAllState();
                    updateDeleteButton();
                });
            });

            function updateSelectAllState() {
                if (selectAllCheckbox && rowCheckboxes.length > 0) {
                    const allChecked = Array.from(rowCheckboxes).every(cb => cb.checked);
                    const someChecked = Array.from(rowCheckboxes).some(cb => cb.checked);
                    selectAllCheckbox.checked = allChecked;
                    selectAllCheckbox.indeterminate = someChecked && !allChecked;
                }
            }

            function updateDeleteButton() {
                const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
                const count = checkedBoxes.length;
                
                if (deleteBtn && selectedCountSpan) {
                    if (count > 0) {
                        deleteBtn.style.display = 'flex';
                        selectedCountSpan.textContent = count;
                    } else {
                        deleteBtn.style.display = 'none';
                    }
                }
            }

            // Delete button click handler
            if (deleteBtn) {
                deleteBtn.addEventListener('click', function() {
                    const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
                    if (checkedBoxes.length === 0) {
                        alert('Please select at least one item to delete.');
                        return;
                    }

                    if (!confirm(`Are you sure you want to delete ${checkedBoxes.length} item(s)? This action cannot be undone.`)) {
                        return;
                    }

                    const skus = Array.from(checkedBoxes).map(cb => cb.dataset.sku);
                    
                    fetch('/mfrg-progresses/delete', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ skus: skus })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            // Remove deleted rows from table
                            checkedBoxes.forEach(checkbox => {
                                const row = checkbox.closest('tr');
                                if (row) {
                                    row.remove();
                                }
                            });
                            
                            // Reset select all checkbox
                            if (selectAllCheckbox) {
                                selectAllCheckbox.checked = false;
                                selectAllCheckbox.indeterminate = false;
                            }
                            
                            // Hide delete button
                            updateDeleteButton();
                            
                            // Recalculate totals
                            calculateTotalCBM();
                            calculateTotalAmount();
                            calculateTotalOrderQty();
                            calculateTotalOrderItems();
                            calculateTotalCTNCBM();
                            updateFollowSupplierCount();
                            
                            alert(`Successfully deleted ${data.deleted_count} item(s).`);
                        } else {
                            alert('Error: ' + (data.message || 'Failed to delete items.'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while deleting items.');
                    });
                });
            }

            // Initialize button state
            updateDeleteButton();
        }


        function setupSupplierAdvanceCalculation() {
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
                
                // Update counts when dropdown opens
                updateSupplierCounts();
                
                setTimeout(() => searchInput.focus(), 100);
            });

            optionsContainer.addEventListener('click', function (e) {
                if (!e.target.classList.contains('custom-select-option')) return;

                // UI update
                allOptions.forEach(opt => opt.classList.remove('selected', 'bg-primary', 'text-white'));
                e.target.classList.add('selected', 'bg-primary', 'text-white');
                
                // Get text without count for display
                const optionText = e.target.textContent.trim();
                const displayText = optionText.replace(/\s*\(\d+\)\s*$/, '');
                selectedText.textContent = displayText;
                
                dropdown.style.display = 'none';
                selectBox.classList.remove('active');

                // Remove count from supplier name for comparison
                const selectedSupplier = optionText.replace(/\s*\(\d+\)\s*$/, '').trim();
                const selectedValue = e.target.getAttribute('data-value');
                const allRows = document.querySelectorAll('tbody tr');

                if (!selectedSupplier || selectedSupplier === 'Select supplier' || selectedSupplier === 'All supplier') {
                    document.getElementById('advance-total-wrapper').style.display = 'none';
                    // Reset all rows visibility
                    allRows.forEach(row => {
                        // Check stage from data attribute first (more reliable)
                        const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
                        const stageSelect = row.querySelector('.editable-select-stage');
                        const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
                        const rowStage = rowStageSelect || rowStageAttr;
                        
                        // Only show MIP stage rows
                        if (rowStage === 'mip') {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                    calculateTotalCBM();
                    calculateTotalAmount();
                    calculateTotalOrderQty();
                    calculateTotalCTNCBM();
                    return;
                }

                // Reset visibility
                allRows.forEach(row => row.style.display = '');

                // Filter rows matching selected supplier
                let matchingRows = [];
                
                // Special case: "Supplier" option - show only rows with blank/empty supplier
                if (selectedValue === '__all_suppliers__' || selectedSupplier === 'Supplier') {
                    // Show only rows that have blank/empty supplier
                    allRows.forEach(row => {
                        // Check stage from data attribute first (more reliable)
                        const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
                        const stageSelect = row.querySelector('.editable-select-stage');
                        const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
                        const rowStage = rowStageSelect || rowStageAttr;
                        
                        // Only show MIP stage rows
                        if (rowStage !== 'mip') {
                            row.style.display = 'none';
                            return;
                        }
                        
                        const supplierCell = row.querySelector('td[data-column="6"]');
                        if (supplierCell) {
                            const supplierSelect = supplierCell.querySelector('select[data-column="supplier"]');
                            const supplierName = supplierSelect ? supplierSelect.value.trim() : '';
                            // Show only rows where supplier is blank/empty
                            if (!supplierName || supplierName === '' || supplierName === 'supplier') {
                                row.style.display = '';
                                matchingRows.push(row);
                            } else {
                                row.style.display = 'none';
                            }
                        } else {
                            // If no supplier cell, show the row
                            row.style.display = '';
                            matchingRows.push(row);
                        }
                    });
                } else {
                    // Normal supplier filtering - match exact supplier name
                    allRows.forEach(row => {
                        // Check stage from data attribute first (more reliable)
                        const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
                        const stageSelect = row.querySelector('.editable-select-stage');
                        const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
                        const rowStage = rowStageSelect || rowStageAttr;
                        
                        // Only show MIP stage rows
                        if (rowStage !== 'mip') {
                            row.style.display = 'none';
                            return;
                        }
                        
                        const supplierCell = row.querySelector('td[data-column="6"]');
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

                if (!wrapper) {
                    console.warn("Wrapper not found! Skipping animation and totals.");
                    return;
                }

                if (matchingRows.length === 0) {
                    wrapper.style.display = 'none';
                    console.log("No matching rows, hiding wrapper.");
                    return;
                }
                
                // Hide advance wrapper when any supplier is selected
                wrapper.style.display = 'none';
                
                // For "Supplier" option, sort visible rows by order date (oldest first)
                if (selectedValue === '__all_suppliers__' || selectedSupplier === 'Supplier') {
                    // Sort visible rows by order date (oldest first)
                    const visibleRows = Array.from(allRows).filter(row => row.style.display !== 'none');
                    sortRowsByOrderDate(visibleRows);
                    // Recalculate totals for all visible rows
                    calculateTotalCBM();
                    calculateTotalAmount();
                    calculateTotalOrderQty();
                    calculateTotalOrderItems();
                    calculateTotalCTNCBM();
                    updateFollowSupplierCount();
                    return;
                } else {
                    // Sort matching rows by order date (oldest first)
                    sortRowsByOrderDate(matchingRows);
                }

                // Calculate total group value
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

                // Calculate total advance
                let totalAdvance = 0;
                matchingRows.forEach(row => {
                    const input = row.querySelector('input[data-supplier]');
                    if (input && !input.disabled) {
                        totalAdvance += parseFloat(input.value || '0') || 0;
                    }
                });

                let totalPending = 0;
                let supplierTotalCBM = 0;
                let supplierTotalOrderQty = 0;
                let supplierTotalAmount = 0;
                let supplierTotalItems = 0;
                let supplierTotalCTNCBM = 0;

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

                    const advanceInput = row.querySelector('input[data-column="advance"]');
                    const pendingInput = row.querySelector('input[data-column="pending"]');

                    const cbmInput = row.querySelector('input[data-column="total_cbm"]');

                    const cbm = parseFloat(cbmInput?.value || '0');

                    if (!isNaN(cbm)) supplierTotalCBM += cbm;
                    if (!isNaN(qty)) supplierTotalOrderQty += qty;
                    supplierTotalAmount += rowTotal;
                    supplierTotalItems++;
                    
                    // Calculate CTN CBM E
                    const ctnCbmECell = row.querySelector('td[data-column="20"]');
                    let ctnCbmE = 0;
                    if (ctnCbmECell) {
                        const ctnCbmEText = ctnCbmECell.textContent.trim();
                        if (ctnCbmEText !== 'N/A' && ctnCbmEText !== '') {
                            ctnCbmE = parseFloat(ctnCbmEText.replace(/,/g, '')) || 0;
                        }
                    }
                    // Add: Order Qty * CTN CBM E
                    supplierTotalCTNCBM += qty * ctnCbmE;

                    let rowAdvance = 0;
                    if (totalGroupValue > 0 && rowTotal > 0) {
                        rowAdvance = (rowTotal / totalGroupValue) * totalAdvance;
                    }

                    const rowPending = rowTotal - rowAdvance;
                    totalPending += rowPending;

                    if (advanceInput) advanceInput.value = rowAdvance.toFixed(2);
                    if (pendingInput) pendingInput.value = rowPending.toFixed(2);
                });

                // Update summary display
                document.getElementById('advance-amount').textContent = totalAdvance.toFixed(2);
                document.getElementById('pending-amount').textContent = totalPending.toFixed(2);

                // Update supplier cbm and order qty (update main totals with supplier filtered values)
                document.getElementById('total-cbm').textContent = supplierTotalCBM.toFixed(2);
                document.getElementById('total-order-qty').textContent = supplierTotalOrderQty;
                document.getElementById('total-amount').textContent = supplierTotalAmount.toFixed(0);
                document.getElementById('total-order-items').textContent = supplierTotalItems;
                document.getElementById('total-ctn-cbm').textContent = supplierTotalCTNCBM.toFixed(2);

                // Animate wrapper
                wrapper.classList.add('animate__animated', 'animate__fadeIn');
                setTimeout(() => wrapper.classList.remove('animate__animated', 'animate__fadeIn'), 800);
            });


            // 🔍 Search filter
            searchInput.addEventListener('input', function () {
                const search = this.value.trim().toLowerCase();
                allOptions.forEach(option => {
                    option.style.display = option.textContent.toLowerCase().includes(search) ? '' : 'none';
                });
            });

            // ⌨️ Keyboard navigation
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

            // 🖱️ Close on outside click
            document.addEventListener('mousedown', function (e) {
                if (!selectBox.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.style.display = 'none';
                    selectBox.classList.remove('active');
                }
            });
        }


        // document.getElementById('play-auto').addEventListener('click', () => {
        //     isPlaying = true;
        //     currentSupplierIndex = 0;
        //     renderGroup(supplierKeys[currentSupplierIndex]);
        //     document.getElementById('play-pause').style.display = 'inline-block';
        //     document.getElementById('play-auto').style.display = 'none';
        // });

        // document.getElementById('play-forward').addEventListener('click', () => {
        //     if (!isPlaying) return;
        //     currentSupplierIndex = (currentSupplierIndex + 1) % supplierKeys.length;
        //     renderGroup(supplierKeys[currentSupplierIndex]);
        // });

        // document.getElementById('play-backward').addEventListener('click', () => {
        //     if (!isPlaying) return;
        //     currentSupplierIndex = (currentSupplierIndex - 1 + supplierKeys.length) % supplierKeys.length;
        //     renderGroup(supplierKeys[currentSupplierIndex]);
        // });

        // document.getElementById('play-pause').addEventListener('click', () => {
        //     isPlaying = false;
        //     tableBody.innerHTML = originalTableHtml;
        //     document.getElementById('play-pause').style.display = 'none';
        //     document.getElementById('play-auto').style.display = 'inline-block';
        //     attachEditableListeners();
        //     attachStageListeners();
        // });

    });
</script>
<script>
    // Helper function to sort rows by order date (oldest first) - Global scope
    function sortRowsByOrderDate(rows) {
        const tbody = document.querySelector('table.wide-table tbody');
        if (!tbody) return;
        
        // Convert NodeList to Array if needed
        const rowsArray = Array.isArray(rows) ? [...rows] : Array.from(rows);
        
        if (rowsArray.length === 0) return;
        
        // Sort by order date (oldest first)
        rowsArray.sort((a, b) => {
            // First, try to extract days difference from span (most reliable for sorting)
            const dateCellA = a.querySelector('td[data-column="10"]');
            const dateCellB = b.querySelector('td[data-column="10"]');
            const spanA = dateCellA ? dateCellA.querySelector('span') : null;
            const spanB = dateCellB ? dateCellB.querySelector('span') : null;
            
            let daysA = 0;
            let daysB = 0;
            
            if (spanA) {
                const matchA = spanA.textContent.match(/\((\d+)\s*D\)/);
                daysA = matchA ? parseInt(matchA[1]) : 0;
            }
            if (spanB) {
                const matchB = spanB.textContent.match(/\((\d+)\s*D\)/);
                daysB = matchB ? parseInt(matchB[1]) : 0;
            }
            
            // If both have days difference, sort by that (higher days = older = comes first)
            if (daysA > 0 && daysB > 0) {
                return daysB - daysA; // Descending: 26D comes before 25D
            }
            
            // Fallback to date input if days difference not available
            const dateInputA = a.querySelector('input[data-column="created_at"]');
            const dateInputB = b.querySelector('input[data-column="created_at"]');
            
            let dateA = dateInputA ? dateInputA.value.trim() : '';
            let dateB = dateInputB ? dateInputB.value.trim() : '';
            
            // Extract only date part (YYYY-MM-DD) if timestamp is present
            if (dateA) {
                dateA = dateA.substring(0, 10);
            }
            if (dateB) {
                dateB = dateB.substring(0, 10);
            }
            
            // If no date, put at the end
            if (!dateA && !dateB) return 0;
            if (!dateA) return 1;
            if (!dateB) return -1;
            
            // Validate date format (YYYY-MM-DD)
            const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
            if (!dateRegex.test(dateA) || !dateRegex.test(dateB)) {
                // If invalid format, use days difference if available
                return daysB - daysA;
            }
            
            // Compare dates (oldest first) - split and compare year, month, day
            const partsA = dateA.split('-').map(Number);
            const partsB = dateB.split('-').map(Number);
            
            // Compare year first
            if (partsA[0] !== partsB[0]) {
                return partsA[0] - partsB[0]; // Older year comes first
            }
            // Compare month
            if (partsA[1] !== partsB[1]) {
                return partsA[1] - partsB[1]; // Older month comes first
            }
            // Compare day
            if (partsA[2] !== partsB[2]) {
                return partsA[2] - partsB[2]; // Older day comes first
            }
            
            // If dates are exactly the same, use days difference as tiebreaker
            // Higher days = older = should come first (descending order)
            return daysB - daysA;
        });
        
        // Get all rows from tbody to preserve hidden rows
        const allRowsInTbody = Array.from(tbody.querySelectorAll('tr'));
        const sortedRowsSet = new Set(rowsArray);
        const hiddenRows = allRowsInTbody.filter(row => !sortedRowsSet.has(row));
        
        // Remove all rows from tbody temporarily
        rowsArray.forEach(row => {
            if (row.parentNode) {
                row.parentNode.removeChild(row);
            }
        });
        
        // Append sorted visible rows first (oldest first)
        rowsArray.forEach(row => {
            tbody.appendChild(row);
        });
        
        // Append hidden rows at the end (maintain their original order)
        hiddenRows.forEach(row => {
            tbody.appendChild(row);
        });
    }

    document.addEventListener("DOMContentLoaded", function () {
        const rows = document.querySelectorAll("table.wide-table tbody tr");
        const filterSelect = document.getElementById("row-data-pending-status");
        const greenSpan = document.getElementById("greenCount");
        const yellowSpan = document.getElementById("yellowCount");
        const redSpan = document.getElementById("redCount");

        const suppliers = [];
        let supplierIndex = 0;
        let intervalId = null;

        // Collect unique suppliers (only from MIP stage rows)
        rows.forEach(row => {
            // Check stage from data attribute first (more reliable)
            const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
            const stageSelect = row.querySelector('.editable-select-stage');
            const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
            const rowStage = rowStageSelect || rowStageAttr;
            
            // Only collect suppliers from MIP stage rows
            if (rowStage !== 'mip') {
                return;
            }
            
            const supplierCell = row.querySelector('td[data-column="6"]');
            if (supplierCell) {
                // Get supplier from dropdown
                const supplierSelect = supplierCell.querySelector('select[data-column="supplier"]');
                const supplierName = supplierSelect ? supplierSelect.value.trim() : '';
                if (supplierName && !suppliers.includes(supplierName)) {
                    suppliers.push(supplierName);
                }
            }
        });

        function showSupplierRows(supplier) {
            const visibleRows = [];
            
            rows.forEach(row => {
                // Check stage from data attribute first (more reliable)
                const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
                const stageSelect = row.querySelector('.editable-select-stage');
                const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
                const rowStage = rowStageSelect || rowStageAttr;
                
                // Only show MIP stage rows
                if (rowStage !== 'mip') {
                    row.style.display = "none";
                    return;
                }
                
                const cell = row.querySelector('td[data-column="6"]');
                if (cell) {
                    // Get supplier from dropdown
                    const supplierSelect = cell.querySelector('select[data-column="supplier"]');
                    const supplierName = supplierSelect ? supplierSelect.value.trim() : '';
                    if (supplierName === supplier) {
                        row.style.display = "";
                        visibleRows.push(row);
                    } else {
                        row.style.display = "none";
                    }
                } else {
                    row.style.display = "none";
                }
            });

            // Sort visible rows by order date (oldest first)
            if (visibleRows.length > 0) {
                sortRowsByOrderDate(visibleRows);
            }

            // Show supplier badge with supplier name
            const supplierBadgeContainer = document.getElementById("supplier-badge-container");
            const supplierBadge = document.getElementById("current-supplier");
            const supplierBadgeVr = document.getElementById("supplier-badge-vr");
            if (supplierBadgeContainer) {
                supplierBadgeContainer.style.display = "block";
            }
            if (supplierBadgeVr) {
                supplierBadgeVr.style.display = "block";
            }
            if (supplierBadge) {
                supplierBadge.textContent = supplier || "-";
            }

            // Update modal supplier name if modal is open
            const modalSupplierName = document.getElementById("modal-supplier-name");
            if (modalSupplierName) {
                modalSupplierName.textContent = supplier || "-";
            }
            
            // Load and display remarks for this supplier
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
                updateCounts(); // Update pending status counts based on visible rows
            }, 50);
        }

        function playNextSupplier() {
            supplierIndex = (supplierIndex + 1) % suppliers.length;
            showSupplierRows(suppliers[supplierIndex]);
        }

        // Function to refresh supplier list (only from MIP stage rows)
        function refreshSupplierList() {
            suppliers.length = 0; // Clear existing list
            rows.forEach(row => {
                // Check stage from data attribute first (more reliable)
                const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
                const stageSelect = row.querySelector('.editable-select-stage');
                const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
                const rowStage = rowStageSelect || rowStageAttr;
                
                // Only collect suppliers from MIP stage rows
                if (rowStage !== 'mip') {
                    return;
                }
                
                const supplierCell = row.querySelector('td[data-column="6"]');
                if (supplierCell) {
                    const supplierSelect = supplierCell.querySelector('select[data-column="supplier"]');
                    const supplierName = supplierSelect ? supplierSelect.value.trim() : '';
                    if (supplierName && !suppliers.includes(supplierName)) {
                        suppliers.push(supplierName);
                    }
                }
            });
            // Reset index if current supplier is no longer in list
            if (supplierIndex >= suppliers.length) {
                supplierIndex = 0;
            }
        }

        // Play button - use event delegation to handle multiple clicks
        document.addEventListener("click", function(e) {
            const playAutoBtn = e.target.closest("#play-auto");
            if (playAutoBtn) {
                e.preventDefault();
                e.stopPropagation();
                
                // Refresh supplier list before playing
                refreshSupplierList();
                if (suppliers.length === 0) {
                    alert("No suppliers found. Please add suppliers to rows.");
                    return;
                }
                
                playAutoBtn.style.display = "none";
                const playPauseBtn = document.getElementById("play-pause");
                if (playPauseBtn) {
                    playPauseBtn.style.display = "inline-block";
                }
                
                supplierIndex = 0; // Start from first supplier
                showSupplierRows(suppliers[supplierIndex]);
            }
        });

        document.getElementById("play-pause").addEventListener("click", function () {
            this.style.display = "none";
            document.getElementById("play-auto").style.display = "inline-block";
            
            // Hide supplier badge when pausing
            const supplierBadgeContainer = document.getElementById("supplier-badge-container");
            const supplierBadgeVr = document.getElementById("supplier-badge-vr");
            if (supplierBadgeContainer) {
                supplierBadgeContainer.style.display = "none";
            }
            if (supplierBadgeVr) {
                supplierBadgeVr.style.display = "none";
            }
            
            // Show only MIP stage rows when pausing
            rows.forEach(row => {
                // Check stage from data attribute first (more reliable)
                const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
                const stageSelect = row.querySelector('.editable-select-stage');
                const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
                const rowStage = rowStageSelect || rowStageAttr;
                
                // Only show MIP stage rows
                if (rowStage === 'mip') {
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
            updateCounts(); // Update pending status counts when pausing
        });


        document.getElementById("play-forward").addEventListener("click", function () {
            if (suppliers.length === 0) {
                refreshSupplierList();
            }
            if (suppliers.length > 0) {
                playNextSupplier();
            }
        });

        document.getElementById("play-backward").addEventListener("click", function () {
            if (suppliers.length === 0) {
                refreshSupplierList();
            }
            if (suppliers.length > 0) {
                supplierIndex = (supplierIndex - 1 + suppliers.length) % suppliers.length;
                showSupplierRows(suppliers[supplierIndex]);
            }
        });

        function updateCounts() {
            let green = 0, yellow = 0, red = 0;

            rows.forEach(row => {
                // Check if row is MIP stage
                const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
                const stageSelect = row.querySelector('.editable-select-stage');
                const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
                const rowStage = rowStageSelect || rowStageAttr;
                if (rowStage !== 'mip') return;

                // Only count visible rows (filtered by play button or other filters)
                if (row.style.display === "none") return;

                // Check created_at (Order Date) field for text color
                const dateInput = row.querySelector('input[data-column="created_at"]');
                if (!dateInput) {
                    green++; // Default to green if no date
                    return;
                }

                // Check inline style first (more reliable)
                const inlineStyle = dateInput.getAttribute('style') || '';
                const inlineStyleLower = inlineStyle.toLowerCase();
                
                // Also check the span element if it exists
                const dateSpan = dateInput.parentElement.querySelector('span');
                const spanStyle = dateSpan ? (dateSpan.getAttribute('style') || '') : '';
                const spanStyleLower = spanStyle.toLowerCase();

                // Check for red color in inline style
                if (inlineStyleLower.includes('color: red') || inlineStyleLower.includes('color:red') || 
                    inlineStyleLower.includes('color: #dc3545') || inlineStyleLower.includes('color:#dc3545') ||
                    spanStyleLower.includes('color: red') || spanStyleLower.includes('color:red') ||
                    spanStyleLower.includes('color: #dc3545') || spanStyleLower.includes('color:#dc3545')) {
                    red++;
                }
                // Check for yellow color in inline style
                else if (inlineStyleLower.includes('color: #ffc107') || inlineStyleLower.includes('color:#ffc107') ||
                         spanStyleLower.includes('color: #ffc107') || spanStyleLower.includes('color:#ffc107')) {
                    yellow++;
                }
                // Default to green (black or no color specified)
                else {
                    green++;
                }
            });

            greenSpan.textContent = `(${green})`;
            yellowSpan.textContent = `(${yellow})`;
            redSpan.textContent = `(${red})`;
        }

        function filterDateRows(type) {
            rows.forEach(row => {
                // Check if row is MIP stage first
                const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
                const stageSelect = row.querySelector('.editable-select-stage');
                const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
                const rowStage = rowStageSelect || rowStageAttr;
                
                if (rowStage !== 'mip') {
                    row.style.display = "none";
                    return;
                }

                if (!type) {
                    // Show all MIP rows if no filter selected
                    row.style.display = "";
                    return;
                }

                // Check created_at (Order Date) field for text color
                const dateInput = row.querySelector('input[data-column="created_at"]');
                if (!dateInput) {
                    // If no date input, default to green
                    row.style.display = (type === "green") ? "" : "none";
                    return;
                }

                // Check inline style first (more reliable)
                const inlineStyle = dateInput.getAttribute('style') || '';
                const inlineStyleLower = inlineStyle.toLowerCase();
                
                // Also check the span element if it exists
                const dateSpan = dateInput.parentElement.querySelector('span');
                const spanStyle = dateSpan ? (dateSpan.getAttribute('style') || '') : '';
                const spanStyleLower = spanStyle.toLowerCase();

                let rowColor = "green"; // Default to green
                
                // Check for red color in inline style (color: red; or color:#dc3545;)
                if (inlineStyleLower.includes('color: red') || inlineStyleLower.includes('color:red') || 
                    inlineStyleLower.includes('color: #dc3545') || inlineStyleLower.includes('color:#dc3545') ||
                    spanStyleLower.includes('color: red') || spanStyleLower.includes('color:red') ||
                    spanStyleLower.includes('color: #dc3545') || spanStyleLower.includes('color:#dc3545')) {
                    rowColor = "red";
                }
                // Check for yellow color in inline style (color: #ffc107;)
                else if (inlineStyleLower.includes('color: #ffc107') || inlineStyleLower.includes('color:#ffc107') ||
                         spanStyleLower.includes('color: #ffc107') || spanStyleLower.includes('color:#ffc107')) {
                    rowColor = "yellow";
                }
                // If no color specified or black/default, it's green
                else {
                    rowColor = "green";
                }

                row.style.display = (rowColor === type) ? "" : "none";
            });

            calculateTotalCBM();
            calculateTotalAmount();
            calculateTotalOrderQty();
            calculateTotalOrderItems();
            calculateTotalCTNCBM();
            updateFollowSupplierCount();
            updateCounts(); // Update pending status counts when filtering by date
        }

        updateCounts();
        updateFollowSupplierCount();

        filterSelect.addEventListener("change", function () {
            filterDateRows(this.value);
        });

        // Supplier Remarks Functionality
        let currentSupplierForRemarks = '';

        // Function to get supplier remarks from local storage
        function getSupplierRemarks(supplier) {
            if (!supplier) return [];
            const remarksKey = `supplier_remarks_${supplier}`;
            const remarks = localStorage.getItem(remarksKey);
            return remarks ? JSON.parse(remarks) : [];
        }

        // Function to save supplier remark
        function saveSupplierRemark(supplier, remark) {
            if (!supplier || !remark.trim()) {
                alert('Please enter a remark.');
                return;
            }

            const remarksKey = `supplier_remarks_${supplier}`;
            const remarks = getSupplierRemarks(supplier);
            
            const newRemark = {
                id: Date.now(),
                text: remark.trim(),
                timestamp: new Date().toLocaleString()
            };
            
            remarks.unshift(newRemark); // Add to beginning
            localStorage.setItem(remarksKey, JSON.stringify(remarks));
            
            // Clear input
            document.getElementById('supplier-remark-input').value = '';
            
            // Reload remarks
            loadSupplierRemarks(supplier);
            
            alert('Remark saved successfully!');
        }

        // Function to load and display supplier remarks
        function loadSupplierRemarks(supplier) {
            const remarksList = document.getElementById('remarks-list');
            if (!remarksList) return;

            const remarks = getSupplierRemarks(supplier);
            
            if (remarks.length === 0) {
                remarksList.innerHTML = '<p class="text-muted">No remarks saved yet.</p>';
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

            // Add delete event listeners
            remarksList.querySelectorAll('.delete-remark-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    if (confirm('Are you sure you want to delete this remark?')) {
                        deleteSupplierRemark(supplier, parseInt(this.dataset.id));
                    }
                });
            });
        }

        // Function to delete supplier remark
        function deleteSupplierRemark(supplier, remarkId) {
            const remarksKey = `supplier_remarks_${supplier}`;
            const remarks = getSupplierRemarks(supplier);
            const filteredRemarks = remarks.filter(r => r.id !== remarkId);
            localStorage.setItem(remarksKey, JSON.stringify(filteredRemarks));
            loadSupplierRemarks(supplier);
        }

        // Open remarks modal button
        document.getElementById('supplier-remarks-btn').addEventListener('click', function() {
            const supplierBadge = document.getElementById('current-supplier');
            const currentSupplier = supplierBadge ? supplierBadge.textContent.trim() : '';
            
            if (!currentSupplier || currentSupplier === '-') {
                alert('Please select a supplier first using the play button.');
                return;
            }

            currentSupplierForRemarks = currentSupplier;
            const modalSupplierName = document.getElementById('modal-supplier-name');
            if (modalSupplierName) {
                modalSupplierName.textContent = currentSupplier;
            }
            
            loadSupplierRemarks(currentSupplier);
            
            // Show modal (using Bootstrap)
            const modalElement = document.getElementById('supplierRemarksModal');
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        });

        // Save remark button
        document.getElementById('save-remark-btn').addEventListener('click', function() {
            const remarkText = document.getElementById('supplier-remark-input').value.trim();
            if (currentSupplierForRemarks) {
                saveSupplierRemark(currentSupplierForRemarks, remarkText);
            } else {
                alert('Please select a supplier first.');
            }
        });

        // Update remarks when supplier changes (if modal is open)
        const supplierRemarksModal = document.getElementById('supplierRemarksModal');
        if (supplierRemarksModal) {
            supplierRemarksModal.addEventListener('show.bs.modal', function() {
                const supplierBadge = document.getElementById('current-supplier');
                const currentSupplier = supplierBadge ? supplierBadge.textContent.trim() : '';
                if (currentSupplier && currentSupplier !== '-') {
                    currentSupplierForRemarks = currentSupplier;
                    loadSupplierRemarks(currentSupplier);
                }
            });
        }
    });

    function calculateTotalCBM() {
        let totalCBM = 0;

        document.querySelectorAll('table.wide-table tbody tr').forEach(row => {
            // Check stage from data attribute first (more reliable)
            const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
            const stageSelect = row.querySelector('.editable-select-stage');
            const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
            const rowStage = rowStageSelect || rowStageAttr;
            
            // Only count MIP stage rows
            if (rowStage !== 'mip') {
                return;
            }
            
            if (row.style.display !== "none") {
                const input = row.querySelector('input[data-column="total_cbm"]');
                if (input) {
                    const value = parseFloat(input.value);
                    if (!isNaN(value)) totalCBM += value;
                }
            }
        });

        document.getElementById('total-cbm').textContent = totalCBM.toFixed(0);
    } 

    function calculateTotalAmount() {
        let totalAmount = 0;

        document.querySelectorAll('table.wide-table tbody tr').forEach(row => {
            // Check stage from data attribute first (more reliable)
            const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
            const stageSelect = row.querySelector('.editable-select-stage');
            const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
            const rowStage = rowStageSelect || rowStageAttr;
            
            // Only count MIP stage rows
            if (rowStage !== 'mip') {
                return;
            }
            
            if (row.style.display !== "none") { 
                const td = row.querySelector('.total-value');
                if (td) {
                    const value = parseFloat(td.textContent.trim());
                    if (!isNaN(value)) {
                        totalAmount += value;
                    }
                }
            }
        });

        document.getElementById('total-amount').textContent = totalAmount.toFixed(0);
    }

    function calculateTotalOrderQty() {
        let totalOrderQty = 0;
        document.querySelectorAll('table.wide-table tbody tr').forEach(row => {
            // Check stage from data attribute first (more reliable)
            const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
            const stageSelect = row.querySelector('.editable-select-stage');
            const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
            const rowStage = rowStageSelect || rowStageAttr;
            
            // Only count MIP stage rows
            if (rowStage !== 'mip') {
                return;
            }
            
            if (row.style.display !== "none") { 
                const cell = row.querySelector('td[data-column="4"]');
                if (cell) {
                    // Try to get value from input field first
                    const input = cell.querySelector('input');
                    let value = 0;
                    
                    if (input) {
                        value = parseFloat(input.value) || 0;
                    } else {
                        // Fallback to textContent or data attribute
                        value = parseFloat(cell.textContent.trim()) || parseFloat(cell.getAttribute('data-qty')) || 0;
                    }
                    
                    if (!isNaN(value)) {
                        totalOrderQty += value;
                    }
                }
            }
        });

        document.getElementById('total-order-qty').textContent = totalOrderQty;
    }

    function calculateTotalOrderItems() {
        let totalItems = 0;
        document.querySelectorAll('table.wide-table tbody tr').forEach(row => {
            // Check stage from data attribute first (more reliable)
            const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
            const stageSelect = row.querySelector('.editable-select-stage');
            const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
            const rowStage = rowStageSelect || rowStageAttr;
            
            // Only count MIP stage rows
            if (rowStage !== 'mip') {
                return;
            }
            
            // Only count visible rows
            if (row.style.display !== "none") {
                totalItems++;
            }
        });

        document.getElementById('total-order-items').textContent = totalItems;
    }

    function calculateTotalCTNCBM() {
        let totalCTNCBM = 0;
        document.querySelectorAll('table.wide-table tbody tr').forEach(row => {
            // Check stage from data attribute first (more reliable)
            const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
            const stageSelect = row.querySelector('.editable-select-stage');
            const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
            const rowStage = rowStageSelect || rowStageAttr;
            
            // Only count MIP stage rows
            if (rowStage !== 'mip') {
                return;
            }
            
            if (row.style.display !== "none") {
                // Get Order Qty (column 4)
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
                
                // Get CTN CBM E (column 20)
                const ctnCbmECell = row.querySelector('td[data-column="20"]');
                let ctnCbmE = 0;
                if (ctnCbmECell) {
                    const ctnCbmEText = ctnCbmECell.textContent.trim();
                    // Remove 'N/A' and parse the value
                    if (ctnCbmEText !== 'N/A' && ctnCbmEText !== '') {
                        ctnCbmE = parseFloat(ctnCbmEText.replace(/,/g, '')) || 0;
                    }
                }
                
                // Calculate: Order Qty * CTN CBM E
                const rowTotal = qty * ctnCbmE;
                if (!isNaN(rowTotal)) {
                    totalCTNCBM += rowTotal;
                }
            }
        });

        document.getElementById('total-ctn-cbm').textContent = totalCTNCBM.toFixed(2);
    }

    // Function to calculate and update supplier counts (defined globally)
    function updateSupplierCounts() {
        const optionsContainer = document.getElementById('customSelectOptions');
        if (!optionsContainer) return;
        
        const allOptions = Array.from(optionsContainer.querySelectorAll('.custom-select-option'));
        const allRows = document.querySelectorAll('tbody tr');
        const supplierCounts = {};
        
        // Count rows for each supplier (only MIP stage)
        allRows.forEach(row => {
            // Check stage from data attribute first (more reliable)
            const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
            const stageSelect = row.querySelector('.editable-select-stage');
            const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
            const rowStage = rowStageSelect || rowStageAttr;
            
            // Only count MIP stage rows
            if (rowStage !== 'mip') {
                return;
            }
            
            const supplierCell = row.querySelector('td[data-column="6"]');
            if (supplierCell) {
                const supplierSelect = supplierCell.querySelector('select[data-column="supplier"]');
                const supplierName = supplierSelect ? supplierSelect.value.trim() : '';
                if (supplierName && supplierName !== '' && supplierName !== 'supplier') {
                    supplierCounts[supplierName] = (supplierCounts[supplierName] || 0) + 1;
                }
            }
        });
        
        // Update option text with counts
        allOptions.forEach(option => {
            const optionText = option.textContent.trim();
            const supplierName = optionText.replace(/\s*\(\d+\)\s*$/, ''); // Remove existing count
            
            // Skip "All supplier" and "Supplier" options
            if (optionText === 'All supplier' || optionText === 'Supplier' || option.getAttribute('data-value') === '__all_suppliers__') {
                return;
            }
            
            // Update with count if supplier exists
            if (supplierCounts[supplierName] !== undefined) {
                option.textContent = `${supplierName} (${supplierCounts[supplierName]})`;
                option.setAttribute('data-count', supplierCounts[supplierName]);
            } else {
                option.textContent = supplierName;
                option.setAttribute('data-count', '0');
            }
        });
    }

    // Function to update Follow Supplier count (defined globally)
    function updateFollowSupplierCount() {
        const followSupplierSpan = document.getElementById("followSupplierCount");
        if (!followSupplierSpan) return;
        
        const supplierSet = new Set();
        const allRows = document.querySelectorAll("table.wide-table tbody tr");
        
        allRows.forEach(row => {
            // Check stage from data attribute first (more reliable)
            const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
            const stageSelect = row.querySelector('.editable-select-stage');
            const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
            const rowStage = rowStageSelect || rowStageAttr;
            
            // Only count MIP stage rows
            if (rowStage !== 'mip') {
                return;
            }
            
            // Get Order Qty (check all MIP rows, not just visible ones)
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
            
            // Only count suppliers with order qty > 0
            if (qty > 0) {
                const supplierCell = row.querySelector('td[data-column="6"]');
                if (supplierCell) {
                    const supplierSelect = supplierCell.querySelector('select[data-column="supplier"]');
                    if (supplierSelect) {
                        const supplierName = supplierSelect.value.trim();
                        // Count suppliers that have a value and it's not empty/default
                        if (supplierName && supplierName !== '' && supplierName !== 'supplier') {
                            supplierSet.add(supplierName);
                        }
                    }
                }
            }
        });
        
        followSupplierSpan.textContent = supplierSet.size;
    }

    // Filter to show only MIP stage on page load
    function filterByMIPStageOnLoad() {
        const rows = document.querySelectorAll('tbody tr.stage-row');
        rows.forEach(row => {
            const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
            const stageSelect = row.querySelector('.editable-select-stage');
            const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
            const rowStage = rowStageSelect || rowStageAttr;
            
            if (rowStage === 'mip') {
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
    }

    // Initialize filter on page load
    filterByMIPStageOnLoad();
    
    // Update supplier counts on page load
    setTimeout(() => {
        if (updateSupplierCounts) {
            updateSupplierCounts();
        }
    }, 500);

</script>

@endsection