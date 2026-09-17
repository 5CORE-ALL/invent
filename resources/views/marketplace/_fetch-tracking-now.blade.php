@php
    $ftNowSlug = $fetchTrackingMarketplace ?? $slug ?? '';
    $ftNowUrl = $fetchTrackingUrl ?? ($ftNowSlug !== '' ? url('marketplace/'.$ftNowSlug.'/orders/fetch-tracking-now') : '');
@endphp
@if($ftNowUrl !== '')
<button type="button" class="btn btn-sm btn-outline-warning btn-mm-fetch-tracking-now"
    data-url="{{ $ftNowUrl }}"
    title="Queue a fresh Veeqo/GOFO catch-up: fulfill unfulfilled Shopify copies, then push tracking to every marketplace. The Shopify customer is not emailed.">
    <i class="ri-truck-line"></i> Fetch tracking now
</button>
@once
<script>
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.btn-mm-fetch-tracking-now');
    if (!btn || btn.disabled) return;
    var url = btn.getAttribute('data-url');
    if (!url) return;
    e.preventDefault();
    if (!confirm('Queue a fresh tracking catch-up now?\n\nShopify copies will fulfill first (no customer email), then AliExpress, Temu 2, and the other marketplaces. This returns immediately; refresh in 2–3 minutes.')) return;
    var original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="ri-loader-4-line"></i> Queueing…';
    fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    })
    .then(function (r) {
        return r.text().then(function (text) {
            var data = {};
            try { data = text ? JSON.parse(text) : {}; } catch (err) {
                data = { message: 'HTTP ' + r.status + (text ? ': ' + text.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 180) : '') };
            }
            return { ok: r.ok, data: data };
        });
    })
    .then(function (res) {
        alert((res.data && res.data.message) || (res.ok ? 'Queued. Refresh in 2–3 minutes.' : 'Failed'));
        if (res.data && res.data.success) location.reload();
    })
    .catch(function (err) { alert('Request failed: ' + (err && err.message ? err.message : 'network error')); })
    .finally(function () {
        btn.disabled = false;
        btn.innerHTML = original;
    });
});
</script>
@endonce
@endif
