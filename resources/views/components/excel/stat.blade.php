@props(['label', 'id'])

<div class="col-md-4">
    <div {{ $attributes->merge(['class' => 'card excel-stat border-0 shadow-sm h-100']) }}>
        <div class="card-body py-3">
            <div class="text-muted text-uppercase excel-stat-label">{{ $label }}</div>
            <div id="{{ $id }}" class="excel-stat-value text-truncate">—</div>
        </div>
    </div>
</div>
