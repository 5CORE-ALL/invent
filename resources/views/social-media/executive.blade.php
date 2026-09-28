@extends('layouts.vertical', ['title' => 'Executive KPI'])

@section('content')
    <h4 class="page-title">Executive progress</h4>
    @include('social-media._nav')
    @include('social-media._filters')
    <div class="card"><div class="card-body table-responsive">
        <table class="table table-sm align-middle">
            <thead>
                <tr><th>KPI</th><th>Target</th><th>Actual</th><th>Remaining</th><th>Achievement</th><th>Status</th><th>Change</th></tr>
            </thead>
            <tbody>
                @foreach (['posts', 'reach', 'impressions', 'engagement', 'engagement_rate', 'followers', 'follower_growth', 'video_views', 'website_clicks', 'leads'] as $key)
                    @php
                        $card = $report['cards'][$key];
                        $row = $report['progress'][$key];
                        $badge = match ($row['status']) {
                            'On Track' => 'success',
                            'In Progress' => 'info',
                            'Needs Attention' => 'warning',
                            default => 'secondary',
                        };
                    @endphp
                    <tr>
                        <td>{{ $card['label'] }}</td>
                        <td>{{ $row['target'] === null ? '—' : number_format((float) $row['target']) }}</td>
                        <td>@include('social-media._value', ['value' => $card['value'], 'availability' => $card['availability'], 'percent' => $key === 'engagement_rate'])</td>
                        <td>{{ $row['remaining'] === null ? '—' : number_format((float) $row['remaining']) }}</td>
                        <td>{{ $row['percent'] === null ? '—' : number_format((float) $row['percent'], 1).'%' }}</td>
                        <td><span class="badge bg-{{ $badge }}">{{ $row['status'] }}</span></td>
                        <td>
                            @if (($card['previous_period']['availability'] ?? '') === 'available')
                                {{ number_format((float) $card['previous_period']['value'], 1) }}%
                            @else
                                Not enough history
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="text-muted small mb-0">Targets come from the Targets page. A KPI stays “Not available” when the platform API does not provide it.</p>
    </div></div>
@endsection
