@extends('layouts.vertical', ['title' => 'Video Ads Master', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">

    {{-- Tabulator look-and-feel matched to resources/views/usage-images-master.blade.php
         (vertical column headers, no sort triangles, common spacing rules). --}}
    <style>
        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }

        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            white-space: nowrap;
            font-size: 12px;
            font-weight: 600;
            color: #1f2937;
            letter-spacing: 0.3px;
        }

        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0px !important;
        }

        .tabulator-paginator label {
            margin-right: 5px;
        }

        .parent-row {
            background-color: #fffacd !important;
        }

        .copy-sku-btn {
            cursor: pointer;
            padding: 2px 5px;
            margin-left: 5px;
        }

        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }

        /* Video Ads Master accents (kept from the previous version). */
        .vam-link-icon {
            color: #2c6ed5;
            font-size: 16px;
            text-decoration: none;
        }
        .vam-link-icon:hover { color: #0a3d8f; }
        .vam-dash { color: #adb5bd; }

        .vam-target-pill {
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 2px 6px;
            border-radius: 4px;
            margin-right: 6px;
            color: #fff;
        }
        .vam-target-pill.sku    { background: #2c6ed5; }
        .vam-target-pill.parent { background: #16a34a; }
        .vam-target-pill.group  { background: #ea580c; }

        /* Count badges on the toolbar — same shape & size for every kind so
           the strip reads as a uniform set. Width is fixed (min-width) so the
           badges don't change size as numbers grow into the hundreds. */
        .vam-count-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-width: 100px;
            height: 32px;
            padding: 0 14px;
            border-radius: 4px;
            color: #fff;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.2px;
            white-space: nowrap;
            line-height: 1;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
        }
        .vam-count-badge span {
            font-weight: 800;
            font-size: 13px;
        }
        .vam-count-badge--sku    { background: #2c6ed5; }
        .vam-count-badge--parent { background: #16a34a; }
        .vam-count-badge--group  { background: #ea580c; }
        .vam-count-badge--total  { background: #6b7280; }

        /* Icon-only button — a fixed 32×32 square showing only the icon.
           Bootstrap's default padding is overridden so the button stays
           compact regardless of its label content. Tooltip (title attribute)
           is the only label cue. */
        .vam-icon-btn {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            width: 32px;
            min-width: 32px;
            height: 32px;
            padding: 0 !important;
        }

        /* The list-editor popup (Tabulator's built-in autocomplete dropdown)
           needs to clear modal/sticky-header layers. */
        .tabulator-edit-list { z-index: 10500 !important; }
        .tabulator-edit-list .tabulator-edit-list-item.active,
        .tabulator-edit-list .tabulator-edit-list-item:hover { background: #eef4ff !important; }

        /* Subtle hint that data cells are click-to-edit. The action column
           and the # column are excluded so their buttons / numbers don't
           look "editable". */
        #video-ads-master-table .tabulator-cell { cursor: text; }
        #video-ads-master-table .tabulator-cell[tabulator-field="id"],
        #video-ads-master-table .tabulator-cell[tabulator-field="_actions"] { cursor: default; }
        #video-ads-master-table .tabulator-cell.tabulator-editing { background: #fff8d6 !important; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Video Ads Master',
        'sub_title'  => 'Manage video ads with SKU / Parent / Group targets',
    ])

    <div class="toast-container"></div>

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                {{-- Control Bar — mirrors usage-images-master layout --}}
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                    <button type="button" class="btn btn-sm btn-primary" id="vamAddRowBtn">
                        <i class="fa fa-plus"></i> Add Row
                    </button>

                    <button type="button" class="btn btn-sm btn-success" id="vamImportBtn" title="Upload a CSV of video ads (same headers as the sample template)">
                        <i class="fa fa-upload"></i> Import CSV
                    </button>
                    <a href="{{ route('video.ads.master.sample.csv') }}" class="btn btn-sm btn-outline-secondary vam-icon-btn" id="vamSampleBtn" title="Download a CSV template with 3 example rows">
                        <i class="fa fa-download"></i>
                    </a>
                    <input type="file" id="vamImportFile" accept=".csv,text/csv" style="display: none;">

                    {{-- Count badges (SKU / Parent / Group / Total). They all
                         share .vam-count-badge so width and typography stay
                         identical; only the colour modifier differs. --}}
                    <span class="vam-count-badge vam-count-badge--sku"    title="Rows targeting a SKU">
                        SKU: <span id="vamSkuCount">0</span>
                    </span>
                    <span class="vam-count-badge vam-count-badge--parent" title="Rows targeting a Parent">
                        Parent: <span id="vamParentCount">0</span>
                    </span>
                    <span class="vam-count-badge vam-count-badge--group"  title="Rows targeting a Group">
                        Group: <span id="vamGroupCount">0</span>
                    </span>
                    <span class="vam-count-badge vam-count-badge--total"  title="Total visible rows">
                        Total: <span id="vamRowCount">0</span>
                    </span>
                </div>
            </div>

            <div class="card-body" style="padding: 0;">
                <div id="vam-table-wrapper" style="height: calc(100vh - 240px); display: flex; flex-direction: column;">
                    {{-- Sticky search bar --}}
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="vamSearch" class="form-control form-control-sm" placeholder="Search across all columns…">
                    </div>

                    <div id="video-ads-master-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal: create / edit a video ad row --}}
    <div class="modal fade" id="vamRowModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%); color: #fff;">
                    <h5 class="modal-title" id="vamRowModalTitle">
                        <i class="fas fa-video me-2"></i>Add Video Ad
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="vamRowForm" autocomplete="off">
                        <input type="hidden" id="vam_id">

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">SKU / Parent / Group <span class="text-danger">*</span></label>
                                <select id="vam_target_type" class="form-select" required>
                                    <option value="">— Select —</option>
                                    <option value="sku">SKU</option>
                                    <option value="parent">Parent</option>
                                    <option value="group">Group</option>
                                </select>
                            </div>

                            <div class="col-md-8">
                                <label class="form-label fw-semibold">Name</label>
                                <input type="text" id="vam_name" class="form-control" placeholder="Ad name / label">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Channel</label>
                                <input type="text" id="vam_channel" class="form-control" list="vam-channels-list" placeholder="Pick or type a channel">
                                <datalist id="vam-channels-list"></datalist>
                            </div>

                            <div class="col-md-8">
                                <label class="form-label fw-semibold">Audience</label>
                                <input type="text" id="vam_audience" class="form-control" placeholder="Target audience">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Hook Name</label>
                                <div class="input-group">
                                    <select id="vam_hook_name" class="form-select">
                                        <option value="">— Select hook name —</option>
                                    </select>
                                    <button type="button" class="btn btn-outline-primary" id="vamHookAddBtn" title="Add new hook name">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Hook Message</label>
                                <input type="text" id="vam_hook" class="form-control" placeholder="Hook copy / message">
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Link</label>
                                <input type="url" id="vam_link" class="form-control" placeholder="https://…">
                                <div class="form-text">A link icon will be shown in the table when a URL is set.</div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="vamRowSaveBtn">
                        <i class="fas fa-save me-1"></i>Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal: add a new HOOK NAME option (invoked from the row modal's "+" button) --}}
    <div class="modal fade" id="vamAddHookModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Hook Name</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">New hook name</label>
                    <input type="text" id="vamNewHookName" class="form-control" placeholder="e.g. Curiosity / Pain Point …" autocomplete="off">
                    <div class="form-text">This will be saved and available for all future rows.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="vamSaveNewHookBtn">Save</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>

    <script>
        (function () {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            // ── State ──────────────────────────────────────────────────────────
            let table              = null;
            let channelOptions     = [];
            let hookOptions        = [];
            let rowModal           = null;   // bootstrap.Modal — Add / Edit form
            let addHookModal       = null;   // bootstrap.Modal — "+ Add hook" sub-modal
            let editingId          = null;   // id of the row currently in the form (null = add mode)

            // ── Helpers ────────────────────────────────────────────────────────
            function escapeHtml(s) {
                if (s === null || s === undefined) return '';
                return String(s)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            function showToast(message, type = 'info') {
                const colors = { success: 'bg-success', error: 'bg-danger', info: 'bg-primary', warning: 'bg-warning' };
                const html = `
                    <div class="toast align-items-center text-white ${colors[type] || colors.info} border-0" role="alert">
                        <div class="d-flex">
                            <div class="toast-body">${escapeHtml(message)}</div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                        </div>
                    </div>`;
                const container = document.querySelector('.toast-container');
                const wrapper = document.createElement('div');
                wrapper.innerHTML = html;
                const el = wrapper.firstElementChild;
                container.appendChild(el);
                const toast = new bootstrap.Toast(el, { delay: 2500 });
                toast.show();
                el.addEventListener('hidden.bs.toast', () => el.remove());
            }

            function isLikelyUrl(value) {
                if (!value) return false;
                const v = String(value).trim();
                return /^(https?:)?\/\//i.test(v) || /^www\./i.test(v);
            }
            function normalizeUrl(value) {
                const v = String(value || '').trim();
                if (!v) return '';
                if (/^https?:\/\//i.test(v)) return v;
                if (/^\/\//.test(v))         return 'https:' + v;
                if (/^www\./i.test(v))       return 'https://' + v;
                return v;
            }

            // ── Formatters ─────────────────────────────────────────────────────

            const TARGET_LABELS = { sku: 'SKU', parent: 'Parent', group: 'Group' };

            function targetFormatter(cell) {
                const type = String(cell.getValue() || '').toLowerCase();
                if (!type) return '<span class="vam-dash">—</span>';
                if (!TARGET_LABELS[type]) return escapeHtml(type);
                return `<span class="vam-target-pill ${type}">${TARGET_LABELS[type]}</span>`;
            }

            function plainFormatter(cell) {
                const v = cell.getValue();
                if (v === null || v === undefined || v === '') return '<span class="vam-dash">—</span>';
                return escapeHtml(v);
            }

            function linkFormatter(cell) {
                const v = cell.getValue();
                if (!v || !String(v).trim()) return '<span class="vam-dash">—</span>';
                const url = normalizeUrl(v);
                if (!isLikelyUrl(url)) return '<span class="vam-dash">—</span>';
                return `<a href="${escapeHtml(url)}" target="_blank" rel="noopener" class="vam-link-icon" title="Open link"><i class="fas fa-link"></i></a>`;
            }

            // ── Inline editors ─────────────────────────────────────────────────
            // All three pickers below use Tabulator's built-in `list` editor,
            // which renders its popup attached to <body> so it doesn't get
            // clipped by the small cell.

            const TARGET_TYPE_OPTIONS = [
                { value: 'sku',    label: 'SKU'    },
                { value: 'parent', label: 'Parent' },
                { value: 'group',  label: 'Group'  },
            ];
            function buildChannelLookup() { return (channelOptions || []).map(c => ({ value: c, label: c })); }

            // Native <select> editor for SKU / PARENT / GROUP — gives a true
            // dropdown experience (no text input, no autocomplete). Tabulator's
            // built-in `list` editor renders an input box that opens a panel
            // on click, which the user found confusing.
            function targetTypeEditor(cell, onRendered, success, cancel) {
                const select = document.createElement('select');
                select.className = 'form-select form-select-sm';
                select.style.cssText = 'width:100%;height:100%;padding:0 4px;font-size:13px;border:none;background-color:#fff8d6;';

                const blank = document.createElement('option');
                blank.value = '';
                blank.textContent = '— Select —';
                select.appendChild(blank);

                TARGET_TYPE_OPTIONS.forEach(opt => {
                    const o = document.createElement('option');
                    o.value = opt.value;
                    o.textContent = opt.label;
                    select.appendChild(o);
                });

                select.value = cell.getValue() || '';

                onRendered(() => {
                    select.focus();
                    // Show the option list immediately on modern browsers.
                    if (typeof select.showPicker === 'function') {
                        try { select.showPicker(); } catch (_) { /* not supported in some browsers */ }
                    }
                });

                let committed = false;
                const commit = () => { if (committed) return; committed = true; success(select.value); };

                select.addEventListener('change', commit);
                select.addEventListener('blur',   commit);
                select.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') cancel();
                    if (e.key === 'Enter')  commit();
                });

                return select;
            }

            // HOOK NAME — list of known hooks. The cell editor uses freetext,
            // so the user can also type a brand new hook directly into the
            // cell; hookNameEdited() auto-saves the new value into the global
            // hook_options table.
            function buildHookLookup() {
                return (hookOptions || []).map(n => ({ value: n, label: n }));
            }

            // ── Persistence (single-cell PUT) ──────────────────────────────────

            // Saves whichever cell the user just edited. The SKU/PARENT/GROUP
            // column writes to `target_type`; everything else writes to the
            // column's own field name. Badges are refreshed *immediately*
            // (before the network call) so the UI feels instant.
            function persistCell(cell) {
                const row   = cell.getRow();
                const data  = row.getData();
                if (!data.id) return;

                const field = cell.getField();
                const value = cell.getValue();

                const payload = {};
                if (field === '_target') {
                    payload.target_type = (value === '' || value === null) ? null : value;
                    // Keep our local mirror in sync without re-triggering cellEdited.
                    row.update({ target_type: payload.target_type });
                } else {
                    payload[field] = (value === '' ? null : value);
                }

                // Optimistic badge refresh — the row data is already updated
                // locally, so update the counts before the server replies.
                updateCount();

                fetch(`/video-ads-master/${data.id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(payload),
                })
                .then(r => r.json().then(j => ({ ok: r.ok, j })))
                .then(({ ok, j }) => {
                    if (!ok || !j.success) { showToast((j && j.message) || 'Failed to save', 'error'); return; }
                    updateCount();
                })
                .catch(e => { console.error(e); showToast('Network error while saving', 'error'); });
            }

            // Special cellEdited for HOOK NAME — supports freetext entry. If
            // the typed value isn't already in the global hook list, we save
            // it to video_ads_hook_options first (so it shows up as a real
            // dropdown option for future rows), then persist the row.
            function hookNameEdited(cell) {
                const raw   = cell.getValue();
                const value = (raw === null || raw === undefined) ? '' : String(raw).trim();

                // Empty → just persist NULL on the row.
                if (value === '') { persistCell(cell); return; }

                // Already a known hook → save the row immediately.
                if ((hookOptions || []).includes(value)) { persistCell(cell); return; }

                // Brand-new hook → register it in the global list first, then
                // persist the row. We don't block the row save on a network
                // error here: even if the option write fails, the row's text
                // is still useful and gets saved.
                fetch('/video-ads-master/hook-options', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ name: value }),
                })
                .then(r => r.json())
                .then(j => {
                    if (j && j.success) {
                        hookOptions = j.options || hookOptions;
                        refreshHookSelect(''); // keep modal form's select fresh
                    }
                })
                .catch(e => console.warn('Failed to register new hook option:', e))
                .finally(() => persistCell(cell));
            }

            // ── Modal form helpers ─────────────────────────────────────────────

            // Repopulates the HOOK NAME <select> in the form modal. Called on
            // boot and whenever a new hook option is added.
            function refreshHookSelect(selected) {
                const sel = document.getElementById('vam_hook_name');
                sel.innerHTML = '';
                const blank = document.createElement('option');
                blank.value = '';
                blank.textContent = '— Select hook name —';
                sel.appendChild(blank);
                (hookOptions || []).forEach(name => {
                    const o = document.createElement('option');
                    o.value = name;
                    o.textContent = name;
                    if (selected && name === selected) o.selected = true;
                    sel.appendChild(o);
                });
            }

            // Repopulates the CHANNEL <datalist>. Called on boot.
            function refreshChannelDatalist() {
                const dl = document.getElementById('vam-channels-list');
                dl.innerHTML = '';
                (channelOptions || []).forEach(c => {
                    const o = document.createElement('option');
                    o.value = c;
                    dl.appendChild(o);
                });
            }

            function resetForm() {
                document.getElementById('vam_id').value           = '';
                document.getElementById('vam_target_type').value  = '';
                document.getElementById('vam_name').value         = '';
                document.getElementById('vam_channel').value      = '';
                document.getElementById('vam_audience').value     = '';
                document.getElementById('vam_hook').value         = '';
                document.getElementById('vam_link').value         = '';
                refreshHookSelect('');
            }

            function openAddForm() {
                editingId = null;
                resetForm();
                document.getElementById('vamRowModalTitle').innerHTML = '<i class="fas fa-video me-2"></i>Add Video Ad';
                rowModal.show();
            }

            function openEditForm(data) {
                editingId = data.id;
                resetForm();
                document.getElementById('vamRowModalTitle').innerHTML = '<i class="fas fa-video me-2"></i>Edit Video Ad';
                document.getElementById('vam_id').value          = data.id;
                document.getElementById('vam_target_type').value = data.target_type || '';
                document.getElementById('vam_name').value        = data.name        || '';
                document.getElementById('vam_channel').value     = data.channel     || '';
                document.getElementById('vam_audience').value    = data.audience    || '';
                refreshHookSelect(data.hook_name || '');
                document.getElementById('vam_hook').value        = data.hook        || '';
                document.getElementById('vam_link').value        = data.link        || '';
                rowModal.show();
            }

            // Collect the form into a clean payload object. Empty strings are
            // sent as null so the server can clear cells when the user blanks
            // them out.
            function collectFormPayload() {
                const v = id => {
                    const raw = (document.getElementById(id).value || '').trim();
                    return raw === '' ? null : raw;
                };
                return {
                    target_type: v('vam_target_type'),
                    name:        v('vam_name'),
                    channel:     v('vam_channel'),
                    audience:    v('vam_audience'),
                    hook_name:   v('vam_hook_name'),
                    hook:        v('vam_hook'),
                    link:        v('vam_link'),
                };
            }

            function saveFormRow() {
                const payload = collectFormPayload();
                if (!payload.target_type) {
                    showToast('Please select SKU / Parent / Group', 'warning');
                    document.getElementById('vam_target_type').focus();
                    return;
                }

                const isEdit = !!editingId;
                const url    = isEdit ? `/video-ads-master/${editingId}` : '/video-ads-master';
                const method = isEdit ? 'PUT' : 'POST';

                fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(payload),
                })
                .then(r => r.json().then(j => ({ ok: r.ok, j })))
                .then(({ ok, j }) => {
                    if (!ok || !j.success) {
                        showToast((j && j.message) || 'Failed to save', 'error');
                        return;
                    }
                    const row = j.row;
                    row._target = row.target_type || '';

                    if (isEdit) {
                        table.updateRow(row.id, row);
                        showToast('Row updated', 'success');
                    } else {
                        table.addRow(row, true); // prepend
                        showToast('Row added', 'success');
                    }
                    updateCount();
                    rowModal.hide();
                })
                .catch(e => { console.error(e); showToast('Network error while saving', 'error'); });
            }

            // ── Boot ───────────────────────────────────────────────────────────
            document.addEventListener('DOMContentLoaded', function () {
                rowModal     = new bootstrap.Modal(document.getElementById('vamRowModal'));
                addHookModal = new bootstrap.Modal(document.getElementById('vamAddHookModal'));

                fetch('/video-ads-master/data', { headers: { 'Accept': 'application/json' } })
                    .then(r => r.json())
                    .then(payload => {
                        if (!payload.success) {
                            showToast('Failed to load data', 'error');
                            return;
                        }
                        channelOptions = payload.channels       || [];
                        hookOptions    = payload.hook_options   || [];

                        refreshChannelDatalist();
                        refreshHookSelect('');
                        initTable(payload.rows || []);
                    })
                    .catch(e => {
                        console.error(e);
                        showToast('Network error loading data', 'error');
                    });

                document.getElementById('vamAddRowBtn').addEventListener('click', openAddForm);
                document.getElementById('vamRowSaveBtn').addEventListener('click', saveFormRow);
                document.getElementById('vamSearch').addEventListener('input', applySearch);

                // Import CSV flow: button proxies the hidden file input; the
                // change handler kicks off the upload.
                document.getElementById('vamImportBtn').addEventListener('click', () => {
                    document.getElementById('vamImportFile').click();
                });
                document.getElementById('vamImportFile').addEventListener('change', handleImportFile);

                // "+" next to the HOOK NAME select inside the row form opens
                // the nested "add new hook" modal.
                document.getElementById('vamHookAddBtn').addEventListener('click', () => {
                    document.getElementById('vamNewHookName').value = '';
                    addHookModal.show();
                });

                document.getElementById('vamSaveNewHookBtn').addEventListener('click', saveNewHook);
                document.getElementById('vamNewHookName').addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') { e.preventDefault(); saveNewHook(); }
                });
            });

            function initTable(rows) {
                // Display-only mirror field. The DB has just `target_type`;
                // we copy it onto `_target` so the column's formatter has a
                // stable field to read.
                rows = rows.map(r => {
                    r._target = r.target_type || '';
                    return r;
                });

                table = new Tabulator('#video-ads-master-table', {
                    data: rows,
                    layout: 'fitData',
                    placeholder: 'No rows yet. Click "Add Row" to start.',
                    pagination: true,
                    paginationSize: 100,
                    paginationSizeSelector: [25, 50, 100, 200, 500],
                    paginationCounter: 'rows',
                    index: 'id',
                    columns: [
                        {
                            title: '#',
                            field: 'id',
                            width: 60,
                            hozAlign: 'center',
                            headerSort: false,
                            formatter: 'rownum',
                        },
                        {
                            title: 'SKU / PARENT / GROUP', field: '_target', width: 200,
                            formatter: targetFormatter,
                            editor: targetTypeEditor,
                            cellEdited: persistCell,
                        },
                        {
                            title: 'NAME', field: 'name', width: 180,
                            formatter: plainFormatter,
                            editor: 'input',
                            cellEdited: persistCell,
                        },
                        {
                            title: 'CHANNEL', field: 'channel', width: 160,
                            formatter: plainFormatter,
                            editor: 'list',
                            editorParams: {
                                values: buildChannelLookup,
                                autocomplete: true,
                                freetext: true,
                                listOnEmpty: true,
                                clearable: true,
                            },
                            cellEdited: persistCell,
                        },
                        {
                            title: 'AUDIENCE', field: 'audience', width: 200,
                            formatter: plainFormatter,
                            editor: 'input',
                            cellEdited: persistCell,
                        },
                        {
                            title: 'HOOK NAME', field: 'hook_name', width: 200,
                            formatter: plainFormatter,
                            editor: 'list',
                            editorParams: {
                                values: buildHookLookup,
                                autocomplete: true,
                                freetext: true,            // accept newly-typed hook names
                                listOnEmpty: true,
                                clearable: true,
                                placeholderEmpty: 'Type a new hook name…',
                            },
                            cellEdited: hookNameEdited,
                        },
                        {
                            title: 'HOOK MESSAGE', field: 'hook', width: 240,
                            formatter: plainFormatter,
                            editor: 'input',
                            cellEdited: persistCell,
                        },
                        {
                            title: 'LINK', field: 'link', width: 90, hozAlign: 'center',
                            formatter: linkFormatter,
                            editor: 'input',
                            cellEdited: persistCell,
                        },
                        {
                            title: '',
                            field: '_actions',
                            width: 150,
                            hozAlign: 'center',
                            headerSort: false,
                            formatter: () => `
                                <button class="btn btn-sm btn-outline-primary me-1 vam-edit-btn"   title="Edit"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-sm btn-outline-success me-1 vam-copy-btn"  title="Duplicate row"><i class="fas fa-copy"></i></button>
                                <button class="btn btn-sm btn-outline-danger vam-delete-btn"      title="Delete"><i class="fas fa-trash"></i></button>
                            `,
                            cellClick: (e, cell) => {
                                if (e.target.closest('.vam-edit-btn'))   { openEditForm(cell.getRow().getData()); return; }
                                if (e.target.closest('.vam-copy-btn'))   { copyRow(cell.getRow()); return; }
                                if (e.target.closest('.vam-delete-btn')) { deleteRow(cell.getRow()); return; }
                            },
                        },
                    ],
                    dataLoaded:   () => updateCount(),
                    dataChanged:  () => updateCount(),   // covers any setData / addData / etc.
                    rowAdded:     () => updateCount(),
                    rowUpdated:   () => updateCount(),   // covers row.update() from persistCell
                    rowDeleted:   () => updateCount(),
                    dataFiltered: () => updateCount(),   // covers search filter changes
                });

                // Belt-and-braces refresh after the table finishes building —
                // some Tabulator versions don't have `active` data available
                // by the time `dataLoaded` fires.
                table.on('tableBuilt', () => updateCount());
                setTimeout(updateCount, 50);
            }

            function updateCount() {
                if (!table) return;
                // Prefer "active" (post-filter) but fall back to the full set
                // when the active view isn't ready yet — e.g. immediately
                // after the table is first built.
                let rows;
                try { rows = table.getData('active'); } catch (e) { rows = null; }
                if (!rows || rows.length === 0) {
                    const all = table.getData();
                    if (all && all.length) rows = all;
                }
                rows = rows || [];

                let sku = 0, parent = 0, group = 0;
                rows.forEach(r => {
                    const t = String(r.target_type || '').toLowerCase();
                    if (t === 'sku')         sku++;
                    else if (t === 'parent') parent++;
                    else if (t === 'group')  group++;
                });
                document.getElementById('vamRowCount').textContent    = rows.length;
                document.getElementById('vamSkuCount').textContent    = sku;
                document.getElementById('vamParentCount').textContent = parent;
                document.getElementById('vamGroupCount').textContent  = group;
            }

            function applySearch() {
                if (!table) return;
                const q = document.getElementById('vamSearch').value.trim().toLowerCase();
                if (!q) { table.clearFilter(); updateCount(); return; }
                table.setFilter((data) => {
                    const haystack = [
                        data._target, data.name, data.channel,
                        data.audience, data.hook_name, data.hook, data.link,
                    ].map(v => (v || '').toString().toLowerCase()).join(' | ');
                    return haystack.includes(q);
                });
                updateCount();
            }

            function deleteRow(row) {
                const data = row.getData();
                if (!confirm('Delete this row?')) return;
                fetch(`/video-ads-master/${data.id}`, {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                })
                .then(r => r.json())
                .then(j => {
                    if (!j.success) { showToast(j.message || 'Failed to delete', 'error'); return; }
                    row.delete();
                    updateCount();
                    showToast('Row deleted', 'success');
                })
                .catch(e => { console.error(e); showToast('Network error', 'error'); });
            }

            // Handle CSV import — uploads the file as multipart/form-data,
            // then refreshes the table (the server creates one row per CSV
            // row). The toast surfaces the created/skipped counts; the first
            // few row errors are printed to the console for debugging.
            function handleImportFile(e) {
                const input = e.target;
                const file = input.files && input.files[0];
                if (!file) return;

                const fd = new FormData();
                fd.append('file', file);

                showToast('Uploading ' + file.name + '…', 'info');

                fetch('/video-ads-master/import', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: fd,
                })
                .then(r => r.json().then(j => ({ ok: r.ok, j })))
                .then(({ ok, j }) => {
                    if (!ok || !j.success) {
                        showToast((j && j.message) || 'Import failed', 'error');
                        return;
                    }
                    const created = j.created || 0;
                    const skipped = j.skipped || 0;
                    let msg = `Imported ${created} row(s)`;
                    if (skipped) msg += `, skipped ${skipped}`;
                    showToast(msg, created > 0 ? 'success' : 'warning');

                    if ((j.errors || []).length) {
                        console.warn('Video Ads Master import — row errors:', j.errors);
                    }

                    // Re-pull the full dataset so the new rows + badge counts
                    // appear immediately.
                    reloadTable();
                })
                .catch(err => { console.error(err); showToast('Network error during import', 'error'); })
                .finally(() => { input.value = ''; }); // allow re-import of same file
            }

            // Reloads rows + lookups from the server. Used after CSV import
            // and could be reused for any other server-side bulk mutation.
            function reloadTable() {
                fetch('/video-ads-master/data', { headers: { 'Accept': 'application/json' } })
                    .then(r => r.json())
                    .then(payload => {
                        if (!payload.success) { showToast('Failed to reload data', 'error'); return; }
                        channelOptions = payload.channels     || [];
                        hookOptions    = payload.hook_options || [];
                        refreshChannelDatalist();
                        refreshHookSelect('');
                        const rows = (payload.rows || []).map(r => {
                            r._target = r.target_type || '';
                            return r;
                        });
                        table.setData(rows);
                        updateCount();
                    })
                    .catch(e => { console.error(e); showToast('Network error reloading data', 'error'); });
            }

            // Duplicate a row via POST /video-ads-master/{id}/copy. Server-side
            // replicate() carries every column, returns the fresh row, and we
            // prepend it to the table.
            function copyRow(row) {
                const data = row.getData();
                fetch(`/video-ads-master/${data.id}/copy`, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                })
                .then(r => r.json().then(j => ({ ok: r.ok, j })))
                .then(({ ok, j }) => {
                    if (!ok || !j.success) {
                        showToast((j && j.message) || 'Failed to copy row', 'error');
                        return;
                    }
                    const newRow = j.row;
                    newRow._target = newRow.target_type || '';
                    table.addRow(newRow, true); // prepend
                    updateCount();
                    showToast('Row duplicated', 'success');
                })
                .catch(e => { console.error(e); showToast('Network error while copying', 'error'); });
            }

            // Save a brand-new HOOK NAME from the "+ Add hook" sub-modal
            // (invoked from the form modal's "+" button). The new option is
            // re-rendered in the form's <select> and pre-selected.
            function saveNewHook() {
                const name = document.getElementById('vamNewHookName').value.trim();
                if (!name) { showToast('Enter a hook name', 'warning'); return; }

                fetch('/video-ads-master/hook-options', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ name }),
                })
                .then(r => r.json())
                .then(j => {
                    if (!j.success) { showToast(j.message || 'Failed to save hook', 'error'); return; }
                    hookOptions = j.options || hookOptions;
                    addHookModal.hide();
                    refreshHookSelect(j.name);
                    showToast('Hook name saved', 'success');
                })
                .catch(e => { console.error(e); showToast('Network error', 'error'); });
            }
        })();
    </script>
@endsection
