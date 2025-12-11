@extends('layouts.vertical', ['title' => 'Ready To Ship', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
@section('css')
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0 font-weight-bold">
                        <i class="mdi mdi-factory mr-2" style="color:#3bc0c3;"></i>
                        Ready To Ship
                    </h4>
                </div>

                <div class="column-controls card mb-4 p-3 shadow-sm" id="columnControls" style="background: #f8f9fa; border-radius: 10px;">
                    <div class="d-flex flex-wrap align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <h5 class="mb-0 font-weight-bold" style="color: #3bc0c3;">
                                <i class="mdi mdi-view-column mr-1"></i>
                                Column Options
                            </h5>
                            <div class="d-flex align-items-center gap-2">
                                <input type="text" class="form-control" id="wholeSearchInput"
                                    placeholder="🔍 Search entire table..."
                                    style="width: 220px; font-size: 0.97rem; height: 36px; border-radius: 6px;">
                            </div>
                            <div class="column-dropdown position-relative">
                                <button class="btn text-white column-dropdown-btn d-flex align-items-center gap-1" id="columnDropdownBtn" style="border-radius: 6px;">
                                    <i class="mdi mdi-format-columns"></i>
                                    Toggle Columns
                                </button>
                                <div class="column-dropdown-content" id="columnDropdownContent"
                                    style="position: absolute; left: 0; top: 110%; min-width: 220px; z-index: 20; background: #fff; box-shadow: 0 2px 12px rgba(60,192,195,0.10); border-radius: 8px; border: 1px solid #e3e3e3; padding: 12px; max-height: 350px; overflow-y: auto;">
                                </div>
                            </div>
                            <button class="btn text-white show-all-columns d-flex align-items-center gap-1" id="showAllColumns" style="border-radius: 6px;">
                                <i class="mdi mdi-eye-check-outline"></i>
                                Show All
                            </button>
                            <div style="min-width: 120px; position: relative;">
                                <select id="zoneFilter" class="form-select border-2 rounded-2 fw-bold">
                                    <option value="">select zone</option>
                                    <option value="GHZ">GHZ</option>
                                    <option value="Ningbo">Ningbo</option>
                                    <option value="Tianjin">Tianjin</option>
                                </select>
                            </div>
                            <!-- Stage Filter -->
                            <div class="col-auto">
                                <label class="form-label fw-semibold mb-1 d-block">🎯 Stage</label>
                                <select id="stage-filter" class="form-select form-select-sm" style="min-width: 160px;">
                                    <option value="">All Stages</option>
                                    <option value="to_order_analysis">2 Order</option>
                                    <option value="mip">MIP</option>
                                    <option value="r2s" selected>R2S</option>
                                </select>
                            </div>
                            <div class="custom-select-wrapper" style="min-width: 220px; position: relative;">
                                <div class="custom-select-box d-flex align-items-center justify-content-between" id="customSelectBox"
                                    style="border: 1.5px solid #e0e6ed; border-radius: 7px; background: #fff; height: 38px; padding: 0 14px; cursor: pointer; min-width: 220px; box-shadow: 0 1px 4px rgba(60,192,195,0.07); transition: border-color 0.2s;">
                                    <span id="customSelectSelectedText" class="flex-grow-1 text-truncate" style="font-size: 1rem; color: #222;">Select supplier</span>
                                    <i class="mdi mdi-menu-down" style="font-size: 1.3rem; color: #3bc0c3;"></i>
                                </div>
                                <div class="custom-select-dropdown shadow" id="customSelectDropdown"
                                    style="display: none; position: absolute; z-index: 30; background: #fff; min-width: 220px; max-width: 320px; border-radius: 10px; border: 1.5px solid #e0e6ed; margin-top: 4px; box-shadow: 0 4px 24px rgba(60,192,195,0.13);">
                                    <div class="p-2 border-bottom" style="background: #f8f9fa; border-radius: 10px 10px 0 0;">
                                        <input type="text" class="form-control border-0 shadow-none" id="customSelectSearchInput"
                                            placeholder="🔍 Search supplier..." style="font-size: 0.97rem; height: 32px; border-radius: 6px; background: #f4f6fa;">
                                    </div>
                                    <div id="customSelectOptions" style="max-height: 160px; overflow-y: auto; padding: 2px 0;">
                                        <div class="custom-select-option px-3 py-2 rounded" data-value="">Select supplier</div>
                                        @foreach ($suppliers as $item)
                                            <div class="custom-select-option px-3 py-2 rounded" data-value="">{{$item}}</div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <div class="py-1 px-3 bg-dark rounded shadow-sm d-inline-flex align-items-center gap-2"  
                                style="color: #ffffff; font-weight: 600; font-size: 15px; border: 1px solid #cde7e2;">
                                <span>Total Amount: <span id="total-amount">0</span></span>
                            </div>
                            <div class="py-1 px-3 bg-info rounded shadow-sm d-inline-flex align-items-center gap-2" 
                                style="color: #ffffff; font-weight: 600; font-size: 15px; border: 1px solid #cde7e2;">
                                <span>Total Qty:  <span id="total-order-qty"> 0</span></span>
                            </div>
                            <div class="py-1 px-3 rounded shadow-sm d-inline-flex align-items-center gap-2" 
                                style="background: #23979b; color: #ffffff; font-weight: 600; font-size: 15px; border: 1px solid #cde7e2;">
                                <span>Total CBM:  <span id="total-cbm"> 0</span></span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-info move-to-transit-btn d-none" style="border-radius: 6px;">
                                <i class="mdi mdi-truck-fast"></i>
                                Move to Transit INV
                            </button>
                            <button id="delete-selected-btn" class="btn btn-primary text-black d-none" style="border-radius: 6px;">
                                <i class="mdi mdi-backup-restore"></i>
                                Revert to MFRG
                            </button>
                            <button id="delete-selected-item" class="btn btn-danger d-none" style="border-radius: 6px;">
                                <i class="mdi mdi-trash-can"></i>
                                Delete
                            </button>
                        </div>
                    </div>
                </div>

                <div class="wide-table-wrapper table-container">
                    <table class="wide-table">
                        <thead>
                            <tr>
                                <th data-column="0">#</th>
                                <th data-column="7" data-column-name="area">ZONE<div class="resizer"></div>
                                </th>
                                <th data-column="1">Image<div class="resizer"></div></th>
                                <th data-column="2">
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
                                <th data-column="4" data-column-name="qty" class="text-center">Or. QTY<div class="resizer"></div></th>
                                <th data-column="20" data-column-name="rec_qty" class="text-center">Rec. QTY<div class="resizer"></div></th>
                                <th data-column="18" data-column-name="qty" class="text-center">Rate<div class="resizer"></div></th>
                                <th data-column="5" data-column-name="supplier">Supplier<div class="resizer"></div>
                                </th>
                                <th data-column="6" data-column-name="cbm">CBM<div class="resizer"></div>
                                </th>
                                <th data-column="19" data-column-name="total_cbm">Total CBM<div class="resizer"></div>
                                </th>
                                <th data-column="8" data-column-name="shipped_cbm_in_container">Balance<div
                                        class="resizer"></div>
                                </th>
                                <th data-column="9" data-column-name="payment">Payment<div class="resizer"></div>
                                </th>
                                <th data-column="10" data-column-name="pay_term">Pay<br/>Term<div class="resizer"></div>
                                </th>
                                <th data-column="11" data-column-name="payment_confirmation">Payment<br/>Confirmation<div
                                        class="resizer"></div>
                                </th>
                                <th data-column="12" data-column-name="model_number">Model<br/>Number<div class="resizer">
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
                                <th data-column="16" data-column-name="container_rfq">Container<br/>RFQ<div class="resizer">
                                    </div>
                                </th>
                                <th data-column="17" data-column-name="quote_result">Quote<br/>Result<div class="resizer">
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($readyToShipList as $item)
                            <tr>
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
                                        <img src="{{ $item->Image }}" class="hover-img" data-src="{{ $item->Image }}" alt="Image" style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px;">
                                    @else
                                        <span class="text-muted">No</span>
                                    @endif
                                </td>
                                
                                <td data-column="2" class="text-center">{{ $item->parent }}</td>
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
                                        <option value="to_order_analysis" {{ $stageValue === 'to_order_analysis' ? 'selected' : '' }}>2 Order</option>
                                        <option value="mip" {{ $stageValue === 'mip' ? 'selected' : '' }}>MIP</option>
                                        <option value="r2s" {{ $stageValue === 'r2s' ? 'selected' : '' }}>R2S</option>
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
                                <td data-column="18">
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
                                    @if(!empty($item->supplier_names))
                                        {{ implode(', ', $item->supplier_names) }}
                                    @else
                                        <span class="text-muted">No supplier</span>
                                    @endif
                                </td>
                                <td data-column="6">{{ isset($item->CBM) && $item->CBM !== null ? number_format((float)$item->CBM, 4) : 'N/A' }}</td>
                                <td data-column="19">{{ is_numeric($item->qty ?? null) && is_numeric($item->CBM ?? null) ? number_format($item->qty * $item->CBM, 2, '.', '') : '' }}</td>
                                
                                <td data-column="8">{{ $item->shipped_cbm_in_container }}</td>
                                <td data-column="9">{{ $item->payment }}</td>
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
                                <td data-column="12">{{ $item->model_number }}</td>
                                <td data-column="13">{{ $item->photo_mail_send }}</td>
                                <td data-column="14">{{ $item->followup_delivery }}</td>
                                <td data-column="15">{{ $item->packing_list }}</td>
                                <td data-column="16">{{ $item->container_rfq }}</td>
                                <td data-column="17">{{ $item->quote_result }}</td>
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
            localStorage.setItem('columnWidths', JSON.stringify(widths));
        }

        function restoreColumnWidths() {
            const widths = JSON.parse(localStorage.getItem('columnWidths') || '{}');
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
                        // Success - color already updated
                    }, function() {
                        alert('Failed to save Stage.');
                        // Revert color
                        this.style.backgroundColor = '#fff';
                    });
                });
            });
        }

        // Stage Filter
        function setupStageFilter() {
            const stageFilter = document.getElementById('stage-filter');
            if (!stageFilter) return;

            // Set default to "R2S"
            stageFilter.value = 'r2s';
            applyStageFilter();

            stageFilter.addEventListener('change', function() {
                applyStageFilter();
            });
        }

        function applyStageFilter() {
            const stageFilter = document.getElementById('stage-filter');
            const selectedStage = stageFilter ? stageFilter.value.toLowerCase().trim() : '';
            const zoneFilter = document.getElementById('zoneFilter');
            const selectedZone = zoneFilter ? zoneFilter.value.trim().toLowerCase() : '';
            const rows = document.querySelectorAll('.wide-table tbody tr');

            rows.forEach(row => {
                const stageSelect = row.querySelector('.editable-select-stage');
                const rowStage = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
                const stageMatch = !selectedStage || rowStage === selectedStage;

                // Also check zone filter
                const selectInRow = row.querySelector('select[data-column="area"]');
                const rowZone = selectInRow ? selectInRow.value.trim().toLowerCase() : '';
                const zoneMatch = !selectedZone || rowZone === selectedZone;

                if (stageMatch && zoneMatch) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Initialize stage handlers
        setupStageUpdate();
        setupStageFilter();

        // Save data on input change
        document.querySelectorAll('.auto-save').forEach(input => {
            input.addEventListener('change', function() {
                const { sku, column } = this.dataset;
                const value = this.value;

                if (!sku || !column) return;

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

        // Button visibility for selected rows
        const deleteBtn = document.getElementById('delete-selected-btn');
        const deleteSelectedItemBtn = document.getElementById('delete-selected-item');
        const moveToTransitBtn = document.querySelector('.move-to-transit-btn');
        const checkboxes = document.querySelectorAll('.row-checkbox');

        checkboxes.forEach(cb => cb.addEventListener('change', updateButtonVisibility));

        function updateButtonVisibility() {
            const anyChecked = document.querySelectorAll('.row-checkbox:checked').length > 0;
            deleteBtn.classList.toggle('d-none', !anyChecked);
            moveToTransitBtn.classList.toggle('d-none', !anyChecked);
            deleteSelectedItemBtn.classList.toggle('d-none', !anyChecked);
        }

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
            localStorage.setItem('hiddenColumns', JSON.stringify(hidden));
        }

        function getHiddenColumns() {
            return JSON.parse(localStorage.getItem('hiddenColumns') || '[]');
        }

        setupSupplierSelect();

        const allRows = document.querySelectorAll('tbody tr');
        calculateSupplierTotals(allRows);

        function setupSupplierSelect() {

            const selectBox = document.getElementById('customSelectBox');
            const dropdown = document.getElementById('customSelectDropdown');
            const selectedText = document.getElementById('customSelectSelectedText');
            const searchInput = document.getElementById('customSelectSearchInput');
            const optionsContainer = document.getElementById('customSelectOptions');

            let allOptions = Array.from(optionsContainer.querySelectorAll('.custom-select-option'));

            selectBox.addEventListener('click', function () {
                dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
                selectBox.classList.toggle('active', dropdown.style.display === 'block');
                searchInput.value = '';
                allOptions.forEach(option => option.style.display = '');
                setTimeout(() => searchInput.focus(), 100);
            });

            optionsContainer.addEventListener('click', function (e) {
                if (!e.target.classList.contains('custom-select-option')) return;

                allOptions.forEach(opt => opt.classList.remove('selected', 'bg-primary', 'text-white'));
                e.target.classList.add('selected', 'bg-primary', 'text-white');
                selectedText.textContent = e.target.textContent;
                dropdown.style.display = 'none';
                selectBox.classList.remove('active');

                const selectedSupplier = e.target.textContent.trim().toLowerCase();
                const allRows = document.querySelectorAll('tbody tr');

                let matchingRows = [];

                allRows.forEach(row => {
                    const supplierCell = row.querySelector('td[data-column="5"]');
                    if (supplierCell && supplierCell.textContent.trim().toLowerCase() === selectedSupplier) {
                        row.style.display = '';
                        matchingRows.push(row);
                    } else {
                        row.style.display = 'none';
                    }
                });

                // After filtering, recalculate totals based on matchingRows
                calculateSupplierTotals(matchingRows);

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

        // Apply stage filter on page load
        setTimeout(() => {
            if (document.getElementById('stage-filter')) {
                applyStageFilter();
            }
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

    document.getElementById('zoneFilter').addEventListener('change', function() {
        applyStageFilter(); // Use the combined filter function
    });


</script>
@endsection