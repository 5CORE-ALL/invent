/**
 * Global product-image hover preview.
 * Shows a large floating preview when hovering small thumbnails app-wide.
 * Opt out: class "no-img-hover" or data-no-img-hover on the img / ancestor.
 */
(function () {
    if (window.__globalImgHoverInit) return;
    window.__globalImgHoverInit = true;

    var STYLE =
        '#global-img-hover-preview{' +
        'position:fixed;display:none;z-index:200060;pointer-events:none;' +
        'width:auto;height:auto;' +
        'max-width:min(420px,90vw)!important;max-height:min(420px,80vh)!important;' +
        'object-fit:contain;background:#fff;border-radius:10px;padding:4px;' +
        'box-shadow:0 8px 32px rgba(0,0,0,.35);border:1px solid rgba(0,0,0,.08);' +
        '}' +
        'img.global-img-hover-active{cursor:zoom-in;}';

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

        var w = img.clientWidth || 0;
        var h = img.clientHeight || 0;
        // Skip tiny icons and images that are already large on screen
        if (w > 0 && h > 0) {
            if (w < 18 || h < 18) return false;
            if (w > 180 || h > 180) return false;
        }
        return true;
    }

    var popup = null;
    var activeImg = null;
    var moveRaf = false;
    var cx = 0;
    var cy = 0;

    function position() {
        if (!popup || popup.style.display !== 'block') return;
        var pad = 16;
        var vw = window.innerWidth;
        var vh = window.innerHeight;
        var pw = popup.offsetWidth || 320;
        var ph = popup.offsetHeight || 320;
        var x = cx + pad;
        var y = cy + pad;
        if (x + pw > vw - 8) x = cx - pw - pad;
        if (y + ph > vh - 8) y = cy - ph - pad;
        if (x < 8) x = 8;
        if (y < 8) y = 8;
        popup.style.left = x + 'px';
        popup.style.top = y + 'px';
    }

    function show(img, e) {
        popup = ensurePopup();
        if (activeImg && activeImg !== img) {
            activeImg.classList.remove('global-img-hover-active');
        }
        activeImg = img;
        img.classList.add('global-img-hover-active');
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
