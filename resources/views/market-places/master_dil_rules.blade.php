@extends('layouts.vertical', ['title' => 'Master Dil Rule / Slab', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .mdil-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(44, 110, 213, 0.06);
        }
        .mdil-table .mdil-input {
            max-width: 110px;
            margin-left: auto;
            text-align: right;
            font-weight: 600;
        }
        .mdil-table .mdil-min,
        .mdil-table .mdil-max {
            margin-left: 0;
        }
        .mdil-row-del {
            border: none;
            background: none;
            color: #dc3545;
            padding: 0 4px;
            line-height: 1;
            cursor: pointer;
            font-size: 1.15rem;
        }
        .mdil-sites {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .mdil-site {
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 999px;
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #e2e8f0;
        }
        .mdil-site.is-saved {
            background: #ecfdf3;
            border-color: #bbf7d0;
            color: #166534;
        }
        .mdil-used {
            font-weight: 600;
            text-align: center;
        }
        #mdil-status { min-height: 1.4em; }
    </style>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="page-title mb-0">
                    <i class="fas fa-sliders-h me-1"></i> Master Dil Rule / Slab
                </h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-8 col-lg-9">
            <div class="card mdil-card">
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        One table for every Sprc Dil page. Add or remove a slab here and it is added or
                        removed on every site’s existing Dil vs Target NROI store. Change a Target NROI%
                        and that same From–To slab is updated wherever it is already used.
                        Per-site pages keep reading their own data. CVR overlay stays per site.
                        Editing a slab on any one site page also updates that same slab on the other sites.
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0 mdil-table" id="mdil-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:120px;">From</th>
                                    <th style="width:120px;">To</th>
                                    <th class="text-center" style="width:90px;">Sites</th>
                                    <th class="text-end" style="width:140px;">Target NROI%</th>
                                    <th style="width:40px;"></th>
                                </tr>
                            </thead>
                            <tbody id="mdil-tbody"></tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="mdil-add-btn">
                        <i class="fas fa-plus me-1"></i> Add slab
                    </button>
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                        <button type="button" class="btn btn-sm btn-primary" id="mdil-save-btn">
                            Save to all sites
                        </button>
                        <div class="small text-muted" id="mdil-status">Loading saved slabs…</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-lg-3">
            <div class="card mdil-card">
                <div class="card-body">
                    <h6 class="mb-2">Sites that use these slabs</h6>
                    <p class="small text-muted mb-2">
                        Green = this site already has a saved Dil table. Grey = still on first-time defaults until you save.
                    </p>
                    <div class="mdil-sites" id="mdil-sites"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
<script>
(function() {
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const DATA_URL = @json(route('master.dil.rules.data'));
    const SAVE_URL = @json(route('master.dil.rules.save'));
    let rules = [];
    let autosaveTimer = null;
    let autosaveXhr = null;
    let autosaveSeq = 0;

    function round2(n) {
        return Math.round((Number(n) + Number.EPSILON) * 100) / 100;
    }
    function fmtNum(n) {
        const s = String(round2(n));
        return s.replace(/\.0+$/, '').replace(/(\.\d*?)0+$/, '$1');
    }
    function normalizeRule(item) {
        const min = parseFloat(item.min);
        const max = parseFloat(item.max);
        const groi = parseFloat(item.groi);
        if (!isFinite(min) || !isFinite(max) || min < 0 || max < min) return null;
        return {
            key: fmtNum(min) + '-' + fmtNum(max),
            label: fmtNum(min) + '–' + fmtNum(max) + '%',
            min: round2(min),
            max: round2(max),
            groi: isFinite(groi) && groi >= 0 ? round2(groi) : 0,
            used_on: Array.isArray(item.used_on) ? item.used_on : [],
        };
    }
    function normalizeList(list) {
        const out = [];
        (list || []).forEach(function(item) {
            const rule = normalizeRule(item);
            if (rule) out.push(rule);
        });
        out.sort(function(a, b) { return a.min - b.min || a.max - b.max; });
        return out;
    }
    function readFromTable() {
        const next = [];
        $('#mdil-tbody tr').each(function() {
            const rule = normalizeRule({
                min: $(this).find('.mdil-min').val(),
                max: $(this).find('.mdil-max').val(),
                groi: $(this).find('.mdil-groi').val(),
                used_on: String($(this).attr('data-used') || '').split(',').filter(Boolean),
            });
            if (rule) next.push(rule);
        });
        rules = normalizeList(next);
        return rules;
    }
    function cascadeFromFirst() {
        const $rows = $('#mdil-tbody tr');
        if (!$rows.length) return;
        const firstVal = parseFloat($rows.eq(0).find('.mdil-groi').val());
        if (!isFinite(firstVal)) return;
        $rows.each(function(i) {
            if (i === 0) return;
            $(this).find('.mdil-groi').val(round2(firstVal + (i * 5)));
        });
        readFromTable();
    }
    function renderTable() {
        const $tb = $('#mdil-tbody');
        $tb.empty();
        const list = normalizeList(rules);
        rules = list;
        const canDelete = list.length > 1;
        list.forEach(function(r, idx) {
            const used = (r.used_on || []).length;
            $tb.append(
                '<tr data-key="' + String(r.key).replace(/"/g, '&quot;') + '" data-used="' + (r.used_on || []).join(',') + '">'
                + '<td><input type="number" min="0" step="0.1" class="form-control form-control-sm mdil-input mdil-min" value="' + r.min + '"></td>'
                + '<td><input type="number" min="0" step="0.1" class="form-control form-control-sm mdil-input mdil-max" value="' + r.max + '"></td>'
                + '<td class="mdil-used" title="Sites that already have this From–To slab">' + used + '</td>'
                + '<td class="text-end"><input type="number" step="0.1" class="form-control form-control-sm mdil-input mdil-groi" value="' + r.groi + '"'
                + (idx === 0 ? ' title="Changing this sets following slabs to +5 each"' : '') + '></td>'
                + '<td class="text-center">'
                + (canDelete ? '<button type="button" class="mdil-row-del" data-idx="' + idx + '" title="Remove slab">&times;</button>' : '')
                + '</td></tr>'
            );
        });
    }
    function renderSites(channels) {
        const $box = $('#mdil-sites');
        $box.empty();
        (channels || []).forEach(function(ch) {
            $box.append(
                '<span class="mdil-site' + (ch.is_default ? '' : ' is-saved') + '">'
                + (ch.label || ch.channel)
                + ' · ' + (ch.slab_count || 0)
                + '</span>'
            );
        });
    }
    function setStatus(text) {
        $('#mdil-status').text(text);
    }
    function postRules() {
        const payload = readFromTable().map(function(r) {
            return { key: r.key, label: r.label, min: r.min, max: r.max, groi: r.groi };
        });
        if (autosaveXhr && typeof autosaveXhr.abort === 'function') {
            try { autosaveXhr.abort(); } catch (e) { /* ignore */ }
        }
        autosaveXhr = $.ajax({
            url: SAVE_URL,
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': CSRF,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            data: JSON.stringify({ rules: payload, _token: CSRF }),
        });
        return autosaveXhr.then(function(res) {
            autosaveXhr = null;
            if (res && Array.isArray(res.rules)) {
                rules = normalizeList(res.rules);
                renderTable();
            }
            if (res && Array.isArray(res.channels)) renderSites(res.channels);
            return res;
        }, function(xhr) {
            autosaveXhr = null;
            throw xhr;
        });
    }
    function scheduleSave() {
        if (autosaveTimer) clearTimeout(autosaveTimer);
        autosaveTimer = setTimeout(function() {
            autosaveTimer = null;
            const seq = ++autosaveSeq;
            setStatus('Saving to all site Dil tables…');
            postRules().then(function(res) {
                if (seq !== autosaveSeq) return;
                const n = (res && res.updated) ? res.updated.length : 0;
                setStatus('Saved. Slabs updated on ' + n + ' site store(s). Open a site page or wait for the usual Sprc Dil cron for S PRC.');
            }, function(xhr) {
                if (seq !== autosaveSeq) return;
                if (xhr && xhr.statusText === 'abort') return;
                const reason = (xhr && xhr.responseJSON && xhr.responseJSON.message) || 'error';
                setStatus('Save failed: ' + reason);
            });
        }, 600);
    }
    function addSlab() {
        readFromTable();
        let nextMin = 0.1;
        let lastGroi = 50;
        rules.forEach(function(r) {
            if (isFinite(Number(r.max)) && Number(r.max) > nextMin) nextMin = Number(r.max);
            if (isFinite(Number(r.groi))) lastGroi = Number(r.groi);
        });
        const added = normalizeRule({ min: nextMin, max: round2(nextMin + 5), groi: Math.max(0, round2(lastGroi + 5)) });
        if (!added) return;
        rules.push(added);
        rules = normalizeList(rules);
        renderTable();
        scheduleSave();
    }
    function deleteSlab(idx) {
        const list = readFromTable();
        if (list.length <= 1) {
            setStatus('Keep at least one Dil slab.');
            return;
        }
        if (!isFinite(idx) || idx < 0 || idx >= list.length) return;
        list.splice(idx, 1);
        rules = normalizeList(list);
        renderTable();
        scheduleSave();
    }
    function load() {
        setStatus('Loading saved slabs…');
        $.ajax({ url: DATA_URL, method: 'GET', dataType: 'json', headers: { 'Accept': 'application/json' } })
            .done(function(res) {
                rules = normalizeList((res && res.rules) || []);
                renderTable();
                renderSites((res && res.channels) || []);
                setStatus(res && res.is_default
                    ? 'No site has saved slabs yet — showing first-time defaults. Edit and they write to every site store.'
                    : 'Loaded the combined Dil slabs from existing site stores. Edits autosave to all of them.');
            })
            .fail(function(xhr) {
                setStatus('Could not load slabs: ' + ((xhr && xhr.responseJSON && xhr.responseJSON.message) || 'error'));
            });
    }

    $(function() {
        $('#mdil-add-btn').on('click', function(e) { e.preventDefault(); addSlab(); });
        $(document).on('click', '.mdil-row-del', function() {
            deleteSlab(parseInt($(this).attr('data-idx'), 10));
        });
        $('#mdil-save-btn').on('click', function(e) {
            e.preventDefault();
            if (autosaveTimer) { clearTimeout(autosaveTimer); autosaveTimer = null; }
            const $btn = $(this);
            const html = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving…');
            postRules().then(function(res) {
                const n = (res && res.updated) ? res.updated.length : 0;
                setStatus('Saved. Slabs updated on ' + n + ' site store(s).');
            }, function(xhr) {
                setStatus('Save failed: ' + ((xhr && xhr.responseJSON && xhr.responseJSON.message) || 'error'));
            }).always(function() {
                $btn.prop('disabled', false).html(html);
            });
        });
        $(document).on('input change', '#mdil-tbody .mdil-input', function() {
            const first = $('#mdil-tbody .mdil-groi').get(0);
            if (this === first) cascadeFromFirst();
            else readFromTable();
            scheduleSave();
        });
        load();
    });
})();
</script>
@endsection
