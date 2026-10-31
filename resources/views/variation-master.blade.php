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

        #vm-tabulator .tabulator-cell.vm-sku {
            font-weight: 700;
            color: #0f172a;
        }

        #vm-tabulator .tabulator-cell.vm-text {
            text-align: left;
            vertical-align: top;
        }

        #vm-tabulator .tabulator-cell.vm-wrap {
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: break-word;
            text-overflow: clip;
            text-align: left;
            vertical-align: top;
            line-height: 1.35;
        }

        #vm-tabulator .vm-multiline {
            white-space: pre-wrap;
            text-align: left;
            max-height: 72px;
            overflow: auto;
        }

        #vm-tabulator .tabulator-row.vm-parent-row .tabulator-cell {
            background-color: #fff7e6;
            font-weight: 600;
        }

        #vm-tabulator .tabulator-row.vm-parent-row .tabulator-cell.vm-editable {
            background-color: #fff1d6;
        }

        #vm-tabulator .tabulator-row.tabulator-selected .tabulator-cell {
            background-color: #dbeafe !important;
        }

        #vm-tabulator .vm-edit-btn {
            border: 0;
            background: transparent;
            color: #2563eb;
            padding: 2px 6px;
            cursor: pointer;
            font-size: 13px;
        }

        #vm-tabulator .vm-edit-btn:hover {
            color: #1e40af;
        }

        .vm-bulk-field {
            display: flex;
            gap: 8px;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .vm-bulk-field .form-check-input {
            margin-top: 8px;
            flex-shrink: 0;
        }

        .vm-bulk-field .vm-bulk-body {
            flex: 1;
        }

        .vm-bulk-field label.vm-bulk-label {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 2px;
        }

        .vm-toolbar {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        #vm-tabulator .vm-chip {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 700;
            background: #e2e8f0;
            color: #334155;
        }

        #vm-tabulator .vm-chip.is-aplus {
            background: #ede9fe;
            color: #5b21b6;
        }

        #vm-tabulator .vm-pill-btn {
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #1e40af;
            border-radius: 6px;
            padding: 2px 8px;
            font-size: 12px;
            cursor: pointer;
            white-space: nowrap;
        }

        #vm-tabulator .vm-pill-btn.is-empty {
            color: #64748b;
            border-style: dashed;
        }

        #vm-tabulator .vm-thumbs {
            display: inline-flex;
            align-items: center;
            gap: 2px;
        }

        #vm-tabulator .vm-thumbs img {
            width: 26px;
            height: 26px;
            object-fit: cover;
            border-radius: 3px;
            border: 1px solid #cbd5e1;
        }

        .vm-aplus-preview {
            width: 100%;
            height: 420px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: #fff;
        }

        .vm-img-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .vm-img-tile {
            position: relative;
            width: 110px;
            height: 110px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            overflow: hidden;
            background: #f8fafc;
        }

        .vm-img-tile img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .vm-img-tile .vm-img-del {
            position: absolute;
            top: 3px;
            right: 3px;
            width: 22px;
            height: 22px;
            border: 0;
            border-radius: 50%;
            background: rgba(15, 23, 42, .75);
            color: #fff;
            line-height: 1;
            cursor: pointer;
        }

        .vm-img-tile .vm-img-no {
            position: absolute;
            left: 3px;
            bottom: 3px;
            padding: 0 6px;
            border-radius: 8px;
            background: rgba(15, 23, 42, .65);
            color: #fff;
            font-size: 10px;
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
                        <select id="vm-type-filter" class="form-select form-select-sm" style="width:auto;" title="Show parent rows, SKU rows, or both">
                            <option value="all">All</option>
                            <option value="parent">Parent</option>
                            <option value="sku">SKU</option>
                        </select>
                        <button type="button" class="btn btn-sm btn-primary" id="vm-upload-btn">
                            <i class="bi bi-upload me-1"></i>Upload
                        </button>
                        <a class="btn btn-sm btn-success" href="{{ route('variation.master.download', ['format' => 'xlsx']) }}">
                            <i class="bi bi-file-earmark-excel me-1"></i>Download Excel
                        </a>
                        <a class="btn btn-sm btn-outline-success" href="{{ route('variation.master.download', ['format' => 'csv']) }}">
                            <i class="bi bi-filetype-csv me-1"></i>Download CSV
                        </a>
                        <button type="button" class="btn btn-sm btn-warning" id="vm-update-btn" title="Fill empty Title, Description and Bullet 1–5 from each parent's SKUs; if a SKU has none, from the Temu tables, then the Amazon tables">
                            <i class="bi bi-arrow-repeat me-1"></i>Update
                        </button>
                        <input type="file" id="vm-upload-file" class="d-none" accept=".xlsx,.xls,.csv">
                        <span class="small text-muted ms-2">Parent SKU and Child SKU come from CP master. Title, Description, A+ Content, Bullet 1–5 and Parent Images are shared by every child of that parent and are used when a variation listing is published from Listing Manager or Missing L. Listing Content picks Text or A+ where the marketplace takes A+ (eBay); other marketplaces use the text. Amazon keeps each child's own content and images.</span>
                        <span class="small ms-auto" id="vm-status"></span>
                    </div>
                </div>
                <div id="vm-table-wrapper">
                    <div id="vm-tabulator"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="vm-edit-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="vm-edit-title">Edit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3" id="vm-edit-hint"></p>
                    <div class="vm-bulk-field">
                        <input class="form-check-input vm-apply" type="checkbox" data-field="variation_type" title="Apply this field">
                        <div class="vm-bulk-body">
                            <label class="vm-bulk-label">Variation Type</label>
                            <input type="text" class="form-control form-control-sm vm-bulk-input" data-field="variation_type" maxlength="120" placeholder="Color, Size…">
                        </div>
                    </div>
                    <div class="vm-bulk-field">
                        <input class="form-check-input vm-apply" type="checkbox" data-field="variation_name" title="Apply this field">
                        <div class="vm-bulk-body">
                            <label class="vm-bulk-label">Variation Name</label>
                            <input type="text" class="form-control form-control-sm vm-bulk-input" data-field="variation_name" maxlength="500" placeholder="Black, Blue…">
                        </div>
                    </div>
                    <div class="vm-bulk-field">
                        <input class="form-check-input vm-apply" type="checkbox" data-field="title" title="Apply this field">
                        <div class="vm-bulk-body">
                            <label class="vm-bulk-label">Title</label>
                            <textarea class="form-control form-control-sm vm-bulk-input" data-field="title" rows="2" maxlength="500"></textarea>
                        </div>
                    </div>
                    <div class="vm-bulk-field">
                        <input class="form-check-input vm-apply" type="checkbox" data-field="description" title="Apply this field">
                        <div class="vm-bulk-body">
                            <label class="vm-bulk-label">Description</label>
                            <textarea class="form-control form-control-sm vm-bulk-input" data-field="description" rows="5" maxlength="20000"></textarea>
                        </div>
                    </div>
                    <div class="vm-bulk-field">
                        <input class="form-check-input vm-apply" type="checkbox" data-field="desc_mode" title="Apply this field">
                        <div class="vm-bulk-body">
                            <label class="vm-bulk-label">Listing Content</label>
                            <select class="form-select form-select-sm vm-bulk-input" data-field="desc_mode">
                                <option value="text">Text description</option>
                                <option value="aplus">A+ content</option>
                            </select>
                        </div>
                    </div>
                    <div class="vm-bulk-field">
                        <input class="form-check-input vm-apply" type="checkbox" data-field="aplus" title="Apply this field">
                        <div class="vm-bulk-body">
                            <label class="vm-bulk-label">A+ Content (HTML)</label>
                            <textarea class="form-control form-control-sm vm-bulk-input" data-field="aplus" rows="4" maxlength="200000" placeholder="&lt;div&gt;…&lt;/div&gt;"></textarea>
                        </div>
                    </div>
                    @for ($b = 1; $b <= 5; $b++)
                        <div class="vm-bulk-field">
                            <input class="form-check-input vm-apply" type="checkbox" data-field="bullet_{{ $b }}" title="Apply this field">
                            <div class="vm-bulk-body">
                                <label class="vm-bulk-label">Bullet {{ $b }}</label>
                                <input type="text" class="form-control form-control-sm vm-bulk-input" data-field="bullet_{{ $b }}" maxlength="1000">
                            </div>
                        </div>
                    @endfor
                    <p class="small text-muted mb-0">Only ticked fields are changed on the selected rows. Leave a ticked field empty to clear it. Title, Description and Bullets are shared by every child of a parent, so they update that whole parent.</p>
                </div>
                <div class="modal-footer py-2">
                    <span class="small me-auto" id="vm-edit-status"></span>
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" id="vm-edit-save">Apply to selected</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="vm-aplus-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="vm-aplus-title">A+ Content</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">HTML for the parent's A+ page. It replaces the text description on marketplaces that accept HTML (eBay) when Listing Content is set to A+. Other marketplaces keep using the text description. Shared by every child of the parent.</p>
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <label class="form-label small fw-semibold mb-1">A+ HTML</label>
                            <textarea class="form-control form-control-sm" id="vm-aplus-input" rows="18" maxlength="200000" spellcheck="false"></textarea>
                            <div class="small text-muted mt-1" id="vm-aplus-count"></div>
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label small fw-semibold mb-1">Preview</label>
                            <iframe class="vm-aplus-preview" id="vm-aplus-preview" sandbox="" title="A+ preview"></iframe>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <span class="small me-auto" id="vm-aplus-status"></span>
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" id="vm-aplus-save">Save A+ content</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="vm-images-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="vm-images-title">Parent Images</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">Photos of the parent listing. A variation listing on eBay, Temu and AliExpress shows these first in its shared gallery; each child keeps its own Image Master photos for its variation. Amazon always uses each child's own images.</p>
                    <div class="mb-3">
                        <input type="file" class="form-control form-control-sm" id="vm-images-file" accept="image/*" multiple>
                        <div class="small text-muted mt-1">JPG, PNG, WEBP or GIF, up to 12 MB each, 20 photos per parent.</div>
                    </div>
                    <div class="vm-img-grid" id="vm-images-grid"></div>
                </div>
                <div class="modal-footer py-2">
                    <span class="small me-auto" id="vm-images-status"></span>
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
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
            const updateUrl = @json(route('variation.master.update.from.sku'));
            const bulkUrl = @json(route('variation.master.bulk.update'));
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const statusEl = document.getElementById('vm-status');

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function textFormatter(cell) {
                const value = String(cell.getValue() || '');
                if (!value) return '';
                return '<div class="vm-multiline">' + escapeHtml(value) + '</div>';
            }

            function skuFormatter(cell) {
                const v = String(cell.getValue() || '').trim();
                return v ? '<strong>' + escapeHtml(v) + '</strong>' : '—';
            }

            function setStatus(text, ok) {
                if (!statusEl) return;
                statusEl.textContent = text || '';
                statusEl.className = 'small ms-auto ' + (ok === false ? 'text-danger' : 'text-success');
            }

            function bulletColumn(number) {
                return {
                    title: 'Bullet ' + number,
                    field: 'bullet_' + number,
                    editor: 'input',
                    headerFilter: 'input',
                    headerFilterPlaceholder: 'Bullet ' + number,
                    minWidth: 160,
                    cssClass: 'vm-editable vm-wrap',
                    formatter: function (cell) {
                        const value = String(cell.getValue() || '').trim();
                        return value ? escapeHtml(value) : '';
                    }
                };
            }

            const table = new Tabulator('#vm-tabulator', {
                ajaxURL: dataUrl,
                ajaxResponse: function (_url, _params, response) {
                    return (response && Array.isArray(response.data)) ? response.data : [];
                },
                index: 'id',
                layout: 'fitColumns',
                height: '100%',
                placeholder: 'No rows on CP master',
                rowFormatter: function (row) {
                    const data = row.getData();
                    row.getElement().classList.toggle('vm-parent-row', data.row_type === 'parent');
                },
                columnDefaults: {
                    hozAlign: 'center',
                    headerHozAlign: 'center',
                    vertAlign: 'middle'
                },
                columns: [
                    {
                        formatter: 'rowSelection',
                        titleFormatter: 'rowSelection',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        headerSort: false,
                        width: 44,
                        minWidth: 44,
                        resizable: false,
                        cssClass: 'vm-check'
                    },
                    {
                        title: 'Parent SKU',
                        field: 'parent',
                        headerFilter: 'input',
                        headerFilterPlaceholder: 'Parent SKU',
                        minWidth: 140,
                        cssClass: 'vm-sku',
                        formatter: skuFormatter
                    },
                    {
                        title: 'SKU',
                        field: 'sku',
                        headerFilter: 'input',
                        headerFilterPlaceholder: 'SKU',
                        minWidth: 160,
                        cssClass: 'vm-sku',
                        formatter: skuFormatter
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
                    },
                    {
                        title: 'Title',
                        field: 'title',
                        editor: 'textarea',
                        headerFilter: 'input',
                        headerFilterPlaceholder: 'Title',
                        minWidth: 200,
                        cssClass: 'vm-editable vm-text',
                        formatter: textFormatter
                    },
                    {
                        title: 'Description',
                        field: 'description',
                        editor: 'textarea',
                        headerFilter: 'input',
                        headerFilterPlaceholder: 'Description',
                        minWidth: 240,
                        cssClass: 'vm-editable vm-text',
                        formatter: textFormatter
                    },
                    bulletColumn(1),
                    bulletColumn(2),
                    bulletColumn(3),
                    bulletColumn(4),
                    bulletColumn(5),
                    {
                        title: 'Listing Content',
                        field: 'desc_mode',
                        editor: 'list',
                        editorParams: { values: { text: 'Text description', aplus: 'A+ content' }, clearable: false },
                        headerFilter: 'list',
                        headerFilterParams: { values: { text: 'Text', aplus: 'A+' }, clearable: true },
                        headerFilterFunc: '=',
                        headerFilterPlaceholder: 'Text / A+',
                        minWidth: 140,
                        cssClass: 'vm-editable',
                        formatter: function (cell) {
                            return String(cell.getValue() || '') === 'aplus'
                                ? '<span class="vm-chip is-aplus">A+ content</span>'
                                : '<span class="vm-chip">Text</span>';
                        }
                    },
                    {
                        title: 'A+ Content',
                        field: 'aplus',
                        headerFilter: 'input',
                        headerFilterPlaceholder: 'A+ HTML',
                        minWidth: 150,
                        formatter: function (cell) {
                            const html = String(cell.getValue() || '').trim();
                            if (!html) {
                                return '<button type="button" class="vm-pill-btn is-empty"><i class="bi bi-plus-lg"></i> Add A+</button>';
                            }
                            return '<button type="button" class="vm-pill-btn" title="Edit the A+ content"><i class="bi bi-file-richtext"></i> A+ · ' + html.length.toLocaleString() + ' chars</button>';
                        },
                        cellClick: function (_e, cell) {
                            openAplusModal(cell.getRow().getData());
                        }
                    },
                    {
                        title: 'Parent Images',
                        field: 'parent_images',
                        headerSort: false,
                        minWidth: 150,
                        formatter: function (cell) {
                            const list = Array.isArray(cell.getValue()) ? cell.getValue() : [];
                            if (!list.length) {
                                return '<button type="button" class="vm-pill-btn is-empty"><i class="bi bi-upload"></i> Upload</button>';
                            }
                            let html = '<span class="vm-thumbs">';
                            list.slice(0, 3).forEach(function (url) {
                                html += '<img src="' + escapeHtml(url) + '" alt="" loading="lazy">';
                            });
                            html += '</span> <button type="button" class="vm-pill-btn" title="Manage parent images">' + list.length + (list.length === 1 ? ' photo' : ' photos') + '</button>';
                            return html;
                        },
                        cellClick: function (_e, cell) {
                            openImagesModal(cell.getRow().getData());
                        }
                    },
                    {
                        title: 'Edit',
                        field: 'vm_edit',
                        headerSort: false,
                        width: 64,
                        minWidth: 64,
                        resizable: false,
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        formatter: function () {
                            return '<button type="button" class="vm-edit-btn" title="Edit selected rows"><i class="bi bi-pencil-fill"></i></button>';
                        },
                        cellClick: function (_e, cell) {
                            openEditModal(cell.getRow());
                        }
                    }
                ]
            });

            // Top filter: All rows, Parent rows only, or SKU (child) rows only.
            document.getElementById('vm-type-filter')?.addEventListener('change', function () {
                const type = this.value;
                table.deselectRow();
                if (type === 'parent' || type === 'sku') {
                    table.setFilter('row_type', '=', type);
                } else {
                    table.clearFilter();
                }
            });

            const editModalEl = document.getElementById('vm-edit-modal');
            const editStatusEl = document.getElementById('vm-edit-status');
            let editRows = [];

            function openEditModal(clickedRow) {
                let rows = table.getSelectedRows();
                if (!rows.length) {
                    // Nothing ticked: edit just the row whose pen was clicked.
                    rows = clickedRow ? [clickedRow] : [];
                }
                if (!rows.length) {
                    setStatus('Select at least one row first.', false);
                    return;
                }
                editRows = rows;
                const parents = {};
                rows.forEach(function (r) {
                    parents[parentKeyOf(r.getData())] = true;
                });
                const parentCount = Object.keys(parents).length;
                document.getElementById('vm-edit-title').textContent = 'Edit ' + rows.length + ' selected SKU' + (rows.length === 1 ? '' : 's');
                document.getElementById('vm-edit-hint').textContent = rows.length + ' SKU' + (rows.length === 1 ? '' : 's') + ' across ' + parentCount + ' parent' + (parentCount === 1 ? '' : 's') + ' will be changed.';
                editModalEl.querySelectorAll('.vm-bulk-input').forEach(function (input) {
                    input.value = '';
                });
                editModalEl.querySelectorAll('.vm-apply').forEach(function (box) {
                    box.checked = false;
                });
                editStatusEl.textContent = '';
                bootstrap.Modal.getOrCreateInstance(editModalEl).show();
            }

            // Typing in a field ticks its "apply" box automatically.
            editModalEl.addEventListener('input', function (event) {
                const input = event.target.closest('.vm-bulk-input');
                if (!input) return;
                const box = editModalEl.querySelector('.vm-apply[data-field="' + input.dataset.field + '"]');
                if (box) box.checked = true;
            });

            document.getElementById('vm-edit-save')?.addEventListener('click', function () {
                const btn = this;
                if (btn.disabled) return;
                const payload = { ids: editRows.map(function (r) { return r.getData().id; }) };
                let fieldCount = 0;
                editModalEl.querySelectorAll('.vm-apply').forEach(function (box) {
                    if (!box.checked) return;
                    const input = editModalEl.querySelector('.vm-bulk-input[data-field="' + box.dataset.field + '"]');
                    payload[box.dataset.field] = input ? input.value : '';
                    fieldCount++;
                });
                if (!fieldCount) {
                    editStatusEl.className = 'small me-auto text-danger';
                    editStatusEl.textContent = 'Tick at least one field to apply.';
                    return;
                }
                btn.disabled = true;
                editStatusEl.className = 'small me-auto text-muted';
                editStatusEl.textContent = 'Saving…';
                fetch(bulkUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf
                    },
                    body: JSON.stringify(payload)
                }).then(function (res) {
                    return res.json().then(function (json) {
                        return { ok: res.ok, json: json };
                    }).catch(function () {
                        return { ok: false, json: null };
                    });
                }).then(function (result) {
                    const json = result.json || {};
                    if (!result.ok || json.success === false) {
                        throw new Error(json.message || 'Bulk update failed.');
                    }
                    bootstrap.Modal.getOrCreateInstance(editModalEl).hide();
                    setStatus(json.message || 'Updated.', true);
                    table.deselectRow();
                    table.replaceData();
                }).catch(function (err) {
                    editStatusEl.className = 'small me-auto text-danger';
                    editStatusEl.textContent = err && err.message ? err.message : 'Bulk update failed.';
                }).finally(function () {
                    btn.disabled = false;
                });
            });

            const bulletFields = ['bullet_1', 'bullet_2', 'bullet_3', 'bullet_4', 'bullet_5'];

            function parentKeyOf(row) {
                return String(row.parent_key || row.parent || '').trim().toUpperCase();
            }

            function sameParent(a, b) {
                const key = parentKeyOf(a);
                return key !== '' && key === parentKeyOf(b);
            }

            function listingSnapshot(row) {
                const snapshot = {
                    title: row.title || '',
                    description: row.description || '',
                    desc_mode: row.desc_mode === 'aplus' ? 'aplus' : 'text'
                };
                bulletFields.forEach(function (field) {
                    snapshot[field] = row[field] || '';
                });
                return snapshot;
            }

            table.on('cellEdited', function (cell) {
                const field = cell.getField();
                const listingFields = ['title', 'description', 'desc_mode'].concat(bulletFields);
                const childFields = ['variation_type', 'variation_name'];
                if (childFields.indexOf(field) === -1 && listingFields.indexOf(field) === -1) return;
                const row = cell.getRow().getData();
                const payload = { id: row.id, parent: row.parent || '' };
                if (listingFields.indexOf(field) !== -1) {
                    Object.assign(payload, listingSnapshot(row));
                } else {
                    payload.variation_type = row.variation_type || '';
                    payload.variation_name = row.variation_name || '';
                }
                fetch(saveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf
                    },
                    body: JSON.stringify(payload)
                }).then(function (res) {
                    return res.json().then(function (json) {
                        return { ok: res.ok, json: json };
                    });
                }).then(function (result) {
                    if (!result.ok || !result.json || result.json.success === false) {
                        throw new Error((result.json && result.json.message) || 'Save failed');
                    }
                    if (listingFields.indexOf(field) !== -1) {
                        table.getRows().forEach(function (other) {
                            const data = other.getData();
                            if (sameParent(data, row) && data.id !== row.id) {
                                other.update(listingSnapshot(row));
                            }
                        });
                    }
                    setStatus('Saved ' + (row.sku || row.parent || ''), true);
                }).catch(function (err) {
                    cell.restoreOldValue();
                    setStatus(err && err.message ? err.message : 'Could not save ' + (row.sku || ''), false);
                });
            });

            // ---- A+ content and parent images (shared by every child of the parent) ----
            const imagesUrl = @json(route('variation.master.parent.images.upload'));
            const imageDeleteUrl = @json(route('variation.master.parent.images.delete'));
            const aplusModalEl = document.getElementById('vm-aplus-modal');
            const imagesModalEl = document.getElementById('vm-images-modal');
            const aplusInput = document.getElementById('vm-aplus-input');
            const aplusPreview = document.getElementById('vm-aplus-preview');
            const aplusStatus = document.getElementById('vm-aplus-status');
            const imagesStatus = document.getElementById('vm-images-status');
            let panelRow = null;
            let previewTimer = null;

            function applyParentPatch(rowData, patch) {
                table.getRows().forEach(function (other) {
                    if (sameParent(other.getData(), rowData)) other.update(patch);
                });
                Object.assign(rowData, patch);
            }

            function parentLabel(row) {
                return String(row.parent_key || row.parent || '').trim();
            }

            function postJson(url, payload) {
                return fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify(payload)
                }).then(function (res) {
                    return res.json().catch(function () { return null; }).then(function (json) {
                        if (!res.ok || !json || json.success === false) {
                            throw new Error((json && (json.message || (json.errors && Object.values(json.errors)[0][0]))) || 'Request failed.');
                        }
                        return json;
                    });
                });
            }

            function refreshAplusPreview() {
                aplusPreview.srcdoc = '<base target="_blank"><body style="font-family:sans-serif;margin:12px">' + aplusInput.value + '</body>';
                document.getElementById('vm-aplus-count').textContent = aplusInput.value.length.toLocaleString() + ' characters';
            }

            function openAplusModal(row) {
                panelRow = row;
                document.getElementById('vm-aplus-title').textContent = 'A+ Content — ' + parentLabel(row);
                aplusInput.value = row.aplus || '';
                aplusStatus.textContent = '';
                refreshAplusPreview();
                bootstrap.Modal.getOrCreateInstance(aplusModalEl).show();
            }

            aplusInput?.addEventListener('input', function () {
                clearTimeout(previewTimer);
                previewTimer = setTimeout(refreshAplusPreview, 250);
            });

            document.getElementById('vm-aplus-save')?.addEventListener('click', function () {
                const btn = this;
                if (!panelRow || btn.disabled) return;
                btn.disabled = true;
                aplusStatus.className = 'small me-auto text-muted';
                aplusStatus.textContent = 'Saving…';
                const row = panelRow;
                postJson(saveUrl, { id: row.id, parent: row.parent || '', aplus: aplusInput.value }).then(function (json) {
                    applyParentPatch(row, { aplus: json.aplus || '' });
                    bootstrap.Modal.getOrCreateInstance(aplusModalEl).hide();
                    setStatus('Saved A+ content for ' + parentLabel(row), true);
                }).catch(function (err) {
                    aplusStatus.className = 'small me-auto text-danger';
                    aplusStatus.textContent = err.message || 'Save failed.';
                }).finally(function () {
                    btn.disabled = false;
                });
            });

            function renderImagesGrid() {
                const grid = document.getElementById('vm-images-grid');
                const list = (panelRow && Array.isArray(panelRow.parent_images)) ? panelRow.parent_images : [];
                if (!list.length) {
                    grid.innerHTML = '<div class="small text-muted">No parent images yet. Choose files above to upload.</div>';
                    return;
                }
                grid.innerHTML = list.map(function (url, i) {
                    return '<div class="vm-img-tile"><a href="' + escapeHtml(url) + '" target="_blank" rel="noopener"><img src="' + escapeHtml(url) + '" alt=""></a>'
                        + '<span class="vm-img-no">' + (i + 1) + '</span>'
                        + '<button type="button" class="vm-img-del" data-url="' + escapeHtml(url) + '" title="Remove this image">&times;</button></div>';
                }).join('');
            }

            function openImagesModal(row) {
                panelRow = row;
                document.getElementById('vm-images-title').textContent = 'Parent Images — ' + parentLabel(row);
                imagesStatus.textContent = '';
                document.getElementById('vm-images-file').value = '';
                renderImagesGrid();
                bootstrap.Modal.getOrCreateInstance(imagesModalEl).show();
            }

            document.getElementById('vm-images-file')?.addEventListener('change', function () {
                const files = Array.from(this.files || []);
                this.value = '';
                if (!files.length || !panelRow) return;
                const row = panelRow;
                const body = new FormData();
                body.append('parent', parentLabel(row));
                files.forEach(function (file) { body.append('images[]', file); });
                imagesStatus.className = 'small me-auto text-muted';
                imagesStatus.textContent = 'Uploading ' + files.length + ' image' + (files.length === 1 ? '' : 's') + '…';
                fetch(imagesUrl, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: body
                }).then(function (res) {
                    return res.json().catch(function () { return null; }).then(function (json) {
                        if (!res.ok || !json || json.success === false) {
                            throw new Error((json && (json.message || (json.errors && Object.values(json.errors)[0][0]))) || 'Upload failed.');
                        }
                        return json;
                    });
                }).then(function (json) {
                    applyParentPatch(row, { parent_images: json.parent_images || [] });
                    renderImagesGrid();
                    imagesStatus.className = 'small me-auto text-success';
                    imagesStatus.textContent = json.message || 'Uploaded.';
                }).catch(function (err) {
                    imagesStatus.className = 'small me-auto text-danger';
                    imagesStatus.textContent = err.message || 'Upload failed.';
                });
            });

            document.getElementById('vm-images-grid')?.addEventListener('click', function (event) {
                const btn = event.target.closest('.vm-img-del');
                if (!btn || !panelRow) return;
                const row = panelRow;
                imagesStatus.className = 'small me-auto text-muted';
                imagesStatus.textContent = 'Removing…';
                postJson(imageDeleteUrl, { parent: parentLabel(row), url: btn.getAttribute('data-url') || '' }).then(function (json) {
                    applyParentPatch(row, { parent_images: json.parent_images || [] });
                    renderImagesGrid();
                    imagesStatus.className = 'small me-auto text-success';
                    imagesStatus.textContent = json.message || 'Removed.';
                }).catch(function (err) {
                    imagesStatus.className = 'small me-auto text-danger';
                    imagesStatus.textContent = err.message || 'Could not remove the image.';
                });
            });

            document.getElementById('vm-update-btn')?.addEventListener('click', function () {
                const btn = this;
                if (btn.disabled) return;
                btn.disabled = true;
                setStatus('Updating from SKUs…', true);
                fetch(updateUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf
                    }
                }).then(function (res) {
                    return res.json().then(function (json) {
                        return { ok: res.ok, json: json };
                    });
                }).then(function (result) {
                    const message = (result.json && result.json.message) || (result.ok ? 'Updated.' : 'Update failed.');
                    setStatus(message, result.ok && result.json && result.json.success !== false);
                    if (result.ok) table.replaceData();
                }).catch(function () {
                    setStatus('Update failed.', false);
                }).finally(function () {
                    btn.disabled = false;
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
