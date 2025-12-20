@extends('layouts.vertical', ['title' => 'Dim & Wt Master', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

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
            overflow-x: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            background-color: white;
            width: 100%;
        }

        .table-responsive thead th {
            position: sticky;
            top: 0;
            background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%) !important;
            color: white;
            z-index: 10;
            padding: 8px 6px;
            font-weight: 600;
            border-bottom: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            font-size: 10px;
            letter-spacing: 0.2px;
            text-transform: uppercase;
            transition: all 0.2s ease;
            white-space: nowrap;
            text-overflow: ellipsis;
            overflow: hidden;
        }

        .table-responsive thead th:hover {
            background: linear-gradient(135deg, #1a56b7 0%, #0a3d8f 100%) !important;
        }

        .table-responsive thead input {
            background-color: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 4px;
            color: #333;
            padding: 3px 5px;
            margin-top: 4px;
            font-size: 10px;
            width: 100%;
            transition: all 0.2s;
        }

        .table-responsive thead select {
            background-color: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 4px;
            color: #333;
            padding: 2px 4px;
            margin-top: 4px;
            font-size: 9px;
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
            padding: 6px 8px;
            vertical-align: middle;
            border-bottom: 1px solid #edf2f9;
            font-size: 11px;
            color: #495057;
            transition: all 0.2s ease;
            white-space: nowrap;
            text-overflow: ellipsis;
            overflow: hidden;
            max-width: 120px;
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
            table-layout: fixed;
        }

        /* Column width constraints */
        .table-responsive th:nth-child(1),
        .table-responsive td:nth-child(1) {
            width: 40px;
            min-width: 40px;
            max-width: 40px;
        }

        .table-responsive th:nth-child(2),
        .table-responsive td:nth-child(2) {
            width: 50px;
            min-width: 50px;
            max-width: 50px;
        }

        .table-responsive th:nth-child(3),
        .table-responsive td:nth-child(3) {
            width: 100px;
            min-width: 100px;
            max-width: 100px;
        }

        .table-responsive th:nth-child(4),
        .table-responsive td:nth-child(4) {
            width: 120px;
            min-width: 120px;
            max-width: 120px;
        }

        .table-responsive th:nth-child(5),
        .table-responsive td:nth-child(5) {
            width: 70px;
            min-width: 70px;
            max-width: 70px;
        }

        .table-responsive th:nth-child(6),
        .table-responsive td:nth-child(6) {
            width: 60px;
            min-width: 60px;
            max-width: 60px;
        }

        .table-responsive th:nth-child(n+7):nth-child(-n+11),
        .table-responsive td:nth-child(n+7):nth-child(-n+11) {
            width: 75px;
            min-width: 75px;
            max-width: 75px;
        }

        .table-responsive th:nth-child(n+12):nth-child(-n+19),
        .table-responsive td:nth-child(n+12):nth-child(-n+19) {
            width: 80px;
            min-width: 80px;
            max-width: 80px;
        }

        .table-responsive th:last-child,
        .table-responsive td:last-child {
            width: 80px;
            min-width: 80px;
            max-width: 80px;
        }

        /* Ensure table fits container */
        #dim-wt-master-datatable {
            width: 100% !important;
            table-layout: fixed;
        }

        /* Prevent horizontal overflow */
        .card-body {
            overflow-x: hidden;
        }

        .edit-btn {
            border-radius: 4px;
            padding: 3px 6px;
            transition: all 0.2s;
            background: #fff;
            border: 1px solid #1a56b7;
            color: #1a56b7;
            font-size: 11px;
        }

        .edit-btn:hover {
            background: #1a56b7;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(26, 86, 183, 0.2);
        }

        .delete-btn {
            border-radius: 4px;
            padding: 3px 6px;
            transition: all 0.2s;
            background: #fff;
            border: 1px solid #dc3545;
            color: #dc3545;
            font-size: 11px;
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

        .row-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #1a56b7;
        }

        #selectAll {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #1a56b7;
        }

        #pushDataBtn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        #addDimWtBtn {
            white-space: nowrap;
        }

        .d-flex.gap-2 > button {
            flex-shrink: 0;
        }

        @media (max-width: 768px) {
            .d-flex.justify-content-end {
                justify-content: flex-start !important;
            }
            
            .d-flex.gap-2 > button {
                flex: 1 1 auto;
                min-width: 0;
            }
        }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('layouts.shared.page-title', [
        'page_title' => 'Dim & Wt Master',
        'sub_title' => 'Dimensions & Weight Master Analysis',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" id="customSearch" class="form-control"
                                    placeholder="Search dimensions & weight...">
                                <button class="btn btn-outline-secondary" type="button" id="clearSearch">Clear</button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-end flex-wrap gap-2">
                                <button type="button" class="btn btn-primary" id="addDimWtBtn">
                                    <i class="fas fa-plus me-1"></i> Add Dim & Wt Data
                                </button>
                                <button type="button" class="btn btn-primary" id="pushDataBtn" disabled>
                                    <i class="fas fa-cloud-upload-alt me-1"></i> Push Data
                                </button>
                                <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#importExcelModal">
                                    <i class="fas fa-file-upload me-1"></i> Import
                                </button>
                                <button type="button" class="btn btn-success" id="downloadExcel">
                                    <i class="fas fa-file-excel me-1"></i> Download
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table id="dim-wt-master-datatable" class="table dt-responsive nowrap w-100">
                            <thead>
                                <tr>
                                    <th class="text-center">
                                        <input type="checkbox" id="selectAll" title="Select All" style="width: 16px; height: 16px;">
                                    </th>
                                    <th>Img</th>
                                    <th>
                                        <div style="font-size: 9px;">Parent <span id="parentCount" style="font-size: 8px;">(0)</span></div>
                                        <input type="text" id="parentSearch" class="form-control-sm"
                                            placeholder="Search" style="font-size: 9px; padding: 2px 4px;">
                                    </th>
                                    <th>
                                        <div style="font-size: 9px;">SKU <span id="skuCount" style="font-size: 8px;">(0)</span></div>
                                        <input type="text" id="skuSearch" class="form-control-sm"
                                            placeholder="Search" style="font-size: 9px; padding: 2px 4px;">
                                    </th>
                                    <th>Status</th>
                                    <th>INV</th>
                                    <th>
                                        <div style="font-size: 9px;">WT ACT <span id="wtActMissingCount" class="text-danger" style="font-weight: bold; font-size: 8px;">(0)</span></div>
                                        <select id="filterWtAct" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px;">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div style="font-size: 9px;">WT DECL <span id="wtDeclMissingCount" class="text-danger" style="font-weight: bold; font-size: 8px;">(0)</span></div>
                                        <select id="filterWtDecl" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px;">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div style="font-size: 9px;">L <span id="lMissingCount" class="text-danger" style="font-weight: bold; font-size: 8px;">(0)</span></div>
                                        <select id="filterL" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px;">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div style="font-size: 9px;">W <span id="wMissingCount" class="text-danger" style="font-weight: bold; font-size: 8px;">(0)</span></div>
                                        <select id="filterW" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px;">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div style="font-size: 9px;">H <span id="hMissingCount" class="text-danger" style="font-weight: bold; font-size: 8px;">(0)</span></div>
                                        <select id="filterH" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px;">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                        </select>
                                    </th>
                                    <th style="font-size: 9px;">CTN L</th>
                                    <th style="font-size: 9px;">CTN W</th>
                                    <th style="font-size: 9px;">CTN H</th>
                                    <th style="font-size: 9px;">CTN CBM</th>
                                    <th style="font-size: 9px;">CTN QTY</th>
                                    <th style="font-size: 9px;">CBM/Each</th>
                                    <th style="font-size: 9px;">CBM E</th>
                                    <th style="font-size: 9px;">CTN GWT</th>
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
                        <div class="loading-text">Loading Dim & Wt Master Data...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Dim & Wt Master Modal -->
    <div class="modal fade" id="addDimWtModal" tabindex="-1" aria-labelledby="addDimWtModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addDimWtModalLabel">Add Dim & Wt Data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addDimWtForm">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="addSku" class="form-label">SKU <span class="text-danger">*</span></label>
                                <select class="form-control" id="addSku" name="sku" required>
                                    <option value="">Select SKU</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="addWtAct" class="form-label">WT ACT</label>
                                <input type="number" step="0.01" class="form-control" id="addWtAct" name="wt_act" placeholder="Enter WT ACT">
                            </div>
                            <div class="col-md-6">
                                <label for="addWtDecl" class="form-label">WT DECL</label>
                                <input type="number" step="0.01" class="form-control" id="addWtDecl" name="wt_decl" placeholder="Enter WT DECL">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="addL" class="form-label">L</label>
                                <input type="number" step="0.01" class="form-control" id="addL" name="l" placeholder="Enter L">
                            </div>
                            <div class="col-md-4">
                                <label for="addW" class="form-label">W</label>
                                <input type="number" step="0.01" class="form-control" id="addW" name="w" placeholder="Enter W">
                            </div>
                            <div class="col-md-4">
                                <label for="addH" class="form-label">H</label>
                                <input type="number" step="0.01" class="form-control" id="addH" name="h" placeholder="Enter H">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="addCtnL" class="form-label">CTN (L)</label>
                                <input type="number" step="0.01" class="form-control" id="addCtnL" name="ctn_l" placeholder="Enter CTN (L)">
                            </div>
                            <div class="col-md-4">
                                <label for="addCtnW" class="form-label">CTN (W)</label>
                                <input type="number" step="0.01" class="form-control" id="addCtnW" name="ctn_w" placeholder="Enter CTN (W)">
                            </div>
                            <div class="col-md-4">
                                <label for="addCtnH" class="form-label">CTN (H)</label>
                                <input type="number" step="0.01" class="form-control" id="addCtnH" name="ctn_h" placeholder="Enter CTN (H)">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="addCtnCbm" class="form-label">CTN (CBM) <small class="text-muted">(Auto-calculated)</small></label>
                                <input type="number" step="0.000001" class="form-control" id="addCtnCbm" name="ctn_cbm" placeholder="Auto-calculated" readonly>
                            </div>
                            <div class="col-md-4">
                                <label for="addCtnQty" class="form-label">CTN (QTY)</label>
                                <input type="number" step="0.01" class="form-control" id="addCtnQty" name="ctn_qty" placeholder="Enter CTN (QTY)">
                            </div>
                            <div class="col-md-4">
                                <label for="addCtnCbmEach" class="form-label">CTN (CBM/Each) <small class="text-muted">(Auto-calculated)</small></label>
                                <input type="number" step="0.000001" class="form-control" id="addCtnCbmEach" name="ctn_cbm_each" placeholder="Auto-calculated" readonly>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="addCbmE" class="form-label">CBM (E)</label>
                                <input type="number" step="0.01" class="form-control" id="addCbmE" name="cbm_e" placeholder="Enter CBM (E)">
                            </div>
                            <div class="col-md-6">
                                <label for="addCtnGwt" class="form-label">CTN GWT</label>
                                <input type="number" step="0.01" class="form-control" id="addCtnGwt" name="ctn_gwt" placeholder="Enter CTN GWT">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveAddDimWtBtn">
                        <i class="fas fa-save me-2"></i> Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Dim & Wt Master Modal -->
    <div class="modal fade" id="editDimWtModal" tabindex="-1" aria-labelledby="editDimWtModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editDimWtModalLabel">Edit Dim & Wt Master</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editDimWtForm">
                        <input type="hidden" id="editProductId" name="product_id">
                        <input type="hidden" id="editSku" name="sku">
                        <input type="hidden" id="editParent" name="parent">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editWtAct" class="form-label">WT ACT</label>
                                <input type="number" step="0.01" class="form-control" id="editWtAct" name="wt_act" placeholder="Enter WT ACT">
                            </div>
                            <div class="col-md-6">
                                <label for="editWtDecl" class="form-label">WT DECL</label>
                                <input type="number" step="0.01" class="form-control" id="editWtDecl" name="wt_decl" placeholder="Enter WT DECL">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="editL" class="form-label">L</label>
                                <input type="number" step="0.01" class="form-control" id="editL" name="l" placeholder="Enter L">
                            </div>
                            <div class="col-md-4">
                                <label for="editW" class="form-label">W</label>
                                <input type="number" step="0.01" class="form-control" id="editW" name="w" placeholder="Enter W">
                            </div>
                            <div class="col-md-4">
                                <label for="editH" class="form-label">H</label>
                                <input type="number" step="0.01" class="form-control" id="editH" name="h" placeholder="Enter H">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="editCtnL" class="form-label">CTN (L)</label>
                                <input type="number" step="0.01" class="form-control" id="editCtnL" name="ctn_l" placeholder="Enter CTN (L)">
                            </div>
                            <div class="col-md-4">
                                <label for="editCtnW" class="form-label">CTN (W)</label>
                                <input type="number" step="0.01" class="form-control" id="editCtnW" name="ctn_w" placeholder="Enter CTN (W)">
                            </div>
                            <div class="col-md-4">
                                <label for="editCtnH" class="form-label">CTN (H)</label>
                                <input type="number" step="0.01" class="form-control" id="editCtnH" name="ctn_h" placeholder="Enter CTN (H)">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="editCtnCbm" class="form-label">CTN (CBM) <small class="text-muted">(Auto-calculated)</small></label>
                                <input type="number" step="0.000001" class="form-control" id="editCtnCbm" name="ctn_cbm" placeholder="Auto-calculated" readonly>
                            </div>
                            <div class="col-md-4">
                                <label for="editCtnQty" class="form-label">CTN (QTY)</label>
                                <input type="number" step="0.01" class="form-control" id="editCtnQty" name="ctn_qty" placeholder="Enter CTN (QTY)">
                            </div>
                            <div class="col-md-4">
                                <label for="editCtnCbmEach" class="form-label">CTN (CBM/Each) <small class="text-muted">(Auto-calculated)</small></label>
                                <input type="number" step="0.000001" class="form-control" id="editCtnCbmEach" name="ctn_cbm_each" placeholder="Auto-calculated" readonly>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editCbmE" class="form-label">CBM (E)</label>
                                <input type="number" step="0.01" class="form-control" id="editCbmE" name="cbm_e" placeholder="Enter CBM (E)">
                            </div>
                            <div class="col-md-6">
                                <label for="editCtnGwt" class="form-label">CTN GWT</label>
                                <input type="number" step="0.01" class="form-control" id="editCtnGwt" name="ctn_gwt" placeholder="Enter CTN GWT">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveDimWtBtn">
                        <i class="fas fa-save me-2"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Excel Modal -->
    <div class="modal fade" id="importExcelModal" tabindex="-1" aria-labelledby="importExcelModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%); color: white;">
                    <h5 class="modal-title" id="importExcelModalLabel">
                        <i class="fas fa-upload me-2"></i>Import Dim & Wt Data
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Instructions:</strong>
                        <ol class="mb-0 mt-2">
                            <li>Download the sample file below</li>
                            <li>Fill in the dim & wt data (WT ACT, WT DECL, L, W, H, CTN L, CTN W, CTN H, CTN QTY, CBM E, CTN GWT)</li>
                            <li>Upload the completed file</li>
                        </ol>
                    </div>
                    
                    <div class="mb-3">
                        <button type="button" class="btn btn-outline-primary w-100" id="downloadSampleBtn">
                            <i class="fas fa-download me-2"></i>Download Sample File
                        </button>
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

            // Load Dim & Wt data from server
            function loadData() {
                const cacheParam = '?ts=' + new Date().getTime();
                makeRequest('/dim-wt-master-data-view' + cacheParam, 'GET')
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
                        } else {
                            console.error('Invalid data format received from server');
                        }
                        document.getElementById('rainbow-loader').style.display = 'none';
                    })
                    .catch(error => {
                        console.error('Failed to load Dim & Wt data: ' + error.message);
                        document.getElementById('rainbow-loader').style.display = 'none';
                    });
            }

            // Render table
            function renderTable(data) {
                const tbody = document.getElementById('table-body');
                tbody.innerHTML = '';

                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="20" class="text-center">No data found</td></tr>';
                    return;
                }

                data.forEach(item => {
                    const row = document.createElement('tr');

                    // Checkbox column
                    const checkboxCell = document.createElement('td');
                    checkboxCell.className = 'text-center';
                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.className = 'row-checkbox';
                    checkbox.value = escapeHtml(item.SKU);
                    checkbox.setAttribute('data-sku', escapeHtml(item.SKU));
                    checkbox.setAttribute('data-id', escapeHtml(item.id));
                    checkbox.addEventListener('change', function() {
                        updatePushButtonState();
                    });
                    checkboxCell.appendChild(checkbox);
                    row.appendChild(checkboxCell);

                    // Image column
                    const imageCell = document.createElement('td');
                    imageCell.className = 'text-center';
                    imageCell.innerHTML = item.image_path 
                        ? `<img src="${item.image_path}" style="width:30px;height:30px;object-fit:cover;border-radius:4px;">`
                        : '-';
                    row.appendChild(imageCell);

                    // Parent column
                    const parentCell = document.createElement('td');
                    parentCell.title = escapeHtml(item.Parent) || '';
                    parentCell.textContent = escapeHtml(item.Parent) || '-';
                    row.appendChild(parentCell);

                    // SKU column
                    const skuCell = document.createElement('td');
                    skuCell.title = escapeHtml(item.SKU) || '';
                    skuCell.textContent = escapeHtml(item.SKU) || '-';
                    row.appendChild(skuCell);

                    // Status column
                    const statusCell = document.createElement('td');
                    statusCell.textContent = escapeHtml(item.status) || '-';
                    row.appendChild(statusCell);

                    // INV column
                    const invCell = document.createElement('td');
                    if (item.shopify_inv === 0 || item.shopify_inv === "0") {
                        invCell.textContent = "0";
                    } else if (item.shopify_inv === null || item.shopify_inv === undefined || item.shopify_inv === "") {
                        invCell.textContent = "-";
                    } else {
                        invCell.textContent = escapeHtml(item.shopify_inv);
                    }
                    row.appendChild(invCell);

                    // WT ACT column
                    const wtActCell = document.createElement('td');
                    wtActCell.className = 'text-center';
                    wtActCell.textContent = formatNumber(item.wt_act || 0, 2);
                    row.appendChild(wtActCell);

                    // WT DECL column
                    const wtDeclCell = document.createElement('td');
                    wtDeclCell.className = 'text-center';
                    wtDeclCell.textContent = formatNumber(item.wt_decl || 0, 2);
                    row.appendChild(wtDeclCell);

                    // L column
                    const lCell = document.createElement('td');
                    lCell.className = 'text-center';
                    lCell.textContent = formatNumber(item.l || 0, 2);
                    row.appendChild(lCell);

                    // W column
                    const wCell = document.createElement('td');
                    wCell.className = 'text-center';
                    wCell.textContent = formatNumber(item.w || 0, 2);
                    row.appendChild(wCell);

                    // H column
                    const hCell = document.createElement('td');
                    hCell.className = 'text-center';
                    hCell.textContent = formatNumber(item.h || 0, 2);
                    row.appendChild(hCell);

                    // CTN (L) column
                    const ctnLCell = document.createElement('td');
                    ctnLCell.className = 'text-center';
                    ctnLCell.textContent = formatNumber(item.ctn_l || 0, 2);
                    row.appendChild(ctnLCell);

                    // CTN (W) column
                    const ctnWCell = document.createElement('td');
                    ctnWCell.className = 'text-center';
                    ctnWCell.textContent = formatNumber(item.ctn_w || 0, 2);
                    row.appendChild(ctnWCell);

                    // CTN (H) column
                    const ctnHCell = document.createElement('td');
                    ctnHCell.className = 'text-center';
                    ctnHCell.textContent = formatNumber(item.ctn_h || 0, 2);
                    row.appendChild(ctnHCell);

                    // CTN (CBM) column
                    const ctnCbmCell = document.createElement('td');
                    ctnCbmCell.className = 'text-center';
                    ctnCbmCell.textContent = formatNumber(item.ctn_cbm || 0, 6);
                    row.appendChild(ctnCbmCell);

                    // CTN (QTY) column
                    const ctnQtyCell = document.createElement('td');
                    ctnQtyCell.className = 'text-center';
                    ctnQtyCell.textContent = formatNumber(item.ctn_qty || 0, 2);
                    row.appendChild(ctnQtyCell);

                    // CTN (CBM/Each) column
                    const ctnCbmEachCell = document.createElement('td');
                    ctnCbmEachCell.className = 'text-center';
                    ctnCbmEachCell.textContent = formatNumber(item.ctn_cbm_each || 0, 6);
                    row.appendChild(ctnCbmEachCell);

                    // CBM (E) column
                    const cbmECell = document.createElement('td');
                    cbmECell.className = 'text-center';
                    cbmECell.textContent = formatNumber(item.cbm_e || 0, 2);
                    row.appendChild(cbmECell);

                    // CTN GWT column
                    const ctnGwtCell = document.createElement('td');
                    ctnGwtCell.className = 'text-center';
                    ctnGwtCell.textContent = formatNumber(item.ctn_gwt || 0, 2);
                    row.appendChild(ctnGwtCell);

                    // Action column
                    const actionCell = document.createElement('td');
                    actionCell.className = 'text-center';
                    actionCell.innerHTML = `
                        <div class="d-inline-flex gap-1">
                            <button class="btn btn-sm btn-outline-warning edit-btn" data-sku="${escapeHtml(item.SKU)}" title="Edit">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger delete-btn" data-id="${escapeHtml(item.id)}" data-sku="${escapeHtml(item.SKU)}" title="Delete">
                                <i class="bi bi-archive"></i>
                            </button>
                        </div>
                    `;
                    row.appendChild(actionCell);
                    
                    // Add event listener for edit button
                    const editBtn = actionCell.querySelector('.edit-btn');
                    editBtn.addEventListener('click', function() {
                        const sku = this.getAttribute('data-sku');
                        const product = tableData.find(d => d.SKU === sku);
                        if (product) {
                            editDimWt(product);
                        }
                    });

                    tbody.appendChild(row);
                });
            }

            // Update counts
            function updateCounts() {
                const parentSet = new Set();
                let skuCount = 0;
                let wtActMissingCount = 0;
                let wtDeclMissingCount = 0;
                let lMissingCount = 0;
                let wMissingCount = 0;
                let hMissingCount = 0;

                tableData.forEach(item => {
                    if (item.Parent) parentSet.add(item.Parent);
                    if (item.SKU && !String(item.SKU).toUpperCase().includes('PARENT'))
                        skuCount++;
                    
                    // Count missing data for each column
                    if (isMissing(item.wt_act)) wtActMissingCount++;
                    if (isMissing(item.wt_decl)) wtDeclMissingCount++;
                    if (isMissing(item.l)) lMissingCount++;
                    if (isMissing(item.w)) wMissingCount++;
                    if (isMissing(item.h)) hMissingCount++;
                });
                
                document.getElementById('parentCount').textContent = `(${parentSet.size})`;
                document.getElementById('skuCount').textContent = `(${skuCount})`;
                document.getElementById('wtActMissingCount').textContent = `(${wtActMissingCount})`;
                document.getElementById('wtDeclMissingCount').textContent = `(${wtDeclMissingCount})`;
                document.getElementById('lMissingCount').textContent = `(${lMissingCount})`;
                document.getElementById('wMissingCount').textContent = `(${wMissingCount})`;
                document.getElementById('hMissingCount').textContent = `(${hMissingCount})`;
            }

            // Check if value is missing (null, undefined, empty, or 0)
            function isMissing(value) {
                return value === null || value === undefined || value === '' || value === 0 || parseFloat(value) === 0;
            }

            // Apply all filters
            function applyFilters() {
                filteredData = tableData.filter(item => {
                    // Parent search filter
                    const parentSearch = document.getElementById('parentSearch').value.toLowerCase();
                    if (parentSearch && !(item.Parent || '').toLowerCase().includes(parentSearch)) {
                        return false;
                    }

                    // SKU search filter
                    const skuSearch = document.getElementById('skuSearch').value.toLowerCase();
                    if (skuSearch && !(item.SKU || '').toLowerCase().includes(skuSearch)) {
                        return false;
                    }

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

                    // WT ACT filter
                    const filterWtAct = document.getElementById('filterWtAct').value;
                    if (filterWtAct === 'missing' && !isMissing(item.wt_act)) {
                        return false;
                    }

                    // WT DECL filter
                    const filterWtDecl = document.getElementById('filterWtDecl').value;
                    if (filterWtDecl === 'missing' && !isMissing(item.wt_decl)) {
                        return false;
                    }

                    // L filter
                    const filterL = document.getElementById('filterL').value;
                    if (filterL === 'missing' && !isMissing(item.l)) {
                        return false;
                    }

                    // W filter
                    const filterW = document.getElementById('filterW').value;
                    if (filterW === 'missing' && !isMissing(item.w)) {
                        return false;
                    }

                    // H filter
                    const filterH = document.getElementById('filterH').value;
                    if (filterH === 'missing' && !isMissing(item.h)) {
                        return false;
                    }

                    return true;
                });
                renderTable(filteredData);
            }

            // Setup search functionality
            function setupSearch() {
                // Parent search
                const parentSearch = document.getElementById('parentSearch');
                parentSearch.addEventListener('input', function() {
                    applyFilters();
                });

                // SKU search
                const skuSearch = document.getElementById('skuSearch');
                skuSearch.addEventListener('input', function() {
                    applyFilters();
                });

                // Custom search
                const customSearch = document.getElementById('customSearch');
                customSearch.addEventListener('input', function() {
                    applyFilters();
                });

                // Clear search
                document.getElementById('clearSearch').addEventListener('click', function() {
                    customSearch.value = '';
                    parentSearch.value = '';
                    skuSearch.value = '';
                    // Reset all column filters
                    document.getElementById('filterWtAct').value = 'all';
                    document.getElementById('filterWtDecl').value = 'all';
                    document.getElementById('filterL').value = 'all';
                    document.getElementById('filterW').value = 'all';
                    document.getElementById('filterH').value = 'all';
                    applyFilters();
                });

                // Column filters
                document.getElementById('filterWtAct').addEventListener('change', function() {
                    applyFilters();
                });

                document.getElementById('filterWtDecl').addEventListener('change', function() {
                    applyFilters();
                });

                document.getElementById('filterL').addEventListener('change', function() {
                    applyFilters();
                });

                document.getElementById('filterW').addEventListener('change', function() {
                    applyFilters();
                });

                document.getElementById('filterH').addEventListener('change', function() {
                    applyFilters();
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
                    const columns = ["Parent", "SKU", "Status", "INV", "WT ACT", "WT DECL", "L", "W", "H", "CTN (L)", "CTN (W)", "CTN (H)", "CTN (CBM)", "CTN (QTY)", "CTN (CBM/Each)", "CBM (E)", "CTN GWT"];

                    // Column definitions with their data keys
                    const columnDefs = {
                        "Parent": {
                            key: "Parent"
                        },
                        "SKU": {
                            key: "SKU"
                        },
                        "Status": {
                            key: "status"
                        },
                        "INV": {
                            key: "shopify_inv"
                        },
                        "WT ACT": {
                            key: "wt_act"
                        },
                        "WT DECL": {
                            key: "wt_decl"
                        },
                        "L": {
                            key: "l"
                        },
                        "W": {
                            key: "w"
                        },
                        "H": {
                            key: "h"
                        },
                        "CTN (L)": {
                            key: "ctn_l"
                        },
                        "CTN (W)": {
                            key: "ctn_w"
                        },
                        "CTN (H)": {
                            key: "ctn_h"
                        },
                        "CTN (CBM)": {
                            key: "ctn_cbm"
                        },
                        "CTN (QTY)": {
                            key: "ctn_qty"
                        },
                        "CTN (CBM/Each)": {
                            key: "ctn_cbm_each"
                        },
                        "CBM (E)": {
                            key: "cbm_e"
                        },
                        "CTN GWT": {
                            key: "ctn_gwt"
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
                                        // Format numeric columns (WT ACT, WT DECL, L, W, H, CTN fields, etc.)
                                        else if (["wt_act", "wt_decl", "l", "w", "h", "ctn_l", "ctn_w", "ctn_h", "ctn_cbm", "ctn_qty", "ctn_cbm_each", "cbm_e", "ctn_gwt"].includes(key)) {
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
                            const wscols = columns.map(col => {
                                // Adjust width based on column type
                                if (["Parent", "SKU"].includes(col)) {
                                    return { wch: 20 }; // Wider for text columns
                                } else if (["Status"].includes(col)) {
                                    return { wch: 12 };
                                } else if (["WT ACT", "WT DECL", "CTN (CBM)", "CTN (CBM/Each)", "CBM (E)"].includes(col)) {
                                    return { wch: 15 }; // Width for weight and CBM columns
                                } else {
                                    return { wch: 12 }; // Default width for numeric columns
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
                            XLSX.utils.book_append_sheet(wb, ws, "Dim & Wt Master");

                            // Generate Excel file and trigger download
                            XLSX.writeFile(wb, "dim_wt_master_export.xlsx");

                            // Show success toast
                            showToast('success', 'Excel file downloaded successfully!');
                        } catch (error) {
                            console.error("Excel export error:", error);
                            showToast('danger', 'Failed to export Excel file.');
                        } finally {
                            // Reset button state
                            document.getElementById('downloadExcel').innerHTML =
                                '<i class="fas fa-file-excel me-1"></i> Download';
                            document.getElementById('downloadExcel').disabled = false;
                        }
                    }, 100); // Small timeout to allow UI to update
                });
            }

            // Setup import functionality
            function setupImport() {
                const importFile = document.getElementById('importFile');
                const importBtn = document.getElementById('importBtn');
                const downloadSampleBtn = document.getElementById('downloadSampleBtn');
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

                // Download sample file
                downloadSampleBtn.addEventListener('click', function() {
                    // Create sample data with all columns
                    const sampleData = [
                        ['SKU', 'WT ACT', 'WT DECL', 'L', 'W', 'H', 'CTN (L)', 'CTN (W)', 'CTN (H)', 'CTN (CBM)', 'CTN (QTY)', 'CTN (CBM/Each)', 'CBM (E)', 'CTN GWT'],
                        ['SKU001', '1.5', '1.2', '10.5', '8.3', '5.2', '30', '25', '20', '0.015', '12', '0.00125', '0.0005', '15.5'],
                        ['SKU002', '2.0', '1.8', '12.0', '9.0', '6.0', '35', '28', '22', '0.0216', '15', '0.00144', '0.0006', '18.0'],
                        ['SKU003', '1.2', '1.0', '9.5', '7.5', '4.5', '28', '24', '18', '0.0121', '10', '0.00121', '0.0004', '12.5']
                    ];

                    // Create workbook
                    const wb = XLSX.utils.book_new();
                    const ws = XLSX.utils.aoa_to_sheet(sampleData);

                    // Set column widths
                    ws['!cols'] = [
                        { wch: 15 }, // SKU
                        { wch: 12 }, // WT ACT
                        { wch: 12 }, // WT DECL
                        { wch: 10 }, // L
                        { wch: 10 }, // W
                        { wch: 10 }, // H
                        { wch: 12 }, // CTN (L)
                        { wch: 12 }, // CTN (W)
                        { wch: 12 }, // CTN (H)
                        { wch: 15 }, // CTN (CBM)
                        { wch: 12 }, // CTN (QTY)
                        { wch: 18 }, // CTN (CBM/Each)
                        { wch: 12 }, // CBM (E)
                        { wch: 12 }  // CTN GWT
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

                    XLSX.utils.book_append_sheet(wb, ws, "Dim & Wt Data");
                    XLSX.writeFile(wb, "dim_wt_master_sample.xlsx");
                    
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
                        const response = await fetch('/dim-wt-master/import', {
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

            // Select All checkbox functionality
            function setupSelectAll() {
                const selectAllCheckbox = document.getElementById('selectAll');
                selectAllCheckbox.addEventListener('change', function() {
                    const checkboxes = document.querySelectorAll('.row-checkbox');
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = selectAllCheckbox.checked;
                    });
                    updatePushButtonState();
                });
            }

            // Update Push Button State
            function updatePushButtonState() {
                const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
                const pushBtn = document.getElementById('pushDataBtn');
                if (checkedBoxes.length > 0) {
                    pushBtn.disabled = false;
                    pushBtn.innerHTML = `<i class="fas fa-cloud-upload-alt me-1"></i> Push Data (${checkedBoxes.length})`;
                } else {
                    pushBtn.disabled = true;
                    pushBtn.innerHTML = '<i class="fas fa-cloud-upload-alt me-1"></i> Push Data';
                }
            }

            // Push Data functionality
            function setupPushData() {
                document.getElementById('pushDataBtn').addEventListener('click', async function() {
                    const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
                    
                    if (checkedBoxes.length === 0) {
                        showToast('warning', 'Please select at least one SKU to push data');
                        return;
                    }

                    // Get selected SKUs and their data
                    const selectedSkus = [];
                    checkedBoxes.forEach(checkbox => {
                        const sku = checkbox.getAttribute('data-sku');
                        const row = checkbox.closest('tr');
                        if (row && sku) {
                            // Get dimensions and weight from the row data
                            const item = tableData.find(d => d.SKU === sku);
                            if (item) {
                                selectedSkus.push({
                                    sku: sku,
                                    id: item.id,
                                    wt_act: item.wt_act || null,
                                    wt_decl: item.wt_decl || null,
                                    l: item.l || null,
                                    w: item.w || null,
                                    h: item.h || null
                                });
                            }
                        }
                    });

                    if (selectedSkus.length === 0) {
                        showToast('warning', 'No valid SKUs found to push');
                        return;
                    }

                    // Confirm action
                    if (!confirm(`Are you sure you want to push dimensions & weight data for ${selectedSkus.length} SKU(s) to all platforms?`)) {
                        return;
                    }

                    const pushBtn = document.getElementById('pushDataBtn');
                    const originalText = pushBtn.innerHTML;
                    
                    try {
                        pushBtn.disabled = true;
                        pushBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Pushing...';
                        
                        const response = await makeRequest('/dim-wt-master/push-data', 'POST', {
                            skus: selectedSkus
                        });

                        const data = await response.json();
                        
                        if (!response.ok) {
                            throw new Error(data.message || 'Failed to push data');
                        }

                        // Show detailed results
                        let message = `Successfully pushed data for ${data.total_success || 0} SKU(s).`;
                        if (data.total_failed > 0) {
                            message += ` ${data.total_failed} failed.`;
                        }
                        
                        if (data.results) {
                            const platformResults = Object.entries(data.results)
                                .map(([platform, result]) => `${platform}: ${result.success} success, ${result.failed} failed`)
                                .join('\n');
                            message += '\n\nPlatform Results:\n' + platformResults;
                        }

                        showToast('success', message);
                        
                        // Uncheck all checkboxes
                        document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
                        document.getElementById('selectAll').checked = false;
                        updatePushButtonState();
                        
                    } catch (error) {
                        console.error('Error pushing data:', error);
                        showToast('danger', error.message || 'Failed to push data to platforms');
                    } finally {
                        pushBtn.innerHTML = originalText;
                        pushBtn.disabled = false;
                        updatePushButtonState();
                    }
                });
            }

            // Calculate CTN (CBM) = CTN (L) × CTN (W) × CTN (H) / 1000000
            function calculateCtnCbm(ctnL, ctnW, ctnH) {
                if (!ctnL || !ctnW || !ctnH) return 0;
                const l = parseFloat(ctnL) || 0;
                const w = parseFloat(ctnW) || 0;
                const h = parseFloat(ctnH) || 0;
                return (l * w * h) / 1000000;
            }

            // Calculate CTN (CBM/Each) = CTN (CBM) / CTN (QTY)
            function calculateCtnCbmEach(ctnCbm, ctnQty) {
                if (!ctnCbm || !ctnQty || parseFloat(ctnQty) === 0) return 0;
                const cbm = parseFloat(ctnCbm) || 0;
                const qty = parseFloat(ctnQty) || 0;
                return qty > 0 ? cbm / qty : 0;
            }

            // Setup calculated fields for add form
            function setupAddFormCalculations() {
                const ctnL = document.getElementById('addCtnL');
                const ctnW = document.getElementById('addCtnW');
                const ctnH = document.getElementById('addCtnH');
                const ctnCbm = document.getElementById('addCtnCbm');
                const ctnQty = document.getElementById('addCtnQty');
                const ctnCbmEach = document.getElementById('addCtnCbmEach');

                function updateCalculations() {
                    const cbm = calculateCtnCbm(ctnL.value, ctnW.value, ctnH.value);
                    ctnCbm.value = cbm.toFixed(6);
                    
                    const cbmEach = calculateCtnCbmEach(cbm, ctnQty.value);
                    ctnCbmEach.value = cbmEach.toFixed(6);
                }

                ctnL.addEventListener('input', updateCalculations);
                ctnW.addEventListener('input', updateCalculations);
                ctnH.addEventListener('input', updateCalculations);
                ctnQty.addEventListener('input', updateCalculations);
            }

            // Setup calculated fields for edit form
            function setupEditFormCalculations() {
                const ctnL = document.getElementById('editCtnL');
                const ctnW = document.getElementById('editCtnW');
                const ctnH = document.getElementById('editCtnH');
                const ctnCbm = document.getElementById('editCtnCbm');
                const ctnQty = document.getElementById('editCtnQty');
                const ctnCbmEach = document.getElementById('editCtnCbmEach');

                function updateCalculations() {
                    const cbm = calculateCtnCbm(ctnL.value, ctnW.value, ctnH.value);
                    ctnCbm.value = cbm.toFixed(6);
                    
                    const cbmEach = calculateCtnCbmEach(cbm, ctnQty.value);
                    ctnCbmEach.value = cbmEach.toFixed(6);
                }

                ctnL.addEventListener('input', updateCalculations);
                ctnW.addEventListener('input', updateCalculations);
                ctnH.addEventListener('input', updateCalculations);
                ctnQty.addEventListener('input', updateCalculations);
            }

            // Setup add button handler
            function setupAddButton() {
                const addBtn = document.getElementById('addDimWtBtn');
                if (addBtn) {
                    // Remove any existing event listeners by cloning
                    const newBtn = addBtn.cloneNode(true);
                    addBtn.parentNode.replaceChild(newBtn, addBtn);
                    
                    newBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        openAddDimWtModal();
                    });
                } else {
                    console.error('Add Dim & Wt button not found');
                }
            }

            // Open Add Dim & Wt Modal
            async function openAddDimWtModal() {
                const modalElement = document.getElementById('addDimWtModal');
                const modal = new bootstrap.Modal(modalElement);
                
                // Reset form
                document.getElementById('addDimWtForm').reset();
                document.getElementById('addCtnCbm').value = '';
                document.getElementById('addCtnCbmEach').value = '';
                
                // Destroy Select2 if already initialized
                const skuSelect = document.getElementById('addSku');
                if (skuSelect && typeof $ !== 'undefined' && typeof $.fn.select2 !== 'undefined') {
                    if ($(skuSelect).hasClass('select2-hidden-accessible')) {
                        $(skuSelect).select2('destroy');
                    }
                }
                
                // Load SKUs into dropdown
                await loadSkusIntoDropdown();
                
                // Setup save button handler
                const saveBtn = document.getElementById('saveAddDimWtBtn');
                const newSaveBtn = saveBtn.cloneNode(true);
                saveBtn.parentNode.replaceChild(newSaveBtn, saveBtn);
                
                newSaveBtn.addEventListener('click', async function() {
                    await saveAddDimWt();
                });
                
                // Clean up Select2 when modal is hidden
                modalElement.addEventListener('hidden.bs.modal', function() {
                    if (skuSelect && typeof $ !== 'undefined' && typeof $.fn.select2 !== 'undefined') {
                        if ($(skuSelect).hasClass('select2-hidden-accessible')) {
                            $(skuSelect).select2('destroy');
                        }
                    }
                }, { once: true });
                
                modal.show();
            }

            // Load SKUs into dropdown
            async function loadSkusIntoDropdown() {
                try {
                    const response = await fetch('/dim-wt-master/skus', {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        }
                    });
                    
                    const data = await response.json();
                    
                    if (data.success && data.data) {
                        const skuSelect = document.getElementById('addSku');
                        
                        if (!skuSelect) {
                            console.error('SKU select element not found');
                            return;
                        }
                        
                        // Check if jQuery and Select2 are available
                        if (typeof $ === 'undefined' || typeof $.fn.select2 === 'undefined') {
                            console.warn('jQuery or Select2 not available, using native select');
                            // Fallback to native select
                            skuSelect.innerHTML = '<option value="">Select SKU</option>';
                            data.data.forEach(item => {
                                const option = document.createElement('option');
                                option.value = item.sku;
                                option.textContent = item.sku;
                                skuSelect.appendChild(option);
                            });
                            return;
                        }
                        
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
                            dropdownParent: $('#addDimWtModal')
                        });
                    }
                } catch (error) {
                    console.error('Error loading SKUs:', error);
                    showToast('warning', 'Failed to load SKUs. Please refresh the page.');
                }
            }

            // Save Add Dim & Wt Master
            async function saveAddDimWt() {
                const saveBtn = document.getElementById('saveAddDimWtBtn');
                const originalText = saveBtn.innerHTML;
                
                // Validate required fields
                const skuSelect = document.getElementById('addSku');
                let sku = '';
                if (typeof $ !== 'undefined' && typeof $.fn.select2 !== 'undefined' && $(skuSelect).hasClass('select2-hidden-accessible')) {
                    sku = $(skuSelect).val() ? $(skuSelect).val().trim() : '';
                } else {
                    sku = skuSelect ? skuSelect.value.trim() : '';
                }
                
                if (!sku) {
                    showToast('warning', 'Please select SKU');
                    if (typeof $ !== 'undefined' && typeof $.fn.select2 !== 'undefined' && $(skuSelect).hasClass('select2-hidden-accessible')) {
                        $(skuSelect).select2('open');
                    } else {
                        skuSelect.focus();
                    }
                    return;
                }
                
                try {
                    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Saving...';
                    saveBtn.disabled = true;
                    
                    // Calculate CTN CBM and CTN CBM/Each
                    const ctnL = parseFloat(document.getElementById('addCtnL').value) || 0;
                    const ctnW = parseFloat(document.getElementById('addCtnW').value) || 0;
                    const ctnH = parseFloat(document.getElementById('addCtnH').value) || 0;
                    const ctnQty = parseFloat(document.getElementById('addCtnQty').value) || 0;
                    const ctnCbm = calculateCtnCbm(ctnL, ctnW, ctnH);
                    const ctnCbmEach = calculateCtnCbmEach(ctnCbm, ctnQty);
                    
                    const formData = {
                        sku: sku,
                        wt_act: document.getElementById('addWtAct').value.trim() || null,
                        wt_decl: document.getElementById('addWtDecl').value.trim() || null,
                        l: document.getElementById('addL').value.trim() || null,
                        w: document.getElementById('addW').value.trim() || null,
                        h: document.getElementById('addH').value.trim() || null,
                        ctn_l: document.getElementById('addCtnL').value.trim() || null,
                        ctn_w: document.getElementById('addCtnW').value.trim() || null,
                        ctn_h: document.getElementById('addCtnH').value.trim() || null,
                        ctn_cbm: ctnCbm > 0 ? ctnCbm : null,
                        ctn_qty: document.getElementById('addCtnQty').value.trim() || null,
                        ctn_cbm_each: ctnCbmEach > 0 ? ctnCbmEach : null,
                        cbm_e: document.getElementById('addCbmE').value.trim() || null,
                        ctn_gwt: document.getElementById('addCtnGwt').value.trim() || null
                    };
                    
                    const response = await fetch('/dim-wt-master/store', {
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
                    
                    showToast('success', 'Dim & Wt Data added successfully!');
                    
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('addDimWtModal'));
                    modal.hide();
                    
                    // Reload data
                    loadData();
                } catch (error) {
                    console.error('Error saving:', error);
                    showToast('danger', error.message || 'Failed to save data');
                } finally {
                    saveBtn.innerHTML = originalText;
                    saveBtn.disabled = false;
                }
            }

            // Edit Dim & Wt Master
            function editDimWt(product) {
                const modal = new bootstrap.Modal(document.getElementById('editDimWtModal'));
                
                // Populate form fields
                document.getElementById('editProductId').value = product.id || '';
                document.getElementById('editSku').value = product.SKU || '';
                document.getElementById('editParent').value = product.Parent || '';
                document.getElementById('editWtAct').value = product.wt_act || '';
                document.getElementById('editWtDecl').value = product.wt_decl || '';
                document.getElementById('editL').value = product.l || '';
                document.getElementById('editW').value = product.w || '';
                document.getElementById('editH').value = product.h || '';
                document.getElementById('editCtnL').value = product.ctn_l || '';
                document.getElementById('editCtnW').value = product.ctn_w || '';
                document.getElementById('editCtnH').value = product.ctn_h || '';
                document.getElementById('editCtnCbm').value = product.ctn_cbm || '';
                document.getElementById('editCtnQty').value = product.ctn_qty || '';
                document.getElementById('editCtnCbmEach').value = product.ctn_cbm_each || '';
                document.getElementById('editCbmE').value = product.cbm_e || '';
                document.getElementById('editCtnGwt').value = product.ctn_gwt || '';
                
                // Update calculated fields
                const ctnL = parseFloat(product.ctn_l) || 0;
                const ctnW = parseFloat(product.ctn_w) || 0;
                const ctnH = parseFloat(product.ctn_h) || 0;
                const ctnQty = parseFloat(product.ctn_qty) || 0;
                const ctnCbm = calculateCtnCbm(ctnL, ctnW, ctnH);
                const ctnCbmEach = calculateCtnCbmEach(ctnCbm, ctnQty);
                
                // Update calculated fields if they're not already set or if values changed
                if (!product.ctn_cbm || Math.abs(parseFloat(product.ctn_cbm) - ctnCbm) > 0.000001) {
                    document.getElementById('editCtnCbm').value = ctnCbm > 0 ? ctnCbm.toFixed(6) : '';
                }
                if (!product.ctn_cbm_each || Math.abs(parseFloat(product.ctn_cbm_each) - ctnCbmEach) > 0.000001) {
                    document.getElementById('editCtnCbmEach').value = ctnCbmEach > 0 ? ctnCbmEach.toFixed(6) : '';
                }
                
                // Setup save button handler
                const saveBtn = document.getElementById('saveDimWtBtn');
                const newSaveBtn = saveBtn.cloneNode(true);
                saveBtn.parentNode.replaceChild(newSaveBtn, saveBtn);
                
                newSaveBtn.addEventListener('click', async function() {
                    await saveDimWt();
                });
                
                modal.show();
            }

            // Save Dim & Wt Master
            async function saveDimWt() {
                const saveBtn = document.getElementById('saveDimWtBtn');
                const originalText = saveBtn.innerHTML;
                
                try {
                    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Saving...';
                    saveBtn.disabled = true;
                    
                    // Calculate CTN CBM and CTN CBM/Each
                    const ctnL = parseFloat(document.getElementById('editCtnL').value) || 0;
                    const ctnW = parseFloat(document.getElementById('editCtnW').value) || 0;
                    const ctnH = parseFloat(document.getElementById('editCtnH').value) || 0;
                    const ctnQty = parseFloat(document.getElementById('editCtnQty').value) || 0;
                    const ctnCbm = calculateCtnCbm(ctnL, ctnW, ctnH);
                    const ctnCbmEach = calculateCtnCbmEach(ctnCbm, ctnQty);
                    
                    const formData = {
                        product_id: document.getElementById('editProductId').value,
                        sku: document.getElementById('editSku').value,
                        parent: document.getElementById('editParent').value,
                        wt_act: document.getElementById('editWtAct').value.trim() || null,
                        wt_decl: document.getElementById('editWtDecl').value.trim() || null,
                        l: document.getElementById('editL').value.trim() || null,
                        w: document.getElementById('editW').value.trim() || null,
                        h: document.getElementById('editH').value.trim() || null,
                        ctn_l: document.getElementById('editCtnL').value.trim() || null,
                        ctn_w: document.getElementById('editCtnW').value.trim() || null,
                        ctn_h: document.getElementById('editCtnH').value.trim() || null,
                        ctn_cbm: ctnCbm > 0 ? ctnCbm : null,
                        ctn_qty: document.getElementById('editCtnQty').value.trim() || null,
                        ctn_cbm_each: ctnCbmEach > 0 ? ctnCbmEach : null,
                        cbm_e: document.getElementById('editCbmE').value.trim() || null,
                        ctn_gwt: document.getElementById('editCtnGwt').value.trim() || null
                    };
                    
                    const response = await fetch('/dim-wt-master/update', {
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
                    
                    showToast('success', 'Dim & Wt Master updated successfully!');
                    
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editDimWtModal'));
                    modal.hide();
                    
                    // Reload data
                    loadData();
                } catch (error) {
                    console.error('Error saving:', error);
                    showToast('danger', error.message || 'Failed to save data');
                } finally {
                    saveBtn.innerHTML = originalText;
                    saveBtn.disabled = false;
                }
            }

            // Initialize
            loadData();
            setupExcelExport();
            setupImport();
            setupSelectAll();
            setupPushData();
            setupAddButton();
            setupAddFormCalculations();
            setupEditFormCalculations();
        });
    </script>
@endsection

