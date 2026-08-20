<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; background: #0b1220; color: #e2e8f0; font-family: Inter, system-ui, -apple-system, sans-serif; }
        body { display: flex; flex-direction: column; }
        .lv-bar {
            display: flex; align-items: center; justify-content: space-between; gap: 1rem;
            padding: .7rem 1rem; background: #111827; border-bottom: 1px solid #1f2937;
        }
        .lv-who { min-width: 0; }
        .lv-name { font-weight: 700; font-size: .98rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .lv-meta { font-size: .75rem; color: #94a3b8; margin-top: .15rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .lv-actions { display: flex; align-items: center; gap: .75rem; flex-shrink: 0; }
        .lv-rec {
            display: inline-flex; align-items: center; gap: .4rem;
            font-size: .72rem; font-weight: 700; letter-spacing: .04em; color: #fecaca;
            background: rgba(239, 68, 68, .14); border: 1px solid rgba(239, 68, 68, .35);
            border-radius: 999px; padding: .25rem .65rem;
        }
        .lv-rec-dot {
            width: 8px; height: 8px; border-radius: 50%; background: #ef4444;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, .25); animation: lvPulse 1.2s infinite;
        }
        .lv-timer { font-variant-numeric: tabular-nums; font-weight: 700; color: #f8fafc; min-width: 4.2rem; text-align: right; }
        .lv-stage { flex: 1; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; background: #020617; }
        .lv-stage img { max-width: 100%; max-height: 100%; object-fit: contain; display: none; }
        .lv-stage img.is-on { display: block; }
        .lv-wait {
            position: absolute; inset: 0; display: flex; flex-direction: column;
            align-items: center; justify-content: center; text-align: center; padding: 2rem; gap: .5rem;
        }
        .lv-wait.has-image {
            inset: auto 0 0 0; background: linear-gradient(transparent, rgba(2,6,23,.82));
            padding: .7rem 1rem 1rem; justify-content: flex-end;
        }
        .lv-wait.has-image .lv-spin { display: none; }
        .lv-wait.has-image h2 { font-size: .85rem; }
        .lv-wait.has-image p { font-size: .72rem; }
        .lv-wait h2 { margin: 0; font-size: 1.05rem; }
        .lv-wait p { margin: 0; color: #94a3b8; font-size: .85rem; max-width: 28rem; line-height: 1.45; }
        .lv-spin {
            width: 28px; height: 28px; border: 3px solid #334155; border-top-color: #38bdf8;
            border-radius: 50%; animation: lvSpin .8s linear infinite; margin-bottom: .6rem;
        }
        .lv-foot { padding: .45rem 1rem; font-size: .72rem; color: #64748b; border-top: 1px solid #1f2937; background: #0f172a; }
        @keyframes lvPulse { 50% { opacity: .35; } }
        @keyframes lvSpin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="lv-bar">
        <div class="lv-who">
            <div class="lv-name">{{ $employee->name }}</div>
            <div class="lv-meta" id="liveMeta">Connecting to desktop agent…</div>
        </div>
        <div class="lv-actions">
            <span class="lv-rec" id="recBadge"><span class="lv-rec-dot"></span> REC</span>
            <span class="lv-timer" id="liveTimer">00:00</span>
        </div>
    </div>
    <div class="lv-stage">
        <div class="lv-wait {{ !empty($poster_url) ? 'has-image' : '' }}" id="liveWait">
            <div class="lv-spin"></div>
            <h2 id="waitTitle">{{ !empty($poster_url) ? 'Opening latest screen' : 'Starting live view' }}</h2>
            <p id="waitText">{{ !empty($poster_url) ? 'Showing the last captured screen. Live frames replace this as soon as the desktop agent streams.' : 'Asking the desktop agent to share this screen. Recording starts as soon as video appears and stops when you close this window.' }}</p>
        </div>
        <img id="liveFrame" alt="Live employee screen" @if(!empty($poster_url)) class="is-on" src="{{ $poster_url }}" @endif>
    </div>
    <div class="lv-foot">Live view and recording run only while this window is open. Close the window to stop.</div>

    <script>
    (function () {
        const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const startUrl = @json($start_url);
        const img = document.getElementById('liveFrame');
        const wait = document.getElementById('liveWait');
        const waitTitle = document.getElementById('waitTitle');
        const waitText = document.getElementById('waitText');
        const metaEl = document.getElementById('liveMeta');
        const timerEl = document.getElementById('liveTimer');
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        let urls = null;
        let pollTimer = null;
        let pingTimer = null;
        let startedAt = Date.now();
        let firstFrameAt = 0;
        let lastObjectUrl = null;
        let shuttingDown = false;
        let recorder = null;
        let recChunks = [];
        let recMime = '';
        let recPromise = null;

        function headers(json) {
            const h = { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' };
            if (json) h.Accept = 'application/json';
            return h;
        }

        function setWait(title, text, show) {
            waitTitle.textContent = title;
            waitText.textContent = text;
            wait.style.display = show ? 'flex' : 'none';
            wait.classList.toggle('has-image', img.classList.contains('is-on'));
        }

        function formatClock(ms) {
            const s = Math.max(0, Math.floor(ms / 1000));
            const m = Math.floor(s / 60);
            return String(m).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
        }

        setInterval(function () {
            timerEl.textContent = formatClock(Date.now() - startedAt);
        }, 1000);

        function pickRecorderMime() {
            const types = ['video/webm;codecs=vp9', 'video/webm;codecs=vp8', 'video/webm'];
            if (typeof MediaRecorder === 'undefined') return '';
            return types.find(function (t) { return MediaRecorder.isTypeSupported(t); }) || '';
        }

        function startRecorder() {
            if (recorder || typeof MediaRecorder === 'undefined') return;
            recMime = pickRecorderMime();
            if (!recMime) return;
            try {
                const stream = canvas.captureStream(4);
                recorder = recMime
                    ? new MediaRecorder(stream, { mimeType: recMime, videoBitsPerSecond: 900000 })
                    : new MediaRecorder(stream);
                recChunks = [];
                recorder.ondataavailable = function (e) {
                    if (e.data && e.data.size) recChunks.push(e.data);
                };
                recorder.start(2000);
            } catch (_) {
                recorder = null;
            }
        }

        function stopRecorder() {
            if (recPromise) return recPromise;
            recPromise = new Promise(function (resolve) {
                if (!recorder || recorder.state === 'inactive') {
                    resolve(recChunks.length ? new Blob(recChunks, { type: recMime || 'video/webm' }) : null);
                    return;
                }
                recorder.onstop = function () {
                    resolve(recChunks.length ? new Blob(recChunks, { type: recorder.mimeType || recMime || 'video/webm' }) : null);
                };
                try { recorder.requestData(); } catch (_) {}
                recorder.stop();
            });
            return recPromise;
        }

        function drawFrame() {
            if (!img.naturalWidth) return;
            if (canvas.width !== img.naturalWidth || canvas.height !== img.naturalHeight) {
                canvas.width = img.naturalWidth;
                canvas.height = img.naturalHeight;
            }
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
            if (!recorder) startRecorder();
        }

        async function pullFrame() {
            if (!urls || shuttingDown) return;
            try {
                const r = await fetch(urls.frame, {
                    headers: headers(false),
                    cache: 'no-store',
                    credentials: 'same-origin',
                });
                if (r.status === 204) {
                    if (!img.classList.contains('is-on') && Date.now() - startedAt > 8000) {
                        setWait(
                            'Waiting for desktop agent',
                            'No screen yet. The employee must be signed into 5Core Attendance. Last screenshots appear here as soon as they upload.',
                            true
                        );
                    }
                    return;
                }
                if (!r.ok) {
                    if (!img.classList.contains('is-on') && Date.now() - startedAt > 8000) {
                        setWait('Cannot load screen', 'Refresh this page and try again.', true);
                    }
                    return;
                }
                const type = (r.headers.get('Content-Type') || '').toLowerCase();
                if (type && type.indexOf('image/') !== 0) return;
                const blob = await r.blob();
                if (!blob.size) return;
                if (lastObjectUrl) URL.revokeObjectURL(lastObjectUrl);
                lastObjectUrl = URL.createObjectURL(blob);
                img.onload = function () {
                    img.classList.add('is-on');
                    const source = (r.headers.get('X-Live-Source') || '').toLowerCase();
                    if (source === 'live') {
                        setWait('', '', false);
                    } else {
                        setWait(
                            'Last captured screen',
                            'Live stream will replace this when the desktop agent is online.',
                            true
                        );
                    }
                    drawFrame();
                };
                img.src = lastObjectUrl;
                firstFrameAt = firstFrameAt || Date.now();
                const title = r.headers.get('X-Live-Window-Title') || '';
                const app = r.headers.get('X-Live-App-Name') || '';
                const source = (r.headers.get('X-Live-Source') || '').toLowerCase();
                metaEl.textContent = [source === 'live' ? 'Live' : 'Last screenshot', app, title].filter(Boolean).join(' — ');
            } catch (_) {}
        }

        async function ping() {
            if (!urls || shuttingDown) return;
            try {
                await fetch(urls.ping, { method: 'POST', headers: headers(true) });
            } catch (_) {}
        }

        function beaconStop() {
            if (!urls) return;
            const body = new URLSearchParams();
            body.set('_token', csrf);
            body.set('reason', 'viewer_closed');
            try {
                navigator.sendBeacon(urls.stop, body);
            } catch (_) {
                fetch(urls.stop, {
                    method: 'POST',
                    headers: Object.assign(headers(true), { 'Content-Type': 'application/x-www-form-urlencoded' }),
                    body: body.toString(),
                    keepalive: true,
                }).catch(function () {});
            }
        }

        async function uploadRecording(blob) {
            if (!urls || !blob || blob.size < 1000) return;
            const form = new FormData();
            form.append('_token', csrf);
            form.append('recording', blob, 'live.webm');
            try {
                await fetch(urls.recording, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body: form,
                    keepalive: true,
                });
            } catch (_) {}
        }

        async function shutdown() {
            if (shuttingDown) return;
            shuttingDown = true;
            if (pollTimer) clearInterval(pollTimer);
            if (pingTimer) clearInterval(pingTimer);
            beaconStop();
            const blob = await stopRecorder();
            await uploadRecording(blob);
        }

        async function start() {
            const r = await fetch(startUrl, { method: 'POST', headers: headers(true), credentials: 'same-origin' });
            if (!r.ok) {
                setWait('Cannot start live view', 'You may not have access, or live watch is disabled.', true);
                return;
            }
            const data = await r.json();
            urls = data.urls;
            startedAt = Date.now();
            setWait('Connecting to employee screen', 'The desktop agent has been asked to stream. Recording begins when video appears and continues until this window closes.', true);
            pollTimer = setInterval(pullFrame, 350);
            pingTimer = setInterval(ping, 4000);
            pullFrame();
            ping();
        }

        window.addEventListener('pagehide', function () { shutdown(); });
        window.addEventListener('beforeunload', function () { shutdown(); });
        start().catch(function () {
            setWait('Cannot start live view', 'The live session could not be created. Refresh and try again.', true);
        });
    })();
    </script>
</body>
</html>
