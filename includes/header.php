<?php
// Shared Header component matching reference UI design
$user_initial = strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1));
?>
<header class="top-header">
    <div style="display: flex; align-items: center; gap: 14px;">
        <button type="button" class="mobile-sidebar-toggle" id="mobileSidebarToggle" aria-label="Toggle Menu">
            <i class="fas fa-bars"></i>
        </button>
        <div class="breadcrumbs">
            <a href="/hmsystem/dashboard/index.php">Gerona Hospital</a>
            <i class="fas fa-chevron-right" style="font-size: 10px; color: var(--text-light);"></i>
            <span class="active"><?= htmlspecialchars($page_title ?? 'Dashboard') ?></span>
        </div>
    </div>

    <div class="header-right-actions">
        <div class="header-user-btn" title="<?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?>">
            <div class="header-user-avatar">
                <?= $user_initial ?>
            </div>
            <span style="font-size: 13px; font-weight: 600; color: var(--navy-dark);"><?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?></span>
        </div>
    </div>
</header>
<script src="/hmsystem/assets/js/main.js" defer></script>

