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

        /* Add Issue — rupees-bag icon, sits above */
        #soiFab {
            bottom: 104px;
            background: transparent;
            padding: 0;
            overflow: hidden;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.25);
        }
        #soiFab:hover { transform: scale(1.06); }
        #soiFab img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            display: block;
            pointer-events: none;
        }

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

        /* Left-anchored, full-height sheet (20% of viewport width). */
        #soiModal .modal-dialog {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            margin: 0;
            width: 20vw;
            max-width: 20vw;
            min-width: 320px;
            height: 100vh;
            max-height: 100vh;
            display: flex;
        }
        #soiModal .modal-content {
            width: 100%;
            height: 100vh;
            max-height: 100vh;
            border-radius: 0;
            display: flex;
            flex-direction: column;
        }
        #soiModal .modal-body { flex: 1 1 auto; overflow-y: auto; }
        @media (max-width: 767.98px) {
            #soiModal .modal-dialog {
                width: 100vw;
                max-width: 100vw;
            }
        }

        /* "Earn monthly Increments" themed modal header */
        #soiModal .modal-content { border: none; border-radius: 0; overflow: hidden; }
        #soiModal .soi-earn-header {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 55%, #166534 100%);
            color: #fff;
            border-bottom: 3px solid #facc15;
            padding: 14px 18px;
        }
        #soiModal .soi-earn-header .modal-title {
            color: #fff;
            font-weight: 700;
            font-size: 1.02rem;
            line-height: 1.25;
        }
        #soiModal .soi-earn-header .btn-close {
            filter: brightness(0) invert(1);
            opacity: 0.85;
        }
        #soiModal .soi-earn-header .btn-close:hover { opacity: 1; }
        #soiModal .soi-rupee-badge {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            background: radial-gradient(circle at 30% 30%, #fde68a 0%, #facc15 55%, #d97706 100%);
            color: #5b3a05;
            border-radius: 50%;
            font-size: 1.15rem;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.25), inset 0 -2px 4px rgba(0, 0, 0, 0.12);
            margin-right: 12px;
            flex-shrink: 0;
        }
        #soiModal .soi-rupee-badge .soi-rupee-symbol {
            position: absolute;
            bottom: -4px;
            right: -4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            background: #fff;
            color: #16a34a;
            border-radius: 50%;
            font-size: 0.78rem;
            font-weight: 800;
            border: 2px solid #16a34a;
            line-height: 1;
        }

        /* Quick search / create combobox for the Issue field */
        .soi-issue-combo { position: relative; }
        .soi-issue-panel {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            z-index: 1080;
            max-height: 300px;
            display: none;
            flex-direction: column;
            background: #fff;
            border: 1px solid #ced4da;
            border-radius: 6px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
            overflow: hidden;
        }
        .soi-issue-panel.is-open { display: flex; }
        .soi-issue-list {
            list-style: none;
            padding: 6px 0;
            margin: 0;
            overflow-y: auto;
            max-height: 290px;
        }
        .soi-issue-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 14px;
            font-size: 13.5px;
            line-height: 1.25;
            cursor: pointer;
            user-select: none;
        }
        .soi-issue-item:hover,
        .soi-issue-item.is-active {
            background: #f1f5f9;
        }
        .soi-issue-item.soi-issue-create {
            background: #f0fdf4;
            color: #166534;
            border-bottom: 1px dashed #bbf7d0;
            font-weight: 600;
        }
        .soi-issue-item.soi-issue-create:hover { background: #dcfce7; }
        .soi-issue-emoji {
            font-size: 1.15rem;
            flex-shrink: 0;
            width: 22px;
            text-align: center;
        }
        .soi-issue-empty {
            padding: 10px 14px;
            font-size: 13px;
            color: #6c757d;
            font-style: italic;
        }
        .soi-issue-label { flex: 1; min-width: 0; }
        .soi-issue-item--critical {
            background: #fef2f2;
            color: #991b1b;
        }
        .soi-issue-item--critical:hover { background: #fee2e2; }
        .soi-issue-critical-badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            background: #dc2626;
            color: #fff;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            border-radius: 10px;
            margin-left: 8px;
            flex-shrink: 0;
        }

        /* "See All" circular runner button in modal footer */
        .soi-see-all-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #16a34a;
            color: #fff;
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.45);
            border: none;
            text-decoration: none;
            font-size: 1.35rem;
            transition: transform 0.15s ease, background 0.15s ease;
        }
        .soi-see-all-btn:hover {
            background: #15803d;
            color: #fff;
            transform: scale(1.08);
            text-decoration: none;
        }
        .soi-see-all-btn:focus { color: #fff; outline: none; box-shadow: 0 0 0 4px rgba(22, 163, 74, 0.3); }
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
                    <button type="button" class="btn btn-sm btn-primary" id="soiAddBtn">
                        <i class="fas fa-plus me-1"></i> Add Issue
                    </button>
                </div>
                <div class="card-body">
                    <div id="soiTable"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Floating movable round buttons --}}
    <button type="button" id="soiFab" class="soi-fab" title="Earn Monthly Increments" aria-label="Earn Monthly Increments">
        <img src="{{ asset('images/rupees-bag-icon.png') }}" alt="Earn Monthly Increments">
    </button>

    {{-- My Progress badge (assigned users update their own root cause / fixing root cause) --}}
    <button type="button" id="soiProgressFab" class="soi-fab" title="My Progress">
        <i class="fas fa-person-running"></i>
    </button>

    {{-- Add / Edit modal --}}
    <div class="modal fade" id="soiModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
            <form class="modal-content" id="soiForm">
                @csrf
                <div class="modal-header soi-earn-header">
                    <div class="d-flex align-items-center">
                        <span class="soi-rupee-badge" aria-hidden="true">
                            <i class="fas fa-sack-dollar"></i>
                            <span class="soi-rupee-symbol">&#8377;</span>
                        </span>
                        <h5 class="modal-title mb-0" id="soiModalTitle">Earn monthly Increments by fixing Scope of Improvement</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="soi_id" name="id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="soi_user_id" class="form-label">For User <span class="text-danger">*</span></label>
                            <select name="user_id" id="soi_user_id" class="form-select" required>
                                <option value="">Select user</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="soi_s_by" class="form-label">Suggested By</label>
                            <input type="text" id="soi_s_by" class="form-control bg-light"
                                value="{{ auth()->user()->name ?? '' }}" readonly>
                        </div>
                        <div class="col-12">
                            <label for="soi_issue" class="form-label">
                                Issue
                                <span class="text-muted fw-normal">(Suggestor Only)</span>
                            </label>
                            <div class="soi-issue-combo">
                                <input type="text" name="issue" id="soi_issue"
                                    class="form-control soi-issue-input"
                                    placeholder="Search a common issue or type your own…"
                                    autocomplete="off">
                                <div class="soi-issue-panel" id="soi_issue_panel">
                                    <ul class="soi-issue-list" id="soi_issue_list"></ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label for="soi_root_cause" class="form-label">
                                Root Cause
                                <span class="text-muted fw-normal">(entered by {{ auth()->user()->name ?? '' }})</span>
                            </label>
                            <textarea name="root_cause" id="soi_root_cause" class="form-control" rows="2" placeholder="What is the root cause?"></textarea>
                        </div>
                        <div class="col-12">
                            <label for="soi_fixing_root_cause" class="form-label">
                                Fixing Root Cause
                                <span class="text-muted fw-normal">(entered by {{ auth()->user()->name ?? '' }})</span>
                            </label>
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
                    <a href="{{ route('scope-of-improvement.index') }}" class="soi-see-all-btn" title="See All Issues" aria-label="See All Issues">
                        <i class="fas fa-person-running"></i>
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

            // Wire the Issue combobox (defined by the shared topbar modal partial).
            if (window.SoiIssueCombo && typeof window.SoiIssueCombo.setup === 'function') {
                window.SoiIssueCombo.setup({
                    combo: modalEl.querySelector('.soi-issue-combo'),
                    input: document.getElementById('soi_issue'),
                    panel: document.getElementById('soi_issue_panel'),
                    list:  document.getElementById('soi_issue_list'),
                });
            }

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
                        title: 'Suggested By',
                        field: 's_by',
                        minWidth: 130,
                        widthGrow: 1,
                        hozAlign: 'left',
                        vertAlign: 'middle',
                        headerSort: true,
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
            const currentUserName = @json(auth()->user()->name ?? '');

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
                    document.getElementById('soi_s_by').value = row.s_by || currentUserName;
                    renderHistory(row.history);
                } else {
                    document.getElementById('soiModalTitle').textContent =
                        'Earn monthly Increments by fixing Scope of Improvement';
                    document.getElementById('soi_id').value = '';
                    document.getElementById('soi_user_id').value = '';
                    document.getElementById('soi_s_by').value = currentUserName;
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
