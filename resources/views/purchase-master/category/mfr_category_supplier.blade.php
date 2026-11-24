@extends('layouts.vertical', ['title' => 'MFR Category - Supplier'])

@section('css')
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<style>
    .tabulator { font-size: 13px; border: 1px solid #dee2e6; }
    .tabulator .tabulator-header { background: linear-gradient(90deg, #e0e7ff 0%, #f4f7fa 100%); border-bottom: 2px solid #2563eb; font-weight: 600; }
    .tabulator .tabulator-header .tabulator-col { border-right: 1px solid #e5e7eb; }
    .tabulator-row:hover { background-color: #f8f9fa !important; }
    .tabulator-cell { padding: 8px !important; }
    .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title { text-align: center; }
    .tabulator .tabulator-cell { text-align: center; }
    .mcs-supplier-badge { display: inline-block; margin: 2px 4px 2px 0; cursor: pointer; }
    .mcs-supplier-badge:hover { background-color: #cfe2ff !important; }
    #mcsSupplierModal dt { font-weight: 600; color: #6c757d; }
    #mcsSupplierModal dd { margin-bottom: .6rem; word-break: break-word; }
</style>
@endsection

@section('content')
<div class="container-fluid mt-3">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="mdi mdi-factory me-1"></i> MFR Category - Supplier</h4>
                    <a href="{{ route('supplier.list') }}" class="btn btn-light btn-sm" target="_blank" rel="noopener">
                        <i class="mdi mdi-open-in-new me-1"></i> Supplier list
                    </a>
                </div>
                <div class="card-body">
                    <div class="mb-3 d-flex gap-2 flex-wrap">
                        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle fs-6 px-3 py-2">
                            Categories: <strong id="mcs-badge-categories">0</strong>
                        </span>
                        <span class="badge border fs-6 px-3 py-2" id="mcs-low-wrap">
                            Under 13 suppliers: <strong id="mcs-badge-low">0</strong>
                        </span>
                    </div>
                    <div id="mcs-table"></div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Edit modal: suppliers of one category --}}
<div class="modal fade" id="mcsModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg shadow-none">
        <div class="modal-content border-0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="mdi mdi-account-group me-1"></i>
                    Suppliers - <span id="mcs-modal-category"></span>
                    <span class="badge bg-light text-dark ms-2" id="mcs-modal-count">0</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <table class="table table-bordered table-sm align-middle mb-3">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th style="width: 90px;" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="mcs-modal-body">
                        <tr><td colspan="2" class="text-center text-muted">Loading...</td></tr>
                    </tbody>
                </table>

                <label class="form-label fw-semibold" for="mcs-add-search">Add a supplier (from the supplier list)</label>
                <div class="input-group position-relative">
                    <input type="text" id="mcs-add-search" class="form-control" autocomplete="off"
                        placeholder="Search and select a supplier...">
                    <button type="button" class="btn btn-primary" id="mcs-add-btn" disabled>
                        <i class="mdi mdi-plus me-1"></i> Add
                    </button>
                    <div id="mcs-add-list" class="list-group position-absolute w-100 shadow"
                        style="top:100%;left:0;z-index:1060;max-height:220px;overflow-y:auto;display:none;"></div>
                </div>
                <div class="form-text">Only suppliers that are not already in this category are listed.</div>
                <div class="text-danger small mt-2" id="mcs-modal-error" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- Supplier details modal: opened by clicking a supplier name --}}
<div class="modal fade" id="mcsSupplierModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg shadow-none">
        <div class="modal-content border-0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="mdi mdi-account-details me-1"></i> Supplier - <span id="mcs-sup-title"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="mcs-sup-body"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
(function() {
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const dataUrl = @json(route('mfr.category.supplier.data'));
    const categoryBase = @json(url('/category'));
    const supplierDetailsBase = @json(url('/mfr-category-supplier/supplier'));
    const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));

    function api(method, url, body) {
        const options = {
            method: method,
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        };
        if (body) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(body);
        }
        return fetch(url, options).then(function(res) {
            return res.json().catch(function() { return {}; }).then(function(json) {
                if (!res.ok || json.success === false) {
                    throw new Error(json.message || 'Request failed (' + res.status + ').');
                }
                return json;
            });
        });
    }

    // Category names are shown in sentence case ("CAR SPK" -> "Car spk"); the saved name is not changed.
    const toSentenceCase = function(s) {
        const t = String(s == null ? '' : s).trim().toLowerCase();
        return t.charAt(0).toUpperCase() + t.slice(1);
    };

    // A category with fewer suppliers than this is shown in red.
    const LOW_SUPPLIER_COUNT = 13;
    let table = null;

    function updateBadges() {
        if (!table) return;
        const rows = table.getData();
        document.getElementById('mcs-badge-categories').textContent = rows.length;
        const low = rows.filter(function(r) { return r.count < LOW_SUPPLIER_COUNT; }).length;
        document.getElementById('mcs-badge-low').textContent = low;
        // red background while there is any category under the limit, quiet otherwise
        const wrap = document.getElementById('mcs-low-wrap');
        wrap.classList.toggle('bg-danger', low > 0);
        wrap.classList.toggle('text-white', low > 0);
        wrap.classList.toggle('border-danger', low > 0);
        wrap.classList.toggle('bg-light', low === 0);
        wrap.classList.toggle('text-dark', low === 0);
        if (typeof window.setMfrLowBadge === 'function') window.setMfrLowBadge(low);
    }

    table = new Tabulator('#mcs-table', {
        ajaxURL: dataUrl,
        layout: 'fitColumns',
        height: '70vh',
        placeholder: 'No categories found',
        initialSort: [{ column: 'mfr_category', dir: 'asc' }],
        columns: [
            {
                title: 'MFR Category',
                field: 'mfr_category',
                width: 260,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerFilter: 'input',
                headerFilterPlaceholder: 'Search category...',
                sorter: 'string',
                formatter: function(cell) { return '<strong>' + esc(toSentenceCase(cell.getValue())) + '</strong>'; },
            },
            {
                title: 'Count',
                field: 'count',
                width: 90,
                hozAlign: 'center',
                headerHozAlign: 'center',
                sorter: 'number',
                formatter: function(cell) {
                    const n = Number(cell.getValue()) || 0;
                    return n < LOW_SUPPLIER_COUNT ? '<span class="text-danger fw-bold">' + n + '</span>' : String(n);
                },
            },
            {
                title: 'Supplier',
                field: 'suppliers_text',
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerFilter: 'input',
                headerFilterPlaceholder: 'Search supplier...',
                sorter: 'string',
                formatter: function(cell) {
                    const row = cell.getRow().getData();
                    if (!row.suppliers.length) return '<span class="text-muted fst-italic">No suppliers</span>';
                    return row.suppliers.map(function(s) {
                        return '<span class="badge bg-info-subtle text-dark border mcs-supplier-badge" data-supplier-id="' + s.id + '" title="Click to see supplier details">' + esc(toNameCase(s.name)) + '</span>';
                    }).join('');
                },
            },
            {
                title: 'Edit',
                field: 'id',
                width: 90,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: false,
                frozen: true,
                formatter: function() {
                    return '<button type="button" class="btn btn-soft-primary btn-sm mcs-edit-btn" title="Add / delete suppliers"><i class="mdi mdi-square-edit-outline"></i></button>';
                },
                cellClick: function(e, cell) {
                    if (!e.target.closest('.mcs-edit-btn')) return;
                    openModal(cell.getRow());
                },
            },
        ],
    });
    table.on('tableBuilt', updateBadges);
    table.on('dataLoaded', updateBadges);

    // ---- supplier details modal ----
    const supplierModalEl = document.getElementById('mcsSupplierModal');
    const supplierLabels = [
        ['name', 'Name'], ['company', 'Company'], ['alias', 'Alias'], ['type', 'Type'], ['approval_status', 'Approval'],
        ['phone', 'Phone'], ['whatsapp', 'WhatsApp'], ['wechat', 'WeChat'], ['email', 'Email'], ['qq', 'QQ'],
        ['alibaba', 'Alibaba'], ['link_1688', '1688 link'], ['city', 'City'], ['zone', 'Zone'], ['address', 'Address'],
        ['sku', 'SKU'], ['parent', 'Parent'], ['next_followup', 'Next follow-up'], ['bank_details', 'Bank details'], ['others', 'Others'],
    ];
    const hiddenFields = ['id', 'created_at', 'updated_at', 'deleted_at'];

    function detailValue(key, value) {
        const text = String(value);
        if (/^https?:\/\//i.test(text)) return '<a href="' + esc(text) + '" target="_blank" rel="noopener noreferrer">' + esc(text) + '</a>';
        if (key === 'email') return '<a href="mailto:' + esc(text) + '">' + esc(text) + '</a>';
        if (key === 'approval_status') {
            const colors = { green: 'success', yellow: 'warning', red: 'danger' };
            return '<span class="badge bg-' + (colors[text.toLowerCase()] || 'secondary') + '">' + esc(text) + '</span>';
        }
        return esc(text).replace(/\n/g, '<br>');
    }

    function openSupplierModal(id, name) {
        const body = document.getElementById('mcs-sup-body');
        document.getElementById('mcs-sup-title').textContent = name || '';
        body.innerHTML = '<div class="text-center text-muted py-4">Loading...</div>';
        bootstrap.Modal.getOrCreateInstance(supplierModalEl).show();
        api('GET', supplierDetailsBase + '/' + id).then(function(res) {
            const d = res.supplier || {};
            const shown = {};
            const items = [];
            supplierLabels.forEach(function(pair) {
                shown[pair[0]] = true;
                if (d[pair[0]] !== null && d[pair[0]] !== undefined && String(d[pair[0]]).trim() !== '') items.push([pair[1], detailValue(pair[0], d[pair[0]])]);
            });
            Object.keys(d).forEach(function(key) {
                if (shown[key] || hiddenFields.indexOf(key) !== -1) return;
                if (d[key] === null || String(d[key]).trim() === '') return;
                const label = key.replace(/_/g, ' ').replace(/^./, function(c) { return c.toUpperCase(); });
                items.push([label, detailValue(key, d[key])]);
            });
            if (res.categories && res.categories.length) {
                items.push(['MFR categories', res.categories.map(function(c) { return '<span class="badge bg-primary-subtle text-primary-emphasis border me-1">' + esc(c) + '</span>'; }).join('')]);
            }
            if (res.latest_remark) items.push(['Latest remark', esc(res.latest_remark).replace(/\n/g, '<br>')]);
            if (!items.length) { body.innerHTML = '<div class="text-center text-muted py-4">No details saved for this supplier.</div>'; return; }
            body.innerHTML = '<dl class="row mb-0">' + items.map(function(it) {
                return '<dt class="col-sm-4">' + esc(it[0]) + '</dt><dd class="col-sm-8">' + it[1] + '</dd>';
            }).join('') + '</dl>';
        }).catch(function(e) {
            body.innerHTML = '<div class="text-danger text-center py-4">' + esc(e.message) + '</div>';
        });
    }

    document.getElementById('mcs-table').addEventListener('click', function(e) {
        const badge = e.target.closest('.mcs-supplier-badge');
        if (badge) openSupplierModal(badge.getAttribute('data-supplier-id'), badge.textContent.trim());
    });

    // ---- modal ----
    const modalEl = document.getElementById('mcsModal');
    // Bootstrap is loaded after this script (footer), so the modal is created when first needed.
    const modal = { show: function() { bootstrap.Modal.getOrCreateInstance(modalEl).show(); } };
    let currentRow = null;
    let available = [];

    function showError(message) {
        const el = document.getElementById('mcs-modal-error');
        el.textContent = message || '';
        el.style.display = message ? '' : 'none';
    }

    // One box: type to filter the suppliers that are not in this category yet, click one to pick it.
    let chosenSupplier = null;

    function renderAvailable(openList) {
        const input = document.getElementById('mcs-add-search');
        const list = document.getElementById('mcs-add-list');
        const q = input.value.trim().toLowerCase();
        if (chosenSupplier && input.value !== chosenSupplier.name) chosenSupplier = null;
        document.getElementById('mcs-add-btn').disabled = !chosenSupplier;
        if (openList === false) { list.style.display = 'none'; return; }
        const hits = available.filter(function(s) { return !q || s.name.toLowerCase().indexOf(q) !== -1; });
        list.innerHTML = hits.length ? hits.map(function(s) {
            return '<button type="button" class="list-group-item list-group-item-action py-2 mcs-add-item" data-id="' + s.id + '">' + esc(s.name) + '</button>';
        }).join('') : '<div class="list-group-item text-muted small">No suppliers found</div>';
        list.style.display = '';
    }

    function renderModal(payload) {
        document.getElementById('mcs-modal-category').textContent = toSentenceCase(payload.category.name);
        document.getElementById('mcs-modal-count').textContent = payload.suppliers.length;
        const body = document.getElementById('mcs-modal-body');
        if (!payload.suppliers.length) {
            body.innerHTML = '<tr><td colspan="2" class="text-center text-muted">No suppliers in this category yet.</td></tr>';
        } else {
            body.innerHTML = payload.suppliers.map(function(s) {
                return '<tr><td><a href="#" class="mcs-supplier-link text-decoration-none" data-supplier-id="' + s.id + '">' + esc(s.name) + '</a></td>' +
                    '<td class="text-center"><button type="button" class="btn btn-soft-danger btn-sm mcs-remove-btn" data-id="' + s.id + '" title="Remove from this category"><i class="mdi mdi-delete"></i></button></td></tr>';
            }).join('');
        }
        available = payload.available || [];
        renderAvailable(false);

        // keep the table row in step with the modal
        if (currentRow) {
            const names = payload.suppliers.map(function(s) { return s.name || ('Supplier #' + s.id); });
            currentRow.update({
                suppliers: payload.suppliers.map(function(s) { return { id: s.id, name: s.name || ('Supplier #' + s.id) }; }),
                suppliers_text: names.join(', '),
                count: payload.suppliers.length,
            });
            currentRow.reformat();
            updateBadges();
        }
    }

    function load() {
        const id = currentRow.getData().id;
        return api('GET', categoryBase + '/' + id + '/suppliers').then(renderModal).catch(function(e) { showError(e.message); });
    }

    function openModal(row) {
        currentRow = row;
        showError('');
        chosenSupplier = null;
        document.getElementById('mcs-add-search').value = '';
        document.getElementById('mcs-add-list').style.display = 'none';
        document.getElementById('mcs-modal-category').textContent = toSentenceCase(row.getData().mfr_category);
        document.getElementById('mcs-modal-count').textContent = row.getData().count;
        document.getElementById('mcs-modal-body').innerHTML = '<tr><td colspan="2" class="text-center text-muted">Loading...</td></tr>';
        modal.show();
        load();
    }

    const addSearch = document.getElementById('mcs-add-search');
    addSearch.addEventListener('input', function() { renderAvailable(true); });
    addSearch.addEventListener('focus', function() { renderAvailable(true); });
    addSearch.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') renderAvailable(false);
        if (e.key === 'Enter') {
            e.preventDefault();
            const first = document.querySelector('#mcs-add-list .mcs-add-item');
            if (!chosenSupplier && first) first.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
            else if (chosenSupplier) document.getElementById('mcs-add-btn').click();
        }
    });
    document.getElementById('mcs-add-list').addEventListener('mousedown', function(e) {
        const item = e.target.closest('.mcs-add-item');
        if (!item) return;
        e.preventDefault();
        const picked = available.find(function(s) { return String(s.id) === item.dataset.id; });
        if (!picked) return;
        chosenSupplier = picked;
        addSearch.value = picked.name;
        renderAvailable(false);
    });
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#mcs-add-search') && !e.target.closest('#mcs-add-list')) renderAvailable(false);
    });

    document.getElementById('mcs-add-btn').addEventListener('click', function() {
        if (!chosenSupplier || !currentRow) return;
        const supplierId = chosenSupplier.id;
        const btn = this;
        btn.disabled = true;
        showError('');
        api('POST', categoryBase + '/' + currentRow.getData().id + '/suppliers', { supplier_id: parseInt(supplierId, 10) })
            .then(function() { chosenSupplier = null; addSearch.value = ''; return load(); })
            .catch(function(e) { showError(e.message); btn.disabled = false; });
    });

    document.getElementById('mcs-modal-body').addEventListener('click', function(e) {
        const link = e.target.closest('.mcs-supplier-link');
        if (link) { e.preventDefault(); openSupplierModal(link.getAttribute('data-supplier-id'), link.textContent.trim()); return; }
        const btn = e.target.closest('.mcs-remove-btn');
        if (!btn || !currentRow) return;
        const category = toSentenceCase(currentRow.getData().mfr_category);
        if (!confirm('Remove this supplier from "' + category + '"? The supplier stays in the supplier list.')) return;
        btn.disabled = true;
        showError('');
        api('DELETE', categoryBase + '/' + currentRow.getData().id + '/suppliers/' + btn.dataset.id)
            .then(load)
            .catch(function(err) { showError(err.message); btn.disabled = false; });
    });
})();
</script>
@endsection
