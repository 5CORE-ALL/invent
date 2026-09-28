<div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-sm {{ request()->routeIs('social-media.dashboard') ? 'btn-primary' : 'btn-light' }}" href="{{ route('social-media.dashboard') }}">Dashboard</a>
    <a class="btn btn-sm {{ request()->routeIs('social-media.accounts') ? 'btn-primary' : 'btn-light' }}" href="{{ route('social-media.accounts') }}">Accounts</a>
    <a class="btn btn-sm {{ request()->routeIs('social-media.content*') ? 'btn-primary' : 'btn-light' }}" href="{{ route('social-media.content') }}">Content</a>
    <a class="btn btn-sm {{ request()->routeIs('social-media.analytics') ? 'btn-primary' : 'btn-light' }}" href="{{ route('social-media.analytics') }}">Analytics</a>
    <a class="btn btn-sm {{ request()->routeIs('social-media.executive') ? 'btn-primary' : 'btn-light' }}" href="{{ route('social-media.executive') }}">Executive KPI</a>
    @can('social_media.targets')
        <a class="btn btn-sm {{ request()->routeIs('social-media.targets') ? 'btn-primary' : 'btn-light' }}" href="{{ route('social-media.targets') }}">Targets</a>
    @endcan
    @can('social_media.reports')
        <a class="btn btn-sm {{ request()->routeIs('social-media.reports') ? 'btn-primary' : 'btn-light' }}" href="{{ route('social-media.reports') }}">Reports</a>
    @endcan
    <a class="btn btn-sm {{ request()->routeIs('social-media.health') ? 'btn-primary' : 'btn-light' }}" href="{{ route('social-media.health') }}">Sync / API Health</a>
</div>
