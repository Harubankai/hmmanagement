document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('mobileSidebarToggle');
    const overlay = document.getElementById('sidebarOverlay');

    // ──────────────────────────────────────────────
    //  Sidebar open / close (mobile)
    // ──────────────────────────────────────────────
    function openSidebar() {
        if (sidebar) sidebar.classList.add('active');
        if (overlay) overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('active');
        if (overlay) overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar && sidebar.classList.contains('active') ? closeSidebar() : openSidebar();
        });
    }

    if (overlay) overlay.addEventListener('click', closeSidebar);

    window.addEventListener('resize', function() {
        if (window.innerWidth > 1024) closeSidebar();
    });

    document.querySelectorAll('.sidebar-link').forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 1024) closeSidebar();
        });
    });

    // ──────────────────────────────────────────────
    //  Logout Confirmation Modal
    // ──────────────────────────────────────────────
    const logoutModal   = document.getElementById('logoutModal');
    const logoutBtn     = document.getElementById('sidebarLogoutBtn');
    const cancelBtn     = document.getElementById('logoutCancelBtn');

    function openLogoutModal() {
        if (logoutModal) {
            logoutModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeLogoutModal() {
        if (logoutModal) {
            logoutModal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    if (logoutBtn)  logoutBtn.addEventListener('click', openLogoutModal);
    if (cancelBtn)  cancelBtn.addEventListener('click', closeLogoutModal);

    // Click backdrop to close
    if (logoutModal) {
        logoutModal.addEventListener('click', function(e) {
            if (e.target === logoutModal) closeLogoutModal();
        });
    }

    // Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeSidebar();
            closeLogoutModal();
        }
    });

    // ──────────────────────────────────────────────
    //  Global Password Field Eye Icon Toggle
    // ──────────────────────────────────────────────
    document.addEventListener('click', function(e) {
        const toggleIcon = e.target.closest('.password-toggle-icon');
        if (toggleIcon) {
            const targetId = toggleIcon.getAttribute('data-target');
            let input = targetId ? document.getElementById(targetId) : null;
            if (!input) {
                const wrapper = toggleIcon.closest('.password-field-wrapper');
                if (wrapper) {
                    input = wrapper.querySelector('input');
                }
            }

            if (input) {
                if (input.type === 'password') {
                    input.type = 'text';
                    toggleIcon.classList.remove('fa-eye');
                    toggleIcon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    toggleIcon.classList.remove('fa-eye-slash');
                    toggleIcon.classList.add('fa-eye');
                }
            }
        }
    });
});

