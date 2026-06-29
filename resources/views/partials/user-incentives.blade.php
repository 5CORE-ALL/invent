{{--
    User incentives — Task Summary column + floating bag for logged-in user.
    Editable by president@5core.com only. Managers can view tagged juniors.

    GET  /tasks/user-incentives
    POST /tasks/user-incentives/sync
--}}

<style>
    .incentive-bag-btn {
        border: none;
        background: transparent;
        color: #b45309;
        padding: 0.15rem 0.35rem;
        cursor: pointer;
        border-radius: 6px;
        transition: background 0.15s ease, transform 0.15s ease;
        line-height: 1;
    }
    .incentive-bag-btn:hover {
        background: rgba(180, 83, 9, 0.12);
        transform: scale(1.08);
    }
    .incentive-bag-count {
        margin-left: 0.15rem;
        font-size: 0.62rem;
        font-weight: 800;
        color: #92400e;
        background: #fef3c7;
        border: 1px solid #fcd34d;
        padding: 0.05em 0.35em;
        border-radius: 999px;
        vertical-align: middle;
    }
    #taskSummaryIncentivesModal .modal-content {
        border-radius: 16px;
        border: none;
        box-shadow: 0 20px 50px rgba(15, 23, 42, 0.18);
    }
    #taskSummaryIncentivesModal .modal-header {
        background: linear-gradient(135deg, #b45309, #f59e0b);
        color: #fff;
        border-bottom: 0;
        border-top-left-radius: 16px;
        border-top-right-radius: 16px;
    }
    #taskSummaryIncentivesModal .modal-header .btn-close {
        filter: brightness(0) invert(1);
    }
    .ts-inc-item {
        border: 1px solid #fde68a;
        border-radius: 10px;
        padding: 0.75rem;
        margin-bottom: 0.65rem;
        background: linear-gradient(135deg, #fffbeb, #fef3c7);
    }
    .ts-inc-item.is-inactive {
        opacity: 0.55;
        background: #f8fafc;
        border-color: #e2e8f0;
    }
    .ts-inc-item-title {
        font-weight: 700;
        color: #92400e;
    }
    .ts-inc-item-amount {
        font-weight: 800;
        color: #15803d;
        font-variant-numeric: tabular-nums;
    }
    .ts-inc-edit-row {
        border: 1px dashed #fcd34d;
        border-radius: 10px;
        padding: 0.65rem;
        margin-bottom: 0.55rem;
        background: #fffbeb;
    }
    #ts-incentive-float-btn {
        position: fixed;
        left: 18px;
        bottom: 22px;
        z-index: 1045;
        width: 52px;
        height: 52px;
        border: none;
        border-radius: 50%;
        background: linear-gradient(145deg, #f59e0b, #d97706);
        color: #fff;
        box-shadow: 0 8px 22px rgba(180, 83, 9, 0.35);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.45rem;
        cursor: pointer;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    #ts-incentive-float-btn:hover {
        transform: scale(1.06);
        box-shadow: 0 10px 28px rgba(180, 83, 9, 0.45);
    }
    #ts-incentive-float-btn .ts-inc-float-count {
        position: absolute;
        top: -4px;
        right: -4px;
        min-width: 1.15rem;
        height: 1.15rem;
        padding: 0 0.25rem;
        border-radius: 999px;
        background: #15803d;
        color: #fff;
        font-size: 0.62rem;
        font-weight: 800;
        line-height: 1.15rem;
        text-align: center;
        border: 2px solid #fff;
    }
    @media (max-width: 576px) {
        #ts-incentive-float-btn {
            left: 12px;
            bottom: 16px;
            width: 46px;
            height: 46px;
            font-size: 1.25rem;
        }
    }
</style>

<button type="button"
        id="ts-incentive-float-btn"
        class="d-none"
        title="My incentives"
        aria-label="Open my incentives">
    <i class="ri-hand-coin-fill" aria-hidden="true"></i>
    <span class="ts-inc-float-count d-none" id="ts-incentive-float-count"></span>
</button>

