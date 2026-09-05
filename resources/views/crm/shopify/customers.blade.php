@extends('layouts.vertical', ['title' => 'Shopify', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Shopify',
        'sub_title' => 'B2B Customers',
    ])

    @include('crm.shopify._nav', ['active' => 'customers'])

    <style>
        .b2b-dot-cell { position:relative; text-align:center; cursor:default; }
        .b2b-dot { display:inline-block; width:10px; height:10px; border-radius:50%; vertical-align:middle; }
        .b2b-dot-yes { background:#16a34a; box-shadow:0 0 0 3px rgba(22,163,74,.18); }
        .b2b-dot-no { background:#dc2626; box-shadow:0 0 0 3px rgba(220,38,38,.16); }
        .b2b-dot-tip { display:none; position:absolute; left:50%; bottom:calc(100% + 8px); transform:translateX(-50%); z-index:80; max-width:min(360px, calc(100vw - 16px)); background:#0f172a; color:#fff; font-size:.75rem; font-weight:600; line-height:1.35; padding:.35rem .55rem; border-radius:6px; box-shadow:0 8px 24px rgba(15,23,42,.2); white-space:pre-wrap; word-break:break-word; pointer-events:none; text-align:left; }
        .b2b-dot-cell:hover .b2b-dot-tip, .b2b-dot-tip.is-open { display:block; }
        .b2b-dot-tip.is-open { position:fixed; transform:none; bottom:auto; }
        .b2b-dup-btn.has-value { color:#1d4ed8; border-color:#93c5fd; background:#eff6ff; font-weight:600; }
        .b2b-dup-banner { display:flex; align-items:center; justify-content:space-between; gap:.75rem; background:#eff6ff; border:1px solid #bfdbfe; color:#1e3a8a; border-radius:8px; padding:.4rem .7rem; margin-bottom:.75rem; font-size:.8rem; }
        .b2b-action-sep { width:1px; height:18px; background:#e2e8f0; flex-shrink:0; margin:0 .15rem; }
        .b2b-stat-strip { background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:.5rem 1rem; margin-bottom:.75rem; display:flex; flex-wrap:wrap; gap:0; }
        .b2b-stat-item { flex:1 1 auto; min-width:120px; padding:.35rem .75rem; border-right:1px solid #e2e8f0; }
        .b2b-stat-item:last-child { border-right:none; }
        .b2b-stat-label { font-size:.65rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#94a3b8; margin-bottom:.1rem; }
        .b2b-stat-value { font-size:1.05rem; font-weight:800; color:#0f172a; line-height:1.1; }
        .b2b-stat-sub { font-size:.68rem; color:#64748b; }
        .b2b-filter-bar { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:.4rem .75rem; margin-bottom:.75rem; display:flex; align-items:center; gap:.4rem; flex-wrap:nowrap; }
        .b2b-filter-bar .form-control-sm, .b2b-filter-bar .form-select-sm { height:30px; font-size:.8rem; padding:.2rem .5rem; border-color:#e2e8f0; border-radius:6px; background-color:#f8fafc; }
        .b2b-filter-bar .form-select-sm { padding-right:1.6rem; }
        .b2b-filter-bar .b2b-filter-sep { width:1px; height:18px; background:#e2e8f0; flex-shrink:0; }
        .b2b-filter-bar [data-filter-control] { flex-shrink:0; }
        .b2b-filter-bar .b2b-filter-label { font-size:.7rem; font-weight:700; color:#64748b; white-space:nowrap; margin:0; }
        .b2b-filter-bar [data-filter-control="search"] { flex:1 1 180px; min-width:140px; max-width:260px; }
        .b2b-filter-bar [data-filter-control="customerType"] { width:auto; display:flex; align-items:center; gap:.35rem; }
        .b2b-filter-bar [data-filter-control="customerType"] .form-select-sm { width:130px; }
        .b2b-filter-bar [data-filter-control="tag"] { width:auto; display:flex; align-items:center; gap:.35rem; position:relative; }
        .b2b-filter-bar [data-filter-control="classificationSource"] { width:120px; }
        .b2b-filter-bar [data-filter-control="marketplaceChannel"] { width:130px; }
        .b2b-filter-bar [data-filter-control="syncStatus"] { width:110px; }
        .b2b-filter-bar [data-filter-control="perPage"] { width:70px; }
        .b2b-tag-ms { position:relative; width:180px; }
        .b2b-tag-ms-toggle { width:100%; text-align:left; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .b2b-tag-ms-toggle.has-value { color:#0f172a; font-weight:600; }
        .b2b-tag-spin { position:absolute; right:1.6rem; top:50%; transform:translateY(-50%); pointer-events:none; }
        .b2b-tag-ms-panel { position:absolute; top:calc(100% + 4px); left:0; z-index:30; width:260px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; box-shadow:0 8px 24px rgba(15,23,42,.12); padding:.5rem; }
        .b2b-tag-ms-list { max-height:220px; overflow:auto; margin-top:.4rem; }
        .b2b-tag-ms-option { display:flex; align-items:flex-start; gap:.4rem; padding:.25rem .2rem; border-radius:4px; font-size:.78rem; color:#0f172a; cursor:pointer; }
        .b2b-tag-ms-option:hover { background:#f8fafc; }
        .b2b-tag-ms-option input { margin-top:.15rem; }
        .b2b-tag-ms-name { flex:1 1 auto; font-weight:700; min-width:0; }
        .b2b-tag-ms-count { flex:0 0 auto; color:#94a3b8; font-size:.72rem; font-weight:600; }
        .b2b-tag-ms-empty { font-size:.75rem; color:#94a3b8; padding:.35rem .2rem; }
        .b2b-tag-ms-footer { display:flex; align-items:center; justify-content:space-between; gap:.5rem; margin-top:.4rem; padding-top:.35rem; border-top:1px solid #e2e8f0; }
        .b2b-btn-customize { flex-shrink:0; color:#94a3b8; border:1px solid #e2e8f0; background:#f8fafc; border-radius:6px; padding:.2rem .55rem; font-size:.8rem; line-height:1.6; cursor:pointer; display:flex; align-items:center; gap:.25rem; white-space:nowrap; }
        .b2b-btn-customize:hover { background:#f1f5f9; color:#475569; }
        .b2b-sync-icon { display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; }
        .b2b-sync-icon svg { width:16px; height:16px; display:block; }
        .b2b-sync-ok { color:#059669; }
        .b2b-sync-no { color:#dc2626; }
        .b2b-wa-yes { display:inline-flex; align-items:center; gap:.3rem; background:#ecfdf5; color:#047857; border:1px solid #a7f3d0; border-radius:999px; padding:.1rem .5rem; font-size:.72rem; font-weight:700; text-decoration:none; }
        .b2b-wa-yes:hover { background:#d1fae5; color:#065f46; }
        .b2b-wa-no, .b2b-wa-unknown { color:#94a3b8; }
        .b2b-act-btn { width:28px; height:28px; padding:0; display:inline-flex; align-items:center; justify-content:center; border-radius:6px; line-height:1; }
        .b2b-act-btn svg { width:14px; height:14px; display:block; }
        .b2b-act-btn:disabled { opacity:.4; }
        .b2b-social-td { min-width:88px; max-width:132px; padding-left:.3rem; padding-right:.3rem; }
        .b2b-social-input { height:26px; font-size:.72rem; padding:.1rem .35rem; border-color:#e2e8f0; background:#fff; }
        .b2b-social-input:focus { background:#fff; border-color:#93c5fd; box-shadow:0 0 0 2px rgba(59,130,246,.15); }
        .b2b-tag-add-btn { width:22px; height:22px; border-radius:6px; border:1px solid #cbd5e1; background:#fff; color:#0f172a; font-size:1rem; font-weight:700; line-height:1; padding:0; display:inline-flex; align-items:center; justify-content:center; }
        .b2b-tag-add-btn:hover:not(:disabled) { background:#f1f5f9; border-color:#94a3b8; }
        .b2b-tag-add-btn:disabled { opacity:.4; cursor:default; }
        .b2b-tag-drawer-backdrop { position:fixed; inset:0; background:rgba(15,23,42,.4); z-index:1040; }
        .b2b-tag-drawer { position:fixed; top:0; right:0; height:100vh; width:25%; min-width:360px; max-width:440px; background:#fff; z-index:1041; box-shadow:-12px 0 40px rgba(15,23,42,.18); display:flex; flex-direction:column; }
        .b2b-tag-drawer-header { padding:1.1rem 1.15rem .9rem; border-bottom:1px solid #e2e8f0; display:flex; align-items:flex-start; justify-content:space-between; gap:.75rem; }
        .b2b-tag-drawer-title { font-size:1.05rem; font-weight:800; color:#0f172a; margin:0; }
        .b2b-tag-drawer-sub { font-size:.78rem; color:#64748b; margin-top:.2rem; }
        .b2b-tag-drawer-body { flex:1 1 auto; overflow:auto; padding:1rem 1.15rem; display:flex; flex-direction:column; gap:.85rem; }
        .b2b-tag-drawer-section-label { font-size:.68rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#94a3b8; margin-bottom:.35rem; }
        .b2b-tag-drawer-list { border:1px solid #e2e8f0; border-radius:8px; max-height:42vh; overflow:auto; background:#f8fafc; }
        .b2b-tag-drawer-row { border-bottom:1px solid #e2e8f0; }
        .b2b-tag-drawer-row:last-child { border-bottom:0; }
        .b2b-tag-drawer-option { display:flex; align-items:center; gap:.4rem; padding:.45rem .65rem; font-size:.82rem; color:#0f172a; margin:0; }
        .b2b-tag-drawer-row:hover { background:#fff; }
        .b2b-tag-drawer-check { display:flex; align-items:center; gap:.5rem; flex:1 1 auto; min-width:0; cursor:pointer; margin:0; }
        .b2b-tag-drawer-name { font-weight:700; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .b2b-tag-drawer-actions { display:flex; align-items:center; gap:.15rem; flex:0 0 auto; }
        .b2b-tag-drawer-action { border:0; background:transparent; font-size:.68rem; font-weight:700; line-height:1.2; padding:.12rem .28rem; border-radius:4px; }
        .b2b-tag-drawer-action:disabled { opacity:.45; }
        .b2b-tag-drawer-action-merge { color:#0d9488; }
        .b2b-tag-drawer-action-merge:hover:not(:disabled) { background:#ccfbf1; }
        .b2b-tag-drawer-action-delete { color:#dc2626; }
        .b2b-tag-drawer-action-delete:hover:not(:disabled) { background:#fee2e2; }
        .b2b-tag-drawer-merge { padding:.15rem .65rem .55rem 1.85rem; background:#fff; }
        .b2b-tag-drawer-merge-label { font-size:.68rem; color:#64748b; margin-bottom:.3rem; }
        .b2b-tag-drawer-chips { display:flex; flex-wrap:wrap; gap:.35rem; }
        .b2b-tag-drawer-chip { display:inline-flex; align-items:center; gap:.3rem; background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; border-radius:999px; padding:.15rem .55rem; font-size:.72rem; font-weight:600; }
        .b2b-tag-drawer-chip button { border:0; background:transparent; color:#1d4ed8; line-height:1; padding:0; font-size:.85rem; }
        .b2b-tag-drawer-footer { padding:.85rem 1.15rem 1.1rem; border-top:1px solid #e2e8f0; display:flex; gap:.5rem; }
        .b2b-tag-drawer-footer .btn { flex:1; }
        @media (max-width: 767px) { .b2b-tag-drawer { width:85%; max-width:none; } }
        @media(max-width:767px){ .b2b-stat-item{ border-right:none; border-bottom:1px solid #e2e8f0; } .b2b-stat-item:last-child{border-bottom:none;} .b2b-filter-bar{ flex-wrap:wrap; } }
    </style>

    {{-- Stat strip — updates with every filter change --}}
    <div class="b2b-stat-strip" id="crm-shopify-summary">
        <div class="b2b-stat-item">
            <div class="b2b-stat-label">Filtered</div>
            <div class="b2b-stat-value" data-summary-key="all">—</div>
            <div class="b2b-stat-sub">
                <span data-summary-key="wholesale">—</span> wholesale ·
                <span data-summary-key="dropshipper">—</span> dropship
            </div>
        </div>
        <div class="b2b-stat-item">
            <div class="b2b-stat-label">Total Orders</div>
            <div class="b2b-stat-value" data-fstat="total_orders">—</div>
            <div class="b2b-stat-sub"><span data-fstat="customers_with_orders">—</span> customers ordered</div>
        </div>
        <div class="b2b-stat-item">
            <div class="b2b-stat-label">Order Revenue</div>
            <div class="b2b-stat-value" data-fstat="total_order_value">—</div>
            <div class="b2b-stat-sub">Linked Shopify orders</div>
        </div>
        <div class="b2b-stat-item">
            <div class="b2b-stat-label">Avg Order Value</div>
            <div class="b2b-stat-value" data-fstat="avg_order_value">—</div>
            <div class="b2b-stat-sub">Per order</div>
        </div>
        <div class="b2b-stat-item">
            <div class="b2b-stat-label">Linked to CRM</div>
            <div class="b2b-stat-value" data-fstat="linked_to_crm">—</div>
            <div class="b2b-stat-sub"><span data-fstat="missing_email">—</span> missing email</div>
        </div>
    </div>

    {{-- Single-line filter bar --}}
    <div class="b2b-filter-bar mb-3" id="crm-shopify-filter-bar">

        <div data-filter-control="search">
            <input type="search" id="crm-shopify-search" class="form-control form-control-sm"
                   placeholder="&#128269; Search name, email, phone…" autocomplete="off">
        </div>

        <div class="b2b-filter-sep"></div>

        <div data-filter-control="customerType">
            <label class="b2b-filter-label" for="crm-shopify-customer-type">Type</label>
            <select id="crm-shopify-customer-type" class="form-select form-select-sm" title="Type">
                <option value="all" selected>All</option>
                <option value="">All B2B</option>
                <option value="wholesale">Wholesale</option>
                <option value="dropshipper">Dropshipper</option>
                <option value="b2c">B2C</option>
                <option value="marketplace">Marketplace</option>
                <option value="unknown">Unknown</option>
            </select>
        </div>

        <div data-filter-control="tag">
            <label class="b2b-filter-label" for="crm-shopify-tag-toggle">Tags</label>
            <div class="b2b-tag-ms" id="crm-shopify-tag-ms">
                <button type="button" id="crm-shopify-tag-toggle" class="form-select form-select-sm b2b-tag-ms-toggle" title="Tags" aria-haspopup="listbox" aria-expanded="false" aria-controls="crm-shopify-tag-panel">
                    All tags
                </button>
                <span id="crm-shopify-tag-loading" class="b2b-tag-spin spinner-border spinner-border-sm d-none text-secondary" role="status" style="width:.65rem;height:.65rem;"></span>
                <div id="crm-shopify-tag-panel" class="b2b-tag-ms-panel d-none" role="listbox" aria-multiselectable="true">
                    <input type="search" id="crm-shopify-tag-search" class="form-control form-control-sm" placeholder="Search tags…" autocomplete="off">
                    <div id="crm-shopify-tag-list" class="b2b-tag-ms-list"></div>
                    <div class="b2b-tag-ms-footer">
                        <button type="button" id="crm-shopify-tag-clear" class="btn btn-link btn-sm p-0 text-decoration-none">Clear</button>
                        <span id="crm-shopify-tag-count" class="small text-muted"></span>
                    </div>
                </div>
            </div>
        </div>

        <div data-filter-control="classificationSource">
            <select id="crm-shopify-source" class="form-select form-select-sm" title="Source">
                <option value="">All sources</option>
                <option value="tag">Tag</option>
                <option value="email_domain">Email domain</option>
                <option value="order_source">Order source</option>
                <option value="manual">Manual</option>
                <option value="Google">Google</option>
                <option value="fallback">Fallback</option>
            </select>
        </div>

        <div data-filter-control="marketplaceChannel">
            <select id="crm-shopify-marketplace-channel" class="form-select form-select-sm" title="Marketplace channel">
                <option value="">All channels</option>
                @foreach (($marketplaceChannels ?? []) as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div data-filter-control="syncStatus">
            <select id="crm-shopify-sync-status-filter" class="form-select form-select-sm" title="Sync status">
                <option value="">All statuses</option>
                <option value="synced">Synced</option>
            </select>
        </div>

        <div data-filter-control="perPage">
            <select id="crm-shopify-per-page" class="form-select form-select-sm" title="Per page">
                @foreach ([10, 25, 50, 100] as $n)
                    <option value="{{ $n }}" @selected($n === 25)>{{ $n }}</option>
                @endforeach
            </select>
        </div>

        <div class="b2b-filter-sep ms-auto"></div>

        {{-- Customize dropdown --}}
        <div class="dropdown flex-shrink-0">
            <button class="b2b-btn-customize dropdown-toggle" type="button"
                    id="crm-shopify-filter-customize-btn" data-bs-toggle="dropdown" data-bs-auto-close="outside" data-bs-boundary="window" aria-expanded="false">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M6 10.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 0 1h-3a.5.5 0 0 1-.5-.5zm-2-3a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5zm-2-3a.5.5 0 0 1 .5-.5h11a.5.5 0 0 1 0 1h-11a.5.5 0 0 1-.5-.5z"/>
                </svg>
                Filters
            </button>
            <div class="dropdown-menu dropdown-menu-end p-3 shadow-sm" aria-labelledby="crm-shopify-filter-customize-btn" style="min-width:200px;">
                <div class="small fw-semibold mb-2 text-muted">Show / hide filters</div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="crm-shopify-show-filter-search" data-filter-visibility="search">
                    <label class="form-check-label small" for="crm-shopify-show-filter-search">Search</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="crm-shopify-show-filter-type" data-filter-visibility="customerType">
                    <label class="form-check-label small" for="crm-shopify-show-filter-type">Type</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="crm-shopify-show-filter-tag" data-filter-visibility="tag">
                    <label class="form-check-label small" for="crm-shopify-show-filter-tag">Tags</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="crm-shopify-show-filter-source" data-filter-visibility="classificationSource">
                    <label class="form-check-label small" for="crm-shopify-show-filter-source">Source</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="crm-shopify-show-filter-channel" data-filter-visibility="marketplaceChannel">
                    <label class="form-check-label small" for="crm-shopify-show-filter-channel">Marketplace channel</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="crm-shopify-show-filter-sync" data-filter-visibility="syncStatus">
                    <label class="form-check-label small" for="crm-shopify-show-filter-sync">Sync status</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="crm-shopify-show-filter-per-page" data-filter-visibility="perPage">
                    <label class="form-check-label small" for="crm-shopify-show-filter-per-page">Per page</label>
                </div>
            </div>
        </div>

        <div class="b2b-action-sep"></div>

        <div class="dropdown flex-shrink-0">
            <button type="button" id="crm-shopify-dup-btn" class="btn btn-outline-secondary btn-sm dropdown-toggle b2b-dup-btn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" style="height:30px;font-size:.8rem;padding:.2rem .65rem;white-space:nowrap;">
                Search Duplicates
            </button>
            <div class="dropdown-menu dropdown-menu-end p-3 shadow-sm" aria-labelledby="crm-shopify-dup-btn" style="min-width:220px;">
                <div class="small fw-semibold mb-2 text-muted">Find duplicates by</div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="crm-shopify-dup-by" id="crm-shopify-dup-email" value="email" checked>
                    <label class="form-check-label small" for="crm-shopify-dup-email">Email</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="crm-shopify-dup-by" id="crm-shopify-dup-phone" value="phone">
                    <label class="form-check-label small" for="crm-shopify-dup-phone">Phone</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="crm-shopify-dup-by" id="crm-shopify-dup-name" value="name">
                    <label class="form-check-label small" for="crm-shopify-dup-name">Name</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="crm-shopify-dup-by" id="crm-shopify-dup-address" value="address">
                    <label class="form-check-label small" for="crm-shopify-dup-address">Address</label>
                </div>
                <div class="d-flex gap-2 mt-2">
                    <button type="button" id="crm-shopify-dup-search" class="btn btn-primary btn-sm flex-fill">Search</button>
                    <button type="button" id="crm-shopify-dup-clear" class="btn btn-outline-secondary btn-sm flex-fill">Clear</button>
                </div>
            </div>
        </div>

        {{-- Action buttons inline in filter bar --}}
        <button type="button" id="crm-shopify-sync-btn" class="btn btn-primary btn-sm flex-shrink-0" style="height:30px;font-size:.8rem;padding:.2rem .65rem;white-space:nowrap;">
            <span class="sync-label">Sync</span>
            <span class="sync-spinner spinner-border spinner-border-sm d-none ms-1" role="status" aria-hidden="true"></span>
        </button>
        <button type="button" id="crm-shopify-create-btn" class="btn btn-success btn-sm flex-shrink-0" style="height:30px;font-size:.8rem;padding:.2rem .65rem;white-space:nowrap;">+ Create</button>
        <button type="button" id="crm-shopify-import-btn" class="btn btn-outline-secondary btn-sm flex-shrink-0" style="height:30px;font-size:.8rem;padding:.2rem .65rem;white-space:nowrap;">Import</button>
        <span id="crm-shopify-sync-status" class="small text-muted flex-shrink-0" aria-live="polite" style="font-size:.72rem;"></span>
    </div>

    <div id="crm-shopify-dup-banner" class="b2b-dup-banner d-none" role="status">
        <span id="crm-shopify-dup-banner-text">Showing duplicate customers.</span>
        <button type="button" id="crm-shopify-dup-banner-clear" class="btn btn-link btn-sm p-0 text-decoration-none">Clear</button>
    </div>

    <div class="card position-relative" id="crm-shopify-list-card">
        <div id="crm-shopify-loading-overlay"
             class="position-absolute top-0 start-0 w-100 h-100 d-none align-items-center justify-content-center rounded"
             style="background: rgba(255,255,255,0.72); z-index: 2;"
             role="status"
             aria-live="polite"
             aria-busy="true">
            <div class="text-center px-3">
                <div class="spinner-border text-primary mb-2" role="status"></div>
                <div class="small text-muted" id="crm-shopify-loading-message">Loading customers…</div>
            </div>
        </div>
        <div class="card-body">
            <div id="crm-shopify-list-alert" class="alert d-none" role="alert"></div>
            <div class="table-responsive" id="crm-shopify-table-region" aria-busy="false">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="text-center" style="width:32px;">
                                <input type="checkbox" id="crm-shopify-select-all" class="form-check-input" title="Select all on this page">
                            </th>
                            <th class="d-none">
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-reset crm-shopify-sort" data-sort-by="shopify_customer_id">Shopify ID</button>
                            </th>
                            <th>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-reset crm-shopify-sort" data-sort-by="name">Name</button>
                            </th>
                            <th>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-reset crm-shopify-sort" data-sort-by="email">Email</button>
                            </th>
                            <th>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-reset crm-shopify-sort" data-sort-by="phone">Phone</button>
                            </th>
                            <th>Whatsapp</th>
                            <th>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-reset crm-shopify-sort" data-sort-by="address" data-sort-label="Add">Add</button>
                            </th>
                            <th>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-reset crm-shopify-sort" data-sort-by="province">State</button>
                            </th>
                            <th>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-reset crm-shopify-sort" data-sort-by="website">Website</button>
                            </th>
                            <th>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-reset crm-shopify-sort" data-sort-by="facebook">FB</button>
                            </th>
                            <th>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-reset crm-shopify-sort" data-sort-by="instagram">Insta</button>
                            </th>
                            <th class="text-end">
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-reset crm-shopify-sort" data-sort-by="orders_count" data-sort-label="Orders">Orders</button>
                            </th>
                            <th class="text-end">
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-reset crm-shopify-sort" data-sort-by="qty" data-sort-label="Qty">Qty</button>
                            </th>
                            <th class="text-end">
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-reset crm-shopify-sort" data-sort-by="revenue" data-sort-label="Revenue">Revenue</button>
                            </th>
                            <th class="text-nowrap">
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-reset crm-shopify-sort" data-sort-by="order_date" data-sort-label="Order Date">Order Date</button>
                            </th>
                            <th>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-reset crm-shopify-sort" data-sort-by="customer_type">Type</button>
                            </th>
                            <th>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-reset crm-shopify-sort" data-sort-by="channel">Channel</button>
                            </th>
                            <th>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-reset crm-shopify-sort" data-sort-by="classification_source">Source</button>
                            </th>
                            <th>
                                <div class="d-inline-flex align-items-center gap-1">
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-reset crm-shopify-sort" data-sort-by="tags" data-sort-label="Tags">Tags</button>
                                    <button type="button" id="crm-shopify-add-tags-btn" class="b2b-tag-add-btn" title="Add tags" aria-label="Add tags">+</button>
                                </div>
                            </th>
                            <th class="d-none">CRM customer</th>
                            <th>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-reset crm-shopify-sort" data-sort-by="sync_status">Sync</button>
                            </th>
                            <th>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-reset crm-shopify-sort" data-sort-by="last_synced_at">Last synced</button>
                            </th>
                            <th class="text-end">Follow-up</th>
                            <th class="text-center text-nowrap">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <button type="button" id="crm-shopify-bulk-edit-btn" class="btn btn-outline-secondary btn-sm b2b-act-btn" title="Edit selected" aria-label="Edit selected" disabled>
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325"/></svg>
                                    </button>
                                    <button type="button" id="crm-shopify-bulk-delete-btn" class="btn btn-outline-danger btn-sm b2b-act-btn" title="Delete selected" aria-label="Delete selected" disabled>
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg>
                                    </button>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="crm-shopify-customers-tbody">
                        <tr>
                            <td colspan="24" class="text-muted text-center py-4">Loading…</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div id="crm-shopify-pagination-wrap" class="mt-3 d-none">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                    <div class="btn-group btn-group-sm" role="group" aria-label="First previous page">
                        <button type="button" id="crm-shopify-first" class="btn btn-outline-secondary" title="First page" disabled>« First</button>
                        <button type="button" id="crm-shopify-prev" class="btn btn-outline-secondary" title="Previous page" disabled>‹ Prev</button>
                    </div>
                    <ul id="crm-shopify-page-numbers" class="pagination pagination-sm mb-0 flex-wrap justify-content-center"></ul>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Next last page">
                        <button type="button" id="crm-shopify-next" class="btn btn-outline-secondary" title="Next page" disabled>Next ›</button>
                        <button type="button" id="crm-shopify-last" class="btn btn-outline-secondary" title="Last page" disabled>Last »</button>
                    </div>
                </div>
                <div id="crm-shopify-page-summary" class="small text-muted text-center"></div>
            </div>
        </div>
    </div>

    @php($crmAssignees = $crmAssignees ?? collect())

    <div class="modal fade" id="crm-shopify-followup-modal" tabindex="-1" aria-labelledby="crm-shopify-followup-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form id="crm-shopify-followup-form">
                    <div class="modal-header">
                        <h5 class="modal-title" id="crm-shopify-followup-modal-label">Create follow-up</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="crm-shopify-followup-modal-alert" class="alert alert-danger d-none small py-2 mb-3" role="alert"></div>
                        <p class="small text-muted mb-3">CRM customer is matched or created from this Shopify row when you save (same rules as customer sync).</p>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small mb-0" for="crm-shopify-fu-name">Name</label>
                                <input type="text" class="form-control form-control-sm bg-light" id="crm-shopify-fu-name" readonly tabindex="-1">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-0" for="crm-shopify-fu-email">Email</label>
                                <input type="text" class="form-control form-control-sm bg-light" id="crm-shopify-fu-email" readonly tabindex="-1">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-0" for="crm-shopify-fu-crm-id">CRM customer ID</label>
                                <input type="text" class="form-control form-control-sm bg-light" id="crm-shopify-fu-crm-id" readonly tabindex="-1">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-0" for="crm-shopify-fu-shopify-label">Shopify customer (API id)</label>
                                <input type="text" class="form-control form-control-sm bg-light font-monospace" id="crm-shopify-fu-shopify-label" readonly tabindex="-1">
                            </div>
                        </div>
                        <input type="hidden" id="crm-shopify-fu-shopify-record-id" value="">
                        <div class="row g-2">
                            <div class="col-12">
                                <label class="form-label small mb-0" for="crm-shopify-fu-title">Title</label>
                                <input type="text" class="form-control form-control-sm" id="crm-shopify-fu-title" required maxlength="255" value="Shopify customer follow-up">
                            </div>
                            <div class="col-12">
                                <label class="form-label small mb-0" for="crm-shopify-fu-description">Description</label>
                                <textarea class="form-control form-control-sm" id="crm-shopify-fu-description" rows="3"></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-0" for="crm-shopify-fu-type">Type</label>
                                <select class="form-select form-select-sm" id="crm-shopify-fu-type" required>
                                    @foreach (['call', 'email', 'whatsapp', 'meeting', 'sms', 'other'] as $t)
                                        <option value="{{ $t }}" @selected($t === 'call')>{{ $t }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-0" for="crm-shopify-fu-priority">Priority</label>
                                <select class="form-select form-select-sm" id="crm-shopify-fu-priority" required>
                                    @foreach (['low', 'medium', 'high'] as $p)
                                        <option value="{{ $p }}" @selected($p === 'medium')>{{ $p }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-0" for="crm-shopify-fu-assignee">Assignee</label>
                                <select class="form-select form-select-sm" id="crm-shopify-fu-assignee" required>
                                    @forelse ($crmAssignees as $u)
                                        <option value="{{ $u->id }}" @selected((int) $u->id === (int) auth()->id())>{{ $u->name }}</option>
                                    @empty
                                        <option value="{{ auth()->id() }}">{{ auth()->user()->name ?? 'Me' }}</option>
                                    @endforelse
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-0" for="crm-shopify-fu-scheduled">Scheduled at</label>
                                <input type="datetime-local" class="form-control form-control-sm" id="crm-shopify-fu-scheduled">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm" id="crm-shopify-fu-submit">
                            <span class="fu-submit-label">Save follow-up</span>
                            <span class="fu-submit-spinner spinner-border spinner-border-sm d-none ms-1" role="status" aria-hidden="true"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="crm-shopify-create-modal" tabindex="-1" aria-labelledby="crm-shopify-create-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form id="crm-shopify-create-form">
                    <div class="modal-header">
                        <h5 class="modal-title" id="crm-shopify-create-modal-label">Create Shopify customer</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="crm-shopify-create-alert" class="alert alert-danger d-none small py-2 mb-3" role="alert"></div>
                        <p class="small text-muted mb-3">This creates the customer in Shopify first, then stores Shopify's returned data locally. Website, FB, and Insta stay in CRM only.</p>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small mb-0" for="crm-shopify-create-name">Name</label>
                                <input type="text" class="form-control form-control-sm" id="crm-shopify-create-name" required maxlength="255">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-0" for="crm-shopify-create-email">Email</label>
                                <input type="email" class="form-control form-control-sm" id="crm-shopify-create-email" maxlength="255">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-0" for="crm-shopify-create-phone">Phone</label>
                                <input type="text" class="form-control form-control-sm" id="crm-shopify-create-phone" maxlength="64">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-0" for="crm-shopify-create-province">State</label>
                                <input type="text" class="form-control form-control-sm" id="crm-shopify-create-province" maxlength="128">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-0" for="crm-shopify-create-zip">Zip</label>
                                <input type="text" class="form-control form-control-sm" id="crm-shopify-create-zip" maxlength="32">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-0" for="crm-shopify-create-website">Website</label>
                                <input type="text" class="form-control form-control-sm" id="crm-shopify-create-website" maxlength="255" placeholder="https://">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-0" for="crm-shopify-create-facebook">FB</label>
                                <input type="text" class="form-control form-control-sm" id="crm-shopify-create-facebook" maxlength="255">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-0" for="crm-shopify-create-instagram">Insta</label>
                                <input type="text" class="form-control form-control-sm" id="crm-shopify-create-instagram" maxlength="255">
                            </div>
                            <div class="col-12">
                                <label class="form-label small mb-0" for="crm-shopify-create-tags">Tags</label>
                                <input type="text" class="form-control form-control-sm" id="crm-shopify-create-tags" maxlength="1000" placeholder="VIP, wholesale">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success btn-sm" id="crm-shopify-create-submit">
                            <span class="create-submit-label">Create in Shopify</span>
                            <span class="create-submit-spinner spinner-border spinner-border-sm d-none ms-1" role="status" aria-hidden="true"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="crm-shopify-edit-modal" tabindex="-1" aria-labelledby="crm-shopify-edit-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form id="crm-shopify-edit-form">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title" id="crm-shopify-edit-modal-label">Edit customer</h5>
                            <div class="small text-muted" id="crm-shopify-edit-modal-sub">Update this customer in Shopify.</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="crm-shopify-edit-alert" class="alert alert-danger d-none small py-2 mb-3" role="alert"></div>
                        <p class="small text-muted mb-3" id="crm-shopify-edit-hint">Name, email, phone, state, zip, and tags are saved to Shopify. Website, FB, and Insta stay in CRM only.</p>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small mb-0" for="crm-shopify-edit-name">Name</label>
                                <input type="text" class="form-control form-control-sm" id="crm-shopify-edit-name" maxlength="255">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-0" for="crm-shopify-edit-email">Email</label>
                                <input type="email" class="form-control form-control-sm" id="crm-shopify-edit-email" maxlength="255">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-0" for="crm-shopify-edit-phone">Phone</label>
                                <input type="text" class="form-control form-control-sm" id="crm-shopify-edit-phone" maxlength="64">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-0" for="crm-shopify-edit-province">State</label>
                                <input type="text" class="form-control form-control-sm" id="crm-shopify-edit-province" maxlength="128">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-0" for="crm-shopify-edit-zip">Zip</label>
                                <input type="text" class="form-control form-control-sm" id="crm-shopify-edit-zip" maxlength="32">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-0" for="crm-shopify-edit-website">Website</label>
                                <input type="text" class="form-control form-control-sm" id="crm-shopify-edit-website" maxlength="255" placeholder="https://">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-0" for="crm-shopify-edit-facebook">FB</label>
                                <input type="text" class="form-control form-control-sm" id="crm-shopify-edit-facebook" maxlength="255">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-0" for="crm-shopify-edit-instagram">Insta</label>
                                <input type="text" class="form-control form-control-sm" id="crm-shopify-edit-instagram" maxlength="255">
                            </div>
                            <div class="col-12">
                                <label class="form-label small mb-0" for="crm-shopify-edit-tags">Tags</label>
                                <input type="text" class="form-control form-control-sm" id="crm-shopify-edit-tags" maxlength="1000" placeholder="VIP, wholesale">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm" id="crm-shopify-edit-submit">
                            <span class="edit-submit-label">Save</span>
                            <span class="edit-submit-spinner spinner-border spinner-border-sm d-none ms-1" role="status" aria-hidden="true"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="crm-shopify-delete-modal" tabindex="-1" aria-labelledby="crm-shopify-delete-modal-label" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="crm-shopify-delete-modal-label">Delete customers</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="crm-shopify-delete-alert" class="alert alert-danger d-none small py-2 mb-3" role="alert"></div>
                    <p class="mb-0" id="crm-shopify-delete-text">Delete this customer from Shopify and this list? This cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger btn-sm" id="crm-shopify-delete-confirm">
                        <span class="delete-submit-label">Delete</span>
                        <span class="delete-submit-spinner spinner-border spinner-border-sm d-none ms-1" role="status" aria-hidden="true"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="crm-shopify-import-modal" tabindex="-1" aria-labelledby="crm-shopify-import-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form id="crm-shopify-import-form">
                    <div class="modal-header">
                        <h5 class="modal-title" id="crm-shopify-import-modal-label">Import Shopify customers</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="crm-shopify-import-alert" class="alert d-none small py-2 mb-3" role="alert"></div>
                        <p class="small text-muted mb-2">Upload CSV/XLS/XLSX with headings: name, email, phone, province, zip, tags.</p>
                        <input type="file" class="form-control form-control-sm" id="crm-shopify-import-file" accept=".csv,.txt,.xls,.xlsx" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm" id="crm-shopify-import-submit">
                            <span class="import-submit-label">Import</span>
                            <span class="import-submit-spinner spinner-border spinner-border-sm d-none ms-1" role="status" aria-hidden="true"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="crm-shopify-tag-drawer-backdrop" class="b2b-tag-drawer-backdrop d-none" hidden></div>
    <aside id="crm-shopify-tag-drawer" class="b2b-tag-drawer d-none" hidden aria-hidden="true" aria-labelledby="crm-shopify-tag-drawer-title">
        <div class="b2b-tag-drawer-header">
            <div>
                <h2 class="b2b-tag-drawer-title" id="crm-shopify-tag-drawer-title">Add tags</h2>
                <div class="b2b-tag-drawer-sub" id="crm-shopify-tag-drawer-sub">Select customers, then add, merge, or delete tags.</div>
            </div>
            <button type="button" class="btn-close" id="crm-shopify-tag-drawer-close" aria-label="Close"></button>
        </div>
        <div class="b2b-tag-drawer-body">
            <div id="crm-shopify-tag-drawer-alert" class="alert alert-danger d-none small py-2 mb-0" role="alert"></div>
            <div>
                <div class="b2b-tag-drawer-section-label">Existing tags</div>
                <input type="search" id="crm-shopify-tag-drawer-search" class="form-control form-control-sm mb-2" placeholder="Search tags…" autocomplete="off">
                <div id="crm-shopify-tag-drawer-list" class="b2b-tag-drawer-list"></div>
            </div>
            <div>
                <div class="b2b-tag-drawer-section-label">New tag</div>
                <div class="d-flex gap-2">
                    <input type="text" id="crm-shopify-tag-drawer-new" class="form-control form-control-sm" maxlength="100" placeholder="Type a new tag">
                    <button type="button" id="crm-shopify-tag-drawer-new-btn" class="btn btn-outline-primary btn-sm flex-shrink-0">Add</button>
                </div>
            </div>
            <div>
                <div class="b2b-tag-drawer-section-label">Selected</div>
                <div id="crm-shopify-tag-drawer-chips" class="b2b-tag-drawer-chips">
                    <span class="small text-muted">No tags selected</span>
                </div>
            </div>
        </div>
        <div class="b2b-tag-drawer-footer">
            <button type="button" id="crm-shopify-tag-drawer-cancel" class="btn btn-light btn-sm">Cancel</button>
            <button type="button" id="crm-shopify-tag-drawer-apply" class="btn btn-primary btn-sm">
                <span class="apply-label">Apply tags</span>
                <span class="apply-spinner spinner-border spinner-border-sm d-none ms-1" role="status" aria-hidden="true"></span>
            </button>
        </div>
    </aside>

    <script>
        (function () {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const dataUrl = @json(route('crm.shopify.customers.data'));
            const addTagsUrl = @json(route('crm.shopify.customers.tags.add'));
            const deleteTagsUrl = @json(route('crm.shopify.customers.tags.delete'));
            const mergeTagsUrl = @json(route('crm.shopify.customers.tags.merge'));
            const whatsappCheckUrl = @json(route('crm.shopify.customers.whatsapp.check'));
            const syncUrl = @json(route('crm.shopify.sync-customers'));
            const storeUrl = @json(route('crm.shopify.customers.store'));
            const updateCustomersUrl = @json(route('crm.shopify.customers.update'));
            const socialCustomersUrl = @json(route('crm.shopify.customers.social'));
            const deleteCustomersUrl = @json(route('crm.shopify.customers.delete'));
            const tableColSpan = 24;
            const importUrl = @json(route('crm.shopify.customers.import'));
            const crmCustomerBase = @json(url('/crm/customers'));
            const shopifyCustomersBase = @json(url('/crm/shopify/customers'));

            const listCard = document.getElementById('crm-shopify-list-card');
            const overlay = document.getElementById('crm-shopify-loading-overlay');
            const loadingMessage = document.getElementById('crm-shopify-loading-message');
            const tbody = document.getElementById('crm-shopify-customers-tbody');
            const tableRegion = document.getElementById('crm-shopify-table-region');
            const alertEl = document.getElementById('crm-shopify-list-alert');
            const syncBtn = document.getElementById('crm-shopify-sync-btn');
            const createBtn = document.getElementById('crm-shopify-create-btn');
            const importBtn = document.getElementById('crm-shopify-import-btn');
            const syncStatus = document.getElementById('crm-shopify-sync-status');
            const syncSpinner = syncBtn?.querySelector('.sync-spinner');
            const searchInput = document.getElementById('crm-shopify-search');
            const tagMs = document.getElementById('crm-shopify-tag-ms');
            const tagToggle = document.getElementById('crm-shopify-tag-toggle');
            const tagPanel = document.getElementById('crm-shopify-tag-panel');
            const tagSearch = document.getElementById('crm-shopify-tag-search');
            const tagList = document.getElementById('crm-shopify-tag-list');
            const tagClear = document.getElementById('crm-shopify-tag-clear');
            const tagCount = document.getElementById('crm-shopify-tag-count');
            const typeSelect = document.getElementById('crm-shopify-customer-type');
            const sourceSelect = document.getElementById('crm-shopify-source');
            const marketplaceChannelSelect = document.getElementById('crm-shopify-marketplace-channel');
            const syncStatusSelect = document.getElementById('crm-shopify-sync-status-filter');
            const perPageSelect = document.getElementById('crm-shopify-per-page');
            const dupBtn = document.getElementById('crm-shopify-dup-btn');
            const dupSearchBtn = document.getElementById('crm-shopify-dup-search');
            const dupClearBtn = document.getElementById('crm-shopify-dup-clear');
            const dupBanner = document.getElementById('crm-shopify-dup-banner');
            const dupBannerText = document.getElementById('crm-shopify-dup-banner-text');
            const dupBannerClear = document.getElementById('crm-shopify-dup-banner-clear');
            const filterControls = document.querySelectorAll('[data-filter-control]');
            const filterVisibilityInputs = document.querySelectorAll('[data-filter-visibility]');
            const summaryEls = document.querySelectorAll('#crm-shopify-summary [data-summary-key]');
            const paginationWrap = document.getElementById('crm-shopify-pagination-wrap');
            const prevBtn = document.getElementById('crm-shopify-prev');
            const nextBtn = document.getElementById('crm-shopify-next');
            const firstBtn = document.getElementById('crm-shopify-first');
            const lastBtn = document.getElementById('crm-shopify-last');
            const pageNumbersEl = document.getElementById('crm-shopify-page-numbers');
            const pageSummary = document.getElementById('crm-shopify-page-summary');
            const sortButtons = document.querySelectorAll('.crm-shopify-sort');
            const filterVisibilityStorageKey = 'crm.shopify.customers.visibleFilters.v3';
            const defaultVisibleFilters = {
                search: true,
                customerType: true,
                tag: true,
                classificationSource: false,
                marketplaceChannel: false,
                syncStatus: false,
                perPage: true,
            };

            let state = {
                page: 1,
                perPage: parseInt(perPageSelect.value, 10) || 25,
                q: '',
                tags: [],
                customerType: 'all',
                classificationSource: '',
                marketplaceChannel: '',
                syncStatus: '',
                sortBy: 'last_synced_at',
                sortDir: 'desc',
                lastPage: 1,
                total: 0,
                duplicateBy: '',
            };
            let visibleFilters = loadVisibleFilters();

            let loadSeq = 0;
            let listAbort = null;
            let successHideTimer = null;
            let filterDebounceTimer = null;
            let availableTags = @json($tagFilters ?? []);
            let tagCounts = @json($tagCounts ?? new \stdClass());
            const selectedTagSet = new Set();

            function applyTagPayload(payload) {
                if (Array.isArray(payload)) {
                    if (payload.length && payload[0] && typeof payload[0] === 'object' && payload[0].tag) {
                        availableTags = payload.map(function (item) { return item.tag; });
                        tagCounts = {};
                        payload.forEach(function (item) { tagCounts[item.tag] = Number(item.count || 0); });
                        return;
                    }
                    availableTags = payload.filter(function (tag) { return typeof tag === 'string'; });
                    return;
                }
                if (payload && Array.isArray(payload.tags)) {
                    availableTags = payload.tags;
                    tagCounts = payload.counts && typeof payload.counts === 'object' ? payload.counts : {};
                }
            }
            const selectedCustomerIds = new Set();
            let lastRowsById = {};
            const drawerTagSet = new Set();
            let drawerAvailableTags = [];
            let drawerMergeFrom = '';
            let drawerBusy = false;

            function selectedTags() {
                return availableTags.filter(function (tag) { return selectedTagSet.has(tag); });
            }

            function setSelectedTags(tags, options) {
                options = options || {};
                selectedTagSet.clear();
                (tags || []).forEach(function (tag) {
                    if (tag) selectedTagSet.add(tag);
                });
                state.tags = selectedTags();
                renderTagOptions();
                updateTagToggle();
                if (options.apply) applyFiltersNow();
            }

            function updateTagToggle() {
                const tags = selectedTags();
                if (!tagToggle) return;
                if (!tags.length) {
                    tagToggle.textContent = 'All tags';
                    tagToggle.classList.remove('has-value');
                } else if (tags.length === 1) {
                    tagToggle.textContent = tags[0];
                    tagToggle.classList.add('has-value');
                } else {
                    tagToggle.textContent = tags.length + ' tags';
                    tagToggle.classList.add('has-value');
                }
                if (tagCount) {
                    tagCount.textContent = tags.length ? (tags.length + ' selected') : '';
                }
            }

            function renderTagOptions() {
                if (!tagList) return;
                const query = (tagSearch?.value || '').trim().toLowerCase();
                const matches = availableTags.filter(function (tag) {
                    return !query || String(tag).toLowerCase().includes(query);
                });
                tagList.innerHTML = '';
                if (!matches.length) {
                    const empty = document.createElement('div');
                    empty.className = 'b2b-tag-ms-empty';
                    empty.textContent = availableTags.length ? 'No matching tags' : 'No tags found';
                    tagList.appendChild(empty);
                    return;
                }
                matches.forEach(function (tag) {
                    const label = document.createElement('label');
                    label.className = 'b2b-tag-ms-option';
                    const input = document.createElement('input');
                    input.type = 'checkbox';
                    input.value = tag;
                    input.checked = selectedTagSet.has(tag);
                    input.addEventListener('change', function () {
                        if (input.checked) selectedTagSet.add(tag);
                        else selectedTagSet.delete(tag);
                        state.tags = selectedTags();
                        updateTagToggle();
                        applyFiltersNow();
                    });
                    const text = document.createElement('span');
                    text.className = 'b2b-tag-ms-name';
                    text.textContent = tag;
                    const count = document.createElement('span');
                    count.className = 'b2b-tag-ms-count';
                    const n = Number(tagCounts[tag] || 0);
                    count.textContent = String(n);
                    label.appendChild(input);
                    label.appendChild(text);
                    label.appendChild(count);
                    tagList.appendChild(label);
                });
            }

            function setTagPanelOpen(open) {
                if (!tagPanel || !tagToggle) return;
                tagPanel.classList.toggle('d-none', !open);
                tagToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (open) {
                    renderTagOptions();
                    setTimeout(function () { tagSearch?.focus(); }, 0);
                }
            }

            const addTagsBtn = document.getElementById('crm-shopify-add-tags-btn');
            const selectAll = document.getElementById('crm-shopify-select-all');
            const bulkEditBtn = document.getElementById('crm-shopify-bulk-edit-btn');
            const bulkDeleteBtn = document.getElementById('crm-shopify-bulk-delete-btn');
            const drawerEl = document.getElementById('crm-shopify-tag-drawer');
            const drawerBackdrop = document.getElementById('crm-shopify-tag-drawer-backdrop');
            const drawerClose = document.getElementById('crm-shopify-tag-drawer-close');
            const drawerCancel = document.getElementById('crm-shopify-tag-drawer-cancel');
            const drawerApply = document.getElementById('crm-shopify-tag-drawer-apply');
            const drawerSearch = document.getElementById('crm-shopify-tag-drawer-search');
            const drawerList = document.getElementById('crm-shopify-tag-drawer-list');
            const drawerNew = document.getElementById('crm-shopify-tag-drawer-new');
            const drawerNewBtn = document.getElementById('crm-shopify-tag-drawer-new-btn');
            const drawerChips = document.getElementById('crm-shopify-tag-drawer-chips');
            const drawerSub = document.getElementById('crm-shopify-tag-drawer-sub');
            const drawerAlert = document.getElementById('crm-shopify-tag-drawer-alert');
            const drawerApplySpinner = drawerApply?.querySelector('.apply-spinner');
            const drawerApplyLabel = drawerApply?.querySelector('.apply-label');

            function selectedCustomerCount() {
                return selectedCustomerIds.size;
            }

            function updateSelectAllState() {
                if (selectAll && tbody) {
                    const boxes = tbody.querySelectorAll('.crm-shopify-row-check');
                    const checked = tbody.querySelectorAll('.crm-shopify-row-check:checked');
                    selectAll.checked = boxes.length > 0 && checked.length === boxes.length;
                    selectAll.indeterminate = checked.length > 0 && checked.length < boxes.length;
                }
                const n = selectedCustomerCount();
                if (bulkEditBtn) bulkEditBtn.disabled = n === 0;
                if (bulkDeleteBtn) bulkDeleteBtn.disabled = n === 0;
            }

            function updateDrawerSelectionCopy() {
                if (!drawerSub) return;
                const n = selectedCustomerCount();
                drawerSub.textContent = n === 0
                    ? 'Select customers in the table, then add, merge, or delete tags.'
                    : (n === 1 ? 'Editing tags for 1 selected customer.' : 'Editing tags for ' + n + ' selected customers.');
            }

            function showDrawerAlert(message) {
                if (!drawerAlert) return;
                drawerAlert.textContent = message || '';
                drawerAlert.classList.toggle('d-none', !message);
            }

            function selectedDrawerTags() {
                return drawerAvailableTags.filter(function (tag) { return drawerTagSet.has(tag); });
            }

            function renderDrawerChips() {
                if (!drawerChips) return;
                const tags = selectedDrawerTags();
                drawerChips.innerHTML = '';
                if (!tags.length) {
                    const empty = document.createElement('span');
                    empty.className = 'small text-muted';
                    empty.textContent = 'No tags selected';
                    drawerChips.appendChild(empty);
                    return;
                }
                tags.forEach(function (tag) {
                    const chip = document.createElement('span');
                    chip.className = 'b2b-tag-drawer-chip';
                    chip.appendChild(document.createTextNode(tag));
                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.setAttribute('aria-label', 'Remove ' + tag);
                    remove.textContent = '×';
                    remove.addEventListener('click', function () {
                        drawerTagSet.delete(tag);
                        renderDrawerTagList();
                        renderDrawerChips();
                    });
                    chip.appendChild(remove);
                    drawerChips.appendChild(chip);
                });
            }

            function selectedCustomerIdList() {
                return Array.from(selectedCustomerIds).map(Number);
            }

            async function postJsonCrm(url, body) {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(body),
                });
                let json = {};
                try { json = await res.json(); } catch (e) {}
                if (!res.ok) {
                    throw new Error(messageFromJson(json, res) || 'Request failed.');
                }
                return json;
            }

            function socialInputTd(rowId, field, value, label) {
                const td = document.createElement('td');
                td.className = 'b2b-social-td';
                const input = document.createElement('input');
                input.type = 'text';
                input.className = 'form-control form-control-sm b2b-social-input';
                input.maxLength = 255;
                input.value = value || '';
                input.dataset.field = field;
                input.dataset.saved = value || '';
                input.setAttribute('aria-label', label);
                input.addEventListener('click', function (e) { e.stopPropagation(); });
                input.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        input.blur();
                    }
                });
                input.addEventListener('blur', function () {
                    saveSocialField(rowId, field, input);
                });
                td.appendChild(input);
                return td;
            }

            function applySocialToVisibleRows(ids, field, value) {
                ids.forEach(function (id) {
                    if (lastRowsById[String(id)]) {
                        lastRowsById[String(id)][field] = value || null;
                    }
                    const check = tbody && tbody.querySelector('.crm-shopify-row-check[value="' + id + '"]');
                    const row = check && check.closest('tr');
                    const input = row && row.querySelector('.b2b-social-input[data-field="' + field + '"]');
                    if (input) {
                        input.value = value;
                        input.dataset.saved = value;
                    }
                });
            }

            async function saveSocialField(rowId, field, input) {
                const value = String(input.value || '').trim();
                if (value === String(input.dataset.saved || '')) {
                    input.value = value;
                    return;
                }
                const ids = actionTargetIds(rowId);
                const payload = { ids: ids };
                payload[field] = value;
                input.disabled = true;
                try {
                    await postJsonCrm(socialCustomersUrl, payload);
                    applySocialToVisibleRows(ids, field, value);
                } catch (e) {
                    input.value = input.dataset.saved || '';
                    showAlert('warning', e && e.message ? e.message : 'Could not save ' + field + '.', { dismissible: true });
                } finally {
                    input.disabled = false;
                }
            }

            async function afterDrawerTagMutation(json) {
                await refreshTagsForType(typeSelect ? typeSelect.value : '');
                drawerAvailableTags = availableTags.slice();
                drawerMergeFrom = '';
                renderDrawerTagList();
                renderDrawerChips();
                await loadPage(state.page, { loadingMessage: 'Refreshing tags…' });
                showAlert('success', json.message || 'Updated.', { autoHideMs: 5000, dismissible: false });
                if (json.failed) {
                    showAlert('warning', json.message + ' ' + (json.errors || []).join(' '), { dismissible: true });
                }
            }

            function setDrawerBusy(busy) {
                drawerBusy = !!busy;
                if (drawerApply) drawerApply.disabled = drawerBusy;
                renderDrawerTagList();
            }

            function requireSelectedCustomers() {
                const ids = selectedCustomerIdList();
                if (!ids.length) {
                    showDrawerAlert('Select one or more customers in the table first.');
                    return null;
                }
                return ids;
            }

            async function deleteDrawerTag(tag) {
                const ids = requireSelectedCustomers();
                if (!ids) return;
                const n = ids.length;
                if (!window.confirm('Remove “' + tag + '” from ' + n + ' selected customer' + (n === 1 ? '' : 's') + '?')) {
                    return;
                }
                showDrawerAlert('');
                setDrawerBusy(true);
                try {
                    const json = await postJsonCrm(deleteTagsUrl, { ids: ids, tag: tag });
                    drawerTagSet.delete(tag);
                    await afterDrawerTagMutation(json);
                } catch (e) {
                    showDrawerAlert(e && e.message ? e.message : 'Could not delete tag.');
                } finally {
                    setDrawerBusy(false);
                }
            }

            async function mergeDrawerTag(from, to) {
                const ids = requireSelectedCustomers();
                if (!ids) return;
                to = (to || '').trim();
                if (!to) {
                    showDrawerAlert('Choose or type a tag to merge into.');
                    return;
                }
                if (String(from).toLowerCase() === to.toLowerCase()) {
                    showDrawerAlert('Pick a different tag to merge into.');
                    return;
                }
                showDrawerAlert('');
                setDrawerBusy(true);
                try {
                    const json = await postJsonCrm(mergeTagsUrl, { ids: ids, from: from, to: to });
                    if (drawerTagSet.has(from)) {
                        drawerTagSet.delete(from);
                        drawerTagSet.add(to);
                    }
                    await afterDrawerTagMutation(json);
                } catch (e) {
                    showDrawerAlert(e && e.message ? e.message : 'Could not merge tags.');
                } finally {
                    setDrawerBusy(false);
                }
            }

            function buildMergePanel(tag) {
                const panel = document.createElement('div');
                panel.className = 'b2b-tag-drawer-merge';
                const hint = document.createElement('div');
                hint.className = 'b2b-tag-drawer-merge-label';
                hint.textContent = 'Merge “' + tag + '” into';
                const row = document.createElement('div');
                row.className = 'd-flex gap-1';
                const input = document.createElement('input');
                input.type = 'text';
                input.className = 'form-control form-control-sm';
                input.maxLength = 100;
                input.placeholder = 'Choose or type a tag';
                input.setAttribute('list', 'crm-shopify-tag-drawer-merge-list');
                input.disabled = drawerBusy;
                const confirm = document.createElement('button');
                confirm.type = 'button';
                confirm.className = 'btn btn-outline-primary btn-sm flex-shrink-0';
                confirm.textContent = 'Merge';
                confirm.disabled = drawerBusy;
                const cancel = document.createElement('button');
                cancel.type = 'button';
                cancel.className = 'btn btn-light btn-sm flex-shrink-0';
                cancel.textContent = 'Cancel';
                cancel.disabled = drawerBusy;
                confirm.addEventListener('click', function () { mergeDrawerTag(tag, input.value); });
                cancel.addEventListener('click', function () {
                    drawerMergeFrom = '';
                    renderDrawerTagList();
                });
                input.addEventListener('keydown', function (ev) {
                    if (ev.key === 'Enter') {
                        ev.preventDefault();
                        mergeDrawerTag(tag, input.value);
                    }
                    if (ev.key === 'Escape') {
                        ev.preventDefault();
                        ev.stopPropagation();
                        drawerMergeFrom = '';
                        renderDrawerTagList();
                    }
                });
                row.appendChild(input);
                row.appendChild(confirm);
                row.appendChild(cancel);
                panel.appendChild(hint);
                panel.appendChild(row);
                setTimeout(function () { input.focus(); }, 0);
                return panel;
            }

            function renderDrawerTagList() {
                if (!drawerList) return;
                const query = (drawerSearch?.value || '').trim().toLowerCase();
                const matches = drawerAvailableTags.filter(function (tag) {
                    return !query || String(tag).toLowerCase().includes(query);
                });
                drawerList.innerHTML = '';
                const datalist = document.createElement('datalist');
                datalist.id = 'crm-shopify-tag-drawer-merge-list';
                drawerAvailableTags.forEach(function (optionTag) {
                    if (optionTag === drawerMergeFrom) return;
                    const option = document.createElement('option');
                    option.value = optionTag;
                    datalist.appendChild(option);
                });
                drawerList.appendChild(datalist);
                if (!matches.length) {
                    const empty = document.createElement('div');
                    empty.className = 'b2b-tag-ms-empty px-2 py-2';
                    empty.textContent = drawerAvailableTags.length ? 'No matching tags' : 'No existing tags';
                    drawerList.appendChild(empty);
                    return;
                }
                matches.forEach(function (tag) {
                    const wrap = document.createElement('div');
                    wrap.className = 'b2b-tag-drawer-row';
                    const row = document.createElement('div');
                    row.className = 'b2b-tag-drawer-option';
                    const label = document.createElement('label');
                    label.className = 'b2b-tag-drawer-check';
                    const input = document.createElement('input');
                    input.type = 'checkbox';
                    input.className = 'form-check-input';
                    input.checked = drawerTagSet.has(tag);
                    input.disabled = drawerBusy;
                    input.addEventListener('change', function () {
                        if (input.checked) drawerTagSet.add(tag);
                        else drawerTagSet.delete(tag);
                        renderDrawerChips();
                    });
                    const text = document.createElement('span');
                    text.className = 'b2b-tag-drawer-name';
                    text.textContent = tag;
                    text.title = tag;
                    label.appendChild(input);
                    label.appendChild(text);
                    const actions = document.createElement('span');
                    actions.className = 'b2b-tag-drawer-actions';
                    const mergeBtn = document.createElement('button');
                    mergeBtn.type = 'button';
                    mergeBtn.className = 'b2b-tag-drawer-action b2b-tag-drawer-action-merge';
                    mergeBtn.textContent = 'Merge';
                    mergeBtn.disabled = drawerBusy;
                    mergeBtn.addEventListener('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        drawerMergeFrom = drawerMergeFrom === tag ? '' : tag;
                        renderDrawerTagList();
                    });
                    const deleteBtn = document.createElement('button');
                    deleteBtn.type = 'button';
                    deleteBtn.className = 'b2b-tag-drawer-action b2b-tag-drawer-action-delete';
                    deleteBtn.textContent = 'Delete';
                    deleteBtn.disabled = drawerBusy;
                    deleteBtn.addEventListener('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        deleteDrawerTag(tag);
                    });
                    actions.appendChild(mergeBtn);
                    actions.appendChild(deleteBtn);
                    const count = document.createElement('span');
                    count.className = 'b2b-tag-ms-count';
                    count.textContent = String(Number(tagCounts[tag] || 0));
                    row.appendChild(label);
                    row.appendChild(actions);
                    row.appendChild(count);
                    wrap.appendChild(row);
                    if (drawerMergeFrom === tag) {
                        wrap.appendChild(buildMergePanel(tag));
                    }
                    drawerList.appendChild(wrap);
                });
            }

            function addDrawerNewTag() {
                const tag = (drawerNew?.value || '').trim();
                if (!tag) return;
                const exists = drawerAvailableTags.some(function (item) {
                    return String(item).toLowerCase() === tag.toLowerCase();
                });
                if (!exists) drawerAvailableTags = [tag].concat(drawerAvailableTags);
                drawerTagSet.add(exists
                    ? drawerAvailableTags.find(function (item) { return String(item).toLowerCase() === tag.toLowerCase(); })
                    : tag);
                if (drawerNew) drawerNew.value = '';
                if (drawerSearch) drawerSearch.value = '';
                showDrawerAlert('');
                renderDrawerTagList();
                renderDrawerChips();
            }

            function setTagDrawerOpen(open) {
                if (!drawerEl || !drawerBackdrop) return;
                drawerEl.classList.toggle('d-none', !open);
                drawerBackdrop.classList.toggle('d-none', !open);
                drawerEl.hidden = !open;
                drawerBackdrop.hidden = !open;
                drawerEl.setAttribute('aria-hidden', open ? 'false' : 'true');
                document.body.style.overflow = open ? 'hidden' : '';
                if (open) {
                    drawerAvailableTags = availableTags.slice();
                    drawerTagSet.clear();
                    drawerMergeFrom = '';
                    drawerBusy = false;
                    if (drawerSearch) drawerSearch.value = '';
                    if (drawerNew) drawerNew.value = '';
                    showDrawerAlert('');
                    updateDrawerSelectionCopy();
                    renderDrawerTagList();
                    renderDrawerChips();
                    setTimeout(function () { drawerSearch?.focus(); }, 0);
                }
            }

            function syncRowCheckboxesFromSelection() {
                if (tbody) {
                    tbody.querySelectorAll('.crm-shopify-row-check').forEach(function (box) {
                        box.checked = selectedCustomerIds.has(box.value);
                    });
                }
                updateSelectAllState();
                updateDrawerSelectionCopy();
            }

            function openTagsForIds(ids) {
                const next = (ids || []).map(String).filter(Boolean);
                if (!next.length) {
                    showAlert('warning', 'Select one or more customers first.', { dismissible: true });
                    return;
                }
                selectedCustomerIds.clear();
                next.forEach(function (id) { selectedCustomerIds.add(id); });
                syncRowCheckboxesFromSelection();
                setTagDrawerOpen(true);
            }

            async function applyDrawerTags() {
                const ids = Array.from(selectedCustomerIds);
                const tags = selectedDrawerTags();
                showDrawerAlert('');
                if (!ids.length) {
                    showDrawerAlert('Select one or more customers in the table first.');
                    return;
                }
                if (!tags.length) {
                    showDrawerAlert('Select existing tags or add a new tag.');
                    return;
                }
                if (drawerApply) drawerApply.disabled = true;
                if (drawerApplySpinner) drawerApplySpinner.classList.remove('d-none');
                try {
                    const json = await postJsonCrm(addTagsUrl, { ids: ids.map(Number), tags: tags });
                    setTagDrawerOpen(false);
                    selectedCustomerIds.clear();
                    if (selectAll) {
                        selectAll.checked = false;
                        selectAll.indeterminate = false;
                    }
                    tags.forEach(function (tag) {
                        if (availableTags.indexOf(tag) === -1) availableTags.push(tag);
                    });
                    availableTags.sort(function (a, b) { return String(a).localeCompare(String(b), undefined, { sensitivity: 'base' }); });
                    renderTagOptions();
                    showAlert('success', json.message || 'Tags added.', { autoHideMs: 5000, dismissible: false });
                    await refreshTagsForType(typeSelect ? typeSelect.value : '');
                    await loadPage(state.page, { loadingMessage: 'Refreshing tags…' });
                    if (json.failed) {
                        showAlert('warning', json.message + ' ' + (json.errors || []).join(' '), { dismissible: true });
                    }
                } catch (e) {
                    showDrawerAlert(e && e.message ? e.message : 'Could not add tags.');
                } finally {
                    if (drawerApply) drawerApply.disabled = false;
                    if (drawerApplySpinner) drawerApplySpinner.classList.add('d-none');
                }
            }

            function loadVisibleFilters() {
                try {
                    const stored = JSON.parse(localStorage.getItem(filterVisibilityStorageKey) || '{}');
                    return Object.assign({}, defaultVisibleFilters, stored && typeof stored === 'object' ? stored : {});
                } catch (e) {
                    return Object.assign({}, defaultVisibleFilters);
                }
            }

            function saveVisibleFilters() {
                try {
                    localStorage.setItem(filterVisibilityStorageKey, JSON.stringify(visibleFilters));
                } catch (e) {}
            }

            function filterValueForKey(key) {
                if (key === 'search') return (searchInput?.value || '').trim();
                if (key === 'tag') return (state.tags || []).join(',');
                if (key === 'customerType') return (typeSelect?.value || '').trim();
                if (key === 'classificationSource') return (sourceSelect?.value || '').trim();
                if (key === 'marketplaceChannel') return (marketplaceChannelSelect?.value || '').trim();
                if (key === 'syncStatus') return (syncStatusSelect?.value || '').trim();
                return '';
            }

            function clearFilterValueForKey(key) {
                if (key === 'search' && searchInput) searchInput.value = '';
                if (key === 'tag') setSelectedTags([]);
                if (key === 'customerType' && typeSelect) typeSelect.value = 'all';
                if (key === 'classificationSource' && sourceSelect) sourceSelect.value = '';
                if (key === 'marketplaceChannel' && marketplaceChannelSelect) marketplaceChannelSelect.value = '';
                if (key === 'syncStatus' && syncStatusSelect) syncStatusSelect.value = '';
            }

            function applyFilterVisibility(options) {
                options = options || {};
                let clearedHiddenFilter = false;

                filterControls.forEach(function (control) {
                    const key = control.getAttribute('data-filter-control');
                    const visible = visibleFilters[key] !== false;
                    control.classList.toggle('d-none', !visible);

                    if (!visible && options.clearHidden && filterValueForKey(key) !== '') {
                        clearFilterValueForKey(key);
                        clearedHiddenFilter = true;
                    }
                });

                filterVisibilityInputs.forEach(function (input) {
                    const key = input.getAttribute('data-filter-visibility');
                    input.checked = visibleFilters[key] !== false;
                });

                return clearedHiddenFilter;
            }

            function setTableBusy(busy) {
                if (tableRegion) {
                    tableRegion.setAttribute('aria-busy', busy ? 'true' : 'false');
                }
            }

            function setListLoading(on, message) {
                if (overlay) {
                    overlay.classList.toggle('d-none', !on);
                    overlay.classList.toggle('d-flex', on);
                }
                if (loadingMessage && message) {
                    loadingMessage.textContent = message;
                }
                setTableBusy(on);
                if (perPageSelect) perPageSelect.disabled = on;
                if (searchInput) searchInput.disabled = on;
                if (tagToggle) tagToggle.disabled = on;
                if (tagSearch) tagSearch.disabled = on;
                if (tagClear) tagClear.disabled = on;
                if (typeSelect) typeSelect.disabled = on;
                if (sourceSelect) sourceSelect.disabled = on;
                if (marketplaceChannelSelect) marketplaceChannelSelect.disabled = on;
                if (syncStatusSelect) syncStatusSelect.disabled = on;
                sortButtons.forEach(function (button) {
                    button.disabled = on;
                });
                if (on) {
                    [firstBtn, prevBtn, nextBtn, lastBtn].forEach(function (b) {
                        if (b) b.disabled = true;
                    });
                    if (pageNumbersEl) {
                        pageNumbersEl.querySelectorAll('button').forEach(function (b) {
                            b.disabled = true;
                        });
                    }
                }
            }

            function clearSuccessTimer() {
                if (successHideTimer) {
                    clearTimeout(successHideTimer);
                    successHideTimer = null;
                }
            }

            function showAlert(type, message, options) {
                options = options || {};
                if (!alertEl) return;
                clearSuccessTimer();
                alertEl.classList.remove('d-none', 'alert-danger', 'alert-success', 'alert-info', 'alert-warning', 'alert-dismissible', 'fade', 'show');
                const variant = type === 'error' ? 'danger' : type === 'success' ? 'success' : type === 'warning' ? 'warning' : 'info';
                alertEl.classList.add('alert-' + variant);
                alertEl.innerHTML = '';

                if (options.dismissible !== false && type === 'error') {
                    alertEl.classList.add('alert-dismissible', 'fade', 'show');
                    alertEl.innerHTML =
                        '<span class="alert-message"></span>' +
                        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                    alertEl.querySelector('.alert-message').textContent = message;
                } else {
                    alertEl.textContent = message;
                }

                if (type === 'success' && options.autoHideMs) {
                    successHideTimer = setTimeout(function () {
                        hideAlert();
                    }, options.autoHideMs);
                }
            }

            function hideAlert() {
                clearSuccessTimer();
                if (!alertEl) return;
                alertEl.classList.add('d-none');
                alertEl.innerHTML = '';
                alertEl.textContent = '';
            }

            function humanHttpStatus(status) {
                if (status === 401 || status === 419) return 'Your session may have expired. Refresh the page and try again.';
                if (status === 403) return 'You do not have permission to perform this action.';
                if (status === 404) return 'The requested resource was not found.';
                if (status === 422) return 'The request could not be processed.';
                if (status >= 500) return 'The server reported an error. Try again in a moment.';
                return null;
            }

            function messageFromJson(json, res) {
                if (!json || typeof json !== 'object') return null;
                if (typeof json.message === 'string' && json.message.trim() !== '') {
                    return json.message;
                }
                if (json.errors && typeof json.errors === 'object') {
                    const parts = [];
                    Object.keys(json.errors).forEach(function (k) {
                        const v = json.errors[k];
                        if (Array.isArray(v)) {
                            v.forEach(function (x) {
                                parts.push(String(x));
                            });
                        } else if (v != null) {
                            parts.push(String(v));
                        }
                    });
                    if (parts.length) return parts.join(' ');
                }
                const hint = humanHttpStatus(res.status);
                return hint || ('Request failed (HTTP ' + res.status + ').');
            }

            function formatSynced(iso) {
                if (!iso) return '—';
                try {
                    const d = new Date(iso);
                    if (Number.isNaN(d.getTime())) return iso;
                    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                    return d.getDate() + ' ' + months[d.getMonth()] + ' ' + String(d.getFullYear()).slice(-2);
                } catch (e) {
                    return iso;
                }
            }

            function tdText(text) {
                const td = document.createElement('td');
                td.className = 'small';
                td.textContent = text == null || text === '' ? '—' : String(text);
                return td;
            }

            function tdMetric(text) {
                const td = document.createElement('td');
                td.className = 'small text-end text-nowrap';
                td.textContent = text == null || text === '' ? '—' : String(text);
                return td;
            }

            let openTip = null;
            let openTipHome = null;

            function hideDotTipNow() {
                if (!openTip || !openTipHome) return;
                openTip.classList.remove('is-open');
                openTip.style.left = '';
                openTip.style.top = '';
                if (openTip.parentNode !== openTipHome) openTipHome.appendChild(openTip);
                openTip = null;
                openTipHome = null;
            }

            function showDotTip(td) {
                const tip = td._dotTip;
                if (!tip) return;
                if (openTip && openTip !== tip) hideDotTipNow();
                openTip = tip;
                openTipHome = td;
                document.body.appendChild(tip);
                tip.classList.add('is-open');
                const rect = td.getBoundingClientRect();
                const tipRect = tip.getBoundingClientRect();
                let left = rect.left + (rect.width / 2) - (tipRect.width / 2);
                left = Math.max(8, Math.min(left, window.innerWidth - tipRect.width - 8));
                let top = rect.top - tipRect.height - 8;
                if (top < 8) top = rect.bottom + 8;
                tip.style.left = left + 'px';
                tip.style.top = top + 'px';
            }

            function paintDotCell(td, options) {
                const available = !!options.available;
                const text = options.text == null || String(options.text).trim() === ''
                    ? '—'
                    : String(options.text);
                td.className = (options.className || 'text-center') + ' b2b-dot-cell';
                td.setAttribute('aria-label', text);
                td.innerHTML = '';
                const dot = document.createElement('span');
                dot.className = 'b2b-dot ' + (available ? 'b2b-dot-yes' : 'b2b-dot-no');
                const tip = document.createElement('div');
                tip.className = 'b2b-dot-tip';
                tip.textContent = text;
                td.appendChild(dot);
                td.appendChild(tip);
                td._dotTip = tip;
                if (options.href) {
                    td.style.cursor = 'pointer';
                    td.setAttribute('data-tip-href', options.href);
                } else {
                    td.style.cursor = 'default';
                    td.removeAttribute('data-tip-href');
                }
                if (!td.dataset.dotBound) {
                    td.dataset.dotBound = '1';
                    td.addEventListener('mouseenter', function () { showDotTip(td); });
                    td.addEventListener('mouseleave', hideDotTipNow);
                    td.addEventListener('click', function () {
                        const href = td.getAttribute('data-tip-href');
                        if (href) window.open(href, '_blank', 'noopener,noreferrer');
                    });
                }
            }

            function hoverDotTd(options) {
                const td = document.createElement('td');
                paintDotCell(td, options);
                return td;
            }

            function addressTd(address, zip) {
                const value = address == null ? '' : String(address).trim();
                const zipValue = zip == null ? '' : String(zip).trim();
                const parts = [];
                if (value) parts.push(value);
                if (zipValue) parts.push('Zip: ' + zipValue);
                return hoverDotTd({
                    available: !!(value || zipValue),
                    text: parts.length ? parts.join('\n') : '—',
                });
            }

            function syncTd(status) {
                const td = document.createElement('td');
                td.className = 'text-center';
                const synced = String(status || '').toLowerCase() === 'synced';
                const icon = document.createElement('span');
                icon.className = 'b2b-sync-icon ' + (synced ? 'b2b-sync-ok' : 'b2b-sync-no');
                icon.title = synced ? 'Synced' : (status ? String(status) : 'Not synced');
                icon.setAttribute('aria-label', icon.title);
                icon.innerHTML = synced
                    ? '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M11.534 7h3.932a.25.25 0 0 0 .192-.41l-1.966-2.36a.25.25 0 0 0-.384 0l-1.966 2.36a.25.25 0 0 0 .192.41m-11 2h3.932a.25.25 0 0 1 .192.41l-1.966 2.36a.25.25 0 0 1-.384 0L.192 9.41A.25.25 0 0 1 .384 9z"/><path fill-rule="evenodd" d="M8 3c-1.552 0-2.94.707-3.857 1.818a.5.5 0 1 1-.771-.636A6.002 6.002 0 0 1 13.917 7H12.9A5 5 0 0 0 8 3M3.1 9a5.002 5.002 0 0 0 8.757 2.182.5.5 0 1 1 .771.636A6.002 6.002 0 0 1 2.083 9z"/></svg>'
                    : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z"/></svg>';
                td.appendChild(icon);
                return td;
            }

            function phoneDigits(phone) {
                return String(phone || '').replace(/\D/g, '');
            }

            function paintWhatsappCell(td, row) {
                td.setAttribute('data-whatsapp-id', String(row.id));
                if (row.phone && row.whatsapp == null && row.whatsapp_checked !== true) {
                    td.className = 'small text-center';
                    td.removeAttribute('data-tip');
                    td.removeAttribute('data-tip-href');
                    td.removeAttribute('aria-label');
                    td.classList.remove('b2b-dot-cell');
                    td.innerHTML = '';
                    const spin = document.createElement('span');
                    spin.className = 'spinner-border spinner-border-sm text-secondary';
                    spin.style.width = '.65rem';
                    spin.style.height = '.65rem';
                    spin.setAttribute('role', 'status');
                    td.appendChild(spin);
                    return;
                }
                const available = row.whatsapp === true;
                let text = '—';
                let href = null;
                if (available) {
                    text = String(row.phone);
                    const digits = phoneDigits(row.phone);
                    href = digits ? ('https://wa.me/' + digits) : null;
                } else if (row.phone && row.whatsapp === false) {
                    text = 'No';
                }
                paintDotCell(td, { available: available, text: text, href: href });
            }

            function whatsappTd(row) {
                const td = document.createElement('td');
                td.setAttribute('data-whatsapp-id', String(row.id));
                paintWhatsappCell(td, row);
                return td;
            }

            async function checkWhatsappForRows(rows) {
                const pending = (rows || []).filter(function (r) {
                    return r && r.id && r.phone && r.whatsapp == null && r.whatsapp_checked !== true;
                });
                const ids = pending.map(function (r) { return r.id; });
                if (!ids.length) return;

                const paintRow = function (row) {
                    const td = tbody ? tbody.querySelector('[data-whatsapp-id="' + row.id + '"]') : null;
                    if (td) paintWhatsappCell(td, row);
                };

                try {
                    const res = await fetch(whatsappCheckUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrf,
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ ids: ids }),
                    });
                    const json = await res.json();
                    const data = Array.isArray(json.data) ? json.data : [];
                    const byId = {};
                    data.forEach(function (item) { byId[String(item.id)] = item; });
                    pending.forEach(function (row) {
                        const item = byId[String(row.id)];
                        row.whatsapp_checked = true;
                        row.whatsapp = item ? item.whatsapp : null;
                        paintRow(row);
                    });
                } catch (e) {
                    pending.forEach(function (row) {
                        row.whatsapp_checked = true;
                        row.whatsapp = null;
                        paintRow(row);
                    });
                }
            }

            function humanLabel(value) {
                if (!value) return '—';
                if (String(value).toLowerCase() === 'direct') return 'B2C';
                return String(value).replace(/[_-]+/g, ' ').replace(/\b\w/g, function (m) { return m.toUpperCase(); });
            }

            function badgeTd(value, badgeClass, title) {
                const td = document.createElement('td');
                td.className = 'small';
                if (value) {
                    const badge = document.createElement('span');
                    badge.className = badgeClass || 'badge bg-light text-dark border';
                    badge.textContent = humanLabel(value);
                    if (title) badge.title = title;
                    td.appendChild(badge);
                } else {
                    td.textContent = '—';
                }
                return td;
            }

            const fmtMoney = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 2 });
            const fmtNum   = new Intl.NumberFormat('en-US');

            function updateSummary(summary, meta) {
                summary = summary || {};
                // type counts
                summaryEls.forEach(function (el) {
                    const key = el.getAttribute('data-summary-key') || '';
                    el.textContent = fmtNum.format(summary[key] || 0);
                });
                // filtered total = paginator total
                const totalEl = document.querySelector('[data-summary-key="all"]');
                if (totalEl && meta && meta.total != null) {
                    totalEl.textContent = fmtNum.format(meta.total);
                }
                // filtered stats
                const fs = (meta && meta.filtered_stats) || {};
                document.querySelectorAll('[data-fstat]').forEach(function (el) {
                    const key = el.getAttribute('data-fstat') || '';
                    const val = fs[key];
                    if (val == null) { el.textContent = '—'; return; }
                    if (key === 'total_order_value' || key === 'avg_order_value') {
                        el.textContent = fmtMoney.format(val);
                    } else {
                        el.textContent = fmtNum.format(val);
                    }
                });
            }

            function updateSortHeaders(meta) {
                meta = meta || {};
                state.sortBy = meta.sort_by || state.sortBy;
                state.sortDir = meta.sort_dir || state.sortDir;

                sortButtons.forEach(function (button) {
                    const sortBy = button.getAttribute('data-sort-by') || '';
                    const baseLabel = button.getAttribute('data-sort-label') || button.textContent.replace(/[↑↓]\s*$/, '').trim();
                    button.setAttribute('data-sort-label', baseLabel);
                    button.setAttribute('aria-sort', sortBy === state.sortBy ? (state.sortDir === 'asc' ? 'ascending' : 'descending') : 'none');
                    button.textContent = baseLabel + (sortBy === state.sortBy ? (state.sortDir === 'asc' ? ' ↑' : ' ↓') : '');
                });
            }

            function renderRows(rows) {
                if (!tbody) return;
                hideDotTipNow();
                lastRowsById = {};
                tbody.innerHTML = '';
                if (!rows.length) {
                    const tr = document.createElement('tr');
                    const td = document.createElement('td');
                    td.colSpan = tableColSpan;
                    td.className = 'text-muted text-center py-4';
                    td.textContent = state.duplicateBy
                        ? ('No duplicate customers found for ' + duplicateByLabel(state.duplicateBy) + '.')
                        : 'No customers found. Try syncing from Shopify or adjust search.';
                    tr.appendChild(td);
                    tbody.appendChild(tr);
                    return;
                }
                rows.forEach(function (r) {
                    lastRowsById[String(r.id)] = r;
                    const tr = document.createElement('tr');

                    const tdCheck = document.createElement('td');
                    tdCheck.className = 'text-center';
                    const check = document.createElement('input');
                    check.type = 'checkbox';
                    check.className = 'form-check-input crm-shopify-row-check';
                    check.value = String(r.id);
                    check.checked = selectedCustomerIds.has(String(r.id));
                    check.addEventListener('change', function () {
                        if (check.checked) selectedCustomerIds.add(String(r.id));
                        else selectedCustomerIds.delete(String(r.id));
                        updateSelectAllState();
                        updateDrawerSelectionCopy();
                    });
                    tdCheck.appendChild(check);

                    const tdId = document.createElement('td');
                    tdId.className = 'font-monospace small d-none';
                    tdId.textContent = r.shopify_customer_id != null ? String(r.shopify_customer_id) : '';

                    const tdName = tdText(r.name || '');

                    const email = r.email == null ? '' : String(r.email).trim();
                    const phone = r.phone == null ? '' : String(r.phone).trim();
                    const tdEmail = hoverDotTd({ available: !!email, text: email || '—' });
                    const tdPhone = hoverDotTd({ available: !!phone, text: phone || '—' });
                    const tdWhatsapp = whatsappTd(r);
                    const tdAddress = addressTd(r.address, r.zip);
                    const tdProvince = tdText(r.province);
                    const tdWebsite = socialInputTd(r.id, 'website', r.website, 'Website');
                    const tdFacebook = socialInputTd(r.id, 'facebook', r.facebook, 'FB');
                    const tdInstagram = socialInputTd(r.id, 'instagram', r.instagram, 'Insta');
                    const ordersCount = Number(r.orders_count || 0);
                    const qty = Number(r.qty || 0);
                    const revenue = Number(r.revenue || 0);
                    const tdOrders = tdMetric(fmtNum.format(ordersCount));
                    const tdQty = tdMetric(fmtNum.format(qty));
                    const tdRevenue = tdMetric(fmtMoney.format(revenue));
                    const tdOrderDate = document.createElement('td');
                    tdOrderDate.className = 'small text-nowrap';
                    tdOrderDate.textContent = formatSynced(r.order_date);

                    const tdType = badgeTd(r.customer_type || 'unknown', 'badge bg-primary-subtle text-primary border', r.classification_reason || '');

                    const tdChannel = document.createElement('td');
                    tdChannel.className = 'small';
                    if (r.marketplace_channel_label || r.channel) {
                        const badge = document.createElement('span');
                        badge.className = 'badge bg-info-subtle text-info border';
                        badge.textContent = r.marketplace_channel_label || r.channel;
                        if (r.classification_reason || r.channel_source) {
                            badge.title = r.classification_reason || r.channel_source;
                        }
                        tdChannel.appendChild(badge);
                    } else {
                        tdChannel.textContent = '—';
                    }

                    const tdSource = badgeTd(r.classification_source, 'badge bg-light text-dark border', r.classification_reason || '');

                    const tdTags = document.createElement('td');
                    tdTags.className = 'b2b-tags-td';
                    const tagsWrap = document.createElement('div');
                    tagsWrap.className = 'd-inline-flex align-items-center flex-wrap gap-1';
                    const tags = Array.isArray(r.tags) ? r.tags : [];
                    if (tags.length) {
                        tags.forEach(function (tag) {
                            const span = document.createElement('span');
                            span.className = 'badge bg-secondary-subtle text-secondary border';
                            span.style.fontSize = '0.7rem';
                            span.textContent = tag;
                            tagsWrap.appendChild(span);
                        });
                    } else {
                        const empty = document.createElement('span');
                        empty.className = 'small text-muted';
                        empty.textContent = '—';
                        tagsWrap.appendChild(empty);
                    }
                    const addBtn = document.createElement('button');
                    addBtn.type = 'button';
                    addBtn.className = 'b2b-tag-add-btn';
                    addBtn.title = 'Add tags';
                    addBtn.setAttribute('aria-label', 'Add tags');
                    addBtn.textContent = '+';
                    addBtn.addEventListener('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        openTagsForIds(actionTargetIds(r.id));
                    });
                    tagsWrap.appendChild(addBtn);
                    tdTags.appendChild(tagsWrap);

                    const tdCrm = document.createElement('td');
                    tdCrm.className = 'small d-none';
                    if (r.customer_id) {
                        const a = document.createElement('a');
                        a.href = crmCustomerBase + '/' + r.customer_id;
                        a.textContent = String(r.customer_id);
                        tdCrm.appendChild(a);
                    } else {
                        tdCrm.textContent = '—';
                    }

                    const tdSync = syncTd(r.sync_status);

                    const tdLast = document.createElement('td');
                    tdLast.className = 'small text-nowrap';
                    tdLast.textContent = formatSynced(r.last_synced_at);

                    const tdFu = document.createElement('td');
                    tdFu.className = 'text-end text-nowrap';
                    const fuBtn = document.createElement('button');
                    fuBtn.type = 'button';
                    fuBtn.className = 'btn btn-outline-primary btn-sm';
                    fuBtn.textContent = 'Create Follow-up';
                    fuBtn.addEventListener('click', function () {
                        openFollowUpModal(r);
                    });
                    tdFu.appendChild(fuBtn);

                    const tdAct = document.createElement('td');
                    tdAct.className = 'text-center text-nowrap';
                    const editBtn = document.createElement('button');
                    editBtn.type = 'button';
                    editBtn.className = 'btn btn-outline-secondary btn-sm b2b-act-btn me-1';
                    editBtn.title = 'Edit';
                    editBtn.setAttribute('aria-label', 'Edit');
                    editBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325"/></svg>';
                    editBtn.addEventListener('click', function () { openEditModal(actionTargetIds(r.id)); });
                    const delBtn = document.createElement('button');
                    delBtn.type = 'button';
                    delBtn.className = 'btn btn-outline-danger btn-sm b2b-act-btn';
                    delBtn.title = 'Delete';
                    delBtn.setAttribute('aria-label', 'Delete');
                    delBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg>';
                    delBtn.addEventListener('click', function () { openDeleteModal(actionTargetIds(r.id)); });
                    tdAct.appendChild(editBtn);
                    tdAct.appendChild(delBtn);

                    tr.appendChild(tdCheck);
                    tr.appendChild(tdId);
                    tr.appendChild(tdName);
                    tr.appendChild(tdEmail);
                    tr.appendChild(tdPhone);
                    tr.appendChild(tdWhatsapp);
                    tr.appendChild(tdAddress);
                    tr.appendChild(tdProvince);
                    tr.appendChild(tdWebsite);
                    tr.appendChild(tdFacebook);
                    tr.appendChild(tdInstagram);
                    tr.appendChild(tdOrders);
                    tr.appendChild(tdQty);
                    tr.appendChild(tdRevenue);
                    tr.appendChild(tdOrderDate);
                    tr.appendChild(tdType);
                    tr.appendChild(tdChannel);
                    tr.appendChild(tdSource);
                    tr.appendChild(tdTags);
                    tr.appendChild(tdCrm);
                    tr.appendChild(tdSync);
                    tr.appendChild(tdLast);
                    tr.appendChild(tdFu);
                    tr.appendChild(tdAct);
                    tbody.appendChild(tr);
                });
                updateSelectAllState();
            }

            function pageWindow(current, last, spread) {
                const s = spread || 2;
                const pages = new Set();
                pages.add(1);
                pages.add(last);
                for (let i = current - s; i <= current + s; i++) {
                    if (i >= 1 && i <= last) pages.add(i);
                }
                return Array.from(pages).sort(function (a, b) { return a - b; });
            }

            function renderPageButtons(current, last) {
                if (!pageNumbersEl) return;
                pageNumbersEl.innerHTML = '';
                if (last <= 1) return;

                const nums = pageWindow(current, last, 2);
                let prevNum = 0;
                nums.forEach(function (num) {
                    if (prevNum && num - prevNum > 1) {
                        const li = document.createElement('li');
                        li.className = 'page-item disabled';
                        const span = document.createElement('span');
                        span.className = 'page-link';
                        span.textContent = '…';
                        li.appendChild(span);
                        pageNumbersEl.appendChild(li);
                    }
                    prevNum = num;

                    const li = document.createElement('li');
                    li.className = 'page-item' + (num === current ? ' active' : '');
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'page-link';
                    btn.textContent = String(num);
                    btn.setAttribute('aria-label', 'Page ' + num);
                    btn.setAttribute('aria-current', num === current ? 'page' : 'false');
                    if (num === current) {
                        btn.disabled = true;
                    } else {
                        btn.addEventListener('click', function () {
                            loadPage(num);
                        });
                    }
                    li.appendChild(btn);
                    pageNumbersEl.appendChild(li);
                });
            }

            function updatePagination(meta) {
                if (!paginationWrap || !prevBtn || !nextBtn || !firstBtn || !lastBtn || !pageSummary) return;
                state.lastPage = Math.max(1, meta.last_page || 1);
                state.total = meta.total || 0;
                const cur = Math.min(Math.max(1, state.page), state.lastPage);
                state.page = cur;

                const hasPages = state.lastPage > 1 || state.total > 0;
                paginationWrap.classList.toggle('d-none', !hasPages);

                const atStart = cur <= 1;
                const atEnd = cur >= state.lastPage;

                firstBtn.disabled = atStart;
                prevBtn.disabled = atStart;
                nextBtn.disabled = atEnd;
                lastBtn.disabled = atEnd;

                renderPageButtons(cur, state.lastPage);

                const from = meta.from;
                const to = meta.to;
                let range = '';
                if (from != null && to != null && state.total > 0) {
                    range = 'Showing ' + from + '–' + to + ' of ' + state.total;
                } else if (state.total === 0) {
                    range = 'No records';
                } else {
                    range = 'Page ' + cur + ' of ' + state.lastPage + ' · ' + state.total + ' total';
                }
                pageSummary.textContent = range + ' · ' + (meta.per_page || state.perPage) + ' per page';
            }

            async function loadPage(page, opts) {
                opts = opts || {};
                hideAlert();
                loadSeq += 1;
                const seq = loadSeq;

                if (listAbort) {
                    try { listAbort.abort(); } catch (e) {}
                }
                listAbort = new AbortController();

                state.page = Math.max(1, page);
                const params = new URLSearchParams({
                    page: String(state.page),
                    per_page: String(state.perPage),
                    sort_by: state.sortBy,
                    sort_dir: state.sortDir,
                });
                if (state.q) {
                    params.set('q', state.q);
                }
                (state.tags || []).forEach(function (tag) {
                    params.append('tags[]', tag);
                });
                if (state.customerType) {
                    params.set('customer_type', state.customerType);
                }
                if (state.classificationSource) {
                    params.set('classification_source', state.classificationSource);
                }
                if (state.marketplaceChannel) {
                    params.set('marketplace_channel', state.marketplaceChannel);
                }
                if (state.syncStatus) {
                    params.set('sync_status', state.syncStatus);
                }
                if (state.duplicateBy) {
                    params.set('duplicate_by', state.duplicateBy);
                }

                setListLoading(true, opts.loadingMessage || 'Loading customers…');

                try {
                    const res = await fetch(dataUrl + '?' + params.toString(), {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        signal: listAbort.signal,
                    });

                    let json = {};
                    try {
                        const text = await res.text();
                        if (text) json = JSON.parse(text);
                    } catch (parseErr) {
                        throw new Error('Invalid response from server. Try refreshing the page.');
                    }

                    if (seq !== loadSeq) return;

                    if (!res.ok) {
                        const msg = messageFromJson(json, res) || 'Could not load customers.';
                        const err = new Error(msg);
                        err.retryPage = state.page;
                        throw err;
                    }

                    renderRows(json.data || []);
                    checkWhatsappForRows(json.data || []);
                    updatePagination(json.meta || {});
                    updateSortHeaders(json.meta || {});
                    updateSummary((json.meta || {}).summary || {}, json.meta || {});
                    updateDuplicateUi();
                } catch (e) {
                    if (e.name === 'AbortError') return;
                    const msg = e && e.message
                        ? e.message
                        : ('network' in navigator && !navigator.onLine
                            ? 'You appear to be offline. Check your connection.'
                            : 'Failed to load customers.');
                    showAlert('error', msg, { dismissible: true });
                    if (tbody) {
                        tbody.innerHTML = '';
                        const tr = document.createElement('tr');
                        const td = document.createElement('td');
                        td.colSpan = tableColSpan;
                        td.className = 'text-center py-4';
                        const wrap = document.createElement('div');
                        wrap.className = 'text-danger small mb-2';
                        wrap.textContent = msg;
                        const retry = document.createElement('button');
                        retry.type = 'button';
                        retry.className = 'btn btn-sm btn-outline-primary';
                        retry.textContent = 'Retry';
                        retry.addEventListener('click', function () {
                            loadPage(state.page);
                        });
                        td.appendChild(wrap);
                        td.appendChild(retry);
                        tr.appendChild(td);
                        tbody.appendChild(tr);
                    }
                    if (paginationWrap) paginationWrap.classList.add('d-none');
                } finally {
                    if (seq === loadSeq) {
                        setListLoading(false);
                    }
                }
            }

            const followUpModalEl = document.getElementById('crm-shopify-followup-modal');
            const followUpForm = document.getElementById('crm-shopify-followup-form');
            const followUpModalAlert = document.getElementById('crm-shopify-followup-modal-alert');
            const fuSubmitBtn = document.getElementById('crm-shopify-fu-submit');
            const fuSubmitSpinner = fuSubmitBtn ? fuSubmitBtn.querySelector('.fu-submit-spinner') : null;

            function followUpStoreUrl(recordId) {
                return shopifyCustomersBase + '/' + encodeURIComponent(recordId) + '/follow-ups';
            }

            function hideFollowUpModalAlert() {
                if (!followUpModalAlert) return;
                followUpModalAlert.classList.add('d-none');
                followUpModalAlert.textContent = '';
            }

            function showFollowUpModalAlert(message) {
                if (!followUpModalAlert) return;
                followUpModalAlert.textContent = message;
                followUpModalAlert.classList.remove('d-none');
            }

            function openFollowUpModal(r) {
                hideFollowUpModalAlert();
                const idEl = document.getElementById('crm-shopify-fu-shopify-record-id');
                const nameEl = document.getElementById('crm-shopify-fu-name');
                const emailEl = document.getElementById('crm-shopify-fu-email');
                const crmEl = document.getElementById('crm-shopify-fu-crm-id');
                const shopifyEl = document.getElementById('crm-shopify-fu-shopify-label');
                if (idEl) idEl.value = String(r.id);
                if (nameEl) nameEl.value = r.name || '';
                if (emailEl) emailEl.value = r.email || '';
                if (crmEl) crmEl.value = r.customer_id != null ? String(r.customer_id) : '— (linked on save if possible)';
                if (shopifyEl) shopifyEl.value = r.shopify_customer_id != null ? String(r.shopify_customer_id) : '';
                if (followUpModalEl && window.bootstrap && window.bootstrap.Modal) {
                    window.bootstrap.Modal.getOrCreateInstance(followUpModalEl).show();
                }
            }

            const createModalEl = document.getElementById('crm-shopify-create-modal');
            const createForm = document.getElementById('crm-shopify-create-form');
            const createAlert = document.getElementById('crm-shopify-create-alert');
            const createSubmitBtn = document.getElementById('crm-shopify-create-submit');
            const createSubmitSpinner = createSubmitBtn ? createSubmitBtn.querySelector('.create-submit-spinner') : null;

            function showCreateAlert(message) {
                if (!createAlert) return;
                createAlert.textContent = message;
                createAlert.classList.remove('d-none');
            }

            function hideCreateAlert() {
                if (!createAlert) return;
                createAlert.classList.add('d-none');
                createAlert.textContent = '';
            }

            createBtn?.addEventListener('click', function () {
                hideCreateAlert();
                createForm?.reset();
                if (createModalEl && window.bootstrap && window.bootstrap.Modal) {
                    window.bootstrap.Modal.getOrCreateInstance(createModalEl).show();
                }
            });

            createForm?.addEventListener('submit', async function (ev) {
                ev.preventDefault();
                hideCreateAlert();
                const payload = {
                    name: document.getElementById('crm-shopify-create-name')?.value || '',
                    email: document.getElementById('crm-shopify-create-email')?.value || '',
                    phone: document.getElementById('crm-shopify-create-phone')?.value || '',
                    province: document.getElementById('crm-shopify-create-province')?.value || '',
                    zip: document.getElementById('crm-shopify-create-zip')?.value || '',
                    website: document.getElementById('crm-shopify-create-website')?.value || '',
                    facebook: document.getElementById('crm-shopify-create-facebook')?.value || '',
                    instagram: document.getElementById('crm-shopify-create-instagram')?.value || '',
                    tags: document.getElementById('crm-shopify-create-tags')?.value || '',
                };
                if (createSubmitBtn) createSubmitBtn.disabled = true;
                if (createSubmitSpinner) createSubmitSpinner.classList.remove('d-none');
                try {
                    const res = await fetch(storeUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(payload),
                    });
                    let json = {};
                    try {
                        const text = await res.text();
                        if (text) json = JSON.parse(text);
                    } catch (parseErr) {
                        throw new Error('Invalid response from server.');
                    }
                    if (!res.ok) {
                        throw new Error(messageFromJson(json, res) || 'Could not create customer.');
                    }
                    if (createModalEl && window.bootstrap && window.bootstrap.Modal) {
                        window.bootstrap.Modal.getOrCreateInstance(createModalEl).hide();
                    }
                    showAlert('success', json.message || 'Customer synced to Shopify.', { autoHideMs: 6000, dismissible: false });
                    await loadPage(1, { loadingMessage: 'Refreshing list…' });
                } catch (e) {
                    showCreateAlert(e && e.message ? e.message : 'Request failed.');
                } finally {
                    if (createSubmitBtn) createSubmitBtn.disabled = false;
                    if (createSubmitSpinner) createSubmitSpinner.classList.add('d-none');
                }
            });

            const importModalEl = document.getElementById('crm-shopify-import-modal');
            const importForm = document.getElementById('crm-shopify-import-form');
            const importAlert = document.getElementById('crm-shopify-import-alert');
            const importSubmitBtn = document.getElementById('crm-shopify-import-submit');
            const importSubmitSpinner = importSubmitBtn ? importSubmitBtn.querySelector('.import-submit-spinner') : null;

            function showImportAlert(type, message) {
                if (!importAlert) return;
                importAlert.classList.remove('d-none', 'alert-danger', 'alert-success', 'alert-warning');
                importAlert.classList.add(type === 'success' ? 'alert-success' : type === 'warning' ? 'alert-warning' : 'alert-danger');
                importAlert.textContent = message;
            }

            function hideImportAlert() {
                if (!importAlert) return;
                importAlert.classList.add('d-none');
                importAlert.textContent = '';
            }

            importBtn?.addEventListener('click', function () {
                hideImportAlert();
                importForm?.reset();
                if (importModalEl && window.bootstrap && window.bootstrap.Modal) {
                    window.bootstrap.Modal.getOrCreateInstance(importModalEl).show();
                }
            });

            importForm?.addEventListener('submit', async function (ev) {
                ev.preventDefault();
                hideImportAlert();
                const fileEl = document.getElementById('crm-shopify-import-file');
                const file = fileEl && fileEl.files ? fileEl.files[0] : null;
                if (!file) {
                    showImportAlert('error', 'Choose a file to import.');
                    return;
                }
                const formData = new FormData();
                formData.append('file', file);
                if (importSubmitBtn) importSubmitBtn.disabled = true;
                if (importSubmitSpinner) importSubmitSpinner.classList.remove('d-none');
                try {
                    const res = await fetch(importUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: formData,
                    });
                    let json = {};
                    try {
                        const text = await res.text();
                        if (text) json = JSON.parse(text);
                    } catch (parseErr) {
                        throw new Error('Invalid response from server.');
                    }
                    if (!res.ok) {
                        throw new Error(messageFromJson(json, res) || 'Import failed.');
                    }
                    const errors = json.summary && Array.isArray(json.summary.errors) && json.summary.errors.length
                        ? ' Errors: ' + json.summary.errors.join(' | ')
                        : '';
                    showImportAlert(errors ? 'warning' : 'success', (json.message || 'Import finished.') + errors);
                    showAlert(errors ? 'warning' : 'success', json.message || 'Import finished.', { autoHideMs: 7000, dismissible: false });
                    await loadPage(1, { loadingMessage: 'Refreshing list…' });
                } catch (e) {
                    showImportAlert('error', e && e.message ? e.message : 'Request failed.');
                } finally {
                    if (importSubmitBtn) importSubmitBtn.disabled = false;
                    if (importSubmitSpinner) importSubmitSpinner.classList.add('d-none');
                }
            });

            function actionTargetIds(rowId) {
                const id = Number(rowId);
                const selected = selectedCustomerIdList();
                if (selected.length > 1 && selected.indexOf(id) !== -1) {
                    return selected;
                }
                return [id];
            }

            const editModalEl = document.getElementById('crm-shopify-edit-modal');
            const editForm = document.getElementById('crm-shopify-edit-form');
            const editAlert = document.getElementById('crm-shopify-edit-alert');
            const editTitle = document.getElementById('crm-shopify-edit-modal-label');
            const editSub = document.getElementById('crm-shopify-edit-modal-sub');
            const editHint = document.getElementById('crm-shopify-edit-hint');
            const editSubmitBtn = document.getElementById('crm-shopify-edit-submit');
            const editSubmitSpinner = editSubmitBtn ? editSubmitBtn.querySelector('.edit-submit-spinner') : null;
            let editTargetIds = [];

            function showEditAlert(message) {
                if (!editAlert) return;
                editAlert.textContent = message || '';
                editAlert.classList.toggle('d-none', !message);
            }

            function setEditField(id, value) {
                const el = document.getElementById(id);
                if (el) el.value = value == null ? '' : String(value);
            }

            function openEditModal(ids) {
                editTargetIds = (ids || []).map(Number).filter(function (id) { return id > 0; });
                if (!editTargetIds.length) {
                    showAlert('warning', 'Select one or more customers first.', { dismissible: true });
                    return;
                }
                showEditAlert('');
                const bulk = editTargetIds.length > 1;
                if (editTitle) editTitle.textContent = bulk ? ('Edit ' + editTargetIds.length + ' customers') : 'Edit customer';
                if (editSub) editSub.textContent = bulk
                    ? 'Filled fields are applied to every selected customer. Leave a field blank to keep its current value.'
                    : 'Update this customer in Shopify.';
                if (editHint) editHint.textContent = bulk
                    ? 'Blank fields are left unchanged on each selected customer.'
                    : 'Name, email, phone, state, zip, and tags are saved to Shopify. Website, FB, and Insta stay in CRM only.';
                setEditField('crm-shopify-edit-name', '');
                setEditField('crm-shopify-edit-email', '');
                setEditField('crm-shopify-edit-phone', '');
                setEditField('crm-shopify-edit-province', '');
                setEditField('crm-shopify-edit-zip', '');
                setEditField('crm-shopify-edit-website', '');
                setEditField('crm-shopify-edit-facebook', '');
                setEditField('crm-shopify-edit-instagram', '');
                setEditField('crm-shopify-edit-tags', '');
                if (!bulk) {
                    const row = lastRowsById[String(editTargetIds[0])] || {};
                    setEditField('crm-shopify-edit-name', row.name || '');
                    setEditField('crm-shopify-edit-email', row.email || '');
                    setEditField('crm-shopify-edit-phone', row.phone || '');
                    setEditField('crm-shopify-edit-province', row.province || '');
                    setEditField('crm-shopify-edit-zip', row.zip || '');
                    setEditField('crm-shopify-edit-website', row.website || '');
                    setEditField('crm-shopify-edit-facebook', row.facebook || '');
                    setEditField('crm-shopify-edit-instagram', row.instagram || '');
                    setEditField('crm-shopify-edit-tags', Array.isArray(row.tags) ? row.tags.join(', ') : '');
                }
                if (editModalEl && window.bootstrap && window.bootstrap.Modal) {
                    window.bootstrap.Modal.getOrCreateInstance(editModalEl).show();
                }
            }

            editForm?.addEventListener('submit', async function (ev) {
                ev.preventDefault();
                showEditAlert('');
                if (!editTargetIds.length) {
                    showEditAlert('Select one or more customers first.');
                    return;
                }
                const payload = {
                    ids: editTargetIds,
                    name: document.getElementById('crm-shopify-edit-name')?.value || '',
                    email: document.getElementById('crm-shopify-edit-email')?.value || '',
                    phone: document.getElementById('crm-shopify-edit-phone')?.value || '',
                    province: document.getElementById('crm-shopify-edit-province')?.value || '',
                    zip: document.getElementById('crm-shopify-edit-zip')?.value || '',
                    website: document.getElementById('crm-shopify-edit-website')?.value || '',
                    facebook: document.getElementById('crm-shopify-edit-facebook')?.value || '',
                    instagram: document.getElementById('crm-shopify-edit-instagram')?.value || '',
                    tags: document.getElementById('crm-shopify-edit-tags')?.value || '',
                };
                if (editSubmitBtn) editSubmitBtn.disabled = true;
                if (editSubmitSpinner) editSubmitSpinner.classList.remove('d-none');
                try {
                    const json = await postJsonCrm(updateCustomersUrl, payload);
                    if (editModalEl && window.bootstrap && window.bootstrap.Modal) {
                        window.bootstrap.Modal.getOrCreateInstance(editModalEl).hide();
                    }
                    showAlert('success', json.message || 'Customers updated.', { autoHideMs: 5000, dismissible: false });
                    if (json.failed) {
                        showAlert('warning', json.message + ' ' + (json.errors || []).join(' '), { dismissible: true });
                    }
                    await loadPage(state.page, { loadingMessage: 'Refreshing list…' });
                } catch (e) {
                    showEditAlert(e && e.message ? e.message : 'Could not update customers.');
                } finally {
                    if (editSubmitBtn) editSubmitBtn.disabled = false;
                    if (editSubmitSpinner) editSubmitSpinner.classList.add('d-none');
                }
            });

            const deleteModalEl = document.getElementById('crm-shopify-delete-modal');
            const deleteAlert = document.getElementById('crm-shopify-delete-alert');
            const deleteText = document.getElementById('crm-shopify-delete-text');
            const deleteConfirmBtn = document.getElementById('crm-shopify-delete-confirm');
            const deleteSubmitSpinner = deleteConfirmBtn ? deleteConfirmBtn.querySelector('.delete-submit-spinner') : null;
            let deleteTargetIds = [];

            function showDeleteAlert(message) {
                if (!deleteAlert) return;
                deleteAlert.textContent = message || '';
                deleteAlert.classList.toggle('d-none', !message);
            }

            function openDeleteModal(ids) {
                deleteTargetIds = (ids || []).map(Number).filter(function (id) { return id > 0; });
                if (!deleteTargetIds.length) {
                    showAlert('warning', 'Select one or more customers first.', { dismissible: true });
                    return;
                }
                showDeleteAlert('');
                if (deleteText) {
                    deleteText.textContent = deleteTargetIds.length === 1
                        ? 'Delete this customer from Shopify and this list? This cannot be undone.'
                        : ('Delete ' + deleteTargetIds.length + ' selected customers from Shopify and this list? This cannot be undone.');
                }
                if (deleteModalEl && window.bootstrap && window.bootstrap.Modal) {
                    window.bootstrap.Modal.getOrCreateInstance(deleteModalEl).show();
                }
            }

            deleteConfirmBtn?.addEventListener('click', async function () {
                showDeleteAlert('');
                if (!deleteTargetIds.length) {
                    showDeleteAlert('Select one or more customers first.');
                    return;
                }
                if (deleteConfirmBtn) deleteConfirmBtn.disabled = true;
                if (deleteSubmitSpinner) deleteSubmitSpinner.classList.remove('d-none');
                try {
                    const json = await postJsonCrm(deleteCustomersUrl, { ids: deleteTargetIds });
                    if (deleteModalEl && window.bootstrap && window.bootstrap.Modal) {
                        window.bootstrap.Modal.getOrCreateInstance(deleteModalEl).hide();
                    }
                    deleteTargetIds.forEach(function (id) { selectedCustomerIds.delete(String(id)); });
                    updateSelectAllState();
                    showAlert('success', json.message || 'Customers deleted.', { autoHideMs: 5000, dismissible: false });
                    if (json.failed) {
                        showAlert('warning', json.message + ' ' + (json.errors || []).join(' '), { dismissible: true });
                    }
                    await loadPage(state.page, { loadingMessage: 'Refreshing list…' });
                } catch (e) {
                    showDeleteAlert(e && e.message ? e.message : 'Could not delete customers.');
                } finally {
                    if (deleteConfirmBtn) deleteConfirmBtn.disabled = false;
                    if (deleteSubmitSpinner) deleteSubmitSpinner.classList.add('d-none');
                }
            });

            bulkEditBtn?.addEventListener('click', function () {
                openEditModal(selectedCustomerIdList());
            });
            bulkDeleteBtn?.addEventListener('click', function () {
                openDeleteModal(selectedCustomerIdList());
            });

            followUpForm?.addEventListener('submit', async function (ev) {
                ev.preventDefault();
                hideFollowUpModalAlert();
                const recordIdEl = document.getElementById('crm-shopify-fu-shopify-record-id');
                const recordId = recordIdEl ? recordIdEl.value : '';
                if (!recordId) {
                    showFollowUpModalAlert('Missing Shopify row.');
                    return;
                }
                const scheduledEl = document.getElementById('crm-shopify-fu-scheduled');
                const payload = {
                    title: document.getElementById('crm-shopify-fu-title')?.value,
                    description: (document.getElementById('crm-shopify-fu-description')?.value || '') || null,
                    follow_up_type: document.getElementById('crm-shopify-fu-type')?.value,
                    priority: document.getElementById('crm-shopify-fu-priority')?.value,
                    assigned_user_id: parseInt(document.getElementById('crm-shopify-fu-assignee')?.value || '0', 10),
                    scheduled_at: scheduledEl && scheduledEl.value ? scheduledEl.value : null,
                };
                if (fuSubmitBtn) fuSubmitBtn.disabled = true;
                if (fuSubmitSpinner) fuSubmitSpinner.classList.remove('d-none');
                try {
                    const res = await fetch(followUpStoreUrl(recordId), {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(payload),
                    });
                    let json = {};
                    try {
                        const text = await res.text();
                        if (text) json = JSON.parse(text);
                    } catch (parseErr) {
                        throw new Error('Invalid response from server.');
                    }
                    if (!res.ok) {
                        throw new Error(messageFromJson(json, res) || 'Could not create follow-up.');
                    }
                    if (followUpModalEl && window.bootstrap && window.bootstrap.Modal) {
                        window.bootstrap.Modal.getOrCreateInstance(followUpModalEl).hide();
                    }
                    const showUrl = typeof json.show_url === 'string' ? json.show_url : '';
                    let msg = (typeof json.message === 'string' && json.message) ? json.message : 'Follow-up created.';
                    if (showUrl) {
                        msg += ' Opening detail in a new tab.';
                    }
                    showAlert('success', msg, { autoHideMs: 6000, dismissible: false });
                    if (showUrl) {
                        window.open(showUrl, '_blank', 'noopener');
                    }
                    await loadPage(state.page, { loadingMessage: 'Refreshing list…' });
                } catch (e) {
                    showFollowUpModalAlert(e && e.message ? e.message : 'Request failed.');
                } finally {
                    if (fuSubmitBtn) fuSubmitBtn.disabled = false;
                    if (fuSubmitSpinner) fuSubmitSpinner.classList.add('d-none');
                }
            });

            async function runSync() {
                if (!syncBtn) return;
                hideAlert();
                syncBtn.disabled = true;
                if (syncSpinner) syncSpinner.classList.remove('d-none');
                syncStatus.textContent = '';
                setListLoading(true, 'Syncing from Shopify…');
                try {
                    const res = await fetch(syncUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: '{}',
                    });
                    let json = {};
                    try {
                        const text = await res.text();
                        if (text) json = JSON.parse(text);
                    } catch (parseErr) {
                        throw new Error('Invalid response from server during sync. Try refreshing the page.');
                    }
                    if (!res.ok) {
                        throw new Error(messageFromJson(json, res) || 'Sync failed.');
                    }
                    const n = json.synced ?? 0;
                    syncStatus.textContent = 'Last sync: ' + n + ' customer(s) processed.';
                    showAlert('success', 'Sync finished: ' + n + ' customer(s) processed.', { autoHideMs: 6000, dismissible: false });
                    await loadPage(1, { loadingMessage: 'Refreshing list…' });
                } catch (e) {
                    syncStatus.textContent = '';
                    const msg = e && e.message ? e.message : 'Sync failed.';
                    showAlert('error', msg, { dismissible: true });
                    setListLoading(false);
                } finally {
                    syncBtn.disabled = false;
                    if (syncSpinner) syncSpinner.classList.add('d-none');
                }
            }

            function duplicateByLabel(by) {
                if (by === 'email') return 'Email';
                if (by === 'phone') return 'Phone';
                if (by === 'name') return 'Name';
                if (by === 'address') return 'Address';
                return '';
            }

            function selectedDuplicateBy() {
                const checked = document.querySelector('input[name="crm-shopify-dup-by"]:checked');
                return (checked && checked.value) ? checked.value : 'email';
            }

            function updateDuplicateUi() {
                const label = duplicateByLabel(state.duplicateBy);
                if (dupBtn) {
                    dupBtn.textContent = label ? ('Duplicates: ' + label) : 'Search Duplicates';
                    dupBtn.classList.toggle('has-value', Boolean(label));
                }
                if (dupBanner) {
                    dupBanner.classList.toggle('d-none', !label);
                }
                if (dupBannerText && label) {
                    const total = Number(state.total || 0);
                    dupBannerText.textContent = total
                        ? ('Showing ' + total + ' customers with the same ' + label.toLowerCase() + '.')
                        : ('No duplicate customers found for ' + label + '.');
                }
            }

            function searchDuplicates() {
                state.duplicateBy = selectedDuplicateBy();
                updateDuplicateUi();
                loadPage(1, { loadingMessage: 'Finding duplicates…' });
            }

            function clearDuplicates() {
                state.duplicateBy = '';
                updateDuplicateUi();
                loadPage(1);
            }

            function applyFiltersNow() {
                if (filterDebounceTimer) {
                    clearTimeout(filterDebounceTimer);
                    filterDebounceTimer = null;
                }
                state.q = (searchInput?.value || '').trim();
                state.tags = selectedTags();
                state.customerType = (typeSelect?.value || '').trim();
                state.classificationSource = (sourceSelect?.value || '').trim();
                state.marketplaceChannel = (marketplaceChannelSelect?.value || '').trim();
                state.syncStatus = (syncStatusSelect?.value || '').trim();
                state.perPage = parseInt(perPageSelect?.value || '25', 10) || 25;
                loadPage(1);
            }

            function applyFiltersDebounced() {
                if (filterDebounceTimer) {
                    clearTimeout(filterDebounceTimer);
                }
                filterDebounceTimer = setTimeout(applyFiltersNow, 350);
            }

            // ── Dynamic tag refresh when customer type changes ──────
            const tagLoadingSpinner = document.getElementById('crm-shopify-tag-loading');
            const tagsUrl = @json(route('crm.shopify.customers.tags'));

            async function refreshTagsForType(customerType) {
                if (tagLoadingSpinner) tagLoadingSpinner.classList.remove('d-none');
                if (tagToggle) tagToggle.disabled = true;

                try {
                    const url = tagsUrl + (customerType ? '?customer_type=' + encodeURIComponent(customerType) : '');
                    const res = await fetch(url, { headers: { Accept: 'application/json' } });
                    if (!res.ok) return;
                    applyTagPayload(await res.json());
                    const keep = selectedTags().filter(function (tag) { return availableTags.indexOf(tag) !== -1; });
                    setSelectedTags(keep);
                } catch (_) {
                    // silently ignore fetch errors — keep current options
                } finally {
                    if (tagToggle) tagToggle.disabled = false;
                    if (tagLoadingSpinner) tagLoadingSpinner.classList.add('d-none');
                }
            }

            searchInput?.addEventListener('input', applyFiltersDebounced);
            tagToggle?.addEventListener('click', function (e) {
                e.preventDefault();
                setTagPanelOpen(tagPanel?.classList.contains('d-none'));
            });
            tagSearch?.addEventListener('input', renderTagOptions);
            tagSearch?.addEventListener('keydown', function (ev) {
                ev.stopPropagation();
                if (ev.key === 'Escape') setTagPanelOpen(false);
            });
            tagClear?.addEventListener('click', function () {
                setSelectedTags([], { apply: true });
            });
            tagPanel?.addEventListener('click', function (e) { e.stopPropagation(); });
            document.addEventListener('click', function (e) {
                if (!tagMs || tagMs.contains(e.target)) return;
                setTagPanelOpen(false);
            });
            document.addEventListener('keydown', function (ev) {
                if (ev.key === 'Escape') setTagPanelOpen(false);
            });
            typeSelect?.addEventListener('change', async function () {
                await refreshTagsForType(typeSelect.value);
                applyFiltersNow();
            });
            sourceSelect?.addEventListener('change', applyFiltersNow);
            marketplaceChannelSelect?.addEventListener('change', applyFiltersNow);
            syncStatusSelect?.addEventListener('change', applyFiltersNow);
            perPageSelect?.addEventListener('change', applyFiltersNow);
            dupSearchBtn?.addEventListener('click', searchDuplicates);
            dupClearBtn?.addEventListener('click', clearDuplicates);
            dupBannerClear?.addEventListener('click', clearDuplicates);

            filterVisibilityInputs.forEach(function (input) {
                input.addEventListener('change', function () {
                    const key = input.getAttribute('data-filter-visibility');
                    visibleFilters[key] = input.checked;
                    saveVisibleFilters();
                    if (applyFilterVisibility({ clearHidden: true })) {
                        applyFiltersNow();
                    }
                });
            });

            searchInput?.addEventListener('keydown', function (ev) {
                if (ev.key === 'Enter') {
                    ev.preventDefault();
                    applyFiltersNow();
                }
            });

            sortButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    const nextSort = button.getAttribute('data-sort-by') || 'last_synced_at';
                    if (state.sortBy === nextSort) {
                        state.sortDir = state.sortDir === 'asc' ? 'desc' : 'asc';
                    } else {
                        state.sortBy = nextSort;
                        state.sortDir = nextSort === 'last_synced_at' ? 'desc' : 'asc';
                    }
                    loadPage(1);
                });
            });

            firstBtn?.addEventListener('click', function () { loadPage(1); });
            prevBtn?.addEventListener('click', function () { loadPage(state.page - 1); });
            nextBtn?.addEventListener('click', function () { loadPage(state.page + 1); });
            lastBtn?.addEventListener('click', function () { loadPage(state.lastPage); });

            syncBtn?.addEventListener('click', function () {
                runSync();
            });

            addTagsBtn?.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const ids = selectedCustomerIdList();
                if (ids.length) openTagsForIds(ids);
                else setTagDrawerOpen(true);
            });
            drawerClose?.addEventListener('click', function () { setTagDrawerOpen(false); });
            drawerCancel?.addEventListener('click', function () { setTagDrawerOpen(false); });
            drawerBackdrop?.addEventListener('click', function () { setTagDrawerOpen(false); });
            drawerSearch?.addEventListener('input', renderDrawerTagList);
            drawerNewBtn?.addEventListener('click', addDrawerNewTag);
            drawerNew?.addEventListener('keydown', function (ev) {
                if (ev.key === 'Enter') {
                    ev.preventDefault();
                    addDrawerNewTag();
                }
            });
            drawerApply?.addEventListener('click', applyDrawerTags);
            selectAll?.addEventListener('change', function () {
                const boxes = tbody ? tbody.querySelectorAll('.crm-shopify-row-check') : [];
                boxes.forEach(function (box) {
                    box.checked = !!selectAll.checked;
                    if (selectAll.checked) selectedCustomerIds.add(box.value);
                    else selectedCustomerIds.delete(box.value);
                });
                updateSelectAllState();
                updateDrawerSelectionCopy();
            });
            document.addEventListener('keydown', function (ev) {
                if (ev.key === 'Escape' && drawerEl && !drawerEl.classList.contains('d-none')) {
                    if (drawerMergeFrom) {
                        drawerMergeFrom = '';
                        renderDrawerTagList();
                        return;
                    }
                    setTagDrawerOpen(false);
                }
            });

            tableRegion?.addEventListener('scroll', hideDotTipNow);
            window.addEventListener('scroll', hideDotTipNow, true);

            applyFilterVisibility({ clearHidden: true });
            updateSortHeaders();
            renderTagOptions();
            updateTagToggle();
            loadPage(1);
        })();
    </script>
@endsection
