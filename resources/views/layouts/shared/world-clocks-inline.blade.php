{{-- World clock: Eastern (EDT/EST) only, same topbar row --}}
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
        flex: 0 1 auto;
        min-width: 0;
        max-width: 16rem;
        text-align: left;
    }
    .topbar-world-clocks .wc-flag-row {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        line-height: 1.15;
        margin-bottom: 0;
    }
    .topbar-world-clocks .wc-code {
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        color: #5c6b7a;
        font-variant-numeric: tabular-nums;
    }
    .topbar-world-clocks .wc-code.wc-place-name {
        text-transform: none;
        letter-spacing: 0.02em;
        font-size: 0.6rem;
    }
    .topbar-world-clocks .wc-flag-img {
        width: 1.125rem;
        height: 0.85rem;
        object-fit: cover;
        display: block;
        flex-shrink: 0;
        border-radius: 2px;
        box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.06);
    }
    .topbar-world-clocks .wc-time-row {
        display: flex;
        align-items: baseline;
        gap: 0.3rem;
        white-space: nowrap;
        min-width: 0;
    }
    .topbar-world-clocks .wc-time {
        font-size: clamp(0.8rem, 1.5vw, 1rem);
        font-weight: 700;
        color: #0b2545;
        line-height: 1.15;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .topbar-world-clocks .wc-tz {
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        color: #5c6b7a;
        line-height: 1.15;
        flex-shrink: 0;
    }
    .topbar-world-clocks .wc-meta {
        font-size: 0.6rem;
        color: #5c6b7a;
        line-height: 1.1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    html[data-bs-theme="dark"] .topbar-world-clocks .wc-code,
    html[data-bs-theme="dark"] .topbar-world-clocks .wc-meta,
    html[data-bs-theme="dark"] .topbar-world-clocks .wc-tz {
        color: rgba(255, 255, 255, 0.55);
    }
    html[data-bs-theme="dark"] .topbar-world-clocks .wc-time {
        color: rgba(255, 255, 255, 0.95);
    }
    html[data-bs-theme="dark"] .topbar-world-clocks .wc-flag-img {
        box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.12);
    }
</style>
<div class="topbar-world-clocks d-none d-lg-flex" aria-label="Eastern time">
    <div class="wc-zone">
        <div class="wc-flag-row" role="img" aria-label="Eastern Time, United States (EDT)">
            <span class="wc-code wc-place-name">Eastern</span>
            <img class="wc-flag-img" src="https://flagcdn.com/w40/us.png" width="18" height="14" alt="" decoding="async" loading="eager">
        </div>
        <div class="wc-time-row">
            <div class="wc-time" id="wc-edt-time">—</div>
            <span class="wc-tz" id="wc-edt-tz">EDT</span>
        </div>
        <div class="wc-meta" id="wc-edt-meta"></div>
    </div>
</div>
<script>
(function () {
    var tz = 'America/New_York';
    function tzAbbrev(now) {
        var parts = new Intl.DateTimeFormat('en-US', {
            timeZone: tz,
            timeZoneName: 'short'
        }).formatToParts(now);
        for (var i = 0; i < parts.length; i++) {
            if (parts[i].type === 'timeZoneName') {
                var name = parts[i].value;
                if (name === 'GMT-4' || name === 'UTC-4') return 'EDT';
                if (name === 'GMT-5' || name === 'UTC-5') return 'EST';
                return name;
            }
        }
        return 'EDT';
    }
    function tick() {
        var now = new Date();
        var timeEl = document.getElementById('wc-edt-time');
        var tzEl = document.getElementById('wc-edt-tz');
        var metaEl = document.getElementById('wc-edt-meta');
        if (!timeEl || !metaEl) {
            return;
        }
        timeEl.textContent = new Intl.DateTimeFormat('en-US', {
            timeZone: tz,
            hour: 'numeric',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
        }).format(now);
        var abbrev = tzAbbrev(now);
        if (tzEl) {
            tzEl.textContent = abbrev;
        }
        metaEl.textContent = new Intl.DateTimeFormat('en-US', {
            timeZone: tz,
            weekday: 'short',
            month: 'short',
            day: 'numeric'
        }).format(now);
    }
    tick();
    setInterval(tick, 1000);
})();
</script>
