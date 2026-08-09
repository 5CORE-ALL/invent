{{-- Status dots (green/red/gray) + rolling history charts for KPI badges --}}
<style>
    .kpi-status-dot {
        display: inline-block;
        width: 0.65rem;
        height: 0.65rem;
        border-radius: 50%;
        margin-right: 0.4rem;
        vertical-align: 0.05em;
        box-shadow: 0 0 0 1px rgba(255,255,255,0.35);
        flex: 0 0 auto;
        cursor: pointer;
        position: relative;
        z-index: 2;
    }
    .kpi-status-dot:hover {
        transform: scale(1.25);
        box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.2);
    }
    .kpi-status-dot--green { background: #22c55e; }
    .kpi-status-dot--red { background: #ef4444; }
    .kpi-status-dot--gray { background: #9ca3af; }
    .dashboard-badge-panel__badges .badge[data-kpi-key] {
        display: inline-flex;
        align-items: center;
        cursor: pointer;
    }
    #dashKpiChartModal.modal {
        --tz-modal-width: 100%;
        padding-left: 0 !important;
        padding-right: 0 !important;
        /* Above card playback modal + its backdrop (never under the black screen) */
        z-index: 1080;
    }
    #dashKpiChartModal .modal-dialog {
        width: 100% !important;
        max-width: none !important;
        margin: 0.5rem 0 0 0 !important;
    }
    .modal-backdrop.dash-kpi-chart-backdrop {
        z-index: 1075 !important;
    }
</style>

<div class="modal fade p-0" id="dashKpiChartModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog shadow-none m-0 mx-0">
        <div class="modal-content" style="overflow:hidden;">
            <div class="modal-header bg-dark text-white py-1 px-3">
                <h6 class="modal-title mb-0" style="font-size:13px;">
                    <i class="fas fa-chart-area me-1"></i>
                    <span id="dashKpiChartTitle">KPI — Rolling history</span>
                </h6>
                <div class="d-flex align-items-center gap-2">
                    <select id="dashKpiChartRange" class="form-select form-select-sm" style="width:auto;font-size:11px;">
                        <option value="7">L7</option>
                        <option value="14">L14</option>
                        <option value="30" selected>L30</option>
                        <option value="60">L60</option>
                        <option value="90">L90</option>
                    </select>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body py-2 px-3">
                <div class="d-flex justify-content-between align-items-center mb-2 small">
                    <span id="dashKpiChartSub" class="text-muted"></span>
                    <span id="dashKpiChartTone" class="badge bg-secondary">—</span>
                </div>
                <div id="dashKpiChartLoading" class="text-center py-3" style="display:none;">
                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                </div>
                <div id="dashKpiChartNoData" class="text-center py-3 text-muted small" style="display:none;">
                    No history yet — dots will color after a few daily snapshots.
                </div>
                <div id="dashKpiChartWrap" style="display:none;height:280px;">
                    <canvas id="dashKpiChartCanvas"></canvas>
                </div>
                <div class="d-flex justify-content-around small mt-2" id="dashKpiChartStats" style="display:none;">
                    <div class="text-center"><div class="text-muted" style="font-size:10px;">Highest</div><div id="dashKpiHi" class="fw-bold">—</div></div>
                    <div class="text-center"><div class="text-muted" style="font-size:10px;">Median</div><div id="dashKpiMed" class="fw-bold">—</div></div>
                    <div class="text-center"><div class="text-muted" style="font-size:10px;">Lowest</div><div id="dashKpiLo" class="fw-bold">—</div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const HISTORY_URL = @json(route('dashboard.kpi.history'));
    const TONES_URL = @json(route('dashboard.kpi.tones'));
    const CSRF = @json(csrf_token());
    // label-prefix → badges_data key (applied when data-kpi-key is missing)
    const AUTO_KPI_MAP = @json($dashKpiAutoMap ?? []);

    let chartInstance = null;
    let chartAjax = null;
    let activeKey = '';
    let activeLabel = '';
    let activeValue = null;
    let activeDays = 30;

    const TONE_COLORS = { green: '#22c55e', red: '#ef4444', gray: '#9ca3af' };

    function autoTagKpiBadges() {
        const map = (AUTO_KPI_MAP || []).slice().sort((a, b) =>
            String(b.prefix || '').length - String(a.prefix || '').length
        );
        document.querySelectorAll('.dashboard-badge-panel__badges > .badge').forEach((badge) => {
            if (badge.getAttribute('data-kpi-key')) return;
            // Skip pure navigation chips (no numeric value)
            const text = (badge.textContent || '').replace(/\s+/g, ' ').trim();
            if (!/\d/.test(text)) return;
            const upper = text.toUpperCase();
            for (const row of map) {
                const prefix = String(row.prefix || '').toUpperCase();
                if (prefix && upper.startsWith(prefix)) {
                    badge.setAttribute('data-kpi-key', row.key);
                    if (row.label) badge.setAttribute('data-kpi-label', row.label);
                    if (row.value != null && badge.getAttribute('data-kpi-value') == null) {
                        badge.setAttribute('data-kpi-value', String(row.value));
                    }
                    break;
                }
            }
        });
    }

    // WeakSet so cloneNode copies of data-kpi-wired don't fake "already wired"
    const wiredDots = new WeakSet();

    function ensureDot(badge, tone) {
        let dot = badge.querySelector('.kpi-status-dot');
        if (!dot) {
            dot = document.createElement('span');
            dot.className = 'kpi-status-dot';
            dot.setAttribute('role', 'button');
            dot.tabIndex = 0;
            dot.title = 'Click for rolling history graph';
            badge.insertBefore(dot, badge.firstChild);
        }
        dot.classList.remove('kpi-status-dot--green', 'kpi-status-dot--red', 'kpi-status-dot--gray');
        dot.classList.add('kpi-status-dot--' + (tone || 'gray'));
        badge.dataset.kpiTone = tone || 'gray';

        if (!wiredDots.has(dot)) {
            wiredDots.add(dot);
            delete dot.dataset.kpiWired;
            const open = (e) => {
                // Dot only — stop badge onclick navigation
                e.preventDefault();
                e.stopPropagation();
                if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
                openChart(badge);
            };
            dot.addEventListener('click', open, true);
            dot.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    open(e);
                }
            });
        }
    }

    function parseValue(badge) {
        const raw = badge.getAttribute('data-kpi-value');
        if (raw !== null && raw !== '' && !isNaN(Number(raw))) return Number(raw);
        const m = String(badge.textContent || '').replace(/,/g, '').match(/-?\d+(\.\d+)?/);
        return m ? Number(m[0]) : null;
    }

    async function applyTones() {
        const badges = [...document.querySelectorAll('.dashboard-badge-panel__badges .badge[data-kpi-key]')];
        if (!badges.length) return;

        // Gray immediately so layout is stable
        badges.forEach((b) => ensureDot(b, b.dataset.kpiTone || 'gray'));

        const keys = [...new Set(badges.map((b) => b.getAttribute('data-kpi-key')).filter(Boolean))];
        try {
            const res = await fetch(TONES_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                },
                credentials: 'same-origin',
                body: JSON.stringify({ keys }),
            });
            const data = await res.json();
            const tones = (data && data.tones) || {};
            badges.forEach((b) => {
                const key = b.getAttribute('data-kpi-key');
                const tone = (tones[key] && tones[key].tone) || 'gray';
                ensureDot(b, tone);
            });
        } catch (e) {
            console.warn('KPI tones failed', e);
        }
    }

    function wireDots() {
        document.querySelectorAll('.dashboard-badge-panel__badges .badge[data-kpi-key]').forEach((badge) => {
            ensureDot(badge, badge.dataset.kpiTone || 'gray');
        });
    }

    function openChart(badge) {
        activeKey = badge.getAttribute('data-kpi-key') || '';
        activeLabel = badge.getAttribute('data-kpi-label') || (badge.textContent || '').trim();
        activeValue = parseValue(badge);
        activeDays = parseInt(document.getElementById('dashKpiChartRange').value, 10) || 30;

        document.getElementById('dashKpiChartTitle').textContent = activeLabel + ' — Rolling L' + activeDays;
        document.getElementById('dashKpiChartSub').textContent = activeKey;
        const tone = badge.dataset.kpiTone || 'gray';
        const toneEl = document.getElementById('dashKpiChartTone');
        toneEl.textContent = tone.toUpperCase();
        toneEl.className = 'badge';
        toneEl.style.background = TONE_COLORS[tone] || TONE_COLORS.gray;
        toneEl.style.color = '#fff';

        const modalEl = document.getElementById('dashKpiChartModal');
        const onShown = () => {
            const backs = document.querySelectorAll('.modal-backdrop');
            const last = backs[backs.length - 1];
            if (last) {
                last.classList.add('dash-kpi-chart-backdrop');
                last.style.zIndex = '1075';
            }
            modalEl.style.zIndex = '1080';
            modalEl.removeEventListener('shown.bs.modal', onShown);
        };
        modalEl.addEventListener('shown.bs.modal', onShown);
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
        loadChart();
    }

    function loadChart() {
        if (!activeKey) return;
        if (chartAjax) chartAjax.abort();
        document.getElementById('dashKpiChartLoading').style.display = 'block';
        document.getElementById('dashKpiChartNoData').style.display = 'none';
        document.getElementById('dashKpiChartWrap').style.display = 'none';
        document.getElementById('dashKpiChartStats').style.display = 'none';

        const params = new URLSearchParams({ key: activeKey, days: String(activeDays) });
        if (activeValue !== null) params.set('badge_value', String(activeValue));

        chartAjax = fetch(HISTORY_URL + '?' + params.toString(), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        }).then((r) => r.json()).then((payload) => {
            chartAjax = null;
            document.getElementById('dashKpiChartLoading').style.display = 'none';
            if (!payload || !payload.success || !payload.data || !payload.data.length) {
                document.getElementById('dashKpiChartNoData').style.display = 'block';
                return;
            }
            document.getElementById('dashKpiChartWrap').style.display = 'block';
            document.getElementById('dashKpiChartStats').style.display = 'flex';
            if (payload.tone) {
                const toneEl = document.getElementById('dashKpiChartTone');
                toneEl.textContent = String(payload.tone).toUpperCase();
                toneEl.style.background = TONE_COLORS[payload.tone] || TONE_COLORS.gray;
            }
            renderChart(payload.data, payload.label || activeLabel);
        }).catch(() => {
            chartAjax = null;
            document.getElementById('dashKpiChartLoading').style.display = 'none';
            document.getElementById('dashKpiChartNoData').style.display = 'block';
        });
    }

    function renderChart(data, label) {
        if (typeof Chart === 'undefined') return;
        const ctx = document.getElementById('dashKpiChartCanvas').getContext('2d');
        if (chartInstance) chartInstance.destroy();

        const labels = data.map((d) => d.date);
        const values = data.map((d) => Number(d.value || 0));
        const colors = data.map((d) => TONE_COLORS[d.tone] || TONE_COLORS.gray);

        const dataMin = Math.min.apply(null, values);
        const dataMax = Math.max.apply(null, values);
        const sorted = values.slice().sort((a, b) => a - b);
        const mid = Math.floor(sorted.length / 2);
        const median = sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
        const range = dataMax - dataMin || 1;

        document.getElementById('dashKpiHi').textContent = fmt(dataMax);
        document.getElementById('dashKpiMed').textContent = fmt(median);
        document.getElementById('dashKpiLo').textContent = fmt(dataMin);

        chartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label,
                    data: values,
                    borderColor: '#94a3b8',
                    backgroundColor: 'rgba(148,163,184,0.12)',
                    fill: true,
                    tension: 0.3,
                    borderWidth: 1.5,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: colors,
                    pointBorderColor: colors,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        min: Math.min(0, dataMin - range * 0.08),
                        max: dataMax + range * 0.08,
                        ticks: { font: { size: 9 }, callback: (v) => fmt(v) },
                    },
                    x: { ticks: { maxRotation: 45, minRotation: 45, font: { size: 9 } } },
                },
            },
        });
    }

    function fmt(v) {
        const n = Number(v || 0);
        if (!isFinite(n)) return '—';
        if (Math.abs(n) >= 1000) return Math.round(n).toLocaleString('en-US');
        if (Math.abs(n - Math.round(n)) < 0.001) return String(Math.round(n));
        return n.toLocaleString('en-US', { maximumFractionDigits: 2 });
    }

    // Expose for customize injector (custom KPI badges)
    window.DashKpiDots = {
        refresh() {
            autoTagKpiBadges();
            wireDots();
            applyTones();
        },
        ensureDot,
        autoTagKpiBadges,
    };

    document.addEventListener('DOMContentLoaded', function () {
        autoTagKpiBadges();
        wireDots();
        applyTones();
        document.getElementById('dashKpiChartRange')?.addEventListener('change', function () {
            activeDays = parseInt(this.value, 10) || 30;
            document.getElementById('dashKpiChartTitle').textContent = activeLabel + ' — Rolling L' + activeDays;
            loadChart();
        });
    });
})();
</script>
