@extends('layouts.vertical', ['title' => 'Videos for Ads', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

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

        .table-responsive thead input,
        .table-responsive thead select {
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

        .table-responsive thead input:focus,
        .table-responsive thead select:focus {
            background-color: white;
            box-shadow: 0 0 0 2px rgba(26, 86, 183, 0.3);
            outline: none;
        }

        .table-responsive tbody td {
            padding: 12px 18px;
            vertical-align: middle;
            border-bottom: 1px solid #edf2f9;
            font-size: 13px;
            color: #495057;
        }

        .table-responsive tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .table-responsive tbody tr:hover {
            background-color: #e8f0fe;
        }

        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            margin: 0 2px;
        }

        .edit-btn {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            color: white;
        }

        .edit-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(255, 193, 7, 0.3);
        }

        #rainbow-loader {
            display: none;
            text-align: center;
            padding: 40px;
        }

        .rainbow-loader .loading-text {
            margin-top: 20px;
            font-weight: bold;
            color: #2c6ed5;
        }

        .modal-header-gradient {
            background: linear-gradient(135deg, #6B73FF 0%, #000DFF 100%);
            border-bottom: 4px solid #4D55E6;
            color: white;
        }

        .video-link-icon {
            color: #2c6ed5;
            font-size: 20px;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .video-link-icon:hover {
            color: #1a56b7;
            transform: scale(1.2);
        }

        .video-link-icon i {
            vertical-align: middle;
        }

        .text-cell-value {
            max-width: 140px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: inline-block;
            font-size: 12px;
        }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('layouts.shared/page-title', [
        'page_title' => 'Videos for Ads',
        'sub_title' => 'Manage Product Ad Videos',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="mb-3 d-flex justify-content-between align-items-center">
                        <div>
                            <button id="addVideoBtn" class="btn btn-success">
                                <i class="fas fa-plus"></i> Add Videos
                            </button>
                            <button id="exportBtn" class="btn btn-primary ms-2">
                                <i class="fas fa-download"></i> Export
                            </button>
                            <button id="importBtn" class="btn btn-info ms-2">
                                <i class="fas fa-upload"></i> Import
                            </button>
                            <input type="file" id="importFile" accept=".csv,.xlsx,.xls" style="display: none;">
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table id="videos-for-ads-table" class="table dt-responsive nowrap w-100">
                            <thead>
                                <tr>
                                    <th>Images</th>
                                    <th>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span>Parent</span>
                                            <span id="parentCount">(0)</span>
                                        </div>
                                        <input type="text" id="parentSearch" class="form-control-sm" placeholder="Search Parent">
                                    </th>
                                    <th>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span>SKU</span>
                                            <span id="skuCount">(0)</span>
                                        </div>
                                        <input type="text" id="skuSearch" class="form-control-sm" placeholder="Search SKU">
                                    </th>
                                    <th>
                                        <div>Shopify Inv</div>
                                    </th>
                                    <th>
                                        <div>Topic / Story <span id="missingCount_ads_topic_story" class="text-danger" style="font-weight:bold;">(0)</span></div>
                                        <div class="small text-white-50">Status</div>
                                        <select id="filter_ads_topic_story" class="form-control form-control-sm mt-1" style="font-size:11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div>What <span id="missingCount_ads_what" class="text-danger" style="font-weight:bold;">(0)</span></div>
                                        <div class="small text-white-50">Status</div>
                                        <select id="filter_ads_what" class="form-control form-control-sm mt-1" style="font-size:11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div>Why (Purpose) <span id="missingCount_ads_why_purpose" class="text-danger" style="font-weight:bold;">(0)</span></div>
                                        <div class="small text-white-50">Status</div>
                                        <select id="filter_ads_why_purpose" class="form-control form-control-sm mt-1" style="font-size:11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div>Audience <span id="missingCount_ads_audience" class="text-danger" style="font-weight:bold;">(0)</span></div>
                                        <div class="small text-white-50">Status</div>
                                        <select id="filter_ads_audience" class="form-control form-control-sm mt-1" style="font-size:11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div>Benefit to Audience <span id="missingCount_ads_benefit_audience" class="text-danger" style="font-weight:bold;">(0)</span></div>
                                        <div class="small text-white-50">Status</div>
                                        <select id="filter_ads_benefit_audience" class="form-control form-control-sm mt-1" style="font-size:11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div>Location <span id="missingCount_ads_location" class="text-danger" style="font-weight:bold;">(0)</span></div>
                                        <div class="small text-white-50">Status</div>
                                        <select id="filter_ads_location" class="form-control form-control-sm mt-1" style="font-size:11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div>Language <span id="missingCount_ads_language" class="text-danger" style="font-weight:bold;">(0)</span></div>
                                        <div class="small text-white-50">Status</div>
                                        <select id="filter_ads_language" class="form-control form-control-sm mt-1" style="font-size:11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div>Script Link <span id="missingCount_ads_script_link" class="text-danger" style="font-weight:bold;">(0)</span></div>
                                        <div class="small text-white-50">Status</div>
                                        <select id="filter_ads_script_link" class="form-control form-control-sm mt-1" style="font-size:11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div>Video EN Link <span id="missingCount_ads_video_en_link" class="text-danger" style="font-weight:bold;">(0)</span></div>
                                        <div class="small text-white-50">Status</div>
                                        <select id="filter_ads_video_en_link" class="form-control form-control-sm mt-1" style="font-size:11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div>Video ES Link <span id="missingCount_ads_video_es_link" class="text-danger" style="font-weight:bold;">(0)</span></div>
                                        <div class="small text-white-50">Status</div>
                                        <select id="filter_ads_video_es_link" class="form-control form-control-sm mt-1" style="font-size:11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="table-body"></tbody>
                        </table>
                    </div>

                    <div id="rainbow-loader" class="rainbow-loader">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <div class="loading-text">Loading Videos Data...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Videos for Ads Modal -->
    <div class="modal fade" id="videoModal" tabindex="-1" aria-labelledby="videoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title" id="videoModalLabel">
                        <i class="fas fa-video me-2"></i><span id="modalTitle">Add Videos for Ads</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="videoForm">
                        <input type="hidden" id="editSku" name="sku">

                        <div class="mb-3">
                            <label for="selectSku" class="form-label">Select SKU <span class="text-danger">*</span></label>
                            <select class="form-select" id="selectSku" name="sku" required>
                                <option value="">Choose SKU...</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="ads_topic_story" class="form-label">Topic / Story</label>
                            <input type="text" class="form-control" id="ads_topic_story" name="ads_topic_story" placeholder="Enter topic or story...">
                        </div>

                        <div class="mb-3">
                            <label for="ads_what" class="form-label">What</label>
                            <input type="text" class="form-control" id="ads_what" name="ads_what" placeholder="What is the video about?">
                        </div>

                        <div class="mb-3">
                            <label for="ads_why_purpose" class="form-label">Why (Purpose)</label>
                            <input type="text" class="form-control" id="ads_why_purpose" name="ads_why_purpose" placeholder="Purpose of the video...">
                        </div>

                        <div class="mb-3">
                            <label for="ads_audience" class="form-label">Audience</label>
                            <input type="text" class="form-control" id="ads_audience" name="ads_audience" placeholder="Target audience...">
                        </div>

                        <div class="mb-3">
                            <label for="ads_benefit_audience" class="form-label">Benefit to Audience</label>
                            <input type="text" class="form-control" id="ads_benefit_audience" name="ads_benefit_audience" placeholder="Benefit to the audience...">
                        </div>

                        <div class="mb-3">
                            <label for="ads_location" class="form-label">Location</label>
                            <input type="text" class="form-control" id="ads_location" name="ads_location" placeholder="Shooting location...">
                        </div>

                        <div class="mb-3">
                            <label for="ads_language" class="form-label">Language</label>
                            <input type="text" class="form-control" id="ads_language" name="ads_language" placeholder="e.g. English, Spanish...">
                        </div>

                        <div class="mb-3">
                            <label for="ads_script_link" class="form-label">Script Link</label>
                            <input type="url" class="form-control" id="ads_script_link" name="ads_script_link" placeholder="https://">
                            <select class="form-select form-select-sm mt-1" id="ads_script_link_status" name="ads_script_link_status">
                                <option value="">-- Status --</option>
                                <option value="N/R">N/R</option>
                                <option value="Done/Uploaded">Done/Uploaded</option>
                                <option value="Assigned">Assigned</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="ads_video_en_link" class="form-label">Video EN Link</label>
                            <input type="url" class="form-control" id="ads_video_en_link" name="ads_video_en_link" placeholder="https://">
                            <select class="form-select form-select-sm mt-1" id="ads_video_en_link_status" name="ads_video_en_link_status">
                                <option value="">-- Status --</option>
                                <option value="N/R">N/R</option>
                                <option value="Done/Uploaded">Done/Uploaded</option>
                                <option value="Assigned">Assigned</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="ads_video_es_link" class="form-label">Video ES Link</label>
                            <input type="url" class="form-control" id="ads_video_es_link" name="ads_video_es_link" placeholder="https://">
                            <select class="form-select form-select-sm mt-1" id="ads_video_es_link_status" name="ads_video_es_link_status">
                                <option value="">-- Status --</option>
                                <option value="N/R">N/R</option>
                                <option value="Done/Uploaded">Done/Uploaded</option>
                                <option value="Assigned">Assigned</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveVideoBtn">
                        <i class="fas fa-save"></i> Save
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
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        let tableData = [];
        let videoModal;

        /**
         * type: 'text'  → plain text input in modal, text value in table cell
         * type: 'link'  → URL input + status dropdown in modal, link icon + status dropdown in table
         */
        const adFields = [
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

        const statusValues = ['', 'N/R', 'Done/Uploaded', 'Assigned'];
        const statusLabels = ['--', 'N/R', 'Done/Uploaded', 'Assigned'];

        document.addEventListener('DOMContentLoaded', function() {
            videoModal = new bootstrap.Modal(document.getElementById('videoModal'));
            loadVideoData();
            setupSearchHandlers();
            setupButtonHandlers();
            setupStatusDropdownHandlers();
        });

        function setupButtonHandlers() {
            document.getElementById('addVideoBtn').addEventListener('click', () => openModal('add'));
            document.getElementById('exportBtn').addEventListener('click', exportToExcel);
            document.getElementById('importBtn').addEventListener('click', () => document.getElementById('importFile').click());
            document.getElementById('importFile').addEventListener('change', function(e) {
                if (e.target.files[0]) importFromExcel(e.target.files[0]);
            });
            document.getElementById('saveVideoBtn').addEventListener('click', saveVideoFromModal);
        }

        function setupStatusDropdownHandlers() {
            document.getElementById('table-body').addEventListener('change', function(e) {
                if (!e.target.classList.contains('video-status-select')) return;
                const sku   = e.target.getAttribute('data-sku');
                const field = e.target.getAttribute('data-field');
                const item  = tableData.find(d => d.SKU === sku);
                if (!item) return;
                item[field + '_status'] = e.target.value;
                fetch('/videos-for-ads/save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify(buildPayload(sku, item))
                })
                .then(r => r.json())
                .then(d => { if (!d.success) alert(d.message || 'Failed to save status'); })
                .catch(err => alert('Error saving status: ' + err.message));
            });
        }

        function buildPayload(sku, item) {
            const payload = { sku };
            adFields.forEach(f => {
                payload[f.key] = item[f.key] || '';
                if (f.type === 'link') payload[f.key + '_status'] = item[f.key + '_status'] || '';
            });
            return payload;
        }

        function loadVideoData() {
            document.getElementById('rainbow-loader').style.display = 'block';
            fetch('/product-master-data-view')
                .then(r => { if (!r.ok) throw new Error('Network response was not ok'); return r.json(); })
                .then(response => {
                    const data = response.data ? response.data : response;
                    if (data && Array.isArray(data)) {
                        tableData = data;
                        renderTable(tableData);
                        updateCounts();
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

        function renderTable(data) {
            const tbody = document.getElementById('table-body');
            tbody.innerHTML = '';

            const filteredData = data.filter(item => !(item.SKU && item.SKU.toUpperCase().includes('PARENT')));

            if (filteredData.length === 0) {
                tbody.innerHTML = '<tr><td colspan="15" class="text-center">No products found</td></tr>';
                return;
            }

            filteredData.forEach(item => {
                const row = document.createElement('tr');

                // Image
                const imageCell = document.createElement('td');
                imageCell.innerHTML = item.image_path
                    ? '<img src="' + item.image_path + '" style="width:40px;height:40px;object-fit:cover;border-radius:4px;">'
                    : '-';
                row.appendChild(imageCell);

                // Parent
                const parentCell = document.createElement('td');
                parentCell.textContent = escapeHtml(item.Parent) || '-';
                row.appendChild(parentCell);

                // SKU
                const skuCell = document.createElement('td');
                skuCell.textContent = escapeHtml(item.SKU) || '-';
                row.appendChild(skuCell);

                // Shopify Inv
                const invCell = document.createElement('td');
                const invVal = item.shopify_inv;
                invCell.textContent = (invVal !== null && invVal !== undefined && invVal !== '') ? Number(invVal) : '-';
                invCell.style.textAlign = 'right';
                row.appendChild(invCell);

                // Dynamic fields
                adFields.forEach(f => {
                    const cell = document.createElement('td');
                    cell.style.textAlign = 'center';
                    cell.style.verticalAlign = 'middle';

                    if (f.type === 'text') {
                        const val = item[f.key] || '';
                        cell.innerHTML = val
                            ? '<span class="text-cell-value" title="' + escapeHtml(val) + '">' + escapeHtml(val) + '</span>'
                            : '<span class="text-muted">-</span>';
                    } else {
                        // link type: icon + status dropdown
                        const statusVal = item[f.key + '_status'] || '';
                        const linkHtml = item[f.key]
                            ? '<a href="' + escapeHtml(item[f.key]) + '" target="_blank" class="video-link-icon" title="' + escapeHtml(item[f.key]) + '"><i class="fas fa-play-circle"></i></a>'
                            : '<span class="text-muted">-</span>';
                        const optionsHtml = statusValues.map((v, i) =>
                            '<option value="' + escapeHtml(v) + '"' + (v === statusVal ? ' selected' : '') + '>' + escapeHtml(statusLabels[i]) + '</option>'
                        ).join('');
                        cell.innerHTML = '<div class="d-flex flex-column align-items-center gap-1">' +
                            '<div>' + linkHtml + '</div>' +
                            '<select class="form-select form-select-sm video-status-select" style="font-size:11px;min-width:100px;" data-sku="' + escapeHtml(item.SKU) + '" data-field="' + f.key + '">' +
                            optionsHtml + '</select></div>';
                    }

                    row.appendChild(cell);
                });

                // Action
                const actionCell = document.createElement('td');
                actionCell.innerHTML = '<button class="action-btn edit-btn" data-sku="' + escapeHtml(item.SKU) + '"><i class="fas fa-edit"></i> Edit</button>';
                row.appendChild(actionCell);

                tbody.appendChild(row);
            });

            setupEditButtons();
        }

        function setupEditButtons() {
            document.querySelectorAll('.edit-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    openModal('edit', this.getAttribute('data-sku'));
                });
            });
        }

        function openModal(mode, sku = null) {
            const selectSku = document.getElementById('selectSku');
            const editSku   = document.getElementById('editSku');
            document.getElementById('videoForm').reset();

            if (mode === 'add') {
                document.getElementById('modalTitle').textContent = 'Add Videos for Ads';
                selectSku.style.display = 'block';
                selectSku.required = true;
                editSku.value = '';

                if ($(selectSku).hasClass('select2-hidden-accessible')) $(selectSku).select2('destroy');

                selectSku.innerHTML = '<option value="">Choose SKU...</option>';
                tableData.forEach(item => {
                    if (item.SKU && !item.SKU.toUpperCase().includes('PARENT')) {
                        selectSku.innerHTML += '<option value="' + escapeHtml(item.SKU) + '">' + escapeHtml(item.SKU) + '</option>';
                    }
                });
                $(selectSku).select2({
                    theme: 'bootstrap-5',
                    placeholder: 'Choose SKU...',
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $('#videoModal')
                });

            } else if (mode === 'edit' && sku) {
                document.getElementById('modalTitle').textContent = 'Edit Videos for Ads';
                selectSku.style.display = 'none';
                selectSku.required = false;
                editSku.value = sku;

                if ($(selectSku).hasClass('select2-hidden-accessible')) $(selectSku).select2('destroy');

                const item = tableData.find(d => d.SKU === sku);
                if (item) {
                    adFields.forEach(f => {
                        const el = document.getElementById(f.key);
                        if (el) el.value = item[f.key] || '';
                        if (f.type === 'link') {
                            const statusEl = document.getElementById(f.key + '_status');
                            if (statusEl) statusEl.value = item[f.key + '_status'] || '';
                        }
                    });
                }
            }

            document.getElementById('videoModal').addEventListener('hidden.bs.modal', function() {
                if ($(selectSku).hasClass('select2-hidden-accessible')) $(selectSku).select2('destroy');
            }, { once: true });

            videoModal.show();
        }

        function saveVideoFromModal() {
            const selectSku = document.getElementById('selectSku');
            const editSku   = document.getElementById('editSku');
            const sku = editSku.value || ($(selectSku).hasClass('select2-hidden-accessible') ? $(selectSku).val() : selectSku.value);

            if (!sku) { alert('Please select a SKU'); return; }

            const item = tableData.find(d => d.SKU === sku) || {};
            adFields.forEach(f => {
                const el = document.getElementById(f.key);
                if (el) item[f.key] = el.value;
                if (f.type === 'link') {
                    const statusEl = document.getElementById(f.key + '_status');
                    if (statusEl) item[f.key + '_status'] = statusEl.value;
                }
            });

            const saveBtn = document.getElementById('saveVideoBtn');
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            fetch('/videos-for-ads/save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify(buildPayload(sku, item))
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    videoModal.hide();
                    loadVideoData();
                    alert('Videos saved successfully!');
                } else {
                    alert(data.message || 'Failed to save videos');
                }
            })
            .catch(error => alert('Error saving videos: ' + error.message))
            .finally(() => {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fas fa-save"></i> Save';
            });
        }

        function exportToExcel() {
            const exportData = tableData
                .filter(item => item.SKU && !item.SKU.toUpperCase().includes('PARENT'))
                .map(item => {
                    const row = {
                        'Parent': item.Parent || '',
                        'SKU': item.SKU || '',
                        'Shopify Inv': (item.shopify_inv !== null && item.shopify_inv !== undefined && item.shopify_inv !== '') ? Number(item.shopify_inv) : ''
                    };
                    adFields.forEach(f => {
                        row[f.label] = item[f.key] || '';
                        if (f.type === 'link') row[f.label + ' Status'] = item[f.key + '_status'] || '';
                    });
                    return row;
                });

            const ws = XLSX.utils.json_to_sheet(exportData);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Videos for Ads');
            XLSX.writeFile(wb, 'videos_for_ads_' + new Date().toISOString().split('T')[0] + '.xlsx');
        }

        function importFromExcel(file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const data = new Uint8Array(e.target.result);
                    const workbook = XLSX.read(data, { type: 'array' });
                    const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
                    const jsonData = XLSX.utils.sheet_to_json(firstSheet);
                    if (jsonData.length === 0) { alert('No data found in the file'); return; }
                    processImportedData(jsonData);
                } catch (error) {
                    alert('Error reading file: ' + error.message);
                }
            };
            reader.readAsArrayBuffer(file);
            document.getElementById('importFile').value = '';
        }

        function processImportedData(jsonData) {
            let successCount = 0, errorCount = 0;
            const errors = [];

            const savePromises = jsonData.map((row, index) => {
                const sku = row['SKU'] || row['sku'];
                if (!sku) { errorCount++; return Promise.resolve(); }

                const item = {};
                adFields.forEach(f => {
                    item[f.key] = row[f.label] || '';
                    if (f.type === 'link') item[f.key + '_status'] = row[f.label + ' Status'] || '';
                });

                return fetch('/videos-for-ads/save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify(buildPayload(sku, item))
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) { successCount++; }
                    else { errorCount++; if (errors.length < 10) errors.push('Row ' + (index + 2) + ' (' + sku + '): ' + (data.message || 'Unknown error')); }
                })
                .catch(err => { errorCount++; if (errors.length < 10) errors.push('Row ' + (index + 2) + ' (' + sku + '): ' + err.message); });
            });

            Promise.all(savePromises).then(() => {
                let message = 'Import completed!\n\nSuccess: ' + successCount + '\nErrors: ' + errorCount;
                if (errors.length > 0) message += '\n\nFirst errors:\n' + errors.join('\n');
                alert(message);
                if (successCount > 0) loadVideoData();
            });
        }

        function applyFilters() {
            const parentFilter = document.getElementById('parentSearch').value.toLowerCase();
            const skuFilter    = document.getElementById('skuSearch').value.toLowerCase();

            const filterMap = {};
            adFields.forEach(f => {
                filterMap[f.key] = document.getElementById('filter_' + f.key).value;
            });

            const filteredData = tableData.filter(item => {
                if (item.SKU && item.SKU.toUpperCase().includes('PARENT')) return false;
                if (parentFilter && !(item.Parent && item.Parent.toLowerCase().includes(parentFilter))) return false;
                if (skuFilter    && !(item.SKU   && item.SKU.toLowerCase().includes(skuFilter)))    return false;
                for (const [key, val] of Object.entries(filterMap)) {
                    if (val === 'missing' && !isMissing(item[key])) return false;
                }
                return true;
            });

            renderTable(filteredData);
        }

        function setupSearchHandlers() {
            document.getElementById('parentSearch').addEventListener('input', applyFilters);
            document.getElementById('skuSearch').addEventListener('input', applyFilters);
            adFields.forEach(f => {
                document.getElementById('filter_' + f.key).addEventListener('change', applyFilters);
            });
        }

        function isMissing(value) {
            return value === null || value === undefined || value === '' || (typeof value === 'string' && value.trim() === '');
        }

        function updateCounts() {
            const parentSet = new Set();
            let skuCount = 0;
            const missingCounts = {};
            adFields.forEach(f => { missingCounts[f.key] = 0; });

            tableData.forEach(item => {
                if (item.Parent) parentSet.add(item.Parent);
                if (item.SKU && !String(item.SKU).toUpperCase().includes('PARENT')) {
                    skuCount++;
                    adFields.forEach(f => { if (isMissing(item[f.key])) missingCounts[f.key]++; });
                }
            });

            document.getElementById('parentCount').textContent = '(' + parentSet.size + ')';
            document.getElementById('skuCount').textContent    = '(' + skuCount + ')';
            adFields.forEach(f => {
                document.getElementById('missingCount_' + f.key).textContent = '(' + missingCounts[f.key] + ')';
            });
        }

        function escapeHtml(text) {
            if (!text) return '';
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }

        function showError(message) { alert(message); }
        @endverbatim
    </script>
@endsection
