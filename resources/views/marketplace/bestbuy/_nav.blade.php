@php
    $active = $active ?? 'overview';
    $tabs = [
        'overview' => ['label' => 'Overview', 'route' => route('marketplace.manager.show', 'bestbuy')],
        'connect' => ['label' => 'Connect', 'route' => route('marketplace.manager.bestbuy.connect')],
        'products' => ['label' => 'Listings', 'route' => route('marketplace.products', 'bestbuy')],
        'orders' => ['label' => 'Orders', 'route' => route('marketplace.orders', 'bestbuy')],
        'settings' => ['label' => 'Settings', 'route' => route('marketplace.settings', 'bestbuy')],
    ];
@endphp
<ul class="nav nav-tabs nav-bordered mb-3">
    @foreach($tabs as $key => $tab)
        <li class="nav-item">
            <a href="{{ $tab['route'] }}" class="nav-link {{ $active === $key ? 'active' : '' }}">{{ $tab['label'] }}</a>
        </li>
    @endforeach
</ul>
