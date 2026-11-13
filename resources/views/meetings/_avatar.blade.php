@php
    /** @var \App\Models\User|null $user */
    $user = $user ?? null;
    $size = $size ?? 32;
    $photo = \App\Support\MeetingPresenter::avatarUrl($user);
@endphp
<span class="mt-av" style="width:{{ $size }}px;height:{{ $size }}px;font-size:{{ max(10, (int) round($size * .38)) }}px;background:{{ \App\Support\MeetingPresenter::colour($user?->name) }}" title="{{ $user?->name ?? 'Unknown' }}">{{ \App\Support\MeetingPresenter::initials($user?->name) }}@if($photo)<img src="{{ $photo }}" alt="{{ $user?->name }}" loading="lazy" onerror="this.remove()">@endif</span>
