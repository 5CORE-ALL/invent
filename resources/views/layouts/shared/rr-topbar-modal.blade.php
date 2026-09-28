@auth
    <style>
        #rrTopbarModal .modal-content,
        #clrrTopbarModal .modal-content {
            border: 0;
            border-radius: 16px;
            overflow: hidden;
        }
        #rrTopbarModal .modal-header {
            background: linear-gradient(135deg, #6d28d9, #8b5cf6);
            color: #fff;
            border: 0;
        }
        #clrrTopbarModal .modal-header {
            background: linear-gradient(135deg, #0e7490, #06b6d4);
            color: #fff;
            border: 0;
        }
        #rrTopbarModal .btn-close,
        #clrrTopbarModal .btn-close { filter: invert(1); }
        #rrTopbarBody,
        #clrrTopbarBody {
            max-height: min(70vh, 640px);
            overflow: auto;
        }
        .rr-topbar-empty {
            text-align: center;
            color: #64748b;
            padding: 2.5rem 1rem;
        }
        .rr-topbar-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.75rem 0.9rem;
            margin-bottom: 0.65rem;
            background: #fff;
        }
        .rr-topbar-card__title {
            font-weight: 700;
            color: #0f172a;
        }
        .rr-topbar-card__desc {
            color: #475569;
            font-size: 0.86rem;
            margin-top: 0.25rem;
        }
        .rr-topbar-score {
            font-weight: 800;
            font-variant-numeric: tabular-nums;
        }
        .rr-topbar-check {
            display: flex;
            align-items: flex-start;
            gap: 0.55rem;
            padding: 0.4rem 0;
            border-top: 1px solid #f1f5f9;
        }
        .rr-topbar-check input { margin-top: 0.2rem; }
        .rr-topbar-check.is-checked .rr-topbar-check__title {
            color: #15803d;
            text-decoration: line-through;
        }
    </style>

    <div class="modal fade" id="rrTopbarModal" tabindex="-1" aria-labelledby="rrTopbarModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title mb-0" id="rrTopbarModalTitle">
                        <i class="ri-file-list-3-fill me-2"></i>My R&amp;R
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="rrTopbarBody">
                    <div class="rr-topbar-empty">Loading R&amp;R…</div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="clrrTopbarModal" tabindex="-1" aria-labelledby="clrrTopbarModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title mb-0" id="clrrTopbarModalTitle">
                        <i class="ri-checkbox-multiple-fill me-2"></i>My CL R&amp;R
                        <span class="rr-topbar-score ms-2" id="clrrTopbarScore"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="clrrTopbarBody">
                    <div class="rr-topbar-empty">Loading CL R&amp;R…</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof bootstrap === 'undefined') return;

            const userId = @json((int) auth()->id());
            const rrUrl = @json(route('tasks.designationRR.get'));
            const clrrUrl = @json(route('tasks.designationRR.checklist.get'));
            const progressUrl = @json(route('tasks.designationRR.checklist.progress'));
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            function escapeHtml(value) {
                const node = document.createElement('div');
                node.textContent = value == null ? '' : String(value);
                return node.innerHTML;
            }

            function paintRrButton(count) {
                const btn = document.getElementById('rrTopbarOpenBtn');
                if (!btn) return;
                count = Math.max(0, parseInt(count, 10) || 0);
                btn.classList.toggle('has-data', count > 0);
                btn.dataset.rrCount = String(count);
                const badge = btn.querySelector('.topbar-rr-btn__count');
                if (badge) badge.textContent = String(count);
                const title = count > 0
                    ? ('R&R — ' + count + ' item' + (count === 1 ? '' : 's'))
                    : 'R&R — no data';
                btn.title = title;
                btn.setAttribute('aria-label', title);
            }

            function paintClrrButton(count, percent) {
                const btn = document.getElementById('clrrTopbarOpenBtn');
                if (!btn) return;
                count = Math.max(0, parseInt(count, 10) || 0);
                percent = Math.max(0, parseInt(percent, 10) || 0);
                btn.classList.toggle('has-data', count > 0);
                btn.dataset.clrrCount = String(count);
                btn.dataset.clrrPercent = String(percent);
                const badge = btn.querySelector('.topbar-rr-btn__count');
                if (badge) badge.textContent = percent + '%';
                const title = count > 0 ? ('CL R&R — ' + percent + '%') : 'CL R&R — no data';
                btn.title = title;
                btn.setAttribute('aria-label', title);
                const score = document.getElementById('clrrTopbarScore');
                if (score) score.textContent = count > 0 ? (percent + '%') : '';
            }

            function emptyHtml(message) {
                return '<div class="rr-topbar-empty">' + escapeHtml(message) + '</div>';
            }

            const rrModalEl = document.getElementById('rrTopbarModal');
            const rrBody = document.getElementById('rrTopbarBody');
            const rrBtn = document.getElementById('rrTopbarOpenBtn');
            if (rrModalEl && rrBody && rrBtn) {
                const rrModal = new bootstrap.Modal(rrModalEl);
                rrBtn.addEventListener('click', function () {
                    rrBody.innerHTML = '<div class="rr-topbar-empty">Loading R&R…</div>';
                    rrModal.show();
                    const url = rrUrl + (rrUrl.indexOf('?') >= 0 ? '&' : '?') + 'user_id=' + encodeURIComponent(userId);
                    fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
                        .then(function (response) {
                            if (!response.ok) throw new Error('Could not load R&R');
                            return response.json();
                        })
                        .then(function (data) {
                            const items = Array.isArray(data.items) ? data.items : [];
                            paintRrButton(items.length);
                            if (!items.length) {
                                rrBody.innerHTML = emptyHtml(data.message || 'No R&R data for your designation yet.');
                                return;
                            }
                            rrBody.innerHTML = items.map(function (item) {
                                return '<article class="rr-topbar-card">'
                                    + '<div class="rr-topbar-card__title">' + escapeHtml(item.title) + '</div>'
                                    + (item.description ? '<div class="rr-topbar-card__desc">' + escapeHtml(item.description) + '</div>' : '')
                                    + '</article>';
                            }).join('');
                        })
                        .catch(function () {
                            rrBody.innerHTML = emptyHtml('Could not load your R&R.');
                        });
                });
            }

            const clrrModalEl = document.getElementById('clrrTopbarModal');
            const clrrBody = document.getElementById('clrrTopbarBody');
            const clrrBtn = document.getElementById('clrrTopbarOpenBtn');
            if (clrrModalEl && clrrBody && clrrBtn) {
                const clrrModal = new bootstrap.Modal(clrrModalEl);
                let clrrItems = [];

                function renderClrr(data) {
                    clrrItems = Array.isArray(data.items) ? data.items : [];
                    const overall = data.overall || {};
                    const count = parseInt(overall.count, 10) || clrrItems.reduce(function (sum, item) {
                        return sum + ((item.checkpoints && item.checkpoints.length) || 0);
                    }, 0);
                    paintClrrButton(count, overall.percent || 0);
                    if (data.needs_rr_seed || !clrrItems.length) {
                        clrrBody.innerHTML = emptyHtml('No CL R&R data for your designation yet.');
                        return;
                    }
                    clrrBody.innerHTML = clrrItems.map(function (item) {
                        const checkpoints = Array.isArray(item.checkpoints) ? item.checkpoints : [];
                        const rows = checkpoints.map(function (cp) {
                            return '<label class="rr-topbar-check' + (cp.checked ? ' is-checked' : '') + '">'
                                + '<input type="checkbox" data-checkpoint-id="' + escapeHtml(cp.id) + '"' + (cp.checked ? ' checked' : '') + '>'
                                + '<span><span class="rr-topbar-check__title">' + escapeHtml(cp.title) + '</span>'
                                + (cp.description ? '<div class="rr-topbar-card__desc">' + escapeHtml(cp.description) + '</div>' : '')
                                + '</span></label>';
                        }).join('');
                        const score = item.score && item.score.percent != null ? item.score.percent + '%' : '';
                        return '<article class="rr-topbar-card">'
                            + '<div class="d-flex justify-content-between gap-2"><div class="rr-topbar-card__title">' + escapeHtml(item.title) + '</div>'
                            + '<div class="rr-topbar-score">' + escapeHtml(score) + '</div></div>'
                            + rows
                            + '</article>';
                    }).join('');
                }

                clrrBtn.addEventListener('click', function () {
                    clrrBody.innerHTML = '<div class="rr-topbar-empty">Loading CL R&R…</div>';
                    clrrModal.show();
                    const url = clrrUrl + (clrrUrl.indexOf('?') >= 0 ? '&' : '?') + 'user_id=' + encodeURIComponent(userId);
                    fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
                        .then(function (response) {
                            if (!response.ok) throw new Error('Could not load CL R&R');
                            return response.json();
                        })
                        .then(renderClrr)
                        .catch(function () {
                            clrrBody.innerHTML = emptyHtml('Could not load your CL R&R.');
                        });
                });

                clrrBody.addEventListener('change', function (event) {
                    const input = event.target;
                    if (!input || !input.matches('input[type="checkbox"][data-checkpoint-id]')) return;
                    const checkpointId = parseInt(input.getAttribute('data-checkpoint-id'), 10);
                    const checked = !!input.checked;
                    input.disabled = true;
                    fetch(progressUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            user_id: userId,
                            designation_rr_checkpoint_id: checkpointId,
                            checked: checked
                        })
                    })
                        .then(function (response) {
                            if (!response.ok) throw new Error('Could not save');
                            return response.json();
                        })
                        .then(function () {
                            const url = clrrUrl + (clrrUrl.indexOf('?') >= 0 ? '&' : '?') + 'user_id=' + encodeURIComponent(userId);
                            return fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
                                .then(function (response) { return response.json(); })
                                .then(renderClrr);
                        })
                        .catch(function () {
                            input.checked = !checked;
                            input.disabled = false;
                        });
                });
            }
        });
    </script>
@endauth
