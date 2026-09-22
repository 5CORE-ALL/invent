@extends('layouts.vertical', ['title' => 'Yesterday Done', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <style>
        .ydone-page-meta {
            color: #64748b;
            font-size: 0.9rem;
        }
        .ydone-pill {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.15rem 0.55rem;
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 0.02em;
        }
        .ydone-pill.is-live {
            background: #ecfdf5;
            color: #15803d;
        }
        .ydone-pill.is-deleted {
            background: #fef2f2;
            color: #b91c1c;
        }
        .ydone-table td {
            vertical-align: middle;
        }
        .ydone-title {
            font-weight: 600;
            color: #0f172a;
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
                            <li class="breadcrumb-item"><a href="{{ route('tasks.summary') }}">Task Summary</a></li>
                            <li class="breadcrumb-item active">Y Done</li>
                        </ol>
                    </div>
                    <h4 class="page-title mb-1">
                        Yesterday done
                        @if (!empty($focusUser))
                            — {{ $focusUser->name }}
                        @endif
                    </h4>
                    <p class="ydone-page-meta mb-0">
                        {{ $window['label'] ?? '' }} ({{ \App\Support\TaskBusinessTime::shortLabel() }})
                        · {{ count($tasks) }} task{{ count($tasks) === 1 ? '' : 's' }}
                    </p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped table-bordered mb-0 ydone-table">
                                <thead class="table-light">
                                    <tr>
                                        <th>Assignee</th>
                                        <th>Task</th>
                                        <th>Completed</th>
                                        <th>Deleted</th>
                                        <th>Deleted by</th>
                                        <th>Deleted at</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($tasks as $task)
                                        <tr>
                                            <td>{{ $task['assignee'] }}</td>
                                            <td class="ydone-title">{{ $task['title'] }}</td>
                                            <td>{{ $task['completed_at'] !== '' ? $task['completed_at'] : '—' }}</td>
                                            <td>
                                                @if (!empty($task['deleted']))
                                                    <span class="ydone-pill is-deleted">Deleted</span>
                                                @else
                                                    <span class="ydone-pill is-live">Not deleted</span>
                                                @endif
                                            </td>
                                            <td>{{ !empty($task['deleted']) ? ($task['deleted_by'] !== '' ? $task['deleted_by'] : '—') : '—' }}</td>
                                            <td>{{ !empty($task['deleted']) && $task['deleted_at'] !== '' ? $task['deleted_at'] : '—' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">No tasks were completed yesterday.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
