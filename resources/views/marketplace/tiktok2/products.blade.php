@extends('layouts.vertical', ['title' => $title ?? 'TikTok 2 — Listings', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.manager.show', 'tiktok2') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> TikTok 2 Manager</a>
        @include('marketplace._page-heading', ['slug' => 'tiktok2', 'heading' => 'TikTok 2 Listings'])
        <p class="text-muted mb-3">
            Seller Center <strong>Active</strong> counts <strong>products</strong> (a combined listing is 1). This page counts <strong>Shopify SKUs</strong>.
            Linked here ≈ Inv SKU Match + Inv SKU Mismatch + Zero on Shopify (sold-out SKUs are still Active in Seller Center).
            <strong>Inv SKU Match / Inv SKU Mismatch</strong> = Shopify vs TikTok 2 quantity (same qty, or gap at most max(3 units, 3% of Shopify)).
            <strong>Active SKU / Inactive SKU</strong> = actual TikTok 2 seller portal status (not inventory match).
            App has {{ $counts['tiktok_products'] ?? 0 }} TikTok products / {{ $counts['tiktok_skus'] ?? 0 }} linked SKUs.
            <em>Refresh live</em> warms the listings cache. Refresh Shopify from <a href="{{ route('marketplace.manager.index') }}">Marketplace Manager</a>.
        </p>

        @if(!empty($shopifyCatalogSyncedAt))
            <p class="small text-muted mb-2">Shopify catalog last synced: {{ $shopifyCatalogSyncedAt }}</p>
        @endif

        @include('marketplace._queue-status', ['slug' => 'tiktok2'])

        @include('marketplace.tiktok2._nav', ['active' => 'products'])

        @if($apiError)
            <div class="alert alert-warning">{{ $apiError }}</div>
        @endif

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="badge bg-primary">
                    @if(($linkTab ?? '') === 'all')
                        {{ $products->total() }} Shopify live SKU(s)
                    @elseif(($linkTab ?? '') === 'unlinked')
                        {{ $products->total() }} not on TikTok (in-stock Shopify)
                    @elseif(($linkTab ?? '') === 'matched')
                        {{ $products->total() }} Inv SKU Match
                    @elseif(($linkTab ?? '') === 'mismatch')
                        {{ $products->total() }} Inv SKU Mismatch
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
                    @if(in_array(($linkTab ?? ''), ['all', 'matched', 'matched_inactive', 'mismatch', 'mismatch_inactive', 'zero', 'unlinked'], true))
                        <a href="{{ request()->fullUrlWithQuery(['refresh_live' => 1, 'clear_cache' => null]) }}" class="btn btn-sm btn-outline-success">
                            <i class="ri-flashlight-line"></i> Refresh live
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['clear_cache' => 1, 'refresh_live' => null]) }}" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Clear the warm TikTok 2 live listings cache?');">
                            <i class="ri-delete-bin-line"></i> Clear cache
                        </a>
                    @endif
                    @if(($linkTab ?? '') === 'mismatch')
                        <button type="button" class="btn btn-sm btn-warning" id="btn-sync-mismatch-now" data-scope="mismatch">
                            <i class="ri-upload-2-line"></i> Sync Mismatch inventory now
                        </button>
                    @endif
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-refresh-api" title="Auto: quick (changed) most times; full catalog when empty or older than 7 days. Also runs hourly.">
                        <i class="ri-refresh-line"></i> Sync TikTok 2 link map
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
                    $counts = $counts ?? ['all' => 0, 'matched' => 0, 'matched_inactive' => 0, 'mismatch' => 0, 'mismatch_inactive' => 0, 'zero' => 0, 'unlinked' => 0, 'linked' => 0, 'tiktok_products' => 0, 'tiktok_skus' => 0];
                    $qName = urlencode($searchName ?? '');
                    $qSku = urlencode($searchSku ?? '');
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
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary btn-sm">Search</button>
                            <a href="{{ request()->url() }}?link={{ urlencode($linkTab ?? 'all') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
                        </div>
                    </div>
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
                        <a href="{{ request()->url() }}?link=mismatch_inactive&search_name={{ $qName }}&search_sku={{ $qSku }}" class="nav-link {{ ($linkTab ?? '') === 'mismatch_inactive' ? 'active' : '' }}">Active SKU {{ $counts['mismatch_inactive'] ?? 0 }}</a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ request()->url() }}?link=matched_inactive&search_name={{ $qName }}&search_sku={{ $qSku }}" class="nav-link {{ ($linkTab ?? '') === 'matched_inactive' ? 'active' : '' }}">Inactive SKU {{ $counts['matched_inactive'] ?? 0 }}</a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ request()->url() }}?link=zero&search_name={{ $qName }}&search_sku={{ $qSku }}" class="nav-link {{ ($linkTab ?? '') === 'zero' ? 'active' : '' }}">Zero on Shopify {{ $counts['zero'] ?? 0 }}</a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ request()->url() }}?link=unlinked&search_name={{ $qName }}&search_sku={{ $qSku }}" class="nav-link {{ ($linkTab ?? '') === 'unlinked' ? 'active' : '' }}">Not on TikTok 2 {{ $counts['unlinked'] ?? 0 }}</a>
                    </li>
                </ul>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0 table-sm">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 64px;">Image</th>
                                <th>SKU</th>
                                <th>Title (Shopify)</th>
                                <th>TikTok Product ID</th>
                                <th>SKU ID</th>
                                <th>State</th>
                                <th>Inactive Reason</th>
                                <th>Shopify Qty</th>
                                <th>TikTok Qty</th>
                                <th>Shopify Price</th>
                                <th>TikTok Price</th>
                                <th>Link</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($products as $p)
                                @php $detailUrl = !empty($p->shopify_sku_id) ? route('marketplace.products.show', ['marketplace' => 'tiktok2', 'shopifySku' => $p->shopify_sku_id]) : null; @endphp
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
                                    <td>{{ \Illuminate\Support\Str::limit($p->title ?? '—', 50) }}</td>
                                    <td class="small">{{ $p->product_id ?? '—' }}</td>
                                    <td class="small">{{ $p->sku_id ?? '—' }}</td>
                                    <td class="small">
                                        @php $stVal = $p->mp_state ?? $p->tiktok_state ?? $p->temu_state ?? null; @endphp
                                        @if(!empty($stVal))
                                            @php $st = strtolower((string)$stVal); @endphp
                                            <span class="badge {{ $st === 'active' ? 'bg-success-subtle text-success' : ($st === 'inactive' ? 'bg-warning-subtle text-warning' : 'bg-light text-muted') }}">{{ $stVal }}</span>
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
                                        @if($p->linked)
                                            <span class="badge bg-success-subtle text-success">Linked</span>
                                        @else
                                            <span class="badge bg-light text-muted">Not linked</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="text-center text-muted py-4">
                                        No rows for this tab.
                                        @if($connected)
                                            Click <strong>Sync TikTok 2 link map</strong> if products are empty, or refresh Shopify.
                                        @else
                                            <a href="{{ route('marketplace.manager.tiktok2.connect') }}">Connect TikTok 2</a> first.
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
    var url = @json(route('marketplace.manager.tiktok2.refresh'));
    var page = 1;
    var pageToken = '';

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
        var extra = totalCount ? ' (' + totalCount + ' products on TikTok 2)' : '';
        countsEl.textContent = totalUpserted + ' SKU link(s) saved so far' + extra;
    }

    function syncNext(reset) {
        var ctrl = (typeof AbortController !== 'undefined') ? new AbortController() : null;
        var timer = ctrl ? setTimeout(function () { ctrl.abort(); }, 70000) : null;
        return fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ page: page, reset: !!reset, mode: 'auto', page_token: pageToken }),
            signal: ctrl ? ctrl.signal : undefined,
        }).then(function (r) { return r.json(); }).finally(function () {
            if (timer) clearTimeout(timer);
        });
    }

    if (!confirm('Sync TikTok 2 link map automatically?\n\n• Quick = only listings changed since last sync\n• Full = whole catalog (when empty / never synced / older than 7 days)\n\nAlso runs hourly in the background.')) {
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="ri-loader-4-line"></i> Syncing…';
    progress.style.display = '';
    bar.classList.add('progress-bar-animated');
    setProgress(0, null, 0, 'Starting sync…');

    function runPage(reset) {
        if (reset) {
            page = 1;
            pageToken = '';
        }
        syncNext(reset).then(function (data) {
            if (!data.success && data.done) {
                alert(data.message || 'Sync failed.');
                progress.style.display = 'none';
                btn.disabled = false;
                btn.innerHTML = '<i class="ri-refresh-line"></i> Sync TikTok 2 link map';
                return;
            }

            var modeNote = data.mode ? (' [' + data.mode + ']') : '';
            setProgress(data.page || page, data.total_page || null, data.total_upserted || 0, (data.message || '') + modeNote, data.total_count || null);

            if (data.done) {
                bar.classList.remove('progress-bar-animated');
                bar.style.width = '100%';
                bar.textContent = '100%';
                pctEl.textContent = '100%';
                statusEl.textContent = (data.message || 'Done') + modeNote;
                setTimeout(function () { location.reload(); }, 800);
                return;
            }

            pageToken = data.next_page_token || '';
            page = (data.page || page) + 1;
            setTimeout(function () { runPage(false); }, 500);
        }).catch(function (err) {
            var aborted = err && (err.name === 'AbortError' || /abort/i.test(String(err)));
            alert(aborted
                ? 'TikTok API timed out on this page. Deploy latest code, clear stuck sync on server, then retry.'
                : 'Request failed.');
            progress.style.display = 'none';
            btn.disabled = false;
            btn.innerHTML = '<i class="ri-refresh-line"></i> Sync TikTok 2 link map';
        });
    }

    runPage(true);
});

document.getElementById('btn-sync-mismatch-now')?.addEventListener('click', function () {
    var btn = this;
    var scope = btn.getAttribute('data-scope') || 'mismatch';
    if (!confirm('Sync Inv SKU Mismatch SKUs from live Shopify → TikTok 2 right now (batched, no queue)?')) {
        return;
    }
    btn.disabled = true;
    var original = btn.innerHTML;
    var url = @json(route('marketplace.manager.tiktok2.sync.mismatch.inventory'));
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
                alert(
                    'Mismatch inventory sync complete.\n'
                    + 'Updated: ' + totals.updated
                    + '\nFailed: ' + totals.failed
                    + '\nSkipped: ' + totals.skipped
                    + (data.message ? ('\n\nLast batch: ' + data.message) : '')
                );
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
