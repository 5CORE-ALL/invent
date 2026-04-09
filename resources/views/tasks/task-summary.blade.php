@extends('layouts.vertical', ['title' => 'Task Summary', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <style>
        .task-summary-table thead th {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
            border-bottom-width: 1px;
        }
        .task-summary-table tbody td {
            vertical-align: middle;
        }
        .task-summary-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid rgba(15, 23, 42, 0.08);
        }
        .task-summary-num {
            font-variant-numeric: tabular-nums;
            font-weight: 600;
        }
        .task-summary-search-wrap {
            max-width: 420px;
        }
        .task-summary-search-wrap .input-group-text {
            background: #f8fafc;
            border-color: #e2e8f0;
            color: #64748b;
        }
        .task-summary-search-wrap .form-control:focus {
            border-color: #94a3b8;
            box-shadow: 0 0 0 0.15rem rgba(100, 116, 139, 0.15);
        }
        .task-summary-th-sort {
            cursor: pointer;
            user-select: none;
            white-space: nowrap;
            transition: background-color 0.12s ease, color 0.12s ease;
        }
        .task-summary-th-sort:hover {
            background-color: #e2e8f0 !important;
            color: #0f172a;
        }
        .task-summary-th-sort.is-sorted {
            color: #0f172a;
        }
        .task-summary-sort-icon {
            font-size: 0.95em;
            opacity: 0.45;
            vertical-align: -0.1em;
            margin-left: 0.15rem;
        }
        .task-summary-th-sort.is-sorted .task-summary-sort-icon {
            opacity: 1;
            color: #0d9488;
        }
    </style>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ url('/') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ url('tasks') }}">Task Manager</a></li>
                        <li class="breadcrumb-item active">Task Summary</li>
                    </ol>
                </div>
                <h4 class="page-title">Task Summary</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-3">
                        Counts are based on tasks assigned to each team member (assignee), using the same visibility rules as Task Manager.
                        <strong>A Task</strong> = tasks in <strong>Todo</strong> status.
                        <strong>Need Approval</strong> = tasks in <strong>Need Approval</strong> status.
                        <strong>Assignor task</strong> = tasks where this member is the assignor (creator).
                    </p>
                    @if (!empty($rows) && count($rows))
                        <div class="task-summary-search-wrap mb-3">
                            <label for="task-summary-search" class="visually-hidden">Search team members</label>
                            <div class="input-group">
                                <span class="input-group-text" aria-hidden="true"><i class="ri-search-line"></i></span>
                                <input type="search"
                                       class="form-control"
                                       id="task-summary-search"
                                       placeholder="Search by name or designation…"
                                       autocomplete="off"
                                       spellcheck="false" />
                            </div>
                        </div>
                    @endif
                    <div class="table-responsive">
                        <table class="table table-hover table-striped table-bordered mb-0 task-summary-table">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col" class="task-summary-th-sort" data-sort-key="member" data-sort-type="text" title="Sort by team member" role="button" tabindex="0">
                                        Team Member <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" class="task-summary-th-sort text-center" style="width: 72px;" data-sort-key="member" data-sort-type="text" title="Sort by team member" role="button" tabindex="0">
                                        Image <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" class="task-summary-th-sort" data-sort-key="designation" data-sort-type="text" title="Sort by designation" role="button" tabindex="0">
                                        Designation <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" class="task-summary-th-sort text-end" data-sort-key="task" data-sort-type="number" title="Sort by assignee task count" role="button" tabindex="0">
                                        Task <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" class="task-summary-th-sort text-end" data-sort-key="assignor_task" data-sort-type="number" title="Sort by assignor task count" role="button" tabindex="0">
                                        Assignor task <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" class="task-summary-th-sort text-end" data-sort-key="overdue" data-sort-type="number" title="Sort by overdue count" role="button" tabindex="0">
                                        Overdue <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" class="task-summary-th-sort text-end" data-sort-key="a_task" data-sort-type="number" title="Sort by A Task count" role="button" tabindex="0">
                                        A Task <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" class="task-summary-th-sort text-end" data-sort-key="need_approval" data-sort-type="number" title="Sort by Need Approval count" role="button" tabindex="0">
                                        Need Approval <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" class="task-summary-th-sort text-end" data-sort-key="done" data-sort-type="number" title="Sort by done count" role="button" tabindex="0">
                                        Done <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $row)
                                    @php
                                        $avatarUrl = !empty($row['avatar'])
                                            ? asset('storage/' . $row['avatar'])
                                            : asset('images/users/avatar-2.jpg');
                                        $searchBlob = strtolower(
                                            trim($row['team_member'] . ' ' . ($row['designation'] ?? ''))
                                        );
                                    @endphp
                                    <tr class="task-summary-row"
                                        data-search="{{ e($searchBlob) }}"
                                        data-sort-member="{{ e($row['team_member']) }}"
                                        data-sort-designation="{{ e($row['designation'] ?? '') }}"
                                        data-sort-task="{{ (int) ($row['task'] ?? 0) }}"
                                        data-sort-assignor_task="{{ (int) ($row['assignor_task'] ?? 0) }}"
                                        data-sort-overdue="{{ (int) ($row['overdue'] ?? 0) }}"
                                        data-sort-a_task="{{ (int) ($row['a_task'] ?? 0) }}"
                                        data-sort-need_approval="{{ (int) ($row['need_approval'] ?? 0) }}"
                                        data-sort-done="{{ (int) ($row['done'] ?? 0) }}">
                                        <td>{{ $row['team_member'] }}</td>
                                        <td class="text-center">
                                            <img src="{{ $avatarUrl }}" alt="" class="task-summary-avatar" width="40" height="40" loading="lazy" />
                                        </td>
                                        <td>{{ $row['designation'] ?: '—' }}</td>
                                        <td class="text-end task-summary-num">{{ $row['task'] }}</td>
                                        <td class="text-end task-summary-num">{{ $row['assignor_task'] }}</td>
                                        <td class="text-end task-summary-num">{{ $row['overdue'] }}</td>
                                        <td class="text-end task-summary-num">{{ $row['a_task'] }}</td>
                                        <td class="text-end task-summary-num">{{ $row['need_approval'] }}</td>
                                        <td class="text-end task-summary-num">{{ $row['done'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">No team members found.</td>
                                    </tr>
                                @endforelse
                                @if (!empty($rows) && count($rows))
                                    <tr id="task-summary-filter-empty" class="d-none">
                                        <td colspan="9" class="text-center text-muted py-4">No matching team members.</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        (function () {
            var input = document.getElementById('task-summary-search');
            var tbody = document.querySelector('.task-summary-table tbody');
            if (!tbody) {
                return;
            }
            var emptyRow = document.getElementById('task-summary-filter-empty');

            function getDataRows() {
                return Array.prototype.slice.call(tbody.querySelectorAll('tr.task-summary-row'));
            }

            function runFilter() {
                if (!input) {
                    return;
                }
                var rows = getDataRows();
                var q = (input.value || '').trim().toLowerCase();
                var shown = 0;
                rows.forEach(function (tr) {
                    var hay = (tr.getAttribute('data-search') || '').toLowerCase();
                    var match = !q || hay.indexOf(q) !== -1;
                    tr.classList.toggle('d-none', !match);
                    if (match) {
                        shown++;
                    }
                });
                if (emptyRow) {
                    emptyRow.classList.toggle('d-none', !(q && shown === 0));
                }
            }

            if (input) {
                input.addEventListener('input', runFilter);
                input.addEventListener('search', runFilter);
            }

            var sortState = { key: null, dir: 'asc' };
            var headers = document.querySelectorAll('.task-summary-th-sort');

            function sortIconClass(dir) {
                return dir === 'asc' ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line';
            }

            function updateHeaderIcons(sortKey, dir) {
                headers.forEach(function (th) {
                    var icon = th.querySelector('.task-summary-sort-icon');
                    if (!icon) {
                        return;
                    }
                    var same = th.getAttribute('data-sort-key') === sortKey;
                    th.classList.toggle('is-sorted', same);
                    if (same) {
                        icon.className = 'task-summary-sort-icon ' + sortIconClass(dir);
                    } else {
                        icon.className = 'task-summary-sort-icon ri-arrow-up-down-line';
                    }
                });
            }

            function compareRows(a, b, key, type) {
                var attr = 'data-sort-' + key;
                var va = a.getAttribute(attr);
                var vb = b.getAttribute(attr);
                if (va === null) {
                    va = '';
                }
                if (vb === null) {
                    vb = '';
                }
                if (type === 'number') {
                    va = parseInt(va, 10) || 0;
                    vb = parseInt(vb, 10) || 0;
                    return va - vb;
                }
                return va.toString().localeCompare(vb.toString(), undefined, { sensitivity: 'base' });
            }

            function applySort() {
                var key = sortState.key;
                var dir = sortState.dir;
                if (!key) {
                    return;
                }
                var activeTh = null;
                headers.forEach(function (th) {
                    if (th.getAttribute('data-sort-key') === key && !activeTh) {
                        activeTh = th;
                    }
                });
                var type = activeTh && activeTh.getAttribute('data-sort-type') === 'number' ? 'number' : 'text';
                var rows = getDataRows();
                rows.sort(function (a, b) {
                    var c = compareRows(a, b, key, type);
                    return dir === 'asc' ? c : -c;
                });
                rows.forEach(function (tr) {
                    tbody.appendChild(tr);
                });
                if (emptyRow) {
                    tbody.appendChild(emptyRow);
                }
            }

            function onSortClick(th) {
                var key = th.getAttribute('data-sort-key');
                if (!key) {
                    return;
                }
                if (sortState.key === key) {
                    sortState.dir = sortState.dir === 'asc' ? 'desc' : 'asc';
                } else {
                    sortState.key = key;
                    sortState.dir = 'asc';
                }
                updateHeaderIcons(key, sortState.dir);
                applySort();
            }

            headers.forEach(function (th) {
                th.addEventListener('click', function () {
                    onSortClick(th);
                });
                th.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        onSortClick(th);
                    }
                });
            });
        })();
    </script>
@endsection
