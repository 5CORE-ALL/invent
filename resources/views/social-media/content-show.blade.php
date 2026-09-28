@extends('layouts.vertical', ['title' => 'Content detail'])

@section('content')
    <h4 class="page-title">{{ \Illuminate\Support\Str::limit($post->title ?: 'Content', 80) }}</h4>
    @include('social-media._nav')
    <p>
        {{ ucfirst($post->account->platform) }} — {{ $post->account->account_name }}
        · {{ $post->content_type }}
        · {{ optional($post->published_at)->toDayDateTimeString() }}
        @if ($post->permalink)<a href="{{ $post->permalink }}" target="_blank" rel="noopener">Open</a>@endif
    </p>
    <div class="card"><div class="card-body table-responsive">
        @if ($post->metrics->isEmpty())
            <p class="text-muted mb-0">No historical metrics have been stored for this item.</p>
        @else
            <table class="table table-sm">
                <thead>
                    <tr><th>Date</th><th>Reach</th><th>Impressions</th><th>Likes</th><th>Comments</th><th>Shares</th><th>Saves</th><th>Video views</th><th>Engagement rate</th></tr>
                </thead>
                <tbody>
                    @foreach ($post->metrics as $snapshot)
                        @php
                            $metrics = $snapshot->metrics ?? [];
                            $read = function (string $name) use ($metrics) {
                                $row = $metrics[$name] ?? null;
                                if (! is_array($row) || ($row['availability'] ?? '') !== 'available' || $row['value'] === null) {
                                    return null;
                                }
                                return (float) $row['value'];
                            };
                            $rate = $kpi->engagementRate($read('engagement'), $read('impressions'), $read('reach'));
                        @endphp
                        <tr>
                            <td>{{ $snapshot->metric_date->toDateString() }}</td>
                            @foreach (['reach', 'impressions', 'likes', 'comments', 'shares', 'saves', 'video_views'] as $name)
                                <td>@include('social-media._value', ['value' => $read($name), 'availability' => $read($name) === null ? 'not_available' : 'available'])</td>
                            @endforeach
                            <td>@include('social-media._value', ['value' => $rate['value'], 'availability' => $rate['availability'], 'percent' => true])</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div></div>
@endsection
