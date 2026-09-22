@extends('layouts.vertical', ['title' => 'Yesterday Done', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        .ydone-page-meta { color: #64748b; font-size: 0.9rem; }
        .ydone-badges { display: flex; flex-wrap: nowrap; gap: 8px; overflow-x: auto; margin-bottom: 12px; }
        .ydone-badges .stat-card {
            border: none;
            border-radius: 8px;
            box-shadow: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 6px 10px;
            color: #fff;
            min-width: max-content;
        }
        .ydone-badges .stat-label,
        .ydone-badges .stat-value { color: inherit; font-size: 13px; font-weight: 700; white-space: nowrap; }
        .ydone-badges .stat-icon { display: none; }
        .ydone-badges .stat-card-blue { background: #3b7ddd; }
        .ydone-badges .stat-card-red { background: #dc3545; }
        .ydone-badges .stat-card-yellow { background: #f0ad4e; }
        .ydone-badges .stat-card-teal { background: #20c997; }
        .ydone-badges .stat-card-perf-score { background: #7c3aed; }
        .ydone-badges .stat-card-red-missed { background: #dc3545; }
        .ydone-badges .stat-card-cyan { background: #0dcaf0; color: #052c3b; }
        .ydone-badges .stat-card-ca { background: #fff; border: 1px solid #dc3545; color: #b71c1c; }
        .ydone-dar-btn.is-empty {
            background: #dc3545;
            border-color: #dc3545;
            color: #fff;
            font-weight: 700;
        }
        .ydone-dar-empty {
            color: #dc3545;
            font-weight: 700;
        }
        .ydone-time-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: #f0fdfa;
            border: 1px solid #ccfbf1;
            text-decoration: none;
        }
        .ydone-time-btn:hover { background: #ccfbf1; }
        .ydone-time-btn img { width: 20px; height: 20px; object-fit: contain; }
        .ydone-active-time {
            font-weight: 700;
            color: #0f172a;
            font-size: 0.95rem;
            white-space: nowrap;
        }
        #ydone-table { border: 1px solid #e9ecef !important; border-radius: 8px !important; font-size: 14px; background: #fff; }
        #ydone-table .tabulator-header { background-color: #f8f9fa !important; border-bottom: 2px solid #e9ecef !important; }
        #ydone-table .tabulator-header .tabulator-col { background-color: #f8f9fa !important; border-right: 1px solid #e9ecef !important; }
        #ydone-table .tabulator-col-title { font-weight: 600 !important; color: #495057 !important; font-size: calc(13px * 0.9) !important; text-transform: uppercase; text-align: center !important; }
        #ydone-table .tabulator-row { border-bottom: 1px solid #e9ecef !important; background: #fff !important; }
        #ydone-table .tabulator-row .tabulator-cell { border-right: 1px solid #e9ecef !important; padding: 12px 8px !important; color: #495057; }
        #ydone-table .tabulator-row:hover { background-color: #f8f9fa !important; }
        #ydone-table .tabulator-row.automated-task,
        #ydone-table .tabulator-row.automated-task .tabulator-cell { background-color: #fffbea !important; }
        #ydone-table .tabulator-row.automated-task.tabulator-row-even,
        #ydone-table .tabulator-row.automated-task.tabulator-row-even .tabulator-cell { background-color: #fff7cc !important; }
        #ydone-table .tabulator-row.ydone-missed,
        #ydone-table .tabulator-row.ydone-missed .tabulator-cell { background-color: #fffde7 !important; }
        #ydone-table .tasks-col-link-icon,
        #ydone-table .tabulator-cell.tasks-col-link-icon { padding: 6px 2px !important; }
        #ydone-table .tasks-col-time-compact,
        #ydone-table .tabulator-cell.tasks-col-time-compact,
        #ydone-table .tasks-col-priority-compact,
        #ydone-table .tabulator-cell.tasks-col-priority-compact { padding: 6px 2px !important; }
    </style>
@endsection

@section('content')
    @php
        $doneCount = collect($rows)->where('type', '!=', 'Missed')->count();
        $missedCount = collect($rows)->where('type', 'Missed')->count();
    @endphp
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box">
                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="{{ route('tasks.index') }}">Task Manager</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('tasks.summary') }}">Task Summary</a></li>
                            <li class="breadcrumb-item active">Y Done</li>
                        </ol>
                    </div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h4 class="page-title mb-0">
                            Yesterday
                            @if (!empty($focusUser))
                                — {{ $focusUser->name }}
                            @endif
                        </h4>
                        <button type="button"
                                class="btn btn-sm {{ count($yesterdayDars) ? 'btn-outline-primary' : 'btn-danger ydone-dar-btn is-empty' }}"
                                data-bs-toggle="modal"
                                data-bs-target="#ydone-dar-modal">
                            Y-DAR
                        </button>
                        <a class="ydone-time-btn"
                           href="{{ $attendanceUrl }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           title="Open attendance summary{{ !empty($focusUser) ? ' for '.$focusUser->name : '' }}"
                           aria-label="Open attendance summary">
                            <img src="{{ asset('assets/images/task-magnify-icon.png') }}" alt="">
                        </a>
                        <span class="ydone-active-time" title="Active time yesterday">{{ $yesterdayActiveLabel }}</span>
                    </div>
                    <p class="ydone-page-meta mb-0">
                        {{ $window['label'] ?? '' }} (PST)
                        · {{ $doneCount }} done
                        · {{ $missedCount }} missed
                    </p>
                </div>
            </div>
        </div>

        <div class="ydone-badges">
            <div class="stat-card stat-card-blue"><span class="stat-label">Pending</span><span class="stat-value">{{ $taskBadges['pending'] }}</span></div>
            <div class="stat-card stat-card-ca"><span class="stat-label">CA</span><span class="stat-value">{{ $taskBadges['ca'] }}</span></div>
            <div class="stat-card stat-card-red"><span class="stat-label">OVERDUE</span><span class="stat-value">{{ $taskBadges['overdue'] }}</span></div>
            <div class="stat-card stat-card-yellow"><span class="stat-label">ETC L30 D</span><span class="stat-value">{{ $taskBadges['etc_l30_h'] }}h</span></div>
            <div class="stat-card stat-card-teal"><span class="stat-label">ATC L30</span><span class="stat-value">{{ $taskBadges['atc_l30_h'] }}h</span></div>
            <div class="stat-card stat-card-teal"><span class="stat-label">TAT</span><span class="stat-value">{{ $taskBadges['tat'] }}</span></div>
            <div class="stat-card stat-card-perf-score"><span class="stat-label">AVG SCORE</span><span class="stat-value">{{ $taskBadges['score'] }}</span></div>
            <div class="stat-card stat-card-red-missed"><span class="stat-label">MISSED</span><span class="stat-value">{{ $taskBadges['missed'] }}</span></div>
            <div class="stat-card stat-card-cyan"><span class="stat-label">PENDING ETC</span><span class="stat-value">{{ $taskBadges['pending_etc_h'] }}h</span></div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div id="ydone-table"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="ydone-dar-modal" tabindex="-1" aria-labelledby="ydone-dar-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ydone-dar-modal-label">
                        Y-DAR
                        @if (!empty($focusUser))
                            — {{ $focusUser->name }}
                        @endif
                        · {{ $window['label'] ?? '' }} (PST)
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @if (count($yesterdayDars) === 0)
                        <p class="ydone-dar-empty mb-0">No DAR filled yesterday.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="table-light">
                                    <tr>
                                        @if (empty($focusUser))
                                            <th>User</th>
                                        @endif
                                        <th>Group</th>
                                        <th>Task</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($yesterdayDars as $dar)
                                        <tr>
                                            @if (empty($focusUser))
                                                <td>{{ $dar['user'] !== '' ? $dar['user'] : '—' }}</td>
                                            @endif
                                            <td>{{ $dar['group'] !== '' ? $dar['group'] : '—' }}</td>
                                            <td style="white-space: pre-wrap;">{{ $dar['task'] !== '' ? $dar['task'] : '—' }}</td>
                                            <td>{{ $dar['time_taken'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        var ydoneRows = @json($rows);

        function esc(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function formatTid(value) {
            if (!value) return null;
            var parts = String(value).trim().split(/[- T]/);
            var year = parseInt(parts[0], 10);
            var month = parseInt(parts[1], 10);
            var day = parseInt(parts[2], 10);
            if (isNaN(year) || isNaN(month) || isNaN(day) || month < 1 || month > 12) return null;
            var mon = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            var j = day % 10, k = day % 100;
            var ord = (j === 1 && k !== 11) ? day + 'st' : (j === 2 && k !== 12) ? day + 'nd' : (j === 3 && k !== 13) ? day + 'rd' : day + 'th';
            return {
                label: ord + ' ' + mon[month - 1],
                title: String(day).padStart(2, '0') + '/' + String(month).padStart(2, '0') + '/' + year
            };
        }

        function linkSlot(raw, icon, label) {
            var v = String(raw || '').trim();
            if (!/^https?:\/\//i.test(v)) return '<span style="color:#adb5bd;">-</span>';
            return '<a href="' + esc(v) + '" target="_blank" rel="noopener noreferrer" title="' + esc(v) + '" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center;line-height:1;" aria-label="' + esc(label) + '">' +
                '<img src="' + icon + '" alt="" style="width:28px;height:28px;display:inline-block;" /></a>';
        }

        var linkIcon = @json(asset('assets/images/task-link-icon.png'));
        var sopIcon = @json(asset('assets/images/task-sop-icon.png'));
        var videoIcon = @json(asset('assets/images/task-video-icon.png'));
        var caIcon = @json(asset('assets/images/task-ca-icon.png'));
        var defaultAvatar = @json(asset('images/users/avatar-2.jpg'));

        function personCell(name, avatar, designation) {
            if (!name || name === '-') return '<span style="color:#adb5bd;">-</span>';
            var firstNames = String(name).split(',').map(function (part) {
                return part.trim().split(/\s+/)[0];
            }).filter(Boolean);
            var img = esc(avatar || defaultAvatar);
            var title = designation ? ' title="' + esc(designation) + '"' : '';
            return '<div class="d-flex align-items-center justify-content-center gap-2 flex-nowrap"' + title + '>' +
                '<img src="' + img + '" alt="" class="rounded-circle" style="width:19px;height:19px;object-fit:cover;flex-shrink:0;">' +
                '<strong style="font-size:11px;line-height:1.4;">' + firstNames.map(esc).join(firstNames.length > 1 ? '<br>' : ', ') + '</strong></div>';
        }

        var statusColors = {
            'Todo': {bg: '#0dcaf0', text: '#000'},
            'Working': {bg: '#ffc107', text: '#000'},
            'Archived': {bg: '#6c757d', text: '#fff'},
            'Done': {bg: '#28a745', text: '#fff'},
            'Missed': {bg: '#dc3545', text: '#fff'},
            'Need Help': {bg: '#fd7e14', text: '#000'},
            'Need Approval': {bg: '#6610f2', text: '#fff'},
            'Dependent': {bg: '#d63384', text: '#fff'},
            'Approved': {bg: '#20c997', text: '#000'},
            'Hold': {bg: '#495057', text: '#fff'},
            'Rework': {bg: '#f5576c', text: '#fff'}
        };

        var table = new Tabulator('#ydone-table', {
            data: ydoneRows,
            layout: 'fitData',
            pagination: true,
            paginationSize: 50,
            paginationSizeSelector: [25, 50, 100, 200],
            placeholder: 'No tasks were completed or missed yesterday.',
            height: 'calc(100vh - 220px)',
            rowFormatter: function (row) {
                var data = row.getData();
                var el = row.getElement();
                el.classList.remove('automated-task', 'ydone-missed');
                el.style.borderLeft = '';
                if (data.is_automate_task == 1) {
                    el.classList.add('automated-task');
                    el.style.borderLeft = '4px solid #ffc107';
                } else if (data.type === 'Missed' || data.status === 'Missed') {
                    el.classList.add('ydone-missed');
                }
            },
            columns: [
                {
                    title: 'CA', field: 'is_corrective_action', width: 44, hozAlign: 'center', headerTooltip: 'Corrective Action', headerSort: false,
                    formatter: function (cell) {
                        var v = cell.getValue();
                        if (v === true || v === 1 || v === '1') {
                            return '<img src="' + caIcon + '" alt="Corrective Action" title="Corrective Action" style="width:28px;height:28px;object-fit:contain;">';
                        }
                        return '<span style="color:#adb5bd;">-</span>';
                    }
                },
                {
                    title: 'GROUP', field: 'group', minWidth: 80,
                    formatter: function (cell) {
                        var value = cell.getValue();
                        return value ? '<span style="color:#6c757d;white-space:nowrap;">' + esc(value) + '</span>' : '<span style="color:#adb5bd;">-</span>';
                    }
                },
                {
                    title: 'TASK', field: 'title', width: 420,
                    formatter: function (cell) {
                        var row = cell.getRow().getData();
                        var title = String(cell.getValue() || '').replace(/\s*\[Auto:\s*\d{1,2}-[A-Za-z]{3}-\d{2}\]\s*$/i, '');
                        var html = esc(title)
                            .replace(/(Missing Mapping:\s*)([\d,]+)/i, '$1<span style="color:#a71d2a;font-weight:700;">$2</span>')
                            .replace(/(\(MISMATCH:\s*)([\d,]+)(\))/i, '$1<span style="color:#a71d2a;font-weight:700;">$2</span>$3');
                        var sub = row.parent_task_id ? '<span class="badge bg-secondary ms-1" style="font-size:9px;">Subtask</span>' : '';
                        return '<div style="white-space:normal;line-height:1.4;text-align:left;"><strong style="font-size:13px;">' + html + '</strong>' + sub + '</div>';
                    }
                },
                {
                    title: 'ASSIGNOR', field: 'assignor_name', hozAlign: 'center',
                    formatter: function (cell) {
                        var row = cell.getRow().getData();
                        return personCell(cell.getValue(), row.assignor_avatar, row.assignor_designation);
                    }
                },
                {
                    title: 'ASSIGNEE', field: 'assignee_name', hozAlign: 'center',
                    formatter: function (cell) {
                        var row = cell.getRow().getData();
                        return personCell(cell.getValue(), row.assignee_avatar, row.assignee_designation);
                    }
                },
                {
                    title: 'TID', field: 'start_date', width: 88, hozAlign: 'center', headerTooltip: 'Task Initiation Date',
                    formatter: function (cell) {
                        var row = cell.getRow().getData();
                        var fp = formatTid(row.tid_business_date || cell.getValue());
                        if (!fp) return '<span style="color:#adb5bd;">-</span>';
                        var missed = row.type === 'Missed' || row.status === 'Missed';
                        return '<span style="color:' + (missed ? '#dc3545' : '#0d6efd') + ';font-weight:600;font-size:11px;" title="' + esc(fp.title) + '">' + esc(fp.label) + '</span>';
                    }
                },
                {
                    title: 'TAT', field: 'tat', width: 64, hozAlign: 'center', headerTooltip: 'Days from TID to completion',
                    formatter: function (cell) {
                        var value = cell.getValue();
                        if (value === null || value === undefined || value === '') return '<span style="color:#adb5bd;">-</span>';
                        var d = Math.round(Number(value));
                        if (isNaN(d)) return '<span style="color:#adb5bd;">-</span>';
                        return '<span style="font-weight:600;" title="' + d + (d === 1 ? ' day' : ' days') + '">' + d + ' D</span>';
                    }
                },
                {
                    title: 'ETC', field: 'eta_time', width: 52, hozAlign: 'center', cssClass: 'tasks-col-time-compact', headerTooltip: 'Estimated time (minutes)',
                    formatter: function (cell) {
                        var n = Math.round(Number(cell.getValue()));
                        if (isNaN(n) || cell.getValue() === '' || cell.getValue() == null) return '<span style="color:#adb5bd;">-</span>';
                        return '<span style="font-size:11px;" title="' + n + ' min">' + n + '</span>';
                    }
                },
                {
                    title: 'ATC', field: 'etc_done', width: 52, hozAlign: 'center', cssClass: 'tasks-col-time-compact', headerTooltip: 'Actual time (minutes)',
                    formatter: function (cell) {
                        var n = Math.round(Number(cell.getValue()));
                        if (!isNaN(n) && n > 0) return '<strong style="color:#28a745;font-size:11px;" title="' + n + ' min">' + n + '</strong>';
                        return '<span style="color:#adb5bd;">0</span>';
                    }
                },
                { title: 'L1', field: 'link1', width: 38, hozAlign: 'center', headerSort: false, cssClass: 'tasks-col-link-icon', formatter: function (cell) { return linkSlot(cell.getRow().getData().link1, linkIcon, 'Open L1'); } },
                { title: 'L2', field: 'link2', width: 38, hozAlign: 'center', headerSort: false, cssClass: 'tasks-col-link-icon', formatter: function (cell) { return linkSlot(cell.getRow().getData().link2, linkIcon, 'Open L2'); } },
                { title: 'SOP', field: 'link3', width: 48, hozAlign: 'center', headerSort: false, headerTooltip: 'Standard Operating Procedure', cssClass: 'tasks-col-link-icon', formatter: function (cell) { return linkSlot(cell.getRow().getData().link3, sopIcon, 'Open SOP'); } },
                { title: 'Video', field: 'link4', width: 44, hozAlign: 'center', headerSort: false, headerTooltip: 'Video', cssClass: 'tasks-col-link-icon', formatter: function (cell) { return linkSlot(cell.getRow().getData().link4, videoIcon, 'Open video'); } },
                { title: 'CL', field: 'link7', width: 38, hozAlign: 'center', headerSort: false, headerTooltip: 'Checklist', cssClass: 'tasks-col-link-icon', formatter: function (cell) { return linkSlot(cell.getRow().getData().link7, linkIcon, 'Open checklist'); } },
                { title: 'Report', field: 'link6', width: 50, hozAlign: 'center', headerSort: false, headerTooltip: 'Form report', cssClass: 'tasks-col-link-icon', formatter: function (cell) { return linkSlot(cell.getRow().getData().link6, linkIcon, 'Open report'); } },
                {
                    title: 'STATUS', field: 'status', hozAlign: 'center',
                    formatter: function (cell) {
                        var value = cell.getValue() || 'Done';
                        var color = statusColors[value] || {bg: '#6c757d', text: '#fff'};
                        return '<span style="background:' + color.bg + ';color:' + color.text + ';padding:4.8px 9.6px;border-radius:16px;font-size:8.8px;font-weight:700;display:inline-block;white-space:nowrap;">' + esc(value) + '</span>';
                    }
                },
                {
                    title: 'P', field: 'priority', width: 44, hozAlign: 'center', headerTooltip: 'Priority', cssClass: 'tasks-col-priority-compact',
                    formatter: function (cell) {
                        var key = String(cell.getValue() || 'normal').toLowerCase();
                        var colors = { low: '#9ca3af', normal: '#fbbf24', high: '#fd7e14', urgent: '#fd7e14' };
                        var labels = { high: 'URGENT', urgent: 'URGENT', normal: 'NORMAL', low: 'LOW' };
                        var label = labels[key] || String(cell.getValue() || 'NORMAL').toUpperCase();
                        return '<span style="display:inline-block;width:16px;height:16px;border-radius:50%;background:' + (colors[key] || colors.normal) + ';box-shadow:0 2px 4px rgba(0,0,0,0.2);" title="' + esc(label) + '"></span>';
                    }
                },
                {
                    title: 'ARCHIVED', field: 'deleted_by', hozAlign: 'center', headerTooltip: 'Archived by',
                    formatter: function (cell) {
                        var row = cell.getRow().getData();
                        if (!row.deleted) return '<span style="color:#adb5bd;">-</span>';
                        if (row.type === 'Missed' || row.status === 'Missed') {
                            return '<strong style="color:#dc3545;">AUTO</strong>';
                        }
                        var name = String(cell.getValue() || '').trim().split(/\s+/)[0];
                        return name ? '<strong style="color:#212529;">' + esc(name) + '</strong>' : '<span style="color:#adb5bd;">-</span>';
                    }
                }
            ]
        });
    </script>
@endsection
