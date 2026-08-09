{{-- Card-wise playback (same control pattern as /product-master) — opens modal with full card --}}
<style>
    .dash-card-playback-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem 0.75rem;
        margin: 0 0 0.5rem;
    }
    .dash-card-playback-bar .time-navigation-group,
    #dashCardPlaybackModal .time-navigation-group {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        border-radius: 50px;
        overflow: hidden;
        padding: 2px;
        background: #f8f9fa;
        display: inline-flex;
        align-items: center;
    }
    .dash-card-playback-bar .time-navigation-group button,
    #dashCardPlaybackModal .time-navigation-group button {
        padding: 0;
        border-radius: 50% !important;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 3px;
        transition: all 0.2s ease;
        border: 1px solid #dee2e6;
        background: white;
        cursor: pointer;
    }
    .dash-card-playback-bar .time-navigation-group button:hover,
    #dashCardPlaybackModal .time-navigation-group button:hover {
        background-color: #f1f3f5 !important;
        transform: scale(1.05);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    .dash-card-playback-bar .time-navigation-group button:active,
    #dashCardPlaybackModal .time-navigation-group button:active { transform: scale(0.95); }
    .dash-card-playback-bar .time-navigation-group button:disabled,
    #dashCardPlaybackModal .time-navigation-group button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none !important;
        box-shadow: none !important;
    }
    .dash-card-playback-bar .time-navigation-group button i,
    #dashCardPlaybackModal .time-navigation-group button i {
        font-size: 1.15rem;
        line-height: 1;
        display: inline-block;
    }
    .dash-card-playback-bar #dash-play-auto { color: #28a745; }
    .dash-card-playback-bar #dash-play-auto:hover {
        background-color: #28a745 !important;
        color: white !important;
    }
    .dash-card-playback-bar #dash-play-pause { color: #ffc107; display: none; }
    .dash-card-playback-bar #dash-play-pause:hover {
        background-color: #ffc107 !important;
        color: white !important;
    }
    .dash-card-playback-bar #dash-play-backward,
    .dash-card-playback-bar #dash-play-forward,
    #dashCardPlaybackModal #dash-play-backward-modal,
    #dashCardPlaybackModal #dash-play-forward-modal { color: #007bff; }
    .dash-card-playback-bar #dash-play-backward:hover,
    .dash-card-playback-bar #dash-play-forward:hover,
    #dashCardPlaybackModal #dash-play-backward-modal:hover,
    #dashCardPlaybackModal #dash-play-forward-modal:hover {
        background-color: #007bff !important;
        color: white !important;
    }
    .dash-card-playback-hint {
        font-size: 0.78rem;
        color: #6b7280;
    }
    .dashboard-badge-panel.is-dash-playback-focus {
        outline: 2px solid #0d6efd;
        outline-offset: 2px;
        box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.15) !important;
    }
    #dashCardPlaybackModal.modal {
        --tz-modal-width: 100%;
        padding-left: 0 !important;
        padding-right: 0 !important;
        /* Above sidebar (1055) and above its own backdrop — fixes black screen covering modal */
        z-index: 1065;
    }
    .modal-backdrop.dash-card-playback-backdrop {
        z-index: 1060 !important;
    }
    #dashCardPlaybackModal .modal-dialog {
        width: min(1440px, 98vw) !important;
        max-width: none !important;
        margin: 0.75rem auto !important;
    }
    #dashCardPlaybackModal .modal-content {
        border-radius: 0.75rem;
        overflow: hidden;
    }
    #dashCardPlaybackModal .modal-header {
        background: #0f172a;
        color: #fff;
        padding: 0.85rem 1.25rem;
    }
    #dashCardPlaybackModal .modal-header .modal-title {
        font-size: 1.35rem;
    }
    #dashCardPlaybackModal .modal-header .btn-close { filter: invert(1); }
    #dashCardPlaybackModal .modal-body {
        background: #f1f5f9;
        padding: 1.5rem;
        min-height: 420px;
    }
    #dashCardPlaybackStage {
        max-width: 1200px;
        margin: 0 auto;
    }
    #dashCardPlaybackStage .dashboard-badge-panel {
        min-height: 0 !important;
        height: auto !important;
        width: 100% !important;
        max-width: 100% !important;
        padding: 1.75rem !important;
        gap: 1rem !important;
        box-shadow: 0 10px 28px rgba(15, 23, 42, 0.12) !important;
    }
    #dashCardPlaybackStage .dashboard-badge-panel__icon {
        flex: 0 0 80px !important;
        width: 80px !important;
        height: 80px !important;
        min-height: 80px !important;
        border-radius: 0.5rem !important;
        font-size: 2.5rem !important;
    }
    #dashCardPlaybackStage .dashboard-badge-panel__icon-img {
        width: 80px !important;
        height: 80px !important;
    }
    #dashCardPlaybackStage .dashboard-badge-panel__header h6 {
        font-size: 1.8rem !important;
    }
    #dashCardPlaybackStage .dashboard-badge-panel__updated {
        font-size: 1.1rem !important;
    }
    #dashCardPlaybackStage .dashboard-badge-panel__badges {
        gap: 0.55rem !important;
    }
    #dashCardPlaybackStage .dashboard-badge-panel__badges .badge {
        font-size: 1.45rem !important;
        padding: 0.55rem 0.9rem !important;
        border-radius: 0.4rem !important;
        line-height: 1.25 !important;
    }
    #dashCardPlaybackStage .kpi-status-dot {
        width: 1.1rem !important;
        height: 1.1rem !important;
        margin-right: 0.55rem !important;
    }
    #dashCardPlaybackStage .dash-card-customize-btn,
    #dashCardPlaybackStage .dashboard-badge-panel__icon a {
        pointer-events: none;
    }
    #dashCardPlaybackStage .dash-card-customize-btn { display: none !important; }
    #dashCardPlaybackCounter {
        font-size: 1.1rem;
        opacity: 0.85;
        white-space: nowrap;
    }
