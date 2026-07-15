@extends('layouts.vertical', ['title' => 'QC Masters', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Match /faire-pricing Tabulator format */
        .tabulator { border: 1px solid #dee2e6; border-radius: 8px; font-size: 12px; }
        .tabulator .tabulator-header { background: #f8f9fa; border-bottom: 1px solid #dee2e6; }
        .tabulator-col .tabulator-col-sorter { display: none !important; }
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl; text-orientation: mixed; transform: rotate(180deg);
            white-space: nowrap; height: 78px; display: flex; align-items: center;
            justify-content: center; font-size: 11px; font-weight: 600;
        }
        .tabulator .tabulator-header .tabulator-col { height: 80px !important; }
        .tabulator .tabulator-row { min-height: 50px; }
        .tabulator-row.qc-parent-row,
        .tabulator-row.qc-parent-row .tabulator-cell {
            background-color: #d1e7dd !important;
            font-weight: 700 !important;
            min-height: 48px !important;
            color: #0f5132;
        }
        .tabulator .tabulator-footer {
            background: #f8fafc !important; border-top: 1px solid #e2e8f0 !important;
            padding: 10px 16px !important;
        }

        .status-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            vertical-align: middle;
            border: 1px solid rgba(0,0,0,.12);
        }
        .row-cb,
        #qc-masters-select-all {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        .qc-search-icon {
            font-size: 14px;
            cursor: pointer;
        }
        .qc-issue-thumb {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #dee2e6;
            cursor: pointer;
        }
        #qcIssueImagePreview img {
            max-width: 100%;
            max-height: 220px;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }
        #qcIssueVideoPreview video {
            max-width: 100%;
            max-height: 220px;
            border-radius: 6px;
            border: 1px solid #dee2e6;
            background: #000;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'QC Masters',
        'sub_title' => 'Product Masters',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <input type="text" id="qc-masters-parent-search" class="form-control form-control-sm"
                            style="max-width:200px;" placeholder="Search parent..." title="Filter by Parent column">
                        <input type="text" id="qc-masters-sku-search" class="form-control form-control-sm"
                            style="max-width:220px;" placeholder="Search SKU...">

                        <div class="btn-group align-items-center ms-2" role="group" aria-label="Parent navigation">
                            <button type="button" id="qc-play-backward" class="btn btn-sm btn-light rounded-circle shadow-sm" title="Previous parent" disabled>
                                <i class="fas fa-step-backward"></i>
                            </button>
                            <button type="button" id="qc-play-auto" class="btn btn-sm btn-primary rounded-circle shadow-sm" title="Start parent navigation">
                                <i class="fas fa-play"></i>
                            </button>
                            <button type="button" id="qc-play-pause" class="btn btn-sm btn-warning rounded-circle shadow-sm" style="display: none;" title="Stop navigation and show all">
                                <i class="fas fa-pause"></i>
                            </button>
                            <button type="button" id="qc-play-forward" class="btn btn-sm btn-light rounded-circle shadow-sm" title="Next parent" disabled>
                                <i class="fas fa-step-forward"></i>
                            </button>
                        </div>
                    </div>
                    <div id="qcMastersTable"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Text edit modal (Problem / Suggestion) --}}
    <div class="modal fade" id="qcTextModal" tabindex="-1" aria-labelledby="qcTextModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="qcTextModalLabel">Edit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="qcTextProductId">
                    <input type="hidden" id="qcTextSku">
                    <input type="hidden" id="qcTextField">
                    <div class="mb-2 small text-muted">SKU: <strong id="qcTextSkuLabel"></strong></div>
                    <textarea id="qcTextValue" class="form-control" rows="5" maxlength="5000" placeholder="Enter text…"></textarea>
                    <div class="form-text text-end"><span id="qcTextCount">0</span> / 5000</div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" id="qcTextSaveBtn">
                        <i class="fas fa-save me-1"></i> Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Image modal (upload + snippet) --}}
    <div class="modal fade" id="qcImageModal" tabindex="-1" aria-labelledby="qcImageModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="qcImageModalLabel">
                        <i class="fas fa-image me-1"></i> Issue Image
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="qcImageProductId">
                    <input type="hidden" id="qcImageSku">
                    <div class="mb-2 small text-muted">SKU: <strong id="qcImageSkuLabel"></strong></div>

                    <div class="mb-3">
                        <label for="qcImageMaxKb" class="form-label fw-semibold mb-1">Max size (KB)</label>
                        <input type="number" id="qcImageMaxKb" class="form-control form-control-sm" value="500" min="1" max="2048" style="max-width:140px;">
                        <div class="form-text">Default 500 KB. Hard limit 2048 KB.</div>
                    </div>

                    <div class="mb-3">
                        <label for="qcImageFile" class="form-label fw-semibold mb-1">Upload image</label>
                        <input type="file" id="qcImageFile" class="form-control form-control-sm" accept="image/jpeg,image/png,image/gif,image/webp">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold mb-1">
                            <i class="fas fa-camera text-primary me-1"></i> Snippet
                        </label>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="qcPasteSnippetBtn"
                                title="Paste image from clipboard (Ctrl/Cmd+V also works in this dialog)">
                                <i class="fas fa-paste me-1"></i> Paste from Clipboard
                            </button>
                            <small class="text-muted">Screenshot then paste here, or press <kbd>Ctrl/Cmd</kbd>+<kbd>V</kbd>.</small>
                            <span id="qcPasteStatus" class="badge bg-success" style="display:none;"></span>
                        </div>
                    </div>

                    <div id="qcIssueImagePreview" class="mb-2"></div>
                    <div id="qcImageMeta" class="small text-muted"></div>
                    <div id="qcImageError" class="text-danger small mt-2" style="display:none;"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-danger me-auto" id="qcImageDeleteBtn" style="display:none;">
                        <i class="fas fa-trash me-1"></i> Remove
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-primary" id="qcImageUploadBtn">
                        <i class="fas fa-upload me-1"></i> Upload
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Video modal --}}
    <div class="modal fade" id="qcVideoModal" tabindex="-1" aria-labelledby="qcVideoModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="qcVideoModalLabel">
                        <i class="fas fa-video me-1"></i> Issue Video
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="qcVideoProductId">
                    <input type="hidden" id="qcVideoSku">
                    <div class="mb-2 small text-muted">SKU: <strong id="qcVideoSkuLabel"></strong></div>

                    <div class="mb-3">
                        <label for="qcVideoMaxKb" class="form-label fw-semibold mb-1">Max size (KB)</label>
                        <input type="number" id="qcVideoMaxKb" class="form-control form-control-sm" value="5120" min="1" max="15360" style="max-width:140px;">
                        <div class="form-text">Default 5120 KB (5 MB). Hard limit 15360 KB (15 MB) to keep the page fast.</div>
                    </div>

                    <div class="mb-3">
                        <label for="qcVideoFile" class="form-label fw-semibold mb-1">Upload video</label>
                        <input type="file" id="qcVideoFile" class="form-control form-control-sm" accept="video/mp4,video/webm,video/quicktime,video/x-msvideo,.mp4,.webm,.mov,.avi,.mkv">
                        <div class="form-text">Accepted: mp4, webm, mov, avi, mkv</div>
                    </div>

                    <div id="qcIssueVideoPreview" class="mb-2"></div>
                    <div id="qcVideoMeta" class="small text-muted"></div>
                    <div id="qcVideoError" class="text-danger small mt-2" style="display:none;"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-danger me-auto" id="qcVideoDeleteBtn" style="display:none;">
                        <i class="fas fa-trash me-1"></i> Remove
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-primary" id="qcVideoUploadBtn">
                        <i class="fas fa-upload me-1"></i> Upload
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- User History modal --}}
    <div class="modal fade" id="qcHistoryModal" tabindex="-1" aria-labelledby="qcHistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="qcHistoryModalLabel">
                        <i class="fas fa-clock-rotate-left me-1"></i> User History — <span id="qcHistorySkuLabel"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="qcHistoryEmpty" class="p-3 text-muted small" style="display:none;">No history yet.</div>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0" id="qcHistoryTable" style="display:none;">
                            <thead>
                                <tr>
                                    <th style="width:90px;">Date</th>
                                    <th>User</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="qcHistoryTbody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const dataUrl = @json(route('dim.wt.master.data'));
            const updateUrl = @json(route('qc.masters.update'));
            const uploadUrl = @json(route('qc.masters.upload.image'));
            const deleteImageUrl = @json(route('qc.masters.delete.image'));
            const uploadVideoUrl = @json(route('qc.masters.upload.video'));
            const deleteVideoUrl = @json(route('qc.masters.delete.video'));
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            let pendingSnippetFile = null;
            let qcTextModal = null;
            let qcImageModal = null;
            let qcVideoModal = null;
            let qcHistoryModal = null;

            function esc(str) {
                return String(str ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function isParentSku(sku) {
                return sku && String(sku).toUpperCase().includes('PARENT');
            }

            function statusDotHtml(status) {
                const raw = String(status || '').trim();
                const s = raw.toLowerCase();
                const upper = raw.toUpperCase();
                let color = '#6c757d';
                if (s === 'active') color = '#28a745';
                else if (s === 'inactive') color = '#dc3545';
                else if (upper === 'DC') color = '#dc3545';
                else if (s === 'upcoming') color = '#ffc107';
                else if (upper === '2BDC') color = '#0d6efd';
                const title = raw || '-';
                return '<span class="status-dot" style="background-color:' + color + '" title="' + esc(title) + '"></span>';
            }

            function parentKeyFromRow(data) {
                const parent = String(data.Parent || '').trim();
                if (parent && !isParentSku(parent)) return parent;
                return String(data.SKU || '').replace(/^PARENT\s+/i, '').trim();
            }

            /**
             * Same as /faire-pricing: group children by Parent, then append a PARENT summary row
             * after each group (reuse DB PARENT row when present, otherwise synthesize).
             * Do not rely on Tabulator sort — it scatters "PARENT xxx" SKUs alphabetically.
             */
            function buildRowsWithParentSummaries(rows) {
                const childrenByParent = {};
                const parentRowByKey = {};
                const orphanParents = [];

                rows.forEach(function (r) {
                    if (isParentSku(r.SKU)) {
                        const key = parentKeyFromRow(r);
                        if (key) {
                            parentRowByKey[key] = r;
                        } else {
                            orphanParents.push(r);
                        }
                        return;
                    }
                    const key = String(r.Parent || '').trim() || '(no parent)';
                    if (!childrenByParent[key]) childrenByParent[key] = [];
                    childrenByParent[key].push(r);
                });

                const keys = Object.keys(childrenByParent).sort(function (a, b) {
                    return String(a).localeCompare(String(b), undefined, { sensitivity: 'base' });
                });

                // Also include PARENT-only keys that have no children
                Object.keys(parentRowByKey).forEach(function (k) {
                    if (!childrenByParent[k]) keys.push(k);
                });
                keys.sort(function (a, b) {
                    return String(a).localeCompare(String(b), undefined, { sensitivity: 'base' });
                });
                // unique
                const seen = {};
                const orderedKeys = [];
                keys.forEach(function (k) {
                    if (seen[k]) return;
                    seen[k] = true;
                    orderedKeys.push(k);
                });

                const result = [];
                orderedKeys.forEach(function (key) {
                    const children = childrenByParent[key] || [];
                    children.sort(function (a, b) {
                        return String(a.SKU || '').localeCompare(String(b.SKU || ''), undefined, { sensitivity: 'base' });
                    });

                    let sumInv = 0;
                    children.forEach(function (c) {
                        c.is_parent = false;
                        sumInv += parseFloat(c.shopify_inv) || 0;
                        result.push(c);
                    });

                    if (key === '(no parent)') return;

                    let parentRow = parentRowByKey[key];
                    if (parentRow) {
                        parentRow = Object.assign({}, parentRow);
                    } else {
                        parentRow = {
                            id: 'qc-parent-' + key,
                            Parent: key,
                            SKU: 'PARENT ' + key,
                            image_path: null,
                            status: null,
                            qc_problem_issue: '',
                            qc_suggestion_improve: '',
                            qc_issue_image: null,
                            qc_issue_image_kb: null,
                            qc_issue_video: null,
                            qc_issue_video_kb: null,
                            qc_user_history: [],
                            qc_user_history_label: '',
                        };
                    }
                    parentRow.is_parent = true;
                    parentRow.shopify_inv = Math.round(sumInv);
                    parentRow.Parent = key;
                    if (!parentRow.SKU || !isParentSku(parentRow.SKU)) {
                        parentRow.SKU = 'PARENT ' + key;
                    }
                    result.push(parentRow);
                    delete parentRowByKey[key];
                });

                orphanParents.forEach(function (r) {
                    r.is_parent = true;
                    result.push(r);
                });

                return result;
            }

            function textCellHtml(val) {
                const s = String(val || '').trim();
                const hasData = s !== '';
                const color = hasData ? '#28a745' : '#dc3545';
                const title = hasData ? s : 'No data — click to add';
                return '<i class="fas fa-search qc-search-icon" style="color:' + color + ';" title="' + esc(title) + '"></i>';
            }

            function updateRowFields(productId, fields) {
                const rows = table.searchRows('id', '=', productId);
                if (!rows.length) return;
                rows[0].update(fields);
            }

            function applyHistoryToRow(productId, json) {
                if (!json) return;
                const patch = {};
                if (Array.isArray(json.user_history)) patch.qc_user_history = json.user_history;
                if (typeof json.user_history_label === 'string') patch.qc_user_history_label = json.user_history_label;
                if (Object.keys(patch).length) updateRowFields(productId, patch);
            }

            function openHistoryModal(rowData) {
                document.getElementById('qcHistorySkuLabel').textContent = rowData.SKU || '';
                const history = Array.isArray(rowData.qc_user_history) ? rowData.qc_user_history.slice().reverse() : [];
                const emptyEl = document.getElementById('qcHistoryEmpty');
                const tableEl = document.getElementById('qcHistoryTable');
                const tbody = document.getElementById('qcHistoryTbody');
                tbody.innerHTML = '';
                if (!history.length) {
                    emptyEl.style.display = '';
                    tableEl.style.display = 'none';
                } else {
                    emptyEl.style.display = 'none';
                    tableEl.style.display = '';
                    history.forEach(function (h) {
                        const tr = document.createElement('tr');
                        tr.innerHTML =
                            '<td><span class="badge bg-light text-dark border">' + esc(h.date || '') + '</span></td>' +
                            '<td>' + esc(h.user || '') + '</td>' +
                            '<td class="small">' + esc(h.action || '') + '</td>';
                        tbody.appendChild(tr);
                    });
                }
                if (!qcHistoryModal) qcHistoryModal = new bootstrap.Modal(document.getElementById('qcHistoryModal'));
                qcHistoryModal.show();
            }

            async function postJson(url, body) {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(body),
                });
                return res.json();
            }

            function openTextModal(rowData, field, title) {
                document.getElementById('qcTextProductId').value = rowData.id;
                document.getElementById('qcTextSku').value = rowData.SKU || '';
                document.getElementById('qcTextField').value = field;
                document.getElementById('qcTextSkuLabel').textContent = rowData.SKU || '';
                document.getElementById('qcTextModalLabel').textContent = title;
                const val = field === 'problem_issue'
                    ? (rowData.qc_problem_issue || '')
                    : (rowData.qc_suggestion_improve || '');
                const ta = document.getElementById('qcTextValue');
                ta.value = val;
                document.getElementById('qcTextCount').textContent = String(val.length);
                if (!qcTextModal) qcTextModal = new bootstrap.Modal(document.getElementById('qcTextModal'));
                qcTextModal.show();
                setTimeout(function () { ta.focus(); }, 200);
            }

            function renderImagePreview(src, kb) {
                const box = document.getElementById('qcIssueImagePreview');
                const meta = document.getElementById('qcImageMeta');
                const delBtn = document.getElementById('qcImageDeleteBtn');
                if (src) {
                    box.innerHTML = '<img src="' + esc(src) + '" alt="Issue image">';
                    meta.textContent = kb != null ? ('Current size: ' + kb + ' KB') : '';
                    delBtn.style.display = '';
                } else {
                    box.innerHTML = '<span class="text-muted small">No image yet.</span>';
                    meta.textContent = '';
                    delBtn.style.display = 'none';
                }
            }

            function openImageModal(rowData) {
                pendingSnippetFile = null;
                document.getElementById('qcImageFile').value = '';
                document.getElementById('qcImageError').style.display = 'none';
                document.getElementById('qcPasteStatus').style.display = 'none';
                document.getElementById('qcImageProductId').value = rowData.id;
                document.getElementById('qcImageSku').value = rowData.SKU || '';
                document.getElementById('qcImageSkuLabel').textContent = rowData.SKU || '';
                renderImagePreview(rowData.qc_issue_image, rowData.qc_issue_image_kb);
                if (!qcImageModal) qcImageModal = new bootstrap.Modal(document.getElementById('qcImageModal'));
                qcImageModal.show();
            }

            function getMaxKb() {
                let maxKb = parseInt(document.getElementById('qcImageMaxKb').value, 10);
                if (!maxKb || maxKb < 1) maxKb = 500;
                if (maxKb > 2048) maxKb = 2048;
                return maxKb;
            }

            function validateFileSize(file, maxKb) {
                const sizeKb = Math.ceil(file.size / 1024);
                if (file.size > maxKb * 1024) {
                    return 'Image is ' + sizeKb + ' KB. Limit is ' + maxKb + ' KB.';
                }
                return null;
            }

            function showImageError(msg) {
                const el = document.getElementById('qcImageError');
                el.textContent = msg || '';
                el.style.display = msg ? '' : 'none';
            }

            function formatKbLabel(kb) {
                if (kb == null) return '';
                if (kb >= 1024) return (kb / 1024).toFixed(1) + ' MB';
                return kb + ' KB';
            }

            function renderVideoPreview(src, kb) {
                const box = document.getElementById('qcIssueVideoPreview');
                const meta = document.getElementById('qcVideoMeta');
                const delBtn = document.getElementById('qcVideoDeleteBtn');
                if (src) {
                    box.innerHTML = '<video src="' + esc(src) + '" controls preload="metadata"></video>';
                    meta.textContent = kb != null ? ('Current size: ' + formatKbLabel(kb)) : '';
                    delBtn.style.display = '';
                } else {
                    box.innerHTML = '<span class="text-muted small">No video yet.</span>';
                    meta.textContent = '';
                    delBtn.style.display = 'none';
                }
            }

            function openVideoModal(rowData) {
                document.getElementById('qcVideoFile').value = '';
                document.getElementById('qcVideoError').style.display = 'none';
                document.getElementById('qcVideoProductId').value = rowData.id;
                document.getElementById('qcVideoSku').value = rowData.SKU || '';
                document.getElementById('qcVideoSkuLabel').textContent = rowData.SKU || '';
                renderVideoPreview(rowData.qc_issue_video, rowData.qc_issue_video_kb);
                if (!qcVideoModal) qcVideoModal = new bootstrap.Modal(document.getElementById('qcVideoModal'));
                qcVideoModal.show();
            }

            function getVideoMaxKb() {
                let maxKb = parseInt(document.getElementById('qcVideoMaxKb').value, 10);
                if (!maxKb || maxKb < 1) maxKb = 5120;
                if (maxKb > 15360) maxKb = 15360;
                return maxKb;
            }

            function validateVideoFileSize(file, maxKb) {
                const sizeKb = Math.ceil(file.size / 1024);
                if (file.size > maxKb * 1024) {
                    return 'Video is ' + formatKbLabel(sizeKb) + '. Limit is ' + formatKbLabel(maxKb) + '.';
                }
                return null;
            }

            function showVideoError(msg) {
                const el = document.getElementById('qcVideoError');
                el.textContent = msg || '';
                el.style.display = msg ? '' : 'none';
            }

            async function uploadVideoFile(file) {
                const maxKb = getVideoMaxKb();
                const err = validateVideoFileSize(file, maxKb);
                if (err) {
                    showVideoError(err);
                    return;
                }
                showVideoError('');
                const btn = document.getElementById('qcVideoUploadBtn');
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Uploading…';
                try {
                    const fd = new FormData();
                    fd.append('product_id', document.getElementById('qcVideoProductId').value);
                    fd.append('sku', document.getElementById('qcVideoSku').value);
                    fd.append('max_kb', String(maxKb));
                    fd.append('video', file);
                    const res = await fetch(uploadVideoUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: fd,
                    });
                    const json = await res.json();
                    if (!json.success) {
                        showVideoError(json.message || 'Upload failed.');
                        return;
                    }
                    updateRowFields(parseInt(document.getElementById('qcVideoProductId').value, 10), {
                        qc_issue_video: json.video_path,
                        qc_issue_video_kb: json.video_size_kb,
                    });
                    applyHistoryToRow(parseInt(document.getElementById('qcVideoProductId').value, 10), json);
                    renderVideoPreview(json.video_path, json.video_size_kb);
                    document.getElementById('qcVideoFile').value = '';
                } catch (e) {
                    showVideoError(e.message || 'Upload failed.');
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-upload me-1"></i> Upload';
                }
            }

            async function uploadImageFile(file) {
                const maxKb = getMaxKb();
                const err = validateFileSize(file, maxKb);
                if (err) {
                    showImageError(err);
                    return;
                }
                showImageError('');
                const btn = document.getElementById('qcImageUploadBtn');
                btn.disabled = true;
                try {
                    const fd = new FormData();
                    fd.append('product_id', document.getElementById('qcImageProductId').value);
                    fd.append('sku', document.getElementById('qcImageSku').value);
                    fd.append('max_kb', String(maxKb));
                    fd.append('image', file);
                    const res = await fetch(uploadUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: fd,
                    });
                    const json = await res.json();
                    if (!json.success) {
                        showImageError(json.message || 'Upload failed.');
                        return;
                    }
                    updateRowFields(parseInt(document.getElementById('qcImageProductId').value, 10), {
                        qc_issue_image: json.image_path,
                        qc_issue_image_kb: json.image_size_kb,
                    });
                    applyHistoryToRow(parseInt(document.getElementById('qcImageProductId').value, 10), json);
                    renderImagePreview(json.image_path, json.image_size_kb);
                    pendingSnippetFile = null;
                    document.getElementById('qcImageFile').value = '';
                } catch (e) {
                    showImageError(e.message || 'Upload failed.');
                } finally {
                    btn.disabled = false;
                }
            }

            async function readClipboardImage() {
                if (navigator.clipboard && navigator.clipboard.read) {
                    const items = await navigator.clipboard.read();
                    for (const item of items) {
                        for (const type of item.types) {
                            if (type.startsWith('image/')) {
                                const blob = await item.getType(type);
                                const ext = type.split('/')[1] || 'png';
                                return new File([blob], 'snippet_' + Date.now() + '.' + ext, { type: type });
                            }
                        }
                    }
                    throw new Error('No image found on the clipboard. Take a screenshot first, then paste.');
                }
                throw new Error('Clipboard API not available. Use Ctrl/Cmd+V instead.');
            }

            const table = new Tabulator('#qcMastersTable', {
                ajaxURL: dataUrl + '?ts=' + Date.now(),
                ajaxResponse: function (url, params, response) {
                    const rows = (response && Array.isArray(response.data)) ? response.data : [];
                    return buildRowsWithParentSummaries(rows);
                },
                index: 'id',
                layout: 'fitDataStretch',
                placeholder: 'No data found',
                pagination: true,
                paginationSize: 100,
                paginationSizeSelector: [25, 50, 100, 200, 500],
                paginationCounter: 'rows',
                initialSort: [],
                headerSort: false,
                rowFormatter: function (row) {
                    const el = row.getElement();
                    if (row.getData().is_parent === true || isParentSku(row.getData().SKU)) {
                        el.classList.add('qc-parent-row');
                    } else {
                        el.classList.remove('qc-parent-row');
                    }
                },
                columns: [
                    {
                        title: "<input type='checkbox' id='qc-masters-select-all' title='Select all'>",
                        field: '_select',
                        width: 38,
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        headerSort: false,
                        formatter: function (cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent || isParentSku(d.SKU)) return '';
                            return '<input type="checkbox" class="row-cb" data-sku="' + esc(d.SKU || '') + '" data-id="' + esc(d.id || '') + '">';
                        },
                    },
                    {
                        title: 'Img',
                        field: 'image_path',
                        width: 60,
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        headerSort: false,
                        formatter: function (cell) {
                            const d = cell.getRow().getData();
                            const src = cell.getValue();
                            if (d.is_parent || isParentSku(d.SKU) || !src) return '';
                            return '<img src="' + esc(src) + '" alt="" ' +
                                'style="width:44px;height:44px;object-fit:cover;border-radius:4px;" ' +
                                'onerror="this.onerror=null;this.style.display=\'none\'">';
                        },
                    },
                    {
                        title: 'Parent',
                        field: 'Parent',
                        width: 120,
                        formatter: function (cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent || isParentSku(d.SKU)) return '';
                            const v = cell.getValue() || '';
                            if (!v) return '<span style="color:#adb5bd;">–</span>';
                            return '<span style="color:#0d6efd;font-size:11px;font-weight:600;">' + esc(v) + '</span>';
                        },
                    },
                    {
                        title: 'SKU',
                        field: 'SKU',
                        minWidth: 200,
                        formatter: function (cell) {
                            const d = cell.getRow().getData();
                            const val = cell.getValue() || '';
                            if (d.is_parent || isParentSku(d.SKU)) {
                                return '<span style="color:#0f5132;font-size:13px;font-weight:700;">' + esc(val) + '</span>';
                            }
                            return '<span class="d-inline-flex align-items-center gap-1">' +
                                '<span class="fw-bold">' + esc(val) + '</span>' +
                                '<button type="button" class="btn btn-sm btn-link p-0 qc-copy-sku-btn" data-sku="' + esc(val) + '" title="Copy SKU" ' +
                                'style="min-width:auto;line-height:1;color:#6c757d;vertical-align:middle;">' +
                                '<i class="fas fa-copy" style="font-size:12px;"></i></button>' +
                                '</span>';
                        },
                    },
                    {
                        title: 'Status',
                        field: 'status',
                        width: 70,
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        formatter: function (cell) {
                            const row = cell.getRow().getData();
                            if (row.is_parent || isParentSku(row.SKU)) return '';
                            let status = cell.getValue();
                            if ((status == null || status === '') && row.Values) {
                                status = row.Values.status;
                            }
                            return statusDotHtml(status);
                        },
                    },
                    {
                        title: 'INV',
                        field: 'shopify_inv',
                        width: 70,
                        sorter: 'number',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        formatter: function (cell) {
                            const d = cell.getRow().getData();
                            const val = parseInt(cell.getValue(), 10) || 0;
                            if (d.is_parent || isParentSku(d.SKU)) {
                                return '<span style="font-weight:700;">' + val + '</span>';
                            }
                            if (val === 0) return '<span style="color:#dc3545;font-weight:600;">0</span>';
                            return '<span style="font-weight:600;">' + val + '</span>';
                        },
                    },
                    {
                        title: 'Problem / Issue',
                        field: 'qc_problem_issue',
                        width: 70,
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        headerSort: false,
                        formatter: function (cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent || isParentSku(d.SKU)) return '';
                            return textCellHtml(cell.getValue());
                        },
                        cellClick: function (e, cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent || isParentSku(d.SKU)) return;
                            openTextModal(d, 'problem_issue', 'Problem / Issue');
                        },
                    },
                    {
                        title: 'Suggestion / Improve',
                        field: 'qc_suggestion_improve',
                        width: 70,
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        headerSort: false,
                        formatter: function (cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent || isParentSku(d.SKU)) return '';
                            return textCellHtml(cell.getValue());
                        },
                        cellClick: function (e, cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent || isParentSku(d.SKU)) return;
                            openTextModal(d, 'suggestion_improve', 'Suggestion / Improve');
                        },
                    },
                    {
                        title: 'Image',
                        field: 'qc_issue_image',
                        width: 90,
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        headerSort: false,
                        formatter: function (cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent || isParentSku(d.SKU)) return '';
                            const src = cell.getValue();
                            if (src) {
                                const kb = d.qc_issue_image_kb != null ? (d.qc_issue_image_kb + ' KB') : '';
                                return '<img src="' + esc(src) + '" class="qc-issue-thumb" title="' + esc(kb || 'View / replace') + '" ' +
                                    'onerror="this.onerror=null;this.style.display=\'none\'">';
                            }
                            return '<button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1" title="Add image / snippet">' +
                                '<i class="fas fa-camera"></i></button>';
                        },
                        cellClick: function (e, cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent || isParentSku(d.SKU)) return;
                            openImageModal(d);
                        },
                    },
                    {
                        title: 'Video',
                        field: 'qc_issue_video',
                        width: 90,
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        headerSort: false,
                        formatter: function (cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent || isParentSku(d.SKU)) return '';
                            const src = cell.getValue();
                            if (src) {
                                const kb = d.qc_issue_video_kb != null ? formatKbLabel(d.qc_issue_video_kb) : '';
                                return '<button type="button" class="btn btn-sm btn-success py-0 px-1" title="' + esc(kb || 'Play / replace') + '">' +
                                    '<i class="fas fa-play"></i></button>';
                            }
                            return '<button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1" title="Add video">' +
                                '<i class="fas fa-video"></i></button>';
                        },
                        cellClick: function (e, cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent || isParentSku(d.SKU)) return;
                            openVideoModal(d);
                        },
                    },
                    {
                        title: 'User History',
                        field: 'qc_user_history_label',
                        minWidth: 110,
                        width: 130,
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        headerSort: false,
                        formatter: function (cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent || isParentSku(d.SKU)) return '';
                            const label = String(cell.getValue() || '').trim();
                            if (!label) {
                                return '<span style="color:#94a3b8;" title="No history yet">—</span>';
                            }
                            return '<span style="max-width:120px;color:#0d6efd;font-size:11px;cursor:pointer;display:inline-block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="' + esc(label) + ' (click for full history)">' +
                                esc(label) + '</span>';
                        },
                        cellClick: function (e, cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent || isParentSku(d.SKU)) return;
                            openHistoryModal(d);
                        },
                    },
                ],
            });

            // Play / Pause parent navigation
            const parentSearchInput = document.getElementById('qc-masters-parent-search');
            const skuSearchInput = document.getElementById('qc-masters-sku-search');
            const playBackwardBtn = document.getElementById('qc-play-backward');
            const playAutoBtn = document.getElementById('qc-play-auto');
            const playPauseBtn = document.getElementById('qc-play-pause');
            const playForwardBtn = document.getElementById('qc-play-forward');
            let searchTimer = null;
            let qcUniqueParents = [];
            let isQcPlayActive = false;
            let currentQcParentIndex = -1;

            function normalizeQcParentKey(val) {
                if (val == null || val === '') return '';
                return String(val).trim().replace(/\s+/g, ' ').replace(/^PARENT\s+/i, '');
            }

            function buildQcUniqueParents() {
                const allRows = table.getData('all') || [];
                const seen = {};
                const list = [];
                allRows.forEach(function (r) {
                    const p = normalizeQcParentKey(parentKeyFromRow(r));
                    if (p && !seen[p]) {
                        seen[p] = true;
                        list.push(p);
                    }
                });
                list.sort(function (a, b) { return String(a).localeCompare(String(b)); });
                return list;
            }

            function updateQcPlayButtonStates() {
                if (playBackwardBtn) playBackwardBtn.disabled = !isQcPlayActive || currentQcParentIndex <= 0;
                if (playForwardBtn) playForwardBtn.disabled = !isQcPlayActive || currentQcParentIndex >= qcUniqueParents.length - 1;
            }

            function applyQcMastersSearch() {
                if (isQcPlayActive && qcUniqueParents.length > 0 && currentQcParentIndex >= 0) {
                    const currentKey = qcUniqueParents[currentQcParentIndex];
                    table.setFilter(function (data) {
                        return normalizeQcParentKey(parentKeyFromRow(data)) === currentKey;
                    });
                    return;
                }

                const parentQ = (parentSearchInput?.value || '').trim().toLowerCase();
                const skuQ = (skuSearchInput?.value || '').trim().toLowerCase();
                if (!parentQ && !skuQ) {
                    table.clearFilter(true);
                    return;
                }

                const parentsWithSkuMatch = new Set();
                if (skuQ) {
                    table.getData().forEach(function (data) {
                        const parent = String(data.Parent || '');
                        const sku = String(data.SKU || '');
                        if (parentQ && !parent.toLowerCase().includes(parentQ)) return;
                        if (sku.toLowerCase().includes(skuQ)) {
                            parentsWithSkuMatch.add(parent);
                        }
                    });
                }

                table.setFilter(function (data) {
                    const parent = String(data.Parent || '');
                    const sku = String(data.SKU || '');
                    const parentL = parent.toLowerCase();
                    const skuL = sku.toLowerCase();

                    if (parentQ && !parentL.includes(parentQ)) return false;
                    if (!skuQ) return true;
                    if (skuL.includes(skuQ)) return true;
                    if (isParentSku(sku) && parentsWithSkuMatch.has(parent)) return true;
                    return false;
                });
            }

            function startQcPlay() {
                qcUniqueParents = buildQcUniqueParents();
                if (qcUniqueParents.length === 0) return;
                isQcPlayActive = true;
                currentQcParentIndex = 0;
                if (playAutoBtn) playAutoBtn.style.display = 'none';
                if (playPauseBtn) playPauseBtn.style.display = '';
                applyQcMastersSearch();
                try { table.setPage(1); } catch (e) {}
                updateQcPlayButtonStates();
            }

            function stopQcPlay() {
                isQcPlayActive = false;
                currentQcParentIndex = -1;
                if (playPauseBtn) playPauseBtn.style.display = 'none';
                if (playAutoBtn) playAutoBtn.style.display = '';
                applyQcMastersSearch();
                updateQcPlayButtonStates();
            }

            function nextQcParent() {
                if (!isQcPlayActive || currentQcParentIndex >= qcUniqueParents.length - 1) return;
                currentQcParentIndex++;
                applyQcMastersSearch();
                try { table.setPage(1); } catch (e) {}
                updateQcPlayButtonStates();
            }

            function previousQcParent() {
                if (!isQcPlayActive || currentQcParentIndex <= 0) return;
                currentQcParentIndex--;
                applyQcMastersSearch();
                try { table.setPage(1); } catch (e) {}
                updateQcPlayButtonStates();
            }

            function scheduleQcMastersSearch() {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(applyQcMastersSearch, 200);
            }

            if (parentSearchInput) parentSearchInput.addEventListener('input', scheduleQcMastersSearch);
            if (skuSearchInput) skuSearchInput.addEventListener('input', scheduleQcMastersSearch);
            if (playAutoBtn) playAutoBtn.addEventListener('click', startQcPlay);
            if (playPauseBtn) playPauseBtn.addEventListener('click', stopQcPlay);
            if (playForwardBtn) playForwardBtn.addEventListener('click', nextQcParent);
            if (playBackwardBtn) playBackwardBtn.addEventListener('click', previousQcParent);

            document.getElementById('qcTextValue').addEventListener('input', function () {
                document.getElementById('qcTextCount').textContent = String(this.value.length);
            });

            document.getElementById('qcTextSaveBtn').addEventListener('click', async function () {
                const productId = parseInt(document.getElementById('qcTextProductId').value, 10);
                const sku = document.getElementById('qcTextSku').value;
                const field = document.getElementById('qcTextField').value;
                const value = document.getElementById('qcTextValue').value;
                const btn = this;
                btn.disabled = true;
                try {
                    const body = { product_id: productId, sku: sku };
                    body[field] = value;
                    const json = await postJson(updateUrl, body);
                    if (!json.success) {
                        alert(json.message || 'Save failed.');
                        return;
                    }
                    const patch = {};
                    if (field === 'problem_issue') patch.qc_problem_issue = json.problem_issue ?? value;
                    if (field === 'suggestion_improve') patch.qc_suggestion_improve = json.suggestion_improve ?? value;
                    updateRowFields(productId, patch);
                    applyHistoryToRow(productId, json);
                    if (qcTextModal) qcTextModal.hide();
                } catch (e) {
                    alert(e.message || 'Save failed.');
                } finally {
                    btn.disabled = false;
                }
            });

            document.getElementById('qcImageUploadBtn').addEventListener('click', function () {
                const fileInput = document.getElementById('qcImageFile');
                const file = (fileInput.files && fileInput.files[0]) || pendingSnippetFile;
                if (!file) {
                    showImageError('Choose a file or paste a snippet first.');
                    return;
                }
                uploadImageFile(file);
            });

            document.getElementById('qcImageFile').addEventListener('change', function () {
                pendingSnippetFile = null;
                showImageError('');
                const file = this.files && this.files[0];
                if (!file) return;
                const maxKb = getMaxKb();
                const err = validateFileSize(file, maxKb);
                if (err) {
                    showImageError(err);
                    return;
                }
                const url = URL.createObjectURL(file);
                renderImagePreview(url, Math.ceil(file.size / 1024));
            });

            document.getElementById('qcPasteSnippetBtn').addEventListener('click', async function () {
                try {
                    const file = await readClipboardImage();
                    pendingSnippetFile = file;
                    document.getElementById('qcImageFile').value = '';
                    const maxKb = getMaxKb();
                    const err = validateFileSize(file, maxKb);
                    if (err) {
                        showImageError(err);
                        return;
                    }
                    showImageError('');
                    const url = URL.createObjectURL(file);
                    renderImagePreview(url, Math.ceil(file.size / 1024));
                    const status = document.getElementById('qcPasteStatus');
                    status.textContent = 'Snippet ready';
                    status.style.display = '';
                } catch (e) {
                    showImageError(e.message || 'Could not read clipboard.');
                }
            });

            document.getElementById('qcImageModal').addEventListener('paste', function (e) {
                const items = (e.clipboardData && e.clipboardData.items) || [];
                for (const item of items) {
                    if (item.type && item.type.startsWith('image/')) {
                        e.preventDefault();
                        const blob = item.getAsFile();
                        if (!blob) return;
                        const ext = (item.type.split('/')[1] || 'png');
                        pendingSnippetFile = new File([blob], 'snippet_' + Date.now() + '.' + ext, { type: item.type });
                        document.getElementById('qcImageFile').value = '';
                        const maxKb = getMaxKb();
                        const err = validateFileSize(pendingSnippetFile, maxKb);
                        if (err) {
                            showImageError(err);
                            return;
                        }
                        showImageError('');
                        renderImagePreview(URL.createObjectURL(pendingSnippetFile), Math.ceil(pendingSnippetFile.size / 1024));
                        const status = document.getElementById('qcPasteStatus');
                        status.textContent = 'Snippet ready';
                        status.style.display = '';
                        return;
                    }
                }
            });

            document.getElementById('qcImageDeleteBtn').addEventListener('click', async function () {
                if (!confirm('Remove this image?')) return;
                const productId = parseInt(document.getElementById('qcImageProductId').value, 10);
                const sku = document.getElementById('qcImageSku').value;
                this.disabled = true;
                try {
                    const json = await postJson(deleteImageUrl, { product_id: productId, sku: sku });
                    if (!json.success) {
                        showImageError(json.message || 'Delete failed.');
                        return;
                    }
                    updateRowFields(productId, { qc_issue_image: null, qc_issue_image_kb: null });
                    applyHistoryToRow(productId, json);
                    pendingSnippetFile = null;
                    document.getElementById('qcImageFile').value = '';
                    renderImagePreview(null, null);
                } catch (e) {
                    showImageError(e.message || 'Delete failed.');
                } finally {
                    this.disabled = false;
                }
            });

            document.getElementById('qcVideoUploadBtn').addEventListener('click', function () {
                const fileInput = document.getElementById('qcVideoFile');
                const file = fileInput.files && fileInput.files[0];
                if (!file) {
                    showVideoError('Choose a video file first.');
                    return;
                }
                uploadVideoFile(file);
            });

            document.getElementById('qcVideoFile').addEventListener('change', function () {
                showVideoError('');
                const file = this.files && this.files[0];
                if (!file) return;
                const maxKb = getVideoMaxKb();
                const err = validateVideoFileSize(file, maxKb);
                if (err) {
                    showVideoError(err);
                    return;
                }
                const url = URL.createObjectURL(file);
                renderVideoPreview(url, Math.ceil(file.size / 1024));
            });

            document.getElementById('qcVideoDeleteBtn').addEventListener('click', async function () {
                if (!confirm('Remove this video?')) return;
                const productId = parseInt(document.getElementById('qcVideoProductId').value, 10);
                const sku = document.getElementById('qcVideoSku').value;
                this.disabled = true;
                try {
                    const json = await postJson(deleteVideoUrl, { product_id: productId, sku: sku });
                    if (!json.success) {
                        showVideoError(json.message || 'Delete failed.');
                        return;
                    }
                    updateRowFields(productId, { qc_issue_video: null, qc_issue_video_kb: null });
                    applyHistoryToRow(productId, json);
                    document.getElementById('qcVideoFile').value = '';
                    renderVideoPreview(null, null);
                } catch (e) {
                    showVideoError(e.message || 'Delete failed.');
                } finally {
                    this.disabled = false;
                }
            });

            document.addEventListener('change', function (e) {
                if (e.target && e.target.id === 'qc-masters-select-all') {
                    const checked = e.target.checked;
                    document.querySelectorAll('#qcMastersTable .row-cb').forEach(function (cb) {
                        cb.checked = checked;
                    });
                }
            });

            document.addEventListener('click', function (e) {
                const btn = e.target.closest('.qc-copy-sku-btn');
                if (!btn) return;
                e.preventDefault();
                e.stopPropagation();
                const sku = btn.getAttribute('data-sku') || '';
                if (!sku) return;
                const done = function () {
                    const icon = btn.querySelector('i');
                    if (icon) {
                        icon.classList.remove('fa-copy');
                        icon.classList.add('fa-check');
                        btn.style.color = '#28a745';
                        setTimeout(function () {
                            icon.classList.remove('fa-check');
                            icon.classList.add('fa-copy');
                            btn.style.color = '#6c757d';
                        }, 900);
                    }
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(sku).then(done).catch(function () {
                        window.prompt('Copy SKU:', sku);
                    });
                } else {
                    window.prompt('Copy SKU:', sku);
                }
            });

            window.qcMastersTable = table;
        });
    </script>
@endsection
