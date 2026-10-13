/*
 * Plays the desktop agent's encoded screen stream into a <video>.
 *
 * The agent uploads one continuous WebM in slices. Media Source Extensions lets
 * the browser decode it as it arrives, which is what the old JPEG polling could
 * never do: a real codec only transmits what changed between frames, so motion
 * stays smooth instead of arriving as a few stills per second.
 *
 * Slices are only decodable in order, so everything here is sequential: one
 * request in flight, one append at a time, and a full rebuild whenever the
 * server reports the run is no longer contiguous.
 *
 * Callers are expected to keep the still-image path as a fallback and switch to
 * it when onInactive fires.
 */
(function (global) {
    'use strict';

    function canPlay() {
        return typeof global.MediaSource !== 'undefined';
    }

    function LivePlayer(options) {
        this.url = options.url;
        this.video = options.video;
        this.headers = options.headers || {};
        this.onActive = options.onActive || function () {};
        this.onInactive = options.onInactive || function () {};
        // How long to wait for a first slice before handing back to the caller.
        this.startTimeoutMs = options.startTimeoutMs || 9000;
        // Discard anything further behind the live edge than this.
        this.maxLagSeconds = options.maxLagSeconds || 1.5;

        this.running = false;
        this.active = false;
        this.media = null;
        this.buffer = null;
        this.objectUrl = null;
        this.queue = [];
        this.gen = null;
        this.after = -1;
        this.timer = null;
        this.startedAt = 0;
    }

    LivePlayer.prototype.start = function () {
        if (this.running) return;
        this.running = true;
        this.startedAt = Date.now();
        this._rebuild();
        this._pump();
    };

    LivePlayer.prototype.stop = function () {
        this.running = false;
        this.active = false;
        if (this.timer) {
            clearTimeout(this.timer);
            this.timer = null;
        }
        this._teardown();
    };

    LivePlayer.prototype._teardown = function () {
        this.queue = [];
        this.buffer = null;
        if (this.media && this.media.readyState === 'open') {
            try {
                this.media.endOfStream();
            } catch (_) {
                /* already torn down */
            }
        }
        this.media = null;
        if (this.objectUrl) {
            URL.revokeObjectURL(this.objectUrl);
            this.objectUrl = null;
        }
    };

    LivePlayer.prototype._rebuild = function () {
        this._teardown();
        this.gen = null;
        this.after = -1;

        this.media = new global.MediaSource();
        this.objectUrl = URL.createObjectURL(this.media);
        this.video.src = this.objectUrl;
        this.video.muted = true;
        this.video.autoplay = true;
        this.video.playsInline = true;
    };

    LivePlayer.prototype._ensureBuffer = function (mime) {
        if (this.buffer) return true;
        if (!this.media || this.media.readyState !== 'open') return false;

        var type = (mime || 'video/webm').split(';').slice(0, 2).join(';');
        if (!global.MediaSource.isTypeSupported(type)) {
            type = 'video/webm';
            if (!global.MediaSource.isTypeSupported(type)) return false;
        }

        try {
            this.buffer = this.media.addSourceBuffer(type);
        } catch (_) {
            return false;
        }

        // The agent's slices have no shared timeline for a late joiner, so let
        // the browser rebase each append onto the end of what it already has.
        try {
            this.buffer.mode = 'sequence';
        } catch (_) {
            /* some builds only allow 'segments' */
        }

        var self = this;
        this.buffer.addEventListener('updateend', function () { self._drain(); });
        this.buffer.addEventListener('error', function () { self._fail('decode error'); });

        return true;
    };

    LivePlayer.prototype._append = function (bytes, mime) {
        if (!this._ensureBuffer(mime)) return false;
        this.queue.push(bytes);
        this._drain();
        return true;
    };

    LivePlayer.prototype._drain = function () {
        if (!this.buffer || this.buffer.updating || !this.queue.length) return;
        if (!this.media || this.media.readyState !== 'open') return;

        var chunk = this.queue.shift();
        try {
            this.buffer.appendBuffer(chunk);
        } catch (err) {
            // Running out of buffer is recoverable: drop what has already played.
            if (err && err.name === 'QuotaExceededError') {
                this._evict();
                this.queue.unshift(chunk);
                return;
            }
            this._fail(err ? err.message : 'append failed');
        }
    };

    LivePlayer.prototype._evict = function () {
        if (!this.buffer || this.buffer.updating || !this.buffer.buffered.length) return;
        var start = this.buffer.buffered.start(0);
        var cut = Math.max(start, this.video.currentTime - 2);
        if (cut <= start) return;
        try {
            this.buffer.remove(start, cut);
        } catch (_) {
            /* nothing safe to drop yet */
        }
    };

    /** Skip ahead when playback drifts behind, so "live" stays live. */
    LivePlayer.prototype._catchUp = function () {
        if (!this.buffer || !this.buffer.buffered.length) return;
        var end = this.buffer.buffered.end(this.buffer.buffered.length - 1);
        if (end - this.video.currentTime > this.maxLagSeconds) {
            try {
                this.video.currentTime = Math.max(0, end - 0.25);
            } catch (_) {
                /* seek rejected while the buffer is in flux */
            }
        }
        if (this.video.paused) {
            var p = this.video.play();
            if (p && p.catch) p.catch(function () {});
        }
    };

    LivePlayer.prototype._fail = function (reason) {
        if (!this.running) return;
        this.running = false;
        this.active = false;
        if (this.timer) {
            clearTimeout(this.timer);
            this.timer = null;
        }
        this._teardown();
        this.onInactive(reason);
    };

    LivePlayer.prototype._schedule = function (ms) {
        if (!this.running) return;
        var self = this;
        this.timer = setTimeout(function () { self._pump(); }, ms);
    };

    LivePlayer.prototype._pump = function () {
        if (!this.running) return;

        var self = this;
        var query = this.url
            + (this.url.indexOf('?') === -1 ? '?' : '&')
            + 'after=' + encodeURIComponent(this.after)
            + (this.gen ? '&gen=' + encodeURIComponent(this.gen) : '');

        fetch(query, { headers: this.headers, cache: 'no-store' })
            .then(function (res) {
                if (res.status === 204) {
                    // Nothing new. Give up only if a stream never appeared at all.
                    if (!self.active && Date.now() - self.startedAt > self.startTimeoutMs) {
                        self._fail('no stream');
                        return null;
                    }
                    self._schedule(250);
                    return null;
                }
                if (!res.ok) {
                    self._fail('http ' + res.status);
                    return null;
                }
                return res.arrayBuffer().then(function (body) {
                    return {
                        bytes: new Uint8Array(body),
                        gen: res.headers.get('X-Live-Gen'),
                        seq: parseInt(res.headers.get('X-Live-Seq') || '0', 10),
                        init: res.headers.get('X-Live-Init') === '1',
                        gap: res.headers.get('X-Live-Gap') === '1',
                        mime: res.headers.get('Content-Type') || 'video/webm',
                    };
                });
            })
            .then(function (slice) {
                if (!slice || !self.running) return;

                // A gap means the slices we would append next are gone, so the
                // existing buffer can no longer be continued. Start over.
                if (slice.gap && !slice.init) {
                    self._rebuild();
                    self._schedule(0);
                    return;
                }

                if (slice.init) {
                    self._rebuild();
                    self.gen = slice.gen;
                }

                if (!self._append(slice.bytes, slice.mime)) {
                    self._fail('codec unsupported');
                    return;
                }

                self.after = slice.seq;
                if (!self.active) {
                    self.active = true;
                    self.onActive();
                }
                self._catchUp();

                // Ask again immediately: there may be a backlog to drain before
                // we are back at the live edge.
                self._schedule(0);
            })
            .catch(function () {
                if (!self.running) return;
                self._schedule(500);
            });
    };

    global.AttendanceLivePlayer = LivePlayer;
    global.AttendanceLivePlayer.canPlay = canPlay;
})(window);
