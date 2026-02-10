@auth
<div id="ai-chat-widget" class="ai-chat-widget">
    <button type="button" id="ai-chat-toggle" class="ai-chat-toggle" aria-label="Open 5Core AI chat">
        <span class="ai-chat-toggle-icon">💬</span>
    </button>
    <div id="ai-chat-panel" class="ai-chat-panel" aria-hidden="true">
        <div class="ai-chat-header">
            <span class="ai-chat-title">5Core AI Assistant</span>
            <button type="button" id="ai-chat-close" class="ai-chat-close" aria-label="Close chat">&times;</button>
        </div>
        <div id="ai-chat-messages" class="ai-chat-messages"></div>
        <div class="ai-chat-input-wrap">
            <textarea id="ai-chat-input" class="ai-chat-input" rows="2" placeholder="Ask a question..." maxlength="8000"></textarea>
            <button type="button" id="ai-chat-send" class="ai-chat-send">Send</button>
        </div>
    </div>
</div>

<style>
.ai-chat-widget { position: fixed; bottom: 24px; right: 24px; z-index: 9998; font-family: inherit; }
.ai-chat-toggle {
    width: 56px; height: 56px; border-radius: 50%;
    background: linear-gradient(135deg, #405189 0%, #2c3e72 100%);
    color: #fff; border: none; cursor: pointer; box-shadow: 0 4px 14px rgba(0,0,0,0.2);
    display: flex; align-items: center; justify-content: center; font-size: 1.5rem;
    transition: transform 0.2s, box-shadow 0.2s;
}
.ai-chat-toggle:hover { transform: scale(1.05); box-shadow: 0 6px 20px rgba(0,0,0,0.25); }
.ai-chat-panel {
    display: none; position: absolute; bottom: 72px; right: 0;
    width: 380px; max-width: calc(100vw - 48px); height: 480px; max-height: 70vh;
    background: #fff; border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,0.15);
    flex-direction: column; overflow: hidden;
}
.ai-chat-panel.is-open { display: flex; }
.ai-chat-header {
    padding: 12px 16px; background: linear-gradient(135deg, #405189 0%, #2c3e72 100%);
    color: #fff; display: flex; align-items: center; justify-content: space-between;
}
.ai-chat-title { font-weight: 600; font-size: 0.95rem; }
.ai-chat-close { background: none; border: none; color: #fff; font-size: 1.5rem; cursor: pointer; line-height: 1; opacity: 0.9; }
.ai-chat-close:hover { opacity: 1; }
.ai-chat-messages {
    flex: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 12px;
    background: #f8f9fa;
}
.ai-chat-msg { max-width: 90%; padding: 10px 14px; border-radius: 12px; font-size: 0.9rem; line-height: 1.45; white-space: pre-wrap; word-break: break-word; }
.ai-chat-msg.user { align-self: flex-end; background: #405189; color: #fff; }
.ai-chat-msg.assistant { align-self: flex-start; background: #fff; border: 1px solid #e9ecef; }
.ai-chat-msg .ai-chat-feedback { margin-top: 8px; display: flex; gap: 8px; }
.ai-chat-msg .ai-chat-feedback button { font-size: 0.75rem; padding: 4px 10px; border-radius: 6px; border: 1px solid #dee2e6; background: #fff; cursor: pointer; }
.ai-chat-msg .ai-chat-feedback button:hover { background: #f1f3f5; }
.ai-chat-msg .ai-chat-feedback button.active { border-color: #405189; background: #e8ebf2; color: #405189; }
.ai-chat-input-wrap { padding: 12px; border-top: 1px solid #e9ecef; background: #fff; display: flex; gap: 8px; align-items: flex-end; }
.ai-chat-input { flex: 1; resize: none; border: 1px solid #ced4da; border-radius: 8px; padding: 10px 12px; font-size: 0.9rem; }
.ai-chat-input:focus { outline: none; border-color: #405189; box-shadow: 0 0 0 2px rgba(64,81,137,0.2); }
.ai-chat-send { padding: 10px 18px; background: #405189; color: #fff; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; }
.ai-chat-send:hover { background: #2c3e72; }
.ai-chat-send:disabled { opacity: 0.6; cursor: not-allowed; }
</style>

<script>
(function() {
    const toggle = document.getElementById('ai-chat-toggle');
    const panel = document.getElementById('ai-chat-panel');
    const closeBtn = document.getElementById('ai-chat-close');
    const messagesEl = document.getElementById('ai-chat-messages');
    const inputEl = document.getElementById('ai-chat-input');
    const sendBtn = document.getElementById('ai-chat-send');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    if (!toggle || !panel) return;

    function openPanel() { panel.classList.add('is-open'); panel.setAttribute('aria-hidden', 'false'); inputEl.focus(); }
    function closePanel() { panel.classList.remove('is-open'); panel.setAttribute('aria-hidden', 'true'); }
    toggle.addEventListener('click', openPanel);
    closeBtn.addEventListener('click', closePanel);

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function appendMessage(role, content, recordId) {
        const wrap = document.createElement('div');
        wrap.className = 'ai-chat-msg ' + role;
        const safe = escapeHtml(content);
        let inner = safe.replace(/\n/g, '<br>');
        if (role === 'assistant' && recordId) {
            inner += '<div class="ai-chat-feedback" data-id="' + escapeHtml(String(recordId)) + '">';
            inner += '<button type="button" class="ai-feedback-btn" data-helpful="1">Helpful</button>';
            inner += '<button type="button" class="ai-feedback-btn" data-helpful="0">Not helpful</button></div>';
        }
        wrap.innerHTML = inner;
        messagesEl.appendChild(wrap);
        messagesEl.scrollTop = messagesEl.scrollHeight;
        if (recordId) {
            wrap.querySelectorAll('.ai-feedback-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const helpful = this.getAttribute('data-helpful') === '1';
                    sendFeedback(recordId, helpful, wrap);
                });
            });
        }
    }

    function sendFeedback(id, helpful, msgWrap) {
        const fd = new FormData();
        fd.append('_token', csrfToken);
        fd.append('id', id);
        fd.append('helpful', helpful ? '1' : '0');
        fetch('{{ route("ai.feedback") }}', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    var fb = msgWrap.querySelector('.ai-chat-feedback');
                    if (fb) { fb.querySelectorAll('button').forEach(function(b) { b.classList.remove('active'); }); msgWrap.querySelector('.ai-chat-feedback button[data-helpful="' + (helpful ? '1' : '0') + '"]').classList.add('active'); }
                }
            });
    }

    function setLoading(loading) {
        sendBtn.disabled = loading;
        if (loading) {
            var el = document.createElement('div');
            el.className = 'ai-chat-msg assistant';
            el.innerHTML = 'Thinking...';
            el.setAttribute('data-loading', '1');
            messagesEl.appendChild(el);
            messagesEl.scrollTop = messagesEl.scrollHeight;
        } else {
            var loadingEl = messagesEl.querySelector('[data-loading="1"]');
            if (loadingEl) loadingEl.remove();
        }
    }

    sendBtn.addEventListener('click', function() { submitQuestion(); });
    inputEl.addEventListener('keydown', function(e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); submitQuestion(); } });

    function submitQuestion() {
        var question = (inputEl.value || '').trim();
        if (!question || sendBtn.disabled) return;
        appendMessage('user', question);
        inputEl.value = '';
        setLoading(true);
        var fd = new FormData();
        fd.append('_token', csrfToken);
        fd.append('question', question);
        fetch('{{ route("ai.chat") }}', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                setLoading(false);
                appendMessage('assistant', data.answer || 'No response.', data.id || null);
            })
            .catch(function() {
                setLoading(false);
                appendMessage('assistant', 'Something went wrong. Please try again.', null);
            });
    }
})();
</script>
@endauth
