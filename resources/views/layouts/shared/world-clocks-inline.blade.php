{{-- World clocks: same topbar row, center (between left tools and right menu) --}}
<style>
    .topbar-world-clocks {
        flex: 1 1 0;
        min-width: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.15rem;
        padding: 0 0.25rem;
        overflow: hidden;
    }
    .topbar-world-clocks .wc-zone {
        flex: 0 0 auto;
        min-width: 0;
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
    /* Raster flags: emoji regional indicators often render as letter pairs on Windows/Linux (looks like duplicate "US" next to .wc-code). */
    .topbar-world-clocks .wc-flag-img {
        width: 1.125rem;
        height: 0.85rem;
        object-fit: cover;
        display: block;
        flex-shrink: 0;
        border-radius: 2px;
        box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.06);
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
        margin: 0;
    }
    html[data-bs-theme="dark"] .topbar-world-clocks .wc-code,
    html[data-bs-theme="dark"] .topbar-world-clocks .wc-meta {
        color: rgba(255, 255, 255, 0.55);
    }
    html[data-bs-theme="dark"] .topbar-world-clocks .wc-time {
        color: rgba(255, 255, 255, 0.95);
    }
    html[data-bs-theme="dark"] .topbar-world-clocks .wc-divider {
        background-color: rgba(255, 255, 255, 0.12);
    }
    html[data-bs-theme="dark"] .topbar-world-clocks .wc-flag-img {
        box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.12);
    }
    /* Clickable single clock in the topbar */
    .topbar-world-clocks .wc-zone-btn {
        cursor: pointer;
        border: 0;
        background: transparent;
        padding: 0.2rem 0.5rem;
        border-radius: 6px;
        transition: background-color 0.15s ease;
    }
    .topbar-world-clocks .wc-zone-btn:hover,
    .topbar-world-clocks .wc-zone-btn:focus-visible {
        background-color: rgba(0, 0, 0, 0.05);
        outline: none;
    }
    html[data-bs-theme="dark"] .topbar-world-clocks .wc-zone-btn:hover,
    html[data-bs-theme="dark"] .topbar-world-clocks .wc-zone-btn:focus-visible {
        background-color: rgba(255, 255, 255, 0.08);
    }
    /* Modal with all clocks */
    #worldClocksModal .wc-modal-card {
        border: 1px solid rgba(0, 0, 0, 0.08);
        border-radius: 10px;
        padding: 0.9rem 1rem;
        height: 100%;
    }
    #worldClocksModal .wc-modal-card.wc-current {
        border-color: #0d6efd;
        box-shadow: 0 0 0 1px #0d6efd inset;
    }
    #worldClocksModal .wc-modal-name {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.85rem;
        font-weight: 700;
        color: #5c6b7a;
    }
    #worldClocksModal .wc-modal-name img {
        width: 1.4rem;
        height: 1.05rem;
        object-fit: cover;
        border-radius: 2px;
        box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.08);
    }
    #worldClocksModal .wc-modal-time {
        font-size: 1.75rem;
        font-weight: 700;
        color: #0b2545;
        font-variant-numeric: tabular-nums;
        line-height: 1.2;
    }
    #worldClocksModal .wc-modal-meta {
        font-size: 0.8rem;
        color: #5c6b7a;
    }
    html[data-bs-theme="dark"] #worldClocksModal .wc-modal-card {
        border-color: rgba(255, 255, 255, 0.15);
    }
    html[data-bs-theme="dark"] #worldClocksModal .wc-modal-time {
        color: rgba(255, 255, 255, 0.95);
    }
    html[data-bs-theme="dark"] #worldClocksModal .wc-modal-name,
    html[data-bs-theme="dark"] #worldClocksModal .wc-modal-meta {
        color: rgba(255, 255, 255, 0.6);
    }
</style>
@php
    $worldClockZones = [
        ['prefix' => 'wc-ca', 'name' => 'California', 'tz' => 'America/Los_Angeles', 'flag' => 'us'],
        ['prefix' => 'wc-in', 'name' => 'India',      'tz' => 'Asia/Kolkata',        'flag' => 'in'],
        ['prefix' => 'wc-oh', 'name' => 'Ohio',       'tz' => 'America/New_York',    'flag' => 'us'],
        ['prefix' => 'wc-cn', 'name' => 'China',      'tz' => 'Asia/Shanghai',       'flag' => 'cn'],
    ];
