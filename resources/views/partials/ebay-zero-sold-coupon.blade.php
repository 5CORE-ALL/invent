@php
    $ebayZeroSoldChannel = $ebayZeroSoldChannel ?? 'ebay1';
    $ebayZeroSoldPart = $ebayZeroSoldPart ?? 'button';
    $ebayZeroSoldPrefix = $ebayZeroSoldChannel.'-zero-sold-coupon';
    $ebayZeroSoldUrl = '/'.$ebayZeroSoldChannel.'-zero-sold-coupon';
@endphp

@if($ebayZeroSoldPart === 'button')
<button type="button" id="{{ $ebayZeroSoldPrefix }}-btn" class="btn btn-outline-secondary btn-sm pricing-filter-item"
    title="Public eBay coupon on 0 sold only. Turns off as soon as E L30 is 1.">
    <i class="fas fa-ticket-alt"></i> 0 Sold CPN Off
</button>
@endif

@if($ebayZeroSoldPart === 'modal')
<div class="modal fade" id="{{ $ebayZeroSoldPrefix }}-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title fs-6">0 Sold coupon</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3">
                <p class="small text-muted mb-2">Public coupon on eBay for rows with E L30 = 0 and INV above 0. When E L30 reaches 1, that SKU is taken off the coupon.</p>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="{{ $ebayZeroSoldPrefix }}-switch">
                    <label class="form-check-label" for="{{ $ebayZeroSoldPrefix }}-switch" id="{{ $ebayZeroSoldPrefix }}-switch-label">Off</label>
                </div>
                <label class="form-label small mb-1" for="{{ $ebayZeroSoldPrefix }}-pct">Coupon %</label>
                <input type="number" class="form-control form-control-sm" id="{{ $ebayZeroSoldPrefix }}-pct" min="5" max="80" step="1" value="5">
                <div class="small mt-2">eBay code: <strong id="{{ $ebayZeroSoldPrefix }}-code">SAVE5OFF</strong></div>
                <div class="small text-muted" id="{{ $ebayZeroSoldPrefix }}-hint">Buyers enter this code on eBay. eBay codes are letters and numbers only.</div>
                <div class="small text-muted mt-2" id="{{ $ebayZeroSoldPrefix }}-status"></div>
            </div>
        </div>
    </div>
</div>
@endif

@if($ebayZeroSoldPart === 'column')
{
    title: "Coupon",
    field: "zero_sold_coupon_code",
    hozAlign: "center",
    width: 92,
    headerTooltip: "Public eBay coupon code. Only rows with E L30 = 0 and INV > 0. A sale of 1 removes the code.",
    formatter: function(cell) {
        const data = cell.getRow().getData() || {};
        const code = (typeof window.ebayZeroSoldColumnCode === 'function') ? window.ebayZeroSoldColumnCode(data) : '';
        if (!code) return '';
        const live = String(data.zero_sold_coupon_code || '').trim().toUpperCase();
        const pending = live !== code.toUpperCase();
        const color = pending ? '#6c757d' : '#0d6efd';
        const title = pending ? ('Sending ' + code + ' to eBay') : (code + ' is live on eBay');
        return '<span style="color:' + color + ';font-weight:600;font-size:11px;" title="' + title + '">' + code + '</span>';
    }
},
@endif

