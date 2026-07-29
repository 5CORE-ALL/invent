@extends('layouts.vertical', ['title' => 'Inv Verify Container'])
@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<style>
  .tabulator .tabulator-header {
    background: linear-gradient(90deg, #e0e7ff 0%, #f4f7fa 100%);
    border-bottom: 2px solid #2563eb;
    box-shadow: 0 4px 16px rgba(37, 99, 235, 0.10);
  }

  .tabulator .tabulator-header .tabulator-col {
    text-align: center;
    background: transparent;
    border-right: 1px solid #e5e7eb;
    padding: 16px 10px;
    font-weight: 700;
    color: #1e293b;
    font-size: 1.08rem;
    letter-spacing: 0.02em;
  }

  .tabulator .tabulator-header .tabulator-col:hover {
    background: #e0eaff;
    color: #2563eb;
  }

  .tabulator-row {
    background-color: #fff !important;
  }

  .tabulator-row:nth-child(even) {
    background-color: #f8fafc !important;
  }

  .tabulator .tabulator-cell {
    text-align: center;
    padding: 14px 10px;
    border-right: 1px solid #e5e7eb;
    border-bottom: 1px solid #e5e7eb;
    font-size: 1rem;
    color: #000000;
    font-weight: 500;
    vertical-align: middle;
  }

  .tabulator-row:hover {
    background-color: #dbeafe !important;
  }

  .card .tabulator {
    border-radius: 18px;
    box-shadow: 0 6px 24px rgba(37, 99, 235, 0.13);
    overflow: hidden;
    border: 1px solid #e5e7eb;
  }

  .tabulator .tabulator-footer {
    background: #f4f7fa;
    border-top: 1px solid #e5e7eb;
    font-size: 1rem;
    color: #4b5563;
    padding: 5px;
    height: 100px;
  }

  .nav-tabs {
    overflow-x: auto;
    overflow-y: hidden;
    flex-wrap: nowrap;
    white-space: nowrap;
    scrollbar-width: thin;
  }

  .nav-tabs .nav-item {
    flex-shrink: 0;
  }

  #invVerifyCartonModal .carton-row input {
    max-width: 140px;
    margin: 0 auto;
  }

  .inv-disc-status-btn {
    border: 0;
    background: transparent;
    padding: 0;
    line-height: 1;
  }
  .inv-disc-status-btn.is-clickable {
    cursor: pointer;
  }
  .inv-disc-status-btn .fa-exclamation-triangle {
    color: #dc2626;
    font-size: 18px;
  }
  .inv-disc-status-btn .fa-check {
    color: #16a34a;
    font-size: 18px;
  }

  .inv-claims-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 999px;
    background: #fee2e2;
    color: #b91c1c;
    font-size: 0.72rem;
    font-weight: 700;
    text-decoration: none;
    border: 1px solid #fecaca;
    line-height: 1.4;
  }
  .inv-claims-badge:hover {
    background: #fecaca;
    color: #991b1b;
  }

  .inv-action-history-table {
    font-size: 0.78rem;
    margin-bottom: 0.35rem;
  }
  .inv-action-history-table th,
  .inv-action-history-table td {
    padding: 0.25rem 0.4rem;
    vertical-align: middle;
  }
</style>
@endsection

