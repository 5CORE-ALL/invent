@extends('layouts.vertical', ['title' => $title ?? 'Reverb Listing Master', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="page-title mb-1">Reverb Listing Master</h4>
                <p class="text-muted mb-0 small">
                    Master data for Reverb listing fields (Make, Model, Finish, Year, Condition, Shipping Profile).
                    Used by <strong>Autopopulate Missing Data</strong> on Marketplace → Reverb → View.
                </p>
            </div>
            <a href="{{ url('/marketplace/reverb/products') }}" class="btn btn-sm btn-outline-primary">
                <i class="ri-store-2-line"></i> Reverb Listings
            </a>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="row g-2 align-items-end mb-3">
                    <div class="col-md-4">
                        <label class="form-label small mb-0">Search SKU / Make / Model</label>
                        <input type="text" id="rlmSearch" class="form-control form-control-sm" placeholder="Type to filter…">
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-sm btn-primary" id="rlmReload">
                            <i class="ri-refresh-line"></i> Reload
                        </button>
                    </div>
                    <div class="col-auto">
                        <span id="rlmStatus" class="small text-muted"></span>
                    </div>
                </div>

                <div class="table-responsive" style="max-height: 70vh;">
                    <table class="table table-sm table-bordered table-hover mb-0" id="rlmTable">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>SKU</th>
                                <th>Title</th>
                                <th>Make</th>
                                <th>Model</th>
                                <th>Finish</th>
                                <th>Year</th>
                                <th>Condition</th>
                                <th>Shipping Profile ID</th>
                            </tr>
                        </thead>
                        <tbody id="rlmBody">
                            <tr><td colspan="8" class="text-center text-muted py-4">Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
(function () {
    var csrf = '{{ csrf_token() }}';
    var dataUrl = @json(route('reverb.listing.master.data'));
    var updateUrl = @json(route('reverb.listing.master.update'));
    var statusEl = document.getElementById('rlmStatus');
    var body = document.getElementById('rlmBody');

    function setStatus(msg, isErr) {
        statusEl.textContent = msg || '';
        statusEl.classList.toggle('text-danger', !!isErr);
        statusEl.classList.toggle('text-muted', !isErr);
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function load() {
        var search = (document.getElementById('rlmSearch').value || '').trim();
        setStatus('Loading…');
        body.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Loading…</td></tr>';
        fetch(dataUrl + (search ? ('?search=' + encodeURIComponent(search)) : ''), {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (!data.success) {
                body.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">' + esc(data.message || 'Failed') + '</td></tr>';
                setStatus(data.message || 'Failed', true);
                return;
            }
            var rows = data.data || [];
            if (!rows.length) {
                body.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No rows found.</td></tr>';
                setStatus('0 rows');
                return;
            }
            body.innerHTML = rows.map(function (row) {
                return '<tr data-id="' + row.id + '">' +
                    '<td><code>' + esc(row.sku) + '</code></td>' +
                    '<td class="small">' + esc((row.title || '').slice(0, 60)) + '</td>' +
                    fieldCell(row, 'reverb_make') +
                    fieldCell(row, 'reverb_model') +
                    fieldCell(row, 'reverb_finish') +
                    fieldCell(row, 'reverb_year') +
                    fieldCell(row, 'reverb_condition') +
                    fieldCell(row, 'reverb_shipping_profile_id') +
                    '</tr>';
            }).join('');
            body.querySelectorAll('input[data-field]').forEach(bindSave);
            setStatus(rows.length + ' row(s)');
        }).catch(function () {
            body.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Request failed.</td></tr>';
            setStatus('Request failed', true);
        });
    }

    function fieldCell(row, field) {
        return '<td><input type="text" class="form-control form-control-sm" data-field="' + field + '" value="' + esc(row[field] || '') + '"></td>';
    }

    function bindSave(inp) {
        var last = inp.value;
        inp.addEventListener('change', function () {
            var tr = inp.closest('tr');
            var id = tr && tr.getAttribute('data-id');
            var field = inp.getAttribute('data-field');
            if (!id || !field || inp.value === last) return;
            setStatus('Saving ' + field + '…');
            fetch(updateUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf
                },
                body: JSON.stringify({ id: id, field: field, value: inp.value })
            }).then(function (r) { return r.json(); }).then(function (data) {
                if (!data.success) {
                    setStatus(data.message || 'Save failed', true);
                    inp.value = last;
                    return;
                }
                last = inp.value;
                setStatus(data.message || 'Saved');
            }).catch(function () {
                setStatus('Save failed', true);
                inp.value = last;
            });
        });
    }

    document.getElementById('rlmReload')?.addEventListener('click', load);
    var searchTimer = null;
    document.getElementById('rlmSearch')?.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(load, 350);
    });
    load();
})();
</script>
@endsection
