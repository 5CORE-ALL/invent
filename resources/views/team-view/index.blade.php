@extends('layouts.vertical', ['title' => 'Team View', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        #team-table .tabulator-row .tabulator-cell { vertical-align: middle; }
        #team-table .tabulator-row.tv-group-row { background: #f1f5f9; font-weight: 700; }
        .tv-person { display: flex; align-items: center; gap: 10px; }
        .tv-avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; flex-shrink: 0; border: 2px solid #fff; box-shadow: 0 0 0 1px #d5dce8; }
        .tv-name { font-weight: 700; color: #0f172a; }
        .tv-badge { font-size: 11px; font-weight: 700; color: #fff; border-radius: 10px; padding: 2px 8px; display: inline-block; }
        .tv-badge-director { background: #7c3aed; }
        .tv-badge-mgr { background: #3b7ddd; }
        .tv-badge-exec { background: #20c997; }
        .tv-count { font-size: 11px; font-weight: 600; color: #475569; background: #eef2f7; border-radius: 10px; padding: 2px 8px; }
        .tv-link { color: #334155; text-decoration: none; }
        .tv-link:hover { color: #3b7ddd; text-decoration: underline; }
        .tv-rr summary { cursor: pointer; color: #3b7ddd; font-weight: 600; }
        .tv-rr ul { margin: 6px 0 0; padding-left: 18px; color: #334155; max-height: 220px; overflow-y: auto; white-space: normal; }
        .tv-legend span { margin-right: 14px; font-size: 13px; }
        .tv-legend i { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 4px; }
    </style>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-12">
                <div class="page-title-box d-flex align-items-center justify-content-between py-1 flex-wrap gap-2">
                    <h4 class="page-title mb-0">Team View</h4>
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div class="tv-legend">
                            <span><i style="background:#7c3aed"></i>Director</span>
                            <span><i style="background:#3b7ddd"></i>Manager</span>
                            <span><i style="background:#20c997"></i>Executive</span>
                        </div>
                        <button type="button" id="tvExpandAll" class="btn btn-sm btn-outline-secondary">Expand all</button>
                        <button type="button" id="tvCollapseAll" class="btn btn-sm btn-outline-secondary">Collapse all</button>
                        <input type="search" id="tvSearch" class="form-control form-control-sm" style="width:260px"
                               placeholder="Search name, designation, email, phone, R&R">
                    </div>
                </div>
                <div class="text-muted small">{{ $total }} active team members &middot; read-only</div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body p-2">
                        <div id="team-table"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        (function () {
            var rows = @json($rows);
            var levelLabels = { director: 'Director', mgr: 'Manager', exec: 'Executive' };

            function esc(s) {
                return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
                });
            }

            var table = new Tabulator('#team-table', {
                data: rows,
                layout: 'fitColumns',
                height: 'calc(100vh - 260px)',
                dataTree: true,
                dataTreeStartExpanded: true,
                dataTreeChildIndent: 24,
                dataTreeFilter: true,
                placeholder: 'No team members found.',
                rowFormatter: function (row) {
                    if (row.getData().group) {
                        row.getElement().classList.add('tv-group-row');
                    }
                },
                columns: [
                    {
                        title: 'Name', field: 'name', minWidth: 280, widthGrow: 2, headerSort: true,
                        formatter: function (cell) {
                            var d = cell.getRow().getData();
                            if (d.group) return '<span class="tv-name">' + esc(d.name) + '</span> <span class="tv-count">' + d.reports + '</span>';
                            return '<div class="tv-person"><img class="tv-avatar" loading="lazy" src="' + esc(d.avatar) + '" alt="">' +
                                '<span class="tv-name">' + esc(d.name) + '</span></div>';
                        }
                    },
                    {
                        title: 'Level', field: 'level', width: 110, hozAlign: 'center',
                        formatter: function (cell) {
                            var v = cell.getValue();
                            return levelLabels[v] ? '<span class="tv-badge tv-badge-' + esc(v) + '">' + levelLabels[v] + '</span>' : '';
                        }
                    },
                    {
                        title: 'Designation', field: 'designation', minWidth: 160, widthGrow: 1,
                        formatter: function (cell) { return esc(cell.getValue() || (cell.getRow().getData().group ? '' : '—')); }
                    },
                    {
                        title: 'Email', field: 'email', minWidth: 200, widthGrow: 1,
                        formatter: function (cell) {
                            var v = cell.getValue();
                            return v ? '<a class="tv-link" href="mailto:' + esc(v) + '">' + esc(v) + '</a>' : '';
                        }
                    },
                    {
                        title: 'Phone', field: 'phone', width: 150,
                        formatter: function (cell) {
                            var v = cell.getValue();
                            return v ? '<a class="tv-link" href="tel:' + esc(String(v).replace(/[^0-9+]/g, '')) + '">' + esc(v) + '</a>' : '';
                        }
                    },
                    {
                        title: 'Reports', field: 'reports', width: 90, hozAlign: 'center', sorter: 'number',
                        formatter: function (cell) {
                            var v = cell.getValue();
                            return v ? '<span class="tv-count" title="Direct reports">' + v + '</span>' : '';
                        }
                    },
                    {
                        title: 'R&R', field: 'rr', minWidth: 200, widthGrow: 2, headerSort: false,
                        formatter: function (cell) {
                            var d = cell.getRow().getData();
                            if (d.group) return '';
                            var rr = cell.getValue() || [];
                            if (!rr.length) return '<span class="text-muted">Not defined</span>';
                            return '<details class="tv-rr"><summary>' + rr.length + ' responsibilit' + (rr.length === 1 ? 'y' : 'ies') +
                                '</summary><ul>' + rr.map(function (l) { return '<li>' + esc(l) + '</li>'; }).join('') + '</ul></details>';
                        }
                    }
                ]
            });

            // Grow/shrink the row when an R&R <details> is toggled ("toggle" does not bubble, so capture it).
            document.getElementById('team-table').addEventListener('toggle', function (e) {
                var el = e.target.closest('.tabulator-row');
                if (!el) return;
                var row = findRow(el);
                if (row) row.normalizeHeight();
            }, true);

            function findRow(el) {
                var found = null;
                (function walk(list) {
                    list.forEach(function (r) {
                        if (found) return;
                        if (r.getElement() === el) { found = r; return; }
                        walk(r.getTreeChildren());
                    });
                })(table.getRows());
                return found;
            }

            function setAll(expanded) {
                (function walk(list) {
                    list.forEach(function (r) {
                        var kids = r.getTreeChildren();
                        if (kids.length) {
                            expanded ? r.treeExpand() : r.treeCollapse();
                            walk(kids);
                        }
                    });
                })(table.getRows());
            }
            document.getElementById('tvExpandAll').addEventListener('click', function () { setAll(true); });
            document.getElementById('tvCollapseAll').addEventListener('click', function () { setAll(false); });

            // Search: a parent stays visible when any descendant matches (dataTreeFilter).
            var searchTerm = '';
            function matches(data) {
                return !searchTerm || (data.search || '').indexOf(searchTerm) !== -1;
            }
            document.getElementById('tvSearch').addEventListener('input', function () {
                searchTerm = this.value.trim().toLowerCase();
                if (searchTerm) {
                    table.setFilter(matches);
                } else {
                    table.clearFilter();
                }
            });
        })();
    </script>
@endsection
