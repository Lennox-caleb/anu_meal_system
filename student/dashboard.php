<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireLogin();
if ($_SESSION['role'] !== 'student') { header('Location: ../admin/dashboard.php'); exit; }

$uid = $_SESSION['user_id'];

// Stats for this student
$my_total    = $conn->query("SELECT COUNT(*) c FROM bookings WHERE user_id=$uid")->fetch_assoc()['c'];
$my_consumed = $conn->query("SELECT COUNT(*) c FROM bookings WHERE user_id=$uid AND status='consumed'")->fetch_assoc()['c'];
$my_pending  = $conn->query("SELECT COUNT(*) c FROM bookings WHERE user_id=$uid AND status='pending'")->fetch_assoc()['c'];

// Today's menu
$today_menus = $conn->query("SELECT * FROM menus WHERE date=CURDATE() AND available=1 ORDER BY type ASC");

// Recent bookings
$recent = $conn->query("
    SELECT b.*, m.name as meal_name, m.type as meal_type
    FROM bookings b JOIN menus m ON b.menu_id=m.id
    WHERE b.user_id=$uid ORDER BY b.created_at DESC LIMIT 5
");

$user = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | ANU</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../public/css/style.css">
</head>
<body>
<div class="d-flex">
    <?php include '../includes/student_sidebar.php'; ?>

    <div class="main-content flex-grow-1">
        <div class="topbar d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm d-md-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
                <h1><i class="bi bi-house me-2"></i>My Dashboard</h1>
            </div>
            <span class="badge rounded-pill text-bg-danger"><?= date('D, d M Y') ?></span>
        </div>

        <div class="p-4 fade-in-up">
            <!-- Welcome Banner -->
            <div class="p-4 rounded-3 mb-4 text-white" style="background:var(--anu-gradient);">
                <h4 class="fw-bold mb-1">Welcome back, <?= htmlspecialchars($user['fullname']) ?>! 👋</h4>
                <p class="mb-0 opacity-90 small">Book your meals for today and upcoming days. Stay nourished!</p>
            </div>

            <!-- Stats -->
            <div class="row g-3 mb-4">
                <div class="col-4">
                    <div class="stat-card text-center">
                        <div class="count"><?= $my_total ?></div>
                        <div class="label">Total Bookings</div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="stat-card text-center" style="border-color:#fac823;">
                        <div class="count" style="color:#b8860b;"><?= $my_pending ?></div>
                        <div class="label">Pending</div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="stat-card text-center" style="border-color:#17a2b8;">
                        <div class="count" style="color:#17a2b8;"><?= $my_consumed ?></div>
                        <div class="label">Consumed</div>
                    </div>
                </div>
            </div>

            <!-- Today's Menu Preview -->
            <h6 class="section-title">Today's Menu</h6>
            <?php if ($today_menus->num_rows > 0): ?>
            <div class="row g-3 mb-4">
                <?php while ($m = $today_menus->fetch_assoc()): ?>
                <div class="col-md-4">
                    <div class="menu-card">
                        <div class="menu-card-header d-flex justify-content-between align-items-center">
                            <span class="fw-bold"><?= htmlspecialchars($m['name']) ?></span>
                            <span class="menu-badge <?= strtolower($m['type']) ?>"><?= $m['type'] ?></span>
                        </div>
                        <div class="p-3">
                            <p class="small text-muted mb-2"><?= htmlspecialchars($m['description'] ?: 'Freshly prepared meal.') ?></p>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-danger">KES <?= number_format($m['price'], 2) ?></span>
                                <a href="menu.php" class="btn btn-anu btn-sm py-0 px-3">Book Now</a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="alert alert-info mb-4"><i class="bi bi-info-circle me-1"></i>No menu available for today. Check back later!</div>
            <?php endif; ?>

            <!-- Recent Bookings -->
            <h6 class="section-title">Recent Bookings</h6>
            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Code</th><th>Meal</th><th>Date</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php while ($b = $recent->fetch_assoc()): ?>
                            <tr>
                                <td><code class="small"><?= htmlspecialchars($b['code']) ?></code></td>
                                <td>
                                    <?= htmlspecialchars($b['meal_name']) ?>
                                    <span class="menu-badge <?= strtolower($b['meal_type']) ?> ms-1"><?= $b['meal_type'] ?></span>
                                </td>
                                <td class="small"><?= date('d M Y', strtotime($b['date'])) ?></td>
                                <td><span class="status-badge badge-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if ($my_total == 0): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">No bookings yet. <a href="menu.php">Book a meal!</a></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-3">
                <a href="my_bookings.php" class="btn btn-outline-anu btn-sm">View All Bookings →</a>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('show');
});
</script>
</body>
</html>
