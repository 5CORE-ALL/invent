{{--
    Pops up pre-saved dynamic messages (see /dynamic-messages) for the logged-in user.
    - A mandatory check-box must be ticked before the message can be closed.
    - If the rule allows it, an optional reply to Amarjit can be written.
--}}
<style>
    #dynMsgModal .modal-content { border: 0; border-radius: 16px; overflow: hidden; box-shadow: 0 24px 60px rgba(15, 23, 42, .28); }
    #dynMsgModal .dm-pop-head { background: #0b1f3a; color: #fff; padding: .8rem 1.1rem; display: flex; justify-content: space-between; align-items: center; }
    #dynMsgModal .dm-pop-head small { opacity: .75; }
    #dynMsgModal .dm-pop-title { font-size: 1.05rem; font-weight: 800; color: #0f172a; }
    #dynMsgModal .dm-pop-text { white-space: pre-wrap; font-size: .92rem; color: #1e293b; margin-top: .5rem; }
    #dynMsgModal .dm-pop-err { color: #dc2626; font-size: .8rem; min-height: 1.1rem; }
</style>
<div class="modal fade" id="dynMsgModal" tabindex="-1" aria-labelledby="dynMsgTitle" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="dm-pop-head">
                <div><span id="dynMsgPage">Message</span> <small id="dynMsgCount"></small></div>
                <small id="dynMsgWhen"></small>
            </div>
            <div class="modal-body">
                <div class="dm-pop-title" id="dynMsgTitle"></div>
                <div class="dm-pop-text" id="dynMsgBody"></div>

                <div class="mt-3 d-none" id="dynMsgReplyWrap">
                    <label class="form-label fw-semibold mb-1" for="dynMsgReply">Reply to <span id="dynMsgReplyTo">Amarjit</span> <span class="text-muted fw-normal">(optional)</span></label>
                    <textarea class="form-control" id="dynMsgReply" rows="3" maxlength="2000" placeholder="Write a reply (optional)…"></textarea>
                </div>

                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" id="dynMsgConfirm">
                    <label class="form-check-label fw-semibold" for="dynMsgConfirm">I have read and understood this message <span class="text-danger">*</span></label>
                </div>
                <div class="dm-pop-err" id="dynMsgErr"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="dynMsgSubmit" disabled>Confirm &amp; close</button>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('dynMsgModal');
    if (!modalEl || typeof bootstrap === 'undefined') return;

    var pendingUrl = @json(route('dynamic-messages.pending'));
    var ackBase = @json(url('dynamic-messages'));
    var csrf = document.querySelector('meta[name="csrf-token"]');
    csrf = csrf ? csrf.getAttribute('content') : @json(csrf_token());

    var modal = new bootstrap.Modal(modalEl);
    var queue = [], total = 0, showing = false;
    var $ = function (id) { return document.getElementById(id); };

    function setSubmit() { $('dynMsgSubmit').disabled = !$('dynMsgConfirm').checked; }
    $('dynMsgConfirm').addEventListener('change', setSubmit);

    function showNext() {
        var m = queue[0];
        if (!m) { showing = false; modal.hide(); return; }
        $('dynMsgPage').textContent = m.page || 'Message';
        $('dynMsgCount').textContent = total > 1 ? '(' + (total - queue.length + 1) + ' of ' + total + ')' : '';
        $('dynMsgWhen').textContent = m.sent_at || '';
        $('dynMsgTitle').textContent = m.title;
        $('dynMsgBody').textContent = m.body;
        $('dynMsgReplyTo').textContent = m.reply_label || 'Amarjit';
        $('dynMsgReplyWrap').classList.toggle('d-none', !m.allow_reply);
        $('dynMsgReply').value = '';
        $('dynMsgConfirm').checked = false;
        $('dynMsgErr').textContent = '';
        setSubmit();
        if (!showing) { showing = true; modal.show(); }
    }

    $('dynMsgSubmit').addEventListener('click', function () {
        var m = queue[0];
        if (!m) return;
        if (!$('dynMsgConfirm').checked) { $('dynMsgErr').textContent = 'Please tick the check-box to continue.'; return; }
        var btn = $('dynMsgSubmit'); btn.disabled = true;
        fetch(ackBase + '/' + m.id + '/acknowledge', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ confirm: true, reply: m.allow_reply ? $('dynMsgReply').value.trim() : '' })
        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
          .then(function (res) {
              if (!res.ok) {
                  var first = res.j.errors ? Object.values(res.j.errors)[0][0] : null;
                  throw new Error(first || res.j.message || 'Could not save. Please try again.');
              }
              queue.shift();
              showNext();
          })
          .catch(function (e) { $('dynMsgErr').textContent = e.message; setSubmit(); });
    });

    function check() {
        if (showing) return;
        fetch(pendingUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.ok ? r.json() : { data: [] }; })
            .then(function (j) {
                if (!j.data || !j.data.length || showing) return;
                queue = j.data.slice(); total = queue.length;
                showNext();
            })
            .catch(function () {});
    }

    setTimeout(check, 2500);
    setInterval(check, 10 * 60 * 1000);
});
</script>
