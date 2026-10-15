@php
    $ebayZeroSoldChannel = $ebayZeroSoldChannel ?? 'ebay1';
    $ebayZeroSoldPart = $ebayZeroSoldPart ?? 'button';
    $ebayZeroSoldPrefix = $ebayZeroSoldChannel.'-zero-sold-coupon';
    $ebayZeroSoldUrl = $ebayZeroSoldChannel === 'ebay1'
        ? '/ebay-zero-sold-coupon'
        : '/'.$ebayZeroSoldChannel.'-zero-sold-coupon';
@endphp

@if($ebayZeroSoldPart === 'button')
<button type="button" id="{{ $ebayZeroSoldPrefix }}-btn" class="btn btn-outline-secondary btn-sm pricing-filter-item"
    title="Public eBay coupon by Dil slab. Dil = OV L30 ÷ INV. Coupon % of 0 leaves that slab off.">
    <i class="fas fa-ticket-alt"></i> Dil CPN Off
</button>
@endif

@if($ebayZeroSoldPart === 'modal')
<div class="modal fade" id="{{ $ebayZeroSoldPrefix }}-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title fs-6">Dil coupon</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3">
                <p class="small text-muted mb-2">Public coupon on eBay by Dil slab. Dil is OV L30 ÷ INV, and INV must be above 0. The 0–0 slab is OV L30 = 0. A SKU moves onto the coupon of the slab its Dil falls in. Coupon % of 0 leaves that slab off. eBay accepts 5–80, letters and numbers only.</p>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="{{ $ebayZeroSoldPrefix }}-switch">
                    <label class="form-check-label" for="{{ $ebayZeroSoldPrefix }}-switch" id="{{ $ebayZeroSoldPrefix }}-switch-label">Off</label>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-2">
                        <thead>
                            <tr>
                                <th>From</th>
                                <th>To</th>
                                <th class="text-center">SKUs</th>
                                <th>Coupon %</th>
                                <th>eBay code</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="{{ $ebayZeroSoldPrefix }}-slabs"></tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="{{ $ebayZeroSoldPrefix }}-add">Add slab</button>
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
    headerTooltip: "Public eBay coupon from the Dil slab. Dil = OV L30 ÷ INV. INV = 0 is blank. Gray means the code is still sending.",
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
    const skipOffSync = @json(($ebayZeroSoldChannel ?? '') === 'ebay3');
    const state = {
        enabled: false,
        slabs: [
            { min: 0, max: 0, pct: 5 },
            { min: 0.01, max: 10, pct: 0 },
            { min: 10, max: 25, pct: 0 },
            { min: 25, max: 50, pct: 0 },
            { min: 50, max: 100, pct: 0 },
            { min: 100, max: 9999, pct: 0 }
        ],
        ready: false,
        loading: false,
        saving: false,
        pending: false
    };
    let applyTimer = null;

    function couponPct(pct) {
        const n = Math.round(Number(pct) || 0);
        return (n >= 5 && n <= 80) ? n : 0;
    }
    function codeFor(pct) {
        const n = couponPct(pct);
        return n >= 5 ? ('SAVE' + String(n).padStart(2, '0') + 'OFF') : '';
    }
    function rowSku(data) {
        return String((data && (data['(Child) sku'] || data.sku)) || '').trim();
    }
    function isParent(data) {
        if (!data) return true;
        if (data.is_parent_summary) return true;
        return rowSku(data).toUpperCase().indexOf('PARENT') !== -1;
    }
    function rowMetrics(data) {
        const inv = parseFloat(data && data.INV) || 0;
        const ov = parseFloat(data && data.L30) || 0;
        return { inv: inv, ov: ov, dil: inv > 0 ? (ov / inv) * 100 : null };
    }
    function contains(dil, ov, slab, prevMax) {
        const min = Number(slab.min) || 0;
        const max = Number(slab.max) || 0;
        if (Math.abs(min) < 0.0000001 && Math.abs(max) < 0.0000001) return Math.abs(ov) < 0.0000001;
        const shares = prevMax !== null && Math.abs(min - prevMax) < 0.0001;
        const loOk = shares ? dil > min : dil >= min;
        return loOk && dil <= max;
    }
    function slabFor(data) {
        if (!data || isParent(data)) return null;
        const metric = rowMetrics(data);
        if (!(metric.inv > 0)) return null;
        let prevMax = null;
        const slabs = state.slabs || [];
        for (let i = 0; i < slabs.length; i++) {
            if (contains(metric.dil, metric.ov, slabs[i], prevMax)) return slabs[i];
            prevMax = Number(slabs[i].max) || 0;
        }
        return null;
    }
    function columnCode(data) {
        if (!state.enabled) return '';
        const slab = slabFor(data);
        return slab ? codeFor(slab.pct) : '';
    }
    window.ebayZeroSoldColumnCode = columnCode;

    function rows() {
        if (Array.isArray(window.allTableData) && window.allTableData.length) return window.allTableData;
        const table = window.table;
        if (table && typeof table.getData === 'function') return table.getData() || [];
        return [];
    }
    function counts() {
        const totals = (state.slabs || []).map(function() { return 0; });
        rows().forEach(function(data) {
            if (!data || isParent(data)) return;
            const metric = rowMetrics(data);
            if (!(metric.inv > 0)) return;
            let prevMax = null;
            for (let i = 0; i < state.slabs.length; i++) {
                if (contains(metric.dil, metric.ov, state.slabs[i], prevMax)) {
                    totals[i] += 1;
                    return;
                }
                prevMax = Number(state.slabs[i].max) || 0;
            }
        });
        return totals;
    }
    function activeCodes() {
        const seen = {};
        const out = [];
        (state.slabs || []).forEach(function(slab) {
            const code = codeFor(slab.pct);
            if (code && !seen[code]) {
                seen[code] = true;
                out.push(code);
            }
        });
        return out;
    }
    function paintChrome() {
        const on = !!state.enabled;
        const codes = activeCodes();
        let label = 'Dil CPN Off';
        if (on && codes.length === 1) label = 'Dil ' + codes[0];
        else if (on && codes.length > 1) label = 'Dil CPN On';
        $('#' + prefix + '-switch').prop('checked', on);
        $('#' + prefix + '-switch-label').text(on ? 'On' : 'Off');
        $('#' + prefix + '-btn')
            .toggleClass('btn-outline-secondary', !on || !codes.length)
            .toggleClass('btn-primary', on && codes.length > 0)
            .html('<i class="fas fa-ticket-alt"></i> ' + label);
        const tally = counts();
        $('#' + prefix + '-slabs tr').each(function(i) {
            $(this).find('.ebay-dil-count').text(tally[i] || 0);
            const pct = couponPct($(this).find('.ebay-dil-pct').val());
            $(this).find('.ebay-dil-code').text(codeFor(pct) || '—');
        });
    }
    function paintSlabs() {
        const tally = counts();
        const html = (state.slabs || []).map(function(slab, i) {
            const pct = couponPct(slab.pct);
            const code = codeFor(pct);
            return '<tr>'
                + '<td><input type="number" step="0.01" class="form-control form-control-sm ebay-dil-min" value="' + slab.min + '" style="width:88px;"></td>'
                + '<td><input type="number" step="0.01" class="form-control form-control-sm ebay-dil-max" value="' + slab.max + '" style="width:88px;"></td>'
                + '<td class="text-center fw-bold ebay-dil-count">' + (tally[i] || 0) + '</td>'
                + '<td><input type="number" min="0" max="80" step="1" class="form-control form-control-sm ebay-dil-pct" value="' + pct + '" style="width:72px;"></td>'
                + '<td class="small fw-semibold ebay-dil-code">' + (code || '—') + '</td>'
                + '<td><button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 ebay-dil-del" data-i="' + i + '">×</button></td>'
                + '</tr>';
        }).join('');
        $('#' + prefix + '-slabs').html(html);
        paintChrome();
    }
    function readSlabs() {
        const body = $('#' + prefix + '-slabs tr');
        if (!body.length) return state.slabs.slice();
        const out = [];
        body.each(function() {
            let min = Number($(this).find('.ebay-dil-min').val());
            let max = Number($(this).find('.ebay-dil-max').val());
            if (!isFinite(min)) min = 0;
            if (!isFinite(max)) max = 0;
            if (max < min) {
                const swap = min;
                min = max;
                max = swap;
                $(this).find('.ebay-dil-min').val(min);
                $(this).find('.ebay-dil-max').val(max);
            }
            out.push({
                min: min,
                max: max,
                pct: couponPct($(this).find('.ebay-dil-pct').val())
            });
        });
        return out.length ? out : state.slabs.slice();
    }
    function modalOpen() {
        const el = document.getElementById(prefix + '-modal');
        return !!(el && el.classList.contains('show'));
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
        const codeBySku = {};
        const out = [];
        rows().forEach(function(data) {
            if (!data || isParent(data)) return;
            const sku = rowSku(data);
            if (!sku || codeBySku[sku.toUpperCase()]) return;
            codeBySku[sku.toUpperCase()] = true;
            const stored = String(data.zero_sold_coupon_code || '').trim().toUpperCase();
            const want = columnCode(data).toUpperCase();
            if (want && stored === want) return;
            if (!want && stored === '') return;
            out.push({ sku: sku, on: !!want, pct: want ? couponPct(want.replace(/\D/g, '')) : 0 });
        });
        return out;
    }
    function sync() {
        if (!state.ready) return;
        if (state.saving) { state.pending = true; return; }
        const list = jobs();
        if (!list.length) {
            $('#' + prefix + '-status').text(state.enabled ? 'Coupons follow the Dil slabs.' : 'Coupon is off.');
            return;
        }
        state.saving = true;
        let index = 0, ok = 0, fail = 0;
        function finish() {
            state.saving = false;
            $('#' + prefix + '-status').text('eBay Dil coupon: ' + ok + ' updated, ' + fail + ' failed.');
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
                data: JSON.stringify({ enabled: !!state.enabled, slabs: state.slabs, items: chunk }),
                success: function(res) {
                    if (res && Array.isArray(res.slabs) && !modalOpen()) state.slabs = res.slabs;
                    (res && res.results ? res.results : []).forEach(function(row) {
                        if (row && row.success) {
                            ok++;
                            patchRow(row.sku, row.on ? (row.coupon_code || '') : null);
                        } else {
                            fail++;
                        }
                    });
                    paintChrome();
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
    function persistSetting() {
        return $.ajax({
            url: url,
            method: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: JSON.stringify({ enabled: !!state.enabled, slabs: state.slabs, items: [] }),
        });
    }
    function autoSync() {
        if (skipOffSync && !state.enabled) return;
        sync();
    }
    function applyFromModal() {
        if (!state.ready) return;
        state.slabs = readSlabs();
        state.enabled = $('#' + prefix + '-switch').is(':checked');
        paintChrome();
        if (window.table && typeof window.table.redraw === 'function') window.table.redraw(true);
        persistSetting().always(function() { sync(); });
    }
    function scheduleApply() {
        clearTimeout(applyTimer);
        applyTimer = setTimeout(applyFromModal, 400);
    }
    function load() {
        if (state.ready) { autoSync(); paintChrome(); return; }
        if (state.loading) return;
        state.loading = true;
        $.get(url, function(res) {
            state.loading = false;
            state.enabled = !!(res && (res.enabled === true || res.enabled === 1 || res.enabled === '1'));
            if (res && Array.isArray(res.slabs) && res.slabs.length) state.slabs = res.slabs;
            state.ready = true;
            paintSlabs();
            if (window.table && typeof window.table.redraw === 'function') window.table.redraw(true);
            autoSync();
            if (typeof state.afterLoad === 'function') {
                const open = state.afterLoad;
                state.afterLoad = null;
                open();
            }
        }).fail(function() {
            state.loading = false;
            $('#' + prefix + '-status').text('Could not load the coupon setting.');
            if (typeof state.afterLoad === 'function') {
                const open = state.afterLoad;
                state.afterLoad = null;
                open();
            }
        });
    }
    function openModal() {
        paintSlabs();
        const modalEl = document.getElementById(prefix + '-modal');
        if (modalEl && window.bootstrap) bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
    $('#' + prefix + '-btn').on('click', function() {
        if (state.ready) { openModal(); return; }
        state.afterLoad = openModal;
        load();
    });
    $('#' + prefix + '-switch').on('change', scheduleApply);
    $('#' + prefix + '-add').on('click', function() {
        state.slabs = readSlabs();
        const last = state.slabs[state.slabs.length - 1] || { max: 0, pct: 0 };
        const min = Number(last.max) || 0;
        state.slabs.push({ min: min, max: Math.round((min + 10) * 100) / 100, pct: 0 });
        paintSlabs();
        scheduleApply();
    });
    $('#' + prefix + '-modal').on('change', '.ebay-dil-min, .ebay-dil-max, .ebay-dil-pct', function() {
        if ($(this).hasClass('ebay-dil-pct')) $(this).val(couponPct($(this).val()));
        scheduleApply();
    });
    $('#' + prefix + '-modal').on('click', '.ebay-dil-del', function() {
        state.slabs = readSlabs();
        if (state.slabs.length <= 1) return;
        state.slabs.splice(Number($(this).attr('data-i')) || 0, 1);
        paintSlabs();
        scheduleApply();
    });
    function bind() {
        const table = window.table;
        if (!table || typeof table.on !== 'function') {
            setTimeout(bind, 400);
            return;
        }
        if (table._ebayZeroSoldBound) return;
        table._ebayZeroSoldBound = true;
        table.on('dataLoaded', function() {
            load();
            paintChrome();
        });
        try {
            if (typeof table.getDataCount === 'function' && table.getDataCount() > 0) load();
        } catch (e) {}
    }
    bind();
})();
@endif
