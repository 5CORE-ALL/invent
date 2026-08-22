@extends('layouts.vertical', ['title' => 'Messages Pending', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-paginator label { margin-right: 5px; }
        .mm-channel-logo {
            width: 28px;
            height: 28px;
            object-fit: contain;
            border-radius: 4px;
            background: #fff;
            border: 1px solid #e9ecef;
            padding: 1px;
            display: inline-block;
        }
        .mm-channel-logo-placeholder {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 4px;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            color: #adb5bd;
            font-size: 12px;
        }
        .mm-listings-arrow {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            text-decoration: none;
            line-height: 1;
        }
        .mm-listings-arrow-on {
            color: #0d6efd;
        }
        .mm-listings-arrow-on:hover {
            color: #0a58ca;
        }
        .mm-listings-arrow-off {
            color: #dc3545;
            cursor: pointer;
        }
        #stat-messages-pending.badge,
        .badge-mm-stat {
            font-size: 1.35rem !important;
            line-height: 1.35;
            padding: 0.75rem 1.25rem !important;
            border-radius: 0.35rem !important;
            font-weight: 700;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Messages Pending',
        'sub_title'  => 'Customer Care',
    ])

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <span class="badge bg-danger badge-mm-stat" id="stat-messages-pending" title="Sum of pending marketplace messages" style="background-color:#a71d2a !important;">
                        Messages Pending: <span id="total-messages-pending">{{ number_format($pendingTotal ?? 0) }}</span>
                    </span>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="cmp-pull-btn">
                        <i class="fas fa-rotate me-1"></i>Pull messages
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="cmp-open-all-btn" title="Open every marketplace messages link in its own tab">
                        <i class="fas fa-up-right-from-square me-1"></i>Open all marketplace tabs
                    </button>
                    <span class="small text-muted" id="cmp-pull-status">Counts come from each marketplace API.</span>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="p-2 bg-light border-bottom">
                    <input type="text" id="messages-pending-search" class="form-control form-control-sm" placeholder="Search by Channel...">
                </div>
                <div id="messages-pending-table" style="height: calc(100vh - 280px);"></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="cmpLinkModal" tabindex="-1" aria-labelledby="cmpLinkModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title fw-semibold mb-0" id="cmpLinkModalLabel">Messages page link</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="small text-muted mb-2" id="cmp-link-modal-channel">—</div>
                    <label class="form-label small mb-1" for="cmp-link-modal-input">URL</label>
                    <input type="url" class="form-control form-control-sm" id="cmp-link-modal-input"
                        placeholder="https://…" autocomplete="off">
                    <div class="small text-muted mt-1">Leave blank and save to clear the link.</div>
                    <div class="small text-danger mt-2 d-none" id="cmp-link-modal-error"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" id="cmp-link-modal-save">Save</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
    const channels = @json($channels);
    const urlPull = @json(route('customer.care.messages.pending.pull'));
    const urlSaveLink = @json(route('customer.care.messages.pending.link.save'));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let table = null;

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function mmLogoSrc(logo) {
        const v = String(logo || '').trim();
        if (!v) return '';
        if (/^https?:\/\//i.test(v) || v.startsWith('/')) return v;
        return '/storage/' + v.replace(/^\/+/, '');
    }

    function api(url, opts) {
        const headers = Object.assign({
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest',
        }, (opts && opts.headers) || {});
        return fetch(url, Object.assign({}, opts, { headers })).then(function (r) {
            return r.text().then(function (text) {
                let j = null;
                try { j = text ? JSON.parse(text) : null; } catch (e) { j = null; }
                if (!r.ok) {
                    const err = new Error((j && j.message) || ('HTTP ' + r.status));
                    err.body = j;
                    throw err;
                }
                return j;
            });
        });
    }

    function updateStats(rows, serverTotal) {
        const total = serverTotal != null
            ? Number(serverTotal)
            : (rows || []).reduce(function (sum, r) {
                return r.fetch_status === 'ok' ? sum + Number(r.pending_count || 0) : sum;
            }, 0);
        $('#total-messages-pending').text(total.toLocaleString('en-US'));
        $('.cc-messages-pending-sidebar-badge').each(function () {
            const $el = $(this);
            $el.text(total.toLocaleString('en-US'));
            $el.toggle(total > 0);
        });
    }

    function applyPullRow(channelId, row, serverTotal) {
        if (!table || !row) return;
        table.getRows().forEach(function (r) {
            if (String(r.getData().id) === String(channelId)) {
                r.update({
                    pending_count: row.pending_count,
                    fetch_status: row.fetch_status,
                    fetch_note: row.fetch_note,
                    last_fetched_at: row.last_fetched_at,
                });
            }
        });
        updateStats(table.getData(), serverTotal);
    }

    function pullAll(force) {
        if (!table) return;
        const rows = table.getData();
        const ids = rows.map(function (r) { return r.id; });
        const $btn = $('#cmp-pull-btn');
        const $st = $('#cmp-pull-status');
        $btn.prop('disabled', true);
        let i = 0;

        function next() {
            if (i >= ids.length) {
                $btn.prop('disabled', false);
                $st.text('Pulled ' + ids.length + ' channels from marketplace APIs.');
                return;
            }
            const id = ids[i];
            i += 1;
            $st.text('Pulling ' + i + ' / ' + ids.length + '…');
            api(urlPull, {
                method: 'POST',
                body: JSON.stringify({ channel_id: id }),
            }).then(function (resp) {
                applyPullRow(id, resp && resp.row, resp && resp.pending_total);
            }).catch(function (e) {
                applyPullRow(id, {
                    pending_count: 0,
                    fetch_status: 'error',
                    fetch_note: (e && e.message) || 'Pull failed',
                    last_fetched_at: null,
                });
            }).finally(next);
        }

        next();
    }

    function collectMarketplaceLinks() {
        const rows = table ? table.getData() : channels;
        const seen = {};
        const urls = [];
        (rows || []).forEach(function (r) {
            const url = String(r.messages_link || '').trim();
            if (!url || seen[url]) return;
            seen[url] = true;
            urls.push(url);
        });
        return urls;
    }

    function openAllMarketplaceTabs() {
        const urls = collectMarketplaceLinks();
        const $st = $('#cmp-pull-status');
        if (!urls.length) {
            $st.text('No marketplace links to open. Add a link on a red arrow first.');
            return;
        }

        const stamp = Date.now();
        let opened = 0;
        for (let i = 0; i < urls.length; i++) {
            const win = window.open(urls[i], 'cmp_mp_' + stamp + '_' + i);
            if (win) opened += 1;
        }

        if (opened >= urls.length) {
            $st.text('Opened all ' + opened + ' marketplace tabs.');
            return;
        }

        $st.text('Opened ' + opened + ' of ' + urls.length +
            '. Allow pop-ups for this site in the address bar, then click this same button once to open all remaining tabs.');
    }

    const linkModalEl = document.getElementById('cmpLinkModal');
    const linkChannelEl = document.getElementById('cmp-link-modal-channel');
    const linkInputEl = document.getElementById('cmp-link-modal-input');
    const linkErrorEl = document.getElementById('cmp-link-modal-error');
    const linkSaveBtn = document.getElementById('cmp-link-modal-save');
    let linkCtx = null;

    function openLinkModal(row) {
        if (!linkModalEl || typeof bootstrap === 'undefined') return;
        linkCtx = row;
        if (linkChannelEl) linkChannelEl.textContent = row.channel || '';
        if (linkInputEl) linkInputEl.value = row.messages_link || '';
        if (linkErrorEl) {
            linkErrorEl.textContent = '';
            linkErrorEl.classList.add('d-none');
        }
        bootstrap.Modal.getOrCreateInstance(linkModalEl).show();
        setTimeout(function () {
            if (linkInputEl) {
                linkInputEl.focus();
                linkInputEl.select();
            }
        }, 200);
    }

    if (linkSaveBtn) {
        linkSaveBtn.addEventListener('click', function () {
            if (!linkCtx) return;
            const val = linkInputEl ? (linkInputEl.value || '').trim() : '';
            linkSaveBtn.disabled = true;
            api(urlSaveLink, {
                method: 'POST',
                body: JSON.stringify({ channel_id: linkCtx.id, value: val }),
            }).then(function (resp) {
                const newVal = resp && resp.value ? resp.value : (val || null);
                if (table) {
                    table.getRows().forEach(function (r) {
                        if (String(r.getData().id) === String(linkCtx.id)) {
                            r.update({ messages_link: newVal });
                        }
                    });
                }
                bootstrap.Modal.getOrCreateInstance(linkModalEl).hide();
            }).catch(function (e) {
                if (linkErrorEl) {
                    linkErrorEl.textContent = (e && e.message) || 'Could not save link.';
                    linkErrorEl.classList.remove('d-none');
                }
            }).finally(function () {
                linkSaveBtn.disabled = false;
            });
        });
    }

    $(document).ready(function () {
        table = new Tabulator('#messages-pending-table', {
            data: channels,
            layout: 'fitDataStretch',
            pagination: true,
            paginationSize: 50,
            paginationSizeSelector: [25, 50, 100, 200, 500],
            initialSort: [{ column: 'pending_count', dir: 'desc' }],
            placeholder: 'No channels found.',
            columns: [
                {
                    title: 'Image',
                    field: 'logo',
                    headerSort: false,
                    width: 90,
                    hozAlign: 'center',
                    formatter: function (cell) {
                        const logo = cell.getValue();
                        const channel = (cell.getRow().getData().channel || '').trim();
                        if (!logo) {
                            return '<span class="mm-channel-logo-placeholder" title="No logo"><i class="fas fa-image"></i></span>';
                        }
                        const src = mmLogoSrc(logo);
                        return `<img src="${escapeHtml(src)}" alt="${escapeHtml(channel)}" class="mm-channel-logo" onerror="this.style.display='none'">`;
                    },
                },
                {
                    title: 'Channel',
                    field: 'channel',
                    minWidth: 260,
                    formatter: function (cell) {
                        const name = (cell.getValue() || '').trim();
                        return name ? escapeHtml(name) : '';
                    },
                },
                {
                    title: 'Messages Pending',
                    field: 'pending_count',
                    width: 180,
                    hozAlign: 'center',
                    sorter: 'number',
                    headerTooltip: 'Unread / unanswered messages from the marketplace API',
                    formatter: function (cell) {
                        const row = cell.getRow().getData() || {};
                        const status = row.fetch_status || '';
                        if (status === 'unsupported') {
                            return '<span class="text-muted" title="' + escapeHtml(row.fetch_note || 'No possible API') + '">—</span>';
                        }
                        if (status === 'error') {
                            return '<span class="text-warning" title="' + escapeHtml(row.fetch_note || 'Pull failed') + '"><i class="fas fa-triangle-exclamation"></i></span>';
                        }
                        if (status === 'pending') {
                            return '<span class="text-muted">…</span>';
                        }
                        const v = Number(cell.getValue() || 0);
                        const color = v === 0 ? '#198754' : '#dc3545';
                        return `<span style="color:${color};font-weight:700;">${v.toLocaleString('en-US')}</span>`;
                    },
                    bottomCalc: function (values, data) {
                        return (data || []).reduce(function (sum, r) {
                            return r.fetch_status === 'ok' ? sum + Number(r.pending_count || 0) : sum;
                        }, 0);
                    },
                },
                {
                    title: 'Source',
                    field: 'fetch_status',
                    width: 170,
                    hozAlign: 'center',
                    formatter: function (cell) {
                        const row = cell.getRow().getData() || {};
                        const status = cell.getValue() || '';
                        if (status === 'ok') return '<span class="text-success" title="Live marketplace API">API</span>';
                        if (status === 'unsupported') return '<span class="text-muted" title="' + escapeHtml(row.fetch_note || 'No possible API') + '">No possible API</span>';
                        if (status === 'error') return '<span class="text-danger" title="' + escapeHtml(row.fetch_note || '') + '">Error</span>';
                        return '<span class="text-muted">Waiting</span>';
                    },
                },
                {
                    title: 'Link',
                    field: 'messages_link',
                    headerSort: false,
                    width: 90,
                    hozAlign: 'center',
                    headerHozAlign: 'center',
                    headerTooltip: 'Open this channel’s messages page',
                    formatter: function (cell) {
                        const row = cell.getRow().getData() || {};
                        const url = (cell.getValue() || '').trim();
                        const name = (row.channel || 'channel').trim();
                        if (!url) {
                            return `<span class="mm-listings-arrow mm-listings-arrow-off" title="Click to add messages page link"><i class="fas fa-arrow-up-right-from-square"></i></span>`;
                        }
                        return `<a href="${escapeHtml(url)}" class="mm-listings-arrow mm-listings-arrow-on" title="Open ${escapeHtml(name)} messages" target="_blank" rel="noopener noreferrer"><i class="fas fa-arrow-up-right-from-square"></i></a>`;
                    },
                    cellClick: function (e, cell) {
                        const row = cell.getRow().getData() || {};
                        const url = (row.messages_link || '').trim();
                        if (url && !e.detail) return;
                        if (!url || e.detail === 2) {
                            e.preventDefault();
                            e.stopPropagation();
                            openLinkModal(row);
                        }
                    },
                },
            ],
        });

        updateStats(channels);

        $('#cmp-pull-btn').on('click', function () {
            pullAll(true);
        });
        $('#cmp-open-all-btn').on('click', function () {
            openAllMarketplaceTabs();
        });
        pullAll(false);

        $('#messages-pending-search').on('input', function () {
            const q = $(this).val().trim().toLowerCase();
            if (!q) {
                table.clearFilter(true);
                return;
            }
            table.setFilter(function (row) {
                return String(row.channel || '').toLowerCase().includes(q);
            });
        });
    });
</script>
@endsection
