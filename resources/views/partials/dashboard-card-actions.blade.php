{{-- Per-card expand / minimize / snooze-until-midnight-PST --}}
<style>
    .dash-card-actions {
        display: inline-flex;
        align-items: center;
        gap: 0.15rem;
        margin-left: auto;
    }
    .dash-card-actions button {
        border: 0;
        background: transparent;
        color: #64748b;
        padding: 0.1rem 0.2rem;
        line-height: 1;
        font-size: 1rem;
        border-radius: 0.25rem;
    }
    .dash-card-actions button:hover {
        color: #0f172a;
        background: #f1f5f9;
    }
    .dash-card-actions .dash-card-expand-btn:hover { color: #2563eb; }
    .dash-card-actions .dash-card-minimize-btn:hover { color: #ca8a04; }
    .dash-card-actions .dash-card-snooze-btn:hover { color: #7c3aed; }
    .dashboard-badge-panel.is-dash-minimized,
    .dashboard-badge-panel.is-dash-snoozed {
        display: none !important;
    }
    .dash-card-restore-bar {
        display: none;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.4rem;
        margin: 0 0 0.65rem;
        padding: 0.4rem 0.55rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
    }
    .dash-card-restore-bar.is-visible { display: flex; }
    .dash-card-restore-bar__label {
        font-size: 0.75rem;
        color: #64748b;
        font-weight: 600;
        margin-right: 0.15rem;
    }
    .dash-card-restore-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #334155;
        border-radius: 999px;
        padding: 0.2rem 0.55rem 0.2rem 0.45rem;
        font-size: 0.75rem;
        font-weight: 600;
        line-height: 1.2;
        cursor: pointer;
    }
    .dash-card-restore-chip:hover {
        border-color: #94a3b8;
        background: #f8fafc;
    }
    .dash-card-restore-chip--snooze {
        border-color: #ddd6fe;
        background: #f5f3ff;
        color: #5b21b6;
    }
    .dash-card-restore-chip i { font-size: 0.9rem; }
    #dashCardPlaybackStage .dash-card-actions { display: none !important; }
</style>

<div id="dashCardRestoreBar" class="dash-card-restore-bar" aria-live="polite">
    <span class="dash-card-restore-bar__label">Hidden cards</span>
    <div id="dashCardRestoreChips" class="d-flex flex-wrap gap-1"></div>
</div>

<script>
(function () {
    const STORAGE_MIN = 'dashCardMinimizedV1';
    const STORAGE_SNOOZE = 'dashCardSnoozedUntilV1';
    const TZ = 'America/Los_Angeles';

    function readJson(key, fallback) {
        try {
            const raw = localStorage.getItem(key);
            if (!raw) return fallback;
            const parsed = JSON.parse(raw);
            return parsed == null ? fallback : parsed;
        } catch (e) {
            return fallback;
        }
    }

    function writeJson(key, value) {
        try { localStorage.setItem(key, JSON.stringify(value)); } catch (e) { /* ignore */ }
    }

    function cardTitle(card) {
        const h6 = card.querySelector('.dashboard-badge-panel__header h6');
        if (!h6) return card.id || 'Card';
        const text = Array.from(h6.childNodes)
            .filter((n) => n.nodeType === Node.TEXT_NODE)
            .map((n) => n.textContent.trim())
            .join(' ')
            .trim();
        return text || (h6.textContent || '').trim() || card.id || 'Card';
    }

    function getParts(ms, timeZone) {
        const parts = new Intl.DateTimeFormat('en-US', {
            timeZone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hourCycle: 'h23',
        }).formatToParts(new Date(ms));
        const map = {};
        parts.forEach((p) => {
            if (p.type !== 'literal') map[p.type] = p.value;
        });
        return {
            year: parseInt(map.year, 10),
            month: parseInt(map.month, 10),
            day: parseInt(map.day, 10),
            hour: parseInt(map.hour, 10),
            minute: parseInt(map.minute, 10),
            second: parseInt(map.second, 10),
        };
    }

    /** Next calendar midnight in America/Los_Angeles (PST/PDT). */
    function nextMidnightPstMs(fromMs) {
        const now = fromMs == null ? Date.now() : fromMs;
        const cur = getParts(now, TZ);
        // Target: next local calendar day 00:00:00
        const noonUtcGuess = Date.UTC(cur.year, cur.month - 1, cur.day, 20, 0, 0); // ~noon–evening LA
        let lo = now + 1000;
        let hi = now + 40 * 3600 * 1000;
        // Prefer search around tomorrow
        let best = null;
        for (let t = lo; t <= hi; t += 60 * 1000) {
            const p = getParts(t, TZ);
            if (p.hour === 0 && p.minute === 0 && (p.day !== cur.day || p.month !== cur.month || p.year !== cur.year)) {
                best = t - (p.second * 1000);
                break;
            }
        }
        if (best != null) return best;

        // Fallback: ~ms until local midnight ignoring DST edge cases
        const msSince = ((cur.hour * 60 + cur.minute) * 60 + cur.second) * 1000;
        return now + (24 * 3600 * 1000 - msSince);
    }

    function formatPstCountdown(untilMs) {
        const p = getParts(untilMs, TZ);
        const hh = String(p.hour).padStart(2, '0');
        const mm = String(p.minute).padStart(2, '0');
        return 'until ' + p.month + '/' + p.day + ' ' + hh + ':' + mm + ' PT';
    }

    function allCards() {
        return Array.from(document.querySelectorAll('.dashboard-cards-grid > .dashboard-badge-panel[id]'));
    }

    function getMinimized() {
        const list = readJson(STORAGE_MIN, []);
        return Array.isArray(list) ? list.filter((id) => typeof id === 'string') : [];
    }

    function setMinimized(list) {
        writeJson(STORAGE_MIN, Array.from(new Set(list)));
    }

    function getSnoozed() {
        const map = readJson(STORAGE_SNOOZE, {});
        return map && typeof map === 'object' ? map : {};
    }

    function setSnoozed(map) {
        writeJson(STORAGE_SNOOZE, map);
    }

    function purgeExpiredSnoozes() {
        const now = Date.now();
        const map = getSnoozed();
        let changed = false;
        Object.keys(map).forEach((id) => {
            const until = Number(map[id]);
            if (!until || until <= now) {
                delete map[id];
                changed = true;
            }
        });
        if (changed) setSnoozed(map);
        return map;
    }

    function applyVisibility() {
        const minimized = new Set(getMinimized());
        const snoozed = purgeExpiredSnoozes();
        allCards().forEach((card) => {
            const id = card.id;
            const isSnoozed = snoozed[id] && Number(snoozed[id]) > Date.now();
            const isMin = minimized.has(id);
            card.classList.toggle('is-dash-snoozed', !!isSnoozed);
            card.classList.toggle('is-dash-minimized', !!isMin && !isSnoozed);
        });
        renderRestoreBar();
        if (window.DashCardPlayback && typeof window.DashCardPlayback.collectCards === 'function') {
            window.DashCardPlayback.collectCards();
        }
    }

    function renderRestoreBar() {
        const bar = document.getElementById('dashCardRestoreBar');
        const wrap = document.getElementById('dashCardRestoreChips');
        if (!bar || !wrap) return;

        const minimized = getMinimized();
        const snoozed = purgeExpiredSnoozes();
        wrap.innerHTML = '';

        const entries = [];
        minimized.forEach((id) => {
            if (snoozed[id] && Number(snoozed[id]) > Date.now()) return;
            const card = document.getElementById(id);
            if (!card) return;
            entries.push({ id, title: cardTitle(card), kind: 'min' });
        });
        Object.keys(snoozed).forEach((id) => {
            const until = Number(snoozed[id]);
            if (!until || until <= Date.now()) return;
            const card = document.getElementById(id);
            if (!card) return;
            entries.push({ id, title: cardTitle(card), kind: 'snooze', until });
        });

        if (!entries.length) {
            bar.classList.remove('is-visible');
            return;
        }

        bar.classList.add('is-visible');
        entries.forEach((item) => {
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'dash-card-restore-chip' + (item.kind === 'snooze' ? ' dash-card-restore-chip--snooze' : '');
            chip.title = item.kind === 'snooze'
                ? ('Snoozed ' + formatPstCountdown(item.until) + ' — click to show now')
                : 'Click to restore card';
            chip.innerHTML = item.kind === 'snooze'
                ? '<i class="ri-timer-line"></i><span></span>'
                : '<i class="ri-eye-line"></i><span></span>';
            chip.querySelector('span').textContent = item.kind === 'snooze'
                ? (item.title + ' · ' + formatPstCountdown(item.until))
                : item.title;
            chip.addEventListener('click', () => restoreCard(item.id));
            wrap.appendChild(chip);
        });
    }

    function minimizeCard(cardId) {
        const list = getMinimized().filter((id) => id !== cardId);
        list.push(cardId);
        setMinimized(list);
        // Clear snooze if any — plain minimize wins
        const snoozed = getSnoozed();
        if (snoozed[cardId]) {
            delete snoozed[cardId];
            setSnoozed(snoozed);
        }
        applyVisibility();
    }

    function snoozeCard(cardId) {
        const until = nextMidnightPstMs();
        const snoozed = getSnoozed();
        snoozed[cardId] = until;
        setSnoozed(snoozed);
        // Remove from plain minimized set (snooze state is separate)
        setMinimized(getMinimized().filter((id) => id !== cardId));
        applyVisibility();
        scheduleSnoozeWake();
    }

    function restoreCard(cardId) {
        setMinimized(getMinimized().filter((id) => id !== cardId));
        const snoozed = getSnoozed();
        if (snoozed[cardId]) {
            delete snoozed[cardId];
            setSnoozed(snoozed);
        }
        applyVisibility();
        const card = document.getElementById(cardId);
        if (card) {
            card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            card.classList.add('is-dash-playback-focus');
            setTimeout(() => card.classList.remove('is-dash-playback-focus'), 1200);
        }
    }

    function expandCard(card) {
        if (window.DashCardPlayback && typeof window.DashCardPlayback.openCard === 'function') {
            window.DashCardPlayback.openCard(card.id);
            return;
        }
        // Fallback: start playback
        if (window.DashCardPlayback && typeof window.DashCardPlayback.start === 'function') {
            window.DashCardPlayback.start();
        }
    }

    function addActionButtons() {
        allCards().forEach((card) => {
            const header = card.querySelector('.dashboard-badge-panel__header');
            if (!header || header.querySelector('.dash-card-actions')) return;

            const wrap = document.createElement('div');
            wrap.className = 'dash-card-actions';
            wrap.setAttribute('role', 'group');
            wrap.setAttribute('aria-label', 'Card actions');

            const expandBtn = document.createElement('button');
            expandBtn.type = 'button';
            expandBtn.className = 'dash-card-expand-btn';
            expandBtn.title = 'Expand card';
            expandBtn.innerHTML = '<i class="ri-fullscreen-line"></i>';
            expandBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                expandCard(card);
            });

            const minBtn = document.createElement('button');
            minBtn.type = 'button';
            minBtn.className = 'dash-card-minimize-btn';
            minBtn.title = 'Minimize card';
            minBtn.innerHTML = '<i class="ri-subtract-line"></i>';
            minBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                minimizeCard(card.id);
            });

            const snoozeBtn = document.createElement('button');
            snoozeBtn.type = 'button';
            snoozeBtn.className = 'dash-card-snooze-btn';
            snoozeBtn.title = 'Hide until midnight PT (PST/PDT)';
            snoozeBtn.innerHTML = '<i class="ri-timer-line"></i>';
            snoozeBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                snoozeCard(card.id);
            });

            wrap.appendChild(expandBtn);
            wrap.appendChild(minBtn);
            wrap.appendChild(snoozeBtn);

            // Keep customize gear after actions if present; else append actions at end
            const gear = header.querySelector('.dash-card-customize-btn');
            if (gear) header.insertBefore(wrap, gear);
            else header.appendChild(wrap);
        });
    }

    let snoozeTimer = null;
    function scheduleSnoozeWake() {
        if (snoozeTimer) {
            clearTimeout(snoozeTimer);
            snoozeTimer = null;
        }
        const snoozed = purgeExpiredSnoozes();
        const times = Object.values(snoozed).map(Number).filter((n) => n > Date.now());
        if (!times.length) return;
        const next = Math.min(...times);
        const delay = Math.min(next - Date.now() + 250, 2147483647);
        snoozeTimer = setTimeout(() => {
            applyVisibility();
            scheduleSnoozeWake();
        }, Math.max(delay, 1000));
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Restore bar sits above the grid — move it if misplaced
        const bar = document.getElementById('dashCardRestoreBar');
        const grid = document.querySelector('.dashboard-cards-grid');
        if (bar && grid && bar.nextElementSibling !== grid) {
            grid.parentNode.insertBefore(bar, grid);
        }
        addActionButtons();
        applyVisibility();
        scheduleSnoozeWake();
        // Re-run shortly in case customize injects gear after us
        setTimeout(addActionButtons, 300);
    });

    window.DashCardActions = {
        minimizeCard,
        snoozeCard,
        restoreCard,
        expandCard,
        applyVisibility,
        nextMidnightPstMs,
    };
})();
</script>
