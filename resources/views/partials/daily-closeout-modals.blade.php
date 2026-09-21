{{--
    IST closeout popups — once a day each, at a random time after 12:00 AM IST
    (task Yes/No, then two DAR reminders). Never before that fire time.
--}}
@auth
<style>
    #closeoutTaskModal .modal-dialog,
    #closeoutDarModal .modal-dialog { max-width: 460px; }
    #closeoutTaskModal .modal-content,
    #closeoutDarModal .modal-content {
        border: none;
        border-radius: 22px;
        overflow: hidden;
        box-shadow: 0 24px 60px rgba(15, 23, 42, 0.22);
    }
    #closeoutTaskModal .modal-body,
    #closeoutDarModal .modal-body {
        padding: 2rem 1.6rem 1.5rem;
        text-align: center;
    }
    #closeoutTaskModal .modal-body {
        background: linear-gradient(180deg, #eff6ff 0%, #fff 55%);
    }
    #closeoutDarModal .modal-body {
        background: linear-gradient(180deg, #fff7ed 0%, #fff 55%);
    }
    .co-nudge-emoji { font-size: 3.6rem; line-height: 1; display: block; margin-bottom: 0.35rem; }
    .co-nudge-kicker {
        font-size: 0.74rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        margin-bottom: 0.45rem;
    }
    #closeoutTaskModal .co-nudge-kicker { color: #1d4ed8; }
    #closeoutDarModal .co-nudge-kicker { color: #9a3412; }
    .co-nudge-title {
        font-size: 1.2rem;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 0.45rem;
    }
    .co-nudge-msg {
        font-size: 0.98rem;
        line-height: 1.5;
        color: #475569;
        margin: 0 0 1.15rem;
        font-weight: 600;
    }
    .co-nudge-actions {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    .co-nudge-actions .btn {
        border-radius: 999px;
        font-weight: 800;
        padding: 0.65rem 1rem;
    }
    #closeoutTaskWhyWrap { text-align: left; }
    #closeoutTaskWhy {
        min-height: 90px;
        resize: vertical;
    }
    #closeoutTaskError { font-size: 0.85rem; font-weight: 700; }
</style>