@section('content')
@include('layouts.shared.page-title', ['page_title' => 'Inv Verify Container', 'sub_title' => 'Inv Verify Container'])

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap mb-2 gap-2">
                    <div class="d-flex gap-4 align-items-center flex-wrap">
                        @include('purchase-master.partials.page-info-toolbar', ['pageKey' => 'arrived_container'])
                        <div class="d-flex align-items-center gap-1">
                            <label for="container-quick-search" class="fw-semibold mb-0" style="font-size: 0.95rem;">C #</label>
                            <input type="text" id="container-quick-search" class="form-control form-control-sm" placeholder="No."
                                style="width: 72px; border: 2px solid #2185ff; font-size: 0.95rem;" inputmode="numeric" autocomplete="off">
                        </div>
                    </div>

                    <input type="text" id="search-input" class="form-control form-control-sm" placeholder="Search by SKU, Supplier, Parent..."
                        style="max-width: 220px; border: 2px solid #2185ff; font-size: 0.95rem;">

                    <button id="export-tab-excel" class="btn btn-sm btn-success">
                        <i class="fas fa-file-excel"></i> Export Excel
                    </button>
                </div>

                <div style="overflow-x: auto; overflow-y: hidden; scrollbar-width: none; -ms-overflow-style: none;">
                    <ul class="nav nav-tabs flex-nowrap d-flex mb-0" id="tabList" role="tablist" style="min-width: max-content;">
                        @foreach($tabs as $index => $tab)
                            <li class="nav-item" style="flex-shrink: 0;">
                                <button class="nav-link {{ $index == 0 ? 'active' : '' }}" id="tab-{{ $index }}-tab" data-bs-toggle="tab" data-bs-target="#tab-{{ $index }}" type="button" role="tab" data-tab-name="{{ $tab }}">
                                    {{ preg_replace('/^Container\s+/i', 'C ', $tab) }}
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="tab-content mt-3" id="tabContent">
                    @foreach($tabs as $index => $tab)
                        <div class="tab-pane fade {{ $index === 0 ? 'show active' : '' }}" id="tab-{{ $index }}" role="tabpanel" data-tab-name="{{ $tab }}">
                            <div id="tabulator-{{ $index }}" class="tabulator-table" data-tab-name="{{ $tab }}"></div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<div id="cell-image-preview" style="position:absolute; display:none; z-index:9999; border:1px solid #ccc; background:#fff; padding:5px; border-radius:6px; box-shadow:0 2px 8px rgba(0,0,0,0.2);">
  <img src="" style="max-height:250px; max-width:350px;">
</div>

{{-- Enter cartons + qty per carton --}}
<div class="modal fade" id="invVerifyCartonModal" tabindex="-1" aria-labelledby="invVerifyCartonModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="invVerifyCartonModalLabel">Verify Cartons</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="inv-verify-row-id" value="">
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold mb-1">SKU</label>
                        <input type="text" id="inv-verify-sku" class="form-control form-control-sm" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold mb-1">Parent</label>
                        <input type="text" id="inv-verify-parent" class="form-control form-control-sm" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold mb-1">Expected Qty</label>
                        <input type="text" id="inv-verify-expected-qty" class="form-control form-control-sm" readonly>
                    </div>
                </div>

                <div class="d-flex align-items-end gap-2 flex-wrap mb-3">
                    <div>
                        <label class="form-label fw-semibold mb-1" for="inv-verify-carton-count">Number of Cartons</label>
                        <input type="number" id="inv-verify-carton-count" class="form-control form-control-sm" min="1" max="500" step="1" style="width: 140px;" value="1">
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="inv-verify-build-rows-btn">
                        Build Carton Rows
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="inv-verify-add-row-btn">
                        + Add Carton
                    </button>
                </div>

                <div class="table-responsive" style="max-height: 360px; overflow:auto;">
                    <table class="table table-sm table-bordered align-middle text-center mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th style="width:90px;">Carton #</th>
                                <th>Qty in Carton</th>
                                <th style="width:70px;"></th>
                            </tr>
                        </thead>
                        <tbody id="inv-verify-carton-tbody"></tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                    <div class="fw-semibold">Verified Total Qty: <span id="inv-verify-total-qty">0</span></div>
                    <div id="inv-verify-match-badge" class="small"></div>
                </div>
                <div id="inv-verify-save-msg" class="small mt-2" style="display:none;"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary" id="inv-verify-save-btn">
                    <i class="fas fa-save me-1"></i>Save
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Record qty discrepancy --}}
<div class="modal fade" id="invVerifyDiscModal" tabindex="-1" aria-labelledby="invVerifyDiscModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title text-danger" id="invVerifyDiscModalLabel">
                    <i class="fas fa-exclamation-triangle me-1"></i>Record Discrepancy
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="inv-disc-row-id" value="">
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label fw-semibold mb-1">SKU</label>
                        <input type="text" id="inv-disc-sku" class="form-control form-control-sm" readonly>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold mb-1">Parent</label>
                        <input type="text" id="inv-disc-parent" class="form-control form-control-sm" readonly>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold mb-1">Expected Qty</label>
                        <input type="text" id="inv-disc-expected" class="form-control form-control-sm" readonly>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold mb-1">Verified Qty</label>
                        <input type="text" id="inv-disc-verified" class="form-control form-control-sm text-danger fw-semibold" readonly>
                    </div>
                </div>
                <div class="mb-0">
                    <label class="form-label fw-semibold mb-1">Discrepancy Note <span class="text-danger">*</span></label>
                    <textarea id="inv-disc-note" class="form-control form-control-sm" rows="3" maxlength="500" placeholder="Describe the qty discrepancy..."></textarea>
                    <div class="form-text"><span id="inv-disc-char-count">0</span>/500</div>
                </div>
                <div id="inv-disc-save-msg" class="small mt-2" style="display:none;"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-danger" id="inv-disc-save-btn">
                    <i class="fas fa-save me-1"></i>Save Discrepancy
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Action / Communication History Modal --}}
<div class="modal fade" id="invActionHistoryModal" tabindex="-1" aria-labelledby="invActionHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title" id="invActionHistoryModalLabel">
                    <i class="fas fa-comments me-2"></i>Action / Communication History
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="inv_action_arrived_id">
                <input type="hidden" id="inv_action_sku">
                <input type="hidden" id="inv_action_supplier">
                <div class="mb-2">
                    <label for="inv_action_note_input" class="form-label fw-semibold">Action / Communication</label>
                    <textarea id="inv_action_note_input" class="form-control" rows="3"
                        placeholder="Enter action or communication note..." maxlength="500"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="inv-action-save-btn">
                    <i class="fas fa-plus me-1"></i> Add
                </button>
            </div>
        </div>
    </div>
