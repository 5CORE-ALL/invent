<div id="mm-queue-status" class="card mb-3 border" data-slug="{{ $slug }}" data-status-url="{{ route('marketplace.queue.status', $slug) }}">
    <div class="card-body py-3">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
            <div>
                <strong class="small"><i class="ri-pulse-line"></i> Background sync status</strong>
                <div id="mm-queue-name" class="small text-muted"></div>
                <div id="mm-queue-worker" class="small mt-1 text-muted">Checking queue…</div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <span id="mm-queue-running" class="badge bg-primary-subtle text-primary border" style="display:none;">Running: 0</span>
                <span id="mm-queue-waiting" class="badge bg-success-subtle text-success border" style="display:none;">Ready: 0</span>
                <span id="mm-queue-delayed" class="badge bg-warning-subtle text-warning border" style="display:none;">Delayed: 0</span>
                <span id="mm-queue-failed" class="badge bg-danger-subtle text-danger border" style="display:none;">Failed (24h): 0</span>
            </div>
        </div>

        <div id="mm-queue-now" class="mb-2" style="display:none;">
            <div class="small fw-semibold text-primary mb-1">In progress</div>
            <ul id="mm-queue-now-list" class="small mb-0 ps-3"></ul>
        </div>
        <div id="mm-queue-ready" class="mb-2" style="display:none;">
            <div class="small fw-semibold text-success mb-1">Ready next</div>
            <ul id="mm-queue-ready-list" class="small mb-0 ps-3"></ul>
        </div>
        <div id="mm-queue-delayed-wrap" class="mb-2" style="display:none;">
            <div class="small fw-semibold text-warning mb-1">Delayed (will retry)</div>
            <ul id="mm-queue-delayed-list" class="small mb-0 ps-3"></ul>
        </div>

        <div id="mm-queue-linkmap" class="mt-2" style="display:none;">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span id="mm-queue-linkmap-msg" class="small text-muted">Link map sync…</span>
                <span id="mm-queue-linkmap-pct" class="small fw-semibold">0%</span>
            </div>
            <div class="progress" style="height: 14px;">
                <div id="mm-queue-linkmap-bar" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%;"></div>
            </div>
        </div>
        <div id="mm-queue-checked" class="small text-muted mt-2"></div>
    </div>
</div>

