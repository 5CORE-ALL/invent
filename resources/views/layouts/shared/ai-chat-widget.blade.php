{{-- AI Chat widget: only for authenticated users --}}
@auth
<div id="ai-chat-widget" class="ai-chat-widget">
    <button type="button" id="ai-chat-toggle" class="ai-chat-toggle" aria-label="Open AI chat">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
    </button>
    <div id="ai-chat-panel" class="ai-chat-panel" hidden>
        <div class="ai-chat-header">
            <span class="ai-chat-title">5Core AI Assistant</span>
            <button type="button" id="ai-chat-close" class="ai-chat-close" aria-label="Close">×</button>
        </div>
        <div id="ai-chat-messages" class="ai-chat-messages"></div>
        <div class="ai-chat-input-wrap">
            <textarea id="ai-chat-input" class="ai-chat-input" rows="2" placeholder="Ask a question..." maxlength="4000"></textarea>
            <button type="button" id="ai-chat-send" class="ai-chat-send">Send</button>
        </div>
    </div>
</div>
<style>
.ai-chat-widget { position: fixed; bottom: 24px; right: 24px; z-index: 1050; font-family: inherit; }
.ai-chat-toggle { width: 56px; height: 56px; border-radius: 50%; border: none; background: linear-gradient(135deg, #405189 0%, #0ab39c 100%); color: #fff; cursor: pointer; box-shadow: 0 4px 14px rgba(0,0,0,0.2); display: flex; align-items: center; justify-content: center; transition: transform 0.2s; }
.ai-chat-toggle:hover { transform: scale(1.05); }
.ai-chat-panel { position: absolute; bottom: 70px; right: 0; width: 380px; max-width: calc(100vw - 48px); height: 480px; background: #fff; border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,0.15); display: flex; flex-direction: column; overflow: hidden; }
.ai-chat-header { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; background: linear-gradient(135deg, #405189 0%, #0ab39c 100%); color: #fff; }
.ai-chat-title { font-weight: 600; font-size: 1rem; }
.ai-chat-close { background: none; border: none; color: #fff; font-size: 24px; line-height: 1; cursor: pointer; padding: 0 4px; opacity: 0.9; }
.ai-chat-close:hover { opacity: 1; }
.ai-chat-messages { flex: 1; overflow-y: auto; padding: 12px; display: flex; flex-direction: column; gap: 10px; background: #f8f9fa; }
.ai-chat-msg { max-width: 90%; padding: 10px 12px; border-radius: 12px; font-size: 0.9rem; line-height: 1.4; word-wrap: break-word; }
.ai-chat-msg.user { align-self: flex-end; background: #405189; color: #fff; }
.ai-chat-msg.assistant { align-self: flex-start; background: #fff; border: 1px solid #e9ecef; white-space: pre-wrap; }
.ai-chat-msg .ai-chat-feedback { margin-top: 8px; font-size: 0.8rem; }
.ai-chat-feedback button { margin-right: 8px; padding: 4px 10px; border-radius: 6px; border: 1px solid #dee2e6; background: #fff; cursor: pointer; }
.ai-chat-feedback button:hover { background: #f1f3f5; }
.ai-chat-feedback button.helpful-yes { border-color: #0ab39c; color: #0ab39c; }
.ai-chat-feedback button.helpful-no { border-color: #f06548; color: #f06548; }
.ai-chat-input-wrap { display: flex; gap: 8px; padding: 12px; border-top: 1px solid #e9ecef; background: #fff; }
.ai-chat-input { flex: 1; resize: none; border: 1px solid #ced4da; border-radius: 8px; padding: 10px 12px; font-size: 0.9rem; }
.ai-chat-input:focus { outline: none; border-color: #405189; }
.ai-chat-send { padding: 10px 16px; border: none; border-radius: 8px; background: #405189; color: #fff; font-weight: 500; cursor: pointer; }
.ai-chat-send:hover { background: #364574; }
.ai-chat-send:disabled { opacity: 0.6; cursor: not-allowed; }
.ai-chat-typing { color: #6c757d; font-style: italic; }
</style>
<script>
(function() {
    const toggle = document.getElementById('ai-chat-toggle');
    const panel = document.getElementById('ai-chat-panel');
    const closeBtn = document.getElementById('ai-chat-close');
    const messagesEl = document.getElementById('ai-chat-messages');
    const inputEl = document.getElementById('ai-chat-input');
    const sendBtn = document.getElementById('ai-chat-send');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    if (!toggle || !panel) return;

    toggle.addEventListener('click', function() {
        const isHidden = panel.hidden;
        panel.hidden = !isHidden;
        if (!isHidden) inputEl.focus();
    });
    closeBtn.addEventListener('click', function() { panel.hidden = true; });

    function appendMessage(role, text, id) {
        const div = document.createElement('div');
        div.className = 'ai-chat-msg ' + role;
        const content = document.createElement('div');
        content.textContent = text;
        div.appendChild(content);
        if (role === 'assistant' && id != null) {
            const feedback = document.createElement('div');
            feedback.className = 'ai-chat-feedback';
            feedback.innerHTML = '<button type="button" class="helpful-yes" data-id="' + id + '" data-helpful="1">Helpful</button><button type="button" class="helpful-no" data-id="' + id + '" data-helpful="0">Not helpful</button>';
            div.appendChild(feedback);
            feedback.querySelectorAll('button').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const recId = this.getAttribute('data-id');
                    const helpful = this.getAttribute('data-helpful') === '1';
                    feedback.replaceChildren(document.createTextNode(helpful ? 'Thanks for your feedback!' : 'Thanks, we\'ll try to improve.'));
                    // fetch('{{ route("ai.feedback") }}', {
                    //     method: 'POST',
                    //     headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    //     body: JSON.stringify({ id: parseInt(recId, 10), helpful: helpful })
                    // }).catch(function() {});
                });
            });
        }
        messagesEl.appendChild(div);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function setLoading(loading) {
        sendBtn.disabled = loading;
        if (loading) {
            const el = document.createElement('div');
            el.className = 'ai-chat-msg assistant ai-chat-typing';
            el.textContent = 'Thinking...';
            el.id = 'ai-chat-loading';
            messagesEl.appendChild(el);
            messagesEl.scrollTop = messagesEl.scrollHeight;
        } else {
            const el = document.getElementById('ai-chat-loading');
            if (el) el.remove();
        }
    }

    sendBtn.addEventListener('click', function() {
        const question = (inputEl.value || '').trim();
        if (!question) return;
        inputEl.value = '';
        appendMessage('user', question);
        setLoading(true);
        fetch('{{ route("ai.chat") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify({ question: question })
        })
        .then(function(r) { return r.json().then(function(data) { return { ok: r.ok, status: r.status, data: data }; }); })
        .then(function(res) {
            setLoading(false);
            var answer = (res.data && res.data.answer) ? res.data.answer : 'Sorry, something went wrong. Please try again.';
            var id = (res.data && res.data.id) != null ? res.data.id : null;
            appendMessage('assistant', answer, id);
        })
        .catch(function() {
            setLoading(false);
            appendMessage('assistant', 'Sorry, something went wrong. Please try again.', null);
        });
    });

    inputEl.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendBtn.click(); }
    });
})();
</script>
@endauth
