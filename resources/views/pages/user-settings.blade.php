@extends('layouts.vertical', ['title' => 'User Settings'])

@section('css')
<style>
    .us-page { max-width: 1100px; margin: 0 auto; }
    .us-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
        margin-bottom: 16px;
    }
    .us-search {
        flex: 1 1 280px;
        position: relative;
    }
    .us-search input {
        padding-left: 2.25rem;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        height: 40px;
    }
    .us-search i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
    }
    .us-filters .btn {
        border-radius: 8px;
        font-size: 0.8rem;
        padding: 0.35rem 0.75rem;
    }
    .us-card {
        background: #fff;
        border: 1px solid #e8edf3;
        border-radius: 10px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        padding: 14px 18px;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .us-card.is-inactive { opacity: 0.82; background: #fafbfc; }
    .us-card.is-deleted { opacity: 0.7; background: #fff8f8; }
    .us-card .us-check { flex-shrink: 0; margin-top: 2px; }
    .us-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #e8eef7;
        color: #3b5bdb;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.8rem;
        flex-shrink: 0;
        overflow: hidden;
        object-fit: cover;
    }
    .us-identity { flex: 1 1 auto; min-width: 0; }
    .us-id-line {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        font-weight: 700;
        color: #1e293b;
        line-height: 1.3;
    }
    .us-name { color: #334155; font-weight: 600; }
    .us-admin-badge {
        display: inline-block;
        background: #e11d48;
        color: #fff;
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        padding: 2px 7px;
        border-radius: 3px;
        line-height: 1.3;
    }
    .us-email {
        color: #64748b;
        font-size: 0.85rem;
        margin-top: 2px;
    }
    .us-status-pill {
        font-size: 0.72rem;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 999px;
        margin-left: 4px;
    }
    .us-status-pill.active { background: #dcfce7; color: #166534; }
    .us-status-pill.inactive { background: #fef9c3; color: #854d0e; }
    .us-status-pill.deleted { background: #fee2e2; color: #991b1b; }
    .us-status-pill.na { background: #e2e8f0; color: #475569; }
    .us-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 4px 2px;
        justify-content: flex-end;
        flex-shrink: 0;
    }
    .us-actions .us-link,
    .us-actions .btn-link {
        color: #2563eb;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        text-decoration: none;
        padding: 4px 8px;
        border: 0;
        background: transparent;
    }
    .us-actions .us-link:hover,
    .us-actions .btn-link:hover { color: #1d4ed8; text-decoration: underline; }
    .us-bulk {
        display: none;
        align-items: center;
        gap: 10px;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 8px;
        padding: 8px 12px;
        margin-bottom: 12px;
        font-size: 0.85rem;
    }
    .us-bulk.is-on { display: flex; }
    .us-empty { text-align: center; color: #64748b; padding: 40px 16px; }
    @media (max-width: 768px) {
        .us-card { flex-wrap: wrap; }
        .us-actions { width: 100%; justify-content: flex-start; }
    }
</style>
@endsection

@section('content')
<div class="container-fluid us-page">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h3 class="mb-1">User Settings</h3>
            <p class="text-muted mb-0">Activate or deactivate accounts for inventory and Team Monitoring. Restricted to HR, President, and Tech Support.</p>
        </div>
    </div>

    <div class="us-toolbar">
        <div class="us-search">
            <i class="ri-search-line"></i>
            <input type="search" id="us-search" class="form-control" placeholder="Search by name or email">
        </div>
        <div class="us-filters btn-group" role="group">
            <button type="button" class="btn btn-primary us-filter" data-filter="all">All</button>
            <button type="button" class="btn btn-outline-secondary us-filter" data-filter="active">Active</button>
            <button type="button" class="btn btn-outline-secondary us-filter" data-filter="inactive">Inactive</button>
            <button type="button" class="btn btn-outline-secondary us-filter" data-filter="deleted">Deleted</button>
        </div>
        <label class="mb-0 small text-muted d-flex align-items-center gap-2">
            <input type="checkbox" id="us-select-all"> Select all
        </label>
    </div>

    <div id="us-bulk" class="us-bulk">
        <span id="us-bulk-count">0 selected</span>
        <button type="button" class="btn btn-sm btn-success" id="us-bulk-activate">Activate</button>
        <button type="button" class="btn btn-sm btn-warning" id="us-bulk-deactivate">Deactivate</button>
    </div>

    <div id="us-list"></div>
    <div id="us-empty" class="us-empty d-none">No users match this filter.</div>
</div>

{{-- Profile --}}
<div class="modal fade" id="us-profile-modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="us-profile-body"></div>
        </div>
    </div>
</div>

{{-- Settings --}}
<div class="modal fade" id="us-settings-modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="us-settings-form">
                <div class="modal-header">
                    <h5 class="modal-title">Settings</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="us-set-id">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" name="name" id="us-set-name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Designation</label>
                        <input type="text" class="form-control" name="designation" id="us-set-designation">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" class="form-control" name="phone" id="us-set-phone">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="role" id="us-set-role">
                            @foreach ($roles as $role)
                                <option value="{{ $role }}">{{ ucfirst($role) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="stay_logged_in" value="1" id="us-set-stay">
                        <label class="form-check-label" for="us-set-stay">Stay logged in (skip auto-logout)</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Credentials --}}
<div class="modal fade" id="us-cred-modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="us-cred-form">
                <div class="modal-header">
                    <h5 class="modal-title">Credentials</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="us-cred-id">
                    <p class="text-muted small">Set a new password or generate one. The user is signed out of inventory and the attendance desktop agent.</p>
                    <div class="mb-3">
                        <label class="form-label">New password</label>
                        <input type="password" class="form-control" name="password" id="us-cred-password" minlength="8" autocomplete="new-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm password</label>
                        <input type="password" class="form-control" name="password_confirmation" id="us-cred-password2" minlength="8" autocomplete="new-password">
                    </div>
                    <div id="us-cred-generated" class="alert alert-success d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" id="us-cred-generate">Generate password</button>
                    <button type="submit" class="btn btn-primary">Save password</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
(function () {
    const USERS = @json($users);
    const CURRENT_ID = {{ (int) $currentUserId }};
    const CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const listEl = document.getElementById('us-list');
    const emptyEl = document.getElementById('us-empty');
    const bulkEl = document.getElementById('us-bulk');
    const bulkCountEl = document.getElementById('us-bulk-count');
    let filter = 'all';
    let query = '';
    const selected = new Set();
    const byId = {};
    USERS.forEach(function (u) { byId[u.id] = u; });

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function visibleUsers() {
        const q = query.trim().toLowerCase();
        return USERS.filter(function (u) {
            if (filter !== 'all' && u.status !== filter) return false;
            if (!q) return true;
            return (u.name || '').toLowerCase().includes(q)
                || (u.email || '').toLowerCase().includes(q)
                || (u.designation || '').toLowerCase().includes(q);
        });
    }

    function avatarHtml(u) {
        if (u.avatar_url) {
            return '<img class="us-avatar" src="' + esc(u.avatar_url) + '" alt="">';
        }
        return '<span class="us-avatar">' + esc(u.initials) + '</span>';
    }

    function cardHtml(u) {
        const checked = selected.has(u.id) ? ' checked' : '';
        const cls = 'us-card' + (u.status === 'inactive' ? ' is-inactive' : '') + (u.status === 'deleted' ? ' is-deleted' : '');
        const admin = u.is_admin ? '<span class="us-admin-badge">ADMIN</span>' : '';
        const status = '<span class="us-status-pill ' + esc(u.status) + '">' + esc(u.status_label) + '</span>';
        const activateLabel = (u.status === 'active') ? 'Deactivate' : 'Activate';
        return (
            '<div class="' + cls + '" data-id="' + u.id + '">'
            + '<input type="checkbox" class="form-check-input us-check"' + checked + ' data-id="' + u.id + '">'
            + avatarHtml(u)
            + '<div class="us-identity">'
            + '<div class="us-id-line">'
            + '<span>' + esc(u.email) + '</span>'
            + '<span class="us-name">' + esc(u.name || '') + '</span>'
            + admin + status
            + '</div>'
            + '<div class="us-email">' + esc(u.email) + (u.designation ? ' · ' + esc(u.designation) : '') + '</div>'
            + '</div>'
            + '<div class="us-actions">'
            + '<button type="button" class="us-link" data-act="profile" data-id="' + u.id + '">Profile</button>'
            + '<button type="button" class="us-link" data-act="settings" data-id="' + u.id + '">Settings</button>'
            + '<button type="button" class="us-link" data-act="credentials" data-id="' + u.id + '">Credentials</button>'
            + '<div class="dropdown d-inline">'
            + '<button type="button" class="us-link dropdown-toggle" data-bs-toggle="dropdown">Actions</button>'
            + '<ul class="dropdown-menu dropdown-menu-end">'
            + '<li><button type="button" class="dropdown-item" data-act="toggle" data-id="' + u.id + '">' + activateLabel + '</button></li>'
            + '<li><button type="button" class="dropdown-item" data-act="kick" data-id="' + u.id + '">Sign out now</button></li>'
            + '<li><hr class="dropdown-divider"></li>'
            + '<li><button type="button" class="dropdown-item text-danger" data-act="delete" data-id="' + u.id + '">Delete</button></li>'
            + '</ul></div>'
            + '</div></div>'
        );
    }

    function render() {
        const rows = visibleUsers();
        listEl.innerHTML = rows.map(cardHtml).join('');
        emptyEl.classList.toggle('d-none', rows.length > 0);
        updateBulk();
    }

    function updateBulk() {
        const n = selected.size;
        bulkEl.classList.toggle('is-on', n > 0);
        bulkCountEl.textContent = n + ' selected';
    }

    function mergeUser(u) {
        if (!u || !u.id) return;
        const idx = USERS.findIndex(function (x) { return x.id === u.id; });
        if (idx >= 0) USERS[idx] = u;
        else USERS.push(u);
        byId[u.id] = u;
        render();
    }

    async function api(url, opts) {
        const res = await fetch(url, Object.assign({
            headers: {
                'X-CSRF-TOKEN': CSRF,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        }, opts || {}));
        const data = await res.json().catch(function () { return {}; });
        if (!res.ok || data.success === false) {
            throw new Error(data.message || 'Request failed');
        }
        return data;
    }

    function toast(msg, ok) {
        if (window.toastr) {
            (ok === false ? toastr.error : toastr.success)(msg);
            return;
        }
        alert(msg);
    }

    document.getElementById('us-search').addEventListener('input', function (e) {
        query = e.target.value;
        render();
    });

    document.querySelectorAll('.us-filter').forEach(function (btn) {
        btn.addEventListener('click', function () {
            filter = btn.getAttribute('data-filter');
            document.querySelectorAll('.us-filter').forEach(function (b) {
                b.classList.toggle('btn-primary', b === btn);
                b.classList.toggle('btn-outline-secondary', b !== btn);
            });
            render();
        });
    });

    document.getElementById('us-select-all').addEventListener('change', function (e) {
        const rows = visibleUsers();
        if (e.target.checked) rows.forEach(function (u) { selected.add(u.id); });
        else rows.forEach(function (u) { selected.delete(u.id); });
        render();
    });

    listEl.addEventListener('change', function (e) {
        const cb = e.target.closest('.us-check');
        if (!cb) return;
        const id = Number(cb.getAttribute('data-id'));
        if (cb.checked) selected.add(id);
        else selected.delete(id);
        updateBulk();
    });

    listEl.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-act]');
        if (!btn) return;
        const id = Number(btn.getAttribute('data-id'));
        const act = btn.getAttribute('data-act');
        const u = byId[id];
        if (!u) return;
        if (act === 'profile') openProfile(u);
        if (act === 'settings') openSettings(u);
        if (act === 'credentials') openCred(u);
        if (act === 'toggle') toggleStatus(u);
        if (act === 'kick') kickUser(u);
        if (act === 'delete') deleteUser(u);
    });

    function openProfile(u) {
        document.getElementById('us-profile-body').innerHTML =
            '<div class="d-flex align-items-center gap-3 mb-3">' + avatarHtml(u)
            + '<div><strong>' + esc(u.name) + '</strong><div class="text-muted">' + esc(u.email) + '</div></div></div>'
            + '<dl class="row mb-0">'
            + '<dt class="col-4">Phone</dt><dd class="col-8">' + esc(u.phone || '—') + '</dd>'
            + '<dt class="col-4">Designation</dt><dd class="col-8">' + esc(u.designation || '—') + '</dd>'
            + '<dt class="col-4">Role</dt><dd class="col-8">' + esc(u.role) + '</dd>'
            + '<dt class="col-4">Joined</dt><dd class="col-8">' + esc(u.date_of_joining || '—') + '</dd>'
            + '<dt class="col-4">Status</dt><dd class="col-8">' + esc(u.status_label) + '</dd>'
            + '</dl>';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('us-profile-modal')).show();
    }

    function openSettings(u) {
        document.getElementById('us-set-id').value = u.id;
        document.getElementById('us-set-name').value = u.name || '';
        document.getElementById('us-set-designation').value = u.designation || '';
        document.getElementById('us-set-phone').value = u.phone || '';
        document.getElementById('us-set-role').value = u.role || 'user';
        document.getElementById('us-set-stay').checked = !!u.stay_logged_in;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('us-settings-modal')).show();
    }

    function openCred(u) {
        document.getElementById('us-cred-id').value = u.id;
        document.getElementById('us-cred-password').value = '';
        document.getElementById('us-cred-password2').value = '';
        const box = document.getElementById('us-cred-generated');
        box.classList.add('d-none');
        box.textContent = '';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('us-cred-modal')).show();
    }

    async function toggleStatus(u) {
        if (u.id === CURRENT_ID && u.status === 'active') {
            toast('You cannot deactivate your own account.', false);
            return;
        }
        const activate = u.status !== 'active';
        const url = activate
            ? '/settings/users/' + u.id + '/activate'
            : '/settings/users/' + u.id + '/deactivate';
        try {
            const data = await api(url, { method: 'POST' });
            mergeUser(data.user);
            toast(data.message);
        } catch (err) {
            toast(err.message, false);
        }
    }

    async function kickUser(u) {
        try {
            const data = await api('/settings/users/' + u.id + '/kick', { method: 'POST' });
            mergeUser(data.user);
            toast(data.message);
        } catch (err) {
            toast(err.message, false);
        }
    }

    async function deleteUser(u) {
        if (u.id === CURRENT_ID) {
            toast('You cannot delete your own account.', false);
            return;
        }
        if (!confirm('Delete ' + u.email + '? They will lose inventory and Team Monitoring access.')) return;
        try {
            const data = await api('/settings/users/' + u.id, { method: 'DELETE' });
            mergeUser(data.user);
            toast(data.message);
        } catch (err) {
            toast(err.message, false);
        }
    }

    document.getElementById('us-settings-form').addEventListener('submit', async function (e) {
        e.preventDefault();
        const id = document.getElementById('us-set-id').value;
        try {
            const data = await api('/settings/users/' + id, {
                method: 'PUT',
                body: JSON.stringify({
                    name: document.getElementById('us-set-name').value,
                    designation: document.getElementById('us-set-designation').value,
                    phone: document.getElementById('us-set-phone').value,
                    role: document.getElementById('us-set-role').value,
                    stay_logged_in: document.getElementById('us-set-stay').checked,
                }),
            });
            mergeUser(data.user);
            bootstrap.Modal.getInstance(document.getElementById('us-settings-modal')).hide();
            toast(data.message);
        } catch (err) {
            toast(err.message, false);
        }
    });

    document.getElementById('us-cred-form').addEventListener('submit', async function (e) {
        e.preventDefault();
        await savePassword(false);
    });
    document.getElementById('us-cred-generate').addEventListener('click', async function () {
        await savePassword(true);
    });

    async function savePassword(generate) {
        const id = document.getElementById('us-cred-id').value;
        const payload = generate
            ? { generate: true }
            : {
                password: document.getElementById('us-cred-password').value,
                password_confirmation: document.getElementById('us-cred-password2').value,
            };
        try {
            const data = await api('/settings/users/' + id + '/password', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            mergeUser(data.user);
            const box = document.getElementById('us-cred-generated');
            box.classList.remove('d-none');
            box.textContent = 'Password: ' + (data.generated_password || '');
            toast(data.message);
        } catch (err) {
            toast(err.message, false);
        }
    }

    async function bulk(action) {
        const ids = Array.from(selected);
        if (!ids.length) return;
        try {
            const data = await api('/settings/users/bulk', {
                method: 'POST',
                body: JSON.stringify({ ids: ids, action: action }),
            });
            selected.clear();
            toast(data.message);
            window.location.reload();
        } catch (err) {
            toast(err.message, false);
        }
    }
    document.getElementById('us-bulk-activate').addEventListener('click', function () { bulk('activate'); });
    document.getElementById('us-bulk-deactivate').addEventListener('click', function () { bulk('deactivate'); });

    render();
})();
</script>
@endsection
