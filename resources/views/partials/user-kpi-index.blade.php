{{--
    Task Summary "KPI" magnifying-glass column.
    View assigned badges with history dots. Header: Key Performance Index of {name}.
--}}
<style>
    #taskSummaryKpiIndexModal .modal-dialog {
        max-width: 720px;
    }
    #taskSummaryKpiIndexModal .modal-content {
        border: none;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 20px 50px rgba(15, 23, 42, 0.18);
    }
    #taskSummaryKpiIndexModal .modal-header {
        background: linear-gradient(135deg, #0f766e, #14b8a6);
        color: #fff;
        border-bottom: 0;
    }
    #taskSummaryKpiIndexModal .modal-header .btn-close {
        filter: brightness(0) invert(1);
    }
    #taskSummaryKpiIndexModal .modal-title {
        font-weight: 800;
        letter-spacing: 0.01em;
    }
    .ts-kpi-index-badges {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 0.55rem;
        min-height: 4rem;
    }
    .ts-kpi-index-badges .badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        border-radius: 999px;
        padding: 0.55rem 1.15rem;
        background: #5ecfc4;
        color: #fff;
        font-size: 0.95rem;
        font-weight: 600;
        line-height: 1.2;
        box-shadow: none;
        cursor: default;
    }
    .ts-kpi-index-badges .badge .kpi-status-dot {
        box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.7);
    }
    .ts-kpi-index-badges .badge .kpi-status-dot--green { background: #22c55e; }
    .ts-kpi-index-badges .badge .kpi-status-dot--red { background: #ef4444; }
    .ts-kpi-index-badges .badge .kpi-status-dot--gray { background: #9ca3af; }
    .ts-kpi-index-empty {
        text-align: center;
        color: #64748b;
        font-weight: 600;
        padding: 1.25rem 0.5rem;
    }
    .ts-kpi-index-empty .btn {
        background: linear-gradient(135deg, #0f766e, #14b8a6);
        border: none;
    }
    #ts-kpi-index-ai-loading {
        text-align: center;
        color: #0f766e;
        font-weight: 600;
        padding: 1.25rem 0.5rem;
    }
    #ts-kpi-index-suggest {
        display: flex;
        gap: 0.4rem;
        margin-top: 1rem;
    }
    #ts-kpi-index-suggest .btn {
        background: #0f766e;
        border-color: #0f766e;
        color: #fff;
        white-space: nowrap;
    }
    .kpi-search-icon-btn.task-summary-kpi-index-btn {
        border: none;
        background: transparent;
        padding: 0.15rem 0.35rem;
        cursor: pointer;
        border-radius: 6px;
        transition: background 0.15s ease, transform 0.15s ease;
    }
    .kpi-search-icon-btn.task-summary-kpi-index-btn:hover {
        background: rgba(15, 118, 110, 0.12);
        transform: scale(1.1);
    }
    .kpi-search-icon-btn.task-summary-kpi-index-btn:disabled {
        opacity: 0.35;
        cursor: default;
        transform: none;
    }
</style>

<div class="modal fade" id="taskSummaryKpiIndexModal" tabindex="-1" aria-labelledby="taskSummaryKpiIndexModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="taskSummaryKpiIndexModalLabel">
                    Key Performance Index of <span id="ts-kpi-index-user">User</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="ts-kpi-index-loading" class="text-center py-4 d-none">
                    <div class="spinner-border text-success" role="status"><span class="visually-hidden">Loading…</span></div>
                    <p class="text-muted small mt-2 mb-0">Loading KPI badges…</p>
                </div>
                <div id="ts-kpi-index-error" class="alert alert-danger d-none" role="alert"></div>
                <div id="ts-kpi-index-ai-loading" class="d-none">
                    <div class="spinner-border text-success" role="status"><span class="visually-hidden">Working…</span></div>
                    <p class="small mt-2 mb-0">Asking AI to choose KPIs for this designation…</p>
                </div>
                <div id="ts-kpi-index-empty" class="ts-kpi-index-empty d-none">
                    <div class="fw-semibold mb-1">No KPI badges assigned yet</div>
                    <p class="small mb-3" id="ts-kpi-index-empty-help">Let AI pick KPIs from this person's designation, the same way R&amp;R is drafted.</p>
                    <button type="button" class="btn btn-primary" id="ts-kpi-index-generate-btn">
                        <i class="ri-magic-line me-1"></i> Generate with AI
                    </button>
                </div>
                <div class="ts-kpi-index-badges" id="ts-kpi-index-badges"></div>
                <div id="ts-kpi-index-suggest" class="d-none">
                    <input type="text" id="ts-kpi-index-hint" class="form-control form-control-sm" maxlength="500" placeholder="Optional hint, or leave blank for one more KPI">
                    <button type="button" class="btn btn-sm" id="ts-kpi-index-suggest-btn">
                        <i class="ri-sparkling-line"></i> Ask AI
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="ts-kpi-index-regen-btn" style="border-color:#0f766e;color:#0f766e;">
                    <i class="ri-refresh-line me-1"></i> Re-generate with AI
                </button>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var endpoint = @json(route('tasks.userKpis.get'));
    var generateUrl = @json(route('tasks.userKpis.generate'));
    var suggestUrl = @json(route('tasks.userKpis.suggest'));
    var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    var state = { userId: 0, canManage: false, count: 0 };

    function el(id) { return document.getElementById(id); }
    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
    function showModal() {
        var m = el('taskSummaryKpiIndexModal');
        if (!m || typeof bootstrap === 'undefined' || !bootstrap.Modal) return;
        bootstrap.Modal.getOrCreateInstance(m).show();
    }
    function setLoading(on) {
        var loading = el('ts-kpi-index-loading');
        if (loading) loading.classList.toggle('d-none', !on);
    }
    function showError(msg) {
        var n = el('ts-kpi-index-error');
        if (!n) return;
        n.textContent = msg || 'Could not load KPIs.';
        n.classList.remove('d-none');
    }
    function clearError() {
        var n = el('ts-kpi-index-error');
        if (n) { n.classList.add('d-none'); n.textContent = ''; }
    }

    function setAiLoading(on) {
        var node = el('ts-kpi-index-ai-loading');
        if (node) node.classList.toggle('d-none', !on);
        if (on) {
            var empty = el('ts-kpi-index-empty');
            var suggest = el('ts-kpi-index-suggest');
            if (empty) empty.classList.add('d-none');
            if (suggest) suggest.classList.add('d-none');
        }
    }

    function syncRowCount(count) {
        document.querySelectorAll('.task-summary-kpi-badges-btn').forEach(function (btn) {
            if (String(btn.getAttribute('data-user-id')) !== String(state.userId)) return;
            btn.setAttribute('data-badge-count', String(count));
            var badge = btn.querySelector('.kpi-badges-count');
            if (count > 0) {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'kpi-badges-count';
                    btn.appendChild(badge);
                }
                badge.textContent = String(count);
            } else if (badge) {
                badge.remove();
            }
        });
    }

    function paintAiControls(count) {
        state.count = count;
        var empty = el('ts-kpi-index-empty');
        var suggest = el('ts-kpi-index-suggest');
        var regen = el('ts-kpi-index-regen-btn');
        var generate = el('ts-kpi-index-generate-btn');
        var has = count > 0;
        if (empty) empty.classList.toggle('d-none', has);
        if (generate) generate.classList.toggle('d-none', !state.canManage);
        var help = el('ts-kpi-index-empty-help');
        if (help) help.classList.toggle('d-none', !state.canManage);
        if (suggest) suggest.classList.toggle('d-none', !state.canManage || !has || count >= 5);
        if (regen) regen.classList.toggle('d-none', !state.canManage || !has);
    }

    function postJson(url, body) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(body)
        }).then(function (r) {
            return r.json().then(function (data) {
                if (!r.ok || data.success === false) {
                    throw new Error((data && data.message) ? data.message : 'AI request failed.');
                }
                return data;
            });
        });
    }

    function renderBadges(assigned, opts) {
        opts = opts || {};
        var wrap = el('ts-kpi-index-badges');
        var empty = el('ts-kpi-index-empty');
        if (!wrap) return;
        wrap.innerHTML = '';
        var list = assigned || [];
        paintAiControls(list.length);
        if (!opts.skipSync) syncRowCount(list.length);
        if (!list.length) {
            if (empty) empty.classList.remove('d-none');
            return;
        }
        if (empty) empty.classList.add('d-none');
        list.forEach(function (item) {
            var badge = document.createElement('span');
            badge.className = 'badge';
            badge.setAttribute('data-kpi-key', item.key || '');
            badge.setAttribute('data-kpi-label', item.label || item.field_label || 'KPI');
            if (item.value !== null && item.value !== undefined && item.value !== '') {
                badge.setAttribute('data-kpi-value', String(item.value));
            }
            var label = item.field_label || item.label || 'KPI';
            var value = (item.value_display !== null && item.value_display !== undefined && item.value_display !== '')
                ? item.value_display
                : '—';
            badge.innerHTML = '<span>' + escapeHtml(label) + '</span>'
                + '<span>' + escapeHtml(value) + '</span>';
            wrap.appendChild(badge);
        });
        if (window.DashKpiDots && typeof window.DashKpiDots.refresh === 'function') {
            window.DashKpiDots.refresh();
        }
    }

    function openFromButton(btn) {
        var userId = parseInt(btn.getAttribute('data-user-id'), 10) || 0;
        var userName = (btn.getAttribute('data-user-name') || '').trim();
        if (!userId) return;

        var nameEl = el('ts-kpi-index-user');
        if (nameEl) nameEl.textContent = userName || 'User';

        state.userId = userId;
        state.canManage = false;
        state.count = 0;
        clearError();
        setAiLoading(false);
        renderBadges([], { skipSync: true });
        var empty = el('ts-kpi-index-empty');
        if (empty) empty.classList.add('d-none');
        setLoading(true);
        showModal();

        fetch(endpoint + '?user_id=' + encodeURIComponent(userId), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) {
            return r.json().then(function (data) {
                if (!r.ok || data.success === false) {
                    throw new Error((data && data.message) ? data.message : 'Could not load KPIs.');
                }
                return data;
            });
        }).then(function (data) {
            setLoading(false);
            state.canManage = !!data.can_manage;
            renderBadges(data.assigned || []);
        }).catch(function (err) {
            setLoading(false);
            showError(err.message || 'Could not load KPIs.');
        });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest && e.target.closest('.task-summary-kpi-index-btn');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        if (btn.disabled) return;
        openFromButton(btn);
    });

    document.addEventListener('click', function (e) {
        var generate = e.target && e.target.closest && e.target.closest('#ts-kpi-index-generate-btn');
        var regen = e.target && e.target.closest && e.target.closest('#ts-kpi-index-regen-btn');
        var suggest = e.target && e.target.closest && e.target.closest('#ts-kpi-index-suggest-btn');
        if (!generate && !regen && !suggest) return;
        e.preventDefault();
        if (!state.userId || !state.canManage) return;
        if (regen && !window.confirm('Replace the current KPI badges with a new AI list for this designation?')) return;
        clearError();
        setAiLoading(true);
        var request = suggest
            ? postJson(suggestUrl, {
                user_id: state.userId,
                hint: (el('ts-kpi-index-hint') && el('ts-kpi-index-hint').value || '').trim()
            })
            : postJson(generateUrl, { user_id: state.userId, force: !!regen });
        request.then(function (data) {
            setAiLoading(false);
            var hint = el('ts-kpi-index-hint');
            if (hint) hint.value = '';
            renderBadges(data.assigned || []);
        }).catch(function (err) {
            setAiLoading(false);
            paintAiControls(state.count);
            showError(err.message || 'AI could not update KPIs.');
        });
    });
})();
</script>
