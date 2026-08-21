@php
    $pageTitle = $pageTitle ?? 'Raw Images';
    $pageSubtitle = $pageSubtitle ?? 'Upload original raw image files by SKU';
    $dataUrl = $dataUrl ?? route('raw.images.data');
    $uploadUrl = $uploadUrl ?? route('raw.images.upload');
    $destroyBaseUrl = $destroyBaseUrl ?? url('/raw-images');
@endphp
@extends('layouts.vertical', ['title' => $pageTitle, 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
<link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
<style>
    .tabulator-col .tabulator-col-sorter { display: none !important; }

    .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
        writing-mode: vertical-rl;
        text-orientation: mixed;
        white-space: nowrap;
        transform: rotate(180deg);
        height: 100px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: 600;
    }

    .tabulator .tabulator-header .tabulator-col { height: 100px !important; }
    .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title { padding-right: 0 !important; }
    .tabulator-paginator label { margin-right: 5px; }

    .parent-row { background-color: #fffacd !important; }

    .copy-sku-btn { cursor: pointer; padding: 2px 5px; margin-left: 5px; }

    .ri-cell-plus {
        width: 36px;
        height: 36px;
        border: 2px dashed #22c55e;
        border-radius: 8px;
        color: #22c55e;
        background: #f0fdf4;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 18px;
        font-weight: 700;
        line-height: 1;
        transition: background .15s, border-color .15s, color .15s;
    }
    .ri-cell-plus:hover { background: #dcfce7; border-color: #16a34a; color: #16a34a; }

    .ri-cell-thumb {
        width: 40px;
        height: 40px;
        object-fit: cover;
        border-radius: 4px;
        cursor: pointer;
        border: 1px solid #e2e8f0;
    }

    .ri-cell-count {
        position: absolute;
        top: -6px;
        right: -8px;
        background: #2c6ed5;
        color: #fff;
        border-radius: 10px;
        font-size: 10px;
        font-weight: 700;
        min-width: 16px;
        height: 16px;
        padding: 0 4px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .ri-plus-tile {
        width: 160px;
        height: 160px;
        border: 2px dashed #94a3b8;
        border-radius: 14px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: #64748b;
        background: #f8fafc;
        gap: 8px;
        transition: border-color .15s, color .15s, background .15s;
        user-select: none;
    }
    .ri-plus-tile:hover,
    .ri-plus-tile.drag-over { border-color: #2c6ed5; color: #2c6ed5; background: #eff6ff; }
    .ri-plus-tile .ri-plus-icon { font-size: 56px; font-weight: 300; line-height: 1; }
    .ri-plus-tile.ri-plus-tile-sm { width: 120px; height: 120px; }
    .ri-plus-tile.ri-plus-tile-sm .ri-plus-icon { font-size: 36px; }

    .ri-modal-grid { display: flex; flex-wrap: wrap; gap: 12px; min-height: 80px; }

    .ri-card {
        width: 140px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
        background: #fff;
        position: relative;
        box-shadow: 0 1px 4px rgba(0,0,0,.06);
    }
    .ri-card img { width: 140px; height: 110px; object-fit: cover; display: block; background: #f1f5f9; }
    .ri-card-file {
        width: 140px;
        height: 110px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        background: #f8fafc;
        color: #475569;
        gap: 6px;
    }
    .ri-card-del {
        position: absolute;
        top: 4px;
        right: 4px;
        background: rgba(220,38,38,.9);
        border: none;
        color: #fff;
        border-radius: 50%;
        width: 22px;
        height: 22px;
        font-size: 11px;
        cursor: pointer;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 0;
        line-height: 1;
        z-index: 2;
    }
    .ri-card:hover .ri-card-del { display: flex; }
    .ri-card-name {
        font-size: 10px;
        color: #64748b;
        padding: 4px 6px 6px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .modal-header-gradient {
        background: linear-gradient(135deg, #6B73FF 0%, #000DFF 100%);
        color: #fff;
    }

    #missing-raw-images-badge { cursor: pointer; }
    #missing-raw-images-badge.active-filter { box-shadow: 0 0 0 2px #fff, 0 0 0 4px #dc3545; }

    #rainbow-loader { display: none; text-align: center; padding: 40px; }
</style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('layouts.shared/page-title', [
        'page_title' => $pageTitle,
        'sub_title' => $pageSubtitle,
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h4 class="card-title mb-0">{{ $pageTitle }}</h4>
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <span class="badge bg-danger fs-6 p-2" id="missing-raw-images-badge" title="Click to show SKUs missing a raw image">
                            Missing Raw Images: <span id="missingRawImagesCount">0</span>
                        </span>
                        <span class="badge bg-primary fs-6 p-2">
                            SKUs: <span id="skuCountBadge">0</span>
                        </span>
                    </div>
                </div>

                <div class="card-body" style="padding: 0;">
                    <div id="raw-images-table-wrapper" style="height: calc(100vh - 220px); display: flex; flex-direction: column;">
                        <div class="p-2 bg-light border-bottom">
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <input type="text" id="general-search" class="form-control form-control-sm" placeholder="Search all columns...">
                                </div>
                                <div class="col-md-3">
                                    <input type="text" id="parentSearch" class="form-control form-control-sm" placeholder="Search Parent... (0)">
                                </div>
                                <div class="col-md-3">
                                    <input type="text" id="skuSearch" class="form-control form-control-sm" placeholder="Search SKU... (0)">
                                </div>
                                <div class="col-md-2">
                                    <select id="filterRawImages" class="form-control form-control-sm">
                                        <option value="all">All SKUs</option>
                                        <option value="missing">Missing Only</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div id="raw-images-table" style="flex: 1;"></div>
                    </div>
                </div>

                <div id="rainbow-loader" class="rainbow-loader">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="mt-2 fw-semibold text-primary">Loading {{ $pageTitle }}...</div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="rawImageModal" tabindex="-1" aria-labelledby="rawImageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title" id="rawImageModalLabel">
                        <i class="fas fa-image me-2"></i>{{ $pageTitle }} — <span id="modalSkuLabel"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="modalSku">
                    <div class="mb-2"><strong>SKU:</strong> <span id="modalSkuText"></span></div>
                    <div class="mb-3 text-muted small">Parent: <span id="modalParentText">—</span></div>
                    <div id="rawImageGrid" class="ri-modal-grid"></div>
                    <input type="file" id="rawImageFileInput" class="d-none" accept="image/*,.dng,.cr2,.cr3,.nef,.arw,.raf,.orf,.rw2,.tif,.tiff,.heic" multiple>
                    <div class="small text-muted mt-3">
                        JPG, PNG, WEBP, or camera RAW files. Max 50 MB each.
                    </div>
                    <div class="small text-success fw-semibold mt-1" id="rawUploadMsg" style="display:none;"></div>
                    <div class="small text-danger fw-semibold mt-1" id="rawUploadErr" style="display:none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const rawImagesDataUrl = @json($dataUrl);
        const rawImagesUploadUrl = @json($uploadUrl);
        const rawImagesDestroyBaseUrl = @json($destroyBaseUrl);
        const rawImagesPageTitle = @json($pageTitle);
        let tableData = [];
        let table;
        let rawImageModal;
        let missingFilterOn = false;

        document.addEventListener('DOMContentLoaded', function () {
            rawImageModal = new bootstrap.Modal(document.getElementById('rawImageModal'));
            initializeTabulator();
            setupSearchHandlers();
            setupModalHandlers();
            setupTableEvents();
        });

        function initializeTabulator() {
            document.getElementById('rainbow-loader').style.display = 'block';

            table = new Tabulator('#raw-images-table', {
                ajaxURL: rawImagesDataUrl,
                ajaxSorting: false,
                ajaxResponse: function (url, params, response) {
                    if (response && Array.isArray(response.data)) {
                        tableData = response.data;
                        updateCounts();
                        hideLoader();
                        return response.data;
                    }
                    hideLoader();
                    return [];
                },
                ajaxError: function () {
                    hideLoader();
                    alert('Failed to load ' + rawImagesPageTitle + ' data.');
                },
                layout: 'fitData',
                pagination: true,
                paginationSize: 100,
                paginationSizeSelector: [25, 50, 100, 200, 500],
                paginationCounter: 'rows',
                rowFormatter: function (row) {
                    const data = row.getData();
                    if (data.SKU && String(data.SKU).toUpperCase().includes('PARENT')) {
                        row.getElement().classList.add('parent-row');
                    }
                },
                langs: {
                    default: {
                        pagination: {
                            page_size: 'Show',
                            counter: { showing: 'Showing', of: 'of', rows: 'rows' }
                        }
                    }
                },
                columns: [
                    {
                        title: "<input type='checkbox' id='ri-select-all' title='Select all'>",
                        field: 'row_select',
                        width: 44,
                        hozAlign: 'center',
                        headerSort: false,
                        frozen: true,
                        formatter: function (cell) {
                            const sku = cell.getData().SKU || '';
                            return "<input type='checkbox' class='ri-row-select' data-sku='" + escapeHtml(sku) + "'>";
                        }
                    },
                    {
                        title: 'Images',
                        field: 'image_path',
                        width: 80,
                        frozen: true,
                        hozAlign: 'center',
                        formatter: function (cell) {
                            const value = cell.getValue();
                            if (!value) return '-';
                            return '<img src="' + escapeHtml(value) + '" class="ri-cell-thumb" alt="Product">';
                        }
                    },
                    {
                        title: 'Parent',
                        field: 'Parent',
                        width: 150,
                        frozen: true
                    },
                    {
                        title: 'SKU',
                        field: 'SKU',
                        width: 200,
                        frozen: true,
                        formatter: function (cell) {
                            const sku = cell.getValue();
                            if (!sku) return '-';
                            return '<div style="display:flex;align-items:center;gap:5px;">'
                                + '<span>' + escapeHtml(sku) + '</span>'
                                + '<button type="button" class="btn btn-sm btn-link p-0 copy-sku-btn" data-sku="' + escapeHtml(sku) + '" title="Copy SKU">'
                                + '<i class="fas fa-copy"></i></button></div>';
                        }
                    },
                    {
                        title: 'Inv',
                        field: 'shopify_inv',
                        width: 80,
                        hozAlign: 'center',
                        sorter: 'number',
                        formatter: function (cell) {
                            const value = cell.getValue();
                            if (value === 0 || value === '0') return '0';
                            if (value === null || value === undefined || value === '') return '-';
                            return String(value);
                        }
                    },
                    {
                        title: 'Ov L30',
                        field: 'ovl30',
                        width: 80,
                        hozAlign: 'center',
                        sorter: 'number',
                        formatter: function (cell) {
                            const value = cell.getValue();
                            return (value === null || value === undefined || value === '') ? '0' : String(value);
                        }
                    },
                    {
                        title: 'Dil',
                        field: 'dil',
                        width: 50,
                        hozAlign: 'center',
                        sorter: 'number',
                        formatter: function (cell) {
                            const value = cell.getValue();
                            let dilText = '0%';
                            let dilColor = '#a00211';
                            if (value !== null && value !== undefined && value !== '') {
                                const dilNum = parseFloat(value);
                                dilText = Math.round(dilNum) + '%';
                                if (dilNum < 16.7) dilColor = '#a00211';
                                else if (dilNum >= 16.7 && dilNum < 25) dilColor = '#ffc107';
                                else if (dilNum >= 25 && dilNum < 50) dilColor = '#28a745';
                                else if (dilNum >= 50) dilColor = '#e83e8c';
                            }
                            return '<span style="color:' + dilColor + ';font-weight:bold;">' + dilText + '</span>';
                        }
                    },
                    {
                        title: 'Raw Images',
                        field: 'has_raw_image',
                        width: 90,
                        hozAlign: 'center',
                        headerSort: false,
                        formatter: function (cell) {
                            const row = cell.getData();
                            const sku = row.SKU || '';
                            const images = Array.isArray(row.raw_images) ? row.raw_images : [];
                            if (!images.length) {
                                return '<button type="button" class="ri-cell-plus js-open-raw-modal" data-sku="' + escapeHtml(sku) + '" title="Add raw image">+</button>';
                            }
                            const first = images[0];
                            const count = images.length;
                            let inner;
                            if (first.previewable && first.url) {
                                inner = '<img src="' + escapeHtml(first.url) + '" class="ri-cell-thumb" alt="Raw">';
                            } else {
                                inner = '<i class="fas fa-file-image" style="font-size:22px;color:#2c6ed5;"></i>';
                            }
                            return '<button type="button" class="btn btn-link p-0 js-open-raw-modal" data-sku="' + escapeHtml(sku) + '" title="View / add raw images" style="position:relative;display:inline-flex;">'
                                + inner
                                + (count > 1 ? '<span class="ri-cell-count">' + count + '</span>' : '')
                                + '</button>';
                        }
                    }
                ]
            });
        }

        function setupTableEvents() {
            const wrap = document.getElementById('raw-images-table');

            wrap.addEventListener('change', function (e) {
                if (e.target.id === 'ri-select-all') {
                    wrap.querySelectorAll('.ri-row-select').forEach(function (cb) {
                        cb.checked = e.target.checked;
                    });
                }
            });

            wrap.addEventListener('click', function (e) {
                const copyBtn = e.target.closest('.copy-sku-btn');
                if (copyBtn) {
                    e.preventDefault();
                    copyToClipboard(copyBtn.getAttribute('data-sku') || '');
                    return;
                }
                const openBtn = e.target.closest('.js-open-raw-modal');
                if (openBtn) {
                    e.preventDefault();
                    openRawImageModal(openBtn.getAttribute('data-sku'));
                }
            });
        }

        function setupSearchHandlers() {
            document.getElementById('general-search').addEventListener('input', applyFilters);
            document.getElementById('parentSearch').addEventListener('input', applyFilters);
            document.getElementById('skuSearch').addEventListener('input', applyFilters);
            document.getElementById('filterRawImages').addEventListener('change', function () {
                missingFilterOn = this.value === 'missing';
                syncMissingBadge();
                applyFilters();
            });
            document.getElementById('missing-raw-images-badge').addEventListener('click', function () {
                const select = document.getElementById('filterRawImages');
                select.value = select.value === 'missing' ? 'all' : 'missing';
                missingFilterOn = select.value === 'missing';
                syncMissingBadge();
                applyFilters();
            });
        }

        function setupModalHandlers() {
            const fileInput = document.getElementById('rawImageFileInput');
            fileInput.addEventListener('change', function () {
                if (fileInput.files && fileInput.files.length) {
                    uploadRawFiles(fileInput.files);
                    fileInput.value = '';
                }
            });

            document.getElementById('rawImageGrid').addEventListener('click', function (e) {
                const plus = e.target.closest('.ri-plus-tile');
                if (plus) {
                    fileInput.click();
                    return;
                }
                const del = e.target.closest('.ri-card-del');
                if (del) {
                    deleteRawImage(del.getAttribute('data-id'));
                }
            });

            const grid = document.getElementById('rawImageGrid');
            grid.addEventListener('dragover', function (e) {
                e.preventDefault();
                const plus = grid.querySelector('.ri-plus-tile');
                if (plus) plus.classList.add('drag-over');
            });
            grid.addEventListener('dragleave', function () {
                const plus = grid.querySelector('.ri-plus-tile');
                if (plus) plus.classList.remove('drag-over');
            });
            grid.addEventListener('drop', function (e) {
                e.preventDefault();
                const plus = grid.querySelector('.ri-plus-tile');
                if (plus) plus.classList.remove('drag-over');
                if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
                    uploadRawFiles(e.dataTransfer.files);
                }
            });
        }

        function openRawImageModal(sku) {
            const item = tableData.find(function (d) { return d.SKU === sku; });
            document.getElementById('modalSku').value = sku || '';
            document.getElementById('modalSkuLabel').textContent = sku || '';
            document.getElementById('modalSkuText').textContent = sku || '';
            document.getElementById('modalParentText').textContent = (item && item.Parent) ? item.Parent : '—';
            renderModalGrid(item ? (item.raw_images || []) : []);
            setUploadMsg('');
            setUploadErr('');
            rawImageModal.show();
        }

        function renderModalGrid(images) {
            const grid = document.getElementById('rawImageGrid');
            const list = Array.isArray(images) ? images : [];
            let html = '';

            list.forEach(function (img) {
                html += '<div class="ri-card">';
                html += '<button type="button" class="ri-card-del" data-id="' + escapeHtml(String(img.id)) + '" title="Remove"><i class="fas fa-times"></i></button>';
                if (img.previewable && img.url) {
                    html += '<a href="' + escapeHtml(img.url) + '" target="_blank"><img src="' + escapeHtml(img.url) + '" alt=""></a>';
                } else {
                    html += '<div class="ri-card-file"><i class="fas fa-file-image fa-2x"></i><span class="small">File</span></div>';
                }
                html += '<div class="ri-card-name" title="' + escapeHtml(img.name || '') + '">' + escapeHtml(img.name || 'image') + '</div>';
                html += '</div>';
            });

            const plusClass = list.length ? 'ri-plus-tile ri-plus-tile-sm' : 'ri-plus-tile';
            const plusLabel = list.length ? 'Add more' : 'Add raw image';
            html += '<div class="' + plusClass + '" title="' + plusLabel + '">'
                + '<span class="ri-plus-icon">+</span>'
                + '<span class="small fw-semibold">' + plusLabel + '</span>'
                + '</div>';

            grid.innerHTML = html;
        }

        function uploadRawFiles(fileList) {
            const sku = document.getElementById('modalSku').value;
            if (!sku) {
                setUploadErr('SKU is missing.');
                return;
            }

            const form = new FormData();
            form.append('sku', sku);
            Array.from(fileList).forEach(function (file) {
                form.append('files[]', file);
            });

            setUploadMsg('Uploading…');
            setUploadErr('');

            fetch(rawImagesUploadUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: form
            })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (result) {
                if (!result.data.success) {
                    setUploadMsg('');
                    setUploadErr(result.data.message || 'Upload failed.');
                    return;
                }
                setUploadErr('');
                setUploadMsg(result.data.message || 'Uploaded.');
                applyImagesToSku(sku, result.data.images || []);
            })
            .catch(function (err) {
                setUploadMsg('');
                setUploadErr('Upload failed: ' + err.message);
            });
        }

        function deleteRawImage(id) {
            if (!id || !confirm('Remove this raw image?')) return;

            const sku = document.getElementById('modalSku').value;
            fetch(rawImagesDestroyBaseUrl + '/' + encodeURIComponent(id), {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.success) {
                    setUploadErr(data.message || 'Failed to remove image.');
                    return;
                }
                setUploadErr('');
                setUploadMsg(data.message || 'Removed.');
                applyImagesToSku(sku, data.images || []);
            })
            .catch(function (err) {
                setUploadErr('Delete failed: ' + err.message);
            });
        }

        function applyImagesToSku(sku, images) {
            const item = tableData.find(function (d) { return d.SKU === sku; });
            if (item) {
                item.raw_images = images;
                item.raw_image_count = images.length;
                item.has_raw_image = images.length > 0;
                item.raw_image_url = images.length ? images[0].url : null;
            }
            if (table) {
                const rows = table.searchRows('SKU', '=', sku);
                rows.forEach(function (row) {
                    row.update({
                        raw_images: images,
                        raw_image_count: images.length,
                        has_raw_image: images.length > 0,
                        raw_image_url: images.length ? images[0].url : null
                    });
                });
            }
            renderModalGrid(images);
            updateCounts();
        }

        function applyFilters() {
            if (!table) return;

            const generalFilter = document.getElementById('general-search').value.toLowerCase();
            const parentFilter = document.getElementById('parentSearch').value.toLowerCase();
            const skuFilter = document.getElementById('skuSearch').value.toLowerCase();
            const filterMode = document.getElementById('filterRawImages').value;
            const filters = [];

            if (generalFilter) {
                filters.push(function (data) {
                    return (data.Parent && String(data.Parent).toLowerCase().includes(generalFilter))
                        || (data.SKU && String(data.SKU).toLowerCase().includes(generalFilter));
                });
            }
            if (parentFilter) {
                filters.push({ field: 'Parent', type: 'like', value: parentFilter });
            }
            if (skuFilter) {
                filters.push({ field: 'SKU', type: 'like', value: skuFilter });
            }
            if (filterMode === 'missing') {
                filters.push(function (data) {
                    if (data.SKU && String(data.SKU).toUpperCase().includes('PARENT')) return false;
                    return !data.has_raw_image;
                });
            }

            table.clearFilter();
            if (filters.length) table.setFilter(filters);
        }

        function updateCounts() {
            const parentSet = new Set();
            let skuCount = 0;
            let missing = 0;

            tableData.forEach(function (item) {
                if (item.Parent) parentSet.add(item.Parent);
                if (item.SKU && !String(item.SKU).toUpperCase().includes('PARENT')) {
                    skuCount++;
                    if (!item.has_raw_image) missing++;
                }
            });

            document.getElementById('parentSearch').placeholder = 'Search Parent... (' + parentSet.size + ')';
            document.getElementById('skuSearch').placeholder = 'Search SKU... (' + skuCount + ')';
            document.getElementById('skuCountBadge').textContent = skuCount;
            document.getElementById('missingRawImagesCount').textContent = missing;
            syncMissingBadge();
        }

        function syncMissingBadge() {
            document.getElementById('missing-raw-images-badge').classList.toggle('active-filter', missingFilterOn);
        }

        function hideLoader() {
            const loader = document.getElementById('rainbow-loader');
            if (loader) loader.style.display = 'none';
        }

        function copyToClipboard(text) {
            if (!text) return;
            navigator.clipboard.writeText(text).catch(function () {});
        }

        function setUploadMsg(text) {
            const el = document.getElementById('rawUploadMsg');
            el.textContent = text;
            el.style.display = text ? 'block' : 'none';
        }

        function setUploadErr(text) {
            const el = document.getElementById('rawUploadErr');
            el.textContent = text;
            el.style.display = text ? 'block' : 'none';
        }

        function escapeHtml(text) {
            if (!text) return '';
            return String(text).replace(/[&<>"']/g, function (m) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
            });
        }
    </script>
@endsection
