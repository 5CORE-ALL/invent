@extends('layouts.vertical', ['title' => '5 Core Bot — '.$target->name])

@section('content')
    <style>
        .bot-tr { max-width: 860px; margin: 0 auto; }
        .bot-tr__list { background: #fff; border: 1px solid #e8e8e8; border-radius: 8px; padding: 8px 0; }
        .bot-tr__msg { display: flex; gap: 10px; padding: 8px 16px; }
        .bot-tr__msg:hover { background: #f8f6fc; }
        .bot-tr__avatar { width: 36px; height: 36px; border-radius: 6px; object-fit: cover; background: #e6dcf7; flex-shrink: 0; }
        .bot-tr__avatar.is-bot { background: #fff; border-radius: 50%; }
        .bot-tr__meta { font-size: 12px; color: #616061; }
        .bot-tr__meta strong { color: #1d1c1d; font-size: 14px; margin-right: 6px; }
        .bot-tr__body { color: #1d1c1d; font-size: 15px; line-height: 1.45; word-break: break-word; }
        .bot-tr__empty { color: #616061; text-align: center; padding: 48px 16px; }
    </style>
    <div class="container-fluid py-3">
        <div class="bot-tr">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h3 class="mb-0">5 Core Bot — {{ $target->name }}</h3>
                    <p class="text-muted mb-0">Read-only view of this user's bot chat (last 300 messages).</p>
                </div>
                <a href="{{ route('tasks.summary') }}" class="btn btn-light">Back to Task Summary</a>
            </div>

            <div class="bot-tr__list">
                @forelse ($messages as $m)
                    <div class="bot-tr__msg" id="bot-msg-{{ $m['id'] }}">
                        <img class="bot-tr__avatar {{ $m['is_bot'] ? 'is-bot' : '' }}" src="{{ $m['avatar'] }}" alt="">
                        <div>
                            <div class="bot-tr__meta">
                                <strong>{{ $m['name'] }}</strong>{{ $m['created_full'] ?? $m['created_label'] }}
                            </div>
                            <div class="bot-tr__body">{!! $m['html'] !!}</div>
                            @if (! empty($m['attachment_url']))
                                <div class="mt-1"><a href="{{ $m['attachment_url'] }}" target="_blank" rel="noopener">{{ $m['attachment_name'] ?: 'Attachment' }}</a></div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="bot-tr__empty">This user has no bot chat messages yet.</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
