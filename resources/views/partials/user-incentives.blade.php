{{--
    User incentives — Task Summary INC column + ₹ icon beside the login name.
    Table columns: Target, Incentive, Condition, CutOff Date.
    Editable by president@5core.com only. software5@5core.com can view every row.
    Everyone else can view their own row.

    GET  /tasks/user-incentives
    POST /tasks/user-incentives/sync  (replace one user, or mode=append to copy the same rows onto many users)
--}}

<style>
    .incentive-bag-btn {
        border: none;
        background: transparent;
        color: #15803d;
        padding: 0.7rem 0.35rem 0.15rem;
        cursor: pointer;
        border-radius: 6px;
        transition: background 0.15s ease, transform 0.15s ease;
        line-height: 1;
        font-weight: 800;
        font-size: 1.15rem;
        position: relative;
        overflow: visible;
    }
    .incentive-bag-btn:hover {
        background: rgba(21, 128, 61, 0.12);
        transform: scale(1.08);
    }
    .incentive-dollar-icon {
        font-weight: 800;
        font-size: 1.2rem;
        color: #15803d;
        line-height: 1;
    }
    .topbar-incentive-dollar-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        position: relative;
        overflow: visible;
        min-width: 32px;
        width: auto;
        height: 32px;
        margin-right: 0.15rem;
        padding: 0 0.65rem;
        border: none;
        border-radius: 999px;
        background: #15803d;
        color: #fff;
        font-weight: 800;
        font-size: 1.05rem;
        line-height: 1;
        cursor: pointer;
        box-shadow: 0 0 0 2px rgba(21, 128, 61, 0.15);
    }
    .topbar-incentive-dollar-btn.has-amount {
        padding-right: 0.45rem;
    }
    .topbar-incentive-amount-badge {
        display: inline-flex;
        align-items: center;
        position: static;
        transform: none;
        top: auto;
        left: auto;
        padding: 0.12em 0.45em;
        border-radius: 999px;
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fcd34d;
        font-size: 0.68rem;
        font-weight: 800;
        line-height: 1.15;
        white-space: nowrap;
    }
    .topbar-incentive-dollar-btn:hover {
        background: #166534;
        color: #fff;
    }
    .topbar-incentive-dollar-btn.is-cutoff-alert {
        background: #dc2626;
        box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.28);
        animation: ts-inc-alert-pulse 1.15s ease-in-out infinite;
    }
    .topbar-incentive-dollar-btn.is-cutoff-alert:hover {
        background: #b91c1c;
        color: #fff;
    }
    .incentive-bag-btn.is-cutoff-alert {
        background: #fef2f2;
    }
    .incentive-bag-btn.is-cutoff-alert .incentive-dollar-icon {
        color: #dc2626;
        animation: ts-inc-alert-pulse 1.15s ease-in-out infinite;
    }
    #ts-incentive-float-btn.is-cutoff-alert {
        background: linear-gradient(145deg, #ef4444, #b91c1c);
        box-shadow: 0 8px 22px rgba(185, 28, 28, 0.4);
        animation: ts-inc-alert-pulse 1.15s ease-in-out infinite;
    }
    .ts-inc-table tr.is-cutoff-alert td {
        background: #fef2f2;
    }
    .ts-inc-table td.ts-inc-cutoff-alert {
        color: #dc2626;
        font-weight: 800;
    }
    #ts-inc-cutoff-alert {
        display: flex;
        align-items: flex-start;
        gap: 0.45rem;
        margin-bottom: 0.85rem;
        font-weight: 600;
    }
    @keyframes ts-inc-alert-pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.08); }
    }
    .ts-inc-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.86rem;
    }
    .ts-inc-table th,
    .ts-inc-table td {
        border: 1px solid #fde68a;
        padding: 0.55rem 0.6rem;
        vertical-align: top;
    }
    .ts-inc-table thead th {
        background: #fffbeb;
        color: #92400e;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        font-weight: 800;
        white-space: nowrap;
    }
    .ts-inc-table .ts-inc-amt {
        font-weight: 800;
        color: #15803d;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .ts-inc-table tfoot td {
        background: #ecfdf5;
        font-weight: 800;
        color: #166534;
        border-top: 2px solid #86efac;
    }
    .ts-inc-table tfoot .ts-inc-amt {
        color: #166534;
        font-size: 1rem;
    }
    #ts-inc-modal-total {
        margin-top: 0.2rem;
        font-weight: 800;
        letter-spacing: 0.02em;
    }
    .incentive-bag-count {
        position: absolute;
        top: -2px;
        left: 50%;
        transform: translateX(-50%);
        margin-left: 0;
        font-size: 0.58rem;
        font-weight: 800;
        color: #92400e;
        background: #fef3c7;
        border: 1px solid #fcd34d;
        padding: 0.05em 0.35em;
        border-radius: 999px;
        white-space: nowrap;
        line-height: 1.15;
        z-index: 1;
    }
    #taskSummaryIncentivesModal .modal-dialog {
        max-width: min(1600px, 96vw);
        width: min(1600px, 96vw);
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
    #taskSummaryIncentivesModal .modal-title {
        font-size: 5rem;
        line-height: 1.15;
        font-weight: 700;
        word-break: break-word;
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
    .ts-inc-also-users {
        margin-bottom: 0.85rem;
        padding: 0.75rem 0.8rem;
        border: 1px solid #fde68a;
        border-radius: 10px;
        background: #fffbeb;
    }
    .ts-inc-also-users-label {
        display: block;
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #92400e;
        margin-bottom: 0.4rem;
    }
    .ts-inc-user-toolbar {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        margin-bottom: 0.45rem;
    }
    .ts-inc-user-list {
        max-height: none;
        overflow: visible;
        border: none;
        border-radius: 0;
        background: transparent;
    }
    .ts-inc-user-cols {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.55rem;
    }
    .ts-inc-user-col {
        display: flex;
        flex-direction: column;
        min-width: 0;
        max-height: 320px;
        border: 1px solid #fcd34d;
        border-radius: 8px;
        background: #fff;
        overflow: hidden;
    }
    .ts-inc-user-col-head {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        margin: 0;
        padding: 0.45rem 0.55rem;
        background: #fffbeb;
        border-bottom: 1px solid #fde68a;
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #92400e;
        cursor: pointer;
        user-select: none;
    }
    .ts-inc-user-col-head input {
        margin: 0;
    }
    .ts-inc-user-col-count {
        margin-left: auto;
        font-weight: 700;
        color: #b45309;
        letter-spacing: 0;
        text-transform: none;
    }
    .ts-inc-user-col-list {
        overflow: auto;
        flex: 1 1 auto;
    }
    @media (max-width: 992px) {
        .ts-inc-user-cols {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    @media (max-width: 576px) {
        .ts-inc-user-cols {
            grid-template-columns: 1fr;
        }
    }
    .ts-inc-user-option {
        display: flex;
        align-items: flex-start;
        gap: 0.5rem;
        padding: 0.4rem 0.6rem;
        margin: 0;
        cursor: pointer;
        border-bottom: 1px solid #fef3c7;
    }
    .ts-inc-user-option:last-child {
        border-bottom: 0;
    }
    .ts-inc-user-option:hover,
    .ts-inc-user-option.is-selected {
        background: #fff7ed;
    }
    .ts-inc-user-option input {
        margin-top: 0.2rem;
    }
    .ts-inc-user-option-name {
        font-weight: 700;
        color: #1f2937;
        line-height: 1.25;
    }
    .ts-inc-user-option-meta {
        display: block;
        font-size: 0.72rem;
        color: #92400e;
    }
    .ts-inc-user-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        margin-top: 0.5rem;
    }
    .ts-inc-user-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        background: #fef3c7;
        border: 1px solid #fcd34d;
        color: #92400e;
        border-radius: 999px;
        padding: 0.12rem 0.5rem 0.12rem 0.65rem;
        font-size: 0.75rem;
        font-weight: 700;
    }
    .ts-inc-user-chip button {
        border: 0;
        background: transparent;
        color: #92400e;
        line-height: 1;
        padding: 0;
        font-size: 0.95rem;
    }
    .ts-inc-user-empty {
        padding: 0.65rem 0.75rem;
        color: #92400e;
        font-size: 0.8rem;
    }
    .ts-inc-add-multi-top {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        border: none;
        border-radius: 999px;
        background: #15803d;
        color: #fff;
        font-weight: 800;
        font-size: 0.92rem;
        line-height: 1;
        padding: 0.55rem 0.95rem;
        cursor: pointer;
        box-shadow: 0 0 0 2px rgba(21, 128, 61, 0.15);
    }
    .ts-inc-add-multi-top:hover {
        background: #166534;
        color: #fff;
    }
    .ts-inc-add-multi-top .incentive-dollar-icon {
        color: #fff;
        font-size: 1.15rem;
    }
    #ts-inc-header-add-multi {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        white-space: nowrap;
        margin-right: 0.55rem;
        border: 0;
        background: rgba(255,255,255,0.18);
        color: #fff;
        font-weight: 800;
    }
    #ts-inc-header-add-multi:hover {
        background: rgba(255,255,255,0.3);
        color: #fff;
    }
