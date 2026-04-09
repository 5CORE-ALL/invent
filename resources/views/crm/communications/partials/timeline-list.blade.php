@php
    $tz = $tz ?? config('app.timezone');

    $categoryLabel = static function (string $type): string {
        return match ($type) {
            'follow_up' => 'Follow-up',
            'communication' => 'Communication',
            'status_change' => 'Status change',
            default => str_replace('_', ' ', $type),
        };
    };

    $categoryBadgeClass = static function (string $type): string {
        return match ($type) {
            'follow_up' => 'bg-primary',
            'communication' => 'bg-info text-dark',
            'status_change' => 'bg-warning text-dark',
            default => 'bg-secondary',
        };
    };

    $channelBadgeClass = static function (?string $channel): string {
        return match (strtolower((string) $channel)) {
            'call' => 'bg-primary',
            'email' => 'bg-info text-dark',
            'whatsapp' => 'bg-success',
            'meeting' => 'bg-warning text-dark',
            'sms' => 'bg-secondary',
            'other' => 'bg-dark',
            default => 'bg-light text-dark border',
        };
    };

    $markerClass = static function (string $type): string {
        return match ($type) {
            'follow_up' => 'crm-timeline-marker--follow-up',
            'communication' => 'crm-timeline-marker--communication',
            'status_change' => 'crm-timeline-marker--status',
            default => '',
        };
    };

    $channelForItem = static function (array $item): ?string {
        return match ($item['type']) {
            'follow_up' => $item['meta']['follow_up_type'] ?? null,
            'communication' => strtolower($item['title'] ?? ''),
            default => null,
        };
    };
@endphp

@if ($timeline->isEmpty())
    <p class="text-muted mb-0">No activity for this customer yet.</p>
@else
    <ul class="crm-timeline">
        @foreach ($timeline as $item)
            @php
                $type = $item['type'];
                $meta = $item['meta'] ?? [];
                $channel = $channelForItem($item);
                $at = $item['occurred_at'] ?? null;
            @endphp
            <li class="crm-timeline-item crm-timeline-item--{{ $type }}">
                <span class="crm-timeline-marker {{ $markerClass($type) }}" aria-hidden="true"></span>
                <div class="card crm-timeline-card shadow-sm mb-0">
                    <div class="card-body py-3">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <span class="badge {{ $categoryBadgeClass($type) }}">
                                {{ $categoryLabel($type) }}
                            </span>
                            @if ($channel)
                                <span class="badge {{ $channelBadgeClass($channel) }}">
                                    {{ ucfirst($channel) }}
                                </span>
                            @endif
                            @if ($type === 'follow_up' && ! empty($meta['status']))
                                <span class="badge bg-light text-dark border text-uppercase small">
                                    {{ $meta['status'] }}
                                </span>
                            @endif
                        </div>

                        <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                            <h6 class="mb-0 fw-semibold">{{ $item['title'] }}</h6>
                            @if ($at)
                                <div class="text-end small">
                                    <div class="text-muted text-nowrap" title="{{ $at->copy()->timezone($tz)->toIso8601String() }}">
                                        {{ $at->copy()->timezone($tz)->format('M j, Y') }}
                                        <span class="text-body-secondary">·</span>
                                        {{ $at->copy()->timezone($tz)->format('g:i A') }}
                                    </div>
                                    <div class="text-muted" style="font-size: 0.75rem;">
                                        {{ $at->copy()->timezone($tz)->diffForHumans() }}
                                    </div>
                                </div>
                            @endif
                        </div>

                        <dl class="row small mb-0 gy-1">
                            @if ($type === 'follow_up')
                                @if (! empty($meta['priority']))
                                    <dt class="col-sm-3 text-muted mb-0">Priority</dt>
                                    <dd class="col-sm-9 mb-0">{{ $meta['priority'] }}</dd>
                                @endif
                                @if (! empty($meta['assigned_to']))
                                    <dt class="col-sm-3 text-muted mb-0">Assignee</dt>
                                    <dd class="col-sm-9 mb-0">{{ $meta['assigned_to'] }}</dd>
                                @endif
                                @if (! empty($meta['follow_up_id']))
                                    <dt class="col-sm-3 text-muted mb-0">Record</dt>
                                    <dd class="col-sm-9 mb-0">
                                        <a href="{{ route('crm.follow-ups.show', $meta['follow_up_id']) }}">
                                            Open follow-up #{{ $meta['follow_up_id'] }}
                                        </a>
                                    </dd>
                                @endif
                            @elseif ($type === 'communication')
                                @if (! empty($meta['message_excerpt']))
                                    <dt class="col-sm-3 text-muted mb-0">Message</dt>
                                    <dd class="col-sm-9 mb-0">{{ $meta['message_excerpt'] }}</dd>
                                @endif
                                @if (! empty($meta['logged_by']))
                                    <dt class="col-sm-3 text-muted mb-0">Logged by</dt>
                                    <dd class="col-sm-9 mb-0">{{ $meta['logged_by'] }}</dd>
                                @endif
                                @if (! empty($meta['follow_up_id']))
                                    <dt class="col-sm-3 text-muted mb-0">Linked</dt>
                                    <dd class="col-sm-9 mb-0">
                                        <a href="{{ route('crm.follow-ups.show', $meta['follow_up_id']) }}">
                                            Follow-up #{{ $meta['follow_up_id'] }}
                                        </a>
                                    </dd>
                                @endif
                            @elseif ($type === 'status_change')
                                @if (! empty($meta['follow_up_title']))
                                    <dt class="col-sm-3 text-muted mb-0">Follow-up</dt>
                                    <dd class="col-sm-9 mb-0">{{ $meta['follow_up_title'] }}</dd>
                                @endif
                                @if (! empty($meta['changed_by']))
                                    <dt class="col-sm-3 text-muted mb-0">Changed by</dt>
                                    <dd class="col-sm-9 mb-0">{{ $meta['changed_by'] }}</dd>
                                @endif
                                @if (! empty($meta['follow_up_id']))
                                    <dt class="col-sm-3 text-muted mb-0">Record</dt>
                                    <dd class="col-sm-9 mb-0">
                                        <a href="{{ route('crm.follow-ups.show', $meta['follow_up_id']) }}">
                                            Open follow-up #{{ $meta['follow_up_id'] }}
                                        </a>
                                    </dd>
                                @endif
                            @endif
                        </dl>
                    </div>
                </div>
            </li>
        @endforeach
    </ul>
@endif
