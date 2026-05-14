@extends('layouts.vertical', ['title' => 'Compliance Certificates', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }
        
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            white-space: nowrap;
            font-size: 12px;
            font-weight: 600;
        }
        
        .tabulator .tabulator-header .tabulator-col {
            height: 40px !important;
        }

        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0px !important;
        }

        .tabulator-paginator label {
            margin-right: 5px;
        }

        .tabulator-cell {
            padding: 5px 8px !important;
            white-space: normal !important;
        }

        .file-list {
            margin-top: 10px;
        }

        .file-item {
            display: inline-block;
            margin: 3px;
            padding: 4px 8px;
            background: #e9ecef;
            border-radius: 3px;
            font-size: 12px;
        }

        .file-item a {
            margin-right: 5px;
            color: #0d6efd;
            text-decoration: none;
        }

        .btn-action {
            padding: 2px 8px;
            font-size: 11px;
            margin: 2px;
        }

        /* Custom Multi-Select for SKUs */
        .custom-multiselect {
            border: 1px solid #ced4da;
            border-radius: 4px;
            background: #fff;
            position: relative;
        }
        .custom-multiselect .ms-search {
            padding: 6px 8px;
            border-bottom: 1px solid #e9ecef;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .custom-multiselect .ms-search input {
            flex: 1;
            border: 1px solid #ced4da;
            border-radius: 3px;
            padding: 4px 8px;
            font-size: 13px;
            outline: none;
        }
        .custom-multiselect .ms-search input:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.2rem rgba(13,110,253,.15);
        }
        .custom-multiselect .ms-actions {
            display: flex;
            gap: 4px;
        }
        .custom-multiselect .ms-actions button {
            border: 1px solid #ced4da;
            background: #fff;
            border-radius: 3px;
            padding: 2px 8px;
            font-size: 11px;
            cursor: pointer;
        }
        .custom-multiselect .ms-actions button:hover {
            background: #e9ecef;
        }
        .custom-multiselect .ms-list {
            max-height: 220px;
            overflow-y: auto;
            padding: 4px 0;
        }
        .custom-multiselect .ms-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 5px 10px;
            cursor: pointer;
            font-size: 13px;
            user-select: none;
        }
        .custom-multiselect .ms-item:hover {
            background: #f1f3f5;
        }
        .custom-multiselect .ms-item input[type="checkbox"] {
            margin: 0;
        }
        .custom-multiselect .ms-empty {
            padding: 12px;
            text-align: center;
            color: #6c757d;
            font-size: 13px;
        }
        .custom-multiselect .ms-footer {
            padding: 5px 10px;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
            font-size: 12px;
            color: #495057;
        }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Compliance Certificates',
        'sub_title' => 'Manage Compliance Certificates',
    ])
    
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;">
        <div id="successToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="toastMessage"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h4 class="mb-0 me-2">Compliance Certificates Management</h4>
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <button type="button" class="btn btn-sm btn-warning" id="bulk-update-btn">
                            <i class="fa fa-edit"></i> Bulk Update
                        </button>
                        <button type="button" class="btn btn-sm btn-info" id="export-btn">
                            <i class="fa fa-file-excel"></i> Export
                        </button>
                        <span class="badge p-2 status-filter-badge" data-filter="all"
                            style="cursor:pointer; background-color:#6c757d; color:white; font-size:13px;">
                            Total: <strong id="count-all">0</strong>
                        </span>
                        <span class="badge p-2 status-filter-badge" data-filter="pending"
                            style="cursor:pointer; background-color:#dc3545; color:white; font-size:13px;">
                            Pending: <strong id="count-pending">0</strong>
                        </span>
                        <span class="badge p-2 status-filter-badge" data-filter="resolved"
                            style="cursor:pointer; background-color:#198754; color:white; font-size:13px;">
                            Resolved: <strong id="count-resolved">0</strong>
                        </span>
                        <span class="badge p-2 status-filter-badge" data-filter="docs_required"
                            style="cursor:pointer; background-color:#fd7e14; color:white; font-size:13px;">
                            Docs Required: <strong id="count-docs">0</strong>
                        </span>
                        <span class="badge p-2 status-filter-badge" data-filter="in_progress"
                            style="cursor:pointer; background-color:#0d6efd; color:white; font-size:13px;">
                            In Progress: <strong id="count-progress">0</strong>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="card-body" style="padding: 0;">
                <div id="compliance-table-wrapper" style="height: calc(100vh - 250px); display: flex; flex-direction: column;">
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search by SKU or INV...">
                    </div>
                    <div id="compliance-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Update Modal -->
    <div class="modal fade" id="bulkUpdateModal" tabindex="-1" aria-labelledby="bulkUpdateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bulkUpdateModalLabel"><i class="fa fa-edit"></i> Bulk Update Compliance Data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2" style="font-size:13px;">
                        <i class="fa fa-info-circle"></i> 
                        Enter SKUs (one per line or comma-separated). Only the fields you fill in will be updated. Leave blank to skip.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select SKUs <span class="text-danger">*</span></label>
                        <div class="custom-multiselect" id="bulk-skus-multiselect">
                            <div class="ms-search">
                                <input type="text" id="bulk-skus-search" placeholder="Search SKUs...">
                                <div class="ms-actions">
                                    <button type="button" id="bulk-skus-select-all">Select All</button>
                                    <button type="button" id="bulk-skus-clear">Clear</button>
                                </div>
                            </div>
                            <div class="ms-list" id="bulk-skus-list"></div>
                            <div class="ms-footer"><span id="bulk-sku-count">0</span> selected of <span id="bulk-sku-total">0</span> SKU(s)</div>
                        </div>
                    </div>
                    
                    <hr>
                    <h6 class="mb-3">Fields to Update (leave empty to skip)</h6>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">FCC Channels</label>
                            <div class="custom-multiselect bulk-channel-ms" data-field="fcc">
                                <div class="ms-search">
                                    <input type="text" class="ms-search-input" placeholder="Search channels...">
                                    <div class="ms-actions">
                                        <button type="button" class="ms-select-all">All</button>
                                        <button type="button" class="ms-clear-all">Clear</button>
                                    </div>
                                </div>
                                <div class="ms-list bulk-fcc-list"></div>
                                <div class="ms-footer"><span class="ms-count">0</span> selected</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">GCC Channels</label>
                            <div class="custom-multiselect bulk-channel-ms" data-field="gcc">
                                <div class="ms-search">
                                    <input type="text" class="ms-search-input" placeholder="Search channels...">
                                    <div class="ms-actions">
                                        <button type="button" class="ms-select-all">All</button>
                                        <button type="button" class="ms-clear-all">Clear</button>
                                    </div>
                                </div>
                                <div class="ms-list bulk-gcc-list"></div>
                                <div class="ms-footer"><span class="ms-count">0</span> selected</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">UL Channels</label>
                            <div class="custom-multiselect bulk-channel-ms" data-field="ul">
                                <div class="ms-search">
                                    <input type="text" class="ms-search-input" placeholder="Search channels...">
                                    <div class="ms-actions">
                                        <button type="button" class="ms-select-all">All</button>
                                        <button type="button" class="ms-clear-all">Clear</button>
                                    </div>
                                </div>
                                <div class="ms-list bulk-ul-list"></div>
                                <div class="ms-footer"><span class="ms-count">0</span> selected</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Battery Channels</label>
                            <div class="custom-multiselect bulk-channel-ms" data-field="battery">
                                <div class="ms-search">
                                    <input type="text" class="ms-search-input" placeholder="Search channels...">
                                    <div class="ms-actions">
                                        <button type="button" class="ms-select-all">All</button>
                                        <button type="button" class="ms-clear-all">Clear</button>
                                    </div>
                                </div>
                                <div class="ms-list bulk-battery-list"></div>
                                <div class="ms-footer"><span class="ms-count">0</span> selected</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Certificate Available</label>
                            <div class="custom-multiselect bulk-channel-ms" data-field="certificate_available">
                                <div class="ms-list bulk-cert-avl-list"></div>
                                <div class="ms-footer"><span class="ms-count">0</span> selected</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select id="bulk-status" class="form-select">
                                <option value="">-- No change --</option>
                                <option value="Select">Select</option>
                                <option value="Resolved">Resolved</option>
                                <option value="Docs Required">Docs Required</option>
                                <option value="In Progress">In Progress</option>
                            </select>
                        </div>
                    </div>
                    <small class="text-muted d-block mt-2">Empty selections will not modify existing data.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" id="bulk-apply-btn"><i class="fa fa-check"></i> Apply Bulk Update</button>
                </div>
            </div>
        </div>
    </div>

    <!-- History Modal -->
    <div class="modal fade" id="historyModal" tabindex="-1" aria-labelledby="historyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="historyModalLabel">Activity History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="historyList" class="table-responsive">
                        <div class="text-center p-3"><i class="fa fa-spinner fa-spin"></i> Loading...</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Files Modal -->
    <div class="modal fade" id="viewFilesModal" tabindex="-1" aria-labelledby="viewFilesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewFilesModalLabel">Certificate Files</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="viewFilesList" class="table-responsive">
                        <!-- Files table will be rendered here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- File Upload Modal -->
    <div class="modal fade" id="fileUploadModal" tabindex="-1" aria-labelledby="fileUploadModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="fileUploadModalLabel">Upload Certificate Files</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="fileInput" class="form-label">Select Files</label>
                        <input type="file" class="form-control" id="fileInput" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                        <div class="form-text">Accepted formats: PDF, DOC, DOCX, JPG, JPEG, PNG (Max 10MB each)</div>
                    </div>
                    <div id="uploadedFilesList"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="uploadFilesBtn">Upload Files</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let table;
        let currentRowId = null;
        let uploadedFiles = [];
        let channelNames = [];
        
        $(document).ready(function() {
            // Fetch channel names first, then initialize table
            $.ajax({
                url: "{{ route('compliance-certificates.data') }}",
                type: 'GET',
                success: function(response) {
                    channelNames = response.channels || [];
                    initializeTable(response.data, channelNames);
                    setupEventListeners();
                    updateStatusCounts(response.data);
                }
            });
        });

        // Bulk SKU multi-select state
        let allSkus = [];           // master list of all SKUs from the table
        let bulkSelectedSkus = new Set();

        // Bulk channel multi-select state (per field)
        let bulkSelections = {
            fcc: new Set(),
            gcc: new Set(),
            ul: new Set(),
            battery: new Set(),
            certificate_available: new Set()
        };
        const CERT_AVL_OPTIONS = ["FCC", "GCC", "UL", "Battery"];

        // Render a channel/cert-avl multi-select list
        function renderBulkChannelList(field, options, searchTerm = '') {
            let term = searchTerm.toLowerCase().trim();
            let filtered = options.filter(o => term === '' || o.toLowerCase().includes(term));
            let listClass = 'bulk-' + (field === 'certificate_available' ? 'cert-avl' : field) + '-list';
            let $list = $('.' + listClass);

            if (filtered.length === 0) {
                $list.html('<div class="ms-empty">No options found</div>');
                return;
            }

            let selected = bulkSelections[field];
            let html = '';
            filtered.forEach(opt => {
                let attrVal = escapeAttr(opt);
                let textVal = $('<div>').text(opt).html();
                let checked = selected.has(opt) ? 'checked' : '';
                html += `<label class="ms-item">
                    <input type="checkbox" class="bulk-channel-checkbox" data-field="${field}" value="${attrVal}" ${checked}>
                    <span>${textVal}</span>
                </label>`;
            });
            $list.html(html);
        }

        function updateBulkChannelCount(field) {
            let count = bulkSelections[field].size;
            $(`.bulk-channel-ms[data-field="${field}"] .ms-count`).text(count);
        }

        // Escape value for use in HTML attributes (handles quotes, special chars)
        function escapeAttr(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }

        // Render the SKU multi-select list (filtered by search term)
        function renderBulkSkusList(searchTerm = '') {
            let term = searchTerm.toLowerCase().trim();
            let filtered = allSkus.filter(s => term === '' || s.toLowerCase().includes(term));

            let $list = $('#bulk-skus-list');
            if (filtered.length === 0) {
                $list.html('<div class="ms-empty">No SKUs found</div>');
                return;
            }

            let html = '';
            filtered.forEach(sku => {
                let checked = bulkSelectedSkus.has(sku) ? 'checked' : '';
                let attrSku = escapeAttr(sku);
                let textSku = $('<div>').text(sku).html(); // escape for visible text
                html += `<label class="ms-item">
                    <input type="checkbox" class="bulk-sku-checkbox" value="${attrSku}" ${checked}>
                    <span>${textSku}</span>
                </label>`;
            });
            $list.html(html);
        }

        function updateBulkSkuCount() {
            $('#bulk-sku-count').text(bulkSelectedSkus.size);
            $('#bulk-sku-total').text(allSkus.length);
        }

        // Determine if a row is "pending" - no compliance data filled yet
        function isRowPending(row) {
            let hasFcc = row.fcc && (Array.isArray(row.fcc) ? row.fcc.length : String(row.fcc).trim() !== '');
            let hasGcc = row.gcc && (Array.isArray(row.gcc) ? row.gcc.length : String(row.gcc).trim() !== '');
            let hasUl = row.ul && (Array.isArray(row.ul) ? row.ul.length : String(row.ul).trim() !== '');
            let hasBattery = row.battery && (Array.isArray(row.battery) ? row.battery.length : String(row.battery).trim() !== '');
            let hasCertAvl = row.certificate_available && (Array.isArray(row.certificate_available) ? row.certificate_available.length : String(row.certificate_available).trim() !== '');
            let hasFiles = row.certificate_files && String(row.certificate_files).trim() !== '';
            return !(hasFcc || hasGcc || hasUl || hasBattery || hasCertAvl || hasFiles);
        }

        function updateStatusCounts(data) {
            let counts = {
                all: data.length,
                pending: 0,
                resolved: 0,
                docs: 0,
                progress: 0
            };

            data.forEach(row => {
                if (isRowPending(row)) {
                    counts.pending++;
                }
                let s = (row.status || '').toLowerCase();
                if (s === 'resolved') counts.resolved++;
                else if (s === 'docs required') counts.docs++;
                else if (s === 'in progress') counts.progress++;
            });

            $('#count-all').text(counts.all);
            $('#count-pending').text(counts.pending);
            $('#count-resolved').text(counts.resolved);
            $('#count-docs').text(counts.docs);
            $('#count-progress').text(counts.progress);
        }

        // Convert a long channel name to a short abbreviation
        // Custom mapping for common channels (extend as needed)
        const CHANNEL_SHORT_MAP = {
            'amazon': 'AMZ',
            'aliexpress': 'ALE',
            'bestbuy usa': 'BBY',
            'business 5core': 'B5C',
            'depop': 'DPP',
            'depop.com': 'DPP2',
            'doba': 'DBA',
            'ebay': 'EBY',
            'ebaythree': 'EB3',
            'ebaytwo': 'EB2',
            'faire': 'FRE',
            'fb marketplace': 'FBM',
            'instagram shop': 'IGS',
            'macys': 'MCY',
            'mercari w ship': 'MWS',
            'mercari wo ship': 'MWO',
            'pls': 'PLS',
            'purchasing power': 'PP',
            'reverb': 'RVB',
            'shein': 'SHN',
            'shopify b2b': 'SB2B',
            'shopify b2c': 'SB2C',
            'temu': 'TMU',
            'temu 2': 'TMU2',
            'tiendamia': 'TDM',
            'tiktok shop': 'TTS',
            'tiktok shop 2': 'TTS2',
            'topdawg': 'TDG',
            'vinted.com': 'VNT',
            'wayfair': 'WFR',
        };

        function shortChannelName(name) {
            if (!name) return '';
            let key = String(name).toLowerCase().trim();
            if (CHANNEL_SHORT_MAP[key]) return CHANNEL_SHORT_MAP[key];
            // Auto fallback: initials for multi-word, first 3 chars for single word
            let words = String(name).trim().split(/\s+/);
            if (words.length > 1) {
                return words.map(w => w.charAt(0)).join('').toUpperCase().substring(0, 4);
            }
            return String(name).substring(0, 3).toUpperCase();
        }

        // Formatter to display comma-separated values as styled badges (wrap to next line if needed)
        function multiSelectFormatter(cell) {
            let value = cell.getValue();
            if (!value) return '';
            
            let items = Array.isArray(value) ? value : String(value).split(',').filter(v => v.trim() !== '');
            if (items.length === 0) return '';
            
            let field = cell.getField();
            // For "certificate_available" field show full text (FCC, GCC, UL, Battery are already short)
            let useShort = field !== 'certificate_available';
            
            let html = '<div style="display:flex; flex-wrap:wrap; gap:4px; align-items:center;">';
            items.forEach(item => {
                let trimmed = item.trim();
                let display = useShort ? shortChannelName(trimmed) : trimmed;
                html += `<span title="${trimmed.replace(/"/g,'&quot;')}" style="font-size:11px; padding:1px 6px; display:inline-block; border:1px solid #ced4da; border-radius:10px; color:#343a40; background:transparent;">${display}</span>`;
            });
            html += '</div>';
            return html;
        }

        // mutatorData runs only when data loads INTO the table (string -> array for editor)
        function multiSelectMutatorIn(value, data, type, params, component) {
            if (!value) return [];
            if (Array.isArray(value)) return value;
            return String(value).split(',').map(v => v.trim()).filter(v => v !== '');
        }

        function initializeTable(data, channels) {
            table = new Tabulator("#compliance-table", {
                height: "100%",
                layout: "fitDataStretch",
                pagination: "local",
                paginationSize: 50,
                paginationSizeSelector: [25, 50, 100, 200],
                movableColumns: true,
                resizableRows: false,
                placeholder: "No data available",
                data: data,
                columns: [
                    {
                        title: "Img",
                        field: "image",
                        width: 70,
                        hozAlign: "center",
                        headerSort: false,
                        formatter: function(cell) {
                            let url = cell.getValue();
                            if (!url) {
                                return '<span style="color:#bbb; font-size:11px;">No image</span>';
                            }
                            return `<img src="${url}" alt="img" style="width:40px; height:40px; object-fit:cover; border-radius:4px; cursor:pointer; border:1px solid #dee2e6;" onerror="this.outerHTML='<span style=\\'color:#bbb;font-size:11px;\\'>N/A</span>'">`;
                        },
                        cellClick: function(e, cell) {
                            let url = cell.getValue();
                            if (url) window.open(url, '_blank');
                        }
                    },
                    {
                        title: "SKU",
                        field: "sku",
                        width: 180,
                        editor: "input"
                    },
                    {
                        title: "INV",
                        field: "inv",
                        width: 80,
                        hozAlign: "center",
                        editor: "number"
                    },
                    {
                        title: "FCC",
                        field: "fcc",
                        width: 250,
                        editor: "list",
                        editorParams: {
                            values: channels,
                            multiselect: true,
                            clearable: true,
                            listOnEmpty: true
                        },
                        formatter: multiSelectFormatter,
                        mutatorData: multiSelectMutatorIn
                    },
                    {
                        title: "GCC",
                        field: "gcc",
                        width: 250,
                        editor: "list",
                        editorParams: {
                            values: channels,
                            multiselect: true,
                            clearable: true,
                            listOnEmpty: true
                        },
                        formatter: multiSelectFormatter,
                        mutatorData: multiSelectMutatorIn
                    },
                    {
                        title: "UL",
                        field: "ul",
                        width: 250,
                        editor: "list",
                        editorParams: {
                            values: channels,
                            multiselect: true,
                            clearable: true,
                            listOnEmpty: true
                        },
                        formatter: multiSelectFormatter,
                        mutatorData: multiSelectMutatorIn
                    },
                    {
                        title: "Battery",
                        field: "battery",
                        width: 250,
                        editor: "list",
                        editorParams: {
                            values: channels,
                            multiselect: true,
                            clearable: true,
                            listOnEmpty: true
                        },
                        formatter: multiSelectFormatter,
                        mutatorData: multiSelectMutatorIn
                    },
                    {
                        title: "Certificate Avl",
                        field: "certificate_available",
                        width: 130,
                        editor: "list",
                        editorParams: {
                            values: ["FCC", "GCC", "UL", "Battery"],
                            multiselect: true,
                            clearable: true,
                            listOnEmpty: true
                        },
                        formatter: multiSelectFormatter,
                        mutatorData: multiSelectMutatorIn
                    },
                    {
                        title: "Certificate Files",
                        field: "certificate_files",
                        width: 130,
                        hozAlign: "center",
                        formatter: function(cell, formatterParams) {
                            let files = cell.getRow().getData().files_array || [];
                            let count = files.length;
                            let rowId = cell.getRow().getData().id;
                            
                            let html = `<button class="btn btn-sm btn-primary btn-action upload-file-btn" data-id="${rowId}" title="Upload Files">
                                <i class="fa fa-upload"></i>
                            </button>`;
                            
                            if (count > 0) {
                                html += ` <button class="btn btn-sm btn-success btn-action view-files-btn" data-id="${rowId}" title="View ${count} file(s)">
                                    <i class="fa fa-folder-open"></i> ${count}
                                </button>`;
                            }
                            
                            return html;
                        },
                        headerSort: false
                    },
                    {
                        title: "Status",
                        field: "status",
                        width: 70,
                        hozAlign: "center",
                        editor: "list",
                        editorParams: {
                            values: ["Select", "Resolved", "Docs Required", "In Progress"]
                        },
                        formatter: function(cell) {
                            let val = (cell.getValue() || '').toString().trim();
                            if (!val) val = 'Select';
                            
                            let color = '#6c757d';
                            if (val === 'Resolved') color = '#198754';
                            else if (val === 'Docs Required') color = '#fd7e14';
                            else if (val === 'In Progress') color = '#0d6efd';
                            
                            // Show only a colored dot, full text on hover (title attr)
                            return `<span title="${val}" style="display:inline-block; width:14px; height:14px; border-radius:50%; background-color:${color}; border:1px solid rgba(0,0,0,0.15); cursor:pointer;"></span>`;
                        }
                    },
                    {
                        title: "Actions",
                        field: "actions",
                        width: 110,
                        hozAlign: "center",
                        formatter: function(cell, formatterParams) {
                            let row = cell.getRow().getData();
                            let html = '';
                            if (row.sku) {
                                html += `<button class="btn btn-sm btn-outline-secondary btn-action view-history-btn" data-sku="${row.sku}" title="View History">
                                    <i class="fa fa-history"></i>
                                </button> `;
                            }
                            html += `<button class="btn btn-sm btn-danger btn-action delete-btn" data-id="${row.id}" title="Delete">
                                <i class="fa fa-trash"></i>
                            </button>`;
                            return html;
                        },
                        headerSort: false
                    }
                ],
                rowFormatter: function(row) {
                    row.getElement().style.height = "auto";
                }
            });

            // Handle cell click events for file upload, view, delete
            table.on("cellClick", function(e, cell) {
                if ($(e.target).closest('.upload-file-btn').length) {
                    currentRowId = cell.getRow().getData().id;
                    uploadedFiles = [];
                    $('#uploadedFilesList').html('');
                    $('#fileUploadModal').modal('show');
                }
                
                if ($(e.target).closest('.view-files-btn').length) {
                    showViewFilesModal(cell.getRow().getData());
                }
                
                if ($(e.target).closest('.view-history-btn').length) {
                    let sku = $(e.target).closest('.view-history-btn').data('sku');
                    showHistoryModal(sku);
                }
                
                if ($(e.target).closest('.delete-btn').length) {
                    deleteRow(cell.getRow().getData().id);
                }
                
                if ($(e.target).closest('.save-btn').length) {
                    saveRow(cell.getRow().getData().id);
                }
            });

            // Auto-save when a cell is edited (FCC, GCC, UL, Battery, status, etc.)
            table.on("cellEdited", function(cell) {
                let rowData = cell.getRow().getData();
                console.log('Cell edited:', cell.getField(), '=', cell.getValue(), 'Row:', rowData);
                saveRowSilently(rowData);
            });
        }

        // Save row silently in the background after edit
        function saveRowSilently(data) {
            saveRowData(data).then((response) => {
                console.log('Auto-save success:', response);
                showToast('Saved', 'success');
                // Update the row id if it was a new row that got an actual id
                if (response.data && response.data.id && String(data.id).startsWith('new_')) {
                    let row = table.getRow(data.id);
                    if (row) {
                        row.update({id: response.data.id});
                    }
                }
            }).catch((error) => {
                console.error('Auto-save error:', error);
                showToast('Error: ' + error, 'error');
            });
        }
        function setupEventListeners() {
            // SKU Search
            $('#sku-search').on('keyup', function() {
                let value = $(this).val();
                table.setFilter([
                    [
                        {field: "sku", type: "like", value: value},
                        {field: "inv", type: "like", value: value}
                    ]
                ]);
            });

            // Bulk Update button - open modal
            $('#bulk-update-btn').on('click', function() {
                // Build SKU list from currently visible/loaded table data
                allSkus = (table.getData() || []).map(r => r.sku).filter(s => !!s);
                bulkSelectedSkus = new Set();
                $('#bulk-skus-search').val('');
                renderBulkSkusList();
                updateBulkSkuCount();

                // Reset channel selections
                ['fcc','gcc','ul','battery','certificate_available'].forEach(f => {
                    bulkSelections[f] = new Set();
                    $(`.bulk-channel-ms[data-field="${f}"] .ms-search-input`).val('');
                    updateBulkChannelCount(f);
                });

                // Render channel multi-selects
                renderBulkChannelList('fcc', channelNames);
                renderBulkChannelList('gcc', channelNames);
                renderBulkChannelList('ul', channelNames);
                renderBulkChannelList('battery', channelNames);
                renderBulkChannelList('certificate_available', CERT_AVL_OPTIONS);

                // Reset status
                $('#bulk-status').val('');

                $('#bulkUpdateModal').modal('show');
            });

            // Channel/cert-avl multi-select: search
            $(document).on('input', '.bulk-channel-ms .ms-search-input', function() {
                let field = $(this).closest('.bulk-channel-ms').data('field');
                let term = $(this).val();
                let opts = field === 'certificate_available' ? CERT_AVL_OPTIONS : channelNames;
                renderBulkChannelList(field, opts, term);
            });

            // Channel checkbox toggle
            $(document).on('change', '.bulk-channel-checkbox', function() {
                let field = $(this).data('field');
                let val = $(this).val();
                if (this.checked) {
                    bulkSelections[field].add(val);
                } else {
                    bulkSelections[field].delete(val);
                }
                updateBulkChannelCount(field);
            });

            // Select All for channel/cert-avl
            $(document).on('click', '.bulk-channel-ms .ms-select-all', function() {
                let $ms = $(this).closest('.bulk-channel-ms');
                let field = $ms.data('field');
                $ms.find('.bulk-channel-checkbox').each(function() {
                    bulkSelections[field].add($(this).val());
                    this.checked = true;
                });
                updateBulkChannelCount(field);
            });

            // Clear All for channel/cert-avl
            $(document).on('click', '.bulk-channel-ms .ms-clear-all', function() {
                let $ms = $(this).closest('.bulk-channel-ms');
                let field = $ms.data('field');
                bulkSelections[field].clear();
                $ms.find('.bulk-channel-checkbox').prop('checked', false);
                updateBulkChannelCount(field);
            });

            // Search in SKU list
            $('#bulk-skus-search').on('input', function() {
                renderBulkSkusList($(this).val());
            });

            // Toggle checkbox
            $(document).on('change', '.bulk-sku-checkbox', function() {
                let sku = $(this).val();
                if (this.checked) {
                    bulkSelectedSkus.add(sku);
                } else {
                    bulkSelectedSkus.delete(sku);
                }
                updateBulkSkuCount();
            });

            // Select All (only visible/filtered ones)
            $('#bulk-skus-select-all').on('click', function() {
                $('.bulk-sku-checkbox').each(function() {
                    bulkSelectedSkus.add($(this).val());
                    this.checked = true;
                });
                updateBulkSkuCount();
            });

            // Clear All
            $('#bulk-skus-clear').on('click', function() {
                bulkSelectedSkus.clear();
                $('.bulk-sku-checkbox').prop('checked', false);
                updateBulkSkuCount();
            });

            // Apply Bulk Update
            $('#bulk-apply-btn').on('click', function() {
                let skus = Array.from(bulkSelectedSkus);
                if (skus.length === 0) {
                    showToast('Please select at least one SKU', 'warning');
                    return;
                }

                let payload = {
                    skus: skus,
                    fcc: Array.from(bulkSelections.fcc),
                    gcc: Array.from(bulkSelections.gcc),
                    ul: Array.from(bulkSelections.ul),
                    battery: Array.from(bulkSelections.battery),
                    certificate_available: Array.from(bulkSelections.certificate_available),
                    status: $('#bulk-status').val() || '',
                    _token: $('meta[name="csrf-token"]').attr('content')
                };

                // Confirm action
                let summary = [];
                if (payload.fcc.length) summary.push('FCC: ' + payload.fcc.join(', '));
                if (payload.gcc.length) summary.push('GCC: ' + payload.gcc.join(', '));
                if (payload.ul.length) summary.push('UL: ' + payload.ul.join(', '));
                if (payload.battery.length) summary.push('Battery: ' + payload.battery.join(', '));
                if (payload.certificate_available.length) summary.push('Cert Avl: ' + payload.certificate_available.join(', '));
                if (payload.status) summary.push('Status: ' + payload.status);

                if (summary.length === 0) {
                    showToast('Please set at least one field to update', 'warning');
                    return;
                }

                if (!confirm(`Update ${skus.length} SKU(s) with:\n\n${summary.join('\n')}\n\nProceed?`)) return;

                // Disable button
                let $btn = $(this);
                $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Updating...');

                $.ajax({
                    url: "{{ route('compliance-certificates.bulk-update') }}",
                    type: 'POST',
                    data: payload,
                    traditional: false,
                    success: function(response) {
                        $btn.prop('disabled', false).html('<i class="fa fa-check"></i> Apply Bulk Update');
                        if (response.success) {
                            $('#bulkUpdateModal').modal('hide');
                            showToast(`Updated ${response.updated} SKU(s) successfully`, 'success');
                            refreshTableData();
                        } else {
                            showToast('Bulk update failed: ' + (response.message || 'Unknown error'), 'error');
                        }
                    },
                    error: function(xhr) {
                        $btn.prop('disabled', false).html('<i class="fa fa-check"></i> Apply Bulk Update');
                        showToast('Error: ' + (xhr.responseJSON?.message || 'Bulk update failed'), 'error');
                    }
                });
            });

            // Status badge filters - toggle on/off
            $(document).on('click', '.status-filter-badge', function() {
                let filter = $(this).data('filter');
                let isActive = $(this).hasClass('active-filter');

                // If clicking the same active badge, clear filter (toggle off)
                if (isActive) {
                    $('.status-filter-badge').removeClass('active-filter').css('outline', 'none');
                    table.clearFilter(true);
                    return;
                }

                // Otherwise, set as active and apply filter
                $('.status-filter-badge').removeClass('active-filter').css('outline', 'none');
                $(this).addClass('active-filter').css('outline', '3px solid #000');

                if (filter === 'all') {
                    table.clearFilter(true);
                } else if (filter === 'pending') {
                    table.setFilter(function(data) {
                        return isRowPending(data);
                    });
                } else if (filter === 'resolved') {
                    table.setFilter('status', '=', 'Resolved');
                } else if (filter === 'docs_required') {
                    table.setFilter('status', '=', 'Docs Required');
                } else if (filter === 'in_progress') {
                    table.setFilter('status', '=', 'In Progress');
                }
            });

            // Export Button - exports currently filtered/visible rows only
            $('#export-btn').on('click', function() {
                let filtered = table.getData("active"); // only visible rows after filters
                if (!filtered || filtered.length === 0) {
                    showToast('No data to export', 'warning');
                    return;
                }

                let timestamp = new Date().toISOString().slice(0, 10);
                let filename = `compliance_certificates_${timestamp}.csv`;

                // Use Tabulator's built-in CSV export but limited to filtered rows
                table.download("csv", filename, {
                    delimiter: ",",
                    bom: true
                }, "active");

                showToast(`Exported ${filtered.length} row(s)`, 'success');
            });

            // File Input Change
            $('#fileInput').on('change', function(e) {
                handleFileSelect(e.target.files);
            });

            // Reset modal state when it closes
            $('#fileUploadModal').on('hidden.bs.modal', function () {
                uploadedFiles = [];
                $('#uploadedFilesList').html('');
                $('#fileInput').val('');
            });

            $('#uploadFilesBtn').on('click', function() {
                uploadFiles();
            });
        }

        function showHistoryModal(sku) {
            $('#historyModalLabel').text('Activity History - ' + sku);
            $('#historyList').html('<div class="text-center p-3"><i class="fa fa-spinner fa-spin"></i> Loading...</div>');
            $('#historyModal').modal('show');
            
            $.ajax({
                url: "{{ url('compliance-certificates/history') }}/" + encodeURIComponent(sku),
                type: 'GET',
                success: function(history) {
                    if (!history || history.length === 0) {
                        $('#historyList').html('<div class="alert alert-info mb-0">No history available for this SKU.</div>');
                        return;
                    }
                    
                    let html = `<table class="table table-bordered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="80">Action</th>
                                <th>Description</th>
                                <th width="140">Updated By</th>
                                <th width="160">Date & Time</th>
                            </tr>
                        </thead>
                        <tbody>`;
                    
                    history.forEach(item => {
                        let badgeClass = 'bg-info';
                        if (item.action === 'uploaded') badgeClass = 'bg-success';
                        else if (item.action === 'deleted') badgeClass = 'bg-danger';
                        else if (item.action === 'created') badgeClass = 'bg-primary';
                        
                        html += `<tr>
                            <td><span class="badge ${badgeClass} text-white">${item.action}</span></td>
                            <td style="font-size:13px;">${item.description || '-'}</td>
                            <td><strong>${item.updated_by || '-'}</strong></td>
                            <td style="font-size:12px;">${item.created_at || '-'}</td>
                        </tr>`;
                    });
                    
                    html += `</tbody></table>`;
                    $('#historyList').html(html);
                },
                error: function() {
                    $('#historyList').html('<div class="alert alert-danger mb-0">Failed to load history.</div>');
                }
            });
        }

        let currentViewFilesSku = null;

        function showViewFilesModal(rowData) {
            currentViewFilesSku = rowData.sku || '';
            renderFilesList(rowData.files_array || [], currentViewFilesSku);
            $('#viewFilesModal').modal('show');
        }

        function renderFilesList(files, sku) {
            $('#viewFilesModalLabel').text('Certificate Files - ' + sku);
            
            if (!files || files.length === 0) {
                $('#viewFilesList').html('<div class="alert alert-info mb-0">No files uploaded yet.</div>');
                return;
            }
            
            let html = `<table class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th width="50">#</th>
                        <th>File Name</th>
                        <th width="170" class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>`;
            
            files.forEach((file, idx) => {
                html += `<tr>
                    <td>${idx + 1}</td>
                    <td><i class="fa fa-file-pdf text-danger me-2"></i>${file.name}</td>
                    <td class="text-center" style="white-space:nowrap;">
                        <a href="${file.url}" target="_blank" class="btn btn-sm btn-info text-white me-1" title="View">
                            <i class="fa fa-eye"></i>
                        </a>
                        <a href="${file.url}" download="${file.name}" class="btn btn-sm btn-success me-1" title="Download">
                            <i class="fa fa-download"></i>
                        </a>
                        <button type="button" class="btn btn-sm btn-danger delete-file-btn" data-filename="${file.name}" title="Delete">
                            <i class="fa fa-trash"></i>
                        </button>
                    </td>
                </tr>`;
            });
            
            html += `</tbody></table>`;
            $('#viewFilesList').html(html);
        }

        // Handle delete file button click (delegated from modal)
        $(document).on('click', '.delete-file-btn', function() {
            let filename = $(this).data('filename');
            if (!currentViewFilesSku || !filename) return;
            
            if (!confirm('Delete this file?\n\n' + filename)) return;
            
            $.ajax({
                url: "{{ route('compliance-certificates.delete-file') }}",
                type: 'POST',
                data: {
                    sku: currentViewFilesSku,
                    filename: filename,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        showToast('File deleted', 'success');
                        // Reload the row data and re-render the modal
                        refreshTableData();
                        // Re-render modal with remaining files (rebuild from response)
                        let remainingFiles = (response.remaining_files || []).map(name => ({
                            name: name,
                            url: '{{ asset("storage/certificates") }}/' + name
                        }));
                        renderFilesList(remainingFiles, currentViewFilesSku);
                    } else {
                        showToast('Failed: ' + (response.message || 'Unknown error'), 'error');
                    }
                },
                error: function(xhr) {
                    showToast('Error: ' + (xhr.responseJSON?.message || 'Failed to delete'), 'error');
                }
            });
        });

        function handleFileSelect(files) {
            uploadedFiles = Array.from(files);
            displaySelectedFiles();
        }

        function displaySelectedFiles() {
            let html = '<h6>Selected Files:</h6>';
            uploadedFiles.forEach((file, index) => {
                html += `<div class="file-item">
                    <i class="fa fa-file"></i> ${file.name}
                    <button type="button" class="btn btn-sm btn-danger ms-2" onclick="removeFile(${index})">
                        <i class="fa fa-times"></i>
                    </button>
                </div>`;
            });
            $('#uploadedFilesList').html(html);
        }

        function removeFile(index) {
            uploadedFiles.splice(index, 1);
            displaySelectedFiles();
        }

        function uploadFiles() {
            if (uploadedFiles.length === 0) {
                showToast('Please select files to upload', 'warning');
                return;
            }

            // Get the row data (so we can send sku along)
            let row = table.getRow(currentRowId);
            if (!row) {
                showToast('Row not found', 'error');
                return;
            }

            let rowData = row.getData();
            if (!rowData.sku) {
                showToast('SKU is missing for this row', 'error');
                return;
            }

            let formData = new FormData();
            formData.append('sku', rowData.sku);
            formData.append('inv', rowData.inv || '');
            formData.append('status', rowData.status || '');

            // Append multi-select fields as arrays
            ['fcc','gcc','ul','battery','certificate_available'].forEach(field => {
                let val = rowData[field];
                if (Array.isArray(val)) {
                    val.forEach(v => formData.append(field + '[]', v));
                } else if (val) {
                    String(val).split(',').filter(x => x.trim() !== '').forEach(v => formData.append(field + '[]', v.trim()));
                }
            });

            // Append files
            uploadedFiles.forEach(file => {
                formData.append('certificate_files[]', file);
            });

            // Disable button during upload
            $('#uploadFilesBtn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Uploading...');

            $.ajax({
                url: "{{ route('compliance-certificates.store') }}",
                type: 'POST',
                data: formData,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                processData: false,
                contentType: false,
                success: function(response) {
                    $('#uploadFilesBtn').prop('disabled', false).html('Upload Files');
                    if (response.success) {
                        $('#fileUploadModal').modal('hide');
                        showToast('Files uploaded successfully');
                        uploadedFiles = [];
                        $('#uploadedFilesList').html('');
                        $('#fileInput').val('');
                        refreshTableData();
                    } else {
                        showToast('Upload failed: ' + (response.message || 'Unknown error'), 'error');
                    }
                },
                error: function(xhr) {
                    $('#uploadFilesBtn').prop('disabled', false).html('Upload Files');
                    let msg = xhr.responseJSON?.message || xhr.responseJSON?.errors ? JSON.stringify(xhr.responseJSON.errors) : 'Error uploading files';
                    console.error('Upload error:', xhr.responseJSON);
                    showToast('Error: ' + msg, 'error');
                }
            });
        }

        function saveRow(rowId) {
            let row = table.getRow(rowId);
            if (!row) return;
            
            let data = row.getData();
            saveRowData(data).then(() => {
                showToast('Row saved successfully');
                refreshTableData();
            }).catch(error => {
                showToast('Error saving row: ' + error, 'error');
            });
        }

        function refreshTableData() {
            $.ajax({
                url: "{{ route('compliance-certificates.data') }}",
                type: 'GET',
                success: function(response) {
                    if (response.data) {
                        table.setData(response.data);
                        updateStatusCounts(response.data);
                    }
                }
            });
        }

        function saveRowData(data) {
            return new Promise((resolve, reject) => {
                let isNew = String(data.id).startsWith('new_');
                let url = isNew ? 
                    "{{ route('compliance-certificates.store') }}" : 
                    "{{ route('compliance-certificates.update', ':id') }}".replace(':id', data.id);
                
                let method = isNew ? 'POST' : 'PUT';
                
                $.ajax({
                    url: url,
                    type: method,
                    data: data,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            resolve(response);
                        } else {
                            reject(response.message);
                        }
                    },
                    error: function(xhr) {
                        reject(xhr.responseJSON?.message || 'Error saving data');
                    }
                });
            });
        }

        function deleteRow(rowId) {
            if (!confirm('Are you sure you want to delete this row?')) {
                return;
            }

            if (String(rowId).startsWith('new_')) {
                table.deleteRow(rowId);
                showToast('Row deleted');
                return;
            }

            $.ajax({
                url: "{{ route('compliance-certificates.destroy', ':id') }}".replace(':id', rowId),
                type: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        table.deleteRow(rowId);
                        showToast('Row deleted successfully');
                    }
                },
                error: function(xhr) {
                    showToast('Error deleting row', 'error');
                }
            });
        }

        function showToast(message, type = 'success') {
            let toast = $('#successToast');
            let toastElement = new bootstrap.Toast(toast[0]);
            
            toast.removeClass('bg-success bg-warning bg-danger');
            if (type === 'error') {
                toast.addClass('bg-danger');
            } else if (type === 'warning') {
                toast.addClass('bg-warning');
            } else {
                toast.addClass('bg-success');
            }
            
            $('#toastMessage').text(message);
            toastElement.show();
        }
    </script>
@endsection
