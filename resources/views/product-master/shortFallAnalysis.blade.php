@extends('layouts.vertical', ['title' => 'ShortFall Analysis', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- Your existing CSS styles here -->
     
    <style>
        /* General Styles */
        .w-25 {
            width: 10% !important;
            margin-left: 20px;
        }

        .tooltip-icon {
            position: absolute;
            right: 5px;
            bottom: 5px;
            cursor: pointer;
        }

        td {
            position: relative;
        }

        /* Custom Modal Positioning */
        .modal.right-to-left .modal-dialog {
            position: fixed;
            margin: 0;
            width: 90%;
            max-width: none;
            height: auto;
            right: 0;
            top: 0;
            transform: translateX(100%);
            transition: transform 0.3s ease-out;
        }

        .modal.right-to-left.show .modal-dialog {
            transform: translateX(0);
        }

        .modal.right-to-left .modal-content {
            height: 100%;
            border-radius: 0;
            border: none;
        }

        .modal.right-to-left .modal-header,
        .modal.right-to-left .modal-body,
        .modal.right-to-left .modal-footer {
            padding: 20px;
        }

        .modal.right-to-left .modal-header {
            border-bottom: 1px solid #dee2e6;
        }

        .modal.right-to-left .modal-footer {
            border-top: 1px solid #dee2e6;
        }

        /* Scoped Styles for Right-to-Left Modal */
        .modal.right-to-left .modal-content {
            border: 2px solid transparent;
            border-radius: 15px;
            background: linear-gradient(white, white), linear-gradient(135deg, #0d6efd, #0dcaf0);
            background-origin: border-box;
            background-clip: content-box, border-box;
            backdrop-filter: blur(20px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .modal.right-to-left .modal-header {
            border-bottom: 2px solid #0d6efd;
        }

        .modal.right-to-left .card {
            /* Base card styles with gradient */
            background: linear-gradient(135deg, rgba(13, 110, 253, 0.1), rgba(255, 255, 255, 0.9));
            border: 1px solid rgba(13, 110, 253, 0.2);
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        /* Status color overlays - will combine with the gradient */
        .modal.right-to-left .card.card-bg-red {
            background: linear-gradient(135deg, rgba(245, 0, 20, 0.69), rgba(255, 255, 255, 0.85));
            border-color: rgba(220, 53, 70, 0.72);
        }

        .modal.right-to-left .card.card-bg-green {
            background: linear-gradient(135deg, rgba(3, 255, 62, 0.424), rgba(255, 255, 255, 0.85));
            border-color: rgba(40, 167, 69, 0.3);
        }

        .modal.right-to-left .card.card-bg-yellow {
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.15), rgba(255, 255, 255, 0.85));
            border-color: rgba(255, 193, 7, 0.3);
        }

        .modal.right-to-left .card.card-bg-blue {
            background: linear-gradient(135deg, rgba(0, 123, 255, 0.15), rgba(255, 255, 255, 0.85));
            border-color: rgba(0, 123, 255, 0.3);
        }

        .modal.right-to-left .card.card-bg-pink {
            background: linear-gradient(135deg, rgba(232, 62, 140, 0.15), rgba(255, 255, 255, 0.85));
            border-color: rgba(232, 62, 141, 0.424);
        }

        .modal.right-to-left .card.card-bg-gray {
            background: linear-gradient(135deg, rgba(108, 117, 125, 0.15), rgba(255, 255, 255, 0.85));
            border-color: rgba(108, 117, 125, 0.3);
        }

        .modal.right-to-left .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }

        .modal.right-to-left .modal-title {
            font-weight: bold;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .modal.right-to-left .modal-dialog {
            animation: slideInRight 0.3s ease-out;
        }

        .fa-pen {
            font-size: 16px;
            transition: color 0.3s ease;
        }

        .fa-pen:hover {
            color: #0d6efd;
        }

        /* Status indicators */
        .status-indicator {
            position: absolute;
            top: 5px;
            left: 0;
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .danger {
            background-color: #dc3545;
        }

        .warning {
            background-color: #ffc107;
        }

        .success {
            background-color: #28a745;
        }

        .info {
            background-color: #007bff;
        }

        .pink {
            background-color: #e83e8c;
        }

        /* Parent row highlight */
        .parent-row {
            background-color: rgba(69, 233, 255, 0.1) !important;
        }

        /* Loading overlay */
        #loader {
            display: none;
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1000;
            padding: 10px 20px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            border-radius: 5px;
        }

        /* Add these styles to your existing CSS */
        .edit-icon,
        .save-icon {
            transition: all 0.3s ease;
        }

        .edit-icon:hover i {
            color: #0d6efd !important;
            transform: scale(1.1);
        }

        .save-icon:hover i {
            color: #28a745 !important;
            transform: scale(1.1);
        }

        .fa-spinner {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Notification styling */
        .alert-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 250px;
        }

        /* Add this to your existing CSS section */
        .invalid-feedback {
            color: #dc3545;
            font-size: 0.875em;
            margin-top: 0.25rem;
        }

        .is-invalid {
            border-color: #dc3545 !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }

        .is-invalid:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
        }
    </style>
    <style>
        /* Add this to your existing CSS section */
        .dropdown-menu {
            display: none;
            position: absolute;
            z-index: 1000;
            background-color: white;
            border: 1px solid rgba(0, 0, 0, .15);
            border-radius: 0.25rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, .175);
            min-width: 10rem;
            padding: 0.5rem 0;
            margin: 0.125rem 0 0;
        }

        .dropdown-menu.show {
            display: block;
        }

        .dropdown-item {
            display: block;
            width: 100%;
            padding: 0.25rem 1.5rem;
            clear: both;
            font-weight: 400;
            color: #212529;
            text-align: inherit;
            white-space: nowrap;
            background-color: transparent;
            border: 0;
        }

        .dropdown-item:hover {
            color: #16181b;
            text-decoration: none;
            background-color: #f8f9fa;
        }

        .status-circle {
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            margin-right: 8px;
            vertical-align: middle;
        }

        .default {
            background-color: grey;
        }

        .red {
            background-color: red;
        }

        .yellow {
            background-color: yellow;
        }

        .blue {
            background-color: blue;
        }

        .green {
            background-color: green;
        }

        .pink {
            background-color: pink;
        }
    </style>
    <style>
        /* Dil% cell styling */
        .dil-percent-cell {
            padding: 8px 4px !important;
        }

        .dil-percent-value {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
        }

        .dil-percent-value.red {
            background-color: #dc3545;
            color: white;
        }

        .dil-percent-value.blue {
            background-color: #3591dc;
            color: white;
        }

        .dil-percent-value.yellow {
            background-color: #ffc107;
            color: #212529;
        }

        .dil-percent-value.green {
            background-color: #28a745;
            color: white;
        }

        .dil-percent-value.pink {
            background-color: #e83e8c;
            color: white;
        }

        .dil-percent-value.gray {
            background-color: #6c757d;
            color: white;
        }
    </style>
    <style>
        /* Fixed Header Styling */
        .dtfh-floatingparenthead {
            left: 286.875px !important;
            width: calc(100% - 286.875px) !important;
            top: 0 !important;
            position: fixed !important;
            z-index: 1000 !important;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            height: auto !important;
        }

        table.fixedHeader-floating {
            background-color: white;
            width: 100% !important;
            left: 0 !important;
            table-layout: fixed !important;
            margin-top: 0 !important;
        }

        table.fixedHeader-floating thead th {
            background-color: white !important;
            position: relative;
            vertical-align: middle;
            white-space: nowrap;
        }

        table.fixedHeader-floating.no-footer {
            border-bottom-width: 0;
        }

        table.fixedHeader-locked {
            position: absolute !important;
            background-color: white;
        }

        /* Adjust table body to account for fixed header */
        .dataTables_scrollBody {
            padding-top: 55px !important;
            overflow: visible !important;
        }

        /* Ensure proper column width synchronization */
        .fixedHeader-floating th {
            box-sizing: border-box;
        }

        /* Print styles */
        @media print {
            table.fixedHeader-floating {
                display: none;
            }

            .dtfh-floatingparenthead {
                display: none;
            }
        }

        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .dtfh-floatingparenthead {
                left: 250px !important;
                width: calc(100% - 250px) !important;
            }
        }

        @media (max-width: 992px) {
            .dtfh-floatingparenthead {
                left: 200px !important;
                width: calc(100% - 200px) !important;
            }
        }

        @media (max-width: 768px) {
            .dtfh-floatingparenthead {
                left: 0 !important;
                width: 100% !important;
            }

            .dataTables_scrollBody {
                padding-top: 100px !important;
            }
        }
    </style>
    <style>
        /* Rainbow Wave Loader */
        .rainbow-loader {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1000;
            text-align: center;
        }

        .wave {
            width: 10px;
            height: 50px;
            background: linear-gradient(45deg, #ff0000, #ff7300, #fffb00, #48ff00, #00ffd5, #002bff, #7a00ff, #ff00c8, #ff0000);
            background-size: 400%;
            margin: 3px;
            animation: wave 1s linear infinite, rainbow 20s linear infinite;
            border-radius: 20px;
            display: inline-block;
        }

        .wave:nth-child(2) {
            animation-delay: 0.1s;
        }

        .wave:nth-child(3) {
            animation-delay: 0.2s;
        }

        .wave:nth-child(4) {
            animation-delay: 0.3s;
        }

        .wave:nth-child(5) {
            animation-delay: 0.4s;
        }

        .loading-text {
            margin-top: 20px;
            font-size: 18px;
            font-weight: bold;
            background: linear-gradient(to right, #ff0000, #ff7300, #fffb00, #48ff00, #00ffd5, #002bff, #7a00ff, #ff00c8);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            background-size: 400%;
            animation: rainbow 8s linear infinite;
        }

        @keyframes wave {

            0%,
            60%,
            100% {
                height: 30px;
            }

            30% {
                height: 70px;
            }
        }

        @keyframes rainbow {
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', [
        'page_title' => 'ShortFall Analysis',
        'sub_title' => 'Product master Analysis',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <!-- Rainbow Wave Loader -->
                    <div id="rainbow-loader" class="rainbow-loader">
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="loading-text">Loading Product Master Data...</div>
                    </div>

                    <div class="col-md-6 text-end">
                        <!-- <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                            data-bs-target="#addProductModal">
                            <i class="fas fa-plus me-1"></i> ADD PRODUCT
                        </button> -->
                    </div>

                    <!-- Add Product Modal -->
                    <div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel"
                        aria-hidden="true">
                        <div class="modal-dialog modal-xl">
                            <div class="modal-content"
                                style="border: none; border-radius: 0; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
                                <!-- Modal Header -->
                                <div class="modal-header"
                                    style="background: linear-gradient(135deg, #6B73FF 0%, #000DFF 100%); border-bottom: 4px solid #4D55E6; padding: 1.5rem; border-radius: 0;">
                                    <h5 class="modal-title" id="addProductModalLabel"
                                        style="color: white; font-weight: 800; font-size: 1.8rem; letter-spacing: 1px;">
                                        <i class="fas fa-plus-circle me-2"></i>ADD NEW PRODUCT LISTING
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>

                                <!-- Modal Body -->
                                <div class="modal-body" style="background-color: #F8FAFF; padding: 2rem;">
                                    <form id="addProductForm">
                                        <!-- Row 1 -->
                                        <div class="row mb-4">
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="sku" class="form-label fw-bold"
                                                        style="color: #4A5568;">SKU*</label>
                                                    <input type="text" class="form-control" id="sku"
                                                        placeholder="Enter SKU"
                                                        style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;">
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="parent" class="form-label fw-bold" style="color: #4A5568;">Parent</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" id="parent" placeholder="Enter or select parent"
                                                            style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;"
                                                            list="parentOptions">
                                                        <datalist id="parentOptions">
                                                            <!-- Parent options will be dynamically added here -->
                                                        </datalist>
                                                        <button class="btn btn-outline-secondary" type="button" id="refreshParents" style="border-radius: 0 6px 6px 0;">
                                                            <i class="fas fa-sync-alt"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="labelQty" class="form-label fw-bold"
                                                        style="color: #4A5568;">Label QTY*</label>
                                                    <input type="text" class="form-control" id="labelQty"
                                                        placeholder="Enter QTY"
                                                        style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;">
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                            </div>
                                            
                                          

                                        </div>

                                        <!-- Row 2 -->
                                        <div class="row mb-4">
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="cps" class="form-label fw-bold"
                                                        style="color: #4A5568;">CP$*</label>
                                                    <input type="text" class="form-control" id="cps"
                                                        placeholder="Enter CPS"
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
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="freight" class="form-label fw-bold"
                                                        style="color: #4A5568;">FRIGHT</label>
                                                    <input type="text" class="form-control" id="freight"
                                                        placeholder="Enter FRIGHT"
                                                        style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: #EDF2F7;"
                                                        readonly>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="lps" class="form-label fw-bold"
                                                        style="color: #4A5568;">LPS</label>
                                                    <input type="text" class="form-control" id="lps"
                                                        placeholder="Enter LPS"
                                                        style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: #EDF2F7;"
                                                        readonly>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Row 3 -->
                                        <div class="row mb-4">
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="ship" class="form-label fw-bold"
                                                        style="color: #4A5568;">SHIP*</label>
                                                    <input type="text" class="form-control" id="ship"
                                                        placeholder="Enter SHIP"
                                                        style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;">
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="wtDecl" class="form-label fw-bold"
                                                        style="color: #4A5568;">WT DECL*</label>
                                                    <input type="text" class="form-control" id="wtDecl"
                                                        placeholder="Enter WT DECL"
                                                        style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;">
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="wtAct" class="form-label fw-bold"
                                                        style="color: #4A5568;">WT ACT*</label>
                                                    <input type="text" class="form-control" id="wtAct"
                                                        placeholder="Enter WT ACT"
                                                        style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;">
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="w" class="form-label fw-bold"
                                                        style="color: #4A5568;">W*</label>
                                                    <input type="text" class="form-control" id="w"
                                                        placeholder="Enter W"
                                                        style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;">
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Row 4 -->
                                        <div class="row mb-4">
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="l" class="form-label fw-bold"
                                                        style="color: #4A5568;">L*</label>
                                                    <input type="text" class="form-control" id="l"
                                                        placeholder="Enter L"
                                                        style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;">
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="h" class="form-label fw-bold"
                                                        style="color: #4A5568;">H*</label>
                                                    <input type="text" class="form-control" id="h"
                                                        placeholder="Enter H"
                                                        style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;">
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="cbm" class="form-label fw-bold"
                                                        style="color: #4A5568;">CBM</label>
                                                    <input type="text" class="form-control" id="cbm"
                                                        placeholder="Enter CBM"
                                                        style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;">
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="l2Url" class="form-label fw-bold"
                                                        style="color: #4A5568;">L(2) URL</label>
                                                    <input type="text" class="form-control" id="l2Url"
                                                        placeholder="Enter URL"
                                                        style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;">
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

                    <!-- DataTable -->
                    <table id="row-callback-datatable" class="table dt-responsive nowrap w-100">
                        <thead>
                        <tr>
                            <th style="vertical-align: middle; white-space: nowrap;">Sl No</th>
                                <th style="vertical-align: middle; white-space: nowrap;">Parent</th>
                                <th style="vertical-align: middle; white-space: nowrap;">SKU</th>
                                <th style="vertical-align: middle; white-space: nowrap;">INV</th>
                                <th style="vertical-align: middle; white-space: nowrap;">OV L30</th>
                                <th style="vertical-align: middle; white-space: nowrap;">DIL</th>
                                <!-- <th style="vertical-align: middle; white-space: nowrap;">STATUS</th>
                                <th style="vertical-align: middle; white-space: nowrap;">LP</th>
                                <th style="vertical-align: middle; white-space: nowrap;">CP$</th>
                                <th style="vertical-align: middle; white-space: nowrap;">FRGHT</th>
                                <th style="vertical-align: middle; white-space: nowrap;">SHIP</th>
                                <th style="vertical-align: middle; white-space: nowrap;">Label QTY</th>
                                <th style="vertical-align: middle; white-space: nowrap;">LPS</th>
                                <th style="vertical-align: middle; white-space: nowrap;">WT ACT</th>
                                <th style="vertical-align: middle; white-space: nowrap;">WT DECL</th>
                                <th style="vertical-align: middle; white-space: nowrap;">L</th>
                                <th style="vertical-align: middle; white-space: nowrap;">W</th>
                                <th style="vertical-align: middle; white-space: nowrap;">H</th>
                                <th style="vertical-align: middle; white-space: nowrap;">CBM</th>
                                <th style="vertical-align: middle; white-space: nowrap;">L(2)</th>
                                <th style="vertical-align: middle; white-space: nowrap;">Action</th> -->
                            </tr>
                        </thead>
                        <tbody id="table-body">
                            <!-- Rows will be dynamically inserted here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
<!-- parent fetch code start -->
<script>
// Function to fetch and populate parent SKUs
    function fetchParentSKUs() {
        const table = $('#row-callback-datatable').DataTable();
        const parentOptions = document.getElementById('parentOptions');
        
        // Clear existing options
        parentOptions.innerHTML = '';
        
        // Get unique parent SKUs from the table data
        const parentSKUs = new Set();
        
        table.rows().every(function() {
            const rowData = this.data();
            if (rowData.SKU && rowData.SKU.toUpperCase().includes('PARENT')) {
                parentSKUs.add(rowData.SKU);
            }
            if (rowData.Parent) {
                parentSKUs.add(rowData.Parent);
            }
        });
        
        // Add parent options to the datalist
        parentSKUs.forEach(sku => {
            const option = document.createElement('option');
            option.value = sku;
            parentOptions.appendChild(option);
        });
    }

// Call fetchParentSKUs when modal opens
document.getElementById('addProductModal').addEventListener('show.bs.modal', function() {
    fetchParentSKUs();
});

// Add refresh button functionality
document.getElementById('refreshParents').addEventListener('click', fetchParentSKUs);

</script>

<!-- parent fetch code end -->

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set zoom level
            document.body.style.zoom = "65%";

            // Validation functions
            function validateNumberInput(input, fieldName, allowZero = true) {
                const value = input.value.trim();
                const errorMessage = fieldName + ' must be a valid number' + (allowZero ? '' :
                    ' greater than zero');

                if (value === '') {
                    showValidationError(input, fieldName + ' is required');
                    return false;
                }

                if (isNaN(value)) {
                    showValidationError(input, errorMessage);
                    return false;
                }

                const numValue = parseFloat(value);
                if (!allowZero && numValue <= 0) {
                    showValidationError(input, errorMessage);
                    return false;
                }

                if (numValue < 0) {
                    showValidationError(input, fieldName + ' cannot be negative');
                    return false;
                }

                clearValidationError(input);
                return true;
            }

            function showValidationError(input, message) {
                const formGroup = input.closest('.form-group');
                let errorElement = formGroup.querySelector('.invalid-feedback');

                if (!errorElement) {
                    errorElement = document.createElement('div');
                    errorElement.className = 'invalid-feedback';
                    formGroup.appendChild(errorElement);
                }

                input.classList.add('is-invalid');
                errorElement.textContent = message;
            }

            function clearValidationError(input) {
                const formGroup = input.closest('.form-group');
                const errorElement = formGroup.querySelector('.invalid-feedback');

                if (errorElement) {
                    input.classList.remove('is-invalid');
                    errorElement.textContent = '';
                }
            }

            // Initialize DataTable
            function initializeDataTable() {
                $('#row-callback-datatable').DataTable({
                    serverSide: true,
                    ajax: {
                        url: '/shortfall-analysis-data-view',
                        type: 'GET',
                        beforeSend: function() {
                            document.getElementById('rainbow-loader').style.display = 'block';
                        },
                        complete: function() {
                            document.getElementById('rainbow-loader').style.display = 'none';
                        },
                        error: function(xhr, error, thrown) {
                            document.getElementById('rainbow-loader').innerHTML =
                                '<div class="error-message">⚠️ Error loading product data</div>';
                        }
                    },
                    fixedHeader: {
                        header: true,
                    },
                    responsive: true,
                    pageLength: 25,
                    lengthMenu: [10, 25, 50, 100],
                    order: [],
                    createdRow: function(row, data, dataIndex) {
                        // Check if SKU contains "PARENT" (case insensitive)
                        if (data.SKU && data.SKU.toUpperCase().includes('PARENT')) {
                            $(row).css({
                                'background-color': 'rgba(13, 110, 253, 0.2)',
                                'font-weight': '500'
                            });
                        }
                    },
                    columns: [{
                            data: null,  // We're not using actual data from the server
                            name: 'sl_no',
                            orderable: false,  // Disable sorting on this column
                            render: function(data, type, row, meta) {
                                // meta.row gives the current row index (0-based)
                                // Add 1 to start numbering from 1
                                return meta.row + 1;
                            }
                        },
                        {
                            data: 'Parent',
                            name: 'Parent'
                        },
                        {
                            data: 'SKU',
                            name: 'SKU'
                        },
                        {
                            data: 'INV',
                            name: 'INV',
                            render: function(data) {
                                return data ? data : '0';
                            }
                        },
                        {
                            data: 'L30',
                            name: 'L30',
                            render: function(data) {
                                return data ? data : '0';
                            }
                        },
                        {
                            data: null,
                            name: 'DIL',
                            render: function(data, type, row) {
                                // Convert L30 and INV to numbers (treat empty/undefined as 0)
                                const l30 = parseFloat(row.L30) || 0;
                                const inv = parseFloat(row.INV) || 0;
                                
                                // Avoid division by zero
                                if (inv === 0) return '<span class="dil-percent-value gray">0.00</span>';
                                
                                // Calculate and format the result to 2 decimal places
                                const result = (l30 / inv).toFixed(2);
                                
                                // Apply conditional formatting
                                let cssClass = 'gray'; // default
                                const numResult = parseFloat(result);
                                
                                if (numResult > 1) cssClass = 'red';
                                else if (numResult > 0.7) cssClass = 'yellow';
                                else if (numResult > 0.3) cssClass = 'blue';
                                else if (numResult >= 0) cssClass = 'green';
                                
                                return `<span class="dil-percent-value ${cssClass}">${result}</span>`;
                            }
                        },
                        // {
                        //     data: 'STATUS',
                        //     name: 'STATUS',
                        //     render: function(data) {
                        //         return data ? data : '-';
                        //     }
                        // },
                        // {
                        //     data: 'LP',
                        //     name: 'LP',
                        //     className: 'text-center',
                        //     render: function(data) {
                        //         return data ? parseFloat(data).toFixed(2) : '-';
                        //     }
                        // },
                        // {
                        //     data: 'CP',
                        //     name: 'CP',
                        //     className: 'text-center',
                        //     render: function(data) {
                        //         return data ? parseFloat(data).toFixed(2) : '-';
                        //     }
                        // },
                        // {
                        //     data: 'FRGHT',
                        //     name: 'FRGHT',
                        //     className: 'text-center',
                        //     render: function(data) {
                        //         return data ? parseFloat(data).toFixed(2) : '-';
                        //     }
                        // },
                        // {
                        //     data: 'SHIP',
                        //     name: 'SHIP',
                        //     className: 'text-center',
                        //     render: function(data) {
                        //         return data || '-';
                        //     }
                        // },
                        // {
                        //     data: 'Label QTY',
                        //     name: 'Label QTY',
                        //     className: 'text-center',
                        //     defaultContent: '0'
                        // },
                        // {
                        //     data: 'LPS',
                        //     name: 'LPS',
                        //     className: 'text-center',
                        //     render: function(data) {
                        //         return data ? parseFloat(data).toFixed(2) : '';
                        //     }
                        // },
                        // {
                        //     data: function(row) {
                        //         return row['WT ACT'] || row['weight_actual'] || '0';
                        //     },
                        //     name: 'WT ACT',
                        //     className: 'text-center',
                        //     render: function(data) {
                        //         return parseFloat(data).toFixed(2);
                        //     }
                        // },
                        // {
                        //     data: function(row) {
                        //         return row['WT DECL'] || row['WT_DECL'] || row['wt_decl'] || row[
                        //             'weight_declared'] || '0';
                        //     },
                        //     name: 'WT DECL',
                        //     className: 'text-center',
                        //     render: function(data) {
                        //         return parseFloat(data).toFixed(2);
                        //     }
                        // },
                        // {
                        //     data: function(row) {
                        //         return row['L'] || row['length'] || row['Length'] || row[
                        //             'product_length'] || '0';
                        //     },
                        //     name: 'L',
                        //     className: 'text-center',
                        //     render: function(data) {
                        //         return data ? parseFloat(data).toFixed(2) : '0.00';
                        //     }
                        // },
                        // {
                        //     data: function(row) {
                        //         return row['W'] || row['width'] || row['Width'] || row[
                        //             'product_width'] || '0';
                        //     },
                        //     name: 'W',
                        //     className: 'text-center',
                        //     render: function(data) {
                        //         return data ? parseFloat(data).toFixed(2) : '0.00';
                        //     }
                        // },
                        // {
                        //     data: function(row) {
                        //         return row['H'] || row['height'] || row['product_height'] || '0';
                        //     },
                        //     name: 'H',
                        //     className: 'text-center',
                        //     render: function(data) {
                        //         return parseFloat(data).toFixed(2);
                        //     }
                        // },
                        // {
                        //     data: 'CBM',
                        //     name: 'CBM',
                        //     className: 'text-center',
                        //     render: function(data) {
                        //         return data ? parseFloat(data).toFixed(4) : '0.0000';
                        //     },
                        //     defaultContent: '0.0000'
                        // },
                        // {
                        //     data: '5C',
                        //     name: '5C',
                        //     className: 'text-center',
                        //     render: function(data) {
                        //         return data ? '<a href="' + data +
                        //             '" target="_blank"><i class="fas fa-external-link-alt"></i></a>' :
                        //             '-';
                        //     },
                        //     defaultContent: '-'
                        // },
                        // {
                        //     data: null,
                        //     name: 'Edit',
                        //     className: 'text-center',
                        //     orderable: false,
                        //     render: function(data, type, row, meta) {
                        //         return '<button class="btn btn-sm btn-outline-primary edit-btn"><i class="bi bi-pencil-square"></i></button>';
                        //     }
                        // }
                    ],
                    initComplete: function() {
                        if ($.fn.DataTable.FixedHeader) {
                            new $.fn.DataTable.FixedHeader(this, {
                                header: true
                            });
                        }
                    }
                });
            }

            // Save Product to Google Sheets
            document.getElementById('saveProductBtn').addEventListener('click', function() {
                // Validate form
                let isValid = true;
                
                // Validate required fields
                // In your validation section, ensure these fields are checked:
                const requiredFields = ['sku', 'labelQty', 'cps', 'ship', 'wtDecl', 'wtAct', 'w', 'l', 'h'];
                requiredFields.forEach(fieldId => {
                    const input = document.getElementById(fieldId);
                    if (!input.value.trim()) {
                        showValidationError(input, 'This field is required');
                        isValid = false;
                    } else {
                        clearValidationError(input);
                    }
                });

                // Validate numbers
                const numberFields = ['labelQty', 'cps', 'ship', 'wtDecl', 'wtAct', 'w', 'l', 'h', 'cbm'];
                numberFields.forEach(fieldId => {
                    const input = document.getElementById(fieldId);
                    if (input.value && isNaN(input.value)) {
                        showValidationError(input, 'Must be a valid number');
                        isValid = false;
                    } else if (input.value) {
                        clearValidationError(input);
                    }
                });

                if (!isValid) {
                    // Scroll to first error
                    const firstError = document.querySelector('.is-invalid');
                    if (firstError) {
                        firstError.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                    }
                    return;
                }

                // Show loading indicator
                const saveBtn = document.getElementById('saveProductBtn');
                const originalBtnText = saveBtn.innerHTML;
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                saveBtn.disabled = true;

                // Prepare data
                const formData = {
                    SKU: document.getElementById('sku').value,
                    Parent: document.getElementById('parent').value || '',
                    'Label_QTY': document.getElementById('labelQty').value, // Note the exact field name
                    CP: document.getElementById('cps').value,
                    SHIP: document.getElementById('ship').value,
                    'WT_ACT': document.getElementById('wtDecl').value, // Exact field name
                    'WT_DECL': document.getElementById('wtAct').value, // Exact field name
                    W: document.getElementById('w').value,
                    L: document.getElementById('l').value,
                    H: document.getElementById('h').value,
                    CBM: document.getElementById('cbm').value || '0',
                    '5C': document.getElementById('l2Url').value || ''
                };

                // Send data to Laravel backend
                fetch('/api/create-product-master', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify(formData)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Show success message
                            showAlert('success', 'Product saved successfully!');

                            // Close modal
                            bootstrap.Modal.getInstance(document.getElementById('addProductModal'))
                                .hide();

                            // Clear form
                            document.getElementById('addProductForm').reset();

                            // Refresh DataTable
                            $('#row-callback-datatable').DataTable().ajax.reload();
                        } else {
                            showAlert('danger', 'Error: ' + (data.error || 'Failed to save product'));
                        }
                    })
                    .catch(error => {
                        showAlert('danger', 'Network error: ' + error.message);
                    })
                    .finally(() => {
                        // Restore button
                        saveBtn.innerHTML = originalBtnText;
                        saveBtn.disabled = false;
                    });
            });

            // Helper function to show alerts
            function showAlert(type, message) {
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert alert-${type} alert-notification`;
                alertDiv.innerHTML = `
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    ${message}
                `;

                document.body.appendChild(alertDiv);

                // Auto-remove after 5 seconds
                setTimeout(() => {
                    alertDiv.remove();
                }, 5000);
            }

            // Initialize DataTable
            if (window.jQuery) {
                if ($.fn.DataTable) {
                    initializeDataTable();
                } else {
                    // Load DataTables if not present
                    var script = document.createElement('script');
                    script.src = 'https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js';
                    script.onload = function() {
                        var script2 = document.createElement('script');
                        script2.src =
                            'https://cdn.datatables.net/fixedheader/3.3.2/js/dataTables.fixedHeader.min.js';
                        script2.onload = initializeDataTable;
                        document.head.appendChild(script2);
                    };
                    document.head.appendChild(script);
                }
            } else {
                // Load jQuery first if not present
                var script = document.createElement('script');
                script.src = 'https://code.jquery.com/jquery-3.6.0.min.js';
                script.onload = function() {
                    var script2 = document.createElement('script');
                    script2.src = 'https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js';
                    script2.onload = function() {
                        var script3 = document.createElement('script');
                        script3.src =
                            'https://cdn.datatables.net/fixedheader/3.3.2/js/dataTables.fixedHeader.min.js';
                        script3.onload = initializeDataTable;
                        document.head.appendChild(script3);
                    };
                    document.head.appendChild(script2);
                };
                document.head.appendChild(script);
            }

            // Show loader immediately
            document.getElementById('rainbow-loader').style.display = 'block';
        });
    </script>
@endsection
