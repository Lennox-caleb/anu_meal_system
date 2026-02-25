<?php
$current_page = basename($_SERVER['PHP_SELF']);
function isActive($page) {
    global $current_page;
    return $current_page === $page ? 'active' : '';
}
$user = currentUser();
?>
<div class="sidebar d-flex flex-column" id="sidebar">
    <div class="sidebar-brand text-center py-3">
        <img src="../public/images/anu-logo.png" alt="ANU Logo" class="sidebar-logo mb-2" onerror="this.style.display='none'">
        <div class="sidebar-title">ANU Meal Booking</div>
    </div>

    <nav class="sidebar-nav flex-grow-1 px-2">
        <a href="dashboard.php" class="sidebar-link <?= isActive('dashboard.php') ?>">
            <i class="bi bi-speedometer2 me-2"></i> Dashboard
        </a>
        <a href="bookings.php" class="sidebar-link <?= isActive('bookings.php') ?>">
            <i class="bi bi-calendar-check me-2"></i> Booking Management
        </a>
        <a href="menu.php" class="sidebar-link <?= isActive('menu.php') ?>">
            <i class="bi bi-journal-text me-2"></i> Menu Management
        </a>
        <a href="validation.php" class="sidebar-link <?= isActive('validation.php') ?>">
            <i class="bi bi-patch-check me-2"></i> Meal Validation
        </a>
        <a href="reports.php" class="sidebar-link <?= isActive('reports.php') ?>">
            <i class="bi bi-bar-chart-line me-2"></i> Reports
        </a>
        <?php if ($user['role'] === 'super_admin'): ?>
        <a href="users.php" class="sidebar-link <?= isActive('users.php') ?>">
            <i class="bi bi-people me-2"></i> User Management
        </a>
        <a href="settings.php" class="sidebar-link <?= isActive('settings.php') ?>">
            <i class="bi bi-gear me-2"></i> Settings
        </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer px-3 py-3">
        <div class="d-flex align-items-center gap-2 mb-2">
            <div class="avatar-circle">
                <?= strtoupper(substr($user['fullname'] ?: $user['username'], 0, 1)) ?>
            </div>
            <div class="small text-white">
                <div class="fw-bold"><?= htmlspecialchars($user['fullname'] ?: $user['username']) ?></div>
                <div class="opacity-75 text-capitalize"><?= str_replace('_',' ', $user['role']) ?></div>
            </div>
        </div>
        <a href="../logout.php" class="btn btn-dark w-100 btn-sm">
            <i class="bi bi-box-arrow-right me-1"></i> Logout
        </a>
    </div>
</div>
