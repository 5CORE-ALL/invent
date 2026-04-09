@extends('layouts.vertical', ['title' => $customer->name.' — Customer', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @include('crm.communications.partials.timeline-styles')
    <style>
        #crm-customer-tab-pane { min-height: 220px; position: relative; }
        .crm-tab-loading-overlay {
            position: absolute;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(var(--bs-body-bg-rgb), 0.65);
            z-index: 2;
        }
        .crm-tab-loading-overlay.is-visible { display: flex; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => $customer->name,
        'sub_title' => 'Customer #'.$customer->id.($customer->email ? ' · '.$customer->email : ''),
    ])

    <div class="mb-3 d-flex flex-wrap gap-2">
        <a href="{{ route('crm.follow-ups.index', ['customer_id' => $customer->id]) }}" class="btn btn-outline-secondary btn-sm">Follow-ups list</a>
        <a href="{{ route('crm.customers.communications.index', $customer) }}" class="btn btn-light btn-sm">Timeline (full page)</a>
    </div>

    <ul class="nav nav-tabs" id="crmCustomerTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link active" data-crm-tab="overview" aria-selected="true">Overview</button>
        </li>
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link" data-crm-tab="follow-ups" aria-selected="false">Follow-ups</button>
        </li>
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link" data-crm-tab="communications" aria-selected="false">Communications</button>
        </li>
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link" data-crm-tab="shopify-data" aria-selected="false">Shopify data</button>
        </li>
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link" data-crm-tab="orders" aria-selected="false">Orders</button>
        </li>
    </ul>

    <div class="border border-top-0 rounded-bottom bg-body p-3 position-relative" id="crm-customer-tab-pane">
        <div class="crm-tab-loading-overlay is-visible" id="crm-customer-tab-loading" aria-hidden="false">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading…</span>
            </div>
        </div>
        <div id="crm-customer-tab-body"></div>
    </div>

    <script>
        (function () {
            const tabUrls = {
                overview: @json(route('crm.customers.tabs', [$customer, 'overview'])),
                'follow-ups': @json(route('crm.customers.tabs', [$customer, 'follow-ups'])),
                communications: @json(route('crm.customers.tabs', [$customer, 'communications'])),
                'shopify-data': @json(route('crm.customers.tabs', [$customer, 'shopify-data'])),
                orders: @json(route('crm.customers.tabs', [$customer, 'orders'])),
            };

            const cache = new Map();
            const bodyEl = document.getElementById('crm-customer-tab-body');
            const loadingEl = document.getElementById('crm-customer-tab-loading');
            const tabButtons = document.querySelectorAll('[data-crm-tab]');
            let activeTab = 'overview';

            function setLoading(show) {
                loadingEl.classList.toggle('is-visible', show);
                loadingEl.setAttribute('aria-hidden', show ? 'false' : 'true');
            }

            async function loadTab(tab, fetchUrl) {
                const url = fetchUrl || tabUrls[tab];
                if (!url) return;

                if (!fetchUrl && cache.has(tab)) {
                    bodyEl.innerHTML = cache.get(tab);
                    return;
                }

                setLoading(true);
                try {
                    const res = await fetch(url, {
                        credentials: 'same-origin',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'text/html',
                        },
                    });
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    const html = await res.text();
                    if (!fetchUrl) {
                        cache.set(tab, html);
                    }
                    bodyEl.innerHTML = html;
                } catch (e) {
                    bodyEl.innerHTML = '<div class="alert alert-danger mb-0">Could not load this tab. Please try again.</div>';
                } finally {
                    setLoading(false);
                }
            }

            tabButtons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const tab = btn.getAttribute('data-crm-tab');
                    if (!tab || tab === activeTab) return;
                    activeTab = tab;
                    tabButtons.forEach(function (b) {
                        const on = b.getAttribute('data-crm-tab') === tab;
                        b.classList.toggle('active', on);
                        b.setAttribute('aria-selected', on ? 'true' : 'false');
                    });
                    loadTab(tab);
                });
            });

            bodyEl.addEventListener('click', function (e) {
                const link = e.target.closest('a');
                if (!link || !link.getAttribute('href')) return;
                const pagination = link.closest('.crm-pagination, .pagination');
                if (!pagination || !bodyEl.contains(link)) return;
                e.preventDefault();
                const tab = activeTab;
                loadTab(tab, link.href);
            });

            loadTab('overview');
        })();
    </script>
@endsection
