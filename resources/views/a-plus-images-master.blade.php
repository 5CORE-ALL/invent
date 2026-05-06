@extends('layouts.vertical', ['title' => 'A+ Masters', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

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

        .table-responsive tbody td {
            padding: 12px 18px;
            vertical-align: middle;
            border-bottom: 1px solid #edf2f9;
            font-size: 13px;
            color: #495057;
            transition: all 0.2s ease;
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

        .edit-btn {
            border-radius: 6px;
            padding: 6px 12px;
            transition: all 0.2s;
            background: #fff;
            border: 1px solid #1a56b7;
            color: #1a56b7;
        }

        .edit-btn:hover {
            background: #1a56b7;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(26, 86, 183, 0.2);
        }

        .delete-btn {
            border-radius: 6px;
            padding: 6px 12px;
            transition: all 0.2s;
            background: #fff;
            border: 1px solid #dc3545;
            color: #dc3545;
        }

        .delete-btn:hover {
            background: #dc3545;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(220, 53, 69, 0.2);
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

        .custom-toast {
            z-index: 2000;
            max-width: 400px;
            width: auto;
            min-width: 300px;
            font-size: 16px;
        }
        
        .toast-body {
            padding: 12px 15px;
            word-wrap: break-word;
            white-space: normal;
        }

        .image-preview {
            transition: transform 0.2s ease;
        }

        .image-preview:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .bs-link-btn {
            transition: all 0.2s ease;
        }

        .bs-link-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        td a[title="Buyer Link"]:hover {
            color: #1a56b7 !important;
            text-decoration: underline !important;
        }

        td a[title="Seller Link"]:hover {
            color: #2e7d4e !important;
            text-decoration: underline !important;
        }

        td a .fa-link:hover {
            transform: scale(1.1);
            transition: transform 0.2s ease;
        }

        .audit-dot {
            width: 12px;
            height: 12px;
            background-color: #dc3545;
            border-radius: 50%;
            display: inline-block;
            cursor: pointer;
            position: relative;
            transition: transform 0.2s ease;
        }

        .audit-dot:hover {
            transform: scale(1.2);
        }

        .audit-tooltip {
            visibility: hidden;
            background-color: #333;
            color: #fff;
            text-align: left;
            border-radius: 6px;
            padding: 8px 12px;
            position: absolute;
            z-index: 1000;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            min-width: 200px;
            max-width: 300px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 12px;
            white-space: normal;
            word-wrap: break-word;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .audit-tooltip::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #333 transparent transparent transparent;
        }

        .audit-dot:hover .audit-tooltip {
            visibility: visible;
            opacity: 1;
        }

        .audit-edit-btn {
            font-size: 11px;
            padding: 2px 6px;
            margin-left: 5px;
        }

        #fixedAuditSuggestionBtn {
            background-color: #28a745;
            border-color: #28a745;
        }

        #fixedAuditSuggestionBtn:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }

        #fixedAuditSuggestionBtn:disabled {
            background-color: #6c757d;
            border-color: #6c757d;
        }

        #voiceNoteRecordBtn {
            transition: all 0.3s ease;
        }

        #voiceNoteRecordBtn:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.4);
        }

        #voiceNoteRecordBtn.btn-danger {
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.7;
            }
        }

        #voiceNoteStatus {
            animation: blink 1s infinite;
        }

        @keyframes blink {
            0%, 50%, 100% {
                opacity: 1;
            }
            25%, 75% {
                opacity: 0.5;
            }
        }

        /* Comp Modal Green Dot for Product Title */
        .comp-product-title-dot {
            position: relative;
            display: inline-block;
            cursor: pointer;
        }

        .green-dot-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            background-color: #28a745;
            border-radius: 50%;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .comp-product-title-dot:hover .green-dot-indicator {
            transform: scale(1.3);
            box-shadow: 0 0 8px rgba(40, 167, 69, 0.6);
        }

        .product-title-tooltip {
            visibility: hidden;
            opacity: 0;
            position: absolute;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            background-color: #333;
            color: #fff;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            white-space: normal;
            max-width: 300px;
            width: max-content;
            text-align: left;
            z-index: 9999;
            transition: opacity 0.3s ease, visibility 0.3s ease;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            line-height: 1.4;
        }

        .product-title-tooltip::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 6px solid transparent;
            border-top-color: #333;
        }

        .comp-product-title-dot:hover .product-title-tooltip {
            visibility: visible;
            opacity: 1;
        }

        /* Sortable Column Styles */
        th.sortable {
            cursor: pointer;
            user-select: none;
            transition: background-color 0.2s ease;
        }

        th.sortable:hover {
            background-color: rgba(44, 110, 213, 0.1);
        }

        th.sortable i.fa-sort {
            color: #999;
            font-size: 12px;
            margin-left: 5px;
        }

        th.sortable i.fa-sort-up,
        th.sortable i.fa-sort-down {
            color: #2c6ed5;
            font-size: 12px;
            margin-left: 5px;
        }

        th.sortable.sorted {
            background-color: rgba(44, 110, 213, 0.05);
        }

        .history-icon {
            cursor: pointer;
            color: #2c6ed5;
            font-size: 16px;
            transition: transform 0.2s ease;
        }

        .btn-info {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn-info:hover {
            transform: scale(1.1);
            box-shadow: 0 2px 8px rgba(0, 123, 255, 0.4);
        }

        #lqsAvg {
            font-size: 14px;
            font-weight: bold;
            display: inline-block;
            min-width: 30px;
            text-align: center;
        }

        .history-icon:hover {
            transform: scale(1.2);
            color: #1a56b7;
        }

        .status-toggle-btn {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            position: relative;
        }

        .status-toggle-btn.red {
            background-color: #dc3545;
        }

        .status-toggle-btn.green {
            background-color: #28a745;
        }

        .status-toggle-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }

        .status-toggle-btn:active {
            transform: scale(0.95);
        }

        .status-toggle-btn.loading {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .status-toggle-btn.loading::after {
            content: "";
            position: absolute;
            width: 12px;
            height: 12px;
            border: 2px solid #fff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s linear infinite;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('layouts.shared.page-title', [
        'page_title' => 'A+ Masters',
        'sub_title' => 'A+ Masters Analysis',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <!-- Statistics Badges Row -->
                    <div class="row mb-3">
                        <div class="col-12 d-flex flex-wrap align-items-center gap-2 justify-content-between">
                            <!-- Left side: LQS Badge and Play Controls -->
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-primary p-2" style="font-weight: bold; font-size: 14px; white-space: nowrap;">
                                    LQS AVG: <span id="lqsAvg">-</span>
                                </span>
                                
                                <!-- Play/Pause Controls -->
                                <div class="btn-group align-items-center" role="group" style="gap: 4px;">
                                    <button type="button" id="lqs-play-backward" class="btn btn-sm btn-light rounded-circle shadow-sm" style="width: 32px; height: 32px; padding: 0;" title="Previous (Lower LQS)" disabled>
                                        <i class="fas fa-step-backward" style="font-size: 12px;"></i>
                                    </button>
                                    <button type="button" id="lqs-play-auto" class="btn btn-sm btn-success rounded-circle shadow-sm" style="width: 32px; height: 32px; padding: 0;" title="Play LQS (Lowest to Highest)">
                                        <i class="fas fa-play" style="font-size: 12px;"></i>
                                    </button>
                                    <button type="button" id="lqs-play-pause" class="btn btn-sm btn-danger rounded-circle shadow-sm" style="display: none; width: 32px; height: 32px; padding: 0;" title="Pause - click to stop">
                                        <i class="fas fa-pause" style="font-size: 12px;"></i>
                                    </button>
                                    <button type="button" id="lqs-play-forward" class="btn btn-sm btn-light rounded-circle shadow-sm" style="width: 32px; height: 32px; padding: 0;" title="Next (Higher LQS)" disabled>
                                        <i class="fas fa-step-forward" style="font-size: 12px;"></i>
                                    </button>
                                </div>
                                
                                <span id="lqs-play-status" class="badge bg-info" style="display: none; font-size: 12px; white-space: nowrap;">
                                    Playing: <span id="current-lqs-value">-</span>
                                </span>
                            </div>
                            
                            <!-- Middle: Search Box -->
                            <div class="d-flex align-items-center gap-2 flex-grow-1" style="max-width: 500px;">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" id="customSearch" class="form-control" placeholder="Search">
                                    <button class="btn btn-outline-secondary" type="button" id="clearSearch">Clear</button>
                                </div>
                            </div>
                            
                            <!-- LQS Search Filter -->
                            <div class="d-flex align-items-center gap-2" style="min-width: 200px;">
                                <div class="input-group">
                                    <span class="input-group-text" style="background-color: #f8f9fa; font-weight: bold;">LQS</span>
                                    <input type="text" id="lqsSearch" class="form-control" placeholder="Search LQS">
                                </div>
                            </div>
                            
                            <!-- Right side: Action Buttons -->
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-primary" id="addAPlusImagesBtn">
                                    <i class="fas fa-plus"></i>
                                </button>
                                <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#importAPlusImagesModal">
                                    <i class="fas fa-upload"></i>
                                </button>
                                <button type="button" class="btn btn-success" id="downloadExcel">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3" style="display: none;">
                        <!-- Hidden row - content moved above -->
                    </div>

                    <!-- Import Modal -->
                    <div class="modal fade" id="importAPlusImagesModal" tabindex="-1" aria-labelledby="importAPlusImagesModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header" style="background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%); color: white;">
                                    <h5 class="modal-title" id="importAPlusImagesModalLabel">
                                        <i class="fas fa-upload me-2"></i>Import A+ Images Data
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Instructions:</strong>
                                        <ol class="mb-0 mt-2">
                                            <li>Download the sample file below</li>
                                            <li>Fill in the A+ images data (DB links)</li>
                                            <li>Upload the completed file</li>
                                        </ol>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <button type="button" class="btn btn-outline-primary w-100" id="downloadSampleAPlusImagesBtn">
                                            <i class="fas fa-download me-2"></i>Download Sample File
                                        </button>
                                    </div>

                                    <div class="mb-3">
                                        <label for="aPlusImagesImportFile" class="form-label fw-bold">Select Excel File</label>
                                        <input type="file" class="form-control" id="aPlusImagesImportFile" accept=".xlsx,.xls,.csv">
                                        <div class="form-text">Supported formats: .xlsx, .xls, .csv</div>
                                        <div id="aPlusImagesFileError" class="text-danger mt-2" style="display: none;"></div>
                                    </div>

                                    <div id="aPlusImagesImportProgress" class="progress mb-3" style="display: none;">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                                    </div>

                                    <div id="aPlusImagesImportResult" class="alert" style="display: none;"></div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="button" class="btn btn-primary" id="importAPlusImagesBtn" disabled>
                                        <i class="fas fa-upload me-2"></i>Import
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table id="a-plus-images-master-datatable" class="table dt-responsive nowrap w-100">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th class="sortable" data-column="Parent">
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span>Parent <i class="fas fa-sort"></i></span>
                                            <span id="parentCount">(0)</span>
                                        </div>
                                    </th>
                                    <th class="sortable" data-column="SKU">
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span>SKU <i class="fas fa-sort"></i></span>
                                            <span id="skuCount">(0)</span>
                                        </div>
                                    </th>
                                    <th class="sortable" data-column="lqs">
                                        <div style="display: flex; align-items: center; gap: 5px;">
                                            <span>LQS <i class="fas fa-sort"></i></span>
                                        </div>
                                    </th>
                                    <th class="sortable" data-column="status">Stat <i class="fas fa-sort"></i></th>
                                    <th class="sortable" data-column="shopify_inv">INV <i class="fas fa-sort"></i></th>
                                    <th class="sortable" data-column="ovl30">Ovl30 <i class="fas fa-sort"></i></th>
                                    <th class="sortable" data-column="dil">Dil <i class="fas fa-sort"></i></th>
                                    <th>B/S</th>
                                    <th>DB <span id="dbMissingCount" class="text-danger" style="font-weight: bold;">(0)</span></th>
                                    <th>Comp</th>
                                    <th>Audit</th>
                                    <th>A+(P)</th>
                                    <th>A+(S)</th>
                                    <th>History</th>
                                    <th>Action</th>
                                    <th>Push</th>
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
                        <div class="loading-text">Loading A+ Masters Data...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add A+ Images Master Modal -->
    <div class="modal fade" id="addAPlusImagesModal" tabindex="-1" aria-labelledby="addAPlusImagesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addAPlusImagesModalLabel">Add A+ Images Data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addAPlusImagesForm">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="addAPlusImagesSku" class="form-label">SKU <span class="text-danger">*</span></label>
                                <select class="form-control" id="addAPlusImagesSku" name="sku" required>
                                    <option value="">Select SKU</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="addDB" class="form-label">DB</label>
                                <input type="text" class="form-control" id="addDB" name="db" placeholder="Enter DB Link">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveAddAPlusImagesBtn">
                        <i class="fas fa-save me-2"></i> Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Audit Modal -->
    <div class="modal fade" id="editAuditSuggestionModal" tabindex="-1" aria-labelledby="editAuditSuggestionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%); color: white;">
                    <h5 class="modal-title" id="editAuditSuggestionModalLabel">
                        <i class="fas fa-edit me-2"></i>Edit Audit
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editAuditSuggestionForm">
                        <input type="hidden" id="editAuditSuggestionSku" name="sku">
                        <div class="mb-3">
                            <label for="editAuditSuggestionText" class="form-label fw-bold">
                                Audit (Max 100 characters)
                                <button type="button" class="btn btn-sm btn-danger ms-2" id="voiceToTextBtn" title="Voice to Text">
                                    <i class="fas fa-microphone"></i>
                                </button>
                                <span id="voiceStatus" class="badge bg-warning ms-2" style="display: none;">Listening...</span>
                            </label>
                            <div class="input-group">
                                <textarea class="form-control" id="editAuditSuggestionText" name="audit_suggestion" rows="3" maxlength="100" placeholder="Enter audit suggestion or click microphone to speak..."></textarea>
                            </div>
                            <div class="form-text">
                                <span id="charCount">0</span> / 100 characters
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="auditLinkData" class="form-label fw-bold">
                                <i class="fas fa-link text-danger me-1"></i>Link Data
                                <button type="button" class="btn btn-sm btn-primary ms-2" id="addMoreLinkBtn" title="Add more link">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </label>
                            <div id="linkDataContainer">
                                <div class="input-group mb-2 link-data-item">
                                    <input type="url" class="form-control link-data-input" placeholder="Enter URL...">
                                    <button type="button" class="btn btn-danger btn-sm remove-link-btn" style="display: none;">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="form-text">Add a related link (will appear as red link icon)</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="auditScreenshot" class="form-label fw-bold">
                                <i class="fas fa-camera text-primary me-1"></i>Screenshot (Snippet)
                                <button type="button" class="btn btn-sm btn-primary ms-2" id="addMoreScreenshotBtn" title="Add more screenshot">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </label>
                            <div id="screenshotContainer">
                                <div class="screenshot-item mb-2">
                                    <div class="input-group">
                                        <input type="file" class="form-control screenshot-input" accept="image/*">
                                        <button type="button" class="btn btn-danger btn-sm remove-screenshot-btn" style="display: none;">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <div class="screenshot-preview mt-2"></div>
                                </div>
                            </div>
                            <div class="form-text">Upload a screenshot or image snippet</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="auditVoiceNote" class="form-label fw-bold">
                                <i class="fas fa-microphone-alt text-success me-1"></i>Voice Note
                                <button type="button" class="btn btn-sm btn-primary ms-2" id="addMoreVoiceNoteBtn" title="Add more voice note">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </label>
                            <div id="voiceNoteContainer">
                                <div class="voice-note-item mb-3">
                                    <button type="button" class="btn btn-sm btn-success voice-note-record-btn" title="Click to record voice note">
                                        <i class="fas fa-circle"></i> Record
                                    </button>
                                    <span class="badge bg-danger ms-2 voice-note-status" style="display: none;">Recording... <span class="voice-note-timer">0:00</span></span>
                                    <button type="button" class="btn btn-danger btn-sm ms-2 remove-voice-note-btn" style="display: none;">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <div class="voice-note-preview mt-2"></div>
                                    <div class="form-text">Click the button to record an audio note (max 2 minutes)</div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" id="fixedAuditSuggestionBtn">
                        <i class="fas fa-check-circle me-2"></i>Fixed
                    </button>
                    <button type="button" class="btn btn-primary" id="saveAuditSuggestionBtn">
                        <i class="fas fa-save me-2"></i>Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- View History Modal -->
    <div class="modal fade" id="viewHistoryModal" tabindex="-1" aria-labelledby="viewHistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%); color: white;">
                    <h5 class="modal-title" id="viewHistoryModalLabel">
                        <i class="fas fa-history me-2"></i>Audit History
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="historyContent">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Upload Modal -->
    <div class="modal fade" id="imageUploadModal" tabindex="-1" aria-labelledby="imageUploadModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%); color: white;">
                    <h5 class="modal-title" id="imageUploadModalLabel">
                        <i class="fas fa-upload me-2"></i>Upload <span id="imageTypeLabel"></span> Image
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="imageUploadForm">
                        <input type="hidden" id="imageUploadSku" name="sku">
                        <input type="hidden" id="imageUploadType" name="image_type">
                        <div class="mb-3">
                            <label for="imageFileInput" class="form-label fw-bold">
                                <i class="fas fa-image text-primary me-1"></i>Select Image File
                            </label>
                            <input type="file" class="form-control" id="imageFileInput" name="image_file" accept="image/*" required>
                            <div class="form-text">Accepted formats: JPG, PNG, GIF, BMP, WEBP, SVG, etc.</div>
                        </div>
                        <div id="imagePreviewContainer" style="display: none;">
                            <label class="form-label fw-bold">Preview:</label>
                            <div class="text-center">
                                <img id="imagePreview" src="" alt="Preview" style="max-width: 100%; max-height: 300px; border: 1px solid #ddd; border-radius: 4px; padding: 5px;">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveImageBtn">
                        <i class="fas fa-save me-2"></i>Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Status History Modal -->
    <div class="modal fade" id="viewStatusHistoryModal" tabindex="-1" aria-labelledby="viewStatusHistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%); color: white;">
                    <h5 class="modal-title" id="viewStatusHistoryModalLabel">
                        <i class="fas fa-history me-2"></i>Status Toggle History
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="statusHistoryContent">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Competitors (LMP) Modal -->
    <div class="modal fade" id="competitorsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%); color: white;">
                    <h5 class="modal-title">
                        <i class="fas fa-search me-2"></i>Competitors for SKU: <span id="competitorsSku"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Add New Competitor Form -->
                    <div class="card mb-3 border-success">
                        <div class="card-header bg-success text-white">
                            <strong><i class="fas fa-plus-circle me-2"></i>Add New Competitor</strong>
                        </div>
                        <div class="card-body">
                            <form id="addCompetitorForm" class="row g-3">
                                <input type="hidden" id="compSku">
                                <div class="col-md-3">
                                    <label class="form-label"><strong>ASIN</strong> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="compAsin" placeholder="B07ABC123" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label"><strong>Price</strong> <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="compPrice" placeholder="29.99" step="0.01" min="0.01" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label"><strong>Product Link</strong></label>
                                    <input type="url" class="form-control" id="compLink" placeholder="https://amazon.com/dp/...">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label"><strong>Marketplace</strong></label>
                                    <select class="form-select" id="compMarketplace">
                                        <option value="Amazon" selected>Amazon</option>
                                        <option value="US">US</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-plus me-2"></i>Add Competitor
                                    </button>
                                    <button type="reset" class="btn btn-secondary">
                                        <i class="fas fa-undo me-2"></i>Clear
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Competitors List -->
                    <div id="competitorsList">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading competitors...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Store the loaded data globally
            let tableData = [];
            let filteredData = [];

            // Get CSRF token from meta tag
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

            // Show loader immediately
            document.getElementById('rainbow-loader').style.display = 'block';

            // Centralized AJAX request function
            function makeRequest(url, method, data = {}) {
                const headers = {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                };

                if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method.toUpperCase())) {
                    data._token = csrfToken;
                }

                return fetch(url, {
                    method: method,
                    headers: headers,
                    body: method === 'GET' ? null : JSON.stringify(data)
                });
            }

            // Escape HTML to prevent XSS
            function escapeHtml(text) {
                if (text == null) return '';
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // Format number
            function formatNumber(value, decimals = 2) {
                if (value === null || value === undefined || value === '') return '-';
                const num = parseFloat(value);
                if (isNaN(num)) return '-';
                return num.toFixed(decimals);
            }

            // Load A+ images data from server
            function loadData() {
                const cacheParam = '?ts=' + new Date().getTime();
                makeRequest('/a-plus-images-master-data-view' + cacheParam, 'GET')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(response => {
                        if (response && response.data && Array.isArray(response.data)) {
                            tableData = response.data;
                            filteredData = [...tableData];
                            renderTable(filteredData);
                            updateCounts();
                            setupSearch();
                            setupSorting();
                        } else {
                            console.error('Invalid data format received from server');
                        }
                        document.getElementById('rainbow-loader').style.display = 'none';
                    })
                    .catch(error => {
                        console.error('Failed to load A+ images data: ' + error.message);
                        document.getElementById('rainbow-loader').style.display = 'none';
                    });
            }

            // Render table
            function renderTable(data) {
                const tbody = document.getElementById('table-body');
                tbody.innerHTML = '';

                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="12" class="text-center">No A+ images data found</td></tr>';
                    return;
                }

                data.forEach(item => {
                    // Debug: Log first item to see what fields are available
                    if (data.indexOf(item) === 0) {
                        console.log('Sample item data:', item);
                        console.log('standard_a_plus:', item.standard_a_plus);
                        console.log('premium_a_plus:', item.premium_a_plus);
                        console.log('shopify_inv (INV):', item.shopify_inv);
                        console.log('ovl30:', item.ovl30);
                        console.log('dil:', item.dil);
                    }
                    const row = document.createElement('tr');
                    
                    // Add light yellow background for parent rows
                    const isParentRow = item.SKU && item.SKU.toUpperCase().includes('PARENT');
                    if (isParentRow) {
                        row.style.backgroundColor = '#fffacd'; // Light yellow
                    }

                    // Image column
                    const imageCell = document.createElement('td');
                    imageCell.innerHTML = item.image_path 
                        ? `<img src="${item.image_path}" style="width:40px;height:40px;object-fit:cover;border-radius:4px;">`
                        : '-';
                    row.appendChild(imageCell);

                    // Parent column
                    const parentCell = document.createElement('td');
                    parentCell.textContent = escapeHtml(item.Parent) || '-';
                    row.appendChild(parentCell);

                    // SKU column
                    const skuCell = document.createElement('td');
                    skuCell.textContent = escapeHtml(item.SKU) || '-';
                    row.appendChild(skuCell);

                    // LQS (Listing Quality Score) column
                    const lqsCell = document.createElement('td');
                    lqsCell.style.textAlign = 'center';
                    const lqsScore = item.listing_quality_score || item.lqs;
                    if (lqsScore && lqsScore !== '' && lqsScore !== null) {
                        const score = parseInt(lqsScore);
                        const badge = document.createElement('span');
                        badge.className = 'badge';
                        badge.style.fontSize = '12px';
                        badge.style.fontWeight = 'bold';
                        badge.textContent = score;
                        
                        // Color based on score
                        if (score >= 8) {
                            badge.style.backgroundColor = '#28a745'; // Green
                            badge.style.color = 'white';
                        } else if (score >= 6) {
                            badge.style.backgroundColor = '#ffc107'; // Yellow/Warning
                            badge.style.color = 'black';
                        } else {
                            badge.style.backgroundColor = '#dc3545'; // Red
                            badge.style.color = 'white';
                        }
                        
                        lqsCell.appendChild(badge);
                    } else {
                        lqsCell.textContent = '-';
                    }
                    row.appendChild(lqsCell);

                    // Status column
                    const statusCell = document.createElement('td');
                    statusCell.style.textAlign = 'center';
                    const status = (item.status || '').toLowerCase();
                    if (status === 'active') {
                        const dot = document.createElement('span');
                        dot.style.display = 'inline-block';
                        dot.style.width = '12px';
                        dot.style.height = '12px';
                        dot.style.borderRadius = '50%';
                        dot.style.backgroundColor = '#28a745';
                        dot.title = 'Active';
                        statusCell.appendChild(dot);
                    } else {
                        statusCell.textContent = escapeHtml(item.status) || '-';
                    }
                    row.appendChild(statusCell);

                    // INV column
                    const invCell = document.createElement('td');
                    invCell.style.textAlign = 'center';
                    const invValue = item.shopify_inv;
                    if (invValue === 0 || invValue === "0") {
                        invCell.textContent = "0";
                    } else if (invValue === null || invValue === undefined || invValue === "") {
                        invCell.textContent = "-";
                    } else {
                        invCell.textContent = escapeHtml(String(invValue));
                    }
                    row.appendChild(invCell);

                    // Ovl30 column
                    const ovl30Cell = document.createElement('td');
                    ovl30Cell.style.textAlign = 'center';
                    const ovl30Value = item.ovl30;
                    if (ovl30Value === null || ovl30Value === undefined || ovl30Value === '') {
                        ovl30Cell.textContent = '0';
                    } else {
                        ovl30Cell.textContent = String(ovl30Value);
                    }
                    row.appendChild(ovl30Cell);

                    // Dil column
                    const dilCell = document.createElement('td');
                    dilCell.style.textAlign = 'center';
                    dilCell.style.fontWeight = 'bold';
                    const dilValue = item.dil;
                    let dilText = '0%';
                    let dilColor = '#a00211'; // Default red for 0%
                    
                    if (dilValue !== null && dilValue !== undefined && dilValue !== '') {
                        const dilNum = parseFloat(dilValue);
                        dilText = Math.round(dilNum) + '%'; // Round to whole number
                        
                        // Color coding based on DIL ranges
                        if (dilNum < 16.7) {
                            dilColor = '#a00211'; // 🔴 Red: Very slow moving inventory
                        } else if (dilNum >= 16.7 && dilNum < 25) {
                            dilColor = '#ffc107'; // 🟡 Yellow: Slow moving inventory
                        } else if (dilNum >= 25 && dilNum < 50) {
                            dilColor = '#28a745'; // 🟢 Green: Good inventory turnover
                        } else if (dilNum >= 50) {
                            dilColor = '#e83e8c'; // 🩷 Pink: Excellent inventory turnover
                        }
                    }
                    
                    dilCell.style.color = dilColor;
                    dilCell.textContent = dilText;
                    row.appendChild(dilCell);

                    // B/S (Buyer/Seller) column
                    const bsCell = document.createElement('td');
                    bsCell.style.textAlign = 'center';
                    if (item.buyer_link || item.seller_link) {
                        let linksHtml = '<div class="d-flex justify-content-center align-items-center" style="font-size: 13px;">';
                        if (item.buyer_link) {
                            linksHtml += `<a href="${escapeHtml(item.buyer_link)}" target="_blank" class="text-decoration-none fw-semibold" style="color: #2c6ed5;" title="Buyer Link">B</a>`;
                        }
                        if (item.seller_link) {
                            linksHtml += `<a href="${escapeHtml(item.seller_link)}" target="_blank" class="text-decoration-none fw-semibold" style="color: #47ad77;" title="Seller Link">S</a>`;
                        }
                        linksHtml += '</div>';
                        bsCell.innerHTML = linksHtml;
                    } else {
                        bsCell.textContent = '-';
                    }
                    row.appendChild(bsCell);

                    // DB column
                    const dbCell = document.createElement('td');
                    const dbValue = item.db || item['DB'] || '';
                    if (dbValue && dbValue.trim()) {
                        const cleanUrl = dbValue.trim();
                        const isUrl = cleanUrl.match(/^https?:\/\//i);
                        
                        if (isUrl) {
                            const escapedUrl = cleanUrl.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                            dbCell.innerHTML = `<a href="${escapedUrl}" target="_blank" class="text-decoration-none" title="${escapeHtml(cleanUrl)}" style="color: #2c6ed5;"><i class="fas fa-link"></i></a>`;
                        } else {
                            dbCell.textContent = escapeHtml(cleanUrl);
                        }
                    } else {
                        dbCell.textContent = '-';
                    }
                    row.appendChild(dbCell);

                    // Comp column (Competitors/LMP)
                    const compCell = document.createElement('td');
                    compCell.style.textAlign = 'center';
                    const compBtn = document.createElement('button');
                    compBtn.className = 'btn btn-sm btn-info';
                    compBtn.innerHTML = '<i class="fas fa-search"></i>';
                    compBtn.title = 'View Competitors (LMP)';
                    compBtn.onclick = function() { viewCompetitors(item.SKU); };
                    compCell.appendChild(compBtn);
                    row.appendChild(compCell);

                    // Audit column
                    const auditSuggestionCell = document.createElement('td');
                    auditSuggestionCell.style.textAlign = 'center';
                    const auditSuggestion = item.audit_suggestion || '';
                    if (auditSuggestion && auditSuggestion.trim()) {
                        const container = document.createElement('div');
                        container.style.display = 'inline-flex';
                        container.style.alignItems = 'center';
                        container.style.gap = '5px';
                        
                        const dot = document.createElement('div');
                        dot.className = 'audit-dot';
                        
                        const tooltip = document.createElement('div');
                        tooltip.className = 'audit-tooltip';
                        tooltip.textContent = auditSuggestion;
                        dot.appendChild(tooltip);
                        
                        const editBtn = document.createElement('button');
                        editBtn.className = 'btn btn-sm btn-outline-primary audit-edit-btn';
                        editBtn.innerHTML = '<i class="fas fa-edit"></i>';
                        editBtn.onclick = function() { openEditAuditSuggestion(item.SKU, auditSuggestion); };
                        
                        container.appendChild(dot);
                        container.appendChild(editBtn);
                        auditSuggestionCell.appendChild(container);
                    } else {
                        const addBtn = document.createElement('button');
                        addBtn.className = 'btn btn-sm btn-outline-success audit-edit-btn';
                        addBtn.innerHTML = '<i class="fas fa-plus"></i>';
                        addBtn.onclick = function() { openEditAuditSuggestion(item.SKU, ''); };
                        auditSuggestionCell.appendChild(addBtn);
                    }
                    row.appendChild(auditSuggestionCell);

                    // A+(P) column
                    const premiumCell = document.createElement('td');
                    premiumCell.style.textAlign = 'center';
                    const premiumImage = item.premium_image || '';
                    if (premiumImage && premiumImage.trim()) {
                        const imgContainer = document.createElement('div');
                        imgContainer.style.display = 'inline-flex';
                        imgContainer.style.alignItems = 'center';
                        imgContainer.style.gap = '5px';
                        
                        const thumbnail = document.createElement('img');
                        thumbnail.src = `/storage/${premiumImage}`;
                        thumbnail.style.width = '40px';
                        thumbnail.style.height = '40px';
                        thumbnail.style.objectFit = 'cover';
                        thumbnail.style.borderRadius = '4px';
                        thumbnail.style.cursor = 'pointer';
                        thumbnail.onclick = function() { window.open(`/storage/${premiumImage}`, '_blank'); };
                        
                        const uploadBtn = document.createElement('button');
                        uploadBtn.className = 'btn btn-sm btn-outline-primary';
                        uploadBtn.innerHTML = '<i class="fas fa-edit"></i>';
                        uploadBtn.onclick = function() { openImageUploadModal(item.SKU, 'premium'); };
                        
                        imgContainer.appendChild(thumbnail);
                        imgContainer.appendChild(uploadBtn);
                        premiumCell.appendChild(imgContainer);
                    } else {
                        const uploadBtn = document.createElement('button');
                        uploadBtn.className = 'btn btn-sm btn-outline-success';
                        uploadBtn.innerHTML = '<i class="fas fa-upload"></i>';
                        uploadBtn.onclick = function() { openImageUploadModal(item.SKU, 'premium'); };
                        premiumCell.appendChild(uploadBtn);
                    }
                    row.appendChild(premiumCell);

                    // A+(S) column
                    const standardCell = document.createElement('td');
                    standardCell.style.textAlign = 'center';
                    const standardImage = item.standard_image || '';
                    if (standardImage && standardImage.trim()) {
                        const imgContainer = document.createElement('div');
                        imgContainer.style.display = 'inline-flex';
                        imgContainer.style.alignItems = 'center';
                        imgContainer.style.gap = '5px';
                        
                        const thumbnail = document.createElement('img');
                        thumbnail.src = `/storage/${standardImage}`;
                        thumbnail.style.width = '40px';
                        thumbnail.style.height = '40px';
                        thumbnail.style.objectFit = 'cover';
                        thumbnail.style.borderRadius = '4px';
                        thumbnail.style.cursor = 'pointer';
                        thumbnail.onclick = function() { window.open(`/storage/${standardImage}`, '_blank'); };
                        
                        const uploadBtn = document.createElement('button');
                        uploadBtn.className = 'btn btn-sm btn-outline-primary';
                        uploadBtn.innerHTML = '<i class="fas fa-edit"></i>';
                        uploadBtn.onclick = function() { openImageUploadModal(item.SKU, 'standard'); };
                        
                        imgContainer.appendChild(thumbnail);
                        imgContainer.appendChild(uploadBtn);
                        standardCell.appendChild(imgContainer);
                    } else {
                        const uploadBtn = document.createElement('button');
                        uploadBtn.className = 'btn btn-sm btn-outline-success';
                        uploadBtn.innerHTML = '<i class="fas fa-upload"></i>';
                        uploadBtn.onclick = function() { openImageUploadModal(item.SKU, 'standard'); };
                        standardCell.appendChild(uploadBtn);
                    }
                    row.appendChild(standardCell);

                    // History column
                    const historyCell = document.createElement('td');
                    historyCell.style.textAlign = 'center';
                    const historyBtn = document.createElement('i');
                    historyBtn.className = 'fas fa-history history-icon';
                    historyBtn.title = 'View History';
                    historyBtn.onclick = function() { viewHistory(item.SKU); };
                    historyCell.appendChild(historyBtn);
                    row.appendChild(historyCell);

                    // Action column
                    const actionCell = document.createElement('td');
                    actionCell.className = 'text-center';
                    actionCell.innerHTML = `
                        <div class="d-inline-flex">
                            <button class="btn btn-sm btn-outline-warning edit-btn" data-sku="${escapeHtml(item.SKU)}">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                        </div>
                    `;
                    row.appendChild(actionCell);

                    // Push column
                    const pushCell = document.createElement('td');
                    pushCell.className = 'text-center';
                    pushCell.innerHTML = `
                        <button class="btn btn-sm btn-primary push-btn" data-sku="${escapeHtml(item.SKU)}" title="Push">
                            <i class="fas fa-arrow-up"></i>
                        </button>
                    `;
                    row.appendChild(pushCell);

                    tbody.appendChild(row);
                });
            }

            // Check if value is missing (null, undefined, empty)
            function isMissing(value) {
                return value === null || value === undefined || value === '' || (typeof value === 'string' && value.trim() === '');
            }

            // Update counts
            function updateCounts() {
                const parentSet = new Set();
                let skuCount = 0;
                let dbMissingCount = 0;
                let lqsTotal = 0;
                let lqsCount = 0;

                tableData.forEach(item => {
                    if (item.Parent) parentSet.add(item.Parent);
                    if (item.SKU && !String(item.SKU).toUpperCase().includes('PARENT'))
                        skuCount++;
                    
                    // Count missing data for DB column
                    const dbValue = item.db || item['DB'] || '';
                    if (isMissing(dbValue)) dbMissingCount++;
                    
                    // Calculate LQS average
                    const lqsValue = item.listing_quality_score || item.lqs;
                    if (lqsValue && lqsValue !== '' && lqsValue !== null && !isNaN(lqsValue)) {
                        lqsTotal += parseFloat(lqsValue);
                        lqsCount++;
                    }
                });
                
                document.getElementById('parentCount').textContent = `(${parentSet.size})`;
                document.getElementById('skuCount').textContent = `(${skuCount})`;
                document.getElementById('dbMissingCount').textContent = `(${dbMissingCount})`;
                
                // Update LQS average with color coding
                const lqsAvgElement = document.getElementById('lqsAvg');
                const lqsBadge = lqsAvgElement.closest('.badge');
                if (lqsCount > 0) {
                    const avg = lqsTotal / lqsCount;
                    lqsAvgElement.textContent = avg.toFixed(1);
                    
                    // Color code based on average
                    if (avg >= 8) {
                        lqsBadge.className = 'badge bg-success fs-6 p-2 me-2';
                    } else if (avg >= 6) {
                        lqsBadge.className = 'badge bg-warning fs-6 p-2 me-2';
                    } else {
                        lqsBadge.className = 'badge bg-danger fs-6 p-2 me-2';
                    }
                } else {
                    lqsAvgElement.textContent = '-';
                    lqsBadge.className = 'badge bg-secondary fs-6 p-2 me-2';
                }
            }

            // Apply all filters
            function applyFilters() {
                filteredData = tableData.filter(item => {
                    // Custom search filter
                    const customSearch = document.getElementById('customSearch').value.toLowerCase();
                    if (customSearch) {
                        const parent = (item.Parent || '').toLowerCase();
                        const sku = (item.SKU || '').toLowerCase();
                        const status = (item.status || '').toLowerCase();
                        if (!parent.includes(customSearch) && !sku.includes(customSearch) && !status.includes(customSearch)) {
                            return false;
                        }
                    }

                    // LQS search filter
                    const lqsSearch = document.getElementById('lqsSearch').value.toLowerCase();
                    if (lqsSearch) {
                        const lqsValue = item.listing_quality_score || item.lqs || '';
                        if (!String(lqsValue).toLowerCase().includes(lqsSearch)) {
                            return false;
                        }
                    }

                    return true;
                });
                
                // Maintain sorting after filtering
                if (currentSortColumn) {
                    sortData();
                }
                
                renderTable(filteredData);
            }

            // Setup search functionality
            function setupSearch() {
                // Custom search
                const customSearch = document.getElementById('customSearch');
                customSearch.addEventListener('input', function() {
                    applyFilters();
                });

                // LQS search
                const lqsSearch = document.getElementById('lqsSearch');
                lqsSearch.addEventListener('input', function() {
                    applyFilters();
                });

                // Clear search
                document.getElementById('clearSearch').addEventListener('click', function() {
                    customSearch.value = '';
                    lqsSearch.value = '';
                    applyFilters();
                });
            }

            // Setup sorting functionality
            let currentSortColumn = null;
            let currentSortDirection = 'asc'; // 'asc' or 'desc'

            function setupSorting() {
                const sortableHeaders = document.querySelectorAll('th.sortable');
                
                sortableHeaders.forEach(header => {
                    header.addEventListener('click', function(e) {
                        // Don't sort if clicking on input fields
                        if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT') {
                            return;
                        }
                        
                        const column = this.getAttribute('data-column');
                        
                        // Toggle sort direction
                        if (currentSortColumn === column) {
                            currentSortDirection = currentSortDirection === 'asc' ? 'desc' : 'asc';
                        } else {
                            currentSortColumn = column;
                            currentSortDirection = 'asc';
                        }
                        
                        // Update sort indicators
                        updateSortIndicators();
                        
                        // Sort and render
                        sortData();
                        renderTable(filteredData);
                    });
                });
            }

            function updateSortIndicators() {
                // Reset all sort icons
                document.querySelectorAll('th.sortable').forEach(header => {
                    header.classList.remove('sorted');
                    const icon = header.querySelector('i.fa-sort, i.fa-sort-up, i.fa-sort-down');
                    if (icon) {
                        icon.className = 'fas fa-sort';
                    }
                });
                
                // Update active column
                if (currentSortColumn) {
                    const activeHeader = document.querySelector(`th.sortable[data-column="${currentSortColumn}"]`);
                    if (activeHeader) {
                        activeHeader.classList.add('sorted');
                        const icon = activeHeader.querySelector('i.fas');
                        if (icon) {
                            icon.className = currentSortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down';
                        }
                    }
                }
            }

            function sortData() {
                if (!currentSortColumn) return;
                
                filteredData.sort((a, b) => {
                    let aVal = a[currentSortColumn];
                    let bVal = b[currentSortColumn];
                    
                    // Handle null/undefined values
                    if (aVal === null || aVal === undefined) aVal = '';
                    if (bVal === null || bVal === undefined) bVal = '';
                    
                    // Numeric columns
                    if (['shopify_inv', 'ovl30', 'dil', 'lqs'].includes(currentSortColumn)) {
                        aVal = parseFloat(aVal) || 0;
                        bVal = parseFloat(bVal) || 0;
                        return currentSortDirection === 'asc' ? aVal - bVal : bVal - aVal;
                    }
                    
                    // String columns (case-insensitive)
                    aVal = String(aVal).toLowerCase();
                    bVal = String(bVal).toLowerCase();
                    
                    if (currentSortDirection === 'asc') {
                        return aVal < bVal ? -1 : aVal > bVal ? 1 : 0;
                    } else {
                        return aVal > bVal ? -1 : aVal < bVal ? 1 : 0;
                    }
                });
            }

            // Toast notification function
            function showToast(type, message) {
                // Remove existing toasts
                document.querySelectorAll('.custom-toast').forEach(t => t.remove());
                
                const toast = document.createElement('div');
                toast.className = `custom-toast toast align-items-center text-bg-${type} border-0 show position-fixed top-0 end-0 m-4`;
                toast.style.zIndex = 2000;
                toast.setAttribute('role', 'alert');
                toast.setAttribute('aria-live', 'assertive');
                toast.setAttribute('aria-atomic', 'true');
                toast.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body">${message}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                `;
                document.body.appendChild(toast);

                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 500);
                }, 3000);

                toast.querySelector('[data-bs-dismiss="toast"]').onclick = () => {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 500);
                };
            }

            // Setup Excel export function
            function setupExcelExport() {
                document.getElementById('downloadExcel').addEventListener('click', function() {
                    // Columns to export (excluding Image and Action)
                    const columns = ["Parent", "SKU", "Stat", "INV", "Ovl30", "Dil", "DB", "A+(P)", "A+(S)"];

                    // Column definitions with their data keys
                    const columnDefs = {
                        "Parent": {
                            key: "Parent"
                        },
                        "SKU": {
                            key: "SKU"
                        },
                        "Stat": {
                            key: "status"
                        },
                        "INV": {
                            key: "shopify_inv"
                        },
                        "Ovl30": {
                            key: "ovl30"
                        },
                        "Dil": {
                            key: "dil"
                        },
                        "DB": {
                            key: "db"
                        },
                        "A+(P)": {
                            key: "premium_image"
                        },
                        "A+(S)": {
                            key: "standard_image"
                        }
                    };

                    // Show loader or indicate download is in progress
                    document.getElementById('downloadExcel').innerHTML =
                        '<i class="fas fa-spinner fa-spin"></i> Generating...';
                    document.getElementById('downloadExcel').disabled = true;

                    // Use setTimeout to avoid UI freeze for large datasets
                    setTimeout(() => {
                        try {
                            // Use filteredData if available, otherwise use tableData
                            const dataToExport = filteredData.length > 0 ? filteredData : tableData;

                            // Create worksheet data array
                            const wsData = [];

                            // Add header row
                            wsData.push(columns);

                            // Add data rows
                            dataToExport.forEach(item => {
                                const row = [];
                                columns.forEach(col => {
                                    const colDef = columnDefs[col];
                                    if (colDef) {
                                        const key = colDef.key;
                                        let value = item[key] !== undefined && item[key] !== null ? item[key] : '';

                                        // Format INV column
                                        if (key === "shopify_inv") {
                                            if (value === 0 || value === "0") {
                                                value = 0;
                                            } else if (value === null || value === undefined || value === "") {
                                                value = '';
                                            } else {
                                                value = parseFloat(value) || 0;
                                            }
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
                            const wscols = columns.map(col => {
                                // Adjust width based on column type
                                if (["Parent", "SKU", "A+(P)", "A+(S)"].includes(col)) {
                                    return { wch: 20 }; // Wider for text columns
                                } else if (["Stat", "DB"].includes(col)) {
                                    return { wch: 15 };
                                } else if (["Ovl30", "Dil", "INV"].includes(col)) {
                                    return { wch: 12 }; // Width for numeric columns
                                } else {
                                    return { wch: 10 }; // Default width
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
                            XLSX.utils.book_append_sheet(wb, ws, "A+ Masters");

                            // Generate Excel file and trigger download
                            XLSX.writeFile(wb, "a_plus_images_master_export.xlsx");

                            // Show success toast
                            showToast('success', 'Excel file downloaded successfully!');
                        } catch (error) {
                            console.error("Excel export error:", error);
                            showToast('danger', 'Failed to export Excel file.');
                        } finally {
                            // Reset button state
                            document.getElementById('downloadExcel').innerHTML =
                                '<i class="fas fa-download"></i>';
                            document.getElementById('downloadExcel').disabled = false;
                        }
                    }, 100); // Small timeout to allow UI to update
                });
            }

            // Setup add button handler
            function setupAddButton() {
                document.getElementById('addAPlusImagesBtn').addEventListener('click', function() {
                    openAddAPlusImagesModal();
                });
            }

            // Open Add A+ Images Modal
            async function openAddAPlusImagesModal() {
                const modalElement = document.getElementById('addAPlusImagesModal');
                const modal = new bootstrap.Modal(modalElement);
                
                // Reset form
                document.getElementById('addAPlusImagesForm').reset();
                
                // Destroy Select2 if already initialized
                const skuSelect = document.getElementById('addAPlusImagesSku');
                if ($(skuSelect).hasClass('select2-hidden-accessible')) {
                    $(skuSelect).select2('destroy');
                }
                
                // Load SKUs into dropdown
                await loadSkusIntoDropdown();
                
                // Setup save button handler
                const saveBtn = document.getElementById('saveAddAPlusImagesBtn');
                const newSaveBtn = saveBtn.cloneNode(true);
                saveBtn.parentNode.replaceChild(newSaveBtn, saveBtn);
                
                newSaveBtn.addEventListener('click', async function() {
                    await saveAddAPlusImages();
                });
                
                // Clean up Select2 when modal is hidden
                modalElement.addEventListener('hidden.bs.modal', function() {
                    if ($(skuSelect).hasClass('select2-hidden-accessible')) {
                        $(skuSelect).select2('destroy');
                    }
                }, { once: true });
                
                modal.show();
            }

            // Load SKUs into dropdown
            async function loadSkusIntoDropdown() {
                try {
                    const response = await fetch('/general-specific-master/skus', {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        }
                    });
                    
                    const data = await response.json();
                    
                    if (data.success && data.data) {
                        const skuSelect = document.getElementById('addAPlusImagesSku');
                        
                        // Destroy Select2 if already initialized
                        if ($(skuSelect).hasClass('select2-hidden-accessible')) {
                            $(skuSelect).select2('destroy');
                        }
                        
                        // Clear existing options except the first one
                        skuSelect.innerHTML = '<option value="">Select SKU</option>';
                        
                        // Add SKU options
                        data.data.forEach(item => {
                            const option = document.createElement('option');
                            option.value = item.sku;
                            option.textContent = item.sku;
                            skuSelect.appendChild(option);
                        });
                        
                        // Initialize Select2 with searchable dropdown
                        $(skuSelect).select2({
                            theme: 'bootstrap-5',
                            placeholder: 'Select SKU',
                            allowClear: true,
                            width: '100%',
                            dropdownParent: $('#addAPlusImagesModal')
                        });
                    }
                } catch (error) {
                    console.error('Error loading SKUs:', error);
                    showToast('warning', 'Failed to load SKUs. Please refresh the page.');
                }
            }

            // Save Add A+ Images Master
            async function saveAddAPlusImages() {
                const saveBtn = document.getElementById('saveAddAPlusImagesBtn');
                const originalText = saveBtn.innerHTML;
                
                // Validate required fields
                const skuSelect = document.getElementById('addAPlusImagesSku');
                const sku = $(skuSelect).val() ? $(skuSelect).val().trim() : '';
                if (!sku) {
                    showToast('warning', 'Please select SKU');
                    $(skuSelect).select2('open');
                    return;
                }
                
                try {
                    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Saving...';
                    saveBtn.disabled = true;
                    
                    const formData = {
                        sku: sku,
                        db: document.getElementById('addDB').value.trim(),
                        standard_a_plus: document.getElementById('addStandardAPlus').value.trim(),
                        premium_a_plus: document.getElementById('addPremiumAPlus').value.trim()
                    };
                    
                    const response = await fetch('/a-plus-images-master/store', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify(formData)
                    });
                    
                    const data = await response.json();
                    
                    if (!response.ok) {
                        throw new Error(data.message || 'Failed to save data');
                    }
                    
                    showToast('success', 'A+ Images Data added successfully!');
                    
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('addAPlusImagesModal'));
                    modal.hide();
                    
                    // Clear cache and reload data
                    tableData = [];
                    filteredData = [];
                    // Add cache buster to force fresh data
                    const cacheParam = '?ts=' + new Date().getTime();
                    setTimeout(() => {
                        loadData();
                    }, 500); // Small delay to ensure data is saved
                } catch (error) {
                    console.error('Error saving:', error);
                    showToast('danger', error.message || 'Failed to save data');
                } finally {
                    saveBtn.innerHTML = originalText;
                    saveBtn.disabled = false;
                }
            }

            // Setup import functionality
            function setupImport() {
                const importFile = document.getElementById('aPlusImagesImportFile');
                const importBtn = document.getElementById('importAPlusImagesBtn');
                const downloadSampleBtn = document.getElementById('downloadSampleAPlusImagesBtn');
                const importModal = document.getElementById('importAPlusImagesModal');
                const fileError = document.getElementById('aPlusImagesFileError');
                const importProgress = document.getElementById('aPlusImagesImportProgress');
                const importResult = document.getElementById('aPlusImagesImportResult');

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

                // Download sample file
                downloadSampleBtn.addEventListener('click', function() {
                    // Create sample data
                    const sampleData = [
                        ['SKU', 'DB'],
                        ['SKU001', 'https://example.com/db1.jpg'],
                        ['SKU002', 'https://example.com/db2.jpg'],
                        ['SKU003', 'https://example.com/db3.jpg']
                    ];

                    // Create workbook
                    const wb = XLSX.utils.book_new();
                    const ws = XLSX.utils.aoa_to_sheet(sampleData);

                    // Set column widths
                    ws['!cols'] = [
                        { wch: 15 }, // SKU
                        { wch: 40 }  // DB
                    ];

                    // Style header row
                    const headerRange = XLSX.utils.decode_range(ws['!ref']);
                    for (let C = headerRange.s.c; C <= headerRange.e.c; ++C) {
                        const cell = XLSX.utils.encode_cell({ r: 0, c: C });
                        if (!ws[cell]) continue;
                        ws[cell].s = {
                            fill: { fgColor: { rgb: "2C6ED5" } },
                            font: { bold: true, color: { rgb: "FFFFFF" } },
                            alignment: { horizontal: "center" }
                        };
                    }

                    XLSX.utils.book_append_sheet(wb, ws, "A+ Images Data");
                    XLSX.writeFile(wb, "a_plus_images_master_sample.xlsx");
                    
                    showToast('success', 'Sample file downloaded successfully!');
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
                        const response = await fetch('/a-plus-images-master/import', {
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
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Import Successful!</strong><br>
                                ${result.message || `Successfully imported ${result.imported || 0} records.`}
                                ${result.errors && result.errors.length > 0 ? `<br><small>Errors: ${result.errors.length}</small>` : ''}
                            `;
                            importResult.style.display = 'block';

                            // Reload data after successful import
                            setTimeout(() => {
                                // Clear cache and reload data
                                tableData = [];
                                filteredData = [];
                                const cacheParam = '?ts=' + new Date().getTime();
                                loadData();
                                // Close modal after a delay
                                setTimeout(() => {
                                    const modal = bootstrap.Modal.getInstance(importModal);
                                    if (modal) modal.hide();
                                    // Reset form
                                    importFile.value = '';
                                    importBtn.disabled = true;
                                    importProgress.style.display = 'none';
                                    importResult.style.display = 'none';
                                    progressBar.style.width = '0%';
                                }, 2000);
                            }, 1000);
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

            // Initialize
            loadData();
            setupExcelExport();
            setupAddButton();
            setupImport();

            // Push button event listener (event delegation)
            document.getElementById('table-body').addEventListener('click', function(e) {
                const pushBtn = e.target.closest('.push-btn');
                if (pushBtn) {
                    const sku = pushBtn.getAttribute('data-sku');
                    if (sku) {
                        handlePush(sku);
                    }
                }
            });

            // Character counter for audit suggestion
            const auditTextarea = document.getElementById('editAuditSuggestionText');
            if (auditTextarea) {
                auditTextarea.addEventListener('input', function() {
                    document.getElementById('charCount').textContent = this.value.length;
                });
            }

            // Multiple Link Data handlers
            let linkDataCount = 1;
            document.getElementById('addMoreLinkBtn').addEventListener('click', function() {
                linkDataCount++;
                const container = document.getElementById('linkDataContainer');
                const newLinkDiv = document.createElement('div');
                newLinkDiv.className = 'input-group mb-2 link-data-item';
                newLinkDiv.innerHTML = `
                    <input type="url" class="form-control link-data-input" placeholder="Enter URL...">
                    <button type="button" class="btn btn-danger btn-sm remove-link-btn">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                container.appendChild(newLinkDiv);
                updateLinkRemoveButtons();
            });

            // Delegate remove link button clicks
            document.getElementById('linkDataContainer').addEventListener('click', function(e) {
                if (e.target.closest('.remove-link-btn')) {
                    e.target.closest('.link-data-item').remove();
                    linkDataCount--;
                    updateLinkRemoveButtons();
                }
            });

            function updateLinkRemoveButtons() {
                const items = document.querySelectorAll('.link-data-item');
                items.forEach((item, index) => {
                    const removeBtn = item.querySelector('.remove-link-btn');
                    if (items.length > 1) {
                        removeBtn.style.display = 'block';
                    } else {
                        removeBtn.style.display = 'none';
                    }
                });
            }

            // Multiple Screenshot handlers
            let screenshotCount = 1;
            document.getElementById('addMoreScreenshotBtn').addEventListener('click', function() {
                screenshotCount++;
                const container = document.getElementById('screenshotContainer');
                const newScreenshotDiv = document.createElement('div');
                newScreenshotDiv.className = 'screenshot-item mb-2';
                newScreenshotDiv.innerHTML = `
                    <div class="input-group">
                        <input type="file" class="form-control screenshot-input" accept="image/*">
                        <button type="button" class="btn btn-danger btn-sm remove-screenshot-btn">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="screenshot-preview mt-2"></div>
                `;
                container.appendChild(newScreenshotDiv);
                attachScreenshotPreview(newScreenshotDiv.querySelector('.screenshot-input'));
                updateScreenshotRemoveButtons();
            });

            // Attach preview handler to initial screenshot input
            document.querySelectorAll('.screenshot-input').forEach(input => {
                attachScreenshotPreview(input);
            });

            function attachScreenshotPreview(input) {
                input.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    const previewDiv = input.closest('.screenshot-item').querySelector('.screenshot-preview');
                    
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(event) {
                            previewDiv.innerHTML = `
                                <div class="position-relative d-inline-block">
                                    <img src="${event.target.result}" class="img-thumbnail" style="max-width: 200px; max-height: 200px;">
                                </div>
                            `;
                        };
                        reader.readAsDataURL(file);
                    } else {
                        previewDiv.innerHTML = '';
                    }
                });
            }

            // Delegate remove screenshot button clicks
            document.getElementById('screenshotContainer').addEventListener('click', function(e) {
                if (e.target.closest('.remove-screenshot-btn')) {
                    e.target.closest('.screenshot-item').remove();
                    screenshotCount--;
                    updateScreenshotRemoveButtons();
                }
            });

            function updateScreenshotRemoveButtons() {
                const items = document.querySelectorAll('.screenshot-item');
                items.forEach((item, index) => {
                    const removeBtn = item.querySelector('.remove-screenshot-btn');
                    if (items.length > 1) {
                        removeBtn.style.display = 'block';
                    } else {
                        removeBtn.style.display = 'none';
                    }
                });
            }

            // Setup save audit suggestion handler
            document.getElementById('saveAuditSuggestionBtn').addEventListener('click', async function() {
                await saveAuditSuggestion();
            });

            // Setup fixed audit suggestion handler
            document.getElementById('fixedAuditSuggestionBtn').addEventListener('click', async function() {
                await saveAuditSuggestion(true); // Pass true to indicate it's a "Fixed" action
            });

            // Voice to Text functionality
            const voiceBtn = document.getElementById('voiceToTextBtn');
            const voiceStatus = document.getElementById('voiceStatus');
            // auditTextarea already declared above
            let recognition = null;
            let isRecording = false;

            // Check if browser supports Speech Recognition
            if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
                const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                recognition = new SpeechRecognition();
                
                recognition.continuous = false;
                recognition.interimResults = false;
                recognition.lang = 'en-US';

                recognition.onstart = function() {
                    isRecording = true;
                    voiceBtn.classList.remove('btn-danger');
                    voiceBtn.classList.add('btn-success');
                    voiceBtn.innerHTML = '<i class="fas fa-microphone-slash"></i>';
                    voiceStatus.style.display = 'inline-block';
                };

                recognition.onresult = function(event) {
                    const transcript = event.results[0][0].transcript;
                    const currentText = auditTextarea.value;
                    const newText = currentText ? currentText + ' ' + transcript : transcript;
                    
                    // Respect 100 character limit
                    auditTextarea.value = newText.substring(0, 100);
                    document.getElementById('charCount').textContent = auditTextarea.value.length;
                };

                recognition.onerror = function(event) {
                    console.error('Speech recognition error:', event.error);
                    showToast('danger', 'Voice recognition error: ' + event.error);
                    resetVoiceButton();
                };

                recognition.onend = function() {
                    resetVoiceButton();
                };

                voiceBtn.addEventListener('click', function() {
                    if (isRecording) {
                        recognition.stop();
                    } else {
                        try {
                            recognition.start();
                        } catch (error) {
                            console.error('Error starting recognition:', error);
                            showToast('danger', 'Could not start voice recognition');
                        }
                    }
                });
            } else {
                // Browser doesn't support speech recognition
                voiceBtn.disabled = true;
                voiceBtn.title = 'Voice recognition not supported in this browser';
                voiceBtn.classList.remove('btn-danger');
                voiceBtn.classList.add('btn-secondary');
            }

            function resetVoiceButton() {
                isRecording = false;
                voiceBtn.classList.remove('btn-success');
                voiceBtn.classList.add('btn-danger');
                voiceBtn.innerHTML = '<i class="fas fa-microphone"></i>';
                voiceStatus.style.display = 'none';
            }

            // Voice Note Recording functionality (Multiple)
            let voiceNoteCount = 1;
            const MAX_RECORDING_TIME = 120000; // 2 minutes in milliseconds
            const voiceNoteRecorders = new Map(); // Track each recorder by its button element

            // Add more voice note handler
            document.getElementById('addMoreVoiceNoteBtn').addEventListener('click', function() {
                voiceNoteCount++;
                const container = document.getElementById('voiceNoteContainer');
                const newVoiceNoteDiv = document.createElement('div');
                newVoiceNoteDiv.className = 'voice-note-item mb-3';
                newVoiceNoteDiv.innerHTML = `
                    <button type="button" class="btn btn-sm btn-success voice-note-record-btn" title="Click to record voice note">
                        <i class="fas fa-circle"></i> Record
                    </button>
                    <span class="badge bg-danger ms-2 voice-note-status" style="display: none;">Recording... <span class="voice-note-timer">0:00</span></span>
                    <button type="button" class="btn btn-danger btn-sm ms-2 remove-voice-note-btn">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="voice-note-preview mt-2"></div>
                    <div class="form-text">Click the button to record an audio note (max 2 minutes)</div>
                `;
                container.appendChild(newVoiceNoteDiv);
                attachVoiceNoteRecorder(newVoiceNoteDiv.querySelector('.voice-note-record-btn'));
                updateVoiceNoteRemoveButtons();
            });

            // Attach recorder to initial voice note button
            document.querySelectorAll('.voice-note-record-btn').forEach(btn => {
                attachVoiceNoteRecorder(btn);
            });

            function attachVoiceNoteRecorder(recordBtn) {
                const voiceNoteItem = recordBtn.closest('.voice-note-item');
                const statusBadge = voiceNoteItem.querySelector('.voice-note-status');
                const timer = voiceNoteItem.querySelector('.voice-note-timer');
                const previewDiv = voiceNoteItem.querySelector('.voice-note-preview');

                const recorderState = {
                    mediaRecorder: null,
                    audioChunks: [],
                    isRecording: false,
                    startTime: null,
                    interval: null,
                    audioBlob: null
                };

                voiceNoteRecorders.set(recordBtn, recorderState);

                recordBtn.addEventListener('click', async function() {
                    if (recorderState.isRecording) {
                        stopRecording(recordBtn);
                    } else {
                        try {
                            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                            startRecording(recordBtn, stream);
                        } catch (error) {
                            console.error('Error accessing microphone:', error);
                            showToast('danger', 'Could not access microphone. Please grant permission.');
                        }
                    }
                });

                function startRecording(btn, stream) {
                    const state = voiceNoteRecorders.get(btn);
                    state.audioChunks = [];
                    state.audioBlob = null;
                    state.mediaRecorder = new MediaRecorder(stream);

                    state.mediaRecorder.ondataavailable = function(event) {
                        if (event.data.size > 0) {
                            state.audioChunks.push(event.data);
                        }
                    };

                    state.mediaRecorder.onstop = function() {
                        const audioBlob = new Blob(state.audioChunks, { type: 'audio/webm' });
                        state.audioBlob = audioBlob;
                        displayPreview(btn, audioBlob, state.startTime);
                        stream.getTracks().forEach(track => track.stop());
                    };

                    state.mediaRecorder.start();
                    state.isRecording = true;
                    state.startTime = Date.now();

                    // Update UI
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-danger');
                    btn.innerHTML = '<i class="fas fa-stop"></i> Stop';
                    statusBadge.style.display = 'inline-block';

                    // Start timer
                    state.interval = setInterval(() => updateTimer(btn), 1000);

                    // Auto-stop after 2 minutes
                    setTimeout(() => {
                        if (state.isRecording) {
                            stopRecording(btn);
                            showToast('info', 'Recording stopped: Maximum duration reached (2 minutes)');
                        }
                    }, MAX_RECORDING_TIME);
                }

                function stopRecording(btn) {
                    const state = voiceNoteRecorders.get(btn);
                    if (state.mediaRecorder && state.mediaRecorder.state !== 'inactive') {
                        state.mediaRecorder.stop();
                    }

                    state.isRecording = false;
                    clearInterval(state.interval);

                    // Reset UI
                    btn.classList.remove('btn-danger');
                    btn.classList.add('btn-success');
                    btn.innerHTML = '<i class="fas fa-circle"></i> Record';
                    statusBadge.style.display = 'none';
                    timer.textContent = '0:00';
                }

                function updateTimer(btn) {
                    const state = voiceNoteRecorders.get(btn);
                    const elapsed = Date.now() - state.startTime;
                    const seconds = Math.floor(elapsed / 1000);
                    const minutes = Math.floor(seconds / 60);
                    const remainingSeconds = seconds % 60;
                    timer.textContent = `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
                }

                function displayPreview(btn, audioBlob, startTime) {
                    const audioUrl = URL.createObjectURL(audioBlob);
                    const duration = ((Date.now() - startTime) / 1000).toFixed(1);
                    
                    previewDiv.innerHTML = `
                        <div class="alert alert-success d-flex align-items-center justify-content-between p-2" role="alert">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fas fa-microphone-alt text-success"></i>
                                <div>
                                    <audio controls style="height: 30px; max-width: 250px;">
                                        <source src="${audioUrl}" type="audio/webm">
                                        Your browser does not support the audio element.
                                    </audio>
                                    <small class="d-block text-muted">Duration: ${duration}s</small>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    showToast('success', 'Voice note recorded successfully!');
                }
            }

            // Delegate remove voice note button clicks
            document.getElementById('voiceNoteContainer').addEventListener('click', function(e) {
                if (e.target.closest('.remove-voice-note-btn')) {
                    const item = e.target.closest('.voice-note-item');
                    const recordBtn = item.querySelector('.voice-note-record-btn');
                    voiceNoteRecorders.delete(recordBtn);
                    item.remove();
                    voiceNoteCount--;
                    updateVoiceNoteRemoveButtons();
                }
            });

            function updateVoiceNoteRemoveButtons() {
                const items = document.querySelectorAll('.voice-note-item');
                items.forEach((item, index) => {
                    const removeBtn = item.querySelector('.remove-voice-note-btn');
                    if (items.length > 1) {
                        removeBtn.style.display = 'inline-block';
                    } else {
                        removeBtn.style.display = 'none';
                    }
                });
            }
            
            // ==================== LQS PLAY/PAUSE FUNCTIONALITY ====================
            let isLqsPlayActive = false;
            let sortedLqsData = [];
            let currentLqsIndex = 0;

            function getSortedLqsData() {
                // Get all items with valid LQS values and sort by LQS (lowest to highest)
                return tableData
                    .filter(item => {
                        const lqsValue = item.listing_quality_score || item.lqs;
                        return lqsValue && lqsValue !== '' && lqsValue !== null && !isNaN(lqsValue);
                    })
                    .sort((a, b) => {
                        const lqsA = parseFloat(a.listing_quality_score || a.lqs);
                        const lqsB = parseFloat(b.listing_quality_score || b.lqs);
                        return lqsA - lqsB;
                    });
            }

            function startLqsPlay() {
                isLqsPlayActive = true;
                sortedLqsData = getSortedLqsData();
                currentLqsIndex = 0;

                if (sortedLqsData.length === 0) {
                    showToast('warning', 'No items with LQS values found');
                    return;
                }

                document.getElementById('lqs-play-auto').style.display = 'none';
                document.getElementById('lqs-play-pause').style.display = 'inline-block';
                document.getElementById('lqs-play-status').style.display = 'inline-block';
                document.getElementById('lqs-play-backward').disabled = false;
                document.getElementById('lqs-play-forward').disabled = false;

                showCurrentLqsItem();
            }

            function stopLqsPlay() {
                isLqsPlayActive = false;

                document.getElementById('lqs-play-auto').style.display = 'inline-block';
                document.getElementById('lqs-play-pause').style.display = 'none';
                document.getElementById('lqs-play-status').style.display = 'none';
                document.getElementById('lqs-play-backward').disabled = true;
                document.getElementById('lqs-play-forward').disabled = true;

                // Show all data
                renderTable(filteredData);
            }

            function showCurrentLqsItem() {
                if (currentLqsIndex < 0 || currentLqsIndex >= sortedLqsData.length) return;

                const currentItem = sortedLqsData[currentLqsIndex];
                const lqsValue = currentItem.listing_quality_score || currentItem.lqs;

                // Update status badge
                document.getElementById('current-lqs-value').textContent = 
                    `${lqsValue} (${currentLqsIndex + 1}/${sortedLqsData.length}) - ${currentItem.SKU}`;

                // Filter and show only the current item
                filteredData = [currentItem];
                renderTable(filteredData);

                // Update button states
                document.getElementById('lqs-play-backward').disabled = currentLqsIndex === 0;
                document.getElementById('lqs-play-forward').disabled = currentLqsIndex === sortedLqsData.length - 1;
            }

            function playNextLqs() {
                if (!isLqsPlayActive || currentLqsIndex >= sortedLqsData.length - 1) return;
                currentLqsIndex++;
                showCurrentLqsItem();
            }

            function playPreviousLqs() {
                if (!isLqsPlayActive || currentLqsIndex <= 0) return;
                currentLqsIndex--;
                showCurrentLqsItem();
            }
            
            // Event listeners for LQS play controls
            document.getElementById('lqs-play-auto').addEventListener('click', startLqsPlay);
            document.getElementById('lqs-play-pause').addEventListener('click', stopLqsPlay);
            document.getElementById('lqs-play-forward').addEventListener('click', playNextLqs);
            document.getElementById('lqs-play-backward').addEventListener('click', playPreviousLqs);
        });

        // Global functions for audit suggestion and history
        function openEditAuditSuggestion(sku, currentSuggestion) {
            document.getElementById('editAuditSuggestionSku').value = sku;
            document.getElementById('editAuditSuggestionText').value = currentSuggestion;
            document.getElementById('charCount').textContent = currentSuggestion.length;
            
            // Reset link data container to single empty input
            const linkContainer = document.getElementById('linkDataContainer');
            linkContainer.innerHTML = `
                <div class="input-group mb-2 link-data-item">
                    <input type="url" class="form-control link-data-input" placeholder="Enter URL...">
                    <button type="button" class="btn btn-danger btn-sm remove-link-btn" style="display: none;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            // Reset screenshot container to single empty input
            const screenshotContainer = document.getElementById('screenshotContainer');
            screenshotContainer.innerHTML = `
                <div class="screenshot-item mb-2">
                    <div class="input-group">
                        <input type="file" class="form-control screenshot-input" accept="image/*">
                        <button type="button" class="btn btn-danger btn-sm remove-screenshot-btn" style="display: none;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="screenshot-preview mt-2"></div>
                </div>
            `;
            // Reattach screenshot preview handler
            screenshotContainer.querySelectorAll('.screenshot-input').forEach(input => {
                attachScreenshotPreview(input);
            });
            
            // Reset voice note container to single recorder
            const voiceNoteContainer = document.getElementById('voiceNoteContainer');
            voiceNoteContainer.innerHTML = `
                <div class="voice-note-item mb-3">
                    <button type="button" class="btn btn-sm btn-success voice-note-record-btn" title="Click to record voice note">
                        <i class="fas fa-circle"></i> Record
                    </button>
                    <span class="badge bg-danger ms-2 voice-note-status" style="display: none;">Recording... <span class="voice-note-timer">0:00</span></span>
                    <button type="button" class="btn btn-danger btn-sm ms-2 remove-voice-note-btn" style="display: none;">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="voice-note-preview mt-2"></div>
                    <div class="form-text">Click the button to record an audio note (max 2 minutes)</div>
                </div>
            `;
            // Clear voice note recorders map and reattach
            voiceNoteRecorders.clear();
            voiceNoteContainer.querySelectorAll('.voice-note-record-btn').forEach(btn => {
                attachVoiceNoteRecorder(btn);
            });
            
            const modal = new bootstrap.Modal(document.getElementById('editAuditSuggestionModal'));
            modal.show();
        }

        async function saveAuditSuggestion(isFixed = false) {
            const saveBtn = document.getElementById('saveAuditSuggestionBtn');
            const fixedBtn = document.getElementById('fixedAuditSuggestionBtn');
            const originalSaveText = saveBtn.innerHTML;
            const originalFixedText = fixedBtn.innerHTML;
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
            
            try {
                if (isFixed) {
                    fixedBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processing...';
                    fixedBtn.disabled = true;
                    saveBtn.disabled = true;
                } else {
                    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Saving...';
                    saveBtn.disabled = true;
                    fixedBtn.disabled = true;
                }
                
                // Use FormData to support file uploads
                const formData = new FormData();
                formData.append('sku', document.getElementById('editAuditSuggestionSku').value);
                formData.append('audit_suggestion', isFixed ? '' : document.getElementById('editAuditSuggestionText').value.trim());
                formData.append('is_fixed', isFixed ? '1' : '0');
                
                // Collect all link data
                const linkInputs = document.querySelectorAll('.link-data-input');
                linkInputs.forEach((input, index) => {
                    const linkValue = input.value.trim();
                    if (linkValue) {
                        formData.append(`link_data[${index}]`, linkValue);
                    }
                });
                
                // Collect all screenshots
                const screenshotInputs = document.querySelectorAll('.screenshot-input');
                screenshotInputs.forEach((input, index) => {
                    if (input.files.length > 0) {
                        formData.append(`screenshots[${index}]`, input.files[0]);
                    }
                });
                
                // Collect all voice notes
                let voiceNoteIndex = 0;
                voiceNoteRecorders.forEach((state, btn) => {
                    if (state.audioBlob) {
                        const timestamp = new Date().getTime();
                        const voiceNoteFile = new File([state.audioBlob], `voice_note_${timestamp}_${voiceNoteIndex}.webm`, { type: 'audio/webm' });
                        formData.append(`voice_notes[${voiceNoteIndex}]`, voiceNoteFile);
                        voiceNoteIndex++;
                    }
                });
                
                // Clear textarea if Fixed is clicked
                if (isFixed) {
                    document.getElementById('editAuditSuggestionText').value = '';
                    document.getElementById('charCount').textContent = '0';
                }
                
                const response = await fetch('/a-plus-images-master/update-audit-suggestion', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: formData
                });
                
                const data = await response.json();
                
                if (!response.ok) {
                    throw new Error(data.message || 'Failed to save audit suggestion');
                }
                
                showToast('success', isFixed ? 'Marked as Fixed successfully!' : 'Audit suggestion saved successfully!');
                
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('editAuditSuggestionModal'));
                modal.hide();
                
                // Reload data
                setTimeout(() => {
                    location.reload();
                }, 500);
            } catch (error) {
                console.error('Error saving:', error);
                showToast('danger', error.message || 'Failed to save audit suggestion');
            } finally {
                saveBtn.innerHTML = originalSaveText;
                saveBtn.disabled = false;
                fixedBtn.innerHTML = originalFixedText;
                fixedBtn.disabled = false;
            }
        }

        // Image Upload Functions
        function openImageUploadModal(sku, type) {
            document.getElementById('imageUploadSku').value = sku;
            document.getElementById('imageUploadType').value = type;
            document.getElementById('imageTypeLabel').textContent = type.charAt(0).toUpperCase() + type.slice(1);
            document.getElementById('imageFileInput').value = '';
            document.getElementById('imagePreviewContainer').style.display = 'none';
            
            const modal = new bootstrap.Modal(document.getElementById('imageUploadModal'));
            modal.show();
        }

        // Preview image when selected
        document.getElementById('imageFileInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('imagePreview').src = e.target.result;
                    document.getElementById('imagePreviewContainer').style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                document.getElementById('imagePreviewContainer').style.display = 'none';
            }
        });

        // Save image upload
        document.getElementById('saveImageBtn').addEventListener('click', async function() {
            const saveBtn = this;
            const originalText = saveBtn.innerHTML;
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
            
            const fileInput = document.getElementById('imageFileInput');
            if (!fileInput.files.length) {
                showToast('warning', 'Please select an image file');
                return;
            }
            
            try {
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Uploading...';
                saveBtn.disabled = true;
                
                const formData = new FormData();
                formData.append('sku', document.getElementById('imageUploadSku').value);
                formData.append('image_type', document.getElementById('imageUploadType').value);
                formData.append('image_file', fileInput.files[0]);
                
                const response = await fetch('/a-plus-images-master/upload-image', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: formData
                });
                
                const data = await response.json();
                
                if (!response.ok) {
                    throw new Error(data.message || 'Failed to upload image');
                }
                
                showToast('success', 'Image uploaded successfully!');
                
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('imageUploadModal'));
                modal.hide();
                
                // Reload data
                setTimeout(() => {
                    location.reload();
                }, 500);
            } catch (error) {
                console.error('Error uploading image:', error);
                showToast('danger', error.message || 'Failed to upload image');
            } finally {
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
            }
        });

        async function viewHistory(sku) {
            const modal = new bootstrap.Modal(document.getElementById('viewHistoryModal'));
            const historyContent = document.getElementById('historyContent');
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
            
            // Show loading
            historyContent.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `;
            
            modal.show();
            
            try {
                const response = await fetch(`/a-plus-images-master/audit-history/${encodeURIComponent(sku)}`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    }
                });
                
                const data = await response.json();
                
                if (!response.ok) {
                    throw new Error(data.message || 'Failed to load history');
                }
                
                if (data.history && data.history.length > 0) {
                    let historyHtml = '<div class="list-group">';
                    data.history.forEach(item => {
                        const date = new Date(item.created_at).toLocaleString();
                        
                        // Check if it's a FIXED action
                        if (item.action_type === 'FIXED') {
                            historyHtml += `
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between align-items-start">
                                        <div>
                                            <span class="badge bg-success me-2">
                                                <i class="fas fa-check-circle me-1"></i>FIXED
                                            </span>
                                            <span class="text-muted">Cleared by <strong>${item.user_name || 'Unknown User'}</strong></span>
                                        </div>
                                        <small class="text-muted">${date}</small>
                                    </div>
                                </div>
                            `;
                        } else {
                            historyHtml += `
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><i class="fas fa-user me-2"></i>${item.user_name || 'Unknown User'}</h6>
                                        <small class="text-muted">${date}</small>
                                    </div>
                                    <p class="mb-1">${item.audit_suggestion || '<em class="text-muted">No suggestion</em>'}</p>
                                </div>
                            `;
                        }
                    });
                    historyHtml += '</div>';
                    historyContent.innerHTML = historyHtml;
                } else {
                    historyContent.innerHTML = '<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No history found for this SKU.</div>';
                }
            } catch (error) {
                console.error('Error loading history:', error);
                historyContent.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>${error.message || 'Failed to load history'}</div>`;
            }
        }

        // Toast notification function (if not already defined)
        function showToast(type, message) {
            // Remove existing toasts
            document.querySelectorAll('.custom-toast').forEach(t => t.remove());
            
            const toast = document.createElement('div');
            toast.className = `custom-toast toast align-items-center text-bg-${type} border-0 show position-fixed top-0 end-0 m-4`;
            toast.style.zIndex = 2000;
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'assertive');
            toast.setAttribute('aria-atomic', 'true');
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            `;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 500);
            }, 3000);

            toast.querySelector('[data-bs-dismiss="toast"]').onclick = () => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 500);
            };
        }

        // Toggle Status Function
        async function toggleStatus(sku, currentStatus) {
            const button = document.querySelector(`.status-toggle-btn[data-sku="${sku}"]`);
            if (!button || button.classList.contains('loading')) return;
            
            const newStatus = currentStatus === 'green' ? 'red' : 'green';
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
            
            try {
                button.classList.add('loading');
                
                const response = await fetch('/a-plus-images-master/toggle-status', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        sku: sku,
                        status: newStatus
                    })
                });
                
                const data = await response.json();
                
                if (!response.ok) {
                    throw new Error(data.message || 'Failed to toggle status');
                }
                
                // Update button appearance
                button.classList.remove('red', 'green', 'loading');
                button.classList.add(newStatus);
                button.setAttribute('onclick', `toggleStatus('${sku}', '${newStatus}')`);
                
                showToast('success', 'Status updated successfully!');
            } catch (error) {
                console.error('Error toggling status:', error);
                showToast('danger', error.message || 'Failed to toggle status');
                button.classList.remove('loading');
            }
        }

        // View Status History Function
        async function viewStatusHistory(sku) {
            const modal = new bootstrap.Modal(document.getElementById('viewStatusHistoryModal'));
            const statusHistoryContent = document.getElementById('statusHistoryContent');
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
            
            // Show loading
            statusHistoryContent.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `;
            
            modal.show();
            
            try {
                const response = await fetch(`/a-plus-images-master/status-history/${encodeURIComponent(sku)}`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    }
                });
                
                const data = await response.json();
                
                if (!response.ok) {
                    throw new Error(data.message || 'Failed to load status history');
                }
                
                if (data.history && data.history.length > 0) {
                    let historyHtml = '<div class="list-group">';
                    data.history.forEach(item => {
                        const date = new Date(item.created_at).toLocaleString();
                        const statusColor = item.status === 'green' ? '#28a745' : '#dc3545';
                        const statusIcon = item.status === 'green' ? '🟢' : '🔴';
                        historyHtml += `
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><i class="fas fa-user me-2"></i>${item.user_name || 'Unknown User'}</h6>
                                        <p class="mb-1">
                                            Status changed to: <span style="font-weight: bold; color: ${statusColor};">${statusIcon} ${item.status.toUpperCase()}</span>
                                        </p>
                                    </div>
                                    <small class="text-muted">${date}</small>
                                </div>
                            </div>
                        `;
                    });
                    historyHtml += '</div>';
                    statusHistoryContent.innerHTML = historyHtml;
                } else {
                    statusHistoryContent.innerHTML = '<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No status history found for this SKU.</div>';
                }
            } catch (error) {
                console.error('Error loading status history:', error);
                statusHistoryContent.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>${error.message || 'Failed to load status history'}</div>`;
            }
        }

        // Handle Push Function
        async function handlePush(sku) {
            const pushBtn = document.querySelector(`.push-btn[data-sku="${sku}"]`);
            if (!pushBtn || pushBtn.disabled) return;
            
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
            const originalHtml = pushBtn.innerHTML;
            
            try {
                pushBtn.disabled = true;
                pushBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                
                const response = await fetch('/a-plus-images-master/push', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        sku: sku
                    })
                });
                
                const data = await response.json();
                
                if (!response.ok) {
                    throw new Error(data.message || 'Failed to push data');
                }
                
                showToast('success', data.message || 'Data pushed successfully!');
            } catch (error) {
                console.error('Error pushing data:', error);
                showToast('danger', error.message || 'Failed to push data');
            } finally {
                pushBtn.disabled = false;
                pushBtn.innerHTML = originalHtml;
            }
        }

        // View Competitors function
        async function viewCompetitors(sku) {
            const modal = new bootstrap.Modal(document.getElementById('competitorsModal'));
            const competitorsList = document.getElementById('competitorsList');
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
            
            // Set SKU in modal and form
            document.getElementById('competitorsSku').textContent = sku;
            document.getElementById('compSku').value = sku;
            
            // Show loading
            competitorsList.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading competitors...</p>
                </div>
            `;
            
            modal.show();
            
            try {
                console.log('Fetching competitors for SKU:', sku);
                const response = await fetch(`/amazon/competitors?sku=${encodeURIComponent(sku)}`, {
                    method: 'GET',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    }
                });
                
                console.log('Response status:', response.status);
                const data = await response.json();
                console.log('Response data:', data);
                
                if (!response.ok) {
                    throw new Error(data.message || data.error || 'Failed to load competitors');
                }
                
                if (data.success && data.competitors && data.competitors.length > 0) {
                    renderCompetitorsList(data.competitors, data.lowest_price);
                } else {
                    competitorsList.innerHTML = `
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>No competitors found yet. Add your first competitor above!
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading competitors:', error);
                competitorsList.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>Error: ${error.message || 'Failed to load competitors'}. Please try again.
                    </div>
                `;
            }
        }

        // Render Competitors List
        function renderCompetitorsList(competitors, lowestPrice) {
            // Local escape function to prevent XSS
            function escapeHtml(text) {
                if (text == null) return '';
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
            
            if (!competitors || competitors.length === 0) {
                document.getElementById('competitorsList').innerHTML = `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No competitors found for this SKU
                    </div>
                `;
                return;
            }
            
            let html = '<div class="table-responsive"><table class="table table-hover table-bordered table-sm">';
            html += `
                <thead class="table-light">
                    <tr>
                        <th style="width: 30px;">#</th>
                        <th style="width: 60px;">Image</th>
                        <th style="width: 100px;">ASIN</th>
                        <th style="width: 250px;">Product Title</th>
                        <th>Seller</th>
                        <th style="width: 80px;">Price</th>
                        <th style="width: 90px;">Revenue<br><small>(30d)</small></th>
                        <th style="width: 70px;">Units<br><small>(30d)</small></th>
                        <th style="width: 100px;">Buy Box</th>
                        <th style="width: 60px;">Type</th>
                        <th style="width: 70px;">Rating</th>
                        <th style="width: 70px;">Reviews</th>
                        <th style="width: 60px;">Link</th>
                        <th style="width: 80px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
            `;
            
            competitors.forEach((item, index) => {
                const isLowest = (parseFloat(item.price) === parseFloat(lowestPrice));
                const rowClass = isLowest ? 'table-success' : '';
                const priceFormatted = '$' + parseFloat(item.price).toFixed(2);
                const priceBadge = isLowest ? 
                    `<span class="badge bg-success">${priceFormatted} <i class="fas fa-trophy"></i></span>` : 
                    `<strong>${priceFormatted}</strong>`;
                
                const productLink = item.link || item.product_link || '#';
                const productTitle = item.title || item.product_title || 'N/A';
                const productTitleShort = productTitle.length > 50 ? productTitle.substring(0, 50) + '...' : productTitle;
                const sellerName = item.seller_name || '—';
                const imageUrl = item.image || '';
                const imageHtml = imageUrl ? `<img src="${imageUrl}" style="width: 50px; height: 50px; object-fit: contain;" />` : '<span style="color: #999;">—</span>';
                
                const revenue = item.monthly_revenue ? `<span style="color: #28a745; font-weight: 600;">$${parseFloat(item.monthly_revenue).toFixed(0)}</span>` : '<span style="color: #999;">—</span>';
                const units = item.monthly_units_sold ? `<span style="color: #007bff; font-weight: 600;">${parseInt(item.monthly_units_sold)}</span>` : '<span style="color: #999;">—</span>';
                const buyBox = item.buy_box_owner ? `<span style="font-size: 11px;">${item.buy_box_owner}</span>` : '<span style="color: #999;">—</span>';
                const sellerType = item.seller_type ? `<span class="badge bg-${item.seller_type === 'FBA' ? 'warning' : 'secondary'}">${item.seller_type}</span>` : '<span style="color: #999;">—</span>';
                
                const rating = item.rating ? `<span style="color: #ffc107;">${parseFloat(item.rating).toFixed(1)} <i class="fas fa-star"></i></span>` : '<span style="color: #999;">—</span>';
                const reviews = item.reviews ? `<span style="font-weight: 600;">${parseInt(item.reviews).toLocaleString()}</span>` : '<span style="color: #999;">—</span>';
                
                html += `
                    <tr class="${rowClass}">
                        <td style="text-align: center;">${index + 1}</td>
                        <td style="text-align: center;">${imageHtml}</td>
                        <td><strong>${escapeHtml(item.asin || 'N/A')}</strong></td>
                        <td style="text-align: center;">
                            <div class="comp-product-title-dot" data-product-title="${escapeHtml(productTitle)}">
                                <span class="green-dot-indicator"></span>
                                <div class="product-title-tooltip">${escapeHtml(productTitle)}</div>
                            </div>
                        </td>
                        <td style="font-size: 11px;">${escapeHtml(sellerName)}</td>
                        <td style="text-align: right;">${priceBadge}</td>
                        <td style="text-align: right;">${revenue}</td>
                        <td style="text-align: center;">${units}</td>
                        <td>${buyBox}</td>
                        <td style="text-align: center;">${sellerType}</td>
                        <td style="text-align: center;">${rating}</td>
                        <td style="text-align: center;">${reviews}</td>
                        <td style="text-align: center;">
                            <a href="${escapeHtml(productLink)}" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        </td>
                        <td style="text-align: center;">
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteCompetitor('${escapeHtml(item.id || '')}', '${escapeHtml(item.sku || '')}')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            html += '</tbody></table></div>';
            document.getElementById('competitorsList').innerHTML = html;
        }

        // Add Competitor Form Submit
        document.getElementById('addCompetitorForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const sku = document.getElementById('compSku').value;
            const asin = document.getElementById('compAsin').value.trim();
            const price = parseFloat(document.getElementById('compPrice').value);
            const link = document.getElementById('compLink').value.trim();
            const marketplace = document.getElementById('compMarketplace').value;
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
            
            if (!asin) {
                showToast('danger', 'ASIN is required');
                return;
            }
            
            if (!price || price <= 0) {
                showToast('danger', 'Valid price is required');
                return;
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalHtml = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding...';
            
            try {
                const response = await fetch('/amazon/lmp/add', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        sku: sku,
                        asin: asin,
                        price: price,
                        product_link: link || null,
                        marketplace: marketplace
                    })
                });
                
                const data = await response.json();
                
                if (!response.ok) {
                    throw new Error(data.message || 'Failed to add competitor');
                }
                
                showToast('success', 'Competitor added successfully');
                
                // Reset form
                this.reset();
                document.getElementById('compSku').value = sku;
                
                // Reload competitors list
                viewCompetitors(sku);
                
            } catch (error) {
                console.error('Error adding competitor:', error);
                showToast('danger', error.message || 'Failed to add competitor');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHtml;
            }
        });

        // Delete Competitor
        async function deleteCompetitor(competitorId, sku) {
            if (!confirm('Are you sure you want to delete this competitor?')) {
                return;
            }
            
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
            
            try {
                const response = await fetch('/amazon/lmp/delete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        id: competitorId
                    })
                });
                
                const data = await response.json();
                
                if (!response.ok) {
                    throw new Error(data.message || 'Failed to delete competitor');
                }
                
                showToast('success', 'Competitor deleted successfully');
                
                // Reload competitors list
                viewCompetitors(sku);
                
            } catch (error) {
                console.error('Error deleting competitor:', error);
                showToast('danger', error.message || 'Failed to delete competitor');
            }
        }
    </script>
@endsection

