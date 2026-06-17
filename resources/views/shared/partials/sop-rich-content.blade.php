{{-- Shared SOP-style markdown/HTML viewer + editor (used by audit SOP and purchase Info modals). --}}
@once
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/marked@12.0.2/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dompurify@3.1.6/dist/purify.min.js"></script>
<style>
    .sop-mode-hint {
        padding: 8px 16px;
        font-size: 12.5px;
        color: #495057;
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
    }
    .sop-viewer {
        min-height: 45vh;
        max-height: 65vh;
        overflow-y: auto;
        padding: 20px 24px;
        border: 1px solid #e9ecef;
        border-radius: 6px;
        background: #fff;
        font-size: 14.5px;
        line-height: 1.6;
        color: #212529;
        word-wrap: break-word;
        font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Apple Color Emoji", "Segoe UI Emoji", "Noto Color Emoji", sans-serif;
    }
    .sop-viewer:empty::before {
        content: "No content has been added yet. " attr(data-empty-hint);
        color: #adb5bd;
        font-style: italic;
    }
    .sop-viewer h1, .sop-viewer h2, .sop-viewer h3, .sop-viewer h4, .sop-viewer h5, .sop-viewer h6 {
        margin: 18px 0 10px;
        font-weight: 700;
        line-height: 1.3;
        color: #0d47a1;
    }
    .sop-viewer h1 { font-size: 24px; border-bottom: 2px solid #e3f2fd; padding-bottom: 6px; }
    .sop-viewer h2 { font-size: 20px; border-bottom: 1px solid #e9ecef; padding-bottom: 4px; }
    .sop-viewer h3 { font-size: 17px; }
    .sop-viewer h4 { font-size: 15px; color: #1565c0; }
    .sop-viewer h5, .sop-viewer h6 { font-size: 14px; color: #1976d2; }
    .sop-viewer h1:first-child, .sop-viewer h2:first-child, .sop-viewer h3:first-child { margin-top: 0; }
    .sop-viewer p { margin: 0 0 10px; }
    .sop-viewer ul, .sop-viewer ol { margin: 6px 0 12px; padding-left: 26px; }
    .sop-viewer li { margin: 2px 0; }
    .sop-viewer li > p { margin: 0; }
    .sop-viewer blockquote {
        margin: 10px 0;
        padding: 8px 14px;
        border-left: 4px solid #1976d2;
        background: #f5faff;
        color: #495057;
        border-radius: 0 4px 4px 0;
    }
    .sop-viewer hr { border: 0; border-top: 1px dashed #ced4da; margin: 18px 0; }
    .sop-viewer a { color: #1565c0; text-decoration: underline; word-break: break-word; }
    .sop-viewer a:hover { color: #0d47a1; }
    .sop-viewer img {
        max-width: 220px;
        max-height: 160px;
        width: auto;
        height: auto;
        object-fit: contain;
        display: inline-block;
        vertical-align: middle;
        margin: 6px 8px 6px 0;
        border: 1px solid #e9ecef;
        border-radius: 6px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        background: #fff;
        cursor: zoom-in;
    }
    .sop-viewer .sop-gallery {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin: 10px 0 14px;
    }
    .sop-viewer .sop-gallery img { margin: 0; }
    .sop-viewer table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 13.5px; }
    .sop-viewer table, .sop-viewer th, .sop-viewer td { border: 1px solid #dee2e6; }
    .sop-viewer th { background: #f1f3f5; font-weight: 600; }
    .sop-viewer th, .sop-viewer td { padding: 8px 10px; vertical-align: top; }
    .sop-viewer tr:nth-child(even) td { background: #fbfcfe; }
    .sop-viewer code {
        background: #f1f3f5;
        color: #c7254e;
        padding: 1px 5px;
        border-radius: 3px;
        font-size: 0.9em;
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    }
    .sop-viewer pre {
        background: #f8f9fa;
        color: #212529;
        padding: 12px 14px;
        border-radius: 6px;
        overflow-x: auto;
        border: 1px solid #e9ecef;
    }
    .sop-viewer pre code { background: transparent; color: inherit; padding: 0; }
    .sop-viewer input[type="checkbox"] { margin-right: 6px; transform: translateY(1px); }
    .sop-viewer .task-list-item { list-style: none; }
    .sop-editor {
        width: 100%;
        min-height: 55vh;
        max-height: 70vh;
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-size: 12.5px;
        line-height: 1.45;
        border: 1px solid #ced4da;
        border-radius: 6px;
        padding: 12px 14px;
        resize: vertical;
    }
    .sop-editor:focus {
        outline: none;
        border-color: #1E90FF;
        box-shadow: 0 0 0 3px rgba(30, 144, 255, 0.18);
    }
    .sop-footer-status { font-size: 12px; color: #6c757d; }
    .sop-footer-status.is-error { color: #b71c1c; }
    .sop-footer-status.is-success { color: #2e7d32; }
    .sop-lightbox-backdrop {
        position: fixed;
        inset: 0;
        z-index: 20000;
        background: rgba(0, 0, 0, 0.88);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
    }
    .sop-lightbox-backdrop img {
        max-width: min(96vw, 1200px);
        max-height: 92vh;
        object-fit: contain;
        border-radius: 8px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.45);
    }
    .sop-lightbox-close {
        position: fixed;
        top: 16px;
        right: 20px;
        z-index: 20001;
        border: none;
        background: rgba(255, 255, 255, 0.15);
        color: #fff;
        font-size: 28px;
        line-height: 1;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        cursor: pointer;
    }
</style>
<script>
(function () {
    if (window.SopRichContent) return;

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function looksLikeHtml(text) {
        if (!text) return false;
        const t = text.trim();
        if (!t) return false;
        if (/^<!doctype\s+html/i.test(t)) return true;
        if (/^<(html|body|div|section|article|h[1-6]|p|table|ul|ol|img|figure)\b/i.test(t)) return true;
        const open = (t.match(/<[a-z][a-z0-9]*\b[^>]*>/gi) || []).length;
        const close = (t.match(/<\/[a-z][a-z0-9]*\s*>/gi) || []).length;
        return open >= 3 && close >= 2;
    }

    function isImageOnlyParagraph(node) {
        if (!node || node.nodeType !== 1 || node.tagName !== 'P') return false;
        if (node.querySelector('img') === null) return false;
        for (const child of node.childNodes) {
            if (child.nodeType === 3 && child.textContent.trim() !== '') return false;
            if (child.nodeType === 1) {
                const tag = child.tagName.toLowerCase();
                if (tag !== 'img' && tag !== 'br') return false;
            }
        }
        return true;
    }

    function groupImagesIntoGallery(root) {
        if (!root) return;
        const children = Array.from(root.children);
        let i = 0;
        while (i < children.length) {
            if (!isImageOnlyParagraph(children[i])) { i++; continue; }
            let j = i;
            const group = [];
            while (j < children.length && isImageOnlyParagraph(children[j])) {
                group.push(children[j]);
                j++;
            }
            const totalImgs = group.reduce(function (n, p) { return n + p.querySelectorAll('img').length; }, 0);
            if (group.length >= 2 || totalImgs >= 2) {
                const gallery = document.createElement('div');
                gallery.className = 'sop-gallery';
                group.forEach(function (p) {
                    p.querySelectorAll('img').forEach(function (img) { gallery.appendChild(img); });
                });
                root.insertBefore(gallery, group[0]);
                group.forEach(function (p) { p.remove(); });
            }
            i = j;
        }
    }

    function closeLightbox() {
        document.querySelectorAll('.sop-lightbox-backdrop').forEach(function (el) { el.remove(); });
        document.removeEventListener('keydown', closeLightboxOnEsc);
    }

    function closeLightboxOnEsc(ev) {
        if (ev.key === 'Escape') closeLightbox();
    }

    function openLightbox(src, alt) {
        closeLightbox();
        const backdrop = document.createElement('div');
        backdrop.className = 'sop-lightbox-backdrop';
        backdrop.setAttribute('role', 'dialog');
        backdrop.setAttribute('aria-modal', 'true');
        const closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'sop-lightbox-close';
        closeBtn.setAttribute('aria-label', 'Close');
        closeBtn.innerHTML = '&times;';
        const img = document.createElement('img');
        img.src = src;
        img.alt = alt || 'Image';
        backdrop.appendChild(closeBtn);
        backdrop.appendChild(img);
        backdrop.addEventListener('click', function (ev) {
            if (ev.target === backdrop || ev.target === closeBtn) closeLightbox();
        });
        document.body.appendChild(backdrop);
        document.addEventListener('keydown', closeLightboxOnEsc);
    }

    function bindViewerImages(viewerEl) {
        if (!viewerEl || viewerEl.dataset.sopImgBound === '1') return;
        viewerEl.dataset.sopImgBound = '1';
        viewerEl.addEventListener('click', function (ev) {
            const img = ev.target.closest('img');
            if (!img || !viewerEl.contains(img)) return;
            ev.preventDefault();
            const src = img.getAttribute('src');
            if (src) openLightbox(src, img.getAttribute('alt'));
        });
    }

    window.SopRichContent = {
        looksLikeHtml: looksLikeHtml,
        renderInto: function (viewerEl, raw) {
            if (!viewerEl) return raw || '';
            raw = raw || '';
            let html;
            if (looksLikeHtml(raw)) {
                html = raw;
            } else if (window.marked) {
                try {
                    marked.use({ gfm: true, breaks: true });
                    html = marked.parse(raw);
                } catch (e) {
                    html = '<pre>' + escapeHtml(raw) + '</pre>';
                }
            } else {
                html = '<pre>' + escapeHtml(raw) + '</pre>';
            }
            if (window.DOMPurify) {
                html = DOMPurify.sanitize(html, {
                    USE_PROFILES: { html: true },
                    ADD_ATTR: ['target'],
                });
            }
            viewerEl.innerHTML = html;
            viewerEl.querySelectorAll('a[href]').forEach(function (a) {
                a.setAttribute('target', '_blank');
                a.setAttribute('rel', 'noopener noreferrer');
            });
            viewerEl.querySelectorAll('img').forEach(function (img) {
                if (!img.getAttribute('title')) img.setAttribute('title', 'Click to enlarge');
                if (!img.getAttribute('alt')) img.setAttribute('alt', 'Content image');
                img.setAttribute('loading', 'lazy');
            });
            groupImagesIntoGallery(viewerEl);
            bindViewerImages(viewerEl);
            return raw;
        },
    };
})();
</script>
@endonce
