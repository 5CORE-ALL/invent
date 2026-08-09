{{-- Per-user dashboard card customization (editors: config/dashboard_customize.php) --}}
@php
    $dashCanCustomize = $dashCanCustomize ?? false;
    $dashPref = $dashPref ?? ['hidden_items' => [], 'custom_links' => [], 'custom_kpis' => []];
    $dashCustomKpis = $dashCustomKpis ?? [];
@endphp

<style>
    .dash-card-customize-btn {
        border: 0;
        background: transparent;
        color: #64748b;
        padding: 0 0.25rem;
        line-height: 1;
        font-size: 0.95rem;
    }
    .dash-card-customize-btn:hover { color: #0f766e; }
    .dash-item-hidden { display: none !important; }
    #dashCustomizeModal .dash-item-row {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.35rem 0;
        border-bottom: 1px solid #f1f5f9;
    }
    #dashCustomizeModal .dash-item-row label {
        margin: 0;
        font-size: 0.85rem;
        cursor: pointer;
        flex: 1;
    }
    #dashCustomizeModal .form-check-input { cursor: pointer; }
    #dashCustomizeModal .dash-add-row {
        display: grid;
        grid-template-columns: 1fr 1.4fr auto;
        gap: 0.4rem;
        margin-top: 0.5rem;
    }
    @media (max-width: 575.98px) {
        #dashCustomizeModal .dash-add-row { grid-template-columns: 1fr; }
    }
</style>

@if ($dashCanCustomize)
<div class="modal fade" id="dashCustomizeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title">
                    <i class="ri-settings-3-line me-1"></i>
                    Customize <span id="dashCustomizeCardTitle">Card</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="dashCustomizeCardId" value="">
                <p class="text-muted small mb-2">
                    Uncheck badges to hide them on your dashboard. Add page links or pin KPI badges from <code>badges_data</code>.
                </p>

                <h6 class="text-uppercase text-muted small fw-bold">Badges on this card</h6>
                <div id="dashCustomizeItemList" class="mb-3"></div>

                <h6 class="text-uppercase text-muted small fw-bold">Add page link</h6>
                <div class="dash-add-row mb-3">
                    <input type="text" id="dashLinkLabel" class="form-control form-control-sm" placeholder="Label (e.g. Video Master)">
                    <input type="text" id="dashLinkUrl" class="form-control form-control-sm" placeholder="URL (/video-master or https://…)">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="dashLinkAddBtn">Add link</button>
                </div>
                <div id="dashCustomizeLinksList" class="mb-3"></div>

                <h6 class="text-uppercase text-muted small fw-bold">Add KPI from pages</h6>
                <div class="dash-add-row mb-2">
                    <select id="dashKpiSelect" class="form-select form-select-sm">
                        <option value="">Select a KPI…</option>
                    </select>
                    <input type="text" id="dashKpiLabel" class="form-control form-control-sm" placeholder="Optional label override">
                    <button type="button" class="btn btn-sm btn-outline-success" id="dashKpiAddBtn">Add KPI</button>
                </div>
                <div id="dashCustomizeKpisList"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="dashCustomizeSaveBtn">
                    <i class="ri-save-line me-1"></i>Save for me
                </button>
            </div>
        </div>
    </div>
</div>
@endif

