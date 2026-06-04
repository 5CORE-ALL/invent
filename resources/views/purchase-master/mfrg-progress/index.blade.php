@extends('layouts.vertical', ['title' => 'MIP', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
<style>
    /* Stage column: colored dot + invisible select overlay (Forecast Analysis pattern) */
    .mip-stage-dot {
        position: relative;
        width: 44px;
        height: 30px;
        margin: 0 auto;
    }
    .mip-stage-marker {
        width: 100%;
        height: 100%;
        pointer-events: none;
    }
    .mip-stage-dot .stage-status-dot {
        display: inline-block;
        width: 16px;
        height: 16px;
        border-radius: 50%;
    }
    .mip-stage-dot .stage-transit-icon {
        color: #0ea5e9;
        font-size: 15px;
    }
    .mip-stage-dot .stage-stage-select {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
        border: none;
        background: transparent;
        padding: 0;
        margin: 0;
    }

    /* PO column: status dot button + optional PDF magnify button */
    .mip-po-dot-wrap {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }
    .mip-po-dot-btn {
        border: none;
        background: transparent;
        padding: 0;
        cursor: pointer;
        line-height: 0;
    }
    .mip-po-status-dot {
        display: inline-block;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        border: 1px solid rgba(0,0,0,0.1);
    }
    .mip-po-pdf-btn {
        border: 1px solid #cbd5e1;
        background: #fff;
        border-radius: 6px;
        width: 26px;
        height: 26px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: #2563eb;
        font-size: 14px;
        padding: 0;
    }
    .mip-po-pdf-btn:hover {
        background: #eff6ff;
    }

    /* Image column: compact thumb */
    .table-container .wide-table th[data-column="1"],
    .table-container .wide-table td[data-column="1"] {
        width: 52px !important;
        min-width: 52px !important;
        max-width: 52px !important;
        white-space: normal !important;
    }
    .table-container .wide-table th[data-column="1"] {
        padding: 2px !important;
        vertical-align: middle;
        white-space: normal !important;
        line-height: 1.15;
        font-size: 0.65rem;
        overflow: hidden !important;
    }
    .table-container .wide-table td[data-column="1"] {
        padding: 0 !important;
        vertical-align: middle;
        line-height: 0;
    }
    .table-container .wide-table td[data-column="1"] > .mip-image-aspect {
        width: 46px !important;
        height: 46px !important;
        max-width: 46px !important;
        max-height: 46px !important;
        margin: 0 auto;
        box-sizing: border-box;
        flex-shrink: 0;
        overflow: hidden !important;
        position: relative;
    }
    .table-container .wide-table td[data-column="1"] .mip-image-aspect img {
        width: 100% !important;
        height: 100% !important;
        max-width: 46px !important;
        max-height: 46px !important;
        object-fit: contain;
        display: block;
        border-radius: 0;
        box-shadow: none;
        background: transparent;
        vertical-align: top;
    }

    /* MIP pagination — modeled after the Tabulator footer used on /forecast.analysis.
       Placed INSIDE .mip-table-scroll and pinned with position:sticky;bottom:0 so it always
       sits at the visible bottom of the scrollable table area and never gets pushed under
       the page footer regardless of how the outer flex layout sizes things.
       .mip-paginated-out rows are filter-visible but outside the current page window, so
       calculate*() totals still count them. */
    tr.mip-paginated-out { display: none !important; }
    .mip-table-scroll { position: relative; }
    .mip-pagination-bar {
        position: sticky;
        bottom: 0;
        left: 0;
        right: 0;
        z-index: 20;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 8px 12px;
        padding: 5px 12px;
        background: #f4f7fa;
        border-top: 1px solid #262626;
        color: #4b5563;
        font-size: 1rem;
        min-height: 56px;
        /* Make the bar span the full width of the scroll container's content so the bar's
           background fills horizontally even when the wide table forces horizontal scroll.
           Combined with sticky+left:0 + bottom:0 the controls stay anchored to the bottom-
           left of the visible viewport. */
        width: 100%;
        box-sizing: border-box;
    }
    .mip-pagination-bar .mip-pag-info {
        color: #4b5563;
        white-space: nowrap;
        font-size: 0.95rem;
    }
    .mip-pagination-bar .mip-pag-info strong {
        color: #1f2937;
        font-weight: 600;
    }
    .mip-pagination-bar .mip-pag-controls {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        flex-wrap: wrap;
    }
    .mip-pagination-bar select.mip-pag-size {
        width: 84px;
        height: 34px;
        font-size: 0.9rem;
        border-radius: 6px;
    }
    .mip-pagination-bar .mip-pag-pages {
        display: inline-flex;
        align-items: center;
        gap: 0;
        margin: 0 6px;
        flex-wrap: wrap;
    }
    .mip-pagination-bar .mip-pag-btn {
        background: transparent;
        border: 1px solid transparent;
        color: #4b5563;
        padding: 8px 16px;
        margin: 0 4px;
        border-radius: 6px;
        font-size: 0.95rem;
        font-weight: 500;
        line-height: 1;
        cursor: pointer;
        transition: all 0.2s;
    }
    .mip-pagination-bar .mip-pag-btn:hover:not(:disabled) {
        background: #e0eaff;
        color: #2563eb;
    }
    .mip-pagination-bar .mip-pag-btn.active {
        background: #2563eb;
        color: #fff;
    }
    .mip-pagination-bar .mip-pag-btn:disabled {
        opacity: 0.35;
        cursor: not-allowed;
    }
    .mip-pagination-bar .mip-pag-btn.mip-pag-ellipsis {
        cursor: default;
        padding: 8px 6px;
    }
    .mip-pagination-bar .mip-pag-btn.mip-pag-ellipsis:hover { background: transparent; color: #4b5563; }
</style>
@endsection
@section('content')
<div id="mip-page-root" class="mip-viewport-fit">
@include('layouts.shared.page-title', ['page_title' => 'MIP', 'sub_title' => 'MIP'])
<div class="row mip-main-outer g-0">
    <div class="col-12 mip-main-col d-flex flex-column mip-minh-0">
        <div class="card shadow-sm mip-main-card mb-0 d-flex flex-column mip-minh-0">
            <div class="card-body mip-card-body d-flex flex-column mip-minh-0">
                <!-- Filters Row -->
                <div class="column-controls card mb-2 p-2 shadow-sm" id="columnControls" style="background: #f8f9fa; border-radius: 8px;">
                    <div class="d-flex flex-wrap align-items-center gap-2">

                        {{-- Stage --}}
                        <div class="d-flex align-items-center gap-1">
                            <label for="mip-stage-filter" class="mb-0 small fw-semibold text-nowrap text-secondary">Stage</label>
                            <select id="mip-stage-filter" class="form-select form-select-sm" style="width: 130px;">
                                <option value="both">MIP + R2S</option>
                                <option value="mip">MIP only</option>
                                <option value="r2s">R2S only</option>
                            </select>
                        </div>

                        {{-- Bulk Stage --}}
                        <div class="d-flex align-items-center gap-1 border-start ps-2">
                            <label for="mip-bulk-stage-select" class="mb-0 small fw-semibold text-nowrap text-secondary">Bulk Stage</label>
                            <select id="mip-bulk-stage-select" class="form-select form-select-sm" style="width: 120px;">
                                <option value="">— Choose —</option>
                                <option value="appr_req">Appr. Req</option>
                                <option value="mip">MIP</option>
                                <option value="r2s">R2S</option>
                                <option value="transit">Transit</option>
                                <option value="all_good">😊 All Good</option>
                                <option value="to_order_analysis">2 Order</option>
                            </select>
                            <button type="button" class="btn btn-sm btn-primary" id="mip-bulk-stage-apply">Apply</button>
                        </div>

                        {{-- Search inputs --}}
                        <input type="text" class="form-control form-control-sm column-search mip-toolbar-search-input border-start ps-2 ms-1" data-search-column="3" placeholder="Search SKU..." autocomplete="off" style="width: 145px;">
                        <input type="text" class="form-control form-control-sm column-search mip-toolbar-search-input" data-search-column="6" placeholder="Search Supplier..." autocomplete="off" style="width: 145px;">

                        {{-- Stats --}}
                        <div class="d-flex align-items-center gap-3 border-start ps-2 ms-1 flex-wrap">
                            <div class="text-center">
                                <div class="text-muted" style="font-size: 0.7rem; font-weight:600; text-transform:uppercase;">👥 Suppliers</div>
                                <div id="followSupplierCount" class="fw-bold" style="font-size: 1rem;">0</div>
                            </div>
                            <div class="text-center">
                                <div class="text-muted" style="font-size: 0.7rem; font-weight:600; text-transform:uppercase;">💰 Amount</div>
                                <div id="total-amount" class="fw-bold" style="font-size: 1rem;">$0</div>
                            </div>
                            <div class="text-center">
                                <div class="text-muted" style="font-size: 0.7rem; font-weight:600; text-transform:uppercase;">📊 CBM</div>
                                <div id="total-cbm" class="fw-bold" style="font-size: 1rem;">0</div>
                            </div>
                            <div class="text-center">
                                <div class="text-muted" style="font-size: 0.7rem; font-weight:600; text-transform:uppercase;">🔢 Items</div>
                                <div id="total-order-items" class="fw-bold" style="font-size: 1rem;">0</div>
                            </div>
                            <div class="text-center">
                                <div class="text-muted" style="font-size: 0.7rem; font-weight:600; text-transform:uppercase;">TAT MIP</div>
                                <div id="tat-mip-badge" class="fw-bold" style="font-size: 1rem;">—</div>
                            </div>
                        </div>

                        {{-- Action buttons --}}
                        <div class="d-flex align-items-center gap-1 ms-auto flex-wrap">
                            @include('purchase-master.partials.page-info-toolbar', ['pageKey' => 'mip'])
                            <button type="button" id="mip-bulk-edit-ddate-btn" class="btn btn-sm btn-primary fw-semibold d-inline-flex align-items-center gap-1">
                                <i class="fas fa-calendar-alt"></i> Bulk Edit D-Date
                            </button>
                            <button type="button" id="mip-bulk-edit-po-btn" class="btn btn-sm btn-primary fw-semibold d-inline-flex align-items-center gap-1">
                                <i class="fas fa-file-invoice"></i> Bulk Edit PO
                            </button>
                            <select id="mip-export-supplier-select" class="form-select form-select-sm" style="width: 140px;">
                                <option value="">All suppliers</option>
                                @foreach ($suppliers as $supplierName)
                                    <option value="{{ e($supplierName) }}">{{ $supplierName }}</option>
                                @endforeach
                            </select>
                            <button type="button" id="mip-summary-export-btn" class="btn btn-sm btn-success fw-semibold d-inline-flex align-items-center gap-1">
                                <i class="fas fa-file-csv"></i> Export
                            </button>
                        </div>

                    </div>
                </div>

                <!-- Bulk Edit PO Modal -->
                <div class="modal fade" id="bulkEditPoModal" tabindex="-1" aria-labelledby="bulkEditPoModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title" id="bulkEditPoModalLabel">
                                    <i class="fas fa-file-invoice"></i> Bulk Edit PO
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-info mb-3">
                                    <i class="fas fa-info-circle"></i>
                                    <span id="bulk-po-selected-count">0</span> rows selected
                                </div>
                                <div class="mb-3">
                                    <label for="bulk-po-number-input" class="form-label fw-bold">PO Number:</label>
                                    <input type="text" class="form-control" id="bulk-po-number-input" maxlength="100" placeholder="Enter PO number (leave empty to clear)">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary" id="apply-bulk-po-btn">
                                    <i class="fas fa-check"></i> Apply to Selected
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bulk Edit Delivery Date Modal -->
                <div class="modal fade" id="bulkEditDDateModal" tabindex="-1" aria-labelledby="bulkEditDDateModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title" id="bulkEditDDateModalLabel">
                                    <i class="fas fa-calendar-alt"></i> Bulk Edit Delivery Date
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> 
                                    <span id="bulk-edit-selected-count">0</span> rows selected
                                </div>
                                <div class="mb-3">
                                    <label for="bulk-delivery-date-input" class="form-label fw-bold">New Delivery Date:</label>
                                    <input type="date" class="form-control" id="bulk-delivery-date-input">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary" id="apply-bulk-ddate-btn">
                                    <i class="fas fa-check"></i> Apply to Selected
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="wide-table-wrapper table-container mip-table-scroll">
                    <table class="wide-table">
                        <thead>
                            <tr>
                                <th data-column="0" style="width: 50px;">
                                    <input type="checkbox" id="selectAllCheckbox" title="Select All">
                                    <div class="resizer"></div>
                                </th>
                                <th data-column="1" class="p-0 m-0" style="width:52px;min-width:52px;max-width:52px;">Img</th>
                                <th data-column="2" hidden>
                                    Parent
                                    <div class="resizer"></div>
                                    <input type="text" class="form-control column-search" data-search-column="2" placeholder="Search Parent..." style="margin-top:4px; font-size:12px; height:28px;">
                                    <div class="search-results" data-results-column="2" style="position:relative; z-index:10;"></div>
                                </th>
                                <th data-column="50" class="text-center" style="width: 120px; min-width: 100px;" title="Executive Assigned">
                                    Executive
                                    <div class="resizer"></div>
                                    <select class="form-select form-select-sm mt-1 mip-exec-header-filter" style="width:100%; font-size:11px; padding:2px 4px;">
                                        <option value="">— All —</option>
                                        <option value="Atin">Atin</option>
                                        <option value="Jack">Jack</option>
                                        <option value="Nitish">Nitish</option>
                                        <option value="Ajay">Ajay</option>
                                        <option value="Candy">Candy</option>
                                        <option value="Sruti">Sruti</option>
                                    </select>
                                </th>
                                <th data-column="3">
                                    SKU
                                    <div class="resizer"></div>
                                </th>
                                <th data-column="18" class="text-center" hidden>NRP<div class="resizer"></div></th>
                                <th data-column="4" class="text-center">QTY<div class="resizer"></div></th>
                                <th data-column="10" class="text-center">O<br/>Date<div class="resizer"></div></th>
                                <th data-column="11" class="text-center">D<br/>date<div class="resizer"></div></th>
                                <th data-column="5" hidden>Rate<div class="resizer"></div></th>
                                <th data-column="6" class="text-center" style="width: 112.5px; min-width: 112.5px; max-width: 112.5px;">Supplier<div class="resizer"></div></th>
                                <th data-column="26" class="text-center" style="width: 96px; min-width: 88px; max-width: 120px;">Platform<div class="resizer"></div></th>
                                <th data-column="27" class="text-center" style="width: 72px; min-width: 64px;">PO<div class="resizer"></div></th>
                                <th data-column="7" hidden>Advance<br/>Amt<div class="resizer"></div></th>
                                <th data-column="8" hidden>Adv<br/>Date<div class="resizer"></div></th>
                                <th data-column="9" hidden>pay conf.<br/>date<div class="resizer"></div></th>
                                {{-- <th data-column="9">pay term<div class="resizer"></div></th> --}}
                                {{-- <th data-column="12">O Links<div class="resizer"></div></th> --}}
                                <th data-column="12" hidden>value<div class="resizer"></div></th>
                                <th data-column="13" hidden>Payment<br/>Pending<div class="resizer"></div></th>
                                {{-- <th data-column="15">photo<br/>packing<div class="resizer"></div></th> --}}
                                {{-- <th data-column="16">photo int.<br/>sale<div class="resizer"></div></th> --}}
                                <th data-column="14" hidden>CBM<div class="resizer"></div></th>
                                <th data-column="15">T-CBM<div class="resizer"></div></th>
                                <th data-column="23" class="text-center">Pkg Inst<div class="resizer"></div></th>
                                <th data-column="24" class="text-center">U-Manual<div class="resizer"></div></th>
                                <th data-column="25" class="text-center">Compliance<div class="resizer"></div></th>
                                <th data-column="20" class="text-center" hidden>CTN CBM E<div class="resizer"></div></th>
                                <th data-column="17" class="text-center">Stage<div class="resizer"></div></th>
                                {{-- <th data-column="19" class="text-center">BARCODE<br/>&<br/>SKU<div class="resizer"></div></th> --}}
                                {{-- <th data-column="20">artwork<br/>&<br/>maual<br/>book<div class="resizer"></div></th> --}}
                                {{-- <th data-column="21">notes<div class="resizer"></div></th> --}}
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($data as $item)
                                @php
                                    $stageValue = $item->stage ?? '';
                                    $sourceTable = $item->source_table ?? '';
                                    $nrValue = strtoupper(trim($item->nr ?? ''));
                                @endphp
                                {{-- Skip only NR items, but NEVER skip RTS items --}}
                                @if($sourceTable !== 'ready_to_ship' && $nrValue === 'NR')
                                    @continue
                                @endif
                                <tr data-stage="{{ $stageValue ?? '' }}" class="stage-row" data-mip-id="{{ $item->id }}" data-sku="{{ e($item->sku ?? '') }}" data-parent="{{ e($item->parent ?? '') }}">
                                    <td data-column="0" class="text-center">
                                        <input type="checkbox" class="row-checkbox" data-sku="{{ $item->sku }}">
                                    </td>
                                    <td data-column="1" class="p-0 m-0">
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
                                            <div class="w-100 h-100 p-0 m-0 mip-image-aspect">
                                                <img src="{{ $imageUrl }}"
                                                     class="w-100 h-100 hover-img"
                                                     data-src="{{ $imageUrl }}"
                                                     alt=""
                                                     loading="lazy"
                                                     decoding="async"
                                                     style="object-fit: contain; display: block;"
                                                     onerror="this.style.display='none'; var s=this.nextElementSibling; if(s) s.style.display='inline';">
                                                <span class="text-muted mip-img-fallback" style="display: none; line-height: normal;">No</span>
                                            </div>
                                        @else
                                            <span class="text-muted" style="line-height: normal;">No</span>
                                        @endif
                                    </td>
                                    <td data-column="2" class="text-center" hidden>
                                        {{ $item->parent ?? '' }}
                                    </td>
                                    @php
                                        $mipExecVal = trim((string) ($item->exec ?? ''));
                                        $mipExecColors = [
                                            'Atin'   => ['bg' => '#3b82f6', 'text' => '#fff'],
                                            'Jack'   => ['bg' => '#10b981', 'text' => '#fff'],
                                            'Nitish' => ['bg' => '#8b5cf6', 'text' => '#fff'],
                                            'Ajay'   => ['bg' => '#f59e0b', 'text' => '#fff'],
                                            'Candy'  => ['bg' => '#ec4899', 'text' => '#fff'],
                                            'Sruti'  => ['bg' => '#14b8a6', 'text' => '#fff'],
                                        ];
                                        $mipExecBg   = $mipExecColors[$mipExecVal]['bg']   ?? '#e5e7eb';
                                        $mipExecText = $mipExecColors[$mipExecVal]['text']  ?? '#6b7280';
                                    @endphp
                                    <td data-column="50" class="text-center align-middle" data-exec="{{ e($mipExecVal) }}">
                                        <select class="mip-exec-select"
                                            data-sku="{{ e($item->sku ?? '') }}"
                                            data-mip-id="{{ (int) ($item->id ?? 0) }}"
                                            style="width:100%;border:none;border-radius:6px;padding:3px 6px;font-size:0.82rem;font-weight:600;background:{{ $mipExecBg }};color:{{ $mipExecText }};cursor:pointer;outline:none;">
                                            <option value="" {{ $mipExecVal === '' ? 'selected' : '' }}>— Unassigned —</option>
                                            @foreach(['Atin','Jack','Nitish','Ajay','Candy','Sruti'] as $en)
                                                <option value="{{ $en }}" {{ $mipExecVal === $en ? 'selected' : '' }}>{{ $en }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td data-column="3" class="text-center align-middle">
                                        <div class="d-inline-flex align-items-center justify-content-center mip-sku-cell">
                                            <span class="mip-sku-text">{{ $item->sku ?? '' }}</span>
                                            @if(!empty($item->sku))
                                                <button type="button"
                                                    class="btn btn-link mip-copy-sku p-0 border-0"
                                                    data-sku="{{ e($item->sku) }}"
                                                    title="Copy SKU"
                                                    aria-label="Copy SKU">
                                                    <i class="far fa-copy" aria-hidden="true"></i>
                                                </button>
                                            @endif
                                        </div>
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
                                    <td data-column="4" data-qty="{{ $item->qty ?? 0 }}" class="text-center" style="background-color: #e9ecef;">
                                        <input type="number" 
                                            value="{{ $item->qty ?? 0 }}" 
                                            data-sku="{{ $item->sku }}"
                                            data-column="qty"
                                            min="0"
                                            step="1"
                                            style="width:80px; text-align:center; background-color: #fff;"
                                            class="form-control form-control-sm auto-save">
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
                                    <td data-column="10" class="text-center">
                                        <div style="display: flex; flex-direction: column; align-items: center; gap: 2px;">
                                            <input type="date" data-sku="{{ $item->sku }}" data-column="created_at" value="{{ !empty($item->created_at) ? \Carbon\Carbon::parse($item->created_at)->format('Y-m-d') : '' }}" 
                                            class="form-control form-control-sm auto-save d-none" style="width: 80px; font-size: 13px; {{ $textColor }}">
                                            @if ($daysDiff !== null && !empty($formattedDate))
                                                <span style="font-size: 11px; {{ $textColor }}; white-space: nowrap; font-weight: 500;">
                                                    {{ $formattedDate }} ({{ $daysDiff }} D)
                                                </span>
                                            @else
                                                <span class="text-muted" style="font-size: 11px;">-</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td data-column="11" class="text-center">
                                        <input type="date"
                                            value="{{ !empty($item->delivery_date) ? \Carbon\Carbon::parse($item->delivery_date)->format('Y-m-d') : '' }}"
                                            data-sku="{{ $item->sku }}"
                                            data-column="delivery_date"
                                            class="form-control form-control-sm auto-save"
                                            style="width: 80px; font-size: 13px;">
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

                                    <td data-column="6" class="text-center" style="width: 112.5px; min-width: 112.5px; max-width: 112.5px;">
                                        <select data-sku="{{ e($item->sku ?? '') }}" data-column="supplier" class="form-select form-select-sm auto-save" style="min-width: 105px; max-width: 105px; font-size: 12px;">
                                            <option value="">supplier</option>
                                            @foreach ($suppliers as $supplierName)
                                                <option value="{{ $supplierName }}" {{ ($item->supplier ?? '') == $supplierName ? 'selected' : '' }}>
                                                    {{ $supplierName }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td data-column="26" class="text-center align-middle mip-platform-cell" style="min-width: 88px; max-width: 120px;">
                                        @php
                                            $mipPlats = $item->supplier_platform_links ?? [];
                                            $mipPlatCount = is_countable($mipPlats) ? count($mipPlats) : 0;
                                        @endphp
                                        @if ($mipPlatCount > 0)
                                            <div class="dropdown d-inline-block">
                                                <button class="btn btn-sm btn-light dropdown-toggle py-0 px-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 11px; max-width: 100%;">
                                                    Platform ({{ $mipPlatCount }})
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end mip-platform-menu" style="max-height: 220px; overflow-y: auto; min-width: 10rem;">
                                                    @foreach ($mipPlats as $plink)
                                                        <li>
                                                            @if (! empty($plink['url']))
                                                                <a class="dropdown-item py-1 px-2 small" href="{{ $plink['url'] }}" @if (! empty($plink['external'])) target="_blank" rel="noopener noreferrer" @endif>{{ $plink['label'] }}</a>
                                                            @else
                                                                <span class="dropdown-item-text py-1 px-2 small text-muted">{{ $plink['label'] }}{{ ! empty($plink['display']) ? ': '.$plink['display'] : '' }}</span>
                                                            @endif
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td data-column="27" class="text-center align-middle mip-po-cell">
                                        @php
                                            $isRtsRow = ($item->source_table ?? '') === 'ready_to_ship';
                                            $mipPoNum = trim((string) ($item->mip_po_number ?? ''));
                                            $mipPoHas = $mipPoNum !== '';
                                            $mipPoTip = $mipPoHas ? e($mipPoNum) : 'Data Required';
                                            $mipPoColor = $mipPoHas ? '#22c55e' : '#dc3545';
                                        @endphp
                                        @if ($isRtsRow)
                                            <span class="text-muted">—</span>
                                        @else
                                            <div class="mip-po-dot-wrap">
                                                <button type="button"
                                                    class="mip-po-dot-btn"
                                                    data-mip-id="{{ (int) ($item->id ?? 0) }}"
                                                    data-sku="{{ e($item->sku ?? '') }}"
                                                    data-po="{{ e($mipPoNum) }}"
                                                    title="{{ $mipPoTip }}"
                                                    aria-label="{{ $mipPoTip }}">
                                                    <span class="mip-po-status-dot" style="background-color: {{ $mipPoColor }};"></span>
                                                </button>
                                                @if ($mipPoHas)
                                                    <button type="button"
                                                        class="mip-po-pdf-btn"
                                                        data-po="{{ e($mipPoNum) }}"
                                                        title="View PO PDF"
                                                        aria-label="View PO PDF">
                                                        <i class="mdi mdi-magnify" aria-hidden="true"></i>
                                                    </button>
                                                @endif
                                            </div>
                                        @endif
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

                                    <td data-column="14" hidden>
                                        {{ isset($item->CBM) ? number_format($item->CBM, 4) : 'N/A' }}
                                    </td>
                                    <td data-column="15">
                                        <input type="number"
                                            data-sku="{{ $item->sku }}"
                                            data-column="total_cbm"
                                            step="0.000000001"
                                            value="{{ is_numeric($item->qty ?? null) && is_numeric($item->CBM ?? null) ? number_format($item->qty * $item->CBM, 2, '.', '') : '' }}"
                                            class="form-control form-control-sm auto-save"
                                            style="min-width: 90px; width: 100px; font-size: 13px;"
                                            placeholder="T-CBM"
                                            readonly>
                                    </td>
                                    <td data-column="23" class="text-center">
                                        @php
                                            $pkgInst = $item->pkg_inst ?? 'No';
                                            $pkgInstYes = strtoupper(trim($pkgInst)) === 'YES';
                                        @endphp
                                        <span
                                            class="pkg-inst-toggle"
                                            data-sku="{{ $item->sku }}"
                                            data-column="pkg_inst"
                                            data-value="{{ $pkgInstYes ? 'Yes' : 'No' }}"
                                            style="display:inline-block;width:14px;height:14px;border-radius:50%;cursor:pointer;background-color: {{ $pkgInstYes ? '#28a745' : '#dc3545' }};">
                                        </span>
                                    </td>
                                    <td data-column="24" class="text-center">
                                        @php
                                            $uManual = $item->u_manual ?? 'No';
                                            $uManualYes = strtoupper(trim($uManual)) === 'YES';
                                        @endphp
                                        <span
                                            class="u-manual-toggle"
                                            data-sku="{{ $item->sku }}"
                                            data-column="u_manual"
                                            data-value="{{ $uManualYes ? 'Yes' : 'No' }}"
                                            style="display:inline-block;width:14px;height:14px;border-radius:50%;cursor:pointer;background-color: {{ $uManualYes ? '#28a745' : '#dc3545' }};">
                                        </span>
                                    </td>
                                    <td data-column="25" class="text-center">
                                        @php
                                            $compliance = $item->compliance ?? 'No';
                                            $complianceYes = strtoupper(trim($compliance)) === 'YES';
                                        @endphp
                                        <span
                                            class="compliance-toggle"
                                            data-sku="{{ $item->sku }}"
                                            data-column="compliance"
                                            data-value="{{ $complianceYes ? 'Yes' : 'No' }}"
                                            style="display:inline-block;width:14px;height:14px;border-radius:50%;cursor:pointer;background-color: {{ $complianceYes ? '#28a745' : '#dc3545' }};">
                                        </span>
                                    </td>
                                    <td data-column="20" class="text-center" hidden>
                                        {{ isset($item->ctn_cbm_e) && $item->ctn_cbm_e !== null ? number_format($item->ctn_cbm_e, 4) : 'N/A' }}
                                    </td>
                                    <td data-column="17" class="text-center align-middle">
                                        @php
                                            $stageValue = $item->stage ?? '';
                                            $sv = strtolower(trim((string) $stageValue));
                                            $mipStageTips = [
                                                '' => 'Select stage',
                                                'appr_req' => 'Appr Req — Approval',
                                                'mip' => 'MIP',
                                                'r2s' => 'R2S — Ready to ship',
                                                'transit' => 'Transit',
                                                'to_order_analysis' => 'Order — 2 Order',
                                                'all_good' => 'All Good',
                                            ];
                                            $mipStageTip = $mipStageTips[$sv] ?? 'Select stage';
                                            $mipDotColor = '#94a3b8';
                                            if ($sv === 'appr_req') {
                                                $mipDotColor = '#facc15';
                                            } elseif ($sv === 'mip') {
                                                $mipDotColor = '#2563eb';
                                            } elseif ($sv === 'to_order_analysis') {
                                                $mipDotColor = '#c2410c';
                                            } elseif ($sv === 'r2s') {
                                                $mipDotColor = '#16a34a';
                                            } elseif ($sv === 'all_good') {
                                                $mipDotColor = '#22c55e';
                                            }
                                        @endphp
                                        <div class="stage-dot-cell mip-stage-dot position-relative d-flex justify-content-center align-items-center mx-auto" title="{{ $mipStageTip }}">
                                            <span class="mip-stage-marker d-inline-flex justify-content-center align-items-center" style="pointer-events: none;">
                                                @if ($sv === 'transit')
                                                    <i class="fas fa-truck stage-transit-icon" aria-hidden="true"></i>
                                                @else
                                                    <span class="stage-status-dot" style="background-color: {{ $mipDotColor }};" aria-hidden="true"></span>
                                                @endif
                                            </span>
                                            <select class="form-select form-select-sm editable-select-stage stage-stage-select position-absolute top-0 start-0 w-100 h-100"
                                                data-type="Stage"
                                                data-sku="{{ $item->sku }}"
                                                data-parent="{{ e($item->parent ?? '') }}"
                                                aria-label="{{ $mipStageTip }}">
                                                <option value="">Select</option>
                                                <option value="appr_req" {{ $sv === 'appr_req' ? 'selected' : '' }}>Appr. Req</option>
                                                <option value="mip" {{ $sv === 'mip' ? 'selected' : '' }}>MIP</option>
                                                <option value="r2s" {{ $sv === 'r2s' ? 'selected' : '' }}>R2S</option>
                                                <option value="transit" {{ $sv === 'transit' ? 'selected' : '' }}>Transit</option>
                                                <option value="all_good" {{ $sv === 'all_good' ? 'selected' : '' }}>😊 All Good</option>
                                                <option value="to_order_analysis" {{ $sv === 'to_order_analysis' ? 'selected' : '' }}>2 Order</option>
                                            </select>
                                        </div>
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
                    <div class="mip-pagination-bar" id="mip-pagination">
                        <div class="mip-pag-info">
                            Showing <strong><span id="mip-pag-from">0</span> to <span id="mip-pag-to">0</span></strong>
                            of <strong><span id="mip-pag-total">0</span></strong> rows
                        </div>
                        <div class="mip-pag-controls">
                            <label class="d-inline-flex align-items-center gap-2 mb-0">
                                <span class="text-muted">Page Size</span>
                                <select class="form-select form-select-sm mip-pag-size" id="mip-pag-size">
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                    <option value="200">200</option>
                                    <option value="500">500</option>
                                    <option value="0">All</option>
                                </select>
                            </label>
                            <button type="button" class="mip-pag-btn" id="mip-pag-first" title="First page">First</button>
                            <button type="button" class="mip-pag-btn" id="mip-pag-prev" title="Previous page">Prev</button>
                            <span class="mip-pag-pages" id="mip-pag-pages"></span>
                            <button type="button" class="mip-pag-btn" id="mip-pag-next" title="Next page">Next</button>
                            <button type="button" class="mip-pag-btn" id="mip-pag-last" title="Last page">Last</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<script>
    /**
     * Stages displayed on MIP In Progress page. The Stage filter dropdown lets the user
     * narrow to MIP only / R2S only / or both. The selection is persisted in localStorage
     * so it survives page reloads.
     */
    let mipStageFilter = (function () {
        const v = localStorage.getItem('mipStageFilter');
        return (v === 'mip' || v === 'r2s') ? v : 'both';
    })();
    function isMipPageStage(rowStage) {
        if (mipStageFilter === 'mip') return rowStage === 'mip';
        if (mipStageFilter === 'r2s') return rowStage === 'r2s';
        return rowStage === 'mip' || rowStage === 'r2s';
    }
    /**
     * Re-run the row visibility / totals / pagination pipeline. Defined here as a fallback
     * for the case where window.filterByMIPStage isn't wired yet (its DOMContentLoaded
     * handler runs later). Once `filterByMIPStage` is exposed we use that directly so the
      */
    function mipReapplyStageFilter() {
        if (typeof window.filterByMIPStage === 'function') {
            window.filterByMIPStage();
            return;
        }
        document.querySelectorAll('table.wide-table tbody tr').forEach(function (row) {
            const rowStageAttr = row.getAttribute('data-stage')
                ? row.getAttribute('data-stage').toLowerCase().trim() : '';
            const stageSelect = row.querySelector('.editable-select-stage');
            const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
            const rowStage = rowStageSelect || rowStageAttr;
            row.style.display = isMipPageStage(rowStage) ? '' : 'none';
        });
        if (typeof calculateTotalCBM === 'function') calculateTotalCBM();
        if (typeof calculateTotalAmount === 'function') calculateTotalAmount();
        if (typeof calculateTotalOrderItems === 'function') calculateTotalOrderItems();
        if (typeof updateFollowSupplierCount === 'function') updateFollowSupplierCount();
        if (typeof mipApplyPagination === 'function') {
            mipPagCurrentPage = 1;
            mipApplyPagination();
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const sel = document.getElementById('mip-stage-filter');
        if (!sel) return;
        sel.value = mipStageFilter;
        sel.addEventListener('change', function () {
            const v = this.value;
            mipStageFilter = (v === 'mip' || v === 'r2s') ? v : 'both';
            try { localStorage.setItem('mipStageFilter', mipStageFilter); } catch (e) {}
            mipReapplyStageFilter();
        });
    });

    /**
     * Client-side pagination — runs on top of the existing filter layer.
     * Filter-hidden rows already have inline style.display='none'; pagination further hides
     * rows outside the current page window using a CSS class (.mip-paginated-out) so the
     * existing calculate*() / supplier-rotation code that checks `row.style.display !== 'none'`
     * still counts every filter-visible row.
     */
    let mipPagPageSize = (function () {
        const raw = parseInt(localStorage.getItem('mipPagPageSize') || '100', 10);
        if ([0, 50, 100, 200, 500].indexOf(raw) === -1) return 100;
        return raw;
    })();
    let mipPagCurrentPage = 1;
    let mipApplyPaginationScheduled = false;

    function mipPagFilterVisibleRows() {
        const rows = document.querySelectorAll('table.wide-table tbody tr');
        const out = [];
        for (let i = 0; i < rows.length; i++) {
            if (rows[i].style.display !== 'none') out.push(rows[i]);
        }
        return out;
    }

    /** Build the numbered-page button list (1 … 4 5 6 … N) similar to Tabulator's paginator. */
    function mipBuildPageButtons(totalPages) {
        const pagesEl = document.getElementById('mip-pag-pages');
        if (!pagesEl) return;
        const cur = mipPagCurrentPage;
        const around = 2; // pages on each side of the current page
        const set = new Set();
        set.add(1);
        set.add(totalPages);
        for (let p = cur - around; p <= cur + around; p++) {
            if (p >= 1 && p <= totalPages) set.add(p);
        }
        const sorted = Array.from(set).sort(function (a, b) { return a - b; });
        let html = '';
        let prev = 0;
        sorted.forEach(function (p) {
            if (prev && p - prev > 1) {
                html += '<button type="button" class="mip-pag-btn mip-pag-ellipsis" disabled>…</button>';
            }
            const active = p === cur ? ' active' : '';
            html += '<button type="button" class="mip-pag-btn' + active + '" data-mip-pag-page="' + p + '">' + p + '</button>';
            prev = p;
        });
        pagesEl.innerHTML = html;
        pagesEl.querySelectorAll('button[data-mip-pag-page]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const p = parseInt(this.getAttribute('data-mip-pag-page'), 10);
                if (isFinite(p) && p >= 1) {
                    mipPagCurrentPage = p;
                    mipApplyPagination();
                }
            });
        });
    }

    function mipApplyPagination() {
        const wrapper = document.getElementById('mip-pagination');
        if (!wrapper) return;
        const visibleRows = mipPagFilterVisibleRows();
        const total = visibleRows.length;
        const size = mipPagPageSize > 0 ? mipPagPageSize : Math.max(total, 1);
        const totalPages = Math.max(1, Math.ceil(total / size));
        if (mipPagCurrentPage > totalPages) mipPagCurrentPage = totalPages;
        if (mipPagCurrentPage < 1) mipPagCurrentPage = 1;
        const startIdx = (mipPagCurrentPage - 1) * size;
        const endIdx = mipPagPageSize > 0 ? Math.min(startIdx + size, total) : total;

        document.querySelectorAll('table.wide-table tbody tr.mip-paginated-out')
            .forEach(function (r) { r.classList.remove('mip-paginated-out'); });

        for (let i = 0; i < visibleRows.length; i++) {
            if (i < startIdx || i >= endIdx) {
                visibleRows[i].classList.add('mip-paginated-out');
            }
        }

        const fromEl = document.getElementById('mip-pag-from');
        const toEl = document.getElementById('mip-pag-to');
        const totalEl = document.getElementById('mip-pag-total');
        if (fromEl) fromEl.textContent = total === 0 ? '0' : String(startIdx + 1);
        if (toEl) toEl.textContent = String(endIdx);
        if (totalEl) totalEl.textContent = String(total);

        const first = document.getElementById('mip-pag-first');
        const prev = document.getElementById('mip-pag-prev');
        const next = document.getElementById('mip-pag-next');
        const last = document.getElementById('mip-pag-last');
        if (first) first.disabled = mipPagCurrentPage <= 1;
        if (prev) prev.disabled = mipPagCurrentPage <= 1;
        if (next) next.disabled = mipPagCurrentPage >= totalPages;
        if (last) last.disabled = mipPagCurrentPage >= totalPages;

        mipBuildPageButtons(totalPages);
    }

    /** Debounced re-pagination so rapid filter calls don't thrash the DOM. */
    function mipSchedulePagination() {
        if (mipApplyPaginationScheduled) return;
        mipApplyPaginationScheduled = true;
        requestAnimationFrame(function () {
            mipApplyPaginationScheduled = false;
            mipApplyPagination();
        });
    }

    function mipPagSetupControls() {
        const sizeSel = document.getElementById('mip-pag-size');
        if (sizeSel) {
            sizeSel.value = String(mipPagPageSize);
            sizeSel.addEventListener('change', function () {
                const v = parseInt(this.value, 10);
                mipPagPageSize = isFinite(v) ? v : 100;
                try { localStorage.setItem('mipPagPageSize', String(mipPagPageSize)); } catch (e) {}
                mipPagCurrentPage = 1;
                mipApplyPagination();
            });
        }
        const first = document.getElementById('mip-pag-first');
        if (first) first.addEventListener('click', function () {
            mipPagCurrentPage = 1; mipApplyPagination();
        });
        const prev = document.getElementById('mip-pag-prev');
        if (prev) prev.addEventListener('click', function () {
            if (mipPagCurrentPage > 1) { mipPagCurrentPage--; mipApplyPagination(); }
        });
        const next = document.getElementById('mip-pag-next');
        if (next) next.addEventListener('click', function () {
            mipPagCurrentPage++; mipApplyPagination();
        });
        const last = document.getElementById('mip-pag-last');
        if (last) last.addEventListener('click', function () {
            mipPagCurrentPage = 1e9; mipApplyPagination();
        });
    }
    document.addEventListener('DOMContentLoaded', mipPagSetupControls);

    var mipTabulatorLoadPromise = null;
    function mipEnsureTabulator() {
        if (typeof Tabulator !== 'undefined') {
            return Promise.resolve();
        }
        if (!mipTabulatorLoadPromise) {
            mipTabulatorLoadPromise = new Promise(function (resolve, reject) {
                if (!document.querySelector('link[data-mip-tabulator-css]')) {
                    var link = document.createElement('link');
                    link.rel = 'stylesheet';
                    link.href = 'https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css';
                    link.setAttribute('data-mip-tabulator-css', '1');
                    document.head.appendChild(link);
                }
                var s = document.createElement('script');
                s.src = 'https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js';
                s.async = true;
                s.onload = function () { resolve(); };
                s.onerror = function () { reject(new Error('Tabulator failed to load')); };
                document.body.appendChild(s);
            });
        }
        return mipTabulatorLoadPromise;
    }

    const popup = document.createElement('img');
    popup.className = 'preview-popup';
    document.body.appendChild(popup);

    function mipCopySkuToClipboard(text, onDone) {
        const t = String(text || '').trim();
        if (!t) return;
        const finish = typeof onDone === 'function' ? onDone : function () {};
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(t).then(finish).catch(function () {
                mipCopySkuFallback(t, finish);
            });
            return;
        }
        mipCopySkuFallback(t, finish);
    }

    function mipCopySkuFallback(text, onDone) {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
            if (typeof onDone === 'function') {
                onDone();
            }
        } catch (err) {
            alert('Unable to copy SKU.');
        }
        document.body.removeChild(ta);
    }

    window.MIP_SUPPLIER_PLATFORMS = @json($supplier_platforms_by_name ?? []);

    function mipEscapeHtml(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function mipPlatformCellHtml(supplierName) {
        const map = window.MIP_SUPPLIER_PLATFORMS || {};
        const list = map[supplierName] || [];
        if (!list.length) {
            return '<span class="text-muted">-</span>';
        }
        let items = '';
        for (let i = 0; i < list.length; i++) {
            const p = list[i];
            if (p.url) {
                const ext = p.external ? ' target="_blank" rel="noopener noreferrer"' : '';
                items += '<li><a class="dropdown-item py-1 px-2 small" href="' + mipEscapeHtml(p.url) + '"' + ext + '>' + mipEscapeHtml(p.label) + '</a></li>';
            } else {
                items += '<li><span class="dropdown-item-text py-1 px-2 small text-muted">' + mipEscapeHtml(p.label) + (p.display ? ': ' + mipEscapeHtml(p.display) : '') + '</span></li>';
            }
        }
        return '<div class="dropdown d-inline-block">' +
            '<button class="btn btn-sm btn-light dropdown-toggle py-0 px-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 11px; max-width: 100%;">Platform (' + list.length + ')</button>' +
            '<ul class="dropdown-menu dropdown-menu-end mip-platform-menu" style="max-height: 220px; overflow-y: auto; min-width: 10rem;">' + items + '</ul></div>';
    }

    function calculateTotalCBM() {
        let totalCBM = 0;
        document.querySelectorAll('table.wide-table tbody tr').forEach(row => {
            const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
            const stageSelect = row.querySelector('.editable-select-stage');
            const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
            const rowStage = rowStageSelect || rowStageAttr;
            if (!isMipPageStage(rowStage)) return;
            if (row.style.display !== "none") {
                const input = row.querySelector('input[data-column="total_cbm"]');
                if (input) {
                    const value = parseFloat(input.value);
                    if (!isNaN(value)) totalCBM += value;
                }
            }
        });
        const el = document.getElementById('total-cbm');
        if (el) el.textContent = String(Math.round(totalCBM));
    }

    /** Calendar days from O Date (YYYY-MM-DD) to today; matches MIP O Date display. Future dates → 0. */
    function mipDaysSinceODate(isoYmd) {
        if (!isoYmd || typeof isoYmd !== 'string') {
            return 0;
        }
        const t = isoYmd.trim();
        if (!/^\d{4}-\d{2}-\d{2}$/.test(t)) {
            return 0;
        }
        const p = t.split('-');
        const y = parseInt(p[0], 10);
        const m = parseInt(p[1], 10) - 1;
        const d = parseInt(p[2], 10);
        if (isNaN(y) || isNaN(m) || isNaN(d)) {
            return 0;
        }
        const o = new Date(y, m, d);
        o.setHours(0, 0, 0, 0);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const diff = Math.round((today - o) / 86400000);
        return diff > 0 ? diff : 0;
    }

    /** Sum of (days since O date) for visible MIP rows ÷ row count; missing O date = 0 days. */
    function calculateTatMip() {
        const el = document.getElementById('tat-mip-badge');
        if (!el) {
            return;
        }
        let sumDays = 0;
        let n = 0;
        document.querySelectorAll('table.wide-table tbody tr').forEach(function (row) {
            const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
            const stageSelect = row.querySelector('.editable-select-stage');
            const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
            const rowStage = rowStageSelect || rowStageAttr;
            if (!isMipPageStage(rowStage)) {
                return;
            }
            if (row.style.display === 'none') {
                return;
            }
            n++;
            const inp = row.querySelector('input[data-column="created_at"]');
            const v = inp && inp.value ? String(inp.value).trim() : '';
            sumDays += mipDaysSinceODate(v);
        });
        if (n === 0) {
            el.textContent = '—';
            return;
        }
        const avg = sumDays / n;
        el.textContent = (Math.round(avg * 10) / 10).toFixed(1) + ' d';
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.documentElement.setAttribute("data-sidenav-size", "condensed");

        const table = document.querySelector('.wide-table');
        const mipTbody = table.querySelector('tbody');
        /** Static snapshot of row nodes at load; filter/sort re-query when needed inside rAF. */
        const rows = table.querySelectorAll('tbody tr');



        // Image preview: one delegated listener (was 3 listeners × every row image)
        if (mipTbody) {
            let hoverMoveRaf = false;
            let hoverClientX = 0;
            let hoverClientY = 0;
            mipTbody.addEventListener('mouseover', function (e) {
                const img = e.target.closest('.hover-img');
                if (!img || !mipTbody.contains(img)) return;
                const src = img.dataset.src || img.getAttribute('src');
                if (src) popup.src = src;
                popup.style.display = 'block';
            });
            mipTbody.addEventListener('mousemove', function (e) {
                if (popup.style.display !== 'block') return;
                hoverClientX = e.clientX;
                hoverClientY = e.clientY;
                if (hoverMoveRaf) return;
                hoverMoveRaf = true;
                requestAnimationFrame(function () {
                    hoverMoveRaf = false;
                    popup.style.top = (hoverClientY + 20) + 'px';
                    popup.style.left = (hoverClientX + 20) + 'px';
                });
            });
            mipTbody.addEventListener('mouseout', function (e) {
                const img = e.target.closest('.hover-img');
                if (!img || !mipTbody.contains(img)) return;
                const rel = e.relatedTarget;
                if (rel && img.contains(rel)) return;
                popup.style.display = 'none';
            });

            mipTbody.addEventListener('click', function (e) {
                const btn = e.target.closest('.mip-copy-sku');
                if (!btn || !mipTbody.contains(btn)) return;
                e.preventDefault();
                e.stopPropagation();
                const sku = (btn.dataset.sku || '').trim();
                if (!sku) return;
                const icon = btn.querySelector('i');
                const prevClass = icon ? icon.className : '';
                mipCopySkuToClipboard(sku, function () {
                    if (icon) {
                        icon.className = 'fas fa-check';
                    }
                    btn.classList.add('text-success');
                    setTimeout(function () {
                        if (icon) {
                            icon.className = prevClass;
                        }
                        btn.classList.remove('text-success');
                    }, 1100);
                });
            });
        }

        initColumnResizing();

        // Column Visibility
        setupColumnVisibility();


        // ✅ Column-Specific Filter (Professional Version)
        setupColumnSearch();

        // Inline Auto-Save
        setupAutoSave();

        // Executive column — save on change + update badge colour live
        (function () {
            const MIP_EXEC_COLORS = {
                'Atin':   { bg: '#3b82f6', text: '#fff' },
                'Jack':   { bg: '#10b981', text: '#fff' },
                'Nitish': { bg: '#8b5cf6', text: '#fff' },
                'Ajay':   { bg: '#f59e0b', text: '#fff' },
                'Candy':  { bg: '#ec4899', text: '#fff' },
                'Sruti':  { bg: '#14b8a6', text: '#fff' },
            };

            // Save on change
            if (mipTbody) {
                mipTbody.addEventListener('change', async function (e) {
                    const sel = e.target.closest('.mip-exec-select');
                    if (!sel) return;
                    const newVal = sel.value;
                    const sku   = sel.dataset.sku   || '';
                    const mipId = sel.dataset.mipId || '';
                    const c = MIP_EXEC_COLORS[newVal] || { bg: '#e5e7eb', text: '#6b7280' };
                    sel.style.background = c.bg;
                    sel.style.color      = c.text;
                    // update data-exec for filter
                    const td = sel.closest('td[data-column="50"]');
                    if (td) td.setAttribute('data-exec', newVal);
                    try {
                        const res = await fetch('/update-link', {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({ sku: sku, row_id: 0, column: 'Exec', value: newVal || null }),
                        });
                        const d = await res.json().catch(function () { return {}; });
                        if (!res.ok || !d.success) throw new Error(d.message || 'Save failed');
                    } catch (err) {
                        alert('Could not save executive: ' + err.message);
                    }
                });
            }
        })();

        // Stage Update Handler
        setupStageUpdate();
        setupMipBulkStage();
        setupNRPUpdate();

        // Defer row-heavy work so first paint + toolbar stay responsive (large tables).
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                filterByMIPStage();
                const rowsAfter = table.querySelectorAll('tbody tr');
                const visibleMipRows = Array.from(rowsAfter).filter(r => {
                    const stageAttr = (r.getAttribute('data-stage') || '').toLowerCase().trim();
                    const stageSelect = r.querySelector('.editable-select-stage');
                    const stage = (stageSelect ? stageSelect.value : '').toLowerCase().trim() || stageAttr;
                    return (stage === 'mip' || stage === 'r2s' || stage === 'rts') && r.style.display !== 'none';
                });
                if (visibleMipRows.length > 0 && typeof sortRowsByOrderDate === 'function') {
                    sortRowsByOrderDate(visibleMipRows);
                }

                setupCurrencyConversion();
                setupOlinkEditor();

                calculateTotalCBM();
                calculateTotalAmount();
                calculateTotalOrderItems();
            });
        });

        // Pkg Inst / U-Manual / Compliance dots: one delegated handler (was 3 listeners × rows)
        if (mipTbody) {
            mipTbody.addEventListener('click', function (e) {
                const dot = e.target.closest('.pkg-inst-toggle, .u-manual-toggle, .compliance-toggle');
                if (!dot || !mipTbody.contains(dot)) return;
                const sku = dot.dataset.sku;
                if (!sku) return;
                let column = dot.dataset.column;
                if (!column) {
                    if (dot.classList.contains('pkg-inst-toggle')) column = 'pkg_inst';
                    else if (dot.classList.contains('u-manual-toggle')) column = 'u_manual';
                    else column = 'compliance';
                }
                const label = column === 'pkg_inst' ? 'Pkg Inst.' : column === 'u_manual' ? 'U-Manual.' : 'Compliance.';
                const current = (dot.dataset.value || 'No').toLowerCase() === 'yes' ? 'Yes' : 'No';
                const next = current === 'Yes' ? 'No' : 'Yes';
                const row = dot.closest('tr');
                const mipId = row ? String(row.getAttribute('data-mip-id') || '').trim() : '';

                fetch('/mfrg-progresses/inline-update-by-sku', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        sku,
                        column,
                        value: next,
                        mip_id: mipId !== '' ? mipId : undefined,
                    })
                })
                    .then(res => res.json())
                    .then(res => {
                        if (!res.success) {
                            alert('Error: ' + (res.message || 'Failed to update ' + label));
                            return;
                        }
                        dot.dataset.value = next;
                        dot.style.backgroundColor = next === 'Yes' ? '#28a745' : '#dc3545';
                    })
                    .catch(() => alert('AJAX error occurred.'));
            });
        }


        // Summary Export to Excel
        const summaryExportBtn = document.getElementById('mip-summary-export-btn');
        if (summaryExportBtn) summaryExportBtn.addEventListener('click', exportMipSummaryToCsv);

        // Bulk Edit Delivery Date
        const bulkEditDDateBtn = document.getElementById('mip-bulk-edit-ddate-btn');
        if (bulkEditDDateBtn) {
            bulkEditDDateBtn.addEventListener('click', function() {
                const selectedCheckboxes = document.querySelectorAll('.row-checkbox:checked');
                if (selectedCheckboxes.length === 0) {
                    alert('Please select at least one row to edit');
                    return;
                }
                document.getElementById('bulk-edit-selected-count').textContent = selectedCheckboxes.length;
                const bulkModal = new bootstrap.Modal(document.getElementById('bulkEditDDateModal'));
                bulkModal.show();
            });
        }

        // Apply bulk delivery date
        const applyBulkDDateBtn = document.getElementById('apply-bulk-ddate-btn');
        if (applyBulkDDateBtn) {
            applyBulkDDateBtn.addEventListener('click', async function() {
                const newDate = document.getElementById('bulk-delivery-date-input').value;
                if (!newDate) {
                    alert('Please select a delivery date');
                    return;
                }
                
                const selectedCheckboxes = document.querySelectorAll('.row-checkbox:checked');
                const skus = Array.from(selectedCheckboxes).map(cb => cb.dataset.sku).filter(s => s);
                
                if (skus.length === 0) {
                    alert('No valid SKUs selected');
                    return;
                }
                
                applyBulkDDateBtn.disabled = true;
                applyBulkDDateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
                
                try {
                    const response = await fetch('/mfrg/bulk-update-delivery-date', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            skus: skus,
                            delivery_date: newDate
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        // Update the UI for each row
                        selectedCheckboxes.forEach(checkbox => {
                            const row = checkbox.closest('tr');
                            const dateInput = row.querySelector('input[data-column="delivery_date"]');
                            if (dateInput) {
                                dateInput.value = newDate;
                            }
                        });
                        
                        alert(`Successfully updated ${result.updated_count || skus.length} row(s)`);
                        bootstrap.Modal.getInstance(document.getElementById('bulkEditDDateModal')).hide();
                        
                        // Uncheck all checkboxes
                        selectedCheckboxes.forEach(cb => cb.checked = false);
                    } else {
                        alert('Error: ' + (result.message || 'Failed to update'));
                    }
                } catch (error) {
                    alert('Error updating delivery dates: ' + error.message);
                } finally {
                    applyBulkDDateBtn.disabled = false;
                    applyBulkDDateBtn.innerHTML = '<i class="fas fa-check"></i> Apply to Selected';
                }
            });
        }

        let activeMipPoRow = null;
        let activeMipPoId = null;
        let activeMipPoSku = null;

        function mipEscapeAttr(s) {
            return String(s ?? '')
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }

        function renderMipPoDotHtml(mipId, sku, poNumber) {
            const po = String(poNumber || '').trim();
            const has = po.length > 0;
            const tip = has ? mipEscapeAttr(po) : 'Data Required';
            const color = has ? '#22c55e' : '#dc3545';
            const pdfBtn = has
                ? '<button type="button" class="mip-po-pdf-btn" data-po="' + mipEscapeAttr(po) + '" title="View PO PDF" aria-label="View PO PDF">' +
                    '<i class="mdi mdi-magnify" aria-hidden="true"></i></button>'
                : '';
            return '<div class="mip-po-dot-wrap">' +
                '<button type="button" class="mip-po-dot-btn" data-mip-id="' + mipId + '" data-sku="' + mipEscapeAttr(sku) + '" data-po="' + mipEscapeAttr(po) + '" title="' + tip + '" aria-label="' + tip + '">' +
                '<span class="mip-po-status-dot" style="background-color:' + color + ';"></span></button>' +
                pdfBtn +
                '</div>';
        }

        function openMipPoPdf(poNumber) {
            const po = String(poNumber || '').trim();
            if (!po) return;
            window.open('/purchase-order/by-po/' + encodeURIComponent(po) + '/generate-pdf', '_blank', 'noopener');
        }

        function updateMipPoCell(row, poNumber) {
            if (!row) return;
            const cell = row.querySelector('td[data-column="27"]');
            if (!cell || cell.querySelector(':scope > .text-muted')) return;
            const mipId = row.getAttribute('data-mip-id') || '';
            const sku = row.getAttribute('data-sku') || '';
            cell.innerHTML = renderMipPoDotHtml(mipId, sku, poNumber);
        }

        function openMipPoModal(mipId, sku, poNumber, row) {
            activeMipPoRow = row || null;
            activeMipPoId = mipId;
            activeMipPoSku = sku;
            document.getElementById('mipPoModalSku').textContent = sku ? '(' + sku + ')' : '';
            document.getElementById('mipPoModalInput').value = poNumber || '';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('mipPoDataModal')).show();
            setTimeout(function () {
                const inp = document.getElementById('mipPoModalInput');
                if (inp) {
                    inp.focus();
                    inp.select();
                }
            }, 300);
        }

        if (mipTbody) {
            mipTbody.addEventListener('click', function (e) {
                const pdfBtn = e.target.closest('.mip-po-pdf-btn');
                if (pdfBtn && mipTbody.contains(pdfBtn)) {
                    e.preventDefault();
                    e.stopPropagation();
                    openMipPoPdf(pdfBtn.getAttribute('data-po') || '');
                    return;
                }
                const poBtn = e.target.closest('.mip-po-dot-btn');
                if (!poBtn || !mipTbody.contains(poBtn)) return;
                e.preventDefault();
                e.stopPropagation();
                openMipPoModal(
                    poBtn.getAttribute('data-mip-id'),
                    poBtn.getAttribute('data-sku') || '',
                    poBtn.getAttribute('data-po') || '',
                    poBtn.closest('tr')
                );
            });
        }

        const mipPoModalSaveBtn = document.getElementById('mipPoModalSaveBtn');
        if (mipPoModalSaveBtn) {
            mipPoModalSaveBtn.addEventListener('click', async function () {
                if (!activeMipPoId) return;
                const inp = document.getElementById('mipPoModalInput');
                const poNumber = (inp?.value || '').trim().slice(0, 100);
                mipPoModalSaveBtn.disabled = true;
                try {
                    const res = await fetch('/mfrg-progress-po/update', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        },
                        body: JSON.stringify({
                            mfrg_progress_id: parseInt(activeMipPoId, 10),
                            sku: activeMipPoSku || '',
                            po_number: poNumber,
                        }),
                    });
                    const data = await res.json();
                    if (!data.success) {
                        throw new Error(data.message || 'Save failed');
                    }
                    updateMipPoCell(activeMipPoRow, data.po_number != null ? String(data.po_number) : poNumber);
                    bootstrap.Modal.getInstance(document.getElementById('mipPoDataModal'))?.hide();
                } catch (err) {
                    alert(err.message || 'Save failed');
                } finally {
                    mipPoModalSaveBtn.disabled = false;
                }
            });
        }

        document.getElementById('mipPoDataModal')?.addEventListener('hidden.bs.modal', function () {
            activeMipPoRow = null;
            activeMipPoId = null;
            activeMipPoSku = null;
        });

        const bulkEditPoBtn = document.getElementById('mip-bulk-edit-po-btn');
        if (bulkEditPoBtn) {
            bulkEditPoBtn.addEventListener('click', function () {
                const selectedCheckboxes = document.querySelectorAll('.row-checkbox:checked');
                if (selectedCheckboxes.length === 0) {
                    alert('Please select at least one row to edit');
                    return;
                }
                document.getElementById('bulk-po-selected-count').textContent = selectedCheckboxes.length;
                document.getElementById('bulk-po-number-input').value = '';
                bootstrap.Modal.getOrCreateInstance(document.getElementById('bulkEditPoModal')).show();
            });
        }

        const applyBulkPoBtn = document.getElementById('apply-bulk-po-btn');
        if (applyBulkPoBtn) {
            applyBulkPoBtn.addEventListener('click', async function () {
                const poNumber = (document.getElementById('bulk-po-number-input')?.value || '').trim().slice(0, 100);
                const selectedCheckboxes = document.querySelectorAll('.row-checkbox:checked');
                const items = [];
                selectedCheckboxes.forEach(function (cb) {
                    const row = cb.closest('tr');
                    if (!row) return;
                    const mipId = parseInt(row.getAttribute('data-mip-id') || '', 10);
                    if (!mipId) return;
                    items.push({
                        mfrg_progress_id: mipId,
                        sku: row.getAttribute('data-sku') || cb.dataset.sku || '',
                    });
                });
                if (items.length === 0) {
                    alert('No valid MIP rows selected');
                    return;
                }
                applyBulkPoBtn.disabled = true;
                applyBulkPoBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
                try {
                    const response = await fetch('/mfrg-progress-po/bulk-update', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        },
                        body: JSON.stringify({ items: items, po_number: poNumber }),
                    });
                    const result = await response.json();
                    if (!result.success) {
                        throw new Error(result.message || 'Failed to update');
                    }
                    const savedPo = result.po_number != null ? String(result.po_number) : poNumber;
                    selectedCheckboxes.forEach(function (cb) {
                        updateMipPoCell(cb.closest('tr'), savedPo);
                    });
                    alert('Successfully updated ' + (result.updated_count || items.length) + ' row(s)');
                    bootstrap.Modal.getInstance(document.getElementById('bulkEditPoModal'))?.hide();
                    selectedCheckboxes.forEach(function (cb) { cb.checked = false; });
                } catch (error) {
                    alert('Error updating PO: ' + error.message);
                } finally {
                    applyBulkPoBtn.disabled = false;
                    applyBulkPoBtn.innerHTML = '<i class="fas fa-check"></i> Apply to Selected';
                }
            });
        }




        function initColumnResizing() {
            const resizers = document.querySelectorAll('.resizer');
            resizers.forEach(resizer => {
                resizer.addEventListener('mousedown', initResize);
            });

            function initResize(e) {
                e.preventDefault();
                const th = e.target.parentElement;
                if (th.getAttribute('data-column') === '1') {
                    return;
                }
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
                if (col === null || col === '') return;
                widths[col] = col === '1' ? 52 : th.offsetWidth;
            });
            localStorage.setItem('columnWidths_mfrg', JSON.stringify(widths));
        }

        function restoreColumnWidths() {
            const widths = JSON.parse(localStorage.getItem('columnWidths_mfrg') || '{}');
                Object.keys(widths).forEach(col => {
                    const th = document.querySelector(`.wide-table thead th[data-column="${col}"]`);
                    if (th) {
                        const w = col === '1' ? 52 : widths[col];
                        th.style.width = th.style.minWidth = th.style.maxWidth = w + 'px';
                    }
                });
            }
            restoreColumnWidths();
        }

        function setupColumnVisibility() {
            const ths = document.querySelectorAll('.wide-table thead th');

            function getHiddenColumns() {
                return JSON.parse(localStorage.getItem('hiddenColumns_mfrg') || '[]');
            }

            const hiddenColumns = getHiddenColumns();
            ths.forEach((th) => {
                const columnIndex = th.getAttribute('data-column');
                if (!columnIndex) return;
                if (columnIndex === '1') return;
                if (hiddenColumns.includes(columnIndex)) {
                    document.querySelectorAll(`[data-column="${columnIndex}"]`).forEach(cell => cell.style.display = 'none');
                }
            });
        }

        // Column filters: debounced + data-sku/data-parent (avoid full table resort on every keypress)
        function setupColumnSearch() {
            let debounceTimer = null;

            // Normalize text for searching - handles special characters, whitespace, and encoding issues
            function normalizeSearchText(text) {
                if (!text) return '';
                // Convert to string, trim, lowercase
                let normalized = String(text).trim().toLowerCase();
                // Replace multiple spaces with single space
                normalized = normalized.replace(/\s+/g, ' ');
                // Remove zero-width spaces, non-breaking spaces, etc.
                normalized = normalized.replace(/[\u200B-\u200D\uFEFF\u00A0]/g, '');
                return normalized;
            }

            function collectFilters() {
                const filters = {};
                document.querySelectorAll('.column-search').forEach(function (searchInput) {
                    const col = searchInput.getAttribute('data-search-column');
                    const val = normalizeSearchText(searchInput.value);
                    if (val !== '') {
                        filters[col] = val;
                    }
                });

                const execFilter = (document.querySelector('.mip-exec-header-filter')?.value || '').trim();
                if (execFilter !== '') {
                    filters['exec'] = execFilter;
                }

                const supplierDropdown = (document.getElementById('mip-export-supplier-select')?.value || '').trim();
                if (supplierDropdown !== '') {
                    filters['supplier_dropdown'] = supplierDropdown;
                }

                return filters;
            }

            function applyMipColumnFilters() {
                const filters = collectFilters();
                const skuSearch  = normalizeSearchText(filters['3']  || '');
                const suppSearch = normalizeSearchText(filters['6']  || '');
                const execFilter = (filters['exec'] || '').trim();
                const suppDropdown = normalizeSearchText(filters['supplier_dropdown'] || '');

                rows.forEach(function (row) {
                    const rowStageAttr = (row.getAttribute('data-stage') || '').toLowerCase().trim();
                    const stageSelect  = row.querySelector('.editable-select-stage');
                    const rowStage     = (stageSelect ? stageSelect.value.toLowerCase().trim() : '') || rowStageAttr;

                    if (!isMipPageStage(rowStage)) {
                        row.style.display = 'none';
                        return;
                    }

                    let show = true;

                    // SKU search — same approach as to-order-analysis searchText
                    if (skuSearch) {
                        const sku = normalizeSearchText(row.getAttribute('data-sku') || '');
                        if (!sku.includes(skuSearch)) show = false;
                    }

                    // Supplier search
                    if (show && suppSearch) {
                        const cell = row.querySelector('td[data-column="6"]');
                        if (cell) {
                            const sel = cell.querySelector('select[data-column="supplier"]');
                            const txt = normalizeSearchText(sel ? (sel.selectedOptions?.[0]?.textContent || sel.value) : cell.textContent);
                            if (!txt.includes(suppSearch)) show = false;
                        } else {
                            show = false;
                        }
                    }

                    // Supplier dropdown (exact match)
                    if (show && suppDropdown) {
                        const cell = row.querySelector('td[data-column="6"]');
                        const sel = cell ? cell.querySelector('select[data-column="supplier"]') : null;
                        const txt = normalizeSearchText(sel ? (sel.selectedOptions?.[0]?.textContent || sel.value) : (cell ? cell.textContent : ''));
                        if (txt !== suppDropdown) show = false;
                    }

                    // Executive filter (exact match from dropdown)
                    if (show && execFilter) {
                        const execTd  = row.querySelector('td[data-column="50"]');
                        const rowExec = (execTd ? execTd.getAttribute('data-exec') : '') || '';
                        if (rowExec !== execFilter) show = false;
                    }

                    row.style.display = show ? '' : 'none';
                });

                requestAnimationFrame(function () {
                    if (typeof calculateTotalCBM === 'function') calculateTotalCBM();
                    if (typeof calculateTotalAmount === 'function') calculateTotalAmount();
                    if (typeof calculateTotalOrderItems === 'function') calculateTotalOrderItems();
                    if (typeof updateFollowSupplierCount === 'function') updateFollowSupplierCount();
                });
                mipPagCurrentPage = 1;
                mipSchedulePagination();
            }

            function scheduleApply() {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function () {
                    debounceTimer = null;
                    applyMipColumnFilters();
                }, 300);
            }

            document.querySelectorAll('.column-search').forEach(function (input) {
                input.addEventListener('input', scheduleApply);
            });

            const mipExecHeaderFilter = document.querySelector('.mip-exec-header-filter');
            if (mipExecHeaderFilter) {
                mipExecHeaderFilter.addEventListener('change', scheduleApply);
            }

            const mipSupplierDropdown = document.getElementById('mip-export-supplier-select');
            if (mipSupplierDropdown) {
                mipSupplierDropdown.addEventListener('change', scheduleApply);
            }


        }

        function setupAutoSave() {
            document.querySelectorAll('.auto-save').forEach(function (input) {
                input.addEventListener('change', function () {
                    const row = this.closest('tr');
                    let sku = (this.getAttribute('data-sku') || this.dataset.sku || '').trim();
                    if (!sku && row) {
                        sku = (row.getAttribute('data-sku') || '').trim();
                    }
                    if (!sku && row) {
                        const skuText = row.querySelector('td[data-column="3"] .mip-sku-text');
                        if (skuText) {
                            sku = (skuText.textContent || '').trim();
                        }
                    }
                    const column = this.dataset.column;
                    const value = this.value;

                    if (!column) {
                        return;
                    }
                    if (!sku) {
                        alert('Could not read SKU for this row — please refresh the page and try again.');
                        return;
                    }

                    const mipId = row ? String(row.getAttribute('data-mip-id') || '').trim() : '';

                    // ✅ Save via AJAX (mip_id = exact mfrg_progress row — fixes duplicate-SKU updating the wrong DB row)
                    fetch('/mfrg-progresses/inline-update-by-sku', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            sku,
                            column,
                            value,
                            mip_id: mipId !== '' ? mipId : undefined,
                        })
                    })
                    .then(function (res) {
                        return res.text().then(function (text) {
                            let data = null;
                            try {
                                data = text ? JSON.parse(text) : {};
                            } catch (e) {
                                throw new Error(text ? text.slice(0, 240) : 'Empty response');
                            }
                            if (!res.ok) {
                                const msg = (data && data.message) ? data.message : ('HTTP ' + res.status);
                                throw new Error(msg);
                            }
                            return data;
                        });
                    })
                    .then(res => {
                        if (res.success) {
                            this.style.border = '2px solid green';
                            setTimeout(() => this.style.border = '', 1000);

                            if (column === 'created_at' && typeof calculateTatMip === 'function') {
                                calculateTatMip();
                            }

                            if (column === 'supplier' && row) {
                                const platCell = row.querySelector('td[data-column="26"]');
                                if (platCell) {
                                    platCell.innerHTML = mipPlatformCellHtml(String(value || '').trim());
                                }
                            }

                            // ✅ Recalculate Total on rate change
                            if (column === 'rate') {
                                const qtyCell = row.querySelector('td[data-column="4"]');
                                const totalCell = row.querySelector('td[data-column="12"]');
                                const qtyInput = qtyCell?.querySelector('input[data-column="qty"]');
                                const qty = parseFloat(qtyInput?.value || qtyCell?.innerText?.trim() || '0');
                                const rate = parseFloat(value);
                                if (!isNaN(qty) && !isNaN(rate)) {
                                    totalCell.innerText = (qty * rate).toFixed(2);
                                }
                            }
                            if (column === 'qty') {
                                const qtyNum = parseFloat(value) || 0;
                                const rateInput = row.querySelector('td[data-column="5"] input[data-column="rate"]');
                                const rateNum = parseFloat(rateInput?.value || '0') || 0;
                                const totalCell = row.querySelector('td[data-column="12"]');
                                if (totalCell) {
                                    totalCell.innerText = (qtyNum * rateNum).toFixed(2);
                                }
                                const cbmCell = row.querySelector('td[data-column="14"]');
                                const cbmNum = parseFloat(cbmCell?.textContent?.trim() || '0') || 0;
                                const totalCbmInput = row.querySelector('input[data-column="total_cbm"]');
                                if (totalCbmInput) {
                                    totalCbmInput.value = (qtyNum * cbmNum).toFixed(2);
                                }
                                if (typeof calculateTotalCBM === 'function') {
                                    calculateTotalCBM();
                                }
                                if (typeof updateFollowSupplierCount === 'function') {
                                    updateFollowSupplierCount();
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
                                const skuVal = (row.getAttribute('data-sku') || row.querySelector('td[data-column="3"] .mip-sku-text')?.textContent || '').trim();
                                const supplierSelect = row.querySelector('td[data-column="6"] select[data-column="supplier"]');
                                const supplier = supplierSelect ? supplierSelect.value.trim() : '';
                                const qty = row.querySelector('td[data-column="4"] input[data-column="qty"]')?.value?.trim() || '';
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
                            alert(res.message ? ('Save failed: ' + res.message) : 'Save failed.');
                        }
                    })
                    .catch(function (err) {
                        this.style.border = '2px solid red';
                        const msg = err && err.message ? err.message : String(err);
                        alert('❌ Save error: ' + msg);
                    });
                });
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

        /** Match Forecast Analysis stage colors + transit truck icon. */
        function mipStageTooltip(v) {
            const tips = {
                '': 'Select stage',
                appr_req: 'Appr Req — Approval',
                mip: 'MIP',
                r2s: 'R2S — Ready to ship',
                transit: 'Transit',
                to_order_analysis: 'Order — 2 Order',
                all_good: 'All Good',
            };
            return tips[v] || 'Select stage';
        }

        function mipApplyStageSelectVisual(selectEl, value) {
            if (!selectEl) return;
            const v = String(value || '').trim().toLowerCase();
            const wrap = selectEl.closest('.mip-stage-dot');
            const slot = wrap ? wrap.querySelector('.mip-stage-marker') : null;
            if (wrap) {
                wrap.title = mipStageTooltip(v);
            }
            selectEl.setAttribute('aria-label', mipStageTooltip(v));
            if (!slot) {
                return;
            }
            if (v === 'transit') {
                slot.innerHTML = '<i class="fas fa-truck stage-transit-icon" aria-hidden="true"></i>';
            } else {
                let c = '#94a3b8';
                if (v === 'appr_req') {
                    c = '#facc15';
                } else if (v === 'mip') {
                    c = '#2563eb';
                } else if (v === 'to_order_analysis') {
                    c = '#c2410c';
                } else if (v === 'r2s') {
                    c = '#16a34a';
                } else if (v === 'all_good') {
                    c = '#22c55e';
                }
                slot.innerHTML = '<span class="stage-status-dot" style="background-color:' + c + ';" aria-hidden="true"></span>';
            }
        }

        function updateForecastFieldAsync(data) {
            return new Promise(function (resolve) {
                updateForecastField(
                    data,
                    function () { resolve(true); },
                    function () { resolve(false); }
                );
            });
        }

        function setupStageUpdate() {
            document.querySelectorAll('.editable-select-stage').forEach(function (sel) {
                sel.addEventListener('change', async function () {
                    const row = sel.closest('tr');
                    const sku = (sel.dataset.sku || (row ? row.getAttribute('data-sku') : '') || '').trim();
                    const parent = (sel.dataset.parent || (row ? row.getAttribute('data-parent') : '') || '').trim();
                    const value = String(sel.value || '').trim();
                    if (!sku) return;

                    // MOQ (qty) must be > 0
                    const qtyCell = row ? row.querySelector('td[data-column="4"] input') : null;
                    const orderQty = qtyCell ? parseFloat(qtyCell.value) : 0;
                    if (!orderQty || orderQty === 0) {
                        alert('MOQ cannot be empty or zero.');
                        return;
                    }

                    const saved = await updateForecastFieldAsync({ sku: sku, parent: parent, column: 'Stage', value: value });
                    if (!saved) {
                        alert('Could not save stage.');
                        return;
                    }

                    mipApplyStageSelectVisual(sel, value);
                    if (row) row.setAttribute('data-stage', value);

                    // If moved to MIP, mirror into mfrg_progress (same as to-order-analysis)
                    if (value === 'mip') {
                        const supplierSel = row ? row.querySelector('td[data-column="6"] select[data-column="supplier"]') : null;
                        const payload = {
                            parent: parent,
                            sku: sku,
                            order_qty: qtyCell ? qtyCell.value : '',
                            supplier: supplierSel ? supplierSel.value : '',
                            adv_date: ''
                        };
                        try {
                            await fetch('/mfrg-progresses/insert', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                },
                                body: JSON.stringify(payload)
                            });
                        } catch (e) {}
                    }

                    if (typeof filterByMIPStage === 'function') filterByMIPStage();
                });
            });
        }

        function setupMipBulkStage() {
            const applyBtn = document.getElementById('mip-bulk-stage-apply');
            const bulkSel = document.getElementById('mip-bulk-stage-select');
            if (!applyBtn || !bulkSel) return;

            applyBtn.addEventListener('click', async function () {
                const stageVal = String(bulkSel.value || '').trim();
                if (!stageVal) {
                    alert('Choose a stage to apply.');
                    return;
                }
                const checked = document.querySelectorAll('.row-checkbox:checked');
                if (!checked.length) {
                    alert('Select at least one row (checkbox in the first column).');
                    return;
                }

                let ok = 0;
                let skippedQty = 0;
                let failed = 0;

                applyBtn.disabled = true;
                const prevLabel = applyBtn.textContent;
                applyBtn.textContent = '…';

                for (const cb of checked) {
                    const row = cb.closest('tr');
                    if (!row) continue;
                    const sel = row.querySelector('.editable-select-stage');
                    const sku = sel ? sel.dataset.sku : row.getAttribute('data-sku');
                    const parent = sel ? (sel.dataset.parent || '') : (row.getAttribute('data-parent') || '');
                    if (!sku) {
                        failed++;
                        continue;
                    }
                    const qtyCell = row.querySelector('td[data-column="4"] input');
                    const orderQty = qtyCell ? parseFloat(qtyCell.value) : 0;
                    if (!orderQty || orderQty === 0) {
                        skippedQty++;
                        continue;
                    }

                    const saved = await updateForecastFieldAsync({
                        sku: sku,
                        parent: parent,
                        column: 'Stage',
                        value: stageVal,
                    });

                    if (saved) {
                        ok++;
                        if (sel) {
                            sel.value = stageVal;
                            mipApplyStageSelectVisual(sel, stageVal);
                        }
                        row.setAttribute('data-stage', stageVal);
                    } else {
                        failed++;
                    }
                }

                applyBtn.disabled = false;
                applyBtn.textContent = prevLabel;

                let msg = 'Updated ' + ok + ' row(s).';
                if (skippedQty) msg += ' Skipped ' + skippedQty + ' with empty or zero order qty.';
                if (failed) msg += ' ' + failed + ' failed.';
                alert(msg);

                if (typeof filterByMIPStage === 'function') {
                    filterByMIPStage();
                }
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
                        if (typeof calculateTatMip === 'function') {
                            calculateTatMip();
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

        // Filter to show MIP and R2S stage rows
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
                
                // Show MIP and R2S stage rows
                if (isMipPageStage(rowStage)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Recalculate totals after filtering
            calculateTotalCBM();
            calculateTotalAmount();
            calculateTotalOrderItems();
            updateFollowSupplierCount();
            mipPagCurrentPage = 1;
            mipSchedulePagination();
        }
        // Expose to global scope so the Stage filter dropdown (registered in a separate
        // DOMContentLoaded handler higher up) can re-run the filter on change.
        window.filterByMIPStage = filterByMIPStage;

        function setupAutoUpload() {
            document.querySelectorAll('.auto-upload').forEach(function(input) {
                input.addEventListener('change', function () {
                    const sku = this.dataset.sku;
                    const column = this.dataset.column;
                    const file = this.files[0];
                    const parentDiv = input.closest('.image-upload-field');

                    if (!sku || !column || !file) return;

                    const row = input.closest('tr');
                    const mipId = row ? String(row.getAttribute('data-mip-id') || '').trim() : '';

                    const formData = new FormData();
                    formData.append('sku', sku);
                    formData.append('column', column);
                    formData.append('value', file);
                    if (mipId !== '') {
                        formData.append('mip_id', mipId);
                    }

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


        // Select All checkbox functionality
        (function setupMipSelectAll() {
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            const rowCheckboxes = document.querySelectorAll('.row-checkbox');

            function updateSelectAllState() {
                if (selectAllCheckbox && rowCheckboxes.length > 0) {
                    const allChecked = Array.from(rowCheckboxes).every(cb => cb.checked);
                    const someChecked = Array.from(rowCheckboxes).some(cb => cb.checked);
                    selectAllCheckbox.checked = allChecked;
                    selectAllCheckbox.indeterminate = someChecked && !allChecked;
                }
            }

            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    rowCheckboxes.forEach(checkbox => { checkbox.checked = this.checked; });
                });
            }
            rowCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectAllState);
            });
        })();


    });
</script>
<script>
    // Helper function to sort rows by order date (oldest first) - Global scope
    function mipGetRowPoNumber(row) {
        if (!row) return '';
        const btn = row.querySelector('.mip-po-dot-btn');
        return btn ? String(btn.getAttribute('data-po') || '').trim() : '';
    }



    function mipPoSortKey(po) {
        const s = String(po || '').trim().toUpperCase();
        const m = s.match(/^PO-(\d{2})(\d{2})(\d{2})-(\d+)$/);
        if (m) {
            const dd = parseInt(m[1], 10);
            const mm = parseInt(m[2], 10);
            const yy = parseInt(m[3], 10);
            const serial = parseInt(m[4], 10);
            const year = yy >= 70 ? 1900 + yy : 2000 + yy;
            const ts = new Date(year, mm - 1, dd).getTime();
            if (!isNaN(ts)) {
                return ts * 1000 + serial;
            }
        }
        return s;
    }

    function mipSortPoNumbersOldestFirst(list) {
        return [...list].sort(function (a, b) {
            const ka = mipPoSortKey(a);
            const kb = mipPoSortKey(b);
            if (typeof ka === 'number' && typeof kb === 'number') {
                return ka - kb;
            }
            return String(ka).localeCompare(String(kb));
        });
    }

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




        function mipHideSupplierPlayBadge() {}
        function mipHidePoPlayBadge() {}
        function mipResetOtherPlayModes() {}

        function mipRefreshPlayStats() {
            setTimeout(function () {
                calculateTotalCBM();
                calculateTotalAmount();
                calculateTotalOrderItems();
                updateFollowSupplierCount();
                updateCounts();
                mipPagCurrentPage = 1;
                mipApplyPagination();
            }, 50);
        }

        function mipShowAllMipStageRows() {
            rows.forEach(function (row) {
                const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
                const stageSelect = row.querySelector('.editable-select-stage');
                const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
                const rowStage = rowStageSelect || rowStageAttr;
                row.style.display = isMipPageStage(rowStage) ? '' : 'none';
            });
        }

        function updateCounts() {
            let green = 0, yellow = 0, red = 0;

            rows.forEach(row => {
                // Check if row is MIP/R2S stage
                const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
                const stageSelect = row.querySelector('.editable-select-stage');
                const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
                const rowStage = rowStageSelect || rowStageAttr;
                if (!isMipPageStage(rowStage)) return;

                // Only count visible rows (filtered by play button or other filters)
                if (row.style.display === "none") return;

                // Check created_at (O Date) field for text color
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

            const greenSpan = document.getElementById("greenCount");
            const yellowSpan = document.getElementById("yellowCount");
            const redSpan = document.getElementById("redCount");
            if (greenSpan) greenSpan.textContent = `(${green})`;
            if (yellowSpan) yellowSpan.textContent = `(${yellow})`;
            if (redSpan) redSpan.textContent = `(${red})`;
        }

        function filterDateRows(type) {
            rows.forEach(row => {
                // Check if row is MIP/R2S stage first
                const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
                const stageSelect = row.querySelector('.editable-select-stage');
                const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
                const rowStage = rowStageSelect || rowStageAttr;
                
                if (!isMipPageStage(rowStage)) {
                    row.style.display = "none";
                    return;
                }

                if (!type) {
                    // Show all MIP/R2S rows if no filter selected
                    row.style.display = "";
                    return;
                }

                // Check created_at (O Date) field for text color
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
            calculateTotalOrderItems();
            updateFollowSupplierCount();
            updateCounts(); // Update pending status counts when filtering by date
            mipPagCurrentPage = 1;
            mipSchedulePagination();
        }

        updateCounts();
        updateFollowSupplierCount();

    });
    function calculateTotalAmount() {
        let totalAmount = 0;

        document.querySelectorAll('table.wide-table tbody tr').forEach(row => {
            // Check stage from data attribute first (more reliable)
            const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
            const stageSelect = row.querySelector('.editable-select-stage');
            const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
            const rowStage = rowStageSelect || rowStageAttr;
            
            // Only count MIP/R2S stage rows
            if (!isMipPageStage(rowStage)) {
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

        document.getElementById('total-amount').textContent = '$' + totalAmount.toFixed(0);
    }

    function calculateTotalOrderItems() {
        let totalItems = 0;
        document.querySelectorAll('table.wide-table tbody tr').forEach(row => {
            // Check stage from data attribute first (more reliable)
            const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
            const stageSelect = row.querySelector('.editable-select-stage');
            const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
            const rowStage = rowStageSelect || rowStageAttr;
            
            // Only count MIP/R2S stage rows
            if (!isMipPageStage(rowStage)) {
                return;
            }
            
            // Only count visible rows
            if (row.style.display !== "none") {
                totalItems++;
            }
        });

        document.getElementById('total-order-items').textContent = totalItems;
        if (typeof calculateTatMip === 'function') {
            calculateTatMip();
        }
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
            
            // Only count MIP/R2S stage rows
            if (!isMipPageStage(rowStage)) {
                return;
            }
            
            // Get Order Qty (check all MIP/R2S rows, not just visible ones)
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

    // Filter to show MIP and R2S stage on page load
    function filterByMIPStageOnLoad() {
        const rows = document.querySelectorAll('table.wide-table tbody tr.stage-row');
        rows.forEach(row => {
            const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
            const stageSelect = row.querySelector('.editable-select-stage');
            const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
            const rowStage = rowStageSelect || rowStageAttr;
            
            if (isMipPageStage(rowStage)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
        // Sort visible MIP/R2S rows by order date (oldest first)
        const visibleMipRows = Array.from(rows).filter(r => r.style.display !== 'none');
        if (visibleMipRows.length > 0 && typeof sortRowsByOrderDate === 'function') {
            sortRowsByOrderDate(visibleMipRows);
        }
        calculateTotalCBM();
        calculateTotalAmount();
        calculateTotalOrderItems();
        updateFollowSupplierCount();
        mipPagCurrentPage = 1;
        mipApplyPagination();
    }

    function mipCsvEscapeField(val) {
        const s = val == null ? '' : String(val);
        if (/[",\r\n]/.test(s)) {
            return '"' + s.replace(/"/g, '""') + '"';
        }
        return s;
    }
    function mipDownloadTextFile(filename, text, mimeType) {
        const blob = new Blob([text], { type: mimeType || 'text/plain;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.click();
        URL.revokeObjectURL(url);
    }

    async function exportMipSummaryToCsv() {
        const rows = document.querySelectorAll('table.wide-table tbody tr');
        const exportSupplierSel = document.getElementById('mip-export-supplier-select');
        const exportSupplierFilter = exportSupplierSel ? String(exportSupplierSel.value || '').trim() : '';
        const exportData = [];
        
        // Collect MIP data from visible rows
        rows.forEach(row => {
            if (row.style.display === 'none') return;
            let rowSupplier = '';
            const supplierCell0 = row.querySelector('td[data-column="6"]');
            const supplierSelect0 = supplierCell0 ? supplierCell0.querySelector('select[data-column="supplier"]') : null;
            if (supplierSelect0) {
                rowSupplier = String(supplierSelect0.value || '').trim();
            }
            if (exportSupplierFilter && rowSupplier !== exportSupplierFilter) return;
            const getCellText = (col) => {
                const cell = row.querySelector(`td[data-column="${col}"]`);
                if (!cell) return '';
                if (col === '3') {
                    const st = cell.querySelector('.mip-sku-text');
                    if (st) return (st.textContent || '').trim();
                    const ds = row.getAttribute('data-sku');
                    if (ds) return ds.trim();
                }
                const input = cell.querySelector('input, select');
                if (input) return (input.value || '').trim();
                return (cell.textContent || '').trim();
            };
            const getCellVal = (col) => {
                const cell = row.querySelector(`td[data-column="${col}"]`);
                if (!cell) return '';
                const input = cell.querySelector('input');
                return input ? (input.value || '').trim() : (cell.textContent || '').trim();
            };
            const getImageUrl = (col) => {
                const cell = row.querySelector(`td[data-column="${col}"]`);
                if (!cell) return '';
                const img = cell.querySelector('img');
                let url = img ? (img.dataset.src || img.src || '').trim() : '';
                if (url && !url.startsWith('http') && !url.startsWith('//')) {
                    url = (url.startsWith('/') ? window.location.origin : window.location.origin + '/') + url;
                }
                return url;
            };
            const orderDateStr = getCellVal('10');
            let daysDiff = '';
            let orderDateFormatted = '';
            if (orderDateStr) {
                const orderDate = new Date(orderDateStr);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                orderDate.setHours(0, 0, 0, 0);
                daysDiff = Math.floor((today - orderDate) / (1000 * 60 * 60 * 24));
                const d = String(orderDate.getDate()).padStart(2, '0');
                const m = String(orderDate.getMonth() + 1).padStart(2, '0');
                const y = String(orderDate.getFullYear()).slice(-2);
                orderDateFormatted = d + '-' + m + '-' + y;
            }
            exportData.push({
                'Stage': 'MIP',
                'Supplier': rowSupplier,
                'SKU': getCellText('3'),
                'Image': getImageUrl('1'),
                'QTY': getCellVal('4'),
                'O Date': orderDateFormatted,
                'Days': daysDiff !== '' ? daysDiff : ''
            });
        });
        
        // Collect unique suppliers from visible MIP rows
        const uniqueSuppliers = [...new Set(exportData.map(row => row['Supplier']).filter(s => s))];
        
        // Fetch R2S data for all suppliers (or just the filtered one if selected)
        const suppliersToFetch = exportSupplierFilter ? [exportSupplierFilter] : uniqueSuppliers;
        
        if (suppliersToFetch.length > 0) {
            console.log('Fetching R2S data for suppliers:', suppliersToFetch);
            
            // Fetch R2S data for each supplier
            for (const supplier of suppliersToFetch) {
                if (!supplier) continue;
                
                try {
                    const response = await fetch('/forecast.analysis/get-r2s-data-for-export?supplier=' + encodeURIComponent(supplier));
                    const result = await response.json();
                    
                    if (result.success && result.data && result.data.length > 0) {
                        result.data.forEach(r2sItem => {
                            exportData.push({
                                'Stage': 'R2S',
                                'Supplier': r2sItem.supplier || supplier,
                                'SKU': r2sItem.sku || '',
                                'Image': r2sItem.image || '',
                                'QTY': r2sItem.qty || '',
                                'O Date': '',
                                'Days': ''
                            });
                        });
                        console.log(`Added ${result.data.length} R2S items for supplier: ${supplier}`);
                    }
                } catch (e) {
                    console.warn(`Failed to fetch R2S data for supplier ${supplier}:`, e);
                    // Continue with other suppliers if one fails
                }
            }
        }
        if (exportData.length === 0) {
            alert(exportSupplierFilter ? 'No rows to export for this supplier (check visible filters and supplier selection).' : 'No data to export.');
            return;
        }
        try {
            const supplierEl = document.getElementById('current-supplier');
            let supplierName = 'All suppliers (visible rows)';
            if (exportSupplierFilter) {
                supplierName = exportSupplierFilter;
            } else if (supplierEl && supplierEl.textContent.trim() !== '-') {
                supplierName = supplierEl.textContent.trim() || supplierName;
            }
            
            // Count MIP and R2S items
            const mipCount = exportData.filter(r => r['Stage'] === 'MIP').length;
            const r2sCount = exportData.filter(r => r['Stage'] === 'R2S').length;
            const itemsCount = exportData.length;
            const dateStr = new Date().toISOString().split('T')[0];
            const supplierFileSlug = exportSupplierFilter
                ? '_' + exportSupplierFilter.replace(/[^\w\-.]+/g, '_').replace(/_+/g, '_').replace(/^_|_$/g, '').slice(0, 48)
                : '';

            const csvCols = ['Stage', 'Supplier', 'SKU', 'Image', 'QTY', 'O Date', 'Days'];
            const csvLines = [];
            csvLines.push(csvCols.map(mipCsvEscapeField).join(','));
            exportData.forEach(function(r) {
                csvLines.push([
                    r['Stage'],
                    r['Supplier'],
                    r['SKU'],
                    r['Image'],
                    r['QTY'],
                    r['O Date'],
                    r['Days']
                ].map(mipCsvEscapeField).join(','));
            });
            const bom = '\uFEFF';
            const exportTitle = exportSupplierFilter ? 'MIP_R2S_Summary' : 'MIP_Summary';
            mipDownloadTextFile(
                exportTitle + '_' + dateStr + supplierFileSlug + '.csv',
                bom + csvLines.join('\r\n'),
                'text/csv;charset=utf-8'
            );

            const imageUrls = exportData.map(r => r['Image']).filter(url => url);
            if (imageUrls.length > 0) {
                const urlsJson = JSON.stringify([...new Set(imageUrls)]);
                const summaryText = exportSupplierFilter 
                    ? '<strong>Supplier:</strong> ' + supplierName.replace(/&/g,'&amp;').replace(/</g,'&lt;') + ' | <strong>Total:</strong> ' + itemsCount + ' (MIP: ' + mipCount + ', R2S: ' + r2sCount + ')'
                    : '<strong>Supplier:</strong> ' + supplierName.replace(/&/g,'&amp;').replace(/</g,'&lt;') + ' | <strong>Items:</strong> ' + itemsCount;
                const html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>MIP+R2S Summary ' + dateStr + '</title><style>body{font-family:Arial,sans-serif;padding:20px;}table{border-collapse:collapse;width:100%;margin-top:20px;}th,td{border:1px solid #ddd;padding:8px;text-align:left;}th{background:#3bc0c3;color:#fff;}.stage-mip{background:#e3f2fd;}.stage-r2s{background:#fff3e0;}</style></head><body><h2>MIP + R2S Summary - ' + dateStr + '</h2><p>' + summaryText + '</p><table><thead><tr><th>Stage</th><th>Supplier</th><th>SKU</th><th>Image</th><th>QTY</th><th>O Date</th><th>Days</th></tr></thead><tbody>' + exportData.map(r => '<tr class="stage-' + (r['Stage']||'').toLowerCase() + '"><td>' + (r['Stage']||'').replace(/&/g,'&amp;').replace(/</g,'&lt;') + '</td><td>' + (r['Supplier']||'').replace(/&/g,'&amp;').replace(/</g,'&lt;') + '</td><td>' + (r['SKU']||'').replace(/&/g,'&amp;').replace(/</g,'&lt;') + '</td><td>' + (r['Image'] ? '<a href="' + r['Image'].replace(/"/g,'&quot;') + '" target="_blank">View</a>' : '') + '</td><td>' + (r['QTY']||'') + '</td><td>' + (r['O Date']||'') + '</td><td>' + (r['Days']||'') + '</td></tr>').join('') + '</tbody></table><script>var urls=' + urlsJson + ';var i=0;function openNext(){if(i<urls.length){window.open(urls[i],"_blank");i++;setTimeout(openNext,800);}}setTimeout(openNext,1000);<\/script></body></html>';
                mipDownloadTextFile(
                    exportTitle + '_' + dateStr + supplierFileSlug + '_images.html',
                    html,
                    'text/html;charset=utf-8'
                );
            }
        } catch (e) {
            alert('Export failed: ' + (e.message || e));
        }
    }

    // Initialize filter on page load
    filterByMIPStageOnLoad();
    

</script>

@endsection