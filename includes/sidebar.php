<?php
// Shared sidebar navigation include
// Variables expected: $page_active (string)
$base = '/hmsystem';

$user_initial = strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1));
$user_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'staff';

if (isPatient()) {
    $nav_groups = [
        'MY PORTAL' => [
            ['href' => '/dashboard/index.php', 'icon' => 'fa-gauge-high', 'label' => 'Overview', 'key' => 'dashboard'],
            ['href' => '/appointments/index.php', 'icon' => 'fa-calendar-check', 'label' => 'My Appointments', 'key' => 'appointments'],
            ['href' => '/billing/index.php', 'icon' => 'fa-file-invoice-dollar', 'label' => 'My Bills & Invoices', 'key' => 'billing'],
        ]
    ];
} else {
    $nav_groups = [
        'MAIN' => [
            ['href' => '/dashboard/index.php', 'icon' => 'fa-gauge-high', 'label' => 'Dashboard', 'key' => 'dashboard'],
        ],
        'PATIENT CARE' => [
            ['href' => '/patients/index.php', 'icon' => 'fa-user-injured', 'label' => 'Patients', 'key' => 'patients'],
            ['href' => '/appointments/index.php', 'icon' => 'fa-calendar-check', 'label' => 'Appointments', 'key' => 'appointments'],
            ['href' => '/medical-records/index.php', 'icon' => 'fa-file-medical', 'label' => 'Medical Records', 'key' => 'medical-records'],
        ],
        'FINANCE' => [
            ['href' => '/billing/index.php', 'icon' => 'fa-file-invoice-dollar', 'label' => 'Billing', 'key' => 'billing'],
            ['href' => '/payments/index.php', 'icon' => 'fa-credit-card', 'label' => 'Payments', 'key' => 'payments'],
        ],
    ];

    if (isAdmin()) {
        $nav_groups['ADMINISTRATION'] = [
            ['href' => '/departments/index.php', 'icon' => 'fa-building-hospital', 'label' => 'Departments', 'key' => 'departments'],
            ['href' => '/reports/index.php', 'icon' => 'fa-chart-pie', 'label' => 'Reports', 'key' => 'reports'],
            ['href' => '/users/index.php', 'icon' => 'fa-user-shield', 'label' => 'User Accounts', 'key' => 'users'],
        ];
    }
}
?>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Logout Confirmation Modal -->
<div class="logout-modal-overlay" id="logoutModal" role="dialog" aria-modal="true" aria-labelledby="logoutModalTitle">
    <div class="logout-modal-box">
        <div class="logout-modal-icon">
            <i class="fas fa-right-from-bracket"></i>
        </div>
        <h3 class="logout-modal-title" id="logoutModalTitle">Sign Out</h3>
        <p class="logout-modal-text">Are you sure you want to log out of your Gerona Hospital session?</p>
        <div class="logout-modal-actions">
            <button type="button" class="btn btn-secondary" id="logoutCancelBtn">
                <i class="fas fa-times"></i> Stay Logged In
            </button>
            <a href="<?= $base ?>/auth/logout.php" class="btn btn-primary" id="logoutConfirmBtn">
                <i class="fas fa-right-from-bracket"></i> Yes, Logout
            </a>
        </div>
    </div>
</div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <div class="sidebar-logo-icon">
                <i class="fas fa-hospital"></i>
            </div>
            <div class="sidebar-logo-text">
                <h2>Gerona Hospital</h2>
                <p>Gerona, Tarlac</p>
            </div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($nav_groups as $group_title => $items): ?>
        <div class="sidebar-nav-section">
            <div class="sidebar-nav-title"><?= $group_title ?></div>
            <?php foreach ($items as $item): ?>
            <a href="<?= $base . $item['href'] ?>" class="sidebar-link <?= ($page_active === $item['key']) ? 'active' : '' ?>">
                <i class="fas <?= $item['icon'] ?>"></i>
                <span><?= $item['label'] ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user-card mb-2">
            <div class="sidebar-user-avatar">
                <?= $user_initial ?>
            </div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?= htmlspecialchars($user_name) ?></div>
                <div class="sidebar-user-role"><?= htmlspecialchars($user_role) ?></div>
            </div>
        </div>
        <button type="button" class="sidebar-logout-btn" id="sidebarLogoutBtn">
            <i class="fas fa-right-from-bracket"></i>
            <span>Logout</span>
        </button>
    </div>
</aside>
