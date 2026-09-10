Object.keys(NIROROADMAP_PLUGIN_ADMIN.menus).forEach(key => {

    document.querySelectorAll(`.toplevel_page_${key} .wp-submenu.wp-submenu-wrap > li`).forEach(function(item) {

    	// add .current on-click
        item.addEventListener('click', function() {
            document.querySelectorAll(`.toplevel_page_${key} .wp-submenu.wp-submenu-wrap > li`).forEach(function(li) {
                li.classList.remove('current');
            });
            item.classList.add('current');
        });

        // add .current to current menu
        const link = item.querySelector('a');
        if (link && link.hash && link.hash === window.location.hash) {
            document.querySelectorAll(`.toplevel_page_${key} .wp-submenu.wp-submenu-wrap > li`).forEach(function(li) {
                li.classList.remove('current');
            });
            item.classList.add('current');
        }
    });

    // add # to the first submenu item
    const li = document.querySelector(`#adminmenu li.toplevel_page_${key}`);
    if (li) {
        const firstItem = li.querySelector('a.wp-first-item');
        if (firstItem) {
            const href = firstItem.getAttribute('href');
            if (href) {
                firstItem.setAttribute('href', `${href}#`);
            }
        }
    }
    
});