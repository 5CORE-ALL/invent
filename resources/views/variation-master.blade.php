@extends('layouts.vertical', ['title' => 'Variation Master', 'mode' => $mode ?? '', 'demo' => $demo ?? '', 'sidenav' => 'condensed', 'skipHighcharts' => true])

@section('css')
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    @include('partials.marketplace-master-button-colors')
    <style>
        #vm-table-wrapper {
            height: calc(100vh - 168px);
            min-height: 280px;
            display: flex;
            flex-direction: column;
            background: #fff;
        }

        #vm-tabulator {
            flex: 1;
            min-height: 0;
            width: 100%;
            border-top: 1px solid #dee2e6;
        }

        #vm-tabulator .tabulator {
            font-size: 11px;
            width: 100% !important;
            border: 1px solid #d1d5db;
        }

        #vm-tabulator .tabulator-header {
            background: #e8ecf1;
            color: #1e293b;
            font-weight: 600;
            border-bottom: 1px solid #cbd5e1;
        }

        #vm-tabulator .tabulator-header .tabulator-col {
            border-right: 1px solid #cbd5e1;
        }

        #vm-tabulator .tabulator-header .tabulator-col-content,
        #vm-tabulator .tabulator-header .tabulator-col-title,
        #vm-tabulator .tabulator-header-filter {
            text-align: center;
        }

        #vm-tabulator .tabulator-header .tabulator-col-title {
            font-size: 11px;
            font-weight: 600;
            color: #334155;
        }

        #vm-tabulator .tabulator-header-filter input {
            text-align: center;
        }

        #vm-tabulator .tabulator-row .tabulator-cell {
            padding: 4px 6px;
            vertical-align: middle;
            text-align: center;
            border-bottom: 1px solid #e5e7eb;
            border-right: 1px solid #f1f5f9;
            font-size: 11px;
        }

        #vm-tabulator .tabulator-row.tabulator-row-even .tabulator-cell {
            background-color: #fafafa;
        }

        #vm-tabulator .tabulator-row:hover .tabulator-cell {
            background-color: #f1f5f9;
        }

        #vm-tabulator .tabulator-cell.vm-editable {
            background-color: #f0fdfa;
        }

        .vm-toolbar {
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
        'page_title' => 'Variation Master',
        'sub_title' => 'Product Masters',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-2">
                    <div class="vm-toolbar">
                        <button type="button" class="btn btn-sm btn-primary" id="vm-upload-btn">
                            <i class="bi bi-upload me-1"></i>Upload
                        </button>
                        <a class="btn btn-sm btn-success" href="{{ route('variation.master.download', ['format' => 'xlsx']) }}">
                            <i class="bi bi-file-earmark-excel me-1"></i>Download Excel
                        </a>
                        <a class="btn btn-sm btn-outline-success" href="{{ route('variation.master.download', ['format' => 'csv']) }}">
                            <i class="bi bi-filetype-csv me-1"></i>Download CSV
                        </a>
                        <input type="file" id="vm-upload-file" class="d-none" accept=".xlsx,.xls,.csv">
                        <span class="small text-muted ms-2">Parent SKU and Child SKU come from CP master. Type Variation Type and Variation Name here. Variation listings on Missing Listing and Listing Manager use these two fields.</span>
                        <span class="small ms-auto" id="vm-status"></span>
                    </div>
                </div>
                <div id="vm-table-wrapper">
                    <div id="vm-tabulator"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const dataUrl = @json(route('variation.master.data'));
            const saveUrl = @json(route('variation.master.save'));
            const importUrl = @json(route('variation.master.import'));
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const statusEl = document.getElementById('vm-status');

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

            const table = new Tabulator('#vm-tabulator', {
                ajaxURL: dataUrl,
                ajaxResponse: function (_url, _params, response) {
                    return (response && Array.isArray(response.data)) ? response.data : [];
                },
                index: 'id',
                layout: 'fitColumns',
                height: '100%',
                placeholder: 'No child SKUs on CP master',
                columnDefaults: {
                    hozAlign: 'center',
                    headerHozAlign: 'center',
                    vertAlign: 'middle'
                },
                columns: [
                    {
                        title: 'Parent SKU',
                        field: 'parent',
                        headerFilter: 'input',
                        headerFilterPlaceholder: 'Parent SKU',
                        minWidth: 140,
                        formatter: function (cell) {
                            const v = String(cell.getValue() || '').trim();
                            return v ? escapeHtml(v) : '—';
                        }
                    },
                    {
                        title: 'Child SKU',
                        field: 'sku',
                        headerFilter: 'input',
                        headerFilterPlaceholder: 'Child SKU',
                        minWidth: 160,
                        formatter: function (cell) {
                            const v = String(cell.getValue() || '').trim();
                            return v ? escapeHtml(v) : '—';
                        }
                    },
                    {
                        title: 'Variation Type',
                        field: 'variation_type',
                        editor: 'input',
                        headerFilter: 'input',
                        headerFilterPlaceholder: 'Color, Size…',
                        minWidth: 160,
                        cssClass: 'vm-editable',
                        formatter: function (cell) {
                            const v = String(cell.getValue() || '').trim();
                            return v ? escapeHtml(v) : '';
                        }
                    },
                    {
                        title: 'Variation Name',
                        field: 'variation_name',
                        editor: 'input',
                        headerFilter: 'input',
                        headerFilterPlaceholder: 'Black, Blue…',
                        minWidth: 180,
                        cssClass: 'vm-editable',
                        formatter: function (cell) {
                            const v = String(cell.getValue() || '').trim();
                            return v ? escapeHtml(v) : '';
                        }
                    }
                ]
            });

            table.on('cellEdited', function (cell) {
                const field = cell.getField();
                if (field !== 'variation_type' && field !== 'variation_name') return;
                const row = cell.getRow().getData();
                fetch(saveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf
                    },
                    body: JSON.stringify({
                        id: row.id,
                        variation_type: row.variation_type || '',
                        variation_name: row.variation_name || ''
                    })
                }).then(function (res) {
                    if (!res.ok) throw new Error('Save failed');
                    return res.json();
                }).then(function () {
                    setStatus('Saved ' + (row.sku || ''), true);
                }).catch(function () {
                    cell.restoreOldValue();
                    setStatus('Could not save ' + (row.sku || ''), false);
                });
            });

            document.getElementById('vm-upload-btn')?.addEventListener('click', function () {
                document.getElementById('vm-upload-file')?.click();
            });

            document.getElementById('vm-upload-file')?.addEventListener('change', function () {
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
