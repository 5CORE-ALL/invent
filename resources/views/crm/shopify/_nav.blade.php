@php($active = $active ?? 'customers')
<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
        <a href="{{ route('crm.shopify.customers.index') }}"
           class="nav-link @if ($active === 'customers') active @endif"
           @if ($active === 'customers') aria-current="page" @endif>
            Shopify Customers
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a href="{{ route('crm.shopify.others.index') }}"
           class="nav-link @if ($active === 'others') active @endif"
           @if ($active === 'others') aria-current="page" @endif>
            Others Customers
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a href="{{ route('crm.shopify.orders.index') }}"
           class="nav-link @if ($active === 'orders') active @endif"
           @if ($active === 'orders') aria-current="page" @endif>
            Orders
        </a>
    </li>
</ul>
