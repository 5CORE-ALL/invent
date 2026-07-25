{{-- Shared Play/Pause parent navigation (same behavior as /product-master) --}}
@once
<style>
    .time-navigation-group {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        border-radius: 50px;
        overflow: hidden;
        padding: 2px;
        background: #f8f9fa;
        display: inline-flex;
        align-items: center;
    }
    .time-navigation-group button {
        padding: 0;
        border-radius: 50% !important;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 3px;
        transition: all 0.2s ease;
        border: 1px solid #dee2e6;
        background: white;
        cursor: pointer;
    }
    .time-navigation-group button:hover {
        background-color: #f1f3f5 !important;
        transform: scale(1.05);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    .time-navigation-group button:active { transform: scale(0.95); }
    .time-navigation-group button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none !important;
        box-shadow: none !important;
    }
    .time-navigation-group button i { font-size: 1rem; }
    .time-navigation-group #play-auto { color: #28a745; }
    .time-navigation-group #play-auto:hover {
        background-color: #28a745 !important;
        color: white !important;
    }
    .time-navigation-group #play-pause { color: #ffc107; display: none; }
    .time-navigation-group #play-pause:hover {
        background-color: #ffc107 !important;
        color: white !important;
    }
    .time-navigation-group #play-backward,
    .time-navigation-group #play-forward { color: #007bff; }
    .time-navigation-group #play-backward:hover,
    .time-navigation-group #play-forward:hover {
        background-color: #007bff !important;
        color: white !important;
    }
    .time-navigation-group button:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
    }
</style>
<script>
window.ParentPlayback = window.ParentPlayback || {
    create: function (opts) {
        opts = opts || {};
        var getAllData = opts.getAllData || function () { return []; };
        var applyFilter = opts.applyFilter || function () {};
        var uniqueParents = [];
        var active = false;
        var idx = -1;
        var root = opts.root || document;
        var btnPlay = root.querySelector ? root.querySelector('#play-auto') : document.getElementById('play-auto');
        var btnPause = root.querySelector ? root.querySelector('#play-pause') : document.getElementById('play-pause');
        var btnNext = root.querySelector ? root.querySelector('#play-forward') : document.getElementById('play-forward');
        var btnPrev = root.querySelector ? root.querySelector('#play-backward') : document.getElementById('play-backward');

        function rebuildParents() {
            var data = getAllData() || [];
            uniqueParents = [];
            var seen = {};
            data.forEach(function (row) {
                var p = row && row.Parent != null ? String(row.Parent) : '';
                if (p && !seen[p]) {
                    seen[p] = true;
                    uniqueParents.push(p);
                }
            });
        }

        function updateButtons() {
            if (btnPrev) btnPrev.disabled = !active || idx <= 0;
            if (btnNext) btnNext.disabled = !active || idx >= uniqueParents.length - 1;
            if (btnPlay) {
                btnPlay.style.display = active ? 'none' : '';
                btnPlay.title = active ? 'Show all products' : 'Start parent navigation';
            }
            if (btnPause) {
                btnPause.style.display = active ? '' : 'none';
                btnPause.title = 'Stop navigation and show all';
            }
            if (btnNext) btnNext.title = active ? 'Next parent' : 'Start navigation first';
            if (btnPrev) btnPrev.title = active ? 'Previous parent' : 'Start navigation first';
        }

        function showCurrent() {
            if (!active || idx < 0 || idx >= uniqueParents.length) return;
            applyFilter(uniqueParents[idx]);
            updateButtons();
        }

        function start() {
            rebuildParents();
            if (!uniqueParents.length) return;
            active = true;
            idx = 0;
            showCurrent();
            updateButtons();
        }

        function stop() {
            active = false;
            idx = -1;
            applyFilter(null);
            updateButtons();
        }

        function next() {
            if (!active || idx >= uniqueParents.length - 1) return;
            idx++;
            showCurrent();
        }

        function prev() {
            if (!active || idx <= 0) return;
            idx--;
            showCurrent();
        }

        if (btnPlay) btnPlay.addEventListener('click', start);
        if (btnPause) btnPause.addEventListener('click', stop);
        if (btnNext) btnNext.addEventListener('click', next);
        if (btnPrev) btnPrev.addEventListener('click', prev);

        updateButtons();

        return {
            rebuildParents: rebuildParents,
            start: start,
            stop: stop,
            next: next,
            prev: prev,
            isActive: function () { return active; },
            currentParent: function () { return active && idx >= 0 ? uniqueParents[idx] : null; },
        };
    }
};
</script>
@endonce

<div class="btn-group time-navigation-group" role="group" aria-label="Parent navigation">
    <button type="button" id="play-backward" class="btn btn-sm btn-light" title="Previous parent">
        <i class="fas fa-step-backward"></i>
    </button>
    <button type="button" id="play-pause" class="btn btn-sm btn-light" title="Show all products" style="display: none;">
        <i class="fas fa-pause"></i>
    </button>
    <button type="button" id="play-auto" class="btn btn-sm btn-light" title="Start parent navigation">
        <i class="fas fa-play"></i>
    </button>
    <button type="button" id="play-forward" class="btn btn-sm btn-light" title="Next parent">
        <i class="fas fa-step-forward"></i>
    </button>
</div>
