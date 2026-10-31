{{--
    Master listing form for marketplaces that have no listing API.
    Lists the Missing L SKUs of the channel with Title, Description, all Shopify image links, weight / size in
    kg + cm and lb + in, Shopify category, price and inventory, all read from our masters. "Fetch updated details"
    reads the masters again; "Export CSV" downloads the sheet to complete the other fields offline.

    Include with  @include('partials.master-listing-form', ['channel' => 'poshmark', 'label' => 'Poshmark'])
    to add a "Master Listing" button to a listing page, or without a channel to only load the form
    (the page then opens it with window.MasterListing.open(slug, label)).
--}}
@php
    $mlfChannel = $channel ?? '';
    $mlfLabel = $label ?? '';
@endphp
<style>
    .mlf-btn { height: 30px; font-size: 11px; font-weight: 700; padding: 2px 10px; border: 1px solid #0d6efd; border-radius: 5px; background: #fff; color: #0d6efd; cursor: pointer; margin: 0 4px; white-space: nowrap; }
    .mlf-btn:hover { background: #0d6efd; color: #fff; }
    #mlf-overlay { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, .55); z-index: 20000; align-items: center; justify-content: center; }
    #mlf-overlay.mlf-open { display: flex; }
    #mlf-box { background: #fff; width: min(1500px, 96vw); height: 90vh; border-radius: 10px; display: flex; flex-direction: column; box-shadow: 0 20px 60px rgba(0, 0, 0, .35); }
    #mlf-head { padding: 12px 16px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    #mlf-head h5 { margin: 0; font-size: 16px; font-weight: 700; flex: 1 1 auto; }
    #mlf-bar { padding: 8px 16px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    #mlf-bar button { font-size: 12px; font-weight: 600; padding: 5px 12px; border-radius: 6px; border: 1px solid #94a3b8; background: #fff; cursor: pointer; }
    #mlf-bar button.mlf-primary { background: #0d6efd; border-color: #0d6efd; color: #fff; }
    #mlf-bar button.mlf-success { background: #198754; border-color: #198754; color: #fff; }
    #mlf-bar button:disabled { opacity: .5; cursor: not-allowed; }
    #mlf-bar input { height: 30px; font-size: 12px; padding: 2px 8px; border: 1px solid #cbd5e1; border-radius: 5px; width: 200px; }
    #mlf-status { font-size: 12px; color: #475569; margin-left: auto; }
    #mlf-note { padding: 6px 16px; font-size: 12px; color: #92400e; background: #fffbeb; border-bottom: 1px solid #fde68a; display: none; }
    #mlf-wrap { flex: 1 1 auto; overflow: auto; }
    #mlf-table { border-collapse: collapse; font-size: 12px; min-width: 100%; }
    #mlf-table th { position: sticky; top: 0; background: #00d5d5; color: #0f172a; padding: 6px 8px; text-align: left; white-space: nowrap; border: 1px solid #cbd5e1; z-index: 1; }
    #mlf-table td { padding: 5px 8px; border: 1px solid #e2e8f0; vertical-align: top; max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    #mlf-table td.mlf-empty { background: #fef2f2; }
    #mlf-table td.mlf-aplus { min-width: 340px; max-width: 420px; white-space: normal; }
    .mlf-aplus-box { max-height: 120px; overflow: auto; font-size: 12px; line-height: 1.4; }
    .mlf-aplus-box img { max-width: 90px; max-height: 70px; height: auto; margin: 0 4px 4px 0; vertical-align: top; border-radius: 4px; }
    .mlf-aplus-box table { max-width: 100%; }
    .mlf-aplus-box * { max-width: 100%; }
    #mlf-table tr:hover td { background: #f1f5f9; }
</style>
<div id="mlf-overlay" aria-hidden="true">
    <div id="mlf-box" role="dialog" aria-modal="true" aria-labelledby="mlf-title">
        <div id="mlf-head">
            <h5 id="mlf-title">Master Listing</h5>
            <button type="button" id="mlf-close" class="btn btn-sm btn-outline-secondary">Close</button>
        </div>
        <div id="mlf-bar">
            <button type="button" id="mlf-fetch" class="mlf-primary" title="Read Title, Description, Image, Dim/Wt, Category masters and Shopify again">Fetch updated details from masters</button>
            <button type="button" id="mlf-export" class="mlf-success" disabled>Export CSV</button>
            <input type="search" id="mlf-search" placeholder="Filter by SKU / title" autocomplete="off">
            <span id="mlf-status"></span>
        </div>
        <div id="mlf-note"></div>
        <div id="mlf-wrap"><table id="mlf-table"><thead></thead><tbody></tbody></table></div>
    </div>
</div>
<script>
(function () {
    if (window.MasterListing) {
        if (window.MasterListing.addButton) window.MasterListing.addButton(@json($mlfChannel), @json($mlfLabel));
        return;
    }

    var csrf = @json(csrf_token());
    var BATCH = 25;
    var state = { slug: '', label: '', columns: {}, rows: [], running: false, token: 0 };
    var $ = function (id) { return document.getElementById(id); };

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
        });
    }

    // Rendered A+ preview: scripts, styles, frames and event handlers are stripped.
    function safeHtml(html) {
        var doc = new DOMParser().parseFromString(String(html || ''), 'text/html');
        doc.querySelectorAll('script, style, iframe, object, embed, link, meta, form, video, audio').forEach(function (n) { n.remove(); });
        doc.body.querySelectorAll('*').forEach(function (n) {
            Array.prototype.slice.call(n.attributes).forEach(function (a) {
                var name = a.name.toLowerCase();
                if (name.indexOf('on') === 0 || name === 'style' || name === 'width' || name === 'height' ||
                    ((name === 'href' || name === 'src') && /^\s*javascript:/i.test(a.value))) {
                    n.removeAttribute(a.name);
                }
            });
            if (n.tagName === 'A') { n.setAttribute('target', '_blank'); n.setAttribute('rel', 'noopener'); }
            if (n.tagName === 'IMG') { n.setAttribute('loading', 'lazy'); }
        });
        return doc.body.innerHTML;
    }

    function setStatus(text) { $('mlf-status').textContent = text || ''; }

    function setNote(text) {
        var el = $('mlf-note');
        el.textContent = text || '';
        el.style.display = text ? 'block' : 'none';
    }

    function renderHead() {
        var html = '<tr>';
        Object.keys(state.columns).forEach(function (key) { html += '<th>' + esc(state.columns[key]) + '</th>'; });
        $('mlf-table').tHead.innerHTML = html + '</tr>';
    }

    function render() {
        var q = $('mlf-search').value.trim().toLowerCase();
        var keys = Object.keys(state.columns);
        var html = '';
        state.rows.forEach(function (row) {
            if (q && String(row.sku || '').toLowerCase().indexOf(q) === -1 && String(row.title || '').toLowerCase().indexOf(q) === -1) return;
            html += '<tr>';
            keys.forEach(function (key) {
                var v = row[key] == null ? '' : String(row[key]);
                var empty = v === '' && ['title', 'description', 'aplus', 'images', 'price', 'inventory'].indexOf(key) !== -1;
                var shown = key === 'images' && v ? (v.split(', ').length + ' image link(s): ' + v) : v;
                if (key === 'aplus') {
                    // A+ is a full HTML page: show it rendered (as in Description Master); the CSV keeps the HTML.
                    html += '<td class="mlf-aplus' + (empty ? ' mlf-empty' : '') + '">' +
                        (v ? '<div class="mlf-aplus-box">' + safeHtml(v) + '</div>' : '') + '</td>';
                    return;
                }
                html += '<td' + (empty ? ' class="mlf-empty"' : '') + ' title="' + esc(v) + '">' + esc(shown) + '</td>';
            });
            html += '</tr>';
        });
        $('mlf-table').tBodies[0].innerHTML = html;
        $('mlf-export').disabled = state.rows.length === 0 || state.running;
    }

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); });
    }

    function load(refresh) {
        if (state.running) return;
        var token = ++state.token;
        state.running = true;
        state.rows = [];
        $('mlf-fetch').disabled = true;
        $('mlf-export').disabled = true;
        setNote('');
        setStatus('Finding the Missing L SKUs...');
        render();

        fetch('/master-listing/' + encodeURIComponent(state.slug) + '/skus', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
            .then(function (res) {
                if (!res.ok || !res.data.success) throw new Error((res.data && res.data.message) || 'Could not load the Missing L SKUs.');
                state.columns = res.data.columns || {};
                renderHead();
                var skus = res.data.skus || [];
                if (!skus.length) {
                    setStatus('No Missing L SKUs for ' + state.label + '.');
                    return null;
                }
                var notes = {};
                var done = 0;
                var step = function (i) {
                    if (token !== state.token) return Promise.resolve();
                    if (i >= skus.length) return Promise.resolve();
                    setStatus('Fetching details from masters: ' + Math.min(i, skus.length) + ' / ' + skus.length);
                    return post('/master-listing/' + encodeURIComponent(state.slug) + '/details', { skus: skus.slice(i, i + BATCH), refresh: !!refresh })
                        .then(function (r) {
                            if (!r.ok || !r.data.success) throw new Error((r.data && r.data.message) || 'Could not read the masters.');
                            state.rows = state.rows.concat(r.data.rows || []);
                            if (r.data.message) notes[r.data.message] = true;
                            done += (r.data.rows || []).length;
                            render();
                            return step(i + BATCH);
                        });
                };
                return step(0).then(function () {
                    setStatus(done + ' SKU(s) loaded from the masters.');
                    var msgs = Object.keys(notes);
                    if (msgs.length) setNote(msgs.join(' '));
                });
            })
            .catch(function (e) { setStatus(''); setNote(e.message || 'Could not load the master listing.'); })
            .then(function () {
                if (token === state.token) {
                    state.running = false;
                    $('mlf-fetch').disabled = false;
                    render();
                }
            });
    }

    function csvCell(v) {
        return '"' + String(v == null ? '' : v).replace(/\r?\n/g, ' ').replace(/"/g, '""') + '"';
    }

    function exportCsv() {
        if (!state.rows.length) return;
        var keys = Object.keys(state.columns);
        var lines = [keys.map(function (k) { return csvCell(state.columns[k]); }).join(',')];
        state.rows.forEach(function (row) {
            lines.push(keys.map(function (k) { return csvCell(row[k]); }).join(','));
        });
        var blob = new Blob(['\ufeff' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = state.slug + '-master-listing-' + new Date().toISOString().slice(0, 10) + '.csv';
        document.body.appendChild(a);
        a.click();
        setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 500);
    }

    function close() {
        state.token++;
        state.running = false;
        $('mlf-fetch').disabled = false;
        $('mlf-overlay').classList.remove('mlf-open');
    }

    function open(slug, label) {
        slug = String(slug || '').trim();
        if (!slug) return;
        var same = state.slug === slug && state.rows.length > 0;
        state.slug = slug;
        state.label = label || slug;
        $('mlf-title').textContent = 'Master Listing - ' + state.label + ' (Missing L)';
        $('mlf-overlay').classList.add('mlf-open');
        if (!same) {
            state.rows = [];
            $('mlf-search').value = '';
            load(false);
        } else {
            render();
        }
    }

    function addButton(slug, label) {
        if (!slug || document.getElementById('mlf-open-btn')) return;
        var tries = 0;
        var timer = setInterval(function () {
            tries++;
            var anchor = document.getElementById('listing-sku-search')
                || document.getElementById('row-data-type')
                || document.getElementById('sheet-search');
            var host = anchor ? anchor.parentNode : null;
            var before = anchor;
            if (!host) {
                var tbl = document.querySelector('.tabulator');
                if (tbl && tbl.parentNode) { host = tbl.parentNode; before = tbl; }
            }
            if (host && !document.getElementById('mlf-open-btn')) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.id = 'mlf-open-btn';
                btn.className = 'mlf-btn';
                btn.textContent = 'Master Listing';
                btn.title = 'Title, Description, Shopify images, weight / size, category, price and inventory from our masters, ready to export as CSV';
                btn.addEventListener('click', function () { open(slug, label); });
                host.insertBefore(btn, before);
                clearInterval(timer);
            } else if (tries > 40) {
                clearInterval(timer);
            }
        }, 300);
    }

    $('mlf-close').addEventListener('click', close);
    $('mlf-overlay').addEventListener('click', function (e) { if (e.target === this) close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && $('mlf-overlay').classList.contains('mlf-open')) close(); });
    $('mlf-fetch').addEventListener('click', function () { load(true); });
    $('mlf-export').addEventListener('click', exportCsv);
    $('mlf-search').addEventListener('input', render);

    window.MasterListing = { open: open, addButton: addButton };
    addButton(@json($mlfChannel), @json($mlfLabel));
})();
</script>
