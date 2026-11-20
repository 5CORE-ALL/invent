{{-- PO Stage dropdown: shared by /list-all-purchase-orders and /ledger-master/advance-and-payments.
     The stage is stored once on purchase_orders.po_stage (see config/po_stages.php). --}}
<style>
    .po-stage-select {
        min-width: 150px;
        padding: 2px 22px 2px 8px;
        font-size: 0.75rem;
        font-weight: 600;
        border-radius: 14px;
        border: 1px solid #ced4da;
        background-color: #f8f9fa;
        color: #6c757d;
        cursor: pointer;
    }
    .po-stage-select:disabled { opacity: .6; cursor: wait; }
    .po-stage-select option { background: #fff; color: #212529; font-weight: 500; }
    @foreach(config('po_stages.stages', []) as $key => $meta)
    .po-stage-select[data-stage="{{ $key }}"] { background-color: {{ $meta['bg'] }}; color: {{ $meta['text'] }}; border-color: {{ $meta['bg'] }}; }
    @endforeach
</style>
<script>
    window.PO_STAGES = @json(config('po_stages.stages', []));

    // <select> for one PO; value '' = no stage set.
    window.poStageSelectHtml = function (poId, value) {
        const v = String(value || '');
        let html = '<select class="po-stage-select" data-po-id="' + poId + '" data-stage="' + v + '" data-prev="' + v + '" title="PO stage">';
        html += '<option value=""' + (v === '' ? ' selected' : '') + '>Select stage…</option>';
        Object.keys(window.PO_STAGES).forEach(function (k) {
            html += '<option value="' + k + '"' + (v === k ? ' selected' : '') + '>' + window.PO_STAGES[k].label + '</option>';
        });
        return html + '</select>';
    };

    document.addEventListener('change', function (e) {
        const sel = e.target;
        if (!sel.classList || !sel.classList.contains('po-stage-select')) return;

        const poId = sel.getAttribute('data-po-id');
        const stage = sel.value;
        const prev = sel.getAttribute('data-prev') || '';
        sel.disabled = true;

        fetch('/purchase-orders/' + poId + '/stage', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ stage: stage })
        })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
        .then(function (res) {
            if (!res.ok || !res.j.success) throw new Error('save failed');
            sel.setAttribute('data-stage', stage);
            sel.setAttribute('data-prev', stage);
            // Let the page update its in-memory rows (and re-apply a PO Stage filter if one is active).
            document.dispatchEvent(new CustomEvent('po-stage-changed', { detail: { poId: poId, stage: stage } }));
        })
        .catch(function () {
            sel.value = prev;
            alert('Could not save the PO stage.');
        })
        .finally(function () { sel.disabled = false; });
    });
</script>
