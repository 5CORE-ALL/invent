@extends('layouts.vertical', ['title' => $title ?? 'Shopify PLS — Listings', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.manager.show', 'pls') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> Shopify PLS Manager</a>
        @include('marketplace._page-heading', ['slug' => 'pls', 'heading' => 'Shopify PLS Listings'])
        <p class="text-muted mb-3">
            Linked tabs: <strong>All</strong> = every Shopify live SKU.
            <strong>Active SKU / Inactive SKU</strong> = qty matches, or Shopify is higher by at most the higher of 3 units or 3% of Shopify qty, split by PLS product status (active vs draft/archived).
            <strong>Active SKU Mismatch / Inactive SKU Mismatch</strong> = PLS qty is higher than Shopify, or the gap is beyond that bar — use <em>Sync Mismatch inventory now</em> to push B2C qty onto PLS.
            <em>Refresh live</em> warms PLS inventory from the Admin API. Refresh Shopify from <a href="{{ route('marketplace.manager.index') }}">Marketplace Manager</a>.
        </p>

        @if(!empty($shopifyCatalogSyncedAt))
            <p class="small text-muted mb-1">Shopify catalog last synced: {{ $shopifyCatalogSyncedAt }}</p>
        @endif
        @if(!empty($plsCatalogSyncedAt))
            <p class="small text-muted mb-2">PLS catalog last synced: {{ $plsCatalogSyncedAt }}</p>
        @endif

        @include('marketplace._queue-status', ['slug' => 'pls'])

        @include('marketplace.pls._nav', ['active' => 'products'])

        @if($apiError)
            <div class="alert alert-warning">{{ $apiError }}</div>
        @endif

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="badge bg-primary">
                    @if(($linkTab ?? '') === 'all')
                        {{ $products->total() }} Shopify live SKU(s)
                    @elseif(($linkTab ?? '') === 'unlinked')
                        {{ $products->total() }} not on PLS (in-stock Shopify)
                    @elseif(($linkTab ?? '') === 'matched')
                        {{ $products->total() }} Active SKU
                    @elseif(($linkTab ?? '') === 'matched_inactive')
                        {{ $products->total() }} Inactive SKU
                    @elseif(($linkTab ?? '') === 'mismatch')
                        {{ $products->total() }} Active SKU Mismatch
                    @elseif(($linkTab ?? '') === 'mismatch_inactive')
                        {{ $products->total() }} Inactive SKU Mismatch
                    @elseif(($linkTab ?? '') === 'zero')
                        {{ $products->total() }} zero on Shopify
                    @else
                        {{ $products->total() }} Shopify SKU(s)
                    @endif
                </span>
                <div class="d-flex gap-2 flex-wrap">
                    @if(in_array(($linkTab ?? ''), ['all', 'matched', 'matched_inactive', 'mismatch', 'mismatch_inactive', 'zero'], true))
                        <a href="{{ request()->fullUrlWithQuery(['refresh_live' => 1, 'clear_cache' => null]) }}" class="btn btn-sm btn-outline-success">
                            <i class="ri-flashlight-line"></i> Refresh live
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['clear_cache' => 1, 'refresh_live' => null]) }}" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Clear the warm PLS live listings cache? Counts will refresh after Refresh live.');">
                            <i class="ri-delete-bin-line"></i> Clear cache
                        </a>
                    @endif
                    @if(in_array(($linkTab ?? ''), ['mismatch', 'mismatch_inactive'], true))
                        <button type="button" class="btn btn-sm btn-warning" id="btn-sync-mismatch-now" data-scope="{{ $linkTab }}">
                            <i class="ri-upload-2-line"></i> Sync Mismatch inventory now
                        </button>
                    @endif
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-refresh-api">
                        <i class="ri-refresh-line"></i> Sync PLS catalog
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-success" id="btn-refresh-pricing">
                        <i class="ri-database-2-line"></i> Refresh pricing
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
                    $counts = $counts ?? ['all' => 0, 'matched' => 0, 'matched_inactive' => 0, 'mismatch' => 0, 'mismatch_inactive' => 0, 'zero' => 0, 'unlinked' => 0, 'linked' => 0];
                    $stateCounts = $stateCounts ?? ['all' => 0, 'active' => 0, 'inactive' => 0, 'other' => 0];
                    $stateTab = $stateTab ?? 'all';
                    $qName = urlencode($searchName ?? '');
                    $qSku = urlencode($searchSku ?? '');
                    $isLinkedTab = in_array(($linkTab ?? ''), ['matched', 'matched_inactive', 'mismatch', 'mismatch_inactive', 'zero'], true);
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
                        @if($isLinkedTab)
                            <div class="col-auto">
                                <label class="form-label small mb-0">State</label>
                                <select name="state" class="form-select form-select-sm" style="min-width: 180px;" onchange="this.form.submit()">
                                    <option value="all" @selected($stateTab === 'all')>All ({{ (int) ($stateCounts['all'] ?? 0) }})</option>
                                    <option value="active" @selected($stateTab === 'active')>Active ({{ (int) ($stateCounts['active'] ?? 0) }})</option>
                                    <option value="inactive" @selected($stateTab === 'inactive')>Inactive ({{ (int) ($stateCounts['inactive'] ?? 0) }})</option>
                                    @if(!empty($stateCounts['other']))
                                        <option value="other" @selected($stateTab === 'other')>Other ({{ (int) $stateCounts['other'] }})</option>
                                    @endif
                                </select>
                            </div>
                        @endif
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary btn-sm">Search</button>
                            <a href="{{ request()->url() }}?link={{ urlencode($linkTab ?? 'all') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
                        </div>
                    </div>
                    @if($isLinkedTab && empty($stateCacheReady))
                        <p class="small text-muted mt-2 mb-0">State counts need the live PLS catalog cache — click <em>Refresh live</em>, wait a minute, then reload.</p>
                    @endif
                </form>

                <ul class="nav nav-tabs nav-bordered mb-3" role="tablist">
                    <li class="nav-item">
                        <a href="{{ request()->url() }}?link=all&search_name={{ $qName }}&search_sku={{ $qSku }}" class="nav-link {{ ($linkTab ?? '') === 'all' ? 'active' : '' }}">All {{ $counts['all'] ?? 0 }}</a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ request()->url() }}?link=matched&state={{ urlencode($stateTab) }}&search_name={{ $qName }}&search_sku={{ $qSku }}" class="nav-link {{ ($linkTab ?? '') === 'matched' ? 'active' : '' }}">Active SKU {{ $counts['matched'] ?? 0 }}</a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ request()->url() }}?link=mismatch&state={{ urlencode($stateTab) }}&search_name={{ $qName }}&search_sku={{ $qSku }}" class="nav-link {{ ($linkTab ?? '') === 'mismatch' ? 'active' : '' }}">Active SKU Mismatch {{ $counts['mismatch'] ?? 0 }}</a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ request()->url() }}?link=matched_inactive&state={{ urlencode($stateTab) }}&search_name={{ $qName }}&search_sku={{ $qSku }}" class="nav-link {{ ($linkTab ?? '') === 'matched_inactive' ? 'active' : '' }}">Inactive SKU {{ $counts['matched_inactive'] ?? 0 }}</a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ request()->url() }}?link=mismatch_inactive&state={{ urlencode($stateTab) }}&search_name={{ $qName }}&search_sku={{ $qSku }}" class="nav-link {{ ($linkTab ?? '') === 'mismatch_inactive' ? 'active' : '' }}">Inactive SKU Mismatch {{ $counts['mismatch_inactive'] ?? 0 }}</a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ request()->url() }}?link=zero&state={{ urlencode($stateTab) }}&search_name={{ $qName }}&search_sku={{ $qSku }}" class="nav-link {{ ($linkTab ?? '') === 'zero' ? 'active' : '' }}">Zero on Shopify {{ $counts['zero'] ?? 0 }}</a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ request()->url() }}?link=unlinked&search_name={{ $qName }}&search_sku={{ $qSku }}" class="nav-link {{ ($linkTab ?? '') === 'unlinked' ? 'active' : '' }}">Not on PLS {{ $counts['unlinked'] ?? 0 }}</a>
                    </li>
                </ul>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0 table-sm">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 64px;">Image</th>
                                <th>SKU</th>
                                <th>Title (Shopify)</th>
                                <th>PLS ID</th>
                                <th>State</th>
                                <th>Shopify Qty</th>
                                <th>PLS Qty</th>
                                <th>Shopify Price</th>
                                <th>PLS Price</th>
                                <th>Link</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($products as $p)
                                @php $detailUrl = !empty($p->shopify_sku_id) ? route('marketplace.products.show', ['marketplace' => 'pls', 'shopifySku' => $p->shopify_sku_id]) : null; @endphp
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
                                        @if(!empty($p->pls_title) && $p->pls_title !== $p->title)
                                            <div class="text-muted small">PLS: {{ Str::limit($p->pls_title, 40) }}</div>
                                        @endif
                                    </td>
                                    <td class="small">{{ $p->product_id ?? '—' }}</td>
                                    <td class="small">
                                        @if(!empty($p->pls_state))
                                            @php $st = strtolower((string)$p->pls_state); @endphp
                                            <span class="badge {{ $st === 'active' ? 'bg-success-subtle text-success' : (in_array($st, ['draft', 'archived', 'inactive'], true) ? 'bg-warning-subtle text-warning' : 'bg-light text-muted') }}">{{ $p->pls_state }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ $p->shopify_quantity !== null ? $p->shopify_quantity : '—' }}</td>
                                    <td>{{ ($p->ae_quantity ?? $p->quantity) !== null ? ($p->ae_quantity ?? $p->quantity) : '—' }}</td>
                                    <td>{{ isset($p->shopify_price) ? number_format((float)$p->shopify_price, 2) : '—' }}</td>
                                    <td>{{ isset($p->price) ? number_format((float)$p->price, 2) : '—' }}</td>
                                    <td>
                                        @if($p->linked)
                                            <span class="badge bg-success-subtle text-success">Linked</span>
                                        @else
                                            <span class="badge bg-light text-muted">Not linked</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-4">
                                        No Shopify SKUs found.
                                        @if(($linkTab ?? 'all') === 'matched')
                                            None linked yet — click <strong>Sync PLS catalog</strong> after SKUs exist on the PLS store.
                                        @elseif($connected)
                                            Your Shopify catalog may be empty, or filters excluded all rows.
                                        @else
                                            <a href="{{ route('marketplace.manager.pls.connect') }}">Connect Shopify PLS</a> first.
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
    var url = '{{ route('marketplace.manager.pls.refresh.products') }}';

    if (!confirm('Sync the PLS Shopify catalog (products + variants) and refresh SKU mappings? This may take a few minutes.')) {
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="ri-loader-4-line"></i> Syncing…';
    progress.style.display = '';
    bar.style.width = '15%';
    bar.textContent = '15%';
    pctEl.textContent = '15%';
    statusEl.textContent = 'Syncing PLS catalog from Admin API…';
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
            btn.innerHTML = '<i class="ri-refresh-line"></i> Sync PLS catalog';
            return;
        }
        bar.classList.remove('progress-bar-animated');
        bar.style.width = '100%';
        bar.textContent = '100%';
        pctEl.textContent = '100%';
        statusEl.textContent = data.message || 'Done';
        countsEl.textContent = (data.count || data.total_upserted || 0) + ' variant row(s) with SKU';
        setTimeout(function () { location.reload(); }, 800);
    }).catch(function () {
        alert('Request failed.');
        progress.style.display = 'none';
        btn.disabled = false;
        btn.innerHTML = '<i class="ri-refresh-line"></i> Sync PLS catalog';
    });
});

