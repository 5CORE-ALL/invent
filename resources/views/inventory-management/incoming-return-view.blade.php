@extends('layouts.vertical', ['title' => 'Incoming Return (Devolución entrante)', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
    <!-- Add DataTables Buttons CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js" defer></script>

    <style>
        /* Your existing styles */
        .dt-buttons .btn {
            margin-left: 10px;
        }

        .dataTables_wrapper .dataTables_filter input {
            border-radius: 4px;
            border: 1px solid #ddd;
            padding: 5px;
        }
    </style>
    <style>
        /* Add this to your existing styles */
        .table-responsive {
            position: relative;
            border: 1px solid #dee2e6;
            max-height: 600px;
            /* or whatever height you prefer */
            overflow-y: auto;
        }

        .table-responsive thead th {
            position: sticky;
            top: 0;
            background-color: #2c6ed5;
            /* Grid blue color */
            color: white;
            /* White text for better contrast */
            z-index: 10;
            padding: 12px 15px;
            /* Adjust padding as needed */
            font-weight: 600;
            /* Make header text slightly bold */
            border-bottom: 2px solid #1a56b7;
            /* Darker blue border bottom */
        }

        /* Optional: Add some shadow to the sticky header */
        .table-responsive thead th {
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        /* Hover effect for header cells */
        .table-responsive thead th:hover {
            background-color: #1a56b7;
            /* Slightly darker blue on hover */
        }

        /* Style for table cells to match the design */
        .table-responsive tbody td {
            padding: 10px 15px;
            vertical-align: middle;
            border-bottom: 1px solid #e0e0e0;
            word-break: break-word;
        }

        /* Alternate row coloring for better readability */
        .table-responsive tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        /* Hover effect for rows */
        .table-responsive tbody tr:hover {
            background-color: #ebf2fb;
        }
    </style>
    <style>
        /* Override DataTables styles if needed */
        #inventoryTable thead th {
            background-color: #2c6ed5 !important;
            color: white !important;
        }

        /* Ensure DataTables sorting icons are visible */
        #inventoryTable thead th.sorting:after,
        #inventoryTable thead th.sorting_asc:after,
        #inventoryTable thead th.sorting_desc:after {
            color: white !important;
            opacity: 0.8 !important;
        }

        #returnHistoryTable thead th {
            background-color: #2c6ed5 !important;
            color: white !important;
        }

        #returnHistoryTable thead th.sorting:after,
        #returnHistoryTable thead th.sorting_asc:after,
        #returnHistoryTable thead th.sorting_desc:after {
            color: white !important;
            opacity: 0.8 !important;
        }

        .is-invalid {
            border: 2px solid red !important;
            background-color: #ffe6e6 !important;
        }
        .error-message {
            font-size: 13px;
            margin-top: 4px;
        }

        /* Success Toast Styles */
        /* Success Modal Styles */
        #successModal .modal-content {
            border: none;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            border-radius: 10px;
        }

        #successModal .modal-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border-bottom: none;
            border-radius: 10px 10px 0 0;
            padding: 1.25rem 1.5rem;
        }

        #successModal .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
        }

        #successModal .modal-body {
            padding: 2rem 1.5rem;
        }

        #successModal .modal-footer {
            border-top: 1px solid #e9ecef;
            padding: 1rem 1.5rem;
        }

        #successModal .btn-success {
            min-width: 120px;
            font-weight: 600;
            padding: 0.75rem 2rem;
        }

        /* Mobile-first incoming form */
        @media (max-width: 767.98px) {
            body.incoming-pwa-page {
                zoom: 1 !important;
            }
        }

        .incoming-mobile .form-label {
            font-size: 0.95rem;
        }

        .incoming-mobile .btn-touch {
            min-height: 48px;
            font-size: 1.05rem;
            padding: 0.65rem 1rem;
            border-radius: 10px;
        }

        .incoming-mobile .form-control,
        .incoming-mobile .form-select {
            min-height: 48px;
            font-size: 1rem;
        }

        #incoming-photo-thumbs {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .incoming-thumb-wrap {
            position: relative;
            width: 72px;
            height: 72px;
        }

        .incoming-thumb-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }

        .incoming-thumb-wrap button {
            position: absolute;
            top: -6px;
            right: -6px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            padding: 0;
            line-height: 1;
            font-size: 14px;
        }

        #barcodeScannerModal .modal-body {
            min-height: 280px;
        }

        #barcode-reader video {
            border-radius: 8px;
        }

        .incoming-product-hint {
            font-size: 0.875rem;
            color: #6c757d;
        }

        .incoming-sku-input-wrap .incoming-sku-suggest {
            top: 100%;
            left: 0;
            margin-top: 2px;
            border-radius: 0.375rem;
            border: 1px solid #dee2e6;
            background: #fff;
        }

        .incoming-sku-suggest-item {
            border: none;
            border-bottom: 1px solid #eee;
            cursor: pointer;
        }

        .incoming-sku-suggest-item:last-child {
            border-bottom: none;
        }

        .incoming-sku-suggest-item:hover,
        .incoming-sku-suggest-item:focus {
            background-color: #e7f1ff;
        }

        .incoming-sku-suggest-thumb {
            width: 52px;
            height: 52px;
            object-fit: cover;
            flex-shrink: 0;
        }

        .incoming-sku-suggest-thumb-placeholder {
            width: 52px;
            height: 52px;
            flex-shrink: 0;
            font-size: 0.75rem;
        }

        /* Wider create / edit form modal (desktop) */
        @media (min-width: 576px) {
            #addWarehouseModal .modal-dialog.incoming-return-modal-dialog {
                max-width: min(96vw, 1400px);
            }
        }

        .incoming-table-thumb {
            width: 48px;
            height: 48px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #dee2e6;
            vertical-align: middle;
        }

        #inventoryTable th:first-child,
        #returnHistoryTable th:first-child {
            width: 64px;
        }

        /* Warehouse filter + custom dropdown trigger: Main (dark green), Trash (red), Open Box (mustard) */
        select.warehouse-filter-select.warehouse-filter-theme-main,
        .incoming-wh-dd-trigger.warehouse-filter-theme-main {
            color: #0d4d2e;
            border-color: #14532d;
            font-weight: 600;
            background-color: #f0f7f2;
        }

        select.warehouse-filter-select.warehouse-filter-theme-trash,
        .incoming-wh-dd-trigger.warehouse-filter-theme-trash {
            color: #9b1c1c;
            border-color: #c62828;
            font-weight: 600;
            background-color: #fff5f5;
        }

        select.warehouse-filter-select.warehouse-filter-theme-openbox,
        .incoming-wh-dd-trigger.warehouse-filter-theme-openbox {
            color: #7a5f00;
            border-color: #b8860b;
            font-weight: 600;
            background-color: #fffbeb;
        }

        .incoming-wh-dd-wrap:has(select.is-invalid) .incoming-wh-dd-trigger {
            border-color: #dc3545 !important;
        }

        .incoming-wh-dd-panel {
            z-index: 1080;
        }

        #addWarehouseModal .incoming-wh-dd-panel {
            z-index: 2005;
        }

        .incoming-wh-dd-item-dot {
            width: 0.55rem;
            height: 0.55rem;
            border-radius: 50%;
            margin-right: 0.45rem;
            flex-shrink: 0;
        }

        .incoming-wh-dd-item-dot--main { background-color: #0d4d2e; }
        .incoming-wh-dd-item-dot--trash { background-color: #9b1c1c; }
        .incoming-wh-dd-item-dot--openbox { background-color: #7a5f00; }

        .incoming-wh-dd-item--main { color: #0d4d2e; font-weight: 600; }
        .incoming-wh-dd-item--trash { color: #9b1c1c; font-weight: 600; }
        .incoming-wh-dd-item--openbox { color: #7a5f00; font-weight: 600; }

        .incoming-wh-dd-item--neutral { color: inherit; }

        .incoming-wh-dd-item:hover {
            background-color: #f1f3f5;
        }

        .incoming-wh-dd-trigger-inner {
            gap: 0.35rem;
        }

        .incoming-wh-dd-trigger-dot {
            width: 0.55rem;
            height: 0.55rem;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .incoming-wh-dd-trigger-dot--main { background-color: #0d4d2e; }
        .incoming-wh-dd-trigger-dot--trash { background-color: #9b1c1c; }
        .incoming-wh-dd-trigger-dot--openbox { background-color: #7a5f00; }

        #inventoryTable tbody td.incoming-wh-main,
        #returnHistoryTable tbody td.incoming-wh-main,
        .incoming-wh-cell.incoming-wh-main {
            color: #0d4d2e !important;
            font-weight: 600;
        }

        #inventoryTable tbody td.incoming-wh-trash,
        #returnHistoryTable tbody td.incoming-wh-trash,
        .incoming-wh-cell.incoming-wh-trash {
            color: #9b1c1c !important;
            font-weight: 600;
        }

        #inventoryTable tbody td.incoming-wh-openbox,
        #returnHistoryTable tbody td.incoming-wh-openbox,
        .incoming-wh-cell.incoming-wh-openbox {
            color: #7a5f00 !important;
            font-weight: 600;
        }

        /* Dot beside warehouse text (same hue as godown theme) */
        #inventoryTable tbody td.incoming-wh-main::before,
        #returnHistoryTable tbody td.incoming-wh-main::before {
            content: '';
            display: inline-block;
            width: 0.55rem;
            height: 0.55rem;
            border-radius: 50%;
            margin-right: 0.45rem;
            vertical-align: 0.1em;
            background-color: #0d4d2e;
        }

        #inventoryTable tbody td.incoming-wh-trash::before,
        #returnHistoryTable tbody td.incoming-wh-trash::before {
            content: '';
            display: inline-block;
            width: 0.55rem;
            height: 0.55rem;
            border-radius: 50%;
            margin-right: 0.45rem;
            vertical-align: 0.1em;
            background-color: #9b1c1c;
        }

        #inventoryTable tbody td.incoming-wh-openbox::before,
        #returnHistoryTable tbody td.incoming-wh-openbox::before {
            content: '';
            display: inline-block;
            width: 0.55rem;
            height: 0.55rem;
            border-radius: 50%;
            margin-right: 0.45rem;
            vertical-align: 0.1em;
            background-color: #7a5f00;
        }

    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', [
        'page_title' => 'Incoming Return (Devolución entrante)',
        'sub_title' => 'Incoming Return (Devolución entrante)',
    ])

    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 11000;">
        <div id="incomingToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="incomingToastBody"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close (Cerrar)"></button>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">


                    <!-- Search Box, warehouse filter, and Add Button-->
                    <div class="row mb-3 g-2 align-items-end">
                        <div class="col-xl-4 col-lg-5 col-md-12 d-flex align-items-center flex-wrap gap-2">
                            <button type="button" class="btn btn-primary" id="openAddWarehouseModal" data-bs-toggle="modal" data-bs-target="#addWarehouseModal">
                                <i class="fas fa-plus me-1"></i> CREATE INCOMING RETURN (CREAR DEVOLUCIÓN ENTRANTE)
                            </button>
                            <div class="dataTables_length"></div>
                        </div>

                        <div class="col-xl-3 col-lg-4 col-md-6">
                            <label for="filterWarehouseMain_btn" class="form-label small fw-semibold mb-1">Warehouse filter (Filtro almacén)</label>
                            <div class="incoming-wh-dd-wrap position-relative">
                                <select id="filterWarehouseMain" class="d-none incoming-wh-dd-native" aria-hidden="true" tabindex="-1">
                                    <option value="">All warehouses (Todos los almacenes)</option>
                                    @foreach($warehouses as $warehouse)
                                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                    @endforeach
                                </select>
                                <button type="button" id="filterWarehouseMain_btn" class="form-select form-select-sm incoming-wh-dd-trigger warehouse-filter-select w-100 text-start d-flex align-items-center" aria-haspopup="listbox" aria-expanded="false">
                                    <span class="incoming-wh-dd-trigger-inner d-flex align-items-center min-w-0 flex-grow-1"></span>
                                </button>
                                <div class="incoming-wh-dd-panel list-group position-absolute top-100 start-0 end-0 d-none bg-white border rounded shadow-sm mt-1 py-1" role="listbox"></div>
                            </div>
                        </div>

                        <div class="col-xl-5 col-lg-12 col-md-6">
                            <label for="customSearch" class="form-label small fw-semibold mb-1 d-none d-md-block">&nbsp;</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" id="customSearch" class="form-control" placeholder="Search Incoming (Buscar entradas)">
                                <button class="btn btn-outline-secondary" type="button" id="clearSearch">Clear (Limpiar)</button>
                            </div>
                        </div>
                    </div>


                    <!-- <div class="col-md-6 text-end">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                            data-bs-target="#addWarehouseModal">
                            <i class="fas fa-plus me-1"></i> ADD WAREHOUSE
                        </button>
                        <button type="button" class="btn btn-success ms-2" id="downloadExcel">
                            <i class="fas fa-file-excel me-1"></i> Download Excel
                        </button>
                    </div> -->

                    <!-- Incoming Modal (full-screen on small viewports) -->
                    <div class="modal fade" id="addWarehouseModal" tabindex="-1" aria-labelledby="incomingModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-fullscreen-sm-down modal-xl modal-dialog-scrollable incoming-return-modal-dialog">
                            <form id="incomingReturnForm" enctype="multipart/form-data">
                                @csrf
                                <div class="modal-content incoming-mobile">

                                    <div class="modal-header bg-primary text-white">
                                        <h5 class="modal-title" id="incomingModalLabel">Add Incoming Return (Agregar devolución entrante)</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>

                                    <div class="modal-body">
                                        <div id="incoming-offline-banner" class="alert alert-warning d-none mb-3" role="alert">
                                            <i class="fas fa-wifi-slash me-2"></i>You are offline. Connect to the internet to submit (camera upload requires a connection). <span class="d-block mt-1">(Sin conexión. Conéctese a internet para enviar; la cámara requiere conexión.)</span>
                                        </div>
                                        <div id="incoming-errors" class="mb-2 text-danger"></div>

                                        <div class="mb-3">
                                            <label for="sku" class="form-label fw-bold">SKU (SKU)</label>
                                            <div class="d-flex flex-column flex-sm-row gap-2">
                                                <div class="incoming-sku-input-wrap flex-grow-1 position-relative">
                                                    <input type="text" class="form-control w-100" id="sku" name="sku" required autocomplete="off" placeholder="Scan or type SKU (Escanee o escriba el SKU)" inputmode="text">
                                                    <div id="sku-suggest-list" class="incoming-sku-suggest list-group position-absolute w-100 d-none" style="z-index: 1060; max-height: 240px; overflow-y: auto;" role="listbox" aria-label="SKU suggestions"></div>
                                                </div>
                                                <button type="button" class="btn btn-outline-primary btn-touch" id="btnScanBarcode">
                                                    <i class="fas fa-barcode me-1"></i> Scan Barcode (Escanear código de barras)
                                                </button>
                                            </div>
                                            <div id="sku-product-hint" class="incoming-product-hint mt-2 d-none"></div>
                                        </div>

                                        <div class="row g-3 mb-3">
                                            <div class="col-12 col-md-6">
                                                <label for="qty" class="form-label fw-bold">Quantity (Cantidad)</label>
                                                <input type="number" class="form-control" id="qty" name="qty" required min="1" step="1" inputmode="numeric">
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label for="warehouse_id_btn" class="form-label fw-bold">Warehouse (Almacén)</label>
                                                <div class="incoming-wh-dd-wrap position-relative">
                                                    <select class="d-none incoming-wh-dd-native" id="warehouse_id" name="warehouse_id" required aria-hidden="true" tabindex="-1">
                                                        <option selected disabled value="">Select Warehouse (Seleccione almacén)</option>
                                                        @foreach($warehouses as $warehouse)
                                                            <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                                        @endforeach
                                                    </select>
                                                    <button type="button" id="warehouse_id_btn" class="form-select incoming-wh-dd-trigger w-100 text-start d-flex align-items-center btn-touch" aria-haspopup="listbox" aria-expanded="false">
                                                        <span class="incoming-wh-dd-trigger-inner d-flex align-items-center min-w-0 flex-grow-1"></span>
                                                    </button>
                                                    <div class="incoming-wh-dd-panel list-group position-absolute top-100 start-0 end-0 d-none bg-white border rounded shadow-sm mt-1 py-1" role="listbox"></div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="reason" class="form-label fw-bold">Condition / Remarks (Condición / observaciones)</label>
                                            <textarea class="form-control" id="reason" name="reason" rows="4" required lang="es" maxlength="10000" style="min-height: 100px;" placeholder="Describa la condición del artículo, observaciones de la devolución, daños visibles, embalaje, etc."></textarea>
                                            <small class="text-muted">Escriba en español. (Write in Spanish.)</small>
                                        </div>

                                        <div class="row g-3 mb-3">
                                            <div class="col-12 col-md-4">
                                                <label class="form-label fw-bold">Photo 1 (Foto 1) <span class="text-muted fw-normal">(optional / opcional)</span></label>
                                                <div class="d-flex flex-column gap-2">
                                                    <input type="file" id="incoming-photo-input" class="d-none" accept="image/*" capture="environment" multiple>
                                                    <button type="button" class="btn btn-outline-secondary btn-touch w-100" id="btnAddPhotos">
                                                        <i class="fas fa-camera me-2"></i>Add Photos (Agregar fotos)
                                                    </button>
                                                </div>
                                                <div id="incoming-photo-thumbs" class="mt-2"></div>
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <label class="form-label fw-bold">Photo 2 (Foto 2) <span class="text-muted fw-normal">(optional / opcional)</span></label>
                                                <div class="d-flex flex-column gap-2">
                                                    <input type="file" id="incoming-photo-input-2" class="d-none" accept="image/*" capture="environment" multiple>
                                                    <button type="button" class="btn btn-outline-secondary btn-touch w-100" id="btnAddPhotos2">
                                                        <i class="fas fa-camera me-2"></i>Add Photos (Agregar fotos)
                                                    </button>
                                                </div>
                                                <div id="incoming-photo-thumbs-2" class="mt-2"></div>
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <label class="form-label fw-bold">Photo 3 (Foto 3) <span class="text-muted fw-normal">(optional / opcional)</span></label>
                                                <div class="d-flex flex-column gap-2">
                                                    <input type="file" id="incoming-photo-input-3" class="d-none" accept="image/*" capture="environment" multiple>
                                                    <button type="button" class="btn btn-outline-secondary btn-touch w-100" id="btnAddPhotos3">
                                                        <i class="fas fa-camera me-2"></i>Add Photos (Agregar fotos)
                                                    </button>
                                                </div>
                                                <div id="incoming-photo-thumbs-3" class="mt-2"></div>
                                            </div>
                                        </div>
                                        <small class="text-muted d-block mb-3">Camera access works on HTTPS. If the camera is unavailable, you can still choose images from your gallery. <span class="d-block mt-1">(La cámara requiere HTTPS. Si no está disponible, puede elegir imágenes de la galería.)</span></small>

                                        <p class="small text-muted mb-0">
                                            <i class="fas fa-clock me-1"></i>Date and time are saved automatically when you submit. (La fecha y hora se guardan automáticamente al enviar.)
                                        </p>
                                    </div>

                                    <input type="hidden" name="type" value="incoming_return">

                                    <div class="modal-footer flex-column flex-sm-row gap-2">
                                        <button type="button" class="btn btn-secondary btn-touch w-100 w-sm-auto" data-bs-dismiss="modal">Cancel (Cancelar)</button>
                                        <button type="submit" class="btn btn-success btn-touch w-100 w-sm-auto" id="incomingSubmitBtn">
                                            <i class="fas fa-save me-1"></i> Save Incoming Return (Guardar devolución entrante)
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Barcode scanner (camera) -->
                    <div class="modal fade" id="barcodeScannerModal" tabindex="-1" aria-labelledby="barcodeScannerLabel" aria-hidden="true" data-bs-backdrop="static">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="barcodeScannerLabel">Scan barcode (Escanear código de barras)</h5>
                                    <button type="button" class="btn-close" id="barcodeScannerClose" data-bs-dismiss="modal" aria-label="Close (Cerrar)"></button>
                                </div>
                                <div class="modal-body">
                                    <div id="barcode-reader" class="w-100"></div>
                                    <p id="barcode-scan-status" class="small text-muted mt-2 mb-0"></p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel (Cancelar)</button>
                                </div>
                            </div>
                        </div>
                    </div>


                    <!-- Add Purchase Order Modal -->
                    <!-- <div class="modal fade" id="addWarehouseModal1" tabindex="-1" aria-labelledby="addWarehouseModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-xl">
                            <form id="purchaseOrderForm" method="POST">
                            @csrf
                            <div class="modal-content" style="border: none; border-radius: 0; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">

                                <div class="modal-header" style="background: linear-gradient(135deg, #6B73FF 0%, #000DFF 100%); border-bottom: 4px solid #4D55E6; padding: 1.5rem; border-radius: 0;">
                                    <h5 class="modal-title" id="warehouseModalLabel"
                                        style="color: white; font-weight: 800; font-size: 1.8rem; letter-spacing: 1px;">
                                        <i class="fas fa-plus-circle me-2"></i>ADD NEW PURCHASE ORDER
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>

                                <div class="modal-body" style="background-color: #F8FAFF; padding: 2rem;">
                                    <input type="hidden" id="warehouseId" name="id">
                                    <div id="form-errors-warehouse" class="mb-3"></div>
                                        <div class="row mb-4">
                                            
                                            <div class="col-md-6 mb-3">
                                                <label for="po_date" class="form-label fw-bold text-secondary">Date</label>
                                                <input type="text" id="po_date" name="po_date" class="form-control" readonly>
                                            </div>
                                           
                                            <div class="col-md-6 mb-3">
                                                <label for="purchase_order_no" class="form-label fw-bold text-secondary">PO Number</label>
                                                <input type="text" id="purchase_order_no" name="purchase_order_no" class="form-control" readonly>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="supplier" class="form-label fw-bold text-secondary">Supplier</label>
                                                <select id="supplier_id" name="supplier_id" class="form-select" required>
                                                    <option value="" disabled selected>Select Supplier</option>
                                                        <option value=""></option>
                                                </select>
                                            </div>
                                           
                                            <div class="col-md-6 mb-3">
                                                <label for="warehouse" class="form-label fw-bold text-secondary">Warehouse</label>
                                                <select id="warehouse_id" name="warehouse_id" class="form-select" required>
                                                    <option value="" disabled selected>Select Warehouse</option>
                                                        <option value=""></option>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="parent" class="form-label fw-bold text-secondary">Parent</label>
                                                <select id="parent" name="parent" class="form-select" required>
                                                    <option value="" disabled selected>Select Parent</option>
                                                        <option value=""></option>
                                                </select>
                                            </div>
                                           
                                            <div class="col-md-6 mb-3">
                                                <label for="sku" class="form-label fw-bold text-secondary">SKU</label>
                                                <select id="sku" name="sku" class="form-select" required>
                                                    <option value="" disabled selected>Select SKU</option>
                                                        <option value=""></option>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="qty" class="form-label fw-bold text-secondary">Quantity</label>
                                                <input type="number" id="qty" name="qty" class="form-control">
                                            </div>
                                           
                                            <div class="col-md-6 mb-3">
                                                <label for="rate" class="form-label fw-bold text-secondary">Rate</label>
                                                <input type="number" id="rate" name="rate" class="form-control">
                                            </div>

                                            <div class="col-md-12 mb-3">
                                                <div class="form-group">
                                                    <label for="currency" class="form-label fw-bold" style="color: #4A5568;">Currency</label>
                                                    <select class="form-select" id="currency" name="currency"
                                                        style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;" required>
                                                        <option selected disabled>Select Currency</option>
                                                        <option value="RMB">RMB</option>
                                                        <option value="USD">USD</option>
                                                    </select>
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="rmb_amt" class="form-label fw-bold text-secondary">Amount-RMB</label>
                                                <input type="number" id="rmb_amt" name="rmb_amt" class="form-control" readonly>
                                            </div>
                                           
                                            <div class="col-md-6 mb-3">
                                                <label for="usd_amt" class="form-label fw-bold text-secondary">Amount-USD</label>
                                                <input type="number" id="usd_amt" name="usd_amt" class="form-control" readonly>
                                            </div>
                                            <div class="col-md-12">
                                                <label for="cbm" class="form-label fw-bold text-secondary">CBM</label>
                                                <input type="text" id="cbm" name="cbm" class="form-control">
                                            </div>
                                           
                                        </div>
                                    </form>
                                </div>

                                <input type="hidden" id="warehouseId">
                                <div class="modal-footer"
                                    style="background: linear-gradient(135deg, #F8FAFF 0%, #E6F0FF 100%); border-top: 4px solid #E2E8F0; padding: 1.5rem; border-radius: 0;">
                                    <button type="button" class="btn btn-lg" data-bs-dismiss="modal"
                                        style="background: linear-gradient(135deg, #FF6B6B 0%, #FF0000 100%); color: white; border: none; border-radius: 6px; padding: 0.75rem 2rem; font-weight: 700; letter-spacing: 0.5px;">
                                        Cancel
                                    </button>
                                    <button type="submit" class="btn btn-lg" id="saveWarehouseBtn"
                                        style="background: linear-gradient(135deg, #4ADE80 0%, #22C55E 100%); color: white; border: none; border-radius: 6px; padding: 0.75rem 2rem; font-weight: 700; letter-spacing: 0.5px;">
                                        Save
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div> -->


                    <!-- Progress Modal -->
                    <div id="progressModal" class="modal fade" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header bg-primary text-white">
                                    <h5 class="modal-title">Processing Data (Procesando datos)</h5>
                                </div>
                                <div class="modal-body">
                                    <div id="progress-container" class="mb-3"></div>
                                    <div id="error-container"></div>
                                    <div id="success-alert" class="alert alert-success" style="display:none">
                                        All sheets updated successfully! (¡Todas las hojas se actualizaron correctamente!)
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button id="cancelUploadBtn" class="btn btn-secondary">Cancel (Cancelar)</button>
                                    <button id="doneBtn" class="btn btn-primary" style="display:none">Done (Listo)</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- DataTable -->
                    <div class="table-responsive">
                        <table id="inventoryTable" class="table dt-responsive nowrap w-100">
                            <thead>
                                <tr>
                                    <th>IMAGE (IMAGEN)</th>
                                    <th>SKU (SKU)</th>
                                    <th>QUANTITY (CANTIDAD)</th>
                                    <th>WAREHOUSE (ALMACÉN)</th>
                                    <th>CONDITION / REMARKS (CONDICIÓN / OBS.)</th>
                                    <th>CREATED BY (CREADO POR)</th>
                                    <th>DATE (FECHA)</th>
                                </tr>
                            </thead>
                            <tbody id="inventory-table-body">
                                <!-- Rows will be dynamically inserted here -->
                            </tbody>
                        </table>
                    </div>
                    <!-- Rainbow Wave Loader -->
                    <div id="rainbow-loader" class="rainbow-loader">
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="loading-text">Loading Incoming Data… (Cargando datos de entradas…)</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">Return History (Historial de devoluciones)</h5>

                    <div class="row mb-3 g-2 align-items-end">
                        <div class="col-xl-4 col-lg-5 col-md-12"></div>
                        <div class="col-xl-3 col-lg-4 col-md-6">
                            <label for="filterWarehouseReturn_btn" class="form-label small fw-semibold mb-1">Warehouse filter (Filtro almacén)</label>
                            <div class="incoming-wh-dd-wrap position-relative">
                                <select id="filterWarehouseReturn" class="d-none incoming-wh-dd-native" aria-hidden="true" tabindex="-1">
                                    <option value="">All warehouses (Todos los almacenes)</option>
                                    @foreach($warehouses as $warehouse)
                                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                    @endforeach
                                </select>
                                <button type="button" id="filterWarehouseReturn_btn" class="form-select form-select-sm incoming-wh-dd-trigger warehouse-filter-select w-100 text-start d-flex align-items-center" aria-haspopup="listbox" aria-expanded="false">
                                    <span class="incoming-wh-dd-trigger-inner d-flex align-items-center min-w-0 flex-grow-1"></span>
                                </button>
                                <div class="incoming-wh-dd-panel list-group position-absolute top-100 start-0 end-0 d-none bg-white border rounded shadow-sm mt-1 py-1" role="listbox"></div>
                            </div>
                        </div>
                        <div class="col-xl-5 col-lg-12 col-md-6">
                            <label for="customSearchReturnHistory" class="form-label small fw-semibold mb-1 d-none d-md-block">&nbsp;</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" id="customSearchReturnHistory" class="form-control" placeholder="Search Return History (Buscar historial de devoluciones)">
                                <button class="btn btn-outline-secondary" type="button" id="clearSearchReturnHistory">Clear (Limpiar)</button>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table id="returnHistoryTable" class="table dt-responsive nowrap w-100">
                            <thead>
                                <tr>
                                    <th>IMAGE (IMAGEN)</th>
                                    <th>SKU (SKU)</th>
                                    <th>QUANTITY (CANTIDAD)</th>
                                    <th>WAREHOUSE (ALMACÉN)</th>
                                    <th>CONDITION / REMARKS (CONDICIÓN / OBS.)</th>
                                    <th>CREATED BY (CREADO POR)</th>
                                    <th>DATE (FECHA)</th>
                                </tr>
                            </thead>
                            <tbody id="return-history-table-body">
                            </tbody>
                        </table>
                    </div>
                    <div id="return-history-rainbow-loader" class="rainbow-loader">
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="loading-text">Loading Return History… (Cargando historial de devoluciones…)</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Popup Modal (Centered) -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="successModalLabel">
                        <i class="fas fa-check-circle me-2"></i>Success (Éxito)
                    </h5>
                </div>
                <div class="modal-body text-center py-4">
                    <div class="mb-3">
                        <i class="fas fa-check-circle text-success" style="font-size: 48px;"></i>
                    </div>
                    <p id="successModalMessage" class="mb-0" style="font-size: 16px; font-weight: 500;"></p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-success btn-lg px-5" id="successModalOkBtn">
                        <i class="fas fa-check me-2"></i>OK (Aceptar)
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <!-- Load jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>


    <script>


        document.addEventListener('DOMContentLoaded', function() {
            document.body.classList.add('incoming-pwa-page');
            if (window.matchMedia('(min-width: 768px)').matches) {
                document.body.style.zoom = "75%";
            }

            if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1')) {
                navigator.serviceWorker.register(@json(asset('service-worker-incoming.js'))).catch(function () { /* optional PWA */ });
            }

            // Show loader immediately
            document.getElementById('rainbow-loader').style.display = 'block';
            document.getElementById('return-history-rainbow-loader').style.display = 'block';

            // Store the loaded data globally
            let tableData = [];
            let returnHistoryTableData = [];

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
                                <div id="${sheet.id}-status" class="small text-muted mt-1">Initializing… (Iniciando…)</div>
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
                            statusEl.textContent = status || 'Completed successfully (Completado correctamente)';
                            statusEl.classList.add('text-success');
                            iconEl.innerHTML = '<i class="fas fa-check-circle text-success"></i>';
                        } else if (progress === 100) {
                            progressEl.classList.remove('progress-bar-animated');
                            progressEl.classList.add('bg-danger');
                            statusEl.textContent = status || 'Failed (Falló)';
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
                            statusEl.textContent = status || 'Processing… (Procesando…)';
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
                                ${successCount}/${totalCount} sheets updated successfully (hojas actualizadas correctamente)
                            </div>
                        `;
                        doneBtn.style.display = 'block';
                    }
                };
            }

            function initializeTable() {
                setupIncomingWarehouseDropdowns();
                loadData();
                loadReturnHistoryData();
                setupSearch();
                setupReturnHistorySearch();
                setupAddWarehouseModal();
                setupProgressModal();
                setupEditDeleteButtons();
                // setupEditButtons();
            }
            

            $(document).ready(function () {

                // Helper: AJAX with retry (supports FormData via extraAjaxSettings)
                function ajaxWithRetry(url, method, data, maxRetries = 4, extraAjaxSettings = {}) {
                    return new Promise((resolve, reject) => {
                        let attempt = 0;

                        function makeRequest() {
                            attempt++;
                            console.log(`[Attempt ${attempt}/${maxRetries}] ${method} ${url}`);

                            const base = {
                                url: url,
                                method: method,
                                data: data,
                                timeout: 95000,
                                success: function (response) {
                                    console.log(`✓ Success on attempt ${attempt}`, response);
                                    resolve(response);
                                },
                                error: function (xhr, status, error) {
                                    const errorType = xhr.status || status;
                                    const shouldRetry = shouldRetryRequest(errorType);

                                    console.warn(`✗ Error on attempt ${attempt}:`, {
                                        status: xhr.status,
                                        type: status,
                                        shouldRetry: shouldRetry
                                    });

                                    if (shouldRetry && attempt < maxRetries) {
                                        const waitTime = getWaitTime(errorType, attempt);
                                        console.log(`⏳ Retrying in ${waitTime}ms...`);
                                        setTimeout(makeRequest, waitTime);
                                    } else {
                                        reject({
                                            status: xhr.status,
                                            response: xhr.responseJSON || { error: error },
                                            attempt: attempt
                                        });
                                    }
                                }
                            };
                            $.ajax(Object.assign(base, extraAjaxSettings));
                        }

                        makeRequest();
                    });
                }

                // Determine if we should retry based on error type
                function shouldRetryRequest(errorCode) {
                    // Rate limit error
                    if (errorCode === 429) return true;
                    // Server errors
                    if (errorCode >= 500 && errorCode <= 599) return true;
                    // Timeout errors
                    if (errorCode === 0 || errorCode === 'timeout') return true;
                    // Bad gateway / Service unavailable
                    if (errorCode === 502 || errorCode === 503 || errorCode === 504) return true;
                    return false;
                }

                // Get wait time based on error type and attempt
                function getWaitTime(errorCode, attempt) {
                    if (errorCode === 429) {
                        // Rate limit: longer wait times
                        const waits = [3000, 5000, 8000];
                        return waits[Math.min(attempt - 1, waits.length - 1)];
                    } else if (errorCode >= 500) {
                        // Server error: exponential backoff
                        const waits = [2000, 4000, 6000];
                        return waits[Math.min(attempt - 1, waits.length - 1)];
                    } else {
                        // Timeout: moderate wait times
                        const waits = [2000, 4000, 6000];
                        return waits[Math.min(attempt - 1, waits.length - 1)];
                    }
                }

                const incomingLookupUrl = @json(route('incoming.sku.lookup'));
                const skuSuggestUrl = @json(route('incoming.return.sku.suggest'));
                const csrfToken = $('meta[name="csrf-token"]').attr('content');
                let incomingPhotoFiles = [];
                let incomingPhoto2Files = [];
                let incomingPhoto3Files = [];
                let html5QrCodeInstance = null;

                function updateOfflineBanner() {
                    const offline = typeof navigator !== 'undefined' && navigator.onLine === false;
                    $('#incoming-offline-banner').toggleClass('d-none', !offline);
                }
                updateOfflineBanner();
                window.addEventListener('online', updateOfflineBanner);
                window.addEventListener('offline', updateOfflineBanner);

                function renderPhotoThumbStrip(files, $wrap) {
                    $wrap.empty();
                    files.forEach(function (file, idx) {
                        if (!file._url) file._url = URL.createObjectURL(file);
                        const div = $('<div class="incoming-thumb-wrap"/>');
                        div.append($('<img/>').attr('src', file._url).attr('alt', ''));
                        const rm = $('<button type="button" class="btn btn-danger btn-sm" aria-label="Remove"/>').html('&times;');
                        rm.on('click', function () {
                            if (file._url) URL.revokeObjectURL(file._url);
                            files.splice(idx, 1);
                            renderPhotoThumbStrip(files, $wrap);
                        });
                        div.append(rm);
                        $wrap.append(div);
                    });
                }

                function clearIncomingPhotos() {
                    [incomingPhotoFiles, incomingPhoto2Files, incomingPhoto3Files].forEach(function (arr) {
                        arr.forEach(function (f) {
                            if (f._url) URL.revokeObjectURL(f._url);
                        });
                        arr.length = 0;
                    });
                    $('#incoming-photo-thumbs').empty();
                    $('#incoming-photo-thumbs-2').empty();
                    $('#incoming-photo-thumbs-3').empty();
                    $('#incoming-photo-input').val('');
                    $('#incoming-photo-input-2').val('');
                    $('#incoming-photo-input-3').val('');
                }

                function renderIncomingPhotoThumbs() {
                    renderPhotoThumbStrip(incomingPhotoFiles, $('#incoming-photo-thumbs'));
                }

                function renderIncomingPhotoThumbs2() {
                    renderPhotoThumbStrip(incomingPhoto2Files, $('#incoming-photo-thumbs-2'));
                }

                function renderIncomingPhotoThumbs3() {
                    renderPhotoThumbStrip(incomingPhoto3Files, $('#incoming-photo-thumbs-3'));
                }

                $('#btnAddPhotos').on('click', function () {
                    $('#incoming-photo-input').trigger('click');
                });

                $('#btnAddPhotos2').on('click', function () {
                    $('#incoming-photo-input-2').trigger('click');
                });

                $('#btnAddPhotos3').on('click', function () {
                    $('#incoming-photo-input-3').trigger('click');
                });

                $('#incoming-photo-input').on('change', function () {
                    const files = Array.from(this.files || []);
                    files.forEach(function (f) {
                        if (f.type.indexOf('image/') === 0) incomingPhotoFiles.push(f);
                    });
                    $(this).val('');
                    renderIncomingPhotoThumbs();
                });

                $('#incoming-photo-input-2').on('change', function () {
                    const files = Array.from(this.files || []);
                    files.forEach(function (f) {
                        if (f.type.indexOf('image/') === 0) incomingPhoto2Files.push(f);
                    });
                    $(this).val('');
                    renderIncomingPhotoThumbs2();
                });

                $('#incoming-photo-input-3').on('change', function () {
                    const files = Array.from(this.files || []);
                    files.forEach(function (f) {
                        if (f.type.indexOf('image/') === 0) incomingPhoto3Files.push(f);
                    });
                    $(this).val('');
                    renderIncomingPhotoThumbs3();
                });

                function showIncomingToast(msg) {
                    const el = document.getElementById('incomingToast');
                    const body = document.getElementById('incomingToastBody');
                    if (!el || !body || !window.bootstrap) return;
                    body.textContent = msg;
                    const t = bootstrap.Toast.getOrCreateInstance(el, { delay: 4000 });
                    t.show();
                }

                function fetchLookupForSku(raw) {
                    const q = (raw || '').trim();
                    const hint = $('#sku-product-hint');
                    if (!q) {
                        hint.addClass('d-none').text('');
                        return;
                    }
                    $.get(incomingLookupUrl, { sku: q })
                        .done(function (res) {
                            if (res.found) {
                                $('#sku').val(res.sku);
                                hint.removeClass('d-none').html(
                                    '<i class="fas fa-check-circle text-success me-1"></i>' +
                                    escapeHtml(res.title || res.sku) +
                                    (res.parent ? ' · Parent (Padre): ' + escapeHtml(res.parent) : '')
                                );
                            } else {
                                hint.removeClass('d-none').html(
                                    '<i class="fas fa-info-circle me-1"></i>' + escapeHtml(res.message || 'SKU not in product master (will still try Shopify). (SKU no está en el catálogo maestro; se intentará en Shopify.)')
                                );
                            }
                        })
                        .fail(function () {
                            hint.addClass('d-none').text('');
                        });
                }

                function hideSkuSuggestions() {
                    $('#sku-suggest-list').addClass('d-none').empty();
                }

                function renderSkuSuggestions(items) {
                    const $list = $('#sku-suggest-list');
                    $list.empty();
                    if (!items || !items.length) {
                        $list.addClass('d-none');
                        return;
                    }
                    items.forEach(function (it) {
                        const $btn = $('<button type="button" class="list-group-item list-group-item-action text-start incoming-sku-suggest-item py-2"/>');
                        const $row = $('<div class="d-flex gap-2 align-items-center w-100"/>');
                        if (it.image_url) {
                            const $img = $('<img class="incoming-sku-suggest-thumb rounded border" alt="" loading="lazy"/>')
                                .attr('src', it.image_url)
                                .on('error', function () {
                                    $(this).replaceWith(
                                        $('<div class="incoming-sku-suggest-thumb-placeholder rounded border bg-light d-flex align-items-center justify-content-center text-muted"/>').text('—')
                                    );
                                });
                            $row.append($img);
                        } else {
                            $row.append(
                                $('<div class="incoming-sku-suggest-thumb-placeholder rounded border bg-light d-flex align-items-center justify-content-center text-muted"/>').text('—')
                            );
                        }
                        const $text = $('<div class="flex-grow-1 min-w-0"/>');
                        $text.append($('<div class="fw-semibold"/>').text(it.sku));
                        if (it.label && String(it.label) !== String(it.sku)) {
                            $text.append($('<div class="small text-muted text-truncate" style="max-width:100%"/>').text(it.label));
                        }
                        if (it.parent) {
                            $text.append($('<div class="small text-muted"/>').text('Parent (Padre): ' + it.parent));
                        }
                        $row.append($text);
                        $btn.append($row);
                        $btn.on('mousedown', function (ev) {
                            ev.preventDefault();
                            $('#sku').val(it.sku);
                            hideSkuSuggestions();
                            fetchLookupForSku(it.sku);
                        });
                        $list.append($btn);
                    });
                    $list.removeClass('d-none');
                }

                function fetchSkuSuggestions(raw) {
                    const q = (raw || '').trim();
                    if (q.length < 1) {
                        hideSkuSuggestions();
                        return;
                    }
                    $.get(skuSuggestUrl, { q: q, limit: 15 })
                        .done(function (res) {
                            renderSkuSuggestions(res.items || []);
                        })
                        .fail(function () {
                            hideSkuSuggestions();
                        });
                }

                let skuLookupTimer = null;
                let skuSuggestTimer = null;
                $('#sku').on('input blur', function () {
                    clearTimeout(skuLookupTimer);
                    skuLookupTimer = setTimeout(function () {
                        fetchLookupForSku($('#sku').val());
                    }, 400);
                });
                $('#sku').on('input', function () {
                    clearTimeout(skuSuggestTimer);
                    skuSuggestTimer = setTimeout(function () {
                        fetchSkuSuggestions($('#sku').val());
                    }, 200);
                });
                $('#sku').on('focus', function () {
                    fetchSkuSuggestions($('#sku').val());
                });
                $('#sku').on('blur', function () {
                    setTimeout(hideSkuSuggestions, 250);
                });
                // USB scanners often send Enter; match WMS scan — lookup immediately (no debounce wait)
                $('#sku').on('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        clearTimeout(skuLookupTimer);
                        clearTimeout(skuSuggestTimer);
                        hideSkuSuggestions();
                        fetchLookupForSku($('#sku').val());
                    }
                    if (e.key === 'Escape') {
                        hideSkuSuggestions();
                    }
                });

                function stopBarcodeScanner() {
                    if (!html5QrCodeInstance) return;
                    const instance = html5QrCodeInstance;
                    html5QrCodeInstance = null;
                    instance.stop().then(function () {
                        instance.clear();
                    }).catch(function () {
                        try { instance.clear(); } catch (e) { /* ignore */ }
                    });
                }

                $('#barcodeScannerModal').on('hidden.bs.modal', function () {
                    stopBarcodeScanner();
                    $('#barcode-scan-status').text('');
                });

                $('#btnScanBarcode').on('click', function () {
                    if (typeof Html5Qrcode === 'undefined') {
                        $('#incoming-errors').html('<div class="alert alert-warning mb-0">Barcode scanner library did not load. Refresh the page or type the SKU manually. <span class="d-block mt-1 small">(No se cargó el escáner. Actualice la página o escriba el SKU manualmente.)</span></div>');
                        return;
                    }
                    $('#incoming-errors').html('');
                    $('#barcode-scan-status').text('Starting camera… (Iniciando cámara…)');
                    const modalEl = document.getElementById('barcodeScannerModal');
                    const bsScanModal = bootstrap.Modal.getOrCreateInstance(modalEl);
                    $(modalEl).one('shown.bs.modal', function () {
                        const regionId = 'barcode-reader';
                        html5QrCodeInstance = new Html5Qrcode(regionId);
                        const config = { fps: 10, qrbox: function (vw, vh) {
                            const w = Math.min(280, vw * 0.9);
                            const h = Math.min(160, vh * 0.35);
                            return { width: w, height: h };
                        }};
                        html5QrCodeInstance.start(
                            { facingMode: 'environment' },
                            config,
                            function (decodedText) {
                                const text = (decodedText || '').trim();
                                if (!text) return;
                                $('#sku').val(text);
                                hideSkuSuggestions();
                                fetchLookupForSku(text);
                                bsScanModal.hide();
                            },
                            function () { /* frame — ignore */ }
                        ).then(function () {
                            $('#barcode-scan-status').text('Point the camera at a barcode. (Apunte la cámara al código de barras.)');
                        }).catch(function (err) {
                            $('#barcode-scan-status').text(
                                'Camera unavailable (' + (err && err.message ? err.message : 'permission or HTTPS') + '). Type the SKU or allow camera access. (Cámara no disponible. Escriba el SKU o permita el acceso a la cámara.)'
                            );
                        });
                    });
                    bsScanModal.show();
                });

                // Prevent duplicate form submissions
                let isSubmitting = false;

                $('#incomingReturnForm').off('submit').on('submit', function (e) {
                    e.preventDefault();

                    if (isSubmitting) {
                        console.log('Form submission already in progress, ignoring duplicate request');
                        return false;
                    }

                    if (typeof navigator !== 'undefined' && navigator.onLine === false) {
                        $('#incoming-errors').html('<div class="alert alert-danger mb-0">You are offline. Connect to the internet to submit incoming return stock. <span class="d-block mt-1 small">(Sin conexión. Conéctese a internet para enviar la devolución entrante.)</span></div>');
                        return false;
                    }

                    $('.error-message').remove();
                    $('#incomingReturnForm input, #incomingReturnForm select, #incomingReturnForm textarea').removeClass('is-invalid');

                    let hasError = false;
                    const fields = [
                        { id: '#sku', name: 'SKU (SKU)' },
                        { id: '#qty', name: 'Quantity (Cantidad)' },
                        { id: '#warehouse_id', name: 'Warehouse (Almacén)' },
                        { id: '#reason', name: 'Condition / Remarks (Condición / observaciones)' },
                    ];

                    fields.forEach(function (f) {
                        const el = $(f.id);
                        const v = (el.val() || '').trim();
                        if (!v || v === 'Select Warehouse') {
                            hasError = true;
                            el.addClass('is-invalid');
                            el.after('<div class="text-danger error-message">' + f.name + ' is required. (Obligatorio.)</div>');
                        }
                    });

                    if (hasError) return;

                    isSubmitting = true;
                    const submitBtn = $(this).find('button[type="submit"]');
                    const originalBtnText = submitBtn.html();
                    submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Processing… (Procesando…)');

                    const allPickedPhotos = incomingPhotoFiles.concat(incomingPhoto2Files, incomingPhoto3Files);
                    if (allPickedPhotos.length > 20) {
                        $('#incoming-errors').html('<div class="alert alert-danger mb-0">Too many images (max 20). Reduce photos across Photo 1–3. <span class="d-block mt-1 small">(Demasiadas imágenes: máximo 20 en total.)</span></div>');
                        submitBtn.prop('disabled', false).html(originalBtnText);
                        isSubmitting = false;
                        return false;
                    }

                    const fd = new FormData(this);
                    fd.set('_token', csrfToken);
                    allPickedPhotos.forEach(function (file) {
                        fd.append('images[]', file, file.name);
                    });

                    const overlay = document.createElement('div');
                    overlay.id = 'processing-overlay';
                    overlay.innerHTML = `
                        <div style="
                            position:fixed;
                            top:0; left:0;
                            width:100%; height:100%;
                            background:rgba(0,0,0,0.6);
                            color:white;
                            display:flex;
                            align-items:center;
                            justify-content:center;
                            flex-direction:column;
                            z-index:9999;
                            font-size:20px;
                        ">
                            <div style="font-size:28px;">🚀 Processing incoming return… (Procesando devolución entrante…)</div>
                            <small style="margin-top:10px;font-size:16px;">
                                Please wait while we update Shopify inventory. (Espere mientras actualizamos el inventario en Shopify.)<br>
                                <span id="retry-status" style="font-size: 14px; opacity: 0.8;"></span>
                            </small>
                        </div>`;
                    document.body.appendChild(overlay);

                    ajaxWithRetry('{{ route("incoming.return.store") }}', 'POST', fd, 4, {
                        processData: false,
                        contentType: false,
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    })
                        .then(function (response) {
                            document.getElementById('processing-overlay')?.remove();

                            const message = response.message || 'Incoming return stored and updated in Shopify successfully!';

                            $('#incoming-errors').html('');
                            $('#addWarehouseModal').modal('hide');
                            $('#incomingReturnForm')[0].reset();
                            if (typeof incomingWhDdSyncAll === 'function') incomingWhDdSyncAll();
                            hideSkuSuggestions();
                            $('#sku-product-hint').addClass('d-none').text('');
                            clearIncomingPhotos();

                            submitBtn.prop('disabled', false).html(originalBtnText);
                            isSubmitting = false;

                            showIncomingToast(message);
                            showSuccessPopup(message);
                        })
                        .catch(function (error) {
                            document.getElementById("processing-overlay")?.remove();

                            console.error('Final error after retries:', error);

                            // Parse error message and show prominently in modal
                            let errorMsg = 'Error storing incoming return. (Error al guardar la devolución entrante.)';

                            if (error.response && error.response.error) {
                                errorMsg = error.response.error;
                                if (error.response.details) {
                                    errorMsg += ' — ' + error.response.details;
                                }
                            } else if (error.status === 0) {
                                errorMsg = 'Network/timeout error. Please try again. (Error de red o tiempo de espera. Inténtelo de nuevo.)';
                            }

                            // Display big red error inside modal area
                            $('#incoming-errors').html(`<div style="color:#b00020;font-size:20px;font-weight:800">${escapeHtml(errorMsg)}<br><small style=\"font-size:13px;color:#6b0b15\">(Attempted ${error.attempt} times) (Intentos: ${error.attempt})</small></div>`);

                            // Reset submit button on error
                            submitBtn.prop('disabled', false).html(originalBtnText);
                            isSubmitting = false;

                            // Also log details to console for debugging
                            console.log('Error details:', {
                                attempt: error.attempt,
                                status: error.status,
                                response: error.response
                            });
                        });
                });

                $(document).on('click', '#openAddWarehouseModal', function () {
                    $('#incomingReturnForm')[0].reset();
                    if (typeof incomingWhDdSyncAll === 'function') incomingWhDdSyncAll();
                    hideSkuSuggestions();
                    $('#warehouseId').val('');
                    $('#incomingModalLabel').text('Create Incoming Return (Crear devolución entrante)');
                    $('#warehouseModalLabel').text('Create Incoming Return (Crear devolución entrante)');
                    $('#incoming-errors').html('');
                    $('#sku-product-hint').addClass('d-none').text('');
                    clearIncomingPhotos();
                    isSubmitting = false;
                    const submitBtn = $('#incomingReturnForm').find('button[type="submit"]');
                    submitBtn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Incoming Return (Guardar devolución entrante)');
                    updateOfflineBanner();
                    $('#addWarehouseModal').modal('show');
                });

                // Reset submission flag when modal is closed
                $('#addWarehouseModal').on('hidden.bs.modal', function () {
                    isSubmitting = false;
                    hideSkuSuggestions();
                    const submitBtn = $('#incomingReturnForm').find('button[type="submit"]');
                    submitBtn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Incoming Return (Guardar devolución entrante)');
                    $('#incoming-errors').html('');
                });

            });


            function warehouseThemeKeyFromName(name) {
                const raw = String(name || '').trim().toLowerCase().replace(/\s+/g, ' ').replace(/\u00a0/g, ' ');
                const compact = raw.replace(/\s/g, '');
                // Match plain labels and common "* Godown" warehouse names from the DB
                if (raw === 'main' || raw === 'main godown') return 'main';
                if (raw === 'trash' || raw === 'trash godown') return 'trash';
                if (raw === 'open box' || raw === 'open box godown' || compact === 'openbox') return 'openbox';
                return null;
            }

            function syncWarehouseFilterSelectTheme(selectId) {
                const el = document.getElementById(selectId);
                if (!el) return;
                el.classList.remove('warehouse-filter-theme-main', 'warehouse-filter-theme-trash', 'warehouse-filter-theme-openbox');
                const wrap = el.closest('.incoming-wh-dd-wrap');
                const btn = wrap ? wrap.querySelector('.incoming-wh-dd-trigger') : null;
                if (btn) {
                    btn.classList.remove('warehouse-filter-theme-main', 'warehouse-filter-theme-trash', 'warehouse-filter-theme-openbox');
                }
                if (!String(el.value || '').trim()) return;
                const opt = el.options[el.selectedIndex];
                const key = warehouseThemeKeyFromName(opt ? opt.text : '');
                if (key && btn) btn.classList.add('warehouse-filter-theme-' + key);
            }

            function incomingWarehouseCellClass(warehouseName) {
                const key = warehouseThemeKeyFromName(warehouseName);
                if (key) return 'incoming-wh-cell incoming-wh-' + key;
                return 'incoming-wh-cell';
            }

            function applyMainTableFilters() {
                syncWarehouseFilterSelectTheme('filterWarehouseMain');
                const whEl = document.getElementById('filterWarehouseMain');
                const searchEl = document.getElementById('customSearch');
                const wh = whEl ? String(whEl.value || '') : '';
                const searchTerm = searchEl ? String(searchEl.value || '').toLowerCase().trim() : '';

                let rows = tableData.slice();
                if (wh !== '') {
                    rows = rows.filter(item => String(item.warehouse_id ?? '') === wh);
                }
                if (searchTerm) {
                    rows = rows.filter(item =>
                        Object.values(item).some(value =>
                            String(value).toLowerCase().includes(searchTerm)
                        )
                    );
                }
                renderTable(rows);
            }

            function applyReturnHistoryFilters() {
                syncWarehouseFilterSelectTheme('filterWarehouseReturn');
                const whEl = document.getElementById('filterWarehouseReturn');
                const searchEl = document.getElementById('customSearchReturnHistory');
                const wh = whEl ? String(whEl.value || '') : '';
                const searchTerm = searchEl ? String(searchEl.value || '').toLowerCase().trim() : '';

                let rows = returnHistoryTableData.slice();
                if (wh !== '') {
                    rows = rows.filter(item => String(item.warehouse_id ?? '') === wh);
                }
                if (searchTerm) {
                    rows = rows.filter(item =>
                        Object.values(item).some(value =>
                            String(value).toLowerCase().includes(searchTerm)
                        )
                    );
                }
                renderReturnHistoryTable(rows);
            }

            function loadData() {
                $.ajax({
                    url: '/incoming-data-list',
                    method: 'GET',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    beforeSend: function () {
                        $('#rainbow-loader').show(); 
                    },
                    success: function (response) {
                        tableData = response.data || [];
                        applyMainTableFilters();
                        $('#rainbow-loader').hide();
                    },
                    error: function(xhr) {
                        console.error("Load error:", xhr.responseText);
                        $('#rainbow-loader').hide();
                    }
                });
            }

            function loadReturnHistoryData() {
                $.ajax({
                    url: '/incoming-return-history-list',
                    method: 'GET',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    beforeSend: function () {
                        $('#return-history-rainbow-loader').show();
                    },
                    success: function (response) {
                        returnHistoryTableData = response.data || [];
                        applyReturnHistoryFilters();
                        $('#return-history-rainbow-loader').hide();
                    },
                    error: function(xhr) {
                        console.error("Return history load error:", xhr.responseText);
                        $('#return-history-rainbow-loader').hide();
                    }
                });
            }

            
            function renderTable(data) {
                const tbody = document.getElementById('inventory-table-body');
                tbody.innerHTML = '';

                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center">No records found (No se encontraron registros)</td></tr>';
                    return;
                }

                data.forEach(item => {
                    const row = document.createElement('tr');
                    const imgUrl = String(item.image_url || '').trim();
                    const imgCell = imgUrl
                        ? `<td class="text-center align-middle"><img class="incoming-table-thumb" src="${escapeHtml(imgUrl)}" alt="" loading="lazy" onerror="this.classList.add('d-none'); var s=this.nextElementSibling; if(s) s.classList.remove('d-none');"><span class="text-muted small d-none">—</span></td>`
                        : `<td class="text-center align-middle"><span class="text-muted small">—</span></td>`;

                    const whName = String(item.warehouse_name ?? '-');
                    row.innerHTML = `
                        ${imgCell}
                        <td>${escapeHtml(String(item.sku ?? '-'))}</td>
                        <td>${escapeHtml(String(item.verified_stock ?? '-'))}</td>
                        <td class="${incomingWarehouseCellClass(whName)}">${escapeHtml(whName)}</td>
                        <td>${escapeHtml(String(item.reason ?? '-'))}</td>
                        <td>${escapeHtml(String(item.approved_by ?? '-'))}</td>
                        <td>${escapeHtml(String(item.approved_at ?? '-'))}</td>
                    `;

                    tbody.appendChild(row);
                });
            }

            function renderReturnHistoryTable(data) {
                const tbody = document.getElementById('return-history-table-body');
                if (!tbody) return;
                tbody.innerHTML = '';

                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center">No records found (No se encontraron registros)</td></tr>';
                    return;
                }

                data.forEach(item => {
                    const row = document.createElement('tr');
                    const imgUrl = String(item.image_url || '').trim();
                    const imgCell = imgUrl
                        ? `<td class="text-center align-middle"><img class="incoming-table-thumb" src="${escapeHtml(imgUrl)}" alt="" loading="lazy" onerror="this.classList.add('d-none'); var s=this.nextElementSibling; if(s) s.classList.remove('d-none');"><span class="text-muted small d-none">—</span></td>`
                        : `<td class="text-center align-middle"><span class="text-muted small">—</span></td>`;

                    const whName = String(item.warehouse_name ?? '-');
                    row.innerHTML = `
                        ${imgCell}
                        <td>${escapeHtml(String(item.sku ?? '-'))}</td>
                        <td>${escapeHtml(String(item.verified_stock ?? '-'))}</td>
                        <td class="${incomingWarehouseCellClass(whName)}">${escapeHtml(whName)}</td>
                        <td>${escapeHtml(String(item.reason ?? '-'))}</td>
                        <td>${escapeHtml(String(item.approved_by ?? '-'))}</td>
                        <td>${escapeHtml(String(item.approved_at ?? '-'))}</td>
                    `;
                    tbody.appendChild(row);
                });
            }



            function setupSearch() {
                const searchInput = document.getElementById('customSearch');
                const clearButton = document.getElementById('clearSearch');
                const whSel = document.getElementById('filterWarehouseMain');
                if (!searchInput || !clearButton || !whSel) return;

                $(searchInput).off('.incomingReturnMain');
                $(clearButton).off('.incomingReturnMain');
                $(whSel).off('.incomingReturnMain');

                $(searchInput).on('input.incomingReturnMain', debounce(function () {
                    applyMainTableFilters();
                }, 300));

                $(clearButton).on('click.incomingReturnMain', function () {
                    searchInput.value = '';
                    applyMainTableFilters();
                });

                $(whSel).on('change.incomingReturnMain', function () {
                    applyMainTableFilters();
                });
            }

            function setupReturnHistorySearch() {
                const searchInput = document.getElementById('customSearchReturnHistory');
                const clearButton = document.getElementById('clearSearchReturnHistory');
                const whSel = document.getElementById('filterWarehouseReturn');
                if (!searchInput || !clearButton || !whSel) return;

                $(searchInput).off('.incomingReturnRH');
                $(clearButton).off('.incomingReturnRH');
                $(whSel).off('.incomingReturnRH');

                $(searchInput).on('input.incomingReturnRH', debounce(function () {
                    applyReturnHistoryFilters();
                }, 300));

                $(clearButton).on('click.incomingReturnRH', function () {
                    searchInput.value = '';
                    applyReturnHistoryFilters();
                });

                $(whSel).on('change.incomingReturnRH', function () {
                    applyReturnHistoryFilters();
                });
            }


            function setupAddWarehouseModal() {
                const modal = document.getElementById('addProductModal');
                const saveBtn = document.getElementById('saveProductBtn');
                const refreshParentsBtn = document.getElementById('refreshParents');

                $(saveBtn).off('click');

            }

            function setupEditDeleteButtons() {
                // EDIT BUTTON
                $(document).on('click', '.edit-btn', function () {
                    const id = $(this).data('id');
                    const warehouse = tableData.find(w => w.id == id);

                    if (warehouse) {
                        $('#warehouseModalLabel').text('Edit Warehouse (Editar almacén)');
                        $('#warehouseId').val(warehouse.id);
                        $('#warehouseName').val(warehouse.name);
                        $('#warehouseGroup').val(warehouse.group).trigger('change');
                        $('#warehouseLocation').val(warehouse.location);
                        $('#addWarehouseModal').modal('show');
                    }
                });

                // DELETE BUTTON
                $(document).on('click', '.delete-btn', function () {
                    const id = $(this).data('id');

                    if (confirm('Are you sure you want to delete this warehouse? (¿Seguro que desea eliminar este almacén?)')) {
                        $.ajax({
                            url: `/warehouses/${id}`,
                            type: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            success: function () {
                                loadData(); // Refresh table
                            },
                            error: function (xhr) {
                                alert('Failed to delete warehouse. (No se pudo eliminar el almacén.)');
                                console.error(xhr.responseText);
                            }
                        });
                    }
                });
            }


            function deleteWarehouse(id) {
                $.ajax({
                    url: `/warehouses/${id}`,
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function (response) {
                        loadData(); // Refresh table
                    },
                    error: function () {
                        alert("Failed to delete warehouse. (No se pudo eliminar el almacén.)");
                    }
                });
            }


            function validateProductForm() {
                let isValid = true;
                const requiredFields = ['labelQty', 'cps', 'ship', 'wtAct', 'wtDecl', 'w', 'l', 'h'];

                requiredFields.forEach(id => {
                    const field = document.getElementById(id);
                    if (!field.value.trim()) {
                        showFieldError(field, 'This field is required. (Este campo es obligatorio.)');
                        isValid = false;
                    } else if (isNaN(field.value)) {
                        showFieldError(field, 'Must be a number. (Debe ser un número.)');
                        isValid = false;
                    } else {
                        clearFieldError(field);
                    }
                });

                return isValid;
            }

            function getFormData() {
                return {
                    SKU: document.getElementById('sku').value,
                    Parent: document.getElementById('parent').value || '',
                    Label_QTY: document.getElementById('labelQty').value,
                    CP: document.getElementById('cps').value,
                    SHIP: document.getElementById('ship').value,
                    WT_ACT: document.getElementById('wtAct').value,
                    WT_DECL: document.getElementById('wtDecl').value,
                    W: document.getElementById('w').value,
                    L: document.getElementById('l').value,
                    H: document.getElementById('h').value,
                    '5C': document.getElementById('l2Url').value || '',
                    pcbox: document.getElementById('pcbox').value || '',
                    l1: document.getElementById('l1').value || '',
                    b: document.getElementById('b').value || '',
                    h1: document.getElementById('h1').value || '',
                    UPC: document.getElementById('upc').value || ''
                };
            }

            async function saveProduct(formData) {
                try {
                    const sheets = [{
                            name: 'ProductMaster',
                            displayName: 'Product Master',
                            id: 'product-master'
                        },
                        {
                            name: 'Amazon',
                            displayName: 'Amazon',
                            id: 'amazon'
                        },
                        {
                            name: 'Ebay',
                            displayName: 'Ebay',
                            id: 'ebay'
                        },
                        {
                            name: 'ShopifyB2C',
                            displayName: 'Shopify B2C',
                            id: 'shopifyb2c'
                        },
                        {
                            name: 'Mecy',
                            displayName: 'Mecy',
                            id: 'mecy'
                        },
                        {
                            name: 'NeweggB2C',
                            displayName: 'Newegg B2C',
                            id: 'neweggb2c'
                        }
                    ];

                    showUploadProgress(sheets);
                    const saveBtn = document.getElementById('saveProductBtn');
                    saveBtn.disabled = true;
                    saveBtn.innerHTML = formData.operation === 'update' ?
                        '<i class="fas fa-spinner fa-spin me-2"></i> Updating… (Actualizando…)' :
                        '<i class="fas fa-spinner fa-spin me-2"></i> Saving… (Guardando…)';

                    currentUpload = new AbortController();
                    const response = await fetch('/api/sync-sheets', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(formData),
                        signal: currentUpload.signal
                    });

                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        const textResponse = await response.text();
                        throw new Error('Server returned an HTML error page. Please check the server logs. (El servidor devolvió HTML. Revise los registros.)');
                    }

                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.message || `Server returned status ${response.status}`);
                    }

                    let successCount = 0;
                    sheets.forEach(sheet => {
                        const result = data.results[sheet.name];
                        if (result?.success) {
                            updateUploadProgress(sheet.id, 100, 'Completed successfully (Completado correctamente)', true);
                            successCount++;
                        } else {
                            updateUploadProgress(sheet.id, 100, 'Failed (Falló)', false, result?.message);
                        }
                    });

                    completeUpload(successCount, sheets.length);

                    if (successCount === sheets.length) {
                        showAlert('success', 'All sheets updated successfully! (¡Todas las hojas se actualizaron!)');
                        return true;
                    } else {
                        showAlert('warning', `${successCount}/${sheets.length} sheets updated successfully (${successCount}/${sheets.length} hojas actualizadas)`);
                        return false;
                    }
                } catch (error) {
                    let errorMessage = error.message;
                    if (error.name === 'AbortError') {
                        errorMessage = 'Request was cancelled. (Solicitud cancelada.)';
                    } else if (error.message.includes('HTML error page')) {
                        errorMessage = 'Server error occurred. Please try again or contact support. (Error del servidor. Inténtelo de nuevo o contacte soporte.)';
                    }

                    showAlert('danger', errorMessage);
                    updateUploadProgress('product-master', 100, 'Failed', false, errorMessage);
                    completeUpload(0, 1);
                    return false;
                } finally {
                    currentUpload = null;
                    const saveBtn = document.getElementById('saveProductBtn');
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = formData.operation === 'update' ?
                        '<i class="fas fa-save me-2"></i> Update Product (Actualizar producto)' :
                        '<i class="fas fa-save me-2"></i> Save Product (Guardar producto)';
                }
            }

            function resetProductForm() {
                document.getElementById('incomingReturnForm').reset();
                if (typeof incomingWhDdSyncAll === 'function') incomingWhDdSyncAll();

                document.querySelectorAll('.is-invalid').forEach(el => {
                    el.classList.remove('is-invalid');
                    const feedback = el.closest('.form-group')?.querySelector('.invalid-feedback');
                    if (feedback) feedback.textContent = '';
                });
                document.getElementById('form-errors').innerHTML = '';

                const saveBtn = document.getElementById('saveProductBtn');
                const newSaveBtn = saveBtn.cloneNode(true);
                saveBtn.parentNode.replaceChild(newSaveBtn, saveBtn);

                newSaveBtn.innerHTML = '<i class="fas fa-save me-2"></i> Save Product (Guardar producto)';
                newSaveBtn.onclick = async function() {
                    if (!validateProductForm()) return;

                    const formData = getFormData();
                    formData.operation = 'create';

                    // Display the data being sent to the server
                    console.log('Data being sent to server:\n' + JSON.stringify(formData, null, 2));

                    const success = await saveProduct(formData);
                    if (success) {
                        bootstrap.Modal.getInstance(document.getElementById('addProductModal')).hide();
                        loadData();
                    }
                };

                newSaveBtn.removeAttribute('data-original-sku');
                newSaveBtn.removeAttribute('data-original-parent');
            }


            function editProduct(product) {
                const modal = new bootstrap.Modal(document.getElementById('addProductModal'));
                const saveBtn = document.getElementById('saveProductBtn');

                $(saveBtn).off('click');
                const newSaveBtn = saveBtn.cloneNode(true);
                saveBtn.parentNode.replaceChild(newSaveBtn, saveBtn);

                newSaveBtn.setAttribute('data-original-sku', product.SKU || '');
                newSaveBtn.setAttribute('data-original-parent', product.Parent || '');

                newSaveBtn.innerHTML = '<i class="fas fa-save me-2"></i> Update Product (Actualizar producto)';
                newSaveBtn.addEventListener('click', async function handleUpdate() {
                    if (!validateProductForm()) return;

                    const formData = getFormData();
                    formData.operation = 'update';
                    formData.original_sku = newSaveBtn.getAttribute('data-original-sku');
                    formData.original_parent = newSaveBtn.getAttribute('data-original-parent');

                    // Display the data being sent to the server
                    console.log('Data being sent to server:\n' + JSON.stringify(formData, null, 2));

                    const success = await saveProduct(formData);
                    if (success) {
                        bootstrap.Modal.getInstance(document.getElementById('addProductModal')).hide();
                        loadData();
                        resetProductForm();
                    }
                });

                const fields = {
                    sku: product.SKU || '',
                    parent: product.Parent || '',
                    labelQty: product['Label QTY'] || '1',
                    cps: product.CP || '',
                    ship: product.SHIP || '',
                    wtAct: product['WT ACT'] || product.weight_actual || '',
                    wtDecl: product['WT DECL'] || product.WT_DECL || product.wt_decl || product
                        .weight_declared || '',
                    w: product.W || product.width || product.Width || product.product_width || '',
                    l: product.L || product.length || item.Length || product.product_length || '',
                    h: product.H || product.height || product.product_height || '',
                    l2Url: product['5C'] || '',
                    pcbox: product.pcbox || '',
                    l1: product.l1 || '',
                    b: product.b || '',
                    h1: product.h1 || '',
                    upc: product.upc || ''
                };

                Object.entries(fields).forEach(([id, value]) => {
                    const element = document.getElementById(id);
                    if (element) element.value = value;
                });

                calculateCBM();
                calculateLP();
                modal.show();
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

            function incomingWhDdTriggerInnerHtml(optionText) {
                const key = warehouseThemeKeyFromName(optionText);
                const esc = escapeHtml(optionText);
                if (!key) {
                    return esc;
                }
                return '<span class="incoming-wh-dd-trigger-dot incoming-wh-dd-trigger-dot--' + key + '" aria-hidden="true"></span><span class="min-w-0 text-truncate">' + esc + '</span>';
            }

            function closeAllIncomingWhDdPanels() {
                document.querySelectorAll('.incoming-wh-dd-panel').forEach(function (p) {
                    p.classList.add('d-none');
                });
                document.querySelectorAll('.incoming-wh-dd-trigger').forEach(function (t) {
                    t.setAttribute('aria-expanded', 'false');
                });
            }

            function incomingWhDdSyncAll() {
                document.querySelectorAll('.incoming-wh-dd-wrap select.incoming-wh-dd-native').forEach(function (sel) {
                    const wrap = sel.closest('.incoming-wh-dd-wrap');
                    const trigger = wrap ? wrap.querySelector('.incoming-wh-dd-trigger') : null;
                    const inner = trigger ? trigger.querySelector('.incoming-wh-dd-trigger-inner') : null;
                    if (!inner) return;
                    const opt = sel.options[sel.selectedIndex];
                    const text = opt ? opt.text : '';
                    inner.innerHTML = incomingWhDdTriggerInnerHtml(text);
                    syncWarehouseFilterSelectTheme(sel.id);
                });
            }

            function setupIncomingWarehouseDropdowns() {
                if (!window.__incomingWhDdDocCloseBound) {
                    window.__incomingWhDdDocCloseBound = true;
                    document.addEventListener('click', function () {
                        closeAllIncomingWhDdPanels();
                    });
                    document.addEventListener('keydown', function (e) {
                        if (e.key === 'Escape') closeAllIncomingWhDdPanels();
                    });
                }

                document.querySelectorAll('.incoming-wh-dd-wrap:not([data-incoming-wh-dd-bound])').forEach(function (wrap) {
                    wrap.setAttribute('data-incoming-wh-dd-bound', '1');
                    const sel = wrap.querySelector('select.incoming-wh-dd-native');
                    const trigger = wrap.querySelector('.incoming-wh-dd-trigger');
                    const panel = wrap.querySelector('.incoming-wh-dd-panel');
                    if (!sel || !trigger || !panel) return;

                    function syncTriggerFromSelect() {
                        const inner = trigger.querySelector('.incoming-wh-dd-trigger-inner');
                        if (!inner) return;
                        const opt = sel.options[sel.selectedIndex];
                        const text = opt ? opt.text : '';
                        inner.innerHTML = incomingWhDdTriggerInnerHtml(text);
                        syncWarehouseFilterSelectTheme(sel.id);
                    }

                    function rebuildPanel() {
                        panel.innerHTML = '';
                        Array.from(sel.options).forEach(function (opt) {
                            if (opt.disabled) return;
                            const val = opt.value;
                            const label = opt.text;
                            const key = warehouseThemeKeyFromName(label);
                            const row = document.createElement('button');
                            row.type = 'button';
                            row.className = 'incoming-wh-dd-item w-100 text-start border-0 bg-transparent py-2 px-3 d-flex align-items-center rounded-0';
                            if (key) {
                                row.classList.add('incoming-wh-dd-item--' + key);
                            } else {
                                row.classList.add('incoming-wh-dd-item--neutral');
                            }
                            row.setAttribute('role', 'option');
                            let html = '';
                            if (key) {
                                html += '<span class="incoming-wh-dd-item-dot incoming-wh-dd-item-dot--' + key + '" aria-hidden="true"></span>';
                            }
                            html += '<span class="text-truncate">' + escapeHtml(label) + '</span>';
                            row.innerHTML = html;
                            row.addEventListener('click', function (e) {
                                e.preventDefault();
                                e.stopPropagation();
                                sel.value = val;
                                sel.dispatchEvent(new Event('change', { bubbles: true }));
                                syncTriggerFromSelect();
                                closeAllIncomingWhDdPanels();
                            });
                            panel.appendChild(row);
                        });
                    }

                    rebuildPanel();
                    syncTriggerFromSelect();

                    trigger.addEventListener('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        const willOpen = panel.classList.contains('d-none');
                        closeAllIncomingWhDdPanels();
                        if (willOpen) {
                            panel.classList.remove('d-none');
                            trigger.setAttribute('aria-expanded', 'true');
                        }
                    });
                });
            }

            function showSuccessPopup(message) {
                const modalElement = document.getElementById('successModal');
                const modalMessage = document.getElementById('successModalMessage');
                const okButton = document.getElementById('successModalOkBtn');
                
                if (modalElement && modalMessage) {
                    // Set the message
                    modalMessage.textContent = message || 'Stock updated successfully in Shopify! (¡Inventario actualizado en Shopify!)';
                    
                    // Initialize Bootstrap modal if not already done
                    const modal = new bootstrap.Modal(modalElement, {
                        backdrop: 'static',
                        keyboard: false
                    });
                    
                    // Remove any existing event listeners on OK button
                    const newOkButton = okButton.cloneNode(true);
                    okButton.parentNode.replaceChild(newOkButton, okButton);
                    
                    // Add click handler to OK button - reload page when clicked
                    newOkButton.addEventListener('click', function() {
                        modal.hide();
                        setTimeout(function () {
                            if (typeof loadData === 'function') {
                                loadData();
                            } else {
                                location.reload();
                            }
                            if (typeof loadReturnHistoryData === 'function') {
                                loadReturnHistoryData();
                            }
                        }, 200);
                    });
                    
                    // Show the modal
                    modal.show();
                } else {
                    // Fallback: use alert if modal elements don't exist
                    alert(message || 'Stock updated successfully in Shopify! (¡Inventario actualizado en Shopify!)');
                    location.reload();
                }
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

            initializeTable();
        });
    </script>

@endsection