@if($ebayZeroSoldPart === 'script')
(function() {
    const prefix = @json($ebayZeroSoldPrefix);
    const url = @json($ebayZeroSoldUrl);
    const state = { enabled: false, pct: 5, code: 'SAVE5OFF', ready: false, loading: false, saving: false, pending: false };

    function codeFor(pct) {
        const n = Math.max(5, Math.min(80, Math.round(Number(pct) || 5)));
        let code = 'SAVE' + n + 'OFF';
        if (code.length < 8) code = 'SAVE' + String(n).padStart(2, '0') + 'OFF';
        return code.slice(0, 15);
    }
    function rowSku(data) {
        return String((data && (data['(Child) sku'] || data.sku)) || '').trim();
    }
    function isParent(data) {
        return rowSku(data).toUpperCase().indexOf('PARENT') !== -1;
    }
    function isZeroSold(data) {
        if (!data || isParent(data)) return false;
        if (!((parseFloat(data.INV) || 0) > 0)) return false;
        return !((parseFloat(data['eBay L30']) || 0) >= 1);
    }
    function columnCode(data) {
        if (!state.enabled || !isZeroSold(data)) return '';
        return state.code || codeFor(state.pct);
    }
    window.ebayZeroSoldColumnCode = columnCode;

    function paint() {
        const on = !!state.enabled;
        const code = state.code || codeFor(state.pct);
        $('#' + prefix + '-switch').prop('checked', on);
        $('#' + prefix + '-switch-label').text(on ? 'On' : 'Off');
        $('#' + prefix + '-pct').val(state.pct);
        $('#' + prefix + '-code').text(code);
        $('#' + prefix + '-hint').text('Buyers enter ' + code + ' on eBay (save ' + state.pct + '% off). eBay codes are letters and numbers only.');
        $('#' + prefix + '-btn')
            .toggleClass('btn-outline-secondary', !on)
            .toggleClass('btn-primary', on)
            .html('<i class="fas fa-ticket-alt"></i> 0 Sold ' + (on ? code : 'CPN Off'));
    }
    function patchRow(sku, code) {
        const want = String(sku || '').trim().toUpperCase();
        if (!want) return;
        const apply = function(data) {
            if (!data || rowSku(data).toUpperCase() !== want) return;
            data.zero_sold_coupon_code = code || null;
        };
        try { if (typeof allTableData !== 'undefined' && Array.isArray(allTableData)) allTableData.forEach(apply); } catch (e) {}
        if (window.allTableData) window.allTableData.forEach(apply);
        const table = window.table;
        if (table && typeof table.getRows === 'function') {
            table.getRows().forEach(function(row) {
                const data = row.getData() || {};
                if (rowSku(data).toUpperCase() === want && typeof row.update === 'function') {
                    row.update({ zero_sold_coupon_code: code || null });
                }
            });
        }
    }
    function jobs() {
        const rows = (Array.isArray(window.allTableData) && window.allTableData.length)
            ? window.allTableData
            : ((window.table && window.table.getData) ? (window.table.getData() || []) : []);
        const code = (state.code || codeFor(state.pct)).toUpperCase();
        const out = [];
        rows.forEach(function(data) {
            if (!data || isParent(data)) return;
            const sku = rowSku(data);
            if (!sku) return;
            const stored = String(data.zero_sold_coupon_code || '').trim().toUpperCase();
            const wantOn = !!state.enabled && isZeroSold(data);
            if (wantOn && stored === code) return;
            if (!wantOn && stored === '') return;
            out.push({ sku: sku, on: wantOn });
        });
        return out;
    }
    function sync() {
        if (!state.ready) return;
        if (state.saving) { state.pending = true; return; }
        const list = jobs();
        if (!list.length) {
            $('#' + prefix + '-status').text(state.enabled ? ('0 sold rows are on ' + state.code + '.') : 'Coupon is off.');
            return;
        }
        state.saving = true;
        let index = 0, ok = 0, fail = 0;
        function finish() {
            state.saving = false;
            $('#' + prefix + '-status').text('eBay coupon ' + state.code + ': ' + ok + ' updated, ' + fail + ' failed.');
            if (state.pending) { state.pending = false; sync(); }
        }
        function next() {
            if (index >= list.length) { finish(); return; }
            const chunk = list.slice(index, index + 4);
            index += chunk.length;
            $('#' + prefix + '-status').text('Updating eBay coupon ' + index + ' / ' + list.length + '…');
            $.ajax({
                url: url,
                method: 'POST',
                contentType: 'application/json',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: JSON.stringify({ enabled: !!state.enabled, pct: state.pct, items: chunk }),
                success: function(res) {
                    if (res && res.coupon_code) state.code = res.coupon_code;
                    (res && res.results ? res.results : []).forEach(function(row) {
                        if (row && row.success) {
                            ok++;
                            patchRow(row.sku, row.on ? (row.coupon_code || state.code) : null);
                        } else {
                            fail++;
                        }
                    });
                    paint();
                    next();
                },
                error: function(xhr) {
                    fail += chunk.length;
                    const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'eBay coupon update failed';
                    $('#' + prefix + '-status').text(msg);
                    next();
                }
            });
        }
        next();
    }
    function applyFromModal() {
        const pct = Math.max(5, Math.min(80, Math.round(Number($('#' + prefix + '-pct').val()) || 5)));
        state.enabled = $('#' + prefix + '-switch').is(':checked');
        state.pct = pct;
        state.code = codeFor(pct);
        paint();
        if (window.table && typeof window.table.redraw === 'function') window.table.redraw(true);
        sync();
    }
    function load() {
        if (state.ready) { sync(); return; }
        if (state.loading) return;
        state.loading = true;
        $.get(url, function(res) {
            state.loading = false;
            state.enabled = !!(res && res.enabled);
            state.pct = (res && res.pct) ? res.pct : 5;
            state.code = (res && res.coupon_code) ? res.coupon_code : codeFor(state.pct);
            state.ready = true;
            paint();
            if (window.table && typeof window.table.redraw === 'function') window.table.redraw(true);
            sync();
        }).fail(function() {
            state.loading = false;
            $('#' + prefix + '-status').text('Could not load the coupon setting.');
        });
    }
    $('#' + prefix + '-btn').on('click', function() {
        paint();
        const modalEl = document.getElementById(prefix + '-modal');
        if (modalEl && window.bootstrap) bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });
    $('#' + prefix + '-switch').on('change', applyFromModal);
    $('#' + prefix + '-pct').on('change', applyFromModal);
    function bind() {
        const table = window.table;
        if (!table || typeof table.on !== 'function') {
            setTimeout(bind, 400);
            return;
        }
        if (table._ebayZeroSoldBound) return;
        table._ebayZeroSoldBound = true;
        table.on('dataLoaded', load);
        try {
            if (typeof table.getDataCount === 'function' && table.getDataCount() > 0) load();
        } catch (e) {}
    }
    bind();
})();
@endif
