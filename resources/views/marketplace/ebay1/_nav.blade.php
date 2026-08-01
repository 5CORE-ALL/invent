@php
    $active = $active ?? 'overview';
    $tabs = [
        'overview' => ['label' => 'Overview', 'route' => route('marketplace.manager.show', 'ebay1')],
        'connect' => ['label' => 'Connect', 'route' => route('marketplace.manager.ebay1.connect')],
        'products' => ['label' => 'Listings', 'route' => route('marketplace.products', 'ebay1')],
        'orders' => ['label' => 'Orders', 'route' => route('marketplace.orders', 'ebay1')],
        'settings' => ['label' => 'Settings', 'route' => route('marketplace.settings', 'ebay1')],
    ];
@endphp
<ul class="nav nav-tabs nav-bordered mb-3">
    @foreach($tabs as $key => $tab)
        <li class="nav-item">
            <a href="{{ $tab['route'] }}" class="nav-link {{ $active === $key ? 'active' : '' }}">{{ $tab['label'] }}</a>
        </li>
    @endforeach
</ul>
