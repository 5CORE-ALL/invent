@extends('layouts.vertical', ['title' => $title ?? 'Business 5 Core (B2B) — Listings', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.manager.show', 'b5cb2b') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> B5C B2B Manager</a>
        @include('marketplace._page-heading', ['slug' => 'b5cb2b', 'heading' => 'Business 5 Core (B2B) Listings'])
        <p class="text-muted mb-3">
            Linked tabs: <strong>All</strong> = every Shopify live SKU.
            <strong>Inv SKU Match / Inv SKU Mismatch</strong> = Shopify vs B2B quantity. Shopify qty must not be less than marketplace qty. Match allows marketplace to be short by at most max(3 units, 3% of Shopify). <strong>Linked mismatch SKU</strong> is only the SKUs where marketplace qty is higher than Shopify.
            <strong>Active SKU / Inactive SKU</strong> = actual B2B portal status (not inventory match).
            <em>Fetch new listings</em> pulls new Shopify SKUs into this table.
            Use <em>Link</em> on an unlinked row to match Shopify inventory to B2B (auto by seller SKU, or paste a listing ID).
            <em>Refresh live</em> warms B2B listings from the Laravel store API.
        </p>

        @if(!empty($shopifyCatalogSyncedAt))
            <p class="small text-muted mb-1">Shopify catalog last synced: {{ $shopifyCatalogSyncedAt }}</p>
        @endif
        @if(!empty($b5cCatalogSyncedAt))
            <p class="small text-muted mb-2">B2B catalog last synced: {{ $b5cCatalogSyncedAt }}</p>
        @endif

        @include('marketplace._queue-status', ['slug' => 'b5cb2b'])

        @include('marketplace.b5cb2b._nav', ['active' => 'products'])

        @if($apiError)
            <div class="alert alert-warning">{{ $apiError }}</div>
        @endif

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="badge bg-primary">
                    @if(($linkTab ?? '') === 'all')
                        {{ $products->total() }} Shopify live SKU(s)
                    @elseif(($linkTab ?? '') === 'unlinked')
                        {{ $products->total() }} not on B2B (in-stock Shopify)
                    @elseif(($linkTab ?? '') === 'matched')
                        {{ $products->total() }} Inv SKU Match
                    @elseif(($linkTab ?? '') === 'mismatch')
                        {{ $products->total() }} Inv SKU Mismatch
                    @elseif(($linkTab ?? '') === 'linked_mismatch')
                        {{ $products->total() }} Linked mismatch SKU
                    @elseif(($linkTab ?? '') === 'mismatch_inactive')
                        {{ $products->total() }} Active SKU
                    @elseif(($linkTab ?? '') === 'matched_inactive')
                        {{ $products->total() }} Inactive SKU
                    @elseif(($linkTab ?? '') === 'zero')
                        {{ $products->total() }} zero on Shopify
                    @else
                        {{ $products->total() }} Shopify SKU(s)
                    @endif
                </span>
                <div class="d-flex gap-2 flex-wrap">
                    @if(in_array(($linkTab ?? ''), ['all', 'matched', 'matched_inactive', 'mismatch', 'linked_mismatch', 'mismatch_inactive', 'zero'], true))
                        <a href="{{ request()->fullUrlWithQuery(['refresh_live' => 1, 'clear_cache' => null]) }}" class="btn btn-sm btn-outline-success">
                            <i class="ri-flashlight-line"></i> Refresh live
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['clear_cache' => 1, 'refresh_live' => null]) }}" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Clear the warm B2B live listings cache? Counts will refresh after Refresh live.');">
                            <i class="ri-delete-bin-line"></i> Clear cache
                        </a>
                    @endif
                    @include('marketplace._push-shopify-inv-btn')
                    @include('marketplace._listings-fetch-new')
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-refresh-api">
                        <i class="ri-refresh-line"></i> Sync B2B link map
                    </button>
                </div>
            </div>
            <div id="link-map-progress" class="card-body border-bottom py-3" style="display:none;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span id="link-map-status" class="small text-muted">Starting…</span>
                    <span id="link-map-pct" class="small fw-semibold">0%</span>
                </div>
                <div class="progress" style="height: 18px;">
                    <div id="link-map-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;">0%</div>
                </div>
                <div id="link-map-counts" class="small text-muted mt-2"></div>
            </div>
            <div class="card-body">
                @php
                    $counts = $counts ?? ['all' => 0, 'matched' => 0, 'matched_inactive' => 0, 'mismatch' => 0, 'linked_mismatch' => 0, 'mismatch_inactive' => 0, 'zero' => 0, 'unlinked' => 0, 'linked' => 0];
                    $stateCounts = $stateCounts ?? ['all' => 0, 'active' => 0, 'inactive' => 0, 'other' => 0];
                    $stateTab = $stateTab ?? 'all';
                    $qName = urlencode($searchName ?? '');
                    $qSku = urlencode($searchSku ?? '');
                    $isLinkedTab = in_array(($linkTab ?? ''), ['matched', 'matched_inactive', 'mismatch', 'linked_mismatch', 'mismatch_inactive', 'zero'], true);
                @endphp
                <form method="get" class="mb-3">
                    <div class="row g-2 align-items-end flex-wrap">
                        <div class="col-auto">
                            <label class="form-label small mb-0">Search name</label>
                            <input type="text" name="search_name" class="form-control form-control-sm" value="{{ $searchName ?? '' }}" placeholder="Title or SKU" style="min-width: 160px;">
                        </div>
                        <div class="col-auto">
                            <label class="form-label small mb-0">Search SKU</label>
                            <input type="text" name="search_sku" class="form-control form-control-sm" value="{{ $searchSku }}" placeholder="SKU" style="min-width: 120px;">
                        </div>
                        <input type="hidden" name="link" value="{{ $linkTab ?? 'all' }}">
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary btn-sm">Search</button>
                            <a href="{{ request()->url() }}?link={{ urlencode($linkTab ?? 'all') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
                        </div>
                    </div>
                    @if(in_array(($linkTab ?? ''), ['matched_inactive', 'mismatch_inactive'], true) && empty($stateCacheReady) && (int) ($counts[$linkTab] ?? 0) === 0)
                        <p class="small text-muted mt-2 mb-0">Active / Inactive counts need the live B2B catalog — click <em>Refresh live</em>, wait a minute, then reload.</p>
                    @endif
                </form>

                <ul class="nav nav-tabs nav-bordered mb-3" role="tablist">
                    <li class="nav-item">
                        <a href="{{ request()->url() }}?link=all&search_name={{ $qName }}&search_sku={{ $qSku }}" class="nav-link {{ ($linkTab ?? '') === 'all' ? 'active' : '' }}">All {{ $counts['all'] ?? 0 }}</a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ request()->url() }}?link=matched&search_name={{ $qName }}&search_sku={{ $qSku }}" class="nav-link {{ ($linkTab ?? '') === 'matched' ? 'active' : '' }}">Inv SKU Match {{ $counts['matched'] ?? 0 }}</a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ request()->url() }}?link=mismatch&search_name={{ $qName }}&search_sku={{ $qSku }}" class="nav-link {{ ($linkTab ?? '') === 'mismatch' ? 'active' : '' }}">Inv SKU Mismatch {{ $counts['mismatch'] ?? 0 }}</a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ request()->url() }}?link=linked_mismatch&search_name={{ $qName }}&search_sku={{ $qSku }}" class="nav-link {{ ($linkTab ?? '') === 'linked_mismatch' ? 'active' : '' }}">Linked mismatch SKU {{ $counts['linked_mismatch'] ?? 0 }}</a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ request()->url() }}?link=mismatch_inactive&search_name={{ $qName }}&search_sku={{ $qSku }}" class="nav-link {{ ($linkTab ?? '') === 'mismatch_inactive' ? 'active' : '' }}">Active SKU {{ $counts['mismatch_inactive'] ?? 0 }}</a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ request()->url() }}?link=matched_inactive&search_name={{ $qName }}&search_sku={{ $qSku }}" class="nav-link {{ ($linkTab ?? '') === 'matched_inactive' ? 'active' : '' }}">Inactive SKU {{ $counts['matched_inactive'] ?? 0 }}</a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ request()->url() }}?link=zero&search_name={{ $qName }}&search_sku={{ $qSku }}" class="nav-link {{ ($linkTab ?? '') === 'zero' ? 'active' : '' }}">Zero on Shopify {{ $counts['zero'] ?? 0 }}</a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ request()->url() }}?link=unlinked&search_name={{ $qName }}&search_sku={{ $qSku }}" class="nav-link {{ ($linkTab ?? '') === 'unlinked' ? 'active' : '' }}">Not on B2B {{ $counts['unlinked'] ?? 0 }}</a>
                    </li>
                </ul>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0 table-sm">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 64px;">Image</th>
                                <th>SKU</th>
                                <th>Title (Shopify)</th>
                                <th>B2B ID</th>
                                <th>State</th>
                                <th>Inactive Reason</th>
                                <th>Shopify Qty</th>
                                <th>B2B Qty</th>
                                <th>Shopify Price</th>
                                <th>B2B Price</th>
                                <th>Link</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($products as $p)
                                @php $detailUrl = !empty($p->shopify_sku_id) ? route('marketplace.products.show', ['marketplace' => 'b5cb2b', 'shopifySku' => $p->shopify_sku_id]) : null; @endphp
                                <tr @if($detailUrl) style="cursor: pointer;" onclick="window.location='{{ $detailUrl }}'" @endif>
                                    <td>
                                        @if(!empty($p->image_src))
                                            <img src="{{ $p->image_src }}" alt="" class="img-thumbnail" style="max-width: 48px; max-height: 48px; object-fit: contain;">
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($detailUrl)
                                            <a href="{{ $detailUrl }}" class="text-decoration-none" onclick="event.stopPropagation();"><code>{{ $p->sku }}</code></a>
                                        @else
                                            <code>{{ $p->sku }}</code>
                                        @endif
                                    </td>
                                    <td>
                                        @if($detailUrl)
                                            <a href="{{ $detailUrl }}" class="text-decoration-none text-body" onclick="event.stopPropagation();">
                                                {{ Str::limit($p->title ?? '—', 50) }}
                                            </a>
                                        @else
                                            {{ Str::limit($p->title ?? '—', 50) }}
                                        @endif
                                        @if(!empty($p->b5c_title) && $p->b5c_title !== $p->title)
                                            <div class="text-muted small">B2B: {{ Str::limit($p->b5c_title, 40) }}</div>
                                        @endif
                                    </td>
                                    <td class="small">{{ $p->product_id ?? '—' }}</td>
                                    <td class="small">
                                        @if(!empty($p->b5c_state))
                                            @php $st = strtolower((string)$p->b5c_state); @endphp
                                            <span class="badge {{ $st === 'active' ? 'bg-success-subtle text-success' : (in_array($st, ['draft', 'archived', 'inactive'], true) ? 'bg-warning-subtle text-warning' : 'bg-light text-muted') }}">{{ $p->b5c_state }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="small">{{ !empty($p->inactive_reason) ? $p->inactive_reason : '—' }}</td>
                                    <td>{{ $p->shopify_quantity !== null ? $p->shopify_quantity : '—' }}</td>
                                    <td>{{ ($p->ae_quantity ?? $p->quantity) !== null ? ($p->ae_quantity ?? $p->quantity) : '—' }}</td>
                                    <td>{{ isset($p->shopify_price) ? number_format((float)$p->shopify_price, 2) : '—' }}</td>
                                    <td>{{ isset($p->price) ? number_format((float)$p->price, 2) : '—' }}</td>
                                    <td>
                                        @include('marketplace._listings-link-cell', [
                                            'linked' => $p->linked,
                                            'listingStatus' => $p->listing_status ?? '',
                                            'shopifySkuId' => $p->shopify_sku_id ?? null,
                                            'sku' => $p->sku ?? '',
                                        ])
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center text-muted py-4">
                                        No Shopify SKUs found.
                                        @if(($linkTab ?? 'all') === 'matched')
                                            None linked yet — click <strong>Sync B2B link map</strong> after SKUs exist on the B2B store.
                                        @elseif($connected)
                                            Your Shopify catalog may be empty, or filters excluded all rows.
                                        @else
                                            <a href="{{ route('marketplace.manager.b5cb2b.connect') }}">Connect Business 5 Core (B2B)</a> first.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($products->hasPages())
                    <div class="d-flex justify-content-center mt-3">{{ $products->onEachSide(1)->links('pagination::bootstrap-5') }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
document.getElementById('btn-refresh-api')?.addEventListener('click', function () {
    var btn = this;
    var progress = document.getElementById('link-map-progress');
    var bar = document.getElementById('link-map-bar');
    var statusEl = document.getElementById('link-map-status');
    var pctEl = document.getElementById('link-map-pct');
    var countsEl = document.getElementById('link-map-counts');
    var url = '{{ route('marketplace.manager.b5cb2b.refresh') }}';

    if (!confirm('Sync all Business 5 Core B2B listings and refresh SKU ↔ listing ID mappings? This may take a few minutes.')) {
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="ri-loader-4-line"></i> Syncing…';
    progress.style.display = '';
    bar.style.width = '15%';
    bar.textContent = '15%';
    pctEl.textContent = '15%';
    statusEl.textContent = 'Syncing B2B listings from the Laravel store API…';
    countsEl.textContent = '';

    fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({}),
    }).then(function (r) { return r.json(); }).then(function (data) {
        if (!data.success) {
            alert(data.message || 'Sync failed.');
            progress.style.display = 'none';
            btn.disabled = false;
            btn.innerHTML = '<i class="ri-refresh-line"></i> Sync B2B link map';
            return;
        }
        bar.classList.remove('progress-bar-animated');
        bar.style.width = '100%';
        bar.textContent = '100%';
        pctEl.textContent = '100%';
        statusEl.textContent = data.message || 'Done';
        countsEl.textContent = (data.stored || data.count || 0) + ' listing(s) stored';
        setTimeout(function () { location.reload(); }, 800);
    }).catch(function () {
        alert('Request failed.');
        progress.style.display = 'none';
        btn.disabled = false;
        btn.innerHTML = '<i class="ri-refresh-line"></i> Sync B2B link map';
    });
});
</script>
@include('marketplace._sync-mismatch-now', [
    'url' => route('marketplace.manager.b5cb2b.sync.mismatch.inventory'),
    'confirm' => 'Push the actual live Shopify quantity to every Linked mismatch SKU on Business 5 Core B2B right now (no queue)? This runs in batches and may take a few minutes.',
    'limit' => 5,
])
@include('marketplace._listings-instant-map-js')
@endsection
