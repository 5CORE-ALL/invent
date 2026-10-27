@php
    $map = [
        'pending' => ['bg-warning text-dark', 'Minutes queued'],
        'processing' => ['bg-info text-dark', 'AI writing minutes…'],
        'ready' => ['bg-success', 'Minutes ready'],
        'failed' => ['bg-danger', 'Minutes failed'],
        'no_recording' => ['bg-secondary', 'No speech recorded'],
    ];
    [$cls, $label] = $map[$status] ?? ['bg-secondary', ucfirst((string) $status)];
@endphp
<span class="badge {{ $cls }}">{{ $label }}</span>