</div>

@include('purchase-master.partials.arrived-po-olink-edit')
@endsection

@section('script')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
document.body.style.zoom = "90%";
const tabs = @json($tabs);
const groupedData = @json($groupedData);
const invVerifySaveCartonsUrl = @json(route('inv.verify.container.save-cartons'));
const invVerifySaveDiscUrl = @json(route('inv.verify.container.save-discrepancy'));
const invActionSaveUrl = @json(route('inv.verify.container.action'));
const invClaimReimbursementUrl = @json(route('claim.reimbursement'));
window.tabTables = window.tabTables || {};
let invVerifyRowRef = null;
let invDiscRowRef = null;
let invActionRowRef = null;

function escHtml(s) {
    return String(s == null ? '' : s)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

function invVerifyColumns() {
    return [
        {
            title: "Sl No.",
            formatter: function(cell) {
                return cell.getRow().getPosition(true) + 0;
            },
            hozAlign: "center",
            headerSort: false
        },
        { title: "Parent", field: "parent" },
        { title: "Sku", field: "our_sku" },
        {
            title: "Supp.",
            field: "supplier_name",
            headerTooltip: "Supplier (same as Arrived Container / Forecast)",
            width: 72,
            minWidth: 56,
            maxWidth: 96,
            hozAlign: "center",
            formatter: function(cell) {
                const value = String(cell.getValue() == null ? '' : cell.getValue()).trim();
                if (!value) return '-';
                const display = escHtml(value.split(/\s+/).filter(Boolean)[0] || value);
                let color = '#212529';
                if (value.toUpperCase() === 'FIND') {
                    color = '#eab308';
                } else {
                    let h = 0;
                    for (let i = 0; i < value.length; i++) h = (h * 31 + value.charCodeAt(i)) % 360;
                    color = 'hsl(' + h + ', 70%, 40%)';
                }
                return '<span title="' + escHtml(value) + '" style="color:' + color + ';font-weight:700;font-size:0.72rem;white-space:nowrap;">' + display + '</span>';
            }
        },
        {
            title: "Images",
            field: "photos",
            formatter: function(cell) {
                const row = cell.getRow().getData();
                let url = cell.getValue();
                if (!url && row.image_src) url = row.image_src;
                if (!url && row.Values) {
                    try {
                        const values = typeof row.Values === "string" ? JSON.parse(row.Values) : row.Values;
                        if (values.image_path) {
                            url = "/storage/" + values.image_path.replace(/^storage\//, "");
                        }
                    } catch (err) {}
                }
                if (!url) return '<span class="text-muted">No Image</span>';
                return `<img src="${url}" data-preview="${url}" style="height:40px;border-radius:4px;border:1px solid #ccc;cursor:zoom-in;">`;
            }
        },
        {
            title: "Verify",
            field: "inv_verify",
            headerSort: false,
            headerTooltip: "Enter number of cartons and qty in each carton",
            hozAlign: "center",
            width: 70,
            formatter: function(cell) {
                const d = cell.getRow().getData() || {};
                const has = !!d.has_inv_verify;
                const color = has ? '#16a34a' : '#dc2626';
                const count = Number(d.inv_verify_carton_count || 0);
                const total = Number(d.inv_verify_total_qty || 0);
                const title = has
                    ? ('Verified: ' + count + ' carton(s), total qty ' + total)
                    : 'Enter cartons / qty per carton';
                return `<button type="button" class="inv-verify-open border-0 bg-transparent p-0" title="${escHtml(title)}" aria-label="${escHtml(title)}" style="line-height:1;cursor:pointer;">
                    <i class="mdi mdi-magnify" style="color:${color};font-size:18px;"></i>
                </button>`;
            },
            cellClick: function(e, cell) {
                const btn = e.target.closest('.inv-verify-open');
                if (!btn) return;
                e.preventDefault();
                e.stopPropagation();
                openInvVerifyCartonModal(cell.getRow());
            }
        },
        {
            title: "Match",
            field: "inv_verify_qty_match",
            headerSort: true,
            headerTooltip: "Green tick if verified carton qty matches expected. Red alert if not — click to record discrepancy.",
            hozAlign: "center",
            width: 72,
            formatter: function(cell) {
                const d = cell.getRow().getData() || {};
                if (!d.has_inv_verify) {
                    return '<span style="color:#94a3b8;">—</span>';
                }
                if (d.inv_verify_qty_match) {
                    return `<span class="inv-disc-status-btn" title="Carton qty matches expected">
                        <i class="fas fa-check"></i>
                    </span>`;
                }
                const note = String(d.inv_verify_discrepancy || '').trim();
                const tip = note
                    ? ('Discrepancy recorded: ' + note)
                    : 'Qty mismatch — click to record discrepancy';
                return `<button type="button" class="inv-disc-status-btn is-clickable inv-disc-open" title="${escHtml(tip)}" aria-label="Record discrepancy">
                    <i class="fas fa-exclamation-triangle"></i>
                </button>`;
            },
            cellClick: function(e, cell) {
                const btn = e.target.closest('.inv-disc-open');
                if (!btn) return;
                e.preventDefault();
                e.stopPropagation();
                openInvVerifyDiscModal(cell.getRow());
            }
        },
        {
            title: "O link",
            headerTooltip: "Order link",
            field: "order_link",
            headerSort: false,
            hozAlign: "center",
            width: 70,
            formatter: function(cell) {
                const url = String(cell.getValue() || '').trim();
                if (!url) return '—';
                return `<a href="${escHtml(url)}" target="_blank" rel="noopener" title="${escHtml(url)}"><i class="fas fa-external-link-alt" style="color:#2563eb;"></i></a>`;
            }
        },
        { title: "Qty / Ctns", field: "no_of_units" },
        { title: "Qty Ctns", field: "total_ctn" },
        {
            title: "Qty",
            field: "pcs_qty",
            formatter: function(cell) {
                const data = cell.getRow().getData();
                const units = parseFloat(data.no_of_units) || 0;
                const ctn = parseFloat(data.total_ctn) || 0;
                return units * ctn;
            }
        },
        {
            title: "Action / Communication History",
            field: "action_history",
            hozAlign: "left",
            headerSort: false,
            minWidth: 280,
            widthGrow: 2,
            variableHeight: true,
            formatter: function(cell) {
                const d = cell.getRow().getData() || {};
                return buildInvActionHistoryInline(cell.getValue(), d.our_sku || '', d.supplier_name || '');
            },
            cellClick: function(e, cell) {
                if (!e.target.closest('.inv-add-action-btn')) return;
                const d = cell.getRow().getData() || {};
                openInvAddActionModal(d.id, d.our_sku || '', d.supplier_name || '', cell.getRow());
            }
        },
        (typeof window.arrivedPoOlinkActionsColumn === 'function'
            ? window.arrivedPoOlinkActionsColumn({ width: 70 })
            : {
                title: "Actions",
                headerSort: false,
                hozAlign: "center",
                width: 70,
                formatter: function() { return '—'; }
            })
    ];
}

function buildInvClaimAddUrl(sku, supplier) {
    const url = new URL(invClaimReimbursementUrl, window.location.origin);
    url.searchParams.set('open_add', '1');
    if (sku) url.searchParams.set('sku', String(sku).trim());
    if (supplier) url.searchParams.set('supplier', String(supplier).trim());
    return url.toString();
}

function buildInvActionHistoryInline(historyData, sku, supplier) {
    let history = historyData || [];
    if (typeof history === 'string') {
        try { history = JSON.parse(history) || []; } catch (e) { history = []; }
    }
    if (!Array.isArray(history)) history = [];

    const claimsUrl = buildInvClaimAddUrl(sku, supplier);
    let html = '<div class="d-flex align-items-center gap-2 mb-1 flex-wrap">'
        + '<a href="' + escHtml(claimsUrl) + '" target="_blank" rel="noopener" class="inv-claims-badge" title="Open Add Claim / Reimbursement">Claims</a>'
        + '</div>';

    if (history.length > 0) {
        html += '<table class="table table-sm table-bordered inv-action-history-table mb-1"><thead class="table-light"><tr>'
            + '<th>Action</th><th>Note</th><th>User</th><th>Date</th></tr></thead><tbody>';
        history.forEach(function(entry) {
            html += '<tr>'
                + '<td><span class="badge bg-info-subtle text-dark">' + escHtml(entry.action || '-') + '</span></td>'
                + '<td class="text-start">' + escHtml(entry.note || '-') + '</td>'
                + '<td>' + escHtml(entry.user || '-') + '</td>'
                + '<td title="' + escHtml(entry.datetime || entry.date || '') + '">' + escHtml(entry.date || '-') + '</td>'
                + '</tr>';
        });
        html += '</tbody></table>';
    } else {
        html += '<span class="text-muted small">No history</span><br>';
    }
    html += '<button type="button" class="btn btn-sm btn-outline-primary inv-add-action-btn">'
        + '<i class="fas fa-plus me-1"></i> Add Action</button>';
    return html;
}

function openInvAddActionModal(arrivedId, sku, supplier, rowRef) {
    if (!arrivedId) {
        alert('Row must be saved before adding action history.');
        return;
    }
    invActionRowRef = rowRef || null;
    document.getElementById('inv_action_arrived_id').value = arrivedId;
    document.getElementById('inv_action_sku').value = sku || '';
    document.getElementById('inv_action_supplier').value = supplier || '';
    document.getElementById('inv_action_note_input').value = '';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('invActionHistoryModal')).show();
}

function openInvVerifyDiscModal(row) {
    invDiscRowRef = row;
    const d = row.getData() || {};
    const expected = (d.inv_verify_expected_qty != null)
        ? Number(d.inv_verify_expected_qty)
        : ((parseFloat(d.no_of_units) || 0) * (parseFloat(d.total_ctn) || 0));
    const verified = Number(d.inv_verify_total_qty || 0);
    document.getElementById('inv-disc-row-id').value = d.id || '';
    document.getElementById('inv-disc-sku').value = d.our_sku || '';
    document.getElementById('inv-disc-parent').value = d.parent || '';
    document.getElementById('inv-disc-expected').value = expected;
    document.getElementById('inv-disc-verified').value = verified;
    const noteEl = document.getElementById('inv-disc-note');
    noteEl.value = d.inv_verify_discrepancy || '';
    document.getElementById('inv-disc-char-count').textContent = String(noteEl.value.length);
    const msg = document.getElementById('inv-disc-save-msg');
    msg.style.display = 'none';
    msg.textContent = '';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('invVerifyDiscModal')).show();
}

