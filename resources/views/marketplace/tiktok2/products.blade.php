@extends('layouts.vertical', ['title' => $title ?? 'TikTok 2 — Listings', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.manager.show', 'tiktok2') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> TikTok 2 Manager</a>
        @include('marketplace._page-heading', ['slug' => 'tiktok2', 'heading' => 'TikTok 2 Listings'])
        <p class="text-muted mb-3">
            <strong>All</strong> = every Shopify live SKU.
            <strong>Active SKU</strong> = qty matched on TikTok.
            <strong>Active SKU Mismatch</strong> = qty differs.
            <strong>Zero on Shopify</strong> / <strong>Not on TikTok 2</strong> = unlinked or zero stock.
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
                        {{ $products->total() }} Active SKU
                    @elseif(($linkTab ?? '') === 'mismatch')
                        {{ $products->total() }} Active SKU Mismatch
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
                    @if(in_array(($linkTab ?? ''), ['mismatch', 'mismatch_inactive'], true))
                        <button type="button" class="btn btn-sm btn-warning" id="btn-sync-mismatch-now" data-scope="{{ $linkTab }}">
                            <i class="ri-upload-2-line"></i> Sync Mismatch inventory now
                        </button>
                    @endif
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-refresh-api">
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
                    $counts = $counts ?? ['all' => 0, 'matched' => 0, 'matched_inactive' => 0, 'mismatch' => 0, 'mismatch_inactive' => 0, 'zero' => 0, 'unlinked' => 0, 'linked' => 0];
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
                        <a href="{{ request()->url() }}?link=matched&search_name={{ $qName }}&search_sku={{ $qSku }}" class="nav-link {{ ($linkTab ?? '') === 'matched' ? 'active' : '' }}">Active SKU {{ $counts['matched'] ?? 0 }}</a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ request()->url() }}?link=mismatch&search_name={{ $qName }}&search_sku={{ $qSku }}" class="nav-link {{ ($linkTab ?? '') === 'mismatch' ? 'active' : '' }}">Active SKU Mismatch {{ $counts['mismatch'] ?? 0 }}</a>
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
    var statusUrl = @json(route('marketplace.manager.tiktok2.refresh.status'));
    var pollTimer = null;
    var ticks = 0;

    function resetBtn() {
        btn.disabled = false;
        btn.innerHTML = '<i class="ri-refresh-line"></i> Sync TikTok 2 link map';
    }

    function applyProgress(p, forcePct) {
        var status = (p && p.status) ? p.status : 'running';
        var msg = (p && p.message) || 'Syncing…';
        var count = (p && p.count) || 0;
        var queuedJobs = (p && p.queued_jobs) || 0;
        var pct;
        if (typeof forcePct === 'number') {
            pct = forcePct;
        } else if (status === 'done') {
            pct = 100;
        } else if (status === 'queued') {
            pct = Math.min(40, 15 + ticks);
        } else {
            pct = Math.min(95, 25 + ticks * 2);
        }
        bar.style.width = pct + '%';
        bar.textContent = pct + '%';
        pctEl.textContent = pct + '%';
        statusEl.textContent = msg;
        var extra = count ? (count + ' SKU link(s) in DB') : '';
        if (queuedJobs > 0) {
            extra += (extra ? ' · ' : '') + queuedJobs + ' listings-queue job(s)';
        }
        if (p && p.queue) {
            extra += (extra ? ' · ' : '') + 'queue: ' + p.queue;
        }
        countsEl.textContent = extra;
    }

    function finishOk(p) {
        if (pollTimer) clearInterval(pollTimer);
        applyProgress(p || {}, 100);
        bar.classList.remove('progress-bar-animated');
        setTimeout(function () { location.reload(); }, 800);
    }

    function finishFail(msg) {
        if (pollTimer) clearInterval(pollTimer);
        alert(msg || 'Sync failed.');
        progress.style.display = 'none';
        resetBtn();
    }

    function poll() {
        ticks++;
        if (ticks > 600) {
            finishFail('Listing sync is still running/queued after a long time. Check mm-tiktok2-listings worker and logs.');
            return;
        }
        fetch(statusUrl, { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var p = data.progress || {};
                var status = p.status || 'idle';
                applyProgress(p);
                if (status === 'done' || data.done) {
                    finishOk(p);
                    return;
                }
                if (status === 'failed' || data.failed) {
                    finishFail(p.message || 'Sync failed.');
                    return;
                }
                if (status === 'idle' && ticks >= 4 && !(p.queued_jobs > 0)) {
                    finishFail('Listing sync is not running (progress was cleared or the job never started). Click Sync again. Ensure mm-tiktok2-listings worker is up.');
                }
            })
            .catch(function () { /* keep polling */ });
    }

    if (!confirm('Sync all TikTok 2 listings and refresh SKU ↔ product_id / sku_id mappings? This may take a few minutes.')) {
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="ri-loader-4-line"></i> Syncing…';
    progress.style.display = '';
    bar.classList.add('progress-bar-animated');
    applyProgress({ status: 'queued', message: 'Queueing TikTok 2 listing sync…', count: 0 }, 10);

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
            finishFail(data.message || 'Sync failed.');
            return;
        }
        if (data.done) {
            finishOk(data.progress || { status: 'done', message: data.message, count: data.count || 0 });
            return;
        }
        applyProgress(data.progress || { status: 'queued', message: data.message }, 20);
        pollTimer = setInterval(poll, 2500);
        poll();
    }).catch(function () {
        finishFail('Could not start listing sync. Is the mm-tiktok2 queue worker running?');
    });
});

document.getElementById('btn-sync-mismatch-now')?.addEventListener('click', function () {
    var btn = this;
    var scope = btn.getAttribute('data-scope') || 'mismatch';
    if (!confirm('Sync Mismatch SKUs from live Shopify → TikTok 2 right now (batched, no queue)?')) {
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
