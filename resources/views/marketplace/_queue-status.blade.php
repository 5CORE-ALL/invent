<div id="mm-queue-status" class="card mb-3" data-slug="{{ $slug }}" data-status-url="{{ route('marketplace.queue.status', $slug) }}" style="display:none;">
    <div class="card-body py-3">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
            <div>
                <strong class="small"><i class="ri-pulse-line"></i> Background sync status</strong>
                <div id="mm-queue-worker" class="small text-muted mt-1">Checking queue…</div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <span id="mm-queue-running" class="badge bg-primary-subtle text-primary border" style="display:none;">Running: 0</span>
                <span id="mm-queue-waiting" class="badge bg-warning-subtle text-warning border" style="display:none;">Waiting: 0</span>
                <span id="mm-queue-failed" class="badge bg-danger-subtle text-danger border" style="display:none;">Failed (24h): 0</span>
            </div>
        </div>
        <ul id="mm-queue-tasks" class="small mb-0 ps-3" style="display:none;"></ul>
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
    var runningBadge = document.getElementById('mm-queue-running');
    var waitingBadge = document.getElementById('mm-queue-waiting');
    var failedBadge = document.getElementById('mm-queue-failed');
    var tasksEl = document.getElementById('mm-queue-tasks');
    var linkMapWrap = document.getElementById('mm-queue-linkmap');
    var linkMapMsg = document.getElementById('mm-queue-linkmap-msg');
    var linkMapPct = document.getElementById('mm-queue-linkmap-pct');
    var linkMapBar = document.getElementById('mm-queue-linkmap-bar');
    var checkedEl = document.getElementById('mm-queue-checked');
    var pollMs = 8000;
    var timer = null;

    function statusIcon(status) {
        if (status === 'running') return '▶';
        if (status === 'waiting') return '⏳';
        if (status === 'delayed') return '⏸';
        return '•';
    }

    function workerClass(state) {
        if (state === 'running') return 'text-primary';
        if (state === 'stalled') return 'text-danger';
        if (state === 'backlogged') return 'text-warning';
        return 'text-muted';
    }

    function render(data) {
        var counts = data.counts || {};
        var worker = data.worker || {};
        var tasks = data.tasks || [];
        var linkMap = data.link_map;
        var show = false;

        workerEl.textContent = worker.message || 'Queue status unknown.';
        workerEl.className = 'small mt-1 ' + workerClass(worker.state || 'idle');

        if ((counts.running || 0) > 0) {
            runningBadge.style.display = '';
            runningBadge.textContent = 'Running: ' + counts.running;
            show = true;
        } else {
            runningBadge.style.display = 'none';
        }

        var waitingTotal = (counts.waiting || 0) + (counts.delayed || 0);
        if (waitingTotal > 0) {
            waitingBadge.style.display = '';
            waitingBadge.textContent = 'Waiting: ' + waitingTotal;
            show = true;
        } else {
            waitingBadge.style.display = 'none';
        }

        if ((counts.failed_recent || 0) > 0) {
            failedBadge.style.display = '';
            failedBadge.textContent = 'Failed (24h): ' + counts.failed_recent;
            show = true;
        } else {
            failedBadge.style.display = 'none';
        }

        if (tasks.length) {
            tasksEl.style.display = '';
            tasksEl.innerHTML = tasks.map(function (t) {
                var suffix = t.count > 1 ? ' (×' + t.count + ')' : '';
                return '<li>' + statusIcon(t.status) + ' ' + (t.label || t.job) + suffix + '</li>';
            }).join('');
            show = true;
        } else {
            tasksEl.style.display = 'none';
            tasksEl.innerHTML = '';
        }

        if (linkMap && (linkMap.running || linkMap.error || !linkMap.done)) {
            linkMapWrap.style.display = '';
            var page = linkMap.page || 0;
            var total = linkMap.total_page || null;
            var pct = 0;
            if (total && total > 0) {
                pct = Math.min(100, Math.round((page / total) * 100));
            } else if (page > 0) {
                pct = Math.min(95, page * 5);
            }
            linkMapMsg.textContent = linkMap.message || ('Link map page ' + page + (total ? ' / ' + total : ''));
            linkMapPct.textContent = pct + '%';
            linkMapBar.style.width = pct + '%';
            if (linkMap.error) {
                linkMapBar.classList.add('bg-danger');
                linkMapBar.classList.remove('progress-bar-animated');
            }
            show = true;
        } else {
            linkMapWrap.style.display = 'none';
        }

        if (worker.state === 'stalled' || worker.state === 'backlogged') {
            show = true;
        }

        panel.style.display = show ? '' : 'none';
        checkedEl.textContent = data.checked_at ? ('Updated ' + new Date(data.checked_at).toLocaleTimeString()) : '';
    }

    function poll() {
        fetch(url, { headers: { 'Accept': 'application/json' } })
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
