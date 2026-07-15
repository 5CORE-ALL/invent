@extends('layouts.vertical', ['title' => $title ?? 'Reverb — Listings', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.manager.show', 'reverb') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> Reverb Manager</a>
        @include('marketplace._page-heading', ['slug' => 'reverb', 'heading' => 'Reverb Listings'])
        <p class="text-muted mb-3">
            @if(!empty($liveMode) && ($linkTab ?? '') === 'linked')
                <strong>Linked</strong> lists Shopify SKUs that are linked to Reverb (paginated 50/page).
                <strong>Shopify Qty</strong> and <strong>Reverb Qty</strong> are loaded live for the current page only.
                Mismatches on this page are auto-queued to sync. Use <em>Refresh live</em> to warm the full Reverb catalog in the background.
            @elseif(!empty($liveMode))
                Page is paginated. Reverb Qty is live for the current page only.
            @else
                Paginated Shopify catalog. Open <strong>Linked</strong> for live Shopify + live Reverb quantities.
            @endif
        </p>

        @if(!empty($liveQueued))
            <div class="alert alert-info py-2">Queued {{ (int) $liveQueued }} SKU(s) for live inventory sync (Shopify → marketplace).</div>
        @endif

        @include('marketplace._queue-status', ['slug' => 'reverb'])

        @include('marketplace.reverb._nav', ['active' => 'products'])

        @if($apiError)
            <div class="alert alert-warning">{{ $apiError }}</div>
        @endif

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="badge bg-primary">
                    @if(($linkTab ?? 'all') === 'not_in_shopify')
                        {{ $products->total() }} live Reverb SKU(s) not in Shopify
                    @elseif(!empty($liveMode) && ($linkTab ?? '') === 'linked')
                        {{ $products->total() }} linked Shopify SKU(s) (live qty on page)
                    @elseif(!empty($liveMode))
                        {{ $products->total() }} live linked Reverb SKU(s)
                    @else
                        {{ $products->total() }} Shopify SKU(s)
                    @endif
                </span>
                <div class="d-flex gap-2 flex-wrap">
                    @if(in_array(($linkTab ?? ''), ['linked', 'not_in_shopify'], true))
                        <a href="{{ request()->fullUrlWithQuery(['refresh_live' => 1]) }}" class="btn btn-sm btn-outline-success">
                            <i class="ri-flashlight-line"></i> Refresh live
                        </a>
                    @endif
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-refresh-api">
                        <i class="ri-refresh-line"></i> Sync Reverb link map
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
                    $counts = $counts ?? ['all' => 0, 'linked' => 0, 'unlinked' => 0, 'not_in_shopify' => 0];
                    $stateCounts = $stateCounts ?? ['all' => 0, 'live' => 0, 'sold' => 0, 'out_of_stock' => 0, 'ended' => 0, 'draft' => 0, 'other' => 0];
                    $stateTab = $stateTab ?? 'all';
                    $qName = urlencode($searchName ?? '');
                    $qSku = urlencode($searchSku ?? '');
                    $isLinkedTab = ($linkTab ?? '') === 'linked';
                @endphp
                <form method="get" class="mb-3">
                    <div class="row g-2 align-items-end flex-wrap">
                        <div class="col-auto">
                            <label class="form-label small mb-0">Search name</label>
                            <input type="text" name="search_name" class="form-control form-control-sm" value="{{ $searchName }}" placeholder="Title or SKU" style="min-width: 160px;">
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
                                    <option value="live" @selected($stateTab === 'live')>Live ({{ (int) ($stateCounts['live'] ?? 0) }})</option>
                                    <option value="sold" @selected($stateTab === 'sold')>Sold ({{ (int) ($stateCounts['sold'] ?? 0) }})</option>
                                    <option value="out_of_stock" @selected($stateTab === 'out_of_stock')>Out of stock ({{ (int) ($stateCounts['out_of_stock'] ?? 0) }})</option>
                                    <option value="ended" @selected($stateTab === 'ended')>Ended ({{ (int) ($stateCounts['ended'] ?? 0) }})</option>
                                    <option value="draft" @selected($stateTab === 'draft')>Draft ({{ (int) ($stateCounts['draft'] ?? 0) }})</option>
                                    @if(!empty($stateCounts['other']))
                                        <option value="other" @selected($stateTab === 'other')>Other ({{ (int) $stateCounts['other'] }})</option>
                                    @endif
                                </select>
                            </div>
                        @endif
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary btn-sm">Search</button>
                            <a href="{{ request()->url() }}?link={{ urlencode($linkTab ?? 'linked') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
                        </div>
                    </div>
                    @if($isLinkedTab && empty($stateCacheReady))
                        <p class="small text-muted mt-2 mb-0">State counts need the live Reverb catalog cache — click <em>Refresh live</em>, wait a minute, then reload.</p>
                    @endif
                </form>

                <ul class="nav nav-tabs nav-bordered mb-3" role="tablist">
                    <li class="nav-item">
                        <a href="{{ request()->url() }}?link=all&search_name={{ $qName }}&search_sku={{ $qSku }}" class="nav-link {{ ($linkTab ?? 'all') === 'all' ? 'active' : '' }}">All {{ $counts['all'] ?? 0 }}</a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ request()->url() }}?link=linked&state={{ urlencode($stateTab) }}&search_name={{ $qName }}&search_sku={{ $qSku }}" class="nav-link {{ ($linkTab ?? '') === 'linked' ? 'active' : '' }}">Linked {{ $counts['linked'] ?? 0 }}</a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ request()->url() }}?link=unlinked&search_name={{ $qName }}&search_sku={{ $qSku }}" class="nav-link {{ ($linkTab ?? '') === 'unlinked' ? 'active' : '' }}">Not on Reverb {{ $counts['unlinked'] ?? 0 }}</a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ request()->url() }}?link=not_in_shopify&search_name={{ $qName }}&search_sku={{ $qSku }}" class="nav-link {{ ($linkTab ?? '') === 'not_in_shopify' ? 'active' : '' }}">Not in Shopify {{ $counts['not_in_shopify'] ?? 0 }}</a>
                    </li>
                </ul>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0 table-sm">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 64px;">Image</th>
                                <th>SKU</th>
                                <th>{{ ($linkTab ?? '') === 'not_in_shopify' ? 'Title (Reverb)' : 'Title (Shopify)' }}</th>
                                <th>Reverb ID</th>
                                <th>State</th>
                                <th>Shopify Qty</th>
                                <th>Reverb Qty</th>
                                <th>Shopify Price</th>
                                <th>Reverb Price</th>
                                <th>Link</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($products as $p)
                                @php $detailUrl = !empty($p->shopify_sku_id) ? route('marketplace.products.show', ['marketplace' => 'reverb', 'shopifySku' => $p->shopify_sku_id]) : null; @endphp
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
                                        @if(!empty($p->reverb_title) && $p->reverb_title !== $p->title)
                                            <div class="text-muted small">Reverb: {{ Str::limit($p->reverb_title, 40) }}</div>
                                        @endif
                                    </td>
                                    <td class="small">{{ $p->product_id ?? '—' }}</td>
                                    <td class="small">
                                        @if(!empty($p->reverb_state))
                                            @php $st = strtolower((string)$p->reverb_state); @endphp
                                            <span class="badge {{ $st === 'live' ? 'bg-success-subtle text-success' : ($st === 'sold' || $st === 'out_of_stock' ? 'bg-warning-subtle text-warning' : 'bg-light text-muted') }}">{{ $p->reverb_state }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ $p->shopify_quantity !== null ? $p->shopify_quantity : '—' }}</td>
                                    <td>{{ ($p->rv_quantity ?? $p->quantity) !== null ? ($p->rv_quantity ?? $p->quantity) : '—' }}</td>
                                    <td>{{ isset($p->shopify_price) ? number_format((float)$p->shopify_price, 2) : '—' }}</td>
                                    <td>{{ isset($p->price) ? number_format((float)$p->price, 2) : '—' }}</td>
                                    <td>
                                        @if(($p->listing_status ?? '') === 'not_in_shopify')
                                            <span class="badge bg-warning-subtle text-warning">Not in Shopify</span>
                                        @elseif($p->linked)
                                            <span class="badge bg-success-subtle text-success">Linked</span>
                                        @else
                                            <span class="badge bg-light text-muted">Not linked</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-4">
                                        @if(($linkTab ?? 'all') === 'not_in_shopify')
                                            No live Reverb listings found without a matching Shopify SKU.
                                        @else
                                            No Shopify SKUs found.
                                        @endif
                                        @if(($linkTab ?? 'all') === 'linked')
                                            None linked yet — click <strong>Sync Reverb link map</strong> after SKUs exist in Reverb.
                                        @elseif(($linkTab ?? 'all') === 'not_in_shopify')
                                            All synced Reverb SKUs appear to exist in your Shopify catalog, or run <strong>Sync Reverb link map</strong> first.
                                        @elseif($connected)
                                            Your Shopify catalog may be empty, or filters excluded all rows.
                                        @else
                                            <a href="{{ route('marketplace.manager.reverb.connect') }}">Connect Reverb</a> first.
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

