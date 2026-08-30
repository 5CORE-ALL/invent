@extends('layouts.vertical', ['title' => 'Marketplaces - Amz FBM - Targeting'])

@section('css')
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<style>
    .amz-fbm-targeting .tabulator { font-size: 13px; border: 1px solid #dee2e6; border-radius: 0 0 8px 8px; }
    .amz-fbm-targeting .tabulator .tabulator-header {
        background: #dbeafe; border-bottom: 1px solid #93c5fd; font-weight: 600;
        position: sticky; top: var(--tz-topbar-height, 70px); z-index: 24;
    }
    .amz-fbm-targeting .tabulator .tabulator-header .tabulator-col .tabulator-col-title {
        font-size: 12.5px; text-align: center; padding: 6px 4px;
    }
    .amz-fbm-targeting .tabulator-row { min-height: 36px; }
    .amz-fbm-targeting .tabulator-row:hover { background-color: #f8fafc !important; }
    .amz-fbm-targeting .tabulator-cell { padding: 6px 8px !important; }
    .amz-fbm-targeting .aft-count-badge {
        display: inline-block; padding: 2px 10px; border-radius: 999px;
        background: #1d4ed8; border: 1px solid #1e40af; color: #fff;
        font-size: 12px; font-weight: 700; cursor: pointer;
    }
    .amz-fbm-targeting .aft-count-badge:hover { background: #1e40af; }
    .aft-empty { color: #94a3b8; font-size: 13px; }
    #aft-modal-targets.aft-modal-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
        max-height: 420px;
        overflow-y: auto;
        padding-right: 4px;
    }
    #aft-modal-targets .aft-chip {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        width: 100%;
        padding: 8px 12px;
        border-radius: 8px;
        background: #eef2ff;
        border: 1px solid #c7d2fe;
        color: #1e293b;
        font-size: 13px;
        line-height: 1.35;
    }
    #aft-modal-targets .aft-chip-text { flex: 1; min-width: 0; word-break: break-word; }
    #aft-modal-targets .aft-chip-match {
        flex-shrink: 0;
        padding: 2px 8px;
        border-radius: 999px;
        background: #1d4ed8;
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.02em;
    }
</style>
@endsection

@section('content')
<div class="container-fluid mt-4 amz-fbm-targeting">
    @include('layouts.shared/page-title', ['sub_title' => 'Marketplaces', 'page_title' => 'Amz FBM - Targeting'])
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h4 class="mb-0"><i class="mdi mdi-bullseye-arrow"></i> Amz FBM Targeting</h4>
                    <span class="small">Campaign names from the same SP reports table as Ads All. Targets from Amazon Ads (keywords + product targets).</span>
                </div>
                <div class="card-body">
                    <div class="mb-3 d-flex gap-2 flex-wrap align-items-center">
                        <input type="text" id="aft-search" class="form-control form-control-sm"
                               style="max-width: 360px;" placeholder="Search campaign name...">
                        <span class="text-muted small" id="aft-count"></span>
                    </div>
                    <div id="aft-table"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="aftTargetsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-bullseye"></i> <span id="aft-modal-title">Targets</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2" id="aft-modal-count"></p>
                <div id="aft-modal-targets" class="aft-modal-list"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
(function () {
    const dataUrl = @json(route('amazon.ads.fbm-targeting.data'));
    const searchEl = document.getElementById('aft-search');
    const countEl = document.getElementById('aft-count');
    let searchTimer = null;

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, function (c) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]);
        });
    }

    const table = new Tabulator('#aft-table', {
        layout: 'fitColumns',
        placeholder: 'Loading campaigns…',
        pagination: true,
        paginationMode: 'remote',
        paginationSize: 50,
        paginationSizeSelector: [25, 50, 100],
        ajaxURL: dataUrl,
        ajaxConfig: 'GET',
        filterMode: 'remote',
        ajaxRequestFunc: function (url, _config, params) {
            const q = new URLSearchParams();
            q.set('page', String(params.page || 1));
            q.set('size', String(params.size || 50));
            const search = (searchEl && searchEl.value || '').trim();
            if (search) q.set('campaign', search);
            return fetch(url + '?' + q.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (res) {
                if (!res.ok) throw new Error('Network response was not ok');
                return res.json();
            });
        },
        ajaxResponse: function (_url, _params, response) {
            const total = Number(response.total || 0);
            if (countEl) countEl.textContent = total ? (total.toLocaleString() + ' campaigns') : '';
            return {
                data: response.data || [],
                last_page: response.last_page || 1,
            };
        },
        columns: [
            {
                title: 'Campaign name',
                field: 'campaign_name',
                minWidth: 280,
                widthGrow: 2,
                headerSort: false,
                formatter: function (cell) {
                    const name = escapeHtml(cell.getValue() || '');
                    const id = escapeHtml((cell.getRow().getData() || {}).campaign_id || '');
                    return '<div><strong>' + name + '</strong>' +
                        (id ? '<div class="text-muted" style="font-size:11px;">' + id + '</div>' : '') +
                        '</div>';
                },
            },
            {
                title: 'Targets',
                field: 'target_count',
                hozAlign: 'center',
                minWidth: 100,
                width: 120,
                headerSort: false,
                formatter: function (cell) {
                    const n = Number(cell.getValue() || 0);
                    return '<span class="aft-count-badge" title="View targets">' + n.toLocaleString() + '</span>';
                },
                cellClick: function (_e, cell) {
                    openTargetsModal(cell.getRow().getData() || {});
                },
            },
        ],
    });

    function openTargetsModal(row) {
        const name = row.campaign_name || 'Campaign';
        const list = Array.isArray(row.targets) ? row.targets : [];
        const titleEl = document.getElementById('aft-modal-title');
        const countLabel = document.getElementById('aft-modal-count');
        const listEl = document.getElementById('aft-modal-targets');
        if (titleEl) titleEl.textContent = name;
        if (countLabel) countLabel.textContent = list.length.toLocaleString() + ' target' + (list.length === 1 ? '' : 's');
        if (listEl) {
            if (list.length === 0) {
                listEl.innerHTML = '<span class="aft-empty">No targets yet</span>';
            } else {
                listEl.innerHTML = list.map(function (t) {
                    const raw = String(t || '').trim();
                    const m = raw.match(/^(.*)\s+\(([A-Za-z][A-Za-z0-9_/ -]{1,20})\)$/);
                    const text = m ? m[1].trim() : raw;
                    const match = m ? m[2].trim() : '';
                    return '<div class="aft-chip"><span class="aft-chip-text">' + escapeHtml(text) + '</span>' +
                        (match ? '<span class="aft-chip-match">' + escapeHtml(match) + '</span>' : '') +
                        '</div>';
                }).join('');
            }
        }
        const modalEl = document.getElementById('aftTargetsModal');
        if (modalEl && window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } else if (window.jQuery) {
            window.jQuery(modalEl).modal('show');
        }
    }

    if (searchEl) {
        searchEl.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () { table.setPage(1); }, 300);
        });
    }
})();
</script>
@endsection
