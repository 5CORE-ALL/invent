@php
    $active = $active ?? 'overview';
    $tabs = [
        'overview' => ['label' => 'Overview', 'route' => route('marketplace.manager.show', 'tiktok')],
        'connect' => ['label' => 'Connect', 'route' => route('marketplace.manager.tiktok.connect')],
        'products' => ['label' => 'Listings', 'route' => route('marketplace.products', 'tiktok')],
        'orders' => ['label' => 'Orders', 'route' => route('marketplace.orders', 'tiktok')],
        'settings' => ['label' => 'Settings', 'route' => route('marketplace.settings', 'tiktok')],
    ];
@endphp
<ul class="nav nav-tabs nav-bordered mb-3">
    @foreach($tabs as $key => $tab)
        <li class="nav-item">
            <a href="{{ $tab['route'] }}" class="nav-link {{ $active === $key ? 'active' : '' }}">{{ $tab['label'] }}</a>
        </li>
    @endforeach
</ul>