<div class="modal fade" id="taskSummaryIncentivesModal" tabindex="-1" aria-labelledby="taskSummaryIncentivesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header flex-column align-items-stretch">
                <div class="d-flex align-items-start w-100">
                    <div class="flex-grow-1 min-w-0">
                        <h5 class="modal-title mb-1" id="taskSummaryIncentivesModalLabel">
                            <i class="ri-hand-coin-fill me-2" aria-hidden="true"></i>
                            <span id="ts-inc-modal-user">Incentives</span>
                        </h5>
                        <div class="small opacity-90" id="ts-inc-modal-designation"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body">
                <div id="ts-inc-loading" class="text-center py-4 d-none">
                    <div class="spinner-border text-warning" role="status"><span class="visually-hidden">Loading…</span></div>
                </div>
                <div id="ts-inc-error" class="alert alert-danger d-none" role="alert"></div>
                <div id="ts-inc-view" class="d-none"></div>
                <div id="ts-inc-edit-wrap" class="d-none">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-bold text-uppercase">Edit incentives</span>
                        <button type="button" class="btn btn-sm btn-outline-warning" id="ts-inc-add-row">
                            <i class="ri-add-line"></i> Add row
                        </button>
                    </div>
                    <div id="ts-inc-edit-rows"></div>
                    <button type="button" class="btn btn-sm text-white mt-2" id="ts-inc-save-btn" style="background:#b45309;border-color:#b45309;">
                        <i class="ri-save-line"></i> Save incentives
                    </button>
                </div>
                <p id="ts-inc-empty" class="text-muted small d-none mb-0">No incentives assigned yet.</p>
            </div>
            <div class="modal-footer">
                <small class="text-muted me-auto" id="ts-inc-footer-note">
                    <i class="ri-information-line me-1"></i> Incentives are managed by president@5core.com.
                </small>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@auth
