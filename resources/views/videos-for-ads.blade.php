@extends('layouts.vertical', ['title' => 'Videos for Ads', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

    <style>
        .table-wrapper {
            position: relative;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            max-height: calc(100vh - 200px);
            overflow-y: auto;
            overflow-x: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            background: #fff;
        }

        .table-wrapper table {
            margin-bottom: 0;
            width: 100% !important;
            table-layout: fixed;
        }

        .table-wrapper thead th {
            position: sticky;
            top: 0;
            background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%) !important;
            color: #fff;
            z-index: 10;
            padding: 8px 5px 6px;
            font-weight: 600;
            border-bottom: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            font-size: 10px;
            text-align: center;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            white-space: nowrap;
            vertical-align: top;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .table-wrapper thead th:hover {
            background: linear-gradient(135deg, #1a56b7 0%, #0a3d8f 100%) !important;
        }

        .th-filter input,
        .th-filter select {
            background: rgba(255,255,255,0.92);
            border: none;
            border-radius: 3px;
            color: #333;
            padding: 3px 4px;
            margin-top: 4px;
            font-size: 10px;
            width: 100%;
        }

        .th-filter input:focus,
        .th-filter select:focus {
            background: #fff;
            box-shadow: 0 0 0 2px rgba(26,86,183,0.3);
            outline: none;
        }

        .table-wrapper tbody td {
            padding: 6px 5px;
            vertical-align: middle;
            text-align: center;
            border-bottom: 1px solid #edf2f9;
            font-size: 11px;
            color: #495057;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .table-wrapper tbody tr:nth-child(even) { background: #f8fafc; }
        .table-wrapper tbody tr:hover           { background: #e8f0fe; }

        .badge-count {
            font-size: 10px;
            font-weight: 700;
            color: #ff6b6b;
            vertical-align: middle;
        }

        .missing-label {
            font-size: 10px;
            color: rgba(255,255,255,0.6);
            display: block;
            margin-top: 2px;
        }

        .action-btn {
            padding: 3px 7px;
            border: none;
            border-radius: 4px;
            font-size: 11px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .edit-btn {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            color: #fff;
        }

        .edit-btn:hover  { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(255,193,7,.35); }

        .del-btn {
            background: linear-gradient(135deg, #ff4d4d 0%, #c0392b 100%);
            color: #fff;
        }

        .del-btn:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(255,77,77,.35); }

        #loader {
            display: none;
            text-align: center;
            padding: 40px;
        }

        .modal-header-gradient {
            background: linear-gradient(135deg, #6B73FF 0%, #000DFF 100%);
            border-bottom: 4px solid #4D55E6;
            color: #fff;
        }

        .video-link-icon {
            color: #2c6ed5;
            font-size: 19px;
            text-decoration: none;
            transition: all .3s ease;
            display: inline-block;
        }

        .video-link-icon:hover { color: #1a56b7; transform: scale(1.2); }

        .cell-text {
            max-width: 130px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: inline-block;
            font-size: 12px;
            vertical-align: middle;
        }

        .data-dot {
            display: inline-block;
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #28a745;
            cursor: pointer;
            box-shadow: 0 0 0 2px rgba(40,167,69,0.25);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            vertical-align: middle;
        }

        .data-dot:hover {
            transform: scale(1.4);
            box-shadow: 0 0 0 4px rgba(40,167,69,0.2);
        }

        /* Status dropdown */
        .status-select {
            border: none;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            padding: 3px 8px;
            cursor: pointer;
            outline: none;
            appearance: none;
            -webkit-appearance: none;
            text-align: center;
            width: 100%;
        }
        .status-select:focus { box-shadow: none; }
        .ss-Todo           { background:#cff4fc; color:#055160; }
        .ss-Working        { background:#fff3cd; color:#856404; }
        .ss-Archived       { background:#e2e3e5; color:#41464b; }
        .ss-Done           { background:#d4edda; color:#155724; }
        .ss-Need_Help      { background:#ffe5d0; color:#984c0c; }
        .ss-Need_Approval  { background:#e0cffc; color:#432874; }
        .ss-Dependent      { background:#f7d6e6; color:#ab296a; }
        .ss-Approved       { background:#d1f4e0; color:#146c43; }
        .ss-Hold           { background:#dee2e6; color:#212529; }
        .ss-Cancelled      { background:#f8d7da; color:#721c24; }

        /* Approval toggle dots */
        .appr-dot {
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            cursor: pointer;
            transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
            border: 2px solid rgba(0,0,0,0.1);
        }
        .appr-dot.off { background: #dc3545; }
        .appr-dot.on  { background: #28a745; }
        .appr-dot:not(.appr-summary):hover { transform: scale(1.35); box-shadow: 0 0 0 3px rgba(0,0,0,0.12); }
        .appr-summary { width: 16px !important; height: 16px !important; cursor: default !important; }

        .lang-toggle {
            line-height: 1.2;
            border-radius: 6px !important;
            transition: all 0.15s;
        }
        .lang-toggle.btn-primary { box-shadow: 0 0 0 2px rgba(26,86,183,0.35); }

        .sku-badge {
            background: #e8f0fe;
            color: #1a56b7;
            font-weight: 600;
            padding: 2px 5px;
            border-radius: 8px;
            font-size: 10px;
            white-space: nowrap;
            display: inline-block;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        #total-count-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, #2c6ed5, #1a56b7);
            color: #fff;
            font-size: 12px;
            font-weight: 600;
            padding: 5px 14px;
            border-radius: 20px;
            box-shadow: 0 2px 8px rgba(26,86,183,0.25);
            letter-spacing: 0.3px;
        }
        #total-count-badge .count-num {
            background: rgba(255,255,255,0.25);
            border-radius: 12px;
            padding: 1px 8px;
            font-size: 13px;
            font-weight: 700;
        }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('layouts.shared/page-title', [
        'page_title' => 'Videos for Ads',
        'sub_title'  => 'Manage Product Ad Videos',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    {{-- Toolbar --}}
                    <div class="mb-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex gap-2 flex-wrap align-items-center">
                            <button id="addBtn" class="btn btn-success btn-sm">
                                <i class="fas fa-plus"></i> Add Video
                            </button>
                            <button id="exportBtn" class="btn btn-primary btn-sm">
                                <i class="fas fa-download"></i> Export
                            </button>
                            <button id="importBtn" class="btn btn-sm" style="background:#17a2b8;color:#fff;">
                                <i class="fas fa-upload"></i> Import
                            </button>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-warning dropdown-toggle" id="bulkBtn" data-bs-toggle="dropdown">
                                    <i class="fas fa-layer-group"></i> Bulk
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" id="bulkDuplicate"><i class="fas fa-copy me-2"></i>Duplicate selected</a></li>
                                </ul>
                            </div>
                        </div>
                        <span id="total-count-badge">
                            <i class="fas fa-video"></i>
                            Total Videos:
                            <span class="count-num" id="total-count">0</span>
                        </span>
                    </div>

                    {{-- Table --}}
                    <div class="table-wrapper">
                        <table class="table w-100" id="ads-table">
                            <thead>
                                <tr>
                                    <th style="width:2%;text-align:center;">
                                        <input type="checkbox" id="selectAll" title="Select all" style="cursor:pointer;width:13px;height:13px;">
                                    </th>
                                    <th style="width:3%;">#</th>
                                    <th style="width:5%;">Videos</th>
                                    <th style="width:10%;">
                                        Category
                                        <div class="th-filter">
                                            <select id="parentSearch">
                                                <option value="all">All</option>
                                                <option value="category">Category</option>
                                                <option value="parents">Parents</option>
                                                <option value="group">Group</option>
                                                <option value="sku">SKU</option>
                                            </select>
                                        </div>
                                    </th>
                                    <th style="width:9%;">
                                        Target
                                        <div class="th-filter">
                                            <input type="text" id="skuSearch" placeholder="Search">
                                        </div>
                                    </th>
                                    <th style="width:7%;">Topic</th>
                                    <th style="width:7%;">What</th>
                                    <th style="width:6%;">Why</th>
                                    <th style="width:10%;">
                                        Audience
                                        <div class="th-filter">
                                            <select id="filter_ads_audience">
                                                <option value="all">All</option>
                                            </select>
                                        </div>
                                    </th>
                                    <th style="width:7%;">Benefits</th>
                                    <th style="width:7%;">Language</th>
                                    <th style="width:7%;">Script</th>
                                    <th style="width:8%;">Link</th>
                                    <th style="width:7%;">Status</th>
                                    <th style="width:4%;" title="Approval S">Appr-S</th>
                                    <th style="width:4%;" title="Approval I">Appr-I</th>
                                    <th style="width:4%;" title="Approval N">Appr-N</th>
                                    <th style="width:4%;" title="All approved">Appr</th>
                                    <th style="width:8%;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="table-body"></tbody>
                        </table>
                    </div>

                    <div id="loader">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <div class="mt-2 fw-bold text-primary">Loading...</div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    {{-- Add / Edit Modal --}}
    <div class="modal fade" id="videoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title">
                        <i class="fas fa-video me-2"></i>
                        <span id="modalTitle">Add Video for Ads</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="videoForm" novalidate>
                        <input type="hidden" id="editId" name="id">

                        <div class="mb-3" id="skuSelectWrap">
                            <label class="form-label fw-semibold">SKU / Target Products</label>
                            <select class="form-select" id="f_sku" name="sku">
                                <option value="">Choose or type SKU...</option>
                            </select>
                            <small class="text-muted">Select from the list <strong>or</strong> type a custom SKU and press Enter</small>
                        </div>
                        <div class="mb-3 d-none" id="skuDisplayWrap">
                            <label class="form-label fw-semibold">SKU / Target Products</label>
                            <div id="skuDisplayBadge" class="sku-badge d-inline-block px-3 py-2" style="font-size:14px;"></div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Category</label>
                                <select class="form-select" id="f_category" name="category">
                                    <option value="">-- Select Category --</option>
                                    <option value="Category">Category</option>
                                    <option value="Parents">Parents</option>
                                    <option value="Group">Group</option>
                                    <option value="SKU">SKU</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Status</label>
                                <select class="form-select" id="f_ads_status" name="ads_status">
                                    <option value="Todo">Todo</option>
                                    <option value="Working">Working</option>
                                    <option value="Archived">Archived</option>
                                    <option value="Done">Done</option>
                                    <option value="Need Help">Need Help</option>
                                    <option value="Need Approval">Need Approval</option>
                                    <option value="Dependent">Dependent</option>
                                    <option value="Approved">Approved</option>
                                    <option value="Hold">Hold</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>

                        <div class="card border-0 bg-light rounded-3 p-3 mb-3">
                            <div class="fw-semibold mb-2" style="font-size:13px;">
                                <i class="fas fa-film me-1 text-primary"></i> Video Preview
                            </div>
                            <div class="mb-2">
                                <label class="form-label mb-1">Video Thumbnail URL</label>
                                <input type="url" class="form-control form-control-sm" id="f_video_thumbnail"
                                       name="video_thumbnail" placeholder="https:// (thumbnail image link)">
                                <small class="text-muted">Image shown in the Videos column. Dropbox: use a <strong>file</strong> share link (not a folder); <code>dl=0</code> links are adjusted automatically.</small>
                                <div id="thumbUrlHint" class="text-warning mt-1 d-none" style="font-size:11px;"></div>
                            </div>
                            <div>
                                <label class="form-label mb-1">Video URL <span class="text-muted">(plays on click)</span></label>
                                <input type="url" class="form-control form-control-sm" id="f_video_url"
                                       name="video_url" placeholder="https:// (YouTube, Drive, Vimeo…)">
                            </div>
                            <div id="videoPreviewBox" class="mt-2 d-none text-center">
                                <img id="videoPreviewThumb" src="" alt="Thumbnail preview"
                                     style="max-height:100px;border-radius:6px;border:1px solid #dee2e6;cursor:pointer;"
                                     title="Click to open video">
                                <div class="text-muted mt-1" style="font-size:11px;">Thumbnail preview</div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Topic</label>
                                <input type="text" class="form-control" id="f_ads_topic_story_val"
                                       name="ads_topic_story" placeholder="Enter topic...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">What</label>
                                <input type="text" class="form-control" id="f_ads_what_val"
                                       name="ads_what" placeholder="What is the video about?">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Why</label>
                                <input type="text" class="form-control" id="f_ads_why_purpose_val"
                                       name="ads_why_purpose" placeholder="Purpose of the video...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Audience</label>
                                <select class="form-select" id="f_ads_audience_val" name="ads_audience">
                                    <option value="">All</option>
                                </select>
                                <small class="text-muted">Choose from list or type to add new</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Benefits</label>
                                <input type="text" class="form-control" id="f_ads_benefit_audience_val"
                                       name="ads_benefit_audience" placeholder="Benefits...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label d-block">Language</label>
                                <div class="d-flex gap-2" id="langToggleGroup">
                                    <button type="button" class="btn btn-outline-secondary lang-toggle" data-lang="en" style="font-size:20px;padding:4px 14px;" title="English">
                                        🇺🇸 <small style="font-size:11px;">EN</small>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary lang-toggle" data-lang="es" style="font-size:20px;padding:4px 14px;" title="Spanish">
                                        🇪🇸 <small style="font-size:11px;">ES</small>
                                    </button>
                                </div>
                                <input type="hidden" id="f_ads_language_val" name="ads_language">
                            </div>
                        </div>

                        <hr class="my-3">

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Script Link</label>
                                <input type="url" class="form-control" id="f_ads_script_link_val"
                                       name="ads_script_link" placeholder="https://">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Link</label>
                                <input type="url" class="form-control" id="f_ads_video_en_link_val"
                                       name="ads_video_en_link" placeholder="https://">
                            </div>
                        </div>

                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveBtn">
                        <i class="fas fa-save"></i> Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Delete Confirm Modal --}}
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h6 class="modal-title"><i class="fas fa-trash me-2"></i>Confirm Delete</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Delete record for SKU <strong id="deleteSku"></strong>?</p>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger btn-sm" id="confirmDeleteBtn">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Import Modal --}}
    <div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <div class="modal-header" style="background:linear-gradient(135deg,#17a2b8,#117a8b);color:#fff;">
                    <h6 class="modal-title"><i class="fas fa-upload me-2"></i>Import Data (CSV)</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">

                    {{-- Instructions --}}
                    <div class="alert alert-info py-2 mb-3" style="font-size:12px;">
                        <strong>Instructions:</strong>
                        <ul class="mb-0 mt-1 ps-3">
                            <li>Upload a <strong>CSV</strong> file with a header row.</li>
                            <li><strong>sku</strong> column is required; all other columns are optional.</li>
                            <li>Existing records with the same SKU will be <strong>updated</strong>; new SKUs will be inserted.</li>
                            <li>Supported columns: <code>sku, ads_status, category, appr_s, appr_i, appr_n, video_thumbnail, video_url, ads_topic_story, ads_what, ads_why_purpose, ads_audience, ads_benefit_audience, ads_language, ads_script_link, ads_video_en_link</code></li>
                        </ul>
                    </div>

                    {{-- Download template --}}
                    <div class="mb-3">
                        <a id="downloadTemplate" href="#" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-file-csv me-1"></i> Download CSV Template
                        </a>
                    </div>

                    {{-- File picker --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Select CSV File</label>
                        <input type="file" class="form-control form-control-sm" id="importFile" accept=".csv,.txt">
                    </div>

                    {{-- Progress / result --}}
                    <div id="importProgress" class="d-none">
                        <div class="progress mb-2" style="height:6px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-info w-100"></div>
                        </div>
                        <small class="text-muted">Uploading and processing…</small>
                    </div>
                    <div id="importResult" class="d-none"></div>

                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm text-white" id="doImportBtn" style="background:#17a2b8;">
                        <i class="fas fa-upload me-1"></i> Import
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
        @verbatim
        const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        let allData      = [];
        let deleteId     = null;
        let videoModal, deleteModal, importModal;

        // Field definitions: type 'text' = plain text, type 'link' = URL + status
        const fields = [
            { key: 'ads_topic_story',      label: 'Topic',          type: 'text' },
            { key: 'ads_what',             label: 'What',           type: 'text' },
            { key: 'ads_why_purpose',      label: 'Why',            type: 'text' },
            { key: 'ads_audience',         label: 'Audience',       type: 'text' },
            { key: 'ads_benefit_audience', label: 'Benefits',       type: 'text' },
            { key: 'ads_language',         label: 'Language',       type: 'lang' },
            { key: 'ads_script_link',      label: 'Script Link',    type: 'url' },
            { key: 'ads_video_en_link',    label: 'Link',           type: 'url' },
        ];

        /* ── Bootstrap ─────────────────────────────────────────── */
        document.addEventListener('DOMContentLoaded', () => {
            videoModal  = new bootstrap.Modal(document.getElementById('videoModal'));
            deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            importModal = new bootstrap.Modal(document.getElementById('importModal'));

            loadData();
            loadAudienceOptions();
            bindToolbar();
            bindFilters();
            bindTableEvents();
        });

        /* ── Toolbar ────────────────────────────────────────────── */
        function bindToolbar() {
            document.getElementById('addBtn').addEventListener('click', () => openModal('add'));
            document.getElementById('exportBtn').addEventListener('click', exportExcel);
            document.getElementById('importBtn').addEventListener('click', openImportModal);
            document.getElementById('saveBtn').addEventListener('click', saveRecord);
            document.getElementById('confirmDeleteBtn').addEventListener('click', deleteRecord);
            document.getElementById('doImportBtn').addEventListener('click', doImport);
            document.getElementById('downloadTemplate').addEventListener('click', downloadCsvTemplate);
        }

        /* ── Table delegation (inline status dropdowns) ─────────── */
        function bindTableEvents() {
            document.getElementById('table-body').addEventListener('change', function(e) {
                if (!e.target.classList.contains('inline-status')) return;
                const id    = e.target.dataset.id;
                const field = e.target.dataset.field;
                const row   = allData.find(r => r.id == id);
                if (!row) return;
                row[field + '_status'] = e.target.value;
                patchRecord(row);
            });
        }

        /* ── Filters ────────────────────────────────────────────── */
        /* ── Checkbox helpers ────────────────────────────────────── */
        function syncSelectAll() {
            const all = document.querySelectorAll('.row-cb');
            const chk = document.querySelectorAll('.row-cb:checked');
            const sa  = document.getElementById('selectAll');
            if (!sa) return;
            sa.indeterminate = chk.length > 0 && chk.length < all.length;
            sa.checked       = chk.length > 0 && chk.length === all.length;
        }

        document.addEventListener('change', function(e) {
            if (e.target.id === 'selectAll') {
                document.querySelectorAll('.row-cb').forEach(cb => cb.checked = e.target.checked);
            }
        });

        function getSelectedIds() {
            return Array.from(document.querySelectorAll('.row-cb:checked')).map(cb => cb.dataset.id);
        }

        /* ── Bulk actions ────────────────────────────────────────── */
        document.addEventListener('click', function(e) {
            if (e.target.closest('#bulkDuplicate')) {
                e.preventDefault();
                bulkDuplicate();
            }
        });

        function bulkDuplicate() {
            const ids = getSelectedIds();
            if (!ids.length) { alert('Please select at least one row to duplicate.'); return; }

            const rows = allData.filter(r => ids.includes(String(r.id)));
            let done = 0;
            const total = rows.length;

            rows.forEach(row => {
                const newSku = 'COPY-' + row.sku + '-' + Date.now().toString().slice(-4);
                const payload = Object.assign({}, row, { sku: newSku, id: undefined, appr_s: 0, appr_i: 0, appr_n: 0 });
                delete payload.id;
                delete payload.parent_name;
                delete payload.image_path;
                delete payload.created_at;
                delete payload.updated_at;

                fetch('/videos-for-ads/store', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify(payload)
                })
                .then(r => r.json())
                .then(() => { done++; if (done === total) { loadData(); alert(total + ' row(s) duplicated.'); } })
                .catch(err => { done++; console.error(err); if (done === total) loadData(); });
            });
        }

        function bindFilters() {
            document.getElementById('skuSearch').addEventListener('input', applyFilters);
            document.getElementById('parentSearch').addEventListener('change', applyFilters);
            document.getElementById('filter_ads_audience').addEventListener('change', applyFilters);
        }

        /* ── Load ───────────────────────────────────────────────── */
        function loadData() {
            document.getElementById('loader').style.display = 'block';
            document.getElementById('table-body').innerHTML = '';

            fetch('/videos-for-ads/data')
                .then(r => r.json())
                .then(resp => {
                    allData = resp.data || [];
                    applyFilters();
                    updateCounts();
                })
                .catch(e => alert('Failed to load data: ' + e.message))
                .finally(() => document.getElementById('loader').style.display = 'none');
        }

        /* ── Render ─────────────────────────────────────────────── */
        /* ── Audience Options ───────────────────────────────────── */
        let audienceOptions = [];

        function loadAudienceOptions(callback) {
            fetch('/videos-for-ads/audience-options')
                .then(r => r.json())
                .then(resp => {
                    audienceOptions = resp.options || [];
                    populateAudienceHeaderFilter();
                    if (callback) callback();
                });
        }

        function populateAudienceHeaderFilter() {
            const filterSel = document.getElementById('filter_ads_audience');
            if (!filterSel) return;
            const current = filterSel.value;
            filterSel.innerHTML = '<option value="all">All</option><option value="missing">Missing</option>';
            audienceOptions.forEach(opt => {
                const o = document.createElement('option');
                o.value = opt; o.textContent = opt;
                filterSel.appendChild(o);
            });
            // Restore selection if still valid
            if (current) filterSel.value = current;
        }

        function rebuildAudienceSelect() {
            const sel = document.getElementById('f_ads_audience_val');
            if (!sel) return;

            // Destroy existing Select2 before rebuilding
            if ($(sel).hasClass('select2-hidden-accessible')) $(sel).select2('destroy');

            // Rebuild options
            sel.innerHTML = '<option value="">All</option>';
            audienceOptions.forEach(opt => {
                const o = document.createElement('option');
                o.value = opt; o.textContent = opt;
                sel.appendChild(o);
            });

            // Initialise Select2 with tags
            $(sel).select2({
                theme: 'bootstrap-5',
                placeholder: 'Select or type audience...',
                allowClear: true,
                tags: true,
                createTag: function(params) {
                    const term = $.trim(params.term);
                    if (!term) return null;
                    return { id: term, text: '+ Add "' + term + '"', newTag: true };
                },
                width: '100%',
                dropdownParent: $('#videoModal')
            });

            // When a new tag is chosen, persist it to the server
            $(sel).on('select2:select', function(e) {
                if (e.params.data.newTag) {
                    const newVal = e.params.data.id;
                    fetch('/videos-for-ads/audience-options', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify({ name: newVal })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && !audienceOptions.includes(data.name)) {
                            audienceOptions.push(data.name);
                            // Add option to form select so it persists on next open
                            const o = document.createElement('option');
                            o.value = data.name; o.textContent = data.name;
                            sel.appendChild(o);
                            // Also update the table header filter
                            populateAudienceHeaderFilter();
                        }
                    });
                }
            });
        }

        function applyFilters() {
            const skuQ      = document.getElementById('skuSearch').value.toLowerCase();
            const categoryQ = document.getElementById('parentSearch').value;
            const audienceQ = document.getElementById('filter_ads_audience').value;

            const filtered = allData.filter(row => {
                if (skuQ && !row.sku.toLowerCase().includes(skuQ)) return false;

                if (categoryQ && categoryQ !== 'all') {
                    const parent = (row.parent_name || '').toLowerCase();
                    if (!parent.includes(categoryQ.toLowerCase())) return false;
                }

                if (audienceQ && audienceQ !== 'all') {
                    if ((row.ads_audience || '').toLowerCase() !== audienceQ.toLowerCase()) return false;
                }

                return true;
            });

            renderTable(filtered);
        }

        /** Match server VideoThumbnailUrl::normalize for <img src>. */
        function normalizeThumbUrlForImg(url) {
            if (!url || typeof url !== 'string') return '';
            url = url.trim();
            if (!url) return '';
            if (/^data:image\//i.test(url)) return url;
            if (url.indexOf('//') === 0) url = 'https:' + url;
            const dm = url.match(/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)/);
            if (dm) return 'https://drive.google.com/uc?export=view&id=' + dm[1];
            if (!/dropbox\.com/i.test(url)) return url;
            if (/\/scl\/fo\//i.test(url)) return url;
            let out = url.replace(/([?&])dl=0(&|$)/i, '$1raw=1$2').replace(/([?&])dl=1(&|$)/i, '$1raw=1$2');
            if (!/[?&]raw=1(&|$)/i.test(out)) out += (out.indexOf('?') !== -1 ? '&' : '?') + 'raw=1';
            return out;
        }

        function extractYoutubeId(u) {
            if (!u || typeof u !== 'string') return '';
            const s = u.trim();
            let m = s.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/|youtube\.com\/shorts\/)([a-zA-Z0-9_-]{11})/);
            if (m) return m[1];
            try {
                const a = document.createElement('a');
                a.href = s;
                if (a.hostname.includes('youtube.com') && a.pathname.indexOf('/watch') === 0) {
                    const v = new URLSearchParams(a.search).get('v');
                    if (v && /^[a-zA-Z0-9_-]{11}$/.test(v)) return v;
                }
            } catch (e) {}
            return '';
        }
        function youtubeThumbUrl(id) {
            return id ? ('https://i.ytimg.com/vi/' + id + '/hqdefault.jpg') : '';
        }
        function resolveVideoThumbnails(row) {
            let primary = normalizeThumbUrlForImg(row.video_thumbnail || '');
            const ytId = extractYoutubeId(row.video_url || '') || extractYoutubeId(row.ads_video_en_link || '');
            const ytThumb = youtubeThumbUrl(ytId);
            let first = '';
            const thumbUsable = primary && (/^https?:\/\//i.test(primary) || /^data:image\//i.test(primary));
            if (thumbUsable) first = primary;
            else if (ytThumb) first = ytThumb;
            const second = (first && ytThumb && first !== ytThumb) ? ytThumb : '';
            return { first, secondEnc: second ? encodeURIComponent(second) : '' };
        }
        function playVideoHref(row) {
            const u = (row.video_url || '').trim() || (row.ads_video_en_link || '').trim();
            return u || '';
        }
        if (!window._vfaThumbErr) {
            window._vfaThumbErr = function (img) {
                const altEnc = img.getAttribute('data-thumb2');
                if (altEnc) {
                    try {
                        const alt = decodeURIComponent(altEnc);
                        if (alt && img.src !== alt) {
                            img.src = alt;
                            return;
                        }
                    } catch (e) {}
                }
                img.onerror = null;
                const a = img.closest('a');
                if (a && a.href && a.href !== '#') {
                    a.innerHTML = '<i class="fas fa-play-circle"></i>';
                    a.className = 'video-link-icon';
                    a.style.position = '';
                    return;
                }
                img.replaceWith(Object.assign(document.createElement('span'), { className: 'text-muted', textContent: '—' }));
            };
        }
        function renderVideoCellHtml(row) {
            const { first, secondEnc } = resolveVideoThumbnails(row);
            const href = playVideoHref(row);
            const hrefEsc = esc(href);
            const imgCommon = ' referrerpolicy="no-referrer" loading="lazy" onerror="window._vfaThumbErr(this)"' +
                (secondEnc ? ' data-thumb2="' + secondEnc + '"' : '') +
                ' style="width:36px;height:28px;object-fit:cover;border-radius:3px;border:2px solid #2c6ed5;" alt=""';
            if (first && href) {
                return '<a href="' + hrefEsc + '" target="_blank" rel="noopener noreferrer" title="Play video" style="display:inline-block;position:relative;">' +
                    '<img src="' + esc(first) + '"' + imgCommon + '>' +
                    '<span style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);' +
                        'background:rgba(0,0,0,0.55);border-radius:50%;width:14px;height:14px;' +
                        'display:flex;align-items:center;justify-content:center;">' +
                        '<i class="fas fa-play" style="color:#fff;font-size:6px;margin-left:1px;"></i>' +
                    '</span></a>';
            }
            if (href) {
                return '<a href="' + hrefEsc + '" target="_blank" rel="noopener noreferrer" class="video-link-icon" title="Play video">' +
                    '<i class="fas fa-play-circle"></i></a>';
            }
            if (first) {
                return '<img src="' + esc(first) + '"' + imgCommon.replace('border:2px solid #2c6ed5;', '') + '>';
            }
            return '<span class="text-muted">—</span>';
        }

        function renderTable(data) {
            const tbody = document.getElementById('table-body');
            tbody.innerHTML = '';
            const sa = document.getElementById('selectAll');
            if (sa) { sa.checked = false; sa.indeterminate = false; }

            if (!data.length) {
                tbody.innerHTML = '<tr><td colspan="17" class="text-center py-4 text-muted">No records found</td></tr>';
                document.getElementById('total-count').textContent = '0';
                return;
            }

            document.getElementById('total-count').textContent = data.length;

            data.forEach((row, idx) => {
                const tr = document.createElement('tr');

                // Checkbox
                const cbTd = document.createElement('td');
                cbTd.style.textAlign = 'center';
                const cb = document.createElement('input');
                cb.type = 'checkbox'; cb.className = 'row-cb';
                cb.dataset.id = row.id;
                cb.style.cssText = 'cursor:pointer;width:13px;height:13px;';
                cb.addEventListener('change', syncSelectAll);
                cbTd.appendChild(cb);
                tr.appendChild(cbTd);

                // #
                td(tr, idx + 1, 'text-center text-muted');

                // Videos column — thumbnail + clickable link (YouTube auto-thumb + onerror fallback)
                const videoTd = document.createElement('td');
                videoTd.style.textAlign = 'center';
                videoTd.style.verticalAlign = 'middle';
                videoTd.innerHTML = renderVideoCellHtml(row);
                tr.appendChild(videoTd);

                // Parent
                const parentTd = document.createElement('td');
                parentTd.style.overflow = 'hidden';
                parentTd.style.textOverflow = 'ellipsis';
                parentTd.style.whiteSpace = 'nowrap';
                parentTd.title = row.parent_name || '';
                parentTd.innerHTML = row.parent_name
                    ? '<span style="color:#6c757d;">' + esc(row.parent_name) + '</span>'
                    : '<span class="text-muted">—</span>';
                tr.appendChild(parentTd);

                // SKU
                const skuTd = document.createElement('td');
                skuTd.style.overflow = 'hidden';
                skuTd.style.textOverflow = 'ellipsis';
                skuTd.style.whiteSpace = 'nowrap';
                skuTd.title = row.sku || '';
                skuTd.innerHTML = '<span class="sku-badge">' + esc(row.sku) + '</span>';
                tr.appendChild(skuTd);

                // Text fields — green dot when data exists, dash when empty
                fields.filter(f => f.type === 'text').forEach(f => {
                    const cell = document.createElement('td');
                    cell.style.textAlign = 'center';
                    cell.style.verticalAlign = 'middle';
                    if (row[f.key]) {
                        cell.innerHTML =
                            '<span class="data-dot" title="' + esc(row[f.key]) + '"></span>';
                    } else {
                        cell.innerHTML = '<span class="text-muted">-</span>';
                    }
                    tr.appendChild(cell);
                });

                // Language field — flag emojis
                fields.filter(f => f.type === 'lang').forEach(f => {
                    const cell = document.createElement('td');
                    cell.style.textAlign = 'center';
                    cell.style.verticalAlign = 'middle';
                    const val = (row[f.key] || '').toLowerCase();
                    let flags = '';
                    if (val.includes('en')) flags += '<span title="English" style="font-size:16px;line-height:1;">🇺🇸</span>';
                    if (val.includes('es')) flags += '<span title="Spanish" style="font-size:16px;line-height:1;margin-left:2px;">🇪🇸</span>';
                    cell.innerHTML = flags || '<span class="text-muted">-</span>';
                    tr.appendChild(cell);
                });

                // URL fields — hyperlink icon only
                fields.filter(f => f.type === 'url').forEach(f => {
                    const cell = document.createElement('td');
                    cell.style.textAlign = 'center';
                    cell.style.verticalAlign = 'middle';
                    cell.innerHTML = row[f.key]
                        ? '<a href="' + esc(row[f.key]) + '" target="_blank" class="video-link-icon" title="' + esc(row[f.key]) + '"><i class="fas fa-link"></i></a>'
                        : '<span class="text-muted">-</span>';
                    tr.appendChild(cell);
                });

                // Status inline dropdown
                const statusOptions = ['Todo','Working','Archived','Done','Need Help','Need Approval','Dependent','Approved','Hold','Cancelled'];
                const curStatus = row.ads_status || 'Todo';
                const statusTd = document.createElement('td');
                statusTd.style.verticalAlign = 'middle';
                let statusOpts = statusOptions.map(s =>
                    '<option value="' + s + '"' + (s === curStatus ? ' selected' : '') + '>' + s + '</option>'
                ).join('');
                const ssClass = 'ss-' + curStatus.replace(' ', '_');
                statusTd.innerHTML = '<select class="status-select ' + ssClass + '" data-id="' + row.id + '">' + statusOpts + '</select>';
                statusTd.querySelector('.status-select').addEventListener('change', function() {
                    const newStatus = this.value;
                    this.className = 'status-select ss-' + newStatus.replace(' ', '_');
                    row.ads_status = newStatus;
                    patchRecord(row);
                });
                tr.appendChild(statusTd);

                // Approval dots (appr_s, appr_i, appr_n) + summary dot
                const summaryTd = document.createElement('td');
                summaryTd.style.verticalAlign = 'middle';
                const summaryDot = document.createElement('span');
                const allApproved = () => parseInt(row.appr_s) === 1 && parseInt(row.appr_i) === 1 && parseInt(row.appr_n) === 1;
                summaryDot.className = 'appr-dot appr-summary ' + (allApproved() ? 'on' : 'off');
                summaryDot.title = allApproved() ? 'All approved' : 'Not fully approved';
                summaryDot.style.width  = '16px';
                summaryDot.style.height = '16px';
                summaryDot.style.cursor = 'default';

                const updateSummary = () => {
                    const ok = allApproved();
                    summaryDot.classList.toggle('on',  ok);
                    summaryDot.classList.toggle('off', !ok);
                    summaryDot.title = ok ? 'All approved' : 'Not fully approved';
                };

                ['appr_s', 'appr_i', 'appr_n'].forEach(col => {
                    const apprTd = document.createElement('td');
                    apprTd.style.verticalAlign = 'middle';
                    const isOn = parseInt(row[col]) === 1;
                    const dot = document.createElement('span');
                    dot.className = 'appr-dot ' + (isOn ? 'on' : 'off');
                    dot.title = isOn ? 'Approved' : 'Not approved';
                    dot.addEventListener('click', function() {
                        const newVal = dot.classList.contains('on') ? 0 : 1;
                        dot.classList.toggle('on',  newVal === 1);
                        dot.classList.toggle('off', newVal === 0);
                        dot.title = newVal === 1 ? 'Approved' : 'Not approved';
                        row[col] = newVal;
                        updateSummary();
                        patchRecord(row);
                    });
                    apprTd.appendChild(dot);
                    tr.appendChild(apprTd);
                });

                summaryTd.appendChild(summaryDot);
                tr.appendChild(summaryTd);

                // Action
                const actTd = document.createElement('td');
                actTd.style.whiteSpace = 'nowrap';
                actTd.innerHTML =
                    '<button class="action-btn edit-btn me-1" data-id="' + row.id + '" title="Edit"><i class="fas fa-edit"></i></button>' +
                    '<button class="action-btn del-btn"  data-id="' + row.id + '" data-sku="' + esc(row.sku) + '" title="Delete"><i class="fas fa-trash"></i></button>';
                tr.appendChild(actTd);

                actTd.querySelector('.edit-btn').addEventListener('click', () => openModal('edit', row.id));
                actTd.querySelector('.del-btn').addEventListener('click', () => openDeleteConfirm(row.id, row.sku));

                tbody.appendChild(tr);
            });
        }

        function td(tr, val, cls = '') {
            const c = document.createElement('td');
            if (cls) c.className = cls;
            c.textContent = val ?? '—';
            tr.appendChild(c);
        }

        /* ── Counts ─────────────────────────────────────────────── */
        function updateCounts() {
            // counts removed per user request
        }

        /* ── Modal open ─────────────────────────────────────────── */
        function openModal(mode, id = null) {
            document.getElementById('videoForm').reset();
            document.getElementById('editId').value = '';
            setLangToggles('');

            const skuSelect      = document.getElementById('f_sku');
            const skuSelectWrap  = document.getElementById('skuSelectWrap');
            const skuDisplayWrap = document.getElementById('skuDisplayWrap');
            const skuBadge       = document.getElementById('skuDisplayBadge');

            if ($(skuSelect).hasClass('select2-hidden-accessible')) {
                $(skuSelect).select2('destroy');
            }

            if (mode === 'add') {
                document.getElementById('modalTitle').textContent = 'Add Video for Ads';
                skuSelectWrap.classList.remove('d-none');
                skuDisplayWrap.classList.add('d-none');

                skuSelect.innerHTML = '<option value="">Loading SKUs...</option>';
                document.getElementById('videoPreviewBox').classList.add('d-none');
                rebuildAudienceSelect();

                fetch('/product-master-data-view')
                    .then(r => r.json())
                    .then(resp => {
                        const data = resp.data ? resp.data : resp;
                        const alreadySaved = new Set(allData.map(r => r.sku));
                        skuSelect.innerHTML = '<option value="">Choose SKU...</option>';
                        (Array.isArray(data) ? data : []).forEach(item => {
                            if (item.SKU && !item.SKU.toUpperCase().includes('PARENT') && !alreadySaved.has(item.SKU)) {
                                const opt = document.createElement('option');
                                opt.value       = item.SKU;
                                opt.textContent = item.SKU;
                                skuSelect.appendChild(opt);
                            }
                        });
                        $(skuSelect).select2({
                            theme: 'bootstrap-5',
                            placeholder: 'Search, choose or type custom SKU...',
                            allowClear: true,
                            tags: true,
                            createTag: function(params) {
                                const term = $.trim(params.term);
                                if (!term) return null;
                                return { id: term, text: term + ' (custom)', newTag: true };
                            },
                            width: '100%',
                            dropdownParent: $('#videoModal')
                        });
                    })
                    .catch(() => {
                        skuSelect.innerHTML = '<option value="">Failed to load SKUs</option>';
                    });

            } else {
                const row = allData.find(r => r.id == id);
                if (!row) return;

                document.getElementById('modalTitle').textContent = 'Edit — ' + row.sku;
                document.getElementById('editId').value = row.id;

                skuSelectWrap.classList.add('d-none');
                skuDisplayWrap.classList.remove('d-none');
                skuBadge.textContent = row.sku;

                // Prefill category, status + video fields
                const catEl = document.getElementById('f_category');
                if (catEl) catEl.value = row.category || '';
                const statusEl = document.getElementById('f_ads_status');
                if (statusEl) statusEl.value = row.ads_status || 'Todo';

                const thumbEl = document.getElementById('f_video_thumbnail');
                const urlEl   = document.getElementById('f_video_url');
                if (thumbEl) thumbEl.value = row.video_thumbnail || '';
                if (urlEl)   urlEl.value   = row.video_url || '';
                updateVideoPreview();

                // Rebuild audience Select2 then set saved value
                rebuildAudienceSelect();
                const savedAudience = row.ads_audience || '';
                if (savedAudience) {
                    // Ensure option exists before setting
                    const audSel = document.getElementById('f_ads_audience_val');
                    if (audSel && !Array.from(audSel.options).some(o => o.value === savedAudience)) {
                        const o = document.createElement('option');
                        o.value = savedAudience; o.textContent = savedAudience;
                        audSel.appendChild(o);
                    }
                    $(audSel).val(savedAudience).trigger('change');
                }

                fields.filter(f => f.type === 'text').forEach(f => {
                    if (f.key === 'ads_audience') return; // handled above
                    const el = document.getElementById('f_' + f.key + '_val');
                    if (el) el.value = row[f.key] || '';
                });
                fields.filter(f => f.type === 'url').forEach(f => {
                    const url = document.getElementById('f_' + f.key + '_val');
                    if (url) url.value = row[f.key] || '';
                });

                // Language toggles prefill
                setLangToggles(row.ads_language || '');
            }

            document.getElementById('videoModal').addEventListener('hidden.bs.modal', function() {
                if ($(skuSelect).hasClass('select2-hidden-accessible')) $(skuSelect).select2('destroy');
                const audSel = document.getElementById('f_ads_audience_val');
                if (audSel && $(audSel).hasClass('select2-hidden-accessible')) $(audSel).select2('destroy');
            }, { once: true });

            videoModal.show();
        }

        /* ── Save ───────────────────────────────────────────────── */
        function saveRecord() {
            const editId   = document.getElementById('editId').value;
            const skuEl    = document.getElementById('f_sku');
            const skuBadge = document.getElementById('skuDisplayBadge');

            // In edit mode use the badge text; in add mode use Select2 value
            const sku = editId
                ? skuBadge.textContent.trim()
                : ($(skuEl).hasClass('select2-hidden-accessible') ? $(skuEl).val() : skuEl.value);

            skuEl.classList.remove('is-invalid');

            // Preserve appr values from existing row in edit mode; default to 0 for new records
            const existingRow = allData.find(r => String(r.id) === String(editId)) || {};
            const payload = { sku };
            payload.ads_status      = document.getElementById('f_ads_status').value;
            payload.appr_s          = existingRow.appr_s ?? 0;
            payload.appr_i          = existingRow.appr_i ?? 0;
            payload.appr_n          = existingRow.appr_n ?? 0;
            payload.category        = document.getElementById('f_category').value;
            payload.video_thumbnail = normalizeThumbUrlForImg(document.getElementById('f_video_thumbnail').value.trim());
            payload.video_url       = document.getElementById('f_video_url').value;
            fields.filter(f => f.type === 'text').forEach(f => {
                payload[f.key] = document.getElementById('f_' + f.key + '_val').value;
            });
            fields.filter(f => f.type === 'lang').forEach(f => {
                payload[f.key] = document.getElementById('f_' + f.key + '_val').value;
            });
            fields.filter(f => f.type === 'url').forEach(f => {
                payload[f.key] = document.getElementById('f_' + f.key + '_val').value;
            });

            const btn = document.getElementById('saveBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            fetch('/videos-for-ads/store', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    videoModal.hide();
                    loadData();
                } else {
                    alert(data.message || 'Failed to save');
                }
            })
            .catch(e => alert('Error: ' + e.message))
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> Save';
            });
        }

        /* ── Inline status patch ────────────────────────────────── */
        function patchRecord(row) {
            const payload = { sku: row.sku };
            payload.ads_status      = row.ads_status      || 'Todo';
            payload.appr_s          = row.appr_s          ?? 0;
            payload.appr_i          = row.appr_i          ?? 0;
            payload.appr_n          = row.appr_n          ?? 0;
            payload.category        = row.category        || '';
            payload.video_thumbnail = normalizeThumbUrlForImg(row.video_thumbnail || '');
            payload.video_url       = row.video_url       || '';
            fields.forEach(f => {
                payload[f.key] = row[f.key] || '';
            });
            fetch('/videos-for-ads/store', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(d => { if (!d.success) alert(d.message || 'Failed to save status'); })
            .catch(e => alert('Error: ' + e.message));
        }

        /* ── Delete ─────────────────────────────────────────────── */
        function openDeleteConfirm(id, sku) {
            deleteId = id;
            document.getElementById('deleteSku').textContent = sku;
            deleteModal.show();
        }

        function deleteRecord() {
            if (!deleteId) return;
            const btn = document.getElementById('confirmDeleteBtn');
            btn.disabled = true;

            fetch('/videos-for-ads/' + deleteId, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrf }
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    deleteModal.hide();
                    loadData();
                } else {
                    alert(data.message || 'Failed to delete');
                }
            })
            .catch(e => alert('Error: ' + e.message))
            .finally(() => { btn.disabled = false; deleteId = null; });
        }

        /* ── Export ─────────────────────────────────────────────── */
        function exportExcel() {
            const rows = allData.map(row => {
                const r = { SKU: row.sku };
                fields.forEach(f => {
                    r[f.label] = row[f.key] || '';
                    if (f.type === 'link') r[f.label + ' Status'] = row[f.key + '_status'] || '';
                });
                return r;
            });
            const ws = XLSX.utils.json_to_sheet(rows);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Videos for Ads');
            XLSX.writeFile(wb, 'videos_for_ads_' + new Date().toISOString().split('T')[0] + '.xlsx');
        }

        /* ── Video thumbnail live preview ───────────────────────── */
        /* ── Language toggle helpers ─────────────────────────────── */
        function setLangToggles(value) {
            const val = (value || '').toLowerCase();
            document.querySelectorAll('.lang-toggle').forEach(btn => {
                const lang = btn.dataset.lang;
                if (val.includes(lang)) {
                    btn.classList.remove('btn-outline-secondary');
                    btn.classList.add('btn-primary');
                } else {
                    btn.classList.remove('btn-primary');
                    btn.classList.add('btn-outline-secondary');
                }
            });
            syncLangHidden();
        }

        function syncLangHidden() {
            const selected = [];
            document.querySelectorAll('.lang-toggle.btn-primary').forEach(btn => selected.push(btn.dataset.lang));
            document.getElementById('f_ads_language_val').value = selected.join(',');
        }

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.lang-toggle')) return;
            const btn = e.target.closest('.lang-toggle');
            btn.classList.toggle('btn-primary');
            btn.classList.toggle('btn-outline-secondary');
            syncLangHidden();
        });

        /* ── Import ──────────────────────────────────────────────── */
        function openImportModal() {
            document.getElementById('importFile').value = '';
            document.getElementById('importProgress').classList.add('d-none');
            document.getElementById('importResult').classList.add('d-none');
            document.getElementById('importResult').innerHTML = '';
            importModal.show();
        }

        function doImport() {
            const fileEl = document.getElementById('importFile');
            if (!fileEl.files.length) {
                alert('Please select a CSV file first.');
                return;
            }
            const btn = document.getElementById('doImportBtn');
            btn.disabled = true;
            document.getElementById('importProgress').classList.remove('d-none');
            document.getElementById('importResult').classList.add('d-none');
            document.getElementById('importResult').innerHTML = '';

            const form = new FormData();
            form.append('file', fileEl.files[0]);
            form.append('_token', csrf);

            fetch('/videos-for-ads/import', { method: 'POST', body: form })
                .then(r => r.json())
                .then(d => {
                    document.getElementById('importProgress').classList.add('d-none');
                    const res = document.getElementById('importResult');
                    res.classList.remove('d-none');
                    if (d.success) {
                        let html = '<div class="alert alert-success py-2 mb-2" style="font-size:12px;">'
                            + '<i class="fas fa-check-circle me-1"></i>'
                            + '<strong>' + d.imported + ' record(s) imported/updated</strong>'
                            + (d.skipped ? ', ' + d.skipped + ' skipped.' : '.')
                            + '</div>';
                        if (d.errors && d.errors.length) {
                            html += '<div class="alert alert-warning py-2" style="font-size:11px;"><strong>Warnings:</strong><ul class="mb-0 mt-1 ps-3">'
                                + d.errors.map(e => '<li>' + e + '</li>').join('')
                                + '</ul></div>';
                        }
                        res.innerHTML = html;
                        loadData();
                    } else {
                        res.innerHTML = '<div class="alert alert-danger py-2" style="font-size:12px;">'
                            + '<i class="fas fa-times-circle me-1"></i>' + (d.message || 'Import failed.') + '</div>';
                    }
                })
                .catch(e => {
                    document.getElementById('importProgress').classList.add('d-none');
                    document.getElementById('importResult').classList.remove('d-none');
                    document.getElementById('importResult').innerHTML = '<div class="alert alert-danger py-2" style="font-size:12px;">Error: ' + e.message + '</div>';
                })
                .finally(() => { btn.disabled = false; });
        }

        function downloadCsvTemplate(e) {
            e.preventDefault();
            const headers = [
                'sku','ads_status','category','appr_s','appr_i','appr_n',
                'video_thumbnail','video_url','ads_topic_story','ads_what',
                'ads_why_purpose','ads_audience','ads_benefit_audience',
                'ads_language','ads_script_link','ads_video_en_link'
            ];
            const example = [
                'SAMPLE-SKU-001','Todo','Category','0','0','0',
                'https://example.com/thumb.jpg','https://example.com/video.mp4',
                'Topic text','What text','Why text','Drummer','Benefit text',
                'en','https://example.com/script','https://example.com/link'
            ];
            const csv = headers.join(',') + '\n' + example.join(',');
            const blob = new Blob([csv], { type: 'text/csv' });
            const url  = URL.createObjectURL(blob);
            const a    = document.createElement('a');
            a.href = url; a.download = 'videos_for_ads_template.csv';
            a.click(); URL.revokeObjectURL(url);
        }

        function updateVideoPreview() {
            const thumbEl   = document.getElementById('f_video_thumbnail');
            const urlEl     = document.getElementById('f_video_url');
            const previewBox   = document.getElementById('videoPreviewBox');
            const previewThumb = document.getElementById('videoPreviewThumb');
            const hintEl       = document.getElementById('thumbUrlHint');
            if (!thumbEl || !previewBox || !previewThumb) return;
            const rawThumb = thumbEl.value.trim();
            const videoUrl = urlEl ? urlEl.value.trim() : '';
            let displaySrc = '';
            if (rawThumb) displaySrc = normalizeThumbUrlForImg(rawThumb);
            else {
                const ytId = extractYoutubeId(videoUrl);
                if (ytId) displaySrc = youtubeThumbUrl(ytId);
            }
            if (hintEl) {
                hintEl.classList.add('d-none');
                hintEl.textContent = '';
            }
            if (displaySrc) {
                previewThumb.referrerPolicy = 'no-referrer';
                previewThumb.onload = function () {
                    if (hintEl && !/\/scl\/fo\//i.test(rawThumb)) {
                        hintEl.classList.add('d-none');
                        hintEl.textContent = '';
                    }
                };
                previewThumb.onerror = function () {
                    previewThumb.onerror = null;
                    if (hintEl) {
                        hintEl.classList.remove('d-none');
                        hintEl.textContent = /\/scl\/fo\//i.test(rawThumb)
                            ? 'This is a Dropbox folder link, not an image file. Share only the image file and paste that link (or upload elsewhere and use a direct image URL).'
                            : 'Could not load this URL as an image. For Dropbox files, ensure the link is for the image file with direct access (dl=0 is converted to raw=1 on save).';
                    }
                };
                previewThumb.src = displaySrc;
                previewThumb.onclick = videoUrl ? function () { window.open(videoUrl, '_blank'); } : null;
                previewBox.classList.remove('d-none');
                if (hintEl && /\/scl\/fo\//i.test(rawThumb)) {
                    hintEl.classList.remove('d-none');
                    hintEl.textContent = 'Dropbox folder URLs cannot be used as thumbnails. Use a share link that points to the image file itself.';
                }
            } else {
                previewBox.classList.add('d-none');
                previewThumb.removeAttribute('src');
            }
        }

        // Bind live preview on thumbnail input
        document.addEventListener('DOMContentLoaded', function() {
            const thumbInput = document.getElementById('f_video_thumbnail');
            const urlInput   = document.getElementById('f_video_url');
            if (thumbInput) thumbInput.addEventListener('input', updateVideoPreview);
            if (urlInput)   urlInput.addEventListener('input', updateVideoPreview);
        });

        /* ── Helpers ────────────────────────────────────────────── */
        function isEmpty(v) { return v === null || v === undefined || v === '' || (typeof v === 'string' && !v.trim()); }

        function esc(t) {
            if (!t) return '';
            return String(t).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
        }
        @endverbatim
    </script>
@endsection
