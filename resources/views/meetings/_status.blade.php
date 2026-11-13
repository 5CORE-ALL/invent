@php $label = ['scheduled' => 'Scheduled', 'live' => 'Live now', 'ended' => 'Ended', 'cancelled' => 'Cancelled'][$status ?? ''] ?? ucfirst((string) ($status ?? '')); @endphp
<span class="mt-status {{ $status }}">{{ $label }}</span>
