@extends('layouts.vertical', ['title' => 'Amz Sprice×CVR Auto Push', 'sidenav' => 'condensed'])

@section('css')
<style>
    .sprice-auto-wrap { max-width: 1100px; }
    .sprice-auto-hero {
        background: linear-gradient(135deg, #b45309 0%, #92400e 55%, #1e293b 100%);
        color: #fff;
        border-radius: 14px;
        padding: 1.5rem 1.75rem;
        margin-bottom: 1.25rem;
    }
    .sprice-auto-hero h3 { margin: 0 0 .35rem; font-weight: 700; }
    .sprice-auto-hero p { margin: 0; opacity: .9; }
    .sprice-step {
        display: flex;
        gap: .85rem;
        align-items: flex-start;
        padding: .9rem 1rem;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        background: #fff;
        margin-bottom: .65rem;
    }
    .sprice-step-num {
        width: 28px; height: 28px; border-radius: 50%;
        background: #b45309; color: #fff;
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: .85rem; flex-shrink: 0;
    }
    .btn-sprice-cvr {
        background: #ffd448; border-color: #e6bf2e; color: #1a1a1a; font-weight: 700;
    }
    .btn-sprice-cvr:hover { background: #f5c842; color: #1a1a1a; }
    .btn-push-amazon {
        background: #b45309; border-color: #b45309; color: #fff; font-weight: 600;
    }
    .btn-push-amazon:hover { background: #d97706; color: #fff; }
    #sprice-auto-output {
        white-space: pre-wrap;
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: 12px;
        max-height: 360px;
        overflow: auto;
        background: #0b1220;
        color: #d1e7dd;
        border-radius: 8px;
        padding: 1rem;
        display: none;
    }
    .meta-pill {
        display: inline-block;
        background: rgba(255,255,255,.15);
        border-radius: 999px;
        padding: .2rem .65rem;
        font-size: .8rem;
        margin-right: .35rem;
        margin-top: .5rem;
    }
</style>
@endsection

@section('content')
<div class="container-fluid sprice-auto-wrap py-3">
    <div class="sprice-auto-hero">
        <h3><i class="fas fa-robot me-2"></i>Amz Sprice×CVR Auto Push</h3>
        <p>Run price commands first, then Clear SPRICE → Apply % Sprice×CVR → Push (listing + min price) to Amz.</p>
        <span class="meta-pill"><i class="far fa-clock me-1"></i>{{ $scheduleLabel }}</span>
        <span class="meta-pill"><i class="fas fa-terminal me-1"></i>{{ $command }}</span>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h5 class="mb-3">Pipeline</h5>
                    <div class="sprice-step">
                        <div class="sprice-step-num">1</div>
                        <div>
                            <div class="fw-semibold">Run price commands</div>
                            <div class="text-muted small"><code>sync:amazon-prices</code> then <code>app:fetch-amazon-listings</code> — freshest Amz price + CVR inputs before Apply.</div>
                        </div>
                    </div>
                    <div class="sprice-step">
                        <div class="sprice-step-num">2</div>
                        <div>
                            <div class="fw-semibold">Clear SPRICE</div>
                            <div class="text-muted small">Remove existing SPRICE / SPFT / SROI fields for datasheet SKUs with price.</div>
                        </div>
                    </div>
                    <div class="sprice-step">
                        <div class="sprice-step-num">3</div>
                        <div>
                            <div class="fw-semibold">Apply % Sprice×CVR</div>
                            <div class="text-muted small">Same rule as tabulator (CVR L30 slabs × Down/Same/Up signed % of Amz price). Ads% is not used.</div>
                        </div>
                    </div>
                    <div class="sprice-step">
                        <div class="sprice-step-num">4</div>
                        <div>
                            <div class="fw-semibold">Push SPRICE to Amz</div>
                            <div class="text-muted small">Listings Items PATCH — listing price + minimum seller allowed price (Shopify push skipped).</div>
                        </div>
                    </div>

                    <hr>
                    <div class="row g-2 mt-1">
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Limit (optional)</label>
                            <input type="number" id="run-limit" class="form-control form-control-sm" min="1" placeholder="All SKUs">
                        </div>
                        <div class="col-md-8 d-flex align-items-end gap-2 flex-wrap">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="skip-clear">
                                <label class="form-check-label small" for="skip-clear">Skip clear</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="skip-push">
                                <label class="form-check-label small" for="skip-push">Skip push</label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <button type="button" id="btn-dry-run" class="btn btn-outline-secondary btn-sm"
                            title="Clear + Apply SPRICE in DB; does not push to Amz">
                            <i class="fas fa-eye"></i> Dry Run (Apply, no push)
                        </button>
                        <button type="button" id="btn-run-now" class="btn btn-push-amazon btn-sm">
                            <i class="fas fa-play"></i> Run Now (Price → Clear → Apply → Push)
                        </button>
                        <a href="{{ url('/amazon-tabulator-view') }}" class="btn btn-sprice-cvr btn-sm">
                            <i class="fas fa-percentage"></i> % Sprice×CVR (Amz)
                        </a>
                        <a href="{{ url('/cron-monitor') }}" class="btn btn-outline-dark btn-sm">
                            <i class="fas fa-heartbeat"></i> Cron Monitor
                        </a>
                    </div>
                    <div id="sprice-auto-status" class="small text-muted mt-2"></div>
                    <pre id="sprice-auto-output"></pre>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h5 class="mb-2">Active Rule</h5>
                    <p class="small text-muted mb-2">Shared key <code>ebay_sprice_cvr</code> (same as Amz / eBay tabulator modal).</p>
                    <ul class="small mb-0">
                        <li>Yellow end (low): {{ number_format((float)($rule['low_cvr'] ?? 3.5), 2) }}%</li>
                        <li>Mid (blue/green): {{ number_format((float)($rule['mid_cvr'] ?? 7), 2) }}%</li>
                        <li>High (pink after): {{ number_format((float)($rule['high_cvr'] ?? 13), 2) }}%</li>
                        <li>Trend tol: ±{{ number_format((float)($rule['trend_tolerance'] ?? 0.1), 3) }}%</li>
                    </ul>
                    <a href="{{ url('/amazon-tabulator-view') }}" class="btn btn-link btn-sm px-0 mt-2">Edit rule on Amz tabulator →</a>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="mb-2">Recent Runs</h5>
                    @if($lastRuns->isEmpty())
                        <p class="small text-muted mb-0">No monitored runs yet. Cron fires daily at 2:00 PM IST.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>When</th>
                                        <th>Status</th>
                                        <th>Expected</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($lastRuns as $run)
                                        <tr>
                                            <td class="small">{{ optional($run->started_at ?? $run->created_at)->timezone('Asia/Kolkata')->format('Y-m-d H:i') }} IST</td>
                                            <td class="small">{{ $run->status ?? '—' }}</td>
                                            <td class="small">{{ $run->expected_records ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card border-0 shadow-sm mt-3">
                <div class="card-body">
                    <h6 class="mb-2">CLI</h6>
                    <code class="small d-block mb-2">php artisan amazon:sprice-cvr-auto-push</code>
                    <code class="small d-block mb-2">php artisan amazon:sprice-cvr-auto-push --dry-run</code>
                    <div class="text-muted small mb-2">Always runs price refresh first (<code>sync:amazon-prices</code> + <code>app:fetch-amazon-listings</code>), then Clear + Apply. Dry-run skips Amz push. Use <code>--skip-price-refresh</code> to skip the price step.</div>
                    <code class="small d-block">php artisan amazon:sprice-cvr-auto-push --limit=10 --dry-run</code>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
(function() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const statusEl = document.getElementById('sprice-auto-status');
    const outEl = document.getElementById('sprice-auto-output');

    function payload(extra) {
        const limitVal = document.getElementById('run-limit').value;
        return Object.assign({
            skip_clear: document.getElementById('skip-clear').checked,
            skip_push: document.getElementById('skip-push').checked,
            limit: limitVal ? Number(limitVal) : null,
        }, extra || {});
    }

    function setBusy(busy) {
        document.getElementById('btn-dry-run').disabled = busy;
        document.getElementById('btn-run-now').disabled = busy;
    }

    function run(extra, confirmMsg) {
        if (confirmMsg && !confirm(confirmMsg)) return;

        setBusy(true);
        statusEl.textContent = 'Starting…';
        outEl.style.display = 'none';
        outEl.textContent = '';

        fetch(@json(route('amazon.sprice-cvr-auto.run')), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload(extra)),
        })
        .then(r => r.json().then(body => ({ ok: r.ok, body })))
        .then(({ ok, body }) => {
            statusEl.textContent = body.message || (ok ? 'Done' : 'Failed');
            if (body.output) {
                outEl.style.display = 'block';
                outEl.textContent = body.output;
            }
            if (!ok) statusEl.classList.add('text-danger');
            else statusEl.classList.remove('text-danger');
        })
        .catch(err => {
            statusEl.textContent = 'Request failed: ' + err.message;
            statusEl.classList.add('text-danger');
        })
        .finally(() => setBusy(false));
    }

    document.getElementById('btn-dry-run').addEventListener('click', function() {
        run({ dry_run: true },
            'Dry Run for Amz?\n\n' +
            '1) Price commands (sync + fetch listings)\n' +
            '2) Clear SPRICE in DB\n' +
            '3) Apply % Sprice×CVR in DB (S PRC will update after refresh)\n' +
            '4) Skip push to Amz\n\n' +
            'Prices are NOT sent to Amz.');
    });

    document.getElementById('btn-run-now').addEventListener('click', function() {
        run({ dry_run: false },
            'Run LIVE pipeline for Amz?\n\n' +
            '1) Price commands (sync:amazon-prices + fetch-amazon-listings)\n' +
            '2) Clear SPRICE\n' +
            '3) Apply % Sprice×CVR\n' +
            '4) Push to Amz (listing + min price)\n\n' +
            'This starts in the background and can take a long time.');
    });
})();
</script>
@endsection
