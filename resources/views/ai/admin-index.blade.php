@extends('layouts.vertical', ['title' => 'AI Chat Admin'])

@section('content')
@include('layouts.shared.page-title', ['page_title' => 'AI Support Admin', 'sub_title' => 'AI Admin'])

<ul class="nav nav-tabs nav-bordered mb-3" role="tablist">
    <li class="nav-item">
        <a href="{{ route('ai.admin.index') }}" class="nav-link {{ ($activeTab ?? 'chat') === 'chat' ? 'active' : '' }}">Chat History</a>
    </li>
    <li class="nav-item">
        <a href="{{ route('ai.admin.escalations') }}" class="nav-link {{ ($activeTab ?? '') === 'escalations' ? 'active' : '' }}">Escalations</a>
    </li>
    <li class="nav-item">
        <a href="{{ route('ai.admin.training') }}" class="nav-link {{ ($activeTab ?? '') === 'training' ? 'active' : '' }}">Training Queue</a>
    </li>
    <li class="nav-item">
        <a href="{{ route('ai.admin.files') }}" class="nav-link {{ ($activeTab ?? '') === 'files' ? 'active' : '' }}">Knowledge Files</a>
    </li>
</ul>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if(session('info'))
    <div class="alert alert-info">{{ session('info') }}</div>
@endif

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                @if(($activeTab ?? 'chat') === 'chat')
                    <h4 class="header-title">Recent questions & answers</h4>
                    <div class="table-responsive">
                        <table class="table table-centered mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>User</th>
                                    <th>Question</th>
                                    <th>AI Answer</th>
                                    <th>Helpful</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse(($questions ?? collect()) as $q)
                                <tr>
                                    <td>{{ $q->created_at->format('M j, Y H:i') }}</td>
                                    <td>{{ e($q->user->name ?? $q->user->email ?? '—') }}</td>
                                    <td><span title="{{ e($q->question) }}">{{ e(Str::limit($q->question, 60)) }}</span></td>
                                    <td><span title="{{ e($q->ai_answer) }}">{{ e(Str::limit($q->ai_answer, 80)) }}</span></td>
                                    <td>
                                        @if($q->helpful === true)<span class="badge bg-success">Yes</span>
                                        @elseif($q->helpful === false)<span class="badge bg-danger">No</span>
                                        @else<span class="text-muted">—</span>@endif
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">No questions yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if(isset($questions) && $questions->hasPages())
                        <div class="d-flex justify-content-end mt-3">{{ $questions->links() }}</div>
                    @endif
                @endif

                @if(($activeTab ?? '') === 'escalations')
                    <h4 class="header-title">Escalations</h4>
                    <div class="mb-2">
                        <a href="{{ route('ai.admin.escalations', ['status' => 'pending']) }}" class="btn btn-sm btn-outline-primary">Pending</a>
                        <a href="{{ route('ai.admin.escalations', ['status' => 'answered']) }}" class="btn btn-sm btn-outline-success">Answered</a>
                        <a href="{{ route('ai.admin.escalations') }}" class="btn btn-sm btn-outline-secondary">All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-centered mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>User</th>
                                    <th>Question</th>
                                    <th>Domain</th>
                                    <th>Senior</th>
                                    <th>Status</th>
                                    <th>Replied at</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse(($escalations ?? collect()) as $e)
                                <tr>
                                    <td>{{ $e->created_at->format('M j, Y H:i') }}</td>
                                    <td>{{ e($e->user->name ?? $e->user->email ?? '—') }}</td>
                                    <td><span title="{{ e($e->original_question) }}">{{ e(Str::limit($e->original_question, 50)) }}</span></td>
                                    <td>{{ e($e->domain) }}</td>
                                    <td>{{ e($e->assigned_senior_email) }}</td>
                                    <td><span class="badge bg-{{ $e->status === 'answered' ? 'success' : ($e->status === 'closed' ? 'secondary' : 'warning') }}">{{ $e->status }}</span></td>
                                    <td>{{ $e->answered_at ? $e->answered_at->format('M j, H:i') : '—' }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="7" class="text-center text-muted py-4">No escalations.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if(isset($escalations) && $escalations->hasPages())
                        <div class="d-flex justify-content-end mt-3">{{ $escalations->links() }}</div>
                    @endif
                @endif

                @if(($activeTab ?? '') === 'training')
                    <h4 class="header-title">Training queue (unapproved)</h4>
                    <div class="table-responsive">
                        <table class="table table-centered mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Question</th>
                                    <th>Answer</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse(($trainingLogs ?? collect()) as $log)
                                <tr>
                                    <td>{{ $log->created_at->format('M j, Y H:i') }}</td>
                                    <td><span title="{{ e($log->question) }}">{{ e(Str::limit($log->question, 60)) }}</span></td>
                                    <td><span title="{{ e($log->answer) }}">{{ e(Str::limit($log->answer, 80)) }}</span></td>
                                    <td>
                                        <form action="{{ route('ai.admin.training.approve', $log->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">No unapproved training logs.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if(isset($trainingLogs) && $trainingLogs->hasPages())
                        <div class="d-flex justify-content-end mt-3">{{ $trainingLogs->links() }}</div>
                    @endif
                @endif

                @if(($activeTab ?? '') === 'files')
                    <h4 class="header-title">Knowledge files</h4>
                    <div class="table-responsive">
                        <table class="table table-centered mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Original name</th>
                                    <th>Status</th>
                                    <th>Processed at</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse(($knowledgeFiles ?? collect()) as $f)
                                <tr>
                                    <td>{{ $f->created_at->format('M j, Y H:i') }}</td>
                                    <td>{{ e($f->original_name) }}</td>
                                    <td><span class="badge bg-{{ $f->status === 'processed' ? 'success' : ($f->status === 'failed' ? 'danger' : 'warning') }}">{{ $f->status }}</span></td>
                                    <td>{{ $f->processed_at ? $f->processed_at->format('M j, H:i') : '—' }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">No knowledge files.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if(isset($knowledgeFiles) && $knowledgeFiles->hasPages())
                        <div class="d-flex justify-content-end mt-3">{{ $knowledgeFiles->links() }}</div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