function collectExistingCartonQtys() {
    return Array.from(document.querySelectorAll('#inv-verify-carton-tbody .inv-carton-qty')).map(function(inp) {
        const v = String(inp.value || '').trim();
        return v === '' ? '' : v;
    });
}

function renderCartonRows(count, existingQtys) {
    const tbody = document.getElementById('inv-verify-carton-tbody');
    if (!tbody) return;
    const n = Math.max(0, Math.min(500, parseInt(count, 10) || 0));
    const prev = Array.isArray(existingQtys) ? existingQtys : [];
    let html = '';
    for (let i = 0; i < n; i++) {
        const qty = prev[i] != null ? prev[i] : '';
        html += `<tr class="carton-row">
            <td class="fw-semibold">${i + 1}</td>
            <td><input type="number" class="form-control form-control-sm inv-carton-qty" min="0" step="any" value="${escHtml(qty)}" placeholder="Qty"></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger inv-carton-remove" title="Remove">&times;</button></td>
        </tr>`;
    }
    tbody.innerHTML = html || '<tr><td colspan="3" class="text-muted">Set number of cartons and click Build Carton Rows.</td></tr>';
    document.getElementById('inv-verify-carton-count').value = n > 0 ? n : '';
    updateInvVerifyTotals();
}

function updateInvVerifyTotals() {
    const inputs = document.querySelectorAll('#inv-verify-carton-tbody .inv-carton-qty');
    let total = 0;
    inputs.forEach(function(inp) {
        const n = parseFloat(inp.value);
        if (!Number.isNaN(n)) total += n;
    });
    document.getElementById('inv-verify-total-qty').textContent = String(Math.round(total * 100) / 100);
    const expected = parseFloat(document.getElementById('inv-verify-expected-qty').value) || 0;
    const badge = document.getElementById('inv-verify-match-badge');
    if (!inputs.length) {
        badge.innerHTML = '';
        return;
    }
    if (expected > 0 && Math.abs(total - expected) < 0.0001) {
        badge.innerHTML = '<span class="badge bg-success">Matches expected qty</span>';
    } else if (expected > 0) {
        badge.innerHTML = '<span class="badge bg-danger">Differs from expected qty</span>';
    } else {
        badge.innerHTML = '';
    }
}

