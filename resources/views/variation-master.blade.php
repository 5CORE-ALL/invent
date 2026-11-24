@extends('layouts.vertical', ['title' => 'Variation Master', 'mode' => $mode ?? '', 'demo' => $demo ?? '', 'sidenav' => 'condensed', 'skipHighcharts' => true])

@section('css')
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
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

        /* Checkbox, Parent SKU and SKU stay in view while scrolling sideways: they need a solid background. */
        #vm-tabulator .tabulator-cell.tabulator-frozen { background-color: #fff; }
        #vm-tabulator .tabulator-header .tabulator-col.tabulator-frozen { background: #e8ecf1; }
        #vm-tabulator .tabulator-cell.tabulator-frozen.tabulator-frozen-left { border-right: 1px solid #cbd5e1; }

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
            /* The box only shows the text. Clicks pass through to the cell, so a double click opens the editor. */
            pointer-events: none;
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

        .vm-col-menu { min-width: 250px; max-height: 70vh; overflow-y: auto; z-index: 1060; }
        .vm-col-menu .vm-col-item { display: flex; align-items: center; gap: 8px; padding: 3px 6px; margin: 0; font-size: 13px; cursor: pointer; border-radius: 4px; white-space: nowrap; }
        .vm-col-menu .vm-col-item:hover { background: #f1f5f9; }

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

        #vm-tabulator .vm-img-cell {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            padding: 4px 0;
        }

        #vm-tabulator .vm-img-count {
            font-size: 12px;
            font-weight: 700;
            color: #0f766e;
        }

        #vm-tabulator .vm-img-count.is-zero {
            color: #94a3b8;
            font-weight: 600;
        }

        #vm-tabulator .vm-img-actions {
            display: inline-flex;
            gap: 4px;
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

        #vm-aplus-modal .modal-header-gradient { background: linear-gradient(135deg, #6B73FF 0%, #000DFF 100%); color: #fff; }
        #vm-aplus-modal .vm-desc-note { font-size: 10px; color: #64748b; }
        #vm-desc-count.at-limit { color: #dc2626; font-weight: 600; }

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

        .vm-img-tile .vm-img-dl {
            position: absolute;
            top: 3px;
            left: 3px;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: rgba(15, 23, 42, .75);
            color: #fff;
            font-size: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
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
        #vm-push-overlay {
            position: fixed; inset: 0; z-index: 2000; display: none;
            align-items: center; justify-content: center;
            background: rgba(15, 23, 42, .55); backdrop-filter: blur(2px);
        }
        #vm-push-overlay.show { display: flex; }
        #vm-push-overlay .vm-push-card {
            width: min(480px, 92vw); max-height: 80vh; overflow: auto; text-align: center;
            background: #fff; border-radius: 14px; padding: 26px 24px 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .35);
        }
        #vm-push-overlay .vm-push-icon { font-size: 44px; line-height: 1; margin-bottom: 10px; }
        #vm-push-overlay .vm-push-title { font-size: 17px; font-weight: 700; margin-bottom: 6px; }
        #vm-push-overlay .vm-push-text { font-size: 13px; color: #475569; word-break: break-word; }
        #vm-push-overlay .vm-push-bar { height: 6px; border-radius: 4px; background: #e2e8f0; overflow: hidden; margin-top: 14px; }
        #vm-push-overlay .vm-push-bar > div { height: 100%; width: 0; background: #3b82f6; transition: width .25s; }
        #vm-push-overlay .vm-push-list { text-align: left; font-size: 12px; margin: 12px 0 0; padding-left: 18px; color: #b91c1c; }
        #vm-push-overlay.is-ok .vm-push-icon { color: #16a34a; }
        #vm-push-overlay.is-fail .vm-push-icon { color: #dc2626; }
        #vm-push-overlay.is-busy .vm-push-icon { color: #3b82f6; }
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
                        {{-- Show / hide any column. The choice is remembered for this user. --}}
                        <div class="dropdown d-inline-block">
                            <button type="button" class="btn btn-sm btn-outline-success dropdown-toggle" id="vm-col-btn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="Show or hide columns">
                                <i class="bi bi-layout-three-columns"></i> <span id="vm-col-count" class="small"></span>
                            </button>
                            <div class="dropdown-menu vm-col-menu p-2" aria-labelledby="vm-col-btn">
                                <div class="d-flex gap-1 mb-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary flex-fill" id="vm-col-all">Show all</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary flex-fill" id="vm-col-none" title="Hide every column except Parent SKU and SKU">Hide all</button>
                                </div>
                                <div class="vm-col-list" id="vm-col-list"></div>
                            </div>
                        </div>
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
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-primary dropdown-toggle" id="vm-bulk-push" data-bs-toggle="dropdown" aria-expanded="false" title="Tick the PARENT rows, then push their saved A+ content to the parent listings">
                                <i class="fas fa-cloud-upload-alt me-1"></i>Push A+
                            </button>
                            <ul class="dropdown-menu">
                                <li><h6 class="dropdown-header">Push the saved A+ of the ticked parents to</h6></li>
                                <li><a class="dropdown-item" href="#" data-bulk-push="ebay2">eBay 2</a></li>
                                <li><a class="dropdown-item" href="#" data-bulk-push="ebay3">eBay 3</a></li>
                            </ul>
                        </div>
                        <input type="file" id="vm-upload-file" class="d-none" accept=".xlsx,.xls,.csv">
                        <span class="small text-muted ms-2">Parent SKU and Child SKU come from CP master. Title, Description and Bullet 1–5 are shared by every child of that parent. A child row’s A+ Content comes from Description Master and its images come from Image Master, and both refresh from those pages whenever Variation Master loads. Where the marketplace takes A+ (eBay) a saved A+ Content is sent instead of the Description; other marketplaces use the Description.</span>
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
                            <input type="text" class="form-control form-control-sm vm-bulk-input" data-field="variation_type" placeholder="Color, Size…">
                        </div>
                    </div>
                    <div class="vm-bulk-field">
                        <input class="form-check-input vm-apply" type="checkbox" data-field="variation_name" title="Apply this field">
                        <div class="vm-bulk-body">
                            <label class="vm-bulk-label">Variation Name</label>
                            <input type="text" class="form-control form-control-sm vm-bulk-input" data-field="variation_name" placeholder="Black, Blue…">
                        </div>
                    </div>
                    <div class="vm-bulk-field">
                        <input class="form-check-input vm-apply" type="checkbox" data-field="title" title="Apply this field">
                        <div class="vm-bulk-body">
                            <label class="vm-bulk-label">Title</label>
                            <textarea class="form-control form-control-sm vm-bulk-input" data-field="title" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="vm-bulk-field">
                        <input class="form-check-input vm-apply" type="checkbox" data-field="description" title="Apply this field">
                        <div class="vm-bulk-body">
                            <label class="vm-bulk-label">Description</label>
                            <textarea class="form-control form-control-sm vm-bulk-input" data-field="description" rows="5"></textarea>
                        </div>
                    </div>
                    <div class="vm-bulk-field">
                        <input class="form-check-input vm-apply" type="checkbox" data-field="aplus" title="Apply this field">
                        <div class="vm-bulk-body">
                            <label class="vm-bulk-label">A+ Content (HTML)</label>
                            <textarea class="form-control form-control-sm vm-bulk-input" data-field="aplus" rows="4" placeholder="&lt;div&gt;…&lt;/div&gt;"></textarea>
                        </div>
                    </div>
                    @for ($b = 1; $b <= 5; $b++)
                        <div class="vm-bulk-field">
                            <input class="form-check-input vm-apply" type="checkbox" data-field="bullet_{{ $b }}" title="Apply this field">
                            <div class="vm-bulk-body">
                                <label class="vm-bulk-label">Bullet {{ $b }}</label>
                                <input type="text" class="form-control form-control-sm vm-bulk-input" data-field="bullet_{{ $b }}">
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

    {{-- Same editor as Description Master's "Edit descriptions" modal. The parent keeps ONE shared content. --}}
    {{-- Loader while A+ is pushed, then the success / failure result. --}}
    <div id="vm-push-overlay" role="alertdialog" aria-live="polite">
        <div class="vm-push-card">
            <div class="vm-push-icon"><i class="fas fa-spinner fa-spin"></i></div>
            <div class="vm-push-title"></div>
            <div class="vm-push-text"></div>
            <div class="vm-push-bar d-none"><div></div></div>
            <ul class="vm-push-list d-none"></ul>
            <div class="mt-3 d-flex justify-content-center gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="vm-push-dl"><i class="fas fa-download me-1"></i>Download list (CSV)</button>
                <button type="button" class="btn btn-primary btn-sm d-none" id="vm-push-close">OK</button>
            </div>
        </div>
    </div>

    <div class="modal fade" id="vm-aplus-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title" id="vm-aplus-title"><i class="fas fa-edit me-2"></i>Edit descriptions</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3"><strong>Product:</strong> <span id="vm-desc-product"></span> <span class="small text-muted" id="vm-desc-sku"></span></div>
                    <p class="small text-muted mb-2"><i class="fas fa-info-circle"></i> One description is shared by every child of this parent. Edit it, then Save. It is saved as the parent's A+ HTML: eBay sends it instead of the Description, other marketplaces keep using the Description.</p>

                    <div class="d-flex flex-wrap align-items-center justify-content-end gap-2 mb-2">
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-success btn-sm" id="vm-desc-fetch-live"><i class="fas fa-cloud-download-alt"></i> Fetch live</button>
                            <button type="button" class="btn btn-outline-dark btn-sm" id="vm-desc-fetch-aplus" title="Load the stored A+ content of this SKU into the editor (fetched once automatically if not stored yet)"><i class="fas fa-star"></i> Fetch A+</button>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="vm-desc-ai"><i class="fas fa-wand-magic-sparkles"></i> AI Generate</button>
                        </div>
                    </div>
                    <textarea id="vm-desc-html"></textarea>
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-1">
                        <div class="small text-muted" id="vm-desc-hint">Editing the shared description of this parent.</div>
                        <div class="small text-muted" id="vm-desc-count">0 / —</div>
                    </div>
                    <p class="vm-desc-note mb-0 mt-1"><i class="fas fa-info-circle"></i> Fetch live reads this SKU from the marketplace. Fetch A+ loads the stored Shopify A+ content.</p>
                    <div class="mt-2 small text-muted d-none" id="vm-desc-loading"><i class="fas fa-spinner fa-spin"></i> <span id="vm-desc-loading-text"></span></div>
                </div>
                <div class="modal-footer">
                    <span class="small me-auto" id="vm-aplus-status"></span>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <div class="btn-group dropup d-none" id="vm-aplus-push-wrap">
                        <button type="button" class="btn btn-primary dropdown-toggle" id="vm-aplus-push" data-bs-toggle="dropdown" aria-expanded="false" title="Saves this A+ content, then updates the parent listing only">
                            <i class="fas fa-cloud-upload-alt"></i> Push A+
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Update the parent listing's A+</h6></li>
                            <li><a class="dropdown-item" href="#" data-push-mp="ebay2">eBay 2</a></li>
                            <li><a class="dropdown-item" href="#" data-push-mp="ebay3">eBay 3</a></li>
                        </ul>
                    </div>
                    <button type="button" class="btn btn-success" id="vm-aplus-save"><i class="fas fa-save"></i> Save all</button>
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
                        <div class="d-flex gap-2 align-items-center">
                            <input type="file" class="form-control form-control-sm" id="vm-images-file" accept="image/*" multiple>
                            <button type="button" class="btn btn-sm btn-outline-success text-nowrap" id="vm-images-download">
                                <i class="bi bi-download me-1"></i>Download all (ZIP)
                            </button>
                        </div>
                        <div class="small text-muted mt-1">JPG, PNG, WEBP or GIF, up to 12 MB each, 20 photos per parent. <strong id="vm-images-count"></strong></div>
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
    @include('partials.all-marketplace-master-channels', ['allMpMaster' => 'description'])
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js" referrerpolicy="origin"></script>
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
                        frozen: true,
                        cssClass: 'vm-check'
                    },
                    {
                        title: 'Parent SKU',
                        field: 'parent',
                        headerFilter: 'input',
                        headerFilterPlaceholder: 'Parent SKU',
                        minWidth: 140,
                        frozen: true,
                        cssClass: 'vm-sku',
                        formatter: skuFormatter
                    },
                    {
                        title: 'SKU',
                        field: 'sku',
                        headerFilter: 'input',
                        headerFilterPlaceholder: 'SKU',
                        minWidth: 160,
                        frozen: true,
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
                    {
                        title: 'A+ Content',
                        field: 'aplus',
                        headerFilter: 'input',
                        headerFilterPlaceholder: 'A+ HTML',
                        minWidth: 150,
                        formatter: function (cell) {
                            const data = cell.getRow().getData();
                            const html = String(cell.getValue() || '').trim();
                            const chars = html ? html.length : Number(data.aplus_chars || 0);
                            if (!chars) {
                                return '<button type="button" class="vm-pill-btn is-empty"><i class="bi bi-plus-lg"></i> Add A+</button>';
                            }
                            return '<button type="button" class="vm-pill-btn" title="Edit the A+ content"><i class="bi bi-file-richtext"></i> A+ · ' + chars.toLocaleString() + ' chars</button>';
                        },
                        cellClick: function (_e, cell) {
                            openAplusModal(cell.getRow().getData());
                        }
                    },
                    bulletColumn(1),
                    bulletColumn(2),
                    bulletColumn(3),
                    bulletColumn(4),
                    bulletColumn(5),
                    {
                        title: 'Parent Images',
                        field: 'parent_images',
                        headerSort: false,
                        minWidth: 150,
                        formatter: function (cell) {
                            const list = Array.isArray(cell.getValue()) ? cell.getValue() : [];
                            let html = '<div class="vm-img-cell">';
                            html += '<span class="vm-img-count' + (list.length ? '' : ' is-zero') + '" title="Images uploaded for this parent">' + list.length + (list.length === 1 ? ' image' : ' images') + '</span>';
                            if (list.length) {
                                html += '<span class="vm-thumbs">';
                                list.slice(0, 3).forEach(function (url) {
                                    html += '<img src="' + escapeHtml(url) + '" alt="" loading="lazy">';
                                });
                                html += '</span>';
                            }
                            html += '<span class="vm-img-actions">'
                                + '<button type="button" class="vm-pill-btn vm-img-up" title="Upload images"><i class="bi bi-upload"></i></button>'
                                + (list.length ? '<button type="button" class="vm-pill-btn vm-img-down" title="Download all images (ZIP)"><i class="bi bi-download"></i></button>' : '')
                                + '</span></div>';
                            return html;
                        },
                        cellClick: function (e, cell) {
                            const data = cell.getRow().getData();
                            if (e.target.closest('.vm-img-down')) {
                                downloadParentImages(data);
                                return;
                            }
                            openImagesModal(data);
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

            // ---------- Show / hide columns (remembered per user through /tabulator-column-visibility-user) ----------
            const colVisGetUrl = @json(route('tabulator.column.visibility.user.get'));
            const colVisSetUrl = @json(route('tabulator.column.visibility.user.set'));
            const COL_VIS_CHANNEL = 'variation_master';
            const COL_KEEP_ON_HIDE_ALL = ['parent', 'sku'];

            function columnLabel(col) {
                return String(col.getDefinition().title || '').replace(/<[^>]*>/g, '').trim();
            }

            // Every column that has a title and a field (the tick-box column has neither, so it always stays).
            function toggleableColumns() {
                return table.getColumns().filter(function (col) { return col.getField() && columnLabel(col); });
            }

            function refreshColumnMenu() {
                const list = document.getElementById('vm-col-list');
                if (!list) return;
                const cols = toggleableColumns();
                list.innerHTML = '';
                cols.forEach(function (col) {
                    const label = document.createElement('label');
                    label.className = 'vm-col-item';
                    const box = document.createElement('input');
                    box.type = 'checkbox';
                    box.className = 'form-check-input m-0';
                    box.dataset.field = col.getField();
                    box.checked = col.isVisible();
                    label.appendChild(box);
                    label.appendChild(document.createTextNode(columnLabel(col)));
                    list.appendChild(label);
                });
                const shown = cols.filter(function (c) { return c.isVisible(); }).length;
                const countEl = document.getElementById('vm-col-count');
                if (countEl) countEl.textContent = shown + '/' + cols.length;
            }

            let colSaveTimer = null;
            function saveColumnVisibility() {
                clearTimeout(colSaveTimer);
                colSaveTimer = setTimeout(function () {
                    const visibility = {};
                    toggleableColumns().forEach(function (col) { visibility[col.getField()] = col.isVisible(); });
                    fetch(colVisSetUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify({ channel: COL_VIS_CHANNEL, visibility: visibility })
                    }).catch(function () { /* the choice still applies on this page */ });
                }, 300);
            }

            function setColumnVisible(col, visible) {
                if (visible) col.show(); else col.hide();
            }

            document.getElementById('vm-col-list')?.addEventListener('change', function (event) {
                const box = event.target.closest('input[data-field]');
                if (!box) return;
                const col = table.getColumn(box.dataset.field);
                if (!col) return;
                setColumnVisible(col, box.checked);
                refreshColumnMenu();
                saveColumnVisibility();
            });
            document.getElementById('vm-col-all')?.addEventListener('click', function () {
                toggleableColumns().forEach(function (col) { setColumnVisible(col, true); });
                refreshColumnMenu();
                saveColumnVisibility();
            });
            document.getElementById('vm-col-none')?.addEventListener('click', function () {
                toggleableColumns().forEach(function (col) { setColumnVisible(col, COL_KEEP_ON_HIDE_ALL.indexOf(col.getField()) !== -1); });
                refreshColumnMenu();
                saveColumnVisibility();
            });

            let colVisReady = false;
            function initColumnVisibility() {
                if (colVisReady) return;
                colVisReady = true;
                refreshColumnMenu();
                fetch(colVisGetUrl + '?channel=' + encodeURIComponent(COL_VIS_CHANNEL), { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf } })
                    .then(function (res) { return res.ok ? res.json() : {}; })
                    .then(function (saved) {
                        if (saved && typeof saved === 'object') {
                            Object.keys(saved).forEach(function (field) {
                                const col = table.getColumn(field);
                                if (!col) return;
                                const v = saved[field];
                                setColumnVisible(col, v === true || v === 1 || v === '1' || v === 'true');
                            });
                        }
                        refreshColumnMenu();
                    })
                    .catch(function () { refreshColumnMenu(); });
            }
            table.on('tableBuilt', initColumnVisibility);
            if (table.initialized) initColumnVisibility();

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
                    if (Array.isArray(json.issues) && json.issues.length) {
                        pushOverlay.done(false, json.issues.length + ' parent' + (json.issues.length === 1 ? '' : 's') + ' not updated', json.message || '', json.issues,
                            [['Reason']].concat(json.issues.map(function (r) { return [r]; })));
                    }
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
                    description: row.description || ''
                };
                bulletFields.forEach(function (field) {
                    snapshot[field] = row[field] || '';
                });
                return snapshot;
            }

            // Open the editor on a double click. The default also opens on focus, so one click is enough to start
            // typing and a second click inside the same cell closes it again before anything is saved.
            table.on('cellDblClick', function (_e, cell) {
                if (cell.getColumn().getDefinition().editor) {
                    cell.edit(true);
                }
            });

            table.on('cellEdited', function (cell) {
                const field = cell.getField();
                const listingFields = ['title', 'description'].concat(bulletFields);
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
                    // The save added the missing PARENT row for this family: reload so its row shows up.
                    if (result.json.parent_created) {
                        table.replaceData();
                    }
                }).catch(function (err) {
                    cell.restoreOldValue();
                    setStatus(err && err.message ? err.message : 'Could not save ' + (row.sku || ''), false);
                });
            });

            // ---- A+ content and parent images (shared by every child of the parent) ----
            const skuAplusUrl = @json(route('variation.master.sku.aplus'));
            const imagesUrl = @json(route('variation.master.parent.images.upload'));
            const imageDeleteUrl = @json(route('variation.master.parent.images.delete'));
            const imagesDownloadUrl = @json(route('variation.master.parent.images.download'));
            const aplusModalEl = document.getElementById('vm-aplus-modal');
            const imagesModalEl = document.getElementById('vm-images-modal');
            const aplusStatus = document.getElementById('vm-aplus-status');
            const imagesStatus = document.getElementById('vm-images-status');
            let panelRow = null;

            function applyParentPatch(rowData, patch) {
                const keepChild = patch && (Object.prototype.hasOwnProperty.call(patch, 'aplus') || Object.prototype.hasOwnProperty.call(patch, 'parent_images'));
                table.getRows().forEach(function (other) {
                    const data = other.getData();
                    if (!sameParent(data, rowData)) return;
                    if (keepChild && data.row_type === 'sku') return;
                    other.update(patch);
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

            // ---- "Edit descriptions" editor (same as Description Master): marketplace tabs + TinyMCE ----
            const mpConfig = window.__ALL_MP__ || {};
            const MP_LABELS = mpConfig.labels || {};
            const ALL_MP = (mpConfig.enabled && mpConfig.enabled.length) ? mpConfig.enabled : Object.keys(MP_LABELS);
            let descActiveMp = ALL_MP[0] || '';
            let descEditor = null;
            let descEditorPromise = null;
            let descSetting = false;

            function plainText(html) {
                const tmp = document.createElement('div');
                tmp.innerHTML = String(html || '');
                return (tmp.textContent || '').replace(/\s+/g, ' ').trim();
            }

            // Bootstrap modals steal focus from TinyMCE dialogs (link / image / table); let TinyMCE keep it.
            document.addEventListener('focusin', function (e) {
                if (e.target.closest && e.target.closest('.tox-tinymce-aux, .tox-dialog, .tox-tinymce') !== null) {
                    e.stopImmediatePropagation();
                }
            });

            function getDescContent() {
                if (descEditor) return descEditor.getContent();
                const el = document.getElementById('vm-desc-html');
                return el ? el.value : '';
            }

            function updateDescCount() {
                const el = document.getElementById('vm-desc-count');
                if (!el) return;
                el.textContent = plainText(getDescContent()).length.toLocaleString() + ' characters';
                el.classList.remove('at-limit');
            }

            function ensureDescEditor() {
                if (descEditor) return Promise.resolve(descEditor);
                if (typeof tinymce === 'undefined') return Promise.resolve(null);
                if (descEditorPromise) return descEditorPromise;
                descEditorPromise = tinymce.init({
                    selector: '#vm-desc-html',
                    license_key: 'gpl',
                    height: 380,
                    menubar: false,
                    plugins: 'lists link image table code',
                    toolbar: 'undo redo | blocks | bold italic | bullist numlist | table | link image | removeformat code',
                    branding: false,
                    promotion: false,
                    convert_urls: false,
                    content_style: 'img{max-width:100%;height:auto;} table{max-width:100%;border-collapse:collapse;} body{font-size:14px;}',
                    setup: function (editor) {
                        editor.on('input change keyup paste Undo Redo SetContent', updateDescCount);
                    }
                }).then(function (eds) {
                    descEditor = (eds && eds[0]) || (tinymce.get ? tinymce.get('vm-desc-html') : null);
                    return descEditor;
                }).catch(function () { return null; });
                return descEditorPromise;
            }

            function setDescContent(html) {
                return ensureDescEditor().then(function (ed) {
                    descSetting = true;
                    if (ed) ed.setContent(String(html || ''));
                    else document.getElementById('vm-desc-html').value = String(html || '');
                    descSetting = false;
                    updateDescCount();
                });
            }

            function descLoading(text) {
                const wrap = document.getElementById('vm-desc-loading');
                if (text) document.getElementById('vm-desc-loading-text').textContent = text;
                wrap.classList.toggle('d-none', !text);
            }

            function descButtonLoading(id, loading, text) {
                const btn = document.getElementById(id);
                if (!btn) return;
                if (!btn.dataset.originalText) btn.dataset.originalText = btn.innerHTML;
                btn.disabled = !!loading;
                btn.innerHTML = loading ? '<i class="fas fa-spinner fa-spin me-1"></i>' + escapeHtml(text || 'Loading...') : btn.dataset.originalText;
            }

            function descApi(url, payload) {
                return fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify(payload)
                }).then(function (res) { return res.json().catch(function () { return {}; }); });
            }

            function descFail(text) {
                aplusStatus.className = 'small me-auto text-danger';
                aplusStatus.textContent = text;
            }

            function descNote(text) {
                aplusStatus.className = 'small me-auto text-success';
                aplusStatus.textContent = text;
            }

            // Fetch the ACTIVE marketplace's live description of this row's SKU into the editor.
            function descFetchLive() {
                const sku = panelRow && panelRow.sku ? String(panelRow.sku) : '';
                const mp = descActiveMp;
                if (!sku || !mp) return;
                descLoading('Fetching live from ' + (MP_LABELS[mp] || mp) + '…');
                descButtonLoading('vm-desc-fetch-live', true, 'Fetching...');
                descApi('/product-description/pull-marketplace', { sku: sku, marketplace: mp }).then(function (res) {
                    if (res.success) {
                        setDescContent(String(res.description_html || res.description_plain || ''));
                        descNote(res.message || ('Fetched ' + (MP_LABELS[mp] || mp) + '. Review, then Save all.'));
                    } else {
                        descFail(res.message || 'Fetch failed.');
                    }
                }).catch(function (e) {
                    descFail('Fetch failed: ' + e.message);
                }).finally(function () {
                    descLoading('');
                    descButtonLoading('vm-desc-fetch-live', false);
                });
            }

            // Load the stored Shopify A+ content of this row's SKU (fetching it once if nothing is stored yet).
            function descFetchAplus() {
                const sku = panelRow && panelRow.sku ? String(panelRow.sku) : '';
                if (!sku) return;
                descLoading('Loading A+ content for ' + sku + '…');
                descButtonLoading('vm-desc-fetch-aplus', true, 'Fetching...');
                const headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf };
                fetch('/product-description/shopify-aplus?sku=' + encodeURIComponent(sku), { credentials: 'same-origin', headers: headers })
                    .then(function (res) { return res.json().catch(function () { return {}; }); })
                    .then(function (stored) {
                        const html = String(((stored && stored.data) || {}).html || '');
                        if (stored && stored.success && html.trim() !== '') return stored;
                        return descApi('/product-description/shopify-aplus/fetch', { sku: sku, force: false });
                    })
                    .then(function (out) {
                        const html = String(((out && out.data) || {}).html || '');
                        if (!out || !out.success || html.trim() === '') {
                            descFail((out && out.message) || 'No A+ content available for this SKU.');
                            return;
                        }
                        setDescContent(html);
                        descNote('A+ content loaded. Review, then Save all.');
                    })
                    .catch(function (e) { descFail('A+ fetch failed: ' + e.message); })
                    .finally(function () {
                        descLoading('');
                        descButtonLoading('vm-desc-fetch-aplus', false);
                    });
            }

            // AI-generate a description for the ACTIVE marketplace at its tier length.
            function descAiGenerate() {
                const mp = descActiveMp;
                if (!mp || !panelRow) return;
                descLoading('Generating description for ' + (MP_LABELS[mp] || mp) + '…');
                descButtonLoading('vm-desc-ai', true, 'Generating...');
                descApi('/product-description/generate', {
                    product_name: String(panelRow.title || parentLabel(panelRow) || ''),
                    current_text: plainText(getDescContent()),
                    marketplace: mp
                }).then(function (res) {
                    if (res.success && res.description) {
                        setDescContent(String(res.description));
                        descNote('AI description generated (' + plainText(res.description).length + ' chars). Review, then Save all.');
                    } else {
                        descFail(res.message || 'AI failed.');
                    }
                }).catch(function (e) {
                    descFail('AI error: ' + e.message);
                }).finally(function () {
                    descLoading('');
                    descButtonLoading('vm-desc-ai', false);
                });
            }

            document.getElementById('vm-desc-fetch-live')?.addEventListener('click', descFetchLive);
            document.getElementById('vm-desc-fetch-aplus')?.addEventListener('click', descFetchAplus);
            document.getElementById('vm-desc-ai')?.addEventListener('click', descAiGenerate);
            aplusModalEl?.addEventListener('shown.bs.modal', function () {
                ensureDescEditor().then(function () {
                    setDescContent(panelRow ? (panelRow.aplus || '') : '');
                });
            });

            function openAplusModal(row) {
                panelRow = row;
                document.getElementById('vm-desc-product').textContent = parentLabel(row);
                document.getElementById('vm-desc-sku').textContent = row.sku ? '· Fetch live / Fetch A+ read SKU ' + row.sku : '';
                aplusStatus.textContent = '';
                descLoading('');
                // Push to marketplace updates the PARENT listing only, so it is offered on PARENT rows.
                document.getElementById('vm-aplus-push-wrap')?.classList.toggle('d-none', row.row_type !== 'parent');
                const show = function () {
                    bootstrap.Modal.getOrCreateInstance(aplusModalEl).show();
                };
                if (row.row_type === 'sku' && !String(row.aplus || '').trim() && Number(row.aplus_chars || 0) > 0) {
                    aplusStatus.className = 'small me-auto text-muted';
                    aplusStatus.textContent = 'Loading A+ from Description Master…';
                    fetch(skuAplusUrl + '?sku=' + encodeURIComponent(row.sku), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                        .then(function (res) { return res.json().catch(function () { return null; }); })
                        .then(function (json) {
                            row.aplus = (json && json.html) || '';
                            row.aplus_chars = row.aplus ? row.aplus.length : Number(row.aplus_chars || 0);
                            aplusStatus.textContent = '';
                            show();
                        })
                        .catch(function () { show(); });
                    return;
                }
                show();
            }

            // Push to marketplace: save the editor as the parent's A+, then update that parent listing on eBay 2 / eBay 3.
            const pushOverlay = (function () {
                const el = document.getElementById('vm-push-overlay');
                const icon = el.querySelector('.vm-push-icon');
                const title = el.querySelector('.vm-push-title');
                const text = el.querySelector('.vm-push-text');
                const bar = el.querySelector('.vm-push-bar');
                const fill = bar.firstElementChild;
                const list = el.querySelector('.vm-push-list');
                const close = document.getElementById('vm-push-close');
                const dl = document.getElementById('vm-push-dl');
                let csvRows = null;
                close.addEventListener('click', function () { el.classList.remove('show'); });
                dl.addEventListener('click', function () {
                    if (!csvRows) return;
                    const csv = csvRows.map(function (r) {
                        return r.map(function (c) { return '"' + String(c == null ? '' : c).replace(/"/g, '""') + '"'; }).join(',');
                    }).join('\r\n');
                    const a = document.createElement('a');
                    a.href = URL.createObjectURL(new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8' }));
                    a.download = 'variation-master-failed-rows.csv';
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                });
                return {
                    busy: function (t, sub, percent) {
                        el.className = 'show is-busy';
                        icon.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                        title.textContent = t;
                        text.textContent = sub || '';
                        list.classList.add('d-none');
                        close.classList.add('d-none');
                        dl.classList.add('d-none');
                        bar.classList.toggle('d-none', typeof percent !== 'number');
                        if (typeof percent === 'number') fill.style.width = Math.max(3, percent) + '%';
                    },
                    done: function (ok, t, message, failures, csv) {
                        csvRows = csv || null;
                        dl.classList.toggle('d-none', !csv);
                        el.className = 'show ' + (ok ? 'is-ok' : 'is-fail');
                        icon.innerHTML = ok ? '<i class="fas fa-circle-check"></i>' : '<i class="fas fa-circle-xmark"></i>';
                        title.textContent = t;
                        text.textContent = message || '';
                        bar.classList.add('d-none');
                        list.innerHTML = '';
                        (failures || []).forEach(function (f) {
                            const li = document.createElement('li');
                            li.textContent = f;
                            list.appendChild(li);
                        });
                        list.classList.toggle('d-none', !(failures && failures.length));
                        close.classList.remove('d-none');
                        close.focus();
                    }
                };
            })();
            const pushAplusUrl = @json(route('variation.master.push.aplus'));
            const PUSH_LABELS = { ebay2: 'eBay 2', ebay3: 'eBay 3' };
            document.querySelectorAll('#vm-aplus-push-wrap [data-push-mp]').forEach(function (item) {
                item.addEventListener('click', function (event) {
                    event.preventDefault();
                    const mp = item.dataset.pushMp;
                    const row = panelRow;
                    const pushBtn = document.getElementById('vm-aplus-push');
                    const saveBtn = document.getElementById('vm-aplus-save');
                    if (!row || row.row_type !== 'parent' || pushBtn.disabled) return;
                    const html = getDescContent();
                    if (!String(html || '').trim()) {
                        descFail('Write the A+ content first.');
                        return;
                    }
                    if (!window.confirm('Save this A+ content and update the PARENT listing ' + (row.sku || parentLabel(row)) + ' on ' + PUSH_LABELS[mp] + '? Child listings are not changed.')) return;
                    pushBtn.disabled = true;
                    saveBtn.disabled = true;
                    const label = row.sku || parentLabel(row);
                    aplusStatus.className = 'small me-auto text-muted';
                    aplusStatus.textContent = 'Saving A+…';
                    pushOverlay.busy('Saving A+ content…', label);
                    postJson(saveUrl, { id: row.id, parent: row.parent || '', aplus: html }).then(function (json) {
                        applyParentPatch(row, { aplus: json.aplus || '' });
                        aplusStatus.textContent = 'Pushing to ' + PUSH_LABELS[mp] + '…';
                        pushOverlay.busy('Pushing A+ to ' + PUSH_LABELS[mp] + '…', label + ' · this can take up to a minute');
                        return postJson(pushAplusUrl, { id: row.id, marketplace: mp });
                    }).then(function (json) {
                        const msg = json.message || ('A+ updated on ' + PUSH_LABELS[mp] + '.');
                        descNote(msg);
                        setStatus(msg, true);
                        pushOverlay.done(true, 'A+ pushed to ' + PUSH_LABELS[mp], msg);
                    }).catch(function (err) {
                        const msg = (err && err.message) || 'Push failed.';
                        descFail(msg);
                        setStatus(msg, false);
                        pushOverlay.done(false, 'Push to ' + PUSH_LABELS[mp] + ' failed', msg);
                    }).finally(function () {
                        pushBtn.disabled = false;
                        saveBtn.disabled = false;
                    });
                });
            });

            // Bulk "Push A+": the saved A+ of every ticked PARENT row, one parent after another.
            document.querySelectorAll('[data-bulk-push]').forEach(function (item) {
                item.addEventListener('click', function (event) {
                    event.preventDefault();
                    const mp = item.dataset.bulkPush;
                    const label = PUSH_LABELS[mp];
                    const picked = table.getSelectedRows().map(function (r) { return r.getData(); });
                    const parents = [];
                    const seen = {};
                    picked.forEach(function (d) {
                        if (d.row_type === 'parent' && !seen[d.id]) { seen[d.id] = true; parents.push(d); }
                    });
                    if (!parents.length) {
                        const msg = picked.length
                            ? 'Only PARENT rows can be pushed. Tick at least one PARENT row.'
                            : 'Tick the PARENT rows whose A+ you want to push first.';
                        setStatus(msg, false);
                        pushOverlay.done(false, 'Nothing to push', msg);
                        return;
                    }
                    if (!window.confirm('Push the saved A+ content of ' + parents.length + ' parent' + (parents.length === 1 ? '' : 's') + ' to ' + label + '? Child listings are not changed.')) return;

                    const failures = [];
                    let okCount = 0;
                    let index = 0;
                    const tag = function (d) { return d.sku || parentLabel(d); };
                    pushOverlay.busy('Pushing A+ to ' + label + '…', 'Starting…', 0);
                    const next = function () {
                        if (index >= parents.length) return Promise.resolve();
                        const d = parents[index++];
                        pushOverlay.busy('Pushing A+ to ' + label + '… (' + index + ' of ' + parents.length + ')', tag(d), (index - 1) / parents.length * 100);
                        return postJson(pushAplusUrl, { id: d.id, marketplace: mp }).then(function () {
                            okCount++;
                        }).catch(function (err) {
                            failures.push((err && err.message) || (tag(d) + ': failed'));
                        }).then(next);
                    };
                    next().then(function () {
                        const summary = okCount + ' of ' + parents.length + ' parent' + (parents.length === 1 ? '' : 's') + ' updated on ' + label + '.';
                        setStatus(summary + (failures.length ? ' ' + failures.length + ' failed.' : ''), failures.length === 0);
                        pushOverlay.done(failures.length === 0, failures.length === 0 ? 'A+ pushed to ' + label : (okCount ? 'Pushed with errors' : 'Push to ' + label + ' failed'), summary, failures);
                    });
                });
            });

            document.getElementById('vm-aplus-save')?.addEventListener('click', function () {
                const btn = this;
                if (!panelRow || btn.disabled) return;
                btn.disabled = true;
                aplusStatus.className = 'small me-auto text-muted';
                aplusStatus.textContent = 'Saving…';
                const row = panelRow;
                const html = getDescContent();
                if (row.row_type === 'sku') {
                    descApi('/product-description/shopify-aplus/save', { sku: row.sku, html: html }).then(function (json) {
                        if (!json || json.success === false) {
                            throw new Error((json && json.message) || 'Save failed.');
                        }
                        row.aplus = html;
                        row.aplus_chars = html.length;
                        const live = table.getRows().find(function (other) { return String(other.getData().id) === String(row.id); });
                        if (live) live.update({ aplus: html, aplus_chars: html.length });
                        bootstrap.Modal.getOrCreateInstance(aplusModalEl).hide();
                        setStatus('Saved A+ content for ' + (row.sku || parentLabel(row)), true);
                    }).catch(function (err) {
                        aplusStatus.className = 'small me-auto text-danger';
                        aplusStatus.textContent = err.message || 'Save failed.';
                    }).finally(function () {
                        btn.disabled = false;
                    });
                    return;
                }
                postJson(saveUrl, { id: row.id, parent: row.parent || '', aplus: html }).then(function (json) {
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
                document.getElementById('vm-images-count').textContent = list.length + (list.length === 1 ? ' image' : ' images') + ' uploaded';
                document.getElementById('vm-images-download').disabled = !list.length;
                if (!list.length) {
                    grid.innerHTML = '<div class="small text-muted">No parent images yet. Choose files above to upload.</div>';
                    return;
                }
                grid.innerHTML = list.map(function (url, i) {
                    return '<div class="vm-img-tile"><a href="' + escapeHtml(url) + '" target="_blank" rel="noopener"><img src="' + escapeHtml(url) + '" alt=""></a>'
                        + '<span class="vm-img-no">' + (i + 1) + '</span>'
                        + '<a class="vm-img-dl" href="' + escapeHtml(url) + '" download title="Download this image"><i class="bi bi-download"></i></a>'
                        + '<button type="button" class="vm-img-del" data-url="' + escapeHtml(url) + '" title="Remove this image">&times;</button></div>';
                }).join('');
            }

            function downloadParentImages(row, statusTarget) {
                const target = statusTarget || null;
                const say = function (text, ok) {
                    if (target) {
                        target.className = 'small me-auto ' + (ok ? 'text-success' : 'text-danger');
                        target.textContent = text;
                    } else {
                        setStatus(text, ok);
                    }
                };
                say('Preparing the ZIP…', true);
                fetch(imagesDownloadUrl + '?parent=' + encodeURIComponent(parentLabel(row)), {
                    headers: { 'X-CSRF-TOKEN': csrf }
                }).then(function (res) {
                    if (!res.ok) {
                        return res.json().catch(function () { return null; }).then(function (json) {
                            throw new Error((json && json.message) || 'Download failed.');
                        });
                    }
                    return res.blob();
                }).then(function (blob) {
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(blob);
                    link.download = parentLabel(row).replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '') + '-images.zip';
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    setTimeout(function () { URL.revokeObjectURL(link.href); }, 2000);
                    say('Downloaded the images of ' + parentLabel(row) + '.', true);
                }).catch(function (err) {
                    say(err.message || 'Download failed.', false);
                });
            }

            document.getElementById('vm-images-download')?.addEventListener('click', function () {
                if (panelRow) downloadParentImages(panelRow, imagesStatus);
            });

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
                pushOverlay.busy('Uploading ' + file.name + '…', 'Importing rows, please wait.');
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
                    const json = result.json || {};
                    let message = json.message || (result.ok ? 'Uploaded.' : 'Upload failed.');
                    if (!result.ok && json.errors) {
                        const first = Object.values(json.errors)[0];
                        if (first && first[0]) message = first[0];
                    }
                    const good = result.ok && json.success !== false;
                    setStatus(message, good);
                    if (result.ok) table.replaceData();
                    const issues = Array.isArray(json.issues) ? json.issues : [];
                    if (issues.length) {
                        const lines = issues.map(function (i) {
                            const who = [i.sku ? 'SKU ' + i.sku : '', i.parent ? 'Parent ' + i.parent : ''].filter(Boolean).join(' · ') || 'Row';
                            return 'Row ' + i.row + ' — ' + who + ': ' + i.reason;
                        });
                        const csv = [['Row', 'SKU', 'Parent', 'Type', 'Reason']].concat(issues.map(function (i) {
                            return [i.row, i.sku, i.parent, i.kind, i.reason];
                        }));
                        pushOverlay.done(false, issues.length + ' row' + (issues.length === 1 ? '' : 's') + ' not imported', message, lines, csv);
                    } else {
                        pushOverlay.done(good, good ? 'Upload complete' : 'Upload failed', message);
                    }
                }).catch(function () {
                    setStatus('Upload failed.', false);
                    pushOverlay.done(false, 'Upload failed', 'The server did not return a readable answer. If the file is large, try again or split it.');
                });
            });
        });
    </script>
@endsection
