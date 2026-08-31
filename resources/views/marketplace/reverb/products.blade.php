@extends('layouts.vertical', ['title' => $title ?? 'Reverb — Listings', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.manager.show', 'reverb') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> Reverb Manager</a>
        @include('marketplace._page-heading', ['slug' => 'reverb', 'heading' => 'Reverb Listings'])
        <p class="text-muted mb-3">
            <strong>All</strong> = every Shopify live SKU.
            <strong>Inv SKU Match / Linked mismatch SKU</strong> = Shopify vs Reverb quantity. Shopify qty must not be less than marketplace qty. Match allows marketplace to be short by at most max(3 units, 3% of Shopify).
            <strong>Active SKU / Inactive SKU</strong> = actual Reverb seller portal status (not inventory match).
            <em>Refresh live</em> warms Reverb states. Refresh Shopify from <a href="{{ route('marketplace.manager.index') }}">Marketplace Manager</a>.
        </p>

        @if(!empty($liveQueued))
            <div class="alert alert-info py-2">Queued {{ (int) $liveQueued }} SKU(s) for live inventory sync (Shopify → marketplace).</div>
        @endif

        @if(isset($shopifyCatalogReady) && empty($shopifyCatalogReady))
            <div class="alert alert-warning py-2">Shared Shopify live catalog is empty. In <a href="{{ route('marketplace.manager.index') }}">Marketplace Manager</a> click <em>Refresh Shopify</em>, wait, then reload.</div>
        @elseif(!empty($shopifyCatalogSyncedAt))
            <p class="small text-muted mb-2">Shopify catalog last synced: {{ $shopifyCatalogSyncedAt }}</p>
        @endif

        @include('marketplace._queue-status', ['slug' => 'reverb'])

        @include('marketplace.reverb._nav', ['active' => 'products'])

        @if($apiError)
            <div class="alert alert-warning">{{ $apiError }}</div>
        @endif

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="badge bg-primary">
                    @if(($linkTab ?? '') === 'all')
                        {{ $products->total() }} Shopify live SKU(s)
                    @elseif(($linkTab ?? '') === 'unlinked')
                        {{ $products->total() }} not on Reverb (in-stock Shopify)
                    @elseif(($linkTab ?? '') === 'matched')
                        {{ $products->total() }} Inv SKU Match
                    @elseif(($linkTab ?? '') === 'mismatch')
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
                    @if(in_array(($linkTab ?? ''), ['all', 'matched', 'matched_inactive', 'mismatch', 'mismatch_inactive', 'zero', 'unlinked'], true))
                        <a href="{{ request()->fullUrlWithQuery(['refresh_live' => 1, 'clear_cache' => null]) }}" class="btn btn-sm btn-outline-success">
                            <i class="ri-flashlight-line"></i> Refresh live
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['clear_cache' => 1, 'refresh_live' => null]) }}" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Clear the warm Reverb live listings cache? Counts will refresh after Refresh live.');">
                            <i class="ri-delete-bin-line"></i> Clear cache
                        </a>
                    @endif
                    @if(($linkTab ?? '') === 'mismatch')
                        <button type="button" class="btn btn-sm btn-warning" id="btn-sync-mismatch-now" data-scope="mismatch">
                            <i class="ri-upload-2-line"></i> Sync actual Shopify quantity
                        </button>
                    @endif
                    @include('marketplace._listings-fetch-new')
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
                    $counts = $counts ?? ['all' => 0, 'matched' => 0, 'matched_inactive' => 0, 'mismatch' => 0, 'mismatch_inactive' => 0, 'zero' => 0, 'unlinked' => 0, 'linked' => 0];
                    $stateCounts = $stateCounts ?? ['all' => 0, 'live' => 0, 'sold' => 0, 'out_of_stock' => 0, 'ended' => 0, 'draft' => 0, 'other' => 0];
                    $stateTab = $stateTab ?? 'all';
                    $qName = urlencode($searchName ?? '');
                    $qSku = urlencode($searchSku ?? '');
                    $isLinkedTab = in_array(($linkTab ?? ''), ['matched', 'matched_inactive', 'mismatch', 'mismatch_inactive', 'zero'], true);
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
                    @if(in_array(($linkTab ?? ''), ['matched_inactive', 'mismatch_inactive'], true) && empty($stateCacheReady) && (int) ($counts[$linkTab] ?? 0) === 0)
                        <p class="small text-muted mt-2 mb-0">Active / Inactive counts need the live Reverb catalog — click <em>Refresh live</em>, wait a minute, then reload.</p>
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
                        <a href="{{ request()->url() }}?link=mismatch&search_name={{ $qName }}&search_sku={{ $qSku }}" class="nav-link {{ ($linkTab ?? '') === 'mismatch' ? 'active' : '' }}">Linked mismatch SKU {{ $counts['mismatch'] ?? 0 }}</a>
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
                        <a href="{{ request()->url() }}?link=unlinked&search_name={{ $qName }}&search_sku={{ $qSku }}" class="nav-link {{ ($linkTab ?? '') === 'unlinked' ? 'active' : '' }}">Not on Reverb {{ $counts['unlinked'] ?? 0 }}</a>
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
                                <th>Inactive Reason</th>
                                <th>Shopify Qty</th>
                                <th>Reverb Qty</th>
                                <th>Shopify Price</th>
                                <th>Reverb Price</th>
                                <th>Link</th>
                                <th class="text-center" style="width: 72px;" title="View">
                                    <i class="ri-search-line"></i><br><span class="small">View</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($products as $p)
                                @php
                                    $detailUrl = !empty($p->shopify_sku_id) ? route('marketplace.products.show', ['marketplace' => 'reverb', 'shopifySku' => $p->shopify_sku_id]) : null;
                                    $canViewListing = !empty($p->linked) && !empty($p->shopify_sku_id) && !empty($p->product_id);
                                    $listingNeedsAttention = $canViewListing && !empty($p->listing_incomplete);
                                @endphp
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
                                    <td class="small">{{ !empty($p->inactive_reason) ? $p->inactive_reason : '—' }}</td>
                                    <td>{{ $p->shopify_quantity !== null ? $p->shopify_quantity : '—' }}</td>
                                    <td>{{ ($p->rv_quantity ?? $p->quantity) !== null ? ($p->rv_quantity ?? $p->quantity) : '—' }}</td>
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
                                    <td class="text-center" onclick="event.stopPropagation();">
                                        @if($canViewListing)
                                            <button type="button"
                                                class="btn btn-sm btn-outline-secondary rv-view-listing-btn d-inline-flex align-items-center gap-1"
                                                title="{{ $listingNeedsAttention ? ('View — '.((int) ($p->listing_issue_count ?? 0)).' field(s) missing/incomplete') : 'View' }}"
                                                data-shopify-sku-id="{{ (int) $p->shopify_sku_id }}"
                                                data-sku="{{ e($p->sku) }}">
                                                <i class="ri-search-line"></i>
                                                @if($listingNeedsAttention)
                                                    <span class="text-danger fw-bold" style="font-size: 0.95rem; line-height: 1;" title="Listing has missing or incomplete data">▲</span>
                                                @endif
                                            </button>
                                        @else
                                            <button type="button" class="btn btn-sm btn-light text-muted" disabled title="Link SKU on Reverb first">
                                                <i class="ri-search-line"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="text-center text-muted py-4">
                                        @if(($linkTab ?? 'all') === 'not_in_shopify')
                                            No live Reverb listings found without a matching Shopify SKU.
                                        @else
                                            No live-verified active Shopify SKUs found. Click Refresh live to sync from Shopify.
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

