{
    title: "Buss Discount",
    field: "buss_discount",
    hozAlign: "center",
    headerSort: true,
    width: 78,
    headerTooltip: "Dynamic Buss Discount from Std Prc ranges in Std prc vs dil. Change the ranges there and this column updates. Std Prc under $15 is 0.5×.",
    sorter: function(a, b, aRow, bRow) {
        const read = window.analyticsBussDiscountPct;
        const av = (typeof read === 'function') ? (Number(read(aRow.getData() || {})) || 0) : 0;
        const bv = (typeof read === 'function') ? (Number(read(bRow.getData() || {})) || 0) : 0;
        return av - bv;
    },
    formatter: function(cell) {
        const row = cell.getRow().getData() || {};
        if (row.is_parent_summary || row.is_parent) return '';
        const read = window.analyticsBussDiscountPct;
        if (typeof read !== 'function') return '<span style="color:#adb5bd;font-weight:600;">—</span>';
        const pct = read(row);
        if (pct == null) return '';
        const n = Number(pct) || 0;
        if (!n) return '<span style="color:#adb5bd;font-weight:600;">—</span>';
        const std = Number(row.STANDARD_PRICE || row.standard_price || row.std_price) || 0;
        const tip = (std > 0 ? ('Std Prc $' + std.toFixed(2) + ' → ') : '') + n + '%';
        return '<span style="font-weight:700;color:#0f172a;" title="' + tip.replace(/"/g, '&quot;') + '">' + n + '</span>';
    }
},
