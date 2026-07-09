@extends('layouts.vertical', ['title' => 'Amazon Ads Missing', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .amz-ads-missing .tabulator .tabulator-header .tabulator-col {
            font-size: 0.8rem;
        }
        .amz-ads-missing .tabulator-row .tabulator-cell {
            font-size: 0.82rem;
        }
        .amz-ads-missing .parent-row {
            background-color: rgba(69, 233, 255, 0.10);
        }
        .amz-ads-missing .parent-copy-btn {
            cursor: pointer;
            color: #868e96;
            margin-left: 6px;
        }
        .amz-ads-missing .parent-copy-btn:hover {
            color: #1971c2;
        }
        .amz-ads-missing .amz-missing-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 1 1 0;
            gap: 0.7rem;
            border-radius: 12px;
            padding: 0.6rem 1.3rem;
            font-size: 1.6rem;
            font-weight: 700;
            line-height: 1.2;
            white-space: nowrap;
            color: #fff;
            cursor: pointer;
        }
        .amz-ads-missing .amz-missing-badge--parent {
            background-color: #1971c2;
        }
        .amz-ads-missing .amz-missing-badge--pt,
        .amz-ads-missing .amz-missing-badge--kw {
            background-color: #dc2626;
        }
        .amz-badge-panel {
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
        .amz-badge-panel.d-none {
            display: none !important;
        }
        .amz-badge-panel .amz-badge-panel-title {
            font-weight: 700;
            font-size: 0.85rem;
            margin-bottom: 6px;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 4px;
        }
        .amz-badge-panel .amz-badge-panel-list {
            overflow-y: auto;
        }
        .amz-badge-panel .amz-badge-panel-item {
            font-size: 0.78rem;
            padding: 2px 2px;
            border-bottom: 1px dashed #f1f3f5;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .amz-badge-panel .amz-badge-panel-empty {
            color: #868e96;
            font-size: 0.78rem;
        }
        .amz-ads-missing .link-chip {
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
        .amz-ads-missing .link-chip .chip-x {
            cursor: pointer;
            color: #e03131;
        }
        .amz-ads-missing .link-add-btn {
            border: 1px solid #adb5bd;
            background: #fff;
            border-radius: 6px;
            padding: 0 6px;
            line-height: 1.4;
            cursor: pointer;
            color: #2f9e44;
        }
        .amz-ads-missing .link-add-btn:hover {
            background: #f1f3f5;
        }
        .amz-campaign-picker {
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
        .amz-campaign-picker.d-none {
            display: none !important;
        }
        .amz-campaign-picker .amz-picker-list {
            overflow-y: auto;
            margin-top: 6px;
        }
        .amz-campaign-picker .amz-picker-option {
            padding: 4px 6px;
            font-size: 0.78rem;
            cursor: pointer;
            border-radius: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .amz-campaign-picker .amz-picker-option:hover {
            background: #e7f5ff;
        }
        .amz-campaign-picker .amz-picker-empty {
            padding: 6px;
            color: #868e96;
            font-size: 0.78rem;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', ['sub_title' => 'Amazon Ads', 'page_title' => 'Amazon Ads Missing'])

    <div class="row amz-ads-missing">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-2">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <div class="amz-missing-badge amz-missing-badge--parent" id="amzParentWrap" title="Parent: total number of parent rows.">
                            <span class="amz-missing-badge-label">Parent</span>
                            <span class="amz-missing-badge-value tabular-nums" id="amzParentValue">0</span>
                        </div>
                        <div class="amz-missing-badge amz-missing-badge--pt" id="amzMissingPtWrap" title="Missing PT: in-stock rows (inventory > 0) in the current view with no linked PT campaign.">
                            <span class="amz-missing-badge-label">Missing PT</span>
                            <span class="amz-missing-badge-value tabular-nums" id="amzMissingPtValue">0</span>
                        </div>
                        <div class="amz-missing-badge amz-missing-badge--kw" id="amzMissingKwWrap" title="Missing KW: in-stock rows (inventory > 0) in the current view with no linked KW campaign.">
                            <span class="amz-missing-badge-label">Missing KW</span>
                            <span class="amz-missing-badge-value tabular-nums" id="amzMissingKwValue">0</span>
                        </div>
                    </div>
                    <div id="amzAdsMissingTable"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        (function () {
            var missingDataUrl = @json(route('amazon.ads.missing.data'));
            var campaignsUrl = @json(route('amazon.ads.missing.campaigns'));
            var linkUrl = @json(route('amazon.ads.missing.link'));
            var unlinkUrl = @json(route('amazon.ads.missing.unlink'));
            var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

            function esc(str) {
                return String(str == null ? '' : str)
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }

            function onlyParentsFilter(data) {
                return data.is_parent === true;
            }

            // Sort PT/KW columns by number of linked campaigns (blank rows first when ascending).
            function linkCountSorter(a, b) {
                var la = Array.isArray(a) ? a.length : 0;
                var lb = Array.isArray(b) ? b.length : 0;
                return la - lb;
            }

            // Header filter for Inventory: All / In Stock (>0) / Zero Inv (<=0).
            function inventoryHeaderFilter(headerValue, rowValue) {
                var inv = Number(rowValue) || 0;
                if (headerValue === 'in') {
                    return inv > 0;
                }
                if (headerValue === 'zero') {
                    return inv <= 0;
                }
                return true;
            }

            // Header filter for PT/KW: All / Missing (blank) / Linked.
            function missingHeaderFilter(headerValue, rowValue) {
                var len = Array.isArray(rowValue) ? rowValue.length : 0;
                if (headerValue === 'missing') {
                    return len === 0;
                }
                if (headerValue === 'linked') {
                    return len > 0;
                }
                return true;
            }

            function chipsFormatter(type) {
                return function (cell) {
                    var d = cell.getData();
                    var list = (type === 'PT' ? d.pt : d.kw) || [];
                    var chips = list.map(function (c) {
                        return '<span class="link-chip" title="' + esc(c.campaign_name) + '">'
                            + esc(c.campaign_name)
                            + ' <i class="fa fa-times chip-x" data-id="' + c.id + '" data-sku="' + esc(d.sku) + '"></i></span>';
                    }).join('');
                    return chips
                        + '<button type="button" class="link-add-btn" data-sku="' + esc(d.sku) + '" data-type="' + type + '" title="Link the selected campaign as ' + type + '"><i class="fa fa-plus"></i></button>';
                };
            }

            function postForm(url, payload) {
                return fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(payload)
                }).then(function (res) { return res.json().then(function (b) { return { ok: res.ok, body: b }; }); });
            }

            function buildTable(campaignNames) {
                var table = new Tabulator('#amzAdsMissingTable', {
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
                            title: 'Inventory', field: 'inventory', width: 130,
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
                            title: 'Campaign KW', field: 'kw', widthGrow: 2,
                            hozAlign: 'center', headerHozAlign: 'center',
                            headerSort: true, sorter: linkCountSorter,
                            headerFilter: 'list',
                            headerFilterParams: { values: { '': 'All', missing: 'Missing', linked: 'Linked' } },
                            headerFilterFunc: missingHeaderFilter,
                            formatter: chipsFormatter('KW')
                        },
                        {
                            title: 'Campaign PT', field: 'pt', widthGrow: 2,
                            hozAlign: 'center', headerHozAlign: 'center',
                            headerSort: true, sorter: linkCountSorter,
                            headerFilter: 'list',
                            headerFilterParams: { values: { '': 'All', missing: 'Missing', linked: 'Linked' } },
                            headerFilterFunc: missingHeaderFilter,
                            formatter: chipsFormatter('PT')
                        }
                    ]
                });

                table.on('tableBuilt', function () {
                    table.setFilter(onlyParentsFilter);
                });

                // Count blank PT / KW cells across the current (filtered) view.
                function updateMissingBadges(rowsData) {
                    var rows = rowsData || table.getData('active');
                    var pt = 0;
                    var kw = 0;
                    rows.forEach(function (r) {
                        // Skip parents with no inventory — they shouldn't inflate the missing count.
                        if (!r || (Number(r.inventory) || 0) <= 0) { return; }
                        if (!r.pt || !r.pt.length) { pt++; }
                        if (!r.kw || !r.kw.length) { kw++; }
                    });
                    var ptEl = document.getElementById('amzMissingPtValue');
                    var kwEl = document.getElementById('amzMissingKwValue');
                    if (ptEl) { ptEl.textContent = Number(pt).toLocaleString('en-US'); }
                    if (kwEl) { kwEl.textContent = Number(kw).toLocaleString('en-US'); }
                }
                // Total parent rows (whole dataset, independent of filters).
                function updateParentBadge() {
                    var all = table.getData();
                    var parents = all.reduce(function (n, r) { return n + (r && r.is_parent ? 1 : 0); }, 0);
                    var el = document.getElementById('amzParentValue');
                    if (el) { el.textContent = Number(parents).toLocaleString('en-US'); }
                }

                // dataFiltered gives the filtered RowComponents reliably (fires on initial filter, toggle, and header filters).
                table.on('dataFiltered', function (filters, rows) {
                    updateMissingBadges((rows || []).map(function (rc) { return rc.getData(); }));
                    updateParentBadge();
                });

                // Clicking a badge shows the list of parents behind its count.
                var badgePanel = document.createElement('div');
                badgePanel.className = 'amz-badge-panel d-none';
                badgePanel.innerHTML = '<div class="amz-badge-panel-title"></div><div class="amz-badge-panel-list"></div>';
                document.body.appendChild(badgePanel);
                var badgePanelTitle = badgePanel.querySelector('.amz-badge-panel-title');
                var badgePanelList = badgePanel.querySelector('.amz-badge-panel-list');

                function openBadgePanel(anchorEl, title, names) {
                    badgePanelTitle.textContent = title + ' (' + names.length + ')';
                    badgePanelList.innerHTML = names.length
                        ? names.map(function (n) { return '<div class="amz-badge-panel-item" title="' + esc(n) + '">' + esc(n) + '</div>'; }).join('')
                        : '<div class="amz-badge-panel-empty">Nothing to show</div>';
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
                bindBadge('amzParentWrap', 'Parents', function () {
                    return parentNamesFrom(table.getData(), function (r) { return r.is_parent; });
                });
                bindBadge('amzMissingPtWrap', 'Missing PT', function () {
                    return parentNamesFrom(table.getData('active'), function (r) { return (Number(r.inventory) || 0) > 0 && (!r.pt || !r.pt.length); });
                });
                bindBadge('amzMissingKwWrap', 'Missing KW', function () {
                    return parentNamesFrom(table.getData('active'), function (r) { return (Number(r.inventory) || 0) > 0 && (!r.kw || !r.kw.length); });
                });

                document.addEventListener('click', function (e) {
                    if (badgePanel.classList.contains('d-none')) { return; }
                    if (badgePanel.contains(e.target) || e.target.closest('.amz-missing-badge')) { return; }
                    badgePanel.classList.add('d-none');
                });
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') { badgePanel.classList.add('d-none'); }
                });

                var tableEl = document.getElementById('amzAdsMissingTable');

                // Floating campaign picker: "+" opens it, matches against the SP campaign list,
                // and clicking an option saves the link.
                var picker = document.createElement('div');
                picker.className = 'amz-campaign-picker d-none';
                picker.innerHTML = '<input type="text" class="form-control form-control-sm amz-picker-input" placeholder="Search campaign...">'
                    + '<div class="amz-picker-list"></div>';
                document.body.appendChild(picker);
                var pickerInput = picker.querySelector('.amz-picker-input');
                var pickerList = picker.querySelector('.amz-picker-list');
                var pickerCtx = { sku: null, type: null };

                function renderPickerList(filter) {
                    var f = (filter || '').toLowerCase();
                    var matches = campaignNames.filter(function (n) {
                        return n && (!f || String(n).toLowerCase().indexOf(f) !== -1);
                    }).slice(0, 100);
                    if (!matches.length) {
                        pickerList.innerHTML = '<div class="amz-picker-empty">No matching campaigns</div>';
                        return;
                    }
                    pickerList.innerHTML = matches.map(function (n) {
                        return '<div class="amz-picker-option" data-name="' + esc(n) + '" title="' + esc(n) + '">' + esc(n) + '</div>';
                    }).join('');
                }

                function openPicker(btn, sku, type) {
                    pickerCtx.sku = sku;
                    pickerCtx.type = type;
                    var rect = btn.getBoundingClientRect();
                    picker.style.top = (window.scrollY + rect.bottom + 2) + 'px';
                    picker.style.left = (window.scrollX + rect.left) + 'px';
                    picker.classList.remove('d-none');
                    pickerInput.value = '';
                    renderPickerList('');
                    pickerInput.focus();
                }

                function closePicker() {
                    picker.classList.add('d-none');
                    pickerCtx.sku = null;
                    pickerCtx.type = null;
                }

                pickerInput.addEventListener('input', function () {
                    renderPickerList(this.value);
                });

                pickerList.addEventListener('click', function (e) {
                    var opt = e.target.closest('.amz-picker-option');
                    if (!opt) {
                        return;
                    }
                    var name = opt.getAttribute('data-name');
                    var sku = pickerCtx.sku;
                    var type = pickerCtx.type;
                    closePicker();
                    postForm(linkUrl, { sku: sku, type: type, campaign_name: name }).then(function (out) {
                        var r = table.getRow(sku);
                        if (out.ok && out.body && r) {
                            r.update({ pt: out.body.pt, kw: out.body.kw });
                            updateMissingBadges();
                        } else if (!out.ok) {
                            window.alert('Failed to link campaign.');
                        }
                    });
                });

                document.addEventListener('click', function (e) {
                    if (picker.classList.contains('d-none')) {
                        return;
                    }
                    if (picker.contains(e.target) || e.target.closest('.link-add-btn')) {
                        return;
                    }
                    closePicker();
                });
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') {
                        closePicker();
                    }
                });

                tableEl.addEventListener('click', function (e) {
                    // Copy icon — copy the parent name to the clipboard.
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

                    // "+" — open the campaign picker for this row + type.
                    var addBtn = e.target.closest('.link-add-btn');
                    if (addBtn) {
                        openPicker(addBtn, addBtn.getAttribute('data-sku'), addBtn.getAttribute('data-type'));
                        return;
                    }

                    // "x" — remove a linked campaign.
                    var x = e.target.closest('.chip-x');
                    if (x) {
                        var id = x.getAttribute('data-id');
                        var sku2 = x.getAttribute('data-sku');
                        postForm(unlinkUrl, { id: Number(id) }).then(function (out) {
                            if (out.ok && out.body) {
                                var r = table.getRow(sku2);
                                if (r) {
                                    r.update({ pt: out.body.pt, kw: out.body.kw });
                                }
                                updateMissingBadges();
                            }
                        });
                    }
                });
            }

            // Load the SP campaign list first (for the picker), then build the grid.
            fetch(campaignsUrl, { headers: { 'Accept': 'application/json' } })
                .then(function (res) { return res.json(); })
                .then(function (json) {
                    var names = (json && Array.isArray(json.data)) ? json.data.map(function (c) { return c.campaign_name; }) : [];
                    buildTable(names);
                })
                .catch(function () { buildTable([]); });
        })();
    </script>
@endsection