<script>
(function () {
    const CAN_CUSTOMIZE = @json((bool) $dashCanCustomize);
    const SAVE_URL = @json(route('dashboard.preferences.store'));
    const CSRF = @json(csrf_token());
    let prefs = @json($dashPref);
    const customKpiResolved = @json($dashCustomKpis);
    let kpiCatalog = [];
    let modal = null;
    let draftHidden = new Set();
    let draftLinks = [];
    let draftKpis = [];

    function slugify(text) {
        return String(text || '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .slice(0, 48) || 'item';
    }

    function ensureItemIds() {
        document.querySelectorAll('.dashboard-badge-panel[id]').forEach((card) => {
            const cardId = card.id;
            const used = new Set();
            card.querySelectorAll('.dashboard-badge-panel__badges > .badge').forEach((badge, idx) => {
                if (badge.dataset.dashItem) {
                    used.add(badge.dataset.dashItem);
                    return;
                }
                let base = cardId + '__' + slugify(badge.textContent);
                let id = base;
                let n = 2;
                while (used.has(id)) {
                    id = base + '-' + n;
                    n += 1;
                }
                badge.dataset.dashItem = id;
                used.add(id);
                if (!badge.dataset.dashLabel) {
                    badge.dataset.dashLabel = (badge.textContent || '').trim();
                }
            });
        });
    }

    function applyHidden() {
        const hidden = new Set(prefs.hidden_items || []);
        document.querySelectorAll('[data-dash-item]').forEach((el) => {
            const id = el.getAttribute('data-dash-item');
            el.classList.toggle('dash-item-hidden', hidden.has(id));
        });
    }

    function clearInjected(card) {
        card.querySelectorAll('.dashboard-badge-panel__badges > .badge[data-dash-injected="1"]').forEach((el) => el.remove());
    }

    function injectExtras() {
        const linksByCard = prefs.custom_links || {};
        document.querySelectorAll('.dashboard-badge-panel[id]').forEach((card) => {
            const cardId = card.id;
            const badges = card.querySelector('.dashboard-badge-panel__badges');
            if (!badges) return;
            clearInjected(card);

            (linksByCard[cardId] || []).forEach((link, i) => {
                const id = cardId + '__custom-link-' + i;
                if ((prefs.hidden_items || []).includes(id)) return;
                const span = document.createElement('span');
                span.className = 'badge fs-6 p-2';
                span.style.cssText = 'background-color:#334155;color:#fff;font-weight:bold;cursor:pointer;';
                span.dataset.dashItem = id;
                span.dataset.dashLabel = link.label;
                span.dataset.dashInjected = '1';
                span.setAttribute('role', 'button');
                span.title = link.url;
                span.textContent = link.label;
                span.addEventListener('click', () => {
                    if (String(link.url).startsWith('http')) window.open(link.url, '_blank');
                    else window.location.href = link.url;
                });
                badges.appendChild(span);
            });

            (customKpiResolved[cardId] || []).forEach((kpi) => {
                if ((prefs.hidden_items || []).includes(kpi.item_id)) return;
                const span = document.createElement('span');
                span.className = 'badge fs-6 p-2';
                span.style.cssText = 'background-color:#0f766e;color:#fff;font-weight:bold;cursor:pointer;';
                span.dataset.dashItem = kpi.item_id;
                span.dataset.dashLabel = kpi.label + ': ' + kpi.value_display;
                span.dataset.dashInjected = '1';
                span.setAttribute('data-kpi-key', kpi.key);
                span.setAttribute('data-kpi-label', kpi.label);
                if (kpi.value != null) span.setAttribute('data-kpi-value', String(kpi.value));
                span.setAttribute('role', 'button');
                span.title = kpi.label + ' — click status for rolling history';
                span.textContent = kpi.label + ': ' + kpi.value_display;
                badges.appendChild(span);
            });
        });
        if (window.DashKpiDots && typeof window.DashKpiDots.refresh === 'function') {
            window.DashKpiDots.refresh();
        }
    }

    function addCustomizeButtons() {
        if (!CAN_CUSTOMIZE) return;
        document.querySelectorAll('.dashboard-badge-panel[id] .dashboard-badge-panel__header').forEach((header) => {
            if (header.querySelector('.dash-card-customize-btn')) return;
            const card = header.closest('.dashboard-badge-panel');
            if (!card || !card.id) return;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'dash-card-customize-btn';
            btn.title = 'Customize this card';
            btn.innerHTML = '<i class="ri-settings-3-line"></i>';
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                openCustomize(card);
            });
            header.appendChild(btn);
        });
    }

    function openCustomize(card) {
        if (!CAN_CUSTOMIZE || !modal) return;
        const cardId = card.id;
        document.getElementById('dashCustomizeCardId').value = cardId;
        const title = (card.querySelector('.dashboard-badge-panel__header h6')?.childNodes[0]?.textContent || cardId).trim();
        document.getElementById('dashCustomizeCardTitle').textContent = title;

        draftHidden = new Set(prefs.hidden_items || []);
        draftLinks = JSON.parse(JSON.stringify((prefs.custom_links || {})[cardId] || []));
        draftKpis = JSON.parse(JSON.stringify((prefs.custom_kpis || {})[cardId] || []));

        const list = document.getElementById('dashCustomizeItemList');
        list.innerHTML = '';
        card.querySelectorAll('.dashboard-badge-panel__badges > .badge:not([data-dash-injected])').forEach((badge) => {
            const id = badge.dataset.dashItem;
            const label = badge.dataset.dashLabel || (badge.textContent || '').trim();
            const row = document.createElement('div');
            row.className = 'dash-item-row';
            row.innerHTML = `<input class="form-check-input" type="checkbox" id="chk_${id}" ${draftHidden.has(id) ? '' : 'checked'}>
                <label for="chk_${id}">${label.replace(/</g, '&lt;')}</label>`;
            const chk = row.querySelector('input');
            chk.addEventListener('change', () => {
                if (chk.checked) draftHidden.delete(id);
                else draftHidden.add(id);
            });
            list.appendChild(row);
        });

        renderDraftLinks();
        renderDraftKpis();
        modal.show();
    }

    function renderDraftLinks() {
        const wrap = document.getElementById('dashCustomizeLinksList');
        if (!wrap) return;
        if (!draftLinks.length) {
            wrap.innerHTML = '<div class="text-muted small">No custom page links yet.</div>';
            return;
        }
        wrap.innerHTML = draftLinks.map((l, i) => `
            <div class="dash-item-row">
                <span class="small flex-grow-1"><strong>${String(l.label).replace(/</g,'&lt;')}</strong>
                <span class="text-muted"> — ${String(l.url).replace(/</g,'&lt;')}</span></span>
                <button type="button" class="btn btn-sm btn-outline-danger" data-rm-link="${i}">Remove</button>
            </div>`).join('');
        wrap.querySelectorAll('[data-rm-link]').forEach((btn) => {
            btn.addEventListener('click', () => {
                draftLinks.splice(parseInt(btn.getAttribute('data-rm-link'), 10), 1);
                renderDraftLinks();
            });
        });
    }

    function renderDraftKpis() {
        const wrap = document.getElementById('dashCustomizeKpisList');
        if (!wrap) return;
        if (!draftKpis.length) {
            wrap.innerHTML = '<div class="text-muted small">No custom KPIs pinned yet.</div>';
            return;
        }
        wrap.innerHTML = draftKpis.map((k, i) => {
            const opt = kpiCatalog.find((o) => o.key === k.key);
            const label = k.label || (opt && opt.label) || k.key;
            return `<div class="dash-item-row">
                <span class="small flex-grow-1"><strong>${String(label).replace(/</g,'&lt;')}</strong>
                <span class="text-muted"> — ${String(k.key).replace(/</g,'&lt;')}</span></span>
                <button type="button" class="btn btn-sm btn-outline-danger" data-rm-kpi="${i}">Remove</button>
            </div>`;
        }).join('');
        wrap.querySelectorAll('[data-rm-kpi]').forEach((btn) => {
            btn.addEventListener('click', () => {
                draftKpis.splice(parseInt(btn.getAttribute('data-rm-kpi'), 10), 1);
                renderDraftKpis();
            });
        });
    }

    function fillKpiSelect() {
        const sel = document.getElementById('dashKpiSelect');
        if (!sel) return;
        const cur = sel.value;
        sel.innerHTML = '<option value="">Select a KPI…</option>';
        kpiCatalog.forEach((o) => {
            const opt = document.createElement('option');
            opt.value = o.key;
            opt.textContent = (o.label || o.key) + (o.value_display != null ? ' (' + o.value_display + ')' : '');
            sel.appendChild(opt);
        });
        if (cur) sel.value = cur;
    }

    async function loadCatalog() {
        if (!CAN_CUSTOMIZE) return;
        try {
            const res = await fetch(@json(route('dashboard.preferences.show')), {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
            });
            const data = await res.json();
            if (data && data.success) {
                prefs = data.preferences || prefs;
                kpiCatalog = data.kpi_catalog || [];
                fillKpiSelect();
            }
        } catch (e) {
            console.warn('dashboard preferences load failed', e);
        }
    }

    async function saveDraft() {
        const cardId = document.getElementById('dashCustomizeCardId').value;
        const nextLinks = Object.assign({}, prefs.custom_links || {});
        const nextKpis = Object.assign({}, prefs.custom_kpis || {});
        if (draftLinks.length) nextLinks[cardId] = draftLinks;
        else delete nextLinks[cardId];
        if (draftKpis.length) nextKpis[cardId] = draftKpis;
        else delete nextKpis[cardId];

        // Keep hidden items for other cards; replace visibility for this card's built-in badges
        const cardPrefix = cardId + '__';
        const keptHidden = (prefs.hidden_items || []).filter((id) => !String(id).startsWith(cardPrefix));
        const payload = {
            hidden_items: Array.from(new Set([...keptHidden, ...Array.from(draftHidden)])),
            custom_links: nextLinks,
            custom_kpis: nextKpis,
        };

        const btn = document.getElementById('dashCustomizeSaveBtn');
        btn.disabled = true;
        try {
            const res = await fetch(SAVE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                alert((data && data.message) || 'Failed to save preferences');
                return;
            }
            prefs = data.preferences;
            // Reload so custom KPI values resolve server-side
            window.location.reload();
        } catch (e) {
            console.error(e);
            alert('Network error while saving');
        } finally {
            btn.disabled = false;
        }
    }

    function boot() {
        ensureItemIds();
        injectExtras();
        applyHidden();
        addCustomizeButtons();

        if (CAN_CUSTOMIZE) {
            const el = document.getElementById('dashCustomizeModal');
            if (el && window.bootstrap) {
                modal = bootstrap.Modal.getOrCreateInstance(el);
            }
            loadCatalog();
            document.getElementById('dashLinkAddBtn')?.addEventListener('click', () => {
                const label = document.getElementById('dashLinkLabel').value.trim();
                const url = document.getElementById('dashLinkUrl').value.trim();
                if (!label || !url) {
                    alert('Enter both label and URL');
                    return;
                }
                draftLinks.push({ label, url });
                document.getElementById('dashLinkLabel').value = '';
                document.getElementById('dashLinkUrl').value = '';
                renderDraftLinks();
            });
            document.getElementById('dashKpiAddBtn')?.addEventListener('click', () => {
                const key = document.getElementById('dashKpiSelect').value;
                if (!key) {
                    alert('Select a KPI');
                    return;
                }
                const label = document.getElementById('dashKpiLabel').value.trim();
                const entry = { key };
                if (label) entry.label = label;
                if (!draftKpis.some((k) => k.key === key)) draftKpis.push(entry);
                document.getElementById('dashKpiLabel').value = '';
                renderDraftKpis();
            });
            document.getElementById('dashCustomizeSaveBtn')?.addEventListener('click', saveDraft);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
