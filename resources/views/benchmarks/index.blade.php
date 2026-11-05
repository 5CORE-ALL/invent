@extends('layouts.vertical', ['title' => 'Benchmarks', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        #benchmarks-table .tabulator-col .tabulator-col-title,
        #benchmarks-table .tabulator-cell {
            font-size: 12px;
        }
        #benchmarks-table .tabulator-col .tabulator-col-title {
            white-space: normal;
        }
        #benchmarks-table .tabulator-row.bm-yours .tabulator-cell {
            background: #eef2ff;
            font-weight: 600;
        }
        #benchmarks-table .tabulator-row.bm-top .tabulator-cell {
            background: #f0fdf4;
        }
        #benchmarks-table .tabulator-row.bm-bottom .tabulator-cell {
            background: #fef2f2;
        }
        #benchmarks-table .tabulator-cell.bm-na {
            color: #9ca3af;
        }
        #benchmarks-table .tabulator-group {
            background: #e0e7ff;
            font-weight: 700;
        }
        .bm-toolbar .form-select,
        .bm-toolbar .form-control {
            width: auto;
            min-width: 150px;
        }
    </style>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        (function() {
            const DATA_URL = @json(route('benchmarks.data'));
            const FIXED_FIELDS = ['category', 'section', 'benchmark', 'metric', 'value'];

            const $category = document.getElementById('bm-category');
            const $section = document.getElementById('bm-section');
            const $benchmark = document.getElementById('bm-benchmark');
            const $search = document.getElementById('bm-search');
            const $group = document.getElementById('bm-group');
            const $count = document.getElementById('bm-count');
            const $source = document.getElementById('bm-source');
            const $empty = document.getElementById('bm-empty');

            /** "96%", "1,234", "10.2" -> number; anything else ("Not relevant", "") -> null. */
            function toNumber(v) {
                if (v === null || v === undefined) return null;
                const s = String(v).replace(/[%,$\s]/g, '');
                if (s === '' || isNaN(Number(s))) return null;
                return Number(s);
            }

            /** Numeric sort on the displayed text; non-numeric cells always sink to the bottom. */
            function textNumberSorter(a, b, aRow, bRow, column, dir) {
                const x = toNumber(a), y = toNumber(b);
                if (x === null && y === null) return 0;
                if (x === null) return dir === 'asc' ? 1 : -1;
                if (y === null) return dir === 'asc' ? -1 : 1;
                return x - y;
            }

            function valueFormatter(cell) {
                const v = cell.getValue();
                if (v === '' || v === null || v === undefined) return '';
                if (toNumber(v) === null) {
                    cell.getElement().classList.add('bm-na');
                }
                return String(v).replace(/[<>&]/g, function(c) {
                    return { '<': '&lt;', '>': '&gt;', '&': '&amp;' }[c];
                });
            }

            function fillSelect(el, values, allLabel) {
                const current = el.value;
                el.innerHTML = '';
                el.appendChild(new Option(allLabel, ''));
                values.forEach(function(v) { el.appendChild(new Option(v, v)); });
                if (values.indexOf(current) !== -1) el.value = current;
            }

            function distinct(rows, key) {
                const seen = [];
                rows.forEach(function(r) { if (r[key] && seen.indexOf(r[key]) === -1) seen.push(r[key]); });
                return seen;
            }

            let table = null;

            function applyFilters() {
                if (!table) return;
                const cat = $category.value, sec = $section.value, bm = $benchmark.value;
                const q = $search.value.trim().toLowerCase();
                const filters = [];
                if (cat) filters.push({ field: 'category', type: '=', value: cat });
                if (sec) filters.push({ field: 'section', type: '=', value: sec });
                if (bm) filters.push({ field: 'benchmark', type: '=', value: bm });
                table.setFilter(filters.length ? filters : []);
                if (q) {
                    table.addFilter(function(row) {
                        return Object.keys(row).some(function(k) {
                            return String(row[k] == null ? '' : row[k]).toLowerCase().indexOf(q) !== -1;
                        });
                    });
                }
                $count.textContent = 'Rows: ' + table.getDataCount('active').toLocaleString();
            }

            function build(payload) {
                const rows = payload.rows || [];
                $source.textContent = payload.source && payload.source.filename
                    ? payload.source.filename : 'No workbook uploaded';

                if (!rows.length) {
                    $empty.classList.remove('d-none');
                    document.getElementById('benchmarks-table').classList.add('d-none');
                    return;
                }

                fillSelect($category, distinct(rows, 'category'), 'All categories');
                fillSelect($section, distinct(rows, 'section'), 'All sections');

                const columns = [
                    { title: 'Category', field: 'category', frozen: true, width: 200, headerFilter: 'input',
                      headerFilterPlaceholder: 'Search...', sorter: 'string' },
                    { title: 'Section', field: 'section', frozen: true, width: 170, sorter: 'string' },
                    { title: 'Benchmark', field: 'benchmark', frozen: true, width: 100, sorter: 'string',
                      tooltip: function(e, cell) { return cell.getRow().getData().benchmark_label || ''; } },
                    { title: 'Metric', field: 'metric', width: 170, sorter: 'string' },
                    { title: 'Value', field: 'value', width: 90, hozAlign: 'right',
                      sorter: textNumberSorter, formatter: valueFormatter },
                    { title: 'Price Range', field: 'price_range', width: 110, sorter: 'string' },
                    { title: 'Sales Rank', field: 'sales_rank', width: 100, hozAlign: 'right' },
                ];

                (payload.columns || []).forEach(function(col) {
                    columns.push({
                        title: col.title, field: col.field, width: 110, hozAlign: 'right',
                        sorter: textNumberSorter, formatter: valueFormatter,
                        headerTooltip: col.title
                    });
                });

                table = new Tabulator('#benchmarks-table', {
                    data: rows,
                    layout: 'fitData',
                    height: 'calc(100vh - 330px)',
                    columns: columns,
                    groupBy: $group.checked ? 'category' : false,
                    groupStartOpen: true,
                    groupToggleElement: 'header',
                    rowFormatter: function(row) {
                        const el = row.getElement();
                        const b = row.getData().benchmark;
                        el.classList.remove('bm-yours', 'bm-top', 'bm-bottom');
                        if (b === 'Yours') el.classList.add('bm-yours');
                        else if (b === 'Top 10%') el.classList.add('bm-top');
                        else if (b === 'Bottom 10%') el.classList.add('bm-bottom');
                    }
                });
                table.on('tableBuilt', applyFilters);
                table.on('dataFiltered', function() {
                    $count.textContent = 'Rows: ' + table.getDataCount('active').toLocaleString();
                });
            }

            [$category, $section, $benchmark].forEach(function(el) { el.addEventListener('change', applyFilters); });
            $search.addEventListener('input', applyFilters);
            $group.addEventListener('change', function() {
                if (table) table.setGroupBy($group.checked ? 'category' : false);
            });

            fetch(DATA_URL, { headers: { 'Accept': 'application/json' } })
                .then(function(r) { return r.json(); })
                .then(build)
                .catch(function() {
                    $source.textContent = 'Could not load benchmarks';
                    $empty.classList.remove('d-none');
                });
        })();
    </script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Benchmarks',
        'sub_title' => 'More benchmarks by category (from /excel eBay LQS upload)',
    ])

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-2">
                <div class="d-flex align-items-center flex-wrap gap-2 bm-toolbar mb-2">
                    <select id="bm-category" class="form-select form-select-sm" title="Category">
                        <option value="">All categories</option>
                    </select>
                    <select id="bm-section" class="form-select form-select-sm" title="Section">
                        <option value="">All sections</option>
                    </select>
                    <select id="bm-benchmark" class="form-select form-select-sm" title="Benchmark">
                        <option value="">All benchmarks</option>
                        <option value="Yours">Yours</option>
                        <option value="Top 10%">Top 10%</option>
                        <option value="Bottom 10%">Bottom 10%</option>
                    </select>
                    <input type="search" id="bm-search" class="form-control form-control-sm" placeholder="Search...">
                    <div class="form-check form-switch ms-1">
                        <input class="form-check-input" type="checkbox" id="bm-group" checked>
                        <label class="form-check-label small" for="bm-group">Group by category</label>
                    </div>
                    <span class="badge bg-dark fs-6 p-2" id="bm-count">Rows: 0</span>
                    <span class="ms-auto small text-muted" id="bm-source"></span>
                </div>

                <div id="bm-empty" class="alert alert-info d-none mb-2">
                    No benchmarks found. Upload the eBay Listing Quality report in
                    <a href="{{ route('excel.index') }}">eBay LQS</a> first.
                </div>

                <div id="benchmarks-table"></div>
            </div>
        </div>
    </div>
@endsection
