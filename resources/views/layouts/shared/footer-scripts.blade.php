<!-- bundle -->
@yield('script')
<!-- App js -->
@yield('script-bottom')
<script>
    // Only run if searchMenuItem exists (not on login page)
    const searchMenuItem = document.getElementById('searchMenuItem');
    if (searchMenuItem) {
        searchMenuItem.addEventListener('input', function () {
            const query = this.value.toLowerCase().trim();

            // If empty query, reset everything
            if (query === '') {
                document.querySelectorAll('.side-nav-item, .side-nav-second-level li, .side-nav-third-level li, .side-nav-forth-level li').forEach(item => {
                    item.style.display = '';
                });
                document.querySelectorAll('.collapse').forEach(collapse => collapse.classList.remove('show'));
                document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(link => link.setAttribute('aria-expanded', 'false'));
                return;
            }

            // Select all top-level menu items
            const topLevelItems = document.querySelectorAll('.side-nav > .side-nav-item');

            topLevelItems.forEach(topItem => {
                // Check if top item matches
                const topItemText = topItem.querySelector('.side-nav-link')?.textContent.toLowerCase() || '';
                const topItemMatches = topItemText.includes(query);

                // Check if any child matches
                let hasMatchingChild = false;
                const allChildLinks = topItem.querySelectorAll('.side-nav-second-level a, .side-nav-third-level a, .side-nav-forth-level a');
                allChildLinks.forEach(link => {
                    const text = link.textContent.toLowerCase();
                    if (text.includes(query)) {
                        hasMatchingChild = true;
                    }
                });

                // Show/hide top level item
                if (topItemMatches || hasMatchingChild) {
                    topItem.style.display = '';
                    
                    // If parent matches, show ALL children
                    if (topItemMatches) {
                        // Show all second level items
                        const secondLevelItems = topItem.querySelectorAll('.side-nav-second-level > li');
                        secondLevelItems.forEach(secondItem => {
                            secondItem.style.display = '';
                            
                            // Show all third level items
                            const thirdLevelItems = secondItem.querySelectorAll('.side-nav-third-level > li');
                            thirdLevelItems.forEach(thirdItem => {
                                thirdItem.style.display = '';
                                
                                // Show all fourth level items
                                const fourthLevelItems = thirdItem.querySelectorAll('.side-nav-forth-level > li');
                                fourthLevelItems.forEach(fourthItem => {
                                    fourthItem.style.display = '';
                                });
                                
                                // Expand fourth level collapses
                                const fourthCollapse = thirdItem.querySelector('.collapse');
                                if (fourthCollapse) {
                                    fourthCollapse.classList.add('show');
                                    const fourthToggleLink = thirdItem.querySelector(`[data-bs-toggle="collapse"]`);
                                    if (fourthToggleLink) fourthToggleLink.setAttribute('aria-expanded', 'true');
                                }
                            });
                            
                            // Expand third level collapses
                            const thirdCollapse = secondItem.querySelector('.collapse');
                            if (thirdCollapse) {
                                thirdCollapse.classList.add('show');
                                const thirdToggleLink = secondItem.querySelector(`[data-bs-toggle="collapse"]`);
                                if (thirdToggleLink) thirdToggleLink.setAttribute('aria-expanded', 'true');
                            }
                        });
                        
                        // Expand second level collapse
                        const collapse = topItem.querySelector('.collapse');
                        if (collapse) {
                            collapse.classList.add('show');
                            const toggleLink = topItem.querySelector(`[data-bs-toggle="collapse"]`);
                            if (toggleLink) toggleLink.setAttribute('aria-expanded', 'true');
                        }
                    } else {
                        // If parent doesn't match, only show matching children
                        const secondLevelItems = topItem.querySelectorAll('.side-nav-second-level > li');
                        secondLevelItems.forEach(secondItem => {
                            const secondText = secondItem.textContent.toLowerCase();
                            const secondMatches = secondText.includes(query);

                            if (secondMatches) {
                                secondItem.style.display = '';
                                
                                // Show all children of matching second level item
                                const thirdLevelItems = secondItem.querySelectorAll('.side-nav-third-level > li');
                                thirdLevelItems.forEach(thirdItem => {
                                    thirdItem.style.display = '';
                                    
                                    const fourthLevelItems = thirdItem.querySelectorAll('.side-nav-forth-level > li');
                                    fourthLevelItems.forEach(fourthItem => {
                                        fourthItem.style.display = '';
                                    });
                                });
                                
                                // Expand collapses for matching item
                                const thirdCollapse = secondItem.querySelector('.collapse');
                                if (thirdCollapse) {
                                    thirdCollapse.classList.add('show');
                                    const thirdToggleLink = secondItem.querySelector(`[data-bs-toggle="collapse"]`);
                                    if (thirdToggleLink) thirdToggleLink.setAttribute('aria-expanded', 'true');
                                }
                            } else {
                                secondItem.style.display = 'none';
                            }
                        });
                        
                        // Expand parent collapse to show matching children
                        const collapse = topItem.querySelector('.collapse');
                        if (collapse) {
                            collapse.classList.add('show');
                            const toggleLink = topItem.querySelector(`[data-bs-toggle="collapse"]`);
                            if (toggleLink) toggleLink.setAttribute('aria-expanded', 'true');
                        }
                    }
                } else {
                    topItem.style.display = 'none';
                }
            });
        });
    }
</script>