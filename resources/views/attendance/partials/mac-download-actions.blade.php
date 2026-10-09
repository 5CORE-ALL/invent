@php
    $macDownloads = $mac_downloads ?? [];
@endphp
@if($macDownloads !== [])
    <div class="d-flex flex-column gap-3">
        @foreach($macDownloads as $mac)
            <div class="mac-download-slot">
                <a href="{{ $mac['url'] }}"
                   class="btn btn-dark da-download-btn da-mac-btn"
                   data-mac-download
                   data-filename="{{ $mac['filename'] }}"
                   download="{{ $mac['filename'] }}">
                    <i class="ri-apple-fill" style="font-size:1.2rem"></i>
                    <span>{{ $mac['label'] }}</span>
                </a>
                <div class="da-meta">
                    File: {{ $mac['filename'] }} · {{ $mac['format_label'] }} · {{ $mac['detail'] }} · {{ $mac['size_label'] }}
                </div>
                <div class="mac-dl-status" data-mac-status role="status" aria-live="polite"></div>
            </div>
        @endforeach
    </div>
@else
    <div class="da-unavailable">
        <i class="ri-information-line me-1"></i>
        The Mac build is not available yet. Ask IT to upload
        <strong>5Core-Attendance-Mac.dmg</strong>
        (one universal app for Apple Silicon M1–M4 and Intel) to the downloads folder.
    </div>
@endif
