@php
    $active = $active ?? 'overview';
    $tabs = [
        'overview' => ['label' => 'Overview', 'route' => route('marketplace.manager.show', 'tiktok2')],
        'connect' => ['label' => 'Connect', 'route' => route('marketplace.manager.tiktok2.connect')],
        'products' => ['label' => 'Listings', 'route' => route('marketplace.products', 'tiktok2')],
        'orders' => ['label' => 'Orders', 'route' => route('marketplace.orders', 'tiktok2')],
        'settings' => ['label' => 'Settings', 'route' => route('marketplace.settings', 'tiktok2')],
    ];
@endphp
<ul class="nav nav-tabs nav-bordered mb-3">
    @foreach($tabs as $key => $tab)
        <li class="nav-item">
            <a href="{{ $tab['route'] }}" class="nav-link {{ $active === $key ? 'active' : '' }}">{{ $tab['label'] }}</a>
        </li>
    @endforeach
</ul>
