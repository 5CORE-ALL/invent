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
        html, body { margin: 0; height: 100%; background: #070b14; color: #e2e8f0; font-family: Inter, system-ui, -apple-system, sans-serif; }
        body { display: flex; flex-direction: column; overflow: hidden; }
        .tmv-bar {
            display: flex; align-items: center; justify-content: space-between; gap: 1rem;
            padding: .65rem 1rem; background: #111827; border-bottom: 1px solid #1f2937; flex-shrink: 0;
        }
        .tmv-title { font-weight: 700; font-size: 1rem; }
        .tmv-sub { font-size: .75rem; color: #94a3b8; margin-top: .1rem; }
        .tmv-bar-right { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; }
        .tmv-bar-right select, .tmv-bar-right a, .tmv-bar-right button {
            font: inherit; font-size: .78rem; border-radius: 8px; border: 1px solid #334155;
            background: #0f172a; color: #e2e8f0; padding: .35rem .65rem; cursor: pointer;
        }
        .tmv-bar-right a { text-decoration: none; display: inline-flex; align-items: center; }
        .tmv-bar-right a:hover, .tmv-bar-right button:hover { background: #1e293b; }
        .tmv-count { font-size: .75rem; color: #94a3b8; font-variant-numeric: tabular-nums; }
        .tmv-stage { flex: 1; min-height: 0; display: flex; flex-direction: column; }
        .tmv-grid {
            flex: 1; min-height: 0; display: grid; gap: 8px; padding: 8px;
            grid-template-columns: repeat(var(--cols, 1), minmax(0, 1fr));
            grid-template-rows: repeat(var(--rows, 1), minmax(0, 1fr));
        }
        .tmv-empty {
            flex: 1; display: none; align-items: center; justify-content: center;
            color: #64748b; font-size: .9rem; text-align: center; padding: 2rem;
        }
        .tmv-empty.is-on { display: flex; }
        .tmv-tile {
            position: relative; min-width: 0; min-height: 0; overflow: hidden;
            background: #020617; border: 1px solid #1f2937; border-radius: 10px;
        }
        .tmv-tile.is-min { display: none; }
        .tmv-tile img {
            width: 100%; height: 100%; object-fit: contain; display: block; background: #020617;
        }
        .tmv-tile img.is-poster { opacity: .45; filter: grayscale(.2); }
        /* Live frames are painted on a canvas (no image-swap flicker); it sits over the still poster. */
        .tmv-canvas {
            position: absolute; inset: 0; width: 100%; height: 100%;
            object-fit: contain; background: #020617; display: none;
        }
        .tmv-canvas.is-on { display: block; }
        .tmv-wait.is-error .tmv-spin { display: none; }
        .tmv-wait.is-error p { color: #fca5a5; font-size: .78rem; }
        .tmv-wait.is-live { background: rgba(2, 6, 23, .2); justify-content: flex-start; align-items: flex-start; padding: .5rem; }
        .tmv-wait.is-live .tmv-spin { width: 12px; height: 12px; }
        .tmv-wait.is-live p { margin-top: .25rem; background: rgba(2, 6, 23, .7); padding: .1rem .4rem; border-radius: 4px; }
        .tmv-wait {
            position: absolute; inset: 0; display: flex; flex-direction: column;
            align-items: center; justify-content: center; text-align: center; padding: 1rem;
            background: rgba(2, 6, 23, .35); pointer-events: none;
        }
        .tmv-wait.is-off { display: none; }
        .tmv-wait p { margin: .35rem 0 0; font-size: .72rem; color: #94a3b8; }
        .tmv-spin {
            width: 20px; height: 20px; border: 2px solid #334155; border-top-color: #38bdf8;
            border-radius: 50%; animation: tmvSpin .8s linear infinite;
        }
        .tmv-overlay {
            position: absolute; left: 0; right: 0; bottom: 0; display: flex;
            align-items: center; justify-content: space-between; gap: .5rem;
            padding: .45rem .55rem; background: linear-gradient(transparent, rgba(0,0,0,.78));
        }
        .tmv-who { min-width: 0; }
        .tmv-name { font-size: .78rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tmv-meta { font-size: .65rem; color: #cbd5e1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tmv-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: .3rem; vertical-align: middle; }
        .tmv-dot.working { background: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,.2); }
        .tmv-dot.idle { background: #eab308; box-shadow: 0 0 0 3px rgba(234,179,8,.2); }
        .tmv-dot.absent { background: #ef4444; box-shadow: 0 0 0 3px rgba(239,68,68,.2); }
        .tmv-controls { display: flex; gap: .3rem; flex-shrink: 0; }
        .tmv-controls button {
            width: 30px; height: 30px; border: 0; border-radius: 7px; cursor: pointer;
            background: rgba(15, 23, 42, .85); color: #f8fafc; display: inline-flex;
            align-items: center; justify-content: center;
        }
        .tmv-controls button:hover { background: #334155; }
        .tmv-controls button.is-on { background: #2563eb; }
        .tmv-controls button:disabled { opacity: .4; cursor: default; }
        .tmv-dock {
            display: none; flex-wrap: wrap; gap: .4rem; padding: .55rem .8rem;
            background: #111827; border-top: 1px solid #1f2937; flex-shrink: 0;
        }
        .tmv-dock.is-on { display: flex; }
        .tmv-chip {
            display: inline-flex; align-items: center; gap: .4rem;
            background: #0f172a; border: 1px solid #334155; border-radius: 999px;
            padding: .2rem .55rem .2rem .25rem; font-size: .72rem; cursor: pointer; color: #e2e8f0;
        }
        .tmv-chip:hover { background: #1e293b; }
        .tmv-chip-thumb {
            width: 22px; height: 16px; border-radius: 4px; object-fit: cover; background: #334155;
        }
        @keyframes tmvSpin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="tmv-bar">
        <div>
            <div class="tmv-title">Team Monitor Video</div>
            <div class="tmv-sub">Live screens for the team. Minimize a tile to let the rest fill the screen.</div>
        </div>
        <div class="tmv-bar-right">
            <span class="tmv-count" id="tmvCount"></span>
            <form method="get" id="teamForm">
                <input type="hidden" name="timezone" value="{{ $timezone }}">
                <select name="team" onchange="this.form.submit()" aria-label="Team">
                    <option value="all" {{ $team === 'all' ? 'selected' : '' }}>All Employees</option>
                    @foreach($teams as $t)
                    <option value="{{ $t }}" {{ $team === $t ? 'selected' : '' }}>{{ $t }}</option>
                    @endforeach
                </select>
            </form>
            <button type="button" id="btnPlayAll">Play all</button>
            <button type="button" id="btnPauseAll">Pause all</button>
            <a href="{{ $summary_url }}">← Team Monitoring</a>
        </div>
    </div>

    <div class="tmv-stage">
        <div class="tmv-grid" id="tmvGrid">
            @forelse($tiles as $tile)
            <article class="tmv-tile" data-user-id="{{ $tile['user_id'] }}" data-start-url="{{ $tile['start_url'] }}" data-name="{{ $tile['name'] }}">
                <img alt="" @if($tile['poster']) src="{{ $tile['poster'] }}" class="is-poster" @endif>
                <canvas class="tmv-canvas"></canvas>
                <div class="tmv-wait">
                    <div class="tmv-spin"></div>
                    <p>Connecting…</p>
                </div>
                <div class="tmv-overlay">
                    <div class="tmv-who">
                        <div class="tmv-name">
                            <span class="tmv-dot {{ $tile['live_status'] }}"></span>{{ $tile['name'] }}
                        </div>
                        <div class="tmv-meta">{{ $tile['live_label'] }}</div>
                    </div>
                    <div class="tmv-controls">
                        <button type="button" class="js-play" title="Play">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                        </button>
                        <button type="button" class="js-pause" title="Pause">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M6 5h4v14H6zm8 0h4v14h-4z"/></svg>
                        </button>
                        <button type="button" class="js-min" title="Minimize">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M5 12h14v2H5z"/></svg>
                        </button>
                    </div>
                </div>
            </article>
            @empty
            @endforelse
        </div>
        <div class="tmv-empty {{ $tiles->isEmpty() ? 'is-on' : '' }}" id="tmvEmpty">
            {{ $tiles->isEmpty() ? 'No employees in this team.' : 'All videos are minimized. Restore one from the bar below.' }}
        </div>
    </div>
    <div class="tmv-dock" id="tmvDock"></div>

    <script>
    (function () {
        const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const grid = document.getElementById('tmvGrid');
        const dock = document.getElementById('tmvDock');
        const empty = document.getElementById('tmvEmpty');
        const countEl = document.getElementById('tmvCount');
        const tiles = Array.from(document.querySelectorAll('.tmv-tile')).map(el => createTile(el));

        function headers(json) {
            const h = { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' };
            if (json) h.Accept = 'application/json';
            return h;
        }

        function bestGrid(n, width, height) {
            if (n <= 0) return { cols: 1, rows: 1 };
            let best = { cols: 1, rows: n, score: -1 };
            for (let cols = 1; cols <= n; cols += 1) {
                const rows = Math.ceil(n / cols);
                const tw = width / cols;
                const th = height / rows;
                if (tw < 80 || th < 70) continue;
                const ratio = tw / Math.max(1, th);
                const score = (1 / (1 + Math.abs(ratio - (16 / 9)))) * 0.7 + (n / (cols * rows)) * 0.3;
                if (score > best.score) best = { cols, rows, score };
            }
            return best;
        }

        function layout() {
            const visible = tiles.filter(t => !t.minimized);
            const n = visible.length;
            empty.classList.toggle('is-on', n === 0);
            grid.style.display = n === 0 ? 'none' : 'grid';
            const rect = grid.getBoundingClientRect();
            const fit = bestGrid(n, Math.max(1, rect.width), Math.max(1, rect.height));
            grid.style.setProperty('--cols', String(fit.cols));
            grid.style.setProperty('--rows', String(fit.rows));
            const playing = tiles.filter(t => t.playing && !t.minimized).length;
            countEl.textContent = playing + ' live · ' + n + ' on screen · ' + (tiles.length - n) + ' minimized';
            renderDock();
        }

        function renderDock() {
            const hidden = tiles.filter(t => t.minimized);
            dock.classList.toggle('is-on', hidden.length > 0);
            dock.innerHTML = hidden.map(t => {
                const src = t.thumb || t.img.currentSrc || t.img.src || '';
                return '<button type="button" class="tmv-chip" data-restore="' + t.userId + '">' +
                    (src ? '<img class="tmv-chip-thumb" alt="" src="' + src + '">' : '') +
                    t.name + ' · Restore</button>';
            }).join('');
        }

        const sleep = ms => new Promise(res => setTimeout(res, ms));

        // One tile = one live session. Frames are pulled one-at-a-time (never overlapping) and
        // painted on a canvas, so the picture updates smoothly instead of flashing between images.
        function createTile(el) {
            const img = el.querySelector('img');
            const canvas = el.querySelector('.tmv-canvas');
            const ctx = canvas.getContext('2d');
            const wait = el.querySelector('.tmv-wait');
            const waitText = wait.querySelector('p');
            const meta = el.querySelector('.tmv-meta');
            const playBtn = el.querySelector('.js-play');
            const pauseBtn = el.querySelector('.js-pause');
            const minBtn = el.querySelector('.js-min');
            const tile = {
                el, img, canvas, wait, meta, playBtn, pauseBtn,
                userId: el.dataset.userId,
                name: el.dataset.name || 'Employee',
                startUrl: el.dataset.startUrl,
                urls: null,
                playing: false,
                minimized: false,
                runId: 0,
                retryTimer: null,
                posterUrl: null,
                thumb: null,
                hasLive: false,
                lastLiveAt: 0,
                lastSessionAt: 0,
            };

            function showWait(text, mode) {
                waitText.textContent = text;
                wait.classList.remove('is-off', 'is-error', 'is-live');
                if (mode) wait.classList.add(mode);
            }

            function hideWait() {
                wait.classList.add('is-off');
            }

            async function startSession() {
                const body = new URLSearchParams();
                body.set('_token', csrf);
                body.set('source', 'wall');
                const r = await fetch(tile.startUrl, {
                    method: 'POST',
                    headers: Object.assign(headers(true), { 'Content-Type': 'application/x-www-form-urlencoded' }),
                    body: body.toString(),
                    credentials: 'same-origin',
                });
                if (!r.ok) {
                    let message = 'Could not start live view';
                    try { message = (await r.json()).message || message; } catch (_) {}
                    const err = new Error(message);
                    err.status = r.status;
                    throw err;
                }
                const data = await r.json();
                tile.urls = data.urls;
                tile.lastSessionAt = Date.now();
            }

            async function stopSession() {
                const urls = tile.urls;
                tile.urls = null;
                if (!urls) return;
                const body = new URLSearchParams();
                body.set('_token', csrf);
                body.set('reason', 'stopped');
                try {
                    await fetch(urls.stop, {
                        method: 'POST',
                        headers: Object.assign(headers(true), { 'Content-Type': 'application/x-www-form-urlencoded' }),
                        body: body.toString(),
                        keepalive: true,
                        credentials: 'same-origin',
                    });
                } catch (_) {}
            }

            async function drawLive(blob) {
                let bmp = null;
                if (window.createImageBitmap) {
                    try { bmp = await createImageBitmap(blob); } catch (_) { bmp = null; }
                }
                if (!bmp) {
                    bmp = await new Promise(function (resolve) {
                        const url = URL.createObjectURL(blob);
                        const im = new Image();
                        im.onload = function () { URL.revokeObjectURL(url); resolve(im); };
                        im.onerror = function () { URL.revokeObjectURL(url); resolve(null); };
                        im.src = url;
                    });
                }
                if (!bmp) return false;
                const w = bmp.width || bmp.naturalWidth;
                const h = bmp.height || bmp.naturalHeight;
                if (!w || !h) return false;
                if (canvas.width !== w || canvas.height !== h) {
                    canvas.width = w;
                    canvas.height = h;
                }
                ctx.drawImage(bmp, 0, 0, w, h);
                if (bmp.close) bmp.close();
                canvas.classList.add('is-on');
                return true;
            }

            function showPoster(blob) {
                const next = URL.createObjectURL(blob);
                const prev = tile.posterUrl;
                tile.posterUrl = next;
                img.classList.add('is-poster');
                img.src = next;
                if (prev) URL.revokeObjectURL(prev);
            }

            // Returns true if the session is still healthy, false if it should be re-created.
            async function pullFrame(runId) {
                const ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
                const abortTimer = ctrl ? setTimeout(() => ctrl.abort(), 8000) : null;
                try {
                    const r = await fetch(tile.urls.frame, {
                        headers: headers(false),
                        cache: 'no-store',
                        credentials: 'same-origin',
                        signal: ctrl ? ctrl.signal : undefined,
                    });
                    if (runId !== tile.runId) return true;
                    if (r.status === 403 || r.status === 404 || r.status === 419) return false;
                    if (r.status === 204 || !r.ok) {
                        if (!tile.hasLive) {
                            showWait('Waiting for desktop agent…');
                        } else if (Date.now() - tile.lastLiveAt > 6000) {
                            showWait('Reconnecting…', 'is-live');
                        }
                        return true;
                    }
                    const type = (r.headers.get('Content-Type') || '').toLowerCase();
                    if (type && type.indexOf('image/') !== 0) return true;
                    const blob = await r.blob();
                    if (runId !== tile.runId || !blob.size) return true;

                    const isLive = (r.headers.get('X-Live-Source') || 'live').toLowerCase() === 'live';
                    if (isLive) {
                        if (await drawLive(blob)) {
                            tile.hasLive = true;
                            tile.lastLiveAt = Date.now();
                            hideWait();
                            const title = r.headers.get('X-Live-Window-Title') || '';
                            const app = r.headers.get('X-Live-App-Name') || '';
                            meta.textContent = [app, title].filter(Boolean).join(' — ') || 'Live';
                        }
                    } else if (!tile.hasLive) {
                        // Only a saved screenshot so far: show it dimmed until real video arrives.
                        showPoster(blob);
                        showWait('Waiting for desktop agent…');
                    } else if (Date.now() - tile.lastLiveAt > 6000) {
                        showWait('Reconnecting…', 'is-live');
                    }
                    return true;
                } catch (_) {
                    return true;
                } finally {
                    if (abortTimer) clearTimeout(abortTimer);
                }
            }

            function frameDelay() {
                // Fewer tiles => faster refresh; many tiles => stay within the browser connection limit.
                const active = tiles.filter(t => t.playing && !t.minimized).length || 1;
                return Math.min(1000, 120 + active * 45);
            }

            async function loop(runId) {
                while (tile.playing && !tile.minimized && runId === tile.runId) {
                    const t0 = performance.now();
                    if (!tile.urls) {
                        try {
                            await startSession();
                        } catch (err) {
                            if (runId !== tile.runId) return;
                            handleStartError(err);
                            return;
                        }
                    }
                    const healthy = await pullFrame(runId);
                    if (runId !== tile.runId) return;

                    // Session lost, or the desktop agent never produced live video for a while:
                    // open a fresh session instead of spinning forever.
                    const stalled = !tile.hasLive && Date.now() - tile.lastSessionAt > 25000;
                    const dropped = tile.hasLive && Date.now() - tile.lastLiveAt > 20000;
                    if (!healthy || stalled || dropped) {
                        await stopSession();
                        tile.hasLive = false;
                        if (runId !== tile.runId) return;
                        await sleep(1000);
                        continue;
                    }
                    await sleep(Math.max(40, frameDelay() - (performance.now() - t0)));
                }
            }

            function scheduleRetry(ms) {
                clearTimeout(tile.retryTimer);
                tile.retryTimer = setTimeout(function () {
                    tile.retryTimer = null;
                    if (!tile.minimized) play();
                }, ms);
            }

            function handleStartError(err) {
                tile.playing = false;
                tile.urls = null;
                playBtn.classList.remove('is-on');
                showWait((err && err.message) || 'Could not start live view', 'is-error');
                meta.textContent = 'Not live';
                // Off duty / transient failure: keep checking so video starts by itself later.
                scheduleRetry(15000);
                layout();
            }

            async function play() {
                if (tile.playing) return;
                clearTimeout(tile.retryTimer);
                tile.retryTimer = null;
                tile.playing = true;
                tile.runId += 1;
                playBtn.classList.add('is-on');
                pauseBtn.classList.remove('is-on');
                if (!tile.hasLive) showWait('Connecting…');
                layout();
                loop(tile.runId);
            }

            async function pause() {
                clearTimeout(tile.retryTimer);
                tile.retryTimer = null;
                tile.playing = false;
                tile.runId += 1;
                playBtn.classList.remove('is-on');
                pauseBtn.classList.add('is-on');
                tile.hasLive = false;
                if (canvas.classList.contains('is-on') || img.src) {
                    showWait('Paused', 'is-live');
                } else {
                    showWait('Paused');
                }
                layout();
                await stopSession();
            }

            function minimize() {
                try {
                    tile.thumb = canvas.classList.contains('is-on') ? canvas.toDataURL('image/jpeg', 0.4) : null;
                } catch (_) { tile.thumb = null; }
                tile.minimized = true;
                el.classList.add('is-min');
                if (tile.playing || tile.retryTimer) pause();
                layout();
            }

            function restore() {
                tile.minimized = false;
                el.classList.remove('is-min');
                layout();
                play();
            }

            playBtn.addEventListener('click', function (e) { e.stopPropagation(); play(); });
            pauseBtn.addEventListener('click', function (e) { e.stopPropagation(); pause(); });
            minBtn.addEventListener('click', function (e) { e.stopPropagation(); minimize(); });

            tile.play = play;
            tile.pause = pause;
            tile.minimize = minimize;
            tile.restore = restore;
            tile.shutdown = function () {
                tile.playing = false;
                tile.runId += 1;
                clearTimeout(tile.retryTimer);
                if (tile.urls) {
                    const body = new URLSearchParams();
                    body.set('_token', csrf);
                    body.set('reason', 'viewer_closed');
                    try { navigator.sendBeacon(tile.urls.stop, body); } catch (_) {}
                }
            };
            return tile;
        }

        dock.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-restore]');
            if (!btn) return;
            const tile = tiles.find(t => String(t.userId) === String(btn.dataset.restore));
            if (tile) tile.restore();
        });

        document.getElementById('btnPlayAll').addEventListener('click', function () {
            tiles.filter(t => !t.minimized).forEach((t, i) => setTimeout(() => t.play(), i * 120));
        });
        document.getElementById('btnPauseAll').addEventListener('click', function () {
            tiles.forEach(t => t.pause());
        });

        window.addEventListener('resize', layout);
        window.addEventListener('pagehide', function () { tiles.forEach(t => t.shutdown()); });
        window.addEventListener('beforeunload', function () { tiles.forEach(t => t.shutdown()); });

        layout();
        tiles.forEach((t, i) => setTimeout(() => t.play(), 200 + i * 140));
    })();
    </script>
</body>
</html>
