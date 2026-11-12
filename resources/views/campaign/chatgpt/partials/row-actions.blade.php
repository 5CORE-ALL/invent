{{-- ChatGPT Ads: per-row Preview button (placed right after SKU). Goes inside `columns: [...]` of partials/ads-tabulator-page. --}}
{
    title: 'Preview',
    field: '_preview',
    hozAlign: 'center',
    headerSort: false,
    width: 80,
    headerTooltip: 'Show how this SKU looks as a ChatGPT sponsored ad.',
    formatter: function(cell) {
        return '<button type="button" class="btn btn-sm btn-outline-primary gpt-preview-btn py-0 px-2" title="Preview as ChatGPT ad"><i class="fas fa-eye"></i> Preview</button>';
    },
    cellClick: function(e, cell) {
        e.stopPropagation();
        if (e.target.closest('.gpt-preview-btn')) adsOpenGptPreview(cell.getRow().getData());
    }
},
