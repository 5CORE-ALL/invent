{{--
    O-Due rolling history modal (Task Summary).

    Opened from the small red dot beside each O-Due number (one member) and from the dot in the O-Due
    column header (all members the viewer can see). Data comes from task_overdue_history, which is
    written by the hourly tasks:snapshot-overdue job and when an admin opens the page.

    ApexCharts is loaded by task-summary.blade.php's script section; this script only uses it on click.
--}}

<style>
    .ts-overdue-hist-dot {
        display: inline-block;
        width: 10px;
        height: 10px;
        min-width: 10px;
        margin-right: 0.35rem;
        border-radius: 50%;
        background: #dc2626;
        box-shadow: 0 0 0 1px rgba(220, 38, 38, 0.35);
        cursor: pointer;
        vertical-align: middle;
        transition: transform 0.15s ease;
    }
    .ts-overdue-hist-dot:hover {
        transform: scale(1.35);
    }
    .ts-overdue-hist-dot:focus-visible {
        outline: 2px solid #dc2626;
        outline-offset: 2px;
    }
    /* O-Due trend arrow: red up = more overdue than the last saved day, green down = fewer. */
    .ts-overdue-trend {
        margin-left: 0.15rem;
        font-size: 1rem;
        font-weight: 800;
        vertical-align: middle;
    }
    .ts-overdue-trend.is-up {
        color: #dc2626;
    }
    .ts-overdue-trend.is-down {
        color: #16a34a;
    }
    #taskSummaryOverdueHistoryModal .modal-content {
        border-radius: 16px;
        border: none;
        box-shadow: 0 20px 50px rgba(15, 23, 42, 0.18);
    }
    #taskSummaryOverdueHistoryModal .modal-header {
        background: linear-gradient(135deg, #b91c1c, #ef4444);
        color: #fff;
        border-bottom: 0;
        border-top-left-radius: 16px;
        border-top-right-radius: 16px;
    }
    #taskSummaryOverdueHistoryModal .modal-header .btn-close {
        filter: brightness(0) invert(1);
    }
    #taskSummaryOverdueHistoryModal .oh-meta {
        font-size: 0.8rem;
        opacity: 0.92;
    }
    #taskSummaryOverdueHistoryModal .oh-current {
        background: rgba(255, 255, 255, 0.18);
        border: 2px solid rgba(255, 255, 255, 0.55);
        border-radius: 999px;
        padding: 0.15em 0.7em;
        font-weight: 800;
        font-size: 0.95rem;
        font-variant-numeric: tabular-nums;
    }
    #ts-oh-chart {
        min-height: 320px;
    }
    #ts-oh-empty,
    #ts-oh-loading {
        text-align: center;
        padding: 1.5rem;
        color: #64748b;
    }
</style>

