{{--
    "Last updated" badge for LQS pages. Pass $lqsLastUpdated (Carbon|null) = when the LQS data last changed.
    Pages can call window.lqsTouchLastUpdated() after a successful upload / refresh to show "now".
--}}
@php
    /** @var \Carbon\Carbon|null $lqsLastUpdated */
    $lqsLastUpdated = $lqsLastUpdated ?? null;
@endphp
<span class="badge fs-6 p-2" id="lqs-last-updated-badge"
      style="font-weight:700;background:#6c757d;color:#fff;white-space:nowrap;"
      title="{{ $lqsLastUpdated ? 'LQS data last changed '.$lqsLastUpdated->diffForHumans() : 'No LQS data has been saved yet' }}">
    <i class="far fa-clock me-1"></i>Last updated: <span id="lqs-last-updated-text">{{ $lqsLastUpdated ? $lqsLastUpdated->format('d M Y, h:i A') : '–' }}</span>
</span>
<script>
    window.lqsTouchLastUpdated = function () {
        // Same shape as the server value: "06 Nov 2026, 02:30 AM"
        var d = new Date();
        var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        var pad = function (n) { return (n < 10 ? '0' : '') + n; };
        var h = d.getHours() % 12 || 12;
        var text = pad(d.getDate()) + ' ' + months[d.getMonth()] + ' ' + d.getFullYear()
            + ', ' + pad(h) + ':' + pad(d.getMinutes()) + ' ' + (d.getHours() < 12 ? 'AM' : 'PM');
        var el = document.getElementById('lqs-last-updated-text');
        var badge = document.getElementById('lqs-last-updated-badge');
        if (el) { el.textContent = text; }
        if (badge) { badge.title = 'LQS data last changed just now'; }
    };
</script>
