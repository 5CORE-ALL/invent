@extends('layouts.vertical', ['title' => 'Shopify', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Shopify',
        'sub_title' => 'B2B Customers',
    ])

    @include('crm.shopify._nav', ['active' => 'customers'])

    <style>
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
        .b2b-filter-bar [data-filter-control="search"] { flex:1 1 180px; min-width:140px; max-width:260px; }
        .b2b-filter-bar [data-filter-control="customerType"] { width:120px; }
        .b2b-filter-bar [data-filter-control="tag"] { width:140px; position:relative; }
        .b2b-filter-bar [data-filter-control="classificationSource"] { width:120px; }
        .b2b-filter-bar [data-filter-control="marketplaceChannel"] { width:130px; }
        .b2b-filter-bar [data-filter-control="syncStatus"] { width:110px; }
        .b2b-filter-bar [data-filter-control="perPage"] { width:70px; }
        .b2b-filter-tag-wrap { position:relative; display:flex; align-items:center; width:100%; }
        .b2b-filter-tag-wrap select { width:100%; }
        .b2b-filter-tag-wrap .b2b-tag-spin { position:absolute; right:1.6rem; pointer-events:none; }
        .b2b-btn-customize { flex-shrink:0; color:#94a3b8; border:1px solid #e2e8f0; background:#f8fafc; border-radius:6px; padding:.2rem .55rem; font-size:.8rem; line-height:1.6; cursor:pointer; display:flex; align-items:center; gap:.25rem; white-space:nowrap; }
        .b2b-btn-customize:hover { background:#f1f5f9; color:#475569; }
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
            <select id="crm-shopify-customer-type" class="form-select form-select-sm" title="Customer type">
                <option value="">All B2B</option>
                <option value="wholesale">Wholesale</option>
                <option value="dropshipper">Dropshipper</option>
            </select>
        </div>

        <div data-filter-control="tag">
            <div class="b2b-filter-tag-wrap">
                <select id="crm-shopify-tag" class="form-select form-select-sm" title="Segment">
                    <option value="">All segments</option>
                    @foreach (($tagFilters ?? []) as $tag)
                        <option value="{{ $tag }}">{{ $tag }}</option>
                    @endforeach
                </select>
                <span id="crm-shopify-tag-loading" class="b2b-tag-spin spinner-border spinner-border-sm d-none text-secondary" role="status" style="width:.65rem;height:.65rem;"></span>
            </div>
        </div>

        <div data-filter-control="classificationSource">
            <select id="crm-shopify-source" class="form-select form-select-sm" title="Source">
                <option value="">All sources</option>
                <option value="tag">Tag</option>
                <option value="email_domain">Email domain</option>
                <option value="order_source">Order source</option>
                <option value="manual">Manual</option>
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
                    <label class="form-check-label small" for="crm-shopify-show-filter-type">Customer type</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="crm-shopify-show-filter-tag" data-filter-visibility="tag">
                    <label class="form-check-label small" for="crm-shopify-show-filter-tag">Segment</label>
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

        {{-- Action buttons inline in filter bar --}}
        <button type="button" id="crm-shopify-sync-btn" class="btn btn-primary btn-sm flex-shrink-0" style="height:30px;font-size:.8rem;padding:.2rem .65rem;white-space:nowrap;">
            <span class="sync-label">Sync</span>
            <span class="sync-spinner spinner-border spinner-border-sm d-none ms-1" role="status" aria-hidden="true"></span>
        </button>
        <button type="button" id="crm-shopify-create-btn" class="btn btn-success btn-sm flex-shrink-0" style="height:30px;font-size:.8rem;padding:.2rem .65rem;white-space:nowrap;">+ Create</button>
        <button type="button" id="crm-shopify-import-btn" class="btn btn-outline-secondary btn-sm flex-shrink-0" style="height:30px;font-size:.8rem;padding:.2rem .65rem;white-space:nowrap;">Import</button>
        <span id="crm-shopify-sync-status" class="small text-muted flex-shrink-0" aria-live="polite" style="font-size:.72rem;"></span>
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
                            <th>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-reset crm-shopify-sort" data-sort-by="province">Province</button>
                            </th>
                            <th>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-reset crm-shopify-sort" data-sort-by="zip">Zip</button>
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
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-reset crm-shopify-sort" data-sort-by="tags">Tags</button>
                            </th>
                            <th class="d-none">CRM customer</th>
                            <th>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-reset crm-shopify-sort" data-sort-by="sync_status">Sync</button>
                            </th>
                            <th>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-reset crm-shopify-sort" data-sort-by="last_synced_at">Last synced</button>
                            </th>
                            <th class="text-end">Follow-up</th>
                        </tr>
                    </thead>
                    <tbody id="crm-shopify-customers-tbody">
                        <tr>
                            <td colspan="14" class="text-muted text-center py-4">Loading…</td>
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
                        <p class="small text-muted mb-3">This creates the customer in Shopify first, then stores Shopify's returned data locally.</p>
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
                                <label class="form-label small mb-0" for="crm-shopify-create-province">Province</label>
                                <input type="text" class="form-control form-control-sm" id="crm-shopify-create-province" maxlength="128">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-0" for="crm-shopify-create-zip">Zip</label>
                                <input type="text" class="form-control form-control-sm" id="crm-shopify-create-zip" maxlength="32">
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

    <script>
        (function () {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const dataUrl = @json(route('crm.shopify.customers.data'));
            const syncUrl = @json(route('crm.shopify.sync-customers'));
            const storeUrl = @json(route('crm.shopify.customers.store'));
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
            const tagSelect = document.getElementById('crm-shopify-tag');
            const typeSelect = document.getElementById('crm-shopify-customer-type');
            const sourceSelect = document.getElementById('crm-shopify-source');
            const marketplaceChannelSelect = document.getElementById('crm-shopify-marketplace-channel');
            const syncStatusSelect = document.getElementById('crm-shopify-sync-status-filter');
            const perPageSelect = document.getElementById('crm-shopify-per-page');
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
                tag: '',
                customerType: '',
                classificationSource: '',
                marketplaceChannel: '',
                syncStatus: '',
                sortBy: 'last_synced_at',
                sortDir: 'desc',
                lastPage: 1,
                total: 0,
            };
            let visibleFilters = loadVisibleFilters();

            let loadSeq = 0;
            let listAbort = null;
            let successHideTimer = null;
            let filterDebounceTimer = null;

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
                if (key === 'tag') return (tagSelect?.value || '').trim();
                if (key === 'customerType') return (typeSelect?.value || '').trim();
                if (key === 'classificationSource') return (sourceSelect?.value || '').trim();
                if (key === 'marketplaceChannel') return (marketplaceChannelSelect?.value || '').trim();
                if (key === 'syncStatus') return (syncStatusSelect?.value || '').trim();
                return '';
            }

            function clearFilterValueForKey(key) {
                if (key === 'search' && searchInput) searchInput.value = '';
                if (key === 'tag' && tagSelect) tagSelect.value = '';
                if (key === 'customerType' && typeSelect) typeSelect.value = '';
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
                if (tagSelect) tagSelect.disabled = on;
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
                    const pad = function (n) { return String(n).padStart(2, '0'); };
                    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + ' '
                        + pad(d.getHours()) + ':' + pad(d.getMinutes());
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

            function humanLabel(value) {
                if (!value) return '—';
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
                tbody.innerHTML = '';
                if (!rows.length) {
                    const tr = document.createElement('tr');
                    const td = document.createElement('td');
                    td.colSpan = 14;
                    td.className = 'text-muted text-center py-4';
                    td.textContent = 'No customers found. Try syncing from Shopify or adjust search.';
                    tr.appendChild(td);
                    tbody.appendChild(tr);
                    return;
                }
                rows.forEach(function (r) {
                    const tr = document.createElement('tr');

                    const tdId = document.createElement('td');
                    tdId.className = 'font-monospace small d-none';
                    tdId.textContent = r.shopify_customer_id != null ? String(r.shopify_customer_id) : '';

                    const tdName = tdText(r.name || '');

                    const tdEmail = tdText(r.email);
                    const tdPhone = tdText(r.phone);
                    const tdProvince = tdText(r.province);
                    const tdZip = tdText(r.zip);

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
                    const tags = Array.isArray(r.tags) ? r.tags : [];
                    if (tags.length) {
                        tags.forEach(function (tag) {
                            const span = document.createElement('span');
                            span.className = 'badge bg-secondary-subtle text-secondary border me-1 mb-1';
                            span.style.fontSize = '0.7rem';
                            span.textContent = tag;
                            tdTags.appendChild(span);
                        });
                    } else {
                        tdTags.className = 'small';
                        tdTags.textContent = '—';
                    }

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

                    const tdSync = document.createElement('td');
                    const badge = document.createElement('span');
                    badge.className = 'badge bg-light text-dark border';
                    badge.textContent = r.sync_status || '—';
                    tdSync.appendChild(badge);

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

                    tr.appendChild(tdId);
                    tr.appendChild(tdName);
                    tr.appendChild(tdEmail);
                    tr.appendChild(tdPhone);
                    tr.appendChild(tdProvince);
                    tr.appendChild(tdZip);
                    tr.appendChild(tdType);
                    tr.appendChild(tdChannel);
                    tr.appendChild(tdSource);
                    tr.appendChild(tdTags);
                    tr.appendChild(tdCrm);
                    tr.appendChild(tdSync);
                    tr.appendChild(tdLast);
                    tr.appendChild(tdFu);
                    tbody.appendChild(tr);
                });
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
                if (state.tag) {
                    params.set('tag', state.tag);
                }
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
                    updatePagination(json.meta || {});
                    updateSortHeaders(json.meta || {});
                    updateSummary((json.meta || {}).summary || {}, json.meta || {});
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
                        td.colSpan = 14;
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

            function applyFiltersNow() {
                if (filterDebounceTimer) {
                    clearTimeout(filterDebounceTimer);
                    filterDebounceTimer = null;
                }
                state.q = (searchInput?.value || '').trim();
                state.tag = (tagSelect?.value || '').trim();
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
                if (!tagSelect) return;

                if (tagLoadingSpinner) tagLoadingSpinner.classList.remove('d-none');
                tagSelect.disabled = true;

                try {
                    const url = tagsUrl + (customerType ? '?customer_type=' + encodeURIComponent(customerType) : '');
                    const res = await fetch(url, { headers: { Accept: 'application/json' } });
                    if (!res.ok) return;
                    const tags = await res.json();

                    const currentVal = tagSelect.value;
                    tagSelect.innerHTML = '<option value="">All segments</option>';
                    tags.forEach(function (tag) {
                        const opt = document.createElement('option');
                        opt.value = tag;
                        opt.textContent = tag;
                        if (tag === currentVal) opt.selected = true;
                        tagSelect.appendChild(opt);
                    });

                    // If the previously selected tag no longer exists for this type, clear it
                    if (currentVal && !tags.includes(currentVal)) {
                        tagSelect.value = '';
                    }
                } catch (_) {
                    // silently ignore fetch errors — keep current options
                } finally {
                    tagSelect.disabled = false;
                    if (tagLoadingSpinner) tagLoadingSpinner.classList.add('d-none');
                }
            }

            searchInput?.addEventListener('input', applyFiltersDebounced);
            tagSelect?.addEventListener('change', applyFiltersNow);
            typeSelect?.addEventListener('change', async function () {
                await refreshTagsForType(typeSelect.value);
                applyFiltersNow();
            });
            sourceSelect?.addEventListener('change', applyFiltersNow);
            marketplaceChannelSelect?.addEventListener('change', applyFiltersNow);
            syncStatusSelect?.addEventListener('change', applyFiltersNow);
            perPageSelect?.addEventListener('change', applyFiltersNow);

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

            applyFilterVisibility({ clearHidden: true });
            updateSortHeaders();
            loadPage(1);
        })();
    </script>
@endsection
