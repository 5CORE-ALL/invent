{{-- Shared Add/Edit PO Number + O link for arrived_containers pages --}}
<div class="modal fade" id="arrivedPoOlinkEditModal" tabindex="-1" aria-labelledby="arrivedPoOlinkEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="arrivedPoOlinkEditModalLabel">Edit PO Number / O link</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="arrived-po-olink-row-id" value="">
                <div class="mb-2">
                    <label class="form-label fw-semibold mb-1">SKU</label>
                    <input type="text" id="arrived-po-olink-sku" class="form-control form-control-sm" readonly>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-semibold mb-1">PO Number <span class="text-muted fw-normal">(from Purchase Orders)</span></label>
                    <select id="arrived-po-olink-select" class="form-select form-select-sm">
                        <option value="">— Select PO —</option>
                    </select>
                    <div class="form-text">
                        Source: <a href="{{ route('list-all-purchase-orders') }}" target="_blank" rel="noopener">list-all-purchase-orders</a>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-semibold mb-1">Or type PO Number</label>
                    <input type="text" id="arrived-po-olink-number" class="form-control form-control-sm" placeholder="PO number" autocomplete="off">
                </div>
                <div class="mb-0">
                    <label class="form-label fw-semibold mb-1">O link</label>
                    <input type="text" id="arrived-po-olink-link" class="form-control form-control-sm" placeholder="https://... or /path" autocomplete="off">
                </div>
                <div id="arrived-po-olink-save-msg" class="small mt-2" style="display:none;"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary" id="arrived-po-olink-save-btn">
                    <i class="fas fa-save me-1"></i>Save
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const ARRIVED_PO_OLINK_SAVE_URL = @json(route('pricing.container.save-po'));
    const ARRIVED_ALL_PO_OPTIONS = @json($allPoOptions ?? []);
    let arrivedPoOlinkRowRef = null;

    function arrivedPoOlinkEsc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }

    function fillArrivedPoOlinkSelect(options, selectedPo) {
        const sel = document.getElementById('arrived-po-olink-select');
        if (!sel) return;
        const selected = String(selectedPo || '').trim();
        let html = '<option value="">— Select PO —</option>';
        (options || []).forEach(function(opt) {
            const po = String(opt.po_number || '').trim();
            if (!po) return;
            const isSel = po === selected ? ' selected' : '';
            html += `<option value="${arrivedPoOlinkEsc(po)}" data-link="${arrivedPoOlinkEsc(opt.link || '')}"${isSel}>${arrivedPoOlinkEsc(po)}</option>`;
        });
        sel.innerHTML = html;
    }

    window.openArrivedPoOlinkEdit = function(row) {
        arrivedPoOlinkRowRef = row;
        const d = (row && typeof row.getData === 'function') ? (row.getData() || {}) : (row || {});
        document.getElementById('arrived-po-olink-row-id').value = d.id || '';
        document.getElementById('arrived-po-olink-sku').value = d.our_sku || '';
        document.getElementById('arrived-po-olink-number').value = d.po_number || '';
        document.getElementById('arrived-po-olink-link').value = d.order_link || '';
        const msg = document.getElementById('arrived-po-olink-save-msg');
        msg.style.display = 'none';
        msg.textContent = '';
        const skuOptions = Array.isArray(d.po_options) && d.po_options.length ? d.po_options : ARRIVED_ALL_PO_OPTIONS;
        fillArrivedPoOlinkSelect(skuOptions, d.po_number || '');
        const titleEl = document.getElementById('arrivedPoOlinkEditModalLabel');
        if (titleEl) {
            const hasVal = String(d.po_number || '').trim() || String(d.order_link || '').trim();
            titleEl.textContent = hasVal ? 'Edit PO Number / O link' : 'Add PO Number / O link';
        }
        bootstrap.Modal.getOrCreateInstance(document.getElementById('arrivedPoOlinkEditModal')).show();
    };

    window.arrivedPoOlinkActionsColumn = function(opts) {
        opts = opts || {};
        return {
            title: "Actions",
            field: "po_olink_actions",
            headerSort: false,
            hozAlign: "center",
            width: opts.width || 70,
            headerTooltip: "Add / Edit PO Number and O link",
            formatter: function() {
                return `<button type="button" class="btn btn-sm btn-outline-primary arrived-po-olink-edit-btn" title="Add / Edit PO Number / O link">
                    <i class="fas fa-pen"></i>
                </button>`;
            },
            cellClick: function(e, cell) {
                if (!e.target.closest('.arrived-po-olink-edit-btn')) return;
                e.preventDefault();
                e.stopPropagation();
                window.openArrivedPoOlinkEdit(cell.getRow());
            }
        };
    };

    document.getElementById('arrived-po-olink-select')?.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        const po = String(this.value || '').trim();
        const link = opt ? String(opt.getAttribute('data-link') || '').trim() : '';
        if (po) {
            document.getElementById('arrived-po-olink-number').value = po;
        }
        if (link) {
            document.getElementById('arrived-po-olink-link').value = link;
        }
    });

    document.getElementById('arrived-po-olink-save-btn')?.addEventListener('click', async function() {
        const id = parseInt(document.getElementById('arrived-po-olink-row-id').value || '0', 10);
        const poNumber = String(document.getElementById('arrived-po-olink-number').value || '').trim();
        const orderLink = String(document.getElementById('arrived-po-olink-link').value || '').trim();
        const msg = document.getElementById('arrived-po-olink-save-msg');
        const btn = this;
        if (!id) {
            msg.style.display = 'block';
            msg.className = 'small mt-2 text-danger';
            msg.textContent = 'Missing row id.';
            return;
        }
        btn.disabled = true;
        msg.style.display = 'none';
        try {
            const res = await fetch(ARRIVED_PO_OLINK_SAVE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    id: id,
                    po_number: poNumber,
                    order_link: orderLink
                })
            });
            const json = await res.json();
            if (!res.ok || !json.success) {
                throw new Error(json.message || 'Save failed.');
            }
            const updatePayload = {
                po_number: json.po_number || '',
                order_link: json.order_link || '',
            };
            // Pricing-specific fields when present in response
            ['cp', 'cp_new', 'cp_diff_pct', 'po_price', 'po_currency', 'rmb_to_usd',
             'cp_approved', 'cp_approved_reason', 'cp_approved_auto'].forEach(function(k) {
                if (json[k] !== undefined) updatePayload[k] = json[k];
            });
            if (arrivedPoOlinkRowRef && typeof arrivedPoOlinkRowRef.update === 'function') {
                arrivedPoOlinkRowRef.update(updatePayload);
            }
            if (typeof window.arrivedPoOlinkOnSaved === 'function') {
                window.arrivedPoOlinkOnSaved(json, arrivedPoOlinkRowRef);
            }
            msg.style.display = 'block';
            msg.className = 'small mt-2 text-success';
            msg.textContent = json.message || 'Saved.';
            setTimeout(function() {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('arrivedPoOlinkEditModal')).hide();
            }, 400);
        } catch (err) {
            msg.style.display = 'block';
            msg.className = 'small mt-2 text-danger';
            msg.textContent = err.message || 'Save failed.';
        } finally {
            btn.disabled = false;
        }
    });
})();
</script>
