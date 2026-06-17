@php
    $infoPageKey = $pageKey ?? '';
    $infoModalId = 'purchase-page-info-modal-' . preg_replace('/[^a-z0-9_-]/', '-', $infoPageKey);
@endphp
<button type="button"
    class="btn btn-sm btn-primary fw-semibold d-inline-flex align-items-center gap-1 shadow-sm position-relative purchase-page-info-badge"
    data-page-key="{{ $infoPageKey }}"
    data-bs-toggle="modal"
    data-bs-target="#{{ $infoModalId }}"
    title="Page information"
    aria-label="Page information">
    <i class="fas fa-info" aria-hidden="true"></i>
    <span>i</span>
    <span class="purchase-page-info-dot purchase-page-info-dot--empty" aria-hidden="true"></span>
</button>

<div class="modal fade purchase-page-info-modal" id="{{ $infoModalId }}" tabindex="-1" aria-labelledby="{{ $infoModalId }}-label" aria-hidden="true" data-page-key="{{ $infoPageKey }}">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center flex-wrap gap-2" id="{{ $infoModalId }}-label">
                    <i class="fas fa-info text-primary" aria-hidden="true"></i>
                    <span>Page Information</span>
                    <span class="badge bg-primary-subtle text-primary-emphasis purchase-page-info-edit-badge d-none">
                        <i class="bi bi-pencil-square me-1"></i> Edit Mode
                    </span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="sop-mode-hint purchase-page-info-hint"></div>

            <div class="modal-body">
                <div class="sop-viewer purchase-page-info-viewer"
                     data-empty-hint="An authorized user can click Edit to add content."></div>
                <textarea class="sop-editor purchase-page-info-editor d-none"
                          spellcheck="false"
                          placeholder="Paste content copied from ChatGPT here...&#10;&#10;Both Markdown and raw HTML are supported.&#10; • Markdown:   # Heading,  ![image](url),  - bullet,  | table | ...&#10; • HTML:       &lt;h1&gt;Heading&lt;/h1&gt; &lt;img src=&quot;url&quot;&gt; ...&#10;&#10;The viewer renders it as a formatted document (real headings, real images), never as raw code."></textarea>
                <div class="purchase-page-info-meta text-muted small mt-2 d-none"></div>
            </div>

            <div class="modal-footer d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="sop-footer-status purchase-page-info-status"></div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary purchase-page-info-edit-btn d-none">
                        <i class="bi bi-pencil-square me-1"></i> Edit
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary purchase-page-info-cancel-btn d-none">
                        Cancel
                    </button>
                    <button type="button" class="btn btn-sm btn-primary purchase-page-info-save-btn d-none">
                        <i class="bi bi-save me-1"></i> Save
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

@include('shared.partials.sop-rich-content')

