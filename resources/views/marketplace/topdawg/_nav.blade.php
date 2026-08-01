@php
    $active = $active ?? 'overview';
    $tabs = [
        'overview' => ['label' => 'Overview', 'route' => route('marketplace.manager.show', 'topdawg')],
        'connect' => ['label' => 'Connect', 'route' => route('marketplace.manager.topdawg.connect')],
        'products' => ['label' => 'Listings', 'route' => route('marketplace.products', 'topdawg')],
        'orders' => ['label' => 'Orders', 'route' => route('marketplace.orders', 'topdawg')],
        'settings' => ['label' => 'Settings', 'route' => route('marketplace.settings', 'topdawg')],
    ];
@endphp
<ul class="nav nav-tabs nav-bordered mb-3">
    @foreach($tabs as $key => $tab)
        <li class="nav-item">
            <a href="{{ $tab['route'] }}" class="nav-link {{ $active === $key ? 'active' : '' }}">{{ $tab['label'] }}</a>
        </li>
    @endforeach
</ul>
