{{-- World clocks: same topbar row, center (between left tools and right menu) --}}
<style>
    .topbar-world-clocks {
        flex: 1 1 0;
        min-width: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        padding: 0 0.5rem;
        overflow: hidden;
    }
    .topbar-world-clocks .wc-zone {
        flex: 1 1 0;
        min-width: 0;
        max-width: 11rem;
        text-align: left;
    }
    .topbar-world-clocks .wc-label {
        font-size: 0.55rem;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #5c6b7a;
        line-height: 1.1;
        margin-bottom: 0;
    }
    .topbar-world-clocks .wc-time {
        font-size: clamp(0.8rem, 1.5vw, 1rem);
        font-weight: 700;
        color: #0b2545;
        line-height: 1.15;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .topbar-world-clocks .wc-meta {
        font-size: 0.6rem;
        color: #5c6b7a;
        line-height: 1.1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .topbar-world-clocks .wc-divider {
        flex: 0 0 1px;
        width: 1px;
        align-self: stretch;
        max-height: 2.5rem;
        background-color: rgba(0, 0, 0, 0.08);
        margin: 0 0.15rem;
    }
    html[data-bs-theme="dark"] .topbar-world-clocks .wc-label,
    html[data-bs-theme="dark"] .topbar-world-clocks .wc-meta {
        color: rgba(255, 255, 255, 0.55);
    }
    html[data-bs-theme="dark"] .topbar-world-clocks .wc-time {
        color: rgba(255, 255, 255, 0.95);
    }
    html[data-bs-theme="dark"] .topbar-world-clocks .wc-divider {
        background-color: rgba(255, 255, 255, 0.12);
    }
</style>
<div class="topbar-world-clocks d-none d-lg-flex" aria-label="Office time zones">
    <div class="wc-zone">
        <div class="wc-label">California</div>
        <div class="wc-time" id="wc-ca-time">—</div>
        <div class="wc-meta" id="wc-ca-meta"></div>
    </div>
    <div class="wc-divider" aria-hidden="true"></div>
    <div class="wc-zone">
        <div class="wc-label">India</div>
        <div class="wc-time" id="wc-in-time">—</div>
        <div class="wc-meta" id="wc-in-meta"></div>
    </div>
    <div class="wc-divider" aria-hidden="true"></div>
    <div class="wc-zone">
        <div class="wc-label">Ohio (Eastern)</div>
        <div class="wc-time" id="wc-oh-time">—</div>
        <div class="wc-meta" id="wc-oh-meta"></div>
    </div>
</div>
<script>
(function () {
    var zones = [
        { prefix: 'wc-ca', tz: 'America/Los_Angeles' },
        { prefix: 'wc-in', tz: 'Asia/Kolkata' },
        { prefix: 'wc-oh', tz: 'America/New_York' }
    ];
    function tzAbbrev(now, timeZone) {
        var style = timeZone === 'Asia/Kolkata' ? 'longOffset' : 'short';
        var parts = new Intl.DateTimeFormat('en-US', {
            timeZone: timeZone,
            timeZoneName: style
        }).formatToParts(now);
        for (var i = 0; i < parts.length; i++) {
            if (parts[i].type === 'timeZoneName') {
                return parts[i].value;
            }
        }
        return '';
    }
    function tick() {
        var now = new Date();
        zones.forEach(function (z) {
            var timeEl = document.getElementById(z.prefix + '-time');
            var metaEl = document.getElementById(z.prefix + '-meta');
            if (!timeEl || !metaEl) {
                return;
            }
            var timeStr = new Intl.DateTimeFormat('en-US', {
                timeZone: z.tz,
                hour: 'numeric',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            }).format(now);
            var dateStr = new Intl.DateTimeFormat('en-US', {
                timeZone: z.tz,
                weekday: 'short',
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            }).format(now);
            var tz = tzAbbrev(now, z.tz);
            timeEl.textContent = timeStr;
            metaEl.textContent = dateStr + (tz ? ' · ' + tz : '');
        });
    }
    tick();
    setInterval(tick, 1000);
})();
</script>
