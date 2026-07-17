@extends('layouts.vertical', ['title' => $title ?? 'Shopify Live SKUs', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
@php
    $status = $status ?? 'all';
    $statusCounts = $statusCounts ?? [];
    $filters = [
        'all' => 'All',
        'active' => 'Active',
        'active_in_stock' => 'Active + inventory',
        'active_oos' => 'Active + no inventory',
        'draft' => 'Draft',
        'archived' => 'Archived',
        'unlisted' => 'Unlisted',
    ];
    $badgeClass = [
        'active' => 'bg-success-subtle text-success',
        'draft' => 'bg-warning-subtle text-warning',
        'archived' => 'bg-secondary-subtle text-secondary',
        'unlisted' => 'bg-info-subtle text-info',
    ];
@endphp
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h4 class="mb-1">Shopify Live SKUs</h4>
                <p class="text-muted mb-0">
                    Showing <strong>{{ number_format((int) ($statusCounts[$status] ?? $rows->total() ?? 0)) }}</strong>
                    · All: <strong>{{ number_format((int) ($allCount ?? 0)) }}</strong>
                    · Active: <strong>{{ number_format((int) ($activeCount ?? 0)) }}</strong>
                    @if(!empty($syncedAt))
                        · catalog synced {{ $syncedAt }}
                    @endif
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('marketplace.manager.index') }}" class="btn btn-outline-secondary btn-sm">Back to Marketplace Manager</a>
                <form method="post" action="{{ route('marketplace.manager.refresh.shopify') }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="ri-store-2-line me-1"></i> Refresh Shopify
                    </button>
                </form>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body py-2">
                <form method="get" action="{{ route('marketplace.manager.shopify.active') }}" class="row g-2 align-items-center">
                    <div class="col-md-5">
                        <input type="text" name="q" value="{{ $search ?? '' }}" class="form-control"
                               placeholder="Search SKU, product title, or variant…">
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            @foreach($filters as $key => $label)
                                <option value="{{ $key }}" @selected($status === $key)>
                                    {{ $label }} ({{ number_format((int) ($statusCounts[$key] ?? 0)) }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        @if(!empty($search) || $status !== 'all')
                            <a href="{{ route('marketplace.manager.shopify.active') }}" class="btn btn-light">Clear</a>
                        @endif
                    </div>
                </form>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    @foreach($filters as $key => $label)
                        <a href="{{ route('marketplace.manager.shopify.active', array_filter(['status' => $key, 'q' => $search ?: null])) }}"
                           class="btn btn-sm {{ $status === $key ? 'btn-dark' : 'btn-outline-secondary' }}">
                            {{ $label }}
                            <span class="ms-1">{{ number_format((int) ($statusCounts[$key] ?? 0)) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>SKU</th>
                                <th>Product</th>
                                <th>Variant</th>
                                <th class="text-end">Qty</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rows as $row)
                                @php
                                    $st = strtolower(trim((string) ($row->product_status ?? 'active'))) ?: 'active';
                                    $qty = (int) ($row->inventory_quantity ?? 0);
                                @endphp
                                <tr>
                                    <td><code>{{ $row->sku }}</code></td>
                                    <td>{{ $row->product_title }}</td>
                                    <td class="text-muted">{{ $row->variant_title ?: '—' }}</td>
                                    <td class="text-end">
                                        @if($qty > 0)
                                            <span class="text-success fw-semibold">{{ number_format($qty) }}</span>
                                        @elseif($qty < 0)
                                            <span class="text-danger">{{ number_format($qty) }}</span>
                                        @else
                                            <span class="text-muted">0</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ $badgeClass[$st] ?? 'bg-light text-muted' }}">{{ $st }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        No SKUs found for this filter. Run <strong>Refresh Shopify</strong> first.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if(method_exists($rows, 'links'))
                <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="text-muted small">
                        @if($rows->total() > 0)
                            Showing {{ number_format($rows->firstItem()) }}–{{ number_format($rows->lastItem()) }}
                            of {{ number_format($rows->total()) }}
                        @else
                            No results
                        @endif
                    </div>
                    <div>
                        {{ $rows->onEachSide(1)->links('pagination::bootstrap-5') }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
