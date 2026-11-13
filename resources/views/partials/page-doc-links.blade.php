{{-- SOP + Checklist icons for a page.
     Usage: @include('partials.page-doc-links', ['pageKey' => 'inactive-listings'])
     - No link yet: the icon opens a modal to add the link to the document.
     - Link added: the icon opens the document (view / edit) in a new tab; the pencil edits or removes the link.
     Links are stored per page in page_doc_links (shared by all users).
     Optional: 'types' => ['sop'] shows only the SOP icon; 'sopIcon' => asset('...') swaps the SOP image
     (e.g. the Automated Tasks icon assets/images/task-sop-icon.png). --}}
@php
    $pdlKey = (string) ($pageKey ?? trim(request()->path(), '/'));
    $pdlLinks = \App\Support\PageDocLinks::forPage($pdlKey);
    $pdlTypes = [
        'sop' => ['label' => 'SOP', 'title' => 'Standard Operating Procedure'],
        'checklist' => ['label' => 'Checklist', 'title' => 'Checklist'],
    ];
    $pdlTypes = array_intersect_key($pdlTypes, array_flip((array) ($types ?? array_keys($pdlTypes))));
    $pdlSopIcon = (string) ($sopIcon ?? asset('images/sop-icon.png'));
@endphp

<style>
    .pdl-wrap { display: inline-flex; align-items: center; gap: 10px; }
    .pdl-item { position: relative; display: inline-flex; align-items: center; gap: 2px; }
    .pdl-icon-btn {
        position: relative; width: 34px; height: 34px; padding: 0; border: 1px solid #dbe3f3; border-radius: 8px;
        background: #fff; display: inline-flex; align-items: center; justify-content: center; cursor: pointer;
        transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
    }
    .pdl-icon-btn:hover { transform: translateY(-1px); border-color: #6d28d9; box-shadow: 0 3px 8px rgba(109, 40, 217, .25); }
    .pdl-icon-btn img { width: 24px; height: 24px; object-fit: contain; display: block; }
    .pdl-icon-btn i { font-size: 1.3rem; line-height: 1; color: #0f766e; }
    .pdl-icon-btn .pdl-dot {
        position: absolute; top: -3px; right: -3px; width: 10px; height: 10px; border-radius: 50%;
        background: #adb5bd; border: 2px solid #fff; display: none;
    }
    .pdl-item.is-linked .pdl-dot { display: block; background: #198754; }
    .pdl-edit-btn {
        border: 0; background: transparent; color: #6c757d; padding: 0 3px; line-height: 1; cursor: pointer; display: none;
    }
    .pdl-edit-btn:hover { color: #0d6efd; }
    .pdl-item.is-linked .pdl-edit-btn { display: inline-block; }
</style>

<span class="pdl-wrap" id="pdlWrap" data-page-key="{{ $pdlKey }}">
    @foreach ($pdlTypes as $type => $meta)
        @php $url = $pdlLinks[$type] ?? ''; @endphp
        <span class="pdl-item {{ $url !== '' ? 'is-linked' : '' }}" data-type="{{ $type }}" data-url="{{ $url }}">
            <button type="button" class="pdl-icon-btn" data-pdl-open
                title="{{ $url !== '' ? 'Open '.$meta['label'].' document' : 'Add '.$meta['label'].' link' }}"
                aria-label="{{ $meta['label'] }}">
                @if ($type === 'sop')
                    <img src="{{ $pdlSopIcon }}" alt="SOP" class="no-img-hover" data-no-img-hover>
                @else
                    <i class="ri-list-check-2" aria-hidden="true"></i>
                @endif
                <span class="pdl-dot"></span>
            </button>
            <button type="button" class="pdl-edit-btn" data-pdl-edit title="Edit / remove {{ $meta['label'] }} link" aria-label="Edit {{ $meta['label'] }} link">
                <i class="fas fa-pen" style="font-size: 11px;"></i>
            </button>
        </span>
    @endforeach
</span>

<div class="modal fade" id="pdlModal" tabindex="-1" aria-labelledby="pdlModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white py-2">
                <h6 class="modal-title mb-0" id="pdlModalTitle"><i class="fas fa-link me-2"></i><span id="pdlModalHeading">Add link</span></h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label for="pdlUrlInput" class="form-label fw-semibold" id="pdlUrlLabel">Document link</label>
                <input type="url" class="form-control" id="pdlUrlInput" placeholder="https://docs.google.com/document/d/…" autocomplete="off">
                <div class="form-text">Paste the link to the Google Doc / Sheet / file. Once saved, the icon opens it to view or edit.</div>
                <div class="text-danger small mt-2 d-none" id="pdlError"></div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-outline-danger btn-sm d-none" id="pdlRemoveBtn"><i class="fas fa-trash me-1"></i>Remove link</button>
                <span class="ms-auto d-flex gap-2">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" id="pdlSaveBtn">Save</button>
                </span>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var wrap = document.getElementById('pdlWrap');
    var modalEl = document.getElementById('pdlModal');
    if (!wrap || !modalEl) return;

    var PAGE = wrap.getAttribute('data-page-key');
    var SAVE_URL = @json(route('page.doc.links.save'));
    var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || @json(csrf_token());
    var LABELS = { sop: 'SOP', checklist: 'Checklist' };
    var current = null;

    var input = document.getElementById('pdlUrlInput');
    var errEl = document.getElementById('pdlError');
    var removeBtn = document.getElementById('pdlRemoveBtn');
    var saveBtn = document.getElementById('pdlSaveBtn');

    // Keep the modal under <body> so table / card stacking contexts can't clip it.
    document.body.appendChild(modalEl);

    function modal() { return bootstrap.Modal.getOrCreateInstance(modalEl); }
    function itemFor(type) { return wrap.querySelector('.pdl-item[data-type="' + type + '"]'); }
    function showErr(msg) { errEl.textContent = msg || ''; errEl.classList.toggle('d-none', !msg); }

    function paint(item) {
        var type = item.getAttribute('data-type');
        var url = item.getAttribute('data-url') || '';
        item.classList.toggle('is-linked', url !== '');
        item.querySelector('[data-pdl-open]').title = url ? 'Open ' + LABELS[type] + ' document' : 'Add ' + LABELS[type] + ' link';
    }

    function openEditor(type) {
        current = type;
        var url = itemFor(type).getAttribute('data-url') || '';
        document.getElementById('pdlModalHeading').textContent = (url ? 'Edit ' : 'Add ') + LABELS[type] + ' link';
        document.getElementById('pdlUrlLabel').textContent = LABELS[type] + ' document link';
        input.value = url;
        removeBtn.classList.toggle('d-none', !url);
        showErr('');
        modal().show();
        setTimeout(function () { input.focus(); input.select(); }, 250);
    }

    function save(url) {
        saveBtn.disabled = true; removeBtn.disabled = true;
        showErr('');
        fetch(SAVE_URL, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ page_key: PAGE, doc_type: current, url: url })
        }).then(function (r) {
            return r.json().then(function (d) { d._ok = r.ok; return d; });
        }).then(function (d) {
            if (!d._ok || !d.success) {
                var m = d.message || 'Could not save the link.';
                if (d.errors) m = Object.keys(d.errors).map(function (k) { return d.errors[k][0]; }).join(' ');
                showErr(m);
                return;
            }
            var item = itemFor(current);
            item.setAttribute('data-url', d.url || '');
            paint(item);
            modal().hide();
        }).catch(function () {
            showErr('Could not save the link.');
        }).finally(function () {
            saveBtn.disabled = false; removeBtn.disabled = false;
        });
    }

    wrap.addEventListener('click', function (e) {
        var item = e.target.closest('.pdl-item');
        if (!item) return;
        var type = item.getAttribute('data-type');
        if (e.target.closest('[data-pdl-edit]')) { openEditor(type); return; }
        if (e.target.closest('[data-pdl-open]')) {
            var url = item.getAttribute('data-url') || '';
            if (url) window.open(url, '_blank', 'noopener');
            else openEditor(type);
        }
    });

    saveBtn.addEventListener('click', function () {
        var url = input.value.trim();
        if (!url) { showErr('Paste the document link, or use Remove link.'); return; }
        if (!/^https?:\/\//i.test(url)) url = 'https://' + url;
        save(url);
    });
    removeBtn.addEventListener('click', function () {
        if (confirm('Remove this ' + LABELS[current] + ' link?')) save('');
    });
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); saveBtn.click(); }
    });
})();
</script>
