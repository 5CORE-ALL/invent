@extends('layouts.vertical', ['title' => 'MFR Category - Supplier'])

@section('css')
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<style>
    .tabulator { font-size: 13px; border: 1px solid #dee2e6; }
    .tabulator .tabulator-header { background: linear-gradient(90deg, #e0e7ff 0%, #f4f7fa 100%); border-bottom: 2px solid #2563eb; font-weight: 600; }
    .tabulator .tabulator-header .tabulator-col { border-right: 1px solid #e5e7eb; }
    .tabulator-row:hover { background-color: #f8f9fa !important; }
    .tabulator-cell { padding: 8px !important; }
    .mcs-supplier-badge { display: inline-block; margin: 2px 4px 2px 0; }
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
                        <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle fs-6 px-3 py-2">
                            With suppliers: <strong id="mcs-badge-with">0</strong>
                        </span>
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle fs-6 px-3 py-2">
                            Without suppliers: <strong id="mcs-badge-without">0</strong>
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
                <input type="text" id="mcs-add-search" class="form-control mb-2" autocomplete="off"
                    placeholder="Quick search a supplier...">
                <div class="input-group">
                    <select id="mcs-add-select" class="form-select">
                        <option value="">Select supplier...</option>
                    </select>
                    <button type="button" class="btn btn-primary" id="mcs-add-btn" disabled>
                        <i class="mdi mdi-plus me-1"></i> Add
                    </button>
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

<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
(function() {
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const dataUrl = @json(route('mfr.category.supplier.data'));
    const categoryBase = @json(url('/category'));
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

    let table = null;

    function updateBadges() {
        if (!table) return;
        const rows = table.getData();
        const withSuppliers = rows.filter(function(r) { return r.count > 0; }).length;
        document.getElementById('mcs-badge-categories').textContent = rows.length;
        document.getElementById('mcs-badge-with').textContent = withSuppliers;
        document.getElementById('mcs-badge-without').textContent = rows.length - withSuppliers;
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
                headerFilter: 'input',
                headerFilterPlaceholder: 'Search category...',
                sorter: 'string',
                formatter: function(cell) { return '<strong>' + esc(cell.getValue()) + '</strong>'; },
            },
            {
                title: 'Supplier',
                field: 'suppliers_text',
                headerFilter: 'input',
                headerFilterPlaceholder: 'Search supplier...',
                sorter: 'string',
                formatter: function(cell) {
                    const row = cell.getRow().getData();
                    if (!row.suppliers.length) return '<span class="text-muted fst-italic">No suppliers</span>';
                    return row.suppliers.map(function(s) {
                        return '<span class="badge bg-info-subtle text-dark border mcs-supplier-badge">' + esc(s.name) + '</span>';
                    }).join('');
                },
            },
            {
                title: 'Count',
                field: 'count',
                width: 90,
                hozAlign: 'center',
                sorter: 'number',
            },
            {
                title: 'Edit',
                field: 'id',
                width: 90,
                hozAlign: 'center',
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

    function renderAvailable() {
        const q = document.getElementById('mcs-add-search').value.trim().toLowerCase();
        const select = document.getElementById('mcs-add-select');
        const keep = select.value;
        select.innerHTML = '<option value="">Select supplier...</option>';
        available.filter(function(s) { return !q || s.name.toLowerCase().indexOf(q) !== -1; }).forEach(function(s) {
            const option = document.createElement('option');
            option.value = s.id;
            option.textContent = s.name;
            select.appendChild(option);
        });
        select.value = keep;
        document.getElementById('mcs-add-btn').disabled = !select.value;
    }

    function renderModal(payload) {
        document.getElementById('mcs-modal-category').textContent = payload.category.name;
        document.getElementById('mcs-modal-count').textContent = payload.suppliers.length;
        const body = document.getElementById('mcs-modal-body');
        if (!payload.suppliers.length) {
            body.innerHTML = '<tr><td colspan="2" class="text-center text-muted">No suppliers in this category yet.</td></tr>';
        } else {
            body.innerHTML = payload.suppliers.map(function(s) {
                return '<tr><td>' + esc(s.name) + '</td>' +
                    '<td class="text-center"><button type="button" class="btn btn-soft-danger btn-sm mcs-remove-btn" data-id="' + s.id + '" title="Remove from this category"><i class="mdi mdi-delete"></i></button></td></tr>';
            }).join('');
        }
        available = payload.available || [];
        renderAvailable();

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
        document.getElementById('mcs-add-search').value = '';
        document.getElementById('mcs-modal-category').textContent = row.getData().mfr_category;
        document.getElementById('mcs-modal-count').textContent = row.getData().count;
        document.getElementById('mcs-modal-body').innerHTML = '<tr><td colspan="2" class="text-center text-muted">Loading...</td></tr>';
        modal.show();
        load();
    }

    document.getElementById('mcs-add-search').addEventListener('input', renderAvailable);
    document.getElementById('mcs-add-select').addEventListener('change', function() {
        document.getElementById('mcs-add-btn').disabled = !this.value;
    });

    document.getElementById('mcs-add-btn').addEventListener('click', function() {
        const supplierId = document.getElementById('mcs-add-select').value;
        if (!supplierId || !currentRow) return;
        const btn = this;
        btn.disabled = true;
        showError('');
        api('POST', categoryBase + '/' + currentRow.getData().id + '/suppliers', { supplier_id: parseInt(supplierId, 10) })
            .then(function() { document.getElementById('mcs-add-search').value = ''; return load(); })
            .catch(function(e) { showError(e.message); btn.disabled = false; });
    });

    document.getElementById('mcs-modal-body').addEventListener('click', function(e) {
        const btn = e.target.closest('.mcs-remove-btn');
        if (!btn || !currentRow) return;
        const category = currentRow.getData().mfr_category;
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
