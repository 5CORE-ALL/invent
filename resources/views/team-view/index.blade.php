@extends('layouts.vertical', ['title' => 'Team View', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        /* ---------- Hero ---------- */
        .tv-hero {
            position: relative; overflow: hidden; border-radius: 16px; padding: 16px 26px; color: #fff; margin-bottom: 14px;
            background: linear-gradient(120deg, #6d28d9, #3b82f6, #06b6d4, #8b5cf6);
            background-size: 300% 300%; animation: tvGradient 14s ease infinite;
            box-shadow: 0 12px 30px rgba(79, 70, 229, .28);
        }
        .tv-hero::before, .tv-hero::after {
            content: ''; position: absolute; border-radius: 50%; background: rgba(255, 255, 255, .12); pointer-events: none;
        }
        .tv-hero::before { width: 260px; height: 260px; right: -60px; top: -90px; animation: tvFloat 9s ease-in-out infinite; }
        .tv-hero::after { width: 160px; height: 160px; right: 180px; bottom: -80px; animation: tvFloat 11s ease-in-out infinite reverse; }
        .tv-hero-grid { position: relative; z-index: 1; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; }
        .tv-hero-text { flex: 1 1 420px; min-width: 0; }
        .tv-group { flex: 0 1 460px; display: flex; flex-direction: column; align-items: center; }
        .tv-group-pics {
            display: flex; flex-wrap: wrap; justify-content: center; padding: 6px 12px 8px 20px;
            background: rgba(255, 255, 255, .14); border: 1px solid rgba(255, 255, 255, .3); border-radius: 22px; backdrop-filter: blur(8px);
            box-shadow: 0 10px 28px rgba(0, 0, 0, .18);
        }
        .tv-group-pic {
            width: 38px; height: 38px; border-radius: 50%; object-fit: cover; margin: 0 0 -4px -9px; background: #fff;
            border: 2px solid #fff; box-shadow: 0 0 0 2px var(--gp, #94a3b8), 0 3px 8px rgba(0, 0, 0, .25);
            transition: transform .2s ease; animation: tvPop .5s cubic-bezier(.2, 1.4, .4, 1) both; position: relative;
        }
        .tv-group-pic:hover { transform: translateY(-8px) scale(1.35); z-index: 5; }
        .tv-gp-director { --gp: #a855f7; }
        .tv-gp-mgr { --gp: #60a5fa; }
        .tv-gp-exec { --gp: #34d399; }
        .tv-group-caption { margin-top: 5px; font-size: .7rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; opacity: .9; }
        @keyframes tvPop { from { opacity: 0; transform: scale(.3) translateY(10px); } to { opacity: 1; transform: none; } }
        .tv-hero-top { font-size: .78rem; letter-spacing: .14em; text-transform: uppercase; font-weight: 700; opacity: .85; }
        .tv-slogan { font-size: clamp(1.15rem, 2vw, 1.7rem); font-weight: 800; line-height: 1.25; margin: 4px 0 2px; max-width: 900px; text-shadow: 0 2px 10px rgba(0, 0, 0, .15); animation: tvRise .8s ease both; }
        .tv-slogan-by { font-size: .9rem; opacity: .85; font-style: italic; animation: tvRise 1s .15s ease both; }
        .tv-stats { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; position: relative; z-index: 1; }
        .tv-stat {
            background: rgba(255, 255, 255, .18); backdrop-filter: blur(8px); border: 1px solid rgba(255, 255, 255, .3);
            border-radius: 12px; padding: 4px 14px; min-width: 100px; transition: transform .2s ease, background .2s ease;
            animation: tvRise .8s ease both;
        }
        .tv-stat:hover { transform: translateY(-3px) scale(1.04); background: rgba(255, 255, 255, .28); }
        .tv-stat b { display: block; font-size: 1.25rem; line-height: 1.1; }
        .tv-stat span { font-size: .75rem; opacity: .9; font-weight: 600; letter-spacing: .03em; }
        @keyframes tvGradient { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
        @keyframes tvFloat { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(18px); } }
        @keyframes tvRise { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }

        /* ---------- Toolbar ---------- */
        .tv-toolbar {
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px;
            background: #fff; border: 1px solid #e8ecf5; border-radius: 14px; padding: 10px 14px; margin-bottom: 14px;
            box-shadow: 0 2px 10px rgba(15, 23, 42, .05);
        }
        .tv-legend span { margin-right: 14px; font-size: 13px; font-weight: 600; color: #475569; }
        .tv-legend i { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 5px; }
        #tvSearch { border-radius: 999px; padding-left: 16px; border-color: #d9e0f0; transition: box-shadow .2s, border-color .2s; }
        #tvSearch:focus { border-color: #6d5dfc; box-shadow: 0 0 0 4px rgba(109, 93, 252, .15); }
        #tvSearchBtn { border-radius: 999px; border: 0; background: linear-gradient(135deg, #6d5dfc, #3b82f6); box-shadow: 0 4px 12px rgba(99, 102, 241, .35); transition: transform .15s; }
        #tvSearchBtn:hover { transform: translateY(-1px); }
        #tvSearchReset { border-radius: 999px; }

        /* ---------- Table ---------- */
        #team-table { border: 0; border-radius: 14px; overflow: hidden; background: transparent; }
        #team-table .tabulator-header { background: linear-gradient(135deg, #f5f3ff, #eff6ff); border-bottom: 2px solid #e0e7ff; color: #3730a3; }
        #team-table .tabulator-header .tabulator-col { background: transparent; border-right: 0; }
        #team-table .tabulator-col-title { font-weight: 800; letter-spacing: .02em; text-transform: uppercase; font-size: .72rem; }
        #team-table .tabulator-row { border-bottom: 1px solid #eef1f8; transition: background .18s ease, transform .18s ease, box-shadow .18s ease; animation: tvRise .45s ease both; }
        #team-table .tabulator-row.tabulator-row-even { background: #fafbff; }
        #team-table .tabulator-row:hover { background: #f1efff; box-shadow: inset 4px 0 0 #6d5dfc; }
        #team-table .tabulator-row .tabulator-cell { vertical-align: middle; border-right: 0; padding: 10px 12px; }
        .tv-person { display: flex; align-items: center; justify-content: center; gap: 12px; }
        .tv-avatar { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; flex-shrink: 0; border: 2px solid #fff; box-shadow: 0 0 0 2px var(--tv-ring, #cbd5e1); transition: transform .2s ease; }
        .tabulator-row:hover .tv-avatar { transform: scale(1.12) rotate(-3deg); }
        .tv-ring-director { --tv-ring: #7c3aed; }
        .tv-ring-mgr { --tv-ring: #3b7ddd; }
        .tv-ring-exec { --tv-ring: #20c997; }
        .tv-name { font-weight: 700; color: #0f172a; }
        .tv-badge { font-size: 11px; font-weight: 700; color: #fff; border-radius: 999px; padding: 3px 11px; display: inline-block; box-shadow: 0 2px 6px rgba(0, 0, 0, .15); }
        .tv-badge-director { background: linear-gradient(135deg, #7c3aed, #a855f7); }
        .tv-badge-mgr { background: linear-gradient(135deg, #2563eb, #60a5fa); }
        .tv-badge-exec { background: linear-gradient(135deg, #10b981, #34d399); }
        #team-table .tabulator-cell.tv-left-cell { text-align: left; }
        #team-table .tv-left-cell .tv-person, #team-table .tv-left-cell .tv-contact-line { justify-content: flex-start; }
        .tv-contact-line { display: flex; align-items: center; justify-content: center; gap: 6px; flex-wrap: wrap; }
        .tv-icon-btn {
            display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%;
            font-size: 14px; text-decoration: none; transition: transform .15s ease, box-shadow .15s ease;
        }
        .tv-icon-btn:hover { transform: translateY(-2px) scale(1.12); }
        .tv-icon-mail { background: #eef2ff; color: #4f46e5; }
        .tv-icon-mail:hover { background: #4f46e5; color: #fff; box-shadow: 0 4px 10px rgba(79, 70, 229, .4); }
        .tv-icon-chat { background: #ccfbf1; color: #0f766e; }
        .tv-icon-chat:hover { background: #0f766e; color: #fff; box-shadow: 0 4px 10px rgba(15, 118, 110, .4); }
        .tv-link { color: #334155; text-decoration: none; }
        .tv-link:hover { color: #6d5dfc; text-decoration: underline; }
        .tv-contactfor { margin: 0; padding: 0; list-style: none; white-space: normal; text-align: center; }
        .tv-contactfor li { display: inline-block; margin: 2px 4px 2px 0; padding: 3px 10px; font-size: .78rem; font-weight: 600; color: #4338ca; background: #eef2ff; border-radius: 999px; }
        #team-table .tabulator-col .tabulator-col-content .tabulator-col-title { text-align: center; }
        #team-table .tabulator-cell { text-align: center; }
        .tv-rr { text-align: center; }
        .tv-rr summary { cursor: pointer; color: #6d5dfc; font-weight: 700; }
        .tv-rr ul { margin: 6px 0 0; padding-left: 18px; text-align: left; color: #334155; max-height: 220px; overflow-y: auto; white-space: normal; }

        /* ---------- Stars / ratings ---------- */
        .tv-stars { position: relative; display: inline-block; font-size: 15px; line-height: 1; letter-spacing: 2px; color: #d6dbe7; white-space: nowrap; }
        .tv-stars-fill { position: absolute; left: 0; top: 0; overflow: hidden; color: #f59e0b; white-space: nowrap; }
        .tv-rate-row { display: flex; align-items: center; justify-content: center; gap: 8px; margin: 2px 0; font-size: .78rem; }
        .tv-rate-label { width: 58px; font-weight: 700; color: #475569; }
        .tv-rate-num { font-weight: 800; color: #0f172a; min-width: 26px; }
        .tv-rate-n { color: #94a3b8; font-size: .7rem; }
        .tv-review-btn {
            border: 0; border-radius: 999px; padding: 4px 9px; font-weight: 700; font-size: .72rem; line-height: 1; color: #fff; white-space: nowrap;
            background: linear-gradient(135deg, #f59e0b, #f97316); box-shadow: 0 3px 8px rgba(249, 115, 22, .35); transition: transform .15s ease, box-shadow .15s ease;
        }
        .tv-review-btn:hover:not(:disabled) { transform: translateY(-2px) scale(1.04); box-shadow: 0 8px 18px rgba(249, 115, 22, .45); }
        .tv-review-btn:disabled { background: #e2e8f0; color: #94a3b8; box-shadow: none; cursor: not-allowed; }

        /* ---------- Review modal ---------- */
        #tvReviewModal .modal-content { border: 0; border-radius: 18px; overflow: hidden; }
        #tvReviewModal .modal-header { background: linear-gradient(120deg, #6d28d9, #3b82f6); color: #fff; border: 0; }
        #tvReviewModal .btn-close { filter: invert(1); }
        .tv-rv-section { background: #f8faff; border: 1px solid #e5eaf7; border-radius: 14px; padding: 12px 14px; margin-bottom: 12px; }
        .tv-rv-title { font-weight: 800; color: #3730a3; margin-bottom: 6px; display: flex; justify-content: space-between; align-items: center; }
        .tv-rv-factor { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 6px 0; border-top: 1px dashed #e2e8f0; flex-wrap: wrap; }
        .tv-rv-factor:first-of-type { border-top: 0; }
        .tv-rv-flabel { font-weight: 600; color: #1e293b; }
        .tv-rv-avg { font-size: .72rem; color: #94a3b8; }
        .tv-pick { display: inline-flex; align-items: center; gap: 2px; }
        .tv-pick button { border: 0; background: none; padding: 0 1px; font-size: 24px; line-height: 1; color: #d6dbe7; cursor: pointer; transition: transform .12s ease, color .12s ease; }
        .tv-pick button.on { color: #f59e0b; }
        .tv-pick button:hover { transform: scale(1.25); }
        .tv-pick .tv-zero { font-size: 11px; font-weight: 700; color: #64748b; border: 1px solid #cbd5e1; border-radius: 8px; padding: 2px 7px; margin-right: 6px; }
        .tv-pick .tv-zero.on { background: #fee2e2; border-color: #fca5a5; color: #b91c1c; }
        .tv-pick .tv-val { font-size: .8rem; font-weight: 800; color: #0f172a; min-width: 34px; text-align: right; margin-left: 6px; }

        @media (prefers-reduced-motion: reduce) {
            .tv-hero, .tv-hero::before, .tv-hero::after, .tv-slogan, .tv-slogan-by, .tv-stat, .tv-group-pic, #team-table .tabulator-row { animation: none !important; }
        }
    </style>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="tv-hero">
                  <div class="tv-hero-grid">
                    <div class="tv-hero-text">
                    <div class="tv-hero-top">&#9889; Team View &middot; {{ now()->format('l, d M Y') }}</div>
                    <div class="tv-slogan">&ldquo;{{ $slogan['text'] }}&rdquo;</div>
                    <div class="tv-slogan-by">&mdash; {{ $slogan['author'] }}</div>
                    </div>
                    {{-- Group photo: everyone's picture in one collage --}}
                    <div class="tv-group" aria-label="Our team">
                        <div class="tv-group-pics">
                            @foreach($rows as $i => $r)
                                <img class="tv-group-pic tv-gp-{{ $r['level'] ?: 'none' }}" src="{{ $r['avatar'] }}" alt="{{ $r['name'] }}"
                                     title="{{ $r['name'] }}{{ $r['designation'] !== '' ? ' · ' . $r['designation'] : '' }}"
                                     onerror="tvAvatarFallback(this)" style="animation-delay: {{ min($i, 40) * 0.03 }}s">
                            @endforeach
                        </div>
                        <div class="tv-group-caption">Team 5Core &middot; {{ $total }} strong</div>
                    </div>
                  </div>
                    <div class="tv-stats">
                        <div class="tv-stat" style="animation-delay:.1s"><b>{{ $total }}</b><span>Team Members</span></div>
                        <div class="tv-stat" style="animation-delay:.2s"><b>{{ $counts['director'] }}</b><span>Directors</span></div>
                        <div class="tv-stat" style="animation-delay:.3s"><b>{{ $counts['mgr'] }}</b><span>Managers</span></div>
                        <div class="tv-stat" style="animation-delay:.4s"><b>{{ $counts['exec'] }}</b><span>Executives</span></div>
                    </div>
                </div>

                <div class="tv-toolbar">
                    <div class="tv-legend">
                        <span><i style="background:#7c3aed"></i>Director</span>
                        <span><i style="background:#3b7ddd"></i>Manager</span>
                        <span><i style="background:#20c997"></i>Executive</span>
                    </div>
                    <form id="tvSearchForm" class="d-flex gap-1" onsubmit="return false;">
                        <input type="search" id="tvSearch" class="form-control form-control-sm" style="width:300px"
                               placeholder="Name, Designation, R&R, Contact For">
                        <button type="submit" id="tvSearchBtn" class="btn btn-sm btn-primary"><i class="ri-search-line"></i> Search</button>
                        <button type="button" id="tvSearchReset" class="btn btn-sm btn-outline-secondary">Reset</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm" style="border-radius:14px">
                    <div class="card-body p-2">
                        <div id="team-table"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="tvReviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-0">&#11088; Review <span id="tvRvName"></span></h5>
                        <small class="opacity-75">Rate each factor from 0 to 5 stars. Factors you leave unrated are skipped.</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="tvRvBody">
                    <div class="text-center text-muted py-4">Loading&hellip;</div>
                </div>
                <div class="modal-footer">
                    <span id="tvRvMsg" class="me-auto small"></span>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="tvRvSave">Submit Review</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        // If someone's uploaded photo can't be loaded, fall back to the default avatar (once).
        function tvAvatarFallback(img) {
            img.onerror = null;
            img.src = @json(asset('images/users/avatar-2.jpg'));
        }
    </script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        (function () {
            var rows = @json($rows);
            var levelLabels = { director: 'Director', mgr: 'Manager', exec: 'Executive' };
            var meId = @json($meId);
            var csrf = @json(csrf_token());
            var reviewUrl = @json(url('/team-view/reviews'));
            var chatUrl = @json(url('/team-view/chat'));
            var SECTION_LABELS = { personal: 'Personal', team: 'Team', rr: 'R&R' };

            // Read-only star display; the gold layer is clipped to avg/5 of the width (supports decimals).
            function starsHtml(avg) {
                var pct = avg == null ? 0 : Math.max(0, Math.min(5, avg)) / 5 * 100;
                return '<span class="tv-stars">&#9733;&#9733;&#9733;&#9733;&#9733;<span class="tv-stars-fill" style="width:' + pct + '%">&#9733;&#9733;&#9733;&#9733;&#9733;</span></span>';
            }
            function ratingsHtml(r) {
                r = r || {};
                return ['personal', 'team', 'rr'].map(function (k) {
                    var x = r[k] || { avg: null, n: 0 };
                    return '<div class="tv-rate-row"><span class="tv-rate-label">' + SECTION_LABELS[k] + '</span>' + starsHtml(x.avg) +
                        '<span class="tv-rate-num">' + (x.avg == null ? '—' : Number(x.avg).toFixed(1)) + '</span>' +
                        (x.n ? '<span class="tv-rate-n">(' + x.n + ')</span>' : '') + '</div>';
                }).join('');
            }

            function esc(s) {
                return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
                });
            }

            var table = new Tabulator('#team-table', {
                data: rows,
                layout: 'fitColumns',
                height: 'calc(100vh - 160px)',
                placeholder: 'No team members found.',
                columnDefaults: { hozAlign: 'center', headerHozAlign: 'center' },
                columns: [
                    {
                        title: 'Name', field: 'name', minWidth: 280, widthGrow: 2, headerSort: true, hozAlign: 'left', cssClass: 'tv-left-cell',
                        formatter: function (cell) {
                            var d = cell.getRow().getData();
                            return '<div class="tv-person"><img class="tv-avatar tv-ring-' + esc(d.level) + '" loading="lazy" src="' + esc(d.avatar) + '" alt="" onerror="tvAvatarFallback(this)">' +
                                '<span class="tv-name">' + esc(d.name) + '</span></div>';
                        }
                    },
                    {
                        title: 'Role', field: 'level', width: 110, hozAlign: 'center',
                        formatter: function (cell) {
                            var v = cell.getValue();
                            return levelLabels[v] ? '<span class="tv-badge tv-badge-' + esc(v) + '">' + levelLabels[v] + '</span>' : '';
                        }
                    },
                    {
                        title: 'Designation', field: 'designation', minWidth: 160, widthGrow: 1,
                        formatter: function (cell) { return esc(cell.getValue() || '—'); }
                    },
                    {
                        title: 'Contact', field: 'email', minWidth: 230, widthGrow: 1, hozAlign: 'left', cssClass: 'tv-left-cell',
                        formatter: function (cell) {
                            var d = cell.getRow().getData();
                            var out = '';
                            if (d.email || d.id !== meId) {
                                out += '<div class="tv-contact-line">';
                                if (d.email) {
                                    out += '<a class="tv-link" href="mailto:' + esc(d.email) + '">' + esc(d.email) + '</a>' +
                                        '<a class="tv-icon-btn tv-icon-mail" href="mailto:' + esc(d.email) + '" title="Send email to ' + esc(d.name) + '"><i class="ri-mail-send-line"></i></a>';
                                }
                                if (d.id !== meId) {
                                    out += '<a class="tv-icon-btn tv-icon-chat" href="' + chatUrl + '/' + d.id + '" target="_blank" rel="noopener" title="Chat with ' + esc(d.name) + '"><i class="ri-chat-3-fill"></i></a>';
                                }
                                out += '</div>';
                            }
                            if (d.phone) out += '<div><a class="tv-link" href="tel:' + esc(String(d.phone).replace(/[^0-9+]/g, '')) + '"><i class="ri-phone-line"></i> ' + esc(d.phone) + '</a></div>';
                            return out || '<span class="text-muted">—</span>';
                        }
                    },
                    {
                        title: 'Contact For:', field: 'contact_for', minWidth: 220, widthGrow: 2, headerSort: false,
                        formatter: function (cell) {
                            var items = cell.getValue() || [];
                            if (!items.length) return '<span class="text-muted">—</span>';
                            return '<ul class="tv-contactfor">' + items.map(function (l) { return '<li>' + esc(l) + '</li>'; }).join('') + '</ul>';
                        }
                    },
                    {
                        title: 'Review', field: 'id', width: 80, hozAlign: 'center', headerSort: false,
                        formatter: function (cell) {
                            var id = cell.getValue();
                            if (id === meId) return '<button type="button" class="tv-review-btn" disabled title="You can\'t review yourself">+<i class="ri-star-line"></i></button>';
                            return '<button type="button" class="tv-review-btn" title="Review this member" aria-label="Review this member">+<i class="ri-star-fill"></i></button>';
                        },
                        cellClick: function (e, cell) {
                            if (!e.target.closest('.tv-review-btn:not(:disabled)')) return;
                            openReview(cell.getRow().getData());
                        }
                    },
                    {
                        title: 'Ratings', field: 'ratings', minWidth: 230, widthGrow: 2, headerSort: false,
                        formatter: function (cell) { return ratingsHtml(cell.getValue()); }
                    },
                    {
                        title: 'R&R', field: 'rr', minWidth: 200, widthGrow: 2, headerSort: false,
                        formatter: function (cell) {
                            var rr = cell.getValue() || [];
                            if (!rr.length) return '<span class="text-muted">Not defined</span>';
                            return '<details class="tv-rr"><summary>' + rr.length + ' responsibilit' + (rr.length === 1 ? 'y' : 'ies') +
                                '</summary><ul>' + rr.map(function (l) { return '<li>' + esc(l) + '</li>'; }).join('') + '</ul></details>';
                        }
                    }
                ]
            });

            // Grow/shrink the row when an R&R <details> is toggled ("toggle" does not bubble, so capture it).
            document.getElementById('team-table').addEventListener('toggle', function (e) {
                var el = e.target.closest('.tabulator-row');
                if (!el) return;
                var row = table.getRows().find(function (r) { return r.getElement() === el; });
                if (row) row.normalizeHeight();
            }, true);

            // ---------- Review modal ----------
            var modalEl = document.getElementById('tvReviewModal');
            var modal = null;
            var current = null;     // { id, row }
            var picks = {};         // picks[section][factor] = 0..5 | null

            function renderPick(sec, f) {
                var v = picks[sec][f];
                var html = '<button type="button" class="tv-zero' + (v === 0 ? ' on' : '') + '" data-v="0" title="Rate 0">0</button>';
                for (var i = 1; i <= 5; i++) {
                    html += '<button type="button" class="' + (v != null && i <= v ? 'on' : '') + '" data-v="' + i + '" title="' + i + ' star' + (i > 1 ? 's' : '') + '">&#9733;</button>';
                }
                html += '<span class="tv-val">' + (v == null ? '—' : v + '/5') + '</span>';
                return html;
            }

            function openReview(rowData) {
                current = { id: rowData.id, row: null };
                document.getElementById('tvRvName').textContent = rowData.name;
                document.getElementById('tvRvMsg').textContent = '';
                document.getElementById('tvRvBody').innerHTML = '<div class="text-center text-muted py-4">Loading…</div>';
                document.getElementById('tvRvSave').disabled = true;
                modal = modal || new bootstrap.Modal(modalEl);
                modal.show();

                fetch(reviewUrl + '/' + rowData.id, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (!d.ok) throw new Error(d.message || 'Failed to load');
                        picks = {};
                        var html = '';
                        d.sections.forEach(function (sec) {
                            picks[sec.key] = {};
                            html += '<div class="tv-rv-section" data-sec="' + esc(sec.key) + '"><div class="tv-rv-title"><span>' + esc(sec.label) + '</span></div>';
                            sec.factors.forEach(function (f) {
                                picks[sec.key][f.key] = f.mine;
                                html += '<div class="tv-rv-factor"><div><div class="tv-rv-flabel">' + esc(f.label) + '</div>' +
                                    '<div class="tv-rv-avg">' + (f.n ? 'Team avg ' + Number(f.avg).toFixed(1) + ' (' + f.n + ' review' + (f.n > 1 ? 's' : '') + ')' : 'No reviews yet') + '</div></div>' +
                                    '<div class="tv-pick" data-sec="' + esc(sec.key) + '" data-f="' + esc(f.key) + '">' + renderPick(sec.key, f.key) + '</div></div>';
                            });
                            html += '</div>';
                        });
                        document.getElementById('tvRvBody').innerHTML = html;
                        document.getElementById('tvRvSave').disabled = !!d.self;
                        if (d.self) document.getElementById('tvRvMsg').textContent = 'You cannot review yourself.';
                    })
                    .catch(function (err) {
                        document.getElementById('tvRvBody').innerHTML = '<div class="alert alert-danger mb-0">' + esc(err.message) + '</div>';
                    });
            }

            document.getElementById('tvRvBody').addEventListener('click', function (e) {
                var btn = e.target.closest('.tv-pick button');
                if (!btn) return;
                var pick = btn.closest('.tv-pick');
                var sec = pick.getAttribute('data-sec'), f = pick.getAttribute('data-f');
                var v = parseInt(btn.getAttribute('data-v'), 10);
                // Clicking the currently selected value again clears it (leaves the factor unrated).
                picks[sec][f] = (picks[sec][f] === v) ? null : v;
                pick.innerHTML = renderPick(sec, f);
            });

            document.getElementById('tvRvSave').addEventListener('click', function () {
                if (!current) return;
                var msg = document.getElementById('tvRvMsg');
                var any = Object.keys(picks).some(function (s) {
                    return Object.keys(picks[s]).some(function (f) { return picks[s][f] != null; });
                });
                if (!any) { msg.className = 'me-auto small text-danger'; msg.textContent = 'Rate at least one factor.'; return; }
                var btn = this;
                btn.disabled = true;
                msg.className = 'me-auto small text-muted';
                msg.textContent = 'Saving…';
                fetch(reviewUrl + '/' + current.id, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ ratings: picks })
                })
                    .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
                    .then(function (res) {
                        if (!res.ok || !res.d.ok) throw new Error(res.d.message || 'Could not save review');
                        var row = table.getRows().find(function (r) { return r.getData().id === current.id; });
                        if (row) { row.update({ ratings: res.d.ratings }); row.reformat(); }
                        modal.hide();
                    })
                    .catch(function (err) {
                        msg.className = 'me-auto small text-danger';
                        msg.textContent = err.message;
                    })
                    .finally(function () { btn.disabled = false; });
            });

            // Search across name, designation, R&R and Contact For.
            var searchTerm = '';
            function matches(data) {
                return !searchTerm || (data.search || '').indexOf(searchTerm) !== -1;
            }
            var searchInput = document.getElementById('tvSearch');
            function runSearch() {
                searchTerm = searchInput.value.trim().toLowerCase();
                if (searchTerm) {
                    table.setFilter(matches);
                } else {
                    table.clearFilter();
                }
            }
            document.getElementById('tvSearchForm').addEventListener('submit', function (e) { e.preventDefault(); runSearch(); });
            document.getElementById('tvSearchReset').addEventListener('click', function () { searchInput.value = ''; runSearch(); });
            // Clearing the box via the "x" also resets the table.
            searchInput.addEventListener('input', function () { if (!this.value) runSearch(); });
        })();
    </script>
@endsection
