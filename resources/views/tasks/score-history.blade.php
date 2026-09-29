@extends('layouts.vertical', ['title' => 'Score History', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <style>
        .score-history-meta { color: #64748b; font-size: 0.9rem; }
        .score-history-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: end;
            margin-bottom: 1rem;
        }
        .score-history-filters label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            color: #475569;
            margin-bottom: 0.25rem;
        }
        .score-history-latest {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        .score-history-latest__card {
            min-width: 140px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.7rem 0.9rem;
            background: #fff;
        }
        .score-history-latest__label {
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #64748b;
        }
        .score-history-latest__value {
            font-size: 1.35rem;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            color: #0f172a;
        }
        .score-history-table th {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
            white-space: nowrap;
        }
        .score-history-percent {
            font-weight: 800;
            font-variant-numeric: tabular-nums;
        }
        .score-history-pager {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-top: 0.75rem;
        }
    </style>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box">
                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="{{ route('tasks.index') }}">Task Manager</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('tasks.summary') }}">Task Summary</a></li>
                            <li class="breadcrumb-item active">Score History</li>
                        </ol>
                    </div>
                    <h4 class="page-title">Score History</h4>
                </div>
            </div>
        </div>

        <p class="score-history-meta">Every CL R&amp;R, CL MGR, and CL GEN score is saved for each user when that checklist changes.</p>

        <form method="get" action="{{ route('tasks.scoreHistory') }}" class="score-history-filters">
            <div>
                <label for="score-history-user">User</label>
                <select id="score-history-user" name="user_id" class="form-select">
                    <option value="">All users</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected($selectedUserId === (int) $user->id)>
                            {{ $user->name }}@if ($user->designation) — {{ $user->designation }}@endif
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="score-history-type">Score</label>
                <select id="score-history-type" name="score_type" class="form-select">
                    <option value="">All scores</option>
                    @foreach ($scoreTypeLabels as $type => $label)
                        <option value="{{ $type }}" @selected($scoreType === $type)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn-primary">Show</button>
            </div>
        </form>

        @if ($selectedUserId > 0)
            <div class="score-history-latest">
                @foreach ($scoreTypeLabels as $type => $label)
                    @php $row = $latest[$type] ?? null; @endphp
                    <div class="score-history-latest__card">
                        <div class="score-history-latest__label">{{ $label }}</div>
                        <div class="score-history-latest__value">{{ $row ? $row->percent.'%' : '—' }}</div>
                        <div class="score-history-meta">
                            {{ $row && $row->captured_at ? $row->captured_at->timezone(config('app.timezone'))->format('M j, Y g:i A') : 'No score saved' }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle score-history-table mb-0">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>User</th>
                                <th>Designation</th>
                                <th>Score</th>
                                <th>Percent</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($history ?? [] as $entry)
                                <tr>
                                    <td>{{ $entry->captured_at ? $entry->captured_at->timezone(config('app.timezone'))->format('M j, Y g:i A') : '—' }}</td>
                                    <td>{{ $entry->user->name ?? '—' }}</td>
                                    <td>{{ $entry->user->designation ?? '—' }}</td>
                                    <td>{{ $scoreTypeLabels[$entry->score_type] ?? $entry->score_type }}</td>
                                    <td class="score-history-percent">{{ (int) $entry->percent }}%</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No scores saved yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($history && $history->hasPages())
                    <div class="score-history-pager">
                        <div>
                            @if ($history->onFirstPage())
                                <span class="btn btn-light btn-sm disabled">Previous</span>
                            @else
                                <a class="btn btn-light btn-sm" href="{{ $history->previousPageUrl() }}">Previous</a>
                            @endif
                            @if ($history->hasMorePages())
                                <a class="btn btn-light btn-sm" href="{{ $history->nextPageUrl() }}">Next</a>
                            @else
                                <span class="btn btn-light btn-sm disabled">Next</span>
                            @endif
                        </div>
                        <div class="score-history-meta">
                            {{ $history->firstItem() }}–{{ $history->lastItem() }} of {{ $history->total() }}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
