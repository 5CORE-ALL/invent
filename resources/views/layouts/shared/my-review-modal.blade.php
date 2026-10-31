{{-- "My Review" modal: opened from the topbar REVIEW pill (#myReviewTopbarBtn).
     Shows the average of all peer reviews the logged-in user has received, with the
     Personal / Team / R&R breakdown. Reviewers are anonymous (counts only). --}}
<style>
    #myReviewModal .modal-content { border: 0; border-radius: 18px; overflow: hidden; }
    #myReviewModal .modal-header { background: linear-gradient(120deg, #6d28d9, #3b82f6); color: #fff; border: 0; }
    #myReviewModal .btn-close { filter: invert(1); }
    .mr-hero { text-align: center; padding: 6px 0 14px; }
    .mr-big { font-size: 3rem; font-weight: 800; line-height: 1; color: #0f172a; }
    .mr-sub { color: #64748b; font-size: .85rem; margin-top: 4px; }
    .mr-stars { position: relative; display: inline-block; font-size: 1.1rem; line-height: 1; letter-spacing: 2px; color: #d6dbe7; white-space: nowrap; }
    .mr-stars-fill { position: absolute; left: 0; top: 0; overflow: hidden; color: #f59e0b; white-space: nowrap; }
    .mr-hero .mr-stars { font-size: 1.7rem; margin-top: 6px; }
    .mr-section { background: #f8faff; border: 1px solid #e5eaf7; border-radius: 14px; padding: 12px 14px; margin-bottom: 12px; }
    .mr-sec-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-bottom: 6px; }
    .mr-sec-title { font-weight: 800; color: #3730a3; }
    .mr-sec-avg { display: flex; align-items: center; gap: 8px; font-weight: 800; color: #0f172a; }
    .mr-sec-n { font-weight: 600; color: #94a3b8; font-size: .75rem; }
    .mr-factor { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 6px 0; border-top: 1px dashed #e2e8f0; flex-wrap: wrap; }
    .mr-factor-label { font-weight: 600; color: #1e293b; }
    .mr-factor-r { display: flex; align-items: center; gap: 8px; font-size: .85rem; }
    .mr-factor-r b { min-width: 28px; text-align: right; color: #0f172a; }
    .mr-factor-n { color: #94a3b8; font-size: .72rem; min-width: 62px; }
    .mr-empty { color: #94a3b8; font-style: italic; }
</style>

<div class="modal fade" id="myReviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0">&#11088; My Review</h5>
                    <small class="opacity-75">What your team thinks of you &mdash; Personal, Team and R&amp;R</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="myReviewBody">
                <div class="text-center text-muted py-4">Loading&hellip;</div>
            </div>
            <div class="modal-footer justify-content-between">
                <small class="text-muted">Reviews are anonymous. Only averages are shown.</small>
                <a href="{{ route('team.view') }}" class="btn btn-sm btn-light">Open Team View</a>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var btn = document.getElementById('myReviewTopbarBtn');
    if (!btn) return;
    var avgEl = document.getElementById('myReviewTopbarAvg');
    var body = document.getElementById('myReviewBody');
    var url = @json(route('team.view.my-review'));
    var data = null;

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function stars(avg) {
        var pct = avg == null ? 0 : Math.max(0, Math.min(5, avg)) / 5 * 100;
        return '<span class="mr-stars">&#9733;&#9733;&#9733;&#9733;&#9733;<span class="mr-stars-fill" style="width:' + pct + '%">&#9733;&#9733;&#9733;&#9733;&#9733;</span></span>';
    }
    function fmt(v) { return v == null ? '—' : Number(v).toFixed(1); }

    function applyPill(d) {
        btn.classList.remove('is-review-none', 'is-review-low', 'is-review-mid', 'is-review-high');
        var cls = 'is-review-none';
        if (d.avg != null) cls = d.avg >= 4 ? 'is-review-high' : (d.avg >= 3 ? 'is-review-mid' : 'is-review-low');
        btn.classList.add(cls);
        avgEl.textContent = d.avg == null ? '—' : Number(d.avg).toFixed(1);
        btn.title = d.avg == null ? 'My Review — no reviews yet'
            : 'My Review — ' + fmt(d.avg) + '/5 from ' + d.reviewers + ' reviewer' + (d.reviewers === 1 ? '' : 's');
    }

    function render(d) {
        var html = '<div class="mr-hero"><div class="mr-big">' + (d.avg == null ? '—' : fmt(d.avg)) + '<span style="font-size:1.1rem;color:#94a3b8"> / 5</span></div>' +
            stars(d.avg) +
            '<div class="mr-sub">' + (d.reviewers ? 'Average across ' + d.reviewers + ' reviewer' + (d.reviewers === 1 ? '' : 's') : 'No one has reviewed you yet') + '</div></div>';
        d.sections.forEach(function (sec) {
            html += '<div class="mr-section"><div class="mr-sec-head"><span class="mr-sec-title">' + esc(sec.label) + '</span>' +
                '<span class="mr-sec-avg">' + stars(sec.avg) + ' ' + fmt(sec.avg) +
                (sec.reviewers ? ' <span class="mr-sec-n">(' + sec.reviewers + ' reviewer' + (sec.reviewers === 1 ? '' : 's') + ')</span>' : '') + '</span></div>';
            sec.factors.forEach(function (f) {
                html += '<div class="mr-factor"><span class="mr-factor-label">' + esc(f.label) + '</span>' +
                    '<span class="mr-factor-r">' + stars(f.avg) + '<b>' + fmt(f.avg) + '</b>' +
                    '<span class="mr-factor-n">' + (f.n ? f.n + ' rating' + (f.n === 1 ? '' : 's') : '<span class="mr-empty">no ratings</span>') + '</span></span></div>';
            });
            html += '</div>';
        });
        body.innerHTML = html;
    }

    function load() {
        return fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.ok) throw new Error('bad response');
                data = d;
                applyPill(d);
                return d;
            });
    }

    // Fill the pill as soon as the page loads.
    load().catch(function () { avgEl.textContent = '—'; });

    btn.addEventListener('click', function () {
        var el = document.getElementById('myReviewModal');
        if (!el || !window.bootstrap) return;
        var modal = bootstrap.Modal.getOrCreateInstance(el);
        if (data) render(data);
        else body.innerHTML = '<div class="text-center text-muted py-4">Loading…</div>';
        modal.show();
        // Always refresh when opened so new reviews show up without a page reload.
        load().then(render).catch(function () {
            body.innerHTML = '<div class="alert alert-danger mb-0">Could not load your reviews.</div>';
        });
    });
})();
</script>
