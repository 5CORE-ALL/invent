@extends('layouts.vertical', ['title' => 'Shopify', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Shopify',
        'sub_title' => 'Customers · Admin API',
    ])

    @include('crm.shopify._nav', ['active' => 'customers'])

    <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
        <a href="{{ route('crm.dashboard') }}" class="btn btn-outline-secondary btn-sm">Dashboard</a>
        <button type="button" id="crm-shopify-sync-btn" class="btn btn-primary btn-sm">
            <span class="sync-label">Sync from Shopify</span>
            <span class="sync-spinner spinner-border spinner-border-sm d-none ms-1" role="status" aria-hidden="true"></span>
        </button>
        <span id="crm-shopify-sync-status" class="small text-muted" aria-live="polite"></span>
    </div>

    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small mb-0" for="crm-shopify-search">Search</label>
                    <input type="search" id="crm-shopify-search" class="form-control form-control-sm"
                           placeholder="Email, phone, name, Shopify ID" autocomplete="off">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0" for="crm-shopify-per-page">Per page</label>
                    <select id="crm-shopify-per-page" class="form-select form-select-sm">
                        @foreach ([10, 25, 50, 100] as $n)
                            <option value="{{ $n }}" @selected($n === 25)>{{ $n }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="button" id="crm-shopify-apply-filters" class="btn btn-sm btn-outline-primary w-100">Apply</button>
                </div>
            </div>
        </div>
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
                            <th>Shopify ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>CRM customer</th>
                            <th>Sync</th>
                            <th>Last synced</th>
                            <th class="text-end">Follow-up</th>
                        </tr>
                    </thead>
                    <tbody id="crm-shopify-customers-tbody">
                        <tr>
                            <td colspan="8" class="text-muted text-center py-4">Loading…</td>
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

    <script>
        (function () {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const dataUrl = @json(route('crm.shopify.customers.data'));
            const syncUrl = @json(route('crm.shopify.sync-customers'));
            const crmCustomerBase = @json(url('/crm/customers'));
            const shopifyCustomersBase = @json(url('/crm/shopify/customers'));

            const listCard = document.getElementById('crm-shopify-list-card');
            const overlay = document.getElementById('crm-shopify-loading-overlay');
            const loadingMessage = document.getElementById('crm-shopify-loading-message');
            const tbody = document.getElementById('crm-shopify-customers-tbody');
            const tableRegion = document.getElementById('crm-shopify-table-region');
            const alertEl = document.getElementById('crm-shopify-list-alert');
            const syncBtn = document.getElementById('crm-shopify-sync-btn');
            const syncStatus = document.getElementById('crm-shopify-sync-status');
            const syncSpinner = syncBtn?.querySelector('.sync-spinner');
            const searchInput = document.getElementById('crm-shopify-search');
            const perPageSelect = document.getElementById('crm-shopify-per-page');
            const applyBtn = document.getElementById('crm-shopify-apply-filters');
            const paginationWrap = document.getElementById('crm-shopify-pagination-wrap');
            const prevBtn = document.getElementById('crm-shopify-prev');
            const nextBtn = document.getElementById('crm-shopify-next');
            const firstBtn = document.getElementById('crm-shopify-first');
            const lastBtn = document.getElementById('crm-shopify-last');
            const pageNumbersEl = document.getElementById('crm-shopify-page-numbers');
            const pageSummary = document.getElementById('crm-shopify-page-summary');

            let state = {
                page: 1,
                perPage: parseInt(perPageSelect.value, 10) || 25,
                q: '',
                lastPage: 1,
                total: 0,
            };

            let loadSeq = 0;
            let listAbort = null;
            let successHideTimer = null;

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
                if (applyBtn) applyBtn.disabled = on;
                if (perPageSelect) perPageSelect.disabled = on;
                if (searchInput) searchInput.disabled = on;
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

            function renderRows(rows) {
                if (!tbody) return;
                tbody.innerHTML = '';
                if (!rows.length) {
                    const tr = document.createElement('tr');
                    const td = document.createElement('td');
                    td.colSpan = 8;
                    td.className = 'text-muted text-center py-4';
                    td.textContent = 'No customers found. Try syncing from Shopify or adjust search.';
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
                    fuBtn.addEventListener('click', function () {
                        openFollowUpModal(r);
                    });
                    tdFu.appendChild(fuBtn);

                    tr.appendChild(tdId);
                    tr.appendChild(tdName);
                    tr.appendChild(tdEmail);
                    tr.appendChild(tdPhone);
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
                });
                if (state.q) {
                    params.set('q', state.q);
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
                        td.colSpan = 8;
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

            applyBtn?.addEventListener('click', function () {
                state.q = (searchInput?.value || '').trim();
                state.perPage = parseInt(perPageSelect?.value || '25', 10) || 25;
                loadPage(1);
            });

            searchInput?.addEventListener('keydown', function (ev) {
                if (ev.key === 'Enter') {
                    ev.preventDefault();
                    applyBtn?.click();
                }
            });

            firstBtn?.addEventListener('click', function () { loadPage(1); });
            prevBtn?.addEventListener('click', function () { loadPage(state.page - 1); });
            nextBtn?.addEventListener('click', function () { loadPage(state.page + 1); });
            lastBtn?.addEventListener('click', function () { loadPage(state.lastPage); });

            syncBtn?.addEventListener('click', function () {
                runSync();
            });

            loadPage(1);
        })();
    </script>
@endsection
