<!-- bundle -->
<?php echo $__env->yieldContent('script'); ?>
<!-- App js -->
<?php echo $__env->yieldContent('script-bottom'); ?>
<script>
    document.getElementById('searchMenuItem').addEventListener('input', function () {
    const query = this.value.toLowerCase().trim();

    // Select all menu items across levels
    const allMenuItems = document.querySelectorAll('.side-nav-item, .side-nav-second-level li, .side-nav-third-level li');

    allMenuItems.forEach(item => {
        const text = item.textContent.toLowerCase();
        const matches = text.includes(query);

        // Show or hide based on match
        item.style.display = matches || query === '' ? '' : 'none';

        // Expand parent collapses if match found
        if (matches) {
            const parentCollapse = item.closest('.collapse');
            if (parentCollapse) {
                parentCollapse.classList.add('show');
                const toggleLink = document.querySelector(`[href="#${parentCollapse.id}"]`);
                if (toggleLink) toggleLink.setAttribute('aria-expanded', 'true');
            }
        }
    });

    // Optionally collapse all if query is empty
    if (query === '') {
        document.querySelectorAll('.collapse').forEach(collapse => collapse.classList.remove('show'));
        document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(link => link.setAttribute('aria-expanded', 'false'));
    }
});
</script><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/5core_inv/resources/views/layouts/shared/footer-scripts.blade.php ENDPATH**/ ?>