@extends('layouts.vertical', ['title' => 'Shopify', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Shopify',
        'sub_title' => 'Marketplace Customers',
    ])

    @include('crm.shopify._nav', ['active' => 'others'])

    <style>
        .mkt-stat-strip { background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:.5rem 1rem; margin-bottom:.75rem; display:flex; flex-wrap:wrap; gap:0; }
        .mkt-stat-item { flex:1 1 auto; min-width:120px; padding:.35rem .75rem; border-right:1px solid #e2e8f0; }
        .mkt-stat-item:last-child { border-right:none; }
        .mkt-stat-label { font-size:.65rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#94a3b8; margin-bottom:.1rem; }
        .mkt-stat-value { font-size:1.05rem; font-weight:800; color:#0f172a; line-height:1.1; }
        .mkt-stat-sub { font-size:.68rem; color:#64748b; }
        .mkt-filter-bar { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:.4rem .75rem; margin-bottom:.75rem; display:flex; align-items:center; gap:.4rem; flex-wrap:nowrap; }
        .mkt-filter-bar .form-control-sm, .mkt-filter-bar .form-select-sm { height:30px; font-size:.8rem; padding:.2rem .5rem; border-color:#e2e8f0; border-radius:6px; background-color:#f8fafc; }
        .mkt-filter-bar .form-select-sm { padding-right:1.6rem; }
        .mkt-filter-bar .mkt-sep { width:1px; height:18px; background:#e2e8f0; flex-shrink:0; }
        .mkt-filter-bar .mkt-search { flex:1 1 180px; min-width:140px; max-width:280px; }
        .mkt-filter-bar .mkt-channel { width:140px; }
        .mkt-filter-bar .mkt-tag { width:140px; }
        .mkt-filter-bar .mkt-source { width:130px; }
        .mkt-filter-bar .mkt-perpage { width:70px; }
        @media(max-width:767px){ .mkt-stat-item{ border-right:none; border-bottom:1px solid #e2e8f0; } .mkt-stat-item:last-child{border-bottom:none;} .mkt-filter-bar{flex-wrap:wrap;} }
    </style>

    {{-- Stat strip --}}
    <div class="mkt-stat-strip" id="crm-others-summary">
        <div class="mkt-stat-item">
            <div class="mkt-stat-label">Filtered</div>
            <div class="mkt-stat-value" id="mkt-stat-total">—</div>
            <div class="mkt-stat-sub">Marketplace customers</div>
        </div>
        <div class="mkt-stat-item">
            <div class="mkt-stat-label">Total Orders</div>
            <div class="mkt-stat-value" id="mkt-stat-orders">—</div>
            <div class="mkt-stat-sub"><span id="mkt-stat-customers-ordered">—</span> customers ordered</div>
        </div>
        <div class="mkt-stat-item">
            <div class="mkt-stat-label">Order Revenue</div>
            <div class="mkt-stat-value" id="mkt-stat-revenue">—</div>
            <div class="mkt-stat-sub">Linked Shopify orders</div>
        </div>
        <div class="mkt-stat-item">
            <div class="mkt-stat-label">Avg Order Value</div>
            <div class="mkt-stat-value" id="mkt-stat-aov">—</div>
            <div class="mkt-stat-sub">Per order</div>
        </div>
        <div class="mkt-stat-item">
            <div class="mkt-stat-label">Linked to CRM</div>
            <div class="mkt-stat-value" id="mkt-stat-linked">—</div>
            <div class="mkt-stat-sub"><span id="mkt-stat-no-email">—</span> missing email</div>
        </div>
    </div>

    {{-- Single-line filter bar --}}
    <div class="mkt-filter-bar mb-3">
        <div class="mkt-search">
            <input type="search" id="crm-others-search" class="form-control form-control-sm"
                   placeholder="&#128269; Search name, email, phone…" autocomplete="off">
        </div>
        <div class="mkt-sep"></div>
        <div class="mkt-channel">
            <select id="crm-others-channel" class="form-select form-select-sm" title="Channel">
                <option value="">All channels</option>
                @foreach (($marketplaceChannels ?? []) as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="mkt-tag">
            <select id="crm-others-tag" class="form-select form-select-sm" title="Tags">
                <option value="">All tags</option>
                @foreach (($tagFilters ?? []) as $tag)
                    <option value="{{ $tag }}">{{ $tag }}</option>
                @endforeach
            </select>
        </div>
        <div class="mkt-source">
            <select id="crm-others-source" class="form-select form-select-sm" title="Source">
                <option value="">All sources</option>
                <option value="email_domain">Email domain</option>
                <option value="order_source">Order source</option>
                <option value="tag">Tag</option>
                <option value="manual">Manual</option>
                <option value="fallback">Fallback</option>
            </select>
        </div>
        <div class="mkt-perpage">
            <select id="crm-others-per-page" class="form-select form-select-sm" title="Per page">
                @foreach ([10, 25, 50, 100] as $n)
                    <option value="{{ $n }}" @selected($n === 25)>{{ $n }}</option>
                @endforeach
            </select>
        </div>
        {{-- hidden apply trigger kept for JS compatibility --}}
        <button type="button" id="crm-others-apply-filters" class="d-none">Apply</button>
    </div>

    <div class="card position-relative" id="crm-others-list-card">
        <div id="crm-others-loading-overlay"
             class="position-absolute top-0 start-0 w-100 h-100 d-none align-items-center justify-content-center rounded"
             style="background: rgba(255,255,255,0.72); z-index: 2;"
             role="status"
             aria-live="polite"
             aria-busy="true">
            <div class="text-center px-3">
                <div class="spinner-border text-primary mb-2" role="status"></div>
                <div class="small text-muted" id="crm-others-loading-message">Loading customers…</div>
            </div>
        </div>
        <div class="card-body">
            <div id="crm-others-list-alert" class="alert d-none" role="alert"></div>
            <div class="table-responsive" id="crm-others-table-region" aria-busy="false">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Shopify ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Channel</th>
                            <th>Source</th>
                            <th>Tags</th>
                            <th>CRM customer</th>
                            <th>Sync</th>
                            <th>Last synced</th>
                            <th class="text-end">Follow-up</th>
                        </tr>
                    </thead>
                    <tbody id="crm-others-customers-tbody">
                        <tr>
                            <td colspan="11" class="text-muted text-center py-4">Loading…</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div id="crm-others-pagination-wrap" class="mt-3 d-none">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" id="crm-others-first" class="btn btn-outline-secondary" title="First page" disabled>« First</button>
                        <button type="button" id="crm-others-prev" class="btn btn-outline-secondary" title="Previous page" disabled>‹ Prev</button>
                    </div>
                    <ul id="crm-others-page-numbers" class="pagination pagination-sm mb-0 flex-wrap justify-content-center"></ul>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" id="crm-others-next" class="btn btn-outline-secondary" title="Next page" disabled>Next ›</button>
                        <button type="button" id="crm-others-last" class="btn btn-outline-secondary" title="Last page" disabled>Last »</button>
                    </div>
                </div>
                <div id="crm-others-page-summary" class="small text-muted text-center"></div>
            </div>
        </div>
    </div>

    @php($crmAssignees = $crmAssignees ?? collect())

    <div class="modal fade" id="crm-others-followup-modal" tabindex="-1" aria-labelledby="crm-others-followup-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form id="crm-others-followup-form">
                    <div class="modal-header">
                        <h5 class="modal-title" id="crm-others-followup-modal-label">Create follow-up</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="crm-others-followup-modal-alert" class="alert alert-danger d-none small py-2 mb-3" role="alert"></div>
                        <p class="small text-muted mb-3">CRM customer is matched or created from this Shopify row when you save.</p>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small mb-0" for="crm-others-fu-name">Name</label>
                                <input type="text" class="form-control form-control-sm bg-light" id="crm-others-fu-name" readonly tabindex="-1">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-0" for="crm-others-fu-email">Email</label>
                                <input type="text" class="form-control form-control-sm bg-light" id="crm-others-fu-email" readonly tabindex="-1">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-0" for="crm-others-fu-crm-id">CRM customer ID</label>
                                <input type="text" class="form-control form-control-sm bg-light" id="crm-others-fu-crm-id" readonly tabindex="-1">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-0" for="crm-others-fu-shopify-label">Shopify customer (API id)</label>
                                <input type="text" class="form-control form-control-sm bg-light font-monospace" id="crm-others-fu-shopify-label" readonly tabindex="-1">
                            </div>
                        </div>
                        <input type="hidden" id="crm-others-fu-shopify-record-id" value="">
                        <div class="row g-2">
                            <div class="col-12">
                                <label class="form-label small mb-0" for="crm-others-fu-title">Title</label>
                                <input type="text" class="form-control form-control-sm" id="crm-others-fu-title" required maxlength="255" value="Shopify customer follow-up">
                            </div>
                            <div class="col-12">
                                <label class="form-label small mb-0" for="crm-others-fu-description">Description</label>
                                <textarea class="form-control form-control-sm" id="crm-others-fu-description" rows="3"></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-0" for="crm-others-fu-type">Type</label>
                                <select class="form-select form-select-sm" id="crm-others-fu-type" required>
                                    @foreach (['call', 'email', 'whatsapp', 'meeting', 'sms', 'other'] as $t)
                                        <option value="{{ $t }}" @selected($t === 'call')>{{ $t }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-0" for="crm-others-fu-priority">Priority</label>
                                <select class="form-select form-select-sm" id="crm-others-fu-priority" required>
                                    @foreach (['low', 'medium', 'high'] as $p)
                                        <option value="{{ $p }}" @selected($p === 'medium')>{{ $p }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-0" for="crm-others-fu-assignee">Assignee</label>
                                <select class="form-select form-select-sm" id="crm-others-fu-assignee" required>
                                    @forelse ($crmAssignees as $u)
                                        <option value="{{ $u->id }}" @selected((int) $u->id === (int) auth()->id())>{{ $u->name }}</option>
                                    @empty
                                        <option value="{{ auth()->id() }}">{{ auth()->user()->name ?? 'Me' }}</option>
                                    @endforelse
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-0" for="crm-others-fu-scheduled">Scheduled at</label>
                                <input type="datetime-local" class="form-control form-control-sm" id="crm-others-fu-scheduled">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm" id="crm-others-fu-submit">
                            <span class="fu-submit-label">Save follow-up</span>
                            <span class="fu-submit-spinner spinner-border spinner-border-sm d-none ms-1" role="status" aria-hidden="true"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const dataUrl = @json(route('crm.shopify.others.data'));
            const crmCustomerBase = @json(url('/crm/customers'));
            const shopifyCustomersBase = @json(url('/crm/shopify/customers'));

            const overlay = document.getElementById('crm-others-loading-overlay');
            const loadingMessage = document.getElementById('crm-others-loading-message');
            const tbody = document.getElementById('crm-others-customers-tbody');
            const tableRegion = document.getElementById('crm-others-table-region');
            const alertEl = document.getElementById('crm-others-list-alert');
            const searchInput = document.getElementById('crm-others-search');
            const channelSelect = document.getElementById('crm-others-channel');
            const tagSelect = document.getElementById('crm-others-tag');
            const sourceSelect = document.getElementById('crm-others-source');
            const perPageSelect = document.getElementById('crm-others-per-page');
            const applyBtn = document.getElementById('crm-others-apply-filters');
            const paginationWrap = document.getElementById('crm-others-pagination-wrap');
            const prevBtn = document.getElementById('crm-others-prev');
            const nextBtn = document.getElementById('crm-others-next');
            const firstBtn = document.getElementById('crm-others-first');
            const lastBtn = document.getElementById('crm-others-last');
            const pageNumbersEl = document.getElementById('crm-others-page-numbers');
            const pageSummary = document.getElementById('crm-others-page-summary');

            const fmtMoney = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 2 });
            const fmtNum   = new Intl.NumberFormat('en-US');

            function updateStats(meta) {
                meta = meta || {};
                const fs = meta.filtered_stats || {};
                const setEl = function (id, val, money) {
                    const el = document.getElementById(id);
                    if (!el) return;
                    if (val == null) { el.textContent = '—'; return; }
                    el.textContent = money ? fmtMoney.format(val) : fmtNum.format(val);
                };
                setEl('mkt-stat-total',             meta.total);
                setEl('mkt-stat-orders',            fs.total_orders);
                setEl('mkt-stat-customers-ordered', fs.customers_with_orders);
                setEl('mkt-stat-revenue',           fs.total_order_value,  true);
                setEl('mkt-stat-aov',               fs.avg_order_value,    true);
                setEl('mkt-stat-linked',            fs.linked_to_crm);
                setEl('mkt-stat-no-email',          fs.missing_email);
            }

            let state = {
                page: 1,
                perPage: parseInt(perPageSelect.value, 10) || 25,
                q: '',
                marketplaceChannel: '',
                tag: '',
                classificationSource: '',
                lastPage: 1,
                total: 0,
            };

            let loadSeq = 0;
            let listAbort = null;
            let successHideTimer = null;

            function setTableBusy(busy) {
                if (tableRegion) tableRegion.setAttribute('aria-busy', busy ? 'true' : 'false');
            }

            function setListLoading(on, message) {
                if (overlay) {
                    overlay.classList.toggle('d-none', !on);
                    overlay.classList.toggle('d-flex', on);
                }
                if (loadingMessage && message) loadingMessage.textContent = message;
                setTableBusy(on);
                if (applyBtn) applyBtn.disabled = on;
                if (perPageSelect) perPageSelect.disabled = on;
                if (searchInput) searchInput.disabled = on;
                if (channelSelect) channelSelect.disabled = on;
                if (tagSelect) tagSelect.disabled = on;
                if (sourceSelect) sourceSelect.disabled = on;
                if (on) {
                    [firstBtn, prevBtn, nextBtn, lastBtn].forEach(function (b) { if (b) b.disabled = true; });
                    if (pageNumbersEl) pageNumbersEl.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
                }
            }

            function clearSuccessTimer() {
                if (successHideTimer) { clearTimeout(successHideTimer); successHideTimer = null; }
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
                    alertEl.innerHTML = '<span class="alert-message"></span><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                    alertEl.querySelector('.alert-message').textContent = message;
                } else {
                    alertEl.textContent = message;
                }
                if (type === 'success' && options.autoHideMs) {
                    successHideTimer = setTimeout(function () { hideAlert(); }, options.autoHideMs);
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
                if (typeof json.message === 'string' && json.message.trim() !== '') return json.message;
                if (json.errors && typeof json.errors === 'object') {
                    const parts = [];
                    Object.keys(json.errors).forEach(function (k) {
                        const v = json.errors[k];
                        if (Array.isArray(v)) v.forEach(function (x) { parts.push(String(x)); });
                        else if (v != null) parts.push(String(v));
                    });
                    if (parts.length) return parts.join(' ');
                }
                return humanHttpStatus(res.status) || ('Request failed (HTTP ' + res.status + ').');
            }

            function formatSynced(iso) {
                if (!iso) return '—';
                try {
                    const d = new Date(iso);
                    if (Number.isNaN(d.getTime())) return iso;
                    const pad = function (n) { return String(n).padStart(2, '0'); };
                    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
                } catch (e) { return iso; }
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

            function renderRows(rows) {
                if (!tbody) return;
                tbody.innerHTML = '';
                if (!rows.length) {
                    const tr = document.createElement('tr');
                    const td = document.createElement('td');
                    td.colSpan = 11;
                    td.className = 'text-muted text-center py-4';
                    td.textContent = 'No marketplace customers found.';
                    tr.appendChild(td);
                    tbody.appendChild(tr);
                    return;
                }
                rows.forEach(function (r) {
                    const tr = document.createElement('tr');

                    const tdId = document.createElement('td');
                    tdId.className = 'font-monospace small';
                    tdId.textContent = r.shopify_customer_id != null ? String(r.shopify_customer_id) : '';

                    const tdName = tdText(r.name || '');
                    const tdEmail = tdText(r.email);
                    const tdPhone = tdText(r.phone);
                    const tdChannel = badgeTd(r.marketplace_channel_label || r.channel, 'badge bg-info-subtle text-info border', r.classification_reason || '');
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
                    tdCrm.className = 'small';
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
                    fuBtn.addEventListener('click', function () { openFollowUpModal(r); });
                    tdFu.appendChild(fuBtn);

                    tr.appendChild(tdId);
                    tr.appendChild(tdName);
                    tr.appendChild(tdEmail);
                    tr.appendChild(tdPhone);
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
                        btn.addEventListener('click', function () { loadPage(num); });
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
                if (listAbort) { try { listAbort.abort(); } catch (e) {} }
                listAbort = new AbortController();
                state.page = Math.max(1, page);
                const params = new URLSearchParams({ page: String(state.page), per_page: String(state.perPage) });
                if (state.q) params.set('q', state.q);
                if (state.marketplaceChannel) params.set('marketplace_channel', state.marketplaceChannel);
                if (state.tag) params.set('tag', state.tag);
                if (state.classificationSource) params.set('classification_source', state.classificationSource);
                setListLoading(true, opts.loadingMessage || 'Loading customers…');
                try {
                    const res = await fetch(dataUrl + '?' + params.toString(), {
                        method: 'GET',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
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
                    updateStats(json.meta || {});
                } catch (e) {
                    if (e.name === 'AbortError') return;
                    const msg = e && e.message ? e.message : 'Failed to load customers.';
                    showAlert('error', msg, { dismissible: true });
                    if (tbody) {
                        tbody.innerHTML = '';
                        const tr = document.createElement('tr');
                        const td = document.createElement('td');
                        td.colSpan = 11;
                        td.className = 'text-center py-4';
                        const wrap = document.createElement('div');
                        wrap.className = 'text-danger small mb-2';
                        wrap.textContent = msg;
                        const retry = document.createElement('button');
                        retry.type = 'button';
                        retry.className = 'btn btn-sm btn-outline-primary';
                        retry.textContent = 'Retry';
                        retry.addEventListener('click', function () { loadPage(state.page); });
                        td.appendChild(wrap);
                        td.appendChild(retry);
                        tr.appendChild(td);
                        tbody.appendChild(tr);
                    }
                    if (paginationWrap) paginationWrap.classList.add('d-none');
                } finally {
                    if (seq === loadSeq) setListLoading(false);
                }
            }

            const followUpModalEl = document.getElementById('crm-others-followup-modal');
            const followUpForm = document.getElementById('crm-others-followup-form');
            const followUpModalAlert = document.getElementById('crm-others-followup-modal-alert');
            const fuSubmitBtn = document.getElementById('crm-others-fu-submit');
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
                const idEl = document.getElementById('crm-others-fu-shopify-record-id');
                const nameEl = document.getElementById('crm-others-fu-name');
                const emailEl = document.getElementById('crm-others-fu-email');
                const crmEl = document.getElementById('crm-others-fu-crm-id');
                const shopifyEl = document.getElementById('crm-others-fu-shopify-label');
                if (idEl) idEl.value = String(r.id);
                if (nameEl) nameEl.value = r.name || '';
                if (emailEl) emailEl.value = r.email || '';
                if (crmEl) crmEl.value = r.customer_id != null ? String(r.customer_id) : '— (linked on save if possible)';
                if (shopifyEl) shopifyEl.value = r.shopify_customer_id != null ? String(r.shopify_customer_id) : '';
                if (followUpModalEl && window.bootstrap && window.bootstrap.Modal) {
                    window.bootstrap.Modal.getOrCreateInstance(followUpModalEl).show();
                }
            }

            followUpForm?.addEventListener('submit', async function (ev) {
                ev.preventDefault();
                hideFollowUpModalAlert();
                const recordIdEl = document.getElementById('crm-others-fu-shopify-record-id');
                const recordId = recordIdEl ? recordIdEl.value : '';
                if (!recordId) { showFollowUpModalAlert('Missing Shopify row.'); return; }
                const scheduledEl = document.getElementById('crm-others-fu-scheduled');
                const payload = {
                    title: document.getElementById('crm-others-fu-title')?.value,
                    description: (document.getElementById('crm-others-fu-description')?.value || '') || null,
                    follow_up_type: document.getElementById('crm-others-fu-type')?.value,
                    priority: document.getElementById('crm-others-fu-priority')?.value,
                    assigned_user_id: parseInt(document.getElementById('crm-others-fu-assignee')?.value || '0', 10),
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
                    } catch (parseErr) { throw new Error('Invalid response from server.'); }
                    if (!res.ok) throw new Error(messageFromJson(json, res) || 'Could not create follow-up.');
                    if (followUpModalEl && window.bootstrap && window.bootstrap.Modal) {
                        window.bootstrap.Modal.getOrCreateInstance(followUpModalEl).hide();
                    }
                    const showUrl = typeof json.show_url === 'string' ? json.show_url : '';
                    let msg = (typeof json.message === 'string' && json.message) ? json.message : 'Follow-up created.';
                    if (showUrl) msg += ' Opening detail in a new tab.';
                    showAlert('success', msg, { autoHideMs: 6000, dismissible: false });
                    if (showUrl) window.open(showUrl, '_blank', 'noopener');
                    await loadPage(state.page, { loadingMessage: 'Refreshing list…' });
                } catch (e) {
                    showFollowUpModalAlert(e && e.message ? e.message : 'Request failed.');
                } finally {
                    if (fuSubmitBtn) fuSubmitBtn.disabled = false;
                    if (fuSubmitSpinner) fuSubmitSpinner.classList.add('d-none');
                }
            });

            applyBtn?.addEventListener('click', function () {
                state.q = (searchInput?.value || '').trim();
                state.marketplaceChannel = (channelSelect?.value || '').trim();
                state.tag = (tagSelect?.value || '').trim();
                state.classificationSource = (sourceSelect?.value || '').trim();
                state.perPage = parseInt(perPageSelect?.value || '25', 10) || 25;
                loadPage(1);
            });

            channelSelect?.addEventListener('change', function () { applyBtn?.click(); });
            tagSelect?.addEventListener('change', function () { applyBtn?.click(); });
            sourceSelect?.addEventListener('change', function () { applyBtn?.click(); });
            perPageSelect?.addEventListener('change', function () { applyBtn?.click(); });

            searchInput?.addEventListener('keydown', function (ev) {
                if (ev.key === 'Enter') { ev.preventDefault(); applyBtn?.click(); }
            });

            firstBtn?.addEventListener('click', function () { loadPage(1); });
            prevBtn?.addEventListener('click', function () { loadPage(state.page - 1); });
            nextBtn?.addEventListener('click', function () { loadPage(state.page + 1); });
            lastBtn?.addEventListener('click', function () { loadPage(state.lastPage); });

            loadPage(1);
        })();
    </script>
@endsection
