@extends('layouts.vertical', ['title' => 'Shipping Chargebacks'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        .vc-card { border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 2px 12px rgba(44,110,213,.06); }
        .vc-toolbar { display:flex; flex-wrap:wrap; align-items:flex-end; gap:.5rem; margin-bottom:.75rem; }
        .vc-toolbar .vc-field { display:flex; flex-direction:column; gap:.2rem; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.03em; color:#475569; }
        .vc-type-btn { min-width:250px; max-width:320px; height:32px; font-size:.82rem; text-transform:none; letter-spacing:0; font-weight:400; color:#212529; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .vc-toolbar .vc-type-menu { width:330px; padding:0; border:1px solid #e2e8f0; border-radius:12px; box-shadow:0 12px 32px rgba(15,23,42,.16); overflow:hidden; text-transform:none; letter-spacing:0; font-weight:400; color:#1e293b; }
        .vc-type-menu .vc-type-head { display:flex; justify-content:space-between; align-items:center; padding:.6rem .8rem .45rem; }
        .vc-type-menu .vc-type-title { font-size:.8rem; font-weight:700; color:#0f172a; }
        .vc-type-menu .vc-type-count { font-size:.7rem; font-weight:700; color:#4338ca; background:#eef2ff; border-radius:999px; padding:1px 8px; }
        .vc-type-menu .vc-type-quick { display:flex; gap:.35rem; padding:0 .8rem .55rem; border-bottom:1px solid #eef2f7; }
        .vc-type-menu .vc-type-quick button { font-size:.72rem; font-weight:600; border-radius:999px; padding:2px 10px; border:1px solid #cbd5e1; background:#fff; color:#334155; line-height:1.5; }
        .vc-type-menu .vc-type-quick button:hover { background:#f1f5f9; }
        .vc-type-menu .vc-type-quick button.on { background:#4f46e5; border-color:#4f46e5; color:#fff; }
        .vc-type-menu .vc-type-list { max-height:320px; overflow-y:auto; padding:.3rem .4rem; }
        .vc-type-menu .vc-type-group { font-size:.66rem; font-weight:800; text-transform:uppercase; letter-spacing:.06em; color:#94a3b8; padding:.45rem .45rem .2rem; }
        .vc-toolbar .vc-type-menu label.vc-type-opt { display:flex; flex-direction:row; align-items:center; gap:.6rem; margin:1px 0; padding:.4rem .45rem; border-radius:8px; cursor:pointer; text-transform:none; letter-spacing:0; font-size:.82rem; font-weight:500; color:#1e293b; }
        .vc-type-menu label.vc-type-opt:hover { background:#f1f5f9; }
        .vc-type-menu label.vc-type-opt.sel { background:#eef2ff; }
        .vc-toolbar .vc-type-menu label.vc-type-opt input { flex:0 0 auto; width:16px; height:16px; margin:0; font-size:inherit; cursor:pointer; }
        .vc-type-menu .vc-type-txt { display:flex; flex-direction:column; line-height:1.2; min-width:0; }
        .vc-type-menu .vc-type-code { font-size:.68rem; color:#94a3b8; font-weight:400; font-family:ui-monospace,SFMono-Regular,Menlo,monospace; }
        .vc-type-menu .vc-type-foot { display:flex; justify-content:space-between; align-items:center; gap:.5rem; padding:.55rem .8rem; border-top:1px solid #eef2f7; background:#f8fafc; }
        .vc-type-menu .vc-type-saved { font-size:.72rem; color:#15803d; font-weight:700; }
        .vc-type-menu .vc-type-unsaved { font-size:.72rem; color:#b45309; font-weight:600; }
        .vc-type-menu #vcTypeSave { font-size:.75rem; font-weight:600; border-radius:8px; padding:3px 12px; }
        .vc-toolbar label { display:flex; flex-direction:column; gap:.2rem; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.03em; color:#475569; }
        .vc-toolbar input, .vc-toolbar select { height:32px; font-size:.82rem; }
        .vc-badges { display:flex; flex-wrap:wrap; gap:.5rem; margin-bottom:.75rem; }
        .vc-badge { border-radius:10px; padding:.45rem .8rem; background:#f8fafc; border:1px solid #e2e8f0; min-width:120px; }
        .vc-badge .vc-k { font-size:.68rem; font-weight:700; text-transform:uppercase; color:#64748b; letter-spacing:.03em; }
        .vc-badge .vc-v { font-size:1.05rem; font-weight:700; color:#0f172a; }
        .vc-badge.vc-debit .vc-v { color:#b91c1c; }
        .vc-badge.vc-credit .vc-v { color:#15803d; }
        .vc-sync { font-size:.78rem; color:#64748b; }
        .vc-neg { color:#b91c1c; }
        .vc-pos { color:#15803d; }
        #veeqo-chargebacks-table { border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; background:#fff; }
        #veeqo-chargebacks-table .tabulator-header { background:linear-gradient(135deg,#2c6ed5 0%,#1a56b7 100%) !important; }
        #veeqo-chargebacks-table .tabulator-col { background:transparent !important; color:#fff !important; border-right:1px solid rgba(255,255,255,.15); }
        #veeqo-chargebacks-table .tabulator-col-title { font-size:11px; font-weight:700; text-transform:uppercase; white-space:normal; }
        #veeqo-chargebacks-table .tabulator-header-filter input { border:none; border-radius:4px; padding:3px 5px; font-size:11px; color:#333; }
        #veeqo-chargebacks-table .tabulator-cell { font-size:12.5px; color:#334155; }
        #veeqo-chargebacks-table .tabulator-row:nth-child(even) { background:#f8fafc; }
        #veeqo-chargebacks-table .tabulator-row:hover { background:#e8f0fe !important; }
        #vc-col-dropdown { max-height:70vh; overflow-y:auto; min-width:280px; padding:.5rem .75rem; }
        #vc-col-dropdown label { display:flex; gap:.4rem; align-items:center; font-size:.8rem; padding:2px 0; }
        .btn-sku-report { background:linear-gradient(135deg,#7c3aed 0%,#db2777 55%,#f97316 100%); color:#fff; border:none; font-weight:600; box-shadow:0 3px 10px rgba(219,39,119,.35); }
        .btn-sku-report:hover { color:#fff; filter:brightness(1.08); transform:translateY(-1px); }
        #vcSkuModal .modal-content { border:none; border-radius:0; overflow:hidden; background:#f8fafc; }
        #vcSkuModal .modal-body { display:flex; flex-direction:column; }
        .vc-import .modal-header { background:linear-gradient(135deg,#f59e0b,#ea580c); color:#fff; border:none; }
        .vc-imp-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:.5rem; }
        .vc-imp-opt { position:relative; margin:0; cursor:pointer; }
        .vc-imp-opt input { position:absolute; opacity:0; }
        .vc-imp-opt span { display:flex; flex-direction:column; gap:2px; border:2px solid #e2e8f0; border-radius:10px; padding:.55rem .7rem; transition:all .15s; }
        .vc-imp-opt b { font-size:.88rem; color:#0f172a; }
        .vc-imp-opt small { font-size:.72rem; color:#64748b; }
        .vc-imp-opt:hover span { border-color:#fdba74; }
        .vc-imp-opt input:checked + span { border-color:#ea580c; background:#fff7ed; box-shadow:0 0 0 3px rgba(234,88,12,.15); }
        .vc-imp-opt input:focus-visible + span { outline:2px solid #ea580c; outline-offset:2px; }
        .vc-imp-help { font-size:.78rem; color:#475569; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:.55rem .7rem; }
        .vc-imp-help code { font-size:.72rem; color:#9a3412; }
        .sr-tm-btn { border:none; border-radius:6px; padding:2px 9px; font-size:11px; font-weight:800; color:#fff; background:linear-gradient(135deg,#4f46e5,#db2777); box-shadow:0 2px 6px rgba(79,70,229,.35); }
        .sr-tm-btn:hover { filter:brightness(1.1); }
        .sr-cchip { display:inline-block; padding:1px 8px; border-radius:999px; font-size:11px; font-weight:700; color:#fff; margin:1px 3px 1px 0; white-space:nowrap; }
        .sr-cchip.fedex { background:#6d28d9; }
        .sr-cchip.ups { background:#7c4a1e; }
        .sr-cchip.usps { background:#1d4ed8; }
        .sr-cchip.dhl { background:#c2410c; }
        .sr-cchip.amazon { background:#1f2937; }
        .sr-cchip.other { background:#64748b; }
        #vcSkuModal .modal-header { background:linear-gradient(120deg,#4f46e5 0%,#7c3aed 35%,#db2777 70%,#f97316 100%); color:#fff; border:none; padding:1rem 1.5rem; }
        #vcSkuModal .modal-header .btn-close { filter:invert(1) grayscale(1) brightness(2); }
        #vcSkuModal .sr-sub { font-size:.8rem; opacity:.9; }
        .sr-kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:.75rem; margin-bottom:1rem; }
        .sr-kpi { border-radius:14px; padding:.8rem 1rem; color:#fff; box-shadow:0 6px 18px rgba(15,23,42,.12); position:relative; overflow:hidden; }
        .sr-kpi::after { content:''; position:absolute; right:-18px; top:-18px; width:80px; height:80px; border-radius:50%; background:rgba(255,255,255,.15); }
        .sr-kpi .k { font-size:.72rem; font-weight:800; text-transform:uppercase; letter-spacing:.05em; text-shadow:0 1px 2px rgba(0,0,0,.25); }
        .sr-kpi .v { font-size:1.45rem; font-weight:800; line-height:1.2; text-shadow:0 1px 3px rgba(0,0,0,.3); }
        .sr-kpi i { position:absolute; right:12px; bottom:10px; font-size:1.4rem; opacity:.35; }
        .sr-k1 { background:linear-gradient(135deg,#4338ca,#6d28d9); }
        .sr-k2 { background:linear-gradient(135deg,#0369a1,#0e7490); }
        .sr-k3 { background:linear-gradient(135deg,#b91c1c,#c2410c); }
        .sr-k4 { background:linear-gradient(135deg,#b45309,#92400e); }
        .sr-k5 { background:linear-gradient(135deg,#047857,#15803d); }
        .sr-k6 { background:linear-gradient(135deg,#be185d,#a21caf); }
        .sr-section { font-size:.75rem; font-weight:800; text-transform:uppercase; letter-spacing:.05em; color:#334155; margin:.25rem 0 .5rem; }
        .sr-carriers { display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:.75rem; margin-bottom:1rem; }
        .sr-carrier { border-radius:14px; padding:.75rem 1rem; color:#fff; box-shadow:0 6px 18px rgba(15,23,42,.12); }
        .sr-carrier .cn { font-size:1rem; font-weight:800; display:flex; justify-content:space-between; align-items:center; text-shadow:0 1px 2px rgba(0,0,0,.3); }
        .sr-carrier .cp { font-size:1.35rem; font-weight:800; text-shadow:0 1px 3px rgba(0,0,0,.3); }
        .sr-carrier .cm { font-size:.8rem; font-weight:600; opacity:.95; }
        .sr-carrier .ct { height:8px; border-radius:999px; background:rgba(255,255,255,.3); margin-top:.45rem; overflow:hidden; }
        .sr-carrier .ct span { display:block; height:100%; background:#fff; border-radius:999px; }
        .sr-carrier .ct.ctv { margin-top:.3rem; }
        .sr-carrier .ct.ctv span { background:#fde047; }
        .sr-carrier .cps { display:flex; gap:.9rem; }
        .sr-carrier .cpb { display:flex; flex-direction:column; align-items:flex-end; line-height:1.05; }
        .sr-carrier .cpb small { font-size:.62rem; font-weight:800; text-transform:uppercase; letter-spacing:.04em; opacity:.9; }
        .sr-carrier .cp.cv { color:#fde047; }
        .sr-c-fedex { background:linear-gradient(135deg,#4d148c,#7e22ce); }
        .sr-c-ups { background:linear-gradient(135deg,#4b2e14,#7c4a1e); }
        .sr-c-usps { background:linear-gradient(135deg,#1e3a8a,#1d4ed8); }
        .sr-c-dhl { background:linear-gradient(135deg,#b91c1c,#d97706); }
        .sr-c-amazon { background:linear-gradient(135deg,#111827,#c2410c); }
        .sr-c-other { background:linear-gradient(135deg,#334155,#475569); }
        .sr-tools { display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; margin-bottom:.75rem; }
        .sr-tools .btn-sort { border-radius:999px; font-weight:600; }
        .sr-tools .btn-sort.active { background:linear-gradient(135deg,#4f46e5,#db2777); color:#fff; border-color:transparent; }
        #vcSkuTable { border-radius:12px; overflow:hidden; border:1px solid #e2e8f0; background:#fff; }
        #vcSkuTable .tabulator-header { background:linear-gradient(90deg,#312e81,#6d28d9,#be185d) !important; }
        #vcSkuTable .tabulator-col { background:transparent !important; color:#fff !important; border-right:1px solid rgba(255,255,255,.15); }
        #vcSkuTable .tabulator-col-title { font-size:11px; font-weight:700; text-transform:uppercase; white-space:normal; }
        #vcSkuTable .tabulator-header-filter input { border:none; border-radius:4px; padding:3px 5px; font-size:11px; color:#333; }
        #vcSkuTable .tabulator-row:nth-child(even) { background:#faf5ff; }
        #vcSkuTable .tabulator-row:hover { background:#fce7f3 !important; }
        #vcSkuTable .tabulator-cell { font-size:12.5px; }
        .sr-rank { display:inline-flex; width:26px; height:26px; border-radius:50%; align-items:center; justify-content:center; font-weight:800; font-size:11px; color:#fff; background:#94a3b8; }
        .sr-rank.r1 { background:linear-gradient(135deg,#f59e0b,#ef4444); }
        .sr-rank.r2 { background:linear-gradient(135deg,#94a3b8,#64748b); }
        .sr-rank.r3 { background:linear-gradient(135deg,#d97706,#92400e); }
        .sr-sku { font-weight:700; color:#4c1d95; }
        .sr-amt { min-width:140px; }
        .sr-amt b { display:block; font-size:13px; font-weight:800; color:#b91c1c; line-height:1.2; }
        .sr-amt .sr-track { height:6px; border-radius:999px; background:#fee2e2; margin-top:3px; overflow:hidden; }
        .sr-amt .sr-track span { display:block; height:100%; border-radius:999px; background:linear-gradient(90deg,#f97316,#dc2626); }
        .btn-sku-report, .btn-sku-report:focus, .btn-sku-report:disabled { color:#fff !important; opacity:1; text-shadow:0 1px 2px rgba(0,0,0,.3); }
        .sr-count { display:inline-block; min-width:30px; text-align:center; padding:1px 8px; border-radius:999px; background:#ede9fe; color:#5b21b6; font-weight:700; }
        .sr-sold { color:#0369a1; font-weight:700; }
        .sr-pct { display:inline-block; min-width:62px; text-align:center; padding:2px 8px; border-radius:999px; font-weight:800; font-size:12px; }
        .sr-pct.low { background:#dcfce7; color:#166534; }
        .sr-pct.mid { background:#fef3c7; color:#92400e; }
        .sr-pct.high { background:#fee2e2; color:#991b1b; }
        .sr-pct.na { background:#f1f5f9; color:#64748b; font-weight:600; }
        .sr-legend { font-size:.75rem; color:#475569; display:flex; gap:.6rem; align-items:center; flex-wrap:wrap; }
        #vcRaw { white-space:pre-wrap; word-break:break-word; font-family:ui-monospace,Consolas,monospace; font-size:12px; max-height:65vh; overflow:auto; }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('layouts.shared/page-title', [
        'page_title' => 'Shipping Chargebacks',
        'sub_title' => 'Carrier label charges and adjustments from Veeqo, Amazon, TikTok, Shopify and Temu',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card vc-card">
                <div class="card-body">
                    <div class="vc-badges" id="vcBadges"></div>

                    <div class="vc-toolbar">
                        <label>From <input type="date" id="vcFrom" class="form-control form-control-sm"></label>
                        <label>To <input type="date" id="vcTo" class="form-control form-control-sm"></label>
                        <label>Source
                            <select id="vcSource" class="form-select form-select-sm">
                                <option value="">All</option>
                                <option value="veeqo_csv">Veeqo card (CSV)</option>
                                <option value="amazon_api">Amazon settlement (API)</option>
                                <option value="tiktok_api">TikTok 1 (API)</option>
                                <option value="tiktok2_api">TikTok 2 (API)</option>
                                <option value="shopify_csv">Shopify (CSV)</option>
                                <option value="temu1_csv">Temu 1 (CSV)</option>
                                <option value="temu2_csv">Temu 2 (CSV)</option>
                                <option value="temu3_csv">Temu 3 (CSV)</option>
                            </select>
                        </label>
                        <div class="vc-field">Type
                            <div class="dropdown">
                                <button type="button" id="vcTypeBtn" class="form-select form-select-sm text-start vc-type-btn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">All chargebacks</button>
                                <div class="dropdown-menu vc-type-menu" id="vcTypeMenu"></div>
                            </div>
                        </div>
                        <label>Reason <select id="vcReason" class="form-select form-select-sm"><option value="">All reasons</option></select></label>
                        <label>Debit / Credit
                            <select id="vcDirection" class="form-select form-select-sm">
                                <option value="">All</option>
                                <option value="debit" selected>Debit</option>
                                <option value="credit">Credit</option>
                            </select>
                        </label>
                        <label>Status <select id="vcStatus" class="form-select form-select-sm"><option value="">All</option></select></label>
                        <label style="min-width:220px;flex:1;">Search
                            <input type="search" id="vcSearch" class="form-control form-control-sm" placeholder="Order, reason, service, settlement, transaction…">
                        </label>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" title="Columns">
                                <i class="fas fa-columns"></i>
                            </button>
                            <div class="dropdown-menu" id="vc-col-dropdown"></div>
                        </div>
                        <button type="button" class="btn btn-sm btn-sku-report" id="vcSkuReport" title="Chargebacks per SKU for the last 12 months"><i class="fas fa-chart-bar"></i> Report SKU wise</button>
                        <button type="button" class="btn btn-sm btn-primary" id="vcExport"><i class="fas fa-download"></i> Export page</button>
                        <button type="button" class="btn btn-sm btn-success" id="vcSync" title="Pull the last 30 days from Amazon Finances"><i class="fas fa-sync"></i> Sync Amazon</button>
                        <button type="button" class="btn btn-sm btn-dark" id="vcSyncTikTok" title="Pull the last 30 days of TikTok 1 and TikTok 2 shipping charges from the TikTok Finance API (also runs daily)"><i class="fab fa-tiktok"></i> Sync TikTok</button>
                        <button type="button" class="btn btn-sm btn-warning" id="vcImport" title="Upload a Veeqo, Shopify or Temu shipping charges CSV"><i class="fas fa-file-upload"></i> Import CSV</button>
                    </div>
                    <div class="vc-sync mb-2" id="vcSyncInfo"></div>

                    <div id="veeqo-chargebacks-table"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="vcSkuModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h4 class="modal-title mb-0"><i class="fas fa-chart-bar me-2"></i>Chargebacks by SKU · last 12 months</h4>
                        <div class="sr-sub" id="vcSkuSub">Loading…</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="sr-kpis" id="vcSkuKpis"></div>
                    <div class="sr-section"><i class="fas fa-truck me-1"></i> Chargebacks by carrier</div>
                    <div class="sr-carriers" id="vcSkuCarriers"></div>
                    <div class="sr-tools">
                        <span class="fw-bold text-secondary small text-uppercase">Sort by chargeback amount</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-sort active" data-dir="desc"><i class="fas fa-sort-amount-down"></i> Max → Min</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-sort" data-dir="asc"><i class="fas fa-sort-amount-up"></i> Min → Max</button>
                        <input type="search" id="vcSkuSearch" class="form-control form-control-sm" style="max-width:220px;" placeholder="Search SKU…">
                        <div class="sr-legend ms-auto">
                            Chargeback rate:
                            <span class="sr-pct low">&lt; 1%</span>
                            <span class="sr-pct mid">1–3%</span>
                            <span class="sr-pct high">&gt; 3%</span>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="vcFindSkus" title="Look up orders without a SKU in the Veeqo API (also runs hourly)"><i class="fas fa-search"></i> Find missing SKUs in Veeqo</button>
                        <button type="button" class="btn btn-sm btn-outline-success" id="vcFillOsize" title="Write each SKU's Avg Chargeback Total (as a positive figure) into Shipping Master O-Size Charge. Ship, Temu ship and Ship BB totals update. Also runs daily."><i class="fas fa-truck-loading"></i> Fill Shipping Master O-Size</button>
                        <button type="button" class="btn btn-sm btn-sku-report" id="vcSkuExport"><i class="fas fa-download"></i> Export CSV</button>
                    </div>
                    <div id="vcSkuTable"></div>
                    <div class="small text-muted mt-2">
                        Uses the Source / Type / Debit-Credit filters from the page. Chargeback count = orders charged back (Veeqo splits one chargeback into a line per reason; those are the charge lines).
                        Orders with several SKUs count for each SKU and their amount is split evenly. Units sold = 12-month sales across all channels. Avg chargeback total = total chargeback ÷ units sold. Chargeback % = chargeback count ÷ units sold. Chargeback%TC = chargeback count ÷ total chargeback count of all SKUs.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="vcImportModal" tabindex="-1" aria-labelledby="vcImportLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content vc-import">
                <div class="modal-header">
                    <h6 class="modal-title mb-0" id="vcImportLabel"><i class="fas fa-file-upload me-2"></i>Import shipping charges CSV</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="vcImportForm">
                    <div class="modal-body">
                        <label class="form-label fw-semibold mb-2">Which CSV are you uploading?</label>
                        <div class="vc-imp-grid mb-3" role="radiogroup">
                            <label class="vc-imp-opt"><input type="radio" name="vcImpPlatform" value="veeqo" checked><span><b>Veeqo</b><small>Reports → transactions</small></span></label>
                            <label class="vc-imp-opt"><input type="radio" name="vcImpPlatform" value="shopify"><span><b>Shopify</b><small>Bills → charges</small></span></label>
                            <label class="vc-imp-opt"><input type="radio" name="vcImpPlatform" value="temu1"><span><b>Temu 1</b><small>Transactions</small></span></label>
                            <label class="vc-imp-opt"><input type="radio" name="vcImpPlatform" value="temu2"><span><b>Temu 2</b><small>Transactions</small></span></label>
                            <label class="vc-imp-opt"><input type="radio" name="vcImpPlatform" value="temu3"><span><b>Temu 3</b><small>Transactions</small></span></label>
                        </div>
                        <div class="vc-imp-help mb-3" id="vcImpHelp"></div>
                        <label class="form-label fw-semibold" for="vcImportFile">CSV file</label>
                        <input type="file" id="vcImportFile" class="form-control" accept=".csv,text/csv" required>
                        <div class="small mt-2 d-none" id="vcImpResult"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-warning fw-semibold" id="vcImpSubmit"><i class="fas fa-upload me-1"></i>Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="vcRawModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="vcRawTitle">Transaction</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body"><div id="vcRaw"></div></div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        (function () {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const COL_KEY = 'veeqo_chargebacks_hidden_cols_v1';
            let table = null;
            let rawModal = null;
            let components = [];

            function esc(s) {
                return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }
            function money(v, signed) {
                if (v === null || v === undefined || v === '') return '';
                const n = Number(v);
                if (isNaN(n)) return esc(v);
                const txt = (n < 0 ? '-$' : '$') + Math.abs(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                return signed ? '<span class="' + (n < 0 ? 'vc-neg' : 'vc-pos') + '">' + txt + '</span>' : txt;
            }
            function label(type) {
                return type.replace(/^(Return)?PostageBilling_/, '$1 ').replace(/([a-z])([A-Z])/g, '$1 $2').trim();
            }
            function params() {
                return {
                    from: document.getElementById('vcFrom').value,
                    to: document.getElementById('vcTo').value,
                    source: document.getElementById('vcSource').value,
                    types: selectedTypes.join('|'),
                    reason: document.getElementById('vcReason').value,
                    direction: document.getElementById('vcDirection').value,
                    status: document.getElementById('vcStatus').value,
                    search: document.getElementById('vcSearch').value.trim(),
                };
            }
            function qs(obj) {
                return Object.keys(obj).filter(k => obj[k] !== '').map(k => encodeURIComponent(k) + '=' + encodeURIComponent(obj[k])).join('&');
            }
            function col(title, field, extra) {
                return Object.assign({ title: title, field: field, headerFilter: 'input', headerFilterLiveFilter: false, minWidth: 90 }, extra || {});
            }
            function moneyCol(title, field, sortable) {
                return col(title, field, { hozAlign: 'right', formatter: c => money(c.getValue(), true), headerFilter: false, headerSort: sortable !== false });
            }
            function plain(title, field, extra) {
                return col(title, field, Object.assign({ headerFilter: false, headerSort: false }, extra || {}));
            }

            function columns() {
                return [
                    {
                        title: '', field: '_view', width: 44, headerSort: false, hozAlign: 'center', frozen: true,
                        formatter: () => '<button type="button" class="btn btn-sm btn-outline-info py-0 px-1" title="Full Amazon transaction"><i class="fas fa-eye"></i></button>',
                        cellClick: (e, cell) => openRaw(cell.getRow().getData()),
                    },
                    col('Posted', 'posted_at', { frozen: true, minWidth: 130 }),
                    col('Order #', 'order_number', { frozen: true, minWidth: 150 }),
                    moneyCol('Chargeback', 'total_amount'),
                    col('Reason', 'reason', { minWidth: 170 }),
                    col('Source', 'source', { minWidth: 100, formatter: c => SOURCE_BADGES[c.getValue()] || esc(c.getValue() || '') }),
                    plain('Channel', 'v_channel'),
                    col('Carrier', 'carrier'),
                    col('Service', 'service', { minWidth: 180 }),
                    moneyCol('You Paid (label)', 'customer_charge'),
                    moneyCol('Carrier Billed', 'carrier_charge'),
                    ...components.map(t => moneyCol(label(t), 'c_' + t, false)),
                    col('Currency', 'currency', { minWidth: 70 }),
                    col('Type', 'description', { minWidth: 170 }),
                    col('Debit/Credit', 'direction', { minWidth: 80 }),
                    col('Status', 'transaction_status'),
                    col('Payment Method', 'payment_method', { minWidth: 140 }),
                    col('Items', 'item_types', { minWidth: 220 }),
                    plain('Veeqo Shipped', 'v_shipped_at', { minWidth: 130 }),
                    plain('Tracking #', 'v_tracking', { minWidth: 180 }),
                    plain('Veeqo Carrier', 'v_carrier'),
                    plain('Veeqo Service', 'v_service', { minWidth: 180 }),
                    plain('Veeqo Label Cost', 'v_label_cost', { hozAlign: 'right', formatter: c => money(c.getValue()) }),
                    plain('Labels', 'v_labels', { hozAlign: 'right', minWidth: 60 }),
                    plain('Weight', 'v_weight'),
                    plain('Tracking Status', 'v_status'),
                    plain('SKUs', 'v_skus', { minWidth: 160 }),
                    col('Amazon Order', 'amazon_order_id', { minWidth: 150 }),
                    col('Settlement ID', 'settlement_id', { minWidth: 120 }),
                    col('Event Group ID', 'financial_event_group_id', { minWidth: 200 }),
                    col('Marketplace', 'marketplace_name'),
                    col('Transaction Type', 'transaction_type'),
                    col('Transaction ID', 'transaction_id', { minWidth: 200 }),
                ];
            }

            function hiddenCols() {
                try { return JSON.parse(localStorage.getItem(COL_KEY) || '[]'); } catch (_) { return []; }
            }

            function buildColumnPicker() {
                const menu = document.getElementById('vc-col-dropdown');
                if (!menu || !table) return;
                const hidden = new Set(hiddenCols());
                menu.innerHTML = '';
                table.getColumns().forEach(function (c) {
                    const def = c.getDefinition();
                    if (!def.field || def.field === '_view') return;
                    if (hidden.has(def.field)) c.hide();
                    const row = document.createElement('label');
                    const cb = document.createElement('input');
                    cb.type = 'checkbox';
                    cb.checked = !hidden.has(def.field);
                    cb.addEventListener('change', function () {
                        const now = new Set(hiddenCols());
                        if (cb.checked) { c.show(); now.delete(def.field); } else { c.hide(); now.add(def.field); }
                        localStorage.setItem(COL_KEY, JSON.stringify(Array.from(now)));
                    });
                    row.appendChild(cb);
                    row.appendChild(document.createTextNode(def.title));
                    menu.appendChild(row);
                });
            }

            function buildTable() {
                if (table) table.destroy();
                table = new Tabulator('#veeqo-chargebacks-table', {
                    layout: 'fitDataStretch',
                    height: 'calc(100vh - 330px)',
                    placeholder: 'No rows for these filters. Check that the Type selection includes types from the chosen Source, and the Debit / Credit and date filters.',
                    pagination: true,
                    paginationMode: 'remote',
                    paginationSize: 100,
                    paginationSizeSelector: [50, 100, 250, 500, 1000],
                    paginationCounter: 'rows',
                    sortMode: 'remote',
                    filterMode: 'remote',
                    ajaxURL: '{{ route('veeqo.chargebacks.data') }}',
                    ajaxParams: params,
                    ajaxResponse: (url, p, response) => response,
                    columns: columns(),
                });
                table.on('tableBuilt', buildColumnPicker);
            }

            const TYPE_KEY = 'veeqo_chargebacks_default_types_v1';
            const TYPE_OPTIONS = [
                ['Chargeback Debit - Outbound', 'Chargeback Debit - Outbound', 'Veeqo'],
                ['Chargeback Credit - Outbound', 'Chargeback Credit - Outbound', 'Veeqo'],
                ['LabmanLabelChargeBack', 'Label chargeback', 'Amazon'],
                ['LabmanLabelPurchase', 'Label purchase', 'Amazon'],
                ['LabmanLabelRefund', 'Label refund', 'Amazon'],
                ['LabmanLabelReturn', 'Return label', 'Amazon'],
                ['TikTok Shipping Fee Adjustment', 'Shipping fee adjustment', 'TikTok'],
                ['TikTok Shipping Fee Compensation', 'Shipping fee compensation', 'TikTok'],
                ['TikTok Logistics Reimbursement', 'Logistics reimbursement', 'TikTok'],
                ['TikTok Shipping Fee Rebate', 'Shipping fee rebate', 'TikTok'],
                ['TikTok Sample Shipping Fee', 'Sample shipping fee', 'TikTok'],
                ['TikTok Shipping Label', 'Shipping label (order)', 'TikTok'],
                ['Shopify Label Price Adjustment', 'Label price adjustment', 'Shopify'],
                ['Shopify Shipping Label', 'Label purchase', 'Shopify'],
                ['Temu Label Purchase Adjustment', 'Label purchase adjustment', 'Temu'],
            ];
            const CHARGEBACK_TYPES = ['Chargeback Debit - Outbound', 'Chargeback Credit - Outbound', 'LabmanLabelChargeBack', 'TikTok Shipping Fee Adjustment', 'TikTok Shipping Fee Compensation', 'Shopify Label Price Adjustment', 'Temu Label Purchase Adjustment'];
            const SOURCE_LABELS = { veeqo_csv: 'Veeqo CSV', amazon_api: 'Amazon API', tiktok_api: 'TikTok 1 API', tiktok2_api: 'TikTok 2 API', shopify_csv: 'Shopify CSV', temu1_csv: 'Temu 1 CSV', temu2_csv: 'Temu 2 CSV', temu3_csv: 'Temu 3 CSV' };
            const SOURCE_BADGES = {
                veeqo_csv: '<span class="badge bg-warning text-dark">Veeqo CSV</span>',
                amazon_api: '<span class="badge bg-info text-dark">Amazon API</span>',
                tiktok_api: '<span class="badge bg-dark">TikTok 1 API</span>',
                tiktok2_api: '<span class="badge bg-secondary">TikTok 2 API</span>',
                shopify_csv: '<span class="badge bg-success">Shopify CSV</span>',
                temu1_csv: '<span class="badge" style="background:#ea580c">Temu 1 CSV</span>',
                temu2_csv: '<span class="badge" style="background:#c2410c">Temu 2 CSV</span>',
                temu3_csv: '<span class="badge" style="background:#9a3412">Temu 3 CSV</span>',
            };
            let typeOptions = TYPE_OPTIONS.slice();
            let selectedTypes = (function () {
                try {
                    const saved = JSON.parse(localStorage.getItem(TYPE_KEY) || 'null');
                    if (Array.isArray(saved)) return saved;
                } catch (_) {}
                return CHARGEBACK_TYPES.slice();
            })();
            let typesDirty = false;

            const SOURCE_GROUP = { veeqo_csv: 'Veeqo', amazon_api: 'Amazon', tiktok_api: 'TikTok', tiktok2_api: 'TikTok', shopify_csv: 'Shopify', temu1_csv: 'Temu', temu2_csv: 'Temu', temu3_csv: 'Temu' };

            // A source only has its own types, so a selection made for another platform would show nothing.
            function alignTypesToSource() {
                const group = SOURCE_GROUP[document.getElementById('vcSource').value];
                if (!group || selectedTypes.length === 0) return;
                const groupTypes = typeOptions.filter(o => o[2] === group).map(o => o[0]);
                if (selectedTypes.some(t => groupTypes.includes(t))) return;
                const chargebacks = groupTypes.filter(t => CHARGEBACK_TYPES.includes(t));
                selectedTypes = chargebacks.length ? chargebacks : groupTypes;
                renderTypeMenu();
            }

            function typeButtonText() {
                if (selectedTypes.length === 0 || selectedTypes.length === typeOptions.length) return 'All label adjustments';
                if (selectedTypes.length === CHARGEBACK_TYPES.length && CHARGEBACK_TYPES.every(t => selectedTypes.includes(t))) return 'All chargebacks';
                if (selectedTypes.length === 1) return (typeOptions.find(o => o[0] === selectedTypes[0]) || [, selectedTypes[0]])[1];
                return selectedTypes.length + ' types selected';
            }

            function renderTypeMenu(dbTypes) {
                (dbTypes || []).forEach(function (t) {
                    if (!typeOptions.some(o => o[0] === t)) typeOptions.push([t, t, 'Other']);
                });
                const menu = document.getElementById('vcTypeMenu');
                const isSaved = JSON.stringify((JSON.parse(localStorage.getItem(TYPE_KEY) || 'null') || CHARGEBACK_TYPES).slice().sort()) === JSON.stringify(selectedTypes.slice().sort());
                const sameSet = list => list.length === selectedTypes.length && list.every(t => selectedTypes.includes(t));
                const preset = sameSet(CHARGEBACK_TYPES) ? 'chargebacks' : (sameSet(typeOptions.map(o => o[0])) ? 'all' : (selectedTypes.length ? '' : 'none'));
                let html = '<div class="vc-type-head"><span class="vc-type-title">Transaction types</span>' +
                    '<span class="vc-type-count">' + selectedTypes.length + ' of ' + typeOptions.length + '</span></div>' +
                    '<div class="vc-type-quick">' +
                    '<button type="button" data-preset="chargebacks"' + (preset === 'chargebacks' ? ' class="on"' : '') + '>All chargebacks</button>' +
                    '<button type="button" data-preset="all"' + (preset === 'all' ? ' class="on"' : '') + '>Select all</button>' +
                    '<button type="button" data-preset="none"' + (preset === 'none' ? ' class="on"' : '') + '>Clear</button>' +
                    '</div><div class="vc-type-list">';
                let group = '';
                typeOptions.forEach(function (o) {
                    if (o[2] !== group) { group = o[2]; html += '<div class="vc-type-group">' + esc(group) + '</div>'; }
                    const on = selectedTypes.includes(o[0]);
                    html += '<label class="vc-type-opt' + (on ? ' sel' : '') + '"><input type="checkbox" class="form-check-input" value="' + esc(o[0]) + '"' + (on ? ' checked' : '') + '>' +
                        '<span class="vc-type-txt"><span>' + esc(o[1]) + '</span>' + (o[1] !== o[0] ? '<span class="vc-type-code">' + esc(o[0]) + '</span>' : '') + '</span></label>';
                });
                html += '</div><div class="vc-type-foot">' +
                    (isSaved ? '<span class="vc-type-saved"><i class="fas fa-check-circle"></i> Saved as default</span>' : '<span class="vc-type-unsaved">Not saved as default</span>') +
                    '<button type="button" class="btn btn-sm btn-primary" id="vcTypeSave"' + (isSaved ? ' disabled' : '') + '><i class="fas fa-save me-1"></i>Save as default</button></div>';
                menu.innerHTML = html;
                document.getElementById('vcTypeBtn').textContent = typeButtonText();

                menu.querySelectorAll('input[type=checkbox]').forEach(cb => cb.addEventListener('change', function () {
                    selectedTypes = Array.from(menu.querySelectorAll('input[type=checkbox]:checked')).map(x => x.value);
                    typesDirty = true;
                    renderTypeMenu();
                }));
                menu.querySelectorAll('[data-preset]').forEach(btn => btn.addEventListener('click', function () {
                    const p = btn.dataset.preset;
                    selectedTypes = p === 'all' ? typeOptions.map(o => o[0]) : (p === 'none' ? [] : CHARGEBACK_TYPES.slice());
                    typesDirty = true;
                    renderTypeMenu();
                }));
                document.getElementById('vcTypeSave').addEventListener('click', function () {
                    localStorage.setItem(TYPE_KEY, JSON.stringify(selectedTypes));
                    renderTypeMenu();
                    if (typesDirty) { typesDirty = false; refresh(); }
                });
            }

            const FIXED_OPTIONS = {
                vcReason: [
                    ['', 'All reasons'],
                    ['Additional Base Rate Charge', 'Additional Base Rate Charge'],
                    ['Fuel Surcharge', 'Fuel Surcharge'],
                    ['Residential Area Delivery Surcharge', 'Residential Area Delivery Surcharge'],
                    ['Delivery Area Surcharge', 'Delivery Area Surcharge'],
                    ['Oversize Charge', 'Oversize Charge'],
                    ['Returned Shipment or Delivery Intercept Charge', 'Returned Shipment or Delivery Intercept Charge'],
                    ['ADJUSTMENT', 'Adjustment'],
                    ['Others', 'Others'],
                ],
                vcStatus: [['', 'All']],
            };

            function setOptions(id, values) {
                const sel = document.getElementById(id);
                const current = sel.value;
                const opts = (FIXED_OPTIONS[id] || [['', 'All']]).slice();
                const known = new Set(opts.map(o => o[0]));
                (values || []).concat(current ? [current] : []).forEach(function (v) {
                    if (!known.has(v)) { known.add(v); opts.push([v, v]); }
                });
                sel.innerHTML = opts.map(o => '<option value="' + esc(o[0]) + '">' + esc(o[1]) + '</option>').join('');
                sel.value = current;
            }

            function badge(k, v, cls) {
                return '<div class="vc-badge ' + (cls || '') + '"><div class="vc-k">' + esc(k) + '</div><div class="vc-v">' + v + '</div></div>';
            }

            async function loadSummary(rebuildIfChanged) {
                const res = await fetch('{{ route('veeqo.chargebacks.summary') }}?' + qs(params()), { headers: { Accept: 'application/json' } });
                const json = await res.json();
                const t = json.totals || {};
                document.getElementById('vcBadges').innerHTML =
                    badge('Adjustments', Number(t.rows_count || 0).toLocaleString()) +
                    badge('Orders', Number(t.orders || 0).toLocaleString()) +
                    badge('Debits', money(t.debit_total || 0), 'vc-debit') +
                    badge('Credits', money(t.credit_total || 0), 'vc-credit') +
                    badge('Net', money(t.net_total || 0), Number(t.net_total) < 0 ? 'vc-debit' : 'vc-credit') +
                    badge('Veeqo card net', money(t.veeqo_net || 0), Number(t.veeqo_net) < 0 ? 'vc-debit' : 'vc-credit') +
                    badge('Amazon settlement net', money(t.amazon_net || 0), Number(t.amazon_net) < 0 ? 'vc-debit' : 'vc-credit') +
                    badge('TikTok net', money(t.tiktok_net || 0), Number(t.tiktok_net) < 0 ? 'vc-debit' : 'vc-credit') +
                    badge('Shopify net', money(t.shopify_net || 0), Number(t.shopify_net) < 0 ? 'vc-debit' : 'vc-credit') +
                    badge('Temu net', money(t.temu_net || 0), Number(t.temu_net) < 0 ? 'vc-debit' : 'vc-credit');

                const f = json.filters || {};
                renderTypeMenu(f.types);
                setOptions('vcReason', f.reasons);
                setOptions('vcStatus', f.statuses);

                const s = json.sync || {};
                const day = v => String(v || '').slice(0, 10);
                let info = Number(s.rows || 0).toLocaleString() + ' rows stored';
                info += s.csv_first
                    ? ' · Veeqo CSV ' + day(s.csv_first) + ' → ' + day(s.csv_last) + ' (imported ' + String(s.csv_imported_at).replace('T', ' ').slice(0, 16) + ')'
                    : ' · no Veeqo CSV imported yet';
                if (s.api_first) info += ' · Amazon API ' + day(s.api_first) + ' → ' + day(s.api_last) + ' (synced ' + String(s.api_synced_at).replace('T', ' ').slice(0, 16) + ')';
                if (s.tiktok_last) info += ' · TikTok API to ' + day(s.tiktok_last) + ' (synced ' + String(s.tiktok_synced_at).replace('T', ' ').slice(0, 16) + ')';
                (s.imports || []).forEach(function (im) {
                    info += ' · ' + (SOURCE_LABELS[im.source] || im.source) + ' ' + day(im.first) + ' → ' + day(im.last) + ' (imported ' + String(im.imported_at).replace('T', ' ').slice(0, 16) + ')';
                });
                document.getElementById('vcSyncInfo').textContent = info;

                const comps = json.components || [];
                if (rebuildIfChanged && comps.join(',') !== components.join(',')) {
                    components = comps;
                    buildTable();
                }
                return json;
            }

            function refresh() {
                if (table) table.setData();
                loadSummary(true);
            }

            async function openRaw(row) {
                document.getElementById('vcRawTitle').textContent = (row.description || 'Transaction') + ' · ' + (row.order_number || '');
                document.getElementById('vcRaw').textContent = 'Loading…';
                rawModal.show();
                try {
                    const res = await fetch('/veeqo-chargebacks/transaction/' + encodeURIComponent(row.id), { headers: { Accept: 'application/json' } });
                    const json = await res.json();
                    document.getElementById('vcRaw').textContent = JSON.stringify(json.transaction || json, null, 2);
                } catch (e) {
                    document.getElementById('vcRaw').textContent = 'Could not load transaction.';
                }
            }

            async function syncNow(btn, url) {
                btn.disabled = true;
                const html = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Syncing…';
                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                    });
                    const json = await res.json();
                    alert(json.message || (res.ok ? 'Sync finished.' : 'Sync failed.'));
                    refresh();
                } catch (e) {
                    alert('Sync failed: ' + e.message);
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = html;
                }
            }

            let skuModal = null;
            let skuTable = null;
            let skuMax = 1;

            function carrierClass(name) {
                const key = String(name || '').toLowerCase();
                return key.includes('fedex') ? 'fedex' : key.includes('usps') ? 'usps' : key === 'ups' ? 'ups' : key.includes('dhl') ? 'dhl' : key.includes('amazon') ? 'amazon' : 'other';
            }
            function fmtPct(v) {
                const n = Number(v) || 0;
                return (Math.abs(n) > 9.9 ? Math.round(n) : n.toFixed(1)) + '%';
            }
            function pctBadge(v) {
                if (v === null || v === undefined) return '<span class="sr-pct na">no sales</span>';
                const n = Number(v);
                const cls = n > 3 ? 'high' : (n >= 1 ? 'mid' : 'low');
                return '<span class="sr-pct ' + cls + '">' + fmtPct(n) + '</span>';
            }
            function absSorter(a, b) { return Math.abs(Number(a) || 0) - Math.abs(Number(b) || 0); }

            function skuKpi(cls, icon, k, v) {
                return '<div class="sr-kpi ' + cls + '"><div class="k">' + esc(k) + '</div><div class="v">' + v + '</div><i class="fas ' + icon + '"></i></div>';
            }

            function buildSkuTable(rows) {
                skuMax = Math.max(1, ...rows.map(r => Math.abs(Number(r.total) || 0)));
                if (skuTable) { skuTable.setData(rows); return; }
                skuTable = new Tabulator('#vcSkuTable', {
                    data: rows,
                    layout: 'fitColumns',
                    height: 'max(380px, calc(100vh - 430px))',
                    placeholder: 'No chargebacks in the last 12 months for these filters.',
                    pagination: true,
                    paginationSize: 100,
                    paginationSizeSelector: [50, 100, 250, 500, true],
                    initialSort: [{ column: 'total', dir: 'desc' }],
                    columns: [
                        { title: '#', field: '_rank', width: 56, hozAlign: 'center', headerSort: false,
                          formatter: c => { const p = c.getRow().getPosition(); return '<span class="sr-rank ' + (p <= 3 ? 'r' + p : '') + '">' + p + '</span>'; } },
                        { title: 'SKU', field: 'sku', minWidth: 200, headerFilter: 'input', formatter: c => '<span class="sr-sku">' + esc(c.getValue()) + '</span>' },
                        { title: 'Carriers', field: 'carrier_text', minWidth: 170, headerFilter: 'input', headerSort: false,
                          headerTooltip: 'Carriers on this SKU\'s charged-back orders (orders per carrier)',
                          formatter: function (c) {
                              const map = c.getRow().getData().carriers || {};
                              return Object.keys(map).map(function (name) {
                                  return '<span class="sr-cchip ' + carrierClass(name) + '">' + esc(name) + ' ' + map[name] + '</span>';
                              }).join('');
                          } },
                        { title: 'Chargeback Count', field: 'count', hozAlign: 'center', width: 130, formatter: c => '<span class="sr-count">' + c.getValue() + '</span>' },
                        { title: 'Charge Lines', field: 'lines', hozAlign: 'center', width: 100, formatter: c => '<span class="text-muted">' + c.getValue() + '</span>' },
                        { title: 'Total Chargeback', field: 'total', minWidth: 190, sorter: absSorter,
                          formatter: c => { const v = Number(c.getValue()) || 0; const w = Math.max(2, Math.round(Math.abs(v) / skuMax * 100)); return '<div class="sr-amt"><b>' + money(v) + '</b><div class="sr-track"><span style="width:' + w + '%"></span></div></div>'; } },
                        { title: 'Avg Chargeback', field: 'average', hozAlign: 'right', width: 140, sorter: absSorter, formatter: c => money(c.getValue(), true) },
                        { title: 'Units Sold (12 mo)', field: 'sold', hozAlign: 'right', width: 150,
                          formatter: c => c.getValue() === null ? '<span class="text-muted">—</span>' : '<span class="sr-sold">' + Number(c.getValue()).toLocaleString() + '</span>' },
                        { title: 'Avg Chargeback Total', field: 'per_unit', hozAlign: 'right', width: 150, sorter: (a, b) => Math.abs(a ?? 0) - Math.abs(b ?? 0),
                          formatter: c => c.getValue() === null ? '<span class="text-muted">—</span>' : money(c.getValue(), true) },
                        { title: 'Chargeback %', field: 'pct', hozAlign: 'center', width: 130, sorter: (a, b) => (a ?? -1) - (b ?? -1), formatter: c => pctBadge(c.getValue()) },
                        { title: 'Chargeback%TC', field: 'pct_tc', hozAlign: 'center', width: 130,
                          headerTooltip: 'Chargeback percentage of total count: this SKU\'s chargeback count ÷ chargeback count of all SKUs',
                          formatter: c => '<span class="sr-pct na" style="color:#4c1d95;background:#ede9fe;">' + fmtPct(c.getValue()) + '</span>' },
                        { title: 'TM', field: '_tm', width: 62, hozAlign: 'center', headerSort: false, frozen: true, download: false,
                          headerTooltip: 'Assign a task for this SKU in Task Manager',
                          formatter: () => '<button type="button" class="sr-tm-btn" title="Assign Task">TM</button>',
                          cellClick: (e, cell) => openTaskForm(cell.getRow().getData()) },
                    ],
                });
                ['dataSorted', 'dataFiltered'].forEach(ev => skuTable.on(ev, () => setTimeout(() => skuTable.getRows('active').forEach(r => r.reformat()), 0)));
            }

            async function openSkuReport() {
                if (!skuModal) skuModal = new bootstrap.Modal(document.getElementById('vcSkuModal'));
                document.getElementById('vcSkuSub').textContent = 'Loading…';
                document.getElementById('vcSkuKpis').innerHTML = '';
                document.getElementById('vcSkuCarriers').innerHTML = '';
                skuModal.show();
                try {
                    const p = params();
                    delete p.from; delete p.to; delete p.search;
                    const res = await fetch('{{ route('veeqo.chargebacks.sku-report') }}?' + qs(p), { headers: { Accept: 'application/json' } });
                    const json = await res.json();
                    const t = json.totals || {};
                    document.getElementById('vcSkuSub').textContent = json.from + ' → ' + json.to + ' · ' + Number(t.chargebacks || 0).toLocaleString() + ' orders charged back (' + Number(t.lines || 0).toLocaleString() + ' charge lines) across ' + Number(t.skus || 0).toLocaleString() + ' SKUs';
                    document.getElementById('vcSkuKpis').innerHTML =
                        skuKpi('sr-k1', 'fa-barcode', 'SKUs hit', Number(t.skus || 0).toLocaleString()) +
                        skuKpi('sr-k2', 'fa-receipt', 'Orders charged back', Number(t.chargebacks || 0).toLocaleString()) +
                        skuKpi('sr-k3', 'fa-dollar-sign', 'Total chargeback', money(t.amount || 0)) +
                        skuKpi('sr-k4', 'fa-balance-scale', 'Avg per order', money(t.average || 0)) +
                        skuKpi('sr-k5', 'fa-box-open', 'Units sold (those SKUs)', Number(t.sold || 0).toLocaleString()) +
                        skuKpi('sr-k6', 'fa-percentage', 'Overall chargeback %', t.pct === null || t.pct === undefined ? '—' : fmtPct(t.pct));
                    document.getElementById('vcSkuCarriers').innerHTML = (json.carriers || []).map(function (c) {
                        return '<div class="sr-carrier sr-c-' + carrierClass(c.carrier) + '">' +
                            '<div class="cn"><span>' + esc(c.carrier) + '</span>' +
                            '<span class="cps"><span class="cpb"><small>By count</small><span class="cp">' + fmtPct(c.pct) + '</span></span>' +
                            '<span class="cpb"><small>By value</small><span class="cp cv">' + fmtPct(c.value_pct) + '</span></span></span></div>' +
                            '<div class="cm">' + Number(c.count).toLocaleString() + ' orders · ' + Number(c.lines).toLocaleString() + ' lines · ' + money(c.amount) + '</div>' +
                            '<div class="ct" title="Share of orders charged back"><span style="width:' + Math.min(100, Number(c.pct)) + '%"></span></div>' +
                            '<div class="ct ctv" title="Share of chargeback amount"><span style="width:' + Math.min(100, Math.max(0, Number(c.value_pct))) + '%"></span></div>' +
                            '</div>';
                    }).join('') || '<div class="text-muted small">No carrier data.</div>';
                    buildSkuTable(json.rows || []);
                    document.querySelectorAll('#vcSkuModal .btn-sort').forEach(b => b.classList.toggle('active', b.dataset.dir === 'desc'));
                } catch (e) {
                    document.getElementById('vcSkuSub').textContent = 'Could not load the SKU report: ' + e.message;
                }
            }

            function taskIssueText(r) {
                const carriers = Object.keys(r.carriers || {}).map(n => n + ' ' + r.carriers[n]).join(', ');
                let text = 'Chargebacks on ' + r.sku + ' (last 12 months): ' + r.count + ' orders charged back, total ' + money(r.total) + ', avg ' + money(r.average) + ' per order';
                if (r.sold) text += ', ' + fmtPct(r.pct) + ' of ' + Number(r.sold).toLocaleString() + ' units sold (' + money(r.per_unit) + ' per unit)';
                if (carriers) text += '. Carriers: ' + carriers;
                return text + '. Check listed weight/dimensions and packaging to stop carrier adjustments.';
            }

            let taskFormWatcher = null;

            function openTaskForm(row) {
                const panel = document.getElementById('floating-task-form');
                if (typeof window.openQuickTaskForm !== 'function' || !panel) {
                    alert('Task Manager form is not available on this page.');
                    return;
                }
                const reopenReport = skuModal && document.getElementById('vcSkuModal').classList.contains('show');
                const open = function () {
                    window.openQuickTaskForm({
                        group: row.sku || '',
                        title: taskIssueText(row).slice(0, 1000),
                        assignorId: {{ (int) (Auth::id() ?? 0) }} || undefined,
                        referenceLink: window.location.origin + window.location.pathname,
                    });
                    if (!reopenReport) return;
                    if (taskFormWatcher) taskFormWatcher.disconnect();
                    taskFormWatcher = new MutationObserver(function () {
                        if (panel.style.display !== 'none') return;
                        taskFormWatcher.disconnect();
                        taskFormWatcher = null;
                        skuModal.show();
                    });
                    taskFormWatcher.observe(panel, { attributes: true, attributeFilter: ['style'] });
                };
                if (!reopenReport) return open();
                document.getElementById('vcSkuModal').addEventListener('hidden.bs.modal', open, { once: true });
                skuModal.hide();
            }

            const IMPORT_HELP = {
                veeqo: 'Veeqo → Reports → shipping transactions, file like <code>reports-…_AsiaKolkata_original.csv</code>. Columns: <code>Type, Transaction ID, Value, Created Date, Order ID</code>. Only Chargeback Debit/Credit rows are kept.',
                shopify: 'Shopify admin → Settings → Billing → Bills/charges export. Columns: <code>Charge category, Description, Amount, Order, Charge date</code>. Label purchases and Shipping Label Price Adjustments are imported.',
                temu: 'Temu Seller Center → Finance → Transactions by custom date range. Columns: <code>Date, Transaction type, Order ID, Total</code>. Shipping label purchase adjustments are imported.',
            };
            let importModal = null;

            function showImportHelp() {
                const p = document.querySelector('input[name="vcImpPlatform"]:checked').value;
                document.getElementById('vcImpHelp').innerHTML = IMPORT_HELP[p.startsWith('temu') ? 'temu' : p];
            }

            function openImportModal() {
                if (!importModal) importModal = new bootstrap.Modal(document.getElementById('vcImportModal'));
                document.getElementById('vcImportFile').value = '';
                const out = document.getElementById('vcImpResult');
                out.className = 'small mt-2 d-none';
                out.textContent = '';
                showImportHelp();
                importModal.show();
            }

            async function importCsv(e) {
                e.preventDefault();
                const input = document.getElementById('vcImportFile');
                const file = input.files && input.files[0];
                if (!file) return;
                const platform = document.querySelector('input[name="vcImpPlatform"]:checked').value;
                const btn = document.getElementById('vcImpSubmit');
                const out = document.getElementById('vcImpResult');
                const html = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Importing…';
                out.className = 'small mt-2 text-muted';
                out.textContent = 'Uploading ' + file.name + '…';
                const form = new FormData();
                form.append('file', file);
                form.append('platform', platform);
                try {
                    const res = await fetch('{{ route('veeqo.chargebacks.import') }}', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                        body: form,
                    });
                    const json = await res.json().catch(() => ({}));
                    out.className = 'small mt-2 fw-semibold ' + (res.ok ? 'text-success' : 'text-danger');
                    out.textContent = json.message || (res.ok ? 'Import finished.' : 'Import failed (' + res.status + ').');
                    if (res.ok) {
                        input.value = '';
                        refresh();
                    }
                } catch (err) {
                    out.className = 'small mt-2 fw-semibold text-danger';
                    out.textContent = 'Import failed: ' + err.message;
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = html;
                }
            }

            document.addEventListener('DOMContentLoaded', function () {
                rawModal = new bootstrap.Modal(document.getElementById('vcRawModal'));
                const today = new Date();
                const yearAgo = new Date();
                yearAgo.setFullYear(today.getFullYear() - 1);
                const iso = d => d.toISOString().slice(0, 10);
                document.getElementById('vcFrom').value = iso(yearAgo);
                document.getElementById('vcTo').value = iso(today);

                document.getElementById('vcSkuReport').addEventListener('click', openSkuReport);
                document.querySelectorAll('#vcSkuModal .btn-sort').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        document.querySelectorAll('#vcSkuModal .btn-sort').forEach(b => b.classList.toggle('active', b === btn));
                        if (skuTable) skuTable.setSort('total', btn.dataset.dir);
                    });
                });
                document.getElementById('vcSkuSearch').addEventListener('input', function () {
                    if (!skuTable) return;
                    const q = this.value.trim().toLowerCase();
                    if (q === '') skuTable.clearFilter(); else skuTable.setFilter(d => String(d.sku || '').toLowerCase().includes(q));
                });
                document.getElementById('vcFindSkus').addEventListener('click', async function () {
                    const btn = this;
                    const html = btn.innerHTML;
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Looking up in Veeqo…';
                    try {
                        const res = await fetch('{{ route('veeqo.chargebacks.find-skus') }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
                        const json = await res.json().catch(() => ({}));
                        alert(json.message || (res.ok ? 'Done.' : 'Lookup failed (' + res.status + ').'));
                        openSkuReport();
                        if (table) table.setData();
                    } catch (e) {
                        alert('Lookup failed: ' + e.message);
                    } finally {
                        btn.disabled = false;
                        btn.innerHTML = html;
                    }
                });
                document.getElementById('vcFillOsize').addEventListener('click', async function () {
                    if (!confirm('Write each SKU\'s Avg Chargeback Total (positive) into Shipping Master O-Size Charge?\n\nThis overwrites existing O-Size values and raises Ship, Temu ship and Ship BB for those SKUs.\nFigures use all chargeback types, debits only, last 12 months.')) return;
                    const btn = this;
                    const html = btn.innerHTML;
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Filling O-Size…';
                    try {
                        const res = await fetch('{{ route('veeqo.chargebacks.fill-osize') }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
                        const json = await res.json().catch(() => ({}));
                        alert(json.message || (res.ok ? 'Done.' : 'O-Size fill failed (' + res.status + ').'));
                    } catch (e) {
                        alert('O-Size fill failed: ' + e.message);
                    } finally {
                        btn.disabled = false;
                        btn.innerHTML = html;
                    }
                });
                document.getElementById('vcSkuExport').addEventListener('click', function () {
                    if (skuTable) skuTable.download('csv', 'veeqo_chargebacks_by_sku.csv');
                });
                document.getElementById('vcImport').addEventListener('click', openImportModal);
                document.querySelectorAll('input[name="vcImpPlatform"]').forEach(r => r.addEventListener('change', showImportHelp));
                document.getElementById('vcImportForm').addEventListener('submit', importCsv);
                renderTypeMenu();
                document.getElementById('vcTypeBtn').closest('.dropdown').addEventListener('hidden.bs.dropdown', function () {
                    if (typesDirty) { typesDirty = false; refresh(); }
                });
                ['vcFrom', 'vcTo', 'vcReason', 'vcDirection', 'vcStatus'].forEach(id => document.getElementById(id).addEventListener('change', refresh));
                document.getElementById('vcSource').addEventListener('change', function () {
                    alignTypesToSource();
                    refresh();
                });
                let timer = null;
                document.getElementById('vcSearch').addEventListener('input', function () {
                    clearTimeout(timer);
                    timer = setTimeout(refresh, 350);
                });
                document.getElementById('vcSync').addEventListener('click', function () { syncNow(this, '{{ route('veeqo.chargebacks.sync') }}'); });
                document.getElementById('vcSyncTikTok').addEventListener('click', function () { syncNow(this, '{{ route('veeqo.chargebacks.sync-tiktok') }}'); });
                document.getElementById('vcExport').addEventListener('click', function () {
                    if (table) table.download('csv', 'veeqo_chargebacks_page.csv');
                });

                loadSummary(false).then(function (json) {
                    components = json.components || [];
                    buildTable();
                }).catch(buildTable);
            });
        })();
    </script>
@endsection