@include('marketplace.reverb._listing-edit-modal')

<script>
(function () {
    var csrf = '{{ csrf_token() }}';
    var editorBase = @json(url('/marketplace/reverb/products'));
    var modalEl = document.getElementById('reverbListingEditModal');
    var currentShopifySkuId = null;
    var listingState = {};

    function getListingModal() {
        if (!modalEl || !window.bootstrap || !bootstrap.Modal) return null;
        return bootstrap.Modal.getOrCreateInstance(modalEl);
    }

    function status(msg, isError) {
        var el = document.getElementById('rvEditorStatus');
        if (!el) return;
        el.textContent = msg || '';
        el.classList.toggle('text-danger', !!isError);
        el.classList.toggle('text-success', !isError && /success|pulled|updated|loaded/i.test(msg || ''));
        el.classList.toggle('text-muted', !isError && !/success|pulled|updated|loaded/i.test(msg || ''));
    }

    function collectPhotos() {
        return Array.prototype.map.call(document.querySelectorAll('#rvPhotoInputs input[data-photo]'), function (inp) {
            return (inp.value || '').trim();
        }).filter(Boolean);
    }

    function collectVideos() {
        return Array.prototype.map.call(document.querySelectorAll('#rvVideoInputs input[data-video]'), function (inp) {
            return (inp.value || '').trim();
        }).filter(Boolean);
    }

    function renderPhotoGrid(urls) {
        var grid = document.getElementById('rvPhotoGrid');
        var inputs = document.getElementById('rvPhotoInputs');
        if (!grid || !inputs) return;
        grid.innerHTML = '';
        inputs.innerHTML = '';
        (urls || []).forEach(function (url, idx) {
            var card = document.createElement('div');
            card.className = 'rv-photo-card';
            card.innerHTML = '<img src="' + url.replace(/"/g, '&quot;') + '" alt=""><button type="button" class="btn btn-sm btn-danger btn-remove" data-idx="' + idx + '">&times;</button>';
            grid.appendChild(card);
            var inp = document.createElement('input');
            inp.type = 'url';
            inp.className = 'form-control form-control-sm';
            inp.setAttribute('data-photo', '1');
            inp.value = url;
            inp.addEventListener('input', function () {
                syncPhotosFromInputs();
                clientValidate();
            });
            inputs.appendChild(inp);
        });
        grid.querySelectorAll('.btn-remove').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var i = parseInt(btn.getAttribute('data-idx'), 10);
                var urls2 = collectPhotos();
                urls2.splice(i, 1);
                renderPhotoGrid(urls2);
                clientValidate();
            });
        });
    }

    function syncPhotosFromInputs() {
        renderPhotoGrid(collectPhotos());
    }

    function renderVideos(urls) {
        var wrap = document.getElementById('rvVideoInputs');
        if (!wrap) return;
        wrap.innerHTML = '';
        (urls || []).forEach(function (url) {
            var inp = document.createElement('input');
            inp.type = 'url';
            inp.className = 'form-control form-control-sm';
            inp.setAttribute('data-video', '1');
            inp.value = url;
            inp.addEventListener('input', clientValidate);
            wrap.appendChild(inp);
        });
    }

    function readFormListing() {
        var bulletsRaw = (document.getElementById('rv_bullets').value || '').trim();
        var bullets = bulletsRaw ? bulletsRaw.split(/\r?\n/).map(function (s) { return s.trim(); }).filter(Boolean) : [];
        var ratesJson = (document.getElementById('rv_shipping_rates_json').value || '').trim();
        var shippingRates = [];
        if (ratesJson) {
            try { shippingRates = JSON.parse(ratesJson); } catch (e) { shippingRates = []; }
        }
        var shipping = null;
        var profileId = (document.getElementById('rv_shipping_profile_id').value || '').trim();
        if (!profileId && Array.isArray(shippingRates) && shippingRates.length) {
            shipping = {
                local: !!document.getElementById('rv_local_pickup_only').checked,
                rates: shippingRates.map(function (r) {
                    return {
                        region_code: r.region_code || 'US_CON',
                        rate: { amount: String(r.amount || '0'), currency: r.currency || 'USD' }
                    };
                })
            };
        }
        var description = document.getElementById('rv_description').value || '';
        if (bullets.length) {
            var features = '<div class="highlighted-features">\n';
            bullets.forEach(function (b) {
                features += '<p>' + b.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '<br></p>\n';
            });
            features += '</div>';
            if (description.indexOf('highlighted-features') === -1) {
                description = features + '\n' + description;
            }
        }
        return {
            listing_id: listingState.listing_id || null,
            sku: document.getElementById('rv_sku_field').value || '',
            title: document.getElementById('rv_title').value || '',
            make: document.getElementById('rv_make').value || '',
            model: document.getElementById('rv_model').value || '',
            finish: document.getElementById('rv_finish').value || '',
            year: document.getElementById('rv_year').value || '',
            condition_uuid: document.getElementById('rv_condition_uuid').value || '',
            condition_name: document.getElementById('rv_condition_name').value || '',
            category_uuid: document.getElementById('rv_category_uuid').value || '',
            category_name: document.getElementById('rv_category_name').value || '',
            upc: document.getElementById('rv_upc').value || '',
            upc_does_not_apply: !!document.getElementById('rv_upc_does_not_apply').checked,
            handmade: !!document.getElementById('rv_handmade').checked,
            offers_enabled: !!document.getElementById('rv_offers_enabled').checked,
            local_pickup_only: !!document.getElementById('rv_local_pickup_only').checked,
            price_amount: document.getElementById('rv_price_amount').value,
            price_currency: document.getElementById('rv_price_currency').value || 'USD',
            inventory: document.getElementById('rv_inventory').value,
            has_inventory: !!document.getElementById('rv_has_inventory').checked,
            description: description,
            bullets: bullets,
            photos: collectPhotos(),
            videos: collectVideos(),
            shipping_profile_id: profileId,
            shipping: shipping,
            shipping_rates: shippingRates
        };
    }

    function clientValidate() {
        var listing = readFormListing();
        var issues = [];
        var sections = { media: true, details: true, pricing: true, description: true, shipping: true };
        function add(section, field, message) {
            issues.push({ section: section, field: field, message: message });
            sections[section] = false;
        }
        function requireText(section, field, label, value) {
            if (!String(value || '').trim()) add(section, field, label + ' is blank.');
        }
        requireText('details', 'title', 'Title', listing.title);
        var make = String(listing.make || '').trim();
        var model = String(listing.model || '').trim();
        requireText('details', 'make', 'Make', make);
        requireText('details', 'model', 'Model', model);
        if (make && make.toLowerCase() === 'unknown') add('details', 'make', 'Make cannot be Unknown.');
        if (model && model.toLowerCase() === 'unknown') add('details', 'model', 'Model cannot be Unknown.');
        requireText('details', 'finish', 'Finish', listing.finish);
        requireText('details', 'year', 'Year', listing.year);
        requireText('details', 'sku', 'SKU', listing.sku);
        if (!String(listing.condition_name || '').trim() && !String(listing.condition_uuid || '').trim()) {
            add('details', 'condition', 'Condition is blank.');
        }
        if (!String(listing.category_name || '').trim() && !String(listing.category_uuid || '').trim()) {
            add('details', 'category', 'Category is blank.');
        }
        if (!listing.upc_does_not_apply && !String(listing.upc || '').trim()) {
            add('details', 'upc', 'UPC / EAN is blank (or check UPC does not apply).');
        }
        var price = parseFloat(listing.price_amount);
        if (!(price > 0)) add('pricing', 'price', 'Price is blank or must be greater than 0.');
        requireText('pricing', 'currency', 'Currency', listing.price_currency);
        if (listing.inventory === '' || listing.inventory === null || listing.inventory === undefined) {
            add('pricing', 'inventory', 'Inventory is blank.');
        } else {
            var inv = parseInt(listing.inventory, 10);
            var cond = String(listing.condition_name || '').toLowerCase();
            var multiOk = /brand new|b-stock|b stock|mint|\bnew\b/.test(cond);
            if (inv > 1 && cond && !multiOk) {
                add('pricing', 'inventory', 'Used conditions allow inventory of 1 only.');
            }
        }
        if (listing.photos.length < 11) {
            add('media', 'photos', 'Need at least 11 images (currently ' + listing.photos.length + ').');
        }
        if (listing.photos.length > 25) add('media', 'photos', 'Maximum 25 photos allowed.');
        if (listing.videos.length < 1) add('media', 'videos', 'Need at least 1 video (currently 0).');
        if (listing.videos.length > 3) add('media', 'videos', 'Maximum 3 videos allowed.');
        if (!String(listing.description || '').trim()) add('description', 'description', 'Description is blank.');
        if (!(listing.bullets || []).length) add('description', 'bullets', 'Highlighted features / bullets are blank.');
        if (!listing.local_pickup_only && !String(listing.shipping_profile_id || '').trim() && !(listing.shipping_rates || []).length) {
            add('shipping', 'shipping', 'Shipping is blank (set profile ID, rates, or local pickup only).');
        }
        applyValidation({ ok: !issues.length, issues: issues, sections: sections });
        return { ok: !issues.length, issues: issues, sections: sections };
    }

    function applyValidation(validation) {
        var issues = (validation && validation.issues) ? validation.issues : [];
        var badFields = {};
        issues.forEach(function (issue) {
            badFields[issue.field] = issue.message || 'Invalid';
        });

        document.querySelectorAll('#reverbListingEditModal .rv-section-alert').forEach(function (el) {
            var sec = el.getAttribute('data-section');
            // sections map: true = ok, false = has issues
            var hasIssue = validation && validation.sections && validation.sections[sec] === false;
            el.classList.toggle('rv-alert-on', !!hasIssue);
        });

        document.querySelectorAll('#reverbListingEditModal .rv-field-alert').forEach(function (el) {
            var field = el.getAttribute('data-field');
            el.classList.toggle('rv-alert-on', !!badFields[field]);
            if (badFields[field]) el.setAttribute('title', badFields[field]);
        });

        document.querySelectorAll('#reverbListingEditModal .rv-field-error').forEach(function (el) {
            var field = el.getAttribute('data-field');
            if (badFields[field]) {
                el.textContent = badFields[field];
                el.classList.add('rv-alert-on');
            } else {
                el.textContent = '';
                el.classList.remove('rv-alert-on');
            }
        });

        document.querySelectorAll('#reverbListingEditModal [data-rv-field]').forEach(function (el) {
            var field = el.getAttribute('data-rv-field');
            el.classList.toggle('is-rv-invalid', !!badFields[field]);
        });

        var headerIcon = document.getElementById('rvHeaderAlertIcon');
        var headerBox = document.getElementById('rvHeaderIssues');
        var headerList = document.getElementById('rvHeaderIssuesList');
        var headerTitle = document.getElementById('rvHeaderIssuesTitle');
        if (headerIcon) headerIcon.classList.toggle('rv-alert-on', issues.length > 0);
        if (headerBox) headerBox.classList.toggle('rv-alert-on', issues.length > 0);
        if (headerTitle) {
            headerTitle.textContent = issues.length
                ? (issues.length + ' blank/invalid field' + (issues.length === 1 ? '' : 's') + ' — fill every listing input')
                : 'Missing or invalid Reverb fields';
        }
        if (headerList) {
            headerList.innerHTML = '';
            issues.forEach(function (issue) {
                var li = document.createElement('li');
                li.textContent = (issue.field ? (issue.field + ': ') : '') + (issue.message || '');
                headerList.appendChild(li);
            });
        }
    }

    function bindListing(listing, validation) {
        listingState = listing || {};
        document.getElementById('rvEditorSku').textContent = listing.sku || '—';
        document.getElementById('rvEditorListingId').textContent = listing.listing_id || '—';
        document.getElementById('rvEditorState').textContent = listing.state || '—';
        var link = document.getElementById('rvEditorListingLink');
        if (listing.listing_url) {
            link.href = listing.listing_url;
            link.style.display = '';
        } else {
            link.style.display = 'none';
        }
        document.getElementById('rv_title').value = listing.title || '';
        document.getElementById('rv_make').value = listing.make || '';
        document.getElementById('rv_model').value = listing.model || '';
        document.getElementById('rv_finish').value = listing.finish || '';
        document.getElementById('rv_year').value = listing.year || '';
        document.getElementById('rv_condition_name').value = listing.condition_name || '';
        document.getElementById('rv_condition_uuid').value = listing.condition_uuid || '';
        document.getElementById('rv_category_name').value = listing.category_name || '';
        document.getElementById('rv_category_uuid').value = listing.category_uuid || '';
        document.getElementById('rv_sku_field').value = listing.sku || '';
        document.getElementById('rv_upc').value = listing.upc || '';
        document.getElementById('rv_upc_does_not_apply').checked = !!listing.upc_does_not_apply;
        document.getElementById('rv_handmade').checked = !!listing.handmade;
        document.getElementById('rv_offers_enabled').checked = listing.offers_enabled !== false;
        document.getElementById('rv_local_pickup_only').checked = !!listing.local_pickup_only;
        document.getElementById('rv_price_amount').value = listing.price_amount != null ? listing.price_amount : '';
        document.getElementById('rv_price_currency').value = listing.price_currency || 'USD';
        document.getElementById('rv_inventory').value = listing.inventory != null ? listing.inventory : '';
        document.getElementById('rv_has_inventory').checked = listing.has_inventory !== false;
        document.getElementById('rv_description').value = listing.description || '';
        document.getElementById('rv_bullets').value = (listing.bullets || []).join('\n');
        document.getElementById('rv_shipping_profile_id').value = listing.shipping_profile_id || '';
        document.getElementById('rv_shipping_rates_json').value = listing.shipping_rates && listing.shipping_rates.length
            ? JSON.stringify(listing.shipping_rates, null, 2)
            : '';
        renderPhotoGrid(listing.photos || []);
        renderVideos(listing.videos || []);
        // Always re-run client validation so triangles stay in sync with the form.
        var live = clientValidate();
        if (validation && validation.issues && validation.issues.length && (!live.issues || !live.issues.length)) {
            applyValidation(validation);
        }
    }

    function applyPartial(partial, missingOnly) {
        if (!partial) return;
        function setIf(id, val, force) {
            var el = document.getElementById(id);
            if (!el || val == null) return;
            if (missingOnly && String(el.value || '').trim() !== '') return;
            el.value = val;
        }
        setIf('rv_title', partial.title, !missingOnly);
        setIf('rv_make', partial.make, !missingOnly);
        setIf('rv_model', partial.model, !missingOnly);
        setIf('rv_finish', partial.finish, !missingOnly);
        setIf('rv_year', partial.year, !missingOnly);
        setIf('rv_condition_name', partial.condition_name, !missingOnly);
        setIf('rv_category_name', partial.category_name, !missingOnly);
        setIf('rv_sku_field', partial.sku, !missingOnly);
        setIf('rv_upc', partial.upc, !missingOnly);
        setIf('rv_shipping_profile_id', partial.shipping_profile_id, !missingOnly);
        if (partial.price_amount != null) {
            var priceEl = document.getElementById('rv_price_amount');
            if (priceEl && (!missingOnly || !(parseFloat(priceEl.value) > 0))) {
                priceEl.value = partial.price_amount;
            }
        }
        if (partial.price_currency != null) {
            setIf('rv_price_currency', partial.price_currency, !missingOnly);
        }
        if (partial.description != null) {
            var descEl = document.getElementById('rv_description');
            if (descEl && (!missingOnly || !String(descEl.value || '').trim())) {
                descEl.value = partial.description;
            }
        }
        // Highlighted features always use Bullet Points Master data when provided.
        if (Array.isArray(partial.bullets)) {
            var bulletsEl = document.getElementById('rv_bullets');
            if (bulletsEl) {
                bulletsEl.value = partial.bullets.join('\n');
            }
        }
        if (Array.isArray(partial.photos)) {
            if (!missingOnly || collectPhotos().length < 11) {
                renderPhotoGrid(missingOnly ? Array.from(new Set(collectPhotos().concat(partial.photos))).slice(0, 25) : partial.photos);
            }
        }
        if (Array.isArray(partial.videos)) {
            if (!missingOnly || collectVideos().length < 1) {
                renderVideos(partial.videos);
            }
        }
        if (partial.highlighted_features_html && !(document.getElementById('rv_description').value || '').includes('highlighted-features')) {
            document.getElementById('rv_description').value =
                partial.highlighted_features_html + '\n' + (document.getElementById('rv_description').value || '');
        }
        clientValidate();
    }

    function editorUrl(suffix) {
        return editorBase + '/' + currentShopifySkuId + '/listing-editor' + (suffix || '');
    }

    function loadEditor() {
        status('Loading listing from Reverb…');
        return fetch(editorUrl(), {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (!data.success) {
                status(data.message || 'Failed to load listing.', true);
                return;
            }
            bindListing(data.listing || {}, data.validation);
            status(data.message || 'Listing loaded.');
        }).catch(function () {
            status('Request failed.', true);
        });
    }

    function openEditor(shopifySkuId, sku) {
        currentShopifySkuId = shopifySkuId;
        document.getElementById('rv_shopify_sku_id').value = shopifySkuId;
        document.getElementById('rvEditorSku').textContent = sku || '—';
        var modal = getListingModal();
        if (modal) {
            modal.show();
        } else if (modalEl && window.jQuery) {
            window.jQuery(modalEl).modal('show');
        }
        loadEditor();
    }

    document.querySelectorAll('.rv-view-listing-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            openEditor(btn.getAttribute('data-shopify-sku-id'), btn.getAttribute('data-sku'));
        });
    });

    document.getElementById('rvAddPhoto')?.addEventListener('click', function () {
        var urls = collectPhotos();
        urls.push('');
        renderPhotoGrid(urls);
        var inputs = document.querySelectorAll('#rvPhotoInputs input[data-photo]');
        if (inputs.length) inputs[inputs.length - 1].focus();
    });

    document.getElementById('rvAddVideo')?.addEventListener('click', function () {
        var urls = collectVideos();
        if (urls.length >= 3) {
            status('Maximum 3 videos allowed.', true);
            return;
        }
        urls.push('');
        renderVideos(urls);
    });

    document.getElementById('rvBtnPull')?.addEventListener('click', function () {
        if (!currentShopifySkuId) return;
        var btn = this;
        btn.disabled = true;
        status('Pulling from Reverb…');
        fetch(editorUrl('/pull'), {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: '{}'
        }).then(function (r) { return r.json(); }).then(function (data) {
            btn.disabled = false;
            if (!data.success) {
                status(data.message || 'Pull failed.', true);
                return;
            }
            bindListing(data.listing || {}, data.validation);
            status(data.message || 'Pulled from Reverb.');
        }).catch(function () {
            btn.disabled = false;
            status('Pull request failed.', true);
        });
    });

    document.getElementById('rvBtnPush')?.addEventListener('click', function () {
        if (!currentShopifySkuId) return;
        var validation = clientValidate();
        if (!validation.ok) {
            status('Fix red-triangle fields before pushing to Reverb.', true);
            return;
        }
        if (!confirm('Push this listing to Reverb now?')) return;
        var btn = this;
        btn.disabled = true;
        status('Pushing to Reverb…');
        fetch(editorUrl('/push'), {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ listing: readFormListing() })
        }).then(function (r) { return r.json(); }).then(function (data) {
            btn.disabled = false;
            if (data.validation) applyValidation(data.validation);
            if (!data.success) {
                status(data.message || 'Push failed.', true);
                return;
            }
            status(data.message || 'Pushed to Reverb.');
        }).catch(function () {
            btn.disabled = false;
            status('Push request failed.', true);
        });
    });

    function pullProductMaster(section) {
        if (!currentShopifySkuId) return;
        status('Loading Product Master (' + section + ')…');
        fetch(editorUrl('/product-master?section=' + encodeURIComponent(section)), {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (!data.success) {
                status(data.message || 'Product Master pull failed.', true);
                return;
            }
            applyPartial(data.partial || {});
            status(data.message || 'Product Master data applied to form.');
        }).catch(function () {
            status('Product Master request failed.', true);
        });
    }

    document.getElementById('rvBtnPullPm')?.addEventListener('click', function () {
        pullProductMaster(this.getAttribute('data-section') || 'full');
    });
    document.querySelectorAll('.rv-pm-section').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            var section = a.getAttribute('data-section') || 'full';
            document.getElementById('rvBtnPullPm').setAttribute('data-section', section);
            pullProductMaster(section);
        });
    });

    document.getElementById('rvBtnAutopopulateMissing')?.addEventListener('click', function () {
        if (!currentShopifySkuId) return;
        var btn = this;
        btn.disabled = true;
        status('Autopopulating missing fields from Product Master…');
        fetch(editorUrl('/autopopulate-missing'), {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ listing: readFormListing() })
        }).then(function (r) { return r.json(); }).then(function (data) {
            btn.disabled = false;
            if (!data.success) {
                status(data.message || 'Autopopulate failed.', true);
                if (data.master_url && confirm((data.message || 'Master data missing.') + '\n\nOpen Reverb Listing Master?')) {
                    window.open(data.master_url, '_blank');
                }
                return;
            }
            applyPartial(data.partial || {}, true);
            var msg = data.message || 'Autopopulated missing fields.';
            if (data.hint) msg += ' ' + data.hint;
            status(msg, !!(data.still_missing && data.still_missing.length));
            if (data.still_missing && data.still_missing.length && data.master_url) {
                if (confirm(msg + '\n\nOpen Reverb Listing Master to fill remaining fields?')) {
                    window.open(data.master_url, '_blank');
                }
            }
        }).catch(function () {
            btn.disabled = false;
            status('Autopopulate request failed.', true);
        });
    });

    [
        'rv_title','rv_make','rv_model','rv_finish','rv_year','rv_condition_name','rv_category_name',
        'rv_sku_field','rv_upc','rv_price_amount','rv_price_currency','rv_inventory','rv_description',
        'rv_bullets','rv_shipping_profile_id','rv_shipping_rates_json'
    ].forEach(function (id) {
        document.getElementById(id)?.addEventListener('input', clientValidate);
        document.getElementById(id)?.addEventListener('change', clientValidate);
    });
    ['rv_upc_does_not_apply','rv_local_pickup_only','rv_has_inventory'].forEach(function (id) {
        document.getElementById(id)?.addEventListener('change', clientValidate);
    });
})();

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

document.getElementById('btn-sync-mismatch-now')?.addEventListener('click', function () {
    var btn = this;
    var scope = btn.getAttribute('data-scope') || 'mismatch';
    if (!confirm('Push the actual live Shopify quantity to every Linked mismatch SKU on Reverb right now (no queue)? This runs in batches and may take a few minutes.')) {
        return;
    }
    btn.disabled = true;
    var original = btn.innerHTML;
    var url = '{{ route('marketplace.manager.reverb.sync.mismatch.inventory') }}';
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
@include('marketplace._listings-instant-map-js')
@endsection
