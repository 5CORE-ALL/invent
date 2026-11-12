{{--
    ChatGPT Ads: row "Preview" modal. Runs inside partials/ads-tabulator-page's script.

    This is a LOCAL mock of a ChatGPT "chat_card" sponsored ad, built from the SKU (title <= 50, body <= 100 chars, price, image).
    OpenAI's real preview (POST /v1/ads/{ad_id}/preview) needs an ad that already exists in the ad account.
--}}
const GPT_TITLE_MAX = 50;
const GPT_BODY_MAX = 100;
let gptAdvertiserName = 'Advertiser';
let gptPreviewModal = null;

function gptClip(text, max) {
    const t = String(text || '').trim();
    return t.length > max ? t.slice(0, max - 1).trimEnd() + '…' : t;
}
function gptCount(text, max) {
    const n = String(text || '').trim().length;
    return '<span style="color:' + (n > max ? '#dc3545' : '#6c757d') + ';">' + n + '/' + max + (n > max ? ' (will be shortened)' : '') + '</span>';
}

function gptEnsurePreviewModal() {
    if (document.getElementById('gptPreviewModal')) return;
    const wrap = document.createElement('div');
    wrap.innerHTML = ''
        + '<div class="modal fade" id="gptPreviewModal" tabindex="-1" aria-hidden="true">'
        + '  <div class="modal-dialog modal-dialog-centered" style="max-width:460px;">'
        + '    <div class="modal-content">'
        + '      <div class="modal-header py-2">'
        + '        <h6 class="modal-title" id="gptPreviewTitle">ChatGPT ad preview</h6>'
        + '        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>'
        + '      </div>'
        + '      <div class="modal-body" id="gptPreviewBody" style="background:#f7f7f8;"></div>'
        + '      <div class="modal-footer py-2 justify-content-start">'
        + '        <small class="text-muted">Mock preview built from the SKU. A real ChatGPT preview needs an ad created in the ad account.</small>'
        + '      </div>'
        + '    </div>'
        + '  </div>'
        + '</div>';
    document.body.appendChild(wrap.firstChild);
}

function adsOpenGptPreview(row) {
    gptEnsurePreviewModal();
    const title = row.ad_title || row.sku || '';
    const body = row.ad_body || '';
    const price = (row.price !== null && row.price !== undefined && row.price !== '') ? '$' + adsNum(row.price).toFixed(2) : '';
    const img = row.image_path
        ? '<img src="' + adsEsc(row.image_path) + '" alt="" style="width:100%;max-height:220px;object-fit:contain;background:#fff;border-radius:8px;">'
        : '<div style="height:140px;display:flex;align-items:center;justify-content:center;background:#e9ecef;border-radius:8px;color:#6c757d;">No image</div>';

    document.getElementById('gptPreviewTitle').textContent = 'ChatGPT ad preview — ' + (row.sku || '');
    document.getElementById('gptPreviewBody').innerHTML = ''
        // the user's question
        + '<div style="display:flex;justify-content:flex-end;margin-bottom:10px;">'
        + '  <div style="background:#e9ecef;border-radius:18px;padding:8px 14px;max-width:80%;font-size:14px;">' + adsEsc('Where can I buy ' + gptClip(title, 40) + '?') + '</div>'
        + '</div>'
        // the sponsored card
        + '<div style="font-size:11px;color:#6c757d;margin:0 0 4px 4px;">Sponsored</div>'
        + '<div style="background:#fff;border:1px solid #dee2e6;border-radius:14px;padding:12px;box-shadow:0 1px 3px rgba(0,0,0,.06);">'
        +      img
        + '  <div style="font-size:12px;color:#6c757d;margin-top:10px;">' + adsEsc(gptAdvertiserName) + '</div>'
        + '  <div style="font-weight:700;font-size:15px;line-height:1.25;margin-top:2px;">' + adsEsc(gptClip(title, GPT_TITLE_MAX)) + '</div>'
        + (body ? '  <div style="font-size:13px;color:#495057;margin-top:4px;">' + adsEsc(gptClip(body, GPT_BODY_MAX)) + '</div>' : '')
        + '  <div style="display:flex;align-items:center;justify-content:space-between;margin-top:10px;">'
        + '    <span style="font-weight:700;">' + adsEsc(price) + '</span>'
        + '    <span class="btn btn-sm btn-dark disabled" style="border-radius:999px;">Visit site</span>'
        + '  </div>'
        + '</div>'
        // limits
        + '<div style="font-size:12px;margin-top:10px;">Title ' + gptCount(title, GPT_TITLE_MAX)
        + ' · Body ' + (body ? gptCount(body, GPT_BODY_MAX) : '<span style="color:#dc3545;">missing</span>') + '</div>';

    if (!gptPreviewModal) gptPreviewModal = new bootstrap.Modal(document.getElementById('gptPreviewModal'));
    gptPreviewModal.show();
}
