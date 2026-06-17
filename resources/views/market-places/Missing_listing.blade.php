@extends('layouts.vertical', ['title' => 'Missing Listing', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-paginator label { margin-right: 5px; }
        .ml-channel-logo {
            width: 28px;
            height: 28px;
            object-fit: contain;
            border-radius: 4px;
            background: #fff;
            border: 1px solid #e9ecef;
            padding: 1px;
            display: inline-block;
        }
        .ml-channel-logo-placeholder {
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
        .badge-ml-stat { font-size: 0.9rem; padding: 0.45rem 0.7rem; }
        .badge-ml-chart { cursor: pointer; font-weight: bold; }
        .badge-ml-history { cursor: pointer; }
        .ml-history-empty { color: #6c757d; padding: 16px; text-align: center; }
        .ml-history-row { white-space: pre-wrap; word-break: break-word; }
        .ml-seller-portal-cell {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .ml-seller-portal-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e7f1ff;
            color: #0d6efd;
            text-decoration: none;
            transition: background-color 0.15s ease, color 0.15s ease;
        }
        .ml-seller-portal-link:hover {
            background: #0d6efd;
            color: #fff;
        }
        .ml-seller-portal-edit {
            color: #adb5bd;
            cursor: pointer;
        }
        .ml-seller-portal-edit:hover { color: #495057; }
        .ml-seller-portal-empty {
            color: #adb5bd;
            font-style: italic;
            font-size: 0.85rem;
        }
        .tabulator .tabulator-cell.tabulator-editing { padding: 2px 4px; }

        /* Metric history modal — same full-width layout as Active Channel */
        #mlMetricChartModal.modal {
            --tz-modal-width: 100%;
            --tz-modal-margin: 0.5rem 0;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        #mlMetricChartModal .modal-dialog {
            width: 100% !important;
            max-width: none !important;
            margin: 0.5rem 0 0 0 !important;
        }
        #mlMetricChartModal .modal-content {
            border-radius: 0;
            width: 100%;
            max-width: 100%;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Missing Listing',
        'sub_title'  => '',
    ])

    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080;"></div>

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <span class="badge bg-danger badge-ml-stat badge-ml-chart" id="stat-missing-listing" data-metric="missing_l" title="View Missing Listing trend">
                        Missing Listing: <span id="total-missing-listing">0</span>
                    </span>
                    <button type="button" class="btn btn-sm btn-primary" id="open-dar-btn" data-bs-toggle="modal" data-bs-target="#darSubmitModal">
                        <i class="fa fa-pen-to-square me-1"></i> DAR
                    </button>
                    <span class="badge bg-info text-dark badge-ml-stat badge-ml-history" id="stat-history" data-bs-toggle="modal" data-bs-target="#darHistoryModal" title="View DAR history">
                        <i class="fa fa-clock-rotate-left me-1"></i> History: 0
                    </span>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="p-2 bg-light border-bottom">
                    <input type="text" id="missing-listing-search" class="form-control form-control-sm" placeholder="Search by Channel...">
                </div>
                <div id="missing-listing-table" style="height: calc(100vh - 280px);"></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="darSubmitModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fa fa-pen-to-square me-2"></i> Submit Daily Activity Report
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="dar-submit-form">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-2 text-muted small">
                            Submitted as <strong>{{ Auth::user()->name ?? 'Guest' }}</strong> at submission time.
                        </div>
                        <div class="mb-3">
                            <label for="dar-report" class="form-label">Report</label>
                            <textarea class="form-control" id="dar-report" name="report" rows="6" placeholder="Describe today's activity on Missing Listings..." required maxlength="5000"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="dar-submit-btn">
                            <i class="fa fa-paper-plane me-1"></i> Submit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="darHistoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-info text-dark">
                    <h5 class="modal-title">
                        <i class="fa fa-clock-rotate-left me-2"></i> DAR History
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-sm align-middle mb-0" id="dar-history-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 60px;">#</th>
                                    <th style="width: 200px;">User</th>
                                    <th style="width: 200px;">Submitted At</th>
                                    <th>Report</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="4" class="ml-history-empty">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade p-0" id="mlMetricChartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0">
            <div class="modal-content" style="overflow: hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size: 13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span id="mlChartModalTitle">Missing Listing - Rolling window</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="mlChartRangeSelect" class="form-select form-select-sm bg-white" style="width: 110px; height: 26px; font-size: 11px; padding: 1px 8px;">
                            <option value="7">7 Days</option>
                            <option value="30">30 Days</option>
                            <option value="31">31 Days</option>
                            <option value="32" selected>32 Days</option>
                            <option value="35">35 Days</option>
                            <option value="60">60 Days</option>
                            <option value="90">90 Days</option>
                            <option value="0">Lifetime</option>
                        </select>
                        <button type="button" class="btn-close btn-close-white" style="font-size: 10px;" data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <div class="modal-body p-2">
                    <div id="mlChartContainer" style="height: 20vh; display: flex; align-items: stretch;">
                        <div style="flex: 1; min-width: 0; position: relative;">
                            <canvas id="mlMetricChart"></canvas>
                        </div>
                        <div id="mlChartRefPanel" style="width: 100px; display: flex; flex-direction: column; justify-content: center; gap: 8px; padding: 6px 8px; border-left: 1px solid #e9ecef; background: #f8f9fa; border-radius: 0 4px 4px 0;">
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #dc3545; margin-bottom: 1px;">Highest</div>
                                <div id="mlChartHighest" style="font-size: 13px; font-weight: 700; color: #dc3545;">-</div>
                            </div>
                            <div style="text-align: center; border-top: 1px dashed #adb5bd; border-bottom: 1px dashed #adb5bd; padding: 4px 0;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; margin-bottom: 1px;">Median</div>
                                <div id="mlChartMedian" style="font-size: 13px; font-weight: 700; color: #6c757d;">-</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #198754; margin-bottom: 1px;">Lowest</div>
                                <div id="mlChartLowest" style="font-size: 13px; font-weight: 700; color: #198754;">-</div>
                            </div>
                        </div>
                    </div>
                    <div id="mlChartLoading" class="text-center py-3" style="display: none;">
                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-1 text-muted small mb-0">Loading chart data...</p>
                    </div>
                    <div id="mlChartNoData" class="text-center py-3" style="display: none;">
                        <i class="fas fa-exclamation-circle text-warning fa-2x mb-2"></i>
                        <p class="text-muted small mb-0">Daily history is not available yet.</p>
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
    let mlMetricChartInstance = null;
    let mlChartAjax = null;
    let mlCurrentChartChannel = 'all';
    let mlCurrentChartDisplayChannel = 'All';
    let mlCurrentMetricKey = 'missing_l';
    let mlCurrentChartDays = 32;
    let mlCurrentBadgeValue = null;

    function updateStats(rows) {
        const total = (rows || []).reduce((sum, r) => sum + Number(r.missing_listing || 0), 0);
        $('#total-missing-listing').text(total.toLocaleString('en-US'));
    }

    function mlChartRangeLabel(days) {
        return days === 0 ? 'Lifetime' : ('L' + days);
    }

    function mlFmtVal(v) {
        return Math.round(Number(v || 0)).toLocaleString('en-US');
    }

    function showMlMetricChart(channel, cellValue) {
        mlCurrentChartDisplayChannel = String(channel || 'All');
        mlCurrentChartChannel = mlCurrentChartDisplayChannel.toLowerCase().replace(/[^a-z0-9]/g, '');
        mlCurrentMetricKey = 'missing_l';
        mlCurrentChartDays = 32;
        mlCurrentBadgeValue = (mlCurrentChartDisplayChannel === 'All' && cellValue !== undefined && cellValue !== null && !isNaN(cellValue))
            ? cellValue
            : null;

        $('#mlChartRangeSelect').val('32');
        const label = mlCurrentChartDisplayChannel === 'All'
            ? 'Missing Listing'
            : (mlCurrentChartDisplayChannel + ' - Missing Listing');
        $('#mlChartModalTitle').text(`${label} (Rolling ${mlChartRangeLabel(mlCurrentChartDays)})`);

        const modal = new bootstrap.Modal(document.getElementById('mlMetricChartModal'));
        modal.show();
        loadMlMetricChart();
    }

    function loadMlMetricChart() {
        if (mlChartAjax) mlChartAjax.abort();

        $('#mlChartNoData').hide();
        $('#mlChartContainer').hide();
        $('#mlChartLoading').show();

        const params = {
            channel: mlCurrentChartChannel,
            metric: mlCurrentMetricKey,
            days: mlCurrentChartDays,
        };
        if (mlCurrentBadgeValue !== null) {
            params.badge_value = mlCurrentBadgeValue;
        }

        mlChartAjax = $.ajax({
            url: '/channel-metric-chart-data',
            method: 'GET',
            data: params,
        }).done(function(response) {
            mlChartAjax = null;
            $('#mlChartLoading').hide();

            if (response.success !== false && response.data && response.data.length > 0) {
                $('#mlChartContainer').show();
                renderMlMetricChart(response.data);
            } else {
                $('#mlChartNoData').show();
            }
        }).fail(function(_xhr, status) {
            mlChartAjax = null;
            if (status === 'abort') return;
            $('#mlChartLoading').hide();
            $('#mlChartNoData').show();
        });
    }

    function renderMlMetricChart(data) {
        const ctx = document.getElementById('mlMetricChart').getContext('2d');
        if (mlMetricChartInstance) mlMetricChartInstance.destroy();

        const labels = data.map(d => d.date);
        const values = data.map(d => Number(d.value || 0));
        const dataMin = Math.min(...values);
        const dataMax = Math.max(...values);
        const sorted = [...values].sort((a, b) => a - b);
        const mid = Math.floor(sorted.length / 2);
        const median = sorted.length % 2 !== 0 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
        const range = dataMax - dataMin || 1;
        const yMin = Math.max(0, dataMin - range * 0.1);
        const yMax = dataMax + range * 0.1;

        const refRed = '#dc3545';
        const refGray = '#6c757d';
        const refGreen = '#198754';
        const highestEl = document.getElementById('mlChartHighest');
        const medianEl = document.getElementById('mlChartMedian');
        const lowestEl = document.getElementById('mlChartLowest');
        highestEl.textContent = mlFmtVal(dataMax);
        highestEl.style.color = dataMax === 0 ? refGreen : dataMax > 0 ? refRed : refGray;
        medianEl.textContent = mlFmtVal(median);
        medianEl.style.color = median === 0 ? refGreen : median > 0 ? refRed : refGray;
        lowestEl.textContent = mlFmtVal(dataMin);
        lowestEl.style.color = dataMin === 0 ? refGreen : dataMin > 0 ? refRed : refGray;

        const dotColors = values.map((v, i) => {
            if (i === 0) return refGray;
            return v > values[i - 1] ? '#28a745' : v < values[i - 1] ? refRed : refGray;
        });
        const labelColors = values.map(v => v === 0 ? refGreen : v > 0 ? refRed : refGray);

        const medianLinePlugin = {
            id: 'mlMedianLine',
            afterDraw(chart) {
                const yScale = chart.scales.y;
                const xScale = chart.scales.x;
                const c = chart.ctx;
                const yPixel = yScale.getPixelForValue(median);
                c.save();
                c.setLineDash([6, 4]);
                c.strokeStyle = refGray;
                c.lineWidth = 1.2;
                c.beginPath();
                c.moveTo(xScale.left, yPixel);
                c.lineTo(xScale.right, yPixel);
                c.stroke();
                c.restore();
            }
        };

        const valueLabelsPlugin = {
            id: 'mlValueLabels',
            afterDatasetsDraw(chart) {
                const dataset = chart.data.datasets[0];
                const meta = chart.getDatasetMeta(0);
                const c = chart.ctx;
                c.save();
                c.font = 'bold 11px Inter, system-ui, sans-serif';
                c.textAlign = 'center';
                c.textBaseline = 'bottom';
                meta.data.forEach((point, i) => {
                    const val = dataset.data[i];
                    c.fillStyle = labelColors[i];
                    c.fillText(mlFmtVal(val), point.x, point.y + ((i % 2 === 0) ? -10 : -20));
                });
                c.restore();
            }
        };

        mlMetricChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Missing Listing',
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
                    pointBorderWidth: 1.5,
                }],
            },
            plugins: [medianLinePlugin, valueLabelsPlugin],
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { top: 26, left: 2, right: 2, bottom: 2 } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        titleFont: { size: 10 },
                        bodyFont: { size: 10 },
                        padding: 6,
                        callbacks: {
                            label: function(context) {
                                const idx = context.dataIndex;
                                const parts = ['Value: ' + mlFmtVal(context.raw)];
                                if (idx > 0) {
                                    const diff = context.raw - values[idx - 1];
                                    const arrow = diff < 0 ? '▼' : diff > 0 ? '▲' : '▬';
                                    parts.push('vs Yesterday: ' + arrow + ' ' + mlFmtVal(Math.abs(diff)));
                                }
                                return parts;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        min: yMin,
                        max: yMax,
                        ticks: {
                            font: { size: 9 },
                            callback: function(value) { return mlFmtVal(value); }
                        }
                    },
                    x: {
                        ticks: { maxRotation: 45, minRotation: 45, font: { size: 9 } }
                    }
                }
            }
        });
    }

    function showToast(message, type) {
        type = type || 'info';
        const container = document.querySelector('.toast-container');
        if (!container) return;
        const bg = type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info';
        const el = document.createElement('div');
        el.className = `toast align-items-center text-white bg-${bg} border-0 mb-2`;
        el.setAttribute('role', 'alert');
        el.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>`;
        container.appendChild(el);
        new bootstrap.Toast(el, { delay: 4000 }).show();
        el.addEventListener('hidden.bs.toast', () => el.remove());
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // channel_master.logo is stored as a relative path under storage/
    // (e.g. channel_logos/amazon.png) — same as all-marketplace-master Img column.
    function mlLogoSrc(logo) {
        const v = String(logo || '').trim();
        if (!v) return '';
        if (/^https?:\/\//i.test(v) || v.startsWith('/')) return v;
        return '/storage/' + v.replace(/^\/+/, '');
    }

    function formatTimestamp(iso) {
        if (!iso) return '-';
        const d = new Date(iso);
        if (isNaN(d.getTime())) return iso;
        return d.toLocaleString();
    }

    function setHistoryBadgeCount(count) {
        $('#stat-history').html(`<i class="fa fa-clock-rotate-left me-1"></i> History: ${Number(count || 0).toLocaleString('en-US')}`);
    }

    function saveSellerPortal(cell) {
        const row = cell.getRow();
        const data = row.getData();
        const newValue = (cell.getValue() || '').trim();
        const oldValue = (cell.getOldValue() || '').trim();

        if (newValue === oldValue) return;

        if (newValue !== '') {
            try {
                new URL(newValue);
            } catch (_) {
                showToast('Please enter a valid URL (including https://).', 'error');
                cell.setValue(oldValue, true);
                return;
            }
        }

        $.ajax({
            url: "{{ route('missing.listing.seller.portal.save') }}",
            method: 'POST',
            data: { id: data.id, seller_portal: newValue },
            dataType: 'json',
        }).done(function(res) {
            if (res && res.success) {
                showToast(res.message || 'Seller Portal updated.', 'success');
            } else {
                showToast((res && res.message) || 'Update failed.', 'error');
                cell.setValue(oldValue, true);
            }
        }).fail(function(xhr) {
            const msg = (xhr.responseJSON && xhr.responseJSON.message)
                ? xhr.responseJSON.message
                : (xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors.seller_portal)
                    ? xhr.responseJSON.errors.seller_portal[0]
                    : 'Update failed.';
            showToast(msg, 'error');
            cell.setValue(oldValue, true);
        });
    }

    function loadDarHistory(renderTable) {
        return $.ajax({
            url: "{{ route('missing.listing.dar.history') }}",
            method: 'GET',
            dataType: 'json',
        }).done(function(res) {
            const rows = (res && res.success && Array.isArray(res.data)) ? res.data : [];
            setHistoryBadgeCount(rows.length);

            if (renderTable) {
                const $tbody = $('#dar-history-table tbody');
                if (rows.length === 0) {
                    $tbody.html('<tr><td colspan="4" class="ml-history-empty">No DAR submissions yet.</td></tr>');
                    return;
                }
                const html = rows.map((r, i) => `
                    <tr>
                        <td>${i + 1}</td>
                        <td>${escapeHtml(r.user_name)}</td>
                        <td>${escapeHtml(formatTimestamp(r.submitted_at))}</td>
                        <td class="ml-history-row">${escapeHtml(r.report)}</td>
                    </tr>
                `).join('');
                $tbody.html(html);
            }
        }).fail(function() {
            if (renderTable) {
                $('#dar-history-table tbody').html('<tr><td colspan="4" class="ml-history-empty text-danger">Failed to load history.</td></tr>');
            }
        });
    }

    $(document).ready(function() {
        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });

        $('#stat-missing-listing').on('click', function() {
            const badgeText = $('#total-missing-listing').text().replace(/[,$%]/g, '').trim();
            const badgeValue = parseFloat(badgeText) || null;
            showMlMetricChart('All', badgeValue);
        });

        $('#mlChartRangeSelect').on('change', function() {
            const days = parseInt($(this).val(), 10);
            if (days === mlCurrentChartDays) return;
            mlCurrentChartDays = days;
            const label = mlCurrentChartDisplayChannel === 'All'
                ? 'Missing Listing'
                : (mlCurrentChartDisplayChannel + ' - Missing Listing');
            $('#mlChartModalTitle').text(`${label} (Rolling ${mlChartRangeLabel(days)})`);
            loadMlMetricChart();
        });

        loadDarHistory(false);

        $('#darHistoryModal').on('show.bs.modal', function() {
            $('#dar-history-table tbody').html('<tr><td colspan="4" class="ml-history-empty">Loading...</td></tr>');
            loadDarHistory(true);
        });

        $('#darSubmitModal').on('hidden.bs.modal', function() {
            $('#dar-report').val('');
        });

        $('#dar-submit-form').on('submit', function(e) {
            e.preventDefault();
            const report = ($('#dar-report').val() || '').trim();
            if (!report) {
                showToast('Please enter your DAR before submitting.', 'error');
                return;
            }

            const $btn = $('#dar-submit-btn');
            const original = $btn.html();
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Submitting...');

            $.ajax({
                url: "{{ route('missing.listing.dar.submit') }}",
                method: 'POST',
                data: { report: report },
                dataType: 'json',
            }).done(function(res) {
                if (res && res.success) {
                    showToast(res.message || 'DAR submitted successfully.', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('darSubmitModal'))?.hide();
                    loadDarHistory(false);
                } else {
                    showToast((res && res.message) || 'Submission failed.', 'error');
                }
            }).fail(function(xhr) {
                const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Submission failed.';
                showToast(msg, 'error');
            }).always(function() {
                $btn.prop('disabled', false).html(original);
            });
        });

        table = new Tabulator("#missing-listing-table", {
            ajaxURL: "{{ route('missing.listing.data') }}",
            ajaxResponse: function(_url, _params, response) {
                const data = (response && response.data) ? response.data : [];
                updateStats(data);
                return data;
            },
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 50,
            paginationSizeSelector: [25, 50, 100, 200, 500],
            initialSort: [{ column: "channel", dir: "asc" }],
            placeholder: "No channels found.",
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
                            return '<span class="ml-channel-logo-placeholder" title="No logo"><i class="fas fa-image"></i></span>';
                        }
                        const src = mlLogoSrc(logo);
                        const safeSrc = escapeHtml(src);
                        const safeAlt = escapeHtml(channel);
                        return `<img src="${safeSrc}" alt="${safeAlt}" class="ml-channel-logo" onerror="this.style.display='none'">`;
                    }
                },
                {
                    title: "Channel",
                    field: "channel",
                    minWidth: 220,
                },
                {
                    title: "Missing Listing",
                    field: "missing_listing",
                    width: 180,
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const v = Number(cell.getValue() || 0);
                        const channel = (cell.getRow().getData().channel || '').trim();
                        const color = v === 0 ? '#198754' : '#dc3545';
                        const dotColor = v === 0 ? '#198754' : (v > 0 ? '#dc3545' : '#6c757d');
                        const chartIcon = `<i class="fas fa-circle ml-metric-chart-icon ms-1" data-channel="${escapeHtml(channel)}" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                        return `<span style="color:${color};font-weight:600;">${v.toLocaleString('en-US')}</span>${chartIcon}`;
                    },
                    cellClick: function(e, cell) {
                        if (e.target.classList.contains('ml-metric-chart-icon')) {
                            e.stopPropagation();
                            const channel = $(e.target).data('channel');
                            const value = Number(cell.getValue() || 0);
                            showMlMetricChart(channel, value);
                        }
                    },
                    bottomCalc: "sum",
                },
                {
                    title: "Seller Portal",
                    field: "seller_portal",
                    width: 140,
                    hozAlign: "center",
                    editor: "input",
                    headerSort: false,
                    cellDblClick: function(_e, cell) {
                        cell.edit();
                    },
                    formatter: function(cell) {
                        const v = (cell.getValue() || '').trim();
                        if (!v) {
                            return '<div class="ml-seller-portal-cell"><span class="ml-seller-portal-empty">Click to add</span></div>';
                        }
                        const safe = escapeHtml(v);
                        return `<div class="ml-seller-portal-cell">
                                    <a href="${safe}" target="_blank" rel="noopener noreferrer" class="ml-seller-portal-link" title="${safe}" onclick="event.stopPropagation();">
                                        <i class="fa fa-link"></i>
                                    </a>
                                    <i class="fa fa-pen ml-seller-portal-edit" title="Double-click to edit"></i>
                                </div>`;
                    },
                    cellEdited: function(cell) {
                        saveSellerPortal(cell);
                    },
                },
            ],
        });

        $('#missing-listing-search').on('input', function() {
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
