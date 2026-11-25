{{--
    Floating "Salary" icon, shown on every page for logged-in users.

    Shows the logged-in user's OWN current salary (same figures as the Salary page: Salary PP + Incr from
    their latest payroll month), the future salary the company planned for them, a progress illustration
    (a rocket climbing toward the target) and a motivational message that changes every day.
    The salary is meant to grow by about 500-1000 per month based on performance.

    Only president@5core.com sees the extra "Set future salary" list and can set / clear it for any
    employee (enforced again on the server in SalaryFabController).

      GET  /salary-fab          (own salary; plus employee list for president@5core.com)
      POST /salary-fab/future   (president@5core.com only)

    Hide on a page with: @extends('layouts.vertical', [..., 'hideSalaryFab' => true])
--}}
@auth
@unless($hideSalaryFab ?? false)
<style>
    /* ---------- floating button ---------- */
    #salaryFab {
        position: fixed; right: 20px; bottom: 212px; z-index: 1040;
        width: 56px; height: 56px; border-radius: 20px; border: 0; padding: 0;
        background: linear-gradient(135deg, #8b5cf6 0%, #ec4899 60%, #f59e0b 100%); color: #fff;
        display: flex; flex-direction: column; align-items: center; justify-content: center; line-height: 1;
        box-shadow: 0 8px 22px rgba(236, 72, 153, .45), inset 0 1px 0 rgba(255,255,255,.35);
        cursor: pointer; transition: transform .18s ease, box-shadow .18s ease, border-radius .18s ease;
        animation: salBob 3.4s ease-in-out infinite;
    }
    #salaryFab:hover { transform: translateY(-4px) rotate(-4deg) scale(1.05); border-radius: 50%; box-shadow: 0 14px 28px rgba(139, 92, 246, .55); animation: none; }
    #salaryFab i { font-size: 21px; }
    #salaryFab .sal-fab-text { font-size: 8.5px; font-weight: 900; letter-spacing: .1em; margin-top: 2px; }
    #salaryFab::after { content: '✨'; position: absolute; top: -7px; right: -5px; font-size: 14px; animation: salTwinkle 2s ease-in-out infinite; }
    @keyframes salBob { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-4px); } }
    @keyframes salTwinkle { 0%,100% { opacity: 1; transform: scale(1) rotate(0); } 50% { opacity: .4; transform: scale(.7) rotate(25deg); } }
    @media (max-width: 991.98px) { #salaryFab { right: 12px; bottom: 266px; width: 50px; height: 50px; border-radius: 18px; } }
    @media (prefers-reduced-motion: reduce) { #salaryFab, #salaryFab::after { animation: none; } }

    /* ---------- modal shell ---------- */
    #salaryFabModal .modal-content { border-radius: 28px; border: 0; overflow: hidden; background: #f6f5fb; box-shadow: 0 30px 80px rgba(76, 29, 149, .35); }
    #salaryFabModal .modal-header {
        position: relative; overflow: hidden; border-bottom: 0; color: #fff; padding: 20px 22px 26px;
        background: #1e1b4b;
    }
    #salaryFabModal .modal-header::before, #salaryFabModal .modal-header::after {
        content: ''; position: absolute; border-radius: 50%; filter: blur(34px); opacity: .85; pointer-events: none;
    }
    #salaryFabModal .modal-header::before { width: 220px; height: 220px; left: -50px; top: -90px; background: #8b5cf6; }
    #salaryFabModal .modal-header::after { width: 200px; height: 200px; right: -40px; top: -60px; background: #ec4899; }
    #salaryFabModal .sal-head { position: relative; z-index: 1; flex: 1; min-width: 0; }
    #salaryFabModal .modal-title { font-weight: 900; font-size: 1.35rem; letter-spacing: -.02em; }
    #salaryFabModal .sal-sub { font-size: .85rem; opacity: .85; margin-top: 2px; }
    #salaryFabModal .btn-close { position: relative; z-index: 1; filter: brightness(0) invert(1); opacity: .9; }
    #salaryFabModal .modal-body { padding: 0 18px 8px; margin-top: -14px; position: relative; z-index: 2; }
    #salaryFabModal .modal-footer { border-top: 0; background: transparent; }
    #salaryFabModal .modal-footer .btn { border-radius: 14px; font-weight: 700; padding: 8px 22px; }

    /* ---------- progress hero ---------- */
    #salaryFabModal .sal-hero {
        position: relative; border-radius: 24px; overflow: hidden; color: #fff;
        background: linear-gradient(160deg, #1e1b4b 0%, #4c1d95 55%, #831843 120%);
        box-shadow: 0 14px 34px rgba(76, 29, 149, .3);
    }
    #salaryFabModal .sal-hero svg { display: block; width: 100%; height: auto; }
    #salaryFabModal .sal-hero-top { position: absolute; left: 16px; top: 14px; z-index: 2; }
    #salaryFabModal .sal-pct { font-size: 2.3rem; font-weight: 900; letter-spacing: -.03em; line-height: 1; }
    #salaryFabModal .sal-pct small { font-size: 1rem; font-weight: 800; opacity: .8; }
    #salaryFabModal .sal-pct-label { font-size: .7rem; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; opacity: .75; margin-top: 4px; }
    #salaryFabModal .sal-star { animation: salTwinkle 2.6s ease-in-out infinite; transform-box: fill-box; transform-origin: center; }
    #salaryFabModal .sal-daily {
        margin: 0; padding: 14px 18px 16px; display: flex; gap: 12px; align-items: flex-start;
        background: rgba(255,255,255,.08); border-top: 1px solid rgba(255,255,255,.12); backdrop-filter: blur(6px);
    }
    #salaryFabModal .sal-daily-emoji { font-size: 1.8rem; line-height: 1; }
    #salaryFabModal .sal-daily-tag { display: inline-block; font-size: .62rem; font-weight: 900; letter-spacing: .12em; text-transform: uppercase; padding: 2px 8px; border-radius: 999px; background: linear-gradient(90deg, #f59e0b, #ec4899); margin-bottom: 4px; }
    #salaryFabModal .sal-daily-text { font-size: .95rem; font-weight: 700; line-height: 1.35; }

    /* ---------- stat cards ---------- */
    #salaryFabModal .sal-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 12px; margin-top: 14px; }
    #salaryFabModal .sal-card { position: relative; border-radius: 22px; padding: 16px 18px; background: #fff; box-shadow: 0 6px 20px rgba(30, 27, 75, .07); overflow: hidden; }
    #salaryFabModal .sal-card.future { color: #fff; background: linear-gradient(135deg, #6366f1 0%, #a855f7 55%, #ec4899 100%); box-shadow: 0 10px 26px rgba(168, 85, 247, .35); }
    #salaryFabModal .sal-card.future::after { content: '🔮'; position: absolute; right: 12px; top: 8px; font-size: 1.6rem; opacity: .9; }
    #salaryFabModal .sal-card.current::after { content: '💸'; position: absolute; right: 12px; top: 8px; font-size: 1.6rem; }
    #salaryFabModal .sal-card.nextincr::after { content: '🎁'; position: absolute; right: 12px; top: 8px; font-size: 1.6rem; }
    #salaryFabModal .sal-card.nextincr .sal-card-value { color: #94a3b8; }
    #salaryFabModal .sal-card.nextincr.approved { background: linear-gradient(135deg, #ecfdf5, #d1fae5); box-shadow: 0 8px 22px rgba(16, 185, 129, .22); }
    #salaryFabModal .sal-card.nextincr.approved .sal-card-value { color: #047857; }
    #salaryFabModal .sal-card.nextincr.approved .sal-card-label,
    #salaryFabModal .sal-card.nextincr.approved .sal-card-sub { color: #065f46; }
    #salaryFabModal .sal-next-pill { display: inline-block; margin-top: 4px; font-size: .68rem; font-weight: 800; padding: 2px 8px; border-radius: 999px; background: #d1fae5; color: #047857; }
    #salaryFabModal .sal-card-label { font-size: .68rem; font-weight: 900; letter-spacing: .12em; text-transform: uppercase; color: #8b8aa8; }
    #salaryFabModal .sal-card.future .sal-card-label { color: rgba(255,255,255,.8); }
    #salaryFabModal .sal-card-value { font-size: 2rem; font-weight: 900; letter-spacing: -.03em; line-height: 1.2; font-variant-numeric: tabular-nums; color: #1e1b4b; margin: 4px 0; }
    #salaryFabModal .sal-card.current .sal-card-value { background: linear-gradient(90deg, #7c3aed, #ec4899); -webkit-background-clip: text; background-clip: text; color: transparent; }
    #salaryFabModal .sal-card.future .sal-card-value { color: #fff; }
    #salaryFabModal .sal-card-sub { font-size: .74rem; color: #8b8aa8; }
    #salaryFabModal .sal-card.future .sal-card-sub { color: rgba(255,255,255,.82); }

    /* performance tiles: DAR % + overdue */
    #salaryFabModal .sal-perf { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-top: 12px; }
    #salaryFabModal .sal-tile { background: #fff; border-radius: 20px; padding: 14px 16px; box-shadow: 0 6px 20px rgba(30, 27, 75, .07); display: flex; align-items: center; gap: 14px; }
    #salaryFabModal .sal-ring { --p: 0; --c: #ef4444; flex: none; width: 62px; height: 62px; border-radius: 50%; display: grid; place-items: center;
        background: conic-gradient(var(--c) calc(var(--p) * 1%), #ede9fe 0); position: relative; }
    #salaryFabModal .sal-ring::before { content: ''; position: absolute; inset: 7px; border-radius: 50%; background: #fff; }
    #salaryFabModal .sal-ring span { position: relative; font-weight: 900; font-size: .9rem; color: #1e1b4b; }
    #salaryFabModal .sal-ring.high { --c: #10b981; } #salaryFabModal .sal-ring.mid { --c: #f59e0b; } #salaryFabModal .sal-ring.low { --c: #ef4444; }
    #salaryFabModal .sal-badge-num { flex: none; width: 62px; height: 62px; border-radius: 20px; display: grid; place-items: center; font-size: 1.7rem; font-weight: 900; font-variant-numeric: tabular-nums; }
    #salaryFabModal .sal-badge-num.zero { background: #d1fae5; color: #047857; }
    #salaryFabModal .sal-badge-num.some { background: #fef3c7; color: #b45309; }
    #salaryFabModal .sal-badge-num.many { background: #fee2e2; color: #b91c1c; }
    #salaryFabModal .sal-tile-label { font-size: .68rem; font-weight: 900; letter-spacing: .12em; text-transform: uppercase; color: #8b8aa8; }
    #salaryFabModal .sal-tile-main { font-size: 1.05rem; font-weight: 900; color: #1e1b4b; line-height: 1.2; }
    #salaryFabModal .sal-tile-sub { font-size: .74rem; color: #8b8aa8; }
    html[data-bs-theme="dark"] #salaryFabModal .sal-tile { background: #1f1c3d; }
    html[data-bs-theme="dark"] #salaryFabModal .sal-ring::before { background: #1f1c3d; }
    html[data-bs-theme="dark"] #salaryFabModal .sal-ring span,
    html[data-bs-theme="dark"] #salaryFabModal .sal-tile-main { color: #f1efff; }
    @media (max-width: 420px) { #salaryFabModal .sal-perf { grid-template-columns: 1fr; } }

    #salaryFabModal .sal-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
    #salaryFabModal .sal-chip { font-size: .76rem; font-weight: 800; padding: 6px 12px; border-radius: 999px; background: #fff; color: #4c1d95; box-shadow: 0 3px 10px rgba(30, 27, 75, .06); }
    #salaryFabModal .sal-chip.hot { background: #fff1f7; color: #be185d; }
    #salaryFabModal .sal-chip.ok { background: #ecfdf5; color: #047857; }

    /* ---------- president editor ---------- */
    #salaryFabModal .sal-setter { margin-top: 22px; }
    #salaryFabModal .sal-section-label { font-size: .72rem; font-weight: 900; text-transform: uppercase; letter-spacing: .12em; color: #6d28d9; margin: 0 0 10px; }
    #salaryFabModal .sal-search { border-radius: 14px; border: 2px solid transparent; background: #fff; box-shadow: 0 4px 14px rgba(30,27,75,.06); padding: 9px 14px; }
    #salaryFabModal .sal-search:focus { border-color: #a78bfa; box-shadow: 0 0 0 4px rgba(167, 139, 250, .25); }
    #salaryFabModal .sal-list { max-height: 330px; overflow-y: auto; margin-top: 10px; padding: 2px 4px 4px 2px; display: flex; flex-direction: column; gap: 8px; }
    #salaryFabModal .sal-row { display: flex; align-items: center; gap: 12px; background: #fff; border-radius: 18px; padding: 10px 12px; box-shadow: 0 3px 10px rgba(30,27,75,.05); flex-wrap: wrap; }
    #salaryFabModal .sal-avatar { width: 40px; height: 40px; border-radius: 14px; flex: none; display: grid; place-items: center; color: #fff; font-weight: 900; font-size: .95rem; }
    #salaryFabModal .sal-row-main { flex: 1 1 130px; min-width: 0; }
    #salaryFabModal .sal-row-name { font-weight: 800; color: #1e1b4b; font-size: .9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    #salaryFabModal .sal-row-cur { font-size: .75rem; color: #8b8aa8; font-variant-numeric: tabular-nums; }
    #salaryFabModal .sal-mini { height: 5px; border-radius: 99px; background: #ede9fe; margin-top: 5px; overflow: hidden; }
    #salaryFabModal .sal-mini > i { display: block; height: 100%; border-radius: 99px; background: linear-gradient(90deg, #8b5cf6, #ec4899); transition: width .6s ease; }
    #salaryFabModal .sal-row-edit { display: flex; align-items: center; gap: 8px; margin-left: auto; }
    #salaryFabModal .sal-amt { display: flex; align-items: center; background: #f3f0ff; border-radius: 12px; padding: 0 10px; }
    #salaryFabModal .sal-amt span { font-size: .75rem; font-weight: 800; color: #7c3aed; white-space: nowrap; }
    #salaryFabModal .sal-amt input { width: 92px; border: 0; background: transparent; text-align: right; font-weight: 800; color: #1e1b4b; padding: 8px 4px; outline: none; font-variant-numeric: tabular-nums; }
    #salaryFabModal .sal-amt:focus-within { box-shadow: 0 0 0 3px rgba(167, 139, 250, .4); }
    #salaryFabModal .sal-save { border: 0; border-radius: 12px; padding: 8px 14px; font-weight: 800; font-size: .8rem; color: #fff; background: linear-gradient(135deg, #8b5cf6, #ec4899); transition: transform .12s ease, opacity .12s ease; }
    #salaryFabModal .sal-save:hover { transform: scale(1.05); }
    #salaryFabModal .sal-save:disabled { opacity: .55; transform: none; }
    #salaryFabModal .sal-saved { font-size: .72rem; font-weight: 800; color: #059669; min-width: 48px; }
    #salaryFabModal .sal-hint-small { font-size: .74rem; color: #8b8aa8; margin-top: 8px; }
    #salaryFabModal .sal-hidden { display: none !important; }
    #salaryFabModal .sal-error { border-radius: 14px; font-size: .85rem; margin-bottom: 10px; }

    /* ---------- dark theme ---------- */
    html[data-bs-theme="dark"] #salaryFabModal .modal-content { background: #14122b; }
    html[data-bs-theme="dark"] #salaryFabModal .sal-card:not(.future),
    html[data-bs-theme="dark"] #salaryFabModal .sal-row,
    html[data-bs-theme="dark"] #salaryFabModal .sal-chip:not(.hot):not(.ok),
    html[data-bs-theme="dark"] #salaryFabModal .sal-search { background: #1f1c3d; color: #e9e7ff; }
    html[data-bs-theme="dark"] #salaryFabModal .sal-row-name,
    html[data-bs-theme="dark"] #salaryFabModal .sal-amt input { color: #f1efff; }
    html[data-bs-theme="dark"] #salaryFabModal .sal-amt { background: #2a2650; }
    html[data-bs-theme="dark"] #salaryFabModal .sal-chip { color: #c4b5fd; }
    html[data-bs-theme="dark"] #salaryFabModal .sal-section-label { color: #c4b5fd; }

    @media (max-width: 575.98px) {
        #salaryFabModal .modal-content { border-radius: 0; }
        #salaryFabModal .sal-row-edit { width: 100%; margin-left: 0; }
        #salaryFabModal .sal-amt { flex: 1; }
        #salaryFabModal .sal-amt input { width: 100%; }
    }
    @media (prefers-reduced-motion: reduce) { #salaryFabModal .sal-star { animation: none; } }
</style>

<button type="button" id="salaryFab" data-bs-toggle="modal" data-bs-target="#salaryFabModal" title="My salary" aria-label="Open my salary">
    <i class="ri-wallet-3-line" aria-hidden="true"></i>
    <span class="sal-fab-text">SALARY</span>
</button>

<div class="modal fade" id="salaryFabModal" tabindex="-1" aria-hidden="true" aria-labelledby="salaryFabTitle">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <div class="sal-head">
                    <h5 class="modal-title mb-0" id="salaryFabTitle">My Salary 💰</h5>
                    <div class="sal-sub" id="salGreeting">loading your glow-up…</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger py-2 sal-error sal-hidden" id="salError" role="alert"></div>

                {{-- Progress illustration + daily message --}}
                <div class="sal-hero">
                    <div class="sal-hero-top">
                        <div class="sal-pct"><span id="salPct">0</span><small>%</small></div>
                        <div class="sal-pct-label" id="salPctLabel">to your goal</div>
                    </div>
                    <svg viewBox="0 0 400 200" role="img" aria-label="Progress toward your future salary" id="salSvg">
                        <defs>
                            <linearGradient id="salTrail" x1="0" y1="0" x2="1" y2="0">
                                <stop offset="0" stop-color="#22d3ee"/><stop offset=".5" stop-color="#a78bfa"/><stop offset="1" stop-color="#f472b6"/>
                            </linearGradient>
                            <radialGradient id="salMoon" cx=".35" cy=".35" r=".8">
                                <stop offset="0" stop-color="#fff7d6"/><stop offset="1" stop-color="#fbbf24"/>
                            </radialGradient>
                            <filter id="salGlow" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="3.2" result="b"/><feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
                        </defs>
                        {{-- sky --}}
                        <circle cx="352" cy="46" r="17" fill="url(#salMoon)" opacity=".9"/>
                        <circle cx="62" cy="48" r="1.6" fill="#fff" class="sal-star"/>
                        <circle cx="132" cy="26" r="1.2" fill="#fff" class="sal-star" style="animation-delay:.6s"/>
                        <circle cx="228" cy="38" r="1.8" fill="#fff" class="sal-star" style="animation-delay:1.2s"/>
                        <circle cx="300" cy="22" r="1.2" fill="#fff" class="sal-star" style="animation-delay:.3s"/>
                        <circle cx="40" cy="104" r="1.2" fill="#fff" class="sal-star" style="animation-delay:1.8s"/>
                        <circle cx="170" cy="70" r="1.1" fill="#fff" class="sal-star" style="animation-delay:.9s"/>
                        {{-- hills --}}
                        <path d="M0 200 L0 168 Q70 140 140 164 T290 150 T400 160 L400 200 Z" fill="#2e1065" opacity=".75"/>
                        <path d="M0 200 L0 182 Q90 164 190 180 T400 176 L400 200 Z" fill="#1e1b4b" opacity=".9"/>
                        {{-- track + progress trail --}}
                        <path id="salTrack" d="M24 168 C 100 172, 140 92, 214 104 S 322 44, 372 52" fill="none" stroke="rgba(255,255,255,.22)" stroke-width="5" stroke-linecap="round" stroke-dasharray="1 11"/>
                        <path id="salTrail2" d="M24 168 C 100 172, 140 92, 214 104 S 322 44, 372 52" fill="none" stroke="url(#salTrail)" stroke-width="6" stroke-linecap="round" filter="url(#salGlow)"/>
                        {{-- milestones --}}
                        <g id="salMiles"></g>
                        {{-- start + goal --}}
                        <text x="24" y="190" font-size="9" font-weight="800" fill="rgba(255,255,255,.65)" text-anchor="middle" id="salStartLbl">START</text>
                        <g transform="translate(372 52)"><line x1="0" y1="0" x2="0" y2="-24" stroke="#fff" stroke-width="2" stroke-linecap="round"/><path d="M0 -24 L16 -19 L0 -13 Z" fill="#f472b6"/></g>
                        <text x="372" y="72" font-size="9" font-weight="800" fill="rgba(255,255,255,.8)" text-anchor="middle">GOAL</text>
                        {{-- rocket --}}
                        <g id="salRocket" transform="translate(24 168)"><text id="salRocketTxt" font-size="26" text-anchor="middle" dominant-baseline="central">🚀</text></g>
                    </svg>
                    <p class="sal-daily">
                        <span class="sal-daily-emoji" id="salDailyEmoji">✨</span>
                        <span><span class="sal-daily-tag">daily boost</span><br><span class="sal-daily-text" id="salDailyText"></span></span>
                    </p>
                </div>

                <div class="sal-cards" id="salCards"></div>
                <div class="sal-perf" id="salPerf"></div>
                <div class="sal-chips" id="salChips"></div>

                {{-- president@5core.com only --}}
                <div class="sal-setter sal-hidden" id="salSetter">
                    <div class="sal-section-label">👑 Set future salary · president only</div>
                    <input type="search" class="form-control sal-search" id="salSearch" placeholder="Search employee…" autocomplete="off">
                    <div class="sal-list" id="salRows"></div>
                    <div class="sal-hint-small">Leave the amount empty and tap Save to clear it. Employees see their future salary read-only, and progress is measured from the salary at the time you first set it.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        if (window.self !== window.top) { // never inside an embedded iframe
            var f = document.getElementById('salaryFab'); if (f) f.remove();
            return;
        }
        var URL_SHOW = @json(route('salaryFab.show'));
        var URL_SET = @json(route('salaryFab.setFuture'));
        var CSRF = @json(csrf_token());

        var modalEl = document.getElementById('salaryFabModal');
        var $ = function (id) { return document.getElementById(id); };
        var state = { me: null, employees: [], isSetter: false, stepMin: 500, stepMax: 1000 };
        var NS = 'http://www.w3.org/2000/svg';
        var anim = null;

        // One message per day (changes at local midnight, cycles through the whole list).
        var DAILY = [
            ['🚀', 'Small steps every day add up to a big raise. Keep climbing.'],
            ['🔥', 'Your effort today is next month\'s increment. Main character energy only.'],
            ['💎', 'Pressure makes diamonds, and diamonds get paid. Shine today.'],
            ['⚡', 'Do the work nobody sees. The results will be loud.'],
            ['🌱', 'Growth is quiet until it isn\'t. Keep watering your skills.'],
            ['🏆', 'Be so good they can\'t ignore your numbers.'],
            ['🎯', 'One focused hour beats five distracted ones. Lock in.'],
            ['💪', 'You\'re not behind, you\'re building. Stay consistent.'],
            ['✨', 'Performance is the plot twist that pays. Make it count.'],
            ['📈', 'Every ₹500 up starts with one great day. Make today that day.'],
            ['🧠', 'Learn one new thing today. Skills compound faster than salary.'],
            ['🌟', 'Your future salary is just a few great months away.'],
            ['🦾', 'Hard days build the best raises. Don\'t skip this one.'],
            ['🎧', 'Put on your playlist, get in the zone, and cook.'],
            ['🏁', 'Finish what you start. Closed tasks are the real flex.'],
            ['🌈', 'Bad day? Fine. Do one good thing anyway. Momentum is real.'],
            ['🛠️', 'Be the person people go to when it needs to get done.'],
            ['🧗', 'The climb is the flex. Enjoy the view from every step.'],
            ['🎉', 'Celebrate small wins, they\'re the receipts of your progress.'],
            ['🔑', 'Consistency is the cheat code. No shortcuts needed.'],
            ['🤝', 'Help a teammate win today. Good vibes get noticed.'],
            ['⏰', 'Show up on time, deliver on time, level up on time.'],
            ['💡', 'Bring one idea today that makes work better for everyone.'],
            ['🥇', 'Don\'t compare. Just beat yesterday\'s you.'],
            ['🌊', 'Ride the wave of momentum, don\'t wait for motivation.'],
            ['🧩', 'Quality over speed, then speed over excuses.'],
            ['💫', 'You\'re closer to your goal than you were this morning.'],
            ['🔭', 'Keep your eyes on the future salary and your hands on today\'s tasks.'],
            ['🍀', 'Luck is preparation meeting performance. Prep up.'],
            ['🛡️', 'Own your mistakes, fix them fast, and keep leveling.'],
            ['🌅', 'New day, fresh run. Make it your best one yet.']
        ];
        function dailyIndex() {
            var d = new Date();
            var dayNum = Math.floor(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()) / 86400000);
            return dayNum % DAILY.length;
        }

        function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
        function money(n, cur) {
            if (n === null || n === undefined || isNaN(Number(n))) return '—';
            cur = cur || { prefix: '₹', locale: 'en-IN' };
            return cur.prefix + Number(n).toLocaleString(cur.locale, { maximumFractionDigits: 0 });
        }
        function showError(msg) { var e = $('salError'); e.textContent = msg || ''; e.classList.toggle('sal-hidden', !msg); }
        function firstError(data) {
            if (data && data.errors) { var k = Object.keys(data.errors)[0]; return data.errors[k][0]; }
            return (data && data.message) || 'Something went wrong. Please try again.';
        }
        function call(method, url, body) {
            return fetch(url, {
                method: method, credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: body ? JSON.stringify(body) : undefined
            }).then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { return { ok: r.ok, data: j }; }); });
        }
        function colorFor(name) {
            var palette = [['#8b5cf6', '#6366f1'], ['#ec4899', '#f43f5e'], ['#06b6d4', '#3b82f6'], ['#f59e0b', '#ef4444'], ['#10b981', '#06b6d4'], ['#a855f7', '#ec4899']];
            var h = 0, s = String(name || '?');
            for (var i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) >>> 0;
            var p = palette[h % palette.length];
            return 'linear-gradient(135deg,' + p[0] + ',' + p[1] + ')';
        }

        // ---------- progress illustration ----------
        function placeRocket(trailLen, track, frac) {
            var len = trailLen * frac;
            var p = track.getPointAtLength(len);
            var p2 = track.getPointAtLength(Math.min(trailLen, len + 2));
            var p1 = track.getPointAtLength(Math.max(0, len - 2));
            // 🚀 points up-right (-45deg on screen); rotate it to follow the path
            var ang = Math.atan2(p2.y - p1.y, p2.x - p1.x) * 180 / Math.PI + 45;
            $('salRocket').setAttribute('transform', 'translate(' + p.x.toFixed(1) + ' ' + (p.y - 6).toFixed(1) + ')');
            $('salRocketTxt').setAttribute('transform', 'rotate(' + ang.toFixed(1) + ')');
            $('salTrail2').style.strokeDashoffset = String(trailLen - len);
        }
        function renderMilestones(track, trailLen, frac) {
            var g = $('salMiles'); g.innerHTML = '';
            [0.25, 0.5, 0.75].forEach(function (m) {
                var p = track.getPointAtLength(trailLen * m);
                var c = document.createElementNS(NS, 'circle');
                c.setAttribute('cx', p.x); c.setAttribute('cy', p.y); c.setAttribute('r', 4.5);
                c.setAttribute('fill', frac >= m ? '#fde68a' : '#312e81');
                c.setAttribute('stroke', 'rgba(255,255,255,.7)'); c.setAttribute('stroke-width', 1.5);
                g.appendChild(c);
            });
        }
        function renderProgress() {
            var m = state.me, track = $('salTrack'), trail = $('salTrail2');
            var trailLen = track.getTotalLength();
            trail.style.strokeDasharray = trailLen + ' ' + trailLen;
            var pct = (m && m.progress !== null && m.progress !== undefined) ? Number(m.progress) : 0;
            var target = Math.max(0, Math.min(100, pct)) / 100;
            // keep the rocket visible on the path even at 0%
            var visual = target === 0 ? 0.02 : target;

            renderMilestones(track, trailLen, target);
            $('salPctLabel').textContent = (m && m.future_salary !== null)
                ? (target >= 1 ? 'goal reached 🎉' : 'to your goal')
                : 'goal coming soon';

            if (anim) cancelAnimationFrame(anim);
            var t0 = null, dur = 1300, from = 0;
            var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            function step(ts) {
                if (t0 === null) t0 = ts;
                var k = reduce ? 1 : Math.min(1, (ts - t0) / dur);
                var e = 1 - Math.pow(1 - k, 3); // easeOutCubic
                var f = from + (visual - from) * e;
                placeRocket(trailLen, track, f);
                $('salPct').textContent = Math.round(target * 100 * e);
                if (k < 1) anim = requestAnimationFrame(step);
            }
            anim = requestAnimationFrame(step);
        }

        function renderDaily() {
            var d = DAILY[dailyIndex()];
            $('salDailyEmoji').textContent = d[0];
            $('salDailyText').textContent = d[1];
        }

        // ---------- cards ----------
        // Increment approved on the Salary → Increment page for next month.
        function nextIncrCard(m) {
            var cur = m.currency;
            if (m.next_increment !== null && m.next_increment !== undefined) {
                return '<div class="sal-card nextincr approved"><div class="sal-card-label">Next month increment</div>'
                    + '<div class="sal-card-value">+' + esc(money(m.next_increment, cur)) + '</div>'
                    + '<div class="sal-card-sub">✅ Approved for ' + esc(m.next_month_label || 'next month')
                    + (m.next_salary !== null ? ' · new salary ' + esc(money(m.next_salary, cur)) : '') + '</div></div>';
            }
            return '<div class="sal-card nextincr"><div class="sal-card-label">Next month increment</div>'
                + '<div class="sal-card-value">—</div>'
                + '<div class="sal-card-sub">Not approved yet' + (m.next_month_label ? ' for ' + esc(m.next_month_label) : '') + '. Keep performing 💪</div></div>';
        }

        // Performance tiles: own DAR % (last 30 days) and overdue task count.
        function renderPerf(p) {
            var box = $('salPerf');
            if (!p) { box.innerHTML = ''; return; }
            var pct = Math.max(0, Math.min(100, Number(p.dar_pct) || 0));
            var band = ['high', 'mid', 'low'].indexOf(p.dar_band) >= 0 ? p.dar_band : 'low';
            var od = Number(p.overdue) || 0;
            var odCls = od === 0 ? 'zero' : (od < 5 ? 'some' : 'many');
            var odMsg = od === 0 ? 'All clear, nice 🎉' : (od < 5 ? 'Close them to stay on track' : 'Clear these to protect your raise');
            var darMsg = pct > 90 ? 'Crushing it 🔥' : (pct >= 80 ? 'Almost there' : 'File daily to level up');
            box.innerHTML =
                '<div class="sal-tile"><div class="sal-ring ' + band + '" style="--p:' + pct + '"><span>' + Math.round(Number(p.dar_pct) || 0) + '%</span></div>'
                + '<div><div class="sal-tile-label">DAR · last 30 days</div>'
                + '<div class="sal-tile-main">' + esc(p.dar_count) + ' / ' + esc(p.dar_target) + ' days</div>'
                + '<div class="sal-tile-sub">' + darMsg + '</div></div></div>'
                + '<div class="sal-tile"><div class="sal-badge-num ' + odCls + '">' + od + '</div>'
                + '<div><div class="sal-tile-label">Overdue tasks</div>'
                + '<div class="sal-tile-main">' + (od === 1 ? '1 task' : od + ' tasks') + '</div>'
                + '<div class="sal-tile-sub">' + odMsg + '</div></div></div>';
        }

        function renderMine() {
            var m = state.me, box = $('salCards'), chips = $('salChips');
            if (!m) return;
            var cur = m.currency;
            var first = String(m.name || 'you').split(' ')[0];
            $('salGreeting').textContent = 'hey ' + first.toLowerCase() + ', here\'s your glow-up ✨';

            var sub = m.current_salary === null
                ? 'No salary recorded yet'
                : 'Salary PP ' + money(m.salary_pp, cur) + ' + Incr ' + money(m.increment, cur) + (m.month_label ? ' · ' + esc(m.month_label) : '');
            var futureSub = m.future_salary === null
                ? 'Not set by the company yet'
                : 'Set by the company' + (m.future_updated_at ? ' · ' + esc(m.future_updated_at) : '');

            box.innerHTML =
                '<div class="sal-card current"><div class="sal-card-label">Current salary</div>'
                + '<div class="sal-card-value">' + esc(money(m.current_salary, cur)) + '</div>'
                + '<div class="sal-card-sub">' + sub + '</div></div>'
                + '<div class="sal-card future"><div class="sal-card-label">Future salary</div>'
                + '<div class="sal-card-value">' + esc(money(m.future_salary, cur)) + '</div>'
                + '<div class="sal-card-sub">' + futureSub + '</div></div>'
                + nextIncrCard(m);

            renderPerf(m.performance);

            var c = '<span class="sal-chip">📈 +' + esc(money(state.stepMin, cur)) + ' to ' + esc(money(state.stepMax, cur)) + ' / month on performance</span>';
            if (m.gap !== null && m.gap > 0) {
                var est = m.months_min === m.months_max ? m.months_min + ' month' + (m.months_min === 1 ? '' : 's')
                    : m.months_min + '–' + m.months_max + ' months';
                c += '<span class="sal-chip hot">🎯 ' + esc(money(m.gap, cur)) + ' to go</span>';
                c += '<span class="sal-chip">⏳ ~' + esc(est) + ' at this pace</span>';
            } else if (m.gap !== null) {
                c += '<span class="sal-chip ok">🏆 You hit your planned salary. Legend.</span>';
            }
            chips.innerHTML = c;
        }

        // ---------- president editor ----------
        function initials(name) {
            var p = String(name || '?').trim().split(/\s+/);
            return ((p[0] || '?')[0] + (p.length > 1 ? p[p.length - 1][0] : '')).toUpperCase();
        }
        function renderRows() {
            var q = $('salSearch').value.trim().toLowerCase(), html = '';
            state.employees.forEach(function (e) {
                if (q && String(e.name || '').toLowerCase().indexOf(q) === -1) return;
                var pct = e.progress === null || e.progress === undefined ? 0 : e.progress;
                html += '<div class="sal-row" data-uid="' + e.user_id + '">'
                    + '<div class="sal-avatar" style="background:' + colorFor(e.name) + '">' + esc(initials(e.name)) + '</div>'
                    + '<div class="sal-row-main"><div class="sal-row-name">' + esc(e.name) + '</div>'
                    + '<div class="sal-row-cur">now ' + esc(money(e.current_salary, e.currency))
                    + (e.future_salary !== null ? ' · ' + Math.round(pct) + '% there' : '') + '</div>'
                    + (e.next_increment !== null && e.next_increment !== undefined
                        ? '<div class="sal-next-pill" title="Approved on the Increment page for ' + esc(e.next_month_label || 'next month') + '">🎁 next +' + esc(money(e.next_increment, e.currency)) + '</div>'
                        : '')
                    + (e.future_salary !== null ? '<div class="sal-mini"><i style="width:' + pct + '%"></i></div>' : '')
                    + '</div>'
                    + '<div class="sal-row-edit"><label class="sal-amt mb-0"><span>' + esc((e.currency || {}).prefix || '₹') + '</span>'
                    + '<input type="number" min="0" step="1" class="sal-in" aria-label="Future salary for ' + esc(e.name) + '" value="'
                    + (e.future_salary === null ? '' : Math.round(e.future_salary)) + '" placeholder="not set"></label>'
                    + '<button type="button" class="sal-save">Save</button><span class="sal-saved"></span></div>'
                    + '</div>';
            });
            $('salRows').innerHTML = html || '<div class="text-muted small p-2">No employees found.</div>';
        }

        function load() {
            showError('');
            renderDaily();
            return call('GET', URL_SHOW).then(function (res) {
                if (!res.ok || !res.data || res.data.success === false) { showError(firstError(res.data)); return; }
                state.me = res.data.me;
                state.isSetter = !!res.data.is_setter;
                state.employees = res.data.employees || [];
                state.stepMin = res.data.step_min || 500;
                state.stepMax = res.data.step_max || 1000;
                renderMine();
                renderProgress();
                $('salSetter').classList.toggle('sal-hidden', !state.isSetter);
                if (state.isSetter) renderRows();
            }).catch(function () { showError('Network error. Please try again.'); });
        }

        function save(row) {
            var uid = parseInt(row.getAttribute('data-uid'), 10);
            var val = row.querySelector('.sal-in').value.trim();
            var btn = row.querySelector('.sal-save'), tag = row.querySelector('.sal-saved');
            btn.disabled = true; tag.textContent = '';
            showError('');
            call('POST', URL_SET, { user_id: uid, future_salary: val === '' ? null : val }).then(function (res) {
                btn.disabled = false;
                if (!res.ok || !res.data || !res.data.success) { showError(firstError(res.data)); return; }
                var emp = res.data.employee;
                state.employees = state.employees.map(function (e) { return e.user_id === emp.user_id ? emp : e; });
                if (state.me && state.me.user_id === emp.user_id) { emp.performance = state.me.performance; state.me = emp; renderMine(); renderProgress(); }
                // refresh this row's progress bar without losing what the president typed in other rows
                var keep = {}; $('salRows').querySelectorAll('.sal-row').forEach(function (r) { keep[r.getAttribute('data-uid')] = r.querySelector('.sal-in').value; });
                renderRows();
                $('salRows').querySelectorAll('.sal-row').forEach(function (r) {
                    var id = r.getAttribute('data-uid');
                    if (id !== String(uid) && keep[id] !== undefined) r.querySelector('.sal-in').value = keep[id];
                    if (id === String(uid)) { var t = r.querySelector('.sal-saved'); t.textContent = '✓ saved'; setTimeout(function () { t.textContent = ''; }, 2500); }
                });
            }).catch(function () { btn.disabled = false; showError('Network error. Please try again.'); });
        }

        $('salRows').addEventListener('click', function (e) {
            var b = e.target.closest('.sal-save'); if (!b) return;
            save(b.closest('.sal-row'));
        });
        $('salRows').addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && e.target.classList.contains('sal-in')) { e.preventDefault(); save(e.target.closest('.sal-row')); }
        });
        $('salSearch').addEventListener('input', function () {
            var keep = {}; $('salRows').querySelectorAll('.sal-row').forEach(function (r) { keep[r.getAttribute('data-uid')] = r.querySelector('.sal-in').value; });
            renderRows();
            $('salRows').querySelectorAll('.sal-row').forEach(function (r) {
                var id = r.getAttribute('data-uid'); if (keep[id] !== undefined) r.querySelector('.sal-in').value = keep[id];
            });
        });
        modalEl.addEventListener('shown.bs.modal', load);
    })();
</script>
@endunless
@endauth
