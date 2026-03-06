@extends('layouts.vertical', ['title' => 'Amazon Listings - Listing Master', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        :root {
            --amz-nav: #232f3e;
            --amz-orange: #ff9900;
            --amz-blue: #146eb4;
            --amz-link: #007185;
            --amz-border: #c7cacb;
            --amz-border-light: #e3e6e6;
            --amz-bg: #eaeded;
            --amz-text: #111;
            --amz-text-secondary: #565959;
        }
        body { font-family: 'Amazon Ember', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; background-color: var(--amz-bg) !important; color: var(--amz-text); }

        /* Amazon Seller Central header - matches Amazon's top bar */
        .amz-page-header {
            background: var(--amz-nav);
            color: #fff;
            padding: 1rem 1.5rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            border-radius: 2px;
        }
        .amz-page-header h1 { margin: 0; font-size: 1.25rem; font-weight: 700; letter-spacing: -0.02em; }
        .amz-page-header .sub { opacity: 0.9; font-size: 0.75rem; margin-top: 0.15rem; }
        .btn-import-amazon {
            background: var(--amz-orange);
            color: #232f3e !important;
            border: 1px solid #e47911;
            padding: 0.4rem 1rem;
            border-radius: 2px;
            font-weight: 600;
            min-width: 150px;
            font-size: 0.8125rem;
        }
        .btn-import-amazon:hover { background: #f0c14b; border-color: #e47911; color: #232f3e !important; }
        .btn-import-amazon:disabled { opacity: 0.6; cursor: not-allowed; }

        /* Amazon inventory dashboard summary boxes */
        .amz-stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 1.25rem; }
        .amz-stat-card {
            background: #fff;
            border: 1px solid var(--amz-border);
            border-radius: 2px;
            padding: 1rem 1.25rem;
        }
        .amz-stat-card .value { font-size: 1.5rem; font-weight: 700; color: var(--amz-text); line-height: 1.2; }
        .amz-stat-card .label { font-size: 0.6875rem; color: var(--amz-text-secondary); margin-top: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em; }

        /* Amazon Manage Inventory table container */
        .amz-table-card {
            background: #fff;
            border: 1px solid var(--amz-border);
            border-radius: 2px;
            overflow: hidden;
        }
        .amz-table-card .card-body { padding: 0; }
        .amz-table-section-title {
            background: #f3f3f3;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            font-weight: 700;
            color: var(--amz-text);
            border-bottom: 1px solid var(--amz-border);
        }
        .tabulator.amz-tabulator .tabulator-header .tabulator-col {
            background: #f3f3f3;
            color: var(--amz-text);
            border-right: 1px solid var(--amz-border-light);
        }
        .tabulator.amz-tabulator .tabulator-header .tabulator-col .tabulator-col-content { padding: 10px 12px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; }
        .tabulator.amz-tabulator .tabulator-row.tabulator-row-even { background: #fff; }
        .tabulator.amz-tabulator .tabulator-row:nth-child(even) { background: #f9f9f9; }
        .tabulator.amz-tabulator .tabulator-row:hover { background: #f0f7fc !important; }
        .tabulator.amz-tabulator .tabulator-row .tabulator-cell { padding: 10px 12px; }
        .tabulator.amz-tabulator .tabulator-cell { border-right: 1px solid var(--amz-border-light); font-size: 0.8125rem; color: var(--amz-text); }
        .tabulator.amz-tabulator .tabulator-footer { border-top: 1px solid var(--amz-border); background: #f3f3f3; }
        #amz-listings-table { min-height: 420px; }

        /* Amazon toolbar above table */
        .amz-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.75rem;
            padding: 0.6rem 1rem;
            background: #f6f6f6;
            border-bottom: 1px solid var(--amz-border-light);
        }
        .amz-toolbar .btn-outline-secondary {
            border-color: var(--amz-border);
            color: var(--amz-text);
            font-size: 0.75rem;
            padding: 0.3rem 0.65rem;
        }
        .amz-toolbar .btn-outline-secondary:hover {
            background: var(--amz-blue);
            border-color: var(--amz-blue);
            color: #fff;
        }
        .amz-toolbar .amz-page-num { font-size: 0.75rem; color: var(--amz-text-secondary); }

        .amz-toast {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: none;
        }
        .amz-toast.show { display: block; animation: slideIn 0.3s ease; }
        .amz-toast.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .amz-toast.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .amz-toast.info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        .amz-loading-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(255,255,255,0.9);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10;
            backdrop-filter: blur(2px);
        }
        .amz-loading-overlay.show { display: flex; }
        .amz-loading-overlay .spinner-border { color: var(--amz-blue) !important; }
        .amz-table-wrap { position: relative; }

        /* Amazon view details action button */
        .amz-dot-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 26px;
            height: 26px;
            border-radius: 2px;
            background: var(--amz-blue);
            color: #fff;
            font-weight: bold;
            cursor: pointer;
            font-size: 13px;
        }
        .amz-dot-btn:hover { background: #185a8e; }

        /* Seller Central-style detail stack (slide-out panel) */
        .amz-stack-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.3);
            z-index: 1050;
            display: none;
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        .amz-stack-overlay.show { display: block; opacity: 1; }
        .amz-stack-panel {
            position: fixed;
            top: 0;
            right: 0;
            width: 720px;
            max-width: 100%;
            height: 100%;
            background: #fff;
            box-shadow: -4px 0 20px rgba(0,0,0,0.15);
            z-index: 1051;
            display: flex;
            flex-direction: column;
            transform: translateX(100%);
            transition: transform 0.25s ease;
        }
        .amz-stack-overlay.show .amz-stack-panel { transform: translateX(0); }
        .amz-stack-header {
            background: var(--amz-nav);
            color: #fff;
            padding: 1rem 1.25rem;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .amz-stack-header h2 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
        }
        .amz-stack-close {
            background: transparent;
            border: none;
            color: #fff;
            font-size: 1.5rem;
            line-height: 1;
            cursor: pointer;
            padding: 0 4px;
            opacity: 0.9;
        }
        .amz-stack-close:hover { opacity: 1; }
        .amz-stack-body {
            flex: 1;
            overflow-y: auto;
            padding: 1rem 1.25rem;
        }
        .amz-stack-section {
            margin-bottom: 1.25rem;
        }
        .amz-stack-section-title {
            font-size: 0.6875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--amz-text-secondary);
            margin-bottom: 0.5rem;
            padding-bottom: 0.25rem;
            border-bottom: 1px solid var(--amz-border-light);
        }
        .amz-stack-row {
            display: flex;
            padding: 0.35rem 0;
            font-size: 0.875rem;
            border-bottom: 1px solid #f0f0f0;
        }
        .amz-stack-row:last-child { border-bottom: none; }
        .amz-stack-label {
            flex: 0 0 45%;
            color: #555;
        }
        .amz-stack-value {
            flex: 1;
            word-break: break-word;
        }
        /* Detail stack: full-screen width, two columns */
        .amz-stack-panel {
            width: 100%;
            max-width: 1800px;
            height: 100vh;
            top: 0;
            left: 50%;
            right: auto;
            transform: translateX(-50%) scale(0.98);
            border-radius: 0;
            overflow: hidden;
        }
        .amz-stack-overlay.show .amz-stack-panel { transform: translateX(-50%) scale(1); }
        .amz-stack-header .amz-stack-copy-btns { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
        .amz-stack-copy-btn {
            padding: 4px 8px;
            font-size: 0.7rem;
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.4);
            color: #fff;
            border-radius: 4px;
            cursor: pointer;
        }
        .amz-stack-copy-btn:hover { background: rgba(255,255,255,0.3); }
        .amz-stack-body { display: flex; flex: 1; overflow: hidden; padding: 0; }
        .amz-stack-gallery-col {
            width: 60%;
            min-width: 0;
            display: flex;
            flex-direction: column;
            background: #fff;
            border-right: 1px solid var(--amz-border);
            padding: 1.25rem;
        }
        .amz-stack-details-col {
            width: 40%;
            min-width: 0;
            overflow-y: auto;
            padding: 1rem 1.25rem;
            background: #fafafa;
        }
        /* Amazon-style gallery: thumb strip (left) + main image */
        .amz-gallery-wrap {
            display: flex;
            gap: 12px;
            flex: 1;
            min-height: 280px;
        }
        .amz-gallery-thumbs {
            flex: 0 0 56px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            overflow-y: auto;
            padding: 2px 0;
        }
        .amz-gallery-thumbs::-webkit-scrollbar { width: 4px; }
        .amz-gallery-thumbs::-webkit-scrollbar-thumb { background: #ccc; border-radius: 2px; }
        .amz-thumb {
            width: 52px;
            height: 52px;
            flex-shrink: 0;
            border: 2px solid #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
            background: #f0f0f0;
            cursor: pointer;
            position: relative;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .amz-thumb:hover { border-color: var(--amz-blue); box-shadow: 0 0 0 1px var(--amz-blue); }
        .amz-thumb.active { border-color: var(--amz-orange); box-shadow: 0 0 0 2px var(--amz-orange); }
        .amz-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .amz-thumb.skeleton { background: linear-gradient(90deg, #eee 25%, #f5f5f5 50%, #eee 75%); background-size: 200% 100%; animation: amz-skeleton 1.2s ease-in-out infinite; }
        .amz-thumb.video-thumb::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.4);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .amz-thumb .amz-play-icon {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 28px;
            height: 28px;
            background: rgba(255,255,255,0.95);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--amz-blue);
            font-size: 12px;
            pointer-events: none;
        }
        .amz-thumb .amz-video-badge {
            position: absolute;
            bottom: 2px;
            left: 2px;
            font-size: 9px;
            font-weight: 700;
            background: rgba(0,0,0,0.7);
            color: #fff;
            padding: 1px 4px;
            border-radius: 2px;
        }
        .amz-gallery-main-wrap {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .amz-gallery-main {
            position: relative;
            width: 100%;
            max-width: 500px;
            aspect-ratio: 1;
            max-height: 500px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            overflow: hidden;
            background: #f8f8f8;
        }
        .amz-gallery-main.skeleton { background: linear-gradient(90deg, #eee 25%, #f5f5f5 50%, #eee 75%); background-size: 200% 100%; animation: amz-skeleton 1.2s ease-in-out infinite; }
        @keyframes amz-skeleton { to { background-position: 200% 0; } }
        .amz-gallery-main img { width: 100%; height: 100%; object-fit: contain; display: block; }
        .amz-gallery-main .amz-zoom-lens {
            position: absolute;
            width: 80px;
            height: 80px;
            border: 2px solid var(--amz-blue);
            border-radius: 4px;
            background: rgba(20, 110, 180, 0.1);
            pointer-events: none;
            display: none;
            z-index: 2;
        }
        .amz-gallery-main.show-zoom .amz-zoom-lens { display: block; }
        .amz-gallery-zoom-preview {
            position: absolute;
            left: 100%;
            top: 0;
            margin-left: 12px;
            width: 280px;
            height: 280px;
            border: 1px solid var(--amz-border);
            border-radius: 6px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
            display: none;
            z-index: 10;
        }
        .amz-gallery-main.show-zoom .amz-gallery-zoom-preview { display: block; }
        .amz-gallery-zoom-preview img {
            position: absolute;
            max-width: none;
            width: auto;
            height: auto;
            min-width: 100%;
            min-height: 100%;
        }
        .amz-gallery-arrows {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 50%;
            background: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            color: #333;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            z-index: 3;
            transition: background 0.2s;
        }
        .amz-gallery-arrows:hover { background: #f0f0f0; }
        .amz-gallery-arrows.prev { left: 8px; }
        .amz-gallery-arrows.next { right: 8px; }
        .amz-gallery-counter {
            font-size: 0.75rem;
            color: #666;
            margin-top: 8px;
        }
        .amz-gallery-counter a {
            color: var(--amz-blue);
            text-decoration: none;
        }
        .amz-gallery-counter a:hover { text-decoration: underline; }
        .amz-gallery-fullscreen-btn {
            margin-top: 6px;
            font-size: 0.75rem;
            color: var(--amz-blue);
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
        }
        .amz-gallery-fullscreen-btn:hover { text-decoration: underline; }
        .amz-gallery-loading-msg {
            margin-top: 10px;
            font-size: 0.8rem;
            color: #666;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .amz-gallery-spinner {
            width: 18px;
            height: 18px;
            border: 2px solid #e0e0e0;
            border-top-color: var(--amz-blue);
            border-radius: 50%;
            animation: amz-spin 0.8s linear infinite;
        }
        @keyframes amz-spin { to { transform: rotate(360deg); } }
        .amz-stack-tabs {
            display: flex;
            border-bottom: 1px solid var(--amz-border);
            gap: 0;
            flex-shrink: 0;
            background: #fff;
            margin: 0 -1.25rem 1rem;
            padding: 0 1.25rem;
        }
        .amz-stack-tab {
            padding: 0.5rem 0.75rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: #666;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -1px;
        }
        .amz-stack-tab:hover { color: var(--amz-blue); }
        .amz-stack-tab.active { color: var(--amz-blue); border-bottom-color: var(--amz-blue); }
        .amz-stack-tab-content { display: none; padding: 0; }
        .amz-stack-tab-content.active { display: block; }
        .amz-tab-images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 12px;
        }
        .amz-tab-images-grid .amz-tab-img {
            aspect-ratio: 1;
            border: 1px solid var(--amz-border);
            border-radius: 6px;
            overflow: hidden;
            background: #f0f0f0;
            cursor: pointer;
        }
        .amz-tab-images-grid .amz-tab-img img { width: 100%; height: 100%; object-fit: cover; }
        .amz-tab-videos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 12px;
        }
        .amz-tab-video-card {
            border: 1px solid var(--amz-border);
            border-radius: 6px;
            overflow: hidden;
            background: #000;
            cursor: pointer;
            position: relative;
        }
        .amz-tab-video-card .thumb { aspect-ratio: 16/9; position: relative; }
        .amz-tab-video-card .thumb img { width: 100%; height: 100%; object-fit: cover; }
        .amz-tab-video-card .play-overlay {
            position: absolute; top: 0; left: 0; right: 0; bottom: 0;
            display: flex; align-items: center; justify-content: center;
            background: rgba(0,0,0,0.3);
        }
        .amz-tab-video-card .play-overlay span { font-size: 2.5rem; color: #fff; }
        .amz-tab-features-list { list-style: none; padding: 0; margin: 0; }
        .amz-tab-features-list li {
            padding: 0.5rem 0 0.5rem 1.75rem;
            position: relative;
            font-size: 0.9rem;
            line-height: 1.4;
            color: #333;
        }
        .amz-tab-features-list li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: var(--amz-blue);
            font-weight: bold;
        }
        .amz-detail-price { font-size: 1.25rem; font-weight: 700; color: #b12704; margin-bottom: 0.5rem; }
        .amz-detail-meta { font-size: 0.875rem; color: #555; margin-bottom: 1rem; }
        .amz-video-modal {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.85);
            z-index: 1062;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .amz-video-modal.show { display: flex; }
        .amz-video-modal-inner {
            position: relative;
            width: 100%;
            max-width: 800px;
            background: #000;
            border-radius: 8px;
            overflow: hidden;
        }
        .amz-video-modal-inner iframe { width: 100%; aspect-ratio: 16/9; display: block; }
        .amz-video-modal-close {
            position: absolute;
            top: -40px;
            right: 0;
            background: none;
            border: none;
            color: #fff;
            font-size: 1.5rem;
            cursor: pointer;
        }
        .amz-lightbox {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.92);
            z-index: 1060;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .amz-lightbox.show { display: flex; }
        .amz-lightbox img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .amz-lightbox-close {
            position: absolute;
            top: 16px;
            right: 20px;
            background: transparent;
            border: none;
            color: #fff;
            font-size: 2rem;
            cursor: pointer;
        }
        .amz-lightbox-counter { position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); color: #fff; font-size: 0.9rem; }
        @media (max-width: 768px) {
            .amz-stack-panel { width: 100%; max-width: none; height: 100%; top: 0; left: 0; transform: none; }
            .amz-stack-overlay.show .amz-stack-panel { transform: none; }
            .amz-stack-body { flex-direction: column; }
            .amz-stack-gallery-col { width: 100%; border-right: none; border-bottom: 1px solid var(--amz-border); }
            .amz-stack-details-col { width: 100%; }
            .amz-gallery-zoom-preview { display: none !important; }
        }
    </style>
@endsection

@section('content')
    <div class="amz-toast" id="amz-toast"></div>

    <div class="amz-page-header">
        <div>
            <h1>Amazon Listings</h1>
            <div class="sub">Raw listing data from SP-API (GET_MERCHANT_LISTINGS_ALL_DATA)</div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-outline-light border border-secondary" id="btn-analyze-data" title="Analyze Amazon data quality (item_name, raw_data)">
                <i class="ri-bar-chart-line me-1"></i> Analyze Amazon Data
            </button>
            <button type="button" class="btn btn-outline-light border border-secondary" id="btn-extract-titles" title="Copy Item Name from Amazon listings into Title Master (product_master.title150)">
                <i class="ri-file-copy-line me-1"></i> Extract Titles to Title Master
            </button>
            <button type="button" class="btn btn-import-amazon" id="btn-import-amazon">
                <i class="ri-download-cloud-line me-1"></i> Import from Amazon
            </button>
        </div>
    </div>

    <div class="amz-stats-row">
        <div class="amz-stat-card">
            <div class="value" id="stat-total">0</div>
            <div class="label">Total Listings</div>
        </div>
        <div class="amz-stat-card">
            <div class="value" id="stat-active">0</div>
            <div class="label">Active (Qty &gt; 0)</div>
        </div>
        <div class="amz-stat-card">
            <div class="value" id="stat-last-import">—</div>
            <div class="label">Last Import</div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card amz-table-card">
                <div class="card-body">
                    <div class="amz-table-section-title">
                        <span>Manage Inventory</span>
                    </div>
                    <div class="amz-toolbar">
                        <div class="amz-toolbar-info">
                            <span class="text-muted" style="font-size:0.75rem;color:var(--amz-text-secondary);">
                                Showing <strong id="row-count">0</strong> of <strong id="total-count">0</strong> rows
                                <span class="ms-2" id="page-info"></span>
                            </span>
                        </div>
                        <div id="pagination-controls" class="amz-pagination-controls d-none">
                            <button type="button" class="btn btn-sm btn-outline-secondary me-1" id="btn-prev">Previous</button>
                            <span class="amz-page-num mx-2" id="page-num">Page 1</span>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-next">Next</button>
                        </div>
                    </div>
                    <div class="amz-table-wrap">
                        <div class="amz-loading-overlay" id="table-loading">
                            <div class="text-center">
                                <div class="spinner-border text-primary mb-2" role="status"></div>
                                <div>Loading listings…</div>
                            </div>
                        </div>
                        <div id="amz-listings-table"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Amazon Seller Central-style detail stack (slide-out) -->
    <div class="amz-stack-overlay" id="amz-detail-stack">
        <div class="amz-stack-panel">
            <div class="amz-stack-header">
                <div>
                    <h2 id="amz-stack-title">Listing details</h2>
                    <div class="amz-stack-copy-btns" id="amz-stack-copy-btns"></div>
                </div>
                <button type="button" class="amz-stack-close" id="amz-stack-close" aria-label="Close">&times;</button>
            </div>
            <div class="amz-stack-body" id="amz-stack-body"></div>
        </div>
    </div>
    <!-- Lightbox for fullscreen image -->
    <div class="amz-lightbox" id="amz-lightbox">
        <button type="button" class="amz-lightbox-close" id="amz-lightbox-close" aria-label="Close">&times;</button>
        <img id="amz-lightbox-img" src="" alt="Enlarged">
        <span class="amz-lightbox-counter" id="amz-lightbox-counter"></span>
    </div>
    <!-- Video embed modal -->
    <div class="amz-video-modal" id="amz-video-modal">
        <div class="amz-video-modal-inner">
            <button type="button" class="amz-video-modal-close" id="amz-video-modal-close" aria-label="Close">&times;</button>
            <div id="amz-video-modal-embed"></div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        (function() {
            var table;
            var dataUrl = "{{ url('/listing-master/amz-data/data') }}";
            var importUrl = "{{ url('/listing-master/amz-data/import') }}";
            var extractTitlesUrl = "{{ url('/listing-master/amz-data/extract-titles') }}";
            var analyzeUrl = "{{ url('/listing-master/amz-data/analyze') }}";
            var imagesUrl = "{{ url('/listing-master/amz-data/images') }}";
            var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            var toastEl = document.getElementById('amz-toast');
            var tableLoading = document.getElementById('table-loading');

            function showToast(type, message) {
                toastEl.className = 'amz-toast show ' + type;
                toastEl.textContent = message;
                setTimeout(function() { toastEl.classList.remove('show'); }, 5000);
            }

            function formatColumnTitle(key) {
                if (!key) return key;
                return key.replace(/-/g, ' ').replace(/\b\w/g, function(c) { return c.toUpperCase(); });
            }

            function buildColumns(columns) {
                var dotCol = {
                    title: '',
                    width: 44,
                    minWidth: 44,
                    maxWidth: 44,
                    headerSort: false,
                    resizable: false,
                    formatter: function() {
                        return '<span class="amz-dot-btn" title="View details">•</span>';
                    },
                    cellClick: function(e, cell) {
                        e.stopPropagation();
                        var rowData = cell.getRow().getData();
                        openDetailStack(rowData);
                    },
                };
                if (!columns || columns.length === 0) {
                    return [dotCol, { title: 'No data', field: 'id', width: 100 }];
                }
                var numFields = ['price', 'quantity', 'fulfillment-channel', 'id'];

                var imageCol = {
                    title: 'Image',
                    field: 'thumbnail_image',
                    width: 80,
                    headerSort: false,
                    formatter: function(cell) {
                        var img = cell.getValue();
                        if (!img) {
                            return '<div style="width:50px;height:50px;background:#eee;border-radius:4px;"></div>';
                        }
                        return '<img src="' + img + '" style="width:50px;height:50px;object-fit:contain;border:1px solid #ddd;border-radius:4px;" />';
                    },
                };

                var dataCols = [];
                columns.forEach(function(col) {
                    // The thumbnail_image column is rendered via the custom Image column above.
                    if (col === 'thumbnail_image') {
                        return;
                    }

                    var def = {
                        title: formatColumnTitle(col),
                        field: col,
                        minWidth: 90,
                        headerSort: true,
                        headerFilter: col.indexOf('sku') !== -1 || col.indexOf('asin') !== -1 || col === 'price' || col === 'quantity' ? 'input' : false,
                    };
                    if (col === 'price' || col === 'quantity') {
                        def.sorter = 'number';
                        def.formatter = function(cell) {
                            var v = cell.getValue();
                            if (col === 'price' && (v !== null && v !== '')) return '$' + Number(v).toFixed(2);
                            return v !== null && v !== '' ? v : '—';
                        };
                    } else if (col === 'seller_sku' || col === 'asin1') {
                        def.sorter = 'string';
                        def.width = 120;
                    }
                    dataCols.push(def);
                });

                // Insert Image column after dot column and before seller_sku when present.
                var insertIndex = dataCols.findIndex(function(c) { return c.field === 'seller_sku'; });
                if (insertIndex === -1) {
                    dataCols.unshift(imageCol);
                } else {
                    dataCols.splice(insertIndex, 0, imageCol);
                }

                return [dotCol].concat(dataCols);
            }

            function formatLabel(key) {
                if (!key) return key;
                return key.replace(/-/g, ' ').replace(/_/g, ' ').replace(/\b\w/g, function(c) { return c.toUpperCase(); });
            }

            function escapeHtml(s) {
                if (s == null) return '';
                var div = document.createElement('div');
                div.textContent = s;
                return div.innerHTML;
            }

            function formatValue(key, v) {
                if (v === undefined || v === null) return '';
                if (typeof v === 'object') return escapeHtml(JSON.stringify(v));
                var s = String(v).trim();
                if (s === '') return '—';
                var kl = key.toLowerCase();
                if (kl.indexOf('date') !== -1 || kl === 'open-date' || kl === 'last-update-date' || kl === 'report_imported_at') {
                    try {
                        var d = new Date(s);
                        if (!isNaN(d.getTime())) return escapeHtml(d.toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' }));
                    } catch (e) {}
                }
                if (kl === 'price' || kl === 'business-price' || (kl.indexOf('price') !== -1 && s.match(/^\d|^\$|^\d+\.\d+$/))) {
                    var num = parseFloat(s.replace(/[^0-9.-]/g, ''));
                    if (!isNaN(num)) return escapeHtml('$' + num.toFixed(2));
                }
                if (s === 'true' || s === '1' || s === 'yes') return 'Yes';
                if (s === 'false' || s === '0' || s === 'no') return 'No';
                return escapeHtml(s);
            }

            function isUrlLike(val) {
                if (typeof val !== 'string' || !val.trim()) return false;
                return /^https?:\/\//i.test(val.trim()) || val.trim().indexOf('http') === 0;
            }

            var IMAGE_KEY_PATTERNS = [
                'main-image-url', 'main-image-url-1',
                'image-url', 'product-image', 'product-image-url', 'picture-url', 'picture-url-1'
            ];
            function imageKeyMatch(k) {
                var kl = k.toLowerCase();
                if (kl.indexOf('image') !== -1 || kl.indexOf('img') !== -1) return true;
                if (/^image-url(-\d+)?$/i.test(kl) || /^picture-url(-\d+)?$/i.test(kl)) return true;
                return IMAGE_KEY_PATTERNS.indexOf(kl) !== -1;
            }
            function isImageUrl(url) {
                if (!url || !isUrlLike(url)) return false;
                return /\.(jpe?g|png|gif|webp)(\?|$)/i.test(url) || /(?:image|img|photo)/i.test(url);
            }
            function extractUrlsFromValue(v) {
                var out = [];
                if (typeof v === 'string') {
                    if (isUrlLike(v)) out.push(v.trim());
                    else {
                        var re = /https?:\/\/[^\s"'<>]+\.(jpe?g|png|gif|webp)(\?[^\s"'<>]*)?/gi;
                        var m;
                        while ((m = re.exec(v)) !== null) out.push(m[0]);
                    }
                } else if (Array.isArray(v)) {
                    v.forEach(function(item) {
                        if (typeof item === 'string' && isUrlLike(item)) out.push(item.trim());
                    });
                }
                return out;
            }

            function makePlaceholderDataUri(sku, asin) {
                var line2 = (sku || asin) ? (sku || '') + (sku && asin ? ' / ' : '') + (asin || '') : '';
                var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="300" viewBox="0 0 300 300">' +
                    '<rect fill="#f0f0f0" width="300" height="300"/>' +
                    '<text x="50%" y="42%" text-anchor="middle" fill="#999" font-size="14" font-family="sans-serif">No image available</text>' +
                    (line2 ? '<text x="50%" y="55%" text-anchor="middle" fill="#999" font-size="11" font-family="sans-serif">' + String(line2).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;') + '</text>' : '') +
                    '</svg>';
                return 'data:image/svg+xml,' + encodeURIComponent(svg);
            }
            function buildGalleryItems(rowData) {
                if (typeof console !== 'undefined' && console.log) {
                    console.log('🔍 [Amz Gallery] Searching for images in rowData. Keys:', rowData ? Object.keys(rowData || {}) : []);
                }
                var items = [];
                var seen = {};
                function addImage(url, alt) {
                    if (!url || typeof url !== 'string') return;
                    url = url.trim();
                    if (!url || !isUrlLike(url) || seen[url]) return;
                    seen[url] = true;
                    if (!isImageUrl(url)) return;
                    items.push({ type: 'image', url: url, thumbnail: url, alt: alt || '' });
                }
                function addVideo(url, thumbnail, duration) {
                    if (!url || typeof url !== 'string') return;
                    url = url.trim();
                    if (!url || !isUrlLike(url) || seen[url]) return;
                    seen[url] = true;
                    items.push({ type: 'video', url: url, thumbnail: thumbnail || '', duration: duration || '' });
                }
                function isEmpty(v) {
                    if (v === undefined || v === null) return true;
                    if (typeof v === 'string' && !v.trim()) return true;
                    return false;
                }
                var videoKeys = [];
                Object.keys(rowData || {}).forEach(function(k) {
                    if (k.toLowerCase().indexOf('video') !== -1) videoKeys.push(k);
                });
                var orderedImageKeys = ['main-image-url', 'main-image-url-1', 'image-url', 'image-url-1', 'thumbnail_image'];
                for (var oi = 0; oi < orderedImageKeys.length; oi++) {
                    var u = rowData[orderedImageKeys[oi]];
                    if (u && typeof u === 'string' && u.trim()) addImage(u);
                }
                for (var i = 1; i <= 9; i++) {
                    var u = rowData['image-url-' + i] || rowData['picture-url-' + i];
                    if (u && typeof u === 'string' && u.trim()) addImage(u);
                }
                for (var i = 10; i <= 12; i++) {
                    var u = rowData['image-url-' + i] || rowData['picture-url-' + i];
                    if (u && typeof u === 'string' && u.trim()) addImage(u);
                }
                var imageKeys = [];
                Object.keys(rowData || {}).forEach(function(k) {
                    if (imageKeyMatch(k) && orderedImageKeys.indexOf(k) === -1) {
                        var skip = false;
                        for (var si = 1; si <= 12; si++) { if (k === 'image-url-' + si || k === 'picture-url-' + si) { skip = true; break; } }
                        if (!skip) imageKeys.push(k);
                    }
                });
                imageKeys.forEach(function(k) {
                    var v = rowData[k];
                    if (isEmpty(v)) return;
                    extractUrlsFromValue(v).forEach(function(u) { addImage(u, k); });
                });
                if (items.length === 0) {
                    Object.keys(rowData || {}).forEach(function(k) {
                        var v = rowData[k];
                        if (typeof v === 'string' && v.trim() && isImageUrl(v)) addImage(v);
                    });
                }
                var rawStr = (rowData && rowData.raw_data) ? (typeof rowData.raw_data === 'string' ? rowData.raw_data : JSON.stringify(rowData.raw_data)) : '';
                if (rawStr) {
                    try {
                        if (typeof console !== 'undefined' && console.log) {
                            try {
                                var parsed = JSON.parse(rowData.raw_data);
                                console.log('🔍 [Amz Gallery] Parsed raw_data keys:', parsed ? Object.keys(parsed) : []);
                            } catch (e) {
                                console.log('❌ [Amz Gallery] Failed to parse raw_data as JSON:', e && e.message);
                            }
                        }
                        var urlRe = /https?:\/\/[^\s"'<>)\]}]+\.(jpe?g|png|gif|webp)(\?[^\s"'<>)\]}]*)?/gi;
                        var match;
                        while ((match = urlRe.exec(rawStr)) !== null) addImage(match[0]);
                    } catch (e) {
                        if (typeof console !== 'undefined' && console.warn) console.warn('❌ [Amz Gallery] Error scanning raw_data for URLs:', e && e.message);
                    }
                }
                videoKeys.forEach(function(k) {
                    var v = rowData[k];
                    if (isEmpty(v) || typeof v !== 'string') return;
                    var thumbKey = k.replace(/^video-url/, 'video-thumbnail');
                    var thumb = (rowData[thumbKey] && String(rowData[thumbKey]).trim()) ? String(rowData[thumbKey]).trim() : (items.length > 0 ? items[0].url : '');
                    var durKey = k.replace(/^video-url/, 'video-duration');
                    addVideo(v, thumb, (rowData[durKey] && String(rowData[durKey]).trim()) ? String(rowData[durKey]).trim() : '');
                });
                for (var vi = 1; vi <= 5; vi++) {
                    var vk = 'video-url-' + vi;
                    var vv = rowData[vk];
                    if (isEmpty(vv) || typeof vv !== 'string') continue;
                    var thumb = (rowData['video-thumbnail-' + vi] && String(rowData['video-thumbnail-' + vi]).trim()) ? String(rowData['video-thumbnail-' + vi]).trim() : (items.length > 0 ? items[0].url : '');
                    addVideo(vv, thumb, rowData['video-duration-' + vi] || '');
                }
                if (items.length === 0) {
                    if (typeof console !== 'undefined' && console.log) console.log('⚠️ [Amz Gallery] No images found - using placeholder. GET_MERCHANT_LISTINGS_ALL_DATA often has empty image-url.');
                    var sku = (rowData.seller_sku || rowData['seller-sku'] || '').toString().trim();
                    var asin = (rowData.asin1 || rowData.asin || '').toString().trim();
                    items.push({ type: 'image', url: makePlaceholderDataUri(sku, asin), thumbnail: makePlaceholderDataUri(sku, asin), alt: 'No image available', isPlaceholder: true });
                }
                if (typeof console !== 'undefined' && console.log) console.log('🔍 [Amz Gallery] galleryItems result:', items.length, items);
                return items;
            }

            var FIELD_SECTIONS = {
                'Identifiers': ['id', 'seller_sku', 'seller-sku', 'asin1', 'asin2', 'asin', 'listing-id', 'product-id', 'item-name', 'parent-sku', 'relationship-type', 'sku'],
                'Pricing & Quantity': ['price', 'quantity', 'fulfillment-channel', 'business-price', 'quantity-price-type', 'quantity-lower-bound', 'quantity-upper-bound', 'merchant-shipping-group'],
                'Product Info': ['item-description', 'item-name', 'product-id-type', 'condition-type', 'condition-note', 'brand', 'manufacturer', 'part-number', 'model', 'generic-keyword', 'target-audience', 'item-note', 'variation-theme'],
                'Status & Dates': ['status', 'report_imported_at', 'open-date', 'last-update-date', 'pending-quantity', 'fulfillment-channel', 'will-ship-internationally'],
                'Offer': ['fulfillment-channel', 'merchant-shipping-group', 'handling-time', 'max-order-quantity'],
            };

            function renderFieldRows(rowData, keys) {
                var html = [];
                keys.forEach(function(k) {
                    if (rowData[k] === undefined && rowData[k] !== 0) return;
                    var v = rowData[k];
                    if (v === null || v === '') return;
                    var display = typeof v === 'object' ? JSON.stringify(v) : String(v);
                    html.push('<div class="amz-stack-row"><span class="amz-stack-label">' + escapeHtml(formatLabel(k)) + '</span><span class="amz-stack-value">' + formatValue(k, v) + '</span></div>');
                });
                return html.join('');
            }

            function flattenForDisplay(obj, prefix) {
                if (!obj || typeof obj !== 'object') return {};
                prefix = prefix || '';
                var out = {};
                Object.keys(obj).forEach(function(k) {
                    if (k === 'raw_data') return;
                    var v = obj[k];
                    var key = prefix ? prefix + '.' + k : k;
                    if (v !== null && typeof v === 'object' && !Array.isArray(v) && !(v instanceof Date)) {
                        Object.assign(out, flattenForDisplay(v, key));
                    } else {
                        out[key] = v;
                    }
                });
                return out;
            }

            function openDetailStack(rowData) {
                var expanded = Object.assign({}, rowData);
                if (rowData && rowData.raw_data) {
                    try {
                        var parsed = typeof rowData.raw_data === 'string' ? JSON.parse(rowData.raw_data) : rowData.raw_data;
                        if (parsed && typeof parsed === 'object') {
                            var flat = flattenForDisplay(parsed);
                            Object.keys(flat).forEach(function(k) {
                                if (expanded[k] === undefined) expanded[k] = flat[k];
                            });
                        }
                    } catch (e) {}
                }
                rowData = expanded;
                var allKeys = Object.keys(rowData).filter(function(k) { return k !== 'raw_data'; });

                var title = rowData.seller_sku || rowData['seller-sku'] || rowData.asin1 || rowData.asin || rowData['item-name'] || 'Listing details';
                document.getElementById('amz-stack-title').textContent = title;

                var copySku = (rowData.seller_sku || rowData['seller-sku'] || '').toString().trim();
                var copyAsin = (rowData.asin1 || rowData.asin || '').toString().trim();
                var copyHtml = '';
                if (copySku) copyHtml += '<button type="button" class="amz-stack-copy-btn" data-copy="' + escapeHtml(copySku) + '">Copy SKU</button>';
                if (copyAsin) copyHtml += '<button type="button" class="amz-stack-copy-btn" data-copy="' + escapeHtml(copyAsin) + '">Copy ASIN</button>';
                document.getElementById('amz-stack-copy-btns').innerHTML = copyHtml;
                document.querySelectorAll('#amz-stack-copy-btns .amz-stack-copy-btn').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var text = this.getAttribute('data-copy');
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(text).then(function() { showToast('success', 'Copied to clipboard'); }).catch(function() { fallbackCopy(text); });
                        } else { fallbackCopy(text); }
                    });
                });
                function fallbackCopy(text) {
                    var ta = document.createElement('textarea');
                    ta.value = text;
                    document.body.appendChild(ta);
                    ta.select();
                    try { document.execCommand('copy'); showToast('success', 'Copied'); } catch (e) { showToast('error', 'Copy failed'); }
                    document.body.removeChild(ta);
                }

                var galleryItems = buildGalleryItems(rowData);
                var usedKeys = {};
                Object.keys(FIELD_SECTIONS).forEach(function(sectionTitle) {
                    FIELD_SECTIONS[sectionTitle].forEach(function(k) { usedKeys[k] = true; });
                });
                var detailsHtml = '';
                Object.keys(FIELD_SECTIONS).forEach(function(sectionTitle) {
                    var keys = FIELD_SECTIONS[sectionTitle];
                    var rows = renderFieldRows(rowData, keys);
                    if (rows) detailsHtml += '<div class="amz-stack-section"><div class="amz-stack-section-title">' + sectionTitle + '</div>' + rows + '</div>';
                });
                var bulletKeys = ['bullet-point', 'bullet_point', 'bullet_points', '_bullet_points'];
                bulletKeys.forEach(function(k) { usedKeys[k] = true; });
                var otherKeys = allKeys.filter(function(k) { return !usedKeys[k] && bulletKeys.indexOf(k) === -1; });
                if (otherKeys.length > 0) {
                    detailsHtml += '<div class="amz-stack-section"><div class="amz-stack-section-title">More fields</div>' + renderFieldRows(rowData, otherKeys) + '</div>';
                }
                var allFieldsHtml = '';
                var excludeFromAll = ['bullet-point', 'bullet_point', 'bullet_points', '_bullet_points'];
                allKeys.forEach(function(k) {
                    if (excludeFromAll.indexOf(k) !== -1) return;
                    var v = rowData[k];
                    if (v === undefined && v !== 0) return;
                    allFieldsHtml += '<div class="amz-stack-row"><span class="amz-stack-label">' + escapeHtml(formatLabel(k)) + '</span><span class="amz-stack-value">' + formatValue(k, v) + '</span></div>';
                });
                if (allFieldsHtml === '') allFieldsHtml = '<div class="text-muted small">No fields</div>';

                var priceStr = rowData.price !== undefined && rowData.price !== null && rowData.price !== '' ? ('$' + Number(rowData.price).toFixed(2)) : '—';
                var conditionDisplay = (rowData.condition_type_display || '').toString().trim();
                var conditionCode = (rowData['condition-type'] || rowData.condition_type || '').toString().trim();
                var conditionStr = conditionDisplay || (conditionCode && /^11$/.test(conditionCode) ? 'New' : conditionCode && /^10$/.test(conditionCode) ? 'Refurbished' : conditionCode && /^[1-4]$/.test(conditionCode) ? 'Used' : conditionCode) || '—';
                var bulletPoints = rowData.bullet_point || rowData.bullet_points || rowData._bullet_points || [];
                if (!Array.isArray(bulletPoints)) bulletPoints = [];
                if (bulletPoints.length === 0 && (rowData['bullet-point'] || rowData['item-description'])) {
                    var desc = (rowData['bullet-point'] || rowData['item-description'] || '').toString();
                    if (desc) {
                        desc.split(/\n|\r\n|•|\*/).forEach(function(line) {
                            line = line.trim();
                            if (line.length > 10) bulletPoints.push(line);
                        });
                    }
                }

                var imageItems = galleryItems.filter(function(i) { return i.type === 'image' && !i.isPlaceholder; });
                var videoItems = galleryItems.filter(function(i) { return i.type === 'video'; });
                var imagesTabHtml = '';
                if (imageItems.length > 0) {
                    imagesTabHtml = '<div class="amz-tab-images-grid" id="amz-tab-images-grid">';
                    imageItems.forEach(function(item, i) {
                        var safeUrl = escapeHtml(item.url);
                        imagesTabHtml += '<div class="amz-tab-img" data-url="' + safeUrl + '" data-index="' + i + '"><img src="' + safeUrl + '" alt="' + escapeHtml(item.alt || 'Image ' + (i + 1)) + '" loading="lazy"></div>';
                    });
                    imagesTabHtml += '</div><p class="small text-muted mt-2">' + imageItems.length + ' image(s). Click to enlarge.</p>';
                } else {
                    imagesTabHtml = '<p class="text-muted">No images available.</p>';
                }
                var videosTabHtml = '';
                if (videoItems.length > 0) {
                    videosTabHtml = '<div class="amz-tab-videos-grid" id="amz-tab-videos-grid">';
                    videoItems.forEach(function(item, i) {
                        var thumbUrl = (item.thumbnail || item.url || '').toString();
                        var videoUrl = (item.url || '').toString();
                        var safeVideoUrl = escapeHtml(videoUrl);
                        var safeThumb = escapeHtml(thumbUrl);
                        videosTabHtml += '<div class="amz-tab-video-card" data-video-url="' + safeVideoUrl + '"><div class="thumb">';
                        if (thumbUrl) videosTabHtml += '<img src="' + safeThumb + '" alt="Video ' + (i + 1) + '" loading="lazy">';
                        videosTabHtml += '<div class="play-overlay"><span>&#9654;</span></div></div></div>';
                    });
                    videosTabHtml += '</div><p class="small text-muted mt-2">' + videoItems.length + ' video(s). Click to play.</p>';
                } else {
                    videosTabHtml = '<p class="text-muted">No videos available.</p>';
                }
                var featuresTabHtml = '';
                if (bulletPoints.length > 0) {
                    featuresTabHtml = '<ul class="amz-tab-features-list">';
                    bulletPoints.forEach(function(b) {
                        featuresTabHtml += '<li>' + escapeHtml(String(b)) + '</li>';
                    });
                    featuresTabHtml += '</ul>';
                } else {
                    featuresTabHtml = '<p class="text-muted">No bullet points available.</p>';
                }

                var galleryLeftHtml = '';
                if (galleryItems.length > 0) {
                    galleryLeftHtml += '<div class="amz-gallery-wrap"><div class="amz-gallery-thumbs" id="amz-gallery-thumbs">';
                    galleryItems.forEach(function(item, i) {
                        var isVideo = item.type === 'video';
                        var cls = 'amz-thumb' + (i === 0 ? ' active' : '') + (isVideo ? ' video-thumb' : '') + ' skeleton';
                        var dataUrl = escapeHtml(item.type === 'image' ? item.url : (item.thumbnail || item.url));
                        var dataIndex = i;
                        var dataType = item.type;
                        var dataVideoUrl = isVideo ? escapeHtml(item.url) : '';
                        galleryLeftHtml += '<div class="' + cls + '" data-index="' + dataIndex + '" data-type="' + dataType + '" data-url="' + dataUrl + '" data-video-url="' + dataVideoUrl + '">';
                        if (isVideo) {
                            galleryLeftHtml += '<span class="amz-play-icon">&#9654;</span><span class="amz-video-badge">Video</span>';
                        }
                        galleryLeftHtml += '</div>';
                    });
                    galleryLeftHtml += '</div><div class="amz-gallery-main-wrap">';
                    galleryLeftHtml += '<div class="amz-gallery-main skeleton" id="amz-gallery-main">';
                    galleryLeftHtml += '<div class="amz-zoom-lens" id="amz-zoom-lens"></div>';
                    galleryLeftHtml += '<div class="amz-gallery-zoom-preview" id="amz-zoom-preview"><img id="amz-zoom-preview-img" src="" alt=""></div>';
                    galleryLeftHtml += '<button type="button" class="amz-gallery-arrows prev" id="amz-gallery-prev" aria-label="Previous">&lsaquo;</button>';
                    galleryLeftHtml += '<button type="button" class="amz-gallery-arrows next" id="amz-gallery-next" aria-label="Next">&rsaquo;</button>';
                    galleryLeftHtml += '</div>';
                    galleryLeftHtml += '<div class="amz-gallery-counter" id="amz-gallery-counter">Image 1 of ' + galleryItems.length + '</div>';
                    galleryLeftHtml += '<button type="button" class="amz-gallery-fullscreen-btn" id="amz-gallery-fullscreen">Fullscreen</button>';
                    var hasOnlyPlaceholder = galleryItems.length === 1 && galleryItems[0].isPlaceholder;
                    if (hasOnlyPlaceholder) {
                        galleryLeftHtml += '<div id="amz-gallery-loading-msg" class="amz-gallery-loading-msg"><span class="amz-gallery-spinner"></span> Loading product media…</div>';
                        galleryLeftHtml += '<p id="amz-gallery-no-media-msg" class="amz-gallery-no-images-msg d-none" style="margin-top:10px;font-size:0.8rem;color:#666;">No images or videos available.</p>';
                    }
                    galleryLeftHtml += '</div></div>';
                } else {
                    galleryLeftHtml += '<div class="amz-gallery-wrap"><div class="amz-gallery-main-wrap"><div class="amz-gallery-main" style="display:flex;align-items:center;justify-content:center;color:#999;font-size:0.9rem;">No images in data</div></div></div>';
                }

                var body = document.getElementById('amz-stack-body');
                body.innerHTML =
                    '<div class="amz-stack-gallery-col">' + galleryLeftHtml + '</div>' +
                    '<div class="amz-stack-details-col">' +
                    '<div class="amz-detail-price">' + escapeHtml(priceStr) + '</div>' +
                    '<div class="amz-detail-meta">Condition: ' + escapeHtml(conditionStr) + '</div>' +
                    '<div class="amz-stack-tabs">' +
                    '<span class="amz-stack-tab" data-tab="images">Images</span>' +
                    '<span class="amz-stack-tab" data-tab="videos">Videos</span>' +
                    '<span class="amz-stack-tab" data-tab="features">Bullet Points</span>' +
                    '<span class="amz-stack-tab active" data-tab="details">Details</span>' +
                    '<span class="amz-stack-tab" data-tab="all">All fields</span>' +
                    '</div>' +
                    '<div class="amz-stack-tab-content" id="amz-tab-images">' + imagesTabHtml + '</div>' +
                    '<div class="amz-stack-tab-content" id="amz-tab-videos">' + videosTabHtml + '</div>' +
                    '<div class="amz-stack-tab-content" id="amz-tab-features">' + featuresTabHtml + '</div>' +
                    '<div class="amz-stack-tab-content active" id="amz-tab-details">' + detailsHtml + '</div>' +
                    '<div class="amz-stack-tab-content" id="amz-tab-all"><div class="amz-stack-section"><div class="amz-stack-section-title">All fields (' + allKeys.length + ')</div>' + allFieldsHtml + '</div></div>' +
                    '</div>';

                body.querySelectorAll('.amz-stack-tab').forEach(function(tab) {
                    tab.addEventListener('click', function() {
                        var t = this.getAttribute('data-tab');
                        body.querySelectorAll('.amz-stack-tab').forEach(function(x) { x.classList.remove('active'); });
                        body.querySelectorAll('.amz-stack-tab-content').forEach(function(x) { x.classList.remove('active'); });
                        this.classList.add('active');
                        var content = document.getElementById('amz-tab-' + t);
                        if (content) content.classList.add('active');
                    });
                });
                var tabImageItems = imageItems;
                body.querySelectorAll('.amz-tab-img').forEach(function(el) {
                    el.addEventListener('click', function() {
                        var url = this.getAttribute('data-url');
                        var idx = parseInt(this.getAttribute('data-index'), 10);
                        if (url) openLightbox(url, idx, tabImageItems);
                    });
                });
                body.querySelectorAll('.amz-tab-video-card').forEach(function(el) {
                    el.addEventListener('click', function() {
                        var url = this.getAttribute('data-video-url');
                        if (url) openVideoModal(url);
                    });
                });

                var currentGalleryIndex = 0;
                function setGalleryIndex(idx) {
                    if (galleryItems.length === 0) return;
                    currentGalleryIndex = (idx % galleryItems.length + galleryItems.length) % galleryItems.length;
                    var item = galleryItems[currentGalleryIndex];
                    var mainEl = document.getElementById('amz-gallery-main');
                    var counterEl = document.getElementById('amz-gallery-counter');
                    if (!mainEl || !counterEl) return;
                    mainEl.querySelectorAll('img').forEach(function(im) { im.remove(); });
                    mainEl.querySelectorAll('.amz-video-inline').forEach(function(el) { el.remove(); });
                    mainEl.classList.remove('skeleton', 'show-zoom');
                    if (item.type === 'video') {
                        var embed = getVideoEmbedHtml(item.url);
                        mainEl.innerHTML = '<div class="amz-zoom-lens" id="amz-zoom-lens"></div><div class="amz-gallery-zoom-preview" id="amz-zoom-preview"><img id="amz-zoom-preview-img" src="" alt=""></div><button type="button" class="amz-gallery-arrows prev" id="amz-gallery-prev" aria-label="Previous">&lsaquo;</button><button type="button" class="amz-gallery-arrows next" id="amz-gallery-next" aria-label="Next">&rsaquo;</button><div class="amz-video-inline" style="position:absolute;top:0;left:0;right:0;bottom:0;display:flex;align-items:center;justify-content:center;background:#000;">' + embed + '</div>';
                    } else {
                        var img = new Image();
                        img.alt = item.alt || '';
                        img.onload = function() {
                            mainEl.classList.remove('skeleton');
                            mainEl.insertBefore(img, mainEl.firstChild);
                            bindZoom(mainEl, img);
                        };
                        img.onerror = function() { mainEl.classList.remove('skeleton'); mainEl.insertBefore(img, mainEl.firstChild); };
                        img.src = item.url;
                    }
                    document.querySelectorAll('#amz-gallery-thumbs .amz-thumb').forEach(function(t, i) {
                        t.classList.toggle('active', i === currentGalleryIndex);
                    });
                    counterEl.textContent = (currentGalleryIndex + 1) + ' / ' + galleryItems.length + (item.type === 'video' ? ' (Video)' : ' photos');
                }
                function getVideoEmbedHtml(url) {
                    var u = (url || '').toLowerCase();
                    if (u.indexOf('youtube.com') !== -1 || u.indexOf('youtu.be') !== -1) {
                        var vid = (u.match(/(?:v=|\/)([a-zA-Z0-9_-]{11})(?:\?|&|$)/) || [])[1] || '';
                        if (vid) return '<iframe src="https://www.youtube.com/embed/' + escapeHtml(vid) + '" frameborder="0" allowfullscreen style="width:100%;height:100%;"></iframe>';
                    }
                    if (u.indexOf('vimeo.com') !== -1) {
                        var vid = (u.match(/vimeo\.com\/(?:video\/)?(\d+)/) || [])[1] || '';
                        if (vid) return '<iframe src="https://player.vimeo.com/video/' + escapeHtml(vid) + '" frameborder="0" allowfullscreen style="width:100%;height:100%;"></iframe>';
                    }
                    if (u.indexOf('.mp4') !== -1 || u.indexOf('video/mp4') !== -1) {
                        return '<video src="' + escapeHtml(url) + '" controls playsinline style="width:100%;height:100%;"></video>';
                    }
                    return '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener" style="color:#146eb4;">Open video</a>';
                }
                function bindZoom(mainEl, imgEl) {
                    var lens = mainEl.querySelector('.amz-zoom-lens');
                    var preview = mainEl.querySelector('.amz-gallery-zoom-preview');
                    var previewImg = mainEl.querySelector('#amz-zoom-preview-img');
                    if (!lens || !preview || !previewImg) return;
                    previewImg.src = imgEl.src;
                    var zoomFactor = 2.2;
                    mainEl.addEventListener('mouseenter', function() { mainEl.classList.add('show-zoom'); });
                    mainEl.addEventListener('mouseleave', function() { mainEl.classList.remove('show-zoom'); });
                    mainEl.addEventListener('mousemove', function(e) {
                        var rect = mainEl.getBoundingClientRect();
                        var x = e.clientX - rect.left;
                        var y = e.clientY - rect.top;
                        var w = rect.width;
                        var h = rect.height;
                        var lw = 80;
                        var lh = 80;
                        var lx = Math.max(0, Math.min(x - lw / 2, w - lw));
                        var ly = Math.max(0, Math.min(y - lh / 2, h - lh));
                        lens.style.left = lx + 'px';
                        lens.style.top = ly + 'px';
                        var pctX = (lx + lw / 2) / w;
                        var pctY = (ly + lh / 2) / h;
                        var pw = previewImg.naturalWidth || w;
                        var ph = previewImg.naturalHeight || h;
                        var moveX = (pw * zoomFactor - preview.offsetWidth) * pctX;
                        var moveY = (ph * zoomFactor - preview.offsetHeight) * pctY;
                        previewImg.style.left = (-moveX) + 'px';
                        previewImg.style.top = (-moveY) + 'px';
                    });
                }
                if (galleryItems.length > 0) {
                    document.querySelectorAll('#amz-gallery-thumbs .amz-thumb').forEach(function(thumb) {
                        var url = thumb.getAttribute('data-url');
                        var idx = parseInt(thumb.getAttribute('data-index'), 10);
                        var type = thumb.getAttribute('data-type');
                        var videoUrl = thumb.getAttribute('data-video-url');
                        thumb.addEventListener('click', function() {
                            setGalleryIndex(idx);
                            if (type === 'video' && videoUrl) openVideoModal(videoUrl);
                        });
                        var img = new Image();
                        img.onload = function() {
                            thumb.classList.remove('skeleton');
                            thumb.appendChild(img);
                        };
                        img.onerror = function() { thumb.classList.remove('skeleton'); thumb.style.background = '#eee'; };
                        if (url) img.src = url;
                    });
                    body.addEventListener('click', function(e) {
                        if (e.target.id === 'amz-gallery-prev' || e.target.closest('#amz-gallery-prev')) setGalleryIndex(currentGalleryIndex - 1);
                        else if (e.target.id === 'amz-gallery-next' || e.target.closest('#amz-gallery-next')) setGalleryIndex(currentGalleryIndex + 1);
                    });
                    document.getElementById('amz-gallery-fullscreen').addEventListener('click', function() {
                        var item = galleryItems[currentGalleryIndex];
                        if (item && item.type === 'image') openLightbox(item.url, currentGalleryIndex, galleryItems);
                        else if (item && item.type === 'video') openVideoModal(item.url);
                    });
                    var mainEl = document.getElementById('amz-gallery-main');
                    if (mainEl) {
                        var touchStartX = 0;
                        mainEl.addEventListener('touchstart', function(e) { touchStartX = e.touches[0].clientX; }, { passive: true });
                        mainEl.addEventListener('touchend', function(e) {
                            var dx = e.changedTouches[0].clientX - touchStartX;
                            if (Math.abs(dx) > 50) setGalleryIndex(currentGalleryIndex + (dx < 0 ? 1 : -1));
                        }, { passive: true });
                    }
                    setGalleryIndex(0);
                    var needsMedia = hasOnlyPlaceholder || !(rowData.bullet_point && rowData.bullet_point.length) && !(rowData.bullet_points && rowData.bullet_points.length);
                    var mediaCacheKey = 'amz_media_' + (copySku || '');
                    var alreadyFetched = (window._amzMediaFetched || (window._amzMediaFetched = {}))[mediaCacheKey];
                    if (copySku && needsMedia && !alreadyFetched) {
                        (window._amzMediaFetched || (window._amzMediaFetched = {}))[mediaCacheKey] = true;
                        fetch(imagesUrl + '?seller_sku=' + encodeURIComponent(copySku))
                            .then(function(r) { return r.json(); })
                            .then(function(res) {
                                var imgCount = (res.images && res.images.length) || 0;
                                var vidCount = (res.videos && res.videos.length) || 0;
                                if (typeof console !== 'undefined' && console.log) console.log('[Amz Gallery] Media API response:', imgCount, 'images in order (image-url-1..' + imgCount + ')', vidCount, 'videos', res);
                                if (res.status === 200 && (imgCount > 0 || vidCount > 0 || (res.bullet_points && res.bullet_points.length > 0))) {
                                    var enhanced = Object.assign({}, rowData);
                                    if (res.images && res.images.length) {
                                        res.images.forEach(function(u, i) {
                                            var url = typeof u === 'string' ? u : (u && (u.url || u.locator || u.media_location));
                                            if (url) enhanced['image-url-' + (i + 1)] = url;
                                        });
                                    }
                                    if (res.videos && res.videos.length) {
                                        res.videos.forEach(function(v, i) {
                                            var url = typeof v === 'string' ? v : (v && v.url);
                                            if (url) {
                                                enhanced['video-url-' + (i + 1)] = url;
                                                if (v.thumbnail) enhanced['video-thumbnail-' + (i + 1)] = v.thumbnail;
                                                if (v.duration) enhanced['video-duration-' + (i + 1)] = (v.duration || '').toString();
                                            }
                                        });
                                    }
                                    if (res.bullet_points && res.bullet_points.length) enhanced.bullet_points = res.bullet_points;
                                    openDetailStack(enhanced);
                                } else if (hasOnlyPlaceholder) {
                                    var loadingEl = document.getElementById('amz-gallery-loading-msg');
                                    if (loadingEl) loadingEl.classList.add('d-none');
                                    var noMsg = document.getElementById('amz-gallery-no-media-msg');
                                    if (noMsg) noMsg.classList.remove('d-none');
                                }
                            })
                            .catch(function(err) {
                                if (typeof console !== 'undefined' && console.error) console.error('[Amz Gallery] Media fetch error:', err);
                                var loadingEl = document.getElementById('amz-gallery-loading-msg');
                                if (loadingEl) loadingEl.classList.add('d-none');
                                var noMsg = document.getElementById('amz-gallery-no-media-msg');
                                if (noMsg) { noMsg.classList.remove('d-none'); noMsg.textContent = 'Could not load media.'; }
                            });
                    }
                }

                function openVideoModal(url) {
                    var embed = document.getElementById('amz-video-modal-embed');
                    embed.innerHTML = getVideoEmbedHtml(url);
                    document.getElementById('amz-video-modal').classList.add('show');
                }
                document.getElementById('amz-video-modal-close').addEventListener('click', function() {
                    document.getElementById('amz-video-modal').classList.remove('show');
                    document.getElementById('amz-video-modal-embed').innerHTML = '';
                });
                document.getElementById('amz-video-modal').addEventListener('click', function(e) {
                    if (e.target === this) { this.classList.remove('show'); document.getElementById('amz-video-modal-embed').innerHTML = ''; }
                });

                document.getElementById('amz-detail-stack').classList.add('show');
            }

            var lightboxIndex = 0;
            var lightboxItems = [];
            function openLightbox(src, index, items) {
                lightboxItems = (items || []).filter(function(i) { return i.type === 'image'; });
                lightboxIndex = typeof index === 'number' ? index : 0;
                document.getElementById('amz-lightbox-img').src = src;
                document.getElementById('amz-lightbox').classList.add('show');
                updateLightboxCounter();
            }
            function updateLightboxCounter() {
                var el = document.getElementById('amz-lightbox-counter');
                if (lightboxItems.length > 1) {
                    el.textContent = (lightboxIndex + 1) + ' / ' + lightboxItems.length;
                    el.style.display = '';
                } else { el.style.display = 'none'; }
            }
            function lightboxPrev() {
                if (lightboxItems.length === 0) return;
                lightboxIndex = (lightboxIndex - 1 + lightboxItems.length) % lightboxItems.length;
                document.getElementById('amz-lightbox-img').src = lightboxItems[lightboxIndex].url;
                updateLightboxCounter();
            }
            function lightboxNext() {
                if (lightboxItems.length === 0) return;
                lightboxIndex = (lightboxIndex + 1) % lightboxItems.length;
                document.getElementById('amz-lightbox-img').src = lightboxItems[lightboxIndex].url;
                updateLightboxCounter();
            }
            function closeLightbox() {
                document.getElementById('amz-lightbox').classList.remove('show');
                document.getElementById('amz-lightbox-img').src = '';
                document.getElementById('amz-lightbox-counter').textContent = '';
            }
            document.getElementById('amz-lightbox-close').addEventListener('click', closeLightbox);
            document.getElementById('amz-lightbox').addEventListener('click', function(e) {
                if (e.target === this) closeLightbox();
            });
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    if (document.getElementById('amz-lightbox').classList.contains('show')) closeLightbox();
                    else if (document.getElementById('amz-detail-stack').classList.contains('show')) closeDetailStack();
                }
                if (document.getElementById('amz-lightbox').classList.contains('show')) {
                    if (e.key === 'ArrowLeft') { e.preventDefault(); lightboxPrev(); }
                    if (e.key === 'ArrowRight') { e.preventDefault(); lightboxNext(); }
                }
            });

            function closeDetailStack() {
                document.getElementById('amz-detail-stack').classList.remove('show');
            }

            document.getElementById('amz-stack-close').addEventListener('click', closeDetailStack);
            document.getElementById('amz-detail-stack').addEventListener('click', function(e) {
                if (e.target === this) closeDetailStack();
            });

            function updateStats(stats) {
                if (!stats) return;
                document.getElementById('stat-total').textContent = (stats.total_listings ?? 0).toLocaleString();
                document.getElementById('stat-active').textContent = (stats.active_listings ?? 0).toLocaleString();
                var last = stats.last_import_at;
                if (last) {
                    try {
                        var d = new Date(last);
                        document.getElementById('stat-last-import').textContent = d.toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' });
                    } catch (e) { document.getElementById('stat-last-import').textContent = last; }
                } else {
                    document.getElementById('stat-last-import').textContent = '—';
                }
            }

            var currentPage = 1;
            var totalRecords = 0;
            // Default 25 per page so backend can fetch thumbnails for all visible rows (limit 25 per request).
            var perPage = 25;
            var lastColumnDefs = null;

            function loadData(page) {
                page = page || 1;
                currentPage = page;
                tableLoading.classList.add('show');
                var url = dataUrl + '?per_page=' + perPage + '&page=' + page;
                fetch(url)
                    .then(function(r) {
                        if (!r.ok) {
                            return r.json().then(function(res) {
                                throw new Error(res.message || 'Server error');
                            }).catch(function() { throw new Error('Request failed'); });
                        }
                        return r.json();
                    })
                    .then(function(res) {
                        if (res.status && res.status !== 200) {
                            throw new Error(res.message || 'Failed to load data');
                        }
                        var data = res.data || [];
                        var columns = res.columns || [];
                        var total = res.total || 0;
                        totalRecords = total;
                        var stats = res.stats || {};
                        var returned = data.length;
                        document.getElementById('row-count').textContent = returned.toLocaleString();
                        document.getElementById('total-count').textContent = total.toLocaleString();
                        updateStats(stats);
                        var colDefs = buildColumns(columns);
                        lastColumnDefs = colDefs;
                        if (table) {
                            table.setColumns(colDefs);
                            table.setData(data);
                        } else {
                            table = new Tabulator("#amz-listings-table", {
                                data: data,
                                columns: colDefs,
                                layout: "fitDataStretch",
                                pagination: true,
                                paginationSize: perPage,
                                paginationSizeSelector: [25, 50],
                                paginationCounter: "rows",
                                cssClass: "amz-tabulator",
                            });
                        }
                        var totalPages = Math.max(1, Math.ceil(total / perPage));
                        document.getElementById('page-num').textContent = 'Page ' + page + ' of ' + totalPages;
                        document.getElementById('page-info').textContent = '(page ' + page + ' of ' + totalPages + ')';
                        var controls = document.getElementById('pagination-controls');
                        if (totalPages > 1) {
                            controls.classList.remove('d-none');
                            document.getElementById('btn-prev').disabled = page <= 1;
                            document.getElementById('btn-next').disabled = page >= totalPages;
                        } else {
                            controls.classList.add('d-none');
                        }
                    })
                    .catch(function(err) {
                        console.error(err);
                        document.getElementById('row-count').textContent = '0';
                        document.getElementById('total-count').textContent = '0';
                        updateStats({ total_listings: 0, active_listings: 0, last_import_at: null });
                        if (table) table.setData([]);
                        showToast('error', err.message || 'Failed to load data.');
                    })
                    .finally(function() {
                        tableLoading.classList.remove('show');
                    });
            }

            document.getElementById('btn-prev').addEventListener('click', function() {
                if (currentPage > 1) loadData(currentPage - 1);
            });
            document.getElementById('btn-next').addEventListener('click', function() {
                var totalPages = Math.max(1, Math.ceil(totalRecords / perPage));
                if (currentPage < totalPages) loadData(currentPage + 1);
            });

            document.getElementById('btn-import-amazon').addEventListener('click', function() {
                var btn = this;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Importing…';
                showToast('info', 'Requesting report from Amazon. This may take a few minutes…');

                fetch(importUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.status === 200) {
                            showToast('success', res.message || 'Import completed.');
                            loadData();
                        } else {
                            showToast('error', res.message || 'Import failed.');
                        }
                    })
                    .catch(function(err) {
                        showToast('error', 'Error: ' + (err.message || 'Request failed'));
                    })
                    .finally(function() {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="ri-download-cloud-line me-1"></i> Import from Amazon';
                    });
            });

            document.getElementById('btn-extract-titles').addEventListener('click', function() {
                var btn = this;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Extracting…';
                fetch(extractTitlesUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) {
                            var msg = 'Extracted ' + (data.count || 0) + ' titles to Title Master.';
                            if ((data.skipped || 0) > 0) {
                                msg += ' ' + data.skipped + ' skipped';
                                if (data.details) {
                                    var d = data.details;
                                    if (d.no_item_name) msg += ' (' + d.no_item_name + ' no item name)';
                                    if (d.sku_not_found) msg += ' (' + d.sku_not_found + ' not in Product Master)';
                                } else {
                                    msg += ' (not in Product Master or missing item name)';
                                }
                            }
                            showToast('success', msg);
                        } else {
                            showToast('error', data.message || 'Extraction failed.');
                        }
                    })
                    .catch(function(err) {
                        showToast('error', 'Extraction failed: ' + (err.message || 'Request failed'));
                    })
                    .finally(function() {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="ri-file-copy-line me-1"></i> Extract Titles to Title Master';
                    });
            });

            document.getElementById('btn-analyze-data').addEventListener('click', function() {
                var btn = this;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Analyzing…';
                fetch(analyzeUrl, { method: 'GET', headers: { 'Accept': 'application/json' } })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        console.log('📊 Analysis:', data);
                        var msg = 'Total: ' + (data.total || 0) + '\nWith Item Name: ' + (data.with_item_name || 0) + '\nIn raw_data: ' + (data.in_raw_data || 0);
                        if (data.sample_missing && data.sample_missing.length) {
                            msg += '\n\nSample missing (first ' + data.sample_missing.length + '): ' + data.sample_missing.map(function(m) { return m.sku; }).join(', ');
                        }
                        showToast('info', 'Analysis complete. Check console for details.');
                        alert('Amazon Data Analysis\n\n' + msg);
                    })
                    .catch(function(err) {
                        showToast('error', 'Analysis failed: ' + (err.message || 'Request failed'));
                    })
                    .finally(function() {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="ri-bar-chart-line me-1"></i> Analyze Amazon Data';
                    });
            });

            loadData(1);
        })();
    </script>
@endsection