</style>

<button type="button"
        id="ts-incentive-float-btn"
        class="d-none"
        title="My incentives"
        aria-label="Open my incentives">
    <span aria-hidden="true">₹</span>
    <span class="ts-inc-float-count d-none" id="ts-incentive-float-count"></span>
</button>

<div class="modal fade" id="taskSummaryIncentivesModal" tabindex="-1" aria-labelledby="taskSummaryIncentivesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header flex-column align-items-stretch">
                <div class="d-flex align-items-start w-100">
                    <div class="flex-grow-1 min-w-0">
                        <h5 class="modal-title mb-1" id="taskSummaryIncentivesModalLabel">
                            <span class="me-2" aria-hidden="true">₹</span>
                            <span id="ts-inc-modal-user">Incentives</span>
                        </h5>
                        <div class="small opacity-90" id="ts-inc-modal-designation"></div>
                        <div class="small" id="ts-inc-modal-total"></div>
                    </div>
                    <button type="button" class="btn btn-sm d-none" id="ts-inc-header-add-multi" title="Add the same incentive to multiple users">
                        <span aria-hidden="true">₹</span> Add incentive
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body">
                <div id="ts-inc-loading" class="text-center py-4 d-none">
                    <div class="spinner-border text-warning" role="status"><span class="visually-hidden">Loading…</span></div>
                </div>
                <div id="ts-inc-error" class="alert alert-danger d-none" role="alert"></div>
                <div id="ts-inc-cutoff-alert" class="alert alert-danger d-none" role="alert"></div>
                <div id="ts-inc-view" class="d-none"></div>
                <div id="ts-inc-edit-wrap" class="d-none">
                    <div id="ts-inc-also-users-wrap" class="ts-inc-also-users d-none">
                        <label class="ts-inc-also-users-label" for="ts-inc-user-search">Add this incentive to</label>
                        <div class="ts-inc-user-toolbar">
                            <input type="search" id="ts-inc-user-search" class="form-control form-control-sm" placeholder="Search users…" autocomplete="off">
                            <button type="button" class="btn btn-sm btn-outline-warning text-nowrap" id="ts-inc-user-select-filtered">Select shown</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary text-nowrap" id="ts-inc-user-clear">Clear</button>
                        </div>
                        <div id="ts-inc-user-list" class="ts-inc-user-list" role="group" aria-label="Users to also receive these incentives"></div>
                        <div id="ts-inc-user-chips" class="ts-inc-user-chips"></div>
                        <div class="form-text mt-1" id="ts-inc-also-users-help">The same incentive will be added separately to each selected user. Their existing incentives stay.</div>
                    </div>
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
        businessToday: @json(\App\Support\TaskBusinessTime::today()->toDateString()),
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
        mode: 'single',
        userId: null,
        userName: '',
        designation: '',
        canEdit: false,
        items: [],
        editItems: [],
        assignableUsers: [],
        alsoUserIds: [],
        userSearch: ''
    };

    function el(id) { return document.getElementById(id); }
    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
    function toDateInputValue(value) {
        var s = String(value == null ? '' : value).trim();
        var m = s.match(/^(\d{4}-\d{2}-\d{2})/);
        return m ? m[1] : '';
    }
    function addMonthsYmd(ymd, months) {
        var m = String(ymd || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (!m) return '';
        var year = parseInt(m[1], 10);
        var monthIndex = parseInt(m[2], 10) - 1 + months;
        var day = parseInt(m[3], 10);
        var first = new Date(year, monthIndex, 1);
        var lastDay = new Date(first.getFullYear(), first.getMonth() + 1, 0).getDate();
        first.setDate(Math.min(day, lastDay));
        return first.getFullYear()
            + '-' + String(first.getMonth() + 1).padStart(2, '0')
            + '-' + String(first.getDate()).padStart(2, '0');
    }
    function defaultCutoffDate() {
        return addMonthsYmd(cfg.businessToday, 1);
    }
    function emptyIncentiveRow() {
        var cutoff = defaultCutoffDate();
        return { title: '', body: '', additional_condition: cutoff, target_date: cutoff, amount: null, is_active: true, sort_order: 0 };
    }
    function usersFromSummaryPage() {
        var seen = {};
        var out = [];
        document.querySelectorAll('.task-summary-incentive-btn[data-user-id]').forEach(function (btn) {
            var id = parseInt(btn.getAttribute('data-user-id'), 10);
            if (!id || seen[id]) return;
            seen[id] = true;
            var row = btn.closest('tr.task-summary-row');
            out.push({
                id: id,
                name: btn.getAttribute('data-user-name') || 'User',
                designation: btn.getAttribute('data-designation') || '',
                org_level: btn.getAttribute('data-org-level')
                    || (row ? row.getAttribute('data-sort-org_level') : '')
                    || ''
            });
        });
        out.sort(function (a, b) {
            return String(a.name || '').localeCompare(String(b.name || ''));
        });
        return out;
    }
    function syncHeaderAddBtn() {
        var btn = el('ts-inc-header-add-multi');
        if (!btn) return;
        btn.classList.toggle('d-none', !cfg.canEdit || state.mode === 'bulk');
    }
    function syncSaveBtnLabel() {
        var btn = el('ts-inc-save-btn');
        if (!btn) return;
        btn.innerHTML = state.mode === 'bulk'
            ? '<i class="ri-save-line"></i> Add to selected users'
            : '<i class="ri-save-line"></i> Save incentives';
    }
    function loadAssignableUsers() {
        var fromPage = usersFromSummaryPage();
        if (fromPage.length) {
            state.assignableUsers = fromPage;
            return Promise.resolve(fromPage);
        }
        return fetch(cfg.routes.get, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) {
            return r.json().then(function (data) {
                if (!r.ok || data.success === false) throw new Error((data && data.message) || 'Could not load users.');
                return data;
            });
        }).then(function (data) {
            state.assignableUsers = data.users || [];
            return state.assignableUsers;
        });
    }
    function formatTargetDate(value) {
        var iso = toDateInputValue(value);
        if (!iso) return value ? String(value) : '—';
        var parts = iso.split('-');
        var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        var month = months[parseInt(parts[1], 10) - 1] || parts[1];
        return parseInt(parts[2], 10) + ' ' + month + ' ' + parts[0];
    }
    function daysUntilCutoff(value) {
        var iso = toDateInputValue(value);
        if (!iso) return null;
        var today = cfg.businessToday || '';
        if (!/^\d{4}-\d{2}-\d{2}$/.test(today)) {
            var now = new Date();
            today = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
        }
        var t = Date.parse(today + 'T00:00:00');
        var c = Date.parse(iso + 'T00:00:00');
        if (isNaN(t) || isNaN(c)) return null;
        return Math.round((c - t) / 86400000);
    }
    function isCutoffAlert(value) {
        var days = daysUntilCutoff(value);
        return days !== null && days <= 1;
    }
    function itemCutoffValue(item) {
        return item ? (item.target_date || item.additional_condition || '') : '';
    }
    function cutoffAlertText(item) {
        var days = daysUntilCutoff(itemCutoffValue(item));
        var name = item && (item.target || item.title) ? (item.target || item.title) + ': ' : '';
        if (days === null) return '';
        if (days < 0) return name + 'CutOff Date has passed.';
        if (days === 0) return name + 'CutOff Date is today.';
        if (days === 1) return name + 'CutOff Date is tomorrow.';
        return name + 'CutOff Date in ' + days + ' day' + (days === 1 ? '' : 's') + '.';
    }
    function urgentCutoffItems(items) {
        return (items || []).filter(function (i) {
            return i && i.is_active !== false && isCutoffAlert(itemCutoffValue(i));
        });
    }
    function setCutoffAlertClass(node, on, title) {
        if (!node) return;
        node.classList.toggle('is-cutoff-alert', !!on);
        node.setAttribute('data-cutoff-alert', on ? '1' : '0');
        if (on && title) node.setAttribute('title', title);
    }
    function applyOwnCutoffAlert(items) {
        var urgent = urgentCutoffItems(items);
        var on = urgent.length > 0;
        var amountTitle = 'My incentives · ' + formatRupee(sumIncentiveAmounts(items));
        var title = on ? cutoffAlertText(urgent[0]) : amountTitle;
        setCutoffAlertClass(document.getElementById('ts-incentive-header-btn'), on, title);
        setCutoffAlertClass(el('ts-incentive-float-btn'), on, title);
    }
    function renderCutoffBanner(items) {
        var box = el('ts-inc-cutoff-alert');
        if (!box) return;
        var urgent = urgentCutoffItems(items);
        if (!urgent.length) {
            box.classList.add('d-none');
            box.innerHTML = '';
            return;
        }
        box.innerHTML = '<i class="ri-error-warning-line" aria-hidden="true"></i><div>'
            + urgent.map(function (item) { return escapeHtml(cutoffAlertText(item)); }).join('<br>')
            + '</div>';
        box.classList.remove('d-none');
    }
    function getModalEl() { return el('taskSummaryIncentivesModal'); }
    function showModal() {
        var m = getModalEl();
        if (!m || typeof bootstrap === 'undefined' || !bootstrap.Modal) return;
        bootstrap.Modal.getOrCreateInstance(m).show();
    }
    function hideModal() {
        var m = getModalEl();
        if (!m || typeof bootstrap === 'undefined' || !bootstrap.Modal) return;
        var instance = bootstrap.Modal.getInstance(m);
        if (instance) instance.hide();
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

    function setAmountBadge(badge, amount, count) {
        if (!badge) return;
        var total = parseFloat(amount) || 0;
        var show = total > 0 || (count || 0) > 0;
        badge.textContent = formatRupee(total);
        badge.classList.toggle('d-none', !show);
        if (!show && badge.classList.contains('incentive-bag-count') && badge.parentNode) {
            badge.remove();
        }
    }
    function syncHeaderAmount(items) {
        var total = sumIncentiveAmounts(items);
        var count = (items || []).filter(function (i) { return i && i.is_active !== false; }).length;
        setAmountBadge(el('ts-incentive-header-amount'), total, count);
        var header = document.getElementById('ts-incentive-header-btn');
        if (header) {
            header.classList.toggle('has-amount', total > 0 || count > 0);
            header.setAttribute('data-incentive-amount', String(total));
            header.title = (total > 0 || count > 0) ? ('My incentives · ' + formatRupee(total)) : 'My incentives';
        }
    }
    function syncFloatCount(items) {
        var btn = el('ts-incentive-float-btn');
        var badge = el('ts-incentive-float-count');
        if (!btn) return;
        btn.classList.remove('d-none');
        var total = sumIncentiveAmounts(items);
        var count = (items || []).filter(function (i) { return i && i.is_active !== false; }).length;
        setAmountBadge(badge, total, count);
        btn.title = count > 0 ? ('My incentives · ' + formatRupee(total)) : 'My incentives';
    }

    function formatAmountK(amount) {
        var n = parseFloat(amount);
        if (isNaN(n) || n <= 0) return '0';
        var k = Math.round((n / 1000) * 10) / 10;
        var text = Math.abs(k - Math.round(k)) < 0.001 ? String(Math.round(k)) : k.toFixed(1);
        return text + 'K';
    }
    function updateRowIncentiveStats(userId, count, amount, cutoffAlert) {
        userId = parseInt(userId, 10) || 0;
        if (!userId) return;
        var rowBtn = document.querySelector('.task-summary-incentive-btn[data-user-id="' + userId + '"]');
        if (!rowBtn) return;
        var n = parseInt(count, 10) || 0;
        var total = parseFloat(amount) || 0;
        rowBtn.setAttribute('data-incentive-count', String(n));
        rowBtn.setAttribute('data-incentive-amount', String(total));
        var icon = rowBtn.querySelector('.incentive-dollar-icon');
        if (icon) icon.remove();
        var oldBadge = rowBtn.querySelector('.incentive-bag-count');
        if (oldBadge) oldBadge.remove();
        var label = rowBtn.querySelector('.incentive-k-amount');
        if (!label) {
            label = document.createElement('span');
            label.className = 'incentive-k-amount';
            rowBtn.appendChild(label);
        }
        label.textContent = formatAmountK(total);
        var baseTitle = rowBtn.getAttribute('title') || '';
        baseTitle = baseTitle.replace(/\s·\s[\d,]+$/, '');
        rowBtn.title = total > 0 ? (baseTitle + ' · ' + Math.round(total).toLocaleString('en-IN')) : baseTitle;
        if (cutoffAlert !== undefined) {
            setCutoffAlertClass(rowBtn, !!cutoffAlert, cutoffAlert ? 'Red alert: CutOff Date is tomorrow or sooner' : '');
        }
        var row = rowBtn.closest('tr.task-summary-row');
        if (row) row.setAttribute('data-sort-incentive_amount', String(total));
    }
    function syncCopiedUserRows(copied) {
        (copied || []).forEach(function (user) {
            if (!user || !user.id) return;
            updateRowIncentiveStats(user.id, user.incentive_count, user.incentive_amount, user.cutoff_alert);
            if ((parseInt(user.id, 10) || 0) === cfg.viewerId) {
                fetch(cfg.routes.get + '?user_id=' + encodeURIComponent(user.id), {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function (r) { return r.json(); }).then(function (data) {
                    if (!data || data.success === false) return;
                    syncFloatCount(data.items || []);
                    syncHeaderAmount(data.items || []);
                    applyOwnCutoffAlert(data.items || []);
                }).catch(function () {});
            }
        });
    }
    function syncRowCount() {
        if (!state.userId) return;
        var n = state.items.filter(function (i) { return i.is_active !== false; }).length;
        var total = sumIncentiveAmounts(state.items);
        var urgent = urgentCutoffItems(state.items);
        updateRowIncentiveStats(state.userId, n, total, urgent.length > 0);
        if (state.userId === cfg.viewerId) {
            syncFloatCount(state.items);
            syncHeaderAmount(state.items);
            applyOwnCutoffAlert(state.items);
        }
        if (typeof window.taskSummaryRecomputeBadges === 'function') {
            window.taskSummaryRecomputeBadges();
        }
    }

    function formatRupee(amount) {
        var n = parseFloat(amount);
        if (amount == null || amount === '' || isNaN(n)) return '₹0';
        return '₹' + Math.round(n).toLocaleString('en-IN');
    }
    function sumIncentiveAmounts(items) {
        return (items || []).reduce(function (sum, item) {
            if (!item || item.is_active === false) return sum;
            var n = parseFloat(item.amount);
            return sum + (isNaN(n) ? 0 : n);
        }, 0);
    }
    function currentIncentiveTotal() {
        if (state.canEdit) {
            var rows = document.querySelectorAll('#ts-inc-edit-rows .ts-inc-edit-row');
            if (rows.length) {
                var sum = 0;
                rows.forEach(function (row) {
                    var activeEl = row.querySelector('.ts-inc-field-active');
                    if (activeEl && !activeEl.checked) return;
                    var amountEl = row.querySelector('.ts-inc-field-amount');
                    var n = amountEl && amountEl.value !== '' ? parseFloat(amountEl.value) : 0;
                    if (!isNaN(n)) sum += n;
                });
                return sum;
            }
            return sumIncentiveAmounts(state.editItems);
        }
        return sumIncentiveAmounts(state.items);
    }
    function incentiveTotalFooter(colCount) {
        var extra = '';
        for (var i = 2; i < colCount; i++) extra += '<td></td>';
        return '<tfoot><tr class="ts-inc-total-row"><td>Total</td><td class="ts-inc-amt" id="ts-inc-total-amount">'
            + formatRupee(currentIncentiveTotal())
            + '</td>' + extra + '</tr></tfoot>';
    }
    function renderIncentiveTotal() {
        var total = formatRupee(currentIncentiveTotal());
        var header = el('ts-inc-modal-total');
        if (header) header.textContent = 'Total Amount: ' + total;
        var cell = el('ts-inc-total-amount');
        if (cell) cell.textContent = total;
    }

    function renderView() {
        var wrap = el('ts-inc-view');
        var empty = el('ts-inc-empty');
        var editWrap = el('ts-inc-edit-wrap');
        if (!wrap) return;

        var active = state.items.filter(function (i) { return i.is_active !== false; });
        renderCutoffBanner(state.canEdit ? state.editItems : state.items);
        if (state.canEdit) {
            wrap.classList.add('d-none');
            if (editWrap) editWrap.classList.remove('d-none');
            renderAlsoUsersPicker();
            renderEditRows();
            if (empty) empty.classList.toggle('d-none', state.editItems.length > 0 || state.mode === 'bulk');
            syncHeaderAddBtn();
            syncSaveBtnLabel();
            return;
        }

        if (editWrap) editWrap.classList.add('d-none');
        var alsoWrap = el('ts-inc-also-users-wrap');
        if (alsoWrap) alsoWrap.classList.add('d-none');
        syncHeaderAddBtn();
        syncSaveBtnLabel();
        if (!active.length) {
            wrap.innerHTML = '';
            wrap.classList.add('d-none');
            if (empty) empty.classList.remove('d-none');
            renderIncentiveTotal();
            return;
        }
        if (empty) empty.classList.add('d-none');
        wrap.classList.remove('d-none');
        wrap.innerHTML = '<div class="table-responsive"><table class="ts-inc-table">'
            + '<thead><tr><th>Target</th><th>Incentive</th><th>Condition</th><th>CutOff Date</th></tr></thead><tbody>'
            + active.map(function (item) {
                var alertOn = isCutoffAlert(itemCutoffValue(item));
                return '<tr class="' + (alertOn ? 'is-cutoff-alert' : '') + '">'
                    + '<td>' + escapeHtml(item.target || item.title || '—') + '</td>'
                    + '<td class="ts-inc-amt">' + escapeHtml(item.amount_display || '—') + '</td>'
                    + '<td>' + escapeHtml(item.condition || item.body || '—').replace(/\n/g, '<br>') + '</td>'
                    + '<td class="' + (alertOn ? 'ts-inc-cutoff-alert' : '') + '">' + escapeHtml(formatTargetDate(itemCutoffValue(item))) + '</td>'
                    + '</tr>';
            }).join('')
            + '</tbody>' + incentiveTotalFooter(4) + '</table></div>';
        renderIncentiveTotal();
    }

    function renderEditRows() {
        var wrap = el('ts-inc-edit-rows');
        if (!wrap) return;
        wrap.innerHTML = '<div class="table-responsive"><table class="ts-inc-table">'
            + '<thead><tr><th>Target</th><th>Incentive</th><th>Condition</th><th>CutOff Date</th><th></th></tr></thead><tbody>'
            + state.editItems.map(function (item, idx) {
                return '<tr class="ts-inc-edit-row" data-edit-idx="' + idx + '">'
                    + '<td><input type="text" class="form-control form-control-sm ts-inc-field-title" placeholder="Target" value="' + escapeHtml(item.title || item.target || '') + '" maxlength="200"></td>'
                    + '<td><input type="number" class="form-control form-control-sm ts-inc-field-amount" placeholder="₹" value="' + (item.amount != null ? escapeHtml(item.amount) : '') + '" min="0" step="1"></td>'
                    + '<td><textarea class="form-control form-control-sm ts-inc-field-body" rows="2" placeholder="Condition" maxlength="5000">' + escapeHtml(item.body || item.condition || '') + '</textarea></td>'
                    + '<td><input type="date" class="form-control form-control-sm ts-inc-field-extra" value="' + escapeHtml(toDateInputValue(item.target_date || item.additional_condition) || defaultCutoffDate()) + '">'
                    + '<div class="form-check mt-1"><input class="form-check-input ts-inc-field-active" type="checkbox" ' + (item.is_active !== false ? 'checked' : '') + ' id="ts-inc-active-' + idx + '"><label class="form-check-label small" for="ts-inc-active-' + idx + '">Active</label></div></td>'
                    + '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger ts-inc-remove-row" data-edit-idx="' + idx + '"><i class="ri-delete-bin-line"></i></button></td>'
                    + '</tr>';
            }).join('')
            + '</tbody>' + incentiveTotalFooter(5) + '</table></div>';
        renderIncentiveTotal();
    }

    function filteredAssignableUsers() {
        var q = String(state.userSearch || '').trim().toLowerCase();
        return (state.assignableUsers || []).filter(function (user) {
            if (!q) return true;
            var blob = ((user.name || '') + ' ' + (user.designation || '') + ' ' + userRoleLabel(user)).toLowerCase();
            return blob.indexOf(q) !== -1;
        });
    }
    var ROLE_COLUMNS = [
        { key: 'director', label: 'Directors' },
        { key: 'mgr', label: 'Managers' },
        { key: 'exec', label: 'Executive' },
        { key: 'other', label: 'Others' }
    ];
    function userRoleKey(user) {
        var level = String(user && user.org_level != null ? user.org_level : '').trim().toLowerCase();
        if (level === 'director') return 'director';
        if (level === 'mgr' || level === 'manager') return 'mgr';
        if (level === 'exec' || level === 'executive') return 'exec';
        return 'other';
    }
    function userRoleLabel(user) {
        var key = userRoleKey(user);
        for (var i = 0; i < ROLE_COLUMNS.length; i++) {
            if (ROLE_COLUMNS[i].key === key) return ROLE_COLUMNS[i].label;
        }
        return 'Others';
    }
    function usersInRole(users, key) {
        return (users || []).filter(function (user) { return userRoleKey(user) === key; });
    }
    function userOptionHtml(user) {
        var selected = isAlsoUserSelected(user.id);
        return '<label class="ts-inc-user-option' + (selected ? ' is-selected' : '') + '">'
            + '<input type="checkbox" class="form-check-input ts-inc-user-check" value="' + escapeHtml(user.id) + '"'
            + (selected ? ' checked' : '') + '>'
            + '<span><span class="ts-inc-user-option-name">' + escapeHtml(user.name || 'User') + '</span>'
            + (user.designation ? '<span class="ts-inc-user-option-meta">' + escapeHtml(user.designation) + '</span>' : '')
            + '</span></label>';
    }
    function isAlsoUserSelected(userId) {
        userId = parseInt(userId, 10);
        return state.alsoUserIds.indexOf(userId) !== -1;
    }
    function setAlsoUserSelected(userId, on) {
        userId = parseInt(userId, 10);
        if (!userId) return;
        var idx = state.alsoUserIds.indexOf(userId);
        if (on && idx === -1) state.alsoUserIds.push(userId);
        if (!on && idx !== -1) state.alsoUserIds.splice(idx, 1);
    }
    function userById(userId) {
        userId = parseInt(userId, 10);
        for (var i = 0; i < (state.assignableUsers || []).length; i++) {
            if (parseInt(state.assignableUsers[i].id, 10) === userId) return state.assignableUsers[i];
        }
        return null;
    }
    function renderAlsoUsersPicker(opts) {
        opts = opts || {};
        var wrap = el('ts-inc-also-users-wrap');
        var list = el('ts-inc-user-list');
        var chips = el('ts-inc-user-chips');
        var colScroll = {};
        if (list) {
            list.querySelectorAll('.ts-inc-user-col').forEach(function (col) {
                var body = col.querySelector('.ts-inc-user-col-list');
                if (body) colScroll[col.getAttribute('data-role')] = body.scrollTop;
            });
        }
        if (!wrap || !list) return;
        var showPicker = state.canEdit && state.mode === 'bulk';
        wrap.classList.toggle('d-none', !showPicker);
        if (!showPicker) {
            state.alsoUserIds = [];
            if (chips) chips.innerHTML = '';
            return;
        }

        var label = wrap.querySelector('.ts-inc-also-users-label');
        var help = el('ts-inc-also-users-help');
        var selectedLabel = state.mode === 'bulk' ? 'Add this incentive to' : 'Also add these incentives to';
        if (label) {
            label.textContent = state.alsoUserIds.length
                ? (selectedLabel + ' (' + state.alsoUserIds.length + ' selected)')
                : selectedLabel;
        }
        if (help) {
            help.textContent = state.mode === 'bulk'
                ? 'The same incentive will be added separately to each selected user. Their existing incentives stay.'
                : 'The same incentive rows will also be added separately to each selected user. Their existing incentives stay.';
        }

        var users = filteredAssignableUsers();
        if (!users.length && !state.assignableUsers.length) {
            list.innerHTML = '<div class="ts-inc-user-empty">No other users available.</div>';
        } else {
            list.innerHTML = '<div class="ts-inc-user-cols">' + ROLE_COLUMNS.map(function (col) {
                var colUsers = usersInRole(users, col.key);
                var selectedCount = colUsers.filter(function (user) { return isAlsoUserSelected(user.id); }).length;
                var allOn = colUsers.length > 0 && selectedCount === colUsers.length;
                var someOn = selectedCount > 0 && !allOn;
                return '<section class="ts-inc-user-col" data-role="' + col.key + '">'
                    + '<label class="ts-inc-user-col-head">'
                    + '<input type="checkbox" class="form-check-input ts-inc-role-check" data-role="' + col.key + '"'
                    + (allOn ? ' checked' : '')
                    + (someOn ? ' data-indeterminate="1"' : '')
                    + (colUsers.length ? '' : ' disabled') + '>'
                    + '<span>' + escapeHtml(col.label) + '</span>'
                    + '<span class="ts-inc-user-col-count">' + selectedCount + '/' + colUsers.length + '</span>'
                    + '</label>'
                    + '<div class="ts-inc-user-col-list">'
                    + (colUsers.length
                        ? colUsers.map(userOptionHtml).join('')
                        : '<div class="ts-inc-user-empty">' + (state.userSearch ? 'No match' : 'None') + '</div>')
                    + '</div></section>';
            }).join('') + '</div>';
            list.querySelectorAll('.ts-inc-role-check[data-indeterminate="1"]').forEach(function (cb) {
                cb.indeterminate = true;
            });
            if (!opts.resetScroll) {
                list.querySelectorAll('.ts-inc-user-col').forEach(function (col) {
                    var body = col.querySelector('.ts-inc-user-col-list');
                    var role = col.getAttribute('data-role');
                    if (body && colScroll[role] != null) body.scrollTop = colScroll[role];
                });
            }
        }

        if (chips) {
            chips.innerHTML = state.alsoUserIds.map(function (id) {
                var user = userById(id);
                if (!user) return '';
                return '<span class="ts-inc-user-chip">'
                    + escapeHtml(user.name || 'User')
                    + '<button type="button" class="ts-inc-user-chip-remove" data-user-id="' + escapeHtml(id) + '" aria-label="Remove ' + escapeHtml(user.name || 'user') + '">&times;</button>'
                    + '</span>';
            }).join('');
        }
    }

    function collectEditItems() {
        var rows = document.querySelectorAll('#ts-inc-edit-rows .ts-inc-edit-row');
        var out = [];
        rows.forEach(function (row, idx) {
            var titleEl = row.querySelector('.ts-inc-field-title');
            var bodyEl = row.querySelector('.ts-inc-field-body');
            var extraEl = row.querySelector('.ts-inc-field-extra');
            var amountEl = row.querySelector('.ts-inc-field-amount');
            var activeEl = row.querySelector('.ts-inc-field-active');
            var title = (titleEl && titleEl.value || '').trim();
            if (!title) return;
            var src = state.editItems[idx] || {};
            out.push({
                id: src.id || null,
                title: title,
                body: (bodyEl && bodyEl.value || '').trim() || null,
                additional_condition: (extraEl && extraEl.value || '').trim() || null,
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
        state.mode = 'single';
        state.userId = userId;
        state.userName = userName || '';
        state.designation = designation || '';
        clearError();
        setLoading(true);
        var view = el('ts-inc-view');
        var editWrap = el('ts-inc-edit-wrap');
        var empty = el('ts-inc-empty');
        var cutoff = el('ts-inc-cutoff-alert');
        var alsoWrap = el('ts-inc-also-users-wrap');
        if (view) view.classList.add('d-none');
        if (editWrap) editWrap.classList.add('d-none');
        if (alsoWrap) alsoWrap.classList.add('d-none');
        if (empty) empty.classList.add('d-none');
        if (cutoff) cutoff.classList.add('d-none');

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
            state.assignableUsers = data.users || [];
            state.alsoUserIds = [];
            state.userSearch = '';
            var searchEl = el('ts-inc-user-search');
            if (searchEl) searchEl.value = '';
            setLoading(false);
            renderView();
            syncRowCount();
            if (state.userId === cfg.viewerId) applyOwnCutoffAlert(state.items);
        }).catch(function (err) {
            setLoading(false);
            showError(err.message || 'Could not load incentives.');
        });
    }

    function openModal(userId, userName, designation) {
        userId = parseInt(userId, 10) || null;
        if (!userId) return;
        state.mode = 'single';
        var title = el('ts-inc-modal-user');
        var des = el('ts-inc-modal-designation');
        if (title) title.textContent = (userName ? userName + ' — ' : '') + 'Incentives';
        if (des) des.innerHTML = designation ? ('<i class="ri-briefcase-line me-1"></i>' + escapeHtml(designation)) : '';
        syncHeaderAddBtn();
        showModal();
        loadIncentives(userId, userName, designation);
    }

    function openBulkAdd(opts) {
        opts = opts || {};
        if (!cfg.canEdit) return;
        if (state.canEdit && state.mode !== 'bulk') {
            pullEditFromDom();
        }
        var keepRows = opts.keepRows && state.editItems && state.editItems.length
            ? state.editItems.map(function (item) { return Object.assign({}, item, { id: null }); })
            : [emptyIncentiveRow()];
        state.mode = 'bulk';
        state.userId = null;
        state.userName = '';
        state.designation = '';
        state.canEdit = true;
        state.items = [];
        state.editItems = keepRows.length ? keepRows : [emptyIncentiveRow()];
        state.alsoUserIds = [];
        state.userSearch = '';
        var searchEl = el('ts-inc-user-search');
        if (searchEl) searchEl.value = '';
        clearError();
        setLoading(true);
        var view = el('ts-inc-view');
        var editWrap = el('ts-inc-edit-wrap');
        var empty = el('ts-inc-empty');
        if (view) view.classList.add('d-none');
        if (editWrap) editWrap.classList.add('d-none');
        if (empty) empty.classList.add('d-none');
        var title = el('ts-inc-modal-user');
        var des = el('ts-inc-modal-designation');
        if (title) title.textContent = 'Add incentive';
        if (des) des.innerHTML = '<i class="ri-group-line me-1"></i>Select users. The same incentive is added to each of them separately.';
        syncHeaderAddBtn();
        showModal();
        loadAssignableUsers().then(function () {
            setLoading(false);
            renderView();
        }).catch(function (err) {
            setLoading(false);
            showError(err.message || 'Could not load users.');
            renderView();
        });
    }

    function saveIncentives() {
        if (!state.canEdit) return;
        pullEditFromDom();
        var isBulk = state.mode === 'bulk';
        if (isBulk && !state.alsoUserIds.length) {
            showError('Select at least one user.');
            return;
        }
        if (isBulk && !state.editItems.length) {
            showError('Add at least one incentive row with a target.');
            return;
        }
        if (!isBulk && !state.userId) return;
        var btn = el('ts-inc-save-btn');
        if (btn) btn.disabled = true;
        clearError();
        var payload = isBulk
            ? { mode: 'append', user_ids: state.alsoUserIds.slice(), items: state.editItems }
            : { user_id: state.userId, items: state.editItems };
        fetch(cfg.routes.sync, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(payload)
        }).then(function (r) {
            return r.json().then(function (data) {
                if (!r.ok || data.success === false) throw new Error((data && data.message) || 'Save failed.');
                return data;
            });
        }).then(function (data) {
            if (isBulk) {
                syncCopiedUserRows(data.copied_to || []);
                hideModal();
                return;
            }
            state.items = data.items || [];
            state.editItems = state.items.map(function (i) { return Object.assign({}, i); });
            state.alsoUserIds = [];
            renderView();
            syncRowCount();
            syncCopiedUserRows(data.copied_to || []);
            if (state.userId === cfg.viewerId) applyOwnCutoffAlert(state.items);
            hideModal();
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

        if (t.closest('#ts-incentive-float-btn') || t.closest('#ts-incentive-header-btn')) {
            e.preventDefault();
            openModal(cfg.viewerId, cfg.viewerName, '');
            return;
        }

        if (t.closest('#ts-inc-add-multi-btn')) {
            e.preventDefault();
            openBulkAdd();
            return;
        }

        if (t.closest('#ts-inc-header-add-multi')) {
            e.preventDefault();
            openBulkAdd({ keepRows: true });
            return;
        }

        if (t.closest('#ts-inc-add-row')) {
            e.preventDefault();
            pullEditFromDom();
            var cutoff = defaultCutoffDate();
            state.editItems.push({ title: '', body: '', additional_condition: cutoff, target_date: cutoff, amount: null, is_active: true, sort_order: state.editItems.length });
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
            return;
        }

        if (t.closest('#ts-inc-user-select-filtered')) {
            e.preventDefault();
            filteredAssignableUsers().forEach(function (user) {
                setAlsoUserSelected(user.id, true);
            });
            renderAlsoUsersPicker();
            return;
        }

        if (t.closest('#ts-inc-user-clear')) {
            e.preventDefault();
            state.alsoUserIds = [];
            renderAlsoUsersPicker();
            return;
        }

        var chipRemove = t.closest('.ts-inc-user-chip-remove');
        if (chipRemove) {
            e.preventDefault();
            setAlsoUserSelected(chipRemove.getAttribute('data-user-id'), false);
            renderAlsoUsersPicker();
        }
    });

    document.addEventListener('input', function (e) {
        var t = e.target;
        if (!t || !t.closest) return;
        if (t.closest('.ts-inc-field-amount') || t.closest('.ts-inc-field-active')) {
            renderIncentiveTotal();
        }
        if (t.id === 'ts-inc-user-search') {
            state.userSearch = t.value || '';
            renderAlsoUsersPicker({ resetScroll: true });
        }
    });
    document.addEventListener('change', function (e) {
        var t = e.target;
        if (!t || !t.closest) return;
        if (t.classList.contains('ts-inc-user-check')) {
            setAlsoUserSelected(t.value, !!t.checked);
            renderAlsoUsersPicker();
        }
        if (t.classList.contains('ts-inc-role-check')) {
            var role = t.getAttribute('data-role');
            var on = !!t.checked;
            usersInRole(filteredAssignableUsers(), role).forEach(function (user) {
                setAlsoUserSelected(user.id, on);
            });
            renderAlsoUsersPicker();
        }
        if (t.closest('.ts-inc-field-active') || t.closest('.ts-inc-field-amount')) {
            renderIncentiveTotal();
        }
    });

    window.taskSummaryIncentives = {
        open: openModal,
        openBulkAdd: openBulkAdd,
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
            syncFloatCount(data.items || []);
            syncHeaderAmount(data.items || []);
            applyOwnCutoffAlert(data.items || []);
        }).catch(function () {});
    }
})();
</script>
@endauth
