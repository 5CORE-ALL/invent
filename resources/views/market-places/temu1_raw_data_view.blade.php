@extends('layouts.vertical', ['title' => 'Temu 1 Raw Data', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        #temu1-raw-table .tabulator-header .tabulator-col .tabulator-col-title {
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }

        #temu1-raw-table .tabulator-cell {
            font-size: 12px;
            padding: 4px 8px;
        }

        #temu1-raw-table .tabulator-header-filter input {
            font-size: 11px;
            padding: 1px 4px;
        }

        #temu1-raw-table .t1r-api-col.tabulator-cell {
            background: #f6fbff;
        }

        .t1r-null {
            color: #adb5bd;
        }

        .t1r-long {
            color: #495057;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 11px;
        }

        .t1r-view {
            cursor: pointer;
            color: #0d6efd;
            font-weight: 600;
            margin-left: 6px;
            white-space: nowrap;
        }

        #t1r-cell-body {
            max-height: 65vh;
            overflow: auto;
            white-space: pre-wrap;
            word-break: break-all;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 12px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 10px;
        }
    </style>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Temu 1 Raw Data',
        'sub_title' => 'Temu 1 Raw Data',
    ])

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-2">
                <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                    <ul class="nav nav-pills" id="t1r-tabs">
                        @foreach ($sources as $key => $s)
                            <li class="nav-item">
                                <a href="#" class="nav-link py-1 px-3 {{ $loop->first ? 'active' : '' }} {{ $s['exists'] ? '' : 'disabled' }}"
                                    data-source="{{ $key }}" title="{{ $s['table'] }}">
                                    {{ $s['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>

                    <div class="input-group input-group-sm" style="width: 280px;">
                        <span class="input-group-text"><i class="fa fa-search"></i></span>
                        <input type="search" id="t1r-search" class="form-control" placeholder="Search all columns...">
                    </div>

                    <button type="button" class="btn btn-sm btn-outline-primary d-none" id="t1r-refresh-api" title="Call the Temu API again (slow) instead of using the cached pull">
                        <i class="fa fa-cloud-arrow-down"></i> Refresh from API
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="t1r-reload" title="Reload">
                        <i class="fa fa-rotate"></i> Reload
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="t1r-clear-filters">
                        Clear filters
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-success" id="t1r-csv" title="Download the rows on this page">
                        <i class="fa fa-download"></i> CSV (this page)
                    </button>

                    <span class="badge bg-primary" id="t1r-total">0 rows</span>
                    <span class="text-muted small" id="t1r-meta"></span>
                </div>

                <div id="temu1-raw-table" style="height: calc(100vh - 230px);"></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="t1r-cell-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title" id="t1r-cell-title">Value</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="t1r-cell-body"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="t1r-cell-copy">Copy</button>
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('script-bottom')
    <script>
        (function() {
            const URLS = {
                columns: @json(url('/temu1-raw-data/columns')),
                data: @json(url('/temu1-raw-data/data')),
                cell: @json(url('/temu1-raw-data/cell')),
            };
            const SOURCES = @json($sources);

            let table = null;
            let source = Object.keys(SOURCES).find(k => SOURCES[k].exists) || 'orders';
            let searchTimer = null;

            function esc(v) {
                return String(v).replace(/[&<>"']/g, c => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                } [c]));
            }

            function buildColumn(col) {
                const isApi = col.kind === 'api';
                const def = {
                    title: col.name,
                    field: col.name,
                    headerSort: col.sortable,
                    headerTooltip: col.name + (isApi ? ' (API field)' : ' (' + col.type + ')'),
                    minWidth: 70,
                };

                if (col.sortable) {
                    def.headerFilter = 'input';
                    def.headerFilterPlaceholder = 'filter';
                }

                if (col.name === 'id' && !isApi) {
                    def.frozen = true;
                    def.width = 70;
                }

                if (isApi) {
                    def.cssClass = 't1r-api-col';
                }

                if (col.long) {
                    def.width = col.heavy || isApi ? 260 : 220;
                    def.formatter = function(cell) {
                        const v = cell.getValue();
                        if (v === null || v === undefined) return '<span class="t1r-null">—</span>';
                        const str = String(v);
                        // Stored text columns send a preview + __len; API fields carry the full value.
                        const len = isApi ? str.length : (cell.getRow().getData()[col.name + '__len'] || 0);
                        const more = len > str.length || (isApi && str.length > 160);
                        const shown = isApi ? str.slice(0, 160) : str;
                        let html = '<span class="t1r-long">' + esc(shown.replace(/\s+/g, ' ')) + (more ? '…' : '') + '</span>';
                        if (more || (col.heavy && !isApi)) {
                            html += '<span class="t1r-view" data-t1r-view="1">View (' + Number(len).toLocaleString() + ')</span>';
                        }
                        return html;
                    };
                    def.cellClick = function(e, cell) {
                        if (!e.target.closest('[data-t1r-view]')) return;
                        if (isApi) {
                            showValue(col.name, cell.getValue());
                        } else {
                            openCell(cell.getRow().getData().id, col.name);
                        }
                    };
                } else {
                    def.formatter = function(cell) {
                        const v = cell.getValue();
                        if (v === null || v === undefined || v === '') return '<span class="t1r-null">—</span>';
                        return esc(v);
                    };
                }

                return def;
            }

            function showValue(title, value) {
                let text = value === null || value === undefined ? '' : String(value);
                try {
                    const first = text.trim().charAt(0);
                    if (first === '{' || first === '[') text = JSON.stringify(JSON.parse(text), null, 2);
                } catch (e) {}
                document.getElementById('t1r-cell-title').textContent = title;
                document.getElementById('t1r-cell-body').textContent = text === '' ? '(empty)' : text;
                bootstrap.Modal.getOrCreateInstance(document.getElementById('t1r-cell-modal')).show();
            }

            function openCell(id, column) {
                const body = document.getElementById('t1r-cell-body');
                document.getElementById('t1r-cell-title').textContent = column + ' · id ' + id;
                body.textContent = 'Loading...';
                bootstrap.Modal.getOrCreateInstance(document.getElementById('t1r-cell-modal')).show();
                $.getJSON(URLS.cell, {
                        source: source,
                        id: id,
                        column: column
                    })
                    .done(res => {
                        body.textContent = res.value === '' ? '(empty)' : res.value;
                    })
                    .fail(xhr => {
                        body.textContent = 'Failed to load value (' + xhr.status + ').';
                    });
            }

            function setMeta(extra) {
                const s = SOURCES[source];
                document.getElementById('t1r-meta').textContent = s ?
                    ((s.table ? s.table + ' — ' : '') + s.api + (extra ? ' · ' + extra : '')) : '';
            }

            function load(refresh) {
                setMeta();
                if (table) {
                    table.destroy();
                    table = null;
                }
                const live = !!(SOURCES[source] && SOURCES[source].live);
                document.getElementById('t1r-refresh-api').classList.toggle('d-none', !live);
                document.getElementById('t1r-total').textContent = live ? 'Calling Temu API…' : 'Loading...';
                let pendingRefresh = !!refresh;

                $.getJSON(URLS.columns, {
                    source: source,
                    refresh: pendingRefresh ? 1 : 0
                }).done(res => {
                    if (res.fetched_at) setMeta('pulled ' + res.fetched_at);
                    const columns = res.columns.map(buildColumn);

                    table = new Tabulator('#temu1-raw-table', {
                        ajaxURL: URLS.data,
                        ajaxParams: function() {
                            return {
                                source: source,
                                q: document.getElementById('t1r-search').value.trim()
                            };
                        },
                        ajaxConfig: {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        },
                        nestedFieldSeparator: false, // field names are dotted API paths, not nested objects
                        pagination: true,
                        paginationMode: 'remote',
                        filterMode: 'remote',
                        sortMode: 'remote',
                        paginationSize: 50,
                        paginationSizeSelector: [25, 50, 100, 200, 500],
                        paginationCounter: 'rows',
                        layout: 'fitData',
                        renderHorizontal: 'virtual',
                        columns: columns,
                        placeholder: 'No rows',
                        ajaxResponse: function(url, params, response) {
                            document.getElementById('t1r-total').textContent =
                                Number(response.total || 0).toLocaleString() + ' rows · ' + columns.length + ' columns';
                            if (response.fetched_at) setMeta('pulled ' + response.fetched_at);
                            return response;
                        },
                        ajaxError: function(err) {
                            document.getElementById('t1r-total').textContent = 'Load failed';
                            console.error('Temu1 raw data load failed', err);
                        },
                    });
                }).fail(xhr => {
                    const msg = xhr.responseJSON && xhr.responseJSON.message;
                    document.getElementById('t1r-total').textContent =
                        msg ? msg : (xhr.status === 404 ? 'Table not available on this environment' : 'Failed to load columns');
                });
            }

            // Source tabs
            document.querySelectorAll('#t1r-tabs .nav-link').forEach(a => {
                a.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (this.classList.contains('disabled')) return;
                    document.querySelectorAll('#t1r-tabs .nav-link').forEach(x => x.classList.remove('active'));
                    this.classList.add('active');
                    source = this.dataset.source;
                    load();
                });
            });

            // Global search (debounced, server-side)
            document.getElementById('t1r-search').addEventListener('input', function() {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => table && table.setPage(1), 350);
            });

            document.getElementById('t1r-reload').addEventListener('click', () => table && table.setData());
            document.getElementById('t1r-refresh-api').addEventListener('click', () => load(true));
            document.getElementById('t1r-clear-filters').addEventListener('click', () => {
                document.getElementById('t1r-search').value = '';
                table && table.clearHeaderFilter();
            });
            document.getElementById('t1r-csv').addEventListener('click', () => {
                table && table.download('csv', 'temu1-raw-' + source + '.csv');
            });
            document.getElementById('t1r-cell-copy').addEventListener('click', () => {
                navigator.clipboard && navigator.clipboard.writeText(document.getElementById('t1r-cell-body').textContent);
            });

            // Initial tab: mark the first existing source active
            document.querySelectorAll('#t1r-tabs .nav-link').forEach(x => {
                x.classList.toggle('active', x.dataset.source === source);
            });
            load();
        })();
    </script>
@endsection
