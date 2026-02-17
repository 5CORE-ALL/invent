@auth
    @if (auth()->user()->is5CoreMember())
        <div id="ai-chat-widget" class="ai-chat-widget" data-user-email="{{ e(auth()->user()->email) }}" data-user-id="{{ auth()->id() }}" data-pusher-key="{{ config('broadcasting.connections.pusher.key', '') }}" data-pusher-cluster="{{ config('broadcasting.connections.pusher.options.cluster', 'ap2') }}">
            <button type="button" id="ai-chat-toggle" class="ai-chat-toggle" aria-label="Open 5Core AI chat">
                <img src="{{ asset('images/chat-icon.png') }}" alt="5Core AI Chat" class="ai-chat-icon">
                <span id="ai-chat-notification-badge" class="ai-chat-notification-badge" style="display: none;">0</span>
            </button>
            <div id="ai-chat-panel" class="ai-chat-panel" aria-hidden="true">
                <div class="ai-chat-header">
                    <span class="ai-chat-title">5Core AI Assistant</span>
                    <div class="ai-chat-header-actions">
                        <a href="{{ route('ai.download.sample') }}" id="ai-sample-csv-btn" class="ai-header-link"
                            title="Download sample CSV format">📥 Sample CSV</a>
                        <button type="button" id="ai-chat-clear-history" class="ai-header-link ai-chat-clear-history-btn" title="Clear chat history">Clear History</button>
                        <button type="button" id="ai-chat-close" class="ai-chat-close"
                            aria-label="Close chat">&times;</button>
                    </div>
                </div>
                <div id="ai-chat-messages" class="ai-chat-messages"></div>
                <div class="ai-chat-input-wrap">
                    <textarea id="ai-chat-input" class="ai-chat-input" rows="2" placeholder="Ask a question..." maxlength="8000"></textarea>
                    <div class="ai-chat-attach">
                        <input type="file" id="ai-file-upload" accept=".csv" hidden>
                        <button type="button" id="ai-attach-btn" class="ai-attach-btn"
                            title="Upload CSV to train AI">📎</button>
                    </div>
                    <button type="button" id="ai-chat-send" class="ai-chat-send">Send</button>
                </div>
            </div>
        </div>

        <style>
            .ai-chat-widget {
                position: fixed;
                bottom: 24px;
                right: 24px;
                z-index: 9998;
                font-family: inherit;
            }

            .ai-chat-toggle {
                position: relative;
                width: 144px;
                height: 144px;
                border-radius: 50%;
                background: transparent;
                border: none;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 0;
                overflow: visible;
                transition: transform 0.2s ease;
            }

            .ai-chat-icon {
                width: 100%;
                height: 100%;
                object-fit: contain;
                filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.15));
                transition: transform 0.35s ease-in-out;
            }

            .ai-chat-toggle:hover .ai-chat-icon {
                animation: ai-chat-shake 0.4s ease-in-out;
            }

            .ai-chat-toggle:active .ai-chat-icon {
                transform: scale(0.95);
            }

            .ai-chat-toggle.has-notification::after {
                content: '';
                position: absolute;
                inset: -4px;
                border-radius: 50%;
                background: transparent;
                animation: ai-chat-pulse 2s infinite;
                pointer-events: none;
            }

            @keyframes ai-chat-shake {

                0%,
                100% {
                    transform: translateX(0) rotate(0deg);
                }

                20% {
                    transform: translateX(-3px) rotate(-4deg);
                }

                40% {
                    transform: translateX(3px) rotate(4deg);
                }

                60% {
                    transform: translateX(-2px) rotate(-2deg);
                }

                80% {
                    transform: translateX(2px) rotate(2deg);
                }
            }

            @keyframes ai-chat-pulse {
                0% {
                    box-shadow: 0 0 0 0 rgba(64, 81, 137, 0.4);
                }

                70% {
                    box-shadow: 0 0 0 10px rgba(64, 81, 137, 0);
                }

                100% {
                    box-shadow: 0 0 0 0 rgba(64, 81, 137, 0);
                }
            }

            .ai-chat-notification-badge {
                position: absolute;
                top: -5px;
                right: -5px;
                min-width: 18px;
                height: 18px;
                padding: 0 5px;
                background: #dc3545;
                color: #fff;
                font-size: 0.7rem;
                font-weight: 600;
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .ai-chat-panel {
                display: none;
                position: absolute;
                bottom: 144px;
                right: 0;
                width: 380px;
                max-width: calc(100vw - 48px);
                height: 480px;
                max-height: 70vh;
                background: #fff;
                border-radius: 12px;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
                flex-direction: column;
                overflow: hidden;
            }

            .ai-chat-panel.is-open {
                display: flex;
            }

            .ai-chat-header {
                padding: 12px 16px;
                background: linear-gradient(135deg, #405189 0%, #2c3e72 100%);
                color: #fff;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }

            .ai-chat-title {
                font-weight: 600;
                font-size: 0.95rem;
            }

            .ai-chat-header-actions {
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .ai-header-link {
                color: rgba(255, 255, 255, 0.95);
                font-size: 0.75rem;
                text-decoration: none;
                padding: 4px 8px;
                border-radius: 4px;
            }

            .ai-header-link:hover {
                color: #fff;
                background: rgba(255, 255, 255, 0.15);
            }

            /* Clear History: small, visible, overrides global button styles */
            .ai-chat-clear-history-btn {
                font-size: 0.65rem !important;
                padding: 3px 6px !important;
                min-height: unset;
                line-height: 1.2;
                border: 1px solid rgba(255, 255, 255, 0.5) !important;
                background: rgba(255, 255, 255, 0.2) !important;
                color: #fff !important;
                font-weight: 500;
                white-space: nowrap;
            }

            .ai-chat-clear-history-btn:hover {
                background: rgba(255, 255, 255, 0.35) !important;
                color: #fff !important;
                border-color: rgba(255, 255, 255, 0.7) !important;
            }

            .ai-chat-close {
                background: none;
                border: none;
                color: #fff;
                font-size: 1.5rem;
                cursor: pointer;
                line-height: 1;
                opacity: 0.9;
            }

            .ai-chat-close:hover {
                opacity: 1;
            }

            .ai-chat-messages {
                flex: 1;
                overflow-y: auto;
                padding: 16px;
                display: flex;
                flex-direction: column;
                gap: 12px;
                background: #f8f9fa;
            }

            .ai-chat-msg {
                max-width: 90%;
                padding: 10px 14px;
                border-radius: 12px;
                font-size: 0.9rem;
                line-height: 1.45;
                white-space: pre-wrap;
                word-break: break-word;
            }

            .ai-chat-msg.user {
                align-self: flex-end;
                background: #405189;
                color: #fff;
            }

            .ai-chat-msg.assistant {
                align-self: flex-start;
                background: #fff;
                border: 1px solid #e9ecef;
            }

            .ai-chat-msg .ai-chat-feedback {
                margin-top: 8px;
                display: flex;
                gap: 8px;
            }

            .ai-chat-msg .ai-chat-feedback button {
                font-size: 0.75rem;
                padding: 4px 10px;
                border-radius: 6px;
                border: 1px solid #dee2e6;
                background: #fff;
                cursor: pointer;
            }

            .ai-chat-msg .ai-chat-feedback button:hover {
                background: #f1f3f5;
            }

            .ai-chat-msg .ai-chat-feedback button.active {
                border-color: #405189;
                background: #e8ebf2;
                color: #405189;
            }

            .ai-chat-escalation-info {
                margin-top: 6px;
                font-size: 0.8rem;
                color: #6c757d;
            }

            .ai-chat-input-wrap {
                padding: 12px;
                border-top: 1px solid #e9ecef;
                background: #fff;
                display: flex;
                gap: 8px;
                align-items: flex-end;
                flex-wrap: wrap;
            }

            .ai-chat-input {
                flex: 1;
                min-width: 0;
                resize: none;
                border: 1px solid #ced4da;
                border-radius: 8px;
                padding: 10px 12px;
                font-size: 0.9rem;
            }

            .ai-chat-input:focus {
                outline: none;
                border-color: #405189;
                box-shadow: 0 0 0 2px rgba(64, 81, 137, 0.2);
            }

            .ai-chat-attach {
                display: flex;
                align-items: center;
            }

            .ai-attach-btn {
                background: #e9ecef;
                border: 1px solid #dee2e6;
                border-radius: 8px;
                padding: 10px 12px;
                cursor: pointer;
                font-size: 1.1rem;
            }

            .ai-attach-btn:hover {
                background: #dee2e6;
            }

            .ai-chat-send {
                padding: 10px 18px;
                background: #405189;
                color: #fff;
                border: none;
                border-radius: 8px;
                font-weight: 500;
                cursor: pointer;
            }

            .ai-chat-send:hover {
                background: #2c3e72;
            }

            .ai-chat-send:disabled {
                opacity: 0.6;
                cursor: not-allowed;
            }

            .ai-chat-toast {
                position: fixed;
                bottom: 100px;
                right: 24px;
                max-width: 360px;
                padding: 12px 16px;
                background: #405189;
                color: #fff;
                border-radius: 8px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.2);
                z-index: 10000;
                font-size: 0.9rem;
                animation: ai-toast-in 0.3s ease;
            }
            @keyframes ai-toast-in {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }
        </style>

        <script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
        <script>
            (function() {
                const toggle = document.getElementById('ai-chat-toggle');
                const panel = document.getElementById('ai-chat-panel');
                const closeBtn = document.getElementById('ai-chat-close');
                const messagesEl = document.getElementById('ai-chat-messages');
                const inputEl = document.getElementById('ai-chat-input');
                const sendBtn = document.getElementById('ai-chat-send');
                const badgeEl = document.getElementById('ai-chat-notification-badge');
                const fileInput = document.getElementById('ai-file-upload');
                const attachBtn = document.getElementById('ai-attach-btn');
                const widget = document.getElementById('ai-chat-widget');
                const userEmail = (widget && widget.getAttribute('data-user-email')) || '';
                const userId = widget && widget.getAttribute('data-user-id');
                const pusherKey = (widget && widget.getAttribute('data-pusher-key')) || '';
                const pusherCluster = (widget && widget.getAttribute('data-pusher-cluster')) || 'ap2';
                const STORAGE_KEY = '5core_chat_' + userEmail.replace(/[^a-zA-Z0-9@._-]/g, '_');
                let chatMessages = [];
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const routes = {
                    chat: '{{ route('ai.chat') }}',
                    feedback: '{{ route('ai.feedback') }}',
                    upload: '{{ route('ai.upload') }}',
                    check: '{{ route('ai.check') }}',
                    pending: '{{ route('ai.pending') }}',
                    markRead: '{{ route('ai.mark-read') }}'
                };

                if (!toggle || !panel) return;

                function showSeniorReplyToast(message) {
                    var existing = document.getElementById('ai-chat-toast');
                    if (existing) existing.remove();
                    var toast = document.createElement('div');
                    toast.id = 'ai-chat-toast';
                    toast.className = 'ai-chat-toast';
                    toast.textContent = message;
                    document.body.appendChild(toast);
                    setTimeout(function() { toast.remove(); }, 5000);
                }

              // ... upar ka code ...

if (pusherKey && userId && typeof Pusher !== 'undefined') {
    try {
        var pusher = new Pusher(pusherKey, {
            cluster: pusherCluster,
            authEndpoint: '{{ url("/broadcasting/auth") }}',
            auth: {
                headers: {
                    'X-CSRF-TOKEN': csrfToken || '',
                    'Accept': 'application/json'
                }
            }
        });

        // 🔥 NAYA CODE YAHAN AAYEGA
        pusher.connection.bind('connecting', function() {
            console.log('🟡 Connecting to Pusher...');
        });

        pusher.connection.bind('connected', function() {
            console.log('✅ Connected to Pusher! Socket ID:', pusher.connection.socket_id);
            
            var channel = pusher.subscribe('private-user.' + userId);
            
            channel.bind('pusher:subscription_succeeded', function() {
                console.log('✅ Subscribed to private-user.' + userId);
            });
            
            channel.bind('pusher:subscription_error', function(err) {
                console.error('❌ Subscription error:', err);
            });
            
            channel.bind('SeniorReplied', function(data) {
                console.log('📨 Received SeniorReplied:', data);
                var text = '🔔 Senior replied to your question:\n\n' + 
                           (data.reply || '') + 
                           '\n\nYour question: ' + (data.question || '');
                appendMessage('assistant', text, null, false);
                showSeniorReplyToast('Senior replied to your escalated question.');
                
                if (badgeEl) {
                    var n = parseInt(badgeEl.textContent || '0', 10) + 1;
                    badgeEl.textContent = n > 99 ? '99+' : n;
                    badgeEl.style.display = 'flex';
                }
                if (toggle) toggle.classList.add('has-notification');
                checkNotifications();
            });
        });

        pusher.connection.bind('failed', function() {
            console.error('❌ Failed to connect to Pusher');
        });

        pusher.connection.bind('error', function(err) {
            console.error('❌ Pusher error:', err);
        });

    } catch (e) {
        console.error('❌ Error initializing Pusher:', e);
    }
}

                function saveToStorage() {
                    try {
                        localStorage.setItem(STORAGE_KEY, JSON.stringify(chatMessages));
                    } catch (e) {}
                }

                function loadFromStorage() {
                    try {
                        const raw = localStorage.getItem(STORAGE_KEY);
                        if (raw) {
                            const parsed = JSON.parse(raw);
                            return Array.isArray(parsed) ? parsed : [];
                        }
                    } catch (e) {}
                    return [];
                }

                function clearHistory() {
                    try {
                        localStorage.removeItem(STORAGE_KEY);
                    } catch (e) {}
                    chatMessages = [];
                    messagesEl.innerHTML = '';
                }

                function renderStoredMessages() {
                    const stored = loadFromStorage();
                    chatMessages = stored;
                    stored.forEach(function(m) {
                        const role = (m.role === 'user' || m.role === 'assistant') ? m.role : 'assistant';
                        const content = (m.content != null) ? String(m.content) : '';
                        appendMessage(role, content, null, false, true);
                    });
                }

                function openPanel() {
                    panel.classList.add('is-open');
                    panel.setAttribute('aria-hidden', 'false');
                    inputEl.focus();
                    fetchPendingReplies();
                }

                function closePanel() {
                    panel.classList.remove('is-open');
                    panel.setAttribute('aria-hidden', 'true');
                }
                toggle.addEventListener('click', openPanel);
                closeBtn.addEventListener('click', closePanel);
                const clearHistoryBtn = document.getElementById('ai-chat-clear-history');
                if (clearHistoryBtn) {
                    clearHistoryBtn.addEventListener('click', function() {
                        clearHistory();
                    });
                }

                function escapeHtml(text) {
                    const div = document.createElement('div');
                    div.textContent = text == null ? '' : String(text);
                    return div.innerHTML;
                }

                function appendMessage(role, content, recordId, isEscalation, skipSave) {
                    const wrap = document.createElement('div');
                    wrap.className = 'ai-chat-msg ' + role;
                    const safe = escapeHtml(content);
                    let inner = safe.replace(/\n/g, '<br>');
                    // Backend already includes full escalation message in content; do not append duplicate text
                    if (role === 'assistant' && recordId) {
                        inner += '<div class="ai-chat-feedback" data-id="' + escapeHtml(String(recordId)) + '">';
                        inner += '<button type="button" class="ai-feedback-btn" data-helpful="1">Helpful</button>';
                        inner +=
                            '<button type="button" class="ai-feedback-btn" data-helpful="0">Not helpful</button></div>';
                    }
                    wrap.innerHTML = inner;
                    messagesEl.appendChild(wrap);
                    messagesEl.scrollTop = messagesEl.scrollHeight;
                    if (recordId && role === 'assistant') {
                        wrap.querySelectorAll('.ai-feedback-btn').forEach(function(btn) {
                            btn.addEventListener('click', function() {
                                sendFeedback(recordId, this.getAttribute('data-helpful') === '1', wrap);
                            });
                        });
                    }
                    if (!skipSave) {
                        chatMessages.push({ role: role, content: content, timestamp: Date.now() });
                        saveToStorage();
                    }
                }

                function sendFeedback(id, helpful, msgWrap) {
                    const fd = new FormData();
                    fd.append('_token', csrfToken);
                    fd.append('id', id);
                    fd.append('helpful', helpful ? '1' : '0');
                    fetch(routes.feedback, {
                            method: 'POST',
                            body: fd,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            },
                            credentials: 'same-origin'
                        })
                        .then(function(r) {
                            return r.json();
                        })
                        .then(function(data) {
                            if (data.success) {
                                var fb = msgWrap.querySelector('.ai-chat-feedback');
                                if (fb) {
                                    fb.querySelectorAll('button').forEach(function(b) {
                                        b.classList.remove('active');
                                    });
                                    msgWrap.querySelector('.ai-chat-feedback button[data-helpful="' + (helpful ? '1' :
                                        '0') + '"]').classList.add('active');
                                }
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

                function fetchPendingReplies() {
                    fetch(routes.pending, {
                            headers: {
                                'Accept': 'application/json'
                            },
                            credentials: 'same-origin'
                        })
                        .then(function(r) {
                            return r.json();
                        })
                        .then(function(data) {
                            var replies = data.replies || [];
                            if (replies.length === 0) return;
                            var ids = [];
                            replies.forEach(function(r) {
                                var text = '🔔 Senior replied to your question:\n\n' + (r.senior_reply || '') +
                                    '\n\nYour question: ' + (r.original_question || '');
                                appendMessage('assistant', text, null, false);
                                if (r.id) ids.push(r.id);
                            });
                            if (ids.length > 0) markRepliesAsRead(ids);
                        })
                        .catch(function() {});
                }

                function markRepliesAsRead(ids) {
                    var fd = new FormData();
                    fd.append('_token', csrfToken);
                    ids.forEach(function(id) {
                        fd.append('ids[]', id);
                    });
                    fetch(routes.markRead, {
                            method: 'POST',
                            body: fd,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            },
                            credentials: 'same-origin'
                        })
                        .then(function() {
                            checkNotifications();
                        })
                        .catch(function() {});
                }

                function checkNotifications() {
                    fetch(routes.check, {
                            headers: {
                                'Accept': 'application/json'
                            },
                            credentials: 'same-origin'
                        })
                        .then(function(r) {
                            return r.json();
                        })
                        .then(function(data) {
                            var count = data.count || 0;
                            if (badgeEl) {
                                if (count > 0) {
                                    badgeEl.textContent = count > 99 ? '99+' : count;
                                    badgeEl.style.display = 'flex';
                                } else {
                                    badgeEl.style.display = 'none';
                                }
                            }
                            if (toggle) {
                                if (count > 0) toggle.classList.add('has-notification');
                                else toggle.classList.remove('has-notification');
                            }
                        });
                }
                setInterval(checkNotifications, 30000);
                checkNotifications();

                attachBtn.addEventListener('click', function() {
                    fileInput.click();
                });
                fileInput.addEventListener('change', function() {
                    if (!this.files || !this.files[0]) return;
                    var fd = new FormData();
                    fd.append('_token', csrfToken);
                    fd.append('file', this.files[0]);
                    var uploadingEl = document.createElement('div');
                    uploadingEl.className = 'ai-chat-msg assistant';
                    uploadingEl.setAttribute('data-uploading', '1');
                    uploadingEl.textContent = 'Uploading file...';
                    messagesEl.appendChild(uploadingEl);
                    messagesEl.scrollTop = messagesEl.scrollHeight;
                    fetch(routes.upload, {
                            method: 'POST',
                            body: fd,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            },
                            credentials: 'same-origin'
                        })
                        .then(function(r) {
                            return r.json();
                        })
                        .then(function(data) {
                            var el = messagesEl.querySelector('[data-uploading="1"]');
                            if (el) el.remove();
                            var msg = data.success ? 'File uploaded and processed successfully.' : (data
                                .message || 'Upload failed.');
                            appendMessage('assistant', msg, null, false);
                        })
                        .catch(function() {
                            var el = messagesEl.querySelector('[data-uploading="1"]');
                            if (el) el.remove();
                            appendMessage('assistant', 'Upload failed. Please try again.', null, false);
                        });
                    this.value = '';
                });

                sendBtn.addEventListener('click', function() {
                    submitQuestion();
                });
                inputEl.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        submitQuestion();
                    }
                });

                function submitQuestion() {
                    var question = (inputEl.value || '').trim();
                    if (!question || sendBtn.disabled) return;
                    appendMessage('user', question);
                    inputEl.value = '';
                    setLoading(true);
                    var fd = new FormData();
                    fd.append('_token', csrfToken);
                    fd.append('question', question);
                    fetch(routes.chat, {
                            method: 'POST',
                            body: fd,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            },
                            credentials: 'same-origin'
                        })
                        .then(function(r) {
                            return r.json();
                        })
                        .then(function(data) {
                            setLoading(false);
                            var isEscalation = (data.status || '') === 'escalated';
                            appendMessage('assistant', data.answer || 'No response.', data.id || null, isEscalation);
                            if (data.error) appendMessage('assistant', data.error, null, false);
                        })
                        .catch(function() {
                            setLoading(false);
                            appendMessage('assistant', 'Something went wrong. Please try again.', null, false);
                        });
                }

                renderStoredMessages();
            })
            ();
        </script>
    @endif
@endauth
