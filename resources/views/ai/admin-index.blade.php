@extends('layouts.vertical', ['title' => 'AI Chat Admin'])

@section('content')
@include('layouts.shared.page-title', ['page_title' => 'AI Chat History', 'sub_title' => 'AI Admin'])

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h4 class="header-title">Recent questions & answers</h4>
                <p class="text-muted mb-0">List of recent AI chat questions and feedback.</p>
            </div>
            <div class="card-body">
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
                            @forelse($questions as $q)
                            <tr>
                                <td>{{ $q->created_at->format('M j, Y H:i') }}</td>
                                <td>{{ e($q->user->name ?? $q->user->email ?? '—') }}</td>
                                <td><span title="{{ e($q->question) }}">{{ e(Str::limit($q->question, 60)) }}</span></td>
                                <td><span title="{{ e($q->ai_answer) }}">{{ e(Str::limit($q->ai_answer, 80)) }}</span></td>
                                <td>
                                    @if($q->helpful === true)
                                        <span class="badge bg-success">Yes</span>
                                    @elseif($q->helpful === false)
                                        <span class="badge bg-danger">No</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No questions yet.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($questions->hasPages())
                <div class="d-flex justify-content-end mt-3">
                    {{ $questions->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
