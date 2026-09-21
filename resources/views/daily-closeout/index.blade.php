@extends('layouts.vertical', ['title' => 'Daily Closeout', 'sidenav' => 'condensed'])

@section('css')
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        #closeoutTable .tabulator-header {
            background: #1abc9c;
        }
        #closeoutTable .tabulator-header .tabulator-col {
            background: #1abc9c;
            border-right: 1px solid rgba(255, 255, 255, 0.25);
        }
        #closeoutTable .tabulator-header .tabulator-col .tabulator-col-title {
            color: #000;
            font-weight: 700;
            font-size: 13px;
        }
        #closeoutTable .tabulator-cell {
            padding: 8px 12px !important;
            white-space: normal !important;
        }
        .co-user-pill {
            display: inline-block;
            padding: 4px 12px;
            background: #e7f1ff;
            color: #0d6efd;
            border-radius: 14px;
            font-size: 13px;
            font-weight: 700;
        }
        .co-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 64px;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
        }
        .co-badge.is-yes { background: #dcfce7; color: #166534; }
        .co-badge.is-no { background: #fee2e2; color: #991b1b; }
        .co-badge.is-pending { background: #f1f5f9; color: #64748b; }
        .co-reason {
            font-size: 12px;
            color: #334155;
            line-height: 1.4;
        }
    </style>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="page-title mb-0">
                    <i class="ri-calendar-check-line me-2 text-primary"></i>Daily Closeout
                </h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('tasks.index') }}">Tasks</a></li>
                        <li class="breadcrumb-item active">Daily Closeout</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-3">
                        End-of-day answers from the 4:30 AM IST task check and the 5:00 / 5:30 AM IST DAR reminders.
                    </p>
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-3 flex-wrap">
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <select id="coUserFilter" class="form-select form-select-sm" style="min-width: 200px;">
                                <option value="">All users</option>
                                @foreach($users as $u)
                                    <option value="{{ $u->id }}" @selected((int) ($filterUserId ?? 0) === (int) $u->id)>{{ $u->name }}</option>
                                @endforeach
                            </select>
                            <select id="coCompletedFilter" class="form-select form-select-sm" style="min-width: 160px;">
                                <option value="">All answers</option>
                                <option value="yes">Completed — Yes</option>
                                <option value="no">Completed — No</option>
                                <option value="pending">Not answered</option>
                            </select>
                            <input type="date" id="coFromFilter" class="form-control form-control-sm" style="min-width: 150px;" title="From date">
                            <input type="date" id="coToFilter" class="form-control form-control-sm" style="min-width: 150px;" title="To date">
                        </div>
                        <span class="badge bg-light text-dark border" id="coCountBadge">0 records</span>
                    </div>
                    <div id="closeoutTable"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-after-vite')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        $(function () {
            const dataUrl = @json(route('daily-closeout.data'));

            function esc(s) {
                const d = document.createElement('div');
                d.textContent = s == null ? '' : String(s);
                return d.innerHTML;
            }
            function ajaxParams() {
                const params = {};
                const uid = $('#coUserFilter').val();
                const completed = $('#coCompletedFilter').val();
                const from = $('#coFromFilter').val();
                const to = $('#coToFilter').val();
                if (uid) params.user_id = uid;
                if (completed) params.completed = completed;
                if (from) params.from = from;
                if (to) params.to = to;
                return params;
            }
            function completedFormatter(cell) {
                const v = (cell.getValue() || 'pending').toString();
                if (v === 'yes') return '<span class="co-badge is-yes">Yes</span>';
                if (v === 'no') return '<span class="co-badge is-no">No</span>';
                return '<span class="co-badge is-pending">Pending</span>';
            }

            const table = new Tabulator('#closeoutTable', {
                ajaxURL: dataUrl,
                ajaxParams: ajaxParams,
                ajaxResponse: function (_url, _params, response) {
                    return (response && response.data) ? response.data : [];
                },
                dataLoaded: function (data) {
                    const n = Array.isArray(data) ? data.length : 0;
                    $('#coCountBadge').text(n + (n === 1 ? ' record' : ' records'));
                },
                layout: 'fitColumns',
                pagination: 'local',
                paginationSize: 25,
                paginationSizeSelector: [10, 25, 50, 100],
                placeholder: 'No daily closeout answers yet.',
                initialSort: [{ column: 'check_date', dir: 'desc' }],
                columns: [
                    { title: '#', formatter: 'rownum', width: 54, hozAlign: 'center', headerSort: false },
                    { title: 'Date (IST)', field: 'check_date', width: 120, hozAlign: 'center' },
                    {
                        title: 'User',
                        field: 'user_name',
                        width: 180,
                        formatter: function (cell) {
                            const v = cell.getValue();
                            return v ? '<span class="co-user-pill">' + esc(v) + '</span>' : '—';
                        }
                    },
                    { title: 'Tasks completed?', field: 'tasks_completed', width: 150, hozAlign: 'center', formatter: completedFormatter },
                    {
                        title: 'Why no',
                        field: 'incomplete_reason',
                        minWidth: 220,
                        formatter: function (cell) {
                            const v = (cell.getValue() || '').toString().trim();
                            return v ? '<span class="co-reason">' + esc(v) + '</span>' : '<span class="text-muted">—</span>';
                        }
                    },
                    { title: 'Answered at', field: 'tasks_answered_at', width: 150, hozAlign: 'center' },
                    { title: 'DAR 5:00 AM', field: 'dar_nudge_5am_at', width: 140, hozAlign: 'center' },
                    { title: 'DAR 5:30 AM', field: 'dar_nudge_530am_at', width: 140, hozAlign: 'center' },
                ]
            });

            $('#coUserFilter, #coCompletedFilter, #coFromFilter, #coToFilter').on('change', function () {
                table.setData(dataUrl, ajaxParams());
            });
        });
    </script>
@endsection
