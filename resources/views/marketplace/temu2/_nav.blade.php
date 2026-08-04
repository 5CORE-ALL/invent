@php
    $active = $active ?? 'overview';
    $tabs = [
        'overview' => ['label' => 'Overview', 'route' => route('marketplace.manager.show', 'temu2')],
        'connect' => ['label' => 'Connect', 'route' => route('marketplace.manager.temu2.connect')],
        'products' => ['label' => 'Listings', 'route' => route('marketplace.products', 'temu2')],
        'orders' => ['label' => 'Orders', 'route' => route('marketplace.orders', 'temu2')],
        'settings' => ['label' => 'Settings', 'route' => route('marketplace.settings', 'temu2')],
    ];
@endphp
<ul class="nav nav-tabs nav-bordered mb-3">
    @foreach($tabs as $key => $tab)
        <li class="nav-item">
            <a href="{{ $tab['route'] }}" class="nav-link {{ $active === $key ? 'active' : '' }}">{{ $tab['label'] }}</a>
        </li>
    @endforeach
</ul>
