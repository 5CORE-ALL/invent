@extends('layouts.vertical', ['title' => 'Permission Overview'])

@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    .permission-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }

    .permission-table th {
        background-color: #f8f9fa;
        padding: 15px;
        font-weight: 600;
        text-align: center;
        border-bottom: 2px solid #dee2e6;
    }

    .permission-table td {
        padding: 12px;
        border: 1px solid #dee2e6;
        text-align: center;
    }

    .module-header {
        background-color: #e9ecef;
        font-weight: 600;
    }

    .permission-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.875rem;
    }

    .permission-granted {
        background-color: #d4edda;
        color: #155724;
    }

    .permission-denied {
        background-color: #f8d7da;
        color: #721c24;
    }

    .perm-search { max-width: 280px; }

    .perm-grant-chip .badge { margin-left: 6px; font-weight: 600; cursor: default; }
    .perm-person-list { max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 6px; }
    .perm-person { display: flex; align-items: center; gap: 8px; padding: 6px 10px; border-bottom: 1px solid #f1f3f5; }
    .perm-person:last-child { border-bottom: 0; }
    .perm-person small { color: #6c757d; }
    .perm-person .perm-tag { font-size: 0.7rem; padding: 1px 6px; border-radius: 10px; background: #e9ecef; color: #495057; margin-left: auto; white-space: nowrap; }

    .edit-button {
        position: absolute;
        top: 20px;
        right: 20px;
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body position-relative">
                    <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                        <h4 class="header-title mb-0 me-2">Role Permissions Overview</h4>
                        <span class="badge bg-primary fs-6 px-3 py-2" id="permPageCount" title="Number of pages (modules) listed">
                            Pages: <span id="permPageCountValue">{{ count($modules) }}</span>
                        </span>
                        <span class="badge bg-secondary fs-6 px-3 py-2" id="permSelectedBadge" title="Pages ticked in the first column">
                            Selected: <span id="permSelectedCount">0</span>
                        </span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="permClearSelection" style="display:none;">Clear</button>
                        <button type="button" class="btn btn-sm btn-success" id="permGrantBtn" disabled title="Tick pages in the first column, then choose people">
                            <i class="fas fa-user-plus me-1"></i> Grant access
                        </button>
                        <input type="search" id="permPageSearch" class="form-control perm-search" placeholder="Search pages…" autocomplete="off" aria-label="Search pages">
                    </div>
                    
                    <a href="{{ route('permissions.edit') }}" class="btn btn-primary edit-button">
                        <i class="fas fa-edit me-1"></i> Edit Permissions
                    </a>

                    <div class="table-responsive">
                        <table class="table permission-table">
                            <thead>
                                <tr>
                                    <th style="width:44px;">
                                        <input type="checkbox" id="permSelectAll" class="form-check-input" title="Select all listed pages" aria-label="Select all listed pages">
                                    </th>
                                    <th>Module/Feature</th>
                                    <th>Viewer</th>
                                    <th>User</th>
                                    <th>Manager</th>
                                    <th>Admin</th>
                                    <th>Superadmin</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($modules as $module)
                                    <tr class="module-header perm-row" data-module="{{ strtolower($module) }}">
                                        <td>
                                            <input type="checkbox" class="form-check-input perm-select" value="{{ $module }}" aria-label="Select {{ $module }}">
                                        </td>
                                        <td colspan="6">
                                            {{ ucfirst($module) }}
                                            <span class="perm-grant-chip" data-grant-module="{{ $module }}"></span>
                                        </td>
                                    </tr>
                                    <tr class="perm-row" data-module="{{ strtolower($module) }}">
                                        <td></td>
                                        <td>{{ ucfirst($module) }} Actions</td>
                                        @foreach(['viewer', 'user', 'manager', 'admin', 'superadmin'] as $role)
                                            <td>
                                                @php
                                                    $currentRolePermissions = [];
                                                    if(isset($rolePermissions[$module][$role])) {
                                                        $currentRolePermissions = is_array($rolePermissions[$module][$role]) 
                                                            ? $rolePermissions[$module][$role] 
                                                            : json_decode($rolePermissions[$module][$role], true);
                                                    }
                                                @endphp
                                                
                                                @if(!empty($currentRolePermissions))
                                                    <span class="permission-badge permission-granted">
                                                        <i class="fas fa-check"></i>
                                                        {{ implode(', ', array_map('ucfirst', $currentRolePermissions)) }}
                                                    </span>
                                                @else
                                                    <span class="permission-badge permission-denied">
                                                        <i class="fas fa-times"></i>
                                                        No Access
                                                    </span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                                <tr id="permNoMatch" style="display:none;">
                                    <td colspan="7" class="text-muted py-4">No pages match your search.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('modal')
<div class="modal fade" id="permGrantModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Grant access to selected people</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">
                    Pages: <strong id="permGrantPages">0</strong>.
                    This adds access on top of each person's role. It never removes what their role already allows.
                </p>

                <div class="d-flex flex-wrap gap-3 mb-3">
                    <div>
                        <div class="fw-semibold small mb-1">What to do</div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="permGrantMode" id="permModeGrant" value="grant" checked>
                            <label class="form-check-label" for="permModeGrant">Grant</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="permGrantMode" id="permModeRevoke" value="revoke">
                            <label class="form-check-label" for="permModeRevoke">Take back</label>
                        </div>
                    </div>
                    <div>
                        <div class="fw-semibold small mb-1">Actions</div>
                        @foreach(['view', 'create', 'edit', 'delete'] as $act)
                            <div class="form-check form-check-inline">
                                <input class="form-check-input perm-grant-action" type="checkbox" id="permAct_{{ $act }}" value="{{ $act }}" {{ $act === 'view' ? 'checked' : '' }}>
                                <label class="form-check-label" for="permAct_{{ $act }}">{{ ucfirst($act) }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <select id="permGroupFilter" class="form-select form-select-sm" style="max-width: 190px;" aria-label="Filter people">
                        <option value="all">All people</option>
                        <option value="user">Users</option>
                        <option value="manager">Managers</option>
                        <option value="director">Directors</option>
                    </select>
                    <input type="search" id="permPersonSearch" class="form-control form-control-sm" style="max-width: 240px;" placeholder="Search name or email…" autocomplete="off">
                    <div class="form-check ms-auto mb-0">
                        <input class="form-check-input" type="checkbox" id="permPersonAll">
                        <label class="form-check-label small" for="permPersonAll">Select all shown</label>
                    </div>
                </div>
                <div class="perm-person-list" id="permPersonList"><div class="p-3 text-muted small">Loading people…</div></div>
                <div class="small text-muted mt-2">People selected: <strong id="permPersonCount">0</strong></div>
                <div id="permGrantMsg" class="alert py-2 small mt-3 d-none" role="status"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="permGrantSubmit">Save</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    (function () {
        var input = document.getElementById('permPageSearch');
        var countEl = document.getElementById('permPageCountValue');
        var noMatch = document.getElementById('permNoMatch');
        if (!input || !countEl) return;
        var total = parseInt(countEl.textContent, 10) || 0;

        function apply() {
            var q = input.value.trim().toLowerCase();
            var shown = {};
            document.querySelectorAll('tr.perm-row').forEach(function (row) {
                var name = row.getAttribute('data-module') || '';
                var match = q === '' || name.indexOf(q) !== -1;
                row.style.display = match ? '' : 'none';
                if (match) shown[name] = true;
            });
            var n = Object.keys(shown).length;
            countEl.textContent = q === '' ? String(total) : n + ' of ' + total;
            if (noMatch) noMatch.style.display = (q !== '' && n === 0) ? '' : 'none';
            syncSelection();
        }
        input.addEventListener('input', apply);

        // ---- Multi select ----
        var selectAll = document.getElementById('permSelectAll');
        var selectedCount = document.getElementById('permSelectedCount');
        var clearBtn = document.getElementById('permClearSelection');
        var grantBtn = document.getElementById('permGrantBtn');

        function boxes() { return Array.prototype.slice.call(document.querySelectorAll('.perm-select')); }
        function visibleBoxes() {
            return boxes().filter(function (b) { return b.closest('tr').style.display !== 'none'; });
        }
        function syncSelection() {
            var all = boxes();
            var checked = all.filter(function (b) { return b.checked; });
            selectedCount.textContent = String(checked.length);
            clearBtn.style.display = checked.length ? '' : 'none';
            if (grantBtn) grantBtn.disabled = checked.length === 0;
            var vis = visibleBoxes();
            var visChecked = vis.filter(function (b) { return b.checked; }).length;
            selectAll.checked = vis.length > 0 && visChecked === vis.length;
            selectAll.indeterminate = visChecked > 0 && visChecked < vis.length;
            all.forEach(function (b) { b.closest('tr').classList.toggle('table-active', b.checked); });
        }
        selectAll.addEventListener('change', function () {
            // Only the pages currently shown (respects the search box)
            visibleBoxes().forEach(function (b) { b.checked = selectAll.checked; });
            syncSelection();
        });
        document.addEventListener('change', function (e) {
            if (e.target.classList && e.target.classList.contains('perm-select')) syncSelection();
        });
        clearBtn.addEventListener('click', function () {
            boxes().forEach(function (b) { b.checked = false; });
            syncSelection();
        });
        // For the next step (bulk actions): window.permSelectedModules() → ['dashboard', ...]
        window.permSelectedModules = function () {
            return boxes().filter(function (b) { return b.checked; }).map(function (b) { return b.value; });
        };

        // ---- Grant access to selected people ----
        var grantUsersUrl = @json(route('permissions.grant.users'));
        var grantStoreUrl = @json(route('permissions.grant.store'));
        var csrf = @json(csrf_token());
        var grants = @json($grantsByModule ?? []);
        var people = null;           // loaded once, when the window is first opened
        var pickedPeople = {};       // id -> true (survives filtering)
        var modalEl = document.getElementById('permGrantModal');
        var listEl = document.getElementById('permPersonList');
        var msgEl = document.getElementById('permGrantMsg');

        function esc(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }

        function renderGrantChips() {
            document.querySelectorAll('[data-grant-module]').forEach(function (el) {
                var rows = grants[el.getAttribute('data-grant-module')] || [];
                if (!rows.length) { el.innerHTML = ''; return; }
                var tip = rows.map(function (r) { return r.name + ' (' + r.actions.join(', ') + ')'; }).join('\n');
                el.innerHTML = '<span class="badge bg-info text-dark" title="' + esc(tip) + '">+' + rows.length + ' ' + (rows.length === 1 ? 'person' : 'people') + '</span>';
            });
        }
        renderGrantChips();

        function groupOf(p) {
            var g = [];
            if (p.role === 'user') g.push('user');
            if (p.role === 'manager' || p.org_level === 'mgr') g.push('manager');
            if (p.org_level === 'director') g.push('director');
            return g;
        }
        function personTag(p) {
            var t = p.role || '';
            if (p.org_level) t += (t ? ' · ' : '') + p.org_level;
            return t;
        }

        function shownPeople() {
            if (!people) return [];
            var group = document.getElementById('permGroupFilter').value;
            var q = document.getElementById('permPersonSearch').value.trim().toLowerCase();
            return people.filter(function (p) {
                if (group !== 'all' && groupOf(p).indexOf(group) === -1) return false;
                if (q && (p.name + ' ' + p.email).toLowerCase().indexOf(q) === -1) return false;
                return true;
            });
        }

        function renderPeople() {
            var shown = shownPeople();
            if (!people) return;
            listEl.innerHTML = shown.length ? shown.map(function (p) {
                return '<label class="perm-person mb-0"><input type="checkbox" class="form-check-input perm-person-box" value="' + p.id + '"' +
                    (pickedPeople[p.id] ? ' checked' : '') + '><span>' + esc(p.name) + ' <small>' + esc(p.email) + '</small></span>' +
                    '<span class="perm-tag">' + esc(personTag(p)) + '</span></label>';
            }).join('') : '<div class="p-3 text-muted small">No people match.</div>';
            syncPeople();
        }

        function syncPeople() {
            document.getElementById('permPersonCount').textContent = String(Object.keys(pickedPeople).length);
            var shown = shownPeople();
            var n = shown.filter(function (p) { return pickedPeople[p.id]; }).length;
            var all = document.getElementById('permPersonAll');
            all.checked = shown.length > 0 && n === shown.length;
            all.indeterminate = n > 0 && n < shown.length;
        }

        function showMsg(type, text) {
            msgEl.className = 'alert py-2 small mt-3 alert-' + type;
            msgEl.textContent = text;
        }

        function openGrantModal() {
            var mods = window.permSelectedModules();
            if (!mods.length) return;
            document.getElementById('permGrantPages').textContent = mods.length + (mods.length <= 6 ? ' (' + mods.join(', ') + ')' : '');
            msgEl.className = 'alert py-2 small mt-3 d-none';
            if (!people) {
                fetch(grantUsersUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (d) { people = d.users || []; renderPeople(); })
                    .catch(function () { listEl.innerHTML = '<div class="p-3 text-danger small">Could not load people.</div>'; });
            } else {
                renderPeople();
            }
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }

        grantBtn.addEventListener('click', openGrantModal);
        document.getElementById('permGroupFilter').addEventListener('change', renderPeople);
        document.getElementById('permPersonSearch').addEventListener('input', renderPeople);
        document.getElementById('permPersonAll').addEventListener('change', function (e) {
            shownPeople().forEach(function (p) {
                if (e.target.checked) pickedPeople[p.id] = true; else delete pickedPeople[p.id];
            });
            renderPeople();
        });
        listEl.addEventListener('change', function (e) {
            if (!e.target.classList.contains('perm-person-box')) return;
            if (e.target.checked) pickedPeople[e.target.value] = true; else delete pickedPeople[e.target.value];
            syncPeople();
        });

        document.getElementById('permGrantSubmit').addEventListener('click', function () {
            var modules = window.permSelectedModules();
            var userIds = Object.keys(pickedPeople).map(Number);
            var actions = Array.prototype.slice.call(document.querySelectorAll('.perm-grant-action:checked')).map(function (b) { return b.value; });
            var mode = document.querySelector('input[name="permGrantMode"]:checked').value;
            if (!modules.length) return showMsg('warning', 'Tick at least one page first.');
            if (!userIds.length) return showMsg('warning', 'Choose at least one person.');
            if (!actions.length) return showMsg('warning', 'Choose at least one action.');
            if (mode === 'revoke' && !window.confirm('Take back these actions for ' + userIds.length + ' person(s) on ' + modules.length + ' page(s)?')) return;

            var btn = this;
            btn.disabled = true;
            showMsg('info', 'Saving…');
            fetch(grantStoreUrl, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                body: JSON.stringify({ mode: mode, modules: modules, user_ids: userIds, actions: actions })
            }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
              .then(function (res) {
                  btn.disabled = false;
                  if (!res.ok || !res.d.success) {
                      var errs = res.d && res.d.errors ? Object.values(res.d.errors).flat().join(' ') : '';
                      return showMsg('danger', (res.d && res.d.message) || errs || 'Could not save.');
                  }
                  grants = res.d.grants || {};
                  renderGrantChips();
                  showMsg('success', res.d.message);
              })
              .catch(function () { btn.disabled = false; showMsg('danger', 'Could not save. Please try again.'); });
        });
    })();
</script>
@endsection