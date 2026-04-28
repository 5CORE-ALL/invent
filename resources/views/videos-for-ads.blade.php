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
            border-radius: 10px;
            max-height: 620px;
            overflow: auto;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            background: #fff;
        }

        .table-wrapper table {
            margin-bottom: 0;
        }

        .table-wrapper thead th {
            position: sticky;
            top: 0;
            background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%) !important;
            color: #fff;
            z-index: 10;
            padding: 14px 14px 8px;
            font-weight: 600;
            border-bottom: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            font-size: 12px;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            white-space: nowrap;
            vertical-align: top;
        }

        .table-wrapper thead th:hover {
            background: linear-gradient(135deg, #1a56b7 0%, #0a3d8f 100%) !important;
        }

        .th-filter input,
        .th-filter select {
            background: rgba(255,255,255,0.92);
            border: none;
            border-radius: 4px;
            color: #333;
            padding: 5px 8px;
            margin-top: 6px;
            font-size: 11px;
            width: 100%;
            min-width: 90px;
        }

        .th-filter input:focus,
        .th-filter select:focus {
            background: #fff;
            box-shadow: 0 0 0 2px rgba(26,86,183,0.3);
            outline: none;
        }

        .table-wrapper tbody td {
            padding: 11px 14px;
            vertical-align: middle;
            border-bottom: 1px solid #edf2f9;
            font-size: 13px;
            color: #495057;
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
            padding: 5px 11px;
            border: none;
            border-radius: 4px;
            font-size: 12px;
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

        .sku-badge {
            background: #e8f0fe;
            color: #1a56b7;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            white-space: nowrap;
        }

        #total-count {
            font-size: 13px;
            color: #6c757d;
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
                    <div class="mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex gap-2 flex-wrap">
                            <button id="addBtn" class="btn btn-success">
                                <i class="fas fa-plus"></i> Add Video
                            </button>
                            <button id="exportBtn" class="btn btn-primary">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                        <span id="total-count"></span>
                    </div>

                    {{-- Table --}}
                    <div class="table-wrapper">
                        <table class="table w-100" id="ads-table">
                            <thead>
                                <tr>
                                    <th style="min-width:50px;">#</th>
                                    <th style="min-width:60px;">Images</th>
                                    <th style="min-width:140px;">
                                        Parent
                                        <div class="th-filter">
                                            <input type="text" id="parentSearch" placeholder="Search Parent">
                                        </div>
                                    </th>
                                    <th style="min-width:130px;">
                                        SKU <span id="skuCount" class="badge-count">(0)</span>
                                        <div class="th-filter">
                                            <input type="text" id="skuSearch" placeholder="Search SKU">
                                        </div>
                                    </th>
                                    <th style="min-width:140px;">
                                        Topic / Story
                                        <span id="mc_ads_topic_story" class="badge-count">(0)</span>
                                        <span class="missing-label">Missing</span>
                                        <div class="th-filter">
                                            <select id="f_ads_topic_story">
                                                <option value="all">All Data</option>
                                                <option value="missing">Missing</option>
                                            </select>
                                        </div>
                                    </th>
                                    <th style="min-width:140px;">
                                        What
                                        <span id="mc_ads_what" class="badge-count">(0)</span>
                                        <span class="missing-label">Missing</span>
                                        <div class="th-filter">
                                            <select id="f_ads_what">
                                                <option value="all">All Data</option>
                                                <option value="missing">Missing</option>
                                            </select>
                                        </div>
                                    </th>
                                    <th style="min-width:150px;">
                                        Why (Purpose)
                                        <span id="mc_ads_why_purpose" class="badge-count">(0)</span>
                                        <span class="missing-label">Missing</span>
                                        <div class="th-filter">
                                            <select id="f_ads_why_purpose">
                                                <option value="all">All Data</option>
                                                <option value="missing">Missing</option>
                                            </select>
                                        </div>
                                    </th>
                                    <th style="min-width:140px;">
                                        Audience
                                        <span id="mc_ads_audience" class="badge-count">(0)</span>
                                        <span class="missing-label">Missing</span>
                                        <div class="th-filter">
                                            <select id="f_ads_audience">
                                                <option value="all">All Data</option>
                                                <option value="missing">Missing</option>
                                            </select>
                                        </div>
                                    </th>
                                    <th style="min-width:160px;">
                                        Benefit to Audience
                                        <span id="mc_ads_benefit_audience" class="badge-count">(0)</span>
                                        <span class="missing-label">Missing</span>
                                        <div class="th-filter">
                                            <select id="f_ads_benefit_audience">
                                                <option value="all">All Data</option>
                                                <option value="missing">Missing</option>
                                            </select>
                                        </div>
                                    </th>
                                    <th style="min-width:130px;">
                                        Location
                                        <span id="mc_ads_location" class="badge-count">(0)</span>
                                        <span class="missing-label">Missing</span>
                                        <div class="th-filter">
                                            <select id="f_ads_location">
                                                <option value="all">All Data</option>
                                                <option value="missing">Missing</option>
                                            </select>
                                        </div>
                                    </th>
                                    <th style="min-width:130px;">
                                        Language
                                        <span id="mc_ads_language" class="badge-count">(0)</span>
                                        <span class="missing-label">Missing</span>
                                        <div class="th-filter">
                                            <select id="f_ads_language">
                                                <option value="all">All Data</option>
                                                <option value="missing">Missing</option>
                                            </select>
                                        </div>
                                    </th>
                                    <th style="min-width:150px;">
                                        Script Link
                                        <span id="mc_ads_script_link" class="badge-count">(0)</span>
                                        <span class="missing-label">Missing</span>
                                        <div class="th-filter">
                                            <select id="f_ads_script_link">
                                                <option value="all">All Data</option>
                                                <option value="missing">Missing</option>
                                            </select>
                                        </div>
                                    </th>
                                    <th style="min-width:160px;">
                                        Video EN Link
                                        <span id="mc_ads_video_en_link" class="badge-count">(0)</span>
                                        <span class="missing-label">Missing</span>
                                        <div class="th-filter">
                                            <select id="f_ads_video_en_link">
                                                <option value="all">All Data</option>
                                                <option value="missing">Missing</option>
                                            </select>
                                        </div>
                                    </th>
                                    <th style="min-width:160px;">
                                        Video ES Link
                                        <span id="mc_ads_video_es_link" class="badge-count">(0)</span>
                                        <span class="missing-label">Missing</span>
                                        <div class="th-filter">
                                            <select id="f_ads_video_es_link">
                                                <option value="all">All Data</option>
                                                <option value="missing">Missing</option>
                                            </select>
                                        </div>
                                    </th>
                                    <th style="min-width:120px;">Action</th>
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
                            <label class="form-label fw-semibold">SKU <span class="text-danger">*</span></label>
                            <select class="form-select" id="f_sku" name="sku" required>
                                <option value="">Choose SKU...</option>
                            </select>
                            <div class="invalid-feedback">Please select a SKU.</div>
                        </div>
                        <div class="mb-3 d-none" id="skuDisplayWrap">
                            <label class="form-label fw-semibold">SKU</label>
                            <div id="skuDisplayBadge" class="sku-badge d-inline-block px-3 py-2" style="font-size:14px;"></div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Topic / Story</label>
                                <input type="text" class="form-control" id="f_ads_topic_story_val"
                                       name="ads_topic_story" placeholder="Enter topic or story...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">What</label>
                                <input type="text" class="form-control" id="f_ads_what_val"
                                       name="ads_what" placeholder="What is the video about?">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Why (Purpose)</label>
                                <input type="text" class="form-control" id="f_ads_why_purpose_val"
                                       name="ads_why_purpose" placeholder="Purpose of the video...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Audience</label>
                                <input type="text" class="form-control" id="f_ads_audience_val"
                                       name="ads_audience" placeholder="Target audience...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Benefit to Audience</label>
                                <input type="text" class="form-control" id="f_ads_benefit_audience_val"
                                       name="ads_benefit_audience" placeholder="Audience benefit...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Location</label>
                                <input type="text" class="form-control" id="f_ads_location_val"
                                       name="ads_location" placeholder="Shooting location...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Language</label>
                                <input type="text" class="form-control" id="f_ads_language_val"
                                       name="ads_language" placeholder="e.g. English, Spanish...">
                            </div>
                        </div>

                        <hr class="my-3">

                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Script Link</label>
                                <div class="input-group">
                                    <input type="url" class="form-control" id="f_ads_script_link_val"
                                           name="ads_script_link" placeholder="https://">
                                    <select class="form-select" id="f_ads_script_link_status_val"
                                            name="ads_script_link_status" style="max-width:160px;">
                                        <option value="">-- Status --</option>
                                        <option value="N/R">N/R</option>
                                        <option value="Done/Uploaded">Done/Uploaded</option>
                                        <option value="Assigned">Assigned</option>
                                        <option value="In Progress">In Progress</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Video EN Link</label>
                                <div class="input-group">
                                    <input type="url" class="form-control" id="f_ads_video_en_link_val"
                                           name="ads_video_en_link" placeholder="https://">
                                    <select class="form-select" id="f_ads_video_en_link_status_val"
                                            name="ads_video_en_link_status" style="max-width:160px;">
                                        <option value="">-- Status --</option>
                                        <option value="N/R">N/R</option>
                                        <option value="Done/Uploaded">Done/Uploaded</option>
                                        <option value="Assigned">Assigned</option>
                                        <option value="In Progress">In Progress</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Video ES Link</label>
                                <div class="input-group">
                                    <input type="url" class="form-control" id="f_ads_video_es_link_val"
                                           name="ads_video_es_link" placeholder="https://">
                                    <select class="form-select" id="f_ads_video_es_link_status_val"
                                            name="ads_video_es_link_status" style="max-width:160px;">
                                        <option value="">-- Status --</option>
                                        <option value="N/R">N/R</option>
                                        <option value="Done/Uploaded">Done/Uploaded</option>
                                        <option value="Assigned">Assigned</option>
                                        <option value="In Progress">In Progress</option>
                                    </select>
                                </div>
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
@endsection

@section('script')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        @verbatim
        const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        let allData      = [];
        let deleteId     = null;
        let videoModal, deleteModal;

        // Field definitions: type 'text' = plain text, type 'link' = URL + status
        const fields = [
            { key: 'ads_topic_story',      label: 'Topic / Story',       type: 'text' },
            { key: 'ads_what',             label: 'What',                type: 'text' },
            { key: 'ads_why_purpose',      label: 'Why (Purpose)',       type: 'text' },
            { key: 'ads_audience',         label: 'Audience',            type: 'text' },
            { key: 'ads_benefit_audience', label: 'Benefit to Audience', type: 'text' },
            { key: 'ads_location',         label: 'Location',            type: 'text' },
            { key: 'ads_language',         label: 'Language',            type: 'text' },
            { key: 'ads_script_link',      label: 'Script Link',         type: 'link' },
            { key: 'ads_video_en_link',    label: 'Video EN Link',       type: 'link' },
            { key: 'ads_video_es_link',    label: 'Video ES Link',       type: 'link' },
        ];

        const statusOpts = ['', 'N/R', 'Done/Uploaded', 'Assigned', 'In Progress'];
        const statusLbls = ['--', 'N/R', 'Done/Uploaded', 'Assigned', 'In Progress'];

        /* ── Bootstrap ─────────────────────────────────────────── */
        document.addEventListener('DOMContentLoaded', () => {
            videoModal  = new bootstrap.Modal(document.getElementById('videoModal'));
            deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));

            loadData();
            bindToolbar();
            bindFilters();
            bindTableEvents();
        });

        /* ── Toolbar ────────────────────────────────────────────── */
        function bindToolbar() {
            document.getElementById('addBtn').addEventListener('click', () => openModal('add'));
            document.getElementById('exportBtn').addEventListener('click', exportExcel);
            document.getElementById('saveBtn').addEventListener('click', saveRecord);
            document.getElementById('confirmDeleteBtn').addEventListener('click', deleteRecord);
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
        function bindFilters() {
            document.getElementById('skuSearch').addEventListener('input', applyFilters);
            document.getElementById('parentSearch').addEventListener('input', applyFilters);
            fields.forEach(f => {
                document.getElementById('f_' + f.key).addEventListener('change', applyFilters);
            });
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
        function applyFilters() {
            const skuQ    = document.getElementById('skuSearch').value.toLowerCase();
            const parentQ = document.getElementById('parentSearch').value.toLowerCase();
            const filterVals = {};
            fields.forEach(f => { filterVals[f.key] = document.getElementById('f_' + f.key).value; });

            const filtered = allData.filter(row => {
                if (skuQ    && !row.sku.toLowerCase().includes(skuQ)) return false;
                if (parentQ && !(row.parent_name && row.parent_name.toLowerCase().includes(parentQ))) return false;
                for (const f of fields) {
                    if (filterVals[f.key] === 'missing' && !isEmpty(row[f.key])) return false;
                }
                return true;
            });

            renderTable(filtered);
        }

        function renderTable(data) {
            const tbody = document.getElementById('table-body');
            tbody.innerHTML = '';

            if (!data.length) {
                tbody.innerHTML = '<tr><td colspan="15" class="text-center py-4 text-muted">No records found</td></tr>';
                document.getElementById('total-count').textContent = '0 records';
                return;
            }

            document.getElementById('total-count').textContent = data.length + ' record' + (data.length !== 1 ? 's' : '');

            data.forEach((row, idx) => {
                const tr = document.createElement('tr');

                // #
                td(tr, idx + 1, 'text-center text-muted');

                // Image
                const imgTd = document.createElement('td');
                imgTd.style.textAlign = 'center';
                imgTd.innerHTML = row.image_path
                    ? '<img src="' + esc(row.image_path) + '" style="width:40px;height:40px;object-fit:cover;border-radius:4px;">'
                    : '<span class="text-muted">—</span>';
                tr.appendChild(imgTd);

                // Parent
                const parentTd = document.createElement('td');
                parentTd.innerHTML = row.parent_name
                    ? '<span style="font-size:12px;color:#6c757d;">' + esc(row.parent_name) + '</span>'
                    : '<span class="text-muted" style="font-size:12px;">—</span>';
                tr.appendChild(parentTd);

                // SKU
                const skuTd = document.createElement('td');
                skuTd.innerHTML = '<span class="sku-badge">' + esc(row.sku) + '</span>';
                tr.appendChild(skuTd);

                // Text fields
                fields.filter(f => f.type === 'text').forEach(f => {
                    const cell = document.createElement('td');
                    if (row[f.key]) {
                        cell.innerHTML = '<span class="cell-text" title="' + esc(row[f.key]) + '">' + esc(row[f.key]) + '</span>';
                    } else {
                        cell.innerHTML = '<span class="text-muted" style="font-size:12px;">—</span>';
                    }
                    tr.appendChild(cell);
                });

                // Link fields
                fields.filter(f => f.type === 'link').forEach(f => {
                    const cell = document.createElement('td');
                    cell.style.textAlign = 'center';
                    const statusVal = row[f.key + '_status'] || '';
                    const linkHtml  = row[f.key]
                        ? '<a href="' + esc(row[f.key]) + '" target="_blank" class="video-link-icon" title="' + esc(row[f.key]) + '"><i class="fas fa-play-circle"></i></a>'
                        : '<span class="text-muted" style="font-size:12px;">—</span>';
                    const opts = statusOpts.map((v, i) =>
                        '<option value="' + esc(v) + '"' + (v === statusVal ? ' selected' : '') + '>' + esc(statusLbls[i]) + '</option>'
                    ).join('');
                    cell.innerHTML =
                        '<div class="d-flex flex-column align-items-center gap-1">' +
                            linkHtml +
                            '<select class="form-select form-select-sm inline-status mt-1" style="font-size:11px;min-width:105px;" ' +
                                'data-id="' + row.id + '" data-field="' + f.key + '">' +
                            opts + '</select>' +
                        '</div>';
                    tr.appendChild(cell);
                });

                // Action
                const actTd = document.createElement('td');
                actTd.style.whiteSpace = 'nowrap';
                actTd.innerHTML =
                    '<button class="action-btn edit-btn me-1" data-id="' + row.id + '"><i class="fas fa-edit"></i> Edit</button>' +
                    '<button class="action-btn del-btn"  data-id="' + row.id + '" data-sku="' + esc(row.sku) + '"><i class="fas fa-trash"></i></button>';
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
            document.getElementById('skuCount').textContent = '(' + allData.length + ')';
            fields.forEach(f => {
                const missing = allData.filter(r => isEmpty(r[f.key])).length;
                document.getElementById('mc_' + f.key).textContent = '(' + missing + ')';
            });
        }

        /* ── Modal open ─────────────────────────────────────────── */
        function openModal(mode, id = null) {
            document.getElementById('videoForm').reset();
            document.getElementById('editId').value = '';

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
                            placeholder: 'Search & choose SKU...',
                            allowClear: true,
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

                fields.filter(f => f.type === 'text').forEach(f => {
                    const el = document.getElementById('f_' + f.key + '_val');
                    if (el) el.value = row[f.key] || '';
                });
                fields.filter(f => f.type === 'link').forEach(f => {
                    const url = document.getElementById('f_' + f.key + '_val');
                    const sts = document.getElementById('f_' + f.key + '_status_val');
                    if (url) url.value = row[f.key] || '';
                    if (sts) sts.value = row[f.key + '_status'] || '';
                });
            }

            document.getElementById('videoModal').addEventListener('hidden.bs.modal', function() {
                if ($(skuSelect).hasClass('select2-hidden-accessible')) $(skuSelect).select2('destroy');
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

            if (!sku) {
                skuEl.classList.add('is-invalid');
                return;
            }
            skuEl.classList.remove('is-invalid');

            const payload = { sku };
            fields.filter(f => f.type === 'text').forEach(f => {
                payload[f.key] = document.getElementById('f_' + f.key + '_val').value;
            });
            fields.filter(f => f.type === 'link').forEach(f => {
                payload[f.key]            = document.getElementById('f_' + f.key + '_val').value;
                payload[f.key + '_status'] = document.getElementById('f_' + f.key + '_status_val').value;
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
            fields.forEach(f => {
                payload[f.key] = row[f.key] || '';
                if (f.type === 'link') payload[f.key + '_status'] = row[f.key + '_status'] || '';
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

        /* ── Helpers ────────────────────────────────────────────── */
        function isEmpty(v) { return v === null || v === undefined || v === '' || (typeof v === 'string' && !v.trim()); }

        function esc(t) {
            if (!t) return '';
            return String(t).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
        }
        @endverbatim
    </script>
@endsection
