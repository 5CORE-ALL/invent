@extends('layouts.vertical', ['title' => 'Bullet Points Master', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

    <style>
        .card.bp-master-card { border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 2px 12px rgba(44,110,213,.06); }
        .card.bp-master-card .card-body { padding: 1.25rem 1.5rem; }
        .bp-master-toolbar { display:flex; flex-wrap:wrap; align-items:center; gap:.5rem; }
        .bp-master-toolbar .btn { padding:.3rem .6rem; font-size:.8rem; border-radius:6px; }
        .table-responsive { position:relative; border:1px solid #e2e8f0; border-radius:10px; max-height:640px; overflow:auto; box-shadow:0 2px 8px rgba(0,0,0,.04); background:#fff; }
        #bullet-master-table thead th { position:sticky; top:0; vertical-align:middle!important; background:linear-gradient(135deg,#2c6ed5 0%,#1a56b7 100%)!important; color:#fff; z-index:10; padding:6px 8px; font-size:10px; font-weight:600; text-transform:uppercase; white-space:nowrap; }
        #bullet-master-table thead .th-caption { display:flex; align-items:center; gap:6px; }
        #bullet-master-table thead .th-sub { margin-top:4px; }
        #bullet-master-table thead input, #bullet-master-table thead select { background:rgba(255,255,255,.95); border:none; border-radius:4px; color:#333; padding:4px 6px; width:100%; font-size:10px; }
        #bullet-master-table tbody td { padding:8px 10px; vertical-align:middle!important; border-bottom:1px solid #edf2f9; font-size:11px; line-height:1.35; color:#475569; }
        #bullet-master-table tbody tr:nth-child(even){ background:#f8fafc; }
        #bullet-master-table tbody tr:hover{ background:#e8f0fe; }
        .table-img-cell img { width:36px; height:36px; object-fit:cover; border-radius:4px; }
        .preview-cell { max-width:240px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; cursor:help; }
        .action-buttons-cell { white-space:nowrap; vertical-align:middle!important; }
        .action-buttons-group { display:flex; align-items:center; gap:6px; }
        .action-btn { padding:5px 10px; border:none; border-radius:6px; font-size:11px; font-weight:500; display:inline-flex; align-items:center; gap:4px; }
        .view-btn { background:#17a2b8; color:#fff; }
        .edit-btn { background:linear-gradient(135deg,#2c6ed5 0%,#1a56b7 100%); color:#fff; }
        .shopify-row-pull-btn { background:#f59e0b; color:#fff; padding:5px 8px; }
        /* Title Master–style horizontal marketplace cells */
        .marketplaces-cell { vertical-align:middle!important; }
        .bp-mp-inline { display:flex; flex-wrap:wrap; align-items:flex-end; gap:6px; justify-content:flex-start; min-width:120px; }
        .bp-mp-th-title { font-weight:600; letter-spacing:0.2px; }
        .bp-mp-th-legend { margin-top:4px; font-size:8px; font-weight:500; opacity:0.92; line-height:1.3; text-transform:none; letter-spacing:0; white-space:normal; max-width:220px; }
        .bp-mp-th-icons { display:flex; flex-wrap:wrap; gap:4px; margin-top:6px; justify-content:center; align-items:center; }
        .bp-mp-th-pill { width:22px; height:22px; border-radius:4px; font-size:8px; font-weight:700; color:#fff; display:inline-flex; align-items:center; justify-content:center; line-height:1; }
        .bp-mp-stack { display:flex; flex-direction:column; align-items:center; gap:3px; border:none; background:transparent; padding:0; cursor:pointer; }
        .bp-mp-stack:hover .marketplace-btn:not(:disabled) { transform:translateY(-1px); box-shadow:0 2px 6px rgba(0,0,0,.18); }
        .bp-mp-dot { width:10px; height:10px; border-radius:50%; border:2px solid #94a3b8; background:transparent; transition:background .15s,border-color .15s; flex-shrink:0; }
        .bp-mp-dot.pushed { background:#22c55e; border-color:#22c55e; }
        .bp-mp-dot.failed { background:#ef4444; border-color:#ef4444; }
        .marketplace-btn { width:28px; height:28px; border:none; border-radius:4px; color:#fff; font-weight:600; font-size:11px; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; transition:all .2s; padding:0; }
        .btn-ebay1 { background-color:#0d6efd; }
        .btn-ebay2 { background-color:#198754; }
        .btn-ebay3 { background-color:#fd7e14; }
        .btn-macy { background-color:#0d6efd; }
        .btn-amazon { background-color:#ff9900; }
        .btn-temu { background-color:#ff6b00; }
        .btn-reverb { background-color:#333333; }
        .btn-wayfair { background-color:#7a3ff2; }
        .btn-bestbuy { background-color:#0046be; }
        .btn-shopify { background-color:#7cb342; }
        .btn-shopify-pls { background-color:#5c6bc0; }
        .mp-counter { font-size:10px; color:#6c757d; }
        .mp-counter.warning { color:#b8860b; font-weight:600; }
        .mp-counter.error { color:#dc3545; font-weight:700; }
        .group-badge { font-size:10px; }
        .btn-push-all { background:#ff9900!important; color:#232f3e!important; font-weight:600; }
        .btn-push-all:hover { background:#e88b00!important; color:#fff!important; }
        .toast-container { z-index:1100; }
        .rainbow-loader { display:none; text-align:center; padding:40px; }
        .rainbow-loader .loading-text { margin-top:16px; font-weight:600; color:#2c6ed5; }
        .modal-header-gradient { background:linear-gradient(135deg,#6B73FF 0%,#000DFF 100%); color:#fff; }
        .ai-edit-panel { border:1px solid #dee2e6; border-radius:8px; padding:10px; background:#f8fafc; }
        #editRowModal { z-index: 1055; }
        .modal-backdrop.edit-row-backdrop { z-index: 1050; }
        #aiPromptRulesModal { z-index: 1075; }
        .modal-backdrop.ai-prompt-rules-backdrop { z-index: 1070; }
        #editBulletChangeModal { z-index: 1085; }
        .modal-backdrop.edit-bullet-change-backdrop { z-index: 1080; }
        .modal-market-wrap { border:1px solid #dee2e6; border-radius:8px; padding:10px; background:#fff; }
        /* View-all bullet points modal */
        #viewRowModal .bp-view-section { margin-bottom:1.25rem; }
        #viewRowModal .bp-view-section-title {
            font-family: ui-monospace, Consolas, monospace;
            font-size:11px; font-weight:700; color:#1e40af; letter-spacing:0.02em;
            padding:.35rem .5rem; background:#eff6ff; border-radius:6px; border-left:4px solid #2563eb;
            margin-bottom:.65rem;
        }
        #viewRowModal .bp-view-mp { margin-bottom:1rem; padding-bottom:1rem; border-bottom:1px solid #e2e8f0; }
        #viewRowModal .bp-view-mp:last-child { border-bottom:none; padding-bottom:0; margin-bottom:0; }
        #viewRowModal .bp-view-mp-label { font-weight:600; color:#0f172a; font-size:13px; margin-bottom:.35rem; display:flex; align-items:center; justify-content:space-between; gap:.5rem; flex-wrap:wrap; }
        #viewRowModal .bp-view-char { font-size:11px; font-weight:500; color:#64748b; }
        #viewRowModal .bp-view-body {
            font-size:12px; line-height:1.45; color:#334155; white-space:pre-wrap; word-break:break-word;
            background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:.65rem .75rem; min-height:2.25rem;
        }
        #viewRowModal .bp-view-empty { color:#94a3b8; font-style:italic; }
        #viewRowModal .modal-body { max-height:min(70vh, 560px); overflow-y:auto; }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('layouts.shared/page-title', [
        'page_title' => 'Bullet Points Master',
        'sub_title' => 'Manage Product Bullet Points',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card bp-master-card">
                <div class="card-body">
                    <div class="mb-3 bp-master-toolbar">
                        <button id="exportBtn" class="btn btn-primary"><i class="fas fa-download"></i> Export</button>
                        <button id="importBtn" class="btn btn-info"><i class="fas fa-upload"></i> Import</button>
                        <button id="pullShopifyBtn" class="btn btn-warning"><i class="fas fa-download"></i> Shopify Pull</button>
                        <button id="pushSelectedBtn" class="btn btn-secondary"><i class="fas fa-cloud-upload-alt"></i> Push Selected</button>
                        <button id="pushAllBtn" class="btn btn-push-all"><i class="fas fa-cloud-upload-alt"></i> Push ALL to All Marketplaces</button>
                        <select id="tableBulletStatusFilter" class="form-select form-select-sm" style="width:auto; display:inline-block; min-width:190px;">
                            <option value="all">All bullet status</option>
                            <option value="master_has">Master bullets present</option>
                            <option value="master_missing">Master bullets missing</option>
                            <option value="count_1">1 bullet point</option>
                            <option value="count_2">2 bullet points</option>
                            <option value="count_3">3 bullet points</option>
                            <option value="count_4">4 bullet points</option>
                            <option value="count_5">5 bullet points</option>
                        </select>
                        <span class="text-muted small" id="rowCountBadge">0 products</span>
                        <input type="file" id="importFile" accept=".csv,.xlsx,.xls" style="display:none;">
                    </div>

                    <div class="table-responsive">
                        <table id="bullet-master-table" class="table dt-responsive nowrap w-100">
                            <thead>
                                <tr>
                                    <th>
                                        <div class="d-flex align-items-center gap-2"><span>SKU</span><span id="skuCountBp">(0)</span></div>
                                        <input type="text" id="skuSearchBp" class="th-sub mt-1" placeholder="Search SKU">
                                    </th>
                                    <th>Product Name</th>
                                    <th>
                                        <div class="th-caption">Current Bullets (Preview) <span id="previewCountBp">(0)</span></div>
                                        <input type="text" id="previewSearchBp" class="th-sub" placeholder="Search preview">
                                    </th>
                                    <th>Action</th>
                                    <th title="eBay1–3, Macy's, Amazon, Temu, Reverb, Wayfair, Best Buy">
                                        <div class="bp-mp-th-title">MARKET PLACES</div>
                                        <div class="bp-mp-th-icons">
                                            <span class="bp-mp-th-pill btn-ebay1">E1</span><span class="bp-mp-th-pill btn-ebay2">E2</span><span class="bp-mp-th-pill btn-ebay3">E3</span><span class="bp-mp-th-pill btn-macy">M</span><span class="bp-mp-th-pill btn-amazon">A</span><span class="bp-mp-th-pill btn-temu">T</span><span class="bp-mp-th-pill btn-reverb">R</span><span class="bp-mp-th-pill btn-wayfair">W</span><span class="bp-mp-th-pill btn-bestbuy">B</span>
                                        </div>
                                    </th>
                                    <th title="Shopify Main, Shopify PLS">
                                        <div class="bp-mp-th-title">SHOPIFY</div>
                                        <div class="bp-mp-th-icons">
                                            <span class="bp-mp-th-pill btn-shopify">SM</span><span class="bp-mp-th-pill btn-shopify-pls">PLS</span>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="table-body"></tbody>
                        </table>
                    </div>

                    <div id="rainbow-loader" class="rainbow-loader">
                        <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
                        <div class="loading-text">Loading Bullet Points Master Data...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editRowModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Bullet Points</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="modalSku">
                    <div class="mb-3 border-bottom pb-2">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                            <div class="small text-muted text-uppercase fw-semibold">Title</div>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="editModalAiPromptRulesBtn">
                                <i class="fas fa-sliders-h me-1"></i>AI Prompt Rules
                            </button>
                        </div>
                        <div id="modalTitleLabel" class="fw-semibold fs-5 text-dark lh-sm"></div>
                        <div class="small text-muted mt-2"><strong>Product:</strong> <span id="modalProductLabel"></span></div>
                        <div class="small text-muted mt-1"><strong>SKU:</strong> <span id="modalSkuLabel"></span></div>
                    </div>
                    <div class="ai-edit-panel mb-3">
                        <div class="mb-2">
                            <label for="editModalAiPromptDetails" class="form-label mb-1">AI Prompt Details / Keywords</label>
                            <textarea class="form-control" id="editModalAiPromptDetails" rows="3" placeholder="Add product details, keywords, material, size, use cases, customer benefits, or anything AI should include."></textarea>
                            <div class="form-text">Optional, but recommended when product name is short or unclear.</div>
                        </div>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <button class="btn btn-primary btn-sm" id="editModalAiGenerateBtn"><i class="fas fa-wand-magic-sparkles"></i> AI Generate</button>
                            <button class="btn btn-outline-primary btn-sm" id="editModalAiRegenerateBtn"><i class="fas fa-rotate"></i> Regenerate Existing Bullet Points with AI</button>
                            <span id="editModalAiLoading" style="display:none;"><i class="fas fa-spinner fa-spin"></i> Generating...</span>
                        </div>
                        <div id="editModalAiFields" class="row g-2"></div>
                    </div>
                    <div class="small text-muted">Edit bullet fields and save them to the product master. Empty slots are allowed.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveModalBtn"><i class="fas fa-save"></i> Save Bullet Points</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="aiPromptRulesModal" tabindex="-1" aria-labelledby="aiPromptRulesModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title" id="aiPromptRulesModalTitle"><i class="fas fa-sliders-h me-2"></i>AI Prompt Rules</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info small">
                        These rules are saved and used for future AI bullet generation instead of the default hardcoded prompt rules.
                    </div>
                    <label for="aiPromptRulesText" class="form-label">Prompt Rules</label>
                    <textarea class="form-control font-monospace" id="aiPromptRulesText" rows="18"></textarea>
                    <div class="form-text">Keep output format and marketplace compliance rules here so AI generation follows them consistently.</div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <span class="small text-muted me-auto" id="aiPromptRulesStatus"></span>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveAiPromptRulesBtn"><i class="fas fa-save me-1"></i>Save Rules</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editBulletChangeModal" tabindex="-1" aria-labelledby="editBulletChangeTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title" id="editBulletChangeTitle"><i class="fas fa-wand-magic-sparkles me-2"></i>Change Bullet</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="editBulletChangeIndex">
                    <div class="small text-muted mb-1">Current bullet</div>
                    <div id="editBulletChangePreview" class="border rounded bg-light p-2 small mb-3"></div>
                    <label for="editBulletChangePrompt" class="form-label">What should AI change in this bullet?</label>
                    <textarea class="form-control" id="editBulletChangePrompt" rows="4" placeholder="Example: make it shorter, focus on easy installation, include 4 inch car speaker keyword, remove repeated bass wording..."></textarea>
                    <div class="form-text">AI will rewrite only this bullet and use the other bullets as context.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="editBulletChangeSubmitBtn"><i class="fas fa-wand-magic-sparkles"></i> Change Bullet</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="viewRowModal" tabindex="-1" aria-labelledby="viewRowModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title" id="viewRowModalTitle"><i class="fas fa-eye me-2"></i>Bullet Points</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="viewRowContent"></div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="viewCopyAllBtn" title="Copy all text below to clipboard">
                        <i class="fas fa-copy me-1"></i> Copy all
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="shopifyPullModal" tabindex="-1" aria-labelledby="shopifyPullModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title" id="shopifyPullModalTitle"><i class="fas fa-download me-2"></i>Shopify Bullet Pull</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning small mb-3">
                        This imports bullet points from Shopify into Product Master only. It does not push anything back to Shopify.
                    </div>
                    <div class="small text-muted mb-2" id="shopifyPullScopeText">Scope: currently filtered SKUs.</div>
                    <div id="shopifyPullPanel" class="border rounded bg-light p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong>Progress</strong>
                            <span class="small text-muted" id="shopifyPullStatus">Ready</span>
                        </div>
                        <div class="progress mb-3" style="height: 12px;">
                            <div id="shopifyPullProgress" class="progress-bar bg-warning" role="progressbar" style="width:0%"></div>
                        </div>
                        <div id="shopifyPullLog" class="small font-monospace bg-white border rounded p-2" style="max-height:280px; overflow:auto;"></div>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" id="startShopifyPullBtn" class="btn btn-warning"><i class="fas fa-play"></i> Run in BG</button>
                    <button type="button" id="pauseShopifyPullBtn" class="btn btn-outline-warning" style="display:none;"><i class="fas fa-pause"></i> Pause</button>
                    <button type="button" id="resumeShopifyPullBtn" class="btn btn-outline-success" style="display:none;"><i class="fas fa-play"></i> Resume</button>
                    <button type="button" id="stopShopifyPullBtn" class="btn btn-outline-danger" style="display:none;"><i class="fas fa-stop"></i> Stop</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="shopifyPullConfirmModal" tabindex="-1" aria-labelledby="shopifyPullConfirmTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning-subtle">
                    <h5 class="modal-title" id="shopifyPullConfirmTitle"><i class="fas fa-triangle-exclamation me-2"></i>Confirm Shopify Pull</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2" id="shopifyPullConfirmScope">Do you want to pull bullet points from Shopify?</p>
                    <div class="alert alert-warning small mb-0">
                        This action will update the existing bullet points in Product Master. It will not update Shopify.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" id="shopifyPullConfirmCancelBtn" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" id="shopifyPullConfirmBtn"><i class="fas fa-download"></i> Yes, Pull Bullets</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="marketplacePushConfirmModal" tabindex="-1" aria-labelledby="marketplacePushConfirmTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger-subtle">
                    <h5 class="modal-title" id="marketplacePushConfirmTitle"><i class="fas fa-triangle-exclamation me-2"></i>Confirm Marketplace Push</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2" id="marketplacePushConfirmMessage">Do you want to push these bullet points?</p>
                    <div class="alert alert-danger small mb-0" id="marketplacePushConfirmWarning">
                        This action will update the selected marketplace listing. Please confirm before continuing.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="marketplacePushConfirmBtn"><i class="fas fa-cloud-upload-alt"></i> Yes, Push</button>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>
@endsection

@section('script')
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const MARKETPLACES = ['ebay', 'ebay2', 'ebay3', 'macy', 'amazon', 'temu', 'reverb', 'wayfair', 'bestbuy', 'shopify_main', 'shopify_pls'];
    const EBAY3_WARNING = 'eBay3 has different listing structure. Please verify bullet points format before pushing.';
    const LABELS = {
        ebay: 'eBay 1', ebay2: 'eBay 2', ebay3: 'eBay 3', macy: "Macy's", amazon: 'Amazon', temu: 'Temu', reverb: 'Reverb', wayfair: 'Wayfair', bestbuy: 'Best Buy',
        shopify_main: 'Shopify Main', shopify_pls: 'Shopify PLS',
    };
    /** Labels in view modal (eBay1-style names) */
    const VIEW_LABELS = {
        ebay: 'eBay1', ebay2: 'eBay2', ebay3: 'eBay3', macy: "Macy's", amazon: 'Amazon', temu: 'Temu', reverb: 'Reverb', wayfair: 'Wayfair', bestbuy: 'Best Buy',
        shopify_main: 'Shopify Main', shopify_pls: 'Shopify PLS',
    };
    const VIEW_SECTIONS = [
        { banner: '========== Marketplaces ==========', keys: MARKETPLACES },
    ];
    const GROUPS = {
        gChannels: ['ebay', 'ebay2', 'ebay3', 'macy', 'amazon', 'temu', 'reverb', 'wayfair', 'bestbuy'],
        gShopify: ['shopify_main', 'shopify_pls'],
    };
    /** Short labels on tiles (horizontal row, Title Master style) */
    const MP_TILE = {
        ebay: { cls: 'btn-ebay1', short: 'E1' },
        ebay2: { cls: 'btn-ebay2', short: 'E2' },
        ebay3: { cls: 'btn-ebay3', short: 'E3' },
        macy: { cls: 'btn-macy', short: 'M' },
        amazon: { cls: 'btn-amazon', short: 'A' },
        temu: { cls: 'btn-temu', short: 'T' },
        reverb: { cls: 'btn-reverb', short: 'R' },
        wayfair: { cls: 'btn-wayfair', short: 'W' },
        bestbuy: { cls: 'btn-bestbuy', short: 'B' },
        shopify_main: { cls: 'btn-shopify', short: 'SM' },
        shopify_pls: { cls: 'btn-shopify-pls', short: 'PLS' },
    };
    let tableData = [];
    let editRowModal, aiPromptRulesModal, editBulletChangeModal, viewRowModal, shopifyPullModal, shopifyPullConfirmModal, marketplacePushConfirmModal;
    let lastViewModalPlainText = '';
    let shopifyPullPollTimer = null;
    let shopifyPullSelectedSkus = null;
    let shopifyPullConfirmResolver = null;
    let marketplacePushConfirmResolver = null;

    const bySku = new Map();
    const cssEsc = (s) => (window.CSS && typeof window.CSS.escape === 'function')
        ? window.CSS.escape(String(s))
        : String(s).replace(/([ !"#$%&'()*+,./:;<=>?@[\\\]^`{|}~])/g, '\\$1');

    const esc = (s) => {
        if (s == null) return '';
        const d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    };
    const trunc = (s, n = 56) => (!s ? '-' : (String(s).length > n ? String(s).slice(0, n) + '…' : String(s)));
    function toast(msg, ok=true) {
        if (!window.bootstrap || !window.bootstrap.Toast) {
            alert(msg);
            return;
        }
        const id = 't' + Date.now();
        const cls = ok ? 'text-bg-success' : 'text-bg-danger';
        document.getElementById('toastContainer').insertAdjacentHTML('beforeend',
            `<div id="${id}" class="toast align-items-center ${cls} border-0" role="alert"><div class="d-flex"><div class="toast-body">${esc(msg)}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>`);
        const el = document.getElementById(id);
        const t = new bootstrap.Toast(el, { delay: 2400 });
        t.show();
        el.addEventListener('hidden.bs.toast', () => el.remove());
    }

    function setAiPromptRulesStatus(message, isError = false) {
        const status = document.getElementById('aiPromptRulesStatus');
        if (!status) return;
        status.textContent = message || '';
        status.classList.toggle('text-danger', !!isError);
        status.classList.toggle('text-success', !!message && !isError);
    }

    async function loadAiPromptRules() {
        const textarea = document.getElementById('aiPromptRulesText');
        if (!textarea) return;

        setAiPromptRulesStatus('Loading rules...');
        const response = await fetch('/bullet-point-master/ai-prompt-rules', {
            headers: { 'Accept': 'application/json' },
        });
        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Failed to load AI prompt rules.');
        }

        textarea.value = data.rules || '';
        setAiPromptRulesStatus('Rules loaded.');
    }

    async function saveAiPromptRules() {
        const textarea = document.getElementById('aiPromptRulesText');
        const button = document.getElementById('saveAiPromptRulesBtn');

        if (!textarea || !button) return;

        button.disabled = true;
        setAiPromptRulesStatus('Saving rules...');

        try {
            const response = await fetch('/bullet-point-master/ai-prompt-rules', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ rules: textarea.value.trim() }),
            });
            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Failed to save AI prompt rules.');
            }

            textarea.value = data.rules || textarea.value;
            setAiPromptRulesStatus('Saved.');
            toast('AI prompt rules saved.');
        } catch (error) {
            setAiPromptRulesStatus(error.message || 'Failed to save rules.', true);
            toast(error.message || 'Failed to save AI prompt rules.', false);
        } finally {
            button.disabled = false;
        }
    }

    function confirmEbay3Push(marketplaces) {
        const mps = Array.isArray(marketplaces) ? marketplaces : [];
        if (!mps.includes('ebay3')) return true;
        return window.confirm(EBAY3_WARNING);
    }

    function confirmMarketplacePush({ sku = '', marketplaces = [], mode = 'single' } = {}) {
        const labels = (Array.isArray(marketplaces) ? marketplaces : [marketplaces])
            .filter(Boolean)
            .map(mp => LABELS[mp] || mp);
        const messageEl = document.getElementById('marketplacePushConfirmMessage');
        const warningEl = document.getElementById('marketplacePushConfirmWarning');
        const confirmBtn = document.getElementById('marketplacePushConfirmBtn');
        const labelText = labels.length ? labels.join(', ') : 'selected marketplaces';
        const skuText = sku ? ` for SKU ${sku}` : '';
        const scopeText = mode === 'all'
            ? 'all visible products and all marketplaces'
            : mode === 'selected'
                ? 'selected products'
                : `${labelText}${skuText}`;

        if (messageEl) {
            messageEl.textContent = `Do you want to push bullet points to ${scopeText}?`;
        }
        if (warningEl) {
            warningEl.textContent = (Array.isArray(marketplaces) && marketplaces.includes('ebay3'))
                ? EBAY3_WARNING
                : 'This action will update the selected marketplace listing. Please confirm before continuing.';
        }
        if (confirmBtn) {
            confirmBtn.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Yes, Push';
        }

        if (!marketplacePushConfirmModal || !window.bootstrap) {
            return Promise.resolve(window.confirm(`Do you want to push bullet points to ${scopeText}?`));
        }

        return new Promise(resolve => {
            marketplacePushConfirmResolver = resolve;
            marketplacePushConfirmModal.show();
        });
    }

    function summarizeMarketplacePushRetries(results) {
        if (!results || typeof results !== 'object') return '';
        const parts = [];
        Object.keys(results).forEach((mp) => {
            const r = results[mp];
            if (!r || typeof r !== 'object') return;
            const label = LABELS[mp] || mp;
            const att = r.attempts != null ? r.attempts : 1;
            if (r.success && r.retried) parts.push(`${label}: ok after ${att} attempts`);
            else if (!r.success && att > 1) parts.push(`${label}: failed after ${att} attempts`);
        });
        return parts.length ? parts.join(' · ') : '';
    }

    function loadData() {
        document.getElementById('rainbow-loader').style.display = 'block';
        fetch('/bullet-point-master-combined-data')
            .then(r => r.json())
            .then(res => {
                const raw = Array.isArray(res.data) ? res.data : Object.values(res.data || {});
                tableData = raw.filter(i => i && i.SKU && !String(i.SKU).toUpperCase().includes('PARENT'));
                bySku.clear();
                tableData.forEach(r => bySku.set(String(r.SKU), r));
                try {
                    renderTable(tableData);
                } catch (e) {
                    console.error('renderTable failed', e);
                    const tbody = document.getElementById('table-body');
                    tbody.innerHTML = `<tr><td colspan="6" class="text-danger">Render failed: ${esc(e.message || e)}</td></tr>`;
                }
                const badge = document.getElementById('rowCountBadge');
                if (badge) badge.textContent = `${tableData.length} products`;
            })
            .catch(e => toast('Failed to load data: ' + e.message, false))
            .finally(() => { document.getElementById('rainbow-loader').style.display = 'none'; });
    }

    function mpStackHtml(sku, mp, status = '') {
        const normalizedStatus = String(status || '').toLowerCase();
        const pushed = normalizedStatus === 'success';
        const failed = normalizedStatus === 'failed';
        const tile = MP_TILE[mp] || { cls: 'btn-secondary', short: '?' };
        const stateText = pushed ? 'Pushed' : (failed ? 'Push failed' : 'Not pushed');
        const dotClass = pushed ? 'pushed' : (failed ? 'failed' : '');
        const tip = `${LABELS[mp]}. ${stateText}. Click to push.`;
        return `
            <button type="button" class="bp-mp-stack" data-push-mp="${mp}" data-sku="${esc(sku)}" title="${esc(tip)}">
                <span class="bp-mp-dot ${dotClass}" aria-hidden="true"></span>
                <span class="marketplace-btn ${tile.cls}">${esc(tile.short)}</span>
            </button>`;
    }

    function groupCell(groupKey, sku, bp, statuses = {}) {
        const marketplaces = GROUPS[groupKey] || [];
        return `
            <div class="marketplaces-cell">
                <div class="bp-mp-inline">
                    ${marketplaces.map(mp => mpStackHtml(sku, mp, statuses[mp] ?? '')).join('')}
                </div>
            </div>`;
    }

    function renderTable(rows) {
        rows = Array.isArray(rows) ? rows : Object.values(rows || {});
        const badge = document.getElementById('rowCountBadge');
        if (badge) badge.textContent = `${rows.length} products`;
        const pc = document.getElementById('previewCountBp');
        const sc = document.getElementById('skuCountBp');
        if (pc) pc.textContent = `(${rows.length})`;
        if (sc) sc.textContent = `(${rows.length})`;
        const tbody = document.getElementById('table-body');
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No products found</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(r => {
            const sku = String(r.SKU || '');
            const preview = r.default_bullets || [r.bullet1, r.bullet2, r.bullet3, r.bullet4, r.bullet5].filter(Boolean).join(' ');
            const bp = r.bullet_points || {};
            const statuses = r.bullet_push_statuses || {};
            return `<tr data-sku="${esc(sku)}">
                <td>${esc(sku)}</td>
                <td>${esc(r.Parent || sku)}</td>
                <td class="preview-cell" title="${esc(preview || '')}">${esc(trunc(preview, 64))}</td>
                <td class="action-buttons-cell">
                    <div class="action-buttons-group">
                        <button type="button" class="action-btn view-btn" data-view="${esc(sku)}" title="View Bullet Points" aria-label="View Bullet Points"><i class="fas fa-eye" aria-hidden="true"></i></button>
                        <button type="button" class="action-btn edit-btn" data-edit="${esc(sku)}"><i class="fas fa-edit"></i> Edit</button>
                        <button type="button" class="action-btn shopify-row-pull-btn" data-shopify-pull-sku="${esc(sku)}" title="Pull Shopify bullets for this SKU"><i class="fas fa-download"></i></button>
                    </div>
                </td>
                <td>${groupCell('gChannels', sku, bp, statuses)}</td>
                <td>${groupCell('gShopify', sku, bp, statuses)}</td>
            </tr>`;
        }).join('');

        bindRowEvents();
    }

    function bindRowEvents() {
        document.querySelectorAll('.view-btn[data-view]').forEach(b => b.addEventListener('click', () => openViewModal(b.dataset.view)));
        document.querySelectorAll('.edit-btn[data-edit]').forEach(b => b.addEventListener('click', () => openEditModal(b.dataset.edit)));
        document.querySelectorAll('.shopify-row-pull-btn[data-shopify-pull-sku]').forEach(b => b.addEventListener('click', () => startSingleShopifyPull(b.dataset.shopifyPullSku, b)));
        document.querySelectorAll('.bp-mp-stack[data-push-mp]').forEach(b => b.addEventListener('click', () => {
            pushSingleMarketplace(b.dataset.sku, b.dataset.pushMp, b);
        }));
    }

    function renderViewMarketplaceBlock(mpKey, label, text) {
        const raw = text == null ? '' : String(text);
        const trimmed = raw.trim();
        const empty = trimmed === '';
        const len = raw.length;
        const bodyHtml = empty
            ? `<div class="bp-view-body bp-view-empty">No bullet points saved yet</div>`
            : `<div class="bp-view-body">${esc(raw)}</div>`;
        return `
            <div class="bp-view-mp" data-mp="${esc(mpKey)}">
                <div class="bp-view-mp-label">
                    <span>${esc(label)}:</span>
                    <span class="bp-view-char">${len} character${len === 1 ? '' : 's'}</span>
                </div>
                ${bodyHtml}
            </div>`;
    }

    function buildViewModalHtml(sku, row) {
        const bp = row.bullet_points || {};
        const lines = [];

        let html = `<div class="mb-2 pb-2 border-bottom"><strong>Product:</strong> ${esc(row.Parent || sku)}</div>`;

        VIEW_SECTIONS.forEach((sec) => {
            html += `<div class="bp-view-section">`;
            html += `<div class="bp-view-section-title">${esc(sec.banner)}</div>`;
            lines.push(sec.banner);
            sec.keys.forEach((mp) => {
                const label = VIEW_LABELS[mp] || LABELS[mp] || mp;
                const text = bp[mp];
                html += renderViewMarketplaceBlock(mp, label, text);
                lines.push(`${label}: ${(text && String(text).trim()) ? String(text) : 'No bullet points saved yet'}`);
            });
            lines.push('');
            html += `</div>`;
        });

        const extraKeys = Object.keys(bp).filter((k) => {
            if (VIEW_SECTIONS.some((s) => s.keys.includes(k))) return false;
            return true;
        });
        if (extraKeys.length) {
            html += `<div class="bp-view-section">`;
            html += `<div class="bp-view-section-title">${esc('========== Other (from API) ==========')}</div>`;
            lines.push('========== Other (from API) ==========');
            extraKeys.sort().forEach((k) => {
                const text = bp[k];
                html += renderViewMarketplaceBlock(k, k, text);
                lines.push(`${k}: ${(text && String(text).trim()) ? String(text) : 'No bullet points saved yet'}`);
                lines.push('');
            });
            html += `</div>`;
        }

        lastViewModalPlainText = `Bullet Points - ${sku}\nProduct: ${row.Parent || sku}\n\n` + lines.join('\n').trim();
        return html;
    }

    function openViewModal(sku) {
        const row = bySku.get(String(sku));
        if (!row) return;
        const titleEl = document.getElementById('viewRowModalTitle');
        if (titleEl) {
            titleEl.innerHTML = `<i class="fas fa-eye me-2" aria-hidden="true"></i>${esc('Bullet Points - ' + sku)}`;
        }
        document.getElementById('viewRowContent').innerHTML = buildViewModalHtml(sku, row);
        if (viewRowModal) viewRowModal.show();
    }

    function copyViewModalToClipboard() {
        const text = lastViewModalPlainText || '';
        if (!text.trim()) {
            toast('Nothing to copy', false);
            return;
        }
        const done = () => toast('Copied all bullet points to clipboard');
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done).catch(() => fallbackCopy(text, done));
        } else {
            fallbackCopy(text, done);
        }
    }

    function fallbackCopy(text, onOk) {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
            onOk();
        } catch (e) {
            toast('Copy failed', false);
        }
        document.body.removeChild(ta);
    }

    function openEditModal(sku) {
        const row = bySku.get(String(sku));
        if (!row) return;
        document.getElementById('modalSku').value = sku;
        document.getElementById('modalSkuLabel').textContent = sku;
        document.getElementById('modalProductLabel').textContent = row.Parent || sku;
        const titleValue = row.shopify_product_title || row.title || row.Title || row.product_title || row.ProductTitle || row.product_name || row.ProductName || row.name || row.Name || row.Parent || sku;
        const titleEl = document.getElementById('modalTitleLabel');
        if (titleEl) titleEl.textContent = titleValue;
        const detailsField = document.getElementById('editModalAiPromptDetails');
        if (detailsField) {
            detailsField.value = '';
            detailsField.placeholder = `Add details for ${sku}: product type, keywords, material, size, benefits, use cases...`;
        }
        renderEditModalAiFields(row);
        if (editRowModal) editRowModal.show();
    }

    function renderEditModalAiFields(row) {
        const savedBullets = [row.bullet1, row.bullet2, row.bullet3, row.bullet4, row.bullet5]
            .map(v => (v == null ? '' : String(v).trim()));
        const current = savedBullets.some(Boolean)
            ? savedBullets
            : splitBulletsForModal(row.default_bullets || '');
        document.getElementById('editModalAiFields').innerHTML = [1,2,3,4,5].map(i => `
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <label class="form-label mb-1">Bullet ${i} <span id="editAiCount${i}" class="text-muted">0 chars</span></label>
                    <div class="btn-group btn-group-sm" role="group" aria-label="AI bullet actions">
                        <button type="button" class="btn btn-outline-secondary edit-ai-revert d-none" data-idx="${i}"><i class="fas fa-rotate-left"></i> Revert</button>
                        <button type="button" class="btn btn-outline-primary edit-ai-change" data-idx="${i}"><i class="fas fa-wand-magic-sparkles"></i> Change</button>
                    </div>
                </div>
                <textarea class="form-control edit-ai-bullet" data-idx="${i}" rows="4">${esc(current[i-1] || '')}</textarea>
            </div>
        `).join('');
        bindEditAICountersAndChanges();
    }

    function splitBulletsForModal(text) {
        const normalized = String(text || '').replace(/\r/g, '\n');
        const raw = normalized.split(/\n|[;|]/).map(s => s.replace(/^[-*\d\.\)\s]+/, '').trim()).filter(Boolean);
        const out = raw.slice(0, 5);
        while (out.length < 5) out.push('');
        return out.map(v => v);
    }

    function bindEditAICountersAndChanges() {
        document.querySelectorAll('.edit-ai-bullet').forEach(t => {
            const idx = t.dataset.idx;
            const update = () => {
                const len = t.value.length;
                const el = document.getElementById('editAiCount' + idx);
                if (el) {
                    el.textContent = `${len} chars`;
                    el.classList.toggle('text-muted', len === 0);
                }
            };
            t.addEventListener('input', update);
            update();
        });

        document.querySelectorAll('.edit-ai-change').forEach(btn => {
            btn.addEventListener('click', function() {
                const idx = this.dataset.idx;
                const field = document.querySelector(`.edit-ai-bullet[data-idx="${idx}"]`);
                openEditBulletChangeModal(idx, field ? field.value : '');
            });
        });

        document.querySelectorAll('.edit-ai-revert').forEach(btn => {
            btn.addEventListener('click', function() {
                const idx = this.dataset.idx;
                const field = document.querySelector(`.edit-ai-bullet[data-idx="${idx}"]`);
                if (!field || field.dataset.previousAiValue == null) {
                    toast('No previous bullet text to restore.', false);
                    return;
                }
                field.value = field.dataset.previousAiValue;
                delete field.dataset.previousAiValue;
                field.dispatchEvent(new Event('input'));
                this.classList.add('d-none');
                toast(`Bullet ${idx} reverted`);
            });
        });
    }

    function openEditBulletChangeModal(idx, currentText) {
        const indexField = document.getElementById('editBulletChangeIndex');
        const preview = document.getElementById('editBulletChangePreview');
        const prompt = document.getElementById('editBulletChangePrompt');
        const title = document.getElementById('editBulletChangeTitle');
        if (indexField) indexField.value = idx;
        if (preview) preview.textContent = currentText || 'This bullet is currently empty.';
        if (prompt) prompt.value = '';
        if (title) title.innerHTML = `<i class="fas fa-wand-magic-sparkles me-2"></i>Change Bullet ${idx}`;
        if (editBulletChangeModal) editBulletChangeModal.show();
        setTimeout(() => prompt && prompt.focus(), 200);
    }

    function rewriteBulletFromModal() {
        const idx = parseInt(document.getElementById('editBulletChangeIndex').value || '0', 10);
        const prompt = (document.getElementById('editBulletChangePrompt').value || '').trim();
        if (!idx || !prompt) {
            toast('Please enter what AI should change for this bullet.', false);
            return;
        }

        const field = document.querySelector(`.edit-ai-bullet[data-idx="${idx}"]`);
        const sku = document.getElementById('modalSku').value;
        const productName = (document.getElementById('modalTitleLabel') && document.getElementById('modalTitleLabel').textContent)
            || document.getElementById('modalProductLabel').textContent
            || sku;
        const promptDetails = (document.getElementById('editModalAiPromptDetails') && document.getElementById('editModalAiPromptDetails').value.trim()) || '';
        const currentBullets = getBulletLinesFromModal();
        const btn = document.getElementById('editBulletChangeSubmitBtn');
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Changing...';

        fetch('/bullet-point-master/rewrite-bullet', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({
                sku,
                product_name: productName,
                prompt_details: promptDetails,
                change_prompt: prompt,
                bullet_index: idx,
                bullet_text: field ? field.value : '',
                current_bullets: currentBullets,
            })
        }).then(async (r) => {
            const payload = await r.json().catch(() => ({}));
            if (!r.ok || !payload.success) {
                throw new Error(payload.message || 'AI bullet change failed');
            }
            return payload;
        }).then((res) => {
            if (field) {
                field.dataset.previousAiValue = field.value;
                field.value = res.bullet || '';
                field.dispatchEvent(new Event('input'));
                const revertBtn = document.querySelector(`.edit-ai-revert[data-idx="${idx}"]`);
                if (revertBtn) revertBtn.classList.remove('d-none');
            }
            if (editBulletChangeModal) editBulletChangeModal.hide();
            toast(`Bullet ${idx} changed`);
        }).catch(e => toast('AI bullet change failed: ' + e.message, false))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        });
    }

    /** Five slots in order (empty strings allowed). */
    function getBulletLinesFromModal() {
        return Array.from(document.querySelectorAll('.edit-ai-bullet')).map(t => t.value.trim());
    }

    /** Newline-separated payload; preserves empty slots so bullet N in the UI matches line N after split. */
    function bulletLinesToPayload(lines) {
        return lines.join('\n');
    }

    function getBulletLinesForPush(sku, row) {
        const savedBullets = [row.bullet1, row.bullet2, row.bullet3, row.bullet4, row.bullet5]
            .map(v => (v == null ? '' : String(v).trim()));
        if (savedBullets.some(Boolean)) {
            return savedBullets;
        }

        return splitBulletsForModal(row.default_bullets || '').map(s => s.trim());
    }

    async function pushSingleMarketplace(sku, mp, stackEl) {
        const row = bySku.get(String(sku));
        if (!row) return;
        const confirmed = await confirmMarketplacePush({ sku, marketplaces: [mp], mode: 'single' });
        if (!confirmed) return;
        const lines = getBulletLinesForPush(sku, row);
        const combined = bulletLinesToPayload(lines);
        const payload = { sku, updates: [{ marketplace: mp, bullet_points: combined }] };
        let retryLabelTimer;
        let origStackHtml;
        if (stackEl) {
            origStackHtml = stackEl.innerHTML;
            stackEl.disabled = true;
            retryLabelTimer = setTimeout(() => {
                if (stackEl.disabled) stackEl.innerHTML = '<span class="small text-nowrap">Updating...</span>';
            }, 2000);
        }
        fetch('/bullet-point-master/update', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(res => {
            const detail = summarizeMarketplacePushRetries(res.results);
            const rmp = res.results && (res.results[mp] || res.results[String(mp).toLowerCase()]);
            if (rmp && rmp.success) {
                if (stackEl) {
                    stackEl.innerHTML = origStackHtml || stackEl.innerHTML;
                    const dot = stackEl.querySelector('.bp-mp-dot');
                    if (dot) {
                        dot.classList.remove('failed');
                        dot.classList.add('pushed');
                    }
                    stackEl.title = `${LABELS[mp] || mp}. Pushed. Click to push.`;
                    row.bullet_points = row.bullet_points || {};
                    row.bullet_push_statuses = row.bullet_push_statuses || {};
                    row.bullet_points[mp] = combined;
                    row.bullet_push_statuses[mp] = 'success';
                    origStackHtml = stackEl.innerHTML;
                }
                toast(`${LABELS[mp]} pushed` + (detail ? ' — ' + detail : ''));
                setTimeout(loadData, 900);
            } else {
                if (stackEl) {
                    stackEl.innerHTML = origStackHtml || stackEl.innerHTML;
                    const dot = stackEl.querySelector('.bp-mp-dot');
                    if (dot) {
                        dot.classList.remove('pushed');
                        dot.classList.add('failed');
                    }
                    stackEl.title = `${LABELS[mp] || mp}. Push failed. Click to push.`;
                    row.bullet_push_statuses = row.bullet_push_statuses || {};
                    row.bullet_push_statuses[mp] = 'failed';
                    origStackHtml = stackEl.innerHTML;
                }
                const msg = (rmp ? rmp.message : (res.message || 'Push failed')) + (detail ? ' — ' + detail : '');
                toast(msg, false);
                loadData();
            }
        })
        .catch(e => toast('Push failed: ' + e.message, false))
        .finally(() => {
            if (retryLabelTimer) clearTimeout(retryLabelTimer);
            if (stackEl) {
                stackEl.disabled = false;
                if (origStackHtml) stackEl.innerHTML = origStackHtml;
            }
        });
    }

    async function bulkPush(mode) {
        const confirmed = await confirmMarketplacePush({ marketplaces: MARKETPLACES, mode });
        if (!confirmed) return;
        toast('Use marketplace tiles to push saved bullet points.', false);
    }

    function appendShopifyPullLog(message, ok = true) {
        const log = document.getElementById('shopifyPullLog');
        if (!log) return;
        const line = document.createElement('div');
        line.className = ok ? 'text-success' : 'text-danger';
        line.textContent = message;
        log.appendChild(line);
        log.scrollTop = log.scrollHeight;
    }

    function setShopifyPullProgress(done, total, text) {
        const pct = total > 0 ? Math.round((done / total) * 100) : 0;
        const bar = document.getElementById('shopifyPullProgress');
        const status = document.getElementById('shopifyPullStatus');
        if (bar) bar.style.width = pct + '%';
        if (status) status.textContent = text || `${done}/${total}`;
    }

    function currentFilteredRowsForPull() {
        const skuQ = (document.getElementById('skuSearchBp') && document.getElementById('skuSearchBp').value.toLowerCase().trim()) || '';
        const prevQ = (document.getElementById('previewSearchBp') && document.getElementById('previewSearchBp').value.toLowerCase().trim()) || '';
        const statusFilter = (document.getElementById('tableBulletStatusFilter') && document.getElementById('tableBulletStatusFilter').value) || 'all';
        let rows = tableData.filter(r => r && r.SKU && !String(r.SKU).toUpperCase().includes('PARENT'));
        if (skuQ) rows = rows.filter(r => String(r.SKU || '').toLowerCase().includes(skuQ));
        if (prevQ) {
            rows = rows.filter(r => {
                const preview = String(r.default_bullets || [r.bullet1, r.bullet2, r.bullet3, r.bullet4, r.bullet5].filter(Boolean).join(' ') || '').toLowerCase();
                return preview.includes(prevQ) || String(r.Parent || '').toLowerCase().includes(prevQ);
            });
        }
        rows = rows.filter(r => rowMatchesBulletStatusFilter(r, statusFilter));
        return rows;
    }

    function productMasterBulletsForRow(row) {
        return [row.bullet1, row.bullet2, row.bullet3, row.bullet4, row.bullet5]
            .map(v => (v == null ? '' : String(v).trim()))
            .filter(Boolean);
    }

    function rowMatchesBulletStatusFilter(row, filter) {
        if (!filter || filter === 'all') return true;
        const masterCount = productMasterBulletsForRow(row).length;
        const masterHas = masterCount > 0;
        if (filter === 'master_has') return masterHas;
        if (filter === 'master_missing') return !masterHas;
        if (filter.startsWith('count_')) return masterCount === Number(filter.replace('count_', ''));
        return true;
    }

    function isShopifyPullActive(status) {
        return ['running', 'paused', 'stopping'].includes(status);
    }

    function renderShopifyPullJob(job) {
        job = job || {};
        const panel = document.getElementById('shopifyPullPanel');
        const log = document.getElementById('shopifyPullLog');
        const pullBtn = document.getElementById('startShopifyPullBtn');
        const pauseBtn = document.getElementById('pauseShopifyPullBtn');
        const resumeBtn = document.getElementById('resumeShopifyPullBtn');
        const stopBtn = document.getElementById('stopShopifyPullBtn');
        const status = job.status || 'idle';
        const total = Number(job.total || 0);
        const done = Number(job.current_index || 0);
        const active = isShopifyPullActive(status);

        if (panel) panel.style.display = 'block';
        if (pullBtn) pullBtn.disabled = active;
        if (pauseBtn) pauseBtn.style.display = status === 'running' ? 'inline-block' : 'none';
        if (resumeBtn) resumeBtn.style.display = status === 'paused' ? 'inline-block' : 'none';
        if (stopBtn) stopBtn.style.display = active ? 'inline-block' : 'none';

        let text = job.last_message || 'Ready';
        if (status === 'running' && job.current_sku) text = `Running ${done + 1}/${total}: ${job.current_sku}`;
        if (status === 'paused') text = `Paused ${done}/${total}`;
        if (status === 'completed') text = `Done: ${job.ok_count || 0} ok, ${job.fail_count || 0} failed`;
        if (status === 'stopped') text = `Stopped: ${job.ok_count || 0} ok, ${job.fail_count || 0} failed`;
        setShopifyPullProgress(done, total, text);

        if (log) {
            log.innerHTML = '';
            (job.messages || []).forEach(item => {
                const line = document.createElement('div');
                line.className = item.ok ? 'text-success' : 'text-danger';
                line.textContent = `[${item.time || ''}] ${item.message || ''}`;
                log.appendChild(line);
            });
            log.scrollTop = log.scrollHeight;
        }
    }

    async function fetchShopifyPullStatus() {
        const res = await fetch('/bullet-point-master/shopify-pull/status', {
            headers: { 'Accept': 'application/json' }
        });
        const payload = await res.json().catch(() => ({}));
        if (!res.ok || !payload.success) throw new Error(payload.message || 'Unable to load Shopify pull status');
        return payload.job || {};
    }

    async function pollShopifyPullStatus() {
        try {
            const job = await fetchShopifyPullStatus();
            const wasActive = shopifyPullPollTimer !== null;
            renderShopifyPullJob(job);
            if (isShopifyPullActive(job.status || 'idle')) {
                startShopifyPullPolling();
            } else {
                stopShopifyPullPolling();
                if (wasActive && ['completed', 'stopped'].includes(job.status || '')) {
                    loadData();
                }
            }
        } catch (e) {
            appendShopifyPullLog('Status check failed: ' + e.message, false);
        }
    }

    function startShopifyPullPolling() {
        if (shopifyPullPollTimer !== null) return;
        shopifyPullPollTimer = window.setInterval(pollShopifyPullStatus, 3000);
    }

    function stopShopifyPullPolling() {
        if (shopifyPullPollTimer === null) return;
        window.clearInterval(shopifyPullPollTimer);
        shopifyPullPollTimer = null;
    }

    async function openShopifyPullModal(skus = null) {
        shopifyPullSelectedSkus = Array.isArray(skus) && skus.length
            ? skus.map(sku => String(sku || '').trim()).filter(Boolean)
            : null;
        const scope = document.getElementById('shopifyPullScopeText');
        if (scope) {
            scope.textContent = shopifyPullSelectedSkus
                ? `Scope: selected SKU ${shopifyPullSelectedSkus.join(', ')}.`
                : 'Scope: currently filtered SKUs.';
        }
        if (shopifyPullModal) shopifyPullModal.show();
        await pollShopifyPullStatus();
    }

    function confirmShopifyPull(scopeText) {
        const scope = document.getElementById('shopifyPullConfirmScope');
        if (scope) {
            scope.textContent = `Do you want to pull bullet points from Shopify for ${scopeText}?`;
        }

        return new Promise(resolve => {
            shopifyPullConfirmResolver = resolve;
            if (shopifyPullConfirmModal) {
                shopifyPullConfirmModal.show();
            } else {
                resolve(false);
            }
        });
    }

    async function startShopifyPullJobForSkus(skus, options = {}) {
        skus = (skus || []).map(sku => String(sku || '').trim()).filter(Boolean);
        if (!skus.length) {
            toast('No SKUs loaded to pull from Shopify.', false);
            return false;
        }

        const scopeText = options.scopeText || `${skus.length} SKU(s)`;
        if (!await confirmShopifyPull(scopeText)) {
            return false;
        }

        try {
            const res = await fetch('/bullet-point-master/shopify-pull/start', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ skus })
            });
            const payload = await res.json().catch(() => ({}));
            if (!res.ok || !payload.success) throw new Error(payload.message || 'Unable to start Shopify pull');
            renderShopifyPullJob(payload.job);
            startShopifyPullPolling();
            toast(options.successMessage || payload.message || 'Background Shopify pull started.');
            return true;
        } catch (e) {
            toast('Shopify pull start failed: ' + e.message, false);
            if (e.message.includes('already')) pollShopifyPullStatus();
            return false;
        }
    }

    async function startSingleShopifyPull(sku, btn) {
        sku = String(sku || '').trim();
        if (!sku) {
            toast('SKU missing for Shopify pull.', false);
            return;
        }

        const oldHtml = btn ? btn.innerHTML : '';
        const oldTitle = btn ? btn.getAttribute('title') : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Syncing';
            btn.setAttribute('title', 'Syncing Shopify bullets...');
        }

        const started = await startShopifyPullJobForSkus([sku], {
            scopeText: `SKU ${sku}`,
            successMessage: `Shopify bullet sync started for ${sku}.`,
        });

        if (!started && btn) {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
            if (oldTitle) btn.setAttribute('title', oldTitle);
        }
    }

    async function startShopifyPullToLocal() {
        const rows = shopifyPullSelectedSkus
            ? shopifyPullSelectedSkus.map(sku => ({ SKU: sku }))
            : currentFilteredRowsForPull();
        const skus = rows.map(row => String(row.SKU || '').trim()).filter(Boolean);
        if (!skus.length) {
            toast('No SKUs loaded to pull from Shopify.', false);
            return;
        }
        const scopeText = shopifyPullSelectedSkus
            ? `SKU: ${skus.join(', ')}`
            : `${skus.length} currently filtered SKU(s)`;
        await startShopifyPullJobForSkus(skus, { scopeText });
    }

    async function controlShopifyPull(action) {
        try {
            const res = await fetch(`/bullet-point-master/shopify-pull/${action}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({})
            });
            const payload = await res.json().catch(() => ({}));
            if (!res.ok || !payload.success) throw new Error(payload.message || `Unable to ${action} Shopify pull`);
            renderShopifyPullJob(payload.job);
            if (isShopifyPullActive((payload.job || {}).status || 'idle')) startShopifyPullPolling();
            toast(`Shopify pull ${action} requested.`);
        } catch (e) {
            toast(`Shopify pull ${action} failed: ${e.message}`, false);
        }
    }

    function exportData() {
        const rows = tableData
            .filter(row => row && row.SKU && !String(row.SKU).toUpperCase().includes('PARENT'))
            .map(row => ({
                Parent: row.Parent || '',
                SKU: row.SKU || '',
                'Product Name': row.Parent || row.SKU || '',
                'Bullet 1': row.bullet1 || '',
                'Bullet 2': row.bullet2 || '',
                'Bullet 3': row.bullet3 || '',
                'Bullet 4': row.bullet4 || '',
                'Bullet 5': row.bullet5 || '',
            }));
        if (!rows.length) {
            toast('No products available to export.', false);
            return;
        }
        const ws = XLSX.utils.json_to_sheet(rows);
        ws['!cols'] = [
            { wch: 24 },
            { wch: 28 },
            { wch: 36 },
            { wch: 48 },
            { wch: 48 },
            { wch: 48 },
            { wch: 48 },
            { wch: 48 },
        ];
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Bullet Points');
        XLSX.writeFile(wb, 'bullet_points_' + new Date().toISOString().split('T')[0] + '.xlsx');
    }

    function importData(file) {
        const reader = new FileReader();
        reader.onload = async function(e) {
            try {
                const wb = XLSX.read(new Uint8Array(e.target.result), { type: 'array' });
                const json = XLSX.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]]);
                if (!json.length) { toast('No rows in file', false); return; }

                const importBtn = document.getElementById('importBtn');
                const oldImportHtml = importBtn ? importBtn.innerHTML : '';
                let successCount = 0;
                let errorCount = 0;
                const failedSkus = [];
                if (importBtn) {
                    importBtn.disabled = true;
                    importBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importing...';
                }

                for (let idx = 0; idx < json.length; idx++) {
                    const row = json[idx];
                    const sku = getImportedValue(row, ['SKU', 'sku', 'Sku', 'Seller SKU', 'SellerSKU', 'seller_sku']);
                    if (importBtn) {
                        importBtn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Importing ${idx + 1}/${json.length}`;
                    }
                    if (!sku) {
                        errorCount++;
                        continue;
                    }

                    const bullets = extractImportedBullets(row);
                    const payload = { sku };
                    [1,2,3,4,5].forEach((i) => { payload['bullet' + i] = bullets[i - 1] || ''; });

                    try {
                        const res = await fetch('/bullet-points/save', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                            body: JSON.stringify(payload),
                        });
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok || !data.success) {
                            throw new Error(data.message || 'Save failed');
                        }
                        successCount++;
                    } catch (err) {
                        console.error('Bullet import row failed', { sku, error: err });
                        errorCount++;
                        failedSkus.push(sku);
                    }
                }

                if (importBtn) {
                    importBtn.disabled = false;
                    importBtn.innerHTML = oldImportHtml;
                }

                const failedText = failedSkus.length ? ` Failed SKUs: ${failedSkus.slice(0, 5).join(', ')}${failedSkus.length > 5 ? '...' : ''}` : '';
                toast(`Import completed: ${successCount} saved, ${errorCount} failed.${failedText}`, errorCount === 0);
                loadData();
            } catch (err) {
                toast('Import failed: ' + err.message, false);
                const importBtn = document.getElementById('importBtn');
                if (importBtn) {
                    importBtn.disabled = false;
                    importBtn.innerHTML = '<i class="fas fa-upload"></i> Import';
                }
            }
        };
        reader.readAsArrayBuffer(file);
    }

    function extractImportedBullets(row) {
        const valueFor = (i) => {
            return getImportedValue(row, [
                'Bullet ' + i,
                'bullet' + i,
                'Bullet' + i,
                'BULLET ' + i,
                'BULLET' + i,
                'bullet_' + i,
                'bullet-' + i,
            ]);
        };

        const bullets = [1,2,3,4,5].map(valueFor);
        if (bullets.some(Boolean)) {
            return bullets;
        }

        const preview = getImportedValue(row, ['Preview', 'preview', 'Current Bullets', 'Current Bullets Preview', 'default_bullets']);
        return splitBulletsForModal(preview);
    }

    function getImportedValue(row, possibleKeys) {
        const normalizedMap = {};
        Object.keys(row || {}).forEach((key) => {
            normalizedMap[normalizeImportKey(key)] = row[key];
        });

        for (const key of possibleKeys) {
            const normalized = normalizeImportKey(key);
            if (Object.prototype.hasOwnProperty.call(normalizedMap, normalized)) {
                const value = normalizedMap[normalized];
                return value == null ? '' : String(value).trim();
            }
        }

        return '';
    }

    function normalizeImportKey(key) {
        return String(key || '').toLowerCase().replace(/[^a-z0-9]/g, '');
    }

    function applyTableFilters() {
        const skuQ = (document.getElementById('skuSearchBp') && document.getElementById('skuSearchBp').value.toLowerCase().trim()) || '';
        const prevQ = (document.getElementById('previewSearchBp') && document.getElementById('previewSearchBp').value.toLowerCase().trim()) || '';
        const statusFilter = (document.getElementById('tableBulletStatusFilter') && document.getElementById('tableBulletStatusFilter').value) || 'all';
        let rows = tableData;
        if (skuQ) {
            rows = rows.filter(r => String(r.SKU || '').toLowerCase().includes(skuQ));
        }
        if (prevQ) {
            rows = rows.filter(r => {
                const preview = String(r.default_bullets || [r.bullet1, r.bullet2, r.bullet3, r.bullet4, r.bullet5].filter(Boolean).join(' ') || '').toLowerCase();
                return preview.includes(prevQ) || String(r.Parent || '').toLowerCase().includes(prevQ);
            });
        }
        rows = rows.filter(r => rowMatchesBulletStatusFilter(r, statusFilter));
        renderTable(rows);
    }
    const skuSearchBp = document.getElementById('skuSearchBp');
    const previewSearchBp = document.getElementById('previewSearchBp');
    const tableBulletStatusFilter = document.getElementById('tableBulletStatusFilter');
    if (skuSearchBp) skuSearchBp.addEventListener('input', applyTableFilters);
    if (previewSearchBp) previewSearchBp.addEventListener('input', applyTableFilters);
    if (tableBulletStatusFilter) tableBulletStatusFilter.addEventListener('change', applyTableFilters);

    document.getElementById('saveModalBtn').addEventListener('click', function() {
        const sku = document.getElementById('modalSku').value;
        const lines = getBulletLinesFromModal();
        const payload = { sku };
        [1,2,3,4,5].forEach((i) => { payload['bullet' + i] = lines[i - 1] || ''; });

        const btn = this; const old = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        fetch('/bullet-points/save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(async (r) => {
            const payload = await r.json().catch(() => ({}));
            if (!r.ok || !payload.success) {
                throw new Error(payload.message || 'Save failed');
            }
            return payload;
        })
        .then(res => {
            toast(res.message || 'Bullet points saved');
                if (editRowModal) editRowModal.hide();
                loadData();
        })
        .catch(e => toast('Save failed: ' + e.message, false))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = old;
        });
    });

    const viewCopyAllBtn = document.getElementById('viewCopyAllBtn');
    if (viewCopyAllBtn) viewCopyAllBtn.addEventListener('click', copyViewModalToClipboard);

    document.getElementById('pushSelectedBtn').addEventListener('click', () => bulkPush('selected'));
    document.getElementById('pushAllBtn').addEventListener('click', () => bulkPush('all'));
    document.getElementById('pullShopifyBtn').addEventListener('click', () => openShopifyPullModal());
    document.getElementById('startShopifyPullBtn').addEventListener('click', startShopifyPullToLocal);
    document.getElementById('pauseShopifyPullBtn').addEventListener('click', () => controlShopifyPull('pause'));
    document.getElementById('resumeShopifyPullBtn').addEventListener('click', () => controlShopifyPull('resume'));
    document.getElementById('stopShopifyPullBtn').addEventListener('click', () => controlShopifyPull('stop'));
    document.getElementById('shopifyPullConfirmBtn').addEventListener('click', () => {
        if (shopifyPullConfirmResolver) shopifyPullConfirmResolver(true);
        shopifyPullConfirmResolver = null;
        if (shopifyPullConfirmModal) shopifyPullConfirmModal.hide();
    });
    document.getElementById('shopifyPullConfirmModal').addEventListener('hidden.bs.modal', () => {
        if (shopifyPullConfirmResolver) shopifyPullConfirmResolver(false);
        shopifyPullConfirmResolver = null;
    });
    document.getElementById('marketplacePushConfirmBtn').addEventListener('click', () => {
        if (marketplacePushConfirmResolver) marketplacePushConfirmResolver(true);
        marketplacePushConfirmResolver = null;
        if (marketplacePushConfirmModal) marketplacePushConfirmModal.hide();
    });
    document.getElementById('marketplacePushConfirmModal').addEventListener('hidden.bs.modal', () => {
        if (marketplacePushConfirmResolver) marketplacePushConfirmResolver(false);
        marketplacePushConfirmResolver = null;
    });
    document.getElementById('exportBtn').addEventListener('click', exportData);
    document.getElementById('importBtn').addEventListener('click', () => document.getElementById('importFile').click());
    document.getElementById('importFile').addEventListener('change', function(e) { if (e.target.files[0]) importData(e.target.files[0]); this.value = ''; });
    document.getElementById('editBulletChangeSubmitBtn').addEventListener('click', rewriteBulletFromModal);

    function tagLatestModalBackdrop(className) {
        const backdrops = document.querySelectorAll('.modal-backdrop');
        const latestBackdrop = backdrops[backdrops.length - 1];
        if (latestBackdrop) latestBackdrop.classList.add(className);
    }

    function keepModalOpenIfEditModalVisible() {
        if (document.getElementById('editRowModal').classList.contains('show')) {
            document.body.classList.add('modal-open');
        }
    }

    document.getElementById('editRowModal').addEventListener('shown.bs.modal', () => {
        tagLatestModalBackdrop('edit-row-backdrop');
    });
    document.getElementById('aiPromptRulesModal').addEventListener('shown.bs.modal', () => {
        tagLatestModalBackdrop('ai-prompt-rules-backdrop');
    });
    document.getElementById('aiPromptRulesModal').addEventListener('hidden.bs.modal', keepModalOpenIfEditModalVisible);
    document.getElementById('editBulletChangeModal').addEventListener('shown.bs.modal', () => {
        tagLatestModalBackdrop('edit-bullet-change-backdrop');
    });
    document.getElementById('editBulletChangeModal').addEventListener('hidden.bs.modal', keepModalOpenIfEditModalVisible);

    function runEditModalAiGenerate(sourceButton, options = {}) {
        const sku = document.getElementById('modalSku').value;
        const productName = (document.getElementById('modalTitleLabel') && document.getElementById('modalTitleLabel').textContent)
            || document.getElementById('modalProductLabel').textContent
            || sku;
        const promptDetails = (document.getElementById('editModalAiPromptDetails') && document.getElementById('editModalAiPromptDetails').value.trim()) || '';
        const currentBullets = getBulletLinesFromModal();
        const currentText = options.regenerate ? currentBullets.filter(Boolean).join('\n') : '';
        if (options.regenerate && currentText === '') {
            toast('No existing bullet points found to regenerate.', false);
            return;
        }
        const btn = sourceButton;
        const otherBtn = btn.id === 'editModalAiGenerateBtn'
            ? document.getElementById('editModalAiRegenerateBtn')
            : document.getElementById('editModalAiGenerateBtn');
        btn.disabled = true;
        if (otherBtn) otherBtn.disabled = true;
        document.getElementById('editModalAiLoading').style.display = 'inline';
        fetch('/bullet-point-master/generate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ product_id: sku, sku, product_name: productName, prompt_details: promptDetails, current_text: currentText })
        }).then(async (r) => {
            const payload = await r.json().catch(() => ({}));
            if (!r.ok || !payload.success) {
                throw new Error(payload.message || 'AI generation failed');
            }
            return payload;
        }).then((res) => {
            const bullets = res.bullets || [];
            document.querySelectorAll('.edit-ai-bullet').forEach((t, i) => {
                if (options.regenerate) {
                    t.dataset.previousAiValue = currentBullets[i] || '';
                    const revertBtn = document.querySelector(`.edit-ai-revert[data-idx="${i + 1}"]`);
                    if (revertBtn) revertBtn.classList.remove('d-none');
                }
                t.value = (bullets[i] || '');
                t.dispatchEvent(new Event('input'));
            });
            const lens = bullets.map(b => String(b || '').length);
            console.info('[BP AI] generated bullet lengths', lens);
            toast(options.regenerate ? 'Existing bullets regenerated with AI' : 'AI bullets generated');
        }).catch(e => toast('AI generation failed: ' + e.message, false))
        .finally(() => {
            btn.disabled = false;
            if (otherBtn) otherBtn.disabled = false;
            document.getElementById('editModalAiLoading').style.display = 'none';
        });
    }

    document.getElementById('editModalAiGenerateBtn').addEventListener('click', function() {
        runEditModalAiGenerate(this);
    });

    document.getElementById('editModalAiRegenerateBtn').addEventListener('click', function() {
        runEditModalAiGenerate(this, { regenerate: true });
    });

    document.getElementById('editModalAiPromptRulesBtn')?.addEventListener('click', async function() {
        try {
            await loadAiPromptRules();
            if (aiPromptRulesModal) {
                aiPromptRulesModal.show();
            }
        } catch (error) {
            toast(error.message || 'Failed to load AI prompt rules.', false);
        }
    });

    document.getElementById('saveAiPromptRulesBtn')?.addEventListener('click', saveAiPromptRules);

    function waitForBootstrap() {
        if (window.bootstrap && window.bootstrap.Modal && window.bootstrap.Toast) {
            return Promise.resolve();
        }

        return new Promise((resolve) => {
            const existing = document.querySelector('script[data-bp-bootstrap]');
            if (existing) {
                existing.addEventListener('load', () => resolve(), { once: true });
                existing.addEventListener('error', () => resolve(), { once: true });
                return;
            }

            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js';
            script.async = true;
            script.dataset.bpBootstrap = '1';
            script.onload = () => resolve();
            script.onerror = () => resolve();
            document.head.appendChild(script);
        });
    }

    waitForBootstrap().then(() => {
        if (window.bootstrap && window.bootstrap.Modal) {
            editRowModal = new bootstrap.Modal(document.getElementById('editRowModal'));
            aiPromptRulesModal = new bootstrap.Modal(document.getElementById('aiPromptRulesModal'));
            editBulletChangeModal = new bootstrap.Modal(document.getElementById('editBulletChangeModal'));
            viewRowModal = new bootstrap.Modal(document.getElementById('viewRowModal'));
            shopifyPullModal = new bootstrap.Modal(document.getElementById('shopifyPullModal'));
            shopifyPullConfirmModal = new bootstrap.Modal(document.getElementById('shopifyPullConfirmModal'));
            marketplacePushConfirmModal = new bootstrap.Modal(document.getElementById('marketplacePushConfirmModal'));
        } else {
            console.warn('Bootstrap JS not available; modals/toasts will be degraded.');
        }
        loadData();
        pollShopifyPullStatus();
    });
});
</script>
@endsection
