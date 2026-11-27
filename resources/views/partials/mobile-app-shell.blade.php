{{--
    Phone "app shell" for pages that opt in with:  @include('partials.mobile-app-shell')

    Phones only (<768px). Desktop is untouched.
      - Hides the desktop topbar (the fixed mobile app bar + bottom tab bar stay).
      - Hides the template page-title row (the app bar already shows the title).
      - Removes the stacked side padding so content runs edge to edge.
      - Tucks the global floating buttons (Penalty / Salary / KPI / Meeting) behind one launcher.
      - Prevents sideways page scroll.
--}}
<style>
    @media (max-width: 767.98px) {
        html, body { overflow-x: clip; max-width: 100%; }

        .navbar-custom { display: none !important; }
        body .content-page { padding-left: 0 !important; padding-right: 0 !important; }
        body .content-page .content { padding-top: 62px !important; background: #f6f7fb !important; }
        body .content-page .container-fluid { padding-left: 0 !important; padding-right: 0 !important; }
        body .content-page .container-fluid > .row { margin-left: 0 !important; margin-right: 0 !important; }
        body .content-page .container-fluid > .row > .col-12 { padding-left: 0 !important; padding-right: 0 !important; }
        .row:has(> .col-12 > .page-title-box) { display: none !important; }

        body:not(.app-shell-fabs-open) #penaltyFab,
        body:not(.app-shell-fabs-open) #salaryFab,
        body:not(.app-shell-fabs-open) #kpiFab,
        body:not(.app-shell-fabs-open) #mrqFab { display: none !important; }

        .app-shell-fab-launcher {
            position: fixed;
            right: 12px;
            bottom: calc(80px + env(safe-area-inset-bottom, 0px));
            z-index: 1041;
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 50%;
            background: #2c3e50;
            color: #fff;
            font-size: 20px;
            opacity: 0.92;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }
        body.app-shell-fabs-open .app-shell-fab-launcher { background: #dc3545; }
    }
</style>

<button type="button" class="app-shell-fab-launcher d-md-none" id="app-shell-fab-launcher" aria-expanded="false" title="Quick actions">
    <i class="mdi mdi-dots-grid"></i>
</button>
<script>
    (function () {
        var btn = document.getElementById('app-shell-fab-launcher');
        if (!btn) return;
        btn.addEventListener('click', function () {
            var open = document.body.classList.toggle('app-shell-fabs-open');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            var icon = btn.querySelector('i');
            icon.classList.toggle('mdi-dots-grid', !open);
            icon.classList.toggle('mdi-close', open);
        });
    })();
</script>
