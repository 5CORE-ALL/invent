@extends('layouts.vertical', ['title' => 'MIP'])
@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator .tabulator-header {
            background: #D8F3F3;
            border-bottom: 1px solid #403f3f;
        }
        .tabulator .tabulator-header .tabulator-col {
            text-align: center;
            background: #3bc0c3;
            border-right: 1px solid #fff;
            padding: 10px 6px;
            font-weight: 700;
            color: #fff;
            font-size: 0.9rem;
        }
        .tabulator-row { background-color: #fff !important; }
        .tabulator-row:nth-child(even) { background-color: #f8fafc !important; }
        .tabulator-row:hover { background-color: #dbeafe !important; }
        .tabulator .tabulator-cell {
            text-align: center;
            padding: 6px;
            border-right: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.85rem;
        }
        .tabulator .tabulator-cell.mip-new-image-cell { padding: 0 !important; line-height: 0; }
        .mip-new-img-aspect { width: 44px; height: 44px; margin: 0 auto; }
        .mip-new-img-aspect img { width: 100%; height: 100%; object-fit: contain; cursor: pointer; display: block; }

        /* Executive colored select */
        .toa-exec-select { border: none; border-radius: 6px; padding: 3px 6px; font-size: 0.8rem; font-weight: 600; cursor: pointer; outline: none; width: 100%; }

        /* Stage dot + invisible select overlay */
        .mip-stage-dot { position: relative; width: 44px; height: 30px; margin: 0 auto; }
        .mip-stage-marker { width: 100%; height: 100%; display: inline-flex; align-items: center; justify-content: center; pointer-events: none; }
        .mip-stage-dot .stage-status-dot { display: inline-block; width: 16px; height: 16px; border-radius: 50%; }
        .mip-stage-dot .stage-transit-icon { color: #0ea5e9; font-size: 15px; }
        .mip-stage-dot .stage-stage-select { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }

        /* Status dot toggles (Pkg/U-Manual/Compliance) */
        .mip-status-dot { display: inline-block; width: 16px; height: 16px; border-radius: 50%; cursor: pointer; border: 1px solid rgba(0,0,0,0.1); }

        /* Supplier cell — plain text display, click to edit (Tabulator's `editor: "list"`
           opens a searchable picker on click). Kept very subtle so the cell looks like
           regular data, not a permanently-open form control. */
        .mip-supplier-text { display: inline-block; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; vertical-align: middle; }
        /* Make the autocomplete edit list comfortably readable */
        .tabulator-edit-list { max-height: 260px; }
        .tabulator-edit-list .tabulator-edit-list-item { font-size: 12px; padding: 5px 8px; }

        /* Communication logos */
        .mip-plat-icon-link { display: inline-flex; align-items: center; justify-content: center; text-decoration: none; transition: transform 0.12s; }
        .mip-plat-icon-link:hover { transform: scale(1.2); }
        .mip-plat-menu { padding: 6px; min-width: auto; }

        /* Footer / pagination */
        .tabulator .tabulator-footer { background: #f4f7fa; border-top: 1px solid #cbd5e1; padding: 6px; }
        .tabulator .tabulator-footer .tabulator-page { padding: 6px 12px; margin: 0 3px; border-radius: 6px; }
        .tabulator .tabulator-footer .tabulator-page.active { background: #3bc0c3; color: #fff; }
        /* Default to ellipsis-truncation so cells don't blow up vertically, but the
           tooltip set in columnDefaults lets users hover to read the full value. */
        .tabulator .tabulator-cell { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        /* When the table fits to data and there's room, let it use the full
           card width so columns can stretch instead of leaving a gap on the right. */
        #mfrg-table { width: 100%; }

        /* Column show/hide menu */
        .mip-columns-wrap { position: relative; }
        .mip-columns-menu {
            position: absolute; z-index: 4000; top: 100%; left: 0; margin-top: 4px;
            background: #fff; border: 1px solid #cbd5e1; border-radius: 8px;
            padding: 8px 10px; min-width: 210px; max-height: 340px; overflow: auto;
            box-shadow: 0 6px 18px rgba(0,0,0,0.12);
        }
        .mip-columns-menu .mip-columns-head {
            display: flex; justify-content: space-between; align-items: center;
            gap: 8px; margin-bottom: 6px; padding-bottom: 6px; border-bottom: 1px solid #e2e8f0;
        }
        .mip-columns-menu .form-check { margin-bottom: 3px; }
        .mip-columns-menu .form-check-label { cursor: pointer; }

        /* SKU copy icon */
        .mip-sku-copy { margin-left: 6px; cursor: pointer; color: #3bc0c3; font-size: 0.8rem; opacity: 0.75; transition: opacity 0.12s, color 0.12s; }
        .mip-sku-copy:hover { opacity: 1; color: #2563eb; }
        .mip-sku-copy.copied { color: #16a34a; opacity: 1; }

        /* ---- Toolbar + Summary strip (Amazon Analytics-style layout) ---- */
        .mip-toolbar {
            display: flex; flex-wrap: wrap; align-items: center; gap: 8px;
            margin-bottom: 10px;
        }
        .mip-toolbar-row {
            display: flex; flex-wrap: wrap; align-items: center; gap: 8px;
            width: 100%;
        }
        .mip-toolbar .mip-field { display: flex; align-items: center; }
        .mip-toolbar .mip-filter-field {
            width: 130px;
            min-width: 0;
        }
        .mip-toolbar .mip-filter-field--wide { width: 170px; }
        .mip-toolbar .mip-filter-field--sku,
        .mip-toolbar .mip-filter-field--supplier { width: 140px; }

        .mip-summary {
            display: flex;
            flex-wrap: nowrap;
            align-items: stretch;
            gap: 6px;
            width: 100%;
            margin-bottom: 12px;
        }
        .mip-summary-badge {
            flex: 1 1 0;
            min-width: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: clamp(4px, 0.8vw, 10px);
            padding: clamp(10px, 1.2vw, 14px) clamp(8px, 1vw, 16px);
            border-radius: 10px;
            font-size: clamp(0.78rem, 1.1vw, 1.05rem);
            font-weight: 600;
            color: #fff;
            line-height: 1.2;
            white-space: nowrap;
            text-align: center;
            box-shadow: 0 2px 4px rgba(15, 23, 42, 0.15);
        }
        .mip-summary-badge .label { opacity: 0.95; font-weight: 500; flex-shrink: 0; }
        .mip-summary-badge .value {
            font-weight: 700;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        @media (max-width: 1100px) {
            .mip-summary {
                overflow-x: auto;
                scrollbar-width: thin;
            }
            .mip-summary-badge {
                flex: 0 0 auto;
                min-width: max-content;
                font-size: 0.78rem;
                padding: 10px 12px;
            }
        }

        .mip-badge--amount    { background: #2563eb; }   /* blue */
        .mip-badge--cbm       { background: #14b8a6; }   /* teal */
        .mip-badge--items     { background: #0f172a; }   /* dark */
        .mip-badge--suppliers { background: #7c3aed; }   /* purple */

        /* "Show archived" toggle — rendered as an icon badge (eye + trash) that
           visibly switches between off (outlined / muted) and on (solid amber).
           The underlying <input type="checkbox"> is visually-hidden but still gets
           toggled via the <label for=…> association, so the existing JS change
           handler runs unchanged. */
        .mip-archive-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 12px; border-radius: 999px;
            font-size: 0.9rem; line-height: 1; white-space: nowrap;
            cursor: pointer; user-select: none;
            background: #f1f5f9; color: #475569;
            border: 1px solid #cbd5e1;
            transition: background 0.15s, color 0.15s, border-color 0.15s, box-shadow 0.15s;
        }
        .mip-archive-badge:hover { background: #e2e8f0; border-color: #94a3b8; }
        .mip-archive-badge:focus-visible { outline: 2px solid #3b82f6; outline-offset: 2px; }
        /* When the hidden checkbox is checked, the adjacent label "lights up"
           amber to indicate archived rows are visible. */
        #show-archived-toggle:checked + .mip-archive-badge {
            background: #f59e0b;
            color: #fff;
            border-color: #d97706;
            box-shadow: 0 1px 4px rgba(245, 158, 11, 0.35);
        }
        #show-archived-toggle:checked + .mip-archive-badge:hover {
            background: #d97706;
            border-color: #b45309;
        }

        /* Supplier summary modal */
        .mip-sup-history-block { background: #fff; }
        .mip-sup-history-block .mip-sup-history-head {
            display: flex; justify-content: space-between; align-items: center; gap: 8px;
            padding: 8px 12px; background: #f1f5f9; border-bottom: 1px solid #e2e8f0;
            font-weight: 600; font-size: 0.9rem;
        }
        .mip-sup-history-block .mip-sup-history-totals {
            font-size: 0.75rem; color: #64748b; font-weight: 500;
        }
        .mip-sup-history-block .list-group-item { border-left: none; border-right: none; }
        .mip-sup-history-block .list-group-item:first-child { border-top: none; }

        /* Pre-MIP CL column */
        .mip-cl-cell { display: flex; align-items: center; justify-content: center; gap: 4px; }
        .mip-cl-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; border: 1px solid rgba(0,0,0,0.08); }
        .mip-cl-item-row { display: flex; align-items: center; gap: 8px; padding: 6px 0; border-bottom: 1px solid #f1f5f9; }
        .mip-cl-item-row:last-child { border-bottom: none; }
        .mip-cl-item-row .form-check { flex: 1; margin: 0; }
        .mip-cl-item-row .mip-cl-remove-item { color: #94a3b8; }
    </style>
@endsection
@section('content')
    @php
        $canMipArchive = strtolower(trim(auth()->user()->email ?? '')) === 'president@5core.com';
    @endphp
    @include('layouts.shared.page-title', ['page_title' => 'MIP', 'sub_title' => 'MIP'])

    <div class="row">
        <div class="col-12">
            {{-- Summary badges — full-width row above the card --}}
            <div class="mip-summary" aria-label="Summary">
                <span class="mip-summary-badge mip-badge--amount" title="Amount">
                    <span class="label">💰 Amount</span>
                    <span class="value" id="totalAmount">0</span>
                </span>
                <span class="mip-summary-badge mip-badge--cbm" title="Total CBM">
                    <span class="label">📦 CBM</span>
                    <span class="value" id="totalCBM">0</span>
                </span>
                <span class="mip-summary-badge mip-badge--items" title="Items in current view">
                    <span class="label">🔢 Items</span>
                    <span class="value" id="totalItems">0</span>
                </span>
                <span class="mip-summary-badge mip-badge--suppliers" title="Unique suppliers in current view">
                    <span class="label">👥 Suppliers</span>
                    <span class="value" id="totalSuppliers">0</span>
                </span>
            </div>
        </div>
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    {{-- ===== Toolbar: filters row + actions row ===== --}}
                    <div class="mip-toolbar">
                        {{-- Row 1: filters & search --}}
                        <div class="mip-toolbar-row">
                            @include('purchase-master.partials.page-info-toolbar', ['pageKey' => 'mip'])

                            <div class="mip-field">
                                <select id="mip-stage-filter" class="form-select form-select-sm border-primary mip-filter-field" aria-label="Stage filter" title="Stage filter">
                                    <option value="">Stage</option>
                                    <option value="mip">MIP only</option>
                                    <option value="r2s">R2S only</option>
                                </select>
                            </div>

                            <div class="mip-field">
                                <select id="mip-exec-filter" class="form-select form-select-sm border-primary mip-filter-field" aria-label="Executive filter" title="Executive filter">
                                    <option value="">Executive</option>
                                    <option value="__un__">Unassigned</option>
                                    <option value="Atin">Atin</option>
                                    <option value="Jack">Jack</option>
                                    <option value="Nitish">Nitish</option>
                                    <option value="Ajay">Ajay</option>
                                    <option value="Candy">Candy</option>
                                    <option value="Sruti">Sruti</option>
                                </select>
                            </div>

                            <div class="mip-field">
                                <input type="text" id="mip-sku-search" class="form-control form-control-sm border-primary mip-filter-field mip-filter-field--sku" placeholder="SKU…" autocomplete="off" aria-label="SKU filter">
                            </div>

                            <div class="mip-field">
                                <input type="text" id="mip-supplier-search" class="form-control form-control-sm border-primary mip-filter-field mip-filter-field--supplier" placeholder="Supplier…" autocomplete="off" aria-label="Supplier filter">
                            </div>

                            {{-- ── Supplier Play/Pause (mirrors /forecast.analysis Supplier play) ── --}}
                            <div class="mip-field" title="Step through rows one supplier at a time">
                                <div class="d-flex align-items-center gap-1 border rounded px-2 py-1 bg-light">
                                    <button type="button" id="mip-supplier-play-backward" class="btn btn-light btn-sm rounded-circle p-0" style="width:28px;height:28px;" title="Prev supplier">
                                        <i class="fas fa-step-backward" style="font-size:10px;"></i>
                                    </button>
                                    <button type="button" id="mip-supplier-play-pause" class="btn btn-warning btn-sm rounded-circle p-0" style="width:28px;height:28px;display:none;" title="Stop supplier">
                                        <i class="fas fa-pause" style="font-size:10px;"></i>
                                    </button>
                                    <button type="button" id="mip-supplier-play-auto" class="btn btn-outline-warning btn-sm rounded-circle p-0 fw-bold" style="width:28px;height:28px;font-size:11px;" title="Play by supplier">S</button>
                                    <button type="button" id="mip-supplier-play-forward" class="btn btn-light btn-sm rounded-circle p-0" style="width:28px;height:28px;" title="Next supplier">
                                        <i class="fas fa-step-forward" style="font-size:10px;"></i>
                                    </button>
                                    <span class="badge bg-warning text-dark" id="mip-supplier-play-label" style="font-size:0.65rem;display:none;max-width:110px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
                                </div>
                            </div>

                            <div class="mip-field">
                                <input type="text" id="search-input" class="form-control form-control-sm border-primary mip-filter-field mip-filter-field--wide" placeholder="Search…" title="Search all columns" aria-label="Search all columns">
                            </div>

                            <div class="mip-field mip-columns-wrap">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="mip-columns-btn" title="Show / hide columns" aria-label="Show / hide columns">
                                    <i class="fas fa-table-columns"></i>
                                </button>
                                <div id="mip-columns-menu" class="mip-columns-menu" style="display:none;"></div>
                            </div>

                            <div class="d-flex align-items-center ms-auto gap-2">
                                @if ($canMipArchive)
                                <input class="visually-hidden" type="checkbox" id="show-archived-toggle">
                                <label for="show-archived-toggle"
                                       id="show-archived-badge"
                                       class="mip-archive-badge"
                                       title="Show archived rows"
                                       role="button"
                                       tabindex="0">
                                    <i class="fas fa-eye"></i>
                                    <i class="fas fa-trash-alt"></i>
                                </label>
                                @endif
                                <button type="button" class="btn btn-sm btn-info text-white" id="mip-supplier-summary-btn" title="Supplier-wise summary with chat history">
                                    <i class="fas fa-table-list"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-info text-white" id="mip-followup-btn" title="Follow-Up"><i class="fas fa-comment-dots"></i></button>
                                @if ($canMipArchive)
                                <button type="button" class="btn btn-sm btn-warning d-none" id="archive-selected-btn" title="Archive selected"><i class="fas fa-archive"></i></button>
                                <button type="button" class="btn btn-sm btn-success d-none" id="restore-selected-btn" title="Restore selected"><i class="fas fa-undo"></i></button>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Bulk edit badge (shown when rows selected — mirrors forecast page) --}}
                    <div id="mip-bulk-edit-badge" class="d-none mb-2 p-2 rounded border bg-light d-flex align-items-center gap-2 flex-wrap" style="min-height: 40px;">
                        <span class="fw-semibold text-dark" id="mip-bulk-edit-count">0 selected</span>
                        <span class="text-muted small">Select rows with checkboxes, then click <strong>Edit</strong> on any row to apply changes to all selected.</span>
                        <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="mip-bulk-cl-btn" title="Bulk edit Pre-MIP checklist for selected rows">
                            <i class="mdi mdi-magnify me-1"></i> CL Bulk Edit
                        </button>
                    </div>

                    <div id="mfrg-table"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Edit All Fields Modal (president@5core.com only) --}}
    <div class="modal fade" id="mipEditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-pen me-2"></i> Edit Row <span id="mip-edit-sku" class="ms-2 fw-normal small"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="mip-edit-subtitle" class="text-muted small mb-2"></p>
                    <div id="mip-edit-form" class="row g-3"></div>
                    <div id="mip-edit-status" class="small mt-2"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="mip-edit-save"><i class="fas fa-save me-1"></i> Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Pre-MIP Checklist (CL) Modal --}}
    <div class="modal fade" id="mipPreMipClModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-secondary text-white">
                    <h5 class="modal-title"><i class="mdi mdi-magnify me-2"></i> Pre-MIP Checklist <span id="mip-cl-modal-title" class="ms-2 fw-normal small"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="mip-cl-modal-subtitle" class="text-muted small mb-3">Verify all points before MIP: QC, printing approvals, compliance, instructions, and delivery.</p>
                    <div id="mip-cl-items-list" class="mb-3"></div>
                    <div class="d-flex gap-2 mb-3">
                        <input type="text" id="mip-cl-new-item" class="form-control form-control-sm" placeholder="Add checklist point…" maxlength="500">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="mip-cl-add-item-btn"><i class="fas fa-plus"></i> Add</button>
                    </div>
                    <div id="mip-cl-escalate-wrap" class="mb-2">
                        <label class="form-label small fw-semibold" for="mip-cl-escalation-note">Escalation note (required when escalating)</label>
                        <textarea id="mip-cl-escalation-note" class="form-control form-control-sm" rows="2" placeholder="Why is this being escalated? Which points failed?"></textarea>
                    </div>
                    <div id="mip-cl-status" class="small"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="mip-cl-escalate-btn"><i class="fas fa-bell me-1"></i> Escalate</button>
                    <button type="button" class="btn btn-success" id="mip-cl-update-btn"><i class="fas fa-check me-1"></i> Update</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Supplier Summary Modal (grouped totals + chat history) --}}
    <div class="modal fade" id="mipSupplierSummaryModal" tabindex="-1" aria-labelledby="mipSupplierSummaryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="mipSupplierSummaryModalLabel">
                        <i class="fas fa-table-list me-2"></i>Supplier Summary
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Totals are from <strong>currently visible</strong> rows (after all filters). Chat history is shown per supplier below.</p>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-striped table-bordered align-middle mb-0" id="mip-supplier-summary-table">
                            <thead class="table-light">
                                <tr>
                                    <th>Supplier</th>
                                    <th class="text-end">Items</th>
                                    <th class="text-end">QTY</th>
                                    <th class="text-end">CBM</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody id="mip-supplier-summary-tbody"></tbody>
                            <tfoot class="table-secondary fw-bold" id="mip-supplier-summary-tfoot"></tfoot>
                        </table>
                    </div>
                    <h6 class="fw-semibold mb-3"><i class="fas fa-comments me-1"></i> Chat History by Supplier</h6>
                    <div id="mip-supplier-summary-history"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" id="mip-supplier-summary-csv-btn" title="Download summary as CSV">
                        <i class="fas fa-file-csv me-1"></i> Export CSV
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Follow-Up / Current Status Modal --}}
    <div class="modal fade" id="mipFollowupModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fas fa-comment-dots me-2"></i> Current Status</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Supplier</label>
                        <select id="followup-supplier-select" class="form-select">
                            <option value="">-- Select supplier --</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Current Status / Remark</label>
                        <textarea id="followup-remark-input" class="form-control" rows="3" placeholder="Report the current status..."></textarea>
                    </div>
                    <div class="text-end mb-3">
                        <button type="button" id="followup-save-btn" class="btn btn-primary"><i class="fas fa-save me-1"></i> Submit</button>
                    </div>
                    <hr>
                    <h6 class="fw-semibold mb-2">History</h6>
                    <div id="followup-history-list"><p class="text-muted small mb-0">Select a supplier to view history.</p></div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            document.body.style.zoom = "96%";
            document.documentElement.setAttribute("data-sidenav-size", "condensed");

            const CSRF = '{{ csrf_token() }}';
            const USER_EMAIL = '{{ strtolower(trim(auth()->user()->email ?? "")) }}';
            const PRIVILEGED_EMAILS = ['president@5core.com', 'purchase@5core.com', 'software5@5core.com'];
            const CAN_EDIT_ALL = PRIVILEGED_EMAILS.includes(USER_EMAIL);
            const CAN_ARCHIVE = USER_EMAIL === 'president@5core.com';
            let uniqueSuppliers = [];
            let showArchived = false;
            let table;
            let mipBulkSelectionCache = [];
            const mipRowEditState = { row: null, targetRows: null, pendingBulkTargets: null };

            function isSelectableMipRow(row) {
                const d = (row && typeof row.getData === 'function') ? row.getData() : {};
                return !!(String(d.sku || '').trim() || d.id);
            }

            function dedupeMipRows(rows) {
                const seen = new Set();
                return (rows || []).filter(function (row) {
                    if (!isSelectableMipRow(row)) return false;
                    const d = row.getData() || {};
                    const key = String(d.id || '') + '||' + String(d.source_table || '') + '||' + String(d.sku || '').trim();
                    if (!key || seen.has(key)) return false;
                    seen.add(key);
                    return true;
                });
            }

            /** Checkbox-selected rows; keeps multi-select when focus moves to the Edit button. */
            function getMipBulkTargetRows(primarySku, extraRows) {
                const merged = dedupeMipRows([
                    ...(mipBulkSelectionCache || []),
                    ...(table && table.getSelectedRows ? table.getSelectedRows() : []),
                    ...(extraRows || [])
                ]);
                if (merged.length > 0) return merged;

                if (primarySku && table) {
                    const match = table.getRows().find(function (r) {
                        return String((r.getData() || {}).sku || '').trim() === String(primarySku).trim();
                    });
                    if (match && isSelectableMipRow(match)) return [match];
                }
                return [];
            }

            function updateMipBulkEditBadge() {
                const badge = document.getElementById('mip-bulk-edit-badge');
                const countEl = document.getElementById('mip-bulk-edit-count');
                if (!badge || !countEl) return;
                const n = dedupeMipRows(table ? table.getSelectedRows() : []).length;
                if (n > 0) {
                    badge.classList.remove('d-none');
                    badge.classList.add('d-flex');
                    countEl.textContent = n + ' selected';
                } else {
                    badge.classList.add('d-none');
                    badge.classList.remove('d-flex');
                }
            }

            // Full supplier list from the database (supplier.list — Supplier type), used for the
            // searchable Supplier dropdown so every supplier is selectable, not just ones already in the grid.
            const ALL_SUPPLIERS = @json($allSuppliers ?? []);

            const EXEC_OPTIONS = ['Atin', 'Jack', 'Nitish', 'Ajay', 'Candy', 'Sruti'];
            const EXEC_COLORS = {
                'Atin':   { bg: '#3b82f6', text: '#fff' },
                'Jack':   { bg: '#10b981', text: '#fff' },
                'Nitish': { bg: '#8b5cf6', text: '#fff' },
                'Ajay':   { bg: '#f59e0b', text: '#fff' },
                'Candy':  { bg: '#ec4899', text: '#fff' },
                'Sruti':  { bg: '#14b8a6', text: '#fff' },
            };
            const STAGE_COLORS = {
                'appr_req': '#facc15', 'mip': '#2563eb', 'to_order_analysis': '#c2410c',
                'r2s': '#16a34a', 'all_good': '#22c55e', '': '#94a3b8',
            };
            // Human-readable labels for the Stage column tooltip (shown on hover over the
            // colored dot). Kept in lockstep with the <option>s rendered inside stageFormatter.
            const STAGE_LABELS = {
                'appr_req': 'Appr. Req',
                'mip': 'MIP',
                'r2s': 'R2S',
                'transit': 'Transit',
                'all_good': 'All Good',
                'to_order_analysis': '2 Order',
                '': 'Not set',
            };
            const PLAT_ICON = { 'Website': 'fas fa-globe', 'Email': 'fas fa-envelope', 'WhatsApp': 'fab fa-whatsapp', 'WeChat': 'fab fa-weixin', 'Alibaba': 'fas fa-store' };
            const PLAT_COLOR = { 'Website': '#2563eb', 'Email': '#dc3545', 'WhatsApp': '#25d366', 'WeChat': '#09b83e', 'Alibaba': '#ff6a00' };

            const DEFAULT_PRE_MIP_ITEMS = [
                { id: 'qc', label: 'QC', checked: false },
                { id: 'printing_approvals', label: 'Printing approvals', checked: false },
                { id: 'compliance', label: 'Compliance', checked: false },
                { id: 'instructions_followed', label: 'Instructions followed', checked: false },
                { id: 'timely_delivery', label: 'Timely Delivery', checked: false },
            ];

            const mipClState = { mode: 'single', rows: [], items: [] };

            function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;'); }

            // Per-unit cost price: price_from_po, then rate, then product_master CP.
            function rowCp(d) {
                return parseFloat(d.price_from_po) || parseFloat(d.rate) || parseFloat(d.product_cp) || 0;
            }
            // Per-row Amount = CP * qty — same math as the Amount badge.
            function rowAmount(d) {
                const qty = parseFloat(d.qty) || 0;
                return rowCp(d) * qty;
            }

            function postInline(sku, mipId, column, value, sourceTable) {
                return fetch('/mfrg-progresses/inline-update-by-sku', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify({ sku: sku, mip_id: mipId, column: column, value: value, source_table: sourceTable || '' })
                }).then(function (r) {
                    if (r.status === 419) { throw new Error('Session expired — please refresh the page and try again.'); }
                    if (r.status === 401 || r.redirected) { throw new Error('Not logged in — please refresh the page.'); }
                    return r.text().then(function (txt) {
                        try { return JSON.parse(txt); }
                        catch (e) { throw new Error('Unexpected server response (HTTP ' + r.status + ').'); }
                    });
                });
            }
            function postUpdateLink(sku, column, value) {
                return fetch('/update-link', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify({ sku: sku, row_id: 0, column: column, value: value })
                }).then(function (r) {
                    if (r.status === 419) { throw new Error('Session expired — please refresh the page and try again.'); }
                    if (r.status === 401 || r.redirected) { throw new Error('Not logged in — please refresh the page.'); }
                    return r.text().then(function (txt) {
                        try { return JSON.parse(txt); }
                        catch (e) { throw new Error('Unexpected server response (HTTP ' + r.status + ').'); }
                    });
                });
            }
            function postStage(sku, parent, value) {
                return $.post('/update-forecast-data', { sku: sku, parent: parent || '', column: 'Stage', value: value, _token: CSRF });
            }
            function postForecastData(sku, parent, column, value) {
                return $.post('/update-forecast-data', { sku: sku, parent: parent || '', column: column, value: value, _token: CSRF });
            }

            // ---- formatters ----
            function execFormatter(cell) {
                const row = cell.getRow().getData();
                const val = (cell.getValue() || '').trim();
                const c = EXEC_COLORS[val] || { bg: '#e5e7eb', text: '#6b7280' };
                let opts = '<option value=""' + (val === '' ? ' selected' : '') + '>— Unassigned —</option>';
                EXEC_OPTIONS.forEach(function (n) { opts += '<option value="' + n + '"' + (n === val ? ' selected' : '') + '>' + n + '</option>'; });
                return '<select class="toa-exec-select" data-sku="' + esc(row.sku) + '" style="background:' + c.bg + ';color:' + c.text + ';">' + opts + '</select>';
            }
            function stageFormatter(cell) {
                const d = cell.getRow().getData();
                // Archived rows: show a dedicated red "Archived" stage dot (read-only)
                if (showArchived || d.deleted_at) {
                    return '<div class="mip-stage-dot" title="Archived"><span class="mip-stage-marker"><span class="stage-status-dot" style="background-color:#dc3545;"></span></span></div>';
                }
                const v = (cell.getValue() || '').toLowerCase().trim();
                const color = STAGE_COLORS[v] !== undefined ? STAGE_COLORS[v] : '#94a3b8';
                // Show the human-readable stage name on hover (e.g. "MIP", "Appr. Req",
                // "Transit") instead of leaving the user to decode the color/icon.
                const stageLabel = STAGE_LABELS[v] !== undefined ? STAGE_LABELS[v] : (v || 'Not set');
                const marker = v === 'transit'
                    ? '<i class="fas fa-truck stage-transit-icon"></i>'
                    : '<span class="stage-status-dot" style="background-color:' + color + ';"></span>';
                const mk = function (val, label) { return '<option value="' + val + '"' + (v === val ? ' selected' : '') + '>' + label + '</option>'; };
                return '<div class="mip-stage-dot" title="' + esc(stageLabel) + '"><span class="mip-stage-marker">' + marker + '</span>' +
                    '<select class="stage-stage-select editable-stage" title="' + esc(stageLabel) + '">' +
                    '<option value="">Select</option>' + mk('appr_req', 'Appr. Req') + mk('mip', 'MIP') + mk('r2s', 'R2S') +
                    mk('transit', 'Transit') + mk('all_good', '😊 All Good') + mk('to_order_analysis', '2 Order') +
                    '</select></div>';
            }
            function dotToggleFormatter(column) {
                return function (cell) {
                    const on = String(cell.getValue() || '').toLowerCase() === 'yes';
                    const color = on ? '#22c55e' : '#dc3545';
                    return '<span class="mip-status-dot mip-dot-toggle" data-column="' + column + '" style="background-color:' + color + ';"></span>';
                };
            }
            function commFormatter(cell) {
                const list = cell.getRow().getData().supplier_platform_links || [];
                if (!list.length) return '<span class="text-muted">-</span>';
                let items = '';
                list.forEach(function (p) {
                    const icon = PLAT_ICON[p.label] || 'fas fa-link';
                    const color = PLAT_COLOR[p.label] || '#6b7280';
                    const title = esc(p.label + (p.display ? ': ' + p.display : ''));
                    if (p.url) {
                        const ext = p.external ? ' target="_blank" rel="noopener noreferrer"' : '';
                        items += '<a class="mip-plat-icon-link" href="' + esc(p.url) + '"' + ext + ' title="' + title + '" style="color:' + color + ';font-size:16px;"><i class="' + icon + '"></i></a>';
                    } else {
                        items += '<span class="mip-plat-icon-link" title="' + title + '" style="color:' + color + ';font-size:16px;"><i class="' + icon + '"></i></span>';
                    }
                });
                return '<div class="dropdown d-inline-block"><button class="btn btn-sm btn-light py-0 px-1" type="button" data-bs-toggle="dropdown" style="font-size:11px;" title="Communication">' + list.length + '</button>' +
                    '<ul class="dropdown-menu dropdown-menu-end mip-plat-menu"><li class="d-flex align-items-center gap-2 px-2">' + items + '</li></ul></div>';
            }
            function clStatusColor(d) {
                const st = String(d.pre_mip_checklist_status || '').toLowerCase();
                if (st === 'updated') return '#22c55e';
                if (st === 'escalated') return '#dc3545';
                const met = parseInt(d.pre_mip_checklist_met_count, 10) || 0;
                const tot = parseInt(d.pre_mip_checklist_total_count, 10) || DEFAULT_PRE_MIP_ITEMS.length;
                if (met > 0 && met < tot) return '#f59e0b';
                return '#94a3b8';
            }
            function clFormatter(cell) {
                const d = cell.getRow().getData();
                const color = clStatusColor(d);
                const met = parseInt(d.pre_mip_checklist_met_count, 10) || 0;
                const tot = parseInt(d.pre_mip_checklist_total_count, 10) || DEFAULT_PRE_MIP_ITEMS.length;
                const st = String(d.pre_mip_checklist_status || 'pending');
                const tip = st + ' (' + met + '/' + tot + ')';
                return '<div class="mip-cl-cell">' +
                    '<span class="mip-cl-dot" style="background:' + color + ';" title="' + esc(tip) + '"></span>' +
                    '<button type="button" class="btn btn-link btn-sm p-0 mip-cl-open-btn" title="Pre-MIP checklist">' +
                    '<i class="mdi mdi-magnify" style="font-size:18px;color:#3bc0c3;line-height:1;"></i></button></div>';
            }
            function mergeClItemsFromRow(d) {
                const raw = d && d.pre_mip_checklist_items;
                if (!Array.isArray(raw) || !raw.length) {
                    return DEFAULT_PRE_MIP_ITEMS.map(function (i) { return { id: i.id, label: i.label, checked: false }; });
                }
                return raw.map(function (i) {
                    return { id: String(i.id || ''), label: String(i.label || ''), checked: !!i.checked };
                });
            }
            function cloneClItems(items) {
                return (items || []).map(function (i) {
                    return { id: i.id, label: i.label, checked: !!i.checked };
                });
            }
            function allClItemsChecked(items) {
                if (!items || !items.length) return false;
                return items.every(function (i) { return i.checked; });
            }
            function renderMipClItemsList() {
                const box = document.getElementById('mip-cl-items-list');
                if (!box) return;
                let html = '';
                mipClState.items.forEach(function (item, idx) {
                    const id = 'mip-cl-chk-' + idx;
                    html += '<div class="mip-cl-item-row" data-idx="' + idx + '">' +
                        '<div class="form-check">' +
                        '<input class="form-check-input mip-cl-item-chk" type="checkbox" id="' + id + '" data-idx="' + idx + '"' + (item.checked ? ' checked' : '') + '>' +
                        '<label class="form-check-label" for="' + id + '">' + esc(item.label) + '</label></div>' +
                        '<button type="button" class="btn btn-link btn-sm p-0 mip-cl-remove-item" data-idx="' + idx + '" title="Remove point"><i class="fas fa-times"></i></button></div>';
                });
                box.innerHTML = html || '<p class="text-muted small mb-0">No checklist points — add one below.</p>';
                syncMipClActionButtons();
            }
            function syncMipClActionButtons() {
                const allMet = allClItemsChecked(mipClState.items);
                const updateBtn = document.getElementById('mip-cl-update-btn');
                const escalateBtn = document.getElementById('mip-cl-escalate-btn');
                if (updateBtn) updateBtn.disabled = !allMet;
                if (escalateBtn) escalateBtn.disabled = allMet;
            }
            function openMipClModal(mode, rows) {
                mipClState.mode = mode;
                mipClState.rows = rows || [];
                const titleEl = document.getElementById('mip-cl-modal-title');
                const subEl = document.getElementById('mip-cl-modal-subtitle');
                const noteEl = document.getElementById('mip-cl-escalation-note');
                const statusEl = document.getElementById('mip-cl-status');
                if (statusEl) statusEl.innerHTML = '';
                if (noteEl) noteEl.value = '';

                if (mode === 'bulk') {
                    titleEl.textContent = '';
                    titleEl.innerHTML = '<span class="badge bg-warning text-dark">' + mipClState.rows.length + ' rows</span>';
                    subEl.textContent = 'Apply the same checklist to all ' + mipClState.rows.length + ' selected rows. Add or remove points as needed.';
                    mipClState.items = cloneClItems(DEFAULT_PRE_MIP_ITEMS);
                } else {
                    const d = mipClState.rows[0] ? mipClState.rows[0].getData() : {};
                    titleEl.textContent = d.sku || '';
                    subEl.textContent = 'Item-wise checklist before MIP. All points must be met to Update; otherwise Escalate.';
                    mipClState.items = mergeClItemsFromRow(d);
                    if (d.pre_mip_checklist_escalation_note) {
                        noteEl.value = d.pre_mip_checklist_escalation_note;
                    }
                }
                renderMipClItemsList();
                bootstrap.Modal.getOrCreateInstance(document.getElementById('mipPreMipClModal')).show();
            }
            function patchRowClData(row, payload) {
                if (!row || !payload) return;
                const items = payload.items || mipClState.items;
                row.update({
                    pre_mip_checklist_items: items,
                    pre_mip_checklist_status: payload.status,
                    pre_mip_checklist_escalation_note: payload.escalation_note || null,
                    pre_mip_checklist_met_count: payload.met_count != null ? payload.met_count : items.filter(function (i) { return i.checked; }).length,
                    pre_mip_checklist_total_count: payload.total_count != null ? payload.total_count : items.length,
                });
            }
            function findMipRowByRef(ref) {
                if (!table || !ref) return null;
                return table.getRows().find(function (r) {
                    const d = r.getData();
                    return String(d.source_table || 'mfrg_progress') === String(ref.source_table) &&
                        String(d.id) === String(ref.source_id);
                }) || null;
            }
            async function saveMipClChecklist(action) {
                const statusEl = document.getElementById('mip-cl-status');
                const note = (document.getElementById('mip-cl-escalation-note').value || '').trim();
                const items = mipClState.items.map(function (i) {
                    return { id: i.id, label: i.label, checked: !!i.checked };
                });

                if (action === 'update' && !allClItemsChecked(items)) {
                    if (statusEl) statusEl.innerHTML = '<span class="text-danger">All points must be checked before Update.</span>';
                    return;
                }
                if (action === 'escalate' && allClItemsChecked(items)) {
                    if (statusEl) statusEl.innerHTML = '<span class="text-warning">All points are met — use Update instead.</span>';
                    return;
                }
                if (action === 'escalate' && !note) {
                    if (statusEl) statusEl.innerHTML = '<span class="text-danger">Please enter an escalation note.</span>';
                    return;
                }

                const updateBtn = document.getElementById('mip-cl-update-btn');
                const escalateBtn = document.getElementById('mip-cl-escalate-btn');
                if (updateBtn) updateBtn.disabled = true;
                if (escalateBtn) escalateBtn.disabled = true;
                if (statusEl) statusEl.innerHTML = '<span class="text-muted"><i class="fas fa-spinner fa-spin"></i> Saving…</span>';

                try {
                    let res;
                    if (mipClState.mode === 'bulk') {
                        const rowsPayload = mipClState.rows.map(function (r) {
                            const d = r.getData();
                            return {
                                source_table: d.source_table || 'mfrg_progress',
                                source_id: d.id,
                                sku: d.sku || '',
                            };
                        });
                        res = await fetch('/mfrg-in-progress/pre-mip-checklist/bulk', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                            body: JSON.stringify({ rows: rowsPayload, items: items, action: action, escalation_note: note }),
                        }).then(r => r.json());
                        if (res.success || (res.data && res.data.length)) {
                            (res.data || []).forEach(function (ref) {
                                const row = findMipRowByRef(ref);
                                if (row) patchRowClData(row, {
                                    status: ref.status,
                                    items: items,
                                    met_count: ref.met_count,
                                    total_count: ref.total_count,
                                    escalation_note: action === 'escalate' ? note : null,
                                });
                            });
                        }
                    } else {
                        const row = mipClState.rows[0];
                        const d = row.getData();
                        res = await fetch('/mfrg-in-progress/pre-mip-checklist', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                            body: JSON.stringify({
                                source_table: d.source_table || 'mfrg_progress',
                                source_id: d.id,
                                sku: d.sku || '',
                                items: items,
                                action: action,
                                escalation_note: note,
                            }),
                        }).then(r => r.json());
                        if (res.success && res.data) {
                            patchRowClData(row, res.data);
                        }
                    }

                    if (!res.success) {
                        if (statusEl) statusEl.innerHTML = '<span class="text-danger">' + esc(res.message || 'Save failed.') + '</span>';
                        return;
                    }

                    if (statusEl) statusEl.innerHTML = '<span class="text-success">' + esc(res.message || 'Saved.') + '</span>';
                    setTimeout(function () {
                        bootstrap.Modal.getInstance(document.getElementById('mipPreMipClModal')).hide();
                        if (mipClState.mode === 'bulk') {
                            table.deselectRow();
                            mipBulkSelectionCache = [];
                            updateMipBulkEditBadge();
                        }
                    }, 450);
                } catch (err) {
                    if (statusEl) statusEl.innerHTML = '<span class="text-danger">Network error.</span>';
                } finally {
                    syncMipClActionButtons();
                }
            }
            function inputFormatter(column, type, width) {
                return function (cell) {
                    const v = cell.getValue() == null ? '' : cell.getValue();
                    return '<input type="' + type + '" class="form-control form-control-sm mip-inline-input" data-column="' + column + '" value="' + esc(v) + '" style="width:' + (width || 80) + 'px;text-align:center;">';
                };
            }
            // Display dates as "1 Apr"; empty -> red dot. Editable via Tabulator date editor.
            function dateDisplayFormatter(cell) {
                const raw = cell.getValue();
                if (!raw) return '<span class="mip-status-dot" style="background-color:#dc3545;" title="No date"></span>';
                const d = new Date(raw);
                if (isNaN(d.getTime())) return '<span class="mip-status-dot" style="background-color:#dc3545;" title="No date"></span>';
                const short = d.getDate() + ' ' + d.toLocaleString('en-US', { month: 'short' });
                const full = short + ' ' + d.getFullYear();
                return '<span title="' + full + '">' + short + '</span>';
            }
            function supplierFormatter(cell) {
                // Plain-text display by default — no border, no caret, no "dropdown" affordance.
                // The cell becomes a searchable supplier picker only when clicked (via the
                // `editor: "list"` on this column), matching the click-to-edit pattern used
                // by QTY / O Date / D Date. Previously this rendered a styled <div> with a
                // border + caret on every row, which made every supplier cell look editable
                // from outside before the user even clicked.
                const val = (cell.getValue() || '').trim();
                if (!val) {
                    return '<span class="text-muted" title="Click to set supplier">—</span>';
                }
                return '<span class="mip-supplier-text" title="' + esc(val) + '">' + esc(val) + '</span>';
            }

            table = new Tabulator("#mfrg-table", {
                ajaxURL: "/mfrg-in-progress/data",
                ajaxParams: function () { return { archived: showArchived ? 1 : 0 }; },
                // IMPORTANT — bypass every cache layer between the table and the DB.
                //
                // Without this, two users could see DIFFERENT data after one of them edits a row:
                //   1) Browser HTTP cache treats /mfrg-in-progress/data?archived=0 as a normal GET
                //      and may serve a stale 200 from disk on the next page refresh, because the
                //      backend response carries no Cache-Control header.
                //   2) The PWA service worker (public/sw.js) only skips requests whose Accept
                //      contains "application/json" or that set X-Requested-With: XMLHttpRequest.
                //      Tabulator's built-in fetch sets neither by default, so the SW intercepted
                //      this request and could return a stale response from its cache-first path.
                //
                // Setting the headers explicitly makes the SW bypass us; cache:"no-store" makes
                // the browser & SW fetch go straight to the network every time.
                ajaxConfig: {
                    method: "GET",
                    headers: {
                        "Accept": "application/json",
                        "X-Requested-With": "XMLHttpRequest"
                    },
                    cache: "no-store"
                },
                selectableRows: true,
                // Header checkbox: only toggle rows on the CURRENT page after the active
                // filter — never the whole dataset, and never just the rows that happen
                // to be rendered in the scroll viewport. We can't use
                // `getRows("visible")` because in Tabulator v6 that only returns rows
                // currently rendered (virtual scroll), so on a 50/page list with ~10
                // rows in view, ticking the header only selected those ~10.
                rowHeader: {
                    formatter: "rowSelection",
                    titleFormatter: function (cell, formatterParams, onRendered) {
                        const box = document.createElement('input');
                        box.type = 'checkbox';
                        box.title = 'Select rows on this page only (respects filter + pagination)';
                        box.style.cursor = 'pointer';

                        const syncCheckbox = () => {
                            const pageRows = getCurrentPageActiveRows();
                            if (!pageRows.length) {
                                box.checked = false; box.indeterminate = false; return;
                            }
                            let selectedCount = 0;
                            for (const r of pageRows) if (r.isSelected()) selectedCount++;
                            box.indeterminate = selectedCount > 0 && selectedCount < pageRows.length;
                            box.checked = selectedCount === pageRows.length;
                        };

                        box.addEventListener('click', function (e) {
                            e.stopPropagation();
                            const pageRows = getCurrentPageActiveRows();
                            // Only act on the current page after the active filter.
                            // Selection on OTHER pages is intentionally left untouched so users
                            // can multi-page-select by paging and re-toggling.
                            if (this.checked) pageRows.forEach(r => r.select());
                            else pageRows.forEach(r => r.deselect());
                        });

                        onRendered(syncCheckbox);

                        // Subscribe ONCE across redraws — Tabulator may rebuild the header
                        // and call titleFormatter again, so we guard against piling up
                        // duplicate listeners which would cause stale checkbox state.
                        if (!table._mipHeaderSelectBound) {
                            table._mipHeaderSelectBound = true;
                            ["dataLoaded", "dataFiltered", "dataSorted", "pageLoaded", "rowSelectionChanged", "renderComplete"].forEach(evt => {
                                table.on(evt, () => {
                                    // The `box` captured here can become detached if Tabulator
                                    // re-rendered the header; guard before mutating.
                                    if (box.isConnected) syncCheckbox();
                                });
                            });
                        }

                        return box;
                    },
                    headerSort: false, frozen: true, hozAlign: "center", width: 45
                },
                // Autofit columns to the actual data they hold instead of stretching the
                // fixed `width:` value on each column proportionally across the page —
                // that was making short columns waste space and long columns truncate
                // their content (the "data doesn't fit" look). With fitDataStretch each
                // column sizes itself to its widest cell, then all columns are stretched
                // proportionally to fill the page width.
                layout: "fitDataStretch",
                layoutColumnsOnNewData: true,
                columnDefaults: { minWidth: 60, resizable: true, tooltip: true },
                height: "70vh",
                pagination: true,
                paginationSize: 50,
                paginationSizeSelector: [25, 50, 100, 200],
                paginationCounter: "rows",
                columns: [
                    {
                        title: "#", field: "Image", headerSort: false, cssClass: "mip-new-image-cell", width: 50,
                        formatter: function (cell) {
                            const url = cell.getValue();
                            return url ? '<div class="mip-new-img-aspect"><img src="' + esc(url) + '"></div>' : '<span class="text-muted">N/A</span>';
                        }
                    },
                    { title: "Executive", field: "exec", width: 120, hozAlign: "center",
                      formatter: execFormatter },
                    { title: "Supplier", field: "supplier", width: 140, hozAlign: "center",
                      formatter: supplierFormatter,
                      editor: "list",
                      editorParams: {
                          values: ALL_SUPPLIERS,
                          autocomplete: true,
                          listOnEmpty: true,
                          clearable: true,
                          freetext: false,
                          placeholderEmpty: "No supplier found",
                          maxHeight: 260,
                      },
                      cellEdited: function (cell) {
                          const d = cell.getRow().getData();
                          const v = (cell.getValue() || '').trim();
                          postInline(d.sku || '', d.id || 0, 'supplier', v, d.source_table)
                              .then(r => { if (!r || !r.success) alert((r && r.message) || 'Could not save supplier.'); })
                              .catch(err => { alert('Could not save supplier: ' + (err && err.message ? err.message : err)); });
                      } },
                    { title: "SKU", field: "sku", width: 190,
                      formatter: function (cell) {
                          const v = cell.getValue() || '';
                          if (!v) return '';
                          return '<span class="mip-sku-text">' + esc(v) + '</span>' +
                              '<i class="far fa-copy mip-sku-copy" data-sku="' + esc(v) + '" title="Copy SKU"></i>';
                      } },
                    { title: "QTY", field: "qty", width: 90, hozAlign: "center",
                      // Read-only display by default; click to open a number editor.
                      // Previously rendered an always-open <input> so the value looked editable
                      // straight away (with spinner arrows) — confusing for users who only want
                      // to read, and easy to mis-edit while scrolling.
                      editor: "number", editorParams: { min: 0 },
                      formatter: function (cell) {
                          const v = cell.getValue();
                          return (v == null || v === '') ? '' : esc(String(v));
                      },
                      cellEdited: function (cell) {
                          const d = cell.getRow().getData();
                          postInline(d.sku || '', d.id || 0, 'qty', cell.getValue(), d.source_table)
                              .then(r => {
                                  if (r && r.success) { updateStats(); }
                                  else { alert((r && r.message) || 'Save failed'); }
                              })
                              .catch(err => alert('Could not save QTY: ' + (err && err.message ? err.message : err)));
                      }
                    },
                    { title: "O Date", field: "created_at", width: 90, hozAlign: "center", formatter: dateDisplayFormatter,
                      editor: "date",
                      cellEdited: function (cell) { const d = cell.getRow().getData(); postInline(d.sku || '', d.id || 0, 'created_at', cell.getValue(), d.source_table).then(r => { if (!r || !r.success) alert((r && r.message) || 'Save failed'); }).catch(err => alert('Could not save date: ' + (err && err.message ? err.message : err))); } },
                    { title: "D Date", field: "delivery_date", width: 90, hozAlign: "center", formatter: dateDisplayFormatter,
                      editor: "date",
                      cellEdited: function (cell) { const d = cell.getRow().getData(); postInline(d.sku || '', d.id || 0, 'delivery_date', cell.getValue(), d.source_table).then(r => { if (!r || !r.success) alert((r && r.message) || 'Save failed'); }).catch(err => alert('Could not save date: ' + (err && err.message ? err.message : err))); } },
                    { title: '<i class="fas fa-comments" title="Communication"></i>', field: "supplier_platform_links", width: 56, headerSort: false, formatter: commFormatter },
                    { title: "PO", field: "mip_po_number", width: 80, hozAlign: "center", formatter: function (c) { const v = c.getValue(); return v ? '<span class="badge bg-info">' + esc(v) + '</span>' : '<span class="mip-status-dot" style="background-color:#dc3545;" title="No PO"></span>'; } },
                    { title: "T-CBM", field: "total_cbm", width: 90, hozAlign: "center", formatter: function (cell) {
                        const d = cell.getRow().getData();
                        const cbm = parseFloat(d.CBM) || 0;
                        const qty = parseFloat(d.qty) || 0;
                        const total = cbm * qty;
                        return total > 0 ? total.toFixed(2) : '<span class="text-muted">-</span>';
                    } },
                    { title: "CBM", field: "CBM", width: 80, hozAlign: "center", formatter: function (cell) {
                        const v = parseFloat(cell.getValue());
                        return (!isNaN(v) && v > 0) ? v.toFixed(2) : '<span class="text-muted">-</span>';
                    } },
                    { title: "CP", field: "row_cp", width: 90, hozAlign: "center",
                      sorter: function (a, b, aRow, bRow) { return rowCp(aRow.getData()) - rowCp(bRow.getData()); },
                      formatter: function (cell) {
                          const cp = rowCp(cell.getRow().getData());
                          return cp > 0 ? cp.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '<span class="text-muted">-</span>';
                      } },
                    { title: "Amount", field: "row_amount", width: 100, hozAlign: "center",
                      sorter: function (a, b, aRow, bRow) {
                          const av = rowAmount(aRow.getData()); const bv = rowAmount(bRow.getData());
                          return av - bv;
                      },
                      formatter: function (cell) {
                          const amt = rowAmount(cell.getRow().getData());
                          return amt > 0 ? Math.round(amt).toLocaleString() : '<span class="text-muted">-</span>';
                      } },
                    { title: "Pkg Inst", field: "pkg_inst", width: 80, hozAlign: "center", formatter: dotToggleFormatter('pkg_inst') },
                    { title: "U-Manual", field: "u_manual", width: 90, hozAlign: "center", formatter: dotToggleFormatter('u_manual') },
                    { title: "Compliance", field: "compliance", width: 100, hozAlign: "center", formatter: dotToggleFormatter('compliance') },
                    { title: "CL", field: "pre_mip_checklist_status", width: 64, hozAlign: "center", headerSort: false,
                      formatter: clFormatter,
                      cellClick: function (e, cell) {
                          if (e.target.closest('.mip-cl-open-btn')) {
                              e.stopPropagation();
                              openMipClModal('single', [cell.getRow()]);
                          }
                      } },
                    { title: "Stage", field: "stage", width: 80, hozAlign: "center", formatter: stageFormatter },
                    ...(CAN_EDIT_ALL ? [{
                        title: "Action", field: "row_action", width: 80, hozAlign: "center", headerSort: false,
                        formatter: function () {
                            return '<button type="button" class="btn btn-sm btn-outline-primary mip-action-btn" title="Edit all fields"><i class="fas fa-pen"></i></button>';
                        }
                    }] : []),
                ],
                ajaxResponse: function (url, params, response) {
                    let data = response.data || [];
                    const normSku = function (s) { return String(s == null ? '' : s).trim().toUpperCase(); };
                    // SKUs that already exist as real MIP rows (anything not from ready_to_ship).
                    const mipSkus = new Set(
                        data.filter(i => (i.source_table || '').toString() !== 'ready_to_ship')
                            .map(i => normSku(i.sku))
                            .filter(Boolean)
                    );
                    // Match the old MIP page: skip genuine NR rows (never skip RTS for the NR rule),
                    // and drop the bare Ready-to-Ship duplicate when the same SKU is already a MIP row.
                    let filtered = data.filter(function (item) {
                        const isRts = (item.source_table || '').toString() === 'ready_to_ship';
                        const nr = (item.nr || '').toString().trim().toUpperCase();
                        if (!isRts && nr === 'NR') return false;
                        if (isRts && mipSkus.has(normSku(item.sku))) return false;
                        return true;
                    });
                    uniqueSuppliers = [...new Set(filtered.map(i => i.supplier))].filter(Boolean).sort();
                    return filtered;
                },
            });

            // ---- Column show/hide menu (shared for all users via channel_tabulator_column_settings) ----
            const TABULATOR_COLUMN_CHANNEL = 'mfrg_in_progress';
            const TABULATOR_COLUMN_VISIBILITY_URL = '/tabulator-column-visibility';
            function applyColumnVisibilityFromServer() {
                return fetch(TABULATOR_COLUMN_VISIBILITY_URL + '?channel=' + encodeURIComponent(TABULATOR_COLUMN_CHANNEL), {
                    method: 'GET', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF }
                })
                    .then(r => r.json())
                    .then(function (saved) {
                        if (!saved || typeof saved !== 'object') return;
                        table.getColumns().forEach(function (col) {
                            const field = col.getField();
                            if (!field) return;
                            if (saved[field] === false) col.hide(); else col.show();
                        });
                        table.redraw(true);
                    })
                    .catch(function () {});
            }
            function saveColumnVisibilityToServer() {
                const visibility = {};
                table.getColumns().forEach(function (col) {
                    const field = col.getField();
                    if (field) visibility[field] = col.isVisible();
                });
                fetch(TABULATOR_COLUMN_VISIBILITY_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify({ channel: TABULATOR_COLUMN_CHANNEL, visibility: visibility })
                }).catch(function () {});
            }
            table.on('tableBuilt', applyColumnVisibilityFromServer);

            const colBtn = document.getElementById('mip-columns-btn');
            const colMenu = document.getElementById('mip-columns-menu');
            function columnLabel(col) {
                const def = col.getDefinition() || {};
                const field = col.getField();
                let label = def.title || field || '';
                const tmp = document.createElement('div');
                tmp.innerHTML = label;
                label = (tmp.textContent || tmp.innerText || '').trim();
                return label || field || '(column)';
            }
            function buildColumnsMenu() {
                let rows = '';
                table.getColumns().forEach(function (col) {
                    const field = col.getField();
                    if (!field) return; // skip row-selection / non-data columns
                    const checked = col.isVisible() ? 'checked' : '';
                    rows += '<div class="form-check">' +
                        '<input class="form-check-input mip-col-toggle" type="checkbox" data-field="' + esc(field) + '" id="mipcol-' + esc(field) + '" ' + checked + '>' +
                        '<label class="form-check-label small" for="mipcol-' + esc(field) + '">' + esc(columnLabel(col)) + '</label>' +
                        '</div>';
                });
                colMenu.innerHTML =
                    '<div class="mip-columns-head">' +
                        '<span class="fw-semibold small">Toggle columns</span>' +
                        '<button type="button" class="btn btn-sm btn-link p-0 small" id="mip-columns-all">Show all</button>' +
                    '</div>' + rows;
            }
            colBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                if (colMenu.style.display === 'none' || colMenu.style.display === '') {
                    buildColumnsMenu();
                    colMenu.style.display = 'block';
                } else {
                    colMenu.style.display = 'none';
                }
            });
            colMenu.addEventListener('click', function (e) { e.stopPropagation(); });
            colMenu.addEventListener('change', function (e) {
                const t = e.target;
                if (!t.classList.contains('mip-col-toggle')) return;
                const field = t.dataset.field;
                if (t.checked) table.showColumn(field); else table.hideColumn(field);
                table.redraw(true);
                saveColumnVisibilityToServer();
            });
            colMenu.addEventListener('click', function (e) {
                if (e.target && e.target.id === 'mip-columns-all') {
                    table.getColumns().forEach(function (col) { if (col.getField()) table.showColumn(col.getField()); });
                    table.redraw(true);
                    buildColumnsMenu();
                    saveColumnVisibilityToServer();
                }
            });
            document.addEventListener('click', function (e) {
                if (colMenu.style.display === 'block' && !colMenu.contains(e.target) && e.target !== colBtn) {
                    colMenu.style.display = 'none';
                }
            });

            // Return the rows that are on the CURRENT pagination page after the active
            // filter. We can't use Tabulator's "visible" range here because that returns
            // only rows currently rendered in the scroll viewport (virtual scrolling).
            // Used by the header "select all" checkbox so it scopes selection exactly to
            // what the user sees on this page.
            function getCurrentPageActiveRows() {
                const active = table.getRows("active");
                const page = (typeof table.getPage === 'function') ? table.getPage() : false;
                const size = (typeof table.getPageSize === 'function') ? table.getPageSize() : 0;
                if (!page || !size) return active;
                const start = (page - 1) * size;
                return active.slice(start, start + size);
            }

            // Supplier-play state. When the user activates "▶ Supplier Play" we set
            // `currentSupplierFilter` to the supplier currently being focused on; the
            // filter callback below restricts the table to that supplier's rows only.
            // null means no play filter is active (all suppliers shown). Mirrors the
            // same pattern used by /forecast.analysis page.
            let currentSupplierFilter = null;

            // ---- combined filtering (stage dropdown + global search + supplier play) ----
            function rowPassesMipFilters(row, skipSupplierPlay) {
                const stage = (document.getElementById('mip-stage-filter').value || 'both').toLowerCase();
                const execFilter = (document.getElementById('mip-exec-filter').value || '').trim();
                const search = (document.getElementById('search-input').value || '').trim().toLowerCase();
                const skuSearch = (document.getElementById('mip-sku-search').value || '').trim().toLowerCase();
                const supplierSearch = (document.getElementById('mip-supplier-search').value || '').trim().toLowerCase();

                let keep = true;
                const rs = (row.stage || '').toLowerCase().trim();
                if (stage === 'mip') keep = keep && rs === 'mip';
                else if (stage === 'r2s') keep = keep && rs === 'r2s';
                if (execFilter) {
                    const rowExec = String(row.exec || '').trim();
                    if (execFilter === '__un__') {
                        if (rowExec !== '') keep = false;
                    } else if (rowExec !== execFilter) {
                        keep = false;
                    }
                }
                if (search) keep = keep && Object.values(row).some(v => v && v.toString().toLowerCase().includes(search));
                if (skuSearch) {
                    const rowSku = String(row.sku || '').trim().toLowerCase();
                    if (!rowSku.includes(skuSearch)) keep = false;
                }
                if (supplierSearch) {
                    const rowSupplier = String(row.supplier || '').trim().toLowerCase();
                    if (!rowSupplier.includes(supplierSearch)) keep = false;
                }
                if (!skipSupplierPlay && currentSupplierFilter) {
                    const rowSupplier = String(row.supplier || '').trim();
                    if (rowSupplier !== currentSupplierFilter) keep = false;
                }
                return keep;
            }

            function applyFilters() {
                table.setFilter(function (row) {
                    return rowPassesMipFilters(row, false);
                });
                setTimeout(updateStats, 0);
            }

            function applyToolbarFilters() {
                applyFilters();
                if (!isSupplierPlaying || !currentSupplierFilter) return;
                const list = getMipSupplierList();
                if (!list.length) {
                    stopSupplierPlay();
                    return;
                }
                if (!list.includes(currentSupplierFilter)) {
                    renderMipSupplierGroup(list[0]);
                } else {
                    syncMipSupplierPlayIndex(list, currentSupplierFilter);
                }
            }

            function updateStats() {
                const rows = table.getRows(true).filter(r => r.getElement().offsetParent !== null);
                let amount = 0, cbm = 0;
                const activeData = table.getData("active");
                const items = activeData.length;
                // Track unique, non-empty supplier names (case-insensitive after trim)
                // so the Suppliers badge always reflects the count for whatever rows
                // the user currently sees (after stage/search/supplier-play filters).
                const supplierSet = new Set();
                activeData.forEach(function (d) {
                    const qty = parseFloat(d.qty) || 0;
                    cbm += (parseFloat(d.CBM) || 0) * qty;
                    amount += rowAmount(d);
                    const sup = String(d.supplier || '').trim();
                    if (sup && sup !== '-') supplierSet.add(sup.toLowerCase());
                });
                document.getElementById('totalAmount').textContent = Math.round(amount).toLocaleString();
                document.getElementById('totalCBM').textContent = Math.round(cbm).toLocaleString();
                document.getElementById('totalItems').textContent = items;
                document.getElementById('totalSuppliers').textContent = supplierSet.size;
            }

            table.on("dataLoaded", function () { applyFilters(); updateMfrgArchiveButtons(); });
            table.on("dataFiltered", updateStats);
            document.getElementById('mip-stage-filter').addEventListener('change', applyToolbarFilters);
            document.getElementById('mip-exec-filter').addEventListener('change', applyToolbarFilters);
            document.getElementById('search-input').addEventListener('input', function () { clearTimeout(window._mipS); window._mipS = setTimeout(applyToolbarFilters, 300); });
            document.getElementById('mip-sku-search').addEventListener('input', function () { clearTimeout(window._mipSkuS); window._mipSkuS = setTimeout(applyToolbarFilters, 300); });
            document.getElementById('mip-supplier-search').addEventListener('input', function () { clearTimeout(window._mipSupS); window._mipSupS = setTimeout(applyToolbarFilters, 300); });

            // ---- delegated inline-edit handlers on the table element ----
            const tableEl = document.getElementById('mfrg-table');

            tableEl.addEventListener('change', function (e) {
                const t = e.target;
                const tr = t.closest('.tabulator-row');
                if (!tr) return;
                const row = table.getRow(tr);
                if (!row) return;
                const d = row.getData();
                const sku = d.sku || '';
                const mipId = d.id || 0;

                if (t.classList.contains('toa-exec-select')) {
                    const v = t.value;
                    const prevExec = (d.exec == null) ? null : d.exec;
                    const c = EXEC_COLORS[v] || { bg: '#e5e7eb', text: '#6b7280' };
                    t.style.background = c.bg; t.style.color = c.text;
                    // OPTIMISTIC update: commit the new exec to the row data immediately.
                    //
                    // The old code waited for the server response before calling
                    // row.update({ exec: v }), which left the new value sitting only in
                    // the raw <select> DOM. If Tabulator re-rendered the cell during the
                    // async save (page change, filter, another row update, dataLoaded,
                    // header-checkbox sync, etc.), it regenerated the dropdown from the
                    // OLD row data — so the user saw their just-picked executive snap
                    // back to "Unassigned" intermittently.
                    //
                    // Now we update the row first; on save failure we roll back and
                    // alert. This eliminates the visual race entirely.
                    row.update({ exec: v });
                    postUpdateLink(sku, 'Exec', v || null)
                        .then(r => {
                            if (!r || !r.success) {
                                row.update({ exec: prevExec });
                                alert((r && r.message) || 'Could not save executive.');
                            }
                        })
                        .catch(err => {
                            row.update({ exec: prevExec });
                            alert('Could not save executive: ' + (err && err.message ? err.message : err));
                        });
                } else if (t.classList.contains('editable-stage')) {
                    const v = t.value;
                    postStage(sku, d.parent, v).done(function () {
                        row.update({ stage: v });
                        if (v === 'mip') {
                            fetch('/mfrg-progresses/insert', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                                body: JSON.stringify({ parent: d.parent || '', sku: sku, order_qty: d.qty || '', supplier: d.supplier || '', adv_date: '' }) });
                        }
                        applyFilters();
                    }).fail(function () { alert('Failed to save stage.'); });
                } else if (t.classList.contains('mip-inline-input')) {
                    const col = t.dataset.column;
                    const v = t.value;
                    postInline(sku, mipId, col, v, d.source_table)
                        .then(r => {
                            if (r && r.success) { row.update({ [col === 'created_at' ? 'created_at' : col]: v }); updateStats(); }
                            else { alert((r && r.message) || 'Save failed.'); }
                        })
                        .catch(err => { alert('Could not save: ' + (err && err.message ? err.message : err)); });
                }
            });

            // ── Pencil (Edit) click — DOCUMENT-LEVEL CAPTURE-PHASE handler ────────
            // Tabulator v6's `selectableRows: true` toggles row selection on any
            // click (and in some builds, on `mousedown`). The forecast page preserves
            // checkbox multi-select by caching selection on rowSelectionChanged and
            // snapshotting targets on mousedown before the Edit click collapses selection.
            document.addEventListener('mousedown', function (e) {
                const actBtn = e.target.closest('.mip-action-btn');
                if (!actBtn || !tableEl.contains(actBtn)) return;

                e.stopPropagation();
                e.stopImmediatePropagation();

                const tr = actBtn.closest('.tabulator-row');
                const row = tr ? table.getRow(tr) : null;
                mipRowEditState.pendingBulkTargets = dedupeMipRows([
                    ...(mipBulkSelectionCache || []),
                    ...(table.getSelectedRows() || []),
                    ...(row ? [row] : [])
                ]);
            }, true /* capture */);

            document.addEventListener('click', function (e) {
                const actBtn = e.target.closest('.mip-action-btn');
                if (!actBtn) return;

                if (!tableEl.contains(actBtn)) return;

                e.stopPropagation();
                e.stopImmediatePropagation();
                e.preventDefault();

                const tr = actBtn.closest('.tabulator-row');
                const row = tr ? table.getRow(tr) : null;
                if (!row) return;

                openEditModal(row);
            }, true /* capture */);

            tableEl.addEventListener('click', function (e) {
                const copyIcon = e.target.closest('.mip-sku-copy');
                if (copyIcon) {
                    const sku = copyIcon.dataset.sku || '';
                    const done = function () {
                        copyIcon.classList.remove('far'); copyIcon.classList.add('fas', 'fa-check', 'copied');
                        setTimeout(function () {
                            copyIcon.classList.remove('fas', 'fa-check', 'copied'); copyIcon.classList.add('far');
                        }, 1200);
                    };
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(sku).then(done).catch(function () {});
                    } else {
                        const ta = document.createElement('textarea');
                        ta.value = sku; document.body.appendChild(ta); ta.select();
                        try { document.execCommand('copy'); done(); } catch (err) {}
                        document.body.removeChild(ta);
                    }
                    return;
                }

                const dot = e.target.closest('.mip-dot-toggle');
                if (!dot) return;
                const tr = dot.closest('.tabulator-row');
                const row = table.getRow(tr);
                if (!row) return;
                const d = row.getData();
                const col = dot.dataset.column;
                const next = String(d[col] || '').toLowerCase() === 'yes' ? 'No' : 'Yes';
                dot.style.backgroundColor = next === 'Yes' ? '#22c55e' : '#dc3545';
                row.update({ [col]: next });
                postInline(d.sku || '', d.id || 0, col, next, d.source_table).then(r => { if (!r || !r.success) alert((r && r.message) || 'Save failed'); }).catch(err => alert('Could not save: ' + (err && err.message ? err.message : err)));
            });

            // Communication dropdown: move to body so it isn't clipped
            document.addEventListener('shown.bs.dropdown', function (e) {
                const toggle = e.target.closest('.mip-plat-menu') ? null : e.target;
                const menu = toggle && toggle.parentElement ? toggle.parentElement.querySelector('.mip-plat-menu') : null;
                if (!menu) return;
                if (!menu._home) menu._home = menu.parentElement;
                document.body.appendChild(menu);
                menu.classList.add('show');
                const rect = toggle.getBoundingClientRect();
                menu.style.position = 'fixed';
                menu.style.zIndex = '20000';
                menu.style.top = (rect.bottom + 2) + 'px';
                menu.style.left = Math.max(8, rect.left - 40) + 'px';
            });
            document.addEventListener('hide.bs.dropdown', function (e) {
                const menu = document.querySelector('body > .mip-plat-menu.show');
                if (!menu) return;
                menu.classList.remove('show');
                menu.style = '';
                if (menu._home) menu._home.appendChild(menu);
            });

            // ── Supplier Play / Pause ───────────────────────────────────────────
            // Same pattern as /forecast.analysis "Play by Supplier" control: cycles
            // through the unique supplier names currently in the table, isolating one
            // supplier's rows at a time. Useful when a buyer wants to focus-walk
            // through every supplier's open MIP/R2S items one at a time without
            // typing into the search box for each.
            let isSupplierPlaying = false;
            let supplierPlayIndex = 0;

            function getMipSupplierList() {
                if (!table) return [];
                const seen = new Set();
                const list = [];
                // Build from full loaded data + toolbar filters, but NOT the supplier-play
                // isolation filter — otherwise forward/back only ever see one supplier.
                (table.getData() || []).forEach(function (row) {
                    if (!rowPassesMipFilters(row, true)) return;
                    const s = String(row.supplier || '').trim();
                    if (s && s !== '-' && !seen.has(s)) {
                        seen.add(s);
                        list.push(s);
                    }
                });
                return list.sort((a, b) => a.localeCompare(b));
            }

            function syncMipSupplierPlayIndex(list, supplier) {
                const idx = list.indexOf(supplier);
                supplierPlayIndex = idx >= 0 ? idx : 0;
            }

            function startMipSupplierPlay(list, index) {
                isSupplierPlaying = true;
                supplierPlayIndex = index;
                renderMipSupplierGroup(list[supplierPlayIndex]);
                document.getElementById('mip-supplier-play-pause').style.display = 'inline-block';
                document.getElementById('mip-supplier-play-auto').style.display  = 'none';
            }

            function renderMipSupplierGroup(supplier) {
                currentSupplierFilter = supplier;
                applyFilters();
                const list = getMipSupplierList();
                syncMipSupplierPlayIndex(list, supplier);
                const lbl = document.getElementById('mip-supplier-play-label');
                if (lbl) { lbl.textContent = supplier; lbl.title = supplier; lbl.style.display = 'inline-block'; }
                if (table && table.rowManager && table.rowManager.element) {
                    table.rowManager.element.scrollTop = 0;
                }
            }

            function stopSupplierPlay() {
                isSupplierPlaying = false;
                currentSupplierFilter = null;
                applyFilters();
                document.getElementById('mip-supplier-play-pause').style.display = 'none';
                document.getElementById('mip-supplier-play-auto').style.display  = 'inline-block';
                const lbl = document.getElementById('mip-supplier-play-label');
                if (lbl) lbl.style.display = 'none';
            }

            document.getElementById('mip-supplier-play-auto').addEventListener('click', function () {
                const list = getMipSupplierList();
                if (!list.length) { alert('No supplier data available to play through.'); return; }
                startMipSupplierPlay(list, 0);
            });

            document.getElementById('mip-supplier-play-forward').addEventListener('click', function () {
                const list = getMipSupplierList();
                if (!list.length) return;
                if (!isSupplierPlaying) {
                    startMipSupplierPlay(list, 0);
                    return;
                }
                supplierPlayIndex = (supplierPlayIndex + 1) % list.length;
                renderMipSupplierGroup(list[supplierPlayIndex]);
            });

            document.getElementById('mip-supplier-play-backward').addEventListener('click', function () {
                const list = getMipSupplierList();
                if (!list.length) return;
                if (!isSupplierPlaying) {
                    startMipSupplierPlay(list, list.length - 1);
                    return;
                }
                supplierPlayIndex = (supplierPlayIndex - 1 + list.length) % list.length;
                renderMipSupplierGroup(list[supplierPlayIndex]);
            });

            document.getElementById('mip-supplier-play-pause').addEventListener('click', stopSupplierPlay);

            // Auto-stop play mode if the underlying dataset gets replaced (page reload,
            // Show-archived toggle, etc.) so the badge doesn't lie about its filter.
            table.on('dataLoaded', function () { if (isSupplierPlaying) stopSupplierPlay(); });

            // ---- Archive / Restore ----
            function updateMfrgArchiveButtons() {
                // Archive/Restore is restricted to president@5core.com only.
                if (!CAN_ARCHIVE) {
                    $('#archive-selected-btn').addClass('d-none');
                    $('#restore-selected-btn').addClass('d-none');
                } else {
                    const n = dedupeMipRows(table.getSelectedRows() || []).length;
                    if (showArchived) {
                        $('#archive-selected-btn').addClass('d-none');
                        $('#restore-selected-btn').removeClass('d-none').prop('disabled', n === 0);
                    } else {
                        $('#restore-selected-btn').addClass('d-none');
                        $('#archive-selected-btn').toggleClass('d-none', n === 0);
                    }
                }
                updateMipBulkEditBadge();
            }
            table.on("rowSelectionChanged", function (data, rows) {
                mipBulkSelectionCache = dedupeMipRows(rows || table.getSelectedRows());
                updateMfrgArchiveButtons();
            });

            $('#show-archived-toggle').on('change', function () {
                showArchived = this.checked;
                table.deselectRow();
                mipBulkSelectionCache = [];
                table.replaceData();
            });

            function bulkArchiveRestore(endpoint, confirmMsg) {
                // Only act on rows that are BOTH selected AND currently visible under the active
                // filter — a "select all" header check can otherwise include filtered-out rows.
                const activeSet = new Set(table.getRows("active"));
                const selectedRows = table.getSelectedRows().filter(r => activeSet.has(r));
                // Archive by specific row id + source table so only the selected rows are affected
                // (multiple rows can share the same SKU).
                const items = selectedRows.map(function (r) {
                    const d = r.getData();
                    const source = (d.source_table === 'ready_to_ship') ? 'ready_to_ship' : 'mfrg_progress';
                    return { id: d.id, source: source };
                }).filter(x => x.id);
                if (!items.length) { alert('No rows selected in the current view.'); return; }
                if (!confirm(confirmMsg.replace('{n}', items.length))) return;
                fetch(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify({ items: items }) })
                    .then(r => r.json())
                    .then(r => { if (r.success) { table.deselectRow(); mipBulkSelectionCache = []; table.replaceData(); updateMipBulkEditBadge(); if (r.message) alert(r.message); } else alert(r.message || 'Failed.'); })
                    .catch(() => alert('Network error.'));
            }
            $('#archive-selected-btn').on('click', function () { bulkArchiveRestore('/mfrg-progresses/delete', 'Archive {n} row(s)?'); });
            $('#restore-selected-btn').on('click', function () { bulkArchiveRestore('/mfrg-progresses/restore', 'Restore {n} row(s)?'); });

            // ---- Edit All Fields modal (president@5core.com only) ----
            function toDateInput(raw) {
                if (!raw) return '';
                const d = new Date(raw);
                if (isNaN(d.getTime())) {
                    const m = String(raw).match(/^\d{4}-\d{2}-\d{2}/);
                    return m ? m[0] : '';
                }
                const mm = String(d.getMonth() + 1).padStart(2, '0');
                const dd = String(d.getDate()).padStart(2, '0');
                return d.getFullYear() + '-' + mm + '-' + dd;
            }
            const YESNO = [['Yes', 'Yes'], ['No', 'No']];
            const EDIT_FIELDS = [
                { key: 'sku', label: 'SKU', type: 'text', readonly: true },
                { key: 'exec', label: 'Executive', type: 'select', options: function () { return [['', '— Unassigned —']].concat(EXEC_OPTIONS.map(function (n) { return [n, n]; })); } },
                { key: 'qty', label: 'QTY', type: 'number' },
                { key: 'created_at', label: 'O Date', type: 'date' },
                { key: 'delivery_date', label: 'D Date', type: 'date' },
                { key: 'supplier', label: 'Supplier', type: 'select', options: function () { return [['', '']].concat(ALL_SUPPLIERS.map(function (s) { return [s, s]; })); } },
                { key: 'supplier_sku', label: 'Supplier SKU', type: 'text' },
                { key: 'rate', label: 'Rate (CP)', type: 'number' },
                { key: 'CBM', label: 'CBM', type: 'number', note: 'Saved to product master' },
                { key: 'mip_po_number', label: 'PO Number', type: 'text', readonly: true },
                { key: 'pkg_inst', label: 'Pkg Inst', type: 'select', options: function () { return YESNO; } },
                { key: 'u_manual', label: 'U-Manual', type: 'select', options: function () { return YESNO; } },
                { key: 'compliance', label: 'Compliance', type: 'select', options: function () { return YESNO; } },
                { key: 'ready_to_ship', label: 'Ready To Ship', type: 'select', options: function () { return YESNO; } },
                { key: 'barcode_sku', label: 'Barcode SKU', type: 'text' },
                { key: 'artwork_manual_book', label: 'Artwork Manual Book', type: 'text' },
                { key: 'o_links', label: 'O Links', type: 'text' },
                { key: 'notes', label: 'Notes', type: 'textarea' },
                { key: 'stage', label: 'Stage', type: 'select', options: function () {
                    return [['', 'Select'], ['appr_req', 'Appr. Req'], ['mip', 'MIP'], ['r2s', 'R2S'], ['transit', 'Transit'], ['all_good', 'All Good'], ['to_order_analysis', '2 Order']];
                } },
            ];
            function openEditModal(row) {
                mipRowEditState.row = row;
                const d = row.getData();
                const sku = String(d.sku || '').trim();

                const bulkTargets = (mipRowEditState.pendingBulkTargets && mipRowEditState.pendingBulkTargets.length)
                    ? mipRowEditState.pendingBulkTargets
                    : getMipBulkTargetRows(sku, [row]);
                mipRowEditState.pendingBulkTargets = null;
                mipRowEditState.targetRows = bulkTargets;

                const isBulk = bulkTargets.length > 1;
                const baselineRow = bulkTargets.indexOf(row) !== -1 ? row : bulkTargets[0];
                const baseline = baselineRow.getData();

                const skuEl = document.getElementById('mip-edit-sku');
                const subEl = document.getElementById('mip-edit-subtitle');
                const statusEl = document.getElementById('mip-edit-status');
                if (isBulk) {
                    skuEl.innerHTML = '<span class="badge bg-warning text-dark">' + bulkTargets.length + ' rows selected</span>';
                    subEl.textContent = 'Changes apply to all ' + bulkTargets.length + ' selected rows. Only fields you change will be overwritten on every row.';
                } else {
                    skuEl.textContent = baseline.sku || '';
                    subEl.textContent = sku ? (baseline.parent ? sku + ' · ' + baseline.parent : sku) : '';
                }
                if (statusEl) statusEl.innerHTML = '';

                let html = '';
                EDIT_FIELDS.forEach(function (f) {
                    const id = 'medit-' + f.key;
                    let val = baseline[f.key] == null ? '' : baseline[f.key];
                    let input = '';
                    if (f.type === 'select') {
                        const opts = (f.options ? f.options() : []).map(function (o) {
                            return '<option value="' + esc(o[0]) + '"' + (String(o[0]) === String(val) ? ' selected' : '') + '>' + esc(o[1]) + '</option>';
                        }).join('');
                        input = '<select class="form-select form-select-sm" id="' + id + '" data-key="' + esc(f.key) + '"' + (f.readonly ? ' disabled' : '') + '>' + opts + '</select>';
                    } else if (f.type === 'textarea') {
                        input = '<textarea class="form-control form-control-sm" id="' + id + '" data-key="' + esc(f.key) + '" rows="2"' + (f.readonly ? ' readonly' : '') + '>' + esc(val) + '</textarea>';
                    } else {
                        if (f.type === 'date') val = toDateInput(val);
                        input = '<input type="' + f.type + '" class="form-control form-control-sm" id="' + id + '" data-key="' + esc(f.key) + '" value="' + esc(val) + '"' + (f.readonly ? ' readonly' : '') + '>';
                    }
                    html += '<div class="col-md-4">' +
                        '<label class="form-label small fw-semibold mb-1" for="' + id + '">' + esc(f.label) + '</label>' +
                        input +
                        (f.note ? '<div class="text-muted" style="font-size:0.7rem;">' + esc(f.note) + '</div>' : '') +
                        '</div>';
                });
                document.getElementById('mip-edit-form').innerHTML = html;
                new bootstrap.Modal(document.getElementById('mipEditModal')).show();
            }
            document.getElementById('mip-edit-save').addEventListener('click', async function () {
                const row = mipRowEditState.row;
                if (!row) return;
                const targets = (mipRowEditState.targetRows && mipRowEditState.targetRows.length)
                    ? mipRowEditState.targetRows
                    : [row];
                const isBulk = targets.length > 1;
                const btn = this;
                const originalLabel = btn.innerHTML;
                const statusEl = document.getElementById('mip-edit-status');
                const baselineRow = targets.indexOf(row) !== -1 ? row : targets[0];
                const baseline = baselineRow.getData();

                // Collect the set of changes the user made in the modal. Each entry is
                // { key, newVal, kind } where kind tells us which backend endpoint to call.
                // In bulk mode we deliberately compare each field against the BASELINE row
                // (the row the modal was opened from); any field equal to the baseline is
                // treated as "user didn't touch it" and skipped — i.e. only fields the
                // user explicitly typed/picked get overwritten on the other selected rows.
                const changes = [];
                document.querySelectorAll('#mip-edit-form [data-key]').forEach(function (el) {
                    const key = el.dataset.key;
                    const field = EDIT_FIELDS.find(function (f) { return f.key === key; });
                    if (!field || field.readonly) return;
                    let newVal = el.value;
                    let oldVal = baseline[key] == null ? '' : baseline[key];
                    if (field.type === 'date') oldVal = toDateInput(oldVal);
                    if (String(newVal) === String(oldVal)) return; // unchanged for the baseline row
                    let kind;
                    if (key === 'exec')      kind = 'exec';
                    else if (key === 'stage') kind = 'stage';
                    else if (key === 'CBM')   kind = 'cbm';
                    else                       kind = 'inline';
                    changes.push({ key, newVal, kind });
                });

                if (changes.length === 0) {
                    if (statusEl) statusEl.innerHTML = '<span class="text-muted">No changes to save.</span>';
                    else bootstrap.Modal.getInstance(document.getElementById('mipEditModal')).hide();
                    return;
                }

                btn.disabled = true;
                if (statusEl) statusEl.innerHTML = '<span class="text-muted">Saving ' + changes.length + ' field(s) to ' + targets.length + ' row(s)…</span>';
                let savedCount = 0;
                const failed = [];
                const renderProgress = () => {
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving ' + savedCount + '/' + targets.length + '…';
                };
                if (isBulk) renderProgress();

                // Save each target row in sequence — kept sequential rather than fully
                // parallel so per-row endpoints (postStage, postUpdateLink, postInline)
                // can't trample each other on the SAME row, and so the user sees a
                // monotonically increasing "N/M saved" counter.
                for (const targetRow of targets) {
                    const td = targetRow.getData();
                    const tSku   = td.sku || '';
                    const tMipId = td.id  || 0;

                    // Per-row optimistic update — collapse all the user's changes into
                    // a single row.update so the grid reflects them instantly even if
                    // the network is slow.
                    const optimistic = {};
                    changes.forEach(c => { optimistic[c.key] = c.newVal; });
                    try { targetRow.update(optimistic); targetRow.reformat(); } catch (e) {}

                    try {
                        for (const c of changes) {
                            if (c.kind === 'exec') {
                                await postUpdateLink(tSku, 'Exec', c.newVal || null);
                            } else if (c.kind === 'stage') {
                                await Promise.resolve(postStage(tSku, td.parent, c.newVal));
                            } else if (c.kind === 'cbm') {
                                await Promise.resolve(postForecastData(tSku, td.parent, 'CBM', c.newVal));
                            } else {
                                await postInline(tSku, tMipId, c.key, c.newVal, td.source_table);
                            }
                        }
                    } catch (err) {
                        failed.push({ sku: tSku, error: err && err.message ? err.message : err });
                    }
                    savedCount++;
                    if (isBulk) renderProgress();
                }

                updateStats();

                btn.disabled = false;
                btn.innerHTML = originalLabel;

                if (failed.length) {
                    const failMsg = 'Some changes could not be saved (' + failed.length + ' row(s)). '
                          + 'Affected SKUs: ' + failed.slice(0, 8).map(f => f.sku).join(', ')
                          + (failed.length > 8 ? ', …' : '');
                    if (statusEl) statusEl.innerHTML = '<span class="text-warning">' + esc(failMsg) + '</span>';
                    else alert(failMsg);
                } else {
                    if (statusEl) statusEl.innerHTML = '<span class="text-success">Saved ' + changes.length + ' field(s) on ' + targets.length + ' row(s).</span>';
                    setTimeout(function () {
                        bootstrap.Modal.getInstance(document.getElementById('mipEditModal')).hide();
                        table.deselectRow();
                        mipBulkSelectionCache = [];
                        updateMipBulkEditBadge();
                        if (CAN_ARCHIVE) updateMfrgArchiveButtons();
                    }, 500);
                }
            });

            // ---- Follow-Up / Current Status ----
            function fmtFollowupDate(raw) {
                if (!raw) return '';
                const d = new Date(raw);
                if (isNaN(d.getTime())) return '';
                return d.getDate() + ' ' + d.toLocaleString('en-US', { month: 'short' }) + ' ' + d.getFullYear();
            }
            function renderFollowupHistoryList(list) {
                if (!list.length) return '<p class="text-muted small mb-0 px-2 py-2">No chat history yet.</p>';
                let html = '<div class="list-group list-group-flush">';
                list.forEach(function (it) {
                    html += '<div class="list-group-item py-2">' +
                        '<div class="d-flex justify-content-between"><span class="fw-semibold small">' + esc(it.created_by || 'Unknown') + '</span>' +
                        '<span class="text-muted small">' + fmtFollowupDate(it.created_at) + '</span></div>' +
                        '<div class="small mt-1">' + esc(it.remark || '') + '</div></div>';
                });
                html += '</div>';
                return html;
            }
            function loadFollowupHistory(supplier) {
                const box = document.getElementById('followup-history-list');
                if (!supplier) { box.innerHTML = '<p class="text-muted small mb-0">Select a supplier to view history.</p>'; return; }
                box.innerHTML = '<p class="text-muted small mb-0"><i class="fas fa-spinner fa-spin"></i> Loading...</p>';
                fetch('/purchase-master/follow-up-history/supplier/' + encodeURIComponent(supplier))
                    .then(r => r.json())
                    .then(res => {
                        const list = (res && res.data) ? res.data : [];
                        if (!list.length) { box.innerHTML = '<p class="text-muted small mb-0">No history yet.</p>'; return; }
                        box.innerHTML = renderFollowupHistoryList(list);
                    })
                    .catch(() => { box.innerHTML = '<p class="text-danger small mb-0">Failed to load history.</p>'; });
            }
            function populateFollowupSuppliers() {
                const sel = document.getElementById('followup-supplier-select');
                const cur = sel.value;
                sel.innerHTML = '<option value="">-- Select supplier --</option>';
                uniqueSuppliers.forEach(function (s) {
                    const o = document.createElement('option');
                    o.value = s; o.textContent = s;
                    if (s === cur) o.selected = true;
                    sel.appendChild(o);
                });
            }
            document.getElementById('mip-followup-btn').addEventListener('click', function () {
                populateFollowupSuppliers();
                document.getElementById('followup-remark-input').value = '';
                loadFollowupHistory(document.getElementById('followup-supplier-select').value);
                new bootstrap.Modal(document.getElementById('mipFollowupModal')).show();
            });
            document.getElementById('followup-supplier-select').addEventListener('change', function () {
                loadFollowupHistory(this.value);
            });
            document.getElementById('followup-save-btn').addEventListener('click', function () {
                const supplier = document.getElementById('followup-supplier-select').value;
                const remark = document.getElementById('followup-remark-input').value.trim();
                if (!supplier) { alert('Please select a supplier.'); return; }
                if (!remark) { alert('Please enter the current status.'); return; }
                const btn = this;
                btn.disabled = true;
                fetch('/purchase-master/follow-up-history/store', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify({ supplier_name: supplier, remark: remark })
                })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            document.getElementById('followup-remark-input').value = '';
                            loadFollowupHistory(supplier);
                        } else {
                            alert(res.message || 'Failed to save.');
                        }
                    })
                    .catch(() => alert('Network error.'))
                    .finally(() => { btn.disabled = false; });
            });

            // ---- Supplier Summary (grouped totals + chat history) ----
            let mipSupplierSummaryCsvLines = [];

            function buildMipSupplierGroups() {
                const groups = {};
                if (!table) return groups;
                table.getData('active').forEach(function (d) {
                    const sup = String(d.supplier || '').trim();
                    const key = sup && sup !== '-' ? sup : 'Unknown';
                    if (!groups[key]) {
                        groups[key] = { items: 0, qty: 0, cbm: 0, amount: 0 };
                    }
                    const qty = parseFloat(d.qty) || 0;
                    groups[key].items += 1;
                    groups[key].qty += qty;
                    groups[key].cbm += (parseFloat(d.CBM) || 0) * qty;
                    groups[key].amount += rowAmount(d);
                });
                return groups;
            }

            function fmtMipQty(n) {
                if (!isFinite(n)) return '0';
                if (Math.abs(n - Math.round(n)) < 1e-9) return String(Math.round(n));
                return n.toFixed(2);
            }

            function fmtMipAmount(n) {
                return Math.round(n).toLocaleString();
            }

            function csvEscapeCell(cell) {
                const s = String(cell != null ? cell : '');
                if (/[",\r\n]/.test(s)) return '"' + s.replace(/"/g, '""') + '"';
                return s;
            }

            function renderMipSupplierSummaryTable(groups) {
                const tbody = document.getElementById('mip-supplier-summary-tbody');
                const tfoot = document.getElementById('mip-supplier-summary-tfoot');
                const names = Object.keys(groups).sort((a, b) => a.toLowerCase().localeCompare(b.toLowerCase()));
                tbody.innerHTML = '';
                tfoot.innerHTML = '';
                mipSupplierSummaryCsvLines = [['Supplier', 'Items', 'QTY', 'CBM', 'Amount']];

                if (!names.length) {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-muted text-center py-3">No rows in the current view.</td></tr>';
                    return names;
                }

                let gItems = 0, gQty = 0, gCbm = 0, gAmount = 0;
                names.forEach(function (name) {
                    const g = groups[name];
                    gItems += g.items;
                    gQty += g.qty;
                    gCbm += g.cbm;
                    gAmount += g.amount;
                    const tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + esc(name) + '</td>' +
                        '<td class="text-end">' + g.items + '</td>' +
                        '<td class="text-end">' + fmtMipQty(g.qty) + '</td>' +
                        '<td class="text-end">' + Math.round(g.cbm).toLocaleString() + '</td>' +
                        '<td class="text-end">' + fmtMipAmount(g.amount) + '</td>';
                    tbody.appendChild(tr);
                    mipSupplierSummaryCsvLines.push([name, g.items, fmtMipQty(g.qty), Math.round(g.cbm), Math.round(g.amount)]);
                });

                mipSupplierSummaryCsvLines.push(['Grand Total', gItems, fmtMipQty(gQty), Math.round(gCbm), Math.round(gAmount)]);
                tfoot.innerHTML =
                    '<tr><td>Grand Total</td>' +
                    '<td class="text-end">' + gItems + '</td>' +
                    '<td class="text-end">' + fmtMipQty(gQty) + '</td>' +
                    '<td class="text-end">' + Math.round(gCbm).toLocaleString() + '</td>' +
                    '<td class="text-end">' + fmtMipAmount(gAmount) + '</td></tr>';
                return names;
            }

            function renderMipSupplierSummaryHistory(names, groups, historyMap) {
                const box = document.getElementById('mip-supplier-summary-history');
                if (!names.length) {
                    box.innerHTML = '<p class="text-muted small mb-0">No suppliers in the current view.</p>';
                    return;
                }
                let html = '';
                names.forEach(function (name) {
                    const g = groups[name];
                    const list = historyMap[name] || [];
                    const totals = fmtMipQty(g.qty) + ' QTY · ' + Math.round(g.cbm).toLocaleString() + ' CBM · ' + fmtMipAmount(g.amount) + ' Amount';
                    html += '<div class="mip-sup-history-block mb-3 border rounded">' +
                        '<div class="mip-sup-history-head">' +
                        '<span>' + esc(name) + '</span>' +
                        '<span class="mip-sup-history-totals">' + g.items + ' items · ' + totals +
                        (list.length ? ' · ' + list.length + ' message' + (list.length === 1 ? '' : 's') : '') + '</span>' +
                        '</div>' +
                        renderFollowupHistoryList(list) +
                        '</div>';
                });
                box.innerHTML = html;
            }

            function openMipSupplierSummaryModal() {
                const groups = buildMipSupplierGroups();
                const names = renderMipSupplierSummaryTable(groups);
                const historyBox = document.getElementById('mip-supplier-summary-history');
                historyBox.innerHTML = '<p class="text-muted small mb-0"><i class="fas fa-spinner fa-spin"></i> Loading chat history…</p>';

                if (!names.length) {
                    historyBox.innerHTML = '<p class="text-muted small mb-0">No suppliers in the current view.</p>';
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('mipSupplierSummaryModal')).show();
                    return;
                }

                const historyMap = {};
                Promise.all(names.map(function (name) {
                    return fetch('/purchase-master/follow-up-history/supplier/' + encodeURIComponent(name))
                        .then(r => r.json())
                        .then(res => {
                            historyMap[name] = (res && res.data) ? res.data : [];
                        })
                        .catch(() => { historyMap[name] = []; });
                })).then(function () {
                    renderMipSupplierSummaryHistory(names, groups, historyMap);
                });

                bootstrap.Modal.getOrCreateInstance(document.getElementById('mipSupplierSummaryModal')).show();
            }

            document.getElementById('mip-supplier-summary-btn').addEventListener('click', openMipSupplierSummaryModal);

            document.getElementById('mip-supplier-summary-csv-btn').addEventListener('click', function () {
                if (!mipSupplierSummaryCsvLines.length || mipSupplierSummaryCsvLines.length <= 1) {
                    const groups = buildMipSupplierGroups();
                    renderMipSupplierSummaryTable(groups);
                }
                if (mipSupplierSummaryCsvLines.length <= 1) return;
                const csv = mipSupplierSummaryCsvLines.map(row => row.map(csvEscapeCell).join(',')).join('\r\n');
                const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'mip-supplier-summary-' + new Date().toISOString().slice(0, 10) + '.csv';
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
            });

            // ---- Pre-MIP CL checklist ----
            document.getElementById('mip-cl-items-list').addEventListener('change', function (e) {
                const chk = e.target.closest('.mip-cl-item-chk');
                if (!chk) return;
                const idx = parseInt(chk.dataset.idx, 10);
                if (mipClState.items[idx]) mipClState.items[idx].checked = chk.checked;
                syncMipClActionButtons();
            });
            document.getElementById('mip-cl-items-list').addEventListener('click', function (e) {
                const btn = e.target.closest('.mip-cl-remove-item');
                if (!btn) return;
                const idx = parseInt(btn.dataset.idx, 10);
                if (idx >= 0) {
                    mipClState.items.splice(idx, 1);
                    renderMipClItemsList();
                }
            });
            document.getElementById('mip-cl-add-item-btn').addEventListener('click', function () {
                const input = document.getElementById('mip-cl-new-item');
                const label = (input.value || '').trim();
                if (!label) return;
                const id = 'custom_' + Date.now() + '_' + Math.random().toString(36).slice(2, 7);
                mipClState.items.push({ id: id, label: label, checked: false });
                input.value = '';
                renderMipClItemsList();
            });
            document.getElementById('mip-cl-new-item').addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    document.getElementById('mip-cl-add-item-btn').click();
                }
            });
            document.getElementById('mip-cl-update-btn').addEventListener('click', function () { saveMipClChecklist('update'); });
            document.getElementById('mip-cl-escalate-btn').addEventListener('click', function () { saveMipClChecklist('escalate'); });
            document.getElementById('mip-bulk-cl-btn').addEventListener('click', function () {
                const selected = dedupeMipRows(table.getSelectedRows() || []);
                if (!selected.length) {
                    alert('Select one or more rows with checkboxes first.');
                    return;
                }
                openMipClModal('bulk', selected);
            });
        });
    </script>
@endsection
