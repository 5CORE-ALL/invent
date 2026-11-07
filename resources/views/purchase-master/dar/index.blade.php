@extends('layouts.vertical', ['title' => 'DAR', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        /* Tabulator header — teal pill so it matches the reference design. */
        #darTable .tabulator-header,
        #darComparisonTable .tabulator-header {
            background: #1abc9c;
        }

        #darTable .tabulator-header .tabulator-col,
        #darComparisonTable .tabulator-header .tabulator-col {
            background: #1abc9c;
            border-right: 1px solid rgba(255, 255, 255, 0.25);
        }

        #darComparisonTable .tabulator-cell {
            padding: 8px 14px !important;
        }

        #darTable .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title,
        #darComparisonTable .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            color: #000000;
            font-weight: 700;
            font-size: 13px;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }

        #darTable .tabulator-header .tabulator-col .tabulator-col-content {
            padding: 10px 14px;
        }

        #darTable .tabulator-col .tabulator-arrow {
            border-bottom-color: #000000 !important;
            border-top-color: #000000 !important;
        }

        #darTable .tabulator-cell {
            padding: 8px 14px !important;
            white-space: normal !important;
        }

        /* User "pill" — light blue background, blue bold text. */
        .dar-user-pill {
            display: inline-block;
            padding: 4px 14px;
            background: #e7f1ff;
            color: #0d6efd;
            border-radius: 14px;
            font-size: 13px;
            font-weight: 700;
        }

        /* Time-taken pill — indigo accent. */
        .dar-time-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 44px;
            min-height: 24px;
            padding: 2px 10px;
            border-radius: 12px;
            background: #eef2ff;
            color: #3730a3;
            font-weight: 700;
            font-size: 12px;
            border: 1px solid #c7d2fe;
            white-space: nowrap;
        }

        .dar-time-pill.is-empty {
            background: #f8f9fa;
            color: #adb5bd;
            border-style: dashed;
            border-color: #ced4da;
            font-weight: 500;
            font-style: italic;
        }

        /* Task cell — keep readable, allow wrapping. */
        .dar-task-cell {
            font-size: 12px;
            color: #212529;
            line-height: 1.4;
        }

        /* Action buttons. */
        .dar-action-btn {
            background: transparent;
            border: 0;
            padding: 4px 6px;
            cursor: pointer;
            font-size: 15px;
            line-height: 1;
            border-radius: 6px;
            transition: background 0.12s ease, color 0.12s ease, transform 0.12s ease;
        }
        .dar-action-btn.is-edit { color: #0d6efd; }
        .dar-action-btn.is-edit:hover { background: #e7f1ff; transform: scale(1.08); }
        .dar-action-btn.is-delete { color: #dc2626; }
        .dar-action-btn.is-delete:hover { background: #fee2e2; transform: scale(1.08); }

        /* "Total Count / 25" badge above the table. */
        .dar-count-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 14px;
            border-radius: 999px;
            background: #eef2ff;
            color: #3730a3;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid #c7d2fe;
            white-space: nowrap;
        }
        .dar-count-badge strong { font-weight: 800; }

        /* Blue "DAR" button — matches the topbar DAR button styling. */
        .dar-page-add-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            border: 0;
            border-radius: 999px;
            background: #2563eb;
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.35);
            transition: background 0.15s ease, transform 0.12s ease;
        }
        .dar-page-add-btn:hover { background: #1d4ed8; transform: translateY(-1px); }
        .dar-page-add-btn i { font-size: 15px; }
        .dar-count-pct {
            margin-left: 6px;
            padding: 1px 8px;
            border-radius: 999px;
            background: #3730a3;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
        }
    </style>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="page-title mb-0">
                    <i class="ri-clipboard-line me-2 text-primary"></i>DAR &mdash; Daily Activity Report
                </h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="javascript:void(0);">Purchase Master</a></li>
                        <li class="breadcrumb-item active">DAR</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <ul class="nav nav-tabs mb-3" id="darTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="dar-tab-btn" data-bs-toggle="tab" data-bs-target="#darTabPane" type="button" role="tab" aria-controls="darTabPane" aria-selected="true">
                                <i class="fas fa-clipboard-list me-1"></i>DAR
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="dar-comparison-tab-btn" data-bs-toggle="tab" data-bs-target="#darComparisonPane" type="button" role="tab" aria-controls="darComparisonPane" aria-selected="false">
                                <i class="fas fa-scale-balanced me-1"></i>Comparison
                            </button>
                        </li>
                    </ul>
                    <div class="tab-content">
                    <div class="tab-pane fade show active" id="darTabPane" role="tabpanel" aria-labelledby="dar-tab-btn">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-2 flex-wrap">
                        <button type="button" class="dar-page-add-btn" id="darPageAddBtn" title="Add Daily Activity Report">
                            <i class="fas fa-clipboard-list"></i>
                            <span>DAR</span>
                        </button>
                        <div class="flex-grow-1" style="max-width: 260px;">
                            <select id="darUserFilter" class="form-select form-select-sm" title="Filter by user">
                                <option value="">All Users</option>
                                @foreach($users as $u)
                                    <option value="{{ $u->id }}" @selected((int) ($filterUserId ?? 0) === (int) $u->id)>{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if((int) ($filterDays ?? 0) > 0)
                            <span class="dar-count-badge" title="Filtered to the last {{ (int) $filterDays }} days, latest first">
                                Last {{ (int) $filterDays }} days · latest first
                            </span>
                        @endif
                        <span class="dar-count-badge" id="darCountBadge" title="Total DAR entries out of a target of 25">
                            <i class="fas fa-list-check me-1"></i>
                            Total Count: <strong id="darCountValue">0</strong>/25
                            <span class="dar-count-pct" id="darCountPct">0%</span>
                        </span>
                    </div>
                    <div id="darTable"></div>
                    </div>

                    {{-- Comparison: DAR time vs same-day done ATC, tasks done and working hours --}}
                    <div class="tab-pane fade" id="darComparisonPane" role="tabpanel" aria-labelledby="dar-comparison-tab-btn">
                        <div class="d-flex justify-content-between align-items-center gap-2 mb-2 flex-wrap">
                            <div class="d-flex gap-2 flex-wrap">
                                <select id="darCmpUser" class="form-select form-select-sm" style="min-width:200px;" title="Filter by user">
                                    <option value="">All Users</option>
                                    @foreach($users as $u)
                                        <option value="{{ $u->id }}" @selected((int) ($filterUserId ?? 0) === (int) $u->id)>{{ $u->name }}</option>
                                    @endforeach
                                </select>
                                <select id="darCmpDays" class="form-select form-select-sm" style="width:auto;" title="Date range">
                                    <option value="today">Today</option>
                                    <option value="yesterday">Yesterday</option>
                                    <option value="7">Last 7 days</option>
                                    <option value="15">Last 15 days</option>
                                    <option value="30" selected>Last 30 days</option>
                                    <option value="60">Last 60 days</option>
                                    <option value="90">Last 90 days</option>
                                    <option value="custom">Custom</option>
                                </select>
                                <input type="date" id="darCmpFrom" class="form-control form-control-sm" style="width:auto;" title="From date">
                                <input type="date" id="darCmpTo" class="form-control form-control-sm" style="width:auto;" title="To date">
                            </div>
                            <span class="dar-count-badge">
                                <i class="fas fa-list me-1"></i>Rows: <strong id="darCmpCount">0</strong>
                            </span>
                        </div>
                        <div id="darComparisonTable"></div>
                    </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- The Add / Edit modal now lives in the shared partial
         resources/views/layouts/shared/dar-modal.blade.php (included from the
         layout) so it can also be opened from the topbar on every page. --}}
@endsection

@section('script-after-vite')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        $(function () {
            const csrf       = $('meta[name="csrf-token"]').attr('content');
            const dataUrl    = @json(route('dar.data'));
            const deleteBase = @json(url('dar/delete'));
            const initialDays = {{ (int) ($filterDays ?? 0) }};

            function ajaxParams() {
                const params = {};
                const uid = $('#darUserFilter').val();
                if (uid) params.user_id = uid;
                if (initialDays > 0) params.days = initialDays;
                return params;
            }

            const DAR_TARGET = 25;
            function updateCountBadge(count) {
                const c = Number(count) || 0;
                const pct = Math.round((c / DAR_TARGET) * 100);
                const valEl = document.getElementById('darCountValue');
                const pctEl = document.getElementById('darCountPct');
                if (valEl) valEl.textContent = c;
                if (pctEl) pctEl.textContent = pct + '%';
            }

            function esc(s) {
                const d = document.createElement('div');
                d.textContent = (s == null ? '' : String(s));
                return d.innerHTML;
            }

            // Minutes → "Xh Ym" (e.g. 135 → "2h 15m", 40 → "40m", 120 → "2h").
            function formatHrsMin(mins) {
                const m = Math.round(Number(mins) || 0);
                const h = Math.floor(m / 60);
                const r = m % 60;
                if (h > 0 && r > 0) return h + 'h ' + r + 'm';
                if (h > 0) return h + 'h';
                return r + 'm';
            }

            function sumGroupMinutes(data) {
                return (data || []).reduce(function (sum, r) {
                    return sum + (Number(r.time_taken) || 0);
                }, 0);
            }

            // "2026-07-08" → "08 Jul" (drop the year for display).
            function formatDateNoYear(value) {
                const s = (value == null ? '' : String(value)).trim();
                const m = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
                if (!m) return esc(s || '\u2014');
                const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                const mon = months[parseInt(m[2], 10) - 1] || m[2];
                return m[3] + ' ' + mon;
            }

            // ---- Column formatters ----
            function userFormatter(cell) {
                const value = (cell.getValue() ?? '').toString();
                if (!value) return '<span class="text-muted">&mdash;</span>';
                return `<span class="dar-user-pill">${esc(value)}</span>`;
            }

            function taskFormatter(cell) {
                const value = (cell.getValue() ?? '').toString();
                if (!value) return '<span class="text-muted">&mdash;</span>';
                return `<span class="dar-task-cell">${esc(value)}</span>`;
            }

            function timeFormatter(cell) {
                const v = cell.getValue();
                if (v === null || v === undefined || v === '') {
                    return '<span class="dar-time-pill is-empty">&mdash;</span>';
                }
                const n = Number(v);
                const display = isNaN(n) ? esc(v) : Math.round(n);
                return `<span class="dar-time-pill">${display}</span>`;
            }

            function actionFormatter() {
                return `
                    <button type="button" class="dar-action-btn is-edit dar-edit" title="Edit">
                        <i class="fas fa-pen"></i>
                    </button>
                    <button type="button" class="dar-action-btn is-delete dar-delete" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>`;
            }

            // ---- Build the Tabulator table ----
            const table = new Tabulator('#darTable', {
                ajaxURL: dataUrl,
                ajaxParams: ajaxParams,
                initialSort: [{ column: 'report_date', dir: 'desc' }],
                ajaxResponse: function (url, params, response) {
                    return (response && response.data) ? response.data : [];
                },
                dataLoaded: function (data) {
                    updateCountBadge(Array.isArray(data) ? data.length : 0);
                },
                dataFiltered: function (filters, rows) {
                    updateCountBadge(Array.isArray(rows) ? rows.length : 0);
                },
                layout: 'fitColumns',
                rowHeight: 52,
                pagination: 'local',
                paginationSize: 25,
                paginationSizeSelector: [10, 25, 50, 100],
                placeholder: 'No DAR records yet. Click "Add" or the topbar DAR button to add one.',
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
                        title: 'Date',
                        field: 'report_date',
                        width: 120,
                        hozAlign: 'center',
                        vertAlign: 'middle',
                        headerSort: true,
                        formatter: function (cell) {
                            return formatDateNoYear(cell.getValue());
                        },
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
                        title: 'Time',
                        field: 'time_taken',
                        width: 150,
                        hozAlign: 'center',
                        vertAlign: 'middle',
                        headerSort: true,
                        sorter: 'number',
                        formatter: timeFormatter,
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
                            if (e.target.closest('.dar-edit')) {
                                if (window.DarModal) window.DarModal.open(data);
                            } else if (e.target.closest('.dar-delete')) {
                                deleteRow(data.id);
                            }
                        },
                    },
                ],
            });
            // Exposed so the shared DAR modal can refresh the grid after saving.
            window.__darTable = table;

            // Page "DAR" button opens the shared add modal (blank/create mode).
            $('#darPageAddBtn').on('click', function () {
                if (window.DarModal) window.DarModal.open();
            });

            // User filter — refetch so the executive deep-link and All Users
            // stay consistent with the optional last-N-days window.
            $('#darUserFilter').on('change', function () {
                table.setData(dataUrl, ajaxParams());
            });

            // ---- Comparison tab (lazy-built the first time it is shown) ----
            let cmpTable = null;
            const cmpUrl = @json(route('dar.comparison'));

            function cmpParams() {
                const preset = $('#darCmpDays').val() || '30';
                const params = {};
                if (preset === 'today' || preset === 'yesterday') {
                    params.range = preset;
                } else if (preset === 'custom') {
                    params.range = 'custom';
                    params.from = $('#darCmpFrom').val();
                    params.to = $('#darCmpTo').val();
                } else {
                    params.days = preset;
                }
                const uid = $('#darCmpUser').val();
                if (uid) params.user_id = uid;
                return params;
            }

            function minsCell(cell) {
                const v = cell.getValue();
                if (v === null || v === undefined || Number(v) <= 0) {
                    return '<span class="dar-time-pill is-empty">&mdash;</span>';
                }
                return '<span class="dar-time-pill">' + formatHrsMin(v) + '</span>';
            }

            function buildComparisonTable() {
                cmpTable = new Tabulator('#darComparisonTable', {
                    ajaxURL: cmpUrl,
                    ajaxParams: cmpParams,
                    ajaxResponse: function (url, params, response) {
                        // Reflect the resolved range (Today / Yesterday / Last N days) in the date boxes.
                        if (response && response.from && response.to) {
                            $('#darCmpFrom').val(response.from);
                            $('#darCmpTo').val(response.to);
                        }
                        return (response && response.data) ? response.data : [];
                    },
                    dataLoaded: function (data) {
                        $('#darCmpCount').text(Array.isArray(data) ? data.length : 0);
                    },
                    dataFiltered: function (filters, rows) {
                        $('#darCmpCount').text(Array.isArray(rows) ? rows.length : 0);
                    },
                    layout: 'fitColumns',
                    rowHeight: 46,
                    pagination: 'local',
                    paginationSize: 25,
                    paginationSizeSelector: [10, 25, 50, 100],
                    initialSort: [{ column: 'report_date', dir: 'desc' }],
                    placeholder: 'No DAR / task / attendance data for this range.',
                    columns: [
                        { title: '#', formatter: 'rownum', width: 60, hozAlign: 'center', vertAlign: 'middle', headerSort: false },
                        {
                            title: 'Date', field: 'report_date', width: 120, hozAlign: 'center', vertAlign: 'middle',
                            formatter: function (cell) { return formatDateNoYear(cell.getValue()); },
                        },
                        {
                            title: 'DAR File', field: 'dar_filed', width: 120, hozAlign: 'center', vertAlign: 'middle',
                            formatter: function (cell) {
                                return cell.getValue() === 'Yes'
                                    ? '<span class="badge bg-success">Yes</span>'
                                    : '<span class="badge bg-danger">No</span>';
                            },
                        },
                        {
                            title: 'No of Task Done', field: 'tasks_done', hozAlign: 'center', vertAlign: 'middle',
                            sorter: 'number',
                            formatter: function (cell) {
                                const n = Number(cell.getValue()) || 0;
                                return n > 0 ? '<span class="dar-time-pill">' + n + '</span>' : '<span class="dar-time-pill is-empty">&mdash;</span>';
                            },
                        },
                        {
                            title: 'That Day ATC', field: 'atc_minutes', hozAlign: 'center', vertAlign: 'middle',
                            sorter: 'number', formatter: minsCell,
                        },
                        {
                            title: 'That Day ETC', field: 'etc_minutes', hozAlign: 'center', vertAlign: 'middle',
                            sorter: 'number', formatter: minsCell,
                        },
                        {
                            title: 'Working Hours', field: 'working_minutes', hozAlign: 'center', vertAlign: 'middle',
                            sorter: 'number', formatter: minsCell,
                        },
                    ],
                });
            }

            document.getElementById('dar-comparison-tab-btn').addEventListener('shown.bs.tab', function () {
                if (!cmpTable) {
                    buildComparisonTable();
                } else {
                    cmpTable.redraw(true);
                }
            });
            $('#darCmpUser, #darCmpDays').on('change', function () {
                if (cmpTable) cmpTable.setData(cmpUrl, cmpParams());
            });
            // Picking a date by hand switches the preset to "Custom".
            $('#darCmpFrom, #darCmpTo').on('change', function () {
                if (!$('#darCmpFrom').val() || !$('#darCmpTo').val()) return;
                $('#darCmpDays').val('custom');
                if (cmpTable) cmpTable.setData(cmpUrl, cmpParams());
            });

            // ---- Quick search for the user dropdowns ----
            // Type to filter the list (or pick from it); clearing the box = All Users.
            // The original <select> stays in the DOM (hidden) so existing logic keeps working.
            function makeUserSearchable(selector) {
                const $sel = $(selector);
                if (!$sel.length) return;
                const listId = $sel.attr('id') + 'Search';
                const options = [];
                $sel.find('option').each(function () {
                    if (this.value) options.push({ id: this.value, name: $(this).text().trim() });
                });

                const $list = $('<datalist>').attr('id', listId);
                options.forEach(function (o) { $list.append($('<option>').attr('value', o.name)); });

                const selected = options.find(function (o) { return o.id === String($sel.val()); });
                const $input = $('<input type="search" autocomplete="off">')
                    .addClass('form-control form-control-sm')
                    .attr({ list: listId, placeholder: '\uD83D\uDD0D Search user…', title: 'Type to search users' })
                    .css({ minWidth: '200px' })
                    .val(selected ? selected.name : '');

                $sel.hide().after($list).after($input);

                function apply() {
                    const q = $input.val().trim().toLowerCase();
                    let match = null;
                    if (q === '') {
                        match = { id: '' };
                    } else {
                        match = options.find(function (o) { return o.name.toLowerCase() === q; });
                        if (!match) {
                            const partial = options.filter(function (o) { return o.name.toLowerCase().indexOf(q) !== -1; });
                            if (partial.length === 1) match = partial[0];
                        }
                    }
                    if (match && String($sel.val() || '') !== String(match.id)) {
                        $sel.val(match.id).trigger('change');
                    }
                }
                $input.on('change search', apply);
                $input.on('keydown', function (e) { if (e.key === 'Enter') apply(); });
            }
            makeUserSearchable('#darUserFilter');
            makeUserSearchable('#darCmpUser');

            function deleteRow(id) {
                if (!id || !confirm('Delete this DAR entry?')) return;
                $.post(deleteBase + '/' + id, { _token: csrf }, function () {
                    table.replaceData();
                }).fail(function () {
                    alert('Could not delete the entry.');
                });
            }

        });
    </script>
@endsection
