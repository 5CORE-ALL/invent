@php
    $isBatchCoo = ($kind ?? '') === \App\Models\ProductRawImage::KIND_BATCH_COO;
    $isHero2 = ($kind ?? '') === \App\Models\ProductRawImage::KIND_HERO_2;
    $pageTitle = $pageTitle ?? ($isHero2 ? 'Hero Image 2' : ($isBatchCoo ? 'Raw Images (Batch +COO)' : 'Raw Images'));
    $pageSubtitle = $pageSubtitle ?? ($isHero2 ? 'Upload hero image 2 files by SKU' : ($isBatchCoo ? 'Upload batch and COO raw image files by SKU' : 'Upload original raw image files by SKU'));
    $dataUrl = $dataUrl ?? ($isHero2 ? route('raw.images.hero.2.data') : ($isBatchCoo ? route('raw.images.batch.coo.data') : route('raw.images.data')));
    $uploadUrl = $uploadUrl ?? ($isHero2 ? route('raw.images.hero.2.upload') : ($isBatchCoo ? route('raw.images.batch.coo.upload') : route('raw.images.upload')));
    $destroyBaseUrl = $destroyBaseUrl ?? ($isHero2 ? url('/raw-images-hero-2') : ($isBatchCoo ? url('/raw-images-batch-coo') : url('/raw-images')));
    $bulkImportUrl = $bulkImportUrl ?? ($isHero2 ? route('raw.images.hero.2.bulk.import') : ($isBatchCoo ? route('raw.images.batch.coo.bulk.import') : route('raw.images.bulk.import')));
    $downloadUrl = $downloadUrl ?? ($isHero2 ? route('raw.images.hero.2.download') : ($isBatchCoo ? route('raw.images.batch.coo.download') : route('raw.images.download')));
    $templateUrl = $templateUrl ?? ($isHero2 ? route('raw.images.hero.2.template') : ($isBatchCoo ? route('raw.images.batch.coo.template') : route('raw.images.template')));
    $aiPromptUrl = $aiPromptUrl ?? ($isHero2 ? route('raw.images.hero.2.ai.prompt') : ($isBatchCoo ? route('raw.images.batch.coo.ai.prompt') : route('raw.images.ai.prompt')));
    $aiPromptSaveUrl = $aiPromptSaveUrl ?? ($isHero2 ? route('raw.images.hero.2.ai.prompt.save') : ($isBatchCoo ? route('raw.images.batch.coo.ai.prompt.save') : route('raw.images.ai.prompt.save')));
    $cachedImageUrl = $cachedImageUrl ?? (\Illuminate\Support\Facades\Route::has('raw.images.cached.image') ? route('raw.images.cached.image') : url('/raw-images/cached-image'));
    $manualColumnTitle = $manualColumnTitle ?? ($isHero2 ? 'Hero Image 2' : 'Raw Images');
    $aiColumnTitle = $aiColumnTitle ?? ($isHero2 ? 'Hero Image 2 AI' : 'Raw Images AI');
    $missingBadgeLabel = $missingBadgeLabel ?? ($isHero2 ? 'Missing Hero Image 2' : 'Missing Raw Images');
    $zipFileName = $zipFileName ?? ($isHero2 ? 'hero-image-2.zip' : 'raw-images.zip');
    $savedAiPrompt = $savedAiPrompt ?? ($isHero2
        ? "Make a hero image 2 from the image in the Hero image column and paste it in the Hero Image 2 AI column.\nThe size should be  2000x2000px.\nmake it realistic and Natural so that AI can not Detect.\nif product is dark then use light Background or vice-versa."
        : "Make a raw shoot image background for the image in Hero image column and paste it in raw image column.\nThe size should be  2000x2000px.\nmake it realistic and Natural so that AI can not Detect.\nif product is dark then use light Background or vice-versa.");
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

    .ri-ai-loading {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border-radius: 4px;
        background: linear-gradient(135deg, rgba(124, 58, 237, .1), rgba(37, 99, 235, .1));
        border: 1px solid #c4b5fd;
        color: #7c3aed;
        font-size: 16px;
    }

    .eh-card {
        width: 112px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #fff;
        overflow: hidden;
        cursor: grab;
        position: relative;
    }
    .eh-card.dragging { opacity: .4; }
    .eh-card img { width: 112px; height: 88px; object-fit: cover; display: block; }
    .eh-card-badge {
        position: absolute;
        top: 4px;
        left: 4px;
        background: #0f172a;
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        border-radius: 8px;
        padding: 1px 6px;
    }
    .eh-card-del {
        position: absolute;
        top: 4px;
        right: 4px;
        width: 20px;
        height: 20px;
        border: 0;
        border-radius: 50%;
        background: #dc3545;
        color: #fff;
        font-size: 11px;
        line-height: 1;
        cursor: pointer;
    }
    .eh-add-tile {
        width: 112px;
        height: 88px;
        border: 2px dashed #22c55e;
        border-radius: 8px;
        color: #22c55e;
        background: #f0fdf4;
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-weight: 700;
        gap: 4px;
    }
    .eh-add-tile:hover { background: #dcfce7; }

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
    #available-images-badge { cursor: pointer; }
    #available-images-badge.active-filter { box-shadow: 0 0 0 2px #fff, 0 0 0 4px #198754; }

    .ri-card-actions {
        display: flex;
        gap: 4px;
        padding: 0 6px 6px;
    }
    .ri-card-actions button,
    .ri-card-actions a {
        flex: 1;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        color: #334155;
        border-radius: 4px;
        font-size: 10px;
        padding: 2px 0;
        text-align: center;
        text-decoration: none;
        cursor: pointer;
        line-height: 1.4;
    }
    .ri-card-actions button:hover,
    .ri-card-actions a:hover { background: #e0e7ff; color: #1d4ed8; }

    #rainbow-loader { display: none; text-align: center; padding: 40px; }

    .ri-ai-btn,
    .ri-ai-btn:hover,
    .ri-ai-btn:focus,
    .ri-ai-btn:active,
    .ri-ai-btn:disabled {
        background: linear-gradient(135deg, #7c3aed 0%, #2563eb 100%) !important;
        border: none !important;
        color: #fff !important;
        --bs-btn-color: #fff;
        --bs-btn-hover-color: #fff;
        --bs-btn-active-color: #fff;
        --bs-btn-disabled-color: #fff;
        box-shadow: 0 1px 2px rgba(37, 99, 235, .25);
    }
    .ri-ai-btn i,
    .ri-ai-btn:hover i,
    .ri-ai-btn:focus i {
        color: #fff !important;
    }
    .ri-ai-btn:hover, .ri-ai-btn:focus { filter: brightness(1.08); }
    .ri-cell-ai {
        padding: 4px 8px !important;
        font-size: 11px;
        font-weight: 700;
        line-height: 1.2;
        letter-spacing: .02em;
    }
    .ri-ai-overlay {
        position: fixed;
        inset: 0;
        z-index: 2050;
        background: rgba(15, 23, 42, .48);
        display: none;
        align-items: center;
        justify-content: center;
        padding: 16px;
    }
    .ri-ai-overlay-card {
        background: #fff;
        border-radius: 16px;
        padding: 36px 44px;
        text-align: center;
        box-shadow: 0 18px 50px rgba(15, 23, 42, .28);
        min-width: 280px;
        max-width: 420px;
    }
    .ri-ai-overlay-title {
        margin-top: 16px;
        font-size: 18px;
        font-weight: 700;
        color: #1e3a8a;
    }
    .ri-ai-overlay-sub {
        margin-top: 8px;
        font-size: 13px;
        color: #64748b;
        line-height: 1.4;
    }
    .ri-ai-logo-preview {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .ri-ai-logo-chip {
        position: relative;
        width: 64px;
        height: 64px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #fff;
        overflow: hidden;
    }
    .ri-ai-logo-chip img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        display: block;
        background: repeating-conic-gradient(#f1f5f9 0% 25%, #fff 0% 50%) 50% / 12px 12px;
    }
    .ri-ai-logo-chip button {
        position: absolute;
        top: 2px;
        right: 2px;
        width: 18px;
        height: 18px;
        border: 0;
        border-radius: 50%;
        background: #dc3545;
        color: #fff;
        font-size: 11px;
        line-height: 1;
        cursor: pointer;
        padding: 0;
    }

    #raw-images-table .tabulator-cell[tabulator-field="barcode"] {
        overflow: visible !important;
        padding: 8px 4px !important;
    }
    .ri-barcode-square {
        width: 130px;
        min-height: 118px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        background: #fff;
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 8px 6px;
        box-sizing: border-box;
        gap: 4px;
        cursor: pointer;
        vertical-align: middle;
    }
    .ri-barcode-square:hover { box-shadow: 0 0 0 2px #4dd0e1; }
    .ri-barcode-square img,
    .ri-barcode-square svg {
        width: 110px;
        height: 48px;
        max-width: 110px;
        max-height: 48px;
        object-fit: contain;
        display: block;
        flex-shrink: 0;
        background: #fff;
    }
    .ri-barcode-sku {
        font-size: 10px;
        font-weight: 700;
        color: #1a3d7c;
        line-height: 1.2;
        text-align: center;
        width: 100%;
        word-break: break-word;
        max-height: 2.4em;
        overflow: hidden;
    }
    .ri-barcode-code {
        font-size: 10px;
        font-weight: 600;
        color: #374151;
        line-height: 1.2;
        text-align: center;
        width: 100%;
        word-break: break-all;
    }
    .ri-barcode-empty {
        width: 130px;
        min-height: 118px;
        border: 1px dashed #cbd5e1;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #94a3b8;
        font-size: 11px;
        background: #f8fafc;
    }
    #riBarcodeModal .modal-content { border-radius: 14px; border: 1px solid #c5d4ea; }
    #riBarcodeModalSku { font-size: 22px; font-weight: 700; color: #1a3d7c; word-break: break-word; }
    #riBarcodeModalCode { font-size: 16px; font-weight: 600; color: #1a3d7c; letter-spacing: .04em; word-break: break-all; }
    #riBarcodeModalMedia { min-height: 120px; display: flex; align-items: center; justify-content: center; border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px; background: #fff; }
    #riBarcodeModalMedia img, #riBarcodeModalMedia svg { max-width: 100%; max-height: 160px; }
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
                        <button type="button" class="btn btn-sm ri-ai-btn" id="riAiBtn" title="{{ $isHero2 ? 'Add prompt for Hero Image 2 AI' : 'Ask AI' }}">
                            <i class="fas fa-wand-magic-sparkles me-1"></i> AI
                        </button>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-warning dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-layer-group"></i> Bulk Update
                            </button>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item" href="#" id="bulkFromSheetBtn">
                                        <i class="fas fa-file-excel me-2"></i>From Sheet
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="#" id="bulkFromDropboxBtn">
                                        <i class="fab fa-dropbox me-2"></i>From Dropbox
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary" id="downloadSelectedBtn" title="Download {{ strtolower($pageTitle) }} for selected SKUs">
                            <i class="fas fa-download"></i> Download
                        </button>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="#" id="copySelectedSkusBtn">
                                        <i class="fas fa-barcode me-2"></i>Copy selected SKUs
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="#" id="copySelectedUrlsBtn">
                                        <i class="fas fa-link me-2"></i>Copy selected image URLs
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <span class="badge bg-secondary fs-6 p-2" id="selectedCountBadge">Selected: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="available-images-badge" title="Click to show SKUs that have a {{ strtolower($manualColumnTitle) }} or AI image">
                            Image: <span id="availableImagesCount">0</span>
                        </span>
                        <span class="badge bg-danger fs-6 p-2" id="missing-raw-images-badge" title="Click to show SKUs with inventory that are missing {{ $manualColumnTitle }} (0 INV excluded)">
                            {{ $missingBadgeLabel }}: <span id="missingRawImagesCount">0</span>
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
                                    <input type="text" id="parentSearch" class="form-control form-control-sm" placeholder="Search Parent... (0)">
                                </div>
                                <div class="col-md-4">
                                    <input type="text" id="skuSearch" class="form-control form-control-sm" placeholder="Search SKU... (0)">
                                </div>
                                <div class="col-md-4">
                                    <select id="filterRawImages" class="form-control form-control-sm">
                                        <option value="all">All SKUs</option>
                                        <option value="available">Available Images</option>
                                        <option value="missing">Missing Only (INV &gt; 0)</option>
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

                <div id="riAiProcessLoader" class="ri-ai-overlay" aria-live="polite" aria-busy="true">
                    <div class="ri-ai-overlay-card">
                        <div class="spinner-border text-primary" role="status" style="width:2.5rem;height:2.5rem;">
                            <span class="visually-hidden">Generating…</span>
                        </div>
                        <div class="ri-ai-overlay-title" id="riAiProcessLoaderTitle">Generating {{ $aiColumnTitle }}…</div>
                        <div class="ri-ai-overlay-sub" id="riAiProcessLoaderText">Please wait. This can take a minute.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="rawImageModal" tabindex="-1" aria-labelledby="rawImageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title" id="rawImageModalLabel">
                        <i class="fas fa-image me-2"></i><span id="modalKindLabel">{{ $pageTitle }}</span> — <span id="modalSkuLabel"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="modalSku">
                    <div class="mb-2"><strong>SKU:</strong> <span id="modalSkuText"></span></div>
                    <div class="mb-3 text-muted small">Parent: <span id="modalParentText">—</span></div>
                    <div id="rawImageGrid" class="ri-modal-grid"></div>
                    <input type="file" id="rawImageFileInput" class="d-none" accept="image/*,.dng,.cr2,.cr3,.nef,.arw,.raf,.orf,.rw2,.tif,.tiff,.heic" multiple>
                    <div class="small text-muted mt-3" id="rawUploadHint">
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

    <div class="modal fade" id="ebayHeroGalleryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title">
                        <i class="fas fa-image me-2"></i>Ebay H Img — <span id="ebayHeroSkuLabel"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="ebayHeroFetchBtn">
                            <i class="fab fa-ebay me-1"></i> Fetch from eBay
                        </button>
                        <button type="button" class="btn btn-sm btn-success" id="ebayHeroAddBtn">
                            <i class="fas fa-plus me-1"></i> Add images
                        </button>
                        <input type="file" id="ebayHeroFileInput" class="d-none" accept="image/jpeg,image/png,image/webp" multiple>
                    </div>
                    <div class="small text-muted mb-2">Drag cards to rearrange. First image is the eBay gallery lead.</div>
                    <div id="ebayHeroGrid" class="ri-modal-grid"></div>
                    <div class="small text-success fw-semibold mt-2" id="ebayHeroMsg" style="display:none;"></div>
                    <div class="small text-danger fw-semibold mt-2" id="ebayHeroErr" style="display:none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="ebayHeroSaveBtn">
                        <i class="fas fa-save me-1"></i> Save to eBay
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="bulkSheetModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title"><i class="fas fa-file-excel me-2"></i>Bulk update from sheet</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 small mb-3">
                        Use columns <strong>SKU</strong> and <strong>URL</strong> (Dropbox or any direct image link). Existing images are kept; new files are added.
                    </div>
                    <div class="mb-3">
                        <a href="{{ $templateUrl }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-file-csv me-1"></i> Download CSV template
                        </a>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Upload CSV / Excel</label>
                        <input type="file" class="form-control form-control-sm" id="bulkSheetFile" accept=".csv,.xlsx,.xls,.txt">
                    </div>
                    <div class="text-center text-muted small mb-2">or</div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Google Sheet URL</label>
                        <input type="url" class="form-control form-control-sm" id="bulkSheetUrl" placeholder="https://docs.google.com/spreadsheets/d/...">
                        <div class="form-text">Sheet must be published or allow CSV export.</div>
                    </div>
                    <div id="bulkSheetResult" class="small"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-warning" id="bulkSheetSubmitBtn">
                        <i class="fas fa-upload me-1"></i> Import
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="bulkDropboxModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title"><i class="fab fa-dropbox me-2"></i>Bulk update from Dropbox</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 small mb-3">
                        Paste <strong>file</strong> share links (not folders). One per line as <code>SKU, URL</code>. If you paste only a URL, the filename is matched to a SKU. <code>dl=0</code> links are converted automatically.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Dropbox file links</label>
                        <textarea class="form-control form-control-sm" id="bulkDropboxUrls" rows="8" placeholder="SKU-001, https://www.dropbox.com/s/.../image.jpg?dl=0"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Or upload a sheet of SKU + Dropbox URLs</label>
                        <input type="file" class="form-control form-control-sm" id="bulkDropboxFile" accept=".csv,.xlsx,.xls,.txt">
                    </div>
                    <div id="bulkDropboxResult" class="small"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-warning" id="bulkDropboxSubmitBtn">
                        <i class="fas fa-cloud-download-alt me-1"></i> Import
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="riAiModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title"><i class="fas fa-wand-magic-sparkles me-2"></i><span id="riAiModalTitle">AI prompt</span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label fw-semibold" for="riAiPrompt">Prompt</label>
                    <textarea class="form-control" id="riAiPrompt" rows="8" maxlength="8000">{{ $savedAiPrompt }}</textarea>
                    <div class="form-text" id="riAiFormHint">Edits are saved automatically. Gemini generates a {{ $isHero2 ? 'Hero Image 2 AI' : 'raw-shoot' }} image for selected rows only. Ctrl / ⌘ + Enter to save and close.</div>
                    <div class="mt-3">
                        <label class="form-label fw-semibold mb-1" for="riAiLogoInput">Logos (optional)</label>
                        <div class="small text-muted mb-2">Upload the logos you want in the AI image. They are used only for generation — not saved as a column.</div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="riAiLogoBtn">
                            <i class="fas fa-upload me-1"></i> Upload logos
                        </button>
                        <input type="file" id="riAiLogoInput" class="d-none" accept="image/png,image/jpeg,image/webp,image/gif" multiple>
                        <div id="riAiLogoPreview" class="ri-ai-logo-preview mt-2"></div>
                    </div>
                    <div id="riAiSelectedHint" class="small mt-2 text-muted"></div>
                    <div id="riAiResult" class="small mt-3"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn ri-ai-btn" id="riAiSubmitBtn">
                        <i class="fas fa-save me-1"></i> Save and Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="riBarcodeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title mb-0 text-muted">Barcode</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center d-flex flex-column align-items-center gap-3">
                    <div id="riBarcodeModalSku"></div>
                    <div id="riBarcodeModalMedia"></div>
                    <div id="riBarcodeModalCode"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const rawImagesDataUrl = @json($dataUrl);
        const rawImagesUploadUrl = @json($uploadUrl);
        const rawImagesDestroyBaseUrl = @json($destroyBaseUrl);
        const rawImagesBulkImportUrl = @json($bulkImportUrl);
        const rawImagesDownloadUrl = @json($downloadUrl);
        const rawImagesAiPromptUrl = @json($aiPromptUrl);
        const rawImagesAiPromptSaveUrl = @json($aiPromptSaveUrl);
        const rawImagesCachedImageUrl = @json($cachedImageUrl ?? (\Illuminate\Support\Facades\Route::has('raw.images.cached.image') ? route('raw.images.cached.image') : url('/raw-images/cached-image')));
        const ebayHeroFetchUrl = @json(\Illuminate\Support\Facades\Route::has('image.master.ebay.images') ? route('image.master.ebay.images') : url('/image-master/ebay-images'));
        const ebayHeroUploadUrl = @json(\Illuminate\Support\Facades\Route::has('image.master.upload') ? route('image.master.upload') : url('/image-master/upload'));
        const ebayHeroSaveUrl = @json(\Illuminate\Support\Facades\Route::has('raw.images.ebay.hero.save') ? route('raw.images.ebay.hero.save') : $uploadUrl);
        const rawImagesPageTitle = @json($pageTitle);
        const rawImagesManualColumnTitle = @json($manualColumnTitle);
        const rawImagesAiColumnTitle = @json($aiColumnTitle);
        const rawImagesIsHero2 = @json((bool) $isHero2);
        const rawImagesZipFileName = @json($zipFileName);
        const riImageWarm = new Set();

        function isParentRow(item) {
            return !!(item && item.SKU && String(item.SKU).toUpperCase().includes('PARENT'));
        }

        function cachedImageSrc(url, thumbUrl) {
            if (thumbUrl && String(thumbUrl).indexOf('/storage/image-cache/') !== -1) {
                return thumbUrl;
            }
            return url || thumbUrl || '';
        }

        function warmImage(url) {
            if (!url || riImageWarm.has(url)) return url;
            riImageWarm.add(url);
            const img = new Image();
            img.decoding = 'async';
            img.src = url;
            return url;
        }

        function rawImageSourceImages(row, source) {
            if (source === 'ai') return Array.isArray(row.raw_ai_images) ? row.raw_ai_images : [];
            return Array.isArray(row.raw_images) ? row.raw_images : [];
        }

        function rawImageCellHtml(row, source) {
            const sku = row.SKU || '';
            const isAi = source === 'ai';
            const images = rawImageSourceImages(row, source);
            const titles = {
                ai: 'View ' + rawImagesAiColumnTitle,
                manual: 'View / add ' + rawImagesManualColumnTitle.toLowerCase()
            };
            const title = titles[source] || titles.manual;
            if (source === 'ai' && (row.ai_generating || riAiLoadingSkus.has(sku))) {
                return '<span class="ri-ai-loading" title="Generating AI image…"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i></span>';
            }
            if (!images.length) {
                if (isAi) {
                    return '<button type="button" class="btn btn-sm ri-ai-btn ri-cell-ai js-open-ai-prompt" data-sku="' + escapeHtml(sku) + '" title="Add prompt for ' + escapeHtml(rawImagesAiColumnTitle) + '">'
                        + '<i class="fas fa-wand-magic-sparkles me-1"></i> AI</button>';
                }
                return '<button type="button" class="ri-cell-plus js-open-raw-modal" data-sku="' + escapeHtml(sku) + '" data-source="manual" title="Add ' + rawImagesManualColumnTitle + '">+</button>';
            }
            const first = images[0];
            const count = images.length;
            const alt = source === 'ai' ? 'Raw AI' : 'Raw';
            let inner;
            if (first.previewable && (first.thumb_url || first.url)) {
                inner = thumbHtml(cachedImageSrc(first.url, first.thumb_url), alt);
            } else {
                inner = '<i class="fas fa-file-image" style="font-size:22px;color:#2c6ed5;"></i>';
            }
            return '<button type="button" class="btn btn-link p-0 js-open-raw-modal" data-sku="' + escapeHtml(sku) + '" data-source="' + escapeHtml(source) + '" title="' + title + '" style="position:relative;display:inline-flex;">'
                + inner
                + (count > 1 ? '<span class="ri-cell-count">' + count + '</span>' : '')
                + '</button>';
        }

        function thumbHtml(url, alt, href) {
            if (!url) return '<span class="text-muted">—</span>';
            const src = warmImage(url);
            const fallback = href && href !== url ? href : '';
            const img = '<img src="' + escapeHtml(src) + '" class="ri-cell-thumb" alt="' + escapeHtml(alt || '') + '" loading="lazy" decoding="async"'
                + (fallback ? ' data-fallback="' + escapeHtml(fallback) + '"' : '')
                + ' onerror="if(this.dataset.fallback){ var f=this.dataset.fallback; this.dataset.fallback=\'\'; this.src=f; } else { this.style.visibility=\'hidden\'; }">';
            if (!href) return img;
            return '<a href="' + escapeHtml(href) + '" target="_blank" title="Image Master / Images tab">' + img + '</a>';
        }

        function ebayHeroImages(row) {
            if (row && Array.isArray(row.ebay_hero_images) && row.ebay_hero_images.length) {
                return row.ebay_hero_images;
            }
            const url = (row && row.ebay_hero_image) || '';
            if (!url) return [];
            return [{ url: url, thumb_url: (row && row.ebay_hero_thumb) || url }];
        }

        function ebayHeroCellHtml(row) {
            const images = ebayHeroImages(row);
            const sku = row.SKU || '';
            if (!images.length) {
                return '<button type="button" class="ri-cell-plus js-open-ebay-hero" data-sku="' + escapeHtml(sku) + '" title="Add Ebay H Img">+</button>';
            }
            const first = images[0];
            const src = cachedImageSrc(first.url, first.thumb_url);
            const count = images.length;
            const inner = thumbHtml(src, 'Ebay H Img');
            return '<button type="button" class="btn btn-link p-0 js-open-ebay-hero" data-sku="' + escapeHtml(sku) + '" title="'
                + (count > 1 ? (count + ' eBay images — drag to rearrange') : 'Open eBay images') + '" style="position:relative;display:inline-flex;">'
                + inner
                + (count > 1 ? '<span class="ri-cell-count">' + count + '</span>' : '')
                + '</button>';
        }

        let ebayHeroSku = '';
        let ebayHeroUrls = [];
        let ebayHeroDragFrom = null;
        const ebayHeroMax = 12;

        function setEbayHeroMsg(text) {
            const el = document.getElementById('ebayHeroMsg');
            if (!el) return;
            el.style.display = text ? 'block' : 'none';
            el.textContent = text || '';
        }
        function setEbayHeroErr(text) {
            const el = document.getElementById('ebayHeroErr');
            if (!el) return;
            el.style.display = text ? 'block' : 'none';
            el.textContent = text || '';
        }

        function uniqueEbayUrls(urls) {
            const seen = {};
            const out = [];
            (urls || []).forEach(function (u) {
                const url = String((typeof u === 'string' ? u : (u && (u.url || u.cdn_url))) || '').trim();
                if (!url) return;
                const key = url.toLowerCase();
                if (seen[key]) return;
                seen[key] = true;
                out.push(url);
            });
            return out.slice(0, ebayHeroMax);
        }

        function renderEbayHeroGrid() {
            const grid = document.getElementById('ebayHeroGrid');
            if (!grid) return;
            const cards = ebayHeroUrls.map(function (url, idx) {
                const src = cachedImageSrc(url);
                return '<div class="eh-card" draggable="true" data-idx="' + idx + '">'
                    + '<span class="eh-card-badge">' + (idx === 0 ? 'MAIN' : (idx + 1)) + '</span>'
                    + '<button type="button" class="eh-card-del" data-i="' + idx + '" title="Remove">&times;</button>'
                    + '<img src="' + escapeHtml(src) + '" alt="eBay ' + (idx + 1) + '" draggable="false">'
                    + '</div>';
            }).join('');
            grid.innerHTML = cards
                + (ebayHeroUrls.length < ebayHeroMax
                    ? '<button type="button" class="eh-add-tile" id="ebayHeroAddTile"><span style="font-size:22px;">+</span><span class="small">Add image</span></button>'
                    : '');

            grid.querySelectorAll('.eh-card-del').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const i = parseInt(btn.getAttribute('data-i'), 10);
                    ebayHeroUrls.splice(i, 1);
                    renderEbayHeroGrid();
                });
            });
            const addTile = document.getElementById('ebayHeroAddTile');
            if (addTile) addTile.addEventListener('click', function () {
                document.getElementById('ebayHeroFileInput').click();
            });

            ebayHeroDragFrom = null;
            grid.querySelectorAll('.eh-card').forEach(function (card) {
                card.addEventListener('dragstart', function (e) {
                    ebayHeroDragFrom = parseInt(card.getAttribute('data-idx'), 10);
                    card.classList.add('dragging');
                    e.dataTransfer.effectAllowed = 'move';
                });
                card.addEventListener('dragend', function () {
                    card.classList.remove('dragging');
                    ebayHeroDragFrom = null;
                });
                card.addEventListener('dragover', function (e) { e.preventDefault(); });
                card.addEventListener('drop', function (e) {
                    e.preventDefault();
                    if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
                        uploadEbayHeroFiles(e.dataTransfer.files);
                        return;
                    }
                    const to = parseInt(card.getAttribute('data-idx'), 10);
                    if (ebayHeroDragFrom === null || ebayHeroDragFrom === to) return;
                    const moved = ebayHeroUrls.splice(ebayHeroDragFrom, 1)[0];
                    ebayHeroUrls.splice(to, 0, moved);
                    renderEbayHeroGrid();
                });
            });

            grid.ondragover = function (e) {
                if (e.dataTransfer && Array.from(e.dataTransfer.types || []).indexOf('Files') !== -1) {
                    e.preventDefault();
                }
            };
            grid.ondrop = function (e) {
                if (!e.dataTransfer || !e.dataTransfer.files || !e.dataTransfer.files.length) return;
                e.preventDefault();
                uploadEbayHeroFiles(e.dataTransfer.files);
            };
        }

        function applyEbayHeroToSku(sku, images) {
            const patch = {
                ebay_hero_images: images,
                ebay_hero_image: images.length ? images[0].url : null,
                ebay_hero_thumb: images.length ? (images[0].thumb_url || images[0].url) : null,
                ebay_hero_image_count: images.length
            };
            const item = tableData.find(function (d) { return d.SKU === sku; });
            if (item) Object.assign(item, patch);
            if (table) {
                table.searchRows('SKU', '=', sku).forEach(function (row) { row.update(patch); });
            }
        }

        function openEbayHeroGallery(sku) {
            ebayHeroSku = sku || '';
            document.getElementById('ebayHeroSkuLabel').textContent = ebayHeroSku;
            const item = tableData.find(function (d) { return d.SKU === ebayHeroSku; }) || {};
            ebayHeroUrls = uniqueEbayUrls(ebayHeroImages(item).map(function (img) { return img.url; }));
            setEbayHeroMsg('');
            setEbayHeroErr('');
            renderEbayHeroGrid();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('ebayHeroGalleryModal')).show();
            fetchEbayHeroImages(true);
        }

        function fetchEbayHeroImages(silent) {
            if (!ebayHeroSku) return;
            setEbayHeroErr('');
            const fetchBtn = document.getElementById('ebayHeroFetchBtn');
            if (fetchBtn) fetchBtn.disabled = true;
            if (!silent) setEbayHeroMsg('Fetching from eBay…');
            fetch(ebayHeroFetchUrl + '?sku=' + encodeURIComponent(ebayHeroSku) + '&account=ebay', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (result) {
                const urls = uniqueEbayUrls(result.data.images || []);
                if (urls.length) {
                    ebayHeroUrls = urls;
                    renderEbayHeroGrid();
                    applyEbayHeroToSku(ebayHeroSku, urls.map(function (url) { return { url: url, thumb_url: url }; }));
                    setEbayHeroMsg('Loaded ' + urls.length + ' image' + (urls.length === 1 ? '' : 's') + ' from eBay.');
                    setEbayHeroErr('');
                    return;
                }
                if (!silent || !ebayHeroUrls.length) {
                    setEbayHeroMsg('');
                    setEbayHeroErr((result.data && result.data.message) || 'No eBay images for this SKU. Use Add images.');
                }
            })
            .catch(function () {
                if (!silent || !ebayHeroUrls.length) {
                    setEbayHeroMsg('');
                    setEbayHeroErr('Could not fetch eBay images.');
                }
            })
            .finally(function () {
                if (fetchBtn) fetchBtn.disabled = false;
            });
        }

        function uploadEbayHeroFiles(fileList) {
            if (!ebayHeroSku) {
                setEbayHeroErr('SKU is missing.');
                return;
            }
            const room = ebayHeroMax - ebayHeroUrls.length;
            if (room <= 0) {
                setEbayHeroErr('eBay allows 12 images. Remove one first.');
                return;
            }
            const files = Array.from(fileList || []).slice(0, room);
            if (!files.length) return;
            const form = new FormData();
            form.append('sku', ebayHeroSku);
            files.forEach(function (file) { form.append('files[]', file); });
            setEbayHeroMsg('Uploading…');
            setEbayHeroErr('');
            fetch(ebayHeroUploadUrl, {
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
                const uploaded = (result.data && (result.data.urls || result.data.images)) || [];
                const urls = uniqueEbayUrls(uploaded.map(function (item) {
                    return typeof item === 'string' ? item : (item && (item.url || item.cdn_url));
                }));
                if (!result.ok || !urls.length) {
                    setEbayHeroMsg('');
                    setEbayHeroErr((result.data && result.data.message) || 'Upload failed.');
                    return;
                }
                ebayHeroUrls = uniqueEbayUrls(ebayHeroUrls.concat(urls));
                renderEbayHeroGrid();
                setEbayHeroMsg('Added ' + urls.length + ' image' + (urls.length === 1 ? '' : 's') + '. Drag to arrange, then Save to eBay.');
                setEbayHeroErr('');
            })
            .catch(function () {
                setEbayHeroMsg('');
                setEbayHeroErr('Upload failed.');
            });
        }

        function saveEbayHeroImages() {
            if (!ebayHeroSku) return;
            if (!ebayHeroUrls.length) {
                setEbayHeroErr('Add at least one image first.');
                return;
            }
            const btn = document.getElementById('ebayHeroSaveBtn');
            if (btn) btn.disabled = true;
            setEbayHeroMsg('Saving to eBay…');
            setEbayHeroErr('');
            fetch(ebayHeroSaveUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ sku: ebayHeroSku, images: ebayHeroUrls, ebay_hero: true })
            })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (result) {
                if (!result.data.success) {
                    setEbayHeroMsg('');
                    setEbayHeroErr(result.data.message || 'Save failed.');
                    return;
                }
                const images = result.data.images || ebayHeroUrls.map(function (url) { return { url: url, thumb_url: url }; });
                applyEbayHeroToSku(ebayHeroSku, images);
                setEbayHeroMsg(result.data.message || 'Saved to eBay.');
                setEbayHeroErr('');
            })
            .catch(function () {
                setEbayHeroMsg('');
                setEbayHeroErr('Save failed.');
            })
            .finally(function () {
                if (btn) btn.disabled = false;
            });
        }

        function setupEbayHeroHandlers() {
            const fetchBtn = document.getElementById('ebayHeroFetchBtn');
            const addBtn = document.getElementById('ebayHeroAddBtn');
            const fileInput = document.getElementById('ebayHeroFileInput');
            const saveBtn = document.getElementById('ebayHeroSaveBtn');
            if (fetchBtn) fetchBtn.addEventListener('click', function () { fetchEbayHeroImages(false); });
            if (addBtn) addBtn.addEventListener('click', function () { fileInput && fileInput.click(); });
            if (fileInput) fileInput.addEventListener('change', function () {
                uploadEbayHeroFiles(fileInput.files);
                fileInput.value = '';
            });
            if (saveBtn) saveBtn.addEventListener('click', saveEbayHeroImages);
        }

        function barcodeCellHtml(row) {
            const sku = String(row.SKU || '').trim();
            const code = String(row.barcode || row.upc || '').trim();
            const img = String(row.barcode_image || '').trim();
            if (!code && !img) {
                return '<div class="ri-barcode-empty">No barcode</div>';
            }
            const media = code
                ? '<svg class="ri-barcode-svg" data-barcode="' + escapeHtml(code) + '"></svg>'
                : '<img src="' + escapeHtml(img) + '" alt="Barcode">';
            return '<div class="ri-barcode-open ri-barcode-square" role="button" tabindex="0" title="View barcode"'
                + ' data-sku="' + escapeHtml(sku) + '" data-code="' + escapeHtml(code) + '" data-img="' + escapeHtml(img) + '">'
                + '<div class="ri-barcode-sku">' + escapeHtml(sku || '—') + '</div>'
                + media
                + '<div class="ri-barcode-code">' + escapeHtml(code || '—') + '</div>'
                + '</div>';
        }

        function paintBarcodeSvg(svg, code, large) {
            if (!svg || !code || typeof JsBarcode !== 'function') return;
            const digits = String(code).replace(/\D/g, '');
            const format = (digits.length === 11 || digits.length === 12) ? 'UPC' : 'CODE128';
            const opts = {
                format: format,
                displayValue: false,
                margin: 0,
                width: large ? 2.6 : 1.4,
                height: large ? 140 : 48,
                background: '#ffffff',
                lineColor: '#111827'
            };
            try {
                JsBarcode(svg, format === 'UPC' ? digits : code, opts);
            } catch (e) {
                try {
                    JsBarcode(svg, code, Object.assign({}, opts, { format: 'CODE128' }));
                } catch (e2) {}
            }
        }

        function paintBarcodeSvgs(root) {
            (root || document).querySelectorAll('.ri-barcode-svg').forEach(function (svg) {
                paintBarcodeSvg(svg, (svg.getAttribute('data-barcode') || '').trim(), false);
            });
        }

        function openBarcodeModal(el) {
            const sku = el.getAttribute('data-sku') || '';
            const code = el.getAttribute('data-code') || '';
            const img = el.getAttribute('data-img') || '';
            document.getElementById('riBarcodeModalSku').textContent = sku || '—';
            document.getElementById('riBarcodeModalCode').textContent = code || '—';
            const media = document.getElementById('riBarcodeModalMedia');
            if (code && typeof JsBarcode === 'function') {
                media.innerHTML = '<svg id="riBarcodeModalSvg"></svg>';
                paintBarcodeSvg(document.getElementById('riBarcodeModalSvg'), code, true);
            } else if (img) {
                media.innerHTML = '<img src="' + escapeHtml(img) + '" alt="Barcode">';
            } else {
                media.innerHTML = '<span class="text-muted">No barcode</span>';
            }
            bootstrap.Modal.getOrCreateInstance(document.getElementById('riBarcodeModal')).show();
        }
        let riAiSaveTimer = null;
        let riAiLastSaved = @json($savedAiPrompt);
        let riAiTarget = 'raw';
        const riAiLoadingSkus = new Set();
        let riAiLogoFiles = [];
        let tableData = [];
        let table;
        let rawImageModal;
        let bulkSheetModal;
        let bulkDropboxModal;
        let riAiModal;
        let missingFilterOn = false;
        let imageFilterOn = false;
        let currentModalSource = 'manual';

        document.addEventListener('DOMContentLoaded', function () {
            rawImageModal = new bootstrap.Modal(document.getElementById('rawImageModal'));
            bulkSheetModal = new bootstrap.Modal(document.getElementById('bulkSheetModal'));
            bulkDropboxModal = new bootstrap.Modal(document.getElementById('bulkDropboxModal'));
            riAiModal = new bootstrap.Modal(document.getElementById('riAiModal'));
            initializeTabulator();
            setupSearchHandlers();
            setupModalHandlers();
            setupTableEvents();
            setupBulkHandlers();
            setupAiHandlers();
            setupEbayHeroHandlers();
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
                        updateSelectedCount();
                        hideLoader();
                        return response.data.filter(function (row) { return !isParentRow(row); });
                    }
                    hideLoader();
                    return [];
                },
                ajaxError: function () {
                    hideLoader();
                    alert('Failed to load ' + rawImagesPageTitle + ' data.');
                },
                renderComplete: function () {
                    paintBarcodeSvgs(document.getElementById('raw-images-table'));
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
                            return thumbHtml(cachedImageSrc(value), 'Product');
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
                        title: 'Barcode',
                        field: 'barcode',
                        width: 160,
                        hozAlign: 'center',
                        headerSort: false,
                        formatter: function (cell) {
                            const html = barcodeCellHtml(cell.getData());
                            const el = cell.getElement();
                            setTimeout(function () { paintBarcodeSvgs(el); }, 0);
                            return html;
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
                        title: 'Hero Image',
                        field: 'hero_image',
                        width: 90,
                        hozAlign: 'center',
                        headerSort: false,
                        formatter: function (cell) {
                            const row = cell.getData();
                            const value = cell.getValue();
                            if (!value) return '<span class="text-muted">—</span>';
                            const src = cachedImageSrc(value, row.hero_thumb);
                            return thumbHtml(src, 'Hero', value);
                        }
                    },
                    {
                        title: 'Ebay H Img',
                        field: 'ebay_hero_image',
                        width: 90,
                        hozAlign: 'center',
                        headerSort: false,
                        formatter: function (cell) {
                            return ebayHeroCellHtml(cell.getData());
                        }
                    },
                    {
                        title: rawImagesAiColumnTitle,
                        field: 'has_raw_ai_image',
                        width: 100,
                        hozAlign: 'center',
                        headerSort: false,
                        formatter: function (cell) {
                            return rawImageCellHtml(cell.getData(), 'ai');
                        }
                    },
                    {
                        title: rawImagesManualColumnTitle,
                        field: 'has_raw_image',
                        width: 90,
                        hozAlign: 'center',
                        headerSort: false,
                        formatter: function (cell) {
                            return rawImageCellHtml(cell.getData(), 'manual');
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
                    updateSelectedCount();
                    return;
                }
                if (e.target.classList.contains('ri-row-select')) {
                    updateSelectedCount();
                }
            });

            wrap.addEventListener('click', function (e) {
                const copyBtn = e.target.closest('.copy-sku-btn');
                if (copyBtn) {
                    e.preventDefault();
                    copyToClipboard(copyBtn.getAttribute('data-sku') || '', null, 'SKU copied.');
                    return;
                }
                const barcodeBtn = e.target.closest('.ri-barcode-open');
                if (barcodeBtn) {
                    e.preventDefault();
                    openBarcodeModal(barcodeBtn);
                    return;
                }
                const openBtn = e.target.closest('.js-open-raw-modal');
                if (openBtn) {
                    e.preventDefault();
                    openRawImageModal(openBtn.getAttribute('data-sku'), openBtn.getAttribute('data-source') || 'manual');
                    return;
                }
                const ebayHeroBtn = e.target.closest('.js-open-ebay-hero');
                if (ebayHeroBtn) {
                    e.preventDefault();
                    openEbayHeroGallery(ebayHeroBtn.getAttribute('data-sku') || '');
                    return;
                }
                const aiPromptBtn = e.target.closest('.js-open-ai-prompt');
                if (aiPromptBtn) {
                    e.preventDefault();
                    selectSkuForAi(aiPromptBtn.getAttribute('data-sku') || '');
                    openAiPromptModal('raw');
                }
            });
        }

        function setupSearchHandlers() {
            document.getElementById('parentSearch').addEventListener('input', applyFilters);
            document.getElementById('skuSearch').addEventListener('input', applyFilters);
            document.getElementById('filterRawImages').addEventListener('change', function () {
                missingFilterOn = this.value === 'missing';
                imageFilterOn = this.value === 'available';
                syncBadges();
                applyFilters();
            });
            document.getElementById('available-images-badge').addEventListener('click', function () {
                const select = document.getElementById('filterRawImages');
                select.value = select.value === 'available' ? 'all' : 'available';
                missingFilterOn = false;
                imageFilterOn = select.value === 'available';
                syncBadges();
                applyFilters();
            });
            document.getElementById('missing-raw-images-badge').addEventListener('click', function () {
                const select = document.getElementById('filterRawImages');
                select.value = select.value === 'missing' ? 'all' : 'missing';
                missingFilterOn = select.value === 'missing';
                imageFilterOn = false;
                syncBadges();
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
                    return;
                }
                const copyBtn = e.target.closest('.js-copy-image-url');
                if (copyBtn) {
                    e.preventDefault();
                    copyToClipboard(copyBtn.getAttribute('data-url') || '', 'Image URL copied.');
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

        function resolveModalSource(source) {
            if (source === 'ai') return source;
            return 'manual';
        }

        function isAiModalSource(source) {
            return source === 'ai';
        }

        function openRawImageModal(sku, source) {
            currentModalSource = resolveModalSource(source);
            const item = tableData.find(function (d) { return d.SKU === sku; });
            const images = item ? rawImageSourceImages(item, currentModalSource) : [];
            document.getElementById('modalSku').value = sku || '';
            document.getElementById('modalSkuLabel').textContent = sku || '';
            document.getElementById('modalSkuText').textContent = sku || '';
            document.getElementById('modalParentText').textContent = (item && item.Parent) ? item.Parent : '—';
            const kindLabel = document.getElementById('modalKindLabel');
            if (kindLabel) {
                kindLabel.textContent = currentModalSource === 'ai' ? rawImagesAiColumnTitle : rawImagesPageTitle;
            }
            const hint = document.getElementById('rawUploadHint');
            if (hint) {
                hint.textContent = currentModalSource === 'ai'
                    ? 'These images are created by the AI button on selected rows.'
                    : 'JPG, PNG, WEBP, or camera RAW files. Max 50 MB each.';
            }
            renderModalGrid(images);
            setUploadMsg('');
            setUploadErr('');
            rawImageModal.show();
        }

        function renderModalGrid(images) {
            const grid = document.getElementById('rawImageGrid');
            const list = Array.isArray(images) ? images : [];
            let html = '';

            list.forEach(function (img) {
                const url = img.url || '';
                const name = img.name || 'image';
                html += '<div class="ri-card">';
                html += '<button type="button" class="ri-card-del" data-id="' + escapeHtml(String(img.id)) + '" title="Remove"><i class="fas fa-times"></i></button>';
                if (img.previewable && url) {
                    const thumb = cachedImageSrc(url, img.thumb_url);
                    html += '<a href="' + escapeHtml(url) + '" target="_blank"><img src="' + escapeHtml(thumb) + '" alt=""></a>';
                } else {
                    html += '<div class="ri-card-file"><i class="fas fa-file-image fa-2x"></i><span class="small">File</span></div>';
                }
                html += '<div class="ri-card-name" title="' + escapeHtml(name) + '">' + escapeHtml(name) + '</div>';
                html += '<div class="ri-card-actions">';
                html += '<a href="' + escapeHtml(url) + '" download="' + escapeHtml(name) + '" title="Download"><i class="fas fa-download"></i> Save</a>';
                html += '<button type="button" class="js-copy-image-url" data-url="' + escapeHtml(url) + '" title="Copy URL"><i class="fas fa-copy"></i> Copy</button>';
                html += '</div></div>';
            });

            if (!isAiModalSource(currentModalSource)) {
                const plusClass = list.length ? 'ri-plus-tile ri-plus-tile-sm' : 'ri-plus-tile';
                const plusLabel = list.length ? 'Add more' : 'Add ' + rawImagesManualColumnTitle;
                html += '<div class="' + plusClass + '" title="' + plusLabel + '">'
                    + '<span class="ri-plus-icon">+</span>'
                    + '<span class="small fw-semibold">' + plusLabel + '</span>'
                    + '</div>';
            }

            grid.innerHTML = html;
        }

        function uploadRawFiles(fileList) {
            if (isAiModalSource(currentModalSource)) {
                setUploadErr('AI images are created with the AI button.');
                return;
            }
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
                applyImagesToSku(sku, result.data.images || [], 'manual');
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
                applyImagesToSku(sku, data.images || [], data.source || currentModalSource);
            })
            .catch(function (err) {
                setUploadErr('Delete failed: ' + err.message);
            });
        }

        function applyImagesToSku(sku, images, source) {
            const resolved = source || currentModalSource;
            const item = tableData.find(function (d) { return d.SKU === sku; });
            const patches = {
                ai: {
                    raw_ai_images: images,
                    raw_ai_image_count: images.length,
                    has_raw_ai_image: images.length > 0,
                    raw_ai_image_url: images.length ? images[0].url : null
                },
                manual: {
                    raw_images: images,
                    raw_image_count: images.length,
                    has_raw_image: images.length > 0,
                    raw_image_url: images.length ? images[0].url : null
                }
            };
            const patch = patches[resolved] || patches.manual;
            if (item) {
                Object.assign(item, patch);
            }
            if (table) {
                const rows = table.searchRows('SKU', '=', sku);
                rows.forEach(function (row) {
                    row.update(patch);
                });
            }
            if ((source || currentModalSource) === currentModalSource) {
                renderModalGrid(images);
            }
            updateCounts();
        }

        function applyFilters() {
            if (!table) return;

            const parentFilter = document.getElementById('parentSearch').value.toLowerCase();
            const skuFilter = document.getElementById('skuSearch').value.toLowerCase();
            const filterMode = document.getElementById('filterRawImages').value;
            const filters = [
                function (data) { return !isParentRow(data); }
            ];

            if (parentFilter) {
                filters.push({ field: 'Parent', type: 'like', value: parentFilter });
            }
            if (skuFilter) {
                filters.push({ field: 'SKU', type: 'like', value: skuFilter });
            }
            if (filterMode === 'available') {
                filters.push(function (data) {
                    if (data.SKU && String(data.SKU).toUpperCase().includes('PARENT')) return false;
                    return hasAvailableImage(data);
                });
            }
            if (filterMode === 'missing') {
                filters.push(function (data) {
                    if (data.SKU && String(data.SKU).toUpperCase().includes('PARENT')) return false;
                    if (!hasPositiveInv(data.shopify_inv)) return false;
                    return !data.has_raw_image;
                });
            }

            table.clearFilter();
            if (filters.length) table.setFilter(filters);
        }

        function hasAvailableImage(item) {
            return !!(item && (item.has_raw_image || item.has_raw_ai_image));
        }

        function updateCounts() {
            const parentSet = new Set();
            let skuCount = 0;
            let missing = 0;
            let available = 0;

            tableData.forEach(function (item) {
                if (item.Parent) parentSet.add(item.Parent);
                if (item.SKU && !String(item.SKU).toUpperCase().includes('PARENT')) {
                    skuCount++;
                    if (hasAvailableImage(item)) available++;
                    if (hasPositiveInv(item.shopify_inv) && !item.has_raw_image) missing++;
                }
            });

            document.getElementById('parentSearch').placeholder = 'Search Parent... (' + parentSet.size + ')';
            document.getElementById('skuSearch').placeholder = 'Search SKU... (' + skuCount + ')';
            document.getElementById('skuCountBadge').textContent = skuCount;
            document.getElementById('availableImagesCount').textContent = available;
            document.getElementById('missingRawImagesCount').textContent = missing;
            syncBadges();
        }

        function syncMissingBadge() {
            syncBadges();
        }

        function syncBadges() {
            const missingEl = document.getElementById('missing-raw-images-badge');
            const imageEl = document.getElementById('available-images-badge');
            if (missingEl) missingEl.classList.toggle('active-filter', missingFilterOn);
            if (imageEl) imageEl.classList.toggle('active-filter', imageFilterOn);
        }

        function hideLoader() {
            const loader = document.getElementById('rainbow-loader');
            if (loader) loader.style.display = 'none';
        }

        function showAiProcessLoader(skus) {
            const el = document.getElementById('riAiProcessLoader');
            const titleEl = document.getElementById('riAiProcessLoaderTitle');
            const textEl = document.getElementById('riAiProcessLoaderText');
            const n = (skus || []).length;
            if (titleEl) titleEl.textContent = 'Generating ' + rawImagesAiColumnTitle + '…';
            if (textEl) {
                textEl.textContent = n
                    ? ('Working on ' + n + ' SKU' + (n === 1 ? '' : 's') + '. Please wait — this can take a minute.')
                    : 'Please wait. This can take a minute.';
            }
            if (el) el.style.display = 'flex';
        }

        function hideAiProcessLoader() {
            const el = document.getElementById('riAiProcessLoader');
            if (el) el.style.display = 'none';
        }

        function setupBulkHandlers() {
            document.getElementById('bulkFromSheetBtn').addEventListener('click', function (e) {
                e.preventDefault();
                document.getElementById('bulkSheetResult').innerHTML = '';
                bulkSheetModal.show();
            });
            document.getElementById('bulkFromDropboxBtn').addEventListener('click', function (e) {
                e.preventDefault();
                document.getElementById('bulkDropboxResult').innerHTML = '';
                bulkDropboxModal.show();
            });
            document.getElementById('bulkSheetSubmitBtn').addEventListener('click', function () {
                submitBulkImport('sheet', {
                    file: document.getElementById('bulkSheetFile').files[0] || null,
                    sheetUrl: document.getElementById('bulkSheetUrl').value.trim(),
                    resultEl: document.getElementById('bulkSheetResult'),
                    button: this
                });
            });
            document.getElementById('bulkDropboxSubmitBtn').addEventListener('click', function () {
                submitBulkImport('dropbox', {
                    file: document.getElementById('bulkDropboxFile').files[0] || null,
                    urls: document.getElementById('bulkDropboxUrls').value.trim(),
                    resultEl: document.getElementById('bulkDropboxResult'),
                    button: this
                });
            });
            document.getElementById('downloadSelectedBtn').addEventListener('click', downloadSelectedImages);
            document.getElementById('copySelectedSkusBtn').addEventListener('click', function (e) {
                e.preventDefault();
                copySelectedSkus();
            });
            document.getElementById('copySelectedUrlsBtn').addEventListener('click', function (e) {
                e.preventDefault();
                copySelectedUrls();
            });
        }

        function selectedRowsForAi() {
            return selectedSkus().map(function (sku) {
                const item = tableData.find(function (d) { return d.SKU === sku; }) || {};
                return {
                    sku: sku,
                    hero_image: item.hero_image || item.image_path || ''
                };
            });
        }

        function updateAiSelectionHint() {
            const hintEl = document.getElementById('riAiSelectedHint');
            const n = selectedSkus().length;
            if (!hintEl) return;
            if (n === 0) {
                hintEl.className = 'small mt-2 text-danger';
                hintEl.textContent = 'Select one or more rows in the table. Run only works on selected images.';
                return;
            }
            hintEl.className = 'small mt-2 text-muted';
            hintEl.textContent = 'This will run only on ' + n + ' selected image' + (n === 1 ? '' : 's') + '.';
        }

        function applyAiBySku(bySku, target) {
            if (!bySku || typeof bySku !== 'object') return;
            Object.keys(bySku).forEach(function (sku) {
                applyImagesToSku(sku, bySku[sku] || [], 'ai');
            });
        }

        function selectSkuForAi(sku) {
            if (!sku) return;
            document.querySelectorAll('.ri-row-select').forEach(function (cb) {
                if ((cb.getAttribute('data-sku') || '') === sku) {
                    cb.checked = true;
                }
            });
            updateSelectedCount();
        }

        function openAiPromptModal(target) {
            const promptEl = document.getElementById('riAiPrompt');
            const resultEl = document.getElementById('riAiResult');
            const titleEl = document.getElementById('riAiModalTitle');
            const hintEl = document.getElementById('riAiFormHint');
            riAiTarget = 'raw';
            if (titleEl) titleEl.textContent = rawImagesIsHero2 ? 'Hero Image 2 AI prompt' : 'AI prompt';
            if (hintEl) {
                hintEl.textContent = rawImagesIsHero2
                    ? 'Edits are saved automatically. Gemini generates a Hero Image 2 AI image for selected rows only. Ctrl / ⌘ + Enter to save and close.'
                    : 'Edits are saved automatically. Gemini generates a raw-shoot image for selected rows only. Ctrl / ⌘ + Enter to save and close.';
            }
            promptEl.value = riAiLastSaved;
            if (resultEl) resultEl.innerHTML = '';
            updateAiSelectionHint();
            riAiModal.show();
            setTimeout(function () { promptEl.focus(); }, 200);
        }

        function renderAiLogoPreview() {
            const wrap = document.getElementById('riAiLogoPreview');
            if (!wrap) return;
            wrap.innerHTML = riAiLogoFiles.map(function (file, idx) {
                const url = URL.createObjectURL(file);
                return '<div class="ri-ai-logo-chip">'
                    + '<img src="' + url + '" alt="Logo ' + (idx + 1) + '">'
                    + '<button type="button" data-i="' + idx + '" title="Remove">&times;</button>'
                    + '</div>';
            }).join('');
            wrap.querySelectorAll('button').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const i = parseInt(btn.getAttribute('data-i'), 10);
                    riAiLogoFiles.splice(i, 1);
                    renderAiLogoPreview();
                });
            });
        }

        function addAiLogoFiles(fileList) {
            const room = 6 - riAiLogoFiles.length;
            if (room <= 0) return;
            Array.from(fileList || []).slice(0, room).forEach(function (file) {
                if (!file || !file.type || file.type.indexOf('image/') !== 0) return;
                riAiLogoFiles.push(file);
            });
            renderAiLogoPreview();
        }

        function setupAiHandlers() {
            const promptEl = document.getElementById('riAiPrompt');
            const resultEl = document.getElementById('riAiResult');
            const submitBtn = document.getElementById('riAiSubmitBtn');

            document.getElementById('riAiBtn').addEventListener('click', function () {
                openAiPromptModal('raw');
            });
            const logoBtn = document.getElementById('riAiLogoBtn');
            const logoInput = document.getElementById('riAiLogoInput');
            if (logoBtn && logoInput) {
                logoBtn.addEventListener('click', function () { logoInput.click(); });
                logoInput.addEventListener('change', function () {
                    addAiLogoFiles(logoInput.files);
                    logoInput.value = '';
                });
            }

            promptEl.addEventListener('input', function () {
                clearTimeout(riAiSaveTimer);
                riAiSaveTimer = setTimeout(function () {
                    saveAiPrompt(promptEl.value, false).catch(function () {});
                }, 500);
            });

            promptEl.addEventListener('keydown', function (e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    e.preventDefault();
                    saveAndCloseAiPrompt();
                }
            });

            submitBtn.addEventListener('click', function () {
                saveAndCloseAiPrompt();
            });

            function saveAndCloseAiPrompt() {
                const prompt = (promptEl.value || '').trim();
                const selected = selectedRowsForAi();
                updateAiSelectionHint();
                if (prompt.length < 2) {
                    resultEl.innerHTML = '<div class="alert alert-danger py-2 mb-0">Enter a prompt first.</div>';
                    promptEl.focus();
                    return;
                }
                if (selected.length > 8) {
                    resultEl.innerHTML = '<div class="alert alert-danger py-2 mb-0">Select up to 8 SKUs at a time.</div>';
                    return;
                }

                submitBtn.disabled = true;
                resultEl.innerHTML = '<div class="text-muted"><i class="fas fa-spinner fa-spin me-1"></i>Starting…</div>';
                if (selected.length) {
                    showAiProcessLoader(selected.map(function (row) { return row.sku; }));
                }

                saveAiPrompt(prompt, true)
                    .catch(function () {})
                    .then(function () {
                        resultEl.innerHTML = '';
                        riAiModal.hide();
                        if (!selected.length) {
                            hideAiProcessLoader();
                            return;
                        }
                        return runAiOnSelected(selected, prompt);
                    })
                    .catch(function (err) {
                        hideAiProcessLoader();
                        resultEl.innerHTML = '<div class="alert alert-danger py-2 mb-0">' + escapeHtml(err.message || 'AI request failed.') + '</div>';
                    })
                    .finally(function () {
                        submitBtn.disabled = false;
                    });
            }
        }

        function setAiGenerating(skus, loading, target) {
            const loadingSet = riAiLoadingSkus;
            const field = 'ai_generating';
            (skus || []).forEach(function (sku) {
                if (!sku) return;
                if (loading) {
                    loadingSet.add(sku);
                } else {
                    loadingSet.delete(sku);
                }
                const item = tableData.find(function (d) { return d.SKU === sku; });
                if (item) {
                    item[field] = !!loading;
                }
                if (table) {
                    const patch = {};
                    patch[field] = !!loading;
                    table.searchRows('SKU', '=', sku).forEach(function (row) {
                        row.update(patch);
                    });
                }
            });
            if (loading && table && skus[0]) {
                const first = table.searchRows('SKU', '=', skus[0])[0];
                if (first && first.getElement) {
                    first.getElement().scrollIntoView({ block: 'center', behavior: 'smooth' });
                }
            }
        }

        function runAiOnSelected(selected, prompt) {
            const target = 'raw';
            const skus = (selected || []).map(function (row) { return row.sku; }).filter(Boolean);
            const aiBtn = document.getElementById('riAiBtn');
            showAiProcessLoader(skus);
            setAiGenerating(skus, true, target);
            if (aiBtn) aiBtn.disabled = true;
            const form = new FormData();
            form.append('prompt', prompt);
            form.append('selected', JSON.stringify(selected));
            riAiLogoFiles.forEach(function (file) { form.append('logos[]', file); });
            return fetch(rawImagesAiPromptUrl, {
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
                hideAiProcessLoader();
                const d = result.data || {};
                applyAiBySku(d.by_sku, target);
                const errors = Array.isArray(d.errors) ? d.errors : [];
                if (!result.ok || !d.success) {
                    const extra = errors.length ? '\n' + errors.join('\n') : '';
                    throw new Error((d.message || d.reply || 'AI request failed.') + extra);
                }
                applyAiAction(d.action || {});
                if (errors.length) {
                    alert((d.reply || 'Done.') + '\n' + errors.join('\n'));
                    return;
                }
                if (d.reply) {
                    alert(d.reply);
                }
            })
            .catch(function (err) {
                hideAiProcessLoader();
                alert(err.message || 'AI request failed.');
            })
            .finally(function () {
                setAiGenerating(skus, false, target);
                if (aiBtn) aiBtn.disabled = false;
                hideAiProcessLoader();
            });
        }

        function saveAiPrompt(prompt, force) {
            const next = String(prompt || '');
            if (!force && next === riAiLastSaved) {
                return Promise.resolve();
            }
            riAiLastSaved = next;
            const body = { prompt: next };
            return fetch(rawImagesAiPromptSaveUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(body)
            }).then(function (res) {
                if (!res.ok && force) {
                    throw new Error('Could not save the prompt.');
                }
            }).catch(function (err) {
                if (force) {
                    throw err;
                }
            });
        }

        function applyAiAction(action) {
            const type = action && action.type ? String(action.type) : 'none';
            const query = action && action.query ? String(action.query) : '';
            const field = action && action.field ? String(action.field) : 'general';

            if (type === 'filter_missing') {
                document.getElementById('filterRawImages').value = 'missing';
                missingFilterOn = true;
                imageFilterOn = false;
                syncBadges();
                applyFilters();
                return;
            }
            if (type === 'filter_all') {
                document.getElementById('filterRawImages').value = 'all';
                document.getElementById('parentSearch').value = '';
                document.getElementById('skuSearch').value = '';
                missingFilterOn = false;
                imageFilterOn = false;
                syncBadges();
                applyFilters();
                return;
            }
            if (type === 'search' && query) {
                document.getElementById('parentSearch').value = field === 'parent' ? query : '';
                document.getElementById('skuSearch').value = (field === 'sku' || field === 'general') ? query : '';
                applyFilters();
                return;
            }
            if (type === 'open_sheet') {
                document.getElementById('bulkSheetResult').innerHTML = '';
                bulkSheetModal.show();
                return;
            }
            if (type === 'open_dropbox') {
                document.getElementById('bulkDropboxResult').innerHTML = '';
                bulkDropboxModal.show();
                return;
            }
            if (type === 'download_selected') {
                downloadSelectedImages();
                return;
            }
            if (type === 'copy_skus') {
                copySelectedSkus();
                return;
            }
            if (type === 'copy_urls') {
                copySelectedUrls();
                return;
            }
            if (type === 'copy_missing') {
                copyMissingSkus();
            }
        }

        function copyMissingSkus() {
            const skus = tableData.filter(function (item) {
                if (!item.SKU || String(item.SKU).toUpperCase().includes('PARENT')) return false;
                return hasPositiveInv(item.shopify_inv) && !item.has_raw_image;
            }).map(function (item) { return item.SKU; });
            if (!skus.length) {
                alert('No missing SKUs with inventory.');
                return;
            }
            copyToClipboard(skus.join('\n'), null, skus.length + ' missing SKU(s) copied.');
        }

        function submitBulkImport(source, opts) {
            const form = new FormData();
            form.append('source', source);
            if (opts.file) form.append('file', opts.file);
            if (opts.sheetUrl) form.append('sheet_url', opts.sheetUrl);
            if (opts.urls) form.append('urls', opts.urls);

            if (!opts.file && !opts.sheetUrl && !opts.urls) {
                opts.resultEl.innerHTML = '<div class="alert alert-danger py-2 mb-0">Add a file, sheet URL, or Dropbox links first.</div>';
                return;
            }

            opts.button.disabled = true;
            opts.resultEl.innerHTML = '<div class="text-muted">Importing images… this can take a minute.</div>';

            fetch(rawImagesBulkImportUrl, {
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
                const d = result.data || {};
                const errors = Array.isArray(d.errors) ? d.errors : [];
                let html = '<div class="alert ' + (d.success ? 'alert-success' : 'alert-danger') + ' py-2 mb-0">';
                html += escapeHtml(d.message || (d.success ? 'Imported.' : 'Import failed.'));
                if (d.imported != null) html += ' Imported: ' + d.imported + ', skipped: ' + (d.skipped || 0) + '.';
                if (errors.length) {
                    html += '<ul class="mb-0 mt-2 ps-3">';
                    errors.forEach(function (err) { html += '<li>' + escapeHtml(err) + '</li>'; });
                    html += '</ul>';
                }
                html += '</div>';
                opts.resultEl.innerHTML = html;
                if (d.by_sku) {
                    Object.keys(d.by_sku).forEach(function (sku) {
                        applyImagesToSku(sku, d.by_sku[sku] || [], 'manual');
                    });
                }
            })
            .catch(function (err) {
                opts.resultEl.innerHTML = '<div class="alert alert-danger py-2 mb-0">Import failed: ' + escapeHtml(err.message) + '</div>';
            })
            .finally(function () {
                opts.button.disabled = false;
            });
        }

        function selectedSkus() {
            return Array.from(document.querySelectorAll('.ri-row-select:checked'))
                .map(function (cb) { return cb.getAttribute('data-sku') || ''; })
                .filter(Boolean);
        }

        function updateSelectedCount() {
            const el = document.getElementById('selectedCountBadge');
            if (el) el.textContent = 'Selected: ' + selectedSkus().length;
            updateAiSelectionHint();
        }

        function downloadSelectedImages() {
            const skus = selectedSkus();
            if (!skus.length) {
                alert('Select at least one SKU.');
                return;
            }

            fetch(rawImagesDownloadUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ skus: skus })
            })
            .then(function (res) {
                const type = res.headers.get('Content-Type') || '';
                if (type.indexOf('application/json') !== -1) {
                    return res.json().then(function (data) {
                        throw new Error(data.message || 'Download failed.');
                    });
                }
                if (!res.ok) throw new Error('Download failed.');
                return res.blob();
            })
            .then(function (blob) {
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = rawImagesZipFileName;
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
            })
            .catch(function (err) {
                alert(err.message || 'Download failed.');
            });
        }

        function copySelectedSkus() {
            const skus = selectedSkus();
            if (!skus.length) {
                alert('Select at least one SKU.');
                return;
            }
            copyToClipboard(skus.join('\n'), null, skus.length + ' SKU(s) copied.');
        }

        function copySelectedUrls() {
            const skus = selectedSkus();
            if (!skus.length) {
                alert('Select at least one SKU.');
                return;
            }
            const urls = [];
            skus.forEach(function (sku) {
                const item = tableData.find(function (d) { return d.SKU === sku; });
                const images = item && Array.isArray(item.raw_images) ? item.raw_images : [];
                const aiImages = item && Array.isArray(item.raw_ai_images) ? item.raw_ai_images : [];
                images.concat(aiImages).forEach(function (img) {
                    if (img && img.url) urls.push(img.url);
                });
            });
            if (!urls.length) {
                alert('No raw image URLs on the selected SKUs.');
                return;
            }
            copyToClipboard(urls.join('\n'), null, urls.length + ' URL(s) copied.');
        }

        function hasPositiveInv(value) {
            const inv = parseFloat(value);
            return !isNaN(inv) && inv > 0;
        }

        function flashAction(msg) {
            const el = document.getElementById('selectedCountBadge');
            if (!el) return;
            el.textContent = msg;
            setTimeout(updateSelectedCount, 1800);
        }

        function copyToClipboard(text, modalMsg, toolbarMsg) {
            if (!text) return;
            const done = function () {
                if (modalMsg) setUploadMsg(modalMsg);
                if (toolbarMsg) flashAction(toolbarMsg);
            };
            navigator.clipboard.writeText(text).then(done).catch(function () {
                const ta = document.createElement('textarea');
                ta.value = text;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                ta.remove();
                done();
            });
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
