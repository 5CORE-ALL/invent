@extends('layouts.vertical', ['title' => 'Inactive Listings', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-paginator label { margin-right: 5px; }
        .il-channel-logo {
            width: 28px;
            height: 28px;
            object-fit: contain;
            border-radius: 4px;
            background: #fff;
            border: 1px solid #e9ecef;
            padding: 1px;
            display: inline-block;
        }
        .il-channel-logo-placeholder {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 4px;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            color: #adb5bd;
            font-size: 12px;
        }
        .il-channel-link {
            color: inherit;
            font-weight: 600;
            text-decoration: none;
        }
        .il-channel-link:hover {
            color: #0d6efd;
            text-decoration: underline;
        }
        .il-inactive-count {
            font-weight: 700;
            text-decoration: none;
        }
        .il-listings-arrow {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            text-decoration: none;
            line-height: 1;
        }
        .il-listings-arrow-on { color: #0d6efd; }
        .il-listings-arrow-on:hover { color: #0a58ca; }
        .il-listings-arrow-off { color: #dc3545; cursor: default; }
        .il-api-dot {
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            box-shadow: 0 0 0 2px rgba(0,0,0,0.06);
        }
        .il-api-dot-green { background: #198754; }
        .il-api-dot-yellow { background: #ffc107; }
        .il-api-dot-red { background: #dc3545; }
        #stat-cp-inactive-listings.badge,
        .badge-il-stat {
            font-size: 1.35rem !important;
            line-height: 1.35;
            padding: 0.75rem 1.25rem !important;
            border-radius: 0.35rem !important;
            font-weight: 700;
        }
        /* TM button: same badge as the Task Summary page */
        .task-summary-tm-badge {
            flex-shrink: 0;
            width: 1.4rem;
            height: 1.4rem;
            padding: 0;
            margin: 0;
            border: none;
            border-radius: 7px;
            background: linear-gradient(135deg, #b45309, #f59e0b);
            color: #fff;
            text-decoration: none;
            font-size: 0.6rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            line-height: 1;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            vertical-align: middle;
            box-shadow: 0 1px 3px rgba(245, 158, 11, 0.45), inset 0 -1px 0 rgba(0, 0, 0, 0.08);
            transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        }
        .task-summary-tm-badge:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(245, 158, 11, 0.5), inset 0 -1px 0 rgba(0, 0, 0, 0.08);
            background: linear-gradient(135deg, #92400e, #b45309);
            color: #fff;
            text-decoration: none;
        }
        .task-summary-tm-badge:focus-visible {
            outline: 2px solid #b45309;
            outline-offset: 2px;
        }
        .il-hist-dot {
            display: inline-block;
            width: 9px;
            height: 9px;
            margin-left: 6px;
            border-radius: 50%;
            background: #6c757d;
            border: 1px solid #fff;
            box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.2);
            cursor: pointer;
            vertical-align: middle;
            padding: 0;
        }
        .il-hist-dot:hover { transform: scale(1.4); }
        #stat-cp-inactive-listings .il-hist-dot { background: #fff; border-color: rgba(15, 23, 42, 0.35); }

        /* History modal: full width, same as the Active Channel metric chart */
        #ilHistoryModal.modal {
            --tz-modal-width: 100%;
            --tz-modal-margin: 0.5rem 0;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        #ilHistoryModal .modal-dialog {
            width: 100% !important;
            max-width: none !important;
            margin: 0.5rem 0 0 0 !important;
        }
        #ilHistoryModal .modal-content {
            border-radius: 0;
            width: 100%;
            max-width: 100%;
        }
        .tabulator .tabulator-header .tabulator-col.tabulator-col-group {
            background: #eef4fb;
            text-align: center;
        }
        .tabulator .tabulator-header .tabulator-col.tabulator-col-group .tabulator-col-title {
            font-weight: 700;
            color: #6c2c2c;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Inactive Listings',
        'sub_title'  => '',
    ])

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    @php $ilInitialTotal = \App\Support\Marketplace\MappingChannelCounts::cachedCpInactiveTotalOrZero(); @endphp
                    <span class="badge text-white badge-il-stat {{ $ilInitialTotal > 0 ? 'bg-danger' : 'bg-success' }}" id="stat-cp-inactive-listings" title="In-stock CP Master SKUs that exist on the marketplace with inactive status. Missing Listing and 0 Inv SKUs are excluded.">
                        Inactive Child SKUs: <span id="total-cp-inactive-listings">{{ number_format($ilInitialTotal) }}</span>
                        <button type="button" class="il-hist-dot" data-channel="all" data-name="All Channels" title="Inactive Child SKUs history" aria-label="Inactive Child SKUs history"></button>
                    </span>
                    <button type="button" id="il-sync-btn" class="btn btn-sm btn-primary" title="Pull current marketplace listing statuses and rebuild this page">
                        <i class="fas fa-sync-alt me-1"></i> Sync
                    </button>
                    <span class="text-muted small" id="il-sync-meta"></span>
                    @include('partials.page-doc-links', ['pageKey' => 'inactive-listings'])
                    <span class="text-muted small">Inactive Listing = in-stock CP Master SKUs that are present on the marketplace with inactive status. Missing / never-listed SKUs stay on Missing Listing. Zero-inventory SKUs are excluded.</span>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="p-2 bg-light border-bottom">
                    <input type="text" id="inactive-listings-search" class="form-control form-control-sm" placeholder="Search by Channel...">
                </div>
                <div id="inactive-listings-table" style="height: calc(100vh - 280px);"></div>
            </div>
        </div>
    </div>

    <!-- Inactive Child SKUs history modal (same layout as the Active Channel metric chart) -->
    <div class="modal fade p-0" id="ilHistoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0">
            <div class="modal-content" style="overflow: hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size: 13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span id="ilHistoryTitle">Inactive Child SKUs - Rolling 30 Days</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="ilHistoryRange" class="form-select form-select-sm bg-white" style="width: 110px; height: 26px; font-size: 11px; padding: 1px 8px;">
                            <option value="7">7 Days</option>
                            <option value="30" selected>30 Days</option>
                            <option value="31">31 Days</option>
                            <option value="32">32 Days</option>
                            <option value="35">35 Days</option>
                            <option value="60">60 Days</option>
                            <option value="90">90 Days</option>
                            <option value="0">Lifetime</option>
                        </select>
                        <button type="button" class="btn-close btn-close-white" style="font-size: 10px;" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body p-2">
                    <div id="ilHistoryContainer" style="height: 34vh; min-height: 220px; display: flex; align-items: stretch;">
                        <div style="flex: 1; min-width: 0; position: relative;">
                            <canvas id="ilHistoryChart"></canvas>
                        </div>
                        <div style="width: 118px; flex-shrink: 0; display: flex; flex-direction: column; justify-content: center; gap: 8px; padding: 6px 8px; border-left: 1px solid #e9ecef; background: #f8f9fa; border-radius: 0 4px 4px 0; overflow: auto;">
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #dc3545; margin-bottom: 1px;">Highest</div>
                                <div id="ilHistoryHighest" style="font-size: 13px; font-weight: 700; color: #dc3545; white-space: nowrap;">-</div>
                            </div>
                            <div style="text-align: center; border-top: 1px dashed #adb5bd; border-bottom: 1px dashed #adb5bd; padding: 4px 0;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; margin-bottom: 1px;">Median</div>
                                <div id="ilHistoryMedian" style="font-size: 13px; font-weight: 700; color: #6c757d; white-space: nowrap;">-</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #198754; margin-bottom: 1px;">Lowest</div>
                                <div id="ilHistoryLowest" style="font-size: 13px; font-weight: 700; color: #198754; white-space: nowrap;">-</div>
                            </div>
                        </div>
                    </div>
                    <div id="ilHistoryLoading" class="text-center py-3" style="display: none;">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
                        <p class="mt-1 text-muted small mb-0">Loading chart data...</p>
                    </div>
                    <div id="ilHistoryNoData" class="text-center py-3" style="display: none;">
                        <i class="fas fa-exclamation-circle text-warning fa-2x mb-2"></i>
                        <p class="text-muted small mb-0">No history yet. A point is saved each day the page is refreshed, so the chart fills in from today.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    let table = null;
    let ilSyncPolling = false;
    const ilSyncUrl = @json(route('inactive.listings.sync'));
    const ilSyncStatusUrl = @json(route('inactive.listings.sync.status'));
    const ilCsrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    function formatIlSyncTime(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) return '';
        return d.toLocaleString();
    }

    function setIlSyncMeta(status) {
        const el = document.getElementById('il-sync-meta');
        if (!el || !status) return;
        const state = String(status.status || 'idle');
        if (state === 'running') {
            el.textContent = 'Syncing listing statuses…';
            return;
        }
        const when = formatIlSyncTime(status.finished_at || status.started_at);
        el.textContent = when ? ('Last sync: ' + when) : '';
    }

    function setIlSyncBusy(busy, label) {
        const $btn = $('#il-sync-btn');
        $btn.prop('disabled', busy);
        $btn.html(busy
            ? '<span class="spinner-border spinner-border-sm me-1"></span>' + (label || 'Syncing…')
            : '<i class="fas fa-sync-alt me-1"></i> Sync');
    }

    function pollIlSync(started) {
        if (ilSyncPolling) return;
        ilSyncPolling = true;
        setIlSyncBusy(true, 'Syncing…');
        const startedAt = started || Date.now();
        const timer = setInterval(function() {
            $.getJSON(ilSyncStatusUrl).done(function(res) {
                const status = (res && res.status) ? res.status : {};
                setIlSyncMeta(status);
                if (String(status.status || '') === 'running') {
                    return;
                }
                clearInterval(timer);
                ilSyncPolling = false;
                if (String(status.status || '') === 'failed') {
                    setIlSyncBusy(false);
                    alert(status.message || 'Sync failed.');
                    return;
                }
                window.location.reload();
            }).fail(function() {
                if (Date.now() - startedAt > 40 * 60 * 1000) {
                    clearInterval(timer);
                    ilSyncPolling = false;
                    setIlSyncBusy(false);
                    alert('Sync status check failed. Refresh the page.');
                }
            });
        }, 3000);
    }

    function startIlSync() {
        setIlSyncBusy(true, 'Starting…');
        $.ajax({
            url: ilSyncUrl,
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': ilCsrf },
            dataType: 'json'
        }).done(function(res) {
            const status = (res && res.status) ? res.status : {};
            setIlSyncMeta(status);
            pollIlSync();
        }).fail(function(xhr) {
            setIlSyncBusy(false);
            alert((xhr.responseJSON && xhr.responseJSON.message) || 'Could not start sync.');
        });
    }

    function updateSidebarInactiveCount(n) {
        const $b = $('.inactive-listings-badge');
        if (!$b.length) return;
        const v = Number(n || 0);
        $b.text(v.toLocaleString('en-US'));
        if (v > 0) {
            $b.css('display', 'inline-block');
        } else {
            $b.hide();
        }
    }

    function updateStats(rows, totals) {
        const data = rows || [];
        let cpTotal = totals && totals.cp != null && !isNaN(Number(totals.cp))
            ? Number(totals.cp)
            : data.reduce((sum, r) => sum + Number(r.cp_inactive_child || 0), 0);
        $('#total-cp-inactive-listings').text(cpTotal.toLocaleString('en-US'));
        // Red when there are inactive child SKUs, green when none.
        $('#stat-cp-inactive-listings')
            .toggleClass('bg-danger', cpTotal > 0)
            .toggleClass('bg-success', cpTotal <= 0);
        updateSidebarInactiveCount(cpTotal);
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function mmLogoSrc(logo) {
        const v = String(logo || '').trim();
        if (!v) return '';
        if (/^https?:\/\//i.test(v) || v.startsWith('/')) return v;
        return '/storage/' + v.replace(/^\/+/, '');
    }

    function skuDetailUrl(row, field) {
        const data = row || {};
        return String(data.cp_detail_url || data.detail_url || '').trim();
    }

    function formatInactiveCount(cell, field) {
        const v = Number(cell.getValue() || 0);
        const row = cell.getRow().getData();
        const url = skuDetailUrl(row, field);
        const color = v === 0 ? '#198754' : '#b45309';
        const label = v.toLocaleString('en-US');
        const slug = String(row.channel_slug || '').trim();
        const dot = (field === 'cp_inactive_child' && slug)
            ? `<button type="button" class="il-hist-dot" data-channel="${escapeHtml(slug)}" data-name="${escapeHtml(row.channel || slug)}" title="${escapeHtml(row.channel || slug)} - Inactive Child SKUs history" aria-label="History"></button>`
            : '';
        if (!url) {
            return `<span class="il-inactive-count" style="color:${color};">${label}</span>${dot}`;
        }
        const title = field === 'cp_inactive_parent'
            ? 'Open inactive parent listings'
            : 'Open inactive child SKUs';
        return `<a href="${escapeHtml(url)}" class="il-inactive-count" style="color:${color};" title="${title}">${label}</a>${dot}`;
    }

    // ---- Inactive Child SKUs history (Active Channel chart standard: lower is better) ----
    const ilTasksIndexUrl = @json(route('tasks.index'));
    const ilHistoryUrl = @json(route('inactive.listings.history'));
    let ilHistChart = null;
    let ilHistChannel = 'all';
    let ilHistName = 'All Channels';
    let ilHistReq = 0;

    function ilFmt(v) { return Math.round(Number(v) || 0).toLocaleString('en-US'); }

    function ilDotColors(values) {
        const gray = '#6c757d', green = '#28a745', red = '#dc3545';
        return values.map(function(v, i) {
            if (i === 0 || v === values[i - 1]) return gray;
            return v < values[i - 1] ? green : red; // fewer inactive SKUs = better
        });
    }

    function ilRangeLabel() {
        const sel = document.getElementById('ilHistoryRange');
        return sel.options[sel.selectedIndex].text.replace(/\s*Days$/i, 'D');
    }

    function ilSetTitle() {
        const range = document.getElementById('ilHistoryRange').value === '0' ? 'Lifetime' : ilRangeLabel();
        document.getElementById('ilHistoryTitle').textContent =
            ilHistName + ' - Inactive Child SKUs (Rolling ' + range + ')';
    }

    function openIlHistory(channel, name) {
        ilHistChannel = channel || 'all';
        ilHistName = name || 'All Channels';
        ilSetTitle();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('ilHistoryModal')).show();
        loadIlHistory();
    }

    function loadIlHistory() {
        const days = document.getElementById('ilHistoryRange').value;
        const req = ++ilHistReq;
        const box = document.getElementById('ilHistoryContainer');
        const noData = document.getElementById('ilHistoryNoData');
        const loading = document.getElementById('ilHistoryLoading');
        ilSetTitle();
        box.style.display = 'none';
        noData.style.display = 'none';
        loading.style.display = 'block';
        $.getJSON(ilHistoryUrl, { channel: ilHistChannel, metric: 'child', days: days }).done(function(res) {
            if (req !== ilHistReq) return;
            loading.style.display = 'none';
            const data = (res && res.data) ? res.data : [];
            if (!data.length) {
                noData.style.display = 'block';
                return;
            }
            box.style.display = 'flex';
            renderIlHistory(data);
        }).fail(function() {
            if (req !== ilHistReq) return;
            loading.style.display = 'none';
            noData.style.display = 'block';
        });
    }

    function renderIlHistory(data) {
        const canvas = document.getElementById('ilHistoryChart');
        const ctx = canvas.getContext('2d');
        if (ilHistChart) ilHistChart.destroy();

        const labels = data.map(d => d.date);
        const values = data.map(d => Number(d.value) || 0);
        const dataMin = Math.min(...values);
        const dataMax = Math.max(...values);
        const sorted = [...values].sort((a, b) => a - b);
        const mid = Math.floor(sorted.length / 2);
        const median = sorted.length % 2 !== 0 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
        const range = dataMax - dataMin || 1;
        const yPad = Math.max(range * 0.28, Math.abs(dataMax) * 0.08, range * 0.1);
        const yMin = Math.max(0, dataMin - range * 0.12);
        const yMax = dataMax + yPad;

        const dotColors = ilDotColors(values);
        const refGray = '#6c757d';
        let maxIdx = 0, minIdx = 0;
        values.forEach((v, i) => {
            if (v >= values[maxIdx]) maxIdx = i;
            if (v <= values[minIdx]) minIdx = i;
        });
        [['ilHistoryHighest', dataMax, dotColors[maxIdx]], ['ilHistoryMedian', median, refGray], ['ilHistoryLowest', dataMin, dotColors[minIdx]]].forEach(function(x) {
            const el = document.getElementById(x[0]);
            el.textContent = ilFmt(x[1]);
            el.style.color = x[2] || refGray;
            if (el.previousElementSibling) el.previousElementSibling.style.color = el.style.color;
        });

        const medianLinePlugin = {
            id: 'ilMedianLine',
            afterDraw(chart) {
                const y = chart.scales.y.getPixelForValue(median);
                const c = chart.ctx;
                c.save();
                c.setLineDash([6, 4]);
                c.strokeStyle = '#6c757d';
                c.lineWidth = 1.2;
                c.beginPath();
                c.moveTo(chart.scales.x.left, y);
                c.lineTo(chart.scales.x.right, y);
                c.stroke();
                c.restore();
            }
        };

        const valueLabelsPlugin = {
            id: 'ilValueLabels',
            afterDraw(chart) {
                const meta = chart.getDatasetMeta(0);
                const c = chart.ctx;
                c.save();
                c.font = 'bold 10px Inter, system-ui, sans-serif';
                c.textAlign = 'left';
                c.textBaseline = 'middle';
                meta.data.forEach(function(point, i) {
                    c.save();
                    c.fillStyle = dotColors[i];
                    c.translate(point.x, point.y + (i % 2 === 0 ? -12 : -26));
                    c.rotate(-Math.PI / 5);
                    c.fillText(ilFmt(values[i]), 2, 0);
                    c.restore();
                });
                c.restore();
            }
        };

        ctx.save();
        ctx.font = 'bold 10px Inter, system-ui, sans-serif';
        const tailW = Math.max.apply(null, values.slice(-2).map(v => ctx.measureText(ilFmt(v)).width));
        ctx.restore();
        const rightPad = Math.max(22, Math.ceil(tailW * Math.cos(Math.PI / 5)) + 12);

        ilHistChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Inactive Child SKUs',
                    data: values,
                    backgroundColor: 'rgba(108,117,125,0.08)',
                    borderColor: '#adb5bd',
                    borderWidth: 1.5,
                    fill: true,
                    tension: 0.3,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    pointBackgroundColor: dotColors,
                    pointBorderColor: dotColors,
                    pointHoverBackgroundColor: dotColors,
                    pointHoverBorderColor: dotColors,
                    pointBorderWidth: 1.5,
                    pointHoverBorderWidth: 1.5
                }]
            },
            plugins: [medianLinePlugin, valueLabelsPlugin],
            options: {
                responsive: true,
                maintainAspectRatio: false,
                clip: false,
                layout: { padding: { top: 44, left: 4, right: rightPad, bottom: 8 } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        titleFont: { size: 10 },
                        bodyFont: { size: 10 },
                        padding: 6,
                        callbacks: {
                            label: function(context) {
                                const idx = context.dataIndex;
                                const parts = ['Value: ' + ilFmt(context.raw)];
                                if (idx > 0) {
                                    const diff = context.raw - values[idx - 1];
                                    parts.push('vs Yesterday: ' + (diff < 0 ? '▼' : diff > 0 ? '▲' : '▬') + ' ' + ilFmt(Math.abs(diff)));
                                }
                                return parts;
                            }
                        }
                    }
                },
                scales: {
                    y: { min: yMin, max: yMax, ticks: { font: { size: 9 }, callback: v => ilFmt(v) } },
                    x: { ticks: { maxRotation: 60, minRotation: 60, autoSkip: false, maxTicksLimit: Math.max(labels.length, 31), font: { size: 8 } } }
                }
            }
        });
    }

    $(document).on('click', '.il-hist-dot', function(e) {
        e.preventDefault();
        e.stopPropagation();
        openIlHistory(this.getAttribute('data-channel'), this.getAttribute('data-name'));
    });
    $(document).on('change', '#ilHistoryRange', loadIlHistory);
    document.getElementById('ilHistoryModal').addEventListener('hidden.bs.modal', function() {
        if (ilHistChart) { ilHistChart.destroy(); ilHistChart = null; }
    });

    $(document).ready(function() {
        table = new Tabulator("#inactive-listings-table", {
            ajaxURL: "{{ url('/inactive-listings/channels-data') }}",
            ajaxRequestTimeout: 25000,
            ajaxRequestFunc: function(url, _config, params) {
                return new Promise(function(resolve, reject) {
                    function attempt(n) {
                        $.ajax({
                            url: url,
                            data: params || {},
                            method: 'GET',
                            dataType: 'json',
                            timeout: 25000,
                        }).done(resolve).fail(function(xhr) {
                            if (n < 2) {
                                setTimeout(function() { attempt(n + 1); }, 1200);
                                return;
                            }
                            reject(xhr);
                        });
                    }
                    attempt(0);
                });
            },
            ajaxResponse: function(_url, _params, response) {
                if (response && response.success === false) {
                    return [];
                }
                const data = (response && response.data) ? response.data : [];
                const childTotal = Number(response && (response.total_cp_inactive_child != null ? response.total_cp_inactive_child : response.total_cp_inactive));
                if (! (response && response.partial && !childTotal)) {
                    updateStats(data, { cp: childTotal });
                }
                if (response && (response.last_sync || response.sync_status)) {
                    setIlSyncMeta({
                        status: response.sync_status || 'idle',
                        finished_at: response.last_sync || null,
                        started_at: response.last_sync || null,
                        message: response.sync_message || '',
                    });
                    if (String(response.sync_status || '') === 'running') {
                        pollIlSync();
                    }
                }
                if (response && response.partial && (window.__ilCountsRetries || 0) < 16) {
                    window.__ilCountsRetries = (window.__ilCountsRetries || 0) + 1;
                    setTimeout(function() {
                        if (table) {
                            table.replaceData();
                        }
                    }, 5000);
                }
                return data;
            },
            ajaxError: function() {
                const holder = document.querySelector('#inactive-listings-table .tabulator-placeholder-contents');
                if (holder) {
                    holder.textContent = 'Could not load channels. Refresh the page.';
                }
            },
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 50,
            paginationSizeSelector: [25, 50, 100, 200, 500],
            initialSort: [{ column: "cp_inactive_child", dir: "desc" }],
            placeholder: "Loading channels…",
            columns: [
                {
                    title: "Image",
                    field: "image",
                    headerSort: false,
                    width: 90,
                    hozAlign: "center",
                    formatter: function(cell) {
                        const logo = cell.getValue();
                        const channel = (cell.getRow().getData().channel || '').trim();
                        if (!logo) {
                            return '<span class="il-channel-logo-placeholder" title="No logo"><i class="fas fa-image"></i></span>';
                        }
                        const src = mmLogoSrc(logo);
                        return `<img src="${escapeHtml(src)}" alt="${escapeHtml(channel)}" class="il-channel-logo" onerror="this.style.display='none'">`;
                    }
                },
                {
                    title: "Channel",
                    field: "channel",
                    minWidth: 240,
                    formatter: function(cell) {
                        const name = (cell.getValue() || '').trim();
                        const url = skuDetailUrl(cell.getRow().getData(), 'cp_inactive_child') || skuDetailUrl(cell.getRow().getData());
                        if (!name) return '';
                        if (!url) return escapeHtml(name);
                        return `<a href="${escapeHtml(url)}" class="il-channel-link" title="Open Inactive Listing SKUs for ${escapeHtml(name)}">${escapeHtml(name)}</a>`;
                    },
                },
                {
                    title: "API",
                    field: "api_status",
                    width: 80,
                    hozAlign: "center",
                    headerHozAlign: "center",
                    headerTooltip: "Green: API linked and inventory synced in the last 24h. Yellow: linked but last sync is older than 24h. Red: not linked.",
                    sorter: function(a, b) {
                        const rank = { green: 0, yellow: 1, red: 2 };
                        return (rank[a] ?? 9) - (rank[b] ?? 9);
                    },
                    formatter: function(cell) {
                        const row = cell.getRow().getData() || {};
                        const status = String(row.api_status || 'red').toLowerCase();
                        const cls = status === 'green' ? 'il-api-dot-green'
                            : (status === 'yellow' ? 'il-api-dot-yellow' : 'il-api-dot-red');
                        const title = escapeHtml(row.api_label || status);
                        return `<span class="il-api-dot ${cls}" title="${title}"></span>`;
                    },
                },
                {
                    title: "Inactive Listing",
                    headerHozAlign: "center",
                    headerTooltip: "Present in CP Master and this marketplace, inactive on the marketplace, and inventory is not zero.",
                    columns: [
                        {
                            title: "Parent",
                            field: "cp_inactive_parent",
                            width: 110,
                            hozAlign: "center",
                            sorter: "number",
                            headerTooltip: "CP Master parent listings that are inactive on the marketplace (0 Inv excluded)",
                            formatter: function(cell) {
                                return formatInactiveCount(cell, 'cp_inactive_parent');
                            },
                            bottomCalc: function(values, data) {
                                return (data || []).reduce((sum, row) => sum + Number(row.cp_inactive_parent || 0), 0);
                            },
                            bottomCalcFormatter: function(cell) {
                                return Number(cell.getValue() || 0).toLocaleString('en-US');
                            },
                        },
                        {
                            title: "Child",
                            field: "cp_inactive_child",
                            width: 110,
                            hozAlign: "center",
                            sorter: "number",
                            headerTooltip: "CP Master child SKUs that are inactive on the marketplace (0 Inv excluded)",
                            formatter: function(cell) {
                                return formatInactiveCount(cell, 'cp_inactive_child');
                            },
                            bottomCalc: function(values, data) {
                                return (data || []).reduce((sum, row) => sum + Number(row.cp_inactive_child || 0), 0);
                            },
                            bottomCalcFormatter: function(cell) {
                                return Number(cell.getValue() || 0).toLocaleString('en-US');
                            },
                        },
                    ],
                },
                {
                    title: "Listings",
                    field: "cp_detail_url",
                    headerSort: false,
                    width: 90,
                    hozAlign: "center",
                    headerHozAlign: "center",
                    headerTooltip: "Open inactive CP Master SKUs for this marketplace",
                    formatter: function(cell) {
                        const row = cell.getRow().getData() || {};
                        const url = String(row.cp_detail_url || row.detail_url || row.listings_url || '').trim();
                        const name = (row.channel || 'channel').trim();
                        if (!url) {
                            return '<span class="il-listings-arrow il-listings-arrow-off" title="Listings link not available"><i class="fas fa-arrow-up-right-from-square"></i></span>';
                        }
                        return `<a href="${escapeHtml(url)}" class="il-listings-arrow il-listings-arrow-on" title="Open ${escapeHtml(name)} inactive listings" target="_self"><i class="fas fa-arrow-up-right-from-square"></i></a>`;
                    },
                },
                {
                    title: "TM",
                    field: "tm",
                    headerSort: false,
                    width: 70,
                    hozAlign: "center",
                    headerHozAlign: "center",
                    headerTooltip: "TM — open Task Manager for this channel (new tab)",
                    formatter: function(cell) {
                        const row = cell.getRow().getData() || {};
                        const name = String(row.channel || '').trim();
                        if (!name) return '';
                        const href = ilTasksIndexUrl + '?search=' + encodeURIComponent(name);
                        return `<a href="${escapeHtml(href)}" target="_blank" rel="noopener noreferrer" class="task-summary-tm-badge task-summary-tm-badge-exec" title="Open Task Manager for ${escapeHtml(name)}" aria-label="Open Task Manager for ${escapeHtml(name)}">TM</a>`;
                    },
                },
            ],
        });

        $('#il-sync-btn').on('click', startIlSync);

        $('#inactive-listings-search').on('input', function() {
            const q = $(this).val().trim().toLowerCase();
            if (!q) {
                table.clearFilter(true);
                return;
            }
            table.setFilter(function(row) {
                return String(row.channel || '').toLowerCase().includes(q);
            });
        });
    });
</script>
@endsection
