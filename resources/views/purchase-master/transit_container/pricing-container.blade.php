@extends('layouts.vertical', ['title' => 'Pricing Container'])
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

  #account-health-master .tabulator,
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
</style>
@endsection

@section('content')
@include('layouts.shared.page-title', ['page_title' => 'Pricing Container', 'sub_title' => 'Pricing Container'])

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
                        @if(!empty($rmbToUsdRate))
                            <span class="badge bg-light text-dark border" title="Live CNY/RMB → USD (Frankfurter)">
                                RMB→USD: {{ number_format((float) $rmbToUsdRate, 4) }}
                            </span>
                        @endif
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

@include('purchase-master.partials.arrived-po-olink-edit')

{{-- Approved Yes/No + Reason --}}
<div class="modal fade" id="pricingApproveModal" tabindex="-1" aria-labelledby="pricingApproveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="pricingApproveModalLabel">CP Approval</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="pricing-approve-row-id" value="">
                <div class="mb-2">
                    <label class="form-label fw-semibold mb-1">SKU</label>
                    <input type="text" id="pricing-approve-sku" class="form-control form-control-sm" readonly>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label fw-semibold mb-1">CP</label>
                        <input type="text" id="pricing-approve-cp" class="form-control form-control-sm" readonly>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold mb-1">CP New</label>
                        <input type="text" id="pricing-approve-cp-new" class="form-control form-control-sm" readonly>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-semibold mb-1">Approved</label>
                    <select id="pricing-approve-value" class="form-select form-select-sm">
                        <option value="">— Select —</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                </div>
                <div class="mb-0">
                    <label class="form-label fw-semibold mb-1">Reason</label>
                    <select id="pricing-approve-reason" class="form-select form-select-sm">
                        <option value="">— Select reason —</option>
                    </select>
                </div>
                <div id="pricing-approve-save-msg" class="small mt-2" style="display:none;"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary" id="pricing-approve-save-btn">
                    <i class="fas fa-save me-1"></i>Save
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
document.body.style.zoom = "90%";
const tabs = @json($tabs);
const groupedData = @json($groupedData);
const approveYesReasons = @json($approveYesReasons ?? []);
const approveNoReasons = @json($approveNoReasons ?? []);
const comparisonSheetPageUrl = @json(route('comparison.sheet.page'));
const purchaseOrdersPageUrl = @json(route('list-all-purchase-orders'));
const pricingSaveApprovalUrl = @json(route('pricing.container.save-approval'));
window.tabTables = window.tabTables || {};
let pricingApproveRowRef = null;

