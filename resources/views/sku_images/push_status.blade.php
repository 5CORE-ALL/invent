@extends('layouts.vertical', ['title' => $title ?? 'SKU image push status'])

@php
    use App\Models\MarketplacePercentage;
@endphp

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'SKU image push status',
        'sub_title' => 'Marketplace & status for each push',
    ])

    <div class="row g-3 mb-3">
        <div class="col-12">
            <a href="{{ route('sku-images.index') }}" class="btn btn-sm btn-outline-primary">
                <i class="ri-arrow-left-line me-1"></i>SKU Image Manager
            </a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h5 class="mb-0">By marketplace</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Marketplace</th>
                            <th class="text-end">Pending</th>
                            <th class="text-end">Sent</th>
                            <th class="text-end">Failed</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($summary as $row)
                            <tr>
                                <td>
                                    @php $mp = $row['marketplace']; @endphp
                                    <span class="fw-semibold">{{ MarketplacePercentage::displayNameForMarketplace($mp) ?? $mp?->name ?? ('#'.$mp?->id) }}</span>
                                    @if ($mp?->code)
                                        <span class="text-muted small">({{ $mp->code }})</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-warning text-dark">{{ (int) $row['pending'] }}</span>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-success">{{ (int) $row['sent'] }}</span>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-danger">{{ (int) $row['failed'] }}</span>
                                </td>
                                <td class="text-end text-muted">{{ (int) $row['total'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No push records yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Detail</h5>
        </div>
        <div class="card-body">
            <form method="get" action="{{ route('sku-images.push-status') }}" class="row g-2 align-items-end mb-3">
                <div class="col-sm-4 col-md-3">
                    <label class="form-label small text-muted mb-0">Marketplace</label>
                    <select name="marketplace_id" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach ($summary as $id => $row)
                            @if ($row['marketplace'])
                                <option value="{{ $row['marketplace']->id }}"
                                    @selected($filterMarketplaceId === (int) $row['marketplace']->id)>
                                    {{ MarketplacePercentage::displayNameForMarketplace($row['marketplace']) ?? $row['marketplace']->name }}
                                </option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-3 col-md-2">
                    <label class="form-label small text-muted mb-0">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="pending" @selected($filterStatus === 'pending')>Pending</option>
                        <option value="sent" @selected($filterStatus === 'sent')>Sent</option>
                        <option value="failed" @selected($filterStatus === 'failed')>Failed</option>
                    </select>
                </div>
                <div class="col-sm-4 col-md-3">
                    <label class="form-label small text-muted mb-0">SKU contains</label>
                    <input type="text" name="sku" class="form-control form-control-sm" value="{{ $filterSku }}"
                        placeholder="Filter by SKU" autocomplete="off">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <a href="{{ route('sku-images.push-status') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0" style="width:100%">
                    <thead class="table-info">
                        <tr>
                            <th style="width:72px">Image</th>
                            <th>SKU</th>
                            <th>File</th>
                            <th>Marketplace</th>
                            <th>Status</th>
                            <th>Updated</th>
                            <th style="width:90px">Response</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($maps as $map)
                            @php
                                $img = $map->skuImage;
                                $product = $img?->product;
                                $sku = $product?->sku ?? '—';
                                $url = $img?->url;
                            @endphp
                            <tr>
                                <td>
                                    @if ($url)
                                        <img src="{{ $url }}" alt="" class="rounded" style="width:56px;height:56px;object-fit:cover"
                                            loading="lazy">
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td class="fw-semibold text-nowrap">{{ $sku }}</td>
                                <td class="small text-break">
                                    @if ($img?->file_name)
                                        <span title="{{ $img->file_name }}">{{ \Illuminate\Support\Str::limit($img->file_name, 40) }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="text-nowrap">{{ MarketplacePercentage::displayNameForMarketplace($map->marketplace) ?? $map->marketplace?->name ?? '—' }}</span>
                                    @if ($map->marketplace?->code)
                                        <span class="text-muted small">({{ $map->marketplace->code }})</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($map->status === 'sent')
                                        <span class="badge bg-success">Sent</span>
                                    @elseif ($map->status === 'failed')
                                        <span class="badge bg-danger">Failed</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    @endif
                                </td>
                                <td class="text-muted small text-nowrap">
                                    {{ $map->updated_at?->format('Y-m-d H:i') ?? '—' }}
                                    @if ($map->status === 'sent' && $map->sent_at)
                                        <br><span class="text-success">Sent {{ $map->sent_at->format('Y-m-d H:i') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($map->response !== null)
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal"
                                            data-bs-target="#imRespModal"
                                            data-payload="{{ e(json_encode($map->response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) }}">
                                            View
                                        </button>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    No rows match the filters, or no pushes have been recorded yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($maps->hasPages())
                <div class="d-flex justify-content-end mt-3">
                    {{ $maps->links() }}
                </div>
            @endif
        </div>
    </div>

    <div class="modal fade" id="imRespModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">API / push response</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <pre class="mb-0 small" style="white-space:pre-wrap;word-break:break-word" id="imRespBody"></pre>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        (function() {
            var modal = document.getElementById('imRespModal');
            if (!modal) return;
            modal.addEventListener('show.bs.modal', function(e) {
                var btn = e.relatedTarget;
                if (!btn || !btn.getAttribute) return;
                var raw = btn.getAttribute('data-payload') || 'null';
                var body = document.getElementById('imRespBody');
                if (!body) return;
                try {
                    var obj = JSON.parse(raw);
                    body.textContent = JSON.stringify(obj, null, 2);
                } catch (err) {
                    body.textContent = String(raw);
                }
            });
        })();
    </script>
@endsection
