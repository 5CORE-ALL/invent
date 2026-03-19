@extends('layouts.vertical', ['title' => 'Daily Activity Report (DAR)', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        .dar-btn-submit {
            background: #dc3545;
            border-color: #dc3545;
            color: #fff;
        }

        .dar-btn-submit:hover:not(:disabled) {
            background: #bb2d3b;
            border-color: #b02a37;
            color: #fff;
        }

        .dar-section-title {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #6c757d;
            margin-top: 1rem;
            margin-bottom: .5rem;
            font-weight: 600;
        }

        .dar-section-title:first-child {
            margin-top: 0;
        }
    </style>
@endsection

@section('content')
    {{-- // TODO: Fetch channels via API --}}
    {{-- // TODO: Submit DAR via API --}}
    {{-- // TODO: Replace chart data with API response --}}

    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">DAR — Daily Activity Report</h4>
                <p class="text-muted small mb-0">Submit between <strong>4:00 PM – 5:00 PM California Time</strong> (PST/PDT).</p>
            </div>
            <div class="text-end small">
                <span id="darClockLa" class="badge bg-secondary">—</span>
                <div id="darWindowHint" class="mt-1 text-muted"></div>
            </div>
        </div>

        <div class="alert alert-info py-2 small mb-3" role="status">
            <i class="mdi mdi-information-outline me-1"></i>
            One submission per channel per day. Use the red button for each channel during the submission window.
        </div>

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>DAR submission trends</strong>
                <span class="small text-muted">Last 30 days · submissions per day + avg submit time (hr, LA)</span>
            </div>
            <div class="card-body" style="max-height: 360px;">
                <canvas id="darTrendChart"></canvas>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <strong>Channels</strong>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Channel name</th>
                            <th>Last submitted</th>
                            <th>Submission status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($channelRows as $row)
                            <tr data-channel-id="{{ $row['id'] }}" data-submitted="{{ $row['submitted_today'] ? '1' : '0' }}">
                                <td class="fw-medium">{{ $row['name'] }}</td>
                                <td>{{ $row['last_submitted'] }}</td>
                                <td>
                                    @if ($row['status'] === 'submitted')
                                        <span class="badge bg-success">Submitted</span>
                                    @else
                                        <span class="badge bg-danger">Pending</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if ($row['id'] <= 0)
                                        <span class="text-muted small">No channel id</span>
                                    @else
                                        <button type="button"
                                            class="btn btn-sm dar-btn-submit dar-open-modal"
                                            data-channel-id="{{ $row['id'] }}"
                                            data-channel-name="{{ e($row['name']) }}"
                                            data-submitted-today="{{ $row['submitted_today'] ? '1' : '0' }}"
                                            data-bs-toggle="tooltip"
                                            title="Submit between 4–5 PM California Time">
                                            Submit DAR
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No active channels.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="darModal" tabindex="-1" aria-labelledby="darModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="darModalLabel">Submit Daily Activity Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="darFormAlert" class="alert alert-danger d-none"></div>
                    <div id="darFormInfo" class="alert alert-warning d-none small"></div>

                    <form id="darForm">
                        @csrf
                        <input type="hidden" name="channel_id" id="dar_channel_id">
                        <input type="hidden" name="user_id" value="{{ auth()->id() }}">
                        <p class="small text-muted mb-2">Channel: <strong id="dar_channel_label"></strong></p>

                        <div class="dar-section-title">Messaging</div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="messaging_responded_queries" id="m1" value="1"><label class="form-check-label" for="m1">Responded to customer queries</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="messaging_followed_tickets" id="m2" value="1"><label class="form-check-label" for="m2">Followed up pending tickets</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="messaging_cleared_inbox" id="m3" value="1"><label class="form-check-label" for="m3">Cleared inbox / messages</label></div>

                        <div class="dar-section-title">Returns &amp; refunds</div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="rr_processed_returns" id="r1" value="1"><label class="form-check-label" for="r1">Processed return requests</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="rr_initiated_refunds" id="r2" value="1"><label class="form-check-label" for="r2">Initiated refunds</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="rr_verified_returns" id="r3" value="1"><label class="form-check-label" for="r3">Verified return cases</label></div>

                        <div class="dar-section-title">Escalations</div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="esc_handled" id="e1" value="1"><label class="form-check-label" for="e1">Handled escalations</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="esc_reported_critical" id="e2" value="1"><label class="form-check-label" for="e2">Reported critical issues</label></div>

                        <div class="dar-section-title">General</div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="gen_updated_crm" id="g1" value="1"><label class="form-check-label" for="g1">Updated CRM</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="gen_internal_coord" id="g2" value="1"><label class="form-check-label" for="g2">Internal team coordination</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="gen_other" id="g3" value="1"><label class="form-check-label" for="g3">Other</label></div>
                        <div class="ms-4 mb-2">
                            <input type="text" class="form-control form-control-sm" name="gen_other_text" id="gen_other_text" placeholder="Describe other activity">
                        </div>

                        <div class="mb-2 mt-3">
                            <label class="form-label">Comments</label>
                            <textarea class="form-control" name="comments" id="dar_comments" rows="3" placeholder="Optional notes"></textarea>
                        </div>
                        <div class="small text-muted">
                            Submission time is captured automatically when you submit.
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="darBtnSave">Submit report</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        (function() {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const storeUrl = @json(url('/dar'));
            const windowUrl = @json(url('/dar/window-status'));
            const chartLabels = @json($chartLabels);
            const chartCounts = @json($chartCounts);
            const chartAvgMinutes = @json($chartAvgMinutes);

            const ctx = document.getElementById('darTrendChart');
            if (ctx && typeof Chart !== 'undefined') {
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: chartLabels,
                        datasets: [{
                                label: 'Submissions',
                                data: chartCounts,
                                borderColor: '#2c6ed5',
                                backgroundColor: 'rgba(44, 110, 213, 0.1)',
                                fill: true,
                                tension: 0.25,
                                yAxisID: 'y',
                            },
                            {
                                label: 'Avg submit time (hr, LA)',
                                data: chartAvgMinutes,
                                borderColor: '#198754',
                                backgroundColor: 'transparent',
                                tension: 0.2,
                                spanGaps: true,
                                yAxisID: 'y1',
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        scales: {
                            y: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                                title: {
                                    display: true,
                                    text: 'Count'
                                },
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            },
                            y1: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                title: {
                                    display: true,
                                    text: 'Hour (local)'
                                },
                                grid: {
                                    drawOnChartArea: false
                                },
                                min: 15,
                                max: 17.5
                            }
                        }
                    }
                });
            }

            async function refreshWindowUi() {
                try {
                    const r = await fetch(windowUrl, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const j = await r.json();
                    document.getElementById('darClockLa').textContent = j.now_display || '—';
                    const hint = document.getElementById('darWindowHint');
                    if (j.in_window) {
                        hint.innerHTML = '<span class="text-success">Window open — you may submit.</span>';
                    } else {
                        hint.innerHTML = '<span class="text-danger">' + (j.message || 'Outside submission window.') + '</span>';
                    }
                    document.querySelectorAll('.dar-open-modal').forEach(btn => {
                        const submitted = btn.getAttribute('data-submitted-today') === '1';
                        btn.disabled = submitted || !j.in_window;
                        if (submitted) btn.title = 'Already submitted today for this channel.';
                        else if (!j.in_window) btn.title = 'Submit between 4–5 PM California Time';
                    });
                } catch (e) {
                    document.getElementById('darWindowHint').textContent = 'Could not check window status.';
                }
            }

            refreshWindowUi();
            setInterval(refreshWindowUi, 60000);

            document.querySelectorAll('.dar-open-modal').forEach(btn => {
                btn.addEventListener('click', function() {
                    if (this.disabled) return;
                    document.getElementById('dar_channel_id').value = this.getAttribute('data-channel-id');
                    document.getElementById('dar_channel_label').textContent = this.getAttribute('data-channel-name');
                    document.getElementById('darForm').reset();
                    document.getElementById('dar_channel_id').value = this.getAttribute('data-channel-id');
                    document.getElementById('darFormAlert').classList.add('d-none');
                    document.getElementById('darFormInfo').classList.add('d-none');
                    const modal = new bootstrap.Modal(document.getElementById('darModal'));
                    modal.show();
                    fetch(windowUrl).then(r => r.json()).then(j => {
                        const info = document.getElementById('darFormInfo');
                        if (!j.in_window) {
                            info.textContent = j.message;
                            info.classList.remove('d-none');
                            info.classList.replace('alert-warning', 'alert-danger');
                        } else {
                            info.textContent = 'You are inside the 4–5 PM California submission window.';
                            info.classList.remove('d-none');
                            info.classList.remove('alert-danger');
                            info.classList.add('alert-warning');
                        }
                    });
                });
            });

            document.getElementById('darBtnSave').addEventListener('click', async function() {
                const alertEl = document.getElementById('darFormAlert');
                alertEl.classList.add('d-none');

                const fd = new FormData(document.getElementById('darForm'));
                const checks = ['messaging_responded_queries', 'messaging_followed_tickets', 'messaging_cleared_inbox',
                    'rr_processed_returns', 'rr_initiated_refunds', 'rr_verified_returns', 'esc_handled',
                    'esc_reported_critical', 'gen_updated_crm', 'gen_internal_coord', 'gen_other'
                ];
                let any = checks.some(name => fd.get(name) === '1');
                if (fd.get('gen_other') === '1' && (fd.get('gen_other_text') || '').trim()) any = true;
                if ((fd.get('comments') || '').trim()) any = true;
                if (!any) {
                    alertEl.textContent = 'Select at least one responsibility or enter comments.';
                    alertEl.classList.remove('d-none');
                    return;
                }

                try {
                    const res = await fetch(storeUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: fd
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.success) {
                        alertEl.textContent = data.message || 'Submission failed.';
                        alertEl.classList.remove('d-none');
                        return;
                    }
                    bootstrap.Modal.getInstance(document.getElementById('darModal')).hide();
                    if (typeof Swal !== 'undefined' && Swal.fire) {
                        Swal.fire({ icon: 'success', title: 'Submitted', text: data.message }).then(() => location.reload());
                    } else {
                        alert(data.message);
                        location.reload();
                    }
                } catch (e) {
                    alertEl.textContent = 'Network error.';
                    alertEl.classList.remove('d-none');
                }
            });

            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
        })();
    </script>
@endsection