<div class="modal fade" id="taskSummaryOverdueHistoryModal" tabindex="-1" aria-labelledby="taskSummaryOverdueHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header flex-column align-items-stretch">
                <div class="d-flex align-items-start w-100">
                    <div class="flex-grow-1 min-w-0">
                        <h5 class="modal-title mb-1" id="taskSummaryOverdueHistoryModalLabel">
                            <i class="ri-line-chart-line me-2" aria-hidden="true"></i>
                            <span id="ts-oh-user">O-Due history</span>
                        </h5>
                        <div class="oh-meta">Overdue tasks · rolling graph, a point every hour</div>
                    </div>
                    <div class="d-flex align-items-center gap-2 ms-2">
                        <select id="ts-oh-days" class="form-select form-select-sm" style="width: 92px;" aria-label="Days">
                            <option value="1">24 hours</option>
                            <option value="7" selected>7 days</option>
                            <option value="30">30 days</option>
                            <option value="90">90 days</option>
                        </select>
                        <span class="oh-current" id="ts-oh-current">—</span>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
            </div>
            <div class="modal-body">
                <div id="ts-oh-loading" class="d-none">
                    <div class="spinner-border text-danger" role="status"><span class="visually-hidden">Loading…</span></div>
                    <p class="text-muted small mt-2 mb-0">Loading O-Due history…</p>
                </div>
                <div id="ts-oh-error" class="alert alert-danger d-none" role="alert"></div>
                <div id="ts-oh-content" class="d-none">
                    <div id="ts-oh-chart"></div>
                    <div id="ts-oh-note" class="alert alert-light border small mb-0 mt-2 d-none"></div>
                </div>
                <div id="ts-oh-empty" class="d-none">
                    <i class="ri-line-chart-line d-block mb-2" style="font-size:2rem;color:#94a3b8;"></i>
                    <div class="fw-semibold mb-1">No history yet</div>
                    <p class="small mb-0">Overdue counts are saved every hour, so the line fills in as time passes.</p>
                </div>
            </div>
            <div class="modal-footer">
                <small class="text-muted me-auto"><i class="ri-information-line me-1"></i> Rolling: every saved reading in the selected range (hourly, plus page visits).</small>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var endpoint = @json(route('tasks.overdueHistory'));
        var chart = null;
        var current = { userId: 0, userName: '' };

        function el(id) { return document.getElementById(id); }

        function destroyChart() {
            if (chart) {
                chart.destroy();
                chart = null;
            }
            var mount = el('ts-oh-chart');
            if (mount) mount.innerHTML = '';
        }

        function showModal() {
            var m = el('taskSummaryOverdueHistoryModal');
            if (m && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(m).show();
            }
        }

        function renderChart(points, single) {
            var mount = el('ts-oh-chart');
            if (!mount || typeof ApexCharts === 'undefined') {
                var empty = el('ts-oh-empty');
                if (empty) {
                    empty.classList.remove('d-none');
                    empty.querySelector('.fw-semibold').textContent = 'Chart library not loaded';
                }
                return;
            }
            destroyChart();
            chart = new ApexCharts(mount, {
                chart: {
                    type: 'line',
                    height: 340,
                    toolbar: { show: true },
                    animations: { enabled: true, easing: 'easeinout', speed: 500 },
                    fontFamily: 'inherit'
                },
                series: [{
                    name: 'Overdue',
                    data: points.map(function (p) {
                        return { x: new Date(p.t).getTime(), y: p.value };
                    })
                }],
                stroke: { curve: 'smooth', width: 3, colors: ['#dc2626'] },
                markers: { size: single ? 7 : 4, colors: ['#dc2626'], strokeColors: '#fff', strokeWidth: 2, hover: { size: 8 } },
                colors: ['#dc2626'],
                xaxis: { type: 'datetime', labels: { style: { fontSize: '11px' }, datetimeUTC: false, datetimeFormatter: { year: 'yyyy', month: "MMM 'yy", day: 'dd MMM', hour: 'dd MMM HH:mm' } } },
                yaxis: { min: 0, forceNiceScale: true, decimalsInFloat: 0, labels: { style: { fontSize: '12px' } } },
                grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
                dataLabels: { enabled: !!single, offsetY: -8, style: { fontSize: '13px', colors: ['#b91c1c'] }, background: { enabled: false } },
                tooltip: { theme: 'light', x: { format: 'dd MMM yyyy, hh:mm TT' } }
            });
            chart.render();
        }

        function load() {
            var loading = el('ts-oh-loading');
            var content = el('ts-oh-content');
            var emptyEl = el('ts-oh-empty');
            var errEl = el('ts-oh-error');
            var curEl = el('ts-oh-current');
            var days = el('ts-oh-days') ? el('ts-oh-days').value : '7';

            if (errEl) { errEl.classList.add('d-none'); errEl.textContent = ''; }
            if (loading) loading.classList.remove('d-none');
            if (content) content.classList.add('d-none');
            if (emptyEl) emptyEl.classList.add('d-none');
            destroyChart();

            var url = endpoint + '?days=' + encodeURIComponent(days)
                + (current.userId > 0 ? '&user_id=' + encodeURIComponent(current.userId) : '');

            fetch(url, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (r) {
                    return r.json().then(function (data) {
                        if (!r.ok || data.success === false) {
                            throw new Error((data && data.message) ? data.message : 'Request failed (HTTP ' + r.status + ')');
                        }
                        return data;
                    });
                })
                .then(function (data) {
                    if (loading) loading.classList.add('d-none');
                    var pts = Array.isArray(data.points) ? data.points : [];
                    if (curEl) curEl.textContent = pts.length ? String(pts[pts.length - 1].value) : '—';
                    if (!pts.length) {
                        if (emptyEl) emptyEl.classList.remove('d-none');
                        return;
                    }
                    if (content) content.classList.remove('d-none');
                    var noteEl = el('ts-oh-note');
                    if (noteEl) {
                        if (pts.length === 1) {
                            noteEl.textContent = 'Only one reading is saved so far (' + new Date(pts[0].t).toLocaleString() + ': ' + pts[0].value
                                + '). The line grows by one point every hour.';
                            noteEl.classList.remove('d-none');
                        } else {
                            noteEl.classList.add('d-none');
                        }
                    }
                    // A single reading cannot draw a line yet: show it as a labelled dot.
                    renderChart(pts, pts.length === 1);
                })
                .catch(function (err) {
                    if (loading) loading.classList.add('d-none');
                    if (errEl) {
                        errEl.textContent = err && err.message ? err.message : 'Failed to load history.';
                        errEl.classList.remove('d-none');
                    }
                });
        }

        function open(dot) {
            current.userId = parseInt(dot.getAttribute('data-user-id'), 10) || 0;
            current.userName = dot.getAttribute('data-user-name') || '';
            var nameEl = el('ts-oh-user');
            if (nameEl) nameEl.textContent = (current.userName || 'Team') + ' — O-Due history';
            var curEl = el('ts-oh-current');
            if (curEl) curEl.textContent = dot.getAttribute('data-current') || '…';
            showModal();
            load();
        }

        // Capture phase so the click never reaches the sortable column header.
        document.addEventListener('click', function (e) {
            var dot = e.target && e.target.closest && e.target.closest('.ts-overdue-hist-dot');
            if (!dot) return;
            e.preventDefault();
            e.stopPropagation();
            open(dot);
        }, true);
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            var dot = e.target && e.target.closest && e.target.closest('.ts-overdue-hist-dot');
            if (!dot) return;
            e.preventDefault();
            e.stopPropagation();
            open(dot);
        }, true);

        var daysSel = el('ts-oh-days');
        if (daysSel) daysSel.addEventListener('change', load);

        var modalEl = el('taskSummaryOverdueHistoryModal');
        if (modalEl) modalEl.addEventListener('hidden.bs.modal', destroyChart);
    })();
</script>