</style>

<div class="dash-card-playback-bar">
    <div class="btn-group time-navigation-group" role="group" aria-label="Dashboard card playback">
        <button type="button" id="dash-play-backward" class="btn btn-sm btn-light" title="Previous card" disabled>
            <i class="ri-skip-back-fill"></i>
        </button>
        <button type="button" id="dash-play-pause" class="btn btn-sm btn-light" title="Stop playback" style="display: none;">
            <i class="ri-pause-fill"></i>
        </button>
        <button type="button" id="dash-play-auto" class="btn btn-sm btn-light" title="Play cards">
            <i class="ri-play-fill"></i>
        </button>
        <button type="button" id="dash-play-forward" class="btn btn-sm btn-light" title="Next card" disabled>
            <i class="ri-skip-forward-fill"></i>
        </button>
    </div>
    <span class="dash-card-playback-hint">Play to review each dashboard card in a modal</span>
</div>

<div class="modal fade" id="dashCardPlaybackModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header align-items-center gap-2">
                <div class="d-flex align-items-center gap-2 flex-grow-1 min-w-0">
                    <h6 class="modal-title mb-0 text-truncate" id="dashCardPlaybackTitle">Card</h6>
                    <span id="dashCardPlaybackCounter" class="ms-1"></span>
                </div>
                <div class="btn-group time-navigation-group me-1" role="group" aria-label="Modal card playback">
                    <button type="button" id="dash-play-backward-modal" class="btn btn-sm btn-light" title="Previous card">
                        <i class="ri-skip-back-fill"></i>
                    </button>
                    <button type="button" id="dash-play-forward-modal" class="btn btn-sm btn-light" title="Next card">
                        <i class="ri-skip-forward-fill"></i>
                    </button>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="dashCardPlaybackStage"></div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    let cards = [];
    let active = false;
    let idx = -1;
    let modal = null;

    const btnPlay = document.getElementById('dash-play-auto');
    const btnPause = document.getElementById('dash-play-pause');
    const btnPrev = document.getElementById('dash-play-backward');
    const btnNext = document.getElementById('dash-play-forward');
    const btnPrevModal = document.getElementById('dash-play-backward-modal');
    const btnNextModal = document.getElementById('dash-play-forward-modal');
    const stage = document.getElementById('dashCardPlaybackStage');
    const titleEl = document.getElementById('dashCardPlaybackTitle');
    const counterEl = document.getElementById('dashCardPlaybackCounter');
    const modalEl = document.getElementById('dashCardPlaybackModal');

    function collectCards() {
        cards = Array.from(document.querySelectorAll('.dashboard-cards-grid > .dashboard-badge-panel[id]'))
            .filter((el) =>
                !el.classList.contains('dash-item-hidden')
                && !el.classList.contains('is-dash-minimized')
                && !el.classList.contains('is-dash-snoozed')
            );
    }

    function cardTitle(card) {
        const h6 = card.querySelector('.dashboard-badge-panel__header h6');
        if (!h6) return card.id || 'Card';
        // Prefer direct text (skip nested icons/links)
        const text = Array.from(h6.childNodes)
            .filter((n) => n.nodeType === Node.TEXT_NODE)
            .map((n) => n.textContent.trim())
            .join(' ')
            .trim();
        return text || (h6.textContent || '').trim() || card.id || 'Card';
    }

    function clearFocus() {
        document.querySelectorAll('.dashboard-badge-panel.is-dash-playback-focus')
            .forEach((el) => el.classList.remove('is-dash-playback-focus'));
    }

    function updateButtons() {
        const atStart = !active || idx <= 0;
        const atEnd = !active || idx >= cards.length - 1;
        [btnPrev, btnPrevModal].forEach((b) => { if (b) b.disabled = atStart; });
        [btnNext, btnNextModal].forEach((b) => { if (b) b.disabled = atEnd; });

        if (btnPlay) {
            btnPlay.style.display = active ? 'none' : '';
            btnPlay.title = active ? 'Stop playback' : 'Play cards';
        }
        if (btnPause) {
            btnPause.style.display = active ? '' : 'none';
            btnPause.title = 'Stop playback';
        }

        if (active) {
            [btnPrev, btnNext, btnPrevModal, btnNextModal].forEach((b) => {
                if (!b) return;
                b.classList.remove('btn-light');
                b.classList.add('btn-primary');
            });
        } else {
            [btnPrev, btnNext, btnPrevModal, btnNextModal].forEach((b) => {
                if (!b) return;
                b.classList.remove('btn-primary');
                b.classList.add('btn-light');
            });
        }
    }

    function renderCurrent() {
        if (!active || idx < 0 || idx >= cards.length) return;
        const card = cards[idx];
        clearFocus();
        card.classList.add('is-dash-playback-focus');
        card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

        titleEl.textContent = cardTitle(card);
        counterEl.textContent = (idx + 1) + ' / ' + cards.length;

        const clone = card.cloneNode(true);
        clone.removeAttribute('id');
        clone.querySelectorAll('[id]').forEach((el) => el.removeAttribute('id'));
        clone.querySelectorAll('.dash-card-customize-btn, .dash-card-actions').forEach((el) => el.remove());
        // Clones copy data-kpi-wired but NOT listeners — clear so dots re-wire for graph modal
        clone.querySelectorAll('.kpi-status-dot').forEach((dot) => {
            delete dot.dataset.kpiWired;
        });
        stage.innerHTML = '';
        stage.appendChild(clone);

        // Wire KPI dots on the modal clone so graph opens from dots
        if (window.DashKpiDots && typeof window.DashKpiDots.ensureDot === 'function') {
            clone.querySelectorAll('.badge[data-kpi-key]').forEach((badge) => {
                window.DashKpiDots.ensureDot(badge, badge.dataset.kpiTone || 'gray');
            });
        }

        updateButtons();

        if (modal && !modalEl.classList.contains('show')) {
            modal.show();
        }
    }

    function ensureModal() {
        if (!modal) {
            modal = bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: true, keyboard: true });
            modalEl.addEventListener('shown.bs.modal', function () {
                const backs = document.querySelectorAll('.modal-backdrop');
                const last = backs[backs.length - 1];
                if (last) {
                    last.classList.add('dash-card-playback-backdrop');
                    // Ensure backdrop stays under this modal (never covers the card)
                    last.style.zIndex = '1060';
                }
                modalEl.style.zIndex = '1065';
            });
        }
        return modal;
    }

    function start() {
        collectCards();
        if (!cards.length) return;
        active = true;
        idx = 0;
        ensureModal();
        renderCurrent();
        modal.show();
        updateButtons();
    }

    /** Expand a specific card in the playback modal. */
    function openCard(cardId) {
        if (!cardId) return;
        const el = document.getElementById(cardId);
        if (el && (el.classList.contains('is-dash-minimized') || el.classList.contains('is-dash-snoozed'))) {
            if (window.DashCardActions && typeof window.DashCardActions.restoreCard === 'function') {
                window.DashCardActions.restoreCard(cardId);
            }
        }
        collectCards();
        let i = cards.findIndex((c) => c.id === cardId);
        if (i < 0 && el && !el.classList.contains('is-dash-minimized') && !el.classList.contains('is-dash-snoozed')) {
            // Card exists but was filtered — include it temporarily
            cards = [el, ...cards.filter((c) => c.id !== cardId)];
            i = 0;
        }
        if (i < 0) return;
        active = true;
        idx = i;
        ensureModal();
        renderCurrent();
        modal.show();
        updateButtons();
    }

    function stop() {
        active = false;
        idx = -1;
        clearFocus();
        stage.innerHTML = '';
        titleEl.textContent = 'Card';
        counterEl.textContent = '';
        updateButtons();
        if (modal && modalEl.classList.contains('show')) {
            modal.hide();
        }
    }

    function next() {
        if (!active || idx >= cards.length - 1) return;
        idx++;
        renderCurrent();
    }

    function prev() {
        if (!active || idx <= 0) return;
        idx--;
        renderCurrent();
    }

    btnPlay?.addEventListener('click', start);
    btnPause?.addEventListener('click', stop);
    btnNext?.addEventListener('click', next);
    btnPrev?.addEventListener('click', prev);
    btnNextModal?.addEventListener('click', next);
    btnPrevModal?.addEventListener('click', prev);

    modalEl?.addEventListener('hidden.bs.modal', function () {
        if (active) {
            // Closing modal stops playback (same as pause)
            active = false;
            idx = -1;
            clearFocus();
            stage.innerHTML = '';
            updateButtons();
        }
    });

    // Keyboard: ← → while modal open
    document.addEventListener('keydown', function (e) {
        if (!active || !modalEl.classList.contains('show')) return;
        if (e.key === 'ArrowRight') {
            e.preventDefault();
            next();
        } else if (e.key === 'ArrowLeft') {
            e.preventDefault();
            prev();
        } else if (e.key === 'Escape') {
            // bootstrap handles dismiss; stop via hidden handler
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        collectCards();
        updateButtons();
    });

    window.DashCardPlayback = { start, stop, next, prev, openCard, collectCards };
})();
</script>