@endphp
{{-- Single, relevant clock (defaults to India; JS switches to the viewer's own zone if it is one of ours) --}}
<div class="topbar-world-clocks d-none d-lg-flex" aria-label="Office time zones">
    <button type="button" class="wc-zone wc-zone-btn" id="wc-top-btn"
            data-bs-toggle="modal" data-bs-target="#worldClocksModal"
            title="Click to view all country clocks" aria-label="Open all country clocks">
        <div class="wc-flag-row">
            <span class="wc-code wc-place-name" id="wc-top-name">India</span>
            <img class="wc-flag-img" id="wc-top-flag" src="https://flagcdn.com/w40/in.png" width="18" height="14" alt="" decoding="async" loading="eager">
        </div>
        <div class="wc-time" id="wc-top-time">—</div>
        <div class="wc-meta" id="wc-top-meta"></div>
    </button>
</div>

{{-- Modal with all clocks (moved to <body> by JS so the topbar's stacking context can't clip it) --}}
<div class="modal fade" id="worldClocksModal" tabindex="-1" aria-labelledby="worldClocksModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="worldClocksModalLabel">World Clocks</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    @foreach ($worldClockZones as $z)
                        <div class="col-12 col-sm-6">
                            <div class="wc-modal-card" data-wc-tz="{{ $z['tz'] }}">
                                <div class="wc-modal-name">
                                    <span>{{ $z['name'] }}</span>
                                    <img src="https://flagcdn.com/w40/{{ $z['flag'] }}.png" alt="" loading="lazy">
                                </div>
                                <div class="wc-modal-time" id="{{ $z['prefix'] }}-time">—</div>
                                <div class="wc-modal-meta" id="{{ $z['prefix'] }}-meta"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var zones = [
        @foreach ($worldClockZones as $z)
        { prefix: '{{ $z['prefix'] }}', name: '{{ $z['name'] }}', tz: '{{ $z['tz'] }}', flag: '{{ $z['flag'] }}' },
        @endforeach
    ];

    // Browser timezone aliases -> one of our zones
    var tzAliases = {
        'Asia/Calcutta': 'Asia/Kolkata',
        'America/Detroit': 'America/New_York',
        'America/Kentucky/Louisville': 'America/New_York',
        'Asia/Chongqing': 'Asia/Shanghai',
        'Asia/Harbin': 'Asia/Shanghai'
    };
    var defaultTz = 'Asia/Kolkata';

    // Move modal to <body> so it is not clipped by the topbar
    var modalEl = document.getElementById('worldClocksModal');
    if (modalEl && modalEl.parentNode !== document.body) {
        document.body.appendChild(modalEl);
    }

    function detectRelevantZone() {
        var local = '';
        try { local = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) { /* ignore */ }
        local = tzAliases[local] || local;
        for (var i = 0; i < zones.length; i++) {
            if (zones[i].tz === local) { return zones[i]; }
        }
        for (var j = 0; j < zones.length; j++) {
            if (zones[j].tz === defaultTz) { return zones[j]; }
        }
        return zones[0];
    }
    var relevant = detectRelevantZone();

    var topName = document.getElementById('wc-top-name');
    var topFlag = document.getElementById('wc-top-flag');
    if (topName) { topName.textContent = relevant.name; }
    if (topFlag) { topFlag.src = 'https://flagcdn.com/w40/' + relevant.flag + '.png'; }

    // Highlight the viewer's own zone inside the modal
    if (modalEl) {
        var cards = modalEl.querySelectorAll('.wc-modal-card');
        for (var c = 0; c < cards.length; c++) {
            if (cards[c].getAttribute('data-wc-tz') === relevant.tz) {
                cards[c].classList.add('wc-current');
            }
        }
    }
    function tzAbbrev(now, timeZone) {
        // Asia/Kolkata: browsers often emit GMT+05:30 (IST is ambiguous with Israel/Ireland).
        // Keep the short office label consistent with PDT / EDT / CST.
        if (timeZone === 'Asia/Kolkata') {
            return 'IST';
        }
        try {
            var parts = new Intl.DateTimeFormat('en-US', {
                timeZone: timeZone,
                timeZoneName: 'short'
            }).formatToParts(now);
            for (var i = 0; i < parts.length; i++) {
                if (parts[i].type === 'timeZoneName') {
                    return parts[i].value;
                }
            }
        } catch (e) { /* ignore */ }
        return '';
    }
    function tick() {
        var now = new Date();
        var targets = zones.map(function (z) {
            return { tz: z.tz, timeId: z.prefix + '-time', metaId: z.prefix + '-meta' };
        });
        // Topbar clock shows only the relevant zone
        targets.push({ tz: relevant.tz, timeId: 'wc-top-time', metaId: 'wc-top-meta' });
        targets.forEach(function (z) {
            var timeEl = document.getElementById(z.timeId);
            var metaEl = document.getElementById(z.metaId);
            if (!timeEl || !metaEl) {
                return;
            }
            try {
                var timeStr = new Intl.DateTimeFormat('en-US', {
                    timeZone: z.tz,
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                }).format(now);
                var dateStr = new Intl.DateTimeFormat('en-US', {
                    timeZone: z.tz,
                    weekday: 'short',
                    month: 'short',
                    day: 'numeric'
                }).format(now);
                var tz = tzAbbrev(now, z.tz);
                timeEl.textContent = timeStr;
                metaEl.textContent = dateStr + (tz ? ' · ' + tz : '');
            } catch (e) {
                if (z.tz === 'Asia/Kolkata') {
                    timeEl.textContent = timeEl.textContent === '—' ? 'IST' : timeEl.textContent;
                }
            }
        });
    }
    tick();
    setInterval(tick, 1000);
})();
</script>
