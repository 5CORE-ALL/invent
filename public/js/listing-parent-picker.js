/*
 * Parent picker for the "Publish variation listing" window — every marketplace.
 *   - Add to an existing parent listing: search every parent listing already on the marketplace
 *     (by parent name, SKU, listing id or title) and pick one.
 *   - Create a new parent listing: type the parent name you want.
 * Shown only in variation mode (single listings have no parent).
 *
 * eBay and AliExpress attach the SKUs to the chosen live listing by its id. Other marketplaces have
 * no "add to listing" API, so the SKUs are published under the chosen parent's name instead
 * (the marketplace groups them by that name).
 */
(function (w, $) {
    'use strict';
    if (!$ || w.ListingParentPicker) return;

    var searchUrl = w.__listingParentsUrl || '/listing-common/existing-parents';
    var timers = {};

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function ensureStyle() {
        if (document.getElementById('lp-parent-style')) return;
        var st = document.createElement('style');
        st.id = 'lp-parent-style';
        st.textContent =
            '.lp-parent{border:1px solid #cbd5e1;border-radius:6px;background:#f8fafc;padding:8px 10px;margin:6px 0 8px;font-size:12px}' +
            '.lp-parent-title{font-weight:700;color:#334155;margin-bottom:4px}' +
            '.lp-parent label.lp-opt{display:flex;align-items:center;gap:6px;margin:4px 0 2px;font-weight:600;color:#1e293b;cursor:pointer}' +
            '.lp-parent .lp-existing,.lp-parent .lp-new{margin:2px 0 6px 22px}' +
            '.lp-parent .lp-existing[hidden],.lp-parent .lp-new[hidden]{display:none}' +
            '.lp-parent .lp-parent-select{margin-top:4px;font-size:12px}' +
            '.lp-parent .lp-parent-note{font-size:11px;color:#64748b;margin-top:3px}';
        document.head.appendChild(st);
    }

    function optionHtml(p, fallbackParent, selected) {
        var parent = String(p.parent || fallbackParent || '').trim();
        var title = String(p.title || '').trim();
        var count = Number(p.sku_count || (p.skus || []).length || 0);
        var text = 'Parent ' + (parent || '—') + ' · ID ' + p.id + ' · ' + count + ' variation(s)' + (title ? ' · ' + title : '');
        var tip = ((title ? title + ' — ' : '') + 'SKUs: ' + (p.skus || []).join(', ')).replace(/\s+/g, ' ');
        return '<option value="' + esc(p.id) + '" data-parent-name="' + esc(parent) + '" title="' + esc(tip) + '"' + (selected ? ' selected' : '') + '>' + esc(text) + '</option>';
    }

    // The list box paints a row blue while selectedIndex stays -1 and .value stays empty.
    // The id is also written on the picker itself, and recovered from the row text if needed.
    function optionId(opt) {
        if (!opt) return '';
        var v = String(opt.getAttribute('value') || opt.value || '').trim();
        if (v) return v;
        var m = String(opt.textContent || '').match(/ID\s+([0-9A-Za-z_-]+)/);
        return m ? m[1] : '';
    }

    function rememberChoice(select, id, parent) {
        var box = select && select.closest ? select.closest('.lp-parent') : null;
        if (!box) return;
        box.setAttribute('data-chosen-id', id || '');
        box.setAttribute('data-chosen-parent', parent || '');
    }

    function chosenExisting(select, parentName) {
        if (!select || !select.options || !select.options.length) return { id: '', parent: '' };
        var idx = select.selectedIndex;
        if (idx < 0 || !optionId(select.options[idx])) {
            idx = -1;
            if (select.options.length === 1) {
                idx = 0;
            } else {
                var want = String(parentName || '').trim().toLowerCase();
                for (var i = 0; i < select.options.length && want; i++) {
                    if (String(select.options[i].getAttribute('data-parent-name') || '').trim().toLowerCase() === want) {
                        idx = i;
                        break;
                    }
                }
            }
        }
        if (idx < 0 || !select.options[idx]) return { id: '', parent: '' };
        select.options[idx].selected = true;
        try { select.selectedIndex = idx; } catch (e) {}
        var chosen = {
            id: optionId(select.options[idx]),
            parent: String(select.options[idx].getAttribute('data-parent-name') || '').trim()
        };
        rememberChoice(select, chosen.id, chosen.parent);
        return chosen;
    }

    /**
     * @param group  preview group ({parent, existing_parents, attach_supported})
     * @param gi     group index
     * @param opts   {channel, label, attachSupported, required}
     */
    function block(group, gi, opts) {
        ensureStyle();
        opts = opts || {};
        var parentName = String((group && group.parent) || '').trim();
        if (parentName.toLowerCase() === 'standalone') parentName = '';
        var existing = (group && group.existing_parents) || [];
        var required = !!opts.required;
        // One parent already on the marketplace: start on "add to existing" with the list
        // visible, so the row is a real selection and Publish can turn on without an extra click.
        var startExisting = required && existing.length > 0;
        var name = 'lp-parent-' + gi;
        var options = '';
        var only = existing.length === 1;
        var presetId = '';
        var presetParent = '';
        existing.forEach(function (p) {
            var parent = String(p.parent || parentName || '').trim();
            var match = parent !== '' && parent.toLowerCase() === parentName.toLowerCase();
            var picked = only || match;
            if (picked && !presetId) {
                presetId = String(p.id || '').trim();
                presetParent = parent || parentName;
            }
            options += optionHtml(p, parentName, picked);
        });

        return '<div class="lp-parent" data-channel="' + esc(opts.channel || '') + '" data-group-parent="' + esc(parentName) + '"' +
            ' data-attach-supported="' + (opts.attachSupported ? '1' : '0') + '" data-required="' + (required ? '1' : '0') + '"' +
            ' data-chosen-id="' + esc(presetId) + '" data-chosen-parent="' + esc(presetParent) + '">' +
            '<div class="lp-parent-title">Parent listing on ' + esc(opts.label || 'the marketplace') + '</div>' +
            '<label class="lp-opt"><input type="radio" class="lp-parent-radio" name="' + name + '" value="existing"' + (startExisting ? ' checked' : '') + '> Add to an existing parent listing</label>' +
            '<div class="lp-existing"' + (startExisting ? '' : ' hidden') + '>' +
                '<input type="search" class="form-control form-control-sm lp-parent-search" placeholder="Search parent name, SKU, listing ID or title…" autocomplete="off">' +
                '<select class="form-select form-select-sm lp-parent-select">' + options + '</select>' +
                '<div class="lp-parent-note">' + (existing.length ? existing.length + ' parent(s) of this group. Type to search all parents.' : 'Type to search the parent listings on this marketplace.') + '</div>' +
            '</div>' +
            '<label class="lp-opt"><input type="radio" class="lp-parent-radio" name="' + name + '" value="new"' + (startExisting ? '' : ' checked') + '> Create a new parent listing</label>' +
            '<div class="lp-new"' + (startExisting ? ' hidden' : '') + '>' +
                '<input type="text" class="form-control form-control-sm lp-parent-name" maxlength="120" value="' + esc(parentName) + '" placeholder="Parent name (you choose)">' +
                '<div class="lp-parent-note">This name is used as the parent of the SKUs you tick.</div>' +
            '</div>' +
        '</div>';
    }

    /** Current choice of one group element (or null when it has no picker). */
    function read(groupEl) {
        var box = $(groupEl).find('.lp-parent').get(0);
        if (!box) return null;
        var $box = $(box);
        var attach = '';
        var radios = box.querySelectorAll('.lp-parent-radio');
        for (var i = 0; i < radios.length; i++) {
            if (radios[i].checked) { attach = String(radios[i].value || ''); break; }
        }
        // The list can be showing while the radio's checked property is still unset.
        if (!attach) {
            var existingPanel = box.querySelector('.lp-existing');
            var newPanel = box.querySelector('.lp-new');
            if (existingPanel && !existingPanel.hidden && !(newPanel && !newPanel.hidden)) attach = 'existing';
            else if (newPanel && !newPanel.hidden) attach = 'new';
        }
        var supported = box.getAttribute('data-attach-supported') === '1';
        var required = box.getAttribute('data-required') === '1';
        var select = box.querySelector('.lp-parent-select');
        var chosen = attach === 'existing' ? chosenExisting(select, box.getAttribute('data-group-parent')) : { id: '', parent: '' };
        var existingId = chosen.id || (attach === 'existing' ? String(box.getAttribute('data-chosen-id') || '').trim() : '');
        var existingParent = chosen.parent || (attach === 'existing' ? String(box.getAttribute('data-chosen-parent') || '').trim() : '');
        var newParent = String($box.find('.lp-parent-name').val() || '').trim();
        var ready = attach === 'new' ? newParent !== ''
            : attach === 'existing' ? existingId !== ''
            : !required;
        return {
            attach: attach,
            supported: supported,
            required: required,
            existingId: existingId,
            existingParent: existingParent,
            newParent: newParent,
            groupParent: String($box.attr('data-group-parent') || ''),
            ready: ready
        };
    }

    function search($box, q) {
        var $select = $box.find('.lp-parent-select');
        var $note = $box.find('.lp-parent-note').first();
        var keep = String($select.val() || '');
        $note.text('Searching…');
        $.ajax({
            url: searchUrl,
            type: 'GET',
            dataType: 'json',
            data: { channel: $box.attr('data-channel') || '', q: q || '', parent: $box.attr('data-group-parent') || '' }
        }).done(function (res) {
            var parents = (res && res.parents) || [];
            var html = '';
            parents.forEach(function (p) { html += optionHtml(p, ''); });
            $select.html(html);
            if (keep && $select.find('option[value="' + keep.replace(/"/g, '') + '"]').length) $select.val(keep);
            else chosenExisting($select.get(0), $box.attr('data-group-parent'));
            $note.text(parents.length
                ? parents.length + ' parent listing(s) found' + (parents.length >= 60 ? ' (showing the first 60 — type to narrow)' : '') + '.'
                : 'No parent listing matches' + (q ? ' “' + q + '”' : '') + '. Use "Create a new parent listing" to start one.');
            $(document).trigger('lp-parent:changed');
        }).fail(function () {
            $note.text('Could not load parent listings. Try again.');
        });
    }

    $(document)
        .on('change.lpParent', '.lp-parent-radio', function () {
            var $box = $(this).closest('.lp-parent');
            var existing = String($box.find('.lp-parent-radio:checked').val()) === 'existing';
            $box.find('.lp-existing').prop('hidden', !existing);
            $box.find('.lp-new').prop('hidden', existing);
            if (existing && !$box.find('.lp-parent-select option').length) search($box, '');
            else if (existing) chosenExisting($box.find('.lp-parent-select').get(0), $box.attr('data-group-parent'));
            $(document).trigger('lp-parent:changed');
        })
        .on('input.lpParent', '.lp-parent-search', function () {
            var $box = $(this).closest('.lp-parent');
            var q = String(this.value || '').trim();
            var id = $box.find('.lp-parent-radio').index($box.find('.lp-parent-radio:checked'));
            clearTimeout(timers[id]);
            timers[id] = setTimeout(function () { search($box, q); }, 300);
        })
        .on('mousedown.lpParent', '.lp-parent-select option', function () {
            this.selected = true;
            rememberChoice(this.parentNode, optionId(this), String(this.getAttribute('data-parent-name') || ''));
        })
        .on('change.lpParent click.lpParent', '.lp-parent-select', function () {
            var opt = this.options && this.selectedIndex >= 0 ? this.options[this.selectedIndex] : this.querySelector('option');
            if (opt) {
                opt.selected = true;
                rememberChoice(this, optionId(opt), String(opt.getAttribute('data-parent-name') || ''));
            }
            $(document).trigger('lp-parent:changed');
        })
        .on('input.lpParent', '.lp-parent-name', function () {
            $(document).trigger('lp-parent:changed');
        });

    // True when every group that has a ticked SKU has a complete parent choice.
    function allReady(container) {
        var ok = true;
        $(container).find('.lp-parent').each(function () {
            var $g = $(this).parent();
            if (!$g.find('input[type=checkbox]:checked:not(:disabled)').length) return;
            var pick = read($g);
            if (pick && !pick.ready) ok = false;
        });
        return ok;
    }

    // For pages with their own publish window: keep the Publish button off until the parent choice is complete.
    function bindConfirm(container, confirm) {
        $(document).off('lp-parent:changed.lp' + confirm).on('lp-parent:changed.lp' + confirm, function () {
            var any = $(container).find('input[type=checkbox]:checked:not(:disabled)').length > 0;
            $(confirm).prop('disabled', !any || !allReady(container));
        });
    }

    w.ListingParentPicker = { block: block, read: read, search: search, allReady: allReady, bindConfirm: bindConfirm };
})(window, window.jQuery);
