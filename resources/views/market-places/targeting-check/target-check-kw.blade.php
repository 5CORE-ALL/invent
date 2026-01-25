@extends('layouts.vertical', ['title' => $title ?? 'Amz FBM Targeting Check - TARGET KW', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        /* Card & title - matching amazon-utilized-kw */
        .tc-card { border: none; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,.08); overflow: hidden; }
        .tc-card .card-body { padding: 1.25rem 1.5rem; background: #fafbfc; }
        .tc-title { font-size: 1.1rem; font-weight: 600; color: #1e293b; letter-spacing: 0.3px; }
        .tc-title i { color: #0d9488; }

        /* Table wrapper - like amazon-utilized-kw #budget-under-table .tabulator */
        #target-check-table.tabulator,
        #target-check-table .tabulator {
            /* border-radius: 18px; */
            box-shadow: 0 6px 24px rgba(37, 99, 235, 0.13);
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }

        /* Header - #D8F3F3 like amazon-utilized-kw */
        .tabulator .tabulator-header {
            background: linear-gradient(90deg, #D8F3F3 0%, #D8F3F3 100%);
            border-bottom: 1px solid #403f3f;
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.10);
        }
        .tabulator .tabulator-header .tabulator-col {
            text-align: center;
            background: #D8F3F3;
            border-right: 1px solid #262626;
            padding: 10px 8px;
            font-weight: 700;
            color: #1e293b;
            font-size: 0.9rem;
            letter-spacing: 0.02em;
        }
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content {
            white-space: normal;
            word-wrap: break-word;
            line-height: 1.25;
            text-align: center;
        }
        .tabulator .tabulator-header .tabulator-col .tabulator-col-sorter,
        .tabulator .tabulator-header .tabulator-col .tabulator-arrow,
        .tabulator .tabulator-header .tabulator-col .tabulator-col-sorter-element { display: none !important; }
        .tabulator .tabulator-header .tabulator-col:hover { background: #D8F3F3; color: #2563eb; }

        /* Rows - like amazon-utilized-kw */
        .tabulator-row { background-color: #fff !important; transition: background 0.18s; }
        .tabulator-row:nth-child(even) { background-color: #f8fafc !important; }
        .tabulator-row:hover { background-color: #dbeafe !important; }

        /* Cells - like amazon-utilized-kw */
        .tabulator .tabulator-cell {
            text-align: center;
            padding: 14px 10px;
            border-right: 1px solid #262626;
            border-bottom: 1px solid #262626;
            font-size: 1rem;
            color: #22223b;
            vertical-align: middle;
            transition: background 0.18s, color 0.18s;
        }
        .tabulator .tabulator-cell:focus { outline: 1px solid #262626; background: #e0eaff; }
        .tabulator .tabulator-row .tabulator-cell:last-child,
        .tabulator .tabulator-header .tabulator-col:last-child { border-right: none; }

        /* Targeting-check specific */
        .sku-cell { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 100%; }
        .thumb-img { max-width: 52px; max-height: 52px; object-fit: contain; border-radius: 6px; border: 1px solid #e2e8f0; }
        .dil-percent-value { font-weight: 600; }
        .dil-percent-value.red { color: #dc2626; background: none !important; padding: 0 !important; border-radius: 0 !important; }
        .dil-percent-value.yellow { color: #d97706; background: none !important; padding: 0 !important; border-radius: 0 !important; }
        .dil-percent-value.green { color: #059669; background: none !important; padding: 0 !important; border-radius: 0 !important; }
        .dil-percent-value.pink { color: #fff !important; background: #db2777 !important; padding: 2px 8px !important; border-radius: 6px !important; }
        .tc-check { width: 18px; height: 18px; cursor: pointer; accent-color: #0d9488; }
        .tc-issue, .tc-remark { border-radius: 8px; border: 1px solid #e2e8f0; font-size: 0.8rem; }
        .tc-issue:focus, .tc-remark:focus { border-color: #14b8a6; box-shadow: 0 0 0 3px rgba(20,184,166,.15); outline: 0; }
        .tc-save { font-size: 0.9rem; font-weight: 600; padding: 6px 10px; border-radius: 8px; color: #fff !important; background: #0d9488 !important; background-color: #0d9488 !important; border: none; }
        .tc-save i { color: #fff !important; }
        .tc-save:hover { color: #fff; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(13,148,136,.35); }
        .campaign-cell { font-size: 0.8rem; color: #475569; }
        .campaign-text { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 100%; }
        .history-block { text-align: center; padding: 6px 0; }
        .history-block .history-date { font-size: 0.8rem; font-weight: 600; color: #1e293b; }
        .history-block .history-time { font-size: 0.75rem; color: #64748b; }
        .history-block .history-sep { height: 1px; background: #e2e8f0; margin: 5px 8px; }
        .history-block .history-row { display: flex; align-items: center; justify-content: center; gap: 6px; flex-wrap: wrap; }
        .history-block .view-history { cursor: pointer; color: #14b8a6; font-size: 1.05rem; }
        .history-block .view-history:hover { color: #0d9488; }
        #historyModal .modal-content { border: none; border-radius: 12px; box-shadow: 0 25px 50px -12px rgba(0,0,0,.25); }
        #historyModal .modal-header { background: linear-gradient(135deg, #f0fdfa 0%, #ccfbf1 100%); border-bottom: 1px solid #99f6e4; padding: 1rem 1.25rem; }
        #historyModal .modal-title { font-weight: 600; color: #0f766e; }
        #historyModal .table th { background: #f8fafc; font-weight: 600; color: #475569; font-size: 0.8rem; text-transform: uppercase; }
        #historyModal .table td { font-size: 0.875rem; vertical-align: middle; }
        .tabulator-placeholder { color: #94a3b8; font-size: 0.95rem; padding: 2rem !important; }
    </style>
@endsection
@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('layouts.shared.page-title', ['page_title' => $title ?? 'TARGET KW', 'sub_title' => 'Amz FBM Targeting Check - TARGET KW'])
    <div class="row">
        <div class="col-12">
            <div class="card tc-card">
                <div class="card-body">
                    <h4 class="tc-title mb-4"><i class="fa-solid fa-bullseye me-2"></i>TARGET KW</h4>
                    <div id="target-check-table"></div>
                </div>
            </div>
        </div>
    </div>
    <!-- History Modal -->
    <div class="modal fade" id="historyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">History - <span id="historySku"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-sm">
                        <thead><tr><th>Campaign</th><th>Issue</th><th>Remark</th><th>User</th><th>Date</th></tr></thead>
                        <tbody id="historyTbody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        (function(){
            const type = 'kw';
            const dataUrl = '{{ route("amazon.fbm.targeting.check.kw.data") }}';
            const saveUrl = '{{ route("amazon.fbm.targeting.check.save") }}';
            const historyUrl = '{{ route("amazon.fbm.targeting.check.history") }}';
            const getDilColor = (p) => {
                if (p < 16.66) return 'red';
                if (p < 25) return 'yellow';
                if (p < 50) return 'green';
                return 'pink';
            };
            var table = new Tabulator("#target-check-table", {
                ajaxURL: dataUrl,
                layout: "fitData",
                resizableColumns: true,
                height: "600px",
                placeholder: "No matching KW campaigns found. Only SKUs with an exact campaign name match (no PT) are shown.",
                ajaxResponse: function(_u, _p, res) {
                    try { return (res && Array.isArray(res.data)) ? res.data : []; } catch(e) { return []; }
                },
                ajaxRequestError: function() { console.error("Targeting check: data request failed"); },
                columns: [
                { title: "Img", field: "image_path", width: 70, minWidth: 70, formatter: function(c) {
                    const v = c.getValue();
                    if (!v) return '-';
                    return '<img class="thumb-img" src="'+v+'" alt="">';
                }},
                { title: "SKU", field: "sku", width: 220, minWidth: 180, formatter: function(c) {
                    const v = String(c.getValue() || '');
                    const esc = (s)=>String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;');
                    return '<span class="sku-cell" title="'+esc(v)+'">'+esc(v)+'</span>';
                }},
                { title: "INV", field: "INV", width: 60, minWidth: 52 },
                { title: "L30", field: "L30", width: 58, minWidth: 50 },
                { title: "DIL", field: "dil", width: 80, minWidth: 70, formatter: function(c) {
                    const p = parseFloat(c.getValue()) || 0;
                    return '<span class="dil-percent-value '+getDilColor(p)+'">'+p+'%</span>';
                }},
                { title: "CAMPAIGN", field: "campaign", width: 220, minWidth: 180, cssClass: "campaign-cell", formatter: function(c) {
                    const v = String(c.getValue() || '');
                    const esc = (s)=>String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;');
                    return '<span class="campaign-text" title="'+esc(v)+'">'+esc(v)+'</span>';
                }},
                { title: "CHECKED", field: "checked", width: 90, minWidth: 82, hozAlign: "center", formatter: function(c) {
                    const d = c.getRow().getData();
                    const v = d.checked ? 'checked' : '';
                    return '<input type="checkbox" class="tc-check" data-sku="'+d.sku+'" '+v+'>';
                }},
                { title: "ISSUE", field: "issue", width: 140, minWidth: 90, formatter: function(c) {
                    const d = c.getRow().getData();
                    return '<input type="text" class="form-control form-control-sm tc-issue" data-sku="'+d.sku+'" value="'+(d.issue||'')+'" placeholder="Issue">';
                }},
                { title: "REMARK", field: "remark", width: 140, minWidth: 90, formatter: function(c) {
                    const d = c.getRow().getData();
                    return '<input type="text" class="form-control form-control-sm tc-remark" data-sku="'+d.sku+'" value="'+(d.remark||'')+'" placeholder="Remark">';
                }},
                { title: "USER", field: "user", width: 110, minWidth: 70 },
                { title: "HISTORY", field: "last_history_at", width: 130, minWidth: 100, formatter: function(c) {
                    const d = c.getRow().getData();
                    const raw = d.last_history_at || '';
                    let dateStr = '', timeStr = '';
                    if (raw) {
                        try {
                            const dt = new Date(raw.replace(' ','T'));
                            if (!isNaN(dt.getTime())) {
                                dateStr = dt.toLocaleDateString('en-GB', { day:'numeric', month:'short', year:'numeric' });
                                timeStr = dt.toLocaleTimeString('en-US', { hour:'numeric', minute:'2-digit', hour12:true });
                            }
                        } catch(e) {}
                    }
                    const infoIcon = '<i class="fa fa-info-circle view-history" data-sku="'+d.sku+'" title="View full history"></i>';
                    const content = dateStr
                        ? '<div class="history-row"><span class="history-date">'+dateStr+'</span>'+infoIcon+'</div><div class="history-time">'+timeStr+'</div><div class="history-sep"></div>'
                        : '<div class="history-row"><span class="history-date text-muted">—</span>'+infoIcon+'</div><div class="history-sep"></div>';
                    return '<div class="history-block">'+content+'</div>';
                }},
                { title: "Save", width: 60, minWidth: 60, hozAlign: "center", formatter: function(c) {
                    const d = c.getRow().getData();
                    return '<button type="button" class="btn btn-sm tc-save" data-sku="'+d.sku+'"><i class="fa fa-save"></i></button>';
                }},
            ],
        });
        function doSave(sku, checked, campaign, issue, remark) {
            const fd = new FormData();
            fd.append('_token', document.querySelector('meta[name="csrf-token"]').content);
            fd.append('sku', sku);
            fd.append('type', type);
            fd.append('checked', checked ? '1' : '0');
            fd.append('campaign', campaign || '');
            fd.append('issue', issue || '');
            fd.append('remark', remark || '');
            fetch(saveUrl, { method: 'POST', body: fd })
                .then(r=>r.json())
                .then(data=>{
                    if (data.status===200) {
                        const r = table.getRows().find(row=>row.getData().sku===sku);
                        if (r) {
                            r.getCell('user').setValue(data.user||'');
                            if (data.last_history_at) r.getCell('last_history_at').setValue(data.last_history_at);
                        }
                    }
                })
                .catch(e=>console.error(e));
        }
        document.getElementById('target-check-table').addEventListener('click', function(e) {
            const t = e.target;
            if (t.classList.contains('tc-save')) {
                const sku = t.getAttribute('data-sku');
                const tabRow = table.getRows().find(function(r){ return r.getData().sku === sku; });
                let campaign = '';
                if (tabRow) {
                    const d = tabRow.getData();
                    campaign = (d.campaign != null && d.campaign !== '') ? String(d.campaign) : '';
                    if (!campaign) { try { var cv = tabRow.getCell('campaign').getValue(); campaign = (cv != null && cv !== '') ? String(cv) : ''; } catch(e) {} }
                }
                const row = t.closest('.tabulator-row');
                const issue = row ? row.querySelector('.tc-issue') : null;
                const remark = row ? row.querySelector('.tc-remark') : null;
                const chk = row ? row.querySelector('.tc-check') : null;
                doSave(sku, chk ? chk.checked : false, campaign, issue ? issue.value : '', remark ? remark.value : '');
            } else if (e.target.closest && e.target.closest('.view-history')) {
                const el = e.target.closest('.view-history');
                const sku = el ? el.getAttribute('data-sku') : null;
                if (!sku) return;
                document.getElementById('historySku').textContent = sku;
                document.getElementById('historyTbody').innerHTML = '<tr><td colspan="5">Loading...</td></tr>';
                var mod = new bootstrap.Modal(document.getElementById('historyModal'));
                mod.show();
                fetch(historyUrl+'?sku='+encodeURIComponent(sku)+'&type='+type)
                    .then(r=>r.json())
                    .then(data=>{
                        function esc(v){ return (v==null||v===undefined?'':String(v)).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
                        const rows = (data.data||[]).map(h=>
                            '<tr><td>'+esc(h.campaign)+'</td><td>'+esc(h.issue)+'</td><td>'+esc(h.remark)+'</td><td>'+esc(h.user)+'</td><td>'+esc(h.created_at)+'</td></tr>'
                        ).join('');
                        document.getElementById('historyTbody').innerHTML = rows || '<tr><td colspan="5">No history</td></tr>';
                    })
                    .catch(()=>{ document.getElementById('historyTbody').innerHTML = '<tr><td colspan="5">Error</td></tr>'; });
            }
        });
        })();
    });
    </script>
@endsection
