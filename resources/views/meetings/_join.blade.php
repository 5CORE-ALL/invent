{{-- "Join Google Meet": only ever rendered with a link Google returned. Otherwise the real state is explained. --}}
@php $size = $size ?? 'btn-sm'; @endphp
@if($m->isCancelled())
    <span class="text-muted small">Cancelled</span>
@elseif($m->hasMeetLink())
    <a href="{{ $m->google_meet_url }}" target="_blank" rel="noopener noreferrer" class="btn {{ $size }} mt-join"><i class="ri-vidicon-fill me-1"></i>Join Google Meet</a>
@elseif($m->isGoogleMeet())
    <span class="badge bg-warning text-dark" title="{{ $m->google_sync_error }}">{{ $m->google_sync_status === 'failed' ? 'Google Meet link not created' : 'Google Meet link pending' }}</span>
@elseif($m->isEnded())
    <span class="text-muted small">Ended</span>
@else
    {{-- legacy meeting from before Google Meet: opening it moves it onto Google Meet --}}
    <a href="{{ route('meetings.room', $m) }}" class="btn {{ $size }} mt-join"><i class="ri-vidicon-fill me-1"></i>Switch to Google Meet</a>
@endif
