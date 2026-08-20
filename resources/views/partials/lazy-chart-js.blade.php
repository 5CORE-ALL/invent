{{-- Load Chart.js only when a history graph is opened. --}}
<script>
(function (w) {
    var SRC = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js';
    w.loadChartJs = function () {
        if (w.Chart) return Promise.resolve(w.Chart);
        if (w._chartJsPromise) return w._chartJsPromise;
        w._chartJsPromise = new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = SRC;
            s.async = true;
            s.onload = function () { resolve(w.Chart); };
            s.onerror = function () {
                w._chartJsPromise = null;
                reject(new Error('Chart.js failed to load'));
            };
            document.head.appendChild(s);
        });
        return w._chartJsPromise;
    };
})(window);
</script>
