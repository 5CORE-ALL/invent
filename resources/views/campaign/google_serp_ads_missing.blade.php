@extends('layouts.vertical', ['title' => 'Missing Google SERP Ads', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .gs-serp-ads-missing .tabulator .tabulator-header .tabulator-col {
            font-size: 0.8rem;
        }
        .gs-serp-ads-missing .tabulator-row .tabulator-cell {
            font-size: 0.82rem;
        }
        .gs-serp-ads-missing .parent-row {
            background-color: #fffef2;
        }
        .gs-serp-ads-missing .parent-copy-btn {
            cursor: pointer;
            color: #868e96;
            margin-left: 6px;
        }
        .gs-serp-ads-missing .parent-copy-btn:hover {
            color: #1971c2;
        }
        .gs-serp-ads-missing .gs-serp-missing-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            border-radius: 6px;
            padding: 0.3rem 0.6rem;
            font-size: 0.8rem;
            font-weight: 600;
            line-height: 1.2;
            white-space: nowrap;
            color: #fff;
            cursor: pointer;
        }
        .gs-serp-ads-missing .gs-serp-missing-badge--parent {
            background-color: #1971c2;
        }
        .gs-serp-ads-missing .gs-serp-missing-badge--missing {
            background-color: #868e96;
        }
        .gs-serp-ads-missing .gs-serp-missing-badge--missing.is-alert {
            background-color: #dc2626;
        }
        .gs-serp-badge-panel {
            position: absolute;
            z-index: 2000;
            width: 320px;
            max-height: 320px;
            background: #fff;
            border: 1px solid #ced4da;
            border-radius: 6px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.18);
            padding: 8px 10px;
            display: flex;
            flex-direction: column;
        }
        .gs-serp-badge-panel.d-none {
            display: none !important;
        }
        .gs-serp-badge-panel .gs-serp-badge-panel-title {
            font-weight: 700;
            font-size: 0.85rem;
            margin-bottom: 6px;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 4px;
        }
        .gs-serp-badge-panel .gs-serp-badge-panel-list {
            overflow-y: auto;
        }
        .gs-serp-badge-panel .gs-serp-badge-panel-item {
            font-size: 0.78rem;
            padding: 2px 2px;
            border-bottom: 1px dashed #f1f3f5;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .gs-serp-badge-panel .gs-serp-badge-panel-empty {
            color: #868e96;
            font-size: 0.78rem;
        }
        .gs-serp-ads-missing .link-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #e7f5ff;
            color: #1971c2;
            border: 1px solid #a5d8ff;
            border-radius: 10px;
            padding: 1px 7px;
            margin: 1px 2px;
            font-size: 0.72rem;
            white-space: nowrap;
        }
        .gs-serp-ads-missing .campaign-dot {
            display: inline-block;
            width: 9px;
            height: 9px;
            border-radius: 50%;
            border: 1px solid rgba(0, 0, 0, 0.15);
            flex: 0 0 auto;
        }
        .gs-serp-ads-missing .campaign-dot-green {
            background-color: #16a34a;
        }
        .gs-serp-ads-missing .campaign-dot-red {
            background-color: #dc2626;
        }
        .gs-serp-ads-missing .link-chip .chip-x {
            cursor: pointer;
            color: #e03131;
        }
        .gs-serp-ads-missing .link-add-btn {
            border: 1px solid #adb5bd;
            background: #fff;
            border-radius: 6px;
            padding: 0 6px;
            line-height: 1.4;
            cursor: pointer;
            color: #2f9e44;
        }
        .gs-serp-ads-missing .link-add-btn:hover { background: #f1f3f5; }
        .gs-serp-campaign-picker {
            position: absolute;
            z-index: 2000;
            width: 300px;
            max-height: 280px;
            background: #fff;
            border: 1px solid #ced4da;
            border-radius: 6px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.18);
            padding: 6px;
            display: flex;
            flex-direction: column;
        }
        .gs-serp-campaign-picker.d-none { display: none !important; }
        .gs-serp-campaign-picker .gs-serp-picker-list { overflow-y: auto; margin-top: 6px; }
        .gs-serp-campaign-picker .gs-serp-picker-option {
            padding: 4px 6px;
            font-size: 0.78rem;
            cursor: pointer;
            border-radius: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .gs-serp-campaign-picker .gs-serp-picker-option:hover { background: #e7f5ff; }
        .gs-serp-campaign-picker .gs-serp-picker-empty {
            padding: 6px;
            color: #868e96;
            font-size: 0.78rem;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', ['sub_title' => 'Google Ads', 'page_title' => 'Missing Google SERP Ads'])

    <div class="row gs-serp-ads-missing">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-2">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <div class="gs-serp-missing-badge gs-serp-missing-badge--parent" id="gsSerpParentWrap" title="Parent: total number of parent rows.">
                            <span class="gs-serp-missing-badge-label">Parent</span>
                            <span class="gs-serp-missing-badge-value tabular-nums" id="gsSerpParentValue">0</span>
                        </div>
                        <div class="gs-serp-missing-badge gs-serp-missing-badge--missing" id="gsSerpMissingWrap" title="Missing: in-stock parents (Inv &gt; 0) in the current view with no linked Google SERP campaign.">
                            <span class="gs-serp-missing-badge-label">Missing</span>
                            <span class="gs-serp-missing-badge-value tabular-nums" id="gsSerpMissingValue">0</span>
                        </div>
                        <button type="button" class="btn btn-success btn-sm ms-auto" id="gsSerpMissingExportBtn" title="Export the current (filtered) view to CSV">
                            <i class="fa fa-download me-1"></i> Export
                        </button>
                    </div>
                    <div id="gsSerpAdsMissingTable"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        (function () {
            var missingDataUrl = @json(route('google.serp.ads.missing.data'));
            var campaignsUrl = @json(route('google.serp.ads.missing.campaigns'));
            var linkUrl = @json(route('google.serp.ads.missing.link'));
            var unlinkUrl = @json(route('google.serp.ads.missing.unlink'));
            var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

            function esc(str) {
                return String(str == null ? '' : str)
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }

            function onlyParentsFilter(data) {
                return data.is_parent === true;
            }

            function linkCountSorter(a, b) {
                var la = Array.isArray(a) ? a.length : 0;
                var lb = Array.isArray(b) ? b.length : 0;
                return la - lb;
            }

            function inventoryHeaderFilter(headerValue, rowValue) {
                var inv = Number(rowValue) || 0;
                if (headerValue === 'in') { return inv > 0; }
                if (headerValue === 'zero') { return inv <= 0; }
                return true;
            }

            function missingHeaderFilter(headerValue, rowValue) {
                var len = Array.isArray(rowValue) ? rowValue.length : 0;
                if (headerValue === 'missing') { return len === 0; }
                if (headerValue === 'linked') { return len > 0; }
                return true;
            }

            function linkNamesAccessor(value) {
                if (!Array.isArray(value)) { return ''; }
                return value.map(function (c) { return c && c.campaign_name ? c.campaign_name : ''; })
                    .filter(function (n) { return n !== ''; })
                    .join(', ');
            }

            function statusDot(c) {
                var dot = c && c.dot;
                if (dot !== 'green' && dot !== 'red') { return ''; }
                var status = c.status || (dot === 'green' ? 'ENABLED' : 'PAUSED');
                var title = status ? (status.charAt(0) + status.slice(1).toLowerCase()) : (dot === 'green' ? 'Enabled' : 'Paused');
                return '<span class="campaign-dot campaign-dot-' + dot + '" title="' + esc(title) + '"></span>';
            }

            function postJson(url, payload) {
                return fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(payload)
                }).then(function (res) {
                    return res.json().then(function (body) { return { ok: res.ok, body: body }; });
                });
            }

            function campaignsFormatter(cell) {
                var d = cell.getData();
                var list = cell.getValue() || [];
                var chips = (Array.isArray(list) ? list : []).map(function (c) {
                    var canRemove = c && c.source === 'manual' && c.id;
                    return '<span class="link-chip" title="' + esc(c.campaign_name) + (c.source === 'manual' ? ' (manual)' : ' (auto)') + '">'
                        + statusDot(c)
                        + esc(c.campaign_name)
                        + (canRemove
                            ? ' <i class="fa fa-times chip-x" data-id="' + c.id + '" data-sku="' + esc(d.sku) + '"></i>'
                            : '')
                        + '</span>';
                }).join('');
                return chips
                    + '<button type="button" class="link-add-btn" data-sku="' + esc(d.sku) + '" title="Link a campaign">'
                    + '<i class="fa fa-plus"></i></button>';
            }

            var campaignNames = [];
            var campaignsLoadPromise = null;

            function ensureCampaignNames() {
                if (campaignsLoadPromise) { return campaignsLoadPromise; }
                campaignsLoadPromise = fetch(campaignsUrl, { headers: { 'Accept': 'application/json' } })
                    .then(function (res) { return res.json(); })
                    .then(function (json) {
                        campaignNames = (json && Array.isArray(json.data))
                            ? json.data.map(function (c) { return c.campaign_name; })
                            : [];
                        return campaignNames;
                    })
                    .catch(function () {
                        campaignNames = [];
                        return campaignNames;
                    });
                return campaignsLoadPromise;
            }

            function buildTable() {
                var table = new Tabulator('#gsSerpAdsMissingTable', {
                    ajaxURL: missingDataUrl,
                    ajaxResponse: function (url, params, response) {
                        return (response && Array.isArray(response.data)) ? response.data : (response || []);
                    },
                    index: 'sku',
                    layout: 'fitColumns',
                    height: 'calc(100vh - 220px)',
                    pagination: true,
                    paginationSize: 100,
                    paginationSizeSelector: [25, 50, 100, 200, 500],
                    paginationCounter: 'rows',
                    initialSort: [{ column: 'parent', dir: 'asc' }],
                    rowFormatter: function (row) {
                        if (row.getData().is_parent) {
                            row.getElement().classList.add('parent-row');
                        }
                    },
                    columns: [
                        {
                            title: 'Parent', field: 'parent', headerFilter: 'input', headerFilterPlaceholder: 'Search Parent...',
                            cssClass: 'text-primary', widthGrow: 1, tooltip: true,
                            hozAlign: 'center', headerHozAlign: 'center',
                            formatter: function (cell) {
                                var v = cell.getValue() || '';
                                return '<span class="parent-name">' + esc(v) + '</span>'
                                    + ' <i class="fa fa-copy parent-copy-btn" role="button" tabindex="0" title="Copy parent name" data-parent="' + esc(v) + '"></i>';
                            }
                        },
                        {
                            title: 'Inv', field: 'inventory', width: 110,
                            hozAlign: 'right', headerHozAlign: 'right',
                            headerSort: true, sorter: 'number',
                            headerFilter: 'list',
                            headerFilterParams: { values: { '': 'All', in: 'In Stock', zero: 'Zero Inv' } },
                            headerFilterFunc: inventoryHeaderFilter,
                            formatter: function (cell) {
                                var v = cell.getValue();
                                return (v == null || v === '') ? '' : Number(v).toLocaleString('en-US');
                            }
                        },
                        {
                            title: 'Campaign', field: 'campaigns', widthGrow: 3,
                            hozAlign: 'center', headerHozAlign: 'center',
                            headerSort: true, sorter: linkCountSorter,
                            headerFilter: 'list',
                            headerFilterParams: { values: { '': 'All', missing: 'Missing', linked: 'Linked' } },
                            headerFilterFunc: missingHeaderFilter,
                            formatter: campaignsFormatter,
                            accessorDownload: linkNamesAccessor
                        }
                    ]
                });

                table.on('tableBuilt', function () {
                    table.setFilter(onlyParentsFilter);
                });

                document.getElementById('gsSerpMissingExportBtn').addEventListener('click', function () {
                    table.download('csv', 'missing-google-serp-ads-' + new Date().toISOString().slice(0, 10) + '.csv');
                });

                function updateMissingBadge(rowsData) {
                    var rows = rowsData || table.getData('active');
                    var missing = 0;
                    rows.forEach(function (r) {
                        if (!r || (Number(r.inventory) || 0) <= 0) { return; }
                        if (!r.campaigns || !r.campaigns.length) { missing++; }
                    });
                    var missingEl = document.getElementById('gsSerpMissingValue');
                    var missingWrap = document.getElementById('gsSerpMissingWrap');
                    if (missingEl) { missingEl.textContent = Number(missing).toLocaleString('en-US'); }
                    if (missingWrap) { missingWrap.classList.toggle('is-alert', missing > 0); }
                }

                function updateParentBadge() {
                    var all = table.getData();
                    var parents = all.reduce(function (n, r) { return n + (r && r.is_parent ? 1 : 0); }, 0);
                    var el = document.getElementById('gsSerpParentValue');
                    if (el) { el.textContent = Number(parents).toLocaleString('en-US'); }
                }

                table.on('dataFiltered', function (filters, rows) {
                    updateMissingBadge((rows || []).map(function (rc) { return rc.getData(); }));
                    updateParentBadge();
                });

                var badgePanel = document.createElement('div');
                badgePanel.className = 'gs-serp-badge-panel d-none';
                badgePanel.innerHTML = '<div class="gs-serp-badge-panel-title"></div><div class="gs-serp-badge-panel-list"></div>';
                document.body.appendChild(badgePanel);
                var badgePanelTitle = badgePanel.querySelector('.gs-serp-badge-panel-title');
                var badgePanelList = badgePanel.querySelector('.gs-serp-badge-panel-list');

                function openBadgePanel(anchorEl, title, names) {
                    badgePanelTitle.textContent = title + ' (' + names.length + ')';
                    badgePanelList.innerHTML = names.length
                        ? names.map(function (n) { return '<div class="gs-serp-badge-panel-item" title="' + esc(n) + '">' + esc(n) + '</div>'; }).join('')
                        : '<div class="gs-serp-badge-panel-empty">Nothing to show</div>';
                    var rect = anchorEl.getBoundingClientRect();
                    badgePanel.style.top = (window.scrollY + rect.bottom + 4) + 'px';
                    badgePanel.style.left = (window.scrollX + rect.left) + 'px';
                    badgePanel.classList.remove('d-none');
                }

                function parentNamesFrom(rows, predicate) {
                    var out = [];
                    rows.forEach(function (r) {
                        if (predicate(r)) { out.push(r.parent || r.sku || ''); }
                    });
                    return out;
                }

                function bindBadge(wrapId, titleText, getNames) {
                    var el = document.getElementById(wrapId);
                    if (!el) { return; }
                    el.addEventListener('click', function () {
                        openBadgePanel(el, titleText, getNames());
                    });
                }
                bindBadge('gsSerpParentWrap', 'Parents', function () {
                    return parentNamesFrom(table.getData(), function (r) { return r.is_parent; });
                });
                bindBadge('gsSerpMissingWrap', 'Missing', function () {
                    return parentNamesFrom(table.getData('active'), function (r) {
                        return r && (Number(r.inventory) || 0) > 0 && (!r.campaigns || !r.campaigns.length);
                    });
                });

                document.addEventListener('click', function (e) {
                    if (badgePanel.classList.contains('d-none')) { return; }
                    if (badgePanel.contains(e.target) || e.target.closest('.gs-serp-missing-badge')) { return; }
                    badgePanel.classList.add('d-none');
                });
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') { badgePanel.classList.add('d-none'); }
                });

                // Campaign picker (+ like /amazon-ads/missing)
                var picker = document.createElement('div');
                picker.className = 'gs-serp-campaign-picker d-none';
                picker.innerHTML = '<input type="text" class="form-control form-control-sm gs-serp-picker-input" placeholder="Search campaign...">'
                    + '<div class="gs-serp-picker-list"></div>';
                document.body.appendChild(picker);
                var pickerInput = picker.querySelector('.gs-serp-picker-input');
                var pickerList = picker.querySelector('.gs-serp-picker-list');
                var pickerSku = null;

                function renderPickerList(filter) {
                    var f = (filter || '').toLowerCase();
                    var matches = campaignNames.filter(function (n) {
                        return n && (!f || String(n).toLowerCase().indexOf(f) !== -1);
                    }).slice(0, 100);
                    if (!matches.length) {
                        pickerList.innerHTML = '<div class="gs-serp-picker-empty">No matching campaigns</div>';
                        return;
                    }
                    pickerList.innerHTML = matches.map(function (n) {
                        return '<div class="gs-serp-picker-option" data-name="' + esc(n) + '" title="' + esc(n) + '">' + esc(n) + '</div>';
                    }).join('');
                }

                function openPicker(btn, sku) {
                    pickerSku = sku;
                    var rect = btn.getBoundingClientRect();
                    picker.style.top = (window.scrollY + rect.bottom + 2) + 'px';
                    picker.style.left = (window.scrollX + rect.left) + 'px';
                    picker.classList.remove('d-none');
                    pickerInput.value = '';
                    pickerList.innerHTML = '<div class="gs-serp-picker-empty">Loading campaigns...</div>';
                    pickerInput.focus();
                    ensureCampaignNames().then(function () {
                        if (pickerSku !== sku) { return; }
                        renderPickerList(pickerInput.value);
                    });
                }

                function closePicker() {
                    picker.classList.add('d-none');
                    pickerSku = null;
                }

                pickerInput.addEventListener('input', function () {
                    renderPickerList(this.value);
                });

                pickerList.addEventListener('click', function (e) {
                    var opt = e.target.closest('.gs-serp-picker-option');
                    if (!opt || !pickerSku) { return; }
                    var name = opt.getAttribute('data-name');
                    var sku = pickerSku;
                    closePicker();
                    postJson(linkUrl, { sku: sku, campaign_name: name }).then(function (out) {
                        var r = table.getRow(sku);
                        if (out.ok && out.body && r) {
                            r.update({ campaigns: out.body.campaigns || [] });
                            updateMissingBadge();
                        } else if (!out.ok) {
                            window.alert((out.body && out.body.message) || 'Failed to link campaign.');
                        }
                    });
                });

                document.addEventListener('click', function (e) {
                    if (picker.classList.contains('d-none')) { return; }
                    if (picker.contains(e.target) || e.target.closest('.link-add-btn')) { return; }
                    closePicker();
                });
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') { closePicker(); }
                });

                document.getElementById('gsSerpAdsMissingTable').addEventListener('click', function (e) {
                    var copyBtn = e.target.closest('.parent-copy-btn');
                    if (copyBtn) {
                        var txt = copyBtn.getAttribute('data-parent') || '';
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(txt);
                        }
                        copyBtn.classList.remove('fa-copy');
                        copyBtn.classList.add('fa-check');
                        setTimeout(function () {
                            copyBtn.classList.remove('fa-check');
                            copyBtn.classList.add('fa-copy');
                        }, 900);
                        return;
                    }

                    var addBtn = e.target.closest('.link-add-btn');
                    if (addBtn) {
                        openPicker(addBtn, addBtn.getAttribute('data-sku'));
                        return;
                    }

                    var x = e.target.closest('.chip-x');
                    if (x) {
                        var id = Number(x.getAttribute('data-id'));
                        var sku2 = x.getAttribute('data-sku');
                        postJson(unlinkUrl, { id: id }).then(function (out) {
                            if (out.ok && out.body) {
                                var r = table.getRow(sku2);
                                if (r) {
                                    r.update({ campaigns: out.body.campaigns || [] });
                                }
                                updateMissingBadge();
                            }
                        });
                    }
                });
            }

            // Load table immediately; campaign list is fetched only when + is clicked.
            buildTable();
        })();
    </script>
@endsection