document.getElementById('btn-refresh-pricing')?.addEventListener('click', function () {
    var btn = this;
    if (!confirm('Rebuild pls_products pricing (L30/L60) from the PLS catalog and orders?')) {
        return;
    }
    btn.disabled = true;
    var original = btn.innerHTML;
    btn.innerHTML = '<i class="ri-loader-4-line"></i> Refreshing…';
    fetch('{{ route('marketplace.manager.pls.refresh.pricing') }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    }).then(function (r) { return r.json(); }).then(function (data) {
        alert(data.message || (data.success ? 'Done' : 'Failed'));
        if (data.success) location.reload();
        else {
            btn.disabled = false;
            btn.innerHTML = original;
        }
    }).catch(function () {
        alert('Request failed.');
        btn.disabled = false;
        btn.innerHTML = original;
    });
});

document.getElementById('btn-sync-mismatch-now')?.addEventListener('click', function () {
    var btn = this;
    var scope = btn.getAttribute('data-scope') || 'mismatch';
    if (!confirm('Sync ' + (scope === 'mismatch_inactive' ? 'Inactive' : 'Active') + ' Mismatch SKUs from live Shopify → PLS right now (no queue)? This runs in batches and may take a few minutes.')) {
        return;
    }
    btn.disabled = true;
    var original = btn.innerHTML;
    var url = '{{ route('marketplace.manager.pls.sync.mismatch.inventory') }}';
    var offset = 0;
    var totals = { updated: 0, failed: 0, skipped: 0 };

    function tick() {
        btn.innerHTML = '<i class="ri-loader-4-line"></i> Syncing… ' + offset;
        return fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ offset: offset, limit: 25, scope: scope }),
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (!data.success) {
                alert(data.message || 'Sync failed.');
                btn.disabled = false;
                btn.innerHTML = original;
                return;
            }
            totals.updated += data.updated || 0;
            totals.failed += data.failed || 0;
            totals.skipped += data.skipped || 0;
            offset = data.offset || offset;
            if (data.done) {
                alert((data.message || 'Done.') + '\nUpdated: ' + totals.updated + ', Failed: ' + totals.failed + ', Skipped: ' + totals.skipped);
                location.reload();
                return;
            }
            setTimeout(tick, 200);
        }).catch(function () {
            alert('Request failed.');
            btn.disabled = false;
            btn.innerHTML = original;
        });
    }

    tick();
});
</script>
@endsection