<div class="modal fade" id="closeoutTaskModal" tabindex="-1" aria-labelledby="closeoutTaskTitle" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body">
                <span class="co-nudge-emoji" aria-hidden="true">📋</span>
                <div class="co-nudge-kicker" id="closeoutTaskHello">End of day</div>
                <div class="co-nudge-title" id="closeoutTaskTitle">Did you complete your today task?</div>
                <p class="co-nudge-msg">Answer Yes or No. If No, a reason is required.</p>
                <div class="co-nudge-actions" id="closeoutTaskChoice">
                    <button type="button" class="btn btn-success" id="closeoutTaskYes">Yes</button>
                    <button type="button" class="btn btn-outline-danger" id="closeoutTaskNo">No</button>
                </div>
                <div id="closeoutTaskWhyWrap" class="d-none mt-3">
                    <label for="closeoutTaskWhy" class="form-label fw-bold">Why no? <span class="text-danger">*</span></label>
                    <textarea id="closeoutTaskWhy" class="form-control" maxlength="2000" placeholder="Tell us why today's tasks were not completed"></textarea>
                    <div class="text-danger mt-2 d-none" id="closeoutTaskError">Please enter a reason.</div>
                    <div class="co-nudge-actions mt-3">
                        <button type="button" class="btn btn-danger" id="closeoutTaskSubmitNo">Submit</button>
                        <button type="button" class="btn btn-light" id="closeoutTaskBack">Back</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="closeoutDarModal" tabindex="-1" aria-labelledby="closeoutDarTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body">
                <span class="co-nudge-emoji" aria-hidden="true">🔔</span>
                <div class="co-nudge-kicker" id="closeoutDarHello">DAR reminder</div>
                <div class="co-nudge-title" id="closeoutDarTitle">Fill your DAR before logout</div>
                <p class="co-nudge-msg" id="closeoutDarMsg">File today's Daily Activity Report before you leave.</p>
                <div class="co-nudge-actions">
                    <button type="button" class="btn btn-warning text-dark" id="closeoutDarFill">Fill DAR</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">I'll do it now</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var cfg = {
        userId: @json((int) auth()->id()),
        userName: @json(auth()->user()->name ?? ''),
        nudgeUrl: @json(route('daily-closeout.nudge')),
        answerUrl: @json(route('daily-closeout.tasks')),
        darUrl: @json(route('daily-closeout.dar-nudge'))
    };
    if (!cfg.userId) return;

    var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    var timers = [];
    var pending = [];
    var showing = false;
    var armed = {};
    var state = { check_date: null, busy: false };
    function waitUntilHidden(el, then) {
        if (el && el.classList.contains('show')) {
            el.addEventListener('hidden.bs.modal', function () { then(); }, { once: true });
            return;
        }
        then();
    }
    function whenReady(cb) {
        var blockers = [
            'overdueNudgeModal',
            'tatNudgeModal',
            'followUpNudgeModal',
            'closeoutTaskModal',
            'closeoutDarModal',
            'darLogoutNudgeModal'
        ];
        function next(i) {
            if (i >= blockers.length) {
                cb();
                return;
            }
            waitUntilHidden(document.getElementById(blockers[i]), function () { next(i + 1); });
        }
        next(0);
    }
    function postJson(url, body) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(body)
        }).then(function (r) { return r.json(); });
    }
    function openDarForm() {
        if (window.DarModal && typeof window.DarModal.open === 'function') {
            window.DarModal.open(null);
            return;
        }
        var btn = document.getElementById('darTopbarOpenBtn');
        if (btn) btn.click();
    }
    function resetTaskForm() {
        var whyWrap = document.getElementById('closeoutTaskWhyWrap');
        var choice = document.getElementById('closeoutTaskChoice');
        var why = document.getElementById('closeoutTaskWhy');
        var err = document.getElementById('closeoutTaskError');
        if (whyWrap) whyWrap.classList.add('d-none');
        if (choice) choice.classList.remove('d-none');
        if (why) why.value = '';
        if (err) err.classList.add('d-none');
    }
    function showTaskModal() {
        var modalEl = document.getElementById('closeoutTaskModal');
        var hello = document.getElementById('closeoutTaskHello');
        if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) return;
        resetTaskForm();
        if (hello && cfg.userName) hello.textContent = 'Hi ' + cfg.userName;
        bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: 'static', keyboard: false }).show();
        return modalEl;
    }
    function hideTaskModal() {
        var modalEl = document.getElementById('closeoutTaskModal');
        if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) return;
        var inst = bootstrap.Modal.getInstance(modalEl);
        if (inst) inst.hide();
    }
    function showDarModal(slot) {
        var modalEl = document.getElementById('closeoutDarModal');
        var hello = document.getElementById('closeoutDarHello');
        var title = document.getElementById('closeoutDarTitle');
        if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) return;
        if (hello) hello.textContent = 'DAR reminder';
        if (title) title.textContent = 'Fill your DAR before logout';
        modalEl.setAttribute('data-slot', slot);
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
        postJson(cfg.darUrl, { check_date: state.check_date, slot: slot }).catch(function () {});
        return modalEl;
    }
    function submitTask(completed, reason) {
        if (state.busy) return;
        state.busy = true;
        postJson(cfg.answerUrl, {
            check_date: state.check_date,
            tasks_completed: completed,
            incomplete_reason: reason || ''
        }).then(function (json) {
            if (!json || json.success === false) {
                var err = document.getElementById('closeoutTaskError');
                if (err) {
                    err.textContent = (json && json.message) ? json.message : 'Could not save your answer.';
                    err.classList.remove('d-none');
                }
                return;
            }
            hideTaskModal();
        }).catch(function () {
            var err = document.getElementById('closeoutTaskError');
            if (err) {
                err.textContent = 'Could not save your answer.';
                err.classList.remove('d-none');
            }
        }).finally(function () {
            state.busy = false;
        });
    }
    function enqueueShow(fn) {
        pending.push(fn);
        flushQueue();
    }
    function flushQueue() {
        if (showing || !pending.length) return;
        showing = true;
        var fn = pending.shift();
        whenReady(function () {
            var open = fn();
            if (open) {
                open.addEventListener('hidden.bs.modal', function () {
                    showing = false;
                    flushQueue();
                }, { once: true });
                return;
            }
            showing = false;
            flushQueue();
        });
    }
    function schedule(data) {
        if (!data || data.success === false) return;
        state.check_date = data.check_date;
        [['tasks', showTaskModal], ['dar_first', function () { showDarModal('first'); }], ['dar_second', function () { showDarModal('second'); }]].forEach(function (item) {
            var key = item[0];
            var show = item[1];
            var slot = data[key];
            if (!slot || !slot.needed || armed[key]) return;
            var wait = parseInt(slot.wait_ms, 10);
            if (!(wait >= 0)) return;
            armed[key] = true;
            timers.push(setTimeout(function () {
                fetch(cfg.nudgeUrl, {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function (r) { return r.json(); }).then(function (fresh) {
                    var next = fresh && fresh[key];
                    if (!next || !next.needed || !next.due) return;
                    state.check_date = fresh.check_date || state.check_date;
                    enqueueShow(show);
                }).catch(function () {});
            }, wait));
        });
    }
    function load() {
        fetch(cfg.nudgeUrl, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.json(); }).then(schedule).catch(function () {});
    }

    document.getElementById('closeoutTaskYes')?.addEventListener('click', function () {
        submitTask(true, '');
    });
    document.getElementById('closeoutTaskNo')?.addEventListener('click', function () {
        document.getElementById('closeoutTaskChoice')?.classList.add('d-none');
        document.getElementById('closeoutTaskWhyWrap')?.classList.remove('d-none');
        document.getElementById('closeoutTaskWhy')?.focus();
    });
    document.getElementById('closeoutTaskBack')?.addEventListener('click', resetTaskForm);
    document.getElementById('closeoutTaskSubmitNo')?.addEventListener('click', function () {
        var why = (document.getElementById('closeoutTaskWhy')?.value || '').trim();
        var err = document.getElementById('closeoutTaskError');
        if (!why) {
            if (err) {
                err.textContent = 'Please enter a reason.';
                err.classList.remove('d-none');
            }
            document.getElementById('closeoutTaskWhy')?.focus();
            return;
        }
        if (err) err.classList.add('d-none');
        submitTask(false, why);
    });
    document.getElementById('closeoutDarFill')?.addEventListener('click', function () {
        var modalEl = document.getElementById('closeoutDarModal');
        if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            var inst = bootstrap.Modal.getInstance(modalEl);
            if (inst) inst.hide();
        }
        openDarForm();
    });
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) load();
    });

    load();
})();
</script>
@endauth
