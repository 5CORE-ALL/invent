@extends('layouts.vertical', ['title' => 'API Vs Sheet'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        #api-vs-sheet-tabulator .tabulator .tabulator-header .tabulator-col .tabulator-col-content {
            text-align: center;
        }

        #api-vs-sheet-tabulator .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            font-weight: 600;
            text-align: center;
            width: 100%;
        }

        #api-vs-sheet-tabulator .tabulator .tabulator-cell {
            text-align: center;
        }

        #api-vs-sheet-tabulator .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #2563eb;
            color: #fff;
        }

        #api-vs-sheet-tabulator .avs-channel-logo {
            max-width: 36px;
            max-height: 36px;
            object-fit: contain;
            display: inline-block;
        }

        #api-vs-sheet-tabulator .avs-channel-logo-placeholder {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 4px;
            background: #f3f4f6;
            color: #9ca3af;
            font-size: 0.75rem;
        }

        #api-vs-sheet-tabulator .avs-select {
            width: 100%;
            max-width: 200px;
            margin: 0 auto;
            font-size: 0.8rem;
            padding: 4px 8px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: #fff;
            color: #111827;
            cursor: pointer;
        }

        #api-vs-sheet-tabulator .avs-select:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15);
        }

        #api-vs-sheet-tabulator .avs-select.is-saving {
            opacity: 0.6;
            pointer-events: none;
        }

        #api-vs-sheet-tabulator .avs-sheet-cell {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        #api-vs-sheet-tabulator .avs-sheet-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            background: #16a34a;
            color: #fff;
            text-decoration: none;
            font-size: 0.8rem;
        }

        #api-vs-sheet-tabulator .avs-sheet-link:hover {
            background: #15803d;
            color: #fff;
        }

        #api-vs-sheet-tabulator .avs-sheet-edit-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            min-width: 28px;
            height: 28px;
            padding: 0 8px;
            border: 0;
            border-radius: 6px;
            background: #f3f4f6;
            color: #2563eb;
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 600;
            transition: background 0.15s ease, color 0.15s ease;
        }

        #api-vs-sheet-tabulator .avs-sheet-edit-btn:hover {
            background: #2563eb;
            color: #fff;
        }

        #api-vs-sheet-tabulator .avs-price-api-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            min-width: 56px;
            height: 28px;
            padding: 0 10px;
            border: 0;
            border-radius: 6px;
            background: #f3f4f6;
            color: #374151;
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 600;
            transition: background 0.15s ease, color 0.15s ease;
        }

        #api-vs-sheet-tabulator .avs-price-api-btn:hover {
            background: #2563eb;
            color: #fff;
        }

        #api-vs-sheet-tabulator .avs-price-api-btn.is-yes {
            background: #dcfce7;
            color: #166534;
        }

        #api-vs-sheet-tabulator .avs-price-api-btn.is-no {
            background: #fee2e2;
            color: #991b1b;
        }

        #api-vs-sheet-tabulator .avs-price-api-cell {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        #avsPriceApi2wModal .avs-yn-options {
            display: flex;
            gap: 16px;
            align-items: center;
        }

        #avsPriceApi2wModal .avs-yn-option {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            user-select: none;
        }
    </style>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <h4 class="page-title mb-0">API Vs Sheet</h4>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <span class="fw-semibold">API Vs Sheet</span>
                    <span class="small text-muted">Active channels from Channel Master</span>
                </div>
                <div class="card-body p-0">
                    <div id="api-vs-sheet-tabulator"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="avsSheetLinkModal" tabindex="-1" aria-labelledby="avsSheetLinkModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title fw-semibold mb-0" id="avsSheetLinkModalLabel">Sheet Link</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="small text-muted mb-2">
                        Channel: <strong id="avs-sheet-modal-channel">—</strong>
                    </div>
                    <div class="mb-1">
                        <label class="form-label small mb-1" for="avs-sheet-link-input">Sheet Link URL</label>
                        <input type="url" class="form-control form-control-sm" id="avs-sheet-link-input"
                            placeholder="https://…" autocomplete="off">
                        <div class="small text-muted mt-1">Leave blank to clear the link.</div>
                    </div>
                    <div class="small text-danger mt-2 d-none" id="avs-sheet-modal-error"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" id="avs-sheet-modal-save">Save</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="avsPriceApi2wModal" tabindex="-1" aria-labelledby="avsPriceApi2wModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title fw-semibold mb-0" id="avsPriceApi2wModalLabel">Price API 2W</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="small text-muted mb-3">
                        Channel: <strong id="avs-price-api-modal-channel">—</strong>
                    </div>
                    <div class="mb-3">
                        <div class="form-label small mb-2">Price API 2W</div>
                        <div class="avs-yn-options">
                            <label class="avs-yn-option">
                                <input type="checkbox" id="avs-price-api-yes" class="form-check-input">
                                Yes
                            </label>
                            <label class="avs-yn-option">
                                <input type="checkbox" id="avs-price-api-no" class="form-check-input">
                                No
                            </label>
                        </div>
                    </div>
                    <div class="mb-1 d-none" id="avs-price-api-sheet-wrap">
                        <label class="form-label small mb-1" for="avs-price-api-sheet-input">Sheet</label>
                        <input type="url" class="form-control form-control-sm" id="avs-price-api-sheet-input"
                            placeholder="https://…" autocomplete="off">
                        <div class="small text-muted mt-1">Required when No is selected. Leave blank to clear.</div>
                    </div>
                    <div class="small text-danger mt-2 d-none" id="avs-price-api-modal-error"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" id="avs-price-api-modal-save">Save</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        (function() {
            const el = document.getElementById('api-vs-sheet-tabulator');
            if (!el) {
                return;
            }

            const urlData = @json(route('api.vs.sheet.tabulator.data'));
            const urlSave = @json(route('api.vs.sheet.save'));
            const urlSheetLinkSave = @json(route('api.vs.sheet.sheet.link.save'));
            const urlPriceApi2wSave = @json(route('api.vs.sheet.price.api.2w.save'));
            const downloadOptions = @json(\App\Http\Controllers\Channels\ApiVsSheetController::DOWNLOAD_OPTIONS);
            const uploadOptions = @json(\App\Http\Controllers\Channels\ApiVsSheetController::UPLOAD_OPTIONS);

            let table = null;
            let editChannelId = null;
            let priceApiEditChannelId = null;

            function csrf() {
                return window.__LaravelCsrfToken ||
                    (document.querySelector('meta[name="csrf-token"]') && document.querySelector(
                        'meta[name="csrf-token"]').getAttribute('content')) || '';
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
                    .replace(/'/g, '&#39;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');
            }

            function channelLogoFormatter(cell) {
                const data = cell.getRow().getData();
                const logo = data && data.logo ? String(data.logo).trim() : '';
                const channel = data && data.channel ? String(data.channel) : '';
                if (!logo) {
                    return '<span class="avs-channel-logo-placeholder" title="No logo">—</span>';
                }
                return '<img src="/storage/' + escAttr(logo) + '" alt="' + escAttr(channel) +
                    '" class="avs-channel-logo" onerror="this.outerHTML=\'<span class=&quot;avs-channel-logo-placeholder&quot; title=&quot;No logo&quot;>—</span>\'" />';
            }

            function openSheetLinkModal(rowData) {
                editChannelId = rowData.id;
                const channelEl = document.getElementById('avs-sheet-modal-channel');
                const inputEl = document.getElementById('avs-sheet-link-input');
                const errorEl = document.getElementById('avs-sheet-modal-error');
                if (channelEl) {
                    channelEl.textContent = rowData.channel || '—';
                }
                if (inputEl) {
                    inputEl.value = rowData.sheet_link || '';
                }
                if (errorEl) {
                    errorEl.textContent = '';
                    errorEl.classList.add('d-none');
                }
                const modalEl = document.getElementById('avsSheetLinkModal');
                if (modalEl && window.bootstrap && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                    setTimeout(function() {
                        if (inputEl) {
                            inputEl.focus();
                            inputEl.select();
                        }
                    }, 200);
                }
            }

            function commitSheetLinkFromModal() {
                if (!editChannelId) {
                    return;
                }
                const inputEl = document.getElementById('avs-sheet-link-input');
                const errorEl = document.getElementById('avs-sheet-modal-error');
                const saveBtn = document.getElementById('avs-sheet-modal-save');
                const value = inputEl ? String(inputEl.value || '').trim() : '';

                if (errorEl) {
                    errorEl.textContent = '';
                    errorEl.classList.add('d-none');
                }
                if (saveBtn) {
                    saveBtn.disabled = true;
                }

                api(urlSheetLinkSave, {
                    method: 'POST',
                    body: JSON.stringify({
                        channel_id: editChannelId,
                        sheet_link: value,
                    }),
                }).then(function(res) {
                    if (table) {
                        const row = table.getRow(editChannelId);
                        if (row) {
                            row.update({
                                sheet_link: res.sheet_link || null
                            });
                        }
                    }
                    const modalEl = document.getElementById('avsSheetLinkModal');
                    if (modalEl && window.bootstrap && bootstrap.Modal) {
                        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                    }
                }).catch(function(err) {
                    const msg = (err && err.body && (err.body.message || (err.body.errors && Object
                        .values(err.body.errors).flat().join(' ')))) || 'Failed to save sheet link.';
                    if (errorEl) {
                        errorEl.textContent = msg;
                        errorEl.classList.remove('d-none');
                    }
                }).finally(function() {
                    if (saveBtn) {
                        saveBtn.disabled = false;
                    }
                });
            }

            function sheetLinkFormatter(cell) {
                const data = cell.getRow().getData() || {};
                const link = data.sheet_link ? String(data.sheet_link).trim() : '';
                const wrap = document.createElement('div');
                wrap.className = 'avs-sheet-cell';

                if (link) {
                    const a = document.createElement('a');
                    a.href = link;
                    a.target = '_blank';
                    a.rel = 'noopener noreferrer';
                    a.className = 'avs-sheet-link';
                    a.title = 'Open sheet link';
                    a.innerHTML = '<i class="fas fa-link"></i>';
                    wrap.appendChild(a);
                }

                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'avs-sheet-edit-btn';
                btn.title = link ? 'Edit sheet link' : 'Add sheet link';
                btn.innerHTML = link ?
                    '<i class="fas fa-pen"></i>' :
                    '<i class="fas fa-plus"></i> Add';
                btn.addEventListener('click', function(ev) {
                    ev.preventDefault();
                    ev.stopPropagation();
                    openSheetLinkModal(data);
                });
                wrap.appendChild(btn);

                return wrap;
            }

            function syncPriceApiSheetVisibility() {
                const noEl = document.getElementById('avs-price-api-no');
                const wrap = document.getElementById('avs-price-api-sheet-wrap');
                if (!wrap) {
                    return;
                }
                if (noEl && noEl.checked) {
                    wrap.classList.remove('d-none');
                } else {
                    wrap.classList.add('d-none');
                }
            }

            function openPriceApi2wModal(rowData) {
                priceApiEditChannelId = rowData.id;
                const channelEl = document.getElementById('avs-price-api-modal-channel');
                const yesEl = document.getElementById('avs-price-api-yes');
                const noEl = document.getElementById('avs-price-api-no');
                const sheetEl = document.getElementById('avs-price-api-sheet-input');
                const errorEl = document.getElementById('avs-price-api-modal-error');

                if (channelEl) {
                    channelEl.textContent = rowData.channel || '—';
                }
                if (yesEl) {
                    yesEl.checked = rowData.price_api_2w === 'Yes';
                }
                if (noEl) {
                    noEl.checked = rowData.price_api_2w === 'No';
                }
                if (sheetEl) {
                    sheetEl.value = rowData.price_api_2w_sheet_link || '';
                }
                if (errorEl) {
                    errorEl.textContent = '';
                    errorEl.classList.add('d-none');
                }
                syncPriceApiSheetVisibility();

                const modalEl = document.getElementById('avsPriceApi2wModal');
                if (modalEl && window.bootstrap && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
            }

            function commitPriceApi2wFromModal() {
                if (!priceApiEditChannelId) {
                    return;
                }
                const yesEl = document.getElementById('avs-price-api-yes');
                const noEl = document.getElementById('avs-price-api-no');
                const sheetEl = document.getElementById('avs-price-api-sheet-input');
                const errorEl = document.getElementById('avs-price-api-modal-error');
                const saveBtn = document.getElementById('avs-price-api-modal-save');

                const isYes = !!(yesEl && yesEl.checked);
                const isNo = !!(noEl && noEl.checked);
                if (!isYes && !isNo) {
                    if (errorEl) {
                        errorEl.textContent = 'Please select Yes or No.';
                        errorEl.classList.remove('d-none');
                    }
                    return;
                }
                if (isYes && isNo) {
                    if (errorEl) {
                        errorEl.textContent = 'Select only Yes or No.';
                        errorEl.classList.remove('d-none');
                    }
                    return;
                }

                const choice = isYes ? 'Yes' : 'No';
                const sheetLink = sheetEl ? String(sheetEl.value || '').trim() : '';

                if (errorEl) {
                    errorEl.textContent = '';
                    errorEl.classList.add('d-none');
                }
                if (saveBtn) {
                    saveBtn.disabled = true;
                }

                api(urlPriceApi2wSave, {
                    method: 'POST',
                    body: JSON.stringify({
                        channel_id: priceApiEditChannelId,
                        price_api_2w: choice,
                        price_api_2w_sheet_link: choice === 'No' ? sheetLink : null,
                    }),
                }).then(function(res) {
                    if (table) {
                        const row = table.getRow(priceApiEditChannelId);
                        if (row) {
                            row.update({
                                price_api_2w: res.price_api_2w || null,
                                price_api_2w_sheet_link: res.price_api_2w_sheet_link || null,
                            });
                        }
                    }
                    const modalEl = document.getElementById('avsPriceApi2wModal');
                    if (modalEl && window.bootstrap && bootstrap.Modal) {
                        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                    }
                }).catch(function(err) {
                    const msg = (err && err.body && (err.body.message || (err.body.errors && Object
                        .values(err.body.errors).flat().join(' ')))) || 'Failed to save Price API 2W.';
                    if (errorEl) {
                        errorEl.textContent = msg;
                        errorEl.classList.remove('d-none');
                    }
                }).finally(function() {
                    if (saveBtn) {
                        saveBtn.disabled = false;
                    }
                });
            }

            function priceApi2wFormatter(cell) {
                const data = cell.getRow().getData() || {};
                const value = data.price_api_2w || '';
                const sheetLink = data.price_api_2w_sheet_link ? String(data.price_api_2w_sheet_link).trim() : '';
                const wrap = document.createElement('div');
                wrap.className = 'avs-price-api-cell';

                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'avs-price-api-btn' +
                    (value === 'Yes' ? ' is-yes' : (value === 'No' ? ' is-no' : ''));
                btn.title = 'Edit Price API 2W';
                btn.textContent = value || 'Set';
                btn.addEventListener('click', function(ev) {
                    ev.preventDefault();
                    ev.stopPropagation();
                    openPriceApi2wModal(data);
                });
                wrap.appendChild(btn);

                if (value === 'No' && sheetLink) {
                    const a = document.createElement('a');
                    a.href = sheetLink;
                    a.target = '_blank';
                    a.rel = 'noopener noreferrer';
                    a.className = 'avs-sheet-link';
                    a.title = 'Open Price API 2W sheet';
                    a.innerHTML = '<i class="fas fa-link"></i>';
                    wrap.appendChild(a);
                }

                return wrap;
            }

            function sourceSelectFormatter(options, field) {
                return function(cell) {
                    const row = cell.getRow().getData();
                    const current = row[field] || '';
                    const select = document.createElement('select');
                    select.className = 'avs-select';
                    select.setAttribute('data-field', field);
                    select.setAttribute('data-channel-id', String(row.id));

                    const blank = document.createElement('option');
                    blank.value = '';
                    blank.textContent = '— Select —';
                    select.appendChild(blank);

                    options.forEach(function(opt) {
                        const o = document.createElement('option');
                        o.value = opt;
                        o.textContent = opt;
                        if (opt === current) {
                            o.selected = true;
                        }
                        select.appendChild(o);
                    });

                    select.addEventListener('change', function() {
                        const value = select.value || null;
                        const prev = row[field] || '';
                        select.classList.add('is-saving');
                        api(urlSave, {
                            method: 'POST',
                            body: JSON.stringify({
                                channel_id: row.id,
                                field: field,
                                value: value,
                            }),
                        }).then(function(res) {
                            cell.getRow().update({
                                download_source: res.download_source || null,
                                upload_source: res.upload_source || null,
                            });
                        }).catch(function() {
                            select.value = prev;
                            alert('Failed to save ' + (field === 'download_source' ? 'Download' : 'Upload') + ' setting.');
                        }).finally(function() {
                            select.classList.remove('is-saving');
                        });
                    });

                    return select;
                };
            }

            function buildTableColumns() {
                return [{
                    title: 'Img',
                    field: 'logo',
                    width: 70,
                    hozAlign: 'center',
                    headerSort: false,
                    formatter: channelLogoFormatter
                }, {
                    title: 'Channels',
                    field: 'channel',
                    minWidth: 200,
                    widthGrow: 1,
                    frozen: true,
                    formatter: function(cell) {
                        const s = document.createElement('span');
                        s.style.fontWeight = '600';
                        s.textContent = cell.getValue() || '';
                        return s;
                    }
                }, {
                    title: 'Sheet Link',
                    field: 'sheet_link',
                    width: 140,
                    hozAlign: 'center',
                    headerSort: false,
                    headerTooltip: 'Add or edit sheet link (saved to Channel Master)',
                    formatter: sheetLinkFormatter
                }, {
                    title: 'Price API 2W',
                    field: 'price_api_2w',
                    width: 130,
                    hozAlign: 'center',
                    headerSort: true,
                    headerTooltip: 'Yes / No. If No, set a Sheet link.',
                    formatter: priceApi2wFormatter
                }, {
                    title: 'Download',
                    field: 'download_source',
                    minWidth: 180,
                    width: 220,
                    hozAlign: 'center',
                    headerSort: true,
                    headerTooltip: 'Sheet or API Called Download',
                    formatter: sourceSelectFormatter(downloadOptions, 'download_source')
                }, {
                    title: 'Upload',
                    field: 'upload_source',
                    minWidth: 180,
                    width: 220,
                    hozAlign: 'center',
                    headerSort: true,
                    headerTooltip: 'Sheet or API Called Upload',
                    formatter: sourceSelectFormatter(uploadOptions, 'upload_source')
                }];
            }

            const saveBtn = document.getElementById('avs-sheet-modal-save');
            if (saveBtn) {
                saveBtn.addEventListener('click', commitSheetLinkFromModal);
            }
            const inputEl = document.getElementById('avs-sheet-link-input');
            if (inputEl) {
                inputEl.addEventListener('keydown', function(ev) {
                    if (ev.key === 'Enter') {
                        ev.preventDefault();
                        commitSheetLinkFromModal();
                    }
                });
            }

            const yesEl = document.getElementById('avs-price-api-yes');
            const noEl = document.getElementById('avs-price-api-no');
            if (yesEl) {
                yesEl.addEventListener('change', function() {
                    if (yesEl.checked && noEl) {
                        noEl.checked = false;
                    }
                    syncPriceApiSheetVisibility();
                });
            }
            if (noEl) {
                noEl.addEventListener('change', function() {
                    if (noEl.checked && yesEl) {
                        yesEl.checked = false;
                    }
                    syncPriceApiSheetVisibility();
                });
            }
            const priceApiSaveBtn = document.getElementById('avs-price-api-modal-save');
            if (priceApiSaveBtn) {
                priceApiSaveBtn.addEventListener('click', commitPriceApi2wFromModal);
            }
            const priceApiSheetInput = document.getElementById('avs-price-api-sheet-input');
            if (priceApiSheetInput) {
                priceApiSheetInput.addEventListener('keydown', function(ev) {
                    if (ev.key === 'Enter') {
                        ev.preventDefault();
                        commitPriceApi2wFromModal();
                    }
                });
            }

            api(urlData).then(function(rows) {
                table = new Tabulator('#api-vs-sheet-tabulator', {
                    layout: 'fitColumns',
                    responsiveLayout: false,
                    data: rows || [],
                    pagination: true,
                    paginationSize: 100,
                    paginationMode: 'local',
                    height: 600,
                    placeholder: 'No active marketplaces in Channel Master',
                    index: 'id',
                    columnDefaults: {
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        vertAlign: 'middle'
                    },
                    initialSort: [{
                        column: 'channel',
                        dir: 'asc'
                    }],
                    columns: buildTableColumns()
                });
            }).catch(function() {
                el.innerHTML = '<div class="p-4 text-center text-danger">Failed to load API Vs Sheet data.</div>';
            });
        })();
    </script>
@endsection
