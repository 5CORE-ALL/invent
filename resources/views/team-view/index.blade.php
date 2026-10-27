@extends('layouts.vertical', ['title' => 'Team View', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <style>
        .tv-tree, .tv-children { list-style: none; margin: 0; padding: 0; }
        .tv-children { margin-left: 34px; padding-left: 22px; border-left: 2px solid #dbe3ee; }
        .tv-node { position: relative; margin: 10px 0; }
        .tv-children > .tv-node::before {
            content: ''; position: absolute; left: -22px; top: 34px; width: 22px; border-top: 2px solid #dbe3ee;
        }
        .tv-card {
            display: flex; gap: 14px; align-items: flex-start; background: #fff;
            border: 1px solid #e5e9f0; border-left: 4px solid #94a3b8; border-radius: 10px;
            padding: 12px 14px; box-shadow: 0 1px 3px rgba(15, 23, 42, .06);
        }
        .tv-card.tv-level-director { border-left-color: #7c3aed; }
        .tv-card.tv-level-mgr { border-left-color: #3b7ddd; }
        .tv-card.tv-level-exec { border-left-color: #20c997; }
        .tv-avatar { width: 56px; height: 56px; border-radius: 50%; object-fit: cover; flex-shrink: 0; border: 2px solid #fff; box-shadow: 0 0 0 1px #d5dce8; }
        .tv-info { min-width: 0; flex: 1; }
        .tv-name { font-weight: 700; font-size: 1rem; color: #0f172a; display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .tv-desig { color: #475569; font-size: .88rem; margin-bottom: 4px; }
        .tv-badge { font-size: 11px; font-weight: 700; color: #fff; border-radius: 10px; padding: 2px 8px; }
        .tv-badge-director { background: #7c3aed; }
        .tv-badge-mgr { background: #3b7ddd; }
        .tv-badge-exec { background: #20c997; }
        .tv-count { font-size: 11px; font-weight: 600; color: #475569; background: #eef2f7; border-radius: 10px; padding: 2px 8px; }
        .tv-contact { display: flex; flex-wrap: wrap; gap: 6px 18px; font-size: .85rem; }
        .tv-contact a { color: #334155; text-decoration: none; }
        .tv-contact a:hover { color: #3b7ddd; text-decoration: underline; }
        .tv-rr { margin-top: 6px; font-size: .85rem; display: flex; gap: 8px; align-items: flex-start; }
        .tv-rr-label { font-weight: 700; color: #0f172a; background: #fef3c7; border-radius: 6px; padding: 0 6px; flex-shrink: 0; }
        .tv-rr summary { cursor: pointer; color: #3b7ddd; font-weight: 600; }
        .tv-rr ul { margin: 6px 0 0; padding-left: 18px; color: #334155; max-height: 220px; overflow-y: auto; }
        .tv-section-title { font-weight: 700; color: #0f172a; margin: 22px 0 6px; }
        .tv-hidden { display: none !important; }
        .tv-legend span { margin-right: 14px; font-size: 13px; }
        .tv-legend i { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 4px; }
    </style>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-12">
                <div class="page-title-box d-flex align-items-center justify-content-between py-1 flex-wrap gap-2">
                    <h4 class="page-title mb-0">Team View</h4>
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div class="tv-legend">
                            <span><i style="background:#7c3aed"></i>Director</span>
                            <span><i style="background:#3b7ddd"></i>Manager</span>
                            <span><i style="background:#20c997"></i>Executive</span>
                        </div>
                        <input type="search" id="tvSearch" class="form-control form-control-sm" style="width:260px"
                               placeholder="Search name, designation, email, phone, R&R">
                    </div>
                </div>
                <div class="text-muted small">{{ $total }} active team members &middot; read-only</div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                @if(count($tree))
                    <ul class="tv-tree" id="tvTree">
                        @foreach($tree as $member)
                            @include('team-view.node', ['member' => $member])
                        @endforeach
                    </ul>
                @endif

                @if(count($unassigned))
                    <div class="tv-section-title">Other Team Members (no reporting line set)</div>
                    <ul class="tv-tree" id="tvUnassigned">
                        @foreach($unassigned as $member)
                            @include('team-view.node', ['member' => $member])
                        @endforeach
                    </ul>
                @endif

                @if(!count($tree) && !count($unassigned))
                    <div class="alert alert-info">No team members found.</div>
                @endif
                <div id="tvNoMatch" class="alert alert-warning tv-hidden">No team members match your search.</div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        (function () {
            var input = document.getElementById('tvSearch');
            if (!input) return;
            var nodes = Array.prototype.slice.call(document.querySelectorAll('.tv-node'));

            input.addEventListener('input', function () {
                var q = input.value.trim().toLowerCase();
                nodes.forEach(function (n) { n.classList.remove('tv-hidden'); });
                var anyVisible = true;
                if (q) {
                    anyVisible = false;
                    // A node stays visible if it matches itself or any descendant matches
                    // (so the reporting chain above a match is preserved).
                    nodes.forEach(function (n) {
                        var show = Array.prototype.some.call(n.querySelectorAll('.tv-card'), function (c) {
                            return (c.textContent || '').toLowerCase().indexOf(q) !== -1;
                        });
                        n.classList.toggle('tv-hidden', !show);
                        if (show) anyVisible = true;
                    });
                }
                document.getElementById('tvNoMatch').classList.toggle('tv-hidden', anyVisible);
                if (q) {
                    document.querySelectorAll('.tv-rr details').forEach(function (d) {
                        d.open = (d.textContent || '').toLowerCase().indexOf(q) !== -1;
                    });
                }
            });
        })();
    </script>
@endsection
