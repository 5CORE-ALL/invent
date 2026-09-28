@php
    $available = ($availability ?? '') === 'available' && $value !== null;
@endphp
@if ($available)
    @if (!empty($percent))
        {{ number_format((float) $value * 100, 2) }}%
    @else
        {{ number_format((float) $value) }}
    @endif
@else
    <span class="text-muted">Not available</span>
@endif
