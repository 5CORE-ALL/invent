@extends('layouts.vertical', ['title' => 'Scope of Improvement', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        /* Tabulator header — teal pill so it matches the DAR design. */
        #soiTable .tabulator-header {
            background: #1abc9c;
        }

        #soiTable .tabulator-header .tabulator-col {
            background: #1abc9c;
            border-right: 1px solid rgba(255, 255, 255, 0.25);
        }

        #soiTable .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            color: #000000;
            font-weight: 700;
            font-size: 13px;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }

        #soiTable .tabulator-header .tabulator-col .tabulator-col-content {
            padding: 10px 14px;
        }

        #soiTable .tabulator-col .tabulator-arrow {
            border-bottom-color: #000000 !important;
            border-top-color: #000000 !important;
        }

        #soiTable .tabulator-cell {
            padding: 8px 14px !important;
            white-space: normal !important;
        }

        /* User "pill" — light blue background, blue bold text. */
        .soi-user-pill {
            display: inline-block;
            padding: 4px 14px;
            background: #e7f1ff;
            color: #0d6efd;
            border-radius: 14px;
            font-size: 13px;
            font-weight: 700;
        }

        .soi-text-cell {
            font-size: 12px;
            color: #212529;
            line-height: 1.4;
        }

        /* Action buttons. */
        .soi-action-btn {
            background: transparent;
            border: 0;
            padding: 4px 6px;
            cursor: pointer;
            font-size: 15px;
            line-height: 1;
            border-radius: 6px;
            transition: background 0.12s ease, color 0.12s ease, transform 0.12s ease;
        }
        .soi-action-btn.is-edit { color: #0d6efd; }
        .soi-action-btn.is-edit:hover { background: #e7f1ff; transform: scale(1.08); }
        .soi-action-btn.is-delete { color: #dc2626; }
        .soi-action-btn.is-delete:hover { background: #fee2e2; transform: scale(1.08); }

        /* History box in the modal. */
        .soi-history-box {
            max-height: 180px;
            overflow-y: auto;
            background: #f8f9fa;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 8px 10px;
        }
        .soi-history-item {
            font-size: 0.8rem;
            padding: 3px 0;
            border-bottom: 1px dashed #e5e7eb;
        }
        .soi-history-item:last-child { border-bottom: none; }

        /* Floating round movable buttons */
        .soi-fab {
            position: fixed;
            right: 32px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            color: #fff;
            border: none;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            cursor: grab;
            z-index: 1050;
            transition: transform 0.15s ease, background 0.15s ease;
            user-select: none;
            touch-action: none;
        }
        .soi-fab.dragging { cursor: grabbing; transform: scale(1.1); opacity: 0.92; }

        /* Add Issue — blue, sits above */
        #soiFab { bottom: 104px; background: #2563eb; box-shadow: 0 6px 18px rgba(37, 99, 235, 0.45); }
        #soiFab:hover { background: #1d4ed8; transform: scale(1.06); }

        /* My Progress — green, bottom anchor */
        #soiProgressFab { bottom: 32px; background: #16a34a; box-shadow: 0 6px 18px rgba(22, 163, 74, 0.45); }
        #soiProgressFab:hover { background: #15803d; transform: scale(1.06); }

        /* Picker list item for "My Progress". */
        .soi-pick-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px 12px;
            margin-bottom: 8px;
            background: #fcfdff;
        }
        .soi-pick-item .soi-pick-issue { font-size: 13px; color: #212529; }
    </style>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="page-title mb-0">
                    <i class="ri-lightbulb-flash-line me-2 text-primary"></i>Scope of Improvement
                </h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="javascript:void(0);">Purchase Master</a></li>
                        <li class="breadcrumb-item active">Scope of Improvement</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="header-title mb-0">Scope of Improvement</h4>
                    @if ($canAddIssue)
                        <button type="button" class="btn btn-sm btn-primary" id="soiAddBtn">
                            <i class="fas fa-plus me-1"></i> Add Issue
                        </button>
                    @endif
                </div>
                <div class="card-body">
                    <div id="soiTable"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Floating movable round buttons --}}
    @if ($canAddIssue)
        <button type="button" id="soiFab" class="soi-fab" title="Add Issue">
            <i class="fas fa-lightbulb"></i>
        </button>
    @endif

    {{-- My Progress badge (assigned users update their own root cause / fixing root cause) --}}
    <button type="button" id="soiProgressFab" class="soi-fab" title="My Progress">
        <i class="fas fa-person-running"></i>
    </button>

    {{-- Add / Edit modal --}}
    <div class="modal fade" id="soiModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
            <form class="modal-content" id="soiForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="soiModalTitle">Add Scope of Improvement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="soi_id" name="id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="soi_user_id" class="form-label">User <span class="text-danger">*</span></label>
                            <select name="user_id" id="soi_user_id" class="form-select" required>
                                <option value="">Select user</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="soi_issue" class="form-label">Issue</label>
                            <textarea name="issue" id="soi_issue" class="form-control" rows="2" placeholder="Describe the issue"></textarea>
                        </div>
                        <div class="col-12">
                            <label for="soi_root_cause" class="form-label">Root Cause</label>
                            <textarea name="root_cause" id="soi_root_cause" class="form-control" rows="2" placeholder="What is the root cause?"></textarea>
                        </div>
                        <div class="col-12">
                            <label for="soi_fixing_root_cause" class="form-label">Fixing Root Cause</label>
                            <textarea name="fixing_root_cause" id="soi_fixing_root_cause" class="form-control" rows="2" placeholder="How will the root cause be fixed?"></textarea>
                        </div>
                    </div>

                    {{-- History at the bottom --}}
                    <div class="mt-4">
                        <label class="form-label mb-1"><i class="fas fa-history me-1"></i> History Update</label>
                        <div class="soi-history-box" id="soiHistoryBox">
                            <div class="text-muted soi-history-item">No history yet.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <a href="{{ route('scope-of-improvement.index') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-table-list me-1"></i> See All
                    </a>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="soiSaveBtn">Save</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- My Progress picker modal --}}
    <div class="modal fade" id="soiProgressPicker" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-person-running me-1 text-success"></i> My Progress</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">Pick an issue assigned to you to update its root cause and how you're fixing it.</p>
                    <div id="soiProgressList"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-after-vite')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        $(function () {
            const csrf       = $('meta[name="csrf-token"]').attr('content');
            const dataUrl    = @json(route('scope-of-improvement.data'));
            const storeUrl   = @json(route('scope-of-improvement.store'));
            const updateBase = @json(url('scope-of-improvement/update'));
            const deleteBase = @json(url('scope-of-improvement/delete'));
            const isPresident   = @json($canAddIssue);
            const currentUserId = @json($currentUserId);

            const modalEl = document.getElementById('soiModal');
            const modal   = new bootstrap.Modal(modalEl);
            const form    = document.getElementById('soiForm');
            let progressMode = false; // "My Progress" edit: only root cause + fixing root cause

            function esc(s) {
                const d = document.createElement('div');
                d.textContent = (s == null ? '' : String(s));
                return d.innerHTML;
            }

            // ---- Column formatters ----
            function userFormatter(cell) {
                const value = (cell.getValue() ?? '').toString();
                if (!value) return '<span class="text-muted">&mdash;</span>';
                return `<span class="soi-user-pill">${esc(value)}</span>`;
            }

            function textFormatter(cell) {
                const value = (cell.getValue() ?? '').toString();
                if (!value) return '<span class="text-muted">&mdash;</span>';
                return `<span class="soi-text-cell">${esc(value)}</span>`;
            }

            function actionFormatter() {
                return `
                    <button type="button" class="soi-action-btn is-edit soi-edit" title="Edit">
                        <i class="fas fa-pen"></i>
                    </button>
                    <button type="button" class="soi-action-btn is-delete soi-delete" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>`;
            }

            // ---- Build the Tabulator table ----
            const table = new Tabulator('#soiTable', {
                ajaxURL: dataUrl,
                ajaxResponse: function (url, params, response) {
                    return (response && response.data) ? response.data : [];
                },
                layout: 'fitColumns',
                rowHeight: 52,
                pagination: 'local',
                paginationSize: 25,
                paginationSizeSelector: [10, 25, 50, 100],
                placeholder: 'No records yet. Click the floating button to add one.',
                columns: [
                    {
                        title: '#',
                        formatter: 'rownum',
                        width: 60,
                        hozAlign: 'center',
                        vertAlign: 'middle',
                        headerSort: false,
                    },
                    {
                        title: 'User',
                        field: 'user_name',
                        minWidth: 160,
                        widthGrow: 1,
                        hozAlign: 'left',
                        vertAlign: 'middle',
                        headerSort: true,
                        formatter: userFormatter,
                    },
                    {
                        title: 'Issue',
                        field: 'issue',
                        minWidth: 200,
                        widthGrow: 2,
                        hozAlign: 'left',
                        vertAlign: 'middle',
                        headerSort: false,
                        formatter: textFormatter,
                    },
                    {
                        title: 'Root Cause',
                        field: 'root_cause',
                        minWidth: 200,
                        widthGrow: 2,
                        hozAlign: 'left',
                        vertAlign: 'middle',
                        headerSort: false,
                        formatter: textFormatter,
                    },
                    {
                        title: 'Fixing Root Cause',
                        field: 'fixing_root_cause',
                        minWidth: 200,
                        widthGrow: 2,
                        hozAlign: 'left',
                        vertAlign: 'middle',
                        headerSort: false,
                        formatter: textFormatter,
                    },
                    {
                        title: 'Last Updated',
                        field: 'updated_at',
                        width: 150,
                        hozAlign: 'center',
                        vertAlign: 'middle',
                        headerSort: true,
                    },
                    {
                        title: 'Action',
                        field: '_action',
                        width: 110,
                        hozAlign: 'center',
                        vertAlign: 'middle',
                        headerSort: false,
                        formatter: actionFormatter,
                        cellClick: function (e, cell) {
                            const data = cell.getRow().getData();
                            if (e.target.closest('.soi-edit')) {
                                openModal(data, isPresident ? 'edit' : 'progress');
                            } else if (e.target.closest('.soi-delete')) {
                                deleteRow(data.id);
                            }
                        },
                    },
                ],
            });
            window.__soiTable = table;

            function reloadTable() {
                table.replaceData();
            }

            // ---- History rendering ----
            function renderHistory(history) {
                const box = document.getElementById('soiHistoryBox');
                if (!history || !history.length) {
                    box.innerHTML = '<div class="text-muted soi-history-item">No history yet.</div>';
                    return;
                }
                box.innerHTML = history.slice().reverse().map(function (h) {
                    return '<div class="soi-history-item">' +
                        '<strong>' + esc(h.email || '—') + '</strong> ' +
                        '<span class="badge bg-soft-secondary text-secondary">' + esc(h.action || '') + '</span> ' +
                        '<span class="text-muted">' + esc(h.at || '') + '</span>' +
                        '</div>';
                }).join('');
            }

            // Lock User + Issue when in "My Progress" mode; only the two
            // root-cause fields stay editable.
            function applyProgressLock(on) {
                const userSel = document.getElementById('soi_user_id');
                const issue   = document.getElementById('soi_issue');
                userSel.disabled = on;
                issue.readOnly = on;
                if (on) {
                    issue.classList.add('bg-light');
                } else {
                    issue.classList.remove('bg-light');
                }
            }

            // ---- Modal open / close ----
            // mode: 'add' | 'edit' | 'progress'
            function openModal(row, mode) {
                form.reset();
                mode = mode || (row && row.id ? 'edit' : 'add');
                progressMode = (mode === 'progress');

                if (row && row.id) {
                    document.getElementById('soiModalTitle').textContent =
                        progressMode ? 'My Progress — Update' : 'Edit Scope of Improvement';
                    document.getElementById('soi_id').value = row.id;
                    document.getElementById('soi_user_id').value = row.user_id || '';
                    document.getElementById('soi_issue').value = row.issue || '';
                    document.getElementById('soi_root_cause').value = row.root_cause || '';
                    document.getElementById('soi_fixing_root_cause').value = row.fixing_root_cause || '';
                    renderHistory(row.history);
                } else {
                    document.getElementById('soiModalTitle').textContent = 'Add Scope of Improvement';
                    document.getElementById('soi_id').value = '';
                    document.getElementById('soi_user_id').value = '';
                    renderHistory([]);
                }

                applyProgressLock(progressMode);
                modal.show();
            }

            function deleteRow(id) {
                if (!id || !confirm('Delete this record?')) return;
                $.post(deleteBase + '/' + id, { _token: csrf }, function () {
                    reloadTable();
                }).fail(function () {
                    alert('Could not delete the record.');
                });
            }

            const addBtn = document.getElementById('soiAddBtn');
            if (addBtn) addBtn.addEventListener('click', function () { openModal(null); });

            // ---- Save (create or update) ----
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                const id  = document.getElementById('soi_id').value;
                const url = id ? (updateBase + '/' + id) : storeUrl;
                const btn = document.getElementById('soiSaveBtn');

                let payload;
                if (progressMode) {
                    // Only the two root-cause fields are sent; server keeps the rest.
                    payload = {
                        _token: csrf,
                        root_cause: document.getElementById('soi_root_cause').value,
                        fixing_root_cause: document.getElementById('soi_fixing_root_cause').value,
                    };
                } else {
                    const userId = document.getElementById('soi_user_id').value;
                    if (!userId) {
                        alert('Please select a user.');
                        return;
                    }
                    payload = {
                        _token: csrf,
                        user_id: userId,
                        issue: document.getElementById('soi_issue').value,
                        root_cause: document.getElementById('soi_root_cause').value,
                        fixing_root_cause: document.getElementById('soi_fixing_root_cause').value,
                    };
                }

                btn.disabled = true;
                $.post(url, payload, function () {
                    modal.hide();
                    reloadTable();
                }).fail(function (xhr) {
                    let msg = 'Something went wrong.';
                    if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                    alert(msg);
                }).always(function () {
                    btn.disabled = false;
                });
            });

            // ---- My Progress picker ----
            const progressPickerEl = document.getElementById('soiProgressPicker');
            const progressPicker   = new bootstrap.Modal(progressPickerEl);

            function openMyProgress() {
                $.getJSON(dataUrl, function (res) {
                    const rows = ((res && res.data) ? res.data : [])
                        .filter(function (r) { return String(r.user_id) === String(currentUserId); });

                    if (!rows.length) {
                        alert('No issues are assigned to you yet.');
                        return;
                    }
                    if (rows.length === 1) {
                        openModal(rows[0], 'progress');
                        return;
                    }

                    const list = document.getElementById('soiProgressList');
                    list.innerHTML = rows.map(function (r) {
                        return '<div class="soi-pick-item">' +
                            '<span class="soi-pick-issue">' + (esc(r.issue) || '<span class="text-muted">(no issue text)</span>') + '</span>' +
                            '<button type="button" class="btn btn-sm btn-success soi-pick-go" ' +
                            'data-row=\'' + esc(JSON.stringify(r)) + '\'>Update</button>' +
                            '</div>';
                    }).join('');
                    progressPicker.show();
                }).fail(function () {
                    alert('Could not load your issues.');
                });
            }

            $(document).on('click', '.soi-pick-go', function () {
                let row = {};
                try { row = JSON.parse(this.getAttribute('data-row')); } catch (e) {}
                progressPicker.hide();
                openModal(row, 'progress');
            });

            // ---- Make a floating button draggable; click (no drag) runs onClick ----
            function makeDraggable(fab, onClick) {
                if (!fab) return;
                let dragging = false, moved = false, startX = 0, startY = 0, origX = 0, origY = 0;

                function onDown(e) {
                    dragging = true;
                    moved = false;
                    fab.classList.add('dragging');
                    const pt = e.touches ? e.touches[0] : e;
                    startX = pt.clientX;
                    startY = pt.clientY;
                    const rect = fab.getBoundingClientRect();
                    origX = rect.left;
                    origY = rect.top;
                    e.preventDefault();
                }

                function onMove(e) {
                    if (!dragging) return;
                    const pt = e.touches ? e.touches[0] : e;
                    const dx = pt.clientX - startX;
                    const dy = pt.clientY - startY;
                    if (Math.abs(dx) > 3 || Math.abs(dy) > 3) moved = true;
                    const nx = Math.min(window.innerWidth - fab.offsetWidth, Math.max(0, origX + dx));
                    const ny = Math.min(window.innerHeight - fab.offsetHeight, Math.max(0, origY + dy));
                    fab.style.left = nx + 'px';
                    fab.style.top = ny + 'px';
                    fab.style.right = 'auto';
                    fab.style.bottom = 'auto';
                }

                function onUp() {
                    if (!dragging) return;
                    dragging = false;
                    fab.classList.remove('dragging');
                }

                fab.addEventListener('click', function (e) {
                    if (moved) { e.stopImmediatePropagation(); e.preventDefault(); return; }
                    onClick();
                });

                fab.addEventListener('mousedown', onDown);
                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onUp);
                fab.addEventListener('touchstart', onDown, { passive: false });
                document.addEventListener('touchmove', onMove, { passive: false });
                document.addEventListener('touchend', onUp);
            }

            makeDraggable(document.getElementById('soiFab'), function () { openModal(null, 'add'); });
            makeDraggable(document.getElementById('soiProgressFab'), openMyProgress);
        });
    </script>
@endsection
