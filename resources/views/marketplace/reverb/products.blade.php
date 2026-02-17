@extends('layouts.vertical', ['title' => $title ?? 'Reverb - Products', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Reverb Listed Products</h4>
                    <span class="badge bg-primary">{{ $products->total() }} products</span>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">Products synced from Reverb listings; title, description, image, UPC, brand and model from Shopify. To keep Reverb inventory in sync with Shopify (bridge), run <code>php artisan reverb:sync-inventory-from-shopify</code> or rely on the scheduled job (every 30 min).</p>

                    {{-- Search and filters --}}
                    <form method="get" action="{{ request()->url() }}" class="mb-3">
                        <input type="hidden" name="state" value="{{ $stateTab ?? 'all' }}" />
                        <div class="row g-2 align-items-end flex-wrap">
                            <div class="col-auto">
                                <label class="form-label small mb-0">Search Products</label>
                                <input type="text" name="search_name" class="form-control form-control-sm" placeholder="By name..." value="{{ old('search_name', $searchName ?? '') }}" style="min-width: 180px;" />
                            </div>
                            <div class="col-auto">
                                <label class="form-label small mb-0">Search SKU</label>
                                <input type="text" name="search_sku" class="form-control form-control-sm" placeholder="By SKU..." value="{{ old('search_sku', $searchSku ?? '') }}" style="min-width: 140px;" />
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-primary btn-sm">Search</button>
                            </div>
                            <div class="col-auto">
                                <a href="{{ request()->url() }}?state={{ urlencode($stateTab ?? 'all') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
                            </div>
                        </div>
                    </form>

                    {{-- Status tabs with counts --}}
                    @php $counts = $counts ?? []; @endphp
                    <ul class="nav nav-tabs nav-bordered mb-3" role="tablist">
                        <li class="nav-item">
                            <a href="{{ request()->url() }}?state=all&search_name={{ urlencode($searchName ?? '') }}&search_sku={{ urlencode($searchSku ?? '') }}" class="nav-link {{ ($stateTab ?? 'all') === 'all' ? 'active' : '' }}">All {{ $counts['all'] ?? 0 }}</a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ request()->url() }}?state=drafts&search_name={{ urlencode($searchName ?? '') }}&search_sku={{ urlencode($searchSku ?? '') }}" class="nav-link {{ ($stateTab ?? '') === 'drafts' ? 'active' : '' }}">Drafts {{ $counts['drafts'] ?? 0 }}</a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ request()->url() }}?state=active&search_name={{ urlencode($searchName ?? '') }}&search_sku={{ urlencode($searchSku ?? '') }}" class="nav-link {{ ($stateTab ?? '') === 'active' ? 'active' : '' }}">Active {{ $counts['active'] ?? 0 }}</a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ request()->url() }}?state=ended&search_name={{ urlencode($searchName ?? '') }}&search_sku={{ urlencode($searchSku ?? '') }}" class="nav-link {{ ($stateTab ?? '') === 'ended' ? 'active' : '' }}">Ended {{ $counts['ended'] ?? 0 }}</a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ request()->url() }}?state=sold&search_name={{ urlencode($searchName ?? '') }}&search_sku={{ urlencode($searchSku ?? '') }}" class="nav-link {{ ($stateTab ?? '') === 'sold' ? 'active' : '' }}">Sold {{ $counts['sold'] ?? 0 }}</a>
                        </li>
                    </ul>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40px;"><input type="checkbox" class="form-check-input" id="selectAll" aria-label="Select all" /></th>
                                    <th style="width: 80px;">Image</th>
                                    <th>Name</th>
                                    <th>SKU</th>
                                    <th>UPC</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                    <th>Brand</th>
                                    <th>Model</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($products as $p)
                                    <tr>
                                        <td><input type="checkbox" class="form-check-input row-select" data-sku="{{ $p->sku ?? '' }}" aria-label="Select" /></td>
                                        <td>
                                            @if(!empty($p->image_src))
                                                <img src="{{ $p->image_src }}" alt="" class="img-thumbnail" style="max-width: 64px; max-height: 64px; object-fit: contain;" />
                                            @else
                                                <span class="text-muted small" title="No image">—</span>
                                            @endif
                                        </td>
                                        <td><span class="text-primary">{{ $p->title ?? $p->sku }}</span></td>
                                        <td>{{ $p->sku ?? '—' }}</td>
                                        <td class="small">{{ $p->upc ?? '—' }}</td>
                                        <td>{{ $p->quantity ?? '—' }}</td>
                                        <td>{{ isset($p->price) ? number_format((float)$p->price, 2) : '—' }}</td>
                                        <td>{{ $p->brand ?? '—' }}</td>
                                        <td class="small">{{ Str::limit($p->model ?? '—', 30) }}</td>
                                        <td class="small text-muted">{{ Str::limit($p->description ?? '', 120) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center text-muted">No Reverb listed products. Run <code>php artisan reverb:fetch</code> to sync.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($products->hasPages())
                        <div class="d-flex justify-content-center mt-3">
                            {{ $products->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <script>
        document.getElementById('selectAll')?.addEventListener('change', function () {
            document.querySelectorAll('.row-select').forEach(function (cb) { cb.checked = this.checked; }.bind(this));
        });
    </script>
@endsection
