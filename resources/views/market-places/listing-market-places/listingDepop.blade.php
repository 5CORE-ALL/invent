@extends('layouts.vertical', ['title' => 'Listing Depop', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <link rel="stylesheet" href="{{ asset('css/listing-page-tools.css') }}?v=3">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        #depop-listing-wrap .tabulator { border: 1px solid #dee2e6; border-radius: 8px; font-size: 13px; }
        #depop-listing-wrap .tabulator .tabulator-header { background: #00d5d5; }
        .depop-stat { min-width: 110px; }
    </style>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h4 class="mb-1">Listing Depop</h4>
                <p class="text-muted mb-0">No API yet. Upload current Depop listings as CSV; they are matched to CP Master. Missing = CP Master REQ with INV &gt; 0 that are not on the sheet.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <span class="badge bg-success depop-stat p-2">REQ: <span id="depop-req">{{ number_format((int) ($counts['REQ'] ?? 0)) }}</span></span>
                <span class="badge bg-danger depop-stat p-2">NRL: <span id="depop-nrl">{{ number_format((int) ($counts['NRL'] ?? 0)) }}</span></span>
                <span class="badge bg-primary depop-stat p-2">Listed: <span id="depop-listed">{{ number_format((int) ($counts['Listed'] ?? 0)) }}</span></span>
                <span class="badge bg-warning text-dark depop-stat p-2">Missing: <span id="depop-missing">{{ number_format((int) ($counts['Pending'] ?? 0)) }}</span></span>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div id="depop-listing-toolbar" class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <input type="search" id="depop-search" class="form-control form-control-sm" style="max-width:220px;" placeholder="Search SKU">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="depop-filter-missing">Missing only</button>
                    <a href="{{ route('listing.depop.template') }}" class="btn btn-sm btn-outline-primary">Download template</a>
                    <a href="{{ route('listing.depop.export') }}" class="btn btn-sm btn-outline-success">Export current sheet</a>
                    <label class="btn btn-sm btn-warning mb-0">
                        Upload current listings
                        <input type="file" id="depop-csv" accept=".csv,text/csv,text/plain" hidden>
                    </label>
                    <span class="text-muted small">CSV needs a SKU column. Upload replaces the current Depop listed set.</span>
                </div>
                <div id="depop-listing-wrap">
                    <div id="depop-listing-table"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrf = '{{ csrf_token() }}';
    let table = null;
    let missingOnly = new URLSearchParams(location.search).get('missing') === '1';

    function applyMissingFilter() {
        if (!table) return;
        if (missingOnly) {
            table.setFilter(function (data) {
                return Number(data.INV || 0) > 0
                    && String(data.nr_req || 'REQ').toUpperCase() === 'REQ'
                    && String(data.listed || '') !== 'Listed';
            });
        } else {
            table.clearFilter(true);
        }
    }

    fetch('{{ url('/listing_depop/view-data') }}', { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            table = new Tabulator('#depop-listing-table', {
                data: res.data || [],
                layout: 'fitColumns',
                height: 'calc(100vh - 280px)',
                placeholder: 'No CP Master SKUs',
                columns: [
                    { title: 'SKU', field: 'sku', minWidth: 160, headerFilter: 'input' },
                    { title: 'INV', field: 'INV', width: 80, hozAlign: 'center' },
                    { title: 'NR / REQ', field: 'nr_req', width: 110, hozAlign: 'center' },
                    { title: 'Listed', field: 'listed', width: 110, hozAlign: 'center' },
                    { title: 'Listing ID', field: 'listing_id', minWidth: 140 },
                ],
            });
            applyMissingFilter();
            document.getElementById('depop-filter-missing').classList.toggle('btn-warning', missingOnly);
            document.getElementById('depop-filter-missing').classList.toggle('btn-outline-secondary', !missingOnly);
        });

    document.getElementById('depop-search').addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        if (!table) return;
        if (!q) {
            applyMissingFilter();
            return;
        }
        table.setFilter('sku', 'like', q);
    });

    document.getElementById('depop-filter-missing').addEventListener('click', function () {
        missingOnly = !missingOnly;
        this.classList.toggle('btn-warning', missingOnly);
        this.classList.toggle('btn-outline-secondary', !missingOnly);
        applyMissingFilter();
    });

    document.getElementById('depop-csv').addEventListener('change', function () {
        const file = this.files && this.files[0];
        this.value = '';
        if (!file) return;
        const body = new FormData();
        body.append('file', file);
        body.append('_token', csrf);
        fetch('{{ route('listing.depop.import') }}', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: body,
        })
        .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
        .then(function (res) {
            alert((res.data && res.data.message) || (res.ok ? 'Imported' : 'Import failed'));
            if (res.data && res.data.success) location.reload();
        })
        .catch(function () { alert('Upload failed.'); });
    });
});
</script>
@endsection