function openInvVerifyCartonModal(row) {
    invVerifyRowRef = row;
    const d = row.getData() || {};
    const expected = (parseFloat(d.no_of_units) || 0) * (parseFloat(d.total_ctn) || 0);
    document.getElementById('inv-verify-row-id').value = d.id || '';
    document.getElementById('inv-verify-sku').value = d.our_sku || '';
    document.getElementById('inv-verify-parent').value = d.parent || '';
    document.getElementById('inv-verify-expected-qty').value = expected;
    const msg = document.getElementById('inv-verify-save-msg');
    msg.style.display = 'none';
    msg.textContent = '';

    const cartons = Array.isArray(d.inv_verify_cartons) ? d.inv_verify_cartons : [];
    const qtys = cartons.map(function(c) { return c && c.qty != null ? c.qty : ''; });
    const defaultCount = qtys.length > 0
        ? qtys.length
        : Math.max(1, parseInt(d.total_ctn, 10) || 1);
    const defaultQtyPer = parseFloat(d.no_of_units);
    const filled = qtys.length
        ? qtys
        : Array.from({ length: defaultCount }, function() {
            return Number.isNaN(defaultQtyPer) ? '' : defaultQtyPer;
        });
    renderCartonRows(defaultCount, filled);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('invVerifyCartonModal')).show();
}

