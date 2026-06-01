{{-- Reusable status badge for follow-up tickets --}}
@props(['status'])
@php
    $classes = match ($status) {
        'Pending' => 'bg-secondary',
        'In Progress' => 'bg-primary',
        'Resolved' => 'bg-success',
        'Escalated' => 'bg-danger',
        default => 'bg-secondary',
    };
@endphp
<span {{ $attributes->merge(['class' => 'badge ' . $classes]) }}>{{ $status }}</span>
