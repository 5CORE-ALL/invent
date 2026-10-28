@extends('layouts.vertical', ['title' => 'Meeting Minutes', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-12">
                <div class="page-title-box d-flex align-items-center justify-content-between py-1 flex-wrap gap-2">
                    <h4 class="page-title mb-0">Meeting Minutes</h4>
                    <a href="{{ route('meetings.index') }}" class="btn btn-primary btn-sm"><i class="ri-vidicon-line me-1"></i>Meeting Room</a>
                </div>
                <div class="text-muted small">AI-generated minutes of every recorded meeting, with the tasks created from them.</div>
            </div>
        </div>

        @if(session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger py-2">{{ session('error') }}</div>@endif

        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-5">
                <input type="search" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="Search title or summary">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All minutes statuses</option>
                    @foreach(['ready' => 'Minutes ready', 'processing' => 'Being written', 'pending' => 'Queued', 'failed' => 'Failed', 'no_recording' => 'No speech recorded'] as $k => $label)
                        <option value="{{ $k }}" @selected($status === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-outline-secondary btn-sm">Search</button></div>
        </form>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Meeting</th>
                            <th>Date</th>
                            <th>Host</th>
                            <th class="text-center">Attended</th>
                            <th>Duration</th>
                            <th class="text-center">Tasks</th>
                            <th>Minutes</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($meetings as $m)
                            @php $dur = $m->durationSeconds(); @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('meetings.minutes.show', $m) }}" class="fw-semibold">{{ $m->title }}</a>
                                    @if($m->summary)<div class="small text-muted">{{ \Illuminate\Support\Str::limit($m->summary, 110) }}</div>@endif
                                </td>
                                <td class="text-nowrap">{{ $m->started_at?->format('d M Y, h:i A') ?? $m->ended_at?->format('d M Y') }}</td>
                                <td>{{ $m->host?->name ?? '—' }}</td>
                                <td class="text-center">{{ $m->attended_count }}</td>
                                <td class="text-nowrap">{{ $dur !== null ? floor($dur / 60).' min' : '—' }}</td>
                                <td class="text-center">{{ $m->action_items_count }}</td>
                                <td>@include('meetings._minutes-badge', ['status' => $m->minutes_status])</td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('meetings.minutes.show', $m) }}" class="btn btn-outline-primary btn-sm">Open</a>
                                    @if((int) $m->host_user_id === (int) auth()->id() || \App\Support\SuperAdminAccess::isTaskAdmin(auth()->user()))
                                        <form method="POST" action="{{ route('meetings.destroy', $m) }}" class="d-inline" onsubmit="return confirm(@js('Delete "'.$m->title.'" permanently? Its recordings, transcript and minutes will be erased. Tasks already created from it are kept. This cannot be undone.'));">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-outline-danger btn-sm" title="Delete meeting"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">No ended meetings yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($meetings->hasPages())<div class="card-footer">{{ $meetings->links() }}</div>@endif
        </div>
    </div>
@endsection
