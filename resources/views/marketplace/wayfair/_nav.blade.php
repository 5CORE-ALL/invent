@php
    $active = $active ?? 'overview';
    $tabs = [
        'overview' => ['label' => 'Overview', 'route' => route('marketplace.manager.show', 'wayfair')],
        'connect' => ['label' => 'Connect', 'route' => route('marketplace.manager.wayfair.connect')],
        'products' => ['label' => 'Listings', 'route' => route('marketplace.products', 'wayfair')],
        'orders' => ['label' => 'Orders', 'route' => route('marketplace.orders', 'wayfair')],
        'settings' => ['label' => 'Settings', 'route' => route('marketplace.settings', 'wayfair')],
    ];
@endphp
<ul class="nav nav-tabs nav-bordered mb-3">
    @foreach($tabs as $key => $tab)
        <li class="nav-item">
            <a href="{{ $tab['route'] }}" class="nav-link {{ $active === $key ? 'active' : '' }}">{{ $tab['label'] }}</a>
        </li>
    @endforeach
</ul>
