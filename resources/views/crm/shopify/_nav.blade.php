@php($active = $active ?? 'customers')

{{-- Nav progress bar --}}
<style>
    #shopify-nav-bar {
        position: fixed; top: 0; left: 0; z-index: 9999;
        height: 3px; width: 0%;
        background: linear-gradient(90deg, #16a34a, #0ea5e9);
        border-radius: 0 2px 2px 0;
        transition: width .12s ease, opacity .3s ease;
        opacity: 0;
        pointer-events: none;
    }
    #shopify-nav-bar.is-loading { opacity: 1; }

    /* ── Compact Shopify page header ─────────────────────── */
    .page-title-box .page-title {
        line-height: 46px;
    }
    .page-title-box .page-title-right {
        margin-top: 10px;
    }
    .page-title-box .breadcrumb {
        padding-top: 4px;
    }

    /* ── Shopify tab bar ─────────────────────────────────── */
    .shopify-nav {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        border-bottom: 1px solid rgba(15,23,42,.1);
        background: transparent;
    }
    .shopify-nav .nav-link {
        position: relative;
        border: 0;
        border-radius: 0;
        padding: .35rem 0 .55rem;
        font-size: .82rem;
        font-weight: 600;
        color: #64748b;
        transition: color .15s ease;
    }
    .shopify-nav .nav-link::before {
        content: '';
        position: absolute;
        left: 0;
        right: 0;
        bottom: -1px;
        height: 2px;
        background: #16a34a;
        border-radius: 999px 999px 0 0;
        transform: scaleX(0);
        transform-origin: center;
        transition: transform .16s ease;
    }
    .shopify-nav .nav-link:hover {
        color: #15803d;
    }
    .shopify-nav .nav-link.active {
        color: #0f172a;
    }
    .shopify-nav .nav-link.active::before,
    .shopify-nav .nav-link:hover::before {
        transform: scaleX(1);
    }
    .shopify-nav .nav-link.is-navigating::after {
        content: '';
        display: inline-block;
        width: .65rem; height: .65rem;
        border: 2px solid currentColor;
        border-top-color: transparent;
        border-radius: 50%;
        animation: nav-spin .6s linear infinite;
        margin-left: .4rem;
        vertical-align: middle;
        opacity: .7;
    }
    @keyframes nav-spin { to { transform: rotate(360deg); } }
</style>
<div id="shopify-nav-bar"></div>

<nav class="shopify-nav mb-2" role="tablist">
    <a href="{{ route('crm.shopify.dashboard') }}"
       class="nav-link @if ($active === 'dashboard') active @endif"
       @if ($active === 'dashboard') aria-current="page" @endif>
        Dashboard
    </a>
    <a href="{{ route('crm.shopify.customers.index') }}"
       class="nav-link @if ($active === 'customers') active @endif"
       @if ($active === 'customers') aria-current="page" @endif>
        B2B Customers
    </a>
    <a href="{{ route('crm.shopify.others.index') }}"
       class="nav-link @if ($active === 'others') active @endif"
       @if ($active === 'others') aria-current="page" @endif>
        Marketplace Customers
    </a>
    <a href="{{ route('crm.shopify.orders.index') }}"
       class="nav-link @if ($active === 'orders') active @endif"
       @if ($active === 'orders') aria-current="page" @endif>
        Orders
    </a>
</nav>

<script>
(function () {
    const bar  = document.getElementById('shopify-nav-bar');
    let raf, pct = 0, timer = null;

    function setWidth(w) {
        if (bar) bar.style.width = w + '%';
    }

    function tick() {
        // Ease toward 90% — never reaches 100 until page unloads
        pct += (90 - pct) * 0.06;
        setWidth(pct);
        raf = requestAnimationFrame(tick);
    }

    function start() {
        cancelAnimationFrame(raf);
        clearTimeout(timer);
        pct = 8;
        setWidth(pct);
        if (bar) bar.classList.add('is-loading');
        raf = requestAnimationFrame(tick);
    }

    function finish() {
        cancelAnimationFrame(raf);
        setWidth(100);
        timer = setTimeout(function () {
            if (bar) { bar.classList.remove('is-loading'); setWidth(0); }
        }, 300);
    }

    // Attach to every nav-tab link
    document.querySelectorAll('.shopify-nav .nav-link').forEach(function (link) {
        link.addEventListener('click', function (e) {
            // Skip if already on this tab or modifier key
            if (link.classList.contains('active') || e.ctrlKey || e.metaKey || e.shiftKey) return;
            // Show spinner on the clicked tab
            link.classList.add('is-navigating');
            start();
        });
    });

    // Complete the bar when the new page starts loading
    window.addEventListener('beforeunload', finish);
})();
</script>
