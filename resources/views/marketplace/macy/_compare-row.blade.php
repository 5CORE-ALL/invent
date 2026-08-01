@php
    $rowTitle = $rowTitle ?? null;
    $left = $left ?? null;
    $right = $right ?? null;
    $leftEmpty = $leftEmpty ?? 'No data available';
    $rightEmpty = $rightEmpty ?? 'No data available';
    $leftClass = $leftClass ?? 'bg-primary-subtle';
    $rightClass = $rightClass ?? 'bg-warning-subtle';
@endphp
<div class="card mb-3">
    @if($rowTitle)
        <div class="card-header">{{ $rowTitle }}</div>
    @endif
    <div class="card-body p-0">
        <div class="row g-0">
            <div class="col-lg-6 border-end">
                <div class="px-3 py-2 {{ $leftClass }} border-bottom small fw-semibold">Shopify (source)</div>
                <div class="p-3">
                    @if(filled($left))
                        {!! $left !!}
                    @else
                        <p class="text-muted mb-0 small">{{ $leftEmpty }}</p>
                    @endif
                </div>
            </div>
            <div class="col-lg-6">
                <div class="px-3 py-2 {{ $rightClass }} border-bottom small fw-semibold">Macy's</div>
                <div class="p-3">
                    @if(filled($right))
                        {!! $right !!}
                    @else
                        <p class="text-muted mb-0 small">{{ $rightEmpty }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
