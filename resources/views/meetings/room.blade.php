@extends('layouts.vertical', ['title' => $meeting->title, 'mode' => $mode ?? '', 'demo' => $demo ?? '', 'hideMeetingFab' => true])

@section('css')
    <style>
        .mr-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 16px; background: #0f172a; color: #fff; border-radius: 10px 10px 0 0; padding: 8px 14px; }
        .mr-bar .mr-title { font-weight: 700; margin-right: auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .mr-rec { display: inline-flex; align-items: center; gap: 6px; font-weight: 700; font-size: .85rem; background: #7f1d1d; border-radius: 14px; padding: 2px 10px; }
        .mr-rec i { width: 9px; height: 9px; border-radius: 50%; background: #f87171; animation: mrPulse 1.2s infinite; }
        @keyframes mrPulse { 0%,100% { opacity: 1 } 50% { opacity: .2 } }
        .mr-people { display: flex; flex-wrap: wrap; gap: 6px; padding: 6px 12px; background: #f8fafc; border: 1px solid #e5e9f0; border-top: 0; font-size: .8rem; }
        .mr-chip { display: inline-flex; align-items: center; gap: 5px; border-radius: 12px; padding: 1px 9px; background: #e2e8f0; color: #475569; }
        .mr-chip.rec { background: #dcfce7; color: #166534; }
        .mr-chip.warn { background: #fef3c7; color: #92400e; }
        .mr-chip i { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
        #mrStage { position: relative; height: calc(100vh - 250px); min-height: 480px; background: #020617; border-radius: 0 0 10px 10px; overflow: hidden; }
        #mrJitsi { position: absolute; inset: 0; }
        .mr-overlay { position: absolute; inset: 0; z-index: 5; display: flex; align-items: center; justify-content: center; padding: 20px; background: #f1f5f9; }
        .mr-overlay.dark { background: rgba(2,6,23,.92); color: #fff; }
        .mr-panel { max-width: 560px; width: 100%; background: #fff; color: #0f172a; border-radius: 12px; padding: 24px; box-shadow: 0 10px 30px rgba(15,23,42,.15); }
        .mr-overlay.dark .mr-panel { background: #1e293b; color: #fff; }
        .mr-consent { background: #fef2f2; border: 1px solid #fecaca; color: #7f1d1d; border-radius: 8px; padding: 10px 12px; font-size: .88rem; }
        .mr-hidden { display: none !important; }
        .mr-warn { position: absolute; top: 8px; left: 50%; transform: translateX(-50%); z-index: 4; background: #f59e0b; color: #1c1917; font-weight: 600; font-size: .82rem; border-radius: 8px; padding: 5px 12px; }
    </style>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-12 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h4 class="page-title mb-0">Meeting Room</h4>
                <a href="{{ route('meetings.index') }}" class="btn btn-outline-secondary btn-sm" id="mrBack">&larr; All meetings</a>
            </div>
        </div>

        <div class="mr-bar">
            <span class="mr-title">{{ $meeting->title }}</span>
            <span class="small text-white-50">Host: {{ $meeting->host?->name ?? '—' }}</span>
            <span class="mr-rec mr-hidden" id="mrRecBadge"><i></i>REC <span id="mrRecTimer">00:00</span></span>
            <button type="button" class="btn btn-light btn-sm mr-hidden" id="mrLeave"><i class="ri-logout-box-r-line me-1"></i>Leave</button>
            @if($isHost)
                <button type="button" class="btn btn-danger btn-sm mr-hidden" id="mrEnd"><i class="ri-stop-circle-line me-1"></i>End for everyone</button>
            @endif
        </div>
        <div class="mr-people mr-hidden" id="mrPeople"></div>

        <div id="mrStage">
            <div id="mrJitsi"></div>
            <div class="mr-warn mr-hidden" id="mrWarn"></div>

            {{-- Gate: nobody gets in without the recorder running --}}
            <div class="mr-overlay" id="mrGate">
                <div class="mr-panel">
                    <h5 class="mb-1">{{ $meeting->title }}</h5>
                    @if($meeting->agenda)<p class="text-secondary small mb-3" style="white-space:pre-line">{{ $meeting->agenda }}</p>@endif
                    <div class="mr-consent mb-3">
                        <strong><i class="ri-record-circle-line me-1"></i>This meeting is recorded &mdash; recording is mandatory.</strong>
                        <ul class="mb-0 mt-1 ps-3">
                            <li>Your microphone is recorded in your browser while you are in the room (nothing is recorded while you are muted).</li>
                            <li>The recording is transcribed by AI. AI minutes and follow-up tasks are created after the meeting.</li>
                            <li>If recording stops, you are removed from the room until it is running again.</li>
                        </ul>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="mrConsent">
                        <label class="form-check-label" for="mrConsent">I understand and agree to be recorded.</label>
                    </div>
                    <div class="alert alert-danger py-2 mr-hidden" id="mrGateError"></div>
                    <button type="button" class="btn btn-primary" id="mrJoin" disabled>
                        <i class="ri-mic-line me-1"></i>Allow microphone &amp; join
                    </button>
                </div>
            </div>

            {{-- Shown when recording stops mid-meeting, or the meeting ended --}}
            <div class="mr-overlay dark mr-hidden" id="mrBlock">
                <div class="mr-panel">
                    <h5 id="mrBlockTitle" class="mb-2"></h5>
                    <p id="mrBlockText" class="mb-3"></p>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-primary mr-hidden" id="mrResume"><i class="ri-mic-line me-1"></i>Resume recording &amp; rejoin</button>
                        <a class="btn btn-light mr-hidden" id="mrToMinutes" href="{{ route('meetings.minutes.show', $meeting) }}">Open meeting minutes</a>
                        <a class="btn btn-outline-light mr-hidden" id="mrToLobby" href="{{ route('meetings.index') }}">Back to meetings</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    @php
        $roomCfg = [
            'joinUrl' => route('meetings.join', $meeting),
            'heartbeatUrl' => route('meetings.heartbeat', $meeting),
            'recordingUrl' => route('meetings.recording', $meeting),
            'leaveUrl' => route('meetings.leave', $meeting),
            'endUrl' => route('meetings.end', $meeting),
            'minutesUrl' => route('meetings.minutes.show', $meeting),
            'domain' => $jitsiDomain,
            'isHost' => $isHost,
        ];
    @endphp
    <script>
        (function () {
            var CFG = @json($roomCfg);
            var CSRF = @json(csrf_token());
            var CHUNK_MS = 5000;

            var $ = function (id) { return document.getElementById(id); };
            var show = function (id, on) { $(id).classList.toggle('mr-hidden', on === false); };

            // ---- state ----------------------------------------------------------------
            var S = {
                inRoom: false, leaving: false, ended: false,
                session: null, clockOffset: 0,
                ctx: null, stream: null, micTrack: null, gain: null, recorder: null,
                api: null, hbTimer: null, recTimer: null, recStartedAt: 0,
                queue: [], uploading: false, closing: false, uploadFail: 0
            };

            function sleep(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }

            function api(url, body) {
                return fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: body || new FormData(),
                    credentials: 'same-origin'
                });
            }

            function pickMime() {
                var list = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/mp4'];
                for (var i = 0; i < list.length; i++) {
                    if (window.MediaRecorder && MediaRecorder.isTypeSupported(list[i])) return list[i];
                }
                return '';
            }

            // ---- gate -----------------------------------------------------------------
            $('mrConsent').addEventListener('change', function () { $('mrJoin').disabled = !this.checked; });
            $('mrJoin').addEventListener('click', function () { enter($('mrGate'), $('mrGateError')); });
            $('mrResume').addEventListener('click', function () {
                enter($('mrBlock'), null);
            });

            function showError(el, msg) {
                if (el) { el.textContent = msg; el.classList.remove('mr-hidden'); }
                else { block('Could not resume', msg, { resume: true }); }
            }

            function block(title, text, opts) {
                opts = opts || {};
                $('mrBlockTitle').textContent = title;
                $('mrBlockText').textContent = text;
                show('mrResume', !!opts.resume);
                show('mrToMinutes', !!opts.minutes);
                show('mrToLobby', !!opts.lobby);
                show('mrBlock', true);
            }

            // ---- enter room -----------------------------------------------------------
            async function enter(overlay, errEl) {
                if (!window.MediaRecorder || !(window.AudioContext || window.webkitAudioContext)) {
                    return showError(errEl, 'This browser cannot record audio, so it cannot join a recorded meeting. Please use a current Chrome, Edge or Firefox.');
                }
                var mime = pickMime();

                // Must be created inside the click so the browser lets it run.
                var ctx = new (window.AudioContext || window.webkitAudioContext)();
                try { await ctx.resume(); } catch (e) {}

                var stream;
                try {
                    stream = await navigator.mediaDevices.getUserMedia({
                        audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true }
                    });
                } catch (e) {
                    ctx.close();
                    return showError(errEl, 'Microphone access is required: this meeting is recorded and you cannot join without it. Allow the microphone in your browser and try again.');
                }

                var info;
                try {
                    var res = await api(CFG.joinUrl);
                    info = await res.json();
                    if (!res.ok || !info.ok) throw new Error(info.message || 'Could not join the meeting.');
                } catch (e) {
                    stream.getTracks().forEach(function (t) { t.stop(); });
                    ctx.close();
                    return showError(errEl, e.message || 'Could not join the meeting.');
                }

                S.session = info.session;
                S.clockOffset = info.server_time_ms - Date.now();
                S.ctx = ctx; S.stream = stream; S.micTrack = stream.getAudioTracks()[0];
                S.leaving = false; S.closing = false;

                try {
                    await startRecorder(mime);
                } catch (e) {
                    teardownRecorder();
                    return showError(errEl, 'Could not start the recording: ' + (e.message || e) + '. You cannot join without it.');
                }

                S.inRoom = true;
                overlay.classList.add('mr-hidden');
                show('mrBlock', false);
                ['mrRecBadge', 'mrPeople', 'mrLeave', 'mrEnd'].forEach(function (id) { if ($(id)) show(id, true); });
                startTimers();

                try {
                    await loadJitsi(info);
                } catch (e) {
                    // Recording keeps running; tell the user the video server is the problem.
                    warn('Could not load the video server (' + CFG.domain + '). Check MEETING_JITSI_DOMAIN / network.');
                }
            }

            // ---- recorder (mic -> gain(mute-aware) -> MediaRecorder) --------------------
            function startRecorder(mime) {
                return new Promise(function (resolve, reject) {
                    var src = S.ctx.createMediaStreamSource(S.stream);
                    S.gain = S.ctx.createGain();
                    // Silent until Jitsi tells us the mic state, so a muted person is never recorded.
                    S.gain.gain.value = 0;
                    var dest = S.ctx.createMediaStreamDestination();
                    src.connect(S.gain); S.gain.connect(dest);

                    var opts = { audioBitsPerSecond: 24000 };
                    if (mime) opts.mimeType = mime;
                    var rec = new MediaRecorder(dest.stream, opts);
                    var session = S.session;
                    var mimeUsed = rec.mimeType || mime || 'audio/webm';
                    var startedMs = 0;
                    var seqCounter = 0; // per recorder, so a late chunk of an old session can never skew a new one

                    rec.onstart = function () {
                        startedMs = Date.now() + S.clockOffset;
                        S.recStartedAt = Date.now();
                        resolve();
                    };
                    rec.ondataavailable = function (e) {
                        if (!e.data || !e.data.size) return;
                        var seq = seqCounter++;
                        S.queue.push({ seq: seq, blob: e.data, session: session, mime: mimeUsed, startedMs: seq === 0 ? startedMs : 0 });
                        flush();
                    };
                    rec.onerror = function (e) { recordingFailed('The recorder reported an error.'); };
                    rec.onstop = function () {
                        if (!S.leaving) recordingFailed('Recording stopped.');
                    };
                    S.micTrack.onended = function () {
                        if (!S.leaving) recordingFailed('Your microphone was disconnected or blocked.');
                    };
                    S.ctx.onstatechange = function () {
                        if (S.ctx && S.ctx.state === 'suspended') S.ctx.resume().catch(function () {});
                    };

                    S.recorder = rec;
                    try { rec.start(CHUNK_MS); } catch (e) { reject(e); }
                });
            }

            function recordingHealthy() {
                return !!(S.recorder && S.recorder.state === 'recording'
                    && S.micTrack && S.micTrack.readyState === 'live'
                    && S.ctx && S.ctx.state === 'running');
            }

            function setMuted(muted) {
                if (S.gain) S.gain.gain.value = muted ? 0 : 1;
            }

            function teardownRecorder() {
                try { if (S.recorder && S.recorder.state !== 'inactive') S.recorder.stop(); } catch (e) {}
                try { if (S.stream) S.stream.getTracks().forEach(function (t) { t.stop(); }); } catch (e) {}
                try { if (S.ctx && S.ctx.state !== 'closed') S.ctx.close(); } catch (e) {}
                S.recorder = null; S.stream = null; S.micTrack = null; S.ctx = null; S.gain = null;
            }

            // ---- upload queue (sequential, retried, resumable) ------------------------
            async function flush() {
                if (S.uploading) return;
                S.uploading = true;
                try {
                    while (S.queue.length) {
                        var item = S.queue[0];
                        var outcome = await upload(item);
                        if (outcome === 'ok') { S.queue.shift(); S.uploadFail = 0; updateWarn(); continue; }
                        if (outcome === 'fatal') { S.queue.shift(); continue; }
                        S.uploadFail++;
                        updateWarn();
                        if (S.closing && S.uploadFail >= 6) { S.queue = []; break; }
                        await sleep(Math.min(1500 * S.uploadFail, 12000));
                    }
                } finally { S.uploading = false; }
            }

            async function upload(item) {
                var fd = new FormData();
                fd.append('session', item.session);
                fd.append('seq', item.seq);
                fd.append('mime', item.mime);
                if (item.seq === 0) fd.append('started_at_ms', item.startedMs);
                fd.append('chunk', item.blob, 'chunk.bin');
                try {
                    var res = await api(CFG.recordingUrl, fd);
                    if (res.ok) return 'ok';
                    if (res.status === 409) {
                        var j = await res.json().catch(function () { return {}; });
                        if (typeof j.expected === 'number') return j.expected > item.seq ? 'ok' : 'fatal';
                        return 'fatal'; // recording closed for this meeting
                    }
                    if (res.status === 403 || res.status === 422) return 'fatal';
                    return 'retry';
                } catch (e) { return 'retry'; }
            }

            async function waitForUploads(maxMs) {
                var until = Date.now() + maxMs;
                flush();
                while ((S.queue.length || S.uploading) && Date.now() < until) await sleep(300);
            }

            // ---- jitsi ----------------------------------------------------------------
            function loadJitsi(info) {
                return new Promise(function (resolve, reject) {
                    function boot() {
                        try {
                            S.api = new JitsiMeetExternalAPI(CFG.domain, {
                                roomName: info.room_name,
                                parentNode: $('mrJitsi'),
                                width: '100%', height: '100%',
                                userInfo: { displayName: info.display_name, email: info.email },
                                configOverwrite: {
                                    prejoinConfig: { enabled: false },
                                    prejoinPageEnabled: false,
                                    disableDeepLinking: true,
                                    disableInviteFunctions: true,
                                    startWithAudioMuted: false,
                                    startWithVideoMuted: false,
                                    doNotStoreRoom: true,
                                    subject: @json($meeting->title),
                                    toolbarButtons: ['microphone', 'camera', 'desktop', 'fullscreen', 'fodeviceselection',
                                        'hangup', 'chat', 'raisehand', 'participants-pane', 'tileview',
                                        'select-background', 'settings', 'videoquality', 'noisesuppression', 'closedcaptions']
                                },
                                interfaceConfigOverwrite: { SHOW_PROMOTIONAL_CLOSE_PAGE: false, MOBILE_APP_PROMO: false }
                            });
                        } catch (e) { return reject(e); }

                        S.api.addListener('videoConferenceJoined', function () {
                            S.api.isAudioMuted().then(setMuted).catch(function () { setMuted(false); });
                        });
                        S.api.addListener('audioMuteStatusChanged', function (e) { setMuted(!!e.muted); });
                        S.api.addListener('videoConferenceLeft', function () { leave('left'); });
                        S.api.addListener('readyToClose', function () { leave('left'); });
                        resolve();
                    }
                    if (window.JitsiMeetExternalAPI) return boot();
                    var s = document.createElement('script');
                    s.src = 'https://' + CFG.domain + '/external_api.js';
                    s.onload = boot;
                    s.onerror = function () { reject(new Error('script load failed')); };
                    document.head.appendChild(s);
                });
            }

            function disposeJitsi() {
                try { if (S.api) S.api.dispose(); } catch (e) {}
                S.api = null;
            }

            // ---- enforcement ----------------------------------------------------------
            function recordingFailed(reason) {
                if (S.leaving || !S.inRoom) return;
                S.inRoom = false;
                disposeJitsi();
                var oldSession = S.session;
                var gap = new FormData(); gap.append('recording', '0');
                api(CFG.heartbeatUrl, gap).catch(function () {}); // server logs a recording gap for this person
                teardownRecorder();          // flushes the final chunk into the queue
                stopTimers();
                ['mrRecBadge', 'mrLeave', 'mrEnd'].forEach(function (id) { if ($(id)) show(id, false); });
                block('Recording stopped — you have been removed from the room',
                    reason + ' Meetings cannot continue without recording. Allow the microphone and resume to rejoin.',
                    { resume: true, lobby: true });
                // finalize the old session on the server (its queued chunks still upload)
                var fd = new FormData(); fd.append('session', oldSession);
                waitForUploads(20000).then(function () { return api(CFG.leaveUrl, fd); }).catch(function () {});
            }

            // ---- leave / end ----------------------------------------------------------
            async function leave(why) {
                if (S.leaving) return;
                S.leaving = true;
                S.closing = true;
                var wasInRoom = S.inRoom;
                S.inRoom = false;
                stopTimers();
                disposeJitsi();
                ['mrRecBadge', 'mrPeople', 'mrLeave', 'mrEnd'].forEach(function (id) { if ($(id)) show(id, false); });
                block(why === 'ended' ? 'The host ended the meeting' : 'Saving your recording…',
                    'Please keep this tab open while the last part of your recording uploads.', {});

                if (wasInRoom || S.queue.length) {
                    await new Promise(function (resolve) {
                        var done = false;
                        var finish = function () { if (!done) { done = true; resolve(); } };
                        if (S.recorder && S.recorder.state !== 'inactive') {
                            S.recorder.addEventListener('stop', function () { setTimeout(finish, 150); });
                            try { S.recorder.stop(); } catch (e) { finish(); }
                            setTimeout(finish, 3000);
                        } else { finish(); }
                    });
                }
                await waitForUploads(60000);

                var fd = new FormData(); fd.append('session', S.session || '');
                try { await api(CFG.leaveUrl, fd); } catch (e) {}
                teardownRecorder();
                S.session = null;

                block('You have left the meeting',
                    'Your recording was saved. AI will write the minutes and create follow-up tasks within a few minutes after the meeting ends.',
                    { minutes: true, lobby: true });
            }

            $('mrLeave').addEventListener('click', function () { leave('left'); });
            if ($('mrEnd')) {
                $('mrEnd').addEventListener('click', async function () {
                    if (!confirm('End this meeting for everyone? The recording will be processed into minutes and tasks.')) return;
                    try { await api(CFG.endUrl); } catch (e) {}
                    leave('ended');
                });
            }

            // ---- heartbeat / participants ---------------------------------------------
            function startTimers() {
                S.hbTimer = setInterval(heartbeat, 15000);
                S.recTimer = setInterval(function () {
                    var s = Math.floor((Date.now() - S.recStartedAt) / 1000);
                    var h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), ss = s % 60;
                    $('mrRecTimer').textContent = (h ? h + ':' : '') + String(m).padStart(2, '0') + ':' + String(ss).padStart(2, '0');
                }, 1000);
                heartbeat();
            }
            function stopTimers() { clearInterval(S.hbTimer); clearInterval(S.recTimer); }

            async function heartbeat() {
                if (!S.inRoom || S.leaving) return;
                if (S.ctx && S.ctx.state === 'suspended') { try { await S.ctx.resume(); } catch (e) {} }
                var healthy = recordingHealthy();
                if (!healthy) { recordingFailed('The recorder is no longer running.'); return; }
                var fd = new FormData(); fd.append('recording', '1');
                try {
                    var res = await api(CFG.heartbeatUrl, fd);
                    if (!res.ok) return;
                    var d = await res.json();
                    renderPeople(d.participants || []);
                    if (d.status === 'ended' && !S.leaving) leave('ended');
                } catch (e) { /* offline: recording keeps buffering, retry next tick */ }
            }

            function renderPeople(list) {
                var box = $('mrPeople');
                box.innerHTML = '';
                list.forEach(function (p) {
                    var c = document.createElement('span');
                    c.className = 'mr-chip ' + (p.in_room ? (p.recording ? 'rec' : 'warn') : '');
                    var dot = document.createElement('i');
                    c.appendChild(dot);
                    c.appendChild(document.createTextNode(p.name + (p.in_room ? (p.recording ? ' · recording' : ' · not recording') : ' · left')));
                    box.appendChild(c);
                });
            }

            function warn(msg) { var w = $('mrWarn'); w.textContent = msg; w.classList.remove('mr-hidden'); }
            function updateWarn() {
                if (S.uploadFail >= 3) warn('Connection problem — your recording is being saved locally and will upload when the connection returns.');
                else if (S.uploadFail === 0) $('mrWarn').classList.add('mr-hidden');
            }

            // ---- page lifecycle -------------------------------------------------------
            window.addEventListener('beforeunload', function (e) {
                if (S.inRoom && !S.leaving) { e.preventDefault(); e.returnValue = ''; }
            });
            window.addEventListener('pagehide', function () {
                if (S.inRoom && !S.leaving && S.session) {
                    var fd = new FormData();
                    fd.append('_token', CSRF); fd.append('session', S.session);
                    navigator.sendBeacon(CFG.leaveUrl, fd);
                }
            });
        })();
    </script>
@endsection