<script>
document.getElementById('btn-refresh-api')?.addEventListener('click', function () {
    var btn = this;
    var progress = document.getElementById('link-map-progress');
    var bar = document.getElementById('link-map-bar');
    var statusEl = document.getElementById('link-map-status');
    var pctEl = document.getElementById('link-map-pct');
    var countsEl = document.getElementById('link-map-counts');
    var url = '{{ route('marketplace.manager.reverb.refresh') }}';
    var page = 1;

    function setProgress(pageNum, totalPage, totalUpserted, message, totalCount) {
        var pct = 0;
        if (totalPage && totalPage > 0) {
            pct = Math.min(100, Math.round((pageNum / totalPage) * 100));
        } else if (pageNum > 1) {
            pct = Math.min(95, pageNum * 5);
        }
        bar.style.width = pct + '%';
        bar.textContent = pct + '%';
        pctEl.textContent = pct + '%';
        statusEl.textContent = message || ('Syncing page ' + pageNum + (totalPage ? ' of ' + totalPage : '') + '…');
        var extra = totalCount ? ' (' + totalCount + ' products on Reverb)' : '';
        countsEl.textContent = totalUpserted + ' SKU link(s) saved so far' + extra;
    }

    function syncNext(reset) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ page: page, reset: !!reset }),
        }).then(function (r) { return r.json(); });
    }

    if (!confirm('Sync all Reverb listings and refresh SKU ↔ product_id mappings? This may take a few minutes.')) {
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="ri-loader-4-line"></i> Syncing…';
    progress.style.display = '';
    setProgress(0, null, 0, 'Starting sync…');

    function runPage(reset) {
        syncNext(reset).then(function (data) {
            if (!data.success && data.done) {
                alert(data.message || 'Sync failed.');
                progress.style.display = 'none';
                btn.disabled = false;
                btn.innerHTML = '<i class="ri-refresh-line"></i> Sync Reverb link map';
                return;
            }

            setProgress(data.page || page, data.total_page || null, data.total_upserted || 0, data.message, data.total_count || null);

            if (data.done) {
                bar.classList.remove('progress-bar-animated');
                bar.style.width = '100%';
                bar.textContent = '100%';
                pctEl.textContent = '100%';
                statusEl.textContent = data.message || 'Done';
                setTimeout(function () { location.reload(); }, 800);
                return;
            }

            page = (data.page || page) + 1;
            setTimeout(function () { runPage(false); }, 500);
        }).catch(function () {
            alert('Request failed.');
            progress.style.display = 'none';
            btn.disabled = false;
            btn.innerHTML = '<i class="ri-refresh-line"></i> Sync Reverb link map';
        });
    }

    runPage(true);
});
</script>
@endsection
