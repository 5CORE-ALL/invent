@extends('layouts.vertical', ['title' => 'Org Mails'])

@section('css')
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        #org-mails {
            --ink: #1c1430;
            --muted: #6d6480;
            --line: #efe7fb;
            --card: #fff;
            --violet: #7c3aed;
            --pink: #ec4899;
            --lime: #bef264;
            font-family: "Segoe UI", "Trebuchet MS", sans-serif;
            color: var(--ink);
        }
        #org-mails .om-hero {
            position: relative; overflow: hidden; border-radius: 28px; padding: 28px 28px 22px;
            background:
                radial-gradient(420px 180px at 8% -20%, #f9a8d4 0%, transparent 70%),
                radial-gradient(380px 200px at 100% 0%, #c4b5fd 0%, transparent 65%),
                linear-gradient(135deg, #2a1848 0%, #4c1d95 55%, #7c3aed 100%);
            color: #fff; box-shadow: 0 18px 40px rgba(76, 29, 149, .28);
        }
        #org-mails .om-kicker {
            display: inline-flex; align-items: center; gap: 8px; background: var(--lime); color: #1a2e05;
            font-weight: 800; font-size: 12px; letter-spacing: .08em; text-transform: uppercase;
            border-radius: 999px; padding: 5px 12px;
        }
        #org-mails .om-hero h1 { font-size: 34px; font-weight: 800; letter-spacing: -0.03em; margin: 14px 0 6px; }
        #org-mails .om-hero p { margin: 0; max-width: 640px; color: #efe7fb; font-size: 14.5px; }
        #org-mails .om-stats { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; }
        #org-mails .om-stat {
            background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.22);
            border-radius: 16px; padding: 10px 14px; min-width: 110px; backdrop-filter: blur(6px);
        }
        #org-mails .om-stat b { display: block; font-size: 22px; line-height: 1.1; }
        #org-mails .om-stat span { font-size: 11px; letter-spacing: .06em; text-transform: uppercase; color: #ddd6fe; }
        #org-mails .om-bar {
            display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin: 16px 0;
            background: var(--card); border: 1px solid var(--line); border-radius: 20px; padding: 12px;
        }
        #org-mails .om-search, #org-mails .om-select {
            border: 1.5px solid #e9defa; border-radius: 14px; padding: 9px 12px; font-size: 14px; color: var(--ink); background: #fbf8ff;
        }
        #org-mails .om-search { flex: 1; min-width: 220px; }
        #org-mails .om-search:focus, #org-mails .om-select:focus { outline: 2px solid #ddd6fe; border-color: var(--violet); }
        #org-mails .om-chip {
            border: 0; border-radius: 999px; padding: 8px 14px; font-weight: 700; font-size: 13px; cursor: pointer; background: #f4eefe; color: #5b21b6;
        }
        #org-mails .om-chip.is-on { background: var(--ink); color: #fff; }
        #org-mails .om-table-card {
            background: var(--card); border: 1px solid var(--line); border-radius: 24px; padding: 8px; overflow: hidden;
        }
        #org-mails .tabulator { border: 0; background: transparent; font-size: 14px; }
        #org-mails .tabulator-header { border: 0; background: transparent; }
        #org-mails .tabulator-col { background: transparent !important; border-right: 0 !important; }
        #org-mails .tabulator-col-title { color: var(--muted); font-size: 11px; letter-spacing: .08em; text-transform: uppercase; font-weight: 800; }
        #org-mails .tabulator-row { border-bottom: 1px solid #f6f1fb; cursor: pointer; }
        #org-mails .tabulator-row:hover { background: #faf6ff !important; }
        #org-mails .tabulator-cell { border-right: 0 !important; padding: 12px 10px !important; }
        #org-mails .tabulator-footer { border: 0; background: transparent; }
        #org-mails .om-person { display: flex; align-items: center; gap: 10px; }
        #org-mails .om-avatar {
            width: 36px; height: 36px; border-radius: 12px; display: grid; place-items: center; flex: none;
            font-weight: 800; color: #fff; background: linear-gradient(135deg, var(--violet), var(--pink));
        }
        #org-mails .om-name { font-weight: 750; }
        #org-mails .om-mail { color: var(--muted); font-size: 12.5px; }
        #org-mails .om-pill { display: inline-block; border-radius: 999px; padding: 3px 9px; font-size: 12px; font-weight: 700; background: #f4eefe; color: #5b21b6; }
        #org-mails .om-pill.off { background: #f3f4f6; color: #6b7280; }
        #org-mails .om-open {
            border: 0; border-radius: 999px; padding: 7px 12px; font-weight: 800; font-size: 12.5px; cursor: pointer;
            background: var(--ink); color: #fff;
        }
        #org-mails .om-open:hover { background: var(--violet); }
        #org-mails .om-mini {
            border: 1.5px solid #e9defa; background: #fff; color: var(--ink); border-radius: 999px; padding: 6px 10px;
            font-weight: 700; font-size: 12px; cursor: pointer; margin-left: 4px;
        }
        #org-mails .om-note { color: var(--muted); font-size: 12.5px; margin: 8px 4px 0; }
    </style>
@endsection

