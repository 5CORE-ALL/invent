<!-- bundle -->
@yield('script')
<!-- App js -->
@yield('script-bottom')
<script>
    (function () {
        const searchMenuItem = document.getElementById('searchMenuItem');
        if (!searchMenuItem) return;

        const sideNav = document.querySelector('.leftside-menu .side-nav');
        if (!sideNav) return;

        const matchesQuery = (text, queryWords) => {
            if (!queryWords.length) return true;
            const t = (text || '').toLowerCase();
            return queryWords.every((word) => t.includes(word));
        };

        const linkLabel = (el) => {
            if (!el) return '';
            const span = el.querySelector(':scope > span:not(.menu-arrow):not(.badge)');
            if (span && span.textContent.trim()) return span.textContent.trim();
            return (el.textContent || '').replace(/\s+/g, ' ').trim();
        };

        const allMenuLis = () => sideNav.querySelectorAll('li');

        const resetVisibility = () => {
            allMenuLis().forEach((item) => {
                item.style.display = '';
            });
            sideNav.querySelectorAll('a').forEach((a) => {
                a.style.display = '';
            });
        };

        const collapseAll = () => {
            sideNav.querySelectorAll('.collapse').forEach((c) => c.classList.remove('show'));
            sideNav.querySelectorAll('[data-bs-toggle="collapse"]').forEach((link) => {
                link.setAttribute('aria-expanded', 'false');
            });
        };

        const expandCollapseEl = (collapseEl) => {
            if (!collapseEl || !collapseEl.classList.contains('collapse')) return;
            collapseEl.classList.add('show');
            const id = collapseEl.id;
            if (!id) return;
            const toggle = sideNav.querySelector('[href="#' + CSS.escape(id) + '"], [data-bs-target="#' + CSS.escape(id) + '"]');
            if (toggle) toggle.setAttribute('aria-expanded', 'true');
        };

        // Show matching pages only; keep ancestor containers for layout but hide non-matching group headers
        const revealMatchingPage = (anchor, queryWords) => {
            const ownLi = anchor.closest('li');
            if (!ownLi) return;

            ownLi.style.display = '';
            anchor.style.display = '';

            let node = ownLi.parentElement;
            while (node && node !== sideNav) {
                if (node.classList && node.classList.contains('collapse')) {
                    expandCollapseEl(node);
                }
                if (node.tagName === 'LI') {
                    node.style.display = '';
                    const headerLink = node.querySelector(':scope > a');
                    if (headerLink) {
                        // Hide group/subgroup header unless the header label itself matches
                        headerLink.style.display = matchesQuery(linkLabel(headerLink), queryWords) ? '' : 'none';
                    }
                }
                node = node.parentElement;
            }
        };

        const runSidebarQuickSearch = () => {
            const query = searchMenuItem.value.toLowerCase().trim();
            const queryWords = query.split(/\s+/).filter(Boolean);

            if (!queryWords.length) {
                resetVisibility();
                collapseAll();
                return;
            }

            collapseAll();
            allMenuLis().forEach((li) => {
                li.style.display = 'none';
            });
            sideNav.querySelectorAll('a').forEach((a) => {
                a.style.display = '';
            });

            const matchedLinks = [...sideNav.querySelectorAll('a')].filter((a) =>
                matchesQuery(linkLabel(a), queryWords)
            );

            matchedLinks.forEach((a) => {
                revealMatchingPage(a, queryWords);
            });
        };

        searchMenuItem.addEventListener('input', runSidebarQuickSearch);
        searchMenuItem.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                this.value = '';
                runSidebarQuickSearch();
            }
        });
    })();
</script>

{{-- Global: any badge with a light background gets black text for readability --}}
<script>
    (function () {
        function brightness(rgb) {
            return (0.299 * rgb[0] + 0.587 * rgb[1] + 0.114 * rgb[2]) / 255;
        }
        function parseRgb(str) {
            const m = (str || '').match(/rgba?\(([^)]+)\)/);
            if (!m) return null;
            const p = m[1].split(',').map((s) => parseFloat(s.trim()));
            if (p.length < 3) return null;
            if (p.length >= 4 && p[3] === 0) return null; // transparent
            return [p[0], p[1], p[2]];
        }
        function fixBadge(el) {
            if (!el || el.dataset.badgeTextFixed === '1') return;
            const rgb = parseRgb(getComputedStyle(el).backgroundColor);
            if (!rgb) return;
            if (brightness(rgb) > 0.6) {
                el.style.setProperty('color', '#000', 'important');
                el.querySelectorAll('*').forEach((c) => c.style.setProperty('color', '#000', 'important'));
            }
            el.dataset.badgeTextFixed = '1';
        }
        function fixAll(root) {
            (root || document).querySelectorAll('.badge').forEach(fixBadge);
        }
        function init() {
            fixAll();
            if (!document.body) return;
            const obs = new MutationObserver(function (muts) {
                muts.forEach(function (m) {
                    (m.addedNodes || []).forEach(function (n) {
                        if (n.nodeType !== 1) return;
                        if (n.classList && n.classList.contains('badge')) fixBadge(n);
                        if (n.querySelectorAll) n.querySelectorAll('.badge').forEach(fixBadge);
                    });
                });
            });
            obs.observe(document.body, { childList: true, subtree: true });
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
</script>
