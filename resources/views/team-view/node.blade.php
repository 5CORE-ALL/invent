@php
    $levelLabels = ['director' => 'Director', 'mgr' => 'Manager', 'exec' => 'Executive'];
    $levelLabel = $levelLabels[$member['level']] ?? null;
    $rr = $member['rr'] ?? [];
    $searchText = strtolower(trim($member['name'] . ' ' . $member['designation'] . ' ' . $member['email'] . ' ' . $member['phone'] . ' ' . implode(' ', $rr)));
@endphp
<li class="tv-node" data-search="{{ $searchText }}">
    <div class="tv-card tv-level-{{ $member['level'] ?: 'none' }}">
        <img src="{{ $member['avatar'] }}" alt="{{ $member['name'] }}" class="tv-avatar" loading="lazy">
        <div class="tv-info">
            <div class="tv-name">
                {{ $member['name'] }}
                @if($levelLabel)
                    <span class="tv-badge tv-badge-{{ $member['level'] }}">{{ $levelLabel }}</span>
                @endif
                @if(count($member['children']))
                    <span class="tv-count" title="Direct reports">{{ count($member['children']) }} report{{ count($member['children']) === 1 ? '' : 's' }}</span>
                @endif
            </div>
            <div class="tv-desig">{{ $member['designation'] !== '' ? $member['designation'] : '—' }}</div>
            <div class="tv-contact">
                @if($member['email'] !== '')
                    <a href="mailto:{{ $member['email'] }}"><i class="ri-mail-line"></i> {{ $member['email'] }}</a>
                @endif
                @if($member['phone'] !== '')
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $member['phone']) }}"><i class="ri-phone-line"></i> {{ $member['phone'] }}</a>
                @endif
            </div>
            <div class="tv-rr">
                <span class="tv-rr-label">R&amp;R</span>
                @if(count($rr))
                    <details>
                        <summary>{{ count($rr) }} responsibilit{{ count($rr) === 1 ? 'y' : 'ies' }}</summary>
                        <ul>
                            @foreach($rr as $line)
                                <li>{{ $line }}</li>
                            @endforeach
                        </ul>
                    </details>
                @else
                    <span class="text-muted">Not defined</span>
                @endif
            </div>
        </div>
    </div>
    @if(count($member['children']))
        <ul class="tv-children">
            @foreach($member['children'] as $child)
                @include('team-view.node', ['member' => $child])
            @endforeach
        </ul>
    @endif
</li>
