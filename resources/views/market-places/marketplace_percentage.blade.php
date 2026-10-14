@extends('layouts.vertical', ['title' => 'Marketplace %', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .mpct-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(44, 110, 213, 0.06);
        }
        .mpct-table input.form-control {
            min-width: 88px;
            font-weight: 600;
        }
        .mpct-table input.mpct-name {
            min-width: 180px;
            font-weight: 600;
        }
        .mpct-table input.mpct-num {
            max-width: 110px;
            text-align: right;
        }
        .mpct-net {
            font-weight: 700;
            text-align: right;
            white-space: nowrap;
        }
        .mpct-status {
            font-size: 12px;
            min-width: 72px;
        }
        .mpct-status.is-ok { color: #166534; }
        .mpct-status.is-err { color: #b91c1c; }
        .mpct-row.is-saving { opacity: 0.65; }
        .mpct-del {
            border: none;
            background: none;
            color: #dc3545;
            padding: 0 4px;
            line-height: 1;
            cursor: pointer;
            font-size: 1.05rem;
        }
        #mpct-flash { min-height: 1.4em; }
    </style>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="page-title mb-0">
                    <i class="fas fa-percent me-1"></i> Marketplace %
                </h4>
                <span class="text-muted small" id="mpct-count"></span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card mpct-card">
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Values from <code>marketplace_percentages</code>. Edit a cell and leave it to save.
                        Percentage is the keep rate. Ad % is the ad deduction on the same row.
                        Net % is Percentage minus Ad %.
                    </p>

                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-md-4">
                            <label class="form-label mb-1" for="mpct-search">Search</label>
                            <input type="search" id="mpct-search" class="form-control" placeholder="Marketplace name">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-1" for="mpct-new-name">New marketplace</label>
                            <input type="text" id="mpct-new-name" class="form-control" maxlength="255" placeholder="Name">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-1" for="mpct-new-pct">Percentage</label>
                            <input type="number" id="mpct-new-pct" class="form-control" min="0" max="100" step="0.01" value="100">
                        </div>
                        @if ($hasAdUpdates)
                        <div class="col-md-2">
                            <label class="form-label mb-1" for="mpct-new-ad">Ad %</label>
                            <input type="number" id="mpct-new-ad" class="form-control" min="0" step="0.01" value="0">
                        </div>
                        @endif
                        <div class="col-md-1">
                            <button type="button" class="btn btn-primary w-100" id="mpct-add">Add</button>
                        </div>
                    </div>
                    <div id="mpct-flash" class="small mb-2"></div>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0 mpct-table">
                            <thead class="table-light">
                                <tr>
                                    <th>Marketplace</th>
                                    <th class="text-end">Percentage</th>
                                    @if ($hasAdUpdates)
                                    <th class="text-end">Ad %</th>
                                    <th class="text-end" title="Percentage minus Ad %">Net %</th>
                                    @endif
                                    <th>Updated</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="mpct-body"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        (function () {
            const hasAd = @json($hasAdUpdates);
            let rows = @json($rows);
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const body = document.getElementById('mpct-body');
            const countEl = document.getElementById('mpct-count');
            const flash = document.getElementById('mpct-flash');
            const search = document.getElementById('mpct-search');

            function num(value) {
                if (value === null || value === undefined || value === '') return '';
                const n = Number(value);
                if (!Number.isFinite(n)) return '';
                return String(Math.round(n * 100) / 100);
            }

            function setFlash(message, ok) {
                flash.textContent = message || '';
                flash.className = 'small mb-2 ' + (ok ? 'text-success' : 'text-danger');
            }

            function visibleRows() {
                const q = (search.value || '').trim().toLowerCase();
                const list = rows.slice().sort(function (a, b) {
                    return String(a.marketplace).localeCompare(String(b.marketplace));
                });
                if (!q) return list;
                return list.filter(function (row) {
                    return String(row.marketplace).toLowerCase().indexOf(q) !== -1;
                });
            }

            function render() {
                const list = visibleRows();
                countEl.textContent = list.length + (list.length === 1 ? ' marketplace' : ' marketplaces');
                body.innerHTML = '';
                if (!list.length) {
                    const tr = document.createElement('tr');
                    const td = document.createElement('td');
                    td.colSpan = hasAd ? 6 : 4;
                    td.className = 'text-muted text-center py-3';
                    td.textContent = rows.length ? 'No marketplace matches that search.' : 'No marketplace percentages yet.';
                    tr.appendChild(td);
                    body.appendChild(tr);
                    return;
                }
                list.forEach(function (row) {
                    body.appendChild(buildRow(row));
                });
            }

            function buildRow(row) {
                const tr = document.createElement('tr');
                tr.className = 'mpct-row';
                tr.dataset.id = row.id;

                tr.appendChild(cell(nameInput(row), false));
                tr.appendChild(cell(numberInput(row, 'percentage'), true));
                if (hasAd) {
                    tr.appendChild(cell(numberInput(row, 'ad_updates'), true));
                    const net = document.createElement('td');
                    net.className = 'mpct-net';
                    net.dataset.net = '1';
                    net.textContent = num(row.net);
                    tr.appendChild(net);
                }
                const updated = document.createElement('td');
                updated.className = 'text-muted small';
                updated.dataset.updated = '1';
                updated.textContent = row.updated_at || '';
                tr.appendChild(updated);

                const actions = document.createElement('td');
                actions.className = 'text-nowrap';
                const status = document.createElement('span');
                status.className = 'mpct-status me-2';
                status.dataset.status = '1';
                const del = document.createElement('button');
                del.type = 'button';
                del.className = 'mpct-del';
                del.title = 'Remove';
                del.innerHTML = '&times;';
                del.addEventListener('click', function () { removeRow(row, tr); });
                actions.appendChild(status);
                actions.appendChild(del);
                tr.appendChild(actions);
                return tr;
            }

            function cell(input, end) {
                const td = document.createElement('td');
                if (end) td.className = 'text-end';
                td.appendChild(input);
                return td;
            }

            function nameInput(row) {
                const input = document.createElement('input');
                input.type = 'text';
                input.className = 'form-control form-control-sm mpct-name';
                input.maxLength = 255;
                input.value = row.marketplace;
                input.dataset.field = 'marketplace';
                input.dataset.original = row.marketplace;
                bindSave(input, row);
                return input;
            }

            function numberInput(row, field) {
                const input = document.createElement('input');
                input.type = 'number';
                input.className = 'form-control form-control-sm mpct-num';
                input.min = '0';
                if (field === 'percentage') input.max = '100';
                input.step = '0.01';
                input.value = num(row[field]);
                input.dataset.field = field;
                input.dataset.original = num(row[field]);
                bindSave(input, row);
                return input;
            }

            function bindSave(input, row) {
                input.addEventListener('focus', function () {
                    input.dataset.original = input.value;
                });
                input.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        input.blur();
                    }
                });
                input.addEventListener('blur', function () {
                    if (input.value === input.dataset.original) return;
                    saveField(row, input);
                });
            }

            function rowStatus(tr, message, ok) {
                const el = tr.querySelector('[data-status]');
                if (!el) return;
                el.textContent = message;
                el.className = 'mpct-status me-2 ' + (ok ? 'is-ok' : 'is-err');
            }

            async function saveField(row, input) {
                const tr = input.closest('tr');
                tr.classList.add('is-saving');
                rowStatus(tr, 'Saving…', true);
                try {
                    const response = await fetch('/marketplace-percentage/' + row.id, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token,
                        },
                        body: JSON.stringify({
                            field: input.dataset.field,
                            value: input.value,
                        }),
                    });
                    const data = await response.json();
                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Could not save.');
                    }
                    Object.assign(row, data.row);
                    input.dataset.original = input.value;
                    if (input.dataset.field !== 'marketplace') {
                        input.value = num(row[input.dataset.field]);
                    }
                    const net = tr.querySelector('[data-net]');
                    if (net) net.textContent = num(row.net);
                    const updated = tr.querySelector('[data-updated]');
                    if (updated) updated.textContent = row.updated_at || '';
                    rowStatus(tr, 'Saved', true);
                    setFlash('', true);
                } catch (error) {
                    input.value = input.dataset.original;
                    rowStatus(tr, error.message || 'Save failed', false);
                    setFlash(error.message || 'Save failed', false);
                } finally {
                    tr.classList.remove('is-saving');
                }
            }

            async function removeRow(row, tr) {
                if (!window.confirm('Remove ' + row.marketplace + ' from Marketplace %?')) return;
                tr.classList.add('is-saving');
                try {
                    const response = await fetch('/marketplace-percentage/' + row.id, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token,
                        },
                    });
                    const data = await response.json();
                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Could not remove.');
                    }
                    rows = rows.filter(function (item) { return item.id !== row.id; });
                    setFlash(data.message || 'Removed.', true);
                    render();
                } catch (error) {
                    tr.classList.remove('is-saving');
                    rowStatus(tr, error.message || 'Remove failed', false);
                    setFlash(error.message || 'Remove failed', false);
                }
            }

            document.getElementById('mpct-add').addEventListener('click', async function () {
                const name = document.getElementById('mpct-new-name').value.trim();
                const percentage = document.getElementById('mpct-new-pct').value;
                const adInput = document.getElementById('mpct-new-ad');
                if (!name) {
                    setFlash('Marketplace name is required.', false);
                    return;
                }
                setFlash('Adding…', true);
                try {
                    const response = await fetch('/marketplace-percentage', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token,
                        },
                        body: JSON.stringify({
                            marketplace: name,
                            percentage: percentage,
                            ad_updates: adInput ? adInput.value : 0,
                        }),
                    });
                    const data = await response.json();
                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Could not add marketplace.');
                    }
                    rows.push(data.row);
                    document.getElementById('mpct-new-name').value = '';
                    document.getElementById('mpct-new-pct').value = '100';
                    if (adInput) adInput.value = '0';
                    search.value = '';
                    setFlash(data.message || 'Marketplace added.', true);
                    render();
                } catch (error) {
                    setFlash(error.message || 'Could not add marketplace.', false);
                }
            });

            search.addEventListener('input', render);
            render();
        })();
    </script>
@endsection