<script>
(function () {
    var panel = document.getElementById('mm-queue-status');
    if (!panel) return;

    var url = panel.getAttribute('data-status-url');
    var workerEl = document.getElementById('mm-queue-worker');
    var queueNameEl = document.getElementById('mm-queue-name');
    var runningBadge = document.getElementById('mm-queue-running');
    var waitingBadge = document.getElementById('mm-queue-waiting');
    var delayedBadge = document.getElementById('mm-queue-delayed');
    var failedBadge = document.getElementById('mm-queue-failed');
    var nowWrap = document.getElementById('mm-queue-now');
    var nowList = document.getElementById('mm-queue-now-list');
    var readyWrap = document.getElementById('mm-queue-ready');
    var readyList = document.getElementById('mm-queue-ready-list');
    var delayedWrap = document.getElementById('mm-queue-delayed-wrap');
    var delayedList = document.getElementById('mm-queue-delayed-list');
    var linkMapWrap = document.getElementById('mm-queue-linkmap');
    var linkMapMsg = document.getElementById('mm-queue-linkmap-msg');
    var linkMapPct = document.getElementById('mm-queue-linkmap-pct');
    var linkMapBar = document.getElementById('mm-queue-linkmap-bar');
    var checkedEl = document.getElementById('mm-queue-checked');
    var pollMs = 5000;
    var timer = null;

    function workerClass(state) {
        if (state === 'running') return 'text-primary';
        if (state === 'stalled') return 'text-danger';
        if (state === 'backlogged') return 'text-warning';
        return 'text-muted';
    }

    function fillList(ul, items, withDelay) {
        ul.innerHTML = (items || []).map(function (t) {
            var extra = '';
            if (withDelay && t.delay_human) {
                extra = ' <span class="text-muted">· retry in ' + t.delay_human + '</span>';
            }
            if (t.attempts > 1) {
                extra += ' <span class="text-muted">· attempt ' + t.attempts + '</span>';
            }
            return '<li>' + (t.label || t.job) + extra + '</li>';
        }).join('');
    }

    function render(data) {
        var counts = data.counts || {};
        var worker = data.worker || {};
        var linkMap = data.link_map;
        var nowRunning = data.now_running || [];
        var ready = data.ready || [];
        var delayedJobs = data.delayed_jobs || [];

        workerEl.textContent = worker.message || 'Queue status unknown.';
        workerEl.className = 'small mt-1 ' + workerClass(worker.state || 'idle');
        if (queueNameEl) {
            var label = data.marketplace_label || data.marketplace || '';
            queueNameEl.textContent = data.queue
                ? (label + ' worker · ' + data.queue)
                : '';
        }

        if ((counts.running || 0) > 0) {
            runningBadge.style.display = '';
            runningBadge.textContent = 'Running: ' + counts.running;
        } else {
            runningBadge.style.display = 'none';
        }

        if ((counts.waiting || 0) > 0) {
            waitingBadge.style.display = '';
            waitingBadge.textContent = 'Ready: ' + counts.waiting;
        } else {
            waitingBadge.style.display = 'none';
        }

        if ((counts.delayed || 0) > 0) {
            delayedBadge.style.display = '';
            delayedBadge.textContent = 'Delayed: ' + counts.delayed;
        } else {
            delayedBadge.style.display = 'none';
        }

        if ((counts.failed_recent || 0) > 0) {
            failedBadge.style.display = '';
            failedBadge.textContent = 'Failed (24h): ' + counts.failed_recent;
        } else {
            failedBadge.style.display = 'none';
        }

        if (nowRunning.length) {
            nowWrap.style.display = '';
            fillList(nowList, nowRunning, false);
        } else {
            nowWrap.style.display = 'none';
            nowList.innerHTML = '';
        }

        if (ready.length) {
            readyWrap.style.display = '';
            fillList(readyList, ready, false);
        } else {
            readyWrap.style.display = 'none';
            readyList.innerHTML = '';
        }

        if (delayedJobs.length) {
            delayedWrap.style.display = '';
            fillList(delayedList, delayedJobs, true);
        } else {
            delayedWrap.style.display = 'none';
            delayedList.innerHTML = '';
        }

        if (linkMap && (linkMap.running || linkMap.error)) {
            linkMapWrap.style.display = '';
            var page = linkMap.page || 0;
            var total = linkMap.total_page || null;
            var pct = 0;
            if (total && total > 0) {
                pct = Math.min(100, Math.round((page / total) * 100));
            } else if (page > 0) {
                pct = Math.min(95, page * 5);
            }
            linkMapMsg.textContent = linkMap.message || ('Link map sync — page ' + page + (total ? ' of ' + total : ''));
            linkMapPct.textContent = pct + '%';
            linkMapBar.style.width = pct + '%';
            if (linkMap.error) {
                linkMapBar.classList.add('bg-danger');
                linkMapBar.classList.remove('progress-bar-animated');
            } else {
                linkMapBar.classList.remove('bg-danger');
                linkMapBar.classList.add('progress-bar-animated');
            }
        } else {
            linkMapWrap.style.display = 'none';
        }

        // Always visible so users know the channel is monitored.
        panel.style.display = '';
        checkedEl.textContent = data.checked_at ? ('Updated ' + new Date(data.checked_at).toLocaleTimeString()) : '';
    }

    function poll() {
        fetch(url, { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (payload) {
                if (payload && payload.success) {
                    render(payload.status || {});
                }
            })
            .catch(function () { /* silent */ });
    }

    poll();
    timer = setInterval(poll, pollMs);
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            if (timer) clearInterval(timer);
            timer = null;
        } else if (!timer) {
            poll();
            timer = setInterval(poll, pollMs);
        }
    });
})();
</script>
