@extends('layouts.vertical', ['title' => 'Shopify Dashboard', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Shopify',
        'sub_title'  => 'Dashboard · Reporting',
    ])

    @include('crm.shopify._nav', ['active' => 'dashboard'])

    <style>
        /* ── design tokens ──────────────────────────────────── */
        :root {
            --sd-green:       #16a34a;
            --sd-green-light: rgba(22,163,74,.12);
            --sd-green-dim:   rgba(22,163,74,.18);
            --sd-ink:         #0f172a;
            --sd-muted:       #64748b;
            --sd-border:      rgba(22,163,74,.15);
            --sd-surface:     #ffffff;
            --sd-bg:          #f8fafc;
            --sd-radius-card: 16px;
            --sd-shadow-card: 0 4px 24px rgba(15,23,42,.07);
        }

        /* ── compact filter bar ─────────────────────────────── */
        .sd-filter-bar {
            background: var(--sd-surface);
            border: 1px solid var(--sd-border);
            border-radius: var(--sd-radius-card);
            box-shadow: var(--sd-shadow-card);
            padding: .55rem 1rem;
        }
        .sd-filter-row {
            display: flex;
            align-items: center;
            gap: .4rem;
            flex-wrap: nowrap;
        }
        .sd-filter-sep { width:1px; height:18px; background:rgba(22,163,74,.2); flex-shrink:0; margin:0 .1rem; }
        .sd-filter-bar .form-control-sm,
        .sd-filter-bar .form-select-sm {
            height: 30px;
            font-size: .8rem;
            padding: .2rem .5rem;
            border-color: #e2e8f0;
            border-radius: 7px;
            background: #f8fafc;
        }
        .sd-filter-bar .form-select-sm { padding-right: 1.6rem; }
        #sd-start, #sd-end { width: 130px; }
        #sd-type    { width: 130px; }
        #sd-channel { width: 150px; }

        /* ── KPI cards ──────────────────────────────────────── */
        .sd-kpi {
            background: var(--sd-surface);
            border: 1px solid var(--sd-border);
            border-radius: var(--sd-radius-card);
            box-shadow: var(--sd-shadow-card);
            padding: .85rem 1rem;
        }
        .sd-kpi-icon {
            width: 36px; height: 36px;
            border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }
        .sd-kpi-label {
            color: var(--sd-muted);
            font-size: .67rem;
            font-weight: 700;
            letter-spacing: .07em;
            text-transform: uppercase;
        }
        .sd-kpi-value {
            color: var(--sd-ink);
            font-size: 1.4rem;
            font-weight: 800;
            line-height: 1.1;
        }
        .sd-kpi-sub {
            color: var(--sd-muted);
            font-size: .72rem;
        }

        /* ── section cards ──────────────────────────────────── */
        .sd-card {
            background: var(--sd-surface);
            border: 1px solid var(--sd-border);
            border-radius: var(--sd-radius-card);
            box-shadow: var(--sd-shadow-card);
        }
        .sd-card-header {
            border-bottom: 1px solid rgba(15,23,42,.07);
            padding: .9rem 1.25rem;
        }
        .sd-card-title {
            font-size: .92rem;
            font-weight: 700;
            color: var(--sd-ink);
            margin: 0;
        }
        .sd-card-body {
            padding: 1rem 1.25rem;
        }

        /* ── chart wrappers ─────────────────────────────────── */
        .sd-chart-wrap {
            position: relative;
            width: 100%;
            height: 260px;
        }
        .sd-chart-wrap canvas {
            position: absolute;
            inset: 0;
        }

        /* ── health grid ────────────────────────────────────── */
        .sd-health-grid {
            display: grid;
            gap: .65rem;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        }
        .sd-health-item {
            background: var(--sd-bg);
            border: 1px solid rgba(148,163,184,.2);
            border-radius: 12px;
            padding: .75rem .9rem;
        }
        .sd-health-label {
            font-size: .68rem;
            font-weight: 600;
            color: var(--sd-muted);
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        .sd-health-value {
            font-size: .95rem;
            font-weight: 700;
            color: var(--sd-ink);
        }
        .sd-health-item.is-warn .sd-health-value { color: #b45309; }
        .sd-health-item.is-ok  .sd-health-value { color: var(--sd-green); }

        /* ── loading skeleton ───────────────────────────────── */
        .sd-skeleton {
            background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
            background-size: 200% 100%;
            animation: sd-shimmer 1.6s infinite;
            border-radius: 8px;
            height: 1.2rem;
            width: 60%;
        }
        @keyframes sd-shimmer { to { background-position: -200% 0; } }

        /* ── table ──────────────────────────────────────────── */
        .sd-table { font-size: .82rem; }
        .sd-table th {
            color: var(--sd-muted);
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            border-bottom-width: 1px;
        }

        /* ── status badges ──────────────────────────────────── */
        .sd-type-badge {
            font-size: .68rem;
            font-weight: 700;
            padding: .2rem .55rem;
            border-radius: 999px;
            border: 1px solid;
        }
        .sd-type-direct       { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
        .sd-type-marketplace  { background: #fdf4ff; color: #7c3aed; border-color: #e9d5ff; }
        .sd-type-wholesale    { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }
        .sd-type-dropshipper  { background: #fff7ed; color: #c2410c; border-color: #fed7aa; }
        .sd-type-unknown      { background: #f8fafc; color: #64748b; border-color: #e2e8f0; }

        /* ── preset range pills ─────────────────────────────────── */
        .sd-presets {
            display: flex;
            flex-wrap: nowrap;
            gap: .25rem;
            overflow-x: auto;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .sd-presets::-webkit-scrollbar { display: none; }
        .sd-preset {
            background: transparent;
            border: 1px solid rgba(22,163,74,.3);
            border-radius: 999px;
            color: #15803d;
            cursor: pointer;
            font-size: .7rem;
            font-weight: 600;
            line-height: 1;
            padding: .22rem .6rem;
            transition: background .12s, color .12s;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .sd-preset:hover,
        .sd-preset.is-active {
            background: var(--sd-green);
            border-color: var(--sd-green);
            color: #fff;
        }
        @media (max-width: 991px) {
            .sd-filter-row { flex-wrap: wrap; }
            .sd-presets { flex-wrap: wrap; overflow-x: visible; }
            #sd-start, #sd-end, #sd-type, #sd-channel { width: 100%; }
        }
    </style>

    {{-- Error alert --}}
    <div id="sd-alert" class="alert alert-danger d-none mb-3" role="alert"></div>

    {{-- No-orders info banner --}}
    <div id="sd-noorders-info" class="alert alert-warning d-none mb-3" role="alert">
        <strong>No orders found for this filter in the selected date range.</strong>
        Customers matching this filter exist, but no orders are linked to them in the current range.
        Possible reasons:
        <ul class="mb-0 mt-1">
            <li>Orders haven't been synced yet — use <strong>Sync Orders</strong> on the Orders tab.</li>
            <li>These customers placed orders outside the selected date range — try widening it.</li>
            <li>Orders may be linked to a different Shopify customer record.</li>
        </ul>
    </div>

    {{-- Compact filter bar --}}
    <div class="sd-filter-bar mb-3">
        {{-- Row 1: preset pills --}}
        <div class="sd-presets mb-2">
            <button type="button" class="sd-preset" data-preset="today">Today</button>
            <button type="button" class="sd-preset" data-preset="yesterday">Yesterday</button>
            <button type="button" class="sd-preset" data-preset="last7">7d</button>
            <button type="button" class="sd-preset is-active" data-preset="last30">30d</button>
            <button type="button" class="sd-preset" data-preset="this_week">This week</button>
            <button type="button" class="sd-preset" data-preset="last_week">Last week</button>
            <button type="button" class="sd-preset" data-preset="this_month">This month</button>
            <button type="button" class="sd-preset" data-preset="last_month">Last month</button>
            <button type="button" class="sd-preset" data-preset="this_year">This year</button>
        </div>
        {{-- Row 2: date range + filters + apply --}}
        <div class="sd-filter-row">
            <span class="small text-muted me-1" style="font-size:.72rem;white-space:nowrap;flex-shrink:0;">📅 Range</span>
            <input type="date" id="sd-start" class="form-control form-control-sm" title="From date">
            <span class="small text-muted" style="flex-shrink:0;">→</span>
            <input type="date" id="sd-end" class="form-control form-control-sm" title="To date">
            <div class="sd-filter-sep"></div>
            <select id="sd-type" class="form-select form-select-sm" title="Customer type">
                <option value="">All types</option>
                <option value="direct">Direct</option>
                <option value="marketplace">Marketplace</option>
                <option value="wholesale">Wholesale</option>
                <option value="dropshipper">Dropshipper</option>
                <option value="unknown">Unknown</option>
            </select>
            <select id="sd-channel" class="form-select form-select-sm" title="Marketplace channel">
                <option value="">All channels</option>
                @foreach ($marketplaceChannels as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <button type="button" id="sd-apply" class="btn btn-success btn-sm ms-1 flex-shrink-0" style="height:30px;font-size:.8rem;padding:.2rem .9rem;white-space:nowrap;">
                <span id="sd-apply-label">Apply</span>
                <span id="sd-apply-spin" class="spinner-border spinner-border-sm ms-1 d-none" role="status"></span>
            </button>
        </div>
    </div>

    {{-- KPI row --}}
    <div class="row g-3 mb-3">
        <div class="col-sm-6 col-xl-3">
            <div class="sd-kpi d-flex gap-3 align-items-start">
                <div class="sd-kpi-icon" style="background:#f0fdf4; color:#16a34a;">💰</div>
                <div>
                    <div class="sd-kpi-label">Revenue</div>
                    <div class="sd-kpi-value" data-kpi="revenue">—</div>
                    <div class="sd-kpi-sub">Non-cancelled orders</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="sd-kpi d-flex gap-3 align-items-start">
                <div class="sd-kpi-icon" style="background:#eff6ff; color:#2563eb;">📦</div>
                <div>
                    <div class="sd-kpi-label">Orders</div>
                    <div class="sd-kpi-value" data-kpi="total_orders">—</div>
                    <div class="sd-kpi-sub"><span data-kpi="cancelled_orders">—</span> cancelled</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="sd-kpi d-flex gap-3 align-items-start">
                <div class="sd-kpi-icon" style="background:#fdf4ff; color:#7c3aed;">📊</div>
                <div>
                    <div class="sd-kpi-label">Avg Order Value</div>
                    <div class="sd-kpi-value" data-kpi="average_order_value">—</div>
                    <div class="sd-kpi-sub">Revenue ÷ active orders</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="sd-kpi d-flex gap-3 align-items-start">
                <div class="sd-kpi-icon" style="background:#fff7ed; color:#ea580c;">👥</div>
                <div style="min-width:0">
                    <div class="sd-kpi-label">Customers (total)</div>
                    <div class="sd-kpi-value" data-kpi="total_customers">—</div>
                    <div class="sd-kpi-sub">
                        <span data-kpi="customers_with_orders">—</span> ordered in range
                        &nbsp;·&nbsp;
                        <span data-kpi="new_customers">—</span> new in range
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Trend + Customer mix --}}
    <div class="row g-3 mb-3">
        <div class="col-xl-8">
            <div class="sd-card h-100">
                <div class="sd-card-header d-flex justify-content-between align-items-center">
                    <h6 class="sd-card-title">Revenue &amp; Orders Trend</h6>
                    <span class="small text-muted" id="sd-range-label"></span>
                </div>
                <div class="sd-card-body">
                    <div class="sd-chart-wrap">
                        <canvas id="sd-trend-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="sd-card h-100">
                <div class="sd-card-header">
                    <h6 class="sd-card-title">Customer Mix</h6>
                </div>
                <div class="sd-card-body">
                    <div class="sd-chart-wrap">
                        <canvas id="sd-type-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Top customers + Data health --}}
    <div class="row g-3">
        <div class="col-xl-7">
            <div class="sd-card h-100">
                <div class="sd-card-header">
                    <h6 class="sd-card-title">Top Customers by Revenue</h6>
                </div>
                <div class="sd-card-body p-0">
                    <div class="table-responsive">
                        <table class="table sd-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-3">Customer</th>
                                    <th>Type</th>
                                    <th class="text-end">Orders</th>
                                    <th class="text-end pe-3">Revenue</th>
                                </tr>
                            </thead>
                            <tbody id="sd-top-customers">
                                <tr>
                                    <td colspan="4" class="text-muted text-center py-4">Loading…</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="sd-card h-100">
                <div class="sd-card-header">
                    <h6 class="sd-card-title">Data Health</h6>
                </div>
                <div class="sd-card-body">
                    <div class="sd-health-grid" id="sd-health-grid">
                        <div class="sd-health-item"><div class="sd-skeleton"></div></div>
                        <div class="sd-health-item"><div class="sd-skeleton"></div></div>
                        <div class="sd-health-item"><div class="sd-skeleton"></div></div>
                        <div class="sd-health-item"><div class="sd-skeleton"></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
    (function () {
        'use strict';

        const DATA_URL        = @json(route('crm.shopify.dashboard.data'));
        const CRM_CUSTOMER_URL = @json(url('/crm/customers'));

        const fmt  = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 2 });
        const fmtN = new Intl.NumberFormat('en-US');
        const charts = {};
        const PALETTE = ['#16a34a','#0ea5e9','#f97316','#8b5cf6','#14b8a6','#ef4444','#eab308','#ec4899','#64748b','#22c55e','#06b6d4','#a855f7'];

        const $ = id => document.getElementById(id);

        const sdStart  = $('sd-start');
        const sdEnd    = $('sd-end');
        const sdType   = $('sd-type');
        const sdChannel= $('sd-channel');
        const sdApply  = $('sd-apply');
        const sdApplyLabel = $('sd-apply-label');
        const sdApplySpin  = $('sd-apply-spin');
        const sdAlert  = $('sd-alert');
        const sdNoOrdersInfo = $('sd-noorders-info');
        const sdRange  = $('sd-range-label');
        const sdTopTbody = $('sd-top-customers');
        const sdHealth = $('sd-health-grid');

        // ── Date helpers ────────────────────────────────────────
        function isoDate(d) { return d.toISOString().slice(0, 10); }

        function startOfWeek(d) {
            // Monday-based week
            const r = new Date(d);
            const day = r.getDay(); // 0=Sun
            const diff = (day === 0 ? -6 : 1 - day);
            r.setDate(r.getDate() + diff);
            return r;
        }

        function presetRange(preset) {
            const now  = new Date();
            const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            let s, e;
            switch (preset) {
                case 'today':
                    s = e = today; break;
                case 'yesterday': {
                    const y = new Date(today); y.setDate(y.getDate() - 1);
                    s = e = y; break;
                }
                case 'last7': {
                    s = new Date(today); s.setDate(s.getDate() - 6);
                    e = today; break;
                }
                case 'last30': {
                    s = new Date(today); s.setDate(s.getDate() - 29);
                    e = today; break;
                }
                case 'this_week': {
                    s = startOfWeek(today);
                    e = today; break;
                }
                case 'last_week': {
                    const lws = startOfWeek(today); lws.setDate(lws.getDate() - 7);
                    const lwe = new Date(lws); lwe.setDate(lwe.getDate() + 6);
                    s = lws; e = lwe; break;
                }
                case 'this_month':
                    s = new Date(today.getFullYear(), today.getMonth(), 1);
                    e = today; break;
                case 'last_month': {
                    const lm  = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                    const lme = new Date(today.getFullYear(), today.getMonth(), 0);
                    s = lm; e = lme; break;
                }
                case 'this_year':
                    s = new Date(today.getFullYear(), 0, 1);
                    e = today; break;
                default:
                    return;
            }
            sdStart.value = isoDate(s);
            sdEnd.value   = isoDate(e);
        }

        // Default: last 30 days
        (function initDates() {
            presetRange('last30');
        })();

        // Preset pill click handler
        document.querySelectorAll('.sd-preset').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.sd-preset').forEach(b => b.classList.remove('is-active'));
                this.classList.add('is-active');
                presetRange(this.dataset.preset);
                load();
            });
        });

        // Deactivate preset pills when user manually changes dates
        [sdStart, sdEnd].forEach(el => {
            el.addEventListener('change', () => {
                document.querySelectorAll('.sd-preset').forEach(b => b.classList.remove('is-active'));
            });
        });

        function params() {
            const p = new URLSearchParams();
            if (sdStart.value)   p.set('start_date', sdStart.value);
            if (sdEnd.value)     p.set('end_date',   sdEnd.value);
            if (sdType.value)    p.set('customer_type', sdType.value);
            if (sdChannel.value) p.set('marketplace_channel', sdChannel.value);
            return p;
        }

        function setLoading(on) {
            sdApply.disabled = on;
            sdApplyLabel.textContent = on ? 'Loading…' : 'Apply';
            sdApplySpin.classList.toggle('d-none', !on);
        }

        function showError(msg) {
            sdAlert.textContent = msg;
            sdAlert.classList.remove('d-none');
        }

        function hideError() {
            sdAlert.classList.add('d-none');
            sdAlert.textContent = '';
            if (sdNoOrdersInfo) sdNoOrdersInfo.classList.add('d-none');
        }

        function esc(s) {
            return String(s ?? '').replace(/[&<>"']/g, c =>
                ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' })[c]
            );
        }

        // ── KPIs ────────────────────────────────────────────────
        function updateKpis(summary) {
            const set = (key, val) => {
                const el = document.querySelector(`[data-kpi="${key}"]`);
                if (el) el.textContent = val;
            };
            set('revenue',               fmt.format(summary.revenue || 0));
            set('total_orders',          fmtN.format(summary.total_orders || 0));
            set('cancelled_orders',      fmtN.format(summary.cancelled_orders || 0));
            set('average_order_value',   fmt.format(summary.average_order_value || 0));
            set('total_customers',       fmtN.format(summary.total_customers || 0));
            set('new_customers',         fmtN.format(summary.new_customers || 0));
            set('customers_with_orders', fmtN.format(summary.customers_with_orders || 0));

            // Show banner when customers exist but no orders found in range
            const noOrders = (summary.total_orders || 0) === 0 && (summary.total_customers || 0) > 0;
            if (sdNoOrdersInfo) sdNoOrdersInfo.classList.toggle('d-none', !noOrders);

            const kpiOrders  = document.querySelector('[data-kpi="total_orders"]');
            const kpiRevenue = document.querySelector('[data-kpi="revenue"]');
            [kpiOrders, kpiRevenue].forEach(el => {
                if (!el) return;
                const card = el.closest('.sd-kpi');
                if (card) card.style.outline = noOrders ? '2px solid #fbbf24' : '';
            });
        }

        // ── Charts ──────────────────────────────────────────────
        function mkChart(key, id, cfg) {
            const canvas = $(id);
            if (!canvas || !window.Chart) return;
            if (charts[key]) { charts[key].destroy(); delete charts[key]; }
            charts[key] = new Chart(canvas, cfg);
        }

        function renderTrend(rows) {
            mkChart('trend', 'sd-trend-chart', {
                type: 'line',
                data: {
                    labels: rows.map(r => r.date),
                    datasets: [
                        {
                            label: 'Revenue',
                            data: rows.map(r => r.revenue),
                            borderColor: '#16a34a',
                            backgroundColor: 'rgba(22,163,74,.1)',
                            fill: true,
                            tension: .4,
                            pointRadius: rows.length > 30 ? 0 : 3,
                            yAxisID: 'y',
                        },
                        {
                            label: 'Orders',
                            data: rows.map(r => r.orders),
                            borderColor: '#0ea5e9',
                            backgroundColor: 'rgba(14,165,233,.15)',
                            tension: .4,
                            pointRadius: rows.length > 30 ? 0 : 3,
                            yAxisID: 'y1',
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11 } } },
                    },
                    scales: {
                        y:  { beginAtZero: true, ticks: { callback: v => fmt.format(v), font: { size: 10 } }, grid: { color: 'rgba(0,0,0,.05)' } },
                        y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, ticks: { font: { size: 10 } } },
                        x:  { ticks: { font: { size: 10 }, maxTicksLimit: 12 }, grid: { display: false } },
                    },
                },
            });
        }

        function renderDoughnut(key, id, rows, valueKey) {
            mkChart(key, id, {
                type: 'doughnut',
                data: {
                    labels: rows.map(r => r.label),
                    datasets: [{ data: rows.map(r => r[valueKey]), backgroundColor: PALETTE, borderWidth: 2, borderColor: '#fff' }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 }, padding: 10 } },
                    },
                },
            });
        }

        // ── Top customers ───────────────────────────────────────
        function renderTopCustomers(rows) {
            if (!rows.length) {
                sdTopTbody.innerHTML = '<tr><td colspan="4" class="text-muted text-center py-5">No orders in this date range.</td></tr>';
                return;
            }
            sdTopTbody.innerHTML = rows.map(row => {
                const typeClass = 'sd-type-' + esc(row.customer_type || 'unknown');
                const nameEl = row.crm_customer_id
                    ? `<a href="${CRM_CUSTOMER_URL}/${esc(row.crm_customer_id)}" class="fw-semibold text-decoration-none">${esc(row.name)}</a>`
                    : `<span class="fw-semibold">${esc(row.name)}</span>`;
                const emailEl = row.email ? `<div class="small text-muted">${esc(row.email)}</div>` : '';
                return `<tr>
                    <td class="ps-3">${nameEl}${emailEl}</td>
                    <td><span class="sd-type-badge ${typeClass}">${esc(row.customer_type || 'unknown')}</span></td>
                    <td class="text-end">${fmtN.format(row.orders || 0)}</td>
                    <td class="text-end pe-3 fw-semibold">${fmt.format(row.revenue || 0)}</td>
                </tr>`;
            }).join('');
        }

        // ── Health grid ─────────────────────────────────────────
        function renderHealth(h) {
            const items = [
                { label: 'Last Order Sync',       value: h.last_order_sync    ? new Date(h.last_order_sync).toLocaleString()    : 'Never',  type: h.last_order_sync    ? 'ok' : 'warn' },
                { label: 'Last Customer Sync',    value: h.last_customer_sync ? new Date(h.last_customer_sync).toLocaleString() : 'Never',  type: h.last_customer_sync ? 'ok' : 'warn' },
                { label: 'Total Customers',       value: fmtN.format(h.total_customers || 0),         type: 'ok' },
                { label: 'Unknown Type',          value: fmtN.format(h.unknown_customers || 0),       type: (h.unknown_customers || 0) > 0 ? 'warn' : 'ok' },
                { label: 'Unlinked to CRM',       value: fmtN.format(h.unlinked_customers || 0),      type: (h.unlinked_customers || 0) > 0 ? 'warn' : 'ok' },
                { label: 'Missing Email',         value: fmtN.format(h.missing_email || 0),           type: (h.missing_email || 0) > 0 ? 'warn' : 'ok' },
                { label: 'Manual Overrides',      value: fmtN.format(h.manual_overrides || 0),        type: 'ok' },
                { label: 'Orders w/o Customer',   value: fmtN.format(h.orders_without_customer || 0), type: (h.orders_without_customer || 0) > 0 ? 'warn' : 'ok' },
            ];
            sdHealth.innerHTML = items.map(it =>
                `<div class="sd-health-item is-${it.type}">
                    <div class="sd-health-label">${esc(it.label)}</div>
                    <div class="sd-health-value">${esc(String(it.value))}</div>
                </div>`
            ).join('');
        }

        // ── Main load ───────────────────────────────────────────
        async function load() {
            setLoading(true);
            hideError();
            try {
                const res = await fetch(`${DATA_URL}?${params()}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });

                let json;
                try { json = await res.json(); } catch (_) { json = {}; }

                if (!res.ok) {
                    const msg = json.message || json.error || `Server error (${res.status})`;
                    showError(msg);
                    return;
                }

                if (json.error) {
                    showError(json.message || 'Dashboard data error.');
                    return;
                }

                const f = json.filters || {};
                if (f.start_date && f.end_date) {
                    sdRange.textContent = `${f.start_date}  →  ${f.end_date}`;
                }

                updateKpis(json.summary || {});
                renderTrend(json.trend || []);
                renderDoughnut('types', 'sd-type-chart', json.customer_types || [], 'value');
                renderTopCustomers(json.top_customers || []);
                renderHealth(json.health || {});

            } catch (err) {
                showError('Unable to reach the dashboard endpoint. ' + (err.message || ''));
            } finally {
                setLoading(false);
            }
        }

        sdApply.addEventListener('click', load);
        load();
    })();
    </script>
@endsection
