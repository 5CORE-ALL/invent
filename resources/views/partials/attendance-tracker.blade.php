@php
    $attUser = auth()->user();
    $attInternal = $attUser && \App\Support\AttendanceAccess::isInternalEmployee($attUser);
@endphp
@if($attInternal)
<script src="{{ asset('js/attendance-tracker.js') }}?v=1"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.AttendanceTracker) {
        window.AttendanceTracker.init({
            baseUrl: @json(url('/attendance')),
            csrf: @json(csrf_token())
        });
    }
});
</script>
@endif
