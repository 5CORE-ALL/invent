@php
    $active = $active ?? 'overview';
    $tabs = [
        'overview' => ['label' => 'Overview', 'route' => route('marketplace.manager.show', 'faire')],
        'connect' => ['label' => 'Connect', 'route' => route('marketplace.manager.faire.connect')],
        'products' => ['label' => 'Listings', 'route' => route('marketplace.products', 'faire')],
        'orders' => ['label' => 'Orders', 'route' => route('marketplace.orders', 'faire')],
        'settings' => ['label' => 'Settings', 'route' => route('marketplace.settings', 'faire')],
    ];
@endphp
<ul class="nav nav-tabs nav-bordered mb-3">
    @foreach($tabs as $key => $tab)
        <li class="nav-item">
            <a href="{{ $tab['route'] }}" class="nav-link {{ $active === $key ? 'active' : '' }}">{{ $tab['label'] }}</a>
        </li>
    @endforeach
</ul>