<script>
(function () {
    var cfg = {
        viewerId: @json((int) auth()->id()),
        viewerName: @json(auth()->user()->name ?? ''),
        canEdit: @json(strtolower((string) (auth()->user()->email ?? '')) === 'president@5core.com'),
        routes: {
            get: @json(route('tasks.userIncentives.get')),
            sync: @json(route('tasks.userIncentives.sync'))
        }
    };

    var csrfToken = (function () {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    })();

    var state = {
        userId: null,
        userName: '',
        designation: '',
        canEdit: false,
        items: [],
        editItems: []
    };

    function el(id) { return document.getElementById(id); }
    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
    function getModalEl() { return el('taskSummaryIncentivesModal'); }
    function showModal() {
        var m = getModalEl();
        if (!m || typeof bootstrap === 'undefined' || !bootstrap.Modal) return;
        bootstrap.Modal.getOrCreateInstance(m).show();
    }
    function showError(msg) {
        var n = el('ts-inc-error');
        if (!n) return;
        n.textContent = msg || 'Something went wrong.';
        n.classList.remove('d-none');
    }
    function clearError() {
        var n = el('ts-inc-error');
        if (n) { n.classList.add('d-none'); n.textContent = ''; }
    }
    function setLoading(on) {
        var loading = el('ts-inc-loading');
        if (loading) loading.classList.toggle('d-none', !on);
    }

    function syncFloatCount(count) {
        var btn = el('ts-incentive-float-btn');
        var badge = el('ts-incentive-float-count');
        if (!btn) return;
        btn.classList.remove('d-none');
        if (state.userId === cfg.viewerId) {
            if (badge) {
                if (count > 0) {
                    badge.textContent = String(count);
                    badge.classList.remove('d-none');
                } else {
                    badge.classList.add('d-none');
                }
            }
        }
    }

    function syncRowCount() {
        if (!state.userId) return;
        var n = state.items.filter(function (i) { return i.is_active !== false; }).length;
        var rowBtn = document.querySelector('.task-summary-incentive-btn[data-user-id="' + state.userId + '"]');
        if (rowBtn) {
            rowBtn.setAttribute('data-incentive-count', String(n));
            var existing = rowBtn.querySelector('.incentive-bag-count');
            if (n > 0) {
                if (existing) existing.textContent = String(n);
                else {
                    var span = document.createElement('span');
                    span.className = 'incentive-bag-count';
                    span.textContent = String(n);
                    rowBtn.appendChild(span);
                }
            } else if (existing) {
                existing.remove();
            }
        }
        if (state.userId === cfg.viewerId) syncFloatCount(n);
    }

    function renderView() {
        var wrap = el('ts-inc-view');
        var empty = el('ts-inc-empty');
        var editWrap = el('ts-inc-edit-wrap');
        if (!wrap) return;

        var active = state.items.filter(function (i) { return i.is_active !== false; });
        if (state.canEdit) {
            wrap.classList.add('d-none');
            if (editWrap) editWrap.classList.remove('d-none');
            renderEditRows();
            if (empty) empty.classList.toggle('d-none', state.editItems.length > 0);
            return;
        }

        if (editWrap) editWrap.classList.add('d-none');
        if (!active.length) {
            wrap.innerHTML = '';
            wrap.classList.add('d-none');
            if (empty) empty.classList.remove('d-none');
            return;
        }
        if (empty) empty.classList.add('d-none');
        wrap.classList.remove('d-none');
        wrap.innerHTML = active.map(function (item) {
            var amt = item.amount_display
                ? ('<div class="ts-inc-item-amount">' + escapeHtml(item.amount_display) + '</div>')
                : '';
            var body = item.body
                ? ('<div class="text-muted small mt-1">' + escapeHtml(item.body).replace(/\n/g, '<br>') + '</div>')
                : '';
            return '<div class="ts-inc-item">'
                + '<div class="d-flex justify-content-between gap-2 align-items-start">'
                + '<div class="ts-inc-item-title">' + escapeHtml(item.title) + '</div>'
                + amt
                + '</div>' + body + '</div>';
        }).join('');
    }

    function renderEditRows() {
        var wrap = el('ts-inc-edit-rows');
        if (!wrap) return;
        wrap.innerHTML = state.editItems.map(function (item, idx) {
            return '<div class="ts-inc-edit-row" data-edit-idx="' + idx + '">'
                + '<div class="row g-2">'
                + '<div class="col-md-5"><input type="text" class="form-control form-control-sm ts-inc-field-title" placeholder="Title" value="' + escapeHtml(item.title || '') + '" maxlength="200"></div>'
                + '<div class="col-md-3"><input type="number" class="form-control form-control-sm ts-inc-field-amount" placeholder="Amount ₹" value="' + (item.amount != null ? escapeHtml(item.amount) : '') + '" min="0" step="1"></div>'
                + '<div class="col-md-2"><div class="form-check mt-1"><input class="form-check-input ts-inc-field-active" type="checkbox" ' + (item.is_active !== false ? 'checked' : '') + ' id="ts-inc-active-' + idx + '"><label class="form-check-label small" for="ts-inc-active-' + idx + '">Active</label></div></div>'
                + '<div class="col-md-2 text-end"><button type="button" class="btn btn-sm btn-outline-danger ts-inc-remove-row" data-edit-idx="' + idx + '"><i class="ri-delete-bin-line"></i></button></div>'
                + '<div class="col-12"><textarea class="form-control form-control-sm ts-inc-field-body" rows="2" placeholder="Details (optional)" maxlength="5000">' + escapeHtml(item.body || '') + '</textarea></div>'
                + '</div></div>';
        }).join('');
    }

    function collectEditItems() {
        var rows = document.querySelectorAll('#ts-inc-edit-rows .ts-inc-edit-row');
        var out = [];
        rows.forEach(function (row, idx) {
            var titleEl = row.querySelector('.ts-inc-field-title');
            var bodyEl = row.querySelector('.ts-inc-field-body');
            var amountEl = row.querySelector('.ts-inc-field-amount');
            var activeEl = row.querySelector('.ts-inc-field-active');
            var title = (titleEl && titleEl.value || '').trim();
            if (!title) return;
            var src = state.editItems[idx] || {};
            out.push({
                id: src.id || null,
                title: title,
                body: (bodyEl && bodyEl.value || '').trim() || null,
                amount: (amountEl && amountEl.value !== '') ? parseFloat(amountEl.value) : null,
                sort_order: idx,
                is_active: !!(activeEl && activeEl.checked)
            });
        });
        return out;
    }

    function pullEditFromDom() {
        state.editItems = collectEditItems();
    }

    function loadIncentives(userId, userName, designation) {
        state.userId = userId;
        state.userName = userName || '';
        state.designation = designation || '';
        clearError();
        setLoading(true);
        var view = el('ts-inc-view');
        var editWrap = el('ts-inc-edit-wrap');
        var empty = el('ts-inc-empty');
        if (view) view.classList.add('d-none');
        if (editWrap) editWrap.classList.add('d-none');
        if (empty) empty.classList.add('d-none');

        return fetch(cfg.routes.get + '?user_id=' + encodeURIComponent(userId), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) {
            return r.json().then(function (data) {
                if (!r.ok || data.success === false) throw new Error((data && data.message) || 'Could not load incentives.');
                return data;
            });
        }).then(function (data) {
            state.canEdit = !!data.can_edit;
            state.items = data.items || [];
            state.editItems = state.items.map(function (i) { return Object.assign({}, i); });
            setLoading(false);
            renderView();
            syncRowCount();
        }).catch(function (err) {
            setLoading(false);
            showError(err.message || 'Could not load incentives.');
        });
    }

    function openModal(userId, userName, designation) {
        userId = parseInt(userId, 10) || null;
        if (!userId) return;
        var title = el('ts-inc-modal-user');
        var des = el('ts-inc-modal-designation');
        if (title) title.textContent = (userName ? userName + ' — ' : '') + 'Incentives';
        if (des) des.innerHTML = designation ? ('<i class="ri-briefcase-line me-1"></i>' + escapeHtml(designation)) : '';
        showModal();
        loadIncentives(userId, userName, designation);
    }

    function saveIncentives() {
        if (!state.canEdit || !state.userId) return;
        pullEditFromDom();
        var btn = el('ts-inc-save-btn');
        if (btn) btn.disabled = true;
        clearError();
        fetch(cfg.routes.sync, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ user_id: state.userId, items: state.editItems })
        }).then(function (r) {
            return r.json().then(function (data) {
                if (!r.ok || data.success === false) throw new Error((data && data.message) || 'Save failed.');
                return data;
            });
        }).then(function (data) {
            state.items = data.items || [];
            state.editItems = state.items.map(function (i) { return Object.assign({}, i); });
            renderView();
            syncRowCount();
        }).catch(function (err) {
            showError(err.message || 'Could not save incentives.');
        }).finally(function () {
            if (btn) btn.disabled = false;
        });
    }

    document.addEventListener('click', function (e) {
        var t = e.target;
        if (!t || !t.closest) return;

        var rowBtn = t.closest('.task-summary-incentive-btn');
        if (rowBtn) {
            e.preventDefault();
            if (rowBtn.disabled) return;
            openModal(
                rowBtn.getAttribute('data-user-id'),
                rowBtn.getAttribute('data-user-name'),
                rowBtn.getAttribute('data-designation')
            );
            return;
        }

        if (t.closest('#ts-incentive-float-btn')) {
            e.preventDefault();
            openModal(cfg.viewerId, cfg.viewerName, '');
            return;
        }

        if (t.closest('#ts-inc-add-row')) {
            e.preventDefault();
            pullEditFromDom();
            state.editItems.push({ title: '', body: '', amount: null, is_active: true, sort_order: state.editItems.length });
            renderEditRows();
            return;
        }

        var rem = t.closest('.ts-inc-remove-row');
        if (rem) {
            e.preventDefault();
            pullEditFromDom();
            var idx = parseInt(rem.getAttribute('data-edit-idx'), 10);
            state.editItems.splice(idx, 1);
            renderEditRows();
            return;
        }

        if (t.closest('#ts-inc-save-btn')) {
            e.preventDefault();
            saveIncentives();
        }
    });

    window.taskSummaryIncentives = {
        open: openModal,
        refreshFloat: function () {
            if (cfg.viewerId) loadIncentives(cfg.viewerId, cfg.viewerName, '');
        }
    };

    if (cfg.viewerId) {
        fetch(cfg.routes.get + '?user_id=' + encodeURIComponent(cfg.viewerId), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (!data || data.success === false) return;
            var n = (data.items || []).filter(function (i) { return i.is_active !== false; }).length;
            syncFloatCount(n);
            el('ts-incentive-float-btn').classList.remove('d-none');
        }).catch(function () {
            el('ts-incentive-float-btn').classList.remove('d-none');
        });
    }
})();
</script>
@endauth
