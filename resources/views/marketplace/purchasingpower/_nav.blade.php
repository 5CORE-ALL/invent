@php
    $active = $active ?? 'overview';
    $tabs = [
        'overview' => ['label' => 'Overview', 'route' => route('marketplace.manager.show', 'purchasingpower')],
        'connect' => ['label' => 'Connect', 'route' => route('marketplace.manager.purchasingpower.connect')],
        'products' => ['label' => 'Listings', 'route' => route('marketplace.products', 'purchasingpower')],
        'orders' => ['label' => 'Orders', 'route' => route('marketplace.orders', 'purchasingpower')],
        'settings' => ['label' => 'Settings', 'route' => route('marketplace.settings', 'purchasingpower')],
    ];
@endphp
<ul class="nav nav-tabs nav-bordered mb-3">
    @foreach($tabs as $key => $tab)
        <li class="nav-item">
            <a href="{{ $tab['route'] }}" class="nav-link {{ $active === $key ? 'active' : '' }}">{{ $tab['label'] }}</a>
        </li>
    @endforeach
</ul>
