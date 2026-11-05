{{--
    Red warning triangle shown beside an executive's name while the fraud checkup has open alerts.
    Usage: @include('attendance.partials.fraud-alert', ['alert' => $row['fraud_alert'] ?? null, 'href' => $row['detail_url']])
    Include once with ['alert' => null] on pages that also draw triangles from JavaScript, so the styles exist.
--}}
@once
<style>
    .fraud-tri {
        display: inline-flex; align-items: center; justify-content: center;
        gap: .2rem; min-width: 20px; height: 20px; margin-left: .4rem; vertical-align: middle;
        flex-shrink: 0; text-decoration: none; line-height: 1; cursor: pointer;
        filter: drop-shadow(0 1px 1px rgba(127, 29, 29, .35));
    }
    .fraud-tri svg { display: block; }
    .fraud-tri-count {
        min-width: 17px; height: 17px; padding: 0 .3rem; border-radius: 999px;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: .68rem; font-weight: 700; line-height: 1; color: #fff; background: #dc2626;
        font-variant-numeric: tabular-nums;
    }
    .fraud-tri.medium .fraud-tri-count, .fraud-tri.low .fraud-tri-count { background: #ea580c; }
    .fraud-tri:hover { transform: scale(1.18); }
    .fraud-tri.high { animation: fraudTriPulse 1.6s ease-in-out infinite; }
    @keyframes fraudTriPulse {
        0%, 100% { filter: drop-shadow(0 0 0 rgba(220, 38, 38, 0)); }
        50% { filter: drop-shadow(0 0 5px rgba(220, 38, 38, .85)); }
    }
</style>
@endonce
@if(!empty($alert) && \App\Support\AttendanceAccess::isPresident())
    @php $triHref = $href ?? '#'; $triNewTab = $triHref !== '' && $triHref[0] !== '#'; @endphp
    <a href="{{ $triHref }}" @if($triNewTab) target="_blank" rel="noopener noreferrer" @endif class="fraud-tri {{ $alert['level'] }}"
       title="{{ $alert['tooltip'] }}"
       aria-label="Fraud check: {{ $alert['count'] }} open alert(s), {{ $alert['level'] }} risk">
        <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 2.2 23.2 21.6H0.8z" fill="#dc2626" stroke="#991b1b" stroke-width="1.2" stroke-linejoin="round"/>
            <rect x="11" y="8.6" width="2" height="6.4" rx="1" fill="#fff"/>
            <circle cx="12" cy="17.9" r="1.2" fill="#fff"/>
        </svg>
        <span class="fraud-tri-count">{{ (int) $alert['count'] }}</span>
    </a>
@endif
