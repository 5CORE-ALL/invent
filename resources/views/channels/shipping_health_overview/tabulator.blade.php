@extends('layouts.vertical', ['title' => 'Shipping Health'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        #shipping-health-overview-tabulator .tabulator .tabulator-header .tabulator-col .tabulator-col-content {
            text-align: center;
        }

        #shipping-health-overview-tabulator .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            font-weight: 600;
            text-align: center;
            width: 100%;
        }

        #shipping-health-overview-tabulator .tabulator .tabulator-cell {
            text-align: center;
        }

        #shipping-health-overview-tabulator .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #2563eb;
            color: #fff;
        }

        #shipping-health-overview-tabulator .tabulator .tabulator-tableholder .tabulator-frozen {
            z-index: 2;
        }

        #shipping-health-overview-tabulator .shov-channel-logo {
            max-width: 36px;
            max-height: 36px;
            object-fit: contain;
            display: inline-block;
        }

        #shipping-health-overview-tabulator .shov-channel-logo-placeholder {
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

        #shipping-health-overview-tabulator .shov-link-arrow {
            width: 28px;
            height: 28px;
            object-fit: contain;
            display: inline-block;
            vertical-align: middle;
        }

        #shipping-health-overview-tabulator .shov-link-arrow-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            background: transparent;
            padding: 0;
            cursor: pointer;
            line-height: 1;
        }

        #shipping-health-overview-tabulator .shov-action-btn {
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

        #shipping-health-overview-tabulator .shov-action-btn:hover {
            background: #2563eb;
            color: #fff;
        }

        #shipping-health-overview-tabulator .shov-history {
            font-weight: 600;
            font-size: 0.85rem;
            white-space: nowrap;
        }

        #shipping-health-overview-tabulator .shov-history.fresh {
            color: #16a34a;
        }

        #shipping-health-overview-tabulator .shov-history.stale {
            color: #dc2626;
        }

        #shipping-health-overview-tabulator .shov-history.empty {
            color: #9ca3af;
            font-weight: 400;
        }

        #shipping-health-overview-tabulator .shov-param {
            font-weight: 600;
        }

        #shipping-health-overview-tabulator .shov-param.empty {
            color: #9ca3af;
            font-weight: 400;
        }

        #shipping-health-overview-tabulator .shov-param.required {
            color: #374151;
        }

        /* Current vs Required:
           - below required → red
           - at/above required but below halfway to 100% → dark mustard
           - otherwise → green */
        #shipping-health-overview-tabulator .shov-param.current.red {
            color: #dc2626;
        }

        #shipping-health-overview-tabulator .shov-param.current.yellow {
            color: #a67c00;
        }

        #shipping-health-overview-tabulator .shov-param.current.green {
            color: #16a34a;
        }

        .shov-pie-layout {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            flex-wrap: nowrap;
        }

        .shov-pie-wrap {
            width: 80px;
            max-width: 80px;
            flex: 0 0 80px;
            position: relative;
        }

        .shov-pie-wrap canvas {
            cursor: pointer;
            width: 80px !important;
            height: 80px !important;
        }

        .shov-pie-side {
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 96px;
        }

        .shov-pie-filter-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
        }

        .shov-pie-legend-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .shov-legend-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 0;
            background: transparent;
            padding: 2px 6px;
            border-radius: 6px;
            cursor: pointer;
            color: inherit;
            font-size: 0.8rem;
            white-space: nowrap;
            justify-content: flex-start;
        }

        .shov-legend-btn:hover {
            background: #f3f4f6;
        }

        .shov-pie-hint {
            font-size: 0.7rem;
            color: #9ca3af;
            margin: 6px 0 0;
            text-align: center;
            line-height: 1.3;
        }

        #shipping-health-overview-tabulator .shov-text-cell {
            display: block;
            max-width: 220px;
            margin: 0 auto;
            white-space: pre-wrap;
            word-break: break-word;
            font-size: 0.85rem;
            line-height: 1.35;
            text-align: center;
        }

        #shipping-health-overview-tabulator .shov-text-cell.empty {
            color: #9ca3af;
            text-align: center;
        }

        /* Status history chart modal — full width */
        #shovStatusHistoryModal.modal {
            --tz-modal-width: 100%;
            --tz-modal-margin: 0.5rem 0;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        #shovStatusHistoryModal .modal-dialog {
            width: 100% !important;
            max-width: none !important;
            margin: 0.5rem 0 0 0 !important;
        }
        #shovStatusHistoryModal .modal-content {
            border-radius: 0;
            width: 100%;
            max-width: 100%;
        }
    </style>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <h4 class="page-title mb-0">Shipping Health</h4>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-auto">
            <div class="card shadow-sm">
                <div class="card-body py-2 px-3">
                    <div class="d-flex justify-content-end mb-1">
                        <span id="shov-pie-filter-badge" class="shov-pie-filter-badge text-muted small d-none">
                            <span>Filter: <strong id="shov-pie-filter-label">—</strong></span>
                            <button type="button" class="btn btn-link btn-sm p-0" id="shov-pie-clear-filter">Show all</button>
                        </span>
                    </div>
                    <div class="shov-pie-layout">
                        <div class="shov-pie-side">
                            <button type="button" class="shov-legend-btn" data-tone="red"
                                title="View last 60 days history">
                                <span class="shov-pie-legend-dot" style="background:#dc2626;"></span>
                                Red <strong id="shov-count-red">0</strong>
                            </button>
                            <button type="button" class="shov-legend-btn" data-tone="yellow"
                                title="View last 60 days history">
                                <span class="shov-pie-legend-dot" style="background:#eab308;"></span>
                                Yellow <strong id="shov-count-yellow">0</strong>
                            </button>
                            <button type="button" class="shov-legend-btn" data-tone="green"
                                title="View last 60 days history">
                                <span class="shov-pie-legend-dot" style="background:#16a34a;"></span>
                                Green <strong id="shov-count-green">0</strong>
                            </button>
                        </div>
                        <div class="shov-pie-wrap">
                            <canvas id="shov-status-pie" width="80" height="80"></canvas>
                        </div>
                    </div>
                    <p class="shov-pie-hint mb-0">Pie: filter · Labels: 60-day history</p>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade p-0" id="shovStatusHistoryModal" tabindex="-1" aria-labelledby="shovStatusHistoryModalLabel"
        aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0">
            <div class="modal-content" style="overflow: hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size: 13px;" id="shovStatusHistoryModalLabel">
                        <i class="fas fa-chart-area me-1"></i>
                        Status history — last 60 days
                    </h6>
                    <button type="button" class="btn-close btn-close-white" style="font-size: 10px;" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-2">
                    <div style="height: 20vh; position: relative;">
                        <canvas id="shov-status-history-chart"></canvas>
                    </div>
                    <p class="small text-muted text-center mb-0 mt-2 d-none" id="shov-status-history-empty">
                        No history yet. Counts are snapshotted as you use the page.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <span class="fw-semibold">Shipping Health</span>
                    <span class="small text-muted">Active channels from Channel Master</span>
                </div>
                <div class="card-body p-0">
                    <div id="shipping-health-overview-tabulator"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="shovUpdateModal" tabindex="-1" aria-labelledby="shovUpdateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title fw-semibold mb-0" id="shovUpdateModalLabel">Update</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="small text-muted mb-2">
                        <span id="shov-update-modal-channel">—</span>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1" for="shov-update-required">Required Parameter (min %)</label>
                        <input type="number" step="0.01" min="0" max="100"
                            class="form-control form-control-sm" id="shov-update-required"
                            placeholder="e.g. 99" autocomplete="off">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1" for="shov-update-current">Current Parameters (%)</label>
                        <input type="number" step="0.01" min="0" max="100"
                            class="form-control form-control-sm" id="shov-update-current"
                            placeholder="e.g. 97.5" autocomplete="off">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1" for="shov-update-link">Link URL</label>
                        <input type="url" class="form-control form-control-sm" id="shov-update-link"
                            placeholder="https://…" autocomplete="off">
                        <div class="small text-muted mt-1">Leave blank to clear the link.</div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1" for="shov-update-summary-issues">Summary / Issues</label>
                        <textarea class="form-control form-control-sm" id="shov-update-summary-issues" rows="3"
                            placeholder="Summarize the issues…"></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1" for="shov-update-root-cause">Root Cause found</label>
                        <textarea class="form-control form-control-sm" id="shov-update-root-cause" rows="3"
                            placeholder="Describe the root cause…"></textarea>
                    </div>
                    <div class="mb-1">
                        <label class="form-label small mb-1" for="shov-update-action-fix">Action to fix root cause</label>
                        <textarea class="form-control form-control-sm" id="shov-update-action-fix" rows="3"
                            placeholder="Describe the action to fix…"></textarea>
                    </div>
                    <div class="small text-danger mt-2 d-none" id="shov-update-modal-error"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" id="shov-update-modal-save">Save</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        (function() {
            const el = document.getElementById('shipping-health-overview-tabulator');
            if (!el) {
                return;
            }

            const urlData = @json(route('shipping.health.overview.tabulator.data'));
            const urlLinkSave = @json(route('shipping.health.overview.link.save'));
            const urlHistory = @json(route('shipping.health.overview.history'));
            const linkArrowSrc = @json(asset('images/cute-blue-cursor.png'));
            const initialToneFilter = new URLSearchParams(window.location.search).get('tone');

            let table = null;
            let editChannelId = null;
            let pieChart = null;
            let statusHistoryChart = null;
            let activeToneFilter = null;
            const PIE_COLORS = {
                red: '#dc2626',
                yellow: '#eab308',
                green: '#16a34a'
            };

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
                    .replace(/</g, '&lt;');
            }

            function formatPct(value) {
                if (value === null || value === undefined || value === '') {
                    return null;
                }
                const n = Number(value);
                if (!isFinite(n)) {
                    return null;
                }
                return Number.isInteger(n) ? String(n) : n.toFixed(2).replace(/\.?0+$/, '');
            }

            function channelLogoFormatter(cell) {
                const row = cell.getRow();
                const data = row ? row.getData() : {};
                const logo = data && data.logo ? String(data.logo).trim() : '';
                const channel = data && data.channel ? String(data.channel) : '';
                if (!logo) {
                    return '<span class="shov-channel-logo-placeholder" title="No logo">—</span>';
                }
                return '<img src="/storage/' + escAttr(logo) + '" alt="' + escAttr(channel) +
                    '" class="shov-channel-logo" onerror="this.outerHTML=\'<span class=&quot;shov-channel-logo-placeholder&quot; title=&quot;No logo&quot;>—</span>\'" />';
            }

            function requiredParameterFormatter(cell) {
                const span = document.createElement('span');
                const pct = formatPct(cell.getValue());
                if (pct === null) {
                    span.className = 'shov-param empty';
                    span.textContent = '—';
                    return span;
                }
                span.className = 'shov-param required';
                span.textContent = 'Min ' + pct + '%';
                span.title = 'Required minimum: ' + pct + '%';
                return span;
            }

            function currentParameterTone(current, required) {
                const cur = Number(current);
                const req = Number(required);
                if (!isFinite(cur) || !isFinite(req)) {
                    return '';
                }
                if (cur < req) {
                    return 'red';
                }
                const halfOfDifference = req + ((100 - req) * 0.5);
                if (cur < halfOfDifference) {
                    return 'yellow';
                }
                return 'green';
            }

            function currentParameterFormatter(cell) {
                const data = cell.getRow().getData() || {};
                const span = document.createElement('span');
                const pct = formatPct(cell.getValue());
                if (pct === null) {
                    span.className = 'shov-param empty';
                    span.textContent = '—';
                    return span;
                }
                const tone = data.status_tone || currentParameterTone(cell.getValue(), data.required_parameter);
                span.className = 'shov-param current' + (tone ? (' ' + tone) : '');
                span.textContent = pct + '%';
                if (tone === 'red') {
                    span.title = 'Below required minimum';
                } else if (tone === 'yellow') {
                    span.title = 'Meets required, but below 50% of the gap to 100%';
                } else if (tone === 'green') {
                    span.title = 'At or above 50% of the gap to 100%';
                }
                return span;
            }

            function countTones(rows) {
                const counts = {
                    red: 0,
                    yellow: 0,
                    green: 0
                };
                (rows || []).forEach(function(row) {
                    const tone = row.status_tone || currentParameterTone(row.current_parameter, row
                        .required_parameter);
                    if (tone && counts[tone] !== undefined) {
                        counts[tone]++;
                    }
                });
                return counts;
            }

            function updateCountLabels(counts) {
                const redEl = document.getElementById('shov-count-red');
                const yellowEl = document.getElementById('shov-count-yellow');
                const greenEl = document.getElementById('shov-count-green');
                if (redEl) redEl.textContent = String(counts.red || 0);
                if (yellowEl) yellowEl.textContent = String(counts.yellow || 0);
                if (greenEl) greenEl.textContent = String(counts.green || 0);
            }

            function setToneFilter(tone) {
                activeToneFilter = tone || null;
                const badge = document.getElementById('shov-pie-filter-badge');
                const label = document.getElementById('shov-pie-filter-label');
                if (!table) {
                    return;
                }
                table.clearFilter(true);
                if (activeToneFilter) {
                    table.setFilter('status_tone', '=', activeToneFilter);
                    if (badge) badge.classList.remove('d-none');
                    if (label) {
                        label.textContent = activeToneFilter.charAt(0).toUpperCase() + activeToneFilter.slice(1);
                        label.style.color = PIE_COLORS[activeToneFilter] || '#111';
                    }
                } else if (badge) {
                    badge.classList.add('d-none');
                }
            }

            function renderStatusPie(rows) {
                const canvas = document.getElementById('shov-status-pie');
                if (!canvas || typeof Chart === 'undefined') {
                    return;
                }
                const counts = countTones(rows);
                updateCountLabels(counts);
                const labels = ['Red', 'Yellow', 'Green'];
                const values = [counts.red, counts.yellow, counts.green];
                const colors = [PIE_COLORS.red, PIE_COLORS.yellow, PIE_COLORS.green];
                const tones = ['red', 'yellow', 'green'];

                if (pieChart) {
                    pieChart.data.datasets[0].data = values;
                    pieChart.update();
                    return;
                }

                pieChart = new Chart(canvas.getContext('2d'), {
                    type: 'pie',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: values,
                            backgroundColor: colors,
                            borderWidth: 1,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        const total = values.reduce(function(a, b) {
                                            return a + b;
                                        }, 0);
                                        const v = ctx.raw || 0;
                                        const pct = total ? ((v / total) * 100).toFixed(1) : '0.0';
                                        return ctx.label + ': ' + v + ' (' + pct + '%)';
                                    }
                                }
                            }
                        },
                        onClick: function(evt, elements) {
                            if (!elements || !elements.length) {
                                return;
                            }
                            const idx = elements[0].index;
                            const tone = tones[idx];
                            if (!tone) {
                                return;
                            }
                            setToneFilter(activeToneFilter === tone ? null : tone);
                        }
                    }
                });
            }

            function openStatusHistory(tone) {
                const titleEl = document.getElementById('shovStatusHistoryModalLabel');
                const emptyEl = document.getElementById('shov-status-history-empty');
                const label = tone ? (tone.charAt(0).toUpperCase() + tone.slice(1)) : 'All';
                if (titleEl) {
                    titleEl.textContent = label + ' status history — last 60 days';
                    titleEl.style.color = tone && PIE_COLORS[tone] ? PIE_COLORS[tone] : '#111827';
                }
                if (emptyEl) {
                    emptyEl.classList.add('d-none');
                }

                const modalEl = document.getElementById('shovStatusHistoryModal');
                if (modalEl && window.bootstrap && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }

                api(urlHistory + '?days=60&tone=' + encodeURIComponent(tone || '')).then(function(res) {
                    const points = (res && res.data) ? res.data : [];
                    const canvas = document.getElementById('shov-status-history-chart');
                    if (!canvas || typeof Chart === 'undefined') {
                        return;
                    }

                    const hasAny = points.some(function(p) {
                        return (p.red || 0) + (p.yellow || 0) + (p.green || 0) > 0;
                    });
                    if (emptyEl) {
                        emptyEl.classList.toggle('d-none', hasAny);
                    }

                    const labels = points.map(function(p) {
                        return p.date;
                    });
                    const datasets = [{
                        key: 'red',
                        label: 'Red',
                        color: PIE_COLORS.red
                    }, {
                        key: 'yellow',
                        label: 'Yellow',
                        color: PIE_COLORS.yellow
                    }, {
                        key: 'green',
                        label: 'Green',
                        color: PIE_COLORS.green
                    }].filter(function(ds) {
                        return !tone || ds.key === tone;
                    }).map(function(ds) {
                        return {
                            label: ds.label,
                            data: points.map(function(p) {
                                return p[ds.key] || 0;
                            }),
                            borderColor: ds.color,
                            backgroundColor: ds.color + '33',
                            tension: 0.25,
                            fill: true,
                            pointRadius: 2,
                            borderWidth: 2
                        };
                    });

                    if (statusHistoryChart) {
                        statusHistoryChart.destroy();
                        statusHistoryChart = null;
                    }

                    statusHistoryChart = new Chart(canvas.getContext('2d'), {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: datasets
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: datasets.length > 1
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        precision: 0
                                    },
                                    title: {
                                        display: true,
                                        text: 'Channel count'
                                    }
                                }
                            }
                        }
                    });
                }).catch(function(err) {
                    console.error('Status history load failed', err);
                    if (emptyEl) {
                        emptyEl.textContent = 'Failed to load history.';
                        emptyEl.classList.remove('d-none');
                    }
                });
            }

            function linkArrowFormatter(cell) {
                const data = cell.getRow().getData() || {};
                const href = data.link ? String(data.link).trim() : '';
                const img = '<img src="' + escAttr(linkArrowSrc) +
                    '" alt="Link" class="shov-link-arrow" title="Link" />';

                if (href) {
                    return '<a href="' + escAttr(href) +
                        '" target="_blank" rel="noopener noreferrer" class="shov-link-arrow-btn" title="Open link">' +
                        img + '</a>';
                }

                return '<span class="shov-link-arrow-btn" title="No link yet">' + img + '</span>';
            }

            function updateFormatter(cell) {
                const data = cell.getRow().getData() || {};
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'shov-action-btn';
                btn.title = 'Update parameters & link';
                btn.setAttribute('aria-label', 'Update');
                btn.innerHTML = '<i class="fa-solid fa-pen" aria-hidden="true"></i><span>Update</span>';
                btn.addEventListener('click', function(ev) {
                    ev.stopPropagation();
                    openUpdateModal(data);
                });
                return btn;
            }

            function historyFormatter(cell) {
                const data = cell.getRow().getData() || {};
                const span = document.createElement('span');
                if (!data.updated_at_ts || !data.history_display) {
                    span.className = 'shov-history empty';
                    span.textContent = '—';
                    return span;
                }
                span.className = 'shov-history ' + (data.is_stale ? 'stale' : 'fresh');
                span.textContent = data.history_display;
                span.title = data.is_stale ?
                    'Older than 3 days — needs update' :
                    'Updated within the last 3 days';
                return span;
            }

            function textCellFormatter(cell) {
                const span = document.createElement('span');
                const value = cell.getValue();
                const text = value ? String(value).trim() : '';
                if (!text) {
                    span.className = 'shov-text-cell empty';
                    span.textContent = '—';
                    return span;
                }
                span.className = 'shov-text-cell';
                span.textContent = text;
                span.title = text;
                return span;
            }

            function openUpdateModal(data) {
                editChannelId = data.id;
                const channelEl = document.getElementById('shov-update-modal-channel');
                const requiredEl = document.getElementById('shov-update-required');
                const currentEl = document.getElementById('shov-update-current');
                const linkEl = document.getElementById('shov-update-link');
                const summaryEl = document.getElementById('shov-update-summary-issues');
                const rootCauseEl = document.getElementById('shov-update-root-cause');
                const actionFixEl = document.getElementById('shov-update-action-fix');
                const errorEl = document.getElementById('shov-update-modal-error');

                if (channelEl) {
                    channelEl.textContent = data.channel || ('Channel #' + data.id);
                }
                if (requiredEl) {
                    requiredEl.value = data.required_parameter !== null && data.required_parameter !==
                        undefined ? data.required_parameter : '';
                }
                if (currentEl) {
                    currentEl.value = data.current_parameter !== null && data.current_parameter !==
                        undefined ? data.current_parameter : '';
                }
                if (linkEl) {
                    linkEl.value = data.link || '';
                }
                if (summaryEl) {
                    summaryEl.value = data.summary_issues || '';
                }
                if (rootCauseEl) {
                    rootCauseEl.value = data.root_cause_found || '';
                }
                if (actionFixEl) {
                    actionFixEl.value = data.action_to_fix || '';
                }
                if (errorEl) {
                    errorEl.classList.add('d-none');
                    errorEl.textContent = '';
                }

                const modalEl = document.getElementById('shovUpdateModal');
                if (modalEl && window.bootstrap && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                    setTimeout(function() {
                        if (currentEl) {
                            currentEl.focus();
                            currentEl.select();
                        }
                    }, 150);
                }
            }

            function commitUpdateFromModal() {
                const requiredEl = document.getElementById('shov-update-required');
                const currentEl = document.getElementById('shov-update-current');
                const linkEl = document.getElementById('shov-update-link');
                const summaryEl = document.getElementById('shov-update-summary-issues');
                const rootCauseEl = document.getElementById('shov-update-root-cause');
                const actionFixEl = document.getElementById('shov-update-action-fix');
                const errorEl = document.getElementById('shov-update-modal-error');
                const saveBtn = document.getElementById('shov-update-modal-save');
                if (!editChannelId || !linkEl) {
                    return;
                }

                if (errorEl) {
                    errorEl.classList.add('d-none');
                    errorEl.textContent = '';
                }
                if (saveBtn) {
                    saveBtn.disabled = true;
                }

                api(urlLinkSave, {
                    method: 'POST',
                    body: JSON.stringify({
                        channel_id: editChannelId,
                        link: String(linkEl.value || '').trim(),
                        required_parameter: requiredEl ? String(requiredEl.value || '').trim() : '',
                        current_parameter: currentEl ? String(currentEl.value || '').trim() : '',
                        summary_issues: summaryEl ? String(summaryEl.value || '').trim() : '',
                        root_cause_found: rootCauseEl ? String(rootCauseEl.value || '').trim() : '',
                        action_to_fix: actionFixEl ? String(actionFixEl.value || '').trim() : ''
                    })
                }).then(function(res) {
                    if (table) {
                        const row = table.getRow(editChannelId);
                        if (row) {
                            row.update({
                                link: res.link || null,
                                required_parameter: res.required_parameter,
                                current_parameter: res.current_parameter,
                                status_tone: res.status_tone || currentParameterTone(res
                                    .current_parameter, res.required_parameter) || null,
                                summary_issues: res.summary_issues || null,
                                root_cause_found: res.root_cause_found || null,
                                action_to_fix: res.action_to_fix || null,
                                updated_by: res.updated_by,
                                updated_at_ts: res.updated_at_ts,
                                history_display: res.history_display,
                                is_stale: res.is_stale
                            });
                        }
                        renderStatusPie(table.getData());
                        if (activeToneFilter) {
                            setToneFilter(activeToneFilter);
                        }
                    }
                    const modalEl = document.getElementById('shovUpdateModal');
                    if (modalEl && window.bootstrap && bootstrap.Modal) {
                        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                    }
                }).catch(function(err) {
                    const msg = (err && err.body && (err.body.message || (err.body.errors && Object
                        .values(err.body.errors).flat().join(' ')))) || 'Failed to save update.';
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

            function buildTableColumns() {
                return [{
                    title: 'Img',
                    field: 'logo',
                    width: 70,
                    hozAlign: 'center',
                    headerSort: false,
                    formatter: channelLogoFormatter
                }, {
                    title: 'Marketplace',
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
                    title: 'Parameter',
                    field: 'required_parameter',
                    width: 150,
                    hozAlign: 'center',
                    sorter: 'number',
                    sorterParams: {
                        alignEmptyValues: 'bottom'
                    },
                    headerTooltip: 'Minimum % points required',
                    formatter: requiredParameterFormatter
                }, {
                    title: 'Current',
                    field: 'current_parameter',
                    width: 150,
                    hozAlign: 'center',
                    sorter: 'number',
                    sorterParams: {
                        alignEmptyValues: 'bottom'
                    },
                    headerTooltip: 'Current % entered via Update',
                    formatter: currentParameterFormatter
                }, {
                    title: 'Link',
                    field: 'link',
                    width: 70,
                    hozAlign: 'center',
                    headerSort: false,
                    formatter: linkArrowFormatter
                }, {
                    title: 'Update',
                    field: '_update',
                    width: 100,
                    hozAlign: 'center',
                    headerSort: false,
                    formatter: updateFormatter
                }, {
                    title: 'History',
                    field: 'updated_at_ts',
                    width: 180,
                    hozAlign: 'center',
                    sorter: 'number',
                    sorterParams: {
                        alignEmptyValues: 'top'
                    },
                    headerSortStartingDir: 'asc',
                    headerTooltip: 'Last update user and date. Green for 3 days, then red. Oldest on top.',
                    formatter: historyFormatter
                }, {
                    title: 'Summary / Issues',
                    field: 'summary_issues',
                    minWidth: 180,
                    width: 220,
                    headerTooltip: 'Editable via Update',
                    formatter: textCellFormatter
                }, {
                    title: 'Root Cause found',
                    field: 'root_cause_found',
                    minWidth: 180,
                    width: 220,
                    headerTooltip: 'Editable via Update',
                    formatter: textCellFormatter
                }, {
                    title: 'Action to fix root cause',
                    field: 'action_to_fix',
                    minWidth: 180,
                    width: 220,
                    headerTooltip: 'Editable via Update',
                    formatter: textCellFormatter
                }];
            }

            const saveBtn = document.getElementById('shov-update-modal-save');
            if (saveBtn) {
                saveBtn.addEventListener('click', commitUpdateFromModal);
            }
            ['shov-update-required', 'shov-update-current', 'shov-update-link'].forEach(function(id) {
                const inputEl = document.getElementById(id);
                if (inputEl) {
                    inputEl.addEventListener('keydown', function(ev) {
                        if (ev.key === 'Enter') {
                            ev.preventDefault();
                            commitUpdateFromModal();
                        }
                    });
                }
            });

            const clearFilterBtn = document.getElementById('shov-pie-clear-filter');
            if (clearFilterBtn) {
                clearFilterBtn.addEventListener('click', function() {
                    setToneFilter(null);
                });
            }

            document.querySelectorAll('.shov-legend-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    openStatusHistory(btn.getAttribute('data-tone') || '');
                });
            });

            api(urlData).then(function(rows) {
                const normalized = (rows || []).map(function(row) {
                    row.status_tone = row.status_tone || currentParameterTone(row.current_parameter,
                        row.required_parameter) || null;
                    return row;
                });

                table = new Tabulator('#shipping-health-overview-tabulator', {
                    layout: 'fitColumns',
                    responsiveLayout: false,
                    data: normalized,
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
                        column: 'updated_at_ts',
                        dir: 'asc'
                    }],
                    columns: buildTableColumns()
                });

                renderStatusPie(normalized);

                if (initialToneFilter && ['red', 'yellow', 'green'].indexOf(initialToneFilter) !== -1) {
                    setToneFilter(initialToneFilter);
                }
            }).catch(function(err) {
                console.error('Shipping Health data load failed', err);
                el.innerHTML =
                    '<div class="p-3 text-danger small">Failed to load shipping health data.</div>';
            });
        })();
    </script>
@endsection