function escHtml(s) {
    return String(s == null ? '' : s)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

function pricingColumns() {
    return [
        {
            title: "Sl No.",
            formatter: function(cell) {
                return cell.getRow().getPosition(true) + 0;
            },
            hozAlign: "center",
            headerSort: false
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
        { title: "Parent", field: "parent" },
        { title: "Sku", field: "our_sku" },
        {
            title: "CD",
            field: "cd_link",
            hozAlign: "center",
            headerSort: false,
            width: 56,
            headerTooltip: "Comparison Data — open comparison page for this SKU",
            formatter: function(cell) {
                const d = cell.getRow().getData() || {};
                const sku = String(d.our_sku || '').trim();
                if (!sku) return '<span style="color:#6c757d;">-</span>';
                const hasSheet = !!d.has_sheet_data;
                const color = hasSheet ? '#16a34a' : '#dc2626';
                const title = hasSheet ? 'View/edit comparison sheet' : 'No comparison data — open to add';
                const safeSku = sku.replace(/&/g, '&amp;').replace(/"/g, '&quot;');
                return `<button type="button" class="pricing-cd-open border-0 bg-transparent p-0" data-sku="${safeSku}" title="${title}" aria-label="${title}" style="line-height:1;cursor:pointer;">
                    <i class="mdi mdi-magnify" style="color:${color};font-size:18px;"></i>
                </button>`;
            }
        },
        {
            title: "Supp.",
            field: "supplier_name",
            headerTooltip: "Supplier (same as Forecast)",
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
            title: "Unit",
            field: "unit",
            headerSort: false,
            headerTooltip: "Unit from CP Master",
            hozAlign: "center",
            formatter: function(cell) {
                const value = String(cell.getValue() || '').trim();
                if (!value) return '—';
                if (value.toLowerCase() === 'pieces') return 'PCs';
                return value;
            }
        },
        {
            title: "CP",
            field: "cp",
            headerSort: true,
            headerTooltip: "CP from CP Master (product master)",
            hozAlign: "center",
            formatter: function(cell) {
                const v = cell.getValue();
                if (v === null || v === undefined || v === '') return '—';
                return escHtml(v);
            }
        },
        {
            title: "Rate ($)",
            field: "rate",
            headerSort: true,
            headerTooltip: "Rate from Arrived Container",
            hozAlign: "center",
            formatter: function(cell) {
                const v = cell.getValue();
                if (v === null || v === undefined || v === '') return '—';
                const n = Number(v);
                if (Number.isNaN(n)) return escHtml(v);
                return escHtml(n.toFixed(2));
            }
        },
        {
            title: "PO Number",
            field: "po_number",
            headerSort: true,
            headerTooltip: "PO Number (from list-all-purchase-orders)",
            hozAlign: "center",
            minWidth: 110,
            formatter: function(cell) {
                const po = String(cell.getValue() || '').trim();
                if (!po) return '—';
                const href = purchaseOrdersPageUrl + '?po=' + encodeURIComponent(po);
                return `<a href="${escHtml(href)}" target="_blank" rel="noopener" title="Open in Purchase Orders">${escHtml(po)}</a>`;
            }
        },
        {
            title: "O link",
            field: "order_link",
            headerSort: false,
            headerTooltip: "Order link",
            hozAlign: "center",
            width: 70,
            formatter: function(cell) {
                const link = String(cell.getValue() || '').trim();
                if (!link) return '—';
                return `<a href="${escHtml(link)}" target="_blank" rel="noopener" title="${escHtml(link)}"><i class="fas fa-external-link-alt" style="color:#2563eb;"></i></a>`;
            }
        },
        {
            title: "CP New",
            field: "cp_new",
            headerSort: true,
            headerTooltip: "PO unit price in USD (RMB converted at live rate). Red if higher than old CP, else green.",
            hozAlign: "center",
            formatter: function(cell) {
                const d = cell.getRow().getData() || {};
                const v = cell.getValue();
                if (v === null || v === undefined || v === '') return '—';
                const cpNew = Number(v);
                const cpOld = (d.cp != null && d.cp !== '') ? Number(d.cp) : NaN;
                let color = '#16a34a'; // green when lower/equal/no old CP
                if (!Number.isNaN(cpNew) && !Number.isNaN(cpOld) && cpNew > cpOld) {
                    color = '#dc2626'; // red when new > old
                }
                const cur = String(d.po_currency || '').toUpperCase();
                let tip = 'USD';
                if (cur === 'RMB' || cur === 'CNY' || cur === 'CNH') {
                    tip = (d.po_price != null ? (d.po_price + ' ' + cur) : cur) + ' → USD';
                } else if (cur) {
                    tip = cur;
                }
                return `<span title="${escHtml(tip)}" style="color:${color};font-weight:700;">${escHtml(cpNew.toFixed(2))}</span>`;
            }
        },
        {
            title: "% Diff",
            field: "cp_diff_pct",
            headerSort: true,
            headerTooltip: "% difference vs previous CP: (CP New − CP) / CP. Red if new > old, else green.",
            hozAlign: "center",
            width: 90,
            formatter: function(cell) {
                const v = cell.getValue();
                if (v === null || v === undefined || v === '') return '—';
                const n = Number(v);
                if (Number.isNaN(n)) return '—';
                const color = n > 0 ? '#dc2626' : '#16a34a';
                const sign = n > 0 ? '+' : '';
                return `<span style="color:${color};font-weight:700;">${sign}${n.toFixed(2)}%</span>`;
            }
        },
        {
            title: "Approved",
            field: "cp_approved",
            headerSort: true,
            headerTooltip: "Yes / No with reason. Auto Yes if CP New < previous CP",
            hozAlign: "center",
            minWidth: 120,
            formatter: function(cell) {
                const d = cell.getRow().getData() || {};
                const approved = String(d.cp_approved || '').trim();
                const reason = String(d.cp_approved_reason || '').trim();
                const isAuto = !!d.cp_approved_auto;
                if (!approved) {
                    return `<button type="button" class="btn btn-sm btn-outline-secondary pricing-approve-btn" title="Set approval">Set</button>`;
                }
                const color = approved === 'Yes' ? '#16a34a' : '#dc2626';
                const autoBadge = isAuto ? ' <span class="badge bg-info text-dark" style="font-size:0.65rem;">Auto</span>' : '';
                return `<button type="button" class="btn btn-sm border-0 bg-transparent pricing-approve-btn p-0" title="${escHtml(reason || approved)}">
                    <span style="color:${color};font-weight:700;">${escHtml(approved)}</span>${autoBadge}
                    ${reason ? `<div style="font-size:0.68rem;color:#64748b;max-width:140px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escHtml(reason)}</div>` : ''}
                </button>`;
            },
            cellClick: function(e, cell) {
                const btn = e.target.closest('.pricing-approve-btn');
                if (!btn) return;
                e.preventDefault();
                e.stopPropagation();
                openPricingApproveModal(cell.getRow());
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

function fillApproveReasons(approved, selectedReason) {
    const sel = document.getElementById('pricing-approve-reason');
    if (!sel) return;
    const list = approved === 'Yes' ? approveYesReasons : (approved === 'No' ? approveNoReasons : []);
    const selected = String(selectedReason || '').trim();
    let html = '<option value="">— Select reason —</option>';
    list.forEach(function(r) {
        const isSel = r === selected ? ' selected' : '';
        html += `<option value="${escHtml(r)}"${isSel}>${escHtml(r)}</option>`;
    });
    sel.innerHTML = html;
}

function openPricingApproveModal(row) {
    pricingApproveRowRef = row;
    const d = row.getData() || {};
    document.getElementById('pricing-approve-row-id').value = d.id || '';
    document.getElementById('pricing-approve-sku').value = d.our_sku || '';
    document.getElementById('pricing-approve-cp').value = (d.cp != null && d.cp !== '') ? d.cp : '—';
    document.getElementById('pricing-approve-cp-new').value = (d.cp_new != null && d.cp_new !== '') ? d.cp_new : '—';
    const approved = String(d.cp_approved || '').trim();
    document.getElementById('pricing-approve-value').value = approved || '';
    fillApproveReasons(approved, d.cp_approved_reason || '');
    const msg = document.getElementById('pricing-approve-save-msg');
    msg.style.display = 'none';
    msg.textContent = '';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('pricingApproveModal')).show();
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
        columns: pricingColumns()
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
        "SKU": row.our_sku,
        "Parent": row.parent,
        "Supplier": row.supplier_name,
        "Unit": row.unit,
        "CP": row.cp,
        "Rate ($)": row.rate,
        "PO Number": row.po_number,
        "O link": row.order_link,
        "CP New": row.cp_new,
        "% Diff": row.cp_diff_pct,
        "Approved": row.cp_approved,
        "Reason": row.cp_approved_reason
    }));
    const worksheet = XLSX.utils.json_to_sheet(exportData);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, "Tab Data");
    XLSX.writeFile(workbook, (data[0]?.tab_name || `tab_${tabIndex + 1}`) + '_pricing.xlsx');
});

document.addEventListener('click', function(e) {
    const btn = e.target.closest('.pricing-cd-open');
    if (!btn) return;
    e.preventDefault();
    const sku = String(btn.getAttribute('data-sku') || '').trim();
    if (!sku) return;
    window.open(comparisonSheetPageUrl + '?sku=' + encodeURIComponent(sku), '_blank', 'noopener');
});

document.getElementById('pricing-approve-value')?.addEventListener('change', function() {
    fillApproveReasons(this.value, '');
});

document.getElementById('pricing-approve-save-btn')?.addEventListener('click', async function() {
    const id = parseInt(document.getElementById('pricing-approve-row-id').value || '0', 10);
    const approved = String(document.getElementById('pricing-approve-value').value || '').trim();
    const reason = String(document.getElementById('pricing-approve-reason').value || '').trim();
    const msg = document.getElementById('pricing-approve-save-msg');
    const btn = this;
    if (!id) {
        msg.style.display = 'block';
        msg.className = 'small mt-2 text-danger';
        msg.textContent = 'Missing row id.';
        return;
    }
    if (!approved || !reason) {
        msg.style.display = 'block';
        msg.className = 'small mt-2 text-danger';
        msg.textContent = 'Select Approved and Reason.';
        return;
    }
    btn.disabled = true;
    msg.style.display = 'none';
    try {
        const res = await fetch(pricingSaveApprovalUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                id: id,
                cp_approved: approved,
                cp_approved_reason: reason
            })
        });
        const json = await res.json();
        if (!res.ok || !json.success) {
            throw new Error(json.message || 'Save failed.');
        }
        if (pricingApproveRowRef) {
            const patch = {
                cp_approved: json.cp_approved || '',
                cp_approved_reason: json.cp_approved_reason || '',
                cp_approved_auto: !!json.cp_approved_auto
            };
            if (json.cp != null && json.cp !== '') {
                patch.cp = json.cp;
            }
            if (json.cp_new != null) {
                patch.cp_new = json.cp_new;
            }
            pricingApproveRowRef.update(patch);
        }
        msg.style.display = 'block';
        msg.className = 'small mt-2 text-success';
        msg.textContent = json.message || 'Saved.';
        setTimeout(function() {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('pricingApproveModal')).hide();
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
