{{-- Reusable priority badge --}}
@props(['priority'])
@php
    $classes = match ($priority) {
        'Low' => 'bg-light text-dark border',
        'Medium' => 'bg-warning text-dark',
        'High' => 'text-white',
        'Urgent' => 'bg-danger',
        default => 'bg-secondary',
    };
    $style = $priority === 'High' ? 'background-color:#fd7e14;' : '';
@endphp
<span {{ $attributes->merge(['class' => 'badge ' . $classes]) }} @if($style) style="{{ $style }}" @endif>{{ $priority }}</span>
