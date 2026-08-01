@php
    $active = $active ?? 'overview';
    $tabs = [
        'overview' => ['label' => 'Overview', 'route' => route('marketplace.manager.show', 'ebay2')],
        'connect' => ['label' => 'Connect', 'route' => route('marketplace.manager.ebay2.connect')],
        'products' => ['label' => 'Listings', 'route' => route('marketplace.products', 'ebay2')],
        'orders' => ['label' => 'Orders', 'route' => route('marketplace.orders', 'ebay2')],
        'settings' => ['label' => 'Settings', 'route' => route('marketplace.settings', 'ebay2')],
    ];
@endphp
<ul class="nav nav-tabs nav-bordered mb-3">
    @foreach($tabs as $key => $tab)
        <li class="nav-item">
            <a href="{{ $tab['route'] }}" class="nav-link {{ $active === $key ? 'active' : '' }}">{{ $tab['label'] }}</a>
        </li>
    @endforeach
</ul>