tabs.forEach(function(tabName, index) {
    const data = groupedData[tabName] || [];
    const table = new Tabulator(`#tabulator-${index}`, {
        layout: "fitDataFill",
        data: data,
        pagination: "local",
        paginationSize: 50,
        height: "700px",
        rowHeight: 55,
        index: "id",
        columns: invVerifyColumns()
    });
    window.tabTables[index] = table;
});

document.addEventListener("DOMContentLoaded", function() {
    document.documentElement.setAttribute("data-sidenav-size", "condensed");
});

function getContainerNumberFromTabName(tabName) {
    const match = String(tabName || '').match(/(\d+)/);
    return match ? match[1] : '';
}

function applyContainerQuickSearch(query) {
    const q = String(query || '').trim();
    const tabButtons = Array.from(document.querySelectorAll('#tabList [data-bs-toggle="tab"]'));
    let matchBtn = null;
    tabButtons.forEach(function(btn) {
        const num = getContainerNumberFromTabName(btn.dataset.tabName);
        const navItem = btn.closest('.nav-item');
        const visible = !q || num === q || num.startsWith(q);
        if (navItem) navItem.style.display = visible ? '' : 'none';
        if (visible && !matchBtn) matchBtn = btn;
        if (q && num === q) matchBtn = btn;
    });
    if (matchBtn && q) {
        bootstrap.Tab.getOrCreateInstance(matchBtn).show();
        matchBtn.scrollIntoView({ inline: 'nearest', behavior: 'smooth', block: 'nearest' });
    }
}

const containerQuickSearch = document.getElementById('container-quick-search');
if (containerQuickSearch) {
    containerQuickSearch.addEventListener('input', function() {
        applyContainerQuickSearch(this.value);
    });
}

document.getElementById('search-input')?.addEventListener('input', function() {
    const value = this.value.toLowerCase();
    const activeTab = document.querySelector('.nav-link.active[data-bs-toggle="tab"]');
    if (!activeTab) return;
    const activeIndex = Array.from(document.querySelectorAll('[data-bs-toggle="tab"]')).indexOf(activeTab);
    const activeTable = window.tabTables[activeIndex];
    if (!activeTable) return;
    activeTable.setFilter([
        [
            { field: "our_sku", type: "like", value: value },
            { field: "supplier_name", type: "like", value: value },
            { field: "parent", type: "like", value: value }
        ]
    ]);
});

