@extends('layouts.vertical', ['title' => 'CP Master', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

    <style>
        .table-responsive {
            position: relative;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            max-height: 600px;
            overflow-y: auto;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            background-color: white;
        }

        .table-responsive thead th {
            position: sticky;
            top: 0;
            background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%) !important;
            color: white;
            z-index: 10;
            padding: 15px 18px;
            font-weight: 600;
            border-bottom: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            font-size: 13px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            transition: all 0.2s ease;
            text-align: center;
        }

        .table-responsive thead th:hover {
            background: linear-gradient(135deg, #1a56b7 0%, #0a3d8f 100%) !important;
        }

        .table-responsive thead input {
            background-color: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 4px;
            color: #333;
            padding: 6px 10px;
            margin-top: 8px;
            font-size: 12px;
            width: 100%;
            transition: all 0.2s;
        }

        .table-responsive thead input:focus {
            background-color: white;
            box-shadow: 0 0 0 2px rgba(26, 86, 183, 0.3);
            outline: none;
        }

        .table-responsive thead input::placeholder {
            color: #8e9ab4;
            font-style: italic;
        }

        .table-responsive thead select.missing-data-filter {
            background-color: rgba(255, 255, 255, 0.9);
            border: 1px solid #ddd;
            border-radius: 4px;
            color: #333;
            transition: all 0.2s;
        }

        .table-responsive thead select.missing-data-filter:focus {
            background-color: white;
            border-color: #1a56b7;
            box-shadow: 0 0 0 2px rgba(26, 86, 183, 0.3);
            outline: none;
        }

        .table-responsive thead select.missing-data-filter option[value="missing"]:checked {
            background-color: #fecaca;
            color: #dc2626;
            font-weight: bold;
        }

        .table-responsive thead select.missing-data-filter[value="missing"] {
            background-color: #fecaca;
            color: #dc2626;
            font-weight: bold;
            border-color: #ef4444;
        }

        .status-badges-full {
            width: 100%;
            flex-wrap: wrap;
        }
        .status-badges-full .status-badge-item {
            flex: 1;
            min-width: 100px;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            color: #000;
            text-align: center;
            white-space: nowrap;
        }
        .status-badges-full .status-badge-item.bg-active { background-color: #bbf7d0; }
        .status-badges-full .status-badge-item.bg-inactive { background-color: #fecaca; }
        .status-badges-full .status-badge-item.bg-dc { background-color: #fecaca; }
        .status-badges-full .status-badge-item.bg-upcoming { background-color: #fef08a; }
        .status-badges-full .status-badge-item.bg-2bdc { background-color: #bfdbfe; }

        .status-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            vertical-align: middle;
        }

        /* Product master — STATUS column (header + cells + filter menu) */
        .table-responsive thead th.pm-status-col {
            background: #284a9e !important;
            color: #fff !important;
            vertical-align: top;
            padding: 10px 8px 8px;
            border-color: rgba(255, 255, 255, 0.12) !important;
            text-transform: none;
        }

        .table-responsive thead th.pm-status-col:hover {
            background: #3257b0 !important;
        }

        .pm-status-header-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-align: center;
            color: #fff;
            margin-bottom: 6px;
        }

        .pm-status-filter-wrap {
            position: relative;
            width: 100%;
        }

        .pm-status-filter-trigger {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 6px 8px;
            font-size: 11px;
            font-weight: 600;
            color: #fff;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 10px;
            cursor: pointer;
            transition: background 0.15s ease, border-color 0.15s ease;
        }

        .pm-status-filter-trigger:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .pm-status-filter-trigger.pm-status-filter-trigger--alert {
            background: rgba(254, 202, 202, 0.95);
            color: #b91c1c;
            border-color: #ef4444;
        }

        .pm-status-filter-menu {
            display: none;
            list-style: none;
            margin: 0;
            padding: 8px;
            background: rgba(30, 34, 42, 0.88);
            -webkit-backdrop-filter: blur(12px);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.28);
            border-radius: 14px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.35);
            z-index: 4000;
        }

        .pm-status-filter-wrap.is-open .pm-status-filter-menu {
            display: block;
        }

        .pm-status-filter-item {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            padding: 10px 12px;
            margin: 0;
            border: none;
            border-radius: 10px;
            background: transparent;
            color: #fff;
            font-size: 13px;
            font-weight: 500;
            text-align: left;
            cursor: pointer;
            transition: background 0.12s ease;
        }

        .pm-status-filter-item:hover,
        .pm-status-filter-item.is-selected {
            background: #2563eb;
        }

        .pm-status-filter-check {
            display: inline-flex;
            width: 18px;
            height: 18px;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
        }

        .pm-status-filter-item-spacer {
            width: 18px;
            flex-shrink: 0;
        }

        .pm-status-marble {
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            flex-shrink: 0;
            box-shadow:
                inset 2px 2px 4px rgba(255, 255, 255, 0.55),
                inset -2px -3px 5px rgba(0, 0, 0, 0.35);
            vertical-align: middle;
        }

        .pm-status-marble--active {
            background: radial-gradient(circle at 32% 28%, #bbf7d0, #22c55e 42%, #14532d);
        }

        .pm-status-marble--inactive,
        .pm-status-marble--dc {
            background: radial-gradient(circle at 32% 28%, #fecaca, #ef4444 42%, #7f1d1d);
        }

        .pm-status-marble--upcoming {
            background: radial-gradient(circle at 32% 28%, #fef9c3, #eab308 45%, #713f12);
        }

        .pm-status-marble--2bdc {
            background: radial-gradient(circle at 32% 28%, #bfdbfe, #2563eb 45%, #1e3a8a);
        }

        .pm-status-marble--muted {
            background: radial-gradient(circle at 32% 28%, #e5e7eb, #9ca3af 45%, #374151);
        }

        .table-responsive tbody td.pm-status-cell {
            color: #4a5568;
            font-weight: 500;
            text-align: center;
            vertical-align: middle;
        }

        .pm-status-cell-inner {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .pm-status-cell-text {
            line-height: 1.2;
        }

        .table-responsive tbody td {
            padding: 12px 18px;
            vertical-align: middle;
            border-bottom: 1px solid #edf2f9;
            font-size: 13px;
            color: #495057;
            transition: all 0.2s ease;
            text-align: center;
        }

        .table-responsive tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .table-responsive tbody tr:hover {
            background-color: #e8f0fe;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .table-responsive tbody tr:hover td {
            color: #000;
        }

        .table-responsive .text-center {
            text-align: center;
        }

        .table {
            margin-bottom: 0;
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .table-responsive .table {
            table-layout: auto;
            width: 100%;
        }
        .table-responsive .table thead th,
        .table-responsive .table tbody td {
            box-sizing: border-box;
        }

        .edit-btn.btn-outline-warning {
            border-radius: 6px;
            padding: 6px 12px;
            transition: all 0.2s;
            background: transparent !important;
            border: 1px solid #b38600 !important;
            color: #b38600 !important;
        }

        .edit-btn.btn-outline-warning:hover {
            background: #b38600 !important;
            color: white !important;
            border-color: #b38600 !important;
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(179, 134, 0, 0.3);
        }

        .form-control {
            border: 2px solid #E2E8F0;
            border-radius: 6px;
            padding: 0.75rem;
        }

        #status {
            display: block !important;
            position: static !important;
            width: 100% !important;
            height: auto !important;
            margin: auto !important;
        }

        .dt-buttons .btn {
            margin-left: 10px;
        }

        .modal-header-gradient {
            background: linear-gradient(135deg, #6B73FF 0%, #000DFF 100%);
            border-bottom: 4px solid #4D55E6;
            padding: 1.5rem;
        }

        .modal-footer-gradient {
            background: linear-gradient(135deg, #F8FAFF 0%, #E6F0FF 100%);
            border-top: 4px solid #E2E8F0;
            padding: 1.5rem;
        }

        .rainbow-loader {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .loading-text {
            margin-top: 10px;
            font-weight: bold;
        }

        .checkbox-column {
            width: 40px;
            text-align: center;
            display: table-cell;
        }

        .select-all-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .row-checkbox {
            width: 16px;
            height: 16px;
            cursor: pointer;
            pointer-events: auto !important;
            z-index: 10;
            position: relative;
        }
        
        .checkbox-cell {
            pointer-events: auto !important;
            z-index: 10;
            position: relative;
        }

        .selection-actions {
            display: none;
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1050;
            background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%);
            padding: 10px 20px;
            border-radius: 50px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .selection-actions .btn {
            margin: 0 5px;
            border-radius: 20px;
            font-weight: 600;
            padding: 5px 15px;
        }

        .selection-count {
            color: white;
            font-weight: bold;
            margin-right: 15px;
            display: inline-block;
        }

        .field-operation {
            padding: 10px;
            border-radius: 6px;
            background-color: #f8f9fa;
            transition: all 0.2s;
        }

        .field-operation:hover {
            background-color: #e9ecef;
        }

        #addFieldBtn {
            border-radius: 20px;
            padding: 6px 15px;
        }

        #applyChangesBtn {
            background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%);
            border: none;
        }

        .remove-field {
            width: 36px;
            height: 36px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
        }

        .custom-toast {
            z-index: 2000;
            max-width: 400px;
            width: auto;
            min-width: 300px;
            font-size: 16px;
        }
        
        /* Toast styling to ensure visibility */
        .toast-body {
            padding: 12px 15px;
            word-wrap: break-word;
            white-space: normal;
        }

        /* Add to your CSS section */
        .sku-tooltip {
            position: absolute;
            z-index: 9999;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 6px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
            display: none;
            max-width: 540px;
            max-height: 540px;
        }

        .sku-tooltip img {
            max-width: 520px;
            max-height: 520px;
            border-radius: 6px;
            display: block;
            object-fit: contain;
        }

        .image-hover {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: zoom-in;
        }

        /* Add to your <style> section */
        .custom-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            z-index: 1000;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
            max-height: 180px;
            overflow-y: auto;
            display: none;
        }

        .custom-dropdown .dropdown-item {
            padding: 8px 14px;
            cursor: pointer;
            font-size: 14px;
            color: #333;
        }

        .custom-dropdown .dropdown-item:hover {
            background: #e8f0fe;
            color: #1a56b7;
        }

                 .time-navigation-group {
            margin-left: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-radius: 50px;
            overflow: hidden;
            padding: 2px;
            background: #f8f9fa;
            display: inline-flex;
            align-items: center;
        }

        .time-navigation-group button {
            padding: 0;
            border-radius: 50% !important;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 3px;
            transition: all 0.2s ease;
            border: 1px solid #dee2e6;
            background: white;
            cursor: pointer;
        }

        .time-navigation-group button:hover {
            background-color: #f1f3f5 !important;
            transform: scale(1.05);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .time-navigation-group button:active {
            transform: scale(0.95);
        }

        .time-navigation-group button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }

        .time-navigation-group button i {
            font-size: 1.1rem;
            transition: transform 0.2s ease;
        }

        /* Play button */
        #play-auto {
            color: #28a745;
        }

        #play-auto:hover {
            background-color: #28a745 !important;
            color: white !important;
        }

        /* Pause button */
        #play-pause {
            color: #ffc107;
            display: none;
        }

        #play-pause:hover {
            background-color: #ffc107 !important;
            color: white !important;
        }

        /* Navigation buttons */
        #play-backward,
        #play-forward {
            color: #007bff;
        }

        #play-backward:hover,
        #play-forward:hover {
            background-color: #007bff !important;
            color: white !important;
        }

        /* Button state colors - must come after hover styles */
        #play-auto.btn-success,
        #play-pause.btn-success {
            background-color: #28a745 !important;
            color: white !important;
        }

        #play-auto.btn-warning,
        #play-pause.btn-warning {
            background-color: #ffc107 !important;
            color: #212529 !important;
        }

        #play-auto.btn-danger,
        #play-pause.btn-danger {
            background-color: #dc3545 !important;
            color: white !important;
        }

        #play-auto.btn-light,
        #play-pause.btn-light {
            background-color: #f8f9fa !important;
            color: #212529 !important;
        }

        /* Ensure hover doesn't override state colors */
        #play-auto.btn-success:hover,
        #play-pause.btn-success:hover {
            background-color: #28a745 !important;
            color: white !important;
        }

        #play-auto.btn-warning:hover,
        #play-pause.btn-warning:hover {
            background-color: #ffc107 !important;
            color: #212529 !important;
        }

        #play-auto.btn-danger:hover,
        #play-pause.btn-danger:hover {
            background-color: #dc3545 !important;
            color: white !important;
        }

        /* Active state styling */
        .time-navigation-group button:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .time-navigation-group button {
                width: 36px;
                height: 36px;
            }

            .time-navigation-group button i {
                font-size: 1rem;
            }
        }

        /* Add to your CSS file or style section */
        .hide-column {
            display: none !important;
        }

        .dataTables_length,
        .dataTables_filter {
            display: none;
        }

        #play-auto.green-btn {
            background-color: green !important;
            color: white;
        }

        #play-auto.red-btn {
            background-color: red !important;
            color: white;
        }

        th small.badge {
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 12px;
        }

        /* Red M indicator for missing data - now a button */
        .missing-data-indicator {
            display: inline-block;
            color: #dc3545;
            font-weight: bold;
            font-size: 14px;
            background-color: #ffebee;
            padding: 4px 10px;
            border-radius: 4px;
            border: 1px solid #dc3545;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .missing-data-indicator:hover {
            background-color: #dc3545;
            color: white;
            transform: scale(1.1);
            box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
        }

        .missing-data-indicator:active {
            transform: scale(0.95);
        }

        .missing-data-cell {
            position: relative;
        }

        /* Verified column – green/red dot dropdown */
        .verified-data-dropdown {
            width: 32px;
            height: 32px;
            min-width: 32px;
            padding: 0;
            border-radius: 50%;
            border: 2px solid rgba(0,0,0,0.15);
            font-size: 18px;
            line-height: 1;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
            outline: none;
            appearance: none;
            -webkit-appearance: none;
            background-repeat: no-repeat;
            background-position: center;
        }

        .verified-data-dropdown.not-verified {
            background-color: #fff;
            border-color: #dc3545;
            color: #dc3545;
        }

        .verified-data-dropdown.not-verified:hover {
            background-color: rgba(220, 53, 69, 0.1);
            box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.3);
        }

        .verified-data-dropdown.verified {
            background-color: #fff;
            border-color: #28a745;
            color: #28a745;
        }

        .verified-data-dropdown.verified:hover {
            background-color: rgba(40, 167, 69, 0.1);
            box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.3);
        }

        .verified-data-dropdown option {
            padding: 8px;
            background: #fff;
            color: #333;
            font-size: 14px;
        }

        .verified-data-dropdown option[value="0"] {
            color: #dc3545;
        }

        .verified-data-dropdown option[value="1"] {
            color: #28a745;
        }

        /* Column header badges (Parent count, SKU count) – 2x size */
        .column-badge {
            font-size: 2em;
            font-weight: 700;
            line-height: 1;
        }

        /* DIL % — same visual weight as Forecast Analysis DIL column */
        .productmaster-dil-pct {
            font-weight: 700;
            font-size: 0.95rem;
            background: none !important;
            border: none !important;
            padding: 0;
            border-radius: 0;
        }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('layouts.shared/page-title', [
        'page_title' => 'CP Master',
        'sub_title' => 'Product master Analysis',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center gap-3">
                                <div class="btn-group time-navigation-group" role="group" aria-label="Parent navigation">
                                    <button id="play-backward" class="btn btn-light rounded-circle" title="Previous parent">
                                        <i class="fas fa-step-backward"></i>
                                    </button>
                                    <button id="play-pause" class="btn btn-light rounded-circle" title="Show all products"
                                        style="display: none;">
                                        <i class="fas fa-pause"></i>
                                    </button>
                                    <button id="play-auto" class="btn btn-light rounded-circle" title="Show all products">
                                        <i class="fas fa-play"></i>
                                    </button>
                                    <button id="play-forward" class="btn btn-light rounded-circle" title="Next parent">
                                        <i class="fas fa-step-forward"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 mb-2">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                        data-bs-target="#addProductModal" title="Add product">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <button type="button" class="btn btn-success" id="downloadExcel">
                                        <i class="fas fa-file-excel me-1"></i> Export
                                    </button>
                                    <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#importExcelModal">
                                        <i class="fas fa-file-upload me-1"></i> Import
                                    </button>
                                    <button type="button" class="btn btn-success" id="viewArchivedBtn">
                                        <i class="fas fa-eye me-1"></i> DC
                                    </button>
                                    <button type="button" class="btn btn-warning" id="importFromApiBtn" hidden>
                                        <i class="fas fa-cloud-download-alt me-1"></i> Import from API Sheet
                                    </button>
                                </div>
                            </div>
                    </div>
                    <div class="col-12 mb-3">
                        <div id="statusBadgesBar" class="status-badges-full d-flex align-items-center justify-content-between gap-2"></div>
                    </div>


                    <div class="modal fade" id="archivedProductsModal" tabindex="-1" aria-labelledby="archivedProductsModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                            <div class="modal-content border-0" style="border-radius: 18px; overflow: hidden;">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title" id="archivedProductsModalLabel">
                                <i class="fas fa-box-archive me-2"></i>Archived Products
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <!-- Search Bar -->
                            <div class="px-4 pt-3 pb-2 bg-light border-bottom">
                                <div class="input-group">
                                    <span class="input-group-text bg-success border-0">
                                        <i class="fas fa-search"></i>
                                    </span>
                                    <input type="text" id="archivedSearch" class="form-control border-0 shadow-none" placeholder="Search by SKU...">
                                </div>
                            </div>
                            <div class="modal-body p-0">
                                <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0" id="archivedProductsTable">
                                    <thead class="table-primary">
                                    <tr>
                                        <th>ID</th>
                                        <th>SKU</th>
                                        {{-- <th>Product Name</th> --}}
                                        <th>Archived By</th>
                                        <th>Archived At</th>
                                        <th>Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <!-- Dynamic rows will load here -->
                                    </tbody>
                                </table>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i>Close
                                </button>
                            </div>
                            </div>
                        </div>
                    </div>


                    <!-- Missing Images Modal -->
                    <div class="modal fade" id="missingImagesModal" tabindex="-1" aria-labelledby="missingImagesModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-fullscreen modal-dialog-scrollable">
                            <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="missingImagesModalLabel">Products Missing Images</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                    <th>Parent</th>
                                    <th>SKU</th>
                                    <th>Status</th>
                                    {{-- <th>LP</th> --}}
                                    <th>CP$</th>
                                    {{-- <th>INV</th>
                                    <th>OV L30</th> --}}
                                    <th>Image</th>
                                    <th>Dimensions (L×W×H)</th>
                                    <th>eBay 2 Ship</th>
                                    <th>Label Qty</th>
                                    <th>Temu Ship</th>
                                    <th>Unit</th>
                                    <th>UPC</th>
                                    <th>MOQ</th>
                                    </tr>
                                </thead>
                                <tbody id="missingImagesTableBody">
                                    <!-- Rows will be filled dynamically -->
                                </tbody>
                                </table>
                            </div>
                            </div>
                        </div>
                    </div>

                    <!-- Add Product Modal -->
                    <div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel"
                        aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content"
                                style="border: none; border-radius: 0; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
                                <!-- Modal Header -->
                                <div class="modal-header"
                                    style="background: linear-gradient(135deg, #6B73FF 0%, #000DFF 100%); border-bottom: 4px solid #4D55E6; padding: 1.5rem; border-radius: 0;">
                                    <h5 class="modal-title" id="addProductModalLabel"
                                        style="color: white; font-weight: 800; font-size: 1.8rem; letter-spacing: 0.5px;">
                                        <i class="fas fa-plus-circle me-2"></i>ADD NEW PRODUCT LISTING
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>

                                <!-- Modal Body -->
                                <div class="modal-body" style="background-color: #F8FAFF; padding: 2rem;">
                                    <div id="form-errors" class="mb-3"></div>
                                    <form id="addProductForm">
                                        <!-- Row 1 -->
                                        <div class="row mb-5">
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="sku" class="form-label fw-bold"
                                                        style="color: #4A5568;">SKU <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" id="sku"
                                                        placeholder="Enter SKU"
                                                        style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;">
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="parent" class="form-label fw-bold"
                                                        style="color: #4A5568;">Parent</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" id="parent"
                                                            placeholder="Enter or select parent"
                                                            style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;"
                                                            list="parentOptions">
                                                        <datalist id="parentOptions"></datalist>
                                                        <button class="btn btn-outline-secondary" type="button"
                                                            id="refreshParents" style="border-radius: 0 6px 6px 0;">
                                                            <i class="fas fa-sync-alt"></i>
                                                        </button>
                                                    </div>
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="labelQty" class="form-label fw-bold"
                                                        style="color: #4A5568;">Label QTY</label>
                                                    <input type="text" class="form-control" id="labelQty"
                                                        placeholder="Enter QTY"
                                                        style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;">
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="status" class="form-label fw-bold"
                                                        style="color: #4A5568;">Status</label>
                                                    <select class="form-control" id="status" name="status"
                                                        style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;">
                                                        <option value="">Select Status</option>
                                                        <option value="active">🟢 Active</option>
                                                        <option value="inactive">🔴 Inactive</option>
                                                        <option value="DC">🔴 DC</option>
                                                        <option value="upcoming">🟡 Upcoming</option>
                                                        <option value="2BDC">🔵 2BDC</option>
                                                    </select>
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="unit" class="form-label fw-bold"
                                                        style="color: #4A5568;">Unit <span class="text-danger">*</span></label>
                                                    <select class="form-control" id="unit" name="unit" required
                                                        style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;">
                                                        <option value="">Select Unit</option>
                                                        <option value="Pieces">Pieces</option>
                                                        <option value="Pair">Pair</option>
                                                    </select>
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Row 2 -->
                                        <div class="row mb-4">
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="cp" class="form-label fw-bold"
                                                        style="color: #4A5568;">CP</label>
                                                    <input type="text" class="form-control" id="cp"
                                                        placeholder="Enter cp"
                                                        style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;">
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="lp" class="form-label fw-bold"
                                                        style="color: #4A5568;">LP</label>
                                                    <input type="text" class="form-control" id="lp"
                                                        placeholder="Enter LP"
                                                        style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: #EDF2F7;"
                                                        readonly>
                                                </div>
                                            </div>
                                            <div class="col-md-3" hidden>
                                                <div class="form-group">
                                                    <label for="lps" class="form-label fw-bold"
                                                        style="color: #4A5568;">LPS</label>
                                                    <input type="text" class="form-control" id="lps"
                                                        placeholder="Enter LPS"
                                                        style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: #EDF2F7;"
                                                        readonly>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="wtAct" class="form-label fw-bold"
                                                        style="color: #4A5568;">WT ACT</label>
                                                    <input type="text" class="form-control" id="wtAct"
                                                        placeholder="Enter WT ACT"
                                                        style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;">
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Row 3 -->
                                        <div class="row mb-4">
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="wtDecl" class="form-label fw-bold"
                                                        style="color: #4A5568;">WT DECL</label>
                                                    <input type="text" class="form-control" id="wtDecl"
                                                        placeholder="Enter WT DECL"
                                                        style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;">
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="ship" class="form-label fw-bold"
                                                        style="color: #4A5568;">SHIP</label>
                                                    <input type="text" class="form-control" id="ship"
                                                        placeholder="Enter SHIP"
                                                        style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;">
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="temu_ship" class="form-label fw-bold"
                                                        style="color: #4A5568;">TEMU SHIP</label>
                                                    <input type="text" class="form-control" id="temu_ship"
                                                        placeholder="Enter TEMU SHIP"
                                                        style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;">
                                                </div>
                                            </div>

                                    

                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="moq" class="form-label fw-bold"
                                                        style="color: #4A5568;">MOQ</label>
                                                    <input type="text" class="form-control" id="moq"
                                                        placeholder="Enter MOQ"
                                                        style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="ebay2_ship" class="form-label fw-bold"
                                                        style="color: #4A5568;">EBAY2 SHIP</label>
                                                    <input type="text" class="form-control" id="ebay2_ship"
                                                        placeholder="Enter EBAY2 SHIP"
                                                        style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="w" class="form-label fw-bold"
                                                        style="color: #4A5568;">Width</label>
                                                    <input type="text" class="form-control" id="w"
                                                        placeholder="Enter Width (inches)"
                                                        style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;">
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="l" class="form-label fw-bold"
                                                        style="color: #4A5568;">Length</label>
                                                    <input type="text" class="form-control" id="l"
                                                        placeholder="Enter Length (inches)"
                                                        style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;">
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="h" class="form-label fw-bold"
                                                        style="color: #4A5568;">Height</label>
                                                    <input type="text" class="form-control" id="h"
                                                        placeholder="Enter Height (inches)"
                                                        style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;">
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="pcbox" class="form-label fw-bold"
                                                        style="color: #4A5568;">Pcs/Box</label>
                                                    <input type="text" class="form-control" id="pcbox"
                                                        placeholder="Enter Pcs/Box"
                                                        style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Row 5 -->
                                        <div class="row mb-4">
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="upc" class="form-label fw-bold"
                                                        style="color: #4A5568;">UPC</label>
                                                    <input type="text" class="form-control" id="upc"
                                                        placeholder="Enter UPC"
                                                        style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;">
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="b" class="form-label fw-bold"
                                                        style="color: #4A5568;">B</label>
                                                    <input type="text" class="form-control" id="b"
                                                        placeholder="Enter b"
                                                        style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="h1" class="form-label fw-bold"
                                                        style="color: #4A5568;">H1</label>
                                                    <input type="text" class="form-control" id="h1"
                                                        placeholder="Enter h1"
                                                        style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="l2Url" class="form-label fw-bold"
                                                        style="color: #4A5568;">Url</label>
                                                    <input type="text" class="form-control" id="l2Url"
                                                        placeholder="Enter Url"
                                                        style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row mb-4">
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="dc" class="form-label fw-bold"
                                                        style="color: #4A5568;">DC</label>
                                                    <input type="text" class="form-control" id="dc"
                                                        placeholder="DC" disabled>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="weight" class="form-label fw-bold"
                                                        style="color: #4A5568;">Weight</label>
                                                    <input type="text" class="form-control" id="weight"
                                                        placeholder="Weight" disabled>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="msrp" class="form-label fw-bold"
                                                        style="color: #4A5568;">MSRP</label>
                                                    <input type="text" class="form-control" id="msrp"
                                                        placeholder="MSRP" disabled>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="map" class="form-label fw-bold"
                                                        style="color: #4A5568;">MAP</label>
                                                    <input type="text" class="form-control" id="map"
                                                        placeholder="MAP" disabled>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row mb-4">
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="productImage" class="form-label fw-bold"
                                                        style="color: #4A5568;">Product Image</label>
                                                    <input type="file" class="form-control" id="productImage"
                                                        name="image" accept="image/*">
                                                    <div id="imagePreview" class="mt-2"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>

                                <!-- Modal Footer -->
                                <div class="modal-footer"
                                    style="background: linear-gradient(135deg, #F8FAFF 0%, #E6F0FF 100%); border-top: 4px solid #E2E8F0; padding: 1.5rem; border-radius: 0;">
                                    <button type="button" class="btn btn-lg" data-bs-dismiss="modal"
                                        style="background: linear-gradient(135deg, #FF6B6B 0%, #FF0000 100%); color: white; border: none; border-radius: 6px; padding: 0.75rem 2rem; font-weight: 700; letter-spacing: 0.5px;">
                                        <i class="fas fa-times me-2"></i>Cancel
                                    </button>
                                    <button type="button" class="btn btn-lg" id="saveProductBtn"
                                        style="background: linear-gradient(135deg, #4ADE80 0%, #22C55E 100%); color: white; border: none; border-radius: 6px; padding: 0.75rem 2rem; font-weight: 700; letter-spacing: 0.5px;">
                                        <i class="fas fa-save me-2"></i>Save Product
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Duplicate SKU (single row — not part of Bulk Actions) -->
                    <div class="modal fade" id="duplicateProductModal" tabindex="-1" aria-labelledby="duplicateProductModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: #fff;">
                                    <h5 class="modal-title" id="duplicateProductModalLabel"><i class="bi bi-files me-2"></i>Duplicate SKU</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="text-muted small mb-3">Copy all CP Master fields from <strong id="duplicateSourceSkuDisplay">—</strong> into a new product row.</p>
                                    <input type="hidden" id="duplicateSourceSku" value="">
                                    <input type="hidden" id="duplicateSourceParent" value="">
                                    <div class="mb-3">
                                        <label for="duplicateNewSku" class="form-label fw-semibold">New SKU <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="duplicateNewSku" autocomplete="off" placeholder="Enter a unique SKU">
                                        <div class="invalid-feedback" id="duplicateNewSkuFeedback">SKU is required.</div>
                                    </div>
                                    <div class="mb-2">
                                        <label for="duplicateNewParent" class="form-label fw-semibold">Parent</label>
                                        <input type="text" class="form-control" id="duplicateNewParent" list="parentOptions" placeholder="Parent name (editable)" autocomplete="off">
                                        <div class="form-text">You can use the same parent or enter a new parent name.</div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-primary" id="confirmDuplicateBtn">
                                        <i class="bi bi-check-lg me-1"></i>Create duplicate
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Progress Modal -->
                    <div id="progressModal" class="modal fade" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header bg-primary text-white">
                                    <h5 class="modal-title">Processing Data</h5>
                                </div>
                                <div class="modal-body">
                                    <div id="progress-container" class="mb-3"></div>
                                    <div id="error-container"></div>
                                    <div id="success-alert" class="alert alert-success" style="display:none">
                                        All sheets updated successfully!
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button id="cancelUploadBtn" class="btn btn-secondary">Cancel</button>
                                    <button id="doneBtn" class="btn btn-primary" style="display:none">Done</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Process Selected Modal -->
                    <div class="modal fade" id="processSelectedModal" tabindex="-1"
                        aria-labelledby="processSelectedModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header"
                                    style="background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%); color: white;">
                                    <h5 class="modal-title" id="processSelectedModalLabel">Process Selected Items</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p>Selected <span id="selectedItemCount" class="fw-bold">0</span> items. Choose fields
                                        to update:</p>

                                    <div id="fieldOperations">
                                        <div class="field-operation mb-3">
                                            <div class="row g-2 align-items-center">
                                                <div class="col-3">
                                                    <select class="form-select field-selector">
                                                        <option value="">Select Field</option>
                                                        <option value="lp">LP</option>
                                                        <option value="cp">CP</option>
                                                        <option value="frght">FRGHT</option>
                                                        <option value="ship">SHIP</option>
                                                        <option value="temu_ship">TEMU SHIP</option>
                                                        <option value="moq">MOQ</option>
                                                        <option value="ebay2_ship">EBAY2 SHIP</option>
                                                        <option value="label_qty">Label QTY</option>
                                                        <option value="wt_act">WT ACT</option>
                                                        <option value="wt_decl">WT DECL</option>
                                                        <option value="l">Length</option>
                                                        <option value="w">Width</option>
                                                        <option value="h">Height</option>
                                                        <option value="status">Status</option>
                                                    </select>
                                                </div>
                                                <div class="col-3">
                                                    <select class="form-select operation-selector">
                                                        <option value="set">=</option>
                                                        <option value="add">+</option>
                                                        <option value="subtract">-</option>
                                                        <option value="multiply">×</option>
                                                        <option value="divide">÷</option>
                                                    </select>
                                                </div>
                                                <div class="col-4">
                                                    <input type="text" class="form-control field-value"
                                                        placeholder="Enter value">
                                                </div>
                                                <div class="col-2">
                                                    <button type="button" class="btn btn-outline-danger remove-field">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <button id="addFieldBtn" class="btn btn-outline-primary mt-2">
                                        <i class="fas fa-plus"></i> Add Field
                                    </button>

                                    <div class="alert alert-info mt-3">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Changes will be applied to all selected items
                                    </div>

                                    <div id="batchUpdateResult" class="mt-3"></div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary"
                                        data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-primary" id="applyChangesBtn">Apply
                                        Changes</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Missing Data Entry Modal -->
                    <div class="modal fade" id="missingDataModal" tabindex="-1" aria-labelledby="missingDataModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white;">
                                    <h5 class="modal-title" id="missingDataModalLabel">
                                        <i class="fas fa-edit me-2"></i>Enter Missing Data
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">SKU:</label>
                                        <p class="form-control-plaintext" id="missingDataSku"></p>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold" id="missingDataFieldLabel">Field:</label>
                                        <p class="form-control-plaintext" id="missingDataField"></p>
                                    </div>
                                    <div class="mb-3">
                                        <label for="missingDataValue" class="form-label fw-bold">Enter Value:</label>
                                        <input type="text" class="form-control" id="missingDataValue" placeholder="Enter value here...">
                                        <input type="file" class="form-control" id="missingDataFile" accept="image/*" style="display: none;">
                                        <small class="form-text text-muted" id="missingDataHint"></small>
                                        <div id="missingDataImagePreview" class="mt-2" style="display: none;">
                                            <img id="missingDataPreviewImg" src="" alt="Preview" style="max-width: 200px; max-height: 200px; border-radius: 8px; border: 1px solid #ddd;">
                                        </div>
                                    </div>
                                    <div id="missingDataError" class="alert alert-danger" style="display: none;"></div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-danger" id="saveMissingDataBtn">
                                        <i class="fas fa-save me-1"></i>Save
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Import Excel Modal (Missing Data Only) -->
                    <div class="modal fade" id="importExcelModal" tabindex="-1" aria-labelledby="importExcelModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header" style="background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%); color: white;">
                                    <h5 class="modal-title" id="importExcelModalLabel">
                                        <i class="fas fa-upload me-2"></i>Import Product Master Data
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Instructions:</strong>
                                        <ol class="mb-0 mt-2">
                                            <li>Export the Excel file using the "Export" button</li>
                                            <li>Fill in the missing data (only missing fields will be updated)</li>
                                            <li>Upload the completed file</li>
                                        </ol>
                                    </div>

                                    <div class="mb-3">
                                        <label for="importFile" class="form-label fw-bold">Select Excel File</label>
                                        <input type="file" class="form-control" id="importFile" accept=".xlsx,.xls,.csv">
                                        <div class="form-text">Supported formats: .xlsx, .xls, .csv</div>
                                        <div id="fileError" class="text-danger mt-2" style="display: none;"></div>
                                    </div>

                                    <div id="importProgress" class="progress mb-3" style="display: none;">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                                    </div>

                                    <div id="importResult" class="alert" style="display: none;"></div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="button" class="btn btn-primary" id="importBtn" disabled>
                                        <i class="fas fa-upload me-2"></i>Import
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bulk Actions Modal (Parent, CP, Unit, MOQ) - same style as Tasks -->
                    <div class="modal fade" id="bulkActionsModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                                    <h5 class="modal-title">
                                        <i class="fas fa-tasks me-2"></i>Bulk Actions
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="mb-3">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong><span id="bulkSelectedCount">0</span> product(s) selected</strong>
                                    </p>
                                    <div class="list-group">
                                        <a href="#" class="list-group-item list-group-item-action" id="bulk-change-parent-btn">
                                            <i class="fas fa-sitemap text-primary me-2"></i>
                                            <strong>Change Parent</strong>
                                            <small class="d-block text-muted">Set the same parent for all selected products</small>
                                        </a>
                                        <a href="#" class="list-group-item list-group-item-action" id="bulk-change-cp-btn">
                                            <i class="fas fa-dollar-sign text-success me-2"></i>
                                            <strong>Change CP (Cost)</strong>
                                            <small class="d-block text-muted">Set cost price for all selected products</small>
                                        </a>
                                        <a href="#" class="list-group-item list-group-item-action" id="bulk-change-unit-btn">
                                            <i class="fas fa-cubes text-info me-2"></i>
                                            <strong>Change Unit</strong>
                                            <small class="d-block text-muted">Set unit (e.g. Pieces, Pair) for all selected products</small>
                                        </a>
                                        <a href="#" class="list-group-item list-group-item-action" id="bulk-change-moq-btn">
                                            <i class="fas fa-sort-numeric-up text-warning me-2"></i>
                                            <strong>Change MOQ</strong>
                                            <small class="d-block text-muted">Set minimum order quantity for all selected products</small>
                                        </a>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bulk Update Form Modal (single field value) -->
                    <div class="modal fade" id="bulkUpdateFormModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header bg-primary text-white">
                                    <h5 class="modal-title" id="bulkUpdateFormModalTitle">Bulk Update</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body" id="bulkUpdateFormModalBody">
                                    <!-- Dynamic content -->
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-primary" id="confirmBulkUpdateFormBtn">
                                        <i class="fas fa-check me-1"></i>Update
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Selection actions bar -->
                    <div class="selection-actions" id="selectionActions">
                        <span class="selection-count">0 items selected</span>
                        <button class="btn btn-sm btn-light" id="cancelSelection">Cancel</button>
                        <button class="btn btn-sm btn-info" id="bulkActionsBtn" title="Bulk edit Parent, CP, Unit, MOQ">
                            <i class="fas fa-tasks me-1"></i>Bulk Actions
                        </button>
                        <button class="btn btn-sm btn-success" id="processSelected" title="Update price, status, etc. for selected products"><i class="fas fa-edit me-1"></i> Update selected</button>
                    </div>

                    <div class="table-responsive">
                        <table id="row-callback-datatable" class="table dt-responsive nowrap w-100">
                            <thead>
                                <tr>
                                    <th class="checkbox-column">
                                        <input type="checkbox" class="select-all-checkbox" id="selectAll">
                                    </th>
                                    <th>Images</th>

                                    <th>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span>Parent</span>
                                            <span id="parentCount" class="column-badge">(0)</span>
                                        </div>
                                        <input type="text" id="parentSearch" class="form-control-sm"
                                            placeholder="Search Parent">
                                    </th>
                                    <th>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span>SKU</span>
                                            <span id="skuCount" class="column-badge">(0)</span>
                                        </div>
                                        <input type="text" id="skuSearch" class="form-control-sm"
                                            placeholder="Search SKU">
                                    </th>
                                    <th>UPC</th>
                                    <th>Status</th>
                                    <th>Inventory</th>
                                    <th>OV L30</th>
                                    <th>DIL</th>
                                    <th>Unit</th>
                                    <th>LP</th>
                                    <th>CP$</th>
                                    <th>FRGHT</th>
                                    <th>SHIP</th>
                                    <th>TEMU SHIP</th>
                                    <th>MOQ</th>
                                    <th>EBAY2 SHIP</th>
                                    {{-- <th>INITIAL QUANTITY</th> --}}
                                    <th>Label QTY</th>
                                    <th>WT ACT</th>
                                    <th>WT DECL</th>
                                    <th>Length</th>
                                    <th>Width</th>
                                    <th>Height</th>
                                    <th>CBM</th>
                                    <th>Url</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="table-body"></tbody>
                        </table>
                    </div>

                    <div id="rainbow-loader" class="rainbow-loader">
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="loading-text">Loading Product Master Data...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="skuImageTooltip" class="sku-tooltip"></div>
@endsection

@section('script')
    <script>
        window.userPermissions = @json($permissions ?? []);
        const productPermissions = window.userPermissions['cp_masters'] || [];
        const emailColumnMap = @json($emailColumnMap ?? []);
        const currentUserEmail = @json(auth()->user()->email ?? '');
    </script>
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize with 75% zoom
            document.body.style.zoom = "75%";

            // Store the loaded data globally
            let tableData = [];
            let productMap = new Map(); // Fast lookup by SKU
            let productUniqueParents = [];
            let isProductNavigationActive = false;
            let currentProductParentIndex = -1;
            let filteredProductData = [];
            let selectionMode = true; // Selection always on (Multi Add removed)

            // Track selected items with both SKU and ID
            let selectedItems = {}; // Format: { sku: { id: 123, checked: true } }
            
            // Store current filter values globally to preserve them across data reloads
            let currentFilterValues = {};

            /** NBSP + collapsed whitespace so SKU/parent search matches DB (e.g. Excel/import double spaces, \u00a0). */
            function normalizeForTextSearch(s) {
                if (s == null || s === '') {
                    return '';
                }
                return String(s)
                    .replace(/\u00a0/g, ' ')
                    .replace(/\s+/g, ' ')
                    .trim()
                    .toLowerCase();
            }

            // Get CSRF token from meta tag
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

            // Show loader immediately
            document.getElementById('rainbow-loader').style.display = 'block';

            // Initialize all components
            initializeTable();

            // Centralized AJAX request function with CSRF protection
            function makeRequest(url, method, data = {}) {
                const headers = {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                };

                // Include CSRF token in request body for POST/PUT/PATCH/DELETE
                if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method.toUpperCase())) {
                    data._token = csrfToken;
                }

                return fetch(url, {
                    method: method,
                    headers: headers,
                    body: method === 'GET' ? null : JSON.stringify(data)
                });
            }

            // Load product data from server
            function loadData(callback) {
                // Preserve current filter values before reloading
                currentFilterValues = {};
                document.querySelectorAll('.missing-data-filter').forEach(filter => {
                    const columnName = filter.getAttribute('data-column');
                    if (columnName && filter.value !== 'all') {
                        const key = columnName === 'INV' ? 'Inventory' : columnName;
                        currentFilterValues[key] = filter.value;
                    }
                });
                
                // Add cache-busting parameter to ensure fresh data
                const cacheParam = '?ts=' + new Date().getTime();
                makeRequest('/product-master-data-view' + cacheParam, 'GET')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(response => {
                        if (response && response.data && Array.isArray(response.data)) {
                            tableData = response.data;
                            
                            // Build fast lookup map
                            productMap.clear();
                            tableData.forEach(product => {
                                if (product.SKU) {
                                    productMap.set(product.SKU, product);
                                }
                            });
                            
                            // If callback is provided, use it for custom rendering (e.g., with filters)
                            // Otherwise check if filters are active and apply them
                            if (typeof callback === 'function') {
                                callback();
                            } else {
                                // Check if any filters are active
                                const hasActiveFilters = Object.keys(currentFilterValues).length > 0;
                                if (hasActiveFilters) {
                                    // Restore filter values and apply filters
                                    renderTable(tableData);
                                    // Restore filter dropdown values after table is rendered
                                    setTimeout(() => {
                                        document.querySelectorAll('.missing-data-filter').forEach(filter => {
                                            const columnName = filter.getAttribute('data-column');
                                            if (columnName && currentFilterValues[columnName]) {
                                                filter.value = currentFilterValues[columnName];
                                                updateFilterStyling(filter);
                                            }
                                        });
                                        // Apply filters with restored values
                                        applyFilters();
                                    }, 100);
                                } else {
                                    renderTable(tableData);
                                }
                            }
                            updateParentOptions();
                            initProductPlaybackControls();
                            // Add this block to update counts
                            const parentSet = new Set();
                            let skuCount = 0;
                            tableData.forEach(item => {
                                if (item.Parent) parentSet.add(item.Parent);
                                // Only count SKUs that do NOT contain 'PARENT'
                                if (item.SKU && !String(item.SKU).toUpperCase().includes('PARENT'))
                                    skuCount++;
                            });
                            document.getElementById('parentCount').textContent = `(${parentSet.size})`;
                            document.getElementById('skuCount').textContent = `(${skuCount})`;
                            setupHeaderColumnSearch();

                        } else {
                            showError('Invalid data format received from server');
                        }
                        document.getElementById('rainbow-loader').style.display = 'none';
                    })
                    .catch(error => {
                        showError('Failed to load product data: ' + error.message);
                        document.getElementById('rainbow-loader').style.display = 'none';
                    });
            }

            /** Match server ordering: parent asc, non-PARENT SKUs before PARENT row, then sku asc */
            function sortTableDataLikeServer() {
                tableData.sort((a, b) => {
                    const pa = String(a.Parent ?? a.parent ?? '');
                    const pb = String(b.Parent ?? b.parent ?? '');
                    const pc = pa.localeCompare(pb, undefined, { sensitivity: 'base' });
                    if (pc !== 0) return pc;
                    const skuA = String(a.SKU ?? a.sku ?? '');
                    const skuB = String(b.SKU ?? b.sku ?? '');
                    const aParent = skuA.toUpperCase().includes('PARENT');
                    const bParent = skuB.toUpperCase().includes('PARENT');
                    if (aParent !== bParent) {
                        return aParent ? 1 : -1;
                    }
                    return skuA.localeCompare(skuB, undefined, { sensitivity: 'base' });
                });
            }

            function upsertProductMasterRow(row) {
                if (!row) return;
                const sku = row.SKU || row.sku;
                if (!sku) return;
                const i = tableData.findIndex(p => (p.SKU || p.sku) === sku);
                if (i >= 0) {
                    Object.assign(tableData[i], row);
                    tableData[i].SKU = sku;
                    tableData[i].sku = sku;
                    productMap.set(sku, tableData[i]);
                } else {
                    tableData.push(row);
                    productMap.set(sku, tableData[tableData.length - 1]);
                }
            }

            /** After create/update store JSON: merge rows, re-sort, refresh counts and table without refetching */
            function patchTableDataAfterStore(serverJson) {
                if (serverJson.parent_row) {
                    upsertProductMasterRow(serverJson.parent_row);
                }
                if (serverJson.data) {
                    upsertProductMasterRow(serverJson.data);
                }
                sortTableDataLikeServer();
                const parentSet = new Set();
                let skuCount = 0;
                tableData.forEach(item => {
                    if (item.Parent) parentSet.add(item.Parent);
                    if (item.SKU && !String(item.SKU).toUpperCase().includes('PARENT')) {
                        skuCount++;
                    }
                });
                document.getElementById('parentCount').textContent = `(${parentSet.size})`;
                document.getElementById('skuCount').textContent = `(${skuCount})`;
                updateParentOptions();
                initProductPlaybackControls();
                applyFilters();
                setTimeout(() => {
                    setupEditButtons();
                    setupDuplicateButtons();
                    setupDeleteButtons();
                }, 100);
            }

            // Modified renderTable function to respect column permissions
            function renderTable(data) {
                
                const tbody = document.getElementById('table-body');
                tbody.innerHTML = '';

                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="18" class="text-center">No products found</td></tr>';
                    return;
                }

                const hasEditPermission = productPermissions.includes('edit');
                const hasDeletePermission = productPermissions.includes('delete');
                // Use global selectionMode variable instead of checking DOM

                // Get columns to hide for current user
                const hiddenColumns = getUserHiddenColumns();

                // All available columns
                const allColumns = [
                    "Image", "Parent", "SKU", "UPC", "Status", "Inventory", "OV L30", "DIL", "Unit", "LP", "CP$",
                    "FRGHT", "SHIP", "TEMU SHIP", "MOQ", "EBAY2 SHIP", "Label QTY", "WT ACT", "WT DECL", "Length", "Width", "Height",
                    "CBM", "Url", "Verified", "Action"
                ];

                // Filter to get visible columns
                const visibleColumns = allColumns.filter(col => !hiddenColumns.includes(col));

                // Update table header to show only visible columns
                updateTableHeader(hiddenColumns);

                // Combine search results and selected items (if in selection mode)
                let displayItems = [...data];
                if (selectionMode && Object.keys(selectedItems).length > 0) {
                    const selectedItemsList = tableData.filter(item => selectedItems[item.SKU]);
                    const combinedItems = [...data];

                    selectedItemsList.forEach(item => {
                        if (!data.some(d => d.SKU === item.SKU)) {
                            combinedItems.push(item);
                        }
                    });

                    displayItems = combinedItems;
                }

                // Sort selected items first
                if (selectionMode) {
                    displayItems.sort((a, b) => {
                        const aSelected = selectedItems[a.SKU];
                        const bSelected = selectedItems[b.SKU];
                        if (aSelected && !bSelected) return -1;
                        if (!aSelected && bSelected) return 1;
                        return 0;
                    });
                }

                // Before rendering rows, calculate totals for each parent
                const parentTotals = {};
                data.forEach(item => {
                    if (item.Parent && !String(item.SKU).toUpperCase().includes('PARENT')) {
                        if (!parentTotals[item.Parent]) {
                            parentTotals[item.Parent] = {
                                inv: 0,
                                ovl30: 0
                            };
                        }
                        parentTotals[item.Parent].inv += Number(item.shopify_inv) || 0;
                        parentTotals[item.Parent].ovl30 += Number(item.shopify_quantity) || 0;
                    }
                });

                // Render rows
                displayItems.forEach(item => {
                    const row = document.createElement('tr');

                    // Parent row
                    if (item.SKU && item.SKU.toUpperCase().includes('PARENT')) {
                        row.style.backgroundColor = 'rgba(13, 110, 253, 0.2)';
                        row.style.fontWeight = '500';
                        const totals = parentTotals[item.Parent] || {
                            inv: 0,
                            ovl30: 0
                        };

                        // Checkbox for parent row when selection mode is active
                        if (selectionMode) {
                            const isChecked = selectedItems[item.SKU] ? 'checked' : '';
                            const checkboxCell = document.createElement('td');
                            checkboxCell.className = 'checkbox-cell';
                            checkboxCell.style.textAlign = 'center';
                            let itemId = item.id;
                            if (!itemId || itemId === '' || itemId === null || itemId === undefined) {
                                const product = productMap.get(item.SKU);
                                if (product && product.id) itemId = product.id;
                            }
                            const numericId = itemId ? parseInt(itemId, 10) : null;
                            const idValue = (numericId && !isNaN(numericId) && numericId > 0) ? numericId.toString() : '';
                            const checkbox = document.createElement('input');
                            checkbox.type = 'checkbox';
                            checkbox.className = 'row-checkbox';
                            checkbox.setAttribute('data-sku', escapeHtml(item.SKU || ''));
                            checkbox.setAttribute('data-id', idValue);
                            checkbox.style.cursor = 'pointer';
                            checkbox.style.pointerEvents = 'auto';
                            checkbox.disabled = false;
                            if (isChecked) checkbox.checked = true;
                            checkbox.addEventListener('click', function(e) { e.stopPropagation(); });
                            checkboxCell.appendChild(checkbox);
                            row.appendChild(checkboxCell);
                        }

                        visibleColumns.forEach(col => {
                            let cell = document.createElement('td');
                            
                            switch (col) {
                                case "Image":
                                    cell.innerHTML = item.image_path 
                                        ? `<span class="image-hover" data-image="${item.image_path}"><img src="${item.image_path}" style="width:40px;height:40px;object-fit:cover;border-radius:4px;"></span>`
                                        : '-';
                                    break;
                                case "Parent":
                                    cell.textContent = escapeHtml(item.Parent) || '-';
                                    break;
                                case "SKU":
                                    cell.textContent = escapeHtml(item.SKU) || '-';
                                    break;
                                case "UPC":
                                    cell.className = 'text-center';
                                    cell.textContent = formatNumber(item.upc, 0);
                                    break;
                                case "Inventory":
                                    cell.innerHTML = `<b>${totals.inv}</b>`;
                                    break;
                                case "OV L30":
                                    cell.innerHTML = `<b>${totals.ovl30}</b>`;
                                    break;
                                case "DIL": {
                                    const ovl = totals.ovl30 || 0;
                                    const inv = totals.inv || 0;
                                    cell.className = 'text-center';
                                    if (inv === 0) {
                                        cell.innerHTML = '<b>-</b>';
                                    } else {
                                        const ratio = ovl / inv;
                                        const col = getDilTextColor(ratio);
                                        const pct = Math.round(ratio * 100);
                                        cell.innerHTML = `<span class="productmaster-dil-pct" style="color:${col};">${pct}%</span>`;
                                    }
                                    break;
                                }
                                case "Status":
                                    cell.className = 'pm-status-cell';
                                    cell.innerHTML = getStatusCellDisplayHtml(item.status) || '-';
                                    break;
                                case "Unit":
                                    cell.textContent = item.unit || '-';
                                    break;
                                case "LP":
                                    cell.className = 'text-center';
                                    cell.textContent = formatNumber(item.lp, 2);
                                    break;
                                case "CP$":
                                    cell.className = 'text-center';
                                    cell.textContent = formatNumber(item.cp, 2);
                                    break;
                                case "FRGHT":
                                    cell.className = 'text-center';
                                    cell.textContent = formatNumber(item.frght, 2);
                                    break;
                                case "SHIP":
                                    cell.textContent = escapeHtml(item.ship) || '-';
                                    break;
                                case "TEMU SHIP":
                                    cell.textContent = escapeHtml(item.temu_ship) || '-';
                                    break;
                                case "MOQ":
                                    cell.textContent = escapeHtml(item.moq) || '-';
                                    break;
                                case "EBAY2 SHIP":
                                    cell.textContent = escapeHtml(item.ebay2_ship) || '-';
                                    break;
                                case "Label QTY":
                                    cell.textContent = escapeHtml(item.label_qty) || '0';
                                    break;
                                case "WT ACT":
                                    cell.className = 'text-center';
                                    cell.textContent = formatNumber(item.wt_act || 0, 2);
                                    break;
                                case "WT DECL":
                                    cell.className = 'text-center';
                                    cell.textContent = formatNumber(item.wt_decl || 0, 2);
                                    break;
                                case "Length":
                                    cell.className = 'text-center';
                                    cell.textContent = formatNumber(item.l || 0, 2);
                                    break;
                                case "Width":
                                    cell.className = 'text-center';
                                    cell.textContent = formatNumber(item.w || 0, 2);
                                    break;
                                case "Height":
                                    cell.className = 'text-center';
                                    cell.textContent = formatNumber(item.h || 0, 2);
                                    break;
                                case "CBM":
                                    cell.className = 'text-center';
                                    cell.textContent = formatNumber(item.cbm, 4);
                                    break;
                                case "Url":
                                    cell.className = 'text-center';
                                    cell.innerHTML = item.l2_url ?
                                        `<a href="${escapeHtml(item.l2_url)}" target="_blank"><i class="fas fa-external-link-alt"></i></a>` :
                                        '-';
                                    break;
                                case "Verified":
                                    cell.className = 'text-center';
                                    cell.textContent = '-';
                                    break;
                                case "Action":
                                    cell.className = 'text-center';
                                    cell.innerHTML = `
                            <div class="d-inline-flex">
                                ${hasEditPermission ? 
                                    `<button class="btn btn-sm btn-outline-warning edit-btn me-1" data-sku="${escapeHtml(item.SKU)}">
                                                            <i class="bi bi-pencil-square"></i>
                                                        </button>` 
                                    : ''
                                }
                                ${hasDeletePermission ? 
                                    `<button class="btn btn-sm btn-outline-danger delete-btn" data-id="${escapeHtml(item.id)}" data-sku="${escapeHtml(item.SKU)}">
                                                            <i class="bi bi-archive"></i>
                                                        </button>` 
                                    : ''
                                }
                                ${(!hasEditPermission && !hasDeletePermission) ? '-' : ''}
                            </div>
                        `;
                                    break;
                                default:
                                    cell.textContent = '-';
                            }
                            row.appendChild(cell);
                        });

                        tbody.appendChild(row);
                        return;
                    }

                    // Add checkbox cell if selection mode is active
                    if (selectionMode) {
                        const isChecked = selectedItems[item.SKU] ? 'checked' : '';
                        const checkboxCell = document.createElement('td');
                        checkboxCell.className = 'checkbox-cell';
                        checkboxCell.style.textAlign = 'center';
                        
                        // Ensure we have an ID - try multiple sources
                        let itemId = item.id;
                        if (!itemId || itemId === '' || itemId === null || itemId === undefined) {
                            // Try to get from productMap
                            const product = productMap.get(item.SKU);
                            if (product && product.id) {
                                itemId = product.id;
                            }
                        }
                        
                        // Ensure itemId is a valid number, convert to string for data attribute
                        const numericId = itemId ? parseInt(itemId, 10) : null;
                        const idValue = (numericId && !isNaN(numericId) && numericId > 0) ? numericId.toString() : '';
                        
                        // Create checkbox element directly instead of using innerHTML for better event handling
                        const checkbox = document.createElement('input');
                        checkbox.type = 'checkbox';
                        checkbox.className = 'row-checkbox';
                        checkbox.setAttribute('data-sku', escapeHtml(item.SKU || ''));
                        checkbox.setAttribute('data-id', idValue);
                        checkbox.style.cursor = 'pointer';
                        checkbox.style.pointerEvents = 'auto';
                        checkbox.disabled = false; // Ensure checkbox is not disabled
                        if (isChecked) {
                            checkbox.checked = true;
                        }
                        
                        // Add click handler directly to ensure it works
                        checkbox.addEventListener('click', function(e) {
                            e.stopPropagation();
                        });
                        
                        checkboxCell.appendChild(checkbox);
                        row.appendChild(checkboxCell);
                    }

                    // Calculate CBM and FRGHT using the formulas
                    const l = parseFloat(item.l);
                    const w = parseFloat(item.w);
                    const h = parseFloat(item.h);
                    let cbm = '';
                    let frght = '';
                    if (!isNaN(l) && !isNaN(w) && !isNaN(h)) {
                        cbm = (((l * 2.54) * (w * 2.54) * (h * 2.54)) / 1000000);
                        frght = cbm * 200;
                        cbm = cbm.toFixed(4);
                        frght = frght.toFixed(2);
                    }

                    // Render only visible columns
                    visibleColumns.forEach(col => {
                        let cell = document.createElement('td');
                        let cellContent = '';
                        let isMissing = false;
                        
                        switch (col) {
                            case "Status":
                                cell.className = 'pm-status-cell';
                                isMissing = isDataMissing(item.status);
                                cellContent = getStatusCellDisplayHtml(item.status);
                                cell.innerHTML = addMissingIndicator(cellContent, isMissing, item.SKU || '', 'status', 'Status');
                                break;
                            case "Image":
                                isMissing = isDataMissing(item.image_path);
                                cellContent = isMissing ? '' : `<span class="image-hover" data-image="${item.image_path}"><img src="${item.image_path}" style="width:40px;height:40px;object-fit:cover;border-radius:4px;"></span>`;
                                cell.innerHTML = addMissingIndicator(cellContent, isMissing, item.SKU || '', 'image_path', 'Image');
                                break;
                            case "Parent":
                                isMissing = isDataMissing(item.Parent);
                                cellContent = isMissing ? '' : escapeHtml(item.Parent);
                                cell.innerHTML = addMissingIndicator(cellContent, isMissing, item.SKU || '', 'Parent', 'Parent');
                                break;
                            case "SKU":
                                isMissing = isDataMissing(item.SKU);
                                if (isMissing) {
                                    cell.innerHTML = createMissingDataButton(item.SKU || '', 'SKU', 'SKU');
                                } else {
                                    cell.innerHTML = `
                                        <span class="sku-hover" 
                                            data-sku="${escapeHtml(item.SKU) || ''}" 
                                            data-image="${item.image_path ? item.image_path : ''}">
                                            ${escapeHtml(item.SKU)}
                                        </span>
                                    `;
                                }
                                break;
                            case "UPC":
                                cell.className = 'text-center';
                                isMissing = isDataMissing(item.upc, true);
                                if (isMissing) {
                                    cell.innerHTML = createMissingDataButton(item.SKU || '', 'upc', 'UPC');
                                } else {
                                    const formatted = formatNumber(item.upc, 0);
                                    if (formatted === '-') {
                                        cell.innerHTML = createMissingDataButton(item.SKU || '', 'upc', 'UPC');
                                    } else {
                                        cell.textContent = formatted;
                                    }
                                }
                                break;
                            case "Inventory":
                                if (item.shopify_inv === 0 || item.shopify_inv === "0") {
                                    cell.textContent = "0";
                                } else if (item.shopify_inv === null || item.shopify_inv === undefined || item.shopify_inv === "") {
                                    isMissing = true;
                                    cell.innerHTML = '<span class="missing-data-indicator" title="Missing Data">M</span>';
                                } else {
                                    cell.textContent = escapeHtml(item.shopify_inv);
                                }
                                break;
                            case "OV L30":
                                if (item.shopify_quantity === 0 || item.shopify_quantity === "0") {
                                    cell.textContent = "0";
                                } else if (item.shopify_quantity === null || item.shopify_quantity === undefined || item.shopify_quantity === "") {
                                    isMissing = true;
                                    cell.innerHTML = '<span class="missing-data-indicator" title="Missing Data">M</span>';
                                } else {
                                    cell.textContent = escapeHtml(item.shopify_quantity);
                                }
                                break;
                            case "DIL": {
                                const invMissing = item.shopify_inv === null || item.shopify_inv === undefined || item.shopify_inv === "";
                                const ovlMissing = item.shopify_quantity === null || item.shopify_quantity === undefined || item.shopify_quantity === "";
                                if (invMissing || ovlMissing) {
                                    cell.innerHTML = '<span class="missing-data-indicator" title="Missing Data">M</span>';
                                } else {
                                    const inv = Number(item.shopify_inv) || 0;
                                    const ovl = Number(item.shopify_quantity) || 0;
                                    cell.className = 'text-center';
                                    if (inv === 0) {
                                        cell.textContent = '-';
                                    } else {
                                        const ratio = ovl / inv;
                                        const col = getDilTextColor(ratio);
                                        const pct = Math.round(ratio * 100);
                                        cell.innerHTML = `<span class="productmaster-dil-pct" style="color:${col};">${pct}%</span>`;
                                    }
                                }
                                break;
                            }
                            case "STATUS":
                                cell.className = 'pm-status-cell';
                                isMissing = isDataMissing(item.status);
                                cellContent = getStatusCellDisplayHtml(item.status);
                                cell.innerHTML = addMissingIndicator(cellContent, isMissing, item.SKU || '', 'status', 'Status');
                                break;
                            case "Unit":
                                isMissing = isDataMissing(item.unit);
                                cellContent = isMissing ? '' : item.unit;
                                cell.innerHTML = addMissingIndicator(cellContent, isMissing, item.SKU || '', 'unit', 'Unit');
                                break;
                            case "LP":
                                cell.className = 'text-center';
                                isMissing = isDataMissing(item.lp, true);
                                if (isMissing) {
                                    cell.innerHTML = '<span class="missing-data-indicator" title="Missing Data">M</span>';
                                } else {
                                    const formatted = formatNumber(item.lp, 2);
                                    if (formatted === '-') {
                                        cell.innerHTML = '<span class="missing-data-indicator" title="Missing Data">M</span>';
                                    } else {
                                        cell.textContent = formatted;
                                    }
                                }
                                break;
                            case "CP$":
                                cell.className = 'text-center';
                                isMissing = isDataMissing(item.cp, true);
                                if (isMissing) {
                                    cell.innerHTML = '<span class="missing-data-indicator" title="Missing Data">M</span>';
                                } else {
                                    const formatted = formatNumber(item.cp, 2);
                                    if (formatted === '-') {
                                        cell.innerHTML = '<span class="missing-data-indicator" title="Missing Data">M</span>';
                                    } else {
                                        cell.textContent = formatted;
                                    }
                                }
                                break;
                            case "FRGHT":
                                cell.className = 'text-center';
                                isMissing = isDataMissing(frght, true);
                                if (isMissing) {
                                    cell.innerHTML = '<span class="missing-data-indicator" title="Missing Data">M</span>';
                                } else {
                                    const formatted = frght || formatNumber(item.frght, 2);
                                    if (formatted === '-' || formatted === '') {
                                        cell.innerHTML = '<span class="missing-data-indicator" title="Missing Data">M</span>';
                                    } else {
                                        cell.textContent = formatted;
                                    }
                                }
                                break;
                            case "SHIP":
                                isMissing = isDataMissing(item.ship);
                                cellContent = isMissing ? '' : escapeHtml(item.ship);
                                cell.innerHTML = addMissingIndicator(cellContent, isMissing, item.SKU || '', 'ship', 'SHIP');
                                break;
                            case "TEMU SHIP":
                                isMissing = isDataMissing(item.temu_ship);
                                cellContent = isMissing ? '' : escapeHtml(item.temu_ship);
                                cell.innerHTML = addMissingIndicator(cellContent, isMissing, item.SKU || '', 'temu_ship', 'TEMU SHIP');
                                break;
                            case "MOQ":
                                isMissing = isDataMissing(item.moq);
                                cellContent = isMissing ? '' : escapeHtml(item.moq);
                                cell.innerHTML = addMissingIndicator(cellContent, isMissing, item.SKU || '', 'moq', 'MOQ');
                                break;
                            case "EBAY2 SHIP":
                                isMissing = isDataMissing(item.ebay2_ship);
                                cellContent = isMissing ? '' : escapeHtml(item.ebay2_ship);
                                cell.innerHTML = addMissingIndicator(cellContent, isMissing, item.SKU || '', 'ebay2_ship', 'EBAY2 SHIP');
                                break;
                            case "Label QTY":
                                isMissing = isDataMissing(item.label_qty, true) || (item.label_qty === 0 || item.label_qty === '0');
                                cellContent = isMissing ? '' : escapeHtml(item.label_qty);
                                cell.innerHTML = addMissingIndicator(cellContent, isMissing, item.SKU || '', 'label_qty', 'Label QTY');
                                break;
                            case "WT ACT":
                                cell.className = 'text-center';
                                isMissing = isDataMissing(item.wt_act, true) || (item.wt_act === 0 || item.wt_act === '0');
                                if (isMissing) {
                                    cell.innerHTML = createMissingDataButton(item.SKU || '', 'wt_act', 'WT ACT');
                                } else {
                                    const formatted = formatNumber(item.wt_act, 2);
                                    if (formatted === '-') {
                                        cell.innerHTML = createMissingDataButton(item.SKU || '', 'wt_act', 'WT ACT');
                                    } else {
                                        cell.textContent = formatted;
                                    }
                                }
                                break;
                            case "WT DECL":
                                cell.className = 'text-center';
                                isMissing = isDataMissing(item.wt_decl, true) || (item.wt_decl === 0 || item.wt_decl === '0');
                                if (isMissing) {
                                    cell.innerHTML = createMissingDataButton(item.SKU || '', 'wt_decl', 'WT DECL');
                                } else {
                                    const formatted = formatNumber(item.wt_decl, 2);
                                    if (formatted === '-') {
                                        cell.innerHTML = createMissingDataButton(item.SKU || '', 'wt_decl', 'WT DECL');
                                    } else {
                                        cell.textContent = formatted;
                                    }
                                }
                                break;
                            case "Length":
                                cell.className = 'text-center';
                                isMissing = isDataMissing(item.l, true) || (item.l === 0 || item.l === '0');
                                if (isMissing) {
                                    cell.innerHTML = createMissingDataButton(item.SKU || '', 'l', 'Length');
                                } else {
                                    const formatted = formatNumber(item.l, 2);
                                    if (formatted === '-') {
                                        cell.innerHTML = createMissingDataButton(item.SKU || '', 'l', 'Length');
                                    } else {
                                        cell.textContent = formatted;
                                    }
                                }
                                break;
                            case "Width":
                                cell.className = 'text-center';
                                isMissing = isDataMissing(item.w, true) || (item.w === 0 || item.w === '0');
                                if (isMissing) {
                                    cell.innerHTML = createMissingDataButton(item.SKU || '', 'w', 'Width');
                                } else {
                                    const formatted = formatNumber(item.w, 2);
                                    if (formatted === '-') {
                                        cell.innerHTML = createMissingDataButton(item.SKU || '', 'w', 'Width');
                                    } else {
                                        cell.textContent = formatted;
                                    }
                                }
                                break;
                            case "Height":
                                cell.className = 'text-center';
                                isMissing = isDataMissing(item.h, true) || (item.h === 0 || item.h === '0');
                                if (isMissing) {
                                    cell.innerHTML = createMissingDataButton(item.SKU || '', 'h', 'Height');
                                } else {
                                    const formatted = formatNumber(item.h, 2);
                                    if (formatted === '-') {
                                        cell.innerHTML = createMissingDataButton(item.SKU || '', 'h', 'Height');
                                    } else {
                                        cell.textContent = formatted;
                                    }
                                }
                                break;
                            case "CBM":
                                cell.className = 'text-center';
                                isMissing = isDataMissing(cbm, true);
                                if (isMissing) {
                                    cell.innerHTML = createMissingDataButton(item.SKU || '', 'cbm', 'CBM');
                                } else {
                                    const formatted = cbm || formatNumber(item.cbm, 4);
                                    if (formatted === '-' || formatted === '') {
                                        cell.innerHTML = createMissingDataButton(item.SKU || '', 'cbm', 'CBM');
                                    } else {
                                        cell.textContent = formatted;
                                    }
                                }
                                break;
                            case "Url":
                                cell.className = 'text-center';
                                isMissing = isDataMissing(item.l2_url);
                                if (isMissing) {
                                    cell.innerHTML = createMissingDataButton(item.SKU || '', 'l2_url', 'Url');
                                } else {
                                    cell.innerHTML = `<a href="${escapeHtml(item.l2_url)}" target="_blank"><i class="fas fa-external-link-alt"></i></a>`;
                                }
                                break;
                            case "Verified":
                                cell.className = 'text-center';
                                const isVerified = item.verified_data === 1 || item.verified_data === true || (item.Values && item.Values.verified_data === 1) || (item.Values && item.Values.verified_data === true);
                                const verifiedClass = isVerified ? 'verified' : 'not-verified';
                                const verifiedValue = isVerified ? '1' : '0';
                                cell.innerHTML = `
                                    <select class="verified-data-dropdown ${verifiedClass}" 
                                        data-sku="${escapeHtml(item.SKU)}" title="${isVerified ? 'Verified' : 'Not verified'}">
                                        <option value="0" ${!isVerified ? 'selected' : ''}>🔴</option>
                                        <option value="1" ${isVerified ? 'selected' : ''}>🟢</option>
                                    </select>
                                `;
                                break;
                            case "Action": {
                                cell.className = 'text-center';
                                const isParentPlaceholder = item.SKU && String(item.SKU).toUpperCase().includes('PARENT');
                                cell.innerHTML = `
                        <div class="d-inline-flex">
                            ${hasEditPermission ? 
                                `<button type="button" class="btn btn-sm btn-outline-warning edit-btn me-1" data-sku="${escapeHtml(item.SKU)}">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>` 
                                : ''
                            }
                            ${hasEditPermission && !isParentPlaceholder ? 
                                `<button type="button" class="btn btn-sm btn-outline-secondary duplicate-btn me-1" data-sku="${escapeHtml(item.SKU)}" title="Duplicate as new SKU">
                                                        <i class="bi bi-files"></i>
                                                    </button>` 
                                : ''
                            }
                            ${hasDeletePermission ? 
                                `<button type="button" class="btn btn-sm btn-outline-danger delete-btn" data-id="${escapeHtml(item.id)}" data-sku="${escapeHtml(item.SKU)}">
                                                        <i class="bi bi-archive"></i>
                                                    </button>` 
                                : ''
                            }
                            ${(!hasEditPermission && !hasDeletePermission) ? '-' : ''}
                        </div>
                    `;
                                break;
                            }
                            default:
                                cell.textContent = '-';
                        }
                        row.appendChild(cell);
                    });

                    tbody.appendChild(row);
                });

                if (hasEditPermission) {
                    setupEditButtons();
                    setupDuplicateButtons();
                    setupDeleteButtons();
                }

                // Setup verified data checkboxes
                setupVerifiedDataCheckboxes();

                // bindRowCheckboxes();
                bindSelectAllCheckbox();

                updateSelectionCount();
                // restoreSelectAllState();
                
                // Convert all missing data indicators to clickable buttons
                convertMissingIndicatorsToButtons();
            }

            // Convert missing data indicators to buttons with proper data attributes
            function convertMissingIndicatorsToButtons() {
                const table = document.getElementById('row-callback-datatable');
                if (!table) return;

                const rows = table.querySelectorAll('tbody tr');
                const headerCells = table.querySelectorAll('thead th');

                rows.forEach((row, rowIndex) => {
                    const cells = row.querySelectorAll('td');
                    const skuCell = Array.from(cells).find(cell => {
                        const span = cell.querySelector('.sku-hover');
                        return span && span.getAttribute('data-sku');
                    });
                    const sku = skuCell ? skuCell.querySelector('.sku-hover')?.getAttribute('data-sku') || '' : '';

                    cells.forEach((cell, cellIndex) => {
                        // Skip checkbox column and action column
                        if (cellIndex === 0 && cell.querySelector('.row-checkbox')) return;
                        if (cell.querySelector('.edit-btn, .duplicate-btn, .delete-btn')) return;

                        const missingIndicator = cell.querySelector('.missing-data-indicator');
                        if (missingIndicator && missingIndicator.tagName === 'SPAN') {
                            // Get column name from header
                            const headerCell = headerCells[cellIndex];
                            let columnName = '';
                            if (headerCell) {
                                const headerText = headerCell.textContent.trim();
                                // Extract column name (remove count and search input)
                                columnName = headerText.split('(')[0].trim();
                                if (!columnName) {
                                    // Try to get from the span inside
                                    const span = headerCell.querySelector('span');
                                    if (span) columnName = span.textContent.trim();
                                }
                            }

                            const fieldName = getFieldNameFromColumn(columnName);
                            const fieldLabel = columnName || fieldName;

                            // Replace span with button
                            const button = document.createElement('button');
                            button.type = 'button';
                            button.className = 'missing-data-indicator';
                            button.title = 'Click to enter missing data';
                            button.setAttribute('data-sku', sku);
                            button.setAttribute('data-field', fieldName);
                            button.setAttribute('data-field-label', fieldLabel);
                            button.textContent = 'M';
                            
                            missingIndicator.replaceWith(button);
                        }
                    });
                });
            }

            // Store reference to clicked button
            let currentMissingDataButton = null;

            // Setup missing data button click handlers
            function setupMissingDataButtons() {
                // Use event delegation for dynamically created buttons
                document.addEventListener('click', function(e) {
                    if (e.target && e.target.classList.contains('missing-data-indicator')) {
                        const button = e.target;
                        const sku = button.getAttribute('data-sku');
                        const field = button.getAttribute('data-field');
                        const fieldLabel = button.getAttribute('data-field-label');

                        if (!sku || !field) {
                            console.error('Missing SKU or field information');
                            return;
                        }

                        // Store button reference
                        currentMissingDataButton = button;

                        // Open modal
                        document.getElementById('missingDataSku').textContent = sku;
                        document.getElementById('missingDataField').textContent = fieldLabel;
                        document.getElementById('missingDataFieldLabel').textContent = fieldLabel;
                        document.getElementById('missingDataValue').value = '';
                        document.getElementById('missingDataFile').value = '';
                        document.getElementById('missingDataError').style.display = 'none';
                        document.getElementById('missingDataImagePreview').style.display = 'none';
                        
                        // Handle image field specially
                        const isImageField = field === 'image_path';
                        const textInput = document.getElementById('missingDataValue');
                        const fileInput = document.getElementById('missingDataFile');
                        
                        if (isImageField) {
                            // Show file input, hide text input
                            textInput.style.display = 'none';
                            fileInput.style.display = 'block';
                            document.getElementById('missingDataHint').textContent = 'Select an image file (max 5MB, JPG, PNG, GIF)';
                            
                            // Add file change listener for preview
                            fileInput.onchange = function(e) {
                                const file = e.target.files[0];
                                const errorDiv = document.getElementById('missingDataError');
                                if (file) {
                                    // Validate file type
                                    if (!file.type.match('image.*')) {
                                        errorDiv.textContent = 'Please select a valid image file';
                                        errorDiv.style.display = 'block';
                                        fileInput.value = '';
                                        document.getElementById('missingDataImagePreview').style.display = 'none';
                                        return;
                                    }
                                    
                                    // Validate file size (5MB)
                                    if (file.size > 5 * 1024 * 1024) {
                                        errorDiv.textContent = 'Image size must be less than 5MB';
                                        errorDiv.style.display = 'block';
                                        fileInput.value = '';
                                        document.getElementById('missingDataImagePreview').style.display = 'none';
                                        return;
                                    }
                                    
                                    // Show preview
                                    const reader = new FileReader();
                                    reader.onload = function(e) {
                                        document.getElementById('missingDataPreviewImg').src = e.target.result;
                                        document.getElementById('missingDataImagePreview').style.display = 'block';
                                        errorDiv.style.display = 'none';
                                    };
                                    reader.readAsDataURL(file);
                                }
                            };
                        } else {
                            // Show text input, hide file input
                            textInput.style.display = 'block';
                            fileInput.style.display = 'none';
                            
                            // Set input type hint
                            const isNumeric = ['lp', 'cp', 'frght', 'wt_act', 'wt_decl', 'l', 'w', 'h', 'cbm', 'upc', 'label_qty', 'moq'].includes(field);
                            if (isNumeric) {
                                textInput.type = 'number';
                                textInput.step = field === 'cbm' ? '0.0001' : (field.includes('wt') || field === 'l' || field === 'w' || field === 'h') ? '0.01' : '0.01';
                                document.getElementById('missingDataHint').textContent = 'Enter a numeric value';
                            } else {
                                textInput.type = 'text';
                                document.getElementById('missingDataHint').textContent = '';
                            }
                        }

                        const modal = new bootstrap.Modal(document.getElementById('missingDataModal'));
                        modal.show();

                        // Focus on input
                        setTimeout(() => {
                            if (isImageField) {
                                fileInput.focus();
                            } else {
                                textInput.focus();
                            }
                        }, 300);
                    }
                });
            }

            // Reset button reference when modal is closed
            const missingDataModal = document.getElementById('missingDataModal');
            if (missingDataModal) {
                missingDataModal.addEventListener('hidden.bs.modal', function() {
                    currentMissingDataButton = null;
                    // Reset form inputs
                    document.getElementById('missingDataValue').value = '';
                    document.getElementById('missingDataFile').value = '';
                    document.getElementById('missingDataImagePreview').style.display = 'none';
                    document.getElementById('missingDataError').style.display = 'none';
                    document.getElementById('missingDataValue').style.display = 'block';
                    document.getElementById('missingDataFile').style.display = 'none';
                });
            }

            // Save missing data
            const saveMissingDataBtn = document.getElementById('saveMissingDataBtn');
            if (saveMissingDataBtn) {
                saveMissingDataBtn.addEventListener('click', async function() {
                    if (!currentMissingDataButton) {
                        showToast('danger', 'Error: Button reference lost. Please try again.');
                    return;
                }

                const sku = document.getElementById('missingDataSku').textContent;
                const field = currentMissingDataButton.getAttribute('data-field');
                const fieldLabel = document.getElementById('missingDataField').textContent;
                const errorDiv = document.getElementById('missingDataError');
                const isImageField = field === 'image_path';
                
                // Validate based on field type
                if (isImageField) {
                    const fileInput = document.getElementById('missingDataFile');
                    const file = fileInput.files[0];
                    
                    if (!file) {
                        errorDiv.textContent = 'Please select an image file';
                        errorDiv.style.display = 'block';
                        return;
                    }
                    
                    // Validate file type
                    if (!file.type.match('image.*')) {
                        errorDiv.textContent = 'Please select a valid image file (JPG, PNG, GIF)';
                        errorDiv.style.display = 'block';
                        return;
                    }
                    
                    // Validate file size (5MB)
                    if (file.size > 5 * 1024 * 1024) {
                        errorDiv.textContent = 'Image size must be less than 5MB';
                        errorDiv.style.display = 'block';
                        return;
                    }
                } else {
                    const value = document.getElementById('missingDataValue').value.trim();
                    
                    if (!value) {
                        errorDiv.textContent = 'Please enter a value';
                        errorDiv.style.display = 'block';
                        return;
                    }
                    
                    // Validate numeric fields
                    const isNumeric = ['lp', 'cp', 'frght', 'wt_act', 'wt_decl', 'l', 'w', 'h', 'cbm', 'upc', 'label_qty', 'moq'].includes(field);
                    if (isNumeric) {
                        const numValue = parseFloat(value);
                        if (isNaN(numValue) || numValue < 0) {
                            errorDiv.textContent = 'Please enter a valid positive number';
                            errorDiv.style.display = 'block';
                            return;
                        }
                    }
                }

                // Disable button and show loading
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';

                try {
                    let response;
                    
                    if (isImageField) {
                        // Use FormData for file upload
                        const formData = new FormData();
                        formData.append('sku', sku);
                        formData.append('field', field);
                        formData.append('image', document.getElementById('missingDataFile').files[0]);
                        formData.append('_token', csrfToken);
                        
                        response = await fetch('/product_master/update-field', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: formData
                        });
                    } else {
                        // Use JSON for regular fields
                        const value = document.getElementById('missingDataValue').value.trim();
                        response = await makeRequest('/product_master/update-field', 'POST', {
                            sku: sku,
                            field: field,
                            value: value
                        });
                    }

                    const data = await response.json();

                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Failed to save data');
                    }

                    // Show success message
                    showToast('success', `${fieldLabel} saved successfully!`);

                    // Close modal
                    bootstrap.Modal.getInstance(document.getElementById('missingDataModal')).hide();

                    // Get the saved value from response
                    const savedValue = data.data?.value || (isImageField ? data.data?.value : document.getElementById('missingDataValue').value.trim());
                    
                    // Update the cell in the table
                    const cell = currentMissingDataButton.closest('td');
                    if (cell) {
                        // Update cell content based on field type
                        if (field === 'image_path') {
                            cell.innerHTML = `<span class="image-hover" data-image="${savedValue}"><img src="${savedValue}" style="width:40px;height:40px;object-fit:cover;border-radius:4px;"></span>`;
                        } else if (field === 'l2_url') {
                            cell.className = 'text-center';
                            cell.innerHTML = `<a href="${escapeHtml(savedValue)}" target="_blank"><i class="fas fa-external-link-alt"></i></a>`;
                        } else {
                            const isNumeric = ['lp', 'cp', 'frght', 'wt_act', 'wt_decl', 'l', 'w', 'h', 'cbm', 'upc', 'label_qty', 'moq'].includes(field);
                            if (isNumeric) {
                                const decimals = field === 'cbm' ? 4 : (['lp', 'cp', 'frght', 'wt_act', 'wt_decl', 'l', 'w', 'h'].includes(field)) ? 2 : 0;
                                cell.textContent = parseFloat(savedValue).toFixed(decimals);
                                if (!cell.className.includes('text-center')) {
                                    cell.className = 'text-center';
                                }
                            } else {
                                cell.textContent = savedValue;
                            }
                        }
                    }

                    // Update the data in tableData
                    const product = productMap.get(sku);
                    if (product) {
                        const isNumeric = ['lp', 'cp', 'frght', 'wt_act', 'wt_decl', 'l', 'w', 'h', 'cbm', 'upc', 'label_qty', 'moq'].includes(field);
                        product[field] = isNumeric ? parseFloat(savedValue) : savedValue;
                        if (field === 'image_path') {
                            product.image_path = savedValue;
                        }
                        // Recalculate derived fields if needed
                        if (field === 'l' || field === 'w' || field === 'h') {
                            // CBM and FRGHT will be recalculated on next render
                        }
                    }

                    // Clear button reference
                    currentMissingDataButton = null;

                    // Update the productMap to keep data in sync
                    if (product) {
                        // Recalculate derived fields if needed
                        if (field === 'l' || field === 'w' || field === 'h') {
                            // CBM and FRGHT will be recalculated on next render if needed
                            const l = parseFloat(product.l) || 0;
                            const w = parseFloat(product.w) || 0;
                            const h = parseFloat(product.h) || 0;
                            if (l > 0 && w > 0 && h > 0) {
                                const cbm = (((l * 2.54) * (w * 2.54) * (h * 2.54)) / 1000000);
                                product.cbm = cbm.toFixed(4);
                                product.frght = (cbm * 200).toFixed(2);
                            }
                        }
                    }
                    
                    // Don't reload data - just update in place

                } catch (error) {
                    errorDiv.textContent = error.message;
                    errorDiv.style.display = 'block';
                } finally {
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-save me-1"></i>Save';
                }
                });
            }

            //play button script
            function initProductPlaybackControls() {
                // Collect unique parents
                productUniqueParents = [...new Set(tableData.map(item => item.Parent))];

                // Button handlers
                $('#play-forward').click(productNextParent);
                $('#play-backward').click(productPreviousParent);
                $('#play-pause').click(productStopNavigation);
                $('#play-auto').click(productStartNavigation);

                updateProductButtonStates();
            }

            // Start navigation (Play button)
            function productStartNavigation() {

                if (productUniqueParents.length === 0) return;

                isProductNavigationActive = true;
                currentProductParentIndex = 0;

                showCurrentProductParent();

                // Button states
                $('#play-auto').hide();
                $('#play-pause').show().removeClass('btn-light');

                updateProductButtonStates();
            }

            // Stop navigation (Pause button)
            function productStopNavigation() {
                isProductNavigationActive = false;
                currentProductParentIndex = -1;

                // Reset buttons
                $('#play-pause').hide();
                $('#play-auto')
                    .show()
                    .removeClass('btn-success btn-warning btn-danger')
                    .addClass('btn-light');

                // Show all
                filteredProductData = [...tableData];
                renderTable(filteredProductData);
            }

            // Next parent
            function productNextParent() {
                if (!isProductNavigationActive) return;
                if (currentProductParentIndex >= productUniqueParents.length - 1) return;

                currentProductParentIndex++;
                showCurrentProductParent();
            }

            // Previous parent
            function productPreviousParent() {
                if (!isProductNavigationActive) return;
                if (currentProductParentIndex <= 0) return;

                currentProductParentIndex--;
                showCurrentProductParent();
            }

            // Show selected parent group
            function showCurrentProductParent() {
                if (!isProductNavigationActive || currentProductParentIndex === -1) return;

                const currentParent = productUniqueParents[currentProductParentIndex];

                filteredProductData = tableData.filter(
                    item => item.Parent === currentParent
                );

                renderTable(filteredProductData);
                updateProductButtonStates();
            }

            // Enable/Disable buttons
            function updateProductButtonStates() {
                $('#play-backward').prop(
                    'disabled',
                    !isProductNavigationActive || currentProductParentIndex <= 0
                );

                $('#play-forward').prop(
                    'disabled',
                    !isProductNavigationActive || currentProductParentIndex >= productUniqueParents.length - 1
                );

                // Tooltips
                $('#play-auto').attr(
                    'title',
                    isProductNavigationActive ? 'Show all products' : 'Start parent navigation'
                );

                $('#play-pause').attr('title', 'Stop navigation and show all');
                $('#play-forward').attr('title', 'Next parent');
                $('#play-backward').attr('title', 'Previous parent');

                // Button colors
                if (isProductNavigationActive) {
                    $('#play-forward, #play-backward')
                        .removeClass('btn-light')
                        .addClass('btn-primary');
                } else {
                    $('#play-forward, #play-backward')
                        .removeClass('btn-primary')
                        .addClass('btn-light');
                }
            }



            // Handle Missing Images / Dimensions / CP  on Button Click
            const missingImagesBtn = document.getElementById('missingImagesBtn');
            if (missingImagesBtn) {
                missingImagesBtn.addEventListener('click', function() {
                    if (!Array.isArray(tableData) || tableData.length === 0) {
                        showError('No data loaded yet.');
                    return;
                }

                // Filter SKUs that are missing image OR missing dimension OR missing CP
                const missingData = tableData.filter(item => {
                    const sku = String(item.SKU || '').trim().toUpperCase();
                    const isNotParent = !sku.startsWith('PARENT');

                    // Missing image
                    const hasNoImage = !item.image_path || item.image_path.trim() === '';

                    // Missing or zero dimensions
                    const l = parseFloat(item.l);
                    const w = parseFloat(item.w);
                    const h = parseFloat(item.h);
                    const missingDimensions = (
                        isNaN(l) || isNaN(w) || isNaN(h) || l <= 0 || w <= 0 || h <= 0
                    );

                    // Missing or invalid CP
                    const cpRaw = (item.cp || '').toString().trim();
                    const cpValue = parseFloat(cpRaw);
                    const missingCP = (
                        cpRaw === '' || cpRaw === '-' || isNaN(cpValue) || cpValue <= 0
                    );

                    const missingEbay2Ship = !item.ebay2ship || item.ebay2ship === '-' || item.ebay2ship === '';
                    const missingLabelQty = !item.label_qty || item.label_qty === '-' || item.label_qty === '' || item.label_qty == 0;
                    const missingTemuShip = !item.temu_ship || item.temu_ship === '-' || item.temu_ship === '';
                    const missingUnit = !item.unit || item.unit === '-' || item.unit === '';
                    const missingUPC = !item.upc || item.upc === '-' || item.upc === '';
                    const missingMOQ = !item.moq || item.moq === '-' || item.moq === '' || item.moq == 0;


                    // Include if any missing condition is true (and not a parent SKU)
                    return isNotParent && (hasNoImage || missingDimensions || missingCP || missingEbay2Ship || missingLabelQty || missingTemuShip || missingUnit || missingUPC || missingMOQ);
                });

                const tbody = document.getElementById('missingImagesTableBody');
                tbody.innerHTML = '';

                if (missingData.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="13" class="text-center text-success">All child products have complete data </td></tr>';
                } else {
                    missingData.forEach(item => {
                        const cpRaw = (item.cp || '').toString().trim();
                        const cpValue = parseFloat(cpRaw);
                        const isMissingCP = (cpRaw === '' || cpRaw === '-' || isNaN(cpValue) || cpValue <= 0);

                        const hasImage = item.image_path && item.image_path.trim() !== '';
                        const hasValidDims = (
                            parseFloat(item.l) > 0 && parseFloat(item.w) > 0 && parseFloat(item.h) > 0
                        );

                        const missingEbay2Ship = !item.ebay2ship || item.ebay2ship === '-' || item.ebay2ship === '';
                        const missingLabelQty = !item.label_qty || item.label_qty === '-' || item.label_qty === '' || item.label_qty == 0;
                        const missingTemuShip = !item.temu_ship || item.temu_ship === '-' || item.temu_ship === '';
                        const missingUnit = !item.unit || item.unit === '-' || item.unit === '';
                        const missingUPC = !item.upc || item.upc === '-' || item.upc === '';
                        const missingMOQ = !item.moq || item.moq === '-' || item.moq === '' || item.moq == 0;

                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${escapeHtml(item.Parent || '-')}</td>
                            <td>${escapeHtml(item.SKU || '-')}</td>
                            <td>${getStatusDot(item.status)}</td>

                            <td class="${isMissingCP ? 'text-danger fw-bold' : ''}">
                                ${isMissingCP ? 'Missing CP' : formatNumber(item.cp, 2)}
                            </td>

                            <td>${hasImage ? '<span class="text-success">✔</span>' : '<span class="text-danger"> Missing Image</span>'}</td>

                            <td>${hasValidDims 
                                ? `${formatNumber(item.l, 2)} × ${formatNumber(item.w, 2)} × ${formatNumber(item.h, 2)}`
                                : '<span class="text-danger"> Missing Dimensions</span>'}
                            </td>

                            <td>${missingEbay2Ship ? '<span class="text-danger">Missing</span>' : escapeHtml(item.ebay2ship)}</td>
                            <td>${missingLabelQty ? '<span class="text-danger">Missing</span>' : escapeHtml(item.label_qty)}</td>
                            <td>${missingTemuShip ? '<span class="text-danger">Missing</span>' : escapeHtml(item.temu_ship)}</td>
                            <td>${missingUnit ? '<span class="text-danger">Missing</span>' : escapeHtml(item.unit)}</td>
                            <td>${missingUPC ? '<span class="text-danger">Missing</span>' : escapeHtml(item.upc)}</td>
                            <td>${missingMOQ ? '<span class="text-danger">Missing</span>' : escapeHtml(item.moq)}</td>
                        `;
                        tbody.appendChild(tr);
                    });
                }

                // Update modal title with count
                document.getElementById('missingImagesModalLabel').textContent = 
                    `Missing Products Data (${missingData.length})`;

                // Show the modal
                const modal = new bootstrap.Modal(document.getElementById('missingImagesModal'));
                modal.show();
                });
            }


            // Updated function to show all columns except those in the hidden list
            function updateTableHeader(hiddenColumns) {
                const thead = document.querySelector('#row-callback-datatable thead tr');

                // Preserve current search input values so re-rendering header doesn't clear them
                const existingParentVal = document.getElementById('parentSearch')?.value || '';
                const existingSkuVal = document.getElementById('skuSearch')?.value || '';

                // Preserve missing data filter values - use global currentFilterValues if available, otherwise get from DOM
                const existingFilterValues = Object.keys(currentFilterValues).length > 0 ? currentFilterValues : {};
                document.querySelectorAll('.missing-data-filter').forEach(filter => {
                    const columnName = filter.getAttribute('data-column');
                    if (columnName) {
                        const key = columnName === 'INV' ? 'Inventory' : columnName;
                        if (!existingFilterValues[key]) {
                            existingFilterValues[key] = filter.value;
                        }
                    }
                });
                if (existingFilterValues['INV'] !== undefined && existingFilterValues['Inventory'] === undefined) {
                    existingFilterValues['Inventory'] = existingFilterValues['INV'];
                    delete existingFilterValues['INV'];
                }
                // Update global filter values
                currentFilterValues = {...existingFilterValues};

                // Preserve focus and selection so user's typing isn't interrupted
                const activeEl = document.activeElement;
                const activeId = (activeEl && ['parentSearch', 'skuSearch'].includes(activeEl.id)) ? activeEl.id : null;
                const activeSelectionStart = activeEl && activeId ? activeEl.selectionStart : null;
                const activeSelectionEnd = activeEl && activeId ? activeEl.selectionEnd : null;

                // Store the checkbox column if it exists
                const checkboxTh = thead.querySelector('.checkbox-column');

                // Clear current header
                thead.innerHTML = '';

                // Re-add checkbox column if it exists
                if (checkboxTh) {
                    thead.appendChild(checkboxTh.cloneNode(true));
                }

                // All available columns
                const allColumns = [
                    "Images", "Parent", "SKU", "UPC","STATUS", "Inventory", "OV L30", "DIL", "Unit", "LP", "CP$",
                    "FRGHT", "SHIP", "TEMU SHIP", "MOQ", "EBAY2 SHIP", "Label QTY", "WT ACT", "WT DECL", "Length", "Width", "Height",
                    "CBM", "Url", "Verified", "Action"
                ];

                // Add only columns that are not in the hidden list
                allColumns.forEach(colName => {
                    if (!hiddenColumns.includes(colName)) {
                        const th = document.createElement('th');

                        if (colName === "Parent" || colName === "SKU") {
                            th.innerHTML = `
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span>${colName}</span>
                        <span id="${colName.toLowerCase()}Count" class="column-badge">(0)</span>
                    </div>
                    <input type="text" id="${colName.toLowerCase()}Search" class="form-control-sm" placeholder="Search ${colName}">
                `;
                        } else if (colName === "Action" || colName === "Verified" || colName === "DIL") {
                            th.textContent = colName;
                        } else if (colName === "STATUS") {
                            const filterId = `filter${colName.replace(/\s+/g, '').replace(/[()]/g, '')}`;
                            const savedFilterValue = existingFilterValues && existingFilterValues[colName] ? existingFilterValues[colName] : 'all';
                            th.className = 'pm-status-col';
                            th.innerHTML = `
                    <div class="pm-status-header-label">STATUS</div>
                    <div class="pm-status-filter-wrap">
                        <button type="button" class="pm-status-filter-trigger" aria-expanded="false" aria-haspopup="listbox">
                            <span class="pm-status-filter-trigger-label">${savedFilterValue === 'all' ? 'All' : (pmStatusFilterLabels()[savedFilterValue] || savedFilterValue)}</span>
                            <span class="pm-status-filter-caret" aria-hidden="true" style="font-size:9px;opacity:0.85;">▼</span>
                        </button>
                        <input type="hidden" id="${filterId}" class="missing-data-filter pm-status-filter-hidden" data-column="${colName}" value="${escapeHtml(savedFilterValue)}">
                        <div class="pm-status-filter-menu" role="listbox">
                            <button type="button" class="pm-status-filter-item" data-value="all" role="option">
                                <span class="pm-status-filter-check" aria-hidden="true">✓</span>
                                <span>All</span>
                            </button>
                            <button type="button" class="pm-status-filter-item" data-value="missing" role="option">
                                <span class="pm-status-filter-item-spacer"></span>
                                <span>Missing</span>
                            </button>
                            <button type="button" class="pm-status-filter-item" data-value="active" role="option">
                                <span class="pm-status-marble pm-status-marble--active"></span>
                                <span>Active</span>
                            </button>
                            <button type="button" class="pm-status-filter-item" data-value="inactive" role="option">
                                <span class="pm-status-marble pm-status-marble--inactive"></span>
                                <span>Inactive</span>
                            </button>
                            <button type="button" class="pm-status-filter-item" data-value="DC" role="option">
                                <span class="pm-status-marble pm-status-marble--dc"></span>
                                <span>DC</span>
                            </button>
                            <button type="button" class="pm-status-filter-item" data-value="upcoming" role="option">
                                <span class="pm-status-marble pm-status-marble--upcoming"></span>
                                <span>Upcoming</span>
                            </button>
                            <button type="button" class="pm-status-filter-item" data-value="2BDC" role="option">
                                <span class="pm-status-marble pm-status-marble--2bdc"></span>
                                <span>2BDC</span>
                            </button>
                        </div>
                    </div>
                `;
                        } else if (colName === "Inventory") {
                            const filterId = `filter${colName.replace(/\s+/g, '').replace(/[()]/g, '')}`;
                            const invSaved = existingFilterValues && existingFilterValues[colName] ? existingFilterValues[colName] : 'all';
                            th.innerHTML = `
                    <div style="font-size: 9px;">${colName}</div>
                    <select id="${filterId}" class="form-control form-control-sm mt-1 missing-data-filter" style="font-size: 9px; padding: 2px 4px;" data-column="${colName}">
                        <option value="all" ${invSaved === 'all' ? 'selected' : ''}>All</option>
                        <option value="0" ${invSaved === '0' ? 'selected' : ''}>0</option>
                    </select>
                `;
                        } else {
                            // Add filter dropdown for missing data
                            const filterId = `filter${colName.replace(/\s+/g, '').replace(/[()]/g, '')}`;
                            const savedFilterValue = existingFilterValues && existingFilterValues[colName] ? existingFilterValues[colName] : 'all';
                            th.innerHTML = `
                    <div style="font-size: 9px;">${colName}</div>
                    <select id="${filterId}" class="form-control form-control-sm mt-1 missing-data-filter" style="font-size: 9px; padding: 2px 4px;" data-column="${colName}">
                        <option value="all" ${savedFilterValue === 'all' ? 'selected' : ''}>All</option>
                        <option value="missing" ${savedFilterValue === 'missing' ? 'selected' : ''}>Missing</option>
                    </select>
                `;
                        }

                        thead.appendChild(th);
                    }
                });

                // Ensure Action column is always visible if user has permissions
                const hasEditPermission = productPermissions.includes('edit');
                const hasDeletePermission = productPermissions.includes('delete');

                if ((hasEditPermission || hasDeletePermission) && !thead.querySelector('th').textContent.includes(
                        "Action")) {
                    let actionExists = false;
                    for (let i = 0; i < thead.children.length; i++) {
                        if (thead.children[i].textContent.trim() === "Action") {
                            actionExists = true;
                            break;
                        }
                    }

                    if (!actionExists) {
                        const actionTh = document.createElement('th');
                        actionTh.textContent = "Action";
                        thead.appendChild(actionTh);
                    }
                }

                // Update parent and SKU counts if they're visible
                const parentCount = document.getElementById('parentCount');
                const skuCount = document.getElementById('skuCount');

                // Restore any preserved search values to inputs that were recreated
                if (document.getElementById('parentSearch')) document.getElementById('parentSearch').value = existingParentVal;
                if (document.getElementById('skuSearch')) document.getElementById('skuSearch').value = existingSkuVal;

                // Restore missing data filter values and apply styling
                document.querySelectorAll('.missing-data-filter').forEach(filter => {
                    const columnName = filter.getAttribute('data-column');
                    if (columnName && existingFilterValues[columnName]) {
                        filter.value = existingFilterValues[columnName];
                    }
                    // Apply styling based on current value
                    updateFilterStyling(filter);
                });

                // Restore focus and cursor position if applicable
                if (activeId) {
                    const restored = document.getElementById(activeId);
                    if (restored) {
                        restored.focus();
                        try {
                            if (typeof activeSelectionStart === 'number' && typeof activeSelectionEnd === 'number') {
                                restored.setSelectionRange(activeSelectionStart, activeSelectionEnd);
                            }
                        } catch (err) {
                            // ignore (some browsers may throw if input type doesn't support selection)
                        }
                    }
                }

                if (parentCount && skuCount) {
                    const parentSet = new Set();
                    let skuCountNum = 0;
                    tableData.forEach(item => {
                        if (item.Parent) parentSet.add(item.Parent);
                        // Only count SKUs that do NOT contain 'PARENT'
                        if (item.SKU && !String(item.SKU).toUpperCase().includes('PARENT'))
                            skuCountNum++;
                    });

                    document.getElementById('parentCount').textContent = `(${parentSet.size})`;
                    document.getElementById('skuCount').textContent = `(${skuCountNum})`;
                }

                updateStatusBadgesBar();

                // Setup missing data filter event listeners
                setupMissingDataFilters();
            }

            function updateStatusBadgesBar() {
                const bar = document.getElementById('statusBadgesBar');
                if (!bar) return;
                const statusCounts = { active: 0, inactive: 0, DC: 0, upcoming: 0, '2BDC': 0 };
                (tableData || []).forEach(item => {
                    const sku = String(item.SKU || item.sku || '').toUpperCase();
                    if (sku.includes('PARENT')) return;
                    let raw = item.status;
                    if ((raw == null || raw === '') && item.Values) {
                        const V = typeof item.Values === 'string' ? (function(){ try { return JSON.parse(item.Values); } catch(e) { return {}; } })() : item.Values;
                        raw = V && V.status;
                    }
                    const s = String(raw || '').trim();
                    const lower = s.toLowerCase();
                    if (lower === 'active') statusCounts.active++;
                    else if (lower === 'inactive') statusCounts.inactive++;
                    else if (s.toUpperCase() === 'DC') statusCounts.DC++;
                    else if (lower === 'upcoming') statusCounts.upcoming++;
                    else if (s.toUpperCase() === '2BDC') statusCounts['2BDC']++;
                });
                bar.innerHTML = `
                    <span class="status-badge-item bg-active">Active ${statusCounts.active}</span>
                    <span class="status-badge-item bg-inactive">Inactive ${statusCounts.inactive}</span>
                    <span class="status-badge-item bg-dc">DC ${statusCounts.DC}</span>
                    <span class="status-badge-item bg-upcoming">Upcoming ${statusCounts.upcoming}</span>
                    <span class="status-badge-item bg-2bdc">2BDC ${statusCounts['2BDC']}</span>
                `;
            }

            function pmStatusFilterLabels() {
                return {
                    all: 'All',
                    missing: 'Missing',
                    active: 'Active',
                    inactive: 'Inactive',
                    DC: 'DC',
                    upcoming: 'Upcoming',
                    '2BDC': '2BDC'
                };
            }

            function getStatusDisplayLabel(raw) {
                const s = String(raw || '').trim();
                if (!s) return '';
                const lower = s.toLowerCase();
                const upper = s.toUpperCase();
                if (lower === 'active') return 'Active';
                if (lower === 'inactive') return 'Inactive';
                if (lower === 'upcoming') return 'Upcoming';
                if (upper === 'DC') return 'DC';
                if (upper === '2BDC') return '2BDC';
                return s;
            }

            function getStatusMarbleModifier(raw) {
                const s = String(raw || '').trim();
                if (!s) return 'muted';
                const lower = s.toLowerCase();
                const upper = s.toUpperCase();
                if (lower === 'active') return 'active';
                if (lower === 'inactive') return 'inactive';
                if (upper === 'DC') return 'dc';
                if (lower === 'upcoming') return 'upcoming';
                if (upper === '2BDC') return '2bdc';
                return 'muted';
            }

            function getStatusCellDisplayHtml(status) {
                const raw = String(status || '').trim();
                if (!raw) return '';
                const mod = getStatusMarbleModifier(raw);
                const label = escapeHtml(getStatusDisplayLabel(raw));
                return `<span class="pm-status-cell-inner"><span class="pm-status-marble pm-status-marble--${mod}" title="${escapeHtml(raw)}"></span><span class="pm-status-cell-text">${label}</span></span>`;
            }

            function positionPmStatusFilterMenu(wrap) {
                const menu = wrap.querySelector('.pm-status-filter-menu');
                const trigger = wrap.querySelector('.pm-status-filter-trigger');
                if (!menu || !trigger) return;
                const r = trigger.getBoundingClientRect();
                const w = Math.max(r.width, 200);
                menu.style.position = 'fixed';
                menu.style.top = (r.bottom + 4) + 'px';
                menu.style.left = Math.max(8, Math.min(r.left, window.innerWidth - w - 8)) + 'px';
                menu.style.minWidth = w + 'px';
                menu.style.zIndex = '4000';
            }

            function refreshPmStatusFilterUI() {
                const wrap = document.querySelector('.pm-status-filter-wrap');
                if (!wrap) return;
                const hidden = wrap.querySelector('.pm-status-filter-hidden');
                const trigger = wrap.querySelector('.pm-status-filter-trigger');
                const labelEl = trigger && trigger.querySelector('.pm-status-filter-trigger-label');
                if (!hidden || !trigger || !labelEl) return;
                const v = hidden.value || 'all';
                const map = pmStatusFilterLabels();
                labelEl.textContent = Object.prototype.hasOwnProperty.call(map, v) ? map[v] : v;
                trigger.classList.toggle('pm-status-filter-trigger--alert', v === 'missing');
                wrap.querySelectorAll('.pm-status-filter-item').forEach(btn => {
                    btn.classList.toggle('is-selected', btn.getAttribute('data-value') === v);
                });
            }

            // Update filter styling based on selected value
            function updateFilterStyling(filter) {
                const col = filter.getAttribute('data-column');
                if (filter.classList && filter.classList.contains('pm-status-filter-hidden')) {
                    refreshPmStatusFilterUI();
                    return;
                }
                if (filter.value === 'missing' || (filter.value === '0' && col === 'Inventory')) {
                    filter.style.backgroundColor = '#fecaca';
                    filter.style.color = '#dc2626';
                    filter.style.fontWeight = 'bold';
                    filter.style.borderColor = '#ef4444';
                } else {
                    filter.style.backgroundColor = 'rgba(255, 255, 255, 0.9)';
                    filter.style.color = '#333';
                    filter.style.fontWeight = 'normal';
                    filter.style.borderColor = '#ddd';
                }
            }

            // Setup missing data filter event listeners
            function setupMissingDataFilters() {
                // Apply styling to all existing filters
                document.querySelectorAll('.missing-data-filter').forEach(filter => {
                    // Apply initial styling
                    updateFilterStyling(filter);
                });
            }

            // Modified row rendering to respect column permissions
            function renderRow(item, allowedColumns, isParent = false) {
                const row = document.createElement('tr');

                if (isParent) {
                    row.style.backgroundColor = 'rgba(13, 110, 253, 0.2)';
                    row.style.fontWeight = '500';
                }

                // If in selection mode, add checkbox first (including parent rows)
                if (selectionMode) {
                    const isChecked = selectedItems[item.SKU] ? 'checked' : '';
                    const checkboxCell = document.createElement('td');
                    checkboxCell.className = 'checkbox-cell';
                    checkboxCell.style.textAlign = 'center';
                    
                    // Ensure we have an ID - try multiple sources
                    let itemId = item.id;
                    if (!itemId || itemId === '' || itemId === null || itemId === undefined) {
                        // Try to get from productMap
                        const product = productMap.get(item.SKU);
                        if (product && product.id) {
                            itemId = product.id;
                        }
                    }
                    
                    checkboxCell.innerHTML = `
            <input type="checkbox" class="row-checkbox" 
                data-sku="${escapeHtml(item.SKU || '')}" 
                data-id="${escapeHtml(itemId || '')}" ${isChecked}>
        `;
                    row.appendChild(checkboxCell);
                }

                // Add only allowed columns
                allowedColumns.forEach(colName => {
                    const cell = document.createElement('td');

                    // Add appropriate cell content based on column name
                    switch (colName) {
                        case "Parent":
                            cell.textContent = escapeHtml(item.Parent) || '-';
                            break;

                        case "SKU":
                            cell.innerHTML = `
                    <span class="sku-hover" 
                        data-sku="${escapeHtml(item.SKU) || ''}" 
                        data-image="${item.image_path ? item.image_path : ''}">
                        ${escapeHtml(item.SKU) || '-'}
                    </span>
                `;
                            break;

                            // Add cases for other columns...

                        case "Action": {
                            cell.className = 'text-center';
                            const isParentPlaceholder = item.SKU && String(item.SKU).toUpperCase().includes('PARENT');
                            cell.innerHTML = `
                    <div class="d-inline-flex">
                        ${hasEditPermission ? 
                            `<button type="button" class="btn btn-sm btn-outline-warning edit-btn me-1" data-sku="${escapeHtml(item.SKU)}">
                                                                            <i class="bi bi-pencil-square"></i>
                                                                        </button>` 
                            : ''
                        }
                        ${hasEditPermission && !isParentPlaceholder ? 
                            `<button type="button" class="btn btn-sm btn-outline-secondary duplicate-btn me-1" data-sku="${escapeHtml(item.SKU)}" title="Duplicate as new SKU">
                                                                            <i class="bi bi-files"></i>
                                                                        </button>` 
                            : ''
                        }
                        ${hasDeletePermission ? 
                            `<button type="button" class="btn btn-sm btn-outline-danger delete-btn" data-id="${escapeHtml(item.id)}" data-sku="${escapeHtml(item.SKU)}">
                                                                            <i class="bi bi-archive"></i>
                                                                        </button>` 
                            : ''
                        }
                        ${(!hasEditPermission && !hasDeletePermission) ? '-' : ''}
                    </div>
                `;
                            break;
                        }

                        default:
                            // Handle any other column using the columnDefs mapping
                            if (columnDefs[colName]) {
                                const key = columnDefs[colName].key;
                                // Format based on column type
                                if (["lp", "cp", "frght"].includes(key)) {
                                    cell.className = 'text-center';
                                    cell.textContent = formatNumber(item[key], 2);
                                } else if (["wt_act", "wt_decl", "l", "w", "h"].includes(key)) {
                                    cell.className = 'text-center';
                                    cell.textContent = formatNumber(item[key] || 0, 2);
                                } else if (key === "cbm") {
                                    cell.className = 'text-center';
                                    cell.textContent = formatNumber(item[key], 4);
                                } else if (key === "l2_url") {
                                    cell.className = 'text-center';
                                    cell.innerHTML = item[key] ?
                                        `<a href="${escapeHtml(item[key])}" target="_blank"><i class="fas fa-external-link-alt"></i></a>` :
                                        '-';
                                } else {
                                    cell.textContent = escapeHtml(item[key]) || '-';
                                }
                            }
                            break;
                    }

                    row.appendChild(cell);
                });

                return row;
            }

            // In your initializeTable function, modify:
            function initializeTable() {
                // Get columns to hide
                const hiddenColumns = getUserHiddenColumns();

                // Update header to exclude hidden columns
                updateTableHeader(hiddenColumns);

                // Rest of initialization...
                loadData();
                setupSearch();
                setupHeaderColumnSearch();
                setupExcelExport();
                setupImport();
                setupAddProductModal();
                setupProgressModal();
                setupSelectionMode();
                setupBatchProcessing();
                setupBulkActionsModal();
                setupDuplicateProductModal();
                setupMissingDataButtons();
            }

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            //archive functionality
            $('#viewArchivedBtn').on('click', function() {
                $.ajax({
                    url: '/product_master/archived',
                    method: 'GET',
                    beforeSend: function() {
                        $('#archivedProductsTable tbody').html(`
                            <tr><td colspan="5" class="text-center py-3">
                                <div class="spinner-border text-primary" role="status"></div>
                            </td></tr>
                        `);
                    },
                    success: function(res) {
                        const tableBody = $('#archivedProductsTable tbody');
                        tableBody.empty();

                        if (res.data.length === 0) {
                            tableBody.append(`
                                <tr><td colspan="5" class="text-center py-3 text-muted">
                                    No archived products found.
                                </td></tr>
                            `);
                            return;
                        }

                        res.data.sort((a, b) => new Date(b.deleted_at) - new Date(a.deleted_at));

                        res.data.forEach(product => {
                            tableBody.append(`
                                <tr>
                                    <td>${product.id}</td>
                                    <td>${product.sku}</td>
                                    <td>${product.deleted_by_name || '-'}</td> 
                                    <td>${product.deleted_at ? new Date(product.deleted_at).toLocaleString() : '-'}</td>
                                    <td>
                                        <button class="btn btn-sm btn-success restore-btn" data-id="${product.id}">
                                            <i class="fas fa-undo me-1"></i>Restore
                                        </button>
                                    </td>
                                </tr>
                            `);
                        });

                        // Attach restore button events
                        $('.restore-btn').off('click').on('click', function() {
                            const id = $(this).data('id');
                            $.ajax({
                                url: '/product_master/restore',
                                method: 'POST',
                                data: { ids: [id] },
                                success: function(res) {
                                    if (res.success) {
                                        showToast('success', res.message || 'Product restored successfully!');
                                        $('#viewArchivedBtn').trigger('click'); // reload modal list
                                        loadData(); // reload main table
                                    } else {
                                        showToast('danger', res.message || 'Failed to restore.');
                                    }
                                },
                                error: function() {
                                    showToast('danger', 'Restore failed.');
                                }
                            });
                        });
                    },
                    error: function() {
                        $('#archivedProductsTable tbody').html(`
                            <tr><td colspan="5" class="text-center text-danger py-3">
                                Failed to load archived products.
                            </td></tr>
                        `);
                    }
                });

                const modal = new bootstrap.Modal(document.getElementById('archivedProductsModal'));
                modal.show();
            });

            // Live search for archived products
            $(document).on('keyup', '#archivedSearch', function() {
                const searchValue = normalizeForTextSearch($(this).val());

                $('#archivedProductsTable tbody tr').each(function() {
                    const sku = normalizeForTextSearch($(this).find('td:nth-child(2)').text());
                    $(this).toggle(sku.includes(searchValue));
                });
            });



            // Global applyFilters function that can be called from anywhere
            function applyFilters() {
                const parentValue = normalizeForTextSearch(document.getElementById('parentSearch')?.value || '');
                const skuValue = normalizeForTextSearch(document.getElementById('skuSearch')?.value || '');
                let filteredData = [...tableData];

                if (parentValue) {
                    filteredData = filteredData.filter(item =>
                        normalizeForTextSearch(item.Parent || item.parent || '').includes(parentValue)
                    );
                }

                if (skuValue) {
                    filteredData = filteredData.filter(item =>
                        normalizeForTextSearch(item.SKU || item.sku || '').includes(skuValue)
                    );
                }

                let excludeParentRowsForColumnFilter = false;
                document.querySelectorAll('.missing-data-filter').forEach(filter => {
                    if (filter.value === 'missing') {
                        excludeParentRowsForColumnFilter = true;
                    }
                    if (filter.value === '0' && filter.getAttribute('data-column') === 'Inventory') {
                        excludeParentRowsForColumnFilter = true;
                    }
                });

                if (excludeParentRowsForColumnFilter) {
                    filteredData = filteredData.filter(item => {
                        // Exclude parent rows (SKU contains 'PARENT')
                        return !(item.SKU && item.SKU.toUpperCase().includes('PARENT'));
                    });
                }

                // Apply missing data filters for each column
                document.querySelectorAll('.missing-data-filter').forEach(filter => {
                    const filterValue = filter.value;
                    const columnName = filter.getAttribute('data-column');
                    if (!columnName || filterValue === 'all') return; // Skip if no column name or 'all' selected

                    if (columnName === 'Inventory' && filterValue === '0') {
                        filteredData = filteredData.filter(item => {
                            const v = item.shopify_inv;
                            if (v === null || v === undefined || v === '') return false;
                            return Number(v) === 0;
                        });
                        return;
                    }
                    
                    const fieldName = getFieldNameFromColumn(columnName);
                    
                    // Special handling for STATUS column with specific status values
                    if (columnName === 'STATUS' && filterValue !== 'missing') {
                        // Filter by specific status value
                        filteredData = filteredData.filter(item => {
                            let value = null;
                            
                            // First, try to get value directly from item object
                            if (item[fieldName] !== undefined && item[fieldName] !== null) {
                                value = item[fieldName];
                            } else {
                                // Get from Values JSON if available
                                let values = {};
                                if (item.Values) {
                                    if (Array.isArray(item.Values)) {
                                        values = item.Values;
                                    } else if (typeof item.Values === 'string') {
                                        try {
                                            values = JSON.parse(item.Values);
                                        } catch (e) {
                                            values = {};
                                        }
                                    } else {
                                        values = item.Values;
                                    }
                                }
                                
                                // Try to get from Values JSON
                                if (values[fieldName] !== undefined && values[fieldName] !== null) {
                                    value = values[fieldName];
                                }
                            }
                            
                            // Compare status value (case-insensitive)
                            return value && String(value).toLowerCase() === String(filterValue).toLowerCase();
                        });
                    } else if (filterValue === 'missing') {
                        if (columnName === 'DIL') {
                            filteredData = filteredData.filter(item => {
                                const invMissing = item.shopify_inv === null || item.shopify_inv === undefined || item.shopify_inv === "";
                                const ovlMissing = item.shopify_quantity === null || item.shopify_quantity === undefined || item.shopify_quantity === "";
                                const inv = Number(item.shopify_inv) || 0;
                                return invMissing || ovlMissing || inv === 0;
                            });
                        } else {
                        // Original missing data filter logic
                        filteredData = filteredData.filter(item => {
                            let value = null;
                            
                            // First, try to get value directly from item object
                            if (item[fieldName] !== undefined && item[fieldName] !== null) {
                                value = item[fieldName];
                            } else {
                                // Get from Values JSON if available
                                let values = {};
                                if (item.Values) {
                                    if (Array.isArray(item.Values)) {
                                        values = item.Values;
                                    } else if (typeof item.Values === 'string') {
                                        try {
                                            values = JSON.parse(item.Values);
                                        } catch (e) {
                                            values = {};
                                        }
                                    } else {
                                        values = item.Values;
                                    }
                                }
                                
                                // Try to get from Values JSON
                                if (values[fieldName] !== undefined && values[fieldName] !== null) {
                                    value = values[fieldName];
                                }
                            }
                            
                            // Determine if numeric based on column
                            const numericColumns = ['LP', 'CP$', 'FRGHT', 'SHIP', 'TEMU SHIP', 'MOQ', 'EBAY2 SHIP', 'Label QTY', 'WT ACT', 'WT DECL', 'Length', 'Width', 'Height', 'CBM', 'UPC', 'Inventory', 'OV L30'];
                            const isNumeric = numericColumns.includes(columnName);
                            
                            // Special handling for dimensions and weights - treat 0 as missing
                            if (isNumeric && ['l', 'w', 'h', 'wt_act', 'wt_decl', 'label_qty', 'moq'].includes(fieldName)) {
                                const num = parseFloat(value);
                                return isDataMissing(value, true) || (num === 0 || num === '0');
                            }
                            
                            return isDataMissing(value, isNumeric);
                        });
                        }
                    }
                });

                renderTable(filteredData);
            }

            function setupSearch() {

                // Use event delegation so listeners survive header re-rendering
                document.addEventListener('input', debounce(function (e) {
                    const id = e.target && e.target.id;
                    if (!id) return;
                    if (id === 'skuSearch' || id === 'parentSearch') {
                        applyFilters();
                    }
                }, 250));

                // Listen for missing data filter changes
                document.addEventListener('change', function (e) {
                    if (e.target && e.target.classList.contains('missing-data-filter')) {
                        const columnName = e.target.getAttribute('data-column');
                        if (columnName) {
                            // Update global filter values
                            if (e.target.value === 'all') {
                                delete currentFilterValues[columnName];
                            } else {
                                currentFilterValues[columnName] = e.target.value;
                            }
                        }
                        updateFilterStyling(e.target);
                        applyFilters();
                    }
                });

                document.addEventListener('click', function (e) {
                    const wrap = e.target.closest('.pm-status-filter-wrap');
                    const item = e.target.closest('.pm-status-filter-item');
                    const trigger = e.target.closest('.pm-status-filter-trigger');

                    if (item && wrap) {
                        e.preventDefault();
                        e.stopPropagation();
                        const val = item.getAttribute('data-value');
                        const hidden = wrap.querySelector('.pm-status-filter-hidden');
                        if (!hidden) return;
                        hidden.value = val;
                        hidden.dispatchEvent(new Event('change', { bubbles: true }));
                        wrap.classList.remove('is-open');
                        const trg = wrap.querySelector('.pm-status-filter-trigger');
                        if (trg) trg.setAttribute('aria-expanded', 'false');
                        refreshPmStatusFilterUI();
                        return;
                    }

                    if (trigger && wrap) {
                        e.preventDefault();
                        e.stopPropagation();
                        const wasOpen = wrap.classList.contains('is-open');
                        document.querySelectorAll('.pm-status-filter-wrap.is-open').forEach(w => {
                            w.classList.remove('is-open');
                            const t = w.querySelector('.pm-status-filter-trigger');
                            if (t) t.setAttribute('aria-expanded', 'false');
                        });
                        if (!wasOpen) {
                            wrap.classList.add('is-open');
                            trigger.setAttribute('aria-expanded', 'true');
                            positionPmStatusFilterMenu(wrap);
                        }
                        return;
                    }

                    if (!wrap) {
                        document.querySelectorAll('.pm-status-filter-wrap.is-open').forEach(w => {
                            w.classList.remove('is-open');
                            const t = w.querySelector('.pm-status-filter-trigger');
                            if (t) t.setAttribute('aria-expanded', 'false');
                        });
                    }
                });

            }

            // Header column search is intentionally a no-op because we handle header inputs
            // via delegated listeners inside setupSearch() above. Keeping this function
            // prevents other code from breaking if it's called elsewhere.
            function setupHeaderColumnSearch() {
                // No-op: listeners are attached by setupSearch using delegation so they
                // survive dynamic header updates.
            }


            // Function to get columns to hide for current user
            function getUserHiddenColumns() {
                // Always hide these columns
                const alwaysHiddenColumns = ['WT ACT', 'WT DECL', 'Width', 'Height', 'Length', 'Label QTY', 'CBM', 'SHIP', 'TEMU SHIP', 'EBAY2 SHIP'];
                
                // Default columns to hide if user has no specific permissions
                const defaultHiddenColumns = [...alwaysHiddenColumns];

                if (currentUserEmail && emailColumnMap[currentUserEmail]) {
                    const userHiddenColumns = (emailColumnMap[currentUserEmail] || []).map(c => c === 'INV' ? 'Inventory' : c);
                    return [...new Set([...alwaysHiddenColumns, ...userHiddenColumns])];
                }

                return defaultHiddenColumns;
            }

            // Update Excel export function to exclude hidden columns
            function setupExcelExport() {
                const downloadExcelBtn = document.getElementById('downloadExcel');
                if (downloadExcelBtn) {
                    downloadExcelBtn.addEventListener('click', function() {
                    const hiddenColumns = getUserHiddenColumns();
                    const allColumns = [
                        "Parent", "SKU", "UPC", "Inventory", "OV L30", "DIL", "STATUS", "Unit", "LP", "CP$",
                        "FRGHT", "SHIP", "TEMU SHIP", "MOQ", "EBAY2 SHIP", "Label QTY", "WT ACT", "WT DECL", "Length", "Width", "Height",
                        "CBM", "Image", "Url", "Verified", "DC", "Pcs/Box", "B", "H1", "Weight", "MSRP", "MAP"
                    ];

                    // Filter out hidden columns
                    const visibleColumns = allColumns.filter(col => !hiddenColumns.includes(col));

                    // Column definitions with their data keys
                    const columnDefs = {
                        "Parent": {
                            key: "Parent"
                        },
                        "SKU": {
                            key: "SKU"
                        },
                        "UPC": {
                            key: "upc"
                        },
                        "Inventory": {
                            key: "shopify_inv"
                        },
                        "OV L30": {
                            key: "shopify_quantity"
                        },
                        "STATUS": {
                            key: "status"
                        },
                        "Unit": {
                            key: "unit"
                        },
                        "LP": {
                            key: "lp"
                        },
                        "CP$": {
                            key: "cp"
                        },
                        "FRGHT": {
                            key: "frght"
                        },
                        "SHIP": {
                            key: "ship"
                        },
                        "TEMU SHIP": {
                            key: "temu_ship"
                        },
                        "MOQ": {
                            key: "moq"
                        },
                        "EBAY2 SHIP": {
                            key: "ebay2_ship"
                        },
                        "Label QTY": {
                            key: "label_qty"
                        },
                        "WT ACT": {
                            key: "wt_act"
                        },
                        "WT DECL": {
                            key: "wt_decl"
                        },
                        "Length": {
                            key: "l"
                        },
                        "Width": {
                            key: "w"
                        },
                        "Height": {
                            key: "h"
                        },
                        "CBM": {
                            key: "cbm"
                        },
                        "Image": {
                            key: "image_path"
                        },
                        "Url": {
                            key: "l2_url"
                        },
                        "Verified": {
                            key: "verified_data"
                        },
                        "DC": {
                            key: "dc"
                        },
                        "Pcs/Box": {
                            key: "pcs_per_box"
                        },
                        "B": {
                            key: "b"
                        },
                        "H1": {
                            key: "h1"
                        },
                        "Weight": {
                            key: "weight"
                        },
                        "MSRP": {
                            key: "msrp"
                        },
                        "MAP": {
                            key: "map"
                        }
                    };

                    // Show loader or indicate download is in progress
                    downloadExcelBtn.innerHTML =
                        '<i class="fas fa-spinner fa-spin"></i> Generating...';
                    downloadExcelBtn.disabled = true;

                    // Use setTimeout to avoid UI freeze for large datasets
                    setTimeout(() => {
                        try {
                            // Create worksheet data array
                            const wsData = [];

                            // Add header row
                            wsData.push(visibleColumns);

                            // Add data rows - include all data including parent SKUs
                            tableData.forEach(item => {
                                const row = [];
                                visibleColumns.forEach(col => {
                                    if (col === 'DIL') {
                                        const invMissing = item.shopify_inv === null || item.shopify_inv === undefined || item.shopify_inv === '';
                                        const ovlMissing = item.shopify_quantity === null || item.shopify_quantity === undefined || item.shopify_quantity === '';
                                        let dilVal = '';
                                        if (!invMissing && !ovlMissing) {
                                            const inv = Number(item.shopify_inv) || 0;
                                            const ovl = Number(item.shopify_quantity) || 0;
                                            if (inv !== 0) dilVal = `${Math.round((ovl / inv) * 100)}%`;
                                        }
                                        row.push(dilVal);
                                        return;
                                    }
                                    const colDef = columnDefs[col];
                                    if (colDef) {
                                        const key = colDef.key;
                                        let value = '';
                                        
                                        // Check both direct property and Values JSON - direct property takes precedence
                                        if (key === "image_path") {
                                            value = item.image_path || (item.Values && item.Values.image_path) || '';
                                        } else if (key === "verified_data") {
                                            const verified = item.verified_data !== undefined ? item.verified_data : (item.Values && item.Values.verified_data);
                                            value = verified === 1 || verified === true ? 'Yes' : 'No';
                                        } else {
                                            // Check Values JSON only for Values column data
                                            if (item.Values && item.Values[key] !== undefined && item.Values[key] !== null && item.Values[key] !== '') {
                                                value = item.Values[key];
                                            } else if (item[key] !== undefined && item[key] !== null && item[key] !== '') {
                                                value = item[key];
                                            } else {
                                                value = '';
                                            }
                                        }

                                        // Format special columns (numeric fields)
                                        if (["lp", "cp", "frght"].includes(key)) {
                                            value = parseFloat(value) || 0;
                                        } else if (["wt_act", "wt_decl", "l", "w", "h"].includes(key)) {
                                            value = parseFloat(value) || 0;
                                        } else if (key === "cbm") {
                                            value = parseFloat(value) || 0;
                                        }

                                        row.push(value);
                                    } else {
                                        row.push('');
                                    }
                                });
                                wsData.push(row);
                            });

                            // Create workbook and worksheet
                            const wb = XLSX.utils.book_new();
                            const ws = XLSX.utils.aoa_to_sheet(wsData);

                            // Set column widths
                            const wscols = visibleColumns.map(col => {
                                // Adjust width based on column type
                                if (["SKU", "Parent"].includes(col)) {
                                    return {
                                        wch: 20
                                    }; // Wider for text columns
                                } else if (["STATUS", "Unit"].includes(col)) {
                                    return {
                                        wch: 12
                                    };
                                } else if (["Image", "Url"].includes(col)) {
                                    return {
                                        wch: 50
                                    }; // Wider for URL columns
                                } else {
                                    return {
                                        wch: 10
                                    }; // Default width for numeric columns
                                }
                            });
                            ws['!cols'] = wscols;

                            // Style the header row
                            const headerRange = XLSX.utils.decode_range(ws['!ref']);
                            for (let C = headerRange.s.c; C <= headerRange.e.c; ++C) {
                                const cell = XLSX.utils.encode_cell({
                                    r: 0,
                                    c: C
                                });
                                if (!ws[cell]) continue;

                                // Add header style
                                ws[cell].s = {
                                    fill: {
                                        fgColor: {
                                            rgb: "2C6ED5"
                                        }
                                    },
                                    font: {
                                        bold: true,
                                        color: {
                                            rgb: "FFFFFF"
                                        }
                                    },
                                    alignment: {
                                        horizontal: "center"
                                    }
                                };
                            }

                            // Add the worksheet to the workbook
                            XLSX.utils.book_append_sheet(wb, ws, "Product Master");

                            // Generate Excel file and trigger download
                            XLSX.writeFile(wb, "product_master_export.xlsx");

                            // Show success toast
                            showToast('success', 'Excel file downloaded successfully!');
                        } catch (error) {
                            console.error("Excel export error:", error);
                            showToast('danger', 'Failed to export Excel file.');
                        } finally {
                            // Reset button state
                            downloadExcelBtn.innerHTML =
                                '<i class="fas fa-file-excel me-1"></i> Export';
                            downloadExcelBtn.disabled = false;
                        }
                    }, 100); // Small timeout to allow UI to update
                    });
                }
            }

            // Setup import functionality (Missing Data Only)
            function setupImport() {
                const importFile = document.getElementById('importFile');
                const importBtn = document.getElementById('importBtn');
                const importModal = document.getElementById('importExcelModal');
                const fileError = document.getElementById('fileError');
                const importProgress = document.getElementById('importProgress');
                const importResult = document.getElementById('importResult');

                // Enable/disable import button based on file selection
                importFile.addEventListener('change', function() {
                    if (this.files && this.files.length > 0) {
                        const file = this.files[0];
                        const fileName = file.name.toLowerCase();
                        const validExtensions = ['.xlsx', '.xls', '.csv'];
                        const isValid = validExtensions.some(ext => fileName.endsWith(ext));

                        if (isValid) {
                            importBtn.disabled = false;
                            fileError.style.display = 'none';
                        } else {
                            importBtn.disabled = true;
                            fileError.textContent = 'Please select a valid Excel file (.xlsx, .xls, or .csv)';
                            fileError.style.display = 'block';
                        }
                    } else {
                        importBtn.disabled = true;
                    }
                });

                // Handle import
                importBtn.addEventListener('click', async function() {
                    const file = importFile.files[0];
                    if (!file) {
                        showToast('danger', 'Please select a file to import');
                        return;
                    }

                    // Disable button and show progress
                    importBtn.disabled = true;
                    importProgress.style.display = 'block';
                    importResult.style.display = 'none';
                    fileError.style.display = 'none';

                    const formData = new FormData();
                    formData.append('excel_file', file);
                    formData.append('_token', csrfToken);

                    try {
                        const response = await fetch('/product-master/import', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: formData
                        });

                        const result = await response.json();

                        // Update progress bar
                        const progressBar = importProgress.querySelector('.progress-bar');
                        progressBar.style.width = '100%';

                        if (response.ok && result.success) {
                            importResult.className = 'alert alert-success';
                            importResult.innerHTML = `
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <i class="fas fa-check-circle me-2"></i>
                                        <strong>Import Successful!</strong><br>
                                        ${result.message || `Successfully imported ${result.imported || 0} records.`}
                                        ${result.errors && result.errors.length > 0 ? `<br><small>Errors: ${result.errors.length}</small>` : ''}
                                    </div>
                                    <button type="button" class="btn btn-sm btn-success ms-3" id="importOkBtn" style="white-space: nowrap;">
                                        <i class="fas fa-check me-1"></i> OK
                                    </button>
                                </div>
                            `;
                            importResult.style.display = 'block';

                            // Reload data after successful import
                            setTimeout(() => {
                                loadData();
                            }, 500);

                            // Handle OK button click
                            const okBtn = document.getElementById('importOkBtn');
                            if (okBtn) {
                                okBtn.addEventListener('click', function() {
                                    const modal = bootstrap.Modal.getInstance(importModal);
                                    if (modal) modal.hide();
                                    // Reset form
                                    importFile.value = '';
                                    importBtn.disabled = true;
                                    importProgress.style.display = 'none';
                                    importResult.style.display = 'none';
                                    progressBar.style.width = '0%';
                                });
                            }
                        } else {
                            importResult.className = 'alert alert-danger';
                            importResult.innerHTML = `
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Import Failed!</strong><br>
                                ${result.message || 'An error occurred during import.'}
                            `;
                            importResult.style.display = 'block';
                            importBtn.disabled = false;
                        }
                    } catch (error) {
                        console.error('Import error:', error);
                        importResult.className = 'alert alert-danger';
                        importResult.innerHTML = `
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Import Failed!</strong><br>
                            ${error.message || 'An error occurred during import.'}
                        `;
                        importResult.style.display = 'block';
                        importBtn.disabled = false;
                    } finally {
                        // Reset progress bar after a delay
                        setTimeout(() => {
                            const progressBar = importProgress.querySelector('.progress-bar');
                            progressBar.style.width = '0%';
                        }, 2000);
                    }
                });

                // Reset form when modal is closed
                importModal.addEventListener('hidden.bs.modal', function() {
                    importFile.value = '';
                    importBtn.disabled = true;
                    importProgress.style.display = 'none';
                    importResult.style.display = 'none';
                    fileError.style.display = 'none';
                    const progressBar = importProgress.querySelector('.progress-bar');
                    if (progressBar) progressBar.style.width = '0%';
                });
            }

            // Initialize the add product modal
            function setupAddProductModal() {
                const modal = document.getElementById('addProductModal');
                const saveBtn = document.getElementById('saveProductBtn');
                const refreshParentsBtn = document.getElementById('refreshParents');

                // Setup event listeners for calculations
                document.getElementById('w')?.addEventListener('input', calculateCBM);
                document.getElementById('l')?.addEventListener('input', calculateCBM);
                document.getElementById('h')?.addEventListener('input', calculateCBM);
                document.getElementById('cp')?.addEventListener('input', calculateLP);
                
                // Add SKU availability check on input
                document.getElementById('sku')?.addEventListener('input', function() {
                    const skuField = this;
                    const sku = skuField.value.trim();
                    const saveBtn = document.getElementById('saveProductBtn');
                    const originalSku = saveBtn.getAttribute('data-original-sku') || null;
                    
                    // Only validate if SKU has actual content and isn't a PARENT
                    if (sku && !sku.toUpperCase().includes('PARENT')) {
                        if (!checkSkuAvailability(sku, originalSku)) {
                            showFieldError(skuField, 'This SKU already exists. Please use a different SKU.');
                        } else {
                            clearFieldError(skuField);
                        }
                    }
                });

                refreshParentsBtn.addEventListener('click', updateParentOptions);

                saveBtn.addEventListener('click', async function() {
                    if (!validateProductForm(false)) return;

                    const formData = getFormData();
                    formData.append('operation', 'create');

                    try {
                        const response = await fetch('/product_master/store', {
                            method: 'POST',
                            // Do NOT set Content-Type when using FormData!
                            headers: {
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: formData
                        });
                        const data = await response.json();

                        if (!response.ok) {
                            // Check if it's a duplicate entry error
                            if (response.status === 409 || 
                                (data.message && data.message.includes('already exists')) ||
                                (data.message && data.message.includes('Duplicate entry'))) {
                                
                                // Show clear error message with SKU information
                                showToast('warning', data.message || 'This SKU already exists in the database!');
                                
                                // Highlight the SKU field to draw attention
                                const skuField = document.getElementById('sku');
                                skuField.classList.add('is-invalid');
                                
                                // Create a feedback div if it doesn't exist
                                let feedback = skuField.nextElementSibling;
                                if (!feedback || !feedback.classList.contains('invalid-feedback')) {
                                    feedback = document.createElement('div');
                                    feedback.className = 'invalid-feedback';
                                    skuField.parentNode.appendChild(feedback);
                                }
                                feedback.textContent = 'This SKU already exists. Please use a different SKU.';
                                
                                return;
                            }
                            throw new Error(data.message || `Server returned status ${response.status}`);
                        }
                        
                        // Show success message
                        showToast('success', 'Product successfully added to database!');
                        bootstrap.Modal.getInstance(modal).hide();
                        patchTableDataAfterStore(data);
                        resetProductForm();
                    } catch (error) {
                        showAlert('danger', error.message);
                    }
                });

                modal.addEventListener('hidden.bs.modal', resetProductForm);
            }

            // Image preview on file select
            const productImage = document.getElementById('productImage');
            if (productImage) {
                productImage.addEventListener('change', function(e) {
                const preview = document.getElementById('imagePreview');
                preview.innerHTML = '';
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(ev) {
                        preview.innerHTML =
                            `<img src="${ev.target.result}" alt="Preview" style="max-width:120px;max-height:120px;border-radius:8px;">`;
                    };
                    reader.readAsDataURL(this.files[0]);
                }
                });
            }

            // Calculate CBM based on dimensions
            function calculateCBM() {
                const w = parseFloat(document.getElementById('w').value) || 0;
                const l = parseFloat(document.getElementById('l').value) || 0;
                const h = parseFloat(document.getElementById('h').value) || 0;

                // Convert to cm then to m³ per user formula: ((L*2.54)*(W*2.54)*(H*2.54))/1000000
                let cbm = 0;
                if (w > 0 && l > 0 && h > 0) {
                    cbm = ((l * 2.54) * (w * 2.54) * (h * 2.54)) / 1000000;
                }
                // CBM and FRGHT removed from form; recalc LP from CP + (CBM*200)
                calculateLP();
            }

            // Calculate LP based on CP and FRGHT (FRGHT = CBM * 200; CBM from dimensions)
            function calculateLP() {
                const cp = parseFloat(document.getElementById('cp').value) || 0;
                const w = parseFloat(document.getElementById('w').value) || 0;
                const l = parseFloat(document.getElementById('l').value) || 0;
                const h = parseFloat(document.getElementById('h').value) || 0;
                let cbm = 0;
                if (w > 0 && l > 0 && h > 0) {
                    cbm = ((l * 2.54) * (w * 2.54) * (h * 2.54)) / 1000000;
                }
                const frght = cbm * 200;
                const lp = cp + frght;
                const lpEl = document.getElementById('lp');
                if (lpEl) lpEl.value = lp.toFixed(2);
            }
            
            // Function to check if SKU already exists in our data
            function checkSkuAvailability(sku, originalSku = null) {
                // If we're editing and the SKU hasn't changed, it's available
                if (originalSku && sku === originalSku) {
                    return true;
                }
                
                // Check if SKU exists in current table data
                const exists = tableData.some(product => product.SKU === sku);
                return !exists;
            }

            // Validate the product form
            function validateProductForm(isUpdate = false) {
                const sku = document.getElementById('sku').value;
                // Get original SKU if in edit mode
                const originalSku = isUpdate ? document.getElementById('saveProductBtn').getAttribute('data-original-sku') : null;
                
                // If SKU contains 'PARENT', skip required validation
                if (sku && sku.toUpperCase().includes('PARENT')) {
                    // Clear any previous errors
                    document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
                    document.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');
                    document.getElementById('form-errors').innerHTML = '';
                    return true;
                }

                let isValid = true;
                // Only required fields: sku and unit
                const requiredFields = ['sku', 'unit'];
                const numericFields = ['labelQty', 'cp', 'wtAct', 'wtDecl', 'w', 'l', 'h'];
                const skuField = document.getElementById('sku');
                
                // Check if SKU already exists (front-end validation)
                if (sku && !checkSkuAvailability(sku, originalSku)) {
                    showFieldError(skuField, 'This SKU already exists in the database. Please use a different SKU.');
                    isValid = false;
                    
                    // Show toast with more detailed message
                    showToast('warning', `Product with SKU "${sku}" already exists. Please use a different SKU.`);
                }

                requiredFields.forEach(id => {
                    const field = document.getElementById(id);
                    if (!field) return; // Skip if field doesn't exist
                    
                    const fieldValue = field.value ? field.value.trim() : '';
                    
                    // Check if field is empty
                    if (!fieldValue) {
                        let errorMessage = 'This field is required';
                        if (id === 'unit') {
                            errorMessage = 'Please select a unit';
                        }
                        showFieldError(field, errorMessage);
                        isValid = false;
                    } else {
                        clearFieldError(field);
                    }
                });

                // Validate numeric fields only if they have values (optional validation)
                numericFields.forEach(id => {
                    const field = document.getElementById(id);
                    if (!field) return;
                    
                    const fieldValue = field.value ? field.value.trim() : '';
                    
                    // Only validate if field has a value
                    if (fieldValue) {
                        const numValue = parseFloat(fieldValue);
                        if (isNaN(numValue) || numValue < 0) {
                            showFieldError(field, 'Must be a valid positive number');
                            isValid = false;
                        } else {
                            clearFieldError(field);
                        }
                    }
                });

                return isValid;
            }

            // Modify getFormData to use FormData for file upload
            function getFormData() {
                const formElement = document.getElementById('addProductForm');
                const formData = new FormData(formElement);

                // Add all fields as before
                formData.append('parent', document.getElementById('parent').value || '');
                formData.append('sku', document.getElementById('sku').value);

                // Build Values JSON
                const w = parseFloat(document.getElementById('w').value) || 0;
                const l = parseFloat(document.getElementById('l').value) || 0;
                const h = parseFloat(document.getElementById('h').value) || 0;
                let cbm = null, frght = null;
                if (w > 0 && l > 0 && h > 0) {
                    cbm = ((l * 2.54) * (w * 2.54) * (h * 2.54)) / 1000000;
                    frght = cbm * 200;
                }
                const values = {
                    lp: document.getElementById('lp').value || null,
                    cp: document.getElementById('cp').value || null,
                    frght: frght != null ? frght.toFixed(2) : null,
                    lps: document.getElementById('lps')?.value || null,
                    ship: document.getElementById('ship').value || null,
                    temu_ship: document.getElementById('temu_ship').value || null,
                    moq: document.getElementById('moq').value || null,
                    ebay2_ship: document.getElementById('ebay2_ship').value || null,
                    label_qty: document.getElementById('labelQty').value || null,
                    wt_act: document.getElementById('wtAct').value || null,
                    wt_decl: document.getElementById('wtDecl').value || null,
                    l: document.getElementById('l').value || null,
                    w: document.getElementById('w').value || null,
                    h: document.getElementById('h').value || null,
                    cbm: cbm != null ? cbm.toFixed(4) : null,
                    dc: document.getElementById('dc')?.value || null,
                    l2_url: document.getElementById('l2Url').value || null,
                    pcs_per_box: document.getElementById('pcbox').value || null,
                    b: document.getElementById('b').value || null,
                    h1: document.getElementById('h1').value || null,
                    weight: document.getElementById('weight').value || null,
                    msrp: document.getElementById('msrp').value || null,
                    map: document.getElementById('map').value || null,
                    status: document.getElementById('status').value || null,
                    unit: document.getElementById('unit').value || null,
                    upc: document.getElementById('upc').value || null,
                };

                formData.append('Values', JSON.stringify(values));
                // The image file is already included by <input name="image">

                return formData;
            }


            // Update parent options in datalist
            function updateParentOptions() {
                const parentOptions = document.getElementById('parentOptions');
                parentOptions.innerHTML = '';

                const parentSKUs = new Set();
                tableData.forEach(item => {
                    // Only add Parent values that do NOT contain 'PARENT'
                    if (item.Parent && !item.Parent.toUpperCase().includes('PARENT')) {
                        parentSKUs.add(item.Parent);
                    }
                });

                parentSKUs.forEach(sku => {
                    const option = document.createElement('option');
                    option.value = sku;
                    parentOptions.appendChild(option);
                });
            }

            // Setup edit buttons
            function setupEditButtons() {
                document.querySelectorAll('.edit-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const sku = this.getAttribute('data-sku');
                        const product = productMap.get(sku); // O(1) lookup instead of O(n) find
                        if (product) {
                            editProduct(product);
                        }
                    });
                });
            }

            function setupDuplicateButtons() {
                document.querySelectorAll('.duplicate-btn').forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        const sku = this.getAttribute('data-sku');
                        const product = productMap.get(sku);
                        if (!product) {
                            showToast('warning', 'Product not found.');
                            return;
                        }
                        if (String(product.SKU || '').toUpperCase().includes('PARENT')) {
                            return;
                        }
                        const dupSku = document.getElementById('duplicateNewSku');
                        const dupFb = document.getElementById('duplicateNewSkuFeedback');
                        if (dupSku) {
                            dupSku.classList.remove('is-invalid');
                            dupSku.value = '';
                        }
                        if (dupFb) dupFb.textContent = 'SKU is required.';
                        document.getElementById('duplicateSourceSku').value = product.SKU || '';
                        document.getElementById('duplicateSourceParent').value = product.Parent ?? '';
                        document.getElementById('duplicateSourceSkuDisplay').textContent = product.SKU || '—';
                        document.getElementById('duplicateNewParent').value = product.Parent ?? '';
                        const m = document.getElementById('duplicateProductModal');
                        bootstrap.Modal.getOrCreateInstance(m).show();
                        setTimeout(() => {
                            if (dupSku) dupSku.focus();
                        }, 350);
                    });
                });
            }

            function setupDuplicateProductModal() {
                const btn = document.getElementById('confirmDuplicateBtn');
                if (!btn || btn.dataset.duplicateBound === '1') return;
                btn.dataset.duplicateBound = '1';
                btn.addEventListener('click', async function() {
                    const newSkuEl = document.getElementById('duplicateNewSku');
                    const newSku = newSkuEl ? newSkuEl.value.trim() : '';
                    const newParent = document.getElementById('duplicateNewParent') ?
                        document.getElementById('duplicateNewParent').value.trim() : '';
                    const sourceSku = document.getElementById('duplicateSourceSku').value;
                    const sourceParent = document.getElementById('duplicateSourceParent').value;
                    const dupFb = document.getElementById('duplicateNewSkuFeedback');
                    if (!newSku) {
                        if (newSkuEl) newSkuEl.classList.add('is-invalid');
                        if (dupFb) dupFb.textContent = 'Enter a new SKU.';
                        return;
                    }
                    if (newSkuEl) newSkuEl.classList.remove('is-invalid');

                    try {
                        const response = await fetch('/product_master/duplicate', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({
                                source_sku: sourceSku,
                                source_parent: sourceParent || null,
                                new_sku: newSku,
                                new_parent: newParent || null
                            })
                        });
                        const data = await response.json();

                        if (!response.ok) {
                            showToast('warning', data.message || 'Could not create duplicate.');
                            if (response.status === 409 && newSkuEl) {
                                newSkuEl.classList.add('is-invalid');
                                if (dupFb) {
                                    dupFb.textContent = data.message && data.message.includes('SKU') ?
                                        data.message : 'This SKU already exists. Choose another.';
                                }
                            }
                            return;
                        }

                        showToast('success', data.message || 'Product duplicated successfully.');
                        const modalEl = document.getElementById('duplicateProductModal');
                        const inst = bootstrap.Modal.getInstance(modalEl);
                        if (inst) inst.hide();

                        patchTableDataAfterStore(data);
                    } catch (err) {
                        showToast('danger', err.message || 'Duplicate failed');
                    }
                });
            }

            // Edit product
            function editProduct(product) {
                const modal = new bootstrap.Modal(document.getElementById('addProductModal'));
                const saveBtn = document.getElementById('saveProductBtn');

                // Clone and replace the save button to prevent multiple event listeners
                const newSaveBtn = saveBtn.cloneNode(true);
                saveBtn.parentNode.replaceChild(newSaveBtn, saveBtn);

                newSaveBtn.setAttribute('data-original-sku', product.SKU || '');
                newSaveBtn.setAttribute('data-original-parent', product.Parent || '');
                newSaveBtn.innerHTML = '<i class="fas fa-save me-2"></i> Update Product';

                newSaveBtn.addEventListener('click', async function() {
                    if (!validateProductForm(true)) return;

                    const formData = getFormData();
                    formData.append('operation', 'update');
                    formData.append('original_sku', this.getAttribute('data-original-sku'));
                    formData.append('original_parent', this.getAttribute('data-original-parent'));

                    try {
                        const response = await fetch('/product_master/store', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: formData
                        });
                        const data = await response.json();

                        if (!response.ok) {
                            // Check if it's a duplicate entry error
                            if (response.status === 409 || 
                                (data.message && data.message.includes('already exists')) ||
                                (data.message && data.message.includes('Duplicate entry'))) {
                                
                                // Show clear error message with SKU information
                                showToast('warning', data.message || 'Another product with this SKU already exists!');
                                
                                // Highlight the SKU field to draw attention
                                const skuField = document.getElementById('sku');
                                skuField.classList.add('is-invalid');
                                
                                // Create a feedback div if it doesn't exist
                                let feedback = skuField.nextElementSibling;
                                if (!feedback || !feedback.classList.contains('invalid-feedback')) {
                                    feedback = document.createElement('div');
                                    feedback.className = 'invalid-feedback';
                                    skuField.parentNode.appendChild(feedback);
                                }
                                feedback.textContent = 'This SKU already exists. Please use a different SKU.';
                                
                                return;
                            }
                            throw new Error(data.message ||
                                `Server returned status ${response.status}`);
                        }

                        // Show specific success message for update
                        showToast('success', `Product ${formData.get('sku')} updated successfully!`);
                        modal.hide();
                        
                        // Update local data and visible cells without reloading
                        const sku = formData.get('sku');
                        if (data.data) {
                            const updatedProduct = data.data;
                            
                            // Extract Values JSON and flatten to top level for table rendering
                            let valuesObj = {};
                            if (updatedProduct.Values) {
                                if (typeof updatedProduct.Values === 'string') {
                                    try {
                                        valuesObj = JSON.parse(updatedProduct.Values);
                                    } catch (e) {
                                        valuesObj = {};
                                    }
                                } else if (typeof updatedProduct.Values === 'object' && updatedProduct.Values !== null) {
                                    valuesObj = updatedProduct.Values;
                                }
                            }
                            
                            // Merge Values JSON fields into top level for easier access
                            const flattenedProduct = {...updatedProduct};
                            
                            // Process all Values fields
                            Object.keys(valuesObj).forEach(key => {
                                if (valuesObj[key] !== null && valuesObj[key] !== undefined && valuesObj[key] !== '') {
                                    // Special handling for image_path - ensure proper path format
                                    if (key === 'image_path') {
                                        let imagePath = valuesObj[key];
                                        // Ensure path starts with / if it's a local path (not URL)
                                        if (imagePath && typeof imagePath === 'string') {
                                            // Remove any existing leading slash to avoid double slashes
                                            imagePath = imagePath.trim();
                                            if (!imagePath.startsWith('http://') && !imagePath.startsWith('https://') && !imagePath.startsWith('/')) {
                                                imagePath = '/' + imagePath;
                                            } else if (imagePath.startsWith('storage/')) {
                                                // If it starts with storage/, ensure it has leading slash
                                                imagePath = '/' + imagePath;
                                            }
                                        }
                                        flattenedProduct[key] = imagePath;
                                    } else {
                                        flattenedProduct[key] = valuesObj[key];
                                    }
                                }
                            });
                            
                            // Also ensure image_path is at top level if it exists in Values but wasn't set above
                            if (valuesObj.image_path && (!flattenedProduct.image_path || flattenedProduct.image_path === updatedProduct.image_path)) {
                                let imagePath = valuesObj.image_path;
                                if (imagePath && typeof imagePath === 'string') {
                                    imagePath = imagePath.trim();
                                    if (!imagePath.startsWith('http://') && !imagePath.startsWith('https://') && !imagePath.startsWith('/')) {
                                        imagePath = '/' + imagePath;
                                    } else if (imagePath.startsWith('storage/')) {
                                        imagePath = '/' + imagePath;
                                    }
                                }
                                flattenedProduct.image_path = imagePath;
                            }
                            
                            // If image_path is already at top level, ensure it has proper format
                            if (flattenedProduct.image_path && typeof flattenedProduct.image_path === 'string') {
                                let imagePath = flattenedProduct.image_path.trim();
                                if (!imagePath.startsWith('http://') && !imagePath.startsWith('https://') && !imagePath.startsWith('/')) {
                                    flattenedProduct.image_path = '/' + imagePath;
                                } else if (imagePath.startsWith('storage/')) {
                                    flattenedProduct.image_path = '/' + imagePath;
                                }
                            }
                            
                            // Update tableData - ensure all fields are properly updated
                            const existingIndex = tableData.findIndex(p => {
                                const pSku = p.SKU || p.sku || '';
                                return pSku === sku || pSku.toLowerCase() === sku.toLowerCase();
                            });
                            
                            if (existingIndex !== -1) {
                                // Preserve SKU and Parent as they might be case-sensitive
                                const originalSku = tableData[existingIndex].SKU || tableData[existingIndex].sku;
                                const originalParent = tableData[existingIndex].Parent || tableData[existingIndex].parent;
                                
                                // Update all fields from the flattened product
                                Object.keys(flattenedProduct).forEach(key => {
                                    if (key !== 'SKU' && key !== 'sku' && key !== 'Parent' && key !== 'parent' && key !== 'id') {
                                        if (key === 'Values') {
                                            // Store Values JSON as is
                                            tableData[existingIndex].Values = updatedProduct.Values;
                                        } else {
                                            // Update direct property (including flattened Values fields like cp, lp, etc.)
                                            tableData[existingIndex][key] = flattenedProduct[key];
                                        }
                                    }
                                });
                                
                                // Also update Values JSON structure
                                if (updatedProduct.Values) {
                                    tableData[existingIndex].Values = updatedProduct.Values;
                                }
                                
                                // Ensure SKU and Parent are preserved with correct casing
                                if (originalSku) {
                                    tableData[existingIndex].SKU = originalSku;
                                    tableData[existingIndex].sku = originalSku;
                                }
                                if (originalParent) {
                                    tableData[existingIndex].Parent = originalParent;
                                    tableData[existingIndex].parent = originalParent;
                                }
                            } else {
                                // If not found, add it (shouldn't happen but handle it)
                                tableData.push(flattenedProduct);
                            }
                            
                            // Update productMap with flattened data
                            const existing = productMap.get(sku);
                            if (existing) {
                                Object.keys(flattenedProduct).forEach(key => {
                                    if (key !== 'SKU' && key !== 'sku' && key !== 'Parent' && key !== 'parent' && key !== 'id') {
                                        if (key === 'Values') {
                                            existing.Values = updatedProduct.Values;
                                        } else {
                                            existing[key] = flattenedProduct[key];
                                        }
                                    }
                                });
                            } else {
                                productMap.set(sku, flattenedProduct);
                            }
                            
                            // Re-render from local tableData (store response already includes formatted image_path)
                            applyFilters();
                            setTimeout(() => {
                                setupEditButtons();
                                setupDuplicateButtons();
                                setupDeleteButtons();
                            }, 100);
                        } else {
                            // If no data returned, reload with filters preserved
                            loadData();
                        }
                        
                        resetProductForm();
                    } catch (error) {
                        showAlert('danger', error.message);
                    }
                });

                // Normalize status value to match select options exactly
                let normalizedStatus = '';
                switch ((product.status || '').toLowerCase()) {
                    case 'active':
                        normalizedStatus = 'active';
                        break;
                    case 'inactive':
                        normalizedStatus = 'inactive';
                        break;
                    case 'dc':
                        normalizedStatus = 'DC';
                        break;
                    case 'upcoming':
                        normalizedStatus = 'upcoming';
                        break;
                    case '2bdc':
                        normalizedStatus = '2BDC';
                        break;
                    default:
                        normalizedStatus = product.status || '';
                        break;
                }

                // Populate form fields (including disabled)
                const fields = {
                    sku: product.SKU || '',
                    parent: product.Parent || '',
                    labelQty: product.label_qty || '1',
                    cp: product.cp || '',
                    ship: product.ship || '',
                    temu_ship: product.temu_ship || '',
                    moq: product.moq || '',
                    ebay2_ship: product.ebay2_ship || '',
                    wtAct: product.wt_act || '',
                    wtDecl: product.wt_decl || '',
                    w: product.w || '',
                    l: product.l || '',
                    h: product.h || '',
                    l2Url: product.l2_url || '',
                    pcbox: product.pcs_per_box || '',
                    b: product.b || '',
                    h1: product.h1 || '',
                    upc: product.upc || '',
                    unit: product.unit || '',
                 
                    status: normalizedStatus,
                    dc: product.dc || '',
                    weight: product.weight || '',
                    msrp: product.msrp || '',
                    map: product.map || ''
                };

                Object.entries(fields).forEach(([id, value]) => {
                    const element = document.getElementById(id);
                    if (element) element.value = value;
                });

                // Show image preview if image_path exists
                const imagePreview = document.getElementById('imagePreview');
                if (product.image_path) {
                    imagePreview.innerHTML =
                        `<img src="${product.image_path}" alt="Product Image" style="max-width:120px;max-height:120px;border-radius:8px;">`;
                } else {
                    imagePreview.innerHTML = '<span class="text-muted">No image</span>';
                }

                // Calculate derived fields
                calculateCBM();
                calculateLP();
                modal.show();
            }

            // Reset the product form
            function resetProductForm() {
                document.getElementById('addProductForm').reset();
                document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
                document.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');
                document.getElementById('form-errors').innerHTML = '';
            }

            // Initialize progress modal
            function setupProgressModal() {
                const progressModal = new bootstrap.Modal(document.getElementById('progressModal'));
                const cancelUploadBtn = document.getElementById('cancelUploadBtn');
                const doneBtn = document.getElementById('doneBtn');
                let uploadInProgress = false;
                let currentUpload = null;

                cancelUploadBtn.addEventListener('click', function() {
                    if (uploadInProgress && currentUpload) {
                        currentUpload.abort();
                    }
                    progressModal.hide();
                });

                doneBtn.addEventListener('click', function() {
                    progressModal.hide();
                });

                window.showUploadProgress = function(sheets) {
                    const progressContainer = document.getElementById('progress-container');
                    const errorContainer = document.getElementById('error-container');

                    progressContainer.innerHTML = '';
                    errorContainer.innerHTML = '';
                    document.getElementById('success-alert').style.display = 'none';
                    doneBtn.style.display = 'none';
                    cancelUploadBtn.disabled = false;
                    uploadInProgress = true;

                    sheets.forEach(sheet => {
                        progressContainer.innerHTML += `
                            <div class="progress-item mb-3" id="${sheet.id}-container">
                                <h6 class="d-flex align-items-center">
                                    <i class="fas fa-file-excel text-primary me-2"></i>
                                    ${sheet.displayName}
                                    <span id="${sheet.id}-icon" class="ms-auto">
                                        <i class="fas fa-circle-notch fa-spin"></i>
                                    </span>
                                </h6>
                                <div class="progress">
                                    <div id="${sheet.id}-progress" class="progress-bar progress-bar-striped progress-bar-animated" 
                                        role="progressbar" style="width: 0%"></div>
                                </div>
                                <div id="${sheet.id}-status" class="small text-muted mt-1">Initializing...</div>
                                <div id="${sheet.id}-error" class="small text-danger mt-1"></div>
                            </div>
                        `;
                    });

                    progressModal.show();
                };

                window.updateUploadProgress = function(sheetId, progress, status, isSuccess, errorMessage) {
                    const progressEl = document.getElementById(`${sheetId}-progress`);
                    const statusEl = document.getElementById(`${sheetId}-status`);
                    const iconEl = document.getElementById(`${sheetId}-icon`);
                    const errorEl = document.getElementById(`${sheetId}-error`);

                    if (progressEl && statusEl && iconEl) {
                        progressEl.style.width = `${progress}%`;

                        if (isSuccess) {
                            progressEl.classList.remove('progress-bar-animated');
                            progressEl.classList.add('bg-success');
                            statusEl.textContent = status || 'Completed successfully';
                            statusEl.classList.add('text-success');
                            iconEl.innerHTML = '<i class="fas fa-check-circle text-success"></i>';
                        } else if (progress === 100) {
                            progressEl.classList.remove('progress-bar-animated');
                            progressEl.classList.add('bg-danger');
                            statusEl.textContent = status || 'Failed';
                            statusEl.classList.add('text-danger');
                            iconEl.innerHTML = '<i class="fas fa-times-circle text-danger"></i>';

                            if (errorMessage) {
                                errorEl.textContent = errorMessage;
                                document.getElementById('error-container').innerHTML += `
                                    <div class="alert alert-danger py-2 mb-2">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong>${sheetId} Error:</strong> ${errorMessage}
                                    </div>
                                `;
                            }
                        } else {
                            statusEl.textContent = status || 'Processing...';
                        }
                    }
                };

                window.completeUpload = function(successCount, totalCount) {
                    uploadInProgress = false;
                    cancelUploadBtn.disabled = true;

                    if (successCount === totalCount) {
                        document.getElementById('success-alert').style.display = 'block';
                        doneBtn.style.display = 'block';
                    } else {
                        document.getElementById('error-container').innerHTML += `
                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-info-circle me-2"></i>
                                ${successCount}/${totalCount} sheets updated successfully
                            </div>
                        `;
                        doneBtn.style.display = 'block';
                    }
                };
            }

            // Setup selection mode functionality (Multi Add removed – checkboxes always visible)
            function setupSelectionMode() {
                const selectAllCheckbox = document.getElementById('selectAll');
                const selectionActions = document.getElementById('selectionActions');
                const selectionCount = selectionActions ? selectionActions.querySelector('.selection-count') : null;
                const cancelButton = document.getElementById('cancelSelection');

                // Cancel selection – clear selections and hide selection bar only
                if (cancelButton && selectionActions) {
                    cancelButton.addEventListener('click', function() {
                        selectedItems = {};
                        selectionActions.style.display = 'none';
                        updateSelectionCount();
                        const currentFilters = getCurrentFilters();
                        let filteredData = applyFiltersToData(currentFilters);
                        renderTable(filteredData);
                    });
                }

                // Handle individual checkbox clicks - use event delegation
                // Use 'click' event instead of 'change' for better compatibility
                document.addEventListener('click', function(e) {
                    if (e.target && e.target.classList.contains('row-checkbox')) {
                        e.stopPropagation(); // Prevent event bubbling
                        const checkbox = e.target;
                        const sku = checkbox.dataset.sku;
                        let id = checkbox.dataset.id;
                        
                        // Toggle checkbox state manually if needed
                        if (checkbox.type === 'checkbox') {
                            // Let the default behavior handle the toggle
                        }

                        // If ID is missing or empty, try to get it from productMap
                        if (!id || id === '' || id === 'undefined' || id === null || id === undefined) {
                            const product = productMap.get(sku);
                            if (product && product.id) {
                                id = product.id;
                                // Update the checkbox data attribute for future reference
                                e.target.dataset.id = product.id;
                            } else {
                                // If still no ID found, log warning and skip this item
                                console.warn('No ID found for SKU:', sku);
                                e.target.checked = false;
                                return;
                            }
                        }

                        // Ensure ID is a valid number
                        const numericId = parseInt(id, 10);
                        if (isNaN(numericId) || numericId <= 0) {
                            console.warn('Invalid ID for SKU:', sku, 'ID:', id);
                            checkbox.checked = false;
                            return;
                        }

                        // Use setTimeout to ensure checkbox state is updated
                        setTimeout(() => {
                            if (checkbox.checked) {
                                selectedItems[sku] = {
                                    id: numericId,
                                    checked: true
                                };
                            } else {
                                delete selectedItems[sku];
                            }
                            updateSelectionCount();
                        }, 0);

                        // Update "select all" checkbox state will be done in setTimeout above
                        // Update "select all" checkbox state
                        setTimeout(() => {
                            const visibleCheckboxes = document.querySelectorAll('.row-checkbox');
                            const allChecked = Array.from(visibleCheckboxes).every(cb => cb.checked);
                            if (selectAllCheckbox) {
                                selectAllCheckbox.checked = allChecked && visibleCheckboxes.length > 0;
                            }
                        }, 0);
                    }
                });
                
                // Also handle change event as backup
                document.addEventListener('change', function(e) {
                    if (e.target && e.target.classList.contains('row-checkbox')) {
                        e.stopPropagation();
                        const checkbox = e.target;
                        const sku = checkbox.dataset.sku;
                        let id = checkbox.dataset.id;

                        // If ID is missing or empty, try to get it from productMap
                        if (!id || id === '' || id === 'undefined' || id === null || id === undefined) {
                            const product = productMap.get(sku);
                            if (product && product.id) {
                                id = product.id;
                                checkbox.dataset.id = product.id;
                            } else {
                                console.warn('No ID found for SKU:', sku);
                                checkbox.checked = false;
                                return;
                            }
                        }

                        const numericId = parseInt(id, 10);
                        if (isNaN(numericId) || numericId <= 0) {
                            console.warn('Invalid ID for SKU:', sku, 'ID:', id);
                            checkbox.checked = false;
                            return;
                        }

                        if (checkbox.checked) {
                            selectedItems[sku] = {
                                id: numericId,
                                checked: true
                            };
                        } else {
                            delete selectedItems[sku];
                        }

                        updateSelectionCount();

                        const visibleCheckboxes = document.querySelectorAll('.row-checkbox');
                        const allChecked = Array.from(visibleCheckboxes).every(cb => cb.checked);
                        if (selectAllCheckbox) {
                            selectAllCheckbox.checked = allChecked && visibleCheckboxes.length > 0;
                        }
                    }
                });
            }

            function updateSelectionCount() {
                const selectionActions = document.getElementById('selectionActions');
                if (!selectionActions) return;
                const count = Object.keys(selectedItems).length;
                selectionActions.style.display = count > 0 ? 'block' : 'none';
                const selectionCount = selectionActions.querySelector('.selection-count');
                if (selectionCount) {
                    selectionCount.textContent = `${count} items selected`;
                }
            }
            function restoreSelectAllState() {
                const selectAllCheckbox = document.getElementById('selectAll');
                if (!selectAllCheckbox) return;

                const visibleCheckboxes = document.querySelectorAll('.row-checkbox');
                if (visibleCheckboxes.length === 0) {
                    selectAllCheckbox.checked = false;
                    return;
                }

                const allChecked = Array.from(visibleCheckboxes).every(cb => cb.checked);
                selectAllCheckbox.checked = allChecked;
            }

            function bindRowCheckboxes() {
                const checkboxes = document.querySelectorAll('.row-checkbox');
                checkboxes.forEach(checkbox => {
                    const sku = checkbox.dataset.sku;
                    const id = checkbox.dataset.id;

                    // Memory se restore state
                    if (selectedItems[sku]) {
                        checkbox.checked = true;
                    }

                    checkbox.addEventListener('change', function () {
                        if (this.checked) {
                            selectedItems[sku] = { id: id, checked: true };
                        } else {
                            delete selectedItems[sku];
                        }
                        updateSelectionCount();
                    });
                });
            }

            function bindSelectAllCheckbox() {
                const selectAllCheckbox = document.getElementById('selectAll');
                if (!selectAllCheckbox) return;

                selectAllCheckbox.addEventListener('change', function () {
                    const checkboxes = document.querySelectorAll('.row-checkbox');

                    if (this.checked) {
                        checkboxes.forEach(checkbox => {
                            const sku = checkbox.dataset.sku;
                            let id = checkbox.dataset.id;
                            
                            // If ID is missing or empty, try to get it from productMap
                            if (!id || id === '' || id === 'undefined' || id === null || id === undefined) {
                                const product = productMap.get(sku);
                                if (product && product.id) {
                                    id = product.id;
                                    // Update the checkbox data attribute for future reference
                                    checkbox.dataset.id = product.id;
                                } else {
                                    // Skip items without valid IDs
                                    console.warn('Skipping SKU without ID:', sku);
                                    return;
                                }
                            }
                            
                            // Ensure ID is a valid number
                            const numericId = parseInt(id, 10);
                            if (isNaN(numericId) || numericId <= 0) {
                                console.warn('Skipping SKU with invalid ID:', sku, 'ID:', id);
                                return;
                            }
                            
                            checkbox.checked = true;
                            selectedItems[sku] = { id: numericId, checked: true };
                        });
                    } else {
                        checkboxes.forEach(checkbox => {
                            checkbox.checked = false;
                            const sku = checkbox.dataset.sku;
                            delete selectedItems[sku];
                        });
                    }

                    updateSelectionCount();
                });
            }


            // Add checkboxes to existing rows when entering selection mode (including parent rows)
            function addCheckboxesToRows() {
                const rows = document.querySelectorAll('#table-body tr');
                rows.forEach(row => {
                    if (!row.querySelector('.row-checkbox')) {
                        const firstCell = row.cells[0];
                        
                        // Find SKU from the row - try multiple cell positions
                        let sku = '';
                        for (let i = 0; i < row.cells.length; i++) {
                            const cell = row.cells[i];
                            const skuSpan = cell.querySelector('.sku-hover');
                            if (skuSpan) {
                                sku = skuSpan.getAttribute('data-sku') || skuSpan.textContent.trim();
                                break;
                            }
                            // Also check if cell text looks like a SKU (include PARENT rows e.g. "PARENT 10 FR")
                            const cellText = cell.textContent.trim();
                            if (cellText && !cellText.match(/^\d+$/) && cellText.length > 0) {
                                const isLikelySku = !cellText.match(/^[\d.,\s%]+$/);
                                if (isLikelySku && cellText.length > 2) {
                                    sku = cellText;
                                    break;
                                }
                            }
                        }
                        
                        if (!sku) {
                            console.warn('Could not find SKU for row');
                            return;
                        }

                        // Find the item in tableData to get the ID
                        const item = productMap.get(sku); // O(1) lookup
                        let id = item ? item.id : null;
                        
                        // Ensure id is a valid number
                        if (id) {
                            const numericId = parseInt(id, 10);
                            id = (!isNaN(numericId) && numericId > 0) ? numericId.toString() : '';
                        } else {
                            id = '';
                        }

                        const checkboxCell = document.createElement('td');
                        checkboxCell.className = 'checkbox-cell';
                        checkboxCell.style.textAlign = 'center';
                        checkboxCell.style.display = 'table-cell';

                        const checkbox = document.createElement('input');
                        checkbox.type = 'checkbox';
                        checkbox.className = 'row-checkbox';
                        checkbox.dataset.sku = sku;
                        checkbox.dataset.id = id;
                        checkbox.style.cursor = 'pointer';
                        checkbox.style.pointerEvents = 'auto';
                        checkbox.disabled = false; // Ensure checkbox is not disabled

                        if (selectedItems[sku]) {
                            checkbox.checked = true;
                        }
                        
                        // Add click handler directly to ensure it works
                        checkbox.addEventListener('click', function(e) {
                            e.stopPropagation();
                        });

                        checkboxCell.appendChild(checkbox);
                        row.insertBefore(checkboxCell, firstCell);
                    }
                });
            }

            // Get current filter values
            function getCurrentFilters() {
                return {
                    parent: normalizeForTextSearch(document.getElementById('parentSearch').value),
                    sku: normalizeForTextSearch(document.getElementById('skuSearch').value),
                    global: ''
                };
            }

            // Apply filters to data
            function applyFiltersToData(filters) {
                let filteredData = [...tableData];

                if (filters.parent) {
                    filteredData = filteredData.filter(item =>
                        normalizeForTextSearch(item.Parent || item.parent || '').includes(filters.parent)
                    );
                }

                if (filters.sku) {
                    filteredData = filteredData.filter(item =>
                        normalizeForTextSearch(item.SKU || item.sku || '').includes(filters.sku)
                    );
                }

                if (filters.global) {
                    filteredData = filteredData.filter(item =>
                        Object.values(item).some(value =>
                            String(value).toLowerCase().includes(filters.global)
                        )
                    );
                }

                let excludeParentRowsForColumnFilter = false;
                document.querySelectorAll('.missing-data-filter').forEach(filter => {
                    if (filter.value === 'missing') {
                        excludeParentRowsForColumnFilter = true;
                    }
                    if (filter.value === '0' && filter.getAttribute('data-column') === 'Inventory') {
                        excludeParentRowsForColumnFilter = true;
                    }
                });

                if (excludeParentRowsForColumnFilter) {
                    filteredData = filteredData.filter(item => {
                        return !(item.SKU && item.SKU.toUpperCase().includes('PARENT'));
                    });
                }

                // Apply column filters (Inventory = 0, missing data, etc.)
                document.querySelectorAll('.missing-data-filter').forEach(filter => {
                    const filterValue = filter.value;
                    const columnName = filter.getAttribute('data-column');
                    if (!columnName || filterValue === 'all') return;

                    if (columnName === 'Inventory' && filterValue === '0') {
                        filteredData = filteredData.filter(item => {
                            const v = item.shopify_inv;
                            if (v === null || v === undefined || v === '') return false;
                            return Number(v) === 0;
                        });
                        return;
                    }

                    if (columnName === 'STATUS' && filterValue !== 'missing') {
                        const fieldName = getFieldNameFromColumn(columnName);
                        filteredData = filteredData.filter(item => {
                            let value = null;
                            if (item[fieldName] !== undefined && item[fieldName] !== null) {
                                value = item[fieldName];
                            } else {
                                let values = {};
                                if (item.Values) {
                                    if (Array.isArray(item.Values)) {
                                        values = item.Values;
                                    } else if (typeof item.Values === 'string') {
                                        try {
                                            values = JSON.parse(item.Values);
                                        } catch (err) {
                                            values = {};
                                        }
                                    } else {
                                        values = item.Values;
                                    }
                                }
                                if (values[fieldName] !== undefined && values[fieldName] !== null) {
                                    value = values[fieldName];
                                }
                            }
                            return value && String(value).toLowerCase() === String(filterValue).toLowerCase();
                        });
                        return;
                    }

                    if (filterValue === 'missing') {
                        if (columnName === 'DIL') {
                            filteredData = filteredData.filter(item => {
                                const invMissing = item.shopify_inv === null || item.shopify_inv === undefined || item.shopify_inv === "";
                                const ovlMissing = item.shopify_quantity === null || item.shopify_quantity === undefined || item.shopify_quantity === "";
                                const inv = Number(item.shopify_inv) || 0;
                                return invMissing || ovlMissing || inv === 0;
                            });
                        } else {
                        const fieldName = getFieldNameFromColumn(columnName);
                        
                        filteredData = filteredData.filter(item => {
                            let value = null;
                            
                            // First, try to get value directly from item object
                            if (item[fieldName] !== undefined && item[fieldName] !== null) {
                                value = item[fieldName];
                            } else {
                                // Get from Values JSON if available
                                let values = {};
                                if (item.Values) {
                                    if (Array.isArray(item.Values)) {
                                        values = item.Values;
                                    } else if (typeof item.Values === 'string') {
                                        try {
                                            values = JSON.parse(item.Values);
                                        } catch (e) {
                                            values = {};
                                        }
                                    } else {
                                        values = item.Values;
                                    }
                                }
                                
                                // Try to get from Values JSON
                                if (values[fieldName] !== undefined && values[fieldName] !== null) {
                                    value = values[fieldName];
                                }
                            }
                            
                            // Determine if numeric based on column
                            const numericColumns = ['LP', 'CP$', 'FRGHT', 'SHIP', 'TEMU SHIP', 'MOQ', 'EBAY2 SHIP', 'Label QTY', 'WT ACT', 'WT DECL', 'Length', 'Width', 'Height', 'CBM', 'UPC', 'Inventory', 'OV L30'];
                            const isNumeric = numericColumns.includes(columnName);
                            
                            // Special handling for dimensions and weights - treat 0 as missing
                            if (isNumeric && ['l', 'w', 'h', 'wt_act', 'wt_decl', 'label_qty', 'moq'].includes(fieldName)) {
                                const num = parseFloat(value);
                                return isDataMissing(value, true) || (num === 0 || num === '0');
                            }
                            
                            return isDataMissing(value, isNumeric);
                        });
                        }
                    }
                });

                return filteredData;
            }



            // Handle the batch processing of selected items
            function setupBatchProcessing() {
                const processSelectedModal = new bootstrap.Modal(document.getElementById('processSelectedModal'));
                const addFieldBtn = document.getElementById('addFieldBtn');
                const fieldOperations = document.getElementById('fieldOperations');
                const applyChangesBtn = document.getElementById('applyChangesBtn');
                const selectedItemCount = document.getElementById('selectedItemCount');
                const batchUpdateResult = document.getElementById('batchUpdateResult');

                // List of all available fields
                const allFields = [{
                        value: 'lp',
                        text: 'LP'
                    },
                    {
                        value: 'cp',
                        text: 'CP'
                    },
                    {
                        value: 'frght',
                        text: 'FRGHT'
                    },
                    {
                        value: 'ship',
                        text: 'SHIP'
                    },
                    {
                        value: 'temu_ship',
                        text: 'TEMU SHIP'
                    },
                    {
                        value: 'moq',
                        text: 'MOQ'
                    },
                    {
                        value: 'ebay2_ship',
                        text: 'EBAY2 SHIP'
                    },
                    {
                    },
                    {
                        value: 'label_qty',
                        text: 'Label QTY'
                    },
                    {
                        value: 'wt_act',
                        text: 'WT ACT'
                    },
                    {
                        value: 'wt_decl',
                        text: 'WT DECL'
                    },
                    {
                        value: 'l',
                        text: 'Length'
                    },
                    {
                        value: 'w',
                        text: 'Width'
                    },
                    {
                        value: 'h',
                        text: 'Height'
                    },
                    {
                        value: 'status',
                        text: 'Status'
                    }
                ];

                // Open modal when Process Selected is clicked
                const processSelectedBtn = document.getElementById('processSelected');
                if (processSelectedBtn) {
                    processSelectedBtn.addEventListener('click', function() {
                        const selectedCount = Object.keys(selectedItems).length;
                        if (selectedCount === 0) {
                            alert('No items selected');
                            return;
                        }

                    // Reset the form
                    resetBatchForm();

                    // Update selected count
                    selectedItemCount.textContent = selectedCount;

                    // Show the modal
                    processSelectedModal.show();
                    });
                }

                // Add a new field operation row
                addFieldBtn.addEventListener('click', function() {
                    addFieldRow();
                    updateFieldOptions();
                });

                // Handle remove field button clicks using event delegation
                fieldOperations.addEventListener('change', function(e) {
                    if (e.target.classList.contains('field-selector')) {
                        updateFieldOptions();

                        // If status is selected, change input to dropdown and hide operation
                        const selectedField = e.target.value;
                        const row = e.target.closest('.field-operation');
                        const valueInput = row.querySelector('.field-value');
                        const operationSelector = row.querySelector('.operation-selector');

                        if (selectedField === 'status') {
                            // Replace text input with status dropdown
                            valueInput.outerHTML = `
                            <select class="form-select field-value">
                                <option value="">Select Status</option>
                                <option value="active">🟢 Active</option>
                                <option value="inactive">🔴 Inactive</option>
                                <option value="DC">🔴 DC</option>
                                <option value="upcoming">🟡 Upcoming</option>
                                <option value="2BDC">🔵 2BDC</option>
                            </select>
                            `;

                            // Hide operation selector for status and set it to "set" (=)
                            operationSelector.style.display = 'none';
                            operationSelector.value = 'set';
                        } else {
                            // For non-status fields, show operation selector
                            operationSelector.style.display = '';

                            // Replace dropdown with text input if it's not status
                            if (valueInput.tagName === 'SELECT' && selectedField !== 'status') {
                                valueInput.outerHTML = `
                                <input type="text" class="form-control field-value" placeholder="Enter value">
                                `;
                            }
                        }
                    }
                });

                // Apply changes to selected items
                applyChangesBtn.addEventListener('click', async function() {
                    // Validate form

                    const operations = getFieldOperations();
                    if (operations.length === 0) {
                        showBatchResult('warning', 'Please select at least one field to update');
                        return;
                    }

                    // Prepare data for update with item IDs
                    // Use the id from selectedItems which was stored when checkbox was checked
                    const itemsWithIssues = [];
                    const items = Object.entries(selectedItems)
                        .map(([sku, item]) => {
                            // Try to get ID from selectedItems first (should already be validated)
                            let id = item.id;
                            
                            // If ID is missing or invalid, try to get it from productMap
                            if (!id || id === '' || id === 'undefined' || id === null || id === undefined) {
                                const product = productMap.get(sku);
                                if (product && product.id) {
                                    id = product.id;
                                } else {
                                    if (!itemsWithIssues.includes(sku)) {
                                        itemsWithIssues.push(sku);
                                    }
                                    return null;
                                }
                            }
                            
                            // If still no ID, try to get it from the checkbox element
                            if (!id || id === '' || id === 'undefined' || id === null || id === undefined) {
                                const checkbox = document.querySelector(`.row-checkbox[data-sku="${sku}"]`);
                                if (checkbox && checkbox.dataset.id && checkbox.dataset.id !== '') {
                                    id = checkbox.dataset.id;
                                } else {
                                    if (!itemsWithIssues.includes(sku)) {
                                        itemsWithIssues.push(sku);
                                    }
                                    return null;
                                }
                            }
                            
                            // Convert to integer if it's a string
                            let numericId = null;
                            if (id !== null && id !== undefined && id !== '') {
                                numericId = parseInt(id, 10);
                                if (isNaN(numericId) || numericId <= 0) {
                                    numericId = null;
                                    if (!itemsWithIssues.includes(sku)) {
                                        itemsWithIssues.push(sku);
                                    }
                                }
                            } else {
                                if (!itemsWithIssues.includes(sku)) {
                                    itemsWithIssues.push(sku);
                                }
                            }
                            
                            // Only return if we have a valid numeric ID
                            if (numericId !== null && !isNaN(numericId) && numericId > 0) {
                                return {
                                    sku: sku,
                                    id: numericId
                                };
                            }
                            return null;
                        })
                        .filter(item => item !== null); // Filter out null items

                    if (items.length === 0) {
                        const errorMsg = itemsWithIssues.length > 0 
                            ? `No valid items selected. The following SKUs are missing IDs: ${itemsWithIssues.slice(0, 5).join(', ')}${itemsWithIssues.length > 5 ? '...' : ''}. Please refresh the page and try again.`
                            : 'No valid items selected. Please ensure all selected items have valid IDs. Try refreshing the page and selecting items again.';
                        showBatchResult('warning', errorMsg);
                        return;
                    }
                    
                    // Log warning if some items were filtered out
                    if (itemsWithIssues.length > 0 && items.length > 0) {
                        console.warn('Some items were filtered out due to missing IDs:', itemsWithIssues);
                    }

                    const updateData = {
                        items: items,
                        operations: operations
                    };

                    // Disable button and show loading
                    applyChangesBtn.disabled = true;
                    applyChangesBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

                    try {
                        // Send to server
                        const response = await makeRequest('/product-master/batch-update', 'POST',
                            updateData);
                        const result = await response.json();

                        if (!response.ok) {
                            throw new Error(result.message || 'Failed to update products');
                        }



                        // Show success message
                        showBatchResult('success',
                            `Successfully updated ${items.length} products`);

                        // Update local data and visible cells without reloading
                        if (result.data && Array.isArray(result.data)) {
                            // Get the operations that were applied
                            const operations = getFieldOperations();
                            
                            // Update tableData and productMap with returned data
                            result.data.forEach(updatedProduct => {
                                const sku = updatedProduct.SKU;
                                if (!sku) return;
                                
                                const existingIndex = tableData.findIndex(p => p.SKU === sku);
                                if (existingIndex !== -1) {
                                    // Merge updated data
                                    Object.assign(tableData[existingIndex], updatedProduct);
                                }
                                
                                // Update productMap
                                const existing = productMap.get(sku);
                                if (existing) {
                                    Object.assign(existing, updatedProduct);
                                } else {
                                    productMap.set(sku, updatedProduct);
                                }
                                
                                // Update visible table cells for each field that was changed
                                operations.forEach(op => {
                                    const fieldName = op.field;
                                    let newValue = updatedProduct[fieldName];
                                    
                                    // If newValue is not directly in updatedProduct, calculate it
                                    if (newValue === undefined) {
                                        const product = productMap.get(sku);
                                        if (product) {
                                            const currentValue = parseFloat(product[fieldName]) || 0;
                                            const opValue = parseFloat(op.value) || 0;
                                            
                                            switch (op.operation) {
                                                case 'set':
                                                    newValue = opValue;
                                                    break;
                                                case 'add':
                                                    newValue = currentValue + opValue;
                                                    break;
                                                case 'subtract':
                                                    newValue = currentValue - opValue;
                                                    break;
                                                case 'multiply':
                                                    newValue = currentValue * opValue;
                                                    break;
                                                case 'divide':
                                                    newValue = opValue !== 0 ? currentValue / opValue : currentValue;
                                                    break;
                                                default:
                                                    newValue = currentValue;
                                            }
                                            
                                            // Update the product data
                                            product[fieldName] = newValue;
                                        }
                                    }
                                    
                                    // Update the visible cell
                                    if (newValue !== undefined) {
                                        updateTableCellInPlace(sku, fieldName, newValue);
                                    }
                                });
                            });
                            
                            // Reapply filters to maintain filter state and show updated data
                            applyFilters();
                        } else {
                            // If server didn't return updated data, calculate and update from operations
                            const operations = getFieldOperations();
                            items.forEach(item => {
                                const sku = item.sku;
                                const product = productMap.get(sku);
                                if (product) {
                                    operations.forEach(op => {
                                        const fieldName = op.field;
                                        const currentValue = parseFloat(product[fieldName]) || 0;
                                        const opValue = parseFloat(op.value) || 0;
                                        let newValue = currentValue;
                                        
                                        switch (op.operation) {
                                            case 'set':
                                                newValue = opValue;
                                                break;
                                            case 'add':
                                                newValue = currentValue + opValue;
                                                break;
                                            case 'subtract':
                                                newValue = currentValue - opValue;
                                                break;
                                            case 'multiply':
                                                newValue = currentValue * opValue;
                                                break;
                                            case 'divide':
                                                newValue = opValue !== 0 ? currentValue / opValue : currentValue;
                                                break;
                                        }
                                        
                                        // Update product data
                                        product[fieldName] = newValue;
                                        
                                        // Update tableData as well
                                        const existingIndex = tableData.findIndex(p => p.SKU === sku || p.sku === sku);
                                        if (existingIndex !== -1) {
                                            tableData[existingIndex][fieldName] = newValue;
                                        }
                                    });
                                }
                            });
                            
                            // Reapply filters to maintain filter state and show updated data
                            applyFilters();
                        }
                        
                        // Clear selections
                        selectedItems = {};
                        updateSelectionCount();
                        
                        // Close modal after a short delay
                        setTimeout(() => {
                            processSelectedModal.hide();
                            showToast('success', `Successfully updated ${items.length} products`);
                        }, 1500);

                    } catch (error) {
                        console.error("Error during batch update:", error);
                        showBatchResult('danger', `Error: ${error.message}`);
                    } finally {
                        applyChangesBtn.disabled = false;
                        applyChangesBtn.innerHTML = 'Apply Changes';
                    }
                });

                // Helper function to add a new field row
                function addFieldRow() {
                    const row = document.createElement('div');
                    row.className = 'field-operation mb-3';
                    row.innerHTML = `
                        <div class="row g-2 align-items-center">
                            <div class="col-3">
                                <select class="form-select field-selector">
                                    <option value="">Select Field</option>
                                    ${generateFieldOptions([])}
                                </select>
                            </div>
                            <div class="col-3">
                                <select class="form-select operation-selector">
                                    <option value="set">=</option>
                                    <option value="add">+</option>
                                    <option value="subtract">-</option>
                                    <option value="multiply">×</option>
                                    <option value="divide">÷</option>
                                </select>
                            </div>
                            <div class="col-4">
                                <input type="text" class="form-control field-value" placeholder="Enter value">
                            </div>
                            <div class="col-2">
                                <button type="button" class="btn btn-outline-danger remove-field">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    `;
                    fieldOperations.appendChild(row);
                }

                // Helper function to reset the batch form
                function resetBatchForm() {
                    fieldOperations.innerHTML = '';
                    addFieldRow();
                    batchUpdateResult.innerHTML = '';
                }

                // Helper function to get field operations from the form
                function getFieldOperations() {
                    const operations = [];
                    const rows = fieldOperations.querySelectorAll('.field-operation');

                    rows.forEach(row => {
                        const field = row.querySelector('.field-selector').value;
                        const operation = row.querySelector('.operation-selector').value;
                        const value = row.querySelector('.field-value').value;

                        if (field && value) {
                            operations.push({
                                field: field,
                                operation: operation,
                                value: value
                            });
                        }
                    });

                    return operations;
                }

                // Helper function to show result messages
                function showBatchResult(type, message) {
                    batchUpdateResult.innerHTML = `
                        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                            ${message}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    `;
                }

                // Helper function to generate field options HTML, excluding already selected fields
                function generateFieldOptions(excludeFields) {
                    return allFields
                        .filter(field => !excludeFields.includes(field.value))
                        .map(field => `<option value="${field.value}">${field.text}</option>`)
                        .join('');
                }

                // Helper function to update field options in all selectors
                function updateFieldOptions() {
                    const rows = fieldOperations.querySelectorAll('.field-operation');
                    const selectedFields = getSelectedFields();

                    rows.forEach(row => {
                        const selector = row.querySelector('.field-selector');
                        const currentValue = selector.value;

                        // Get fields to exclude (all selected fields except current one)
                        const fieldsToExclude = selectedFields.filter(field => field !== currentValue);

                        // Store current selection
                        const currentSelection = selector.value;

                        // Update options, excluding already selected fields
                        selector.innerHTML = `
                            <option value="">Select Field</option>
                            ${generateFieldOptions(fieldsToExclude)}
                        `;

                        // Restore current selection
                        selector.value = currentSelection;
                    });

                    // Disable/enable add field button based on available options
                    const unusedFields = allFields.filter(field => !selectedFields.includes(field.value));
                    addFieldBtn.disabled = unusedFields.length === 0;
                }

                // Helper function to get currently selected fields
                function getSelectedFields() {
                    const selectedFields = [];
                    const selectors = fieldOperations.querySelectorAll('.field-selector');

                    selectors.forEach(selector => {
                        if (selector.value) {
                            selectedFields.push(selector.value);
                        }
                    });

                    return selectedFields;
                }
            }

            // Bulk Actions Modal (Parent, CP, Unit, MOQ) - same pattern as Tasks page
            function setupBulkActionsModal() {
                const bulkActionsBtn = document.getElementById('bulkActionsBtn');
                const bulkActionsModal = document.getElementById('bulkActionsModal');
                const bulkSelectedCount = document.getElementById('bulkSelectedCount');
                const bulkUpdateFormModal = document.getElementById('bulkUpdateFormModal');
                const bulkUpdateFormModalTitle = document.getElementById('bulkUpdateFormModalTitle');
                const bulkUpdateFormModalBody = document.getElementById('bulkUpdateFormModalBody');
                const confirmBulkUpdateFormBtn = document.getElementById('confirmBulkUpdateFormBtn');

                if (!bulkActionsBtn || !bulkActionsModal) return;

                function getSelectedItemsForBulk() {
                    const itemsWithIssues = [];
                    const items = Object.entries(selectedItems)
                        .map(([sku, item]) => {
                            let id = item.id;
                            if (!id || id === '' || id === 'undefined' || id === null || id === undefined) {
                                const product = productMap.get(sku);
                                if (product && product.id) id = product.id;
                                else {
                                    if (!itemsWithIssues.includes(sku)) itemsWithIssues.push(sku);
                                    return null;
                                }
                            }
                            if (!id || id === '' || id === 'undefined' || id === null || id === undefined) {
                                const checkbox = document.querySelector(`.row-checkbox[data-sku="${sku}"]`);
                                if (checkbox && checkbox.dataset.id && checkbox.dataset.id !== '') id = checkbox.dataset.id;
                                else {
                                    if (!itemsWithIssues.includes(sku)) itemsWithIssues.push(sku);
                                    return null;
                                }
                            }
                            let numericId = parseInt(id, 10);
                            if (isNaN(numericId) || numericId <= 0) {
                                if (!itemsWithIssues.includes(sku)) itemsWithIssues.push(sku);
                                return null;
                            }
                            return { sku: sku, id: numericId };
                        })
                        .filter(item => item !== null);
                    return { items, itemsWithIssues };
                }

                function showBulkForm(title, bodyHtml, getValue) {
                    bulkUpdateFormModalTitle.textContent = title;
                    bulkUpdateFormModalBody.innerHTML = bodyHtml;
                    const modal = bootstrap.Modal.getOrCreateInstance(bulkUpdateFormModal);
                    modal.show();

                    const once = function() {
                        const value = getValue();
                        if (value === null) return;
                        confirmBulkUpdateFormBtn.removeEventListener('click', once);
                        const { items, itemsWithIssues } = getSelectedItemsForBulk();
                        if (items.length === 0) {
                            alert('No valid products selected. Please refresh and try again.');
                            return;
                        }
                        confirmBulkUpdateFormBtn.disabled = true;
                        confirmBulkUpdateFormBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Updating...';
                        makeRequest('/product-master/batch-update', 'POST', {
                            items: items,
                            operations: [{ field: window._bulkActionField, operation: 'set', value: value }]
                        })
                            .then(res => res.json())
                            .then(function(result) {
                                bootstrap.Modal.getInstance(bulkUpdateFormModal).hide();
                                if (result.success) {
                                    if (typeof showToast === 'function') showToast('success', result.message || 'Products updated.');
                                    else alert(result.message || 'Products updated.');
                                    if (typeof loadData === 'function') loadData();
                                } else {
                                    alert(result.message || 'Update failed.');
                                }
                            })
                            .catch(function(err) {
                                console.error(err);
                                alert('Update failed. Please try again.');
                            })
                            .finally(function() {
                                confirmBulkUpdateFormBtn.disabled = false;
                                confirmBulkUpdateFormBtn.innerHTML = '<i class="fas fa-check me-1"></i>Update';
                            });
                    };
                    confirmBulkUpdateFormBtn.replaceWith(confirmBulkUpdateFormBtn.cloneNode(true));
                    document.getElementById('confirmBulkUpdateFormBtn').addEventListener('click', once);
                }

                bulkActionsBtn.addEventListener('click', function() {
                    const count = Object.keys(selectedItems).length;
                    if (count === 0) {
                        alert('Please select one or more products first (use the checkboxes on the left).');
                        return;
                    }
                    if (bulkSelectedCount) bulkSelectedCount.textContent = count;
                    bootstrap.Modal.getOrCreateInstance(bulkActionsModal).show();
                });

                document.getElementById('bulk-change-parent-btn').addEventListener('click', function(e) {
                    e.preventDefault();
                    bootstrap.Modal.getInstance(bulkActionsModal).hide();
                    window._bulkActionField = 'parent';
                    const n = Object.keys(selectedItems).length;
                    showBulkForm('Change Parent', `
                        <p class="mb-3"><strong>Set parent for ${n} product(s):</strong></p>
                        <div class="mb-3">
                            <label for="bulk-parent-input" class="form-label">Parent:</label>
                            <input type="text" class="form-control" id="bulk-parent-input" placeholder="e.g. 10 FR">
                        </div>
                    `, function() {
                        const v = document.getElementById('bulk-parent-input').value.trim();
                        if (v === '') { alert('Please enter a parent value.'); return null; }
                        return v;
                    });
                });

                document.getElementById('bulk-change-cp-btn').addEventListener('click', function(e) {
                    e.preventDefault();
                    bootstrap.Modal.getInstance(bulkActionsModal).hide();
                    window._bulkActionField = 'cp';
                    const n = Object.keys(selectedItems).length;
                    showBulkForm('Change CP (Cost)', `
                        <p class="mb-3"><strong>Set cost for ${n} product(s):</strong></p>
                        <div class="mb-3">
                            <label for="bulk-cp-input" class="form-label">CP (Cost):</label>
                            <input type="text" class="form-control" id="bulk-cp-input" placeholder="e.g. 5.99">
                        </div>
                    `, function() {
                        const v = document.getElementById('bulk-cp-input').value.trim();
                        if (v === '') { alert('Please enter a cost value.'); return null; }
                        return v;
                    });
                });

                document.getElementById('bulk-change-unit-btn').addEventListener('click', function(e) {
                    e.preventDefault();
                    bootstrap.Modal.getInstance(bulkActionsModal).hide();
                    window._bulkActionField = 'unit';
                    const n = Object.keys(selectedItems).length;
                    showBulkForm('Change Unit', `
                        <p class="mb-3"><strong>Set unit for ${n} product(s):</strong></p>
                        <div class="mb-3">
                            <label for="bulk-unit-input" class="form-label">Unit:</label>
                            <input type="text" class="form-control" id="bulk-unit-input" placeholder="e.g. Pieces, Pair">
                        </div>
                    `, function() {
                        const v = document.getElementById('bulk-unit-input').value.trim();
                        if (v === '') { alert('Please enter a unit value.'); return null; }
                        return v;
                    });
                });

                document.getElementById('bulk-change-moq-btn').addEventListener('click', function(e) {
                    e.preventDefault();
                    bootstrap.Modal.getInstance(bulkActionsModal).hide();
                    window._bulkActionField = 'moq';
                    const n = Object.keys(selectedItems).length;
                    showBulkForm('Change MOQ', `
                        <p class="mb-3"><strong>Set MOQ for ${n} product(s):</strong></p>
                        <div class="mb-3">
                            <label for="bulk-moq-input" class="form-label">MOQ (Minimum Order Qty):</label>
                            <input type="text" class="form-control" id="bulk-moq-input" placeholder="e.g. 100">
                        </div>
                    `, function() {
                        const v = document.getElementById('bulk-moq-input').value.trim();
                        if (v === '') { alert('Please enter an MOQ value.'); return null; }
                        return v;
                    });
                });
            }

            // Initialize import from API functionality
            const importFromApiBtn = document.getElementById('importFromApiBtn');
            if (importFromApiBtn) {
                importFromApiBtn.addEventListener('click', function() {
                    const importBtn = this;
                    importBtn.disabled = true;
                    importBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Importing...';

                makeRequest('/product-master-data-view', 'GET')
                    .then(res => {
                        if (!res.ok) {
                            throw new Error(`HTTP error! status: ${res.status}`);
                        }
                        return res.json();
                    })
                    .then(apiResponse => {
                        if (apiResponse && apiResponse.data) {
                            return makeRequest('/product-master/import-from-sheet', 'POST', {
                                data: apiResponse.data
                            });
                        }
                        throw new Error('Failed to fetch API data.');
                    })
                    .then(res => {
                        if (!res.ok) {
                            throw new Error(`HTTP error! status: ${res.status}`);
                        }
                        return res.json();
                    })
                    .then(result => {
                        alert(
                            `Import complete!\nImported: ${result.imported ?? 0}\nErrors: ${result.errors?.length ? result.errors.join('\n') : 'None'}`
                        );
                        loadData(); // Refresh the table after import
                    })
                    .catch(err => {
                        console.error('Import failed:', err);
                        alert('Import failed: ' + err.message);
                    })
                    .finally(() => {
                        importBtn.disabled = false;
                        importBtn.innerHTML =
                            '<i class="fas fa-cloud-download-alt me-1"></i> Import from API Sheet';
                    });
                });
            }

            // Utility functions
            /** DIL % text color — same rules as Forecast Analysis `getDilTextColor` (ov_l30 / inv as decimal). */
            function getDilTextColor(ratio) {
                const percent = parseFloat(ratio) * 100;
                if (isNaN(percent)) return '#6c757d';
                if (percent < 16.66) return '#b71c1c';
                if (percent < 50) return '#1b5e20';
                return '#ad1457';
            }

            function escapeHtml(str) {
                if (!str) return '';
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            // Helper function to check if data is missing
            function isDataMissing(value, isNumeric = false) {
                if (value === null || value === undefined) return true;
                
                const strValue = String(value).trim();
                
                // Empty string or dash means missing
                if (strValue === '' || strValue === '-' || strValue === 'null' || strValue === 'undefined') {
                    return true;
                }
                
                // For numeric fields
                if (isNumeric) {
                    const numValue = parseFloat(strValue);
                    // If it's NaN, it's missing
                    if (isNaN(numValue)) return true;
                    // For numeric fields, 0 or negative might be valid depending on context
                    // But we'll consider 0 as potentially missing for dimensions (L, W, H, WT ACT, WT DECL)
                    // For now, treat 0 as valid (not missing) for most numeric fields
                    return false; // If it's a valid number, it's not missing
                }
                
                // For non-numeric fields, empty string already handled above
                return false;
            }

            // Helper function to get field name from column name
            function getFieldNameFromColumn(columnName) {
                const fieldMap = {
                    "Status": "status",
                    "STATUS": "status",
                    "Images": "image_path",
                    "Image": "image_path",
                    "Parent": "Parent",
                    "SKU": "SKU",
                    "UPC": "upc",
                    "Inventory": "shopify_inv",
                    "OV L30": "shopify_quantity",
                    "Unit": "unit",
                    "LP": "lp",
                    "CP$": "cp",
                    "FRGHT": "frght",
                    "SHIP": "ship",
                    "TEMU SHIP": "temu_ship",
                    "MOQ": "moq",
                    "EBAY2 SHIP": "ebay2_ship",
                    "Label QTY": "label_qty",
                    "WT ACT": "wt_act",
                    "WT DECL": "wt_decl",
                    "Length": "l",
                    "Width": "w",
                    "Height": "h",
                    "CBM": "cbm",
                    "Url": "l2_url"
                };
                return fieldMap[columnName] || columnName.toLowerCase().replace(/\s+/g, '_');
            }
            
            // Helper function to get column name from field name
            function getColumnNameFromField(fieldName) {
                const columnMap = {
                    "status": "Status",
                    "image_path": "Image",
                    "Parent": "Parent",
                    "SKU": "SKU",
                    "upc": "UPC",
                    "shopify_inv": "Inventory",
                    "shopify_quantity": "OV L30",
                    "unit": "Unit",
                    "lp": "LP",
                    "cp": "CP$",
                    "frght": "FRGHT",
                    "ship": "SHIP",
                    "temu_ship": "TEMU SHIP",
                    "moq": "MOQ",
                    "ebay2_ship": "EBAY2 SHIP",
                    "label_qty": "Label QTY",
                    "wt_act": "WT ACT",
                    "wt_decl": "WT DECL",
                    "l": "L",
                    "w": "W",
                    "h": "H",
                    "cbm": "CBM",
                    "l2_url": "Url"
                };
                return columnMap[fieldName] || fieldName;
            }
            
            // Helper function to update a table cell in place
            function updateTableCellInPlace(sku, fieldName, newValue) {
                // Find the row by SKU
                const rows = document.querySelectorAll('#table-body tr');
                let targetRow = null;
                
                for (let row of rows) {
                    const skuCell = row.querySelector('.sku-hover');
                    if (skuCell) {
                        const rowSku = skuCell.getAttribute('data-sku') || skuCell.textContent.trim();
                        if (rowSku === sku) {
                            targetRow = row;
                            break;
                        }
                    }
                }
                
                if (!targetRow) return;
                
                // Get column name from field name
                const columnName = getColumnNameFromField(fieldName);
                
                // Get all header cells to find column index
                const headerRow = document.querySelector('#row-callback-datatable thead tr');
                if (!headerRow) return;
                
                const headerCells = Array.from(headerRow.querySelectorAll('th'));
                let columnIndex = -1;
                
                // Find the column index by matching header text
                headerCells.forEach((th, index) => {
                    const thText = th.textContent.trim();
                    // Remove any extra text like "(0)" from parent count
                    const cleanText = thText.replace(/\s*\(\d+\)\s*$/, '').trim();
                    
                    // Check if this is the column we're looking for
                    if (cleanText === columnName || 
                        thText === columnName ||
                        (columnName === "Status" && (cleanText === "Status" || cleanText === "STATUS")) ||
                        (columnName === "CP$" && (cleanText.includes("CP") || thText.includes("CP"))) ||
                        (columnName === "FRGHT" && (cleanText.includes("FRGHT") || thText.includes("FRGHT"))) ||
                        (columnName === "Label QTY" && (cleanText.includes("Label") || thText.includes("Label"))) ||
                        (columnName === "Image" && (cleanText.includes("Image") || thText.includes("Image"))) ||
                        (columnName === "Images" && (cleanText.includes("Image") || thText.includes("Image")))) {
                        columnIndex = index;
                    }
                });
                
                if (columnIndex === -1) {
                    console.warn(`Column not found for field: ${fieldName}, column: ${columnName}`);
                    return;
                }
                
                // Get the cell at the column index (cells array already includes checkbox column if present)
                const cells = targetRow.querySelectorAll('td');
                const cell = cells[columnIndex];
                
                if (!cell) return;
                
                // Update cell content based on field type
                const isNumeric = ['lp', 'cp', 'frght', 'wt_act', 'wt_decl', 'l', 'w', 'h', 'cbm', 'upc', 'label_qty', 'moq'].includes(fieldName);
                
                if (fieldName === 'image_path') {
                    cell.innerHTML = newValue ? `<span class="image-hover" data-image="${newValue}"><img src="${newValue}" style="width:40px;height:40px;object-fit:cover;border-radius:4px;"></span>` : '<span class="missing-data-indicator" title="Missing Data">M</span>';
                } else if (fieldName === 'l2_url') {
                    cell.className = 'text-center';
                    cell.innerHTML = newValue ? `<a href="${escapeHtml(newValue)}" target="_blank"><i class="fas fa-external-link-alt"></i></a>` : createMissingDataButton(sku, 'l2_url', 'Url');
                } else if (isNumeric) {
                    cell.className = 'text-center';
                    const numValue = parseFloat(newValue);
                    if (isNaN(numValue) || numValue === 0) {
                        cell.innerHTML = createMissingDataButton(sku, fieldName, columnName);
                    } else {
                        const decimals = fieldName === 'cbm' ? 4 : (['lp', 'cp', 'frght', 'wt_act', 'wt_decl', 'l', 'w', 'h'].includes(fieldName)) ? 2 : 0;
                        cell.textContent = numValue.toFixed(decimals);
                    }
                } else {
                    if (!newValue || newValue === '' || newValue === '-') {
                        cell.innerHTML = addMissingIndicator('', true, sku, fieldName, columnName);
                    } else {
                        cell.innerHTML = addMissingIndicator(escapeHtml(newValue), false, sku, fieldName, columnName);
                    }
                }
                
                // If dimensions were updated, recalculate CBM and FRGHT
                if (['l', 'w', 'h'].includes(fieldName)) {
                    const product = productMap.get(sku);
                    if (product) {
                        const l = parseFloat(product.l) || 0;
                        const w = parseFloat(product.w) || 0;
                        const h = parseFloat(product.h) || 0;
                        if (l > 0 && w > 0 && h > 0) {
                            const cbm = (((l * 2.54) * (w * 2.54) * (h * 2.54)) / 1000000);
                            product.cbm = parseFloat(cbm.toFixed(4));
                            product.frght = parseFloat((cbm * 200).toFixed(2));
                            
                            // Update CBM and FRGHT cells
                            updateTableCellInPlace(sku, 'cbm', product.cbm);
                            updateTableCellInPlace(sku, 'frght', product.frght);
                        }
                    }
                }
            }

            // Helper function to create missing data button
            function createMissingDataButton(sku, field, fieldLabel) {
                return `<button type="button" class="missing-data-indicator" 
                    title="Click to enter missing data" 
                    data-sku="${escapeHtml(sku)}" 
                    data-field="${escapeHtml(field)}" 
                    data-field-label="${escapeHtml(fieldLabel)}">
                    M
                </button>`;
            }

            function getStatusDot(status) {
                const inner = getStatusCellDisplayHtml(status);
                if (inner) return inner;
                const title = String(status || '').trim() || '-';
                return `<span class="pm-status-marble pm-status-marble--muted" title="${escapeHtml(title)}"></span>`;
            }

            // Helper function to add missing data indicator
            function addMissingIndicator(content, isMissing, sku = '', field = '', fieldLabel = '') {
                if (isMissing) {
                    return createMissingDataButton(sku, field, fieldLabel);
                }
                return content;
            }

            function formatNumber(num, decimals) {
                if (num === undefined || num === null) return '-';
                const n = parseFloat(num);
                return isNaN(n) ? '-' : n.toFixed(decimals);
            }

            function debounce(func, wait) {
                let timeout;
                return function() {
                    const context = this,
                        args = arguments;
                    clearTimeout(timeout);
                    timeout = setTimeout(() => func.apply(context, args), wait);
                };
            }

            function showError(message) {
                document.getElementById('rainbow-loader').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        ${escapeHtml(message)}
                    </div>
                `;
            }

            function showAlert(type, message) {
                const alert = document.createElement('div');
                alert.className = `alert alert-${type} alert-dismissible fade show`;
                alert.innerHTML = `
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;

                const container = document.getElementById('form-errors');
                container.innerHTML = '';
                container.appendChild(alert);
            }

            function showFieldError(field, message) {
                const formGroup = field.closest('.form-group');
                if (!formGroup) return;

                let errorElement = formGroup.querySelector('.invalid-feedback');
                if (!errorElement) {
                    errorElement = document.createElement('div');
                    errorElement.className = 'invalid-feedback';
                    formGroup.appendChild(errorElement);
                }

                field.classList.add('is-invalid');
                errorElement.textContent = message;
            }

            function clearFieldError(field) {
                const formGroup = field.closest('.form-group');
                if (!formGroup) return;

                const errorElement = formGroup.querySelector('.invalid-feedback');
                if (errorElement) {
                    field.classList.remove('is-invalid');
                    errorElement.textContent = '';
                }
            }

            function setupDeleteButtons() {
                document.querySelectorAll('.delete-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        const sku = this.getAttribute('data-sku');
                        // Use Bootstrap modal for confirmation instead of window.confirm
                        const confirmModal = document.createElement('div');
                        confirmModal.innerHTML = `
                            <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content border-0" style="border-radius: 18px; overflow: hidden;">
                                <div class="modal-header" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: #fff;">
                                    <div class="d-flex align-items-center w-100">
                                    <div class="me-3" style="font-size: 2.5rem;">
                                        <i class="fas fa-exclamation-triangle fa-shake"></i>
                                    </div>
                                    <div>
                                        <h5 class="modal-title mb-0" id="deleteConfirmModalLabel" style="font-weight: 800; letter-spacing: 1px;">
                                        Archive Product?
                                        </h5>
                                    </div>
                                    <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                </div>
                                <div class="modal-body text-center py-4">
                                    <div class="mb-3" style="font-size: 1.2rem;">
                                    Are you sure you want to <span class="fw-bold text-danger">Archive</span> product<br>
                                    <span class="badge bg-danger fs-6 px-3 py-2 mt-2" style="font-size:1.1rem;">SKU: ${escapeHtml(sku)}</span>?
                                    </div>
                                    
                                </div>
                                <div class="modal-footer justify-content-center" style="background: #fff;">
                                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-1"></i>Cancel
                                    </button>
                                    <button type="button" class="btn btn-danger px-4" id="confirmDeleteBtn">
                                    <i class="fas fa-archive me-1"></i> Archive
                                    </button>
                                </div>
                                </div>
                            </div>
                            </div>
                        `;
                        document.body.appendChild(confirmModal);

                        const modal = new bootstrap.Modal(confirmModal.querySelector(
                            '#deleteConfirmModal'));
                        modal.show();

                        confirmModal.querySelector('#confirmDeleteBtn').addEventListener('click',
                            () => {
                                makeRequest('/product_master/delete', 'DELETE', {
                                        ids: [id]
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            showToast('success', data.message ||
                                                'Product archived successfully!');
                                            // loadData() will preserve filters automatically
                                            loadData();
                                        } else {
                                            showToast('danger', data.message ||
                                                'Archive failed');
                                        }
                                    })
                                    .catch(() => {
                                        showToast('danger', 'Archive failed');
                                    })
                                    .finally(() => {
                                        modal.hide();
                                        setTimeout(() => confirmModal.remove(), 500);
                                    });
                            });

                        // Remove modal from DOM after hiding
                        confirmModal.querySelectorAll('[data-bs-dismiss="modal"]').forEach(btn => {
                            btn.addEventListener('click', () => setTimeout(() =>
                                confirmModal.remove(), 500));
                        });
                    });
                });
            }

            // Setup verified data dropdowns
            function setupVerifiedDataCheckboxes() {
                // Use event delegation for dynamically created dropdowns
                document.addEventListener('change', function(e) {
                    if (e.target && e.target.classList.contains('verified-data-dropdown')) {
                        const dropdown = e.target;
                        const sku = dropdown.getAttribute('data-sku');
                        const isVerified = parseInt(dropdown.value) === 1;
                        const verifiedValue = isVerified ? 1 : 0;

                        // Update class immediately for visual feedback
                        if (isVerified) {
                            dropdown.classList.remove('not-verified');
                            dropdown.classList.add('verified');
                        } else {
                            dropdown.classList.remove('verified');
                            dropdown.classList.add('not-verified');
                        }

                        // Disable dropdown while saving
                        dropdown.disabled = true;

                        // Send update request
                        makeRequest('/product_master/update-verified', 'POST', {
                            sku: sku,
                            verified_data: verifiedValue
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Update local data
                                const product = productMap.get(sku);
                                if (product) {
                                    if (product.Values && typeof product.Values === 'object') {
                                        product.Values.verified_data = verifiedValue;
                                    } else {
                                        if (!product.Values) product.Values = {};
                                        product.Values.verified_data = verifiedValue;
                                    }
                                    product.verified_data = verifiedValue;
                                }
                                
                                // Update tableData
                                const tableItem = tableData.find(item => item.SKU === sku);
                                if (tableItem) {
                                    if (tableItem.Values && typeof tableItem.Values === 'object') {
                                        tableItem.Values.verified_data = verifiedValue;
                                    } else {
                                        if (!tableItem.Values) tableItem.Values = {};
                                        tableItem.Values.verified_data = verifiedValue;
                                    }
                                    tableItem.verified_data = verifiedValue;
                                }
                            } else {
                                // Revert dropdown state on error
                                dropdown.value = isVerified ? '0' : '1';
                                if (isVerified) {
                                    dropdown.classList.remove('verified');
                                    dropdown.classList.add('not-verified');
                                } else {
                                    dropdown.classList.remove('not-verified');
                                    dropdown.classList.add('verified');
                                }
                                showToast('danger', data.message || 'Failed to update verified status');
                            }
                        })
                        .catch(error => {
                            // Revert dropdown state on error
                            dropdown.value = isVerified ? '0' : '1';
                            if (isVerified) {
                                dropdown.classList.remove('verified');
                                dropdown.classList.add('not-verified');
                            } else {
                                dropdown.classList.remove('not-verified');
                                dropdown.classList.add('verified');
                            }
                            showToast('danger', 'Failed to update verified status');
                            console.error('Error updating verified data:', error);
                        })
                        .finally(() => {
                            dropdown.disabled = false;
                        });
                    }
                });
            }

            function showToast(type, message) {
                // Remove any existing toast
                document.querySelectorAll('.custom-toast').forEach(t => t.remove());

                const toast = document.createElement('div');
                toast.className =
                    `custom-toast toast align-items-center text-bg-${type} border-0 show position-fixed bottom-0 start-50 translate-middle-x mb-4`;
                toast.style.zIndex = 2000;
                toast.setAttribute('role', 'alert');
                toast.setAttribute('aria-live', 'assertive');
                toast.setAttribute('aria-atomic', 'true');
                toast.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body" style="font-size: 15px; padding: 12px 15px;">${escapeHtml(message)}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                `;
                
                // Make toast wider to accommodate longer messages and more noticeable
                toast.style.minWidth = '350px';
                toast.style.maxWidth = '450px';
                toast.style.boxShadow = '0 5px 15px rgba(0,0,0,0.3)';
                toast.style.borderRadius = '8px';
                document.body.appendChild(toast);

                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 500);
                }, 5000);

                toast.querySelector('[data-bs-dismiss="toast"]').onclick = () => {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 500);
                };
            }

            const tooltipEl = document.getElementById('skuImageTooltip');
            let tooltipRAF = null;
            function getHoverImageTarget(eventTarget) {
                return eventTarget.closest('.sku-hover, .image-hover');
            }
            document.addEventListener('mouseover', function(e) {
                const target = getHoverImageTarget(e.target);
                if (target && tooltipEl) {
                    const image = target.getAttribute('data-image');
                    if (image) {
                        tooltipEl.innerHTML = `<img src="${image}" alt="Product Image">`;
                        tooltipEl.style.display = 'block';
                    } else {
                        tooltipEl.style.display = 'none';
                    }
                }
            });
            document.addEventListener('mousemove', function(e) {
                if (!tooltipEl || tooltipEl.style.display !== 'block') return;
                if (tooltipRAF) cancelAnimationFrame(tooltipRAF);
                tooltipRAF = requestAnimationFrame(function() {
                    tooltipEl.style.left = (e.pageX + 20) + 'px';
                    tooltipEl.style.top = (e.pageY + 10) + 'px';
                    tooltipRAF = null;
                });
            });
            document.addEventListener('mouseout', function(e) {
                const hoverTarget = getHoverImageTarget(e.target);
                const relatedHoverTarget = e.relatedTarget ? getHoverImageTarget(e.relatedTarget) : null;
                if (hoverTarget && hoverTarget !== relatedHoverTarget && tooltipEl) {
                    tooltipEl.style.display = 'none';
                }
            });
        });
    </script>
@endsection