@once
<style>
    .purchase-page-info-badge {
        border-radius: 6px;
        white-space: nowrap;
    }
    .purchase-page-info-dot {
        position: absolute;
        top: -4px;
        right: -4px;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        border: 2px solid #fff;
        box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.15);
        pointer-events: none;
    }
    .purchase-page-info-dot--empty { background: #dc3545; }
    .purchase-page-info-dot--filled { background: #198754; }
</style>
<script>
(function () {
    if (window.__purchasePageInfoInit) return;
    window.__purchasePageInfoInit = true;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    function setDot(badge, hasContent) {
        const dot = badge?.querySelector('.purchase-page-info-dot');
        if (!dot) return;
        dot.classList.toggle('purchase-page-info-dot--empty', !hasContent);
        dot.classList.toggle('purchase-page-info-dot--filled', !!hasContent);
    }

    function setStatus(modal, msg, kind) {
        const el = modal.querySelector('.purchase-page-info-status');
        if (!el) return;
        el.classList.remove('is-error', 'is-success');
        el.textContent = msg || '';
        if (kind === 'error') el.classList.add('is-error');
        if (kind === 'success') el.classList.add('is-success');
    }

    async function fetchPayload(pageKey) {
        const res = await fetch('/purchase-page-info/' + encodeURIComponent(pageKey), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!res.ok) throw new Error('Failed to load page information');
        return res.json();
    }

    async function saveContent(pageKey, content) {
        const res = await fetch('/purchase-page-info/' + encodeURIComponent(pageKey), {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ html_content: content }),
        });
        const data = await res.json().catch(function () { return {}; });
        if (!res.ok) throw new Error(data.message || 'Save failed');
        return data;
    }

    function renderViewer(modal, raw) {
        const viewer = modal.querySelector('.purchase-page-info-viewer');
        if (window.SopRichContent && viewer) {
            window.SopRichContent.renderInto(viewer, raw || '');
        } else if (viewer) {
            viewer.textContent = raw || '';
        }
    }

    function setHint(modal, isEditing, canEdit) {
        const hint = modal.querySelector('.purchase-page-info-hint');
        if (!hint) return;
        if (isEditing) {
            hint.innerHTML = '<i class="bi bi-pencil-square me-1"></i> <strong>Edit mode.</strong> Paste <strong>Markdown</strong> or <strong>HTML</strong> copied from ChatGPT below, then click <em>Save</em>. The viewer renders it as a formatted document. Only <code>purchase@5core.com</code> and <code>president@5core.com</code> can save.';
        } else {
            hint.innerHTML = '<i class="bi bi-eye me-1"></i> Read-only view.' + (canEdit
                ? ' Click <strong>Edit</strong> (or double-click the Info button) to update this page information.'
                : ' Contact an admin to update this page information.');
        }
    }

    function showViewerMode(modal, state) {
        const viewer = modal.querySelector('.purchase-page-info-viewer');
        const editor = modal.querySelector('.purchase-page-info-editor');
        const editBtn = modal.querySelector('.purchase-page-info-edit-btn');
        const cancelBtn = modal.querySelector('.purchase-page-info-cancel-btn');
        const saveBtn = modal.querySelector('.purchase-page-info-save-btn');
        const editBadge = modal.querySelector('.purchase-page-info-edit-badge');

        state.isEditing = false;
        viewer?.classList.remove('d-none');
        editor?.classList.add('d-none');
        editBtn?.classList.toggle('d-none', !state.canEdit);
        cancelBtn?.classList.add('d-none');
        saveBtn?.classList.add('d-none');
        editBadge?.classList.add('d-none');
        setHint(modal, false, state.canEdit);
        renderViewer(modal, state.currentContent);
    }

    function showEditorMode(modal, state) {
        if (!state.canEdit) {
            setStatus(modal, 'You are not authorized to edit page information.', 'error');
            return;
        }
        const viewer = modal.querySelector('.purchase-page-info-viewer');
        const editor = modal.querySelector('.purchase-page-info-editor');
        const editBtn = modal.querySelector('.purchase-page-info-edit-btn');
        const cancelBtn = modal.querySelector('.purchase-page-info-cancel-btn');
        const saveBtn = modal.querySelector('.purchase-page-info-save-btn');
        const editBadge = modal.querySelector('.purchase-page-info-edit-badge');

        state.isEditing = true;
        if (editor) editor.value = state.currentContent || '';
        viewer?.classList.add('d-none');
        editor?.classList.remove('d-none');
        editBtn?.classList.add('d-none');
        cancelBtn?.classList.remove('d-none');
        saveBtn?.classList.remove('d-none');
        editBadge?.classList.remove('d-none');
        setHint(modal, true, true);
        setStatus(modal, '', null);
        setTimeout(function () { editor?.focus(); }, 50);
    }

    function applyPayload(modal, state, payload) {
        state.currentContent = payload.content || '';
        state.canEdit = !!payload.can_edit;

        const meta = modal.querySelector('.purchase-page-info-meta');
        if (meta) {
            if (payload.updated_by) {
                meta.textContent = 'Last updated by ' + payload.updated_by + (payload.updated_at ? ' · ' + new Date(payload.updated_at).toLocaleString() : '');
                meta.classList.remove('d-none');
            } else {
                meta.classList.add('d-none');
                meta.textContent = '';
            }
        }

        showViewerMode(modal, state);
    }

    function initBadge(badge) {
        const pageKey = badge.getAttribute('data-page-key');
        if (!pageKey || badge.dataset.infoReady === '1') return;
        badge.dataset.infoReady = '1';

        fetchPayload(pageKey).then(function (payload) {
            setDot(badge, payload.has_content);
            badge.dataset.canEdit = payload.can_edit ? '1' : '0';
        }).catch(function () {
            setDot(badge, false);
            badge.dataset.canEdit = '0';
        });

        badge.addEventListener('dblclick', function () {
            if (badge.dataset.canEdit === '1') {
                badge.dataset.forceEdit = '1';
            } else {
                badge.dataset.forceEdit = 'denied';
            }
        });
    }

    function initModal(modal) {
        const pageKey = modal.getAttribute('data-page-key');
        if (!pageKey || modal.dataset.infoModalReady === '1') return;
        modal.dataset.infoModalReady = '1';

        const badge = document.querySelector('.purchase-page-info-badge[data-page-key="' + pageKey + '"]');
        const editBtn = modal.querySelector('.purchase-page-info-edit-btn');
        const cancelBtn = modal.querySelector('.purchase-page-info-cancel-btn');
        const saveBtn = modal.querySelector('.purchase-page-info-save-btn');
        const editor = modal.querySelector('.purchase-page-info-editor');

        const state = {
            currentContent: '',
            canEdit: false,
            isEditing: false,
            saving: false,
        };

        modal.addEventListener('show.bs.modal', function () {
            setStatus(modal, '', null);
            fetchPayload(pageKey).then(function (payload) {
                applyPayload(modal, state, payload);
                setDot(badge, payload.has_content);
                if (badge?.dataset.canEdit !== undefined) {
                    badge.dataset.canEdit = payload.can_edit ? '1' : '0';
                }
                if (badge?.dataset.forceEdit === '1' && state.canEdit) {
                    showEditorMode(modal, state);
                } else if (badge?.dataset.forceEdit === 'denied') {
                    setStatus(modal, 'Only purchase@5core.com and president@5core.com can edit page information.', 'error');
                }
                if (badge) badge.dataset.forceEdit = '';
            }).catch(function () {
                setStatus(modal, 'Could not load page information.', 'error');
            });
        });

        modal.addEventListener('hidden.bs.modal', function () {
            showViewerMode(modal, state);
            setStatus(modal, '', null);
        });

        editBtn?.addEventListener('click', function () {
            showEditorMode(modal, state);
        });

        cancelBtn?.addEventListener('click', function () {
            showViewerMode(modal, state);
        });

        saveBtn?.addEventListener('click', async function () {
            if (state.saving) return;
            state.saving = true;
            saveBtn.disabled = true;
            setStatus(modal, 'Saving…', null);
            try {
                const result = await saveContent(pageKey, editor?.value ?? '');
                const payload = result.data || await fetchPayload(pageKey);
                applyPayload(modal, state, payload);
                setDot(badge, payload.has_content);
                setStatus(modal, 'Saved.', 'success');
            } catch (err) {
                setStatus(modal, err.message || 'Could not save page information.', 'error');
            } finally {
                state.saving = false;
                saveBtn.disabled = false;
            }
        });
    }

    function boot() {
        document.querySelectorAll('.purchase-page-info-badge[data-page-key]').forEach(initBadge);
        document.querySelectorAll('.purchase-page-info-modal[data-page-key]').forEach(initModal);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
@endonce
