@extends('layouts.vertical', ['title' => 'Edit follow-up', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Edit follow-up',
        'sub_title' => $followUp->title,
    ])

    <div class="mb-2">
        <a href="{{ route('crm.follow-ups.show', $followUp) }}" class="btn btn-light btn-sm">← Back</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="post" action="{{ route('crm.follow-ups.update', $followUp) }}" class="row g-3">
                @csrf
                @method('PUT')
                <div class="col-md-6">
                    <label class="form-label">Customer ID</label>
                    <input type="number" class="form-control" value="{{ $followUp->customer_id }}" disabled>
                    <small class="text-muted">Customer cannot be changed here.</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Assignee</label>
                    <select name="assigned_user_id" class="form-select" required>
                        @foreach ($users as $u)
                            <option value="{{ $u->id }}" @selected(old('assigned_user_id', $followUp->assigned_user_id) == $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Title</label>
                    <input type="text" name="title" class="form-control" value="{{ old('title', $followUp->title) }}" required maxlength="255">
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3">{{ old('description', $followUp->description) }}</textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Type</label>
                    <select name="follow_up_type" class="form-select" required>
                        @foreach (['call', 'email', 'whatsapp', 'meeting', 'sms', 'other'] as $t)
                            <option value="{{ $t }}" @selected(old('follow_up_type', $followUp->follow_up_type) === $t)>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-select" required>
                        @foreach (['low', 'medium', 'high'] as $p)
                            <option value="{{ $p }}" @selected(old('priority', $followUp->priority) === $p)>{{ $p }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" required>
                        @foreach (['pending', 'completed', 'postponed', 'cancelled'] as $s)
                            <option value="{{ $s }}" @selected(old('status', $followUp->status) === $s)>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Scheduled at</label>
                    <input type="datetime-local" name="scheduled_at" class="form-control"
                           value="{{ old('scheduled_at', optional($followUp->scheduled_at)->format('Y-m-d\TH:i')) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Reminder at</label>
                    <input type="datetime-local" name="reminder_at" class="form-control"
                           value="{{ old('reminder_at', optional($followUp->reminder_at)->format('Y-m-d\TH:i')) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Next follow-up at</label>
                    <input type="datetime-local" name="next_follow_up_at" class="form-control"
                           value="{{ old('next_follow_up_at', optional($followUp->next_follow_up_at)->format('Y-m-d\TH:i')) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Outcome</label>
                    <select name="outcome" class="form-select">
                        <option value="">—</option>
                        @php
                            $outcomes = [
                                App\Models\Crm\FollowUp::OUTCOME_INTERESTED,
                                App\Models\Crm\FollowUp::OUTCOME_NOT_INTERESTED,
                                App\Models\Crm\FollowUp::OUTCOME_CALLBACK,
                                App\Models\Crm\FollowUp::OUTCOME_CONVERTED,
                            ];
                        @endphp
                        @foreach ($outcomes as $o)
                            <option value="{{ $o }}" @selected(old('outcome', $followUp->outcome) === $o)>{{ $o }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>

            <hr class="my-4">
            <form method="post" action="{{ route('crm.follow-ups.destroy', $followUp) }}" onsubmit="return confirm('Delete this follow-up?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger">Delete follow-up</button>
            </form>
        </div>
    </div>
@endsection
