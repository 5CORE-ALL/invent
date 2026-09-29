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
                <div id="ts-kpi-index-empty" class="ts-kpi-index-empty d-none">No KPI badges assigned yet. Use + to add them.</div>
                <div class="ts-kpi-index-badges" id="ts-kpi-index-badges"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var endpoint = @json(route('tasks.userKpis.get'));

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

    function renderBadges(assigned) {
        var wrap = el('ts-kpi-index-badges');
        var empty = el('ts-kpi-index-empty');
        if (!wrap) return;
        wrap.innerHTML = '';
        if (!assigned || !assigned.length) {
            if (empty) empty.classList.remove('d-none');
            return;
        }
        if (empty) empty.classList.add('d-none');
        assigned.forEach(function (item) {
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

        clearError();
        renderBadges([]);
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
})();
</script>
