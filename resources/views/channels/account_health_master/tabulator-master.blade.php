@extends('layouts.vertical', ['title' => 'Account Health'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        #account-health-tabulator .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            font-weight: 600;
        }

        #account-health-tabulator .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #2563eb;
            color: #fff;
        }

        #account-health-tabulator .tabulator .tabulator-tableholder .tabulator-frozen {
            z-index: 2;
        }

        .ahm-section-title {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #6b7280;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .ahm-icon-btn {
            width: 2rem;
            height: 2rem;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        /* Bootstrap 5 table cells use --bs-table-bg + inset box-shadow; override both for thead */
        #ahmFactorInfoModal .ahm-factor-table thead th {
            --bs-table-bg: #00acc1;
            --bs-table-color: #111827;
            background-color: #00acc1 !important;
            color: #111827 !important;
            box-shadow: inset 0 0 0 9999px #00acc1 !important;
            font-weight: 700;
            border-color: #00838f;
            padding: 0.55rem 0.75rem;
        }

        #ahmFactorInfoModal .ahm-factor-table td {
            vertical-align: middle;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Account Health',
        'sub_title' =>
            'Each marketplace has its own form fields. eBay 1, 2, and 3 share one field list but save separate values. Open a row to customize fields and enter %.',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <span class="fw-semibold">Marketplaces</span>
                </div>
                <div class="card-body p-0">
                    <div id="account-health-tabulator"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Factor / history preview (read-only) --}}
    <div class="modal fade" id="ahmFactorInfoModal" tabindex="-1" aria-labelledby="ahmFactorInfoLabel" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title" id="ahmFactorInfoLabel">Factor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-0">
                    <p class="ahm-section-title mb-1">2. Values for <span id="ahm-factor-info-channel">—</span> (%)</p>
                    <p class="text-muted small mb-2">Each save <strong>adds</strong> a row; the date is the save date
                        (today). History is not overwritten for the same factor key.</p>
                    <div class="row g-2 align-items-end mb-2">
                        <div class="col-sm-auto">
                            <label class="form-label small mb-0" for="ahm-factor-info-date-filter">Date</label>
                            <select class="form-select form-select-sm" id="ahm-factor-info-date-filter"
                                style="min-width: 11rem;" autocomplete="off">
                            </select>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered ahm-factor-table mb-0">
                            <thead>
                                <tr>
                                    <th>Factor</th>
                                    <th style="width:120px;">Value (%)</th>
                                    <th style="width:120px;">Date</th>
                                </tr>
                            </thead>
                            <tbody id="ahm-factor-info-tbody">
                            </tbody>
                        </table>
                        <p class="text-muted small mb-0 mt-2 d-none" id="ahm-factor-info-empty">No factor history for this
                            marketplace yet.</p>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="ahmChannelMetricsModal" tabindex="-1" aria-labelledby="ahmChannelMetricsLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ahmChannelMetricsLabel">Edit account health</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="p-2 mb-3 border rounded-2 bg-light">
                        <div class="ahm-section-title">Marketplace name</div>
                        <div class="fs-5 fw-semibold" id="ahm-modal-channel-display">—</div>
                    </div>
                    <div class="alert alert-info py-2 d-none" id="ahm-ebay-note" role="alert">
                        eBay 1, eBay 2, and eBay 3 <strong>share the same custom fields</strong> (same factors &amp;
                        keys). Each account still has <strong>its own saved values</strong>.
                    </div>

                    <p class="ahm-section-title">1. Customize this form (your marketplace only)</p>
                    <form id="add-metric-field-in-modal" class="row g-2 align-items-end mb-2">
                        <div class="col-sm-5">
                            <label class="form-label small mb-0" for="inmodal-new-label">Factor</label>
                            <input type="text" class="form-control form-control-sm" id="inmodal-new-label" required
                                placeholder="e.g. Transaction defect rate" autocomplete="off">
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label small mb-0" for="inmodal-new-key">Storage key (optional)</label>
                            <input type="text" class="form-control form-control-sm" id="inmodal-new-key"
                                pattern="[a-z0-9_]*" placeholder="auto" autocomplete="off">
                        </div>
                        <div class="col-sm-3">
                            <button type="submit" class="btn btn-sm btn-outline-primary w-100" id="inmodal-add-btn">Add
                                to form</button>
                        </div>
                    </form>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-bordered mb-0" id="ahm-modal-field-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:40px;">#</th>
                                    <th>Factors</th>
                                    <th style="width:120px;">Key</th>
                                    <th class="text-end" style="width:90px;">Order</th>
                                    <th class="text-end" style="width:140px;">&nbsp;</th>
                                </tr>
                            </thead>
                            <tbody id="ahm-modal-field-tbody">
                            </tbody>
                        </table>
                        <p class="text-muted small mb-0 mt-1 d-none" id="ahm-modal-field-empty">No fields yet — add a
                            factor above.</p>
                    </div>

                    <p class="ahm-section-title">2. New reading for <span id="ahm-modal-values-suffix">this
                            marketplace</span> (%)</p>
                    <form id="ahm-channel-metrics-form">
                        <input type="hidden" name="channel_id" id="ahm-modal-channel-id" value="" autocomplete="off">
                        <p class="small text-muted mb-2">Each save adds a row dated today, keyed by each factor below.
                            View or filter past values from the <strong>Factor</strong> column on the grid.</p>
                        <div id="ahm-modal-values-wrap"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="ahm-modal-save">Save values</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        (function() {
            const el = document.getElementById('account-health-tabulator');
            if (!el) {
                return;
            }

            const urlFields = @json(route('account.health.master.metric.fields'));
            const urlFieldsStore = @json(route('account.health.master.metric.fields.store'));
            const urlData = @json(route('account.health.master.tabulator.data'));
            const urlMetricsBatch = @json(route('account.health.master.channel.metrics.batch'));
            const urlHistory = @json(route('account.health.master.metric.value.history'));
            const pathFields = @json(url('/account-health-master/metric-fields'));

            function csrf() {
                return window.__LaravelCsrfToken ||
                    (document.querySelector('meta[name="csrf-token"]') && document.querySelector('meta[name="csrf-token"]')
                        .getAttribute('content')) || '';
            }

            function api(path, options = {}) {
                const headers = Object.assign({
                    'X-CSRF-TOKEN': csrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                }, options.headers || {});
                if (options.body && !(options.body instanceof FormData) && !headers['Content-Type']) {
                    headers['Content-Type'] = 'application/json';
                }
                return fetch(path, Object.assign({
                    credentials: 'same-origin'
                }, options, {
                    headers
                })).then(r => {
                    if (!r.ok) {
                        return r.json().catch(() => ({})).then(j => Promise.reject({
                            status: r.status,
                            body: j
                        }));
                    }
                    if (r.status === 204) {
                        return {};
                    }
                    return r.json();
                });
            }

            function escAttr(s) {
                return String(s)
                    .replace(/&/g, '&amp;')
                    .replace(/"/g, '&quot;')
                    .replace(/</g, '&lt;');
            }

            function escHtml(s) {
                return escAttr(s);
            }

            let table = null;
            let currentModalRow = null;
            let modalFieldList = [];
            const metricsModal = document.getElementById('ahmChannelMetricsModal');
            const metricsChannelDisplay = document.getElementById('ahm-modal-channel-display');
            const metricsChannelId = document.getElementById('ahm-modal-channel-id');
            const valuesWrap = document.getElementById('ahm-modal-values-wrap');
            const valuesSuffix = document.getElementById('ahm-modal-values-suffix');
            const ebayNote = document.getElementById('ahm-ebay-note');
            const factorInfoModal = document.getElementById('ahmFactorInfoModal');
            const factorInfoChannel = document.getElementById('ahm-factor-info-channel');
            const factorInfoDateFilter = document.getElementById('ahm-factor-info-date-filter');
            const factorInfoTbody = document.getElementById('ahm-factor-info-tbody');
            const factorInfoEmpty = document.getElementById('ahm-factor-info-empty');
            let factorInfoHistoryAll = [];

            function appendHistoryRowsToTbody(tbody, rows) {
                if (!tbody) {
                    return;
                }
                tbody.innerHTML = '';
                const list = Array.isArray(rows) ? rows : [];
                list.forEach(function(r) {
                    const tr = document.createElement('tr');
                    const v = (r.value !== null && r.value !== undefined) ? Number(r.value) : null;
                    const vTxt = (v === null || isNaN(v)) ? '—' : (v.toLocaleString(undefined, {
                        maximumFractionDigits: 4
                    }) + '%');
                    tr.innerHTML = '<td class="align-middle"><strong>' + escHtml(r.factor_label || r.field_key) +
                        '</strong> <code class="small text-muted">(' + escHtml(r.field_key) + ')</code></td>' +
                        '<td class="align-middle text-end">' + vTxt + '</td>' +
                        '<td class="align-middle text-nowrap">' + escHtml(r.recorded_on || '—') + '</td>';
                    tbody.appendChild(tr);
                });
            }

            function distinctRecordedDates(rows) {
                const set = new Set();
                (rows || []).forEach(function(r) {
                    const d = r.recorded_on;
                    if (d != null && String(d).trim() !== '') {
                        set.add(String(d).trim());
                    }
                });
                return Array.from(set).sort(function(a, b) {
                    return b.localeCompare(a);
                });
            }

            function populateFactorInfoDateSelect(rows) {
                if (!factorInfoDateFilter) {
                    return;
                }
                factorInfoHistoryAll = Array.isArray(rows) ? rows.slice() : [];
                factorInfoDateFilter.innerHTML = '';
                const dates = distinctRecordedDates(factorInfoHistoryAll);
                if (!factorInfoHistoryAll.length) {
                    const opt = document.createElement('option');
                    opt.value = '';
                    opt.textContent = 'No dates';
                    opt.disabled = true;
                    opt.selected = true;
                    factorInfoDateFilter.appendChild(opt);
                    factorInfoDateFilter.disabled = true;
                    return;
                }
                if (!dates.length) {
                    const opt = document.createElement('option');
                    opt.value = '__ALL__';
                    opt.textContent = 'All records';
                    opt.selected = true;
                    factorInfoDateFilter.appendChild(opt);
                    factorInfoDateFilter.disabled = false;
                    return;
                }
                factorInfoDateFilter.disabled = false;
                dates.forEach(function(d) {
                    const opt = document.createElement('option');
                    opt.value = d;
                    opt.textContent = d;
                    factorInfoDateFilter.appendChild(opt);
                });
                factorInfoDateFilter.value = dates[0];
            }

            function renderFactorInfoTableForSelectedDate() {
                if (!factorInfoTbody) {
                    return;
                }
                if (!factorInfoHistoryAll.length) {
                    factorInfoTbody.innerHTML = '';
                    if (factorInfoEmpty) {
                        factorInfoEmpty.classList.remove('d-none');
                    }
                    return;
                }
                if (!factorInfoDateFilter || factorInfoDateFilter.disabled) {
                    factorInfoTbody.innerHTML = '';
                    if (factorInfoEmpty) {
                        factorInfoEmpty.classList.remove('d-none');
                    }
                    return;
                }
                const sel = String(factorInfoDateFilter.value || '');
                let filtered;
                if (sel === '__ALL__') {
                    filtered = factorInfoHistoryAll.slice();
                } else {
                    filtered = factorInfoHistoryAll.filter(function(r) {
                        return String(r.recorded_on || '').trim() === sel;
                    });
                }
                if (!filtered.length) {
                    factorInfoTbody.innerHTML = '';
                    if (factorInfoEmpty) {
                        factorInfoEmpty.classList.remove('d-none');
                    }
                    return;
                }
                if (factorInfoEmpty) {
                    factorInfoEmpty.classList.add('d-none');
                }
                appendHistoryRowsToTbody(factorInfoTbody, filtered);
            }

            if (factorInfoDateFilter && !factorInfoDateFilter.dataset.ahmBound) {
                factorInfoDateFilter.dataset.ahmBound = '1';
                factorInfoDateFilter.addEventListener('change', function() {
                    renderFactorInfoTableForSelectedDate();
                });
            }

            function openFactorInfoModal(rowData) {
                if (!rowData || !factorInfoModal) {
                    return;
                }
                if (typeof bootstrap === 'undefined') {
                    return;
                }
                if (factorInfoChannel) {
                    factorInfoChannel.textContent = rowData.channel || '—';
                }
                if (factorInfoTbody) {
                    factorInfoTbody.innerHTML = '';
                }
                if (factorInfoEmpty) {
                    factorInfoEmpty.classList.add('d-none');
                }
                factorInfoHistoryAll = [];
                if (factorInfoDateFilter) {
                    factorInfoDateFilter.innerHTML = '';
                    factorInfoDateFilter.disabled = true;
                }
                api(urlHistory + '?channel_id=' + encodeURIComponent(rowData.id)).then(function(hist) {
                    const h = (hist && hist.history) ? hist.history : [];
                    populateFactorInfoDateSelect(h);
                    renderFactorInfoTableForSelectedDate();
                    bootstrap.Modal.getOrCreateInstance(factorInfoModal).show();
                }).catch(function() {
                    alert('Could not load factor history.');
                });
            }

            function buildValueInputs(fields, latestByKey) {
                if (valuesWrap) {
                    valuesWrap.innerHTML = '';
                }
                if (!fields || !fields.length) {
                    if (valuesWrap) {
                        const p = document.createElement('p');
                        p.className = 'text-muted small';
                        p.textContent = 'Add at least one field in step 1 to enter values.';
                        valuesWrap.appendChild(p);
                    }
                    return;
                }
                const latest = latestByKey && typeof latestByKey === 'object' ? latestByKey : {};
                fields.forEach(function(f) {
                    const key = f.field_key;
                    const v = (latest[key] !== undefined && latest[key] !== null) ? latest[key] : '';
                    const vStr = v === '' || v === null || v === undefined ? '' : String(v);
                    const wrap = document.createElement('div');
                    wrap.className = 'mb-2';
                    wrap.innerHTML = '<label class="form-label small mb-0" for="ahm-val-' + escAttr(f.field_key) + '">' +
                        escHtml(f.label) + ' <code class="small">(' + escHtml(f.field_key) + ')</code> ' +
                        '<span class="text-muted">(%)</span></label>' +
                        '<input type="number" class="form-control" id="ahm-val-' + escAttr(f.field_key) + '" ' +
                        'name="' + escAttr(f.field_key) + '" data-field-key="' + escAttr(f.field_key) + '" ' +
                        'step="any" min="0" value="' + escAttr(vStr) + '">';
                    if (valuesWrap) {
                        valuesWrap.appendChild(wrap);
                    }
                });
            }

            function renderModalFieldRows(fields) {
                const tbody = document.getElementById('ahm-modal-field-tbody');
                const emptyH = document.getElementById('ahm-modal-field-empty');
                if (!tbody) {
                    return;
                }
                tbody.innerHTML = '';
                if (!fields.length) {
                    if (emptyH) {
                        emptyH.classList.remove('d-none');
                    }
                    return;
                }
                if (emptyH) {
                    emptyH.classList.add('d-none');
                }
                fields.forEach(function(f, idx) {
                    const tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + (idx + 1) + '</td>' +
                        '<td><input type="text" class="form-control form-control-sm ahm-mf-label" value="' + escAttr(
                            f.label) + '"></td>' +
                        '<td><code class="small">' + String(f.field_key).replace(/</g, '&lt;') + '</code></td>' +
                        '<td class="text-end text-nowrap">' +
                        '<button type="button" class="btn btn-sm btn-light border ahm-icon-btn ahm-mf-up" title="Move up" aria-label="Move up">' +
                        '<i class="fa-solid fa-chevron-up" aria-hidden="true"></i></button> ' +
                        '<button type="button" class="btn btn-sm btn-light border ahm-icon-btn ahm-mf-down" title="Move down" aria-label="Move down">' +
                        '<i class="fa-solid fa-chevron-down" aria-hidden="true"></i></button></td>' +
                        '<td class="text-end text-nowrap">' +
                        '<button type="button" class="btn btn-sm btn-primary ahm-icon-btn ahm-mf-save" title="Save factor" aria-label="Save factor">' +
                        '<i class="fa-solid fa-floppy-disk" aria-hidden="true"></i></button> ' +
                        '<button type="button" class="btn btn-sm btn-outline-danger ahm-icon-btn ahm-mf-del" title="Remove field" aria-label="Remove field">' +
                        '<i class="fa-solid fa-trash-can" aria-hidden="true"></i></button></td>';
                    tr.querySelector('.ahm-mf-save').addEventListener('click', function() {
                        const input = tr.querySelector('.ahm-mf-label');
                        const label = input && input.value.trim();
                        if (!label) {
                            return;
                        }
                        api(pathFields + '/' + f.id, {
                            method: 'PATCH',
                            body: JSON.stringify({
                                label: label
                            })
                        }).then(function() {
                            return reloadModalContext();
                        }).catch(function() {
                            alert('Could not update factor');
                        });
                    });
                    tr.querySelector('.ahm-mf-del').addEventListener('click', function() {
                        if (!confirm('Remove this field and its values for this marketplace (or eBay group)?')) {
                            return;
                        }
                        api(pathFields + '/' + f.id, {
                            method: 'DELETE'
                        }).then(function() {
                            return reloadModalContext();
                        }).catch(function() {
                            alert('Remove failed');
                        });
                    });
                    tr.querySelector('.ahm-mf-up').addEventListener('click', function() {
                        api(pathFields + '/' + f.id + '/reorder/up', {
                            method: 'POST'
                        }).then(function() {
                            return reloadModalContext();
                        }).catch(function() {});
                    });
                    tr.querySelector('.ahm-mf-down').addEventListener('click', function() {
                        api(pathFields + '/' + f.id + '/reorder/down', {
                            method: 'POST'
                        }).then(function() {
                            return reloadModalContext();
                        }).catch(function() {});
                    });
                    tbody.appendChild(tr);
                });
            }

            function mergeRowWithFreshData(prevRow) {
                if (!table || !prevRow) {
                    return Promise.resolve(prevRow);
                }
                return api(urlData).then(function(rows) {
                    const m = (rows || []).find(function(r) {
                        return String(r.id) === String(prevRow.id);
                    });
                    return m || prevRow;
                });
            }

            function reloadModalContext() {
                if (!currentModalRow) {
                    return Promise.resolve();
                }
                return mergeRowWithFreshData(currentModalRow).then(function(merged) {
                    currentModalRow = merged;
                    return Promise.all([
                        api(urlFields + '?channel_id=' + encodeURIComponent(merged.id)),
                        api(urlHistory + '?channel_id=' + encodeURIComponent(merged.id))
                    ]).then(function(results) {
                        const data = results[0];
                        const hist = results[1] || {};
                        const fields = (data && data.fields) ? data.fields : [];
                        if (data && data.is_ebay_shared && ebayNote) {
                            ebayNote.classList.remove('d-none');
                        } else if (ebayNote) {
                            ebayNote.classList.add('d-none');
                        }
                        modalFieldList = fields;
                        renderModalFieldRows(fields);
                        if (valuesSuffix) {
                            valuesSuffix.textContent = (merged && merged.channel) ? merged.channel :
                                'this marketplace';
                        }
                        buildValueInputs(fields, hist.latest_by_key);
                    });
                });
            }

            function openChannelModal(rowData) {
                if (!rowData) {
                    return;
                }
                if (typeof bootstrap === 'undefined' || ! metricsModal) {
                    return;
                }
                currentModalRow = rowData;
                if (metricsChannelDisplay) {
                    metricsChannelDisplay.textContent = rowData.channel || '—';
                }
                if (metricsChannelId) {
                    metricsChannelId.value = String(rowData.id);
                }
                if (valuesSuffix) {
                    valuesSuffix.textContent = rowData.channel || 'this marketplace';
                }
                Promise.all([
                    api(urlFields + '?channel_id=' + encodeURIComponent(rowData.id)),
                    api(urlHistory + '?channel_id=' + encodeURIComponent(rowData.id))
                ]).then(function(results) {
                    const data = results[0];
                    const hist = results[1] || {};
                    if (data && data.is_ebay_shared && ebayNote) {
                        ebayNote.classList.remove('d-none');
                    } else if (ebayNote) {
                        ebayNote.classList.add('d-none');
                    }
                    const fields = (data && data.fields) ? data.fields : [];
                    modalFieldList = fields;
                    renderModalFieldRows(fields);
                    buildValueInputs(fields, hist.latest_by_key);
                    new bootstrap.Modal(metricsModal).show();
                }).catch(function() {
                    alert('Could not load form for this marketplace.');
                });
            }

            function editActionFormatter() {
                return function(cell) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'btn btn-sm btn-primary';
                    btn.textContent = 'Form';
                    btn.addEventListener('click', function(ev) {
                        ev.stopPropagation();
                        const row = cell.getRow();
                        if (row) {
                            openChannelModal(row.getData());
                        }
                    });
                    return btn;
                };
            }

            function factorInfoIconFormatter() {
                return function(cell) {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className =
                        'btn btn-sm btn-link text-info ahm-icon-btn text-decoration-none border-0 shadow-none p-0';
                    b.setAttribute('title', 'View factor history');
                    b.setAttribute('aria-label', 'View factor history');
                    b.innerHTML = '<i class="fa-solid fa-circle-info" aria-hidden="true"></i>';
                    b.addEventListener('click', function(ev) {
                        ev.stopPropagation();
                        const row = cell.getRow();
                        if (row) {
                            openFactorInfoModal(row.getData());
                        }
                    });
                    return b;
                };
            }

            function buildTableColumns() {
                return [{
                    title: '#',
                    formatter: 'rownum',
                    hozAlign: 'center',
                    width: 50,
                    headerSort: false
                }, {
                    title: 'Marketplace name',
                    field: 'channel',
                    minWidth: 240,
                    widthGrow: 1,
                    frozen: true,
                    headerFilter: 'input',
                    headerFilterPlaceholder: 'Filter…',
                    formatter: function(cell) {
                        const s = document.createElement('span');
                        s.style.fontWeight = '600';
                        s.textContent = cell.getValue() || '';
                        return s;
                    }
                }, {
                    field: 'type',
                    title: 'Type',
                    width: 80,
                    visible: false
                }, {
                    title: 'Factor',
                    field: '_factor',
                    width: 72,
                    hozAlign: 'center',
                    headerSort: false,
                    headerTooltip: 'Factor value history (%)',
                    formatter: factorInfoIconFormatter()
                }, {
                    title: 'Form',
                    field: '_form',
                    width: 88,
                    hozAlign: 'center',
                    headerSort: false,
                    formatter: editActionFormatter()
                }];
            }

            function mountTable() {
                if (table) {
                    table.destroy();
                    table = null;
                }
                return api(urlData).then(function(rows) {
                    table = new Tabulator('#account-health-tabulator', {
                        layout: 'fitColumns',
                        responsiveLayout: false,
                        data: rows,
                        pagination: true,
                        paginationSize: 100,
                        paginationMode: 'local',
                        height: 600,
                        placeholder: 'No active marketplaces in Channel Master',
                        index: 'id',
                        initialSort: [{
                            column: 'channel',
                            dir: 'asc'
                        }],
                        columns: buildTableColumns()
                    });
                    table.on('rowDblClick', function(e, row) {
                        openChannelModal(row.getData());
                    });
                });
            }

            document.getElementById('ahm-modal-save') && document.getElementById('ahm-modal-save').addEventListener(
                'click',
                function() {
                    const chId = metricsChannelId && metricsChannelId.value ? metricsChannelId.value : null;
                    if (!chId) {
                        return;
                    }
                    if (!valuesWrap) {
                        return;
                    }
                    const payload = {
                        channel_id: parseInt(chId, 10),
                        metrics: {}
                    };
                    valuesWrap.querySelectorAll('input[data-field-key]').forEach(function(inp) {
                        const k = inp.getAttribute('data-field-key');
                        if (!k) {
                            return;
                        }
                        if (inp.value === '' || inp.value === null) {
                            return;
                        }
                        payload.metrics[k] = inp.value;
                    });
                    if (Object.keys(payload.metrics).length === 0) {
                        alert('Enter at least one value (%).');
                        return;
                    }
                    api(urlMetricsBatch, {
                        method: 'POST',
                        body: JSON.stringify(payload)
                    }).then(function(res) {
                        const n = res && res.inserted ? res.inserted : 0;
                        if (n) {
                            // optional: could show "Saved n rows"
                        }
                        return Promise.all([reloadModalContext(), mountTable()]);
                    }).catch(function(e) {
                        const msg = (e.body && (e.body.message || (e.body.errors && JSON.stringify(e
                            .body.errors)))) || 'Save failed. Try again.';
                        alert(msg);
                    });
                });

            document.getElementById('add-metric-field-in-modal') && document
                .getElementById('add-metric-field-in-modal')
                .addEventListener('submit', function(ev) {
                    ev.preventDefault();
                    const chId = metricsChannelId && metricsChannelId.value;
                    if (!chId) {
                        return;
                    }
                    const lab = document.getElementById('inmodal-new-label');
                    const k = document.getElementById('inmodal-new-key');
                    if (!lab) {
                        return;
                    }
                    const body = {
                        label: (lab.value || '').trim(),
                        channel_id: parseInt(chId, 10)
                    };
                    if (k && (k.value || '').trim() !== '') {
                        body.field_key = (k.value || '').trim();
                    }
                    if (!body.label) {
                        return;
                    }
                    const b = document.getElementById('inmodal-add-btn');
                    if (b) {
                        b.disabled = true;
                    }
                    api(urlFieldsStore, {
                        method: 'POST',
                        body: JSON.stringify(body)
                    }).then(function() {
                        lab.value = '';
                        if (k) {
                            k.value = '';
                        }
                        return reloadModalContext();
                    }).catch(function(e) {
                        const msg = (e.body && (e.body.message)) || 'Could not add';
                        alert(msg);
                    }).finally(function() {
                        if (b) {
                            b.disabled = false;
                        }
                    });
                });

            mountTable();
        })();
    </script>
@endsection
