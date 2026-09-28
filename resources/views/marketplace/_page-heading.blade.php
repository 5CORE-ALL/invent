{{--
  Marketplace page heading with logo before the title.
  @param string $slug    Marketplace slug (aliexpress|alibaba|reverb)
  @param string $heading Page title text
  @param string $mt      Bootstrap margin-top class (default: mt-2)
  @param string $mb      Bootstrap margin-bottom class (default: mb-1)
--}}
@php
    $slug = strtolower($slug ?? '');
    $heading = $heading ?? '';
    $mt = $mt ?? 'mt-2';
    $mb = $mb ?? 'mb-1';
    $logoUrl = \App\Services\MarketplaceManager\MarketplaceManagerRegistry::logoUrl($slug);
@endphp
<div class="d-flex align-items-center gap-2 {{ $mt }} {{ $mb }}">
    @if($logoUrl)
        <img src="{{ $logoUrl }}" alt="{{ ucfirst($slug) }}" class="mm-mp-logo" width="32" height="32"
             style="width:32px;height:32px;object-fit:contain;flex-shrink:0;"
             onerror="this.style.display='none'">
    @endif
    <h4 class="mb-0">{{ $heading }}</h4>
    @if(str_contains(strtolower($heading), 'orders'))
        <div class="ms-auto d-flex gap-2 align-items-center">
            <button type="button" class="btn btn-sm btn-success" id="mm-push-tracking-btn" data-slug="{{ $slug }}">
                <i class="ri-truck-line"></i> Push tracking
            </button>
            @include('marketplace._fetch-tracking-now', ['fetchTrackingMarketplace' => $slug])
        </div>
    @endif
</div>
@if(session('success'))
    <div class="alert alert-success py-2">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger py-2">{{ session('error') }}</div>
@endif
@if(str_contains(strtolower($heading), 'orders'))
<div id="mm-push-tracking-overlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:2000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:22px 24px;width:min(420px,92vw);box-shadow:0 12px 40px rgba(0,0,0,.18);">
        <div style="font-weight:600;margin-bottom:10px;">Pushing tracking</div>
        <div style="height:10px;background:#e9ecef;border-radius:99px;overflow:hidden;">
            <div id="mm-push-tracking-bar" style="height:100%;width:0%;background:#198754;transition:width .2s;"></div>
        </div>
        <div id="mm-push-tracking-pct" style="margin-top:8px;font-size:20px;font-weight:700;">0%</div>
        <div id="mm-push-tracking-msg" class="text-muted small" style="margin-top:6px;">Starting…</div>
    </div>
</div>
<script>
(function () {
    if (window.__mmPushTrackingBound) return;
    window.__mmPushTrackingBound = true;
    const btn = document.getElementById('mm-push-tracking-btn');
    if (!btn) return;
    const overlay = document.getElementById('mm-push-tracking-overlay');
    const bar = document.getElementById('mm-push-tracking-bar');
    const pctEl = document.getElementById('mm-push-tracking-pct');
    const msgEl = document.getElementById('mm-push-tracking-msg');
    const url = @json(url('/marketplace-manager/push-tracking')) + '/' + btn.dataset.slug;
    const token = @json(csrf_token());

    function paint(pct, msg) {
        const n = Math.max(0, Math.min(100, pct));
        bar.style.width = n + '%';
        pctEl.textContent = n + '%';
        msgEl.textContent = msg;
    }

    btn.addEventListener('click', async function () {
        if (btn.disabled) return;
        btn.disabled = true;
        overlay.style.display = 'flex';
        paint(0, 'Looking up orders…');
        let initial = null;
        let pushed = 0;
        let skipped = 0;
        let failed = 0;
        let rounds = 0;
        let stall = 0;
        try {
            while (rounds < 200) {
                rounds++;
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ limit: 2 }),
                });
                const data = await res.json().catch(function () { return {}; });
                pushed += Number(data.pushed || 0);
                skipped += Number(data.skipped || 0);
                failed += Number(data.failed || 0);
                if (initial === null && data.pending_before != null) {
                    initial = Number(data.pending_before);
                }
                const left = data.pending_after == null ? null : Number(data.pending_after);
                let pct = 0;
                if (data.finished || left === 0) {
                    pct = 100;
                } else if (initial && left != null) {
                    pct = Math.min(99, Math.round(((initial - left) / initial) * 100));
                } else {
                    pct = Math.min(95, rounds * 5);
                }
                paint(pct, 'Shipped ' + pushed + ', skipped ' + skipped + ', failed ' + failed + (data.message ? '. ' + data.message : ''));
                if (data.finished || Number(data.checked || 0) === 0) break;
                if (Number(data.pushed || 0) === 0 && Number(data.failed || 0) === 0) {
                    stall++;
                    if (stall >= 2) break;
                } else {
                    stall = 0;
                }
            }
            paint(100, 'Done. Shipped ' + pushed + ', skipped ' + skipped + ', failed ' + failed + '.');
        } catch (err) {
            paint(100, 'Stopped: ' + (err && err.message ? err.message : 'request failed'));
        }
        setTimeout(function () {
            overlay.style.display = 'none';
            btn.disabled = false;
            window.location.reload();
        }, 1200);
    });
})();
</script>
@endif
