@php
    $active = $active ?? 'overview';
    $tabs = [
        'overview' => ['label' => 'Overview', 'route' => route('marketplace.manager.show', 'ebay3')],
        'connect' => ['label' => 'Connect', 'route' => route('marketplace.manager.ebay3.connect')],
        'products' => ['label' => 'Listings', 'route' => route('marketplace.products', 'ebay3')],
        'orders' => ['label' => 'Orders', 'route' => route('marketplace.orders', 'ebay3')],
        'settings' => ['label' => 'Settings', 'route' => route('marketplace.settings', 'ebay3')],
    ];
@endphp
<ul class="nav nav-tabs nav-bordered mb-3">
    @foreach($tabs as $key => $tab)
        <li class="nav-item">
            <a href="{{ $tab['route'] }}" class="nav-link {{ $active === $key ? 'active' : '' }}">{{ $tab['label'] }}</a>
        </li>
    @endforeach
</ul>
