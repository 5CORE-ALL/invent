@extends('layouts.vertical', ['title' => $title ?? 'Live Recordings'])

@section('css')
<style>
    .rc-card { border: 1px solid rgba(0,0,0,.08); border-radius: 12px; background: #fff; }
    .rc-toolbar { display: flex; flex-wrap: wrap; align-items: flex-end; gap: .5rem; }
    .rc-toolbar .form-select, .rc-toolbar .form-control { min-height: 34px; font-size: .85rem; min-width: 150px; }
    .rc-toolbar label { font-size: .7rem; text-transform: uppercase; letter-spacing: .03em; color: #64748b; font-weight: 600; }
    .rc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: .9rem; }
    .rc-item {
        border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; overflow: hidden;
        display: flex; flex-direction: column; transition: box-shadow .15s, transform .15s;
    }
    .rc-item:hover { box-shadow: 0 10px 24px rgba(15,23,42,.12); transform: translateY(-2px); }
    .rc-thumb {
        position: relative; aspect-ratio: 16 / 9; background: #0f172a; color: #e2e8f0;
        display: flex; align-items: center; justify-content: center; cursor: pointer; border: 0; width: 100%;
    }
    .rc-thumb i { font-size: 2.4rem; opacity: .85; }
    .rc-thumb:hover i { opacity: 1; color: #38bdf8; }
    .rc-dur {
        position: absolute; right: .4rem; bottom: .4rem; background: rgba(2,6,23,.82);
        color: #f8fafc; font-size: .7rem; font-weight: 700; padding: .1rem .4rem; border-radius: 4px;
        font-variant-numeric: tabular-nums;
    }
    .rc-body { padding: .6rem .7rem; display: flex; flex-direction: column; gap: .2rem; min-width: 0; }
    .rc-name { font-weight: 600; font-size: .85rem; color: #0f172a; }
    .rc-when { font-size: .75rem; color: #475569; }
    .rc-sub { font-size: .72rem; color: #94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .rc-foot { display: flex; align-items: center; justify-content: space-between; gap: .4rem; margin-top: .3rem; }
    .rc-empty { padding: 3rem 1rem; text-align: center; color: #64748b; }
    .rc-empty i { font-size: 2.5rem; color: #cbd5e1; display: block; margin-bottom: .6rem; }
    #rcPlayer { width: 100%; max-height: 72vh; background: #000; border-radius: 6px; display: block; }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="rc-card p-3 mb-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
                <h4 class="mb-0">Live Recordings</h4>
                <div class="text-muted" style="font-size:.78rem">
                    {{ $recordings->total() }} recording{{ $recordings->total() === 1 ? '' : 's' }} · {{ $total_seconds_label }} on this page
                </div>
            </div>
            <a href="{{ route('attendance.live.team') }}" class="btn btn-sm btn-danger">
                <i class="ri-vidicon-line me-1"></i> Team Monitor Video
            </a>
        </div>

        <form method="GET" class="rc-toolbar">
            <div>
                <label for="rcEmployee">Employee</label>
                <select name="employee" id="rcEmployee" class="form-select">
                    <option value="">All employees</option>
                    @foreach ($employees as $person)
                        <option value="{{ $person->id }}" @selected((int) $employee === (int) $person->id)>{{ $person->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="rcFrom">From</label>
                <input type="date" name="from" id="rcFrom" class="form-control" value="{{ $from }}">
            </div>
            <div>
                <label for="rcTo">To</label>
                <input type="date" name="to" id="rcTo" class="form-control" value="{{ $to }}">
            </div>
            <div>
                <label for="rcTz">Timezone</label>
                <select name="timezone" id="rcTz" class="form-select">
                    @foreach ($timezones as $tz => $tzLabel)
                        <option value="{{ $tz }}" @selected($timezone === $tz)>{{ $tzLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                <a href="{{ route('attendance.live.recordings') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>

    <div class="rc-card p-3">
        @if ($rows->isEmpty())
            <div class="rc-empty">
                <i class="ri-film-line"></i>
                <div class="fw-semibold">No recordings in this range</div>
                <p class="mb-0" style="font-size:.82rem; max-width:34rem; margin:.4rem auto 0">
                    A recording is saved when you open an employee's live video page and keep the tab open.
                    The Team Monitor Video wall shows live tiles but does not record.
                </p>
            </div>
        @else
            <div class="rc-grid">
                @foreach ($rows as $row)
                    <div class="rc-item">
                        <button type="button" class="rc-thumb js-play"
                                data-url="{{ $row['play_url'] }}"
                                data-title="{{ $row['name'] }} — {{ $row['started_label'] }}">
                            <i class="ri-play-circle-line"></i>
                            <span class="rc-dur">{{ $row['duration_label'] }}</span>
                        </button>
                        <div class="rc-body">
                            <div class="rc-name">{{ $row['name'] }}</div>
                            <div class="rc-when">{{ $row['started_label'] }}</div>
                            @if ($row['app_name'] || $row['window_title'])
                                <div class="rc-sub" title="{{ trim(($row['app_name'] ?? '').' '.($row['window_title'] ?? '')) }}">
                                    {{ collect([$row['app_name'], $row['window_title']])->filter()->implode(' — ') }}
                                </div>
                            @endif
                            <div class="rc-foot">
                                <span class="rc-sub">{{ $row['size_label'] }}@if ($row['viewer']) · by {{ $row['viewer'] }}@endif</span>
                                <a href="{{ $row['play_url'] }}" class="btn btn-sm btn-link p-0" download>Download</a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-3">
                {{ $recordings->onEachSide(1)->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
</div>

<div class="modal fade" id="rcModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="rcModalTitle">Recording</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-2">
                <video id="rcPlayer" controls preload="metadata"></video>
            </div>
            <div class="modal-footer py-2">
                <a href="#" class="btn btn-sm btn-outline-primary" id="rcDownload" download>Download</a>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
(function () {
    const modalEl = document.getElementById('rcModal');
    const player = document.getElementById('rcPlayer');
    const titleEl = document.getElementById('rcModalTitle');
    const downloadEl = document.getElementById('rcDownload');

    document.querySelectorAll('.js-play').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const url = btn.dataset.url;
            titleEl.textContent = btn.dataset.title || 'Recording';
            downloadEl.href = url;
            player.src = url;
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
            player.play().catch(function () {});
        });
    });

    // Keep audio/video from running on in a hidden modal.
    modalEl.addEventListener('hidden.bs.modal', function () {
        player.pause();
        player.removeAttribute('src');
        player.load();
    });
})();
</script>
@endsection
