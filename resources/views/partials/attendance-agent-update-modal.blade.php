{{-- Custom modal (does not depend on Bootstrap JS Modal API) --}}
@php
    $installed = $agent_installed_version ?? null;
    $latest = $agent_latest_version ?? config('attendance.agent_version', '1.0.0');
    $dl = $download_url ?? ($agent_download_url ?? route('attendance.agent.download'));
    $showAuto = !empty($agent_update_available);
@endphp
<style>
    .att-upd-modal {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 20000;
        align-items: center;
        justify-content: center;
        padding: 16px;
    }
    .att-upd-modal.is-open { display: flex; }
    .att-upd-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, 0.55);
    }
    .att-upd-dialog {
        position: relative;
        width: 100%;
        max-width: 440px;
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 24px 60px rgba(0,0,0,.28);
        padding: 1.35rem 1.4rem 1.2rem;
        z-index: 1;
    }
    .att-upd-dialog h5 {
        font-size: 1.1rem;
        font-weight: 700;
        margin: 0 0 .75rem;
        color: #0f172a;
    }
    .att-upd-close {
        position: absolute;
        top: 10px;
        right: 12px;
        border: 0;
        background: transparent;
        font-size: 1.4rem;
        line-height: 1;
        color: #64748b;
        cursor: pointer;
    }
    .att-upd-versions {
        display: flex;
        align-items: center;
        gap: .5rem;
        margin: .85rem 0 1rem;
        flex-wrap: wrap;
    }
    .att-upd-steps {
        margin: 0 0 1rem;
        padding-left: 1.1rem;
        color: #475569;
        font-size: .84rem;
        line-height: 1.45;
    }
    .att-upd-steps li { margin-bottom: .35rem; }
    .att-upd-actions {
        display: flex;
        gap: .5rem;
        justify-content: flex-end;
        flex-wrap: wrap;
    }
    body.att-upd-open { overflow: hidden; }
</style>

<div id="agentUpdateModal" class="att-upd-modal" aria-hidden="true" role="dialog" aria-labelledby="agentUpdateModalLabel"
     data-auto-open="{{ $showAuto ? '1' : '0' }}"
     data-snooze-key="att_agent_update_snooze_{{ $latest }}">
    <div class="att-upd-backdrop" data-att-upd-close></div>
    <div class="att-upd-dialog">
        <button type="button" class="att-upd-close" data-att-upd-close aria-label="Close">&times;</button>
        <h5 id="agentUpdateModalLabel">
            <i class="ri-download-cloud-2-line text-primary me-1"></i>
            Update 5Core Attendance
        </h5>
        <p class="mb-0" style="color:#334155;font-size:.9rem">
            A newer desktop app is available. This updates your <strong>existing</strong> install —
            it does <strong>not</strong> create a second app.
        </p>
        <div class="att-upd-versions">
            <span class="badge bg-secondary">Installed {{ $installed ? 'v'.$installed : 'older version' }}</span>
            <span class="text-muted">→</span>
            <span class="badge bg-primary">Latest v{{ $latest }}</span>
        </div>
        <ol class="att-upd-steps">
            <li>Quit the app from the <strong>system tray</strong> (right‑click → Quit).</li>
            <li>Download and run the installer.</li>
            <li>Keep the default install folder — do not choose a new path.</li>
            <li>Reopen the app; footer must show <strong>v{{ $latest }}</strong>.</li>
        </ol>
        <div class="att-upd-actions">
            <button type="button" class="btn btn-light" data-att-upd-close>Later</button>
            @if(!empty($dl))
                <a href="{{ $dl }}" class="btn btn-primary" id="attUpdDownloadBtn">
                    <i class="ri-download-cloud-line me-1"></i> Download update
                </a>
            @else
                <a href="{{ route('attendance.agent') }}" class="btn btn-primary">Go to download page</a>
            @endif
        </div>
    </div>
</div>

<script>
(function () {
    function ready(fn) {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
        else fn();
    }
    ready(function () {
        var modal = document.getElementById('agentUpdateModal');
        if (!modal) return;
        var snoozeKey = modal.getAttribute('data-snooze-key') || 'att_agent_update_snooze';

        function openModal() {
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('att-upd-open');
        }
        function closeModal(snooze) {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('att-upd-open');
            if (snooze) {
                try { localStorage.setItem(snoozeKey, String(Date.now() + 4 * 60 * 60 * 1000)); } catch (e) {}
            }
        }

        modal.querySelectorAll('[data-att-upd-close]').forEach(function (el) {
            el.addEventListener('click', function () { closeModal(true); });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal(true);
        });
        window.showAttendanceAgentUpdateModal = openModal;

        var auto = modal.getAttribute('data-auto-open') === '1';
        if (!auto) return;
        var snoozeUntil = 0;
        try { snoozeUntil = Number(localStorage.getItem(snoozeKey) || 0); } catch (e) {}
        if (Date.now() > snoozeUntil) {
            setTimeout(openModal, 250);
        }
    });
})();
</script>
