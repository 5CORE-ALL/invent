/**
 * Global product-image hover preview.
 * Shows a large floating preview, ALWAYS centered in the viewport (same as /forecast.analysis),
 * when hovering small thumbnails app-wide. The page-specific preview boxes listed in
 * CENTER_SELECTORS below are forced to the same centered position via CSS, so every page behaves alike.
 * Opt out: class "no-img-hover" or data-no-img-hover on the img / ancestor.
 */
(function () {
    if (window.__globalImgHoverInit) return;
    window.__globalImgHoverInit = true;

    // Page-specific hover preview boxes (their own JS positions them by cursor / anchor).
    // Forced to the viewport center so every page matches /forecast.analysis.
    var CENTER_SELECTORS = [
        '#image-hover-preview',
        '#compliance-img-hover-preview',
        '#cell-image-preview',
        '#image-preview-popup',
        '#comparison-cd-image-hover-preview',
        '#mercWsOpImgHoverPreview',
        '#mercWosOpImgHoverPreview',
        '#vnOpImgHoverPreview',
        '#dpOpImgHoverPreview',
        '#ads-image-preview',
        '#vnt-img-hover-preview',
        '#titleMasterImgPreview',
        '#spDwImgHover',
        '#sku-link-lmp-image-hover-preview',
        '#sku-link-lmp-amz-image-hover-preview',
        '#wf-image-hover-preview',
        '#fr-image-hover-preview',
        '#ne-img-preview',
        '#ebay2op-img-hover-preview',
        '#dws-img-hover-preview',
        '#skuImageTooltip',
        '#optionsHoverPreview',
        '#ovl30SkuThumbPreview',
        '#scPreview',
        '#task-avatar-flyout',
        '#task-summary-avatar-flyout',
        'img.preview-popup',
        '.popup-img',
        '.zoomed-photo',
        '.zoomed-barcode',
        '.image-hover-preview',
        '.image-hover-container .hover-preview',
        // CD (Comparison Data) hover cards on /to-order-analysis and /purchase-master/comparison
        '#toa-cd-hover-preview.cd-hover-centered',
        '#cd-hover-preview.cd-hover-centered'
    ];

    var STYLE =
        '#global-img-hover-preview{' +
        'position:fixed;display:none;z-index:200060;pointer-events:none;' +
        'width:auto;height:auto;' +
        'max-width:min(480px,80vw)!important;max-height:min(480px,80vh)!important;' +
        'object-fit:contain;background:#fff;border-radius:6px;padding:4px;' +
        'box-shadow:0 4px 16px rgba(0,0,0,.18);border:1px solid #ccc;' +
        '}' +
        '#global-img-hover-preview.is-large{' +
        'max-width:min(680px,88vw)!important;max-height:min(680px,82vh)!important;' +
        'border-radius:10px;padding:6px;' +
        '}' +
        '#global-img-hover-preview.is-circle{' +
        'width:min(280px,70vw);height:min(280px,70vw);' +
        'max-width:min(280px,70vw)!important;max-height:min(280px,70vw)!important;' +
        'border-radius:50%;object-fit:cover;padding:0;border:3px solid #fff;' +
        '}' +
        'img.global-img-hover-active{cursor:zoom-in;}' +
        // Centered placement for every page-specific preview box (does not touch show/hide).
        CENTER_SELECTORS.join(',') + '{' +
        'position:fixed!important;left:50%!important;top:50%!important;' +
        'right:auto!important;bottom:auto!important;margin:0!important;' +
        'transform:translate(-50%,-50%)!important;pointer-events:none!important;' +
        '}';

    var styleEl = document.createElement('style');
    styleEl.textContent = STYLE;
    (document.head || document.documentElement).appendChild(styleEl);

    function ensurePopup() {
        var el = document.getElementById('global-img-hover-preview');
        if (el) return el;
        el = document.createElement('img');
        el.id = 'global-img-hover-preview';
        el.alt = '';
        (document.body || document.documentElement).appendChild(el);
        return el;
    }

    var SKIP_CLOSEST = [
        '.side-nav',
        '.leftside-menu',
        '.navbar-custom',
        '.logo-box',
        '.topnav',
        '.logo',
        '#global-img-hover-preview',
        '.dws-img-hover-wrap',
        '#ne-img-preview',
        '#image-hover-preview',
        '#skuImageTooltip',
        '.sku-tooltip',
        '.image-hover',
        '#toa-cd-hover-preview',
        '#dws-img-hover-preview',
        '#ebay2op-img-hover-preview',
        '#comparison-cd-image-hover-preview',
        '.product-image-thumbnail',
        '.product-image-enlarged',
        '.no-img-hover',
        '[data-no-img-hover]',
        '.cd-sheet-table',
        '.cd-sheet-cell-image',
        '.task-summary-avatar-wrap',
        '.task-summary-avatar',
        '#task-summary-avatar-flyout',
    ].join(',');

    function isEligible(img) {
        if (!img || img.tagName !== 'IMG') return false;
        if (img.id === 'global-img-hover-preview') return false;
        if (img.classList && img.classList.contains('no-img-hover')) return false;
        if (img.getAttribute && img.getAttribute('data-no-img-hover') != null) return false;
        if (img.closest && img.closest(SKIP_CLOSEST)) return false;

        var src = (img.currentSrc || img.src || '').trim();
        if (!src) return false;
        if (/^data:image\/svg/i.test(src)) return false;

        var zoom = img.closest && img.closest('[data-hover-zoom]');
        var w = img.clientWidth || 0;
        var h = img.clientHeight || 0;
        // Skip tiny icons and images that are already large on screen
        if (w > 0 && h > 0) {
            if (w < 18 || h < 18) return false;
            if (!zoom && (w > 180 || h > 180)) return false;
        }
        return true;
    }

    var popup = null;
    var activeImg = null;
    var moveRaf = false;
    var cx = 0;
    var cy = 0;

    // Always centered in the viewport (cursor position is ignored).
    function position() {
        if (!popup || popup.style.display !== 'block') return;
        popup.style.left = '50%';
        popup.style.top = '50%';
        popup.style.transform = 'translate(-50%, -50%)';
    }

    function show(img, e) {
        popup = ensurePopup();
        if (activeImg && activeImg !== img) {
            activeImg.classList.remove('global-img-hover-active');
        }
        activeImg = img;
        img.classList.add('global-img-hover-active');
        var circle = !!(img.classList && (img.classList.contains('rounded-circle') || img.classList.contains('task-avatar-hover')));
        popup.classList.toggle('is-circle', circle);
        popup.classList.toggle('is-large', !circle && !!(img.closest && img.closest('[data-hover-zoom]')));
        // head-css forces "width/height:auto !important; object-fit:contain !important" on the popup,
        // so the circle styles must be applied with !important too or the preview stays rectangular.
        var circleProps = {
            'width': '240px',
            'height': '240px',
            'max-width': '240px',
            'max-height': '240px',
            'border-radius': '50%',
            'object-fit': 'cover',
            'padding': '0'
        };
        Object.keys(circleProps).forEach(function (prop) {
            if (circle) {
                popup.style.setProperty(prop, circleProps[prop], 'important');
            } else {
                popup.style.removeProperty(prop);
            }
        });
        popup.src = img.currentSrc || img.src;
        popup.style.display = 'block';
        cx = e.clientX;
        cy = e.clientY;
        position();
        if (!popup.complete) {
            popup.onload = function () {
                position();
            };
        }
    }

    function hide() {
        if (activeImg) {
            activeImg.classList.remove('global-img-hover-active');
            activeImg = null;
        }
        if (!popup) return;
        popup.style.display = 'none';
        popup.removeAttribute('src');
        popup.onload = null;
    }

    function onOver(e) {
        var img = e.target && e.target.closest ? e.target.closest('img') : null;
        if (!isEligible(img)) return;
        var from = e.relatedTarget;
        if (from && img.contains && img.contains(from)) return;
        show(img, e);
    }

    function onOut(e) {
        var img = e.target && e.target.closest ? e.target.closest('img') : null;
        if (!img || img !== activeImg) return;
        var to = e.relatedTarget;
        if (to && img.contains && img.contains(to)) return;
        if (to && to.closest) {
            var next = to.closest('img');
            if (next && isEligible(next)) {
                show(next, e);
                return;
            }
        }
        hide();
    }

    function onMove(e) {
        if (!popup || popup.style.display !== 'block') return;
        cx = e.clientX;
        cy = e.clientY;
        if (moveRaf) return;
        moveRaf = true;
        requestAnimationFrame(function () {
            moveRaf = false;
            position();
        });
    }

    function bind() {
        document.addEventListener('mouseover', onOver, true);
        document.addEventListener('mouseout', onOut, true);
        document.addEventListener('mousemove', onMove, true);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }
})();
