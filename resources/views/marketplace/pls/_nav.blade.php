@php
    $active = $active ?? 'overview';
    $tabs = [
        'overview' => ['label' => 'Overview', 'route' => route('marketplace.manager.show', 'pls')],
        'connect' => ['label' => 'Connect', 'route' => route('marketplace.manager.pls.connect')],
        'products' => ['label' => 'Listings', 'route' => route('marketplace.products', 'pls')],
        'orders' => ['label' => 'Orders', 'route' => route('marketplace.orders', 'pls')],
        'settings' => ['label' => 'Settings', 'route' => route('marketplace.settings', 'pls')],
    ];
@endphp
<ul class="nav nav-tabs nav-bordered mb-3">
    @foreach($tabs as $key => $tab)
        <li class="nav-item">
            <a href="{{ $tab['route'] }}" class="nav-link {{ $active === $key ? 'active' : '' }}">{{ $tab['label'] }}</a>
        </li>
    @endforeach
</ul>
