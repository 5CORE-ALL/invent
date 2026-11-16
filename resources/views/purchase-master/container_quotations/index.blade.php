@extends('layouts.vertical', ['title' => 'Quotations Container'])
@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
            padding: 8px 16px; margin: 0 4px; border-radius: 6px; font-size: 0.95rem; font-weight: 500;
        }
        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active { background: #2563eb; color: #fff; }
        .qc-q-btn {
            width: 28px; height: 28px; border-radius: 50%; border: 2px solid #94a3b8; background: #fff;
            color: #64748b; font-weight: 800; font-size: 14px; line-height: 1; padding: 0; cursor: pointer;
        }
        .qc-q-btn:hover { border-color: #2563eb; color: #2563eb; }
        .qc-q-btn.filled { background: #16a34a; border-color: #16a34a; color: #fff; }
        .qc-q-btn.filled:hover { background: #15803d; border-color: #15803d; color: #fff; }
        .qc-qr-btn {
            min-width: 40px; height: 28px; border-radius: 14px; border: 2px solid #94a3b8; background: #fff;
            color: #64748b; font-weight: 800; font-size: 12px; line-height: 1; padding: 0 8px; cursor: pointer;
        }
        .qc-qr-btn:hover { border-color: #2563eb; color: #2563eb; }
        .qc-qr-btn.filled { background: #2563eb; border-color: #2563eb; color: #fff; }
        .qc-qr-btn.filled:hover { background: #1d4ed8; border-color: #1d4ed8; }
        .qc-qr-btn .qc-qr-count { font-weight: 600; margin-left: 4px; opacity: .9; }
        #qrTable input { min-width: 110px; }
        #qrTable tr.qr-lowest td { background: #ecfdf5; }
    </style>
@endsection
@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Quotations Container',
        'sub_title' => 'Quotations Container',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3 gap-3 flex-wrap">
                        <div class="input-group" style="max-width: 320px;">
                            <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" id="qc-search" class="form-control border-start-0"
                                placeholder="Search container, port, forwarder…" autocomplete="off">
                        </div>
                        <span class="badge bg-light text-dark fs-6" id="qc-count">0 containers</span>
                        <span class="text-muted small">Click <b>Q</b> to enter or edit a container's quotation details.</span>
                    </div>
                    <div id="quotations-container-table"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="qcModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-file-invoice-dollar me-2"></i>Quotation — <span id="qcModalContainer"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="qcForm" autocomplete="off">
                    <div class="modal-body">
                        <input type="hidden" name="container_number" id="qc_container_number">
                        <div class="alert alert-danger py-2 d-none" id="qcError"></div>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Supplier</label>
                                <input type="text" name="supplier" id="qc_supplier" class="form-control"
                                    list="qcSupplierList" placeholder="Type to search supplier…" maxlength="255">
                                <datalist id="qcSupplierList">
                                    @foreach ($suppliers as $s)
                                        <option value="{{ $s }}"></option>
                                    @endforeach
                                </datalist>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Container Size</label>
                                <input type="text" name="container_size" id="qc_container_size" class="form-control"
                                    list="qcSizeList" placeholder="e.g. 45'" maxlength="50">
                                <datalist id="qcSizeList">
                                    <option value="45'"></option>
                                    <option value="20GP"></option>
                                    <option value="40GP"></option>
                                    <option value="40HQ"></option>
                                    <option value="45HQ"></option>
                                    <option value="LCL"></option>
                                </datalist>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Gross Weight</label>
                                <input type="text" name="gross_weight" id="qc_gross_weight" class="form-control"
                                    placeholder="e.g. &lt; 15000 Kgs" maxlength="100">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Freight All Inclusive ($)</label>
                                <input type="number" step="0.01" min="0" name="freight_all_inclusive" id="qc_freight_all_inclusive" class="form-control">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Pickup Address</label>
                                <textarea name="pickup_address" id="qc_pickup_address" class="form-control" rows="2" maxlength="2000"></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Loading Port</label>
                                <select name="loading_port" id="qc_loading_port" class="form-select">
                                    <option value="">Select loading port</option>
                                    <option value="Ningbo">Ningbo</option>
                                    <option value="Guangzhou">Guangzhou</option>
                                    <option value="Tianjin">Tianjin</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Discharge Port</label>
                                <input type="text" name="discharge_port" id="qc_discharge_port" class="form-control" maxlength="255">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Forwarder</label>
                                <input type="text" name="forwarder" id="qc_forwarder" class="form-control"
                                    list="qcForwarderList" maxlength="255">
                                <datalist id="qcForwarderList">
                                    @foreach ($forwarders as $f)
                                        <option value="{{ $f }}"></option>
                                    @endforeach
                                </datalist>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="qcSaveBtn">
                            <i class="fas fa-save me-1"></i> Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade" id="qrModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-envelope-open-text me-2"></i>Quotes received — <span id="qrModalContainer"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger py-2 d-none" id="qrError"></div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle" id="qrTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width:200px;">Forwarder</th>
                                    <th>Pickup Charges ($)</th>
                                    <th>Freight ($)</th>
                                    <th>Total ($)</th>
                                    <th style="width:40px;"></th>
                                </tr>
                            </thead>
                            <tbody id="qrBody"></tbody>
                        </table>
                    </div>
                    <div class="text-muted small d-none" id="qrEmpty">No quotes entered yet — add the first forwarder quote.</div>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-1" id="qrAddRow">
                        <i class="fas fa-plus me-1"></i> Add forwarder quote
                    </button>
                    <span class="text-muted small ms-2">Total = Pickup Charges + Freight. The cheapest total is highlighted.</span>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="qrSaveBtn">
                        <i class="fas fa-save me-1"></i> Save
                    </button>
                </div>
            </div>
        </div>
    </div>@endsection
@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const dataUrl = @json(route('container.quotations.data'));
            const saveUrl = @json(route('container.quotations.save'));
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || @json(csrf_token());
            // Prefilled on the form until a value is saved.
            const DEFAULTS = {
                container_size: "45'",
                gross_weight: '< 15000 Kgs',
                discharge_port: 'CMH, Ohio, USA',
            };
            const fields = ['supplier', 'container_size', 'gross_weight', 'freight_all_inclusive', 'pickup_address',
                'loading_port', 'discharge_port', 'forwarder'];

            function esc(v) {
                return String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }
            function textCell(cell) {
                const v = String(cell.getValue() ?? '').trim();
                return v ? esc(v) : '<span class="text-muted">—</span>';
            }
            function numCell(prefix, suffix) {
                return function (cell) {
                    const v = cell.getValue();
                    if (v === null || v === undefined || v === '') return '<span class="text-muted">—</span>';
                    return prefix + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + suffix;
                };
            }

            const table = new Tabulator('#quotations-container-table', {
                ajaxURL: dataUrl,
                ajaxConfig: 'GET',
                layout: 'fitData',
                pagination: true,
                paginationSize: 50,
                paginationMode: 'local',
                movableColumns: false,
                resizableColumns: true,
                height: '70vh',
                placeholder: 'No containers found on the Ready To Ship page.',
                index: 'container_number',
                columns: [
                    { title: 'Container', field: 'container_number', frozen: true, headerSort: true, minWidth: 120,
                      formatter: c => '<b>' + esc(c.getValue()) + '</b>' },
                    { title: 'Q', field: 'has_details', frozen: true, width: 56, hozAlign: 'center', headerSort: false,
                      headerHozAlign: 'center',
                      formatter: function (cell) {
                          const filled = cell.getValue() ? ' filled' : '';
                          const tip = cell.getValue() ? 'Edit quotation details' : 'Add quotation details';
                          return '<button type="button" class="qc-q-btn' + filled + '" title="' + tip + '">Q</button>';
                      },
                      cellClick: function (e, cell) {
                          if (e.target.closest('.qc-q-btn')) openModal(cell.getRow().getData());
                      } },
                    { title: 'Quotes', field: 'quotes', frozen: true, width: 80, hozAlign: 'center', headerHozAlign: 'center',
                      headerSort: false,
                      formatter: function (cell) {
                          const n = (cell.getValue() || []).length;
                          const cls = n ? ' filled' : '';
                          const badge = n ? '<span class="qc-qr-count">' + n + '</span>' : '';
                          return '<button type="button" class="qc-qr-btn' + cls + '" title="Quote received">QR' + badge + '</button>';
                      },
                      cellClick: function (e, cell) {
                          if (e.target.closest('.qc-qr-btn')) openQrModal(cell.getRow().getData());
                      } },
                    { title: 'Supplier', field: 'supplier', formatter: textCell, minWidth: 140 },
                    { title: 'Container Size', field: 'container_size', formatter: textCell },
                    { title: 'Pickup Address', field: 'pickup_address', formatter: textCell, minWidth: 220, widthGrow: 2 },
                    { title: 'Loading Port', field: 'loading_port', formatter: textCell },
                    { title: 'Discharge Port', field: 'discharge_port', formatter: textCell },
                    { title: 'Gross Weight', field: 'gross_weight', formatter: textCell },
                    { title: 'Freight All Inclusive', field: 'freight_all_inclusive', hozAlign: 'right', sorter: 'number',
                      formatter: numCell('$', '') },
                    { title: 'Forwarder', field: 'forwarder', formatter: textCell },
                    { title: 'Updated', field: 'updated_at', formatter: function (cell) {
                          const d = cell.getData();
                          if (!d.updated_at) return '<span class="text-muted">—</span>';
                          return '<span class="small">' + esc(d.updated_at) + '<br><span class="text-muted">' + esc(d.updated_by || '') + '</span></span>';
                      } },
                ],
            });

            function refreshCount() {
                const n = table.getDataCount('active');
                document.getElementById('qc-count').textContent = n + ' container' + (n === 1 ? '' : 's');
            }
            table.on('dataLoaded', refreshCount);
            table.on('dataFiltered', refreshCount);

            const searchEl = document.getElementById('qc-search');
            searchEl.addEventListener('input', function () {
                const terms = searchEl.value.toLowerCase().split(/\s+/).filter(Boolean);
                if (!terms.length) { table.clearFilter(); return; }
                table.setFilter(function (row) {
                    const hay = [row.container_number, (row.quotes || []).map(q => q.forwarder).join(' '), row.supplier, row.container_size, row.pickup_address, row.loading_port,
                        row.discharge_port, row.forwarder, row.gross_weight, row.freight_all_inclusive]
                        .map(v => String(v ?? '').toLowerCase()).join(' ');
                    return terms.every(t => hay.indexOf(t) !== -1);
                });
            });

            // ── Quotes received (QR) modal ───────────────────────────────
            const qrUrl = @json(route('container.quotations.quotes.save'));
            const qrModalEl = document.getElementById('qrModal');
            const qrBody = document.getElementById('qrBody');
            const qrErr = document.getElementById('qrError');
            const qrEmpty = document.getElementById('qrEmpty');
            const qrSave = document.getElementById('qrSaveBtn');
            let qrContainer = null;

            function num(v) {
                const n = parseFloat(v);
                return isNaN(n) ? 0 : n;
            }
            function fmtMoney(n) {
                return Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            function qrRecalc() {
                let lowest = null;
                const rows = [...qrBody.querySelectorAll('tr')];
                rows.forEach(function (tr) {
                    const pickup = tr.querySelector('.qr-pickup').value;
                    const freight = tr.querySelector('.qr-freight').value;
                    const has = String(pickup).trim() !== '' || String(freight).trim() !== '';
                    const total = num(pickup) + num(freight);
                    tr.querySelector('.qr-total').textContent = has ? fmtMoney(total) : '—';
                    tr.dataset.total = has ? String(total) : '';
                    if (has && (lowest === null || total < lowest)) lowest = total;
                });
                const comparable = rows.filter(tr => tr.dataset.total !== '').length;
                rows.forEach(function (tr) {
                    tr.classList.toggle('qr-lowest', comparable > 1 && tr.dataset.total !== '' && Number(tr.dataset.total) === lowest);
                });
                qrEmpty.classList.toggle('d-none', rows.length > 0);
            }
            function qrAddRow(q) {
                q = q || {};
                const tr = document.createElement('tr');
                tr.innerHTML =
                    '<td><input type="text" class="form-control form-control-sm qr-forwarder" list="qcForwarderList" maxlength="255" placeholder="Forwarder"></td>' +
                    '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm qr-pickup"></td>' +
                    '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm qr-freight"></td>' +
                    '<td class="fw-bold qr-total">—</td>' +
                    '<td><button type="button" class="btn btn-sm btn-link text-danger p-0 qr-del" title="Remove"><i class="fas fa-trash-alt"></i></button></td>';
                tr.querySelector('.qr-forwarder').value = q.forwarder ?? '';
                tr.querySelector('.qr-pickup').value = q.pickup_charges ?? '';
                tr.querySelector('.qr-freight').value = q.freight ?? '';
                qrBody.appendChild(tr);
                qrRecalc();
                return tr;
            }
            function openQrModal(row) {
                qrContainer = row.container_number;
                qrErr.classList.add('d-none');
                document.getElementById('qrModalContainer').textContent = row.container_number;
                qrBody.innerHTML = '';
                (row.quotes || []).forEach(qrAddRow);
                if (!(row.quotes || []).length) qrAddRow();
                qrRecalc();
                bootstrap.Modal.getOrCreateInstance(qrModalEl).show();
            }
            qrBody.addEventListener('input', qrRecalc);
            qrBody.addEventListener('click', function (e) {
                const del = e.target.closest('.qr-del');
                if (!del) return;
                del.closest('tr').remove();
                qrRecalc();
            });
            document.getElementById('qrAddRow').addEventListener('click', function () {
                qrAddRow().querySelector('.qr-forwarder').focus();
            });
            qrSave.addEventListener('click', async function () {
                qrErr.classList.add('d-none');
                qrSave.disabled = true;
                const quotes = [...qrBody.querySelectorAll('tr')].map(tr => ({
                    forwarder: tr.querySelector('.qr-forwarder').value,
                    pickup_charges: tr.querySelector('.qr-pickup').value,
                    freight: tr.querySelector('.qr-freight').value,
                }));
                try {
                    const res = await fetch(qrUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({ container_number: qrContainer, quotes: quotes }),
                    });
                    const json = await res.json().catch(() => ({}));
                    if (!res.ok || !json.success) {
                        let msg = json.message || 'Could not save.';
                        if (json.errors) msg = Object.values(json.errors).flat().join(' ');
                        throw new Error(msg);
                    }
                    table.updateData([{ container_number: json.container_number, quotes: json.quotes }]);
                    bootstrap.Modal.getOrCreateInstance(qrModalEl).hide();
                } catch (err) {
                    qrErr.textContent = err.message || 'Could not save.';
                    qrErr.classList.remove('d-none');
                } finally {
                    qrSave.disabled = false;
                }
            });
            // ── Modal ─────────────────────────────────────────────────────
            const modalEl = document.getElementById('qcModal');
            const errEl = document.getElementById('qcError');
            const saveBtn = document.getElementById('qcSaveBtn');

            function openModal(row) {
                errEl.classList.add('d-none');
                document.getElementById('qcModalContainer').textContent = row.container_number;
                document.getElementById('qc_container_number').value = row.container_number;
                fields.forEach(function (f) {
                    const el = document.getElementById('qc_' + f);
                    if (!el) return;
                    let val = row[f] ?? '';
                    // Keep a previously saved port even if it is not one of the standard choices.
                    if (f === 'loading_port' && val && ![...el.options].some(o => o.value === val)) {
                        el.add(new Option(val, val));
                    }
                    // Autofill defaults for anything not saved yet.
                    if (String(val).trim() === '' && DEFAULTS[f]) val = DEFAULTS[f];
                    el.value = val;
                });
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
            modalEl.addEventListener('shown.bs.modal', () => document.getElementById('qc_supplier').focus());

            document.getElementById('qcForm').addEventListener('submit', async function (e) {
                e.preventDefault();
                errEl.classList.add('d-none');
                saveBtn.disabled = true;
                const body = { container_number: document.getElementById('qc_container_number').value };
                fields.forEach(f => { body[f] = document.getElementById('qc_' + f).value; });
                try {
                    const res = await fetch(saveUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify(body),
                    });
                    const json = await res.json().catch(() => ({}));
                    if (!res.ok || !json.success) {
                        let msg = json.message || 'Could not save.';
                        if (json.errors) msg = Object.values(json.errors).flat().join(' ');
                        throw new Error(msg);
                    }
                    table.updateData([json.row]);
                    bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                } catch (err) {
                    errEl.textContent = err.message || 'Could not save.';
                    errEl.classList.remove('d-none');
                } finally {
                    saveBtn.disabled = false;
                }
            });
        });
    </script>
@endsection