{{--
  Batched mismatch inventory sync. Always shows HTTP status / response body
  instead of a blank "Sync failed."

  @param string $url
  @param string $confirm
  @param int $limit
--}}
<script>
document.getElementById('btn-sync-mismatch-now')?.addEventListener('click', function () {
    var btn = this;
    var scope = btn.getAttribute('data-scope') || 'mismatch';
    if (!confirm(@json($confirm))) {
        return;
    }
    btn.disabled = true;
    var original = btn.innerHTML;
    var url = @json($url);
    var offset = 0;
    var ready = false;
    var totals = { updated: 0, failed: 0, skipped: 0 };
    var retries = 0;
    var limit = {{ (int) ($limit ?? 1) }};

    function finish(msg) {
        alert(msg);
        btn.disabled = false;
        btn.innerHTML = original;
    }

    function tick() {
        btn.innerHTML = ready
            ? ('<i class="ri-loader-4-line"></i> Syncing… ' + offset)
            : '<i class="ri-loader-4-line"></i> Preparing…';
        return fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ offset: offset, limit: limit, scope: scope, ready: ready ? 1 : 0 }),
        }).then(function (r) {
            return r.text().then(function (text) {
                var data = null;
                try { data = text ? JSON.parse(text) : null; } catch (e) { data = null; }
                if (!data || typeof data !== 'object') {
                    var snippet = (text || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 240);
                    throw new Error('HTTP ' + r.status + (snippet ? (': ' + snippet) : ' (empty response) at offset ' + offset));
                }
                if (!r.ok && !data.success) {
                    throw new Error(data.message || data.error || ('HTTP ' + r.status + ' at offset ' + offset));
                }
                return data;
            });
        }).then(function (data) {
            if (!data.success) {
                finish(data.message || ('Sync failed at offset ' + offset + '.'));
                return;
            }
            retries = 0;
            if (data.prepared) {
                ready = true;
                if (data.done) {
                    alert(data.message || 'No mismatch SKUs to sync.');
                    location.reload();
                    return;
                }
                btn.innerHTML = '<i class="ri-loader-4-line"></i> Prepared ' + (data.total || 0);
                setTimeout(tick, 200);
                return;
            }
            ready = true;
            totals.updated += data.updated || 0;
            totals.failed += data.failed || 0;
            totals.skipped += data.skipped || 0;
            offset = data.offset || offset;
            if (data.done) {
                alert(
                    (data.message || 'Mismatch inventory sync complete.')
                    + '\nUpdated: ' + totals.updated
                    + '\nFailed: ' + totals.failed
                    + '\nSkipped: ' + totals.skipped
                );
                location.reload();
                return;
            }
            setTimeout(tick, 400);
        }).catch(function (err) {
            var msg = String((err && err.message) || '');
            var timeoutish = /504|502|503|timeout|gateway|empty response/i.test(msg);
            var maxRetries = timeoutish ? 3 : 1;
            if (retries < maxRetries) {
                retries++;
                setTimeout(tick, timeoutish ? 2000 : 1500);
                return;
            }
            finish(((err && err.message) || ('Request failed at offset ' + offset + '.'))
                + '\nProgress so far — Updated: ' + totals.updated + ', Failed: ' + totals.failed);
        });
    }

    tick();
});
</script>
