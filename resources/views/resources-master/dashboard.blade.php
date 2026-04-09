@extends('layouts.vertical', ['title' => 'Resources Master'])

@section('css')
<style>
    .rm-stat-card { border-radius: 12px; border: 1px solid rgba(0,0,0,.06); transition: transform .15s; }
    .rm-stat-card:hover { transform: translateY(-2px); }
    .rm-nav-tile { border-radius: 10px; padding: 1rem 1.25rem; display: flex; align-items: center; gap: .75rem;
        border: 1px solid rgba(0,0,0,.08); text-decoration: none; color: inherit; transition: .15s; }
    .rm-nav-tile:hover { border-color: var(--bs-primary); background: rgba(var(--bs-primary-rgb), .06); color: inherit; }
    .rm-nav-tile i { font-size: 1.5rem; color: var(--bs-primary); }
</style>
@endsection

@section('content')
    @include('layouts.shared.page-title', ['page_title' => 'Resources Master', 'sub_title' => 'Library'])

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card rm-stat-card shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase small mb-1">Total resources</h6>
                    <h2 class="mb-0">{{ $stats['total'] ?? 0 }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card rm-stat-card shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase small mb-1">Videos</h6>
                    <h2 class="mb-0">{{ $stats['videos'] ?? 0 }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card rm-stat-card shadow-sm h-100">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase small mb-2">Recently uploaded</h6>
                    @forelse(($stats['recent'] ?? collect()) as $r)
                        <div class="d-flex justify-content-between small py-1 border-bottom border-light">
                            <span class="text-truncate me-2">{{ $r->title }}</span>
                            <span class="text-muted text-nowrap">{{ $r->created_at?->diffForHumans() }}</span>
                        </div>
                    @empty
                        <span class="text-muted">No uploads yet.</span>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12">
            <h5 class="mb-3">Browse by area</h5>
        </div>
        @foreach($categories as $key => $label)
            <div class="col-md-6 col-xl-4">
                <a href="{{ route('resources-master.section', $key) }}" class="rm-nav-tile">
                    <i class="ri-folder-open-line"></i>
                    <div>
                        <div class="fw-semibold">{{ $label }}</div>
                        <div class="small text-muted">Open library</div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <h6 class="text-muted text-uppercase small mb-3">Most viewed</h6>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Title</th><th>Watches</th><th>Downloads</th></tr></thead>
                    <tbody>
                        @forelse(($stats['most_viewed'] ?? collect()) as $r)
                            <tr>
                                <td>{{ $r->title }}</td>
                                <td>{{ $r->watch_count }}</td>
                                <td>{{ $r->download_count }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-muted">No activity yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
