@extends('layouts.vertical', ['title' => $followUp->title, 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => $followUp->title,
        'sub_title' => optional($followUp->customer)->name,
    ])

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="mb-2 d-flex flex-wrap gap-2">
        <a href="{{ route('crm.follow-ups.index') }}" class="btn btn-light btn-sm">← Back to list</a>
        @if ($followUp->customer)
            <a href="{{ route('crm.customers.show', $followUp->customer) }}" class="btn btn-outline-secondary btn-sm">Customer</a>
        @endif
        <a href="{{ route('crm.follow-ups.edit', $followUp) }}" class="btn btn-outline-primary btn-sm">Edit</a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-body">
                    <p><strong>Status:</strong> {{ $followUp->status }}</p>
                    <p><strong>Type:</strong> {{ $followUp->follow_up_type }} · <strong>Priority:</strong> {{ $followUp->priority }}</p>
                    <p><strong>Assignee:</strong> {{ $followUp->assignedUser->name ?? '—' }}</p>
                    <p><strong>Scheduled:</strong> {{ optional($followUp->scheduled_at)->format('Y-m-d H:i') ?? '—' }}</p>
                    <p><strong>Reminder:</strong> {{ optional($followUp->reminder_at)->format('Y-m-d H:i') ?? '—' }}</p>
                    @if ($followUp->description)
                        <hr>
                        <div class="text-muted">{!! nl2br(e($followUp->description)) !!}</div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header">Status history</div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        @forelse ($followUp->statusHistories as $h)
                            <li class="mb-2">
                                <small class="text-muted">{{ $h->created_at->format('Y-m-d H:i') }}</small>
                                — {{ $h->old_status ?? '—' }} → <strong>{{ $h->new_status }}</strong>
                                @if ($h->changedByUser)
                                    ({{ $h->changedByUser->name }})
                                @endif
                            </li>
                        @empty
                            <li class="text-muted">No history.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">Change status</div>
                <div class="card-body">
                    <form method="post" action="{{ route('crm.follow-ups.change-status', $followUp) }}">
                        @csrf
                        <select name="status" class="form-select mb-2" required>
                            @foreach (['pending', 'completed', 'postponed', 'cancelled'] as $st)
                                <option value="{{ $st }}" @selected($followUp->status === $st)>{{ $st }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-primary w-100">Update status</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
