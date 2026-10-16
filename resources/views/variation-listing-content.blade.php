@extends('layouts.vertical', ['title' => 'Variation Title, Bullet Points and Description', 'mode' => $mode ?? '', 'demo' => $demo ?? '', 'sidenav' => 'condensed', 'skipHighcharts' => true])

@section('css')
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    @include('partials.marketplace-master-button-colors')
    <style>
        #vlc-table-wrapper {
            height: calc(100vh - 168px);
            min-height: 280px;
            display: flex;
            flex-direction: column;
            background: #fff;
        }

        #vlc-tabulator {
            flex: 1;
            min-height: 0;
            width: 100%;
            border-top: 1px solid #dee2e6;
        }

        #vlc-tabulator .tabulator {
            font-size: 11px;
            width: 100% !important;
            border: 1px solid #64748b;
        }

        #vlc-tabulator .tabulator-header {
            background: #e8ecf1;
            color: #1e293b;
            font-weight: 600;
            border-bottom: 1px solid #64748b;
        }

        #vlc-tabulator .tabulator-header .tabulator-col {
            border-right: 1px solid #64748b;
        }

        #vlc-tabulator .tabulator-header .tabulator-col-content,
        #vlc-tabulator .tabulator-header .tabulator-col-title,
        #vlc-tabulator .tabulator-header-filter {
            text-align: center;
        }

        #vlc-tabulator .tabulator-header .tabulator-col-title {
            font-size: 11px;
            font-weight: 600;
            color: #334155;
        }

        #vlc-tabulator .tabulator-header-filter input {
            text-align: center;
        }

        #vlc-tabulator .tabulator-row .tabulator-cell {
            padding: 4px 6px;
            vertical-align: middle;
            text-align: center;
            border-bottom: 1px solid #94a3b8;
            border-right: 1px solid #64748b;
            font-size: 11px;
        }

        #vlc-tabulator .tabulator-cell.vlc-parent {
            font-weight: 700;
            color: #0f172a;
        }

        #vlc-tabulator .tabulator-row.tabulator-row-even .tabulator-cell {
            background-color: #fafafa;
        }

        #vlc-tabulator .tabulator-row:hover .tabulator-cell {
            background-color: #f1f5f9;
        }

        #vlc-tabulator .tabulator-cell.vlc-editable {
            background-color: #f0fdfa;
            text-align: left;
            vertical-align: top;
        }

        #vlc-tabulator .vlc-text {
            white-space: pre-wrap;
            text-align: left;
            max-height: 72px;
            overflow: auto;
        }

        .vlc-toolbar {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('layouts.shared.page-title', [
        'page_title' => 'Variation Title, Bullet Points and Description',
        'sub_title' => 'Product Masters',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-2">
                    <div class="vlc-toolbar">
                        <button type="button" class="btn btn-sm btn-primary" id="vlc-upload-btn">
                            <i class="bi bi-upload me-1"></i>Upload CSV
                        </button>
                        <a class="btn btn-sm btn-outline-success" href="{{ route('variation.listing.content.download') }}">
                            <i class="bi bi-filetype-csv me-1"></i>Download CSV
                        </a>
                        <input type="file" id="vlc-upload-file" class="d-none" accept=".csv,.txt,.xlsx,.xls">
                        <span class="small text-muted ms-2">Parent comes from CP master. Title, Description, and Bullet Points (one per line) are used when a variation listing is published on Listing Manager or Missing Listing. Empty fields keep the existing master text. Single listings are unchanged.</span>
                        <span class="small ms-auto" id="vlc-status"></span>
                    </div>
                </div>
                <div id="vlc-table-wrapper">
                    <div id="vlc-tabulator"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const dataUrl = @json(route('variation.listing.content.data'));
            const saveUrl = @json(route('variation.listing.content.save'));
            const importUrl = @json(route('variation.listing.content.import'));
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const statusEl = document.getElementById('vlc-status');

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function setStatus(text, ok) {
                if (!statusEl) return;
                statusEl.textContent = text || '';
                statusEl.className = 'small ms-auto ' + (ok === false ? 'text-danger' : 'text-success');
            }

            function textFormatter(cell) {
                const value = String(cell.getValue() || '');
                if (!value) return '';
                return '<div class="vlc-text">' + escapeHtml(value) + '</div>';
            }

            const table = new Tabulator('#vlc-tabulator', {
                ajaxURL: dataUrl,
                ajaxResponse: function (_url, _params, response) {
                    return (response && Array.isArray(response.data)) ? response.data : [];
                },
                index: 'parent',
                layout: 'fitColumns',
                height: '100%',
                placeholder: 'No parent groups on CP master',
                columnDefaults: {
                    hozAlign: 'center',
                    headerHozAlign: 'center',
                    vertAlign: 'middle'
                },
                columns: [
                    {
                        title: 'Parent',
                        field: 'parent',
                        headerFilter: 'input',
                        headerFilterPlaceholder: 'Parent',
                        minWidth: 160,
                        widthGrow: 1,
                        cssClass: 'vlc-parent',
                        formatter: function (cell) {
                            const v = String(cell.getValue() || '').trim();
                            return v ? '<strong>' + escapeHtml(v) + '</strong>' : '—';
                        }
                    },
                    {
                        title: 'Title',
                        field: 'title',
                        editor: 'textarea',
                        headerFilter: 'input',
                        headerFilterPlaceholder: 'Title',
                        minWidth: 220,
                        widthGrow: 2,
                        cssClass: 'vlc-editable',
                        formatter: textFormatter
                    },
                    {
                        title: 'Description',
                        field: 'description',
                        editor: 'textarea',
                        headerFilter: 'input',
                        headerFilterPlaceholder: 'Description',
                        minWidth: 280,
                        widthGrow: 3,
                        cssClass: 'vlc-editable',
                        formatter: textFormatter
                    },
                    {
                        title: 'Bullet Points',
                        field: 'bullets',
                        editor: 'textarea',
                        headerFilter: 'input',
                        headerFilterPlaceholder: 'One per line',
                        minWidth: 240,
                        widthGrow: 2,
                        cssClass: 'vlc-editable',
                        formatter: textFormatter
                    }
                ]
            });

            table.on('cellEdited', function (cell) {
                const field = cell.getField();
                if (field !== 'title' && field !== 'description' && field !== 'bullets') return;
                const row = cell.getRow().getData();
                fetch(saveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf
                    },
                    body: JSON.stringify({
                        parent: row.parent || '',
                        title: row.title || '',
                        description: row.description || '',
                        bullets: row.bullets || ''
                    })
                }).then(function (res) {
                    return res.json().then(function (json) {
                        return { ok: res.ok, json: json };
                    });
                }).then(function (result) {
                    if (!result.ok || !result.json || result.json.success === false) {
                        throw new Error((result.json && result.json.message) || 'Save failed');
                    }
                    if (result.json.id) {
                        cell.getRow().update({ id: result.json.id });
                    }
                    setStatus('Saved ' + (row.parent || ''), true);
                }).catch(function (err) {
                    cell.restoreOldValue();
                    setStatus(err && err.message ? err.message : 'Could not save ' + (row.parent || ''), false);
                });
            });

            document.getElementById('vlc-upload-btn')?.addEventListener('click', function () {
                document.getElementById('vlc-upload-file')?.click();
            });

            document.getElementById('vlc-upload-file')?.addEventListener('change', function () {
                const file = this.files && this.files[0];
                this.value = '';
                if (!file) return;
                const body = new FormData();
                body.append('file', file);
                setStatus('Uploading…', true);
                fetch(importUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf
                    },
                    body: body
                }).then(function (res) {
                    return res.json().then(function (json) {
                        return { ok: res.ok, json: json };
                    });
                }).then(function (result) {
                    const message = (result.json && result.json.message) || (result.ok ? 'Uploaded.' : 'Upload failed.');
                    setStatus(message, result.ok && result.json && result.json.success !== false);
                    if (result.ok) table.replaceData();
                }).catch(function () {
                    setStatus('Upload failed.', false);
                });
            });
        });
    </script>
@endsection
