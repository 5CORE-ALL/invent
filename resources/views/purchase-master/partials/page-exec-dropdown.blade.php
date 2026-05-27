@php
    $pageExecKey = $pageKey ?? '';
@endphp
<div class="page-exec-control d-inline-flex align-items-center gap-1 flex-nowrap" data-page-key="{{ $pageExecKey }}" title="Assigned executive for this page">
    <label class="mb-0 small fw-semibold text-nowrap text-secondary" for="page-exec-select-{{ $pageExecKey }}">Exec</label>
    <select id="page-exec-select-{{ $pageExecKey }}" class="form-select form-select-sm page-exec-select" style="width: 115px; min-width: 100px;" disabled aria-label="Page executive assignment">
        <option value="">Loading…</option>
    </select>
    <input type="text" class="form-control form-control-sm page-exec-add-input d-none" style="width: 88px;" placeholder="Name" maxlength="64" aria-label="Add executive name">
    <button type="button" class="btn btn-sm btn-outline-secondary page-exec-add-btn d-none py-0 px-2" title="Add to list">+</button>
    <button type="button" class="btn btn-sm btn-outline-secondary page-exec-toggle-add d-none py-0 px-2" title="Add executive name">+</button>
</div>

@once
<style>
    .page-exec-control {
        max-width: 100%;
        flex: 0 0 auto;
    }
    .page-exec-control .page-exec-select,
    .page-exec-control .page-exec-add-input {
        height: 31px;
        min-height: 31px;
        padding-top: 0.2rem;
        padding-bottom: 0.2rem;
    }
    .page-exec-control .page-exec-toggle-add,
    .page-exec-control .page-exec-add-btn {
        line-height: 1;
        min-width: 28px;
        height: 31px;
    }
</style>
<script>
(function () {
    if (window.__purchasePageExecInit) return;
    window.__purchasePageExecInit = true;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    function renderSelect(select, options, assigned, canEdit) {
        select.innerHTML = '';
        const blank = document.createElement('option');
        blank.value = '';
        blank.textContent = '— Unassigned —';
        select.appendChild(blank);

        (options || []).forEach(function (name) {
            const opt = document.createElement('option');
            opt.value = name;
            opt.textContent = name;
            if (assigned && assigned === name) {
                opt.selected = true;
            }
            select.appendChild(opt);
        });

        select.disabled = !canEdit;
    }

    function setAddUi(root, showInput) {
        const addInput = root.querySelector('.page-exec-add-input');
        const addBtn = root.querySelector('.page-exec-add-btn');
        const toggleBtn = root.querySelector('.page-exec-toggle-add');
        if (showInput) {
            addInput?.classList.remove('d-none');
            addBtn?.classList.remove('d-none');
            toggleBtn?.classList.add('d-none');
            addInput?.focus();
        } else {
            addInput?.classList.add('d-none');
            addBtn?.classList.add('d-none');
            toggleBtn?.classList.remove('d-none');
            if (addInput) addInput.value = '';
        }
    }

    async function fetchPayload(pageKey) {
        const res = await fetch('/purchase-page-exec/' + encodeURIComponent(pageKey), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!res.ok) throw new Error('Failed to load executive settings');
        return res.json();
    }

    async function saveAssignment(pageKey, assignedExec) {
        const res = await fetch('/purchase-page-exec/' + encodeURIComponent(pageKey) + '/assignment', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ assigned_exec: assignedExec || null }),
        });
        const data = await res.json().catch(function () { return {}; });
        if (!res.ok) throw new Error(data.message || 'Save failed');
        return data;
    }

    async function addOption(name) {
        const res = await fetch('/purchase-page-exec/options', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ name: name }),
        });
        const data = await res.json().catch(function () { return {}; });
        if (!res.ok) throw new Error(data.message || 'Could not add option');
        return data;
    }

    function initControl(root) {
        const pageKey = root.getAttribute('data-page-key');
        if (!pageKey || root.dataset.execReady === '1') return;
        root.dataset.execReady = '1';

        const select = root.querySelector('.page-exec-select');
        const addInput = root.querySelector('.page-exec-add-input');
        const addBtn = root.querySelector('.page-exec-add-btn');
        const toggleBtn = root.querySelector('.page-exec-toggle-add');
        let saving = false;

        fetchPayload(pageKey).then(function (payload) {
            renderSelect(select, payload.options, payload.assigned_exec, payload.can_edit);
            if (payload.can_edit) {
                toggleBtn?.classList.remove('d-none');
                setAddUi(root, false);
            } else {
                toggleBtn?.classList.add('d-none');
                addInput?.classList.add('d-none');
                addBtn?.classList.add('d-none');
            }
        }).catch(function () {
            select.innerHTML = '<option value="">Unavailable</option>';
            select.disabled = true;
        });

        select?.addEventListener('change', async function () {
            if (select.disabled || saving) return;
            saving = true;
            const previous = select.dataset.lastValue || '';
            select.dataset.lastValue = select.value;
            try {
                await saveAssignment(pageKey, select.value);
            } catch (err) {
                alert(err.message || 'Could not save assignment');
                select.value = previous;
            } finally {
                saving = false;
            }
        });

        toggleBtn?.addEventListener('click', function () {
            setAddUi(root, true);
        });

        async function handleAddOption() {
            const name = (addInput?.value || '').trim();
            if (!name) return;
            addBtn.disabled = true;
            try {
                const result = await addOption(name);
                const payload = await fetchPayload(pageKey);
                const current = select.value || name;
                renderSelect(select, result.options || payload.options, current, true);
                select.value = current;
                select.dataset.lastValue = current;
                setAddUi(root, false);
            } catch (err) {
                alert(err.message || 'Could not add executive');
            } finally {
                addBtn.disabled = false;
            }
        }

        addBtn?.addEventListener('click', handleAddOption);
        addInput?.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleAddOption();
            }
            if (e.key === 'Escape') {
                setAddUi(root, false);
            }
        });
    }

    function boot() {
        document.querySelectorAll('.page-exec-control[data-page-key]').forEach(initControl);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
@endonce
