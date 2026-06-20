@extends('layouts.vertical', ['title' => 'R&R Checklist', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        .rrc-stat-card {
            border-radius: 0.5rem;
            padding: 0.6rem 0.85rem;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }
        .rrc-stat-card .label {
            font-size: 0.7rem;
            text-transform: uppercase;
            opacity: 0.9;
            letter-spacing: 0.04em;
        }
        .rrc-stat-card .value {
            font-size: 1.25rem;
            font-weight: 700;
            line-height: 1;
        }

        .rrc-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            align-items: center;
        }
        .rrc-toolbar .form-control,
        .rrc-toolbar .form-select {
            height: 32px;
            font-size: 0.82rem;
        }

        #rrcTable {
            font-size: 0.82rem;
        }

        .rrc-badge {
            display: inline-block;
            font-size: 0.7rem;
            padding: 0.18rem 0.5rem;
            border-radius: 0.3rem;
            line-height: 1.1;
            font-weight: 600;
        }

        .rrc-pill {
            display: inline-block;
            font-size: 0.7rem;
            padding: 0.1rem 0.5rem;
            border-radius: 999px;
            background: #f1f3f5;
            color: #495057;
            font-weight: 600;
        }
        .rrc-pill.ok      { background: #d1e7dd; color: #0f5132; }
        .rrc-pill.warn    { background: #fff3cd; color: #8a6d00; }
        .rrc-pill.bad     { background: #f8d7da; color: #842029; }

        .tabulator-row.rrc-row-missing { background-color: #fff8f8 !important; }
        .tabulator-row.rrc-row-fits    { background-color: #f3fff5 !important; }

        .tabulator .tabulator-header {
            background: #cfe2ff;
        }
        .tabulator .tabulator-header .tabulator-col {
            background: #cfe2ff;
            color: #1e3a8a;
            font-weight: 600;
        }
        .tabulator-row .tabulator-cell {
            white-space: normal !important;
            word-break: break-word;
            line-height: 1.25;
        }

        /* Image / Name / Designation styling — mirrors /users/add (Team Management). */
        #rrcTable .user-avatar-img {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            object-fit: cover;
            background: #eef2ff;
            flex: 0 0 auto;
        }
        #rrcTable .designation-badge {
            background: #eef2ff;
            color: #4338ca;
            font-weight: 600;
            border-radius: 6px;
            padding: 2px 8px;
            font-size: 12px;
        }

        /* R&R magnifier button + modal */
        .rrc-rr-btn {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .rrc-rr-btn:hover { background: #d1fae5; color: #064e3b; transform: translateY(-1px); }
        .rrc-rr-btn:disabled { opacity: 0.45; cursor: not-allowed; }

        .rrc-modal .modal-header {
            background: linear-gradient(135deg, #065f46 0%, #10b981 100%);
            color: #fff;
            border-bottom: 4px solid #f59e0b;
        }
        .rrc-modal .modal-header .modal-title {
            font-size: 1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .rrc-modal .modal-header .btn-close {
            filter: invert(1) brightness(2);
        }

        .rrc-modal .form-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #6b7280;
            font-weight: 600;
        }

        .rrc-modal .rrc-readonly {
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 0.45rem 0.7rem;
            font-size: 0.9rem;
            color: #1f2937;
            font-weight: 600;
        }

        .rrc-issue-search-wrap {
            position: relative;
        }
        .rrc-issue-search-wrap .form-control {
            padding-left: 2rem;
        }
        .rrc-issue-search-wrap .rrc-search-icon {
            position: absolute;
            left: 0.6rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 0.85rem;
        }

        .rrc-issue-list {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            max-height: 320px;
            overflow-y: auto;
            background: #fff;
        }
        .rrc-issue-list .rrc-group-title {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
            font-weight: 700;
            padding: 0.5rem 0.85rem 0.25rem;
            background: #fafafa;
            border-top: 1px solid #f1f5f9;
        }
        .rrc-issue-list .rrc-group-title:first-child { border-top: none; }
        .rrc-issue-list .rrc-issue {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.55rem 0.85rem;
            cursor: pointer;
            border-top: 1px solid #f1f5f9;
            font-size: 0.88rem;
            color: #111827;
        }
        .rrc-issue-list .rrc-issue:hover { background: #f9fafb; }
        .rrc-issue-list .rrc-issue.active {
            background: #fef2f2;
            color: #991b1b;
            font-weight: 600;
        }
        .rrc-issue-list .rrc-issue .rrc-issue-emoji {
            font-size: 1.05rem;
            width: 1.4rem;
            text-align: center;
            flex: 0 0 auto;
        }
        .rrc-issue-list .rrc-issue .rrc-issue-text {
            flex: 1;
            line-height: 1.25;
        }
        .rrc-issue-list .rrc-issue .rrc-critical-pill {
            background: #fee2e2;
            color: #991b1b;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 0.15rem 0.55rem;
            border-radius: 999px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            flex: 0 0 auto;
        }
        .rrc-issue-list .rrc-empty {
            padding: 1.2rem;
            text-align: center;
            color: #9ca3af;
            font-size: 0.85rem;
        }

        .rrc-history-empty {
            font-size: 0.8rem;
            color: #9ca3af;
            margin-top: 0.4rem;
        }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'R&R Checklist',
        'sub_title' => 'User',
    ])

    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080;"></div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Stat tiles --}}
    <div class="row g-2 mb-2">
        <div class="col-6 col-md">
            <div class="rrc-stat-card" style="background:#5a67d8;">
                <div><div class="label">Total Users</div><div class="value" id="rrc-stat-total">{{ $stats['total'] ?? 0 }}</div></div>
                <i class="fas fa-users fs-3"></i>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="rrc-stat-card" style="background:#198754;">
                <div><div class="label">Assigned</div><div class="value" id="rrc-stat-assigned">{{ $stats['assigned'] ?? 0 }}</div></div>
                <i class="fas fa-file-circle-check fs-3"></i>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="rrc-stat-card" style="background:#dc3545;">
                <div><div class="label">Missing</div><div class="value" id="rrc-stat-missing">{{ $stats['missing'] ?? 0 }}</div></div>
                <i class="fas fa-file-circle-xmark fs-3"></i>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="rrc-stat-card" style="background:#0d6efd;">
                <div><div class="label">Fits</div><div class="value" id="rrc-stat-fits">{{ $stats['fits'] ?? 0 }}</div></div>
                <i class="fas fa-circle-check fs-3"></i>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="rrc-stat-card" style="background:#f59f00;">
                <div><div class="label">Not Fitting</div><div class="value" id="rrc-stat-notfits">{{ $stats['not_fits'] ?? 0 }}</div></div>
                <i class="fas fa-triangle-exclamation fs-3"></i>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body py-3">
            <div class="rrc-toolbar mb-2">
                <input type="text" id="rrc-search" class="form-control" style="max-width: 280px;"
                       placeholder="Search name / email / designation ...">

                <select id="rrc-status" class="form-select" style="width:auto;" title="Portfolio status">
                    <option value="">Any portfolio status</option>
                    <option value="assigned">Assigned</option>
                    <option value="missing">Missing</option>
                </select>

                <select id="rrc-fits" class="form-select" style="width:auto;" title="Fits status">
                    <option value="">Any fits</option>
                    <option value="yes">Fits</option>
                    <option value="no">Not fitting</option>
                    <option value="na">N/A (no portfolio)</option>
                </select>

                <select id="rrc-designation" class="form-select" style="width:auto;" title="Designation">
                    <option value="">Any designation</option>
                    @foreach ($designations ?? [] as $d)
                        <option value="{{ $d }}">{{ $d }}</option>
                    @endforeach
                </select>

                <button id="rrc-clear-btn" type="button" class="btn btn-light btn-sm" title="Clear filters">
                    <i class="fas fa-rotate-left"></i> Clear
                </button>

                <span class="ms-auto d-flex gap-2">
                    <button id="rrc-refresh-btn" type="button" class="btn btn-outline-secondary btn-sm" title="Reload">
                        <i class="fas fa-arrows-rotate"></i>
                    </button>
                    <button id="rrc-export-btn" type="button" class="btn btn-outline-primary btn-sm" title="Download CSV">
                        <i class="fas fa-file-csv"></i> CSV
                    </button>
                </span>
            </div>

            <div id="rrcTable" style="min-height: 480px;"></div>
        </div>
    </div>

    {{-- R&R Scope-of-Improvement modal (opened by the magnifier in the R&R column) --}}
    <div class="modal fade rrc-modal" id="rrcRRModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <form id="rrcRRForm" class="modal-content" autocomplete="off">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-sack-dollar"></i>
                        Earn monthly Increments by fixing Scope of Improvement
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label class="form-label mb-1">For User <span class="text-danger">*</span></label>
                            <div class="rrc-readonly" id="rrcRRUserName">—</div>
                            <input type="hidden" name="user_id" id="rrcRRUserId">
                            <input type="hidden" name="designation" id="rrcRRDesignation">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label mb-1">Suggested By</label>
                            <div class="rrc-readonly">{{ auth()->user()->name ?? auth()->user()->email ?? 'You' }}</div>
                        </div>
                    </div>

                    <label class="form-label mb-1">
                        Issue <span class="text-muted text-lowercase fw-normal">(Suggestor only · designation-wise)</span>
                    </label>
                    <div class="rrc-issue-search-wrap mb-2">
                        <i class="fas fa-magnifying-glass rrc-search-icon"></i>
                        <input type="text" class="form-control" id="rrcRRSearch"
                               placeholder="Search a common issue or type your own...">
                    </div>

                    <input type="hidden" name="item_id" id="rrcRRItemId">
                    <input type="hidden" name="item_question" id="rrcRRItemQuestion">

                    <div class="rrc-issue-list" id="rrcRRIssueList">
                        <div class="rrc-empty">Pick a user — issues are loaded from their designation’s R&amp;R checklist.</div>
                    </div>

                    <p class="rrc-history-empty"><i class="far fa-clock"></i> No history yet.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="fas fa-xmark"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-success" id="rrcRRSaveBtn" disabled>
                        <i class="fas fa-floppy-disk"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('script-bottom')
    <script>
        (function () {
            const dataUrl = '{{ route('users.rr-checklist.data') }}';

            function escapeHtml(v) {
                const d = document.createElement('div');
                d.textContent = v == null ? '' : String(v);
                return d.innerHTML;
            }

            const AVATAR_PLACEHOLDER = '{{ asset('images/users/add-image-placeholder.svg') }}';

            function imageFormatter(cell) {
                const d = cell.getRow().getData();
                const src = d.avatar_url || AVATAR_PLACEHOLDER;
                return '<img src="' + escapeHtml(src) + '" class="user-avatar-img" alt="" loading="lazy"' +
                    ' onerror="this.onerror=null;this.src=\'' + AVATAR_PLACEHOLDER + '\';">';
            }

            function nameFormatter(cell) {
                const r = cell.getRow().getData();
                let html = `<div class="fw-semibold">${escapeHtml(r.name || '—')}</div>`;
                if (r.email) {
                    html += `<div class="text-muted small">${escapeHtml(r.email)}</div>`;
                }
                return html;
            }

            function designationFormatter(cell) {
                const v = cell.getValue();
                if (!v) return '<span class="text-muted">-</span>';
                return `<span class="designation-badge">${escapeHtml(v)}</span>`;
            }

            function portfolioFormatter(cell) {
                const r = cell.getRow().getData();
                if (!r.has_portfolio) {
                    return '<span class="rrc-pill bad">Missing</span>';
                }
                const file = r.original_filename ? escapeHtml(r.original_filename) : 'Portfolio';
                return `<span class="rrc-pill ok">Assigned</span>
                        <div class="small text-muted mt-1"><i class="fas fa-file-lines"></i> ${file}</div>`;
            }

            function fitsFormatter(cell) {
                const r = cell.getRow().getData();
                if (!r.has_portfolio) return '<span class="text-muted small">—</span>';
                if (r.fits === true || r.fits === 1 || r.fits === '1') {
                    return '<span class="rrc-pill ok"><i class="fas fa-check"></i> Fits</span>';
                }
                return '<span class="rrc-pill warn"><i class="fas fa-xmark"></i> Not fitting</span>';
            }

            function activityFormatter(cell) {
                const r = cell.getRow().getData();
                if (r.assigned_at_human) {
                    return `<div class="small">Assigned ${escapeHtml(r.assigned_at_human)}</div>`;
                }
                if (r.user_updated_at_human) {
                    return `<div class="small text-muted">User updated ${escapeHtml(r.user_updated_at_human)}</div>`;
                }
                return '<span class="text-muted">—</span>';
            }

            function actionsFormatter(cell) {
                const r = cell.getRow().getData();
                let html = '<div class="d-flex gap-1 flex-wrap">';
                if (r.portfolio_url) {
                    html += `<a href="${escapeHtml(r.portfolio_url)}" class="btn btn-sm btn-soft-primary" title="Open R&R portfolio"><i class="fas fa-folder-open"></i></a>`;
                }
                if (r.email) {
                    html += `<a href="mailto:${escapeHtml(r.email)}" class="btn btn-sm btn-soft-secondary" title="Email user"><i class="fas fa-envelope"></i></a>`;
                }
                html += '</div>';
                return html;
            }

            // Magnifying-glass button that opens the designation-wise R&R modal.
            function rrFormatter(cell) {
                const r = cell.getRow().getData();
                const disabled = !r.designation;
                const title = disabled
                    ? 'No designation set for this user'
                    : 'View R&R issues for ' + (r.designation || 'this user');
                return `<button type="button" class="rrc-rr-btn"
                            title="${escapeHtml(title)}"
                            ${disabled ? 'disabled' : ''}
                            aria-label="Open R&R issues">
                            <i class="fas fa-magnifying-glass"></i>
                        </button>`;
            }

            const table = new Tabulator('#rrcTable', {
                ajaxURL: dataUrl,
                layout: 'fitDataStretch',
                height: 'calc(100vh - 360px)',
                pagination: 'local',
                paginationSize: 25,
                paginationSizeSelector: [25, 50, 100, 200, 500],
                placeholder: 'No active users found.',
                initialSort: [{ column: 'name', dir: 'asc' }],
                rowFormatter: function (row) {
                    const d = row.getData();
                    const el = row.getElement();
                    el.classList.remove('rrc-row-missing', 'rrc-row-fits');
                    if (!d.has_portfolio) {
                        el.classList.add('rrc-row-missing');
                    } else if (d.fits === true || d.fits === 1 || d.fits === '1') {
                        el.classList.add('rrc-row-fits');
                    }
                },
                columns: [
                    { title: '#', formatter: 'rownum', width: 55, hozAlign: 'center', headerSort: false },
                    { title: 'Image', field: 'avatar_url', width: 80, hozAlign: 'center', headerSort: false, formatter: imageFormatter },
                    { title: 'Name', field: 'name', minWidth: 200, formatter: nameFormatter },
                    { title: 'Designation', field: 'designation', minWidth: 150, formatter: designationFormatter },
                    {
                        title: 'R&R', field: '__rr', width: 75, hozAlign: 'center', headerSort: false,
                        titleFormatter: () => '<span title="R&R Scope of Improvement">R&amp;R</span>',
                        formatter: rrFormatter,
                        cellClick: function (e, cell) {
                            const btn = e.target.closest('.rrc-rr-btn');
                            if (!btn || btn.disabled) return;
                            openRRModal(cell.getRow().getData());
                        },
                    },
                    { title: 'Role', field: 'role', width: 120, formatter: c => c.getValue()
                        ? `<span class="rrc-badge bg-soft-info text-info">${escapeHtml(c.getValue())}</span>`
                        : '<span class="text-muted">—</span>' },
                    { title: 'Portfolio', field: 'has_portfolio', width: 200, formatter: portfolioFormatter, hozAlign: 'left' },
                    { title: 'Fits R&R', field: 'fits', width: 130, formatter: fitsFormatter, hozAlign: 'center' },
                    { title: 'Latest Activity', field: 'assigned_at', width: 180, formatter: activityFormatter, sorter: 'datetime' },
                    { title: 'Actions', field: '__actions', width: 130, formatter: actionsFormatter, headerSort: false, hozAlign: 'center' },
                ],
            });

            function reload() { table.setData(dataUrl).then(refreshStats); }

            function refreshStats() {
                const all = table.getData();
                const counts = { total: all.length, assigned: 0, missing: 0, fits: 0, notfits: 0 };
                all.forEach(r => {
                    if (r.has_portfolio) {
                        counts.assigned++;
                        if (r.fits === true || r.fits === 1 || r.fits === '1') counts.fits++;
                        else counts.notfits++;
                    } else {
                        counts.missing++;
                    }
                });
                $('#rrc-stat-total').text(counts.total);
                $('#rrc-stat-assigned').text(counts.assigned);
                $('#rrc-stat-missing').text(counts.missing);
                $('#rrc-stat-fits').text(counts.fits);
                $('#rrc-stat-notfits').text(counts.notfits);
            }

            table.on('dataLoaded', refreshStats);

            function applyFilters() {
                const q = ($('#rrc-search').val() || '').trim().toLowerCase();
                const portfolio = $('#rrc-status').val();
                const fits = $('#rrc-fits').val();
                const desig = ($('#rrc-designation').val() || '').toLowerCase();

                table.setFilter(function (row) {
                    if (q) {
                        const hay = [row.name, row.email, row.designation, row.role, row.original_filename]
                            .map(v => String(v || '').toLowerCase()).join(' ');
                        if (hay.indexOf(q) === -1) return false;
                    }
                    if (portfolio === 'assigned' && !row.has_portfolio) return false;
                    if (portfolio === 'missing' && row.has_portfolio) return false;
                    if (fits === 'yes' && !(row.has_portfolio && (row.fits === true || row.fits === 1 || row.fits === '1'))) return false;
                    if (fits === 'no' && !(row.has_portfolio && !(row.fits === true || row.fits === 1 || row.fits === '1'))) return false;
                    if (fits === 'na' && row.has_portfolio) return false;
                    if (desig && String(row.designation || '').toLowerCase() !== desig) return false;
                    return true;
                });
            }

            $('#rrc-search').on('input', applyFilters);
            $('#rrc-status, #rrc-fits, #rrc-designation').on('change', applyFilters);
            $('#rrc-clear-btn').on('click', function () {
                $('#rrc-search').val('');
                $('#rrc-status, #rrc-fits, #rrc-designation').val('');
                applyFilters();
            });
            $('#rrc-refresh-btn').on('click', reload);
            $('#rrc-export-btn').on('click', function () {
                table.download('csv', 'r-and-r-checklist.csv');
            });

            // ── R&R Scope-of-Improvement modal (designation-wise) ──────────
            const rrModalEl = document.getElementById('rrcRRModal');
            const rrModal = new bootstrap.Modal(rrModalEl);
            const rrUserName = document.getElementById('rrcRRUserName');
            const rrUserId = document.getElementById('rrcRRUserId');
            const rrDesignation = document.getElementById('rrcRRDesignation');
            const rrItemId = document.getElementById('rrcRRItemId');
            const rrItemQuestion = document.getElementById('rrcRRItemQuestion');
            const rrSearch = document.getElementById('rrcRRSearch');
            const rrIssueList = document.getElementById('rrcRRIssueList');
            const rrSaveBtn = document.getElementById('rrcRRSaveBtn');
            const rrForm = document.getElementById('rrcRRForm');

            // Cache checklist responses per designation so reopening the modal
            // for the same designation does not refetch.
            const checklistCache = {};

            // A small keyword→emoji map gives the list the same visual feel as
            // the screenshot. Falls back to a generic icon when nothing matches.
            const EMOJI_RULES = [
                { re: /help|ask/i,                       e: '🙋' },
                { re: /colleague|junior|team|peer/i,     e: '🤝' },
                { re: /escalat/i,                        e: '📢' },
                { re: /flag|raise/i,                     e: '🚩' },
                { re: /report|statement|mislead/i,       e: '📊' },
                { re: /attend|time|punctual/i,           e: '⏰' },
                { re: /done|complet|fully|quality/i,     e: '✅' },
                { re: /communicat|message|update/i,      e: '💬' },
                { re: /deadline|delay|late/i,            e: '⏳' },
                { re: /goal|target|kpi/i,                e: '🎯' },
                { re: /learn|train|skill/i,              e: '📚' },
                { re: /document|sop|process/i,           e: '📄' },
                { re: /money|cost|budget|expense/i,      e: '💰' },
            ];
            function emojiFor(text) {
                const t = String(text || '');
                for (const r of EMOJI_RULES) if (r.re.test(t)) return r.e;
                return '📌';
            }

            // A checklist item is treated as "critical" when its weight is high
            // enough that fixing it materially moves the review score.
            function isCritical(item) {
                const w = parseFloat(item.weight);
                return !isNaN(w) && w >= 5;
            }

            function openRRModal(rowData) {
                if (!rowData.designation) return;

                rrUserName.textContent = rowData.name || '—';
                rrUserId.value = rowData.id || '';
                rrDesignation.value = rowData.designation || '';
                rrItemId.value = '';
                rrItemQuestion.value = '';
                rrSearch.value = '';
                rrSaveBtn.disabled = true;

                rrIssueList.innerHTML = '<div class="rrc-empty"><i class="fas fa-spinner fa-spin"></i> Loading issues for ' +
                    escapeHtml(rowData.designation) + '…</div>';
                rrModal.show();

                const url = rowData.checklist_url;
                if (!url) {
                    rrIssueList.innerHTML = '<div class="rrc-empty">No checklist endpoint available.</div>';
                    return;
                }

                const cached = checklistCache[url];
                if (cached) {
                    renderIssues(cached);
                    return;
                }

                fetch(url, { headers: { 'Accept': 'application/json' } })
                    .then(r => r.ok ? r.json() : Promise.reject(r.statusText))
                    .then(payload => {
                        checklistCache[url] = payload;
                        renderIssues(payload);
                    })
                    .catch(err => {
                        rrIssueList.innerHTML = '<div class="rrc-empty text-danger">Failed to load checklist: ' +
                            escapeHtml(String(err)) + '</div>';
                    });
            }

            function renderIssues(payload) {
                const categories = (payload && Array.isArray(payload.categories)) ? payload.categories : [];

                const allItems = [];
                categories.forEach(cat => {
                    (cat.items || []).forEach(it => {
                        allItems.push({
                            id: it.id,
                            question: it.question || '',
                            weight: it.weight,
                            category: cat.name || '',
                        });
                    });
                });

                if (!allItems.length) {
                    rrIssueList.innerHTML = '<div class="rrc-empty">No R&amp;R checklist items configured for this designation yet.</div>';
                    return;
                }

                // Group items by category for readability.
                const byCat = {};
                allItems.forEach(it => {
                    const k = it.category || 'General';
                    (byCat[k] = byCat[k] || []).push(it);
                });

                let html = '';
                Object.keys(byCat).forEach(cat => {
                    html += `<div class="rrc-group-title">${escapeHtml(cat)}</div>`;
                    byCat[cat].forEach(it => {
                        const crit = isCritical(it);
                        html += `<div class="rrc-issue ${crit ? 'critical' : ''}"
                                       data-item-id="${escapeHtml(it.id)}"
                                       data-q="${escapeHtml(it.question.toLowerCase())}"
                                       data-question="${escapeHtml(it.question)}">
                                    <span class="rrc-issue-emoji">${emojiFor(it.question)}</span>
                                    <span class="rrc-issue-text">${escapeHtml(it.question)}</span>
                                    ${crit ? '<span class="rrc-critical-pill">Critical</span>' : ''}
                                </div>`;
                    });
                });
                rrIssueList.innerHTML = html;
            }

            // Live filter the issue list by the search box.
            rrSearch.addEventListener('input', function () {
                const q = (this.value || '').trim().toLowerCase();
                const rows = rrIssueList.querySelectorAll('.rrc-issue');
                let visibleCount = 0;
                rows.forEach(row => {
                    const hay = row.getAttribute('data-q') || '';
                    const show = !q || hay.indexOf(q) !== -1;
                    row.style.display = show ? '' : 'none';
                    if (show) visibleCount++;
                });
                // Hide category headers when none of their issues are visible.
                rrIssueList.querySelectorAll('.rrc-group-title').forEach(title => {
                    let sib = title.nextElementSibling;
                    let anyVisible = false;
                    while (sib && !sib.classList.contains('rrc-group-title')) {
                        if (sib.classList.contains('rrc-issue') && sib.style.display !== 'none') {
                            anyVisible = true; break;
                        }
                        sib = sib.nextElementSibling;
                    }
                    title.style.display = anyVisible ? '' : 'none';
                });
            });

            // Pick a single issue from the list (acts like a custom dropdown).
            rrIssueList.addEventListener('click', function (e) {
                const row = e.target.closest('.rrc-issue');
                if (!row) return;
                rrIssueList.querySelectorAll('.rrc-issue.active').forEach(r => r.classList.remove('active'));
                row.classList.add('active');
                rrItemId.value = row.getAttribute('data-item-id') || '';
                rrItemQuestion.value = row.getAttribute('data-question') || '';
                rrSearch.value = row.getAttribute('data-question') || '';
                rrSaveBtn.disabled = false;
            });

            rrForm.addEventListener('submit', function (e) {
                e.preventDefault();
                if (!rrItemId.value) {
                    showToast('Please pick an issue from the list.', 'warning');
                    return;
                }
                // TODO: POST the suggestion to a dedicated endpoint once the
                // backend store is wired up. For now the modal is feedback-only.
                showToast('Issue noted for ' + (rrUserName.textContent || 'user') + ': "' + rrItemQuestion.value + '"', 'success');
                rrModal.hide();
            });

            // Lightweight toast helper (shared with errors above).
            function showToast(message, type) {
                type = type || 'info';
                const container = document.querySelector('.toast-container');
                if (!container) return;
                const bg = type === 'error' ? 'danger'
                         : type === 'success' ? 'success'
                         : type === 'warning' ? 'warning' : 'info';
                const t = document.createElement('div');
                t.className = `toast align-items-center text-white bg-${bg} border-0`;
                t.setAttribute('role', 'alert');
                t.innerHTML = `<div class="d-flex"><div class="toast-body">${escapeHtml(message)}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
                container.appendChild(t);
                new bootstrap.Toast(t, { delay: 3000 }).show();
                t.addEventListener('hidden.bs.toast', () => t.remove());
            }
        })();
    </script>
@endsection
