document.addEventListener('DOMContentLoaded', function () {

    const mobileToggle = document.getElementById('mobileToggle');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    if (!mobileToggle || !sidebar || !sidebarOverlay) {
        return;
    }

    const icon = mobileToggle.querySelector('i');
    mobileToggle.addEventListener('click', function () {

        const isOpen = sidebar.classList.contains('active');

        if (isOpen) {
            closeSidebar();
        } else {
            openSidebar();
        }

    });

    sidebarOverlay.addEventListener('click', function () {
        closeSidebar();
    });

    const sidebarLinks = sidebar.querySelectorAll('a');

    sidebarLinks.forEach(function (link) {

        link.addEventListener('click', function () {

            if (window.innerWidth <= 991.98) {
                closeSidebar();
            }

        });

    });

    document.addEventListener('keydown', function (event) {

        if (event.key === 'Escape') {
            closeSidebar();
        }

    });

    window.addEventListener('resize', function () {

        if (window.innerWidth > 991.98) {
            closeSidebar();
        }

    });

    function openSidebar() {

        sidebar.classList.add('active');
        sidebarOverlay.classList.add('active');
        document.body.classList.add('sidebar-open');

        if (icon) {
            icon.classList.remove('fa-bars');
            icon.classList.add('fa-xmark');
        }

    }


    function closeSidebar() {

        sidebar.classList.remove('active');
        sidebarOverlay.classList.remove('active');
        document.body.classList.remove('sidebar-open');

        if (icon) {
            icon.classList.remove('fa-xmark');
            icon.classList.add('fa-bars');
        }

    }

});