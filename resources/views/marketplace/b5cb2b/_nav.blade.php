@php
    $active = $active ?? 'overview';
    $tabs = [
        'overview' => ['label' => 'Overview', 'route' => route('marketplace.manager.show', 'b5cb2b')],
        'connect' => ['label' => 'Connect', 'route' => route('marketplace.manager.b5cb2b.connect')],
        'products' => ['label' => 'Listings', 'route' => route('marketplace.products', 'b5cb2b')],
        'orders' => ['label' => 'Orders', 'route' => route('marketplace.orders', 'b5cb2b')],
        'settings' => ['label' => 'Settings', 'route' => route('marketplace.settings', 'b5cb2b')],
    ];
@endphp
<ul class="nav nav-tabs nav-bordered mb-3">
    @foreach($tabs as $key => $tab)
        <li class="nav-item">
            <a href="{{ $tab['route'] }}" class="nav-link {{ $active === $key ? 'active' : '' }}">{{ $tab['label'] }}</a>
        </li>
    @endforeach
</ul>
