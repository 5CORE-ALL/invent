@php
    $nodes = $nodes ?? [];
    $depth = $depth ?? 0;
@endphp

@foreach($nodes as $node)
    @php
        $hasChildren = ! empty($node['children']);
        $isOpen = $hasChildren && ($node['open'] ?? false);
    @endphp
    <li class="res-tree-node {{ ($node['active'] ?? false) ? 'active' : '' }} {{ $isOpen ? 'open' : '' }}" data-folder-name="{{ strtolower($node['name']) }}">
        <div class="res-tree-row" style="padding-left: {{ 12 + ($depth * 14) }}px;">
            @if($hasChildren)
                <button type="button" class="res-tree-toggle" aria-label="Toggle folder">
                    <i class="ri-arrow-right-s-line"></i>
                </button>
            @else
                <span class="res-tree-spacer"></span>
            @endif
            <a href="{{ route('resources.index', ['folder' => $node['id']]) }}" class="res-tree-link">
                <i class="ri-folder-3-{{ ($node['active'] ?? false) ? 'fill' : 'line' }}"></i>
                <span class="res-tree-name" title="{{ $node['name'] }}">{{ $node['name'] }}</span>
                @if(($node['count'] ?? 0) > 0)
                    <span class="res-tree-count">{{ $node['count'] }}</span>
                @endif
            </a>
        </div>
        @if($hasChildren)
            <ul class="res-tree-list">
                @include('resources.partials.folder-tree-nodes', ['nodes' => $node['children'], 'depth' => $depth + 1])
            </ul>
        @endif
    </li>
@endforeach
