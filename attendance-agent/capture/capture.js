/*
 * Screen encoder.
 *
 * Holds one long-lived MediaStream and lets MediaRecorder encode it, instead of
 * grabbing a fresh screenshot per frame. Keeping the stream open is what makes
 * the result look like video: the capture pipeline stays warm and the codec can
 * send only what changed between frames.
 *
 * Every failure path reports back so the main process can drop to the old
 * still-image mode rather than leaving a viewer with a dead screen.
 */

let stream = null;
let recorder = null;
let generation = '';
let sequence = 0;

const CODECS = [
    'video/webm;codecs=vp8',
    'video/webm;codecs=vp9',
    'video/webm',
];

function pickMime() {
    if (typeof MediaRecorder === 'undefined') return null;
    return CODECS.find((type) => {
        try {
            return MediaRecorder.isTypeSupported(type);
        } catch (_) {
            return false;
        }
    }) || null;
}

function report(state, detail) {
    try {
        window.capture.sendStatus({ state, detail: detail ? String(detail) : '' });
    } catch (_) {
        /* main process is gone; nothing useful left to do */
    }
}

async function acquireStream(opts) {
    const width = Math.max(640, Number(opts.width) || 1280);
    const height = Math.max(360, Number(opts.height) || 720);
    const fps = Math.max(5, Math.min(30, Number(opts.fps) || 20));
    const errors = [];

    // Electron's own constraint form. Unlike getDisplayMedia it needs no user
    // gesture and never shows a picker, which matters for a background agent.
    if (opts.sourceId) {
        try {
            return await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: {
                    mandatory: {
                        chromeMediaSource: 'desktop',
                        chromeMediaSourceId: opts.sourceId,
                        maxWidth: width,
                        maxHeight: height,
                        maxFrameRate: fps,
                    },
                },
            });
        } catch (err) {
            errors.push(`getUserMedia: ${err.message}`);
        }
    }

    // Newer supported path. The main process answers the picker automatically
    // through setDisplayMediaRequestHandler.
    try {
        return await navigator.mediaDevices.getDisplayMedia({
            audio: false,
            video: { frameRate: fps, width: { max: width }, height: { max: height } },
        });
    } catch (err) {
        errors.push(`getDisplayMedia: ${err.message}`);
    }

    throw new Error(errors.join(' | ') || 'no capture source');
}

function teardown() {
    if (recorder && recorder.state !== 'inactive') {
        try {
            recorder.stop();
        } catch (_) {
            /* already stopping */
        }
    }
    recorder = null;

    if (stream) {
        stream.getTracks().forEach((track) => {
            try {
                track.stop();
            } catch (_) {
                /* already stopped */
            }
        });
    }
    stream = null;
}

async function start(opts) {
    teardown();

    const mime = pickMime();
    if (!mime) {
        report('unsupported', 'MediaRecorder has no usable WebM codec');
        return;
    }

    try {
        stream = await acquireStream(opts);
    } catch (err) {
        report('unsupported', err.message);
        return;
    }

    const track = stream.getVideoTracks()[0];
    if (!track) {
        teardown();
        report('unsupported', 'capture returned no video track');
        return;
    }

    // Fires when the OS revokes the share, e.g. a display is unplugged or the
    // user withdraws Screen Recording permission while the agent is running.
    track.addEventListener('ended', () => {
        teardown();
        report('ended', 'capture track ended');
    });

    generation = opts.gen || String(Date.now());
    sequence = 0;

    try {
        recorder = new MediaRecorder(stream, {
            mimeType: mime,
            videoBitsPerSecond: Math.max(200000, Number(opts.bitrate) || 1200000),
        });
    } catch (err) {
        teardown();
        report('unsupported', `MediaRecorder: ${err.message}`);
        return;
    }

    recorder.ondataavailable = async (event) => {
        if (!event.data || !event.data.size) return;
        try {
            const buffer = await event.data.arrayBuffer();
            window.capture.sendChunk({
                gen: generation,
                // The very first blob carries the WebM header and track table.
                // Every later one is a bare continuation of the same stream.
                seq: sequence,
                init: sequence === 0,
                mime,
                bytes: new Uint8Array(buffer),
            });
            sequence += 1;
        } catch (err) {
            report('error', `chunk: ${err.message}`);
        }
    };

    recorder.onerror = (event) => {
        report('error', event?.error?.message || 'recorder error');
    };

    try {
        recorder.start(Math.max(200, Math.min(2000, Number(opts.chunkMs) || 600)));
    } catch (err) {
        teardown();
        report('unsupported', `recorder start: ${err.message}`);
        return;
    }

    report('streaming', mime);
}

window.capture.onStart((opts) => {
    start(opts).catch((err) => {
        teardown();
        report('unsupported', err.message);
    });
});

window.capture.onStop(() => {
    teardown();
    report('stopped', '');
});
