{{-- Task Summary: inline member-name editing (president@5core.com only; enforced server-side too). --}}
<style>
    .ts-name-edit-btn { border: 0; background: transparent; color: #94a3b8; padding: 0 .15rem; line-height: 1; font-size: .8rem; border-radius: .25rem; opacity: 0; }
    tr.task-summary-row:hover .ts-name-edit-btn, .ts-name-edit-btn:focus-visible { opacity: 1; }
    .ts-name-edit-btn:hover { color: #2563eb; background: #f1f5f9; }
    .ts-name-edit-input { width: 11rem; max-width: 100%; font-size: .85rem; padding: .1rem .35rem; border: 1px solid #2563eb; border-radius: .25rem; }
    .ts-name-edit-input.is-saving { opacity: .6; }
</style>
<script>
(function () {
    var URL_NAME = @json(route('tasks.users.name'));
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrf = csrfMeta ? csrfMeta.getAttribute('content') : @json(csrf_token());

    function firstName(full) { return (full || '').trim().split(/\s+/)[0] || full; }

    function applyName(tr, full) {
        tr.setAttribute('data-sort-member', full);
        var des = tr.getAttribute('data-sort-designation') || '';
        tr.setAttribute('data-search', (full + ' ' + des).trim().toLowerCase());
        tr.querySelectorAll('[data-user-name]').forEach(function (n) { n.setAttribute('data-user-name', full); });
        var btn = tr.querySelector('.ts-name-edit-btn');
        if (btn) btn.setAttribute('data-full-name', full);
        var label = tr.querySelector('.task-summary-member-name');
        if (label) {
            label.textContent = firstName(full);
            if (firstName(full) !== full) label.title = full; else label.removeAttribute('title');
        }
    }

    function edit(btn) {
        var tr = btn.closest('tr.task-summary-row');
        var label = tr && tr.querySelector('.task-summary-member-name');
        if (!tr || !label || tr.querySelector('.ts-name-edit-input')) return;
        var userId = parseInt(btn.getAttribute('data-user-id'), 10);
        var original = btn.getAttribute('data-full-name') || '';
        var input = document.createElement('input');
        input.type = 'text'; input.maxLength = 255; input.value = original; input.className = 'ts-name-edit-input';
        input.setAttribute('aria-label', 'Member name');
        label.style.display = 'none'; btn.style.display = 'none';
        label.parentNode.insertBefore(input, label);
        input.focus(); input.select();

        var done = false;
        function close() { if (input.parentNode) input.parentNode.removeChild(input); label.style.display = ''; btn.style.display = ''; }
        function save() {
            if (done) return; done = true;
            var value = input.value.trim().replace(/\s+/g, ' ');
            if (value === '' || value === original) { close(); return; }
            input.disabled = true; input.classList.add('is-saving');
            fetch(URL_NAME, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ user_id: userId, name: value })
            }).then(function (r) { return r.json().then(function (j) { if (!r.ok || j.success === false) { var f = j.errors ? Object.values(j.errors)[0][0] : null; throw new Error(f || j.message || 'Request failed'); } return j; }); })
              .then(function (j) {
                  applyName(tr, (j.user && j.user.name) || value);
                  close();
                  if (window.taskSummaryHierarchy && typeof window.taskSummaryHierarchy.rebuildIfActive === 'function') window.taskSummaryHierarchy.rebuildIfActive();
              })
              .catch(function (err) { close(); alert('Could not save name: ' + (err.message || 'unknown error')); });
        }
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); save(); }
            else if (e.key === 'Escape') { e.preventDefault(); done = true; close(); }
        });
        input.addEventListener('blur', save);
    }

    document.addEventListener('click', function (e) {
        var b = e.target && e.target.closest && e.target.closest('.ts-name-edit-btn');
        if (!b) return;
        e.preventDefault(); e.stopPropagation();
        edit(b);
    });
})();
</script>
