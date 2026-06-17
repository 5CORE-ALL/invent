@extends('layouts.vertical', ['title' => 'DAR', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        /* Tabulator header — teal pill so it matches the reference design. */
        #darTable .tabulator-header {
            background: #1abc9c;
        }

        #darTable .tabulator-header .tabulator-col {
            background: #1abc9c;
            border-right: 1px solid rgba(255, 255, 255, 0.25);
        }

        #darTable .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
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
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <button type="button" class="dar-page-add-btn" id="darPageAddBtn" title="Add Daily Activity Report">
                            <i class="fas fa-clipboard-list"></i>
                            <span>DAR</span>
                        </button>
                        <span class="dar-count-badge" id="darCountBadge" title="Total DAR entries out of a target of 25">
                            <i class="fas fa-list-check me-1"></i>
                            Total Count: <strong id="darCountValue">0</strong>/25
                            <span class="dar-count-pct" id="darCountPct">0%</span>
                        </span>
                    </div>
                    <div id="darTable"></div>
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
                return `<span class="dar-time-pill">${esc(v)}</span>`;
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
                ajaxResponse: function (url, params, response) {
                    return (response && response.data) ? response.data : [];
                },
                dataLoaded: function (data) {
                    updateCountBadge(Array.isArray(data) ? data.length : 0);
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
                        title: 'Task',
                        field: 'task',
                        minWidth: 240,
                        widthGrow: 2,
                        hozAlign: 'left',
                        vertAlign: 'middle',
                        headerSort: false,
                        formatter: taskFormatter,
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
