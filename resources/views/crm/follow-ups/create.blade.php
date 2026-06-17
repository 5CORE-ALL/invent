@extends('layouts.vertical', ['title' => 'New follow-up', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'New follow-up',
        'sub_title' => 'Create',
    ])

    <div class="mb-2">
        <a href="{{ route('crm.follow-ups.index') }}" class="btn btn-light btn-sm">← Back</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="post" action="{{ route('crm.follow-ups.store') }}" class="row g-3">
                @csrf
                <div class="col-md-6">
                    <label class="form-label">Customer</label>
                    <select name="customer_id" class="form-select" required>
                        <option value="">—</option>
                        @foreach ($customers as $c)
                            <option value="{{ $c->id }}" @selected(old('customer_id') == $c->id)>{{ $c->name }} (#{{ $c->id }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Assignee</label>
                    <select name="assigned_user_id" class="form-select" required>
                        @foreach ($users as $u)
                            <option value="{{ $u->id }}" @selected(old('assigned_user_id', auth()->id()) == $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Title</label>
                    <input type="text" name="title" class="form-control" value="{{ old('title') }}" required maxlength="255">
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Type</label>
                    <select name="follow_up_type" class="form-select" required>
                        @foreach (['call', 'email', 'whatsapp', 'meeting', 'sms', 'other'] as $t)
                            <option value="{{ $t }}" @selected(old('follow_up_type') === $t)>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-select" required>
                        @foreach (['low', 'medium', 'high'] as $p)
                            <option value="{{ $p }}" @selected(old('priority', 'medium') === $p)>{{ $p }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status (optional)</label>
                    <select name="status" class="form-select">
                        <option value="">Default (pending)</option>
                        @foreach (['pending', 'completed', 'postponed', 'cancelled'] as $s)
                            <option value="{{ $s }}" @selected(old('status') === $s)>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Scheduled at</label>
                    <input type="datetime-local" name="scheduled_at" class="form-control" value="{{ old('scheduled_at') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Reminder at</label>
                    <input type="datetime-local" name="reminder_at" class="form-control" value="{{ old('reminder_at') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Next follow-up at</label>
                    <input type="datetime-local" name="next_follow_up_at" class="form-control" value="{{ old('next_follow_up_at') }}">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>
@endsection