document.getElementById('export-tab-excel')?.addEventListener('click', function() {
    const activeTabPane = document.querySelector('.tab-pane.active');
    if (!activeTabPane) return;
    const tabIndex = Array.from(activeTabPane.parentElement.children).indexOf(activeTabPane);
    const table = window.tabTables[tabIndex];
    if (!table) return;
    const data = table.getData().filter(row => row.parent || row.our_sku);
    if (!data.length) {
        alert('No data to export for this tab.');
        return;
    }
    const exportData = data.map(row => ({
        "Parent": row.parent,
        "SKU": row.our_sku,
        "Supplier": row.supplier_name,
        "O link": row.order_link || "",
        "Qty / Ctns": row.no_of_units,
        "Qty Ctns": row.total_ctn,
        "Qty": (parseFloat(row.no_of_units) || 0) * (parseFloat(row.total_ctn) || 0)
    }));
    const worksheet = XLSX.utils.json_to_sheet(exportData);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, "Tab Data");
    XLSX.writeFile(workbook, (data[0]?.tab_name || `tab_${tabIndex + 1}`) + '_inv_verify.xlsx');
});

document.getElementById('inv-verify-build-rows-btn')?.addEventListener('click', function() {
    const count = document.getElementById('inv-verify-carton-count').value;
    const prev = collectExistingCartonQtys();
    renderCartonRows(count, prev);
});

document.getElementById('inv-verify-add-row-btn')?.addEventListener('click', function() {
    const prev = collectExistingCartonQtys();
    prev.push('');
    renderCartonRows(prev.length, prev);
});

document.getElementById('inv-verify-carton-tbody')?.addEventListener('input', function(e) {
    if (e.target && e.target.classList.contains('inv-carton-qty')) {
        updateInvVerifyTotals();
    }
});

document.getElementById('inv-verify-carton-tbody')?.addEventListener('click', function(e) {
    const btn = e.target.closest('.inv-carton-remove');
    if (!btn) return;
    const tr = btn.closest('tr');
    if (tr) tr.remove();
    const prev = collectExistingCartonQtys();
    renderCartonRows(prev.length, prev);
});

document.getElementById('inv-verify-save-btn')?.addEventListener('click', async function() {
    const id = parseInt(document.getElementById('inv-verify-row-id').value || '0', 10);
    const msg = document.getElementById('inv-verify-save-msg');
    const btn = this;
    if (!id) {
        msg.style.display = 'block';
        msg.className = 'small mt-2 text-danger';
        msg.textContent = 'Missing row id.';
        return;
    }
    const cartons = [];
    let invalid = false;
    document.querySelectorAll('#inv-verify-carton-tbody .inv-carton-qty').forEach(function(inp) {
        const raw = String(inp.value || '').trim();
        if (raw === '') return;
        const n = parseFloat(raw);
        if (Number.isNaN(n) || n < 0) {
            invalid = true;
            return;
        }
        cartons.push({ qty: n });
    });
    if (invalid) {
        msg.style.display = 'block';
        msg.className = 'small mt-2 text-danger';
        msg.textContent = 'Each carton qty must be a non-negative number.';
        return;
    }
    if (!cartons.length) {
        msg.style.display = 'block';
        msg.className = 'small mt-2 text-danger';
        msg.textContent = 'Enter qty for at least one carton.';
        return;
    }
    btn.disabled = true;
    msg.style.display = 'none';
    try {
        const res = await fetch(invVerifySaveCartonsUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ id: id, cartons: cartons })
        });
        const json = await res.json();
        if (!res.ok || !json.success) {
            throw new Error(json.message || 'Save failed.');
        }
        if (invVerifyRowRef) {
            invVerifyRowRef.update({
                inv_verify_cartons: json.inv_verify_cartons || [],
                inv_verify_carton_count: json.inv_verify_carton_count || 0,
                inv_verify_total_qty: json.inv_verify_total_qty || 0,
                inv_verify_expected_qty: json.inv_verify_expected_qty,
                inv_verify_qty_match: !!json.inv_verify_qty_match,
                has_inv_verify: !!json.has_inv_verify,
                inv_verify_discrepancy: json.inv_verify_discrepancy || null
            });
        }
        msg.style.display = 'block';
        msg.className = 'small mt-2 text-success';
        msg.textContent = json.message || 'Saved.';
        const needsDisc = !!json.needs_discrepancy;
        const rowForDisc = invVerifyRowRef;
        setTimeout(function() {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('invVerifyCartonModal')).hide();
            if (needsDisc && rowForDisc) {
                setTimeout(function() { openInvVerifyDiscModal(rowForDisc); }, 250);
            }
        }, 400);
    } catch (err) {
        msg.style.display = 'block';
        msg.className = 'small mt-2 text-danger';
        msg.textContent = err.message || 'Save failed.';
    } finally {
        btn.disabled = false;
    }
});

