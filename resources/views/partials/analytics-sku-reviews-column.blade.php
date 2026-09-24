{
    title: "Reviews",
    field: "sku_review_count",
    hozAlign: "center",
    headerSort: true,
    width: 72,
    headerTooltip: "Review count from /reviews for this SKU on this marketplace. Click to open those reviews.",
    sorter: function(a, b, aRow, bRow) {
        const mp = @json($marketplace);
        const map = (window.__skuReviewCounts && window.__skuReviewCounts[mp]) || {};
        const ka = window.analyticsReviewSkuKey ? window.analyticsReviewSkuKey(aRow.getData()) : '';
        const kb = window.analyticsReviewSkuKey ? window.analyticsReviewSkuKey(bRow.getData()) : '';
        return (parseInt(map[ka], 10) || 0) - (parseInt(map[kb], 10) || 0);
    },
    formatter: function(cell) {
        const mp = @json($marketplace);
        if (!window.analyticsReviewSkuKey) {
            window.analyticsReviewSkuKey = function(row) {
                row = row || {};
                if (row.is_parent_summary || row.is_parent) return '';
                const sku = String(row.sku || row.SKU || row['(Child) sku'] || row.seller_part_number || '').trim();
                if (!sku || /^parent/i.test(sku)) return '';
                return sku.toLowerCase();
            };
        }
        if (!window.__skuReviewCounts) window.__skuReviewCounts = {};
        if (!window.__skuReviewLoading) window.__skuReviewLoading = {};
        if (!Object.prototype.hasOwnProperty.call(window.__skuReviewCounts, mp) && !window.__skuReviewLoading[mp]) {
            window.__skuReviewLoading[mp] = true;
            const table = cell.getTable();
            fetch('/reviews/counts?marketplace=' + encodeURIComponent(mp), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            }).then(function(r) { return r.json(); }).then(function(data) {
                window.__skuReviewCounts[mp] = (data && data.counts) || {};
                window.__skuReviewLoading[mp] = false;
                try { table.redraw(true); } catch (e) {}
            }).catch(function() {
                window.__skuReviewCounts[mp] = {};
                window.__skuReviewLoading[mp] = false;
            });
        }
        const row = cell.getRow().getData() || {};
        const key = window.analyticsReviewSkuKey(row);
        if (!key) return '';
        const map = window.__skuReviewCounts[mp];
        if (!map) return '<span class="text-muted">…</span>';
        const n = parseInt(map[key], 10) || 0;
        const sku = String(row.sku || row.SKU || row['(Child) sku'] || row.seller_part_number || '').trim();
        const href = '/reviews?sku=' + encodeURIComponent(sku) + '&marketplace=' + encodeURIComponent(mp);
        const color = n > 0 ? '#0d6efd' : '#6c757d';
        return '<a href="' + href + '" target="_blank" rel="noopener" style="color:' + color + ';font-weight:600;text-decoration:none;" title="Open /reviews" onclick="event.stopPropagation();">' + n.toLocaleString() + '</a>';
    }
},
