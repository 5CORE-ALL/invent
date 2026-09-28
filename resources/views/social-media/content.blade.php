@extends('layouts.vertical', ['title' => 'Social Media Content'])

@section('content')
    <h4 class="page-title">Content</h4>
    @include('social-media._nav')
    @include('social-media._filters', ['search' => true])
    <div class="card"><div class="card-body table-responsive">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Platform</th><th>Account</th>
                    <th><a href="{{ request()->fullUrlWithQuery(['sort' => 'title', 'direction' => 'asc']) }}">Content</a></th>
                    <th><a href="{{ request()->fullUrlWithQuery(['sort' => 'content_type', 'direction' => 'asc']) }}">Type</a></th>
                    <th><a href="{{ request()->fullUrlWithQuery(['sort' => 'published_at', 'direction' => 'desc']) }}">Published</a></th>
                    <th>Reach</th><th>Impressions</th><th>Likes</th><th>Comments</th><th>Shares</th><th>Saves</th><th>Clicks</th><th>Video views</th><th>Engagement rate</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($posts as $post)
                    @php
                        $metrics = $post->latestMetric->metrics ?? [];
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
                        <td>{{ ucfirst($post->account->platform) }}</td>
                        <td>{{ $post->account->account_name }}</td>
                        <td><a href="{{ route('social-media.content.show', $post) }}">{{ \Illuminate\Support\Str::limit($post->title ?: $post->external_post_id, 80) }}</a></td>
                        <td>{{ $post->content_type }}</td>
                        <td>{{ optional($post->published_at)->toDateString() }}</td>
                        @foreach (['reach', 'impressions', 'likes', 'comments', 'shares', 'saves', 'clicks', 'video_views'] as $name)
                            <td>@include('social-media._value', ['value' => $read($name), 'availability' => $read($name) === null ? 'not_available' : 'available'])</td>
                        @endforeach
                        <td>@include('social-media._value', ['value' => $rate['value'], 'availability' => $rate['availability'], 'percent' => true])</td>
                    </tr>
                @empty
                    <tr><td colspan="14" class="text-muted">No content in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $posts->links() }}
    </div></div>
@endsection
