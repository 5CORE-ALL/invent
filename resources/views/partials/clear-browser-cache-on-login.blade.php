@if (session('clear_browser_cache'))
<script>
    (function () {
        if (!('caches' in window)) {
            return;
        }
        caches.keys().then(function (keys) {
            return Promise.all(keys.map(function (key) {
                return caches.delete(key);
            }));
        }).catch(function () {});
    })();
</script>
@endif
