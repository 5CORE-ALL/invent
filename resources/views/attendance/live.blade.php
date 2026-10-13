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
        .lv-stage video, .lv-stage canvas { max-width: 100%; max-height: 100%; object-fit: contain; display: none; background: #000; }
        .lv-stage video.is-on, .lv-stage canvas.is-on { display: block; width: 100%; height: 100%; }
        .lv-wait {
            position: absolute; inset: 0; display: flex; flex-direction: column;
            align-items: center; justify-content: center; text-align: center; padding: 2rem; gap: .5rem;
        }
        .lv-wait h2 { margin: 0; font-size: 1.05rem; }
        .lv-wait p { margin: 0; color: #94a3b8; font-size: .85rem; max-width: 30rem; line-height: 1.45; }
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
            <div class="lv-meta" id="liveMeta">Starting live video…</div>
        </div>
        <div class="lv-actions">
            <span class="lv-rec" id="recBadge"><span class="lv-rec-dot"></span> REC</span>
            <span class="lv-timer" id="liveTimer">00:00</span>
        </div>
    </div>
    <div class="lv-stage">
        <div class="lv-wait" id="liveWait">
            <div class="lv-spin"></div>
            <h2 id="waitTitle">Connecting live video</h2>
            <p id="waitText">Waiting for the employee desktop app to start sending the screen. This is a live stream, not a saved screenshot.</p>
        </div>
        <video id="liveVideo" autoplay muted playsinline></video>
        <canvas id="liveCanvas"></canvas>
    </div>
    <div class="lv-foot">Live video and recording run only while this window is open. Close the window to stop.</div>

    <script src="{{ asset('js/attendance-live-player.js') }}"></script>
    <script>
    (function () {
        const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const startUrl = @json($start_url);
        const defaultWaitTitle = @json($wait_title ?? 'Desktop app is not streaming yet');
        const defaultWaitText = @json($wait_text ?? 'Ask the employee to restart 5Core Attendance while this window stays open, or install the latest app from /attendance/agent.');
        let waitTitleCopy = defaultWaitTitle;
        let waitTextCopy = defaultWaitText;
        const video = document.getElementById('liveVideo');
        const canvas = document.getElementById('liveCanvas');
        const ctx = canvas.getContext('2d');
        const wait = document.getElementById('liveWait');
        const waitTitle = document.getElementById('waitTitle');
        const waitText = document.getElementById('waitText');
        const metaEl = document.getElementById('liveMeta');
        const timerEl = document.getElementById('liveTimer');
        const loader = new Image();
        let urls = null;
        let pollTimer = null;
        let pingTimer = null;
        let startedAt = Date.now();
        let firstFrameAt = 0;
        let lastObjectUrl = null;
        let lastEtag = null;
        let shuttingDown = false;
        let recorder = null;
        let recChunks = [];
        let recMime = '';
        let recPromise = null;
        let videoBound = false;
        let player = null;
        let streaming = false;

        function headers(json) {
            const h = { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' };
            if (json) h.Accept = 'application/json';
            return h;
        }

        function setWait(title, text, show) {
            waitTitle.textContent = title;
            waitText.textContent = text;
            wait.style.display = show ? 'flex' : 'none';
        }

        function formatClock(ms) {
            const s = Math.max(0, Math.floor(ms / 1000));
            const m = Math.floor(s / 60);
            return String(m).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
        }

        setInterval(function () {
            timerEl.textContent = formatClock(Date.now() - startedAt);
        }, 1000);

        function bindVideo() {
            if (videoBound || streaming) return;
            try {
                const stream = canvas.captureStream(8);
                video.srcObject = stream;
                video.classList.add('is-on');
                canvas.classList.add('is-on');
                video.play().catch(function () {});
                videoBound = true;
                startRecorder(stream);
            } catch (_) {
                canvas.classList.add('is-on');
            }
        }

        function pickRecorderMime() {
            const types = ['video/webm;codecs=vp9', 'video/webm;codecs=vp8', 'video/webm'];
            if (typeof MediaRecorder === 'undefined') return '';
            return types.find(function (t) { return MediaRecorder.isTypeSupported(t); }) || '';
        }

        function startRecorder(stream) {
            if (recorder || typeof MediaRecorder === 'undefined') return;
            recMime = pickRecorderMime();
            try {
                recorder = recMime
                    ? new MediaRecorder(stream, { mimeType: recMime, videoBitsPerSecond: 1200000 })
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

        function paintFrame(hideWait) {
            if (!loader.naturalWidth) return;
            if (canvas.width !== loader.naturalWidth || canvas.height !== loader.naturalHeight) {
                canvas.width = loader.naturalWidth;
                canvas.height = loader.naturalHeight;
            }
            ctx.drawImage(loader, 0, 0, canvas.width, canvas.height);
            bindVideo();
            if (hideWait) setWait('', '', false);
        }

        async function pullFrame() {
            if (!urls || shuttingDown || streaming) return false;
            try {
                const h = headers(false);
                if (lastEtag) h['If-None-Match'] = lastEtag;
                const r = await fetch(urls.frame, {
                    headers: h,
                    cache: 'no-store',
                    credentials: 'same-origin',
                });
                // Same frame already painted on the canvas.
                if (r.status === 304) return false;
                if (r.status === 204 || !r.ok) {
                    if (!firstFrameAt && Date.now() - startedAt > 8000) {
                        setWait(waitTitleCopy, waitTextCopy, true);
                    }
                    return false;
                }
                const type = (r.headers.get('Content-Type') || '').toLowerCase();
                if (type && type.indexOf('image/') !== 0) return false;
                const blob = await r.blob();
                if (!blob.size) return false;
                lastEtag = r.headers.get('ETag') || null;
                if (lastObjectUrl) URL.revokeObjectURL(lastObjectUrl);
                lastObjectUrl = URL.createObjectURL(blob);
                const source = (r.headers.get('X-Live-Source') || 'live').toLowerCase();
                const isLive = source === 'live';
                loader.onload = function () { paintFrame(isLive); };
                loader.src = lastObjectUrl;
                if (isLive) {
                    firstFrameAt = firstFrameAt || Date.now();
                } else if (!firstFrameAt && Date.now() - startedAt > 8000) {
                    setWait(waitTitleCopy, waitTextCopy, true);
                }
                const title = r.headers.get('X-Live-Window-Title') || '';
                const app = r.headers.get('X-Live-App-Name') || '';
                metaEl.textContent = [isLive ? 'Live video' : 'Last screen (waiting for live)', app, title].filter(Boolean).join(' — ');
                return true;
            } catch (_) {
                return false;
            }
        }

        // Chained rather than setInterval, so requests cannot overlap and the next
        // fetch starts as soon as the previous frame is on screen.
        async function pollLoop() {
            if (shuttingDown) return;
            const tickStartedAt = Date.now();
            const painted = await pullFrame();
            if (shuttingDown) return;
            const target = painted ? 120 : 320;
            pollTimer = setTimeout(pollLoop, Math.max(50, target - (Date.now() - tickStartedAt)));
        }

        // Encoded video when the agent can produce it. The canvas path stays wired
        // up underneath and takes back over if the stream never arrives or drops.
        function startVideoPlayer() {
            if (!window.AttendanceLivePlayer || !window.AttendanceLivePlayer.canPlay()) return;
            if (!urls || !urls.video) return;

            player = new window.AttendanceLivePlayer({
                url: urls.video,
                video: video,
                headers: headers(false),
                onActive: function () {
                    streaming = true;
                    firstFrameAt = firstFrameAt || Date.now();
                    if (pollTimer) { clearTimeout(pollTimer); pollTimer = null; }
                    canvas.classList.remove('is-on');
                    video.classList.add('is-on');
                    setWait('', '', false);
                    metaEl.textContent = 'Live video';
                    recordFromVideo();
                },
                onInactive: function () {
                    player = null;
                    streaming = false;
                    video.classList.remove('is-on');
                    if (!shuttingDown && !pollTimer) pollLoop();
                },
            });
            player.start();
        }

        // The recorder was fed by canvas.captureStream; with MSE the frames land
        // in the video element instead, so the saved recording comes from there.
        function recordFromVideo() {
            if (recorder) return;
            try {
                const grab = video.captureStream || video.mozCaptureStream;
                if (!grab) return;
                const stream = grab.call(video);
                if (stream) startRecorder(stream);
            } catch (_) {
                /* recording is best effort; live view still works */
            }
        }

        async function ping() {
            if (!urls || shuttingDown) return;
            try {
                await fetch(urls.ping, { method: 'POST', headers: headers(true), credentials: 'same-origin' });
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
                    credentials: 'same-origin',
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
                    credentials: 'same-origin',
                });
            } catch (_) {}
        }

        async function shutdown() {
            if (shuttingDown) return;
            shuttingDown = true;
            if (pollTimer) clearTimeout(pollTimer);
            if (pingTimer) clearInterval(pingTimer);
            beaconStop();
            // Stop fetching slices, but leave the recorder alone until it has
            // flushed what it already captured.
            if (player) { player.stop(); player = null; }
            const blob = await stopRecorder();
            await uploadRecording(blob);
        }

        async function start() {
            const r = await fetch(startUrl, { method: 'POST', headers: headers(true), credentials: 'same-origin' });
            if (!r.ok) {
                let message = 'You may not have access, or live watch is disabled.';
                let title = 'Cannot start live video';
                try {
                    const err = await r.json();
                    if (err && err.message) message = err.message;
                    if (err && err.off_duty) title = 'Off duty';
                } catch (_) {}
                setWait(title, message, true);
                return;
            }
            const data = await r.json();
            urls = data.urls;
            if (data.wait_title) waitTitleCopy = data.wait_title;
            if (data.wait_text) waitTextCopy = data.wait_text;
            startedAt = Date.now();
            setWait('Connecting live video', 'Asking the desktop app to stream this screen now. Recording starts when video appears.', true);
            pingTimer = setInterval(ping, 4000);
            pollLoop();
            startVideoPlayer();
            ping();
        }

        window.addEventListener('pagehide', function () { shutdown(); });
        window.addEventListener('beforeunload', function () { shutdown(); });
        start().catch(function () {
            setWait('Cannot start live video', 'The live session could not be created. Refresh and try again.', true);
        });
    })();
    </script>
</body>
</html>
