@extends('layouts.vertical', ['title' => 'Bullet Points Master', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

    <style>
        .table-responsive {
            position: relative;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            max-height: 600px;
            overflow-x: auto;
            overflow-y: auto;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            background-color: white;
        }
        .table-responsive thead th {
            position: sticky;
            top: 0;
            background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%) !important;
            color: white;
            z-index: 10;
            padding: 8px 10px;
            font-weight: 600;
            font-size: 11px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .table-responsive thead input, .table-responsive thead select {
            background-color: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 4px;
            padding: 4px 6px;
            margin-top: 4px;
            font-size: 10px;
            width: 100%;
        }
        .table-responsive tbody td {
            padding: 8px 10px;
            vertical-align: middle;
            font-size: 12px;
            color: #495057;
        }
        .table-responsive tbody tr:nth-child(even) { background-color: #f8fafc; }
        .table-responsive tbody tr:hover { background-color: #e8f0fe; }
        .bullet-text {
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .mp-cell {
            max-width: 120px;
            min-width: 100px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            cursor: pointer;
            padding: 6px 8px;
            font-size: 11px;
        }
        .mp-cell:hover {
            background-color: #e8f0fe;
            overflow: visible;
            white-space: normal;
            word-wrap: break-word;
            z-index: 5;
        }
        .action-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            font-size: 11px;
            cursor: pointer;
            transition: all 0.2s;
            margin: 0 2px;
        }
        .edit-btn {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            color: white;
        }
        .edit-btn:hover { box-shadow: 0 2px 8px rgba(255, 193, 7, 0.3); }
        .modal-header-gradient {
            background: linear-gradient(135deg, #6B73FF 0%, #000DFF 100%);
            color: white;
        }
        .char-counter { font-size: 11px; color: #6c757d; }
        .char-counter.error { color: #dc3545; font-weight: 600; }
        .char-counter.warning { color: #b8860b; }
        .btn-update-selected {
            background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%);
            color: white;
        }
        .mp-col-header { font-size: 10px; }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('layouts.shared/page-title', [
        'page_title' => 'Bullet Points Master',
        'sub_title' => 'Manage Product Bullet Points',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
                        <button id="addBulletBtn" class="btn btn-success btn-sm"><i class="fas fa-plus"></i> Add Bullet Points</button>
                        <button id="exportBtn" class="btn btn-primary btn-sm"><i class="fas fa-download"></i> Export</button>
                        <button id="importBtn" class="btn btn-info btn-sm"><i class="fas fa-upload"></i> Import</button>
                        <input type="file" id="importFile" accept=".csv,.xlsx,.xls" style="display: none;">
                        <button id="updateSelectedBtn" class="btn btn-update-selected btn-sm" disabled>
                            <i class="fas fa-cloud-upload-alt"></i> Update Selected (<span id="selectedCount">0</span>)
                        </button>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Select marketplace group</button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" data-group="150">All 150-char (eBay1-3, Walmart, Macy, AliExpress, Faire, BestBuy)</a></li>
                                <li><a class="dropdown-item" href="#" data-group="100">Wayfair (100)</a></li>
                                <li><a class="dropdown-item" href="#" data-group="80">Shein (80)</a></li>
                                <li><a class="dropdown-item" href="#" data-group="60">DOBA (60)</a></li>
                            </ul>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table id="bullet-master-table" class="table table-sm">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="selectAll" title="Select All"></th>
                                    <th>Images</th>
                                    <th>
                                        <span>Parent</span><span id="parentCount">(0)</span>
                                        <input type="text" id="parentSearch" class="form-control-sm" placeholder="Search Parent">
                                    </th>
                                    <th>
                                        <span>SKU</span><span id="skuCount">(0)</span>
                                        <input type="text" id="skuSearch" class="form-control-sm" placeholder="Search SKU">
                                    </th>
                                    <th>Bullet 1 <span id="bullet1MissingCount" class="text-danger">(0)</span>
                                        <select id="filterBullet1" class="form-control form-control-sm mt-1" style="font-size: 10px;">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                        </select>
                                    </th>
                                    <th>Bullet 2 <span id="bullet2MissingCount" class="text-danger">(0)</span>
                                        <select id="filterBullet2" class="form-control form-control-sm mt-1" style="font-size: 10px;">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                        </select>
                                    </th>
                                    <th>Bullet 3 <span id="bullet3MissingCount" class="text-danger">(0)</span>
                                        <select id="filterBullet3" class="form-control form-control-sm mt-1" style="font-size: 10px;">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                        </select>
                                    </th>
                                    <th>Bullet 4 <span id="bullet4MissingCount" class="text-danger">(0)</span>
                                        <select id="filterBullet4" class="form-control form-control-sm mt-1" style="font-size: 10px;">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                        </select>
                                    </th>
                                    <th>Bullet 5 <span id="bullet5MissingCount" class="text-danger">(0)</span>
                                        <select id="filterBullet5" class="form-control form-control-sm mt-1" style="font-size: 10px;">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                        </select>
                                    </th>
                                    <th>Action</th>
                                    <th class="mp-col-header">150: eBay1</th>
                                    <th class="mp-col-header">eBay2</th>
                                    <th class="mp-col-header">eBay3</th>
                                    <th class="mp-col-header">Walmart</th>
                                    <th class="mp-col-header">Macy</th>
                                    <th class="mp-col-header">AliExp</th>
                                    <th class="mp-col-header">Faire</th>
                                    <th class="mp-col-header">BestBuy</th>
                                    <th class="mp-col-header">100: Wayfair</th>
                                    <th class="mp-col-header">80: Shein</th>
                                    <th class="mp-col-header">60: DOBA</th>
                                </tr>
                            </thead>
                            <tbody id="table-body"></tbody>
                        </table>
                    </div>

                    <div id="rainbow-loader" class="text-center py-4" style="display: none;">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2">Loading Bullet Points Data...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Bullet Points (1-5) Modal -->
    <div class="modal fade" id="bulletModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title"><i class="fas fa-list me-2"></i><span id="modalTitle">Add Bullet Points</span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="bulletForm">
                        <input type="hidden" id="editSku" name="sku">
                        <div class="mb-3">
                            <label for="selectSku" class="form-label">Select SKU <span class="text-danger">*</span></label>
                            <select class="form-select" id="selectSku" name="sku" required>
                                <option value="">Choose SKU...</option>
                            </select>
                        </div>
                        @for($i = 1; $i <= 5; $i++)
                        <div class="mb-3">
                            <label for="bullet{{ $i }}" class="form-label">Bullet {{ $i }} <span class="char-counter" id="counter{{ $i }}">0/200</span></label>
                            <textarea class="form-control" id="bullet{{ $i }}" name="bullet{{ $i }}" rows="2" maxlength="200"></textarea>
                        </div>
                        @endfor
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveBulletBtn"><i class="fas fa-save"></i> Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Marketplace Bullet Points Modal -->
    <div class="modal fade" id="mpBulletModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title"><i class="fas fa-store me-2"></i>Edit <span id="mpModalMarketplace"></span> Bullet Points</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="mpModalSku">
                    <input type="hidden" id="mpModalMarketplaceVal">
                    <div class="mb-3">
                        <label class="form-label">SKU</label>
                        <div class="form-control-plaintext fw-bold" id="mpModalSkuDisplay"></div>
                    </div>
                    <div class="mb-3">
                        <label for="mpModalTextarea" class="form-label">
                            Bullet Points <span class="char-counter" id="mpModalCounter">0/150</span>
                        </label>
                        <textarea class="form-control" id="mpModalTextarea" rows="4"></textarea>
                        <small class="text-muted">Limit: <span id="mpModalLimit">150</span> characters</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveMpBulletBtn"><i class="fas fa-save"></i> Save</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
(function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const LIMITS = { ebay:150, ebay2:150, ebay3:150, walmart:150, macy:150, aliexpress:150, faire:150, bestbuy:150, wayfair:100, shein:80, doba:60 };
    const MP_LABELS = { ebay:'eBay1', ebay2:'eBay2', ebay3:'eBay3', walmart:'Walmart', macy:"Macy's", aliexpress:'AliExpress', faire:'Faire', bestbuy:'BestBuy', wayfair:'Wayfair', shein:'Shein', doba:'DOBA' };
    const MARKETPLACES = ['ebay','ebay2','ebay3','walmart','macy','aliexpress','faire','bestbuy','wayfair','shein','doba'];
    const MP_GROUP_150 = ['ebay','ebay2','ebay3','walmart','macy','aliexpress','faire','bestbuy'];
    let tableData = [];
    let bulletModal, mpBulletModal;

    function escapeHtml(s) {
        if (s == null) return '';
        const d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }

    function truncate(str, len) {
        if (!str) return '-';
        str = String(str).trim();
        if (!str) return '-';
        return str.length <= len ? str : str.substring(0, len) + '...';
    }

    function loadData() {
        document.getElementById('rainbow-loader').style.display = 'block';
        document.getElementById('table-body').innerHTML = '';
        fetch('/bullet-point-master-combined-data')
            .then(r => r.json())
            .then(res => {
                tableData = res.data || [];
                applyFilters();
                updateCounts();
                document.getElementById('rainbow-loader').style.display = 'none';
            })
            .catch(err => {
                console.error(err);
                document.getElementById('rainbow-loader').style.display = 'none';
                document.getElementById('table-body').innerHTML = '<tr><td colspan="22" class="text-danger">Failed to load data.</td></tr>';
            });
    }

    function applyFilters() {
        const parentFilter = (document.getElementById('parentSearch')?.value || '').toLowerCase();
        const skuFilter = (document.getElementById('skuSearch')?.value || '').toLowerCase();
        const f1 = document.getElementById('filterBullet1')?.value || 'all';
        const f2 = document.getElementById('filterBullet2')?.value || 'all';
        const f3 = document.getElementById('filterBullet3')?.value || 'all';
        const f4 = document.getElementById('filterBullet4')?.value || 'all';
        const f5 = document.getElementById('filterBullet5')?.value || 'all';

        const filtered = tableData.filter(item => {
            if (item.SKU && String(item.SKU).toUpperCase().includes('PARENT')) return false;
            if (parentFilter && !(item.Parent || '').toLowerCase().includes(parentFilter)) return false;
            if (skuFilter && !(item.SKU || '').toLowerCase().includes(skuFilter)) return false;
            const miss = v => v == null || v === '' || (typeof v === 'string' && v.trim() === '');
            if (f1 === 'missing' && !miss(item.bullet1)) return false;
            if (f2 === 'missing' && !miss(item.bullet2)) return false;
            if (f3 === 'missing' && !miss(item.bullet3)) return false;
            if (f4 === 'missing' && !miss(item.bullet4)) return false;
            if (f5 === 'missing' && !miss(item.bullet5)) return false;
            return true;
        });

        renderTable(filtered);
    }

    function renderTable(data) {
        const tbody = document.getElementById('table-body');
        tbody.innerHTML = '';
        if (!data || data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="22" class="text-center">No products found</td></tr>';
            return;
        }

        data.forEach(item => {
            const tr = document.createElement('tr');
            const sku = item.SKU || item.sku || '';

            tr.appendChild(cell('<input type="checkbox" class="row-select">'));
            tr.appendChild(cell(item.image_path ? `<img src="${escapeHtml(item.image_path)}" style="width:36px;height:36px;object-fit:cover;border-radius:4px;">` : '-'));
            tr.appendChild(cell(escapeHtml(item.Parent || '-')));
            tr.appendChild(cell(escapeHtml(sku)));

            for (let i = 1; i <= 5; i++) {
                const td = document.createElement('td');
                td.className = 'bullet-text';
                td.textContent = item['bullet' + i] || '-';
                td.title = item['bullet' + i] || '';
                tr.appendChild(td);
            }

            const actionTd = document.createElement('td');
            actionTd.innerHTML = `<button class="action-btn edit-btn" data-sku="${escapeHtml(sku)}"><i class="fas fa-edit"></i> Edit</button>`;
            tr.appendChild(actionTd);

            const bp = item.bullet_points || {};
            MARKETPLACES.forEach(mp => {
                const td = document.createElement('td');
                td.className = 'mp-cell';
                td.dataset.sku = sku;
                td.dataset.mp = mp;
                const val = bp[mp] || item.default_bullets || '';
                td.innerHTML = `<input type="checkbox" class="mp-checkbox form-check-input" data-sku="${escapeHtml(sku)}" data-mp="${mp}"> <span class="mp-edit-text">${escapeHtml(truncate(val, 35))}</span>`;
                td.title = val || 'Click to edit';
                tr.appendChild(td);
            });

            tbody.appendChild(tr);
        });

        setupRowEvents();
    }

    function cell(html) {
        const td = document.createElement('td');
        td.innerHTML = html;
        return td;
    }

    function setupRowEvents() {
        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', function() { openBulletModal('edit', this.dataset.sku); });
        });
        document.querySelectorAll('.mp-cell').forEach(td => {
            td.addEventListener('click', function(e) {
                if (e.target.classList.contains('mp-checkbox')) return;
                openMpModal(td.dataset.sku, td.dataset.mp);
            });
            td.querySelectorAll('.mp-edit-text').forEach(span => {
                span.style.cursor = 'pointer';
            });
        });
        document.querySelectorAll('.mp-checkbox').forEach(cb => {
            cb.addEventListener('click', e => e.stopPropagation());
            cb.addEventListener('change', updateSelectedCount);
        });
        document.getElementById('selectAll')?.addEventListener('change', function() {
            document.querySelectorAll('.mp-checkbox').forEach(cb => { cb.checked = this.checked; });
            updateSelectedCount();
        });
    }

    function updateSelectedCount() {
        const n = document.querySelectorAll('.mp-checkbox:checked').length;
        document.getElementById('selectedCount').textContent = n;
        document.getElementById('updateSelectedBtn').disabled = n === 0;
    }

    function updateCounts() {
        const items = tableData.filter(i => i.SKU && !String(i.SKU).toUpperCase().includes('PARENT'));
        const parentSet = new Set(items.map(i => i.Parent).filter(Boolean));
        const miss = v => v == null || v === '' || (typeof v === 'string' && v.trim() === '');
        document.getElementById('parentCount').textContent = `(${parentSet.size})`;
        document.getElementById('skuCount').textContent = `(${items.length})`;
        document.getElementById('bullet1MissingCount').textContent = `(${items.filter(i => miss(i.bullet1)).length})`;
        document.getElementById('bullet2MissingCount').textContent = `(${items.filter(i => miss(i.bullet2)).length})`;
        document.getElementById('bullet3MissingCount').textContent = `(${items.filter(i => miss(i.bullet3)).length})`;
        document.getElementById('bullet4MissingCount').textContent = `(${items.filter(i => miss(i.bullet4)).length})`;
        document.getElementById('bullet5MissingCount').textContent = `(${items.filter(i => miss(i.bullet5)).length})`;
    }

    function openBulletModal(mode, sku) {
        const modalTitle = document.getElementById('modalTitle');
        const selectSku = document.getElementById('selectSku');
        const editSku = document.getElementById('editSku');
        document.getElementById('bulletForm').reset();
        for (let i = 1; i <= 5; i++) {
            document.getElementById('counter' + i).textContent = '0/200';
            document.getElementById('counter' + i).classList.remove('error');
        }

        if (mode === 'add') {
            modalTitle.textContent = 'Add Bullet Points';
            selectSku.style.display = 'block';
            selectSku.required = true;
            editSku.value = '';
            selectSku.innerHTML = '<option value="">Choose SKU...</option>';
            tableData.forEach(i => {
                if (i.SKU && !String(i.SKU).toUpperCase().includes('PARENT')) {
                    selectSku.innerHTML += `<option value="${escapeHtml(i.SKU)}">${escapeHtml(i.SKU)}</option>`;
                }
            });
            if (typeof $ !== 'undefined' && $(selectSku).length) {
                try { $(selectSku).select2('destroy'); } catch(e) {}
                $(selectSku).select2({ theme: 'bootstrap-5', placeholder: 'Choose SKU...', width: '100%', dropdownParent: $('#bulletModal') });
            }
        } else {
            modalTitle.textContent = 'Edit Bullet Points';
            selectSku.style.display = 'none';
            selectSku.required = false;
            editSku.value = sku;
            try { $(selectSku).select2('destroy'); } catch(e) {}
            const item = tableData.find(d => (d.SKU || d.sku) === sku);
            if (item) {
                for (let i = 1; i <= 5; i++) {
                    const v = item['bullet' + i] || '';
                    document.getElementById('bullet' + i).value = v;
                    document.getElementById('counter' + i).textContent = v.length + '/200';
                }
            }
        }
        bulletModal.show();
    }

    function openMpModal(sku, mp) {
        const item = tableData.find(d => (d.SKU || d.sku) === sku);
        const bp = item?.bullet_points || {};
        const val = bp[mp] || item?.default_bullets || '';
        const limit = LIMITS[mp] || 150;

        document.getElementById('mpModalSku').value = sku;
        document.getElementById('mpModalMarketplaceVal').value = mp;
        document.getElementById('mpModalSkuDisplay').textContent = sku;
        document.getElementById('mpModalMarketplace').textContent = MP_LABELS[mp] || mp;
        document.getElementById('mpModalTextarea').value = val;
        document.getElementById('mpModalLimit').textContent = limit;
        document.getElementById('mpModalTextarea').maxLength = limit + 50;
        document.getElementById('mpModalCounter').textContent = val.length + '/' + limit;
        mpBulletModal.show();
    }

    document.getElementById('addBulletBtn')?.addEventListener('click', () => openBulletModal('add'));

    for (let i = 1; i <= 5; i++) {
        const input = document.getElementById('bullet' + i);
        const counter = document.getElementById('counter' + i);
        if (input) input.addEventListener('input', function() {
            const len = this.value.length;
            counter.textContent = len + '/200';
            counter.classList.toggle('error', len > 200);
        });
    }

    document.getElementById('saveBulletBtn')?.addEventListener('click', function() {
        const editSku = document.getElementById('editSku');
        const selectSku = document.getElementById('selectSku');
        const sku = editSku.value || (typeof $ !== 'undefined' && $(selectSku).hasClass('select2-hidden-accessible') ? $(selectSku).val() : selectSku.value);
        if (!sku) { alert('Please select a SKU'); return; }

        const payload = { sku };
        for (let i = 1; i <= 5; i++) payload['bullet' + i] = document.getElementById('bullet' + i).value;

        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        fetch('/bullet-points/save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) { bulletModal.hide(); loadData(); alert('Saved!'); }
            else alert(data.message || 'Save failed');
        })
        .catch(e => alert('Error: ' + e.message))
        .finally(() => { this.disabled = false; this.innerHTML = '<i class="fas fa-save"></i> Save'; });
    });

    document.getElementById('mpModalTextarea')?.addEventListener('input', function() {
        const mp = document.getElementById('mpModalMarketplaceVal').value;
        const limit = LIMITS[mp] || 150;
        const len = this.value.length;
        const counter = document.getElementById('mpModalCounter');
        counter.textContent = len + '/' + limit;
        counter.classList.remove('error', 'warning');
        if (len > limit) counter.classList.add('error');
        else if (len > limit * 0.9) counter.classList.add('warning');
        if (len > limit) this.value = this.value.substring(0, limit);
    });

    document.getElementById('saveMpBulletBtn')?.addEventListener('click', function() {
        const sku = document.getElementById('mpModalSku').value;
        const mp = document.getElementById('mpModalMarketplaceVal').value;
        const text = document.getElementById('mpModalTextarea').value.trim();
        if (!text) { alert('Bullet points cannot be empty'); return; }

        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        fetch('/bullet-point-master/update', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ sku, updates: [{ marketplace: mp, bullet_points: text }] })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) { mpBulletModal.hide(); loadData(); alert('Saved!'); }
            else alert(data.message || 'Save failed');
        })
        .catch(e => alert('Error: ' + e.message))
        .finally(() => { this.disabled = false; this.innerHTML = '<i class="fas fa-save"></i> Save'; });
    });

    document.getElementById('updateSelectedBtn')?.addEventListener('click', function() {
        const bySku = {};
        document.querySelectorAll('.mp-checkbox:checked').forEach(cb => {
            const sku = cb.dataset.sku;
            const mp = cb.dataset.mp;
            const item = tableData.find(d => (d.SKU || d.sku) === sku);
            const bp = item?.bullet_points || {};
            const val = (bp[mp] || item?.default_bullets || '').trim();
            if (!val) return;
            if (!bySku[sku]) bySku[sku] = [];
            bySku[sku].push({ marketplace: mp, bullet_points: val });
        });

        const skus = Object.keys(bySku).filter(s => (bySku[s] || []).length > 0);
        if (skus.length === 0) { alert('No bullet points to update. Enter text and try again.'); return; }

        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        let completed = 0;
        const errors = [];

        function next() {
            if (completed >= skus.length) {
                document.getElementById('updateSelectedBtn').disabled = false;
                document.getElementById('updateSelectedBtn').innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Update Selected (<span id="selectedCount">0</span>)';
                document.querySelectorAll('.mp-checkbox:checked').forEach(cb => cb.checked = false);
                updateSelectedCount();
                loadData();
                if (errors.length) alert('Some failed:\n' + errors.slice(0, 5).join('\n'));
                return;
            }
            const sku = skus[completed];
            const updates = bySku[sku].filter(u => (u.bullet_points || '').trim());
            if (updates.length === 0) { completed++; next(); return; }

            fetch('/bullet-point-master/update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ sku, updates })
            })
            .then(r => r.json())
            .then(res => { if (!res.success) errors.push(sku + ': ' + (res.message || 'Failed')); completed++; next(); })
            .catch(e => { errors.push(sku + ': ' + e.message); completed++; next(); });
        }
        next();
    });

    document.querySelectorAll('[data-group]').forEach(el => {
        el.addEventListener('click', function(e) {
            e.preventDefault();
            const g = this.dataset.group;
            const mps = { '150': MP_GROUP_150, '100': ['wayfair'], '80': ['shein'], '60': ['doba'] }[g] || [];
            document.querySelectorAll('.mp-checkbox').forEach(cb => {
                if (mps.includes(cb.dataset.mp)) cb.checked = true;
            });
            updateSelectedCount();
        });
    });

    document.getElementById('exportBtn')?.addEventListener('click', function() {
        const items = tableData.filter(i => i.SKU && !String(i.SKU).toUpperCase().includes('PARENT'));
        const rows = items.map(i => ({
            Parent: i.Parent || '', SKU: i.SKU || '',
            'Bullet 1': i.bullet1 || '', 'Bullet 2': i.bullet2 || '', 'Bullet 3': i.bullet3 || '',
            'Bullet 4': i.bullet4 || '', 'Bullet 5': i.bullet5 || ''
        }));
        const ws = XLSX.utils.json_to_sheet(rows);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Bullet Points');
        XLSX.writeFile(wb, 'bullet_points_' + new Date().toISOString().split('T')[0] + '.xlsx');
    });

    document.getElementById('importBtn')?.addEventListener('click', () => document.getElementById('importFile').click());
    document.getElementById('importFile')?.addEventListener('change', function(e) {
        const f = e.target.files[0];
        if (!f) return;
        const r = new FileReader();
        r.onload = function() {
            try {
                const wb = XLSX.read(new Uint8Array(r.result), { type: 'array' });
                const json = XLSX.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]]);
                if (json.length === 0) { alert('No data'); return; }
                let ok = 0, err = 0;
                Promise.all(json.map(row => {
                    const sku = row.SKU || row.sku;
                    if (!sku) { err++; return Promise.resolve(); }
                    const payload = { sku };
                    for (let i = 1; i <= 5; i++) payload['bullet' + i] = (row['Bullet ' + i] || row['bullet' + i] || '').substring(0, 200);
                    return fetch('/bullet-points/save', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body: JSON.stringify(payload)
                    }).then(res => res.json()).then(d => d.success ? ok++ : err++);
                })).then(() => {
                    alert('Import done. Success: ' + ok + ', Errors: ' + err);
                    loadData();
                });
            } catch (ex) { alert('Import error: ' + ex.message); }
        };
        r.readAsArrayBuffer(f);
        this.value = '';
    });

    document.getElementById('parentSearch')?.addEventListener('input', applyFilters);
    document.getElementById('skuSearch')?.addEventListener('input', applyFilters);
    [1,2,3,4,5].forEach(i => document.getElementById('filterBullet' + i)?.addEventListener('change', applyFilters));

    document.addEventListener('DOMContentLoaded', function() {
        bulletModal = new bootstrap.Modal(document.getElementById('bulletModal'));
        mpBulletModal = new bootstrap.Modal(document.getElementById('mpBulletModal'));
        loadData();
    });
})();
</script>
@endsection
