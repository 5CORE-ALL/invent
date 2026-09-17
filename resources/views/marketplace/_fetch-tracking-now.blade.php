@php
    $ftNowSlug = $fetchTrackingMarketplace ?? $slug ?? '';
@endphp
@if($ftNowSlug !== '')
<button type="button" class="btn btn-sm btn-outline-warning btn-mm-fetch-tracking-now"
    data-url="{{ url('marketplace/'.$ftNowSlug.'/orders/fetch-tracking-now') }}"
    title="Check Veeqo and GOFO for every marketplace, write tracking to unfulfilled Shopify copies, and mark them fulfilled. The Shopify customer is not emailed.">
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
    if (!confirm('Fetch tracking from Veeqo and GOFO (4Seller) now, fulfill those Shopify copies, and push tracking to every marketplace (AliExpress, Temu 2, etc.)?\n\nThe Shopify customer will not be emailed. This can take a minute.')) return;
    var original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="ri-loader-4-line"></i> Fetching…';
    fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
    })
    .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
    .then(function (res) {
        alert((res.data && res.data.message) || (res.ok ? 'Queued' : 'Failed'));
        if (res.data && res.data.success) location.reload();
    })
    .catch(function () { alert('Request failed.'); })
    .finally(function () {
        btn.disabled = false;
        btn.innerHTML = original;
    });
});
</script>
@endonce
@endif
