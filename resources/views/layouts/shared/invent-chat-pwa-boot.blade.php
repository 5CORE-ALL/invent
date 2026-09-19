<script>
(function () {
    try {
        var standalone = (window.matchMedia && (
            window.matchMedia('(display-mode: standalone)').matches ||
            window.matchMedia('(display-mode: fullscreen)').matches ||
            window.matchMedia('(display-mode: minimal-ui)').matches
        )) || window.navigator.standalone === true;
        if (!standalone) {
            return;
        }
        var path = window.location.pathname || '/';
        if (path === '/chat' || path.indexOf('/chat/') === 0) {
            return;
        }
        if (path.indexOf('/auth/') === 0) {
            return;
        }
        if (path === '/offline.html' || path === '/offline') {
            return;
        }
        window.location.replace('/chat');
    } catch (e) {}
})();
</script>