@section('content')
    <div id="org-mails">
        <div class="om-hero">
            <span class="om-kicker"><i class="fa-brands fa-google"></i> {{ $domain }}</span>
            <h1>Org mails</h1>
            <p>Every mailbox this app knows. Click a row, or Open, and Google asks for that account, then drops you in its Gmail. Drive and Calendar are right there too.</p>
            <div class="om-stats">
                <div class="om-stat"><b id="om-total">0</b><span>mailboxes</span></div>
                <div class="om-stat"><b id="om-org">0</b><span>{{ $domain }}</span></div>
                <div class="om-stat"><b id="om-active">0</b><span>active</span></div>
            </div>
        </div>

        <div class="om-bar">
            <input id="om-search" class="om-search" type="search" placeholder="Search a name or a mail…" autocomplete="off">
            <select id="om-domain" class="om-select" aria-label="Domain">
                <option value="org">{{ $domain }} only</option>
                <option value="all">Every domain</option>
            </select>
            <button type="button" class="om-chip is-on" id="om-active-only" data-on="1">Active only</button>
        </div>

        <div class="om-table-card">
            <div id="om-table"></div>
        </div>
        <p class="om-note">A mailbox that was never added as a user here will not show. Opening one uses the Google account already signed in on this browser; otherwise Google asks you to sign in. Nothing is stored and no password is used.</p>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        const OM_DOMAIN = @json($domain);
        let omTable = null;

        function omEsc(value) {
            return String(value ?? '').replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }

        function omInitials(name) {
            const parts = String(name || '').trim().split(/\s+/).slice(0, 2);
            return (parts.map(p => p.charAt(0)).join('') || '?').toUpperCase();
        }

        function omOpen(url) {
            if (url) window.open(url, '_blank', 'noopener');
        }

        function omApply() {
            if (!omTable) return;
            const q = (document.getElementById('om-search').value || '').trim().toLowerCase();
            const domain = document.getElementById('om-domain').value;
            const activeOnly = document.getElementById('om-active-only').dataset.on === '1';
            omTable.setFilter(function (row) {
                if (domain === 'org' && row.domain !== OM_DOMAIN) return false;
                if (activeOnly && !row.active) return false;
                if (!q) return true;
                return (row.name + ' ' + row.email + ' ' + row.role + ' ' + row.designation).toLowerCase().includes(q);
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            omTable = new Tabulator('#om-table', {
                ajaxURL: @json(route('org.mails.data')),
                ajaxResponse: function (url, params, response) {
                    const meta = response.meta || {};
                    document.getElementById('om-total').textContent = (meta.total || 0).toLocaleString();
                    document.getElementById('om-org').textContent = (meta.org || 0).toLocaleString();
                    document.getElementById('om-active').textContent = (meta.active || 0).toLocaleString();
                    return response.data || [];
                },
                layout: 'fitColumns',
                height: '640px',
                pagination: true,
                paginationSize: 25,
                paginationSizeSelector: [25, 50, 100],
                placeholder: 'No mailboxes yet.',
                rowClick: function (e, row) {
                    if (e.target.closest('button')) return;
                    omOpen(row.getData().gmail);
                },
                columns: [
                    {
                        title: 'Who', field: 'name', minWidth: 240, widthGrow: 3,
                        formatter: function (cell) {
                            const d = cell.getRow().getData();
                            return '<div class="om-person"><span class="om-avatar">' + omInitials(d.name) + '</span><span><span class="om-name">'
                                + omEsc(d.name) + '</span><br><span class="om-mail">'
                                + omEsc(d.email) + '</span></span></div>';
                        }
                    },
                    {
                        title: 'Role', field: 'role', width: 150,
                        formatter: function (cell) {
                            const v = cell.getValue();
                            return v ? '<span class="om-pill">' + omEsc(v) + '</span>' : '';
                        }
                    },
                    { title: 'Designation', field: 'designation', minWidth: 140, widthGrow: 2 },
                    {
                        title: 'Status', field: 'active', width: 110, hozAlign: 'center',
                        formatter: function (cell) {
                            return cell.getValue()
                                ? '<span class="om-pill">active</span>'
                                : '<span class="om-pill off">inactive</span>';
                        }
                    },
                    {
                        title: 'Open', field: 'gmail', hozAlign: 'right', headerSort: false, minWidth: 280,
                        formatter: function (cell) {
                            const d = cell.getRow().getData();
                            return '<button type="button" class="om-open" data-url="' + d.gmail + '">Open Gmail</button>'
                                + '<button type="button" class="om-mini" data-url="' + d.drive + '">Drive</button>'
                                + '<button type="button" class="om-mini" data-url="' + d.calendar + '">Calendar</button>';
                        }
                    }
                ]
            });

            omTable.on('dataProcessed', omApply);
            document.getElementById('om-table').addEventListener('click', function (e) {
                const btn = e.target.closest('button[data-url]');
                if (btn) omOpen(btn.dataset.url);
            });
            document.getElementById('om-search').addEventListener('input', omApply);
            document.getElementById('om-domain').addEventListener('change', omApply);
            document.getElementById('om-active-only').addEventListener('click', function () {
                this.dataset.on = this.dataset.on === '1' ? '0' : '1';
                this.classList.toggle('is-on', this.dataset.on === '1');
                this.textContent = this.dataset.on === '1' ? 'Active only' : 'Showing inactive too';
                omApply();
            });
        });
    </script>
@endsection