document.getElementById('inv-action-save-btn')?.addEventListener('click', function() {
    const arrivedId = document.getElementById('inv_action_arrived_id').value;
    const note = String(document.getElementById('inv_action_note_input').value || '').trim();
    if (!note) {
        document.getElementById('inv_action_note_input').focus();
        return;
    }
    const btn = this;
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    fetch(invActionSaveUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            arrived_id: arrivedId,
            sku: document.getElementById('inv_action_sku').value || '',
            supplier_name: document.getElementById('inv_action_supplier').value || '',
            note: note
        })
    })
    .then(function(r) { return r.json().then(function(j) { return { ok: r.ok, j }; }); })
    .then(function(res) {
        if (!res.ok || !res.j.success) {
            alert((res.j && res.j.message) || 'Failed to save action.');
            return;
        }
        if (invActionRowRef) {
            try { invActionRowRef.update({ action_history: res.j.action_history || [] }); } catch (_) {}
        }
        bootstrap.Modal.getOrCreateInstance(document.getElementById('invActionHistoryModal')).hide();
    })
    .catch(function() {
        alert('Something went wrong while saving action.');
    })
    .finally(function() {
        btn.disabled = false;
        btn.innerHTML = original;
    });
});

document.getElementById('inv-disc-note')?.addEventListener('input', function() {
    document.getElementById('inv-disc-char-count').textContent = String(this.value.length);
});

document.getElementById('inv-disc-save-btn')?.addEventListener('click', async function() {
    const id = parseInt(document.getElementById('inv-disc-row-id').value || '0', 10);
    const note = String(document.getElementById('inv-disc-note').value || '').trim();
    const msg = document.getElementById('inv-disc-save-msg');
    const btn = this;
    if (!id) {
        msg.style.display = 'block';
        msg.className = 'small mt-2 text-danger';
        msg.textContent = 'Missing row id.';
        return;
    }
    if (!note) {
        msg.style.display = 'block';
        msg.className = 'small mt-2 text-danger';
        msg.textContent = 'Discrepancy note is required.';
        return;
    }
    btn.disabled = true;
    msg.style.display = 'none';
    try {
        const res = await fetch(invVerifySaveDiscUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ id: id, discrepancy: note })
        });
        const json = await res.json();
        if (!res.ok || !json.success) {
            throw new Error(json.message || 'Save failed.');
        }
        if (invDiscRowRef) {
            invDiscRowRef.update({
                inv_verify_discrepancy: json.inv_verify_discrepancy || note,
                inv_verify_qty_match: false,
                has_inv_verify: true
            });
        }
        msg.style.display = 'block';
        msg.className = 'small mt-2 text-success';
        msg.textContent = json.message || 'Saved.';
        setTimeout(function() {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('invVerifyDiscModal')).hide();
        }, 400);
    } catch (err) {
        msg.style.display = 'block';
        msg.className = 'small mt-2 text-danger';
        msg.textContent = err.message || 'Save failed.';
    } finally {
        btn.disabled = false;
    }
});

document.addEventListener('mouseover', function(e) {
    if (e.target && e.target.dataset.preview) {
        const previewBox = document.getElementById('cell-image-preview');
        previewBox.querySelector('img').src = e.target.dataset.preview;
        const rect = e.target.getBoundingClientRect();
        previewBox.style.left = (rect.right + 10) + 'px';
        previewBox.style.top = rect.top + 'px';
        previewBox.style.display = 'block';
    }
});
document.addEventListener('mouseout', function(e) {
    if (e.target && e.target.dataset.preview) {
        document.getElementById('cell-image-preview').style.display = 'none';
    }
});
</script>
@endsection
