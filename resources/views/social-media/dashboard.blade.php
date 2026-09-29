@extends('layouts.vertical', ['title' => 'Social Media Dashboard'])

@section('content')
    <div class="row"><div class="col-12"><h4 class="page-title">Social Media Content & Communications</h4></div></div>
    @include('social-media._nav')
    @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
    @include('social-media._filters')

    @if (($report['accounts'] ?? collect())->isEmpty())
        <div class="card"><div class="card-body text-muted">No connected accounts match these filters. Connect an account before KPIs can be calculated.</div></div>
    @else
        <div class="row">
            @foreach (['posts', 'reach', 'impressions', 'engagement', 'engagement_rate', 'followers', 'follower_growth', 'video_views', 'website_clicks', 'leads'] as $key)
                @php $card = $report['cards'][$key]; @endphp
                <div class="col-6 col-md-4 col-xl-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-muted small">{{ $card['label'] }}</div>
                            <h3 class="my-1">
                                @include('social-media._value', ['value' => $card['value'], 'availability' => $card['availability'], 'percent' => $key === 'engagement_rate'])
                            </h3>
                            <div class="small text-muted">
                                Previous period:
                                @if (($card['previous_period']['availability'] ?? '') === 'available')
                                    {{ number_format((float) $card['previous_period']['value'], 1) }}%
                                @else
                                    Not enough history
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        @include('social-media._charts')
        <div class="card"><div class="card-body">
            <h5>Data notes</h5>
            <ul class="mb-0">@foreach ($report['notes'] as $note)<li>{{ $note }}</li>@endforeach</ul>
        </div></div>
    @endif
@endsection
