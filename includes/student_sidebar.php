<?php
$current_page = basename($_SERVER['PHP_SELF']);
if (!function_exists('isActiveS')) {
    function isActiveS($p) { global $current_page; return $current_page === $p ? 'active' : ''; }
}
$user = currentUser();
$base = strpos($_SERVER['SCRIPT_NAME'], '/student/') !== false ? '../' : '';
?>
<div id="sidebar-overlay"></div>

<div class="sidebar d-flex flex-column" id="sidebar">
    <div class="sidebar-brand text-center py-3">
        <img src="../public/images/ANU-Logo-PNG-logo.png" alt="ANU Logo" class="sidebar-logo mb-2" onerror="this.style.display='none'">
        <div class="sidebar-title">ANU Student Portal</div>
    </div>

    <nav class="sidebar-nav flex-grow-1 px-2 py-2">
        <a href="dashboard.php" class="sidebar-link <?= isActiveS('dashboard.php') ?>">
            <i class="bi bi-house me-2"></i> Dashboard
        </a>
        <a href="menu.php" class="sidebar-link <?= isActiveS('menu.php') ?>">
            <i class="bi bi-journal-text me-2"></i> Daily Menu
        </a>
        <a href="my_bookings.php" class="sidebar-link <?= isActiveS('my_bookings.php') ?>">
            <i class="bi bi-calendar-check me-2"></i> My Bookings
        </a>
        <a href="profile.php" class="sidebar-link <?= isActiveS('profile.php') ?>">
            <i class="bi bi-person-circle me-2"></i> My Profile
        </a>
    </nav>

    <div class="sidebar-footer px-2">
        <div class="d-flex align-items-center gap-2 mb-2">
            <div class="avatar-circle"><?= strtoupper(substr($user['fullname'] ?: $user['username'], 0, 1)) ?></div>
            <div class="small text-white" style="min-width:0;">
                <div class="fw-bold text-truncate" style="max-width:150px;"><?= htmlspecialchars($user['fullname'] ?: $user['username']) ?></div>
                <div class="opacity-75" style="font-size:11px;">Student</div>
            </div>
        </div>
        <a href="<?= $base ?>logout.php" class="btn btn-dark w-100 btn-sm">
            <i class="bi bi-box-arrow-right me-1"></i> Logout
        </a>
    </div>
</div>
