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
                                        <td colspan="6">{{ ucfirst($module) }}</td>
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

        function boxes() { return Array.prototype.slice.call(document.querySelectorAll('.perm-select')); }
        function visibleBoxes() {
            return boxes().filter(function (b) { return b.closest('tr').style.display !== 'none'; });
        }
        function syncSelection() {
            var all = boxes();
            var checked = all.filter(function (b) { return b.checked; });
            selectedCount.textContent = String(checked.length);
            clearBtn.style.display = checked.length ? '' : 'none';
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
    })();
</script>
@endsection